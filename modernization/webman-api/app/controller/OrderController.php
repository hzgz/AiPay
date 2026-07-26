<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\controller;

use app\controller\concerns\AdminControllerFormatSupport;
use app\support\AdminOrderFormatter;
use app\support\ApiResponse;
use app\support\BusinessTable;
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

        $total = (int)(clone $query)->count('orders.id');
        $rows = $query
            ->orderByDesc('orders.id')
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
            ->where('orders.id', $id)
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
                'orders.id',
                'orders.name',
                'orders.sitename',
                'orders.trade_no',
                'orders.out_trade_no',
                'orders.alipay_order_no',
                'orders.user_id',
                'orders.account_id',
                'orders.type',
                'orders.pay_type',
                'orders.money',
                'orders.truemoney',
                'orders.feilvmoney',
                'orders.status',
                'orders.notify_url',
                'orders.return_url',
                'orders.ip',
                'orders.create_time',
                'orders.end_time',
                'orders.api_memo',
                'merchant.username as merchant_username',
                'paylist.type as paylist_type',
                'paylist.name as paylist_name'
            );
    }

    private function baseOrderQuery(): Builder
    {
        return Db::table(BusinessTable::order('orders'))
            ->leftJoin(BusinessTable::user('merchant'), 'orders.user_id', '=', 'merchant.id')
            ->leftJoin(BusinessTable::paylist('paylist'), 'orders.account_id', '=', 'paylist.id');
    }

    private function summary(Builder $query): array
    {
        $row = (array)($query
            ->selectRaw('COUNT(orders.id) as total_count')
            ->selectRaw('SUM(CASE WHEN orders.status = 1 THEN 1 ELSE 0 END) as paid_count')
            ->selectRaw('SUM(CASE WHEN orders.status = 0 THEN 1 ELSE 0 END) as pending_count')
            ->selectRaw('SUM(CASE WHEN orders.status NOT IN (0, 1) THEN 1 ELSE 0 END) as unknown_status_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN orders.user_id > 0 THEN orders.user_id END) as merchant_count')
            ->selectRaw('COALESCE(SUM(orders.money), 0) as gross_amount')
            ->selectRaw("COALESCE(SUM(CASE WHEN orders.status = 1 THEN CASE WHEN orders.type = 'usdt' THEN orders.money ELSE orders.truemoney END ELSE 0 END), 0) as paid_amount")
            ->selectRaw('COALESCE(SUM(CASE WHEN orders.status = 0 THEN orders.money ELSE 0 END), 0) as pending_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN orders.status = 1 THEN orders.feilvmoney ELSE 0 END), 0) as fee_amount')
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
                    ->where('orders.trade_no', 'like', '%' . $keyword . '%')
                    ->orWhere('orders.out_trade_no', 'like', '%' . $keyword . '%')
                    ->orWhere('orders.name', 'like', '%' . $keyword . '%')
                    ->orWhere('orders.sitename', 'like', '%' . $keyword . '%')
                    ->orWhere('merchant.username', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder
                        ->orWhere('orders.user_id', (int)$keyword)
                        ->orWhere('orders.account_id', (int)$keyword)
                        ->orWhere('orders.id', (int)$keyword);
                }
            });
        }

        $status = $request->get('status');
        if ($status !== null && $status !== '') {
            $query->where('orders.status', (int)$status);
        }

        $type = trim((string)$request->get('type', ''));
        if ($type !== '') {
            $query->where('orders.type', $type);
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', ''));
        $endDate = $this->normalizeDate((string)$request->get('end_date', ''));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('orders.create_time', '>=', $startDate . ' 00:00:00')
                ->where('orders.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
        }
    }

    private function orderIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

}
