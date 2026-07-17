<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$repoRoot = dirname($projectRoot, 2);
$releaseRoot = dirname($projectRoot);
$options = parseOptions(array_slice($argv, 1));
$env = loadRuntimeEnv($projectRoot);

validateEnvironment($env);

$pdo = createPdo($env);
$trackerTable = 'aipay_deploy_migrations';

ensureTrackerTable($pdo, $trackerTable, $options['dry-run']);

$tableCount = countUserTables($pdo, $trackerTable);
$hasCoreTable = tableExists($pdo, 'admin_admin');
$needsBaseSchema = false;

if ($options['with-base-schema']) {
    if ($tableCount > 0 && !$hasCoreTable) {
        fail('Database is not empty but the core schema is missing. Refuse to import the base schema automatically.');
    }

    if ($hasCoreTable) {
        out('INFO', 'Core schema already exists, base schema import will be skipped.');
    } else {
        $needsBaseSchema = true;
    }
} elseif (!$hasCoreTable) {
    if ($tableCount === 0) {
        $needsBaseSchema = true;
    } else {
        fail('Core schema is missing but the database is not empty. Re-run with --with-base-schema on a clean database.');
    }
}

$coreInstallPath = firstExistingPath([
    $releaseRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'core-install.sql',
    $repoRoot . DIRECTORY_SEPARATOR . 'modernization' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'core-install.sql',
]);
$baseSchemaPath = firstExistingPath([
    $releaseRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'base-schema.sql',
    $repoRoot . DIRECTORY_SEPARATOR . 'modernization' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'base-schema.sql',
]);
$adminAuthSeedPath = firstExistingPath([
    $releaseRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'admin-auth-seed.sql',
    $repoRoot . DIRECTORY_SEPARATOR . 'modernization' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'admin-auth-seed.sql',
]);
if ($needsBaseSchema) {
    if ($coreInstallPath !== null) {
        applySqlAsset(
            $pdo,
            $trackerTable,
            'base',
            'core-install.sql',
            $coreInstallPath,
            $options['dry-run']
        );
    } elseif ($baseSchemaPath !== null) {
        applySqlAsset(
            $pdo,
            $trackerTable,
            'base',
            'base-schema.sql',
            $baseSchemaPath,
            $options['dry-run']
        );
    } else {
        fail('Core install SQL was not found. Checked source tree and release-package locations.');
    }
}

applyAdminAuthorizationSeed($pdo, $adminAuthSeedPath, $options['dry-run']);

$coreMigrationDirectory = $projectRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
$pluginRoot = $projectRoot . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'payments';

$coreMigrations = discoverSqlFiles($coreMigrationDirectory, 'core');
$pluginMigrations = discoverPluginMigrations($pluginRoot);

applyMigrationList($pdo, $trackerTable, $coreMigrations, $options['dry-run']);
applyMigrationList($pdo, $trackerTable, $pluginMigrations, $options['dry-run']);

if ($options['dry-run']) {
    out('DONE', 'Dry run completed. No database changes were written.');
    exit(0);
}

out('DONE', 'Database installation and migration completed successfully.');
exit(0);

function parseOptions(array $arguments): array
{
    $options = [
        'with-base-schema' => false,
        'dry-run' => false,
        'help' => false,
    ];

    foreach ($arguments as $argument) {
        if ($argument === '--with-base-schema') {
            $options['with-base-schema'] = true;
            continue;
        }

        if ($argument === '--dry-run') {
            $options['dry-run'] = true;
            continue;
        }

        if ($argument === '--help') {
            $options['help'] = true;
            continue;
        }

        failWithUsage("Unknown argument: {$argument}");
    }

    if ($options['help']) {
        $usage = <<<TEXT
Usage:
  php deploy/shared/install-database.php [--with-base-schema] [--dry-run]

Options:
  --with-base-schema  Import the clean-install core schema when the target database is empty.
  --dry-run           Show what would be executed without writing to the database.
  --help              Show this help message.
TEXT;

        fwrite(STDOUT, $usage . PHP_EOL);
        exit(0);
    }

    return $options;
}

function failWithUsage(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    fwrite(STDERR, 'Run with --help to see supported options.' . PHP_EOL);
    exit(1);
}

function loadRuntimeEnv(string $projectRoot): array
{
    $env = loadEnvFile($projectRoot . DIRECTORY_SEPARATOR . '.env');
    foreach (array_keys($env) as $key) {
        $runtimeValue = getenv($key);
        if ($runtimeValue !== false) {
            $env[$key] = (string)$runtimeValue;
        }
    }

    foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_CHARSET'] as $key) {
        $runtimeValue = getenv($key);
        if ($runtimeValue !== false) {
            $env[$key] = (string)$runtimeValue;
        }
    }

    return $env;
}

