<?php

declare(strict_types=1);

namespace app\support;

use support\Db;

class MerchantPortalCancellationSupport
{
    public static function featureEnabled(): bool
    {
        return trim((string)SystemConfig::get('is_logOff', '0')) === '1';
    }

    public static function payload(array $merchant, ?array $audit = null): array
    {
        $featureEnabled = self::featureEnabled();
        $audit = $audit ?? self::audit($merchant);
        $canSubmit = $featureEnabled && (bool)($audit['can_delete'] ?? false);
        $summary = (array)($audit['summary'] ?? []);

        return [
            'feature_enabled' => $featureEnabled,
            'can_submit' => $canSubmit,
            'confirmation_phrase' => $featureEnabled ? (string)($audit['confirmation_phrase'] ?? '') : '',
            'merchant_username' => (string)($audit['merchant_username'] ?? ($merchant['username'] ?? '')),
            'balance_amount' => number_format((float)($merchant['money'] ?? 0), 2, '.', ''),
            'blocking_reasons' => array_values(array_map('strval', (array)($audit['blocking_reasons'] ?? []))),
            'warnings' => array_values(array_map('strval', (array)($audit['warnings'] ?? []))),
            'related_counts' => array_values((array)($audit['related_counts'] ?? [])),
            'summary' => [
                'delete_row_count' => (int)($summary['delete_row_count'] ?? 0),
                'non_empty_target_count' => (int)($summary['non_empty_target_count'] ?? 0),
                'blocking_reference_count' => (int)($summary['blocking_reference_count'] ?? 0),
                'balance_blocked' => (bool)($summary['balance_blocked'] ?? false),
                'pending_order_count' => (int)($summary['pending_order_count'] ?? 0),
                'pending_recharge_count' => (int)($summary['pending_recharge_count'] ?? 0),
                'subordinate_count' => (int)($summary['subordinate_count'] ?? 0),
            ],
            'write_message' => !$featureEnabled
                ? '系统未开启商户账户注销功能。'
                : ($canSubmit
                    ? '账号注销已开放，提交后会清理当前商户及其归属数据，并立即退出登录。'
                    : '当前账号暂不满足注销条件，请先处理拦截项后再提交。'),
        ];
    }

    public static function audit(array $merchant): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $subordinateCount = self::countRows('ypay_user', 'superior_id', $merchantId);
        $pendingOrderCount = self::pendingCount('ypay_order', 'user_id', $merchantId);
        $pendingRechargeCount = self::pendingCount('ypay_recharge', 'user_id', $merchantId);
        $balanceAmount = round((float)($merchant['money'] ?? 0), 3);
        $relatedCounts = [
            [
                'key' => 'subordinate_merchants',
                'label' => '下级商户',
                'table_name' => 'ypay_user',
                'column_name' => 'superior_id',
                'count' => $subordinateCount,
                'delete_action' => 'block',
                'help_text' => '存在下级商户时不允许自助注销，避免留下悬空的上下级关系。',
            ],
            [
                'key' => 'pending_orders',
                'label' => '未完成订单',
                'table_name' => 'ypay_order',
                'column_name' => 'user_id',
                'count' => $pendingOrderCount,
                'delete_action' => 'block',
                'help_text' => '请先处理未完成订单，避免支付回调或补单流程中断。',
            ],
            [
                'key' => 'pending_recharges',
                'label' => '未完成充值',
                'table_name' => 'ypay_recharge',
                'column_name' => 'user_id',
                'count' => $pendingRechargeCount,
                'delete_action' => 'block',
                'help_text' => '请先处理未完成充值记录，避免后续到账与对账异常。',
            ],
        ];

        $deleteRowCount = 0;
        $nonEmptyTargetCount = 0;
        foreach (self::targets() as $target) {
            $count = self::countRows($target['table'], $target['column'], $merchantId);
            if ($count > 0) {
                $deleteRowCount += $count;
                $nonEmptyTargetCount++;
            }

            $relatedCounts[] = [
                'key' => $target['key'],
                'label' => $target['label'],
                'table_name' => $target['table'],
                'column_name' => $target['column'],
                'count' => $count,
                'delete_action' => 'delete',
                'help_text' => $target['help_text'],
            ];
        }

        $blockingReasons = [];
        if ($balanceAmount > 0) {
            $blockingReasons[] = sprintf('当前账户仍有 %.2f 元余额，请先处理余额后再注销。', $balanceAmount);
        }
        if ($subordinateCount > 0) {
            $blockingReasons[] = sprintf('当前账户仍绑定 %d 个下级商户，请先调整上下级关系。', $subordinateCount);
        }
        if ($pendingOrderCount > 0) {
            $blockingReasons[] = sprintf('当前账户仍有 %d 笔未完成订单，请先处理。', $pendingOrderCount);
        }
        if ($pendingRechargeCount > 0) {
            $blockingReasons[] = sprintf('当前账户仍有 %d 笔未完成充值，请先处理。', $pendingRechargeCount);
        }

