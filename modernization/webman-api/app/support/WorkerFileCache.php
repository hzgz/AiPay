<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\support;

final class WorkerFileCache
{
    private const MAX_TEXT_FILES = 256;

    /**
     * @var array<string, array{mtime:int,size:int,content:string}>
     */
    private static array $textFiles = [];

    private function __construct()
    {
    }

    public static function readText(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $resolvedPath = realpath($path) ?: $path;
        $mtime = (int)(@filemtime($resolvedPath) ?: 0);
        $size = (int)(@filesize($resolvedPath) ?: 0);
        $cacheKey = str_replace('\\', '/', $resolvedPath);
        $cached = self::$textFiles[$cacheKey] ?? null;

        if (
            is_array($cached)
            && ($cached['mtime'] ?? -1) === $mtime
            && ($cached['size'] ?? -1) === $size
        ) {
            return $cached['content'] ?? null;
        }

        $content = @file_get_contents($resolvedPath);
        if (!is_string($content) || $content === '') {
            unset(self::$textFiles[$cacheKey]);

            return null;
        }

        if (!isset(self::$textFiles[$cacheKey]) && count(self::$textFiles) >= self::MAX_TEXT_FILES) {
            $oldestKey = array_key_first(self::$textFiles);
            if ($oldestKey !== null) {
                unset(self::$textFiles[$oldestKey]);
            }
        }

        self::$textFiles[$cacheKey] = [
            'mtime' => $mtime,
            'size' => $size,
            'content' => $content,
        ];

        return $content;
    }

    public static function clear(): void
    {
        self::$textFiles = [];
    }
}
