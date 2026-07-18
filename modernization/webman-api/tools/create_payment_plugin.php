#!/usr/bin/env php
<?php

declare(strict_types=1);

use app\service\payment\PaymentPluginScaffoldGenerator;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "该脚本只能在命令行环境中运行。\n");
    exit(1);
}

try {
    $options = parseArguments($argv);
    $generator = new PaymentPluginScaffoldGenerator(dirname(__DIR__));
    $created = $generator->generate($options);
    printSummary($created);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

function parseArguments(array $argv): array
{
    $args = $argv;
    array_shift($args);

    if ($args === [] || in_array('--help', $args, true) || in_array('-h', $args, true)) {
        printUsage();
        exit($args === [] ? 1 : 0);
    }

    $options = [
        'code' => null,
        'name' => null,
        'provider' => 'AiPay 官方',
        'description' => 'AiPay 支付插件脚手架，用于生成独立插件目录与生命周期配置。',
        'version' => '0.1.0',
        'capabilities' => PaymentPluginScaffoldGenerator::defaultCapabilities(),
    ];
    $positionals = [];

    for ($index = 0; $index < count($args); $index++) {
        $argument = $args[$index];

        if (!str_starts_with($argument, '--')) {
            $positionals[] = $argument;
            continue;
        }

        [$key, $value] = parseOption($args, $index);

        switch ($key) {
            case 'code':
            case 'name':
            case 'provider':
            case 'description':
            case 'version':
                $options[$key] = $value;
                break;
            case 'capabilities':
                $options['capabilities'] = $value === '' ? [] : array_map('trim', explode(',', $value));
                break;
            default:
                throw new InvalidArgumentException("未知参数 [--$key]");
        }
    }

    if ($options['code'] === null && isset($positionals[0])) {
        $options['code'] = $positionals[0];
    }

    if ($options['name'] === null && isset($positionals[1])) {
        $options['name'] = $positionals[1];
    }

    if ($options['provider'] === 'AiPay 官方' && isset($positionals[2])) {
        $options['provider'] = $positionals[2];
    }

    if ($options['description'] === 'AiPay 支付插件脚手架，用于生成独立插件目录与生命周期配置。' && isset($positionals[3])) {
        $options['description'] = $positionals[3];
    }

    return $options;
}

function parseOption(array $args, int &$index): array
{
    $argument = $args[$index];
    $position = strpos($argument, '=');

    if ($position !== false) {
        return [
            substr($argument, 2, $position - 2),
            substr($argument, $position + 1),
        ];
    }

    $key = substr($argument, 2);
    if (!isset($args[$index + 1])) {
        throw new InvalidArgumentException("参数 [--$key] 缺少取值");
    }

    $index++;

    return [$key, $args[$index]];
}

function printSummary(array $created): void
{
    echo '支付插件脚手架已创建。' . PHP_EOL;
    echo '插件编码：' . ($created['plugin_code'] ?? '') . PHP_EOL;
    echo '插件类名：' . ($created['class'] ?? '') . PHP_EOL;
    echo '插件目录：' . ($created['plugin_directory'] ?? '') . PHP_EOL;
    echo '声明能力：' . implode(', ', (array)($created['capabilities'] ?? [])) . PHP_EOL;
    echo '生成文件：' . PHP_EOL;

    foreach ((array)($created['files'] ?? []) as $file) {
        echo '  - ' . $file . PHP_EOL;
    }

    echo '下一步：先在 src/Plugin.php 中补齐真实支付逻辑，再通过后台插件生命周期接口完成安装与验证。' . PHP_EOL;
}

function printUsage(): void
{
    $usage = <<<TXT
用法：
  php tools/create_payment_plugin.php --code=demo_gateway --name="演示通道"
  php tools/create_payment_plugin.php demo_gateway "演示通道" "AiPay 官方" "演示插件脚手架"

参数：
  --code            插件编码，只允许小写字母、数字和下划线。
  --name            插件显示名称。
  --provider        写入 plugin.json 的提供方名称。
  --description     写入 plugin.json 与 README.md 的插件说明。
  --version         初始语义化版本，默认：0.1.0
  --capabilities    逗号分隔的能力列表，默认：create_order,query,refund,notify
  --help, -h        显示帮助信息。
TXT;

    echo $usage . PHP_EOL;
}
