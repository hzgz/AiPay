<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\support;

final class AdminThemeFormatter
{
    /**
     * @param array<string, mixed> $theme
     * @return array<string, mixed>
     */
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
        $activateSupported = (bool)($theme['activation_supported'] ?? $theme['activate_supported'] ?? false);
        $deleteSupported = (bool)($theme['delete_supported'] ?? false);

        return [
            'id' => $id,
            'scope' => (string)($theme['scope'] ?? ''),
            'scope_label' => (string)($theme['scope_label'] ?? ''),
            'title' => $title,
            'title_label' => $title ?: ($id !== '' ? $id : '未命名模板'),
            'description' => $description,
            'description_preview' => self::preview($description, 120) ?? '当前模板未填写说明',
            'version' => $version,
            'version_label' => $version ?: '--',
            'relative_path' => (string)($theme['relative_path'] ?? ''),
            'asset_path' => (string)($theme['asset_path'] ?? ''),
            'style_path' => self::nullableString($theme['style_path'] ?? null),
            'screenshot_path' => self::nullableString($theme['screenshot_path'] ?? null),
            'has_style' => $hasStyle,
            'has_screenshot' => $hasScreenshot,
            'metadata_complete' => $metadataComplete,
            'metadata_label' => $metadataComplete ? '元数据完整' : '元数据待补充',
            'metadata_type' => $metadataComplete ? 'success' : 'warning',
            'is_active' => $isActive,
            'status_label' => self::statusLabel($isActive, $hasStyle),
            'status_type' => self::statusType($isActive, $hasStyle),
            'config_key' => self::nullableString($theme['config_key'] ?? null),
            'configured_value' => self::nullableString($theme['configured_value'] ?? null),
            'effective_value' => self::nullableString($theme['effective_value'] ?? null),
            'config_missing' => $configMissing,
            'config_state_label' => self::configStateLabel($theme, $configMissing),
            'activate_supported' => $activateSupported,
            'delete_supported' => $deleteSupported,
            'readonly_note' => self::maintenanceNote($activateSupported, $deleteSupported),
        ];
    }

    private static function statusLabel(bool $isActive, bool $hasStyle): string
    {
        if ($isActive) {
            return '当前使用中';
        }

        if (!$hasStyle) {
            return '缺少样式文件';
        }

        return '可启用';
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

    /**
     * @param array<string, mixed> $theme
     */
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
            return '当前模板支持启用切换和安全删除。删除正在使用的模板前，系统会先切回备用模板。';
        }

        if ($deleteSupported) {
            return '当前模板支持查看和删除校验，确认通过后即可安全移除。';
        }

        if ($activateSupported) {
            return '当前模板支持启用切换，但暂不开放删除。';
        }

        return '当前模板仅支持查看信息。';
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
