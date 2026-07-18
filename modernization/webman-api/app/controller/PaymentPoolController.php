<?php

namespace app\controller;

use app\support\AdminRouteAuthorization;
use app\support\AdminPaymentPoolFormatter;
use app\support\ApiResponse;
use app\support\BusinessTable;
use app\support\RequestPayload;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class PaymentPoolController
{
    public function create(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'add');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $userId = $this->normalizeUserId($payload['user_id'] ?? null);
            $name = $this->normalizePoolName($payload['name'] ?? '');
            $type = $this->normalizePoolType($payload['type'] ?? '');
            $roundType = $this->normalizeRoundType($payload['round_type'] ?? 1);
            $status = $this->normalizeStatus($payload['status'] ?? 1);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $merchant = $this->findMerchant($userId);
        if ($merchant === null) {
            return ApiResponse::error('merchant not found', 404, null, 404);
        }

        $now = date('Y-m-d H:i:s');
        $poolId = (int)Db::table(BusinessTable::pollPool())->insertGetId([
            'user_id' => $userId,
            'name' => $name,
            'type' => $type,
            'round_type' => $roundType,
            'status' => $status,
            'current_index' => 0,
            'current_weight' => 1,
            'last_account_id' => 0,
            'create_time' => $now,
            'update_time' => $now,
        ]);

        $detail = $this->findPoolDetail($poolId);
        if ($detail === null) {
            return ApiResponse::error('payment pool created but detail reload failed', 500, null, 500);
        }

        $this->recordAdminPoolCreate($request, (array)($detail['item'] ?? []));

        $item = (array)($detail['item'] ?? []);

        return ApiResponse::success([
            'created_pool_id' => $poolId,
            'created_pool_label' => (string)($item['name_label'] ?? ('轮询池 #' . $poolId)),
            'item' => $detail['item'] ?? null,
            'editable' => $detail['editable'] ?? null,
        ], 'payment pool created');
    }

    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $query = $this->poolQuery();
        $this->applyFilters($query, $request);

        $summary = $this->summary(clone $query);
        $total = (int)(clone $query)->count('pool.id');
        $rows = $query
            ->orderByDesc('pool.id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        $poolIds = array_values(array_unique(array_map(
            static fn ($row): int => (int)((array)$row)['id'],
            $rows
        )));
        $stats = $this->loadPoolStats($poolIds);
        $lastAccounts = $this->loadLastAccounts($rows);

        $records = array_map(function ($row) use ($stats, $lastAccounts): array {
            $record = (array)$row;
            $poolId = (int)($record['id'] ?? 0);
            $lastAccountId = (int)($record['last_account_id'] ?? 0);

            return AdminPaymentPoolFormatter::format(
                $record,
                $stats[$poolId] ?? [],
                $lastAccounts[$lastAccountId] ?? null
            );
        }, $rows);

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
        $id = $this->poolIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('payment pool id is required', 422, null, 422);
        }

        $detail = $this->findPoolDetail($id);
        if ($detail === null) {
            return ApiResponse::error('payment pool not found', 404, null, 404);
        }

        return ApiResponse::success($detail);
    }

    public function update(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->poolIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('payment pool id is required', 422, null, 422);
        }

        $record = $this->poolRecord($id);
        if ($record === null) {
            return ApiResponse::error('payment pool not found', 404, null, 404);
        }

        $payload = RequestPayload::all($request);
        $before = $this->findPool($id);

        try {
            $name = $this->normalizePoolName($payload['name'] ?? ($record['name'] ?? ''));
            $roundType = $this->normalizeRoundType($payload['round_type'] ?? ($record['round_type'] ?? 1));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table(BusinessTable::pollPool())
            ->where('id', $id)
            ->update([
                'name' => $name,
                'round_type' => $roundType,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        $detail = $this->findPoolDetail($id);
        if ($detail === null) {
            return ApiResponse::error('payment pool updated but detail reload failed', 500, null, 500);
        }

        $this->recordAdminPoolUpdate($request, $before ?? [], (array)($detail['item'] ?? []));

        return ApiResponse::success($detail, 'payment pool updated');
    }

    public function status(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'status');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->poolIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('payment pool id is required', 422, null, 422);
        }

        if ($this->poolRecord($id) === null) {
            return ApiResponse::error('payment pool not found', 404, null, 404);
        }

        $payload = RequestPayload::all($request);
        $before = $this->findPool($id);

        try {
            $status = $this->normalizeStatus($payload['status'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table(BusinessTable::pollPool())
            ->where('id', $id)
            ->update([
                'status' => $status,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        $item = $this->findPool($id);
        $this->recordAdminPoolStatus($request, $before ?? [], $status, $item ?? []);

        return ApiResponse::success([
            'item' => $item,
        ], 'payment pool status updated');
    }

    public function channelEditor(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->poolIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('payment pool id is required', 422, null, 422);
        }

        $editor = $this->buildChannelEditor($id);
        if ($editor === null) {
            return ApiResponse::error('payment pool not found', 404, null, 404);
        }

        return ApiResponse::success($editor);
    }

    public function saveChannels(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->poolIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('payment pool id is required', 422, null, 422);
        }

        $record = $this->poolRecord($id);
        if ($record === null) {
            return ApiResponse::error('payment pool not found', 404, null, 404);
        }

        $beforeEditor = $this->buildChannelEditor($id);
        $payload = RequestPayload::all($request);

        try {
            $channels = $this->normalizeChannelPayload(
                $payload['channels'] ?? []
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $validAccountIds = [];
        if ($channels !== []) {
            $validAccountIds = Db::table(BusinessTable::account())
                ->where('user_id', (int)($record['user_id'] ?? 0))
                ->where('type', trim((string)($record['type'] ?? '')))
                ->whereIn('id', array_column($channels, 'account_id'))
                ->pluck('id')
                ->map(static fn ($value): int => (int)$value)
                ->toArray();

            $validLookup = array_fill_keys($validAccountIds, true);
            foreach ($channels as $channel) {
                if (!isset($validLookup[(int)$channel['account_id']])) {
                    return ApiResponse::error(
                        'channel selection contains an invalid merchant payment account',
                        422,
                        null,
                        422
                    );
                }
            }
        }

        $now = date('Y-m-d H:i:s');
        Db::transaction(function () use ($id, $record, $channels, $now): void {
            Db::table(BusinessTable::pollPoolItem())
                ->where('pool_id', $id)
                ->delete();

            if ($channels !== []) {
                $rows = array_map(static function (array $channel) use ($id, $record, $now): array {
                    return [
                        'user_id' => (int)($record['user_id'] ?? 0),
                        'pool_id' => $id,
                        'account_id' => (int)$channel['account_id'],
                        'weight' => (int)$channel['weight'],
                        'sort' => (int)$channel['sort'],
                        'create_time' => $now,
                        'update_time' => $now,
                    ];
                }, $channels);

                Db::table(BusinessTable::pollPoolItem())->insert($rows);
            }

            Db::table(BusinessTable::pollPool())
                ->where('id', $id)
                ->update([
                    'current_index' => 0,
                    'current_weight' => 1,
                    'last_account_id' => 0,
                    'update_time' => $now,
                ]);
        });

        $detail = $this->findPoolDetail($id);
        $editor = $this->buildChannelEditor($id);
        if ($detail === null || $editor === null) {
            return ApiResponse::error('payment pool channels saved but reload failed', 500, null, 500);
        }

        $this->recordAdminPoolChannels(
            $request,
            $beforeEditor ?? [],
            $editor,
            (array)($detail['item'] ?? [])
        );

        return ApiResponse::success([
            'item' => $detail['item'] ?? null,
            'editable' => $detail['editable'] ?? null,
            'channel_editor' => $editor['editor'] ?? null,
            'saved_channel_count' => count($channels),
            'cleared_channel_count' => max(
                0,
                (int)((($beforeEditor['editor'] ?? [])['summary'] ?? [])['selected_count'] ?? 0) - count($channels)
            ),
        ], $channels === [] ? 'payment pool channels cleared' : 'payment pool channels saved');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->poolIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('payment pool id is required', 422, null, 422);
        }

        $record = $this->poolRecord($id);
        if ($record === null) {
            return ApiResponse::error('payment pool not found', 404, null, 404);
        }

        $stats = $this->loadPoolStats([$id]);
        $lastAccountId = (int)($record['last_account_id'] ?? 0);
        $lastAccounts = $this->loadAccountMap($lastAccountId > 0 ? [$lastAccountId] : []);
        $item = AdminPaymentPoolFormatter::formatDetail(
            $record,
            $stats[$id] ?? [],
            $lastAccounts[$lastAccountId] ?? null
        );

        return ApiResponse::success([
            'item' => $item,
            'audit' => $this->buildDeleteAudit($record, $stats[$id] ?? [], $item),
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->poolIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('payment pool id is required', 422, null, 422);
        }

        $record = $this->poolRecord($id);
        if ($record === null) {
            return ApiResponse::error('payment pool not found', 404, null, 404);
        }

        $stats = $this->loadPoolStats([$id]);
        $lastAccountId = (int)($record['last_account_id'] ?? 0);
        $lastAccounts = $this->loadAccountMap($lastAccountId > 0 ? [$lastAccountId] : []);
        $item = AdminPaymentPoolFormatter::formatDetail(
            $record,
            $stats[$id] ?? [],
            $lastAccounts[$lastAccountId] ?? null
        );
        $audit = $this->buildDeleteAudit($record, $stats[$id] ?? [], $item);

        $payload = RequestPayload::all($request);
        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('confirmation phrase mismatch', 422, ['audit' => $audit], 422);
        }

        Db::transaction(function () use ($id): void {
            Db::table(BusinessTable::pollPoolItem())->where('pool_id', $id)->delete();
            Db::table(BusinessTable::pollPool())->where('id', $id)->delete();
        });

        $this->recordAdminPoolDelete($request, $audit);

        return ApiResponse::success([
            'deleted_pool_id' => $id,
            'deleted_pool_label' => (string)($audit['pool_label'] ?? ('轮询池 #' . $id)),
            'audit' => $audit,
        ], 'payment pool deleted');
    }

    private function poolQuery(): Builder
    {
        return Db::table(BusinessTable::pollPool('pool'))
            ->leftJoin(BusinessTable::user('merchant'), 'pool.user_id', '=', 'merchant.id')
            ->select(
                'pool.id',
                'pool.user_id',
                'pool.name',
                'pool.type',
                'pool.round_type',
                'pool.status',
                'pool.current_index',
                'pool.current_weight',
                'pool.last_account_id',
                'pool.create_time',
                'pool.update_time',
                'merchant.username as merchant_username',
                'merchant.name as merchant_name'
            );
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword) {
                $builder
                    ->where('pool.name', 'like', '%' . $keyword . '%')
                    ->orWhere('pool.type', 'like', '%' . $keyword . '%')
                    ->orWhere('merchant.username', 'like', '%' . $keyword . '%')
                    ->orWhere('merchant.name', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder
                        ->orWhere('pool.id', (int)$keyword)
                        ->orWhere('pool.user_id', (int)$keyword)
                        ->orWhere('pool.last_account_id', (int)$keyword);
                }
            });
        }

        $userId = trim((string)$request->get('user_id', ''));
        if ($userId !== '' && ctype_digit($userId)) {
            $query->where('pool.user_id', (int)$userId);
        }

        $type = trim((string)$request->get('type', ''));
        if ($type !== '') {
            $query->where('pool.type', $type);
        }

        $roundType = trim((string)$request->get('round_type', ''));
        if (in_array($roundType, ['1', '2'], true)) {
            $query->where('pool.round_type', (int)$roundType);
        }

        $status = trim((string)$request->get('status', ''));
        if (in_array($status, ['0', '1'], true)) {
            $query->where('pool.status', (int)$status);
        }
    }

    private function summary(Builder $query): array
    {
        $rows = (clone $query)
            ->select('pool.id', 'pool.user_id')
            ->get()
            ->toArray();

        $poolIds = array_values(array_unique(array_map(
            static fn ($row): int => (int)((array)$row)['id'],
            $rows
        )));
        $userIds = array_values(array_unique(array_filter(array_map(
            static fn ($row): int => (int)((array)$row)['user_id'],
            $rows
        ))));

        $stats = $this->loadPoolStats($poolIds);
        $configuredPoolCount = 0;
        $emptyPoolCount = 0;
        $configuredChannelCount = 0;
        $healthyPoolCount = 0;

        foreach ($poolIds as $poolId) {
            $itemCount = (int)($stats[$poolId]['item_count'] ?? 0);
            $activeItemCount = (int)($stats[$poolId]['active_item_count'] ?? 0);
            $missingItemCount = (int)($stats[$poolId]['missing_item_count'] ?? 0);

            if ($itemCount > 0) {
                $configuredPoolCount++;
                $configuredChannelCount += $itemCount;
            } else {
                $emptyPoolCount++;
            }

            if ($itemCount > 0 && $activeItemCount === $itemCount && $missingItemCount === 0) {
                $healthyPoolCount++;
            }
        }

        return [
            'total_count' => count($poolIds),
            'merchant_count' => count($userIds),
            'enabled_count' => (int)(clone $query)->where('pool.status', 1)->count('pool.id'),
            'disabled_count' => (int)(clone $query)->where('pool.status', '<>', 1)->count('pool.id'),
            'configured_pool_count' => $configuredPoolCount,
            'empty_pool_count' => $emptyPoolCount,
            'configured_channel_count' => $configuredChannelCount,
            'healthy_pool_count' => $healthyPoolCount,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function loadPoolStats(array $poolIds): array
    {
        if ($poolIds === []) {
            return [];
        }

        $rows = Db::table(BusinessTable::pollPoolItem())
            ->select('id', 'pool_id', 'account_id', 'weight', 'sort', 'create_time', 'update_time')
            ->whereIn('pool_id', $poolIds)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->toArray();

        $accountIds = array_values(array_unique(array_filter(array_map(
            static fn ($row): int => (int)((array)$row)['account_id'],
            $rows
        ))));
        $accountMap = $this->loadAccountMap($accountIds);
        $stats = [];

        foreach ($poolIds as $poolId) {
            $stats[$poolId] = [
                'item_count' => 0,
                'active_item_count' => 0,
                'disabled_item_count' => 0,
                'missing_item_count' => 0,
                'total_weight' => 0,
                'latest_item_time' => null,
                'items' => [],
            ];
        }

        foreach ($rows as $row) {
            $record = (array)$row;
            $poolId = (int)($record['pool_id'] ?? 0);
            $accountId = (int)($record['account_id'] ?? 0);
            $account = $accountMap[$accountId] ?? null;
            $item = [
                'item_id' => (int)($record['id'] ?? 0),
                'account_id' => $accountId,
                'weight' => max(1, (int)($record['weight'] ?? 1)),
                'sort' => max(0, (int)($record['sort'] ?? 0)),
                'create_time' => $this->nullableString($record['create_time'] ?? null),
                'update_time' => $this->nullableString($record['update_time'] ?? null),
                'account_exists' => $account !== null,
                'account_code' => $account['code'] ?? '',
                'account_type' => $account['type'] ?? '',
                'channel_name' => $account['channel_name'] ?? '',
                'account_memo' => $account['memo'] ?? '',
                'account_status' => (int)($account['status'] ?? 0),
                'account_enabled' => (int)($account['is_status'] ?? 0),
                'account_update_time' => $account['update_time'] ?? null,
            ];

            $stats[$poolId]['item_count']++;
            $stats[$poolId]['total_weight'] += $item['weight'];
            $stats[$poolId]['items'][] = $item;

            if (!$item['account_exists']) {
                $stats[$poolId]['missing_item_count']++;
            } elseif ($item['account_status'] === 1 && $item['account_enabled'] === 1) {
                $stats[$poolId]['active_item_count']++;
            } else {
                $stats[$poolId]['disabled_item_count']++;
            }

            $latestCandidate = $item['update_time'] ?? $item['create_time'] ?? $item['account_update_time'];
            if ($latestCandidate !== null) {
                $stats[$poolId]['latest_item_time'] = $this->maxTimestamp(
                    $stats[$poolId]['latest_item_time'],
                    $latestCandidate
                );
            }
        }

        return $stats;
    }

    private function loadLastAccounts(array $rows): array
    {
        $accountIds = array_values(array_unique(array_filter(array_map(
            static fn ($row): int => (int)((array)$row)['last_account_id'],
            $rows
        ))));

        return $this->loadAccountMap($accountIds);
    }

    private function loadAccountMap(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        $rows = Db::table(BusinessTable::account('account'))
            ->leftJoin('admin_channel', 'account.code', '=', 'admin_channel.code')
            ->select(
                'account.id',
                'account.code',
                'account.type',
                'account.memo',
                'account.status',
                'account.is_status',
                'account.update_time',
                'admin_channel.name as channel_name'
            )
            ->whereIn('account.id', $accountIds)
            ->get()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $map[(int)($record['id'] ?? 0)] = [
                'id' => (int)($record['id'] ?? 0),
                'code' => trim((string)($record['code'] ?? '')),
                'type' => trim((string)($record['type'] ?? '')),
                'memo' => trim((string)($record['memo'] ?? '')),
                'status' => (int)($record['status'] ?? 0),
                'is_status' => (int)($record['is_status'] ?? 0),
                'update_time' => $this->nullableString($record['update_time'] ?? null),
                'channel_name' => trim((string)($record['channel_name'] ?? '')),
            ];
        }

        return $map;
    }

    private function poolIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function poolRecord(int $id): ?array
    {
        $row = $this->poolQuery()
            ->where('pool.id', $id)
            ->first();

        return $row ? (array)$row : null;
    }

    private function findPool(int $id): ?array
    {
        $record = $this->poolRecord($id);
        if ($record === null) {
            return null;
        }

        $stats = $this->loadPoolStats([$id]);
        $lastAccountId = (int)($record['last_account_id'] ?? 0);
        $lastAccounts = $this->loadAccountMap($lastAccountId > 0 ? [$lastAccountId] : []);

        return AdminPaymentPoolFormatter::format(
            $record,
            $stats[$id] ?? [],
            $lastAccounts[$lastAccountId] ?? null
        );
    }

    private function findPoolDetail(int $id): ?array
    {
        $record = $this->poolRecord($id);
        if ($record === null) {
            return null;
        }

        $stats = $this->loadPoolStats([$id]);
        $lastAccountId = (int)($record['last_account_id'] ?? 0);
        $lastAccounts = $this->loadAccountMap($lastAccountId > 0 ? [$lastAccountId] : []);

        return [
            'item' => AdminPaymentPoolFormatter::formatDetail(
                $record,
                $stats[$id] ?? [],
                $lastAccounts[$lastAccountId] ?? null
            ),
            'editable' => [
                'name' => trim((string)($record['name'] ?? '')),
                'type' => trim((string)($record['type'] ?? '')),
                'round_type' => (int)($record['round_type'] ?? 1) === 2 ? 2 : 1,
                'status' => (int)($record['status'] ?? 1) === 0 ? 0 : 1,
            ],
        ];
    }

    private function buildChannelEditor(int $id): ?array
    {
        $record = $this->poolRecord($id);
        if ($record === null) {
            return null;
        }

        $detail = $this->findPoolDetail($id);
        if ($detail === null) {
            return null;
        }

        $stats = $this->loadPoolStats([$id]);
        $poolStats = $stats[$id] ?? [];
        $selectedLookup = [];
        $missingSelectedAccounts = [];

        foreach ((array)($poolStats['items'] ?? []) as $item) {
            $accountId = (int)($item['account_id'] ?? 0);
            if ($accountId <= 0) {
                continue;
            }

            $selectedLookup[$accountId] = [
                'weight' => max(1, (int)($item['weight'] ?? 1)),
                'sort' => max(0, (int)($item['sort'] ?? 0)),
            ];

            if (!empty($item['account_exists'])) {
                continue;
            }

            $missingSelectedAccounts[] = [
                'account_id' => $accountId,
                'account_label' => '#'.$accountId.' / 已删除账号',
                'channel_label' => trim((string)($item['account_code'] ?? '')) ?: '通道缺失',
                'weight' => max(1, (int)($item['weight'] ?? 1)),
                'sort_order' => max(0, (int)($item['sort'] ?? 0)) + 1,
                'status_label' => '缺失',
                'status_type' => 'danger',
                'update_time' => $this->nullableString(
                    $item['update_time'] ?? ($item['create_time'] ?? ($item['account_update_time'] ?? null))
                ),
            ];
        }

        $rows = Db::table(BusinessTable::account('account'))
            ->leftJoin('admin_channel', 'account.code', '=', 'admin_channel.code')
            ->select(
                'account.id',
                'account.code',
                'account.type',
                'account.memo',
                'account.status',
                'account.is_status',
                'account.update_time',
                'admin_channel.name as channel_name'
            )
            ->where('account.user_id', (int)($record['user_id'] ?? 0))
            ->where('account.type', trim((string)($record['type'] ?? '')))
            ->orderByDesc('account.id')
            ->get()
            ->toArray();

        $availableAccounts = [];
        $activeAvailableCount = 0;
        $disabledAvailableCount = 0;

        foreach ($rows as $row) {
            $account = (array)$row;
            $accountId = (int)($account['id'] ?? 0);
            [$statusLabel, $statusType] = $this->channelStatusMeta(
                (int)($account['status'] ?? 0),
                (int)($account['is_status'] ?? 0)
            );
            if ((int)($account['status'] ?? 0) === 1 && (int)($account['is_status'] ?? 0) === 1) {
                $activeAvailableCount++;
            } else {
                $disabledAvailableCount++;
            }

            $selectedMeta = $selectedLookup[$accountId] ?? null;
            $channelLabel = trim((string)($account['channel_name'] ?? ''));
            if ($channelLabel === '') {
                $channelLabel = trim((string)($account['code'] ?? '')) ?: '未知通道';
            }
            $memo = trim((string)($account['memo'] ?? ''));

            $availableAccounts[] = [
                'account_id' => $accountId,
                'account_label' => $memo !== ''
                    ? '#'.$accountId.' / '.$channelLabel.' / '.$memo
                    : '#'.$accountId.' / '.$channelLabel,
                'channel_label' => $channelLabel,
                'memo' => $memo,
                'status' => (int)($account['status'] ?? 0),
                'is_status' => (int)($account['is_status'] ?? 0),
                'status_label' => $statusLabel,
                'status_type' => $statusType,
                'selected' => $selectedMeta !== null,
                'weight' => (int)($selectedMeta['weight'] ?? 1),
                'sort_order' => $selectedMeta === null ? null : ((int)$selectedMeta['sort'] + 1),
                'update_time' => $this->nullableString($account['update_time'] ?? null),
            ];
        }

        usort($availableAccounts, static function (array $left, array $right): int {
            $leftSelected = !empty($left['selected']);
            $rightSelected = !empty($right['selected']);

            if ($leftSelected && $rightSelected) {
                return ((int)($left['sort_order'] ?? 0)) <=> ((int)($right['sort_order'] ?? 0));
            }

            if ($leftSelected !== $rightSelected) {
                return $leftSelected ? -1 : 1;
            }

            return ((int)($right['account_id'] ?? 0)) <=> ((int)($left['account_id'] ?? 0));
        });

        $warnings = [];
        if ($availableAccounts === []) {
            $warnings[] = '当前商户与支付类型下还没有可用的收款账号。';
        }
        if ($missingSelectedAccounts !== []) {
            $warnings[] = '保存后会自动移除已失效、且不再映射到收款账号的轮询池项。';
        }
        if ($disabledAvailableCount > 0) {
            $warnings[] = '离线或停用的账号可以保留在轮询池中，但实际路由时会自动跳过，直到恢复可用。';
        }

        return [
            'item' => $detail['item'] ?? null,
            'editable' => $detail['editable'] ?? null,
            'editor' => [
                'available_accounts' => $availableAccounts,
                'missing_selected_accounts' => $missingSelectedAccounts,
                'summary' => [
                    'available_count' => count($availableAccounts),
                    'selected_count' => max(0, (int)($poolStats['item_count'] ?? 0)),
                    'active_available_count' => $activeAvailableCount,
                    'disabled_available_count' => $disabledAvailableCount,
                    'missing_selected_count' => count($missingSelectedAccounts),
                    'total_weight' => max(0, (int)($poolStats['total_weight'] ?? 0)),
                ],
                'warnings' => $warnings,
            ],
        ];
    }

    private function buildDeleteAudit(array $record, array $stats, array $item): array
    {
        $poolId = (int)($record['id'] ?? 0);

        return [
            'pool_id' => $poolId,
            'pool_label' => (string)($item['name_label'] ?? ('轮询池 #' . $poolId)),
            'merchant_display' => (string)($item['merchant_display'] ?? ''),
            'type' => trim((string)($record['type'] ?? '')),
            'type_label' => (string)($item['type_label'] ?? ''),
            'can_delete' => true,
            'confirmation_phrase' => $this->poolDeleteConfirmationPhrase($poolId),
            'blocking_reasons' => [],
            'warnings' => [
                '删除轮询池只会清理路由游标状态和已选轮询池项。',
                '商户收款账号、通道配置和历史订单不会被删除。',
            ],
            'summary' => [
                'delete_row_count' => 1,
                'selected_channel_count' => max(0, (int)($stats['item_count'] ?? 0)),
                'active_selected_channel_count' => max(0, (int)($stats['active_item_count'] ?? 0)),
                'missing_selected_channel_count' => max(0, (int)($stats['missing_item_count'] ?? 0)),
                'total_weight' => max(0, (int)($stats['total_weight'] ?? 0)),
            ],
        ];
    }

    private function normalizePoolName(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('payment pool name must be a scalar');
        }

        $name = trim((string)$value);
        if ($name === '') {
            throw new \InvalidArgumentException('payment pool name is required');
        }

        if (mb_strlen($name) > 64) {
            throw new \InvalidArgumentException('payment pool name is too long');
        }

        return $name;
    }

    private function normalizeUserId(mixed $value): int
    {
        if (is_numeric($value)) {
            $userId = (int)$value;
            if ($userId > 0) {
                return $userId;
            }
        }

        throw new \InvalidArgumentException('merchant user_id is required');
    }

    private function normalizePoolType(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('payment pool type must be a scalar');
        }

        $type = strtolower(trim((string)$value));
        if ($type === '') {
            throw new \InvalidArgumentException('payment pool type is required');
        }

        if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/', $type)) {
            throw new \InvalidArgumentException('payment pool type format is invalid');
        }

        return $type;
    }

    private function normalizeRoundType(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 2 : 1;
        }

        if (is_numeric($value)) {
            $roundType = (int)$value;
            if (in_array($roundType, [1, 2], true)) {
                return $roundType;
            }
        }

        $normalized = strtolower(trim((string)$value));

        return match ($normalized) {
            '1', 'sequence', 'sequential', 'order', 'ordered' => 1,
            '2', 'random', 'weight', 'weighted' => 2,
            default => throw new \InvalidArgumentException('payment pool round type must be 1 or 2'),
        };
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
            default => throw new \InvalidArgumentException('payment pool status must be 0 or 1'),
        };
    }

    private function normalizeChannelPayload(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException('payment pool channels must be an array');
        }

        $normalized = [];
        $seen = [];

        foreach ($value as $index => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('payment pool channels must contain objects');
            }

            $accountId = (int)($row['account_id'] ?? 0);
            if ($accountId <= 0) {
                throw new \InvalidArgumentException('payment pool channels contain an invalid account_id');
            }

            if (isset($seen[$accountId])) {
                throw new \InvalidArgumentException('payment pool channels cannot contain duplicate account ids');
            }
            $seen[$accountId] = true;

            $weight = (int)($row['weight'] ?? 1);
            $weight = max(1, min($weight, 9999));

            $normalized[] = [
                'account_id' => $accountId,
                'weight' => $weight,
                'sort' => $index,
            ];
        }

        return $normalized;
    }

    private function findMerchant(int $userId): ?array
    {
        $row = Db::table(BusinessTable::user())
            ->select('id', 'username', 'name')
            ->where('id', $userId)
            ->first();

        return $row ? (array)$row : null;
    }

    private function channelStatusMeta(int $status, int $enabled): array
    {
        if ($status !== 1) {
            return ['offline', 'warning'];
        }

        if ($enabled !== 1) {
            return ['disabled', 'info'];
        }

        return ['active', 'success'];
    }

    private function poolDeleteConfirmationPhrase(int $poolId): string
    {
        return '删除轮询池 ' . $poolId;
    }

    private function recordAdminPoolCreate(Request $request, array $item): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/payment-pools/create',
            'desc' => sprintf(
                'payment pool create pool_id=%d user_id=%d type=%s round_type=%d status=%d name="%s"',
                (int)($item['id'] ?? 0),
                (int)($item['user_id'] ?? 0),
                trim((string)($item['type'] ?? '')),
                (int)($item['round_type'] ?? 1),
                (int)($item['status'] ?? 1),
                $this->truncateLogText((string)($item['name_label'] ?? ''), 120)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminPoolUpdate(Request $request, array $before, array $after): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $poolId = (int)($after['id'] ?? $before['id'] ?? 0);
        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/payment-pools/' . $poolId . '/update',
            'desc' => sprintf(
                'payment pool update pool_id=%d user_id=%d name_changed=%d from_round_type=%d to_round_type=%d from_name="%s" to_name="%s"',
                $poolId,
                (int)($after['user_id'] ?? $before['user_id'] ?? 0),
                trim((string)($before['name'] ?? '')) === trim((string)($after['name'] ?? '')) ? 0 : 1,
                (int)($before['round_type'] ?? 1),
                (int)($after['round_type'] ?? 1),
                $this->truncateLogText((string)($before['name_label'] ?? ''), 120),
                $this->truncateLogText((string)($after['name_label'] ?? ''), 120)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminPoolStatus(Request $request, array $before, int $status, array $after): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $poolId = (int)($after['id'] ?? $before['id'] ?? 0);
        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/payment-pools/' . $poolId . '/status',
            'desc' => sprintf(
                'payment pool status pool_id=%d user_id=%d from_status=%d to_status=%d name="%s"',
                $poolId,
                (int)($after['user_id'] ?? $before['user_id'] ?? 0),
                (int)($before['status'] ?? 0),
                $status,
                $this->truncateLogText((string)($after['name_label'] ?? $before['name_label'] ?? ''), 120)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminPoolChannels(Request $request, array $beforeEditor, array $afterEditor, array $item): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $beforeSummary = (array)(($beforeEditor['editor'] ?? [])['summary'] ?? []);
        $afterSummary = (array)(($afterEditor['editor'] ?? [])['summary'] ?? []);
        $poolId = (int)($item['id'] ?? 0);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/payment-pools/' . $poolId . '/channels',
            'desc' => sprintf(
                'payment pool channels pool_id=%d user_id=%d from_selected=%d to_selected=%d from_weight=%d to_weight=%d missing_removed=%d name="%s"',
                $poolId,
                (int)($item['user_id'] ?? 0),
                (int)($beforeSummary['selected_count'] ?? 0),
                (int)($afterSummary['selected_count'] ?? 0),
                (int)($beforeSummary['total_weight'] ?? 0),
                (int)($afterSummary['total_weight'] ?? 0),
                max(
                    0,
                    (int)($beforeSummary['missing_selected_count'] ?? 0) - (int)($afterSummary['missing_selected_count'] ?? 0)
                ),
                $this->truncateLogText((string)($item['name_label'] ?? ''), 120)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminPoolDelete(Request $request, array $audit): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/payment-pools/' . (int)($audit['pool_id'] ?? 0) . '/delete',
            'desc' => sprintf(
                'payment pool delete pool_id=%d type=%s selected_channels=%d active_selected=%d missing_selected=%d total_weight=%d label="%s"',
                (int)($audit['pool_id'] ?? 0),
                trim((string)($audit['type'] ?? '')),
                (int)($summary['selected_channel_count'] ?? 0),
                (int)($summary['active_selected_channel_count'] ?? 0),
                (int)($summary['missing_selected_channel_count'] ?? 0),
                (int)($summary['total_weight'] ?? 0),
                $this->truncateLogText((string)($audit['pool_label'] ?? ''), 120)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function adminIdFromRequest(Request $request): int
    {
        $admin = (array)($request->admin ?? []);
        return (int)($admin['id'] ?? 0);
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorizeAny($request, 'PaymentPools', [$authMark, 'index']);
    }

    private function truncateLogText(string $value, int $limit): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($clean === '') {
            return '';
        }

        return mb_strlen($clean) > $limit ? mb_substr($clean, 0, $limit - 3) . '...' : $clean;
    }

    private function maxTimestamp(?string $left, ?string $right): ?string
    {
        if ($left === null || $left === '') {
            return $right;
        }

        if ($right === null || $right === '') {
            return $left;
        }

        return strcmp($left, $right) >= 0 ? $left : $right;
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }
}
