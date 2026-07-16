<?php

namespace app\controller;

use app\support\AdminRechargeFormatter;
use app\support\ApiResponse;
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

        $total = (int)(clone $query)->count('ypay_recharge.id');
        $rows = $query
            ->orderByDesc('ypay_recharge.id')
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
                    ->where('ypay_recharge.out_trade_no', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_recharge.type', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_user.username', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_user.email', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_user.mobile', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder
                        ->orWhere('ypay_recharge.id', (int)$keyword)
                        ->orWhere('ypay_recharge.user_id', (int)$keyword);
                }
            });
        }

        $status = $request->get('status');
        if ($status !== null && $status !== '') {
            $query->where('ypay_recharge.status', (int)$status);
        }

        $type = trim((string)$request->get('type', ''));
        if ($type !== '') {
            $query->where('ypay_recharge.type', $type);
        }

        $rtype = $request->get('rtype');
        if ($rtype !== null && $rtype !== '') {
            $query->where('ypay_recharge.rtype', (int)$rtype);
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', ''));
        $endDate = $this->normalizeDate((string)$request->get('end_date', ''));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('ypay_recharge.create_time', '>=', $startDate . ' 00:00:00')
                ->where(
                    'ypay_recharge.create_time',
                    '<',
                    date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day'))
                );
        }
    }

    private function rechargeQuery(): Builder
    {
        return $this->baseRechargeQuery()
            ->select(
                'ypay_recharge.id',
                'ypay_recharge.type',
                'ypay_recharge.rtype',
                'ypay_recharge.out_trade_no',
                'ypay_recharge.user_id',
                'ypay_recharge.money',
                'ypay_recharge.qrcode',
                'ypay_recharge.status',
                'ypay_recharge.regdata',
                'ypay_recharge.create_time',
                'ypay_recharge.end_time',
                'ypay_recharge.update_time',
                'ypay_recharge.out_time',
                'ypay_user.username as merchant_username',
                'ypay_user.name as merchant_name',
                'ypay_user.email as merchant_email',
                'ypay_user.mobile as merchant_mobile'
            );
    }

    private function baseRechargeQuery(): Builder
    {
        return Db::table('ypay_recharge')
            ->leftJoin('ypay_user', 'ypay_recharge.user_id', '=', 'ypay_user.id');
    }

    private function summary(Builder $query): array
    {
        $now = time();
        $row = (array)($query
            ->selectRaw('COUNT(ypay_recharge.id) as total_count')
            ->selectRaw('SUM(CASE WHEN ypay_recharge.status = 1 THEN 1 ELSE 0 END) as paid_count')
            ->selectRaw('SUM(CASE WHEN ypay_recharge.status = 0 THEN 1 ELSE 0 END) as pending_count')
            ->selectRaw('SUM(CASE WHEN ypay_recharge.status NOT IN (0, 1) THEN 1 ELSE 0 END) as unknown_status_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN ypay_recharge.user_id > 0 THEN ypay_recharge.user_id END) as merchant_count')
            ->selectRaw('SUM(CASE WHEN ypay_recharge.rtype = 0 THEN 1 ELSE 0 END) as merchant_recharge_count')
            ->selectRaw('SUM(CASE WHEN ypay_recharge.rtype = 1 THEN 1 ELSE 0 END) as registration_count')
            ->selectRaw(
                'SUM(CASE WHEN ypay_recharge.status = 0 AND ypay_recharge.out_time IS NOT NULL AND ypay_recharge.out_time > 0 AND ypay_recharge.out_time <= ? THEN 1 ELSE 0 END) as expired_pending_count',
                [$now]
            )
            ->selectRaw('COALESCE(SUM(ypay_recharge.money), 0) as gross_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN ypay_recharge.status = 1 THEN ypay_recharge.money ELSE 0 END), 0) as paid_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN ypay_recharge.status = 0 THEN ypay_recharge.money ELSE 0 END), 0) as pending_amount')
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
            ->where('ypay_recharge.id', $id)
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
