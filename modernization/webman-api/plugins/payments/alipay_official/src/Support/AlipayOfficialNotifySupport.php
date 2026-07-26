<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace Plugins\Payments\AlipayOfficial\Support;

use app\support\BusinessTable;
use app\service\order\OrderCallbackTaskService;
use InvalidArgumentException;
use Plugins\Payments\Shared\EpayProtocol\EpayOrderRepository;
use RuntimeException;
use support\Db;

final class AlipayOfficialNotifySupport
{
    private const CHANNEL_CODES = ['alipay_official'];
    private const PAID_STATUS = 'TRADE_SUCCESS';

    public function __construct(
        private readonly EpayOrderRepository $orders = new EpayOrderRepository(),
        private readonly OrderCallbackTaskService $callbackTasks = new OrderCallbackTaskService()
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handle(array $payload): array
    {
        $payload = $this->normalizePayload($payload);
        $outTradeNo = trim((string)($payload['out_trade_no'] ?? ''));
        if ($outTradeNo === '') {
            throw new InvalidArgumentException('商户订单号不能为空');
        }

        $order = $this->orders->findByOutTradeNo($outTradeNo);
        if ($order === null) {
            throw new RuntimeException('订单不存在');
        }

        $account = $this->loadBoundAccount($order);
        $publicKey = trim((string)($account['cookie'] ?? ''));
        if ($publicKey === '' || !$this->verifySignature($payload, $publicKey)) {
            throw new RuntimeException('支付宝签名校验失败');
        }

        $this->assertAppIdMatches($payload, $account);
        $this->assertAmountMatches($payload, $order);

        $tradeStatus = strtoupper(trim((string)($payload['trade_status'] ?? '')));
        if ($tradeStatus !== self::PAID_STATUS) {
            throw new RuntimeException('支付宝订单未支付成功');
        }

        $transactionId = trim((string)($payload['trade_no'] ?? ''));
        if ($transactionId === '') {
            throw new InvalidArgumentException('支付宝交易号不能为空');
        }
        $this->assertReplayTransactionMatches($order, $transactionId);

        $merchant = $this->loadMerchant((int)($order['user_id'] ?? 0));
        if ($merchant === null) {
            throw new RuntimeException('商户不存在');
        }

        $settlement = $this->orders->settlePaidOrder($order, $merchant, [
            'trade_no' => $transactionId,
            'transaction_id' => $transactionId,
            'buyer_trade_no' => '',
            'transaction_provider' => 'alipay',
        ]);
        $settledOrder = (array)($settlement['order'] ?? $order);

        $callback = $this->callbackTasks->enqueueForSettledOrder($settledOrder, $merchant, [
            'scene' => 'alipay_notify',
        ]);

        return [
            'acknowledgement' => 'success',
            'verified' => true,
            'paid' => true,
            'channel_code' => (string)$account['code'],
            'transaction_id' => $transactionId,
            'order' => $settledOrder,
            'settlement' => [
                'already_paid' => (bool)($settlement['already_paid'] ?? false),
                'settlement_executed' => (bool)($settlement['settlement_executed'] ?? false),
            ],
            'merchant_callback' => $callback,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function verifySignature(array $payload, string $publicKey): bool
    {
        $signature = trim((string)($payload['sign'] ?? ''));
        $signType = strtoupper(trim((string)($payload['sign_type'] ?? '')));
        if ($signature === '' || !in_array($signType, ['RSA', 'RSA2'], true)) {
            return false;
        }

        $decodedSignature = base64_decode($signature, true);
        if ($decodedSignature === false) {
            return false;
        }

        $formattedKey = $this->formatPublicKey($publicKey);
        if ($formattedKey === '') {
            return false;
        }

        $key = openssl_pkey_get_public($formattedKey);
        if ($key === false) {
            return false;
        }

        $algorithm = $signType === 'RSA2' ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;
        $verified = openssl_verify($this->signContent($payload), $decodedSignature, $key, $algorithm);

        return $verified === 1;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function signContent(array $payload): string
    {
        unset($payload['sign'], $payload['sign_type']);
        ksort($payload, SORT_STRING);

        $pairs = [];
        foreach ($payload as $name => $value) {
            if (!is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException('Alipay callback fields must be scalar');
            }

            if ($value === null) {
                continue;
            }

            $text = (string)$value;
            if (trim($text) === '' || str_starts_with($text, '@')) {
                continue;
            }

            $pairs[] = (string)$name . '=' . $text;
        }

        return implode('&', $pairs);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function normalizePayload(array $payload): array
    {
        $normalized = [];
        foreach ($payload as $name => $value) {
            if (!is_string($name) || (!is_scalar($value) && $value !== null)) {
                throw new InvalidArgumentException('支付宝回调参数格式不正确');
            }

            $normalized[$name] = $value === null ? '' : (string)$value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private function loadBoundAccount(array $order): array
    {
        $accountId = (int)($order['account_id'] ?? 0);
        if ($accountId <= 0) {
            throw new RuntimeException('订单未绑定收款账号');
        }

        $row = Db::table(BusinessTable::account())
            ->select('id', 'user_id', 'code', 'type', 'wxname', 'zfb_pid', 'cookie', 'status')
            ->where('id', $accountId)
            ->first();
        if (!$row) {
            throw new RuntimeException('收款账号不存在');
        }

        $account = (array)$row;
        $code = strtolower(trim((string)($account['code'] ?? '')));
        if (!in_array($code, self::CHANNEL_CODES, true)) {
            throw new RuntimeException('当前收款账号不支持支付宝官方版回调');
        }

        if ((int)($account['user_id'] ?? 0) !== (int)($order['user_id'] ?? 0)) {
            throw new RuntimeException('收款账号不属于当前订单商户');
        }

        if (strtolower(trim((string)($order['type'] ?? ''))) !== 'alipay') {
            throw new RuntimeException('当前订单支付方式不是支付宝');
        }

        $account['code'] = $code;

        return $account;
    }

    /**
     * @param array<string, string> $payload
     * @param array<string, mixed> $account
     */
    private function assertAppIdMatches(array $payload, array $account): void
    {
        $expected = trim((string)($account['wxname'] ?? ''));
        $received = trim((string)($payload['app_id'] ?? ''));
        if ($expected === '' || $received === '' || !hash_equals($expected, $received)) {
            throw new RuntimeException('支付宝应用ID与收款账号配置不一致');
        }
    }

    /**
     * @param array<string, string> $payload
     * @param array<string, mixed> $order
     */
    private function assertAmountMatches(array $payload, array $order): void
    {
        $received = $this->amountInCents($payload['total_amount'] ?? '');
        $expectedValue = trim((string)($order['truemoney'] ?? ''));
        if ($expectedValue === '') {
            $expectedValue = (string)($order['money'] ?? '');
        }
        $expected = $this->amountInCents($expectedValue);

        if ($received === null || $expected === null || $received !== $expected) {
            throw new RuntimeException('支付宝回调金额与订单不一致');
        }
    }

    /**
     * @param array<string, mixed> $order
     */
    private function assertReplayTransactionMatches(array $order, string $transactionId): void
    {
        if ((int)($order['status'] ?? 0) !== 1) {
            return;
        }

        $storedTransactionId = trim((string)($order['alipay_order_no'] ?? ''));
        if ($storedTransactionId !== '' && !hash_equals($storedTransactionId, $transactionId)) {
            throw new RuntimeException('支付宝交易号与已落账订单不一致');
        }
    }

    private function amountInCents(string $value): ?int
    {
        $value = trim($value);
        if (preg_match('/^\d{1,13}(?:\.(\d{1,2}))?$/D', $value, $matches) !== 1) {
            return null;
        }

        [$whole] = explode('.', $value, 2);
        $fraction = str_pad((string)($matches[1] ?? ''), 2, '0');
        $wholeAmount = (int)$whole;
        if ($wholeAmount > intdiv(PHP_INT_MAX - 99, 100)) {
            return null;
        }

        return ($wholeAmount * 100) + (int)$fraction;
    }

    private function formatPublicKey(string $publicKey): string
    {
        $publicKey = trim(str_replace(['\\r\\n', '\\n', "\r\n", "\r"], ["\n", "\n", "\n", "\n"], $publicKey));
        if ($publicKey === '') {
            return '';
        }

        if (preg_match('/-----BEGIN (PUBLIC KEY|RSA PUBLIC KEY|CERTIFICATE)-----(.*?)-----END \\1-----/s', $publicKey, $matches) === 1) {
            $label = (string)$matches[1];
            $body = preg_replace('/\s+/', '', (string)$matches[2]);
            if (!is_string($body) || $body === '') {
                return '';
            }

            return sprintf(
                "-----BEGIN %s-----\n%s-----END %s-----\n",
                $label,
                chunk_split($body, 64, "\n"),
                $label
            );
        }

        $body = preg_replace('/\s+/', '', $publicKey);
        if (!is_string($body) || $body === '') {
            return '';
        }

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split($body, 64, "\n")
            . "-----END PUBLIC KEY-----\n";
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
}
