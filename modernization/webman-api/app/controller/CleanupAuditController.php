<?php

namespace app\controller;

use app\support\BusinessTable;
use app\support\AdminCleanupAuditFormatter;
use app\support\AdminRouteAuthorization;
use app\support\ApiResponse;
use app\support\RequestPayload;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class CleanupAuditController
{
    private const LEGACY_CLEANUP_PAGE = '/' . 'aip' . 'ay.shop/clear';
    private const LEGACY_ORDER_ENDPOINT = '/' . 'aip' . 'ay.shop/clearOrder';
    private const LEGACY_RECHARGE_ENDPOINT = '/' . 'aip' . 'ay.shop/clearRecharge';
    private const LEGACY_ADMIN_LOG_ENDPOINT = '/' . 'aip' . 'ay.shop/clearAdminLog';
    private const LEGACY_USER_LOG_ENDPOINT = '/' . 'aip' . 'ay.shop/clearUserLog';

    private const ITEMS = [
        'order_unpaid' => [
            'title' => '订单清理',
            'category' => 'orders',
            'category_label' => '订单',
            'table_name' => '',
            'target_description' => '未支付订单记录',
            'legacy_page' => self::LEGACY_CLEANUP_PAGE,
            'legacy_endpoint' => self::LEGACY_ORDER_ENDPOINT,
            'legacy_action_label' => '执行后会永久删除所有未支付订单记录。',
            'action_mode' => 'delete_where',
            'action_mode_label' => '条件删除',
            'action_scope_label' => '删除全部未支付订单',
            'action_label' => '清理未支付订单',
            'threshold_value' => null,
            'threshold_label' => '存在命中记录即可清理',
            'note' => '当前按未支付状态统一清理，仅删除未支付订单。',
        ],
        'recharge_unpaid' => [
            'title' => '充值记录清理',
            'category' => 'recharges',
            'category_label' => '充值',
            'table_name' => '',
            'target_description' => '未支付充值记录',
            'legacy_page' => self::LEGACY_CLEANUP_PAGE,
            'legacy_endpoint' => self::LEGACY_RECHARGE_ENDPOINT,
            'legacy_action_label' => '执行后会永久删除所有未支付充值记录。',
            'action_mode' => 'delete_where',
            'action_mode_label' => '条件删除',
            'action_scope_label' => '删除全部未支付充值记录',
            'action_label' => '清理未支付充值记录',
            'threshold_value' => null,
            'threshold_label' => '存在命中记录即可清理',
            'note' => '当前按未支付状态统一清理，仅删除未支付充值记录。',
        ],
        'admin_logs' => [
            'title' => '后台日志清理',
            'category' => 'admin_logs',
            'category_label' => '后台日志',
            'table_name' => 'admin_admin_log',
            'target_description' => '后台操作日志整表',
            'legacy_page' => self::LEGACY_CLEANUP_PAGE,
            'legacy_endpoint' => self::LEGACY_ADMIN_LOG_ENDPOINT,
            'legacy_action_label' => '执行后会直接清空整张后台日志表。',
            'action_mode' => 'truncate',
            'action_mode_label' => '整表清空',
            'action_scope_label' => '清空整个后台操作日志表',
            'action_label' => '清空后台日志',
            'threshold_value' => 500,
            'threshold_label' => '建议 500 条以上再清理',
            'note' => '当日志条数较少时通常无需清理，如需释放空间仍可手动执行整表清理。',
        ],
        'front_logs' => [
            'title' => '用户日志清理',
            'category' => 'front_logs',
            'category_label' => '商户日志',
            'table_name' => 'admin_front_log',
            'target_description' => '商户行为日志整表',
            'legacy_page' => self::LEGACY_CLEANUP_PAGE,
            'legacy_endpoint' => self::LEGACY_USER_LOG_ENDPOINT,
            'legacy_action_label' => '执行后会直接清空整张商户日志表。',
            'action_mode' => 'truncate',
            'action_mode_label' => '整表清空',
            'action_scope_label' => '清空整个商户行为日志表',
            'action_label' => '清空商户日志',
            'threshold_value' => 500,
            'threshold_label' => '建议 500 条以上再清理',
            'note' => '当日志条数较少时通常无需清理，如需释放空间仍可手动执行整表清理。',
        ],
    ];

    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $items = $this->auditItems();
        $filtered = $this->applyFilters($items, $request);
        $total = count($filtered);
        $records = array_slice($filtered, ($current - 1) * $size, $size);

        return ApiResponse::success([
            'records' => $records,
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => $this->summary($filtered),
        ]);
    }

    public function show(Request $request): Response
    {
        $item = $this->itemFromRequest($request);
        if ($item === null) {
            return ApiResponse::error('清理项不存在或已失效', 422, null, 422);
        }

        return ApiResponse::success(['item' => $item]);
    }

    public function actionAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'execute');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $item = $this->itemFromRequest($request);
        if ($item === null) {
            return ApiResponse::error('清理项不存在或已失效', 422, null, 422);
        }

        return ApiResponse::success([
            'item' => $item,
            'audit' => $this->buildExecutionAudit($item),
        ]);
    }

    public function execute(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'execute');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $item = $this->itemFromRequest($request);
        if ($item === null) {
            return ApiResponse::error('清理项不存在或已失效', 422, null, 422);
        }

        $audit = $this->buildExecutionAudit($item);
        if (empty($audit['can_execute'])) {
            return ApiResponse::error('当前清理项暂不可执行', 422, ['audit' => $audit], 422);
        }

        $payload = RequestPayload::all($request);
        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('确认短语不匹配', 422, ['audit' => $audit], 422);
        }

        $deletedCount = $this->executeCleanupAction($item);
        $freshItem = $this->itemByKey((string)($item['key'] ?? ''));
        if ($freshItem === null) {
            return ApiResponse::error('清理项不存在', 404, null, 404);
        }

        $this->recordAdminCleanupExecution($request, $item, $audit, $deletedCount, $freshItem);

        return ApiResponse::success([
            'key' => (string)($item['key'] ?? ''),
            'title' => (string)($item['title'] ?? ''),
            'action_label' => (string)($item['action_label'] ?? ''),
            'action_mode' => (string)($item['action_mode'] ?? ''),
            'deleted_count' => $deletedCount,
            'audit' => $audit,
            'item' => $freshItem,
        ], '清理执行完成');
    }

    private function auditItems(): array
    {
        $items = [];

        foreach (array_keys(self::ITEMS) as $key) {
            $items[] = AdminCleanupAuditFormatter::format($this->buildItem($key));
        }

        return $items;
    }

    private function itemFromRequest(Request $request): ?array
    {
        $key = $this->cleanupKeyFromRequest($request);
        if ($key === '') {
            return null;
        }

        return $this->itemByKey($key);
    }

    private function itemByKey(string $key): ?array
    {
        if (!isset(self::ITEMS[$key])) {
            return null;
        }

        return AdminCleanupAuditFormatter::format($this->buildItem($key));
    }

    private function buildItem(string $key): array
    {
        $definition = self::ITEMS[$key];

        return match ($key) {
            'order_unpaid' => $this->buildUnpaidItem($key, $definition, BusinessTable::order(), 'status', 0),
            'recharge_unpaid' => $this->buildUnpaidItem($key, $definition, BusinessTable::recharge(), 'status', 0),
            'admin_logs' => $this->buildLogItem($key, $definition, 'admin_admin_log'),
            'front_logs' => $this->buildLogItem($key, $definition, 'admin_front_log'),
            default => array_merge($definition, [
                'key' => $key,
                'total_count' => 0,
                'target_count' => 0,
                'latest_record_time' => null,
                'latest_target_time' => null,
                'recommended' => false,
            ]),
        };
    }

    private function buildExecutionAudit(array $item): array
    {
        $key = (string)($item['key'] ?? '');
        $title = (string)($item['title'] ?? '');
        $actionMode = (string)($item['action_mode'] ?? 'delete_where');
        $targetCount = max(0, (int)($item['target_count'] ?? 0));
        $totalCount = max(0, (int)($item['total_count'] ?? 0));
        $thresholdValue = isset($item['threshold_value']) ? (int)$item['threshold_value'] : null;
        $thresholdMet = $thresholdValue !== null ? $totalCount >= $thresholdValue : null;
        $canExecute = !empty($item['action_available']) && $targetCount > 0;
        $blockingReasons = [];
        $warnings = [];

        if (!$canExecute) {
            $blockingReasons[] = '当前没有命中可清理数据，请刷新列表后重试。';
        }

        if ($actionMode === 'truncate') {
            $warnings[] = sprintf(
                '该操作会直接清空 %s 表，无法撤销。',
                (string)($item['table_name'] ?? '目标')
            );
        } else {
            $warnings[] = sprintf('该操作会永久删除 %d 条命中记录，无法撤销。', $targetCount);
        }

        $keepRowCount = max(0, $totalCount - $targetCount);
        if ($keepRowCount > 0) {
            $warnings[] = sprintf('执行后将保留 %d 条未命中的记录。', $keepRowCount);
        }

        if ($thresholdValue !== null && $totalCount > 0 && !$thresholdMet) {
            $warnings[] = sprintf(
                '当前日志量低于建议阈值 %d 条，通常无需清理，但系统仍允许手动执行。',
                $thresholdValue
            );
        }

        $latestTargetTime = trim((string)($item['latest_target_time'] ?? ''));
        if ($latestTargetTime !== '') {
            $warnings[] = '最近一次命中记录时间：' . $latestTargetTime;
        }

        $latestRecordTime = trim((string)($item['latest_record_time'] ?? ''));
        if ($latestRecordTime !== '' && $latestRecordTime !== $latestTargetTime) {
            $warnings[] = '最近一条源表记录时间：' . $latestRecordTime;
        }

        return [
            'key' => $key,
            'title' => $title,
            'table_name' => (string)($item['table_name'] ?? ''),
            'action_label' => (string)($item['action_label'] ?? $title),
            'action_mode' => $actionMode,
            'action_mode_label' => (string)($item['action_mode_label'] ?? ''),
            'action_scope_label' => (string)($item['action_scope_label'] ?? ''),
            'target_description' => (string)($item['target_description'] ?? ''),
            'can_execute' => $canExecute,
            'confirmation_phrase' => $canExecute ? $this->executionConfirmationPhrase($item) : '',
            'blocking_reasons' => $blockingReasons,
            'warnings' => array_values(array_unique($warnings)),
            'summary' => [
                'total_count' => $totalCount,
                'delete_row_count' => $targetCount,
                'keep_row_count' => $keepRowCount,
                'ratio' => (float)($item['ratio'] ?? 0),
                'ratio_label' => (string)($item['ratio_label'] ?? '0.00%'),
                'threshold_value' => $thresholdValue,
                'threshold_met' => $thresholdMet,
                'recommended' => !empty($item['recommended']),
                'latest_record_time' => $latestRecordTime !== '' ? $latestRecordTime : null,
                'latest_target_time' => $latestTargetTime !== '' ? $latestTargetTime : null,
            ],
        ];
    }

    private function buildUnpaidItem(
        string $key,
        array $definition,
        string $table,
        string $statusColumn,
        int $pendingValue
    ): array {
        $totalCount = $this->safeCount($table);
        $targetCount = $this->safeCount($table, [$statusColumn => $pendingValue]);
        $latestTargetTime = $this->safeMax($table, 'create_time', [$statusColumn => $pendingValue]);
        $latestRecordTime = $this->safeMax($table, 'create_time');

        return array_merge($definition, [
            'key' => $key,
            'table_name' => $table,
            'total_count' => $totalCount,
            'target_count' => $targetCount,
            'latest_record_time' => $latestRecordTime,
            'latest_target_time' => $latestTargetTime,
            'recommended' => $targetCount > 0,
        ]);
    }

    private function buildLogItem(string $key, array $definition, string $table): array
    {
        $totalCount = $this->safeCount($table);
        $latestRecordTime = $this->safeMax($table, 'create_time');
        $thresholdValue = isset($definition['threshold_value']) ? (int)$definition['threshold_value'] : null;

        return array_merge($definition, [
            'key' => $key,
            'total_count' => $totalCount,
            'target_count' => $totalCount,
            'latest_record_time' => $latestRecordTime,
            'latest_target_time' => $latestRecordTime,
            'recommended' => $thresholdValue !== null && $totalCount >= $thresholdValue,
        ]);
    }

    private function executeCleanupAction(array $item): int
    {
        return match ((string)($item['key'] ?? '')) {
            'order_unpaid' => (int)Db::table(BusinessTable::order())->where('status', 0)->delete(),
            'recharge_unpaid' => (int)Db::table(BusinessTable::recharge())->where('status', 0)->delete(),
            'admin_logs' => $this->truncateTableWithCount('admin_admin_log', (int)($item['target_count'] ?? 0)),
            'front_logs' => $this->truncateTableWithCount('admin_front_log', (int)($item['target_count'] ?? 0)),
            default => 0,
        };
    }

    private function truncateTableWithCount(string $table, int $count): int
    {
        Db::statement('TRUNCATE TABLE ' . $table);
        return $count;
    }

    private function applyFilters(array $items, Request $request): array
    {
        $keyword = mb_strtolower(trim((string)$request->get('keyword', '')));
        $category = trim((string)$request->get('category', ''));
        $status = trim((string)$request->get('status', ''));

        return array_values(array_filter($items, static function (array $item) use ($keyword, $category, $status): bool {
            if ($category !== '' && $item['category'] !== $category) {
                return false;
            }

            if ($status !== '') {
                if ($status === 'recommended' && !$item['recommended']) {
                    return false;
                }

                if ($status === 'stable' && $item['recommended']) {
                    return false;
                }
            }

            if ($keyword === '') {
                return true;
            }

            $haystack = mb_strtolower(implode(' ', array_filter([
                $item['key'] ?? '',
                $item['title'] ?? '',
                $item['category_label'] ?? '',
                $item['target_description'] ?? '',
                $item['table_name'] ?? '',
                $item['legacy_endpoint'] ?? '',
                $item['legacy_action_label'] ?? '',
                $item['action_label'] ?? '',
                $item['action_scope_label'] ?? '',
                $item['maintenance_note'] ?? '',
                $item['note'] ?? '',
            ])));

            return str_contains($haystack, $keyword);
        }));
    }

    private function summary(array $items): array
    {
        $recommendedCount = count(array_filter($items, static fn (array $item): bool => (bool)$item['recommended']));
        $targetRowCount = array_reduce($items, static fn (int $carry, array $item): int => $carry + (int)($item['target_count'] ?? 0), 0);
        $thresholdGuardedCount = count(array_filter($items, static fn (array $item): bool => ($item['threshold_value'] ?? null) !== null));

        return [
            'item_count' => count($items),
            'recommended_count' => $recommendedCount,
            'stable_count' => count($items) - $recommendedCount,
            'target_row_count' => $targetRowCount,
            'threshold_guarded_count' => $thresholdGuardedCount,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function safeCount(string $table, array $where = []): int
    {
        try {
            $query = Db::table($table);
            foreach ($where as $column => $value) {
                $query->where($column, $value);
            }

            return (int)$query->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeMax(string $table, string $column, array $where = []): ?string
    {
        try {
            $query = Db::table($table);
            foreach ($where as $whereColumn => $value) {
                $query->where($whereColumn, $value);
            }

            $value = $query->max($column);
        } catch (\Throwable) {
            return null;
        }

        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }

    private function cleanupKeyFromRequest(Request $request): string
    {
        return trim((string)($request->route ? $request->route->param('key', '') : ''));
    }

    private function executionConfirmationPhrase(array $item): string
    {
        $key = strtoupper(str_replace('-', '_', (string)($item['key'] ?? '')));
        $targetCount = max(0, (int)($item['target_count'] ?? 0));
        $digest = strtoupper(substr(md5(implode('|', [
            $key,
            (string)($item['table_name'] ?? ''),
            (int)($item['total_count'] ?? 0),
            $targetCount,
            (string)($item['latest_target_time'] ?? ''),
            (string)($item['latest_record_time'] ?? ''),
        ])), 0, 6));

        return sprintf('执行清理 %s %d-%s', $key, $targetCount, $digest);
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'SystemCleanupAudit', $authMark);
    }

    private function adminIdFromRequest(Request $request): int
    {
        return (int)(((array)($request->admin ?? []))['id'] ?? 0);
    }

    private function recordAdminCleanupExecution(
        Request $request,
        array $item,
        array $audit,
        int $deletedCount,
        array $freshItem
    ): void {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $title = $this->truncateLogText((string)($item['title'] ?? ''), 80);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/cleanup-audit/' . (string)($item['key'] ?? '') . '/execute',
            'desc' => sprintf(
                'cleanup execute key=%s title="%s" table=%s mode=%s deleted=%d total_before=%d target_before=%d total_after=%d',
                (string)($item['key'] ?? ''),
                $title,
                (string)($item['table_name'] ?? ''),
                (string)($item['action_mode'] ?? ''),
                $deletedCount,
                (int)($summary['total_count'] ?? 0),
                (int)($summary['delete_row_count'] ?? 0),
                (int)($freshItem['total_count'] ?? 0)
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
}
