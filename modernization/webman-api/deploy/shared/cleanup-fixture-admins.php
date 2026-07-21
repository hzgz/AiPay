#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/install_support.php';

AiPayInstallSupport::requireCli();

$projectRoot = dirname(__DIR__, 2);
$options = parseOptions(array_slice($argv, 1));

try {
    $pdo = AiPayInstallSupport::createPdo($projectRoot);
    $fixtureAdmins = loadFixtureAdmins($pdo);

    $payload = [
        'ok' => true,
        'dry_run' => !$options['execute'],
        'fixture_count' => count($fixtureAdmins),
        'fixtures' => $fixtureAdmins,
        'affected_tables' => [],
    ];

    if ($fixtureAdmins !== [] && $options['execute']) {
        $pdo->beginTransaction();
        $payload['affected_tables'] = cleanupFixtureAdmins($pdo, $fixtureAdmins);
        $pdo->commit();
    }
} catch (Throwable $throwable) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, '[FAIL] ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

if ($options['json']) {
    AiPayInstallSupport::printJson($payload);
    exit(0);
}

if ($payload['fixture_count'] === 0) {
    fwrite(STDOUT, "[PASS] no fixture admin accounts were found.\n");
    AiPayInstallSupport::printJson($payload);
    exit(0);
}

if ($options['execute']) {
    fwrite(STDOUT, sprintf(
        "[PASS] removed %d fixture admin accounts.\n",
        $payload['fixture_count']
    ));
} else {
    fwrite(STDOUT, sprintf(
        "[WARN] detected %d fixture admin accounts. Re-run with --execute to remove them.\n",
        $payload['fixture_count']
    ));
}

AiPayInstallSupport::printJson($payload);

function parseOptions(array $arguments): array
{
    $options = [
        'execute' => false,
        'json' => false,
        'help' => false,
    ];

    foreach ($arguments as $argument) {
        if ($argument === '--execute') {
            $options['execute'] = true;
            continue;
        }

        if ($argument === '--json') {
            $options['json'] = true;
            continue;
        }

        if ($argument === '--help') {
            $options['help'] = true;
            continue;
        }

        fwrite(STDERR, "Unknown argument: {$argument}\n");
        fwrite(STDERR, "Run with --help to see supported options.\n");
        exit(1);
    }

    if ($options['help']) {
        fwrite(STDOUT, <<<TEXT
Usage:
  php deploy/shared/cleanup-fixture-admins.php [--execute] [--json] [--help]

Options:
  --execute    Actually delete detected fixture admin accounts and their related rows.
  --json       Output machine-readable JSON only.
  --help       Show this help message.
TEXT . PHP_EOL);
        exit(0);
    }

    return $options;
}

/**
 * @return array<int, array{id:int,username:string,status:int}>
 */
function loadFixtureAdmins(PDO $pdo): array
{
    $statement = $pdo->query('SELECT id, username, status FROM admin_admin WHERE id <> 1 ORDER BY id');
    $fixtures = [];

    foreach ($statement->fetchAll() as $row) {
        $username = trim((string)($row['username'] ?? ''));
        if ($username === '' || !isFixtureAdminUsername($username)) {
            continue;
        }

        $fixtures[] = [
            'id' => (int)($row['id'] ?? 0),
            'username' => $username,
            'status' => (int)($row['status'] ?? 0),
        ];
    }

    return $fixtures;
}

/**
 * @param array<int, array{id:int,username:string,status:int}> $fixtureAdmins
 * @return array<string, int>
 */
function cleanupFixtureAdmins(PDO $pdo, array $fixtureAdmins): array
{
    $ids = array_values(array_filter(array_map(
        static fn (array $row): int => (int)($row['id'] ?? 0),
        $fixtureAdmins
    )));

    if ($ids === []) {
        return [];
    }

    $affected = [];
    $tables = findAdminLinkedTables($pdo);
    foreach ($tables as $table) {
        $affected[$table] = deleteByAdminIds($pdo, $table, $ids);
    }

    $affected['admin_admin'] = deleteByIds($pdo, 'admin_admin', $ids);

    return array_filter(
        $affected,
        static fn (int $count): bool => $count > 0
    );
}

/**
 * @return array<int, string>
 */
function findAdminLinkedTables(PDO $pdo): array
{
    $statement = $pdo->prepare(
        'SELECT TABLE_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND COLUMN_NAME = :column
           AND TABLE_NAME <> :self
         ORDER BY TABLE_NAME'
    );
    $statement->execute([
        ':column' => 'admin_id',
        ':self' => 'admin_admin',
    ]);

    return array_values(array_filter(array_map(
        static fn (array $row): string => trim((string)($row['TABLE_NAME'] ?? '')),
        $statement->fetchAll()
    )));
}

/**
 * @param array<int, int> $ids
 */
function deleteByAdminIds(PDO $pdo, string $table, array $ids): int
{
    if ($ids === []) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = $pdo->prepare(sprintf(
        'DELETE FROM `%s` WHERE `admin_id` IN (%s)',
        str_replace('`', '``', $table),
        $placeholders
    ));
    $statement->execute($ids);

    return $statement->rowCount();
}

/**
 * @param array<int, int> $ids
 */
function deleteByIds(PDO $pdo, string $table, array $ids): int
{
    if ($ids === []) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = $pdo->prepare(sprintf(
        'DELETE FROM `%s` WHERE `id` IN (%s)',
        str_replace('`', '``', $table),
        $placeholders
    ));
    $statement->execute($ids);

    return $statement->rowCount();
}

function isFixtureAdminUsername(string $username): bool
{
    return (bool)preg_match(
        '/^(suite_|ppla_|paya_|cca_|adm_|ops_|role_|menu_|delverify_)/i',
        $username
    );
}
