<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

class AdminOrderFormatter
{
    public static function formatOrder(array $order): array
    {
        $type = trim((string)($order['type'] ?? ''));
        $merchantUsername = AdminFixtureTextNormalizer::normalize(trim((string)($order['merchant_username'] ?? '')));
        $merchantDisplay = $merchantUsername !== '' ? $merchantUsername : ('商户 #' . (int)($order['user_id'] ?? 0));

        return [
            'id' => (int)($order['id'] ?? 0),
            'name' => AdminFixtureTextNormalizer::normalize(trim((string)($order['name'] ?? ''))),
            'sitename' => AdminFixtureTextNormalizer::normalize(trim((string)($order['sitename'] ?? ''))),
            'trade_no' => trim((string)($order['trade_no'] ?? '')),
            'out_trade_no' => AdminFixtureTextNormalizer::normalize(trim((string)($order['out_trade_no'] ?? ''))),
            'upstream_trade_no' => trim((string)($order['alipay_order_no'] ?? '')),
            'user_id' => (int)($order['user_id'] ?? 0),
            'merchant_username' => $merchantUsername,
            'merchant_display' => $merchantDisplay,
            'account_id' => (int)($order['account_id'] ?? 0),
            'type' => $type,
            'type_text' => self::paymentTypeLabel($type),
            'type_label' => self::paymentTypeLabel($type),
            'pay_type' => (int)($order['pay_type'] ?? 0),
            'channel_text' => self::channelLabel($order),
            'channel_label' => self::channelLabel($order),
            'money' => self::toFloat($order['money'] ?? 0),
            'settled_amount' => self::settledAmount($order),
            'fee_amount' => self::toFloat($order['feilvmoney'] ?? 0, 3),
            'status' => (int)($order['status'] ?? 0),
            'status_text' => self::statusLabel((int)($order['status'] ?? 0)),
            'status_label' => self::statusLabel((int)($order['status'] ?? 0)),
            'status_type' => self::statusType((int)($order['status'] ?? 0)),
            'notify_url' => trim((string)($order['notify_url'] ?? '')),
            'return_url' => trim((string)($order['return_url'] ?? '')),
            'ip' => trim((string)($order['ip'] ?? '')),
            'create_time' => self::nullableString($order['create_time'] ?? null),
            'end_time' => self::nullableString($order['end_time'] ?? null),
            'api_memo' => AdminFixtureTextNormalizer::normalizeNullable(self::nullableString($order['api_memo'] ?? null)),
        ];
    }

    public static function paymentTypeLabel(?string $type): string
    {
        return match (strtolower(trim((string)$type))) {
            'alipay' => '支付宝',
            'wxpay', 'wechat' => '微信支付',
            'qqpay', 'qq' => 'QQ钱包',
            'usdt' => 'USDT',
            'epay_ali' => '易支付支付宝',
            'epay_wechat' => '易支付微信',
            default => trim((string)$type) !== ''
                ? strtoupper(str_replace('_', ' ', trim((string)$type)))
                : '未知方式',
        };
    }

    public static function statusLabel(int $status): string
    {
        return match ($status) {
            1 => '已支付',
            0 => '待支付',
            default => '未知状态',
        };
    }

    public static function statusType(int $status): string
    {
        return match ($status) {
            1 => 'success',
            0 => 'warning',
            default => 'info',
        };
    }

    public static function settledAmount(array $order): float
    {
        $type = strtolower(trim((string)($order['type'] ?? '')));
        $field = $type === 'usdt' ? ($order['money'] ?? 0) : ($order['truemoney'] ?? 0);

        return self::toFloat($field);
    }

    public static function toFloat(mixed $value, int $precision = 2): float
    {
        return round((float)$value, $precision);
    }

    private static function channelLabel(array $order): string
    {
        $payType = (int)($order['pay_type'] ?? 0);
        if ($payType === 2) {
            $suffix = trim((string)($order['paylist_name'] ?? ''));
            if ($suffix === '') {
                $suffix = trim((string)($order['paylist_type'] ?? ''));
            }

            $normalizedSuffix = AdminFixtureTextNormalizer::normalize($suffix);
            return $normalizedSuffix !== '' ? ('插件通道 / ' . $normalizedSuffix) : '插件通道';
        }

        if ($payType === 1) {
            return '本地通道';
        }

        return '未分配通道';
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }
}
