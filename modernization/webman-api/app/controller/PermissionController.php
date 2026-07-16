<?php

namespace app\controller;

use app\support\AdminPermissionFormatter;
use app\support\AdminRouteAuthorization;
use app\support\ApiResponse;
use app\support\RequestPayload;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class PermissionController
{
    private const STATUS_ENABLED = 1;
    private const STATUS_DISABLED = 2;

    /**
     * @var array<int, string>
     */
    private const PROTECTED_MENU_PATHS = [
        '/admin.permission/index',
        '/admin.permission/add',
        '/admin.permission/edit',
        '/admin.permission/status',
        '/admin.permission/remove',
    ];

    public function index(Request $request): Response
    {
        $query = Db::table('admin_permission')
            ->select('id', 'pid', 'title', 'href', 'icon', 'sort', 'type', 'status');

        $this->applyFilters($query, $request);

        $rows = $query
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->toArray();

        $records = array_map(
            static fn ($row): array => AdminPermissionFormatter::format((array)$row),
            $rows
        );

        $tree = $this->buildTree($rows);

        return ApiResponse::success([
            'records' => $records,
            'tree' => $tree,
            'summary' => $this->summary($records, $tree),
        ]);
    }

    public function show(Request $request): Response
    {
        $id = $this->permissionIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少菜单编号', 422, null, 422);
        }

        $record = $this->permissionRecord($id);
        if ($record === null) {
            return ApiResponse::error('菜单不存在', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminPermissionFormatter::format($record),
            'children' => $this->children($id),
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

        $permissionId = (int)Db::table('admin_permission')->insertGetId([
            'pid' => $payload['parent_id'],
            'title' => $payload['title'],
            'href' => $payload['path'],
            'icon' => $payload['icon'],
            'sort' => $payload['sort'],
            'type' => $payload['type'],
            'status' => $payload['status'],
        ]);

        $created = $this->permissionRecord($permissionId);
        if ($created === null) {
            return ApiResponse::error('菜单已创建，但重新加载失败', 500, null, 500);
        }

        $this->recordAdminPermissionCreate($request, $created);

        return ApiResponse::success([
            'item' => AdminPermissionFormatter::format($created),
            'created_permission_id' => $permissionId,
            'created_permission_label' => $this->permissionLabel($created),
        ], '菜单已创建');
    }

    public function update(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->permissionIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少菜单编号', 422, null, 422);
        }

        $current = $this->permissionRecord($id);
        if ($current === null) {
            return ApiResponse::error('菜单不存在', 404, null, 404);
        }

        try {
            $payload = $this->normalizeWritePayload(RequestPayload::all($request), $current);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $protectionReasons = $this->protectedMutationReasons($current, $payload);
        if ($protectionReasons !== []) {
            return ApiResponse::error(
                '当前菜单属于系统内置授权节点，部分字段不可修改',
                422,
                ['blocking_reasons' => $protectionReasons],
                422
            );
        }

        Db::table('admin_permission')
            ->where('id', $id)
            ->update([
                'pid' => $payload['parent_id'],
                'title' => $payload['title'],
                'href' => $payload['path'],
                'icon' => $payload['icon'],
                'sort' => $payload['sort'],
                'type' => $payload['type'],
                'status' => $payload['status'],
            ]);

        $updated = $this->permissionRecord($id);
        if ($updated === null) {
            return ApiResponse::error('菜单已更新，但重新加载失败', 500, null, 500);
        }

        $this->recordAdminPermissionUpdate($request, $current, $updated);

        return ApiResponse::success([
            'item' => AdminPermissionFormatter::format($updated),
            'updated_permission_id' => $id,
            'updated_permission_label' => $this->permissionLabel($updated),
        ], '菜单已更新');
    }

    public function status(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'status');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->permissionIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少菜单编号', 422, null, 422);
        }

        $record = $this->permissionRecord($id);
        if ($record === null) {
            return ApiResponse::error('菜单不存在', 404, null, 404);
        }

        try {
            $status = $this->normalizeEnum(
                RequestPayload::all($request)['status'] ?? null,
                [self::STATUS_ENABLED, self::STATUS_DISABLED],
                'status'
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $protectionReasons = $this->protectedStatusReasons($record, $status);
        if ($protectionReasons !== []) {
            return ApiResponse::error(
                '当前菜单属于系统内置授权节点，状态不可修改',
                422,
                ['blocking_reasons' => $protectionReasons],
                422
            );
        }

        if ((int)($record['status'] ?? 0) !== $status) {
            Db::table('admin_permission')
                ->where('id', $id)
                ->update(['status' => $status]);
        }

        $updated = $this->permissionRecord($id);
        if ($updated === null) {
            return ApiResponse::error('菜单状态已更新，但重新加载失败', 500, null, 500);
        }

        $this->recordAdminPermissionStatus($request, $record, $updated);

        return ApiResponse::success([
            'item' => AdminPermissionFormatter::format($updated),
            'updated_permission_id' => $id,
            'updated_permission_label' => $this->permissionLabel($updated),
        ], '菜单状态已更新');
    }

    public function reorder(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'sort');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $payload = $this->normalizeReorderPayload(RequestPayload::all($request));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $beforeRows = $this->permissionRowsByIds($payload['permission_ids']);
        Db::transaction(function () use ($payload): void {
            foreach ($payload['permission_ids'] as $sort => $permissionId) {
                Db::table('admin_permission')
                    ->where('id', $permissionId)
                    ->update(['sort' => $sort]);
            }
        });
        $afterRows = $this->permissionRowsByIds($payload['permission_ids']);

        $this->recordAdminPermissionReorder($request, $payload['parent_id'], $beforeRows, $afterRows);

        return ApiResponse::success([
            'parent_id' => $payload['parent_id'],
            'ordered_permission_ids' => $payload['permission_ids'],
            'items' => array_map(
                static fn (array $row): array => AdminPermissionFormatter::format($row),
                array_values($afterRows)
            ),
        ], '菜单排序已更新');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->permissionIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少菜单编号', 422, null, 422);
        }

        $record = $this->permissionRecord($id);
        if ($record === null) {
            return ApiResponse::error('菜单不存在', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminPermissionFormatter::format($record),
            'audit' => $this->buildPermissionDeleteAudit($record),
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->permissionIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少菜单编号', 422, null, 422);
        }

        $record = $this->permissionRecord($id);
        if ($record === null) {
            return ApiResponse::error('菜单不存在', 404, null, 404);
        }

        $audit = $this->buildPermissionDeleteAudit($record);
        if (empty($audit['can_delete'])) {
            return ApiResponse::error(
                '当前菜单仍有关联限制，暂时不能删除',
                422,
                ['audit' => $audit],
                422
            );
        }

        $payload = RequestPayload::all($request);
        $cascadeChildren = !empty($payload['cascade_children']);
        if (!empty($audit['requires_cascade']) && !$cascadeChildren) {
            return ApiResponse::error(
                '当前菜单包含子节点，请确认连同子节点一起删除',
                422,
                ['audit' => $audit],
                422
            );
        }

        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('确认短语不正确', 422, ['audit' => $audit], 422);
        }

        $affectedPermissionIds = array_values(array_map('intval', (array)($audit['affected_permission_ids'] ?? [])));
        Db::transaction(function () use ($affectedPermissionIds): void {
            $this->deletePermissionRows($affectedPermissionIds);
        });

        $this->recordAdminPermissionDelete($request, $audit);

        return ApiResponse::success([
            'deleted_permission_id' => $id,
            'deleted_permission_label' => (string)($audit['permission_label'] ?? ''),
            'deleted_permission_ids' => $affectedPermissionIds,
            'audit' => $audit,
        ], '菜单已删除');
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('title', 'like', '%' . $keyword . '%')
                    ->orWhere('href', 'like', '%' . $keyword . '%')
                    ->orWhere('icon', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder
                        ->orWhere('id', (int)$keyword)
                        ->orWhere('pid', (int)$keyword);
                }
            });
        }

        $status = $request->get('status');
        if ($status !== null && $status !== '') {
            $query->where('status', (int)$status);
        }

        $type = $request->get('type');
        if ($type !== null && $type !== '') {
            $query->where('type', (int)$type);
        }
    }

    private function permissionIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function permissionRecord(int $id): ?array
    {
        $row = Db::table('admin_permission')
            ->select('id', 'pid', 'title', 'href', 'icon', 'sort', 'type', 'status')
            ->where('id', $id)
            ->first();

        return $row ? (array)$row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function allPermissionRecords(): array
    {
        return array_map(
            static fn ($row): array => (array)$row,
            Db::table('admin_permission')
                ->select('id', 'pid', 'title', 'href', 'icon', 'sort', 'type', 'status')
                ->orderBy('sort')
                ->orderBy('id')
                ->get()
                ->toArray()
        );
    }

    private function buildTree(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $id = (int)($record['id'] ?? 0);
            $items[$id] = AdminPermissionFormatter::format($record);
        }

        $tree = [];
        foreach ($items as $id => &$item) {
            $parentId = (int)($item['parent_id'] ?? 0);
            if ($parentId > 0 && isset($items[$parentId])) {
                $item['depth'] = (int)($items[$parentId]['depth'] ?? 0) + 1;
                $items[$parentId]['children'][] = &$item;
                continue;
            }

            $tree[] = &$item;
        }
        unset($item);

        $this->refreshDepth($tree);

        return $tree;
    }

    private function refreshDepth(array &$nodes, int $depth = 0): void
    {
        foreach ($nodes as &$node) {
            $node['depth'] = $depth;
            if (!empty($node['children'])) {
                $this->refreshDepth($node['children'], $depth + 1);
            }
        }
        unset($node);
    }

    private function children(int $id): array
    {
        $rows = Db::table('admin_permission')
            ->select('id', 'pid', 'title', 'href', 'icon', 'sort', 'type', 'status')
            ->where('pid', $id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->toArray();

        return array_map(
            static fn ($row): array => AdminPermissionFormatter::format((array)$row),
            $rows
        );
    }

    private function summary(array $records, array $tree): array
    {
        $ids = [];
        $enabled = 0;
        $disabled = 0;
        $root = 0;
        $orphan = 0;
        $directory = 0;
        $permission = 0;
        $writeEnabled = 0;
        $readOnly = 0;
        $pendingWrite = 0;
        $groupSplit = 0;
        $legacyOnly = 0;
        $unmapped = 0;

        foreach ($records as $record) {
            $ids[(int)$record['id']] = true;
        }

        foreach ($records as $record) {
            $parentId = (int)$record['parent_id'];
            if ((int)$record['status'] === self::STATUS_ENABLED) {
                $enabled++;
            } else {
                $disabled++;
            }

            if ($parentId === 0) {
                $root++;
            } elseif (!isset($ids[$parentId])) {
                $orphan++;
            }

            if ((int)$record['type'] === 0) {
                $directory++;
            }

            if ((int)$record['type'] === 1) {
                $permission++;
            }

            $migrationStatus = (string)($record['migration_status'] ?? 'unmapped');
            match ($migrationStatus) {
                'write_enabled' => $writeEnabled++,
                'read_only' => $readOnly++,
                'pending_write' => $pendingWrite++,
                'group_split' => $groupSplit++,
                'legacy_only' => $legacyOnly++,
                default => $unmapped++,
            };
        }

        return [
            'total' => count($records),
            'enabled_count' => $enabled,
            'disabled_count' => $disabled,
            'root_count' => $root,
            'tree_root_count' => count($tree),
            'orphan_count' => $orphan,
            'directory_count' => $directory,
            'permission_count' => $permission,
            'write_enabled_count' => $writeEnabled,
            'read_only_count' => $readOnly,
            'pending_write_count' => $pendingWrite,
            'group_split_count' => $groupSplit,
            'legacy_only_count' => $legacyOnly,
            'unmapped_count' => $unmapped,
        ];
    }

    private function normalizeWritePayload(array $payload, ?array $current = null): array
    {
        $currentId = (int)($current['id'] ?? 0);
        $currentParentId = (int)($current['pid'] ?? 0);
        $currentStatus = (int)($current['status'] ?? self::STATUS_ENABLED);
        $currentType = (int)($current['type'] ?? 1);
        $currentSort = (int)($current['sort'] ?? 99);

        $parentId = $this->normalizeParentId($payload['parent_id'] ?? $payload['pid'] ?? $currentParentId);
        $type = $this->normalizeEnum($payload['type'] ?? $currentType, [0, 1], 'type');
        $status = $this->normalizeEnum(
            $payload['status'] ?? $currentStatus,
            [self::STATUS_ENABLED, self::STATUS_DISABLED],
            'status'
        );
        $sort = $this->normalizeIntRange($payload['sort'] ?? $currentSort, 0, 127, 'sort');
        $title = $this->normalizeRequiredString(
            $payload['title'] ?? ($current['title'] ?? null),
            50,
            'title'
        );
        $icon = $this->normalizeNullableString(
            $payload['icon'] ?? ($current['icon'] ?? null),
            50,
            'icon'
        ) ?? '';
        $path = $this->normalizePermissionPath(
            $payload['path'] ?? $payload['href'] ?? ($current['href'] ?? null),
            $type
        );

        if ($parentId > 0 && $this->permissionRecord($parentId) === null) {
            throw new \InvalidArgumentException('上级菜单不存在');
        }

        if ($currentId > 0) {
            if ($parentId === $currentId) {
                throw new \InvalidArgumentException('上级菜单不能选择当前节点');
            }

            $descendantIds = $this->descendantPermissionIds($currentId);
            if (in_array($parentId, $descendantIds, true)) {
                throw new \InvalidArgumentException('上级菜单不能移动到自己的子节点下');
            }
        }

        if ($path !== '' && $this->permissionPathExists($path, $currentId > 0 ? $currentId : null)) {
            throw new \InvalidArgumentException('菜单路径已存在');
        }

        return [
            'parent_id' => $parentId,
            'title' => $title,
            'path' => $path,
            'icon' => $icon,
            'sort' => $sort,
            'type' => $type,
            'status' => $status,
        ];
    }

    /**
     * @return array{parent_id: int, permission_ids: array<int, int>}
     */
    private function normalizeReorderPayload(array $payload): array
    {
        $parentId = $this->normalizeParentId($payload['parent_id'] ?? $payload['pid'] ?? 0);
        if ($parentId > 0 && $this->permissionRecord($parentId) === null) {
            throw new \InvalidArgumentException('上级菜单不存在');
        }

        $rawIds = $payload['permission_ids'] ?? $payload['ordered_permission_ids'] ?? $payload['ids'] ?? null;
        if (is_string($rawIds)) {
            $rawIds = preg_split('/\s*,\s*/', trim($rawIds), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        if (!is_array($rawIds)) {
            throw new \InvalidArgumentException('排序列表格式不正确');
        }

        $permissionIds = [];
        foreach ($rawIds as $rawId) {
            if (is_array($rawId) || is_object($rawId) || !preg_match('/^[1-9]\d*$/', trim((string)$rawId))) {
                throw new \InvalidArgumentException('排序列表里只能包含有效的菜单编号');
            }

            $permissionIds[] = (int)$rawId;
        }

        if (count($permissionIds) < 2) {
            throw new \InvalidArgumentException('至少需要两个同级菜单才能调整排序');
        }

        if (count($permissionIds) !== count(array_unique($permissionIds))) {
            throw new \InvalidArgumentException('排序列表中存在重复的菜单编号');
        }

        if (count($permissionIds) > 128) {
            throw new \InvalidArgumentException('同级菜单数量过多，暂不支持本次排序');
        }

        $siblingIds = $this->siblingPermissionIds($parentId);
        if (!$this->sameIntegerSet($siblingIds, $permissionIds)) {
            throw new \InvalidArgumentException('排序时必须提交当前上级菜单下的全部同级节点');
        }

        return [
            'parent_id' => $parentId,
            'permission_ids' => array_values($permissionIds),
        ];
    }

    private function normalizeParentId(mixed $value): int
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('上级菜单编号格式不正确');
        }

        $parentId = (int)$value;
        if ($parentId < 0) {
            throw new \InvalidArgumentException('上级菜单编号必须为非负整数');
        }

        return $parentId;
    }

    private function normalizePermissionPath(mixed $value, int $type): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('菜单路径格式不正确');
        }

        $normalized = trim((string)$value);
        if ($type === 0) {
            return '';
        }

        if ($normalized === '') {
            throw new \InvalidArgumentException('菜单节点必须填写访问路径');
        }

        if (preg_match('/\s/', $normalized)) {
            throw new \InvalidArgumentException('菜单路径不能包含空格');
        }

        $normalized = '/' . ltrim($normalized, '/');
        if (mb_strlen($normalized) > 50) {
            throw new \InvalidArgumentException('菜单路径过长');
        }

        return $normalized;
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

    /**
     * @param array<int, int> $allowed
     */
    private function normalizeEnum(mixed $value, array $allowed, string $field): int
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '格式不正确');
        }

        $normalized = (int)$value;
        if (!in_array($normalized, $allowed, true)) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '取值无效');
        }

        return $normalized;
    }

    private function normalizeIntRange(mixed $value, int $min, int $max, string $field): int
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '格式不正确');
        }

        if (!preg_match('/^-?\d+$/', trim((string)$value))) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '必须为整数');
        }

        $normalized = (int)$value;
        if ($normalized < $min || $normalized > $max) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '超出允许范围');
        }

        return $normalized;
    }

    private function permissionPathExists(string $path, ?int $ignoreId = null): bool
    {
        $query = Db::table('admin_permission')->where('href', $path);

        if ($ignoreId !== null && $ignoreId > 0) {
            $query->where('id', '<>', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * @return array<int, int>
     */
    private function siblingPermissionIds(int $parentId): array
    {
        return array_values(array_map(
            static fn ($id): int => (int)$id,
            Db::table('admin_permission')
                ->where('pid', $parentId)
                ->orderBy('sort')
                ->orderBy('id')
                ->pluck('id')
                ->toArray()
        ));
    }

    /**
     * @param array<int, int> $left
     * @param array<int, int> $right
     */
    private function sameIntegerSet(array $left, array $right): bool
    {
        sort($left);
        sort($right);

        return array_values($left) === array_values($right);
    }

    /**
     * @param array<int, int> $permissionIds
     * @return array<int, array<string, mixed>>
     */
    private function permissionRowsByIds(array $permissionIds): array
    {
        if ($permissionIds === []) {
            return [];
        }

        $rows = array_map(
            static fn ($row): array => (array)$row,
            Db::table('admin_permission')
                ->select('id', 'pid', 'title', 'href', 'icon', 'sort', 'type', 'status')
                ->whereIn('id', $permissionIds)
                ->get()
                ->toArray()
        );

        $rowsById = [];
        foreach ($rows as $row) {
            $rowsById[(int)($row['id'] ?? 0)] = $row;
        }

        $ordered = [];
        foreach ($permissionIds as $permissionId) {
            if (isset($rowsById[$permissionId])) {
                $ordered[$permissionId] = $rowsById[$permissionId];
            }
        }

        return $ordered;
    }

    /**
     * @return array<int, int>
     */
    private function descendantPermissionIds(int $permissionId): array
    {
        if ($permissionId <= 0) {
            return [];
        }

        $rows = $this->allPermissionRecords();
        $childrenByParentId = [];
        foreach ($rows as $row) {
            $childrenByParentId[(int)($row['pid'] ?? 0)][] = (int)($row['id'] ?? 0);
        }

        $descendantIds = [];
        $stack = $childrenByParentId[$permissionId] ?? [];
        while ($stack !== []) {
            $currentId = (int)array_pop($stack);
            if ($currentId <= 0 || isset($descendantIds[$currentId])) {
                continue;
            }

            $descendantIds[$currentId] = $currentId;
            foreach ($childrenByParentId[$currentId] ?? [] as $childId) {
                $stack[] = (int)$childId;
            }
        }

        return array_values($descendantIds);
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'title' => '菜单名称',
            'icon' => '菜单图标',
            'sort' => '排序值',
            'status' => '状态',
            'type' => '菜单类型',
            'path' => '菜单路径',
            default => $field,
        };
    }

    private function protectedStatusReasons(array $record, int $nextStatus): array
    {
        if ((int)($record['status'] ?? 0) === $nextStatus) {
            return [];
        }

        $path = trim((string)($record['href'] ?? ''));
        if (!in_array($path, self::PROTECTED_MENU_PATHS, true)) {
            return [];
        }

        return [
            '当前菜单为系统内置授权节点，必须保持启用。',
        ];
    }

    private function protectedMutationReasons(array $current, array $payload): array
    {
        $path = trim((string)($current['href'] ?? ''));
        if (!in_array($path, self::PROTECTED_MENU_PATHS, true)) {
            return [];
        }

        $reasons = [];
        if (($payload['path'] ?? '') !== $path) {
            $reasons[] = '当前菜单为系统内置授权节点，访问路径不可修改。';
        }

        if ((int)($payload['parent_id'] ?? 0) !== (int)($current['pid'] ?? 0)) {
            $reasons[] = '当前菜单为系统内置授权节点，上级关系不可修改。';
        }

        if ((int)($payload['type'] ?? 0) !== (int)($current['type'] ?? 0)) {
            $reasons[] = '当前菜单为系统内置授权节点，类型不可修改。';
        }

        if ((int)($payload['status'] ?? 0) !== (int)($current['status'] ?? 0)) {
            $reasons[] = '当前菜单为系统内置授权节点，必须保持启用。';
        }

        return array_values(array_unique($reasons));
    }

    private function buildPermissionDeleteAudit(array $record): array
    {
        $permissionId = (int)($record['id'] ?? 0);
        $allRows = $this->allPermissionRecords();
        $rowsById = [];
        $childrenByParentId = [];
        foreach ($allRows as $row) {
            $rowId = (int)($row['id'] ?? 0);
            $rowsById[$rowId] = $row;
            $childrenByParentId[(int)($row['pid'] ?? 0)][] = $rowId;
        }

        $affectedIds = [$permissionId];
        $stack = $childrenByParentId[$permissionId] ?? [];
        while ($stack !== []) {
            $currentId = (int)array_pop($stack);
            if ($currentId <= 0 || in_array($currentId, $affectedIds, true)) {
                continue;
            }

            $affectedIds[] = $currentId;
            foreach ($childrenByParentId[$currentId] ?? [] as $childId) {
                $stack[] = (int)$childId;
            }
        }

        $directChildIds = array_values(array_map(
            'intval',
            $childrenByParentId[$permissionId] ?? []
        ));
        sort($affectedIds);
        sort($directChildIds);

        $affectedRows = array_values(array_filter(
            array_map(
                static fn (int $rowId): ?array => $rowsById[$rowId] ?? null,
                $affectedIds
            )
        ));

        $protectedPaths = $this->protectedPathsInRows($affectedRows);
        $deletePermissionRowCount = count($affectedIds);
        $deleteRolePermissionRowCount = $this->relationCount('admin_role_permission', $affectedIds);
        $deleteAdminPermissionRowCount = $this->relationCount('admin_admin_permission', $affectedIds);
        $requiresCascade = count($affectedIds) > 1;

        $blockingReasons = [];
        if ($protectedPaths !== []) {
            $blockingReasons[] = sprintf(
                '删除后会影响系统内置授权节点：%s',
                implode(', ', $protectedPaths)
            );
        }

        $warnings = [];
        if ($requiresCascade) {
            $warnings[] = sprintf(
                '本次会连同 %d 个子级菜单一起清理。',
                max(0, $deletePermissionRowCount - 1)
            );
        }

        if ($deleteRolePermissionRowCount > 0) {
            $warnings[] = sprintf(
                '将同步清理 %d 条角色授权关联记录。',
                $deleteRolePermissionRowCount
            );
        }

        if ($deleteAdminPermissionRowCount > 0) {
            $warnings[] = sprintf(
                '将同步清理 %d 条管理员直连授权记录。',
                $deleteAdminPermissionRowCount
            );
        }

        if ($warnings === []) {
            $warnings[] = '删除后不会进入回收站，请确认后再继续。';
        }

        return [
            'permission_id' => $permissionId,
            'permission_label' => $this->permissionLabel($record),
            'path' => trim((string)($record['href'] ?? '')),
            'requires_cascade' => $requiresCascade,
            'can_delete' => $blockingReasons === [],
            'confirmation_phrase' => $this->permissionDeleteConfirmationPhrase($permissionId, $requiresCascade),
            'affected_permission_ids' => $affectedIds,
            'direct_child_ids' => $directChildIds,
            'blocking_reasons' => $blockingReasons,
            'warnings' => $warnings,
            'summary' => [
                'delete_permission_row_count' => $deletePermissionRowCount,
                'delete_role_permission_row_count' => $deleteRolePermissionRowCount,
                'delete_admin_permission_row_count' => $deleteAdminPermissionRowCount,
                'direct_child_count' => count($directChildIds),
                'descendant_count' => max(0, $deletePermissionRowCount - 1),
                'protected_row_count' => count($protectedPaths),
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function protectedPathsInRows(array $rows): array
    {
        $paths = [];
        foreach ($rows as $row) {
            $path = trim((string)($row['href'] ?? ''));
            if ($path === '' || !in_array($path, self::PROTECTED_MENU_PATHS, true)) {
                continue;
            }

            $paths[$path] = $path;
        }

        ksort($paths);

        return array_values($paths);
    }

    /**
     * @param array<int, int> $permissionIds
     */
    private function relationCount(string $table, array $permissionIds): int
    {
        if ($permissionIds === []) {
            return 0;
        }

        return (int)Db::table($table)
            ->whereIn('permission_id', $permissionIds)
            ->count();
    }

    /**
     * @param array<int, int> $permissionIds
     */
    private function deletePermissionRows(array $permissionIds): void
    {
        if ($permissionIds === []) {
            return;
        }

        Db::table('admin_role_permission')
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        Db::table('admin_admin_permission')
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        Db::table('admin_permission')
            ->whereIn('id', $permissionIds)
            ->delete();
    }

    private function permissionLabel(array $record): string
    {
        $title = trim((string)($record['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $path = trim((string)($record['href'] ?? ''));
        if ($path !== '') {
            return $path;
        }

        return '菜单 #' . (int)($record['id'] ?? 0);
    }

    private function permissionDeleteConfirmationPhrase(int $permissionId, bool $requiresCascade): string
    {
        return $requiresCascade
            ? 'DELETE MENU TREE ' . $permissionId
            : 'DELETE MENU ' . $permissionId;
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'SystemMenu', $authMark, false);
    }

    private function adminIdFromRequest(Request $request): int
    {
        return (int)(((array)($request->admin ?? []))['id'] ?? 0);
    }

    private function recordAdminPermissionCreate(Request $request, array $record): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $permissionId = (int)($record['id'] ?? 0);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/permissions/create',
            'desc' => sprintf(
                'permission create permission_id=%d title="%s" parent_id=%d path="%s" type=%d status=%d',
                $permissionId,
                $this->truncateLogText($this->permissionLabel($record), 120),
                (int)($record['pid'] ?? 0),
                $this->truncateLogText((string)($record['href'] ?? ''), 120),
                (int)($record['type'] ?? 0),
                (int)($record['status'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminPermissionUpdate(Request $request, array $before, array $after): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $permissionId = (int)($after['id'] ?? 0);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/permissions/' . $permissionId . '/update',
            'desc' => sprintf(
                'permission update permission_id=%d title="%s" title_changed=%d parent_changed=%d path_changed=%d type_changed=%d status_changed=%d',
                $permissionId,
                $this->truncateLogText($this->permissionLabel($after), 120),
                trim((string)($before['title'] ?? '')) === trim((string)($after['title'] ?? '')) ? 0 : 1,
                (int)($before['pid'] ?? 0) === (int)($after['pid'] ?? 0) ? 0 : 1,
                trim((string)($before['href'] ?? '')) === trim((string)($after['href'] ?? '')) ? 0 : 1,
                (int)($before['type'] ?? 0) === (int)($after['type'] ?? 0) ? 0 : 1,
                (int)($before['status'] ?? 0) === (int)($after['status'] ?? 0) ? 0 : 1
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminPermissionStatus(Request $request, array $before, array $after): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $permissionId = (int)($after['id'] ?? 0);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/permissions/' . $permissionId . '/status',
            'desc' => sprintf(
                'permission status permission_id=%d title="%s" before=%d after=%d',
                $permissionId,
                $this->truncateLogText($this->permissionLabel($after), 120),
                (int)($before['status'] ?? 0),
                (int)($after['status'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminPermissionDelete(Request $request, array $audit): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $permissionId = (int)($audit['permission_id'] ?? 0);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/permissions/' . $permissionId . '/delete',
            'desc' => sprintf(
                'permission delete permission_id=%d title="%s" delete_permission_rows=%d delete_role_permission_rows=%d delete_admin_permission_rows=%d descendant_count=%d cascade=%d',
                $permissionId,
                $this->truncateLogText((string)($audit['permission_label'] ?? ''), 120),
                (int)($summary['delete_permission_row_count'] ?? 0),
                (int)($summary['delete_role_permission_row_count'] ?? 0),
                (int)($summary['delete_admin_permission_row_count'] ?? 0),
                (int)($summary['descendant_count'] ?? 0),
                !empty($audit['requires_cascade']) ? 1 : 0
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $beforeRows
     * @param array<int, array<string, mixed>> $afterRows
     */
    private function recordAdminPermissionReorder(Request $request, int $parentId, array $beforeRows, array $afterRows): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $orderedIds = array_keys($afterRows);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/permissions/reorder',
            'desc' => sprintf(
                'permission reorder parent_id=%d count=%d before="%s" after="%s"',
                $parentId,
                count($orderedIds),
                $this->truncateLogText($this->orderLogText($beforeRows), 120),
                $this->truncateLogText($this->orderLogText($afterRows), 120)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function orderLogText(array $rows): string
    {
        $parts = [];
        foreach ($rows as $row) {
            $parts[] = (int)($row['id'] ?? 0) . ':' . (int)($row['sort'] ?? 0);
        }

        return implode(',', $parts);
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
