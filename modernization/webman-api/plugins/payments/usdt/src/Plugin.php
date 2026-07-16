<?php

declare(strict_types=1);

namespace Plugins\Payments\Usdt;

use app\payment\AbstractManagedPaymentPlugin;

class Plugin extends AbstractManagedPaymentPlugin
{
    public function code(): string
    {
        return 'usdt';
    }

    protected function pluginName(): string
    {
        return 'USDT 插件';
    }

    protected function configTable(): string
    {
        return 'pay_plugin_usdt_config';
    }

    protected function logTable(): ?string
    {
        return 'pay_plugin_usdt_log';
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
            'display_name' => 'USDT 插件',
            'operator_note' => '用于管理 USDT-TRC20 钱包地址账户与对账配置。',
            'account_hint' => '账户编码固定为 usdt，核心字段为钱包地址。',
            default => parent::defaultConfigValue($configKey),
        };
    }
}
