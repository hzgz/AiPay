<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

$repoRoot = dirname(__DIR__);
$sourcePath = $repoRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'data.sql';
$targetDirectory = $repoRoot . DIRECTORY_SEPARATOR . 'modernization' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'install';
$targetPath = $targetDirectory . DIRECTORY_SEPARATOR . 'base-schema.sql';
$seedPath = $targetDirectory . DIRECTORY_SEPARATOR . 'admin-auth-seed.sql';

require_once $repoRoot . DIRECTORY_SEPARATOR . 'modernization' . DIRECTORY_SEPARATOR . 'webman-api' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'support' . DIRECTORY_SEPARATOR . 'AdminPermissionMigrationMapper.php';

if (!is_file($sourcePath)) {
    fwrite(STDERR, "Legacy source SQL was not found: {$sourcePath}\n");
    exit(1);
}

$sourceContent = file_get_contents($sourcePath);
if ($sourceContent === false) {
    fwrite(STDERR, "Failed to read legacy source SQL.\n");
    exit(1);
}

$sourceContent = normalizeSqlEncoding($sourceContent);
$lines = preg_split('/\R/u', $sourceContent);
if (!is_array($lines)) {
    fwrite(STDERR, "Failed to split legacy source SQL into lines.\n");
    exit(1);
}

$statements = [];
$mode = null;
$buffer = [];

foreach ($lines as $index => $line) {
    if ($index === 0) {
        $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
    }

    $trimmed = trim($line);
    if ($trimmed === '') {
        continue;
    }

    if ($mode === null) {
        if (str_starts_with($trimmed, 'CREATE TABLE `')) {
            $mode = 'create';
            $buffer = [sanitizeSchemaLine($trimmed)];
            if (preg_match('/^\)\s*ENGINE=.*;\s*$/i', $trimmed) === 1) {
                $statements[] = implode(PHP_EOL, $buffer);
                $mode = null;
                $buffer = [];
            }
            continue;
        }

        if (str_starts_with($trimmed, 'ALTER TABLE `')) {
            $mode = 'alter';
            $buffer = [sanitizeSchemaLine($trimmed)];
            if (str_ends_with(rtrim($trimmed), ';')) {
                $statements[] = implode(PHP_EOL, $buffer);
                $mode = null;
                $buffer = [];
            }
            continue;
        }

        continue;
    }

    $buffer[] = sanitizeSchemaLine($trimmed);

    if ($mode === 'create' && preg_match('/^\)\s*ENGINE=.*;\s*$/i', $trimmed) === 1) {
        $statements[] = implode(PHP_EOL, $buffer);
        $mode = null;
        $buffer = [];
        continue;
    }

    if ($mode === 'alter' && str_ends_with(rtrim($trimmed), ';')) {
        $statements[] = implode(PHP_EOL, $buffer);
        $mode = null;
        $buffer = [];
    }
}

if ($mode !== null || $buffer !== []) {
    fwrite(STDERR, "Source SQL parsing did not finish cleanly.\n");
    exit(1);
}

if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
    fwrite(STDERR, "Failed to create target directory: {$targetDirectory}\n");
    exit(1);
}

$header = [
    '-- Generated from app/install/data/data.sql',
    '-- Only schema and ALTER statements are preserved.',
    '-- Seed data is intentionally excluded and should be inserted separately.',
    '',
];

$content = implode(PHP_EOL, array_merge($header, $statements, ['']));
if (file_put_contents($targetPath, $content) === false) {
    fwrite(STDERR, "Failed to write clean base schema: {$targetPath}\n");
    exit(1);
}

$permissionRows = buildPermissionSeedRows($lines);
if ($permissionRows === []) {
    fwrite(STDERR, "Failed to rebuild admin authorization seed rows from legacy source SQL.\n");
    exit(1);
}

$permissionInsert = buildPermissionInsertSql($permissionRows);
$roleInsert = implode(PHP_EOL, [
    'INSERT IGNORE INTO `admin_role` (`id`, `name`, `desc`, `create_time`, `update_time`, `delete_time`) VALUES',
    "(1, '超级管理员', '默认超级管理员角色', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL);",
]);