        return [
            'merchant_id' => $merchantId,
            'merchant_username' => trim((string)($merchant['username'] ?? '')),
            'confirmation_phrase' => self::confirmationPhrase($merchantId),
            'can_delete' => $blockingReasons === [],
            'blocking_reasons' => $blockingReasons,
            'related_counts' => $relatedCounts,
            'summary' => [
                'delete_row_count' => $deleteRowCount,
                'non_empty_target_count' => $nonEmptyTargetCount,
                'blocking_reference_count' => $subordinateCount + $pendingOrderCount + $pendingRechargeCount,
                'balance_blocked' => $balanceAmount > 0,
                'pending_order_count' => $pendingOrderCount,
                'pending_recharge_count' => $pendingRechargeCount,
                'subordinate_count' => $subordinateCount,
            ],
            'warnings' => [
                '账号注销不可恢复，将同步清理当前商户归属的通道、订单、日志、工单等数据。',
                '请务必先确认余额、下级关系和未完成交易都已处理完毕。',
            ],
        ];
    }

    public static function deleteOwnedRows(int $merchantId): void
    {
        foreach (self::targets() as $target) {
            if (!self::targetAvailable($target['table'], $target['column'])) {
                continue;
            }

            Db::table($target['table'])
                ->where($target['column'], $merchantId)
                ->delete();
        }
    }

    private static function countRows(string $table, string $column, int $merchantId): int
    {
        if (!self::targetAvailable($table, $column)) {
            return 0;
        }

        return (int)Db::table($table)->where($column, $merchantId)->count();
    }

    private static function targetAvailable(string $table, string $column): bool
    {
        return DatabaseColumnInspector::hasColumn($table, $column);
    }

    private static function pendingCount(string $table, string $column, int $merchantId): int
    {
        if (!self::targetAvailable($table, $column) || !DatabaseColumnInspector::hasColumn($table, 'status')) {
            return 0;
        }

        return (int)Db::table($table)
            ->where($column, $merchantId)
            ->where('status', 0)
            ->count();
    }

    private static function confirmationPhrase(int $merchantId): string
    {
        return 'DELETE ACCOUNT ' . $merchantId;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function targets(): array
    {
        return [
            [
                'key' => 'userbasic',
                'label' => '商户基础配置',
                'table' => 'ypay_userbasic',
                'column' => 'user_id',
                'help_text' => '删除通讯密钥、超时回调、通知偏好等商户基础设置。',
            ],
            [
                'key' => 'payment_accounts',
                'label' => '商户本地通道',
                'table' => 'ypay_account',
                'column' => 'user_id',
                'help_text' => '删除当前商户名下的本地收款通道记录。',
            ],
            [
                'key' => 'merchant_paylists',
                'label' => '商户上游通道',
                'table' => 'ypay_paylist',
                'column' => 'user_id',
                'help_text' => '删除当前商户配置的上游支付通道凭据。',
            ],
            [
                'key' => 'payment_pools',
                'label' => '轮询池',
                'table' => 'ypay_poll_pool',
                'column' => 'user_id',
                'help_text' => '删除当前商户创建的轮询池配置。',
            ],
            [
                'key' => 'payment_pool_items',
                'label' => '轮询池通道',
                'table' => 'ypay_poll_pool_item',
                'column' => 'user_id',
                'help_text' => '删除轮询池内已绑定的通道选择记录。',
            ],
            [
                'key' => 'orders',
                'label' => '订单记录',
                'table' => 'ypay_order',
                'column' => 'user_id',
                'help_text' => '删除当前商户的订单流水。',
            ],
            [
                'key' => 'recharges',
                'label' => '充值记录',
                'table' => 'ypay_recharge',
                'column' => 'user_id',
                'help_text' => '删除当前商户的充值与付费注册记录。',
            ],
            [
                'key' => 'money_logs',
                'label' => '余额日志',
                'table' => 'money_log',
                'column' => 'user_id',
                'help_text' => '删除当前商户的余额变动日志。',
            ],
            [
                'key' => 'front_logs',
                'label' => '前台日志',
                'table' => 'admin_front_log',
                'column' => 'uid',
                'help_text' => '删除当前商户的前台访问与行为日志。',
            ],
            [
                'key' => 'domains',
                'label' => '域名记录',
                'table' => 'ypay_domain',
                'column' => 'user_id',
                'help_text' => '删除当前商户提交的域名与审核结果。',
            ],
            [
                'key' => 'risks',
                'label' => '风控记录',
                'table' => 'ypay_risk',
                'column' => 'user_id',
                'help_text' => '删除当前商户的风控命中记录。',
            ],
            [
                'key' => 'tickets',
                'label' => '工单记录',
                'table' => 'ypay_ticket',
                'column' => 'creator_id',
                'help_text' => '删除当前商户提交的工单记录。',
            ],
            [
                'key' => 'merchant_record',
                'label' => '商户账号',
                'table' => 'ypay_user',
                'column' => 'id',
                'help_text' => '删除当前商户账号本身。',
            ],
        ];
    }
}
