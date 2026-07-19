<?php

declare(strict_types=1);

namespace Plugins\Payments\Shared\EpayProtocol;

use app\support\BusinessTable;
use Plugins\Payments\Shared\Support\LegacyTradeNumber;
use Plugins\Payments\Shared\Support\PaymentErrorMessageCatalog;
use Plugins\Payments\Shared\Support\PaymentPluginException;
use support\Db;

class EpayOrderRepository
{
    private const SETTLEMENT_MEMO = 'universal_epay_fee_deduct';

    public function findByOutTradeNo(string $outTradeNo): ?array
    {
        $orders = Db::table(BusinessTable::order())
            ->select(
                'id',
                'name',
                'sitename',
                'trade_no',
                'out_trade_no',
                'alipay_order_no',
                'user_id',
                'account_id',
                'pay_type',
                'type',
                'money',
                'truemoney',
                'feilvmoney',
                'status',
                'return_num',
                'notify_url',
                'return_url',
                'ip',
                'qrcode',
                'h5_qrurl',
                'api_memo',
                'out_time',
                'create_time',
                'end_time'
            )
            ->where('out_trade_no', $outTradeNo)
            ->limit(2)
            ->get()
            ->toArray();
        if (count($orders) > 1) {
            throw new \RuntimeException('out_trade_no resolves to more than one order');
        }

        $order = $orders[0] ?? null;

        return $order ? (array)$order : null;
    }

    public function findById(int $orderId): ?array
    {
        if ($orderId <= 0) {
            return null;
        }

        $order = Db::table(BusinessTable::order())
            ->select(
                'id',
                'name',
                'sitename',
                'trade_no',
                'out_trade_no',
                'alipay_order_no',
                'user_id',
                'account_id',
                'pay_type',
                'type',
                'money',
                'truemoney',
                'feilvmoney',
                'status',
                'return_num',
                'notify_url',
                'return_url',
                'ip',
                'qrcode',
                'h5_qrurl',
                'api_memo',
                'out_time',
                'create_time',
                'end_time'
            )
            ->where('id', $orderId)
            ->first();

        return $order ? (array)$order : null;
    }

    public function findByTradeNo(string $tradeNo): ?array
    {
        $order = Db::table(BusinessTable::order())
            ->select(
                'id',
                'name',
                'sitename',
                'trade_no',
                'out_trade_no',
                'alipay_order_no',
                'user_id',
                'account_id',
                'pay_type',
                'type',
                'money',
                'truemoney',
                'feilvmoney',
                'status',
                'return_num',
                'notify_url',
                'return_url',
                'ip',
                'qrcode',
                'h5_qrurl',
                'api_memo',
                'out_time',
                'create_time',
                'end_time'
            )
            ->where('trade_no', $tradeNo)
            ->first();

        return $order ? (array)$order : null;
    }

    public function assertRequestCanCreate(array $payload): void
    {
        $outTradeNo = trim((string)($payload['out_trade_no'] ?? ''));
        if ($outTradeNo === '') {
            throw PaymentPluginException::validation(PaymentErrorMessageCatalog::merchantOrderNoRequired());
        }

        $existing = $this->findByOutTradeNo($outTradeNo);
        if ($existing) {
            throw PaymentPluginException::conflict(PaymentErrorMessageCatalog::merchantOrderNoDuplicate());
        }
    }

    public function create(array $merchant, array $payload, ?array $paylist, array $basicSettings, string $clientIp = ''): array
    {
        $draft = $this->makeDraft($merchant, $payload, $paylist, $basicSettings, $clientIp);

        $insert = $draft;
        unset($insert['migration_state']);

        Db::table(BusinessTable::order())->insert($insert);

        $order = $this->findByTradeNo((string)$draft['trade_no']);
        if (!$order) {
            throw new \RuntimeException(PaymentErrorMessageCatalog::orderCreatedReloadFailed());
        }

        return $order;
    }

