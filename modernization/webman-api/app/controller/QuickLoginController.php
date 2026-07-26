<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\controller;

use app\support\AdminRouteAuthorization;
use app\support\AdminQuickLoginFormatter;
use app\support\ApiResponse;
use app\support\BusinessTable;
use app\support\RequestPayload;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class QuickLoginController
{
    private const BINDING_CONFIGS = [
        'qq_login' => 'QQ Login Binding',
        'wechat_login' => 'WeChat Login Binding',
    ];

    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $query = $this->quickLoginQuery();
        $this->applyFilters($query, $request);

        $summary = $this->summary(clone $query);
        $total = (int)(clone $query)->count('id');
        $rows = array_map(
            static fn($row): array => (array)$row,
            $query
                ->orderByDesc('id')
                ->offset(($current - 1) * $size)
                ->limit($size)
                ->get()
                ->toArray()
        );

        return ApiResponse::success([
            'records' => $this->formatQuickLoginRows($rows),
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => $summary,
        ]);
    }

    public function show(Request $request): Response
    {
        $id = $this->quickLoginIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('quick login id is required', 422, null, 422);
        }

        $row = $this->loadQuickLoginRow($id);
        if ($row === null) {
            return ApiResponse::error('quick login config not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminQuickLoginFormatter::format($row),
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

        $quickLoginId = (int)Db::table(BusinessTable::quickLogin())->insertGetId([
            'type' => $payload['type'],
            'status' => $payload['status'],
            'name' => $payload['name'],
            'url' => $payload['url'],
            'appid' => $payload['appid'],
            'appkey' => $payload['appkey'],
            'create_time' => date('Y-m-d H:i:s'),
        ]);

        $created = $this->loadQuickLoginRow($quickLoginId);
        if ($created === null) {
            return ApiResponse::error('created quick login config could not be loaded', 500, null, 500);
        }

        $this->recordAdminQuickLoginCreate($request, $created);

        return ApiResponse::success([
            'item' => AdminQuickLoginFormatter::format($created),
            'created_quick_login_id' => $quickLoginId,
            'created_quick_login_label' => $this->quickLoginLabel($created),
        ], 'quick login config created');
    }

    public function update(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->quickLoginIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('quick login id is required', 422, null, 422);
        }

        $record = $this->loadQuickLoginRow($id);
        if ($record === null) {
            return ApiResponse::error('quick login config not found', 404, null, 404);
        }

        try {
            $payload = $this->normalizeWritePayload(RequestPayload::all($request), $record);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table(BusinessTable::quickLogin())
            ->where('id', $id)
            ->update([
                'type' => $payload['type'],
                'status' => $payload['status'],
                'name' => $payload['name'],
                'url' => $payload['url'],
                'appid' => $payload['appid'],
                'appkey' => $payload['appkey'],
            ]);

        $updated = $this->loadQuickLoginRow($id);
        if ($updated === null) {
            return ApiResponse::error('updated quick login config could not be loaded', 500, null, 500);
        }

        $this->recordAdminQuickLoginUpdate($request, $record, $updated);

        return ApiResponse::success([
            'item' => AdminQuickLoginFormatter::format($updated),
            'updated_quick_login_id' => $id,
            'updated_quick_login_label' => $this->quickLoginLabel($updated),
        ], 'quick login config updated');
    }

    public function status(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'status');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->quickLoginIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('quick login id is required', 422, null, 422);
        }

        $record = $this->loadQuickLoginRow($id);
        if ($record === null) {
            return ApiResponse::error('quick login config not found', 404, null, 404);
        }

        try {
            $status = $this->normalizeStatus(RequestPayload::all($request)['status'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table(BusinessTable::quickLogin())
            ->where('id', $id)
            ->update([
                'status' => $status,
            ]);

        $updated = $this->loadQuickLoginRow($id);
        if ($updated === null) {
            return ApiResponse::error('updated quick login config could not be loaded', 500, null, 500);
        }

        $this->recordAdminQuickLoginStatus($request, $record, $status);

        return ApiResponse::success([
            'item' => AdminQuickLoginFormatter::format($updated),
            'updated_quick_login_id' => $id,
            'updated_quick_login_label' => $this->quickLoginLabel($updated),
            'status' => $status,
            'status_label' => (string)(AdminQuickLoginFormatter::format($updated)['status_label'] ?? ''),
        ], 'quick login status updated');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->quickLoginIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('quick login id is required', 422, null, 422);
        }

        $record = $this->loadQuickLoginRow($id);
        if ($record === null) {
            return ApiResponse::error('quick login config not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminQuickLoginFormatter::format($record),
            'audit' => $this->buildQuickLoginDeleteAudit($record),
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->quickLoginIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('quick login id is required', 422, null, 422);
        }

        $record = $this->loadQuickLoginRow($id);
        if ($record === null) {
            return ApiResponse::error('quick login config not found', 404, null, 404);
        }

        $audit = $this->buildQuickLoginDeleteAudit($record);
        if (empty($audit['can_delete'])) {
            return ApiResponse::error(
                'quick login config cannot be deleted until every binding is removed',
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
            $this->deleteQuickLoginRow($id);
        });

        $this->recordAdminQuickLoginDelete($request, $audit);

        return ApiResponse::success([
            'deleted_quick_login_id' => $id,
            'deleted_quick_login_label' => (string)($audit['quick_login_label'] ?? ''),
            'audit' => $audit,
        ], 'quick login config deleted');
    }

    public function batchDeleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $quickLoginIds = $this->normalizeQuickLoginIds(
                RequestPayload::all($request)['quick_login_ids']
                    ?? RequestPayload::all($request)['ids']
                    ?? []
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success([
            'audit' => $this->batchQuickLoginDeleteAudit($quickLoginIds),
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
            $quickLoginIds = $this->normalizeQuickLoginIds(
                $payload['quick_login_ids'] ?? $payload['ids'] ?? []
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $audit = $this->batchQuickLoginDeleteAudit($quickLoginIds);
        if (empty($audit['can_delete_all'])) {
            return ApiResponse::error(
                'selected quick login configs cannot be batch deleted until every binding is removed',
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
            foreach ((array)($audit['deletable_quick_login_ids'] ?? []) as $quickLoginId) {
                $this->deleteQuickLoginRow((int)$quickLoginId);
            }
        });

        $this->recordAdminQuickLoginBatchDelete($request, $audit);

        return ApiResponse::success([
            'deleted_quick_login_ids' => array_values(array_map(
                'intval',
                (array)($audit['deletable_quick_login_ids'] ?? [])
            )),
            'deleted_count' => (int)(($audit['summary'] ?? [])['deletable_count'] ?? 0),
            'audit' => $audit,
        ], 'quick login batch delete completed');
    }

    private function quickLoginQuery(): Builder
    {
        return Db::table(BusinessTable::quickLogin())
            ->select('id', 'type', 'status', 'name', 'url', 'appid', 'appkey', 'create_time');
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('type', 'like', '%' . $keyword . '%')
                    ->orWhere('name', 'like', '%' . $keyword . '%')
                    ->orWhere('url', 'like', '%' . $keyword . '%')
                    ->orWhere('appid', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('id', (int)$keyword);
                }
            });
        }

        $type = trim((string)$request->get('type', ''));
        if ($type !== '') {
            $query->where('type', $type);
        }

        $status = trim((string)$request->get('status', ''));
        if ($status !== '' && in_array($status, ['1', '2'], true)) {
            $query->where('status', (int)$status);
        }

        $name = trim((string)$request->get('name', ''));
        if ($name !== '') {
            $query->where('name', 'like', '%' . $name . '%');
        }
    }

    private function summary(Builder $query): array
    {
        return [
            'enabled_count' => (int)(clone $query)->where('status', 1)->count('id'),
            'disabled_count' => (int)(clone $query)->where('status', '<>', 1)->count('id'),
            'qq_count' => (int)(clone $query)->where('type', 'qq')->count('id'),
            'polymerization_count' => (int)(clone $query)->where('type', 'polymerization')->count('id'),
            'credential_ready_count' => (int)(clone $query)
                ->whereNotNull('appid')
                ->where('appid', '<>', '')
                ->whereNotNull('appkey')
                ->where('appkey', '<>', '')
                ->count('id'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function formatQuickLoginRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $bindingMap = $this->bindingUsageMap(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $rows
        ));

        return array_map(function (array $row) use ($bindingMap): array {
            $id = (int)($row['id'] ?? 0);
            return AdminQuickLoginFormatter::format(
                $this->attachBindingMeta($row, $bindingMap[$id] ?? [])
            );
        }, $rows);
    }

    private function loadQuickLoginRow(int $id): ?array
    {
        $row = $this->quickLoginQuery()
            ->where('id', $id)
            ->first();

        if (!$row) {
            return null;
        }

        $rowArray = (array)$row;
        $bindingMap = $this->bindingUsageMap([$id]);

        return $this->attachBindingMeta($rowArray, $bindingMap[$id] ?? []);
    }

    /**
     * @param array<int, int> $quickLoginIds
     * @return array<int, array<string, mixed>>
     */
    private function loadQuickLoginRowsByIds(array $quickLoginIds): array
    {
        if ($quickLoginIds === []) {
            return [];
        }

        $rows = array_map(
            static fn($row): array => (array)$row,
            Db::table(BusinessTable::quickLogin())
                ->select('id', 'type', 'status', 'name', 'url', 'appid', 'appkey', 'create_time')
                ->whereIn('id', $quickLoginIds)
                ->get()
                ->toArray()
        );

        if ($rows === []) {
            return [];
        }

        $bindingMap = $this->bindingUsageMap($quickLoginIds);

        return array_map(function (array $row) use ($bindingMap): array {
            $id = (int)($row['id'] ?? 0);
            return $this->attachBindingMeta($row, $bindingMap[$id] ?? []);
        }, $rows);
    }

    /**
     * @param array<int, int> $quickLoginIds
     * @return array<int, array<string, array<int, string>|int>>
     */
    private function bindingUsageMap(array $quickLoginIds): array
    {
        $quickLoginIds = array_values(array_filter(array_map('intval', $quickLoginIds), static fn(int $id): bool => $id > 0));
        if ($quickLoginIds === []) {
            return [];
        }

        $map = [];
        foreach ($quickLoginIds as $quickLoginId) {
            $map[$quickLoginId] = [
                'binding_config_names' => [],
                'binding_labels' => [],
            ];
        }

        $configRows = Db::table('admin_config')
            ->select('config_name', 'config_value')
            ->whereIn('config_name', array_keys(self::BINDING_CONFIGS))
            ->whereIn('config_value', array_map('strval', $quickLoginIds))
            ->get()
            ->toArray();

        foreach ($configRows as $configRow) {
            $configName = trim((string)($configRow->config_name ?? ''));
            $quickLoginId = (int)($configRow->config_value ?? 0);

            if ($quickLoginId <= 0 || !isset($map[$quickLoginId])) {
                continue;
            }

            $map[$quickLoginId]['binding_config_names'][] = $configName;
            $map[$quickLoginId]['binding_labels'][] = (string)(self::BINDING_CONFIGS[$configName] ?? $configName);
        }

        foreach ($map as $quickLoginId => $bindingMeta) {
            $map[$quickLoginId]['binding_config_names'] = array_values(array_unique(array_map(
                'strval',
                (array)($bindingMeta['binding_config_names'] ?? [])
            )));
            $map[$quickLoginId]['binding_labels'] = array_values(array_unique(array_map(
                'strval',
                (array)($bindingMeta['binding_labels'] ?? [])
            )));
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, array<int, string>|int> $bindingMeta
     * @return array<string, mixed>
     */
    private function attachBindingMeta(array $row, array $bindingMeta): array
    {
        $row['binding_config_names'] = array_values(array_map(
            'strval',
            (array)($bindingMeta['binding_config_names'] ?? [])
        ));
        $row['binding_labels'] = array_values(array_map(
            'strval',
            (array)($bindingMeta['binding_labels'] ?? [])
        ));

        return $row;
    }

    private function normalizeWritePayload(array $payload, ?array $current = null): array
    {
        $type = $this->normalizeRequiredString(
            $payload['type'] ?? ($current['type'] ?? null),
            255,
            'quick login type'
        );
        $name = $this->normalizeOptionalString(
            $payload['name'] ?? ($current['name'] ?? null),
            255,
            'quick login name'
        );
        $url = $this->normalizeOptionalString(
            $payload['url'] ?? ($current['url'] ?? null),
            255,
            'quick login url'
        );
        $status = $this->normalizeStatus($payload['status'] ?? ($current['status'] ?? 1));
        $appid = $this->normalizeSecretField($payload, 'appid', $current['appid'] ?? null, 50, 'quick login APPID');
        $appkey = $this->normalizeSecretField($payload, 'appkey', $current['appkey'] ?? null, 255, 'quick login APPKEY');

        return [
            'type' => $type,
            'name' => $name === '' ? null : $name,
            'url' => $url === '' ? null : $url,
            'appid' => $appid,
            'appkey' => $appkey,
            'status' => $status,
        ];
    }

    private function normalizeRequiredString(mixed $value, int $maxLength, string $field): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($field . ' must be a scalar');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            throw new \InvalidArgumentException($field . ' is required');
        }

        if (mb_strlen($normalized) > $maxLength) {
            throw new \InvalidArgumentException($field . ' is too long');
        }

        return $normalized;
    }

    private function normalizeOptionalString(mixed $value, int $maxLength, string $field): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($field . ' must be a scalar');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            return '';
        }

        if (mb_strlen($normalized) > $maxLength) {
            throw new \InvalidArgumentException($field . ' is too long');
        }

        return $normalized;
    }

    private function normalizeSecretField(
        array $payload,
        string $key,
        mixed $current,
        int $maxLength,
        string $field
    ): ?string {
        if (!array_key_exists($key, $payload)) {
            return $this->nullableString($current);
        }

        $value = $payload[$key];
        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($field . ' must be a scalar');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) > $maxLength) {
            throw new \InvalidArgumentException($field . ' is too long');
        }

        return $normalized;
    }

    private function normalizeStatus(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 2;
        }

        if (is_numeric($value)) {
            $status = (int)$value;
            if ($status === 1) {
                return 1;
            }
            if (in_array($status, [0, 2], true)) {
                return 2;
            }
        }

        $normalized = strtolower(trim((string)$value));

        return match ($normalized) {
            '1', 'true', 'yes', 'on', 'enable', 'enabled', 'active' => 1,
            '0', '2', 'false', 'no', 'off', 'disable', 'disabled', 'inactive' => 2,
            default => throw new \InvalidArgumentException('quick login status must be 1 or 2'),
        };
    }

    private function buildQuickLoginDeleteAudit(array $record): array
    {
        $bindingConfigNames = array_values(array_map(
            'strval',
            (array)($record['binding_config_names'] ?? [])
        ));
        $bindingLabels = array_values(array_map(
            'strval',
            (array)($record['binding_labels'] ?? [])
        ));

        $blockingReasons = [];
        if ($bindingLabels !== []) {
            $blockingReasons[] = '当前快捷登录配置仍被以下设置引用：' . implode('、', $bindingLabels) . '。';
        }

        return [
            'quick_login_id' => (int)($record['id'] ?? 0),
            'quick_login_label' => $this->quickLoginLabel($record),
            'type' => trim((string)($record['type'] ?? '')),
            'status' => (int)($record['status'] ?? 0),
            'binding_config_names' => $bindingConfigNames,
            'binding_labels' => $bindingLabels,
            'can_delete' => $blockingReasons === [],
            'confirmation_phrase' => $this->quickLoginDeleteConfirmationPhrase((int)($record['id'] ?? 0)),
            'blocking_reasons' => $blockingReasons,
            'summary' => [
                'delete_row_count' => $blockingReasons === [] ? 1 : 0,
                'binding_count' => count($bindingLabels),
                'blocked_count' => $blockingReasons === [] ? 0 : 1,
            ],
            'warnings' => [
                '删除后会永久移除这条快捷登录数据库记录。',
                '如果仍被 QQ 登录绑定或微信登录绑定引用，请先解除绑定再删除。',
            ],
        ];
    }

    private function batchQuickLoginDeleteAudit(array $quickLoginIds): array
    {
        $rows = $this->loadQuickLoginRowsByIds($quickLoginIds);
        $rowMap = [];
        foreach ($rows as $row) {
            $rowMap[(int)($row['id'] ?? 0)] = $row;
        }

        $items = [];
        $deletableQuickLoginIds = [];
        $blockedQuickLoginIds = [];
        $missingQuickLoginIds = [];
        $deleteRowCount = 0;
        $bindingBlockedCount = 0;

        foreach ($quickLoginIds as $quickLoginId) {
            $row = $rowMap[$quickLoginId] ?? null;
            if ($row === null) {
                $missingQuickLoginIds[] = $quickLoginId;
                $items[] = [
                    'quick_login_id' => $quickLoginId,
                    'quick_login_label' => '',
                    'type' => '',
                    'exists' => false,
                    'can_delete' => false,
                    'binding_config_names' => [],
                    'binding_labels' => [],
                    'blocking_reasons' => ['快捷登录配置记录不存在。'],
                    'summary' => [
                        'delete_row_count' => 0,
                        'binding_count' => 0,
                        'blocked_count' => 1,
                    ],
                    'warnings' => ['请先把不存在的快捷登录配置从已选项中移除，再重新执行批量删除。'],
                ];
                continue;
            }

            $audit = $this->buildQuickLoginDeleteAudit($row);
            $items[] = [
                'quick_login_id' => $quickLoginId,
                'quick_login_label' => (string)($audit['quick_login_label'] ?? ''),
                'type' => (string)($audit['type'] ?? ''),
                'exists' => true,
                'can_delete' => !empty($audit['can_delete']),
                'binding_config_names' => array_values(array_map(
                    'strval',
                    (array)($audit['binding_config_names'] ?? [])
                )),
                'binding_labels' => array_values(array_map(
                    'strval',
                    (array)($audit['binding_labels'] ?? [])
                )),
                'blocking_reasons' => array_values(array_map(
                    'strval',
                    (array)($audit['blocking_reasons'] ?? [])
                )),
                'summary' => (array)($audit['summary'] ?? []),
                'warnings' => array_values(array_map('strval', (array)($audit['warnings'] ?? []))),
            ];

            $summary = (array)($audit['summary'] ?? []);
            $deleteRowCount += (int)($summary['delete_row_count'] ?? 0);

            if (!empty($audit['can_delete'])) {
                $deletableQuickLoginIds[] = $quickLoginId;
                continue;
            }

            $blockedQuickLoginIds[] = $quickLoginId;
            if ((int)($summary['binding_count'] ?? 0) > 0) {
                $bindingBlockedCount++;
            }
        }

        $warnings = [];
        if ($missingQuickLoginIds !== []) {
            $warnings[] = '部分已选快捷登录配置已不存在，请先移出选择列表。';
        }
        if ($bindingBlockedCount > 0) {
            $warnings[] = '至少有一条已选快捷登录配置仍被 QQ 登录绑定或微信登录绑定引用，当前不能继续批量删除。';
        }
        if ($deletableQuickLoginIds !== []) {
            $warnings[] = '确认后会永久删除本次可移除的快捷登录记录。';
        }

        return [
            'requested_quick_login_ids' => $quickLoginIds,
            'deletable_quick_login_ids' => $deletableQuickLoginIds,
            'blocked_quick_login_ids' => $blockedQuickLoginIds,
            'missing_quick_login_ids' => $missingQuickLoginIds,
            'confirmation_phrase' => $this->batchQuickLoginDeleteConfirmationPhrase($quickLoginIds),
            'can_delete_all' => $quickLoginIds !== [] && $blockedQuickLoginIds === [] && $missingQuickLoginIds === [],
            'items' => $items,
            'summary' => [
                'requested_count' => count($quickLoginIds),
                'existing_count' => count($quickLoginIds) - count($missingQuickLoginIds),
                'deletable_count' => count($deletableQuickLoginIds),
                'blocked_count' => count($blockedQuickLoginIds),
                'missing_count' => count($missingQuickLoginIds),
                'delete_row_count' => $deleteRowCount,
                'binding_blocked_count' => $bindingBlockedCount,
            ],
            'warnings' => $warnings,
        ];
    }

    private function deleteQuickLoginRow(int $id): void
    {
        Db::table(BusinessTable::quickLogin())
            ->where('id', $id)
            ->delete();
    }

    /**
     * @param mixed $value
     * @return array<int, int>
     */
    private function normalizeQuickLoginIds(mixed $value, int $maxCount = 100): array
    {
        $items = [];

        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $items = preg_split('/\s*,\s*/', trim($value)) ?: [];
        } elseif (is_numeric($value)) {
            $items = [$value];
        }

        $quickLoginIds = [];
        foreach ($items as $item) {
            if (is_bool($item) || is_array($item) || is_object($item)) {
                continue;
            }

            $normalized = trim((string)$item);
            if ($normalized === '' || !ctype_digit($normalized)) {
                continue;
            }

            $quickLoginId = (int)$normalized;
            if ($quickLoginId > 0) {
                $quickLoginIds[$quickLoginId] = $quickLoginId;
            }
        }

        $quickLoginIds = array_values($quickLoginIds);
        sort($quickLoginIds);

        if ($quickLoginIds === []) {
            throw new \InvalidArgumentException('quick login ids are required');
        }

        if (count($quickLoginIds) > $maxCount) {
            throw new \InvalidArgumentException('too many quick login rows were selected for one batch action');
        }

        return $quickLoginIds;
    }

    private function quickLoginIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function quickLoginLabel(array $record): string
    {
        $name = trim((string)($record['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $type = trim((string)($record['type'] ?? ''));
        if ($type !== '') {
            return $type . ' #' . (int)($record['id'] ?? 0);
        }

        return '快捷登录 #' . (int)($record['id'] ?? 0);
    }

    private function quickLoginDeleteConfirmationPhrase(int $id): string
    {
        return '删除快捷登录 ' . $id;
    }

    /**
     * @param array<int, int> $quickLoginIds
     */
    private function batchQuickLoginDeleteConfirmationPhrase(array $quickLoginIds): string
    {
        return sprintf(
            '批量删除快捷登录 %d-%s',
            count($quickLoginIds),
            strtoupper(substr(md5(implode(',', $quickLoginIds)), 0, 6))
        );
    }

    private function recordAdminQuickLoginCreate(Request $request, array $record): void
    {
        $adminId = (int)(((array)($request->admin ?? []))['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $quickLoginId = (int)($record['id'] ?? 0);
        $quickLoginLabel = $this->truncateLogText($this->quickLoginLabel($record), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/quick-logins/create',
            'desc' => sprintf(
                'quick login create quick_login_id=%d label="%s" type="%s" status=%d has_appid=%d has_appkey=%d url="%s"',
                $quickLoginId,
                $quickLoginLabel,
                $this->truncateLogText((string)($record['type'] ?? ''), 80),
                (int)($record['status'] ?? 0),
                trim((string)($record['appid'] ?? '')) === '' ? 0 : 1,
                trim((string)($record['appkey'] ?? '')) === '' ? 0 : 1,
                $this->truncateLogText((string)($record['url'] ?? ''), 160)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminQuickLoginUpdate(Request $request, array $before, array $after): void
    {
        $adminId = (int)(((array)($request->admin ?? []))['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $quickLoginId = (int)($after['id'] ?? 0);
        $quickLoginLabel = $this->truncateLogText($this->quickLoginLabel($after), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/quick-logins/' . $quickLoginId . '/update',
            'desc' => sprintf(
                'quick login update quick_login_id=%d label="%s" from_status=%d to_status=%d type_changed=%d name_changed=%d url_changed=%d appid_changed=%d appkey_changed=%d',
                $quickLoginId,
                $quickLoginLabel,
                (int)($before['status'] ?? 0),
                (int)($after['status'] ?? 0),
                trim((string)($before['type'] ?? '')) === trim((string)($after['type'] ?? '')) ? 0 : 1,
                trim((string)($before['name'] ?? '')) === trim((string)($after['name'] ?? '')) ? 0 : 1,
                trim((string)($before['url'] ?? '')) === trim((string)($after['url'] ?? '')) ? 0 : 1,
                trim((string)($before['appid'] ?? '')) === trim((string)($after['appid'] ?? '')) ? 0 : 1,
                trim((string)($before['appkey'] ?? '')) === trim((string)($after['appkey'] ?? '')) ? 0 : 1
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminQuickLoginStatus(Request $request, array $record, int $status): void
    {
        $adminId = (int)(((array)($request->admin ?? []))['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $quickLoginId = (int)($record['id'] ?? 0);
        $quickLoginLabel = $this->truncateLogText($this->quickLoginLabel($record), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/quick-logins/' . $quickLoginId . '/status',
            'desc' => sprintf(
                'quick login status quick_login_id=%d label="%s" from_status=%d to_status=%d',
                $quickLoginId,
                $quickLoginLabel,
                (int)($record['status'] ?? 0),
                $status
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminQuickLoginDelete(Request $request, array $audit): void
    {
        $adminId = (int)(((array)($request->admin ?? []))['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $quickLoginId = (int)($audit['quick_login_id'] ?? 0);
        $quickLoginLabel = $this->truncateLogText((string)($audit['quick_login_label'] ?? ''), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/quick-logins/' . $quickLoginId . '/delete',
            'desc' => sprintf(
                'quick login delete quick_login_id=%d label="%s" delete_rows=%d binding_count=%d',
                $quickLoginId,
                $quickLoginLabel,
                (int)($summary['delete_row_count'] ?? 0),
                (int)($summary['binding_count'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminQuickLoginBatchDelete(Request $request, array $audit): void
    {
        $adminId = (int)(((array)($request->admin ?? []))['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $quickLoginIds = implode(',', array_map('intval', (array)($audit['deletable_quick_login_ids'] ?? [])));
        $quickLoginLabels = implode(',', array_map(
            static function (array $item): string {
                $label = trim((string)($item['quick_login_label'] ?? ''));
                $quickLoginId = (int)($item['quick_login_id'] ?? 0);
                return $label !== '' ? $label : ('快捷登录 #' . $quickLoginId);
            },
            array_values(array_filter(
                (array)($audit['items'] ?? []),
                static fn(array $item): bool => !empty($item['can_delete'])
            ))
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/quick-logins/batch-delete',
            'desc' => sprintf(
                'quick login batch delete requested=%d deleted=%d blocked=%d missing=%d binding_blocked=%d delete_rows=%d quick_login_ids="%s" labels="%s"',
                (int)($summary['requested_count'] ?? 0),
                (int)($summary['deletable_count'] ?? 0),
                (int)($summary['blocked_count'] ?? 0),
                (int)($summary['missing_count'] ?? 0),
                (int)($summary['binding_blocked_count'] ?? 0),
                (int)($summary['delete_row_count'] ?? 0),
                $this->truncateLogText($quickLoginIds, 255),
                $this->truncateLogText($quickLoginLabels, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorizeAny($request, 'SystemQuickLogins', [$authMark, 'index']);
    }

    private function truncateLogText(string $value, int $limit): string
    {
        $value = trim(str_replace(["\r", "\n"], ' ', $value));
        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, max(0, $limit - 3)) . '...';
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }
}
