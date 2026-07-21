<?php

namespace app\support;

class AdminChannelCatalogFormatter
{
    public static function format(array $channel, array $stats = []): array
    {
        $status = (int)($channel['status'] ?? 0);
        $type = trim((string)($channel['type'] ?? ''));
        $createType = (int)($channel['create_type'] ?? 0);
        $summary = self::dependencySummary($stats);
        $policy = self::lifecyclePolicy($channel, $stats);

        return [
            'id' => (int)($channel['id'] ?? 0),
            'name' => AdminFixtureTextNormalizer::normalize(trim((string)($channel['name'] ?? ''))),
            'type' => $type,
            'type_label' => self::paymentTypeLabel($type),
            'type_tag' => self::typeTag($type),
            'payment_name' => AdminFixtureTextNormalizer::normalizeNullable(self::nullableString($channel['payment_name'] ?? null)),
            'payment_display' => self::paymentDisplay($channel, $type),
            'create_type' => $createType,
            'create_type_label' => self::createTypeLabel($createType),
            'create_type_type' => self::createTypeType($createType),
            'code' => trim((string)($channel['code'] ?? '')),
            'code_display' => AdminFixtureTextNormalizer::normalize(trim((string)($channel['code'] ?? ''))),
            'info' => AdminFixtureTextNormalizer::normalizeNullable(self::nullableString($channel['info'] ?? null)),
            'info_preview' => self::preview(AdminFixtureTextNormalizer::normalize((string)($channel['info'] ?? '')), 56),
            'has_info' => trim((string)($channel['info'] ?? '')) !== '',
            'status' => $status,
            'status_label' => self::statusLabel($status),
            'status_type' => self::statusType($status),
            'sort' => (int)($channel['sort'] ?? 0),
            'maxcount' => (int)($channel['maxcount'] ?? 0),
            'account_count' => $summary['account_count'],
            'merchant_count' => $summary['merchant_count'],
            'online_account_count' => $summary['online_account_count'],
            'enabled_account_count' => $summary['enabled_account_count'],
            'pool_count' => $summary['pool_count'],
            'pool_item_count' => $summary['pool_item_count'],
            'order_count' => $summary['order_count'],
            'paid_order_count' => $summary['paid_order_count'],
            'paid_amount' => $summary['paid_amount'],
            'latest_account_time' => self::nullableString($summary['latest_account_time']),
            'latest_order_time' => self::nullableString($summary['latest_order_time']),
            'usage_preview' => self::usagePreview(
                $summary['account_count'],
                $summary['merchant_count'],
                $summary['online_account_count'],
                $summary['pool_count']
            ),
            'dependency_preview' => self::dependencyPreview($summary),
            'create_time' => self::nullableString($channel['create_time'] ?? null),
            'deleted' => !empty($policy['deleted']),
            'delete_time' => self::nullableString($channel['delete_time'] ?? null),
            'plugin_owner_code' => self::nullableString($channel['plugin_owner_code'] ?? null),
            'plugin_owner_name' => self::nullableString($channel['plugin_owner_name'] ?? null),
            'plugin_catalog_available' => !empty($channel['plugin_catalog_available']),
            'plugin_owner_label' => self::pluginOwnerLabel($channel),
            'is_local' => $policy['is_local'],
            'is_plugin_managed' => $policy['is_plugin_managed'],
            'has_dependencies' => $policy['has_dependencies'],
            'can_edit' => $policy['can_edit'],
            'can_delete' => $policy['can_delete'],
            'can_change_type' => $policy['can_change_type'],
            'can_change_code' => $policy['can_change_code'],
            'lifecycle_mode_label' => self::lifecycleModeLabel($policy),
            'lifecycle_mode_type' => self::lifecycleModeType($policy),
            'maintenance_note' => self::maintenanceNote($policy),
            'readonly_note' => self::maintenanceNote($policy),
            'blocking_reasons' => array_values(array_map('strval', (array)($policy['blocking_reasons'] ?? []))),
            'warnings' => array_values(array_map('strval', (array)($policy['warnings'] ?? []))),
        ];
    }