    public function makeDraft(
        array $merchant,
        array $payload,
        ?array $paylist = null,
        array $basicSettings = [],
        string $clientIp = ''
    ): array {
        $orderAmount = number_format((float)($payload['money'] ?? 0), 2, '.', '');
        $feeRate = (float)($merchant['feilv'] ?? 0);
        $feeMoney = number_format(round(((float)$orderAmount) * $feeRate / 100, 3), 3, '.', '');
        $timeoutSeconds = $this->resolveTimeoutSeconds($basicSettings);
        $tradeNo = trim((string)($payload['_trade_no'] ?? ''));
        if ($tradeNo === '') {
            $tradeNo = LegacyTradeNumber::make('Y');
        }
        $apiMemo = $this->buildApiMemo($payload, $merchant, $paylist, $timeoutSeconds);

        return [
            'name' => trim((string)($payload['name'] ?? '')),
            'sitename' => trim((string)($payload['sitename'] ?? '')),
            'type' => trim((string)($payload['type'] ?? '')),
            'account_id' => (int)($paylist['id'] ?? 0),
            'trade_no' => $tradeNo,
            'out_trade_no' => trim((string)$payload['out_trade_no']),
            'user_id' => (int)$merchant['id'],
            'pay_type' => $paylist ? 2 : 0,
            'money' => $orderAmount,
            'truemoney' => $orderAmount,
            'feilvmoney' => $feeMoney,
            'status' => 0,
            'notify_url' => trim((string)($payload['notify_url'] ?? '')),
            'return_url' => trim((string)($payload['return_url'] ?? '')),
            'ip' => $clientIp,
            'qrcode' => '',
            'h5_qrurl' => '',
            'api_memo' => $apiMemo,
            'out_time' => time() + $timeoutSeconds,
            'create_time' => date('Y-m-d H:i:s'),
            'migration_state' => 'persisted',
        ];
    }

    public function settlePaidOrder(array $order, array $merchant, array $payload): array
    {
        return Db::transaction(function () use ($order, $merchant, $payload): array {
            $orderId = (int)($order['id'] ?? 0);
            if ($orderId <= 0) {
                throw PaymentPluginException::notFound(PaymentErrorMessageCatalog::orderNotFound());
            }

            $current = Db::table(BusinessTable::order())
                ->select(
                    'id',
                    'user_id',
                    'account_id',
                    'out_trade_no',
                    'trade_no',
                    'money',
                    'truemoney',
                    'feilvmoney',
                    'status',
                    'return_num',
                    'notify_url',
                    'return_url',
                    'alipay_order_no'
                )
            ->where('id', $orderId)
            ->lockForUpdate()
            ->first();
            if (!$current) {
                throw PaymentPluginException::notFound(PaymentErrorMessageCatalog::orderNotFound());
            }

            $current = (array)$current;
            $expectedOutTradeNo = trim((string)($order['out_trade_no'] ?? ''));
            $expectedTradeNo = trim((string)($order['trade_no'] ?? ''));
            if (
                ($expectedOutTradeNo !== '' && !hash_equals((string)$current['out_trade_no'], $expectedOutTradeNo))
                || ($expectedTradeNo !== '' && !hash_equals((string)$current['trade_no'], $expectedTradeNo))
            ) {
                throw new \RuntimeException(PaymentErrorMessageCatalog::orderIdentityChangedBeforeSettlement());
            }

            $alreadyPaid = (int)($current['status'] ?? 0) === 1;
            if (!$alreadyPaid) {
                $transactionNo = $this->payloadTransactionNo($payload);
                $this->claimTransaction(
                    trim((string)($payload['transaction_provider'] ?? '')),
                    (string)($transactionNo ?? ''),
                    $current
                );

                Db::table(BusinessTable::order())
                    ->where('id', (int)$current['id'])
                    ->update([
                        'status' => 1,
                        'end_time' => date('Y-m-d H:i:s'),
                        'update_time' => date('Y-m-d H:i:s'),
                        'alipay_order_no' => $transactionNo ?: (string)($current['alipay_order_no'] ?? ''),
                    ]);

                $this->debitMerchantFee((int)($current['user_id'] ?? $merchant['id'] ?? 0), (string)($current['feilvmoney'] ?? '0'));
            }

            $settled = $this->findById($orderId);
            if (!$settled) {
                throw PaymentPluginException::notFound(PaymentErrorMessageCatalog::orderNotFound());
            }

            return [
                'order' => $settled,
                'already_paid' => $alreadyPaid,
                'settlement_executed' => !$alreadyPaid,
            ];
        });
    }

