<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../modernization/webman-api/deploy/shared/install_support.php';

$repoRoot = dirname(__DIR__);
$backendRoot = $repoRoot . DIRECTORY_SEPARATOR . 'modernization' . DIRECTORY_SEPARATOR . 'webman-api';
$targetDirectory = $repoRoot . DIRECTORY_SEPARATOR . 'modernization' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'install';
$baseSchemaPath = $targetDirectory . DIRECTORY_SEPARATOR . 'base-schema.sql';
$seedPath = $targetDirectory . DIRECTORY_SEPARATOR . 'admin-auth-seed.sql';
$coreInstallPath = $targetDirectory . DIRECTORY_SEPARATOR . 'core-install.sql';

AiPayInstallSupport::loadEnvFile($backendRoot);
$pdo = AiPayInstallSupport::createPdo($backendRoot);

if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
    fwrite(STDERR, "Failed to create target directory: {$targetDirectory}\n");
    exit(1);
}

$coreTables = discoverCoreTables($pdo);
if ($coreTables === []) {
    fwrite(STDERR, "No core tables were discovered from the current database.\n");
    exit(1);
}

$baseSchemaSql = buildBaseSchemaSql($pdo, $coreTables);
$seedSql = buildAdminSeedSql($pdo);
$coreInstallSql = buildCoreInstallSql($baseSchemaSql, $seedSql);

writeFile($baseSchemaPath, $baseSchemaSql);
writeFile($seedPath, $seedSql);
writeFile($coreInstallPath, $coreInstallSql);

