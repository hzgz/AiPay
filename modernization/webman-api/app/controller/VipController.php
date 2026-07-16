<?php

namespace app\controller;

use app\controller\concerns\AdminControllerFormatSupport;
use app\support\AdminRouteAuthorization;
use app\support\AdminVipFormatter;
use app\support\ApiResponse;
use app\support\RequestPayload;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class VipController
{
    use AdminControllerFormatSupport;

    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $query = $this->vipQuery();
        $this->applyFilters($query, $request);

        $total = (int)(clone $query)->count('id');
        $rows = $query
            ->orderBy('sort')
            ->orderByDesc('id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        $vipIds = array_values(array_unique(array_map(
            static fn ($row): int => (int)((array)$row)['id'],
            $rows
        )));
        $statsByVipId = $this->loadMerchantStats($vipIds);

        $records = array_map(function ($row) use ($statsByVipId): array {
            $record = (array)$row;
            $vipId = (int)($record['id'] ?? 0);

            return AdminVipFormatter::format($record, $statsByVipId[$vipId] ?? []);
        }, $rows);

        return ApiResponse::success([
            'records' => $records,
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => $this->summary($records),
        ]);
    }

    public function show(Request $request): Response
    {
        $id = $this->vipIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少套餐编号', 422, null, 422);
        }

        $detail = $this->findVipDetail($id);
        if ($detail === null) {
            return ApiResponse::error('套餐不存在', 404, null, 404);
        }

        return ApiResponse::success($detail);
    }

    public function template(Request $request): Response
    {
        return ApiResponse::success([
            'editable' => $this->editablePayload(
                [
                    'name' => '',
                    'money' => '0.00',
                    'viptime' => 0,
                    'feilv' => '',
                    'sort' => 0,
                    'status' => 1,
                    'is_profiteer' => 0,
                    'is_addChannelNum' => 0,
                    'addChannelNum' => 0,
                    'is_quota' => 0,
                    'today_quota' => '',
                    'moon_quota' => '',
                    'is_passage' => 0,
                    'passage' => '',
                ],
                $this->loadChannelCatalog()['groups']
            ),
        ]);
    }

    public function create(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'add');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $attributes = $this->normalizeVipPayload(RequestPayload::all($request));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $attributes['create_time'] = date('Y-m-d H:i:s');

        $id = (int)Db::table('ypay_vip')->insertGetId($attributes);

        return ApiResponse::success(
            $this->findVipDetail($id),
            '套餐已创建'
        );
    }

    public function update(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->vipIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少套餐编号', 422, null, 422);
        }

        $record = $this->vipRecord($id);
        if ($record === null) {
            return ApiResponse::error('套餐不存在', 404, null, 404);
        }

        try {
            $updates = $this->normalizeVipPayload(RequestPayload::all($request), $record);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table('ypay_vip')
            ->where('id', $id)
            ->update($updates);

        return ApiResponse::success(
            $this->findVipDetail($id),
            '套餐已更新'
        );
    }

    public function status(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'status');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->vipIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少套餐编号', 422, null, 422);
        }

        if ($this->vipRecord($id) === null) {
            return ApiResponse::error('套餐不存在', 404, null, 404);
        }

        $payload = RequestPayload::all($request);

        try {
            $status = $this->normalizeStatus($payload['status'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table('ypay_vip')
            ->where('id', $id)
            ->update(['status' => $status]);

        return ApiResponse::success(
            $this->findVipDetail($id),
            '套餐状态已更新'
        );
    }

    public function sort(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'sort');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->vipIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少套餐编号', 422, null, 422);
        }

        $record = $this->vipRecord($id);
        if ($record === null) {
            return ApiResponse::error('套餐不存在', 404, null, 404);
        }

        $payload = RequestPayload::all($request);

        try {
            $sort = $this->normalizeNonNegativeInteger($payload['sort'] ?? $record['sort'] ?? 0, 'vip sort');
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table('ypay_vip')
            ->where('id', $id)
            ->update(['sort' => $sort]);

        return ApiResponse::success(
            $this->findVipDetail($id),
            '套餐排序已更新'
        );
    }

    public function reorder(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'sort');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $vipIds = $this->normalizeOrderedVipIds($payload['visible_vip_ids'] ?? $payload['vip_ids'] ?? [], 200);
            $fromIndex = $this->normalizeSequenceIndex(
                $payload['from_index'] ?? $payload['sort_old'] ?? null,
                'vip sort from index'
            );
            $toIndex = $this->normalizeSequenceIndex(
                $payload['to_index'] ?? $payload['sort_new'] ?? null,
                'vip sort to index'
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        if ($fromIndex >= count($vipIds) || $toIndex >= count($vipIds)) {
            return ApiResponse::error('排序位置超出范围', 422, null, 422);
        }

        $sortBaselineReset = $this->ensureVipSortBaseline();
        $rows = $this->loadVipRowsBySequence($vipIds);
        if (count($rows) !== count($vipIds)) {
            return ApiResponse::error('存在已失效的套餐记录，请刷新后重试', 404, null, 404);
        }

        $sortValues = array_values(array_map(
            static fn (array $row): int => (int)($row['sort'] ?? 0),
            $rows
        ));

        if ($fromIndex !== $toIndex) {
            $movedRow = $rows[$fromIndex] ?? null;
            if ($movedRow === null) {
                return ApiResponse::error('排序位置超出范围', 422, null, 422);
            }

            array_splice($rows, $fromIndex, 1);
            array_splice($rows, $toIndex, 0, [$movedRow]);
        }

        Db::transaction(function () use ($rows, $sortValues): void {
            foreach ($rows as $index => $row) {
                Db::table('ypay_vip')
                    ->where('id', (int)($row['id'] ?? 0))
                    ->update(['sort' => (int)($sortValues[$index] ?? ($index + 1))]);
            }
        });

        return ApiResponse::success([
            'moved_vip_id' => (int)($rows[$toIndex]['id'] ?? 0),
            'from_index' => $fromIndex,
            'to_index' => $toIndex,
            'updated_count' => count($rows),
            'sort_baseline_reset' => $sortBaselineReset,
            'visible_vip_ids' => array_values(array_map(
                static fn (array $row): int => (int)($row['id'] ?? 0),
                $rows
            )),
        ] , '套餐已移入回收站');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->vipIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少套餐编号', 422, null, 422);
        }

        $detail = $this->findVipDetail($id);
        if ($detail === null) {
            return ApiResponse::error('套餐不存在', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => $detail['item'],
            'audit' => $this->vipDeleteAudit($id),
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->vipIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少套餐编号', 422, null, 422);
        }

        $item = $this->findVipDetail($id);
        if ($item === null) {
            return ApiResponse::error('套餐不存在', 404, null, 404);
        }

        $audit = $this->vipDeleteAudit($id);
        if (!empty($audit['blocking_reasons'])) {
            return ApiResponse::error(
                '当前套餐仍有关联商户，清理后才能删除',
                422,
                ['audit' => $audit],
                422
            );
        }

        $payload = RequestPayload::all($request);
        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('confirmation phrase mismatch', 422, ['audit' => $audit], 422);
        }

        Db::transaction(function () use ($id): void {
            $this->deleteVipRow($id);
        });

        $this->recordAdminVipDelete($request, $audit);

        return ApiResponse::success([
            'deleted_vip_id' => $id,
            'deleted_vip_name' => (string)(($item['item'] ?? [])['name'] ?? ''),
            'audit' => $audit,
        ], '套餐已移入回收站');
    }

    public function batchDeleteAudit(Request $request): Response{
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $vipIds = $this->normalizeVipIds($payload['vip_ids'] ?? $payload['ids'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success([
            'audit' => $this->batchVipDeleteAudit($vipIds),
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
            $vipIds = $this->normalizeVipIds($payload['vip_ids'] ?? $payload['ids'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $audit = $this->batchVipDeleteAudit($vipIds);
        if (empty($audit['can_delete_all'])) {
            return ApiResponse::error(
                'selected VIP packages cannot be batch deleted until linked merchants are cleared',
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
            foreach ((array)($audit['deletable_vip_ids'] ?? []) as $vipId) {
                $this->deleteVipRow((int)$vipId);
            }
        });

        $this->recordAdminVipBatchDelete($request, $audit);

        return ApiResponse::success([
            'deleted_vip_ids' => array_values(array_map('intval', (array)($audit['deletable_vip_ids'] ?? []))),
            'deleted_count' => (int)(($audit['summary'] ?? [])['deletable_count'] ?? 0),
            'audit' => $audit,
        ], '套餐已批量移入回收站');
    }

    public function restore(Request $request): Response{
        $authorizationError = $this->authorizeWrite($request, 'recycle');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->vipIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少套餐编号', 422, null, 422);
        }

        $record = $this->vipRecord($id, true);
        if ($record === null) {
            return ApiResponse::error('套餐不存在', 404, null, 404);
        }

        if (empty($record['delete_time'])) {
            return ApiResponse::error('当前套餐已处于正常状态', 422, null, 422);
        }

        Db::transaction(function () use ($id): void {
            $this->restoreVipRow($id);
        });

        $detail = $this->findVipDetail($id);
        if ($detail === null) {
            return ApiResponse::error('套餐恢复失败', 500, null, 500);
        }

        $this->recordAdminVipRestore($request, $record);

        return ApiResponse::success([
            'restored_vip_id' => $id,
            'restored_vip_name' => trim((string)($record['name'] ?? '')),
            'item' => $detail['item'],
            'editable' => $detail['editable'],
            'merchants' => $detail['merchants'],
        ] , '套餐已移入回收站');
    }

    public function batchRestore(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'recycle');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $vipIds = $this->normalizeVipIds($payload['vip_ids'] ?? $payload['ids'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $rows = $this->loadVipRowsByIds($vipIds, true);
        $rowsById = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $rowsById[(int)($record['id'] ?? 0)] = $record;
        }

        $restorableRows = [];
        $alreadyActiveVipIds = [];
        $missingVipIds = [];

        foreach ($vipIds as $vipId) {
            $record = $rowsById[$vipId] ?? null;
            if ($record === null) {
                $missingVipIds[] = $vipId;
                continue;
            }

            if (empty($record['delete_time'])) {
                $alreadyActiveVipIds[] = $vipId;
                continue;
            }

            $restorableRows[] = $record;
        }

        if ($restorableRows === []) {
            return ApiResponse::error('没有可恢复的回收站套餐', 422, [
                'requested_vip_ids' => $vipIds,
                'already_active_vip_ids' => $alreadyActiveVipIds,
                'missing_vip_ids' => $missingVipIds,
            ], 422);
        }

        $restoredVipIds = array_values(array_map(
            static fn (array $row): int => (int)($row['id'] ?? 0),
            $restorableRows
        ));

        Db::transaction(function () use ($restoredVipIds): void {
            foreach ($restoredVipIds as $vipId) {
                $this->restoreVipRow($vipId);
            }
        });

        $this->recordAdminVipBatchRestore($request, $restorableRows, $vipIds, $alreadyActiveVipIds, $missingVipIds);

        return ApiResponse::success([
            'requested_vip_ids' => $vipIds,
            'restored_vip_ids' => $restoredVipIds,
            'restored_count' => count($restoredVipIds),
            'already_active_vip_ids' => $alreadyActiveVipIds,
            'missing_vip_ids' => $missingVipIds,
        ] , '套餐已移入回收站');
    }

    private function vipQuery(): Builder
    {
        return Db::table('ypay_vip')
            ->select(
                'id',
                'icon',
                'avatar_frame',
                'name',
                'feilv',
                'money',
                'viptime',
                'status',
                'sort',
                'is_profiteer',
                'is_addChannelNum',
                'addChannelNum',
                'is_quota',
                'today_quota',
                'moon_quota',
                'is_passage',
                'passage',
                'create_time',
                'delete_time'
            );
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword) {
                $builder
                    ->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('feilv', 'like', '%' . $keyword . '%')
                    ->orWhere('passage', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('id', (int)$keyword);
                }
            });
        }

        $status = trim((string)$request->get('status', ''));
        if ($status === '-1' || strtolower($status) === 'deleted') {
            $query->whereNotNull('delete_time');
        } else {
            $query->whereNull('delete_time');
            if ($status !== '') {
                $query->where('status', (int)$status);
            }
        }

        $passageEnabled = $request->get('passage_enabled');
        if ($passageEnabled !== null && $passageEnabled !== '') {
            $query->where('is_passage', (int)$passageEnabled);
        }
    }

    private function vipIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function vipRecord(int $id, bool $includeDeleted = false): ?array
    {
        $query = $this->vipQuery()
            ->where('id', $id);

        if (!$includeDeleted) {
            $query->whereNull('delete_time');
        }

        $row = $query->first();

        return $row ? (array)$row : null;
    }

    private function findVipDetail(int $id): ?array
    {
        $record = $this->vipRecord($id, true);
        if ($record === null) {
            return null;
        }

        $statsByVipId = $this->loadMerchantStats([$id]);
        $channelCatalog = $this->loadChannelCatalog(array_values(array_filter(array_map(
            'trim',
            explode(',', trim((string)($record['passage'] ?? '')))
        ))));

        return [
            'item' => AdminVipFormatter::format($record, $statsByVipId[$id] ?? []),
            'editable' => $this->editablePayload($record, $channelCatalog['groups']),
            'merchants' => $this->loadVipMerchants($id),
        ];
    }

    private function vipDeleteAudit(int $id): array
    {
        $vip = $this->vipRecord($id);
        if ($vip === null) {
            throw new \RuntimeException('套餐不存在');
        }

        $stats = $this->loadMerchantStats([$id]);
        $stat = (array)($stats[$id] ?? []);
        $merchantCount = (int)($stat['merchant_count'] ?? 0);
        $activeMerchantCount = (int)($stat['active_merchant_count'] ?? 0);
        $expiredMerchantCount = (int)($stat['expired_merchant_count'] ?? 0);
        $linkedMerchants = $this->loadVipMerchants($id);

        $blockingReasons = [];
        if ($merchantCount > 0) {
            $blockingReasons[] = sprintf(
                '当前套餐仍关联 %d 个商户，请先调整这些商户的套餐归属后再删除。',
                $merchantCount
            );
        }

        return [
            'vip_id' => $id,
            'vip_name' => trim((string)($vip['name'] ?? '')),
            'confirmation_phrase' => $this->vipDeleteConfirmationPhrase($id),
            'can_delete' => $blockingReasons === [],
            'blocking_reasons' => $blockingReasons,
            'linked_merchants' => $linkedMerchants,
            'summary' => [
                'delete_row_count' => 1,
                'non_empty_target_count' => 1,
                'blocking_reference_count' => $merchantCount,
                'linked_merchant_count' => $merchantCount,
                'active_linked_merchant_count' => $activeMerchantCount,
                'expired_linked_merchant_count' => $expiredMerchantCount,
            ],
            'warnings' => [
                '删除套餐会先移入回收站，不会立即物理清除。',
                '如仍有关联商户，请先清空套餐归属或解除关联商户。',
            ],
        ];
    }

    private function batchVipDeleteAudit(array $vipIds): array
    {
        $items = [];
        $deletableVipIds = [];
        $blockedVipIds = [];
        $missingVipIds = [];
        $deleteRowCount = 0;
        $nonEmptyTargetCount = 0;
        $blockingReferenceCount = 0;
        $linkedMerchantCount = 0;
        $activeLinkedMerchantCount = 0;

        foreach ($vipIds as $vipId) {
            $identity = $this->vipIdentity($vipId);
            if ($identity === null) {
                $missingVipIds[] = $vipId;
                $items[] = [
                    'vip_id' => $vipId,
                    'vip_name' => '',
                    'exists' => false,
                    'can_delete' => false,
                    'blocking_reasons' => ['该套餐记录不存在，请刷新列表后重试。'],
                    'linked_merchants' => [],
                    'summary' => [
                        'delete_row_count' => 0,
                        'non_empty_target_count' => 0,
                        'blocking_reference_count' => 0,
                        'linked_merchant_count' => 0,
                        'active_linked_merchant_count' => 0,
                        'expired_linked_merchant_count' => 0,
                    ],
                    'warnings' => ['请先将不存在的套餐移出本次选择，再重新执行批量删除。'],
                ];
                continue;
            }

            $audit = $this->vipDeleteAudit($vipId);
            $items[] = [
                'vip_id' => $vipId,
                'vip_name' => (string)($audit['vip_name'] ?? ''),
                'exists' => true,
                'can_delete' => !empty($audit['can_delete']),
                'blocking_reasons' => array_values(array_map('strval', (array)($audit['blocking_reasons'] ?? []))),
                'linked_merchants' => array_values((array)($audit['linked_merchants'] ?? [])),
                'summary' => (array)($audit['summary'] ?? []),
                'warnings' => array_values(array_map('strval', (array)($audit['warnings'] ?? []))),
            ];

            $summary = (array)($audit['summary'] ?? []);
            $deleteRowCount += (int)($summary['delete_row_count'] ?? 0);
            $nonEmptyTargetCount += (int)($summary['non_empty_target_count'] ?? 0);
            $blockingReferenceCount += (int)($summary['blocking_reference_count'] ?? 0);
            $linkedMerchantCount += (int)($summary['linked_merchant_count'] ?? 0);
            $activeLinkedMerchantCount += (int)($summary['active_linked_merchant_count'] ?? 0);

            if (!empty($audit['can_delete'])) {
                $deletableVipIds[] = $vipId;
                continue;
            }

            $blockedVipIds[] = $vipId;
        }

        $warnings = [];
        if ($missingVipIds !== []) {
            $warnings[] = '所选套餐中存在已失效记录，请刷新后重新选择。';
        }
        if ($blockedVipIds !== []) {
            $warnings[] = '所选套餐中仍有关联商户，需先解除关联后才能继续批量删除。';
        }
        if ($deletableVipIds !== []) {
            $warnings[] = '确认后，符合条件的套餐会统一移入回收站。';
        }

        return [
            'requested_vip_ids' => $vipIds,
            'deletable_vip_ids' => $deletableVipIds,
            'blocked_vip_ids' => $blockedVipIds,
            'missing_vip_ids' => $missingVipIds,
            'confirmation_phrase' => $this->batchVipDeleteConfirmationPhrase($vipIds),
            'can_delete_all' => $vipIds !== [] && $blockedVipIds === [] && $missingVipIds === [],
            'items' => $items,
            'summary' => [
                'requested_count' => count($vipIds),
                'existing_count' => count($vipIds) - count($missingVipIds),
                'deletable_count' => count($deletableVipIds),
                'blocked_count' => count($blockedVipIds),
                'missing_count' => count($missingVipIds),
                'delete_row_count' => $deleteRowCount,
                'non_empty_target_count' => $nonEmptyTargetCount,
                'blocking_reference_count' => $blockingReferenceCount,
                'linked_merchant_count' => $linkedMerchantCount,
                'active_linked_merchant_count' => $activeLinkedMerchantCount,
            ],
            'warnings' => $warnings,
        ];
    }

    private function editablePayload(array $record, array $passageOptionGroups = []): array
    {
        return [
            'name' => trim((string)($record['name'] ?? '')),
            'money' => trim((string)($record['money'] ?? '')),
            'vip_days' => (int)($record['viptime'] ?? 0),
            'fee_rate' => trim((string)($record['feilv'] ?? '')),
            'sort' => (int)($record['sort'] ?? 0),
            'status' => (int)($record['status'] ?? 0),
            'profit_enabled' => (int)($record['is_profiteer'] ?? 0),
            'add_channel_enabled' => (int)($record['is_addChannelNum'] ?? 0),
            'add_channel_num' => (int)($record['addChannelNum'] ?? 0),
            'quota_enabled' => (int)($record['is_quota'] ?? 0),
            'today_quota' => trim((string)($record['today_quota'] ?? '')),
            'month_quota' => trim((string)($record['moon_quota'] ?? '')),
            'passage_enabled' => (int)($record['is_passage'] ?? 0),
            'passage_codes' => array_values(array_filter(array_map(
                'trim',
                explode(',', trim((string)($record['passage'] ?? '')))
            ))),
            'passage_option_groups' => $passageOptionGroups,
        ];
    }

    private function loadChannelCatalog(array $selectedCodes = []): array
    {
        $payments = Db::table('ypay_payment')
            ->select('name', 'type', 'sort', 'id')
            ->whereNull('delete_time')
            ->orderByRaw('CAST(COALESCE(sort, 0) AS UNSIGNED) asc')
            ->orderBy('id')
            ->get()
            ->toArray();

        $channels = Db::table('admin_channel')
            ->select('name', 'type', 'code', 'sort', 'id', 'status')
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->toArray();

        $channelsByType = [];
        $codeMap = [];

        foreach ($channels as $channelRow) {
            $channel = (array)$channelRow;
            $type = trim((string)($channel['type'] ?? ''));
            $code = trim((string)($channel['code'] ?? ''));
            $label = trim((string)($channel['name'] ?? ''));

            if ($type === '' || $code === '') {
                continue;
            }

            $option = [
                'label' => $label === '' ? $code : $label,
                'value' => $code,
                'code' => $code,
                'type' => $type,
                'status' => (int)($channel['status'] ?? 0),
            ];

            $channelsByType[$type][] = $option;
            $codeMap[$code] = $option;
        }

        $groups = [];
        $coveredTypes = [];

        foreach ($payments as $paymentRow) {
            $payment = (array)$paymentRow;
            $type = trim((string)($payment['type'] ?? ''));
            if ($type === '') {
                continue;
            }

            $options = $channelsByType[$type] ?? [];
            if ($options === []) {
                continue;
            }

            $groups[] = [
                'label' => trim((string)($payment['name'] ?? '')) ?: $type,
                'value' => $type,
                'disabled' => true,
                'children' => array_values($options),
            ];
            $coveredTypes[$type] = true;
        }

        foreach ($channelsByType as $type => $options) {
            if (isset($coveredTypes[$type])) {
                continue;
            }

            $groups[] = [
                'label' => $type,
                'value' => $type,
                'disabled' => true,
                'children' => array_values($options),
            ];
        }

        $legacyCodes = [];
        foreach ($selectedCodes as $selectedCode) {
            $code = trim((string)$selectedCode);
            if ($code === '' || isset($codeMap[$code])) {
                continue;
            }

            $legacyCodes[] = [
                'label' => $code . '（旧通道）',
                'value' => $code,
                'code' => $code,
                'type' => 'legacy',
                'status' => 0,
            ];
            $codeMap[$code] = end($legacyCodes);
        }

        if ($legacyCodes !== []) {
            $groups[] = [
                'label' => '旧通道 / 已移除',
                'value' => 'legacy_removed',
                'disabled' => true,
                'children' => $legacyCodes,
            ];
        }

        return [
            'groups' => $groups,
            'code_map' => $codeMap,
        ];
    }

    private function normalizeVipPayload(array $payload, array $record = []): array
    {
        $currentPassageCodes = array_values(array_filter(array_map(
            'trim',
            explode(',', trim((string)($record['passage'] ?? '')))
        )));
        $channelCatalog = $this->loadChannelCatalog($currentPassageCodes);

        $addChannelEnabled = $this->normalizeToggle(
            $payload['add_channel_enabled'] ?? $payload['is_addChannelNum'] ?? ($record['is_addChannelNum'] ?? 0),
            'vip add-channel switch'
        );
        $quotaEnabled = $this->normalizeToggle(
            $payload['quota_enabled'] ?? $payload['is_quota'] ?? ($record['is_quota'] ?? 0),
            'vip quota switch'
        );
        $todayQuota = $this->normalizeOptionalDecimal(
            $payload['today_quota'] ?? ($record['today_quota'] ?? ''),
            255,
            'vip daily quota'
        );
        $monthQuota = $this->normalizeOptionalDecimal(
            $payload['month_quota'] ?? $payload['moon_quota'] ?? ($record['moon_quota'] ?? ''),
            255,
            'vip monthly quota'
        );

        if ($quotaEnabled === 1 && $todayQuota === '') {
            throw new \InvalidArgumentException('启用额度限制时，必须填写日额度');
        }

        $passageEnabled = $this->normalizeToggle(
            $payload['passage_enabled'] ?? $payload['is_passage'] ?? ($record['is_passage'] ?? 0),
            'vip passage switch'
        );
        $passageCodes = $this->normalizePassageCodes(
            $payload['passage_codes']
                ?? $payload['passage']
                ?? $payload['select']
                ?? ($record['passage'] ?? ''),
            $channelCatalog['code_map'],
            $passageEnabled === 1
        );

        return [
            'name' => $this->normalizeRequiredString(
                $payload['name'] ?? ($record['name'] ?? ''),
                50,
                'vip package name'
            ),
            'money' => $this->normalizeMoney($payload['money'] ?? ($record['money'] ?? 0)),
            'viptime' => $this->normalizeNonNegativeInteger(
                $payload['vip_days'] ?? $payload['viptime'] ?? ($record['viptime'] ?? 0),
                'vip package duration'
            ),
            'feilv' => $this->normalizeFeeRate($payload['fee_rate'] ?? $payload['feilv'] ?? ($record['feilv'] ?? '')),
            'status' => $this->normalizeStatus($payload['status'] ?? ($record['status'] ?? 1)),
            'sort' => $this->normalizeNonNegativeInteger($payload['sort'] ?? ($record['sort'] ?? 0), 'vip sort'),
            'is_profiteer' => $this->normalizeToggle(
                $payload['profit_enabled'] ?? $payload['is_profiteer'] ?? ($record['is_profiteer'] ?? 0),
                'vip profit switch'
            ),
            'is_addChannelNum' => $addChannelEnabled,
            'addChannelNum' => $addChannelEnabled === 1
                ? $this->normalizeNonNegativeInteger(
                    $payload['add_channel_num'] ?? $payload['addChannelNum'] ?? ($record['addChannelNum'] ?? 0),
                    'vip add-channel count'
                )
                : 0,
            'is_quota' => $quotaEnabled,
            'today_quota' => $todayQuota === '' ? null : $todayQuota,
            'moon_quota' => $monthQuota === '' ? null : $monthQuota,
            'is_passage' => $passageEnabled,
            'passage' => $passageEnabled === 1 ? implode(',', $passageCodes) : null,
        ];
    }

    private function loadMerchantStats(array $vipIds): array
    {
        if ($vipIds === []) {
            return [];
        }

        $now = date('Y-m-d H:i:s');
        $rows = Db::table('ypay_user')
            ->select('vip_id')
            ->selectRaw('COUNT(*) as merchant_count')
            ->selectRaw(
                'SUM(CASE WHEN vip_time IS NULL OR vip_time >= ? THEN 1 ELSE 0 END) as active_merchant_count',
                [$now]
            )
            ->selectRaw(
                'SUM(CASE WHEN vip_time IS NOT NULL AND vip_time < ? THEN 1 ELSE 0 END) as expired_merchant_count',
                [$now]
            )
            ->whereIn('vip_id', $vipIds)
            ->groupBy('vip_id')
            ->get()
            ->toArray();

        $stats = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $stats[(int)($record['vip_id'] ?? 0)] = $record;
        }

        return $stats;
    }

    private function loadVipMerchants(int $vipId): array
    {
        $rows = Db::table('ypay_user')
            ->select('id', 'username', 'name', 'vip_time', 'create_time')
            ->where('vip_id', $vipId)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->toArray();

        return array_map(
            static fn ($row): array => AdminVipFormatter::formatMerchant((array)$row),
            $rows
        );
    }

    private function vipIdentity(int $id, bool $includeDeleted = false): ?array
    {
        $query = Db::table('ypay_vip')
            ->select('id', 'name')
            ->where('id', $id);

        if (!$includeDeleted) {
            $query->whereNull('delete_time');
        }

        $row = $query->first();

        return $row ? (array)$row : null;
    }

    private function summary(array $records): array
    {
        $enabled = 0;
        $disabled = 0;
        $merchantCount = 0;
        $activeMerchantCount = 0;

        foreach ($records as $record) {
            if ((int)$record['status'] === 1) {
                $enabled++;
            } else {
                $disabled++;
            }

            $merchantCount += (int)$record['merchant_count'];
            $activeMerchantCount += (int)$record['active_merchant_count'];
        }

        return [
            'total' => count($records),
            'enabled_count' => $enabled,
            'disabled_count' => $disabled,
            'merchant_count' => $merchantCount,
            'active_merchant_count' => $activeMerchantCount,
        ];
    }

    private function ensureVipSortBaseline(): bool
    {
        $rows = Db::table('ypay_vip')
            ->select('id', 'sort')
            ->whereNull('delete_time')
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get()
            ->toArray();

        $seenSorts = [];
        $needsReset = false;

        foreach ($rows as $row) {
            $record = (array)$row;
            $sort = (int)($record['sort'] ?? 0);
            if ($sort <= 0 || isset($seenSorts[$sort])) {
                $needsReset = true;
                break;
            }

            $seenSorts[$sort] = true;
        }

        if (!$needsReset) {
            return false;
        }

        Db::transaction(function () use ($rows): void {
            $sort = 1;
            foreach ($rows as $row) {
                $record = (array)$row;
                Db::table('ypay_vip')
                    ->where('id', (int)($record['id'] ?? 0))
                    ->update(['sort' => $sort]);
                $sort++;
            }
        });

        return true;
    }

    private function loadVipRowsBySequence(array $vipIds): array
    {
        if ($vipIds === []) {
            return [];
        }

        $rows = Db::table('ypay_vip')
            ->select('id', 'name', 'sort', 'delete_time')
            ->whereNull('delete_time')
            ->whereIn('id', $vipIds)
            ->get()
            ->toArray();

        $rowsById = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $rowsById[(int)($record['id'] ?? 0)] = $record;
        }

        $ordered = [];
        foreach ($vipIds as $vipId) {
            if (!isset($rowsById[$vipId])) {
                continue;
            }

            $ordered[] = $rowsById[$vipId];
        }

        return $ordered;
    }

    private function loadVipRowsByIds(array $vipIds, bool $includeDeleted = false): array
    {
        if ($vipIds === []) {
            return [];
        }

        $query = Db::table('ypay_vip')
            ->select('id', 'name', 'status', 'sort', 'delete_time')
            ->whereIn('id', $vipIds);

        if (!$includeDeleted) {
            $query->whereNull('delete_time');
        }

        return array_map(
            static fn ($row): array => (array)$row,
            $query->get()->toArray()
        );
    }

    private function normalizeStatus(mixed $value): int
    {
        try {
            return $this->normalizeToggle($value, 'vip status');
        } catch (\InvalidArgumentException) {
            throw new \InvalidArgumentException('套餐状态只能是启用或停用');
        }
    }

    private function normalizeToggle(mixed $value, string $field): int
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
            '1', 'true', 'yes', 'on', 'enable', 'enabled' => 1,
            '0', 'false', 'no', 'off', 'disable', 'disabled' => 0,
            default => throw new \InvalidArgumentException($this->fieldLabel($field) . '只能是开启或关闭'),
        };
    }

    private function normalizeRequiredString(mixed $value, int $maxLength, string $field): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '格式不正确');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '不能为空');
        }

        if (mb_strlen($normalized) > $maxLength) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '过长');
        }

        return $normalized;
    }

    private function normalizeNonNegativeInteger(mixed $value, string $field): int
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '格式不正确');
        }

        $normalized = trim((string)$value);
        if ($normalized === '' || !preg_match('/^\d+$/', $normalized)) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '必须为非负整数');
        }

        return (int)$normalized;
    }

    private function normalizeSequenceIndex(mixed $value, string $field): int
    {
        return $this->normalizeNonNegativeInteger($value, $field);
    }

    private function normalizeMoney(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('套餐价格格式不正确');
        }

        $normalized = trim((string)$value);
        if ($normalized === '' || !preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw new \InvalidArgumentException('套餐价格必须为最多两位小数的非负金额');
        }

        if ((float)$normalized > 99999999.99) {
            throw new \InvalidArgumentException('套餐价格超出允许范围');
        }

        return number_format((float)$normalized, 2, '.', '');
    }

    private function normalizeFeeRate(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('套餐费率格式不正确');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            throw new \InvalidArgumentException('套餐费率不能为空');
        }

        if (strlen($normalized) > 50 || !preg_match('/^\d+(?:\.\d+)?$/', $normalized)) {
            throw new \InvalidArgumentException('套餐费率必须为非负数字');
        }

        return $normalized;
    }

    private function normalizeOptionalDecimal(mixed $value, int $maxLength, string $field): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '格式不正确');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            return '';
        }

        if (strlen($normalized) > $maxLength || !preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '必须为最多两位小数的非负金额');
        }

        return $normalized;
    }

    private function normalizePassageCodes(mixed $value, array $allowedCodes, bool $required): array
    {
        $codes = [];

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_array($item) || is_object($item)) {
                    throw new \InvalidArgumentException('可用通道标识格式不正确');
                }

                $code = trim((string)$item);
                if ($code !== '') {
                    $codes[] = $code;
                }
            }
        } else {
            $raw = trim((string)$value);
            if ($raw !== '') {
                $codes = array_filter(array_map('trim', explode(',', $raw)), static fn (string $code): bool => $code !== '');
            }
        }

        $codes = array_values(array_unique($codes));

        if ($required && $codes === []) {
            throw new \InvalidArgumentException('启用专属通道时，至少需要选择一个通道');
        }

        foreach ($codes as $code) {
            if (!isset($allowedCodes[$code])) {
                throw new \InvalidArgumentException('存在无效的通道标识：' . $code);
            }
        }

        return $codes;
    }

    private function vipDeleteConfirmationPhrase(int $id): string
    {
        return 'DELETE VIP ' . $id;
    }

    private function batchVipDeleteConfirmationPhrase(array $vipIds): string
    {
        return sprintf(
            'DELETE VIP BATCH %d-%s',
            count($vipIds),
            strtoupper(substr(md5(implode(',', $vipIds)), 0, 6))
        );
    }

    private function deleteVipRow(int $id): void
    {
        Db::table('ypay_vip')
            ->where('id', $id)
            ->update(['delete_time' => date('Y-m-d H:i:s')]);
    }

    private function restoreVipRow(int $id): void
    {
        Db::table('ypay_vip')
            ->where('id', $id)
            ->update(['delete_time' => null]);
    }

    private function normalizeVipIds(mixed $value, int $maxCount = 100): array
    {
        $items = [];

        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value) && trim((string)$value) !== '') {
            $items = preg_split('/\s*,\s*/', trim((string)$value)) ?: [];
        } elseif (is_numeric($value)) {
            $items = [$value];
        }

        $ids = [];
        foreach ($items as $item) {
            if (is_bool($item) || is_array($item) || is_object($item)) {
                continue;
            }

            $normalized = trim((string)$item);
            if ($normalized === '' || !ctype_digit($normalized)) {
                continue;
            }

            $id = (int)$normalized;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        $vipIds = array_values($ids);
        sort($vipIds);

        if ($vipIds === []) {
            throw new \InvalidArgumentException('请至少选择一个套餐');
        }

        if (count($vipIds) > $maxCount) {
            throw new \InvalidArgumentException('本次选择的套餐数量过多');
        }

        return $vipIds;
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'vip status' => '套餐状态',
            'vip package name' => '套餐名称',
            'vip package duration' => '套餐时长',
            'vip sort', 'vip sort from index', 'vip sort to index' => '排序值',
            'vip profit switch' => '返佣开关',
            'vip add-channel switch' => '额外通道开关',
            'vip add-channel count' => '额外通道数量',
            'vip quota switch' => '额度限制开关',
            'vip daily quota' => '日额度',
            'vip monthly quota' => '月额度',
            'vip passage switch' => '专属通道开关',
            default => $field,
        };
    }

    private function normalizeOrderedVipIds(mixed $value, int $maxCount = 100): array
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

            $id = (int)$normalized;
            if ($id <= 0 || isset($orderedIds[$id])) {
                continue;
            }

            $orderedIds[$id] = $id;
        }

        $vipIds = array_values($orderedIds);

        if ($vipIds === []) {
            throw new \InvalidArgumentException('请至少选择一个套餐');
        }

        if (count($vipIds) > $maxCount) {
            throw new \InvalidArgumentException('本次选择的套餐数量过多');
        }

        return $vipIds;
    }

    private function recordAdminVipDelete(Request $request, array $audit): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $vipId = (int)($audit['vip_id'] ?? 0);
        $vipName = $this->truncateLogText((string)($audit['vip_name'] ?? ''), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/vips/' . $vipId . '/delete',
            'desc' => sprintf(
                'vip delete vip_id=%d name="%s" linked=%d active_linked=%d delete_rows=%d',
                $vipId,
                $vipName,
                (int)($summary['linked_merchant_count'] ?? 0),
                (int)($summary['active_linked_merchant_count'] ?? 0),
                (int)($summary['delete_row_count'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminVipBatchDelete(Request $request, array $audit): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $vipIds = implode(',', array_map('intval', (array)($audit['deletable_vip_ids'] ?? [])));
        $vipLabels = implode(',', array_map(
            static function (array $item): string {
                $name = trim((string)($item['vip_name'] ?? ''));
                $vipId = (int)($item['vip_id'] ?? 0);

                return $name !== '' ? $name : ('#' . $vipId);
            },
            array_values(array_filter(
                (array)($audit['items'] ?? []),
                static fn (array $item): bool => !empty($item['can_delete'])
            ))
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/vips/batch-delete',
            'desc' => sprintf(
                'vip batch delete requested=%d deleted=%d blocked=%d missing=%d delete_rows=%d vips="%s" labels="%s"',
                (int)($summary['requested_count'] ?? 0),
                (int)($summary['deletable_count'] ?? 0),
                (int)($summary['blocked_count'] ?? 0),
                (int)($summary['missing_count'] ?? 0),
                (int)($summary['delete_row_count'] ?? 0),
                $this->truncateLogText($vipIds, 255),
                $this->truncateLogText($vipLabels, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminVipRestore(Request $request, array $record): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $vipId = (int)($record['id'] ?? 0);
        $vipName = $this->truncateLogText((string)($record['name'] ?? ''), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/vips/' . $vipId . '/restore',
            'desc' => sprintf(
                'vip restore vip_id=%d name="%s"',
                $vipId,
                $vipName
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'SystemVips', $authMark);
    }

    private function recordAdminVipBatchRestore(
        Request $request,
        array $restorableRows,
        array $requestedVipIds,
        array $alreadyActiveVipIds,
        array $missingVipIds
    ): void {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $restoredVipIds = implode(',', array_map(
            static fn (array $row): int => (int)($row['id'] ?? 0),
            $restorableRows
        ));
        $restoredLabels = implode(',', array_map(
            static function (array $row): string {
                $name = trim((string)($row['name'] ?? ''));
                $vipId = (int)($row['id'] ?? 0);

                return $name !== '' ? $name : ('#' . $vipId);
            },
            $restorableRows
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/vips/batch-restore',
            'desc' => sprintf(
                'vip batch restore requested=%d restored=%d active=%d missing=%d vips="%s" labels="%s"',
                count($requestedVipIds),
                count($restorableRows),
                count($alreadyActiveVipIds),
                count($missingVipIds),
                $this->truncateLogText($restoredVipIds, 255),
                $this->truncateLogText($restoredLabels, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

}
