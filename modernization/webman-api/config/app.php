<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use support\Request;

$envValue = static function (string $key): string|false {
    $value = getenv($key);
    if ($value !== false) {
        return (string)$value;
    }

    if (array_key_exists($key, $_ENV) && is_scalar($_ENV[$key])) {
        return (string)$_ENV[$key];
    }

    if (array_key_exists($key, $_SERVER) && is_scalar($_SERVER[$key])) {
        return (string)$_SERVER[$key];
    }

    static $envFileCache = null;
    if ($envFileCache === null) {
        $envFileCache = [];
        $envPath = base_path() . DIRECTORY_SEPARATOR . '.env';
        if (is_file($envPath)) {
            foreach ((array)file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim((string)$line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }

                [$name, $rawValue] = explode('=', $line, 2);
                $name = trim($name);
                if ($name === '') {
                    continue;
                }

                $parsedValue = trim($rawValue);
                $length = strlen($parsedValue);
                if ($length >= 2) {
                    $first = $parsedValue[0];
                    $last = $parsedValue[$length - 1];
                    if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                        $parsedValue = substr($parsedValue, 1, -1);
                    }
                }

                $envFileCache[$name] = $parsedValue;
            }
        }
    }

    if (array_key_exists($key, $envFileCache)) {
        return (string)$envFileCache[$key];
    }

    return false;
};

$debugEnv = $envValue('APP_DEBUG');
$appEnv = strtolower(trim((string) ($envValue('APP_ENV') ?: '')));
$debug = $debugEnv === false || trim((string) $debugEnv) === ''
    ? in_array($appEnv, ['dev', 'development', 'local'], true)
    : filter_var($debugEnv, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

if (in_array($appEnv, ['prod', 'production'], true)) {
    $debug = false;
}

return [
    'debug' => $debug ?? false,
    'error_reporting' => E_ALL,
    'default_timezone' => 'Asia/Shanghai',
    'request_class' => Request::class,
    'public_path' => base_path() . DIRECTORY_SEPARATOR . 'public',
    'runtime_path' => base_path(false) . DIRECTORY_SEPARATOR . 'runtime',
    'controller_suffix' => 'Controller',
    'controller_reuse' => false,
];
