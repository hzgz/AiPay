<?php

declare(strict_types=1);

namespace app\support;

final class ProductionSecurity
{
    private const PRODUCTION_ENVS = ['prod', 'production'];
    private const WEAK_ADMIN_USERNAMES = ['admin', 'administrator', 'root', 'test'];
    private const WEAK_ADMIN_PASSWORDS = [
        '123456',
        '123123',
        '12345678',
        'admin',
        'admin123',
        'password',
        'root',
        'a123456',
    ];
    private static ?array $envFileCache = null;

    public static function appEnv(): string
    {
        return strtolower(trim((string)(self::envValue('APP_ENV') ?? '')));
    }

    public static function isProductionLike(): bool
    {
        return in_array(self::appEnv(), self::PRODUCTION_ENVS, true);
    }

    public static function debugAssistAllowed(): bool
    {
        return !self::isProductionLike();
    }

    public static function demoRoutesAllowed(): bool
    {
        return !self::isProductionLike();
    }

    public static function isWeakAdminCredentialAttempt(string $username, string $password): bool
    {
        $normalizedUsername = strtolower(trim($username));
        $normalizedPassword = trim($password);

        if ($normalizedUsername === '' || $normalizedPassword === '') {
            return false;
        }

        return in_array($normalizedUsername, self::WEAK_ADMIN_USERNAMES, true)
            && in_array($normalizedPassword, self::WEAK_ADMIN_PASSWORDS, true);
    }

    public static function weakAdminUsernames(): array
    {
        return self::WEAK_ADMIN_USERNAMES;
    }

    public static function weakAdminPasswords(): array
    {
        return self::WEAK_ADMIN_PASSWORDS;
    }

    private static function envValue(string $key): ?string
    {
        $value = getenv($key);
        if ($value !== false) {
            return (string)$value;
        }

        if (array_key_exists($key, $_ENV)) {
            return is_scalar($_ENV[$key]) ? (string)$_ENV[$key] : null;
        }

        if (array_key_exists($key, $_SERVER)) {
            return is_scalar($_SERVER[$key]) ? (string)$_SERVER[$key] : null;
        }

        return self::envFileValue($key);
    }

    private static function envFileValue(string $key): ?string
    {
        if (self::$envFileCache === null) {
            self::$envFileCache = [];
            $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
            if (is_file($path)) {
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
            }
        }

        return array_key_exists($key, self::$envFileCache) ? self::$envFileCache[$key] : null;
    }
}
