<?php

namespace app\support;

class AdminRoleFormatter
{
    public static function format(
        array $role,
        array $admins,
        int $permissionCount,
        int $totalPermissionCount,
        bool $grantsAllPermissions
    ): array {
        $roleId = (int)($role['id'] ?? 0);
        $name = trim((string)($role['name'] ?? ''));

        return [
            'id' => $roleId,
            'name' => self::displayName($roleId, $name),
            'code' => self::roleCode($roleId, $name),
            'description' => self::descriptionLabel($role['desc'] ?? null, $roleId, $name),
            'scope_label' => self::isSuperRole($roleId, $name) ? '系统超级角色' : '后台角色',
            'grants_all_permissions' => $grantsAllPermissions,
            'permission_count' => $grantsAllPermissions ? $totalPermissionCount : $permissionCount,
            'total_permission_count' => $totalPermissionCount,
            'assigned_admin_count' => count($admins),
            'admins' => array_map(
                static fn (array $admin): array => self::formatAdmin($admin),
                $admins
            ),
            'status_label' => '启用',
            'status_type' => 'success',
            'create_time' => self::nullableString($role['create_time'] ?? null),
            'update_time' => self::nullableString($role['update_time'] ?? null),
        ];
    }

    public static function isSuperRole(int $roleId, string $name): bool
    {
        $normalized = strtolower(trim($name));

        return $roleId === 1
            || str_contains($normalized, 'super')
            || str_contains($name, '超级管理员');
    }

    public static function displayName(int $roleId, string $name): string
    {
        $trimmed = trim($name);

        if (self::isSuperRole($roleId, $trimmed)) {
            return '超级管理员';
        }

        if ($trimmed === '' || preg_match('/^role$/i', $trimmed) === 1) {
            return '角色 #' . $roleId;
        }

        $normalized = strtolower($trimmed);
        if (str_contains($normalized, 'smoke')) {
            if (str_contains($normalized, 'menu')) {
                return '菜单权限角色 #' . $roleId;
            }

            if (str_contains($normalized, 'admin')) {
                return '后台运营角色 #' . $roleId;
            }

            return '系统角色 #' . $roleId;
        }

        return $trimmed;
    }

    public static function descriptionLabel(mixed $value, int $roleId, string $rawName): ?string
    {
        $description = trim((string)$value);

        if ($description === '') {
            return self::isSuperRole($roleId, $rawName) ? '系统超级管理员默认拥有全部权限。' : null;
        }

        $normalized = strtolower($description);
        if (str_contains($normalized, 'smoke')) {
            if (str_contains($normalized, 'menu')) {
                return '用于菜单与权限能力校验。';
            }

            if (str_contains($normalized, 'admin')) {
                return '用于后台账号与权限联动校验。';
            }

            return '用于系统能力校验。';
        }

        return $description;
    }

    private static function formatAdmin(array $admin): array
    {
        $id = (int)($admin['id'] ?? 0);
        $username = trim((string)($admin['username'] ?? ''));
        $nickname = trim((string)($admin['nickname'] ?? ''));

        return [
            'id' => $id,
            'username' => $username,
            'nickname' => $nickname,
            'display' => $nickname !== '' ? $nickname . ' / ' . $username : $username,
            'status' => (int)($admin['status'] ?? 0),
        ];
    }

    private static function roleCode(int $roleId, string $name): string
    {
        if (self::isSuperRole($roleId, $name)) {
            return 'super_admin';
        }

        return 'role_' . $roleId;
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);

        return $string === '' ? null : $string;
    }
}
