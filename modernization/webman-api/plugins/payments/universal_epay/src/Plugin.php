<?php

declare(strict_types=1);

namespace Plugins\Payments\UniversalEpay;

use app\service\order\OrderCallbackBuilder;
use app\service\order\OrderCallbackTaskService;
use app\support\BusinessTable;
use Plugins\Payments\Shared\AbstractManagedPaymentPlugin;
use Plugins\Payments\Shared\EpayProtocol\EpayOrderRepository;
use Plugins\Payments\Shared\Support\PaymentPluginException;
use Plugins\Payments\UniversalEpay\Support\UniversalEpayCore;
use Plugins\Payments\UniversalEpay\Support\UniversalEpayGatewayService;
use Plugins\Payments\UniversalEpay\Support\UniversalEpayNotifyService;
use RuntimeException;
use support\Db;

class Plugin extends AbstractManagedPaymentPlugin
{
    public function code(): string
    {
        return 'universal_epay';
    }

    protected function pluginName(): string
    {
        return '通用易支付插件';
    }

    protected function configTable(): string
    {
        return 'pay_plugin_universal_epay_config';
    }

    protected function logTable(): ?string
    {
        return 'pay_plugin_universal_epay_log';
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
            'display_name' => '通用易支付插件',
            'operator_note' => '用于统一管理易支付上游接口地址、商户ID、商户密钥和接口模式。',
            'account_hint' => '账户编码固定为 universal_epay，常用字段为商户ID、接口地址、商户密钥和接口模式，可挂载到支付宝、微信、QQ 三种支付方式。',
            default => parent::defaultConfigValue($configKey),
        };
    }

    public function createOrder(array $payload): array
    {
        return (new UniversalEpayGatewayService())->createOrder($payload);
    }

    public function query(string $orderNo): array
    {
        $order = $this->findLocalOrder($orderNo);
        $account = $this->loadBoundAccount($order);
        $result = $this->coreForAccount($account)->queryOrderByOutTradeNo((string)($order['out_trade_no'] ?? ''));
        if (!is_array($result)) {
            throw new RuntimeException('通用易支付插件查单响应无效');
        }

        if ((int)($result['code'] ?? 1) !== 0) {
            $message = trim((string)($result['msg'] ?? $result['message'] ?? ''));
            throw new RuntimeException($message !== '' ? $message : '通用易支付插件查单失败');
        }

        return [
            'plugin' => $this->code(),
            'order_no' => $orderNo,
            'gateway_trade_no' => trim((string)($result['trade_no'] ?? '')),
            'status' => (int)($result['status'] ?? 0),
            'money' => (string)($result['money'] ?? ''),
            'buyer' => (string)($result['buyer'] ?? ''),
            'bill_trade_no' => (string)($result['api_trade_no'] ?? ''),
            'endtime' => (string)($result['endtime'] ?? ''),
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
        $money = trim((string)($payload['money'] ?? $payload['refund_money'] ?? $order['truemoney'] ?? $order['money'] ?? ''));
        if ($money === '' || !is_numeric($money) || (float)$money <= 0) {
            throw PaymentPluginException::validation('退款金额无效');
        }

        $gatewayTradeNo = trim((string)($order['alipay_order_no'] ?? ''));
        if ($gatewayTradeNo === '') {
            throw PaymentPluginException::validation('订单缺少上游交易号，无法发起退款');
        }

        $result = $this->coreForAccount($account)->refund($refundNo, $gatewayTradeNo, $money);
        if (!is_array($result)) {
            throw new RuntimeException('通用易支付插件退款响应无效');
        }

        return [
            'plugin' => $this->code(),
            'order_no' => $orderReference,
            'refund_no' => $refundNo,
            'success' => (int)($result['code'] ?? 1) === 0,
            'message' => trim((string)($result['msg'] ?? $result['message'] ?? '')),
            'raw' => $result,
        ];
    }

    public function handleNotify(array $payload): array
    {
        return (new UniversalEpayNotifyService())->handle($payload);
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
            ->select('id', 'user_id', 'code', 'type', 'wxname', 'qr_url', 'qr_type', 'cookie', 'status', 'is_status')
            ->where('id', $accountId)
            ->first();
        if ($row === null) {
            throw new RuntimeException('收款账户不存在');
        }

        $account = (array)$row;
        if (strtolower(trim((string)($account['code'] ?? ''))) !== $this->code()) {
            throw new RuntimeException('订单绑定的收款账户不属于通用易支付插件');
        }

        return $account;
    }

    /**
     * @param array<string, mixed> $account
     */
    private function coreForAccount(array $account): UniversalEpayCore
    {
        $merchantId = trim((string)($account['wxname'] ?? ''));
        if ($merchantId === '') {
            throw new RuntimeException('通用易支付插件商户ID未配置');
        }

        $gatewayUrl = trim((string)($account['qr_url'] ?? ''));
        if ($gatewayUrl === '') {
            throw new RuntimeException('通用易支付插件接口地址未配置');
        }

        $merchantKey = trim((string)($account['cookie'] ?? ''));
        if ($merchantKey === '') {
            throw new RuntimeException('通用易支付插件商户密钥未配置');
        }

        return new UniversalEpayCore([
            'apiurl' => $gatewayUrl,
            'pid' => $merchantId,
            'key' => $merchantKey,
        ]);
    }
}
