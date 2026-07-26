<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

class AdminPermissionFormatter
{
    public static function format(array $permission, int $depth = 0): array
    {
        $type = (int)($permission['type'] ?? 0);
        $status = (int)($permission['status'] ?? 0);
        $migration = AdminPermissionMigrationMapper::describe($permission);

        return [
            'id' => (int)($permission['id'] ?? 0),
            'parent_id' => (int)($permission['pid'] ?? $permission['parent_id'] ?? 0),
            'title' => trim((string)($permission['title'] ?? '')),
            'path' => trim((string)($permission['href'] ?? $permission['path'] ?? '')),
            'icon' => trim((string)($permission['icon'] ?? '')),
            'sort' => (int)($permission['sort'] ?? 0),
            'type' => $type,
            'type_label' => self::typeLabel($type),
            'type_tag' => self::typeTag($type),
            'status' => $status,
            'status_label' => self::statusLabel($status),
            'status_type' => self::statusType($status),
            'depth' => $depth,
            'children' => [],
            ...$migration,
        ];
    }

    public static function typeLabel(int $type): string
    {
        return match ($type) {
            0 => '目录',
            1 => '菜单/权限',
            default => '未知类型',
        };
    }

    public static function typeTag(int $type): string
    {
        return match ($type) {
            0 => 'info',
            1 => 'primary',
            default => 'warning',
        };
    }

    public static function statusLabel(int $status): string
    {
        return match ($status) {
            1 => '启用',
            2, 0 => '停用',
            default => '未知状态',
        };
    }

    public static function statusType(int $status): string
    {
        return match ($status) {
            1 => 'success',
            2, 0 => 'warning',
            default => 'info',
        };
    }
}