    public function incrementReturnCount(int $orderId): int
    {
        if ($orderId <= 0) {
            return 0;
        }

        Db::table(BusinessTable::order())->where('id', $orderId)->increment('return_num');
        $order = Db::table(BusinessTable::order())->select('return_num')->where('id', $orderId)->first();

        return (int)(($order ? (array)$order : [])['return_num'] ?? 0);
    }

    private function debitMerchantFee(int $merchantId, string $feeMoney): void
    {
        $fee = round((float)$feeMoney, 3);
        if ($fee <= 0 || $merchantId <= 0) {
            return;
        }

        $merchant = Db::table(BusinessTable::user())
            ->select('id', 'money')
            ->where('id', $merchantId)
            ->lockForUpdate()
            ->first();
        if (!$merchant) {
            return;
        }

        $merchant = (array)$merchant;
        $before = round((float)($merchant['money'] ?? 0), 3);
        $after = round($before - $fee, 3);

        Db::table(BusinessTable::user())
            ->where('id', $merchantId)
            ->update([
                'money' => number_format($after, 2, '.', ''),
            ]);

        Db::table('money_log')->insert([
            'user_id' => $merchantId,
            'type' => null,
            'money' => number_format(-$fee, 3, '.', ''),
            'beforemoney' => number_format($before, 3, '.', ''),
            'after' => number_format($after, 3, '.', ''),
            'memo' => self::SETTLEMENT_MEMO,
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function resolveTimeoutSeconds(array $basicSettings): int
    {
        $timeout = (int)($basicSettings['timeout_time'] ?? 180);
        if ($timeout <= 0) {
            $timeout = 180;
        }

        $maxTimeout = (int)($basicSettings['system_timeout'] ?? 0);
        if ($maxTimeout > 0 && $timeout > $maxTimeout) {
            $timeout = $maxTimeout;
        }

        return $timeout;
    }

    private function buildApiMemo(array $payload, array $merchant, ?array $paylist, int $timeoutSeconds): string
    {
        $memo = [
            'migration' => 'webman_universal_epay_v1',
            'merchant_id' => (int)($merchant['id'] ?? 0),
            'merchant_username' => (string)($merchant['username'] ?? ''),
            'paylist_id' => (int)($paylist['id'] ?? 0),
            'paylist_type' => (string)($paylist['type'] ?? ''),
            'source' => (string)($payload['_entry'] ?? 'submit'),
            'timeout_seconds' => $timeoutSeconds,
            'created_at' => date('c'),
        ];

        $passThrough = [
            'clientip',
            'device',
            'param',
            'sign_type',
        ];
        foreach ($passThrough as $field) {
            if (isset($payload[$field]) && $payload[$field] !== '') {
                $memo[$field] = (string)$payload[$field];
            }
        }

        $encoded = json_encode($memo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? 'webman_universal_epay_v1' : $encoded;
    }

    private function payloadTransactionNo(array $payload): ?string
    {
        foreach (['trade_no', 'transaction_id', 'buyer_trade_no'] as $field) {
            $value = trim((string)($payload[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $order
     */
    private function claimTransaction(string $provider, string $transactionId, array $order): void
    {
        $provider = strtolower(trim($provider));
        $transactionId = trim($transactionId);
        if ($provider === '' || $transactionId === '') {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $inserted = (int)Db::table(BusinessTable::paymentTransactionClaim())->insertOrIgnore([
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'order_id' => (int)($order['id'] ?? 0),
            'account_id' => (int)($order['account_id'] ?? 0),
            'trade_no' => trim((string)($order['trade_no'] ?? '')),
            'create_time' => $now,
            'update_time' => $now,
        ]);
        if ($inserted > 0) {
            return;
        }

        $claim = Db::table(BusinessTable::paymentTransactionClaim())
            ->select('order_id')
            ->where('provider', $provider)
            ->where('transaction_id', $transactionId)
            ->lockForUpdate()
            ->first();
        if (!$claim || (int)(((array)$claim)['order_id'] ?? 0) !== (int)($order['id'] ?? 0)) {
            throw new \RuntimeException(PaymentErrorMessageCatalog::paymentTransactionClaimed());
        }
    }
}
