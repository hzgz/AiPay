<?php

namespace app\controller;

use app\support\AdminCdkFormatter;
use app\support\AdminRouteAuthorization;
use app\support\ApiResponse;
use app\support\RequestPayload;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class CdkController
{
    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $query = $this->cdkQuery();
        $this->applyFilters($query, $request);

        $summary = $this->summary(clone $query);
        $total = (int)(clone $query)->count('ypay_cdk.id');
        $rows = array_map(
            static fn($row): array => (array)$row,
            $query
                ->orderByDesc('ypay_cdk.id')
                ->offset(($current - 1) * $size)
                ->limit($size)
                ->get()
                ->toArray()
        );

        return ApiResponse::success([
            'records' => $this->formatCdkRows($rows),
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => $summary,
        ]);
    }

    public function show(Request $request): Response
    {
        $id = $this->cdkIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('cdk id is required', 422, null, 422);
        }

        $row = $this->loadCdkRow($id);
        if ($row === null) {
            return ApiResponse::error('cdk not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminCdkFormatter::format($row),
        ]);
    }

    public function create(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'add');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $payload = $this->normalizeCreatePayload(RequestPayload::all($request));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $now = date('Y-m-d H:i:s');
        $generatedCards = [];
        $generatedCodes = [];

        Db::transaction(function () use ($payload, $now, &$generatedCards, &$generatedCodes): void {
            for ($index = 0; $index < $payload['count']; $index++) {
                $code = $this->generateUniqueCode($payload['prefix']);
                $cdkId = (int)Db::table('ypay_cdk')->insertGetId([
                    'type' => $payload['type'],
                    'value' => $payload['value'],
                    'code' => $code,
                    'status' => 0,
                    'create_time' => $now,
                ]);

                $generatedCards[] = [
                    'id' => $cdkId,
                    'type' => $payload['type'],
                    'type_label' => AdminCdkFormatter::typeLabel($payload['type']),
                    'value' => $payload['value'],
                    'value_label' => AdminCdkFormatter::valueLabel(
                        $payload['type'],
                        $payload['value'],
                        $payload['vip_name']
                    ),
                    'code' => $code,
                ];
                $generatedCodes[] = $code;
            }
        });

        $createdIds = array_values(array_map(
            static fn(array $item): int => (int)$item['id'],
            $generatedCards
        ));
        $createdRows = $this->loadCdkRowsByIds($createdIds);
        $formattedCards = array_map(
            static fn(array $row): array => AdminCdkFormatter::format($row),
            $createdRows
        );

        $this->recordAdminCdkCreate($request, $payload, $generatedCards);

        return ApiResponse::success([
            'created_count' => count($generatedCards),
            'created_cdk_ids' => $createdIds,
            'created_type' => $payload['type'],
            'created_type_label' => AdminCdkFormatter::typeLabel($payload['type']),
            'value_label' => AdminCdkFormatter::valueLabel(
                $payload['type'],
                $payload['value'],
                $payload['vip_name']
            ),
            'prefix' => $payload['prefix'],
            'generated_codes' => $generatedCodes,
            'generated_cards' => $generatedCards,
            'records' => $formattedCards,
        ], 'cdk batch created');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->cdkIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('cdk id is required', 422, null, 422);
        }

        $record = $this->loadCdkRow($id);
        if ($record === null) {
            return ApiResponse::error('cdk not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminCdkFormatter::format($record),
            'audit' => $this->buildCdkDeleteAudit($record),
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->cdkIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('cdk id is required', 422, null, 422);
        }

        $record = $this->loadCdkRow($id);
        if ($record === null) {
            return ApiResponse::error('cdk not found', 404, null, 404);
        }

        $audit = $this->buildCdkDeleteAudit($record);
        $confirmationPhrase = trim((string)(RequestPayload::all($request)['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('confirmation phrase mismatch', 422, ['audit' => $audit], 422);
        }

        Db::transaction(function () use ($id): void {
            $this->deleteCdkRow($id);
        });

        $this->recordAdminCdkDelete($request, $audit);

        return ApiResponse::success([
            'deleted_cdk_id' => $id,
            'deleted_cdk_label' => (string)($audit['cdk_label'] ?? ''),
            'audit' => $audit,
        ], 'cdk deleted');
    }

    public function batchDeleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $cdkIds = $this->normalizeCdkIds(
                RequestPayload::all($request)['cdk_ids']
                    ?? RequestPayload::all($request)['ids']
                    ?? []
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success([
            'audit' => $this->batchCdkDeleteAudit($cdkIds),
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
            $cdkIds = $this->normalizeCdkIds($payload['cdk_ids'] ?? $payload['ids'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $audit = $this->batchCdkDeleteAudit($cdkIds);
        if (empty($audit['can_delete_all'])) {
            return ApiResponse::error(
                'selected cdks cannot be batch deleted until the selection is refreshed',
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
            foreach ((array)($audit['deletable_cdk_ids'] ?? []) as $cdkId) {
                $this->deleteCdkRow((int)$cdkId);
            }
        });

        $this->recordAdminCdkBatchDelete($request, $audit);

        return ApiResponse::success([
            'deleted_cdk_ids' => array_values(array_map('intval', (array)($audit['deletable_cdk_ids'] ?? []))),
            'deleted_count' => (int)(($audit['summary'] ?? [])['deletable_count'] ?? 0),
            'audit' => $audit,
        ], 'cdk batch delete completed');
    }

    public function cleanupUsedAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        return ApiResponse::success([
            'audit' => $this->usedCleanupAudit(),
        ]);
    }

    public function cleanupUsed(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $audit = $this->usedCleanupAudit();
        if (empty($audit['can_cleanup'])) {
            return ApiResponse::error('no used cdks are available for cleanup', 422, ['audit' => $audit], 422);
        }

        $confirmationPhrase = trim((string)(RequestPayload::all($request)['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('confirmation phrase mismatch', 422, ['audit' => $audit], 422);
        }

        Db::transaction(function (): void {
            Db::table('ypay_cdk')
                ->where('status', 1)
                ->delete();
        });

        $this->recordAdminCdkCleanupUsed($request, $audit);

        return ApiResponse::success([
            'deleted_count' => (int)(($audit['summary'] ?? [])['used_count'] ?? 0),
            'audit' => $audit,
        ], 'used cdks cleaned up');
    }

    private function cdkQuery(): Builder
    {
        return Db::table('ypay_cdk')
            ->leftJoin('ypay_vip', 'ypay_cdk.value', '=', 'ypay_vip.id')
            ->select(
                'ypay_cdk.id',
                'ypay_cdk.type',
                'ypay_cdk.value',
                'ypay_cdk.code',
                'ypay_cdk.status',
                'ypay_cdk.create_time',
                'ypay_vip.name as vip_name'
            );
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('ypay_cdk.code', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_cdk.value', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_vip.name', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('ypay_cdk.id', (int)$keyword);
                }
            });
        }

        $type = trim((string)$request->get('type', ''));
        if ($type !== '' && in_array($type, ['1', '2'], true)) {
            $query->where('ypay_cdk.type', (int)$type);
        }

        $status = trim((string)$request->get('status', ''));
        if ($status !== '' && in_array($status, ['0', '1'], true)) {
            $query->where('ypay_cdk.status', (int)$status);
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', ''));
        $endDate = $this->normalizeDate((string)$request->get('end_date', ''));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('ypay_cdk.create_time', '>=', $startDate . ' 00:00:00')
                ->where('ypay_cdk.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
        }
    }

    private function summary(Builder $query): array
    {
        return [
            'unused_count' => (int)(clone $query)->where('ypay_cdk.status', 0)->count('ypay_cdk.id'),
            'used_count' => (int)(clone $query)->where('ypay_cdk.status', 1)->count('ypay_cdk.id'),
            'balance_card_count' => (int)(clone $query)->where('ypay_cdk.type', 1)->count('ypay_cdk.id'),
            'vip_card_count' => (int)(clone $query)->where('ypay_cdk.type', 2)->count('ypay_cdk.id'),
            'total_face_amount' => AdminCdkFormatter::toFloat(
                (clone $query)->where('ypay_cdk.type', 1)->sum('ypay_cdk.value'),
                2
            ),
            'code_ready_count' => (int)(clone $query)
                ->whereNotNull('ypay_cdk.code')
                ->where('ypay_cdk.code', '<>', '')
                ->count('ypay_cdk.id'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function formatCdkRows(array $rows): array
    {
        return array_map(
            static fn(array $row): array => AdminCdkFormatter::format($row),
            $rows
        );
    }

    private function loadCdkRow(int $cdkId): ?array
    {
        $row = $this->cdkQuery()
            ->where('ypay_cdk.id', $cdkId)
            ->first();

        return $row ? (array)$row : null;
    }

    /**
     * @param array<int, int> $cdkIds
     * @return array<int, array<string, mixed>>
     */
    private function loadCdkRowsByIds(array $cdkIds): array
    {
        if ($cdkIds === []) {
            return [];
        }

        $rows = array_map(
            static fn($row): array => (array)$row,
            $this->cdkQuery()
                ->whereIn('ypay_cdk.id', $cdkIds)
                ->orderByDesc('ypay_cdk.id')
                ->get()
                ->toArray()
        );

        return $rows;
    }

    /**
     * @return array{type:int,count:int,value:string,prefix:string,vip_name:?string}
     */
    private function normalizeCreatePayload(array $payload): array
    {
        $type = $this->normalizeType($payload['type'] ?? $payload['cdkType'] ?? null);
        $count = $this->normalizeCount($payload['count'] ?? $payload['num'] ?? null);
        $prefix = $this->normalizePrefix($payload['prefix'] ?? $payload['diyPrefix'] ?? '');

        if ($type === 1) {
            $amount = $this->normalizeAmount($payload['amount'] ?? $payload['value'] ?? null);

            return [
                'type' => 1,
                'count' => $count,
                'value' => number_format($amount, 2, '.', ''),
                'prefix' => $prefix,
                'vip_name' => null,
            ];
        }

        $vipId = $this->normalizeVipId($payload['vip_id'] ?? $payload['vip'] ?? null);
        $vip = Db::table('ypay_vip')
            ->select('id', 'name')
            ->where('id', $vipId)
            ->first();

        if (!$vip) {
            throw new \InvalidArgumentException('selected vip package was not found');
        }

        return [
            'type' => 2,
            'count' => $count,
            'value' => (string)$vipId,
            'prefix' => $prefix,
            'vip_name' => trim((string)(((array)$vip)['name'] ?? '')) ?: ('VIP #' . $vipId),
        ];
    }

    private function normalizeType(mixed $value): int
    {
        if (is_int($value)) {
            $type = $value;
        } elseif (is_string($value) && ctype_digit(trim($value))) {
            $type = (int)trim($value);
        } else {
            throw new \InvalidArgumentException('cdk type must be 1 or 2');
        }

        if (!in_array($type, [1, 2], true)) {
            throw new \InvalidArgumentException('cdk type must be 1 or 2');
        }

        return $type;
    }

    private function normalizeCount(mixed $value): int
    {
        if (is_int($value)) {
            $count = $value;
        } elseif (is_string($value) && ctype_digit(trim($value))) {
            $count = (int)trim($value);
        } else {
            throw new \InvalidArgumentException('cdk create count must be an integer');
        }

        if ($count < 1 || $count > 200) {
            throw new \InvalidArgumentException('cdk create count must be between 1 and 200');
        }

        return $count;
    }

    private function normalizeAmount(mixed $value): float
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('balance amount must be numeric');
        }

        $amount = round((float)$value, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('balance amount must be greater than 0');
        }

        if ($amount > 99999999) {
            throw new \InvalidArgumentException('balance amount is too large');
        }

        return $amount;
    }

    private function normalizeVipId(mixed $value): int
    {
        if (is_int($value)) {
            $vipId = $value;
        } elseif (is_string($value) && ctype_digit(trim($value))) {
            $vipId = (int)trim($value);
        } else {
            throw new \InvalidArgumentException('vip package id is required');
        }

        if ($vipId <= 0) {
            throw new \InvalidArgumentException('vip package id is required');
        }

        return $vipId;
    }

    private function normalizePrefix(mixed $value): string
    {
        $prefix = strtoupper(trim((string)$value));
        if ($prefix === '') {
            return '';
        }

        $prefix = preg_replace('/[^A-Z0-9_-]+/', '', $prefix) ?? '';
        $prefix = trim($prefix, '_-');
        if ($prefix === '') {
            throw new \InvalidArgumentException('cdk prefix must contain letters or numbers');
        }

        if (strlen($prefix) > 20) {
            throw new \InvalidArgumentException('cdk prefix must be 20 characters or fewer');
        }

        return $prefix;
    }

    private function generateUniqueCode(string $prefix): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $suffix = strtoupper(substr(bin2hex(random_bytes(10)), 0, 15));
            $code = $prefix === '' ? $suffix : ($prefix . '_' . $suffix);
            $exists = Db::table('ypay_cdk')
                ->where('code', $code)
                ->exists();

            if (!$exists) {
                return $code;
            }
        }

        throw new \RuntimeException('failed to generate a unique cdk code');
    }

    private function buildCdkDeleteAudit(array $record): array
    {
        $formatted = AdminCdkFormatter::format($record);
        $cdkId = (int)($record['id'] ?? 0);
        $status = (int)($record['status'] ?? 0);
        $type = isset($record['type']) ? (int)$record['type'] : null;
        $warnings = [];

        if ($status === 0) {
            $warnings[] = 'This unused card can no longer be redeemed after deletion.';
        } else {
            $warnings[] = 'This used card record will be permanently removed from the audit trail.';
        }

        if (!empty($formatted['code_masked'])) {
            $warnings[] = 'The list and detail views keep the code masked even though the record is deletable.';
        }

        return [
            'cdk_id' => $cdkId,
            'cdk_label' => $this->cdkLabel($record),
            'type' => $type,
            'type_label' => (string)($formatted['type_label'] ?? ''),
            'status' => $status,
            'status_label' => (string)($formatted['status_label'] ?? ''),
            'is_used' => $status === 1,
            'can_delete' => true,
            'confirmation_phrase' => $this->cdkDeleteConfirmationPhrase($cdkId),
            'blocking_reasons' => [],
            'summary' => [
                'delete_row_count' => 1,
                'used_count' => $status === 1 ? 1 : 0,
                'unused_count' => $status === 0 ? 1 : 0,
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<int, int> $cdkIds
     */
    private function batchCdkDeleteAudit(array $cdkIds): array
    {
        $rows = $this->loadCdkRowsByIds($cdkIds);
        $rowsById = [];
        foreach ($rows as $row) {
            $rowsById[(int)($row['id'] ?? 0)] = $row;
        }

        $items = [];
        $deletableCdkIds = [];
        $missingCdkIds = [];
        $usedCount = 0;
        $unusedCount = 0;

        foreach ($cdkIds as $cdkId) {
            $row = $rowsById[$cdkId] ?? null;
            if ($row === null) {
                $missingCdkIds[] = $cdkId;
                $items[] = [
                    'cdk_id' => $cdkId,
                    'cdk_label' => 'CDK #' . $cdkId,
                    'exists' => false,
                    'can_delete' => false,
                    'type' => null,
                    'type_label' => 'Missing',
                    'status' => null,
                    'status_label' => 'Missing',
                    'is_used' => false,
                    'blocking_reasons' => ['This cdk record no longer exists in the live table.'],
                    'warnings' => ['Refresh the selection before retrying the batch delete.'],
                    'summary' => [
                        'delete_row_count' => 0,
                        'used_count' => 0,
                        'unused_count' => 0,
                    ],
                ];
                continue;
            }

            $audit = $this->buildCdkDeleteAudit($row);
            $deletableCdkIds[] = $cdkId;
            $usedCount += (int)(($audit['summary'] ?? [])['used_count'] ?? 0);
            $unusedCount += (int)(($audit['summary'] ?? [])['unused_count'] ?? 0);
            $items[] = [
                'cdk_id' => $cdkId,
                'cdk_label' => (string)($audit['cdk_label'] ?? $this->cdkLabel($row)),
                'exists' => true,
                'can_delete' => true,
                'type' => $audit['type'] ?? null,
                'type_label' => (string)($audit['type_label'] ?? ''),
                'status' => $audit['status'] ?? null,
                'status_label' => (string)($audit['status_label'] ?? ''),
                'is_used' => !empty($audit['is_used']),
                'blocking_reasons' => [],
                'warnings' => array_values(array_map('strval', (array)($audit['warnings'] ?? []))),
                'summary' => (array)($audit['summary'] ?? []),
            ];
        }

        $summary = [
            'requested_count' => count($cdkIds),
            'existing_count' => count($deletableCdkIds),
            'deletable_count' => count($deletableCdkIds),
            'missing_count' => count($missingCdkIds),
            'used_count' => $usedCount,
            'unused_count' => $unusedCount,
        ];

        $warnings = [];
        if ($summary['missing_count'] > 0) {
            $warnings[] = sprintf(
                '%d selected cdk record(s) are already missing and must be reselected before deletion.',
                $summary['missing_count']
            );
        }
        if ($summary['unused_count'] > 0) {
            $warnings[] = sprintf(
                '%d unused cdk record(s) will lose their redemption codes permanently.',
                $summary['unused_count']
            );
        }
        if ($summary['used_count'] > 0) {
            $warnings[] = sprintf(
                '%d used cdk record(s) will be removed from the historical ledger.',
                $summary['used_count']
            );
        }

        $canDeleteAll = $deletableCdkIds !== [] && $missingCdkIds === [];

        return [
            'requested_cdk_ids' => $cdkIds,
            'deletable_cdk_ids' => $deletableCdkIds,
            'missing_cdk_ids' => $missingCdkIds,
            'confirmation_phrase' => $deletableCdkIds === []
                ? ''
                : $this->batchCdkDeleteConfirmationPhrase($deletableCdkIds),
            'can_delete_all' => $canDeleteAll,
            'items' => $items,
            'summary' => $summary,
            'warnings' => $warnings,
        ];
    }

    private function usedCleanupAudit(): array
    {
        $rows = array_map(
            static fn($row): array => (array)$row,
            $this->cdkQuery()
                ->where('ypay_cdk.status', 1)
                ->orderByDesc('ypay_cdk.id')
                ->limit(200)
                ->get()
                ->toArray()
        );

        $usedIds = array_values(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $rows
        ));
        $usedBalanceCount = 0;
        $usedVipCount = 0;

        foreach ($rows as $row) {
            $type = isset($row['type']) ? (int)$row['type'] : null;
            if ($type === 1) {
                $usedBalanceCount++;
            } elseif ($type === 2) {
                $usedVipCount++;
            }
        }

        $summary = [
            'used_count' => count($usedIds),
            'balance_card_count' => $usedBalanceCount,
            'vip_card_count' => $usedVipCount,
        ];

        return [
            'used_cdk_ids' => $usedIds,
            'can_cleanup' => $summary['used_count'] > 0,
            'confirmation_phrase' => $summary['used_count'] > 0
                ? $this->cleanupUsedConfirmationPhrase($usedIds)
                : '',
            'summary' => $summary,
            'warnings' => $summary['used_count'] > 0
                ? [
                    sprintf('%d used cdk record(s) will be permanently removed.', $summary['used_count']),
                    'Cleanup affects only rows where status = 1 and does not touch unused cards.',
                ]
                : ['No used cdk records are currently available for cleanup.'],
        ];
    }

    private function deleteCdkRow(int $cdkId): void
    {
        Db::table('ypay_cdk')
            ->where('id', $cdkId)
            ->delete();
    }

    /**
     * @param mixed $value
     * @return array<int, int>
     */
    private function normalizeCdkIds(mixed $value, int $maxCount = 500): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('cdk ids are required');
        }

        $cdkIds = [];
        foreach ($value as $item) {
            if (is_int($item)) {
                $cdkId = $item;
            } else {
                $normalized = trim((string)$item);
                if ($normalized === '' || !ctype_digit($normalized)) {
                    continue;
                }
                $cdkId = (int)$normalized;
            }

            if ($cdkId > 0) {
                $cdkIds[$cdkId] = $cdkId;
            }
        }

        $cdkIds = array_values($cdkIds);
        sort($cdkIds);

        if ($cdkIds === []) {
            throw new \InvalidArgumentException('cdk ids are required');
        }

        if (count($cdkIds) > $maxCount) {
            throw new \InvalidArgumentException('too many cdks were selected for one batch action');
        }

        return $cdkIds;
    }

    private function cdkIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function adminIdFromRequest(Request $request): int
    {
        return (int)(((array)($request->admin ?? []))['id'] ?? 0);
    }

    private function cdkLabel(array $record): string
    {
        $type = isset($record['type']) ? (int)$record['type'] : null;
        $value = trim((string)($record['value'] ?? ''));
        $vipName = trim((string)($record['vip_name'] ?? ''));

        return AdminCdkFormatter::typeLabel($type)
            . ' / '
            . AdminCdkFormatter::valueLabel($type, $value === '' ? null : $value, $vipName === '' ? null : $vipName);
    }

    private function cdkDeleteConfirmationPhrase(int $cdkId): string
    {
        return 'DELETE CDK ' . $cdkId;
    }

    /**
     * @param array<int, int> $cdkIds
     */
    private function batchCdkDeleteConfirmationPhrase(array $cdkIds): string
    {
        return sprintf(
            'DELETE CDK BATCH %d-%s',
            count($cdkIds),
            strtoupper(substr(md5(implode(',', $cdkIds)), 0, 6))
        );
    }

    /**
     * @param array<int, int> $usedIds
     */
    private function cleanupUsedConfirmationPhrase(array $usedIds): string
    {
        return sprintf(
            'CLEAN USED CDKS %d-%s',
            count($usedIds),
            strtoupper(substr(md5(implode(',', $usedIds)), 0, 6))
        );
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'FinanceCdks', $authMark);
    }

    /**
     * @param array<int, array<string, mixed>> $generatedCards
     */
    private function recordAdminCdkCreate(Request $request, array $payload, array $generatedCards): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $createdIds = implode(',', array_map(
            static fn(array $item): int => (int)($item['id'] ?? 0),
            $generatedCards
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/cdks/create',
            'desc' => sprintf(
                'cdk create type=%d count=%d prefix="%s" value="%s" created_ids="%s"',
                (int)$payload['type'],
                count($generatedCards),
                $this->truncateLogText((string)$payload['prefix'], 40),
                $this->truncateLogText((string)$payload['value'], 60),
                $this->truncateLogText($createdIds, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminCdkDelete(Request $request, array $audit): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $cdkId = (int)($audit['cdk_id'] ?? 0);
        $label = $this->truncateLogText((string)($audit['cdk_label'] ?? ''), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/cdks/' . $cdkId . '/delete',
            'desc' => sprintf(
                'cdk delete cdk_id=%d label="%s" delete_rows=%d used=%d unused=%d',
                $cdkId,
                $label,
                (int)($summary['delete_row_count'] ?? 0),
                (int)($summary['used_count'] ?? 0),
                (int)($summary['unused_count'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminCdkBatchDelete(Request $request, array $audit): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $cdkIds = implode(',', array_map('intval', (array)($audit['deletable_cdk_ids'] ?? [])));
        $labels = implode(',', array_map(
            static function (array $item): string {
                $label = trim((string)($item['cdk_label'] ?? ''));
                $cdkId = (int)($item['cdk_id'] ?? 0);
                return $label !== '' ? $label : ('CDK #' . $cdkId);
            },
            array_values(array_filter(
                (array)($audit['items'] ?? []),
                static fn(array $item): bool => !empty($item['can_delete'])
            ))
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/cdks/batch-delete',
            'desc' => sprintf(
                'cdk batch delete requested=%d deleted=%d missing=%d used=%d unused=%d cdk_ids="%s" labels="%s"',
                (int)($summary['requested_count'] ?? 0),
                (int)($summary['deletable_count'] ?? 0),
                (int)($summary['missing_count'] ?? 0),
                (int)($summary['used_count'] ?? 0),
                (int)($summary['unused_count'] ?? 0),
                $this->truncateLogText($cdkIds, 255),
                $this->truncateLogText($labels, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminCdkCleanupUsed(Request $request, array $audit): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/cdks/cleanup-used',
            'desc' => sprintf(
                'cdk cleanup used deleted=%d balance_cards=%d vip_cards=%d',
                (int)($summary['used_count'] ?? 0),
                (int)($summary['balance_card_count'] ?? 0),
                (int)($summary['vip_card_count'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
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
