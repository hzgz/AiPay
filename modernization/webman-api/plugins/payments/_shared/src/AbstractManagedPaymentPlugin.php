<?php

declare(strict_types=1);

namespace Plugins\Payments\Shared;

use app\payment\Contracts\PaymentPluginCleanupHookInterface;
use app\payment\Contracts\PaymentPluginInterface;
use RuntimeException;
use support\Db;
use function runtime_path;

abstract class AbstractManagedPaymentPlugin implements PaymentPluginInterface, PaymentPluginCleanupHookInterface
{
    private const METADATA_FILE = 'lifecycle.json';

    abstract protected function pluginName(): string;

    protected function pluginVersion(): string
    {
        return '1.0.0';
    }

    abstract protected function configTable(): string;

    protected function logTable(): ?string
    {
        return null;
    }

    protected function runtimeDirectory(): string
    {
        return 'payment-plugins/' . $this->code();
    }

    public function install(): void
    {
        $this->ensureRuntimeDirectory();
        $this->seedConfigSkeleton();
        $this->recordPluginLog('info', $this->pluginName() . ' installed.', [
            'plugin' => $this->code(),
            'version' => $this->pluginVersion(),
        ]);
        $this->writeLifecycleMetadata([
            'status' => 'installed',
            'installed_at' => $this->now(),
            'purge_requested' => false,
            'config_keys' => array_column($this->configSchema(), 'field'),
        ]);
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        $this->ensureRuntimeDirectory();
        $this->seedConfigSkeleton();
        $this->recordPluginLog('info', $this->pluginName() . ' upgraded.', [
            'plugin' => $this->code(),
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
        ]);
        $this->writeLifecycleMetadata([
            'status' => 'upgraded',
            'upgraded_at' => $this->now(),
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'purge_requested' => false,
            'config_keys' => array_column($this->configSchema(), 'field'),
        ]);
    }

    public function uninstall(bool $purge = false): void
    {
        $this->ensureRuntimeDirectory();
        $this->recordPluginLog('warning', $this->pluginName() . ' uninstall requested.', [
            'plugin' => $this->code(),
            'purge_requested' => $purge,
        ]);
        $this->writeLifecycleMetadata([
            'status' => $purge ? 'purge_requested' : 'uninstalled',
            'uninstalled_at' => $this->now(),
            'purge_requested' => $purge,
        ]);
    }

    public function cleanup(string $mode, array $context = []): array
    {
        $plan = is_array($context['plan'] ?? null) ? $context['plan'] : [];
        $fileTargets = is_array($plan['file_audit'] ?? null) ? count($plan['file_audit']) : 0;
        $tableTargets = is_array($plan['table_audit'] ?? null) ? count($plan['table_audit']) : 0;

        $steps = [
            sprintf(
                'Reviewed %d audited file target(s) and %d table target(s) before %s cleanup.',
                $fileTargets,
                $tableTargets,
                $mode
            ),
        ];

        if (is_dir($this->runtimeDirectoryPath())) {
            $this->writeLifecycleMetadata([
                'status' => $mode === 'purge' ? 'purge_cleanup_running' : 'safe_cleanup_running',
                'cleanup_mode' => $mode,
                'cleanup_requested_at' => $this->now(),
            ]);
            $steps[] = 'Updated lifecycle metadata so the cleanup handoff remains visible during execution.';
        } else {
            $steps[] = 'Skipped runtime metadata handoff because the plugin runtime directory is already absent.';
        }

        $this->recordPluginLog(
            $mode === 'purge' ? 'warning' : 'info',
            $this->pluginName() . ' cleanup prepared.',
            [
                'plugin' => $this->code(),
                'mode' => $mode,
                'file_target_count' => $fileTargets,
                'table_target_count' => $tableTargets,
            ]
        );

        return [
            'summary' => $mode === 'purge'
                ? 'Prepared the plugin for final package removal and namespaced data purge.'
                : 'Prepared the plugin runtime for safe cleanup while leaving business records untouched.',
            'steps' => $steps,
            'metadata' => [
                'file_target_count' => $fileTargets,
                'table_target_count' => $tableTargets,
            ],
        ];
    }

