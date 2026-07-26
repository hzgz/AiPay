<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

class AdminPaymentPoolFormatter
{
    public static function format(array $pool, array $stats = [], ?array $lastAccount = null): array
    {
        $id = (int)($pool['id'] ?? 0);
        $userId = (int)($pool['user_id'] ?? 0);
        $name = AdminFixtureTextNormalizer::normalize(trim((string)($pool['name'] ?? '')));
        $type = trim((string)($pool['type'] ?? ''));
        $merchantUsername = AdminFixtureTextNormalizer::normalize(trim((string)($pool['merchant_username'] ?? '')));
        $merchantName = AdminFixtureTextNormalizer::normalizeNullable(self::nullableString($pool['merchant_name'] ?? null)) ?? '';
        $roundType = self::normalizeRoundType($pool['round_type'] ?? 1);
        $status = self::normalizeStatus($pool['status'] ?? 1);
        $itemCount = max(0, (int)($stats['item_count'] ?? 0));
        $activeItemCount = max(0, (int)($stats['active_item_count'] ?? 0));
        $disabledItemCount = max(0, (int)($stats['disabled_item_count'] ?? 0));
        $missingItemCount = max(0, (int)($stats['missing_item_count'] ?? 0));
        $totalWeight = max(0, (int)($stats['total_weight'] ?? 0));
        $typeText = AdminOrderFormatter::paymentTypeLabel($type);
        $roundTypeText = self::roundTypeLabel($roundType);
        $statusText = $status === 1 ? '启用' : '停用';
        [$poolStateLabel, $poolStateType] = self::poolState($itemCount, $activeItemCount, $missingItemCount);
        $selectedPreview = self::selectedPreview($stats['items'] ?? []);
        $readonlyNote = '当前阶段已支持轮询池创建、删除、通道分配、名称、轮询方式与启停状态维护；支付类型变更仍暂不开放。';

        return [
            'id' => $id,
            'name' => $name,
            'name_label' => $name !== '' ? $name : ('轮询池 #' . $id),
            'user_id' => $userId,
            'merchant_username' => $merchantUsername,
            'merchant_name' => $merchantName === '' ? null : $merchantName,
            'merchant_display' => self::merchantDisplay($merchantUsername, $merchantName, $userId),
            'type' => $type,
            'type_text' => $typeText,
            'type_label' => $typeText,
            'type_tag' => self::typeTag($type),
            'round_type' => $roundType,
            'round_type_text' => $roundTypeText,
            'round_type_label' => $roundTypeText,
            'round_type_tag' => $roundType === 2 ? 'warning' : 'primary',
            'status' => $status,
            'status_text' => $statusText,
            'status_label' => $statusText,
            'status_type' => $status === 1 ? 'success' : 'info',
            'current_index' => max(0, (int)($pool['current_index'] ?? 0)),
            'current_weight' => max(1, (int)($pool['current_weight'] ?? 1)),
            'progress_label' => self::progressLabel(
                $roundType,
                max(0, (int)($pool['current_index'] ?? 0)),
                max(1, (int)($pool['current_weight'] ?? 1)),
                $itemCount
            ),
            'last_account_id' => max(0, (int)($pool['last_account_id'] ?? 0)),
            'last_account_label' => self::lastAccountLabel(
                max(0, (int)($pool['last_account_id'] ?? 0)),
                $lastAccount
            ),
            'item_count' => $itemCount,
            'active_item_count' => $activeItemCount,
            'disabled_item_count' => $disabledItemCount,
            'missing_item_count' => $missingItemCount,
            'total_weight' => $totalWeight,
            'has_items' => $itemCount > 0,
            'pool_state_text' => $poolStateLabel,
            'pool_state_label' => $poolStateLabel,
            'pool_state_type' => $poolStateType,
            'selected_preview_text' => $selectedPreview,
            'selected_preview' => $selectedPreview,
            'create_time' => self::nullableString($pool['create_time'] ?? null),
            'update_time' => self::nullableString($pool['update_time'] ?? null),
            'latest_item_time' => self::nullableString($stats['latest_item_time'] ?? null),
            'readonly_note_text' => $readonlyNote,
            'readonly_note' => $readonlyNote,
        ];
    }

    public static function formatDetail(array $pool, array $stats = [], ?array $lastAccount = null): array
    {
        $item = self::format($pool, $stats, $lastAccount);
        $lastAccountId = (int)($pool['last_account_id'] ?? 0);

        $item['selected_items'] = array_map(
            static fn (array $row): array => self::formatChannel($row, $lastAccountId),
            $stats['items'] ?? []
        );

        return $item;
    }

