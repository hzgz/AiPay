<?php

declare(strict_types=1);

namespace app\support;

use Throwable;

final class HotPathStore
{
    private const DEFAULT_REDIS_HOST = '127.0.0.1';
    private const DEFAULT_REDIS_PORT = 6379;
    private const DEFAULT_REDIS_DB = 0;
    private const DEFAULT_REDIS_TIMEOUT = 1.0;
    private const DEFAULT_REDIS_PREFIX = 'aipay:hot:';
    private const REDIS_RETRY_SECONDS = 10;
    private const REDIS_SCAN_COUNT = 200;
    private const REDIS_PURGE_BATCH_SIZE = 200;

    private static $redis = null;
    private static $managementRedis = null;
    private static int $redisRetryAt = 0;

    private function __construct()
    {
    }

    public static function fallbackDirectory(): string
    {
        return runtime_path('hot-path-store');
    }

    public static function fileFallbackEnabled(): bool
    {
        return Environment::bool('HOT_PATH_FILE_FALLBACK_ENABLE', false);
    }

    public static function redisTargetPath(): string
    {
        $prefix = self::redisPrefix();
        $location = sprintf(
            'redis://%s:%d/%d',
            self::redisHost(),
            self::redisPort(),
            self::redisDatabase()
        );

        return $prefix === ''
            ? $location
            : $location . ' [prefix ' . $prefix . '*]';
    }

    public static function inspectRedisPrefix(): array
    {
        $payload = [
            'enabled' => self::redisEnabled(),
            'available' => false,
            'prefix' => self::redisPrefix(),
            'relative_path' => self::redisTargetPath(),
            'key_count' => 0,
            'size_bytes' => 0,
            'errors' => [],
        ];

        if (!$payload['enabled']) {
            return $payload;
        }

        $redis = self::managementRedis();
        if (!$redis instanceof \Redis) {
            return $payload;
        }

        try {
            [$keys, $sizeBytes] = self::scanPrefixedKeys($redis, true);

            $payload['available'] = true;
            $payload['key_count'] = count($keys);
            $payload['size_bytes'] = $sizeBytes;

            return $payload;
        } catch (Throwable $exception) {
            self::disableRedis($exception);
            $payload['errors'][] = 'redis prefix inspection failed';

            return $payload;
        }
    }

    public static function clearRedisPrefix(): array
    {
        $payload = [
            'enabled' => self::redisEnabled(),
            'available' => false,
            'removed_key_count' => 0,
            'released_size_bytes' => 0,
            'errors' => [],
        ];

        if (!$payload['enabled']) {
            return $payload;
        }

        $redis = self::managementRedis();
        if (!$redis instanceof \Redis) {
            return $payload;
        }

        try {
            [$keys, $sizeBytes] = self::scanPrefixedKeys($redis, true);
            $payload['available'] = true;
            $payload['released_size_bytes'] = $sizeBytes;

            if ($keys === []) {
                return $payload;
            }

            $payload['removed_key_count'] = self::deleteKeys($redis, $keys);

            return $payload;
        } catch (Throwable $exception) {
            self::disableRedis($exception);
            $payload['errors'][] = 'redis prefix cleanup failed';

            return $payload;
        }
    }

    public static function get(string $key): mixed
    {
        if ($key === '') {
            return null;
        }

        $redis = self::redis();
        if ($redis instanceof \Redis) {
            try {
                $payload = $redis->get($key);

                return is_string($payload) ? self::decodeValue($payload) : null;
            } catch (Throwable $exception) {
                self::disableRedis($exception);
            }
        }

        return self::fileFallbackEnabled() ? self::fileGet($key) : null;
    }

    public static function put(string $key, mixed $value, int $ttl): void
    {
        if ($key === '') {
            return;
        }

        $ttl = max(1, $ttl);
        $redis = self::redis();
        if ($redis instanceof \Redis) {
            try {
                $payload = self::encodeValue($value);
                if ($payload !== null) {
                    $redis->setEx($key, $ttl, $payload);
                    self::fileForget($key);
                    return;
                }
            } catch (Throwable $exception) {
                self::disableRedis($exception);
            }
        }

        if (self::fileFallbackEnabled()) {
            self::filePut($key, $value, $ttl);
        }
    }

    public static function forget(string $key): void
    {
        if ($key === '') {
            return;
        }

        $redis = self::redis();
        if ($redis instanceof \Redis) {
            try {
                $redis->del($key);
            } catch (Throwable $exception) {
                self::disableRedis($exception);
            }
        }

        if (self::fileFallbackEnabled()) {
            self::fileForget($key);
        }
    }

