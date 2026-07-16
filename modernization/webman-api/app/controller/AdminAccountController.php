<?php

namespace app\controller;

use app\controller\concerns\AdminControllerFormatSupport;
use app\support\AdminAccountFormatter;
use app\support\AdminPermissionFormatter;
use app\support\AdminRouteAuthorization;
use app\support\ApiResponse;
use app\support\DatabaseColumnInspector;
use app\support\LegacyPassword;
use app\support\RequestPayload;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class AdminAccountController
{
    use AdminControllerFormatSupport;

    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $query = $this->adminQuery();
        $this->applyFilters($query, $request);

        $total = (int)(clone $query)->count('id');
        $rows = $query
            ->orderByDesc('id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        $adminIds = array_values(array_unique(array_map(
            static fn($row): int => (int)((array)$row)['id'],
            $rows
        )));
        $rolesByAdminId = $this->loadRolesByAdminId($adminIds);
        $permissionStats = $this->permissionStatsByAdminId($adminIds);
        $totalPermissionCount = $this->totalPermissionCount();

        $records = array_map(function ($row) use (
            $rolesByAdminId,
            $permissionStats,
            $totalPermissionCount
        ): array {
            $record = (array)$row;
            $adminId = (int)($record['id'] ?? 0);

            return $this->formatAdminItem(
                $record,
                $rolesByAdminId[$adminId] ?? [],
                $permissionStats[$adminId] ?? [],
                $totalPermissionCount
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
        $id = $this->adminIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('admin id is required', 422, null, 422);
        }

        $summary = $this->findAdminSummary($id, true);
        if ($summary === null) {
            return ApiResponse::error('admin not found', 404, null, 404);
        }

        $record = (array)($summary['record'] ?? []);

        return ApiResponse::success([
            'item' => $summary['item'],
            'roles' => $summary['roles'],
            'permission_tree' => $this->buildPermissionTree(
                (array)($summary['effective_ids'] ?? []),
                $this->isRootAdminId((int)($record['id'] ?? 0))
            ),
            'direct_permission_tree' => $this->buildPermissionTree(
                (array)($summary['direct_ids'] ?? []),
                false
            ),
            'editable' => $this->editablePayload($record, $request, $summary['roles']),
        ]);
    }

    public function template(Request $request): Response
    {
        $authorizationError = (new AdminRouteAuthorization())->authorizeAny(
            $request,
            'SystemAdmins',
            ['index', 'add', 'edit', 'role']
        );
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        return ApiResponse::success([
            'editable' => [
                'username' => '',
                'nickname' => '',
                'status' => 1,
                'current_role_ids' => [],
                'current_direct_permission_ids' => [],
                'available_roles' => $this->availableRoles(),
                'read_only_reasons' => [],
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'add');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $attributes = $this->normalizeCreatePayload($payload);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $roleIds = $attributes['role_ids'];
        unset($attributes['role_ids']);

        if ($roleIds !== []) {
            $roleAuthorizationError = $this->authorizeWrite($request, 'role');
            if ($roleAuthorizationError instanceof Response) {
                return $roleAuthorizationError;
            }
        }

        $now = date('Y-m-d H:i:s');

        $adminId = (int)Db::transaction(function () use ($attributes, $roleIds, $now): int {
            $insertPayload = [
                'username' => $attributes['username'],
                'nickname' => $attributes['nickname'],
                'password' => $attributes['password_hash'],
                'status' => $attributes['status'],
                'token' => null,
                'create_time' => $now,
                'update_time' => $now,
            ];

            if ($this->adminTableHasDeleteTime()) {
                $insertPayload['delete_time'] = null;
            }

            $id = (int)Db::table('admin_admin')->insertGetId($insertPayload);

            $this->syncAdminRoles($id, $roleIds);

            return $id;
        });

        $summary = $this->findAdminSummary($adminId);
        if ($summary === null) {
            return ApiResponse::error('created admin could not be loaded', 500, null, 500);
        }

        $this->recordAdminCreate($request, (array)$summary['record'], $roleIds);

        return ApiResponse::success([
            'item' => $summary['item'],
            'created_admin_id' => $adminId,
            'created_admin_label' => $this->adminLabel((array)$summary['record']),
        ], 'admin account created');
    }

    public function update(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->adminIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('admin id is required', 422, null, 422);
        }

        $record = $this->adminRecord($id);
        if ($record === null) {
            return ApiResponse::error('admin not found', 404, null, 404);
        }

        $blockingReasons = $this->adminMaintenanceBlockers($record, $request);
        if ($blockingReasons !== []) {
            return ApiResponse::error(
                '当前管理员账号暂不支持直接修改',
                422,
                ['blocking_reasons' => $blockingReasons],
                422
            );
        }

        $payload = RequestPayload::all($request);
        if (array_key_exists('status', $payload)) {
            return ApiResponse::error('status updates must use the dedicated status endpoint', 422, null, 422);
        }
        if (array_key_exists('role_ids', $payload)) {
            return ApiResponse::error('role assignment must use the dedicated roles endpoint', 422, null, 422);
        }

        try {
            $attributes = $this->normalizeUpdatePayload($payload, $record);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $updates = [
            'username' => $attributes['username'],
            'nickname' => $attributes['nickname'],
            'update_time' => date('Y-m-d H:i:s'),
        ];

        $passwordReset = $attributes['password_hash'] !== null;
        if ($passwordReset) {
            $updates['password'] = $attributes['password_hash'];
            $updates['token'] = null;
        }

        Db::table('admin_admin')
            ->where('id', $id)
            ->update($updates);

        $summary = $this->findAdminSummary($id);
        if ($summary === null) {
            return ApiResponse::error('updated admin could not be loaded', 500, null, 500);
        }

        $this->recordAdminUpdate($request, $record, (array)$summary['record'], $passwordReset);

        return ApiResponse::success([
            'item' => $summary['item'],
            'updated_admin_id' => $id,
            'updated_admin_label' => $this->adminLabel((array)$summary['record']),
            'password_reset' => $passwordReset,
        ], 'admin account updated');
    }

    public function status(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'status');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->adminIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('admin id is required', 422, null, 422);
        }

        $record = $this->adminRecord($id);
        if ($record === null) {
            return ApiResponse::error('admin not found', 404, null, 404);
        }

        $blockingReasons = $this->adminMaintenanceBlockers($record, $request);
        if ($blockingReasons !== []) {
            return ApiResponse::error(
                '当前管理员账号暂不支持直接修改',
                422,
                ['blocking_reasons' => $blockingReasons],
                422
            );
        }

        $payload = RequestPayload::all($request);

        try {
            $status = $this->normalizeStatus($payload['status'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table('admin_admin')
            ->where('id', $id)
            ->update([
                'status' => $status,
                'token' => null,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        $summary = $this->findAdminSummary($id);
        if ($summary === null) {
            return ApiResponse::error('status-updated admin could not be loaded', 500, null, 500);
        }

        $this->recordAdminStatus($request, $record, (array)$summary['record']);

        return ApiResponse::success([
            'item' => $summary['item'],
            'updated_admin_id' => $id,
            'updated_admin_label' => $this->adminLabel((array)$summary['record']),
            'token_cleared' => true,
        ], 'admin account status updated');
    }

    public function roles(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'role');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->adminIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('admin id is required', 422, null, 422);
        }

        $record = $this->adminRecord($id);
        if ($record === null) {
            return ApiResponse::error('admin not found', 404, null, 404);
        }

        $blockingReasons = $this->adminMaintenanceBlockers($record, $request);
        if ($blockingReasons !== []) {
            return ApiResponse::error(
                '当前管理员账号暂不支持直接修改',
                422,
                ['blocking_reasons' => $blockingReasons],
                422
            );
        }

        $payload = RequestPayload::all($request);

        try {
            $roleIds = $this->normalizeRoleIds($payload['role_ids'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $this->assertRolesExist($roleIds);

        $beforeSummary = $this->findAdminSummary($id);
        if ($beforeSummary === null) {
            return ApiResponse::error('admin not found', 404, null, 404);
        }

        $beforeRoleIds = array_values(array_map(
            static fn(array $role): int => (int)($role['id'] ?? 0),
            (array)($beforeSummary['roles'] ?? [])
        ));

        Db::transaction(function () use ($id, $roleIds): void {
            $this->syncAdminRoles($id, $roleIds);
            Db::table('admin_admin')
                ->where('id', $id)
                ->update([
                    'token' => null,
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
        });

        $summary = $this->findAdminSummary($id);
        if ($summary === null) {
            return ApiResponse::error('role-updated admin could not be loaded', 500, null, 500);
        }

        $this->recordAdminRoleSync(
            $request,
            $record,
            $beforeRoleIds,
            array_values(array_map(
                static fn(array $role): int => (int)($role['id'] ?? 0),
                (array)($summary['roles'] ?? [])
            ))
        );

        return ApiResponse::success([
            'item' => $summary['item'],
            'updated_admin_id' => $id,
            'updated_admin_label' => $this->adminLabel((array)$summary['record']),
            'assigned_role_ids' => array_values(array_map(
                static fn(array $role): int => (int)($role['id'] ?? 0),
                (array)($summary['roles'] ?? [])
            )),
            'token_cleared' => true,
        ], 'admin account roles updated');
    }

    public function permissions(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'permission');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->adminIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('admin id is required', 422, null, 422);
        }

        $record = $this->adminRecord($id);
        if ($record === null) {
            return ApiResponse::error('admin not found', 404, null, 404);
        }

        $blockingReasons = $this->adminMaintenanceBlockers($record, $request);
        if ($blockingReasons !== []) {
            return ApiResponse::error(
                '当前管理员账号暂不支持直接修改',
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
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $this->assertPermissionsExist($permissionIds);
        $beforePermissionIds = $this->directPermissionIdsForAdmin($id);

        Db::transaction(function () use ($id, $permissionIds): void {
            $this->syncAdminDirectPermissions($id, $permissionIds);
            Db::table('admin_admin')
                ->where('id', $id)
                ->update([
                    'token' => null,
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
        });

        $summary = $this->findAdminSummary($id);
        if ($summary === null) {
            return ApiResponse::error('permission-updated admin could not be loaded', 500, null, 500);
        }

        $afterPermissionIds = array_values(array_map(
            static fn($value): int => (int)$value,
            (array)($summary['direct_ids'] ?? [])
        ));

        $this->recordAdminPermissionSync(
            $request,
            $record,
            $beforePermissionIds,
            $afterPermissionIds
        );

        return ApiResponse::success([
            'item' => $summary['item'],
            'updated_admin_id' => $id,
            'updated_admin_label' => $this->adminLabel((array)$summary['record']),
            'assigned_permission_ids' => $afterPermissionIds,
            'token_cleared' => true,
        ], 'admin account direct permissions updated');
    }

    public function batchDeleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $adminIds = $this->normalizeAdminIds(
                RequestPayload::all($request)['admin_ids']
                    ?? RequestPayload::all($request)['ids']
                    ?? []
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success([
            'audit' => $this->batchAdminDeleteAudit($adminIds, $request),
        ]);
    }

    public function batchDelete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $adminIds = $this->normalizeAdminIds(
                $payload['admin_ids'] ?? $payload['ids'] ?? []
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $audit = $this->batchAdminDeleteAudit($adminIds, $request);
        if (empty($audit['can_delete_all'])) {
            return ApiResponse::error(
                'selected admin accounts cannot be batch recycled until the selection is refreshed',
                422,
                ['audit' => $audit],
                422
            );
        }

        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('confirmation phrase mismatch', 422, ['audit' => $audit], 422);
        }

        Db::transaction(function () use ($audit): void {
            foreach ((array)($audit['deletable_admin_ids'] ?? []) as $adminId) {
                $this->deleteAdminRow((int)$adminId);
            }
        });

        $this->recordAdminBatchDelete($request, $audit);

        return ApiResponse::success([
            'deleted_admin_ids' => array_values(array_map('intval', (array)($audit['deletable_admin_ids'] ?? []))),
            'deleted_count' => (int)(($audit['summary'] ?? [])['deletable_count'] ?? 0),
            'audit' => $audit,
        ], 'admin account batch recycle completed');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->adminIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('admin id is required', 422, null, 422);
        }

        $summary = $this->findAdminSummary($id);
        if ($summary === null) {
            return ApiResponse::error('admin not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => $summary['item'],
            'audit' => $this->buildAdminDeleteAudit((array)$summary['record'], $request, (array)$summary['roles']),
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->adminIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('admin id is required', 422, null, 422);
        }

        $summary = $this->findAdminSummary($id);
        if ($summary === null) {
            return ApiResponse::error('admin not found', 404, null, 404);
        }

        $audit = $this->buildAdminDeleteAudit((array)$summary['record'], $request, (array)$summary['roles']);
        if (empty($audit['can_delete'])) {
            return ApiResponse::error(
                'admin account cannot be moved into the recycle bin until every blocker is cleared',
                422,
                ['audit' => $audit],
                422
            );
        }

        $confirmationPhrase = trim((string)(RequestPayload::all($request)['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('confirmation phrase mismatch', 422, ['audit' => $audit], 422);
        }

        Db::transaction(function () use ($id): void {
            $this->deleteAdminRow($id);
        });

        $this->recordAdminDelete($request, $audit);

        return ApiResponse::success([
            'deleted_admin_id' => $id,
            'deleted_admin_label' => (string)($audit['admin_label'] ?? ''),
            'audit' => $audit,
        ], 'admin account moved to recycle bin');
    }

    public function restore(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'recycle');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        if (!$this->adminTableHasDeleteTime()) {
            return ApiResponse::error(
                '当前缺少管理员回收站所需字段，请先执行 admin_admin.delete_time 结构升级。',
                422,
                ['migration' => 'database/migrations/20260701_add_delete_time_to_admin_admin.sql'],
                422
            );
        }

        $id = $this->adminIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('admin id is required', 422, null, 422);
        }

        $record = $this->adminRecord($id, true);
        if ($record === null) {
            return ApiResponse::error('admin not found', 404, null, 404);
        }

        if (empty($record['delete_time'])) {
            return ApiResponse::error('admin account is already active', 422, null, 422);
        }

        $restoreConflict = $this->adminRestoreConflictReason($record);
        if ($restoreConflict !== null) {
            return ApiResponse::error('admin account cannot be restored yet', 422, [
                'blocking_reasons' => [$restoreConflict],
            ], 422);
        }

        Db::transaction(function () use ($id): void {
            $this->restoreAdminRow($id);
        });

        $summary = $this->findAdminSummary($id, true);
        if ($summary === null) {
            return ApiResponse::error('admin restore failed', 500, null, 500);
        }

        $this->recordAdminRestore($request, $record);

        return ApiResponse::success([
            'restored_admin_id' => $id,
            'restored_admin_label' => $this->adminLabel((array)$summary['record']),
            'item' => $summary['item'],
        ], 'admin account restored');
    }

    public function batchRestore(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'recycle');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        if (!$this->adminTableHasDeleteTime()) {
            return ApiResponse::error(
                '当前缺少管理员回收站所需字段，请先执行 admin_admin.delete_time 结构升级。',
                422,
                ['migration' => 'database/migrations/20260701_add_delete_time_to_admin_admin.sql'],
                422
            );
        }

        $payload = RequestPayload::all($request);

        try {
            $adminIds = $this->normalizeAdminIds(
                $payload['admin_ids'] ?? $payload['ids'] ?? []
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $recordsById = $this->loadAdminRecordsByIds($adminIds, true);
        $restorableRows = [];
        $alreadyActiveAdminIds = [];
        $missingAdminIds = [];
        $blockedItems = [];

        foreach ($adminIds as $adminId) {
            $record = $recordsById[$adminId] ?? null;
            if ($record === null) {
                $missingAdminIds[] = $adminId;
                continue;
            }

            if (empty($record['delete_time'])) {
                $alreadyActiveAdminIds[] = $adminId;
                continue;
            }

            $restoreConflict = $this->adminRestoreConflictReason($record);
            if ($restoreConflict !== null) {
                $blockedItems[] = [
                    'admin_id' => $adminId,
                    'admin_label' => $this->adminLabel($record),
                    'reason' => $restoreConflict,
                ];
                continue;
            }

            $restorableRows[] = $record;
        }

        if ($restorableRows === []) {
            return ApiResponse::error('no recycled admin accounts matched the restore request', 422, [
                'requested_admin_ids' => $adminIds,
                'already_active_admin_ids' => $alreadyActiveAdminIds,
                'missing_admin_ids' => $missingAdminIds,
                'blocked_items' => $blockedItems,
            ], 422);
        }

        $restoredAdminIds = array_values(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $restorableRows
        ));

        Db::transaction(function () use ($restoredAdminIds): void {
            foreach ($restoredAdminIds as $adminId) {
                $this->restoreAdminRow($adminId);
            }
        });

        $this->recordAdminBatchRestore(
            $request,
            $restorableRows,
            $adminIds,
            $alreadyActiveAdminIds,
            $missingAdminIds,
            $blockedItems
        );

        return ApiResponse::success([
            'requested_admin_ids' => $adminIds,
            'restored_admin_ids' => $restoredAdminIds,
            'restored_count' => count($restoredAdminIds),
            'already_active_admin_ids' => $alreadyActiveAdminIds,
            'missing_admin_ids' => $missingAdminIds,
            'blocked_items' => $blockedItems,
        ], 'admin accounts restored');
    }

    private function adminQuery(): Builder
    {
        $columns = ['id', 'username', 'nickname', 'status', 'token', 'create_time', 'update_time'];
        if ($this->adminTableHasDeleteTime()) {
            $columns[] = 'delete_time';
        }

        return Db::table('admin_admin')->select(...$columns);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', $request->get('username', '')));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('username', 'like', '%' . $keyword . '%')
                    ->orWhere('nickname', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('id', (int)$keyword);
                }
            });
        }

        $status = trim((string)$request->get('status', ''));
        if ($status === '-1' || strtolower($status) === 'deleted') {
            if ($this->adminTableHasDeleteTime()) {
                $query->whereNotNull('delete_time');
            } else {
                $query->whereRaw('1 = 0');
            }

            return;
        }

        $this->applyAdminDeleteTimeFilter($query);
        if ($status !== '') {
            $query->where('status', (int)$status);
        }
    }

    private function adminIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function actorAdminId(Request $request): int
    {
        return (int)(((array)($request->admin ?? []))['id'] ?? 0);
    }

    private function adminRecord(int $id, bool $includeDeleted = false): ?array
    {
        $query = $this->adminQuery()->where('id', $id);
        if (!$includeDeleted) {
            $this->applyAdminDeleteTimeFilter($query);
        }

        $row = $query->first();

        return $row ? (array)$row : null;
    }

    private function loadAdminRecordsByIds(array $adminIds, bool $includeDeleted = false): array
    {
        if ($adminIds === []) {
            return [];
        }

        $query = $this->adminQuery()->whereIn('id', $adminIds);
        if (!$includeDeleted) {
            $this->applyAdminDeleteTimeFilter($query);
        }

        $rows = $query->get()->toArray();

        $records = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $adminId = (int)($record['id'] ?? 0);
            if ($adminId > 0) {
                $records[$adminId] = $record;
            }
        }

        return $records;
    }

    private function findAdminSummary(int $id, bool $includeDeleted = false): ?array
    {
        $record = $this->adminRecord($id, $includeDeleted);
        if ($record === null) {
            return null;
        }

        $rolesByAdminId = $this->loadRolesByAdminId([$id]);
        $permissionStats = $this->permissionStatsByAdminId([$id]);
        $totalPermissionCount = $this->totalPermissionCount();
        $stats = $permissionStats[$id] ?? [
            'direct_count' => 0,
            'direct_ids' => [],
            'effective_count' => 0,
            'effective_ids' => [],
        ];

        return [
            'record' => $record,
            'item' => $this->formatAdminItem($record, $rolesByAdminId[$id] ?? [], $stats, $totalPermissionCount),
            'roles' => array_map(
                static fn(array $role): array => AdminAccountFormatter::formatRole($role),
                $rolesByAdminId[$id] ?? []
            ),
            'direct_ids' => array_values(array_map(
                static fn($value): int => (int)$value,
                (array)($stats['direct_ids'] ?? [])
            )),
            'effective_ids' => array_values(array_map(
                static fn($value): int => (int)$value,
                (array)($stats['effective_ids'] ?? [])
            )),
        ];
    }

    private function formatAdminItem(
        array $record,
        array $roles,
        array $stats,
        int $totalPermissionCount
    ): array {
        return AdminAccountFormatter::format(
            $record,
            $roles,
            (int)($stats['direct_count'] ?? 0),
            (int)($stats['effective_count'] ?? 0),
            $totalPermissionCount
        );
    }

    private function loadRolesByAdminId(array $adminIds): array
    {
        if ($adminIds === []) {
            return [];
        }

        $rows = Db::table('admin_admin_role')
            ->join('admin_role', 'admin_admin_role.role_id', '=', 'admin_role.id')
            ->select('admin_admin_role.admin_id', 'admin_role.id', 'admin_role.name', 'admin_role.desc')
            ->whereIn('admin_admin_role.admin_id', $adminIds)
            ->whereNull('admin_role.delete_time')
            ->orderBy('admin_role.id')
            ->get()
            ->toArray();

        $rolesByAdminId = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $adminId = (int)($record['admin_id'] ?? 0);
            $rolesByAdminId[$adminId] ??= [];
            $rolesByAdminId[$adminId][] = $record;
        }

        return $rolesByAdminId;
    }

    private function permissionStatsByAdminId(array $adminIds): array
    {
        if ($adminIds === []) {
            return [];
        }

        $directPermissionIds = $this->directPermissionIdsByAdminId($adminIds);
        $rolePermissionIds = $this->rolePermissionIdsByAdminId($adminIds);

        $stats = [];
        foreach ($adminIds as $adminId) {
            $directIds = array_values(array_unique($directPermissionIds[$adminId] ?? []));
            $roleIds = array_values(array_unique($rolePermissionIds[$adminId] ?? []));
            $effectiveIds = array_values(array_unique(array_merge($directIds, $roleIds)));
            sort($directIds);
            sort($roleIds);
            sort($effectiveIds);

            $stats[$adminId] = [
                'direct_count' => count($directIds),
                'direct_ids' => $directIds,
                'effective_count' => count($effectiveIds),
                'effective_ids' => $effectiveIds,
            ];
        }

        return $stats;
    }

    private function directPermissionIdsByAdminId(array $adminIds): array
    {
        if ($adminIds === []) {
            return [];
        }

        $rows = Db::table('admin_admin_permission')
            ->select('admin_id', 'permission_id')
            ->whereIn('admin_id', $adminIds)
            ->get()
            ->toArray();

        $permissionIdsByAdminId = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $adminId = (int)($record['admin_id'] ?? 0);
            $permissionIdsByAdminId[$adminId] ??= [];
            $permissionIdsByAdminId[$adminId][] = (int)($record['permission_id'] ?? 0);
        }

        return $permissionIdsByAdminId;
    }

    private function rolePermissionIdsByAdminId(array $adminIds): array
    {
        if ($adminIds === []) {
            return [];
        }

        $rows = Db::table('admin_admin_role')
            ->join('admin_role_permission', 'admin_admin_role.role_id', '=', 'admin_role_permission.role_id')
            ->select('admin_admin_role.admin_id', 'admin_role_permission.permission_id')
            ->whereIn('admin_admin_role.admin_id', $adminIds)
            ->get()
            ->toArray();

        $permissionIdsByAdminId = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $adminId = (int)($record['admin_id'] ?? 0);
            $permissionIdsByAdminId[$adminId] ??= [];
            $permissionIdsByAdminId[$adminId][] = (int)($record['permission_id'] ?? 0);
        }

        return $permissionIdsByAdminId;
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

    private function editablePayload(array $record, Request $request, array $roles): array
    {
        return [
            'username' => trim((string)($record['username'] ?? '')),
            'nickname' => trim((string)($record['nickname'] ?? '')),
            'status' => (int)($record['status'] ?? 0),
            'current_role_ids' => array_values(array_map(
                static fn(array $role): int => (int)($role['id'] ?? 0),
                $roles
            )),
            'current_direct_permission_ids' => $this->directPermissionIdsForAdmin((int)($record['id'] ?? 0)),
            'available_roles' => $this->availableRoles(),
            'read_only_reasons' => $this->adminMaintenanceBlockers($record, $request),
        ];
    }

    private function availableRoles(): array
    {
        $rows = Db::table('admin_role')
            ->select('id', 'name', 'desc')
            ->whereNull('delete_time')
            ->orderBy('id')
            ->get()
            ->toArray();

        $roleIds = array_values(array_unique(array_map(
            static fn($row): int => (int)((array)$row)['id'],
            $rows
        )));

        $assignedCounts = [];
        if ($roleIds !== []) {
            $countRows = Db::table('admin_admin_role')
                ->select('role_id', Db::raw('count(*) as assigned_admin_count'))
                ->whereIn('role_id', $roleIds)
                ->groupBy('role_id')
                ->get()
                ->toArray();

            foreach ($countRows as $countRow) {
                $countRecord = (array)$countRow;
                $assignedCounts[(int)($countRecord['role_id'] ?? 0)] = (int)($countRecord['assigned_admin_count'] ?? 0);
            }
        }

        return array_map(function ($row) use ($assignedCounts): array {
            $record = (array)$row;
            $roleId = (int)($record['id'] ?? 0);
            $formatted = AdminAccountFormatter::formatRole($record);

            return [
                'id' => $roleId,
                'name' => (string)($formatted['name'] ?? ''),
                'description' => $formatted['description'] ?? null,
                'code' => (string)($formatted['code'] ?? ''),
                'grants_all_permissions' => $this->isSuperRoleRecord($record),
                'assigned_admin_count' => (int)($assignedCounts[$roleId] ?? 0),
            ];
        }, $rows);
    }

    private function normalizeCreatePayload(array $payload): array
    {
        $username = $this->normalizeUsername($payload['username'] ?? '');
        $nickname = $this->normalizeNickname($payload['nickname'] ?? '');
        $password = $this->normalizePassword($payload['password'] ?? null, true);
        $status = $this->normalizeStatus($payload['status'] ?? 1);
        $roleIds = $this->normalizeRoleIds($payload['role_ids'] ?? []);

        $this->assertUniqueUsername($username);
        $this->assertRolesExist($roleIds);

        return [
            'username' => $username,
            'nickname' => $nickname,
            'password_hash' => LegacyPassword::hash((string)$password),
            'status' => $status,
            'role_ids' => $roleIds,
        ];
    }

    private function normalizeUpdatePayload(array $payload, array $record): array
    {
        $username = $this->normalizeUsername($payload['username'] ?? ($record['username'] ?? ''));
        $nickname = $this->normalizeNickname($payload['nickname'] ?? ($record['nickname'] ?? ''));
        $password = $this->normalizePassword($payload['password'] ?? null, false);

        $this->assertUniqueUsername($username, (int)($record['id'] ?? 0));

        return [
            'username' => $username,
            'nickname' => $nickname,
            'password_hash' => $password !== null ? LegacyPassword::hash($password) : null,
        ];
    }

    private function normalizeUsername(mixed $value): string
    {
        $username = trim((string)$value);
        if ($username === '') {
            throw new \InvalidArgumentException('username is required');
        }

        if (strlen($username) < 2 || strlen($username) > 40) {
            throw new \InvalidArgumentException('username must be between 2 and 40 characters');
        }

        if (preg_match('/\s/', $username)) {
            throw new \InvalidArgumentException('username cannot contain whitespace');
        }

        return $username;
    }

    private function normalizeNickname(mixed $value): string
    {
        $nickname = trim((string)$value);
        if ($nickname === '') {
            throw new \InvalidArgumentException('nickname is required');
        }

        if (strlen($nickname) > 40) {
            throw new \InvalidArgumentException('nickname must be 40 characters or fewer');
        }

        return $nickname;
    }

    private function normalizePassword(mixed $value, bool $required): ?string
    {
        $password = trim((string)($value ?? ''));
        if ($password === '') {
            if ($required) {
                throw new \InvalidArgumentException('password is required');
            }

            return null;
        }

        if (strlen($password) < 6 || strlen($password) > 64) {
            throw new \InvalidArgumentException('password must be between 6 and 64 characters');
        }

        return $password;
    }

    private function normalizeStatus(mixed $value): int
    {
        if ($value === null || $value === '') {
            throw new \InvalidArgumentException('status is required');
        }

        $status = (int)$value;
        if (!in_array($status, [0, 1], true)) {
            throw new \InvalidArgumentException('status must be 0 or 1');
        }

        return $status;
    }

    private function normalizeRoleIds(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $value = array_values(array_filter(array_map('trim', explode(',', $value)), static fn($item): bool => $item !== ''));
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException('role_ids must be an array of role ids');
        }

        $roleIds = [];
        foreach ($value as $roleId) {
            $normalized = (int)$roleId;
            if ($normalized <= 0) {
                continue;
            }

            $roleIds[$normalized] = $normalized;
        }

        return array_values($roleIds);
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
            throw new \InvalidArgumentException('permission_ids must be an array of permission ids');
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

    private function normalizeAdminIds(mixed $value, int $maxCount = 100): array
    {
        $items = [];

        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $items = preg_split('/\s*,\s*/', trim($value)) ?: [];
        } elseif (is_numeric($value)) {
            $items = [$value];
        }

        $adminIds = [];
        foreach ($items as $item) {
            if (is_bool($item) || is_array($item) || is_object($item)) {
                continue;
            }

            $normalized = trim((string)$item);
            if ($normalized === '' || !ctype_digit($normalized)) {
                continue;
            }

            $adminId = (int)$normalized;
            if ($adminId > 0) {
                $adminIds[$adminId] = $adminId;
            }
        }

        $adminIds = array_values($adminIds);
        sort($adminIds);

        if ($adminIds === []) {
            throw new \InvalidArgumentException('admin ids are required');
        }

        if (count($adminIds) > $maxCount) {
            throw new \InvalidArgumentException('too many admin accounts were selected for one batch action');
        }

        return $adminIds;
    }

    private function assertUniqueUsername(string $username, ?int $ignoreId = null): void
    {
        $query = Db::table('admin_admin')->where('username', $username);
        if ($ignoreId !== null && $ignoreId > 0) {
            $query->where('id', '<>', $ignoreId);
        }

        if ($query->exists()) {
            throw new \InvalidArgumentException('username already exists');
        }
    }

    private function assertRolesExist(array $roleIds): void
    {
        if ($roleIds === []) {
            return;
        }

        $count = (int)Db::table('admin_role')
            ->whereIn('id', $roleIds)
            ->whereNull('delete_time')
            ->count('id');

        if ($count !== count($roleIds)) {
            throw new \InvalidArgumentException('one or more role_ids were not found');
        }
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
            throw new \InvalidArgumentException('one or more permission_ids were not found');
        }
    }

    private function adminMaintenanceBlockers(array $record, Request $request): array
    {
        $adminId = (int)($record['id'] ?? 0);
        $reasons = [];

        if (!empty($record['delete_time'])) {
            $reasons[] = '当前管理员账号已进入回收站，请先恢复后再继续维护';
        }

        if ($this->isRootAdminId($adminId)) {
            $reasons[] = '系统内置 root 管理员默认仅允许查看';
        }

        if ($adminId === $this->actorAdminId($request)) {
            $reasons[] = '当前已登录的管理员账号请通过个人中心流程自行维护';
        }

        return $reasons;
    }

    private function syncAdminRoles(int $adminId, array $roleIds): void
    {
        Db::table('admin_admin_role')->where('admin_id', $adminId)->delete();

        foreach ($roleIds as $roleId) {
            Db::table('admin_admin_role')->insert([
                'admin_id' => $adminId,
                'role_id' => (int)$roleId,
            ]);
        }
    }

    private function syncAdminDirectPermissions(int $adminId, array $permissionIds): void
    {
        Db::table('admin_admin_permission')->where('admin_id', $adminId)->delete();

        foreach ($permissionIds as $permissionId) {
            Db::table('admin_admin_permission')->insert([
                'admin_id' => $adminId,
                'permission_id' => (int)$permissionId,
            ]);
        }
    }

    private function directPermissionIdsForAdmin(int $adminId): array
    {
        if ($adminId <= 0) {
            return [];
        }

        $permissionIds = array_values(array_unique($this->directPermissionIdsByAdminId([$adminId])[$adminId] ?? []));
        sort($permissionIds);

        return $permissionIds;
    }

    private function buildAdminDeleteAudit(array $record, Request $request, array $roles): array
    {
        $adminId = (int)($record['id'] ?? 0);
        $roleIds = array_values(array_map(
            static fn(array $role): int => (int)($role['id'] ?? 0),
            $roles
        ));
        $roleLinkCount = (int)Db::table('admin_admin_role')->where('admin_id', $adminId)->count();
        $directPermissionCount = (int)Db::table('admin_admin_permission')->where('admin_id', $adminId)->count();
        $adminLogCount = (int)Db::table('admin_admin_log')->where('uid', $adminId)->count();
        $blockingReasons = $this->adminMaintenanceBlockers($record, $request);
        $recycleAvailable = $this->adminTableHasDeleteTime();
        if (!$recycleAvailable) {
            $blockingReasons[] = '当前缺少管理员回收站所需字段，删除前请先完成 admin_admin.delete_time 结构升级。';
        }

        $tokenActive = trim((string)($record['token'] ?? '')) !== '';
        $warnings = [];

        if ($tokenActive) {
            $warnings[] = '移入回收站后，该管理员当前保存的登录令牌会一并失效。';
        }

        $warnings[] = '已分配角色和直接权限会被保留，恢复后仍会继续生效。';

        if ($adminLogCount > 0) {
            $warnings[] = '历史后台操作日志会继续保留，不会随账号一并删除。';
        }

        return [
            'admin_id' => $adminId,
            'admin_label' => $this->adminLabel($record),
            'username' => trim((string)($record['username'] ?? '')),
            'nickname' => trim((string)($record['nickname'] ?? '')),
            'status' => (int)($record['status'] ?? 0),
            'status_label' => (int)($record['status'] ?? 0) === 1 ? 'enabled' : 'disabled',
            'token_active' => $tokenActive,
            'role_ids' => $roleIds,
            'roles' => $roles,
            'assigned_role_count' => count($roleIds),
            'direct_permission_count' => $directPermissionCount,
            'can_delete' => $blockingReasons === [],
            'confirmation_phrase' => $this->adminDeleteConfirmationPhrase($adminId),
            'blocking_reasons' => $blockingReasons,
            'summary' => [
                'recycle_admin_row_count' => $recycleAvailable ? 1 : 0,
                'retained_admin_role_row_count' => $roleLinkCount,
                'retained_admin_permission_row_count' => $directPermissionCount,
                'retained_admin_log_row_count' => $adminLogCount,
                'assigned_role_count' => count($roleIds),
                'direct_permission_count' => $directPermissionCount,
                'blocked_count' => count($blockingReasons),
            ],
            'warnings' => $warnings,
        ];
    }

    private function batchAdminDeleteAudit(array $adminIds, Request $request): array
    {
        $recordsById = $this->loadAdminRecordsByIds($adminIds);
        $items = [];
        $deletableAdminIds = [];
        $blockedAdminIds = [];
        $missingAdminIds = [];
        $recycleAdminRowCount = 0;
        $retainedRoleRowCount = 0;
        $retainedPermissionRowCount = 0;
        $retainedAdminLogRowCount = 0;
        $assignedRoleCount = 0;
        $directPermissionCount = 0;
        $activeTokenCount = 0;

        foreach ($adminIds as $adminId) {
            $record = $recordsById[$adminId] ?? null;
            if ($record === null) {
                $missingAdminIds[] = $adminId;
                $items[] = [
                    'admin_id' => $adminId,
                    'admin_label' => 'admin #' . $adminId,
                    'username' => '',
                    'nickname' => '',
                    'status' => 0,
                    'status_label' => 'missing',
                    'token_active' => false,
                    'assigned_role_count' => 0,
                    'direct_permission_count' => 0,
                    'exists' => false,
                    'can_delete' => false,
                    'blocking_reasons' => ['this admin account no longer exists in the live table'],
                    'warnings' => ['refresh the selection before retrying the batch recycle'],
                    'summary' => [
                        'recycle_admin_row_count' => 0,
                        'retained_admin_role_row_count' => 0,
                        'retained_admin_permission_row_count' => 0,
                        'retained_admin_log_row_count' => 0,
                        'assigned_role_count' => 0,
                        'direct_permission_count' => 0,
                        'blocked_count' => 1,
                    ],
                ];
                continue;
            }

            $summary = $this->findAdminSummary($adminId);
            if ($summary === null) {
                $missingAdminIds[] = $adminId;
                $items[] = [
                    'admin_id' => $adminId,
                    'admin_label' => 'admin #' . $adminId,
                    'username' => '',
                    'nickname' => '',
                    'status' => 0,
                    'status_label' => 'missing',
                    'token_active' => false,
                    'assigned_role_count' => 0,
                    'direct_permission_count' => 0,
                    'exists' => false,
                    'can_delete' => false,
                    'blocking_reasons' => ['this admin account could not be reloaded from the live table'],
                    'warnings' => ['refresh the selection before retrying the batch recycle'],
                    'summary' => [
                        'recycle_admin_row_count' => 0,
                        'retained_admin_role_row_count' => 0,
                        'retained_admin_permission_row_count' => 0,
                        'retained_admin_log_row_count' => 0,
                        'assigned_role_count' => 0,
                        'direct_permission_count' => 0,
                        'blocked_count' => 1,
                    ],
                ];
                continue;
            }

            $audit = $this->buildAdminDeleteAudit(
                (array)$summary['record'],
                $request,
                (array)$summary['roles']
            );

            if (!empty($audit['can_delete'])) {
                $deletableAdminIds[] = $adminId;
                $recycleAdminRowCount += (int)(($audit['summary'] ?? [])['recycle_admin_row_count'] ?? 0);
                $retainedRoleRowCount += (int)(($audit['summary'] ?? [])['retained_admin_role_row_count'] ?? 0);
                $retainedPermissionRowCount += (int)(($audit['summary'] ?? [])['retained_admin_permission_row_count'] ?? 0);
                $retainedAdminLogRowCount += (int)(($audit['summary'] ?? [])['retained_admin_log_row_count'] ?? 0);
                $assignedRoleCount += (int)(($audit['summary'] ?? [])['assigned_role_count'] ?? 0);
                $directPermissionCount += (int)(($audit['summary'] ?? [])['direct_permission_count'] ?? 0);
                if (!empty($audit['token_active'])) {
                    $activeTokenCount++;
                }
            } else {
                $blockedAdminIds[] = $adminId;
            }

            $items[] = [
                'admin_id' => $adminId,
                'admin_label' => (string)($audit['admin_label'] ?? $this->adminLabel($record)),
                'username' => (string)($audit['username'] ?? ''),
                'nickname' => (string)($audit['nickname'] ?? ''),
                'status' => (int)($audit['status'] ?? 0),
                'status_label' => (string)($audit['status_label'] ?? 'disabled'),
                'token_active' => !empty($audit['token_active']),
                'assigned_role_count' => (int)($audit['assigned_role_count'] ?? 0),
                'direct_permission_count' => (int)($audit['direct_permission_count'] ?? 0),
                'exists' => true,
                'can_delete' => !empty($audit['can_delete']),
                'blocking_reasons' => array_values(array_map('strval', (array)($audit['blocking_reasons'] ?? []))),
                'warnings' => array_values(array_map('strval', (array)($audit['warnings'] ?? []))),
                'summary' => (array)($audit['summary'] ?? []),
            ];
        }

        $summary = [
            'requested_count' => count($adminIds),
            'existing_count' => count($adminIds) - count($missingAdminIds),
            'deletable_count' => count($deletableAdminIds),
            'blocked_count' => count($blockedAdminIds),
            'missing_count' => count($missingAdminIds),
            'recycle_admin_row_count' => $recycleAdminRowCount,
            'retained_admin_role_row_count' => $retainedRoleRowCount,
            'retained_admin_permission_row_count' => $retainedPermissionRowCount,
            'retained_admin_log_row_count' => $retainedAdminLogRowCount,
            'assigned_role_count' => $assignedRoleCount,
            'direct_permission_count' => $directPermissionCount,
            'active_token_count' => $activeTokenCount,
        ];

        $warnings = [];
        if ($summary['missing_count'] > 0) {
            $warnings[] = sprintf(
                '%d selected admin account(s) are already missing and must be reselected before batch recycle can continue.',
                $summary['missing_count']
            );
        }
        if ($summary['blocked_count'] > 0) {
            $warnings[] = sprintf(
                '%d selected admin account(s) are protected and block the whole batch until they are removed from the selection.',
                $summary['blocked_count']
            );
        }
        if ($summary['deletable_count'] > 0) {
            $warnings[] = '批量回收会在确认统一口令后，将所选管理员一并移入回收站。';
            $warnings[] = '已分配角色和直接权限会继续保留，恢复后会按原权限返回。';
            $warnings[] = '历史后台操作日志会继续保留，不会被删除。';
        }
        if ($summary['active_token_count'] > 0) {
            $warnings[] = sprintf(
                '%d selected admin account(s) still have stored login tokens that will be invalidated by the batch recycle.',
                $summary['active_token_count']
            );
        }

        return [
            'requested_admin_ids' => $adminIds,
            'deletable_admin_ids' => $deletableAdminIds,
            'blocked_admin_ids' => $blockedAdminIds,
            'missing_admin_ids' => $missingAdminIds,
            'confirmation_phrase' => $deletableAdminIds === []
                ? ''
                : $this->batchAdminDeleteConfirmationPhrase($deletableAdminIds),
            'can_delete_all' => $deletableAdminIds !== [] && $blockedAdminIds === [] && $missingAdminIds === [],
            'items' => $items,
            'summary' => $summary,
            'warnings' => $warnings,
        ];
    }

    private function deleteAdminRow(int $adminId): void
    {
        if (!$this->adminTableHasDeleteTime()) {
            throw new \RuntimeException('当前缺少管理员回收站所需字段，请先执行 admin_admin.delete_time 结构升级。');
        }

        $now = date('Y-m-d H:i:s');

        Db::table('admin_admin')
            ->where('id', $adminId)
            ->update([
                'delete_time' => $now,
                'token' => null,
                'update_time' => $now,
            ]);
    }

    private function restoreAdminRow(int $adminId): void
    {
        if (!$this->adminTableHasDeleteTime()) {
            throw new \RuntimeException('当前缺少管理员回收站所需字段，请先执行 admin_admin.delete_time 结构升级。');
        }

        $now = date('Y-m-d H:i:s');

        Db::table('admin_admin')
            ->where('id', $adminId)
            ->update([
                'delete_time' => null,
                'token' => null,
                'update_time' => $now,
            ]);
    }

    private function adminRestoreConflictReason(array $record): ?string
    {
        if (!$this->adminTableHasDeleteTime()) {
            return '当前缺少管理员回收站所需字段，请先执行 admin_admin.delete_time 结构升级。';
        }

        $adminId = (int)($record['id'] ?? 0);
        $username = trim((string)($record['username'] ?? ''));
        if ($adminId <= 0 || $username === '') {
            return null;
        }

        $query = Db::table('admin_admin')
            ->where('username', $username)
            ->where('id', '<>', $adminId);
        $this->applyAdminDeleteTimeFilter($query);

        if (!$query->exists()) {
            return null;
        }

        return sprintf(
            '当前已有启用中的管理员占用了账号 [%s]，请先处理重名冲突后再恢复。',
            $username
        );
    }

    private function adminLabel(array $record): string
    {
        $nickname = trim((string)($record['nickname'] ?? ''));
        $username = trim((string)($record['username'] ?? ''));

        if ($nickname !== '' && $username !== '') {
            return $nickname . ' / ' . $username;
        }

        if ($username !== '') {
            return $username;
        }

        return 'admin #' . (int)($record['id'] ?? 0);
    }

    private function adminDeleteConfirmationPhrase(int $adminId): string
    {
        return 'DELETE ADMIN ' . $adminId;
    }

    private function batchAdminDeleteConfirmationPhrase(array $adminIds): string
    {
        return sprintf(
            'DELETE ADMIN BATCH %d-%s',
            count($adminIds),
            strtoupper(substr(md5(implode(',', $adminIds)), 0, 6))
        );
    }

    private function isRootAdminId(int $adminId): bool
    {
        return $adminId === 1;
    }

    private function isSuperRoleRecord(array $record): bool
    {
        $roleId = (int)($record['id'] ?? 0);
        $name = trim((string)($record['name'] ?? ''));

        return $roleId === 1 || strcasecmp($name, 'super') === 0 || strcasecmp($name, 'super admin') === 0;
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'SystemAdmins', $authMark);
    }

    private function applyAdminDeleteTimeFilter(Builder $query): void
    {
        if ($this->adminTableHasDeleteTime()) {
            $query->whereNull('delete_time');
        }
    }

    private function adminTableHasDeleteTime(): bool
    {
        return DatabaseColumnInspector::hasColumn('admin_admin', 'delete_time');
    }

    private function recordAdminCreate(Request $request, array $record, array $roleIds): void
    {
        $adminId = $this->actorAdminId($request);
        if ($adminId <= 0) {
            return;
        }

        $createdAdminId = (int)($record['id'] ?? 0);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/admins/create',
            'desc' => sprintf(
                'admin create admin_id=%d label="%s" status=%d role_count=%d role_ids="%s"',
                $createdAdminId,
                $this->truncateLogText($this->adminLabel($record), 120),
                (int)($record['status'] ?? 0),
                count($roleIds),
                $this->truncateLogText(implode(',', $roleIds), 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminUpdate(Request $request, array $before, array $after, bool $passwordReset): void
    {
        $adminId = $this->actorAdminId($request);
        if ($adminId <= 0) {
            return;
        }

        $targetAdminId = (int)($after['id'] ?? 0);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/admins/' . $targetAdminId . '/update',
            'desc' => sprintf(
                'admin update admin_id=%d label="%s" username_changed=%d nickname_changed=%d password_reset=%d',
                $targetAdminId,
                $this->truncateLogText($this->adminLabel($after), 120),
                trim((string)($before['username'] ?? '')) === trim((string)($after['username'] ?? '')) ? 0 : 1,
                trim((string)($before['nickname'] ?? '')) === trim((string)($after['nickname'] ?? '')) ? 0 : 1,
                $passwordReset ? 1 : 0
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminStatus(Request $request, array $before, array $after): void
    {
        $adminId = $this->actorAdminId($request);
        if ($adminId <= 0) {
            return;
        }

        $targetAdminId = (int)($after['id'] ?? 0);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/admins/' . $targetAdminId . '/status',
            'desc' => sprintf(
                'admin status admin_id=%d label="%s" from=%d to=%d token_cleared=1',
                $targetAdminId,
                $this->truncateLogText($this->adminLabel($after), 120),
                (int)($before['status'] ?? 0),
                (int)($after['status'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminRoleSync(Request $request, array $record, array $beforeRoleIds, array $afterRoleIds): void
    {
        $adminId = $this->actorAdminId($request);
        if ($adminId <= 0) {
            return;
        }

        $targetAdminId = (int)($record['id'] ?? 0);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/admins/' . $targetAdminId . '/roles',
            'desc' => sprintf(
                'admin roles admin_id=%d label="%s" before="%s" after="%s" token_cleared=1',
                $targetAdminId,
                $this->truncateLogText($this->adminLabel($record), 120),
                $this->truncateLogText(implode(',', $beforeRoleIds), 255),
                $this->truncateLogText(implode(',', $afterRoleIds), 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminPermissionSync(
        Request $request,
        array $record,
        array $beforePermissionIds,
        array $afterPermissionIds
    ): void {
        $adminId = $this->actorAdminId($request);
        if ($adminId <= 0) {
            return;
        }

        $targetAdminId = (int)($record['id'] ?? 0);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/admins/' . $targetAdminId . '/permissions',
            'desc' => sprintf(
                'admin permissions admin_id=%d label="%s" before="%s" after="%s" token_cleared=1',
                $targetAdminId,
                $this->truncateLogText($this->adminLabel($record), 120),
                $this->truncateLogText(implode(',', $beforePermissionIds), 255),
                $this->truncateLogText(implode(',', $afterPermissionIds), 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminDelete(Request $request, array $audit): void
    {
        $adminId = $this->actorAdminId($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $targetAdminId = (int)($audit['admin_id'] ?? 0);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/admins/' . $targetAdminId . '/delete',
            'desc' => sprintf(
                'admin recycle admin_id=%d label="%s" recycle_admin_rows=%d retained_admin_role_rows=%d retained_admin_permission_rows=%d retained_admin_log_rows=%d role_count=%d direct_permissions=%d',
                $targetAdminId,
                $this->truncateLogText((string)($audit['admin_label'] ?? ''), 120),
                (int)($summary['recycle_admin_row_count'] ?? 0),
                (int)($summary['retained_admin_role_row_count'] ?? 0),
                (int)($summary['retained_admin_permission_row_count'] ?? 0),
                (int)($summary['retained_admin_log_row_count'] ?? 0),
                (int)($summary['assigned_role_count'] ?? 0),
                (int)($summary['direct_permission_count'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminBatchDelete(Request $request, array $audit): void
    {
        $adminId = $this->actorAdminId($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $deletedAdminIds = array_values(array_map('intval', (array)($audit['deletable_admin_ids'] ?? [])));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/admins/batch-delete',
            'desc' => sprintf(
                'admin batch recycle requested=%d recycled=%d blocked=%d missing=%d recycle_admin_rows=%d retained_admin_role_rows=%d retained_admin_permission_rows=%d retained_admin_log_rows=%d admin_ids="%s"',
                (int)($summary['requested_count'] ?? 0),
                (int)($summary['deletable_count'] ?? 0),
                (int)($summary['blocked_count'] ?? 0),
                (int)($summary['missing_count'] ?? 0),
                (int)($summary['recycle_admin_row_count'] ?? 0),
                (int)($summary['retained_admin_role_row_count'] ?? 0),
                (int)($summary['retained_admin_permission_row_count'] ?? 0),
                (int)($summary['retained_admin_log_row_count'] ?? 0),
                $this->truncateLogText(implode(',', $deletedAdminIds), 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminRestore(Request $request, array $record): void
    {
        $adminId = $this->actorAdminId($request);
        if ($adminId <= 0) {
            return;
        }

        $targetAdminId = (int)($record['id'] ?? 0);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/admins/' . $targetAdminId . '/restore',
            'desc' => sprintf(
                'admin restore admin_id=%d label="%s"',
                $targetAdminId,
                $this->truncateLogText($this->adminLabel($record), 120)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminBatchRestore(
        Request $request,
        array $restorableRows,
        array $requestedAdminIds,
        array $alreadyActiveAdminIds,
        array $missingAdminIds,
        array $blockedItems
    ): void {
        $adminId = $this->actorAdminId($request);
        if ($adminId <= 0) {
            return;
        }

        $restoredAdminIds = implode(',', array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $restorableRows
        ));
        $restoredLabels = implode(',', array_map(
            fn(array $row): string => $this->adminLabel($row),
            $restorableRows
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/admins/batch-restore',
            'desc' => sprintf(
                'admin batch restore requested=%d restored=%d active=%d missing=%d blocked=%d admin_ids="%s" labels="%s"',
                count($requestedAdminIds),
                count($restorableRows),
                count($alreadyActiveAdminIds),
                count($missingAdminIds),
                count($blockedItems),
                $this->truncateLogText($restoredAdminIds, 255),
                $this->truncateLogText($restoredLabels, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

}
