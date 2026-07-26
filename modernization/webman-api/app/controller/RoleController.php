<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\controller;

use app\support\AdminPermissionFormatter;
use app\support\AdminRoleFormatter;
use app\support\AdminRouteAuthorization;
use app\support\ApiResponse;
use app\support\RequestPayload;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class RoleController
{
    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $query = Db::table('admin_role')
            ->select('id', 'name', 'desc', 'create_time', 'update_time')
            ->whereNull('delete_time');

        $this->applyFilters($query, $request);

        $total = (int)(clone $query)->count('id');
        $rows = $query
            ->orderByDesc('id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        $roleIds = array_values(array_unique(array_map(
            static fn($row): int => (int)((array)$row)['id'],
            $rows
        )));
        $adminsByRoleId = $this->loadAdminsByRoleId($roleIds);
        $permissionIdsByRoleId = $this->loadPermissionIdsByRoleId($roleIds);
        $totalPermissionCount = $this->totalPermissionCount();

        $records = array_map(function ($row) use (
            $adminsByRoleId,
            $permissionIdsByRoleId,
            $totalPermissionCount
        ): array {
            $record = (array)$row;
            $roleId = (int)($record['id'] ?? 0);
            $name = trim((string)($record['name'] ?? ''));

            return AdminRoleFormatter::format(
                $record,
                $adminsByRoleId[$roleId] ?? [],
                count(array_values(array_unique($permissionIdsByRoleId[$roleId] ?? []))),
                $totalPermissionCount,
                AdminRoleFormatter::isSuperRole($roleId, $name)
            );
        }, $rows);

        return ApiResponse::success([
            'records' => $records,
            'current' => $current,
            'size' => $size,
            'total' => $total,
        ]);
    }

    public function show(Request $request): Response
    {
        $id = $this->roleIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少角色编号', 422, null, 422);
        }

        $record = $this->roleRecord($id);
        if ($record === null) {
            return ApiResponse::error('角色不存在', 404, null, 404);
        }

        $context = $this->roleContext($record);

        return ApiResponse::success([
            'item' => $this->formatRoleRecord($record, $context),
            'permission_tree' => $this->buildPermissionTree(
                $context['permission_ids'],
                $context['grants_all_permissions']
            ),
            'assigned_permission_ids' => $context['permission_ids'],
        ]);
    }

    public function create(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'add');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $payload = $this->normalizeWritePayload(RequestPayload::all($request));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $now = date('Y-m-d H:i:s');
        $roleId = (int)Db::table('admin_role')->insertGetId([
            'name' => $payload['name'],
            'desc' => $payload['desc'],
            'create_time' => $now,
            'update_time' => $now,
            'delete_time' => null,
        ]);

        $created = $this->roleRecord($roleId);
        if ($created === null) {
            return ApiResponse::error('角色已创建，但重新加载失败', 500, null, 500);
        }

        $context = $this->roleContext($created);
        $this->recordAdminRoleCreate($request, $created);

        return ApiResponse::success([
            'item' => $this->formatRoleRecord($created, $context),
            'created_role_id' => $roleId,
            'created_role_label' => $this->roleLabel($created),
        ], '角色已创建');
    }

    public function update(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->roleIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少角色编号', 422, null, 422);
        }

        $record = $this->roleRecord($id);
        if ($record === null) {
            return ApiResponse::error('角色不存在', 404, null, 404);
        }

        try {
            $payload = $this->normalizeWritePayload(RequestPayload::all($request), $record);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table('admin_role')
            ->where('id', $id)
            ->update([
                'name' => $payload['name'],
                'desc' => $payload['desc'],
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        $updated = $this->roleRecord($id);
        if ($updated === null) {
            return ApiResponse::error('角色已更新，但重新加载失败', 500, null, 500);
        }

        $context = $this->roleContext($updated);
        $this->recordAdminRoleUpdate($request, $record, $updated);

        return ApiResponse::success([
            'item' => $this->formatRoleRecord($updated, $context),
            'updated_role_id' => $id,
            'updated_role_label' => $this->roleLabel($updated),
        ], '角色已更新');
    }

    public function permissions(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'permission');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->roleIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少角色编号', 422, null, 422);
        }

        $record = $this->roleRecord($id);
        if ($record === null) {
            return ApiResponse::error('角色不存在', 404, null, 404);
        }

        $blockingReasons = $this->rolePermissionMaintenanceBlockers($record);
        if ($blockingReasons !== []) {
            return ApiResponse::error(
                '当前角色暂不支持直接修改权限',
                422,
                ['blocking_reasons' => $blockingReasons],
                422
            );
        }

        $payload = RequestPayload::all($request);

        try {
            $permissionIds = $this->normalizePermissionIds(
                $payload['permission_ids'] ?? $payload['permissions'] ?? []
            );
            $this->assertPermissionsExist($permissionIds);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $beforePermissionIds = $this->permissionIdsForRole($id);

        Db::transaction(function () use ($id, $permissionIds): void {
            $this->syncRolePermissions($id, $permissionIds);
            Db::table('admin_role')
                ->where('id', $id)
                ->update([
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
        });

        $updated = $this->roleRecord($id);
        if ($updated === null) {
            return ApiResponse::error('角色权限已更新，但重新加载失败', 500, null, 500);
        }

        $context = $this->roleContext($updated);
        $afterPermissionIds = $context['permission_ids'];

        $this->recordAdminRolePermissionSync(
            $request,
            $updated,
            $beforePermissionIds,
            $afterPermissionIds
        );

        return ApiResponse::success([
            'item' => $this->formatRoleRecord($updated, $context),
            'updated_role_id' => $id,
            'updated_role_label' => $this->roleLabel($updated),
            'assigned_permission_ids' => $afterPermissionIds,
            'permission_tree' => $this->buildPermissionTree(
                $afterPermissionIds,
                $context['grants_all_permissions']
            ),
        ], '角色权限已更新');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->roleIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少角色编号', 422, null, 422);
        }

        $record = $this->roleRecord($id);
        if ($record === null) {
            return ApiResponse::error('角色不存在', 404, null, 404);
        }

        $context = $this->roleContext($record);

        return ApiResponse::success([
            'item' => $this->formatRoleRecord($record, $context),
            'audit' => $this->buildRoleDeleteAudit($record, $context),
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->roleIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少角色编号', 422, null, 422);
        }

        $record = $this->roleRecord($id);
        if ($record === null) {
            return ApiResponse::error('角色不存在', 404, null, 404);
        }

        $context = $this->roleContext($record);
        $audit = $this->buildRoleDeleteAudit($record, $context);
        if (empty($audit['can_delete'])) {
            return ApiResponse::error(
                '当前角色仍有关联限制，暂时不能删除',
                422,
                ['audit' => $audit],
                422
            );
        }

        $confirmationPhrase = trim((string)(RequestPayload::all($request)['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('确认短语不正确', 422, ['audit' => $audit], 422);
        }

        Db::transaction(function () use ($id): void {
            $this->deleteRoleRow($id);
        });

        $this->recordAdminRoleDelete($request, $audit);

        return ApiResponse::success([
            'deleted_role_id' => $id,
            'deleted_role_label' => (string)($audit['role_label'] ?? ''),
            'audit' => $audit,
        ], '角色已删除');
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('desc', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('id', (int)$keyword);
                }
            });
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', ''));
        $endDate = $this->normalizeDate((string)$request->get('end_date', ''));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('create_time', '>=', $startDate . ' 00:00:00')
                ->where('create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
        }
    }

    private function roleIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function roleRecord(int $id): ?array
    {
        $row = Db::table('admin_role')
            ->select('id', 'name', 'desc', 'create_time', 'update_time', 'delete_time')
            ->where('id', $id)
            ->whereNull('delete_time')
            ->first();

        return $row ? (array)$row : null;
    }

    private function loadAdminsByRoleId(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $rows = Db::table('admin_admin_role')
            ->join('admin_admin', 'admin_admin_role.admin_id', '=', 'admin_admin.id')
            ->select(
                'admin_admin_role.role_id',
                'admin_admin.id',
                'admin_admin.username',
                'admin_admin.nickname',
                'admin_admin.status'
            )
            ->whereIn('admin_admin_role.role_id', $roleIds)
            ->orderBy('admin_admin.id')
            ->get()
            ->toArray();

        $adminsByRoleId = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $roleId = (int)($record['role_id'] ?? 0);
            $adminsByRoleId[$roleId] ??= [];
            $adminsByRoleId[$roleId][] = $record;
        }

        $rootAdmin = Db::table('admin_admin')
            ->select('id', 'username', 'nickname', 'status')
            ->where('id', 1)
            ->where('status', 1)
            ->first();

        foreach ($roleIds as $roleId) {
            $role = $this->roleRecord((int)$roleId);
            if ($role === null) {
                continue;
            }

            if (!AdminRoleFormatter::isSuperRole((int)$role['id'], (string)$role['name'])) {
                continue;
            }

            if (!$rootAdmin) {
                continue;
            }

            $adminsByRoleId[$roleId] ??= [];
            $hasRootAdmin = false;
            foreach ($adminsByRoleId[$roleId] as $admin) {
                if ((int)($admin['id'] ?? 0) === 1) {
                    $hasRootAdmin = true;
                    break;
                }
            }

            if (!$hasRootAdmin) {
                $adminsByRoleId[$roleId][] = (array)$rootAdmin;
            }
        }

        return $adminsByRoleId;
    }

    private function loadPermissionIdsByRoleId(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $rows = Db::table('admin_role_permission')
            ->select('role_id', 'permission_id')
            ->whereIn('role_id', $roleIds)
            ->get()
            ->toArray();

        $permissionIdsByRoleId = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $roleId = (int)($record['role_id'] ?? 0);
            $permissionIdsByRoleId[$roleId] ??= [];
            $permissionIdsByRoleId[$roleId][] = (int)($record['permission_id'] ?? 0);
        }

        return $permissionIdsByRoleId;
    }

    private function totalPermissionCount(): int
    {
        return (int)Db::table('admin_permission')->count('id');
    }

    private function buildPermissionTree(array $permissionIds, bool $grantsAllPermissions): array
    {
        $rows = Db::table('admin_permission')
            ->select('id', 'pid', 'title', 'href', 'icon', 'sort', 'type', 'status')
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->toArray();

        $checkedMap = [];
        foreach ($permissionIds as $permissionId) {
            $checkedMap[(int)$permissionId] = true;
        }

        $items = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $id = (int)($record['id'] ?? 0);
            $item = AdminPermissionFormatter::format($record);
            $item['checked'] = $grantsAllPermissions || isset($checkedMap[$id]);
            $items[$id] = $item;
        }

        $tree = [];
        foreach ($items as $id => &$item) {
            $parentId = (int)($item['parent_id'] ?? 0);
            if ($parentId > 0 && isset($items[$parentId])) {
                $items[$parentId]['children'][] = &$item;
                continue;
            }

            $tree[] = &$item;
        }
        unset($item);

        return $tree;
    }

    /**
     * @return array{
     *   admins: array<int, array<string, mixed>>,
     *   permission_ids: array<int, int>,
     *   grants_all_permissions: bool,
     *   total_permission_count: int
     * }
     */
    private function roleContext(array $record): array
    {
        $roleId = (int)($record['id'] ?? 0);
        $name = trim((string)($record['name'] ?? ''));

        return [
            'admins' => $this->loadAdminsByRoleId([$roleId])[$roleId] ?? [],
            'permission_ids' => $this->permissionIdsForRole($roleId),
            'grants_all_permissions' => AdminRoleFormatter::isSuperRole($roleId, $name),
            'total_permission_count' => $this->totalPermissionCount(),
        ];
    }

    /**
     * @param array{
     *   admins: array<int, array<string, mixed>>,
     *   permission_ids: array<int, int>,
     *   grants_all_permissions: bool,
     *   total_permission_count: int
     * } $context
     */
    private function formatRoleRecord(array $record, array $context): array
    {
        return AdminRoleFormatter::format(
            $record,
            $context['admins'],
            count($context['permission_ids']),
            $context['total_permission_count'],
            $context['grants_all_permissions']
        );
    }

    /**
     * @param array{
     *   admins: array<int, array<string, mixed>>,
     *   permission_ids: array<int, int>,
     *   grants_all_permissions: bool,
     *   total_permission_count: int
     * } $context
     */
    private function buildRoleDeleteAudit(array $record, array $context): array
    {
        $formatted = $this->formatRoleRecord($record, $context);
        $roleId = (int)($record['id'] ?? 0);
        $assignedAdminCount = count($context['admins']);
        $permissionCount = count($context['permission_ids']);
        $blockingReasons = [];

        if (!empty($context['grants_all_permissions'])) {
            $blockingReasons[] = '系统内置超级角色不可删除。';
        }

        if ($assignedAdminCount > 0) {
            $blockingReasons[] = sprintf(
                '请先移除该角色下的 %d 个管理员账号，再执行删除。',
                $assignedAdminCount
            );
        }

        $warnings = [
            '删除后会永久清理角色记录及对应授权关系，请谨慎操作。',
            '建议先确认管理员归属和授权范围，再执行删除。',
        ];

        if ($assignedAdminCount > 0) {
            $warnings[] = '管理员账号本身不会被删除，请先解除角色绑定后再重试。';
        }

        if ($permissionCount > 0) {
            $warnings[] = sprintf(
                '删除时会同步清理 %d 条角色授权关联记录。',
                $permissionCount
            );
        }

        return [
            'role_id' => $roleId,
            'role_label' => $this->roleLabel($record),
            'role_code' => (string)($formatted['code'] ?? ''),
            'grants_all_permissions' => !empty($context['grants_all_permissions']),
            'assigned_admin_count' => $assignedAdminCount,
            'assigned_admins' => (array)($formatted['admins'] ?? []),
            'permission_count' => $permissionCount,
            'can_delete' => $blockingReasons === [],
            'confirmation_phrase' => $this->roleDeleteConfirmationPhrase($roleId),
            'blocking_reasons' => $blockingReasons,
            'summary' => [
                'delete_role_row_count' => $blockingReasons === [] ? 1 : 0,
                'delete_admin_role_row_count' => $blockingReasons === []
                    ? $this->countRoleAdminAssignments($roleId)
                    : 0,
                'delete_role_permission_row_count' => $blockingReasons === []
                    ? $permissionCount
                    : 0,
                'assigned_admin_count' => $assignedAdminCount,
                'permission_count' => $permissionCount,
                'blocked_count' => $blockingReasons === [] ? 0 : 1,
            ],
            'warnings' => $warnings,
        ];
    }

    private function normalizeWritePayload(array $payload, ?array $current = null): array
    {
        $currentId = (int)($current['id'] ?? 0);
        $name = $this->normalizeRequiredString(
            $payload['name'] ?? ($current['name'] ?? null),
            30,
            'role name'
        );
        $description = $this->normalizeNullableString(
            $payload['description'] ?? $payload['desc'] ?? ($current['desc'] ?? null),
            100,
            'role description'
        );

        if ($currentId <= 0 && AdminRoleFormatter::isSuperRole(0, $name)) {
            throw new \InvalidArgumentException('该角色名称已被系统内置超级角色占用');
        }

        if ($this->roleNameExists($name, $currentId > 0 ? $currentId : null)) {
            throw new \InvalidArgumentException('角色名称已存在');
        }

        return [
            'name' => $name,
            'desc' => $description,
        ];
    }

    private function normalizeRequiredString(mixed $value, int $maxLength, string $field): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '格式不正确');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '不能为空');
        }

        if (mb_strlen($normalized) > $maxLength) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '过长');
        }

        return $normalized;
    }

    private function normalizeNullableString(mixed $value, int $maxLength, string $field): ?string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '格式不正确');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) > $maxLength) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '过长');
        }

        return $normalized;
    }

    private function normalizePermissionIds(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $value = array_values(array_filter(array_map('trim', explode(',', $value)), static fn($item): bool => $item !== ''));
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException('权限编号列表格式不正确');
        }

        $permissionIds = [];
        foreach ($value as $permissionId) {
            $normalized = (int)$permissionId;
            if ($normalized <= 0) {
                continue;
            }

            $permissionIds[$normalized] = $normalized;
        }

        return array_values($permissionIds);
    }

    private function assertPermissionsExist(array $permissionIds): void
    {
        if ($permissionIds === []) {
            return;
        }

        $count = (int)Db::table('admin_permission')
            ->whereIn('id', $permissionIds)
            ->count('id');

        if ($count !== count($permissionIds)) {
            throw new \InvalidArgumentException('存在无效的权限编号，请刷新后重试');
        }
    }

    private function roleNameExists(string $name, ?int $ignoreId = null): bool
    {
        $query = Db::table('admin_role')
            ->whereNull('delete_time')
            ->where('name', $name);

        if ($ignoreId !== null && $ignoreId > 0) {
            $query->where('id', '<>', $ignoreId);
        }

        return $query->exists();
    }

    private function permissionIdsForRole(int $roleId): array
    {
        if ($roleId <= 0) {
            return [];
        }

        $permissionIds = array_values(array_unique($this->loadPermissionIdsByRoleId([$roleId])[$roleId] ?? []));
        sort($permissionIds);

        return $permissionIds;
    }

    private function rolePermissionMaintenanceBlockers(array $record): array
    {
        $roleId = (int)($record['id'] ?? 0);
        $name = trim((string)($record['name'] ?? ''));
        $reasons = [];

        if (AdminRoleFormatter::isSuperRole($roleId, $name)) {
            $reasons[] = '系统内置超级角色默认继承全部权限，仅允许查看';
        }

        return $reasons;
    }

    private function syncRolePermissions(int $roleId, array $permissionIds): void
    {
        Db::table('admin_role_permission')->where('role_id', $roleId)->delete();

        foreach ($permissionIds as $permissionId) {
            Db::table('admin_role_permission')->insert([
                'role_id' => $roleId,
                'permission_id' => (int)$permissionId,
            ]);
        }
    }

    private function countRoleAdminAssignments(int $roleId): int
    {
        return (int)Db::table('admin_admin_role')
            ->where('role_id', $roleId)
            ->count('id');
    }

    private function deleteRoleRow(int $roleId): void
    {
        Db::table('admin_admin_role')
            ->where('role_id', $roleId)
            ->delete();

        Db::table('admin_role_permission')
            ->where('role_id', $roleId)
            ->delete();

        Db::table('admin_role')
            ->where('id', $roleId)
            ->delete();
    }

    private function roleLabel(array $record): string
    {
        $name = trim((string)($record['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return '角色 #' . (int)($record['id'] ?? 0);
    }

    private function roleDeleteConfirmationPhrase(int $roleId): string
    {
        return 'DELETE ROLE ' . $roleId;
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'SystemRole', $authMark);
    }

    private function adminIdFromRequest(Request $request): int
    {
        return (int)(((array)($request->admin ?? []))['id'] ?? 0);
    }

    private function recordAdminRoleCreate(Request $request, array $record): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $roleId = (int)($record['id'] ?? 0);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/roles/create',
            'desc' => sprintf(
                'role create role_id=%d name="%s" description="%s"',
                $roleId,
                $this->truncateLogText($this->roleLabel($record), 120),
                $this->truncateLogText((string)($record['desc'] ?? ''), 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminRoleUpdate(Request $request, array $before, array $after): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $roleId = (int)($after['id'] ?? 0);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/roles/' . $roleId . '/update',
            'desc' => sprintf(
                'role update role_id=%d name="%s" name_changed=%d description_changed=%d',
                $roleId,
                $this->truncateLogText($this->roleLabel($after), 120),
                trim((string)($before['name'] ?? '')) === trim((string)($after['name'] ?? '')) ? 0 : 1,
                trim((string)($before['desc'] ?? '')) === trim((string)($after['desc'] ?? '')) ? 0 : 1
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminRolePermissionSync(
        Request $request,
        array $record,
        array $beforePermissionIds,
        array $afterPermissionIds
    ): void {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $roleId = (int)($record['id'] ?? 0);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/roles/' . $roleId . '/permissions',
            'desc' => sprintf(
                'role permissions role_id=%d name="%s" before="%s" after="%s"',
                $roleId,
                $this->truncateLogText($this->roleLabel($record), 120),
                $this->truncateLogText(implode(',', $beforePermissionIds), 255),
                $this->truncateLogText(implode(',', $afterPermissionIds), 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminRoleDelete(Request $request, array $audit): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $roleId = (int)($audit['role_id'] ?? 0);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/roles/' . $roleId . '/delete',
            'desc' => sprintf(
                'role delete role_id=%d name="%s" delete_role_rows=%d delete_admin_role_rows=%d delete_role_permission_rows=%d assigned_admin_count=%d permission_count=%d',
                $roleId,
                $this->truncateLogText((string)($audit['role_label'] ?? ''), 120),
                (int)($summary['delete_role_row_count'] ?? 0),
                (int)($summary['delete_admin_role_row_count'] ?? 0),
                (int)($summary['delete_role_permission_row_count'] ?? 0),
                (int)($summary['assigned_admin_count'] ?? 0),
                (int)($summary['permission_count'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return null;
        }

        return substr($value, 0, 10);
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'role name' => '角色名称',
            'role description' => '角色备注',
            default => $field,
        };
    }

    private function truncateLogText(string $value, int $limit): string
    {
        $value = trim(str_replace(["\r", "\n"], ' ', $value));
        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, max(0, $limit - 3)) . '...';
    }
}
