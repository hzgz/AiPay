<?php

namespace app\support;

class AdminFrontLogFormatter
{
    public static function format(array $log): array
    {
        $userId = (int)($log['user_id'] ?? $log['uid'] ?? 0);
        $username = trim((string)($log['merchant_username'] ?? ''));
        $name = trim((string)($log['merchant_name'] ?? ''));
        $email = self::nullableString($log['merchant_email'] ?? null);
        $mobile = self::nullableString($log['merchant_mobile'] ?? null);
        $payload = self::decodePayload($log['desc'] ?? null);
        $payloadText = self::payloadText($payload);
        $userAgent = self::nullableString($log['user_agent'] ?? null);
        $url = self::normalizeUrl((string)($log['url'] ?? ''));

        return [
            'id' => (int)($log['id'] ?? 0),
            'user_id' => $userId,
            'merchant_username' => $username,
            'merchant_name' => $name === '' ? null : $name,
            'merchant_email' => $email,
            'merchant_mobile' => $mobile,
            'merchant_display' => self::merchantDisplay($userId, $username, $name),
            'url' => $url,
            'path' => self::normalizePath($url),
            'ip' => trim((string)($log['ip'] ?? '')),
            'create_time' => self::nullableString($log['create_time'] ?? null),
            'payload_preview' => self::payloadPreview($payloadText),
            'payload_text' => $payloadText,
            'payload_is_empty' => self::payloadIsEmpty($payload),
            'user_agent_preview' => self::preview($userAgent, 120) ?? '未知',
            'user_agent' => $userAgent,
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
            return AdminFixtureTextNormalizer::normalize($username);
        }

        return $userId > 0 ? '商户 #' . $userId : '未知商户';
    }

    private static function normalizeUrl(string $url): string
    {
        $url = trim($url);

        return $url === '' ? '/' : $url;
    }

    private static function normalizePath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && trim($path) !== '') {
            return AdminFixtureTextNormalizer::normalize($path);
        }

        return AdminFixtureTextNormalizer::normalize($url);
    }

    private static function decodePayload(mixed $value): mixed
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
            return $decoded;
        }

        return AdminFixtureTextNormalizer::normalize($text);
    }

    private static function payloadText(mixed $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        if (is_string($payload)) {
            return AdminFixtureTextNormalizer::normalize($payload);
        }

        $normalizedPayload = AdminFixtureTextNormalizer::normalizePayload($payload);

        $encoded = json_encode(
            $normalizedPayload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        return is_string($encoded) ? $encoded : null;
    }

    private static function payloadPreview(?string $payloadText): string
    {
        if ($payloadText === null) {
            return '暂无请求载荷';
        }

        $normalized = trim($payloadText);
        if ($normalized === '' || $normalized === '[]' || $normalized === '{}') {
            return '暂无请求载荷';
        }

        return self::preview($payloadText, 120) ?? '未捕获到请求载荷';
    }

    private static function payloadIsEmpty(mixed $payload): bool
    {
        if ($payload === null) {
            return true;
        }

        if (is_string($payload)) {
            return trim($payload) === '';
        }

        if (is_array($payload)) {
            return $payload === [];
        }

        return false;
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
