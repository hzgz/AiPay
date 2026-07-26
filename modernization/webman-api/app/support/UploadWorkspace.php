<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\support;

final class UploadWorkspace
{
    private const WORKSPACE_ROOT = 'upload-assets';

    public static function rootPath(): string
    {
        return self::workspaceRoot();
    }

    public static function directoryPath(string $directory): string
    {
        $normalizedDirectory = self::normalizeDirectoryName($directory);
        if ($normalizedDirectory === null) {
            throw new \InvalidArgumentException('上传目录标识不合法');
        }

        $directoryPath = self::rootPath() . DIRECTORY_SEPARATOR . $normalizedDirectory;
        self::ensureDirectory($directoryPath);

        return realpath($directoryPath) ?: $directoryPath;
    }

    public static function publicHref(string $directory, string $relativeChild = ''): string
    {
        $normalizedDirectory = self::normalizeDirectoryName($directory);
        if ($normalizedDirectory === null) {
            throw new \InvalidArgumentException('上传目录标识不合法');
        }

        $href = '/upload/' . $normalizedDirectory;
        $normalizedChild = trim(str_replace('\\', '/', $relativeChild), '/');

        return $normalizedChild === '' ? $href : $href . '/' . $normalizedChild;
    }

    public static function resolveAssetPath(string $relativePath): ?string    {
        $normalizedPath = self::normalizeRelativeAssetPath($relativePath);
        if ($normalizedPath === null) {
            return null;
        }

        $absolutePath = self::rootPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
        $realPath = realpath($absolutePath);
        if (!$realPath || !self::isWithinDirectory($realPath, self::rootPath())) {
            return null;
        }

        return $realPath;
    }

    private static function workspaceRoot(): string
    {
        $root = self::projectRoot() . DIRECTORY_SEPARATOR . self::WORKSPACE_ROOT;
        self::ensureDirectory($root);

        return realpath($root) ?: $root;
    }

    private static function projectRoot(): string
    {
        if (function_exists('base_path')) {
            return base_path();
        }

        return dirname(__DIR__, 2);
    }

    private static function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    private static function normalizeDirectoryName(string $directory): ?string    {
        $normalized = trim(str_replace('\\', '/', $directory), '/');

        if ($normalized === '' || preg_match('/^[A-Za-z0-9_-]+$/', $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }

    private static function normalizeRelativeAssetPath(string $relativePath): ?string
    {
        $normalized = trim(str_replace('\\', '/', $relativePath), '/');
        if ($normalized === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $normalized), static fn(string $segment): bool => $segment !== ''));
        if ($segments === []) {
            return null;
        }

        foreach ($segments as $index => $segment) {
            if ($segment === '.' || $segment === '..' || str_contains($segment, "\0")) {
                return null;
            }

            $pattern = $index === 0 ? '/^[A-Za-z0-9_-]+$/' : '/^[A-Za-z0-9._-]+$/';
            if (preg_match($pattern, $segment) !== 1) {
                return null;
            }
        }

        return implode('/', $segments);
    }

    private static function isWithinDirectory(string $path, string $directory): bool
    {
        $normalizedPath = strtolower(str_replace('\\', '/', $path));
        $normalizedDirectory = rtrim(strtolower(str_replace('\\', '/', $directory)), '/');

        return $normalizedPath === $normalizedDirectory
            || str_starts_with($normalizedPath, $normalizedDirectory . '/');
    }
}