function loadEnvFile(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $values = [];
    foreach ((array)file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim((string)$line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $rawValue] = explode('=', $line, 2);
        $name = trim($name);
        if ($name === '') {
            continue;
        }

        $value = trim($rawValue);
        $length = strlen($value);
        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        $values[$name] = $value;
    }

    return $values;
}

function firstExistingPath(array $paths): ?string
{
    foreach ($paths as $path) {
        if (is_string($path) && $path !== '' && is_file($path)) {
            return $path;
        }
    }

    return null;
}

function validateEnvironment(array $env): void
{
    foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
        if (trim((string)($env[$key] ?? '')) === '') {
            fail("Required database env key is missing: {$key}");
        }
    }
}

function createPdo(array $env): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $env['DB_HOST'],
        $env['DB_PORT'],
        $env['DB_DATABASE'],
        $env['DB_CHARSET'] ?? 'utf8'
    );

    try {
        return new PDO($dsn, (string)$env['DB_USERNAME'], (string)$env['DB_PASSWORD'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $exception) {
        fail('Database connection failed: ' . $exception->getMessage());
    }
}

function ensureTrackerTable(PDO $pdo, string $trackerTable, bool $dryRun): void
{
    if ($dryRun) {
        out('PLAN', "Ensure migration tracker table exists: {$trackerTable}");
        return;
    }

    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `{$trackerTable}` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope` VARCHAR(32) NOT NULL,
  `migration_key` VARCHAR(255) NOT NULL,
  `checksum` CHAR(40) NOT NULL,
  `status` VARCHAR(32) NOT NULL,
  `note` TEXT NULL,
  `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_scope_migration` (`scope`, `migration_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    $pdo->exec($sql);
    out('PASS', "Migration tracker ready: {$trackerTable}");
}

function countUserTables(PDO $pdo, string $trackerTable): int
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME <> ?'
    );
    $statement->execute([$trackerTable]);

    return (int)$statement->fetchColumn();
}

function tableExists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $statement->execute([$table]);

    return (int)$statement->fetchColumn() > 0;
}

function applyAdminAuthorizationSeed(PDO $pdo, ?string $seedPath, bool $dryRun): void
{
    if (!tableExists($pdo, 'admin_permission') || !tableExists($pdo, 'admin_role')) {
        out('SKIP', 'Admin authorization seed skipped because auth tables are not available yet.');
        return;
    }

    $needsSeed = countRows($pdo, 'admin_permission') === 0
        || countRows($pdo, 'admin_role') === 0
        || (
            tableExists($pdo, 'admin_role_permission')
            && countWhere($pdo, 'admin_role_permission', '`role_id` = 1') === 0
        )
        || (
            tableExists($pdo, 'admin_admin')
            && tableExists($pdo, 'admin_admin_role')
            && countWhere($pdo, 'admin_admin', '`id` = 1') > 0
            && countWhere($pdo, 'admin_admin_role', '`admin_id` = 1 AND `role_id` = 1') === 0
        );

    if (!$needsSeed) {
        out('SKIP', 'Admin authorization seed already satisfied.');
        return;
    }

    if ($seedPath === null || !is_file($seedPath)) {
        fail('Admin authorization seed file was not found. Clean installs would miss default admin write permissions.');
    }

    $sqlContent = file_get_contents($seedPath);
    if ($sqlContent === false) {
        fail("Unable to read admin authorization seed file: {$seedPath}");
    }

    $statements = splitSqlStatements(stripUtf8Bom((string)$sqlContent));
    $statementCount = count($statements);
    if ($statementCount === 0) {
        fail("Admin authorization seed file does not contain executable SQL: {$seedPath}");
    }

    if ($dryRun) {
        out('PLAN', sprintf('Would apply admin authorization seed (%d statements)', $statementCount));
        return;
    }

    out('RUN', sprintf('Applying admin authorization seed (%d statements)', $statementCount));

    try {
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
        out('PASS', 'Admin authorization seed applied.');
    } catch (PDOException $exception) {
        fail('Admin authorization seed failed: ' . $exception->getMessage());
    }
}

function countRows(PDO $pdo, string $table): int
{
    $statement = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
    return (int)$statement->fetchColumn();
}

function countWhere(PDO $pdo, string $table, string $whereClause): int
{
    $statement = $pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE {$whereClause}");
    return (int)$statement->fetchColumn();
}

