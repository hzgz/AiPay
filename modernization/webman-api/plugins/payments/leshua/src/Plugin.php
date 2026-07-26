<?php

declare(strict_types=1);

namespace Plugins\Payments\Leshua;

use app\support\BusinessTable;
use Plugins\Payments\Leshua\Support\LeshuaCore;
use Plugins\Payments\Leshua\Support\LeshuaGatewayService;
use Plugins\Payments\Leshua\Support\LeshuaNotifyService;
use Plugins\Payments\Shared\AbstractManagedPaymentPlugin;
use Plugins\Payments\Shared\EpayProtocol\EpayOrderRepository;
use Plugins\Payments\Shared\Support\PaymentPluginException;
use RuntimeException;
use support\Db;

final class Plugin extends AbstractManagedPaymentPlugin
{
    public function code(): string
    {
        return 'leshua';
    }

    protected function pluginName(): string
    {
        return '乐刷支付插件';
    }

    protected function configTable(): string
    {
        return 'pay_plugin_leshua_config';
    }

    protected function logTable(): ?string
    {
        return 'pay_plugin_leshua_log';
    }

    public function configSchema(): array
    {
        return [
            [
                'field' => 'display_name',
                'label' => '插件显示名称',
                'type' => 'text',
                'required' => true,
            ],
            [
                'field' => 'operator_note',
                'label' => '运维备注',
                'type' => 'textarea',
                'required' => false,
            ],
            [
                'field' => 'account_hint',
                'label' => '账户录入提示',
                'type' => 'textarea',
                'required' => false,
            ],
        ];
    }

    protected function defaultConfigValue(string $configKey): ?string
    {
        return match ($configKey) {
            'display_name' => '乐刷支付插件',
            'operator_note' => '用于统一管理乐刷支付宝与微信账号、插件目录和清理策略。',
            'account_hint' => '账户编码固定为 leshua，常用字段为商户号、交易密钥和异步通知密钥；其中 wxname=商户号，cookie=交易密钥，qr_url=异步通知密钥。',
            default => parent::defaultConfigValue($configKey),
        };
    }

    public function createOrder(array $payload): array
    {
        return $this->gatewayService($this->normalizeGatewayPaymentType((string)($payload['type'] ?? '')))
            ->createOrder($payload);
    }

    public function query(string $orderNo): array
    {
        $order = $this->findLocalOrder($orderNo);
        $account = $this->loadBoundAccount($order);
        $result = LeshuaCore::fromAccount($account)->queryOrderByTradeNo((string)($order['trade_no'] ?? ''));

        return [
            'plugin' => $this->code(),
            'order_no' => $orderNo,
            'gateway_trade_no' => trim((string)($result['leshua_order_id'] ?? '')),
            'status' => trim((string)($result['status'] ?? '')) === '2' ? 1 : 0,
            'money' => $this->moneyFromFen($result['amount'] ?? ''),
            'buyer' => trim((string)($result['sub_openid'] ?? '')),
            'bill_trade_no' => trim((string)($result['out_transaction_id'] ?? '')),
            'endtime' => trim((string)($result['pay_time'] ?? '')),
            'raw' => $result,
        ];
    }

    public function refund(array $payload): array
    {
        $orderReference = trim((string)($payload['order_no'] ?? $payload['trade_no'] ?? $payload['out_trade_no'] ?? ''));
        if ($orderReference === '') {
            throw PaymentPluginException::validation('缺少退款订单号');
        }

        $refundNo = trim((string)($payload['refund_no'] ?? ''));
        if ($refundNo === '') {
            throw PaymentPluginException::validation('缺少退款单号');
        }

        $order = $this->findLocalOrder($orderReference);
        $account = $this->loadBoundAccount($order);
        $core = LeshuaCore::fromAccount($account);
        $gatewayTradeNo = trim((string)($order['alipay_order_no'] ?? ''));

        if ($gatewayTradeNo === '') {
            $query = $core->queryOrderByTradeNo((string)($order['trade_no'] ?? ''));
            $gatewayTradeNo = trim((string)($query['leshua_order_id'] ?? ''));
        }

        if ($gatewayTradeNo === '') {
            throw PaymentPluginException::validation('订单缺少上游交易号，无法发起退款');
        }

        $money = trim((string)($payload['money'] ?? $payload['refund_money'] ?? $order['truemoney'] ?? $order['money'] ?? ''));
        $refundAmountFen = LeshuaCore::amountToFen($money);
        if ($refundAmountFen <= 0) {
            throw PaymentPluginException::validation('退款金额无效');
        }

        $result = $core->refund($gatewayTradeNo, $refundNo, $refundAmountFen);

        return [
            'plugin' => $this->code(),
            'order_no' => $orderReference,
            'refund_no' => $refundNo,
            'success' => true,
            'gateway_trade_no' => $gatewayTradeNo,
            'gateway_refund_no' => trim((string)($result['leshua_refund_id'] ?? '')),
            'refund_money' => $this->moneyFromFen($result['refund_amount'] ?? $refundAmountFen),
            'message' => '退款申请已提交',
            'raw' => $result,
        ];
    }

    public function handleNotify(array $payload): array
    {
        return (new LeshuaNotifyService())->handle($payload);
    }

    private function gatewayService(string $paymentType): LeshuaGatewayService
    {
        return new LeshuaGatewayService($paymentType);
    }

    private function normalizeGatewayPaymentType(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'alipay', 'alipay_official', 'alipay_bill', 'alipay_mck' => 'alipay',
            'wxpay', 'wxpay_v3' => 'wxpay',
            default => throw PaymentPluginException::validation('乐刷支付插件仅支持支付宝和微信订单'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function findLocalOrder(string $orderNo): array
    {
        $orders = new EpayOrderRepository();
        $normalized = trim($orderNo);
        if ($normalized === '') {
            throw PaymentPluginException::validation('订单号不能为空');
        }

        $order = $orders->findByTradeNo($normalized);
        if ($order === null) {
            $order = $orders->findByOutTradeNo($normalized);
        }

        if ($order === null) {
            throw PaymentPluginException::notFound('订单不存在');
        }

        return $order;
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
        if (strtolower(trim((string)($account['code'] ?? ''))) !== $this->code()) {
            throw new RuntimeException('订单绑定的收款账户不属于乐刷支付插件');
        }

        return $account;
    }

    private function moneyFromFen(mixed $value): string
    {
        if (is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1)) {
            return number_format(((int)$value) / 100, 2, '.', '');
        }

        return '0.00';
    }
}
