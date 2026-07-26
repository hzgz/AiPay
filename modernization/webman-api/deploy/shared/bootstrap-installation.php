#!/usr/bin/env php
<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */


declare(strict_types=1);

require_once __DIR__ . '/install_support.php';

AiPayInstallSupport::requireCli();

$projectRoot = dirname(__DIR__, 2);
$options = parseOptions(array_slice($argv, 1));

if ($options['help']) {
    fwrite(STDOUT, <<<TEXT
Usage:
  php deploy/shared/bootstrap-installation.php [options]

Options:
  --admin-user=NAME          Admin username. Default: adminroot
  --admin-password=PASS      Admin password. Default: auto-generate
  --admin-nickname=NAME      Admin nickname. Default: AiPayAdmin
  --merchant-user=NAME       Optional demo merchant username.
  --merchant-password=PASS   Demo merchant password. Default: auto-generate when merchant is enabled
  --merchant-email=EMAIL     Demo merchant email. Default: <username>@aipay.local
  --merchant-name=NAME       Demo merchant display name. Default: Demo Merchant
  --merchant-remarks=TEXT    Demo merchant remarks. Default: system seeded merchant
  --skip-payment-methods     Skip seeding base payment methods.
  --json                     Output machine-readable JSON only.
  --help                     Show this help message.
TEXT . PHP_EOL);
    exit(0);
}

if ($options['admin-password'] === '') {
    $options['admin-password'] = AiPayInstallSupport::generatePassword(20);
}

if ($options['merchant-user'] !== '' && $options['merchant-password'] === '') {
    $options['merchant-password'] = AiPayInstallSupport::generatePassword(18);
}

$pdo = AiPayInstallSupport::createPdo($projectRoot);
$summary = [];

$pdo->beginTransaction();
try {
    $summary['admin'] = AiPayInstallSupport::ensureAdmin($pdo, [
        'username' => $options['admin-user'],
        'password' => $options['admin-password'],
        'nickname' => $options['admin-nickname'],
    ]);

    if (!$options['skip-payment-methods']) {
        $summary['payment_methods'] = AiPayInstallSupport::ensurePaymentMethods($pdo);
    } else {
        $summary['payment_methods'] = [];
    }

    $summary['system_config'] = AiPayInstallSupport::ensureSystemConfigDefaults($pdo);

    if ($options['merchant-user'] !== '') {
        $summary['merchant'] = AiPayInstallSupport::ensureMerchant($pdo, [
            'username' => $options['merchant-user'],
            'password' => $options['merchant-password'],
            'email' => $options['merchant-email'],
            'name' => $options['merchant-name'],
            'remarks' => $options['merchant-remarks'],
        ]);
    } else {
        $summary['merchant'] = null;
    }

    $pdo->commit();
} catch (Throwable $throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, '[FAIL] ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

if ($options['json']) {
    AiPayInstallSupport::printJson([
        'ok' => true,
        'summary' => $summary,
    ]);
    exit(0);
}

fwrite(STDOUT, sprintf(
    "[PASS] admin ready: %s\n",
    (string)$summary['admin']['username']
));
if ($summary['merchant'] !== null) {
    fwrite(STDOUT, sprintf(
        "[PASS] demo merchant ready: %s\n",
        (string)$summary['merchant']['username']
    ));
}
if (!$options['skip-payment-methods']) {
    fwrite(STDOUT, sprintf(
        "[PASS] payment methods ready: %d\n",
        count($summary['payment_methods'])
    ));
}
AiPayInstallSupport::printJson([
    'ok' => true,
    'summary' => $summary,
]);

function parseOptions(array $arguments): array
{
    $options = [
        'admin-user' => 'adminroot',
        'admin-password' => '',
        'admin-nickname' => 'AiPayAdmin',
        'merchant-user' => '',
        'merchant-password' => '',
        'merchant-email' => '',
        'merchant-name' => 'Demo Merchant',
        'merchant-remarks' => 'system seeded merchant',
        'skip-payment-methods' => false,
        'json' => false,
        'help' => false,
    ];

    foreach ($arguments as $argument) {
        if ($argument === '--skip-payment-methods') {
            $options['skip-payment-methods'] = true;
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
