<?php

declare(strict_types=1);

namespace Plugins\Payments\LegacyEpay;

use Plugins\Payments\Shared\Contracts\PaymentPluginCleanupHookInterface;
use Plugins\Payments\Shared\Contracts\PaymentPluginInterface;
use Plugins\Payments\Shared\Legacy\LegacyEpayService;
use RuntimeException;
use support\Db;
use function runtime_path;

class Plugin implements PaymentPluginInterface, PaymentPluginCleanupHookInterface
{
    private const CONFIG_TABLE = 'pay_plugin_legacy_epay_config';
    private const RUNTIME_DIRECTORY = 'payment-plugins/legacy_epay';
    private const METADATA_FILE = 'lifecycle.json';

    public function code(): string
    {
        return 'legacy_epay';
    }

    public function install(): void
    {
        $this->ensureRuntimeDirectory();
        $this->seedConfigSkeleton();
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

        return [
            'summary' => $mode === 'purge'
                ? 'Prepared the compatibility plugin for final package removal and namespaced data purge.'
                : 'Prepared the compatibility plugin runtime for safe cleanup while leaving business records untouched.',
            'steps' => $steps,
            'metadata' => [
                'file_target_count' => $fileTargets,
                'table_target_count' => $tableTargets,
            ],
        ];
    }

    public function configSchema(): array
    {
        return [
            [
                'field' => 'merchant_id',
                'label' => '默认商户号',
                'type' => 'text',
                'required' => false,
            ],
            [
                'field' => 'merchant_key',
                'label' => '默认商户密钥',
                'type' => 'password',
                'required' => false,
                'secret' => true,
            ],
            [
                'field' => 'gateway_url',
                'label' => '默认网关地址',
                'type' => 'text',
                'required' => false,
            ],
            [
                'field' => 'notify_url',
                'label' => '默认通知地址',
                'type' => 'text',
                'required' => false,
            ],
        ];
    }

    public function createOrder(array $payload): array
    {
        return $this->service()->createOrder($payload);
    }

    public function query(string $orderNo): array
    {
        return [
            'plugin' => $this->code(),
            'status' => 'planned',
            'order_no' => $orderNo,
            'message' => '旧版易支付查单能力将在后续阶段迁移。',
        ];
    }

    public function refund(array $payload): array
    {
        return [
            'plugin' => $this->code(),
            'status' => 'planned',
            'message' => '旧版易支付退款能力将在后续阶段迁移。',
            'payload' => $payload,
        ];
    }

    public function handleNotify(array $payload): array
    {
        return $this->service()->handleNotify($payload);
    }

    private function service(): LegacyEpayService
    {
        return new LegacyEpayService();
    }

    private function seedConfigSkeleton(): void
    {
        $rows = [];

        foreach ($this->configSchema() as $field) {
            $configKey = trim((string)($field['field'] ?? ''));
            if ($configKey === '') {
                continue;
            }

            $exists = Db::table(self::CONFIG_TABLE)
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
            Db::table(self::CONFIG_TABLE)->insert($rows);
        }
    }

    private function defaultConfigValue(string $configKey): ?string
    {
        if ($configKey === 'gateway_url') {
            return 'https://';
        }

        return null;
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
        return runtime_path(self::RUNTIME_DIRECTORY);
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
