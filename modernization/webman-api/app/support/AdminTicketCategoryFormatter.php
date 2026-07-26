<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

class AdminTicketCategoryFormatter
{
    public static function format(array $category): array
    {
        $status = self::nullableInt($category['status'] ?? null);
        $sort = self::nullableString($category['sort'] ?? null);
        $ticketCount = (int)($category['ticket_count'] ?? 0);
        $openTicketCount = (int)($category['open_ticket_count'] ?? 0);
        $name = trim((string)($category['name'] ?? ''));

        return [
            'id' => (int)($category['id'] ?? 0),
            'name' => $name,
            'name_label' => self::nameLabel($category),
            'sort' => $sort,
            'sort_number' => is_numeric($sort) ? (int)$sort : null,
            'status' => $status,
            'status_label' => self::statusLabel($status),
            'status_text' => self::statusText($status),
            'status_type' => self::statusType($status),
            'create_time' => self::nullableString($category['create_time'] ?? null),
            'update_time' => self::nullableString($category['update_time'] ?? null),
            'ticket_count' => $ticketCount,
            'open_ticket_count' => $openTicketCount,
            'replied_ticket_count' => (int)($category['replied_ticket_count'] ?? 0),
            'latest_ticket_time' => self::nullableString($category['latest_ticket_time'] ?? null),
            'is_linked' => $ticketCount > 0,
            'link_status_label' => $ticketCount > 0 ? 'Linked Tickets' : 'Unused Category',
            'link_status_text' => $ticketCount > 0 ? '已关联工单' : '未使用分类',
            'delete_blocked' => $ticketCount > 0,
            'delete_guard_reason' => $ticketCount > 0
                ? sprintf(
                    '当前分类已关联 %d 条工单，其中 %d 条仍未处理，请先处理或清理后再删除。',
                    $ticketCount,
                    $openTicketCount
                )
                : '当前分类未关联任何工单，可直接删除。',
        ];
    }

    private static function nameLabel(array $category): string
    {
        $name = trim((string)($category['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $id = (int)($category['id'] ?? 0);
        return $id > 0 ? '分类 #' . $id : '未命名分类';
    }

    private static function statusLabel(?int $status): string
    {
        return match ($status) {
            1 => 'Enabled',
            0 => 'Disabled',
            default => 'Unknown',
        };
    }

    private static function statusText(?int $status): string
    {
        return match ($status) {
            1 => '启用',
            0 => '停用',
            default => '未知',
        };
    }

    private static function statusType(?int $status): string
    {
        return match ($status) {
            1 => 'success',
            0 => 'info',
            default => 'danger',
        };
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }

    private static function nullableInt(mixed $value): ?int
    {
        $string = self::nullableString($value);
        return $string === null ? null : (int)$string;
    }
}
