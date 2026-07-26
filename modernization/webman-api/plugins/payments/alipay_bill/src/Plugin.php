<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace Plugins\Payments\AlipayBill;

use Plugins\Payments\Shared\AbstractManagedPaymentPlugin;

class Plugin extends AbstractManagedPaymentPlugin
{
    public function code(): string
    {
        return 'alipay_bill';
    }

    protected function pluginName(): string
    {
        return '支付宝二维码账单插件';
    }

    protected function configTable(): string
    {
        return 'pay_plugin_alipay_bill_config';
    }

    protected function logTable(): ?string
    {
        return 'pay_plugin_alipay_bill_log';
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
            'display_name' => '支付宝二维码账单插件',
            'operator_note' => '用于管理支付宝二维码账单插件配置与账户资料，核心字段为应用 ID、公钥、私钥和账单二维码内容。',
            'account_hint' => '账户编码固定为 alipay_bill，常用字段为应用 ID、公钥、私钥与账单二维码内容。',
            default => parent::defaultConfigValue($configKey),
        };
    }
}
