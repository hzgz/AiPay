<?php

declare(strict_types=1);

namespace app\service\order;

use Plugins\Payments\AlipayBill\Support\AlipayBillSupport;
use Plugins\Payments\Shared\Legacy\LegacyEpayOrderRepository;
use Plugins\Payments\Shared\Support\JiaofeiyiSupport;
use Plugins\Payments\Usdt\Support\UsdtTrc20Support;
use Plugins\Payments\WxpayV3\Support\WxpayV3OrderQuerySupport;
use RuntimeException;
use support\Db;
use Throwable;

final class OrderReconcileTaskService
{
    private const TABLE = 'ypay_order_reconcile_task';
    private const TRANSACTION_CLAIM_TABLE = 'ypay_payment_transaction_claim';
    private const ALIPAY_BILL_CHANNEL_CODES = ['alipay_bill', 'alipay_mck'];
    private const JIAOFEIYI_CHANNEL_CODES = ['jiaofeiyi_alipay', 'jiaofeiyi_wxpay'];
    private const SUPPORTED_CHANNEL_CODES = ['alipay_bill', 'alipay_mck', 'usdt', 'jiaofeiyi_alipay', 'jiaofeiyi_wxpay', 'wxpay_v3'];
    private const ALIPAY_BILL_GRACE_SECONDS = 300;
    private const USDT_GRACE_SECONDS = 300;
    private const WXPAY_V3_GRACE_SECONDS = 86400;
    private const STALE_LOCK_SECONDS = 180;
    private const RETRY_DELAYS = [5, 10, 15, 20, 30, 45];

    public function __construct(
        private readonly JiaofeiyiSupport $jiaofeiyi = new JiaofeiyiSupport(),
        private readonly AlipayBillSupport $alipayBill = new AlipayBillSupport(),
        private readonly UsdtTrc20Support $usdt = new UsdtTrc20Support(),
        private readonly WxpayV3OrderQuerySupport $wxpayV3 = new WxpayV3OrderQuerySupport(),
        private readonly LegacyEpayOrderRepository $orders = new LegacyEpayOrderRepository(),
        private readonly OrderCallbackTaskService $callbackTasks = new OrderCallbackTaskService()
    ) {
    }

