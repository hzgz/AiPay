<?php

namespace app\support;

class AdminPluginDownloadFormatter
{
    public static function format(array $plugin): array
    {
        $downloadUrl = trim((string)($plugin['downurl'] ?? ''));
        $introduce = self::nullableString($plugin['introduce'] ?? null);
        $introduceText = self::plainText($introduce);
        $status = (int)($plugin['status'] ?? 0);
        $deleteTime = self::nullableString($plugin['delete_time'] ?? null);
        $isDeleted = $deleteTime !== null;

        return [
            'id' => (int)($plugin['id'] ?? 0),
            'name' => trim((string)($plugin['name'] ?? '')),
            'name_label' => self::nameLabel($plugin),
            'downurl' => $downloadUrl,
            'downurl_link' => self::safeUrl($downloadUrl),
            'is_external' => self::isExternal($downloadUrl),
            'has_download_url' => $downloadUrl !== '',
            'introduce' => $introduce,
            'introduce_text' => $introduceText,
            'introduce_preview' => self::preview($introduceText, 140) ?? 'No introduction',
            'introduce_preview_text' => self::preview($introduceText, 140) ?? '暂无插件简介',
            'has_introduce' => $introduceText !== null,
            'status' => $status,
            'status_label' => self::statusLabel($status, $isDeleted),
            'status_text' => self::statusText($status, $isDeleted),
            'status_type' => self::statusType($status, $isDeleted),
            'create_time' => self::nullableString($plugin['create_time'] ?? null),
            'update_time' => self::nullableString($plugin['update_time'] ?? null),
            'delete_time' => $deleteTime,
            'is_deleted' => $isDeleted,
        ];
    }

    private static function nameLabel(array $plugin): string
    {
        $name = trim((string)($plugin['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $id = (int)($plugin['id'] ?? 0);
        return $id > 0 ? '插件 #' . $id : '未命名插件';
    }

    private static function statusLabel(int $status, bool $isDeleted): string
    {
        if ($isDeleted) {
            return 'Recycled';
        }

        return $status === 1 ? 'Visible' : 'Hidden';
    }

    private static function statusText(int $status, bool $isDeleted): string
    {
        if ($isDeleted) {
            return '回收站';
        }

        return $status === 1 ? '展示' : '隐藏';
    }

    private static function statusType(int $status, bool $isDeleted): string
    {
        if ($isDeleted) {
            return 'info';
        }

        return $status === 1 ? 'success' : 'info';
    }

    private static function safeUrl(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        return null;
    }

    private static function isExternal(string $url): bool
    {
        return preg_match('#^https?://#i', $url) === 1;
    }

    private static function plainText(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/\s+/u', ' ', trim($text));

        if (!is_string($normalized) || $normalized === '') {
            return null;
        }

        return $normalized;
    }

    private static function preview(?string $text, int $length): ?string
    {
        if ($text === null) {
            return null;
        }

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, max(1, $length - 3)) . '...';
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }
}
