<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

use support\Db;

final class DatabaseColumnInspector
{
    /**
     * @var array<string, bool>
     */
    private static array $cache = [];

    public static function hasColumn(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        try {
            self::$cache[$cacheKey] = Db::connection()->getSchemaBuilder()->hasColumn($table, $column);
        } catch (\Throwable) {
            self::$cache[$cacheKey] = false;
        }

        return self::$cache[$cacheKey];
    }
}
