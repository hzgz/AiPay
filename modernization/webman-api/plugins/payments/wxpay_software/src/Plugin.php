<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace Plugins\Payments\WxpaySoftware;

use Plugins\Payments\Shared\AbstractManagedPaymentPlugin;

class Plugin extends AbstractManagedPaymentPlugin
{
    public function code(): string
    {
        return 'wxpay_software';
    }

    protected function pluginName(): string
    {
        return '微信软件版插件';
    }

    protected function configTable(): string
    {
        return 'pay_plugin_wxpay_software_config';
    }

    protected function logTable(): ?string
    {
        return 'pay_plugin_wxpay_software_log';
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
            'display_name' => '微信软件版插件',
            'operator_note' => '用于管理微信软件版账户目录、安装状态与清理策略。',
            'account_hint' => '账户编码固定为 wxpay_software，可选填写二维码地址用于软件版轮询。',
            default => parent::defaultConfigValue($configKey),
        };
    }
}
