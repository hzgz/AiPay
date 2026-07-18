<?php

namespace app\support;

class AdminThemeFormatter
{
    public static function format(array $theme): array
    {
        $id = trim((string)($theme['id'] ?? ''));
        $title = self::nullableString($theme['title'] ?? null);
        $description = self::nullableString($theme['description'] ?? null);
        $version = self::nullableString($theme['version'] ?? null);
        $hasStyle = (bool)($theme['has_style'] ?? false);
        $hasScreenshot = (bool)($theme['has_screenshot'] ?? false);
        $isActive = (bool)($theme['is_active'] ?? false);
        $configMissing = (bool)($theme['config_missing'] ?? false);
        $metadataComplete = $title !== null && $description !== null && $version !== null;
        $activateSupported = (bool)($theme['activation_supported'] ?? false);
        $deleteSupported = (bool)($theme['delete_supported'] ?? false);

        return [
            'id' => $id,
            'scope' => (string)($theme['scope'] ?? ''),
            'scope_label' => (string)($theme['scope_label'] ?? ''),
            'title' => $title,
            'title_label' => $title ?: ($id !== '' ? $id : '未命名模板'),
            'description' => $description,
            'description_preview' => self::preview($description, 120) ?? '暂无模板说明',
            'version' => $version,
            'version_label' => $version ?: '--',
            'relative_path' => (string)($theme['relative_path'] ?? ''),
            'asset_path' => (string)($theme['asset_path'] ?? ''),
            'style_path' => self::nullableString($theme['style_path'] ?? null),
            'screenshot_path' => self::nullableString($theme['screenshot_path'] ?? null),
            'has_style' => $hasStyle,
            'has_screenshot' => $hasScreenshot,
            'metadata_complete' => $metadataComplete,
            'metadata_label' => $metadataComplete ? '元数据完整' : '元数据未完善',
            'metadata_type' => $metadataComplete ? 'success' : 'warning',
            'is_active' => $isActive,
            'status_label' => self::statusLabel($isActive, $hasStyle),
            'status_type' => self::statusType($isActive, $hasStyle),
            'config_key' => self::nullableString($theme['config_key'] ?? null),
            'configured_value' => self::nullableString($theme['configured_value'] ?? null),
            'effective_value' => self::nullableString($theme['effective_value'] ?? null),
            'config_missing' => $configMissing,
            'config_state_label' => self::configStateLabel($theme, $configMissing),
            'legacy_controller' => (string)($theme['legacy_controller'] ?? ''),
            'legacy_path' => (string)($theme['legacy_path'] ?? ''),
            'activate_supported' => $activateSupported,
            'delete_supported' => $deleteSupported,
            'readonly_note' => self::maintenanceNote($activateSupported, $deleteSupported),
        ];
    }

    private static function statusLabel(bool $isActive, bool $hasStyle): string
    {
        if ($isActive) {
            return '已启用';
        }

        if (!$hasStyle) {
            return '缺少样式文件';
        }

        return '可使用';
    }

    private static function statusType(bool $isActive, bool $hasStyle): string
    {
        if ($isActive) {
            return 'success';
        }

        if (!$hasStyle) {
            return 'warning';
        }

        return 'info';
    }

    private static function configStateLabel(array $theme, bool $configMissing): string
    {
        if (($theme['config_key'] ?? null) === null) {
            return '未接入系统配置';
        }

        if ($configMissing && self::nullableString($theme['effective_value'] ?? null) !== null) {
            return '当前使用默认配置';
        }

        if ($configMissing) {
            return '缺少系统配置';
        }

        return '配置已就绪';
    }

    private static function maintenanceNote(bool $activateSupported, bool $deleteSupported): string
    {
        if ($activateSupported && $deleteSupported) {
            return '当前范围已接入模板中心，支持启用切换与安全删除。若删除的是正在使用的模板，系统会先切回标准模板再完成清理。';
        }

        if ($deleteSupported) {
            return '当前范围已接入模板中心，现阶段开放查看与安全删除；通过引用检查后即可执行模板清理。';
        }

        if ($activateSupported) {
            return '当前范围已接入模板中心，现阶段支持启用切换；模板删除与文件清理暂未开放。';
        }

        return '当前范围当前仅开放信息查看；模板删除与文件清理暂未开放。';
    }

    private static function preview(?string $text, int $length): ?string
    {
        if ($text === null) {
            return null;
        }

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, max(1, $length - 3)) . '...';
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }
}