$seedContent = implode(PHP_EOL, [
    '-- Generated from app/install/data/data.sql',
    '-- Minimal admin authorization seed for clean installs.',
    '-- Safe to rerun; duplicate rows are skipped or filtered.',
    '',
    $permissionInsert,
    '',
    $roleInsert,
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

if (file_put_contents($seedPath, $seedContent) === false) {
    fwrite(STDERR, "Failed to write admin authorization seed: {$seedPath}\n");
    exit(1);
}

fwrite(STDOUT, json_encode([
    'target' => $targetPath,
    'statement_count' => count($statements),
    'seed_target' => $seedPath,
    'seed_permission_count' => count($permissionRows),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);

function sanitizeSchemaLine(string $line): string
{
    $trimmed = trim($line);
    if ($trimmed === '') {
        return '';
    }

    if (preg_match('/^\)\s*ENGINE=/i', $trimmed) === 1) {
        return ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
    }

    $hasComma = preg_match('/,\s*$/', $trimmed) === 1;
    $hasSemicolon = preg_match('/;\s*$/', $trimmed) === 1;

    $sanitized = preg_replace("/\s+COMMENT\s+'.*$/u", '', $trimmed) ?? $trimmed;
    $sanitized = rtrim($sanitized, ",; \t");

    if ($hasComma) {
        $sanitized .= ',';
    } elseif ($hasSemicolon) {
        $sanitized .= ';';
    }

    return $sanitized;
}

function extractInsertStatement(array $lines, string $prefix): ?string
{
    $lines = extractInsertLines($lines, $prefix);
    return $lines === [] ? null : implode(PHP_EOL, $lines);
}

function normalizeSqlEncoding(string $content): string
{
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
    $encoding = mb_detect_encoding($content, ['UTF-8', 'GB18030', 'GBK', 'GB2312'], true);
    if ($encoding === false) {
        $encoding = 'GB18030';
    }

    if (strtoupper($encoding) !== 'UTF-8') {
        $converted = mb_convert_encoding($content, 'UTF-8', $encoding);
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }
    }

    return $content;
}

function extractInsertLines(array $lines, string $prefix): array
{
    $capturing = false;
    $buffer = [];

    foreach ($lines as $index => $line) {
        if ($index === 0) {
            $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
        }

        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }

        if (!$capturing) {
            if (!str_starts_with($trimmed, $prefix)) {
                continue;
            }

            $capturing = true;
            $buffer[] = $trimmed;

            if (str_ends_with(rtrim($trimmed), ';')) {
                return $buffer;
            }

            continue;
        }

        $buffer[] = $trimmed;
        if (str_ends_with(rtrim($trimmed), ';')) {
            return $buffer;
        }
    }

    return [];
}

function buildPermissionSeedRows(array $lines): array
{
    $insertLines = extractInsertLines($lines, 'INSERT INTO `admin_permission`');
    if ($insertLines === []) {
        return [];
    }

    $rows = [];
    foreach (array_slice($insertLines, 1) as $line) {
        $trimmed = rtrim(trim($line), ',;');
        if ($trimmed === '') {
            continue;
        }

        if (preg_match(
            "/^\\((\\d+),\\s*(\\d+),\\s*.+,\\s*'([^']*)',\\s*(NULL|'([^']*)'),\\s*(\\d+),\\s*(\\d+),\\s*(\\d+)\\)$/u",
            $trimmed,
            $matches
        ) !== 1) {
            continue;
        }

        $id = (int)$matches[1];
        $pid = (int)$matches[2];
        $href = (string)$matches[3];
        $icon = strtoupper((string)$matches[4]) === 'NULL' ? null : (string)($matches[5] ?? '');
        $sort = (int)$matches[6];
        $type = (int)$matches[7];
        $status = (int)$matches[8];

        $rows[] = [
            'id' => $id,
            'pid' => $pid,
            'title' => buildPermissionTitle($id, $pid, $href),
            'href' => $href,
            'icon' => $icon,
            'sort' => $sort,
            'type' => $type,
            'status' => $status,
        ];
    }

    usort(
        $rows,
        static fn(array $left, array $right): int => (int)$left['id'] <=> (int)$right['id']
    );

    return $rows;
}

function buildPermissionInsertSql(array $rows): string
{
    $values = [];

    foreach ($rows as $row) {
        $values[] = sprintf(
            "(%d, %d, %s, %s, %s, %d, %d, %d)",
            (int)$row['id'],
            (int)$row['pid'],
            sqlString((string)$row['title']),
            sqlString((string)$row['href']),
            $row['icon'] === null ? 'NULL' : sqlString((string)$row['icon']),
            (int)$row['sort'],
            (int)$row['type'],
            (int)$row['status'],
        );
    }

    return implode(PHP_EOL, [
        'INSERT IGNORE INTO `admin_permission` (`id`, `pid`, `title`, `href`, `icon`, `sort`, `type`, `status`) VALUES',
        implode(',' . PHP_EOL, $values) . ';',
    ]);
}

function buildPermissionTitle(int $id, int $pid, string $href): string
{
    $href = trim($href);
    if ($href === '') {
        return legacyGroupTitle($id, $pid);
    }

    $description = \app\support\AdminPermissionMigrationMapper::describe([
        'id' => $id,
        'pid' => $pid,
        'title' => '',
        'href' => $href,
    ]);

    $menuTitle = trim((string)($description['modern_menu_title'] ?? ''));
    if ($menuTitle === '') {
        $menuTitle = fallbackMenuTitle($href, $id);
    }

    $action = trim((string)($description['legacy_action'] ?? ''));
    return legacyActionTitle($menuTitle, $action);
}

function legacyGroupTitle(int $id, int $pid): string
{
    $rootTitles = [
        1 => '系统管理',
        22 => '系统管理',
        34 => '支付配置',
        53 => '商户管理',
        78 => '订单中心',
        91 => '风控中心',
        98 => '内容中心',
        148 => '工单中心',
        174 => '主题模板',
    ];

    if ($pid === 0) {
        return $rootTitles[$id] ?? ('系统分组 ' . $id);
    }

    return '权限分组 ' . $id;
}

function fallbackMenuTitle(string $href, int $id): string
{
    $normalized = trim($href, '/');
    if ($normalized === '') {
        return '权限节点 ' . $id;
    }

    $parts = explode('/', $normalized);
    $tail = trim((string)end($parts));
    if ($tail === '') {
        return '权限节点 ' . $id;
    }

    return strtoupper($tail) === $tail ? $tail : ucfirst(str_replace(['.', '_', '-'], ' ', $tail));
}

function legacyActionTitle(string $menuTitle, string $action): string
{
    $menuTitle = trim($menuTitle) !== '' ? trim($menuTitle) : '权限节点';

    return match ($action) {
        '', 'index', 'home' => $menuTitle,
        'add' => '新增' . $menuTitle,
        'edit' => '编辑' . $menuTitle,
        'status' => $menuTitle . '状态切换',
        'remove' => '删除' . $menuTitle,
        'batchRemove' => '批量删除' . $menuTitle,
        'recycle' => $menuTitle . '回收站',
        'role' => $menuTitle . '分配角色',
        'permission' => $menuTitle . '分配权限',
        'sort' => $menuTitle . '排序',
        'target' => $menuTitle . '打开方式',
        'removeLog' => '清空' . $menuTitle,
        'addPhoto' => '新增单图素材',
        'addPhotos' => '新增多图素材',
        'list' => $menuTitle . '列表',
        'del' => '删除' . $menuTitle,
        'plus' => '人工调整' . $menuTitle,
        'clear' => '清理' . $menuTitle,
        'email' => $menuTitle . '邮件发送',
        default => $menuTitle . ' ' . $action,
    };
}

function sqlString(string $value): string
{
    return "'" . str_replace("'", "''", $value) . "'";
}
