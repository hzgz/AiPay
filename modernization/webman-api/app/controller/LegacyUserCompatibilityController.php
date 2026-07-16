<?php

declare(strict_types=1);

namespace app\controller;

use app\support\ApiResponse;
use app\support\FrontendUrlBuilder;
use app\support\MerchantFrontSession;
use app\support\SystemConfig;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class LegacyUserCompatibilityController
{
    public function register(Request $request): Response
    {
        if (strtoupper($request->method()) !== 'GET') {
            return $this->blockedWriteResponse(
                '旧版公开注册入口已下线，当前系统不再处理注册提交、验证码发送或验证码校验。',
                ['register', 'send_code', 'captcha_verify'],
                'legacy_register_decommissioned'
            );
        }

        if ($this->merchantFromRequest($request) !== null) {
            return redirect($this->merchantDashboardUrl($request));
        }

        if (($redirect = $this->compatibilityPageRedirect($request, $this->publicHomeUrl($request))) !== null) {
            return $redirect;
        }

        $payload = $this->registrationPayload($request);
        if ($this->wantsJson($request)) {
            return $this->ok($payload);
        }

        return response(
            $this->publicCompatibilityPage(
                '商户开户说明',
                '旧注册入口已下线',
                '当前页面仅用于说明旧版商户注册入口已停用。新商户创建请统一使用正式入口，不再从旧公开页直接提交。',
                [
                    ['label' => '注册状态', 'value' => (string)($payload['feature_status_label'] ?? '未知')],
                    ['label' => '注册方式', 'value' => (string)($payload['registration_type_label'] ?? '未知')],
                    ['label' => '验证码方式', 'value' => (string)($payload['captcha_type_label'] ?? '未知')],
                    ['label' => '付费注册', 'value' => !empty($payload['paid_registration']['enabled']) ? (string)($payload['paid_registration']['amount_display'] ?? '已开启') : '已关闭'],
                ],
                [
                    '统一入口' => (string)($payload['public_home_url'] ?? ''),
                    '商户登录' => (string)($payload['merchant_login_url'] ?? ''),
                    '可见快捷登录渠道' => (string)count((array)($payload['quick_logins'] ?? [])),
                    '可用联系渠道' => $this->commaJoin(array_map(
                        static fn (array $item): string => (string)$item['label'],
                        array_filter((array)($payload['contact_channels'] ?? []), static fn ($item): bool => !empty($item['available']))
                    )),
                ],
                [
                    '浏览器默认访问会重定向到统一公开入口。',
                    '如需查看历史入口说明，请联系管理员获取历史访问方式。',
                    '旧注册提交、验证码发送和验证码校验接口均已安全关闭。',
                ],
                [
                    ['label' => '进入统一入口', 'href' => (string)($payload['public_home_url'] ?? '')],
                    ['label' => '返回商户登录', 'href' => (string)($payload['merchant_login_url'] ?? '')],
                ]
            ),
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }

    public function lostPassword(Request $request): Response
    {
        if (strtoupper($request->method()) !== 'GET') {
            return $this->blockedWriteResponse(
                '旧版找回密码入口已下线，当前系统不再处理验证码发送、验证码校验或密码重置写入。',
                ['password_recovery', 'send_code', 'captcha_verify'],
                'legacy_password_recovery_decommissioned'
            );
        }

        if (($redirect = $this->compatibilityPageRedirect($request, $this->merchantLoginUrl($request))) !== null) {
            return $redirect;
        }

        $payload = $this->lostPasswordPayload($request);
        if ($this->wantsJson($request)) {
            return $this->ok($payload);
        }

        return response(
            $this->publicCompatibilityPage(
                '账号找回说明',
                '旧找回密码入口已下线',
                '当前页面仅用于说明旧版找回密码入口已停用。旧找回流程已关闭，请返回新商户登录入口继续处理。',
                [
                    ['label' => '找回状态', 'value' => (string)($payload['feature_status_label'] ?? '未知')],
                    ['label' => '找回方式', 'value' => (string)($payload['retrieve_type_label'] ?? '未知')],
                    ['label' => '验证码方式', 'value' => (string)($payload['captcha_type_label'] ?? '未知')],
                    ['label' => '可用联系渠道', 'value' => (string)($payload['available_channel_count'] ?? 0)],
                ],
                [
                    '统一入口' => (string)($payload['public_home_url'] ?? ''),
                    '商户登录' => (string)($payload['merchant_login_url'] ?? ''),
                    '旧写入支持' => !empty($payload['supports_write']) ? '已开启' : '已关闭',
                ],
                [
                    '浏览器默认访问会直接返回新商户登录页。',
                    '旧验证码发送、密码重置和账号找回写入均已停用。',
                    '如需继续操作，请按新系统口径重新登录或联系管理员处理。'
                ],
                [
                    ['label' => '返回商户登录', 'href' => (string)($payload['merchant_login_url'] ?? '')],
                    ['label' => '进入统一入口', 'href' => (string)($payload['public_home_url'] ?? '')],
                ]
            ),
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }

    public function bind(Request $request): Response
    {
        if (strtoupper($request->method()) !== 'GET') {
            return $this->blockedWriteResponse(
                '旧版快捷登录绑定入口已下线，当前系统不再处理绑定写入、验证码发送或验证码校验。',
                ['bind_account', 'send_code', 'captcha_verify'],
                'legacy_bind_decommissioned'
            );
        }

        if ($this->merchantFromRequest($request) !== null) {
            return redirect($this->merchantDashboardUrl($request));
        }

        if (($redirect = $this->compatibilityPageRedirect($request, $this->merchantLoginUrl($request))) !== null) {
            return $redirect;
        }

        $payload = $this->bindPayload($request);
        if ($this->wantsJson($request)) {
            return $this->ok($payload);
        }

        return response(
            $this->publicCompatibilityPage(
                '快捷登录绑定说明',
                '旧绑定入口已下线',
                '当前页面仅用于说明旧版快捷登录绑定入口已停用。绑定、接管和找回写入均已关闭，请返回新商户登录页处理。',
                [
                    ['label' => '可见渠道数', 'value' => (string)count((array)($payload['quick_logins'] ?? []))],
                    ['label' => '当前选择', 'value' => (string)($payload['selected_provider_label'] ?? '未知')],
                    ['label' => '旧写入支持', 'value' => !empty($payload['supports_write']) ? '已开启' : '已关闭'],
                    ['label' => '商户中心', 'value' => (string)($payload['merchant_center_url'] ?? '')],
                ],
                [
                    '统一入口' => (string)($payload['public_home_url'] ?? ''),
                    '商户登录' => (string)($payload['merchant_login_url'] ?? ''),
                    '快捷登录驱动' => $this->commaJoin(array_map(
                        static fn (array $item): string => (string)$item['label'],
                        (array)($payload['quick_logins'] ?? [])
                    )),
                ],
                [
                    '这里只展示旧配置下可见的快捷登录渠道，不执行任何旧式绑定写入。',
                    '浏览器默认访问会返回新商户登录页。',
                    '如需继续使用系统，请按统一登录方式进入新商户端。'
                ],
                [
                    ['label' => '返回商户登录', 'href' => (string)($payload['merchant_login_url'] ?? '')],
                    ['label' => '进入统一入口', 'href' => (string)($payload['public_home_url'] ?? '')],
                ]
            ),
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }

    public function getLoginCode(Request $request): Response
    {
        return $this->blockedWriteResponse(
            '旧版登录验证码发送接口已下线。',
            ['send_login_code'],
            'legacy_login_code_decommissioned'
        );
    }

    public function getRegCode(Request $request): Response
    {
        return $this->blockedWriteResponse(
            '旧版注册验证码发送接口已下线。',
            ['send_registration_code'],
            'legacy_registration_code_decommissioned'
        );
    }

    public function getLostCode(Request $request): Response
    {
        return $this->blockedWriteResponse(
            '旧版找回密码验证码发送接口已下线。',
            ['send_recovery_code'],
            'legacy_recovery_code_decommissioned'
        );
    }

    public function captcha(Request $request): Response
    {
        return $this->blockedWriteResponse(
            '旧版公开验证码校验接口已下线。',
            ['captcha_verify'],
            'legacy_public_captcha_decommissioned'
        );
    }

    public function verify(Request $request): Response
    {
        return response($this->captchaPlaceholderSvg(), 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function oauthAccountLogin(Request $request, ?string $type = null): Response
    {
        if (strtoupper($request->method()) !== 'GET') {
            return $this->blockedWriteResponse(
                '旧版第三方快捷登录跳转与回调入口已下线，当前系统不再处理旧式 OAuth 登录写入。',
                ['oauth_redirect', 'oauth_login'],
                'legacy_oauth_decommissioned'
            );
        }

        $selectedType = trim((string)($type ?? $request->get('type', '')));
        if (($redirect = $this->compatibilityPageRedirect($request, $this->merchantLoginUrl($request))) !== null) {
            return $redirect;
        }

        $payload = $this->quickLoginPayload($request, $selectedType);
        if ($this->wantsJson($request)) {
            return $this->ok($payload);
        }

        return response(
            $this->publicCompatibilityPage(
                '快捷登录说明',
                '旧 OAuth 登录入口已下线',
                '当前页面仅用于说明旧版快捷登录落点已停用。第三方跳转、回调换取登录态和旧式落地写入均已关闭。',
                [
                    ['label' => '登录渠道', 'value' => (string)($payload['selected_provider_label'] ?? '未知')],
                    ['label' => '渠道状态', 'value' => (string)($payload['selected_provider_status'] ?? '未知')],
                    ['label' => '可见渠道数', 'value' => (string)count((array)($payload['quick_logins'] ?? []))],
                    ['label' => '商户中心', 'value' => (string)($payload['merchant_center_url'] ?? '')],
                ],
                [
                    '统一入口' => (string)($payload['public_home_url'] ?? ''),
                    '商户登录' => (string)($payload['merchant_login_url'] ?? ''),
                    '当前类型' => $selectedType !== '' ? $selectedType : '未指定',
                ],
                [
                    '浏览器默认访问会返回新商户登录页。',
                    'QQ 与微信快捷登录的旧回调落点不再开放写入。',
                    '如需继续使用，请从新商户登录页进入统一流程。'
                ],
                [
                    ['label' => '返回商户登录', 'href' => (string)($payload['merchant_login_url'] ?? '')],
                    ['label' => '进入统一入口', 'href' => (string)($payload['public_home_url'] ?? '')],
                ]
            ),
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }

    public function qqLogin(Request $request): Response
    {
        return $this->oauthAccountLogin($request, 'qq');
    }

    public function notice(Request $request): Response
    {
        return $this->blockedWriteResponse(
            '旧版通知写入接口已下线，当前服务不会处理任何旧式通知写入。',
            ['send_notice'],
            'legacy_notice_decommissioned'
        );
    }

    public function pluginDownload(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->jsonOrRedirect($request, 'merchant login is required', $this->merchantLoginUrl($request));
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->jsonOrHtml(
                $request,
                ['code' => 201, 'msg' => 'merchant is frozen', 'message' => 'merchant is frozen'],
                $this->frozenPage($merchant, $this->merchantLoginUrl($request)),
                403
            );
        }

        if (strtoupper($request->method()) !== 'GET') {
            return $this->blockedWriteResponse(
                '旧版插件下载目录维护入口已下线，当前仅保留只读目录浏览能力。',
                ['catalog_refresh', 'catalog_write'],
                'legacy_plugin_catalog_read_only'
            );
        }

        $payload = $this->pluginDownloadPayload($request, $merchant);
        if ($this->wantsJson($request)) {
            return json([
                'code' => 0,
                'msg' => '成功',
                'message' => '成功',
                'data' => $payload['records'],
                'records' => $payload['records'],
                'extend' => [
                    'count' => $payload['total'],
                    'limit' => $payload['size'],
                ],
                'pagination' => [
                    'current' => $payload['current'],
                    'size' => $payload['size'],
                    'total' => $payload['total'],
                ],
                'summary' => $payload['summary'],
                'write_actions' => $payload['write_actions'],
                'migration_guard' => $this->migrationGuardFromPayload($payload, true),
                'route_policy' => $payload['route_policy'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return response($this->pluginDownloadPage($merchant, $payload), 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function registrationPayload(Request $request): array
    {
        $config = SystemConfig::all();
        $captchaType = (int)($config['captcha-type'] ?? 0);
        $registrationType = (int)($config['regcode-type'] ?? 0);
        $quickLogins = $this->quickLoginProviders($config, $this->merchantLoginUrl($request));
        $contactChannels = $this->contactChannels($config);
        $paidRegistrationEnabled = trim((string)($config['paid_reg'] ?? '0')) === '1'
            && (float)($config['paid_reg_price'] ?? 0) > 0;

        return [
            'feature_enabled' => trim((string)($config['is_reg'] ?? '0')) === '1',
            'feature_status_label' => trim((string)($config['is_reg'] ?? '0')) === '1' ? '已开启' : '已关闭',
            'registration_type' => $registrationType,
            'registration_type_label' => $this->registrationTypeLabel($registrationType),
            'captcha_type' => $captchaType,
            'captcha_type_label' => $this->captchaTypeLabel($captchaType),
            'paid_registration' => [
                'enabled' => $paidRegistrationEnabled,
                'amount' => round((float)($config['paid_reg_price'] ?? 0), 2),
                'amount_display' => $this->money($config['paid_reg_price'] ?? 0),
            ],
            'quick_logins' => $quickLogins,
            'contact_channels' => $contactChannels,
            'merchant_login_url' => $this->merchantLoginUrl($request),
            'merchant_center_url' => $this->merchantDashboardUrl($request),
            'public_home_url' => $this->publicHomeUrl($request),
            'legacy_url' => $this->publicHomeUrl($request),
            'route_policy' => [
                'strategy' => 'legacy_register_decommissioned',
                'browser_get' => 'redirect',
                'redirect_url' => $this->publicHomeUrl($request),
                'compat_query_flag' => 'compat=1',
                'write_policy' => 'always_405',
            ],
            'supports_write' => false,
            'write_actions' => [
                'register' => false,
                'send_code' => false,
                'captcha_verify' => false,
            ],
            'migration_guard' => [
                'read_only' => true,
                'blocked_actions' => ['register', 'send_code', 'captcha_verify'],
            ],
        ];
    }

    private function lostPasswordPayload(Request $request): array
    {
        $config = SystemConfig::all();
        $captchaType = (int)($config['captcha-type'] ?? 0);
        $retrieveType = (int)($config['retrieve-type'] ?? 0);
        $channels = $this->contactChannels($config);
        $availableChannelCount = count(array_filter($channels, static fn ($item): bool => !empty($item['available'])));

        return [
            'feature_enabled' => $retrieveType !== 0,
            'feature_status_label' => $retrieveType !== 0 ? '已开启' : '已关闭',
            'retrieve_type' => $retrieveType,
            'retrieve_type_label' => $this->retrieveTypeLabel($retrieveType),
            'captcha_type' => $captchaType,
            'captcha_type_label' => $this->captchaTypeLabel($captchaType),
            'contact_channels' => $channels,
            'available_channel_count' => $availableChannelCount,
            'merchant_login_url' => $this->merchantLoginUrl($request),
            'merchant_center_url' => $this->merchantDashboardUrl($request),
            'public_home_url' => $this->publicHomeUrl($request),
            'legacy_url' => $this->publicHomeUrl($request),
            'route_policy' => [
                'strategy' => 'legacy_password_recovery_decommissioned',
                'browser_get' => 'redirect',
                'redirect_url' => $this->merchantLoginUrl($request),
                'compat_query_flag' => 'compat=1',
                'write_policy' => 'always_405',
            ],
            'supports_write' => false,
            'write_actions' => [
                'password_recovery' => false,
                'send_code' => false,
                'captcha_verify' => false,
            ],
            'migration_guard' => [
                'read_only' => true,
                'blocked_actions' => ['password_recovery', 'send_code', 'captcha_verify'],
            ],
        ];
    }

    private function bindPayload(Request $request): array
    {
        $payload = $this->quickLoginPayload($request, (string)$request->get('type', ''));

        return [
            'quick_logins' => $payload['quick_logins'],
            'selected_provider' => $payload['selected_provider'],
            'selected_provider_label' => $payload['selected_provider_label'],
            'selected_provider_status' => $payload['selected_provider_status'],
            'merchant_login_url' => $payload['merchant_login_url'],
            'merchant_center_url' => $payload['merchant_center_url'],
            'public_home_url' => $payload['public_home_url'],
            'legacy_url' => $payload['legacy_url'],
            'route_policy' => [
                'strategy' => 'legacy_bind_decommissioned',
                'browser_get' => 'redirect',
                'redirect_url' => $payload['merchant_login_url'],
                'compat_query_flag' => 'compat=1',
                'write_policy' => 'always_405',
            ],
            'supports_write' => false,
            'write_actions' => [
                'bind_account' => false,
                'send_code' => false,
                'captcha_verify' => false,
            ],
            'migration_guard' => [
                'read_only' => true,
                'blocked_actions' => ['bind_account', 'send_code', 'captcha_verify'],
            ],
        ];
    }

    private function quickLoginPayload(Request $request, string $selectedType = ''): array
    {
        $config = SystemConfig::all();
        $quickLogins = $this->quickLoginProviders($config, $this->merchantLoginUrl($request));
        $selectedProvider = null;
        foreach ($quickLogins as $provider) {
            if (($provider['id'] ?? '') === $selectedType) {
                $selectedProvider = $provider;
                break;
            }
        }

        return [
            'selected_provider' => $selectedType,
            'selected_provider_label' => (string)($selectedProvider['label'] ?? ($selectedType !== '' ? strtoupper($selectedType) : '自动识别')),
            'selected_provider_status' => (string)($selectedProvider['status_label'] ?? '未开启'),
            'quick_logins' => $quickLogins,
            'merchant_login_url' => $this->merchantLoginUrl($request),
            'merchant_center_url' => $this->merchantDashboardUrl($request),
            'public_home_url' => $this->publicHomeUrl($request),
            'legacy_url' => $this->publicHomeUrl($request),
            'route_policy' => [
                'strategy' => 'legacy_oauth_decommissioned',
                'browser_get' => 'redirect',
                'redirect_url' => $this->merchantLoginUrl($request),
                'compat_query_flag' => 'compat=1',
                'write_policy' => 'always_405',
            ],
            'supports_write' => false,
            'write_actions' => [
                'oauth_redirect' => false,
                'oauth_login' => false,
            ],
            'migration_guard' => [
                'read_only' => true,
                'blocked_actions' => ['oauth_redirect', 'oauth_login'],
            ],
        ];
    }

    private function pluginDownloadPayload(Request $request, array $merchant): array
    {
        $current = max(1, (int)$request->get('current', $request->get('page', 1)));
        $size = max(1, min((int)$request->get('size', $request->get('limit', 10)), 100));
        $keyword = trim((string)$request->get('keyword', ''));

        $query = Db::table('ypay_plug')
            ->select('id', 'name', 'downurl', 'introduce', 'status', 'create_time', 'update_time', 'delete_time')
            ->where('status', 1)
            ->whereNull('delete_time');

        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword): void {
                $builder
                    ->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('downurl', 'like', '%' . $keyword . '%')
                    ->orWhere('introduce', 'like', '%' . $keyword . '%');
            });
        }

        $total = (int)(clone $query)->count('id');
        $rows = (clone $query)
            ->orderByDesc('id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        $records = array_map(function ($row): array {
            $item = (array)$row;
            $downloadUrl = trim((string)($item['downurl'] ?? ''));
            return [
                'id' => (int)($item['id'] ?? 0),
                'name' => trim((string)($item['name'] ?? '')),
                'downurl' => $downloadUrl,
                'introduce' => $this->nullableString($item['introduce'] ?? null),
                'download_host' => $downloadUrl !== '' ? parse_url($downloadUrl, PHP_URL_HOST) : null,
                'create_time' => $this->nullableString($item['create_time'] ?? null),
                'update_time' => $this->nullableString($item['update_time'] ?? null),
                'status' => 1,
                'status_label' => '可用',
            ];
        }, $rows);

        return [
            'merchant_username' => trim((string)($merchant['username'] ?? '')),
            'records' => $records,
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => [
                'total_count' => $total,
                'keyword' => $keyword,
                'available_count' => $total,
            ],
            'write_actions' => [
                'catalog_refresh' => false,
                'catalog_write' => false,
            ],
            'migration_guard' => [
                'read_only' => true,
                'blocked_actions' => ['catalog_refresh', 'catalog_write'],
            ],
            'route_policy' => [
                'strategy' => 'legacy_plugin_catalog_read_only',
                'browser_get' => 'allow_read_only',
                'write_policy' => 'always_405',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function quickLoginProviders(array $config, string $merchantLoginUrl): array
    {
        $definitions = [
            ['id' => 'qq', 'label' => 'QQ 登录', 'config_key' => 'qq_login', 'entry' => $merchantLoginUrl],
            ['id' => 'wx', 'label' => '微信登录', 'config_key' => 'wechat_login', 'entry' => $merchantLoginUrl],
        ];

        $configIds = [];
        foreach ($definitions as $definition) {
            $configId = (int)($config[$definition['config_key']] ?? 0);
            if ($configId > 0) {
                $configIds[$configId] = $configId;
            }
        }

        $rows = [];
        if ($configIds !== []) {
            foreach (
                Db::table('ypay_quicklogin')
                    ->select('id', 'type', 'status', 'name', 'url', 'appid', 'appkey', 'create_time')
                    ->whereIn('id', array_values($configIds))
                    ->get()
                    ->toArray() as $row
            ) {
                $item = (array)$row;
                $rows[(int)($item['id'] ?? 0)] = $item;
            }
        }

        $providers = [];
        foreach ($definitions as $definition) {
            $configId = (int)($config[$definition['config_key']] ?? 0);
            $record = $configId > 0 ? ($rows[$configId] ?? null) : null;
            $status = (int)($record['status'] ?? 0);
            $available = $configId > 0 && is_array($record) && $status === 1;
            $providers[] = [
                'id' => $definition['id'],
                'label' => $definition['label'],
                'config_id' => $configId > 0 ? $configId : null,
                'available' => $available,
                'status_label' => $available ? '可用' : '未开启',
                'status_type' => $available ? 'success' : 'warning',
                'config_name' => trim((string)($record['name'] ?? $definition['label'])),
                'driver_type' => trim((string)($record['type'] ?? '')),
                'credential_ready' => trim((string)($record['appid'] ?? '')) !== '' && trim((string)($record['appkey'] ?? '')) !== '',
                'callback_entry' => $definition['entry'],
                'write_message' => '旧版第三方快捷登录跳转与回调入口已下线。',
            ];
        }

        return $providers;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function contactChannels(array $config): array
    {
        return [
            ['id' => 'email', 'label' => '邮箱', 'available' => trim((string)($config['email_switch'] ?? '0')) === '1'],
            ['id' => 'mobile', 'label' => '手机', 'available' => trim((string)($config['code_switch'] ?? '0')) === '1'],
            ['id' => 'wxpusher_uid', 'label' => 'WxPusher', 'available' => trim((string)($config['wxpusher_switch'] ?? '0')) === '1'],
            ['id' => 'tg_chat_id', 'label' => 'Telegram', 'available' => trim((string)($config['tg_switch'] ?? '0')) === '1'],
        ];
    }

    private function ok(array $payload): Response
    {
        $responsePayload = [
            'code' => 0,
            'msg' => '成功',
            'message' => '成功',
            'data' => $payload,
        ];
        $responsePayload['msg'] = ApiResponse::normalizeText((string)$responsePayload['msg']);
        $responsePayload['message'] = ApiResponse::normalizeText((string)$responsePayload['message']);

        return json($responsePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function blockedWriteResponse(string $message, array $blockedActions, string $strategy): Response
    {
        $responsePayload = [
            'code' => 202,
            'msg' => $message,
            'message' => $message,
            'data' => [
                'write_actions' => array_fill_keys($blockedActions, false),
                'migration_guard' => [
                    'read_only' => true,
                    'blocked_actions' => array_values($blockedActions),
                ],
                'route_policy' => [
                    'strategy' => $strategy,
                    'write_policy' => 'always_405',
                ],
            ],
        ];
        $responsePayload['msg'] = ApiResponse::normalizeText((string)$responsePayload['msg']);
        $responsePayload['message'] = ApiResponse::normalizeText((string)$responsePayload['message']);

        return json($responsePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)->withStatus(405);
    }

    private function merchantFromRequest(Request $request): ?array
    {
        $token = MerchantFrontSession::resolveToken($request);
        if ($token === '') {
            return null;
        }

        $row = Db::table('ypay_user')
            ->select('id', 'username', 'is_frozen', 'frozen_reason')
            ->where('token', $token)
            ->first();

        return $row ? (array)$row : null;
    }

    private function wantsJson(Request $request): bool
    {
        $accept = strtolower((string)$request->header('accept', ''));
        $requestedWith = strtolower((string)$request->header('x-requested-with', ''));
        $format = strtolower(trim((string)$request->get('format', '')));

        return str_contains($accept, 'application/json')
            || $requestedWith === 'xmlhttprequest'
            || $format === 'json';
    }

    private function compatibilityPageRedirect(Request $request, string $location): ?Response
    {
        if ($this->wantsJson($request)) {
            return null;
        }

        return trim((string)$request->get('compat', '')) === '1' ? null : redirect($location);
    }

    private function jsonOrRedirect(Request $request, string $message, string $location): Response
    {
        $message = $this->normalizeMerchantMessage($message);

        if ($this->wantsJson($request)) {
            return json(['code' => 401, 'msg' => $message, 'message' => $message], JSON_UNESCAPED_UNICODE)
                ->withStatus(401);
        }

        return redirect($location);
    }

    private function jsonOrHtml(Request $request, array $payload, string $html, int $status): Response
    {
        if ($this->wantsJson($request)) {
            return json($this->normalizeMerchantPayload($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ->withStatus($status);
        }

        return response($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function normalizeMerchantPayload(array $payload): array
    {
        foreach (['msg', 'message'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key])) {
                $payload[$key] = $this->normalizeMerchantMessage($payload[$key]);
            }
        }

        $payload['migration_guard'] = $this->migrationGuardFromPayload($payload, true);

        return $payload;
    }

    private function migrationGuardFromPayload(array $payload, bool $readOnly = false): array
    {
        $guard = $payload['migration_guard'] ?? null;
        if (is_array($guard)) {
            return [
                'read_only' => (bool)($guard['read_only'] ?? $readOnly),
                'blocked_actions' => array_values(array_filter(
                    is_array($guard['blocked_actions'] ?? null) ? $guard['blocked_actions'] : [],
                    static fn (mixed $action): bool => is_string($action) && trim($action) !== ''
                )),
            ];
        }

        return [
            'read_only' => $readOnly,
            'blocked_actions' => [],
        ];
    }

    private function normalizeMerchantMessage(string $message): string
    {
        return match (trim($message)) {
            'merchant login is required' => '请先登录商户账号',
            'merchant is frozen' => '商户账户已冻结',
            default => $message,
        };
    }

    private function publicCompatibilityPage(
        string $title,
        string $eyebrow,
        string $description,
        array $cards,
        array $facts,
        array $notes,
        array $actions
    ): string {
        $safeTitle = $this->escape($title);
        $safeEyebrow = $this->escape($eyebrow);
        $safeDescription = $this->escape($description);

        $cardHtml = '';
        foreach ($cards as $card) {
            $cardHtml .= '<div class="card"><span>' . $this->escape((string)($card['label'] ?? '')) . '</span><strong>'
                . $this->escape((string)($card['value'] ?? '--')) . '</strong></div>';
        }

        $factHtml = '';
        foreach ($facts as $label => $value) {
            $factHtml .= '<tr><th>' . $this->escape((string)$label) . '</th><td>' . $this->escape((string)$value) . '</td></tr>';
        }

        $noteHtml = '';
        foreach ($notes as $note) {
            $noteHtml .= '<li>' . $this->escape((string)$note) . '</li>';
        }

        $actionHtml = '';
        foreach ($actions as $action) {
            $href = $this->escape((string)($action['href'] ?? '#'));
            $label = $this->escape((string)($action['label'] ?? '打开'));
            $actionHtml .= '<a class="btn" href="' . $href . '">' . $label . '</a>';
        }

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$safeTitle}</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:linear-gradient(135deg,#f8fafc,#e0f2fe);color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1120px;margin:0 auto 20px;padding:28px;border-radius:24px;background:linear-gradient(135deg,#0f172a,#155e75);color:#fff;box-shadow:0 20px 60px rgba(15,23,42,.18)}
    .hero p{margin:0;color:#cbd5e1;line-height:1.8}
    .eyebrow{display:inline-flex;padding:6px 10px;border-radius:999px;background:rgba(255,255,255,.12);font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
    h1{margin:18px 0 10px;font-size:30px}
    .grid{max-width:1120px;margin:0 auto;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
    .card,.panel{background:#fff;border:1px solid #dbeafe;border-radius:18px;padding:18px;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    .card span{display:block;color:#64748b;font-size:13px;margin-bottom:8px}
    .card strong{display:block;font-size:22px;color:#0f172a}
    .panel{max-width:1120px;margin:14px auto 0}
    table{width:100%;border-collapse:collapse}
    th,td{padding:12px 0;border-bottom:1px solid #e2e8f0;text-align:left;font-size:14px;vertical-align:top}
    th{width:220px;color:#475569}
    ul{margin:0;padding-left:18px;color:#475569;line-height:1.8}
    .actions{max-width:1120px;margin:14px auto 0;display:flex;gap:10px;flex-wrap:wrap}
    .btn{display:inline-flex;padding:11px 15px;border-radius:12px;background:#0f172a;color:#fff;text-decoration:none}
    @media (max-width:900px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media (max-width:560px){.grid{grid-template-columns:1fr}.shell{padding:18px}.hero{padding:22px}th{width:120px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <span class="eyebrow">{$safeEyebrow}</span>
      <h1>{$safeTitle}</h1>
      <p>{$safeDescription}</p>
    </section>
    <section class="grid">{$cardHtml}</section>
    <section class="panel">
      <table>{$factHtml}</table>
    </section>
    <section class="panel">
      <ul>{$noteHtml}</ul>
    </section>
    <section class="actions">{$actionHtml}</section>
  </div>
</body>
</html>
HTML;
    }

    private function pluginDownloadPage(array $merchant, array $payload): string
    {
        $displayName = $this->escape((string)($merchant['username'] ?? '商户'));
        $summary = (array)($payload['summary'] ?? []);
        $rowsHtml = '';

        foreach ((array)($payload['records'] ?? []) as $record) {
            $item = (array)$record;
            $downloadUrl = trim((string)($item['downurl'] ?? ''));
            $openLabel = $downloadUrl !== '' ? '<a class="link" href="' . $this->escape($downloadUrl) . '" target="_blank" rel="noreferrer">打开链接</a>' : '<span class="muted">暂无链接</span>';
            $rowsHtml .= '<tr>'
                . '<td>' . (int)($item['id'] ?? 0) . '</td>'
                . '<td>' . $this->escape((string)($item['name'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string)($item['download_host'] ?? '未知')) . '</td>'
                . '<td>' . $this->escape((string)($item['introduce'] ?? '')) . '</td>'
                . '<td>' . $openLabel . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="5" class="muted">当前没有已发布的插件下载记录。</td></tr>';
        }

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>插件下载目录</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f7f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1160px;margin:0 auto 20px;padding:28px;border-radius:24px;background:linear-gradient(135deg,#111827,#1d4ed8);color:#fff;box-shadow:0 20px 60px rgba(15,23,42,.18)}
    .hero p{margin:8px 0 0;color:#dbeafe;line-height:1.8}
    .grid{max-width:1160px;margin:0 auto;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
    .card,.panel{background:#fff;border:1px solid #dbeafe;border-radius:18px;padding:18px;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    .card span{display:block;color:#64748b;font-size:13px;margin-bottom:8px}
    .card strong{display:block;font-size:24px}
    .panel{max-width:1160px;margin:14px auto 0}
    .notice{max-width:1160px;margin:14px auto 0;padding:16px 18px;border-radius:16px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;line-height:1.8}
    table{width:100%;border-collapse:collapse}
    th,td{padding:14px 0;border-bottom:1px solid #e2e8f0;text-align:left;font-size:14px;vertical-align:top}
    .link{color:#1d4ed8;text-decoration:none}
    .muted{color:#64748b}
    @media (max-width:860px){.grid{grid-template-columns:1fr}}
    @media (max-width:560px){.shell{padding:18px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>插件下载目录</h1>
      <p>{$displayName}，当前页面仅保留公开下载记录查询能力；目录刷新、写入和维护请统一在管理员后台处理。</p>
    </section>
    <section class="grid">
      <div class="card"><span>已发布插件</span><strong>{$this->escape((string)($summary['total_count'] ?? 0))}</strong></div>
      <div class="card"><span>关键词筛选</span><strong>{$this->escape((string)($summary['keyword'] ?? '未筛选'))}</strong></div>
      <div class="card"><span>写保护</span><strong>已开启</strong></div>
    </section>
    <section class="notice">你可以继续查看公开下载记录，但不能在这里刷新目录或修改目录数据。</section>
    <section class="panel">
      <table>
        <thead>
          <tr><th>编号</th><th>名称</th><th>主机</th><th>简介</th><th>链接</th></tr>
        </thead>
        <tbody>{$rowsHtml}</tbody>
      </table>
    </section>
  </div>
</body>
</html>
HTML;
    }

    private function frozenPage(array $merchant, string $loginUrl): string
    {
        $username = $this->escape((string)($merchant['username'] ?? '商户'));
        $reason = $this->escape((string)($merchant['frozen_reason'] ?? '当前商户账号已被冻结。'));
        $safeLoginUrl = $this->escape($loginUrl);

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>商户已冻结</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#fff7ed;color:#7c2d12}
    main{max-width:640px;margin:10vh auto;padding:28px;background:#fff;border:1px solid #fed7aa;border-radius:20px;box-shadow:0 18px 48px rgba(124,45,18,.08)}
    h1{margin:0 0 10px;font-size:28px}
    p{line-height:1.8}
    a{display:inline-flex;margin-top:12px;padding:10px 14px;border-radius:12px;background:#9a3412;color:#fff;text-decoration:none}
  </style>
</head>
<body>
  <main>
    <h1>商户已冻结</h1>
    <p>{$username}，当前商户账号已被冻结，暂时无法继续访问这些历史公开页面。</p>
    <p>{$reason}</p>
    <a href="{$safeLoginUrl}">返回登录</a>
  </main>
</body>
</html>
HTML;
    }

    private function captchaPlaceholderSvg(): string
    {
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="240" height="70" viewBox="0 0 240 70" role="img" aria-label="当前已关闭验证码">
  <rect width="240" height="70" rx="14" fill="#eff6ff"/>
  <rect x="5" y="5" width="230" height="60" rx="12" fill="none" stroke="#93c5fd" stroke-width="2"/>
  <text x="120" y="30" text-anchor="middle" font-family="Segoe UI, Microsoft YaHei, sans-serif" font-size="13" fill="#1d4ed8">当前已关闭验证码</text>
  <text x="120" y="49" text-anchor="middle" font-family="Segoe UI, Microsoft YaHei, sans-serif" font-size="11" fill="#475569">请返回商户登录页或统一入口</text>
</svg>
SVG;
    }

    private function registrationTypeLabel(int $value): string
    {
        return match ($value) {
            1 => '手机验证',
            2 => '邮箱验证',
            3 => 'Telegram 验证',
            default => '账号密码 + 邮箱',
        };
    }

    private function retrieveTypeLabel(int $value): string
    {
        return match ($value) {
            1 => '手机找回',
            2 => '邮箱找回',
            3 => 'Telegram 找回',
            default => '已关闭',
        };
    }

    private function captchaTypeLabel(int $value): string
    {
        return match ($value) {
            1 => '图片验证码',
            2 => '腾讯验证码',
            3 => '极验验证码',
            default => '已关闭',
        };
    }

    private function merchantLoginUrl(Request $request): string
    {
        return FrontendUrlBuilder::merchantLoginUrl($request);
    }

    private function merchantDashboardUrl(Request $request): string
    {
        return FrontendUrlBuilder::merchantDashboardUrl($request);
    }

    private function publicHomeUrl(Request $request): string
    {
        return FrontendUrlBuilder::publicHomeUrl($request);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function money(mixed $value): string
    {
        return number_format((float)$value, 2, '.', '');
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string)$value);
        return $text === '' ? null : $text;
    }

    private function commaJoin(array $items): string
    {
        $values = array_values(array_filter(array_map(
            static fn ($item): string => trim((string)$item),
            $items
        ), static fn ($item): bool => $item !== ''));

        return $values === [] ? '无' : implode('、', $values);
    }
}
