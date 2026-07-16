<?php

namespace app\support;

class AdminCleanupAuditFormatter
{
    public static function format(array $item): array
    {
        $totalCount = max(0, (int)($item['total_count'] ?? 0));
        $targetCount = max(0, (int)($item['target_count'] ?? 0));
        $recommended = !empty($item['recommended']);
        $thresholdValue = isset($item['threshold_value']) ? (int)$item['threshold_value'] : null;
        $actionMode = trim((string)($item['action_mode'] ?? 'delete_where'));
        $ratio = $totalCount > 0 ? round($targetCount / $totalCount, 4) : 0.0;
        [$statusLabel, $statusType] = self::statusMeta($targetCount, $recommended, $thresholdValue);
        $actionAvailable = $targetCount > 0;
        $actionModeLabel = self::actionModeLabel($actionMode, (string)($item['action_mode_label'] ?? ''));
        $actionModeType = $actionMode === 'truncate' ? 'danger' : 'warning';
        $actionScopeLabel = self::nonEmptyString(
            $item['action_scope_label'] ?? null,
            self::defaultActionScopeLabel($item, $actionMode)
        );
        $actionLabel = self::nonEmptyString(
            $item['action_label'] ?? null,
            self::defaultActionLabel($item)
        );
        $thresholdLabel = self::thresholdLabel($item, $thresholdValue);
        $maintenanceNote = self::maintenanceNote($item, $actionAvailable, $recommended, $thresholdValue, $actionMode);

        return [
            'key' => trim((string)($item['key'] ?? '')),
            'title' => trim((string)($item['title'] ?? '')),
            'category' => trim((string)($item['category'] ?? '')),
            'category_label' => trim((string)($item['category_label'] ?? '')),
            'table_name' => trim((string)($item['table_name'] ?? '')),
            'target_description' => trim((string)($item['target_description'] ?? '')),
            'legacy_page' => trim((string)($item['legacy_page'] ?? '')),
            'legacy_endpoint' => trim((string)($item['legacy_endpoint'] ?? '')),
            'legacy_action_label' => trim((string)($item['legacy_action_label'] ?? '')),
            'note' => self::nullableString($item['note'] ?? null),
            'readonly_note' => $maintenanceNote,
            'maintenance_note' => $maintenanceNote,
            'total_count' => $totalCount,
            'target_count' => $targetCount,
            'has_targets' => $targetCount > 0,
            'ratio' => $ratio,
            'ratio_label' => number_format($ratio * 100, 2) . '%',
            'threshold_value' => $thresholdValue,
            'threshold_label' => $thresholdLabel,
            'recommended' => $recommended,
            'status_label' => $statusLabel,
            'status_type' => $statusType,
            'latest_record_time' => self::nullableString($item['latest_record_time'] ?? null),
            'latest_target_time' => self::nullableString($item['latest_target_time'] ?? null),
            'action_available' => $actionAvailable,
            'action_mode' => $actionMode,
            'action_mode_label' => $actionModeLabel,
            'action_mode_type' => $actionModeType,
            'action_scope_label' => $actionScopeLabel,
            'action_label' => $actionLabel,
        ];
    }

    private static function statusMeta(int $targetCount, bool $recommended, ?int $thresholdValue): array
    {
        if ($targetCount <= 0) {
            return ['无需处理', 'success'];
        }

        if ($thresholdValue !== null && !$recommended) {
            return ['可手动清理', 'info'];
        }

        return ['建议清理', 'warning'];
    }

    private static function actionModeLabel(string $mode, string $fallback): string
    {
        $fallback = trim($fallback);
        if ($fallback !== '') {
            return $fallback;
        }

        return match ($mode) {
            'truncate' => '整表清空',
            default => '条件删除',
        };
    }

    private static function defaultActionScopeLabel(array $item, string $actionMode): string
    {
        if ($actionMode === 'truncate') {
            return '直接清空整张数据表';
        }

        return trim((string)($item['target_description'] ?? '按当前规则清理命中记录'));
    }

    private static function defaultActionLabel(array $item): string
    {
        $title = trim((string)($item['title'] ?? '清理动作'));
        if ($title === '') {
            return '执行清理';
        }

        if (str_contains($title, '清理')) {
            return $title;
        }

        return '执行' . $title;
    }

    private static function thresholdLabel(array $item, ?int $thresholdValue): string
    {
        $label = trim((string)($item['threshold_label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        if ($thresholdValue !== null) {
            return '建议 ' . $thresholdValue . ' 条以上再清理';
        }

        return '存在命中记录即可清理';
    }

    private static function maintenanceNote(
        array $item,
        bool $actionAvailable,
        bool $recommended,
        ?int $thresholdValue,
        string $actionMode
    ): string {
        $title = trim((string)($item['title'] ?? ''));
        $tableName = trim((string)($item['table_name'] ?? ''));
        $totalCount = max(0, (int)($item['total_count'] ?? 0));
        $targetCount = max(0, (int)($item['target_count'] ?? 0));

        if (!$actionAvailable) {
            if ($thresholdValue !== null) {
                return $title !== ''
                    ? $title . ' 当前没有可清理日志，保持现状即可。'
                    : '当前没有可清理日志，保持现状即可。';
            }

            return $title !== ''
                ? $title . ' 当前没有命中待清理数据。'
                : '当前没有命中待清理数据。';
        }

        if ($thresholdValue !== null && !$recommended) {
            return sprintf(
                '当前共有 %d 条日志，低于建议阈值 %d 条，通常无需清理，但仍允许管理员手动执行。',
                $totalCount,
                $thresholdValue
            );
        }

        if ($actionMode === 'truncate') {
            return sprintf(
                '执行后会直接清空 %s 表，属于高风险维护操作，请先确认当前日志是否仍需保留。',
                $tableName !== '' ? $tableName : '目标'
            );
        }

        return sprintf(
            '执行后将按当前清理规则永久删除 %d 条命中记录，其余 %d 条记录会保留。',
            $targetCount,
            max(0, $totalCount - $targetCount)
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }

    private static function nonEmptyString(mixed $value, string $fallback): string
    {
        $string = trim((string)$value);
        return $string === '' ? $fallback : $string;
    }
}
