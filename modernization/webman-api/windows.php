<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

/**
 * Windows supervisor entry for Webman.
 */
chdir(__DIR__);
require_once __DIR__ . '/vendor/autoload.php';

use support\App;

loadDotenv();
configureRuntime();
App::loadAllConfig(['route']);

$runtimeProcessPath = runtime_path('windows');
ensureRuntimeDirectories([
    $runtimeProcessPath,
    runtime_path('logs'),
    runtime_path('views'),
]);

$command = strtolower(trim((string)($_SERVER['argv'][1] ?? 'start')));
$supervisorPidFile = $runtimeProcessPath . DIRECTORY_SEPARATOR . 'supervisor.pid';

switch ($command) {
    case 'status':
        exit(showStatus($supervisorPidFile));

    case 'stop':
        exit(stopSupervisor($supervisorPidFile, true));

    case 'restart':
        stopSupervisor($supervisorPidFile, false);
        break;

    case 'start':
    case '':
        break;

    default:
        fwrite(STDOUT, "Usage: php windows.php [start|stop|restart|status]\r\n");
        exit(1);
}

$existingPid = readPid($supervisorPidFile);
$protectedPids = array_values(array_filter([getmypid(), $existingPid], static fn (int $pid): bool => $pid > 0));
cleanupStaleProjectPhpProcesses($protectedPids);
if ($existingPid > 0 && isProcessAlive($existingPid)) {
    fwrite(STDOUT, "Webman Windows supervisor already running. PID={$existingPid}\r\n");
    exit(0);
}

file_put_contents($supervisorPidFile, (string)getmypid());
register_shutdown_function(static function () use ($supervisorPidFile): void {
    $currentPid = getmypid();
    $storedPid = readPid($supervisorPidFile);
    if ($storedPid === $currentPid && is_file($supervisorPidFile)) {
        @unlink($supervisorPidFile);
    }
});

$processFiles = collectProcessFiles($runtimeProcessPath);
$monitor = buildMonitor();
$resource = popenProcesses($processFiles);

fwrite(STDOUT, "Webman Windows supervisor started. PID=" . getmypid() . "\r\n");

while (true) {
    sleep(1);

    if (readPid($supervisorPidFile) !== getmypid()) {
        terminateChildTree($resource);
        break;
    }

    $status = proc_get_status($resource);
    if (!($status['running'] ?? false)) {
        $resource = popenProcesses($processFiles);
        continue;
    }

    if ($monitor !== null && $monitor->checkAllFilesChange()) {
        terminateChildTree($resource);
        $resource = popenProcesses($processFiles);
    }
}

exit(0);

function loadDotenv(): void
{
    if (!class_exists('Dotenv\Dotenv') || !file_exists(base_path('.env'))) {
        return;
    }

    if (method_exists('Dotenv\Dotenv', 'createUnsafeImmutable')) {
        \Dotenv\Dotenv::createUnsafeImmutable(base_path())->load();
        return;
    }

    \Dotenv\Dotenv::createMutable(base_path())->load();
}

function configureRuntime(): void
{
    $debugEnv = envValue('APP_DEBUG');
    $appEnv = strtolower(trim((string)(envValue('APP_ENV') ?: '')));
    $debug = $debugEnv === false || trim((string)$debugEnv) === ''
        ? in_array($appEnv, ['dev', 'development', 'local'], true)
        : filter_var($debugEnv, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

    ini_set('display_errors', ($debug ?? false) ? 'on' : 'off');
    error_reporting(E_ALL);

    $errorReporting = config('app.error_reporting');
    if (isset($errorReporting)) {
        error_reporting($errorReporting);
    }
}

function envValue(string $key): string|false
{
    $value = getenv($key);
    if ($value !== false) {
        return (string)$value;
    }

    if (array_key_exists($key, $_ENV) && is_scalar($_ENV[$key])) {
        return (string)$_ENV[$key];
    }

    if (array_key_exists($key, $_SERVER) && is_scalar($_SERVER[$key])) {
        return (string)$_SERVER[$key];
    }

    static $envFileCache = null;
    if ($envFileCache === null) {
        $envFileCache = [];
        $envPath = base_path('.env');
        if (is_file($envPath)) {
            foreach ((array)file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim((string)$line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }

                [$name, $rawValue] = explode('=', $line, 2);
                $name = trim($name);
                if ($name === '') {
                    continue;
                }

                $parsedValue = trim($rawValue);
                $length = strlen($parsedValue);
                if ($length >= 2) {
                    $first = $parsedValue[0];
                    $last = $parsedValue[$length - 1];
                    if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                        $parsedValue = substr($parsedValue, 1, -1);
                    }
                }

                $envFileCache[$name] = $parsedValue;
            }
        }
    }

    if (array_key_exists($key, $envFileCache)) {
        return (string)$envFileCache[$key];
    }

    return false;
}

