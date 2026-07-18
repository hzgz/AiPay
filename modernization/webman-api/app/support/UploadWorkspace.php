<?php

declare(strict_types=1);

namespace app\support;

final class UploadWorkspace
{
    private const WORKSPACE_ROOT = 'upload-assets';
    private const BOOTSTRAP_MARKER = '.workspace-ready';

    public static function rootPath(): string
    {
        $root = self::workspaceRoot();
        self::bootstrapFromLegacy($root);

        return $root;
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

    public static function legacyPublicPath(string $directory, string $relativeChild = ''): string
    {
        $normalizedDirectory = self::normalizeDirectoryName($directory);
        if ($normalizedDirectory === null) {
            throw new \InvalidArgumentException('upload directory identifier is invalid');
        }

        $path = self::legacyUploadRoot() . DIRECTORY_SEPARATOR . $normalizedDirectory;
        $normalizedChild = trim(str_replace('\\', '/', $relativeChild), '/');

        return $normalizedChild === ''
            ? $path
            : $path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedChild);
    }

    public static function mirrorFileToLegacyPublic(string $workspaceAbsolutePath, string $directory, string $relativeChild = ''): string
    {
        if (!is_file($workspaceAbsolutePath)) {
            throw new \RuntimeException('工作区上传文件不存在');
        }

        $legacyPath = self::legacyPublicPath($directory, $relativeChild);
        self::ensureDirectory(dirname($legacyPath));

        if (!@copy($workspaceAbsolutePath, $legacyPath) && !is_file($legacyPath)) {
            throw new \RuntimeException('同步上传文件到公开镜像目录失败');
        }

        return $legacyPath;
    }

    public static function deleteLegacyPublicFile(string $directory, string $relativeChild = ''): void
    {
        $legacyPath = self::legacyPublicPath($directory, $relativeChild);
        if (is_file($legacyPath)) {
            @unlink($legacyPath);
        }
    }

    public static function deleteLegacyPublicDirectory(string $directory): void
    {
        $legacyPath = self::legacyPublicPath($directory);
        if (!is_dir($legacyPath)) {
            return;
        }

        self::deleteDirectoryRecursively($legacyPath);
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

    private static function bootstrapFromLegacy(string $workspaceRoot): void
    {
        $markerPath = $workspaceRoot . DIRECTORY_SEPARATOR . self::BOOTSTRAP_MARKER;
        if (is_file($markerPath)) {
            return;
        }

        $legacyRoot = self::legacyUploadRoot();
        $legacyRealPath = realpath($legacyRoot);
        if ($legacyRealPath && is_dir($legacyRealPath)) {
            self::copyDirectoryRecursively($legacyRealPath, $workspaceRoot);
        }

        @file_put_contents($markerPath, date('Y-m-d H:i:s'));
    }

    private static function projectRoot(): string
    {
        if (function_exists('base_path')) {
            return base_path();
        }

        return dirname(__DIR__, 2);
    }

    private static function legacyUploadRoot(): string
    {
        if (function_exists('public_path')) {
            return public_path() . DIRECTORY_SEPARATOR . 'upload';
        }

        return self::projectRoot() . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'upload';
    }

    private static function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    private static function copyDirectoryRecursively(string $sourceDirectory, string $targetDirectory): void
    {
        self::ensureDirectory($targetDirectory);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $sourcePath = $item->getPathname();
            $relativePath = substr($sourcePath, strlen($sourceDirectory) + 1);
            if ($relativePath === false || $relativePath === '') {
                continue;
            }

            $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . $relativePath;
            if ($item->isDir()) {
                self::ensureDirectory($targetPath);
                continue;
            }

            self::ensureDirectory(dirname($targetPath));
            copy($sourcePath, $targetPath);
        }
    }

    private static function deleteDirectoryRecursively(string $directoryPath): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directoryPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
                continue;
            }

            @unlink($item->getPathname());
        }

        @rmdir($directoryPath);
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
