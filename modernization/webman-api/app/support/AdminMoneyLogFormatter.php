<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

class AdminMoneyLogFormatter
{
    public static function format(array $log): array
    {
        $id = (int)($log['id'] ?? 0);
        $amount = self::toFloat($log['money'] ?? 0, 3);
        $before = self::toFloat($log['beforemoney'] ?? 0, 3);
        $after = self::toFloat($log['after'] ?? 0, 3);
        $memo = AdminFixtureTextNormalizer::normalize(trim((string)($log['memo'] ?? '')));
        $userId = (int)($log['user_id'] ?? 0);
        $username = AdminFixtureTextNormalizer::normalize(trim((string)($log['merchant_username'] ?? '')));
        $merchantName = AdminFixtureTextNormalizer::normalize(trim((string)($log['merchant_name'] ?? '')));

        return [
            'id' => $id,
            'user_id' => $userId,
            'merchant_username' => $username,
            'merchant_name' => $merchantName === '' ? null : $merchantName,
            'merchant_display' => self::merchantDisplay($username, $merchantName, $userId),
            'type' => isset($log['type']) ? (int)$log['type'] : null,
            'type_label' => self::typeLabel($log['type'] ?? null, $amount, $memo),
            'type_tag' => self::typeTag($amount),
            'money' => $amount,
            'money_display' => self::signedAmount($amount),
            'before_money' => $before,
            'after_money' => $after,
            'balance_delta_label' => self::signedAmount($amount),
            'direction' => $amount < 0 ? 'expense' : 'income',
            'direction_label' => $amount < 0 ? '支出' : '收入',
            'memo' => $memo,
            'memo_label' => self::memoLabel($memo),
            'create_time' => self::nullableString($log['create_time'] ?? null),
        ];
    }

    public static function toFloat(mixed $value, int $precision = 3): float
    {
        return round((float)$value, $precision);
    }

    public static function typeLabel(mixed $type, float $amount, string $memo): string
    {
        $normalized = strtolower($memo);

        if (str_contains($normalized, 'fee') || str_contains($memo, '手续费')) {
            return '手续费扣除';
        }

        if (str_contains($memo, '充值') || str_contains($normalized, 'recharge')) {
            return '余额充值';
        }

        if (str_contains($memo, '扣除') || str_contains($normalized, 'deduct')) {
            return '余额扣减';
        }

        if (str_contains($normalized, 'settle') || str_contains($memo, '结算')) {
            return '结算变动';
        }

        if ($type !== null && $type !== '') {
            return '类型 ' . (int)$type;
        }

        return $amount < 0 ? '余额扣减' : '余额增加';
    }

    public static function typeTag(float $amount): string
    {
        if ($amount < 0) {
            return 'warning';
        }

        if ($amount > 0) {
            return 'success';
        }

        return 'info';
    }

    public static function valueLabel(mixed $value): string
    {
        return self::signedAmount(self::toFloat($value));
    }

    private static function merchantDisplay(string $username, string $name, int $userId): string
    {
        if ($name !== '' && $username !== '') {
            return $name . ' / ' . $username;
        }

        if ($name !== '') {
            return $name;
        }

        if ($username !== '') {
            return $username;
        }

        return '商户 #' . $userId;
    }

    private static function memoLabel(string $memo): string
    {
        return $memo !== '' ? $memo : '无备注';
    }

    private static function signedAmount(float $amount): string
    {
        $prefix = $amount > 0 ? '+' : '';
        return $prefix . number_format($amount, 3, '.', '');
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }
}