function ensureRuntimeDirectories(array $paths): void
{
    foreach ($paths as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }
}

function collectProcessFiles(string $runtimeProcessPath): array
{
    $processFiles = [];

    foreach ((array)config('process', []) as $processName => $definition) {
        if (!is_array($definition)) {
            continue;
        }

        $processFiles[] = writeProcessFile($runtimeProcessPath, (string)$processName, '');
    }

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

                $processFiles[] = writeProcessFile($runtimeProcessPath, (string)$processName, (string)$vendor . '.' . (string)$name);
            }
        }

        foreach ((array)($projects['process'] ?? []) as $processName => $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $processFiles[] = writeProcessFile($runtimeProcessPath, (string)$processName, (string)$vendor);
        }
    }

    return $processFiles;
}

function writeProcessFile(string $runtimeProcessPath, string $processName, string $scope): string
{
    $processParam = $scope !== '' ? "plugin.$scope.$processName" : $processName;
    $configParam = $scope !== ''
        ? "config('plugin.$scope.process')['$processName']"
        : "config('process')['$processName']";

    $fileContent = <<<PHP
<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Workerman\\Connection\\TcpConnection;
use Workerman\\Worker;

if (class_exists('Dotenv\\Dotenv') && file_exists(base_path() . '/.env')) {
    if (method_exists('Dotenv\\Dotenv', 'createUnsafeImmutable')) {
        \\Dotenv\\Dotenv::createUnsafeImmutable(base_path())->load();
    } else {
        \\Dotenv\\Dotenv::createMutable(base_path())->load();
    }
}

if (is_callable('opcache_reset')) {
    opcache_reset();
}

require_once base_path() . '/support/bootstrap.php';

worker_start('$processParam', $configParam);

if (DIRECTORY_SEPARATOR !== '/') {
    \$configuredLogFile = config('server')['log_file'] ?? Worker::\$logFile;
    if (is_string(\$configuredLogFile) && \$configuredLogFile !== '') {
        \$extension = pathinfo(\$configuredLogFile, PATHINFO_EXTENSION);
        \$basePath = \$extension !== ''
            ? substr(\$configuredLogFile, 0, -strlen(\$extension) - 1)
            : \$configuredLogFile;
        Worker::\$logFile = \$basePath . '-' . getmypid() . (\$extension !== '' ? '.' . \$extension : '');
    }
    TcpConnection::\$defaultMaxPackageSize = config('server')['max_package_size'] ?? 10 * 1024 * 1024;
}

Worker::runAll();
PHP;

    $processFile = $runtimeProcessPath . DIRECTORY_SEPARATOR . "start_$processParam.php";
    file_put_contents($processFile, $fileContent);

    return $processFile;
}

function buildMonitor(): ?object
{
    $monitorConfig = config('process.monitor.constructor');
    if (!$monitorConfig) {
        return null;
    }

    $monitorHandler = config('process.monitor.handler');
    if (!is_string($monitorHandler) || !class_exists($monitorHandler)) {
        return null;
    }

    return new $monitorHandler(...array_values((array)$monitorConfig));
}

function popenProcesses(array $processFiles)
{
    $cmd = '"' . PHP_BINARY . '" ' . implode(' ', array_map(
        static fn (string $file): string => '"' . $file . '"',
        $processFiles
    ));
    $descriptorSpec = [STDIN, STDOUT, STDOUT];
    $resource = proc_open($cmd, $descriptorSpec, $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($resource)) {
        exit("Can not execute $cmd\r\n");
    }

    return $resource;
}

function terminateChildTree($resource): void
{
    if (!is_resource($resource)) {
        return;
    }

    $status = proc_get_status($resource);
    $childPid = (int)($status['pid'] ?? 0);
    if ($childPid > 0) {
        shell_exec('taskkill /F /T /PID ' . $childPid . ' >NUL 2>NUL');
    }
    proc_close($resource);
}

function showStatus(string $supervisorPidFile): int
{
    $pid = readPid($supervisorPidFile);
    if ($pid > 0 && isProcessAlive($pid)) {
        fwrite(STDOUT, "Webman Windows supervisor is running. PID={$pid}\r\n");
        return 0;
    }

    fwrite(STDOUT, "Webman Windows supervisor is not running.\r\n");
    return 1;
}