    public static function lifecyclePolicy(array $channel, array $stats = []): array
    {
        $summary = self::dependencySummary($stats);
        $createType = (int)($channel['create_type'] ?? 0);
        $isLocal = $createType === 1;
        $isPluginManaged = $createType === 2;
        $pluginOwnerCode = trim((string)($channel['plugin_owner_code'] ?? ''));
        $pluginOwnerName = trim((string)($channel['plugin_owner_name'] ?? ''));
        $pluginCatalogAvailable = !empty($channel['plugin_catalog_available']);
        $pluginOwnerLabel = self::pluginOwnerLabel($channel);
        $isDeleted = !empty($channel['deleted'])
            || !empty($channel['is_deleted'])
            || trim((string)($channel['delete_time'] ?? '')) !== '';
        $hasDependencies = $summary['account_count'] > 0
            || $summary['pool_item_count'] > 0
            || $summary['order_count'] > 0;

        $blockingReasons = [];
        if ($isDeleted) {
            $blockingReasons[] = '该通道已经在回收站中。';
        }
        if ($isPluginManaged) {
            $blockingReasons[] = $pluginOwnerLabel !== null
                ? '该通道归属于插件 ' . $pluginOwnerLabel . '，必须通过支付插件生命周期维护。'
                : '该通道由支付插件托管，必须通过支付插件生命周期维护。';
            if (!$pluginCatalogAvailable) {
                $blockingReasons[] = '本地插件目录当前不可用，请先检查恢复快照或注册表残留。';
            }
        }
        if ($summary['account_count'] > 0) {
            $blockingReasons[] = sprintf('已被 %d 个支付账号引用。', $summary['account_count']);
        }
        if ($summary['pool_item_count'] > 0) {
            $blockingReasons[] = sprintf('已出现在 %d 个轮询池条目中。', $summary['pool_item_count']);
        }
        if ($summary['order_count'] > 0) {
            $blockingReasons[] = sprintf('已有 %d 笔历史订单关联该通道。', $summary['order_count']);
        }

        $warnings = [];
        if ($isPluginManaged) {
            $warnings[] = $pluginOwnerLabel !== null
                ? '请到支付插件页维护 ' . $pluginOwnerLabel . '，不要在共享本地通道里直接修改。'
                : '请到支付插件页修复或清理该托管通道，不要直接修改共享本地通道。';
            if (!$pluginCatalogAvailable) {
                $warnings[] = '插件目录已缺失，请优先检查恢复快照、恢复仓库或注册表残留清理链路。';
            }
        }
        if ($isLocal && $hasDependencies) {
            $warnings[] = '当前本地通道已有依赖，建议只维护名称、说明、排序、单商户上限和状态等低风险字段。';
            $warnings[] = '如果继续变更通道标识或支付类型，历史账号、轮询池和订单映射可能失真。';
        }
        if ($isLocal && !$hasDependencies) {
            $warnings[] = '当前为本地通道，可在目录页直接维护。';
            $warnings[] = '删除会先移动到回收站，而不是直接硬删除。';
        }
        if (!$isLocal && !$isPluginManaged) {
            $warnings[] = '该通道来源未知，请在变更前先核对历史数据。';
        }

        if ($isDeleted) {
            $warnings = [
                '恢复该通道后，才能继续执行编辑或状态维护。',
            ];
        }

        $canEdit = $isLocal && !$isDeleted;
        $canDelete = $isLocal && !$hasDependencies && !$isDeleted;
        $canChangeType = $isLocal && !$hasDependencies && !$isDeleted;
        $canChangeCode = $isLocal && !$hasDependencies && !$isDeleted;

        return [
            'deleted' => $isDeleted,
            'is_local' => $isLocal,
            'is_plugin_managed' => $isPluginManaged,
            'plugin_owner_code' => $pluginOwnerCode !== '' ? $pluginOwnerCode : null,
            'plugin_owner_name' => $pluginOwnerName !== '' ? $pluginOwnerName : null,
            'plugin_owner_label' => $pluginOwnerLabel,
            'plugin_catalog_available' => $pluginCatalogAvailable,
            'has_dependencies' => $hasDependencies,
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
            'can_change_type' => $canChangeType,
            'can_change_code' => $canChangeCode,
            'blocking_reasons' => $blockingReasons,
            'warnings' => $warnings,
            'summary' => $summary,
        ];
    }

    public static function paymentTypeLabel(?string $type): string
    {
        return match (strtolower(trim((string)$type))) {
            'alipay' => '支付宝',
            'wxpay', 'wechat' => '微信',
            'qqpay', 'qq' => 'QQ',
            'usdt' => 'USDT',
            'epay_ali' => '易支付支付宝',
            'epay_wechat' => '易支付微信',
            default => trim((string)$type) !== ''
                ? strtoupper(str_replace('_', ' ', trim((string)$type)))
                : '未知类型',
        };
    }

    public static function createTypeLabel(int $createType): string
    {
        return match ($createType) {
            1 => '本地通道',
            2 => '插件通道',
            default => '未知来源',
        };
    }

    public static function statusLabel(int $status): string
    {
        return $status === 1 ? '启用' : '禁用';
    }

    private static function paymentDisplay(array $channel, string $type): string
    {
        $paymentName = trim((string)($channel['payment_name'] ?? ''));
        if ($paymentName !== '') {
            return AdminFixtureTextNormalizer::normalize($paymentName);
        }

        return self::paymentTypeLabel($type);
    }

