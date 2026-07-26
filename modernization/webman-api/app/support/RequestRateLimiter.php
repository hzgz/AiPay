<?php

declare(strict_types=1);

namespace app\support;

final class RequestRateLimiter
{
    private function __construct()
    {
    }

    public static function attempt(string $key, int $limit, int $windowSeconds): array
    {
        if ($key === '' || $limit <= 0 || $windowSeconds <= 0) {
            return [
                'allowed' => true,
                'attempts' => 0,
                'remaining' => 0,
                'retry_after' => 0,
            ];
        }

        $storeKey = 'rate-limit:' . hash('sha256', $key);
        $attempts = HotPathStore::increment($storeKey, $windowSeconds);
        $retryAfter = HotPathStore::ttl($storeKey);

        return [
            'allowed' => $attempts <= $limit,
            'attempts' => $attempts,
            'remaining' => max(0, $limit - min($attempts, $limit)),
            'retry_after' => max(1, $retryAfter > 0 ? $retryAfter : $windowSeconds),
        ];
    }
}
