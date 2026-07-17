<?php

namespace Plugins\Payments\WxpayV3;

use Plugins\Payments\Shared\AbstractManagedPaymentPlugin;
use Plugins\Payments\WxpayV3\Support\WxpayV3GatewayService;

class Plugin extends AbstractManagedPaymentPlugin
{
    public function code(): string
    {
        return 'wxpay_v3';
    }

    protected function pluginName(): string
    {
        return '微信支付 V3 插件';
    }

    protected function configTable(): string
    {
        return 'pay_plugin_wxpay_v3_config';
    }

    protected function logTable(): ?string
    {
        return 'pay_plugin_wxpay_v3_log';
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
            'display_name' => '微信支付 V3 插件',
            'operator_note' => '用于管理微信支付 V3 账号、插件目录和清理策略。',
            'account_hint' => '账户编码固定为 wxpay_v3，常用字段为应用 ID、商户号、平台公钥、商户 API 私钥、API V3 密钥、商户证书序列号，以及可选的平台公钥 ID、APIv2 密钥。',
            default => parent::defaultConfigValue($configKey),
        };
    }

    public function createOrder(array $payload): array
    {
        return (new WxpayV3GatewayService())->createOrder($payload);
    }
}
