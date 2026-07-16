<?php

namespace app\support;

use support\Db;

class SystemConfig
{
    private static ?array $cache = null;
    private static ?string $signature = null;

    public static function all(bool $refresh = false): array
    {
        if (!$refresh && self::$cache !== null) {
            $currentSignature = self::loadSignature();
            if ($currentSignature !== null && $currentSignature === self::$signature) {
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
        self::$signature = self::loadSignature();

        return self::$cache;
    }

    public static function refresh(): array
    {
        return self::all(true);
    }

    public static function clearCache(): void
    {
        self::$cache = null;
        self::$signature = null;
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
            'shield_key' => '',
            'shield_tips' => '商品存在风控风险',
            'is_pay_money' => '1',
            'isDiy_orderNo' => '0',
            'diy_orderNo' => '',
            'is_reg' => '1',
            'software_callback_sign_mode' => 'strict',
            'software_callback_sign_window' => '300',
        ];
    }

    private static function loadSignature(): ?string
    {
        $row = Db::table('admin_config')
            ->selectRaw(
                "COUNT(*) as row_count, COALESCE(MAX(id), 0) as max_id, "
                . "COALESCE(SUM(CRC32(CONCAT_WS(':', config_name, config_value))), 0) as checksum"
            )
            ->first();

        if (!$row) {
            return null;
        }

        $item = (array)$row;

        return implode(':', [
            (string)($item['row_count'] ?? '0'),
            (string)($item['max_id'] ?? '0'),
            (string)($item['checksum'] ?? '0'),
        ]);
    }
}