    public static function increment(string $key, int $ttl, int $amount = 1): int
    {
        if ($key === '') {
            return 0;
        }

        $ttl = max(1, $ttl);
        $amount = max(1, $amount);

        $redis = self::redis();
        if ($redis instanceof \Redis) {
            try {
                $count = $redis->incrBy($key, $amount);
                $currentTtl = (int)$redis->ttl($key);
                if ($count === $amount || $currentTtl < 0) {
                    $redis->expire($key, $ttl);
                }

                self::fileForget($key);

                return is_numeric($count) ? (int)$count : $amount;
            } catch (Throwable $exception) {
                self::disableRedis($exception);
            }
        }

        return self::fileFallbackEnabled() ? self::fileIncrement($key, $ttl, $amount) : $amount;
    }

    public static function ttl(string $key): int
    {
        if ($key === '') {
            return -2;
        }

        $redis = self::redis();
        if ($redis instanceof \Redis) {
            try {
                $ttl = $redis->ttl($key);
                if (is_numeric($ttl)) {
                    return (int)$ttl;
                }
            } catch (Throwable $exception) {
                self::disableRedis($exception);
            }
        }

        return self::fileFallbackEnabled() ? self::fileTtl($key) : -2;
    }

    private static function redis(): ?\Redis
    {
        if (self::$redis instanceof \Redis) {
            return self::$redis;
        }

        if (time() < self::$redisRetryAt) {
            return null;
        }

        if (!self::redisEnabled()) {
            return null;
        }

        try {
            self::$redis = self::connectRedis(true);
            self::$redisRetryAt = 0;

            return self::$redis;
        } catch (Throwable $exception) {
            self::disableRedis($exception);

            return null;
        }
    }

    private static function managementRedis(): ?\Redis
    {
        if (self::$managementRedis instanceof \Redis) {
            return self::$managementRedis;
        }

        if (time() < self::$redisRetryAt) {
            return null;
        }

        if (!self::redisEnabled()) {
            return null;
        }

        try {
            self::$managementRedis = self::connectRedis(false);
            self::$redisRetryAt = 0;

            return self::$managementRedis;
        } catch (Throwable $exception) {
            self::disableRedis($exception);

            return null;
        }
    }

    private static function connectRedis(bool $withPrefix): \Redis
    {
        $client = new \Redis();
        $timeout = self::redisTimeout();
        $host = self::redisHost();
        $port = self::redisPort();
        $persistent = Environment::bool('HOT_PATH_REDIS_PERSISTENT', true);

        $connected = $persistent
            ? @$client->pconnect($host, $port, $timeout, $withPrefix ? 'aipay-hot-path' : 'aipay-hot-path-admin')
            : @$client->connect($host, $port, $timeout);

        if (!$connected) {
            throw new \RuntimeException('redis connection failed');
        }

        $password = Environment::string('HOT_PATH_REDIS_PASSWORD', '');
        if ($password !== '') {
            $client->auth($password);
        }

        $client->select(self::redisDatabase());

        if ($withPrefix) {
            $prefix = self::redisPrefix();
            if ($prefix !== '') {
                $client->setOption(\Redis::OPT_PREFIX, $prefix);
            }
        }

        return $client;
    }

    private static function disableRedis(Throwable $exception): void
    {
        self::$redis = null;
        self::$managementRedis = null;
        self::$redisRetryAt = time() + self::REDIS_RETRY_SECONDS;
        error_log('[hot_path_store] redis disabled temporarily: ' . $exception->getMessage());
    }

    private static function scanPrefixedKeys(\Redis $redis, bool $includeSize): array
    {
        $pattern = self::redisScanPattern();
        $iterator = null;
        $keys = [];
        $sizeBytes = 0;

        do {
            $batch = $redis->scan($iterator, $pattern, self::REDIS_SCAN_COUNT);
            if ($batch === false) {
                continue;
            }

            foreach ($batch as $key) {
                if (!is_string($key) || $key === '') {
                    continue;
                }

                if (!self::keyMatchesPrefix($key)) {
                    continue;
                }

                $keys[] = $key;
                if ($includeSize) {
                    $sizeBytes += self::keyMemoryUsage($redis, $key);
                }
            }
        } while ($iterator !== 0);

        return [$keys, $sizeBytes];
    }

    private static function deleteKeys(\Redis $redis, array $keys): int
    {
        $removed = 0;

        foreach (array_chunk($keys, self::REDIS_PURGE_BATCH_SIZE) as $chunk) {
            if ($chunk === []) {
                continue;
            }

            try {
                $result = $redis->rawCommand('UNLINK', ...$chunk);
            } catch (Throwable) {
                $result = false;
            }

            if (!is_numeric($result)) {
                $result = $redis->rawCommand('DEL', ...$chunk);
            }

            $removed += is_numeric($result) ? (int)$result : count($chunk);
        }

        return $removed;
    }

