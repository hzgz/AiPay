<?php

namespace app\support;

class AdminAccountFormatter
{
    public static function format(
        array $admin,
        array $roles,
        int $directPermissionCount,
        int $effectivePermissionCount,
        int $totalPermissionCount
    ): array {
        $id = (int)($admin['id'] ?? 0);
        $username = trim((string)($admin['username'] ?? ''));
        $nickname = AdminFixtureTextNormalizer::normalize(trim((string)($admin['nickname'] ?? '')));
        $display = $nickname !== ''
            ? $nickname
            : AdminFixtureTextNormalizer::normalize($username);
        $status = (int)($admin['status'] ?? 0);
        $deleteTime = self::nullableString($admin['delete_time'] ?? null);
        $deleted = $deleteTime !== null;
        $isRoot = $id === 1;
        $displayRoles = self::displayRoles($roles, $isRoot);
        $permissionCount = $isRoot ? $totalPermissionCount : $effectivePermissionCount;
        $statusText = self::statusLabel($status);
        $scopeText = self::scopeLabel($isRoot);

        return [
            'id' => $id,
            'username' => $username,
            'nickname' => $nickname,
            'display' => $display,
            'status' => $status,
            'status_text' => $statusText,
            'status_label' => $statusText,
            'status_type' => self::statusType($status),
            'is_root' => $isRoot,
            'scope_text' => $scopeText,
            'scope_label' => $scopeText,
            'roles' => $displayRoles,
            'role_names' => array_map(static fn (array $role): string => $role['name'], $displayRoles),
            'role_count' => count($displayRoles),
            'direct_permission_count' => $isRoot ? 0 : $directPermissionCount,
            'effective_permission_count' => $permissionCount,
            'total_permission_count' => $totalPermissionCount,
            'permission_coverage_label' => $permissionCount . ' / ' . $totalPermissionCount,
            'token_active' => trim((string)($admin['token'] ?? '')) !== '',
            'create_time' => self::nullableString($admin['create_time'] ?? null),
            'update_time' => self::nullableString($admin['update_time'] ?? null),
            'deleted' => $deleted,
            'delete_time' => $deleteTime,
        ];
    }

    public static function formatRole(array $role): array
    {
        $id = (int)($role['id'] ?? 0);
        $rawName = trim((string)($role['name'] ?? ''));

        return [
            'id' => $id,
            'name' => AdminRoleFormatter::displayName($id, $rawName),
            'description' => AdminRoleFormatter::descriptionLabel($role['desc'] ?? null, $id, $rawName),
            'code' => $id === 1 ? 'super_admin' : 'role_' . $id,
        ];
    }

    private static function displayRoles(array $roles, bool $isRoot): array
    {
        $items = array_map(
            static fn (array $role): array => self::formatRole($role),
            $roles
        );

        if (!$isRoot) {
            return $items;
        }

        foreach ($items as $item) {
            if ((int)$item['id'] === 1 || $item['code'] === 'super_admin') {
                return $items;
            }
        }

        array_unshift($items, [
            'id' => 1,
            'name' => '超级管理员',
            'description' => '系统根管理员隐式拥有全部权限',
            'code' => 'super_admin',
        ]);

        return $items;
    }

    private static function scopeLabel(bool $isRoot): string
    {
        return $isRoot ? '系统超级管理员' : '后台管理员';
    }

    private static function statusLabel(int $status): string
    {
        return $status === 1 ? '启用' : '停用';
    }

    private static function statusType(int $status): string
    {
        return $status === 1 ? 'success' : 'warning';
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }
}
