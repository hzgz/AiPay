<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace Plugins\Payments\Shared;

use Plugins\Payments\Shared\Contracts\PaymentPluginCleanupHookInterface;
use Plugins\Payments\Shared\Contracts\PaymentPluginInterface;
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
        $this->recordPluginLog('info', $this->pluginName() . ' 安装完成', [
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
        $this->recordPluginLog('info', $this->pluginName() . ' 升级完成', [
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
        $this->recordPluginLog('warning', $this->pluginName() . ' 已提交卸载请求', [
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
                '开始%s前，已审计 %d 个文件目标与 %d 个数据表目标。',
                $mode === 'purge' ? '彻底清理' : '安全清理',
                $fileTargets,
                $tableTargets
            ),
        ];

        if (is_dir($this->runtimeDirectoryPath())) {
            $this->writeLifecycleMetadata([
                'status' => $mode === 'purge' ? 'purge_cleanup_running' : 'safe_cleanup_running',
                'cleanup_mode' => $mode,
                'cleanup_requested_at' => $this->now(),
            ]);
            $steps[] = '已更新生命周期元数据，方便清理执行期间继续追踪状态。';
        } else {
            $steps[] = '插件运行目录已不存在，跳过运行态元数据交接。';
        }

        $this->recordPluginLog(
            $mode === 'purge' ? 'warning' : 'info',
            $this->pluginName() . ' 清理任务已准备完成',
            [
                'plugin' => $this->code(),
                'mode' => $mode,
                'file_target_count' => $fileTargets,
                'table_target_count' => $tableTargets,
            ]
        );

        return [
            'summary' => $mode === 'purge'
                ? '已完成彻底清理前置准备，可继续删除插件包与插件独立数据。'
                : '已完成安全清理前置准备，业务订单等数据将继续保留。',
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
            $this->pluginName() . ' 属于后台托管型插件，不负责统一网关下单。',
            ['payload' => $payload]
        );
    }

    public function query(string $orderNo): array
    {
        return $this->unsupportedCapabilityResponse(
            'query',
            $this->pluginName() . ' 属于后台托管型插件，不提供统一查单能力。',
            ['order_no' => $orderNo]
        );
    }

    public function refund(array $payload): array
    {
        return $this->unsupportedCapabilityResponse(
            'refund',
            $this->pluginName() . ' 属于后台托管型插件，不提供统一退款能力。',
            ['payload' => $payload]
        );
    }

    public function handleNotify(array $payload): array
    {
        return $this->unsupportedCapabilityResponse(
            'notify',
            $this->pluginName() . ' 属于后台托管型插件，不处理统一回调流量。',
            ['payload' => $payload]
        );
    }

    protected function defaultConfigValue(string $configKey): ?string
    {
        return match ($configKey) {
            'display_name' => $this->pluginName(),
            'operator_note' => '通过支付插件目录安装。',
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
            'status_text' => '未接入',
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
            throw new RuntimeException('支付插件运行目录目标不是文件夹');
        }

        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('创建支付插件运行目录失败');
        }
    }

    private function writeLifecycleMetadata(array $payload): void
    {
        $existing = [];
        $metadataPath = $this->metadataPath();

        if (is_file($metadataPath)) {
            $decoded = json_decode((string) file_get_contents($metadataPath), true);
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
            throw new RuntimeException('编码支付插件生命周期元数据失败');
        }

        if (file_put_contents($metadataPath, $encoded . PHP_EOL) === false) {
            throw new RuntimeException('写入支付插件生命周期元数据失败');
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
