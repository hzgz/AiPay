<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\support;

use Throwable;

final class SharedRedis
{
    private const DEFAULT_HOST = '127.0.0.1';
    private const DEFAULT_PORT = 6379;
    private const DEFAULT_DB = 0;
    private const DEFAULT_TIMEOUT = 1.0;
    private const DEFAULT_RETRY_SECONDS = 5;

    /**
     * @var array<string, \Redis>
     */
    private static array $connections = [];

    /**
     * @var array<string, int>
     */
    private static array $retryAt = [];

    private function __construct()
    {
    }

    public static function enabled(): bool
    {
        return class_exists(\Redis::class) && Environment::bool('HOT_PATH_REDIS_ENABLE', true);
    }

    /**
     * @return array<string, mixed>
     */
    public static function sessionConfig(): array
    {
        $maxConnections = max(5, Environment::int('SESSION_REDIS_POOL_MAX_CONNECTIONS', 20));
        $minConnections = max(1, min($maxConnections, Environment::int('SESSION_REDIS_POOL_MIN_CONNECTIONS', 2)));

        return [
            'host' => Environment::string('SESSION_REDIS_HOST', Environment::string('HOT_PATH_REDIS_HOST', self::DEFAULT_HOST)),
            'port' => max(1, Environment::int('SESSION_REDIS_PORT', Environment::int('HOT_PATH_REDIS_PORT', self::DEFAULT_PORT))),
            'auth' => Environment::string('SESSION_REDIS_PASSWORD', Environment::string('HOT_PATH_REDIS_PASSWORD', '')),
            'timeout' => max(0.1, Environment::float('SESSION_REDIS_TIMEOUT', Environment::float('HOT_PATH_REDIS_TIMEOUT', self::DEFAULT_TIMEOUT))),
            'database' => max(0, Environment::int('SESSION_REDIS_DB', Environment::int('HOT_PATH_REDIS_DB', self::DEFAULT_DB))),
            'prefix' => trim(Environment::string('SESSION_REDIS_PREFIX', 'aipay:session:')),
            'pool' => [
                'max_connections' => $maxConnections,
                'min_connections' => $minConnections,
                'wait_timeout' => max(1, Environment::int('SESSION_REDIS_POOL_WAIT_TIMEOUT', 10)),
                'idle_timeout' => max(10, Environment::int('SESSION_REDIS_POOL_IDLE_TIMEOUT', 60)),
                'heartbeat_interval' => max(5, Environment::int('SESSION_REDIS_POOL_HEARTBEAT_INTERVAL', 50)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function softwareNonceConfig(): array
    {
        return [
            'host' => Environment::string('SOFTWARE_NONCE_REDIS_HOST', Environment::string('HOT_PATH_REDIS_HOST', self::DEFAULT_HOST)),
            'port' => max(1, Environment::int('SOFTWARE_NONCE_REDIS_PORT', Environment::int('HOT_PATH_REDIS_PORT', self::DEFAULT_PORT))),
            'auth' => Environment::string('SOFTWARE_NONCE_REDIS_PASSWORD', Environment::string('HOT_PATH_REDIS_PASSWORD', '')),
            'timeout' => max(0.1, Environment::float('SOFTWARE_NONCE_REDIS_TIMEOUT', Environment::float('HOT_PATH_REDIS_TIMEOUT', self::DEFAULT_TIMEOUT))),
            'database' => max(0, Environment::int('SOFTWARE_NONCE_REDIS_DB', Environment::int('HOT_PATH_REDIS_DB', self::DEFAULT_DB))),
            'prefix' => trim(Environment::string('SOFTWARE_NONCE_REDIS_PREFIX', 'aipay:software:nonce:')),
            'persistent' => Environment::bool('SOFTWARE_NONCE_REDIS_PERSISTENT', Environment::bool('HOT_PATH_REDIS_PERSISTENT', true)),
            'persistent_id' => 'aipay-software-nonce',
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function ping(array $config = []): bool
    {
        $redis = self::connection($config);
        if (!$redis instanceof \Redis) {
            return false;
        }

        try {
            $pong = $redis->ping();

            return $pong === true || (is_string($pong) && stripos($pong, 'pong') !== false);
        } catch (Throwable $exception) {
            self::disable(self::cacheKey(self::normalizeConfig($config)), $exception);

            return false;
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function setIfAbsent(string $key, string $value, int $ttl, array $config = []): ?bool
    {
        if ($key === '') {
            return false;
        }

        $normalized = self::normalizeConfig($config);
        $redis = self::connection($normalized);
        if (!$redis instanceof \Redis) {
            return null;
        }

        try {
            $result = $redis->set($key, $value, ['nx', 'ex' => max(1, $ttl)]);

            return $result === true || $result === 'OK';
        } catch (Throwable $exception) {
            self::disable(self::cacheKey($normalized), $exception);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function connection(array $config = []): ?\Redis
    {
        if (!self::enabled()) {
            return null;
        }

        $normalized = self::normalizeConfig($config);
        $cacheKey = self::cacheKey($normalized);
        if (isset(self::$connections[$cacheKey])) {
            return self::$connections[$cacheKey];
        }

        if (time() < (self::$retryAt[$cacheKey] ?? 0)) {
            return null;
        }

        try {
            $client = new \Redis();
            $host = (string)$normalized['host'];
            $port = (int)$normalized['port'];
            $timeout = (float)$normalized['timeout'];
            $persistent = (bool)$normalized['persistent'];
            $persistentId = trim((string)$normalized['persistent_id']);

            $connected = $persistent
                ? ($persistentId === ''
                    ? @$client->pconnect($host, $port, $timeout)
                    : @$client->pconnect($host, $port, $timeout, $persistentId))
                : @$client->connect($host, $port, $timeout);

            if (!$connected) {
                throw new \RuntimeException('redis connection failed');
            }

            $password = trim((string)$normalized['auth']);
            if ($password !== '') {
                $client->auth($password);
            }

            $database = (int)$normalized['database'];
            if ($database > 0) {
                $client->select($database);
            }

            $prefix = trim((string)$normalized['prefix']);
            if ($prefix !== '') {
                $client->setOption(\Redis::OPT_PREFIX, $prefix);
            }

            self::$connections[$cacheKey] = $client;
            self::$retryAt[$cacheKey] = 0;

            return $client;
        } catch (Throwable $exception) {
            self::disable($cacheKey, $exception);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function normalizeConfig(array $config): array
    {
        return [
            'host' => trim((string)($config['host'] ?? Environment::string('HOT_PATH_REDIS_HOST', self::DEFAULT_HOST))),
            'port' => max(1, (int)($config['port'] ?? Environment::int('HOT_PATH_REDIS_PORT', self::DEFAULT_PORT))),
            'auth' => (string)($config['auth'] ?? Environment::string('HOT_PATH_REDIS_PASSWORD', '')),
            'timeout' => max(0.1, (float)($config['timeout'] ?? Environment::float('HOT_PATH_REDIS_TIMEOUT', self::DEFAULT_TIMEOUT))),
            'database' => max(0, (int)($config['database'] ?? Environment::int('HOT_PATH_REDIS_DB', self::DEFAULT_DB))),
            'prefix' => trim((string)($config['prefix'] ?? '')),
            'persistent' => (bool)($config['persistent'] ?? Environment::bool('HOT_PATH_REDIS_PERSISTENT', true)),
            'persistent_id' => trim((string)($config['persistent_id'] ?? 'aipay-shared')),
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function cacheKey(array $config): string
    {
        return md5(json_encode([
            'host' => (string)$config['host'],
            'port' => (int)$config['port'],
            'auth' => (string)$config['auth'],
            'database' => (int)$config['database'],
            'prefix' => (string)$config['prefix'],
            'persistent' => (bool)$config['persistent'],
            'persistent_id' => (string)$config['persistent_id'],
        ], JSON_UNESCAPED_SLASHES) ?: '');
    }

    private static function disable(string $cacheKey, Throwable $exception): void
    {
        unset(self::$connections[$cacheKey]);
        self::$retryAt[$cacheKey] = time() + max(1, Environment::int('SHARED_REDIS_RETRY_SECONDS', self::DEFAULT_RETRY_SECONDS));
        error_log('[shared_redis] ' . $exception->getMessage());
    }
}
