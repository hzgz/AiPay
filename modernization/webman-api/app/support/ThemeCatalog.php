<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\support;

use support\Db;

final class ThemeCatalog
{
    /**
     * @var array<string, array{
     *     label: string,
     *     config_key: string,
     *     default_id: string,
     *     activation_supported: bool
     * }>
     */
    private const SCOPES = [
        'home' => [
            'label' => '首页模板',
            'config_key' => 'theme_home',
            'default_id' => 'default',
            'activation_supported' => true,
        ],
        'pay' => [
            'label' => '支付模板',
            'config_key' => 'theme_pay',
            'default_id' => 'default',
            'activation_supported' => true,
        ],
    ];

    public static function normalizeScope(string $scope): ?string
    {
        $normalized = strtolower(trim($scope));
        if ($normalized === '' || !isset(self::SCOPES[$normalized])) {
            return null;
        }

        return $normalized;
    }

    /**
     * @return array<string, array{
     *     label: string,
     *     config_key: string,
     *     default_id: string,
     *     activation_supported: bool
     * }>
     */
    public static function scopeDefinitions(): array
    {
        return self::SCOPES;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function allThemes(): array
    {
        $themes = [];
        foreach (array_keys(self::SCOPES) as $scope) {
            foreach (self::scopeThemes($scope) as $theme) {
                $themes[] = $theme;
            }
        }

        usort($themes, static function (array $left, array $right): int {
            return [$left['scope'] ?? '', !empty($left['is_active']) ? 0 : 1, $left['id'] ?? '']
                <=> [$right['scope'] ?? '', !empty($right['is_active']) ? 0 : 1, $right['id'] ?? ''];
        });

        return $themes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function filteredThemes(?string $scope, string $keyword = '', string $status = ''): array
    {
        $themes = self::allThemes();
        $keyword = trim($keyword);
        $status = strtolower(trim($status));

        return array_values(array_filter($themes, static function (array $theme) use ($scope, $keyword, $status): bool {
            if ($scope !== null && ($theme['scope'] ?? '') !== $scope) {
                return false;
            }

            if ($keyword !== '') {
                $matched = false;
                foreach ([
                    (string)($theme['id'] ?? ''),
                    (string)($theme['title'] ?? ''),
                    (string)($theme['description'] ?? ''),
                    (string)($theme['scope_label'] ?? ''),
                ] as $haystack) {
                    if ($haystack !== '' && mb_stripos($haystack, $keyword) !== false) {
                        $matched = true;
                        break;
                    }
                }

                if (!$matched) {
                    return false;
                }
            }

            return match ($status) {
                '', 'all' => true,
                'active' => !empty($theme['is_active']),
                'inactive' => empty($theme['is_active']),
                'config-missing' => !empty($theme['config_missing']),
                default => true,
            };
        }));
    }

    /**
     * @return list<array{label: string, value: string, count: int, config_key: string|null, default_value: string|null, activation_supported: bool}>
     */
    public static function scopeOptions(): array
    {
        $options = [];
        foreach (self::SCOPES as $scope => $definition) {
            $options[] = [
                'label' => $definition['label'],
                'value' => $scope,
                'count' => count(self::scopeThemes($scope)),
                'config_key' => $definition['config_key'],
                'default_value' => $definition['default_id'],
                'activation_supported' => $definition['activation_supported'],
            ];
        }

        return $options;
    }

    /**
     * @param list<array<string, mixed>> $themes
     * @return array<string, int|string>
     */
    public static function summary(array $themes): array
    {
        return [
            'total_count' => count($themes),
            'scope_count' => count(array_unique(array_map(static fn (array $theme): string => (string)($theme['scope'] ?? ''), $themes))),
            'active_count' => count(array_filter($themes, static fn (array $theme): bool => !empty($theme['is_active']))),
            'screenshot_count' => count(array_filter($themes, static fn (array $theme): bool => !empty($theme['has_screenshot']))),
            'metadata_ready_count' => count(array_filter($themes, static fn (array $theme): bool => !empty($theme['title']) && !empty($theme['description']) && !empty($theme['version']))),
            'config_missing_count' => count(array_filter($themes, static fn (array $theme): bool => !empty($theme['config_missing']))),
            'style_missing_count' => count(array_filter($themes, static fn (array $theme): bool => empty($theme['has_style']))),
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findTheme(string $scope, string $id): ?array
    {
        $normalizedScope = self::normalizeScope($scope);
        $normalizedId = self::normalizeThemeId($id);
        if ($normalizedScope === null || $normalizedId === '') {
            return null;
        }

        foreach (self::scopeThemes($normalizedScope) as $theme) {
            if (($theme['id'] ?? '') === $normalizedId) {
                return $theme;
            }
        }

        return null;
    }

    public static function effectiveThemeId(string $scope): string
    {
        $normalizedScope = self::normalizeScope($scope);
        if ($normalizedScope === null) {
            return 'default';
        }

        $definition = self::SCOPES[$normalizedScope];
        $configured = trim((string)SystemConfig::get($definition['config_key'], ''));
        $availableIds = array_map(
            static fn (array $theme): string => (string)($theme['id'] ?? ''),
            self::scopeThemes($normalizedScope)
        );

        if ($configured !== '' && in_array($configured, $availableIds, true)) {
            return $configured;
        }

        if (in_array($definition['default_id'], $availableIds, true)) {
            return $definition['default_id'];
        }

        return $availableIds[0] ?? $definition['default_id'];
    }

    /**
     * @return array{id: string, title: string, scope: string, scope_label: string, config_key: string}
     */
    public static function activeThemeSummary(string $scope): array
    {
        $normalizedScope = self::normalizeScope($scope) ?? 'home';
        $theme = self::findTheme($normalizedScope, self::effectiveThemeId($normalizedScope));
        $definition = self::SCOPES[$normalizedScope];

        return [
            'id' => (string)($theme['id'] ?? $definition['default_id']),
            'title' => trim((string)($theme['title'] ?? $theme['id'] ?? $definition['default_id'])) ?: $definition['default_id'],
            'scope' => $normalizedScope,
            'scope_label' => $definition['label'],
            'config_key' => $definition['config_key'],
        ];
    }

    /**
     * @return array{item: array<string, mixed>, previous_theme_id: string|null, previous_theme_label: string|null, config_key: string}
     */
    public static function activateTheme(string $scope, string $id): array
    {
        $theme = self::findTheme($scope, $id);
        if ($theme === null) {
            throw new \InvalidArgumentException('模板不存在');
        }

        $definition = self::SCOPES[(string)$theme['scope']];
        $previous = self::findTheme((string)$theme['scope'], self::effectiveThemeId((string)$theme['scope']));

        self::persistConfigValue($definition['config_key'], (string)$theme['id']);

        return [
            'item' => self::findTheme((string)$theme['scope'], (string)$theme['id']) ?? $theme,
            'previous_theme_id' => $previous ? (string)($previous['id'] ?? '') : null,
            'previous_theme_label' => $previous ? self::themeLabel($previous) : null,
            'config_key' => $definition['config_key'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildDeleteAudit(string $scope, string $id): array
    {
        $theme = self::findTheme($scope, $id);
        if ($theme === null) {
            throw new \InvalidArgumentException('模板不存在');
        }

        $scopeDefinition = self::SCOPES[(string)$theme['scope']];
        $stats = self::directoryStats((string)($theme['absolute_path'] ?? ''));
        $fallback = self::fallbackTheme($theme);
        $isBuiltin = !empty($theme['builtin']);
        $isActive = !empty($theme['is_active']);

        $blockingReasons = [];
        if ($isBuiltin) {
            $blockingReasons[] = '内置模板不支持删除。';
        }
        if (empty($stats['exists'])) {
            $blockingReasons[] = '模板目录不存在，无法执行删除。';
        }
        if ($isActive && $fallback === null) {
            $blockingReasons[] = '当前模板正在使用，且没有可切换的备用模板。';
        }

        $warnings = [];
        if ($isActive && $fallback !== null) {
            $warnings[] = '删除后系统会自动切换到备用模板：' . self::themeLabel($fallback) . '。';
        }

        return [
            'scope' => (string)$theme['scope'],
            'scope_label' => $scopeDefinition['label'],
            'theme_id' => (string)$theme['id'],
            'theme_label' => self::themeLabel($theme),
            'is_active' => $isActive,
            'can_delete' => $blockingReasons === [] && !empty($theme['delete_supported']),
            'confirmation_phrase' => '删除模板 ' . (string)$theme['id'],
            'blocking_reasons' => $blockingReasons,
            'warnings' => $warnings,
            'directory' => [
                'exists' => (bool)$stats['exists'],
                'absolute_path' => (string)($theme['absolute_path'] ?? ''),
                'relative_path' => (string)($theme['relative_path'] ?? ''),
                'file_count' => (int)$stats['file_count'],
                'directory_count' => (int)$stats['directory_count'],
                'entry_count' => (int)$stats['entry_count'],
                'size_bytes' => (int)$stats['size_bytes'],
            ],
            'fallback' => [
                'required' => $isActive,
                'config_key' => $scopeDefinition['config_key'],
                'theme_id' => $fallback ? (string)($fallback['id'] ?? '') : null,
                'theme_label' => $fallback ? self::themeLabel($fallback) : null,
            ],
            'summary' => [
                'file_count' => (int)$stats['file_count'],
                'directory_count' => (int)$stats['directory_count'],
                'entry_count' => (int)$stats['entry_count'],
                'size_bytes' => (int)$stats['size_bytes'],
                'paypage_reference_count' => (string)$theme['scope'] === 'pay' && $isActive ? 1 : 0,
            ],
        ];
    }

    /**
     * @return array{item: array<string, mixed>, fallback_theme_id: string|null, fallback_theme_label: string|null, config_key: string|null, audit: array<string, mixed>}
     */
    public static function deleteTheme(string $scope, string $id): array
    {
        $audit = self::buildDeleteAudit($scope, $id);
        if (empty($audit['can_delete'])) {
            throw new \RuntimeException('当前模板不允许删除');
        }

        $theme = self::findTheme($scope, $id);
        if ($theme === null) {
            throw new \InvalidArgumentException('模板不存在');
        }

        $fallbackTheme = self::fallbackTheme($theme);
        if (!empty($audit['fallback']['required']) && $fallbackTheme !== null) {
            self::persistConfigValue((string)$audit['fallback']['config_key'], (string)($fallbackTheme['id'] ?? ''));
        }

        self::deleteDirectory((string)($theme['absolute_path'] ?? ''));

        return [
            'item' => $theme,
            'fallback_theme_id' => $fallbackTheme ? (string)($fallbackTheme['id'] ?? '') : null,
            'fallback_theme_label' => $fallbackTheme ? self::themeLabel($fallbackTheme) : null,
            'config_key' => (string)($audit['fallback']['config_key'] ?? ''),
            'audit' => $audit,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function scopeThemes(string $scope): array
    {
        $definition = self::SCOPES[$scope];
        $configured = trim((string)SystemConfig::get($definition['config_key'], ''));
        $themeRoot = self::scopeRoot($scope);
        if (!is_dir($themeRoot)) {
            return [];
        }

        $items = [];
        $availableIds = [];
        $directories = array_filter(glob($themeRoot . DIRECTORY_SEPARATOR . '*') ?: [], 'is_dir');
        sort($directories);

        foreach ($directories as $directory) {
            $theme = self::readTheme($scope, $directory, $definition);
            if ($theme === null) {
                continue;
            }

            $items[] = $theme;
            $availableIds[] = (string)$theme['id'];
        }

        $effective = $configured !== '' && in_array($configured, $availableIds, true)
            ? $configured
            : (in_array($definition['default_id'], $availableIds, true) ? $definition['default_id'] : ($availableIds[0] ?? $definition['default_id']));

        $configMissing = $configured === '' || !in_array($configured, $availableIds, true);

        foreach ($items as &$item) {
            $item['configured_value'] = $configured !== '' ? $configured : null;
            $item['effective_value'] = $effective;
            $item['config_missing'] = $configMissing;
            $item['is_active'] = ($item['id'] ?? '') === $effective;
        }
        unset($item);

        return $items;
    }

    /**
     * @param array{label: string, config_key: string, default_id: string, activation_supported: bool} $definition
     * @return array<string, mixed>|null
     */
    private static function readTheme(string $scope, string $directory, array $definition): ?array
    {
        $id = self::normalizeThemeId(basename($directory));
        if ($id === '') {
            return null;
        }

        $relativeDirectory = 'public/themes/' . $scope . '/' . $id;
        $manifest = self::manifest($directory);
        $stylePath = is_file($directory . DIRECTORY_SEPARATOR . 'style.css')
            ? '/themes/' . $scope . '/' . $id . '/style.css'
            : null;
        $previewPath = self::previewPath($scope, $id, $directory, $manifest);

        $title = trim((string)($manifest['title'] ?? ''));
        $description = trim((string)($manifest['description'] ?? ''));
        $version = trim((string)($manifest['version'] ?? ''));

        return [
            'id' => $id,
            'scope' => $scope,
            'scope_label' => $definition['label'],
            'title' => $title !== '' ? $title : null,
            'description' => $description !== '' ? $description : null,
            'version' => $version !== '' ? $version : null,
            'relative_path' => $relativeDirectory,
            'absolute_path' => $directory,
            'asset_path' => '/themes/' . $scope . '/' . $id . '/',
            'style_path' => $stylePath,
            'screenshot_path' => $previewPath,
            'has_style' => $stylePath !== null,
            'has_screenshot' => $previewPath !== null,
            'config_key' => $definition['config_key'],
            'activation_supported' => !isset($manifest['activation_supported'])
                ? $definition['activation_supported']
                : (bool)$manifest['activation_supported'],
            'delete_supported' => (bool)($manifest['delete_supported'] ?? false),
            'builtin' => !isset($manifest['builtin']) ? true : (bool)$manifest['builtin'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function manifest(string $directory): array
    {
        $manifestPath = $directory . DIRECTORY_SEPARATOR . 'theme.json';
        $manifest = [];

        if (is_file($manifestPath)) {
            $decoded = json_decode((string)file_get_contents($manifestPath), true);
            if (is_array($decoded)) {
                $manifest = $decoded;
            }
        }

        if (($manifest['title'] ?? '') === '' || ($manifest['description'] ?? '') === '' || ($manifest['version'] ?? '') === '') {
            $cssMeta = self::readCssMeta($directory . DIRECTORY_SEPARATOR . 'style.css');
            $manifest['title'] = trim((string)($manifest['title'] ?? $cssMeta['themename'] ?? ''));
            $manifest['description'] = trim((string)($manifest['description'] ?? $cssMeta['description'] ?? ''));
            $manifest['version'] = trim((string)($manifest['version'] ?? $cssMeta['version'] ?? ''));
        }

        return $manifest;
    }

    /**
     * @return array<string, string>
     */
    private static function readCssMeta(string $stylePath): array
    {
        if (!is_file($stylePath) || filesize($stylePath) > 262144) {
            return [];
        }

        $content = @file_get_contents($stylePath);
        if (!is_string($content) || $content === '') {
            return [];
        }

        $meta = [];
        foreach (['ThemeName', 'Description', 'Version'] as $key) {
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*[:=]\s*(.*?)\s*$/mi', $content, $matches) === 1) {
                $meta[strtolower($key)] = trim((string)($matches[1] ?? ''));
            }
        }

        return $meta;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private static function previewPath(string $scope, string $id, string $directory, array $manifest): ?string
    {
        $manifestPreview = trim((string)($manifest['preview'] ?? ''));
        if ($manifestPreview !== '') {
            return str_starts_with($manifestPreview, '/')
                ? $manifestPreview
                : '/themes/' . $scope . '/' . $id . '/' . ltrim($manifestPreview, '/');
        }

        foreach (['preview.svg', 'preview.webp', 'preview.png', 'screenshot.webp', 'screenshot.png'] as $candidate) {
            if (is_file($directory . DIRECTORY_SEPARATOR . $candidate)) {
                return '/themes/' . $scope . '/' . $id . '/' . $candidate;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $theme
     * @return array<string, mixed>|null
     */
    private static function fallbackTheme(array $theme): ?array
    {
        $scope = (string)($theme['scope'] ?? '');
        $definition = self::SCOPES[$scope] ?? null;
        if ($definition === null) {
            return null;
        }

        foreach (self::scopeThemes($scope) as $candidate) {
            if (($candidate['id'] ?? '') === ($theme['id'] ?? '')) {
                continue;
            }

            if (($candidate['id'] ?? '') === $definition['default_id']) {
                return $candidate;
            }
        }

        foreach (self::scopeThemes($scope) as $candidate) {
            if (($candidate['id'] ?? '') !== ($theme['id'] ?? '')) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array{exists: bool, file_count: int, directory_count: int, entry_count: int, size_bytes: int}
     */
    private static function directoryStats(string $absolutePath): array
    {
        if ($absolutePath === '' || !is_dir($absolutePath)) {
            return [
                'exists' => false,
                'file_count' => 0,
                'directory_count' => 0,
                'entry_count' => 0,
                'size_bytes' => 0,
            ];
        }

        $fileCount = 0;
        $directoryCount = 0;
        $sizeBytes = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absolutePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $directoryCount++;
                continue;
            }

            $fileCount++;
            $sizeBytes += max(0, (int)$item->getSize());
        }

        return [
            'exists' => true,
            'file_count' => $fileCount,
            'directory_count' => $directoryCount,
            'entry_count' => $fileCount + $directoryCount,
            'size_bytes' => $sizeBytes,
        ];
    }

    private static function deleteDirectory(string $absolutePath): void
    {
        $root = realpath(public_path() . DIRECTORY_SEPARATOR . 'themes');
        $path = realpath($absolutePath);
        if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('模板目录不在受管控范围内');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                if (!@rmdir($item->getPathname())) {
                    throw new \RuntimeException('删除模板目录失败：' . $item->getPathname());
                }
                continue;
            }

            if (!@unlink($item->getPathname())) {
                throw new \RuntimeException('删除模板文件失败：' . $item->getPathname());
            }
        }

        if (!@rmdir($path)) {
            throw new \RuntimeException('删除模板根目录失败');
        }
    }

    private static function persistConfigValue(string $key, string $value): void
    {
        $exists = Db::table('admin_config')
            ->where('config_name', $key)
            ->exists();

        if ($exists) {
            Db::table('admin_config')
                ->where('config_name', $key)
                ->update(['config_value' => $value]);
        } else {
            Db::table('admin_config')->insert([
                'config_name' => $key,
                'config_value' => $value,
            ]);
        }

        SystemConfig::clearCache();
    }

    private static function scopeRoot(string $scope): string
    {
        return public_path() . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $scope;
    }

    private static function normalizeThemeId(string $id): string
    {
        $normalized = strtolower(trim($id));
        if ($normalized === '' || preg_match('/^[a-z0-9-]+$/', $normalized) !== 1) {
            return '';
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $theme
     */
    private static function themeLabel(array $theme): string
    {
        $title = trim((string)($theme['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $id = trim((string)($theme['id'] ?? ''));
        return $id !== '' ? $id : '未命名模板';
    }
}