function stopSupervisor(string $supervisorPidFile, bool $verbose): int
{
    $pid = readPid($supervisorPidFile);
    if ($pid <= 0) {
        cleanupResidualRuntime();
        cleanupStaleProjectPhpProcesses();
        if ($verbose) {
            fwrite(STDOUT, "Webman Windows supervisor is not running.\r\n");
        }
        return 0;
    }

    if (!isProcessAlive($pid)) {
        @unlink($supervisorPidFile);
        cleanupResidualRuntime();
        if ($verbose) {
            fwrite(STDOUT, "Webman Windows supervisor was already stopped.\r\n");
        }
        return 0;
    }

    shell_exec('taskkill /F /T /PID ' . $pid . ' >NUL 2>NUL');

    $deadline = microtime(true) + 8;
    while (microtime(true) < $deadline) {
        if (!isProcessAlive($pid)) {
            break;
        }
        usleep(200000);
    }

    @unlink($supervisorPidFile);
    cleanupResidualRuntime();
    cleanupStaleProjectPhpProcesses();

    if ($verbose) {
        fwrite(STDOUT, "Webman Windows supervisor stopped.\r\n");
    }

    return 0;
}

function cleanupResidualRuntime(): void
{
    foreach ([runtime_path('webman.pid'), runtime_path('webman.status')] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

function readPid(string $pidFile): int
{
    if (!is_file($pidFile)) {
        return 0;
    }

    return max(0, (int)trim((string)file_get_contents($pidFile)));
}

function isProcessAlive(int $pid): bool
{
    if ($pid <= 0) {
        return false;
    }

    $output = shell_exec('tasklist /FI "PID eq ' . $pid . '" /FO CSV /NH 2>NUL');
    if (!is_string($output) || trim($output) === '' || str_contains($output, 'No tasks are running')) {
        return false;
    }

    return str_contains($output, '"' . $pid . '"');
}

function cleanupStaleProjectPhpProcesses(array $protectedPids = []): void
{
    $processes = listProjectPhpProcesses();
    if ($processes === []) {
        return;
    }

    $protectedLookup = array_fill_keys(
        collectDescendantProcessIds($processes, array_values(array_unique(array_filter($protectedPids, static fn (int $pid): bool => $pid > 0)))),
        true
    );

    $staleProcesses = array_values(array_filter($processes, static function (array $process) use ($protectedLookup): bool {
        $pid = (int)($process['ProcessId'] ?? 0);

        return $pid > 0 && !isset($protectedLookup[$pid]);
    }));

    if ($staleProcesses === []) {
        return;
    }

    $staleLookup = [];
    foreach ($staleProcesses as $process) {
        $pid = (int)($process['ProcessId'] ?? 0);
        if ($pid > 0) {
            $staleLookup[$pid] = true;
        }
    }

    foreach ($staleProcesses as $process) {
        $pid = (int)($process['ProcessId'] ?? 0);
        $parentPid = (int)($process['ParentProcessId'] ?? 0);
        if ($pid <= 0 || isset($staleLookup[$parentPid])) {
            continue;
        }

        shell_exec('taskkill /F /T /PID ' . $pid . ' >NUL 2>NUL');
    }
}

function collectDescendantProcessIds(array $processes, array $rootPids): array
{
    if ($rootPids === []) {
        return [];
    }

    $childrenByParent = [];
    foreach ($processes as $process) {
        $pid = (int)($process['ProcessId'] ?? 0);
        $parentPid = (int)($process['ParentProcessId'] ?? 0);
        if ($pid <= 0) {
            continue;
        }

        $childrenByParent[$parentPid][] = $pid;
    }

    $collected = [];
    $queue = array_values(array_unique($rootPids));
    while ($queue !== []) {
        $pid = (int)array_shift($queue);
        if ($pid <= 0 || isset($collected[$pid])) {
            continue;
        }

        $collected[$pid] = true;
        foreach ($childrenByParent[$pid] ?? [] as $childPid) {
            if (!isset($collected[$childPid])) {
                $queue[] = $childPid;
            }
        }
    }

    return array_map('intval', array_keys($collected));
}

function listProjectPhpProcesses(): array
{
    $basePath = str_replace("'", "''", str_replace('/', '\\', base_path()));
    $script = <<<POWERSHELL
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
\$basePath = '$basePath'
Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
    Where-Object {
        \$commandLine = [string]\$_.CommandLine
        \$commandLine -ne '' -and
        \$commandLine -like "*\$basePath*" -and
        (
            \$commandLine -like '*\\windows.php start*' -or
            \$commandLine -like '*\\runtime\\windows\\start_*'
        )
    } |
    Select-Object ProcessId, ParentProcessId, CommandLine |
    ConvertTo-Json -Compress
POWERSHELL;

    $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
    $output = shell_exec('powershell -NoProfile -ExecutionPolicy Bypass -EncodedCommand ' . $encoded . ' 2>NUL');
    if (!is_string($output) || trim($output) === '') {
        return [];
    }

    $decoded = json_decode(trim($output), true);
    if (!is_array($decoded)) {
        return [];
    }

    if (array_key_exists('ProcessId', $decoded)) {
        return [$decoded];
    }

    return array_values(array_filter($decoded, static fn (mixed $item): bool => is_array($item)));
}
