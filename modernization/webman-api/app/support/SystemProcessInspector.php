<?php

namespace app\support;

use app\process\Monitor;

class SystemProcessInspector
{
    private const PAYMENT_PLUGIN_ROOT = 'plugins/payments';

    public function snapshot(): array
    {
        $workerProcesses = $this->workerProcesses();
        $coreProcesses = $this->decorateProcessDefinitions($this->coreProcessDefinitions(), $workerProcesses);
        $pluginProcesses = $this->decorateProcessDefinitions($this->pluginProcessDefinitions(), $workerProcesses);
        $paymentPluginManifestProcesses = $this->decorateProcessDefinitions(
            $this->paymentPluginManifestProcessDefinitions(),
            $workerProcesses
        );
        $supervisorProcesses = $this->supervisorProcesses();
        $monitor = $this->monitorState($coreProcesses);
        $duplicateCleanup = $this->duplicateSupervisorCleanupPlan($supervisorProcesses, $workerProcesses);

        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'summary' => [
                'core_total' => count($coreProcesses),
                'core_running_total' => count(array_filter(
                    $coreProcesses,
                    static fn (array $item): bool => (bool)($item['running'] ?? false)
                )),
                'core_worker_total' => array_reduce(
                    $coreProcesses,
                    static fn (int $carry, array $item): int => $carry + (int)($item['process_count'] ?? 0),
                    0
                ),
                'plugin_total' => count($pluginProcesses),
                'plugin_running_total' => count(array_filter(
                    $pluginProcesses,
                    static fn (array $item): bool => (bool)($item['running'] ?? false)
                )),
                'payment_plugin_total' => $this->paymentPluginCatalogCount(),
                'payment_plugin_manifest_process_total' => count($paymentPluginManifestProcesses),
                'supervisor_total' => count($supervisorProcesses),
                'monitor_running' => (bool)($monitor['running'] ?? false),
                'monitor_paused' => (bool)($monitor['paused'] ?? false),
            ],
            'environment' => [
                'os_family' => PHP_OS_FAMILY,
                'php_binary' => PHP_BINARY,
                'project_root' => $this->normalizeFilesystemPath(base_path()),
                'runtime_root' => $this->normalizeFilesystemPath(runtime_path()),
                'server_listen' => $this->serverListen(),
                'windows_runtime_directory' => $this->normalizeFilesystemPath(runtime_path('windows')),
            ],
            'monitor' => $monitor,
            'duplicate_cleanup' => $duplicateCleanup,
            'supervisors' => [
                'count' => count($supervisorProcesses),
                'items' => $supervisorProcesses,
            ],
            'core_processes' => $coreProcesses,
            'plugin_processes' => $pluginProcesses,
            'payment_plugin_manifest_processes' => $paymentPluginManifestProcesses,
            'runtime_files' => $this->runtimeFiles(),
        ];
    }

    public function pauseMonitor(): array
    {
        Monitor::pause();

        return $this->snapshot();
    }

    public function resumeMonitor(): array
    {
        Monitor::resume();

        return $this->snapshot();
    }

    public function cleanupDuplicateSupervisors(): array
    {
        $plan = $this->duplicateSupervisorCleanupPlan();
        if (!($plan['can_cleanup'] ?? false)) {
            return $this->snapshot();
        }

        $workerPids = array_map(
            static fn (array $item): int => (int)($item['pid'] ?? 0),
            (array)($plan['remove_workers'] ?? [])
        );
        $supervisorPids = array_map(
            static fn (array $item): int => (int)($item['pid'] ?? 0),
            (array)($plan['remove_supervisors'] ?? [])
        );

        $this->terminateProcesses($workerPids);
        usleep(250000);
        $this->terminateProcesses($supervisorPids);
        usleep(250000);

        return $this->snapshot();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function coreProcessDefinitions(): array
    {
        $definitions = [];
        foreach ((array)config('process', []) as $processName => $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $definitions[] = $this->baseDefinition(
                (string)$processName,
                $definition,
                [
                    'scope' => 'core',
                    'title' => $this->coreProcessTitle((string)$processName, $definition),
                    'source' => 'config/process.php',
                    'plugin_code' => null,
                    'plugin_name' => null,
                    'runtime_start_file' => runtime_path('windows/start_' . $processName . '.php'),
                ]
            );
        }

        return $definitions;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pluginProcessDefinitions(): array
    {
        $definitions = [];
        foreach ((array)config('plugin', []) as $vendor => $projects) {
            if (!is_array($projects)) {
                continue;
            }

            foreach ($projects as $name => $project) {
                if (!is_array($project)) {
                    continue;
                }

                foreach ((array)($project['process'] ?? []) as $processName => $definition) {
                    if (!is_array($definition)) {
                        continue;
                    }

                    $runtimeStartFile = runtime_path(sprintf(
                        'windows/start_plugin.%s.%s.%s.php',
                        $vendor,
                        $name,
                        $processName
                    ));

                    $definitions[] = $this->baseDefinition(
                        (string)$processName,
                        $definition,
                        [
                            'scope' => 'plugin',
                            'title' => trim((string)($definition['name'] ?? "$vendor/$name/$processName")),
                            'source' => sprintf('config("plugin.%s.%s.process")', $vendor, $name),
                            'plugin_code' => (string)$name,
                            'plugin_name' => (string)$name,
                            'runtime_start_file' => $runtimeStartFile,
                        ]
                    );
                }
            }

            foreach ((array)($projects['process'] ?? []) as $processName => $definition) {
                if (!is_array($definition)) {
                    continue;
                }

                $runtimeStartFile = runtime_path(sprintf(
                    'windows/start_plugin.%s.%s.php',
                    $vendor,
                    $processName
                ));

                $definitions[] = $this->baseDefinition(
                    (string)$processName,
                    $definition,
                    [
                        'scope' => 'plugin',
                        'title' => trim((string)($definition['name'] ?? "$vendor/$processName")),
                        'source' => sprintf('config("plugin.%s.process")', $vendor),
                        'plugin_code' => (string)$vendor,
                        'plugin_name' => (string)$vendor,
                        'runtime_start_file' => $runtimeStartFile,
                    ]
                );
            }
        }

        return $definitions;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function paymentPluginManifestProcessDefinitions(): array
    {
        $definitions = [];
        $pluginRoot = base_path(self::PAYMENT_PLUGIN_ROOT);
        if (!is_dir($pluginRoot)) {
            return [];
        }

        $directories = glob($pluginRoot . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
        foreach ($directories as $directory) {
            $manifestPath = $directory . DIRECTORY_SEPARATOR . 'plugin.json';
            if (!is_file($manifestPath)) {
                continue;
            }

            $decoded = json_decode((string)file_get_contents($manifestPath), true);
            if (!is_array($decoded)) {
                continue;
            }

            $pluginCode = trim((string)($decoded['code'] ?? basename($directory)));
            $pluginName = trim((string)($decoded['name'] ?? $pluginCode));
            $processes = $decoded['process'] ?? [];
            if (!is_array($processes)) {
                continue;
            }

            foreach ($processes as $processKey => $processDefinition) {
                if (!is_array($processDefinition)) {
                    continue;
                }

                $normalizedKey = is_string($processKey) && trim($processKey) !== ''
                    ? trim($processKey)
                    : trim((string)($processDefinition['key'] ?? $processDefinition['name'] ?? 'process'));

                $definitions[] = $this->baseDefinition(
                    $normalizedKey,
                    $processDefinition,
                    [
                        'scope' => 'payment_plugin_manifest',
                        'title' => trim((string)($processDefinition['title'] ?? $processDefinition['name'] ?? $normalizedKey)),
                        'source' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $manifestPath),
                        'plugin_code' => $pluginCode,
                        'plugin_name' => $pluginName,
                        'runtime_start_file' => null,
                    ]
                );
            }
        }

        return $definitions;
    }

    /**
     * @param array<int, array<string, mixed>> $definitions
     * @return array<int, array<string, mixed>>
     */
    private function decorateProcessDefinitions(array $definitions, ?array $workerProcesses = null): array
    {
        if ($definitions === []) {
            return [];
        }

        $workerProcesses = $workerProcesses ?? $this->workerProcesses();
        $listenerLookup = $this->listenerLookup($definitions);
        $items = [];

        foreach ($definitions as $definition) {
            $runtimeStartFile = trim((string)($definition['runtime_start_file'] ?? ''));
            $processes = PHP_OS_FAMILY === 'Windows'
                ? ($runtimeStartFile !== '' ? $this->matchWorkerProcesses($workerProcesses, basename($runtimeStartFile)) : [])
                : $this->matchUnixWorkerProcesses($workerProcesses, (string)($definition['key'] ?? ''));
            $listen = trim((string)($definition['listen'] ?? ''));
            $listeners = $listen !== '' ? ($listenerLookup[$listen] ?? []) : [];

            $item = $definition;
            $item['running'] = $processes !== [];
            $item['process_count'] = count($processes);
            $item['workers'] = $processes;
            $item['listening'] = $listeners !== [];
            $item['listeners'] = $listeners;
            unset($item['runtime_start_file']);

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function workerProcesses(): array
    {
        $processes = PHP_OS_FAMILY === 'Windows'
            ? $this->windowsProcessRecords()
            : $this->unixProcessRecords();

        return array_values(array_filter(
            $processes,
            static function (array $item): bool {
                $commandLine = self::normalizedCommandLine((string)($item['command_line'] ?? ''));

                if (PHP_OS_FAMILY === 'Windows') {
                    return self::commandHasQueueFlag($commandLine);
                }

                return self::isUnixWorkerCommand($commandLine);
            }
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function supervisorProcesses(): array
    {
        $processes = PHP_OS_FAMILY === 'Windows'
            ? $this->windowsProcessRecords()
            : $this->unixProcessRecords();

        return array_values(array_filter($processes, static function (array $item): bool {
            $commandLine = self::normalizedCommandLine((string)($item['command_line'] ?? ''));
            if (PHP_OS_FAMILY === 'Windows') {
                return str_contains($commandLine, 'start_webman.php')
                    && str_contains($commandLine, 'start_monitor.php')
                    && !self::commandHasQueueFlag($commandLine);
            }

            return self::isUnixMasterCommand($commandLine);
        }));
    }

    private function monitorState(array $coreProcesses): array
    {
        $monitorProcess = null;
        foreach ($coreProcesses as $item) {
            if (($item['key'] ?? '') === 'monitor') {
                $monitorProcess = $item;
                break;
            }
        }

        $lockFile = runtime_path('monitor.lock');

        return [
            'running' => (bool)($monitorProcess['running'] ?? false),
            'paused' => Monitor::isPaused(),
            'lock_file' => $this->normalizeFilesystemPath($lockFile),
            'paused_at' => is_file($lockFile) ? $this->formatTimestamp((int)filemtime($lockFile)) : null,
            'process_count' => (int)($monitorProcess['process_count'] ?? 0),
            'workers' => $monitorProcess['workers'] ?? [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function runtimeFiles(): array
    {
        $server = (array)config('server', []);
        $files = [
            $this->runtimeFile('pid_file', '进程标识文件', (string)($server['pid_file'] ?? '')),
            $this->runtimeFile('status_file', '状态记录文件', (string)($server['status_file'] ?? '')),
            $this->runtimeFile('stdout_file', '控制台日志', (string)($server['stdout_file'] ?? '')),
            $this->runtimeFile('log_file', '服务日志', (string)($server['log_file'] ?? '')),
            $this->runtimeFile('monitor_lock', '巡检锁文件', runtime_path('monitor.lock')),
            $this->runtimeFile('start_webman', 'Windows 主服务启动文件', runtime_path('windows/start_webman.php')),
            $this->runtimeFile('start_monitor', 'Windows 巡检启动文件', runtime_path('windows/start_monitor.php')),
        ];

        return array_values(array_filter(
            $files,
            static fn (array $item): bool => trim((string)($item['path'] ?? '')) !== ''
        ));
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function baseDefinition(string $key, array $definition, array $extra): array
    {
        return array_merge([
            'key' => $key,
            'handler' => trim((string)($definition['handler'] ?? '')),
            'listen' => trim((string)($definition['listen'] ?? '')) ?: null,
            'configured_workers' => isset($definition['count']) ? (int)$definition['count'] : null,
            'reloadable' => array_key_exists('reloadable', $definition) ? (bool)$definition['reloadable'] : null,
        ], $extra);
    }

    private function coreProcessTitle(string $processName, array $definition): string
    {
        return match ($processName) {
            'webman' => 'HTTP 服务',
            'monitor' => '进程巡检',
            default => trim((string)($definition['name'] ?? $processName)),
        };
    }

    /**
     * @param array<int, array<string, mixed>> $definitions
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function listenerLookup(array $definitions): array
    {
        $lookup = [];
        foreach ($definitions as $definition) {
            $listen = trim((string)($definition['listen'] ?? ''));
            if ($listen === '') {
                continue;
            }

            $address = $this->listenAddress($listen);
            if ($address === null) {
                $lookup[$listen] = [];
                continue;
            }

            $listeners = PHP_OS_FAMILY === 'Windows'
                ? $this->windowsPortListeners((int)$address['port'])
                : $this->socketListeners((string)$address['host'], (int)$address['port']);

            $lookup[$listen] = $listeners;
        }

        return $lookup;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function matchWorkerProcesses(array $workers, string $runtimeStartFileName): array
    {
        $runtimeStartFileName = strtolower(trim($runtimeStartFileName));
        if ($runtimeStartFileName === '') {
            return [];
        }

        return array_values(array_filter($workers, static function (array $item) use ($runtimeStartFileName): bool {
            $commandLine = self::normalizedCommandLine((string)($item['command_line'] ?? ''));
            return str_contains($commandLine, $runtimeStartFileName);
        }));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function matchUnixWorkerProcesses(array $workers, string $processKey): array
    {
        $processKey = strtolower(trim($processKey));
        if ($processKey === '') {
            return [];
        }

        return array_values(array_filter($workers, static function (array $item) use ($processKey): bool {
            $workerName = self::unixWorkerName((string)($item['command_line'] ?? ''));

            return $workerName !== null && $workerName === $processKey;
        }));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function windowsProcessRecords(): array
    {
        $script = <<<'POWERSHELL'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$targets = Get-CimInstance Win32_Process | Where-Object {
    $_.Name -match '^php(\.exe)?$' -and (
        $_.CommandLine -match 'start_webman\.php' -or
        $_.CommandLine -match 'start_monitor\.php'
    )
} | Select-Object `
    @{Name='pid'; Expression={$_.ProcessId}}, `
    @{Name='parent_pid'; Expression={$_.ParentProcessId}}, `
    @{Name='name'; Expression={$_.Name}}, `
    @{Name='executable_path'; Expression={$_.ExecutablePath}}, `
    @{Name='command_line'; Expression={$_.CommandLine}}, `
    @{Name='started_at'; Expression={ if ($_.CreationDate) { $_.CreationDate.ToString('yyyy-MM-dd HH:mm:ss') } else { $null } }}

$targets | ConvertTo-Json -Compress -Depth 3
POWERSHELL;

        return $this->decodeJsonList($this->runPowerShellScript($script));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function unixProcessRecords(): array
    {
        $output = $this->runCommand('ps -eo pid=,ppid=,comm=,args=');
        if ($output === '') {
            return [];
        }

        $records = [];
        foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
            $line = trim((string)$line);
            if ($line === '') {
                continue;
            }

            if (!preg_match('/^(\d+)\s+(\d+)\s+(\S+)\s+(.+)$/', $line, $matches)) {
                continue;
            }

            $commandLine = trim((string)$matches[4]);
            $normalized = self::normalizedCommandLine($commandLine);
            if (
                !str_contains($normalized, 'start_webman.php')
                && !str_contains($normalized, 'start_monitor.php')
                && !self::isUnixMasterCommand($normalized)
                && !self::isUnixWorkerCommand($normalized)
            ) {
                continue;
            }

            $records[] = [
                'pid' => (int)$matches[1],
                'parent_pid' => (int)$matches[2],
                'name' => (string)$matches[3],
                'executable_path' => null,
                'command_line' => $commandLine,
                'started_at' => null,
            ];
        }

        return $records;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function windowsPortListeners(int $port): array
    {
        if ($port <= 0) {
            return [];
        }

        $script = <<<POWERSHELL
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
\$port = $port
\$targets = Get-NetTCPConnection -State Listen | Where-Object { \$_.LocalPort -eq \$port } | Select-Object `
    @{Name='local_address'; Expression={\$_.LocalAddress}}, `
    @{Name='local_port'; Expression={\$_.LocalPort}}, `
    @{Name='owning_process'; Expression={\$_.OwningProcess}}, `
    @{Name='state'; Expression={\$_.State}}

\$targets | ConvertTo-Json -Compress -Depth 3
POWERSHELL;

        return $this->decodeJsonList($this->runPowerShellScript($script));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function socketListeners(string $host, int $port): array
    {
        $probeHost = in_array($host, ['0.0.0.0', '::', '[::]'], true) ? '127.0.0.1' : $host;
        if ($probeHost === '' || $port <= 0) {
            return [];
        }

        $connection = @fsockopen($probeHost, $port, $errorCode, $errorMessage, 0.35);
        if (!is_resource($connection)) {
            return [];
        }

        fclose($connection);

        return [[
            'local_address' => $probeHost,
            'local_port' => $port,
            'owning_process' => null,
            'state' => 'LISTEN',
        ]];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function listenAddress(string $listen): ?array
    {
        $parsed = parse_url($listen);
        if ($parsed === false) {
            return null;
        }

        $host = trim((string)($parsed['host'] ?? ''));
        $port = isset($parsed['port']) ? (int)$parsed['port'] : 0;
        if ($host === '' || $port <= 0) {
            return null;
        }

        return [
            'host' => $host,
            'port' => $port,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeJsonList(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        if (array_is_list($decoded)) {
            return array_values(array_map([$this, 'normalizeRecord'], $decoded));
        }

        return [$this->normalizeRecord($decoded)];
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function normalizeRecord(array $record): array
    {
        return [
            'pid' => isset($record['pid']) ? (int)$record['pid'] : (isset($record['ProcessId']) ? (int)$record['ProcessId'] : 0),
            'parent_pid' => isset($record['parent_pid'])
                ? (int)$record['parent_pid']
                : (isset($record['ParentProcessId']) ? (int)$record['ParentProcessId'] : null),
            'name' => trim((string)($record['name'] ?? $record['Name'] ?? '')),
            'executable_path' => $this->normalizeCommandPath($record['executable_path'] ?? $record['ExecutablePath'] ?? null),
            'command_line' => $this->normalizeCommandPath($record['command_line'] ?? $record['CommandLine'] ?? null),
            'started_at' => $this->nullableString($record['started_at'] ?? $record['CreationDate'] ?? null),
            'local_address' => $this->nullableString($record['local_address'] ?? null),
            'local_port' => isset($record['local_port']) ? (int)$record['local_port'] : null,
            'owning_process' => isset($record['owning_process']) ? (int)$record['owning_process'] : null,
            'state' => $this->nullableString($record['state'] ?? null),
        ];
    }

    /**
     * @param array<int, array<string, mixed>>|null $supervisorProcesses
     * @param array<int, array<string, mixed>>|null $workerProcesses
     * @return array<string, mixed>
     */
    private function duplicateSupervisorCleanupPlan(?array $supervisorProcesses = null, ?array $workerProcesses = null): array
    {
        $supervisorProcesses = $supervisorProcesses ?? $this->supervisorProcesses();
        $workerProcesses = $workerProcesses ?? $this->workerProcesses();
        $orderedSupervisors = $supervisorProcesses;

        usort($orderedSupervisors, static function (array $left, array $right): int {
            $leftStartedAt = (string)($left['started_at'] ?? '');
            $rightStartedAt = (string)($right['started_at'] ?? '');
            if ($leftStartedAt !== $rightStartedAt) {
                return strcmp($rightStartedAt, $leftStartedAt);
            }

            return ((int)($right['pid'] ?? 0)) <=> ((int)($left['pid'] ?? 0));
        });

        $keepSupervisor = $orderedSupervisors[0] ?? null;
        $removeSupervisors = array_slice($orderedSupervisors, 1);
        $keepSupervisorPid = (int)($keepSupervisor['pid'] ?? 0);
        $removeSupervisorPids = array_values(array_filter(
            array_map(static fn (array $item): int => (int)($item['pid'] ?? 0), $removeSupervisors),
            static fn (int $pid): bool => $pid > 0
        ));

        $keepWorkers = [];
        $removeWorkers = [];
        $unlinkedWorkers = [];

        foreach ($workerProcesses as $worker) {
            $parentPid = (int)($worker['parent_pid'] ?? 0);
            if ($parentPid > 0) {
                if ($parentPid === $keepSupervisorPid) {
                    $keepWorkers[] = $worker;
                    continue;
                }

                if (in_array($parentPid, $removeSupervisorPids, true)) {
                    $removeWorkers[] = $worker;
                    continue;
                }
            }

            $unlinkedWorkers[] = $worker;
        }

        $expectedWebmanWorkerTotal = (int)($this->configuredProcessWorkerCount('webman') ?? 1);
        $expectedMonitorWorkerTotal = (int)($this->configuredProcessWorkerCount('monitor') ?? 1);
        $keepWebmanWorkerTotal = count(array_filter(
            $keepWorkers,
            fn (array $item): bool => $this->isWebmanWorkerRecord($item)
        ));
        $keepMonitorWorkerTotal = count(array_filter(
            $keepWorkers,
            fn (array $item): bool => $this->isMonitorWorkerRecord($item)
        ));

        $warnings = [];
        if (count($orderedSupervisors) <= 1) {
            $warnings[] = '当前没有检测到可清理的重复主服务进程。';
        }

        if ($unlinkedWorkers !== []) {
            $warnings[] = sprintf(
                '检测到 %d 个无法通过父子关系归属的子进程，当前不会自动清理这些进程。',
                count($unlinkedWorkers)
            );
        }

        if ($keepSupervisor !== null && $keepWorkers === []) {
            $warnings[] = '保留主服务下未发现直接关联子进程，执行清理前请再次确认当前服务访问状态。';
        }

        if ($keepSupervisor !== null && $keepWebmanWorkerTotal < $expectedWebmanWorkerTotal) {
            $warnings[] = sprintf(
                '保留组当前只关联 %d 个主服务进程，低于配置值 %d，清理后请重点确认服务并发与监听状态。',
                $keepWebmanWorkerTotal,
                $expectedWebmanWorkerTotal
            );
        }

        if ($keepSupervisor !== null && $keepMonitorWorkerTotal < $expectedMonitorWorkerTotal) {
            $warnings[] = sprintf(
                '保留组当前只关联 %d 个巡检进程，低于配置值 %d，清理后请确认进程巡检是否正常。',
                $keepMonitorWorkerTotal,
                $expectedMonitorWorkerTotal
            );
        }

        $summary = $keepSupervisor === null
            ? '当前未检测到可用于保留的主服务进程。'
            : sprintf(
                '建议保留主服务 #%d，并清理 %d 个重复主服务进程与 %d 个关联子进程。',
                $keepSupervisorPid,
                count($removeSupervisors),
                count($removeWorkers)
            );

        return [
            'can_cleanup' => $keepSupervisor !== null && $removeSupervisors !== [],
            'strategy' => 'keep_latest_supervisor_group',
            'summary' => $summary,
            'keep_supervisor_pid' => $keepSupervisorPid > 0 ? $keepSupervisorPid : null,
            'keep_supervisor' => $keepSupervisor,
            'keep_workers' => $keepWorkers,
            'remove_supervisors' => $removeSupervisors,
            'remove_workers' => $removeWorkers,
            'remove_supervisor_pids' => $removeSupervisorPids,
            'remove_worker_pids' => array_values(array_filter(
                array_map(static fn (array $item): int => (int)($item['pid'] ?? 0), $removeWorkers),
                static fn (int $pid): bool => $pid > 0
            )),
            'current_webman_worker_total' => count(array_filter(
                $workerProcesses,
                fn (array $item): bool => $this->isWebmanWorkerRecord($item)
            )),
            'current_monitor_worker_total' => count(array_filter(
                $workerProcesses,
                fn (array $item): bool => $this->isMonitorWorkerRecord($item)
            )),
            'expected_webman_worker_total' => $expectedWebmanWorkerTotal,
            'expected_monitor_worker_total' => $expectedMonitorWorkerTotal,
            'warnings' => $warnings,
        ];
    }

    private function runPowerShellScript(string $script): string
    {
        $script = <<<'POWERSHELL'
$ProgressPreference = 'SilentlyContinue'
$InformationPreference = 'SilentlyContinue'
$VerbosePreference = 'SilentlyContinue'
$WarningPreference = 'SilentlyContinue'
$ErrorActionPreference = 'SilentlyContinue'
POWERSHELL
        . "\n" . $script;

        $encoded = function_exists('mb_convert_encoding')
            ? base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'))
            : base64_encode((string)iconv('UTF-8', 'UTF-16LE', $script));

        return $this->runCommand(
            'powershell -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -EncodedCommand ' . $encoded
        );
    }

    private function runCommand(string $command): string
    {
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        if (in_array('shell_exec', $disabled, true)) {
            return '';
        }

        $output = @shell_exec($command);

        return is_string($output) ? trim($output) : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function runtimeFile(string $key, string $label, string $path): array
    {
        $path = $this->normalizeFilesystemPath($path);
        $exists = $path !== '' && file_exists($path);

        return [
            'key' => $key,
            'label' => $label,
            'path' => $path,
            'exists' => $exists,
            'size' => $exists && is_file($path) ? filesize($path) : null,
            'updated_at' => $exists ? $this->formatTimestamp((int)filemtime($path)) : null,
        ];
    }

    private function paymentPluginCatalogCount(): int
    {
        $pluginRoot = base_path(self::PAYMENT_PLUGIN_ROOT);
        if (!is_dir($pluginRoot)) {
            return 0;
        }

        return count(glob($pluginRoot . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: []);
    }

    private function configuredProcessWorkerCount(string $processName): ?int
    {
        $definition = config('process.' . $processName);
        if (!is_array($definition)) {
            return null;
        }

        if (!isset($definition['count'])) {
            return 1;
        }

        return max(1, (int)$definition['count']);
    }

    /**
     * @param array<int, int> $pids
     */
    private function terminateProcesses(array $pids): void
    {
        $pids = array_values(array_unique(array_filter(
            array_map('intval', $pids),
            static fn (int $pid): bool => $pid > 0
        )));

        if ($pids === []) {
            return;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $this->terminateWindowsProcesses($pids);
            return;
        }

        foreach ($pids as $pid) {
            if (function_exists('posix_kill')) {
                @posix_kill($pid, SIGTERM);
            }
        }
    }

    /**
     * @param array<int, int> $pids
     */
    private function terminateWindowsProcesses(array $pids): void
    {
        $script = "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8\n";
        $script .= '$ids = @(' . implode(',', $pids) . ')' . "\n";
        $script .= "foreach (\$id in \$ids) {\n";
        $script .= "    try {\n";
        $script .= "        Stop-Process -Id \$id -Force -ErrorAction Stop\n";
        $script .= "    } catch {\n";
        $script .= "    }\n";
        $script .= "}\n";

        $this->runPowerShellScript($script);
    }

    private function isWebmanWorkerRecord(array $record): bool
    {
        $commandLine = self::normalizedCommandLine((string)($record['command_line'] ?? ''));

        if (PHP_OS_FAMILY === 'Windows') {
            return self::commandHasQueueFlag($commandLine) && str_contains($commandLine, 'start_webman.php');
        }

        return self::unixWorkerName($commandLine) === 'webman';
    }

    private function isMonitorWorkerRecord(array $record): bool
    {
        $commandLine = self::normalizedCommandLine((string)($record['command_line'] ?? ''));

        if (PHP_OS_FAMILY === 'Windows') {
            return self::commandHasQueueFlag($commandLine) && str_contains($commandLine, 'start_monitor.php');
        }

        return self::unixWorkerName($commandLine) === 'monitor';
    }

    private function serverListen(): string
    {
        $listen = trim((string)config('process.webman.listen', ''));
        if ($listen !== '') {
            return $listen;
        }

        return trim((string)config('server.listen', ''));
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function normalizeFilesystemPath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || PHP_OS_FAMILY !== 'Windows') {
            return $path;
        }

        return preg_replace('/[\\\\\/]+/', '\\', $path) ?: $path;
    }

    private function normalizeCommandPath(mixed $value): ?string
    {
        $normalized = $this->nullableString($value);
        if ($normalized === null || PHP_OS_FAMILY !== 'Windows') {
            return $normalized;
        }

        return str_replace('/', '\\', $normalized);
    }

    private function formatTimestamp(int $timestamp): ?string
    {
        if ($timestamp <= 0) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private static function normalizedCommandLine(string $commandLine): string
    {
        return strtolower(str_replace('\\', '/', trim($commandLine)));
    }

    private static function commandHasQueueFlag(string $commandLine): bool
    {
        return preg_match('/(?:^|\s)-q(?:\s|$)/', $commandLine) === 1;
    }

    private static function isUnixMasterCommand(string $commandLine): bool
    {
        return str_contains($commandLine, 'workerman: master process')
            && str_contains($commandLine, 'start_file=')
            && str_contains($commandLine, '/start.php');
    }

    private static function isUnixWorkerCommand(string $commandLine): bool
    {
        return self::unixWorkerName($commandLine) !== null;
    }

    private static function unixWorkerName(string $commandLine): ?string
    {
        $commandLine = self::normalizedCommandLine($commandLine);
        if (!str_contains($commandLine, 'workerman: worker process')) {
            return null;
        }

        if (preg_match('/workerman:\s+worker process\s+([a-z0-9_.-]+)/', $commandLine, $matches) !== 1) {
            return null;
        }

        $workerName = strtolower(trim((string)($matches[1] ?? '')));

        return $workerName === '' ? null : $workerName;
    }
}
