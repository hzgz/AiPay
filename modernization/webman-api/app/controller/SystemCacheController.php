<?php

declare(strict_types=1);

namespace app\controller;

use app\support\AdminRouteAuthorization;
use app\support\ApiResponse;
use app\support\HotPathStore;
use app\support\RequestPayload;
use app\support\SystemConfig;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class SystemCacheController
{
    private const TARGETS = [
        'runtime_cache' => [
            'type' => 'directory',
            'title' => '运行缓存',
            'relative_path' => 'runtime/cache',
            'description' => '系统运行时缓存目录，清理后会在后续请求中自动重建。',
        ],
        'runtime_views' => [
            'type' => 'directory',
            'title' => '模板编译缓存',
            'relative_path' => 'runtime/views',
            'description' => '服务端模板编译输出目录，清理后会在下次访问时重新生成。',
        ],
        'hot_path_store' => [
            'type' => 'directory',
            'title' => '热路径文件缓存',
            'relative_path' => 'runtime/hot-path-store',
            'description' => 'HotPathStore 的文件兜底目录，Redis 不可用时会回退到这里。',
        ],
        'hot_path_redis' => [
            'type' => 'redis',
            'title' => 'Redis 热缓存',
            'relative_path' => 'redis',
            'description' => '只清理 HotPathStore 使用的 Redis 前缀，不会清空整个 Redis 数据库。',
        ],
    ];

    private const BROWSER_HINTS = [
        'local_storage_prefix' => 'sys-v',
        'local_storage_keys' => [
            'sys-version',
            'sys-theme',
            'sys-last-user-id',
            'sys-responsive-menu-type',
        ],
        'session_storage_keys' => [
            'iframeRoutes',
            '__art_chunk_reload_once__',
        ],
        'note' => '前端缓存只能清理当前管理员正在使用的浏览器，不会远程清理其他用户的浏览器缓存。',
    ];

    public function audit(Request $request): Response
    {
        return ApiResponse::success($this->buildAuditPayload());
    }

    public function cleanup(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'execute');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);
        $requestedKeys = $this->normalizeTargetKeys((array)($payload['targets'] ?? []));
        $targetKeys = $requestedKeys === [] ? array_keys(self::TARGETS) : $requestedKeys;
        $invalidKeys = array_values(array_diff($targetKeys, array_keys(self::TARGETS)));
        if ($invalidKeys !== []) {
            return ApiResponse::error('system cache targets are invalid', 422, [
                'invalid_targets' => $invalidKeys,
            ], 422);
        }

        $results = [];
        $warnings = [];
        $removedFileCount = 0;
        $removedDirectoryCount = 0;
        $removedKeyCount = 0;
        $releasedSizeBytes = 0;

        foreach ($targetKeys as $key) {
            $definition = self::TARGETS[$key];
            $before = $this->inspectTarget($key, $definition);
            $cleanup = $this->clearTarget($key, $definition);
            $after = $this->inspectTarget($key, $definition);

            $removedFileCount += (int)($cleanup['removed_file_count'] ?? 0);
            $removedDirectoryCount += (int)($cleanup['removed_directory_count'] ?? 0);
            $removedKeyCount += (int)($cleanup['removed_key_count'] ?? 0);
            $releasedSizeBytes += (int)($cleanup['released_size_bytes'] ?? 0);
            $warnings = array_merge($warnings, (array)($cleanup['errors'] ?? []));

            $results[] = [
                'key' => $key,
                'title' => (string)$definition['title'],
                'relative_path' => (string)($before['relative_path'] ?? $definition['relative_path']),
                'before' => $before,
                'after' => $after,
                'removed_file_count' => (int)($cleanup['removed_file_count'] ?? 0),
                'removed_directory_count' => (int)($cleanup['removed_directory_count'] ?? 0),
                'removed_key_count' => (int)($cleanup['removed_key_count'] ?? 0),
                'released_size_bytes' => (int)($cleanup['released_size_bytes'] ?? 0),
                'released_size_label' => $this->formatBytes((int)($cleanup['released_size_bytes'] ?? 0)),
                'errors' => array_values(array_unique(array_filter((array)($cleanup['errors'] ?? [])))),
            ];
        }

        if ($this->requiresHotPathReset($targetKeys)) {
            SystemConfig::clearCache();
        }

        $warnings = array_values(array_unique(array_filter($warnings)));
        $audit = $this->buildAuditPayload();

        $this->recordAdminCleanupExecution(
            $request,
            $targetKeys,
            $removedFileCount,
            $removedDirectoryCount,
            $removedKeyCount,
            $releasedSizeBytes,
            $warnings
        );

        return ApiResponse::success([
            'target_keys' => $targetKeys,
            'cleared_target_count' => count($targetKeys),
            'removed_file_count' => $removedFileCount,
            'removed_directory_count' => $removedDirectoryCount,
            'removed_key_count' => $removedKeyCount,
            'released_size_bytes' => $releasedSizeBytes,
            'released_size_label' => $this->formatBytes($releasedSizeBytes),
            'results' => $results,
            'warnings' => $warnings,
            'audit' => $audit,
        ], 'system cache cleaned');
    }

    private function buildAuditPayload(): array
    {
        $targets = [];
        foreach (self::TARGETS as $key => $definition) {
            $targets[] = $this->inspectTarget($key, $definition);
        }

        return [
            'server_targets' => $targets,
            'server_summary' => $this->buildSummary($targets),
            'browser_hints' => self::BROWSER_HINTS,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function buildSummary(array $targets): array
    {
        $clearableTargetCount = count(array_filter(
            $targets,
            static fn (array $target): bool => !empty($target['clearable'])
        ));
        $fileCount = array_reduce(
            $targets,
            static fn (int $carry, array $target): int => $carry + (int)($target['file_count'] ?? 0),
            0
        );
        $directoryCount = array_reduce(
            $targets,
            static fn (int $carry, array $target): int => $carry + (int)($target['directory_count'] ?? 0),
            0
        );
        $entryCount = array_reduce(
            $targets,
            static fn (int $carry, array $target): int => $carry + (int)($target['entry_count'] ?? 0),
            0
        );
        $sizeBytes = array_reduce(
            $targets,
            static fn (int $carry, array $target): int => $carry + (int)($target['size_bytes'] ?? 0),
            0
        );

        return [
            'target_count' => count($targets),
            'clearable_target_count' => $clearableTargetCount,
            'file_count' => $fileCount,
            'directory_count' => $directoryCount,
            'entry_count' => $entryCount,
            'size_bytes' => $sizeBytes,
            'size_label' => $this->formatBytes($sizeBytes),
        ];
    }

    private function inspectTarget(string $key, array $definition): array
    {
        $type = (string)($definition['type'] ?? 'directory');
        if ($type === 'redis') {
            return $this->inspectRedisTarget($key, $definition);
        }

        return $this->inspectDirectoryTarget($key, $definition);
    }

    private function inspectDirectoryTarget(string $key, array $definition): array
    {
        $absolutePath = $this->resolveTargetPath((string)$definition['relative_path']);
        $stats = $this->inspectDirectory($absolutePath);
        $isHotPathFallback = $key === 'hot_path_store';
        $fallbackEnabled = !$isHotPathFallback || HotPathStore::fileFallbackEnabled();
        $title = (string)$definition['title'];
        $description = (string)$definition['description'];
        $relativePath = (string)$definition['relative_path'];

        if ($isHotPathFallback) {
            $title = 'Hot Path File Fallback';
        }

        if ($isHotPathFallback && !$fallbackEnabled) {
            $description = 'Disk fallback is disabled. Any files shown here are leftover artifacts and are safe to clear.';
            $relativePath = 'disabled';
        }

        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'relative_path' => $relativePath,
            'exists' => is_dir($absolutePath),
            'clearable' => (int)$stats['entry_count'] > 0,
            'file_count' => (int)$stats['file_count'],
            'directory_count' => (int)$stats['directory_count'],
            'entry_count' => (int)$stats['entry_count'],
            'size_bytes' => (int)$stats['size_bytes'],
            'size_label' => (string)$stats['size_label'],
            'fallback_enabled' => $fallbackEnabled,
        ];
    }

    private function inspectRedisTarget(string $key, array $definition): array
    {
        $stats = HotPathStore::inspectRedisPrefix();
        $keyCount = (int)($stats['key_count'] ?? 0);
        $sizeBytes = (int)($stats['size_bytes'] ?? 0);
        $title = $key === 'hot_path_redis' ? 'Hot Path Redis Cache' : (string)$definition['title'];
        $description = $key === 'hot_path_redis'
            ? 'Clears only the Redis prefix used by HotPathStore and does not wipe the whole Redis database.'
            : (string)$definition['description'];

        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'relative_path' => (string)($stats['relative_path'] ?? HotPathStore::redisTargetPath()),
            'exists' => (bool)($stats['available'] ?? false),
            'clearable' => $keyCount > 0,
            'file_count' => $keyCount,
            'directory_count' => 0,
            'entry_count' => $keyCount,
            'size_bytes' => $sizeBytes,
            'size_label' => $this->formatBytes($sizeBytes),
        ];
    }

    private function inspectDirectory(string $directory): array
    {
        if (!is_dir($directory)) {
            return [
                'file_count' => 0,
                'directory_count' => 0,
                'entry_count' => 0,
                'size_bytes' => 0,
                'size_label' => '0 B',
            ];
        }

        $fileCount = 0;
        $directoryCount = 0;
        $sizeBytes = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                $directoryCount++;
                continue;
            }

            $fileCount++;
            if ($item->isFile()) {
                $sizeBytes += max(0, (int)$item->getSize());
            }
        }

        return [
            'file_count' => $fileCount,
            'directory_count' => $directoryCount,
            'entry_count' => $fileCount + $directoryCount,
            'size_bytes' => $sizeBytes,
            'size_label' => $this->formatBytes($sizeBytes),
        ];
    }

    private function clearTarget(string $key, array $definition): array
    {
        $type = (string)($definition['type'] ?? 'directory');
        if ($type === 'redis') {
            return $this->clearRedisTarget();
        }

        return $this->clearDirectoryTarget((string)$definition['relative_path']);
    }

    private function clearDirectoryTarget(string $relativePath): array
    {
        $absolutePath = $this->resolveTargetPath($relativePath);
        if (!is_dir($absolutePath)) {
            @mkdir($absolutePath, 0777, true);

            return [
                'removed_file_count' => 0,
                'removed_directory_count' => 0,
                'removed_key_count' => 0,
                'released_size_bytes' => 0,
                'errors' => [],
            ];
        }

        $removedFileCount = 0;
        $removedDirectoryCount = 0;
        $releasedSizeBytes = 0;
        $errors = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $pathname = $item->getPathname();

            try {
                if ($item->isDir() && !$item->isLink()) {
                    if (!@rmdir($pathname) && is_dir($pathname)) {
                        throw new \RuntimeException('directory removal failed');
                    }

                    $removedDirectoryCount++;
                    continue;
                }

                if ($item->isFile()) {
                    $releasedSizeBytes += max(0, (int)$item->getSize());
                }

                if (!@unlink($pathname) && file_exists($pathname)) {
                    throw new \RuntimeException('file removal failed');
                }

                $removedFileCount++;
            } catch (\Throwable $exception) {
                $errors[] = sprintf('%s cleanup failed', str_replace('\\', '/', $pathname));
            }
        }

        clearstatcache();
        @mkdir($absolutePath, 0777, true);

        return [
            'removed_file_count' => $removedFileCount,
            'removed_directory_count' => $removedDirectoryCount,
            'removed_key_count' => 0,
            'released_size_bytes' => $releasedSizeBytes,
            'errors' => $errors,
        ];
    }

    private function clearRedisTarget(): array
    {
        $cleanup = HotPathStore::clearRedisPrefix();

        return [
            'removed_file_count' => 0,
            'removed_directory_count' => 0,
            'removed_key_count' => (int)($cleanup['removed_key_count'] ?? 0),
            'released_size_bytes' => (int)($cleanup['released_size_bytes'] ?? 0),
            'errors' => array_values(array_unique(array_filter((array)($cleanup['errors'] ?? [])))),
        ];
    }

    private function resolveTargetPath(string $relativePath): string
    {
        $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($relativePath));
        $runtimeRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)runtime_path()), DIRECTORY_SEPARATOR);

        if ($relativePath === 'runtime') {
            return $runtimeRoot;
        }

        $runtimePrefix = 'runtime' . DIRECTORY_SEPARATOR;
        if (str_starts_with($relativePath, $runtimePrefix)) {
            return $runtimeRoot . DIRECTORY_SEPARATOR . substr($relativePath, strlen($runtimePrefix));
        }

        return $runtimeRoot . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR);
    }

    private function normalizeTargetKeys(array $targetKeys): array
    {
        $normalized = array_map(
            static fn ($value): string => trim((string)$value),
            $targetKeys
        );

        return array_values(array_unique(array_filter($normalized, static fn (string $key): bool => $key !== '')));
    }

    private function requiresHotPathReset(array $targetKeys): bool
    {
        return in_array('hot_path_store', $targetKeys, true)
            || in_array('hot_path_redis', $targetKeys, true);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float)$bytes;
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return sprintf(
            $value >= 10 || $index === 0 ? '%.0f %s' : '%.1f %s',
            $value,
            $units[$index]
        );
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'SystemCleanupAudit', $authMark);
    }

    private function adminIdFromRequest(Request $request): int
    {
        return (int)(((array)($request->admin ?? []))['id'] ?? 0);
    }

    private function recordAdminCleanupExecution(
        Request $request,
        array $targetKeys,
        int $removedFileCount,
        int $removedDirectoryCount,
        int $removedKeyCount,
        int $releasedSizeBytes,
        array $warnings
    ): void {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        try {
            Db::table('admin_admin_log')->insert([
                'uid' => $adminId,
                'url' => '/api/admin/system-cache/server/cleanup',
                'desc' => sprintf(
                    'system cache cleanup targets=%s removed_files=%d removed_directories=%d removed_keys=%d released=%s warnings=%d',
                    implode(',', $targetKeys),
                    $removedFileCount,
                    $removedDirectoryCount,
                    $removedKeyCount,
                    $this->formatBytes($releasedSizeBytes),
                    count($warnings)
                ),
                'ip' => (string)$request->getRealIp(),
                'user_agent' => (string)$request->header('user-agent', ''),
                'create_time' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
        }
    }
}
