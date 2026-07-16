<?php

namespace app\controller;

use app\support\AdminOrderFormatter;
use app\support\ApiResponse;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class DashboardController
{
    public function overview(Request $request): Response
    {
        $todayStart = date('Y-m-d 00:00:00');
        $tomorrowStart = date('Y-m-d 00:00:00', strtotime('+1 day'));

        $todayQuery = $this->windowOrderQuery($todayStart, $tomorrowStart);
        $allOrdersQuery = Db::table('ypay_order');
        $paidOrdersQuery = Db::table('ypay_order')->where('status', 1);

        $todayOrderCount = (int)(clone $todayQuery)->count();
        $todayPaidOrderCount = (int)(clone $todayQuery)->where('status', 1)->count();
        $todayPaidAmount = $this->sumSettledAmount((clone $todayQuery)->where('status', 1));
        $todayFeeAmount = $this->sumFeeAmount((clone $todayQuery)->where('status', 1));

        $totalOrderCount = (int)(clone $allOrdersQuery)->count();
        $totalPaidOrderCount = (int)(clone $paidOrdersQuery)->count();
        $totalPaidAmount = $this->sumSettledAmount(clone $paidOrdersQuery);
        $totalFeeAmount = $this->sumFeeAmount(clone $paidOrdersQuery);

        $pendingOrderCount = (int)Db::table('ypay_order')->where('status', 0)->count();
        $merchantCount = (int)Db::table('ypay_user')->count();
        $successRate = $totalOrderCount > 0
            ? round(($totalPaidOrderCount / $totalOrderCount) * 100, 2)
            : 0.0;

        return ApiResponse::success([
            'summary' => [
                'today_order_count' => $todayOrderCount,
                'today_paid_order_count' => $todayPaidOrderCount,
                'today_paid_amount' => $todayPaidAmount,
                'today_fee_amount' => $todayFeeAmount,
                'total_order_count' => $totalOrderCount,
                'total_paid_order_count' => $totalPaidOrderCount,
                'total_paid_amount' => $totalPaidAmount,
                'total_fee_amount' => $totalFeeAmount,
                'pending_order_count' => $pendingOrderCount,
                'merchant_count' => $merchantCount,
                'success_rate' => $successRate,
            ],
            'trend' => $this->trend(),
            'payment_distribution' => $this->paymentDistribution($totalOrderCount),
            'recent_orders' => $this->recentOrders(),
            'generated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function windowOrderQuery(string $start, string $end)
    {
        return Db::table('ypay_order')
            ->where('create_time', '>=', $start)
            ->where('create_time', '<', $end);
    }

    private function sumSettledAmount($query): float
    {
        $row = (array)((clone $query)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'usdt' THEN money ELSE truemoney END), 0) as amount")
            ->first() ?? []);

        return AdminOrderFormatter::toFloat($row['amount'] ?? 0);
    }

    private function sumFeeAmount($query): float
    {
        $row = (array)((clone $query)
            ->selectRaw('COALESCE(SUM(feilvmoney), 0) as amount')
            ->first() ?? []);

        return AdminOrderFormatter::toFloat($row['amount'] ?? 0, 3);
    }

    private function trend(): array
    {
        $trendEnd = new \DateTimeImmutable('tomorrow');
        $trendStart = $trendEnd->modify('-7 days');
        $start = $trendStart->format('Y-m-d 00:00:00');
        $end = $trendEnd->format('Y-m-d 00:00:00');

        $rows = Db::table('ypay_order')
            ->selectRaw("DATE_FORMAT(create_time, '%Y-%m-%d') as date_key")
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as paid_order_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 1 THEN CASE WHEN type = 'usdt' THEN money ELSE truemoney END ELSE 0 END), 0) as paid_amount")
            ->where('create_time', '>=', $start)
            ->where('create_time', '<', $end)
            ->groupByRaw("DATE_FORMAT(create_time, '%Y-%m-%d')")
            ->orderBy('date_key')
            ->get()
            ->toArray();

        $indexed = [];
        foreach ($rows as $row) {
            $item = (array)$row;
            $indexed[(string)($item['date_key'] ?? '')] = $item;
        }

        $labels = [];
        $orderCounts = [];
        $paidOrderCounts = [];
        $paidAmounts = [];

        for ($cursor = $trendStart; $cursor < $trendEnd; $cursor = $cursor->modify('+1 day')) {
            $dateKey = $cursor->format('Y-m-d');
            $labels[] = $cursor->format('m-d');
            $dayData = $indexed[$dateKey] ?? null;
            $orderCounts[] = (int)($dayData['order_count'] ?? 0);
            $paidOrderCounts[] = (int)($dayData['paid_order_count'] ?? 0);
            $paidAmounts[] = AdminOrderFormatter::toFloat($dayData['paid_amount'] ?? 0);
        }

        return [
            'labels' => $labels,
            'order_counts' => $orderCounts,
            'paid_order_counts' => $paidOrderCounts,
            'paid_amounts' => $paidAmounts,
        ];
    }

    private function paymentDistribution(int $totalOrderCount): array
    {
        $rows = Db::table('ypay_order')
            ->select('type')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as paid_order_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 1 THEN CASE WHEN type = 'usdt' THEN money ELSE truemoney END ELSE 0 END), 0) as paid_amount")
            ->groupBy('type')
            ->orderByDesc('paid_amount')
            ->orderByDesc('order_count')
            ->limit(8)
            ->get()
            ->toArray();

        return array_map(
            static function ($row) use ($totalOrderCount): array {
                $item = (array)$row;
                $type = trim((string)($item['type'] ?? ''));
                $orderCount = (int)($item['order_count'] ?? 0);

                return [
                    'type' => $type,
                    'label' => AdminOrderFormatter::paymentTypeLabel($type),
                    'order_count' => $orderCount,
                    'paid_order_count' => (int)($item['paid_order_count'] ?? 0),
                    'paid_amount' => AdminOrderFormatter::toFloat($item['paid_amount'] ?? 0),
                    'share' => $totalOrderCount > 0 ? round(($orderCount / $totalOrderCount) * 100, 2) : 0.0,
                ];
            },
            $rows
        );
    }

    private function recentOrders(): array
    {
        $rows = Db::table('ypay_order')
            ->leftJoin('ypay_user', 'ypay_order.user_id', '=', 'ypay_user.id')
            ->leftJoin('ypay_paylist', 'ypay_order.account_id', '=', 'ypay_paylist.id')
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
            )
            ->orderByDesc('ypay_order.id')
            ->limit(8)
            ->get()
            ->toArray();

        return array_map(
            static fn ($row): array => AdminOrderFormatter::formatOrder((array)$row),
            $rows
        );
    }
}
