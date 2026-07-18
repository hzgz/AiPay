<?php

namespace app\controller;

use app\support\BusinessTable;
use app\support\AdminPaymentFormatter;
use app\support\AdminRouteAuthorization;
use app\support\ApiResponse;
use app\support\CorePaymentMethodCatalog;
use app\support\RequestPayload;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class PaymentController
{
    public function index(Request $request): Response
    {
        CorePaymentMethodCatalog::seedWhenTableEmpty();

        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));
        $recycleView = $this->isRecycleView($request);

        $query = Db::table(BusinessTable::payment())
            ->select('id', 'name', 'type', 'sort', 'status', 'create_time', 'update_time', 'delete_time');

        if ($recycleView) {
            $query->whereNotNull('delete_time');
        } else {
            $query->whereNull('delete_time');
        }

        $this->applyFilters($query, $request, $recycleView);

        $total = (int)(clone $query)->count('id');
        $rows = array_map(
            static fn($row): array => (array)$row,
            $query
                ->orderByDesc('id')
                ->offset(($current - 1) * $size)
                ->limit($size)
                ->get()
                ->toArray()
        );

        return ApiResponse::success([
            'records' => $this->formatMethodRecords($rows),
            'current' => $current,
            'size' => $size,
            'total' => $total,
        ]);
    }

    public function show(Request $request): Response
    {
        $id = $this->paymentIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('支付方式编号不能为空', 422, null, 422);
        }

        $detail = $this->findMethodDetail($id);
        if ($detail === null) {
            return ApiResponse::error('支付方式不存在', 404, null, 404);
        }

        return ApiResponse::success($detail);
    }

    public function create(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'add');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $name = $this->normalizeMethodName($payload['name'] ?? '');
            $type = $this->normalizeMethodType($payload['type'] ?? '');
            $sort = $this->normalizeMethodSort($payload['sort'] ?? '');
            $status = $this->normalizeStatus($payload['status'] ?? 1);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $existing = $this->findMethodByType($type);
        if ($existing !== null) {
            $existingId = (int)($existing['id'] ?? 0);
            if (!empty($existing['delete_time'])) {
                return ApiResponse::error(
                    '该支付标识已存在于回收站，请先恢复后再继续',
                    422,
                    ['existing_payment_id' => $existingId],
                    422
                );
            }

            return ApiResponse::error(
                '支付标识已存在，请勿重复创建',
                422,
                ['existing_payment_id' => $existingId],
                422
            );
        }

        $now = date('Y-m-d H:i:s');
        $paymentId = (int)Db::table(BusinessTable::payment())->insertGetId([
            'name' => $name,
            'type' => $type,
            'sort' => $sort,
            'status' => $status,
            'create_time' => $now,
            'update_time' => $now,
            'delete_time' => null,
        ]);

        $detail = $this->findMethodDetail($paymentId);
        if ($detail === null) {
            return ApiResponse::error('支付方式创建成功，但详情刷新失败', 500, null, 500);
        }

        return ApiResponse::success([
            'created_payment_id' => $paymentId,
            'created_payment_label' => $this->methodLabel((array)($detail['item'] ?? [])),
            'item' => $detail['item'] ?? null,
            'editable' => $detail['editable'] ?? null,
        ], '支付方式创建成功');
    }

    public function update(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->paymentIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('支付方式编号不能为空', 422, null, 422);
        }

        $record = $this->paymentRecord($id);
        if ($record === null) {
            return ApiResponse::error('支付方式不存在', 404, null, 404);
        }

        if (!empty($record['delete_time'])) {
            return ApiResponse::error('回收站内的支付方式请先恢复后再编辑', 422, null, 422);
        }

        $payload = RequestPayload::all($request);

        try {
            $name = $this->normalizeMethodName($payload['name'] ?? ($record['name'] ?? ''));
            $sort = $this->normalizeMethodSort($payload['sort'] ?? ($record['sort'] ?? ''));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table(BusinessTable::payment())
            ->where('id', $id)
            ->update([
                'name' => $name,
                'sort' => $sort,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        $detail = $this->findMethodDetail($id);
        if ($detail === null) {
            return ApiResponse::error('支付方式更新成功，但详情刷新失败', 500, null, 500);
        }

        return ApiResponse::success($detail, '支付方式已更新');
    }

    public function status(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'status');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->paymentIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('支付方式编号不能为空', 422, null, 422);
        }

        $record = $this->paymentRecord($id);
        if ($record === null) {
            return ApiResponse::error('支付方式不存在', 404, null, 404);
        }

        if (!empty($record['delete_time'])) {
            return ApiResponse::error('回收站内的支付方式请先恢复后再切换状态', 422, null, 422);
        }

        $payload = RequestPayload::all($request);

        try {
            $status = $this->normalizeStatus($payload['status'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table(BusinessTable::payment())
            ->where('id', $id)
            ->update([
                'status' => $status,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        return ApiResponse::success([
            'item' => $this->findMethod($id),
        ], '支付方式状态已更新');
    }

    public function restore(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'recycle');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->paymentIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('支付方式编号不能为空', 422, null, 422);
        }

        $record = $this->paymentRecord($id);
        if ($record === null) {
            return ApiResponse::error('支付方式不存在', 404, null, 404);
        }

        if (empty($record['delete_time'])) {
            return ApiResponse::error('该支付方式已处于正常状态', 422, null, 422);
        }

        $activeDuplicate = $this->findActiveMethodByType(trim((string)($record['type'] ?? '')), $id);
        if ($activeDuplicate !== null) {
            return ApiResponse::error(
                '已有正常支付方式占用了该支付标识，请先处理重复项后再恢复',
                422,
                ['existing_payment_id' => (int)($activeDuplicate['id'] ?? 0)],
                422
            );
        }

        Db::table(BusinessTable::payment())
            ->where('id', $id)
            ->update([
                'delete_time' => null,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        $detail = $this->findMethodDetail($id);
        if ($detail === null) {
            return ApiResponse::error('支付方式恢复成功，但详情刷新失败', 500, null, 500);
        }

        return ApiResponse::success([
            'restored_payment_id' => $id,
            'restored_payment_label' => $this->methodLabel($record),
            'item' => $detail['item'] ?? null,
            'editable' => $detail['editable'] ?? null,
        ], '支付方式已恢复');
    }

    public function batchRestore(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'recycle');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $paymentIds = $this->normalizeMethodIds(
                $request->post('payment_ids', $request->post('ids', []))
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        if ($paymentIds === []) {
            return ApiResponse::error('至少需要选择一条支付方式记录', 422, null, 422);
        }

        $rows = $this->loadPaymentRowsByIds($paymentIds);
        $rowMap = [];
        foreach ($rows as $row) {
            $rowMap[(int)($row['id'] ?? 0)] = $row;
        }

        $restorableRows = [];
        $alreadyActivePaymentIds = [];
        $missingPaymentIds = [];
        $blockedItems = [];

        foreach ($paymentIds as $paymentId) {
            $row = $rowMap[$paymentId] ?? null;
            if ($row === null) {
                $missingPaymentIds[] = $paymentId;
                continue;
            }

            if (empty($row['delete_time'])) {
                $alreadyActivePaymentIds[] = $paymentId;
                continue;
            }

            $activeDuplicate = $this->findActiveMethodByType(trim((string)($row['type'] ?? '')), $paymentId);
            if ($activeDuplicate !== null) {
                $blockedItems[] = [
                    'payment_id' => $paymentId,
                    'payment_label' => $this->methodLabel($row),
                    'reason' => '已有正常支付方式占用了该支付标识',
                ];
                continue;
            }

            $restorableRows[] = $row;
        }

        if ($restorableRows === []) {
            return ApiResponse::error('没有可恢复的回收站支付方式', 422, [
                'requested_payment_ids' => $paymentIds,
                'restored_payment_ids' => [],
                'restored_count' => 0,
                'already_active_payment_ids' => $alreadyActivePaymentIds,
                'missing_payment_ids' => $missingPaymentIds,
                'blocked_items' => $blockedItems,
            ], 422);
        }

        $restoredPaymentIds = array_values(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $restorableRows
        ));

        Db::table(BusinessTable::payment())
            ->whereIn('id', $restoredPaymentIds)
            ->update([
                'delete_time' => null,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        return ApiResponse::success([
            'requested_payment_ids' => $paymentIds,
            'restored_payment_ids' => $restoredPaymentIds,
            'restored_count' => count($restorableRows),
            'already_active_payment_ids' => $alreadyActivePaymentIds,
            'missing_payment_ids' => $missingPaymentIds,
            'blocked_items' => $blockedItems,
        ], '支付方式已批量恢复');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->paymentIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('支付方式编号不能为空', 422, null, 422);
        }

        $record = $this->paymentRecord($id);
        if ($record === null) {
            return ApiResponse::error('支付方式不存在', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => $this->findMethod($id),
            'audit' => $this->buildDeleteAudit($record),
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->paymentIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('支付方式编号不能为空', 422, null, 422);
        }

        $record = $this->paymentRecord($id);
        if ($record === null) {
            return ApiResponse::error('支付方式不存在', 404, null, 404);
        }

        $audit = $this->buildDeleteAudit($record);
        if (empty($audit['can_delete'])) {
            return ApiResponse::error(
                '当前支付方式暂时不能移入回收站，请先处理阻塞项',
                422,
                ['audit' => $audit],
                422
            );
        }

        $payload = RequestPayload::all($request);
        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error(
                '确认口令不正确',
                422,
                ['audit' => $audit],
                422
            );
        }

        Db::table(BusinessTable::payment())
            ->where('id', $id)
            ->update([
                'delete_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        return ApiResponse::success([
            'deleted_payment_id' => $id,
            'deleted_payment_label' => (string)($audit['payment_label'] ?? ''),
            'audit' => $audit,
        ], '支付方式已移入回收站');
    }

    public function batchDeleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $paymentIds = $this->normalizeMethodIds($payload['payment_ids'] ?? ($payload['ids'] ?? []));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success([
            'audit' => $this->batchDeleteAuditPayload($paymentIds),
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
            $paymentIds = $this->normalizeMethodIds($payload['payment_ids'] ?? ($payload['ids'] ?? []));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $audit = $this->batchDeleteAuditPayload($paymentIds);
        if (empty($audit['can_delete_all'])) {
            return ApiResponse::error(
                '当前所选支付方式暂时不能批量移入回收站，请先清理选择项',
                422,
                ['audit' => $audit],
                422
            );
        }

        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error(
                '确认口令不正确',
                422,
                ['audit' => $audit],
                422
            );
        }

        $deletablePaymentIds = array_values(array_map('intval', (array)($audit['deletable_payment_ids'] ?? [])));
        if ($deletablePaymentIds !== []) {
            Db::table(BusinessTable::payment())
                ->whereIn('id', $deletablePaymentIds)
                ->update([
                    'delete_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
        }

        return ApiResponse::success([
            'deleted_payment_ids' => $deletablePaymentIds,
            'deleted_count' => count($deletablePaymentIds),
            'audit' => $audit,
        ], '支付方式已批量移入回收站');
    }

    private function applyFilters(Builder $query, Request $request, bool $recycleView): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('type', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('id', (int)$keyword);
                }
            });
        }

        $type = trim((string)$request->get('type', ''));
        if ($type !== '') {
            $query->where('type', $type);
        }

        if ($recycleView) {
            return;
        }

        $status = trim((string)$request->get('status', ''));
        if (in_array($status, ['0', '1'], true)) {
            $query->where('status', (int)$status);
        }
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'PaymentMethods', $authMark);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function formatMethodRecords(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $types = array_values(array_unique(array_filter(array_map(
            static fn(array $row): string => trim((string)($row['type'] ?? '')),
            $rows
        ))));
        $orderStats = $this->loadOrderStats($types);
        $accountStats = $this->loadAccountStats($types);

        return array_map(static function (array $record) use ($orderStats, $accountStats): array {
            $type = trim((string)($record['type'] ?? ''));

            return AdminPaymentFormatter::formatMethod(
                $record,
                $orderStats[$type] ?? [],
                $accountStats[$type] ?? []
            );
        }, $rows);
    }

    private function loadOrderStats(array $types): array
    {
        if ($types === []) {
            return [];
        }

        $rows = Db::table(BusinessTable::order())
            ->select('type')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as paid_order_count')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN truemoney ELSE 0 END) as paid_amount')
            ->whereIn('type', $types)
            ->groupBy('type')
            ->get()
            ->toArray();

        $stats = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $stats[trim((string)($record['type'] ?? ''))] = $record;
        }

        return $stats;
    }

    private function loadAccountStats(array $types): array
    {
        if ($types === []) {
            return [];
        }

        $rows = Db::table(BusinessTable::account())
            ->select('type')
            ->selectRaw('COUNT(*) as account_count')
            ->selectRaw('SUM(CASE WHEN is_status = 1 THEN 1 ELSE 0 END) as enabled_account_count')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as online_account_count')
            ->whereIn('type', $types)
            ->groupBy('type')
            ->get()
            ->toArray();

        $stats = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $stats[trim((string)($record['type'] ?? ''))] = $record;
        }

        return $stats;
    }

    private function paymentIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function paymentRecord(int $id): ?array
    {
        $row = Db::table(BusinessTable::payment())
            ->select('id', 'name', 'type', 'sort', 'status', 'create_time', 'update_time', 'delete_time')
            ->where('id', $id)
            ->first();

        return $row ? (array)$row : null;
    }

    /**
     * @param array<int, int> $paymentIds
     * @return array<int, array<string, mixed>>
     */
    private function loadPaymentRowsByIds(array $paymentIds): array
    {
        if ($paymentIds === []) {
            return [];
        }

        return array_map(
            static fn($row): array => (array)$row,
            Db::table(BusinessTable::payment())
                ->select('id', 'name', 'type', 'sort', 'status', 'create_time', 'update_time', 'delete_time')
                ->whereIn('id', $paymentIds)
                ->get()
                ->toArray()
        );
    }

    private function findMethod(int $id): ?array
    {
        $record = $this->paymentRecord($id);
        if ($record === null) {
            return null;
        }

        $records = $this->formatMethodRecords([$record]);

        return $records[0] ?? null;
    }

    private function findMethodDetail(int $id): ?array
    {
        $record = $this->paymentRecord($id);
        if ($record === null) {
            return null;
        }

        return [
            'item' => $this->findMethod($id),
            'editable' => [
                'name' => trim((string)($record['name'] ?? '')),
                'type' => trim((string)($record['type'] ?? '')),
                'sort' => trim((string)($record['sort'] ?? '')),
                'status' => (int)($record['status'] ?? 0),
            ],
        ];
    }

    private function buildDeleteAudit(array $record): array
    {
        $item = $this->formatMethodRecords([$record])[0] ?? [];
        $paymentId = (int)($record['id'] ?? 0);
        $deleted = !empty($record['delete_time']);

        return [
            'payment_id' => $paymentId,
            'payment_label' => $this->methodLabel($record),
            'type' => trim((string)($record['type'] ?? '')),
            'type_text' => (string)($item['type_text'] ?? ($item['type_label'] ?? '')),
            'type_label' => (string)($item['type_label'] ?? ''),
            'status' => (int)($record['status'] ?? 0),
            'status_text' => (string)($item['status_text'] ?? ($item['status_label'] ?? '')),
            'status_label' => (string)($item['status_label'] ?? ''),
            'can_delete' => !$deleted,
            'confirmation_phrase' => $this->deleteConfirmationPhrase($paymentId),
            'blocking_reasons' => $deleted ? ['该支付方式已在回收站中。'] : [],
            'warnings' => [
                '删除支付方式仅会把当前方式记录移入回收站。',
                '关联收款账号与历史订单会保留，后续仍可恢复该支付方式。',
            ],
            'summary' => [
                'delete_row_count' => $deleted ? 0 : 1,
                'order_count' => (int)($item['order_count'] ?? 0),
                'paid_order_count' => (int)($item['paid_order_count'] ?? 0),
                'paid_amount' => (float)($item['paid_amount'] ?? 0),
                'account_count' => (int)($item['account_count'] ?? 0),
                'enabled_account_count' => (int)($item['enabled_account_count'] ?? 0),
                'online_account_count' => (int)($item['online_account_count'] ?? 0),
            ],
        ];
    }

    private function batchDeleteAuditPayload(array $paymentIds): array
    {
        $rows = $this->loadPaymentRowsByIds($paymentIds);
        $rowMap = [];
        foreach ($rows as $row) {
            $rowMap[(int)($row['id'] ?? 0)] = $row;
        }

        $items = [];
        $deletablePaymentIds = [];
        $blockedPaymentIds = [];
        $missingPaymentIds = [];
        $deleteRowCount = 0;
        $orderCount = 0;
        $paidOrderCount = 0;
        $accountCount = 0;

        foreach ($paymentIds as $paymentId) {
            $row = $rowMap[$paymentId] ?? null;
            if ($row === null) {
                $missingPaymentIds[] = $paymentId;
                $items[] = [
                    'payment_id' => $paymentId,
                    'payment_label' => '',
                    'type' => '',
                    'exists' => false,
                    'can_delete' => false,
                    'blocking_reasons' => ['未在支付方式表中找到该记录。'],
                    'summary' => [
                        'delete_row_count' => 0,
                        'order_count' => 0,
                        'paid_order_count' => 0,
                        'account_count' => 0,
                    ],
                    'warnings' => ['请先移除不存在的支付方式后再重试批量删除。'],
                ];
                continue;
            }

            $audit = $this->buildDeleteAudit($row);
            $summary = (array)($audit['summary'] ?? []);
            $items[] = [
                'payment_id' => $paymentId,
                'payment_label' => (string)($audit['payment_label'] ?? ''),
                'type' => (string)($audit['type'] ?? ''),
                'exists' => true,
                'can_delete' => !empty($audit['can_delete']),
                'blocking_reasons' => array_values(array_map('strval', (array)($audit['blocking_reasons'] ?? []))),
                'summary' => [
                    'delete_row_count' => (int)($summary['delete_row_count'] ?? 0),
                    'order_count' => (int)($summary['order_count'] ?? 0),
                    'paid_order_count' => (int)($summary['paid_order_count'] ?? 0),
                    'account_count' => (int)($summary['account_count'] ?? 0),
                ],
                'warnings' => array_values(array_map('strval', (array)($audit['warnings'] ?? []))),
            ];

            $deleteRowCount += (int)($summary['delete_row_count'] ?? 0);
            $orderCount += (int)($summary['order_count'] ?? 0);
            $paidOrderCount += (int)($summary['paid_order_count'] ?? 0);
            $accountCount += (int)($summary['account_count'] ?? 0);

            if (!empty($audit['can_delete'])) {
                $deletablePaymentIds[] = $paymentId;
                continue;
            }

            $blockedPaymentIds[] = $paymentId;
        }

        $warnings = [];
        if ($missingPaymentIds !== []) {
            $warnings[] = '所选支付方式中存在已不存在的记录，请先移除后再继续。';
        }
        if ($blockedPaymentIds !== []) {
            $warnings[] = '所选支付方式中包含已在回收站内的记录，请先清理选择项后再继续。';
        }
        if ($deletablePaymentIds !== []) {
            $warnings[] = '确认后，符合条件的支付方式会统一移入回收站。';
        }

        return [
            'requested_payment_ids' => $paymentIds,
            'deletable_payment_ids' => $deletablePaymentIds,
            'blocked_payment_ids' => $blockedPaymentIds,
            'missing_payment_ids' => $missingPaymentIds,
            'confirmation_phrase' => $this->batchDeleteConfirmationPhrase($paymentIds),
            'can_delete_all' => $paymentIds !== [] && $blockedPaymentIds === [] && $missingPaymentIds === [],
            'items' => $items,
            'summary' => [
                'requested_count' => count($paymentIds),
                'existing_count' => count($paymentIds) - count($missingPaymentIds),
                'deletable_count' => count($deletablePaymentIds),
                'blocked_count' => count($blockedPaymentIds),
                'missing_count' => count($missingPaymentIds),
                'delete_row_count' => $deleteRowCount,
                'order_count' => $orderCount,
                'paid_order_count' => $paidOrderCount,
                'account_count' => $accountCount,
            ],
            'warnings' => $warnings,
        ];
    }

    private function isRecycleView(Request $request): bool
    {
        $status = strtolower(trim((string)$request->get('status', '')));
        if (in_array($status, ['-1', 'deleted', 'recycle', 'recycled'], true)) {
            return true;
        }

        $recycle = strtolower(trim((string)$request->get('recycle', '')));

        return in_array($recycle, ['1', 'true', 'yes', 'on'], true);
    }

    private function findMethodByType(string $type, ?int $exceptId = null): ?array
    {
        $query = Db::table(BusinessTable::payment())
            ->select('id', 'name', 'type', 'sort', 'status', 'create_time', 'update_time', 'delete_time')
            ->where('type', $type)
            ->orderByDesc('id');

        if ($exceptId !== null && $exceptId > 0) {
            $query->where('id', '<>', $exceptId);
        }

        $row = $query->first();

        return $row ? (array)$row : null;
    }

    private function findActiveMethodByType(string $type, ?int $exceptId = null): ?array
    {
        $query = Db::table(BusinessTable::payment())
            ->select('id', 'name', 'type', 'sort', 'status', 'create_time', 'update_time', 'delete_time')
            ->where('type', $type)
            ->whereNull('delete_time')
            ->orderByDesc('id');

        if ($exceptId !== null && $exceptId > 0) {
            $query->where('id', '<>', $exceptId);
        }

        $row = $query->first();

        return $row ? (array)$row : null;
    }

    private function deleteConfirmationPhrase(int $paymentId): string
    {
        return '删除支付方式 ' . $paymentId;
    }

    /**
     * @param array<int, int> $paymentIds
     */
    private function batchDeleteConfirmationPhrase(array $paymentIds): string
    {
        return sprintf(
            '批量删除支付方式 %d-%s',
            count($paymentIds),
            strtoupper(substr(md5(implode(',', $paymentIds)), 0, 6))
        );
    }

    private function methodLabel(array $record): string
    {
        $name = trim((string)($record['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $type = trim((string)($record['type'] ?? ''));
        if ($type !== '') {
            return $type;
        }

        return '支付方式 #' . (int)($record['id'] ?? 0);
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
            '1', 'true', 'yes', 'on', 'enable', 'enabled' => 1,
            '0', 'false', 'no', 'off', 'disable', 'disabled' => 0,
            default => throw new \InvalidArgumentException('支付方式状态只能是 0 或 1'),
        };
    }

    private function normalizeMethodName(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('支付方式名称必须是标量值');
        }

        $name = trim((string)$value);
        if ($name === '') {
            throw new \InvalidArgumentException('请输入支付方式名称');
        }

        if (mb_strlen($name) > 255) {
            throw new \InvalidArgumentException('支付方式名称长度不能超过 255 个字符');
        }

        return $name;
    }

    private function normalizeMethodType(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('支付标识必须是标量值');
        }

        $type = strtolower(trim((string)$value));
        if ($type === '') {
            throw new \InvalidArgumentException('请输入支付标识');
        }

        if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/', $type)) {
            throw new \InvalidArgumentException('支付标识格式不正确，仅支持小写字母、数字和下划线，且需以字母开头');
        }

        return $type;
    }

    private function normalizeMethodSort(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('排序权重必须是标量值');
        }

        $sort = trim((string)$value);
        if ($sort === '') {
            throw new \InvalidArgumentException('请输入排序权重');
        }

        if (!preg_match('/^\d+$/', $sort)) {
            throw new \InvalidArgumentException('排序权重必须是非负整数');
        }

        if (strlen($sort) > 20) {
            throw new \InvalidArgumentException('排序权重长度不能超过 20 位');
        }

        return $sort;
    }

    /**
     * @return array<int, int>
     */
    private function normalizeMethodIds(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = preg_split('/\s*,\s*/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            }
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException('支付方式编号必须是数组');
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_numeric($item)) {
                throw new \InvalidArgumentException('支付方式编号必须是正整数');
            }

            $paymentId = (int)$item;
            if ($paymentId <= 0) {
                throw new \InvalidArgumentException('支付方式编号必须是正整数');
            }

            $normalized[$paymentId] = $paymentId;
        }

        return array_values($normalized);
    }
}
