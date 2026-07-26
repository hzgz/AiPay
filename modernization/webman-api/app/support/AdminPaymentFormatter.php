<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

class AdminPaymentFormatter
{
    public static function formatMethod(array $payment, array $orderStats = [], array $accountStats = []): array
    {
        $status = (int)($payment['status'] ?? 0);
        $type = trim((string)($payment['type'] ?? ''));
        $typeText = AdminOrderFormatter::paymentTypeLabel($type);
        $statusText = self::statusLabel($status);

        return [
            'id' => (int)($payment['id'] ?? 0),
            'name' => AdminFixtureTextNormalizer::normalize(trim((string)($payment['name'] ?? ''))),
            'type' => $type,
            'type_text' => $typeText,
            'type_label' => $typeText,
            'sort' => (int)($payment['sort'] ?? 0),
            'status' => $status,
            'status_text' => $statusText,
            'status_label' => $statusText,
            'status_type' => self::statusType($status),
            'create_time' => self::nullableString($payment['create_time'] ?? null),
            'update_time' => self::nullableString($payment['update_time'] ?? null),
            'delete_time' => self::nullableString($payment['delete_time'] ?? null),
            'deleted' => self::nullableString($payment['delete_time'] ?? null) !== null,
            'order_count' => (int)($orderStats['order_count'] ?? 0),
            'paid_order_count' => (int)($orderStats['paid_order_count'] ?? 0),
            'paid_amount' => self::toFloat($orderStats['paid_amount'] ?? 0),
            'enabled_account_count' => (int)($accountStats['enabled_account_count'] ?? 0),
            'online_account_count' => (int)($accountStats['online_account_count'] ?? 0),
            'account_count' => (int)($accountStats['account_count'] ?? 0),
        ];
    }

    public static function formatChannel(array $channel, array $stats = []): array
    {
        $status = (int)($channel['status'] ?? 0);
        $userId = (int)($channel['user_id'] ?? 0);
        $merchantUsername = AdminFixtureTextNormalizer::normalize(trim((string)($channel['merchant_username'] ?? '')));
        $scope = $userId > 0 ? 'merchant' : 'platform';
        $url = trim((string)($channel['url'] ?? ''));
        $name = AdminFixtureTextNormalizer::normalize(trim((string)($channel['name'] ?? '')));
        $scopeText = $scope === 'merchant' ? '商户通道' : '平台通道';
        $gatewayText = self::gatewayLabel((string)($channel['type'] ?? ''));
        $statusText = self::statusLabel($status);

        return [
            'id' => (int)($channel['id'] ?? 0),
            'user_id' => $userId,
            'scope' => $scope,
            'scope_text' => $scopeText,
            'scope_label' => $scopeText,
            'merchant_username' => $merchantUsername,
            'merchant_display' => $merchantUsername !== '' ? $merchantUsername : ($userId > 0 ? ('商户 #' . $userId) : '平台'),
            'type' => trim((string)($channel['type'] ?? '')),
            'gateway_text' => $gatewayText,
            'gateway_label' => $gatewayText,
            'status' => $status,
            'status_text' => $statusText,
            'status_label' => $statusText,
            'status_type' => self::statusType($status),
            'name' => $name,
            'url' => $url,
            'url_host' => self::urlHost($url),
            'pid_preview' => self::maskCredential((string)($channel['pid'] ?? '')),
            'has_key' => trim((string)($channel['key'] ?? '')) !== '',
            'has_other' => trim((string)($channel['other'] ?? '')) !== '',
            'create_time' => self::nullableString($channel['create_time'] ?? null),
            'order_count' => (int)($stats['order_count'] ?? 0),
            'paid_order_count' => (int)($stats['paid_order_count'] ?? 0),
            'paid_amount' => self::toFloat($stats['paid_amount'] ?? 0),
            'latest_order_time' => self::nullableString($stats['latest_order_time'] ?? null),
        ];
    }

    public static function statusLabel(int $status): string
    {
        return match ($status) {
            1 => '启用中',
            0 => '已停用',
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

    public static function gatewayLabel(string $type): string
    {
        return match (strtolower(trim($type))) {
            'epay' => '易支付网关',
            '' => '未配置网关',
            default => strtoupper(trim($type)),
        };
    }

    public static function toFloat(mixed $value, int $precision = 2): float
    {
        return round((float)$value, $precision);
    }

    private static function maskCredential(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (strlen($value) <= 8) {
            return substr($value, 0, 2) . '***' . substr($value, -2);
        }

        return substr($value, 0, 4) . '***' . substr($value, -4);
    }

    private static function urlHost(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return AdminFixtureTextNormalizer::normalize($host);
        }

        return AdminFixtureTextNormalizer::normalizeUrlPreview($url);
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }
}
