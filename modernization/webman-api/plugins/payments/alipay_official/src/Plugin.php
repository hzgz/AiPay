<?php

namespace Plugins\Payments\AlipayOfficial;

use app\payment\AbstractManagedPaymentPlugin;
use Plugins\Payments\AlipayOfficial\Support\AlipayOfficialGatewayService;

class Plugin extends AbstractManagedPaymentPlugin
{
    public function code(): string
    {
        return 'alipay_official';
    }

    protected function pluginName(): string
    {
        return '支付宝官方版V3插件';
    }

    protected function configTable(): string
    {
        return 'pay_plugin_alipay_official_config';
    }

    protected function logTable(): ?string
    {
        return 'pay_plugin_alipay_official_log';
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
            'display_name' => '支付宝官方版V3插件',
            'operator_note' => '用于管理支付宝官方版V3账号、插件目录和清理策略。',
            'account_hint' => '账户编码固定为 alipay_official，常用字段为应用 ID、支付宝用户 ID、公钥、私钥、签名模式，以及证书模式下的应用证书、支付宝证书、根证书。',
            default => parent::defaultConfigValue($configKey),
        };
    }

    public function createOrder(array $payload): array
    {
        return (new AlipayOfficialGatewayService())->createOrder($payload);
    }
}
