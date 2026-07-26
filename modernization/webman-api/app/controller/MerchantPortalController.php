<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\controller;

use app\controller\concerns\AdminControllerFormatSupport;
use app\controller\concerns\MerchantPortalUrlSupport;
use app\service\MerchantRechargeService;
use app\service\order\OrderCallbackTaskService;
use app\support\AdminRechargeFormatter;
use app\support\AdminDomainFormatter;
use app\support\AdminFrontLogFormatter;
use app\support\AdminSmtpMailer;
use app\support\AdminTicketFormatter;
use app\support\AdminVipFormatter;
use app\support\ApiResponse;
use app\support\BusinessTable;
use app\support\Environment;
use app\support\GoogleAuthenticator;
use app\support\LegacyPassword;
use app\support\LegacyMojibakeGuard;
use app\support\MerchantFrontSession;
use app\support\MerchantPortalAccountSupport;
use app\support\MerchantPortalCancellationSupport;
use app\support\MerchantPortalConnectionSupport;
use app\support\MerchantPortalHtmlLocalizer;
use app\support\MerchantPortalMessageCatalog;
use app\support\MerchantPortalSecuritySupport;
use app\support\MerchantPortalReadOnlyGuard;
use app\support\MerchantSmsCodeSender;
use app\support\RequestRateLimiter;
use app\support\RequestPayload;
use app\support\SystemConfig;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class MerchantPortalController
{
    use AdminControllerFormatSupport;
    use MerchantPortalUrlSupport;

    private const COOKIE_TTL = 7 * 86400;
    private const GOOGLE_AUTH_BIND_COOKIE = 'merchant_google_auth_ticket';
    private const GOOGLE_AUTH_BIND_TTL = 900;
    private const MERCHANT_CONNECTION_VERIFY_COOKIE = 'merchant_connection_verify_ticket';
    private const MERCHANT_CONNECTION_VERIFY_TTL = 300;
    private const MERCHANT_CONNECTION_RESEND_SECONDS = 60;
    private const FRONT_LOG_DUPLICATE_WINDOW_SECONDS = 30;
    private const DEFAULT_VOICE_TIPS = MerchantPortalMessageCatalog::DEFAULT_VOICE_TIPS;

    public function login(Request $request): Response
    {
        if (strtoupper($request->method()) !== 'GET') {
            return $this->loginSubmit($request);
        }

        $merchant = $this->merchantFromRequest($request);
        if ($merchant !== null && (int)($merchant['is_frozen'] ?? 0) !== 1) {
            return $this->merchantSpaRedirect($request, '/merchant/dashboard');
        }

        return redirect($this->merchantLoginUrl($request));
    }

    public function index(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request, '请先登录商户账号');
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->jsonOrHtml(
                $request,
                ['code' => 201, 'msg' => 'merchant is frozen', 'message' => 'merchant is frozen'],
                $this->frozenPage($merchant),
                403
            );
        }

        $this->recordMerchantFrontLog(
            (int)($merchant['id'] ?? 0),
            '/merchant/dashboard',
            3,
            '进入商户中心',
            $request
        );

        if ($this->wantsJson($request)) {
            return json([
                'code' => 0,
                'msg' => '成功',
                'message' => '成功',
                'data' => $this->merchantDashboardPayload($merchant),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $this->merchantSpaRedirect($request, '/merchant/dashboard');
    }

    public function logout(Request $request): Response
    {
        $response = $this->wantsJson($request)
            ? $this->merchantJson(200, '已退出登录', 200)
            : redirect($this->merchantLoginUrl($request));

        return $response
            ->cookie('front_token', '', 0, '/')
            ->cookie('sign', '', 0, '/')
            ->cookie('PHPSESSID', '', 0, '/')
            ->cookie(self::MERCHANT_CONNECTION_VERIFY_COOKIE, '', 0, '/')
            ->cookie(self::GOOGLE_AUTH_BIND_COOKIE, '', 0, '/');
    }

    public function userProfile(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->jsonOrHtml(
                $request,
                ['code' => 201, 'msg' => 'merchant is frozen', 'message' => 'merchant is frozen'],
                $this->frozenPage($merchant),
                403
            );
        }

        if (strtoupper($request->method()) !== 'GET') {
            return $this->saveMerchantProfile($request, $merchant);
        }

        $payload = $this->merchantProfilePayload($request, $merchant);
        if ($this->wantsJson($request)) {
            return json([
                'code' => 0,
                'msg' => '成功',
                'message' => '成功',
                'data' => $payload,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $this->merchantSpaRedirectForCurrentRequest($request);
    }

    public function notifications(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->jsonOrHtml(
                $request,
                ['code' => 201, 'msg' => 'merchant is frozen', 'message' => 'merchant is frozen'],
                $this->frozenPage($merchant),
                403
            );
        }

        if (strtoupper($request->method()) !== 'GET') {
            return $this->saveMerchantNotifications($request, $merchant);
        }

        $payload = $this->merchantNotificationsPayload($merchant);
        if ($this->wantsJson($request)) {
            return json([
                'code' => 0,
                'msg' => '成功',
                'message' => '成功',
                'data' => $payload,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $this->merchantSpaRedirectForCurrentRequest($request);
    }

    public function connections(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->jsonOrHtml(
                $request,
                ['code' => 201, 'msg' => 'merchant is frozen', 'message' => 'merchant is frozen'],
                $this->frozenPage($merchant),
                403
            );
        }

        if (strtoupper($request->method()) !== 'GET') {
            return $this->blockedWriteResponse('connections');
        }

        $payload = MerchantPortalConnectionSupport::payload(
            $merchant,
            SystemConfig::all(),
            fn (string $value): ?string => $this->maskIdentifier($value),
            fn (mixed $value): ?string => $this->nullableString($value)
        );
        if ($this->wantsJson($request)) {
            return json([
                'code' => 0,
                'msg' => '成功',
                'message' => '成功',
                'data' => $payload,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $this->merchantSpaRedirectForCurrentRequest($request);
    }

    public function unbinding(Request $request): Response
    {
        $merchant = $this->connectionMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $payload = $this->requestPayload($request);
        $type = strtolower($this->sanitizeMerchantInput($payload['type'] ?? ''));
        $updates = match ($type) {
            'qq' => ['is_bindqq' => 0, 'qq_sid' => ''],
            'wx', 'wechat' => ['is_bindwx' => 0, 'wx_sid' => ''],
            'wxpusher', 'wxpusher_uid' => ['wxpusher_uid' => ''],
            'telegram', 'tg', 'tg_chat_id' => ['tg_chat_id' => ''],
            default => null,
        };

        if ($updates === null) {
            return $this->merchantValidationError('不支持的绑定类型');
        }

        Db::table(BusinessTable::user())
            ->where('id', (int)($merchant['id'] ?? 0))
            ->update($updates);

        return json([
            'code' => 1,
            'msg' => '解绑成功',
            'message' => '解绑成功',
            'data' => [
                'type' => $type,
                'bindings' => MerchantPortalConnectionSupport::payload(
                    array_replace($merchant, $updates),
                    SystemConfig::all(),
                    fn (string $value): ?string => $this->maskIdentifier($value),
                    fn (mixed $value): ?string => $this->nullableString($value)
                ),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function wxPusherQrCode(Request $request): Response
    {
        $merchant = $this->connectionMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $config = SystemConfig::all();
        $enrollment = MerchantPortalConnectionSupport::wxPusherEnrollmentPayload(
            $merchant,
            $config,
            fn (string $value): ?string => $this->maskIdentifier($value)
        );
        if (empty($enrollment['write_allowed'])) {
            return $this->merchantJson(
                202,
                (string)($enrollment['write_message'] ?? '当前无法生成 WxPusher 绑定二维码'),
                403,
                $enrollment
            );
        }

        try {
            $payload = array_merge(
                $enrollment,
                MerchantPortalConnectionSupport::createWxPusherQrCode(
                    (int)($merchant['id'] ?? 0),
                    MerchantPortalConnectionSupport::wxPusherAppToken($config),
                    fn (mixed $value): ?string => $this->nullableString($value),
                    fn (string $scanContent): string => $this->merchantQrCodeUrl($request, $scanContent, '260x260'),
                    (int)($enrollment['expires_seconds'] ?? 1800)
                ),
                [
                    'merchant_id' => (int)($merchant['id'] ?? 0),
                    'merchant_username' => trim((string)($merchant['username'] ?? '')),
                    'uid_masked' => $this->maskIdentifier(trim((string)($merchant['wxpusher_uid'] ?? ''))),
                    'callback_url' => $this->requestOrigin($request) . '/Notify/wxpusher',
                ]
            );
        } catch (\RuntimeException $exception) {
            return $this->merchantJson(201, $exception->getMessage(), 502, $enrollment);
        }

        return json([
            'code' => 1,
            'msg' => 'WxPusher 绑定二维码已生成',
            'message' => 'WxPusher 绑定二维码已生成',
            'data' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function wxPusherUidStatus(Request $request): Response
    {
        $merchant = $this->connectionMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $payload = $this->requestPayload($request);
        $operate = strtolower($this->sanitizeMerchantInput($payload['operate'] ?? 'bind'));
        $uid = $this->sanitizeMerchantInput($payload['uid'] ?? '');
        $currentUid = trim((string)($merchant['wxpusher_uid'] ?? ''));
        $code = 2;

        if ($operate === 'bind') {
            $code = $currentUid !== '' ? 1 : 2;
        } elseif ($uid !== '' && $currentUid !== $uid) {
            $code = 1;
        }

        return json([
            'code' => $code,
            'msg' => $this->normalizeMerchantMessage(
                $code === 1 ? 'wxpusher uid is already configured' : 'wxpusher uid is not configured yet'
            ),
            'message' => $this->normalizeMerchantMessage(
                $code === 1 ? 'wxpusher uid is already configured' : 'wxpusher uid is not configured yet'
            ),
            'data' => [
                'operate' => $operate,
                'bound' => $currentUid !== '',
                'uid_masked' => $this->maskIdentifier($currentUid),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function saveWxPusherUid(Request $request): Response
    {
        $merchant = $this->connectionMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $payload = $this->requestPayload($request);
        $uid = $this->sanitizeMerchantInput($payload['wxpusher_uid'] ?? '');
        $currentUid = trim((string)($merchant['wxpusher_uid'] ?? ''));

        if ($uid === '') {
            return json([
                'code' => $currentUid !== '' ? 1 : 2,
                'msg' => $this->normalizeMerchantMessage(
                    $currentUid !== ''
                        ? 'wxpusher uid is already configured'
                        : 'please submit a wxpusher uid before saving'
                ),
                'message' => $this->normalizeMerchantMessage(
                    $currentUid !== ''
                        ? 'wxpusher uid is already configured'
                        : 'please submit a wxpusher uid before saving'
                ),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($this->stringLength($uid) > 50) {
            return $this->merchantValidationError('wxpusher uid is too long');
        }

        Db::table(BusinessTable::user())
            ->where('id', (int)($merchant['id'] ?? 0))
            ->update(['wxpusher_uid' => $uid]);

        return json([
            'code' => 1,
            'msg' => '微信推送标识已保存',
            'message' => '微信推送标识已保存',
            'data' => [
                'wxpusher_uid_masked' => $this->maskIdentifier($uid),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function saveTgChatId(Request $request): Response
    {
        $merchant = $this->connectionMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $payload = $this->requestPayload($request);
        $chatId = trim((string)($payload['tg_chat_id'] ?? ''));
        if ($chatId === '') {
            return $this->merchantValidationError('telegram chat id is required');
        }
        if ($this->stringLength($chatId) > 50) {
            return $this->merchantValidationError('telegram chat id is too long');
        }
        if (!preg_match('/^-?[0-9]{5,32}$/', $chatId)) {
            return $this->merchantValidationError('telegram chat id format is invalid');
        }

        Db::table(BusinessTable::user())
            ->where('id', (int)($merchant['id'] ?? 0))
            ->update(['tg_chat_id' => $chatId]);

        return json([
            'code' => 1,
            'msg' => '电报会话标识已保存',
            'message' => '电报会话标识已保存',
            'data' => [
                'tg_chat_id_masked' => $this->maskIdentifier($chatId),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function getUnbindCode(Request $request): Response
    {
        $merchant = $this->connectionMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        return $this->sendMerchantConnectionCode($request, $merchant, 'unbind');
    }

    public function getBindCode(Request $request): Response
    {
        $merchant = $this->connectionMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        return $this->sendMerchantConnectionCode($request, $merchant, 'bind');
    }

    public function bindOrUnbindEmail(Request $request): Response
    {
        $merchant = $this->connectionMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        return $this->submitMerchantConnectionVerification($request, $merchant, 'email');
    }

    public function bindOrUnbindMobile(Request $request): Response
    {
        $merchant = $this->connectionMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        return $this->submitMerchantConnectionVerification($request, $merchant, 'mobile');
    }

    private function sendMerchantConnectionCode(Request $request, array $merchant, string $action): Response
    {
        $payload = $this->requestPayload($request);
        $channel = strtolower($this->sanitizeMerchantInput($payload['bind'] ?? $payload['channel'] ?? ''));
        if (!in_array($channel, ['email', 'mobile'], true)) {
            return $this->merchantValidationError('connection verification channel is invalid');
        }

        $config = SystemConfig::all();
        $featureError = $this->merchantConnectionFeatureError($channel, $config);
        if ($featureError !== null) {
            return $featureError;
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $target = $this->resolveMerchantConnectionVerificationTarget($merchant, $payload, $channel, $action, $merchantId);
        if ($target instanceof Response) {
            return $target;
        }

        $existingTicket = $this->readMerchantConnectionVerifyTicket($request, $merchantId);
        if (
            $existingTicket !== null
            && (string)($existingTicket['channel'] ?? '') === $channel
            && (string)($existingTicket['action'] ?? '') === $action
            && (string)($existingTicket['target'] ?? '') === $target
            && (int)($existingTicket['issued_at'] ?? 0) + self::MERCHANT_CONNECTION_RESEND_SECONDS > time()
        ) {
            return $this->merchantValidationError('please wait before requesting another verification code');
        }

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            $delivery = $this->dispatchMerchantConnectionVerificationCode($channel, $action, $target, $code, $config);
        } catch (\Throwable $exception) {
            $message = trim((string)$exception->getMessage());
            return $this->merchantValidationError(
                $message !== '' ? $message : 'merchant connection verification code sending failed'
            );
        }

        $response = json([
            'code' => 200,
            'msg' => '绑定验证码已发送',
            'message' => '绑定验证码已发送',
            'data' => array_merge([
                'channel' => $channel,
                'action' => $action,
                'target_masked' => $this->maskIdentifier($target) ?? $target,
                'expires_in' => self::MERCHANT_CONNECTION_VERIFY_TTL,
            ], $delivery),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->storeMerchantConnectionVerifyTicket($response, $merchantId, $channel, $action, $target, $code);
    }

    private function submitMerchantConnectionVerification(Request $request, array $merchant, string $channel): Response
    {
        $payload = $this->requestPayload($request);
        $type = (int)($payload['type'] ?? 0);
        $action = match ($type) {
            1 => 'bind',
            2 => 'unbind',
            default => '',
        };
        if ($action === '') {
            return $this->merchantValidationError('connection verification action is invalid');
        }

        $config = SystemConfig::all();
        $featureError = $this->merchantConnectionFeatureError($channel, $config);
        if ($featureError !== null) {
            return $featureError;
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $target = $this->resolveMerchantConnectionVerificationTarget($merchant, $payload, $channel, $action, $merchantId);
        if ($target instanceof Response) {
            return $target;
        }

        $code = $this->sanitizeMerchantInput($payload['captcha'] ?? $payload['code'] ?? '');
        if (!preg_match('/^\d{6}$/', $code)) {
            return $this->merchantValidationError('verification code is invalid');
        }

        $ticket = $this->readMerchantConnectionVerifyTicket($request, $merchantId);
        if ($ticket === null || (int)($ticket['expires_at'] ?? 0) < time()) {
            return $this->merchantValidationError('verification code has expired');
        }

        if (
            (string)($ticket['channel'] ?? '') !== $channel
            || (string)($ticket['action'] ?? '') !== $action
            || (string)($ticket['target'] ?? '') !== $target
        ) {
            return $this->merchantValidationError('verification target does not match the current request');
        }

        $expectedHash = $this->merchantConnectionVerificationCodeHash($code);
        if (!hash_equals((string)($ticket['code_hash'] ?? ''), $expectedHash)) {
            return $this->merchantValidationError('verification code is invalid');
        }

        $fieldValue = $action === 'bind' ? $target : '';
        Db::table(BusinessTable::user())
            ->where('id', $merchantId)
            ->update([$channel => $fieldValue]);

        $updatedMerchant = array_replace($merchant, [$channel => $fieldValue]);
        $response = json([
            'code' => 1,
            'msg' => sprintf('merchant %s %s completed successfully', $channel, $action),
            'message' => sprintf('merchant %s %s completed successfully', $channel, $action),
            'data' => [
                'channel' => $channel,
                'action' => $action,
                'value' => $fieldValue !== '' ? $fieldValue : null,
                'bindings' => MerchantPortalConnectionSupport::payload(
                    $updatedMerchant,
                    SystemConfig::all(),
                    fn (string $value): ?string => $this->maskIdentifier($value),
                    fn (mixed $value): ?string => $this->nullableString($value)
                ),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->clearMerchantConnectionVerifyTicket($response);
    }

    private function merchantConnectionFeatureError(string $channel, array $config): ?Response
    {
        if ($channel === 'email' && trim((string)($config['email_switch'] ?? '0')) !== '1') {
            return $this->merchantValidationError('当前邮箱验证功能未开启');
        }

        if ($channel === 'mobile' && trim((string)($config['code_switch'] ?? '0')) !== '1') {
            return $this->merchantValidationError('当前手机验证功能未开启');
        }

        return null;
    }

    private function resolveMerchantConnectionVerificationTarget(
        array $merchant,
        array $payload,
        string $channel,
        string $action,
        int $merchantId
    ): string|Response {
        $currentValue = trim((string)($merchant[$channel] ?? ''));
        if ($action === 'unbind') {
            if ($currentValue === '') {
                return $this->merchantValidationError(
                    $channel === 'email'
                        ? 'current merchant email is not configured yet'
                        : 'current merchant mobile is not configured yet'
                );
            }

            return $currentValue;
        }

        if ($currentValue !== '') {
            return $this->merchantValidationError(
                $channel === 'email'
                    ? 'current merchant email is already configured, please use unbind flow'
                    : 'current merchant mobile is already configured, please use unbind flow'
            );
        }

        if ($channel === 'email') {
            $email = strtolower($this->sanitizeMerchantInput($payload['email'] ?? ''));
            if ($email === '') {
                return $this->merchantValidationError('email address is required');
            }
            if ($this->stringLength($email) > 50) {
                return $this->merchantValidationError('email is too long');
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                return $this->merchantValidationError('email format is invalid');
            }
            if ($this->merchantFieldExists('email', $email, $merchantId)) {
                return $this->merchantValidationError('email is already in use by another merchant');
            }

            return $email;
        }

        $mobile = $this->sanitizeMerchantInput($payload['mobile'] ?? '');
        if ($mobile === '') {
            return $this->merchantValidationError('mobile number is required');
        }
        if ($this->stringLength($mobile) > 50) {
            return $this->merchantValidationError('mobile number is too long');
        }
        if (!preg_match('/^1\d{10}$/', $mobile)) {
            return $this->merchantValidationError('mobile format is invalid');
        }
        if ($this->merchantFieldExists('mobile', $mobile, $merchantId)) {
            return $this->merchantValidationError('mobile number is already in use by another merchant');
        }

        return $mobile;
    }

    private function dispatchMerchantConnectionVerificationCode(
        string $channel,
        string $action,
        string $target,
        string $code,
        array $config
    ): array {
        $debug = $this->merchantDebugMode();
        $data = [
            'delivery_mode' => $channel === 'email' ? 'email' : 'sms',
        ];

        if ($channel === 'email') {
            $mailer = new AdminSmtpMailer();
            $summary = $mailer->configurationSummary($config);
            if ($summary['ready']) {
                $mailer->sendHtml(
                    $target,
                    $this->merchantConnectionVerificationTitle($channel, $action, $config),
                    $this->merchantConnectionVerificationEmailHtml($channel, $action, $target, $code, $config),
                    $config
                );

                if ($debug) {
                    $data['debug_code'] = $code;
                }

                return $data;
            }

            if (!$debug) {
                if (!$summary['enabled']) {
                    throw new \InvalidArgumentException('system email switch is disabled');
                }

                throw new \InvalidArgumentException('smtp configuration is incomplete');
            }

            return [
                'delivery_mode' => 'debug',
                'debug_code' => $code,
            ];
        }

        $smsSender = new MerchantSmsCodeSender();
        $summary = $smsSender->configurationSummary($config);
        if ($summary['ready']) {
            $smsSender->sendCode($target, $code, $config);

            if ($debug) {
                $data['debug_code'] = $code;
            }

            return $data;
        }

        if (!$debug) {
            $smsSender->assertReady($config);
        }

        return [
            'delivery_mode' => 'debug',
            'debug_code' => $code,
        ];
    }

    private function merchantConnectionVerificationTitle(string $channel, string $action, array $config): string
    {
        $siteName = trim((string)($config['sitename'] ?? 'AiPay'));
        $channelLabel = $channel === 'email' ? '邮箱' : '手机';
        $actionLabel = $action === 'bind' ? '绑定' : '解绑';

        return $siteName . '商户' . $channelLabel . $actionLabel . '验证码';
    }

    private function merchantConnectionVerificationEmailHtml(
        string $channel,
        string $action,
        string $target,
        string $code,
        array $config
    ): string {
        $channelLabel = $channel === 'email' ? '邮箱' : '手机';
        $actionLabel = $action === 'bind' ? '绑定' : '解绑';
        $maskedTarget = $this->maskIdentifier($target) ?? $target;
        $template = trim((string)($config['diy_codeTemp'] ?? ''));
        if ($template === '') {
            $template = '您的验证码是 [code]，5 分钟内有效，请勿泄露。';
        }
        $template = str_replace('[code]', $code, $template);
        $safeTemplate = nl2br($this->escape($template));
        $safeTarget = $this->escape($maskedTarget);
        $safeCode = $this->escape($code);
        $safeTitle = $this->escape($this->merchantConnectionVerificationTitle($channel, $action, $config));

        return <<<HTML
<div style="font-family:'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;background:#f8fafc;padding:24px;color:#172033">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #dbeafe;border-radius:20px;padding:28px">
    <h2 style="margin:0 0 14px;font-size:22px;color:#0f172a">{$safeTitle}</h2>
    <p style="margin:0 0 16px;line-height:1.8">当前请求正在执行商户{$actionLabel}{$channelLabel}校验，目标：{$safeTarget}</p>
    <div style="margin:0 0 16px;padding:18px;border-radius:16px;background:#eff6ff;border:1px solid #bfdbfe;text-align:center">
      <div style="font-size:13px;color:#475569;margin-bottom:6px">验证码</div>
      <div style="font-size:32px;letter-spacing:8px;font-weight:700;color:#1d4ed8">{$safeCode}</div>
    </div>
    <p style="margin:0;color:#475569;line-height:1.8">{$safeTemplate}</p>
  </div>
</div>
HTML;
    }

    private function merchantDebugMode(): bool
    {
        return (bool)config('app.debug', false) && \app\support\ProductionSecurity::debugAssistAllowed();
    }

    private function storeMerchantConnectionVerifyTicket(
        Response $response,
        int $merchantId,
        string $channel,
        string $action,
        string $target,
        string $code
    ): Response {
        $issuedAt = time();
        $expiresAt = $issuedAt + self::MERCHANT_CONNECTION_VERIFY_TTL;
        $codeHash = $this->merchantConnectionVerificationCodeHash($code);
        $message = implode('|', [$merchantId, $channel, $action, $target, $codeHash, $issuedAt, $expiresAt]);
        $payload = [
            'merchant_id' => $merchantId,
            'channel' => $channel,
            'action' => $action,
            'target' => $target,
            'code_hash' => $codeHash,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'signature' => hash_hmac('sha256', $message, $this->merchantConnectionVerifyTicketKey()),
        ];

        return $response->cookie(
            self::MERCHANT_CONNECTION_VERIFY_COOKIE,
            $this->encodeGoogleAuthTicket($payload),
            self::MERCHANT_CONNECTION_VERIFY_TTL,
            '/'
        );
    }

    private function clearMerchantConnectionVerifyTicket(Response $response): Response
    {
        return $response->cookie(self::MERCHANT_CONNECTION_VERIFY_COOKIE, '', 0, '/');
    }

    private function readMerchantConnectionVerifyTicket(Request $request, int $merchantId): ?array
    {
        $ticket = $this->decodeGoogleAuthTicket(
            (string)$request->cookie(self::MERCHANT_CONNECTION_VERIFY_COOKIE, '')
        );
        if ($ticket === null) {
            return null;
        }

        $ticketMerchantId = (int)($ticket['merchant_id'] ?? 0);
        $channel = trim((string)($ticket['channel'] ?? ''));
        $action = trim((string)($ticket['action'] ?? ''));
        $target = trim((string)($ticket['target'] ?? ''));
        $codeHash = trim((string)($ticket['code_hash'] ?? ''));
        $issuedAt = (int)($ticket['issued_at'] ?? 0);
        $expiresAt = (int)($ticket['expires_at'] ?? 0);
        $signature = trim((string)($ticket['signature'] ?? ''));

        if (
            $ticketMerchantId !== $merchantId
            || !in_array($channel, ['email', 'mobile'], true)
            || !in_array($action, ['bind', 'unbind'], true)
            || $target === ''
            || $codeHash === ''
            || $issuedAt <= 0
            || $expiresAt <= 0
            || $signature === ''
        ) {
            return null;
        }

        $message = implode('|', [$ticketMerchantId, $channel, $action, $target, $codeHash, $issuedAt, $expiresAt]);
        $expected = hash_hmac('sha256', $message, $this->merchantConnectionVerifyTicketKey());
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        return $ticket;
    }

    private function merchantConnectionVerificationCodeHash(string $code): string
    {
        return hash_hmac('sha256', $code, $this->merchantConnectionVerifyTicketKey() . '|code');
    }

    private function merchantConnectionVerifyTicketKey(): string
    {
        return hash(
            'sha256',
            dirname(base_path(), 2) . '|' . self::MERCHANT_CONNECTION_VERIFY_COOKIE . '|webman-merchant-connections'
        );
    }

    public function security(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->jsonOrHtml(
                $request,
                ['code' => 201, 'msg' => 'merchant is frozen', 'message' => 'merchant is frozen'],
                $this->frozenPage($merchant),
                403
            );
        }

        if (strtoupper($request->method()) !== 'GET') {
            return $this->blockedWriteResponse('security');
        }

        $payload = $this->merchantSecurityPayloadV2($request, $merchant);
        if ($this->wantsJson($request)) {
            return json([
                'code' => 0,
                'msg' => '成功',
                'message' => '成功',
                'data' => $payload,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $this->merchantSpaRedirectForCurrentRequest($request);
    }

    public function updatePassword(Request $request): Response
    {
        $merchant = $this->securityMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $payload = $this->requestPayload($request);
        $newPassword = trim((string)($payload['newpwd'] ?? ''));
        $confirmPassword = trim((string)($payload['renewpwd'] ?? ''));

        if ($newPassword === '' || $confirmPassword === '') {
            return $this->merchantValidationError('new password and confirmation are required');
        }
        if (strlen($newPassword) < 6) {
            return $this->merchantValidationError('new password must be at least 6 characters long');
        }
        if (strlen($newPassword) > 64) {
            return $this->merchantValidationError('new password is too long');
        }
        if ($newPassword !== $confirmPassword) {
            return $this->merchantValidationError('password confirmation does not match');
        }

        Db::table(BusinessTable::user())
            ->where('id', (int)($merchant['id'] ?? 0))
            ->update(['password' => LegacyPassword::hash($newPassword)]);

        $this->rotateMerchantToken((int)($merchant['id'] ?? 0));

        return json([
            'code' => 200,
            'msg' => '密码修改成功，请重新登录',
            'message' => '密码修改成功，请重新登录',
            'data' => [
                'relogin_required' => true,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->cookie('front_token', '', 0, '/')
            ->cookie('sign', '', 0, '/')
            ->cookie('PHPSESSID', '', 0, '/')
            ->cookie(self::MERCHANT_CONNECTION_VERIFY_COOKIE, '', 0, '/');
    }

    public function cancellation(Request $request): Response
    {
        $merchant = $this->securityMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        if (strtoupper($request->method()) === 'GET') {
            if ($this->wantsJson($request)) {
                return $this->merchantJson(405, '请使用 POST 提交账号注销', 405, [
                    'account_cancellation' => MerchantPortalCancellationSupport::payload($merchant),
                ]);
            }

            return $this->merchantSpaRedirectForCurrentRequest($request);
        }

        if (!MerchantPortalCancellationSupport::featureEnabled()) {
            return $this->merchantJson(202, '商户账号注销功能未开启', 403, [
                'account_cancellation' => MerchantPortalCancellationSupport::payload($merchant),
            ]);
        }

        $audit = MerchantPortalCancellationSupport::audit($merchant);
        $payload = $this->requestPayload($request);
        $confirmation = trim((string)($payload['confirmation'] ?? $payload['confirm'] ?? $payload['phrase'] ?? ''));

        if ($confirmation === '') {
            return $this->merchantJson(422, '请输入注销确认口令', 422, [
                'account_cancellation' => MerchantPortalCancellationSupport::payload($merchant, $audit),
            ]);
        }

        if (!hash_equals((string)($audit['confirmation_phrase'] ?? ''), $confirmation)) {
            return $this->merchantJson(422, '注销确认口令不正确', 422, [
                'account_cancellation' => MerchantPortalCancellationSupport::payload($merchant, $audit),
            ]);
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        Db::transaction(function () use ($merchantId): void {
            MerchantPortalCancellationSupport::deleteOwnedRows($merchantId);
        });

        return $this->merchantJson(200, '商户账号已注销', 200, [
            'deleted' => true,
            'relogin_required' => true,
            'merchant_id' => $merchantId,
        ])->cookie('front_token', '', 0, '/')
            ->cookie('sign', '', 0, '/')
            ->cookie('PHPSESSID', '', 0, '/')
            ->cookie(self::MERCHANT_CONNECTION_VERIFY_COOKIE, '', 0, '/')
            ->cookie(self::GOOGLE_AUTH_BIND_COOKIE, '', 0, '/');
    }

    public function generateKey(Request $request): Response
    {
        $merchant = $this->apiKeyMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $key = $this->generateMerchantSecret(32);
        Db::table(BusinessTable::user())
            ->where('id', (int)($merchant['id'] ?? 0))
            ->update(['user_key' => $key]);

        return json([
            'code' => 1,
            'msg' => '商户签名密钥重置成功',
            'message' => '商户签名密钥重置成功',
            'key' => $key,
            'data' => [
                'key' => $key,
                'key_masked' => $this->maskSecret($key),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function generateAppKey(Request $request): Response
    {
        $merchant = $this->apiKeyMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $key = $this->generateMerchantSecret(32);
        $merchantId = (int)($merchant['id'] ?? 0);
        MerchantPortalAccountSupport::ensureBasicRecord($merchantId, $key);
        Db::table(BusinessTable::userBasic())
            ->where('user_id', $merchantId)
            ->update(['appkey' => $key]);

        return json([
            'code' => 1,
            'msg' => '商户通讯密钥重置成功',
            'message' => '商户通讯密钥重置成功',
            'key' => $key,
            'data' => [
                'key' => $key,
                'key_masked' => $this->maskSecret($key),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function googleAuth(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->jsonOrHtml(
                $request,
                ['code' => 201, 'msg' => 'merchant is frozen', 'message' => 'merchant is frozen'],
                $this->frozenPage($merchant),
                403
            );
        }

        if (strtoupper($request->method()) !== 'GET') {
            return $this->blockedWriteResponse('security');
        }

        $payload = $this->merchantSecurityPayloadV2($request, $merchant);
        if ($this->wantsJson($request)) {
            return json([
                'code' => 0,
                'msg' => '成功',
                'message' => '成功',
                'data' => $payload,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $this->merchantSpaRedirectForCurrentRequest($request);
    }

    public function googleAuthQrCode(Request $request): Response
    {
        $merchant = $this->securityMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        if (trim((string)($merchant['googlekey'] ?? '')) !== '') {
            return $this->merchantValidationError('当前商户账号已绑定谷歌验证器');
        }

        $config = SystemConfig::all();
        $merchantId = (int)($merchant['id'] ?? 0);
        $secret = (new GoogleAuthenticator())->createSecret();

        $response = json([
            'code' => 200,
            'msg' => '谷歌验证二维码已生成',
            'message' => '谷歌验证二维码已生成',
            'data' => $this->buildMerchantGoogleAuthPayload(
                $merchant,
                $config,
                trim((string)($config['isSecurity'] ?? '0')) === '1',
                trim((string)($config['isSecurityLogin'] ?? '0')) === '1',
                $secret
            ),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->storeGoogleAuthBindTicket($response, $merchantId, $secret);
    }

    public function bindGoogleAuth(Request $request): Response
    {
        $merchant = $this->securityMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        if (trim((string)($merchant['googlekey'] ?? '')) !== '') {
            return $this->merchantValidationError('当前商户账号已绑定谷歌验证器');
        }

        $payload = $this->requestPayload($request);
        $code = $this->sanitizeMerchantInput($payload['code'] ?? '');
        if (!preg_match('/^\d{6}$/', $code)) {
            return $this->merchantValidationError('请输入 6 位谷歌验证码');
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $secret = $this->readGoogleAuthBindTicket($request, $merchantId);
        if ($secret === null) {
            return $this->merchantValidationError('请先重新获取谷歌验证二维码');
        }

        if (!(new GoogleAuthenticator())->verifyCode($secret, $code, 4)) {
            return $this->merchantValidationError('谷歌验证码不正确');
        }

        Db::table(BusinessTable::user())
            ->where('id', $merchantId)
            ->update(['googlekey' => $secret]);

        $updatedMerchant = array_replace($merchant, ['googlekey' => $secret]);
        $config = SystemConfig::all();
        $response = json([
            'code' => 200,
            'msg' => '谷歌验证器绑定成功',
            'message' => '谷歌验证器绑定成功',
            'data' => $this->buildMerchantGoogleAuthPayload(
                $updatedMerchant,
                $config,
                trim((string)($config['isSecurity'] ?? '0')) === '1',
                trim((string)($config['isSecurityLogin'] ?? '0')) === '1'
            ),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->clearGoogleAuthBindTicket($response);
    }

    public function unbindGoogleAuth(Request $request): Response
    {
        $merchant = $this->securityMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $secret = trim((string)($merchant['googlekey'] ?? ''));
        if ($secret === '') {
            return $this->merchantValidationError('当前商户账号尚未绑定谷歌验证器');
        }

        $payload = $this->requestPayload($request);
        $code = $this->sanitizeMerchantInput($payload['code'] ?? '');
        if (!preg_match('/^\d{6}$/', $code)) {
            return $this->merchantValidationError('请输入 6 位谷歌验证码');
        }

        if (!(new GoogleAuthenticator())->verifyCode($secret, $code, 4)) {
            return $this->merchantValidationError('谷歌验证码不正确');
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        Db::table(BusinessTable::user())
            ->where('id', $merchantId)
            ->update(['googlekey' => '']);

        $updatedMerchant = array_replace($merchant, ['googlekey' => '']);
        $config = SystemConfig::all();
        $response = json([
            'code' => 200,
            'msg' => '谷歌验证器解绑成功',
            'message' => '谷歌验证器解绑成功',
            'data' => $this->buildMerchantGoogleAuthPayload(
                $updatedMerchant,
                $config,
                trim((string)($config['isSecurity'] ?? '0')) === '1',
                trim((string)($config['isSecurityLogin'] ?? '0')) === '1'
            ),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->clearGoogleAuthBindTicket($response);
    }

    public function affiliate(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->jsonOrHtml(
                $request,
                ['code' => 201, 'msg' => 'merchant is frozen', 'message' => 'merchant is frozen'],
                $this->frozenPage($merchant),
                403
            );
        }

        if (strtoupper($request->method()) === 'GET' && $this->wantsMerchantSpaPage($request)) {
            return $this->merchantSpaRedirectForCurrentRequest($request);
        }

        if (!$this->affiliateFeatureEnabled()) {
            if ($this->wantsJson($request)) {
                return $this->merchantJson(202, '当前推广返佣功能未开启', 200);
            }

            return $this->htmlResponse($this->featureDisabledPage(
                $request,
                $merchant,
                '推广返佣',
                '当前系统尚未开启推广返佣功能。',
                '如需开放推广返佣，请先在系统配置中启用邀请返佣开关。功能关闭期间，推广记录与返佣发放入口不会对商户开放。'
            ));
        }

        if (strtoupper($request->method()) !== 'GET') {
            return $this->merchantJson(202, '当前推广返佣页用于查看统计数据，邀请链接重置和返佣提现请通过对应结算入口处理。', 405);
        }

        if ($this->wantsJson($request)) {
            $payload = $this->merchantAffiliatePayload($request, $merchant);
            return json([
                'code' => 0,
                'msg' => '成功',
                'message' => '成功',
                'data' => $payload,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $this->merchantSpaRedirectForCurrentRequest($request);
    }

    public function affiliateInfo(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->merchantJson(201, 'merchant is frozen', 403);
        }

        if (!$this->affiliateFeatureEnabled()) {
            return $this->merchantJson(202, '当前推广返佣功能未开启', 200);
        }

        $payload = $this->merchantAffiliatePayload($request, $merchant);
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
            'migration_guard' => $this->migrationGuardFromPayload($payload),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function realName(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->jsonOrHtml(
                $request,
                ['code' => 201, 'msg' => 'merchant is frozen', 'message' => 'merchant is frozen'],
                $this->frozenPage($merchant),
                403
            );
        }

        if (strtoupper($request->method()) === 'GET' && $this->wantsMerchantSpaPage($request)) {
            return $this->merchantSpaRedirectForCurrentRequest($request);
        }

        if (!$this->realNameFeatureEnabled()) {
            if ($this->wantsJson($request)) {
                return $this->merchantJson(202, '当前实名认证功能未开启', 200);
            }

            return $this->htmlResponse($this->featureDisabledPage(
                $request,
                $merchant,
                '实名认证',
                '当前系统尚未开启实名认证功能。',
                '实名认证开关关闭时，商户端不会开放实名提交流程。如需启用，请先在系统配置中打开实名认证相关开关。'
            ));
        }

        if (strtoupper($request->method()) !== 'GET') {
            return $this->realNameSubmit($request);
        }

        $payload = $this->merchantRealNamePayloadV2($request, $merchant);
        if ($this->wantsJson($request)) {
            return json([
                'code' => 0,
                'msg' => '成功',
                'message' => '成功',
                'data' => $payload,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $this->merchantSpaRedirectForCurrentRequest($request);
    }

    public function realNameSubmit(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantJson(401, 'merchant login is required', 401);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->merchantJson(201, 'merchant is frozen', 403);
        }

        if (!$this->realNameFeatureEnabled()) {
            return $this->merchantJson(202, '当前实名认证功能未开启', 200);
        }

        if ((int)($merchant['is_realName'] ?? 0) === 1) {
            return $this->merchantValidationError('merchant real-name verification is already completed');
        }

        $config = SystemConfig::all();
        $type = max(1, (int)($config['realNameType'] ?? 1));
        $input = $this->resolveMerchantRealNameInput($this->requestPayload($request), $type);

        if ($input['name'] === '') {
            return $this->merchantValidationError('real-name full name is required');
        }
        if ($input['id_card'] === '') {
            return $this->merchantValidationError('real-name id card is required');
        }
        if (!$this->isValidRealNameIdCard($input['id_card'])) {
            return $this->merchantValidationError('real-name id card format is invalid');
        }

        $availableChannels = $this->realNameAvailableChannels($type);
        $channel = strtolower(trim((string)($input['channel'] ?? '')));
        if ($channel === '' || !isset($availableChannels[$channel])) {
            return $this->merchantValidationError('real-name verification channel is invalid');
        }

        $merchantId = (int)($merchant['id'] ?? 0);

        try {
            Db::table(BusinessTable::user())
                ->where('id', $merchantId)
                ->update([
                    'name' => $input['name'],
                    'idCard' => $input['id_card'],
                ]);

            if ($type === 1) {
                $thinkCode = trim((string)($config['thinkCode'] ?? ''));
                if ($thinkCode === '') {
                    return $this->merchantJson(422, 'real-name provider app code is not configured', 422);
                }

                $this->requireLegacyProjectAutoload();

                $faceauthMode = $channel === 'wechat' ? 'WECHAT' : 'ZHIMACREDIT';
                $callbackUrl = $this->requestOrigin($request) . '/security/real-name';
                $notifyUrl = $this->requestOrigin($request) . '/Notify/realName';

                $client = new \think\api\Client($thinkCode);
                $result = $client->faceDetect()
                    ->withIdcard($input['id_card'])
                    ->withName($input['name'])
                    ->withCallbackUrl($callbackUrl)
                    ->withNotifyUrl($notifyUrl)
                    ->withFaceauthMode($faceauthMode)
                    ->request();

                $providerMessage = trim((string)($result['message'] ?? ''));
                $providerStatus = !empty($result['data']['status']);
                $originalUrl = trim((string)($result['data']['originalUrl'] ?? ''));
                $orderNumber = trim((string)($result['data']['orderNumber'] ?? ''));

                if (!$providerStatus || $originalUrl === '' || $orderNumber === '') {
                    return $this->merchantJson(
                        422,
                        $providerMessage !== '' ? $providerMessage : 'merchant real-name verification request failed',
                        422
                    );
                }

                if (trim((string)($config['realNameBear'] ?? '0')) === '1') {
                    $this->deductMerchantRealNameFee($merchantId, round((float)($config['bearMoney'] ?? 0), 2));
                }

                $qrcodeUrl = $this->merchantQrCodeUrl($request, $originalUrl, '350x350');

                return $this->merchantJson(200, 'merchant real-name verification started successfully', 200, [
                    'merchant_id' => $merchantId,
                    'channel' => $channel,
                    'channel_label' => (string)($availableChannels[$channel]['label'] ?? $channel),
                    'orderNumber' => $orderNumber,
                    'order_number' => $orderNumber,
                    'qrcode' => $qrcodeUrl,
                    'qr_url' => $qrcodeUrl,
                    'original_url' => $originalUrl,
                    'redirect_url' => $originalUrl,
                    'state' => 'processing',
                ]);
            }

            $appId = trim((string)($config['appid'] ?? ''));
            if ($appId === '') {
                return $this->merchantJson(422, 'real-name alipay app credentials are not configured', 422);
            }

            $originalUrl = 'https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?' . http_build_query([
                'app_id' => $appId,
                'redirect_uri' => $this->requestOrigin($request) . '/Notify/aliRealName',
                'cert_verify_id' => $merchantId,
                'scope' => 'id_verify',
                'state' => 'STATE',
            ], '', '&', PHP_QUERY_RFC3986);
            $qrcodeUrl = $this->merchantQrCodeUrl($request, $originalUrl, '350x350');

            return $this->merchantJson(200, 'merchant real-name verification started successfully', 200, [
                'merchant_id' => $merchantId,
                'channel' => $channel,
                'channel_label' => (string)($availableChannels[$channel]['label'] ?? $channel),
                'orderNumber' => 'ali',
                'order_number' => 'ali',
                'qrcode' => $qrcodeUrl,
                'qr_url' => $qrcodeUrl,
                'original_url' => $originalUrl,
                'redirect_url' => $originalUrl,
                'state' => 'processing',
            ]);
        } catch (\Throwable $exception) {
            $message = trim($exception->getMessage());
            return $this->merchantJson(
                422,
                $message !== '' ? $message : 'merchant real-name verification request failed',
                422
            );
        }
    }

    public function realNameStatus(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->merchantJson(201, 'merchant is frozen', 403);
        }

        if (!$this->realNameFeatureEnabled()) {
            return $this->merchantJson(202, '当前实名认证功能未开启', 200);
        }

        $payload = $this->merchantRealNamePayload($request, $merchant);
        $status = (array)($payload['status'] ?? []);
        $verification = (array)($payload['verification'] ?? []);
        $verified = !empty($status['verified']);

        return json([
            'code' => $verified ? 200 : 201,
            'msg' => $verified ? '已认证' : '未认证',
            'message' => $verified ? '已认证' : '未认证',
            'data' => [
                'merchant_id' => (int)($payload['merchant_id'] ?? 0),
                'feature_enabled' => (bool)($status['feature_enabled'] ?? false),
                'verified' => $verified,
                'status_label' => (string)($status['label'] ?? '未知'),
                'status_type' => (string)($status['type'] ?? 'info'),
                'name_masked' => $status['name_masked'] ?? null,
                'id_card_masked' => $status['id_card_masked'] ?? null,
                'status_endpoint' => '/api/merchant/security/real-name/status',
                'write_allowed' => (bool)($verification['write_allowed'] ?? false),
                'write_message' => (string)($verification['write_message'] ?? '实名认证状态会根据当前提交结果动态更新。'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function realNameStatusV2(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->merchantJson(201, 'merchant is frozen', 403);
        }

        if (!$this->realNameFeatureEnabled()) {
            return $this->merchantJson(202, '当前实名认证功能未开启', 200);
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $payloadData = $this->requestPayload($request);
        $orderNumber = trim((string)($request->get('orderNumber', $payloadData['orderNumber'] ?? $payloadData['order_number'] ?? '')));
        $providerMessage = '';
        $providerStatus = null;

        if ($orderNumber !== '' && strtolower($orderNumber) !== 'ali') {
            $thinkCode = trim((string)SystemConfig::get('thinkCode', ''));
            if ($thinkCode === '') {
                return $this->merchantJson(422, 'real-name provider app code is not configured', 422);
            }

            try {
                $this->requireLegacyProjectAutoload();
                $client = new \think\api\Client($thinkCode);
                $result = $client->faceQuery()
                    ->withOrderNumber($orderNumber)
                    ->request();

                $providerStatus = isset($result['data']['status']) ? (int)($result['data']['status']) : null;
                $providerMessage = trim((string)($result['message'] ?? ''));

                if ($providerStatus === 1) {
                    Db::table(BusinessTable::user())
                        ->where('id', $merchantId)
                        ->update([
                            'is_realName' => 1,
                            'name' => trim((string)($result['data']['name'] ?? ($merchant['name'] ?? ''))),
                            'idCard' => trim((string)($result['data']['idcard'] ?? ($merchant['idCard'] ?? ''))),
                        ]);
                }
            } catch (\Throwable $exception) {
                $message = trim($exception->getMessage());
                return $this->merchantJson(422, $message !== '' ? $message : 'merchant real-name verification status query failed', 422);
            }
        }

        $payload = $this->merchantRealNamePayloadV2($request, $this->merchantById($merchantId) ?? $merchant);
        $status = (array)($payload['status'] ?? []);
        $verified = !empty($status['verified']);
        $message = $verified
            ? '实名认证已完成'
            : '实名认证处理中';

        return json([
            'code' => $verified ? 200 : 201,
            'msg' => $message,
            'message' => $message,
            'data' => [
                'merchant_id' => (int)($payload['merchant_id'] ?? 0),
                'feature_enabled' => (bool)($status['feature_enabled'] ?? false),
                'verified' => $verified,
                'status_label' => (string)($status['label'] ?? '未知'),
                'status_type' => (string)($status['type'] ?? 'info'),
                'name_masked' => $status['name_masked'] ?? null,
                'id_card_masked' => $status['id_card_masked'] ?? null,
                'order_number' => $orderNumber,
                'orderNumber' => $orderNumber,
                'state' => $verified ? 'verified' : ($orderNumber !== '' ? 'processing' : 'pending'),
                'provider_status' => $providerStatus,
                'provider_message' => $providerMessage,
                'status_endpoint' => '/api/merchant/security/real-name/status',
                'write_allowed' => true,
                'write_message' => (string)($payload['verification']['write_message'] ?? ''),
                'message' => $message,
                'payload' => $payload,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function notifyRealNameCallback(Request $request): Response
    {
        $payload = array_merge($request->get(), $request->post() ?: []);
        $passed = strtolower(trim((string)($payload['passed'] ?? '')));
        $merchantId = (int)($payload['merchant_id'] ?? $payload['user_id'] ?? 0);

        if ($passed === 'true' && $merchantId > 0) {
            Db::table(BusinessTable::user())
                ->where('id', $merchantId)
                ->update(['is_realName' => 1]);
        }

        return response('success', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function notifyWxPusherCallback(Request $request): Response
    {
        $payload = array_merge($request->get(), $this->requestPayload($request));
        $data = $payload['data'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        $merchantId = (int)($data['extra'] ?? $payload['extra'] ?? 0);
        $uid = $this->sanitizeMerchantInput($data['uid'] ?? $payload['uid'] ?? '');

        if ($merchantId > 0 && $uid !== '') {
            Db::table(BusinessTable::user())
                ->where('id', $merchantId)
                ->update(['wxpusher_uid' => $uid]);
        }

        return response('success', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function notifyAliRealNameCallback(Request $request): Response
    {
        $authCode = trim((string)$request->get('auth_code', ''));
        $merchantId = (int)$request->get('cert_verify_id', 0);

        if ($authCode === '' || $merchantId <= 0) {
            return $this->htmlResponse($this->realNameCallbackResultPage(
                $request,
                false,
                '未接收到支付宝实名授权参数，请返回商户中心重新发起认证。'
            ), 400);
        }

        $config = SystemConfig::all();
        if (
            trim((string)($config['appid'] ?? '')) === ''
            || trim((string)($config['rsaPrivateKey'] ?? '')) === ''
            || trim((string)($config['alipayrsaPublicKey'] ?? '')) === ''
        ) {
            return $this->htmlResponse($this->realNameCallbackResultPage(
                $request,
                false,
                '支付宝实名认证配置不完整，请联系管理员检查应用密钥。'
            ), 500);
        }

        $merchant = $this->merchantById($merchantId);
        if ($merchant === null) {
            return $this->htmlResponse($this->realNameCallbackResultPage(
                $request,
                false,
                '对应商户不存在，无法完成实名认证结果写入。'
            ), 404);
        }

        try {
            $this->requireLegacyProjectAutoload();

            $aop = new \AopClient();
            $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
            $aop->appId = trim((string)($config['appid'] ?? ''));
            $aop->rsaPrivateKey = trim((string)($config['rsaPrivateKey'] ?? ''));
            $aop->alipayrsaPublicKey = trim((string)($config['alipayrsaPublicKey'] ?? ''));
            $aop->apiVersion = '1.0';
            $aop->signType = 'RSA2';
            $aop->postCharset = 'UTF-8';
            $aop->format = 'json';

            $tokenRequest = new \AlipaySystemOauthTokenRequest();
            $tokenRequest->setCode($authCode);
            $tokenRequest->setGrantType('authorization_code');
            $tokenResult = $aop->execute($tokenRequest);
            $tokenNode = str_replace('.', '_', $tokenRequest->getApiMethodName()) . '_response';
            $accessToken = trim((string)($tokenResult->$tokenNode->access_token ?? ''));
            if ($accessToken === '') {
                throw new \RuntimeException('alipay real-name authorization failed');
            }

            $name = trim((string)($merchant['name'] ?? ''));
            $idCard = trim((string)($merchant['idCard'] ?? ''));
            if ($name === '' || $idCard === '') {
                throw new \RuntimeException('merchant identity information is incomplete');
            }

            $preconsultRequest = new \AlipayUserCertdocCertverifyPreconsultRequest();
            $preconsultRequest->setBizContent(json_encode([
                'user_name' => $name,
                'cert_type' => 'IDENTITY_CARD',
                'cert_no' => $idCard,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $preconsultResult = $aop->execute($preconsultRequest);
            $preconsultNode = str_replace('.', '_', $preconsultRequest->getApiMethodName()) . '_response';
            $verifyId = trim((string)($preconsultResult->$preconsultNode->verify_id ?? ''));
            if ($verifyId === '') {
                throw new \RuntimeException('alipay real-name authorization failed');
            }

            $consultRequest = new \AlipayUserCertdocCertverifyConsultRequest();
            $consultRequest->setBizContent(json_encode([
                'verify_id' => $verifyId,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $consultResult = $aop->execute($consultRequest, $accessToken);
            $consultNode = str_replace('.', '_', $consultRequest->getApiMethodName()) . '_response';
            $passed = strtoupper(trim((string)($consultResult->$consultNode->passed ?? ''))) === 'T';

            if ($passed) {
                Db::table(BusinessTable::user())
                    ->where('id', $merchantId)
                    ->update(['is_realName' => 1]);

                return $this->htmlResponse($this->realNameCallbackResultPage(
                    $request,
                    true,
                    '支付宝实名认证已完成，请回到商户中心刷新认证状态。'
                ));
            }

            return $this->htmlResponse($this->realNameCallbackResultPage(
                $request,
                false,
                '支付宝实名认证未通过，请返回商户中心重新发起认证。'
            ), 400);
        } catch (\Throwable $exception) {
            $message = trim($exception->getMessage());
            return $this->htmlResponse($this->realNameCallbackResultPage(
                $request,
                false,
                $message !== '' ? $message : 'alipay real-name authorization failed'
            ), 500);
        }
    }

    public function orderLog(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request, '请先登录商户账号');
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->jsonOrHtml(
                $request,
                ['code' => 201, 'msg' => 'merchant is frozen', 'message' => 'merchant is frozen'],
                $this->frozenPage($merchant),
                403
            );
        }

        if ($this->wantsJson($request)) {
            $payload = $this->merchantOrdersPayload($request, (int)$merchant['id']);
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
                'migration_guard' => $this->migrationGuardFromPayload($payload),
            ], JSON_UNESCAPED_UNICODE);
        }

        return $this->merchantSpaRedirectForCurrentRequest($request);
    }

    public function moneyLog(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->jsonOrHtml(
                $request,
                ['code' => 201, 'msg' => 'merchant is frozen', 'message' => 'merchant is frozen'],
                $this->frozenPage($merchant),
                403
            );
        }

        if ($this->wantsJson($request)) {
            $payload = $this->merchantMoneyLogsPayload($request, (int)$merchant['id']);
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
            ], JSON_UNESCAPED_UNICODE);
        }

        return $this->merchantSpaRedirectForCurrentRequest($request);
    }

    public function recharge(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->jsonOrHtml(
                $request,
                ['code' => 201, 'msg' => 'merchant is frozen', 'message' => 'merchant is frozen'],
                $this->frozenPage($merchant),
                403
            );
        }

        if (strtoupper($request->method()) !== 'GET') {
            return $this->rechargeWriteGuard($request);
        }

        if ($this->wantsJson($request)) {
            $payload = $this->merchantRechargesPayload($request, (int)$merchant['id']);
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
                'catalog' => $payload['catalog'],
                'write_actions' => $payload['write_actions'],
                'migration_guard' => $this->migrationGuardFromPayload($payload),
            ], JSON_UNESCAPED_UNICODE);
        }

        return $this->merchantSpaRedirectForCurrentRequest($request);
    }

    public function doPay(Request $request): Response
    {
        return $this->rechargeWriteGuard($request);
    }

    public function cdkPay(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantJson(401, 'merchant login is required', 401);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->merchantJson(201, 'merchant is frozen', 403);
        }

        if (!$this->cdkRechargeFeatureEnabled()) {
            return json([
                'code' => 202,
                'msg' => '卡密充值功能未开启',
                'message' => '卡密充值功能未开启',
            ], JSON_UNESCAPED_UNICODE)->withStatus(403);
        }

        $payload = $this->requestPayload($request);
        $cdkCode = $this->sanitizeMerchantInput($payload['cdk'] ?? $payload['code'] ?? '');
        if ($cdkCode === '') {
            return $this->merchantValidationError('请输入卡券兑换码');
        }

        try {
            $result = Db::transaction(function () use ($merchant, $cdkCode): array {
                $merchantId = (int)($merchant['id'] ?? 0);
                $merchantRow = Db::table(BusinessTable::user())
                    ->where('id', $merchantId)
                    ->lockForUpdate()
                    ->first();
                if (!$merchantRow) {
                    throw new \InvalidArgumentException('商户不存在');
                }

                $cdkRow = Db::table(BusinessTable::cdk())
                    ->where('code', $cdkCode)
                    ->lockForUpdate()
                    ->first();
                if (!$cdkRow) {
                    throw new \InvalidArgumentException('卡券兑换码无效');
                }

                $cdk = (array)$cdkRow;
                if ((int)($cdk['status'] ?? 0) === 1) {
                    throw new \InvalidArgumentException('该卡券已被使用');
                }

                $currentMerchant = (array)$merchantRow;
                $now = date('Y-m-d H:i:s');
                $reward = [
                    'cdk_id' => (int)($cdk['id'] ?? 0),
                    'code' => (string)($cdk['code'] ?? ''),
                    'type' => (int)($cdk['type'] ?? 0),
                ];

                switch ((int)($cdk['type'] ?? 0)) {
                    case 1:
                        $amount = round((float)($cdk['value'] ?? 0), 2);
                        if ($amount <= 0) {
                            throw new \InvalidArgumentException('卡券金额无效');
                        }

                        $before = round((float)($currentMerchant['money'] ?? 0), 2);
                        $after = round($before + $amount, 2);

                        Db::table(BusinessTable::user())
                            ->where('id', $merchantId)
                            ->update([
                                'money' => number_format($after, 2, '.', ''),
                            ]);

                        Db::table('money_log')->insert([
                            'user_id' => $merchantId,
                            'type' => null,
                            'money' => number_format($amount, 2, '.', ''),
                            'beforemoney' => number_format($before, 2, '.', ''),
                            'after' => number_format($after, 2, '.', ''),
                            'memo' => 'CDK余额充值',
                            'create_time' => $now,
                        ]);

                        $reward += [
                            'reward_type' => 'balance',
                            'amount' => number_format($amount, 2, '.', ''),
                            'merchant_balance' => number_format($after, 2, '.', ''),
                        ];
                        break;

                    case 2:
                        $vipId = (int)($cdk['value'] ?? 0);
                        $vipRow = Db::table(BusinessTable::vip())
                            ->where('id', $vipId)
                            ->lockForUpdate()
                            ->first();
                        if (!$vipRow) {
                            throw new \InvalidArgumentException('卡券关联会员套餐不存在');
                        }

                        $vip = (array)$vipRow;
                        $vipDays = max(0, (int)($vip['viptime'] ?? 0));
                        $vipTime = $vipDays > 0
                            ? date('Y-m-d H:i:s', strtotime('+ ' . $vipDays . ' day'))
                            : null;
                        $feeRate = trim((string)($vip['feilv'] ?? ''));

                        Db::table(BusinessTable::user())
                            ->where('id', $merchantId)
                            ->update([
                                'vip_id' => $vipId,
                                'vip_time' => $vipTime,
                                'feilv' => $feeRate === '' ? null : $feeRate,
                            ]);

                        $reward += [
                            'reward_type' => 'vip',
                            'vip_id' => $vipId,
                            'vip_name' => trim((string)($vip['name'] ?? '')),
                            'vip_days' => $vipDays,
                            'vip_time' => $vipTime,
                            'fee_rate' => $feeRate,
                        ];
                        break;

                    default:
                        throw new \InvalidArgumentException('暂不支持该卡券类型');
                }

                Db::table(BusinessTable::cdk())
                    ->where('id', (int)($cdk['id'] ?? 0))
                    ->update([
                        'status' => 1,
                    ]);

                return $reward;
            });
        } catch (\InvalidArgumentException $exception) {
            return $this->merchantValidationError($exception->getMessage());
        }

        return json([
            'code' => 200,
            'msg' => '卡券兑换成功',
            'message' => '卡券兑换成功',
            'data' => $result,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function setFunction(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantJson(401, 'merchant login is required', 401);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->merchantJson(201, 'merchant is frozen', 403);
        }

        $payload = $this->requestPayload($request);
        $orderId = (int)($payload['id'] ?? $request->get('id', 0));
        if ($orderId <= 0) {
            return $this->merchantValidationError('订单编号不能为空');
        }

        $action = strtolower(trim((string)($payload['type'] ?? $payload['action'] ?? $request->get('type', 'reback'))));
        if (!in_array($action, ['reback', 'callback_replay'], true)) {
            return json([
                'code' => 202,
                'msg' => '当前订单页已支持回调重放，状态重置暂未开放。',
                'message' => '当前订单页已支持回调重放，状态重置暂未开放。',
            ], JSON_UNESCAPED_UNICODE)->withStatus(405);
        }

        $orderRow = $this->merchantOrderQuery((int)($merchant['id'] ?? 0))
            ->where('orders.id', $orderId)
            ->first();
        if (!$orderRow) {
            return $this->merchantValidationError('订单不存在');
        }

        $order = (array)$orderRow;
        if ((int)($order['status'] ?? 0) !== 1) {
            return $this->merchantValidationError('仅已支付订单支持回调重放');
        }

        $notifyUrl = trim((string)($order['notify_url'] ?? ''));
        if ($notifyUrl === '') {
            return $this->merchantValidationError('当前订单未配置通知地址');
        }

        $callbackTask = (new OrderCallbackTaskService())->enqueueForSettledOrder($order, $merchant, [
            'scene' => 'merchant_replay',
            'force_new' => true,
        ]);
        $callbackUrls = [
            'notify' => trim((string)($callbackTask['callback_url'] ?? $callbackTask['notify_url'] ?? '')),
        ];
        $callbackResponse = [
            'ok' => true,
            'status' => (int)($callbackTask['http_status'] ?? 202),
            'body' => trim((string)($callbackTask['memo'] ?? 'callback task queued')),
        ];
        if (empty($callbackResponse['ok'])) {
            return json([
                'code' => 201,
                'msg' => '回调请求发送失败，请稍后重试',
                'message' => '回调请求发送失败，请稍后重试',
                'data' => [
                    'callback_url' => (string)($callbackUrls['notify'] ?? ''),
                    'callback_response' => $callbackResponse,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)->withStatus(502);
        }

        $memo = $this->merchantOrderCallbackMemo($callbackResponse);
        Db::table(BusinessTable::order())
            ->where('id', $orderId)
            ->where('user_id', (int)($merchant['id'] ?? 0))
            ->update([
                'api_memo' => $memo,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        $updatedOrder = $this->merchantOrderQuery((int)($merchant['id'] ?? 0))
            ->where('orders.id', $orderId)
            ->first();

        return json([
            'code' => 200,
            'msg' => '订单回调已重放',
            'message' => '订单回调已重放',
            'data' => [
                'order' => $updatedOrder ? $this->formatMerchantOrder((array)$updatedOrder, true) : ['id' => $orderId],
                'callback_url' => (string)($callbackUrls['notify'] ?? ''),
                'callback_response' => $callbackResponse,
                'callback_task' => $callbackTask,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function vip(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->jsonOrHtml(
                $request,
                ['code' => 201, 'msg' => 'merchant is frozen', 'message' => 'merchant is frozen'],
                $this->frozenPage($merchant),
                403
            );
        }

        if (strtoupper($request->method()) !== 'GET') {
            return $this->vipPurchase($request, $merchant);
        }

        if ($this->wantsJson($request)) {
            $payload = $this->merchantVipPayload($request, $merchant);
            return json([
                'code' => 0,
                'msg' => '成功',
                'message' => '成功',
                'data' => $payload['records'],
                'records' => $payload['records'],
                'current_vip' => $payload['current_vip'],
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
                'migration_guard' => $this->migrationGuardFromPayload($payload),
            ], JSON_UNESCAPED_UNICODE);
        }

        return $this->merchantSpaRedirectForCurrentRequest($request);
    }

    private function vipPurchase(Request $request, array $merchant): Response
    {
        $payload = $this->requestPayload($request);
        $vipId = (int)($payload['tcid'] ?? $payload['vip_id'] ?? $payload['id'] ?? 0);
        if ($vipId <= 0) {
            return $this->merchantValidationError('vip package is required');
        }

        $config = SystemConfig::all();

        try {
            $result = Db::transaction(function () use ($merchant, $vipId, $config): array {
                $merchantId = (int)($merchant['id'] ?? 0);

                $merchantRow = Db::table(BusinessTable::user())
                    ->select('id', 'money', 'vip_id', 'vip_time', 'feilv', 'superior_id')
                    ->where('id', $merchantId)
                    ->lockForUpdate()
                    ->first();
                if (!$merchantRow) {
                    throw new \InvalidArgumentException('merchant login is required');
                }

                $vipRow = Db::table(BusinessTable::vip())
                    ->where('id', $vipId)
                    ->where('status', 1)
                    ->whereNull('delete_time')
                    ->lockForUpdate()
                    ->first();
                if (!$vipRow) {
                    throw new \InvalidArgumentException('vip package not found');
                }

                $currentMerchant = (array)$merchantRow;
                $vip = (array)$vipRow;
                $amount = round((float)($vip['money'] ?? 0), 2);
                $before = round((float)($currentMerchant['money'] ?? 0), 2);

                if ($before < $amount) {
                    throw new \DomainException('merchant balance is insufficient for vip purchase');
                }

                $after = round($before - $amount, 2);
                $now = date('Y-m-d H:i:s');
                $feeRate = trim((string)($vip['feilv'] ?? ''));
                $vipTime = $this->resolveMerchantVipPurchaseTime($currentMerchant, $vip);

                Db::table(BusinessTable::user())
                    ->where('id', $merchantId)
                    ->update([
                        'money' => number_format($after, 2, '.', ''),
                        'vip_id' => (int)($vip['id'] ?? 0),
                        'vip_time' => $vipTime,
                        'feilv' => $feeRate === '' ? null : $feeRate,
                    ]);

                Db::table('money_log')->insert([
                    'user_id' => $merchantId,
                    'type' => null,
                    'money' => number_format(-$amount, 2, '.', ''),
                    'beforemoney' => number_format($before, 2, '.', ''),
                    'after' => number_format($after, 2, '.', ''),
                    'memo' => '购买套餐扣款',
                    'create_time' => $now,
                ]);

                $this->settleMerchantVipAffiliateRebate($currentMerchant, $amount, $config, $now);

                return [
                    'vip' => $vip,
                    'amount' => number_format($amount, 2, '.', ''),
                    'balance' => number_format($after, 2, '.', ''),
                    'vip_time' => $vipTime,
                ];
            });
        } catch (\DomainException $exception) {
            return $this->merchantJson(202, $exception->getMessage(), 200);
        } catch (\InvalidArgumentException $exception) {
            return $this->merchantValidationError($exception->getMessage());
        }

        $freshMerchant = $this->merchantById((int)($merchant['id'] ?? 0)) ?? $merchant;
        $currentVip = $this->merchantCurrentVip($freshMerchant);
        $vipPayload = $this->formatMerchantVip((array)$result['vip'], $currentVip);

        return $this->merchantJson(200, 'merchant vip purchase completed successfully', 200, [
            'amount' => (string)($result['amount'] ?? '0.00'),
            'balance_display' => $this->money($freshMerchant['money'] ?? 0),
            'current_vip' => $currentVip,
            'vip' => $vipPayload,
        ]);
    }

    private function resolveMerchantVipPurchaseTime(array $merchant, array $vip): ?string
    {
        $vipDays = max(0, (int)($vip['viptime'] ?? 0));
        if ($vipDays <= 0) {
            return null;
        }

        $currentVipTime = $this->nullableString($merchant['vip_time'] ?? null);
        $currentFeeRate = trim((string)($merchant['feilv'] ?? ''));
        $nextFeeRate = trim((string)($vip['feilv'] ?? ''));

        if (
            $currentVipTime !== null
            && strtotime($currentVipTime) !== false
            && strtotime($currentVipTime) >= time()
            && $currentFeeRate === $nextFeeRate
        ) {
            return date('Y-m-d H:i:s', strtotime($currentVipTime . ' + ' . $vipDays . ' day'));
        }

        return date('Y-m-d H:i:s', strtotime('+ ' . $vipDays . ' day'));
    }

    private function settleMerchantVipAffiliateRebate(array $merchant, float $amount, array $config, string $now): void
    {
        if ((int)($config['is_aff'] ?? 0) !== 1) {
            return;
        }

        if ((int)($config['aff_type'] ?? 0) !== 1) {
            return;
        }

        $superiorId = (int)($merchant['superior_id'] ?? 0);
        if ($superiorId <= 0) {
            return;
        }

        $percentage = (float)($config['aff_percentage'] ?? 0);
        if ($percentage <= 0) {
            return;
        }

        $rebate = round($amount * $percentage, 2);
        if ($rebate <= 0) {
            return;
        }

        $superiorRow = Db::table(BusinessTable::user())
            ->where('id', $superiorId)
            ->lockForUpdate()
            ->first();
        if (!$superiorRow) {
            return;
        }

        $superior = (array)$superiorRow;
        $before = round((float)($superior['money'] ?? 0), 2);
        $after = round($before + $rebate, 2);

        Db::table(BusinessTable::user())
            ->where('id', $superiorId)
            ->update([
                'money' => number_format($after, 2, '.', ''),
            ]);

        Db::table('money_log')->insert([
            'user_id' => $superiorId,
            'type' => null,
            'money' => number_format($rebate, 2, '.', ''),
            'beforemoney' => number_format($before, 2, '.', ''),
            'after' => number_format($after, 2, '.', ''),
            'memo' => '下级购买会员套餐返利',
            'create_time' => $now,
        ]);
    }

    public function api(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->jsonOrHtml(
                $request,
                ['code' => 201, 'msg' => 'merchant is frozen', 'message' => 'merchant is frozen'],
                $this->frozenPage($merchant),
                403
            );
        }

        if (strtoupper($request->method()) !== 'GET') {
            return $this->blockedWriteResponse('api_key');
        }

        $payload = MerchantPortalAccountSupport::apiPayload(
            $merchant,
            MerchantPortalAccountSupport::basic((int)($merchant['id'] ?? 0)),
            $this->requestOrigin($request),
            $this->gatewayLines($request),
            fn (string $secret): ?string => $this->maskSecret($secret),
            fn (mixed $value): ?string => $this->nullableString($value),
            fn (int $method): string => $this->timeoutMethodLabel($method)
        );
        if ($this->wantsJson($request)) {
            return json([
                'code' => 0,
                'msg' => '成功',
                'message' => '成功',
                'data' => $payload,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $this->merchantSpaRedirectForCurrentRequest($request);
    }

    public function apiSecret(Request $request): Response
    {
        $merchant = $this->apiKeyMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $payload = $this->requestPayload($request);
        $requestedType = strtolower(trim((string)($payload['key_type'] ?? '')));
        $keyType = match ($requestedType) {
            'sign', 'sign_key', 'user_key', 'merchant_sign_key' => 'sign_key',
            'app', 'appkey', 'app_key', 'merchant_app_key', 'communication', 'communication_key' => 'appkey',
            default => '',
        };

        if ($keyType === '') {
            return $this->merchantValidationError('密钥类型无效');
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $basic = MerchantPortalAccountSupport::basic($merchantId);
        $secret = $keyType === 'sign_key'
            ? trim((string)($merchant['user_key'] ?? ''))
            : trim((string)($basic['appkey'] ?? ''));
        $label = $keyType === 'sign_key' ? '商户密钥' : '通讯密钥';

        if ($secret === '') {
            return $this->merchantValidationError($label . '未配置');
        }

        return $this->merchantJson(200, $label . '读取成功', 200, [
            'key_type' => $keyType,
            'key' => $secret,
            'key_masked' => $this->maskSecret($secret),
            'key_length' => strlen($secret),
        ]);
    }

    public function apiQrcode(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantJson(401, 'merchant login is required', 401);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->merchantJson(201, 'merchant is frozen', 403);
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $basic = MerchantPortalAccountSupport::basic($merchantId);
        $appkey = trim((string)($basic['appkey'] ?? ''));
        $appkeyGenerated = false;
        if ($appkey === '') {
            $bootstrapAppkey = filter_var($request->post('bootstrap_appkey', false), FILTER_VALIDATE_BOOLEAN);
            if (!$bootstrapAppkey) {
                return $this->merchantJson(422, '通讯密钥未配置，请先生成后再展示商户二维码', 422);
            }

            $key = $this->generateMerchantSecret(32);
            MerchantPortalAccountSupport::ensureBasicRecord($merchantId, $key);
            Db::table(BusinessTable::userBasic())
                ->where('user_id', $merchantId)
                ->update(['appkey' => $key]);

            $appkey = $key;
            $appkeyGenerated = true;
        }

        $gatewayLines = $this->gatewayLines($request);
        $selectedLine = $this->resolveMerchantApiGatewayLine($request, $gatewayLines);
        if ($selectedLine === null) {
            return $this->merchantJson(422, '所选线路无效，请刷新页面后重试', 422);
        }

        $contentBase64 = $this->merchantApiQrPayload($selectedLine, $merchantId, $appkey);
        $qrcodeUrl = $this->merchantQrCodeUrl($request, $contentBase64, '350x350');

        return $this->merchantJson(200, '商户对接二维码已生成', 200, [
            'merchant_id' => $merchantId,
            'selected_line' => $selectedLine,
            'key_type' => 'appkey',
            'key_masked' => $this->maskSecret($appkey),
            'content_base64' => $contentBase64,
            'content_length' => strlen($contentBase64),
            'qrcode' => $qrcodeUrl,
            'qrcode_url' => $qrcodeUrl,
            'appkey_generated' => $appkeyGenerated,
            'raw_secret_exposed' => false,
        ]);
    }

    public function channelPool(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->jsonOrHtml(
                $request,
                ['code' => 201, 'msg' => 'merchant is frozen', 'message' => 'merchant is frozen'],
                $this->frozenPage($merchant),
                403
            );
        }

        return $this->merchantSpaRedirect($request, '/merchant/pools');
    }

    public function ticket(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->jsonOrHtml(
                $request,
                ['code' => 201, 'msg' => 'merchant is frozen', 'message' => 'merchant is frozen'],
                $this->frozenPage($merchant),
                403
            );
        }

        if (strtoupper($request->method()) === 'GET' && $this->wantsMerchantSpaPage($request)) {
            return $this->merchantSpaRedirectForCurrentRequest($request);
        }

        if (!$this->ticketFeatureEnabled()) {
            if ($this->wantsJson($request)) {
                return json([
                    'code' => 202,
                    'msg' => '工单功能未开启',
                    'message' => '工单功能未开启',
                ], JSON_UNESCAPED_UNICODE)->withStatus(403);
            }

            return $this->htmlResponse($this->featureDisabledPage(
                $request,
                $merchant,
                '工单中心',
                '当前系统尚未开启工单功能。',
                '工单开关关闭时，商户端不会开放工单创建、删除与跟进入口。如需开放，请先在系统配置中启用工单功能。'
            ));
        }

        if (strtoupper($request->method()) !== 'GET') {
            return $this->blockedWriteResponse('ticket');
        }

        if ($this->wantsJson($request)) {
            $payload = $this->merchantTicketsPayload($request, (int)$merchant['id']);
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
                'categories' => $payload['categories'],
                'write_actions' => $payload['write_actions'],
            ], JSON_UNESCAPED_UNICODE);
        }

        return $this->merchantSpaRedirectForCurrentRequest($request);
    }

    public function addTicket(Request $request): Response
    {
        $merchant = $this->ticketWriteGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $payload = $this->requestPayload($request);
        $title = $this->sanitizeMerchantInput($payload['title'] ?? '');
        $content = $this->sanitizeMerchantInput($payload['content'] ?? '');
        $type = (int)($payload['type'] ?? $payload['category_id'] ?? 0);

        if ($title === '') {
            return $this->merchantValidationError('ticket title is required');
        }
        if ($this->stringLength($title) > 255) {
            return $this->merchantValidationError('ticket title is too long');
        }
        if ($content === '') {
            return $this->merchantValidationError('ticket content is required');
        }

        $categories = $this->merchantTicketCategories();
        $categoryIds = array_map(static fn (array $item): int => (int)($item['id'] ?? 0), $categories);
        if ($categoryIds !== [] && !in_array($type, $categoryIds, true)) {
            return $this->merchantValidationError('ticket category is invalid or disabled');
        }

        $now = date('Y-m-d H:i:s');
        $ticketData = [
            'type' => $type,
            'title' => $title,
            'content' => $content,
            'reply_content' => null,
            'creator_id' => (int)($merchant['id'] ?? 0),
            'assignee_id' => null,
            'create_time' => $now,
            'update_time' => $now,
            'reply_time' => null,
            'status' => 0,
        ];

        Db::table(BusinessTable::ticket())->insert($ticketData);
        $ticketId = (int)Db::getPdo()->lastInsertId();
        $created = $this->merchantTicketQuery((int)($merchant['id'] ?? 0))
            ->where('ticket.id', $ticketId)
            ->first();

        return json([
            'code' => 200,
            'msg' => '工单创建成功',
            'message' => '工单创建成功',
            'data' => $created ? $this->formatMerchantTicket((array)$created) : ['id' => $ticketId],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function delTicket(Request $request): Response
    {
        $merchant = $this->ticketWriteGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $payload = $this->requestPayload($request);
        $id = (int)($payload['id'] ?? $request->get('id', 0));
        if ($id <= 0) {
            return $this->merchantValidationError('ticket id is required');
        }

        $query = Db::table(BusinessTable::ticket())
            ->where('id', $id)
            ->where('creator_id', (int)($merchant['id'] ?? 0));

        $ticket = $query->first();
        if (!$ticket) {
            return $this->merchantValidationError('ticket not found');
        }

        $query->delete();

        return json([
            'code' => 200,
            'msg' => '工单删除成功',
            'message' => '工单删除成功',
            'data' => ['id' => $id],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function domain(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->jsonOrHtml(
                $request,
                ['code' => 201, 'msg' => 'merchant is frozen', 'message' => 'merchant is frozen'],
                $this->frozenPage($merchant),
                403
            );
        }

        if (strtoupper($request->method()) === 'GET' && $this->wantsMerchantSpaPage($request)) {
            return $this->merchantSpaRedirectForCurrentRequest($request);
        }

        if (!$this->domainFeatureEnabled()) {
            if ($this->wantsJson($request)) {
                return json([
                    'code' => 202,
                    'msg' => '域名功能未开启',
                    'message' => '域名功能未开启',
                ], JSON_UNESCAPED_UNICODE)->withStatus(403);
            }

            return $this->htmlResponse($this->featureDisabledPage(
                $request,
                $merchant,
                '域名管理',
                '当前系统尚未开启域名功能。',
                '域名审核开关关闭时，商户端不会开放域名提交、修改与删除入口。如需开放，请先在系统配置中启用域名功能。'
            ));
        }

        if (strtoupper($request->method()) !== 'GET') {
            return $this->blockedWriteResponse('domain');
        }

        if ($this->wantsJson($request)) {
            $payload = $this->merchantDomainsPayload($request, (int)$merchant['id']);
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
                'migration_guard' => $this->migrationGuardFromPayload($payload),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $this->merchantSpaRedirectForCurrentRequest($request);
    }

    public function addDomain(Request $request): Response
    {
        $merchant = $this->domainWriteGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $validated = $this->validateMerchantDomainPayload($request, (int)($merchant['id'] ?? 0));
        if ($validated instanceof Response) {
            return $validated;
        }

        if (!$this->merchantDomainSubmissionAllowed((int)($merchant['id'] ?? 0))) {
            return $this->merchantValidationError('已达到当日域名提交上限');
        }

        $data = $this->prepareMerchantDomainData($validated, (int)($merchant['id'] ?? 0));
        $data['create_time'] = date('Y-m-d H:i:s');
        Db::table(BusinessTable::domain())->insert($data);
        $domainId = (int)Db::getPdo()->lastInsertId();
        $created = $this->merchantDomainQuery((int)($merchant['id'] ?? 0))
            ->where('domain.id', $domainId)
            ->first();

        return json([
            'code' => 200,
            'msg' => '域名提交成功',
            'message' => '域名提交成功',
            'data' => $created ? $this->formatMerchantDomain((array)$created) : ['id' => $domainId],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function editDomain(Request $request): Response
    {
        $merchant = $this->domainWriteGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $payload = $this->requestPayload($request);
        $id = (int)($payload['id'] ?? $request->get('id', 0));
        if ($id <= 0) {
            return $this->merchantValidationError('请输入域名 ID');
        }

        $existing = $this->merchantDomainQuery((int)($merchant['id'] ?? 0))
            ->where('domain.id', $id)
            ->first();
        if (!$existing) {
            return $this->merchantValidationError('域名不存在');
        }

        $validated = $this->validateMerchantDomainPayload($request, (int)($merchant['id'] ?? 0));
        if ($validated instanceof Response) {
            return $validated;
        }

        $updates = $this->prepareMerchantDomainData($validated, (int)($merchant['id'] ?? 0));
        Db::table(BusinessTable::domain())
            ->where('id', $id)
            ->where('user_id', (int)($merchant['id'] ?? 0))
            ->whereNull('delete_time')
            ->update($updates);

        $updated = $this->merchantDomainQuery((int)($merchant['id'] ?? 0))
            ->where('domain.id', $id)
            ->first();

        return json([
            'code' => 200,
            'msg' => '域名更新成功',
            'message' => '域名更新成功',
            'data' => $updated ? $this->formatMerchantDomain((array)$updated) : ['id' => $id],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function delDomain(Request $request): Response
    {
        $merchant = $this->domainWriteGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $payload = $this->requestPayload($request);
        $id = (int)($payload['id'] ?? $request->get('id', 0));
        if ($id <= 0) {
            return $this->merchantValidationError('请输入域名 ID');
        }

        $existing = $this->merchantDomainQuery((int)($merchant['id'] ?? 0))
            ->where('domain.id', $id)
            ->first();
        if (!$existing) {
            return $this->merchantValidationError('域名不存在');
        }

        Db::table(BusinessTable::domain())
            ->where('id', $id)
            ->where('user_id', (int)($merchant['id'] ?? 0))
            ->whereNull('delete_time')
            ->update(['delete_time' => date('Y-m-d H:i:s')]);

        return json([
            'code' => 200,
            'msg' => '域名删除成功',
            'message' => '域名删除成功',
            'data' => ['id' => $id],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function loginLog(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->jsonOrHtml(
                $request,
                ['code' => 201, 'msg' => 'merchant is frozen', 'message' => 'merchant is frozen'],
                $this->frozenPage($merchant),
                403
            );
        }

        if ($this->wantsJson($request)) {
            $payload = $this->merchantLoginLogsPayload($request, (int)$merchant['id']);
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
                'migration_guard' => $this->migrationGuardFromPayload($payload),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $this->merchantSpaRedirectForCurrentRequest($request);
    }

    public function orderDetails(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantLoginRequiredResponse($request);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->merchantJson(201, 'merchant is frozen', 403);
        }

        $id = (int)($request->get('id', $request->post('id', 0)));
        if ($id <= 0) {
            return $this->merchantJson(201, 'order id is required', 200);
        }

        $row = $this->merchantOrderQuery((int)$merchant['id'])
            ->where('orders.id', $id)
            ->first();

        if (!$row) {
            return $this->merchantJson(201, 'order not found', 200);
        }

        $detail = $this->formatMerchantOrder((array)$row, true);
        $detail['type_label_localized'] = (string)($detail['type_label'] ?? '');
        $detail['status_label_localized'] = (string)($detail['status_label'] ?? '');
        $detail['type_label'] = $this->merchantOrderCompatibilityTypeLabel((string)($detail['type'] ?? ''));
        $detail['status_label'] = $this->merchantOrderCompatibilityStatusLabel((int)($detail['status'] ?? 0));

        return json([
            'code' => 200,
            'msg' => '成功',
            'message' => '成功',
            'dataArray' => $detail,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function loginSubmit(Request $request): Response
    {
        $payload = $this->requestPayload($request);
        $username = trim((string)($payload['username'] ?? $payload['userName'] ?? ''));
        $password = trim((string)($payload['password'] ?? ''));

        if ($username === '' || $password === '') {
            return $this->loginJson(201, 'username and password are required');
        }

        $throttleError = $this->merchantLoginThrottleError($request, $username);
        if ($throttleError instanceof Response) {
            return $throttleError;
        }

        $config = SystemConfig::all();

        $merchant = $this->findMerchantForLogin($username, $password);
        if ($merchant === null) {
            return $this->loginJson(201, 'username or password is incorrect');
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            $reason = trim((string)($merchant['frozen_reason'] ?? ''));
            return $this->loginJson(201, $reason !== '' ? $reason : 'merchant is frozen');
        }

        if ($this->requiresGoogleLogin($merchant, $config)) {
            return $this->loginJson(202, '当前账号需先完成谷歌验证后才可继续登录');
        }

        $newToken = $this->rotateMerchantToken((int)$merchant['id']);
        $this->recordMerchantFrontLog(
            (int)($merchant['id'] ?? 0),
            '/api/merchant/login',
            1,
            '商户登录成功',
            $request
        );

        return $this->loginJson(200, 'login success', [
            'url' => $this->withHashPath($this->merchantFrontendBaseUrl($request), '/merchant/dashboard'),
            'token' => $newToken,
            'merchant_id' => (int)$merchant['id'],
            'merchant_username' => (string)$merchant['username'],
        ])->cookie('front_token', $newToken, self::COOKIE_TTL, '/')
            ->cookie('sign', '', 0, '/')
            ->cookie('PHPSESSID', '', 0, '/')
            ->cookie(self::MERCHANT_CONNECTION_VERIFY_COOKIE, '', 0, '/')
            ->cookie(self::GOOGLE_AUTH_BIND_COOKIE, '', 0, '/');
    }

    private function merchantFromRequest(Request $request): ?array
    {
        return MerchantFrontSession::current($request);
    }

    private function merchantById(int $merchantId): ?array
    {
        $row = Db::table(BusinessTable::user('merchant'))
            ->leftJoin(BusinessTable::vip('vip'), 'merchant.vip_id', '=', 'vip.id')
            ->select(
                'merchant.id',
                'merchant.username',
                'merchant.email',
                'merchant.mobile',
                'merchant.wxpusher_uid',
                'merchant.tg_chat_id',
                'merchant.is_bindqq',
                'merchant.qq_sid',
                'merchant.is_bindwx',
                'merchant.wx_sid',
                'merchant.googlekey',
                'merchant.is_realName',
                'merchant.name',
                'merchant.idCard',
                'merchant.superior_id',
                'merchant.money',
                'merchant.vip_id',
                'merchant.vip_time',
                'merchant.feilv',
                'merchant.user_key',
                'merchant.create_time',
                'merchant.is_frozen',
                'merchant.frozen_reason',
                'vip.name as vip_name'
            )
            ->where('merchant.id', $merchantId)
            ->first();

        return $row ? (array)$row : null;
    }

    private function merchantOrdersPayload(Request $request, int $merchantId, ?int $defaultSize = null): array
    {
        $current = max(1, (int)$request->get('current', $request->get('page', 1)));
        $fallbackSize = $defaultSize ?? SystemConfig::int('orderDisplay', 10);
        $size = max(1, min((int)$request->get('size', $request->get('limit', $fallbackSize)), 100));

        $query = $this->merchantOrderQuery($merchantId);
        $this->applyMerchantOrderFilters($query, $request);

        $summaryQuery = $this->merchantOrderBaseQuery($merchantId);
        $this->applyMerchantOrderFilters($summaryQuery, $request);

        $total = (int)(clone $query)->count('orders.id');
        $rows = $query
            ->orderByDesc('orders.id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        return [
            'records' => array_map(fn ($row): array => $this->formatMerchantOrder((array)$row), $rows),
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => $this->merchantOrderSummary($summaryQuery),
            'write_actions' => [
                'callback_replay' => true,
            ],
            'migration_guard' => [
                'read_only' => false,
                'blocked_actions' => [],
            ],
        ];
    }

    private function merchantMoneyLogsPayload(Request $request, int $merchantId, ?int $defaultSize = null): array
    {
        $current = max(1, (int)$request->get('current', $request->get('page', 1)));
        $fallbackSize = $defaultSize ?? 10;
        $size = max(1, min((int)$request->get('size', $request->get('limit', $fallbackSize)), 100));

        $query = $this->merchantMoneyLogQuery($merchantId);
        $this->applyMerchantMoneyLogFilters($query, $request);

        $summaryQuery = $this->merchantMoneyLogBaseQuery($merchantId);
        $this->applyMerchantMoneyLogFilters($summaryQuery, $request);

        $total = (int)(clone $query)->count('money_log.id');
        $rows = $query
            ->orderByDesc('money_log.id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        return [
            'records' => array_map(fn ($row): array => $this->formatMerchantMoneyLog((array)$row), $rows),
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => $this->merchantMoneyLogSummary($summaryQuery),
        ];
    }

    private function merchantRechargesPayload(Request $request, int $merchantId, ?int $defaultSize = null): array
    {
        $current = max(1, (int)$request->get('current', $request->get('page', 1)));
        $fallbackSize = $defaultSize ?? 10;
        $size = max(1, min((int)$request->get('size', $request->get('limit', $fallbackSize)), 100));
        $catalog = (new MerchantRechargeService())->catalog();
        $rechargeCreateEnabled = (int)($catalog['enabled_count'] ?? 0) > 0;
        $cdkRedeemEnabled = $this->cdkRechargeFeatureEnabled();

        $query = $this->merchantRechargeQuery($merchantId);
        $this->applyMerchantRechargeFilters($query, $request);

        $summaryQuery = $this->merchantRechargeBaseQuery($merchantId);
        $this->applyMerchantRechargeFilters($summaryQuery, $request);

        $total = (int)(clone $query)->count('recharge.id');
        $rows = $query
            ->orderByDesc('recharge.id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        return [
            'records' => array_map(fn ($row): array => $this->formatMerchantRecharge((array)$row), $rows),
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => $this->merchantRechargeSummary($summaryQuery),
            'catalog' => $catalog,
            'write_actions' => [
                'recharge_create' => $rechargeCreateEnabled,
                'payment_handoff' => $rechargeCreateEnabled,
                'cdk_redeem' => $cdkRedeemEnabled,
            ],
            'migration_guard' => [
                'read_only' => !$rechargeCreateEnabled && !$cdkRedeemEnabled,
                'blocked_actions' => array_values(array_filter([
                    $rechargeCreateEnabled ? null : 'recharge_create',
                    $rechargeCreateEnabled ? null : 'payment_handoff',
                    $cdkRedeemEnabled ? null : 'cdk_redeem',
                ])),
            ],
        ];
    }

    private function merchantVipPayload(Request $request, array $merchant, ?int $defaultSize = null): array
    {
        $current = max(1, (int)$request->get('current', $request->get('page', 1)));
        $fallbackSize = $defaultSize ?? 12;
        $size = max(1, min((int)$request->get('size', $request->get('limit', $fallbackSize)), 100));

        $query = $this->merchantVipQuery();
        $this->applyMerchantVipFilters($query, $request);

        $summaryQuery = $this->merchantVipBaseQuery();
        $this->applyMerchantVipFilters($summaryQuery, $request);

        $total = (int)(clone $query)->count('id');
        $rows = $query
            ->orderBy('sort')
            ->orderByDesc('id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        $currentVip = $this->merchantCurrentVip($merchant);

        return [
            'records' => array_map(
                fn ($row): array => $this->formatMerchantVip((array)$row, $currentVip),
                $rows
            ),
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'current_vip' => $currentVip,
            'summary' => $this->merchantVipSummary($summaryQuery, $currentVip),
            'write_actions' => [
                'purchase' => true,
            ],
            'migration_guard' => [
                'read_only' => false,
                'blocked_actions' => [],
            ],
        ];
    }

    private function merchantTicketsPayload(Request $request, int $merchantId, ?int $defaultSize = null): array
    {
        $current = max(1, (int)$request->get('current', $request->get('page', 1)));
        $fallbackSize = $defaultSize ?? 10;
        $size = max(1, min((int)$request->get('size', $request->get('limit', $fallbackSize)), 100));

        $query = $this->merchantTicketQuery($merchantId);
        $this->applyMerchantTicketFilters($query, $request);

        $summaryQuery = $this->merchantTicketBaseQuery($merchantId);
        $this->applyMerchantTicketFilters($summaryQuery, $request);

        $total = (int)(clone $query)->count('ticket.id');
        $rows = $query
            ->orderByDesc('ticket.id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        return [
            'records' => array_map(fn ($row): array => $this->formatMerchantTicket((array)$row), $rows),
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => $this->merchantTicketSummary($summaryQuery),
            'categories' => $this->merchantTicketCategories(),
            'write_actions' => [
                'create' => true,
                'delete' => true,
                'reply' => false,
            ],
        ];
    }

    private function merchantDomainsPayload(Request $request, int $merchantId, ?int $defaultSize = null): array
    {
        $current = max(1, (int)$request->get('current', $request->get('page', 1)));
        $fallbackSize = $defaultSize ?? 10;
        $size = max(1, min((int)$request->get('size', $request->get('limit', $fallbackSize)), 100));

        $query = $this->merchantDomainQuery($merchantId);
        $this->applyMerchantDomainFilters($query, $request);

        $summaryQuery = $this->merchantDomainBaseQuery($merchantId);
        $this->applyMerchantDomainFilters($summaryQuery, $request);

        $total = (int)(clone $query)->count('domain.id');
        $rows = $query
            ->orderByDesc('domain.id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        return [
            'records' => array_map(fn ($row): array => $this->formatMerchantDomain((array)$row), $rows),
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => $this->merchantDomainSummary($summaryQuery),
            'write_actions' => [
                'create' => true,
                'edit' => true,
                'delete' => true,
                'resubmit' => true,
            ],
            'migration_guard' => [
                'read_only' => false,
                'blocked_actions' => [],
            ],
        ];
    }

    private function merchantDashboardPayload(array $merchant): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $orderStats = $this->orderStats($merchantId);
        $channelStats = $this->channelStats($merchantId);

        return [
            'merchant_id' => $merchantId,
            'merchant_username' => trim((string)($merchant['username'] ?? '')),
            'display_name' => $this->merchantDisplay($merchant),
            'balance_display' => $this->money($merchant['money'] ?? 0),
            'fee_rate' => $this->feeRate($merchant['feilv'] ?? null),
            'vip_label' => $this->vipLabel($merchant),
            'create_time' => $this->nullableString($merchant['create_time'] ?? null),
            'summary' => [
                'order_count' => (int)($orderStats['order_count'] ?? 0),
                'paid_order_count' => (int)($orderStats['paid_order_count'] ?? 0),
                'paid_amount_display' => $this->money($orderStats['paid_amount'] ?? 0),
                'today_paid_amount_display' => $this->money($orderStats['today_paid_amount'] ?? 0),
                'account_count' => (int)($channelStats['account_count'] ?? 0),
                'upstream_count' => (int)($channelStats['upstream_count'] ?? 0),
                'last_order_time' => $this->nullableString($orderStats['last_order_time'] ?? null),
            ],
        ];
    }

    private function merchantProfilePayload(Request $request, array $merchant): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $basic = MerchantPortalAccountSupport::basic($merchantId);
        $currentVip = $this->merchantCurrentVip($merchant);

        return [
            'profile' => [
                'id' => $merchantId,
                'username' => trim((string)($merchant['username'] ?? '')),
                'display_name' => $this->merchantDisplay($merchant),
                'email' => $this->nullableString($merchant['email'] ?? null),
                'mobile' => $this->nullableString($merchant['mobile'] ?? null),
                'money' => round((float)($merchant['money'] ?? 0), 2),
                'money_display' => $this->money($merchant['money'] ?? 0),
                'fee_rate' => $this->feeRate($merchant['feilv'] ?? null),
                'create_time' => $this->nullableString($merchant['create_time'] ?? null),
                'vip_id' => (int)($merchant['vip_id'] ?? 0),
                'vip_label' => $this->vipLabel($merchant),
                'vip_time' => $this->nullableString($merchant['vip_time'] ?? null),
            ],
            'vip' => $currentVip,
            'api_settings' => [
                'timeout_url' => $this->nullableString($basic['timeout_url'] ?? null) ?? '/',
                'timeout_time' => (int)($basic['timeout_time'] ?? 0),
                'timeout_method' => (int)($basic['timeout_method'] ?? 0),
                'timeout_method_label' => $this->timeoutMethodLabel((int)($basic['timeout_method'] ?? 0)),
            ],
            'write_actions' => [
                'profile_update' => true,
                'mobile_update' => true,
                'email_update' => true,
            ],
            'migration_guard' => [
                'read_only' => false,
                'blocked_actions' => [],
            ],
        ];
    }

    private function merchantNotificationsPayload(array $merchant): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $basic = MerchantPortalAccountSupport::notificationBasic($merchantId, self::DEFAULT_VOICE_TIPS);
        $channels = $this->notificationChannels();
        $settings = [];
        foreach ($this->notificationSettingDefinitions() as $definition) {
            $field = (string)$definition['id'];
            $selected = $this->normalizeNotificationChannel($basic[$field] ?? 'close');
            $settings[] = [
                'id' => $field,
                'name' => (string)$definition['name'],
                'selected' => $selected,
                'selected_label' => $this->notificationChannelLabel($selected),
                'selected_available' => (bool)($channels[$selected]['available'] ?? false),
                'channels' => array_values(array_map(
                    fn (array $channel): array => [
                        'id' => (string)$channel['id'],
                        'label' => (string)$channel['label'],
                        'available' => (bool)$channel['available'],
                        'selected' => (string)$channel['id'] === $selected,
                    ],
                    $channels
                )),
            ];
        }

        return [
            'merchant_id' => $merchantId,
            'merchant_username' => trim((string)($merchant['username'] ?? '')),
            'settings' => $settings,
            'channels' => array_values($channels),
            'low_balance_threshold' => trim((string)($basic['money_tips'] ?? '0')),
            'console_notice' => $this->nullableString($basic['console_notity'] ?? null),
            'voice_tips' => [
                'enabled' => (int)($basic['is_voice_tips'] ?? 0) === 1,
                'template' => $this->nullableString($basic['voice_tips'] ?? null) ?? '尊敬的用户，您本次交易金额为[money]',
            ],
            'write_actions' => [
                'save_notifications' => true,
            ],
            'migration_guard' => [
                'read_only' => false,
                'blocked_actions' => [],
            ],
        ];
    }

    private function saveMerchantProfile(Request $request, array $merchant): Response
    {
        $payload = $this->requestPayload($request);
        $merchantId = (int)($merchant['id'] ?? 0);
        $updates = [];
        $touched = false;

        if (array_key_exists('email', $payload)) {
            $touched = true;
            $email = $this->sanitizeMerchantInput($payload['email']);
            if ($this->stringLength($email) > 50) {
                return $this->merchantValidationError('email is too long');
            }
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                return $this->merchantValidationError('email format is invalid');
            }
            if ($email !== '' && $this->merchantFieldExists('email', $email, $merchantId)) {
                return $this->merchantValidationError('email is already in use by another merchant');
            }
            $updates['email'] = $email;
        }

        if (array_key_exists('mobile', $payload)) {
            $touched = true;
            $mobile = $this->sanitizeMerchantInput($payload['mobile']);
            if ($this->stringLength($mobile) > 50) {
                return $this->merchantValidationError('mobile number is too long');
            }
            if ($mobile !== '' && !preg_match('/^1\d{10}$/', $mobile)) {
                return $this->merchantValidationError('mobile format is invalid');
            }
            if ($mobile !== '' && $this->merchantFieldExists('mobile', $mobile, $merchantId)) {
                return $this->merchantValidationError('mobile number is already in use by another merchant');
            }
            $updates['mobile'] = $mobile;
        }

        if (!$touched) {
            return $this->merchantValidationError('email or mobile is required');
        }

        if ($updates !== []) {
            Db::table(BusinessTable::user())
                ->where('id', $merchantId)
                ->update($updates);
            $merchant = array_merge($merchant, $updates);
        }

        return json([
            'code' => 1,
            'msg' => '商户资料更新成功',
            'message' => '商户资料更新成功',
            'data' => $this->merchantProfilePayload($request, $merchant),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function saveMerchantNotifications(Request $request, array $merchant): Response
    {
        $payload = $this->requestPayload($request);
        $merchantId = (int)($merchant['id'] ?? 0);
        $channels = $this->notificationChannels();
        $updates = [];
        $touched = false;

        foreach ($this->notificationSettingDefinitions() as $definition) {
            $field = (string)$definition['id'];
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $touched = true;
            $updates[$field] = $this->normalizeAvailableNotificationChannel($payload[$field], $channels);
        }

        if (array_key_exists('money_tips', $payload)) {
            $touched = true;
            $moneyTips = $this->sanitizeMerchantInput($payload['money_tips']);
            $moneyTips = $moneyTips === '' ? '0' : $moneyTips;
            if ($this->stringLength($moneyTips) > 50) {
                return $this->merchantValidationError('low balance threshold is too long');
            }
            if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $moneyTips)) {
                return $this->merchantValidationError('low balance threshold must be a non-negative amount with up to 2 decimals');
            }
            $updates['money_tips'] = $moneyTips;
        }

        if (array_key_exists('console_notity', $payload) || array_key_exists('console_notice', $payload)) {
            $touched = true;
            $consoleNotice = array_key_exists('console_notity', $payload) ? $payload['console_notity'] : $payload['console_notice'];
            $consoleNotice = $this->sanitizeMerchantInput($consoleNotice);
            if ($this->stringLength($consoleNotice) > 255) {
                return $this->merchantValidationError('console notice is too long');
            }
            $updates['console_notity'] = $consoleNotice === '' ? null : $consoleNotice;
        }

        if (array_key_exists('is_voice_tips', $payload)) {
            $touched = true;
            $updates['is_voice_tips'] = $this->normalizeBinaryFlag($payload['is_voice_tips']);
        }

        if (array_key_exists('voice_tips', $payload)) {
            $touched = true;
            $voiceTips = $this->sanitizeMerchantInput($payload['voice_tips']);
            $voiceTips = $voiceTips === '' ? self::DEFAULT_VOICE_TIPS : $voiceTips;
            if ($this->stringLength($voiceTips) > 255) {
                return $this->merchantValidationError('voice template is too long');
            }
            $updates['voice_tips'] = $voiceTips;
        }

        if (!$touched) {
            return $this->merchantValidationError('at least one notification setting is required');
        }

        if ((int)Db::table(BusinessTable::userBasic())->where('user_id', $merchantId)->count() > 0) {
            Db::table(BusinessTable::userBasic())
                ->where('user_id', $merchantId)
                ->update($updates);
        } else {
            Db::table(BusinessTable::userBasic())->insert(array_merge(['user_id' => $merchantId], $updates));
        }

        return json([
            'code' => 200,
            'msg' => '通知设置保存成功',
            'message' => '通知设置保存成功',
            'data' => $this->merchantNotificationsPayload($merchant),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function merchantSecurityPayload(Request $request, array $merchant): array
    {
        return $this->merchantSecurityPayloadV2($request, $merchant);
    }

    private function merchantSecurityPayloadV2(Request $request, array $merchant): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);

        return MerchantPortalSecuritySupport::payload(
            $merchant,
            SystemConfig::all(),
            $this->merchantLoginLogsPayload($request, $merchantId, 5),
            $this->readGoogleAuthBindTicket($request, $merchantId),
            MerchantPortalCancellationSupport::payload($merchant),
            fn (string $secret): ?string => $this->maskSecret($secret),
            fn (string $value): ?string => $this->maskIdentifier($value),
            fn (mixed $value): ?string => $this->nullableString($value)
        );
    }

    private function buildMerchantGoogleAuthPayload(
        array $merchant,
        array $config,
        bool $securityEnabled,
        bool $securityLoginEnabled,
        ?string $pendingSecret = null
    ): array {
        unset($securityEnabled, $securityLoginEnabled);

        return MerchantPortalSecuritySupport::googleAuthPayload(
            $merchant,
            $config,
            fn (string $secret): ?string => $this->maskSecret($secret),
            $pendingSecret
        );
    }

    private function merchantAffiliatePayload(Request $request, array $merchant, ?int $defaultSize = null): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $current = max(1, (int)$request->get('current', $request->get('page', 1)));
        $fallbackSize = $defaultSize ?? 10;
        $size = max(1, min((int)$request->get('size', $request->get('limit', $fallbackSize)), 100));

        $query = $this->merchantAffiliateQuery($merchantId);
        $this->applyMerchantAffiliateFilters($query, $request);

        $summaryQuery = $this->merchantAffiliateBaseQuery($merchantId);
        $this->applyMerchantAffiliateFilters($summaryQuery, $request);

        $total = (int)(clone $query)->count('merchant.id');
        $rows = $query
            ->orderByDesc('merchant.id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        return [
            'merchant_id' => $merchantId,
            'merchant_username' => trim((string)($merchant['username'] ?? '')),
            'records' => array_map(fn ($row): array => $this->formatMerchantAffiliateInvitee((array)$row), $rows),
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => $this->merchantAffiliateSummary($request, $merchant, $summaryQuery),
            'write_actions' => [],
            'migration_guard' => [
                'read_only' => false,
                'blocked_actions' => [],
            ],
        ];
    }

    private function merchantRealNamePayload(Request $request, array $merchant): array
    {
        $config = SystemConfig::all();
        $featureEnabled = $this->realNameFeatureEnabled();
        $verified = (int)($merchant['is_realName'] ?? 0) === 1;
        $type = max(1, (int)($config['realNameType'] ?? 1));
        $channels = $this->realNameChannels($type);
        $availableChannelCount = 0;
        foreach ($channels as $channel) {
            if (!empty($channel['available'])) {
                $availableChannelCount++;
            }
        }
        $writeAllowed = $featureEnabled && !$verified && $availableChannelCount > 0;

        return [
            'merchant_id' => (int)($merchant['id'] ?? 0),
            'merchant_username' => trim((string)($merchant['username'] ?? '')),
            'status' => [
                'feature_enabled' => $featureEnabled,
                'verified' => $verified,
                'label' => !$featureEnabled ? '未开启' : ($verified ? '已认证' : '待认证'),
                'type' => !$featureEnabled ? 'info' : ($verified ? 'success' : 'warning'),
                'name_masked' => $this->maskPersonalName((string)($merchant['name'] ?? '')),
                'id_card_masked' => $this->maskIdentifier((string)($merchant['idCard'] ?? '')),
            ],
            'verification' => [
                'type' => $type,
                'type_label' => $this->realNameTypeLabel($type),
                'channels' => $channels,
                'available_channel_count' => $availableChannelCount,
                'entry_route' => '/merchant/real-name',
                'submit_endpoint' => '/api/merchant/security/real-name',
                'status_endpoint' => '/api/merchant/security/real-name/status',
                'write_allowed' => $writeAllowed,
                'write_message' => $verified
                    ? '当前商户已完成实名认证。'
                    : (!$featureEnabled
                        ? '系统未开启实名认证功能。'
                        : ($availableChannelCount > 0
                            ? '实名认证发起已接入当前商户后台。'
                            : '管理员尚未配置可用的实名认证通道。')),
            ],
            'cost' => [
                'merchant_bears_cost' => trim((string)($config['realNameBear'] ?? '0')) === '1',
                'amount' => round((float)($config['bearMoney'] ?? 0), 2),
                'amount_display' => $this->money($config['bearMoney'] ?? 0),
            ],
            'write_actions' => [
                'identity_submit' => $writeAllowed,
                'verification_start' => $writeAllowed,
                'verification_poll' => $featureEnabled,
            ],
            'migration_guard' => [
                'read_only' => !$writeAllowed,
                'blocked_actions' => $writeAllowed ? [] : ['identity_submit', 'verification_start'],
            ],
        ];
    }

    private function merchantRealNamePayloadV2(Request $request, array $merchant): array
    {
        $config = SystemConfig::all();
        $featureEnabled = $this->realNameFeatureEnabled();
        $verified = (int)($merchant['is_realName'] ?? 0) === 1;
        $type = max(1, (int)($config['realNameType'] ?? 1));
        $channels = $this->realNameChannels($type);
        $availableChannels = array_values(array_filter($channels, static fn (array $channel): bool => !empty($channel['available'])));
        $defaultChannel = (string)($availableChannels[0]['id'] ?? ($type === 2 ? 'ali' : 'wechat'));
        $writeAllowed = $featureEnabled && !$verified && $availableChannels !== [];

        return [
            'merchant_id' => (int)($merchant['id'] ?? 0),
            'merchant_username' => trim((string)($merchant['username'] ?? '')),
            'status' => [
                'feature_enabled' => $featureEnabled,
                'verified' => $verified,
                'label' => !$featureEnabled ? '未开启' : ($verified ? '已认证' : '待认证'),
                'type' => !$featureEnabled ? 'info' : ($verified ? 'success' : 'warning'),
                'name_masked' => $this->maskPersonalName((string)($merchant['name'] ?? '')),
                'id_card_masked' => $this->maskIdentifier((string)($merchant['idCard'] ?? '')),
            ],
            'verification' => [
                'type' => $type,
                'type_label' => $this->realNameTypeLabel($type),
                'channels' => $channels,
                'available_channel_count' => count($availableChannels),
                'entry_route' => '/merchant/real-name',
                'submit_endpoint' => '/api/merchant/security/real-name',
                'status_endpoint' => '/api/merchant/security/real-name/status',
                'write_allowed' => $writeAllowed,
                'write_message' => $verified
                    ? '当前商户已完成实名认证。'
                    : (!$featureEnabled
                        ? '系统未开启实名认证功能。'
                        : ($availableChannels !== []
                            ? '实名认证发起已接入当前商户后台。'
                            : '管理员尚未配置可用的实名认证通道。')),
            ],
            'form' => [
                'name' => trim((string)($merchant['name'] ?? '')),
                'id_card' => trim((string)($merchant['idCard'] ?? '')),
                'default_channel' => $defaultChannel,
                'channels' => $availableChannels,
                'can_edit_identity' => !$verified,
            ],
            'cost' => [
                'merchant_bears_cost' => trim((string)($config['realNameBear'] ?? '0')) === '1',
                'amount' => round((float)($config['bearMoney'] ?? 0), 2),
                'amount_display' => $this->money($config['bearMoney'] ?? 0),
            ],
            'write_actions' => [
                'identity_submit' => $writeAllowed,
                'verification_start' => $writeAllowed,
                'verification_poll' => $featureEnabled,
            ],
            'migration_guard' => [
                'read_only' => !$writeAllowed,
                'blocked_actions' => $writeAllowed ? [] : ['identity_submit', 'verification_start'],
            ],
        ];
    }

    private function resolveMerchantRealNameInput(array $payload, int $type): array
    {
        $formData = $payload['formData'] ?? [];
        $name = $this->sanitizeMerchantInput($payload['name'] ?? $payload['real_name'] ?? $payload['full_name'] ?? '');
        $idCard = strtoupper(str_replace(' ', '', $this->sanitizeMerchantInput(
            $payload['idCard'] ?? $payload['id_card'] ?? $payload['identity_no'] ?? ''
        )));

        if ($name === '' && is_array($formData) && isset($formData[0]['value'])) {
            $name = $this->sanitizeMerchantInput($formData[0]['value']);
        }

        if ($idCard === '' && is_array($formData) && isset($formData[1]['value'])) {
            $idCard = strtoupper(str_replace(' ', '', $this->sanitizeMerchantInput($formData[1]['value'])));
        }

        $channel = strtolower($this->sanitizeMerchantInput(
            $payload['channel'] ?? $payload['type'] ?? $payload['verification_channel'] ?? ''
        ));
        if ($channel === '') {
            $channel = $type === 2 ? 'ali' : 'wechat';
        }

        return [
            'name' => $name,
            'id_card' => $idCard,
            'channel' => $channel,
        ];
    }

    private function realNameAvailableChannels(int $type): array
    {
        $available = [];
        foreach ($this->realNameChannels($type) as $channel) {
            if (!empty($channel['available'])) {
                $available[(string)($channel['id'] ?? '')] = $channel;
            }
        }

        return $available;
    }

    private function isValidRealNameIdCard(string $idCard): bool
    {
        return preg_match('/^(?:\d{15}|\d{17}[\dX])$/i', trim($idCard)) === 1;
    }

    private function merchantLoginLogsPayload(Request $request, int $merchantId, ?int $defaultSize = null): array
    {
        $current = max(1, (int)$request->get('current', $request->get('page', 1)));
        $fallbackSize = $defaultSize ?? 10;
        $size = max(1, min((int)$request->get('size', $request->get('limit', $fallbackSize)), 100));

        $query = $this->merchantLoginLogQuery($merchantId);
        $this->applyMerchantLoginLogFilters($query, $request);

        $summaryQuery = $this->merchantLoginLogBaseQuery($merchantId);
        $this->applyMerchantLoginLogFilters($summaryQuery, $request);

        $total = (int)(clone $query)->count('admin_front_log.id');
        $rows = $query
            ->orderByDesc('admin_front_log.id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        return [
            'records' => array_map(fn ($row): array => $this->formatMerchantLoginLog((array)$row), $rows),
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => $this->merchantLoginLogSummary($summaryQuery),
            'write_actions' => [],
            'migration_guard' => [
                'read_only' => false,
                'blocked_actions' => [],
            ],
        ];
    }

    private function notificationSettingDefinitions(): array
    {
        return [
            ['id' => 'order_tips', 'name' => '新订单通知'],
            ['id' => 'lose_tips', 'name' => '通道离线提醒'],
            ['id' => 'login_tips', 'name' => '账户登录提醒'],
            ['id' => 'is_money_tips', 'name' => '余额不足提醒'],
        ];
    }

    private function notificationChannels(): array
    {
        $config = SystemConfig::all();

        return [
            'close' => [
                'id' => 'close',
                'label' => '关闭',
                'available' => true,
            ],
            'email' => [
                'id' => 'email',
                'label' => '邮箱',
                'available' => trim((string)($config['email_switch'] ?? '0')) === '1',
            ],
            'wxpusher' => [
                'id' => 'wxpusher',
                'label' => '微信推送',
                'available' => trim((string)($config['wxpusher_switch'] ?? '0')) === '1',
            ],
            'tg' => [
                'id' => 'tg',
                'label' => '电报通知',
                'available' => trim((string)($config['tg_switch'] ?? '0')) === '1',
            ],
        ];
    }

    private function normalizeNotificationChannel(mixed $value): string
    {
        $channel = strtolower(trim((string)$value));

        return in_array($channel, ['email', 'wxpusher', 'tg', 'close'], true) ? $channel : 'close';
    }

    private function normalizeAvailableNotificationChannel(mixed $value, array $channels): string
    {
        $channel = $this->normalizeNotificationChannel($value);
        if ($channel === 'close') {
            return 'close';
        }

        return !empty($channels[$channel]['available']) ? $channel : 'close';
    }

    private function normalizeBinaryFlag(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    }

    private function notificationChannelLabel(string $channel): string
    {
        return match ($channel) {
            'email' => '邮箱',
            'wxpusher' => '微信推送',
            'tg' => '电报通知',
            default => '关闭',
        };
    }

    private function gatewayLines(Request $request): array
    {
        $config = SystemConfig::all();
        $enabled = trim((string)($config['is_pay_api'] ?? '0')) === '1';
        $configured = trim((string)($config['pay_api'] ?? ''));
        $urls = [];

        if ($enabled && $configured !== '') {
            foreach (explode(',', $configured) as $value) {
                $url = trim((string)$value);
                if ($url !== '') {
                    $urls[] = $url;
                }
            }
        }

        if ($urls === []) {
            $urls[] = $this->requestOrigin($request) . '/';
        }

        $lines = [];
        foreach (array_values(array_unique($urls)) as $index => $url) {
            $baseUrl = rtrim($url, '/') . '/';
            $lines[] = [
                'name' => 'Line ' . ($index + 1),
                'url' => $baseUrl,
                'submit_url' => $this->gatewayUrl($baseUrl, '/submit.php'),
                'mapi_url' => $this->gatewayUrl($baseUrl, '/mapi.php'),
            ];
        }

        return $lines;
    }

    private function timeoutMethodLabel(int $method): string
    {
        return $method === 1 ? '使用订单回调域名' : '使用已配置的超时跳转地址';
    }

    private function maskSecret(string $secret): ?string
    {
        $secret = trim($secret);
        if ($secret === '') {
            return null;
        }

        $length = strlen($secret);
        if ($length <= 8) {
            return str_repeat('*', max(4, $length));
        }

        return substr($secret, 0, 4) . str_repeat('*', 8) . substr($secret, -4);
    }

    private function maskIdentifier(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_contains($value, '@')) {
            [$localPart, $domainPart] = explode('@', $value, 2);
            $localLength = strlen($localPart);
            if ($localLength <= 1) {
                return '*' . '@' . $domainPart;
            }
            if ($localLength === 2) {
                return substr($localPart, 0, 1) . '*' . '@' . $domainPart;
            }

            return substr($localPart, 0, 2)
                . str_repeat('*', max(2, $localLength - 3))
                . substr($localPart, -1)
                . '@'
                . $domainPart;
        }

        $length = strlen($value);
        if ($length <= 2) {
            return str_repeat('*', $length);
        }
        if ($length <= 6) {
            return substr($value, 0, 1) . str_repeat('*', $length - 2) . substr($value, -1);
        }

        return substr($value, 0, 3) . str_repeat('*', max(4, $length - 6)) . substr($value, -3);
    }

    private function merchantOrderQuery(int $merchantId): Builder
    {
        return $this->merchantOrderBaseQuery($merchantId)
            ->select(
                'orders.id',
                'orders.name',
                'orders.sitename',
                'orders.trade_no',
                'orders.out_trade_no',
                'orders.alipay_order_no',
                'orders.user_id',
                'orders.account_id',
                'orders.type',
                'orders.pay_type',
                'orders.money',
                'orders.truemoney',
                'orders.feilvmoney',
                'orders.status',
                'orders.notify_url',
                'orders.return_url',
                'orders.ip',
                'orders.create_time',
                'orders.end_time',
                'orders.api_memo',
                'paylist.type as paylist_type',
                'paylist.name as paylist_name',
                'account.code as local_account_code',
                'admin_channel.type as local_channel_type',
                'admin_channel.name as local_channel_name'
            );
    }

    private function merchantOrderBaseQuery(int $merchantId): Builder
    {
        return Db::table(BusinessTable::order('orders'))
            ->leftJoin(BusinessTable::paylist('paylist'), 'orders.account_id', '=', 'paylist.id')
            ->leftJoin(BusinessTable::account('account'), 'orders.account_id', '=', 'account.id')
            ->leftJoin('admin_channel', 'account.code', '=', 'admin_channel.code')
            ->where('orders.user_id', $merchantId);
    }

    private function merchantMoneyLogQuery(int $merchantId): Builder
    {
        return $this->merchantMoneyLogBaseQuery($merchantId)
            ->select(
                'money_log.id',
                'money_log.user_id',
                'money_log.type',
                'money_log.money',
                'money_log.beforemoney',
                'money_log.after',
                'money_log.create_time',
                'money_log.memo'
            );
    }

    private function merchantMoneyLogBaseQuery(int $merchantId): Builder
    {
        return Db::table('money_log')
            ->where('money_log.user_id', $merchantId);
    }

    private function merchantRechargeQuery(int $merchantId): Builder
    {
        return $this->merchantRechargeBaseQuery($merchantId)
            ->select(
                'recharge.id',
                'recharge.type',
                'recharge.rtype',
                'recharge.out_trade_no',
                'recharge.user_id',
                'recharge.money',
                'recharge.qrcode',
                'recharge.status',
                'recharge.regdata',
                'recharge.create_time',
                'recharge.end_time',
                'recharge.update_time',
                'recharge.out_time',
                'merchant.username as merchant_username',
                'merchant.name as merchant_name',
                'merchant.email as merchant_email',
                'merchant.mobile as merchant_mobile'
            );
    }

    private function merchantRechargeBaseQuery(int $merchantId): Builder
    {
        return Db::table(BusinessTable::recharge('recharge'))
            ->leftJoin(BusinessTable::user('merchant'), 'recharge.user_id', '=', 'merchant.id')
            ->where('recharge.user_id', $merchantId);
    }

    private function merchantVipQuery(): Builder
    {
        return $this->merchantVipBaseQuery()
            ->select(
                'id',
                'icon',
                'avatar_frame',
                'name',
                'feilv',
                'money',
                'viptime',
                'status',
                'sort',
                'is_profiteer',
                'is_addChannelNum',
                'addChannelNum',
                'is_quota',
                'today_quota',
                'moon_quota',
                'is_passage',
                'passage',
                'create_time',
                'delete_time'
            );
    }

    private function merchantVipBaseQuery(): Builder
    {
        return Db::table(BusinessTable::vip())
            ->whereNull('delete_time')
            ->where('status', 1);
    }

    private function merchantTicketQuery(int $merchantId): Builder
    {
        return $this->merchantTicketBaseQuery($merchantId)
            ->select(
                'ticket.id',
                'ticket.type',
                'ticket.title',
                'ticket.content',
                'ticket.reply_content',
                'ticket.creator_id',
                'ticket.assignee_id',
                'ticket.create_time',
                'ticket.update_time',
                'ticket.reply_time',
                'ticket.status',
                'category.name as category_name',
                'creator.username as creator_username',
                'creator.name as creator_name',
                'creator.email as creator_email',
                'creator.mobile as creator_mobile',
                'assignee.username as assignee_username',
                'assignee.nickname as assignee_nickname'
            );
    }

    private function merchantTicketBaseQuery(int $merchantId): Builder
    {
        return Db::table(BusinessTable::ticket('ticket'))
            ->leftJoin(BusinessTable::ticketCategory('category'), 'ticket.type', '=', 'category.id')
            ->leftJoin(BusinessTable::user('creator'), 'ticket.creator_id', '=', 'creator.id')
            ->leftJoin('admin_admin as assignee', 'ticket.assignee_id', '=', 'assignee.id')
            ->where('ticket.creator_id', $merchantId);
    }

    private function merchantDomainQuery(int $merchantId): Builder
    {
        return $this->merchantDomainBaseQuery($merchantId)
            ->select(
                'domain.id',
                'domain.user_id',
                'domain.sitename',
                'domain.siteurl',
                'domain.status',
                'domain.reason',
                'domain.create_time',
                'domain.delete_time',
                'merchant.username as merchant_username',
                'merchant.name as merchant_name',
                'merchant.email as merchant_email',
                'merchant.mobile as merchant_mobile'
            );
    }

    private function merchantDomainBaseQuery(int $merchantId): Builder
    {
        return Db::table(BusinessTable::domain('domain'))
            ->leftJoin(BusinessTable::user('merchant'), 'domain.user_id', '=', 'merchant.id')
            ->where('domain.user_id', $merchantId)
            ->whereNull('domain.delete_time');
    }

    private function merchantLoginLogQuery(int $merchantId): Builder
    {
        return $this->merchantLoginLogBaseQuery($merchantId)
            ->select(
                'admin_front_log.id',
                'admin_front_log.uid as user_id',
                'admin_front_log.url',
                'admin_front_log.type',
                'admin_front_log.desc',
                'admin_front_log.ip',
                'admin_front_log.user_agent',
                'admin_front_log.create_time',
                'merchant.username as merchant_username',
                'merchant.name as merchant_name',
                'merchant.email as merchant_email',
                'merchant.mobile as merchant_mobile'
            );
    }

    private function merchantLoginLogBaseQuery(int $merchantId): Builder
    {
        return Db::table('admin_front_log')
            ->leftJoin(BusinessTable::user('merchant'), 'admin_front_log.uid', '=', 'merchant.id')
            ->where('admin_front_log.uid', $merchantId);
    }

    private function merchantAffiliateQuery(int $merchantId): Builder
    {
        return $this->merchantAffiliateBaseQuery($merchantId)
            ->select(
                'merchant.id',
                'merchant.username',
                'merchant.name',
                'merchant.email',
                'merchant.mobile',
                'merchant.money',
                'merchant.vip_id',
                'merchant.vip_time',
                'merchant.is_realName',
                'merchant.create_time',
                'vip.name as vip_name'
            );
    }

    private function merchantAffiliateBaseQuery(int $merchantId): Builder
    {
        return Db::table(BusinessTable::user('merchant'))
            ->leftJoin(BusinessTable::vip('vip'), 'merchant.vip_id', '=', 'vip.id')
            ->where('merchant.superior_id', $merchantId);
    }

    private function applyMerchantOrderFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', $request->get('name', '')));
        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder
                    ->where('orders.trade_no', 'like', '%' . $keyword . '%')
                    ->orWhere('orders.out_trade_no', 'like', '%' . $keyword . '%')
                    ->orWhere('orders.name', 'like', '%' . $keyword . '%')
                    ->orWhere('orders.sitename', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder
                        ->orWhere('orders.id', (int)$keyword)
                        ->orWhere('orders.account_id', (int)$keyword);
                }
            });
        }

        $status = $request->get('status');
        if ($status !== null && $status !== '') {
            $query->where('orders.status', (int)$status);
        }

        $type = trim((string)$request->get('type', ''));
        if ($type !== '') {
            $query->where('orders.type', $type);
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', $request->get('create_time-start', '')));
        $endDate = $this->normalizeDate((string)$request->get('end_date', $request->get('create_time-end', '')));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('orders.create_time', '>=', $startDate . ' 00:00:00')
                ->where('orders.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
        }
    }

    private function applyMerchantMoneyLogFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', $request->get('memo', '')));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword) {
                $builder->where('money_log.memo', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('money_log.id', (int)$keyword);
                }
            });
        }

        $type = trim((string)$request->get('type', ''));
        if ($type !== '') {
            $query->where('money_log.type', (int)$type);
        }

        $direction = strtolower(trim((string)$request->get('direction', '')));
        if ($direction === 'income') {
            $query->where('money_log.money', '>=', 0);
        }
        if ($direction === 'expense') {
            $query->where('money_log.money', '<', 0);
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', $request->get('create_time-start', '')));
        $endDate = $this->normalizeDate((string)$request->get('end_date', $request->get('create_time-end', '')));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('money_log.create_time', '>=', $startDate . ' 00:00:00')
                ->where('money_log.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
        }
    }

    private function applyMerchantRechargeFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', $request->get('out_trade_no', '')));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword) {
                $builder
                    ->where('recharge.out_trade_no', 'like', '%' . $keyword . '%')
                    ->orWhere('recharge.type', 'like', '%' . $keyword . '%')
                    ->orWhere('recharge.regdata', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('recharge.id', (int)$keyword);
                }
            });
        }

        $status = $request->get('status');
        if ($status !== null && $status !== '') {
            $query->where('recharge.status', (int)$status);
        }

        $type = trim((string)$request->get('type', ''));
        if ($type !== '') {
            $query->where('recharge.type', $type);
        }

        $rtype = $request->get('rtype');
        if ($rtype !== null && $rtype !== '') {
            $query->where('recharge.rtype', (int)$rtype);
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', $request->get('create_time-start', '')));
        $endDate = $this->normalizeDate((string)$request->get('end_date', $request->get('create_time-end', '')));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('recharge.create_time', '>=', $startDate . ' 00:00:00')
                ->where('recharge.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
        }
    }

    private function applyMerchantVipFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword) {
                $builder
                    ->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('feilv', 'like', '%' . $keyword . '%')
                    ->orWhere('passage', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('id', (int)$keyword);
                }
            });
        }

        $passageEnabled = $request->get('passage_enabled');
        if ($passageEnabled !== null && $passageEnabled !== '') {
            $query->where('is_passage', (int)$passageEnabled);
        }
    }

    private function applyMerchantTicketFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('ticket.title', 'like', '%' . $keyword . '%')
                    ->orWhere('ticket.content', 'like', '%' . $keyword . '%')
                    ->orWhere('ticket.reply_content', 'like', '%' . $keyword . '%')
                    ->orWhere('category.name', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('ticket.id', (int)$keyword);
                }
            });
        }

        $status = $request->get('status');
        if ($status !== null && $status !== '' && in_array((string)$status, ['0', '1', '2', '3'], true)) {
            $query->where('ticket.status', (int)$status);
        }

        $type = $request->get('type');
        if ($type !== null && $type !== '' && ctype_digit((string)$type)) {
            $query->where('ticket.type', (int)$type);
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', $request->get('create_time-start', '')));
        $endDate = $this->normalizeDate((string)$request->get('end_date', $request->get('create_time-end', '')));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('ticket.create_time', '>=', $startDate . ' 00:00:00')
                ->where('ticket.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
        }
    }

    private function applyMerchantDomainFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', $request->get('sitename', '')));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('domain.sitename', 'like', '%' . $keyword . '%')
                    ->orWhere('domain.siteurl', 'like', '%' . $keyword . '%')
                    ->orWhere('domain.reason', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('domain.id', (int)$keyword);
                }
            });
        }

        $status = $request->get('status');
        if ($status !== null && $status !== '' && in_array((string)$status, ['0', '1', '2'], true)) {
            $query->where('domain.status', (int)$status);
        }

        $siteurl = trim((string)$request->get('siteurl', ''));
        if ($siteurl !== '') {
            $query->where('domain.siteurl', 'like', '%' . $siteurl . '%');
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', $request->get('create_time-start', '')));
        $endDate = $this->normalizeDate((string)$request->get('end_date', $request->get('create_time-end', '')));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('domain.create_time', '>=', $startDate . ' 00:00:00')
                ->where('domain.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
        }
    }

    private function applyMerchantLoginLogFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('admin_front_log.url', 'like', '%' . $keyword . '%')
                    ->orWhere('admin_front_log.ip', 'like', '%' . $keyword . '%')
                    ->orWhere('admin_front_log.desc', 'like', '%' . $keyword . '%')
                    ->orWhere('admin_front_log.user_agent', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('admin_front_log.id', (int)$keyword);
                }
            });
        }

        $ip = trim((string)$request->get('ip', ''));
        if ($ip !== '') {
            $query->where('admin_front_log.ip', 'like', '%' . $ip . '%');
        }

        $type = $request->get('type');
        if ($type !== null && $type !== '' && ctype_digit((string)$type)) {
            $query->where('admin_front_log.type', (int)$type);
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', $request->get('create_time-start', '')));
        $endDate = $this->normalizeDate((string)$request->get('end_date', $request->get('create_time-end', '')));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('admin_front_log.create_time', '>=', $startDate . ' 00:00:00')
                ->where('admin_front_log.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
        }
    }

    private function applyMerchantAffiliateFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', $request->get('id', '')));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('merchant.username', 'like', '%' . $keyword . '%')
                    ->orWhere('merchant.name', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('merchant.id', (int)$keyword);
                }
            });
        }

        $verified = strtolower(trim((string)$request->get('verified', '')));
        if ($verified === '1' || $verified === 'true') {
            $query->where('merchant.is_realName', 1);
        } elseif ($verified === '0' || $verified === 'false') {
            $query->where('merchant.is_realName', 0);
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', $request->get('start', '')));
        $endDate = $this->normalizeDate((string)$request->get('end_date', $request->get('end', '')));
        if ($startDate !== null) {
            $query->where('merchant.create_time', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate !== null) {
            $query->where('merchant.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
        }
    }

    private function merchantAffiliateSummary(Request $request, array $merchant, Builder $query): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $config = SystemConfig::all();
        $inviteCount = (int)(clone $query)->count('merchant.id');
        $verifiedInviteCount = (int)(clone $query)->where('merchant.is_realName', 1)->count('merchant.id');
        $vipInviteCount = (int)(clone $query)->where('merchant.vip_id', '>', 0)->count('merchant.id');
        $lastInviteTime = $this->nullableString((clone $query)->max('merchant.create_time'));
        $rebateQuery = $this->merchantAffiliateRebateQuery($merchantId);
        $totalRebate = round((float)(clone $rebateQuery)->sum('money_log.money'), 2);
        $todayRebate = round((float)(clone $rebateQuery)
            ->where('money_log.create_time', '>=', date('Y-m-d 00:00:00'))
            ->sum('money_log.money'), 2);
        $percentage = (float)($config['aff_percentage'] ?? 0);

        return [
            'enabled' => true,
            'rebate_type' => (int)($config['aff_type'] ?? 0),
            'rebate_type_label' => $this->affiliateTypeLabel((int)($config['aff_type'] ?? 0)),
            'percentage' => $percentage,
            'percentage_display' => rtrim(rtrim(number_format($percentage * 100, 2, '.', ''), '0'), '.') . '%',
            'invite_count' => $inviteCount,
            'verified_invite_count' => $verifiedInviteCount,
            'vip_invite_count' => $vipInviteCount,
            'total_rebate_amount' => $totalRebate,
            'total_rebate_display' => $this->money($totalRebate),
            'today_rebate_amount' => $todayRebate,
            'today_rebate_display' => $this->money($todayRebate),
            'parent_affiliate_id' => empty($merchant['superior_id']) ? null : (int)$merchant['superior_id'],
            'parent_affiliate_label' => empty($merchant['superior_id'])
                ? '暂无上级商户'
                : ('商户 #' . (int)$merchant['superior_id']),
            'invite_url' => $this->withHashPath(
                $this->merchantFrontendBaseUrl($request),
                '/merchant/register',
                ['aff' => $merchantId]
            ),
            'last_invite_time' => $lastInviteTime,
        ];
    }

    private function merchantAffiliateRebateQuery(int $merchantId): Builder
    {
        return Db::table('money_log')
            ->where('money_log.user_id', $merchantId)
            ->whereIn('money_log.memo', ['下级充值返利', '下级购买会员套餐返利']);
    }

    private function merchantOrderSummary(Builder $query): array
    {
        $row = (array)($query
            ->selectRaw('COUNT(orders.id) as total_count')
            ->selectRaw('SUM(CASE WHEN orders.status = 1 THEN 1 ELSE 0 END) as paid_count')
            ->selectRaw('SUM(CASE WHEN orders.status = 0 THEN 1 ELSE 0 END) as pending_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN orders.status = 1 THEN CASE WHEN orders.type = 'usdt' THEN orders.money ELSE orders.truemoney END ELSE 0 END), 0) as paid_amount")
            ->selectRaw('COALESCE(SUM(CASE WHEN orders.status = 0 THEN orders.money ELSE 0 END), 0) as pending_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN orders.status = 1 THEN orders.feilvmoney ELSE 0 END), 0) as fee_amount')
            ->selectRaw('MAX(orders.create_time) as last_order_time')
            ->first() ?? []);

        $totalCount = (int)($row['total_count'] ?? 0);
        $paidCount = (int)($row['paid_count'] ?? 0);

        return [
            'total_count' => $totalCount,
            'paid_count' => $paidCount,
            'pending_count' => (int)($row['pending_count'] ?? 0),
            'paid_amount' => round((float)($row['paid_amount'] ?? 0), 2),
            'pending_amount' => round((float)($row['pending_amount'] ?? 0), 2),
            'fee_amount' => round((float)($row['fee_amount'] ?? 0), 3),
            'success_rate' => $totalCount > 0 ? round(($paidCount / $totalCount) * 100, 2) : 0.0,
            'last_order_time' => $this->nullableString($row['last_order_time'] ?? null),
        ];
    }

    private function merchantMoneyLogSummary(Builder $query): array
    {
        $incomeQuery = clone $query;
        $expenseQuery = clone $query;
        $lastTimeQuery = clone $query;

        $incomeAmount = round((float)$incomeQuery->where('money_log.money', '>=', 0)->sum('money_log.money'), 3);
        $expenseAmount = round((float)$expenseQuery->where('money_log.money', '<', 0)->sum('money_log.money'), 3);

        return [
            'income_count' => (int)(clone $query)->where('money_log.money', '>=', 0)->count('money_log.id'),
            'expense_count' => (int)(clone $query)->where('money_log.money', '<', 0)->count('money_log.id'),
            'income_amount' => $incomeAmount,
            'expense_amount' => $expenseAmount,
            'net_amount' => round($incomeAmount + $expenseAmount, 3),
            'last_log_time' => $this->nullableString($lastTimeQuery->max('money_log.create_time')),
        ];
    }

    private function merchantRechargeSummary(Builder $query): array
    {
        $now = time();
        $row = (array)($query
            ->selectRaw('COUNT(recharge.id) as total_count')
            ->selectRaw('SUM(CASE WHEN recharge.status = 1 THEN 1 ELSE 0 END) as paid_count')
            ->selectRaw('SUM(CASE WHEN recharge.status = 0 THEN 1 ELSE 0 END) as pending_count')
            ->selectRaw('SUM(CASE WHEN recharge.status NOT IN (0, 1) THEN 1 ELSE 0 END) as unknown_status_count')
            ->selectRaw('SUM(CASE WHEN recharge.rtype = 0 THEN 1 ELSE 0 END) as merchant_recharge_count')
            ->selectRaw('SUM(CASE WHEN recharge.rtype = 1 THEN 1 ELSE 0 END) as registration_count')
            ->selectRaw(
                'SUM(CASE WHEN recharge.status = 0 AND recharge.out_time IS NOT NULL AND recharge.out_time > 0 AND recharge.out_time <= ? THEN 1 ELSE 0 END) as expired_pending_count',
                [$now]
            )
            ->selectRaw('COALESCE(SUM(recharge.money), 0) as gross_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN recharge.status = 1 THEN recharge.money ELSE 0 END), 0) as paid_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN recharge.status = 0 THEN recharge.money ELSE 0 END), 0) as pending_amount')
            ->selectRaw('MAX(recharge.create_time) as last_recharge_time')
            ->first() ?? []);

        $totalCount = (int)($row['total_count'] ?? 0);
        $paidCount = (int)($row['paid_count'] ?? 0);

        return [
            'total_count' => $totalCount,
            'paid_count' => $paidCount,
            'pending_count' => (int)($row['pending_count'] ?? 0),
            'unknown_status_count' => (int)($row['unknown_status_count'] ?? 0),
            'merchant_recharge_count' => (int)($row['merchant_recharge_count'] ?? 0),
            'registration_count' => (int)($row['registration_count'] ?? 0),
            'expired_pending_count' => (int)($row['expired_pending_count'] ?? 0),
            'gross_amount' => AdminRechargeFormatter::toFloat($row['gross_amount'] ?? 0),
            'paid_amount' => AdminRechargeFormatter::toFloat($row['paid_amount'] ?? 0),
            'pending_amount' => AdminRechargeFormatter::toFloat($row['pending_amount'] ?? 0),
            'success_rate' => $totalCount > 0 ? round(($paidCount / $totalCount) * 100, 2) : 0.0,
            'last_recharge_time' => $this->nullableString($row['last_recharge_time'] ?? null),
        ];
    }

    private function merchantVipSummary(Builder $query, array $currentVip): array
    {
        $row = (array)($query
            ->selectRaw('COUNT(id) as total_count')
            ->selectRaw('COALESCE(MIN(money), 0) as min_price')
            ->selectRaw('COALESCE(MAX(money), 0) as max_price')
            ->selectRaw('SUM(CASE WHEN viptime <= 0 THEN 1 ELSE 0 END) as unlimited_count')
            ->selectRaw('SUM(CASE WHEN is_passage = 1 THEN 1 ELSE 0 END) as passage_locked_count')
            ->selectRaw('SUM(CASE WHEN is_quota = 1 THEN 1 ELSE 0 END) as quota_enabled_count')
            ->first() ?? []);

        return [
            'total_count' => (int)($row['total_count'] ?? 0),
            'min_price' => round((float)($row['min_price'] ?? 0), 2),
            'max_price' => round((float)($row['max_price'] ?? 0), 2),
            'unlimited_count' => (int)($row['unlimited_count'] ?? 0),
            'passage_locked_count' => (int)($row['passage_locked_count'] ?? 0),
            'quota_enabled_count' => (int)($row['quota_enabled_count'] ?? 0),
            'current_vip_id' => (int)($currentVip['id'] ?? 0),
            'current_vip_active' => (bool)($currentVip['is_active'] ?? false),
        ];
    }

    private function merchantTicketSummary(Builder $query): array
    {
        return [
            'new_count' => (int)(clone $query)->where('ticket.status', 0)->count('ticket.id'),
            'processing_count' => (int)(clone $query)->where('ticket.status', 1)->count('ticket.id'),
            'resolved_count' => (int)(clone $query)->where('ticket.status', 2)->count('ticket.id'),
            'closed_count' => (int)(clone $query)->where('ticket.status', 3)->count('ticket.id'),
            'replied_count' => (int)(clone $query)
                ->whereNotNull('ticket.reply_content')
                ->where('ticket.reply_content', '<>', '')
                ->count('ticket.id'),
            'last_ticket_time' => $this->nullableString((clone $query)->max('ticket.create_time')),
        ];
    }

    private function merchantDomainSummary(Builder $query): array
    {
        return [
            'pending_count' => (int)(clone $query)->where('domain.status', 0)->count('domain.id'),
            'approved_count' => (int)(clone $query)->where('domain.status', 1)->count('domain.id'),
            'rejected_count' => (int)(clone $query)->where('domain.status', 2)->count('domain.id'),
            'last_domain_time' => $this->nullableString((clone $query)->max('domain.create_time')),
        ];
    }

    private function merchantLoginLogSummary(Builder $query): array
    {
        return [
            'total_count' => (int)(clone $query)->count('admin_front_log.id'),
            'payload_count' => (int)(clone $query)
                ->whereNotNull('admin_front_log.desc')
                ->where('admin_front_log.desc', '<>', '')
                ->count('admin_front_log.id'),
            'today_count' => (int)(clone $query)
                ->where('admin_front_log.create_time', '>=', date('Y-m-d 00:00:00'))
                ->count('admin_front_log.id'),
            'ip_count' => (int)(clone $query)
                ->where('admin_front_log.ip', '<>', '')
                ->distinct()
                ->count('admin_front_log.ip'),
            'last_log_time' => $this->nullableString((clone $query)->max('admin_front_log.create_time')),
        ];
    }

    private function merchantTicketCategories(): array
    {
        $rows = Db::table(BusinessTable::ticketCategory())
            ->select('id', 'name', 'status')
            ->where('status', 1)
            ->orderByRaw("CAST(COALESCE(NULLIF(sort, ''), '0') AS UNSIGNED)")
            ->orderBy('id')
            ->get()
            ->toArray();

        return array_map(
            static fn ($row): array => AdminTicketFormatter::formatCategory((array)$row),
            $rows
        );
    }

    private function validateMerchantDomainPayload(Request $request, int $merchantId): array|Response
    {
        $payload = $this->requestPayload($request);
        $siteName = $this->sanitizeMerchantInput($payload['sitename'] ?? '');
        $siteUrl = $this->normalizeMerchantDomainUrl($payload['siteurl'] ?? '');

        if ($siteName === '') {
            return $this->merchantValidationError('site name is required');
        }
        if ($this->stringLength($siteName) > 255) {
            return $this->merchantValidationError('site name is too long');
        }
        if ($siteUrl === '') {
            return $this->merchantValidationError('site domain is required');
        }
        if ($this->stringLength($siteUrl) > 255) {
            return $this->merchantValidationError('site domain is too long');
        }
        if (!$this->isMerchantDomainUrlValid($siteUrl)) {
            return $this->merchantValidationError('site domain format is invalid');
        }

        $config = SystemConfig::all();
        if ($this->domainMatchesConfigList($siteUrl, (string)($config['domain_black'] ?? ''))) {
            return $this->merchantValidationError('当前域名已被商户黑名单拦截');
        }

        $status = 0;
        if ($this->domainMatchesConfigList($siteUrl, (string)($config['domain_white'] ?? ''))) {
            $status = 1;
        }
        if (trim((string)($config['is_examine'] ?? '0')) === '1') {
            $status = 1;
        }

        return [
            'user_id' => $merchantId,
            'sitename' => $siteName,
            'siteurl' => $siteUrl,
            'status' => $status,
            'reason' => null,
        ];
    }

    private function prepareMerchantDomainData(array $validated, int $merchantId): array
    {
        return [
            'user_id' => $merchantId,
            'sitename' => $validated['sitename'],
            'siteurl' => $validated['siteurl'],
            'status' => (int)($validated['status'] ?? 0),
            'reason' => null,
        ];
    }

    private function merchantDomainSubmissionAllowed(int $merchantId): bool
    {
        $limit = (int)trim((string)SystemConfig::get('domainNum', '0'));
        if ($limit <= 0) {
            return true;
        }

        $start = date('Y-m-d 00:00:00');
        $end = date('Y-m-d 00:00:00', strtotime('+1 day'));
        $count = (int)Db::table(BusinessTable::domain())
            ->where('user_id', $merchantId)
            ->where('create_time', '>=', $start)
            ->where('create_time', '<', $end)
            ->count();

        return $count < $limit;
    }

    private function normalizeMerchantDomainUrl(mixed $value): string
    {
        $siteUrl = strtolower($this->sanitizeMerchantInput($value));
        $siteUrl = preg_replace('#^https?://#i', '', $siteUrl);
        $siteUrl = rtrim((string)$siteUrl, '/');

        return trim((string)$siteUrl);
    }

    private function isMerchantDomainUrlValid(string $siteUrl): bool
    {
        return preg_match('/^(?:[a-z0-9-]+\.)+[a-z0-9-]+(?::\d{1,5})?(?:\/[^\s]*)?$/i', $siteUrl) === 1;
    }

    private function domainMatchesConfigList(string $siteUrl, string $configValue): bool
    {
        $items = preg_split('/[\r\n,\s]+/', strtolower($configValue), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($items) || $items === []) {
            return false;
        }

        $normalizedUrl = strtolower($siteUrl);
        foreach ($items as $item) {
            $normalizedItem = $this->normalizeMerchantDomainUrl($item);
            if ($normalizedItem !== '' && $normalizedItem === $normalizedUrl) {
                return true;
            }
        }

        return false;
    }

    private function formatMerchantOrder(array $order, bool $includeUrls = false): array
    {
        $status = (int)($order['status'] ?? 0);
        $type = trim((string)($order['type'] ?? ''));
        $formatted = [
            'id' => (int)($order['id'] ?? 0),
            'name' => trim((string)($order['name'] ?? '')),
            'sitename' => trim((string)($order['sitename'] ?? '')),
            'trade_no' => trim((string)($order['trade_no'] ?? '')),
            'out_trade_no' => trim((string)($order['out_trade_no'] ?? '')),
            'upstream_trade_no' => trim((string)($order['alipay_order_no'] ?? '')),
            'type' => $type,
            'type_label' => $this->paymentTypeLabel($type),
            'account_id' => (int)($order['account_id'] ?? 0),
            'pay_type' => (int)($order['pay_type'] ?? 0),
            'channel_name' => $this->merchantChannelName($order),
            'channel_label' => $this->merchantChannelLabel($order),
            'money' => round((float)($order['money'] ?? 0), 2),
            'truemoney' => $type === 'usdt'
                ? (round((float)($order['truemoney'] ?? 0), 2) . 'Usdt')
                : round((float)($order['truemoney'] ?? 0), 2),
            'settled_amount' => $type === 'usdt'
                ? round((float)($order['money'] ?? 0), 2)
                : round((float)($order['truemoney'] ?? 0), 2),
            'fee_amount' => round((float)($order['feilvmoney'] ?? 0), 3),
            'status' => $status,
            'status_label' => $status === 1 ? '已支付' : ($status === 0 ? '待支付' : '未知'),
            'status_badge' => $status === 1 ? 'success' : ($status === 0 ? 'warning' : 'info'),
            'create_time' => $this->nullableString($order['create_time'] ?? null),
            'end_time' => $this->nullableString($order['end_time'] ?? null) ?? '-',
            'ip' => trim((string)($order['ip'] ?? '')),
            'api_memo' => $this->nullableString($order['api_memo'] ?? null) ?? '',
            'details_url' => '/api/merchant/orders/detail?id=' . (int)($order['id'] ?? 0),
        ];

        if ($includeUrls) {
            $formatted['notify_url'] = trim((string)($order['notify_url'] ?? ''));
            $formatted['return_url'] = trim((string)($order['return_url'] ?? ''));
        }

        return $formatted;
    }

    private function formatMerchantMoneyLog(array $log): array
    {
        $amount = round((float)($log['money'] ?? 0), 3);
        $before = round((float)($log['beforemoney'] ?? 0), 3);
        $after = round((float)($log['after'] ?? 0), 3);
        $memo = trim((string)($log['memo'] ?? ''));

        return [
            'id' => (int)($log['id'] ?? 0),
            'user_id' => (int)($log['user_id'] ?? 0),
            'type' => isset($log['type']) ? (int)$log['type'] : null,
            'type_label' => $this->moneyLogTypeLabel($log['type'] ?? null, $amount, $memo),
            'type_tag' => $amount < 0 ? 'warning' : ($amount > 0 ? 'success' : 'info'),
            'money' => $amount,
            'money_display' => $this->signedLogMoney($amount),
            'before_money' => $before,
            'after_money' => $after,
            'before_money_display' => $this->logMoney($before),
            'after_money_display' => $this->logMoney($after),
            'balance_delta_label' => $this->signedLogMoney($amount),
            'direction' => $amount < 0 ? 'expense' : 'income',
            'direction_label' => $amount < 0 ? '支出' : '收入',
            'memo' => $memo,
            'memo_label' => $memo !== '' ? $memo : '无备注',
            'create_time' => $this->nullableString($log['create_time'] ?? null),
        ];
    }

    private function moneyLogTypeLabel(mixed $type, float $amount, string $memo): string
    {
        $normalizedMemo = strtolower($memo);
        if (str_contains($normalizedMemo, 'fee')) {
            return '手续费扣减';
        }
        if (str_contains($normalizedMemo, 'recharge')) {
            return '余额充值';
        }
        if (str_contains($normalizedMemo, 'deduct')) {
            return '余额扣减';
        }
        if (str_contains($normalizedMemo, 'settle') || str_contains($normalizedMemo, 'order')) {
            return '结算变动';
        }
        if ($type !== null && $type !== '') {
            return '类型 ' . (int)$type;
        }

        return $amount < 0 ? '余额减少' : '余额增加';
    }

    private function formatMerchantRecharge(array $recharge): array
    {
        return AdminRechargeFormatter::format($recharge);
    }

    private function formatMerchantVip(array $vip, array $currentVip): array
    {
        $formatted = AdminVipFormatter::format($vip);
        $formatted['is_current'] = (int)($formatted['id'] ?? 0) > 0
            && (int)($formatted['id'] ?? 0) === (int)($currentVip['id'] ?? 0);
        $formatted['purchase_enabled'] = true;
        $formatted['purchase_status'] = $formatted['is_current'] ? 'renewable' : 'available';
        $formatted['purchase_message'] = $formatted['is_current']
            ? '当前套餐可直接续费'
            : '当前套餐已支持购买';

        return $formatted;
    }

    private function formatMerchantTicket(array $ticket): array
    {
        $formatted = AdminTicketFormatter::format($ticket);
        $formatted['create_enabled'] = true;
        $formatted['delete_enabled'] = true;
        $formatted['write_message'] = '工单创建与删除已在当前商户后台开放';

        return $formatted;
    }

    private function formatMerchantDomain(array $domain): array
    {
        $formatted = AdminDomainFormatter::format($domain);
        $formatted['create_enabled'] = true;
        $formatted['edit_enabled'] = true;
        $formatted['delete_enabled'] = true;
        $formatted['write_message'] = '域名新增、编辑与删除已在当前商户后台开放';

        return $formatted;
    }

    private function formatMerchantLoginLog(array $log): array
    {
        $formatted = AdminFrontLogFormatter::format($log);
        $type = isset($log['type']) ? (int)$log['type'] : 0;
        $formatted['type'] = $type;
        $formatted['type_label'] = $this->frontLogTypeLabel($type);
        $formatted['delete_enabled'] = false;
        $formatted['cleanup_enabled'] = false;
        $formatted['write_message'] = '当前页面提供当前商户的登录与访问记录查询。';

        return $formatted;
    }

    private function formatMerchantAffiliateInvitee(array $invitee): array
    {
        $id = (int)($invitee['id'] ?? 0);
        $username = trim((string)($invitee['username'] ?? ''));
        $name = trim((string)($invitee['name'] ?? ''));
        $email = trim((string)($invitee['email'] ?? ''));
        $mobile = trim((string)($invitee['mobile'] ?? ''));
        $verified = (int)($invitee['is_realName'] ?? 0) === 1;

        return [
            'id' => $id,
            'username' => $username,
            'display_name' => $name !== '' ? $name : ($username !== '' ? $username : ('商户 #' . $id)),
            'name_masked' => $this->maskPersonalName($name),
            'email_masked' => $email !== '' ? $this->maskIdentifier($email) : null,
            'mobile_masked' => $mobile !== '' ? $this->maskIdentifier($mobile) : null,
            'verified' => $verified,
            'verified_label' => $verified ? '已实名' : '未实名',
            'verified_type' => $verified ? 'success' : 'warning',
            'vip_label' => trim((string)($invitee['vip_name'] ?? '')) ?: '普通商户',
            'balance_display' => $this->money($invitee['money'] ?? 0),
            'create_time' => $this->nullableString($invitee['create_time'] ?? null),
        ];
    }

    private function merchantCurrentVip(array $merchant): array
    {
        $vipId = (int)($merchant['vip_id'] ?? 0);
        $vipTime = $this->nullableString($merchant['vip_time'] ?? null);
        $isActive = $vipId > 0 && ($vipTime === null || strtotime($vipTime) === false || strtotime($vipTime) >= time());

        $base = [
            'id' => $vipId,
            'name' => '普通商户',
            'vip_time' => $vipTime,
            'is_active' => false,
            'status_label' => '暂无有效会员',
            'status_type' => 'info',
            'package' => null,
        ];

        if ($vipId <= 0) {
            return $base;
        }

        $row = $this->merchantVipQuery()
            ->where('id', $vipId)
            ->first();
        $package = $row ? AdminVipFormatter::format((array)$row) : null;
        $packageName = is_array($package) ? (string)($package['name'] ?? ('VIP #' . $vipId)) : ('VIP #' . $vipId);

        return [
            'id' => $vipId,
            'name' => $packageName,
            'vip_time' => $vipTime,
            'is_active' => $isActive,
            'status_label' => $isActive ? '会员有效' : '会员已过期',
            'status_type' => $isActive ? 'success' : 'warning',
            'package' => $package,
        ];
    }

    private function merchantChannelName(array $order): string
    {
        $payType = (int)($order['pay_type'] ?? 0);
        if ($payType === 1) {
            $localName = trim((string)($order['local_channel_name'] ?? ''));
            if ($localName !== '') {
                return $localName;
            }
        }

        if ($payType === 2) {
            $paylistName = trim((string)($order['paylist_name'] ?? ''));
            if ($paylistName !== '') {
                return $paylistName;
            }
        }

        return '未知通道';
    }

    private function merchantChannelLabel(array $order): string
    {
        $prefix = (int)($order['pay_type'] ?? 0) === 2 ? '上游通道' : '本地通道';
        return $prefix . ' / ' . $this->merchantChannelName($order);
    }

    private function paymentTypeLabel(string $type): string
    {
        return match (strtolower($type)) {
            'alipay' => '支付宝',
            'wxpay', 'wechat' => '微信支付',
            'qqpay', 'qq' => 'QQ 钱包',
            'usdt' => 'USDT',
            default => $type !== '' ? strtoupper(str_replace('_', ' ', $type)) : '未知方式',
        };
    }

    private function legacyPaymentTypeLabel(string $type): string
    {
        return match (strtolower($type)) {
            'alipay' => '支付宝',
            'wxpay', 'wechat' => '微信支付',
            'qqpay', 'qq' => 'QQ支付',
            'usdt' => 'USDT',
            default => $type !== '' ? strtoupper(str_replace('_', ' ', $type)) : '未知方式',
        };
    }

    private function legacyOrderStatusLabel(int $status): string
    {
        return match ($status) {
            1 => '已支付',
            0 => '待支付',
            default => '未知',
        };
    }

    private function merchantOrderCompatibilityTypeLabel(string $type): string
    {
        return match (strtolower($type)) {
            'alipay' => 'Alipay',
            'wxpay', 'wechat' => 'WeChat Pay',
            'qqpay', 'qq' => 'QQ Pay',
            'usdt' => 'USDT',
            default => $type !== '' ? strtoupper(str_replace('_', ' ', $type)) : 'Unknown',
        };
    }

    private function merchantOrderCompatibilityStatusLabel(int $status): string
    {
        return match ($status) {
            1 => 'Paid',
            0 => 'Pending',
            default => 'Unknown',
        };
    }

    private function frontLogTypeLabel(int $type): string
    {
        return match ($type) {
            1 => '登录事件',
            2 => '安全事件',
            3 => '商户操作',
            default => '行为日志',
        };
    }

    private function affiliateTypeLabel(int $type): string
    {
        return match ($type) {
            1 => '会员购买返佣',
            default => '充值返佣',
        };
    }

    private function realNameTypeLabel(int $type): string
    {
        return match ($type) {
            2 => '支付宝身份授权',
            default => '微信/支付宝人脸核验',
        };
    }

    private function realNameChannels(int $type): array
    {
        return [
            [
                'id' => 'wechat',
                'label' => '微信',
                'flow' => '扫码人脸核验',
                'available' => $type !== 2,
            ],
            [
                'id' => 'ali',
                'label' => '支付宝',
                'flow' => $type === 2 ? '支付宝授权实名核验' : '芝麻信用核验',
                'available' => true,
            ],
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        if (LegacyMojibakeGuard::isBrokenVoiceTipsTemplate($string, self::DEFAULT_VOICE_TIPS)) {
            return self::DEFAULT_VOICE_TIPS;
        }

        return $string === '' ? null : $string;
    }

    private function sanitizeMerchantInput(mixed $value): string
    {
        return trim(strip_tags((string)$value));
    }

    private function stringLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private function merchantFieldExists(string $field, string $value, int $ignoreMerchantId): bool
    {
        return (int)Db::table(BusinessTable::user())
            ->where($field, $value)
            ->where('id', '<>', $ignoreMerchantId)
            ->count() > 0;
    }

    private function merchantValidationError(string $message): Response
    {
        $message = $this->normalizeMerchantMessage($message);

        return json([
            'code' => 201,
            'msg' => $message,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function maskPersonalName(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            $length = mb_strlen($value, 'UTF-8');
            if ($length <= 2) {
                return mb_substr($value, 0, 1, 'UTF-8') . '*';
            }

            return mb_substr($value, 0, 1, 'UTF-8')
                . str_repeat('*', $length - 2)
                . mb_substr($value, $length - 1, 1, 'UTF-8');
        }

        $length = strlen($value);
        if ($length <= 2) {
            return substr($value, 0, 1) . '*';
        }

        return substr($value, 0, 1) . str_repeat('*', $length - 2) . substr($value, -1);
    }

    private function requestPayload(Request $request): array
    {
        $payload = RequestPayload::all($request);
        if ($payload !== []) {
            return $payload;
        }

        $post = $request->post();
        return is_array($post) ? $post : [];
    }

    private function findMerchantForLogin(string $username, string $password): ?array
    {
        $row = Db::table(BusinessTable::user())
            ->select('id', 'username', 'password', 'token', 'is_frozen', 'frozen_reason', 'googlekey')
            ->where('username', $username)
            ->where('password', LegacyPassword::hash($password))
            ->first();

        return $row ? (array)$row : null;
    }

    private function merchantLoginThrottleError(Request $request, string $username): ?Response
    {
        $maxAttempts = Environment::int('MERCHANT_LOGIN_RATE_LIMIT_MAX', 20);
        $windowSeconds = Environment::int('MERCHANT_LOGIN_RATE_LIMIT_WINDOW', 60);
        if ($maxAttempts <= 0 || $windowSeconds <= 0) {
            return null;
        }

        $ip = trim((string)$request->getRealIp());
        if ($ip === '') {
            return null;
        }

        $keys = ['merchant:login:ip:' . $ip];
        $normalizedUsername = strtolower(trim($username));
        if ($normalizedUsername !== '') {
            $keys[] = 'merchant:login:ip-user:' . $ip . ':' . $normalizedUsername;
        }

        foreach ($keys as $key) {
            $result = RequestRateLimiter::attempt($key, $maxAttempts, $windowSeconds);
            if (!$result['allowed']) {
                return $this->loginJson(429, '请求过于频繁，请稍后再试');
            }
        }

        return null;
    }

    private function requiresGoogleLogin(array $merchant, array $config): bool
    {
        $securityEnabled = (string)($config['isSecurity'] ?? '0') === '1';
        $loginSecurityEnabled = (string)($config['isSecurityLogin'] ?? '0') === '1';
        $googleKey = trim((string)($merchant['googlekey'] ?? ''));

        return $securityEnabled && $loginSecurityEnabled && $googleKey !== '';
    }

    private function rotateMerchantToken(int $merchantId): string
    {
        $newToken = substr(bin2hex(random_bytes(16)), 0, 32)
            . $merchantId
            . str_replace('.', '', sprintf('%.6f', microtime(true)));

        Db::table(BusinessTable::user())
            ->where('id', $merchantId)
            ->update(['token' => $newToken]);

        return $newToken;
    }

    private function recordMerchantFrontLog(
        int $merchantId,
        string $url,
        int $type,
        string $desc,
        Request $request
    ): void {
        if ($merchantId <= 0) {
            return;
        }

        $latest = Db::table('admin_front_log')
            ->select('id', 'create_time')
            ->where('uid', $merchantId)
            ->where('url', $url)
            ->where('type', $type)
            ->where('desc', $desc)
            ->orderByDesc('id')
            ->first();
        if ($latest) {
            $createdAt = strtotime((string)(((array)$latest)['create_time'] ?? ''));
            if ($createdAt !== false && $createdAt >= time() - self::FRONT_LOG_DUPLICATE_WINDOW_SECONDS) {
                return;
            }
        }

        Db::table('admin_front_log')->insert([
            'uid' => $merchantId,
            'url' => $url,
            'type' => $type,
            'desc' => $desc,
            'ip' => trim((string)$request->getRealIp()),
            'user_agent' => trim((string)$request->header('user-agent', '')),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function generateMerchantSecret(int $length = 32): string
    {
        return substr(bin2hex(random_bytes(max(16, (int)ceil($length / 2)))), 0, $length);
    }

    private function loginJson(int $code, string $message, array $data = []): Response
    {
        $message = $this->normalizeMerchantMessage($message);

        return json([
            'code' => $code,
            'msg' => $message,
            'message' => $message,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function merchantJson(int $code, string $message, int $status, array $data = []): Response
    {
        $message = $this->normalizeMerchantMessage($message);

        return json([
            'code' => $code,
            'msg' => $message,
            'message' => $message,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE)->withStatus($status);
    }

    private function ticketFeatureEnabled(): bool
    {
        return trim((string)SystemConfig::get('isTicket', '1')) === '1';
    }

    private function affiliateFeatureEnabled(): bool
    {
        return trim((string)SystemConfig::get('is_aff', '0')) === '1';
    }

    private function domainFeatureEnabled(): bool
    {
        return trim((string)SystemConfig::get('is_domain', '1')) === '1';
    }

    private function realNameFeatureEnabled(): bool
    {
        return trim((string)SystemConfig::get('isRealName', '0')) === '1';
    }

    private function cdkRechargeFeatureEnabled(): bool
    {
        return trim((string)SystemConfig::get('isCdkPay', '0')) === '1';
    }

    private function ticketWriteGuard(Request $request): array|Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantJson(401, 'merchant login is required', 401);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->merchantJson(201, 'merchant is frozen', 403);
        }

        if (!$this->ticketFeatureEnabled()) {
            return $this->merchantJson(202, '当前工单功能未开启', 403);
        }

        return $merchant;
    }

    private function domainWriteGuard(Request $request): array|Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantJson(401, 'merchant login is required', 401);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->merchantJson(201, 'merchant is frozen', 403);
        }

        if (!$this->domainFeatureEnabled()) {
            return $this->merchantJson(202, '当前域名管理功能未开启', 403);
        }

        return $merchant;
    }

    private function connectionMerchantGuard(Request $request): array|Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantJson(401, 'merchant login is required', 401);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->merchantJson(201, 'merchant is frozen', 403);
        }

        return $merchant;
    }

    private function connectionWriteGuard(Request $request): Response
    {
        $merchant = $this->connectionMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        return $this->blockedWriteResponse('connections');
    }

    private function securityMerchantGuard(Request $request): array|Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantJson(401, 'merchant login is required', 401);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->merchantJson(201, 'merchant is frozen', 403);
        }

        return $merchant;
    }

    private function securityWriteGuard(Request $request): Response
    {
        $merchant = $this->securityMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        return $this->blockedWriteResponse('security');
    }

    private function googleAuthIssuer(array $config): string
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

    private function googleAuthAccountLabel(array $merchant): string
    {
        $username = trim((string)($merchant['username'] ?? ''));
        if ($username !== '') {
            return $username;
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        return $merchantId > 0 ? 'merchant-' . $merchantId : 'merchant-account';
    }

    private function storeGoogleAuthBindTicket(Response $response, int $merchantId, string $secret): Response
    {
        $expiresAt = time() + self::GOOGLE_AUTH_BIND_TTL;
        $message = $merchantId . '|' . $secret . '|' . $expiresAt;
        $payload = [
            'merchant_id' => $merchantId,
            'secret' => $secret,
            'expires_at' => $expiresAt,
            'signature' => hash_hmac('sha256', $message, $this->googleAuthTicketKey()),
        ];

        return $response->cookie(
            self::GOOGLE_AUTH_BIND_COOKIE,
            $this->encodeGoogleAuthTicket($payload),
            self::GOOGLE_AUTH_BIND_TTL,
            '/'
        );
    }

    private function clearGoogleAuthBindTicket(Response $response): Response
    {
        return $response->cookie(self::GOOGLE_AUTH_BIND_COOKIE, '', 0, '/');
    }

    private function readGoogleAuthBindTicket(Request $request, int $merchantId): ?string
    {
        $ticket = $this->decodeGoogleAuthTicket((string)$request->cookie(self::GOOGLE_AUTH_BIND_COOKIE, ''));
        if ($ticket === null) {
            return null;
        }

        $expiresAt = (int)($ticket['expires_at'] ?? 0);
        $secret = trim((string)($ticket['secret'] ?? ''));
        $ticketMerchantId = (int)($ticket['merchant_id'] ?? 0);
        $signature = trim((string)($ticket['signature'] ?? ''));
        if ($ticketMerchantId !== $merchantId || $secret === '' || $expiresAt < time() || $signature === '') {
            return null;
        }

        $message = $ticketMerchantId . '|' . $secret . '|' . $expiresAt;
        $expected = hash_hmac('sha256', $message, $this->googleAuthTicketKey());
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        return $secret;
    }

    private function googleAuthTicketKey(): string
    {
        return hash('sha256', dirname(base_path(), 2) . '|' . self::GOOGLE_AUTH_BIND_COOKIE . '|webman-merchant-security');
    }

    private function encodeGoogleAuthTicket(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return '';
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private function decodeGoogleAuthTicket(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $decoded = base64_decode(strtr($value . str_repeat('=', (4 - strlen($value) % 4) % 4), '-_', '+/'), true);
        if (!is_string($decoded) || $decoded === '') {
            return null;
        }

        $payload = json_decode($decoded, true);
        return is_array($payload) ? $payload : null;
    }

    private function rechargeWriteGuard(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantJson(401, 'merchant login is required', 401);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->merchantJson(201, 'merchant is frozen', 403);
        }

        return $this->blockedWriteResponse('recharge');
    }

    private function cdkWriteGuard(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantJson(401, 'merchant login is required', 401);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->merchantJson(201, 'merchant is frozen', 403);
        }

        if (!$this->cdkRechargeFeatureEnabled()) {
            return $this->merchantJson(202, '当前卡密兑换功能未开启', 403);
        }

        return $this->blockedWriteResponse('cdk');
    }

    private function orderWriteGuard(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantJson(401, 'merchant login is required', 401);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->merchantJson(201, 'merchant is frozen', 403);
        }

        return $this->blockedWriteResponse('order');
    }

    private function merchantOrderCallbackUrls(array $order, array $merchant): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $basicRow = Db::table(BusinessTable::userBasic())
            ->select('callback_hiddenName')
            ->where('user_id', $merchantId)
            ->first();
        $hiddenName = (int)(($basicRow ? (array)$basicRow : [])['callback_hiddenName'] ?? 0) === 1;

        $payload = [
            'pid' => (string)($order['user_id'] ?? ''),
            'trade_no' => trim((string)($order['trade_no'] ?? '')),
            'out_trade_no' => trim((string)($order['out_trade_no'] ?? '')),
            'type' => trim((string)($order['type'] ?? '')),
            'money' => number_format((float)($order['money'] ?? 0), 2, '.', ''),
            'trade_status' => 'TRADE_SUCCESS',
        ];

        if (!$hiddenName) {
            $payload['name'] = trim((string)($order['name'] ?? ''));
        }

        $payload['sign'] = $this->merchantOrderCallbackSign($payload, (string)($merchant['user_key'] ?? ''));
        $payload['sign_type'] = 'MD5';

        return [
            'notify' => $this->appendMerchantQuery(
                trim((string)($order['notify_url'] ?? '')),
                $payload
            ),
            'return' => $this->appendMerchantQuery(
                trim((string)($order['return_url'] ?? '')),
                $payload
            ),
        ];
    }

    private function merchantOrderCallbackSign(array $payload, string $key): string
    {
        ksort($payload);
        $pairs = [];
        foreach ($payload as $name => $value) {
            if ($name === 'sign' || $name === 'sign_type' || $value === '' || $value === null) {
                continue;
            }

            $pairs[] = $name . '=' . (string)$value;
        }

        return md5(implode('&', $pairs) . $key);
    }

    private function appendMerchantQuery(string $url, array $query): string
    {
        if ($url === '') {
            return '';
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    private function merchantOrderCallbackMemo(array $response): string
    {
        $status = (int)($response['status'] ?? 0);
        $body = trim((string)($response['body'] ?? ''));
        if ($body === '') {
            return 'HTTP ' . $status;
        }

        $memo = 'HTTP ' . $status . ' ' . $body;
        if (mb_strlen($memo) > 500) {
            return mb_substr($memo, 0, 500);
        }

        return $memo;
    }

    private function apiKeyMerchantGuard(Request $request): array|Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantJson(401, 'merchant login is required', 401);
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->merchantJson(201, 'merchant is frozen', 403);
        }

        return $merchant;
    }

    private function apiKeyWriteGuard(Request $request): Response
    {
        $merchant = $this->apiKeyMerchantGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        return $this->blockedWriteResponse('api_key');
    }

    private function blockedWriteResponse(string $scope): Response
    {
        $payload = MerchantPortalReadOnlyGuard::response($scope);

        return $this->merchantJson(
            (int)($payload['code'] ?? 202),
            (string)($payload['message'] ?? ''),
            (int)($payload['status'] ?? 405)
        );
    }

    private function blockedTicketWriteResponse(): Response
    {
        return $this->merchantJson(202, '当前工单列表页用于查询记录，新增或删除请在对应工单页面处理。', 405);
    }

    private function blockedDomainWriteResponse(): Response
    {
        return $this->merchantJson(202, '当前域名列表页用于查询记录，新增、编辑或删除请在对应域名页面处理。', 405);
    }

    private function blockedConnectionsWriteResponse(): Response
    {
        return $this->merchantJson(202, '当前绑定中心页用于查看接入状态，绑定、解绑、验证码或扫码请在对应页面处理。', 405);
    }

    private function blockedSecurityWriteResponse(): Response
    {
        return $this->merchantJson(202, '当前安全中心页用于查看状态；密码修改、谷歌验证、实名认证和账号注销请在对应入口处理。', 405);
    }

    private function blockedRechargeWriteResponse(): Response
    {
        return $this->merchantJson(202, '当前充值页用于查询记录，充值创建与支付跳转请在对应页面处理。', 405);
    }

    private function blockedCdkWriteResponse(): Response
    {
        return $this->merchantJson(202, '当前充值页暂未开放卡密兑换。', 405);
    }

    private function blockedOrderWriteResponse(): Response
    {
        return $this->merchantJson(202, '当前订单页已支持回调重放，状态重置暂未开放。', 405);
    }

    private function blockedApiKeyWriteResponse(): Response
    {
        return $this->merchantJson(202, '当前接口信息页用于查看接入信息，请使用签名密钥或通讯密钥重置入口处理变更。', 405);
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

    private function wantsMerchantSpaPage(Request $request): bool
    {
        return strtoupper($request->method()) === 'GET' && !$this->wantsJson($request);
    }

    private function jsonOrRedirect(Request $request, string $message, string $location): Response
    {
        $message = $this->normalizeMerchantMessage($message);

        if ($this->wantsJson($request)) {
            return json(['code' => 401, 'msg' => $message, 'message' => $message], JSON_UNESCAPED_UNICODE)
                ->withStatus(401);
        }

        $targetPath = $this->merchantSpaPathForLegacyPath($location);
        if ($targetPath === '/merchant/login') {
            $requestedPath = $this->merchantSpaPathForLegacyPath($this->requestLegacyPath($request));
            $redirectPath = $requestedPath !== '/merchant/login' ? $requestedPath : null;
            return redirect($this->merchantLoginUrl($request, $redirectPath));
        }

        return $this->merchantSpaRedirect($request, $targetPath);
    }

    private function merchantLoginRequiredResponse(Request $request, string $message = 'merchant login is required'): Response
    {
        $message = $this->normalizeMerchantMessage($message);

        if ($this->wantsJson($request)) {
            return json(['code' => 401, 'msg' => $message, 'message' => $message], JSON_UNESCAPED_UNICODE)
                ->withStatus(401);
        }

        $requestedPath = $this->merchantSpaPathForLegacyPath($this->requestLegacyPath($request));
        $redirectPath = $requestedPath !== '/merchant/login' ? $requestedPath : null;

        return redirect($this->merchantLoginUrl($request, $redirectPath));
    }

    private function jsonOrHtml(Request $request, array $payload, string $html, int $status): Response
    {
        if ($this->wantsJson($request)) {
            return json($this->normalizeMerchantPayload($payload), JSON_UNESCAPED_UNICODE)->withStatus($status);
        }

        return $this->htmlResponse($html, $status);
    }

    private function normalizeMerchantPayload(array $payload): array
    {
        foreach (['msg', 'message'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key])) {
                $payload[$key] = $this->normalizeMerchantMessage($payload[$key]);
            }
        }

        return $this->stripCompatibilityMeta($payload);
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

    private function stripCompatibilityMeta(array $payload): array
    {
        foreach (['route_policy', 'migration_guard', 'legacy_url', 'legacy_routes', 'legacy_page', 'legacy_endpoint', 'legacy_action_label'] as $key) {
            unset($payload[$key]);
        }

        return $payload;
    }

    private function normalizeMerchantMessage(string $message): string
    {
        return MerchantPortalMessageCatalog::normalizeMessage($message);
    }

    private function htmlResponse(string $html, int $status = 200): Response
    {
        return response(MerchantPortalHtmlLocalizer::localize($html), $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function featureDisabledPage(Request $request, array $merchant, string $title, string $summary, string $detail): string
    {
        $displayName = $this->escape($this->merchantDisplay($merchant));
        $title = $this->escape($title);
        $summary = $this->escape($summary);
        $detail = $this->escape($detail);
        $merchantBase = $this->merchantFrontendBaseUrl($request);
        $dashboardUrl = $this->escape($this->withHashPath($merchantBase, '/merchant/dashboard'));
        $profileUrl = $this->escape($this->withHashPath($merchantBase, '/merchant/profile'));
        $securityUrl = $this->escape($this->withHashPath($merchantBase, '/merchant/security'));
        $ordersUrl = $this->escape($this->withHashPath($merchantBase, '/merchant/orders'));
        $loginLogsUrl = $this->escape($this->withHashPath($merchantBase, '/merchant/login-logs'));
        $logoutUrl = $this->escape(rtrim($this->requestOrigin($request), '/') . '/api/merchant/logout');

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title}</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f6f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:980px;margin:0 auto 18px;padding:28px;border-radius:24px;background:linear-gradient(135deg,#172033,#854d0e);color:#fff;box-shadow:0 20px 60px rgba(15,23,42,.18)}
    .hero h1{margin:0 0 8px;font-size:30px}
    .hero p{margin:0;color:#fef3c7;line-height:1.7}
    .notice{max-width:980px;margin:0 auto 16px;padding:16px 18px;border-radius:18px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;line-height:1.8}
    .panel{max-width:980px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:22px;box-shadow:0 14px 36px rgba(15,23,42,.06);line-height:1.8}
    .actions{max-width:980px;margin:16px auto 0;display:flex;gap:10px;flex-wrap:wrap}
    .btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#0f172a;color:#fff;text-decoration:none}
    .btn.secondary{background:#e2e8f0;color:#0f172a}
    @media (max-width:560px){.shell{padding:18px}.hero{padding:22px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>{$title}</h1>
      <p>{$displayName}，{$summary}</p>
    </section>
    <section class="notice">当前入口已改为可直接访问，不再静默跳回首页。页面会明确展示功能状态，避免出现“点击无反应”的错觉。</section>
    <section class="panel">
      <p>{$detail}</p>
      <p>如需继续排查或开放该功能，建议先在系统配置中确认相关开关与业务规则，再联动回归管理员端与商户端表现。</p>
    </section>
    <nav class="actions">
      <a class="btn secondary" href="{$dashboardUrl}">商户中心</a>
      <a class="btn secondary" href="{$profileUrl}">资料维护</a>
      <a class="btn secondary" href="{$securityUrl}">安全中心</a>
      <a class="btn secondary" href="{$ordersUrl}">订单记录</a>
      <a class="btn secondary" href="{$loginLogsUrl}">登录日志</a>
      <a class="btn" href="{$logoutUrl}">退出登录</a>
    </nav>
  </div>
</body>
</html>
HTML;
    }


    private function frozenPage(array $merchant): string
    {
        $displayName = $this->escape($this->merchantDisplay($merchant));
        $reason = $this->escape((string)($merchant['frozen_reason'] ?? '未提供原因说明。'));

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>商户账户已冻结</title>
</head>
<body>
  <h1>商户账户已冻结</h1>
  <p>{$displayName} 当前暂时无法进入商户中心。</p>
  <p>{$reason}</p>
</body>
</html>
HTML;
    }

    private function orderStats(int $merchantId): array
    {
        $todayStart = date('Y-m-d 00:00:00');
        $row = Db::table(BusinessTable::order())
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as paid_order_count')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN truemoney ELSE 0 END) as paid_amount')
            ->selectRaw('SUM(CASE WHEN status = 1 AND create_time >= ? THEN truemoney ELSE 0 END) as today_paid_amount', [$todayStart])
            ->selectRaw('MAX(create_time) as last_order_time')
            ->where('user_id', $merchantId)
            ->first();

        return $row ? (array)$row : [];
    }

    private function channelStats(int $merchantId): array
    {
        return [
            'account_count' => (int)Db::table(BusinessTable::account())->where('user_id', $merchantId)->count(),
            'upstream_count' => (int)Db::table(BusinessTable::paylist())->where('user_id', $merchantId)->where('status', 1)->count(),
        ];
    }

    private function merchantDisplay(array $merchant): string
    {
        $username = trim((string)($merchant['username'] ?? ''));

        return $username !== '' ? $username : ('Merchant #' . (int)($merchant['id'] ?? 0));
    }

    private function vipLabel(array $merchant): string
    {
        $vipId = (int)($merchant['vip_id'] ?? 0);
        $vipName = trim((string)($merchant['vip_name'] ?? ''));
        $vipTime = trim((string)($merchant['vip_time'] ?? ''));
        if ($vipId <= 0) {
            return '普通商户';
        }

        $expired = $vipTime !== '' && strtotime($vipTime) !== false && strtotime($vipTime) < time();
        $label = $vipName !== '' ? $vipName : '会员商户';

        return $expired ? ($label . '（已过期）') : $label;
    }

    private function feeRate(mixed $value): string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return '--';
        }

        return rtrim(rtrim($raw, '0'), '.') . '%';
    }

    private function merchantHasEnoughBalance(int $merchantId, float $amount): bool
    {
        if ($amount <= 0) {
            return true;
        }

        $balance = (float)(Db::table(BusinessTable::user())
            ->where('id', $merchantId)
            ->value('money') ?? 0);

        return round($balance, 2) >= round($amount, 2);
    }

    private function deductMerchantRealNameFee(int $merchantId, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        Db::transaction(function () use ($merchantId, $amount): void {
            $merchantRow = Db::table(BusinessTable::user())
                ->where('id', $merchantId)
                ->lockForUpdate()
                ->first();

            if (!$merchantRow) {
                throw new \RuntimeException('merchant login is required');
            }

            $before = round((float)($merchantRow->money ?? 0), 2);
            if ($before < round($amount, 2)) {
                throw new \RuntimeException('merchant real-name verification fee balance is insufficient');
            }

            $after = round($before - $amount, 2);
            $now = date('Y-m-d H:i:s');

            Db::table(BusinessTable::user())
                ->where('id', $merchantId)
                ->update([
                    'money' => number_format($after, 2, '.', ''),
                ]);

            Db::table('money_log')->insert([
                'user_id' => $merchantId,
                'type' => null,
                'money' => number_format(-$amount, 2, '.', ''),
                'beforemoney' => number_format($before, 2, '.', ''),
                'after' => number_format($after, 2, '.', ''),
                'memo' => '实名认证费用扣除',
                'create_time' => $now,
            ]);
        });
    }

    private function realNameCallbackResultPage(Request $request, bool $success, string $message): string
    {
        $title = $success ? '实名认证结果' : '实名认证失败';
        $statusLabel = $success ? '处理完成' : '处理异常';
        $statusClass = $success ? 'success' : 'danger';
        $escapedTitle = $this->escape($title);
        $escapedStatus = $this->escape($statusLabel);
        $escapedMessage = $this->escape($message);
        $merchantCenter = $this->escape($this->withHashPath($this->merchantFrontendBaseUrl($request), '/merchant/real-name'));

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$escapedTitle}</title>
  <style>
    :root{color-scheme:light}
    *{box-sizing:border-box}
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f4f7fb;color:#172033}
    main{max-width:560px;margin:10vh auto;padding:32px;border-radius:24px;background:#fff;box-shadow:0 24px 60px rgba(15,23,42,.08)}
    .badge{display:inline-flex;align-items:center;padding:0 12px;height:34px;border-radius:999px;font-size:13px;font-weight:600}
    .badge.success{color:#0f766e;background:#ccfbf1}
    .badge.danger{color:#b42318;background:#fee4e2}
    h1{margin:16px 0 10px;font-size:28px;color:#0f172a}
    p{margin:0;color:#475467;line-height:1.9}
    .actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}
    .btn{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 18px;border-radius:999px;text-decoration:none;font-weight:600}
    .btn.primary{color:#fff;background:#5677ff}
    .btn.secondary{color:#334155;background:#eef2ff}
  </style>
</head>
<body>
  <main>
    <span class="badge {$statusClass}">{$escapedStatus}</span>
    <h1>{$escapedTitle}</h1>
    <p>{$escapedMessage}</p>
    <div class="actions">
      <a class="btn primary" href="{$merchantCenter}">返回商户实名认证</a>
      <a class="btn secondary" href="{$merchantCenter}">刷新认证状态</a>
    </div>
  </main>
</body>
</html>
HTML;
    }

    private function money(mixed $value): string
    {
        return number_format((float)$value, 2, '.', '');
    }

    private function logMoney(mixed $value): string
    {
        return number_format((float)$value, 3, '.', '');
    }

    private function signedLogMoney(float $amount): string
    {
        $prefix = $amount > 0 ? '+' : '';

        return $prefix . $this->logMoney($amount);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
