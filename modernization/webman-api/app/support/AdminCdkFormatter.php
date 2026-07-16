<?php

namespace app\support;

class AdminCdkFormatter
{
    public static function format(array $cdk): array
    {
        $id = (int)($cdk['id'] ?? 0);
        $type = isset($cdk['type']) ? (int)$cdk['type'] : null;
        $status = (int)($cdk['status'] ?? 0);
        $value = self::nullableString($cdk['value'] ?? null);
        $vipName = AdminFixtureTextNormalizer::normalizeNullable(self::nullableString($cdk['vip_name'] ?? null));
        $code = trim((string)($cdk['code'] ?? ''));
        $faceAmount = $type === 1 ? self::toFloat($value ?? 0, 2) : null;
        $hasCode = $code !== '';
        $isUsed = $status === 1;

        return [
            'id' => $id,
            'type' => $type,
            'type_label' => self::typeLabel($type),
            'type_tag' => self::typeTag($type),
            'value' => $value,
            'value_label' => self::valueLabel($type, $value, $vipName),
            'face_amount' => $faceAmount,
            'vip_id' => $type === 2 && ctype_digit((string)$value) ? (int)$value : null,
            'vip_name' => $vipName,
            'code_masked' => self::maskCode($code),
            'has_code' => $hasCode,
            'code_length' => mb_strlen($code),
            'status' => $status,
            'status_label' => self::statusLabel($status),
            'status_type' => self::statusType($status),
            'is_used' => $isUsed,
            'create_time' => self::nullableString($cdk['create_time'] ?? null),
            'delete_guard_reason' => $isUsed
                ? '已使用卡券在确认后可单条删除，也支持批量清理已使用记录。'
                : '未使用卡券在确认后可永久删除。',
        ];
    }

    public static function toFloat(mixed $value, int $precision = 2): float
    {
        return round((float)$value, $precision);
    }

    public static function typeLabel(?int $type): string
    {
        return match ($type) {
            1 => '余额充值卡',
            2 => 'VIP 兑换卡',
            default => $type !== null ? '卡券类型 ' . $type : '未知类型',
        };
    }

    public static function valueLabel(?int $type, ?string $value, ?string $vipName): string
    {
        if ($type === 1) {
            return '余额 ' . number_format(self::toFloat($value ?? 0, 2), 2, '.', '') . ' 元';
        }

        if ($type === 2) {
            if ($vipName !== null) {
                return $vipName;
            }

            return $value !== null ? 'VIP #' . $value : '未分配 VIP';
        }

        return $value !== null ? AdminFixtureTextNormalizer::normalize($value) : '--';
    }

    public static function maskCode(string $code): ?string
    {
        if ($code === '') {
            return null;
        }

        $length = mb_strlen($code);
        if ($length <= 8) {
            return str_repeat('*', max(4, $length));
        }

        return mb_substr($code, 0, 4)
            . str_repeat('*', 8)
            . mb_substr($code, $length - 4);
    }

    private static function typeTag(?int $type): string
    {
        return match ($type) {
            1 => 'success',
            2 => 'warning',
            default => 'info',
        };
    }

    private static function statusLabel(int $status): string
    {
        return match ($status) {
            0 => '未使用',
            1 => '已使用',
            default => '状态 ' . $status,
        };
    }

    private static function statusType(int $status): string
    {
        return match ($status) {
            0 => 'warning',
            1 => 'info',
            default => 'danger',
        };
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }
}
