<?php

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

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => $envString('DB_HOST', '127.0.0.1'),
            'port' => $envString('DB_PORT', '3306'),
            'database' => $envString('DB_DATABASE', 'pay-v7'),
            'username' => $envString('DB_USERNAME', 'pay-v7'),
            'password' => $envString('DB_PASSWORD', 'pay-v7'),
            'charset' => $envString('DB_CHARSET', 'utf8'),
            'collation' => $envString('DB_COLLATION', 'utf8_general_ci'),
            'prefix' => '',
            'strict' => true,
            'engine' => null,
            'options' => [
                PDO::ATTR_EMULATE_PREPARES => false, // Must be false for Swoole and Swow drivers.
            ],
            'pool' => [
                'max_connections' => 5,
                'min_connections' => 1,
                'wait_timeout' => 3,
                'idle_timeout' => 60,
                'heartbeat_interval' => 50,
            ],
        ],
    ],
];