fwrite(STDOUT, json_encode([
    'core_table_count' => count($coreTables),
    'core_tables' => $coreTables,
    'base_schema_path' => $baseSchemaPath,
    'admin_seed_path' => $seedPath,
    'core_install_path' => $coreInstallPath,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);

function discoverCoreTables(PDO $pdo): array
{
    $rows = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
    $tables = [];

    foreach ($rows as $row) {
        $table = trim((string)($row[0] ?? ''));
        if ($table === '') {
            continue;
        }

        if ($table === 'aipay_deploy_migrations') {
            continue;
        }

        if (str_starts_with($table, 'pay_plugin_')) {
            continue;
        }

        $tables[] = $table;
    }

    sort($tables, SORT_STRING);
    return $tables;
}

function buildBaseSchemaSql(PDO $pdo, array $tables): string
{
    $chunks = [
        '-- AiPay core database schema',
        '-- Generated from the current Webman database on ' . date('Y-m-d H:i:s'),
        '-- Plugin-owned tables are excluded on purpose and remain managed by plugins/payments/*/migrations.',
        '',
        'SET NAMES utf8mb4;',
        'SET FOREIGN_KEY_CHECKS=0;',
        '',
    ];

    foreach ($tables as $table) {
        $statement = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`');
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $createSql = (string)($row['Create Table'] ?? '');
        if ($createSql === '') {
            throw new RuntimeException("Failed to read CREATE TABLE statement for {$table}");
        }

        $chunks[] = normalizeCreateTableSql($createSql) . ';';
        $chunks[] = '';
    }

    $chunks[] = 'SET FOREIGN_KEY_CHECKS=1;';
    $chunks[] = '';

    return implode(PHP_EOL, $chunks);
}

function normalizeCreateTableSql(string $sql): string
{
    $sql = str_replace("\r\n", "\n", trim($sql));
    $sql = preg_replace('/AUTO_INCREMENT=\d+\s*/i', '', $sql) ?? $sql;
    $sql = preg_replace('/ROW_FORMAT=\w+\s*/i', '', $sql) ?? $sql;
    $sql = preg_replace('/\s+COMMENT=\'(?:[^\'\\\\]|\\\\.)*\'/u', '', $sql) ?? $sql;
    $sql = preg_replace('/;\s*$/', '', $sql) ?? $sql;
    $sql = preg_replace("/\n{3,}/", "\n\n", $sql) ?? $sql;
    return rtrim($sql);
}

function buildAdminSeedSql(PDO $pdo): string
{
    $permissionRows = $pdo
        ->query('SELECT id, pid, title, href, icon, sort, type, status FROM admin_permission ORDER BY id ASC')
        ->fetchAll(PDO::FETCH_ASSOC);

    if ($permissionRows === []) {
        throw new RuntimeException('admin_permission is empty; unable to generate admin authorization seed');
    }

    $roleRow = $pdo
        ->query('SELECT id, name, `desc` FROM admin_role WHERE id = 1 LIMIT 1')
        ->fetch(PDO::FETCH_ASSOC);

    $roleName = trim((string)($roleRow['name'] ?? '超级管理员'));
    $roleDesc = trim((string)($roleRow['desc'] ?? '默认超级管理员角色'));
    if ($roleName === '') {
        $roleName = '超级管理员';
    }
    if ($roleDesc === '') {
        $roleDesc = '默认超级管理员角色';
    }

    $permissionValues = [];
    foreach ($permissionRows as $row) {
        $permissionValues[] = sprintf(
            "(%d, %d, %s, %s, %s, %d, %d, %d)",
            (int)($row['id'] ?? 0),
            (int)($row['pid'] ?? 0),
            sqlValue($row['title'] ?? ''),
            sqlValue($row['href'] ?? ''),
            array_key_exists('icon', $row) && $row['icon'] !== null ? sqlValue($row['icon']) : 'NULL',
            (int)($row['sort'] ?? 0),
            (int)($row['type'] ?? 1),
            (int)($row['status'] ?? 1),
        );
    }

    return implode(PHP_EOL, [
        '-- AiPay admin authorization seed',
        '-- Generated from the current Webman database on ' . date('Y-m-d H:i:s'),
        '-- Safe to rerun on clean or existing databases.',
        '',
        'INSERT IGNORE INTO `admin_permission` (`id`, `pid`, `title`, `href`, `icon`, `sort`, `type`, `status`) VALUES',
        implode(',' . PHP_EOL, $permissionValues) . ';',
        '',
        'INSERT IGNORE INTO `admin_role` (`id`, `name`, `desc`, `create_time`, `update_time`, `delete_time`) VALUES',
        sprintf("(1, %s, %s, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL);", sqlValue($roleName), sqlValue($roleDesc)),
        '',
        'INSERT INTO `admin_admin_role` (`admin_id`, `role_id`)',
        'SELECT 1, 1 FROM DUAL',
        'WHERE EXISTS (SELECT 1 FROM `admin_admin` WHERE `id` = 1)',
        '  AND NOT EXISTS (',
        '    SELECT 1 FROM `admin_admin_role`',
        '    WHERE `admin_id` = 1 AND `role_id` = 1',
        '  );',
        '',
        'INSERT INTO `admin_role_permission` (`role_id`, `permission_id`)',
        'SELECT 1, p.`id`',
        'FROM `admin_permission` AS p',
        'WHERE NOT EXISTS (',
        '  SELECT 1',
        '  FROM `admin_role_permission` AS rp',
        '  WHERE rp.`role_id` = 1',
        '    AND rp.`permission_id` = p.`id`',
        ');',
        '',
    ]);
}

function buildCoreInstallSql(string $baseSchemaSql, string $seedSql): string
{
    $schemaBody = trim($baseSchemaSql);
    $seedBody = trim($seedSql);

    return implode(PHP_EOL, [
        '-- AiPay clean install asset',
        '-- Generated from the current Webman database on ' . date('Y-m-d H:i:s'),
        '-- This file is intended for brand-new installations.',
        '-- Upgrade patches still stay under backend/database/migrations and plugins/payments/*/migrations.',
        '',
        $schemaBody,
        '',
        $seedBody,
        '',
    ]);
}

function sqlValue(mixed $value): string
{
    return "'" . str_replace("'", "''", (string)$value) . "'";
}

function writeFile(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException("Failed to write file: {$path}");
    }
}