    private static function keyMemoryUsage(\Redis $redis, string $key): int
    {
        try {
            $usage = $redis->rawCommand('MEMORY', 'USAGE', $key);
            if (is_numeric($usage)) {
                return max(0, (int)$usage);
            }
        } catch (Throwable) {
        }

        try {
            $value = $redis->get($key);
            if (is_string($value)) {
                return strlen($key) + strlen($value);
            }
        } catch (Throwable) {
        }

        return strlen($key);
    }

    private static function redisEnabled(): bool
    {
        return Environment::bool('HOT_PATH_REDIS_ENABLE', true) && class_exists(\Redis::class);
    }

    private static function redisHost(): string
    {
        return Environment::string('HOT_PATH_REDIS_HOST', self::DEFAULT_REDIS_HOST);
    }

    private static function redisPort(): int
    {
        return max(1, Environment::int('HOT_PATH_REDIS_PORT', self::DEFAULT_REDIS_PORT));
    }

    private static function redisDatabase(): int
    {
        return max(0, Environment::int('HOT_PATH_REDIS_DB', self::DEFAULT_REDIS_DB));
    }

    private static function redisTimeout(): float
    {
        return max(0.1, Environment::float('HOT_PATH_REDIS_TIMEOUT', self::DEFAULT_REDIS_TIMEOUT));
    }

    private static function redisPrefix(): string
    {
        return trim(Environment::string('HOT_PATH_REDIS_PREFIX', self::DEFAULT_REDIS_PREFIX));
    }

    private static function redisScanPattern(): string
    {
        $prefix = self::redisPrefix();

        return $prefix === '' ? '*' : $prefix . '*';
    }

    private static function keyMatchesPrefix(string $key): bool
    {
        $prefix = self::redisPrefix();

        return $prefix === '' || str_starts_with($key, $prefix);
    }

    private static function encodeValue(mixed $value): ?string
    {
        $payload = json_encode(
            ['value' => $value],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return is_string($payload) ? $payload : null;
    }

    private static function decodeValue(string $payload): mixed
    {
        $decoded = json_decode($payload, true);
        if (!is_array($decoded) || !array_key_exists('value', $decoded)) {
            return null;
        }

        return $decoded['value'];
    }

    private static function fileGet(string $key): mixed
    {
        $record = self::readFileRecord(self::filePath($key));
        if (!is_array($record)) {
            return null;
        }

        $expiresAt = (int)($record['expires_at'] ?? 0);
        if ($expiresAt <= time()) {
            self::fileForget($key);

            return null;
        }

        return $record['value'] ?? null;
    }

    private static function filePut(string $key, mixed $value, int $ttl): void
    {
        $record = [
            'expires_at' => time() + max(1, $ttl),
            'value' => $value,
        ];

        self::writeFileRecord(self::filePath($key), $record);
    }

    private static function fileIncrement(string $key, int $ttl, int $amount): int
    {
        $path = self::filePath($key);
        self::ensureDirectory(dirname($path));

        $handle = fopen($path, 'c+b');
        if (!is_resource($handle)) {
            return $amount;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return $amount;
            }

            $contents = stream_get_contents($handle);
            $record = self::decodeFileRecord(is_string($contents) ? $contents : '');
            $expiresAt = (int)($record['expires_at'] ?? 0);
            $value = $expiresAt > time() ? (int)($record['value'] ?? 0) : 0;
            $expiresAt = $expiresAt > time() ? $expiresAt : (time() + $ttl);
            $value += $amount;

            $payload = json_encode([
                'expires_at' => $expiresAt,
                'value' => $value,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (is_string($payload)) {
                rewind($handle);
                ftruncate($handle, 0);
                fwrite($handle, $payload);
                fflush($handle);
            }

            flock($handle, LOCK_UN);

            return $value;
        } finally {
            fclose($handle);
        }
    }

    private static function fileTtl(string $key): int
    {
        $record = self::readFileRecord(self::filePath($key));
        if (!is_array($record)) {
            return -2;
        }

        $ttl = (int)($record['expires_at'] ?? 0) - time();
        if ($ttl <= 0) {
            self::fileForget($key);

            return -2;
        }

        return $ttl;
    }

    private static function fileForget(string $key): void
    {
        $path = self::filePath($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function readFileRecord(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $handle = fopen($path, 'rb');
        if (!is_resource($handle)) {
            return null;
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                return null;
            }

            $contents = stream_get_contents($handle);
            flock($handle, LOCK_UN);

            return self::decodeFileRecord(is_string($contents) ? $contents : '');
        } finally {
            fclose($handle);
        }
    }

    private static function writeFileRecord(string $path, array $record): void
    {
        self::ensureDirectory(dirname($path));
        $payload = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            return;
        }

        $handle = fopen($path, 'c+b');
        if (!is_resource($handle)) {
            return;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return;
            }

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, $payload);
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    private static function decodeFileRecord(string $payload): ?array
    {
        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }

    private static function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    private static function filePath(string $key): string
    {
        return self::fallbackDirectory() . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    }
}
