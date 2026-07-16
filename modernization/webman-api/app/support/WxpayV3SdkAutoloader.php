<?php

declare(strict_types=1);

namespace app\support;

final class WxpayV3SdkAutoloader
{
    private const PREFIX = 'WeChatPay\\V3\\';

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

    public static function ensureRuntimeDirectory(int $accountId): string
    {
        $directory = runtime_path('payment-plugins/wxpay_v3/account-' . max(0, $accountId));
        if (is_dir($directory)) {
            return $directory;
        }

        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('failed to create wxpay v3 runtime directory');
        }

        return $directory;
    }

    private static function sourceRoot(): string
    {
        return base_path()
            . DIRECTORY_SEPARATOR . 'support'
            . DIRECTORY_SEPARATOR . 'wechatpay-sdk'
            . DIRECTORY_SEPARATOR . 'src'
            . DIRECTORY_SEPARATOR . 'V3'
            . DIRECTORY_SEPARATOR;
    }
}
