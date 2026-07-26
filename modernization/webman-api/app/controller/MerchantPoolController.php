<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\controller;

use app\support\AdminFixtureTextNormalizer;
use app\support\AdminOrderFormatter;
use app\support\AdminPaymentPoolFormatter;
use app\support\AdminVipFormatter;
use app\support\ApiResponse;
use app\support\BusinessTable;
use app\support\FrontendUrlBuilder;
use app\support\MerchantFrontSession;
use app\support\RequestPayload;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class MerchantPoolController
{
    public function index(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->wantsJson($request)
                ? $this->merchantAuthError()
                : $this->merchantLoginRedirect($request, '/merchant/pools');
        }

        if (!$this->wantsJson($request)) {
            return $this->merchantSpaRedirect($request, '/merchant/pools');
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $current = max(1, (int)$request->get('current', $request->get('page', 1)));
        $size = max(1, min((int)$request->get('size', $request->get('limit', 20)), 100));

        $query = $this->poolQuery($merchantId);
        $this->applyFilters($query, $request);

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

        return $this->merchantCollectionSuccess(
            $records,
            [
                'current' => $current,
                'size' => $size,
                'total' => $total,
            ],
            $this->buildSummary($merchant, clone $query),
            $this->buildWriteActions($merchant),
            $this->buildCatalog($merchantId)
        );
    }

    public function show(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantAuthError();
        }

        $id = $this->poolIdFromRequest($request);
        if ($id <= 0) {
            return $this->merchantError('轮询池编号无效', 422, 201);
        }

        $detail = $this->findPoolDetail((int)($merchant['id'] ?? 0), $id);
        if ($detail === null) {
            return $this->merchantError('商户轮询池不存在', 404, 404);
        }

        return $this->merchantDataSuccess($detail, '商户轮询池详情获取成功');
    }

    public function create(Request $request): Response
    {
        $merchant = $this->writeGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $payload = $this->requestPayload($request);

        try {
            $name = $this->normalizePoolName($payload['name'] ?? '');
            $type = $this->normalizePoolType($payload['type'] ?? '');
            $roundType = $this->normalizeRoundType($payload['round_type'] ?? 1);
            $status = $this->normalizeStatus($payload['status'] ?? 1);
        } catch (\InvalidArgumentException $exception) {
            return $this->merchantError($exception->getMessage(), 422, 201);
        }

        if (!$this->paymentTypeExists($type)) {
            return $this->merchantError('支付类型不存在或已停用', 422, 201);
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $now = date('Y-m-d H:i:s');
        $poolId = (int)Db::table(BusinessTable::pollPool())->insertGetId([
            'user_id' => $merchantId,
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

        $detail = $this->findPoolDetail($merchantId, $poolId);
        if ($detail === null) {
            return $this->merchantError('轮询池创建成功，但详情加载失败', 500, 201);
        }

        return $this->merchantDataSuccess(
            array_merge($detail, [
                'created_pool_id' => $poolId,
                'created_pool_label' => (string)((array)($detail['item'] ?? []))['name_label'] ?? ('轮询池 #' . $poolId),
            ]),
            '商户轮询池已创建'
        );
    }

    public function update(Request $request): Response
    {
        $merchant = $this->writeGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $id = $this->poolIdFromRequest($request);
        if ($id <= 0) {
            return $this->merchantError('轮询池编号无效', 422, 201);
        }

        $record = $this->poolRecord($merchantId, $id);
        if ($record === null) {
            return $this->merchantError('商户轮询池不存在', 404, 404);
        }

        $payload = $this->requestPayload($request);

        try {
            $name = $this->normalizePoolName($payload['name'] ?? ($record['name'] ?? ''));
            $roundType = $this->normalizeRoundType($payload['round_type'] ?? ($record['round_type'] ?? 1));
        } catch (\InvalidArgumentException $exception) {
            return $this->merchantError($exception->getMessage(), 422, 201);
        }

        Db::table(BusinessTable::pollPool())
            ->where('id', $id)
            ->where('user_id', $merchantId)
            ->update([
                'name' => $name,
                'round_type' => $roundType,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        $detail = $this->findPoolDetail($merchantId, $id);
        if ($detail === null) {
            return $this->merchantError('轮询池更新成功，但详情加载失败', 500, 201);
        }

        return $this->merchantDataSuccess($detail, '商户轮询池已更新');
    }

    public function status(Request $request): Response
    {
        $merchant = $this->writeGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $id = $this->poolIdFromRequest($request);
        if ($id <= 0) {
            return $this->merchantError('轮询池编号无效', 422, 201);
        }

        if ($this->poolRecord($merchantId, $id) === null) {
            return $this->merchantError('商户轮询池不存在', 404, 404);
        }

        $payload = $this->requestPayload($request);

        try {
            $status = $this->normalizeStatus($payload['status'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            return $this->merchantError($exception->getMessage(), 422, 201);
        }

        Db::table(BusinessTable::pollPool())
            ->where('id', $id)
            ->where('user_id', $merchantId)
            ->update([
                'status' => $status,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        return $this->merchantDataSuccess([
            'item' => $this->findPool($merchantId, $id),
        ], '商户轮询池状态已更新');
    }

    public function channelEditor(Request $request): Response
    {
        $merchant = $this->writeGuard($request, false);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $id = $this->poolIdFromRequest($request);
        if ($id <= 0) {
            return $this->merchantError('轮询池编号无效', 422, 201);
        }

        $editor = $this->buildChannelEditor($merchantId, $id);
        if ($editor === null) {
            return $this->merchantError('商户轮询池不存在', 404, 404);
        }

        return $this->merchantDataSuccess($editor, '轮询池通道编辑器加载成功');
    }

    public function saveChannels(Request $request): Response
    {
        $merchant = $this->writeGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $id = $this->poolIdFromRequest($request);
        if ($id <= 0) {
            return $this->merchantError('轮询池编号无效', 422, 201);
        }

        $record = $this->poolRecord($merchantId, $id);
        if ($record === null) {
            return $this->merchantError('商户轮询池不存在', 404, 404);
        }

        $payload = $this->requestPayload($request);
        try {
            $channels = $this->normalizeChannelPayload($payload['channels'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            return $this->merchantError($exception->getMessage(), 422, 201);
        }

        if ($channels !== []) {
            $validAccountIds = Db::table(BusinessTable::account())
                ->where('user_id', $merchantId)
                ->where('type', trim((string)($record['type'] ?? '')))
                ->whereIn('id', array_column($channels, 'account_id'))
                ->pluck('id')
                ->map(static fn ($value): int => (int)$value)
                ->toArray();

            $validLookup = array_fill_keys($validAccountIds, true);
            foreach ($channels as $channel) {
                if (!isset($validLookup[(int)$channel['account_id']])) {
                    return $this->merchantError('通道分配中存在无效账号，请刷新后重试', 422, 201);
                }
            }
        }

        $now = date('Y-m-d H:i:s');
        Db::transaction(function () use ($id, $merchantId, $channels, $now): void {
            Db::table(BusinessTable::pollPoolItem())
                ->where('pool_id', $id)
                ->where('user_id', $merchantId)
                ->delete();

            if ($channels !== []) {
                $rows = array_map(static function (array $channel) use ($id, $merchantId, $now): array {
                    return [
                        'user_id' => $merchantId,
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
                ->where('user_id', $merchantId)
                ->update([
                    'current_index' => 0,
                    'current_weight' => 1,
                    'last_account_id' => 0,
                    'update_time' => $now,
                ]);
        });

        $detail = $this->findPoolDetail($merchantId, $id);
        $editor = $this->buildChannelEditor($merchantId, $id);
        if ($detail === null || $editor === null) {
            return $this->merchantError('通道分配保存成功，但结果刷新失败', 500, 201);
        }

        return $this->merchantDataSuccess([
            'item' => $detail['item'] ?? null,
            'editable' => $detail['editable'] ?? null,
            'channel_editor' => $editor['editor'] ?? null,
            'saved_channel_count' => count($channels),
        ], $channels === [] ? '轮询池通道已清空' : '轮询池通道分配已保存');
    }

    public function deleteAudit(Request $request): Response
    {
        $merchant = $this->writeGuard($request, false);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $id = $this->poolIdFromRequest($request);
        if ($id <= 0) {
            return $this->merchantError('轮询池编号无效', 422, 201);
        }

        $record = $this->poolRecord($merchantId, $id);
        if ($record === null) {
            return $this->merchantError('商户轮询池不存在', 404, 404);
        }

        $stats = $this->loadPoolStats([$id]);
        $lastAccountId = (int)($record['last_account_id'] ?? 0);
        $lastAccounts = $this->loadAccountMap($lastAccountId > 0 ? [$lastAccountId] : []);
        $item = AdminPaymentPoolFormatter::formatDetail(
            $record,
            $stats[$id] ?? [],
            $lastAccounts[$lastAccountId] ?? null
        );

        return $this->merchantDataSuccess([
            'item' => $item,
            'audit' => $this->buildDeleteAudit($record, $stats[$id] ?? [], $item),
        ], '轮询池删除审计获取成功');
    }

    public function delete(Request $request): Response
    {
        $merchant = $this->writeGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $id = $this->poolIdFromRequest($request);
        if ($id <= 0) {
            return $this->merchantError('轮询池编号无效', 422, 201);
        }

        $record = $this->poolRecord($merchantId, $id);
        if ($record === null) {
            return $this->merchantError('商户轮询池不存在', 404, 404);
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

        $payload = $this->requestPayload($request);
        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return $this->merchantError('删除确认短语不匹配', 422, 201, ['audit' => $audit]);
        }

        Db::transaction(function () use ($id, $merchantId): void {
            Db::table(BusinessTable::pollPoolItem())
                ->where('pool_id', $id)
                ->where('user_id', $merchantId)
                ->delete();
            Db::table(BusinessTable::pollPool())
                ->where('id', $id)
                ->where('user_id', $merchantId)
                ->delete();
        });

        return $this->merchantDataSuccess([
            'deleted_pool_id' => $id,
            'deleted_pool_label' => (string)($audit['pool_label'] ?? ('轮询池 #' . $id)),
            'audit' => $audit,
        ], '商户轮询池已删除');
    }

    private function poolQuery(int $merchantId): Builder
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
            )
            ->where('pool.user_id', $merchantId);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword) {
                $builder
                    ->where('pool.name', 'like', '%' . $keyword . '%')
                    ->orWhere('pool.type', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder
                        ->orWhere('pool.id', (int)$keyword)
                        ->orWhere('pool.last_account_id', (int)$keyword);
                }
            });
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

    private function buildSummary(array $merchant, Builder $query): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $rows = (clone $query)
            ->select('pool.id')
            ->get()
            ->toArray();

        $poolIds = array_values(array_unique(array_map(
            static fn ($row): int => (int)((array)$row)['id'],
            $rows
        )));

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

        $vipPackage = $this->vipPackage($merchant);

        return [
            'merchant_id' => $merchantId,
            'merchant_username' => trim((string)($merchant['username'] ?? '')),
            'vip_label' => $vipPackage['name'] ?? (trim((string)($merchant['vip_name'] ?? '')) ?: '普通商户'),
            'total_count' => count($poolIds),
            'enabled_count' => (int)(clone $query)->where('pool.status', 1)->count('pool.id'),
            'disabled_count' => (int)(clone $query)->where('pool.status', '<>', 1)->count('pool.id'),
            'configured_pool_count' => $configuredPoolCount,
            'empty_pool_count' => $emptyPoolCount,
            'configured_channel_count' => $configuredChannelCount,
            'healthy_pool_count' => $healthyPoolCount,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function buildWriteActions(array $merchant): array
    {
        $canWrite = (int)($merchant['is_frozen'] ?? 0) !== 1;

        return [
            'create' => $canWrite,
            'edit' => $canWrite,
            'status' => $canWrite,
            'remove' => $canWrite,
        ];
    }

    private function buildCatalog(int $merchantId): array
    {
        $methodRows = Db::table(BusinessTable::payment())
            ->select('id', 'name', 'type', 'status', 'sort')
            ->where('status', 1)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->toArray();

        $paymentTypes = array_map(static function ($row): array {
            $record = (array)$row;
            $type = trim((string)($record['type'] ?? ''));
            $name = AdminFixtureTextNormalizer::normalize(trim((string)($record['name'] ?? '')));

            return [
                'id' => (int)($record['id'] ?? 0),
                'value' => $type,
                'label' => $name !== '' ? $name : AdminOrderFormatter::paymentTypeLabel($type),
                'type_label' => AdminOrderFormatter::paymentTypeLabel($type),
                'sort' => (int)($record['sort'] ?? 0),
            ];
        }, $methodRows);

        $knownTypes = Db::table(BusinessTable::account())
            ->where('user_id', $merchantId)
            ->select('type')
            ->distinct()
            ->pluck('type')
            ->map(static fn ($value): string => trim((string)$value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->toArray();

        foreach ($knownTypes as $type) {
            $exists = false;
            foreach ($paymentTypes as $item) {
                if (($item['value'] ?? '') === $type) {
                    $exists = true;
                    break;
                }
            }

            if ($exists) {
                continue;
            }

            $paymentTypes[] = [
                'id' => 0,
                'value' => $type,
                'label' => AdminOrderFormatter::paymentTypeLabel($type),
                'type_label' => AdminOrderFormatter::paymentTypeLabel($type),
                'sort' => 9999,
            ];
        }

        usort($paymentTypes, static function (array $left, array $right): int {
            $sortCompare = ((int)($left['sort'] ?? 0)) <=> ((int)($right['sort'] ?? 0));
            if ($sortCompare !== 0) {
                return $sortCompare;
            }

            return strcmp((string)($left['label'] ?? ''), (string)($right['label'] ?? ''));
        });

        return [
            'payment_types' => $paymentTypes,
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

    private function findPool(int $merchantId, int $id): ?array
    {
        $record = $this->poolRecord($merchantId, $id);
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

    private function findPoolDetail(int $merchantId, int $id): ?array
    {
        $record = $this->poolRecord($merchantId, $id);
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

    private function buildChannelEditor(int $merchantId, int $id): ?array
    {
        $record = $this->poolRecord($merchantId, $id);
        if ($record === null) {
            return null;
        }

        $detail = $this->findPoolDetail($merchantId, $id);
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
                'account_label' => '#' . $accountId . ' / 已删除账号',
                'channel_label' => trim((string)($item['account_code'] ?? '')) ?: '缺失通道',
                'weight' => max(1, (int)($item['weight'] ?? 1)),
                'sort_order' => max(0, (int)($item['sort'] ?? 0)) + 1,
                'status_text' => '缺失',
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
            ->where('account.user_id', $merchantId)
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
                    ? '#' . $accountId . ' / ' . $channelLabel . ' / ' . $memo
                    : '#' . $accountId . ' / ' . $channelLabel,
                'channel_label' => $channelLabel,
                'memo' => $memo,
                'status' => (int)($account['status'] ?? 0),
                'is_status' => (int)($account['is_status'] ?? 0),
                'status_text' => $statusLabel,
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
            $warnings[] = '当前商户在该支付类型下还没有可分配的收款账号。';
        }
        if ($missingSelectedAccounts !== []) {
            $warnings[] = '保存后会自动清理已经失效的轮询条目。';
        }
        if ($disabledAvailableCount > 0) {
            $warnings[] = '离线或已关闭的账号仍可保留在轮询池中，但路由时会自动跳过。';
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
                '删除轮询池只会清理轮询游标和已选账号条目。',
                '商户账号、上游通道和历史订单不会被一并删除。',
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

    private function poolRecord(int $merchantId, int $id): ?array
    {
        $row = $this->poolQuery($merchantId)
            ->where('pool.id', $id)
            ->first();

        return $row ? (array)$row : null;
    }

    private function poolIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function paymentTypeExists(string $type): bool
    {
        return Db::table(BusinessTable::payment())
            ->where('type', $type)
            ->where('status', 1)
            ->exists();
    }

    private function normalizePoolName(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('轮询池名称格式无效');
        }

        $name = trim((string)$value);
        if ($name === '') {
            throw new \InvalidArgumentException('轮询池名称不能为空');
        }

        if (mb_strlen($name) > 64) {
            throw new \InvalidArgumentException('轮询池名称过长');
        }

        return $name;
    }

    private function normalizePoolType(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('支付类型格式无效');
        }

        $type = strtolower(trim((string)$value));
        if ($type === '') {
            throw new \InvalidArgumentException('支付类型不能为空');
        }

        if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/', $type)) {
            throw new \InvalidArgumentException('支付类型格式无效');
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
            default => throw new \InvalidArgumentException('轮询方式必须为顺序轮询或随机轮询'),
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
            default => throw new \InvalidArgumentException('状态必须为启用或停用'),
        };
    }

    private function normalizeChannelPayload(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException('轮询池通道必须是数组');
        }

        $normalized = [];
        $seen = [];

        foreach ($value as $index => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('轮询池通道条目格式无效');
            }

            $accountId = (int)($row['account_id'] ?? 0);
            if ($accountId <= 0) {
                throw new \InvalidArgumentException('轮询池通道条目缺少有效账号');
            }

            if (isset($seen[$accountId])) {
                throw new \InvalidArgumentException('轮询池通道不能重复添加同一账号');
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

    private function channelStatusMeta(int $status, int $enabled): array
    {
        if ($status !== 1) {
            return ['离线', 'warning'];
        }

        if ($enabled !== 1) {
            return ['停用', 'info'];
        }

        return ['可用', 'success'];
    }

    private function poolDeleteConfirmationPhrase(int $poolId): string
    {
        return '删除轮询池 ' . $poolId;
    }

    private function merchantFromRequest(Request $request): ?array
    {
        $token = MerchantFrontSession::resolveToken($request);
        if ($token === '') {
            return null;
        }

        $row = Db::table(BusinessTable::user('merchant'))
            ->leftJoin(BusinessTable::vip('vip'), 'merchant.vip_id', '=', 'vip.id')
            ->select(
                'merchant.id',
                'merchant.username',
                'merchant.vip_id',
                'merchant.vip_time',
                'merchant.is_frozen',
                'merchant.frozen_reason',
                'vip.name as vip_name'
            )
            ->where('merchant.token', $token)
            ->first();

        return $row ? (array)$row : null;
    }

    private function vipPackage(array $merchant): ?array
    {
        $vipId = (int)($merchant['vip_id'] ?? 0);
        if ($vipId <= 0) {
            return null;
        }

        $row = Db::table(BusinessTable::vip())
            ->where('id', $vipId)
            ->first();

        return $row ? AdminVipFormatter::format((array)$row) : null;
    }

    private function merchantAuthError(): Response
    {
        $payload = [
            'code' => 401,
            'msg' => '请先登录商户账号',
            'message' => '请先登录商户账号',
        ];
        $payload['msg'] = ApiResponse::normalizeText((string)$payload['msg']);
        $payload['message'] = ApiResponse::normalizeText((string)$payload['message']);

        return json($payload, JSON_UNESCAPED_UNICODE)->withStatus(401);
    }

    private function merchantCollectionSuccess(
        array $records,
        array $pagination,
        array $summary,
        array $writeActions,
        array $catalog = []
    ): Response {
        $payload = [
            'code' => 0,
            'msg' => '成功',
            'message' => '成功',
            'data' => $records,
            'records' => $records,
            'pagination' => [
                'current' => (int)($pagination['current'] ?? 1),
                'size' => (int)($pagination['size'] ?? count($records)),
                'total' => (int)($pagination['total'] ?? count($records)),
            ],
            'summary' => $summary,
            'write_actions' => $writeActions,
            'catalog' => $catalog,
        ];
        $payload['msg'] = ApiResponse::normalizeText((string)$payload['msg']);
        $payload['message'] = ApiResponse::normalizeText((string)$payload['message']);

        return json($payload, JSON_UNESCAPED_UNICODE);
    }

    private function merchantDataSuccess(array $data, string $message): Response
    {
        $message = ApiResponse::normalizeText($message);

        return json([
            'code' => 0,
            'msg' => $message,
            'message' => $message,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function merchantError(
        string $message,
        int $status = 422,
        int $code = 201,
        array $extra = []
    ): Response {
        $message = ApiResponse::normalizeText($message);

        return json(array_merge([
            'code' => $code,
            'msg' => $message,
            'message' => $message,
        ], $extra), JSON_UNESCAPED_UNICODE)->withStatus($status);
    }

    private function writeGuard(Request $request, bool $requireWritable = true): array|Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantAuthError();
        }

        if ($requireWritable && (int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->merchantError('商户账户已被冻结，暂时无法维护轮询池', 403, 201);
        }

        return $merchant;
    }

    private function requestPayload(Request $request): array
    {
        $payload = RequestPayload::all($request);
        if ($payload !== []) {
            return $payload;
        }

        $post = $request->post();
        return is_array($post) ? $post : [];
    }

    private function wantsJson(Request $request): bool
    {
        $format = strtolower(trim((string)$request->get('format', '')));
        if ($format === 'json') {
            return true;
        }

        $accept = strtolower(trim((string)$request->header('accept', '')));

        return str_contains($accept, 'application/json')
            || str_contains($accept, 'text/json')
            || str_contains($accept, '+json');
    }

    private function merchantFrontendBaseUrl(Request $request): string
    {
        return FrontendUrlBuilder::merchantBaseUrl($request);
    }

    private function merchantLoginRedirect(Request $request, ?string $redirectPath = null): Response
    {
        return redirect($this->merchantLoginUrl($request, $redirectPath));
    }

    private function merchantLoginUrl(Request $request, ?string $redirectPath = null): string
    {
        $query = [];
        if ($redirectPath !== null && $redirectPath !== '' && $redirectPath !== '/merchant/login') {
            $query['redirect'] = $redirectPath;
        }

        return $this->withHashPath($this->merchantFrontendBaseUrl($request), '/merchant/login', $query);
    }

    private function merchantSpaRedirect(Request $request, string $targetPath, array $query = []): Response
    {
        return redirect($this->withHashPath($this->merchantFrontendBaseUrl($request), $targetPath, $query));
    }

    private function withHashPath(string $baseUrl, string $path, array $query = []): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $path = '/' . ltrim($path, '/');
        $queryString = $query === [] ? '' : ('?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));

        if (str_contains($baseUrl, '#')) {
            return preg_replace('#/+$#', '', $baseUrl) . $path . $queryString;
        }

        return $baseUrl . '/#' . $path . $queryString;
    }

    private function requestOrigin(Request $request): string
    {
        return $this->requestScheme($request) . '://' . trim((string)$request->host());
    }

    private function requestScheme(Request $request): string
    {
        $forwardedProto = strtolower(trim((string)$request->header('x-forwarded-proto', '')));
        if ($forwardedProto !== '') {
            $proto = trim((string)(explode(',', $forwardedProto)[0] ?? ''));
            if (in_array($proto, ['http', 'https'], true)) {
                return $proto;
            }
        }

        if ((string)$request->header('front-end-https', '') === 'on') {
            return 'https';
        }

        if ((string)$request->header('x-forwarded-port', '') === '443') {
            return 'https';
        }

        return 'http';
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
