#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/install_support.php';

AiPayInstallSupport::requireCli();

$projectRoot = dirname(__DIR__, 2);
$options = parseOptions(array_slice($argv, 1));

if ($options['help']) {
    fwrite(STDOUT, <<<TEXT
Usage:
  php deploy/shared/manage-admin.php [options]

Options:
  --username=NAME       Admin username. Default: adminroot
  --password=PASS       Admin password. Default: auto-generate
  --nickname=NAME       Admin nickname. Default: AiPayAdmin
  --json                Output machine-readable JSON only.
  --help                Show this help message.
TEXT . PHP_EOL);
    exit(0);
}

if ($options['password'] === '') {
    $options['password'] = AiPayInstallSupport::generatePassword(20);
}

try {
    $pdo = AiPayInstallSupport::createPdo($projectRoot);
    $pdo->beginTransaction();
    $summary = AiPayInstallSupport::ensureAdmin($pdo, [
        'username' => $options['username'],
        'password' => $options['password'],
        'nickname' => $options['nickname'],
    ]);
    $pdo->commit();
} catch (Throwable $throwable) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, '[FAIL] ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

$payload = [
    'ok' => true,
    'summary' => $summary,
];

if ($options['json']) {
    AiPayInstallSupport::printJson($payload);
    exit(0);
}

fwrite(STDOUT, sprintf(
    "[PASS] admin account is ready: %s\n",
    (string)$summary['username']
));
AiPayInstallSupport::printJson($payload);

function parseOptions(array $arguments): array
{
    $options = [
        'username' => 'adminroot',
        'password' => '',
        'nickname' => 'AiPayAdmin',
        'json' => false,
        'help' => false,
    ];

    foreach ($arguments as $argument) {
        if ($argument === '--json') {
            $options['json'] = true;
            continue;
        }

        if ($argument === '--help') {
            $options['help'] = true;
            continue;
        }

        if (!str_starts_with($argument, '--') || !str_contains($argument, '=')) {
            failWithUsage("Unknown argument: {$argument}");
        }

        [$name, $value] = explode('=', substr($argument, 2), 2);
        if (!array_key_exists($name, $options)) {
            failWithUsage("Unknown option: --{$name}");
        }

        $options[$name] = trim($value);
    }

    return $options;
}

function failWithUsage(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    fwrite(STDERR, "Run with --help to see supported options.\n");
    exit(1);
}
