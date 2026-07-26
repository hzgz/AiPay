<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace Plugins\Payments\UniversalEpay\Support;

use app\service\order\OrderCallbackBuilder;
use app\service\order\OrderCallbackTaskService;
use app\support\BusinessTable;
use Plugins\Payments\Shared\EpayProtocol\EpayOrderRepository;
use Plugins\Payments\Shared\Support\PaymentPluginException;
use RuntimeException;
use support\Db;

final class UniversalEpayNotifyService
{
    public function __construct(
        private readonly EpayOrderRepository $orders = new EpayOrderRepository(),
        private readonly OrderCallbackBuilder $callbackBuilder = new OrderCallbackBuilder(),
        private readonly OrderCallbackTaskService $callbackTasks = new OrderCallbackTaskService()
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function handle(array $context): array
    {
        $mode = strtolower(trim((string)($context['mode'] ?? 'notify')));
        $payload = $this->normalizePayload((array)($context['payload'] ?? []));
        $outTradeNo = trim((string)($payload['out_trade_no'] ?? ''));
        if ($outTradeNo === '') {
            throw PaymentPluginException::validation('回调缺少商户订单号');
        }

        $order = $this->orders->findByOutTradeNo($outTradeNo);
        if ($order === null) {
            throw PaymentPluginException::notFound('订单不存在');
        }

        $account = $this->loadBoundAccount($order);
        $core = new UniversalEpayCore([
            'apiurl' => trim((string)($account['qr_url'] ?? '')),
            'pid' => trim((string)($account['wxname'] ?? '')),
            'key' => trim((string)($account['cookie'] ?? '')),
        ]);
        if (!$core->verify($payload)) {
            throw PaymentPluginException::unauthorized();
        }

        $this->assertAmountMatches($payload, $order);
        $this->assertPaymentTypeMatches($payload, $order);
        $this->assertReplayTransactionMatches($order, trim((string)($payload['trade_no'] ?? '')));

        $paid = strtoupper(trim((string)($payload['trade_status'] ?? ''))) === 'TRADE_SUCCESS';
        $settlement = [
            'order' => $order,
            'already_paid' => (int)($order['status'] ?? 0) === 1,
            'settlement_executed' => false,
        ];
        $merchantNotify = null;
        $callbackUrls = ['notify' => '', 'return' => '', 'payload' => []];

        if ($paid) {
            $merchant = $this->loadMerchant((int)($order['user_id'] ?? 0));
            if ($merchant === null) {
                throw new RuntimeException('订单所属商户不存在');
            }

            $transactionId = trim((string)($payload['trade_no'] ?? ''));
            if ($transactionId === '') {
                throw PaymentPluginException::validation('缺少上游交易号');
            }

            $settlement = $this->orders->settlePaidOrder($order, $merchant, [
                'trade_no' => $transactionId,
                'transaction_id' => $transactionId,
                'transaction_provider' => 'universal_epay',
            ]);
            $callbackUrls = $this->callbackBuilder->buildUrls($settlement['order'], $merchant);

            if ($mode === 'notify') {
                $merchantNotify = $this->callbackTasks->enqueueForSettledOrder($settlement['order'], $merchant, [
                    'scene' => 'universal_epay_notify',
                ]);
            }
        }

        return [
            'plugin' => 'universal_epay',
            'mode' => $mode,
            'verified' => true,
            'paid' => $paid,
            'notify_response' => $paid ? 'success' : 'fail',
            'return_response' => $paid ? 'success' : 'fail',
            'return_redirect' => $paid ? (string)($callbackUrls['return'] ?? '') : null,
            'merchant_notify' => $merchantNotify,
            'callback_urls' => $callbackUrls,
            'order' => $settlement['order'],
            'settlement' => [
                'already_paid' => (bool)($settlement['already_paid'] ?? false),
                'settlement_executed' => (bool)($settlement['settlement_executed'] ?? false),
            ],
        ];
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
                continue;
            }

            $normalized[$name] = $value === null ? '' : trim((string)$value);
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
            throw new RuntimeException('订单未绑定收款账户');
        }

        $row = Db::table(BusinessTable::account())
            ->select('id', 'user_id', 'code', 'type', 'wxname', 'qr_url', 'qr_type', 'cookie')
            ->where('id', $accountId)
            ->first();
        if ($row === null) {
            throw new RuntimeException('收款账户不存在');
        }

        $account = (array)$row;
        if (strtolower(trim((string)($account['code'] ?? ''))) !== 'universal_epay') {
            throw new RuntimeException('当前订单不属于通用易支付V1插件');
        }

        if ((int)($account['user_id'] ?? 0) !== (int)($order['user_id'] ?? 0)) {
            throw new RuntimeException('收款账户与订单商户不匹配');
        }

        return $account;
    }

    /**
     * @param array<string, string> $payload
     * @param array<string, mixed> $order
     */
    private function assertAmountMatches(array $payload, array $order): void
    {
        $received = $this->amountInCents($payload['money'] ?? '');
        $expectedValue = trim((string)($order['truemoney'] ?? ''));
        if ($expectedValue === '') {
            $expectedValue = trim((string)($order['money'] ?? ''));
        }
        $expected = $this->amountInCents($expectedValue);

        if ($received === null || $expected === null || $received !== $expected) {
            throw new RuntimeException('回调金额与订单金额不一致');
        }
    }

    /**
     * @param array<string, string> $payload
     * @param array<string, mixed> $order
     */
    private function assertPaymentTypeMatches(array $payload, array $order): void
    {
        $received = $this->normalizePaymentType($payload['type'] ?? '');
        $expected = $this->normalizePaymentType((string)($order['type'] ?? ''));
        if ($received === '' || $expected === '' || !hash_equals($expected, $received)) {
            throw new RuntimeException('回调支付方式与订单不一致');
        }
    }

    /**
     * @param array<string, mixed> $order
     */
    private function assertReplayTransactionMatches(array $order, string $transactionId): void
    {
        if ((int)($order['status'] ?? 0) !== 1 || $transactionId === '') {
            return;
        }

        $stored = trim((string)($order['alipay_order_no'] ?? ''));
        if ($stored !== '' && !hash_equals($stored, $transactionId)) {
            throw new RuntimeException('回调交易号与已落账订单不一致');
        }
    }

    private function normalizePaymentType(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'wechat' => 'wxpay',
            'qq' => 'qqpay',
            default => $normalized,
        };
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
