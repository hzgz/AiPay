<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

class AdminRiskFormatter
{
    public static function format(array $risk): array
    {
        $id = (int)($risk['id'] ?? 0);
        $userId = (int)($risk['user_id'] ?? 0);
        $username = AdminFixtureTextNormalizer::normalize(trim((string)($risk['merchant_username'] ?? '')));
        $merchantName = AdminFixtureTextNormalizer::normalize(trim((string)($risk['merchant_name'] ?? '')));
        $url = trim((string)($risk['url'] ?? ''));
        // Risk labels are business data and should be returned verbatim.
        $productName = trim((string)($risk['name'] ?? ''));

        return [
            'id' => $id,
            'user_id' => $userId,
            'merchant_username' => $username,
            'merchant_name' => $merchantName === '' ? null : $merchantName,
            'merchant_email' => self::nullableString($risk['merchant_email'] ?? null),
            'merchant_mobile' => self::nullableString($risk['merchant_mobile'] ?? null),
            'merchant_display' => self::merchantDisplay($userId, $username, $merchantName),
            'name' => $productName,
            'name_label' => $productName !== '' ? $productName : '未命名商品',
            'url' => $url,
            'url_preview' => self::preview(AdminFixtureTextNormalizer::normalizeUrlPreview($url), 120) ?? '暂无来源地址',
            'url_link' => self::safeUrl($url),
            'url_host' => self::urlHost($url),
            'create_time' => self::nullableString($risk['create_time'] ?? null),
            'update_time' => self::nullableString($risk['update_time'] ?? null),
        ];
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

    private static function safeUrl(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return null;
    }

    private static function urlHost(string $url): ?string
    {
        $safeUrl = self::safeUrl($url);
        if ($safeUrl === null) {
            return null;
        }

        $host = parse_url($safeUrl, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }

        return AdminFixtureTextNormalizer::normalize($host);
    }

    private static function preview(string $text, int $length): ?string
    {
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
