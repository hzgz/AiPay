<?php

namespace app\controller;

use app\controller\concerns\AdminControllerFormatSupport;
use app\support\AdminPluginDownloadFormatter;
use app\support\AdminRouteAuthorization;
use app\support\ApiResponse;
use app\support\RequestPayload;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class PluginDownloadController
{
    use AdminControllerFormatSupport;

    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $summaryQuery = $this->pluginQuery();
        $this->applyBaseFilters($summaryQuery, $request);

        $summary = $this->summary(clone $summaryQuery);

        $query = clone $summaryQuery;
        $this->applyStatusFilter($query, $request);

        $total = (int)(clone $query)->count('id');
        $rows = $query
            ->orderByDesc('id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        $records = array_map(
            static fn($row): array => AdminPluginDownloadFormatter::format((array)$row),
            $rows
        );

        return ApiResponse::success([
            'records' => $records,
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => $summary,
        ]);
    }

    public function show(Request $request): Response
    {
        $id = $this->pluginIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('plugin download id is required', 422, null, 422);
        }

        $row = $this->loadPluginRow($id);
        if ($row === null) {
            return ApiResponse::error('plugin download not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminPluginDownloadFormatter::format($row),
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
        $pluginId = (int)Db::table('ypay_plug')->insertGetId([
            'name' => $payload['name'],
            'downurl' => $payload['downurl'],
            'introduce' => $payload['introduce'],
            'status' => $payload['status'],
            'create_time' => $now,
            'update_time' => $now,
            'delete_time' => null,
        ]);

        $created = $this->loadPluginRow($pluginId);
        if ($created === null) {
            return ApiResponse::error('created plugin download could not be loaded', 500, null, 500);
        }

        $this->recordAdminPluginCreate($request, $created);

        return ApiResponse::success([
            'item' => AdminPluginDownloadFormatter::format($created),
            'created_plugin_id' => $pluginId,
            'created_plugin_label' => $this->pluginLabel($created),
        ], 'plugin download created');
    }

    public function update(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->pluginIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('plugin download id is required', 422, null, 422);
        }

        $record = $this->loadPluginRow($id);
        if ($record === null) {
            return ApiResponse::error('plugin download not found', 404, null, 404);
        }

        if (!empty($record['delete_time'])) {
            return ApiResponse::error('recycled plugin download must be restored before editing', 422, null, 422);
        }

        try {
            $payload = $this->normalizeWritePayload(RequestPayload::all($request), $record);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table('ypay_plug')
            ->where('id', $id)
            ->update([
                'name' => $payload['name'],
                'downurl' => $payload['downurl'],
                'introduce' => $payload['introduce'],
                'status' => $payload['status'],
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        $updated = $this->loadPluginRow($id);
        if ($updated === null) {
            return ApiResponse::error('updated plugin download could not be loaded', 500, null, 500);
        }

        $this->recordAdminPluginUpdate($request, $record, $updated);

        return ApiResponse::success([
            'item' => AdminPluginDownloadFormatter::format($updated),
            'updated_plugin_id' => $id,
            'updated_plugin_label' => $this->pluginLabel($updated),
        ], 'plugin download updated');
    }

    public function status(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'status');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->pluginIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('plugin download id is required', 422, null, 422);
        }

        $record = $this->loadPluginRow($id);
        if ($record === null) {
            return ApiResponse::error('plugin download not found', 404, null, 404);
        }

        if (!empty($record['delete_time'])) {
            return ApiResponse::error('recycled plugin download must be restored before changing status', 422, null, 422);
        }

        $payload = RequestPayload::all($request);

        try {
            $status = $this->normalizeStatus($payload['status'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table('ypay_plug')
            ->where('id', $id)
            ->update([
                'status' => $status,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        $updated = $this->loadPluginRow($id);
        if ($updated === null) {
            return ApiResponse::error('updated plugin download could not be loaded', 500, null, 500);
        }

        $this->recordAdminPluginStatus($request, $record, $status);

        return ApiResponse::success([
            'item' => AdminPluginDownloadFormatter::format($updated),
            'updated_plugin_id' => $id,
            'updated_plugin_label' => $this->pluginLabel($updated),
            'status' => $status,
            'status_label' => (string)(AdminPluginDownloadFormatter::format($updated)['status_label'] ?? ''),
        ], 'plugin download status updated');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->pluginIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('plugin download id is required', 422, null, 422);
        }

        $record = $this->loadPluginRow($id);
        if ($record === null) {
            return ApiResponse::error('plugin download not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminPluginDownloadFormatter::format($record),
            'audit' => $this->buildPluginDeleteAudit($record),
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->pluginIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('plugin download id is required', 422, null, 422);
        }

        $record = $this->loadPluginRow($id);
        if ($record === null) {
            return ApiResponse::error('plugin download not found', 404, null, 404);
        }

        $audit = $this->buildPluginDeleteAudit($record);
        if (empty($audit['can_delete'])) {
            return ApiResponse::error(
                'plugin download cannot be deleted until the recycle-bin conflict is cleared',
                422,
                ['audit' => $audit],
                422
            );
        }

        $payload = RequestPayload::all($request);
        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error(
                'confirmation phrase mismatch',
                422,
                ['audit' => $audit],
                422
            );
        }

        Db::transaction(function () use ($id): void {
            $this->deletePluginRow($id);
        });

        $this->recordAdminPluginDelete($request, $audit);

        return ApiResponse::success([
            'deleted_plugin_id' => $id,
            'deleted_plugin_label' => (string)($audit['plugin_label'] ?? ''),
            'audit' => $audit,
        ], 'plugin download deleted');
    }

    public function batchDeleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $pluginIds = $this->normalizePluginIds($payload['plugin_ids'] ?? $payload['ids'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success([
            'audit' => $this->batchPluginDeleteAudit($pluginIds),
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
            $pluginIds = $this->normalizePluginIds($payload['plugin_ids'] ?? $payload['ids'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $audit = $this->batchPluginDeleteAudit($pluginIds);
        if (empty($audit['can_delete_all'])) {
            return ApiResponse::error(
                'selected plugin downloads cannot be batch deleted until the recycle-bin conflicts are cleared',
                422,
                ['audit' => $audit],
                422
            );
        }

        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error(
                'confirmation phrase mismatch',
                422,
                ['audit' => $audit],
                422
            );
        }

        Db::transaction(function () use ($audit): void {
            foreach ((array)($audit['deletable_plugin_ids'] ?? []) as $pluginId) {
                $this->deletePluginRow((int)$pluginId);
            }
        });

        $this->recordAdminPluginBatchDelete($request, $audit);

        return ApiResponse::success([
            'deleted_plugin_ids' => array_values(array_map('intval', (array)($audit['deletable_plugin_ids'] ?? []))),
            'deleted_count' => (int)(($audit['summary'] ?? [])['deletable_count'] ?? 0),
            'audit' => $audit,
        ], 'plugin download batch delete completed');
    }

    public function restore(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'recycle');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->pluginIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('plugin download id is required', 422, null, 422);
        }

        $record = $this->loadPluginRow($id);
        if ($record === null) {
            return ApiResponse::error('plugin download not found', 404, null, 404);
        }

        if (empty($record['delete_time'])) {
            return ApiResponse::error('plugin download is already active', 422, null, 422);
        }

        try {
            $this->restorePluginRow($id);
        } catch (\Throwable $exception) {
            return ApiResponse::error('plugin download restore failed', 500, null, 500);
        }

        $restored = $this->loadPluginRow($id);
        if ($restored === null) {
            return ApiResponse::error('restored plugin download could not be loaded', 500, null, 500);
        }

        $this->recordAdminPluginRestore($request, $record);

        return ApiResponse::success([
            'item' => AdminPluginDownloadFormatter::format($restored),
            'restored_plugin_id' => $id,
            'restored_plugin_label' => $this->pluginLabel($record),
        ], 'plugin download restored');
    }

    public function batchRestore(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'recycle');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $payload = RequestPayload::all($request);
            $pluginIds = $this->normalizePluginIds($payload['plugin_ids'] ?? $request->post('plugin_ids', []));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        if ($pluginIds === []) {
            return ApiResponse::error('at least one plugin id is required', 422, null, 422);
        }

        $rows = $this->loadPluginRowsByIds($pluginIds);
        if ($rows === []) {
            return ApiResponse::error('no plugin download rows matched the restore request', 422, [
                'restored_plugin_ids' => [],
                'already_active_plugin_ids' => [],
                'missing_plugin_ids' => $pluginIds,
            ], 422);
        }

        $rowMap = [];
        foreach ($rows as $row) {
            $rowMap[(int)($row['id'] ?? 0)] = $row;
        }

        $restorableRows = [];
        $alreadyActivePluginIds = [];
        $matchedPluginIds = [];

        foreach ($pluginIds as $pluginId) {
            $row = $rowMap[$pluginId] ?? null;
            if ($row === null) {
                continue;
            }

            $matchedPluginIds[] = $pluginId;

            if (empty($row['delete_time'])) {
                $alreadyActivePluginIds[] = $pluginId;
                continue;
            }

            $restorableRows[] = $row;
        }

        $missingPluginIds = array_values(array_diff($pluginIds, $matchedPluginIds));

        if ($restorableRows === []) {
            return ApiResponse::error('no recycled plugin download rows matched the restore request', 422, [
                'restored_plugin_ids' => [],
                'already_active_plugin_ids' => $alreadyActivePluginIds,
                'missing_plugin_ids' => $missingPluginIds,
            ], 422);
        }

        Db::transaction(function () use ($restorableRows): void {
            foreach ($restorableRows as $row) {
                $this->restorePluginRow((int)($row['id'] ?? 0));
            }
        });

        $restoredPluginIds = array_values(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $restorableRows
        ));

        $this->recordAdminPluginBatchRestore(
            $request,
            $restorableRows,
            $pluginIds,
            $alreadyActivePluginIds,
            $missingPluginIds
        );

        return ApiResponse::success([
            'restored_plugin_ids' => $restoredPluginIds,
            'restored_count' => count($restorableRows),
            'already_active_plugin_ids' => $alreadyActivePluginIds,
            'missing_plugin_ids' => $missingPluginIds,
        ], 'plugin downloads restored');
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'ContentPluginDownloads', $authMark);
    }

    private function pluginQuery(): Builder
    {
        return Db::table('ypay_plug')
            ->select(
                'id',
                'name',
                'downurl',
                'introduce',
                'status',
                'create_time',
                'update_time',
                'delete_time'
            );
    }

    private function applyBaseFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('downurl', 'like', '%' . $keyword . '%')
                    ->orWhere('introduce', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('id', (int)$keyword);
                }
            });
        }
    }

    private function applyStatusFilter(Builder $query, Request $request): void
    {
        $status = trim((string)$request->get('status', ''));
        if ($status === '-1' || strtolower($status) === 'deleted') {
            $query->whereNotNull('delete_time');
            return;
        }

        $query->whereNull('delete_time');

        if ($status === '1') {
            $query->where('status', 1);
            return;
        }

        if ($status !== '' && in_array($status, ['0', '2'], true)) {
            $query->where('status', '<>', 1);
        }
    }

    private function summary(Builder $query): array
    {
        return [
            'visible_count' => (int)(clone $query)
                ->where('status', 1)
                ->whereNull('delete_time')
                ->count('id'),
            'hidden_count' => (int)(clone $query)
                ->where('status', '<>', 1)
                ->whereNull('delete_time')
                ->count('id'),
            'download_ready_count' => (int)(clone $query)
                ->whereNotNull('downurl')
                ->where('downurl', '<>', '')
                ->whereNull('delete_time')
                ->count('id'),
            'introduced_count' => (int)(clone $query)
                ->whereNotNull('introduce')
                ->where('introduce', '<>', '')
                ->whereNull('delete_time')
                ->count('id'),
            'deleted_count' => (int)(clone $query)
                ->whereNotNull('delete_time')
                ->count('id'),
        ];
    }

    private function loadPluginRow(int $id): ?array
    {
        $row = $this->pluginQuery()
            ->where('id', $id)
            ->first();

        return $row ? (array)$row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadPluginRowsByIds(array $pluginIds): array
    {
        if ($pluginIds === []) {
            return [];
        }

        return array_map(
            static fn($row): array => (array)$row,
            Db::table('ypay_plug')
                ->select('id', 'name', 'downurl', 'delete_time')
                ->whereIn('id', $pluginIds)
                ->get()
                ->toArray()
        );
    }

    private function normalizeWritePayload(array $payload, ?array $current = null): array
    {
        $name = $this->normalizeOptionalString(
            $payload['name'] ?? ($current['name'] ?? null),
            50,
            'plugin download name'
        );
        $downurl = $this->normalizeOptionalString(
            $payload['downurl'] ?? ($current['downurl'] ?? null),
            65535,
            'plugin download url'
        );
        $introduce = $this->normalizeNullableText(
            $payload['introduce'] ?? ($current['introduce'] ?? null),
            'plugin introduction',
            65535
        );
        $status = $this->normalizeStatus($payload['status'] ?? ($current['status'] ?? 1));

        return [
            'name' => $name === '' ? null : $name,
            'downurl' => $downurl === '' ? null : $downurl,
            'introduce' => $introduce,
            'status' => $status,
        ];
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
            '1', 'true', 'yes', 'on', 'enable', 'enabled', 'visible' => 1,
            '0', '2', 'false', 'no', 'off', 'disable', 'disabled', 'hidden' => 2,
            default => throw new \InvalidArgumentException('plugin download status must be 1 or 2'),
        };
    }

    private function normalizeOptionalString(mixed $value, int $maxLength, string $field): string
    {
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

    private function normalizeNullableText(mixed $value, string $field, int $maxLength): ?string
    {
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

    private function buildPluginDeleteAudit(array $record): array
    {
        $isDeleted = !empty($record['delete_time']);
        $pluginId = (int)($record['id'] ?? 0);

        $blockingReasons = [];
        if ($isDeleted) {
            $blockingReasons[] = 'This plugin download record is already in the recycle bin.';
        }

        return [
            'plugin_id' => $pluginId,
            'plugin_label' => $this->pluginLabel($record),
            'downurl' => trim((string)($record['downurl'] ?? '')),
            'status' => (int)($record['status'] ?? 0),
            'can_delete' => $blockingReasons === [],
            'confirmation_phrase' => $this->pluginDeleteConfirmationPhrase($pluginId),
            'blocking_reasons' => $blockingReasons,
            'summary' => [
                'delete_row_count' => $blockingReasons === [] ? 1 : 0,
                'blocked_count' => $blockingReasons === [] ? 0 : 1,
            ],
            'warnings' => [
                'Deleting a plugin download record moves the row into the recycle bin first.',
                'You can restore the plugin download record later from the recycle view if needed.',
            ],
        ];
    }

    private function batchPluginDeleteAudit(array $pluginIds): array
    {
        $rows = $this->loadPluginRowsByIds($pluginIds);
        $rowMap = [];
        foreach ($rows as $row) {
            $rowMap[(int)($row['id'] ?? 0)] = $row;
        }

        $items = [];
        $deletablePluginIds = [];
        $blockedPluginIds = [];
        $missingPluginIds = [];
        $deleteRowCount = 0;

        foreach ($pluginIds as $pluginId) {
            $row = $rowMap[$pluginId] ?? null;
            if ($row === null) {
                $missingPluginIds[] = $pluginId;
                $items[] = [
                    'plugin_id' => $pluginId,
                    'plugin_label' => '',
                    'downurl' => '',
                    'exists' => false,
                    'can_delete' => false,
                    'blocking_reasons' => ['This plugin download record was not found in ypay_plug.'],
                    'summary' => [
                        'delete_row_count' => 0,
                        'blocked_count' => 1,
                    ],
                    'warnings' => ['Remove missing plugin download records from the selection before retrying the batch delete.'],
                ];
                continue;
            }

            $audit = $this->buildPluginDeleteAudit($row);
            $items[] = [
                'plugin_id' => $pluginId,
                'plugin_label' => (string)($audit['plugin_label'] ?? ''),
                'downurl' => (string)($audit['downurl'] ?? ''),
                'exists' => true,
                'can_delete' => !empty($audit['can_delete']),
                'blocking_reasons' => array_values(array_map('strval', (array)($audit['blocking_reasons'] ?? []))),
                'summary' => (array)($audit['summary'] ?? []),
                'warnings' => array_values(array_map('strval', (array)($audit['warnings'] ?? []))),
            ];

            $summary = (array)($audit['summary'] ?? []);
            $deleteRowCount += (int)($summary['delete_row_count'] ?? 0);

            if (!empty($audit['can_delete'])) {
                $deletablePluginIds[] = $pluginId;
                continue;
            }

            $blockedPluginIds[] = $pluginId;
        }

        $warnings = [];
        if ($missingPluginIds !== []) {
            $warnings[] = 'Some selected plugin download records no longer exist and must be removed from the batch selection.';
        }
        if ($blockedPluginIds !== []) {
            $warnings[] = 'At least one selected plugin download record is already in the recycle bin, so the batch delete is paused until the selection is cleaned up.';
        }
        if ($deletablePluginIds !== []) {
            $warnings[] = 'Batch delete moves the selected plugin download records into the recycle bin after one shared confirmation phrase is accepted.';
        }

        return [
            'requested_plugin_ids' => $pluginIds,
            'deletable_plugin_ids' => $deletablePluginIds,
            'blocked_plugin_ids' => $blockedPluginIds,
            'missing_plugin_ids' => $missingPluginIds,
            'confirmation_phrase' => $this->batchPluginDeleteConfirmationPhrase($pluginIds),
            'can_delete_all' => $pluginIds !== [] && $blockedPluginIds === [] && $missingPluginIds === [],
            'items' => $items,
            'summary' => [
                'requested_count' => count($pluginIds),
                'existing_count' => count($pluginIds) - count($missingPluginIds),
                'deletable_count' => count($deletablePluginIds),
                'blocked_count' => count($blockedPluginIds),
                'missing_count' => count($missingPluginIds),
                'delete_row_count' => $deleteRowCount,
            ],
            'warnings' => $warnings,
        ];
    }

    private function deletePluginRow(int $id): void
    {
        Db::table('ypay_plug')
            ->where('id', $id)
            ->update(['delete_time' => date('Y-m-d H:i:s')]);
    }

    private function restorePluginRow(int $id): void
    {
        Db::table('ypay_plug')
            ->where('id', $id)
            ->update(['delete_time' => null]);
    }

    /**
     * @return array<int, int>
     */
    private function normalizePluginIds(mixed $value, int $maxCount = 100): array
    {
        $items = [];

        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $items = preg_split('/\s*,\s*/', trim($value)) ?: [];
        } elseif (is_numeric($value)) {
            $items = [$value];
        }

        $pluginIds = [];
        foreach ($items as $item) {
            if (is_bool($item) || is_array($item) || is_object($item)) {
                continue;
            }

            $normalized = trim((string)$item);
            if ($normalized === '' || !ctype_digit($normalized)) {
                continue;
            }

            $pluginId = (int)$normalized;
            if ($pluginId > 0) {
                $pluginIds[$pluginId] = $pluginId;
            }
        }

        $pluginIds = array_values($pluginIds);
        sort($pluginIds);

        if ($pluginIds === []) {
            throw new \InvalidArgumentException('plugin ids are required');
        }

        if (count($pluginIds) > $maxCount) {
            throw new \InvalidArgumentException('too many plugin download rows were selected for one batch action');
        }

        return $pluginIds;
    }

    private function pluginIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function pluginLabel(array $record): string
    {
        $name = trim((string)($record['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $downurl = trim((string)($record['downurl'] ?? ''));
        if ($downurl !== '') {
            return $downurl;
        }

        return 'plugin #' . (int)($record['id'] ?? 0);
    }

    private function pluginDeleteConfirmationPhrase(int $id): string
    {
        return 'DELETE PLUGIN DOWNLOAD ' . $id;
    }

    private function batchPluginDeleteConfirmationPhrase(array $pluginIds): string
    {
        return sprintf(
            'DELETE PLUGIN DOWNLOAD BATCH %d-%s',
            count($pluginIds),
            strtoupper(substr(md5(implode(',', $pluginIds)), 0, 6))
        );
    }

    private function recordAdminPluginCreate(Request $request, array $record): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $pluginId = (int)($record['id'] ?? 0);
        $pluginLabel = $this->truncateLogText($this->pluginLabel($record), 120);
        $downurl = $this->truncateLogText((string)($record['downurl'] ?? ''), 160);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/plugin-downloads/create',
            'desc' => sprintf(
                'plugin download create plugin_id=%d label="%s" status=%d url="%s" has_introduce=%d',
                $pluginId,
                $pluginLabel,
                (int)($record['status'] ?? 0),
                $downurl,
                trim((string)($record['introduce'] ?? '')) === '' ? 0 : 1
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminPluginUpdate(Request $request, array $before, array $after): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $pluginId = (int)($after['id'] ?? 0);
        $pluginLabel = $this->truncateLogText($this->pluginLabel($after), 120);
        $downurl = $this->truncateLogText((string)($after['downurl'] ?? ''), 160);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/plugin-downloads/' . $pluginId . '/update',
            'desc' => sprintf(
                'plugin download update plugin_id=%d label="%s" from_status=%d to_status=%d name_changed=%d url_changed=%d introduce_changed=%d url="%s"',
                $pluginId,
                $pluginLabel,
                (int)($before['status'] ?? 0),
                (int)($after['status'] ?? 0),
                trim((string)($before['name'] ?? '')) === trim((string)($after['name'] ?? '')) ? 0 : 1,
                trim((string)($before['downurl'] ?? '')) === trim((string)($after['downurl'] ?? '')) ? 0 : 1,
                trim((string)($before['introduce'] ?? '')) === trim((string)($after['introduce'] ?? '')) ? 0 : 1,
                $downurl
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminPluginStatus(Request $request, array $record, int $status): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $pluginId = (int)($record['id'] ?? 0);
        $pluginLabel = $this->truncateLogText($this->pluginLabel($record), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/plugin-downloads/' . $pluginId . '/status',
            'desc' => sprintf(
                'plugin download status plugin_id=%d label="%s" from_status=%d to_status=%d',
                $pluginId,
                $pluginLabel,
                (int)($record['status'] ?? 0),
                $status
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminPluginDelete(Request $request, array $audit): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $pluginId = (int)($audit['plugin_id'] ?? 0);
        $pluginLabel = $this->truncateLogText((string)($audit['plugin_label'] ?? ''), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/plugin-downloads/' . $pluginId . '/delete',
            'desc' => sprintf(
                'plugin download delete plugin_id=%d label="%s" delete_rows=%d',
                $pluginId,
                $pluginLabel,
                (int)($summary['delete_row_count'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminPluginBatchDelete(Request $request, array $audit): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $pluginIds = implode(',', array_map('intval', (array)($audit['deletable_plugin_ids'] ?? [])));
        $pluginLabels = implode(',', array_map(
            static function (array $item): string {
                $label = trim((string)($item['plugin_label'] ?? ''));
                $pluginId = (int)($item['plugin_id'] ?? 0);

                return $label !== '' ? $label : ('plugin #' . $pluginId);
            },
            array_values(array_filter(
                (array)($audit['items'] ?? []),
                static fn(array $item): bool => !empty($item['can_delete'])
            ))
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/plugin-downloads/batch-delete',
            'desc' => sprintf(
                'plugin download batch delete requested=%d deleted=%d blocked=%d missing=%d delete_rows=%d plugins="%s" labels="%s"',
                (int)($summary['requested_count'] ?? 0),
                (int)($summary['deletable_count'] ?? 0),
                (int)($summary['blocked_count'] ?? 0),
                (int)($summary['missing_count'] ?? 0),
                (int)($summary['delete_row_count'] ?? 0),
                $this->truncateLogText($pluginIds, 255),
                $this->truncateLogText($pluginLabels, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminPluginRestore(Request $request, array $record): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $pluginId = (int)($record['id'] ?? 0);
        $pluginLabel = $this->truncateLogText($this->pluginLabel($record), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/plugin-downloads/' . $pluginId . '/restore',
            'desc' => sprintf(
                'plugin download restore plugin_id=%d label="%s"',
                $pluginId,
                $pluginLabel
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminPluginBatchRestore(
        Request $request,
        array $restorableRows,
        array $requestedPluginIds,
        array $alreadyActivePluginIds,
        array $missingPluginIds
    ): void {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $restoredPluginIds = implode(',', array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $restorableRows
        ));
        $restoredLabels = implode(',', array_map(
            fn(array $row): string => $this->pluginLabel($row),
            $restorableRows
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/plugin-downloads/batch-restore',
            'desc' => sprintf(
                'plugin download batch restore requested=%d restored=%d active=%d missing=%d plugins="%s" labels="%s"',
                count($requestedPluginIds),
                count($restorableRows),
                count($alreadyActivePluginIds),
                count($missingPluginIds),
                $this->truncateLogText($restoredPluginIds, 255),
                $this->truncateLogText($restoredLabels, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

}
