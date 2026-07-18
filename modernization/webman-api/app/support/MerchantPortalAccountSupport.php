<?php

declare(strict_types=1);

namespace app\support;

use support\Db;

class MerchantPortalAccountSupport
{
    public static function apiPayload(
        array $merchant,
        array $basic,
        string $origin,
        array $gatewayLines,
        callable $maskSecret,
        callable $nullableString,
        callable $timeoutMethodLabel
    ): array {
        $merchantId = (int)($merchant['id'] ?? 0);
        $signKey = trim((string)($merchant['user_key'] ?? ''));
        $appkey = trim((string)($basic['appkey'] ?? ''));
        $timeoutMethod = (int)($basic['timeout_method'] ?? 0);

        return [
            'merchant_id' => $merchantId,
            'merchant_username' => trim((string)($merchant['username'] ?? '')),
            'gateway_lines' => $gatewayLines,
            'default_gateway_url' => rtrim((string)($gatewayLines[0]['url'] ?? ($origin . '/')), '/'),
            'endpoints' => [
                'submit' => [
                    'method' => 'GET/POST',
                    'path' => '/submit.php',
                    'url' => $origin . '/submit.php',
                    'description' => '易支付网关的浏览器表单下单入口。',
                ],
                'mapi' => [
                    'method' => 'GET/POST',
                    'path' => '/mapi.php',
                    'url' => $origin . '/mapi.php',
                    'description' => '易支付网关的接口下单入口。',
                ],
                'notify' => [
                    'method' => 'GET/POST',
                    'path' => '/Notify/epay_notifyzj',
                    'url' => $origin . '/Notify/epay_notifyzj',
                    'description' => '上游支付结果异步回调入口。',
                ],
                'return' => [
                    'method' => 'GET',
                    'path' => '/Notify/epay_returnzj',
                    'url' => $origin . '/Notify/epay_returnzj',
                    'description' => '上游支付结果同步跳转入口。',
                ],
            ],
            'sign_key_configured' => $signKey !== '',
            'sign_key_masked' => $maskSecret($signKey),
            'sign_key_length' => strlen($signKey),
            'appkey_configured' => $appkey !== '',
            'appkey_masked' => $maskSecret($appkey),
            'appkey_length' => strlen($appkey),
            'timeout_time' => (int)($basic['timeout_time'] ?? 0),
            'timeout_url' => $nullableString($basic['timeout_url'] ?? null) ?? '/',
            'timeout_method' => $timeoutMethod,
            'timeout_method_label' => $timeoutMethodLabel($timeoutMethod),
            'signing' => [
                'algorithm' => 'MD5',
                'secret_source' => BusinessTable::column('user', 'user_key'),
                'raw_secret_exposed' => false,
                'note' => '支付请求签名请使用当前商户已配置的签名密钥，原始密钥默认保持脱敏。',
            ],
            'write_actions' => [
                'key_reset' => true,
                'sign_key_reset' => true,
                'appkey_reset' => true,
                'qrcode' => true,
                'secret_export' => false,
            ],
            'migration_guard' => [
                'read_only' => false,
                'blocked_actions' => ['raw_secret_export'],
            ],
        ];
    }

    public static function basic(int $merchantId): array
    {
        $row = Db::table(BusinessTable::userBasic())
            ->select('user_id', 'appkey', 'timeout_url', 'timeout_time', 'timeout_method')
            ->where('user_id', $merchantId)
            ->first();

        if (!$row) {
            return self::basicDefaults($merchantId);
        }

        return array_merge(self::basicDefaults($merchantId), (array)$row);
    }

    public static function ensureBasicRecord(int $merchantId, string $appkey): void
    {
        if (Db::table(BusinessTable::userBasic())->where('user_id', $merchantId)->exists()) {
            return;
        }

        Db::table(BusinessTable::userBasic())->insert([
            'user_id' => $merchantId,
            'timeout_method' => 2,
            'timeout_url' => '/',
            'timeout_time' => '180',
            'loginfailure' => 0,
            'appkey' => $appkey,
            'order_tips' => 'close',
            'is_money_tips' => 'close',
            'money_tips' => '0',
            'is_rate' => 0,
            'callback_hiddenName' => 0,
        ]);
    }

