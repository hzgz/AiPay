<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\service\order;

use app\support\BusinessTable;
use app\support\Environment;
use app\support\LegacyHttpClient;
use Plugins\Payments\Shared\EpayProtocol\EpayOrderRepository;
use RuntimeException;
use support\Db;

final class OrderCallbackTaskService
{
    private const MERCHANT_TEST_ORDER_MEMOS = ['merchant_channel_test_pay', 'merchant_channel_test_paid'];
    private const MERCHANT_TEST_OUT_TRADE_NO_PREFIX = 'TEST';
    private const STALE_LOCK_SECONDS = 180;
    private const RETRY_DELAYS = [5, 15, 30, 60, 120, 300];

    public function __construct(
        private readonly OrderCallbackBuilder $builder = new OrderCallbackBuilder(),
        private readonly EpayOrderRepository $orders = new EpayOrderRepository()
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
            throw new RuntimeException('Missing order id while creating merchant callback task.');
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
            throw new RuntimeException('Merchant was not found while creating merchant callback task.');
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
            $existing = Db::table(self::table())->where('task_key', $taskKey)->first();
            if ($existing) {
                $existingRecord = (array)$existing;
                $status = trim((string)($existingRecord['status'] ?? ''));

                if (in_array($status, ['pending', 'retry', 'running', 'success'], true)) {
                    return $this->taskSummary($existingRecord, true);
                }

                Db::table(self::table())
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

                $reloaded = Db::table(self::table())
                    ->where('id', (int)($existingRecord['id'] ?? 0))
                    ->first();

                return $this->taskSummary((array)$reloaded, true);
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

        $inserted = (int)Db::table(self::table())->insertOrIgnore($insert);
        $stored = Db::table(self::table())->where('task_key', $taskKey)->first();
        if (!$stored) {
            throw new RuntimeException('Failed to persist merchant callback task.');
        }

        $this->updateOrderMemo($orderId, 'merchant_callback_queued');

        return $this->taskSummary((array)$stored, $inserted === 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function claimDueTask(): ?array
    {
        return Db::transaction(function (): ?array {
            $now = $this->now();
            $staleAt = $this->dateTime(time() - self::STALE_LOCK_SECONDS);

            $row = Db::table(self::table())
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

            Db::table(self::table())
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

        $response = LegacyHttpClient::get(
            trim((string)($task['callback_url'] ?? '')),
            [],
            $this->callbackHttpTimeout(),
            $this->callbackHttpConnectTimeout()
        );
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

        Db::table(self::table())
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

        Db::table(self::table())
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
     * @return array<string, mixed>|null
     */
    private function loadMerchant(int $merchantId): ?array
    {
        if ($merchantId <= 0) {
            return null;
        }

        $row = Db::table(BusinessTable::user())
            ->select('id', 'username', 'user_key')
            ->where('id', $merchantId)
            ->first();

        return $row ? (array)$row : null;
    }

    private function isMerchantChannelTestOrder(array $order): bool
    {
        $memo = trim((string)($order['api_memo'] ?? ''));
        if (in_array($memo, self::MERCHANT_TEST_ORDER_MEMOS, true)) {
            return true;
        }

        $outTradeNo = strtoupper(trim((string)($order['out_trade_no'] ?? '')));
        if (!str_starts_with($outTradeNo, self::MERCHANT_TEST_OUT_TRADE_NO_PREFIX)) {
            return false;
        }

        return trim((string)($order['notify_url'] ?? '')) === ''
            && trim((string)($order['return_url'] ?? '')) === '';
    }

    private function updateOrderMemo(int $orderId, string $memo): void
    {
        if ($orderId <= 0) {
            return;
        }

        Db::table(BusinessTable::order())
            ->where('id', $orderId)
            ->update([
                'api_memo' => $memo,
                'update_time' => $this->now(),
            ]);
    }

    private static function table(): string
    {
        return BusinessTable::orderCallbackTask();
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

    private function callbackHttpTimeout(): int
    {
        return max(1, Environment::int('CALLBACK_HTTP_TIMEOUT', 8));
    }

    private function callbackHttpConnectTimeout(): int
    {
        return max(1, Environment::int('CALLBACK_HTTP_CONNECT_TIMEOUT', 3));
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
