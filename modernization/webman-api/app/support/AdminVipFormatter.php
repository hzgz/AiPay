<?php

namespace app\support;

class AdminVipFormatter
{
    public static function format(array $vip, array $stats = []): array
    {
        $id = (int)($vip['id'] ?? 0);
        $name = AdminFixtureTextNormalizer::normalize(trim((string)($vip['name'] ?? '')));
        $status = (int)($vip['status'] ?? 0);
        $deleteTime = self::nullableString($vip['delete_time'] ?? null);
        $deleted = $deleteTime !== null;
        $feeRate = trim((string)($vip['feilv'] ?? ''));
        $money = self::toFloat($vip['money'] ?? 0);
        $duration = (int)($vip['viptime'] ?? 0);
        $passageCodes = self::splitCodes($vip['passage'] ?? '');
        $statusText = self::statusLabel($status, $deleted);

        return [
            'id' => $id,
            'name' => $name !== '' ? $name : ('VIP #' . $id),
            'icon' => self::nullableString($vip['icon'] ?? null),
            'avatar_frame' => self::nullableString($vip['avatar_frame'] ?? null),
            'fee_rate' => $feeRate === '' ? null : (float)$feeRate,
            'fee_rate_display' => $feeRate === '' ? '--' : rtrim(rtrim($feeRate, '0'), '.') . '%',
            'money' => $money,
            'money_display' => number_format($money, 2, '.', ''),
            'vip_days' => $duration,
            'duration_label' => self::durationLabel($duration),
            'status' => $status,
            'status_text' => $statusText,
            'status_label' => $statusText,
            'status_type' => $deleted ? 'info' : ($status === 1 ? 'success' : 'warning'),
            'sort' => (int)($vip['sort'] ?? 0),
            'deleted' => $deleted,
            'delete_time' => $deleteTime,
            'profit_enabled' => (int)($vip['is_profiteer'] ?? 0) === 1,
            'add_channel_enabled' => (int)($vip['is_addChannelNum'] ?? 0) === 1,
            'add_channel_num' => (int)($vip['addChannelNum'] ?? 0),
            'quota_enabled' => (int)($vip['is_quota'] ?? 0) === 1,
            'today_quota' => self::nullableString($vip['today_quota'] ?? null),
            'month_quota' => self::nullableString($vip['moon_quota'] ?? null),
            'passage_enabled' => (int)($vip['is_passage'] ?? 0) === 1,
            'passage_codes' => $passageCodes,
            'passage_count' => count($passageCodes),
            'create_time' => self::nullableString($vip['create_time'] ?? null),
            'merchant_count' => (int)($stats['merchant_count'] ?? 0),
            'active_merchant_count' => (int)($stats['active_merchant_count'] ?? 0),
            'expired_merchant_count' => (int)($stats['expired_merchant_count'] ?? 0),
        ];
    }

    public static function formatMerchant(array $merchant): array
    {
        $username = trim((string)($merchant['username'] ?? ''));
        $name = AdminFixtureTextNormalizer::normalize(trim((string)($merchant['name'] ?? '')));
        $vipTime = self::nullableString($merchant['vip_time'] ?? null);
        $isActive = $vipTime === null || strtotime($vipTime) >= time();
        $statusText = $isActive ? '会员有效' : '会员已过期';

        return [
            'id' => (int)($merchant['id'] ?? 0),
            'username' => $username,
            'name' => $name === '' ? null : $name,
            'display' => $name !== '' ? $name . ($username !== '' ? ' / ' . $username : '') : $username,
            'vip_time' => $vipTime,
            'is_active' => $isActive,
            'status_text' => $statusText,
            'status_label' => $statusText,
            'status_type' => $isActive ? 'success' : 'warning',
            'create_time' => self::nullableString($merchant['create_time'] ?? null),
        ];
    }

    private static function statusLabel(int $status, bool $deleted): string
    {
        if ($deleted) {
            return '回收站';
        }

        return $status === 1 ? '已启用' : '已停用';
    }

    private static function durationLabel(int $days): string
    {
        if ($days <= 0) {
            return '永久';
        }

        if ($days % 365 === 0) {
            return (int)($days / 365) . ' 年';
        }

        if ($days % 30 === 0) {
            return (int)($days / 30) . ' 个月';
        }

        return $days . ' 天';
    }

    private static function splitCodes(mixed $value): array
    {
        $string = trim((string)$value);
        if ($string === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $string))));
    }

    private static function toFloat(mixed $value, int $precision = 2): float
    {
        return round((float)$value, $precision);
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }
}
