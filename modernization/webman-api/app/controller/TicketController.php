<?php

namespace app\controller;

use app\controller\concerns\AdminControllerFormatSupport;
use app\support\AdminRouteAuthorization;
use app\support\AdminTicketFormatter;
use app\support\ApiResponse;
use app\support\RequestPayload;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class TicketController
{
    use AdminControllerFormatSupport;

    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $query = $this->ticketQuery();
        $this->applyFilters($query, $request);

        $summary = $this->summary(clone $query);
        $total = (int)(clone $query)->count('ypay_ticket.id');
        $rows = array_map(
            static fn($row): array => (array)$row,
            $query
                ->orderByDesc('ypay_ticket.id')
                ->offset(($current - 1) * $size)
                ->limit($size)
                ->get()
                ->toArray()
        );

        return ApiResponse::success([
            'records' => $this->formatTicketRows($rows),
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => $summary,
            'categories' => $this->categories(),
        ]);
    }

    public function show(Request $request): Response
    {
        $id = $this->ticketIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('ticket id is required', 422, null, 422);
        }

        $row = $this->loadTicketRow($id);
        if ($row === null) {
            return ApiResponse::error('ticket not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminTicketFormatter::format($row),
            'categories' => $this->categories(),
        ]);
    }

    public function reply(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'reply');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->ticketIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('ticket id is required', 422, null, 422);
        }

        $record = $this->loadTicketRow($id);
        if ($record === null) {
            return ApiResponse::error('ticket not found', 404, null, 404);
        }

        try {
            $payload = $this->normalizeReplyPayload(RequestPayload::all($request), $record);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $adminId = $this->adminIdFromRequest($request);
        $now = date('Y-m-d H:i:s');
        $update = [
            'status' => $payload['status'],
            'update_time' => $now,
        ];

        if (!empty($payload['reply_content_provided'])) {
            $update['reply_content'] = $payload['reply_content'];
            $update['reply_time'] = $now;
        }

        if ($adminId > 0) {
            $update['assignee_id'] = $adminId;
        }

        Db::table('ypay_ticket')
            ->where('id', $id)
            ->update($update);

        $updated = $this->loadTicketRow($id);
        if ($updated === null) {
            return ApiResponse::error('updated ticket could not be loaded', 500, null, 500);
        }

        $this->recordAdminTicketReply($request, $record, $updated, !empty($payload['reply_content_provided']));
        $formatted = AdminTicketFormatter::format($updated);

        return ApiResponse::success([
            'item' => $formatted,
            'updated_ticket_id' => $id,
            'updated_ticket_label' => $this->ticketLabel($updated),
            'status' => (int)($updated['status'] ?? 0),
            'status_label' => (string)($formatted['status_label'] ?? ''),
            'reply_state_label' => (string)($formatted['reply_state_label'] ?? ''),
        ], 'ticket reply saved');
    }

    public function status(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'status');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->ticketIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('ticket id is required', 422, null, 422);
        }

        $record = $this->loadTicketRow($id);
        if ($record === null) {
            return ApiResponse::error('ticket not found', 404, null, 404);
        }

        try {
            $status = $this->normalizeStatus(RequestPayload::all($request)['status'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $update = [
            'status' => $status,
            'update_time' => date('Y-m-d H:i:s'),
        ];

        $adminId = $this->adminIdFromRequest($request);
        if ($adminId > 0) {
            $update['assignee_id'] = $adminId;
        }

        Db::table('ypay_ticket')
            ->where('id', $id)
            ->update($update);

        $updated = $this->loadTicketRow($id);
        if ($updated === null) {
            return ApiResponse::error('updated ticket could not be loaded', 500, null, 500);
        }

        $this->recordAdminTicketStatus($request, $record, $updated);
        $formatted = AdminTicketFormatter::format($updated);

        return ApiResponse::success([
            'item' => $formatted,
            'updated_ticket_id' => $id,
            'updated_ticket_label' => $this->ticketLabel($updated),
            'status' => (int)($updated['status'] ?? 0),
            'status_label' => (string)($formatted['status_label'] ?? ''),
        ], 'ticket status updated');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->ticketIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('ticket id is required', 422, null, 422);
        }

        $record = $this->loadTicketRow($id);
        if ($record === null) {
            return ApiResponse::error('ticket not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminTicketFormatter::format($record),
            'audit' => $this->buildTicketDeleteAudit($record),
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->ticketIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('ticket id is required', 422, null, 422);
        }

        $record = $this->loadTicketRow($id);
        if ($record === null) {
            return ApiResponse::error('ticket not found', 404, null, 404);
        }

        $audit = $this->buildTicketDeleteAudit($record);
        $confirmationPhrase = trim((string)(RequestPayload::all($request)['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('confirmation phrase mismatch', 422, ['audit' => $audit], 422);
        }

        Db::transaction(function () use ($id): void {
            $this->deleteTicketRow($id);
        });

        $this->recordAdminTicketDelete($request, $audit);

        return ApiResponse::success([
            'deleted_ticket_id' => $id,
            'deleted_ticket_label' => (string)($audit['ticket_label'] ?? ''),
            'audit' => $audit,
        ], 'ticket deleted');
    }

    public function batchDeleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $ticketIds = $this->normalizeTicketIds(
                RequestPayload::all($request)['ticket_ids']
                    ?? RequestPayload::all($request)['ids']
                    ?? []
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success([
            'audit' => $this->batchTicketDeleteAudit($ticketIds),
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
            $ticketIds = $this->normalizeTicketIds(
                $payload['ticket_ids'] ?? $payload['ids'] ?? []
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $audit = $this->batchTicketDeleteAudit($ticketIds);
        if (empty($audit['can_delete_all'])) {
            return ApiResponse::error(
                'selected tickets cannot be batch deleted until the selection is refreshed',
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
            foreach ((array)($audit['deletable_ticket_ids'] ?? []) as $ticketId) {
                $this->deleteTicketRow((int)$ticketId);
            }
        });

        $this->recordAdminTicketBatchDelete($request, $audit);

        return ApiResponse::success([
            'deleted_ticket_ids' => array_values(array_map(
                'intval',
                (array)($audit['deletable_ticket_ids'] ?? [])
            )),
            'deleted_count' => (int)(($audit['summary'] ?? [])['deletable_count'] ?? 0),
            'audit' => $audit,
        ], 'ticket batch delete completed');
    }

    private function ticketQuery(): Builder
    {
        return Db::table('ypay_ticket')
            ->leftJoin('ypay_ticket_category', 'ypay_ticket.type', '=', 'ypay_ticket_category.id')
            ->leftJoin('ypay_user as creator', 'ypay_ticket.creator_id', '=', 'creator.id')
            ->leftJoin('admin_admin as assignee', 'ypay_ticket.assignee_id', '=', 'assignee.id')
            ->select(
                'ypay_ticket.id',
                'ypay_ticket.type',
                'ypay_ticket.title',
                'ypay_ticket.content',
                'ypay_ticket.reply_content',
                'ypay_ticket.creator_id',
                'ypay_ticket.assignee_id',
                'ypay_ticket.create_time',
                'ypay_ticket.update_time',
                'ypay_ticket.reply_time',
                'ypay_ticket.status',
                'ypay_ticket_category.name as category_name',
                'creator.username as creator_username',
                'creator.name as creator_name',
                'creator.email as creator_email',
                'creator.mobile as creator_mobile',
                'assignee.username as assignee_username',
                'assignee.nickname as assignee_nickname'
            );
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('ypay_ticket.title', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_ticket.content', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_ticket.reply_content', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_ticket_category.name', 'like', '%' . $keyword . '%')
                    ->orWhere('creator.username', 'like', '%' . $keyword . '%')
                    ->orWhere('creator.name', 'like', '%' . $keyword . '%')
                    ->orWhere('creator.email', 'like', '%' . $keyword . '%')
                    ->orWhere('creator.mobile', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder
                        ->orWhere('ypay_ticket.id', (int)$keyword)
                        ->orWhere('ypay_ticket.creator_id', (int)$keyword)
                        ->orWhere('ypay_ticket.assignee_id', (int)$keyword);
                }
            });
        }

        $creatorId = trim((string)$request->get('creator_id', ''));
        if ($creatorId !== '') {
            $query->where('ypay_ticket.creator_id', 'like', '%' . $creatorId . '%');
        }

        $status = trim((string)$request->get('status', ''));
        if ($status !== '' && in_array($status, ['0', '1', '2', '3'], true)) {
            $query->where('ypay_ticket.status', (int)$status);
        }

        $type = trim((string)$request->get('type', ''));
        if ($type !== '' && ctype_digit($type)) {
            $query->where('ypay_ticket.type', (int)$type);
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', ''));
        $endDate = $this->normalizeDate((string)$request->get('end_date', ''));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('ypay_ticket.create_time', '>=', $startDate . ' 00:00:00')
                ->where('ypay_ticket.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
        }
    }

    private function summary(Builder $query): array
    {
        return [
            'new_count' => (int)(clone $query)->where('ypay_ticket.status', 0)->count('ypay_ticket.id'),
            'processing_count' => (int)(clone $query)->where('ypay_ticket.status', 1)->count('ypay_ticket.id'),
            'resolved_count' => (int)(clone $query)->where('ypay_ticket.status', 2)->count('ypay_ticket.id'),
            'closed_count' => (int)(clone $query)->where('ypay_ticket.status', 3)->count('ypay_ticket.id'),
            'replied_count' => (int)(clone $query)
                ->whereNotNull('ypay_ticket.reply_content')
                ->where('ypay_ticket.reply_content', '<>', '')
                ->count('ypay_ticket.id'),
        ];
    }

    private function categories(): array
    {
        $rows = array_map(
            static fn($row): array => (array)$row,
            Db::table('ypay_ticket_category')
                ->select('id', 'name', 'status')
                ->orderByRaw("CAST(COALESCE(NULLIF(sort, ''), '0') AS UNSIGNED)")
                ->orderBy('id')
                ->get()
                ->toArray()
        );

        return array_map(
            static fn(array $row): array => AdminTicketFormatter::formatCategory($row),
            $rows
        );
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function formatTicketRows(array $rows): array
    {
        return array_map(
            static fn(array $row): array => AdminTicketFormatter::format($row),
            $rows
        );
    }

    private function loadTicketRow(int $ticketId): ?array
    {
        $row = $this->ticketQuery()
            ->where('ypay_ticket.id', $ticketId)
            ->first();

        return $row ? (array)$row : null;
    }

    /**
     * @param array<int, int> $ticketIds
     * @return array<int, array<string, mixed>>
     */
    private function loadTicketRowsByIds(array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }

        $rows = array_map(
            static fn($row): array => (array)$row,
            $this->ticketQuery()
                ->whereIn('ypay_ticket.id', $ticketIds)
                ->get()
                ->toArray()
        );

        $grouped = [];
        foreach ($rows as $row) {
            $ticketId = (int)($row['id'] ?? 0);
            if ($ticketId > 0) {
                $grouped[$ticketId] = $row;
            }
        }

        return $grouped;
    }

    private function buildTicketDeleteAudit(array $record): array
    {
        $formatted = AdminTicketFormatter::format($record);
        $ticketId = (int)($record['id'] ?? 0);
        $status = (int)($record['status'] ?? 0);
        $creatorId = (int)($record['creator_id'] ?? 0);
        $assigneeId = (int)($record['assignee_id'] ?? 0);
        $isReplied = !empty($formatted['is_replied']);
        $openTicketCount = in_array($status, [0, 1], true) ? 1 : 0;
        $warnings = [];

        if ($openTicketCount > 0) {
            $warnings[] = 'This ticket is still open and will be permanently removed.';
        }

        if ($isReplied) {
            $warnings[] = 'The stored admin reply will also be permanently removed.';
        } else {
            $warnings[] = 'No admin reply is stored on this ticket yet.';
        }

        if ($creatorId > 0) {
            $warnings[] = 'The merchant support record will lose this ticket entry after deletion.';
        }

        return [
            'ticket_id' => $ticketId,
            'ticket_label' => $this->ticketLabel($record),
            'status' => $status,
            'status_label' => (string)($formatted['status_label'] ?? ''),
            'type' => (int)($record['type'] ?? 0),
            'type_name' => (string)($formatted['type_name'] ?? ''),
            'creator_id' => $creatorId,
            'assignee_id' => $assigneeId,
            'is_replied' => $isReplied,
            'can_delete' => true,
            'confirmation_phrase' => $this->ticketDeleteConfirmationPhrase($ticketId),
            'blocking_reasons' => [],
            'summary' => [
                'delete_row_count' => 1,
                'open_ticket_count' => $openTicketCount,
                'replied_count' => $isReplied ? 1 : 0,
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<int, int> $ticketIds
     */
    private function batchTicketDeleteAudit(array $ticketIds): array
    {
        $rowsById = $this->loadTicketRowsByIds($ticketIds);
        $items = [];
        $deletableTicketIds = [];
        $missingTicketIds = [];
        $openTicketCount = 0;
        $repliedCount = 0;

        foreach ($ticketIds as $ticketId) {
            $row = $rowsById[$ticketId] ?? null;
            if ($row === null) {
                $missingTicketIds[] = $ticketId;
                $items[] = [
                    'ticket_id' => $ticketId,
                    'ticket_label' => 'Ticket #' . $ticketId,
                    'exists' => false,
                    'can_delete' => false,
                    'status' => null,
                    'status_label' => 'Missing',
                    'type' => 0,
                    'type_name' => 'Unknown Category',
                    'creator_id' => 0,
                    'assignee_id' => 0,
                    'is_replied' => false,
                    'blocking_reasons' => ['This ticket no longer exists in the live table.'],
                    'warnings' => ['Refresh the selection before retrying the batch delete.'],
                    'summary' => [
                        'delete_row_count' => 0,
                        'open_ticket_count' => 0,
                        'replied_count' => 0,
                    ],
                ];
                continue;
            }

            $audit = $this->buildTicketDeleteAudit($row);
            $deletableTicketIds[] = $ticketId;
            $openTicketCount += (int)(($audit['summary'] ?? [])['open_ticket_count'] ?? 0);
            $repliedCount += (int)(($audit['summary'] ?? [])['replied_count'] ?? 0);
            $items[] = [
                'ticket_id' => $ticketId,
                'ticket_label' => (string)($audit['ticket_label'] ?? $this->ticketLabel($row)),
                'exists' => true,
                'can_delete' => true,
                'status' => (int)($audit['status'] ?? 0),
                'status_label' => (string)($audit['status_label'] ?? ''),
                'type' => (int)($audit['type'] ?? 0),
                'type_name' => (string)($audit['type_name'] ?? ''),
                'creator_id' => (int)($audit['creator_id'] ?? 0),
                'assignee_id' => (int)($audit['assignee_id'] ?? 0),
                'is_replied' => !empty($audit['is_replied']),
                'blocking_reasons' => [],
                'warnings' => array_values(array_map('strval', (array)($audit['warnings'] ?? []))),
                'summary' => (array)($audit['summary'] ?? []),
            ];
        }

        $summary = [
            'requested_count' => count($ticketIds),
            'existing_count' => count($deletableTicketIds),
            'deletable_count' => count($deletableTicketIds),
            'missing_count' => count($missingTicketIds),
            'open_ticket_count' => $openTicketCount,
            'replied_count' => $repliedCount,
        ];

        $warnings = [];
        if ($summary['missing_count'] > 0) {
            $warnings[] = sprintf(
                '%d selected ticket(s) are already missing and must be reselected before deletion.',
                $summary['missing_count']
            );
        }
        if ($summary['open_ticket_count'] > 0) {
            $warnings[] = sprintf(
                '%d selected ticket(s) are still open and will be permanently removed.',
                $summary['open_ticket_count']
            );
        }
        if ($summary['replied_count'] > 0) {
            $warnings[] = sprintf(
                '%d selected ticket(s) already contain stored admin replies that will also be removed.',
                $summary['replied_count']
            );
        }

        $canDeleteAll = $deletableTicketIds !== [] && $missingTicketIds === [];

        return [
            'requested_ticket_ids' => $ticketIds,
            'deletable_ticket_ids' => $deletableTicketIds,
            'missing_ticket_ids' => $missingTicketIds,
            'confirmation_phrase' => $deletableTicketIds === []
                ? ''
                : $this->batchTicketDeleteConfirmationPhrase($deletableTicketIds),
            'can_delete_all' => $canDeleteAll,
            'items' => $items,
            'summary' => $summary,
            'warnings' => $warnings,
        ];
    }

    private function deleteTicketRow(int $ticketId): void
    {
        Db::table('ypay_ticket')
            ->where('id', $ticketId)
            ->delete();
    }

    /**
     * @return array{reply_content_provided: bool, reply_content: ?string, status: int}
     */
    private function normalizeReplyPayload(array $payload, array $record): array
    {
        $hasReplyContent = array_key_exists('reply_content', $payload);
        $replyContent = null;

        if ($hasReplyContent) {
            $replyContent = trim((string)$payload['reply_content']);
            if ($replyContent === '') {
                throw new \InvalidArgumentException('reply content is required when submitting an admin reply');
            }
        }

        $hasStatus = array_key_exists('status', $payload);
        $status = $hasStatus
            ? $this->normalizeStatus($payload['status'])
            : ($hasReplyContent ? 2 : (int)($record['status'] ?? 0));

        if (!$hasReplyContent && !$hasStatus) {
            throw new \InvalidArgumentException('reply content or status is required');
        }

        return [
            'reply_content_provided' => $hasReplyContent,
            'reply_content' => $replyContent,
            'status' => $status,
        ];
    }

    private function normalizeStatus(mixed $value): int
    {
        if (is_int($value)) {
            $status = $value;
        } elseif (is_string($value) && ctype_digit(trim($value))) {
            $status = (int)trim($value);
        } else {
            throw new \InvalidArgumentException('ticket status must be 0, 1, 2, or 3');
        }

        if (!in_array($status, [0, 1, 2, 3], true)) {
            throw new \InvalidArgumentException('ticket status must be 0, 1, 2, or 3');
        }

        return $status;
    }

    /**
     * @param mixed $value
     * @return array<int, int>
     */
    private function normalizeTicketIds(mixed $value, int $maxCount = 100): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('ticket ids are required');
        }

        $ticketIds = [];
        foreach ($value as $item) {
            if (is_int($item)) {
                $ticketId = $item;
            } else {
                $normalized = trim((string)$item);
                if ($normalized === '' || !ctype_digit($normalized)) {
                    continue;
                }
                $ticketId = (int)$normalized;
            }

            if ($ticketId > 0) {
                $ticketIds[$ticketId] = $ticketId;
            }
        }

        $ticketIds = array_values($ticketIds);
        sort($ticketIds);

        if ($ticketIds === []) {
            throw new \InvalidArgumentException('ticket ids are required');
        }

        if (count($ticketIds) > $maxCount) {
            throw new \InvalidArgumentException('too many tickets were selected for one batch action');
        }

        return $ticketIds;
    }

    private function ticketIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function adminIdFromRequest(Request $request): int
    {
        return (int)(((array)($request->admin ?? []))['id'] ?? 0);
    }

    private function ticketLabel(array $record): string
    {
        $title = trim((string)($record['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        return 'Ticket #' . (int)($record['id'] ?? 0);
    }

    private function ticketDeleteConfirmationPhrase(int $ticketId): string
    {
        return 'DELETE TICKET ' . $ticketId;
    }

    /**
     * @param array<int, int> $ticketIds
     */
    private function batchTicketDeleteConfirmationPhrase(array $ticketIds): string
    {
        return sprintf(
            'DELETE TICKET BATCH %d-%s',
            count($ticketIds),
            strtoupper(substr(md5(implode(',', $ticketIds)), 0, 6))
        );
    }

    private function recordAdminTicketReply(
        Request $request,
        array $before,
        array $after,
        bool $replyContentProvided
    ): void {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $ticketId = (int)($after['id'] ?? 0);
        $ticketLabel = $this->truncateLogText($this->ticketLabel($after), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/tickets/' . $ticketId . '/reply',
            'desc' => sprintf(
                'ticket reply ticket_id=%d label="%s" from_status=%d to_status=%d reply_changed=%d assignee_id=%d',
                $ticketId,
                $ticketLabel,
                (int)($before['status'] ?? 0),
                (int)($after['status'] ?? 0),
                $replyContentProvided ? 1 : 0,
                (int)($after['assignee_id'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminTicketStatus(Request $request, array $before, array $after): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $ticketId = (int)($after['id'] ?? 0);
        $ticketLabel = $this->truncateLogText($this->ticketLabel($after), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/tickets/' . $ticketId . '/status',
            'desc' => sprintf(
                'ticket status ticket_id=%d label="%s" from_status=%d to_status=%d assignee_id=%d',
                $ticketId,
                $ticketLabel,
                (int)($before['status'] ?? 0),
                (int)($after['status'] ?? 0),
                (int)($after['assignee_id'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminTicketDelete(Request $request, array $audit): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $ticketId = (int)($audit['ticket_id'] ?? 0);
        $ticketLabel = $this->truncateLogText((string)($audit['ticket_label'] ?? ''), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/tickets/' . $ticketId . '/delete',
            'desc' => sprintf(
                'ticket delete ticket_id=%d label="%s" delete_rows=%d open_tickets=%d replied=%d',
                $ticketId,
                $ticketLabel,
                (int)($summary['delete_row_count'] ?? 0),
                (int)($summary['open_ticket_count'] ?? 0),
                (int)($summary['replied_count'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminTicketBatchDelete(Request $request, array $audit): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $ticketIds = implode(',', array_map('intval', (array)($audit['deletable_ticket_ids'] ?? [])));
        $ticketLabels = implode(',', array_map(
            static function (array $item): string {
                $label = trim((string)($item['ticket_label'] ?? ''));
                $ticketId = (int)($item['ticket_id'] ?? 0);
                return $label !== '' ? $label : ('Ticket #' . $ticketId);
            },
            array_values(array_filter(
                (array)($audit['items'] ?? []),
                static fn(array $item): bool => !empty($item['can_delete'])
            ))
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/tickets/batch-delete',
            'desc' => sprintf(
                'ticket batch delete requested=%d deleted=%d missing=%d open_tickets=%d replied=%d ticket_ids="%s" labels="%s"',
                (int)($summary['requested_count'] ?? 0),
                (int)($summary['deletable_count'] ?? 0),
                (int)($summary['missing_count'] ?? 0),
                (int)($summary['open_ticket_count'] ?? 0),
                (int)($summary['replied_count'] ?? 0),
                $this->truncateLogText($ticketIds, 255),
                $this->truncateLogText($ticketLabels, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'TicketList', $authMark);
    }
}
