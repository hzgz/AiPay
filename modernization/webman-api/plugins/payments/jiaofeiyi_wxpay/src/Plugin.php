<?php

declare(strict_types=1);

namespace Plugins\Payments\JiaofeiyiWxpay;

use Plugins\Payments\Shared\AbstractJiaofeiyiManagedPlugin;

class Plugin extends AbstractJiaofeiyiManagedPlugin
{
    public function code(): string
    {
        return 'jiaofeiyi_wxpay';
    }

    protected function displayName(): string
    {
        return '缴费易微信支付插件';
    }

    protected function configTable(): string
    {
        return 'pay_plugin_jiaofeiyi_wxpay_config';
    }

    protected function logTable(): ?string
    {
        return 'pay_plugin_jiaofeiyi_wxpay_log';
    }

    protected function operatorNote(): string
    {
        return '用于维护缴费易微信账户，统一收敛商户 ID、商户号、微信支付模式、店铺名、收款备注、指定 IP、远程 API 与代理 IP API。';
    }

    protected function accountHintText(): string
    {
        return '账户编码固定为 jiaofeiyi_wxpay，常用字段为商户 ID、商户号、微信支付模式、店铺名、收款备注、指定 IP、远程 API 与代理 IP API。';
    }
}
