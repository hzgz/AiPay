<?php

namespace app\payment;

use app\support\CorePaymentMethodCatalog;
use app\support\DatabaseColumnInspector;
use DomainException;
use InvalidArgumentException;
use Plugins\Payments\Shared\Contracts\PaymentPluginCleanupHookInterface;
use Plugins\Payments\Shared\Contracts\PaymentPluginInterface;
use RuntimeException;
use support\Db;
use function base_path;
use function runtime_path;

class PaymentPluginManager
{
    private const PLUGIN_ROOT = 'plugins/payments';
    private const REGISTRY_FILE = 'payment_plugins.json';
    private const HISTORY_ROOT = 'payment-plugin-audit';
    private const SNAPSHOT_ROOT = 'payment-plugin-snapshots';
    private const REGISTRY_RESIDUE_LEDGER_FILE = 'registry-residue-ledger.json';
    private const CHANNEL_CREATE_TYPE_PLUGIN = 2;

    public function all(): array
    {
        $registry = $this->loadRegistry();
        $plugins = [];

        foreach ($this->discoverManifests() as $manifest) {
            $snapshot = $this->pluginSnapshot($manifest, $registry);
            $this->persistRegistrySnapshot($manifest, $snapshot, $registry);
            $state = $snapshot['state'];
            $plugins[] = [
                'code' => $manifest['code'],
                'name' => $manifest['name'],
                'description' => $manifest['description'],
                'version' => $manifest['version'],
                'provider' => $manifest['provider'],
                'directory' => $manifest['directory'],
                'installed' => $state['installed'],
                'enabled' => $state['enabled'],
                'status' => $state['status'],
                'installed_at' => $state['installed_at'],
                'updated_at' => $state['updated_at'],
                'capabilities' => $manifest['capabilities'],
                'state_audit' => $snapshot['state_audit'],
                'cleanup_policy' => [
                    'safe_files' => $manifest['cleanup']['safe']['files'],
                    'safe_tables' => $manifest['cleanup']['safe']['tables'],
                    'retain_scopes' => $manifest['cleanup']['retain'],
                    'purge_requires_confirmation' => $manifest['cleanup']['purge_requires_confirmation'],
                ],
            ];
        }

        usort($plugins, static fn (array $left, array $right): int => strcmp($left['code'], $right['code']));

        return $plugins;
    }

    public function detail(string $code): array
    {
        $manifest = $this->loadManifest($code);
        $registry = $this->loadRegistry();
        $snapshot = $this->pluginSnapshot($manifest, $registry);
        $this->persistRegistrySnapshot($manifest, $snapshot, $registry);

        return $this->detailPayload($manifest, $snapshot);
    }

    public function history(string $code): array
    {
        $manifest = $this->loadManifest($code);

        return $this->historyPayload($manifest['code']);
    }

    public function bundle(string $code): array
    {
        $manifest = $this->loadManifest($code);
        $registry = $this->loadRegistry();
        $snapshot = $this->pluginSnapshot($manifest, $registry);
        $this->persistRegistrySnapshot($manifest, $snapshot, $registry);

        return [
            'plugin_code' => $manifest['code'],
            'generated_at' => $this->now(),
            'paths' => [
                'plugin_directory' => $manifest['directory'],
                'runtime_directory' => $this->runtimeTarget($manifest['code']),
                'history_path' => $this->historyRelativePath($manifest['code']),
                'migration_journal_path' => $this->migrationJournalRelativePath($manifest['code']),
                'snapshot_directory' => $this->snapshotDirectoryRelativePath($manifest['code']),
            ],
            'detail' => $this->detailPayload($manifest, $snapshot),
            'migration_journal' => $this->loadMigrationJournal($manifest),
        ];
    }

    public function snapshots(string $code): array
    {
        return $this->snapshotsPayload($this->normalizeCode($code));
    }

    public function recoveryVault(): array
    {
        return $this->recoveryVaultPayload();
    }

    public function registryResidues(): array
    {
        return $this->registryResiduesPayload();
    }

    public function registryResidueLedger(): array
    {
        return $this->registryResidueLedgerPayload();
    }

    public function createScaffold(array $payload, array $operator = []): array
    {
        $generator = new PaymentPluginScaffoldGenerator();
        $created = $generator->generate($payload);
        $code = (string)($created['plugin_code'] ?? '');

        return [
            'created' => $created,
            'detail' => $this->detail($code),
        ];
    }

    /**
     * @return array{
     *     plugin_code: string,
     *     field: string,
     *     capability: string,
     *     capabilities: array<int, string>,
     *     installed: bool,
     *     enabled: bool
     * }
     */
    public function credentialDecodeProfile(string $code, string $field): array
    {
        $manifest = $this->loadManifest($code);
        $registry = $this->loadRegistry();
        $state = $this->stateFor($manifest['code'], (string)$manifest['version'], $registry);
        $field = strtolower(trim($field));

        $capability = match ($field) {
            'qr_url' => 'merchant_qrcode',
            'extra_value' => 'bill_qrcode',
            default => throw new InvalidArgumentException('the requested field does not support qrcode decoding'),
        };

        $capabilities = is_array($manifest['capabilities'] ?? null) ? $manifest['capabilities'] : [];
        if (!in_array($capability, $capabilities, true)) {
            throw new DomainException(sprintf(
                'payment plugin [%s] does not declare the [%s] capability',
                $manifest['code'],
                $capability
            ));
        }

        if (!(bool)($state['installed'] ?? false)) {
            throw new DomainException(sprintf('payment plugin [%s] is not installed', $manifest['code']));
        }

        if (!(bool)($state['enabled'] ?? false)) {
            throw new DomainException(sprintf('payment plugin [%s] is disabled', $manifest['code']));
        }

        return [
            'plugin_code' => $manifest['code'],
            'field' => $field,
            'capability' => $capability,
            'capabilities' => $capabilities,
            'installed' => true,
            'enabled' => true,
        ];
    }

    public function createSnapshot(string $code, ?string $label = null, array $operator = []): array
    {
        $manifest = $this->loadManifest($code);
        $registry = $this->loadRegistry();
        $snapshot = $this->pluginSnapshot($manifest, $registry);
        $this->persistRegistrySnapshot($manifest, $snapshot, $registry);

        $snapshotId = $this->snapshotId($manifest['code']);
        $payload = $this->buildSnapshotPayload($manifest, $snapshot, $snapshotId, $label, $operator);
        $this->saveSnapshotPayload($manifest['code'], $snapshotId, $payload);

        $state = $snapshot['state'];
        $this->recordHistoryEvent(
            $manifest['code'],
            'snapshot_created',
            $state,
            $operator,
            sprintf('Captured a recovery snapshot%s.', $label !== null && trim($label) !== '' ? ' [' . trim($label) . ']' : ''),
            [
                'snapshot_id' => $snapshotId,
                'snapshot_path' => $this->snapshotRelativePath($manifest['code'], $snapshotId),
                'file_root_count' => (int)($payload['summary']['file_root_count'] ?? 0),
                'archived_file_count' => (int)($payload['summary']['archived_file_count'] ?? 0),
                'table_count' => (int)($payload['summary']['table_count'] ?? 0),
                'row_count' => (int)($payload['summary']['row_count'] ?? 0),
            ]
        );

        return [
            'detail' => $this->detail($manifest['code']),
            'snapshot' => $this->snapshotMetadata($payload, $this->snapshotPath($manifest['code'], $snapshotId)),
        ];
    }

    public function restoreSnapshot(string $code, string $snapshotId, array $operator = []): array
    {
        $code = $this->normalizeCode($code);
        $snapshotId = $this->normalizeSnapshotId($snapshotId);
        $payload = $this->loadSnapshotPayload($code, $snapshotId);
        $archivedCode = trim((string)($payload['plugin_code'] ?? ''));

        if ($archivedCode !== $code) {
            throw new RuntimeException('snapshot code does not match the requested plugin code');
        }

        $registry = $this->loadRegistry();
        $currentState = $this->stateFor(
            $code,
            (string)($payload['manifest']['version'] ?? '0.0.0'),
            $registry
        );

        if ((bool)($currentState['enabled'] ?? false)) {
            throw new RuntimeException('disable the plugin before restoring a recovery snapshot');
        }

        $this->restoreSnapshotArchives($code, $payload);
        $manifest = $this->loadManifest($code);
        $registryRecord = is_array($payload['registry_record'] ?? null) ? $payload['registry_record'] : null;
        if ($registryRecord !== null) {
            $registry[$code] = $this->stateFor(
                $code,
                (string)$manifest['version'],
                [$code => $registryRecord]
            );
        } else {
            unset($registry[$code]);
        }

        $restoredState = $this->stateFor($code, (string)$manifest['version'], $registry);
        $restoredState = $this->touchState($restoredState, 'snapshot_restored', $operator);
        $registry[$code] = $restoredState;
        $this->saveRegistry($registry);
        $this->recordHistoryEvent(
            $code,
            'snapshot_restored',
            $restoredState,
            $operator,
            sprintf(
                'Restored plugin assets from recovery snapshot %s%s.',
                $snapshotId,
                isset($payload['label']) && trim((string)$payload['label']) !== ''
                    ? ' [' . trim((string)$payload['label']) . ']'
                    : ''
            ),
            [
                'snapshot_id' => $snapshotId,
                'snapshot_path' => $this->snapshotRelativePath($code, $snapshotId),
                'snapshot_created_at' => $payload['created_at'] ?? null,
                'file_root_count' => (int)($payload['summary']['file_root_count'] ?? 0),
                'archived_file_count' => (int)($payload['summary']['archived_file_count'] ?? 0),
                'table_count' => (int)($payload['summary']['table_count'] ?? 0),
                'row_count' => (int)($payload['summary']['row_count'] ?? 0),
            ]
        );

        return [
            'detail' => $this->detail($code),
            'snapshot' => $this->snapshotMetadata($payload, $this->snapshotPath($code, $snapshotId)),
        ];
    }

    public function deleteSnapshot(string $code, string $snapshotId, array $operator = []): array
    {
        $code = $this->normalizeCode($code);
        $snapshotId = $this->normalizeSnapshotId($snapshotId);
        $payload = $this->loadSnapshotPayload($code, $snapshotId);
        $archivedCode = trim((string)($payload['plugin_code'] ?? ''));

        if ($archivedCode !== $code) {
            throw new RuntimeException('snapshot code does not match the requested plugin code');
        }

        $path = $this->snapshotPath($code, $snapshotId);
        $metadata = $this->snapshotMetadata($payload, $path);
        $catalogAvailable = $this->pluginCatalogAvailable($code);
        $registry = $this->loadRegistry();
        $version = trim((string)($payload['manifest']['version'] ?? '0.0.0')) ?: '0.0.0';
        $historyState = $this->stateFor($code, $version, $registry);

        if (
            !(bool)($historyState['installed'] ?? false)
            && is_array($payload['registry_record'] ?? null)
        ) {
            $historyState = $this->stateFor($code, $version, [$code => (array)$payload['registry_record']]);
        }

        $this->removePath($path);
        $this->cleanupSnapshotDirectory($code);

        if ($this->shouldRecordSnapshotHistory($code)) {
            $this->recordHistoryEvent(
                $code,
                'snapshot_deleted',
                $historyState,
                $operator,
                sprintf(
                    'Deleted recovery snapshot %s%s.',
                    $snapshotId,
                    $metadata['label'] !== null ? ' [' . $metadata['label'] . ']' : ''
                ),
                [
                    'snapshot_id' => $snapshotId,
                    'snapshot_path' => $metadata['snapshot_path'],
                    'snapshot_created_at' => $metadata['created_at'],
                    'catalog_available' => $catalogAvailable,
                ]
            );
        }

        return [
            'plugin_code' => $code,
            'deleted_snapshot_id' => $snapshotId,
            'catalog_available' => $catalogAvailable,
            'detail' => $catalogAvailable ? $this->detail($code) : null,
            'snapshots' => $this->snapshotsPayload($code),
        ];
    }

    public function cleanupRegistryResidue(string $code, array $operator = []): array
    {
        $code = $this->normalizeCode($code);
        $registry = $this->loadRegistry();
        if (!is_array($registry[$code] ?? null)) {
            throw new InvalidArgumentException("payment plugin registry residue [$code] was not found");
        }

        if ($this->pluginCatalogAvailable($code)) {
            throw new DomainException('plugin catalog is still available; use the standard plugin lifecycle actions instead');
        }

        $tableAudit = $this->namespacedTableAudit($code);
        $managedChannelAudit = $this->registryResidueManagedChannelAudit($code);
        $fileAudit = [
            $this->auditCleanupFile($this->runtimeTarget($code)),
            $this->auditCleanupFile($this->historyDirectoryTarget($code)),
            $this->auditCleanupFile($this->pluginSourceTarget($code)),
        ];
        $blockedManagedChannels = array_values(array_filter(
            $managedChannelAudit,
            static fn (array $item): bool => !(bool)($item['can_cleanup'] ?? false)
        ));
        if ($blockedManagedChannels !== []) {
            throw new DomainException(sprintf(
                'plugin-managed channel residue is still blocked for [%s]; clear account, pool, or order dependencies before orphan cleanup can continue',
                $code
            ));
        }

        $snapshotGuard = $this->registryResidueGuardPayload($code);
        $report = [
            'mode' => 'registry_residue_cleanup',
            'removed_file_count' => 0,
            'removed_table_count' => 0,
            'removed_row_count' => 0,
            'removed_managed_channel_count' => 0,
            'items' => [],
            'registry_removed' => false,
            'snapshot_retained' => (bool)($snapshotGuard['has_snapshot'] ?? false),
            'retained_snapshot_count' => (int)($snapshotGuard['snapshot_total'] ?? 0),
            'finished_at' => $this->now(),
        ];

        foreach ($fileAudit as $audit) {
            $item = $this->cleanupPurgeFile($code, $audit);
            $report['items'][] = $item;
            if ($item['removed']) {
                $report['removed_file_count']++;
            }
        }

        foreach ($tableAudit as $audit) {
            $item = $this->cleanupPurgeTable($code, $audit);
            $report['items'][] = $item;
            if ($item['removed']) {
                $report['removed_table_count']++;
                $report['removed_row_count'] += (int)($item['row_count'] ?? 0);
            }
        }

        foreach ($managedChannelAudit as $audit) {
            $item = $this->cleanupPurgeManagedChannel($code, $audit);
            $report['items'][] = $item;
            if ($item['removed']) {
                $report['removed_managed_channel_count']++;
                $report['removed_row_count'] += (int)($item['row_count'] ?? 0);
            }
        }

        unset($registry[$code]);
        $this->saveRegistry($registry);
        $report['registry_removed'] = true;
        $this->recordRegistryResidueLedger(
            $code,
            $snapshotGuard,
            $fileAudit,
            $tableAudit,
            $managedChannelAudit,
            $report,
            $operator
        );

        return [
            'plugin_code' => $code,
            'cleanup_report' => $report,
            'registry_residue' => $this->registryResiduesPayload(),
            'registry_residue_ledger' => $this->registryResidueLedgerPayload(),
            'recovery_vault' => $this->recoveryVaultPayload(),
        ];
    }