    public static function notificationBasic(int $merchantId, string $defaultVoiceTips): array
    {
        $defaults = [
            'user_id' => $merchantId,
            'order_tips' => 'close',
            'lose_tips' => 'close',
            'login_tips' => 'close',
            'is_money_tips' => 'close',
            'money_tips' => '0',
            'console_notity' => null,
            'is_voice_tips' => 0,
            'voice_tips' => $defaultVoiceTips,
        ];

        $row = Db::table(BusinessTable::userBasic())
            ->select(
                'user_id',
                'order_tips',
                'lose_tips',
                'login_tips',
                'is_money_tips',
                'money_tips',
                'console_notity',
                'is_voice_tips',
                'voice_tips'
            )
            ->where('user_id', $merchantId)
            ->first();

        if (!$row) {
            return $defaults;
        }

        $basic = array_merge($defaults, (array)$row);
        $basic['voice_tips'] = LegacyMojibakeGuard::normalizeVoiceTipsTemplate(
            $basic['voice_tips'] ?? null,
            $defaultVoiceTips
        );

        return $basic;
    }

    public static function quickLoginBindings(
        array $merchant,
        array $config,
        callable $maskIdentifier,
        callable $nullableString
    ): array {
        $configIds = [];
        foreach (self::quickLoginDefinitions() as $definition) {
            $configId = (int)($config[$definition['config_key']] ?? 0);
            if ($configId > 0) {
                $configIds[] = $configId;
            }
        }

        $quickLoginRows = [];
        if ($configIds !== []) {
            foreach (
                Db::table(BusinessTable::quickLogin())
                    ->select('id', 'type', 'status', 'name', 'url', 'appid', 'appkey', 'create_time')
                    ->whereIn('id', array_values(array_unique($configIds)))
                    ->get()
                    ->toArray() as $row
            ) {
                $item = (array)$row;
                $quickLoginRows[(int)($item['id'] ?? 0)] = $item;
            }
        }

        $bindings = [];
        foreach (self::quickLoginDefinitions() as $definition) {
            $configId = (int)($config[$definition['config_key']] ?? 0);
            $record = $configId > 0 ? ($quickLoginRows[$configId] ?? null) : null;
            $status = (int)($record['status'] ?? 0);
            $sid = trim((string)($merchant[$definition['sid_field']] ?? ''));
            $bound = (int)($merchant[$definition['bind_flag']] ?? 0) === 1 || $sid !== '';

            $bindings[] = [
                'id' => $definition['id'],
                'label' => $definition['label'],
                'config_id' => $configId > 0 ? $configId : null,
                'available' => $configId > 0 && is_array($record) && $status === 1,
                'status_label' => $configId > 0 && is_array($record) && $status === 1 ? '可用' : '未开启',
                'status_type' => $configId > 0 && is_array($record) && $status === 1 ? 'success' : 'warning',
                'bound' => $bound,
                'bound_label' => $bound ? '已绑定' : '未绑定',
                'bound_type' => $bound ? 'success' : 'info',
                'identifier_present' => $sid !== '',
                'identifier_masked' => $maskIdentifier($sid),
                'config_name' => trim((string)($record['name'] ?? $definition['label'])),
                'config_type' => trim((string)($record['type'] ?? '')),
                'credential_ready' => trim((string)($record['appid'] ?? '')) !== ''
                    && trim((string)($record['appkey'] ?? '')) !== '',
                'url' => $nullableString($record['url'] ?? null),
                'callback_entry' => $definition['id'] === 'qq' ? '/User/qqlogin' : '/User/OAuthAccountLogin?type=wx',
                'unbind_allowed' => $bound,
                'write_message' => '快捷登录解绑已生效；当前页面仅展示已接入状态，不再提供新的授权绑定入口。',
            ];
        }

        return $bindings;
    }

    private static function basicDefaults(int $merchantId): array
    {
        return [
            'user_id' => $merchantId,
            'appkey' => '',
            'timeout_url' => '/',
            'timeout_time' => '180',
            'timeout_method' => 2,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function quickLoginDefinitions(): array
    {
        return [
            [
                'id' => 'qq',
                'label' => 'QQ 登录',
                'config_key' => 'qq_login',
                'bind_flag' => 'is_bindqq',
                'sid_field' => 'qq_sid',
            ],
            [
                'id' => 'wx',
                'label' => '微信登录',
                'config_key' => 'wechat_login',
                'bind_flag' => 'is_bindwx',
                'sid_field' => 'wx_sid',
            ],
        ];
    }
}
