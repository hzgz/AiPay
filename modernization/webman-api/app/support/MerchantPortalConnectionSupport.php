<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\support;

class MerchantPortalConnectionSupport
{
    public static function payload(
        array $merchant,
        array $config,
        callable $maskIdentifier,
        callable $nullableString
    ): array {
        $quickLogins = MerchantPortalAccountSupport::quickLoginBindings(
            $merchant,
            $config,
            $maskIdentifier,
            $nullableString
        );
        $contactBindings = self::contactBindings($merchant, $config, $maskIdentifier);
        $wxPusherEnrollment = self::wxPusherEnrollmentPayload($merchant, $config, $maskIdentifier);

        $boundQuickLoginCount = 0;
        foreach ($quickLogins as $item) {
            if (!empty($item['bound'])) {
                $boundQuickLoginCount++;
            }
        }

        $configuredContactCount = 0;
        foreach ($contactBindings as $item) {
            if (!empty($item['configured'])) {
                $configuredContactCount++;
            }
        }

        return [
            'merchant_id' => (int)($merchant['id'] ?? 0),
            'merchant_username' => trim((string)($merchant['username'] ?? '')),
            'quick_logins' => $quickLogins,
            'contact_bindings' => $contactBindings,
            'wxpusher_enrollment' => $wxPusherEnrollment,
            'summary' => [
                'quick_login_count' => count($quickLogins),
                'bound_quick_login_count' => $boundQuickLoginCount,
                'contact_binding_count' => count($contactBindings),
                'configured_contact_count' => $configuredContactCount,
            ],
            'write_actions' => [
                'bind' => true,
                'unbind' => true,
                'wxpusher_qrcode' => !empty($wxPusherEnrollment['write_allowed']),
                'verify_code' => true,
                'wxpusher_uid_status' => true,
                'save_wxpusher_uid' => true,
                'save_tg_chat_id' => true,
            ],
            'migration_guard' => [
                'read_only' => false,
                'blocked_actions' => !empty($wxPusherEnrollment['write_allowed'])
                    ? []
                    : ['wxpusher_qrcode'],
            ],
        ];
    }

    public static function wxPusherEnrollmentPayload(array $merchant, array $config, callable $maskIdentifier): array
    {
        $enabled = trim((string)($config['wxpusher_switch'] ?? '0')) === '1';
        $tokenConfigured = self::wxPusherAppToken($config) !== '';
        $currentUid = trim((string)($merchant['wxpusher_uid'] ?? ''));
        $writeAllowed = $enabled && $tokenConfigured;

        $writeMessage = '扫码关注后会自动写入当前商户的 WxPusher UID，也支持手动填写 UID 作为兜底。';
        if (!$enabled) {
            $writeMessage = '管理员尚未开启 WxPusher 通知开关，当前仅建议保留已有 UID 作为预配置。';
        } elseif (!$tokenConfigured) {
            $writeMessage = '请先在系统配置中填写 WxPusher 应用令牌，然后才能生成扫码绑定二维码。';
        }

        return [
            'enabled' => $enabled,
            'token_configured' => $tokenConfigured,
            'bound' => $currentUid !== '',
            'uid_masked' => $maskIdentifier($currentUid),
            'manual_save_allowed' => true,
            'write_allowed' => $writeAllowed,
            'write_message' => $writeMessage,
            'expires_seconds' => 1800,
            'callback_entry' => '/Notify/wxpusher',
        ];
    }

    public static function wxPusherAppToken(array $config): string
    {
        return trim((string)($config['wxpusher_appToken'] ?? ''));
    }

    public static function createWxPusherQrCode(
        int $merchantId,
        string $appToken,
        callable $nullableString,
        callable $buildQrCodeUrl,
        int $validSeconds = 1800
    ): array {
        if ($merchantId <= 0) {
            throw new \RuntimeException('商户信息无效，无法生成 WxPusher 绑定二维码。');
        }

        if ($appToken === '') {
            throw new \RuntimeException('请先配置 WxPusher 应用令牌。');
        }

        $payload = json_encode([
            'appToken' => $appToken,
            'extra' => (string)$merchantId,
            'validTime' => max(60, $validSeconds),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($payload) || $payload === '') {
            throw new \RuntimeException('生成 WxPusher 请求参数失败。');
        }

        $ch = curl_init('https://wxpusher.zjiecode.com/api/fun/create/qrcode');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);

        $body = curl_exec($ch);
        $error = $body === false ? curl_error($ch) : '';
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException(
                $error !== ''
                    ? 'WxPusher 请求失败，请检查网络或应用令牌配置。'
                    : 'WxPusher 请求失败，请稍后重试。'
            );
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException('WxPusher 服务暂时不可用，请稍后重试。');
        }

