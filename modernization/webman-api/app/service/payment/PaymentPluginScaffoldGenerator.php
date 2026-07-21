<?php

declare(strict_types=1);

namespace app\service\payment;

use InvalidArgumentException;
use RuntimeException;

class PaymentPluginScaffoldGenerator
{
    private const DEFAULT_PROVIDER = 'AiPay 官方';
    private const DEFAULT_DESCRIPTION = '为支付通道生成独立插件目录与基础配置。';
    private const DEFAULT_VERSION = '0.1.0';

    /**
     * @var list<string>
     */
    private const DEFAULT_CAPABILITIES = ['create_order', 'query', 'refund', 'notify'];

    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $root = $projectRoot !== null && trim($projectRoot) !== ''
            ? trim($projectRoot)
            : dirname(__DIR__, 2);

        $this->projectRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR);
    }

    /**
     * @return list<string>
     */
    public static function defaultCapabilities(): array
    {
        return self::DEFAULT_CAPABILITIES;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function generate(array $input): array
    {
        $options = $this->normalizeInput($input);
        $pluginDirectory = $this->pluginRootPath() . DIRECTORY_SEPARATOR . $options['code'];
        $configSchema = $this->configSchemaFields($options);

        if (file_exists($pluginDirectory)) {
            throw new RuntimeException('支付插件目录已存在');
        }

        $configTable = 'pay_plugin_' . $options['code'] . '_config';
        $logTable = 'pay_plugin_' . $options['code'] . '_log';
        $namespace = 'Plugins\\Payments\\' . $options['namespace_suffix'];
        $class = $namespace . '\\Plugin';
        $manifestDirectory = 'plugins/payments/' . $options['code'];

        $files = [
            $pluginDirectory . DIRECTORY_SEPARATOR . 'plugin.json' => $this->renderJson(
                $this->manifestPayload($options, $class, $configTable, $logTable)
            ),
            $pluginDirectory . DIRECTORY_SEPARATOR . 'README.md' => $this->renderReadme(
                $options,
                $namespace,
                $configTable,
                $logTable,
                $configSchema
            ),
            $pluginDirectory . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Plugin.php' => $this->renderPluginClass(
                $options,
                $namespace,
                $configTable,
                $logTable,
                $configSchema
            ),
            $pluginDirectory . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '001_create_config_table.sql' => $this->renderConfigMigration(
                $options['code'],
                $configTable
            ),
            $pluginDirectory . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '002_create_plugin_log_table.sql' => $this->renderLogMigration(
                $options['code'],
                $logTable
            ),
        ];

        $this->writeScaffold($pluginDirectory, $files);

        return [
            'plugin_code' => $options['code'],
            'plugin_name' => $options['name'],
            'version' => $options['version'],
            'provider' => $options['provider'],
            'description' => $options['description'],
            'plugin_directory' => $manifestDirectory,
            'namespace' => $namespace,
            'class' => $class,
            'config_table' => $configTable,
            'log_table' => $logTable,
            'runtime_directory' => 'runtime/payment-plugins/' . $options['code'],
            'capabilities' => $options['capabilities'],
            'config_fields' => array_map(
                static fn (array $field): string => (string)($field['field'] ?? ''),
                $configSchema
            ),
            'files' => array_map(
                fn (string $path): string => $this->relativeProjectPath($path),
                array_keys($files)
            ),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   code: string,
     *   name: string,
     *   provider: string,
     *   description: string,
     *   version: string,
     *   capabilities: list<string>,
     *   namespace_suffix: string
     * }
     */
    private function normalizeInput(array $input): array
    {
        $code = $this->normalizeCode((string)($input['code'] ?? ''));
        $name = $this->requireNonEmpty((string)($input['name'] ?? ''), '插件名称不能为空');
        $provider = trim((string)($input['provider'] ?? self::DEFAULT_PROVIDER));
        $description = trim((string)($input['description'] ?? self::DEFAULT_DESCRIPTION));
        $version = $this->normalizeVersion((string)($input['version'] ?? self::DEFAULT_VERSION));
        $capabilities = $this->normalizeCapabilities($input['capabilities'] ?? self::DEFAULT_CAPABILITIES);

        if ($provider === '') {
            throw new InvalidArgumentException('插件提供方不能为空');
        }

        if ($description === '') {
            throw new InvalidArgumentException('插件说明不能为空');
        }

        return [
            'code' => $code,
            'name' => $name,
            'provider' => $provider,
            'description' => $description,
            'version' => $version,
            'capabilities' => $capabilities,
            'namespace_suffix' => $this->buildNamespaceSuffix($code),
        ];
    }

    private function requireNonEmpty(string $value, string $message): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    private function normalizeCode(string $code): string
    {
        $code = strtolower(trim($code));
        if ($code === '' || !preg_match('/^[a-z0-9_]+$/', $code)) {
            throw new InvalidArgumentException('支付插件编码格式无效');
        }

        return $code;
    }

    private function normalizeVersion(string $version): string
    {
        $version = trim($version);
        if ($version === '' || !preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new InvalidArgumentException('插件版本号格式无效');
        }

        return $version;
    }

    /**
     * @param mixed $capabilities
     * @return list<string>
     */
    private function normalizeCapabilities(mixed $capabilities): array
    {
        if (is_string($capabilities)) {
            $capabilities = explode(',', $capabilities);
        }

        if (!is_array($capabilities)) {
            throw new InvalidArgumentException('插件能力必须为数组');
        }

        $normalized = [];
        foreach ($capabilities as $capability) {
            $capability = strtolower(trim((string)$capability));
            if ($capability === '') {
                continue;
            }

            if (!preg_match('/^[a-z0-9_]+$/', $capability)) {
                throw new InvalidArgumentException('插件能力标识无效：' . $capability);
            }

            $normalized[$capability] = true;
        }

        if ($normalized === []) {
            throw new InvalidArgumentException('至少需要声明一个插件能力');
        }

        return array_keys($normalized);
    }

    private function buildNamespaceSuffix(string $code): string
    {
        $segments = preg_split('/_+/', $code) ?: [];
        $parts = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            $parts[] = ucfirst($segment);
        }

        $suffix = implode('', $parts);
        if ($suffix === '') {
            $suffix = 'GeneratedPlugin';
        }

        if (!preg_match('/^[A-Za-z_]/', $suffix)) {
            $suffix = 'Plugin' . $suffix;
        }

        return $suffix;
    }

    private function pluginRootPath(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'payments';
    }

    /**
     * @param array<string, mixed> $options
     * @return list<array<string, mixed>>
     */
    private function configSchemaFields(array $options): array
    {
        $capabilities = $this->capabilitySet($options['capabilities'] ?? []);
        $fields = [
            [
                'field' => 'merchant_id',
                'label' => '商户号',
                'type' => 'text',
                'required' => true,
            ],
            [
                'field' => 'merchant_key',
                'label' => '商户密钥',
                'type' => 'password',
                'required' => true,
                'secret' => true,
            ],
        ];

        if ($this->supportsAnyCapability($capabilities, ['create_order', 'query', 'refund'])) {
            $fields[] = [
                'field' => 'gateway_url',
                'label' => '网关地址',
                'type' => 'text',
                'required' => true,
            ];
        }

        if (isset($capabilities['notify'])) {
            $fields[] = [
                'field' => 'notify_secret',
                'label' => '回调密钥',
                'type' => 'password',
                'required' => false,
                'secret' => true,
            ];
        }

        return $fields;
    }

    /**
     * @param list<string> $capabilities
     * @return array<string, bool>
     */
    private function capabilitySet(array $capabilities): array
    {
        $set = [];
        foreach ($capabilities as $capability) {
            $normalized = strtolower(trim((string)$capability));
            if ($normalized === '') {
                continue;
            }

            $set[$normalized] = true;
        }

        return $set;
    }

    /**
     * @param array<string, bool> $capabilities
     * @param list<string> $targets
     */
    private function supportsAnyCapability(array $capabilities, array $targets): bool
    {
        foreach ($targets as $target) {
            if (isset($capabilities[$target])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<string>
     */
    private function scaffoldChecklist(array $options): array
    {
        $checklist = [
            '首次生产安装前，请先检查 plugin.json、configSchema() 与清理策略。',
            '后续版本若要引入破坏性表结构变更，请先创建一份恢复快照。',
        ];

        foreach ($this->capabilityNotes($options) as $note) {
            $checklist[] = $note['checklist'];
        }

        $checklist[] = '升级后请先执行上述能力专项冒烟检查，确认通知与退款链路通过后再重新启用插件。';

        return $checklist;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<array{capability: string, label: string, summary: string, checklist: string}>
     */
    private function capabilityNotes(array $options): array
    {
        $selected = $this->capabilitySet($options['capabilities'] ?? []);
        $notes = [];

        $known = [
            'create_order' => [
                'label' => '创建订单',
                'summary' => '实现上游下单、请求签名，以及跳转或二维码拉起支付。',
                'checklist' => '请校验 create_order 的下单流程、金额规则与上游签名。',
            ],
            'query' => [
                'label' => '订单查询',
                'summary' => '实现上游订单状态轮询或延迟对账逻辑。',
                'checklist' => '请校验 query 的状态映射与对账处理逻辑。',
            ],
            'refund' => [
                'label' => '退款',
                'summary' => '实现上游退款请求、状态落库与失败处理。',
                'checklist' => '请校验 refund 的签名、幂等与失败态处理。',
            ],
            'notify' => [
                'label' => '回调通知',
                'summary' => '实现回调验签，以及插件侧结算追踪。',
                'checklist' => '请校验 notify 的验签、结算更新与重放保护。',
            ],
        ];

        foreach ($known as $capability => $note) {
            if (!isset($selected[$capability])) {
                continue;
            }

            $notes[] = [
                'capability' => $capability,
                'label' => $note['label'],
                'summary' => $note['summary'],
                'checklist' => $note['checklist'],
            ];
        }

        foreach (array_keys($selected) as $capability) {
            if (isset($known[$capability])) {
                continue;
            }

            $notes[] = [
                'capability' => $capability,
                'label' => ucwords(str_replace('_', ' ', $capability)),
                'summary' => '自定义能力默认只写入清单，需要手工补齐运行时接入与运维校验说明。',
                'checklist' => '请在生产启用前，为自定义能力 [' . $capability . '] 补全文档并完成验证。',
            ];
        }

        return $notes;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<array{method: string, capability: string, selected: bool, label: string, selected_message: string, unsupported_message: string}>
     */
    private function methodBlueprints(array $options): array
    {
        $selected = $this->capabilitySet($options['capabilities'] ?? []);

        return [
            [
                'method' => 'createOrder',
                'capability' => 'create_order',
                'selected' => isset($selected['create_order']),
                'label' => '创建订单',
                'selected_message' => '正式接入生产流量前，请将 createOrder() 替换为真实的上游下单流程。',
                'unsupported_message' => '当前脚手架未在 plugin.json 中声明 create_order 能力。',
            ],
            [
                'method' => 'query',
                'capability' => 'query',
                'selected' => isset($selected['query']),
                'label' => '订单查询',
                'selected_message' => '请为该插件实现上游订单状态查询能力。',
                'unsupported_message' => '当前脚手架未在 plugin.json 中声明 query 能力。',
            ],
            [
                'method' => 'refund',
                'capability' => 'refund',
                'selected' => isset($selected['refund']),
                'label' => '退款',
                'selected_message' => '请为该插件实现上游退款能力。',
                'unsupported_message' => '当前脚手架未在 plugin.json 中声明 refund 能力。',
            ],
            [
                'method' => 'handleNotify',
                'capability' => 'notify',
                'selected' => isset($selected['notify']),
                'label' => '回调通知',
                'selected_message' => '请为该插件实现回调验签与订单结算逻辑。',
                'unsupported_message' => '当前脚手架未在 plugin.json 中声明 notify 能力。',
            ],
        ];
    }

    private function manifestPayload(array $options, string $class, string $configTable, string $logTable): array
    {
        $code = $options['code'];

        return [
            'code' => $code,
            'name' => $options['name'],
            'description' => $options['description'],
            'version' => $options['version'],
            'provider' => $options['provider'],
            'entry' => 'plugins/payments/' . $code . '/src/Plugin.php',
            'class' => $class,
            'capabilities' => $options['capabilities'],
            'managed_channels' => [
                [
                    'code' => $code . '_default',
                    'name' => $options['name'] . '默认通道',
                    'type' => 'alipay',
                    'info' => '插件安装后自动生成的默认通道。',
                    'status' => 1,
                    'sort' => 0,
                    'maxcount' => 10,
                ],
            ],
            'migrations' => [
                'strategy' => 'versioned_sql',
                'releases' => [
                    [
                        'version' => $options['version'],
                        'files' => [
                            '001_create_config_table.sql',
                            '002_create_plugin_log_table.sql',
                        ],
                        'notes' => [
                            '创建 pay_plugin_' . $code . '_* 命名空间下的插件独立配置表与日志表。',
                            '脚手架默认使用隔离表结构，方便安装、清理与恢复流程保持无残留管理。',
                        ],
                    ],
                ],
            ],
            'upgrade' => [
                'impact' => 'medium',
                'downtime' => 'brief_validation',
                'requires_disable_after_upgrade' => true,
                'notes' => [
                    '脚手架默认采用谨慎升级策略，建议先验证新版本 SQL 再恢复通道流量。',
                    '后续版本仅应变更插件独立运行目录与 pay_plugin_' . $code . '_* 表。',
                ],
                'checklist' => $this->scaffoldChecklist($options),
                'changelog' => [
                    [
                        'version' => $options['version'],
                        'summary' => '初始化插件脚手架，包含独立配置表、日志表、生命周期元数据与清理钩子。',
                        'breaking' => false,
                        'migration_files' => [
                            '001_create_config_table.sql',
                            '002_create_plugin_log_table.sql',
                        ],
                        'notes' => [
                            '支付逻辑仍需在 src/Plugin.php 中按真实上游协议补齐后再启用。',
                            '后续 SQL 请以新版本发布追加，不要直接修改初始迁移文件。',
                        ],
                    ],
                ],
                'rollback' => [
                    'supported' => true,
                    'mode' => 'backup_restore',
                    'automatic' => false,
                    'requires_backup' => true,
                    'notes' => [
                        '回滚应通过备份或恢复仓恢复插件包、运行目录与插件独立数据表。',
                        '没有可验证恢复点时，不要执行破坏性降级。',
                    ],
                ],
            ],
            'cleanup' => [
                'safe' => [
                    'files' => [
                        'runtime/payment-plugins/' . $code,
                    ],
                    'tables' => [
                        $configTable,
                    ],
                    'notes' => [
                        '安全清理仅移除运行目录与插件独立配置数据，业务订单等数据继续保留。',
                    ],
                ],
                'purge' => [
                    'files' => [
                        'plugins/payments/' . $code,
                    ],
                    'tables' => [
                        $logTable,
                    ],
                    'notes' => [
                        '彻底清理会在明确确认后删除插件目录与插件独立日志表。',
                    ],
                ],
                'retain' => [
                    '商户订单记录',
                    '充值订单记录',
                    '资金与余额流水',
                    '结算记录',
                    '通知与审计轨迹',
                ],
                'purge_requires_confirmation' => true,
            ],
        ];
    }

    /**
     * @param array<string, string> $files
     */
    private function writeScaffold(string $pluginDirectory, array $files): void
    {
        $srcDirectory = $pluginDirectory . DIRECTORY_SEPARATOR . 'src';
        $migrationDirectory = $pluginDirectory . DIRECTORY_SEPARATOR . 'migrations';

        try {
            foreach ([$pluginDirectory, $srcDirectory, $migrationDirectory] as $directory) {
                if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                    throw new RuntimeException('创建插件脚手架目录失败');
                }
            }

            foreach ($files as $path => $contents) {
                if (file_put_contents($path, $contents) === false) {
                    throw new RuntimeException('写入插件脚手架文件失败');
                }
            }
        } catch (\Throwable $exception) {
            if (is_dir($pluginDirectory)) {
                $this->deleteDirectory($pluginDirectory);
            }

            throw $exception;
        }
    }

    private function deleteDirectory(string $directory): void
    {
        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }

    private function renderJson(array $payload): string
    {
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('编码支付插件清单失败');
        }

        return $encoded . PHP_EOL;
    }

    private function renderReadme(
        array $options,
        string $namespace,
        string $configTable,
        string $logTable,
        array $configSchema
    ): string
    {
        $capabilityList = implode(
            PHP_EOL,
            array_map(
                static fn (string $capability): string => '- `' . $capability . '`',
                $options['capabilities']
            )
        );
        $configFieldList = implode(
            PHP_EOL,
            array_map(
                static function (array $field): string {
                    $suffix = !empty($field['required']) ? '必填' : '可选';
                    return '- `' . ($field['field'] ?? '') . '` (' . $suffix . ')';
                },
                $configSchema
            )
        );
        $capabilityNotes = implode(
            PHP_EOL,
            array_map(
                static fn (array $note): string => '- `' . $note['capability'] . '` - ' . $note['summary'],
                $this->capabilityNotes($options)
            )
        );
        $runtimeDefaults = implode(
            PHP_EOL,
            array_map(
                static function (array $blueprint): string {
                    return '- `' . $blueprint['method'] . '()` -> '
                        . ($blueprint['selected'] ? '待补齐真实逻辑' : '未声明时返回不支持');
                },
                $this->methodBlueprints($options)
            )
        );
        $nextSteps = implode(
            PHP_EOL,
            array_map(
                static fn (string $item, int $index): string => ($index + 1) . '. ' . $item,
                [
                    '检查 plugin.json，并把后续 SQL 变更放到新的 `migrations.releases[]` 版本里。',
                    '仅为当前插件真实声明的能力补齐 `src/Plugin.php` 中的业务逻辑。',
                    '根据上游协议实际要求调整 `configSchema()` 字段。',
                    '后续如需破坏性变更，请先创建恢复快照。',
                ],
                array_keys([0, 1, 2, 3])
            )
        );

        return <<<MD
# {$options['name']}

该插件脚手架由 `php tools/create_payment_plugin.php` 自动生成。

## 基础信息

- 插件编码：`{$options['code']}`
- 命名空间：`{$namespace}`
- 初始版本：`{$options['version']}`
- 配置表：`{$configTable}`
- 日志表：`{$logTable}`
- 运行目录：`runtime/payment-plugins/{$options['code']}`
- 默认通道编码：`{$options['code']}_default`
- 清理策略：安全清理只移除运行目录与插件独立配置，彻底清理会删除插件目录与日志表

## 声明能力

{$capabilityList}

## 配置骨架

{$configFieldList}

## 能力说明

{$capabilityNotes}

## 默认行为

{$runtimeDefaults}

## 后续步骤

{$nextSteps}

## 说明

- 脚手架已接入 `PaymentPluginCleanupHookInterface`，卸载前可以先记录清理交接信息。
- 未声明的能力会直接返回不支持，便于区分“本插件不做”和“功能尚未补齐”。
- 后续插件独立数据表建议继续使用 `pay_plugin_{$options['code']}_*` 命名空间，方便清理与恢复。
- 插件托管通道请继续放在 `plugin.json -> managed_channels[]` 中，并保持 `{$options['code']}_*` 前缀。
- 订单、充值、余额流水、结算等业务表不应放入插件目录清理范围。
MD;
    }

    private function renderPluginClass(
        array $options,
        string $namespace,
        string $configTable,
        string $logTable,
        array $configSchema
    ): string
    {
        $code = $options['code'];
        $name = addslashes($options['name']);
        $version = addslashes($options['version']);
        $configTableLiteral = addslashes($configTable);
        $logTableLiteral = addslashes($logTable);
        $runtimeDirectoryLiteral = 'payment-plugins/' . $code;
        $capabilityConstant = $this->renderPhpArrayLiteral($options['capabilities']);
        $configSchemaLiteral = $this->renderPhpArrayLiteral($configSchema);
        $methodBlueprints = [];

        foreach ($this->methodBlueprints($options) as $blueprint) {
            $methodBlueprints[$blueprint['method']] = $blueprint;
        }

        $createOrderMethodBody = !empty($methodBlueprints['createOrder']['selected'])
            ? <<<PHP
        return [
            'plugin' => \$this->code(),
            'status' => 'pending',
            'status_text' => '待实现',
            'message' => '{$methodBlueprints['createOrder']['selected_message']}',
            'payload' => \$payload,
        ];
PHP
            : <<<PHP
        return \$this->unsupportedCapabilityResponse(
            'create_order',
            '{$methodBlueprints['createOrder']['unsupported_message']}',
            ['payload' => \$payload]
        );
PHP;
        $queryMethodBody = !empty($methodBlueprints['query']['selected'])
            ? <<<PHP
        return [
            'plugin' => \$this->code(),
            'status' => 'pending',
            'status_text' => '待实现',
            'order_no' => \$orderNo,
            'message' => '{$methodBlueprints['query']['selected_message']}',
        ];
PHP
            : <<<PHP
        return \$this->unsupportedCapabilityResponse(
            'query',
            '{$methodBlueprints['query']['unsupported_message']}',
            ['order_no' => \$orderNo]
        );
PHP;
        $refundMethodBody = !empty($methodBlueprints['refund']['selected'])
            ? <<<PHP
        return [
            'plugin' => \$this->code(),
            'status' => 'pending',
            'status_text' => '待实现',
            'message' => '{$methodBlueprints['refund']['selected_message']}',
            'payload' => \$payload,
        ];
PHP
            : <<<PHP
        return \$this->unsupportedCapabilityResponse(
            'refund',
            '{$methodBlueprints['refund']['unsupported_message']}',
            ['payload' => \$payload]
        );
PHP;
        $notifyMethodBody = !empty($methodBlueprints['handleNotify']['selected'])
            ? <<<PHP
        return [
            'plugin' => \$this->code(),
            'status' => 'pending',
            'status_text' => '待实现',
            'message' => '{$methodBlueprints['handleNotify']['selected_message']}',
            'payload' => \$payload,
        ];
PHP
            : <<<PHP
        return \$this->unsupportedCapabilityResponse(
            'notify',
            '{$methodBlueprints['handleNotify']['unsupported_message']}',
            ['payload' => \$payload]
        );
PHP;

        return <<<PHP
<?php

namespace {$namespace};

        use Plugins\Payments\Shared\Contracts\PaymentPluginCleanupHookInterface;
        use Plugins\Payments\Shared\Contracts\PaymentPluginInterface;
use RuntimeException;
use support\Db;
use function runtime_path;

class Plugin implements PaymentPluginInterface, PaymentPluginCleanupHookInterface
{
    private const CAPABILITIES = {$capabilityConstant};
    private const CONFIG_TABLE = '{$configTableLiteral}';
    private const LOG_TABLE = '{$logTableLiteral}';
    private const RUNTIME_DIRECTORY = '{$runtimeDirectoryLiteral}';
    private const METADATA_FILE = 'lifecycle.json';

    public function code(): string
    {
        return '{$code}';
    }

    public function install(): void
    {
        \$this->ensureRuntimeDirectory();
        \$this->seedConfigSkeleton();
        \$this->recordPluginLog('info', '{$name} 安装完成', [
            'plugin' => \$this->code(),
            'version' => '{$version}',
            'capabilities' => self::CAPABILITIES,
        ]);
        \$this->writeLifecycleMetadata([
            'status' => 'installed',
            'installed_at' => \$this->now(),
            'purge_requested' => false,
            'config_keys' => array_column(\$this->configSchema(), 'field'),
        ]);
    }

    public function upgrade(string \$fromVersion, string \$toVersion): void
    {
        \$this->ensureRuntimeDirectory();
        \$this->seedConfigSkeleton();
        \$this->recordPluginLog('info', '{$name} 升级完成', [
            'plugin' => \$this->code(),
            'from_version' => \$fromVersion,
            'to_version' => \$toVersion,
            'capabilities' => self::CAPABILITIES,
        ]);
        \$this->writeLifecycleMetadata([
            'status' => 'upgraded',
            'upgraded_at' => \$this->now(),
            'from_version' => \$fromVersion,
            'to_version' => \$toVersion,
            'purge_requested' => false,
            'config_keys' => array_column(\$this->configSchema(), 'field'),
        ]);
    }

    public function uninstall(bool \$purge = false): void
    {
        \$this->ensureRuntimeDirectory();
        \$this->recordPluginLog('warning', '{$name} 已提交卸载请求', [
            'plugin' => \$this->code(),
            'purge_requested' => \$purge,
        ]);
        \$this->writeLifecycleMetadata([
            'status' => \$purge ? 'purge_requested' : 'uninstalled',
            'uninstalled_at' => \$this->now(),
            'purge_requested' => \$purge,
        ]);
    }

    public function cleanup(string \$mode, array \$context = []): array
    {
        \$plan = is_array(\$context['plan'] ?? null) ? \$context['plan'] : [];
        \$fileTargets = is_array(\$plan['file_audit'] ?? null) ? count(\$plan['file_audit']) : 0;
        \$tableTargets = is_array(\$plan['table_audit'] ?? null) ? count(\$plan['table_audit']) : 0;

        \$steps = [
            sprintf(
                '开始%s前，已审计 %d 个文件目标与 %d 个数据表目标。',
                \$mode === 'purge' ? '彻底清理' : '安全清理',
                \$fileTargets,
                \$tableTargets
            ),
        ];

        if (is_dir(\$this->runtimeDirectoryPath())) {
            \$this->writeLifecycleMetadata([
                'status' => \$mode === 'purge' ? 'purge_cleanup_running' : 'safe_cleanup_running',
                'cleanup_mode' => \$mode,
                'cleanup_requested_at' => \$this->now(),
            ]);
            \$steps[] = '已更新生命周期元数据，方便清理执行期间继续追踪状态。';
        } else {
            \$steps[] = '插件运行目录已不存在，跳过运行态元数据交接。';
        }

        \$this->recordPluginLog(
            \$mode === 'purge' ? 'warning' : 'info',
            '{$name} 清理任务已准备完成',
            [
                'plugin' => \$this->code(),
                'mode' => \$mode,
                'file_target_count' => \$fileTargets,
                'table_target_count' => \$tableTargets,
            ]
        );

        return [
            'summary' => \$mode === 'purge'
                ? '已完成彻底清理前置准备，可继续删除插件包与插件独立数据。'
                : '已完成安全清理前置准备，业务订单等数据将继续保留。',
            'steps' => \$steps,
            'metadata' => [
                'file_target_count' => \$fileTargets,
                'table_target_count' => \$tableTargets,
            ],
        ];
    }

    public function configSchema(): array
    {
        return {$configSchemaLiteral};
    }

    public function createOrder(array \$payload): array
    {
{$createOrderMethodBody}
    }

    public function query(string \$orderNo): array
    {
{$queryMethodBody}
    }

    public function refund(array \$payload): array
    {
{$refundMethodBody}
    }

    public function handleNotify(array \$payload): array
    {
{$notifyMethodBody}
    }

    private function seedConfigSkeleton(): void
    {
        \$rows = [];

        foreach (\$this->configSchema() as \$field) {
            \$configKey = trim((string)(\$field['field'] ?? ''));
            if (\$configKey === '') {
                continue;
            }

            \$exists = Db::table(self::CONFIG_TABLE)
                ->where('plugin_code', \$this->code())
                ->where('config_key', \$configKey)
                ->exists();

            if (\$exists) {
                continue;
            }

            \$rows[] = [
                'plugin_code' => \$this->code(),
                'config_key' => \$configKey,
                'config_value' => \$this->defaultConfigValue(\$configKey),
                'created_at' => \$this->now(),
                'updated_at' => \$this->now(),
            ];
        }

        if (\$rows !== []) {
            Db::table(self::CONFIG_TABLE)->insert(\$rows);
        }
    }

    private function defaultConfigValue(string \$configKey): ?string
    {
        if (\$configKey === 'gateway_url') {
            return 'https://';
        }

        return null;
    }

    private function recordPluginLog(string \$level, string \$message, array \$context = []): void
    {
        try {
            Db::table(self::LOG_TABLE)->insert([
                'plugin_code' => \$this->code(),
                'level' => \$level,
                'message' => \$message,
                'context' => \$context === [] ? null : json_encode(\$context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => \$this->now(),
            ]);
        } catch (\\Throwable) {
            // Log rows are best-effort so lifecycle recovery is never blocked by diagnostics.
        }
    }

    private function supportsCapability(string \$capability): bool
    {
        return in_array(trim(\$capability), self::CAPABILITIES, true);
    }

    private function unsupportedCapabilityResponse(string \$capability, string \$message, array \$context = []): array
    {
        return array_merge([
            'plugin' => \$this->code(),
            'status' => 'unsupported',
            'status_text' => '未接入',
            'capability' => \$capability,
            'message' => \$message,
        ], \$context);
    }

    private function ensureRuntimeDirectory(): void
    {
        \$directory = \$this->runtimeDirectoryPath();
        if (is_dir(\$directory)) {
            return;
        }

        if (file_exists(\$directory)) {
            throw new RuntimeException('支付插件运行目录目标不是文件夹');
        }

        if (!mkdir(\$directory, 0777, true) && !is_dir(\$directory)) {
            throw new RuntimeException('创建支付插件运行目录失败');
        }
    }

    private function writeLifecycleMetadata(array \$payload): void
    {
        \$existing = [];
        \$metadataPath = \$this->metadataPath();

        if (is_file(\$metadataPath)) {
            \$decoded = json_decode((string)file_get_contents(\$metadataPath), true);
            if (is_array(\$decoded)) {
                \$existing = \$decoded;
            }
        }

        \$metadata = array_merge(\$existing, \$payload, [
            'plugin' => \$this->code(),
            'updated_at' => \$this->now(),
        ]);

        \$encoded = json_encode(\$metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (\$encoded === false) {
            throw new RuntimeException('编码支付插件生命周期元数据失败');
        }

        if (file_put_contents(\$metadataPath, \$encoded . PHP_EOL) === false) {
            throw new RuntimeException('写入支付插件生命周期元数据失败');
        }
    }

    private function runtimeDirectoryPath(): string
    {
        return runtime_path(self::RUNTIME_DIRECTORY);
    }

    private function metadataPath(): string
    {
        return \$this->runtimeDirectoryPath() . DIRECTORY_SEPARATOR . self::METADATA_FILE;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
PHP;
    }

    private function renderConfigMigration(string $code, string $configTable): string
    {
        return <<<SQL
        -- 插件安装阶段的首个迁移文件。
        -- 由 tools/create_payment_plugin.php 为 {$code} 插件自动生成。

CREATE TABLE IF NOT EXISTS `{$configTable}` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plugin_code` VARCHAR(64) NOT NULL DEFAULT '{$code}',
  `config_key` VARCHAR(100) NOT NULL,
  `config_value` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_plugin_key` (`plugin_code`, `config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    }

    private function renderLogMigration(string $code, string $logTable): string
    {
        return <<<SQL
        -- 插件安装阶段的首个迁移文件。
        -- 由 tools/create_payment_plugin.php 为 {$code} 插件自动生成。

CREATE TABLE IF NOT EXISTS `{$logTable}` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plugin_code` VARCHAR(64) NOT NULL DEFAULT '{$code}',
  `level` VARCHAR(32) NOT NULL DEFAULT 'info',
  `message` VARCHAR(255) NOT NULL,
  `context` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_plugin_created_at` (`plugin_code`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    }

    private function renderPhpArrayLiteral(array $value, int $indentLevel = 2): string
    {
        $indent = str_repeat('    ', $indentLevel);
        $childIndent = str_repeat('    ', $indentLevel + 1);

        if (array_is_list($value)) {
            if ($value === []) {
                return '[]';
            }

            $items = [];
            foreach ($value as $item) {
                $items[] = $childIndent . $this->renderPhpValueLiteral($item, $indentLevel + 1) . ',';
            }

            return '[' . PHP_EOL . implode(PHP_EOL, $items) . PHP_EOL . $indent . ']';
        }

        if ($value === []) {
            return '[]';
        }

        $items = [];
        foreach ($value as $key => $item) {
            $items[] = $childIndent
                . $this->renderPhpStringLiteral((string)$key)
                . ' => '
                . $this->renderPhpValueLiteral($item, $indentLevel + 1)
                . ',';
        }

        return '[' . PHP_EOL . implode(PHP_EOL, $items) . PHP_EOL . $indent . ']';
    }

    private function renderPhpValueLiteral(mixed $value, int $indentLevel = 2): string
    {
        if (is_array($value)) {
            return $this->renderPhpArrayLiteral($value, $indentLevel);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        return $this->renderPhpStringLiteral((string)$value);
    }

    private function renderPhpStringLiteral(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }

    private function relativeProjectPath(string $absolutePath): string
    {
        $normalizedRoot = str_replace('\\', '/', $this->projectRoot);
        $normalizedPath = str_replace('\\', '/', $absolutePath);

        return ltrim(substr($normalizedPath, strlen($normalizedRoot)), '/');
    }
}
