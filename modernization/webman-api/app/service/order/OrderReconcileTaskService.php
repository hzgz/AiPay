<?php

declare(strict_types=1);

namespace app\service\order;

use app\support\BusinessTable;
use Plugins\Payments\AlipayBill\Support\AlipayBillSupport;
use Plugins\Payments\Leshua\Support\LeshuaCore;
use Plugins\Payments\Shared\EpayProtocol\EpayOrderRepository;
use Plugins\Payments\Shared\Support\JiaofeiyiSupport;
use Plugins\Payments\Usdt\Support\UsdtTrc20Support;
use Plugins\Payments\WxpayV3\Support\WxpayV3OrderQuerySupport;
use RuntimeException;
use support\Db;
use Throwable;

final class OrderReconcileTaskService
{
    private const ALIPAY_BILL_CHANNEL_CODES = ['alipay_bill', 'alipay_mck'];
    private const JIAOFEIYI_CHANNEL_CODES = ['jiaofeiyi_alipay', 'jiaofeiyi_wxpay'];
    private const LESHUA_CHANNEL_CODE = 'leshua';
    private const SUPPORTED_CHANNEL_CODES = ['alipay_bill', 'alipay_mck', 'usdt', 'jiaofeiyi_alipay', 'jiaofeiyi_wxpay', 'wxpay_v3', 'leshua'];
    private const ALIPAY_BILL_GRACE_SECONDS = 300;
    private const USDT_GRACE_SECONDS = 300;
    private const WXPAY_V3_GRACE_SECONDS = 86400;
    private const LESHUA_GRACE_SECONDS = 86400;
    private const STALE_LOCK_SECONDS = 180;
    private const RETRY_DELAYS = [5, 10, 15, 20, 30, 45];

    public function __construct(
        private readonly JiaofeiyiSupport $jiaofeiyi = new JiaofeiyiSupport(),
        private readonly AlipayBillSupport $alipayBill = new AlipayBillSupport(),
        private readonly UsdtTrc20Support $usdt = new UsdtTrc20Support(),
        private readonly WxpayV3OrderQuerySupport $wxpayV3 = new WxpayV3OrderQuerySupport(),
        private readonly EpayOrderRepository $orders = new EpayOrderRepository(),
        private readonly OrderCallbackTaskService $callbackTasks = new OrderCallbackTaskService()
    ) {
    }

