<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\controller;

use app\support\AdminRouteAuthorization;
use app\support\AdminThemeFormatter;
use app\support\ApiResponse;
use app\support\RequestPayload;
use app\support\ThemeCatalog;
use Webman\Http\Request;
use Webman\Http\Response;

final class ThemeController
{
    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));
        $scope = ThemeCatalog::normalizeScope((string)$request->get('scope', ''));
        $keyword = trim((string)$request->get('keyword', ''));
        $status = trim((string)$request->get('status', ''));

        $themes = ThemeCatalog::filteredThemes($scope, $keyword, $status);
        $total = count($themes);
        $records = array_slice($themes, ($current - 1) * $size, $size);

        return ApiResponse::success([
            'records' => array_map(fn (array $theme): array => $this->formatTheme($theme, $request), $records),
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => ThemeCatalog::summary($themes),
            'scope_options' => ThemeCatalog::scopeOptions(),
        ]);
    }

    public function show(Request $request): Response
    {
        $theme = $this->themeFromRequest($request);
        if ($theme === null) {
            return ApiResponse::error('模板不存在', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => $this->formatTheme($theme, $request),
        ]);
    }

    public function activate(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $theme = $this->themeFromRequest($request);
        if ($theme === null) {
            return ApiResponse::error('模板不存在', 404, null, 404);
        }

        try {
            $result = ThemeCatalog::activateTheme((string)$theme['scope'], (string)$theme['id']);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $item = (array)($result['item'] ?? $theme);
        $definition = ThemeCatalog::scopeDefinitions()[(string)$item['scope']];

        return ApiResponse::success([
            'item' => $this->formatTheme($item, $request),
            'activated_scope' => (string)$item['scope'],
            'activated_scope_label' => $definition['label'],
            'activated_theme_id' => (string)$item['id'],
            'activated_theme_label' => $this->themeLabel($item),
            'config_key' => (string)($result['config_key'] ?? $definition['config_key']),
            'config_value' => (string)$item['id'],
            'previous_theme_id' => $result['previous_theme_id'] ?? null,
            'previous_theme_label' => $result['previous_theme_label'] ?? null,
        ], '模板已启用');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $theme = $this->themeFromRequest($request);
        if ($theme === null) {
            return ApiResponse::error('模板不存在', 404, null, 404);
        }

        try {
            $audit = ThemeCatalog::buildDeleteAudit((string)$theme['scope'], (string)$theme['id']);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success([
            'item' => $this->formatTheme($theme, $request),
            'audit' => $audit,
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $theme = $this->themeFromRequest($request);
        if ($theme === null) {
            return ApiResponse::error('模板不存在', 404, null, 404);
        }

        $payload = RequestPayload::all($request);
        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));

        try {
            $audit = ThemeCatalog::buildDeleteAudit((string)$theme['scope'], (string)$theme['id']);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('确认短语不匹配', 422, ['audit' => $audit], 422);
        }

        try {
            $result = ThemeCatalog::deleteTheme((string)$theme['scope'], (string)$theme['id']);
        } catch (\RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, ['audit' => $audit], 422);
        } catch (\Throwable $exception) {
            return ApiResponse::error('模板删除失败：' . $exception->getMessage(), 500, ['audit' => $audit], 500);
        }

        $definition = ThemeCatalog::scopeDefinitions()[(string)$theme['scope']];

        return ApiResponse::success([
            'deleted_scope' => (string)$theme['scope'],
            'deleted_scope_label' => $definition['label'],
            'deleted_theme_id' => (string)$theme['id'],
            'deleted_theme_label' => $this->themeLabel($theme),
            'fallback_theme_id' => $result['fallback_theme_id'] ?: null,
            'fallback_theme_label' => $result['fallback_theme_label'] ?: null,
            'config_key' => $result['config_key'] ?: null,
            'audit' => $audit,
        ], '模板已删除');
    }

    /**
     * @param array<string, mixed> $theme
     * @return array<string, mixed>
     */
    private function formatTheme(array $theme, Request $request): array
    {
        $resolved = $theme;
        foreach (['asset_path', 'style_path', 'screenshot_path'] as $key) {
            $value = $resolved[$key] ?? null;
            if (!is_string($value) || $value === '') {
                continue;
            }

            $resolved[$key] = $this->absoluteUrl($request, $value);
        }

        return AdminThemeFormatter::format($resolved);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function themeFromRequest(Request $request): ?array
    {
        $scope = ThemeCatalog::normalizeScope((string)($request->route ? $request->route->param('scope', '') : ''));
        $id = trim((string)($request->route ? $request->route->param('id', '') : ''));
        if ($scope === null || $id === '') {
            return null;
        }

        return ThemeCatalog::findTheme($scope, $id);
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'ContentThemes', $authMark);
    }

    /**
     * @param array<string, mixed> $theme
     */
    private function themeLabel(array $theme): string
    {
        $title = trim((string)($theme['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        return trim((string)($theme['id'] ?? '')) ?: '未命名模板';
    }

    private function absoluteUrl(Request $request, string $path): string
    {
        if ($path === '' || preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $scheme = $this->requestScheme($request);

        $host = trim((string)$request->header('host'));
        if ($host === '') {
            return $path;
        }

        return $scheme . '://' . $host . '/' . ltrim($path, '/');
    }

    private function requestScheme(Request $request): string
    {
        $forwardedProto = strtolower(trim((string)$request->header('x-forwarded-proto', '')));
        if ($forwardedProto !== '') {
            $proto = trim((string)(explode(',', $forwardedProto)[0] ?? ''));
            if (in_array($proto, ['http', 'https'], true)) {
                return $proto;
            }
        }

        if (in_array(strtolower(trim((string)$request->header('x-forwarded-scheme', ''))), ['http', 'https'], true)) {
            return strtolower(trim((string)$request->header('x-forwarded-scheme', '')));
        }

        if (in_array(strtolower(trim((string)$request->header('x-forwarded-ssl', ''))), ['on', '1'], true)) {
            return 'https';
        }

        if ((string)$request->header('front-end-https', '') === 'on') {
            return 'https';
        }

        return 'http';
    }
}
