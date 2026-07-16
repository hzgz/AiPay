#!/usr/bin/env php
<?php

declare(strict_types=1);

use app\payment\PaymentPluginManager;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

$projectRoot = dirname(__DIR__);
require $projectRoot . '/vendor/autoload.php';
require $projectRoot . '/support/bootstrap.php';
require $projectRoot . '/vendor/webman/database/src/Initializer.php';

$codes = array_values(array_filter(array_slice($argv, 1), static fn ($value) => is_string($value) && trim($value) !== ''));
if ($codes === []) {
    fwrite(STDERR, "Usage: php tools/install_payment_plugins.php <plugin-code> [plugin-code...]\n");
    exit(1);
}

$manager = new PaymentPluginManager();
$operator = [
    'id' => 1,
    'username' => 'admin',
    'nickname' => 'codex',
];

$failed = false;

foreach ($codes as $code) {
    $pluginCode = trim((string)$code);
    if ($pluginCode === '') {
        continue;
    }

    echo "== {$pluginCode} ==" . PHP_EOL;

    try {
        $result = $manager->install($pluginCode, $operator);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    } catch (Throwable $exception) {
        $failed = true;
        fwrite(STDERR, '[FAIL] ' . $pluginCode . ': ' . $exception->getMessage() . PHP_EOL);
    }
}

exit($failed ? 1 : 0);