function discoverSqlFiles(string $directory, string $scope): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $files = array_values(array_filter(
        glob($directory . DIRECTORY_SEPARATOR . '*.sql') ?: [],
        static fn(string $path): bool => is_file($path)
    ));
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);

    return array_map(
        static fn(string $path): array => [
            'scope' => $scope,
            'migration_key' => basename($path),
            'path' => $path,
        ],
        $files
    );
}

function discoverPluginMigrations(string $pluginRoot): array
{
    if (!is_dir($pluginRoot)) {
        return [];
    }

    $migrations = [];
    $pluginDirectories = array_values(array_filter(
        glob($pluginRoot . DIRECTORY_SEPARATOR . '*') ?: [],
        static fn(string $path): bool => is_dir($path)
    ));
    sort($pluginDirectories, SORT_NATURAL | SORT_FLAG_CASE);

    foreach ($pluginDirectories as $pluginDirectory) {
        $pluginCode = basename($pluginDirectory);
        $migrationDirectory = $pluginDirectory . DIRECTORY_SEPARATOR . 'migrations';
        if (!is_dir($migrationDirectory)) {
            continue;
        }

        $files = array_values(array_filter(
            glob($migrationDirectory . DIRECTORY_SEPARATOR . '*.sql') ?: [],
            static fn(string $path): bool => is_file($path)
        ));
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($files as $path) {
            $migrations[] = [
                'scope' => 'plugin',
                'migration_key' => $pluginCode . '/' . basename($path),
                'path' => $path,
            ];
        }
    }

    return $migrations;
}

function applyMigrationList(PDO $pdo, string $trackerTable, array $migrations, bool $dryRun): void
{
    foreach ($migrations as $migration) {
        applySqlAsset(
            $pdo,
            $trackerTable,
            (string)$migration['scope'],
            (string)$migration['migration_key'],
            (string)$migration['path'],
            $dryRun
        );
    }
}

function applySqlAsset(
    PDO $pdo,
    string $trackerTable,
    string $scope,
    string $migrationKey,
    string $path,
    bool $dryRun
): void {
    if (!is_file($path)) {
        fail("Migration file is missing: {$path}");
    }

    $checksum = sha1_file($path);
    if ($checksum === false) {
        fail("Unable to hash migration file: {$path}");
    }

    $record = findTrackerRecord($pdo, $trackerTable, $scope, $migrationKey);
    if ($record !== null) {
        $recordChecksum = (string)($record['checksum'] ?? '');
        if ($recordChecksum !== '' && !hashEqualsSafe($recordChecksum, $checksum)) {
            fail("Migration checksum changed after it was recorded: {$scope}:{$migrationKey}");
        }

        out('SKIP', "Already recorded: {$scope}:{$migrationKey} [{$record['status']}]");
        return;
    }

    $sqlContent = file_get_contents($path);
    if ($sqlContent === false) {
        fail("Unable to read migration file: {$path}");
    }

    $statements = splitSqlStatements(stripUtf8Bom((string)$sqlContent));
    $statementCount = count($statements);

    if ($dryRun) {
        out('PLAN', sprintf('Would apply %s:%s (%d statements)', $scope, $migrationKey, $statementCount));
        return;
    }

    if ($statementCount === 0) {
        recordTracker($pdo, $trackerTable, $scope, $migrationKey, $checksum, 'empty', 'No executable SQL statements found.');
        out('SKIP', "No executable SQL found: {$scope}:{$migrationKey}");
        return;
    }

    out('RUN', sprintf('Applying %s:%s (%d statements)', $scope, $migrationKey, $statementCount));

    try {
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
        recordTracker($pdo, $trackerTable, $scope, $migrationKey, $checksum, 'applied', null);
        out('PASS', "Applied: {$scope}:{$migrationKey}");
    } catch (PDOException $exception) {
        if (isCompatibleMigrationError($exception)) {
            recordTracker($pdo, $trackerTable, $scope, $migrationKey, $checksum, 'compatible_skip', $exception->getMessage());
            out('SKIP', "Compatibility skip: {$scope}:{$migrationKey}");
            return;
        }

        fail("Migration failed for {$scope}:{$migrationKey}: " . $exception->getMessage());
    }
}

function findTrackerRecord(PDO $pdo, string $trackerTable, string $scope, string $migrationKey): ?array
{
    if (!tableExists($pdo, $trackerTable)) {
        return null;
    }

    $statement = $pdo->prepare(
        "SELECT `scope`, `migration_key`, `checksum`, `status`, `note`, `executed_at`
         FROM `{$trackerTable}`
         WHERE `scope` = ? AND `migration_key` = ?
         LIMIT 1"
    );
    $statement->execute([$scope, $migrationKey]);

    $row = $statement->fetch();
    return is_array($row) ? $row : null;
}