    private static function usagePreview(
        int $accountCount,
        int $merchantCount,
        int $onlineAccountCount,
        int $poolCount
    ): string {
        if ($accountCount <= 0) {
            return '暂无支付账号引用';
        }

        return '商户 ' . $merchantCount . ' / 在线账号 ' . $onlineAccountCount . ' / 轮询池 ' . $poolCount;
    }

    private static function dependencyPreview(array $summary): string
    {
        if ($summary['account_count'] <= 0 && $summary['pool_item_count'] <= 0 && $summary['order_count'] <= 0) {
            return '暂无账号、轮询池或订单依赖';
        }

        return sprintf(
            '账号 %d / 轮询条目 %d / 订单 %d',
            $summary['account_count'],
            $summary['pool_item_count'],
            $summary['order_count']
        );
    }

    private static function lifecycleModeLabel(array $policy): string
    {
        if (!empty($policy['deleted'])) {
            return '回收站';
        }
        if (!empty($policy['is_plugin_managed'])) {
            return '插件维护';
        }

        if (!empty($policy['can_delete']) && !empty($policy['can_change_code'])) {
            return '可完整维护';
        }

        if (!empty($policy['can_edit'])) {
            return '受限维护';
        }

        return '查看';
    }

    private static function lifecycleModeType(array $policy): string
    {
        if (!empty($policy['deleted'])) {
            return 'info';
        }
        if (!empty($policy['is_plugin_managed'])) {
            return 'warning';
        }

        if (!empty($policy['can_delete']) && !empty($policy['can_change_code'])) {
            return 'success';
        }

        if (!empty($policy['can_edit'])) {
            return 'primary';
        }

        return 'info';
    }

    private static function maintenanceNote(array $policy): string
    {
        if (!empty($policy['deleted'])) {
            return '当前通道已进入回收站，恢复后才能继续维护。';
        }
        if (!empty($policy['is_plugin_managed'])) {
            $ownerLabel = trim((string)($policy['plugin_owner_label'] ?? ''));
            if ($ownerLabel !== '' && !empty($policy['plugin_catalog_available'])) {
                return '由插件 ' . $ownerLabel . ' 托管，请在支付插件页完成安装、修复、启停、快照和清理操作。';
            }

            if ($ownerLabel !== '' && empty($policy['plugin_catalog_available'])) {
                return '插件 ' . $ownerLabel . ' 的目录已缺失，请通过支付插件页的恢复或残留清理链路处理。';
            }

            return '当前通道由支付插件托管，请通过支付插件生命周期维护。';
        }

        if (!empty($policy['has_dependencies'])) {
            return '已有支付账号、轮询池或订单依赖，建议仅维护低风险字段，禁止删除。';
        }

        if (!empty($policy['can_edit'])) {
            return '当前为本地通道，可直接创建、编辑、切换状态和删除。';
        }

        return '当前记录支持查看。';
    }

    private static function createTypeType(int $createType): string
    {
        return match ($createType) {
            1 => 'success',
            2 => 'warning',
            default => 'info',
        };
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

    private static function statusType(int $status): string
    {
        return $status === 1 ? 'success' : 'warning';
    }

    private static function preview(string $value, int $limit): string
    {
        $value = trim($value);
        if ($value === '') {
            return '暂无说明';
        }

        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, max(1, $limit - 1)) . '…';
    }

    private static function dependencySummary(array $stats): array
    {
        return [
            'account_count' => max(0, (int)($stats['account_count'] ?? 0)),
            'merchant_count' => max(0, (int)($stats['merchant_count'] ?? 0)),
            'online_account_count' => max(0, (int)($stats['online_account_count'] ?? 0)),
            'enabled_account_count' => max(0, (int)($stats['enabled_account_count'] ?? 0)),
            'pool_count' => max(0, (int)($stats['pool_count'] ?? 0)),
            'pool_item_count' => max(0, (int)($stats['pool_item_count'] ?? 0)),
            'order_count' => max(0, (int)($stats['order_count'] ?? 0)),
            'paid_order_count' => max(0, (int)($stats['paid_order_count'] ?? 0)),
            'paid_amount' => AdminPaymentFormatter::toFloat($stats['paid_amount'] ?? 0),
            'latest_account_time' => $stats['latest_account_time'] ?? null,
            'latest_order_time' => $stats['latest_order_time'] ?? null,
        ];
    }

    private static function pluginOwnerLabel(array $channel): ?string
    {
        $code = trim((string)($channel['plugin_owner_code'] ?? ''));
        $name = trim((string)($channel['plugin_owner_name'] ?? ''));

        if ($code === '' && $name === '') {
            return null;
        }

        if ($code === '') {
            return $name;
        }

        if ($name === '' || $name === $code) {
            return $code;
        }

        return $name . ' (' . $code . ')';
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }
}
