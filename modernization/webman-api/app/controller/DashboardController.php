<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\controller;

use app\support\AdminOrderFormatter;
use app\support\ApiResponse;
use app\support\BusinessTable;
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
        $allOrdersQuery = Db::table(BusinessTable::order());
        $paidOrdersQuery = Db::table(BusinessTable::order())->where('status', 1);

        $todayOrderCount = (int)(clone $todayQuery)->count();
        $todayPaidOrderCount = (int)(clone $todayQuery)->where('status', 1)->count();
        $todayPaidAmount = $this->sumSettledAmount((clone $todayQuery)->where('status', 1));
        $todayFeeAmount = $this->sumFeeAmount((clone $todayQuery)->where('status', 1));

        $totalOrderCount = (int)(clone $allOrdersQuery)->count();
        $totalPaidOrderCount = (int)(clone $paidOrdersQuery)->count();
        $totalPaidAmount = $this->sumSettledAmount(clone $paidOrdersQuery);
        $totalFeeAmount = $this->sumFeeAmount(clone $paidOrdersQuery);

        $pendingOrderCount = (int)Db::table(BusinessTable::order())->where('status', 0)->count();
        $merchantCount = (int)Db::table(BusinessTable::user())->count();
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
            'duty_board' => $this->dutyBoard(),
            'trend' => $this->trend(),
            'payment_distribution' => $this->paymentDistribution($totalOrderCount),
            'recent_orders' => $this->recentOrders(),
            'generated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function windowOrderQuery(string $start, string $end)
    {
        return Db::table(BusinessTable::order())
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

        $rows = Db::table(BusinessTable::order())
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
        $rows = Db::table(BusinessTable::order())
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

    private function dutyBoard(): array
    {
        $pendingRechargeQuery = Db::table(BusinessTable::recharge())->where('status', 0);
        $pendingRechargeCount = (int)(clone $pendingRechargeQuery)->count();
        $pendingRechargeRow = (array)((clone $pendingRechargeQuery)
            ->selectRaw('COALESCE(SUM(money), 0) as amount')
            ->first() ?? []);

        $newTicketCount = (int)Db::table(BusinessTable::ticket())->where('status', 0)->count();
        $processingTicketCount = (int)Db::table(BusinessTable::ticket())->where('status', 1)->count();
        $onlineAccountCount = (int)Db::table(BusinessTable::account())->where('status', 1)->count();
        $enabledAccountCount = (int)Db::table(BusinessTable::account())->where('is_status', 1)->count();

        return [
            'pending_recharge_count' => $pendingRechargeCount,
            'pending_recharge_amount' => AdminOrderFormatter::toFloat($pendingRechargeRow['amount'] ?? 0),
            'new_ticket_count' => $newTicketCount,
            'processing_ticket_count' => $processingTicketCount,
            'pending_ticket_count' => $newTicketCount + $processingTicketCount,
            'online_account_count' => $onlineAccountCount,
            'enabled_account_count' => $enabledAccountCount,
        ];
    }

    private function recentOrders(): array
    {
        $rows = Db::table(BusinessTable::order('orders'))
            ->leftJoin(BusinessTable::user('merchant'), 'orders.user_id', '=', 'merchant.id')
            ->leftJoin(BusinessTable::paylist('paylist'), 'orders.account_id', '=', 'paylist.id')
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
            )
            ->orderByDesc('orders.id')
            ->limit(8)
            ->get()
            ->toArray();

        return array_map(
            static fn ($row): array => AdminOrderFormatter::formatOrder((array)$row),
            $rows
        );
    }
}