    public function seedMissingTasks(int $limit = 20): int
    {
        $now = time();
        $rows = Db::table(BusinessTable::order('orders'))
            ->join(BusinessTable::account('account'), 'orders.account_id', '=', 'account.id')
            ->leftJoin(self::table() . ' as reconcile_task', 'orders.id', '=', 'reconcile_task.order_id')
            ->select(
                'orders.id',
                'orders.user_id',
                'orders.account_id',
                'orders.trade_no',
                'orders.out_trade_no',
                'orders.type',
                'orders.create_time',
                'orders.out_time',
                'orders.alipay_order_no',
                'orders.status',
                'account.code',
                'account.wxname',
                'account.zfb_pid',
                'account.cookie',
                'account.remark'
            )
            ->where('orders.status', 0)
            ->whereNull('reconcile_task.id')
            ->whereIn('account.code', self::SUPPORTED_CHANNEL_CODES)
            ->where(function ($query) use ($now) {
                $query->where(function ($jiaofeiyiQuery) use ($now) {
                    $jiaofeiyiQuery
                        ->whereIn('account.code', self::JIAOFEIYI_CHANNEL_CODES)
                        ->where('orders.out_time', '>', $now)
                        ->whereNotNull('orders.alipay_order_no')
                        ->where('orders.alipay_order_no', '<>', '');
                })->orWhere(function ($alipayBillQuery) use ($now) {
                    $alipayBillQuery
                        ->whereIn('account.code', self::ALIPAY_BILL_CHANNEL_CODES)
                        ->where('orders.out_time', '>', $now - self::ALIPAY_BILL_GRACE_SECONDS);
                })->orWhere(function ($usdtQuery) use ($now) {
                    $usdtQuery
                        ->where('account.code', 'usdt')
                        ->where('orders.out_time', '>', $now - self::USDT_GRACE_SECONDS);
                })->orWhere(function ($wxpayV3Query) use ($now) {
                    $wxpayV3Query
                        ->where('account.code', 'wxpay_v3')
                        ->where('orders.out_time', '>', $now - self::WXPAY_V3_GRACE_SECONDS);
                })->orWhere(function ($leshuaQuery) use ($now) {
                    $leshuaQuery
                        ->where('account.code', self::LESHUA_CHANNEL_CODE)
                        ->where('orders.out_time', '>', $now - self::LESHUA_GRACE_SECONDS);
                });
            })
            ->orderBy('orders.id')
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
        $existing = Db::table(self::table())->where('task_key', $taskKey)->first();
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

        $inserted = (int)Db::table(self::table())->insertOrIgnore($insert);
        $stored = Db::table(self::table())->where('task_key', $taskKey)->first();
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

        $graceSeconds = $this->graceSecondsForChannel($channelCode);
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

        if ($channelCode === self::LESHUA_CHANNEL_CODE) {
            return $this->processLeshuaTask($task, $order, $account);
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
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    private function processLeshuaTask(array $task, array $order, array $account): array
    {
        $tradeNo = trim((string)($order['trade_no'] ?? $task['trade_no'] ?? ''));
        $gatewayTradeNo = trim((string)($order['alipay_order_no'] ?? $task['query_identifier'] ?? ''));
        if ($tradeNo === '' && $gatewayTradeNo === '') {
            $this->completeRetryOrFailure($task, [], 'missing_query_identifier');

            return ['status' => 'retry', 'reason' => 'missing_query_identifier'];
        }

        $core = LeshuaCore::fromAccount($account);
        try {
            $queryResult = $gatewayTradeNo !== ''
                ? $core->queryOrderByGatewayTradeNo($gatewayTradeNo)
                : $core->queryOrderByTradeNo($tradeNo);
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

        if (!$core->isPaid($queryResult)) {
            $this->completeRetryOrFailure($task, $queryResult, 'query_pending');

            return [
                'status' => 'retry',
                'reason' => 'query_pending',
                'query' => $queryResult,
            ];
        }

        try {
            $this->assertLeshuaQueryMatches($order, $queryResult);
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
            $this->leshuaSettlementPayloadFromQuery($queryResult),
            ['query' => $queryResult]
        );
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

        Db::table(BusinessTable::account())
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

        $row = Db::table(BusinessTable::order('orders'))
            ->join(BusinessTable::account('account'), 'orders.account_id', '=', 'account.id')
            ->select(
                'orders.id as order_id',
                'orders.name',
                'orders.trade_no',
                'orders.out_trade_no',
                'orders.user_id',
                'orders.account_id',
                'orders.type',
                'orders.money',
                'orders.truemoney',
                'orders.feilvmoney',
                'orders.status',
                'orders.notify_url',
                'orders.return_url',
                'orders.alipay_order_no',
                'orders.api_memo',
                'orders.create_time',
                'orders.out_time',
                'account.id as account_row_id',
                'account.code',
                'account.wxname',
                'account.zfb_pid',
                'account.wx_guid',
                'account.cloud_id',
                'account.cookie',
                'account.qr_url',
                'account.remark'
            )
            ->where('orders.id', $orderId)
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

        $usedByOrders = Db::table(BusinessTable::order())
            ->where('id', '<>', $currentOrderId)
            ->whereIn('alipay_order_no', array_values(array_unique($candidateIds)))
            ->pluck('alipay_order_no')
            ->map(static fn (mixed $value): string => trim((string)$value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        $usedByClaims = Db::table(self::transactionClaimTable())
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

        $count = Db::table(BusinessTable::order('orders'))
            ->join(BusinessTable::account('account'), 'orders.account_id', '=', 'account.id')
            ->whereIn('account.code', self::ALIPAY_BILL_CHANNEL_CODES)
            ->whereRaw('BINARY account.wxname = ?', [$appId])
            ->where('orders.status', 0)
            ->where('orders.out_time', '>', time() - max(0, $graceSeconds))
            ->where('orders.truemoney', $amount)
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
        $count = Db::table(BusinessTable::order('orders'))
            ->join(BusinessTable::account('account'), 'orders.account_id', '=', 'account.id')
            ->where('account.code', 'usdt')
            ->whereRaw('BINARY account.wxname = ?', [$wallet])
            ->where('orders.status', 0)
            ->where('orders.truemoney', $amount)
            ->where('orders.create_time', '<=', $this->dateTime($outTime))
            ->where('orders.out_time', '>=', $createdAt)
            ->count();

        return $count === 1;
    }

    /**
     * @param array<string, mixed> $task
     * @param array<string, mixed> $result
     */
    private function markFinished(array $task, string $status, array $result, string $memo): void
    {
        Db::table(self::table())
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

        Db::table(self::table())
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
     * @param array<string, mixed> $order
     * @param array<string, string> $queryResult
     */
    private function assertLeshuaQueryMatches(array $order, array $queryResult): void
    {
        $receivedTradeNo = trim((string)($queryResult['third_order_id'] ?? ''));
        $expectedTradeNo = trim((string)($order['trade_no'] ?? ''));
        if ($receivedTradeNo === '' || $expectedTradeNo === '' || !hash_equals($expectedTradeNo, $receivedTradeNo)) {
            throw new RuntimeException('leshua_trade_no_mismatch');
        }

        $receivedAmount = $this->integerFen($queryResult['amount'] ?? '');
        $expectedValue = trim((string)($order['truemoney'] ?? ''));
        if ($expectedValue === '') {
            $expectedValue = trim((string)($order['money'] ?? ''));
        }
        $expectedAmount = LeshuaCore::amountToFen($expectedValue);
        if ($receivedAmount <= 0 || $expectedAmount <= 0 || $receivedAmount !== $expectedAmount) {
            throw new RuntimeException('leshua_amount_mismatch');
        }

        if (trim((string)($queryResult['leshua_order_id'] ?? '')) === '') {
            throw new RuntimeException('leshua_gateway_trade_no_missing');
        }
    }

    /**
     * @param array<string, string> $queryResult
     * @return array<string, string>
     */
    private function leshuaSettlementPayloadFromQuery(array $queryResult): array
    {
        $gatewayTradeNo = trim((string)($queryResult['leshua_order_id'] ?? ''));

        return [
            'trade_no' => $gatewayTradeNo,
            'transaction_id' => $gatewayTradeNo,
            'buyer_trade_no' => trim((string)($queryResult['out_transaction_id'] ?? '')),
            'transaction_provider' => self::LESHUA_CHANNEL_CODE,
        ];
    }

    private function integerFen(string $value): int
    {
        $value = trim($value);
        if (preg_match('/^\d+$/', $value) !== 1) {
            return 0;
        }

        return (int)$value;
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

    private function graceSecondsForChannel(string $channelCode): int
    {
        if (in_array($channelCode, self::ALIPAY_BILL_CHANNEL_CODES, true)) {
            return self::ALIPAY_BILL_GRACE_SECONDS;
        }

        return match ($channelCode) {
            'usdt' => self::USDT_GRACE_SECONDS,
            'wxpay_v3' => self::WXPAY_V3_GRACE_SECONDS,
            self::LESHUA_CHANNEL_CODE => self::LESHUA_GRACE_SECONDS,
            default => 0,
        };
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

    private static function table(): string
    {
        return BusinessTable::orderReconcileTask();
    }

    private static function transactionClaimTable(): string
    {
        return BusinessTable::paymentTransactionClaim();
    }
}
