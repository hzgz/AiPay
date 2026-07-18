<?php

namespace app\controller;

use app\support\AdminRechargeFormatter;
use app\support\ApiResponse;
use app\support\BusinessTable;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class RechargeController
{
    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $query = $this->rechargeQuery();
        $this->applyFilters($query, $request);

        $summaryQuery = $this->baseRechargeQuery();
        $this->applyFilters($summaryQuery, $request);

        $total = (int)(clone $query)->count('recharge.id');
        $rows = $query
            ->orderByDesc('recharge.id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        $records = array_map(
            static fn ($row): array => AdminRechargeFormatter::format((array)$row),
            $rows
        );

        return ApiResponse::success([
            'records' => $records,
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => $this->summary($summaryQuery),
        ]);
    }

    public function show(Request $request): Response
    {
        $id = $this->rechargeIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('recharge id is required', 422, null, 422);
        }

        $record = $this->rechargeRecord($id);
        if ($record === null) {
            return ApiResponse::error('recharge record not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminRechargeFormatter::format($record),
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword) {
                $builder
                    ->where('recharge.out_trade_no', 'like', '%' . $keyword . '%')
                    ->orWhere('recharge.type', 'like', '%' . $keyword . '%')
                    ->orWhere('merchant.username', 'like', '%' . $keyword . '%')
                    ->orWhere('merchant.email', 'like', '%' . $keyword . '%')
                    ->orWhere('merchant.mobile', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder
                        ->orWhere('recharge.id', (int)$keyword)
                        ->orWhere('recharge.user_id', (int)$keyword);
                }
            });
        }

        $status = $request->get('status');
        if ($status !== null && $status !== '') {
            $query->where('recharge.status', (int)$status);
        }

        $type = trim((string)$request->get('type', ''));
        if ($type !== '') {
            $query->where('recharge.type', $type);
        }

        $rtype = $request->get('rtype');
        if ($rtype !== null && $rtype !== '') {
            $query->where('recharge.rtype', (int)$rtype);
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', ''));
        $endDate = $this->normalizeDate((string)$request->get('end_date', ''));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('recharge.create_time', '>=', $startDate . ' 00:00:00')
                ->where(
                    'recharge.create_time',
                    '<',
                    date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day'))
                );
        }
    }

    private function rechargeQuery(): Builder
    {
        return $this->baseRechargeQuery()
            ->select(
                'recharge.id',
                'recharge.type',
                'recharge.rtype',
                'recharge.out_trade_no',
                'recharge.user_id',
                'recharge.money',
                'recharge.qrcode',
                'recharge.status',
                'recharge.regdata',
                'recharge.create_time',
                'recharge.end_time',
                'recharge.update_time',
                'recharge.out_time',
                'merchant.username as merchant_username',
                'merchant.name as merchant_name',
                'merchant.email as merchant_email',
                'merchant.mobile as merchant_mobile'
            );
    }

    private function baseRechargeQuery(): Builder
    {
        return Db::table(BusinessTable::recharge('recharge'))
            ->leftJoin(BusinessTable::user('merchant'), 'recharge.user_id', '=', 'merchant.id');
    }

    private function summary(Builder $query): array
    {
        $now = time();
        $row = (array)($query
            ->selectRaw('COUNT(recharge.id) as total_count')
            ->selectRaw('SUM(CASE WHEN recharge.status = 1 THEN 1 ELSE 0 END) as paid_count')
            ->selectRaw('SUM(CASE WHEN recharge.status = 0 THEN 1 ELSE 0 END) as pending_count')
            ->selectRaw('SUM(CASE WHEN recharge.status NOT IN (0, 1) THEN 1 ELSE 0 END) as unknown_status_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN recharge.user_id > 0 THEN recharge.user_id END) as merchant_count')
            ->selectRaw('SUM(CASE WHEN recharge.rtype = 0 THEN 1 ELSE 0 END) as merchant_recharge_count')
            ->selectRaw('SUM(CASE WHEN recharge.rtype = 1 THEN 1 ELSE 0 END) as registration_count')
            ->selectRaw(
                'SUM(CASE WHEN recharge.status = 0 AND recharge.out_time IS NOT NULL AND recharge.out_time > 0 AND recharge.out_time <= ? THEN 1 ELSE 0 END) as expired_pending_count',
                [$now]
            )
            ->selectRaw('COALESCE(SUM(recharge.money), 0) as gross_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN recharge.status = 1 THEN recharge.money ELSE 0 END), 0) as paid_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN recharge.status = 0 THEN recharge.money ELSE 0 END), 0) as pending_amount')
            ->first() ?? []);

        $totalCount = (int)($row['total_count'] ?? 0);
        $paidCount = (int)($row['paid_count'] ?? 0);

        return [
            'total_count' => $totalCount,
            'paid_count' => $paidCount,
            'pending_count' => (int)($row['pending_count'] ?? 0),
            'unknown_status_count' => (int)($row['unknown_status_count'] ?? 0),
            'merchant_count' => (int)($row['merchant_count'] ?? 0),
            'merchant_recharge_count' => (int)($row['merchant_recharge_count'] ?? 0),
            'registration_count' => (int)($row['registration_count'] ?? 0),
            'expired_pending_count' => (int)($row['expired_pending_count'] ?? 0),
            'gross_amount' => AdminRechargeFormatter::toFloat($row['gross_amount'] ?? 0),
            'paid_amount' => AdminRechargeFormatter::toFloat($row['paid_amount'] ?? 0),
            'pending_amount' => AdminRechargeFormatter::toFloat($row['pending_amount'] ?? 0),
            'success_rate' => $totalCount > 0 ? round(($paidCount / $totalCount) * 100, 2) : 0.0,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function rechargeIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function rechargeRecord(int $id): ?array
    {
        $row = $this->rechargeQuery()
            ->where('recharge.id', $id)
            ->first();

        return $row ? (array)$row : null;
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return null;
        }

        return substr($value, 0, 10);
    }
}
