<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\support;

class MerchantPortalSecuritySupport
{
    public static function payload(
        array $merchant,
        array $config,
        array $logs,
        ?string $pendingGoogleSecret,
        array $cancellation,
        callable $maskSecret,
        callable $maskIdentifier,
        callable $nullableString
    ): array {
        $merchantId = (int)($merchant['id'] ?? 0);
        $googleKey = trim((string)($merchant['googlekey'] ?? ''));
        $setupSecret = $googleKey === '' ? trim((string)$pendingGoogleSecret) : '';
        $securityEnabled = trim((string)($config['isSecurity'] ?? '0')) === '1';
        $securityForceEnabled = trim((string)($config['isSecurityForce'] ?? '0')) === '1';
        $securityLoginEnabled = trim((string)($config['isSecurityLogin'] ?? '0')) === '1';
        $realNameFeatureEnabled = trim((string)($config['isRealName'] ?? '0')) === '1';
        $realNameVerified = (int)($merchant['is_realName'] ?? 0) === 1;

        return [
            'merchant_id' => $merchantId,
            'merchant_username' => trim((string)($merchant['username'] ?? '')),
            'security_center' => [
                'enabled' => $securityEnabled,
                'force_bind' => $securityForceEnabled,
                'login_verification_required' => $securityLoginEnabled,
                'provider_name' => $nullableString($config['securityName'] ?? null) ?? '谷歌验证器',
                'provider_icon' => $nullableString($config['securityIcon'] ?? null),
                'bind_tips' => $nullableString($config['securityBindTips'] ?? null),
                'popup_title' => $nullableString($config['securityPopTitle'] ?? null),
                'popup_content' => $nullableString($config['securityPopContent'] ?? null),
            ],
            'password' => [
                'update_allowed' => true,
                'minimum_length' => 6,
                'legacy_route' => '/api/merchant/security/password',
                'write_message' => '商户密码修改后立即生效，保存后需要重新登录。',
            ],
            'google_auth' => self::googleAuthPayload(
                $merchant,
                $config,
                $maskSecret,
                $setupSecret
            ),
            'real_name' => [
                'feature_enabled' => $realNameFeatureEnabled,
                'verified' => $realNameVerified,
                'status_label' => !$realNameFeatureEnabled
                    ? '未开启'
                    : ($realNameVerified ? '已认证' : '未认证'),
                'status_type' => !$realNameFeatureEnabled
                    ? 'info'
                    : ($realNameVerified ? 'success' : 'warning'),
                'id_card_masked' => $realNameVerified ? $maskIdentifier((string)($merchant['idCard'] ?? '')) : null,
                'write_allowed' => $realNameFeatureEnabled && !$realNameVerified,
                'write_message' => !$realNameFeatureEnabled
                    ? '系统尚未开启实名认证功能。'
                    : ($realNameVerified
                        ? '当前商户已完成实名认证。'
                        : '实名认证发起流程已接入当前商户中心。'),
            ],
            'account_cancellation' => $cancellation,
            'recent_logs' => array_slice((array)($logs['records'] ?? []), 0, 5),
            'log_summary' => (array)($logs['summary'] ?? []),
            'write_actions' => [
                'password_update' => true,
                'google_verify' => false,
                'google_bind' => $googleKey === '',
                'google_unbind' => $googleKey !== '',
                'account_cancellation' => (bool)($cancellation['can_submit'] ?? false),
                'api_key_reset' => true,
                'appkey_reset' => true,
            ],
            'migration_guard' => [
                'read_only' => false,
                'blocked_actions' => array_values(array_filter([
                    'google_verify',
                    ($cancellation['can_submit'] ?? false) ? null : 'account_cancellation',
                ])),
            ],
        ];
    }

    public static function googleAuthPayload(
        array $merchant,
        array $config,
        callable $maskSecret,
        ?string $pendingSecret = null
    ): array {
        $googleKey = trim((string)($merchant['googlekey'] ?? ''));
        $setupSecret = $googleKey === '' ? trim((string)$pendingSecret) : '';
        $setupPending = $setupSecret !== '';
        $setupAccount = $setupPending ? self::googleAuthAccountLabel($merchant) : null;
        $setupIssuer = $setupPending ? self::googleAuthIssuer($config) : null;
        $google = $setupPending ? new GoogleAuthenticator() : null;
        $securityEnabled = trim((string)($config['isSecurity'] ?? '0')) === '1';
        $securityLoginEnabled = trim((string)($config['isSecurityLogin'] ?? '0')) === '1';

        return [
            'bound' => $googleKey !== '',
            'status_label' => $googleKey !== '' ? '已绑定' : '未绑定',
            'status_type' => $googleKey !== '' ? 'success' : 'warning',
            'secret_masked' => $maskSecret($googleKey),
            'verification_page' => '/merchant/security',
            'verification_required_at_login' => $securityEnabled && $securityLoginEnabled,
            'verification_allowed' => false,
            'bind_allowed' => $googleKey === '',
            'unbind_allowed' => $googleKey !== '',
            'setup_pending' => $setupPending,
            'setup_account' => $setupAccount,
            'setup_issuer' => $setupIssuer,
            'setup_secret' => $setupPending ? $setupSecret : null,
            'setup_secret_masked' => $setupPending ? $maskSecret($setupSecret) : null,
            'setup_qrcode_url' => $setupPending && $google !== null
                ? $google->getQRCodeGoogleUrl((string)$setupAccount, $setupSecret, (string)$setupIssuer)
                : null,
            'otp_auth_url' => $setupPending && $google !== null
                ? $google->buildOtpAuthUrl((string)$setupAccount, $setupSecret, (string)$setupIssuer)
                : null,
            'write_message' => $googleKey !== ''
                ? '谷歌验证解绑功能已接入当前系统，是否在登录时校验由系统安全开关决定。'
                : '谷歌验证绑定功能已接入当前系统，是否在登录时校验由系统安全开关决定。',
        ];
    }

    private static function googleAuthIssuer(array $config): string
    {
        $candidates = [
            $config['title'] ?? null,
            $config['siteName'] ?? null,
            $config['webName'] ?? null,
            $config['sitename'] ?? null,
            'AiPay 商户',
        ];

        foreach ($candidates as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                return $value;
            }
        }

        return 'AiPay 商户';
    }

    private static function googleAuthAccountLabel(array $merchant): string
    {
        $username = trim((string)($merchant['username'] ?? ''));
        if ($username !== '') {
            return $username;
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        return $merchantId > 0 ? 'merchant-' . $merchantId : 'merchant-account';
    }
}
