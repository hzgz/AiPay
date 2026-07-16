<?php

namespace app\controller;

use app\support\AdminRouteAuthorization;
use app\support\AdminTicketCategoryFormatter;
use app\support\ApiResponse;
use app\support\RequestPayload;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class TicketCategoryController
{
    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $query = $this->categoryQuery();
        $this->applyFilters($query, $request);

        $allIds = array_map('intval', (clone $query)->pluck('id')->toArray());
        $allStats = $this->statsForCategoryIds($allIds);
        $summary = $this->summary($allIds, $allStats, clone $query);
        $total = (int)(clone $query)->count('id');

        $rows = array_map(
            static fn($row): array => (array)$row,
            $query
                ->orderByRaw("CAST(COALESCE(NULLIF(sort, ''), '0') AS UNSIGNED)")
                ->orderBy('id')
                ->offset(($current - 1) * $size)
                ->limit($size)
                ->get()
                ->toArray()
        );

        return ApiResponse::success([
            'records' => $this->formatCategoryRows($rows),
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => $summary,
        ]);
    }

    public function show(Request $request): Response
    {
        $id = $this->categoryIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('ticket category id is required', 422, null, 422);
        }

        $row = $this->loadCategoryRow($id);
        if ($row === null) {
            return ApiResponse::error('ticket category not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminTicketCategoryFormatter::format($row),
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
        $categoryId = (int)Db::table('ypay_ticket_category')->insertGetId([
            'name' => $payload['name'],
            'sort' => $payload['sort'],
            'status' => $payload['status'],
            'create_time' => $now,
            'update_time' => $now,
        ]);

        $created = $this->loadCategoryRow($categoryId);
        if ($created === null) {
            return ApiResponse::error('created ticket category could not be loaded', 500, null, 500);
        }

        $this->recordAdminCategoryCreate($request, $created);

        return ApiResponse::success([
            'item' => AdminTicketCategoryFormatter::format($created),
            'created_category_id' => $categoryId,
            'created_category_label' => $this->categoryLabel($created),
        ], 'ticket category created');
    }

    public function update(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->categoryIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('ticket category id is required', 422, null, 422);
        }

        $record = $this->loadCategoryRow($id);
        if ($record === null) {
            return ApiResponse::error('ticket category not found', 404, null, 404);
        }

        try {
            $payload = $this->normalizeWritePayload(RequestPayload::all($request), $record);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table('ypay_ticket_category')
            ->where('id', $id)
            ->update([
                'name' => $payload['name'],
                'sort' => $payload['sort'],
                'status' => $payload['status'],
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        $updated = $this->loadCategoryRow($id);
        if ($updated === null) {
            return ApiResponse::error('updated ticket category could not be loaded', 500, null, 500);
        }

        $this->recordAdminCategoryUpdate($request, $record, $updated);

        return ApiResponse::success([
            'item' => AdminTicketCategoryFormatter::format($updated),
            'updated_category_id' => $id,
            'updated_category_label' => $this->categoryLabel($updated),
        ], 'ticket category updated');
    }

    public function status(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'status');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->categoryIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('ticket category id is required', 422, null, 422);
        }

        $record = $this->loadCategoryRow($id);
        if ($record === null) {
            return ApiResponse::error('ticket category not found', 404, null, 404);
        }

        try {
            $status = $this->normalizeStatus(RequestPayload::all($request)['status'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table('ypay_ticket_category')
            ->where('id', $id)
            ->update([
                'status' => $status,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        $updated = $this->loadCategoryRow($id);
        if ($updated === null) {
            return ApiResponse::error('updated ticket category could not be loaded', 500, null, 500);
        }

        $this->recordAdminCategoryStatus($request, $record, $status);

        return ApiResponse::success([
            'item' => AdminTicketCategoryFormatter::format($updated),
            'updated_category_id' => $id,
            'updated_category_label' => $this->categoryLabel($updated),
            'status' => $status,
            'status_label' => (string)(AdminTicketCategoryFormatter::format($updated)['status_label'] ?? ''),
        ], 'ticket category status updated');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->categoryIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('ticket category id is required', 422, null, 422);
        }

        $record = $this->loadCategoryRow($id);
        if ($record === null) {
            return ApiResponse::error('ticket category not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminTicketCategoryFormatter::format($record),
            'audit' => $this->buildCategoryDeleteAudit($record),
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->categoryIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('ticket category id is required', 422, null, 422);
        }

        $record = $this->loadCategoryRow($id);
        if ($record === null) {
            return ApiResponse::error('ticket category not found', 404, null, 404);
        }

        $audit = $this->buildCategoryDeleteAudit($record);
        if (empty($audit['can_delete'])) {
            return ApiResponse::error(
                'ticket category cannot be deleted until every linked ticket is cleared or reassigned',
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
            $this->deleteCategoryRow($id);
        });

        $this->recordAdminCategoryDelete($request, $audit);

        return ApiResponse::success([
            'deleted_category_id' => $id,
            'deleted_category_label' => (string)($audit['category_label'] ?? ''),
            'audit' => $audit,
        ], 'ticket category deleted');
    }

    public function batchDeleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $categoryIds = $this->normalizeCategoryIds(
                RequestPayload::all($request)['category_ids']
                    ?? RequestPayload::all($request)['ids']
                    ?? []
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success([
            'audit' => $this->batchCategoryDeleteAudit($categoryIds),
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
            $categoryIds = $this->normalizeCategoryIds(
                $payload['category_ids'] ?? $payload['ids'] ?? []
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $audit = $this->batchCategoryDeleteAudit($categoryIds);
        if (empty($audit['can_delete_all'])) {
            return ApiResponse::error(
                'selected ticket categories cannot be batch deleted until every linked ticket is cleared or reassigned',
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
            foreach ((array)($audit['deletable_category_ids'] ?? []) as $categoryId) {
                $this->deleteCategoryRow((int)$categoryId);
            }
        });

        $this->recordAdminCategoryBatchDelete($request, $audit);

        return ApiResponse::success([
            'deleted_category_ids' => array_values(array_map(
                'intval',
                (array)($audit['deletable_category_ids'] ?? [])
            )),
            'deleted_count' => (int)(($audit['summary'] ?? [])['deletable_count'] ?? 0),
            'audit' => $audit,
        ], 'ticket category batch delete completed');
    }

    private function categoryQuery(): Builder
    {
        return Db::table('ypay_ticket_category')
            ->select('id', 'name', 'sort', 'status', 'create_time', 'update_time');
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('sort', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('id', (int)$keyword);
                }
            });
        }

        $status = trim((string)$request->get('status', ''));
        if ($status !== '' && in_array($status, ['0', '1'], true)) {
            $query->where('status', $status);
        }
    }

    /**
     * @param array<int, int> $categoryIds
     * @return array<int, array<string, mixed>>
     */
    private function statsForCategoryIds(array $categoryIds): array
    {
        $categoryIds = array_values(array_unique(array_filter(
            $categoryIds,
            static fn(int $id): bool => $id > 0
        )));

        if ($categoryIds === []) {
            return [];
        }

        $rows = Db::table('ypay_ticket')
            ->select('type')
            ->selectRaw('COUNT(*) as ticket_count')
            ->selectRaw('SUM(CASE WHEN status IN (0, 1) THEN 1 ELSE 0 END) as open_ticket_count')
            ->selectRaw("SUM(CASE WHEN reply_content IS NOT NULL AND reply_content <> '' THEN 1 ELSE 0 END) as replied_ticket_count")
            ->selectRaw('MAX(create_time) as latest_ticket_time')
            ->whereIn('type', $categoryIds)
            ->groupBy('type')
            ->get()
            ->toArray();

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int)$row->type] = [
                'ticket_count' => (int)$row->ticket_count,
                'open_ticket_count' => (int)$row->open_ticket_count,
                'replied_ticket_count' => (int)$row->replied_ticket_count,
                'latest_ticket_time' => $row->latest_ticket_time,
            ];
        }

        return $stats;
    }

    /**
     * @param array<int, int> $allIds
     * @param array<int, array<string, mixed>> $stats
     */
    private function summary(array $allIds, array $stats, Builder $query): array
    {
        $linked = 0;
        $openTickets = 0;
        foreach ($allIds as $id) {
            $ticketCount = (int)($stats[$id]['ticket_count'] ?? 0);
            if ($ticketCount > 0) {
                $linked++;
            }
            $openTickets += (int)($stats[$id]['open_ticket_count'] ?? 0);
        }

        $total = count($allIds);

        return [
            'total_count' => $total,
            'enabled_count' => (int)(clone $query)->where('status', '1')->count('id'),
            'disabled_count' => (int)(clone $query)->where('status', '<>', '1')->count('id'),
            'linked_count' => $linked,
            'unused_count' => max(0, $total - $linked),
            'open_ticket_count' => $openTickets,
        ];
    }

    /**
     * @param array<string, mixed> $category
     * @param array<int, array<string, mixed>> $stats
     * @return array<string, mixed>
     */
    private function withStats(array $category, array $stats): array
    {
        $id = (int)($category['id'] ?? 0);

        return array_merge($category, [
            'ticket_count' => (int)($stats[$id]['ticket_count'] ?? 0),
            'open_ticket_count' => (int)($stats[$id]['open_ticket_count'] ?? 0),
            'replied_ticket_count' => (int)($stats[$id]['replied_ticket_count'] ?? 0),
            'latest_ticket_time' => $stats[$id]['latest_ticket_time'] ?? null,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function formatCategoryRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $pageIds = array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $rows);
        $pageStats = $this->statsForCategoryIds($pageIds);

        return array_map(
            fn(array $row): array => AdminTicketCategoryFormatter::format($this->withStats($row, $pageStats)),
            $rows
        );
    }

    private function loadCategoryRow(int $id): ?array
    {
        $row = $this->categoryQuery()
            ->where('id', $id)
            ->first();

        if (!$row) {
            return null;
        }

        $stats = $this->statsForCategoryIds([$id]);

        return $this->withStats((array)$row, $stats);
    }

    /**
     * @param array<int, int> $categoryIds
     * @return array<int, array<string, mixed>>
     */
    private function loadCategoryRowsByIds(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $rows = array_map(
            static fn($row): array => (array)$row,
            Db::table('ypay_ticket_category')
                ->select('id', 'name', 'sort', 'status', 'create_time', 'update_time')
                ->whereIn('id', $categoryIds)
                ->get()
                ->toArray()
        );

        if ($rows === []) {
            return [];
        }

        $stats = $this->statsForCategoryIds($categoryIds);

        return array_map(
            fn(array $row): array => $this->withStats($row, $stats),
            $rows
        );
    }

    private function normalizeWritePayload(array $payload, ?array $current = null): array
    {
        $name = $this->normalizeRequiredString(
            $payload['name'] ?? ($current['name'] ?? null),
            255,
            'ticket category name'
        );
        $sort = $this->normalizeSort($payload['sort'] ?? ($current['sort'] ?? null));
        $status = $this->normalizeStatus($payload['status'] ?? ($current['status'] ?? 1));

        return [
            'name' => $name,
            'sort' => $sort,
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

    private function normalizeSort(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('ticket category sort must be a scalar');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            return null;
        }

        if (!preg_match('/^\d{1,9}$/', $normalized)) {
            throw new \InvalidArgumentException('ticket category sort must be a positive integer with up to 9 digits');
        }

        return $normalized;
    }

    private function normalizeStatus(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_numeric($value)) {
            $status = (int)$value;
            if (in_array($status, [0, 1], true)) {
                return $status;
            }
        }

        $normalized = strtolower(trim((string)$value));

        return match ($normalized) {
            '1', 'true', 'yes', 'on', 'enable', 'enabled', 'active' => 1,
            '0', 'false', 'no', 'off', 'disable', 'disabled', 'inactive' => 0,
            default => throw new \InvalidArgumentException('ticket category status must be 0 or 1'),
        };
    }

    private function buildCategoryDeleteAudit(array $record): array
    {
        $ticketCount = (int)($record['ticket_count'] ?? 0);
        $openTicketCount = (int)($record['open_ticket_count'] ?? 0);
        $repliedTicketCount = (int)($record['replied_ticket_count'] ?? 0);

        $blockingReasons = [];
        if ($ticketCount > 0) {
            $blockingReasons[] = sprintf(
                'This ticket category still has %d linked ticket(s), including %d open ticket(s).',
                $ticketCount,
                $openTicketCount
            );
        }

        return [
            'category_id' => (int)($record['id'] ?? 0),
            'category_label' => $this->categoryLabel($record),
            'status' => (int)($record['status'] ?? 0),
            'ticket_count' => $ticketCount,
            'open_ticket_count' => $openTicketCount,
            'replied_ticket_count' => $repliedTicketCount,
            'can_delete' => $blockingReasons === [],
            'confirmation_phrase' => $this->categoryDeleteConfirmationPhrase((int)($record['id'] ?? 0)),
            'blocking_reasons' => $blockingReasons,
            'summary' => [
                'delete_row_count' => $blockingReasons === [] ? 1 : 0,
                'linked_ticket_count' => $ticketCount,
                'open_ticket_count' => $openTicketCount,
                'blocked_count' => $blockingReasons === [] ? 0 : 1,
            ],
            'warnings' => [
                'Deleting a ticket category permanently removes the category row.',
                'Any category with linked tickets must stay in place until those tickets are cleared or reassigned.',
            ],
        ];
    }

    /**
     * @param array<int, int> $categoryIds
     */
    private function batchCategoryDeleteAudit(array $categoryIds): array
    {
        $rows = $this->loadCategoryRowsByIds($categoryIds);
        $rowMap = [];
        foreach ($rows as $row) {
            $rowMap[(int)($row['id'] ?? 0)] = $row;
        }

        $items = [];
        $deletableCategoryIds = [];
        $blockedCategoryIds = [];
        $missingCategoryIds = [];
        $deleteRowCount = 0;
        $linkedTicketCount = 0;
        $openTicketCount = 0;

        foreach ($categoryIds as $categoryId) {
            $row = $rowMap[$categoryId] ?? null;
            if ($row === null) {
                $missingCategoryIds[] = $categoryId;
                $items[] = [
                    'category_id' => $categoryId,
                    'category_label' => '',
                    'exists' => false,
                    'can_delete' => false,
                    'ticket_count' => 0,
                    'open_ticket_count' => 0,
                    'replied_ticket_count' => 0,
                    'blocking_reasons' => ['This ticket category was not found in ypay_ticket_category.'],
                    'summary' => [
                        'delete_row_count' => 0,
                        'linked_ticket_count' => 0,
                        'open_ticket_count' => 0,
                        'blocked_count' => 1,
                    ],
                    'warnings' => ['Remove missing ticket categories from the selection before retrying the batch delete.'],
                ];
                continue;
            }

            $audit = $this->buildCategoryDeleteAudit($row);
            $items[] = [
                'category_id' => $categoryId,
                'category_label' => (string)($audit['category_label'] ?? ''),
                'exists' => true,
                'can_delete' => !empty($audit['can_delete']),
                'ticket_count' => (int)($audit['ticket_count'] ?? 0),
                'open_ticket_count' => (int)($audit['open_ticket_count'] ?? 0),
                'replied_ticket_count' => (int)($audit['replied_ticket_count'] ?? 0),
                'blocking_reasons' => array_values(array_map('strval', (array)($audit['blocking_reasons'] ?? []))),
                'summary' => (array)($audit['summary'] ?? []),
                'warnings' => array_values(array_map('strval', (array)($audit['warnings'] ?? []))),
            ];

            $summary = (array)($audit['summary'] ?? []);
            $deleteRowCount += (int)($summary['delete_row_count'] ?? 0);
            $linkedTicketCount += (int)($summary['linked_ticket_count'] ?? 0);
            $openTicketCount += (int)($summary['open_ticket_count'] ?? 0);

            if (!empty($audit['can_delete'])) {
                $deletableCategoryIds[] = $categoryId;
                continue;
            }

            $blockedCategoryIds[] = $categoryId;
        }

        $warnings = [];
        if ($missingCategoryIds !== []) {
            $warnings[] = 'Some selected ticket categories no longer exist and must be removed from the selection.';
        }
        if ($blockedCategoryIds !== []) {
            $warnings[] = 'At least one selected ticket category still has linked tickets, so the batch delete is paused.';
        }
        if ($deletableCategoryIds !== []) {
            $warnings[] = 'Batch delete permanently removes the selected ticket category rows after one shared confirmation phrase is accepted.';
        }

        return [
            'requested_category_ids' => $categoryIds,
            'deletable_category_ids' => $deletableCategoryIds,
            'blocked_category_ids' => $blockedCategoryIds,
            'missing_category_ids' => $missingCategoryIds,
            'confirmation_phrase' => $this->batchCategoryDeleteConfirmationPhrase($categoryIds),
            'can_delete_all' => $categoryIds !== [] && $blockedCategoryIds === [] && $missingCategoryIds === [],
            'items' => $items,
            'summary' => [
                'requested_count' => count($categoryIds),
                'existing_count' => count($categoryIds) - count($missingCategoryIds),
                'deletable_count' => count($deletableCategoryIds),
                'blocked_count' => count($blockedCategoryIds),
                'missing_count' => count($missingCategoryIds),
                'delete_row_count' => $deleteRowCount,
                'linked_ticket_count' => $linkedTicketCount,
                'open_ticket_count' => $openTicketCount,
            ],
            'warnings' => $warnings,
        ];
    }

    private function deleteCategoryRow(int $id): void
    {
        Db::table('ypay_ticket_category')
            ->where('id', $id)
            ->delete();
    }

    /**
     * @param mixed $value
     * @return array<int, int>
     */
    private function normalizeCategoryIds(mixed $value, int $maxCount = 100): array
    {
        $items = [];

        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $items = preg_split('/\s*,\s*/', trim($value)) ?: [];
        } elseif (is_numeric($value)) {
            $items = [$value];
        }

        $categoryIds = [];
        foreach ($items as $item) {
            if (is_bool($item) || is_array($item) || is_object($item)) {
                continue;
            }

            $normalized = trim((string)$item);
            if ($normalized === '' || !ctype_digit($normalized)) {
                continue;
            }

            $categoryId = (int)$normalized;
            if ($categoryId > 0) {
                $categoryIds[$categoryId] = $categoryId;
            }
        }

        $categoryIds = array_values($categoryIds);
        sort($categoryIds);

        if ($categoryIds === []) {
            throw new \InvalidArgumentException('ticket category ids are required');
        }

        if (count($categoryIds) > $maxCount) {
            throw new \InvalidArgumentException('too many ticket categories were selected for one batch action');
        }

        return $categoryIds;
    }

    private function categoryIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function categoryLabel(array $record): string
    {
        $name = trim((string)($record['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return 'category #' . (int)($record['id'] ?? 0);
    }

    private function categoryDeleteConfirmationPhrase(int $id): string
    {
        return 'DELETE TICKET CATEGORY ' . $id;
    }

    /**
     * @param array<int, int> $categoryIds
     */
    private function batchCategoryDeleteConfirmationPhrase(array $categoryIds): string
    {
        return sprintf(
            'DELETE TICKET CATEGORY BATCH %d-%s',
            count($categoryIds),
            strtoupper(substr(md5(implode(',', $categoryIds)), 0, 6))
        );
    }

    private function recordAdminCategoryCreate(Request $request, array $record): void
    {
        $adminId = (int)(((array)($request->admin ?? []))['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $categoryId = (int)($record['id'] ?? 0);
        $categoryLabel = $this->truncateLogText($this->categoryLabel($record), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/ticket-categories/create',
            'desc' => sprintf(
                'ticket category create category_id=%d label="%s" sort="%s" status=%d',
                $categoryId,
                $categoryLabel,
                $this->truncateLogText((string)($record['sort'] ?? ''), 40),
                (int)($record['status'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminCategoryUpdate(Request $request, array $before, array $after): void
    {
        $adminId = (int)(((array)($request->admin ?? []))['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $categoryId = (int)($after['id'] ?? 0);
        $categoryLabel = $this->truncateLogText($this->categoryLabel($after), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/ticket-categories/' . $categoryId . '/update',
            'desc' => sprintf(
                'ticket category update category_id=%d label="%s" from_status=%d to_status=%d name_changed=%d sort_changed=%d',
                $categoryId,
                $categoryLabel,
                (int)($before['status'] ?? 0),
                (int)($after['status'] ?? 0),
                trim((string)($before['name'] ?? '')) === trim((string)($after['name'] ?? '')) ? 0 : 1,
                trim((string)($before['sort'] ?? '')) === trim((string)($after['sort'] ?? '')) ? 0 : 1
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminCategoryStatus(Request $request, array $record, int $status): void
    {
        $adminId = (int)(((array)($request->admin ?? []))['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $categoryId = (int)($record['id'] ?? 0);
        $categoryLabel = $this->truncateLogText($this->categoryLabel($record), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/ticket-categories/' . $categoryId . '/status',
            'desc' => sprintf(
                'ticket category status category_id=%d label="%s" from_status=%d to_status=%d',
                $categoryId,
                $categoryLabel,
                (int)($record['status'] ?? 0),
                $status
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminCategoryDelete(Request $request, array $audit): void
    {
        $adminId = (int)(((array)($request->admin ?? []))['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $categoryId = (int)($audit['category_id'] ?? 0);
        $categoryLabel = $this->truncateLogText((string)($audit['category_label'] ?? ''), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/ticket-categories/' . $categoryId . '/delete',
            'desc' => sprintf(
                'ticket category delete category_id=%d label="%s" delete_rows=%d linked_tickets=%d open_tickets=%d',
                $categoryId,
                $categoryLabel,
                (int)($summary['delete_row_count'] ?? 0),
                (int)($summary['linked_ticket_count'] ?? 0),
                (int)($summary['open_ticket_count'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminCategoryBatchDelete(Request $request, array $audit): void
    {
        $adminId = (int)(((array)($request->admin ?? []))['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $categoryIds = implode(',', array_map('intval', (array)($audit['deletable_category_ids'] ?? [])));
        $categoryLabels = implode(',', array_map(
            static function (array $item): string {
                $label = trim((string)($item['category_label'] ?? ''));
                $categoryId = (int)($item['category_id'] ?? 0);
                return $label !== '' ? $label : ('category #' . $categoryId);
            },
            array_values(array_filter(
                (array)($audit['items'] ?? []),
                static fn(array $item): bool => !empty($item['can_delete'])
            ))
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/ticket-categories/batch-delete',
            'desc' => sprintf(
                'ticket category batch delete requested=%d deleted=%d blocked=%d missing=%d linked_tickets=%d open_tickets=%d category_ids="%s" labels="%s"',
                (int)($summary['requested_count'] ?? 0),
                (int)($summary['deletable_count'] ?? 0),
                (int)($summary['blocked_count'] ?? 0),
                (int)($summary['missing_count'] ?? 0),
                (int)($summary['linked_ticket_count'] ?? 0),
                (int)($summary['open_ticket_count'] ?? 0),
                $this->truncateLogText($categoryIds, 255),
                $this->truncateLogText($categoryLabels, 255)
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

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'TicketCategories', $authMark);
    }
}