    public function seedMissingTasks(int $limit = 20): int
    {
        $now = time();
        $rows = Db::table('ypay_order')
            ->join('ypay_account', 'ypay_order.account_id', '=', 'ypay_account.id')
            ->leftJoin(self::TABLE . ' as reconcile_task', 'ypay_order.id', '=', 'reconcile_task.order_id')
            ->select(
                'ypay_order.id',
                'ypay_order.user_id',
                'ypay_order.account_id',
                'ypay_order.trade_no',
                'ypay_order.out_trade_no',
                'ypay_order.type',
                'ypay_order.create_time',
                'ypay_order.out_time',
                'ypay_order.alipay_order_no',
                'ypay_order.status',
                'ypay_account.code',
                'ypay_account.wxname',
                'ypay_account.zfb_pid',
                'ypay_account.cookie',
                'ypay_account.remark'
            )
            ->where('ypay_order.status', 0)
            ->whereNull('reconcile_task.id')
            ->whereIn('ypay_account.code', self::SUPPORTED_CHANNEL_CODES)
            ->where(function ($query) use ($now) {
                $query->where(function ($jiaofeiyiQuery) use ($now) {
                    $jiaofeiyiQuery
                        ->whereIn('ypay_account.code', self::JIAOFEIYI_CHANNEL_CODES)
                        ->where('ypay_order.out_time', '>', $now)
                        ->whereNotNull('ypay_order.alipay_order_no')
                        ->where('ypay_order.alipay_order_no', '<>', '');
                })->orWhere(function ($alipayBillQuery) use ($now) {
                    $alipayBillQuery
                        ->whereIn('ypay_account.code', self::ALIPAY_BILL_CHANNEL_CODES)
                        ->where('ypay_order.out_time', '>', $now - self::ALIPAY_BILL_GRACE_SECONDS);
                })->orWhere(function ($usdtQuery) use ($now) {
                    $usdtQuery
                        ->where('ypay_account.code', 'usdt')
                        ->where('ypay_order.out_time', '>', $now - self::USDT_GRACE_SECONDS);
                })->orWhere(function ($wxpayV3Query) use ($now) {
                    $wxpayV3Query
                        ->where('ypay_account.code', 'wxpay_v3')
                        ->where('ypay_order.out_time', '>', $now - self::WXPAY_V3_GRACE_SECONDS);
                });
            })
            ->orderBy('ypay_order.id')
            ->limit(max(1, $limit))
            ->get()
            ->toArray();

        $created = 0;
        foreach ($rows as $row) {
            $record = (array)$row;
            if ($this->enqueueForOrder($record)['created'] ?? false) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    public function enqueueForOrder(array $order): array
    {
        $orderId = (int)($order['id'] ?? 0);
        if ($orderId <= 0) {
            return ['created' => false, 'reason' => 'missing_order_id'];
        }

        $channelCode = strtolower(trim((string)($order['code'] ?? '')));
        if (!in_array($channelCode, self::SUPPORTED_CHANNEL_CODES, true)) {
            return ['created' => false, 'reason' => 'unsupported_channel'];
        }

        $queryIdentifier = $this->resolveQueryIdentifier($order);
        if ($queryIdentifier === '') {
            return ['created' => false, 'reason' => 'missing_query_identifier'];
        }

        $taskKey = sprintf('order:%d:reconcile', $orderId);
        $existing = Db::table(self::TABLE)->where('task_key', $taskKey)->first();
        if ($existing) {
            return [
                'created' => false,
                'reason' => 'already_exists',
                'task_id' => (int)(((array)$existing)['id'] ?? 0),
            ];
        }

        $insert = [
            'task_key' => $taskKey,
            'order_id' => $orderId,
            'merchant_id' => (int)($order['user_id'] ?? 0),
            'account_id' => (int)($order['account_id'] ?? 0),
            'trade_no' => trim((string)($order['trade_no'] ?? '')),
            'out_trade_no' => trim((string)($order['out_trade_no'] ?? '')),
            'plugin_code' => $channelCode,
            'channel_code' => $channelCode,
            'payment_type' => trim((string)($order['type'] ?? '')),
            'query_identifier' => $queryIdentifier,
            'status' => 'pending',
            'attempt_count' => 0,
            'max_attempts' => 30,
            'next_run_at' => $this->now(),
            'locked_at' => null,
            'started_at' => null,
            'finished_at' => null,
            'last_error' => null,
            'last_result_json' => null,
            'create_time' => $this->now(),
            'update_time' => $this->now(),
        ];

        $inserted = (int)Db::table(self::TABLE)->insertOrIgnore($insert);
        $stored = Db::table(self::TABLE)->where('task_key', $taskKey)->first();
        if (!$stored) {
            throw new RuntimeException('Failed to persist reconciliation task');
        }
        $insert = (array)$stored;

        return [
            'created' => $inserted > 0,
            'reason' => $inserted > 0 ? 'created' : 'already_exists',
            'task_id' => (int)$insert['id'],
            'task_key' => $taskKey,
        ];
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

        try {
            $result = $this->processTask($task);

            return [
                'processed' => true,
                'task' => $task,
                'result' => $result,
            ];
        } catch (Throwable $exception) {
            $this->completeRetryOrFailure($task, [], 'exception: ' . $exception->getMessage());

            return [
                'processed' => true,
                'task' => $task,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param array<string, mixed> $task
     * @return array<string, mixed>
     */
    private function processTask(array $task): array
    {
        $context = $this->loadOrderContext((int)($task['order_id'] ?? 0));
        if ($context === null) {
            $this->markFinished($task, 'skipped', ['reason' => 'order_or_account_missing'], 'order_or_account_missing');

            return ['status' => 'skipped', 'reason' => 'order_or_account_missing'];
        }

        $order = $context['order'];
        $account = $context['account'];
        $channelCode = strtolower(trim((string)($account['code'] ?? '')));

        if ((int)($order['status'] ?? 0) === 1) {
            $callbackTask = $this->callbackTasks->enqueueForSettledOrder($order, null, [
                'scene' => 'reconcile',
            ]);
            $this->markFinished($task, 'success', [
                'reason' => 'already_paid',
                'callback' => $callbackTask,
            ], 'already_paid');

            return [
                'status' => 'success',
                'reason' => 'already_paid',
                'callback' => $callbackTask,
            ];
        }

        $graceSeconds = in_array($channelCode, self::ALIPAY_BILL_CHANNEL_CODES, true)
            ? self::ALIPAY_BILL_GRACE_SECONDS
            : ($channelCode === 'usdt'
                ? self::USDT_GRACE_SECONDS
                : ($channelCode === 'wxpay_v3' ? self::WXPAY_V3_GRACE_SECONDS : 0));
        if (((int)($order['out_time'] ?? 0) + $graceSeconds) <= time()) {
            $this->markFinished($task, 'skipped', ['reason' => 'order_timeout'], 'order_timeout');

            return ['status' => 'skipped', 'reason' => 'order_timeout'];
        }

        if (in_array($channelCode, self::ALIPAY_BILL_CHANNEL_CODES, true)) {
            return $this->processAlipayBillTask($task, $order, $account);
        }

        if ($channelCode === 'usdt') {
            return $this->processUsdtTask($task, $order, $account);
        }

        if (in_array($channelCode, self::JIAOFEIYI_CHANNEL_CODES, true)) {
            return $this->processJiaofeiyiTask($task, $order, $account);
        }

        if ($channelCode === 'wxpay_v3') {
            return $this->processWxpayV3Task($task, $order, $account);
        }

        $this->markFinished($task, 'skipped', ['reason' => 'unsupported_channel'], 'unsupported_channel');

        return ['status' => 'skipped', 'reason' => 'unsupported_channel'];
    }

    /**
     * @param array<string, mixed> $task
     * @param array<string, mixed> $order
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    private function processJiaofeiyiTask(array $task, array $order, array $account): array
    {

        $queryIdentifier = $this->resolveQueryIdentifier(array_merge($order, $account, [
            'query_identifier' => (string)($task['query_identifier'] ?? ''),
        ]));
        if ($queryIdentifier === '') {
            $this->completeRetryOrFailure($task, [], 'missing_query_identifier');

            return ['status' => 'retry', 'reason' => 'missing_query_identifier'];
        }

        $queryResult = $this->jiaofeiyi->queryOrder($account, $queryIdentifier);
        $statusValue = $queryResult['order_status'] ?? null;

        if ($this->jiaofeiyi->isPaidStatus($statusValue)) {
            $settlementPayload = $this->settlementPayloadFromQuery($queryResult);

            return $this->settlePaidAndQueue($task, $order, $settlementPayload, [
                'query' => $queryResult,
            ]);
        }

        if ($this->jiaofeiyi->isUnpaidStatus($statusValue)) {
            $this->completeRetryOrFailure($task, $queryResult, 'query_pending');

            return [
                'status' => 'retry',
                'reason' => 'query_pending',
                'query' => $queryResult,
            ];
        }

        $this->completeRetryOrFailure($task, $queryResult, 'unexpected_query_status');

        return [
            'status' => 'retry',
            'reason' => 'unexpected_query_status',
            'query' => $queryResult,
        ];
    }

    /**
     * @param array<string, mixed> $task
     * @param array<string, mixed> $order
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    private function processAlipayBillTask(array $task, array $order, array $account): array
    {
        $transactions = $this->alipayBill->queryTransactions($account, $order);
        $transactionIds = array_values(array_filter(array_map(
            static fn (array $transaction): string => trim((string)($transaction['transaction_id'] ?? '')),
            $transactions
        ), static fn (string $value): bool => $value !== ''));
        $excludedTransactionIds = $this->usedTransactionIds(
            $transactionIds,
            (int)($order['id'] ?? 0),
            'alipay'
        );
        $allowAmountFallback = $this->hasUniquePendingAmount($order, $account, self::ALIPAY_BILL_GRACE_SECONDS);
        $match = $this->alipayBill->matchTransaction(
            $order,
            $transactions,
            $allowAmountFallback,
            $excludedTransactionIds
        );

        if (($match['status'] ?? '') !== 'paid') {
            $reason = trim((string)($match['reason'] ?? 'query_pending')) ?: 'query_pending';
            $this->completeRetryOrFailure($task, $match, $reason);

            return [
                'status' => 'retry',
                'reason' => $reason,
                'query' => $match,
            ];
        }

        $transaction = is_array($match['transaction'] ?? null) ? $match['transaction'] : [];
        $transactionId = trim((string)($transaction['transaction_id'] ?? ''));
        if ($transactionId === '') {
            $this->completeRetryOrFailure($task, $match, 'missing_transaction_id');

            return ['status' => 'retry', 'reason' => 'missing_transaction_id'];
        }

        return $this->settlePaidAndQueue($task, $order, [
            'trade_no' => $transactionId,
            'transaction_id' => $transactionId,
            'buyer_trade_no' => '',
            'transaction_provider' => 'alipay',
        ], [
            'query' => $match,
        ]);
    }

    /**
     * @param array<string, mixed> $task
     * @param array<string, mixed> $order
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    private function processUsdtTask(array $task, array $order, array $account): array
    {
        $transfers = $this->usdt->queryIncomingTransfers($account, $order);
        $transactionIds = array_values(array_filter(array_map(
            static fn (array $transfer): string => strtolower(trim((string)($transfer['transaction_id'] ?? ''))),
            $transfers
        ), static fn (string $value): bool => $value !== ''));
        $excludedTransactionIds = $this->usedTransactionIds(
            $transactionIds,
            (int)($order['id'] ?? 0),
            UsdtTrc20Support::TRANSACTION_PROVIDER
        );
        $match = $this->usdt->matchTransfer(
            $order,
            $transfers,
            $this->hasUniquePendingUsdtAmount($order, $account),
            $excludedTransactionIds
        );

        if (($match['status'] ?? '') !== 'paid') {
            $reason = trim((string)($match['reason'] ?? 'query_pending')) ?: 'query_pending';
            $this->completeRetryOrFailure($task, $match, $reason);

            return [
                'status' => 'retry',
                'reason' => $reason,
                'query' => $match,
            ];
        }

        $transaction = is_array($match['transaction'] ?? null) ? $match['transaction'] : [];
        $transactionId = strtolower(trim((string)($transaction['transaction_id'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/', $transactionId)) {
            $this->completeRetryOrFailure($task, $match, 'missing_transaction_id');

            return ['status' => 'retry', 'reason' => 'missing_transaction_id'];
        }

        return $this->settlePaidAndQueue($task, $order, [
            'trade_no' => $transactionId,
            'transaction_id' => $transactionId,
            'buyer_trade_no' => trim((string)($transaction['from_address'] ?? '')),
            'transaction_provider' => UsdtTrc20Support::TRANSACTION_PROVIDER,
        ], [
            'query' => $match,
        ]);
    }

    /**
     * @param array<string, mixed> $task
     * @param array<string, mixed> $order
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    private function processWxpayV3Task(array $task, array $order, array $account): array
    {
        $outTradeNo = trim((string)($order['out_trade_no'] ?? $task['out_trade_no'] ?? ''));
        $transactionId = trim((string)($order['alipay_order_no'] ?? ''));
        if ($outTradeNo === '' && $transactionId === '') {
            $this->completeRetryOrFailure($task, [], 'missing_query_identifier');

            return ['status' => 'retry', 'reason' => 'missing_query_identifier'];
        }

        try {
            $queryResult = $this->wxpayV3->queryOrder($account, $outTradeNo, $transactionId);
        } catch (Throwable $exception) {
            $this->completeRetryOrFailure($task, [
                'exception' => $exception->getMessage(),
            ], 'query_exception');

            return [
                'status' => 'retry',
                'reason' => 'query_exception',
                'query' => ['exception' => $exception->getMessage()],
            ];
        }

        $tradeState = $queryResult['trade_state'] ?? null;
        if ($this->wxpayV3->isPaidStatus($tradeState)) {
            try {
                $this->wxpayV3->assertOrderMatches($order, $queryResult);
            } catch (RuntimeException $exception) {
                $reason = trim($exception->getMessage()) ?: 'query_validation_failed';
                $this->completeRetryOrFailure($task, $queryResult, $reason);

                return [
                    'status' => 'retry',
                    'reason' => $reason,
                    'query' => $queryResult,
                ];
            }

            return $this->settlePaidAndQueue(
                $task,
                $order,
                $this->wxpayV3->settlementPayloadFromQuery($queryResult),
                ['query' => $queryResult]
            );
        }

        if ($this->wxpayV3->isPendingStatus($tradeState)) {
            $this->completeRetryOrFailure($task, $queryResult, 'query_pending');

            return [
                'status' => 'retry',
                'reason' => 'query_pending',
                'query' => $queryResult,
            ];
        }

        if ($this->wxpayV3->isClosedStatus($tradeState)) {
            $memo = 'trade_state_' . strtolower(trim((string)$tradeState));
            $this->markFinished($task, 'skipped', [
                'reason' => $memo,
                'query' => $queryResult,
            ], $memo);

            return [
                'status' => 'skipped',
                'reason' => $memo,
                'query' => $queryResult,
            ];
        }

        $this->completeRetryOrFailure($task, $queryResult, 'unexpected_query_status');

        return [
            'status' => 'retry',
            'reason' => 'unexpected_query_status',
            'query' => $queryResult,
        ];
    }

    /**
     * @param array<string, mixed> $task
     * @param array<string, mixed> $order
     * @param array<string, string> $settlementPayload
     * @param array<string, mixed> $evidence
     * @return array<string, mixed>
     */
    private function settlePaidAndQueue(
        array $task,
        array $order,
        array $settlementPayload,
        array $evidence
    ): array {
        $settlement = $this->orders->settlePaidOrder($order, [
            'id' => (int)($order['user_id'] ?? 0),
        ], $settlementPayload);
        $settledOrder = (array)($settlement['order'] ?? $order);
        $callbackTask = $this->callbackTasks->enqueueForSettledOrder($settledOrder, null, [
            'scene' => 'reconcile',
        ]);

        Db::table('ypay_account')
            ->where('id', (int)($order['account_id'] ?? 0))
            ->update(['update_time' => $this->now()]);

        $result = array_merge($evidence, [
            'settlement' => [
                'already_paid' => (bool)($settlement['already_paid'] ?? false),
                'settlement_executed' => (bool)($settlement['settlement_executed'] ?? false),
            ],
            'callback' => $callbackTask,
        ]);
        $this->markFinished($task, 'success', $result, 'paid_and_queued');

        return array_merge(['status' => 'success'], $result);
    }

    /**
     * @return array{order: array<string, mixed>, account: array<string, mixed>}|null
     */
    private function loadOrderContext(int $orderId): ?array
    {
        if ($orderId <= 0) {
            return null;
        }

        $row = Db::table('ypay_order')
            ->join('ypay_account', 'ypay_order.account_id', '=', 'ypay_account.id')
            ->select(
                'ypay_order.id as order_id',
                'ypay_order.name',
                'ypay_order.trade_no',
                'ypay_order.out_trade_no',
                'ypay_order.user_id',
                'ypay_order.account_id',
                'ypay_order.type',
                'ypay_order.money',
                'ypay_order.truemoney',
                'ypay_order.feilvmoney',
                'ypay_order.status',
                'ypay_order.notify_url',
                'ypay_order.return_url',
                'ypay_order.alipay_order_no',
                'ypay_order.api_memo',
                'ypay_order.create_time',
                'ypay_order.out_time',
                'ypay_account.id as account_row_id',
                'ypay_account.code',
                'ypay_account.wxname',
                'ypay_account.zfb_pid',
                'ypay_account.wx_guid',
                'ypay_account.cloud_id',
                'ypay_account.cookie',
                'ypay_account.qr_url',
                'ypay_account.remark'
            )
            ->where('ypay_order.id', $orderId)
            ->first();

        if (!$row) {
            return null;
        }

        $record = (array)$row;
        $order = [
            'id' => (int)($record['order_id'] ?? 0),
            'name' => $record['name'] ?? '',
            'trade_no' => $record['trade_no'] ?? '',
            'out_trade_no' => $record['out_trade_no'] ?? '',
            'user_id' => (int)($record['user_id'] ?? 0),
            'account_id' => (int)($record['account_id'] ?? 0),
            'type' => $record['type'] ?? '',
            'money' => $record['money'] ?? '0.00',
            'truemoney' => $record['truemoney'] ?? '0.00',
            'feilvmoney' => $record['feilvmoney'] ?? '0.000',
            'status' => (int)($record['status'] ?? 0),
            'notify_url' => $record['notify_url'] ?? '',
            'return_url' => $record['return_url'] ?? '',
            'alipay_order_no' => $record['alipay_order_no'] ?? '',
            'api_memo' => $record['api_memo'] ?? '',
            'create_time' => $record['create_time'] ?? '',
            'out_time' => (int)($record['out_time'] ?? 0),
        ];
        $account = [
            'id' => (int)($record['account_row_id'] ?? 0),
            'code' => trim((string)($record['code'] ?? '')),
            'wxname' => $record['wxname'] ?? '',
            'zfb_pid' => $record['zfb_pid'] ?? '',
            'wx_guid' => $record['wx_guid'] ?? '',
            'cloud_id' => $record['cloud_id'] ?? '',
            'cookie' => $record['cookie'] ?? '',
            'qr_url' => $record['qr_url'] ?? '',
            'remark' => $record['remark'] ?? '',
        ];

        return [
            'order' => $order,
            'account' => $account,
        ];
    }

    /**
     * @param array<int, string> $candidateIds
     * @return array<int, string>
     */
    private function usedTransactionIds(array $candidateIds, int $currentOrderId, string $provider): array
    {
        if ($candidateIds === []) {
            return [];
        }

        $usedByOrders = Db::table('ypay_order')
            ->where('id', '<>', $currentOrderId)
            ->whereIn('alipay_order_no', array_values(array_unique($candidateIds)))
            ->pluck('alipay_order_no')
            ->map(static fn (mixed $value): string => trim((string)$value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        $usedByClaims = Db::table(self::TRANSACTION_CLAIM_TABLE)
            ->where('provider', strtolower(trim($provider)))
            ->where('order_id', '<>', $currentOrderId)
            ->whereIn('transaction_id', array_values(array_unique($candidateIds)))
            ->pluck('transaction_id')
            ->map(static fn (mixed $value): string => trim((string)$value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        return array_values(array_unique(array_merge($usedByOrders, $usedByClaims)));
    }

    /**
     * @param array<string, mixed> $order
     */
    private function hasUniquePendingAmount(array $order, array $account, int $graceSeconds): bool
    {
        $amount = number_format((float)($order['truemoney'] ?? $order['money'] ?? 0), 2, '.', '');
        $appId = trim((string)($account['wxname'] ?? ''));
        if ($appId === '') {
            return false;
        }

        $count = Db::table('ypay_order')
            ->join('ypay_account', 'ypay_order.account_id', '=', 'ypay_account.id')
            ->whereIn('ypay_account.code', self::ALIPAY_BILL_CHANNEL_CODES)
            ->whereRaw('BINARY ypay_account.wxname = ?', [$appId])
            ->where('ypay_order.status', 0)
            ->where('ypay_order.out_time', '>', time() - max(0, $graceSeconds))
            ->where('ypay_order.truemoney', $amount)
            ->count();

        return $count === 1;
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $account
     */
    private function hasUniquePendingUsdtAmount(array $order, array $account): bool
    {
        $wallet = trim((string)($account['wxname'] ?? ''));
        $createdAt = strtotime(trim((string)($order['create_time'] ?? ''))) ?: 0;
        $outTime = (int)($order['out_time'] ?? 0);
        if (!$this->usdt->isValidMainnetAddress($wallet) || $createdAt <= 0 || $outTime < $createdAt) {
            return false;
        }

        $amount = number_format((float)($order['truemoney'] ?? $order['money'] ?? 0), 2, '.', '');
        $count = Db::table('ypay_order')
            ->join('ypay_account', 'ypay_order.account_id', '=', 'ypay_account.id')
            ->where('ypay_account.code', 'usdt')
            ->whereRaw('BINARY ypay_account.wxname = ?', [$wallet])
            ->where('ypay_order.status', 0)
            ->where('ypay_order.truemoney', $amount)
            ->where('ypay_order.create_time', '<=', $this->dateTime($outTime))
            ->where('ypay_order.out_time', '>=', $createdAt)
            ->count();

        return $count === 1;
    }

    /**
     * @param array<string, mixed> $task
     * @param array<string, mixed> $result
     */
    private function markFinished(array $task, string $status, array $result, string $memo): void
    {
        Db::table(self::TABLE)
            ->where('id', (int)($task['id'] ?? 0))
            ->update([
                'status' => $status,
                'locked_at' => null,
                'next_run_at' => null,
                'finished_at' => $this->now(),
                'last_error' => $status === 'success' ? null : $memo,
                'last_result_json' => $this->encodeJson($result),
                'update_time' => $this->now(),
            ]);
    }

    /**
     * @param array<string, mixed> $task
     * @param array<string, mixed> $result
     */
    private function completeRetryOrFailure(array $task, array $result, string $error): void
    {
        $attemptCount = (int)($task['attempt_count'] ?? 0);
        $maxAttempts = max(1, (int)($task['max_attempts'] ?? 1));
        $status = $attemptCount >= $maxAttempts ? 'failed' : 'retry';

        Db::table(self::TABLE)
            ->where('id', (int)($task['id'] ?? 0))
            ->update([
                'status' => $status,
                'locked_at' => null,
                'next_run_at' => $status === 'retry'
                    ? $this->dateTime(time() + $this->retryDelaySeconds($attemptCount))
                    : null,
                'finished_at' => $status === 'failed' ? $this->now() : null,
                'last_error' => $this->truncate($error, 1000),
                'last_result_json' => $result === [] ? null : $this->encodeJson($result),
                'update_time' => $this->now(),
            ]);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function resolveQueryIdentifier(array $record): string
    {
        foreach (['query_identifier', 'alipay_order_no', 'trade_no', 'out_trade_no'] as $field) {
            $value = trim((string)($record[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $queryResult
     * @return array<string, string>
     */
    private function settlementPayloadFromQuery(array $queryResult): array
    {
        $transaction = trim((string)($queryResult['sys_trade_no'] ?? ''));
        if ($transaction === '') {
            $transaction = trim((string)($queryResult['pay_order_no'] ?? ''));
        }
        if ($transaction === '') {
            $transaction = trim((string)($queryResult['channel_trade_no'] ?? ''));
        }
        if ($transaction === '') {
            $transaction = trim((string)($queryResult['bill_trade_no'] ?? ''));
        }

        return [
            'trade_no' => trim((string)($queryResult['bill_trade_no'] ?? '')),
            'transaction_id' => $transaction,
            'buyer_trade_no' => trim((string)($queryResult['buyer'] ?? '')),
            'transaction_provider' => 'jiaofeiyi',
        ];
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
