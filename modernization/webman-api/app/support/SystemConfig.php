<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

use support\Db;

class SystemConfig
{
    private static ?array $cache = null;
    private static int $cacheExpiresAt = 0;
    private const CACHE_KEY = 'system-config:all';

    public static function all(bool $refresh = false): array
    {
        $ttl = max(1, Environment::int('SYSTEM_CONFIG_CACHE_TTL', 5));
        $now = time();

        if (!$refresh && self::$cache !== null && self::$cacheExpiresAt > $now) {
            return self::$cache;
        }

        if (!$refresh) {
            $cached = HotPathStore::get(self::CACHE_KEY);
            if (is_array($cached)) {
                self::$cache = array_replace(self::defaults(), $cached);
                self::$cacheExpiresAt = $now + $ttl;

                return self::$cache;
            }
        }

        $config = self::defaults();
        $rows = Db::table('admin_config')
            ->select('config_name', 'config_value')
            ->get();

        foreach ($rows as $row) {
            $item = (array)$row;
            $name = trim((string)($item['config_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $config[$name] = (string)($item['config_value'] ?? '');
        }

        self::$cache = $config;
        self::$cacheExpiresAt = $now + $ttl;
        HotPathStore::put(self::CACHE_KEY, self::$cache, $ttl);

        return self::$cache;
    }

    public static function refresh(): array
    {
        return self::all(true);
    }

    public static function clearCache(): void
    {
        self::$cache = null;
        self::$cacheExpiresAt = 0;
        HotPathStore::forget(self::CACHE_KEY);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $config = self::all();

        return array_key_exists($key, $config) ? $config[$key] : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);

        return is_numeric($value) ? (int)$value : $default;
    }

    public static function float(string $key, float $default = 0): float
    {
        $value = self::get($key, $default);

        return is_numeric($value) ? (float)$value : $default;
    }

    private static function defaults(): array
    {
        return [
            'sitename' => 'AiPay',
            'title' => 'AiPay',
            'key' => 'AiPay,聚合支付,商户系统',
            'desc' => '安全、高效、稳定的聚合支付系统',
            'adminMail' => '',
            'email_switch' => '0',
            'diy_codeTemp' => '',
            'diy_loginTips' => '',
            'diy_regTips' => '',
            'diy_orderTips' => '',
            'diy_moneyTips' => '',
            'diy_loseTips' => '',
            'diy_vipTemp' => '',
            'tg_bind_tips' => '',
            'qq_login' => '0',
            'wechat_login' => '0',
            'smtp-host' => '',
            'smtp-port' => '',
            'smtp-user' => '',
            'smtp-pass' => '',
            'SmtpSecure' => '',
            'isSecurity' => '0',
            'isSecurityForce' => '0',
            'isSecurityLogin' => '0',
            'timeout' => '180',
            'min_orderprice' => '0',
            'max_orderprice' => '1000',
            'demopay_money' => '0.10',
            'shield_key' => '',
            'shield_tips' => '商品存在风控风险',
            'is_pay_money' => '1',
            'isDiy_orderNo' => '0',
            'diy_orderNo' => '',
            'is_reg' => '1',
            'merchant_login_drag_verify' => '1',
            'merchant_register_drag_verify' => '1',
            'merchant_retrieve_drag_verify' => '1',
            'theme_home' => 'default',
            'theme_pay' => 'default',
            'software_callback_sign_mode' => 'strict',
            'software_callback_sign_window' => '300',
        ];
    }
}
