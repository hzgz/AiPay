<?php

declare(strict_types=1);

namespace Plugins\Payments\Shared\Support;

final class PaymentPluginAutoloader
{
    private const PLUGIN_CLASS_PREFIX = 'Plugins\\Payments\\';
    private const SHARED_CLASS_PREFIX = 'Plugins\\Payments\\Shared\\';
    private const SHARED_SOURCE_DIRECTORY = 'plugins/payments/_shared/src/';

    private static bool $registered = false;

    /** @var array<string, string>|null */
    private static ?array $prefixMap = null;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        spl_autoload_register([self::class, 'autoload'], true, true);
        self::$registered = true;
    }

    private static function autoload(string $className): void
    {
        foreach (self::prefixMap() as $prefix => $directory) {
            if (!str_starts_with($className, $prefix)) {
                continue;
            }

            $relativeClass = substr($className, strlen($prefix));
            $targetPath = base_path(
                rtrim($directory, '/\\') . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)
                . '.php'
            );

            if (is_file($targetPath)) {
                require_once $targetPath;
            }

            return;
        }
    }

    /**
     * @return array<string, string>
     */
    private static function prefixMap(): array
    {
        if (self::$prefixMap !== null) {
            return self::$prefixMap;
        }

        $prefixMap = [
            self::SHARED_CLASS_PREFIX => self::SHARED_SOURCE_DIRECTORY,
        ];

        $pluginRoot = base_path('plugins/payments');
        if (!is_dir($pluginRoot)) {
            return self::$prefixMap = $prefixMap;
        }

        foreach (glob($pluginRoot . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'plugin.json') ?: [] as $manifestPath) {
            $manifestDirectory = dirname($manifestPath);
            $decoded = json_decode((string)file_get_contents($manifestPath), true);
            if (!is_array($decoded)) {
                continue;
            }

            $className = trim((string)($decoded['class'] ?? ''));
            $entry = trim((string)($decoded['entry'] ?? ''));
            if ($className === '' || $entry === '' || !str_starts_with($className, self::PLUGIN_CLASS_PREFIX)) {
                continue;
            }

            $lastSeparatorPosition = strrpos($className, '\\');
            if ($lastSeparatorPosition === false) {
                continue;
            }

            $classPrefix = substr($className, 0, $lastSeparatorPosition + 1);
            $sourceDirectory = dirname(str_replace('\\', '/', $entry)) . '/';

            $prefixMap[$classPrefix] = $sourceDirectory;
        }

        return self::$prefixMap = $prefixMap;
    }
}
