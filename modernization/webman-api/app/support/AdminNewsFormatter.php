<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

class AdminNewsFormatter
{
    public static function format(array $news): array
    {
        $content = self::nullableString($news['content'] ?? null);
        $contentText = self::plainText($content);
        $status = (int)($news['status'] ?? 0);
        $type = (int)($news['type'] ?? 0);
        $deleteTime = self::nullableString($news['delete_time'] ?? null);
        $isDeleted = $deleteTime !== null;
        $typeText = self::typeLabel($type);
        $statusText = self::statusLabel($status, $isDeleted);

        return [
            'id' => (int)($news['id'] ?? 0),
            'type' => $type,
            'type_text' => $typeText,
            'type_label' => $typeText,
            'type_tag' => self::typeTag($type),
            'title' => AdminFixtureTextNormalizer::normalize(trim((string)($news['title'] ?? ''))),
            'color' => self::nullableString($news['color'] ?? null),
            'content' => $content,
            'content_text' => $contentText,
            'content_preview' => self::preview($contentText, 140) ?? '暂无公告内容',
            'has_content' => $contentText !== null,
            'status' => $status,
            'status_text' => $statusText,
            'status_label' => $statusText,
            'status_type' => self::statusType($status, $isDeleted),
            'create_time' => self::nullableString($news['create_time'] ?? null),
            'update_time' => self::nullableString($news['update_time'] ?? null),
            'delete_time' => $deleteTime,
            'is_deleted' => $isDeleted,
        ];
    }

    private static function typeLabel(int $type): string
    {
        return match ($type) {
            1 => '平台公告',
            2 => '行业资讯',
            3 => '常见问题',
            default => '未知类型',
        };
    }

    private static function typeTag(int $type): string
    {
        return match ($type) {
            1 => 'primary',
            2 => 'warning',
            3 => 'info',
            default => 'danger',
        };
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
