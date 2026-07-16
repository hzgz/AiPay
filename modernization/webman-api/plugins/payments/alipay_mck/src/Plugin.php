<?php

namespace Plugins\Payments\AlipayMck;

use app\payment\AbstractManagedPaymentPlugin;

class Plugin extends AbstractManagedPaymentPlugin
{
    public function code(): string
    {
        return 'alipay_mck';
    }

    protected function pluginName(): string
    {
        return '支付宝免CK插件';
    }

    protected function configTable(): string
    {
        return 'pay_plugin_alipay_mck_config';
    }

    protected function logTable(): ?string
    {
        return 'pay_plugin_alipay_mck_log';
    }

    public function configSchema(): array
    {
        return [
            [
                'field' => 'display_name',
                'label' => '插件展示名称',
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
            'display_name' => '支付宝免CK插件',
            'operator_note' => '用于管理支付宝免CK账户、插件目录和清理策略。',
            'account_hint' => '账户编码固定为 alipay_mck，常用字段为 PID、应用 ID、公钥与私钥。',
            default => parent::defaultConfigValue($configKey),
        };
    }
}