        $decoded = json_decode((string)$body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('WxPusher 返回了无法识别的数据。');
        }

        if (empty($decoded['success']) || !is_array($decoded['data'] ?? null)) {
            $message = trim((string)($decoded['msg'] ?? ''));
            throw new \RuntimeException(
                $message !== '' && preg_match('/[\x{4e00}-\x{9fff}]/u', $message)
                    ? $message
                    : 'WxPusher 二维码生成失败，请检查应用令牌配置。'
            );
        }

        $data = $decoded['data'];
        $shortUrl = $nullableString($data['shortUrl'] ?? $data['short_url'] ?? null);
        $url = $nullableString($data['url'] ?? null);
        $scanContent = $url ?? $shortUrl;

        if ($scanContent === null) {
            throw new \RuntimeException('WxPusher 未返回可用的二维码内容。');
        }

        return [
            'shortUrl' => $shortUrl,
            'short_url' => $shortUrl,
            'url' => $url,
            'extra' => $nullableString($data['extra'] ?? null) ?? (string)$merchantId,
            'expires' => $nullableString($data['expires'] ?? null),
            'expires_at' => $nullableString($data['expires'] ?? null),
            'qrcode_url' => $buildQrCodeUrl($scanContent),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function contactBindings(array $merchant, array $config, callable $maskIdentifier): array
    {
        $definitions = [
            ['id' => 'email', 'label' => '邮箱', 'available' => trim((string)($config['email_switch'] ?? '0')) === '1'],
            ['id' => 'mobile', 'label' => '手机号', 'available' => trim((string)($config['code_switch'] ?? '0')) === '1'],
            ['id' => 'wxpusher_uid', 'label' => '微信推送', 'available' => trim((string)($config['wxpusher_switch'] ?? '0')) === '1'],
            ['id' => 'tg_chat_id', 'label' => 'Telegram 通知', 'available' => trim((string)($config['tg_switch'] ?? '0')) === '1'],
        ];

        $bindings = [];
        foreach ($definitions as $definition) {
            $id = (string)$definition['id'];
            $value = trim((string)($merchant[$id] ?? ''));
            $useMaskedValue = in_array($id, ['wxpusher_uid', 'tg_chat_id'], true);

            $bindings[] = [
                'id' => $id,
                'label' => (string)$definition['label'],
                'available' => (bool)$definition['available'],
                'status_label' => (bool)$definition['available'] ? '可用' : '未开启',
                'status_type' => (bool)$definition['available'] ? 'success' : 'warning',
                'configured' => $value !== '',
                'configured_label' => $value !== '' ? '已配置' : '未配置',
                'configured_type' => $value !== '' ? 'success' : 'info',
                'value' => $value !== '' && !$useMaskedValue ? $value : null,
                'value_present' => $value !== '',
                'value_display' => $value === ''
                    ? '未配置'
                    : ($useMaskedValue ? ($maskIdentifier($value) ?? '已配置') : $value),
                'save_allowed' => in_array($id, ['wxpusher_uid', 'tg_chat_id'], true),
                'bind_allowed' => in_array($id, ['email', 'mobile'], true) && (bool)$definition['available'] && $value === '',
                'verify_code_allowed' => in_array($id, ['email', 'mobile'], true) && (bool)$definition['available'],
                'unbind_allowed' => $value !== '',
                'status_check_allowed' => $id === 'wxpusher_uid',
                'write_message' => in_array($id, ['wxpusher_uid', 'tg_chat_id'], true)
                    ? '微信推送与 Telegram 通知可直接在当前商户中心保存。'
                    : '邮箱和手机号验证码绑定流程可直接在当前商户中心处理。',
            ];
        }

        return $bindings;
    }
}
