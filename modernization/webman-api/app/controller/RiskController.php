<?php

namespace app\controller;

use app\support\AdminRouteAuthorization;
use app\support\AdminRiskFormatter;
use app\support\ApiResponse;
use app\support\RequestPayload;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class RiskController
{
    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $query = $this->riskQuery();
        $this->applyFilters($query, $request);

        $summary = $this->summary(clone $query);
        $total = (int)(clone $query)->count('ypay_risk.id');
        $rows = array_map(
            static fn($row): array => (array)$row,
            $query
                ->orderByDesc('ypay_risk.id')
                ->offset(($current - 1) * $size)
                ->limit($size)
                ->get()
                ->toArray()
        );

        return ApiResponse::success([
            'records' => $this->formatRiskRows($rows),
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => $summary,
        ]);
    }

    public function show(Request $request): Response
    {
        $id = $this->riskIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('risk id is required', 422, null, 422);
        }

        $row = $this->loadRiskRow($id);
        if ($row === null) {
            return ApiResponse::error('risk record not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminRiskFormatter::format($row),
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

        $riskId = (int)Db::table('ypay_risk')->insertGetId([
            'user_id' => $payload['user_id'],
            'name' => $payload['name'],
            'url' => $payload['url'],
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ]);

        $created = $this->loadRiskRow($riskId);
        if ($created === null) {
            return ApiResponse::error('created risk record could not be loaded', 500, null, 500);
        }

        $this->recordAdminRiskCreate($request, $created);

        return ApiResponse::success([
            'item' => AdminRiskFormatter::format($created),
            'created_risk_id' => $riskId,
            'created_risk_label' => $this->riskLabel($created),
        ], 'risk record created');
    }

    public function update(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->riskIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('risk id is required', 422, null, 422);
        }

        $record = $this->loadRiskRow($id);
        if ($record === null) {
            return ApiResponse::error('risk record not found', 404, null, 404);
        }

        try {
            $payload = $this->normalizeWritePayload(RequestPayload::all($request), $record);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table('ypay_risk')
            ->where('id', $id)
            ->update([
                'user_id' => $payload['user_id'],
                'name' => $payload['name'],
                'url' => $payload['url'],
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        $updated = $this->loadRiskRow($id);
        if ($updated === null) {
            return ApiResponse::error('updated risk record could not be loaded', 500, null, 500);
        }

        $this->recordAdminRiskUpdate($request, $record, $updated);

        return ApiResponse::success([
            'item' => AdminRiskFormatter::format($updated),
            'updated_risk_id' => $id,
            'updated_risk_label' => $this->riskLabel($updated),
        ], 'risk record updated');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->riskIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('risk id is required', 422, null, 422);
        }

        $record = $this->loadRiskRow($id);
        if ($record === null) {
            return ApiResponse::error('risk record not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminRiskFormatter::format($record),
            'audit' => $this->buildRiskDeleteAudit($record),
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->riskIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('risk id is required', 422, null, 422);
        }

        $record = $this->loadRiskRow($id);
        if ($record === null) {
            return ApiResponse::error('risk record not found', 404, null, 404);
        }

        $audit = $this->buildRiskDeleteAudit($record);
        $confirmationPhrase = trim((string)(RequestPayload::all($request)['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('confirmation phrase mismatch', 422, ['audit' => $audit], 422);
        }

        Db::transaction(function () use ($id): void {
            $this->deleteRiskRow($id);
        });

        $this->recordAdminRiskDelete($request, $audit);

        return ApiResponse::success([
            'deleted_risk_id' => $id,
            'deleted_risk_label' => (string)($audit['risk_label'] ?? ''),
            'audit' => $audit,
        ], 'risk record deleted');
    }

    public function batchDeleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $riskIds = $this->normalizeRiskIds(
                RequestPayload::all($request)['risk_ids']
                    ?? RequestPayload::all($request)['ids']
                    ?? []
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success([
            'audit' => $this->batchRiskDeleteAudit($riskIds),
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
            $riskIds = $this->normalizeRiskIds(
                $payload['risk_ids'] ?? $payload['ids'] ?? []
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $audit = $this->batchRiskDeleteAudit($riskIds);
        if (empty($audit['can_delete_all'])) {
            return ApiResponse::error(
                'selected risk records cannot be batch deleted until the selection is refreshed',
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
            foreach ((array)($audit['deletable_risk_ids'] ?? []) as $riskId) {
                $this->deleteRiskRow((int)$riskId);
            }
        });

        $this->recordAdminRiskBatchDelete($request, $audit);

        return ApiResponse::success([
            'deleted_risk_ids' => array_values(array_map('intval', (array)($audit['deletable_risk_ids'] ?? []))),
            'deleted_count' => (int)(($audit['summary'] ?? [])['deletable_count'] ?? 0),
            'audit' => $audit,
        ], 'risk batch delete completed');
    }

    private function riskQuery(): Builder
    {
        return Db::table('ypay_risk')
            ->leftJoin('ypay_user', 'ypay_risk.user_id', '=', 'ypay_user.id')
            ->select(
                'ypay_risk.id',
                'ypay_risk.user_id',
                'ypay_risk.name',
                'ypay_risk.url',
                'ypay_risk.create_time',
                'ypay_risk.update_time',
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
                    ->where('ypay_risk.name', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_risk.url', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_user.username', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_user.name', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_user.email', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_user.mobile', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder
                        ->orWhere('ypay_risk.id', (int)$keyword)
                        ->orWhere('ypay_risk.user_id', (int)$keyword);
                }
            });
        }

        $userId = trim((string)$request->get('user_id', ''));
        if ($userId !== '') {
            $query->where('ypay_risk.user_id', 'like', '%' . $userId . '%');
        }

        $name = trim((string)$request->get('name', ''));
        if ($name !== '') {
            $query->where('ypay_risk.name', 'like', '%' . $name . '%');
        }

        $url = trim((string)$request->get('url', ''));
        if ($url !== '') {
            $query->where('ypay_risk.url', 'like', '%' . $url . '%');
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', ''));
        $endDate = $this->normalizeDate((string)$request->get('end_date', ''));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('ypay_risk.create_time', '>=', $startDate . ' 00:00:00')
                ->where('ypay_risk.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
        }
    }

    private function summary(Builder $query): array
    {
        return [
            'total_count' => (int)(clone $query)->count('ypay_risk.id'),
            'merchant_count' => (int)(clone $query)
                ->where('ypay_risk.user_id', '>', 0)
                ->distinct()
                ->count('ypay_risk.user_id'),
            'named_count' => (int)(clone $query)
                ->whereNotNull('ypay_risk.name')
                ->where('ypay_risk.name', '<>', '')
                ->count('ypay_risk.id'),
            'source_count' => (int)(clone $query)
                ->whereNotNull('ypay_risk.url')
                ->where('ypay_risk.url', '<>', '')
                ->count('ypay_risk.id'),
            'today_count' => (int)(clone $query)
                ->where('ypay_risk.create_time', '>=', date('Y-m-d 00:00:00'))
                ->count('ypay_risk.id'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function formatRiskRows(array $rows): array
    {
        return array_map(
            static fn(array $row): array => AdminRiskFormatter::format($row),
            $rows
        );
    }

    private function loadRiskRow(int $riskId): ?array
    {
        $row = $this->riskQuery()
            ->where('ypay_risk.id', $riskId)
            ->first();

        return $row ? (array)$row : null;
    }

    /**
     * @param array<int, int> $riskIds
     * @return array<int, array<string, mixed>>
     */
    private function loadRiskRowsByIds(array $riskIds): array
    {
        if ($riskIds === []) {
            return [];
        }

        $rows = array_map(
            static fn($row): array => (array)$row,
            $this->riskQuery()
                ->whereIn('ypay_risk.id', $riskIds)
                ->get()
                ->toArray()
        );

        $grouped = [];
        foreach ($rows as $row) {
            $riskId = (int)($row['id'] ?? 0);
            if ($riskId > 0) {
                $grouped[$riskId] = $row;
            }
        }

        return $grouped;
    }

    private function normalizeWritePayload(array $payload, ?array $current = null): array
    {
        $userId = $this->normalizeRequiredUserId(
            array_key_exists('user_id', $payload) ? $payload['user_id'] : ($current['user_id'] ?? null)
        );
        $name = $this->normalizeOptionalString(
            array_key_exists('name', $payload) ? $payload['name'] : ($current['name'] ?? null),
            225,
            'risk product name'
        );
        $url = $this->normalizeOptionalString(
            array_key_exists('url', $payload) ? $payload['url'] : ($current['url'] ?? null),
            2500,
            'risk source url'
        );

        return [
            'user_id' => $userId,
            'name' => $name === '' ? null : $name,
            'url' => $url === '' ? null : $url,
        ];
    }

    private function normalizeRequiredUserId(mixed $value): int
    {
        if (is_bool($value) || is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('merchant id must be a scalar');
        }

        $normalized = trim((string)$value);
        if ($normalized === '' || !ctype_digit($normalized)) {
            throw new \InvalidArgumentException('merchant id is required');
        }

        $userId = (int)$normalized;
        if ($userId <= 0) {
            throw new \InvalidArgumentException('merchant id must be greater than 0');
        }

        if (!$this->merchantExists($userId)) {
            throw new \InvalidArgumentException('merchant does not exist');
        }

        return $userId;
    }

    private function merchantExists(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return Db::table('ypay_user')
            ->where('id', $userId)
            ->exists();
    }

    private function normalizeOptionalString(mixed $value, int $maxLength, string $field): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value) || is_array($value) || is_object($value)) {
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

    private function buildRiskDeleteAudit(array $record): array
    {
        $formatted = AdminRiskFormatter::format($record);
        $riskId = (int)($record['id'] ?? 0);
        $userId = (int)($record['user_id'] ?? 0);
        $name = trim((string)($record['name'] ?? ''));
        $url = trim((string)($record['url'] ?? ''));

        return [
            'risk_id' => $riskId,
            'risk_label' => $this->riskLabel($record),
            'merchant_id' => $userId,
            'merchant_display' => (string)($formatted['merchant_display'] ?? ''),
            'name' => $name,
            'url' => $url,
            'url_host' => (string)($formatted['url_host'] ?? ''),
            'can_delete' => true,
            'confirmation_phrase' => $this->riskDeleteConfirmationPhrase($riskId),
            'blocking_reasons' => [],
            'summary' => [
                'delete_row_count' => 1,
                'merchant_count' => $userId > 0 ? 1 : 0,
                'named_count' => $name === '' ? 0 : 1,
                'source_count' => $url === '' ? 0 : 1,
            ],
            'warnings' => [
                'Deleting a risk record permanently removes the database row.',
                'Automatic payment risk capture can create a new row later if the same merchant and source trigger again.',
            ],
        ];
    }

    /**
     * @param array<int, int> $riskIds
     */
    private function batchRiskDeleteAudit(array $riskIds): array
    {
        $rowsById = $this->loadRiskRowsByIds($riskIds);
        $items = [];
        $deletableRiskIds = [];
        $missingRiskIds = [];
        $merchantIds = [];
        $namedCount = 0;
        $sourceCount = 0;

        foreach ($riskIds as $riskId) {
            $row = $rowsById[$riskId] ?? null;
            if ($row === null) {
                $missingRiskIds[] = $riskId;
                $items[] = [
                    'risk_id' => $riskId,
                    'risk_label' => 'Risk #' . $riskId,
                    'merchant_id' => 0,
                    'merchant_display' => 'Unknown Merchant',
                    'name' => '',
                    'url' => '',
                    'url_host' => '',
                    'exists' => false,
                    'can_delete' => false,
                    'blocking_reasons' => ['This risk record no longer exists in the live table.'],
                    'warnings' => ['Refresh the selection before retrying the batch delete.'],
                    'summary' => [
                        'delete_row_count' => 0,
                        'merchant_count' => 0,
                        'named_count' => 0,
                        'source_count' => 0,
                    ],
                ];
                continue;
            }

            $audit = $this->buildRiskDeleteAudit($row);
            $deletableRiskIds[] = $riskId;

            if ((int)($audit['merchant_id'] ?? 0) > 0) {
                $merchantIds[(int)$audit['merchant_id']] = (int)$audit['merchant_id'];
            }
            $namedCount += (int)(($audit['summary'] ?? [])['named_count'] ?? 0);
            $sourceCount += (int)(($audit['summary'] ?? [])['source_count'] ?? 0);

            $items[] = [
                'risk_id' => $riskId,
                'risk_label' => (string)($audit['risk_label'] ?? $this->riskLabel($row)),
                'merchant_id' => (int)($audit['merchant_id'] ?? 0),
                'merchant_display' => (string)($audit['merchant_display'] ?? ''),
                'name' => (string)($audit['name'] ?? ''),
                'url' => (string)($audit['url'] ?? ''),
                'url_host' => (string)($audit['url_host'] ?? ''),
                'exists' => true,
                'can_delete' => true,
                'blocking_reasons' => [],
                'warnings' => array_values(array_map('strval', (array)($audit['warnings'] ?? []))),
                'summary' => (array)($audit['summary'] ?? []),
            ];
        }

        $summary = [
            'requested_count' => count($riskIds),
            'existing_count' => count($deletableRiskIds),
            'deletable_count' => count($deletableRiskIds),
            'missing_count' => count($missingRiskIds),
            'delete_row_count' => count($deletableRiskIds),
            'merchant_count' => count($merchantIds),
            'named_count' => $namedCount,
            'source_count' => $sourceCount,
        ];

        $warnings = [];
        if ($summary['missing_count'] > 0) {
            $warnings[] = sprintf(
                '%d selected risk record(s) are already missing and must be reselected before deletion.',
                $summary['missing_count']
            );
        }
        if ($summary['deletable_count'] > 0) {
            $warnings[] = 'Batch delete permanently removes the selected risk rows after one shared confirmation phrase is accepted.';
            $warnings[] = 'Automatic payment risk capture can create similar rows again later if new events match the same merchant or URL.';
        }

        return [
            'requested_risk_ids' => $riskIds,
            'deletable_risk_ids' => $deletableRiskIds,
            'missing_risk_ids' => $missingRiskIds,
            'confirmation_phrase' => $deletableRiskIds === []
                ? ''
                : $this->batchRiskDeleteConfirmationPhrase($deletableRiskIds),
            'can_delete_all' => $deletableRiskIds !== [] && $missingRiskIds === [],
            'items' => $items,
            'summary' => $summary,
            'warnings' => $warnings,
        ];
    }

    private function deleteRiskRow(int $riskId): void
    {
        Db::table('ypay_risk')
            ->where('id', $riskId)
            ->delete();
    }

    /**
     * @param mixed $value
     * @return array<int, int>
     */
    private function normalizeRiskIds(mixed $value, int $maxCount = 100): array
    {
        $items = [];

        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $items = preg_split('/\s*,\s*/', trim($value)) ?: [];
        } elseif (is_numeric($value)) {
            $items = [$value];
        }

        $riskIds = [];
        foreach ($items as $item) {
            if (is_bool($item) || is_array($item) || is_object($item)) {
                continue;
            }

            $normalized = trim((string)$item);
            if ($normalized === '' || !ctype_digit($normalized)) {
                continue;
            }

            $riskId = (int)$normalized;
            if ($riskId > 0) {
                $riskIds[$riskId] = $riskId;
            }
        }

        $riskIds = array_values($riskIds);
        sort($riskIds);

        if ($riskIds === []) {
            throw new \InvalidArgumentException('risk ids are required');
        }

        if (count($riskIds) > $maxCount) {
            throw new \InvalidArgumentException('too many risk rows were selected for one batch action');
        }

        return $riskIds;
    }

    private function riskIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function adminIdFromRequest(Request $request): int
    {
        return (int)(((array)($request->admin ?? []))['id'] ?? 0);
    }

    private function riskLabel(array $record): string
    {
        $name = trim((string)($record['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $url = trim((string)($record['url'] ?? ''));
        if ($url !== '') {
            return $url;
        }

        return 'Risk #' . (int)($record['id'] ?? 0);
    }

    private function riskDeleteConfirmationPhrase(int $riskId): string
    {
        return 'DELETE RISK ' . $riskId;
    }

    /**
     * @param array<int, int> $riskIds
     */
    private function batchRiskDeleteConfirmationPhrase(array $riskIds): string
    {
        return sprintf(
            'DELETE RISK BATCH %d-%s',
            count($riskIds),
            strtoupper(substr(md5(implode(',', $riskIds)), 0, 6))
        );
    }

    private function recordAdminRiskCreate(Request $request, array $record): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $riskId = (int)($record['id'] ?? 0);
        $riskLabel = $this->truncateLogText($this->riskLabel($record), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/risks/create',
            'desc' => sprintf(
                'risk create risk_id=%d label="%s" merchant_id=%d has_name=%d has_url=%d',
                $riskId,
                $riskLabel,
                (int)($record['user_id'] ?? 0),
                trim((string)($record['name'] ?? '')) === '' ? 0 : 1,
                trim((string)($record['url'] ?? '')) === '' ? 0 : 1
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminRiskUpdate(Request $request, array $before, array $after): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $riskId = (int)($after['id'] ?? 0);
        $riskLabel = $this->truncateLogText($this->riskLabel($after), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/risks/' . $riskId . '/update',
            'desc' => sprintf(
                'risk update risk_id=%d label="%s" merchant_id=%d merchant_changed=%d name_changed=%d url_changed=%d',
                $riskId,
                $riskLabel,
                (int)($after['user_id'] ?? 0),
                (int)($before['user_id'] ?? 0) === (int)($after['user_id'] ?? 0) ? 0 : 1,
                trim((string)($before['name'] ?? '')) === trim((string)($after['name'] ?? '')) ? 0 : 1,
                trim((string)($before['url'] ?? '')) === trim((string)($after['url'] ?? '')) ? 0 : 1
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminRiskDelete(Request $request, array $audit): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $riskId = (int)($audit['risk_id'] ?? 0);
        $riskLabel = $this->truncateLogText((string)($audit['risk_label'] ?? ''), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/risks/' . $riskId . '/delete',
            'desc' => sprintf(
                'risk delete risk_id=%d label="%s" merchant_id=%d delete_rows=%d named=%d source=%d',
                $riskId,
                $riskLabel,
                (int)($audit['merchant_id'] ?? 0),
                (int)($summary['delete_row_count'] ?? 0),
                (int)($summary['named_count'] ?? 0),
                (int)($summary['source_count'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminRiskBatchDelete(Request $request, array $audit): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $riskIds = implode(',', array_map('intval', (array)($audit['deletable_risk_ids'] ?? [])));
        $riskLabels = implode(',', array_map(
            static function (array $item): string {
                $label = trim((string)($item['risk_label'] ?? ''));
                $riskId = (int)($item['risk_id'] ?? 0);
                return $label !== '' ? $label : ('Risk #' . $riskId);
            },
            array_values(array_filter(
                (array)($audit['items'] ?? []),
                static fn(array $item): bool => !empty($item['can_delete'])
            ))
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/risks/batch-delete',
            'desc' => sprintf(
                'risk batch delete requested=%d deleted=%d missing=%d merchants=%d named=%d source=%d risk_ids="%s" labels="%s"',
                (int)($summary['requested_count'] ?? 0),
                (int)($summary['deletable_count'] ?? 0),
                (int)($summary['missing_count'] ?? 0),
                (int)($summary['merchant_count'] ?? 0),
                (int)($summary['named_count'] ?? 0),
                (int)($summary['source_count'] ?? 0),
                $this->truncateLogText($riskIds, 255),
                $this->truncateLogText($riskLabels, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'RiskRecords', $authMark);
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return null;
        }

        return substr($value, 0, 10);
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