    public static function formatChannel(array $item, int $lastAccountId = 0): array
    {
        $accountId = (int)($item['account_id'] ?? 0);
        $accountExists = (bool)($item['account_exists'] ?? false);
        [$statusLabel, $statusType] = self::channelStatus(
            $accountExists,
            (int)($item['account_status'] ?? 0),
            (int)($item['account_enabled'] ?? 0)
        );

        return [
            'item_id' => (int)($item['item_id'] ?? 0),
            'account_id' => $accountId,
            'account_label' => self::accountLabel($accountId, $item),
            'channel_label' => self::channelLabel($item),
            'weight' => max(1, (int)($item['weight'] ?? 1)),
            'sort_order' => max(0, (int)($item['sort'] ?? 0)) + 1,
            'status_text' => $statusLabel,
            'status_label' => $statusLabel,
            'status_type' => $statusType,
            'account_exists' => $accountExists,
            'is_last_selected' => $lastAccountId > 0 && $accountId === $lastAccountId,
            'update_time' => self::nullableString(
                $item['update_time'] ?? ($item['create_time'] ?? ($item['account_update_time'] ?? null))
            ),
        ];
    }

    private static function selectedPreview(array $items): string
    {
        if ($items === []) {
            return '暂无已配通道';
        }

        $labels = array_map(
            static fn (array $item): string => self::accountLabel((int)($item['account_id'] ?? 0), $item),
            array_slice($items, 0, 3)
        );

        $preview = implode(' / ', array_filter($labels));
        $count = count($items);

        if ($count <= 3) {
            return $preview;
        }

        return $preview . ' 等 ' . $count . ' 条';
    }

    private static function poolState(int $itemCount, int $activeItemCount, int $missingItemCount): array
    {
        if ($itemCount === 0) {
            return ['空池', 'info'];
        }

        if ($activeItemCount === $itemCount && $missingItemCount === 0) {
            return ['全部可用', 'success'];
        }

        if ($activeItemCount > 0) {
            return ['部分可用', 'warning'];
        }

        if ($missingItemCount > 0) {
            return ['配置异常', 'danger'];
        }

        return ['全部不可用', 'danger'];
    }

    private static function channelStatus(bool $accountExists, int $status, int $enabled): array
    {
        if (!$accountExists) {
            return ['通道缺失', 'danger'];
        }

        if ($status !== 1) {
            return ['账号离线', 'warning'];
        }

        if ($enabled !== 1) {
            return ['收款关闭', 'info'];
        }

        return ['可用', 'success'];
    }

    private static function accountLabel(int $accountId, array $item): string
    {
        $channelLabel = self::channelLabel($item);
        $memo = AdminFixtureTextNormalizer::normalize(trim((string)($item['account_memo'] ?? '')));

        if (!(bool)($item['account_exists'] ?? false)) {
            return '#' . $accountId . ' / 已删除通道';
        }

        if ($memo !== '') {
            return '#' . $accountId . ' / ' . $channelLabel . ' / ' . $memo;
        }

        return '#' . $accountId . ' / ' . $channelLabel;
    }

    private static function channelLabel(array $item): string
    {
        $channelName = AdminFixtureTextNormalizer::normalize(trim((string)($item['channel_name'] ?? '')));
        if ($channelName !== '') {
            return $channelName;
        }

        $code = trim((string)($item['account_code'] ?? ''));
        return $code !== '' ? AdminFixtureTextNormalizer::normalize($code) : '未知通道';
    }

    private static function lastAccountLabel(int $accountId, ?array $account): ?string
    {
        if ($accountId <= 0) {
            return null;
        }

        if (!$account) {
            return '#' . $accountId . ' / 已删除通道';
        }

        return self::accountLabel($accountId, [
            'account_exists' => true,
            'channel_name' => $account['channel_name'] ?? '',
            'account_code' => $account['code'] ?? '',
            'account_memo' => $account['memo'] ?? '',
        ]);
    }

    private static function progressLabel(int $roundType, int $currentIndex, int $currentWeight, int $itemCount): string
    {
        if ($itemCount <= 0) {
            return '暂无轮询游标';
        }

        if ($roundType === 2) {
            return '随机权重 / 共 ' . $itemCount . ' 项';
        }

        $humanIndex = min($itemCount, $currentIndex + 1);
        return '顺序 ' . $humanIndex . '/' . $itemCount . ' · 权重游标 ' . $currentWeight;
    }

    private static function roundTypeLabel(int $roundType): string
    {
        return $roundType === 2 ? '随机轮询' : '顺序轮询';
    }

    private static function typeTag(string $type): string
    {
        return match (strtolower($type)) {
            'alipay' => 'primary',
            'wxpay', 'wechat' => 'success',
            'qqpay', 'qq' => 'warning',
            'usdt' => 'info',
            default => 'info',
        };
    }

    private static function merchantDisplay(string $username, string $name, int $userId): string
    {
        if ($name !== '' && $username !== '') {
            return $name . ' / ' . $username;
        }

        if ($name !== '') {
            return $name;
        }

        if ($username !== '') {
            return $username;
        }

        return $userId > 0 ? '商户 #' . $userId : '未绑定商户';
    }

    private static function normalizeRoundType(mixed $value): int
    {
        return (int)$value === 2 ? 2 : 1;
    }

    private static function normalizeStatus(mixed $value): int
    {
        return (int)$value === 0 ? 0 : 1;
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }
}
