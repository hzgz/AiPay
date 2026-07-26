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

class CommerceOverviewController
{
    private const COMPARISON_TYPES = [
        ['key' => 'wxpay', 'label' => '微信'],
        ['key' => 'alipay', 'label' => '支付宝'],
        ['key' => 'qqpay', 'label' => 'QQ钱包'],
    ];

    public function overview(Request $request): Response
    {
        unset($request);

        return ApiResponse::success([
            'summary' => $this->summary(),
            'periods' => [
                $this->periodItem('day', '今日'),
                $this->periodItem('month', '本月'),
                $this->periodItem('year', '本年'),
            ],
            'order_trend' => $this->orderTrend(),
            'collection_comparison' => $this->collectionComparison(),
            'recharge_comparison' => $this->rechargeComparison(),
            'readonly_note' => '当前页面展示经营统计数据与关键指标，不处理加款、清理、卡券或工单操作。',
            'generated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function summary(): array
    {
        [$todayStart, $tomorrowStart] = $this->currentRange('day');
        [$yesterdayStart, $todayStartAgain] = $this->previousDayRange();
        [$lastWeekStart, $lastWeekEnd] = $this->previousWeekRange();
        [$lastMonthStart, $lastMonthEnd] = $this->previousMonthRange();

        $totalPaidOrderCount = (int)Db::table(BusinessTable::order())->where('status', 1)->count();
        $totalPaidRechargeCount = (int)Db::table(BusinessTable::recharge())->where('status', 1)->count();

        return [
            'total_user_count' => (int)Db::table(BusinessTable::user())->count(),
            'total_paid_order_count' => $totalPaidOrderCount,
            'total_paid_recharge_count' => $totalPaidRechargeCount,
            'total_paid_trade_count' => $totalPaidOrderCount + $totalPaidRechargeCount,
            'total_balance_pool' => $this->sumColumn(Db::table(BusinessTable::user()), 'money'),
            'total_online_account_count' => (int)Db::table(BusinessTable::account())->where('status', 1)->count(),
            'today_new_user_count' => (int)$this->betweenQuery(BusinessTable::user(), $todayStart, $tomorrowStart)->count(),
            'today_paid_recharge_count' => (int)$this->betweenQuery(BusinessTable::recharge(), $todayStart, $tomorrowStart)
                ->where('status', 1)
                ->count(),
            'yesterday_paid_order_count' => (int)$this->betweenQuery(BusinessTable::order(), $yesterdayStart, $todayStartAgain)
                ->where('status', 1)
                ->count(),
            'yesterday_paid_amount' => $this->sumSettledAmount(
                $this->betweenQuery(BusinessTable::order(), $yesterdayStart, $todayStartAgain)->where('status', 1)
            ),
            'last_week_paid_amount' => $this->sumSettledAmount(
                $this->betweenQuery(BusinessTable::order(), $lastWeekStart, $lastWeekEnd)->where('status', 1)
            ),
            'last_month_paid_amount' => $this->sumSettledAmount(
                $this->betweenQuery(BusinessTable::order(), $lastMonthStart, $lastMonthEnd)->where('status', 1)
            ),
            'qq_online_account_count' => (int)Db::table(BusinessTable::account())->where('status', 1)->where('type', 'qqpay')->count(),
            'wx_online_account_count' => (int)Db::table(BusinessTable::account())->where('status', 1)->where('type', 'wxpay')->count(),
            'alipay_online_account_count' => (int)Db::table(BusinessTable::account())->where('status', 1)->where('type', 'alipay')->count(),
        ];
    }

    private function periodItem(string $key, string $label): array
    {
        [$start, $end] = $this->currentRange($key);
        $query = $this->betweenQuery(BusinessTable::order(), $start, $end);
        $paidOrderCount = (int)(clone $query)->where('status', 1)->count();
        $totalOrderCount = (int)(clone $query)->count();
        $unpaidOrderCount = (int)(clone $query)->where('status', 0)->count();

        return [
            'key' => $key,
            'label' => $label,
            'paid_order_count' => $paidOrderCount,
            'total_order_count' => $totalOrderCount,
            'unpaid_order_count' => $unpaidOrderCount,
            'success_rate' => $totalOrderCount > 0 ? round(($paidOrderCount / $totalOrderCount) * 100, 2) : 0.0,
            'paid_amount' => $this->sumSettledAmount((clone $query)->where('status', 1)),
            'total_amount' => $this->sumSettledAmount(clone $query),
        ];
    }

    private function orderTrend(): array
    {
        $trendEnd = new \DateTimeImmutable('today');
        $trendStart = $trendEnd->modify('-30 days');
        $start = $trendStart->format('Y-m-d 00:00:00');
        $end = $trendEnd->format('Y-m-d 00:00:00');

        $rows = Db::table(BusinessTable::order())
            ->selectRaw("DATE_FORMAT(create_time, '%Y-%m-%d') as date_key")
            ->selectRaw('COUNT(*) as total_order_count')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as paid_order_count')
            ->selectRaw('SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as unpaid_order_count')
            ->where('create_time', '>=', $start)
            ->where('create_time', '<', $end)
            ->groupByRaw("DATE_FORMAT(create_time, '%Y-%m-%d')")
            ->orderBy('date_key')
            ->get()
            ->toArray();

        $indexed = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $indexed[(string)($record['date_key'] ?? '')] = $record;
        }

        $labels = [];
        $fullLabels = [];
        $totalOrderCounts = [];
        $paidOrderCounts = [];
        $unpaidOrderCounts = [];

        for ($cursor = $trendStart; $cursor < $trendEnd; $cursor = $cursor->modify('+1 day')) {
            $dateKey = $cursor->format('Y-m-d');
            $labels[] = $cursor->format('m-d');
            $fullLabels[] = $dateKey;
            $record = $indexed[$dateKey] ?? [];
            $totalOrderCounts[] = (int)($record['total_order_count'] ?? 0);
            $paidOrderCounts[] = (int)($record['paid_order_count'] ?? 0);
            $unpaidOrderCounts[] = (int)($record['unpaid_order_count'] ?? 0);
        }

        return [
            'labels' => $labels,
            'full_labels' => $fullLabels,
            'total_order_counts' => $totalOrderCounts,
            'paid_order_counts' => $paidOrderCounts,
            'unpaid_order_counts' => $unpaidOrderCounts,
        ];
    }

    private function collectionComparison(): array
    {
        return $this->amountComparison(BusinessTable::order(), false);
    }

    private function rechargeComparison(): array
    {
        return $this->amountComparison(BusinessTable::recharge(), true);
    }

    private function amountComparison(string $table, bool $recharge): array
    {
        $ranges = [
            ['label' => '本月', 'range' => $this->currentRange('month')],
            ['label' => '本周', 'range' => $this->currentRange('week')],
            ['label' => '今日', 'range' => $this->currentRange('day')],
        ];

        $series = [];
        foreach (self::COMPARISON_TYPES as $typeMeta) {
            $data = [];
            foreach ($ranges as $rangeMeta) {
                [$start, $end] = $rangeMeta['range'];
                $query = $this->betweenQuery($table, $start, $end)
                    ->where('status', 1)
                    ->where('type', $typeMeta['key']);

                $data[] = $recharge
                    ? $this->sumColumn(clone $query, 'money')
                    : $this->sumSettledAmount(clone $query);
            }

            $series[] = [
                'key' => $typeMeta['key'],
                'label' => $typeMeta['label'],
                'data' => $data,
            ];
        }

        return [
            'labels' => array_map(static fn (array $item): string => $item['label'], $ranges),
            'series' => $series,
        ];
    }

    private function betweenQuery(string $table, string $start, string $end)
    {
        return Db::table($table)
            ->where('create_time', '>=', $start)
            ->where('create_time', '<', $end);
    }

    private function currentRange(string $preset): array
    {
        $today = new \DateTimeImmutable('today');

        return match ($preset) {
            'day' => [
                $today->format('Y-m-d 00:00:00'),
                $today->modify('+1 day')->format('Y-m-d 00:00:00'),
            ],
            'week' => [
                $today->modify('monday this week')->format('Y-m-d 00:00:00'),
                $today->modify('monday this week')->modify('+7 days')->format('Y-m-d 00:00:00'),
            ],
            'month' => [
                $today->modify('first day of this month')->format('Y-m-d 00:00:00'),
                $today->modify('first day of next month')->format('Y-m-d 00:00:00'),
            ],
            'year' => [
                $today->setDate((int)$today->format('Y'), 1, 1)->format('Y-m-d 00:00:00'),
                $today->setDate((int)$today->format('Y') + 1, 1, 1)->format('Y-m-d 00:00:00'),
            ],
            default => [
                $today->format('Y-m-d 00:00:00'),
                $today->modify('+1 day')->format('Y-m-d 00:00:00'),
            ],
        };
    }

    private function previousDayRange(): array
    {
        $today = new \DateTimeImmutable('today');
        $yesterday = $today->modify('-1 day');

        return [
            $yesterday->format('Y-m-d 00:00:00'),
            $today->format('Y-m-d 00:00:00'),
        ];
    }

    private function previousWeekRange(): array
    {
        $currentWeekStart = (new \DateTimeImmutable('today'))->modify('monday this week');
        $lastWeekStart = $currentWeekStart->modify('-7 days');

        return [
            $lastWeekStart->format('Y-m-d 00:00:00'),
            $currentWeekStart->format('Y-m-d 00:00:00'),
        ];
    }

    private function previousMonthRange(): array
    {
        $currentMonthStart = (new \DateTimeImmutable('today'))->modify('first day of this month');
        $lastMonthStart = $currentMonthStart->modify('-1 month');

        return [
            $lastMonthStart->format('Y-m-d 00:00:00'),
            $currentMonthStart->format('Y-m-d 00:00:00'),
        ];
    }

    private function sumSettledAmount($query): float
    {
        $row = (array)((clone $query)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'usdt' THEN money ELSE truemoney END), 0) as amount")
            ->first() ?? []);

        return AdminOrderFormatter::toFloat($row['amount'] ?? 0);
    }

    private function sumColumn($query, string $column): float
    {
        $row = (array)((clone $query)
            ->selectRaw(sprintf('COALESCE(SUM(%s), 0) as amount', $column))
            ->first() ?? []);

        return AdminOrderFormatter::toFloat($row['amount'] ?? 0);
    }
}