    public function saveConfig(string $code, array $config, array $operator = []): array
    {
        $manifest = $this->loadManifest($code);
        $registry = $this->loadRegistry();
        $snapshot = $this->pluginSnapshot($manifest, $registry);
        $this->persistRegistrySnapshot($manifest, $snapshot, $registry);

        $state = $snapshot['state'];

        if (!$state['installed']) {
            throw new RuntimeException('plugin must be installed before config can be updated');
        }

        $schema = $snapshot['config_schema'];
        $table = $this->configTableName($manifest);
        if (!$this->tableExists($table)) {
            throw new RuntimeException('plugin config table is not available; install migrations first');
        }

        $values = $this->normalizeConfigInput($schema, $config);
        $now = $this->now();

        Db::transaction(function () use ($table, $manifest, $schema, $values, $now): void {
            foreach ($schema as $field) {
                $configKey = (string)$field['field'];
                $exists = Db::table($table)
                    ->where('plugin_code', $manifest['code'])
                    ->where('config_key', $configKey)
                    ->exists();

                $payload = [
                    'config_value' => $values[$configKey],
                    'updated_at' => $now,
                ];

                if ($exists) {
                    Db::table($table)
                        ->where('plugin_code', $manifest['code'])
                        ->where('config_key', $configKey)
                        ->update($payload);
                    continue;
                }

                Db::table($table)->insert([
                    'plugin_code' => $manifest['code'],
                    'config_key' => $configKey,
                    'config_value' => $values[$configKey],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        $state['last_action'] = 'config_update';
        $state['updated_at'] = $now;
        $state['last_operator'] = $this->normalizeOperator($operator);
        $registry[$manifest['code']] = $state;
        $this->saveRegistry($registry);
        $this->recordHistoryEvent(
            $manifest['code'],
            'config_update',
            $state,
            $operator,
            sprintf('Saved plugin config across %d field(s).', count($schema)),
            [
                'field_count' => count($schema),
                'required_field_count' => count(array_filter(
                    $schema,
                    static fn (array $field): bool => (bool)($field['required'] ?? false)
                )),
                'submitted_field_count' => count($values),
            ]
        );

        return $this->detail($manifest['code']);
    }

    public function install(string $code, array $operator = []): array
    {
        $manifest = $this->loadManifest($code);
        $registry = $this->loadRegistry();
        $snapshot = $this->pluginSnapshot($manifest, $registry);
        $this->persistRegistrySnapshot($manifest, $snapshot, $registry);

        $state = $snapshot['state'];
        $plugin = $snapshot['plugin'];

        $this->syncManifestMigrations($manifest, $state, $snapshot['state_audit'], $manifest['version'], 'install');
        $plugin->install();
        $channelSync = $this->syncManagedChannels($manifest);

        $state['installed'] = true;
        $state['enabled'] = false;
        $state['status'] = 'disabled';
        $state['version'] = $manifest['version'];
        $state['installed_at'] = $state['installed_at'] ?: $this->now();
        $state['enabled_at'] = null;
        $state['disabled_at'] = null;
        $state['uninstalled_at'] = null;
        $state['hook_execution'] = 'install_executed';
        $state['cleanup_execution'] = 'plan_only';
        $state['last_uninstall_plan'] = null;
        $state['last_cleanup_report'] = null;
        $state = $this->touchState($state, 'install', $operator);

        $registry[$manifest['code']] = $state;
        $this->saveRegistry($registry);
        $this->recordHistoryEvent(
            $manifest['code'],
            'install',
            $state,
            $operator,
            sprintf('Installed plugin version %s and left it disabled for validation.', $manifest['version']),
            [
                'version' => $manifest['version'],
                'config_table' => $this->configTableName($manifest),
                'runtime_path' => $this->runtimeTarget($manifest['code']),
                'managed_channel_total' => (int)($channelSync['summary']['declared_count'] ?? 0),
                'managed_channel_changed_count' => (int)($channelSync['summary']['changed_count'] ?? 0),
            ]
        );

        return $this->detail($manifest['code']);
    }

    public function repair(string $code, array $operator = []): array
    {
        $manifest = $this->loadManifest($code);
        $registry = $this->loadRegistry();
        $snapshot = $this->pluginSnapshot($manifest, $registry);
        $this->persistRegistrySnapshot($manifest, $snapshot, $registry);

        $state = $snapshot['state'];
        $plugin = $snapshot['plugin'];
        $audit = $snapshot['state_audit'];

        if (!(bool)($audit['repair_recommended'] ?? false)) {
            throw new RuntimeException(
                $state['installed']
                    ? 'plugin install assets are already healthy; no repair is required'
                    : 'plugin does not currently require repair; use install to initialize it'
            );
        }

        $this->syncManifestMigrations($manifest, $state, $snapshot['state_audit'], $manifest['version'], 'repair');
        $plugin->install();
        $channelSync = $this->syncManagedChannels($manifest);

        $state['installed'] = true;
        $state['enabled'] = false;
        $state['status'] = 'disabled';
        $state['version'] = $manifest['version'];
        $state['installed_at'] = $state['installed_at'] ?: $this->now();
        $state['enabled_at'] = null;
        $state['disabled_at'] = $state['disabled_at'] ?: $this->now();
        $state['uninstalled_at'] = null;
        $state['hook_execution'] = 'repair_executed';
        $state['cleanup_execution'] = 'plan_only';
        $state['last_uninstall_plan'] = null;
        $state['last_cleanup_report'] = null;
        $state = $this->touchState($state, 'repair', $operator);

        $registry[$manifest['code']] = $state;
        $this->saveRegistry($registry);
        $this->recordHistoryEvent(
            $manifest['code'],
            'repair',
            $state,
            $operator,
            sprintf('Reconciled install assets and aligned the plugin to manifest version %s.', $manifest['version']),
            [
                'version' => $manifest['version'],
                'config_table' => $this->configTableName($manifest),
                'runtime_path' => $this->runtimeTarget($manifest['code']),
                'pending_migration_files' => (int)($snapshot['migration_audit']['pending_file_count'] ?? 0),
                'managed_channel_total' => (int)($channelSync['summary']['declared_count'] ?? 0),
                'managed_channel_changed_count' => (int)($channelSync['summary']['changed_count'] ?? 0),
            ]
        );

        return $this->detail($manifest['code']);
    }

    public function upgrade(string $code, array $operator = []): array
    {
        $manifest = $this->loadManifest($code);
        $registry = $this->loadRegistry();
        $snapshot = $this->pluginSnapshot($manifest, $registry);
        $this->persistRegistrySnapshot($manifest, $snapshot, $registry);

        $state = $snapshot['state'];
        $plugin = $snapshot['plugin'];
        $audit = $snapshot['state_audit'];

        if (!$state['installed']) {
            throw new RuntimeException('plugin must be installed before it can be upgraded');
        }

        if ((bool)($audit['repair_recommended'] ?? false)) {
            throw new RuntimeException('plugin install assets are incomplete; repair the plugin before upgrading it');
        }

        if (!(bool)($audit['upgrade_recommended'] ?? false)) {
            throw new RuntimeException('plugin is already on the latest manifest version');
        }

        $fromVersion = (string)($audit['registry_version'] ?? $state['version'] ?? '0.0.0');
        $toVersion = (string)($audit['manifest_version'] ?? $manifest['version']);
        $wasEnabled = (bool)$state['enabled'];

        $this->syncManifestMigrations($manifest, $state, $snapshot['state_audit'], $toVersion, 'upgrade');
        $plugin->upgrade($fromVersion, $toVersion);
        $channelSync = $this->syncManagedChannels($manifest);

        $state['installed'] = true;
        $state['enabled'] = false;
        $state['status'] = 'disabled';
        $state['version'] = $toVersion;
        $state['disabled_at'] = $wasEnabled ? $this->now() : ($state['disabled_at'] ?? null);
        $state['hook_execution'] = 'upgrade_executed';
        $state = $this->touchState($state, 'upgrade', $operator);

        $registry[$manifest['code']] = $state;
        $this->saveRegistry($registry);
        $this->recordHistoryEvent(
            $manifest['code'],
            'upgrade',
            $state,
            $operator,
            sprintf('Upgraded plugin from %s to %s.', $fromVersion, $toVersion),
            [
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
                'was_enabled_before_upgrade' => $wasEnabled,
                'pending_release_versions' => $this->stringList(
                    $snapshot['upgrade_preview']['pending_release_versions'] ?? []
                ),
                'managed_channel_total' => (int)($channelSync['summary']['declared_count'] ?? 0),
                'managed_channel_changed_count' => (int)($channelSync['summary']['changed_count'] ?? 0),
            ]
        );

        return $this->detail($manifest['code']);
    }

    public function enable(string $code, array $operator = []): array
    {
        $manifest = $this->loadManifest($code);
        $registry = $this->loadRegistry();
        $snapshot = $this->pluginSnapshot($manifest, $registry);
        $this->persistRegistrySnapshot($manifest, $snapshot, $registry);

        $state = $snapshot['state'];
        $audit = $snapshot['state_audit'];

        if (!$state['installed']) {
            throw new RuntimeException('plugin must be installed before it can be enabled');
        }

        if (!(bool)($audit['runtime_exists'] ?? false) || !(bool)($audit['config_table_exists'] ?? false)) {
            throw new RuntimeException('plugin install assets are incomplete; repair or reinstall the plugin before enabling it');
        }

        if ((int)($audit['managed_channel_missing_count'] ?? 0) > 0) {
            throw new RuntimeException('plugin-managed channels are incomplete; repair the plugin before enabling it');
        }

        $missingFields = $this->missingRequiredConfigFields($snapshot['config_schema']);
        if (!empty($missingFields)) {
            throw new RuntimeException('plugin config is incomplete: ' . implode(', ', $missingFields));
        }

        $state['enabled'] = true;
        $state['status'] = 'enabled';
        $state['enabled_at'] = $this->now();
        $state = $this->touchState($state, 'enable', $operator);

        $registry[$manifest['code']] = $state;
        $this->saveRegistry($registry);
        $this->recordHistoryEvent(
            $manifest['code'],
            'enable',
            $state,
            $operator,
            '已通过运行目录、配置和版本检查，并启用插件路由。',
            [
                'version' => $state['version'] ?? $manifest['version'],
                'config_table' => $this->configTableName($manifest),
            ]
        );

        return $this->detail($manifest['code']);
    }

    public function disable(string $code, array $operator = []): array
    {
        $manifest = $this->loadManifest($code);
        $registry = $this->loadRegistry();
        $snapshot = $this->pluginSnapshot($manifest, $registry);
        $this->persistRegistrySnapshot($manifest, $snapshot, $registry);

        $state = $snapshot['state'];

        if (!$state['installed']) {
            throw new RuntimeException('plugin is not installed');
        }

        $state['enabled'] = false;
        $state['status'] = 'disabled';
        $state['disabled_at'] = $this->now();
        $state = $this->touchState($state, 'disable', $operator);

        $registry[$manifest['code']] = $state;
        $this->saveRegistry($registry);
        $this->recordHistoryEvent(
            $manifest['code'],
            'disable',
            $state,
            $operator,
            '已停用插件路由，并保留安装资源。',
            [
                'version' => $state['version'] ?? $manifest['version'],
                'config_table' => $this->configTableName($manifest),
            ]
        );

        return $this->detail($manifest['code']);
    }

    public function uninstallPlan(string $code, bool $purge = false): array
    {
        $manifest = $this->loadManifest($code);

        return $this->buildUninstallPlan($manifest, $purge);
    }

    public function uninstall(string $code, bool $purge = false, array $operator = []): array
    {
        $manifest = $this->loadManifest($code);
        $registry = $this->loadRegistry();
        $snapshot = $this->pluginSnapshot($manifest, $registry);
        $this->persistRegistrySnapshot($manifest, $snapshot, $registry);

        $state = $snapshot['state'];
        $plugin = $snapshot['plugin'];

        if (!$state['installed']) {
            throw new RuntimeException('plugin is not installed');
        }

        $plugin->uninstall($purge);

        $state['installed'] = false;
        $state['enabled'] = false;
        $state['status'] = 'not_installed';
        $state['uninstalled_at'] = $this->now();
        $state['hook_execution'] = $purge ? 'uninstall_purge_requested' : 'uninstall_executed';
        $state['cleanup_execution'] = 'deferred';
        $state['last_uninstall_plan'] = $this->buildUninstallPlan($manifest, $purge);
        $state = $this->touchState($state, $purge ? 'uninstall_purge_requested' : 'uninstall', $operator);

        $registry[$manifest['code']] = $state;
        $this->saveRegistry($registry);
        $this->recordHistoryEvent(
            $manifest['code'],
            $purge ? 'uninstall_purge_requested' : 'uninstall',
            $state,
            $operator,
            $purge
                ? 'Marked plugin uninstalled and captured a purge plan for operator review.'
                : 'Marked plugin uninstalled and deferred cleanup to the safe cleanup flow.',
            [
                'purge_requested' => $purge,
                'cleanup_execution' => $state['cleanup_execution'] ?? 'deferred',
                'plan_mode' => $purge ? 'purge' : 'safe',
            ]
        );

        return $this->detail($manifest['code']);
    }

    public function cleanupSafe(string $code, array $operator = []): array
    {
        $manifest = $this->loadManifest($code);
        $registry = $this->loadRegistry();
        $snapshot = $this->pluginSnapshot($manifest, $registry);
        $this->persistRegistrySnapshot($manifest, $snapshot, $registry);

        $state = $snapshot['state'];
        $plugin = $snapshot['plugin'];

        if ($state['installed'] || $state['enabled']) {
            throw new RuntimeException('plugin must be uninstalled before safe cleanup can run');
        }

        $plan = $this->buildUninstallPlan($manifest, false);
        $hookReport = $this->executeCleanupHook($plugin, $manifest, $state, $plan, 'safe');
        $report = [
            'mode' => 'safe',
            'removed_file_count' => 0,
            'removed_table_count' => 0,
            'removed_row_count' => 0,
            'items' => [],
            'plugin_hook' => $hookReport,
            'finished_at' => $this->now(),
        ];

        foreach ($plan['file_audit'] as $fileAudit) {
            $item = $this->cleanupSafeFile($manifest['code'], $fileAudit);
            $report['items'][] = $item;
            if ($item['removed']) {
                $report['removed_file_count']++;
            }
        }

        foreach ($plan['table_audit'] as $tableAudit) {
            $item = $this->cleanupSafeTable($manifest['code'], $tableAudit);
            $report['items'][] = $item;
            if ($item['removed']) {
                $report['removed_table_count']++;
                $report['removed_row_count'] += (int)($item['row_count'] ?? 0);
            }
        }

        foreach ($plan['managed_channel_audit'] as $channelAudit) {
            $item = $this->cleanupSafeManagedChannel($manifest['code'], $channelAudit);
            $report['items'][] = $item;
            if ($item['removed']) {
                $report['removed_row_count'] += (int)($item['row_count'] ?? 0);
            }
        }

        $state['cleanup_execution'] = 'safe_completed';
        $state['last_cleanup_report'] = $report;
        $state['last_uninstall_plan'] = $this->buildUninstallPlan($manifest, false);
        $state = $this->touchState($state, 'safe_cleanup', $operator);

        $registry[$manifest['code']] = $state;
        $this->saveRegistry($registry);
        $this->recordHistoryEvent(
            $manifest['code'],
            'safe_cleanup',
            $state,
            $operator,
            sprintf(
                'Completed safe cleanup: removed %d file target(s), %d table(s), and %d row(s)%s.',
                (int)$report['removed_file_count'],
                (int)$report['removed_table_count'],
                (int)$report['removed_row_count'],
                $hookReport['executed']
                    ? sprintf(' after the plugin cleanup hook reported %d step(s)', count($hookReport['steps']))
                    : ''
            ),
            [
                'removed_file_count' => (int)$report['removed_file_count'],
                'removed_table_count' => (int)$report['removed_table_count'],
                'removed_row_count' => (int)$report['removed_row_count'],
                'target_count' => count($report['items']),
                'plugin_hook_supported' => (bool)$hookReport['supported'],
                'plugin_hook_executed' => (bool)$hookReport['executed'],
                'plugin_hook_step_count' => count($hookReport['steps']),
                'plugin_hook_summary' => $hookReport['summary'],
            ]
        );

        return [
            'plugin_code' => $manifest['code'],
            'detail' => $this->detail($manifest['code']),
            'cleanup_report' => $report,
            'plugin_removed_from_catalog' => false,
        ];
    }

    public function cleanupPurge(string $code, array $operator = []): array
    {
        $manifest = $this->loadManifest($code);
        $registry = $this->loadRegistry();
        $snapshot = $this->pluginSnapshot($manifest, $registry);
        $this->persistRegistrySnapshot($manifest, $snapshot, $registry);

        $state = $snapshot['state'];
        $plugin = $snapshot['plugin'];

        if ($state['installed'] || $state['enabled']) {
            throw new RuntimeException('plugin must be uninstalled before purge cleanup can run');
        }

        $plan = $this->buildUninstallPlan($manifest, true);
        $hookReport = $this->executeCleanupHook($plugin, $manifest, $state, $plan, 'purge');
        $report = [
            'mode' => 'purge',
            'removed_file_count' => 0,
            'removed_table_count' => 0,
            'removed_row_count' => 0,
            'items' => [],
            'plugin_hook' => $hookReport,
            'finished_at' => $this->now(),
        ];

        foreach ($plan['table_audit'] as $tableAudit) {
            $item = $this->cleanupPurgeTable($manifest['code'], $tableAudit);
            $report['items'][] = $item;
            if ($item['removed']) {
                $report['removed_table_count']++;
                $report['removed_row_count'] += (int)($item['row_count'] ?? 0);
            }
        }

        foreach ($plan['managed_channel_audit'] as $channelAudit) {
            $item = $this->cleanupPurgeManagedChannel($manifest['code'], $channelAudit);
            $report['items'][] = $item;
            if ($item['removed']) {
                $report['removed_row_count'] += (int)($item['row_count'] ?? 0);
            }
        }

        foreach ($this->orderedPurgeFileAudit($manifest['code'], $plan['file_audit']) as $fileAudit) {
            $item = $this->cleanupPurgeFile($manifest['code'], $fileAudit);
            $report['items'][] = $item;
            if ($item['removed']) {
                $report['removed_file_count']++;
            }
        }

        unset($registry[$manifest['code']]);
        $this->saveRegistry($registry);
        $pluginRemovedFromCatalog = !file_exists(base_path($this->pluginSourceTarget($manifest['code']) . '/plugin.json'));

        return [
            'plugin_code' => $manifest['code'],
            'detail' => null,
            'cleanup_report' => $report,
            'plugin_removed_from_catalog' => $pluginRemovedFromCatalog,
        ];
    }

    private function detailPayload(array $manifest, array $snapshot): array
    {
        return [
            'manifest' => $manifest,
            'state' => $snapshot['state'],
            'state_audit' => $snapshot['state_audit'],
            'migration_audit' => $snapshot['migration_audit'],
            'upgrade_preview' => $snapshot['upgrade_preview'],
            'managed_channels' => $snapshot['managed_channels'],
            'config_schema' => $this->publicConfigSchema($snapshot['config_schema']),
            'config_summary' => $snapshot['config_summary'],
            'uninstall_plan' => $this->buildUninstallPlan($manifest, false),
            'purge_plan' => $this->buildUninstallPlan($manifest, true),
            'history' => $this->historyPayload($manifest['code']),
        ];
    }

    private function persistRegistrySnapshot(array $manifest, array $snapshot, array $registry): void
    {
        if (!(bool)($snapshot['registry_changed'] ?? false)) {
            return;
        }

        $this->saveRegistry($registry);

        if (!(bool)($snapshot['state_audit']['reconciled'] ?? false)) {
            return;
        }

        $issues = $this->stringList($snapshot['state_audit']['issues'] ?? []);
        $reconciledActions = $this->stringList($snapshot['state_audit']['reconciled_actions'] ?? []);

        $this->recordHistoryEvent(
            $manifest['code'],
            'state_reconciled',
            is_array($snapshot['state'] ?? null) ? $snapshot['state'] : [],
            [],
            empty($issues)
                ? 'Auto-reconciled stored registry state to match the effective plugin state.'
                : 'Auto-reconciled stored registry state after drift was detected during plugin inspection.',
            [
                'issues' => $issues,
                'reconciled_actions' => $reconciledActions,
                'registry_status' => (string)($snapshot['state_audit']['registry_status'] ?? ''),
                'effective_status' => (string)($snapshot['state_audit']['effective_status'] ?? ''),
                'runtime_exists' => (bool)($snapshot['state_audit']['runtime_exists'] ?? false),
                'config_table_exists' => (bool)($snapshot['state_audit']['config_table_exists'] ?? false),
            ]
        );
    }

    private function discoverManifests(): array
    {
        $pluginRoot = $this->pluginRootPath();
        if (!is_dir($pluginRoot)) {
            return [];
        }

        $directories = glob($pluginRoot . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
        $manifests = [];

        foreach ($directories as $directory) {
            $manifestPath = $directory . DIRECTORY_SEPARATOR . 'plugin.json';
            if (!is_file($manifestPath)) {
                continue;
            }

            $manifests[] = $this->parseManifest($manifestPath, basename($directory));
        }

        return $manifests;
    }

    private function loadManifest(string $code): array
    {
        $code = $this->normalizeCode($code);
        $manifestPath = $this->pluginRootPath() . DIRECTORY_SEPARATOR . $code . DIRECTORY_SEPARATOR . 'plugin.json';
        if (!is_file($manifestPath)) {
            throw new InvalidArgumentException("payment plugin [$code] was not found");
        }

        return $this->parseManifest($manifestPath, $code);
    }

    private function parseManifest(string $manifestPath, string $directoryCode): array
    {
        $contents = file_get_contents($manifestPath);
        $decoded = json_decode($contents ?: '', true);
        if (!is_array($decoded)) {
            throw new RuntimeException('invalid plugin manifest: ' . $manifestPath);
        }

        $manifestCode = $this->normalizeCode((string)($decoded['code'] ?? $directoryCode));
        if ($manifestCode !== $directoryCode) {
            throw new RuntimeException("plugin manifest code [$manifestCode] does not match directory [$directoryCode]");
        }

        $cleanup = is_array($decoded['cleanup'] ?? null) ? $decoded['cleanup'] : [];
        $safe = is_array($cleanup['safe'] ?? null) ? $cleanup['safe'] : [];
        $purge = is_array($cleanup['purge'] ?? null) ? $cleanup['purge'] : [];

        $manifestVersion = trim((string)($decoded['version'] ?? '0.0.0'));
        $manifestDirectory = "plugins/payments/$manifestCode";
        $migrations = $this->normalizeManifestMigrations(
            $manifestDirectory,
            $manifestCode,
            $manifestVersion,
            $decoded['migrations'] ?? null
        );

        return [
            'code' => $manifestCode,
            'name' => trim((string)($decoded['name'] ?? $manifestCode)),
            'description' => trim((string)($decoded['description'] ?? '')),
            'version' => $manifestVersion,
            'provider' => trim((string)($decoded['provider'] ?? 'unknown')),
            'entry' => trim((string)($decoded['entry'] ?? "plugins/payments/$manifestCode/src/Plugin.php")),
            'class' => trim((string)($decoded['class'] ?? '')),
            'directory' => $manifestDirectory,
            'capabilities' => $this->stringList($decoded['capabilities'] ?? []),
            'merchant_enabled' => (bool)($decoded['merchant_enabled'] ?? true),
            'channel_type' => trim((string)($decoded['channel_type'] ?? '')),
            'supported_payment_types' => array_values(array_unique(array_map(
                static function (string $value): string {
                    $normalized = strtolower(trim($value));

                    return match ($normalized) {
                        'wechat' => 'wxpay',
                        'qq' => 'qqpay',
                        default => $normalized,
                    };
                },
                $this->stringList($decoded['supported_payment_types'] ?? [])
            ))),
            'managed_channels' => $this->normalizeManagedChannels($manifestCode, $decoded['managed_channels'] ?? []),
            'migrations' => $migrations,
            'upgrade' => $this->normalizeUpgradeMetadata($manifestVersion, $decoded['upgrade'] ?? null, $migrations),
            'cleanup' => [
                'safe' => [
                    'files' => $this->stringList($safe['files'] ?? ["runtime/payment-plugins/$manifestCode"]),
                    'tables' => $this->stringList($safe['tables'] ?? []),
                    'notes' => $this->stringList($safe['notes'] ?? [
                        'Phase 1 only records this cleanup plan. No files or tables are deleted automatically.',
                    ]),
                ],
                'purge' => [
                    'files' => $this->stringList($purge['files'] ?? ["plugins/payments/$manifestCode"]),
                    'tables' => $this->stringList($purge['tables'] ?? []),
                    'notes' => $this->stringList($purge['notes'] ?? [
                        'Purge is deferred until plugin-specific cleanup has been audited and explicitly approved.',
                    ]),
                ],
                'retain' => $this->stringList($cleanup['retain'] ?? [
                    'merchant order history',
                    'recharge records',
                    'fund and balance logs',
                    'settlement records',
                    'notify and audit traces',
                ]),
                'purge_requires_confirmation' => (bool)($cleanup['purge_requires_confirmation'] ?? true),
            ],
        ];
    }

    private function buildUninstallPlan(array $manifest, bool $purge): array
    {
        $mode = $purge ? 'purge' : 'safe';
        $targets = $this->cleanupTargets($manifest, $purge);
        $fileAudit = array_map(fn (string $target): array => $this->auditCleanupFile($target), $targets['files']);
        $tableAudit = array_map(fn (string $table): array => $this->auditCleanupTable($table), $targets['tables']);
        $channelAudit = $this->managedChannelAudit($manifest, $purge);
        $snapshotGuard = $this->snapshotGuardPayload($manifest['code'], $purge);

        return [
            'plugin_code' => $manifest['code'],
            'mode' => $mode,
            'will_execute_now' => false,
            'execution_mode' => 'plan_only',
            'requires_confirmation' => $purge ? (bool)$manifest['cleanup']['purge_requires_confirmation'] : false,
            'files' => $targets['files'],
            'tables' => $targets['tables'],
            'file_audit' => $fileAudit,
            'table_audit' => $tableAudit,
            'managed_channel_audit' => $channelAudit,
            'summary' => [
                'existing_file_count' => count(array_filter(
                    $fileAudit,
                    static fn (array $item): bool => (bool)($item['exists'] ?? false)
                )),
                'existing_table_count' => count(array_filter(
                    $tableAudit,
                    static fn (array $item): bool => (bool)($item['exists'] ?? false)
                )),
                'table_row_count' => array_reduce(
                    $tableAudit,
                    static fn (int $carry, array $item): int => $carry + (int)($item['row_count'] ?? 0),
                    0
                ),
                'managed_channel_count' => count($channelAudit),
                'existing_managed_channel_count' => count(array_filter(
                    $channelAudit,
                    static fn (array $item): bool => (bool)($item['exists'] ?? false)
                )),
                'deletable_managed_channel_count' => count(array_filter(
                    $channelAudit,
                    static fn (array $item): bool => (bool)($item['can_cleanup'] ?? false)
                )),
                'blocked_managed_channel_count' => count(array_filter(
                    $channelAudit,
                    static fn (array $item): bool => (bool)($item['exists'] ?? false) && !(bool)($item['can_cleanup'] ?? false)
                )),
            ],
            'snapshot_guard' => $snapshotGuard,
            'retain_scopes' => $manifest['cleanup']['retain'],
            'notes' => array_values(array_unique(array_merge(
                $this->snapshotGuardNotes($manifest['code'], $purge, $snapshotGuard),
                $targets['notes']
            ))),
        ];
    }

    private function snapshotGuardPayload(string $code, bool $purge): array
    {
        $snapshots = $this->snapshotsPayload($code);
        $items = array_values(is_array($snapshots['items'] ?? null) ? $snapshots['items'] : []);
        $latest = is_array($items[0] ?? null) ? $items[0] : null;

        return [
            'mode' => $purge ? 'purge' : 'safe',
            'snapshot_total' => (int)($snapshots['total'] ?? 0),
            'has_snapshot' => !empty($items),
            'latest_snapshot_id' => is_array($latest) ? (string)($latest['snapshot_id'] ?? '') : null,
            'latest_snapshot_label' => is_array($latest) ? ($latest['label'] ?? null) : null,
            'latest_snapshot_created_at' => is_array($latest) ? ($latest['created_at'] ?? null) : null,
            'purge_confirmation_phrase' => $this->purgeConfirmationPhrase($code),
            'missing_snapshot_confirmation_phrase' => $this->purgeWithoutSnapshotConfirmationPhrase($code),
            'warning' => $purge && empty($items)
                ? 'No recovery snapshot is currently available for this plugin. Capture one before purge cleanup, or explicitly acknowledge irreversible removal.'
                : null,
        ];
    }

    private function snapshotGuardNotes(string $code, bool $purge, ?array $guard = null): array
    {
        $guard = is_array($guard) ? $guard : $this->snapshotGuardPayload($code, $purge);

        if (!$purge || (bool)($guard['has_snapshot'] ?? false)) {
            return [];
        }

        return [
            'No recovery snapshot exists for this plugin right now.',
            'Capture a recovery snapshot before purge cleanup whenever possible.',
            'If you intentionally accept irreversible removal, purge confirmation must use [' . $this->purgeWithoutSnapshotConfirmationPhrase($code) . '].',
        ];
    }

    private function snapshotsPayload(string $code): array
    {
        $items = [];
        $directory = $this->snapshotDirectoryPath($code);

        if (is_dir($directory)) {
            $paths = glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [];

            foreach ($paths as $path) {
                if (!is_file($path)) {
                    continue;
                }

                $contents = file_get_contents($path);
                $decoded = json_decode($contents ?: '', true);
                if (!is_array($decoded)) {
                    continue;
                }

                try {
                    $items[] = $this->snapshotMetadata($decoded, $path);
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        usort($items, static function (array $left, array $right): int {
            return strcmp((string)($right['created_at'] ?? ''), (string)($left['created_at'] ?? ''));
        });

        return [
            'plugin_code' => $code,
            'snapshot_directory' => $this->snapshotDirectoryRelativePath($code),
            'total' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @param mixed $value
     * @return list<array<string, mixed>>
     */
    private function normalizeManagedChannels(string $pluginCode, mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $code = $this->normalizeManagedChannelCode($pluginCode, (string)($item['code'] ?? ''));
            $items[$code] = [
                'code' => $code,
                'name' => $this->requireManifestManagedChannelString($item['name'] ?? null, 50, 'managed channel name'),
                'type' => $this->normalizeManagedChannelType($item['type'] ?? null),
                'info' => $this->normalizeManifestManagedChannelInfo($item['info'] ?? null),
                'status' => $this->normalizeManagedChannelStatus($item['status'] ?? 1),
                'sort' => $this->normalizeManagedChannelInteger($item['sort'] ?? 0, 'managed channel sort', 0, 999999),
                'maxcount' => $this->normalizeManagedChannelInteger($item['maxcount'] ?? 10, 'managed channel maxcount', 0, 999999),
            ];
        }

        return array_values($items);
    }

    private function normalizeManagedChannelCode(string $pluginCode, string $code): string
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            throw new RuntimeException('managed channel code is required');
        }

        if (mb_strlen($code) > 50) {
            throw new RuntimeException('managed channel code is too long');
        }

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $code)) {
            throw new RuntimeException('managed channel code must start with a letter and contain only lowercase letters, digits, or underscores');
        }

        if ($code !== $pluginCode && !str_starts_with($code, $pluginCode . '_')) {
            throw new RuntimeException(
                'managed channel code must equal the plugin code [' . $pluginCode . '] or start with the plugin code prefix [' . $pluginCode . '_]'
            );
        }

        return $code;
    }

    private function requireManifestManagedChannelString(mixed $value, int $maxLength, string $field): string
    {
        if (is_bool($value) || is_array($value) || is_object($value)) {
            throw new RuntimeException($field . ' must be a scalar');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            throw new RuntimeException($field . ' is required');
        }

        if (mb_strlen($normalized) > $maxLength) {
            throw new RuntimeException($field . ' is too long');
        }

        return $normalized;
    }

    private function normalizeManifestManagedChannelInfo(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value) || is_array($value) || is_object($value)) {
            throw new RuntimeException('managed channel info must be a scalar');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) > 225) {
            throw new RuntimeException('managed channel info is too long');
        }

        return $normalized;
    }

    private function normalizeManagedChannelType(mixed $value): string
    {
        if (is_bool($value) || is_array($value) || is_object($value)) {
            throw new RuntimeException('managed channel type must be a scalar');
        }

        $normalized = $this->normalizePaymentTypeAlias((string)$value);
        if ($normalized === '') {
            throw new RuntimeException('managed channel type is required');
        }

        if (!$this->paymentTypeExists($normalized)) {
            throw new RuntimeException('managed channel payment type is not supported');
        }

        return $normalized;
    }

    private function normalizeManagedChannelStatus(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_numeric($value)) {
            $status = (int)$value;
            if (in_array($status, [0, 1], true)) {
                return $status;
            }
        }

        $normalized = strtolower(trim((string)$value));

        return match ($normalized) {
            '1', 'true', 'yes', 'on', 'enable', 'enabled' => 1,
            '0', 'false', 'no', 'off', 'disable', 'disabled' => 0,
            default => throw new RuntimeException('managed channel status must be 0 or 1'),
        };
    }

    private function normalizeManagedChannelInteger(mixed $value, string $field, int $min, int $max): int
    {
        if (is_bool($value) || is_array($value) || is_object($value)) {
            throw new RuntimeException($field . ' must be an integer');
        }

        $normalized = trim((string)$value);
        if ($normalized === '' || !preg_match('/^-?\d+$/', $normalized)) {
            throw new RuntimeException($field . ' must be an integer');
        }

        $integer = (int)$normalized;
        if ($integer < $min || $integer > $max) {
            throw new RuntimeException($field . ' is out of range');
        }

        return $integer;
    }

    private function recoveryVaultPayload(): array
    {
        $root = runtime_path(self::SNAPSHOT_ROOT);
        $registry = $this->loadRegistry();
        $items = [];

        if (is_dir($root)) {
            $paths = glob($root . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*.json') ?: [];

            foreach ($paths as $path) {
                if (!is_file($path)) {
                    continue;
                }

                $contents = file_get_contents($path);
                $decoded = json_decode($contents ?: '', true);
                if (!is_array($decoded)) {
                    continue;
                }

                try {
                    $items[] = $this->recoveryVaultItem($decoded, $path, $registry);
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        usort($items, static function (array $left, array $right): int {
            return strcmp((string)($right['created_at'] ?? ''), (string)($left['created_at'] ?? ''));
        });

        $pluginCodes = [];
        $catalogMissingCodes = [];
        $restoreReadyCount = 0;

        foreach ($items as $item) {
            $code = (string)($item['plugin_code'] ?? '');
            if ($code !== '') {
                $pluginCodes[$code] = true;
                if (!(bool)($item['catalog_available'] ?? false)) {
                    $catalogMissingCodes[$code] = true;
                }
            }

            if ((bool)($item['restorable'] ?? false)) {
                $restoreReadyCount++;
            }
        }

        return [
            'snapshot_root' => 'runtime/' . self::SNAPSHOT_ROOT,
            'summary' => [
                'total_snapshots' => count($items),
                'plugin_count' => count($pluginCodes),
                'catalog_missing_count' => count($catalogMissingCodes),
                'restore_ready_count' => $restoreReadyCount,
            ],
            'items' => $items,
        ];
    }

    private function paymentTypeExists(string $type): bool
    {
        if ($type === '') {
            return false;
        }

        CorePaymentMethodCatalog::seedWhenTableEmpty();

        $exists = Db::table('ypay_payment')
            ->where('type', $type)
            ->exists();

        if ($exists) {
            return true;
        }

        return CorePaymentMethodCatalog::isCoreType($type);
    }

    private function normalizePaymentTypeAlias(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'wechat' => 'wxpay',
            'qq' => 'qqpay',
            default => $normalized,
        };
    }

    private function managedChannelAudit(array $manifest, bool $purge): array
    {
        $declared = [];
        foreach ($manifest['managed_channels'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $code = trim((string)($item['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $declared[$code] = $item;
        }

        $rows = $this->loadManagedChannelRows($manifest['code']);
        $rowMap = [];
        foreach ($rows as $row) {
            $rowCode = trim((string)($row['code'] ?? ''));
            if ($rowCode === '') {
                continue;
            }

            $rowMap[$rowCode] = $row;
        }

        $codes = array_values(array_unique(array_merge(array_keys($declared), array_keys($rowMap))));
        sort($codes);

        $audit = [];
        foreach ($codes as $code) {
            $row = $rowMap[$code] ?? null;
            $definition = $declared[$code] ?? null;
            $stats = $this->loadManagedChannelStats($code);
            $dependencySummary = $this->managedChannelDependencySummary($stats);
            $exists = is_array($row);
            $deleted = $exists && trim((string)($row['delete_time'] ?? '')) !== '';
            $canCleanup = false;
            $blockingReasons = [];
            $drift = [];

            if (!$exists) {
                $blockingReasons[] = 'Managed channel row is currently missing from admin_channel.';
            } else {
                if ((int)($row['create_type'] ?? 0) !== self::CHANNEL_CREATE_TYPE_PLUGIN) {
                    $blockingReasons[] = 'Managed channel row does not use create_type = 2.';
                }

                if ($dependencySummary['account_count'] > 0) {
                    $blockingReasons[] = sprintf('Channel is still referenced by %d payment account(s).', $dependencySummary['account_count']);
                }

                if ($dependencySummary['pool_item_count'] > 0) {
                    $blockingReasons[] = sprintf('Channel is still referenced by %d pool item(s).', $dependencySummary['pool_item_count']);
                }

                if ($dependencySummary['order_count'] > 0) {
                    $blockingReasons[] = sprintf('Channel still has %d historical order(s) linked through payment accounts.', $dependencySummary['order_count']);
                }

                if (!$purge && !$this->channelTableHasDeleteTime()) {
                    $blockingReasons[] = $this->channelRecycleMigrationError();
                }

                $canCleanup = $blockingReasons === [];
            }

            if (is_array($definition) && $exists) {
                foreach (['name', 'type', 'info', 'status', 'sort', 'maxcount'] as $field) {
                    $expected = $definition[$field] ?? null;
                    $actual = $row[$field] ?? null;
                    if ((string)$expected !== (string)($actual ?? '')) {
                        $drift[$field] = [
                            'expected' => $expected,
                            'actual' => $actual,
                        ];
                    }
                }
            }

            $audit[] = [
                'code' => $code,
                'declared' => is_array($definition),
                'exists' => $exists,
                'deleted' => $deleted,
                'can_cleanup' => $canCleanup,
                'mode' => $purge ? 'purge' : 'safe',
                'row' => $row,
                'definition' => $definition,
                'drift' => $drift,
                'blocking_reasons' => $blockingReasons,
                'dependency_summary' => $dependencySummary,
            ];
        }

        return $audit;
    }

    private function registryResidueManagedChannelAudit(string $pluginCode): array
    {
        $audit = [];
        foreach ($this->loadManagedChannelRows($pluginCode) as $row) {
            $code = trim((string)($row['code'] ?? ''));
            if ($code === '' || !$this->isAllowedManagedChannelCode($pluginCode, $code)) {
                continue;
            }

            $dependencySummary = $this->managedChannelDependencySummary($this->loadManagedChannelStats($code));
            $blockingReasons = [];

            if ((int)($row['create_type'] ?? 0) !== self::CHANNEL_CREATE_TYPE_PLUGIN) {
                $blockingReasons[] = 'Managed channel row does not use create_type = 2.';
            }

            if ($dependencySummary['account_count'] > 0) {
                $blockingReasons[] = sprintf('Channel is still referenced by %d payment account(s).', $dependencySummary['account_count']);
            }

            if ($dependencySummary['pool_item_count'] > 0) {
                $blockingReasons[] = sprintf('Channel is still referenced by %d pool item(s).', $dependencySummary['pool_item_count']);
            }

            if ($dependencySummary['order_count'] > 0) {
                $blockingReasons[] = sprintf(
                    'Channel still has %d historical order(s) linked through payment accounts.',
                    $dependencySummary['order_count']
                );
            }

            $audit[] = [
                'code' => $code,
                'declared' => false,
                'exists' => true,
                'deleted' => trim((string)($row['delete_time'] ?? '')) !== '',
                'can_cleanup' => $blockingReasons === [],
                'mode' => 'purge',
                'row' => $row,
                'definition' => null,
                'drift' => [],
                'blocking_reasons' => $blockingReasons,
                'dependency_summary' => $dependencySummary,
            ];
        }

        return $audit;
    }

    private function loadManagedChannelRows(string $pluginCode): array
    {
        $pattern = $pluginCode . '\_%';

        $rows = Db::table('admin_channel')
            ->where(function ($query) use ($pluginCode, $pattern): void {
                $query->where('code', $pluginCode)
                    ->orWhere('code', 'like', $pattern);
            })
            ->orderBy('id')
            ->get();

        $items = json_decode(json_encode($rows), true);
        return is_array($items) ? array_values(array_filter($items, static fn (mixed $row): bool => is_array($row))) : [];
    }

    private function loadManagedChannelRow(string $code): ?array
    {
        $row = Db::table('admin_channel')
            ->where('code', $code)
            ->first();

        if ($row === null) {
            return null;
        }

        $normalized = json_decode(json_encode($row), true);
        return is_array($normalized) ? $normalized : null;
    }

    private function loadManagedChannelStats(string $code): array
    {
        $stats = [
            'account_count' => 0,
            'merchant_count' => 0,
            'online_account_count' => 0,
            'enabled_account_count' => 0,
            'pool_count' => 0,
            'pool_item_count' => 0,
            'order_count' => 0,
            'paid_order_count' => 0,
            'paid_amount' => 0,
            'latest_account_time' => null,
            'latest_order_time' => null,
        ];

        $account = Db::table('ypay_account')
            ->selectRaw('COUNT(*) as account_count')
            ->selectRaw('COUNT(DISTINCT user_id) as merchant_count')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as online_account_count')
            ->selectRaw('SUM(CASE WHEN is_status = 1 THEN 1 ELSE 0 END) as enabled_account_count')
            ->selectRaw('MAX(update_time) as latest_account_time')
            ->where('code', $code)
            ->first();
        if ($account !== null) {
            $stats = array_merge($stats, (array)$account);
        }

        $pool = Db::table('ypay_poll_pool_item as item')
            ->join('ypay_account as account', 'account.id', '=', 'item.account_id')
            ->selectRaw('COUNT(item.id) as pool_item_count')
            ->selectRaw('COUNT(DISTINCT item.pool_id) as pool_count')
            ->where('account.code', $code)
            ->first();
        if ($pool !== null) {
            $stats = array_merge($stats, (array)$pool);
        }

        $orders = Db::table('ypay_order as orders')
            ->join('ypay_account as account', 'account.id', '=', 'orders.account_id')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('SUM(CASE WHEN orders.status = 1 THEN 1 ELSE 0 END) as paid_order_count')
            ->selectRaw(
                'SUM(CASE WHEN orders.status = 1 THEN CASE WHEN orders.type = "usdt" THEN orders.money ELSE orders.truemoney END ELSE 0 END) as paid_amount'
            )
            ->selectRaw('MAX(orders.create_time) as latest_order_time')
            ->where('account.code', $code)
            ->first();
        if ($orders !== null) {
            $stats = array_merge($stats, (array)$orders);
        }

        return $stats;
    }

    private function managedChannelDependencySummary(array $stats): array
    {
        return [
            'account_count' => max(0, (int)($stats['account_count'] ?? 0)),
            'merchant_count' => max(0, (int)($stats['merchant_count'] ?? 0)),
            'online_account_count' => max(0, (int)($stats['online_account_count'] ?? 0)),
            'enabled_account_count' => max(0, (int)($stats['enabled_account_count'] ?? 0)),
            'pool_count' => max(0, (int)($stats['pool_count'] ?? 0)),
            'pool_item_count' => max(0, (int)($stats['pool_item_count'] ?? 0)),
            'order_count' => max(0, (int)($stats['order_count'] ?? 0)),
            'paid_order_count' => max(0, (int)($stats['paid_order_count'] ?? 0)),
            'paid_amount' => (float)($stats['paid_amount'] ?? 0),
            'latest_account_time' => $stats['latest_account_time'] ?? null,
            'latest_order_time' => $stats['latest_order_time'] ?? null,
        ];
    }

    private function syncManagedChannels(array $manifest): array
    {
        $changes = [];

        foreach ($manifest['managed_channels'] as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $code = (string)($definition['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $row = $this->loadManagedChannelRow($code);
            $payload = [
                'name' => $definition['name'],
                'type' => $definition['type'],
                'create_type' => self::CHANNEL_CREATE_TYPE_PLUGIN,
                'code' => $code,
                'info' => $definition['info'],
                'status' => $definition['status'],
                'sort' => $definition['sort'],
                'maxcount' => $definition['maxcount'],
            ];

            if ($row === null) {
                $payload['create_time'] = $this->now();
                $payload['delete_time'] = null;
                Db::table('admin_channel')->insert($payload);
                $changes[] = ['code' => $code, 'action' => 'created'];
                continue;
            }

            $update = [];
            foreach (['name', 'type', 'create_type', 'info', 'status', 'sort', 'maxcount'] as $field) {
                if ((string)($row[$field] ?? '') !== (string)($payload[$field] ?? '')) {
                    $update[$field] = $payload[$field];
                }
            }

            if (array_key_exists('delete_time', $row) && trim((string)($row['delete_time'] ?? '')) !== '') {
                $update['delete_time'] = null;
            }

            if ($update !== []) {
                Db::table('admin_channel')
                    ->where('id', (int)$row['id'])
                    ->update($update);
                $changes[] = [
                    'code' => $code,
                    'action' => 'updated',
                    'fields' => array_keys($update),
                ];
            }
        }

        return [
            'summary' => [
                'declared_count' => count($manifest['managed_channels']),
                'changed_count' => count($changes),
            ],
            'changes' => $changes,
        ];
    }

    private function buildSnapshotManagedChannelArchives(string $code, array $channelAudit): array
    {
        $archives = [];

        foreach ($channelAudit as $item) {
            if (!is_array($item)) {
                continue;
            }

            $channelCode = trim((string)($item['code'] ?? ''));
            if ($channelCode === '' || !$this->isAllowedManagedChannelCode($code, $channelCode)) {
                continue;
            }

            $row = is_array($item['row'] ?? null) ? $item['row'] : null;
            $archives[] = [
                'code' => $channelCode,
                'exists' => $row !== null,
                'row' => $row,
                'dependency_summary' => is_array($item['dependency_summary'] ?? null) ? $item['dependency_summary'] : null,
            ];
        }

        return $archives;
    }

    private function restoreSnapshotManagedChannelArchive(string $pluginCode, array $archive): void
    {
        $code = trim((string)($archive['code'] ?? ''));
        if ($code === '' || !$this->isAllowedManagedChannelCode($pluginCode, $code)) {
            throw new RuntimeException('snapshot managed channel code is outside the allowed plugin scope');
        }

        $existing = $this->loadManagedChannelRow($code);
        if ($existing !== null) {
            Db::table('admin_channel')->where('id', (int)$existing['id'])->delete();
        }

        if (!(bool)($archive['exists'] ?? false)) {
            return;
        }

        $row = is_array($archive['row'] ?? null) ? $archive['row'] : null;
        if ($row === null) {
            throw new RuntimeException('snapshot managed channel payload is missing row data for [' . $code . ']');
        }

        unset($row['id']);
        Db::table('admin_channel')->insert($row);
    }

    private function cleanupSafeManagedChannel(string $pluginCode, array $audit): array
    {
        $code = trim((string)($audit['code'] ?? ''));
        $row = is_array($audit['row'] ?? null) ? $audit['row'] : null;

        if ($code === '' || $row === null) {
            return [
                'type' => 'managed_channel',
                'target' => $code,
                'removed' => false,
                'kind' => 'missing',
                'reason' => 'managed channel row missing',
                'row_count' => null,
            ];
        }

        if (!$this->isAllowedManagedChannelCode($pluginCode, $code)) {
            throw new RuntimeException("managed channel [$code] is outside the allowed plugin namespace");
        }

        if (!(bool)($audit['can_cleanup'] ?? false)) {
            return [
                'type' => 'managed_channel',
                'target' => $code,
                'removed' => false,
                'kind' => 'row',
                'reason' => implode(' ', $this->stringList($audit['blocking_reasons'] ?? [])),
                'row_count' => 1,
            ];
        }

        if (!$this->channelTableHasDeleteTime()) {
            throw new RuntimeException($this->channelRecycleMigrationError());
        }

        Db::table('admin_channel')
            ->where('id', (int)$row['id'])
            ->update([
                'delete_time' => $this->now(),
            ]);

        return [
            'type' => 'managed_channel',
            'target' => $code,
            'removed' => true,
            'kind' => 'row',
            'reason' => null,
            'row_count' => 1,
        ];
    }

    private function cleanupPurgeManagedChannel(string $pluginCode, array $audit): array
    {
        $code = trim((string)($audit['code'] ?? ''));
        $row = is_array($audit['row'] ?? null) ? $audit['row'] : null;

        if ($code === '' || $row === null) {
            return [
                'type' => 'managed_channel',
                'target' => $code,
                'removed' => false,
                'kind' => 'missing',
                'reason' => 'managed channel row missing',
                'row_count' => null,
            ];
        }

        if (!$this->isAllowedManagedChannelCode($pluginCode, $code)) {
            throw new RuntimeException("managed channel [$code] is outside the allowed plugin namespace");
        }

        if (!(bool)($audit['can_cleanup'] ?? false)) {
            return [
                'type' => 'managed_channel',
                'target' => $code,
                'removed' => false,
                'kind' => 'row',
                'reason' => implode(' ', $this->stringList($audit['blocking_reasons'] ?? [])),
                'row_count' => 1,
            ];
        }

        Db::table('admin_channel')
            ->where('id', (int)$row['id'])
            ->delete();

        return [
            'type' => 'managed_channel',
            'target' => $code,
            'removed' => true,
            'kind' => 'row',
            'reason' => null,
            'row_count' => 1,
        ];
    }

    private function isAllowedManagedChannelCode(string $pluginCode, string $channelCode): bool
    {
        $pluginCode = $this->normalizeCode($pluginCode);
        $channelCode = strtolower(trim($channelCode));

        return $channelCode !== ''
            && preg_match('/^[a-z][a-z0-9_]*$/', $channelCode) === 1
            && ($channelCode === $pluginCode || str_starts_with($channelCode, $pluginCode . '_'));
    }

    private function channelTableHasDeleteTime(): bool
    {
        return DatabaseColumnInspector::hasColumn('admin_channel', 'delete_time');
    }

    private function channelRecycleMigrationError(): string
    {
        return 'channel recycle support requires the admin_channel.delete_time migration';
    }

    private function registryResiduesPayload(): array
    {
        $registry = $this->loadRegistry();
        $manifestCodes = [];
        foreach ($this->discoverManifests() as $manifest) {
            $manifestCodes[$manifest['code']] = true;
        }

        $items = [];
        foreach ($registry as $code => $record) {
            try {
                $normalizedCode = $this->normalizeCode((string)$code);
            } catch (\Throwable) {
                continue;
            }

            if (isset($manifestCodes[$normalizedCode])) {
                continue;
            }

            $items[] = $this->registryResidueItem(
                $normalizedCode,
                is_array($record) ? $record : []
            );
        }

        usort($items, static function (array $left, array $right): int {
            return strcmp((string)($left['plugin_code'] ?? ''), (string)($right['plugin_code'] ?? ''));
        });

        $runtimeResidueCount = 0;
        $historyResidueCount = 0;
        $snapshotBackedCount = 0;
        $tableResidueCount = 0;
        $rowResidueCount = 0;
        $managedChannelResidueCount = 0;
        $blockedManagedChannelCount = 0;

        foreach ($items as $item) {
            if ((bool)($item['runtime_audit']['exists'] ?? false)) {
                $runtimeResidueCount++;
            }

            if ((bool)($item['history_audit']['exists'] ?? false)) {
                $historyResidueCount++;
            }

            if ((bool)($item['snapshot_guard']['has_snapshot'] ?? false)) {
                $snapshotBackedCount++;
            }

            $tableResidueCount += (int)($item['summary']['existing_table_count'] ?? 0);
            $rowResidueCount += (int)($item['summary']['table_row_count'] ?? 0);
            $managedChannelResidueCount += (int)($item['summary']['existing_managed_channel_count'] ?? 0);
            $blockedManagedChannelCount += (int)($item['summary']['blocked_managed_channel_count'] ?? 0);
        }

        return [
            'summary' => [
                'total_items' => count($items),
                'runtime_residue_count' => $runtimeResidueCount,
                'history_residue_count' => $historyResidueCount,
                'snapshot_backed_count' => $snapshotBackedCount,
                'table_residue_count' => $tableResidueCount,
                'table_row_count' => $rowResidueCount,
                'managed_channel_residue_count' => $managedChannelResidueCount,
                'blocked_managed_channel_count' => $blockedManagedChannelCount,
            ],
            'items' => $items,
        ];
    }

    private function registryResidueLedgerPayload(int $limit = 12): array
    {
        $ledger = $this->loadRegistryResidueLedger();
        $entries = array_values(is_array($ledger['entries'] ?? null) ? $ledger['entries'] : []);
        $items = $limit > 0 ? array_slice($entries, 0, $limit) : $entries;

        $withoutSnapshotCount = 0;
        $snapshotRetainedCount = 0;
        $removedFileCount = 0;
        $removedTableCount = 0;
        $removedRowCount = 0;
        $removedManagedChannelCount = 0;
        $latestEventAt = null;

        foreach ($entries as $entry) {
            $details = is_array($entry['details'] ?? null) ? $entry['details'] : [];
            if (($details['cleanup_guard_mode'] ?? 'snapshot_backed') === 'without_snapshot') {
                $withoutSnapshotCount++;
            }

            if ((bool)($details['snapshot_retained'] ?? false)) {
                $snapshotRetainedCount++;
            }

            $removedFileCount += (int)($details['removed_file_count'] ?? 0);
            $removedTableCount += (int)($details['removed_table_count'] ?? 0);
            $removedRowCount += (int)($details['removed_row_count'] ?? 0);
            $removedManagedChannelCount += (int)($details['removed_managed_channel_count'] ?? 0);

            if ($latestEventAt === null && trim((string)($entry['created_at'] ?? '')) !== '') {
                $latestEventAt = (string)$entry['created_at'];
            }
        }

        return [
            'ledger_path' => $this->registryResidueLedgerRelativePath(),
            'summary' => [
                'total_events' => count($entries),
                'visible_items' => count($items),
                'without_snapshot_count' => $withoutSnapshotCount,
                'snapshot_retained_count' => $snapshotRetainedCount,
                'removed_file_count' => $removedFileCount,
                'removed_table_count' => $removedTableCount,
                'removed_row_count' => $removedRowCount,
                'removed_managed_channel_count' => $removedManagedChannelCount,
                'latest_event_at' => $latestEventAt,
            ],
            'items' => $items,
        ];
    }

    private function registryResidueItem(string $code, array $record): array
    {
        $version = trim((string)($record['version'] ?? '0.0.0')) ?: '0.0.0';
        $state = $this->stateFor($code, $version, [$code => $record]);
        $runtimeAudit = $this->auditCleanupFile($this->runtimeTarget($code));
        $historyAudit = $this->auditCleanupFile($this->historyDirectoryTarget($code));
        $pluginDirectoryAudit = $this->auditCleanupFile($this->pluginSourceTarget($code));
        $tableAudit = $this->namespacedTableAudit($code);
        $managedChannelAudit = $this->registryResidueManagedChannelAudit($code);
        $snapshotGuard = $this->registryResidueGuardPayload($code);

        return [
            'plugin_code' => $code,
            'catalog_available' => $this->pluginCatalogAvailable($code),
            'current_state' => $this->historyStateSnapshot($state),
            'runtime_audit' => $runtimeAudit,
            'history_audit' => $historyAudit,
            'plugin_directory_audit' => $pluginDirectoryAudit,
            'table_audit' => $tableAudit,
            'managed_channel_audit' => $managedChannelAudit,
            'snapshot_guard' => $snapshotGuard,
            'summary' => [
                'existing_file_target_count' => count(array_filter(
                    [$runtimeAudit, $historyAudit, $pluginDirectoryAudit],
                    static fn (array $item): bool => (bool)($item['exists'] ?? false)
                )),
                'existing_table_count' => count(array_filter(
                    $tableAudit,
                    static fn (array $item): bool => (bool)($item['exists'] ?? false)
                )),
                'table_row_count' => array_reduce(
                    $tableAudit,
                    static fn (int $carry, array $item): int => $carry + (int)($item['row_count'] ?? 0),
                    0
                ),
                'existing_managed_channel_count' => count($managedChannelAudit),
                'deletable_managed_channel_count' => count(array_filter(
                    $managedChannelAudit,
                    static fn (array $item): bool => (bool)($item['can_cleanup'] ?? false)
                )),
                'blocked_managed_channel_count' => count(array_filter(
                    $managedChannelAudit,
                    static fn (array $item): bool => !(bool)($item['can_cleanup'] ?? false)
                )),
            ],
        ];
    }

    private function registryResidueGuardPayload(string $code): array
    {
        $snapshots = $this->snapshotsPayload($code);
        $items = array_values(is_array($snapshots['items'] ?? null) ? $snapshots['items'] : []);
        $latest = is_array($items[0] ?? null) ? $items[0] : null;

        return [
            'snapshot_total' => (int)($snapshots['total'] ?? 0),
            'has_snapshot' => !empty($items),
            'latest_snapshot_id' => is_array($latest) ? (string)($latest['snapshot_id'] ?? '') : null,
            'latest_snapshot_label' => is_array($latest) ? ($latest['label'] ?? null) : null,
            'latest_snapshot_created_at' => is_array($latest) ? ($latest['created_at'] ?? null) : null,
            'cleanup_confirmation_phrase' => $this->cleanupRegistryResidueConfirmationPhrase($code),
            'cleanup_without_snapshot_confirmation_phrase' => $this->cleanupRegistryResidueWithoutSnapshotConfirmationPhrase($code),
            'warning' => empty($items)
                ? 'No recovery snapshot exists for this orphaned plugin code. Cleaning the residue will remove the last live runtime/history/table footprint without leaving an admin restore point.'
                : 'Recovery snapshots exist and will be retained in the Recovery Vault after residue cleanup.',
        ];
    }

    private function recordRegistryResidueLedger(
        string $code,
        array $snapshotGuard,
        array $fileAudit,
        array $tableAudit,
        array $managedChannelAudit,
        array $report,
        array $operator = []
    ): void {
        $ledger = $this->loadRegistryResidueLedger();
        $timestamp = trim((string)($report['finished_at'] ?? '')) ?: $this->now();
        $hasSnapshot = (bool)($snapshotGuard['has_snapshot'] ?? false);
        $retainedSnapshotCount = (int)($report['retained_snapshot_count'] ?? 0);
        $existingFileTargetCount = count(array_filter(
            $fileAudit,
            static fn (array $item): bool => (bool)($item['exists'] ?? false)
        ));
        $existingTableCount = count(array_filter(
            $tableAudit,
            static fn (array $item): bool => (bool)($item['exists'] ?? false)
        ));
        $tableRowCount = array_reduce(
            $tableAudit,
            static fn (int $carry, array $item): int => $carry + (int)($item['row_count'] ?? 0),
            0
        );
        $existingManagedChannelCount = count($managedChannelAudit);
        $blockedManagedChannelCount = count(array_filter(
            $managedChannelAudit,
            static fn (array $item): bool => !(bool)($item['can_cleanup'] ?? false)
        ));
        $removedManagedChannelCount = (int)($report['removed_managed_channel_count'] ?? 0);

        $summary = $hasSnapshot
            ? sprintf(
                'Cleaned orphan residue for [%s], removed %d file targets, %d tables, and %d managed channels, retained %d recovery snapshot(s).',
                $code,
                (int)($report['removed_file_count'] ?? 0),
                (int)($report['removed_table_count'] ?? 0),
                $removedManagedChannelCount,
                $retainedSnapshotCount
            )
            : sprintf(
                'Cleaned orphan residue for [%s] without a recovery snapshot, removed %d file targets, %d tables, and %d managed channels.',
                $code,
                (int)($report['removed_file_count'] ?? 0),
                (int)($report['removed_table_count'] ?? 0),
                $removedManagedChannelCount
            );

        $ledger['entries'][] = [
            'id' => $this->historyEventId($code, 'registry_residue_cleanup'),
            'plugin_code' => $code,
            'action' => 'registry_residue_cleanup',
            'label' => $this->historyActionLabel('registry_residue_cleanup'),
            'status' => 'success',
            'created_at' => $timestamp,
            'operator' => $this->normalizeOperator($operator),
            'summary' => $summary,
            'details' => [
                'mode' => 'registry_residue_cleanup',
                'cleanup_guard_mode' => $hasSnapshot ? 'snapshot_backed' : 'without_snapshot',
                'snapshot_retained' => (bool)($report['snapshot_retained'] ?? false),
                'retained_snapshot_count' => $retainedSnapshotCount,
                'registry_removed' => (bool)($report['registry_removed'] ?? false),
                'removed_file_count' => (int)($report['removed_file_count'] ?? 0),
                'removed_table_count' => (int)($report['removed_table_count'] ?? 0),
                'removed_row_count' => (int)($report['removed_row_count'] ?? 0),
                'removed_managed_channel_count' => $removedManagedChannelCount,
                'existing_file_target_count' => $existingFileTargetCount,
                'existing_table_count' => $existingTableCount,
                'table_row_count' => $tableRowCount,
                'existing_managed_channel_count' => $existingManagedChannelCount,
                'blocked_managed_channel_count' => $blockedManagedChannelCount,
                'runtime_exists' => (bool)($fileAudit[0]['exists'] ?? false),
                'history_exists' => (bool)($fileAudit[1]['exists'] ?? false),
                'plugin_directory_exists' => (bool)($fileAudit[2]['exists'] ?? false),
                'latest_snapshot_id' => $snapshotGuard['latest_snapshot_id'] ?? null,
                'latest_snapshot_label' => $snapshotGuard['latest_snapshot_label'] ?? null,
                'latest_snapshot_created_at' => $snapshotGuard['latest_snapshot_created_at'] ?? null,
            ],
        ];
        $ledger['updated_at'] = $timestamp;

        $this->saveRegistryResidueLedger($ledger);
    }

    private function loadRegistryResidueLedger(): array
    {
        $path = $this->registryResidueLedgerPath();
        if (!is_file($path)) {
            return $this->normalizeRegistryResidueLedger([]);
        }

        $contents = file_get_contents($path);
        $decoded = json_decode($contents ?: '', true);

        return $this->normalizeRegistryResidueLedger(is_array($decoded) ? $decoded : []);
    }

    private function normalizeRegistryResidueLedger(array $decoded): array
    {
        $entries = [];

        foreach ($decoded['entries'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            try {
                $pluginCode = $this->normalizeCode((string)($entry['plugin_code'] ?? ''));
            } catch (\Throwable) {
                continue;
            }

            $action = trim((string)($entry['action'] ?? 'registry_residue_cleanup')) ?: 'registry_residue_cleanup';
            $createdAt = trim((string)($entry['created_at'] ?? '')) ?: $this->now();
            $summary = isset($entry['summary']) && trim((string)$entry['summary']) !== ''
                ? trim((string)$entry['summary'])
                : null;

            $entries[] = [
                'id' => trim((string)($entry['id'] ?? '')) ?: $this->historyEventId($pluginCode, $action),
                'plugin_code' => $pluginCode,
                'action' => $action,
                'label' => trim((string)($entry['label'] ?? '')) ?: $this->historyActionLabel($action),
                'status' => trim((string)($entry['status'] ?? 'success')) ?: 'success',
                'created_at' => $createdAt,
                'operator' => is_array($entry['operator'] ?? null)
                    ? $this->normalizeOperator((array)$entry['operator'])
                    : null,
                'summary' => $summary,
                'details' => is_array($entry['details'] ?? null)
                    ? $this->normalizeRegistryResidueLedgerDetails((array)$entry['details'])
                    : null,
            ];
        }

        usort($entries, static function (array $left, array $right): int {
            return strcmp((string)($right['created_at'] ?? ''), (string)($left['created_at'] ?? ''));
        });

        return [
            'ledger_path' => $this->registryResidueLedgerRelativePath(),
            'updated_at' => isset($decoded['updated_at']) ? (string)$decoded['updated_at'] : null,
            'entries' => $entries,
        ];
    }

    private function normalizeRegistryResidueLedgerDetails(array $details): array
    {
        $guardMode = trim((string)($details['cleanup_guard_mode'] ?? 'snapshot_backed'));
        if (!in_array($guardMode, ['snapshot_backed', 'without_snapshot'], true)) {
            $guardMode = 'snapshot_backed';
        }

        return [
            'mode' => trim((string)($details['mode'] ?? 'registry_residue_cleanup')) ?: 'registry_residue_cleanup',
            'cleanup_guard_mode' => $guardMode,
            'snapshot_retained' => (bool)($details['snapshot_retained'] ?? false),
            'retained_snapshot_count' => (int)($details['retained_snapshot_count'] ?? 0),
            'registry_removed' => (bool)($details['registry_removed'] ?? false),
            'removed_file_count' => (int)($details['removed_file_count'] ?? 0),
            'removed_table_count' => (int)($details['removed_table_count'] ?? 0),
            'removed_row_count' => (int)($details['removed_row_count'] ?? 0),
            'removed_managed_channel_count' => (int)($details['removed_managed_channel_count'] ?? 0),
            'existing_file_target_count' => (int)($details['existing_file_target_count'] ?? 0),
            'existing_table_count' => (int)($details['existing_table_count'] ?? 0),
            'table_row_count' => (int)($details['table_row_count'] ?? 0),
            'existing_managed_channel_count' => (int)($details['existing_managed_channel_count'] ?? 0),
            'blocked_managed_channel_count' => (int)($details['blocked_managed_channel_count'] ?? 0),
            'runtime_exists' => (bool)($details['runtime_exists'] ?? false),
            'history_exists' => (bool)($details['history_exists'] ?? false),
            'plugin_directory_exists' => (bool)($details['plugin_directory_exists'] ?? false),
            'latest_snapshot_id' => isset($details['latest_snapshot_id']) && trim((string)$details['latest_snapshot_id']) !== ''
                ? trim((string)$details['latest_snapshot_id'])
                : null,
            'latest_snapshot_label' => isset($details['latest_snapshot_label']) && trim((string)$details['latest_snapshot_label']) !== ''
                ? trim((string)$details['latest_snapshot_label'])
                : null,
            'latest_snapshot_created_at' => isset($details['latest_snapshot_created_at']) && trim((string)$details['latest_snapshot_created_at']) !== ''
                ? trim((string)$details['latest_snapshot_created_at'])
                : null,
        ];
    }

    private function saveRegistryResidueLedger(array $ledger): void
    {
        $path = $this->registryResidueLedgerPath();
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $entries = array_values(is_array($ledger['entries'] ?? null) ? $ledger['entries'] : []);
        usort($entries, static function (array $left, array $right): int {
            return strcmp((string)($right['created_at'] ?? ''), (string)($left['created_at'] ?? ''));
        });

        $payload = [
            'ledger_path' => $this->registryResidueLedgerRelativePath(),
            'updated_at' => $this->now(),
            'entries' => $entries,
        ];

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('failed to encode payment plugin registry residue ledger');
        }

        if (file_put_contents($path, $encoded . PHP_EOL) === false) {
            throw new RuntimeException('failed to write payment plugin registry residue ledger');
        }
    }

    private function recoveryVaultItem(array $payload, string $path, array $registry): array
    {
        $metadata = $this->snapshotMetadata($payload, $path);
        $code = $this->normalizeCode((string)($payload['plugin_code'] ?? ''));
        $manifest = is_array($payload['manifest'] ?? null) ? $payload['manifest'] : [];
        $catalogAvailable = $this->pluginCatalogAvailable($code);
        $currentState = $this->stateFor(
            $code,
            trim((string)($manifest['version'] ?? '0.0.0')) ?: '0.0.0',
            $registry
        );
        $restoreBlockedReason = (bool)($currentState['enabled'] ?? false)
            ? 'Disable the plugin before restoring this recovery snapshot.'
            : null;

        return array_merge($metadata, [
            'plugin_code' => $code,
            'plugin_name' => trim((string)($manifest['name'] ?? $code)) ?: $code,
            'provider' => trim((string)($manifest['provider'] ?? 'unknown')) ?: 'unknown',
            'catalog_available' => $catalogAvailable,
            'runtime_available' => (bool)($this->auditCleanupFile($this->runtimeTarget($code))['exists'] ?? false),
            'history_available' => (bool)($this->auditCleanupFile($this->historyDirectoryTarget($code))['exists'] ?? false),
            'current_state' => $this->historyStateSnapshot($currentState),
            'restorable' => $restoreBlockedReason === null,
            'restore_blocked_reason' => $restoreBlockedReason,
        ]);
    }

    private function pluginCatalogAvailable(string $code): bool
    {
        return is_file(base_path($this->pluginSourceTarget($code) . '/plugin.json'));
    }

    private function shouldRecordSnapshotHistory(string $code): bool
    {
        if ($this->pluginCatalogAvailable($code)) {
            return true;
        }

        $historyPath = $this->historyPath($code);
        if (is_file($historyPath)) {
            return true;
        }

        return is_dir(dirname($historyPath));
    }

    private function snapshotId(string $code): string
    {
        return date('YmdHis') . '_' . substr(sha1(uniqid($code . '_snapshot_', true)), 0, 12);
    }

    private function buildSnapshotPayload(
        array $manifest,
        array $snapshot,
        string $snapshotId,
        ?string $label = null,
        array $operator = []
    ): array {
        $plan = $this->buildUninstallPlan($manifest, true);
        $files = $this->buildSnapshotFileArchives($manifest['code'], $plan['file_audit']);
        $tables = $this->buildSnapshotTableArchives($manifest['code'], $plan['table_audit']);
        $managedChannels = $this->buildSnapshotManagedChannelArchives($manifest['code'], $plan['managed_channel_audit']);

        return [
            'schema_version' => 1,
            'snapshot_id' => $snapshotId,
            'plugin_code' => $manifest['code'],
            'label' => $label !== null && trim($label) !== '' ? trim($label) : null,
            'created_at' => $this->now(),
            'operator' => $this->normalizeOperator($operator),
            'manifest' => $manifest,
            'registry_record' => $snapshot['state'],
            'state_snapshot' => $this->historyStateSnapshot($snapshot['state']),
            'paths' => [
                'plugin_directory' => $manifest['directory'],
                'runtime_directory' => $this->runtimeTarget($manifest['code']),
                'history_directory' => $this->historyDirectoryTarget($manifest['code']),
                'snapshot_directory' => $this->snapshotDirectoryRelativePath($manifest['code']),
            ],
            'plan' => [
                'mode' => $plan['mode'],
                'files' => $plan['files'],
                'tables' => $plan['tables'],
                'summary' => $plan['summary'],
            ],
            'summary' => $this->buildSnapshotSummary($files, $tables, $managedChannels),
            'archives' => [
                'files' => $files,
                'tables' => $tables,
                'managed_channels' => $managedChannels,
            ],
        ];
    }

    private function buildSnapshotSummary(array $files, array $tables, array $managedChannels = []): array
    {
        $existingFileRootCount = 0;
        $archivedFileCount = 0;
        $archivedDirectoryCount = 0;
        $archivedBytes = 0;

        foreach ($files as $file) {
            if ((bool)($file['exists'] ?? false)) {
                $existingFileRootCount++;
            }

            $archivedFileCount += (int)($file['file_count'] ?? 0);
            $archivedDirectoryCount += (int)($file['directory_count'] ?? 0);
            $archivedBytes += (int)($file['size_bytes'] ?? 0);
        }

        $existingTableCount = 0;
        $rowCount = 0;

        foreach ($tables as $table) {
            if ((bool)($table['exists'] ?? false)) {
                $existingTableCount++;
            }

            $rowCount += (int)($table['row_count'] ?? 0);
        }

        $managedChannelCount = 0;
        $existingManagedChannelCount = 0;
        foreach ($managedChannels as $channel) {
            $managedChannelCount++;
            if ((bool)($channel['exists'] ?? false)) {
                $existingManagedChannelCount++;
                $rowCount += 1;
            }
        }

        return [
            'file_root_count' => count($files),
            'existing_file_root_count' => $existingFileRootCount,
            'archived_file_count' => $archivedFileCount,
            'archived_directory_count' => $archivedDirectoryCount,
            'archived_bytes' => $archivedBytes,
            'table_count' => count($tables),
            'existing_table_count' => $existingTableCount,
            'managed_channel_count' => $managedChannelCount,
            'existing_managed_channel_count' => $existingManagedChannelCount,
            'row_count' => $rowCount,
        ];
    }

    private function buildSnapshotFileArchives(string $code, array $fileAudit): array
    {
        $archives = [];

        foreach ($this->uniqueSnapshotFileAudit($fileAudit) as $audit) {
            $target = trim((string)($audit['target'] ?? ''));
            $absolutePath = trim((string)($audit['absolute_path'] ?? ''));

            if ($target === '' || $absolutePath === '') {
                continue;
            }

            if ((bool)($audit['exists'] ?? false) && !$this->isAllowedPurgeFilePath($code, $absolutePath)) {
                throw new RuntimeException("snapshot target [$target] is outside the allowed plugin scope");
            }

            $archives[] = $this->buildSnapshotFileArchive($audit);
        }

        return $archives;
    }

    private function uniqueSnapshotFileAudit(array $fileAudit): array
    {
        usort($fileAudit, static function (array $left, array $right): int {
            return strlen((string)($left['absolute_path'] ?? '')) <=> strlen((string)($right['absolute_path'] ?? ''));
        });

        $unique = [];

        foreach ($fileAudit as $candidate) {
            $candidatePath = $this->normalizeAbsolutePath((string)($candidate['absolute_path'] ?? ''));
            if ($candidatePath === '') {
                continue;
            }

            $nested = false;
            foreach ($unique as $existing) {
                $existingPath = $this->normalizeAbsolutePath((string)($existing['absolute_path'] ?? ''));
                if ($existingPath !== '' && str_starts_with($candidatePath, $existingPath . '/')) {
                    $nested = true;
                    break;
                }
            }

            if ($nested) {
                continue;
            }

            $unique[] = $candidate;
        }

        return $unique;
    }

    private function buildSnapshotFileArchive(array $audit): array
    {
        $target = trim((string)($audit['target'] ?? ''));
        $absolutePath = trim((string)($audit['absolute_path'] ?? ''));
        $exists = (bool)($audit['exists'] ?? false);
        $kind = trim((string)($audit['kind'] ?? 'missing')) ?: 'missing';
        $payload = [
            'target' => $target,
            'kind' => $kind,
            'exists' => $exists,
            'file_count' => 0,
            'directory_count' => 0,
            'size_bytes' => 0,
            'entries' => [],
        ];

        if (!$exists || !file_exists($absolutePath)) {
            $payload['kind'] = 'missing';

            return $payload;
        }

        if (is_file($absolutePath)) {
            $contents = file_get_contents($absolutePath);
            if ($contents === false) {
                throw new RuntimeException('failed to read snapshot file target: ' . $target);
            }

            $payload['kind'] = 'file';
            $payload['file_count'] = 1;
            $payload['size_bytes'] = strlen($contents);
            $payload['content_base64'] = base64_encode($contents);
            $payload['checksum'] = sha1($contents);

            return $payload;
        }

        $payload['kind'] = 'directory';
        $payload['directory_count'] = 1;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absolutePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $entries = [];
        $rootPath = $this->normalizeAbsolutePath($absolutePath);

        foreach ($iterator as $item) {
            $itemPath = $this->normalizeAbsolutePath($item->getPathname());
            $relativePath = ltrim(substr($itemPath, strlen($rootPath)), '/');
            if ($relativePath === '') {
                continue;
            }

            if ($item->isDir()) {
                $entries[] = [
                    'path' => $relativePath,
                    'type' => 'directory',
                ];
                $payload['directory_count']++;
                continue;
            }

            $contents = file_get_contents($item->getPathname());
            if ($contents === false) {
                throw new RuntimeException('failed to read snapshot file target: ' . $item->getPathname());
            }

            $entries[] = [
                'path' => $relativePath,
                'type' => 'file',
                'size_bytes' => strlen($contents),
                'checksum' => sha1($contents),
                'content_base64' => base64_encode($contents),
            ];
            $payload['file_count']++;
            $payload['size_bytes'] += strlen($contents);
        }

        usort($entries, static function (array $left, array $right): int {
            $leftPath = (string)($left['path'] ?? '');
            $rightPath = (string)($right['path'] ?? '');
            $leftType = (string)($left['type'] ?? '');
            $rightType = (string)($right['type'] ?? '');

            if ($leftType !== $rightType) {
                return $leftType === 'directory' ? -1 : 1;
            }

            return strcmp($leftPath, $rightPath);
        });

        $payload['entries'] = $entries;

        return $payload;
    }

    private function buildSnapshotTableArchives(string $code, array $tableAudit): array
    {
        $archives = [];
        $seen = [];

        foreach ($tableAudit as $audit) {
            $table = trim((string)($audit['table'] ?? ''));
            if ($table === '' || isset($seen[$table])) {
                continue;
            }

            if (!$this->isAllowedSafeTableName($code, $table)) {
                throw new RuntimeException("snapshot table [$table] is outside the allowed plugin namespace");
            }

            $seen[$table] = true;
            $archives[] = $this->buildSnapshotTableArchive($table);
        }

        return $archives;
    }

    private function buildSnapshotTableArchive(string $table): array
    {
        $audit = $this->auditCleanupTable($table);

        if (!(bool)($audit['exists'] ?? false)) {
            return [
                'table' => $table,
                'exists' => false,
                'row_count' => 0,
                'create_sql' => null,
                'rows' => [],
            ];
        }

        $rows = Db::table($table)->get();
        $normalizedRows = json_decode(json_encode($rows), true);
        $normalizedRows = is_array($normalizedRows) ? array_values($normalizedRows) : [];

        return [
            'table' => $table,
            'exists' => true,
            'row_count' => count($normalizedRows),
            'create_sql' => $this->tableCreateStatement($table),
            'rows' => $normalizedRows,
        ];
    }

    private function tableCreateStatement(string $table): string
    {
        $result = Db::select('SHOW CREATE TABLE ' . $this->quoteIdentifier($table));
        if (!isset($result[0])) {
            throw new RuntimeException('failed to load create statement for table [' . $table . ']');
        }

        $row = json_decode(json_encode($result[0]), true);
        if (!is_array($row)) {
            throw new RuntimeException('failed to normalize create statement for table [' . $table . ']');
        }

        foreach (['Create Table', 'Create View'] as $key) {
            if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
                return trim((string)$row[$key]);
            }
        }

        foreach ($row as $key => $value) {
            if (stripos((string)$key, 'create ') === 0 && trim((string)$value) !== '') {
                return trim((string)$value);
            }
        }

        throw new RuntimeException('create statement was not returned for table [' . $table . ']');
    }

    private function saveSnapshotPayload(string $code, string $snapshotId, array $payload): void
    {
        $path = $this->snapshotPath($code, $snapshotId);
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('failed to encode payment plugin snapshot');
        }

        if (file_put_contents($path, $encoded . PHP_EOL) === false) {
            throw new RuntimeException('failed to write payment plugin snapshot');
        }
    }

    private function snapshotMetadata(array $payload, string $path): array
    {
        $code = $this->normalizeCode((string)($payload['plugin_code'] ?? ''));
        $snapshotId = $this->normalizeSnapshotId(
            (string)($payload['snapshot_id'] ?? pathinfo($path, PATHINFO_FILENAME))
        );
        $summary = is_array($payload['summary'] ?? null)
            ? $payload['summary']
            : $this->buildSnapshotSummary(
                is_array($payload['archives']['files'] ?? null) ? $payload['archives']['files'] : [],
                is_array($payload['archives']['tables'] ?? null) ? $payload['archives']['tables'] : [],
                is_array($payload['archives']['managed_channels'] ?? null) ? $payload['archives']['managed_channels'] : []
            );
        $stateSnapshot = is_array($payload['state_snapshot'] ?? null)
            ? $this->historyStateSnapshot((array)$payload['state_snapshot'])
            : $this->historyStateSnapshot(
                is_array($payload['registry_record'] ?? null) ? (array)$payload['registry_record'] : []
            );

        return [
            'snapshot_id' => $snapshotId,
            'label' => isset($payload['label']) && trim((string)$payload['label']) !== ''
                ? trim((string)$payload['label'])
                : null,
            'created_at' => isset($payload['created_at']) ? (string)$payload['created_at'] : null,
            'operator' => is_array($payload['operator'] ?? null)
                ? $this->normalizeOperator((array)$payload['operator'])
                : null,
            'manifest_version' => isset($payload['manifest']['version'])
                ? (string)$payload['manifest']['version']
                : '',
            'snapshot_path' => $this->snapshotRelativePath($code, $snapshotId),
            'size_bytes' => is_file($path) ? $this->safeFileSize($path) : null,
            'state_snapshot' => $stateSnapshot,
            'summary' => [
                'file_root_count' => (int)($summary['file_root_count'] ?? 0),
                'existing_file_root_count' => (int)($summary['existing_file_root_count'] ?? 0),
                'archived_file_count' => (int)($summary['archived_file_count'] ?? 0),
                'archived_directory_count' => (int)($summary['archived_directory_count'] ?? 0),
                'archived_bytes' => (int)($summary['archived_bytes'] ?? 0),
                'table_count' => (int)($summary['table_count'] ?? 0),
                'existing_table_count' => (int)($summary['existing_table_count'] ?? 0),
                'managed_channel_count' => (int)($summary['managed_channel_count'] ?? 0),
                'existing_managed_channel_count' => (int)($summary['existing_managed_channel_count'] ?? 0),
                'row_count' => (int)($summary['row_count'] ?? 0),
            ],
        ];
    }

    private function snapshotPath(string $code, string $snapshotId): string
    {
        return runtime_path(self::SNAPSHOT_ROOT . '/' . $code . '/' . $snapshotId . '.json');
    }

    private function snapshotRelativePath(string $code, string $snapshotId): string
    {
        return 'runtime/' . self::SNAPSHOT_ROOT . '/' . $code . '/' . $snapshotId . '.json';
    }

    private function snapshotDirectoryPath(string $code): string
    {
        return runtime_path(self::SNAPSHOT_ROOT . '/' . $code);
    }

    private function snapshotDirectoryRelativePath(string $code): string
    {
        return 'runtime/' . self::SNAPSHOT_ROOT . '/' . $code;
    }

    private function normalizeSnapshotId(string $snapshotId): string
    {
        $snapshotId = trim($snapshotId);
        if ($snapshotId === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $snapshotId)) {
            throw new InvalidArgumentException('invalid payment plugin snapshot id');
        }

        return $snapshotId;
    }

    private function loadSnapshotPayload(string $code, string $snapshotId): array
    {
        $path = $this->snapshotPath($code, $snapshotId);
        if (!is_file($path)) {
            throw new InvalidArgumentException("payment plugin snapshot [$snapshotId] was not found");
        }

        $contents = file_get_contents($path);
        $decoded = json_decode($contents ?: '', true);
        if (!is_array($decoded)) {
            throw new RuntimeException('invalid payment plugin snapshot payload');
        }

        return $decoded;
    }

    private function cleanupSnapshotDirectory(string $code): void
    {
        $directory = $this->snapshotDirectoryPath($code);
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $_item) {
            return;
        }

        if (!@rmdir($directory) && file_exists($directory)) {
            throw new RuntimeException('failed to remove snapshot directory: ' . $directory);
        }
    }

    private function restoreSnapshotArchives(string $code, array $payload): void
    {
        $archives = is_array($payload['archives'] ?? null) ? $payload['archives'] : [];
        $fileArchives = is_array($archives['files'] ?? null) ? array_values($archives['files']) : [];
        $tableArchives = is_array($archives['tables'] ?? null) ? array_values($archives['tables']) : [];
        $channelArchives = is_array($archives['managed_channels'] ?? null) ? array_values($archives['managed_channels']) : [];

        foreach ($tableArchives as $tableArchive) {
            $table = trim((string)($tableArchive['table'] ?? ''));
            if ($table === '') {
                continue;
            }

            if (!$this->isAllowedSafeTableName($code, $table)) {
                throw new RuntimeException("snapshot table [$table] is outside the allowed plugin namespace");
            }

            if ($this->tableExists($table)) {
                Db::statement('DROP TABLE IF EXISTS ' . $this->quoteIdentifier($table));
            }
        }

        usort($fileArchives, static function (array $left, array $right): int {
            return strlen((string)($right['target'] ?? '')) <=> strlen((string)($left['target'] ?? ''));
        });

        foreach ($fileArchives as $fileArchive) {
            $target = trim((string)($fileArchive['target'] ?? ''));
            if ($target === '') {
                continue;
            }

            $absolutePath = $this->normalizeAbsolutePath(base_path($target));
            if (!$this->isAllowedPurgeFilePath($code, $absolutePath)) {
                throw new RuntimeException("snapshot target [$target] is outside the allowed plugin scope");
            }

            if (file_exists($absolutePath)) {
                $this->removePath($absolutePath);
            }
        }

        usort($fileArchives, static function (array $left, array $right): int {
            return strlen((string)($left['target'] ?? '')) <=> strlen((string)($right['target'] ?? ''));
        });

        foreach ($fileArchives as $fileArchive) {
            $this->restoreSnapshotFileArchive($code, $fileArchive);
        }

        foreach ($tableArchives as $tableArchive) {
            $this->restoreSnapshotTableArchive($code, $tableArchive);
        }

        foreach ($channelArchives as $channelArchive) {
            $this->restoreSnapshotManagedChannelArchive($code, $channelArchive);
        }
    }

    private function restoreSnapshotFileArchive(string $code, array $archive): void
    {
        $target = trim((string)($archive['target'] ?? ''));
        if ($target === '') {
            return;
        }

        $absolutePath = $this->normalizeAbsolutePath(base_path($target));
        if (!$this->isAllowedPurgeFilePath($code, $absolutePath)) {
            throw new RuntimeException("snapshot target [$target] is outside the allowed plugin scope");
        }

        if (!(bool)($archive['exists'] ?? false)) {
            return;
        }

        $kind = trim((string)($archive['kind'] ?? 'missing'));
        if ($kind === 'file') {
            $parentDirectory = dirname($absolutePath);
            if (!is_dir($parentDirectory)) {
                mkdir($parentDirectory, 0777, true);
            }

            $contents = $this->decodeSnapshotFileContent($archive['content_base64'] ?? null, $target);
            if (file_put_contents($absolutePath, $contents) === false) {
                throw new RuntimeException('failed to restore snapshot file target: ' . $target);
            }

            return;
        }

        if ($kind !== 'directory') {
            return;
        }

        if (!is_dir($absolutePath)) {
            mkdir($absolutePath, 0777, true);
        }

        $entries = is_array($archive['entries'] ?? null) ? $archive['entries'] : [];
        $directories = [];
        $files = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $type = trim((string)($entry['type'] ?? ''));
            if ($type === 'directory') {
                $directories[] = $entry;
                continue;
            }

            if ($type === 'file') {
                $files[] = $entry;
            }
        }

        usort($directories, static function (array $left, array $right): int {
            return strlen((string)($left['path'] ?? '')) <=> strlen((string)($right['path'] ?? ''));
        });

        foreach ($directories as $entry) {
            $directoryPath = $this->snapshotArchiveEntryPath($absolutePath, (string)($entry['path'] ?? ''));
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0777, true);
            }
        }

        foreach ($files as $entry) {
            $filePath = $this->snapshotArchiveEntryPath($absolutePath, (string)($entry['path'] ?? ''));
            $parentDirectory = dirname($filePath);
            if (!is_dir($parentDirectory)) {
                mkdir($parentDirectory, 0777, true);
            }

            $contents = $this->decodeSnapshotFileContent($entry['content_base64'] ?? null, (string)($entry['path'] ?? ''));
            if (file_put_contents($filePath, $contents) === false) {
                throw new RuntimeException('failed to restore snapshot file target: ' . $filePath);
            }
        }
    }

    private function snapshotArchiveEntryPath(string $rootPath, string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        $relativePath = ltrim($relativePath, '/');
        $entryPath = $relativePath === '' ? $rootPath : $rootPath . '/' . $relativePath;
        $normalizedRoot = $this->normalizeAbsolutePath($rootPath);
        $normalizedPath = $this->normalizeAbsolutePath($entryPath);

        if (
            $normalizedPath !== $normalizedRoot
            && !str_starts_with($normalizedPath, $normalizedRoot . '/')
        ) {
            throw new RuntimeException('snapshot archive entry path is outside the restore root');
        }

        return $normalizedPath;
    }

    private function decodeSnapshotFileContent(mixed $encoded, string $target): string
    {
        $decoded = base64_decode((string)$encoded, true);
        if ($decoded === false) {
            throw new RuntimeException('failed to decode snapshot file content for [' . $target . ']');
        }

        return $decoded;
    }

    private function restoreSnapshotTableArchive(string $code, array $archive): void
    {
        $table = trim((string)($archive['table'] ?? ''));
        if ($table === '') {
            return;
        }

        if (!$this->isAllowedSafeTableName($code, $table)) {
            throw new RuntimeException("snapshot table [$table] is outside the allowed plugin namespace");
        }

        if (!(bool)($archive['exists'] ?? false)) {
            return;
        }

        $createSql = trim((string)($archive['create_sql'] ?? ''));
        if ($createSql === '') {
            throw new RuntimeException('snapshot table definition is missing for [' . $table . ']');
        }

        Db::statement($createSql);

        $rows = is_array($archive['rows'] ?? null) ? array_values($archive['rows']) : [];
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            $normalizedRows = array_values(array_filter($chunk, static fn (mixed $row): bool => is_array($row)));
            if ($normalizedRows === []) {
                continue;
            }

            Db::table($table)->insert($normalizedRows);
        }
    }

    private function cleanupTargets(array $manifest, bool $purge): array
    {
        if (!$purge) {
            return $manifest['cleanup']['safe'];
        }

        return [
            'files' => $this->stringList(array_merge(
                $manifest['cleanup']['safe']['files'],
                [
                    $this->historyDirectoryTarget($manifest['code']),
                    $this->pluginSourceTarget($manifest['code']),
                ],
                $manifest['cleanup']['purge']['files']
            )),
            'tables' => $this->stringList(array_merge(
                $manifest['cleanup']['safe']['tables'],
                $manifest['cleanup']['purge']['tables']
            )),
            'notes' => $this->stringList(array_merge(
                [
                    'Purge executes all audited safe-cleanup targets, then removes the plugin package and lifecycle audit artifacts.',
                ],
                $manifest['cleanup']['safe']['notes'],
                $manifest['cleanup']['purge']['notes']
            )),
        ];
    }

    private function executeCleanupHook(
        PaymentPluginInterface $plugin,
        array $manifest,
        array $state,
        array $plan,
        string $mode
    ): array {
        $report = [
            'supported' => false,
            'executed' => false,
            'mode' => $mode,
            'summary' => null,
            'steps' => [],
            'metadata' => null,
        ];

        if (!$plugin instanceof PaymentPluginCleanupHookInterface) {
            return $report;
        }

        $report['supported'] = true;
        $result = $plugin->cleanup($mode, [
            'plugin_code' => $manifest['code'],
            'plugin_name' => $manifest['name'],
            'mode' => $mode,
            'state' => $this->historyStateSnapshot($state),
            'plan' => $plan,
            'paths' => [
                'runtime_directory' => $this->runtimeTarget($manifest['code']),
                'history_directory' => $this->historyDirectoryTarget($manifest['code']),
                'plugin_directory' => $this->pluginSourceTarget($manifest['code']),
            ],
        ]);

        $report['executed'] = true;

        if (is_string($result)) {
            $report['summary'] = trim($result) !== '' ? trim($result) : null;

            return $report;
        }

        if (!is_array($result)) {
            return $report;
        }

        $summary = isset($result['summary']) ? trim((string)$result['summary']) : '';
        $report['summary'] = $summary !== '' ? $summary : null;
        $report['steps'] = $this->stringList($result['steps'] ?? []);
        $report['metadata'] = is_array($result['metadata'] ?? null) ? $result['metadata'] : null;

        return $report;
    }

    private function pluginSnapshot(array $manifest, array &$registry): array
    {
        $plugin = $this->instantiate($manifest);
        $state = $this->stateFor($manifest['code'], $manifest['version'], $registry);
        $configSchema = $this->configSchemaState($manifest, $plugin);
        $configSummary = $this->configSummary($configSchema);
        $runtimeAudit = $this->auditCleanupFile($this->runtimeTarget($manifest['code']));
        $configTableAudit = $this->auditCleanupTable($this->configTableName($manifest));
        $managedChannels = $this->managedChannelAudit($manifest, false);
        $migrationAudit = $this->buildMigrationAudit($manifest, $state, [
            'runtime_exists' => (bool)($runtimeAudit['exists'] ?? false),
            'config_table_exists' => (bool)($configTableAudit['exists'] ?? false),
        ]);
        $auditResult = $this->auditAndReconcileState(
            $manifest,
            $state,
            $configSchema,
            $runtimeAudit,
            $configTableAudit,
            $migrationAudit,
            $managedChannels
        );

        if ($auditResult['registry_changed']) {
            $registry[$manifest['code']] = $auditResult['state'];
        }

        return [
            'plugin' => $plugin,
            'state' => $auditResult['state'],
            'state_audit' => $auditResult['audit'],
            'migration_audit' => $migrationAudit,
            'upgrade_preview' => $this->buildUpgradePreview(
                $manifest,
                $auditResult['state'],
                $auditResult['audit'],
                $migrationAudit
            ),
            'managed_channels' => $managedChannels,
            'config_schema' => $configSchema,
            'config_summary' => $configSummary,
            'registry_changed' => $auditResult['registry_changed'],
        ];
    }

    private function auditAndReconcileState(
        array $manifest,
        array $state,
        array $configSchema,
        array $runtimeAudit,
        array $configTableAudit,
        array $migrationAudit,
        array $managedChannels = []
    ): array {
        $originalState = $state;
        $registryInstalled = (bool)$state['installed'];
        $registryEnabled = (bool)$state['enabled'];
        $registryStatus = (string)$state['status'];
        $registryVersion = (string)($state['version'] ?? $manifest['version']);
        $manifestVersion = (string)$manifest['version'];
        $runtimeExists = (bool)($runtimeAudit['exists'] ?? false);
        $configTableExists = (bool)($configTableAudit['exists'] ?? false);
        $missingRequiredFields = $this->missingRequiredConfigFields($configSchema);
        $requiredConfigReady = empty($missingRequiredFields);
        $managedChannelIssues = [];
        $managedChannelMissingCount = 0;
        $managedChannelDriftCount = 0;
        $managedChannelExistingCount = 0;
        foreach ($managedChannels as $managedChannel) {
            if (!is_array($managedChannel)) {
                continue;
            }

            if ((bool)($managedChannel['exists'] ?? false)) {
                $managedChannelExistingCount++;
            } else {
                $managedChannelMissingCount++;
                $managedChannelIssues[] = 'Managed channel [' . (string)($managedChannel['code'] ?? '') . '] is missing from admin_channel.';
            }

            if (!empty($managedChannel['drift'])) {
                $managedChannelDriftCount++;
                $managedChannelIssues[] = 'Managed channel [' . (string)($managedChannel['code'] ?? '') . '] has manifest drift and should be resynced.';
            }
        }
        $pendingCurrentMigrationFiles = $state['installed']
            && version_compare($registryVersion, $manifestVersion, '==')
            ? (int)($migrationAudit['pending_file_count'] ?? 0)
            : 0;
        $issues = [];
        $reconciledActions = [];
        $now = $this->now();

        if ($state['enabled'] && !$state['installed']) {
            $issues[] = 'Registry marked the plugin as enabled without an installed state.';
            $state['enabled'] = false;
            $state['disabled_at'] = $state['disabled_at'] ?: $now;
            $reconciledActions[] = 'cleared_enabled_without_install';
        }

        if ($state['installed'] && !$runtimeExists && !$configTableExists) {
            $issues[] = 'Registry marked the plugin as installed, but both the runtime directory and config table are missing.';
            $state['installed'] = false;
            $state['enabled'] = false;
            $state['status'] = 'not_installed';
            $state['disabled_at'] = $state['disabled_at'] ?: $now;
            $reconciledActions[] = 'downgraded_missing_install_assets';
        } else {
            if ($state['installed'] && !$runtimeExists) {
                $issues[] = 'Plugin runtime directory is missing.';
            }

            if ($state['installed'] && !$configTableExists) {
                $issues[] = 'Plugin config table is missing.';
            }
        }

        if ($state['enabled'] && (!$runtimeExists || !$configTableExists)) {
            $issues[] = 'Enabled state was cleared because required install assets are incomplete.';
            $state['enabled'] = false;
            $state['disabled_at'] = $state['disabled_at'] ?: $now;
            $reconciledActions[] = 'disabled_incomplete_install_assets';
        }

        if ($state['enabled'] && !$requiredConfigReady) {
            $issues[] = 'Enabled state was cleared because required config is incomplete: ' . implode(', ', $missingRequiredFields);
            $state['enabled'] = false;
            $state['disabled_at'] = $state['disabled_at'] ?: $now;
            $reconciledActions[] = 'disabled_incomplete_required_config';
        }

        if (!$state['installed'] && ($runtimeExists || $configTableExists)) {
            $issues[] = 'Plugin is marked not installed, but plugin-owned runtime or config residue still exists.';
        }

        if ((bool)($state['installed'] ?? false) && $managedChannelIssues !== []) {
            foreach ($managedChannelIssues as $managedChannelIssue) {
                $issues[] = $managedChannelIssue;
            }
        }

        if ($state['installed'] && version_compare($registryVersion, $manifestVersion, '<')) {
            $issues[] = sprintf(
                'Installed plugin version [%s] is behind manifest version [%s]. Run upgrade to apply the latest plugin assets.',
                $registryVersion,
                $manifestVersion
            );
        }

        if ($state['installed'] && version_compare($registryVersion, $manifestVersion, '>')) {
            $issues[] = sprintf(
                'Registry version [%s] is newer than manifest version [%s]. Local plugin files may have been rolled back.',
                $registryVersion,
                $manifestVersion
            );
        }

        if ($state['installed'] && ($migrationAudit['drifted_file_count'] ?? 0) > 0) {
            $issues[] = sprintf(
                '%d applied migration file(s) no longer match the manifest files on disk.',
                (int)$migrationAudit['drifted_file_count']
            );
        }

        if ($pendingCurrentMigrationFiles > 0) {
            $issues[] = sprintf(
                'The current manifest version still has %d unapplied migration file(s). Run repair to reconcile plugin-owned database assets.',
                $pendingCurrentMigrationFiles
            );
        }

        $state = $this->normalizeStateStatus($state);
        if (
            ($registryInstalled !== (bool)$state['installed'])
            || ($registryEnabled !== (bool)$state['enabled'])
            || ($registryStatus !== (string)$state['status'])
        ) {
            $reconciledActions[] = 'normalized_registry_status';
        }

        $reconciledActions = array_values(array_unique($reconciledActions));
        $registryChanged = $state !== $originalState;

        if ($registryChanged) {
            $state = $this->markStateReconciled($state);
        }

        $repairReason = $this->repairReason($state, $runtimeExists, $configTableExists, $pendingCurrentMigrationFiles);
        if ($repairReason === null && (bool)($state['installed'] ?? false) && ($managedChannelMissingCount > 0 || $managedChannelDriftCount > 0)) {
            $repairReason = sprintf(
                'Managed channel sync drift detected: %d missing and %d drifted channel row(s). Run repair to resync plugin-owned channel metadata.',
                $managedChannelMissingCount,
                $managedChannelDriftCount
            );
        }
        if ($repairReason === null && !(bool)($state['installed'] ?? false) && $managedChannelExistingCount > 0) {
            $repairReason = sprintf(
                'Plugin-managed channel residue still exists for %d row(s). Run repair to restore a consistent installed state or purge cleanup to remove them.',
                $managedChannelExistingCount
            );
        }
        $upgradeReason = $this->upgradeReason(
            $state,
            $registryVersion,
            $manifestVersion,
            $runtimeExists,
            $configTableExists,
            $migrationAudit
        );

        return [
            'state' => $state,
            'registry_changed' => $registryChanged,
            'audit' => [
                'health' => $this->auditHealth($state, $issues, $reconciledActions, $runtimeExists, $configTableExists),
                'issues' => array_values(array_unique($issues)),
                'runtime_exists' => $runtimeExists,
                'runtime_kind' => (string)($runtimeAudit['kind'] ?? 'missing'),
                'config_table' => (string)($configTableAudit['table'] ?? $this->configTableName($manifest)),
                'config_table_exists' => $configTableExists,
                'config_table_rows' => $configTableAudit['row_count'] ?? null,
                'managed_channel_count' => count($managedChannels),
                'managed_channel_existing_count' => $managedChannelExistingCount,
                'managed_channel_missing_count' => $managedChannelMissingCount,
                'managed_channel_drift_count' => $managedChannelDriftCount,
                'required_config_ready' => $requiredConfigReady,
                'missing_required_fields' => $missingRequiredFields,
                'registry_installed' => $registryInstalled,
                'registry_enabled' => $registryEnabled,
                'registry_status' => $registryStatus,
                'registry_version' => $registryVersion,
                'manifest_version' => $manifestVersion,
                'version_matches' => version_compare($registryVersion, $manifestVersion, '=='),
                'effective_installed' => (bool)$state['installed'],
                'effective_enabled' => (bool)$state['enabled'],
                'effective_status' => (string)$state['status'],
                'reconciled' => $registryChanged,
                'reconciled_actions' => $reconciledActions,
                'migration_journal_exists' => (bool)($migrationAudit['journal_exists'] ?? false),
                'pending_migration_files' => (int)($migrationAudit['pending_file_count'] ?? 0),
                'pending_migration_releases' => $this->stringList($migrationAudit['pending_release_versions'] ?? []),
                'drifted_migration_files' => (int)($migrationAudit['drifted_file_count'] ?? 0),
                'repair_recommended' => $repairReason !== null,
                'repair_reason' => $repairReason,
                'upgrade_recommended' => $upgradeReason !== null,
                'upgrade_reason' => $upgradeReason,
            ],
        ];
    }

    private function configSchemaState(array $manifest, PaymentPluginInterface $plugin): array
    {
        $values = $this->configValues($manifest);
        $schema = is_array($plugin->configSchema()) ? $plugin->configSchema() : [];
        $items = [];

        foreach ($schema as $field) {
            if (!is_array($field)) {
                continue;
            }

            $configKey = trim((string)($field['field'] ?? ''));
            if ($configKey === '') {
                continue;
            }

            $type = trim((string)($field['type'] ?? 'text')) ?: 'text';
            $value = array_key_exists($configKey, $values) ? $values[$configKey] : null;
            $configured = $this->hasConfigValue($value);
            $secret = $this->isSecretConfigField($field, $type);
            $items[] = array_merge($field, [
                'field' => $configKey,
                'label' => trim((string)($field['label'] ?? $configKey)),
                'type' => $type,
                'required' => (bool)($field['required'] ?? false),
                'value' => $secret ? null : $value,
                'configured' => $configured,
                'secret' => $secret,
                'masked_value' => $secret && $configured ? $this->maskConfigValue($value) : null,
                'placeholder' => $secret && $configured ? 'Already configured. Leave blank to keep current value.' : null,
                'stored_value' => $value,
            ]);
        }

        return $items;
    }

    private function publicConfigSchema(array $schema): array
    {
        return array_map(static function (array $field): array {
            unset($field['stored_value']);

            return $field;
        }, $schema);
    }

    private function configSummary(array $schema): array
    {
        $requiredFields = 0;
        $configuredFields = 0;
        $missingRequiredFields = 0;

        foreach ($schema as $field) {
            $configured = (bool)($field['configured'] ?? false);
            if ($configured) {
                $configuredFields++;
            }

            if (!(bool)($field['required'] ?? false)) {
                continue;
            }

            $requiredFields++;
            if (!$configured) {
                $missingRequiredFields++;
            }
        }

        return [
            'total_fields' => count($schema),
            'configured_fields' => $configuredFields,
            'required_fields' => $requiredFields,
            'missing_required_fields' => $missingRequiredFields,
        ];
    }

    private function configValues(array $manifest): array
    {
        $table = $this->configTableName($manifest);
        if (!$this->tableExists($table)) {
            return [];
        }

        $rows = Db::table($table)
            ->select('config_key', 'config_value')
            ->where('plugin_code', $manifest['code'])
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $configKey = trim((string)($row->config_key ?? ''));
            if ($configKey === '') {
                continue;
            }

            $items[$configKey] = isset($row->config_value) ? (string)$row->config_value : null;
        }

        return $items;
    }

    private function normalizeConfigInput(array $schema, array $config): array
    {
        $allowedKeys = [];
        foreach ($schema as $field) {
            $allowedKeys[(string)$field['field']] = $field;
        }

        foreach ($config as $configKey => $_) {
            $configKey = trim((string)$configKey);
            if ($configKey === '') {
                continue;
            }

            if (!array_key_exists($configKey, $allowedKeys)) {
                throw new DomainException("unexpected config field [$configKey]");
            }
        }

        $values = [];
        foreach ($allowedKeys as $configKey => $field) {
            $rawValue = $config[$configKey] ?? null;
            $value = $this->normalizeConfigValue($rawValue);
            $storedValue = isset($field['stored_value']) ? $this->normalizeConfigValue($field['stored_value']) : null;

            if ((bool)($field['secret'] ?? false) && !$this->hasConfigValue($value) && $this->hasConfigValue($storedValue)) {
                $value = $storedValue;
            }

            if ((bool)($field['required'] ?? false) && !$this->hasConfigValue($value)) {
                throw new DomainException("required config field [$configKey] cannot be empty");
            }

            $values[$configKey] = $this->hasConfigValue($value) ? $value : null;
        }

        return $values;
    }

    private function normalizeConfigValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string)$value;
        }

