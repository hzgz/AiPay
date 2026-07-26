<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\support;

final class Environment
{
    private static ?array $envFileCache = null;

    private function __construct()
    {
    }

    public static function string(string $key, string $default = ''): string
    {
        [$exists, $value] = self::lookup($key);

        return $exists ? (string)$value : $default;
    }

    public static function int(string $key, int $default): int
    {
        [$exists, $value] = self::lookup($key);
        if (!$exists) {
            return $default;
        }

        $normalized = trim((string)$value);
        if ($normalized === '' || !is_numeric($normalized)) {
            return $default;
        }

        return (int)$normalized;
    }

    public static function float(string $key, float $default): float
    {
        [$exists, $value] = self::lookup($key);
        if (!$exists) {
            return $default;
        }

        $normalized = trim((string)$value);
        if ($normalized === '' || !is_numeric($normalized)) {
            return $default;
        }

        return (float)$normalized;
    }

    public static function bool(string $key, bool $default): bool
    {
        [$exists, $value] = self::lookup($key);
        if (!$exists) {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $parsed ?? $default;
    }

    private static function lookup(string $key): array
    {
        $value = getenv($key);
        if ($value !== false) {
            return [true, (string)$value];
        }

        if (array_key_exists($key, $_ENV) && is_scalar($_ENV[$key])) {
            return [true, (string)$_ENV[$key]];
        }

        if (array_key_exists($key, $_SERVER) && is_scalar($_SERVER[$key])) {
            return [true, (string)$_SERVER[$key]];
        }

        $cache = self::envFileCache();
        if (array_key_exists($key, $cache)) {
            return [true, (string)$cache[$key]];
        }

        return [false, null];
    }

    private static function envFileCache(): array
    {
        if (self::$envFileCache !== null) {
            return self::$envFileCache;
        }

        self::$envFileCache = [];
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($path)) {
            return self::$envFileCache;
        }

        foreach ((array)file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim((string)$line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $rawValue] = explode('=', $line, 2);
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $value = trim($rawValue);
            $length = strlen($value);
            if ($length >= 2) {
                $first = $value[0];
                $last = $value[$length - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            self::$envFileCache[$name] = $value;
        }

        return self::$envFileCache;
    }
}
