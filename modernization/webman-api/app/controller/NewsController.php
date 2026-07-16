<?php

namespace app\controller;

use app\support\AdminNewsFormatter;
use app\support\AdminRouteAuthorization;
use app\support\ApiResponse;
use app\support\RequestPayload;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class NewsController
{
    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $summaryQuery = $this->newsQuery();
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
            static fn($row): array => AdminNewsFormatter::format((array)$row),
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
        $id = $this->newsIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('news id is required', 422, null, 422);
        }

        $row = $this->loadNewsRow($id);
        if ($row === null) {
            return ApiResponse::error('news not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminNewsFormatter::format($row),
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
        $newsId = (int)Db::table('ypay_news')->insertGetId([
            'type' => $payload['type'],
            'title' => $payload['title'],
            'color' => $payload['color'],
            'content' => $payload['content'],
            'status' => $payload['status'],
            'create_time' => $now,
            'update_time' => $now,
            'delete_time' => null,
        ]);

        $created = $this->loadNewsRow($newsId);
        if ($created === null) {
            return ApiResponse::error('created news could not be loaded', 500, null, 500);
        }

        $this->recordAdminNewsCreate($request, $created);

        return ApiResponse::success([
            'item' => AdminNewsFormatter::format($created),
            'created_news_id' => $newsId,
            'created_news_label' => $this->newsLabel($created),
        ], 'news created');
    }

    public function update(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->newsIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('news id is required', 422, null, 422);
        }

        $record = $this->loadNewsRow($id);
        if ($record === null) {
            return ApiResponse::error('news not found', 404, null, 404);
        }

        if (!empty($record['delete_time'])) {
            return ApiResponse::error('recycled news must be restored before editing', 422, null, 422);
        }

        try {
            $payload = $this->normalizeWritePayload(RequestPayload::all($request), $record);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table('ypay_news')
            ->where('id', $id)
            ->update([
                'type' => $payload['type'],
                'title' => $payload['title'],
                'color' => $payload['color'],
                'content' => $payload['content'],
                'status' => $payload['status'],
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        $updated = $this->loadNewsRow($id);
        if ($updated === null) {
            return ApiResponse::error('updated news could not be loaded', 500, null, 500);
        }

        $this->recordAdminNewsUpdate($request, $record, $updated);

        return ApiResponse::success([
            'item' => AdminNewsFormatter::format($updated),
            'updated_news_id' => $id,
            'updated_news_label' => $this->newsLabel($updated),
        ], 'news updated');
    }

    public function status(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'status');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->newsIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('news id is required', 422, null, 422);
        }

        $record = $this->loadNewsRow($id);
        if ($record === null) {
            return ApiResponse::error('news not found', 404, null, 404);
        }

        if (!empty($record['delete_time'])) {
            return ApiResponse::error('recycled news must be restored before changing status', 422, null, 422);
        }

        $payload = RequestPayload::all($request);

        try {
            $status = $this->normalizeStatus($payload['status'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table('ypay_news')
            ->where('id', $id)
            ->update([
                'status' => $status,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        $updated = $this->loadNewsRow($id);
        if ($updated === null) {
            return ApiResponse::error('updated news could not be loaded', 500, null, 500);
        }

        $this->recordAdminNewsStatus($request, $record, $status);

        return ApiResponse::success([
            'item' => AdminNewsFormatter::format($updated),
            'updated_news_id' => $id,
            'updated_news_label' => $this->newsLabel($updated),
            'status' => $status,
            'status_label' => (string)(AdminNewsFormatter::format($updated)['status_label'] ?? ''),
        ], 'news status updated');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->newsIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('news id is required', 422, null, 422);
        }

        $record = $this->loadNewsRow($id);
        if ($record === null) {
            return ApiResponse::error('news not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminNewsFormatter::format($record),
            'audit' => $this->buildNewsDeleteAudit($record),
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->newsIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('news id is required', 422, null, 422);
        }

        $record = $this->loadNewsRow($id);
        if ($record === null) {
            return ApiResponse::error('news not found', 404, null, 404);
        }

        $audit = $this->buildNewsDeleteAudit($record);
        if (empty($audit['can_delete'])) {
            return ApiResponse::error(
                'news cannot be deleted until the recycle-bin conflict is cleared',
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
            $this->deleteNewsRow($id);
        });

        $this->recordAdminNewsDelete($request, $audit);

        return ApiResponse::success([
            'deleted_news_id' => $id,
            'deleted_news_label' => (string)($audit['news_label'] ?? ''),
            'audit' => $audit,
        ], 'news deleted');
    }

    public function batchDeleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $newsIds = $this->normalizeNewsIds($payload['news_ids'] ?? $payload['ids'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success([
            'audit' => $this->batchNewsDeleteAudit($newsIds),
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
            $newsIds = $this->normalizeNewsIds($payload['news_ids'] ?? $payload['ids'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $audit = $this->batchNewsDeleteAudit($newsIds);
        if (empty($audit['can_delete_all'])) {
            return ApiResponse::error(
                'selected news records cannot be batch deleted until the recycle-bin conflicts are cleared',
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
            foreach ((array)($audit['deletable_news_ids'] ?? []) as $newsId) {
                $this->deleteNewsRow((int)$newsId);
            }
        });

        $this->recordAdminNewsBatchDelete($request, $audit);

        return ApiResponse::success([
            'deleted_news_ids' => array_values(array_map('intval', (array)($audit['deletable_news_ids'] ?? []))),
            'deleted_count' => (int)(($audit['summary'] ?? [])['deletable_count'] ?? 0),
            'audit' => $audit,
        ], 'news batch delete completed');
    }

    public function restore(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'recycle');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->newsIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('news id is required', 422, null, 422);
        }

        $record = $this->loadNewsRow($id);
        if ($record === null) {
            return ApiResponse::error('news not found', 404, null, 404);
        }

        if (empty($record['delete_time'])) {
            return ApiResponse::error('news is already active', 422, null, 422);
        }

        try {
            $this->restoreNewsRow($id);
        } catch (\Throwable $exception) {
            return ApiResponse::error('news restore failed', 500, null, 500);
        }

        $restored = $this->loadNewsRow($id);
        if ($restored === null) {
            return ApiResponse::error('restored news could not be loaded', 500, null, 500);
        }

        $this->recordAdminNewsRestore($request, $record);

        return ApiResponse::success([
            'item' => AdminNewsFormatter::format($restored),
            'restored_news_id' => $id,
            'restored_news_label' => $this->newsLabel($record),
        ], 'news restored');
    }

    public function batchRestore(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'recycle');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $newsIds = $this->normalizeNewsIds($request->post('news_ids', RequestPayload::all($request)['news_ids'] ?? []));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        if ($newsIds === []) {
            return ApiResponse::error('at least one news id is required', 422, null, 422);
        }

        $rows = $this->loadNewsRowsByIds($newsIds);
        if ($rows === []) {
            return ApiResponse::error('no news rows matched the restore request', 422, [
                'restored_news_ids' => [],
                'already_active_news_ids' => [],
                'missing_news_ids' => $newsIds,
            ], 422);
        }

        $rowMap = [];
        foreach ($rows as $row) {
            $rowMap[(int)($row['id'] ?? 0)] = $row;
        }

        $restorableRows = [];
        $alreadyActiveNewsIds = [];
        $matchedNewsIds = [];

        foreach ($newsIds as $newsId) {
            $row = $rowMap[$newsId] ?? null;
            if ($row === null) {
                continue;
            }

            $matchedNewsIds[] = $newsId;

            if (empty($row['delete_time'])) {
                $alreadyActiveNewsIds[] = $newsId;
                continue;
            }

            $restorableRows[] = $row;
        }

        $missingNewsIds = array_values(array_diff($newsIds, $matchedNewsIds));

        if ($restorableRows === []) {
            return ApiResponse::error('no recycled news rows matched the restore request', 422, [
                'restored_news_ids' => [],
                'already_active_news_ids' => $alreadyActiveNewsIds,
                'missing_news_ids' => $missingNewsIds,
            ], 422);
        }

        Db::transaction(function () use ($restorableRows): void {
            foreach ($restorableRows as $row) {
                $this->restoreNewsRow((int)($row['id'] ?? 0));
            }
        });

        $restoredNewsIds = array_values(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $restorableRows
        ));

        $this->recordAdminNewsBatchRestore(
            $request,
            $restorableRows,
            $newsIds,
            $alreadyActiveNewsIds,
            $missingNewsIds
        );

        return ApiResponse::success([
            'restored_news_ids' => $restoredNewsIds,
            'restored_count' => count($restorableRows),
            'already_active_news_ids' => $alreadyActiveNewsIds,
            'missing_news_ids' => $missingNewsIds,
        ], 'news restored');
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'ContentNews', $authMark);
    }

    private function newsQuery(): Builder
    {
        return Db::table('ypay_news')
            ->select(
                'id',
                'type',
                'title',
                'color',
                'content',
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
                    ->where('title', 'like', '%' . $keyword . '%')
                    ->orWhere('content', 'like', '%' . $keyword . '%')
                    ->orWhere('color', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('id', (int)$keyword);
                }
            });
        }

        $type = trim((string)$request->get('type', ''));
        if ($type !== '' && in_array($type, ['1', '2', '3'], true)) {
            $query->where('type', (int)$type);
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

        if ($status !== '' && in_array($status, ['1', '2'], true)) {
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
            'platform_count' => (int)(clone $query)
                ->where('type', 1)
                ->whereNull('delete_time')
                ->count('id'),
            'industry_count' => (int)(clone $query)
                ->where('type', 2)
                ->whereNull('delete_time')
                ->count('id'),
            'faq_count' => (int)(clone $query)
                ->where('type', 3)
                ->whereNull('delete_time')
                ->count('id'),
            'content_count' => (int)(clone $query)
                ->whereNotNull('content')
                ->where('content', '<>', '')
                ->whereNull('delete_time')
                ->count('id'),
            'deleted_count' => (int)(clone $query)
                ->whereNotNull('delete_time')
                ->count('id'),
        ];
    }

    private function loadNewsRow(int $id): ?array
    {
        $row = $this->newsQuery()
            ->where('id', $id)
            ->first();

        return $row ? (array)$row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadNewsRowsByIds(array $newsIds): array
    {
        if ($newsIds === []) {
            return [];
        }

        return array_map(
            static fn($row): array => (array)$row,
            Db::table('ypay_news')
                ->select('id', 'type', 'title', 'status', 'delete_time')
                ->whereIn('id', $newsIds)
                ->get()
                ->toArray()
        );
    }

    private function normalizeWritePayload(array $payload, ?array $current = null): array
    {
        $type = $this->normalizeType($payload['type'] ?? ($current['type'] ?? null));
        $title = $this->normalizeOptionalString($payload['title'] ?? ($current['title'] ?? null), 2500, 'news title');
        $color = $this->normalizeOptionalString($payload['color'] ?? ($current['color'] ?? null), 50, 'news color');
        $content = $this->normalizeNullableText($payload['content'] ?? ($current['content'] ?? null), 'news content');
        $status = $this->normalizeStatus($payload['status'] ?? ($current['status'] ?? 1));

        return [
            'type' => $type,
            'title' => $title === '' ? null : $title,
            'color' => $color === '' ? null : $color,
            'content' => $content,
            'status' => $status,
        ];
    }

    private function normalizeType(mixed $value): int
    {
        if (is_numeric($value)) {
            $type = (int)$value;
            if (in_array($type, [1, 2, 3], true)) {
                return $type;
            }
        }

        throw new \InvalidArgumentException('news type must be 1, 2, or 3');
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
            '1', 'true', 'yes', 'on', 'enable', 'enabled' => 1,
            '0', '2', 'false', 'no', 'off', 'disable', 'disabled' => 2,
            default => throw new \InvalidArgumentException('news status must be 1 or 2'),
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

    private function normalizeNullableText(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($field . ' must be a scalar');
        }

        $normalized = trim((string)$value);
        return $normalized === '' ? null : $normalized;
    }

    private function buildNewsDeleteAudit(array $record): array
    {
        $isDeleted = !empty($record['delete_time']);
        $newsId = (int)($record['id'] ?? 0);

        $blockingReasons = [];
        if ($isDeleted) {
            $blockingReasons[] = 'This announcement is already in the recycle bin.';
        }

        return [
            'news_id' => $newsId,
            'news_label' => $this->newsLabel($record),
            'type' => (int)($record['type'] ?? 0),
            'status' => (int)($record['status'] ?? 0),
            'can_delete' => $blockingReasons === [],
            'confirmation_phrase' => $this->newsDeleteConfirmationPhrase($newsId),
            'blocking_reasons' => $blockingReasons,
            'summary' => [
                'delete_row_count' => $blockingReasons === [] ? 1 : 0,
                'blocked_count' => $blockingReasons === [] ? 0 : 1,
            ],
            'warnings' => [
                'Deleting an announcement moves the row into the recycle bin first.',
                'You can restore the announcement later from the recycle view if needed.',
            ],
        ];
    }

    private function batchNewsDeleteAudit(array $newsIds): array
    {
        $rows = $this->loadNewsRowsByIds($newsIds);
        $rowMap = [];
        foreach ($rows as $row) {
            $rowMap[(int)($row['id'] ?? 0)] = $row;
        }

        $items = [];
        $deletableNewsIds = [];
        $blockedNewsIds = [];
        $missingNewsIds = [];
        $deleteRowCount = 0;

        foreach ($newsIds as $newsId) {
            $row = $rowMap[$newsId] ?? null;
            if ($row === null) {
                $missingNewsIds[] = $newsId;
                $items[] = [
                    'news_id' => $newsId,
                    'news_label' => '',
                    'type' => 0,
                    'exists' => false,
                    'can_delete' => false,
                    'blocking_reasons' => ['This announcement was not found in ypay_news.'],
                    'summary' => [
                        'delete_row_count' => 0,
                        'blocked_count' => 1,
                    ],
                    'warnings' => ['Remove missing announcements from the selection before retrying the batch delete.'],
                ];
                continue;
            }

            $audit = $this->buildNewsDeleteAudit($row);
            $items[] = [
                'news_id' => $newsId,
                'news_label' => (string)($audit['news_label'] ?? ''),
                'type' => (int)($audit['type'] ?? 0),
                'exists' => true,
                'can_delete' => !empty($audit['can_delete']),
                'blocking_reasons' => array_values(array_map('strval', (array)($audit['blocking_reasons'] ?? []))),
                'summary' => (array)($audit['summary'] ?? []),
                'warnings' => array_values(array_map('strval', (array)($audit['warnings'] ?? []))),
            ];

            $summary = (array)($audit['summary'] ?? []);
            $deleteRowCount += (int)($summary['delete_row_count'] ?? 0);

            if (!empty($audit['can_delete'])) {
                $deletableNewsIds[] = $newsId;
                continue;
            }

            $blockedNewsIds[] = $newsId;
        }

        $warnings = [];
        if ($missingNewsIds !== []) {
            $warnings[] = 'Some selected announcements no longer exist and must be removed from the batch selection.';
        }
        if ($blockedNewsIds !== []) {
            $warnings[] = 'At least one selected announcement is already in the recycle bin, so the batch delete is paused until the selection is cleaned up.';
        }
        if ($deletableNewsIds !== []) {
            $warnings[] = 'Batch delete moves the selected announcements into the recycle bin after one shared confirmation phrase is accepted.';
        }

        return [
            'requested_news_ids' => $newsIds,
            'deletable_news_ids' => $deletableNewsIds,
            'blocked_news_ids' => $blockedNewsIds,
            'missing_news_ids' => $missingNewsIds,
            'confirmation_phrase' => $this->batchNewsDeleteConfirmationPhrase($newsIds),
            'can_delete_all' => $newsIds !== [] && $blockedNewsIds === [] && $missingNewsIds === [],
            'items' => $items,
            'summary' => [
                'requested_count' => count($newsIds),
                'existing_count' => count($newsIds) - count($missingNewsIds),
                'deletable_count' => count($deletableNewsIds),
                'blocked_count' => count($blockedNewsIds),
                'missing_count' => count($missingNewsIds),
                'delete_row_count' => $deleteRowCount,
            ],
            'warnings' => $warnings,
        ];
    }

    private function deleteNewsRow(int $id): void
    {
        Db::table('ypay_news')
            ->where('id', $id)
            ->update(['delete_time' => date('Y-m-d H:i:s')]);
    }

    private function restoreNewsRow(int $id): void
    {
        Db::table('ypay_news')
            ->where('id', $id)
            ->update(['delete_time' => null]);
    }

    /**
     * @return array<int, int>
     */
    private function normalizeNewsIds(mixed $value, int $maxCount = 100): array
    {
        $items = [];

        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $items = preg_split('/\s*,\s*/', trim($value)) ?: [];
        } elseif (is_numeric($value)) {
            $items = [$value];
        }

        $newsIds = [];
        foreach ($items as $item) {
            if (is_bool($item) || is_array($item) || is_object($item)) {
                continue;
            }

            $normalized = trim((string)$item);
            if ($normalized === '' || !ctype_digit($normalized)) {
                continue;
            }

            $newsId = (int)$normalized;
            if ($newsId > 0) {
                $newsIds[$newsId] = $newsId;
            }
        }

        $newsIds = array_values($newsIds);
        sort($newsIds);

        if ($newsIds === []) {
            throw new \InvalidArgumentException('news ids are required');
        }

        if (count($newsIds) > $maxCount) {
            throw new \InvalidArgumentException('too many news rows were selected for one batch action');
        }

        return $newsIds;
    }

    private function newsIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function newsLabel(array $record): string
    {
        $title = trim((string)($record['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        return 'news #' . (int)($record['id'] ?? 0);
    }

    private function newsDeleteConfirmationPhrase(int $id): string
    {
        return 'DELETE NEWS ' . $id;
    }

    private function batchNewsDeleteConfirmationPhrase(array $newsIds): string
    {
        return sprintf(
            'DELETE NEWS BATCH %d-%s',
            count($newsIds),
            strtoupper(substr(md5(implode(',', $newsIds)), 0, 6))
        );
    }

    private function recordAdminNewsCreate(Request $request, array $record): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $newsId = (int)($record['id'] ?? 0);
        $newsLabel = $this->truncateLogText($this->newsLabel($record), 120);
        $color = $this->truncateLogText((string)($record['color'] ?? ''), 50);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/news/create',
            'desc' => sprintf(
                'news create news_id=%d title="%s" type=%d status=%d color="%s" has_content=%d',
                $newsId,
                $newsLabel,
                (int)($record['type'] ?? 0),
                (int)($record['status'] ?? 0),
                $color,
                trim((string)($record['content'] ?? '')) === '' ? 0 : 1
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminNewsUpdate(Request $request, array $before, array $after): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $newsId = (int)($after['id'] ?? 0);
        $newsLabel = $this->truncateLogText($this->newsLabel($after), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/news/' . $newsId . '/update',
            'desc' => sprintf(
                'news update news_id=%d title="%s" from_type=%d to_type=%d from_status=%d to_status=%d title_changed=%d color_changed=%d content_changed=%d',
                $newsId,
                $newsLabel,
                (int)($before['type'] ?? 0),
                (int)($after['type'] ?? 0),
                (int)($before['status'] ?? 0),
                (int)($after['status'] ?? 0),
                trim((string)($before['title'] ?? '')) === trim((string)($after['title'] ?? '')) ? 0 : 1,
                trim((string)($before['color'] ?? '')) === trim((string)($after['color'] ?? '')) ? 0 : 1,
                trim((string)($before['content'] ?? '')) === trim((string)($after['content'] ?? '')) ? 0 : 1
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminNewsStatus(Request $request, array $record, int $status): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $newsId = (int)($record['id'] ?? 0);
        $newsLabel = $this->truncateLogText($this->newsLabel($record), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/news/' . $newsId . '/status',
            'desc' => sprintf(
                'news status news_id=%d title="%s" from_status=%d to_status=%d',
                $newsId,
                $newsLabel,
                (int)($record['status'] ?? 0),
                $status
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminNewsRestore(Request $request, array $record): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $newsId = (int)($record['id'] ?? 0);
        $newsLabel = $this->truncateLogText($this->newsLabel($record), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/news/' . $newsId . '/restore',
            'desc' => sprintf(
                'news restore news_id=%d title="%s"',
                $newsId,
                $newsLabel
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminNewsBatchRestore(
        Request $request,
        array $restorableRows,
        array $requestedNewsIds,
        array $alreadyActiveNewsIds,
        array $missingNewsIds
    ): void {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $restoredNewsIds = implode(',', array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $restorableRows
        ));
        $restoredLabels = implode(',', array_map(
            fn(array $row): string => $this->newsLabel($row),
            $restorableRows
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/news/batch-restore',
            'desc' => sprintf(
                'news batch restore requested=%d restored=%d active=%d missing=%d news="%s" titles="%s"',
                count($requestedNewsIds),
                count($restorableRows),
                count($alreadyActiveNewsIds),
                count($missingNewsIds),
                $this->truncateLogText($restoredNewsIds, 255),
                $this->truncateLogText($restoredLabels, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminNewsDelete(Request $request, array $audit): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $newsId = (int)($audit['news_id'] ?? 0);
        $newsLabel = $this->truncateLogText((string)($audit['news_label'] ?? ''), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/news/' . $newsId . '/delete',
            'desc' => sprintf(
                'news delete news_id=%d title="%s" delete_rows=%d',
                $newsId,
                $newsLabel,
                (int)($summary['delete_row_count'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminNewsBatchDelete(Request $request, array $audit): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $newsIds = implode(',', array_map('intval', (array)($audit['deletable_news_ids'] ?? [])));
        $newsLabels = implode(',', array_map(
            static function (array $item): string {
                $label = trim((string)($item['news_label'] ?? ''));
                $newsId = (int)($item['news_id'] ?? 0);

                return $label !== '' ? $label : ('news #' . $newsId);
            },
            array_values(array_filter(
                (array)($audit['items'] ?? []),
                static fn(array $item): bool => !empty($item['can_delete'])
            ))
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/news/batch-delete',
            'desc' => sprintf(
                'news batch delete requested=%d deleted=%d blocked=%d missing=%d delete_rows=%d news="%s" titles="%s"',
                (int)($summary['requested_count'] ?? 0),
                (int)($summary['deletable_count'] ?? 0),
                (int)($summary['blocked_count'] ?? 0),
                (int)($summary['missing_count'] ?? 0),
                (int)($summary['delete_row_count'] ?? 0),
                $this->truncateLogText($newsIds, 255),
                $this->truncateLogText($newsLabels, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
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
