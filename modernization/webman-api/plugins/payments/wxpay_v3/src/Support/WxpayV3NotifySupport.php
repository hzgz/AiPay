<?php

declare(strict_types=1);

namespace Plugins\Payments\WxpayV3\Support;

use app\support\BusinessTable;
use app\service\order\OrderCallbackTaskService;
use JsonException;
use Plugins\Payments\Shared\EpayProtocol\EpayOrderRepository;
use support\Db;

final class WxpayV3NotifySupport
{
    private const CHANNEL_CODE = 'wxpay_v3';
    private const MAX_SIGNATURE_SKEW_SECONDS = 300;
    private const RESOURCE_ALGORITHM = 'AEAD_AES_256_GCM';
    private const TRANSACTION_PROVIDER = 'wxpay_v3';

    public function __construct(
        private readonly EpayOrderRepository $orders = new EpayOrderRepository(),
        private readonly OrderCallbackTaskService $callbackTasks = new OrderCallbackTaskService()
    ) {
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<string, mixed>
     */
    public function handle(string $body, array $headers): array
    {
        if (trim($body) === '') {
            throw new WxpayV3NotifyException('empty_body', '请求体不能为空');
        }

        $envelope = $this->decodeJson($body, 'invalid_body');
        $resource = $envelope['resource'] ?? null;
        if (!is_array($resource)) {
            throw new WxpayV3NotifyException('invalid_resource', '支付资源数据缺失');
        }

        $algorithm = strtoupper(trim((string)($resource['algorithm'] ?? '')));
        if ($algorithm !== self::RESOURCE_ALGORITHM) {
            throw new WxpayV3NotifyException('unsupported_algorithm', '支付资源加密算法不受支持');
        }

        $ciphertext = trim((string)($resource['ciphertext'] ?? ''));
        $resourceNonce = trim((string)($resource['nonce'] ?? ''));
        $associatedData = (string)($resource['associated_data'] ?? '');
        if ($ciphertext === '' || $resourceNonce === '') {
            throw new WxpayV3NotifyException('invalid_resource', '加密支付资源不完整');
        }

        $timestamp = $this->header($headers, 'Wechatpay-Timestamp');
        $nonce = $this->header($headers, 'Wechatpay-Nonce');
        $signature = $this->header($headers, 'Wechatpay-Signature');
        $serial = $this->header($headers, 'Wechatpay-Serial');
        if (
            $timestamp === ''
            || !ctype_digit($timestamp)
            || (int)$timestamp <= 0
            || $nonce === ''
            || $signature === ''
            || $serial === ''
        ) {
            throw new WxpayV3NotifyException('missing_signature_headers', '签名请求头不完整');
        }
        if (abs(time() - (int)$timestamp) > self::MAX_SIGNATURE_SKEW_SECONDS) {
            throw new WxpayV3NotifyException('stale_timestamp', '签名时间戳超出允许范围', 401);
        }

        $accounts = Db::table(BusinessTable::account())
            ->select('id', 'user_id', 'code', 'wxname', 'zfb_pid', 'cookie', 'remark')
            ->where('code', self::CHANNEL_CODE)
            ->orderBy('id')
            ->get()
            ->toArray();
        if ($accounts === []) {
            throw new WxpayV3NotifyException('account_not_found', '未找到微信官方 V3 收款账号', 404);
        }

        foreach ($accounts as $row) {
            $account = (array)$row;
            if (!WxpayV3Crypto::verifySignature(
                (string)($account['cookie'] ?? ''),
                $timestamp,
                $nonce,
                $body,
                $signature
            )) {
                continue;
            }

            $plainText = WxpayV3Crypto::decryptResource(
                (string)($account['remark'] ?? ''),
                $ciphertext,
                $resourceNonce,
                $associatedData
            );
            if ($plainText === false) {
                continue;
            }

            try {
                $notification = $this->decodeJson($plainText, 'invalid_plaintext');
            } catch (WxpayV3NotifyException) {
                continue;
            }

            $outTradeNo = trim((string)($notification['out_trade_no'] ?? ''));
            if ($outTradeNo === '') {
                continue;
            }

            $order = $this->orders->findByOutTradeNo($outTradeNo);
            if (!$order || (int)($order['account_id'] ?? 0) !== (int)($account['id'] ?? 0)) {
                continue;
            }

            $accountMerchantId = (int)($account['user_id'] ?? 0);
            if ($accountMerchantId > 0 && $accountMerchantId !== (int)($order['user_id'] ?? 0)) {
                throw new WxpayV3NotifyException('merchant_binding_mismatch', '商户绑定关系不匹配', 409);
            }
            if (strtolower(trim((string)($order['type'] ?? ''))) !== 'wxpay') {
                throw new WxpayV3NotifyException('order_type_mismatch', '订单支付方式不匹配', 409);
            }

            $this->assertProviderBinding($account, $notification);

            $tradeState = strtoupper(trim((string)($notification['trade_state'] ?? '')));
            if ($tradeState !== 'SUCCESS') {
                return [
                    'acknowledged' => true,
                    'verified' => true,
                    'paid' => false,
                    'trade_state' => $tradeState,
                    'account_id' => (int)($account['id'] ?? 0),
                    'order_id' => (int)($order['id'] ?? 0),
                ];
            }

            $transactionId = $this->assertSuccessfulPayment($order, $notification);
            $settlement = $this->orders->settlePaidOrder($order, [
                'id' => (int)($order['user_id'] ?? 0),
            ], [
                'trade_no' => $transactionId,
                'transaction_id' => $transactionId,
                'transaction_provider' => self::TRANSACTION_PROVIDER,
            ]);
            $settledOrder = (array)($settlement['order'] ?? $order);
            $callback = $this->callbackTasks->enqueueForSettledOrder($settledOrder, null, [
                'scene' => 'wxpay_v3_notify',
            ]);

            return [
                'acknowledged' => true,
                'verified' => true,
                'paid' => true,
                'account_id' => (int)($account['id'] ?? 0),
                'order_id' => (int)($settledOrder['id'] ?? 0),
                'transaction_id' => $transactionId,
                'settlement' => [
                    'already_paid' => (bool)($settlement['already_paid'] ?? false),
                    'settlement_executed' => (bool)($settlement['settlement_executed'] ?? false),
                ],
                'merchant_callback' => $callback,
            ];
        }

        throw new WxpayV3NotifyException('verification_failed', '签名、资源或订单校验失败', 401);
    }

    /**
     * @param array<string, mixed> $account
     * @param array<string, mixed> $notification
     */
    private function assertProviderBinding(array $account, array $notification): void
    {
        $configuredAppId = trim((string)($account['wxname'] ?? ''));
        $configuredMerchantId = trim((string)($account['zfb_pid'] ?? ''));
        $notifiedAppId = trim((string)($notification['appid'] ?? ''));
        $notifiedMerchantId = trim((string)($notification['mchid'] ?? ''));

        if (
            $configuredAppId === ''
            || $configuredMerchantId === ''
            || $notifiedAppId === ''
            || $notifiedMerchantId === ''
            || !hash_equals($configuredAppId, $notifiedAppId)
            || !hash_equals($configuredMerchantId, $notifiedMerchantId)
        ) {
            throw new WxpayV3NotifyException('provider_binding_mismatch', '应用ID或商户号绑定关系不匹配', 409);
        }
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $notification
     */
    private function assertSuccessfulPayment(array $order, array $notification): string
    {
        $transactionId = trim((string)($notification['transaction_id'] ?? ''));
        if ($transactionId === '') {
            throw new WxpayV3NotifyException('transaction_id_missing', '交易号缺失');
        }

        $amount = $notification['amount'] ?? null;
        if (!is_array($amount) || !array_key_exists('total', $amount)) {
            throw new WxpayV3NotifyException('amount_missing', '支付金额缺失');
        }

        $notifiedTotal = $this->integerCents($amount['total']);
        $expectedTotal = $this->amountToCents((string)($order['truemoney'] ?? $order['money'] ?? '0'));
        if ($notifiedTotal <= 0 || $expectedTotal <= 0 || $notifiedTotal !== $expectedTotal) {
            throw new WxpayV3NotifyException('amount_mismatch', '支付金额与订单不一致', 409);
        }

        $currency = strtoupper(trim((string)($amount['currency'] ?? '')));
        if ($currency !== 'CNY') {
            throw new WxpayV3NotifyException('currency_mismatch', '支付币种与订单不一致', 409);
        }

        $storedTransactionId = trim((string)($order['alipay_order_no'] ?? ''));
        if (
            (int)($order['status'] ?? 0) === 1
            && $storedTransactionId !== ''
            && !hash_equals($storedTransactionId, $transactionId)
        ) {
            throw new WxpayV3NotifyException('transaction_mismatch', '已支付订单的交易号不一致', 409);
        }

        return $transactionId;
    }

    private function integerCents(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (!is_string($value) || !preg_match('/^[1-9]\d*$/', $value) || strlen($value) > 14) {
            return 0;
        }

        return (int)$value;
    }

    private function amountToCents(string $amount): int
    {
        $amount = trim($amount);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            return 0;
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        if (strlen($whole) > 12) {
            return 0;
        }

        return ((int)$whole * 100) + (int)str_pad($fraction, 2, '0');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $json, string $reason): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new WxpayV3NotifyException($reason, 'JSON 载荷无效', 400, $exception);
        }

        if (!is_array($decoded)) {
            throw new WxpayV3NotifyException($reason, 'JSON 载荷必须为对象');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function header(array $headers, string $name): string
    {
        $target = strtolower($name);
        foreach ($headers as $headerName => $value) {
            if (strtolower(str_replace('_', '-', (string)$headerName)) !== $target) {
                continue;
            }

            return trim(is_array($value) ? (string)reset($value) : (string)$value);
        }

        return '';
    }
}
