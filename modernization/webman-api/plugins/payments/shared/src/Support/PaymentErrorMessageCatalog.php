<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace Plugins\Payments\Shared\Support;

final class PaymentErrorMessageCatalog
{
    public static function merchantIdRequired(): string
    {
        return '商户编号不能为空，请检查 pid 参数';
    }

    public static function merchantNotFound(): string
    {
        return '商户不存在，请确认商户编号是否正确';
    }

    public static function merchantFrozen(): string
    {
        return '商户账户已被冻结，请联系平台管理员';
    }

    public static function signatureInvalid(): string
    {
        return '签名校验失败，请检查商户密钥与签名参数';
    }

    public static function requiredField(string $field): string
    {
        return '缺少必填参数：' . self::fieldLabel($field);
    }

    public static function paymentTypeRequired(): string
    {
        return '缺少支付方式，请检查 type 参数';
    }

    public static function amountNotNumeric(): string
    {
        return '金额格式不正确，请传入有效数字';
    }

    public static function amountMustBePositive(): string
    {
        return '支付金额必须大于 0';
    }

    public static function amountBelowMin(): string
    {
        return '支付金额低于系统最小限额';
    }

    public static function amountAboveMax(): string
    {
        return '支付金额超过系统最大限额';
    }

    public static function orderNameInvalid(): string
    {
        return '商品名称包含非法字符，请检查 name 参数';
    }

    public static function orderNameRiskBlocked(): string
    {
        return '商品名称触发风控规则，请调整后重试';
    }

    public static function merchantBalanceInsufficient(): string
    {
        return '商户余额不足，请先充值后再发起支付';
    }

    public static function merchantPackageMissing(): string
    {
        return '商户套餐未开通，请先联系平台开通';
    }

    public static function merchantPackageExpired(): string
    {
        return '商户套餐已过期，请续费后再试';
    }

    public static function merchantOrderNoRequired(): string
    {
        return '商户订单号不能为空，请检查 out_trade_no 参数';
    }

    public static function merchantOrderNoDuplicate(): string
    {
        return '商户订单号重复，请更换后重试';
    }

    public static function callbackOrderNoRequired(): string
    {
        return '回调缺少商户订单号(out_trade_no)';
    }

    public static function orderNotFound(): string
    {
        return '订单不存在，请确认订单号是否正确';
    }

    public static function noGatewayPluginAvailable(): string
    {
        return '当前没有可用的支付插件，请先启用支持网关下单的插件';
    }

    public static function merchantNoChannel(string $paymentType): string
    {
        return sprintf(
            '当前商户没有可用的[%s]通道，请先在商户后台启用对应通道',
            self::paymentTypeLabel($paymentType)
        );
    }

    public static function requestChannelUnavailable(): string
    {
        return '当前请求未匹配到可用通道，请检查商户通道配置';
    }

    public static function accountNotFound(): string
    {
        return '收款账号不存在';
    }

    public static function accountMerchantMismatch(): string
    {
        return '收款账号不属于当前商户';
    }

    public static function accountTypeMismatch(): string
    {
        return '收款账号与支付方式不匹配';
    }

    public static function accountPluginMismatch(): string
    {
        return '收款账号插件与当前请求不匹配';
    }

    public static function accountDisabled(): string
    {
        return '收款账号已停用';
    }

    public static function poolNotFound(): string
    {
        return '轮询池不存在';
    }

    public static function poolDisabled(): string
    {
        return '轮询池已停用';
    }

    public static function poolTypeMismatch(): string
    {
        return '轮询池与支付方式不匹配';
    }

    public static function poolNoChannel(string $paymentType): string
    {
        return sprintf('当前轮询池没有可用的[%s]通道', self::paymentTypeLabel($paymentType));
    }

    public static function pluginNoAvailableAccount(string $pluginName): string
    {
        return sprintf('%s 没有可用的收款账号', $pluginName);
    }

    public static function pluginNoAvailableAccountInPool(string $pluginName): string
    {
        return sprintf('%s 轮询池里没有可用的收款账号', $pluginName);
    }

    public static function pluginPaymentTypeMismatch(string $pluginName, string $paymentType): string
    {
        return sprintf('%s 仅支持 %s 订单', $pluginName, self::paymentTypeLabel($paymentType));
    }

    public static function pluginDoesNotSupportGateway(string $pluginCode): string
    {
        return sprintf('支付插件[%s]不支持网关下单', $pluginCode);
    }

    public static function pluginNotInstalled(string $pluginCode): string
    {
        return sprintf('支付插件[%s]未安装', $pluginCode);
    }

    public static function pluginDisabled(string $pluginCode): string
    {
        return sprintf('支付插件[%s]已停用', $pluginCode);
    }

    public static function universalEpayChannelUnavailable(): string
    {
        return '通用易支付V1插件未配置可用通道';
    }

    public static function upstreamGatewayUrlMissing(): string
    {
        return '上游网关地址未配置，请先完善插件接口地址';
    }

    public static function upstreamChannelUnavailable(): string
    {
        return '当前上游通道暂不可用，请检查插件配置与状态';
    }

    public static function orderCreatedReloadFailed(): string
    {
        return '订单已创建，但系统刷新订单状态失败';
    }

    public static function orderGatewayRefreshFailed(): string
    {
        return '订单网关信息已更新，但系统刷新订单失败';
    }

    public static function invalidGatewayPayUrl(string $pluginName): string
    {
        return sprintf('%s 未返回有效的支付地址', $pluginName);
    }

    public static function orderIdentityChangedBeforeSettlement(): string
    {
        return '订单校验失败，订单标识在落账前发生变化';
    }

    public static function paymentTransactionClaimed(): string
    {
        return '支付流水已被其他订单占用，请联系平台管理员核查';
    }

    public static function gatewayProcessingFailed(): string
    {
        return '支付网关处理失败，请稍后重试';
    }

    public static function orderCreatePersistedReason(): string
    {
        return '订单会先写入平台，再跳转到上游通道继续支付。';
    }

    public static function notifyPersistedReason(): string
    {
        return '回调结算由平台完成，商户通知通过队列异步派发。';
    }

    private static function fieldLabel(string $field): string
    {
        return match (strtolower(trim($field))) {
            'pid' => '商户编号(pid)',
            'out_trade_no' => '商户订单号(out_trade_no)',
            'type' => '支付方式(type)',
            'name' => '商品名称(name)',
            'money' => '支付金额(money)',
            'notify_url' => '异步通知地址(notify_url)',
            'return_url' => '同步跳转地址(return_url)',
            default => $field,
        };
    }

    private static function paymentTypeLabel(string $paymentType): string
    {
        return match (strtolower(trim($paymentType))) {
            'alipay' => '支付宝(alipay)',
            'wxpay' => '微信支付(wxpay)',
            'qqpay' => 'QQ支付(qqpay)',
            default => trim($paymentType) === '' ? '当前支付方式' : trim($paymentType),
        };
    }
}
