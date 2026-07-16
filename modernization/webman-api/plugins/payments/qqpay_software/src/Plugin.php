<?php

namespace Plugins\Payments\QqpaySoftware;

use app\payment\AbstractManagedPaymentPlugin;

class Plugin extends AbstractManagedPaymentPlugin
{
    public function code(): string
    {
        return 'qqpay_software';
    }

    protected function pluginName(): string
    {
        return 'QQ 软件版插件';
    }

    protected function configTable(): string
    {
        return 'pay_plugin_qqpay_software_config';
    }

    protected function logTable(): ?string
    {
        return 'pay_plugin_qqpay_software_log';
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
            'display_name' => 'QQ 软件版插件',
            'operator_note' => '用于管理 QQ 软件版账户目录、安装状态与清理策略。',
            'account_hint' => '账户编码固定为 qqpay_software，可选填写二维码地址用于软件版轮询。',
            default => parent::defaultConfigValue($configKey),
        };
    }
}