    public function configSchema(): array
    {
        return [];
    }

    public function createOrder(array $payload): array
    {
        return $this->unsupportedCapabilityResponse(
            'create_order',
            $this->pluginName() . ' is an admin-managed account plugin and does not handle gateway order creation.',
            ['payload' => $payload]
        );
    }

    public function query(string $orderNo): array
    {
        return $this->unsupportedCapabilityResponse(
            'query',
            $this->pluginName() . ' is an admin-managed account plugin and does not provide order query traffic.',
            ['order_no' => $orderNo]
        );
    }

    public function refund(array $payload): array
    {
        return $this->unsupportedCapabilityResponse(
            'refund',
            $this->pluginName() . ' is an admin-managed account plugin and does not provide refund traffic.',
            ['payload' => $payload]
        );
    }

    public function handleNotify(array $payload): array
    {
        return $this->unsupportedCapabilityResponse(
            'notify',
            $this->pluginName() . ' is an admin-managed account plugin and does not handle callback traffic.',
            ['payload' => $payload]
        );
    }

    protected function defaultConfigValue(string $configKey): ?string
    {
        return match ($configKey) {
            'display_name' => $this->pluginName(),
            'operator_note' => 'Installed from the managed payment plugin catalog.',
            default => null,
        };
    }

    private function seedConfigSkeleton(): void
    {
        $rows = [];

        foreach ($this->configSchema() as $field) {
            if (!is_array($field)) {
                continue;
            }

            $configKey = trim((string)($field['field'] ?? ''));
            if ($configKey === '') {
                continue;
            }

            $exists = Db::table($this->configTable())
                ->where('plugin_code', $this->code())
                ->where('config_key', $configKey)
                ->exists();

            if ($exists) {
                continue;
            }

            $rows[] = [
                'plugin_code' => $this->code(),
                'config_key' => $configKey,
                'config_value' => $this->defaultConfigValue($configKey),
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ];
        }

        if ($rows !== []) {
            Db::table($this->configTable())->insert($rows);
        }
    }

    private function recordPluginLog(string $level, string $message, array $context = []): void
    {
        $table = $this->logTable();
        if ($table === null || $table === '') {
            return;
        }

        try {
            Db::table($table)->insert([
                'plugin_code' => $this->code(),
                'level' => $level,
                'message' => $message,
                'context' => $context === [] ? null : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $this->now(),
            ]);
        } catch (\Throwable) {
            // Plugin diagnostics are best-effort only.
        }
    }

    private function unsupportedCapabilityResponse(string $capability, string $message, array $context = []): array
    {
        return array_merge([
            'plugin' => $this->code(),
            'status' => 'unsupported',
            'capability' => $capability,
            'message' => $message,
        ], $context);
    }

    private function ensureRuntimeDirectory(): void
    {
        $directory = $this->runtimeDirectoryPath();
        if (is_dir($directory)) {
            return;
        }

        if (file_exists($directory)) {
            throw new RuntimeException('payment plugin runtime path is not a directory');
        }

        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('failed to create payment plugin runtime directory');
        }
    }

    private function writeLifecycleMetadata(array $payload): void
    {
        $existing = [];
        $metadataPath = $this->metadataPath();

        if (is_file($metadataPath)) {
            $decoded = json_decode((string)file_get_contents($metadataPath), true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }

        $metadata = array_merge($existing, $payload, [
            'plugin' => $this->code(),
            'updated_at' => $this->now(),
        ]);

        $encoded = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('failed to encode payment plugin lifecycle metadata');
        }

        if (file_put_contents($metadataPath, $encoded . PHP_EOL) === false) {
            throw new RuntimeException('failed to write payment plugin lifecycle metadata');
        }
    }

    private function runtimeDirectoryPath(): string
    {
        return runtime_path($this->runtimeDirectory());
    }

    private function metadataPath(): string
    {
        return $this->runtimeDirectoryPath() . DIRECTORY_SEPARATOR . self::METADATA_FILE;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
