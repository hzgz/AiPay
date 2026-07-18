<?php

declare(strict_types=1);

namespace app\support;

final class WxpayV3SdkAutoloader
{
    private const PREFIX = 'WeChatPay\\V3\\';
    private const FALLBACK_RUNTIME_ROOT = 'aipay-runtime';

    private static bool $registered = false;
    /** @var array<int, string> */
    private static array $resolvedRuntimeDirectories = [];

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
        $accountId = max(0, $accountId);
        if (isset(self::$resolvedRuntimeDirectories[$accountId])) {
            return self::$resolvedRuntimeDirectories[$accountId];
        }

        $failures = [];
        foreach (self::runtimeDirectoryCandidates($accountId) as $directory) {
            if (self::prepareWritableDirectory($directory, $failures)) {
                self::$resolvedRuntimeDirectories[$accountId] = $directory;

                return $directory;
            }
        }

        throw new \RuntimeException(
            'wxpay_v3 runtime directory is not writable: ' . implode('; ', array_unique($failures))
        );
    }

    public static function writeRuntimeFile(int $accountId, string $fileName, string $contents): string
    {
        $directory = self::ensureRuntimeDirectory($accountId);
        $path = $directory . DIRECTORY_SEPARATOR . ltrim($fileName, DIRECTORY_SEPARATOR);
        $parent = dirname($path);
        $failures = [];
        if (!self::prepareWritableDirectory($parent, $failures)) {
            throw new \RuntimeException(
                'wxpay_v3 runtime parent directory is not writable: '
                . implode('; ', array_unique($failures))
            );
        }

        if (@file_put_contents($path, $contents, LOCK_EX) === false) {
            $error = error_get_last();
            $message = is_array($error) ? (string)($error['message'] ?? '') : '';
            throw new \RuntimeException(
                sprintf('failed to write wxpay_v3 runtime file: %s%s', $path, $message !== '' ? " ({$message})" : '')
            );
        }

        @chmod($path, 0664);

        return $path;
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

    /**
     * @return array<int, string>
     */
    private static function runtimeDirectoryCandidates(int $accountId): array
    {
        $accountDirectory = 'account-' . $accountId;

        return [
            runtime_path('payment-plugins/wxpay_v3/' . $accountDirectory),
            rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . self::FALLBACK_RUNTIME_ROOT
                . DIRECTORY_SEPARATOR . 'payment-plugins'
                . DIRECTORY_SEPARATOR . 'wxpay_v3'
                . DIRECTORY_SEPARATOR . $accountDirectory,
        ];
    }

    /**
     * @param array<int, string> $failures
     */
    private static function prepareWritableDirectory(string $directory, array &$failures): bool
    {
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            $error = error_get_last();
            $failures[] = sprintf(
                '%s mkdir failed%s',
                $directory,
                is_array($error) && ($error['message'] ?? '') !== '' ? ': ' . $error['message'] : ''
            );

            return false;
        }

        @chmod($directory, 0777);

        if (!is_writable($directory)) {
            $failures[] = sprintf('%s is not writable', $directory);

            return false;
        }

        $probe = $directory . DIRECTORY_SEPARATOR . '.write-probe-' . bin2hex(random_bytes(4));
        if (@file_put_contents($probe, 'ok', LOCK_EX) === false) {
            $error = error_get_last();
            $failures[] = sprintf(
                '%s probe write failed%s',
                $directory,
                is_array($error) && ($error['message'] ?? '') !== '' ? ': ' . $error['message'] : ''
            );

            return false;
        }

        @unlink($probe);

        return true;
    }
}
