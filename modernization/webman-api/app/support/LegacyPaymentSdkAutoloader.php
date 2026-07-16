<?php

declare(strict_types=1);

namespace app\support;

final class LegacyPaymentSdkAutoloader
{
    private const PREFIX = 'iboxs\\payment\\';

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        spl_autoload_register(static function (string $class): void {
            if (!str_starts_with($class, self::PREFIX)) {
                return;
            }

            $relative = substr($class, strlen(self::PREFIX));
            if ($relative === false || $relative === '') {
                return;
            }

            $file = self::sourceRoot() . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });

        self::$registered = true;
    }

    public static function sourceRoot(): string
    {
        return dirname(base_path(), 2)
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'iboxs'
            . DIRECTORY_SEPARATOR . 'payment'
            . DIRECTORY_SEPARATOR . 'src'
            . DIRECTORY_SEPARATOR;
    }
}