function recordTracker(
    PDO $pdo,
    string $trackerTable,
    string $scope,
    string $migrationKey,
    string $checksum,
    string $status,
    ?string $note
): void {
    $statement = $pdo->prepare(
        "INSERT INTO `{$trackerTable}` (`scope`, `migration_key`, `checksum`, `status`, `note`)
         VALUES (?, ?, ?, ?, ?)"
    );
    $statement->execute([$scope, $migrationKey, $checksum, $status, $note]);
}

function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $inSingleQuote = false;
    $inDoubleQuote = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($index = 0; $index < $length; $index++) {
        $char = $sql[$index];
        $next = $index + 1 < $length ? $sql[$index + 1] : '';
        $nextNext = $index + 2 < $length ? $sql[$index + 2] : '';

        if ($inLineComment) {
            $buffer .= $char;
            if ($char === "\n") {
                $inLineComment = false;
            }
            continue;
        }

        if ($inBlockComment) {
            $buffer .= $char;
            if ($char === '*' && $next === '/') {
                $buffer .= '/';
                $index++;
                $inBlockComment = false;
            }
            continue;
        }

        if (!$inSingleQuote && !$inDoubleQuote) {
            if ($char === '#' || ($char === '-' && $next === '-' && ($nextNext === ' ' || $nextNext === "\t" || $nextNext === "\r" || $nextNext === "\n"))) {
                $inLineComment = true;
                $buffer .= $char;
                continue;
            }

            if ($char === '/' && $next === '*') {
                $inBlockComment = true;
                $buffer .= $char;
                continue;
            }
        }

        if ($char === "'" && !$inDoubleQuote) {
            if ($inSingleQuote && $next === "'") {
                $buffer .= $char . $next;
                $index++;
                continue;
            }

            if (!isEscaped($buffer)) {
                $inSingleQuote = !$inSingleQuote;
            }

            $buffer .= $char;
            continue;
        }

        if ($char === '"' && !$inSingleQuote) {
            if ($inDoubleQuote && $next === '"') {
                $buffer .= $char . $next;
                $index++;
                continue;
            }

            if (!isEscaped($buffer)) {
                $inDoubleQuote = !$inDoubleQuote;
            }

            $buffer .= $char;
            continue;
        }

        if ($char === ';' && !$inSingleQuote && !$inDoubleQuote) {
            $statement = trim($buffer);
            if (statementHasExecutableSql($statement)) {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $statement = trim($buffer);
    if (statementHasExecutableSql($statement)) {
        $statements[] = $statement;
    }

    return $statements;
}

function stripUtf8Bom(string $sql): string
{
    if (strncmp($sql, "\xEF\xBB\xBF", 3) === 0) {
        return substr($sql, 3);
    }

    return $sql;
}

function statementHasExecutableSql(string $statement): bool
{
    if ($statement === '') {
        return false;
    }

    $normalized = preg_replace('#/\*.*?\*/#s', '', $statement);
    $normalized = preg_replace('/^\s*(?:--|#).*$\R?/m', '', (string)$normalized);

    return trim((string)$normalized) !== '';
}

function isEscaped(string $buffer): bool
{
    $slashCount = 0;
    for ($index = strlen($buffer) - 1; $index >= 0; $index--) {
        if ($buffer[$index] !== '\\') {
            break;
        }
        $slashCount++;
    }

    return $slashCount % 2 === 1;
}

function isCompatibleMigrationError(PDOException $exception): bool
{
    $driverCode = isset($exception->errorInfo[1]) ? (int)$exception->errorInfo[1] : 0;
    if (in_array($driverCode, [1050, 1060, 1061], true)) {
        return true;
    }

    $message = strtolower($exception->getMessage());
    foreach ([
        'duplicate column name',
        'already exists',
        'duplicate key name',
    ] as $needle) {
        if (str_contains($message, $needle)) {
            return true;
        }
    }

    return false;
}

function hashEqualsSafe(string $left, string $right): bool
{
    if (function_exists('hash_equals')) {
        return hash_equals($left, $right);
    }

    return $left === $right;
}

function out(string $level, string $message): void
{
    fwrite(STDOUT, "[{$level}] {$message}" . PHP_EOL);
}

function fail(string $message): void
{
    fwrite(STDERR, "[FAIL] {$message}" . PHP_EOL);
    exit(1);
}
