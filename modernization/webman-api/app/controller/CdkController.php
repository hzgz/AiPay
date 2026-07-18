<?php

namespace app\controller;

use app\support\AdminCdkFormatter;
use app\support\AdminRouteAuthorization;
use app\support\ApiResponse;
use app\support\BusinessTable;
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
        $total = (int)(clone $query)->count('cdk.id');
        $rows = array_map(
            static fn($row): array => (array)$row,
            $query
                ->orderByDesc('cdk.id')
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
            return ApiResponse::error('CDK 编号不能为空', 422, null, 422);
        }

        $row = $this->loadCdkRow($id);
        if ($row === null) {
            return ApiResponse::error('CDK 记录不存在', 404, null, 404);
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
                $cdkId = (int)Db::table(BusinessTable::cdk())->insertGetId([
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
        ], 'CDK 已批量创建');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->cdkIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('CDK 编号不能为空', 422, null, 422);
        }

        $record = $this->loadCdkRow($id);
        if ($record === null) {
            return ApiResponse::error('CDK 记录不存在', 404, null, 404);
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
            return ApiResponse::error('CDK 编号不能为空', 422, null, 422);
        }

        $record = $this->loadCdkRow($id);
        if ($record === null) {
            return ApiResponse::error('CDK 记录不存在', 404, null, 404);
        }

        $audit = $this->buildCdkDeleteAudit($record);
        $confirmationPhrase = trim((string)(RequestPayload::all($request)['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('确认短语不匹配', 422, ['audit' => $audit], 422);
        }

        Db::transaction(function () use ($id): void {
            $this->deleteCdkRow($id);
        });

        $this->recordAdminCdkDelete($request, $audit);

        return ApiResponse::success([
            'deleted_cdk_id' => $id,
            'deleted_cdk_label' => (string)($audit['cdk_label'] ?? ''),
            'audit' => $audit,
        ], 'CDK 已删除');
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
                '所选 CDK 已发生变化，请刷新选择后再执行批量删除',
                422,
                ['audit' => $audit],
                422
            );
        }

        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('确认短语不匹配', 422, ['audit' => $audit], 422);
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
        ], 'CDK 已批量删除');
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
            return ApiResponse::error('当前没有可清理的已使用 CDK', 422, ['audit' => $audit], 422);
        }

        $confirmationPhrase = trim((string)(RequestPayload::all($request)['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('确认短语不匹配', 422, ['audit' => $audit], 422);
        }

        Db::transaction(function (): void {
            Db::table(BusinessTable::cdk())
                ->where('status', 1)
                ->delete();
        });

        $this->recordAdminCdkCleanupUsed($request, $audit);

        return ApiResponse::success([
            'deleted_count' => (int)(($audit['summary'] ?? [])['used_count'] ?? 0),
            'audit' => $audit,
        ], '已使用 CDK 已清理');
    }

    private function cdkQuery(): Builder
    {
        return Db::table(BusinessTable::cdk('cdk'))
            ->leftJoin(BusinessTable::vip('vip'), 'cdk.value', '=', 'vip.id')
            ->select(
                'cdk.id',
                'cdk.type',
                'cdk.value',
                'cdk.code',
                'cdk.status',
                'cdk.create_time',
                'vip.name as vip_name'
            );
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('cdk.code', 'like', '%' . $keyword . '%')
                    ->orWhere('cdk.value', 'like', '%' . $keyword . '%')
                    ->orWhere('vip.name', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('cdk.id', (int)$keyword);
                }
            });
        }

        $type = trim((string)$request->get('type', ''));
        if ($type !== '' && in_array($type, ['1', '2'], true)) {
            $query->where('cdk.type', (int)$type);
        }

        $status = trim((string)$request->get('status', ''));
        if ($status !== '' && in_array($status, ['0', '1'], true)) {
            $query->where('cdk.status', (int)$status);
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', ''));
        $endDate = $this->normalizeDate((string)$request->get('end_date', ''));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('cdk.create_time', '>=', $startDate . ' 00:00:00')
                ->where('cdk.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
        }
    }

    private function summary(Builder $query): array
    {
        return [
            'unused_count' => (int)(clone $query)->where('cdk.status', 0)->count('cdk.id'),
            'used_count' => (int)(clone $query)->where('cdk.status', 1)->count('cdk.id'),
            'balance_card_count' => (int)(clone $query)->where('cdk.type', 1)->count('cdk.id'),
            'vip_card_count' => (int)(clone $query)->where('cdk.type', 2)->count('cdk.id'),
            'total_face_amount' => AdminCdkFormatter::toFloat(
                (clone $query)->where('cdk.type', 1)->sum('cdk.value'),
                2
            ),
            'code_ready_count' => (int)(clone $query)
                ->whereNotNull('cdk.code')
                ->where('cdk.code', '<>', '')
                ->count('cdk.id'),
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
            ->where('cdk.id', $cdkId)
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
                ->whereIn('cdk.id', $cdkIds)
                ->orderByDesc('cdk.id')
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
        $vip = Db::table(BusinessTable::vip())
            ->select('id', 'name')
            ->where('id', $vipId)
            ->first();

        if (!$vip) {
            throw new \InvalidArgumentException('所选 VIP 套餐不存在');
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
            throw new \InvalidArgumentException('CDK 类型只能为 1 或 2');
        }

        if (!in_array($type, [1, 2], true)) {
            throw new \InvalidArgumentException('CDK 类型只能为 1 或 2');
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
            throw new \InvalidArgumentException('CDK 生成数量必须是整数');
        }

        if ($count < 1 || $count > 200) {
            throw new \InvalidArgumentException('CDK 生成数量必须在 1 到 200 之间');
        }

        return $count;
    }

    private function normalizeAmount(mixed $value): float
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('余额面值必须是数字');
        }

        $amount = round((float)$value, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('余额面值必须大于 0');
        }

        if ($amount > 99999999) {
            throw new \InvalidArgumentException('余额面值超出允许范围');
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
            throw new \InvalidArgumentException('VIP 套餐编号不能为空');
        }

        if ($vipId <= 0) {
            throw new \InvalidArgumentException('VIP 套餐编号不能为空');
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
            throw new \InvalidArgumentException('CDK 前缀必须包含字母或数字');
        }

        if (strlen($prefix) > 20) {
            throw new \InvalidArgumentException('CDK 前缀长度不能超过 20 个字符');
        }

        return $prefix;
    }

    private function generateUniqueCode(string $prefix): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $suffix = strtoupper(substr(bin2hex(random_bytes(10)), 0, 15));
            $code = $prefix === '' ? $suffix : ($prefix . '_' . $suffix);
            $exists = Db::table(BusinessTable::cdk())
                ->where('code', $code)
                ->exists();

            if (!$exists) {
                return $code;
            }
        }

        throw new \RuntimeException('生成唯一 CDK 编码失败，请稍后重试');
    }

    private function buildCdkDeleteAudit(array $record): array
    {
        $formatted = AdminCdkFormatter::format($record);
        $cdkId = (int)($record['id'] ?? 0);
        $status = (int)($record['status'] ?? 0);
        $type = isset($record['type']) ? (int)$record['type'] : null;
        $warnings = [];

        if ($status === 0) {
            $warnings[] = '这张未使用的卡删除后将无法继续兑换。';
        } else {
            $warnings[] = '这张已使用的卡删除后会从历史记录中永久移除。';
        }

        if (!empty($formatted['code_masked'])) {
            $warnings[] = '即使允许删除，列表和详情中的卡密仍会保持掩码显示。';
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
                    'type_label' => '已缺失',
                    'status' => null,
                    'status_label' => '已缺失',
                    'is_used' => false,
                    'blocking_reasons' => ['这条 CDK 记录已不在当前数据表中。'],
                    'warnings' => ['请先刷新选择结果，再重新执行批量删除。'],
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
                '%d 条所选 CDK 记录已缺失，删除前请先重新选择。',
                $summary['missing_count']
            );
        }
        if ($summary['unused_count'] > 0) {
            $warnings[] = sprintf(
                '%d 条未使用的 CDK 记录删除后将永久失去兑换能力。',
                $summary['unused_count']
            );
        }
        if ($summary['used_count'] > 0) {
            $warnings[] = sprintf(
                '%d 条已使用的 CDK 记录将从历史台账中移除。',
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
                ->where('cdk.status', 1)
                ->orderByDesc('cdk.id')
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
                    sprintf('将永久删除 %d 条已使用的 CDK 记录。', $summary['used_count']),
                    '本次清理只会影响状态为 1 的已使用卡，不会处理未使用卡。',
                ]
                : ['当前没有可清理的已使用 CDK 记录。'],
        ];
    }

    private function deleteCdkRow(int $cdkId): void
    {
        Db::table(BusinessTable::cdk())
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
            throw new \InvalidArgumentException('CDK 编号列表不能为空');
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
            throw new \InvalidArgumentException('CDK 编号列表不能为空');
        }

        if (count($cdkIds) > $maxCount) {
            throw new \InvalidArgumentException('单次批量操作选择的 CDK 数量过多');
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
        return '删除CDK ' . $cdkId;
    }

    /**
     * @param array<int, int> $cdkIds
     */
    private function batchCdkDeleteConfirmationPhrase(array $cdkIds): string
    {
        return sprintf(
            '批量删除CDK %d-%s',
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
            '清理已使用CDK %d-%s',
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
