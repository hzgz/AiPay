<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

class AdminDomainFormatter
{
    public static function format(array $domain): array
    {
        $id = (int)($domain['id'] ?? 0);
        $userId = (int)($domain['user_id'] ?? 0);
        $siteName = trim((string)($domain['sitename'] ?? ''));
        $rawSiteUrl = trim((string)($domain['siteurl'] ?? ''));
        $siteUrl = $rawSiteUrl;
        $status = (int)($domain['status'] ?? 0);
        $deleteTime = self::nullableString($domain['delete_time'] ?? null);
        $username = trim((string)($domain['merchant_username'] ?? ''));
        $merchantName = trim((string)($domain['merchant_name'] ?? ''));
        $reason = self::nullableString($domain['reason'] ?? null);

        return [
            'id' => $id,
            'user_id' => $userId,
            'merchant_username' => $username,
            'merchant_name' => $merchantName === '' ? null : $merchantName,
            'merchant_email' => self::nullableString($domain['merchant_email'] ?? null),
            'merchant_mobile' => self::nullableString($domain['merchant_mobile'] ?? null),
            'merchant_display' => self::merchantDisplay($userId, $username, $merchantName),
            'sitename' => $siteName,
            'siteurl' => $siteUrl,
            'siteurl_preview' => $siteUrl,
            'siteurl_link' => self::siteUrlLink($rawSiteUrl),
            'status' => $status,
            'status_label' => self::statusLabel($status, $deleteTime),
            'status_text' => self::statusText($status, $deleteTime),
            'status_type' => self::statusType($status, $deleteTime),
            'is_deleted' => $deleteTime !== null,
            'reason' => $reason,
            'reason_preview' => self::preview($reason, 120) ?? '暂无驳回原因',
            'create_time' => self::nullableString($domain['create_time'] ?? null),
            'delete_time' => $deleteTime,
        ];
    }

    public static function statusLabel(int $status, ?string $deleteTime = null): string
    {
        if ($deleteTime !== null) {
            return 'Recycled';
        }

        return match ($status) {
            1 => 'Approved',
            2 => 'Rejected',
            0 => 'Pending',
            default => 'Unknown',
        };
    }

    public static function statusText(int $status, ?string $deleteTime = null): string
    {
        if ($deleteTime !== null) {
            return '回收站';
        }

        return match ($status) {
            1 => '已通过',
            2 => '已驳回',
            0 => '待审核',
            default => '未知状态',
        };
    }

    public static function statusType(int $status, ?string $deleteTime = null): string
    {
        if ($deleteTime !== null) {
            return 'info';
        }

        return match ($status) {
            1 => 'success',
            2 => 'danger',
            0 => 'warning',
            default => 'info',
        };
    }

    private static function merchantDisplay(int $userId, string $username, string $name): string
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

        return $userId > 0 ? '商户 #' . $userId : '未知商户';
    }

    private static function siteUrlLink(string $siteUrl): ?string
    {
        if ($siteUrl === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $siteUrl)) {
            return $siteUrl;
        }

        return '//' . $siteUrl;
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
