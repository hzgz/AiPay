<?php

namespace app\controller;

use app\controller\concerns\AdminControllerFormatSupport;
use app\support\AdminOrderFormatter;
use app\support\ApiResponse;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class OrderController
{
    use AdminControllerFormatSupport;

    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $query = $this->orderQuery();
        $this->applyFilters($query, $request);

        $summaryQuery = $this->baseOrderQuery();
        $this->applyFilters($summaryQuery, $request);

        $total = (int)(clone $query)->count('ypay_order.id');
        $rows = $query
            ->orderByDesc('ypay_order.id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        $records = array_map(
            static fn ($row): array => AdminOrderFormatter::formatOrder((array)$row),
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
        $id = $this->orderIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('order id is required', 422, null, 422);
        }

        $row = $this->orderQuery()
            ->where('ypay_order.id', $id)
            ->first();

        if (!$row) {
            return ApiResponse::error('order not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminOrderFormatter::formatOrder((array)$row),
        ]);
    }

    private function orderQuery(): Builder
    {
        return $this->baseOrderQuery()
            ->select(
                'ypay_order.id',
                'ypay_order.name',
                'ypay_order.sitename',
                'ypay_order.trade_no',
                'ypay_order.out_trade_no',
                'ypay_order.alipay_order_no',
                'ypay_order.user_id',
                'ypay_order.account_id',
                'ypay_order.type',
                'ypay_order.pay_type',
                'ypay_order.money',
                'ypay_order.truemoney',
                'ypay_order.feilvmoney',
                'ypay_order.status',
                'ypay_order.notify_url',
                'ypay_order.return_url',
                'ypay_order.ip',
                'ypay_order.create_time',
                'ypay_order.end_time',
                'ypay_order.api_memo',
                'ypay_user.username as merchant_username',
                'ypay_paylist.type as paylist_type',
                'ypay_paylist.name as paylist_name'
            );
    }

    private function baseOrderQuery(): Builder
    {
        return Db::table('ypay_order')
            ->leftJoin('ypay_user', 'ypay_order.user_id', '=', 'ypay_user.id')
            ->leftJoin('ypay_paylist', 'ypay_order.account_id', '=', 'ypay_paylist.id');
    }

    private function summary(Builder $query): array
    {
        $row = (array)($query
            ->selectRaw('COUNT(ypay_order.id) as total_count')
            ->selectRaw('SUM(CASE WHEN ypay_order.status = 1 THEN 1 ELSE 0 END) as paid_count')
            ->selectRaw('SUM(CASE WHEN ypay_order.status = 0 THEN 1 ELSE 0 END) as pending_count')
            ->selectRaw('SUM(CASE WHEN ypay_order.status NOT IN (0, 1) THEN 1 ELSE 0 END) as unknown_status_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN ypay_order.user_id > 0 THEN ypay_order.user_id END) as merchant_count')
            ->selectRaw('COALESCE(SUM(ypay_order.money), 0) as gross_amount')
            ->selectRaw("COALESCE(SUM(CASE WHEN ypay_order.status = 1 THEN CASE WHEN ypay_order.type = 'usdt' THEN ypay_order.money ELSE ypay_order.truemoney END ELSE 0 END), 0) as paid_amount")
            ->selectRaw('COALESCE(SUM(CASE WHEN ypay_order.status = 0 THEN ypay_order.money ELSE 0 END), 0) as pending_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN ypay_order.status = 1 THEN ypay_order.feilvmoney ELSE 0 END), 0) as fee_amount')
            ->first() ?? []);

        $totalCount = (int)($row['total_count'] ?? 0);
        $paidCount = (int)($row['paid_count'] ?? 0);

        return [
            'total_count' => $totalCount,
            'paid_count' => $paidCount,
            'pending_count' => (int)($row['pending_count'] ?? 0),
            'unknown_status_count' => (int)($row['unknown_status_count'] ?? 0),
            'merchant_count' => (int)($row['merchant_count'] ?? 0),
            'gross_amount' => AdminOrderFormatter::toFloat($row['gross_amount'] ?? 0),
            'paid_amount' => AdminOrderFormatter::toFloat($row['paid_amount'] ?? 0),
            'pending_amount' => AdminOrderFormatter::toFloat($row['pending_amount'] ?? 0),
            'fee_amount' => AdminOrderFormatter::toFloat($row['fee_amount'] ?? 0, 3),
            'success_rate' => $totalCount > 0 ? round(($paidCount / $totalCount) * 100, 2) : 0.0,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder
                    ->where('ypay_order.trade_no', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_order.out_trade_no', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_order.name', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_order.sitename', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_user.username', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder
                        ->orWhere('ypay_order.user_id', (int)$keyword)
                        ->orWhere('ypay_order.account_id', (int)$keyword)
                        ->orWhere('ypay_order.id', (int)$keyword);
                }
            });
        }

        $status = $request->get('status');
        if ($status !== null && $status !== '') {
            $query->where('ypay_order.status', (int)$status);
        }

        $type = trim((string)$request->get('type', ''));
        if ($type !== '') {
            $query->where('ypay_order.type', $type);
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', ''));
        $endDate = $this->normalizeDate((string)$request->get('end_date', ''));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('ypay_order.create_time', '>=', $startDate . ' 00:00:00')
                ->where('ypay_order.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
        }
    }

    private function orderIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

}
