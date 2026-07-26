<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

class AdminNavFormatter
{
    public static function format(array $nav): array
    {
        $url = trim((string)($nav['url'] ?? ''));
        $status = (int)($nav['status'] ?? 0);
        $isTarget = (int)($nav['is_target'] ?? 0);
        $deleteTime = self::nullableString($nav['delete_time'] ?? null);
        $isDeleted = $deleteTime !== null;
        $targetText = $isTarget === 1 ? '新窗口打开' : '当前窗口打开';
        $statusText = self::statusLabel($status, $isDeleted);

        return [
            'id' => (int)($nav['id'] ?? 0),
            'name' => AdminFixtureTextNormalizer::normalize(trim((string)($nav['name'] ?? ''))),
            'url' => $url,
            'url_link' => self::safeUrl($url),
            'is_external' => self::isExternal($url),
            'is_target' => $isTarget,
            'target_text' => $targetText,
            'target_label' => $targetText,
            'target_type' => $isTarget === 1 ? 'primary' : 'info',
            'status' => $status,
            'status_text' => $statusText,
            'status_label' => $statusText,
            'status_type' => self::statusType($status, $isDeleted),
            'create_time' => self::nullableString($nav['create_time'] ?? null),
            'sort' => (int)($nav['sort'] ?? 0),
            'delete_time' => $deleteTime,
            'is_deleted' => $isDeleted,
        ];
    }

    private static function statusLabel(int $status, bool $isDeleted): string
    {
        if ($isDeleted) {
            return '回收站';
        }

        return $status === 1 ? '启用中' : '已停用';
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

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }
}
