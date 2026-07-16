<?php

namespace app\support;

class AdminRechargeFormatter
{
    public static function format(array $recharge): array
    {
        $status = (int)($recharge['status'] ?? 0);
        $rtype = (int)($recharge['rtype'] ?? 0);
        $type = trim((string)($recharge['type'] ?? ''));
        $userId = (int)($recharge['user_id'] ?? 0);
        $outTradeNo = trim((string)($recharge['out_trade_no'] ?? ''));
        $username = trim((string)($recharge['merchant_username'] ?? ''));
        $name = self::nullableString($recharge['merchant_name'] ?? null);
        $email = self::nullableString($recharge['merchant_email'] ?? null);
        $mobile = self::nullableString($recharge['merchant_mobile'] ?? null);
        $qrcodeUrl = self::nullableString($recharge['qrcode'] ?? null);
        $regdataText = self::payloadText($recharge['regdata'] ?? null);
        $expiresAt = self::expiresAt($recharge['out_time'] ?? null);
        $isExpired = $status !== 1 && $expiresAt !== null && strtotime($expiresAt) <= time();

        return [
            'id' => (int)($recharge['id'] ?? 0),
            'out_trade_no' => $outTradeNo,
            'user_id' => $userId,
            'merchant_username' => $username,
            'merchant_name' => $name,
            'merchant_email' => $email,
            'merchant_mobile' => $mobile,
            'merchant_display' => self::merchantDisplay($userId, $username, $rtype),
            'type' => $type,
            'type_label' => self::typeLabel($type),
            'type_text' => self::typeText($type),
            'rtype' => $rtype,
            'rtype_label' => self::rtypeLabel($rtype),
            'rtype_text' => self::rtypeText($rtype),
            'money' => self::toFloat($recharge['money'] ?? 0),
            'status' => $status,
            'status_label' => self::statusLabel($status),
            'status_text' => self::statusText($status),
            'status_type' => self::statusType($status),
            'create_time' => self::nullableString($recharge['create_time'] ?? null),
            'end_time' => self::nullableString($recharge['end_time'] ?? null),
            'update_time' => self::nullableString($recharge['update_time'] ?? null),
            'expires_at' => $expiresAt,
            'timeout_status' => self::timeoutStatus($expiresAt, $status, $isExpired),
            'timeout_status_text' => self::timeoutStatusText($expiresAt, $status, $isExpired),
            'is_expired' => $isExpired,
            'has_qrcode' => $qrcodeUrl !== null,
            'qrcode_preview' => $qrcodeUrl ?? 'No QR code',
            'qrcode_url' => $qrcodeUrl,
            'has_regdata' => $regdataText !== null && trim($regdataText) !== '' && trim($regdataText) !== '[]',
            'regdata_preview' => self::payloadPreview($regdataText),
            'regdata_text' => $regdataText,
        ];
    }

    public static function statusLabel(int $status): string
    {
        return match ($status) {
            1 => '已支付',
            0 => '待支付',
            default => '未知',
        };
    }

    public static function statusText(int $status): string
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

    public static function typeLabel(string $type): string
    {
        return match (strtolower(trim($type))) {
            'default' => '默认方式',
            'alipay' => '支付宝',
            'wxpay', 'wechat' => '微信支付',
            'qqpay', 'qq' => 'QQ支付',
            'usdt' => 'USDT',
            'epay_ali' => '易支付支付宝',
            'epay_wechat' => '易支付微信',
            '' => '未知方式',
            default => strtoupper(str_replace('_', ' ', trim($type))),
        };
    }

    public static function typeText(string $type): string
    {
        return match (strtolower(trim($type))) {
            'default' => '默认通道',
            'alipay' => '支付宝',
            'wxpay', 'wechat' => '微信支付',
            'qqpay', 'qq' => 'QQ 钱包',
            'usdt' => 'USDT',
            'epay_ali' => '易支付支付宝',
            'epay_wechat' => '易支付微信',
            '' => '未知方式',
            default => trim($type),
        };
    }

    public static function rtypeLabel(int $rtype): string
    {
        return match ($rtype) {
            1 => 'Registration',
            0 => 'Merchant Recharge',
            default => 'Other Income',
        };
    }

    public static function rtypeText(int $rtype): string
    {
        return match ($rtype) {
            1 => '付费注册',
            0 => '商户充值',
            default => '其他收入',
        };
    }

    public static function toFloat(mixed $value, int $precision = 2): float
    {
        return round((float)$value, $precision);
    }

    private static function merchantDisplay(int $userId, string $username, int $rtype): string
    {
        if ($username !== '') {
            return $username;
        }

        if ($userId > 0) {
            return '商户 #' . $userId;
        }

        if ($rtype === 1) {
            return '游客注册';
        }

        return '平台';
    }

    private static function expiresAt(mixed $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        $timestamp = (int)$value;
        if ($timestamp <= 0) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private static function timeoutStatus(?string $expiresAt, int $status, bool $isExpired): string
    {
        if ($status === 1) {
            return 'Completed';
        }

        if ($expiresAt === null) {
            return 'No Timeout';
        }

        return $isExpired ? 'Expired' : 'Pending';
    }

    private static function timeoutStatusText(?string $expiresAt, int $status, bool $isExpired): string
    {
        if ($status === 1) {
            return '已完成';
        }

        if ($expiresAt === null) {
            return '无超时限制';
        }

        return $isExpired ? '已过期' : '待支付';
    }

    private static function payloadText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }

        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $encoded = json_encode(
                $decoded,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
            );

            return is_string($encoded) ? $encoded : $text;
        }

        return $text;
    }

    private static function payloadPreview(?string $payloadText): string
    {
        if ($payloadText === null) {
            return '暂无附加参数';
        }

        $normalized = trim($payloadText);
        if ($normalized === '' || $normalized === '[]' || $normalized === '{}') {
            return '暂无附加参数';
        }

        return self::preview($payloadText, 120) ?? '暂无附加参数';
    }

    private static function preview(?string $text, int $length): ?string
    {
        if ($text === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($text));
        if (!is_string($normalized) || $normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) <= $length) {
            return $normalized;
        }

        return mb_substr($normalized, 0, max(1, $length - 3)) . '...';
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }
}
