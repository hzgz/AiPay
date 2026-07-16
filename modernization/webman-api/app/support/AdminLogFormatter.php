<?php

namespace app\support;

class AdminLogFormatter
{
    public static function format(array $log): array
    {
        $adminId = (int)($log['admin_id'] ?? $log['uid'] ?? 0);
        $username = trim((string)($log['admin_username'] ?? $log['username'] ?? ''));
        $nickname = trim((string)($log['admin_nickname'] ?? $log['nickname'] ?? ''));
        $payload = self::decodePayload($log['desc'] ?? null);
        $payloadText = self::payloadText($payload);
        $userAgent = self::nullableString($log['user_agent'] ?? null);
        $url = self::normalizeUrl((string)($log['url'] ?? ''));

        return [
            'id' => (int)($log['id'] ?? 0),
            'admin_id' => $adminId,
            'admin_username' => $username,
            'admin_nickname' => AdminFixtureTextNormalizer::normalize($nickname),
            'admin_display' => self::adminDisplay($adminId, $username, $nickname),
            'url' => $url,
            'path' => self::normalizePath($url),
            'ip' => trim((string)($log['ip'] ?? '')),
            'create_time' => self::nullableString($log['create_time'] ?? null),
            'payload_preview' => self::payloadPreview($payloadText),
            'payload_text' => $payloadText,
            'payload_is_empty' => self::payloadIsEmpty($payload),
            'user_agent_preview' => self::preview($userAgent, 120) ?? '未知设备',
            'user_agent' => $userAgent,
        ];
    }

    private static function adminDisplay(int $adminId, string $username, string $nickname): string
    {
        $normalizedNickname = AdminFixtureTextNormalizer::normalize($nickname);
        $normalizedUsername = AdminFixtureTextNormalizer::normalize($username);

        if ($normalizedNickname !== '') {
            return $normalizedNickname;
        }

        if ($normalizedUsername !== '') {
            return $normalizedUsername;
        }

        return $adminId > 0 ? '管理员 #' . $adminId : '未知管理员';
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
            return $path;
        }

        return $url;
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

        $normalizedPayload = self::normalizePayload($payload);
        $encoded = json_encode(
            $normalizedPayload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        return is_string($encoded) ? $encoded : null;
    }

    private static function payloadPreview(?string $payloadText): string
    {
        if ($payloadText === null) {
            return '无附加参数';
        }

        $normalized = trim($payloadText);
        if ($normalized === '' || $normalized === '[]' || $normalized === '{}') {
            return '无附加参数';
        }

        return self::preview($payloadText, 120) ?? '无附加参数';
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

    private static function normalizePayload(mixed $payload): mixed
    {
        return AdminFixtureTextNormalizer::normalizePayload($payload);
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

        return mb_substr($normalized, 0, $length - 3) . '...';
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }
}
