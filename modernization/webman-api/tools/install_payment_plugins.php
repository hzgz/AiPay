#!/usr/bin/env php
<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */


declare(strict_types=1);

use app\service\payment\PaymentPluginManager;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "该脚本只能在命令行环境中运行。\n");
    exit(1);
}

$projectRoot = dirname(__DIR__);
require $projectRoot . '/vendor/autoload.php';
require $projectRoot . '/support/bootstrap.php';
require $projectRoot . '/vendor/webman/database/src/Initializer.php';

$codes = array_values(array_filter(array_slice($argv, 1), static fn ($value) => is_string($value) && trim($value) !== ''));
if ($codes === []) {
    fwrite(STDERR, "用法：php tools/install_payment_plugins.php <plugin-code> [plugin-code...]\n");
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
        fwrite(STDERR, '[失败] ' . $pluginCode . ': ' . $exception->getMessage() . PHP_EOL);
    }
}

exit($failed ? 1 : 0);
