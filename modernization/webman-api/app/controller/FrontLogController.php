<?php

namespace app\controller;

use app\controller\concerns\AdminControllerFormatSupport;
use app\support\AdminRouteAuthorization;
use app\support\AdminFrontLogFormatter;
use app\support\ApiResponse;
use app\support\RequestPayload;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class FrontLogController
{
    use AdminControllerFormatSupport;

    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $query = $this->frontLogQuery();
        $this->applyFilters($query, $request);

        $summary = $this->summary(clone $query);
        $total = (int)(clone $query)->count('admin_front_log.id');
        $rows = array_map(
            static fn($row): array => (array)$row,
            $query
                ->orderByDesc('admin_front_log.id')
                ->offset(($current - 1) * $size)
                ->limit($size)
                ->get()
                ->toArray()
        );

        return ApiResponse::success([
            'records' => $this->formatFrontLogRows($rows),
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => $summary,
        ]);
    }

    public function show(Request $request): Response
    {
        $id = $this->logIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('front log id is required', 422, null, 422);
        }

        $row = $this->loadFrontLogRow($id);
        if ($row === null) {
            return ApiResponse::error('front log not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminFrontLogFormatter::format($row),
        ]);
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->logIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('front log id is required', 422, null, 422);
        }

        $record = $this->loadFrontLogRow($id);
        if ($record === null) {
            return ApiResponse::error('front log not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminFrontLogFormatter::format($record),
            'audit' => $this->buildFrontLogDeleteAudit($record),
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->logIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('front log id is required', 422, null, 422);
        }

        $record = $this->loadFrontLogRow($id);
        if ($record === null) {
            return ApiResponse::error('front log not found', 404, null, 404);
        }

        $audit = $this->buildFrontLogDeleteAudit($record);
        $confirmationPhrase = trim((string)(RequestPayload::all($request)['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('confirmation phrase mismatch', 422, ['audit' => $audit], 422);
        }

        Db::transaction(function () use ($id): void {
            $this->deleteFrontLogRow($id);
        });

        $this->recordAdminFrontLogDelete($request, $audit);

        return ApiResponse::success([
            'deleted_log_id' => $id,
            'deleted_log_label' => (string)($audit['log_label'] ?? ''),
            'audit' => $audit,
        ], 'front log deleted');
    }

    public function batchDeleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $logIds = $this->normalizeLogIds(
                RequestPayload::all($request)['log_ids']
                    ?? RequestPayload::all($request)['ids']
                    ?? []
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success([
            'audit' => $this->batchFrontLogDeleteAudit($logIds),
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
            $logIds = $this->normalizeLogIds($payload['log_ids'] ?? $payload['ids'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $audit = $this->batchFrontLogDeleteAudit($logIds);
        if (empty($audit['can_delete_all'])) {
            return ApiResponse::error(
                'selected front logs cannot be batch deleted until the selection is refreshed',
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
            Db::table('admin_front_log')
                ->whereIn('id', (array)($audit['deletable_log_ids'] ?? []))
                ->delete();
        });

        $this->recordAdminFrontLogBatchDelete($request, $audit);

        return ApiResponse::success([
            'deleted_log_ids' => array_values(array_map('intval', (array)($audit['deletable_log_ids'] ?? []))),
            'deleted_count' => (int)(($audit['summary'] ?? [])['deletable_count'] ?? 0),
            'audit' => $audit,
        ], 'front log batch delete completed');
    }

    public function cleanupAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        return ApiResponse::success([
            'audit' => $this->frontLogCleanupAudit(),
        ]);
    }

    public function cleanup(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $audit = $this->frontLogCleanupAudit();
        if (empty($audit['can_cleanup'])) {
            return ApiResponse::error('no front logs are available for cleanup', 422, ['audit' => $audit], 422);
        }

        $confirmationPhrase = trim((string)(RequestPayload::all($request)['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('confirmation phrase mismatch', 422, ['audit' => $audit], 422);
        }

        Db::statement('TRUNCATE TABLE admin_front_log');

        $this->recordAdminFrontLogCleanup($request, $audit);

        return ApiResponse::success([
            'deleted_count' => (int)(($audit['summary'] ?? [])['total_count'] ?? 0),
            'audit' => $audit,
        ], 'front logs cleaned up');
    }

    private function frontLogQuery(): Builder
    {
        return Db::table('admin_front_log')
            ->leftJoin('ypay_user', 'admin_front_log.uid', '=', 'ypay_user.id')
            ->select(
                'admin_front_log.id',
                'admin_front_log.uid as user_id',
                'admin_front_log.url',
                'admin_front_log.type',
                'admin_front_log.desc',
                'admin_front_log.ip',
                'admin_front_log.user_agent',
                'admin_front_log.create_time',
                'ypay_user.username as merchant_username',
                'ypay_user.name as merchant_name',
                'ypay_user.email as merchant_email',
                'ypay_user.mobile as merchant_mobile'
            );
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('ypay_user.username', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_user.name', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_user.email', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_user.mobile', 'like', '%' . $keyword . '%')
                    ->orWhere('admin_front_log.url', 'like', '%' . $keyword . '%')
                    ->orWhere('admin_front_log.ip', 'like', '%' . $keyword . '%')
                    ->orWhere('admin_front_log.desc', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder
                        ->orWhere('admin_front_log.id', (int)$keyword)
                        ->orWhere('admin_front_log.uid', (int)$keyword);
                }
            });
        }

        $userId = trim((string)$request->get('user_id', $request->get('uid', '')));
        if ($userId !== '' && ctype_digit($userId)) {
            $query->where('admin_front_log.uid', (int)$userId);
        }

        $ip = trim((string)$request->get('ip', ''));
        if ($ip !== '') {
            $query->where('admin_front_log.ip', 'like', '%' . $ip . '%');
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', ''));
        $endDate = $this->normalizeDate((string)$request->get('end_date', ''));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('admin_front_log.create_time', '>=', $startDate . ' 00:00:00')
                ->where(
                    'admin_front_log.create_time',
                    '<',
                    date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day'))
                );
        }
    }

    private function summary(Builder $query): array
    {
        return [
            'total_count' => (int)(clone $query)->count('admin_front_log.id'),
            'merchant_count' => (int)(clone $query)
                ->where('admin_front_log.uid', '>', 0)
                ->distinct()
                ->count('admin_front_log.uid'),
            'payload_count' => (int)(clone $query)
                ->whereNotNull('admin_front_log.desc')
                ->where('admin_front_log.desc', '<>', '')
                ->count('admin_front_log.id'),
            'today_count' => (int)(clone $query)
                ->where('admin_front_log.create_time', '>=', date('Y-m-d 00:00:00'))
                ->count('admin_front_log.id'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function formatFrontLogRows(array $rows): array
    {
        return array_map(
            static fn(array $row): array => AdminFrontLogFormatter::format($row),
            $rows
        );
    }

    private function loadFrontLogRow(int $logId): ?array
    {
        $row = $this->frontLogQuery()
            ->where('admin_front_log.id', $logId)
            ->first();

        return $row ? (array)$row : null;
    }

    /**
     * @param array<int, int> $logIds
     * @return array<int, array<string, mixed>>
     */
    private function loadFrontLogRowsByIds(array $logIds): array
    {
        if ($logIds === []) {
            return [];
        }

        return array_map(
            static fn($row): array => (array)$row,
            $this->frontLogQuery()
                ->whereIn('admin_front_log.id', $logIds)
                ->orderByDesc('admin_front_log.id')
                ->get()
                ->toArray()
        );
    }

    private function buildFrontLogDeleteAudit(array $record): array
    {
        $formatted = AdminFrontLogFormatter::format($record);
        $logId = (int)($formatted['id'] ?? 0);
        $merchantId = (int)($formatted['user_id'] ?? 0);
        $hasPayload = empty($formatted['payload_is_empty']);
        $warnings = [];

        if ($hasPayload) {
            $warnings[] = 'Captured request payload will be permanently removed.';
        }

        if (!empty($formatted['user_agent'])) {
            $warnings[] = 'Captured client user-agent metadata will be removed with this log row.';
        }

        return [
            'log_id' => $logId,
            'log_label' => $this->frontLogLabel($record),
            'merchant_id' => $merchantId,
            'merchant_display' => (string)($formatted['merchant_display'] ?? ''),
            'path' => (string)($formatted['path'] ?? $formatted['url'] ?? '/'),
            'ip' => (string)($formatted['ip'] ?? ''),
            'has_payload' => $hasPayload,
            'can_delete' => true,
            'confirmation_phrase' => $this->frontLogDeleteConfirmationPhrase($logId),
            'blocking_reasons' => [],
            'summary' => [
                'delete_row_count' => 1,
                'payload_log_count' => $hasPayload ? 1 : 0,
                'merchant_linked_count' => $merchantId > 0 ? 1 : 0,
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<int, int> $logIds
     * @return array<string, mixed>
     */
    private function batchFrontLogDeleteAudit(array $logIds): array
    {
        $rows = $this->loadFrontLogRowsByIds($logIds);
        $rowsById = [];
        foreach ($rows as $row) {
            $rowsById[(int)($row['id'] ?? 0)] = $row;
        }

        $items = [];
        $deletableLogIds = [];
        $missingLogIds = [];
        $payloadCount = 0;
        $merchantIds = [];

        foreach ($logIds as $logId) {
            $row = $rowsById[$logId] ?? null;
            if ($row === null) {
                $missingLogIds[] = $logId;
                $items[] = [
                    'log_id' => $logId,
                    'log_label' => 'Front log #' . $logId,
                    'exists' => false,
                    'can_delete' => false,
                    'merchant_id' => 0,
                    'merchant_display' => 'Missing log',
                    'path' => '--',
                    'ip' => '',
                    'has_payload' => false,
                    'blocking_reasons' => ['This front log no longer exists. Refresh the selection and try again.'],
                    'warnings' => [],
                    'summary' => [
                        'delete_row_count' => 0,
                        'payload_log_count' => 0,
                        'merchant_linked_count' => 0,
                    ],
                ];
                continue;
            }

            $audit = $this->buildFrontLogDeleteAudit($row);
            $merchantId = (int)($audit['merchant_id'] ?? 0);
            if ($merchantId > 0) {
                $merchantIds[$merchantId] = true;
            }
            $payloadCount += (int)(($audit['summary'] ?? [])['payload_log_count'] ?? 0);
            $deletableLogIds[] = $logId;
            $items[] = [
                'log_id' => $logId,
                'log_label' => (string)($audit['log_label'] ?? ''),
                'exists' => true,
                'can_delete' => true,
                'merchant_id' => $merchantId,
                'merchant_display' => (string)($audit['merchant_display'] ?? ''),
                'path' => (string)($audit['path'] ?? '--'),
                'ip' => (string)($audit['ip'] ?? ''),
                'has_payload' => !empty($audit['has_payload']),
                'blocking_reasons' => [],
                'warnings' => array_values((array)($audit['warnings'] ?? [])),
                'summary' => (array)($audit['summary'] ?? []),
            ];
        }

        $summary = [
            'requested_count' => count($logIds),
            'existing_count' => count($logIds) - count($missingLogIds),
            'deletable_count' => count($deletableLogIds),
            'missing_count' => count($missingLogIds),
            'payload_log_count' => $payloadCount,
            'merchant_count' => count($merchantIds),
        ];

        $warnings = [];
        if ($summary['missing_count'] > 0) {
            $warnings[] = sprintf(
                '%d selected front log record(s) no longer exist and blocked the batch delete.',
                $summary['missing_count']
            );
        }
        if ($summary['payload_log_count'] > 0) {
            $warnings[] = sprintf(
                '%d selected front log record(s) include captured request payloads.',
                $summary['payload_log_count']
            );
        }

        return [
            'requested_log_ids' => $logIds,
            'deletable_log_ids' => $deletableLogIds,
            'missing_log_ids' => $missingLogIds,
            'confirmation_phrase' => $deletableLogIds === []
                ? ''
                : $this->batchFrontLogDeleteConfirmationPhrase($deletableLogIds),
            'can_delete_all' => $deletableLogIds !== [] && $summary['missing_count'] === 0,
            'items' => $items,
            'summary' => $summary,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function frontLogCleanupAudit(): array
    {
        $summary = [
            'total_count' => (int)Db::table('admin_front_log')->count('id'),
            'merchant_count' => (int)Db::table('admin_front_log')
                ->where('uid', '>', 0)
                ->distinct()
                ->count('uid'),
            'payload_log_count' => (int)Db::table('admin_front_log')
                ->whereNotNull('desc')
                ->where('desc', '<>', '')
                ->count('id'),
            'first_log_id' => (int)(Db::table('admin_front_log')->min('id') ?? 0),
            'last_log_id' => (int)(Db::table('admin_front_log')->max('id') ?? 0),
        ];

        $warnings = [];
        if ($summary['total_count'] === 0) {
            $warnings[] = 'No front log rows are available for cleanup.';
        } else {
            $warnings[] = 'This permanently removes every merchant front-log row and resets the table state.';
            if ($summary['payload_log_count'] > 0) {
                $warnings[] = sprintf(
                    '%d front log row(s) include captured request payloads that will also be removed.',
                    $summary['payload_log_count']
                );
            }
        }

        return [
            'can_cleanup' => $summary['total_count'] > 0,
            'confirmation_phrase' => $summary['total_count'] > 0
                ? $this->frontLogCleanupConfirmationPhrase($summary)
                : '',
            'summary' => $summary,
            'warnings' => $warnings,
        ];
    }

    private function deleteFrontLogRow(int $logId): void
    {
        Db::table('admin_front_log')
            ->where('id', $logId)
            ->delete();
    }

    /**
     * @param mixed $value
     * @return array<int, int>
     */
    private function normalizeLogIds(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('front log ids must be provided as an array');
        }

        $logIds = [];
        foreach ($value as $item) {
            if ($item === null || $item === '') {
                continue;
            }

            $logId = (int)$item;
            if ($logId <= 0) {
                throw new \InvalidArgumentException('front log ids must contain only positive integers');
            }

            $logIds[] = $logId;
        }

        $logIds = array_values(array_unique($logIds));
        if ($logIds === []) {
            throw new \InvalidArgumentException('at least one front log id is required');
        }

        if (count($logIds) > 200) {
            throw new \InvalidArgumentException('too many front logs were selected for one batch action');
        }

        return $logIds;
    }

    private function logIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function adminIdFromRequest(Request $request): int
    {
        return (int)(((array)($request->admin ?? []))['id'] ?? 0);
    }

    private function frontLogLabel(array $record): string
    {
        $formatted = AdminFrontLogFormatter::format($record);
        $merchantDisplay = trim((string)($formatted['merchant_display'] ?? ''));
        $path = trim((string)($formatted['path'] ?? $formatted['url'] ?? '/'));

        if ($merchantDisplay !== '') {
            return $merchantDisplay . ' / ' . ($path === '' ? '/' : $path);
        }

        return $path === '' ? ('Front log #' . (int)($record['id'] ?? 0)) : $path;
    }

    private function frontLogDeleteConfirmationPhrase(int $logId): string
    {
        return 'DELETE FRONT LOG ' . $logId;
    }

    /**
     * @param array<int, int> $logIds
     */
    private function batchFrontLogDeleteConfirmationPhrase(array $logIds): string
    {
        return sprintf(
            'DELETE FRONT LOG BATCH %d-%s',
            count($logIds),
            strtoupper(substr(md5(implode(',', $logIds)), 0, 6))
        );
    }

    private function frontLogCleanupConfirmationPhrase(array $summary): string
    {
        return sprintf(
            'CLEAN FRONT LOGS %d-%s',
            (int)($summary['total_count'] ?? 0),
            strtoupper(substr(md5(implode(':', [
                (int)($summary['total_count'] ?? 0),
                (int)($summary['first_log_id'] ?? 0),
                (int)($summary['last_log_id'] ?? 0),
            ])), 0, 6))
        );
    }

    private function recordAdminFrontLogDelete(Request $request, array $audit): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $logId = (int)($audit['log_id'] ?? 0);
        $label = $this->truncateLogText((string)($audit['log_label'] ?? ''), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/front-logs/' . $logId . '/delete',
            'desc' => sprintf(
                'front log delete log_id=%d label="%s" delete_rows=%d payload_logs=%d merchant_links=%d',
                $logId,
                $label,
                (int)($summary['delete_row_count'] ?? 0),
                (int)($summary['payload_log_count'] ?? 0),
                (int)($summary['merchant_linked_count'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminFrontLogBatchDelete(Request $request, array $audit): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $logIds = implode(',', array_map('intval', (array)($audit['deletable_log_ids'] ?? [])));
        $labels = implode(',', array_map(
            static function (array $item): string {
                $label = trim((string)($item['log_label'] ?? ''));
                $logId = (int)($item['log_id'] ?? 0);

                return $label !== '' ? $label : ('Front log #' . $logId);
            },
            array_values(array_filter(
                (array)($audit['items'] ?? []),
                static fn(array $item): bool => !empty($item['can_delete'])
            ))
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/front-logs/batch-delete',
            'desc' => sprintf(
                'front log batch delete requested=%d deleted=%d missing=%d payload_logs=%d merchant_count=%d log_ids="%s" labels="%s"',
                (int)($summary['requested_count'] ?? 0),
                (int)($summary['deletable_count'] ?? 0),
                (int)($summary['missing_count'] ?? 0),
                (int)($summary['payload_log_count'] ?? 0),
                (int)($summary['merchant_count'] ?? 0),
                $this->truncateLogText($logIds, 255),
                $this->truncateLogText($labels, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminFrontLogCleanup(Request $request, array $audit): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/front-logs/cleanup',
            'desc' => sprintf(
                'front log cleanup deleted=%d payload_logs=%d merchant_count=%d first_id=%d last_id=%d',
                (int)($summary['total_count'] ?? 0),
                (int)($summary['payload_log_count'] ?? 0),
                (int)($summary['merchant_count'] ?? 0),
                (int)($summary['first_log_id'] ?? 0),
                (int)($summary['last_log_id'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'SystemFrontLogs', $authMark);
    }

}
