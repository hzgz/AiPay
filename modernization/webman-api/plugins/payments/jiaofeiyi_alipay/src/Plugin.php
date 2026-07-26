<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace Plugins\Payments\JiaofeiyiAlipay;

use Plugins\Payments\Shared\AbstractJiaofeiyiManagedPlugin;

class Plugin extends AbstractJiaofeiyiManagedPlugin
{
    public function code(): string
    {
        return 'jiaofeiyi_alipay';
    }

    protected function displayName(): string
    {
        return '缴费易支付宝插件';
    }

    protected function configTable(): string
    {
        return 'pay_plugin_jiaofeiyi_alipay_config';
    }

    protected function logTable(): ?string
    {
        return 'pay_plugin_jiaofeiyi_alipay_log';
    }

    protected function operatorNote(): string
    {
        return '用于维护缴费易支付宝账户，统一收敛商户 ID、商户号、店铺名、收款备注、指定 IP、远程 API 与代理 IP API。';
    }

    protected function accountHintText(): string
    {
        return '账户编码固定为 jiaofeiyi_alipay，常用字段为商户 ID、商户号、店铺名、收款备注、指定 IP、远程 API 与代理 IP API。';
    }
}