        throw new DomainException('plugin config values must be scalar or null');
    }

    private function hasConfigValue(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    private function missingRequiredConfigFields(array $schema): array
    {
        $missing = [];
        foreach ($schema as $field) {
            if (!(bool)($field['required'] ?? false)) {
                continue;
            }

            if ((bool)($field['configured'] ?? false)) {
                continue;
            }

            $missing[] = (string)($field['label'] ?? $field['field'] ?? 'unknown');
        }

        return array_values(array_unique($missing));
    }

    private function isSecretConfigField(array $field, string $type): bool
    {
        if (array_key_exists('secret', $field)) {
            return (bool)$field['secret'];
        }

        return $type === 'password';
    }

    private function maskConfigValue(?string $value): ?string
    {
        if (!$this->hasConfigValue($value)) {
            return null;
        }

        $length = strlen($value);
        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 2)
            . str_repeat('*', min(16, max(4, $length - 4)))
            . substr($value, -2);
    }

    private function runtimeTarget(string $code): string
    {
        return 'runtime/payment-plugins/' . $code;
    }

    private function historyDirectoryTarget(string $code): string
    {
        return 'runtime/' . self::HISTORY_ROOT . '/' . $code;
    }

    private function pluginSourceTarget(string $code): string
    {
        return self::PLUGIN_ROOT . '/' . $code;
    }

    private function auditCleanupFile(string $target): array
    {
        $target = trim($target);
        $absolutePath = $target === '' ? '' : $this->normalizeAbsolutePath(base_path($target));
        $exists = $absolutePath !== '' && file_exists($absolutePath);
        $isDirectory = $exists && is_dir($absolutePath);
        $isFile = $exists && is_file($absolutePath);

        return [
            'target' => $target,
            'absolute_path' => $absolutePath,
            'exists' => $exists,
            'kind' => $isDirectory ? 'directory' : ($isFile ? 'file' : 'missing'),
            'entry_count' => $isDirectory ? $this->safeDirectoryEntryCount($absolutePath) : null,
            'size_bytes' => $isFile ? $this->safeFileSize($absolutePath) : null,
        ];
    }

    private function auditCleanupTable(string $table): array
    {
        $table = trim($table);
        if ($table === '') {
            return [
                'table' => '',
                'exists' => false,
                'row_count' => null,
            ];
        }

        try {
            return [
                'table' => $table,
                'exists' => true,
                'row_count' => (int)Db::table($table)->count(),
            ];
        } catch (\Throwable) {
            return [
                'table' => $table,
                'exists' => false,
                'row_count' => null,
            ];
        }
    }

    private function namespacedTableAudit(string $code): array
    {
        return array_map(
            fn (string $table): array => $this->auditCleanupTable($table),
            $this->discoverNamespacedTables($code)
        );
    }

    private function discoverNamespacedTables(string $code): array
    {
        $code = $this->normalizeCode($code);
        $prefix = 'pay_plugin_' . $code . '_';
        $pattern = str_replace("'", "''", $prefix) . '%';

        try {
            $rows = Db::select("SHOW TABLES LIKE '{$pattern}'");
        } catch (\Throwable) {
            return [];
        }

        $tables = [];
        foreach ($rows as $row) {
            $values = array_values((array)$row);
            $table = trim((string)($values[0] ?? ''));
            if ($table === '' || !$this->isAllowedSafeTableName($code, $table)) {
                continue;
            }

            $tables[] = $table;
        }

        sort($tables);

        return array_values(array_unique($tables));
    }

    private function cleanupSafeFile(string $code, array $audit): array
    {
        $target = trim((string)($audit['target'] ?? ''));
        $absolutePath = trim((string)($audit['absolute_path'] ?? ''));

        if ($target === '' || $absolutePath === '') {
            return [
                'type' => 'file',
                'target' => $target,
                'removed' => false,
                'kind' => 'missing',
                'reason' => 'invalid target',
                'row_count' => null,
            ];
        }

        if (!$this->isAllowedSafeFilePath($code, $absolutePath)) {
            throw new RuntimeException("safe cleanup target [$target] is outside the allowed runtime scope");
        }

        if (!file_exists($absolutePath)) {
            return [
                'type' => 'file',
                'target' => $target,
                'removed' => false,
                'kind' => 'missing',
                'reason' => 'target missing',
                'row_count' => null,
            ];
        }

        $kind = is_dir($absolutePath) ? 'directory' : 'file';
        $this->removePath($absolutePath);

        if (file_exists($absolutePath)) {
            throw new RuntimeException("failed to remove cleanup target [$target]");
        }

        return [
            'type' => 'file',
            'target' => $target,
            'removed' => true,
            'kind' => $kind,
            'reason' => null,
            'row_count' => null,
        ];
    }

    private function cleanupSafeTable(string $code, array $audit): array
    {
        $table = trim((string)($audit['table'] ?? ''));
        if ($table === '') {
            return [
                'type' => 'table',
                'target' => '',
                'removed' => false,
                'kind' => 'missing',
                'reason' => 'invalid table name',
                'row_count' => null,
            ];
        }

        if (!$this->isAllowedSafeTableName($code, $table)) {
            throw new RuntimeException("safe cleanup table [$table] is outside the allowed plugin namespace");
        }

        $currentAudit = $this->auditCleanupTable($table);
        if (!(bool)($currentAudit['exists'] ?? false)) {
            return [
                'type' => 'table',
                'target' => $table,
                'removed' => false,
                'kind' => 'missing',
                'reason' => 'table missing',
                'row_count' => null,
            ];
        }

        $rowCount = (int)($currentAudit['row_count'] ?? 0);
        Db::statement('DROP TABLE IF EXISTS ' . $this->quoteIdentifier($table));

        return [
            'type' => 'table',
            'target' => $table,
            'removed' => true,
            'kind' => 'table',
            'reason' => null,
            'row_count' => $rowCount,
        ];
    }

    private function cleanupPurgeFile(string $code, array $audit): array
    {
        $target = trim((string)($audit['target'] ?? ''));
        $absolutePath = trim((string)($audit['absolute_path'] ?? ''));

        if ($target === '' || $absolutePath === '') {
            return [
                'type' => 'file',
                'target' => $target,
                'removed' => false,
                'kind' => 'missing',
                'reason' => 'invalid target',
                'row_count' => null,
            ];
        }

        if (!$this->isAllowedPurgeFilePath($code, $absolutePath)) {
            throw new RuntimeException("purge cleanup target [$target] is outside the allowed plugin scope");
        }

        if (!file_exists($absolutePath)) {
            return [
                'type' => 'file',
                'target' => $target,
                'removed' => false,
                'kind' => 'missing',
                'reason' => 'target missing',
                'row_count' => null,
            ];
        }

        $kind = is_dir($absolutePath) ? 'directory' : 'file';
        $this->removePath($absolutePath);

        if (file_exists($absolutePath)) {
            throw new RuntimeException("failed to remove purge target [$target]");
        }

        return [
            'type' => 'file',
            'target' => $target,
            'removed' => true,
            'kind' => $kind,
            'reason' => null,
            'row_count' => null,
        ];
    }

    private function cleanupPurgeTable(string $code, array $audit): array
    {
        return $this->cleanupSafeTable($code, $audit);
    }

    private function isAllowedSafeFilePath(string $code, string $absolutePath): bool
    {
        $runtimeRoot = $this->normalizeAbsolutePath(base_path('runtime'));
        $pluginRuntimeRoot = $this->normalizeAbsolutePath(base_path("runtime/payment-plugins/$code"));
        $absolutePath = $this->normalizeAbsolutePath($absolutePath);

        if ($absolutePath === '' || $runtimeRoot === '' || $pluginRuntimeRoot === '') {
            return false;
        }

        if (!str_starts_with($absolutePath, $runtimeRoot . '/')) {
            return false;
        }

        return $absolutePath === $pluginRuntimeRoot || str_starts_with($absolutePath, $pluginRuntimeRoot . '/');
    }

    private function isAllowedPurgeFilePath(string $code, string $absolutePath): bool
    {
        $absolutePath = $this->normalizeAbsolutePath($absolutePath);
        $allowedRoots = array_filter([
            $this->normalizeAbsolutePath(base_path($this->runtimeTarget($code))),
            $this->normalizeAbsolutePath(base_path($this->historyDirectoryTarget($code))),
            $this->normalizeAbsolutePath(base_path($this->pluginSourceTarget($code))),
        ]);

        if ($absolutePath === '' || $allowedRoots === []) {
            return false;
        }

        foreach ($allowedRoots as $root) {
            if ($absolutePath === $root || str_starts_with($absolutePath, $root . '/')) {
                return true;
            }
        }

        return false;
    }

    private function isAllowedSafeTableName(string $code, string $table): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return false;
        }

        return str_starts_with($table, 'pay_plugin_' . $code . '_');
    }

    private function removePath(string $absolutePath): void
    {
        if (is_dir($absolutePath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absolutePath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    if (!@rmdir($item->getPathname()) && file_exists($item->getPathname())) {
                        throw new RuntimeException('failed to remove directory: ' . $item->getPathname());
                    }
                    continue;
                }

                if (!@unlink($item->getPathname()) && file_exists($item->getPathname())) {
                    throw new RuntimeException('failed to remove file: ' . $item->getPathname());
                }
            }

            if (!@rmdir($absolutePath) && file_exists($absolutePath)) {
                throw new RuntimeException('failed to remove directory: ' . $absolutePath);
            }

            return;
        }

        if (!@unlink($absolutePath) && file_exists($absolutePath)) {
            throw new RuntimeException('failed to remove file: ' . $absolutePath);
        }
    }

    private function orderedPurgeFileAudit(string $code, array $fileAudit): array
    {
        $pluginSourceTarget = $this->pluginSourceTarget($code);
        $historyTarget = $this->historyDirectoryTarget($code);

        usort($fileAudit, static function (array $left, array $right) use ($pluginSourceTarget, $historyTarget): int {
            $leftTarget = trim((string)($left['target'] ?? ''));
            $rightTarget = trim((string)($right['target'] ?? ''));
            $leftPriority = $leftTarget === $pluginSourceTarget ? 30 : ($leftTarget === $historyTarget ? 20 : 10);
            $rightPriority = $rightTarget === $pluginSourceTarget ? 30 : ($rightTarget === $historyTarget ? 20 : 10);

            if ($leftPriority !== $rightPriority) {
                return $leftPriority <=> $rightPriority;
            }

            return strlen($rightTarget) <=> strlen($leftTarget);
        });

        return $fileAudit;
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new RuntimeException('invalid database identifier');
        }

        return '`' . $identifier . '`';
    }

    private function normalizeAbsolutePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '') {
            return '';
        }

        $drive = '';
        if (preg_match('/^[A-Za-z]:/', $path) === 1) {
            $drive = substr($path, 0, 2);
            $path = substr($path, 2);
        }

        $leadingSlash = str_starts_with($path, '/');
        $segments = [];

        foreach (explode('/', trim($path, '/')) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        $normalized = implode('/', $segments);

        if ($drive !== '') {
            return $drive . ($leadingSlash ? '/' : '') . $normalized;
        }

        return ($leadingSlash ? '/' : '') . $normalized;
    }

    private function safeDirectoryEntryCount(string $absolutePath): int
    {
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absolutePath, \FilesystemIterator::SKIP_DOTS)
            );
            $count = 0;
            foreach ($iterator as $_) {
                $count++;
            }

            return $count;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeFileSize(string $absolutePath): ?int
    {
        try {
            $size = filesize($absolutePath);
        } catch (\Throwable) {
            return null;
        }

        return $size === false ? null : (int)$size;
    }

    private function tableExists(string $table): bool
    {
        return (bool)($this->auditCleanupTable($table)['exists'] ?? false);
    }

    private function configTableName(array $manifest): string
    {
        $expected = 'pay_plugin_' . $manifest['code'] . '_config';
        foreach ($manifest['cleanup']['safe']['tables'] as $table) {
            $table = trim((string)$table);
            if ($table === $expected) {
                return $table;
            }
        }

        foreach ($manifest['cleanup']['safe']['tables'] as $table) {
            $table = trim((string)$table);
            if ($table !== '' && str_ends_with($table, '_config')) {
                return $table;
            }
        }

        return $expected;
    }

    private function instantiate(array $manifest): PaymentPluginInterface
    {
        $entryPath = base_path($manifest['entry']);
        if (!is_file($entryPath)) {
            throw new RuntimeException('plugin entry file was not found: ' . $manifest['entry']);
        }

        require_once $entryPath;

        $className = $manifest['class'];
        if ($className === '' || !class_exists($className)) {
            throw new RuntimeException('plugin class was not found: ' . $className);
        }

        $plugin = new $className();
        if (!$plugin instanceof PaymentPluginInterface) {
            throw new RuntimeException('plugin class must implement PaymentPluginInterface');
        }

        if ($plugin->code() !== $manifest['code']) {
            throw new RuntimeException('plugin code does not match manifest code');
        }

        return $plugin;
    }

    private function normalizeManifestMigrations(
        string $manifestDirectory,
        string $code,
        string $manifestVersion,
        mixed $value
    ): array {
        $directory = $manifestDirectory . '/migrations';
        $absoluteDirectory = base_path($directory);
        $discoveredFiles = [];

        if (is_dir($absoluteDirectory)) {
            $files = glob($absoluteDirectory . DIRECTORY_SEPARATOR . '*.sql') ?: [];
            sort($files, SORT_STRING);
            $discoveredFiles = array_map('basename', $files);
        }

        $config = is_array($value) ? $value : [];
        $groupedReleases = [];

        foreach ($config['releases'] ?? [] as $release) {
            if (!is_array($release)) {
                continue;
            }

            $version = trim((string)($release['version'] ?? ''));
            if ($version === '') {
                continue;
            }

            if (!isset($groupedReleases[$version])) {
                $groupedReleases[$version] = [
                    'version' => $version,
                    'files' => [],
                    'notes' => [],
                ];
            }

            $groupedReleases[$version]['files'] = array_values(array_unique(array_merge(
                $groupedReleases[$version]['files'],
                $this->stringList($release['files'] ?? [])
            )));
            $groupedReleases[$version]['notes'] = array_values(array_unique(array_merge(
                $groupedReleases[$version]['notes'],
                $this->stringList($release['notes'] ?? [])
            )));
        }

        if (empty($groupedReleases) && !empty($discoveredFiles)) {
            $groupedReleases[$manifestVersion] = [
                'version' => $manifestVersion,
                'files' => $discoveredFiles,
                'notes' => [
                    "Fallback migration release derived from the local [$code] migrations directory.",
                ],
            ];
        }

        $releases = array_values($groupedReleases);
        usort($releases, static function (array $left, array $right): int {
            $versionComparison = version_compare((string)$left['version'], (string)$right['version']);
            if ($versionComparison !== 0) {
                return $versionComparison;
            }

            return strcmp(implode('|', $left['files']), implode('|', $right['files']));
        });

        return [
            'strategy' => trim((string)($config['strategy'] ?? 'versioned_sql')) ?: 'versioned_sql',
            'directory' => $directory,
            'releases' => $releases,
        ];
    }

    private function normalizeUpgradeMetadata(string $manifestVersion, mixed $value, array $migrations): array
    {
        $config = is_array($value) ? $value : [];
        $requiresDisable = (bool)($config['requires_disable_after_upgrade'] ?? true);

        return [
            'impact' => $this->normalizeUpgradeImpact((string)($config['impact'] ?? 'medium')),
            'downtime' => $this->normalizeUpgradeDowntime((string)($config['downtime'] ?? 'brief_validation')),
            'requires_disable_after_upgrade' => $requiresDisable,
            'notes' => $this->stringList($config['notes'] ?? [
                $requiresDisable
                    ? 'Upgrade returns the plugin to a disabled state so config and routing can be revalidated before traffic resumes.'
                    : 'Upgrade keeps the plugin available immediately after completion.',
            ]),
            'checklist' => $this->stringList($config['checklist'] ?? [
                'Review required plugin config before sending traffic to the upgraded connector.',
                'Confirm plugin-owned migrations completed and smoke-check the target payment flow.',
            ]),
            'changelog' => $this->normalizeUpgradeChangelog($config['changelog'] ?? null, $migrations, $manifestVersion),
            'rollback' => $this->normalizeRollbackPolicy($config['rollback'] ?? null),
        ];
    }

    private function normalizeUpgradeChangelog(mixed $value, array $migrations, string $manifestVersion): array
    {
        $entries = [];

        if (is_array($value)) {
            foreach ($value as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $version = trim((string)($entry['version'] ?? ''));
                if ($version === '') {
                    continue;
                }

                $summary = trim((string)($entry['summary'] ?? ''));
                $notes = $this->stringList($entry['notes'] ?? []);
                $migrationFiles = $this->stringList($entry['migration_files'] ?? []);
                $entries[$version] = [
                    'version' => $version,
                    'summary' => $summary !== ''
                        ? $summary
                        : (isset($notes[0]) ? $notes[0] : "Release [$version] updates plugin-owned assets."),
                    'breaking' => (bool)($entry['breaking'] ?? false),
                    'migration_files' => $migrationFiles,
                    'notes' => $notes,
                ];
            }
        }

        if (empty($entries)) {
            foreach (is_array($migrations['releases'] ?? null) ? $migrations['releases'] : [] as $release) {
                if (!is_array($release)) {
                    continue;
                }

                $version = trim((string)($release['version'] ?? ''));
                if ($version === '') {
                    continue;
                }

                $notes = $this->stringList($release['notes'] ?? []);
                $entries[$version] = [
                    'version' => $version,
                    'summary' => isset($notes[0])
                        ? $notes[0]
                        : "Release [$version] updates plugin-owned SQL assets.",
                    'breaking' => false,
                    'migration_files' => $this->stringList($release['files'] ?? []),
                    'notes' => $notes,
                ];
            }
        }

        if (!isset($entries[$manifestVersion])) {
            $entries[$manifestVersion] = [
                'version' => $manifestVersion,
                'summary' => "Manifest version [$manifestVersion] is available for this plugin.",
                'breaking' => false,
                'migration_files' => [],
                'notes' => [],
            ];
        }

        $normalized = array_values($entries);
        usort($normalized, static function (array $left, array $right): int {
            $versionComparison = version_compare((string)$left['version'], (string)$right['version']);
            if ($versionComparison !== 0) {
                return $versionComparison;
            }

            return strcmp((string)$left['summary'], (string)$right['summary']);
        });

        return $normalized;
    }

    private function normalizeRollbackPolicy(mixed $value): array
    {
        $config = is_array($value) ? $value : [];
        $supported = (bool)($config['supported'] ?? false);
        $automatic = (bool)($config['automatic'] ?? false);
        $mode = trim((string)($config['mode'] ?? ($supported ? 'manual_restore' : 'not_supported')));
        if ($mode === '') {
            $mode = $supported ? 'manual_restore' : 'not_supported';
        }

        $requiresBackup = array_key_exists('requires_backup', $config)
            ? (bool)$config['requires_backup']
            : ($supported || !$automatic);

        return [
            'supported' => $supported,
            'mode' => $mode,
            'automatic' => $automatic,
            'requires_backup' => $requiresBackup,
            'notes' => $this->stringList($config['notes'] ?? (
                $supported
                    ? [
                        'Phase 1 does not provide an automatic rollback executor for payment plugins.',
                        'Rollback requires restoring plugin files, plugin-owned tables, and runtime journals from backup.',
                    ]
                    : [
                        'Rollback is not supported automatically in the current migration phase.',
                        'If reversal is required, restore the plugin package and plugin-owned data from a verified backup.',
                    ]
            )),
        ];
    }

    private function normalizeUpgradeImpact(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['low', 'medium', 'high', 'critical'], true) ? $value : 'medium';
    }

    private function normalizeUpgradeDowntime(string $value): string
    {
        $value = strtolower(trim($value));
        return $value !== '' ? $value : 'brief_validation';
    }

    private function buildUpgradePreview(
        array $manifest,
        array $state,
        array $stateAudit,
        array $migrationAudit
    ): array {
        $upgrade = is_array($manifest['upgrade'] ?? null)
            ? $manifest['upgrade']
            : $this->normalizeUpgradeMetadata((string)$manifest['version'], null, $manifest['migrations'] ?? []);
        $fromVersion = (string)($stateAudit['registry_version'] ?? $state['version'] ?? $manifest['version']);
        $toVersion = (string)($stateAudit['manifest_version'] ?? $manifest['version']);
        $available = (bool)($stateAudit['upgrade_recommended'] ?? false);
        $pendingReleases = $this->stringList($migrationAudit['pending_release_versions'] ?? []);
        $pendingMigrationFiles = (int)($migrationAudit['pending_file_count'] ?? 0);
        $changelog = array_values(array_filter(
            is_array($upgrade['changelog'] ?? null) ? $upgrade['changelog'] : [],
            static fn (array $entry): bool => version_compare((string)$entry['version'], $fromVersion, '>')
                && version_compare((string)$entry['version'], $toVersion, '<=')
        ));
        $breakingChangeCount = count(array_filter(
            $changelog,
            static fn (array $entry): bool => (bool)($entry['breaking'] ?? false)
        ));

        return [
            'available' => $available,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'impact' => (string)($upgrade['impact'] ?? 'medium'),
            'downtime' => (string)($upgrade['downtime'] ?? 'brief_validation'),
            'requires_disable_after_upgrade' => (bool)($upgrade['requires_disable_after_upgrade'] ?? true),
            'notes' => $this->stringList($upgrade['notes'] ?? []),
            'checklist' => $this->stringList($upgrade['checklist'] ?? []),
            'pending_migration_files' => $pendingMigrationFiles,
            'pending_release_versions' => $pendingReleases,
            'breaking_change_count' => $breakingChangeCount,
            'summary' => $available
                ? sprintf(
                    'Upgrade from [%s] to [%s] with %d pending migration file(s) across %d release(s).',
                    $fromVersion,
                    $toVersion,
                    $pendingMigrationFiles,
                    max(1, count($pendingReleases))
                )
                : sprintf(
                    'Plugin is already on manifest version [%s]. The release policy below documents what the next upgrade window should follow.',
                    $toVersion
                ),
            'changelog' => $changelog,
            'rollback' => is_array($upgrade['rollback'] ?? null)
                ? $upgrade['rollback']
                : $this->normalizeRollbackPolicy(null),
        ];
    }

    private function resolvedMigrationReleases(array $manifest): array
    {
        $migrations = is_array($manifest['migrations'] ?? null) ? $manifest['migrations'] : [];
        $directory = trim((string)($migrations['directory'] ?? $manifest['directory'] . '/migrations'));
        $releases = is_array($migrations['releases'] ?? null) ? $migrations['releases'] : [];
        $resolved = [];

        foreach ($releases as $release) {
            if (!is_array($release)) {
                continue;
            }

            $version = trim((string)($release['version'] ?? ''));
            if ($version === '') {
                continue;
            }

            $files = [];
            foreach ($this->stringList($release['files'] ?? []) as $file) {
                $relativePath = trim($directory . '/' . ltrim($file, "/\\"));
                $absolutePath = base_path($relativePath);
                $exists = is_file($absolutePath);
                $checksum = $exists ? sha1_file($absolutePath) : false;

                $files[] = [
                    'file' => $file,
                    'relative_path' => $relativePath,
                    'absolute_path' => $absolutePath,
                    'exists' => $exists,
                    'checksum' => $checksum === false ? null : (string)$checksum,
                ];
            }

            $resolved[] = [
                'version' => $version,
                'notes' => $this->stringList($release['notes'] ?? []),
                'files' => $files,
            ];
        }

        usort($resolved, static function (array $left, array $right): int {
            $versionComparison = version_compare((string)$left['version'], (string)$right['version']);
            if ($versionComparison !== 0) {
                return $versionComparison;
            }

            return strcmp(
                implode('|', array_column($left['files'], 'file')),
                implode('|', array_column($right['files'], 'file'))
            );
        });

        return $resolved;
    }

    private function buildMigrationAudit(array $manifest, array $state, array $installEvidence): array
    {
        $releases = $this->resolvedMigrationReleases($manifest);
        $storedJournal = $this->loadMigrationJournal($manifest);
        $baselineVersion = $this->migrationBaselineVersion($manifest, $state, $installEvidence, $storedJournal);
        $effectiveJournal = $baselineVersion === null
            ? $storedJournal
            : $this->withBaselinedMigrationEntries(
                $manifest,
                $storedJournal,
                $baselineVersion,
                isset($state['installed_at']) ? (string)$state['installed_at'] : null,
                false,
                'baseline'
            );

        $entriesAudit = [];
        $releaseAudit = [];
        $appliedReleaseVersions = [];
        $pendingReleaseVersions = [];
        $appliedFileCount = 0;
        $pendingFileCount = 0;
        $driftedFileCount = 0;
        $lastAppliedAt = null;

        foreach ($releases as $release) {
            $appliedFiles = 0;
            $pendingFiles = 0;

            foreach ($release['files'] as $file) {
                $entry = is_array($effectiveJournal['entries'][$file['file']] ?? null)
                    ? $effectiveJournal['entries'][$file['file']]
                    : null;
                $applied = $entry !== null;
                $recorded = $applied ? (bool)($entry['recorded'] ?? false) : false;
                $checksumMatches = null;

                if ($applied) {
                    $appliedFiles++;
                    $appliedFileCount++;
                    if (($entry['applied_at'] ?? null) !== null) {
                        $appliedAt = (string)$entry['applied_at'];
                        if ($lastAppliedAt === null || strcmp($appliedAt, $lastAppliedAt) > 0) {
                            $lastAppliedAt = $appliedAt;
                        }
                    }

                    if (!(bool)$file['exists']) {
                        $driftedFileCount++;
                        $checksumMatches = false;
                    } elseif (($entry['checksum'] ?? null) !== null) {
                        $checksumMatches = (string)$entry['checksum'] === (string)($file['checksum'] ?? '');
                        if ($checksumMatches === false) {
                            $driftedFileCount++;
                        }
                    }
                } elseif (version_compare($release['version'], (string)$manifest['version'], '<=')) {
                    $pendingFiles++;
                    $pendingFileCount++;
                }

                $entriesAudit[] = [
                    'file' => $file['file'],
                    'release_version' => $release['version'],
                    'relative_path' => $file['relative_path'],
                    'absolute_path' => $this->normalizeAbsolutePath($file['absolute_path']),
                    'exists' => (bool)$file['exists'],
                    'checksum' => $file['checksum'],
                    'applied' => $applied,
                    'recorded' => $recorded,
                    'status' => $applied ? (string)($entry['status'] ?? 'executed') : null,
                    'source' => $applied ? (string)($entry['source'] ?? 'unknown') : null,
                    'applied_at' => $applied ? ($entry['applied_at'] ?? null) : null,
                    'checksum_matches' => $checksumMatches,
                ];
            }

            $releaseApplied = !empty($release['files']) && $appliedFiles === count($release['files']);
            $releasePending = version_compare($release['version'], (string)$manifest['version'], '<=') && $pendingFiles > 0;

            if ($releaseApplied) {
                $appliedReleaseVersions[] = $release['version'];
            }

            if ($releasePending) {
                $pendingReleaseVersions[] = $release['version'];
            }

            $releaseAudit[] = [
                'version' => $release['version'],
                'files' => array_values(array_column($release['files'], 'file')),
                'notes' => $release['notes'],
                'file_count' => count($release['files']),
                'applied_files' => $appliedFiles,
                'pending_files' => $releasePending ? $pendingFiles : 0,
                'applied' => $releaseApplied,
                'pending' => $releasePending,
            ];
        }

        return [
            'strategy' => trim((string)($manifest['migrations']['strategy'] ?? 'versioned_sql')) ?: 'versioned_sql',
            'journal_exists' => is_file($this->migrationJournalPath($manifest['code'])),
            'journal_path' => $this->migrationJournalRelativePath($manifest['code']),
            'baseline_version' => $effectiveJournal['baseline_version'] ?? null,
            'baseline_recorded' => ($storedJournal['baseline_version'] ?? null) !== null,
            'baseline_at' => $effectiveJournal['baseline_at'] ?? null,
            'manifest_version' => (string)$manifest['version'],
            'installed_version' => (string)($state['version'] ?? $manifest['version']),
            'release_count' => count($releaseAudit),
            'applied_file_count' => $appliedFileCount,
            'pending_file_count' => $pendingFileCount,
            'drifted_file_count' => $driftedFileCount,
            'applied_release_versions' => array_values(array_unique($appliedReleaseVersions)),
            'pending_release_versions' => array_values(array_unique($pendingReleaseVersions)),
            'last_applied_at' => $lastAppliedAt,
            'releases' => $releaseAudit,
            'entries' => $entriesAudit,
        ];
    }

    private function syncManifestMigrations(
        array $manifest,
        array $state,
        array $stateAudit,
        string $targetVersion,
        string $operation
    ): void {
        $journal = $this->loadMigrationJournal($manifest);
        $baselineVersion = $this->migrationBaselineVersion($manifest, $state, $stateAudit, $journal);
        $journalChanged = false;

        if ($baselineVersion !== null) {
            $journal = $this->withBaselinedMigrationEntries(
                $manifest,
                $journal,
                $baselineVersion,
                isset($state['installed_at']) ? (string)$state['installed_at'] : null,
                true,
                'baseline'
            );
            $journalChanged = true;
        }

        foreach ($this->pendingMigrationFiles($manifest, $journal, $targetVersion) as $migrationFile) {
            if (!(bool)($migrationFile['exists'] ?? false)) {
                throw new RuntimeException('declared plugin migration file was not found: ' . $migrationFile['relative_path']);
            }

            $contents = file_get_contents((string)$migrationFile['absolute_path']);
            if ($contents === false) {
                throw new RuntimeException('failed to read plugin migration file: ' . $migrationFile['relative_path']);
            }

            $sql = trim($contents);
            if ($sql !== '') {
                Db::statement($sql);
            }

            $journal['entries'][(string)$migrationFile['file']] = [
                'file' => (string)$migrationFile['file'],
                'release_version' => (string)$migrationFile['release_version'],
                'checksum' => $migrationFile['checksum'] ?? null,
                'status' => 'executed',
                'source' => $operation,
                'applied_at' => $this->now(),
                'recorded' => true,
            ];
            $journalChanged = true;
        }

        if ($journalChanged) {
            $this->saveMigrationJournal($manifest, $journal);
        }
    }

    private function pendingMigrationFiles(array $manifest, array $journal, string $targetVersion): array
    {
        $pending = [];

        foreach ($this->resolvedMigrationReleases($manifest) as $release) {
            if (version_compare($release['version'], $targetVersion, '>')) {
                continue;
            }

            foreach ($release['files'] as $file) {
                if (isset($journal['entries'][$file['file']])) {
                    continue;
                }

                $pending[] = [
                    'file' => $file['file'],
                    'release_version' => $release['version'],
                    'relative_path' => $file['relative_path'],
                    'absolute_path' => $file['absolute_path'],
                    'exists' => (bool)$file['exists'],
                    'checksum' => $file['checksum'],
                ];
            }
        }

        return $pending;
    }

    private function migrationBaselineVersion(
        array $manifest,
        array $state,
        array $installEvidence,
        array $journal
    ): ?string {
        if (!(bool)($state['installed'] ?? false)) {
            return null;
        }

        if (!(bool)($installEvidence['config_table_exists'] ?? false)) {
            return null;
        }

        $installedVersion = trim((string)($state['version'] ?? ''));
        if ($installedVersion === '') {
            return null;
        }

        $baselineVersion = version_compare($installedVersion, (string)$manifest['version'], '<=')
            ? $installedVersion
            : (string)$manifest['version'];

        foreach ($this->resolvedMigrationReleases($manifest) as $release) {
            if (version_compare($release['version'], $baselineVersion, '>')) {
                continue;
            }

            foreach ($release['files'] as $file) {
                if (!isset($journal['entries'][$file['file']])) {
                    return $baselineVersion;
                }
            }
        }

        return null;
    }

    private function withBaselinedMigrationEntries(
        array $manifest,
        array $journal,
        string $baselineVersion,
        ?string $appliedAt,
        bool $recorded,
        string $source
    ): array {
        $timestamp = $appliedAt ?: $this->now();

        foreach ($this->resolvedMigrationReleases($manifest) as $release) {
            if (version_compare($release['version'], $baselineVersion, '>')) {
                continue;
            }

            foreach ($release['files'] as $file) {
                if (isset($journal['entries'][$file['file']])) {
                    continue;
                }

                $journal['entries'][$file['file']] = [
                    'file' => $file['file'],
                    'release_version' => $release['version'],
                    'checksum' => $file['checksum'],
                    'status' => 'baselined',
                    'source' => $source,
                    'applied_at' => $timestamp,
                    'recorded' => $recorded,
                ];
            }
        }

        $journal['baseline_version'] = $baselineVersion;
        $journal['baseline_at'] = $journal['baseline_at'] ?? $timestamp;
        $journal['updated_at'] = $this->now();

        return $journal;
    }

    private function loadMigrationJournal(array $manifest): array
    {
        $path = $this->migrationJournalPath($manifest['code']);
        if (!is_file($path)) {
            return $this->normalizeMigrationJournal($manifest, []);
        }

        $contents = file_get_contents($path);
        $decoded = json_decode($contents ?: '', true);

        return $this->normalizeMigrationJournal($manifest, is_array($decoded) ? $decoded : []);
    }

    private function normalizeMigrationJournal(array $manifest, array $decoded): array
    {
        $entries = [];

        foreach ($decoded['entries'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $file = trim((string)($entry['file'] ?? ''));
            if ($file === '') {
                continue;
            }

            $entries[$file] = [
                'file' => $file,
                'release_version' => trim((string)($entry['release_version'] ?? $entry['version'] ?? $manifest['version']))
                    ?: (string)$manifest['version'],
                'checksum' => isset($entry['checksum']) && $entry['checksum'] !== null
                    ? (string)$entry['checksum']
                    : null,
                'status' => trim((string)($entry['status'] ?? 'executed')) ?: 'executed',
                'source' => trim((string)($entry['source'] ?? 'unknown')) ?: 'unknown',
                'applied_at' => isset($entry['applied_at']) ? (string)$entry['applied_at'] : null,
                'recorded' => true,
            ];
        }

        return [
            'plugin' => $manifest['code'],
            'strategy' => trim((string)($decoded['strategy'] ?? ($manifest['migrations']['strategy'] ?? 'versioned_sql')))
                ?: 'versioned_sql',
            'baseline_version' => isset($decoded['baseline_version']) && trim((string)$decoded['baseline_version']) !== ''
                ? trim((string)$decoded['baseline_version'])
                : null,
            'baseline_at' => isset($decoded['baseline_at']) ? (string)$decoded['baseline_at'] : null,
            'updated_at' => isset($decoded['updated_at']) ? (string)$decoded['updated_at'] : null,
            'entries' => $entries,
        ];
    }

    private function saveMigrationJournal(array $manifest, array $journal): void
    {
        $path = $this->migrationJournalPath($manifest['code']);
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $entries = array_values(is_array($journal['entries'] ?? null) ? $journal['entries'] : []);
        usort($entries, static function (array $left, array $right): int {
            $versionComparison = version_compare(
                (string)($left['release_version'] ?? '0.0.0'),
                (string)($right['release_version'] ?? '0.0.0')
            );
            if ($versionComparison !== 0) {
                return $versionComparison;
            }

            return strcmp((string)($left['file'] ?? ''), (string)($right['file'] ?? ''));
        });

        $payload = [
            'plugin' => $manifest['code'],
            'strategy' => trim((string)($journal['strategy'] ?? ($manifest['migrations']['strategy'] ?? 'versioned_sql')))
                ?: 'versioned_sql',
            'baseline_version' => $journal['baseline_version'] ?? null,
            'baseline_at' => $journal['baseline_at'] ?? null,
            'updated_at' => $this->now(),
            'entries' => array_map(static function (array $entry): array {
                unset($entry['recorded']);

                return $entry;
            }, $entries),
        ];

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('failed to encode payment plugin migration journal');
        }

        if (file_put_contents($path, $encoded . PHP_EOL) === false) {
            throw new RuntimeException('failed to write payment plugin migration journal');
        }
    }

    private function migrationJournalRelativePath(string $code): string
    {
        return $this->runtimeTarget($code) . '/migration_journal.json';
    }

    private function migrationJournalPath(string $code): string
    {
        return runtime_path('payment-plugins/' . $code . '/migration_journal.json');
    }

    private function historyPayload(string $code): array
    {
        $history = $this->loadHistoryLog($code);

        return [
            'plugin_code' => $code,
            'history_path' => $this->historyRelativePath($code),
            'total' => count($history['entries']),
            'items' => array_values($history['entries']),
        ];
    }

    private function recordHistoryEvent(
        string $code,
        string $action,
        array $state,
        array $operator = [],
        ?string $summary = null,
        array $details = []
    ): void {
        $history = $this->loadHistoryLog($code);
        $timestamp = $this->now();
        $history['entries'][] = [
            'id' => $this->historyEventId($code, $action),
            'action' => $action,
            'label' => $this->historyActionLabel($action),
            'status' => 'success',
            'created_at' => $timestamp,
            'operator' => $this->normalizeOperator($operator),
            'summary' => $summary !== null && trim($summary) !== '' ? trim($summary) : null,
            'details' => empty($details) ? null : $details,
            'state_snapshot' => $this->historyStateSnapshot($state),
        ];
        $history['updated_at'] = $timestamp;

        $this->saveHistoryLog($code, $history);
    }

    private function loadHistoryLog(string $code): array
    {
        $path = $this->historyPath($code);
        if (!is_file($path)) {
            return $this->normalizeHistoryLog($code, []);
        }

        $contents = file_get_contents($path);
        $decoded = json_decode($contents ?: '', true);

        return $this->normalizeHistoryLog($code, is_array($decoded) ? $decoded : []);
    }

    private function normalizeHistoryLog(string $code, array $decoded): array
    {
        $entries = [];

        foreach ($decoded['entries'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $action = trim((string)($entry['action'] ?? ''));
            if ($action === '') {
                continue;
            }

            $createdAt = isset($entry['created_at']) ? trim((string)$entry['created_at']) : '';
            if ($createdAt === '') {
                $createdAt = $this->now();
            }

            $operator = is_array($entry['operator'] ?? null)
                ? $this->normalizeOperator((array)$entry['operator'])
                : null;

            $summary = isset($entry['summary']) && trim((string)$entry['summary']) !== ''
                ? trim((string)$entry['summary'])
                : null;

            $entries[] = [
                'id' => trim((string)($entry['id'] ?? '')) ?: $this->historyEventId($code, $action),
                'action' => $action,
                'label' => trim((string)($entry['label'] ?? '')) ?: $this->historyActionLabel($action),
                'status' => trim((string)($entry['status'] ?? 'success')) ?: 'success',
                'created_at' => $createdAt,
                'operator' => $operator,
                'summary' => $summary,
                'details' => is_array($entry['details'] ?? null) ? $entry['details'] : null,
                'state_snapshot' => $this->historyStateSnapshot(
                    is_array($entry['state_snapshot'] ?? null) ? $entry['state_snapshot'] : []
                ),
            ];
        }

        usort($entries, static function (array $left, array $right): int {
            return strcmp((string)($right['created_at'] ?? ''), (string)($left['created_at'] ?? ''));
        });

        return [
            'plugin' => $code,
            'updated_at' => isset($decoded['updated_at']) ? (string)$decoded['updated_at'] : null,
            'entries' => $entries,
        ];
    }

    private function saveHistoryLog(string $code, array $history): void
    {
        $path = $this->historyPath($code);
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $entries = array_values(is_array($history['entries'] ?? null) ? $history['entries'] : []);
        usort($entries, static function (array $left, array $right): int {
            return strcmp((string)($right['created_at'] ?? ''), (string)($left['created_at'] ?? ''));
        });

        $payload = [
            'plugin' => $code,
            'history_path' => $this->historyRelativePath($code),
            'updated_at' => $this->now(),
            'entries' => $entries,
        ];

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('failed to encode payment plugin lifecycle history');
        }

        if (file_put_contents($path, $encoded . PHP_EOL) === false) {
            throw new RuntimeException('failed to write payment plugin lifecycle history');
        }
    }

    private function historyEventId(string $code, string $action): string
    {
        return date('YmdHis') . '_' . substr(str_replace('.', '', uniqid($code . '_' . $action . '_', true)), -12);
    }

    private function purgeConfirmationPhrase(string $code): string
    {
        return '确认彻底清理 ' . trim($code);
    }

    private function purgeWithoutSnapshotConfirmationPhrase(string $code): string
    {
        return '无快照彻底清理 ' . trim($code);
    }

    private function cleanupRegistryResidueConfirmationPhrase(string $code): string
    {
        return '确认清理残留 ' . trim($code);
    }

    private function cleanupRegistryResidueWithoutSnapshotConfirmationPhrase(string $code): string
    {
        return '无快照清理残留 ' . trim($code);
    }

    private function historyActionLabel(string $action): string
    {
        return match ($action) {
            'install' => '已安装',
            'repair' => '已修复',
            'upgrade' => '已升级',
            'enable' => '已启用',
            'disable' => '已停用',
            'snapshot_created' => '创建快照',
            'snapshot_deleted' => '删除快照',
            'snapshot_restored' => '恢复快照',
            'config_update' => '更新配置',
            'uninstall' => '已卸载',
            'uninstall_purge_requested' => '申请彻底清理',
            'safe_cleanup' => '安全清理',
            'purge_cleanup' => '彻底清理',
            'registry_residue_cleanup' => '注册残留清理',
            'state_reconciled' => '状态对齐',
            default => ucwords(str_replace('_', ' ', trim($action))),
        };
    }

    private function historyStateSnapshot(array $state): array
    {
        return [
            'installed' => (bool)($state['installed'] ?? false),
            'enabled' => (bool)($state['enabled'] ?? false),
            'status' => (string)($state['status'] ?? 'not_installed'),
            'version' => (string)($state['version'] ?? ''),
            'installed_at' => $state['installed_at'] ?? null,
            'enabled_at' => $state['enabled_at'] ?? null,
            'disabled_at' => $state['disabled_at'] ?? null,
            'uninstalled_at' => $state['uninstalled_at'] ?? null,
            'updated_at' => $state['updated_at'] ?? null,
            'last_action' => $state['last_action'] ?? null,
            'hook_execution' => (string)($state['hook_execution'] ?? 'deferred'),
            'cleanup_execution' => (string)($state['cleanup_execution'] ?? 'plan_only'),
        ];
    }

    private function historyRelativePath(string $code): string
    {
        return 'runtime/' . self::HISTORY_ROOT . '/' . $code . '/history.json';
    }

    private function historyPath(string $code): string
    {
        return runtime_path(self::HISTORY_ROOT . '/' . $code . '/history.json');
    }

    private function registryResidueLedgerRelativePath(): string
    {
        return 'runtime/' . self::HISTORY_ROOT . '/' . self::REGISTRY_RESIDUE_LEDGER_FILE;
    }

    private function registryResidueLedgerPath(): string
    {
        return runtime_path(self::HISTORY_ROOT . '/' . self::REGISTRY_RESIDUE_LEDGER_FILE);
    }

    private function loadRegistry(): array
    {
        $path = $this->registryPath();
        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        $decoded = json_decode($contents ?: '', true);

        return is_array($decoded) ? $decoded : [];
    }

    private function saveRegistry(array $registry): void
    {
        $path = $this->registryPath();
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $payload = json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new RuntimeException('failed to encode payment plugin registry');
        }

        file_put_contents($path, $payload . PHP_EOL);
    }

    private function stateFor(string $code, string $version, array $registry): array
    {
        $record = is_array($registry[$code] ?? null) ? $registry[$code] : [];

        return [
            'installed' => (bool)($record['installed'] ?? false),
            'enabled' => (bool)($record['enabled'] ?? false),
            'status' => (string)($record['status'] ?? 'not_installed'),
            'version' => (string)($record['version'] ?? $version),
            'installed_at' => $record['installed_at'] ?? null,
            'enabled_at' => $record['enabled_at'] ?? null,
            'disabled_at' => $record['disabled_at'] ?? null,
            'uninstalled_at' => $record['uninstalled_at'] ?? null,
            'updated_at' => $record['updated_at'] ?? null,
            'last_action' => $record['last_action'] ?? null,
            'last_operator' => is_array($record['last_operator'] ?? null) ? $record['last_operator'] : null,
            'hook_execution' => (string)($record['hook_execution'] ?? 'deferred'),
            'cleanup_execution' => (string)($record['cleanup_execution'] ?? 'plan_only'),
            'last_uninstall_plan' => is_array($record['last_uninstall_plan'] ?? null) ? $record['last_uninstall_plan'] : null,
            'last_cleanup_report' => is_array($record['last_cleanup_report'] ?? null) ? $record['last_cleanup_report'] : null,
        ];
    }

    private function normalizeStateStatus(array $state): array
    {
        $state['installed'] = (bool)($state['installed'] ?? false);
        $state['enabled'] = $state['installed'] ? (bool)($state['enabled'] ?? false) : false;
        $state['status'] = !$state['installed']
            ? 'not_installed'
            : ($state['enabled'] ? 'enabled' : 'disabled');

        return $state;
    }

    private function markStateReconciled(array $state): array
    {
        $state['last_action'] = 'state_reconciled';
        $state['updated_at'] = $this->now();

        return $state;
    }

    private function repairReason(
        array $state,
        bool $runtimeExists,
        bool $configTableExists,
        int $pendingCurrentMigrationFiles = 0
    ): ?string
    {
        if ((bool)($state['installed'] ?? false) && !$runtimeExists && !$configTableExists) {
            return 'The plugin is marked installed, but both the runtime directory and config table need to be rebuilt.';
        }

        if ((bool)($state['installed'] ?? false) && !$runtimeExists) {
            return 'The plugin runtime directory is missing and should be rebuilt.';
        }

        if ((bool)($state['installed'] ?? false) && !$configTableExists) {
            return 'The plugin config table is missing and should be rebuilt.';
        }

        if (($state['last_action'] ?? null) === 'state_reconciled' && !$runtimeExists && !$configTableExists) {
            return 'The plugin was auto-reconciled after its install assets disappeared. Run repair to rebuild them.';
        }

        if ((bool)($state['installed'] ?? false) && $pendingCurrentMigrationFiles > 0) {
            return sprintf(
                'The current manifest version still has %d unapplied migration file(s). Run repair to reconcile plugin-owned database assets.',
                $pendingCurrentMigrationFiles
            );
        }

        return null;
    }

    private function upgradeReason(
        array $state,
        string $registryVersion,
        string $manifestVersion,
        bool $runtimeExists,
        bool $configTableExists,
        array $migrationAudit
    ): ?string {
        if (!(bool)($state['installed'] ?? false)) {
            return null;
        }

        if (!$runtimeExists || !$configTableExists) {
            return null;
        }

        if (version_compare($registryVersion, $manifestVersion, '<')) {
            $pendingFileCount = (int)($migrationAudit['pending_file_count'] ?? 0);
            $pendingVersions = $this->stringList($migrationAudit['pending_release_versions'] ?? []);

            if ($pendingFileCount === 0) {
                return sprintf(
                    '当前已安装版本 [%s] 落后于清单版本 [%s]，请执行升级以同步最新插件资源。',
                    $registryVersion,
                    $manifestVersion
                );
            }

            return sprintf(
                '当前已安装版本 [%s] 落后于清单版本 [%s]，请执行升级以应用 %d 个待执行数据库脚本%s。',
                $registryVersion,
                $manifestVersion,
                $pendingFileCount,
                empty($pendingVersions) ? '' : '，涉及版本 [' . implode(', ', $pendingVersions) . ']'
            );
        }

        return null;
    }

    private function auditHealth(
        array $state,
        array $issues,
        array $reconciledActions,
        bool $runtimeExists,
        bool $configTableExists
    ): string {
        if (!empty($reconciledActions)) {
            return 'drifted';
        }

        if ($state['installed'] && (!$runtimeExists || !$configTableExists)) {
            return 'drifted';
        }

        if (!empty($issues)) {
            return 'warning';
        }

        return 'healthy';
    }

    private function touchState(array $state, string $action, array $operator = []): array
    {
        $state['last_action'] = $action;
        $state['updated_at'] = $this->now();
        $state['last_operator'] = $this->normalizeOperator($operator);

        return $state;
    }

    private function normalizeOperator(array $operator): ?array
    {
        if (empty($operator)) {
            return null;
        }

        return [
            'id' => isset($operator['id']) ? (int)$operator['id'] : null,
            'username' => isset($operator['username']) ? (string)$operator['username'] : '',
            'nickname' => isset($operator['nickname']) ? (string)$operator['nickname'] : '',
        ];
    }

    private function normalizeCode(string $code): string
    {
        $code = strtolower(trim($code));
        if ($code === '' || !preg_match('/^[a-z0-9_]+$/', $code)) {
            throw new InvalidArgumentException('invalid payment plugin code');
        }

        return $code;
    }

    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            $item = trim((string)$item);
            if ($item === '') {
                continue;
            }
            $items[] = $item;
        }

        return array_values(array_unique($items));
    }

    private function pluginRootPath(): string
    {
        return base_path(self::PLUGIN_ROOT);
    }

    private function registryPath(): string
    {
        return runtime_path(self::REGISTRY_FILE);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
