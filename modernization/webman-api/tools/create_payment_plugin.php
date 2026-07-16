#!/usr/bin/env php
<?php

declare(strict_types=1);

use app\payment\PaymentPluginScaffoldGenerator;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
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
        'provider' => 'Aipay modernization',
        'description' => 'Generated payment plugin scaffold for the Webman payment lifecycle flow.',
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
                throw new InvalidArgumentException("unknown option [--$key]");
        }
    }

    if ($options['code'] === null && isset($positionals[0])) {
        $options['code'] = $positionals[0];
    }

    if ($options['name'] === null && isset($positionals[1])) {
        $options['name'] = $positionals[1];
    }

    if ($options['provider'] === 'Aipay modernization' && isset($positionals[2])) {
        $options['provider'] = $positionals[2];
    }

    if ($options['description'] === 'Generated payment plugin scaffold for the Webman payment lifecycle flow.' && isset($positionals[3])) {
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
        throw new InvalidArgumentException("missing value for option [--$key]");
    }

    $index++;

    return [$key, $args[$index]];
}

function printSummary(array $created): void
{
    echo 'Created payment plugin scaffold.' . PHP_EOL;
    echo 'Code: ' . ($created['plugin_code'] ?? '') . PHP_EOL;
    echo 'Class: ' . ($created['class'] ?? '') . PHP_EOL;
    echo 'Directory: ' . ($created['plugin_directory'] ?? '') . PHP_EOL;
    echo 'Capabilities: ' . implode(', ', (array)($created['capabilities'] ?? [])) . PHP_EOL;
    echo 'Files:' . PHP_EOL;

    foreach ((array)($created['files'] ?? []) as $file) {
        echo '  - ' . $file . PHP_EOL;
    }

    echo 'Next: implement the payment logic in src/Plugin.php, then use the admin plugin lifecycle APIs to install and verify it.' . PHP_EOL;
}

function printUsage(): void
{
    $usage = <<<TXT
Usage:
  php tools/create_payment_plugin.php --code=demo_gateway --name="Demo Gateway"
  php tools/create_payment_plugin.php demo_gateway "Demo Gateway" "Aipay modernization" "Gateway scaffold"

Options:
  --code            Plugin code. Lowercase letters, numbers, and underscores only.
  --name            Human-readable plugin name.
  --provider        Provider label written into plugin.json.
  --description     Plugin description written into plugin.json and README.md.
  --version         Semantic version for the initial scaffold. Default: 0.1.0
  --capabilities    Comma-separated capability list. Default: create_order,query,refund,notify
  --help, -h        Show this help message.
TXT;

    echo $usage . PHP_EOL;
}
