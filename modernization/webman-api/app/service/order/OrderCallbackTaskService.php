<?php

declare(strict_types=1);

namespace app\service\order;

use app\support\LegacyHttpClient;
use Plugins\Payments\Shared\Legacy\LegacyEpayOrderRepository;
use RuntimeException;
use support\Db;

final class OrderCallbackTaskService
{
    private const TABLE = 'ypay_order_callback_task';
    private const MERCHANT_TEST_ORDER_MEMOS = ['merchant_channel_test_pay', 'merchant_channel_test_paid'];
    private const STALE_LOCK_SECONDS = 180;
    private const RETRY_DELAYS = [5, 15, 30, 60, 120, 300];

    public function __construct(
        private readonly OrderCallbackBuilder $builder = new OrderCallbackBuilder(),
        private readonly LegacyEpayOrderRepository $orders = new LegacyEpayOrderRepository()
    ) {
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed>|null $merchant
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function enqueueForSettledOrder(array $order, ?array $merchant = null, array $options = []): array
    {
        $orderId = (int)($order['id'] ?? 0);
        if ($orderId <= 0) {
            throw new RuntimeException('order id is required when enqueueing merchant callback');
        }

        if ($this->isMerchantChannelTestOrder($order)) {
            $this->updateOrderMemo($orderId, 'merchant_channel_test_paid');

            return [
                'queued' => false,
                'skipped' => true,
                'scene' => 'test_pay',
                'task_id' => 0,
                'notify_url' => '',
                'return_url' => '',
                'callback_url' => '',
                'memo' => 'merchant_channel_test_paid',
                'ok' => true,
                'http_status' => 200,
            ];
        }

        $merchant = $merchant ?? $this->loadMerchant((int)($order['user_id'] ?? 0));
        if ($merchant === null) {
            throw new RuntimeException('merchant was not found for callback enqueue');
        }

        $urls = $this->builder->buildUrls($order, $merchant);
        $notifyUrl = trim((string)($urls['notify'] ?? ''));
        $returnUrl = trim((string)($urls['return'] ?? ''));
        $scene = strtolower(trim((string)($options['scene'] ?? 'settlement'))) ?: 'settlement';
        $forceNew = !empty($options['force_new']);

        if ($notifyUrl === '') {
            $this->updateOrderMemo($orderId, 'notify_url_empty');

            return [
                'queued' => false,
                'skipped' => true,
                'scene' => $scene,
                'task_id' => 0,
                'notify_url' => '',
                'return_url' => $returnUrl,
                'callback_url' => '',
                'memo' => 'notify_url_empty',
                'ok' => true,
                'http_status' => 204,
            ];
        }

        $taskKey = $forceNew
            ? sprintf('order:%d:merchant_notify:%s:%s', $orderId, $scene, bin2hex(random_bytes(6)))
            : sprintf('order:%d:merchant_notify:%s', $orderId, $scene);

        if (!$forceNew) {
            $existing = Db::table(self::TABLE)->where('task_key', $taskKey)->first();
            if ($existing) {
                $existingRecord = (array)$existing;
                $status = trim((string)($existingRecord['status'] ?? ''));

                if (in_array($status, ['pending', 'retry', 'running', 'success'], true)) {
                    return $this->taskSummary($existingRecord, true);
                }

                Db::table(self::TABLE)
                    ->where('id', (int)($existingRecord['id'] ?? 0))
                    ->update([
                        'status' => 'pending',
                        'attempt_count' => 0,
                        'next_run_at' => $this->now(),
                        'locked_at' => null,
                        'started_at' => null,
                        'finished_at' => null,
                        'last_http_status' => null,
                        'last_error' => null,
                        'last_response_body' => null,
                        'callback_url' => $notifyUrl,
                        'return_url' => $returnUrl,
                        'payload_json' => $this->encodeJson($urls['payload']),
                        'update_time' => $this->now(),
                    ]);

                $this->updateOrderMemo($orderId, 'merchant_callback_queued');

                $reloaded = Db::table(self::TABLE)
                    ->where('id', (int)($existingRecord['id'] ?? 0))
                    ->first();

                $reloadedTask = (array)$reloaded;
                if (!empty($options['dispatch_now'])) {
                    return $this->dispatchTaskNow((int)($reloadedTask['id'] ?? 0), true);
                }

                return $this->taskSummary($reloadedTask, true);
            }
        }

        $insert = [
            'task_key' => $taskKey,
            'order_id' => $orderId,
            'merchant_id' => (int)($merchant['id'] ?? 0),
            'trade_no' => trim((string)($order['trade_no'] ?? '')),
            'out_trade_no' => trim((string)($order['out_trade_no'] ?? '')),
            'scene' => $scene,
            'status' => 'pending',
            'attempt_count' => 0,
            'max_attempts' => max(1, (int)($options['max_attempts'] ?? 8)),
            'next_run_at' => $this->now(),
            'locked_at' => null,
            'started_at' => null,
            'finished_at' => null,
            'notify_url' => trim((string)($order['notify_url'] ?? '')),
            'return_url' => $returnUrl,
            'callback_url' => $notifyUrl,
            'payload_json' => $this->encodeJson($urls['payload']),
            'last_http_status' => null,
            'last_error' => null,
            'last_response_body' => null,
            'create_time' => $this->now(),
            'update_time' => $this->now(),
        ];

        $inserted = (int)Db::table(self::TABLE)->insertOrIgnore($insert);
        $stored = Db::table(self::TABLE)->where('task_key', $taskKey)->first();
        if (!$stored) {
            throw new RuntimeException('Failed to persist merchant callback task');
        }
        $insert = (array)$stored;
        $this->updateOrderMemo($orderId, 'merchant_callback_queued');

        if (!empty($options['dispatch_now'])) {
            return $this->dispatchTaskNow((int)($insert['id'] ?? 0), $inserted === 0);
        }

        return $this->taskSummary($insert, $inserted === 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function claimDueTask(): ?array
    {
        return Db::transaction(function (): ?array {
            $now = $this->now();
            $staleAt = $this->dateTime(time() - self::STALE_LOCK_SECONDS);

            $row = Db::table(self::TABLE)
                ->where(function ($query) use ($now, $staleAt) {
                    $query
                        ->whereIn('status', ['pending', 'retry'])
                        ->where('next_run_at', '<=', $now)
                        ->orWhere(function ($staleQuery) use ($staleAt) {
                            $staleQuery
                                ->where('status', 'running')
                                ->whereNotNull('locked_at')
                                ->where('locked_at', '<=', $staleAt);
                        });
                })
                ->orderBy('next_run_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (!$row) {
                return null;
            }

            $task = (array)$row;
            $attemptCount = (int)($task['attempt_count'] ?? 0) + 1;

            Db::table(self::TABLE)
                ->where('id', (int)($task['id'] ?? 0))
                ->update([
                    'status' => 'running',
                    'attempt_count' => $attemptCount,
                    'locked_at' => $now,
                    'started_at' => $task['started_at'] ?: $now,
                    'update_time' => $now,
                ]);

            $task['status'] = 'running';
            $task['attempt_count'] = $attemptCount;
            $task['locked_at'] = $now;
            $task['started_at'] = $task['started_at'] ?: $now;
            $task['update_time'] = $now;

            return $task;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function processNextTask(): array
    {
        $task = $this->claimDueTask();
        if ($task === null) {
            return [
                'processed' => false,
                'task' => null,
            ];
        }

        $response = LegacyHttpClient::get(trim((string)($task['callback_url'] ?? '')));
        $memo = $this->builder->memoFromResponse($response);
        $success = $this->isSuccessfulResponse($response);

        if ($success) {
            $this->completeSuccess($task, $response, $memo);
        } else {
            $this->completeRetryOrFailure($task, $response, $memo);
        }

        return [
            'processed' => true,
            'success' => $success,
            'task' => $task,
            'response' => $response,
            'memo' => $memo,
        ];
    }

    /**
     * @param array<string, mixed> $task
     * @param array<string, mixed> $response
     */
    private function completeSuccess(array $task, array $response, string $memo): void
    {
        $now = $this->now();
        $taskId = (int)($task['id'] ?? 0);
        $orderId = (int)($task['order_id'] ?? 0);

        Db::table(self::TABLE)
            ->where('id', $taskId)
            ->update([
                'status' => 'success',
                'finished_at' => $now,
                'locked_at' => null,
                'next_run_at' => null,
                'last_http_status' => (int)($response['status'] ?? 0),
                'last_error' => null,
                'last_response_body' => $this->truncate((string)($response['body'] ?? ''), 5000),
                'update_time' => $now,
            ]);

        $this->orders->incrementReturnCount($orderId);
        $this->updateOrderMemo($orderId, $memo);
    }

    /**
     * @param array<string, mixed> $task
     * @param array<string, mixed> $response
     */
    private function completeRetryOrFailure(array $task, array $response, string $memo): void
    {
        $taskId = (int)($task['id'] ?? 0);
        $orderId = (int)($task['order_id'] ?? 0);
        $attemptCount = (int)($task['attempt_count'] ?? 0);
        $maxAttempts = max(1, (int)($task['max_attempts'] ?? 1));
        $now = $this->now();
        $status = $attemptCount >= $maxAttempts ? 'failed' : 'retry';
        $nextRunAt = $status === 'retry'
            ? $this->dateTime(time() + $this->retryDelaySeconds($attemptCount))
            : null;

        $lastError = trim((string)($response['error'] ?? ''));
        if ($lastError === '') {
            $lastError = 'unexpected_http_status';
        }

        Db::table(self::TABLE)
            ->where('id', $taskId)
            ->update([
                'status' => $status,
                'finished_at' => $status === 'failed' ? $now : null,
                'locked_at' => null,
                'next_run_at' => $nextRunAt,
                'last_http_status' => (int)($response['status'] ?? 0),
                'last_error' => $this->truncate($lastError, 1000),
                'last_response_body' => $this->truncate((string)($response['body'] ?? ''), 5000),
                'update_time' => $now,
            ]);

        $this->updateOrderMemo($orderId, $memo);
    }

    /**
     * @param array<string, mixed> $task
     * @return array<string, mixed>
     */
    private function taskSummary(array $task, bool $deduplicated): array
    {
        return [
            'queued' => true,
            'deduplicated' => $deduplicated,
            'skipped' => false,
            'scene' => trim((string)($task['scene'] ?? 'settlement')),
            'task_id' => (int)($task['id'] ?? 0),
            'notify_url' => trim((string)($task['notify_url'] ?? '')),
            'return_url' => trim((string)($task['return_url'] ?? '')),
            'callback_url' => trim((string)($task['callback_url'] ?? '')),
            'memo' => $deduplicated ? 'merchant_callback_already_queued' : 'merchant_callback_queued',
            'ok' => true,
            'http_status' => 202,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dispatchTaskNow(int $taskId, bool $deduplicated): array
    {
        if ($taskId <= 0) {
            throw new RuntimeException('callback task id is required for synchronous dispatch');
        }

        $task = Db::transaction(function () use ($taskId): array {
            $row = Db::table(self::TABLE)
                ->where('id', $taskId)
                ->lockForUpdate()
                ->first();
            if (!$row) {
                throw new RuntimeException('callback task was not found for synchronous dispatch');
            }

            $task = (array)$row;
            $status = trim((string)($task['status'] ?? ''));
            if ($status === 'success') {
                return $task;
            }

            $attemptCount = (int)($task['attempt_count'] ?? 0) + 1;
            $now = $this->now();

            Db::table(self::TABLE)
                ->where('id', $taskId)
                ->update([
                    'status' => 'running',
                    'attempt_count' => $attemptCount,
                    'locked_at' => $now,
                    'started_at' => $task['started_at'] ?: $now,
                    'update_time' => $now,
                ]);

            $task['status'] = 'running';
            $task['attempt_count'] = $attemptCount;
            $task['locked_at'] = $now;
            $task['started_at'] = $task['started_at'] ?: $now;
            $task['update_time'] = $now;

            return $task;
        });

        $response = LegacyHttpClient::get(trim((string)($task['callback_url'] ?? '')));
        $memo = $this->builder->memoFromResponse($response);
        $success = $this->isSuccessfulResponse($response);

        if ($success) {
            $this->completeSuccess($task, $response, $memo);
        } else {
            $this->completeRetryOrFailure($task, $response, $memo);
        }

        return [
            'queued' => false,
            'deduplicated' => $deduplicated,
            'skipped' => false,
            'scene' => trim((string)($task['scene'] ?? 'settlement')),
            'task_id' => (int)($task['id'] ?? 0),
            'notify_url' => trim((string)($task['notify_url'] ?? '')),
            'return_url' => trim((string)($task['return_url'] ?? '')),
            'callback_url' => trim((string)($task['callback_url'] ?? '')),
            'memo' => $memo,
            'ok' => $success,
            'http_status' => (int)($response['status'] ?? 0),
            'response_body' => $this->truncate(trim((string)($response['body'] ?? '')), 500),
            'error' => trim((string)($response['error'] ?? '')),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadMerchant(int $merchantId): ?array
    {
        if ($merchantId <= 0) {
            return null;
        }

        $row = Db::table('ypay_user')
            ->select('id', 'username', 'user_key')
            ->where('id', $merchantId)
            ->first();

        return $row ? (array)$row : null;
    }

    private function isMerchantChannelTestOrder(array $order): bool
    {
        return in_array(
            trim((string)($order['api_memo'] ?? '')),
            self::MERCHANT_TEST_ORDER_MEMOS,
            true
        );
    }

    private function updateOrderMemo(int $orderId, string $memo): void
    {
        if ($orderId <= 0) {
            return;
        }

        Db::table('ypay_order')
            ->where('id', $orderId)
            ->update([
                'api_memo' => $memo,
                'update_time' => $this->now(),
            ]);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function isSuccessfulResponse(array $response): bool
    {
        $status = (int)($response['status'] ?? 0);

        return !empty($response['ok']) && $status >= 200 && $status < 300;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodeJson(array $payload): string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '{}';
    }

    private function retryDelaySeconds(int $attemptCount): int
    {
        $index = max(0, min(count(self::RETRY_DELAYS) - 1, $attemptCount - 1));

        return self::RETRY_DELAYS[$index];
    }

    private function truncate(string $value, int $limit): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function dateTime(int $timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }
}
