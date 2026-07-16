<?php

namespace app\support;

class AdminUserFormatter
{
    public static function formatUser(array $user, array $stats = []): array
    {
        $id = (int)($user['id'] ?? 0);
        $username = AdminFixtureTextNormalizer::normalize(trim((string)($user['username'] ?? '')));
        $merchantName = AdminFixtureTextNormalizer::normalize(trim((string)($user['name'] ?? '')));
        $email = AdminFixtureTextNormalizer::normalize(trim((string)($user['email'] ?? '')));
        $mobile = trim((string)($user['mobile'] ?? ''));
        $remarks = AdminFixtureTextNormalizer::normalize(trim((string)($user['remarks'] ?? '')));
        $displayName = $merchantName !== '' ? $merchantName : ($username !== '' ? $username : ('商户 #' . $id));
        $isFrozen = (int)($user['is_frozen'] ?? 0) === 1;
        $isRealName = (int)($user['is_realName'] ?? 0) === 1;
        $vipId = (int)($user['vip_id'] ?? 0);
        $vipName = AdminFixtureTextNormalizer::normalize(trim((string)($user['vip_name'] ?? '')));
        $vipExpireTime = self::nullableString($user['vip_time'] ?? null);
        $hasVip = $vipId > 0 && ($vipExpireTime === null || strtotime($vipExpireTime) >= time());
        $feeRateValue = trim((string)($user['feilv'] ?? ''));
        $feeRateDisplay = $feeRateValue === '' ? '--' : rtrim(rtrim($feeRateValue, '0'), '.') . '%';
        $statusCode = $isFrozen ? '2' : '1';
        $statusLabel = $isFrozen ? '已冻结' : '正常';
        $statusType = $isFrozen ? 'danger' : 'success';
        $realNameLabel = $isRealName ? '已实名' : '未实名';
        $vipLabel = $hasVip ? ($vipName !== '' ? $vipName : '会员商户') : '普通商户';
        $orderTips = self::normalizeNotificationChannel($user['order_tips'] ?? null);
        $lowBalanceTips = self::normalizeNotificationChannel($user['is_money_tips'] ?? null);
        $lowBalanceThreshold = trim((string)($user['money_tips'] ?? '0'));
        $wxpusherUid = trim((string)($user['wxpusher_uid'] ?? ''));
        $tgChatId = trim((string)($user['tg_chat_id'] ?? ''));

        return [
            'id' => $id,
            'avatar' => self::avatar($username !== '' ? $username : $displayName),
            'status' => $statusCode,
            'userName' => $username,
            'userGender' => $realNameLabel,
            'nickName' => $displayName,
            'userPhone' => $mobile,
            'userEmail' => $email,
            'userRoles' => [$vipLabel],
            'createBy' => '系统',
            'createTime' => self::nullableString($user['create_time'] ?? null),
            'updateBy' => '系统',
            'updateTime' => self::nullableString($user['create_time'] ?? null),
            'merchant_name' => $displayName,
            'name' => $merchantName,
            'email' => $email,
            'mobile' => $mobile,
            'money' => self::toFloat($user['money'] ?? 0),
            'balance' => self::toFloat($user['money'] ?? 0),
            'fee_rate' => $feeRateValue === '' ? null : (float)$feeRateValue,
            'fee_rate_display' => $feeRateDisplay,
            'vip_id' => $vipId,
            'vip_name' => $vipName,
            'vip_status_label' => $vipLabel,
            'vip_expire_time' => $vipExpireTime,
            'is_vip' => $hasVip,
            'is_frozen' => $isFrozen,
            'status_label' => $statusLabel,
            'status_type' => $statusType,
            'real_name_verified' => $isRealName,
            'real_name_status_label' => $realNameLabel,
            'appkey' => trim((string)($user['appkey'] ?? '')),
            'loginfailure' => (int)($user['loginfailure'] ?? 0),
            'timeout_time' => (int)($user['timeout_time'] ?? 0),
            'is_rate' => (int)($user['is_rate'] ?? 0) === 1,
            'order_count' => (int)($stats['order_count'] ?? 0),
            'paid_order_count' => (int)($stats['paid_order_count'] ?? 0),
            'paid_amount' => self::toFloat($stats['paid_amount'] ?? 0),
            'today_paid_amount' => self::toFloat($stats['today_paid_amount'] ?? 0),
            'last_order_time' => self::nullableString($stats['last_order_time'] ?? null),
            'frozen_reason' => AdminFixtureTextNormalizer::normalizeNullable(self::nullableString($user['frozen_reason'] ?? null)),
            'remarks' => $remarks === '' ? null : $remarks,
            'superior_id' => isset($user['superior_id']) ? (int)$user['superior_id'] : null,
            'order_tips' => $orderTips,
            'order_tips_label' => self::notificationChannelLabel($orderTips),
            'low_balance_tips' => $lowBalanceTips,
            'low_balance_tips_label' => self::notificationChannelLabel($lowBalanceTips),
            'low_balance_threshold' => $lowBalanceThreshold === '' ? '0' : $lowBalanceThreshold,
            'wxpusher_uid_configured' => $wxpusherUid !== '',
            'wxpusher_uid_masked' => self::maskIdentifier($wxpusherUid),
            'tg_chat_id_configured' => $tgChatId !== '',
            'tg_chat_id_masked' => self::maskIdentifier($tgChatId),
        ];
    }

    public static function toFloat(mixed $value, int $precision = 2): float
    {
        return round((float)$value, $precision);
    }

    private static function avatar(string $seed): string
    {
        $seed = trim($seed);
        $seed = $seed !== '' ? $seed : 'M';
        $initial = strtoupper(substr($seed, 0, 1));
        $background = substr(md5($seed), 0, 6);
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96"><rect width="100%%" height="100%%" rx="18" fill="#%s"/><text x="50%%" y="52%%" dominant-baseline="middle" text-anchor="middle" font-family="Arial, sans-serif" font-size="38" fill="#ffffff">%s</text></svg>',
            $background,
            htmlspecialchars($initial, ENT_QUOTES)
        );

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }

    private static function normalizeNotificationChannel(mixed $value): string
    {
        $normalized = strtolower(trim((string)$value));

        return match ($normalized) {
            '', '0', 'off', 'disabled' => 'close',
            'email', 'wxpusher', 'tg', 'close' => $normalized,
            default => 'close',
        };
    }

    private static function notificationChannelLabel(string $channel): string
    {
        return match (trim($channel)) {
            'email' => '邮件',
            'wxpusher' => 'WxPusher',
            'tg' => 'Telegram',
            default => '关闭',
        };
    }

    private static function maskIdentifier(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (strlen($value) <= 6) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 3) . str_repeat('*', max(1, strlen($value) - 6)) . substr($value, -3);
    }
}
