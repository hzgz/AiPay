<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace Plugins\Payments\Leshua\Support;

use app\service\order\OrderCallbackBuilder;
use app\service\order\OrderCallbackTaskService;
use app\support\BusinessTable;
use Plugins\Payments\Shared\EpayProtocol\EpayOrderRepository;
use Plugins\Payments\Shared\Support\PaymentPluginException;
use RuntimeException;
use support\Db;

final class LeshuaNotifyService
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
        $payload = $this->resolvePayload($context);
        $tradeNo = trim((string)($payload['third_order_id'] ?? ''));
        $gatewayTradeNo = trim((string)($payload['leshua_order_id'] ?? ''));

        if ($tradeNo === '' && $gatewayTradeNo === '') {
            throw PaymentPluginException::validation('乐刷回调缺少订单号');
        }

        $order = $tradeNo !== ''
            ? $this->orders->findByTradeNo($tradeNo)
            : $this->findOrderByGatewayTradeNo($gatewayTradeNo);
        if ($order === null) {
            throw PaymentPluginException::notFound('订单不存在');
        }

        $account = $this->loadBoundAccount($order);
        $core = LeshuaCore::fromAccount($account);
        $verifiedPayload = $payload;

        if (!$core->hasNotifyKey() || !$core->verifyNotifySignature($payload)) {
            $verifiedPayload = $this->queryVerifiedPayload($core, $tradeNo, $gatewayTradeNo);
        }

        $this->assertTradeNoMatches($verifiedPayload, $order);
        $this->assertAmountMatches($verifiedPayload, $order);
        $gatewayTradeNo = trim((string)($verifiedPayload['leshua_order_id'] ?? $gatewayTradeNo));
        $this->assertReplayTransactionMatches($order, $gatewayTradeNo);

        $paid = $core->isPaid($verifiedPayload);
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

            if ($gatewayTradeNo === '') {
                throw PaymentPluginException::validation('乐刷回调缺少上游交易号');
            }

            $billTradeNo = trim((string)($verifiedPayload['out_transaction_id'] ?? ''));
            $settlement = $this->orders->settlePaidOrder($order, $merchant, [
                'trade_no' => $gatewayTradeNo,
                'transaction_id' => $gatewayTradeNo,
                'buyer_trade_no' => $billTradeNo,
                'transaction_provider' => 'leshua',
            ]);
            $callbackUrls = $this->callbackBuilder->buildUrls($settlement['order'], $merchant);

            if ($mode === 'notify') {
                $merchantNotify = $this->callbackTasks->enqueueForSettledOrder($settlement['order'], $merchant, [
                    'scene' => 'leshua_notify',
                ]);
            }
        }

        return [
            'plugin' => 'leshua',
            'mode' => $mode,
            'verified' => true,
            'paid' => $paid,
            'notify_response' => '000000',
            'return_response' => '000000',
            'return_redirect' => $mode === 'return' && $paid ? (string)($callbackUrls['return'] ?? '') : null,
            'merchant_notify' => $merchantNotify,
            'callback_urls' => $callbackUrls,
            'order' => $settlement['order'],
            'settlement' => [
                'already_paid' => (bool)($settlement['already_paid'] ?? false),
                'settlement_executed' => (bool)($settlement['settlement_executed'] ?? false),
            ],
            'raw' => $verifiedPayload,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    private function resolvePayload(array $context): array
    {
        $rawBody = trim((string)($context['raw_body'] ?? ''));
        if ($rawBody !== '') {
            $xmlPayload = LeshuaCore::parseXml($rawBody);
            if ($xmlPayload !== []) {
                return $xmlPayload;
            }
        }

        return LeshuaCore::normalizePayload((array)($context['payload'] ?? []));
    }

    /**
     * @return array<string, string>
     */
    private function queryVerifiedPayload(LeshuaCore $core, string $tradeNo, string $gatewayTradeNo): array
    {
        if ($gatewayTradeNo !== '') {
            return $core->queryOrderByGatewayTradeNo($gatewayTradeNo);
        }

        if ($tradeNo !== '') {
            return $core->queryOrderByTradeNo($tradeNo);
        }

        throw PaymentPluginException::validation('乐刷回调缺少可查单的订单号');
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
            ->select('id', 'user_id', 'code', 'type', 'wxname', 'qr_url', 'cookie', 'status', 'is_status')
            ->where('id', $accountId)
            ->first();
        if ($row === null) {
            throw new RuntimeException('收款账户不存在');
        }

        $account = (array)$row;
        if (strtolower(trim((string)($account['code'] ?? ''))) !== 'leshua') {
            throw new RuntimeException('当前订单不属于乐刷支付插件');
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
    private function assertTradeNoMatches(array $payload, array $order): void
    {
        $received = trim((string)($payload['third_order_id'] ?? ''));
        $expected = trim((string)($order['trade_no'] ?? ''));
        if ($received === '' || $expected === '' || !hash_equals($expected, $received)) {
            throw new RuntimeException('乐刷回调订单号与平台订单不一致');
        }
    }

    /**
     * @param array<string, string> $payload
     * @param array<string, mixed> $order
     */
    private function assertAmountMatches(array $payload, array $order): void
    {
        $received = $this->integerFen($payload['amount'] ?? '');
        $expectedValue = trim((string)($order['truemoney'] ?? ''));
        if ($expectedValue === '') {
            $expectedValue = trim((string)($order['money'] ?? ''));
        }
        $expected = LeshuaCore::amountToFen($expectedValue);

        if ($received <= 0 || $expected <= 0 || $received !== $expected) {
            throw new RuntimeException('乐刷回调金额与订单金额不一致');
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
            throw new RuntimeException('乐刷回调交易号与已支付订单不一致');
        }
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

    /**
     * @return array<string, mixed>|null
     */
    private function findOrderByGatewayTradeNo(string $gatewayTradeNo): ?array
    {
        if ($gatewayTradeNo === '') {
            return null;
        }

        $row = Db::table(BusinessTable::order())
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
            ->where('alipay_order_no', $gatewayTradeNo)
            ->first();

        return $row ? (array)$row : null;
    }
}
