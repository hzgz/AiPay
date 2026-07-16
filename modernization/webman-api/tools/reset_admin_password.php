#!/usr/bin/env php
<?php

declare(strict_types=1);

use app\support\LegacyPassword;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

$projectRoot = dirname(__DIR__);
$username = trim((string)($argv[1] ?? 'admin'));
$password = trim((string)($argv[2] ?? ''));

require_once $projectRoot . '/app/support/LegacyPassword.php';

if ($username === '') {
    fwrite(STDERR, "[FAIL] username is required.\n");
    exit(1);
}

if ($password === '') {
    $password = generatePassword(20);
}

[$pdo] = bootstrapDatabase($projectRoot);
$stmt = $pdo->prepare('UPDATE admin_admin SET password = :password, update_time = :update_time WHERE username = :username AND status = 1');
$stmt->execute([
    ':username' => $username,
    ':password' => LegacyPassword::hash($password),
    ':update_time' => date('Y-m-d H:i:s'),
]);

if ($stmt->rowCount() < 1) {
    fwrite(STDERR, "[FAIL] active admin account not found: {$username}\n");
    exit(1);
}

echo json_encode([
    'ok' => true,
    'username' => $username,
    'password' => $password,
    'updated_at' => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

function bootstrapDatabase(string $projectRoot): array
{
    $config = require $projectRoot . '/config/database.php';
    $connection = $config['connections'][$config['default']] ?? null;
    if (!is_array($connection)) {
        throw new RuntimeException('database configuration is invalid');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $connection['host'],
        $connection['port'],
        $connection['database'],
        $connection['charset'] ?? 'utf8'
    );

    $pdo = new PDO($dsn, (string)$connection['username'], (string)$connection['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return [$pdo, $connection];
}

function generatePassword(int $length): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
    $password = '';
    $max = strlen($alphabet) - 1;

    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $max)];
    }

    return $password;
}
