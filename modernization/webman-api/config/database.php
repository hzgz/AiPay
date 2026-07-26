<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

use app\support\Environment;

$envString = static function (string $key, string $default = ''): string {
    if (class_exists(Environment::class)) {
        return Environment::string($key, $default);
    }

    $value = getenv($key);
    if ($value === false || $value === null || $value === '') {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }

    return is_string($value) && $value !== '' ? $value : $default;
};

$envInt = static function (string $key, int $default): int {
    if (class_exists(Environment::class)) {
        return Environment::int($key, $default);
    }

    $value = getenv($key);
    if ($value === false || $value === null || $value === '') {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }

    if (!is_scalar($value)) {
        return $default;
    }

    $normalized = trim((string)$value);

    return $normalized !== '' && is_numeric($normalized) ? (int)$normalized : $default;
};

$poolMaxConnections = max(5, $envInt('DB_POOL_MAX_CONNECTIONS', 20));
$poolMinConnections = max(1, min($poolMaxConnections, $envInt('DB_POOL_MIN_CONNECTIONS', 2)));
$poolWaitTimeout = max(1, $envInt('DB_POOL_WAIT_TIMEOUT', 10));
$poolIdleTimeout = max(10, $envInt('DB_POOL_IDLE_TIMEOUT', 60));
$poolHeartbeatInterval = max(5, $envInt('DB_POOL_HEARTBEAT_INTERVAL', 50));

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => $envString('DB_HOST', '127.0.0.1'),
            'port' => $envString('DB_PORT', '3306'),
            'database' => $envString('DB_DATABASE', 'pay'),
            'username' => $envString('DB_USERNAME', 'pay'),
            'password' => $envString('DB_PASSWORD', 'aipay'),
            'charset' => $envString('DB_CHARSET', 'utf8'),
            'collation' => $envString('DB_COLLATION', 'utf8_general_ci'),
            'prefix' => '',
            'strict' => true,
            'engine' => null,
            'options' => [
                PDO::ATTR_EMULATE_PREPARES => false, // Must be false for Swoole and Swow drivers.
            ],
            'pool' => [
                'max_connections' => $poolMaxConnections,
                'min_connections' => $poolMinConnections,
                'wait_timeout' => $poolWaitTimeout,
                'idle_timeout' => $poolIdleTimeout,
                'heartbeat_interval' => $poolHeartbeatInterval,
            ],
        ],
    ],
];
