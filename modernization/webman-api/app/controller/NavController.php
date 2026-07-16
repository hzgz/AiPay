<?php

namespace app\controller;

use app\controller\concerns\AdminControllerFormatSupport;
use app\support\AdminNavFormatter;
use app\support\AdminRouteAuthorization;
use app\support\ApiResponse;
use app\support\RequestPayload;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class NavController
{
    use AdminControllerFormatSupport;

    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $summaryQuery = $this->navQuery();
        $this->applyBaseFilters($summaryQuery, $request);

        $summary = $this->summary(clone $summaryQuery);

        $query = clone $summaryQuery;
        $this->applyStatusFilter($query, $request);

        $total = (int)(clone $query)->count('id');
        $rows = $query
            ->orderBy('sort')
            ->orderBy('id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        $records = array_map(
            static fn($row): array => AdminNavFormatter::format((array)$row),
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
        $id = $this->navIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('nav id is required', 422, null, 422);
        }

        $row = $this->loadNavRow($id);
        if ($row === null) {
            return ApiResponse::error('nav not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminNavFormatter::format($row),
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

        $navId = (int)Db::table('ypay_navs')->insertGetId([
            'name' => $payload['name'],
            'url' => $payload['url'],
            'is_target' => $payload['is_target'],
            'status' => $payload['status'],
            'create_time' => date('Y-m-d H:i:s'),
            'sort' => $payload['sort'],
            'delete_time' => null,
        ]);

        $created = $this->loadNavRow($navId);
        if ($created === null) {
            return ApiResponse::error('created nav could not be loaded', 500, null, 500);
        }

        $this->recordAdminNavCreate($request, $created);

        return ApiResponse::success([
            'item' => AdminNavFormatter::format($created),
            'created_nav_id' => $navId,
            'created_nav_label' => $this->navLabel($created),
        ], 'nav created');
    }

    public function update(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->navIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('nav id is required', 422, null, 422);
        }

        $record = $this->loadNavRow($id);
        if ($record === null) {
            return ApiResponse::error('nav not found', 404, null, 404);
        }

        if (!empty($record['delete_time'])) {
            return ApiResponse::error('recycled nav must be restored before editing', 422, null, 422);
        }

        try {
            $payload = $this->normalizeWritePayload(RequestPayload::all($request), $record);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table('ypay_navs')
            ->where('id', $id)
            ->update([
                'name' => $payload['name'],
                'url' => $payload['url'],
                'is_target' => $payload['is_target'],
                'status' => $payload['status'],
                'sort' => $payload['sort'],
            ]);

        $updated = $this->loadNavRow($id);
        if ($updated === null) {
            return ApiResponse::error('updated nav could not be loaded', 500, null, 500);
        }

        $this->recordAdminNavUpdate($request, $record, $updated);

        return ApiResponse::success([
            'item' => AdminNavFormatter::format($updated),
            'updated_nav_id' => $id,
            'updated_nav_label' => $this->navLabel($updated),
        ], 'nav updated');
    }

    public function reorder(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'sort');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $navIds = $this->normalizeOrderedNavIds($payload['visible_nav_ids'] ?? $payload['nav_ids'] ?? [], 200);
            $fromIndex = $this->normalizeSequenceIndex(
                $payload['from_index'] ?? $payload['sort_old'] ?? null,
                'nav sort from index'
            );
            $toIndex = $this->normalizeSequenceIndex(
                $payload['to_index'] ?? $payload['sort_new'] ?? null,
                'nav sort to index'
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        if ($fromIndex >= count($navIds) || $toIndex >= count($navIds)) {
            return ApiResponse::error('nav reorder indexes are out of range', 422, null, 422);
        }

        $rows = $this->loadNavRowsBySequence($navIds);
        if (count($rows) !== count($navIds)) {
            return ApiResponse::error('one or more navigation records were not found or are recycled', 404, null, 404);
        }

        [$sortValues, $sortValuesRebased] = $this->resolveNavReorderSortValues($rows);

        if ($fromIndex !== $toIndex) {
            $movedRow = $rows[$fromIndex] ?? null;
            if ($movedRow === null) {
                return ApiResponse::error('nav reorder indexes are out of range', 422, null, 422);
            }

            array_splice($rows, $fromIndex, 1);
            array_splice($rows, $toIndex, 0, [$movedRow]);
        }

        Db::transaction(function () use ($rows, $sortValues): void {
            foreach ($rows as $index => $row) {
                Db::table('ypay_navs')
                    ->where('id', (int)($row['id'] ?? 0))
                    ->update(['sort' => (int)($sortValues[$index] ?? ($index + 1))]);
            }
        });

        $this->recordAdminNavReorder($request, $rows, $fromIndex, $toIndex, $sortValuesRebased);

        return ApiResponse::success([
            'moved_nav_id' => (int)($rows[$toIndex]['id'] ?? 0),
            'from_index' => $fromIndex,
            'to_index' => $toIndex,
            'updated_count' => count($rows),
            'sort_values_rebased' => $sortValuesRebased,
            'visible_nav_ids' => array_values(array_map(
                static fn(array $row): int => (int)($row['id'] ?? 0),
                $rows
            )),
        ], 'nav order updated');
    }

    public function status(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'status');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->navIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('nav id is required', 422, null, 422);
        }

        $record = $this->loadNavRow($id);
        if ($record === null) {
            return ApiResponse::error('nav not found', 404, null, 404);
        }

        if (!empty($record['delete_time'])) {
            return ApiResponse::error('recycled nav must be restored before changing status', 422, null, 422);
        }

        $payload = RequestPayload::all($request);

        try {
            $status = $this->normalizeToggle($payload['status'] ?? null, 'nav status');
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table('ypay_navs')
            ->where('id', $id)
            ->update(['status' => $status]);

        $updated = $this->loadNavRow($id);
        if ($updated === null) {
            return ApiResponse::error('updated nav could not be loaded', 500, null, 500);
        }

        $this->recordAdminNavStatus($request, $record, $status);

        return ApiResponse::success([
            'item' => AdminNavFormatter::format($updated),
            'updated_nav_id' => $id,
            'updated_nav_label' => $this->navLabel($updated),
            'status' => $status,
            'status_label' => (string)(AdminNavFormatter::format($updated)['status_label'] ?? ''),
        ], 'nav status updated');
    }

    public function target(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'target');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->navIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('nav id is required', 422, null, 422);
        }

        $record = $this->loadNavRow($id);
        if ($record === null) {
            return ApiResponse::error('nav not found', 404, null, 404);
        }

        if (!empty($record['delete_time'])) {
            return ApiResponse::error('recycled nav must be restored before changing target mode', 422, null, 422);
        }

        $payload = RequestPayload::all($request);

        try {
            $isTarget = $this->normalizeToggle($payload['is_target'] ?? null, 'nav target');
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table('ypay_navs')
            ->where('id', $id)
            ->update(['is_target' => $isTarget]);

        $updated = $this->loadNavRow($id);
        if ($updated === null) {
            return ApiResponse::error('updated nav could not be loaded', 500, null, 500);
        }

        $this->recordAdminNavTarget($request, $record, $isTarget);

        return ApiResponse::success([
            'item' => AdminNavFormatter::format($updated),
            'updated_nav_id' => $id,
            'updated_nav_label' => $this->navLabel($updated),
            'is_target' => $isTarget,
            'target_label' => (string)(AdminNavFormatter::format($updated)['target_label'] ?? ''),
        ], 'nav target updated');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->navIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('nav id is required', 422, null, 422);
        }

        $record = $this->loadNavRow($id);
        if ($record === null) {
            return ApiResponse::error('nav not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminNavFormatter::format($record),
            'audit' => $this->buildNavDeleteAudit($record),
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->navIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('nav id is required', 422, null, 422);
        }

        $record = $this->loadNavRow($id);
        if ($record === null) {
            return ApiResponse::error('nav not found', 404, null, 404);
        }

        $audit = $this->buildNavDeleteAudit($record);
        if (empty($audit['can_delete'])) {
            return ApiResponse::error(
                'nav cannot be deleted until the recycle-bin conflict is cleared',
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
            $this->deleteNavRow($id);
        });

        $this->recordAdminNavDelete($request, $audit);

        return ApiResponse::success([
            'deleted_nav_id' => $id,
            'deleted_nav_label' => (string)($audit['nav_label'] ?? ''),
            'audit' => $audit,
        ], 'nav deleted');
    }

    public function batchDeleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $navIds = $this->normalizeNavIds($payload['nav_ids'] ?? $payload['ids'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success([
            'audit' => $this->batchNavDeleteAudit($navIds),
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
            $navIds = $this->normalizeNavIds($payload['nav_ids'] ?? $payload['ids'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $audit = $this->batchNavDeleteAudit($navIds);
        if (empty($audit['can_delete_all'])) {
            return ApiResponse::error(
                'selected navs cannot be batch deleted until the recycle-bin conflicts are cleared',
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
            foreach ((array)($audit['deletable_nav_ids'] ?? []) as $navId) {
                $this->deleteNavRow((int)$navId);
            }
        });

        $this->recordAdminNavBatchDelete($request, $audit);

        return ApiResponse::success([
            'deleted_nav_ids' => array_values(array_map('intval', (array)($audit['deletable_nav_ids'] ?? []))),
            'deleted_count' => (int)(($audit['summary'] ?? [])['deletable_count'] ?? 0),
            'audit' => $audit,
        ], 'nav batch delete completed');
    }

    public function restore(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'recycle');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->navIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('nav id is required', 422, null, 422);
        }

        $record = $this->loadNavRow($id);
        if ($record === null) {
            return ApiResponse::error('nav not found', 404, null, 404);
        }

        if (empty($record['delete_time'])) {
            return ApiResponse::error('nav is already active', 422, null, 422);
        }

        try {
            $this->restoreNavRow($id);
        } catch (\Throwable $exception) {
            return ApiResponse::error('nav restore failed', 500, null, 500);
        }

        $restored = $this->loadNavRow($id);
        if ($restored === null) {
            return ApiResponse::error('restored nav could not be loaded', 500, null, 500);
        }

        $this->recordAdminNavRestore($request, $record);

        return ApiResponse::success([
            'item' => AdminNavFormatter::format($restored),
            'restored_nav_id' => $id,
            'restored_nav_label' => $this->navLabel($record),
        ], 'nav restored');
    }

    public function batchRestore(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'recycle');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $navIds = $this->normalizeNavIds($request->post('nav_ids', RequestPayload::all($request)['nav_ids'] ?? []));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        if ($navIds === []) {
            return ApiResponse::error('at least one nav id is required', 422, null, 422);
        }

        $rows = $this->loadNavRowsByIds($navIds);
        if ($rows === []) {
            return ApiResponse::error('no navigation rows matched the restore request', 422, [
                'restored_nav_ids' => [],
                'already_active_nav_ids' => [],
                'missing_nav_ids' => $navIds,
            ], 422);
        }

        $rowMap = [];
        foreach ($rows as $row) {
            $rowMap[(int)($row['id'] ?? 0)] = $row;
        }

        $restorableRows = [];
        $alreadyActiveNavIds = [];
        $matchedNavIds = [];

        foreach ($navIds as $navId) {
            $row = $rowMap[$navId] ?? null;
            if ($row === null) {
                continue;
            }

            $matchedNavIds[] = $navId;

            if (empty($row['delete_time'])) {
                $alreadyActiveNavIds[] = $navId;
                continue;
            }

            $restorableRows[] = $row;
        }

        $missingNavIds = array_values(array_diff($navIds, $matchedNavIds));

        if ($restorableRows === []) {
            return ApiResponse::error('no recycled navigation rows matched the restore request', 422, [
                'restored_nav_ids' => [],
                'already_active_nav_ids' => $alreadyActiveNavIds,
                'missing_nav_ids' => $missingNavIds,
            ], 422);
        }

        Db::transaction(function () use ($restorableRows): void {
            foreach ($restorableRows as $row) {
                $this->restoreNavRow((int)($row['id'] ?? 0));
            }
        });

        $restoredNavIds = array_values(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $restorableRows
        ));

        $this->recordAdminNavBatchRestore(
            $request,
            $restorableRows,
            $navIds,
            $alreadyActiveNavIds,
            $missingNavIds
        );

        return ApiResponse::success([
            'restored_nav_ids' => $restoredNavIds,
            'restored_count' => count($restorableRows),
            'already_active_nav_ids' => $alreadyActiveNavIds,
            'missing_nav_ids' => $missingNavIds,
        ], 'navs restored');
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'ContentNavs', $authMark);
    }

    private function navQuery(): Builder
    {
        return Db::table('ypay_navs')
            ->select('id', 'name', 'url', 'is_target', 'status', 'create_time', 'sort', 'delete_time');
    }

    private function applyBaseFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('url', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('id', (int)$keyword);
                }
            });
        }

        $isTarget = trim((string)$request->get('is_target', ''));
        if ($isTarget !== '' && in_array($isTarget, ['0', '1'], true)) {
            $query->where('is_target', (int)$isTarget);
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

        if ($status !== '' && in_array($status, ['0', '1'], true)) {
            $query->where('status', (int)$status);
        }
    }

    private function summary(Builder $query): array
    {
        return [
            'enabled_count' => (int)(clone $query)
                ->where('status', 1)
                ->whereNull('delete_time')
                ->count('id'),
            'disabled_count' => (int)(clone $query)
                ->where('status', '<>', 1)
                ->whereNull('delete_time')
                ->count('id'),
            'new_window_count' => (int)(clone $query)
                ->where('is_target', 1)
                ->whereNull('delete_time')
                ->count('id'),
            'same_window_count' => (int)(clone $query)
                ->where('is_target', '<>', 1)
                ->whereNull('delete_time')
                ->count('id'),
            'deleted_count' => (int)(clone $query)
                ->whereNotNull('delete_time')
                ->count('id'),
        ];
    }

    private function loadNavRow(int $id): ?array
    {
        $row = $this->navQuery()
            ->where('id', $id)
            ->first();

        return $row ? (array)$row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadNavRowsByIds(array $navIds): array
    {
        if ($navIds === []) {
            return [];
        }

        return array_map(
            static fn($row): array => (array)$row,
            Db::table('ypay_navs')
                ->select('id', 'name', 'url', 'is_target', 'status', 'sort', 'delete_time')
                ->whereIn('id', $navIds)
                ->get()
                ->toArray()
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadNavRowsBySequence(array $navIds): array
    {
        if ($navIds === []) {
            return [];
        }

        $rows = Db::table('ypay_navs')
            ->select('id', 'name', 'url', 'is_target', 'status', 'sort', 'delete_time')
            ->whereNull('delete_time')
            ->whereIn('id', $navIds)
            ->get()
            ->toArray();

        $rowsById = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $rowsById[(int)($record['id'] ?? 0)] = $record;
        }

        $ordered = [];
        foreach ($navIds as $navId) {
            if (!isset($rowsById[$navId])) {
                continue;
            }

            $ordered[] = $rowsById[$navId];
        }

        return $ordered;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{0: array<int, int>, 1: bool}
     */
    private function resolveNavReorderSortValues(array $rows): array
    {
        $sortValues = array_values(array_map(
            static fn(array $row): int => (int)($row['sort'] ?? 0),
            $rows
        ));

        $seenSorts = [];
        $requiresRebase = false;
        foreach ($sortValues as $sort) {
            if ($sort <= 0 || isset($seenSorts[$sort])) {
                $requiresRebase = true;
                break;
            }

            $seenSorts[$sort] = true;
        }

        if (!$requiresRebase) {
            return [$sortValues, false];
        }

        $positiveSorts = array_values(array_filter(
            $sortValues,
            static fn(int $sort): bool => $sort > 0
        ));
        $baseSort = $positiveSorts === [] ? 1 : min($positiveSorts);

        $rebalanced = [];
        foreach ($rows as $index => $_row) {
            $rebalanced[] = $baseSort + $index;
        }

        return [$rebalanced, true];
    }

    private function normalizeWritePayload(array $payload, ?array $current = null): array
    {
        $name = $this->normalizeRequiredString(
            $payload['name'] ?? ($current['name'] ?? null),
            50,
            'nav name'
        );
        $url = $this->normalizeOptionalString(
            $payload['url'] ?? ($current['url'] ?? null),
            65535,
            'nav url'
        );
        $status = $this->normalizeToggle(
            $payload['status'] ?? ($current['status'] ?? 1),
            'nav status'
        );
        $isTarget = $this->normalizeToggle(
            $payload['is_target'] ?? ($current['is_target'] ?? 0),
            'nav target'
        );
        $sort = $this->normalizeNonNegativeInteger(
            $payload['sort'] ?? ($current['sort'] ?? 0),
            'nav sort'
        );

        return [
            'name' => $name,
            'url' => $url,
            'status' => $status,
            'is_target' => $isTarget,
            'sort' => $sort,
        ];
    }

    private function buildNavDeleteAudit(array $record): array
    {
        $isDeleted = !empty($record['delete_time']);
        $navId = (int)($record['id'] ?? 0);

        $blockingReasons = [];
        if ($isDeleted) {
            $blockingReasons[] = 'This navigation record is already in the recycle bin.';
        }

        return [
            'nav_id' => $navId,
            'nav_label' => $this->navLabel($record),
            'url' => trim((string)($record['url'] ?? '')),
            'status' => (int)($record['status'] ?? 0),
            'is_target' => (int)($record['is_target'] ?? 0),
            'can_delete' => $blockingReasons === [],
            'confirmation_phrase' => $this->navDeleteConfirmationPhrase($navId),
            'blocking_reasons' => $blockingReasons,
            'summary' => [
                'delete_row_count' => $blockingReasons === [] ? 1 : 0,
                'blocked_count' => $blockingReasons === [] ? 0 : 1,
            ],
            'warnings' => [
                'Deleting a navigation record moves the row into the recycle bin first.',
                'You can restore the navigation record later from the recycle view if needed.',
            ],
        ];
    }

    private function batchNavDeleteAudit(array $navIds): array
    {
        $rows = $this->loadNavRowsByIds($navIds);
        $rowMap = [];
        foreach ($rows as $row) {
            $rowMap[(int)($row['id'] ?? 0)] = $row;
        }

        $items = [];
        $deletableNavIds = [];
        $blockedNavIds = [];
        $missingNavIds = [];
        $deleteRowCount = 0;

        foreach ($navIds as $navId) {
            $row = $rowMap[$navId] ?? null;
            if ($row === null) {
                $missingNavIds[] = $navId;
                $items[] = [
                    'nav_id' => $navId,
                    'nav_label' => '',
                    'url' => '',
                    'exists' => false,
                    'can_delete' => false,
                    'blocking_reasons' => ['This navigation record was not found in ypay_navs.'],
                    'summary' => [
                        'delete_row_count' => 0,
                        'blocked_count' => 1,
                    ],
                    'warnings' => ['Remove missing navigation records from the selection before retrying the batch delete.'],
                ];
                continue;
            }

            $audit = $this->buildNavDeleteAudit($row);
            $items[] = [
                'nav_id' => $navId,
                'nav_label' => (string)($audit['nav_label'] ?? ''),
                'url' => (string)($audit['url'] ?? ''),
                'exists' => true,
                'can_delete' => !empty($audit['can_delete']),
                'blocking_reasons' => array_values(array_map('strval', (array)($audit['blocking_reasons'] ?? []))),
                'summary' => (array)($audit['summary'] ?? []),
                'warnings' => array_values(array_map('strval', (array)($audit['warnings'] ?? []))),
            ];

            $summary = (array)($audit['summary'] ?? []);
            $deleteRowCount += (int)($summary['delete_row_count'] ?? 0);

            if (!empty($audit['can_delete'])) {
                $deletableNavIds[] = $navId;
                continue;
            }

            $blockedNavIds[] = $navId;
        }

        $warnings = [];
        if ($missingNavIds !== []) {
            $warnings[] = 'Some selected navigation records no longer exist and must be removed from the batch selection.';
        }
        if ($blockedNavIds !== []) {
            $warnings[] = 'At least one selected navigation record is already in the recycle bin, so the batch delete is paused until the selection is cleaned up.';
        }
        if ($deletableNavIds !== []) {
            $warnings[] = 'Batch delete moves the selected navigation records into the recycle bin after one shared confirmation phrase is accepted.';
        }

        return [
            'requested_nav_ids' => $navIds,
            'deletable_nav_ids' => $deletableNavIds,
            'blocked_nav_ids' => $blockedNavIds,
            'missing_nav_ids' => $missingNavIds,
            'confirmation_phrase' => $this->batchNavDeleteConfirmationPhrase($navIds),
            'can_delete_all' => $navIds !== [] && $blockedNavIds === [] && $missingNavIds === [],
            'items' => $items,
            'summary' => [
                'requested_count' => count($navIds),
                'existing_count' => count($navIds) - count($missingNavIds),
                'deletable_count' => count($deletableNavIds),
                'blocked_count' => count($blockedNavIds),
                'missing_count' => count($missingNavIds),
                'delete_row_count' => $deleteRowCount,
            ],
            'warnings' => $warnings,
        ];
    }

    private function deleteNavRow(int $id): void
    {
        Db::table('ypay_navs')
            ->where('id', $id)
            ->update(['delete_time' => date('Y-m-d H:i:s')]);
    }

    private function restoreNavRow(int $id): void
    {
        Db::table('ypay_navs')
            ->where('id', $id)
            ->update(['delete_time' => null]);
    }

    /**
     * @return array<int, int>
     */
    private function normalizeNavIds(mixed $value, int $maxCount = 100): array
    {
        $items = [];

        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $items = preg_split('/\s*,\s*/', trim($value)) ?: [];
        } elseif (is_numeric($value)) {
            $items = [$value];
        }

        $navIds = [];
        foreach ($items as $item) {
            if (is_bool($item) || is_array($item) || is_object($item)) {
                continue;
            }

            $normalized = trim((string)$item);
            if ($normalized === '' || !ctype_digit($normalized)) {
                continue;
            }

            $navId = (int)$normalized;
            if ($navId > 0) {
                $navIds[$navId] = $navId;
            }
        }

        $navIds = array_values($navIds);
        sort($navIds);

        if ($navIds === []) {
            throw new \InvalidArgumentException('nav ids are required');
        }

        if (count($navIds) > $maxCount) {
            throw new \InvalidArgumentException('too many navigation rows were selected for one batch action');
        }

        return $navIds;
    }

    /**
     * @return array<int, int>
     */
    private function normalizeOrderedNavIds(mixed $value, int $maxCount = 100): array
    {
        $items = [];

        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value) && trim((string)$value) !== '') {
            $items = preg_split('/\s*,\s*/', trim((string)$value)) ?: [];
        } elseif (is_numeric($value)) {
            $items = [$value];
        }

        $orderedIds = [];
        foreach ($items as $item) {
            if (is_bool($item) || is_array($item) || is_object($item)) {
                continue;
            }

            $normalized = trim((string)$item);
            if ($normalized === '' || !ctype_digit($normalized)) {
                continue;
            }

            $navId = (int)$normalized;
            if ($navId <= 0 || isset($orderedIds[$navId])) {
                continue;
            }

            $orderedIds[$navId] = $navId;
        }

        $navIds = array_values($orderedIds);

        if ($navIds === []) {
            throw new \InvalidArgumentException('nav ids are required');
        }

        if (count($navIds) > $maxCount) {
            throw new \InvalidArgumentException('too many navigation rows were selected for one reorder action');
        }

        return $navIds;
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

        if (strlen($normalized) > $maxLength) {
            throw new \InvalidArgumentException($field . ' is too long');
        }

        return $normalized;
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

        if (strlen($normalized) > $maxLength) {
            throw new \InvalidArgumentException($field . ' is too long');
        }

        return $normalized;
    }

    private function normalizeNonNegativeInteger(mixed $value, string $field): int
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($field . ' must be a scalar');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            return 0;
        }

        if (!preg_match('/^\d+$/', $normalized)) {
            throw new \InvalidArgumentException($field . ' must be a non-negative integer');
        }

        return (int)$normalized;
    }

    private function normalizeSequenceIndex(mixed $value, string $field): int
    {
        return $this->normalizeNonNegativeInteger($value, $field);
    }

    private function normalizeToggle(mixed $value, string $field): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_numeric($value)) {
            $toggle = (int)$value;
            if (in_array($toggle, [0, 1], true)) {
                return $toggle;
            }
        }

        $normalized = strtolower(trim((string)$value));

        return match ($normalized) {
            '1', 'true', 'yes', 'on', 'enable', 'enabled' => 1,
            '0', 'false', 'no', 'off', 'disable', 'disabled' => 0,
            default => throw new \InvalidArgumentException($field . ' must be 0 or 1'),
        };
    }

    private function navIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function navLabel(array $record): string
    {
        $name = trim((string)($record['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $url = trim((string)($record['url'] ?? ''));
        if ($url !== '') {
            return $url;
        }

        return 'nav #' . (int)($record['id'] ?? 0);
    }

    private function navDeleteConfirmationPhrase(int $id): string
    {
        return 'DELETE NAV ' . $id;
    }

    private function batchNavDeleteConfirmationPhrase(array $navIds): string
    {
        return sprintf(
            'DELETE NAV BATCH %d-%s',
            count($navIds),
            strtoupper(substr(md5(implode(',', $navIds)), 0, 6))
        );
    }

    private function recordAdminNavCreate(Request $request, array $record): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $navId = (int)($record['id'] ?? 0);
        $navLabel = $this->truncateLogText($this->navLabel($record), 120);
        $url = $this->truncateLogText((string)($record['url'] ?? ''), 160);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/navs/create',
            'desc' => sprintf(
                'nav create nav_id=%d label="%s" status=%d target=%d sort=%d url="%s"',
                $navId,
                $navLabel,
                (int)($record['status'] ?? 0),
                (int)($record['is_target'] ?? 0),
                (int)($record['sort'] ?? 0),
                $url
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminNavUpdate(Request $request, array $before, array $after): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $navId = (int)($after['id'] ?? 0);
        $navLabel = $this->truncateLogText($this->navLabel($after), 120);
        $url = $this->truncateLogText((string)($after['url'] ?? ''), 160);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/navs/' . $navId . '/update',
            'desc' => sprintf(
                'nav update nav_id=%d label="%s" from_status=%d to_status=%d from_target=%d to_target=%d from_sort=%d to_sort=%d url_changed=%d url="%s"',
                $navId,
                $navLabel,
                (int)($before['status'] ?? 0),
                (int)($after['status'] ?? 0),
                (int)($before['is_target'] ?? 0),
                (int)($after['is_target'] ?? 0),
                (int)($before['sort'] ?? 0),
                (int)($after['sort'] ?? 0),
                trim((string)($before['url'] ?? '')) === trim((string)($after['url'] ?? '')) ? 0 : 1,
                $url
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminNavStatus(Request $request, array $record, int $status): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $navId = (int)($record['id'] ?? 0);
        $navLabel = $this->truncateLogText($this->navLabel($record), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/navs/' . $navId . '/status',
            'desc' => sprintf(
                'nav status nav_id=%d label="%s" from_status=%d to_status=%d',
                $navId,
                $navLabel,
                (int)($record['status'] ?? 0),
                $status
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminNavTarget(Request $request, array $record, int $isTarget): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $navId = (int)($record['id'] ?? 0);
        $navLabel = $this->truncateLogText($this->navLabel($record), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/navs/' . $navId . '/target',
            'desc' => sprintf(
                'nav target nav_id=%d label="%s" from_target=%d to_target=%d',
                $navId,
                $navLabel,
                (int)($record['is_target'] ?? 0),
                $isTarget
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function recordAdminNavReorder(
        Request $request,
        array $rows,
        int $fromIndex,
        int $toIndex,
        bool $sortValuesRebased
    ): void {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $orderedNavIds = implode(',', array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $rows
        ));
        $orderedLabels = implode(',', array_map(
            fn(array $row): string => $this->navLabel($row),
            $rows
        ));
        $movedNavId = (int)($rows[$toIndex]['id'] ?? 0);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/navs/reorder',
            'desc' => sprintf(
                'nav reorder moved_nav_id=%d from_index=%d to_index=%d updated=%d sort_values_rebased=%d navs="%s" labels="%s"',
                $movedNavId,
                $fromIndex,
                $toIndex,
                count($rows),
                $sortValuesRebased ? 1 : 0,
                $this->truncateLogText($orderedNavIds, 255),
                $this->truncateLogText($orderedLabels, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminNavDelete(Request $request, array $audit): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $navId = (int)($audit['nav_id'] ?? 0);
        $navLabel = $this->truncateLogText((string)($audit['nav_label'] ?? ''), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/navs/' . $navId . '/delete',
            'desc' => sprintf(
                'nav delete nav_id=%d label="%s" delete_rows=%d',
                $navId,
                $navLabel,
                (int)($summary['delete_row_count'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminNavBatchDelete(Request $request, array $audit): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $navIds = implode(',', array_map('intval', (array)($audit['deletable_nav_ids'] ?? [])));
        $navLabels = implode(',', array_map(
            static function (array $item): string {
                $label = trim((string)($item['nav_label'] ?? ''));
                $navId = (int)($item['nav_id'] ?? 0);

                return $label !== '' ? $label : ('nav #' . $navId);
            },
            array_values(array_filter(
                (array)($audit['items'] ?? []),
                static fn(array $item): bool => !empty($item['can_delete'])
            ))
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/navs/batch-delete',
            'desc' => sprintf(
                'nav batch delete requested=%d deleted=%d blocked=%d missing=%d delete_rows=%d navs="%s" labels="%s"',
                (int)($summary['requested_count'] ?? 0),
                (int)($summary['deletable_count'] ?? 0),
                (int)($summary['blocked_count'] ?? 0),
                (int)($summary['missing_count'] ?? 0),
                (int)($summary['delete_row_count'] ?? 0),
                $this->truncateLogText($navIds, 255),
                $this->truncateLogText($navLabels, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminNavRestore(Request $request, array $record): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $navId = (int)($record['id'] ?? 0);
        $navLabel = $this->truncateLogText($this->navLabel($record), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/navs/' . $navId . '/restore',
            'desc' => sprintf(
                'nav restore nav_id=%d label="%s"',
                $navId,
                $navLabel
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminNavBatchRestore(
        Request $request,
        array $restorableRows,
        array $requestedNavIds,
        array $alreadyActiveNavIds,
        array $missingNavIds
    ): void {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $restoredNavIds = implode(',', array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $restorableRows
        ));
        $restoredLabels = implode(',', array_map(
            fn(array $row): string => $this->navLabel($row),
            $restorableRows
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/navs/batch-restore',
            'desc' => sprintf(
                'nav batch restore requested=%d restored=%d active=%d missing=%d navs="%s" labels="%s"',
                count($requestedNavIds),
                count($restorableRows),
                count($alreadyActiveNavIds),
                count($missingNavIds),
                $this->truncateLogText($restoredNavIds, 255),
                $this->truncateLogText($restoredLabels, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

}
