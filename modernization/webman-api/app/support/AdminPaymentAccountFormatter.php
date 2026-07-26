<?php

namespace app\support;

class AdminPaymentAccountFormatter
{
    public static function format(array $account, array $stats = []): array
    {
        $id = (int)($account['id'] ?? 0);
        $type = trim((string)($account['type'] ?? ''));
        $code = trim((string)($account['code'] ?? ''));
        $status = (int)($account['status'] ?? 0);
        $enabledStatus = (int)($account['is_status'] ?? 0);
        $typeText = AdminOrderFormatter::paymentTypeLabel($type);
        $statusText = $status === 1 ? '在线' : '离线';
        $enabledStatusText = $enabledStatus === 1 ? '启用' : '禁用';
        $qrType = self::nullableString($account['qr_type'] ?? null);
        $qrTypeText = self::qrTypeLabel($qrType);
        $identifier = self::accountIdentifier($account);
        $merchantUsername = AdminFixtureTextNormalizer::normalize(trim((string)($account['merchant_username'] ?? '')));
        $merchantName = AdminFixtureTextNormalizer::normalize(trim((string)($account['merchant_name'] ?? '')));
        $memo = AdminFixtureTextNormalizer::normalizeNullable(self::nullableString($account['memo'] ?? null));
        $memoText = $memo ?? '无备注';
        $userId = (int)($account['user_id'] ?? 0);

        return [
            'id' => $id,
            'code' => $code,
            'code_display' => AdminFixtureTextNormalizer::normalize($code),
            'code_label' => self::codeLabel($account),
            'type' => $type,
            'type_text' => $typeText,
            'type_label' => $typeText,
            'type_tag' => self::typeTag($type),
            'user_id' => $userId,
            'merchant_username' => $merchantUsername,
            'merchant_name' => $merchantName === '' ? null : $merchantName,
            'merchant_display' => self::merchantDisplay($merchantUsername, $merchantName, $userId),
            'identifier' => $identifier === '' ? null : AdminFixtureTextNormalizer::normalize($identifier),
            'identifier_source' => self::identifierSource($account),
            'identifier_masked' => self::maskIdentifier($identifier),
            'has_identifier' => $identifier !== '',
            'identifier_length' => mb_strlen($identifier),
            'status' => $status,
            'status_text' => $statusText,
            'status_label' => $statusText,
            'status_type' => $status === 1 ? 'success' : 'warning',
            'is_status' => $enabledStatus,
            'is_status_text' => $enabledStatusText,
            'is_status_label' => $enabledStatusText,
            'is_status_type' => $enabledStatus === 1 ? 'success' : 'info',
            'qr_type' => $qrType,
            'qr_type_text' => $qrTypeText,
            'qr_type_label' => $qrTypeText,
            'has_qr_url' => self::filled($account['qr_url'] ?? null),
            'qr_url_length' => mb_strlen(trim((string)($account['qr_url'] ?? ''))),
            'has_cookie' => self::filled($account['cookie'] ?? null),
            'cookie_length' => mb_strlen(trim((string)($account['cookie'] ?? ''))),
            'has_remark' => self::filled($account['remark'] ?? null),
            'has_wx_guid' => self::filled($account['wx_guid'] ?? null),
            'has_cloud_id' => self::filled($account['cloud_id'] ?? null),
            'credential_ready' => self::credentialReady($account),
            'allmaxcount' => (int)($account['allmaxcount'] ?? 0),
            'allmaxmoney' => self::nullableString($account['allmaxmoney'] ?? null),
            'daymaxcount' => (int)($account['daymaxcount'] ?? 0),
            'daymaxmoney' => self::nullableString($account['daymaxmoney'] ?? null),
            'account_balance' => self::toFloat($account['money'] ?? 0),
            'memo' => $memo,
            'memo_text' => $memoText,
            'memo_label' => $memoText,
            'create_time' => self::nullableString($account['create_time'] ?? null),
            'update_time' => self::nullableString($account['update_time'] ?? null),
            'order_count' => (int)($stats['order_count'] ?? 0),
            'paid_order_count' => (int)($stats['paid_order_count'] ?? 0),
            'pending_order_count' => (int)($stats['pending_order_count'] ?? 0),
            'paid_amount' => self::toFloat($stats['paid_amount'] ?? 0),
            'latest_order_time' => self::nullableString($stats['latest_order_time'] ?? null),
        ];
    }

    public static function toFloat(mixed $value, int $precision = 2): float
    {
        return round((float)$value, $precision);
    }

    private static function accountIdentifier(array $account): string
    {
        $code = trim((string)($account['code'] ?? ''));

        return trim((string)match ($code) {
            'alipay_grmg', 'alipay_software', 'alipay_bill' => $account['zfb_pid'] ?? '',
            'qqpay_mg', 'qqpay_software' => $account['qq'] ?? '',
            default => $account['wxname'] ?? '',
        });
    }

    private static function identifierSource(array $account): string
    {
        $code = trim((string)($account['code'] ?? ''));

        return match ($code) {
            'alipay_grmg', 'alipay_software', 'alipay_bill' => 'PID',
            'qqpay_mg', 'qqpay_software' => 'QQ',
            'leshua' => '商户号',
            'usdt' => 'USDT 地址',
            default => '账号标识',
        };
    }

    private static function maskIdentifier(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $length = mb_strlen($value);
        if ($length <= 4) {
            return str_repeat('*', max(4, $length));
        }

        if ($length <= 8) {
            return mb_substr($value, 0, 2)
                . str_repeat('*', max(4, $length - 4))
                . mb_substr($value, $length - 2);
        }

        return mb_substr($value, 0, 3)
            . str_repeat('*', 8)
            . mb_substr($value, $length - 3);
    }

    private static function codeLabel(array $account): string
    {
        $channelName = AdminFixtureTextNormalizer::normalize(trim((string)($account['channel_name'] ?? '')));
        if ($channelName !== '') {
            return $channelName;
        }

        $code = trim((string)($account['code'] ?? ''));
        return $code !== '' ? AdminFixtureTextNormalizer::normalize($code) : '未配置通道';
    }

    private static function typeTag(string $type): string
    {
        return match (strtolower($type)) {
            'alipay' => 'primary',
            'wxpay', 'wechat' => 'success',
            'qqpay', 'qq' => 'warning',
            'usdt' => 'info',
            default => 'info',
        };
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

        return $userId > 0 ? '商户 #' . $userId : '未绑定商户';
    }

    private static function qrTypeLabel(mixed $value): string
    {
        $type = trim((string)$value);
        if ($type === '') {
            return '未配置';
        }

        return match (strtolower($type)) {
            'qrcode' => '固定二维码',
            'agt' => '监听模式',
            'pic' => '图片模式',
            'personormerchant' => '个人/经营码',
            'appreciate' => '赞赏码',
            default => $type,
        };
    }

    private static function credentialReady(array $account): bool
    {
        return self::filled($account['cookie'] ?? null)
            || self::filled($account['qr_url'] ?? null)
            || self::filled($account['remark'] ?? null)
            || self::filled($account['wx_guid'] ?? null)
            || self::filled($account['cloud_id'] ?? null);
    }

    private static function filled(mixed $value): bool
    {
        return trim((string)$value) !== '';
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }
}
