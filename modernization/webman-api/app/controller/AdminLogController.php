<?php

namespace app\controller;

use app\controller\concerns\AdminControllerFormatSupport;
use app\support\AdminLogFormatter;
use app\support\AdminRouteAuthorization;
use app\support\ApiResponse;
use app\support\RequestPayload;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class AdminLogController
{
    use AdminControllerFormatSupport;

    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $query = $this->adminLogQuery();
        $this->applyFilters($query, $request);

        $total = (int)(clone $query)->count('admin_admin_log.id');
        $rows = $query
            ->orderByDesc('admin_admin_log.id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        $records = array_map(
            static fn($row): array => AdminLogFormatter::format((array)$row),
            $rows
        );

        return ApiResponse::success([
            'records' => $records,
            'current' => $current,
            'size' => $size,
            'total' => $total,
        ]);
    }

    public function show(Request $request): Response
    {
        $id = $this->logIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('后台日志编号不能为空', 422, null, 422);
        }

        $record = $this->logRecord($id);
        if ($record === null) {
            return ApiResponse::error('后台日志不存在', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminLogFormatter::format($record),
        ]);
    }

    public function cleanupAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'removeLog');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        return ApiResponse::success([
            'audit' => $this->adminLogCleanupAudit(),
        ]);
    }

    public function cleanup(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'removeLog');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $audit = $this->adminLogCleanupAudit();
        if (empty($audit['can_cleanup'])) {
            return ApiResponse::error('当前没有可清理的后台日志记录', 422, ['audit' => $audit], 422);
        }

        $confirmationPhrase = trim((string)(RequestPayload::all($request)['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('确认短语不匹配', 422, ['audit' => $audit], 422);
        }

        Db::transaction(function () use ($request, $audit): void {
            Db::table('admin_admin_log')->delete();
            $this->recordAdminLogCleanup($request, $audit);
        });

        return ApiResponse::success([
            'deleted_count' => (int)(($audit['summary'] ?? [])['total_count'] ?? 0),
            'audit' => $audit,
        ], '后台日志已清理');
    }

    private function adminLogQuery(): Builder
    {
        return Db::table('admin_admin_log')
            ->leftJoin('admin_admin', 'admin_admin_log.uid', '=', 'admin_admin.id')
            ->select(
                'admin_admin_log.id',
                'admin_admin_log.uid as admin_id',
                'admin_admin_log.url',
                'admin_admin_log.desc',
                'admin_admin_log.ip',
                'admin_admin_log.user_agent',
                'admin_admin_log.create_time',
                'admin_admin.username as admin_username',
                'admin_admin.nickname as admin_nickname'
            );
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('admin_admin.username', 'like', '%' . $keyword . '%')
                    ->orWhere('admin_admin.nickname', 'like', '%' . $keyword . '%')
                    ->orWhere('admin_admin_log.url', 'like', '%' . $keyword . '%')
                    ->orWhere('admin_admin_log.ip', 'like', '%' . $keyword . '%')
                    ->orWhere('admin_admin_log.desc', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder
                        ->orWhere('admin_admin_log.id', (int)$keyword)
                        ->orWhere('admin_admin_log.uid', (int)$keyword);
                }
            });
        }

        $adminId = trim((string)$request->get('admin_id', $request->get('uid', '')));
        if ($adminId !== '' && ctype_digit($adminId)) {
            $query->where('admin_admin_log.uid', (int)$adminId);
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', ''));
        $endDate = $this->normalizeDate((string)$request->get('end_date', ''));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('admin_admin_log.create_time', '>=', $startDate . ' 00:00:00')
                ->where(
                    'admin_admin_log.create_time',
                    '<',
                    date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day'))
                );
        }
    }

    private function logIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function logRecord(int $id): ?array
    {
        $row = $this->adminLogQuery()
            ->where('admin_admin_log.id', $id)
            ->first();

        return $row ? (array)$row : null;
    }

    private function adminLogCleanupAudit(): array
    {
        $query = $this->adminLogQuery();
        $summary = [
            'total_count' => (int)(clone $query)->count('admin_admin_log.id'),
            'admin_count' => (int)(clone $query)
                ->where('admin_admin_log.uid', '>', 0)
                ->distinct()
                ->count('admin_admin_log.uid'),
            'payload_log_count' => (int)(clone $query)
                ->whereNotNull('admin_admin_log.desc')
                ->where('admin_admin_log.desc', '<>', '')
                ->count('admin_admin_log.id'),
            'first_log_id' => (int)((clone $query)->min('admin_admin_log.id') ?: 0),
            'last_log_id' => (int)((clone $query)->max('admin_admin_log.id') ?: 0),
        ];

        $canCleanup = $summary['total_count'] > 0;
        $warnings = [];

        if (!$canCleanup) {
            $warnings[] = '当前没有可清理的后台日志记录。';
        } else {
            $warnings[] = '执行“全部清理”后，会永久删除当前命中的后台日志记录。';
            $warnings[] = '清理完成后系统会立即写入一条新的清理回执，便于后续审计追踪。';

            if ($summary['payload_log_count'] > 0) {
                $warnings[] = sprintf(
                    '本次清理会删除 %d 条包含请求载荷文本的后台日志记录。',
                    $summary['payload_log_count']
                );
            }

            if ($summary['admin_count'] > 0) {
                $warnings[] = sprintf(
                    '当前清理范围涉及 %d 个有操作记录的管理员账号。',
                    $summary['admin_count']
                );
            }

            if ($summary['total_count'] < 500) {
                $warnings[] = '当前日志量低于建议清理阈值 500 条，当前清理属于可选操作。';
            }
        }

        return [
            'can_cleanup' => $canCleanup,
            'confirmation_phrase' => $canCleanup ? $this->adminLogCleanupConfirmationPhrase($summary) : '',
            'summary' => $summary,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<string, int> $summary
     */
    private function adminLogCleanupConfirmationPhrase(array $summary): string
    {
        return sprintf(
            '清理后台日志 %d-%s',
            (int)($summary['total_count'] ?? 0),
            strtoupper(substr(md5(implode('|', [
                (int)($summary['total_count'] ?? 0),
                (int)($summary['payload_log_count'] ?? 0),
                (int)($summary['admin_count'] ?? 0),
                (int)($summary['first_log_id'] ?? 0),
                (int)($summary['last_log_id'] ?? 0),
            ])), 0, 6))
        );
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'SystemAdminLogs', $authMark);
    }

    private function adminIdFromRequest(Request $request): int
    {
        return (int)(((array)($request->admin ?? []))['id'] ?? 0);
    }

    private function recordAdminLogCleanup(Request $request, array $audit): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/admin-logs/cleanup',
            'desc' => sprintf(
                'admin log cleanup deleted=%d admin_count=%d payload_logs=%d first_id=%d last_id=%d remaining_after=1',
                (int)($summary['total_count'] ?? 0),
                (int)($summary['admin_count'] ?? 0),
                (int)($summary['payload_log_count'] ?? 0),
                (int)($summary['first_log_id'] ?? 0),
                (int)($summary['last_log_id'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

}
