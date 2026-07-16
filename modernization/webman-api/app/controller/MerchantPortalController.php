<?php

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
use app\support\DatabaseColumnInspector;
use app\support\GoogleAuthenticator;
use app\support\LegacyPassword;
use app\support\LegacyMojibakeGuard;
use app\support\MerchantFrontSession;
use app\support\MerchantSmsCodeSender;
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
    private const DEFAULT_VOICE_TIPS = '尊敬的用户，你本次交易金额为[money]';

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
            '/User/Index',
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
        return redirect($this->merchantLoginUrl($request))
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
            return $this->blockedConnectionsWriteResponse();
        }

        $payload = $this->merchantConnectionsPayload($merchant);
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

        Db::table('ypay_user')
            ->where('id', (int)($merchant['id'] ?? 0))
            ->update($updates);

        return json([
            'code' => 1,
            'msg' => '解绑成功',
            'message' => '解绑成功',
            'data' => [
                'type' => $type,
                'bindings' => $this->merchantConnectionsPayload(array_replace($merchant, $updates)),
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
        $enrollment = $this->merchantWxPusherEnrollmentPayload($merchant, $config);
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
                $this->createWxPusherQrCode(
                    $request,
                    (int)($merchant['id'] ?? 0),
                    $this->wxPusherAppToken($config),
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
            'msg' => $code === 1 ? 'wxpusher uid is already configured' : 'wxpusher uid is not configured yet',
            'message' => $code === 1 ? 'wxpusher uid is already configured' : 'wxpusher uid is not configured yet',
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
                'msg' => $currentUid !== ''
                    ? 'wxpusher uid is already configured'
                    : 'please submit a wxpusher uid before saving',
                'message' => $currentUid !== ''
                    ? 'wxpusher uid is already configured'
                    : 'please submit a wxpusher uid before saving',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($this->stringLength($uid) > 50) {
            return $this->merchantValidationError('wxpusher uid is too long');
        }

        Db::table('ypay_user')
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

        Db::table('ypay_user')
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
        Db::table('ypay_user')
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
                'bindings' => $this->merchantConnectionsPayload($updatedMerchant),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->clearMerchantConnectionVerifyTicket($response);
    }

    private function merchantConnectionFeatureError(string $channel, array $config): ?Response
    {
        if ($channel === 'email' && trim((string)($config['email_switch'] ?? '0')) !== '1') {
            return $this->merchantValidationError('merchant email verification feature is disabled');
        }

        if ($channel === 'mobile' && trim((string)($config['code_switch'] ?? '0')) !== '1') {
            return $this->merchantValidationError('merchant mobile verification feature is disabled');
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
            return $this->blockedSecurityWriteResponse();
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

        Db::table('ypay_user')
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
                    'account_cancellation' => $this->merchantAccountCancellationPayload($merchant),
                ]);
            }

            return $this->merchantSpaRedirectForCurrentRequest($request);
        }

        if (!$this->merchantCancellationFeatureEnabled()) {
            return $this->merchantJson(202, '商户账号注销功能未开启', 403, [
                'account_cancellation' => $this->merchantAccountCancellationPayload($merchant),
            ]);
        }

        $audit = $this->merchantCancellationAudit($merchant);
        $payload = $this->requestPayload($request);
        $confirmation = trim((string)($payload['confirmation'] ?? $payload['confirm'] ?? $payload['phrase'] ?? ''));

        if ($confirmation === '') {
            return $this->merchantJson(422, '请输入注销确认口令', 422, [
                'account_cancellation' => $this->merchantAccountCancellationPayload($merchant, $audit),
            ]);
        }

        if (!hash_equals((string)($audit['confirmation_phrase'] ?? ''), $confirmation)) {
            return $this->merchantJson(422, '注销确认口令不正确', 422, [
                'account_cancellation' => $this->merchantAccountCancellationPayload($merchant, $audit),
            ]);
        }

        if (!($audit['can_delete'] ?? false)) {
            return $this->merchantJson(422, '当前账号暂不满足注销条件，请先处理拦截项', 422, [
                'account_cancellation' => $this->merchantAccountCancellationPayload($merchant, $audit),
            ]);
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        Db::transaction(function () use ($merchantId): void {
            $this->deleteMerchantOwnedRowsForCancellation($merchantId);
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
        Db::table('ypay_user')
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

        $merchantId = (int)($merchant['id'] ?? 0);
        $this->ensureMerchantBasicRecord($merchantId);
        $key = $this->generateMerchantSecret(32);
        Db::table('ypay_userbasic')
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
            return $this->blockedSecurityWriteResponse();
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

        Db::table('ypay_user')
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
        Db::table('ypay_user')
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
                return $this->merchantJson(202, 'merchant affiliate feature is disabled', 200);
            }

            return $this->htmlResponse($this->featureDisabledPage(
                $merchant,
                '推广返佣',
                '当前系统尚未开启推广返佣功能。',
                '如需开放推广返佣，请先在系统配置中启用邀请返佣开关。功能关闭期间，推广记录与返佣发放入口不会对商户开放。'
            ));
        }

        if (strtoupper($request->method()) !== 'GET') {
            return $this->merchantJson(202, '当前推广返佣页仅提供查询统计，不提供邀请链接重置或返佣提现写入。', 405);
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
            return $this->merchantJson(202, 'merchant affiliate feature is disabled', 200);
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
                return $this->merchantJson(202, 'merchant real-name feature is disabled', 200);
            }

            return $this->htmlResponse($this->featureDisabledPage(
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
            return $this->merchantJson(202, 'merchant real-name feature is disabled', 200);
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
            Db::table('ypay_user')
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
                $callbackUrl = $this->requestOrigin($request) . '/My/Real_name';
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
            return $this->merchantJson(202, 'merchant real-name feature is disabled', 200);
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
                'status_endpoint' => '/My/getRealNameStatus',
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
            return $this->merchantJson(202, 'merchant real-name feature is disabled', 200);
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
                    Db::table('ypay_user')
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
                'status_endpoint' => '/My/getRealNameStatus',
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
            Db::table('ypay_user')
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
            Db::table('ypay_user')
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
                Db::table('ypay_user')
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
                $merchantRow = Db::table('ypay_user')
                    ->where('id', $merchantId)
                    ->lockForUpdate()
                    ->first();
                if (!$merchantRow) {
                    throw new \InvalidArgumentException('商户不存在');
                }

                $cdkRow = Db::table('ypay_cdk')
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

                        Db::table('ypay_user')
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
                        $vipRow = Db::table('ypay_vip')
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

                        Db::table('ypay_user')
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

                Db::table('ypay_cdk')
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
                'msg' => '当前商户中心仅保留订单回调重放，状态重置已下线',
                'message' => '当前商户中心仅保留订单回调重放，状态重置已下线',
            ], JSON_UNESCAPED_UNICODE)->withStatus(405);
        }

        $orderRow = $this->merchantOrderQuery((int)($merchant['id'] ?? 0))
            ->where('ypay_order.id', $orderId)
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
            'dispatch_now' => true,
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
        Db::table('ypay_order')
            ->where('id', $orderId)
            ->where('user_id', (int)($merchant['id'] ?? 0))
            ->update([
                'api_memo' => $memo,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        $updatedOrder = $this->merchantOrderQuery((int)($merchant['id'] ?? 0))
            ->where('ypay_order.id', $orderId)
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

                $merchantRow = Db::table('ypay_user')
                    ->select('id', 'money', 'vip_id', 'vip_time', 'feilv', 'superior_id')
                    ->where('id', $merchantId)
                    ->lockForUpdate()
                    ->first();
                if (!$merchantRow) {
                    throw new \InvalidArgumentException('merchant login is required');
                }

                $vipRow = Db::table('ypay_vip')
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

                Db::table('ypay_user')
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

        $superiorRow = Db::table('ypay_user')
            ->where('id', $superiorId)
            ->lockForUpdate()
            ->first();
        if (!$superiorRow) {
            return;
        }

        $superior = (array)$superiorRow;
        $before = round((float)($superior['money'] ?? 0), 2);
        $after = round($before + $rebate, 2);

        Db::table('ypay_user')
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
            return $this->blockedApiKeyWriteResponse();
        }

        $payload = $this->merchantApiPayload($request, $merchant);
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
        $basic = $this->merchantBasic($merchantId);
        $appkey = trim((string)($basic['appkey'] ?? ''));
        $appkeyGenerated = false;
        if ($appkey === '') {
            $bootstrapAppkey = filter_var($request->post('bootstrap_appkey', false), FILTER_VALIDATE_BOOLEAN);
            if (!$bootstrapAppkey) {
                return $this->merchantJson(422, '通讯密钥未配置，请先生成后再展示商户二维码', 422);
            }

            $this->ensureMerchantBasicRecord($merchantId);
            $key = $this->generateMerchantSecret(32);
            Db::table('ypay_userbasic')
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
                $merchant,
                '工单中心',
                '当前系统尚未开启工单功能。',
                '工单开关关闭时，商户端不会开放工单创建、删除与跟进入口。如需开放，请先在系统配置中启用工单功能。'
            ));
        }

        if (strtoupper($request->method()) !== 'GET') {
            return $this->blockedTicketWriteResponse();
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

        Db::table('ypay_ticket')->insert($ticketData);
        $ticketId = (int)Db::getPdo()->lastInsertId();
        $created = $this->merchantTicketQuery((int)($merchant['id'] ?? 0))
            ->where('ypay_ticket.id', $ticketId)
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

        $query = Db::table('ypay_ticket')
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
                $merchant,
                '域名管理',
                '当前系统尚未开启域名功能。',
                '域名审核开关关闭时，商户端不会开放域名提交、修改与删除入口。如需开放，请先在系统配置中启用域名功能。'
            ));
        }

        if (strtoupper($request->method()) !== 'GET') {
            return $this->blockedDomainWriteResponse();
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
        Db::table('ypay_domain')->insert($data);
        $domainId = (int)Db::getPdo()->lastInsertId();
        $created = $this->merchantDomainQuery((int)($merchant['id'] ?? 0))
            ->where('ypay_domain.id', $domainId)
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
            ->where('ypay_domain.id', $id)
            ->first();
        if (!$existing) {
            return $this->merchantValidationError('域名不存在');
        }

        $validated = $this->validateMerchantDomainPayload($request, (int)($merchant['id'] ?? 0));
        if ($validated instanceof Response) {
            return $validated;
        }

        $updates = $this->prepareMerchantDomainData($validated, (int)($merchant['id'] ?? 0));
        Db::table('ypay_domain')
            ->where('id', $id)
            ->where('user_id', (int)($merchant['id'] ?? 0))
            ->whereNull('delete_time')
            ->update($updates);

        $updated = $this->merchantDomainQuery((int)($merchant['id'] ?? 0))
            ->where('ypay_domain.id', $id)
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
            ->where('ypay_domain.id', $id)
            ->first();
        if (!$existing) {
            return $this->merchantValidationError('域名不存在');
        }

        Db::table('ypay_domain')
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
            ->where('ypay_order.id', $id)
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
            '/User/Login',
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
        $row = Db::table('ypay_user')
            ->leftJoin('ypay_vip', 'ypay_user.vip_id', '=', 'ypay_vip.id')
            ->select(
                'ypay_user.id',
                'ypay_user.username',
                'ypay_user.email',
                'ypay_user.mobile',
                'ypay_user.wxpusher_uid',
                'ypay_user.tg_chat_id',
                'ypay_user.is_bindqq',
                'ypay_user.qq_sid',
                'ypay_user.is_bindwx',
                'ypay_user.wx_sid',
                'ypay_user.googlekey',
                'ypay_user.is_realName',
                'ypay_user.name',
                'ypay_user.idCard',
                'ypay_user.superior_id',
                'ypay_user.money',
                'ypay_user.vip_id',
                'ypay_user.vip_time',
                'ypay_user.feilv',
                'ypay_user.user_key',
                'ypay_user.create_time',
                'ypay_user.is_frozen',
                'ypay_user.frozen_reason',
                'ypay_vip.name as vip_name'
            )
            ->where('ypay_user.id', $merchantId)
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

        $total = (int)(clone $query)->count('ypay_order.id');
        $rows = $query
            ->orderByDesc('ypay_order.id')
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

        $total = (int)(clone $query)->count('ypay_recharge.id');
        $rows = $query
            ->orderByDesc('ypay_recharge.id')
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

        $total = (int)(clone $query)->count('ypay_ticket.id');
        $rows = $query
            ->orderByDesc('ypay_ticket.id')
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

        $total = (int)(clone $query)->count('ypay_domain.id');
        $rows = $query
            ->orderByDesc('ypay_domain.id')
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
        $basic = $this->merchantBasic($merchantId);
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
        $basic = $this->merchantNotificationBasic($merchantId);
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
                'template' => $this->nullableString($basic['voice_tips'] ?? null) ?? '尊敬的用户，你本次交易金额为[money]',
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
            Db::table('ypay_user')
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

        if ((int)Db::table('ypay_userbasic')->where('user_id', $merchantId)->count() > 0) {
            Db::table('ypay_userbasic')
                ->where('user_id', $merchantId)
                ->update($updates);
        } else {
            Db::table('ypay_userbasic')->insert(array_merge(['user_id' => $merchantId], $updates));
        }

        return json([
            'code' => 200,
            'msg' => '通知设置保存成功',
            'message' => '通知设置保存成功',
            'data' => $this->merchantNotificationsPayload($merchant),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function merchantConnectionsPayload(array $merchant): array
    {
        $config = SystemConfig::all();
        $quickLogins = $this->merchantQuickLoginBindings($merchant, $config);
        $contactBindings = $this->merchantContactBindings($merchant, $config);
        $wxPusherEnrollment = $this->merchantWxPusherEnrollmentPayload($merchant, $config);
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

    private function merchantWxPusherEnrollmentPayload(array $merchant, array $config): array
    {
        $enabled = trim((string)($config['wxpusher_switch'] ?? '0')) === '1';
        $tokenConfigured = $this->wxPusherAppToken($config) !== '';
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
            'uid_masked' => $this->maskIdentifier($currentUid),
            'manual_save_allowed' => true,
            'write_allowed' => $writeAllowed,
            'write_message' => $writeMessage,
            'expires_seconds' => 1800,
            'callback_entry' => '/Notify/wxpusher',
        ];
    }

    private function wxPusherAppToken(array $config): string
    {
        return trim((string)($config['wxpusher_appToken'] ?? ''));
    }

    private function createWxPusherQrCode(Request $request, int $merchantId, string $appToken, int $validSeconds = 1800): array
    {
        if ($merchantId <= 0) {
            throw new \RuntimeException('商户信息无效，无法生成 WxPusher 绑定二维码');
        }

        if ($appToken === '') {
            throw new \RuntimeException('请先配置 WxPusher 应用令牌');
        }

        $payload = json_encode([
            'appToken' => $appToken,
            'extra' => (string)$merchantId,
            'validTime' => max(60, $validSeconds),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($payload) || $payload === '') {
            throw new \RuntimeException('生成 WxPusher 请求参数失败');
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
                    ? 'WxPusher 请求失败，请检查网络或应用令牌配置'
                    : 'WxPusher 请求失败，请稍后重试'
            );
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException('WxPusher 服务暂时不可用，请稍后重试');
        }

        $decoded = json_decode((string)$body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('WxPusher 返回了无法识别的数据');
        }

        if (empty($decoded['success']) || !is_array($decoded['data'] ?? null)) {
            $message = trim((string)($decoded['msg'] ?? ''));
            throw new \RuntimeException(
                $message !== '' && preg_match('/[\x{4e00}-\x{9fff}]/u', $message)
                    ? $message
                    : 'WxPusher 二维码生成失败，请检查应用令牌配置'
            );
        }

        $data = $decoded['data'];
        $shortUrl = $this->nullableString($data['shortUrl'] ?? $data['short_url'] ?? null);
        $url = $this->nullableString($data['url'] ?? null);
        $scanContent = $url ?? $shortUrl;

        if ($scanContent === null) {
            throw new \RuntimeException('WxPusher 未返回可用的二维码内容');
        }

        return [
            'shortUrl' => $shortUrl,
            'short_url' => $shortUrl,
            'url' => $url,
            'extra' => $this->nullableString($data['extra'] ?? null) ?? (string)$merchantId,
            'expires' => $this->nullableString($data['expires'] ?? null),
            'expires_at' => $this->nullableString($data['expires'] ?? null),
            'qrcode_url' => $this->merchantQrCodeUrl($request, $scanContent, '260x260'),
        ];
    }

    private function merchantSecurityPayload(Request $request, array $merchant): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $config = SystemConfig::all();
        $logs = $this->merchantLoginLogsPayload($request, $merchantId, 5);
        $googleKey = trim((string)($merchant['googlekey'] ?? ''));
        $pendingGoogleSecret = $googleKey === '' ? $this->readGoogleAuthBindTicket($request, $merchantId) : null;
        $isSecurityEnabled = trim((string)($config['isSecurity'] ?? '0')) === '1';
        $isSecurityForceEnabled = trim((string)($config['isSecurityForce'] ?? '0')) === '1';
        $isSecurityLoginEnabled = trim((string)($config['isSecurityLogin'] ?? '0')) === '1';
        $isRealNameFeatureEnabled = trim((string)($config['isRealName'] ?? '0')) === '1';
        $isRealNameVerified = (int)($merchant['is_realName'] ?? 0) === 1;
        $googleSetupAccount = $this->googleAuthAccountLabel($merchant);
        $googleSetupIssuer = $this->googleAuthIssuer($config);
        $google = $pendingGoogleSecret !== null ? new GoogleAuthenticator() : null;
        $cancellation = $this->merchantAccountCancellationPayload($merchant);

        return [
            'merchant_id' => $merchantId,
            'merchant_username' => trim((string)($merchant['username'] ?? '')),
            'security_center' => [
                'enabled' => $isSecurityEnabled,
                'force_bind' => $isSecurityForceEnabled,
                'login_verification_required' => $isSecurityLoginEnabled,
                'provider_name' => $this->nullableString($config['securityName'] ?? null) ?? '谷歌验证器',
                'provider_icon' => $this->nullableString($config['securityIcon'] ?? null),
                'bind_tips' => $this->nullableString($config['securityBindTips'] ?? null),
                'popup_title' => $this->nullableString($config['securityPopTitle'] ?? null),
                'popup_content' => $this->nullableString($config['securityPopContent'] ?? null),
            ],
            'password' => [
                'update_allowed' => true,
                'minimum_length' => 6,
                'legacy_route' => '/My/UpdatePwd',
                'write_message' => '商户密码修改已生效，保存后需要重新登录',
            ],
            'google_auth' => [
                'bound' => $googleKey !== '',
                'status_label' => $googleKey !== '' ? '已绑定' : '未绑定',
                'status_type' => $googleKey !== '' ? 'success' : 'warning',
                'secret_masked' => $this->maskSecret($googleKey),
                'verification_page' => '/My/GoogleAuth',
                'verification_required_at_login' => $isSecurityEnabled && $isSecurityLoginEnabled,
                'verification_allowed' => false,
                'bind_allowed' => $googleKey === '',
                'unbind_allowed' => $googleKey !== '',
                'setup_pending' => $pendingGoogleSecret !== null,
                'setup_account' => $pendingGoogleSecret !== null ? $googleSetupAccount : null,
                'setup_issuer' => $pendingGoogleSecret !== null ? $googleSetupIssuer : null,
                'setup_secret' => $pendingGoogleSecret,
                'setup_secret_masked' => $this->maskSecret((string)$pendingGoogleSecret),
                'setup_qrcode_url' => $pendingGoogleSecret !== null && $google !== null
                    ? $google->getQRCodeGoogleUrl($googleSetupAccount, $pendingGoogleSecret, $googleSetupIssuer)
                    : null,
                'otp_auth_url' => $pendingGoogleSecret !== null && $google !== null
                    ? $google->buildOtpAuthUrl($googleSetupAccount, $pendingGoogleSecret, $googleSetupIssuer)
                    : null,
                'write_message' => $googleKey !== ''
                    ? '谷歌验证解绑功能已接入当前系统；登录时是否校验仍由系统安全开关决定。'
                    : '谷歌验证绑定功能已接入当前系统；登录时是否校验仍由系统安全开关决定。',
            ],
            'real_name' => [
                'feature_enabled' => $isRealNameFeatureEnabled,
                'verified' => $isRealNameVerified,
                'status_label' => !$isRealNameFeatureEnabled
                    ? '未开启'
                    : ($isRealNameVerified ? '已认证' : '未认证'),
                'status_type' => !$isRealNameFeatureEnabled
                    ? 'info'
                    : ($isRealNameVerified ? 'success' : 'warning'),
                'id_card_masked' => $isRealNameVerified ? $this->maskIdentifier((string)($merchant['idCard'] ?? '')) : null,
                'write_allowed' => $isRealNameFeatureEnabled && !$isRealNameVerified,
                'write_message' => !$isRealNameFeatureEnabled
                    ? '系统未开启实名认证功能。'
                    : ($isRealNameVerified
                        ? '当前商户已完成实名认证。'
                        : '实名认证发起已接入当前商户后台。'),
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

    private function merchantSecurityPayloadV2(Request $request, array $merchant): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $config = SystemConfig::all();
        $logs = $this->merchantLoginLogsPayload($request, $merchantId, 5);
        $googleKey = trim((string)($merchant['googlekey'] ?? ''));
        $pendingGoogleSecret = $googleKey === '' ? $this->readGoogleAuthBindTicket($request, $merchantId) : null;
        $isSecurityEnabled = trim((string)($config['isSecurity'] ?? '0')) === '1';
        $isSecurityForceEnabled = trim((string)($config['isSecurityForce'] ?? '0')) === '1';
        $isSecurityLoginEnabled = trim((string)($config['isSecurityLogin'] ?? '0')) === '1';
        $isRealNameFeatureEnabled = trim((string)($config['isRealName'] ?? '0')) === '1';
        $isRealNameVerified = (int)($merchant['is_realName'] ?? 0) === 1;
        $googleSetupAccount = $this->googleAuthAccountLabel($merchant);
        $googleSetupIssuer = $this->googleAuthIssuer($config);
        $google = $pendingGoogleSecret !== null ? new GoogleAuthenticator() : null;
        $cancellation = $this->merchantAccountCancellationPayload($merchant);

        return [
            'merchant_id' => $merchantId,
            'merchant_username' => trim((string)($merchant['username'] ?? '')),
            'security_center' => [
                'enabled' => $isSecurityEnabled,
                'force_bind' => $isSecurityForceEnabled,
                'login_verification_required' => $isSecurityLoginEnabled,
                'provider_name' => $this->nullableString($config['securityName'] ?? null) ?? '谷歌验证器',
                'provider_icon' => $this->nullableString($config['securityIcon'] ?? null),
                'bind_tips' => $this->nullableString($config['securityBindTips'] ?? null),
                'popup_title' => $this->nullableString($config['securityPopTitle'] ?? null),
                'popup_content' => $this->nullableString($config['securityPopContent'] ?? null),
            ],
            'password' => [
                'update_allowed' => true,
                'minimum_length' => 6,
                'legacy_route' => '/My/UpdatePwd',
                'write_message' => '商户密码修改已生效，保存后需要重新登录',
            ],
            'google_auth' => [
                'bound' => $googleKey !== '',
                'status_label' => $googleKey !== '' ? '已绑定' : '未绑定',
                'status_type' => $googleKey !== '' ? 'success' : 'warning',
                'secret_masked' => $this->maskSecret($googleKey),
                'verification_page' => '/My/GoogleAuth',
                'verification_required_at_login' => $isSecurityEnabled && $isSecurityLoginEnabled,
                'verification_allowed' => false,
                'bind_allowed' => $googleKey === '',
                'unbind_allowed' => $googleKey !== '',
                'setup_pending' => $pendingGoogleSecret !== null,
                'setup_account' => $pendingGoogleSecret !== null ? $googleSetupAccount : null,
                'setup_issuer' => $pendingGoogleSecret !== null ? $googleSetupIssuer : null,
                'setup_secret' => $pendingGoogleSecret,
                'setup_secret_masked' => $this->maskSecret((string)$pendingGoogleSecret),
                'setup_qrcode_url' => $pendingGoogleSecret !== null && $google !== null
                    ? $google->getQRCodeGoogleUrl($googleSetupAccount, $pendingGoogleSecret, $googleSetupIssuer)
                    : null,
                'otp_auth_url' => $pendingGoogleSecret !== null && $google !== null
                    ? $google->buildOtpAuthUrl($googleSetupAccount, $pendingGoogleSecret, $googleSetupIssuer)
                    : null,
                'write_message' => $googleKey !== ''
                    ? '谷歌验证解绑功能已接入当前系统；登录时是否校验仍由系统安全开关决定。'
                    : '谷歌验证绑定功能已接入当前系统；登录时是否校验仍由系统安全开关决定。',
            ],
            'real_name' => [
                'feature_enabled' => $isRealNameFeatureEnabled,
                'verified' => $isRealNameVerified,
                'status_label' => !$isRealNameFeatureEnabled
                    ? '未开启'
                    : ($isRealNameVerified ? '已认证' : '未认证'),
                'status_type' => !$isRealNameFeatureEnabled
                    ? 'info'
                    : ($isRealNameVerified ? 'success' : 'warning'),
                'id_card_masked' => $isRealNameVerified ? $this->maskIdentifier((string)($merchant['idCard'] ?? '')) : null,
                'write_allowed' => $isRealNameFeatureEnabled && !$isRealNameVerified,
                'write_message' => !$isRealNameFeatureEnabled
                    ? '系统未开启实名认证功能。'
                    : ($isRealNameVerified
                        ? '当前商户已完成实名认证。'
                        : '实名认证发起已接入当前商户后台。'),
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

    private function merchantCancellationFeatureEnabled(): bool
    {
        return trim((string)SystemConfig::get('is_logOff', '0')) === '1';
    }

    private function merchantAccountCancellationPayload(array $merchant, ?array $audit = null): array
    {
        $featureEnabled = $this->merchantCancellationFeatureEnabled();
        $audit = $audit ?? $this->merchantCancellationAudit($merchant);
        $canSubmit = $featureEnabled && (bool)($audit['can_delete'] ?? false);
        $summary = (array)($audit['summary'] ?? []);

        return [
            'feature_enabled' => $featureEnabled,
            'can_submit' => $canSubmit,
            'confirmation_phrase' => $featureEnabled ? (string)($audit['confirmation_phrase'] ?? '') : '',
            'merchant_username' => (string)($audit['merchant_username'] ?? ($merchant['username'] ?? '')),
            'balance_amount' => number_format((float)($merchant['money'] ?? 0), 2, '.', ''),
            'blocking_reasons' => array_values(array_map('strval', (array)($audit['blocking_reasons'] ?? []))),
            'warnings' => array_values(array_map('strval', (array)($audit['warnings'] ?? []))),
            'related_counts' => array_values((array)($audit['related_counts'] ?? [])),
            'summary' => [
                'delete_row_count' => (int)($summary['delete_row_count'] ?? 0),
                'non_empty_target_count' => (int)($summary['non_empty_target_count'] ?? 0),
                'blocking_reference_count' => (int)($summary['blocking_reference_count'] ?? 0),
                'balance_blocked' => (bool)($summary['balance_blocked'] ?? false),
                'pending_order_count' => (int)($summary['pending_order_count'] ?? 0),
                'pending_recharge_count' => (int)($summary['pending_recharge_count'] ?? 0),
                'subordinate_count' => (int)($summary['subordinate_count'] ?? 0),
            ],
            'write_message' => !$featureEnabled
                ? '系统未开启商户账号注销功能。'
                : ($canSubmit
                    ? '账号注销已开放，提交后会清理当前商户及其归属数据，并立即退出登录。'
                    : '当前账号暂不满足注销条件，请先处理拦截项后再提交。'),
        ];
    }

    private function merchantCancellationAudit(array $merchant): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $subordinateCount = $this->countMerchantCancellationRows('ypay_user', 'superior_id', $merchantId);
        $pendingOrderCount = $this->merchantCancellationPendingCount('ypay_order', 'user_id', $merchantId);
        $pendingRechargeCount = $this->merchantCancellationPendingCount('ypay_recharge', 'user_id', $merchantId);
        $balanceAmount = round((float)($merchant['money'] ?? 0), 3);
        $relatedCounts = [
            [
                'key' => 'subordinate_merchants',
                'label' => '下级商户',
                'table_name' => 'ypay_user',
                'column_name' => 'superior_id',
                'count' => $subordinateCount,
                'delete_action' => 'block',
                'help_text' => '存在下级商户时不允许自助注销，避免留下悬空的上级关系。',
            ],
            [
                'key' => 'pending_orders',
                'label' => '未完成订单',
                'table_name' => 'ypay_order',
                'column_name' => 'user_id',
                'count' => $pendingOrderCount,
                'delete_action' => 'block',
                'help_text' => '请先处理未完成订单，避免支付回调或补单流程中断。',
            ],
            [
                'key' => 'pending_recharges',
                'label' => '未完成充值',
                'table_name' => 'ypay_recharge',
                'column_name' => 'user_id',
                'count' => $pendingRechargeCount,
                'delete_action' => 'block',
                'help_text' => '请先处理未完成充值记录，避免后续到账与对账异常。',
            ],
        ];

        $deleteRowCount = 0;
        $nonEmptyTargetCount = 0;
        foreach ($this->merchantCancellationTargets() as $target) {
            $count = $this->countMerchantCancellationRows($target['table'], $target['column'], $merchantId);
            if ($count > 0) {
                $deleteRowCount += $count;
                $nonEmptyTargetCount++;
            }

            $relatedCounts[] = [
                'key' => $target['key'],
                'label' => $target['label'],
                'table_name' => $target['table'],
                'column_name' => $target['column'],
                'count' => $count,
                'delete_action' => 'delete',
                'help_text' => $target['help_text'],
            ];
        }

        $blockingReasons = [];
        if ($balanceAmount > 0) {
            $blockingReasons[] = sprintf('当前账户仍有 %.2f 元余额，请先处理余额后再注销。', $balanceAmount);
        }
        if ($subordinateCount > 0) {
            $blockingReasons[] = sprintf('当前账户仍绑定 %d 个下级商户，请先调整上下级关系。', $subordinateCount);
        }
        if ($pendingOrderCount > 0) {
            $blockingReasons[] = sprintf('当前账户仍有 %d 笔未完成订单，请先处理。', $pendingOrderCount);
        }
        if ($pendingRechargeCount > 0) {
            $blockingReasons[] = sprintf('当前账户仍有 %d 笔未完成充值，请先处理。', $pendingRechargeCount);
        }

        return [
            'merchant_id' => $merchantId,
            'merchant_username' => trim((string)($merchant['username'] ?? '')),
            'confirmation_phrase' => $this->merchantCancellationConfirmationPhrase($merchantId),
            'can_delete' => $blockingReasons === [],
            'blocking_reasons' => $blockingReasons,
            'related_counts' => $relatedCounts,
            'summary' => [
                'delete_row_count' => $deleteRowCount,
                'non_empty_target_count' => $nonEmptyTargetCount,
                'blocking_reference_count' => $subordinateCount + $pendingOrderCount + $pendingRechargeCount,
                'balance_blocked' => $balanceAmount > 0,
                'pending_order_count' => $pendingOrderCount,
                'pending_recharge_count' => $pendingRechargeCount,
                'subordinate_count' => $subordinateCount,
            ],
            'warnings' => [
                '账号注销不可恢复，将同步清理当前商户归属的通道、订单、日志、工单等数据。',
                '请务必先确认余额、下级关系和未完成交易都已处理完毕。',
            ],
        ];
    }

    private function merchantCancellationTargets(): array
    {
        return [
            [
                'key' => 'userbasic',
                'label' => '商户基础配置',
                'table' => 'ypay_userbasic',
                'column' => 'user_id',
                'help_text' => '删除通讯密钥、超时回调、通知偏好等商户基础设置。',
            ],
            [
                'key' => 'payment_accounts',
                'label' => '商户本地通道',
                'table' => 'ypay_account',
                'column' => 'user_id',
                'help_text' => '删除当前商户名下的本地收款通道记录。',
            ],
            [
                'key' => 'merchant_paylists',
                'label' => '商户上游通道',
                'table' => 'ypay_paylist',
                'column' => 'user_id',
                'help_text' => '删除当前商户配置的上游支付通道凭据。',
            ],
            [
                'key' => 'payment_pools',
                'label' => '轮询池',
                'table' => 'ypay_poll_pool',
                'column' => 'user_id',
                'help_text' => '删除当前商户创建的轮询池配置。',
            ],
            [
                'key' => 'payment_pool_items',
                'label' => '轮询池通道',
                'table' => 'ypay_poll_pool_item',
                'column' => 'user_id',
                'help_text' => '删除轮询池内已绑定的通道选择记录。',
            ],
            [
                'key' => 'orders',
                'label' => '订单记录',
                'table' => 'ypay_order',
                'column' => 'user_id',
                'help_text' => '删除当前商户的订单流水。',
            ],
            [
                'key' => 'recharges',
                'label' => '充值记录',
                'table' => 'ypay_recharge',
                'column' => 'user_id',
                'help_text' => '删除当前商户的充值与付费注册记录。',
            ],
            [
                'key' => 'money_logs',
                'label' => '余额日志',
                'table' => 'money_log',
                'column' => 'user_id',
                'help_text' => '删除当前商户的余额变动日志。',
            ],
            [
                'key' => 'front_logs',
                'label' => '前台日志',
                'table' => 'admin_front_log',
                'column' => 'uid',
                'help_text' => '删除当前商户的前台访问与行为日志。',
            ],
            [
                'key' => 'domains',
                'label' => '域名记录',
                'table' => 'ypay_domain',
                'column' => 'user_id',
                'help_text' => '删除当前商户提交的域名与审核结果。',
            ],
            [
                'key' => 'risks',
                'label' => '风控记录',
                'table' => 'ypay_risk',
                'column' => 'user_id',
                'help_text' => '删除当前商户的风控命中记录。',
            ],
            [
                'key' => 'tickets',
                'label' => '工单记录',
                'table' => 'ypay_ticket',
                'column' => 'creator_id',
                'help_text' => '删除当前商户提交的工单记录。',
            ],
            [
                'key' => 'merchant_record',
                'label' => '商户账号',
                'table' => 'ypay_user',
                'column' => 'id',
                'help_text' => '删除当前商户账号本身。',
            ],
        ];
    }

    private function deleteMerchantOwnedRowsForCancellation(int $merchantId): void
    {
        foreach ($this->merchantCancellationTargets() as $target) {
            if (!$this->merchantCancellationTargetAvailable($target['table'], $target['column'])) {
                continue;
            }

            Db::table($target['table'])
                ->where($target['column'], $merchantId)
                ->delete();
        }
    }

    private function countMerchantCancellationRows(string $table, string $column, int $merchantId): int
    {
        if (!$this->merchantCancellationTargetAvailable($table, $column)) {
            return 0;
        }

        return (int)Db::table($table)->where($column, $merchantId)->count();
    }

    private function merchantCancellationTargetAvailable(string $table, string $column): bool
    {
        return DatabaseColumnInspector::hasColumn($table, $column);
    }

    private function merchantCancellationPendingCount(string $table, string $column, int $merchantId): int
    {
        if (
            !$this->merchantCancellationTargetAvailable($table, $column)
            || !DatabaseColumnInspector::hasColumn($table, 'status')
        ) {
            return 0;
        }

        return (int)Db::table($table)
            ->where($column, $merchantId)
            ->where('status', 0)
            ->count();
    }

    private function merchantCancellationConfirmationPhrase(int $merchantId): string
    {
        return 'DELETE ACCOUNT ' . $merchantId;
    }

    private function buildMerchantGoogleAuthPayload(
        array $merchant,
        array $config,
        bool $securityEnabled,
        bool $securityLoginEnabled,
        ?string $pendingSecret = null
    ): array {
        $googleKey = trim((string)($merchant['googlekey'] ?? ''));
        $setupSecret = $googleKey === '' ? trim((string)$pendingSecret) : '';
        $setupPending = $setupSecret !== '';
        $setupAccount = $setupPending ? $this->googleAuthAccountLabel($merchant) : null;
        $setupIssuer = $setupPending ? $this->googleAuthIssuer($config) : null;
        $google = $setupPending ? new GoogleAuthenticator() : null;

        return [
            'bound' => $googleKey !== '',
            'status_label' => $googleKey !== '' ? '已绑定' : '未绑定',
            'status_type' => $googleKey !== '' ? 'success' : 'warning',
            'secret_masked' => $this->maskSecret($googleKey),
            'verification_page' => '/My/GoogleAuth',
            'verification_required_at_login' => $securityEnabled && $securityLoginEnabled,
            'verification_allowed' => false,
            'bind_allowed' => $googleKey === '',
            'unbind_allowed' => $googleKey !== '',
            'setup_pending' => $setupPending,
            'setup_account' => $setupAccount,
            'setup_issuer' => $setupIssuer,
            'setup_secret' => $setupPending ? $setupSecret : null,
            'setup_secret_masked' => $setupPending ? $this->maskSecret($setupSecret) : null,
            'setup_qrcode_url' => $setupPending && $google !== null
                ? $google->getQRCodeGoogleUrl((string)$setupAccount, $setupSecret, (string)$setupIssuer)
                : null,
            'otp_auth_url' => $setupPending && $google !== null
                ? $google->buildOtpAuthUrl((string)$setupAccount, $setupSecret, (string)$setupIssuer)
                : null,
            'write_message' => $googleKey !== ''
                ? '谷歌验证解绑功能已接入当前系统；登录时是否校验仍由系统安全开关决定。'
                : '谷歌验证绑定功能已接入当前系统；登录时是否校验仍由系统安全开关决定。',
        ];
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

        $total = (int)(clone $query)->count('ypay_user.id');
        $rows = $query
            ->orderByDesc('ypay_user.id')
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
                'entry_route' => '/My/real_name',
                'submit_endpoint' => '/My/realname',
                'status_endpoint' => '/My/getRealNameStatus',
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
                'entry_route' => '/My/real_name',
                'submit_endpoint' => '/My/realname',
                'status_endpoint' => '/My/getRealNameStatus',
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

    private function merchantApiPayload(Request $request, array $merchant): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $basic = $this->merchantBasic($merchantId);
        $signKey = trim((string)($merchant['user_key'] ?? ''));
        $appkey = trim((string)($basic['appkey'] ?? ''));
        $origin = $this->requestOrigin($request);
        $timeoutMethod = (int)($basic['timeout_method'] ?? 0);
        $gatewayLines = $this->gatewayLines($request);

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
            'sign_key_masked' => $this->maskSecret($signKey),
            'sign_key_length' => strlen($signKey),
            'appkey_configured' => $appkey !== '',
            'appkey_masked' => $this->maskSecret($appkey),
            'appkey_length' => strlen($appkey),
            'timeout_time' => (int)($basic['timeout_time'] ?? 0),
            'timeout_url' => $this->nullableString($basic['timeout_url'] ?? null) ?? '/',
            'timeout_method' => $timeoutMethod,
            'timeout_method_label' => $this->timeoutMethodLabel($timeoutMethod),
            'signing' => [
                'algorithm' => 'MD5',
                'secret_source' => 'ypay_user.user_key',
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

    private function merchantBasic(int $merchantId): array
    {
        $row = Db::table('ypay_userbasic')
            ->select('user_id', 'appkey', 'timeout_url', 'timeout_time', 'timeout_method')
            ->where('user_id', $merchantId)
            ->first();

        if (!$row) {
            return [
                'user_id' => $merchantId,
                'appkey' => '',
                'timeout_url' => '/',
                'timeout_time' => '180',
                'timeout_method' => 2,
            ];
        }

        return array_merge([
            'user_id' => $merchantId,
            'appkey' => '',
            'timeout_url' => '/',
            'timeout_time' => '180',
            'timeout_method' => 2,
        ], (array)$row);
    }

    private function ensureMerchantBasicRecord(int $merchantId): void
    {
        $exists = Db::table('ypay_userbasic')->where('user_id', $merchantId)->exists();
        if ($exists) {
            return;
        }

        Db::table('ypay_userbasic')->insert([
            'user_id' => $merchantId,
            'timeout_method' => 2,
            'timeout_url' => '/',
            'timeout_time' => '180',
            'loginfailure' => 0,
            'appkey' => $this->generateMerchantSecret(32),
            'order_tips' => 'close',
            'is_money_tips' => 'close',
            'money_tips' => '0',
            'is_rate' => 0,
            'callback_hiddenName' => 0,
        ]);
    }

    private function merchantNotificationBasic(int $merchantId): array
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
            'voice_tips' => self::DEFAULT_VOICE_TIPS,
        ];

        $row = Db::table('ypay_userbasic')
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
        $voiceTips = trim((string)($basic['voice_tips'] ?? ''));
        $basic['voice_tips'] = LegacyMojibakeGuard::normalizeVoiceTipsTemplate(
            $basic['voice_tips'] ?? null,
            self::DEFAULT_VOICE_TIPS
        );

        return $basic;
    }

    private function merchantQuickLoginBindings(array $merchant, array $config): array
    {
        $definitions = [
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

        $configIds = [];
        foreach ($definitions as $definition) {
            $configId = (int)($config[$definition['config_key']] ?? 0);
            if ($configId > 0) {
                $configIds[] = $configId;
            }
        }

        $quickLoginRows = [];
        if ($configIds !== []) {
            foreach (
                Db::table('ypay_quicklogin')
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
        foreach ($definitions as $definition) {
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
                'identifier_masked' => $this->maskIdentifier($sid),
                'config_name' => trim((string)($record['name'] ?? $definition['label'])),
                'config_type' => trim((string)($record['type'] ?? '')),
                'credential_ready' => trim((string)($record['appid'] ?? '')) !== '' && trim((string)($record['appkey'] ?? '')) !== '',
                'url' => $this->nullableString($record['url'] ?? null),
                'callback_entry' => $definition['id'] === 'qq' ? '/User/qqlogin' : '/User/OAuthAccountLogin?type=wx',
                'unbind_allowed' => $bound,
                'write_message' => '快捷登录解绑已生效；当前页面仅展示已接入状态，不再提供新的授权绑定入口。',
            ];
        }

        return $bindings;
    }

    private function merchantContactBindings(array $merchant, array $config): array
    {
        $definitions = [
            ['id' => 'email', 'label' => '邮箱', 'available' => trim((string)($config['email_switch'] ?? '0')) === '1'],
            ['id' => 'mobile', 'label' => '手机号', 'available' => trim((string)($config['code_switch'] ?? '0')) === '1'],
            ['id' => 'wxpusher_uid', 'label' => '微信推送', 'available' => trim((string)($config['wxpusher_switch'] ?? '0')) === '1'],
            ['id' => 'tg_chat_id', 'label' => '电报通知', 'available' => trim((string)($config['tg_switch'] ?? '0')) === '1'],
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
                'value_display' => $value === '' ? '未配置' : ($useMaskedValue ? ($this->maskIdentifier($value) ?? '已配置') : $value),
                'save_allowed' => in_array($id, ['wxpusher_uid', 'tg_chat_id'], true),
                'bind_allowed' => in_array($id, ['email', 'mobile'], true) && (bool)$definition['available'] && $value === '',
                'verify_code_allowed' => in_array($id, ['email', 'mobile'], true) && (bool)$definition['available'],
                'unbind_allowed' => $value !== '',
                'status_check_allowed' => $id === 'wxpusher_uid',
                'write_message' => in_array($id, ['wxpusher_uid', 'tg_chat_id'], true)
                    ? '微信推送与 Telegram 通知可直接在当前商户中心保存。'
                    : '邮箱和手机验证码绑定流程可直接在当前商户中心处理。',
            ];
        }

        return $bindings;
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
                'ypay_order.id',
                'ypay_order.name',
                'ypay_order.sitename',
                'ypay_order.trade_no',
                'ypay_order.out_trade_no',
                'ypay_order.alipay_order_no',
                'ypay_order.user_id',
                'ypay_order.account_id',
                'ypay_order.type',
                'ypay_order.pay_type',
                'ypay_order.money',
                'ypay_order.truemoney',
                'ypay_order.feilvmoney',
                'ypay_order.status',
                'ypay_order.notify_url',
                'ypay_order.return_url',
                'ypay_order.ip',
                'ypay_order.create_time',
                'ypay_order.end_time',
                'ypay_order.api_memo',
                'ypay_paylist.type as paylist_type',
                'ypay_paylist.name as paylist_name',
                'ypay_account.code as local_account_code',
                'admin_channel.type as local_channel_type',
                'admin_channel.name as local_channel_name'
            );
    }

    private function merchantOrderBaseQuery(int $merchantId): Builder
    {
        return Db::table('ypay_order')
            ->leftJoin('ypay_paylist', 'ypay_order.account_id', '=', 'ypay_paylist.id')
            ->leftJoin('ypay_account', 'ypay_order.account_id', '=', 'ypay_account.id')
            ->leftJoin('admin_channel', 'ypay_account.code', '=', 'admin_channel.code')
            ->where('ypay_order.user_id', $merchantId);
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
                'ypay_recharge.id',
                'ypay_recharge.type',
                'ypay_recharge.rtype',
                'ypay_recharge.out_trade_no',
                'ypay_recharge.user_id',
                'ypay_recharge.money',
                'ypay_recharge.qrcode',
                'ypay_recharge.status',
                'ypay_recharge.regdata',
                'ypay_recharge.create_time',
                'ypay_recharge.end_time',
                'ypay_recharge.update_time',
                'ypay_recharge.out_time',
                'ypay_user.username as merchant_username',
                'ypay_user.name as merchant_name',
                'ypay_user.email as merchant_email',
                'ypay_user.mobile as merchant_mobile'
            );
    }

    private function merchantRechargeBaseQuery(int $merchantId): Builder
    {
        return Db::table('ypay_recharge')
            ->leftJoin('ypay_user', 'ypay_recharge.user_id', '=', 'ypay_user.id')
            ->where('ypay_recharge.user_id', $merchantId);
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
        return Db::table('ypay_vip')
            ->whereNull('delete_time')
            ->where('status', 1);
    }

    private function merchantTicketQuery(int $merchantId): Builder
    {
        return $this->merchantTicketBaseQuery($merchantId)
            ->select(
                'ypay_ticket.id',
                'ypay_ticket.type',
                'ypay_ticket.title',
                'ypay_ticket.content',
                'ypay_ticket.reply_content',
                'ypay_ticket.creator_id',
                'ypay_ticket.assignee_id',
                'ypay_ticket.create_time',
                'ypay_ticket.update_time',
                'ypay_ticket.reply_time',
                'ypay_ticket.status',
                'ypay_ticket_category.name as category_name',
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
        return Db::table('ypay_ticket')
            ->leftJoin('ypay_ticket_category', 'ypay_ticket.type', '=', 'ypay_ticket_category.id')
            ->leftJoin('ypay_user as creator', 'ypay_ticket.creator_id', '=', 'creator.id')
            ->leftJoin('admin_admin as assignee', 'ypay_ticket.assignee_id', '=', 'assignee.id')
            ->where('ypay_ticket.creator_id', $merchantId);
    }

    private function merchantDomainQuery(int $merchantId): Builder
    {
        return $this->merchantDomainBaseQuery($merchantId)
            ->select(
                'ypay_domain.id',
                'ypay_domain.user_id',
                'ypay_domain.sitename',
                'ypay_domain.siteurl',
                'ypay_domain.status',
                'ypay_domain.reason',
                'ypay_domain.create_time',
                'ypay_domain.delete_time',
                'ypay_user.username as merchant_username',
                'ypay_user.name as merchant_name',
                'ypay_user.email as merchant_email',
                'ypay_user.mobile as merchant_mobile'
            );
    }

    private function merchantDomainBaseQuery(int $merchantId): Builder
    {
        return Db::table('ypay_domain')
            ->leftJoin('ypay_user', 'ypay_domain.user_id', '=', 'ypay_user.id')
            ->where('ypay_domain.user_id', $merchantId)
            ->whereNull('ypay_domain.delete_time');
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
                'ypay_user.username as merchant_username',
                'ypay_user.name as merchant_name',
                'ypay_user.email as merchant_email',
                'ypay_user.mobile as merchant_mobile'
            );
    }

    private function merchantLoginLogBaseQuery(int $merchantId): Builder
    {
        return Db::table('admin_front_log')
            ->leftJoin('ypay_user', 'admin_front_log.uid', '=', 'ypay_user.id')
            ->where('admin_front_log.uid', $merchantId);
    }

    private function merchantAffiliateQuery(int $merchantId): Builder
    {
        return $this->merchantAffiliateBaseQuery($merchantId)
            ->select(
                'ypay_user.id',
                'ypay_user.username',
                'ypay_user.name',
                'ypay_user.email',
                'ypay_user.mobile',
                'ypay_user.money',
                'ypay_user.vip_id',
                'ypay_user.vip_time',
                'ypay_user.is_realName',
                'ypay_user.create_time',
                'ypay_vip.name as vip_name'
            );
    }

    private function merchantAffiliateBaseQuery(int $merchantId): Builder
    {
        return Db::table('ypay_user')
            ->leftJoin('ypay_vip', 'ypay_user.vip_id', '=', 'ypay_vip.id')
            ->where('ypay_user.superior_id', $merchantId);
    }

    private function applyMerchantOrderFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', $request->get('name', '')));
        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder
                    ->where('ypay_order.trade_no', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_order.out_trade_no', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_order.name', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_order.sitename', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder
                        ->orWhere('ypay_order.id', (int)$keyword)
                        ->orWhere('ypay_order.account_id', (int)$keyword);
                }
            });
        }

        $status = $request->get('status');
        if ($status !== null && $status !== '') {
            $query->where('ypay_order.status', (int)$status);
        }

        $type = trim((string)$request->get('type', ''));
        if ($type !== '') {
            $query->where('ypay_order.type', $type);
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', $request->get('create_time-start', '')));
        $endDate = $this->normalizeDate((string)$request->get('end_date', $request->get('create_time-end', '')));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('ypay_order.create_time', '>=', $startDate . ' 00:00:00')
                ->where('ypay_order.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
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
                    ->where('ypay_recharge.out_trade_no', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_recharge.type', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_recharge.regdata', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('ypay_recharge.id', (int)$keyword);
                }
            });
        }

        $status = $request->get('status');
        if ($status !== null && $status !== '') {
            $query->where('ypay_recharge.status', (int)$status);
        }

        $type = trim((string)$request->get('type', ''));
        if ($type !== '') {
            $query->where('ypay_recharge.type', $type);
        }

        $rtype = $request->get('rtype');
        if ($rtype !== null && $rtype !== '') {
            $query->where('ypay_recharge.rtype', (int)$rtype);
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', $request->get('create_time-start', '')));
        $endDate = $this->normalizeDate((string)$request->get('end_date', $request->get('create_time-end', '')));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('ypay_recharge.create_time', '>=', $startDate . ' 00:00:00')
                ->where('ypay_recharge.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
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
                    ->where('ypay_ticket.title', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_ticket.content', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_ticket.reply_content', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_ticket_category.name', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('ypay_ticket.id', (int)$keyword);
                }
            });
        }

        $status = $request->get('status');
        if ($status !== null && $status !== '' && in_array((string)$status, ['0', '1', '2', '3'], true)) {
            $query->where('ypay_ticket.status', (int)$status);
        }

        $type = $request->get('type');
        if ($type !== null && $type !== '' && ctype_digit((string)$type)) {
            $query->where('ypay_ticket.type', (int)$type);
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', $request->get('create_time-start', '')));
        $endDate = $this->normalizeDate((string)$request->get('end_date', $request->get('create_time-end', '')));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('ypay_ticket.create_time', '>=', $startDate . ' 00:00:00')
                ->where('ypay_ticket.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
        }
    }

    private function applyMerchantDomainFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', $request->get('sitename', '')));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('ypay_domain.sitename', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_domain.siteurl', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_domain.reason', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('ypay_domain.id', (int)$keyword);
                }
            });
        }

        $status = $request->get('status');
        if ($status !== null && $status !== '' && in_array((string)$status, ['0', '1', '2'], true)) {
            $query->where('ypay_domain.status', (int)$status);
        }

        $siteurl = trim((string)$request->get('siteurl', ''));
        if ($siteurl !== '') {
            $query->where('ypay_domain.siteurl', 'like', '%' . $siteurl . '%');
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', $request->get('create_time-start', '')));
        $endDate = $this->normalizeDate((string)$request->get('end_date', $request->get('create_time-end', '')));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('ypay_domain.create_time', '>=', $startDate . ' 00:00:00')
                ->where('ypay_domain.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
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
                    ->where('ypay_user.username', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_user.name', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('ypay_user.id', (int)$keyword);
                }
            });
        }

        $verified = strtolower(trim((string)$request->get('verified', '')));
        if ($verified === '1' || $verified === 'true') {
            $query->where('ypay_user.is_realName', 1);
        } elseif ($verified === '0' || $verified === 'false') {
            $query->where('ypay_user.is_realName', 0);
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', $request->get('start', '')));
        $endDate = $this->normalizeDate((string)$request->get('end_date', $request->get('end', '')));
        if ($startDate !== null) {
            $query->where('ypay_user.create_time', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate !== null) {
            $query->where('ypay_user.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
        }
    }

    private function merchantAffiliateSummary(Request $request, array $merchant, Builder $query): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $config = SystemConfig::all();
        $inviteCount = (int)(clone $query)->count('ypay_user.id');
        $verifiedInviteCount = (int)(clone $query)->where('ypay_user.is_realName', 1)->count('ypay_user.id');
        $vipInviteCount = (int)(clone $query)->where('ypay_user.vip_id', '>', 0)->count('ypay_user.id');
        $lastInviteTime = $this->nullableString((clone $query)->max('ypay_user.create_time'));
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
            'invite_url' => $this->requestOrigin($request) . '/?aff=' . $merchantId,
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
            ->selectRaw('COUNT(ypay_order.id) as total_count')
            ->selectRaw('SUM(CASE WHEN ypay_order.status = 1 THEN 1 ELSE 0 END) as paid_count')
            ->selectRaw('SUM(CASE WHEN ypay_order.status = 0 THEN 1 ELSE 0 END) as pending_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN ypay_order.status = 1 THEN CASE WHEN ypay_order.type = 'usdt' THEN ypay_order.money ELSE ypay_order.truemoney END ELSE 0 END), 0) as paid_amount")
            ->selectRaw('COALESCE(SUM(CASE WHEN ypay_order.status = 0 THEN ypay_order.money ELSE 0 END), 0) as pending_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN ypay_order.status = 1 THEN ypay_order.feilvmoney ELSE 0 END), 0) as fee_amount')
            ->selectRaw('MAX(ypay_order.create_time) as last_order_time')
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
            ->selectRaw('COUNT(ypay_recharge.id) as total_count')
            ->selectRaw('SUM(CASE WHEN ypay_recharge.status = 1 THEN 1 ELSE 0 END) as paid_count')
            ->selectRaw('SUM(CASE WHEN ypay_recharge.status = 0 THEN 1 ELSE 0 END) as pending_count')
            ->selectRaw('SUM(CASE WHEN ypay_recharge.status NOT IN (0, 1) THEN 1 ELSE 0 END) as unknown_status_count')
            ->selectRaw('SUM(CASE WHEN ypay_recharge.rtype = 0 THEN 1 ELSE 0 END) as merchant_recharge_count')
            ->selectRaw('SUM(CASE WHEN ypay_recharge.rtype = 1 THEN 1 ELSE 0 END) as registration_count')
            ->selectRaw(
                'SUM(CASE WHEN ypay_recharge.status = 0 AND ypay_recharge.out_time IS NOT NULL AND ypay_recharge.out_time > 0 AND ypay_recharge.out_time <= ? THEN 1 ELSE 0 END) as expired_pending_count',
                [$now]
            )
            ->selectRaw('COALESCE(SUM(ypay_recharge.money), 0) as gross_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN ypay_recharge.status = 1 THEN ypay_recharge.money ELSE 0 END), 0) as paid_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN ypay_recharge.status = 0 THEN ypay_recharge.money ELSE 0 END), 0) as pending_amount')
            ->selectRaw('MAX(ypay_recharge.create_time) as last_recharge_time')
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
            'new_count' => (int)(clone $query)->where('ypay_ticket.status', 0)->count('ypay_ticket.id'),
            'processing_count' => (int)(clone $query)->where('ypay_ticket.status', 1)->count('ypay_ticket.id'),
            'resolved_count' => (int)(clone $query)->where('ypay_ticket.status', 2)->count('ypay_ticket.id'),
            'closed_count' => (int)(clone $query)->where('ypay_ticket.status', 3)->count('ypay_ticket.id'),
            'replied_count' => (int)(clone $query)
                ->whereNotNull('ypay_ticket.reply_content')
                ->where('ypay_ticket.reply_content', '<>', '')
                ->count('ypay_ticket.id'),
            'last_ticket_time' => $this->nullableString((clone $query)->max('ypay_ticket.create_time')),
        ];
    }

    private function merchantDomainSummary(Builder $query): array
    {
        return [
            'pending_count' => (int)(clone $query)->where('ypay_domain.status', 0)->count('ypay_domain.id'),
            'approved_count' => (int)(clone $query)->where('ypay_domain.status', 1)->count('ypay_domain.id'),
            'rejected_count' => (int)(clone $query)->where('ypay_domain.status', 2)->count('ypay_domain.id'),
            'last_domain_time' => $this->nullableString((clone $query)->max('ypay_domain.create_time')),
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
        $rows = Db::table('ypay_ticket_category')
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
        $count = (int)Db::table('ypay_domain')
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
            'details_url' => '/Deal/getDetails?id=' . (int)($order['id'] ?? 0),
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
        $formatted['write_message'] = '当前页面仅提供当前商户登录与访问记录查询。';

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
        return (int)Db::table('ypay_user')
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
        $row = Db::table('ypay_user')
            ->select('id', 'username', 'password', 'token', 'is_frozen', 'frozen_reason', 'googlekey')
            ->where('username', $username)
            ->where('password', LegacyPassword::hash($password))
            ->first();

        return $row ? (array)$row : null;
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

        Db::table('ypay_user')
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
            return $this->merchantJson(202, 'merchant ticket feature is disabled', 403);
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
            return $this->merchantJson(202, 'merchant domain feature is disabled', 403);
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

        return $this->blockedConnectionsWriteResponse();
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

        return $this->blockedSecurityWriteResponse();
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

        return $this->blockedRechargeWriteResponse();
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
            return $this->merchantJson(202, 'merchant cdk recharge feature is disabled', 403);
        }

        return $this->blockedCdkWriteResponse();
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

        return $this->blockedOrderWriteResponse();
    }

    private function merchantOrderCallbackUrls(array $order, array $merchant): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $basicRow = Db::table('ypay_userbasic')
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

        return $this->blockedApiKeyWriteResponse();
    }

    private function blockedTicketWriteResponse(): Response
    {
        return $this->merchantJson(202, '当前工单列表页仅提供查询，请在对应工单页面完成新增或删除。', 405);
    }

    private function blockedDomainWriteResponse(): Response
    {
        return $this->merchantJson(202, '当前域名列表页仅提供查询，请在对应域名页面完成新增、编辑或删除。', 405);
    }

    private function blockedConnectionsWriteResponse(): Response
    {
        return $this->merchantJson(202, '当前绑定中心页仅提供查询，请在对应页面完成绑定、解绑、验证码或扫码操作。', 405);
    }

    private function blockedSecurityWriteResponse(): Response
    {
        return $this->merchantJson(202, '当前安全中心页仅提供查询，请在对应页面完成密码修改、谷歌验证、实名认证或账号注销。', 405);
    }

    private function blockedRechargeWriteResponse(): Response
    {
        return $this->merchantJson(202, '当前充值页仅提供查询，请在对应页面完成充值创建与支付跳转。', 405);
    }

    private function blockedCdkWriteResponse(): Response
    {
        return $this->merchantJson(202, '当前充值页不处理卡密兑换，请在卡密兑换页面完成操作。', 405);
    }

    private function blockedOrderWriteResponse(): Response
    {
        return $this->merchantJson(202, '当前商户中心仅保留订单回调重放，状态重置已下线。', 405);
    }

    private function blockedApiKeyWriteResponse(): Response
    {
        return $this->merchantJson(202, '当前接口信息页仅提供查询，请使用签名密钥或通讯密钥重置入口完成操作。', 405);
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

        $payload['migration_guard'] = $this->migrationGuardFromPayload($payload);

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
        $message = trim($message);

        $translations = [
            'merchant login is required' => '请先登录商户账号',
            'merchant is frozen' => '商户账户已被冻结',
            'merchant ticket feature is disabled' => '工单功能未开启',
            'merchant domain feature is disabled' => '域名功能未开启',
            'merchant real-name feature is disabled' => '实名认证功能未开启',
            'merchant cdk recharge feature is disabled' => '卡密充值功能未开启',
            'merchant affiliate feature is disabled' => '推广返佣功能未开启',
            'merchant affiliate page is read-only in the Webman merchant center' => '当前推广返佣页仅提供统计查看与邀请链接复制，不提供重置邀请链接或返佣提现。',
            'merchant ticket create/delete flows are not migrated for Webman merchant center yet' => '当前工单列表入口仅提供总览查询，请使用工单创建或删除入口操作。',
            'merchant domain create/edit/delete flows are not migrated for Webman merchant center yet' => '当前域名列表入口仅提供总览查询，请使用域名新增、编辑或删除入口操作。',
            'merchant connection bind-code, QR enrollment, and email/mobile verification flows are not migrated for Webman merchant center yet' => '当前绑定中心总览入口仅提供状态查看，请使用绑定、解绑、验证码和扫码入口操作。',
            'merchant Google Auth, real-name, and account-cancellation flows are not migrated for Webman merchant center yet' => '当前安全中心总览入口仅提供状态查看，请使用修改密码、谷歌验证、实名认证和账号注销入口操作。',
            'merchant recharge creation and payment handoff are not migrated for Webman merchant center yet' => '当前充值总览入口仅提供查询，请使用充值创建与支付跳转入口操作。',
            'merchant real-name verification flows are not migrated for Webman merchant center yet' => '当前实名认证总览入口仅提供状态查看，请使用实名认证入口操作。',
            'merchant cdk redemption is not migrated for Webman merchant center yet' => '当前充值总览入口不处理卡密兑换，请使用卡密兑换入口操作。',
            'merchant order callback replay and status reset flows are not migrated for Webman merchant center yet' => '当前商户中心仅保留订单回调重放，状态重置已下线。',
            'merchant api key maintenance is not migrated for Webman merchant center yet' => '当前接口信息总览入口仅提供查看，请使用密钥重置入口操作。',
            'vip purchase is not migrated for Webman merchant center yet' => '当前会员页面仅展示套餐信息，购买与续费暂未开放。',
            'vip package is required' => '请选择会员套餐',
            'vip package not found' => '会员套餐不存在或已下架',
            'merchant balance is insufficient for vip purchase' => '余额不足，请先充值',
            'merchant vip purchase completed successfully' => '会员套餐购买成功',
            'username and password are required' => '请输入账号和密码',
            'captcha verification is not migrated for Webman merchant login yet' => '当前暂未开放验证码登录，请直接使用账号密码登录',
            'only username/password merchant login is migrated in Webman' => '当前仅支持账号密码登录',
            'username or password is incorrect' => '账号或密码错误',
            'google verification is required before Webman direct merchant login can continue' => '当前账号需先完成谷歌验证后才可继续登录',
            'order id is required' => '请输入订单编号',
            'order not found' => '订单不存在',
            'login success' => '登录成功',
            'connection unbound successfully' => '绑定已解除',
            'merchant quick-login OAuth bind and WxPusher QR enrollment flows are not migrated for Webman merchant center yet' => '当前绑定中心总览入口仅提供状态查看，请使用绑定、解绑、验证码和扫码入口操作。',
            'merchant password change is live in the Webman merchant center and will require a fresh login after save' => '商户密码修改已生效，保存后需要重新登录',
            'merchant google auth bind is live in webman. login-time verification still follows the migration guard.' => '谷歌验证绑定功能已接入当前系统；登录时是否校验仍由系统安全开关决定。',
            'merchant google auth unbind is live in webman. login-time verification still follows the migration guard.' => '谷歌验证解绑功能已接入当前系统；登录时是否校验仍由系统安全开关决定。',
            'merchant email verification feature is disabled' => '邮箱验证码功能未开启',
            'merchant mobile verification feature is disabled' => '手机验证码功能未开启',
            'connection verification channel is invalid' => '绑定验证码通道无效',
            'connection verification action is invalid' => '绑定验证码操作无效',
            'please wait before requesting another verification code' => '请求过于频繁，请稍后再获取验证码',
            'merchant connection verification code sent successfully' => '绑定验证码已发送',
            'merchant connection verification code sending failed' => '绑定验证码发送失败',
            'email address is required' => '请输入邮箱地址',
            'mobile number is required' => '请输入手机号码',
            'system email switch is disabled' => '系统邮箱开关未开启',
            'smtp configuration is incomplete' => 'SMTP 配置不完整',
            'system mobile verification switch is disabled' => '系统手机验证码开关未开启',
            'sms package is not installed for webman merchant center' => '商户后台未安装短信依赖包',
            'sms verification provider is not supported' => '当前短信验证码服务商不受支持',
            'sms verification provider configuration is incomplete' => '短信验证码服务商配置不完整',
            'sms verification code sending failed' => '短信验证码发送失败',
            'current merchant email is not configured yet' => '当前商户尚未绑定邮箱',
            'current merchant mobile is not configured yet' => '当前商户尚未绑定手机号',
            'current merchant email is already configured, please use unbind flow' => '当前商户已绑定邮箱，请先解绑后再重新绑定',
            'current merchant mobile is already configured, please use unbind flow' => '当前商户已绑定手机号，请先解绑后再重新绑定',
            'verification code is invalid' => '验证码不正确',
            'verification code has expired' => '验证码已过期',
            'verification target does not match the current request' => '验证码目标与当前请求不匹配',
            'email is too long' => '邮箱长度不能超过 50 个字符',
            'email format is invalid' => '邮箱格式不正确',
            'email is already in use by another merchant' => '该邮箱已被其他商户使用',
            'mobile number is too long' => '手机号长度不能超过 50 个字符',
            'mobile format is invalid' => '手机号格式不正确',
            'mobile number is already in use by another merchant' => '该手机号已被其他商户使用',
            'merchant email bind completed successfully' => '邮箱绑定成功',
            'merchant email unbind completed successfully' => '邮箱解绑成功',
            'merchant mobile bind completed successfully' => '手机绑定成功',
            'merchant mobile unbind completed successfully' => '手机解绑成功',
            'telegram chat id is required' => '请输入电报会话标识',
            'telegram chat id format is invalid' => '电报会话标识格式不正确',
            'wxpusher uid saved successfully' => '微信推送标识已保存',
            'telegram chat id saved successfully' => '电报会话标识已保存',
            'merchant password updated successfully, please sign in again' => '密码修改成功，请重新登录',
            'current merchant account already has Google Auth bound' => '当前商户账号已绑定谷歌验证器',
            'current merchant account already has google auth bound' => '当前商户账号已绑定谷歌验证器',
            'google auth qr code generated successfully' => '谷歌验证二维码已生成',
            'google verification code is required' => '请输入 6 位谷歌验证码',
            'google verification code is invalid' => '谷歌验证码不正确',
            'merchant Google Auth bound successfully' => '谷歌验证器绑定成功',
            'merchant google auth bound successfully' => '谷歌验证器绑定成功',
            'current merchant account has not bound Google Auth yet' => '当前商户账号尚未绑定谷歌验证器',
            'current merchant account has not bound google auth yet' => '当前商户账号尚未绑定谷歌验证器',
            'merchant Google Auth unbound successfully' => '谷歌验证器解绑成功',
            'merchant google auth unbound successfully' => '谷歌验证器解绑成功',
            'real-name full name is required' => '请输入真实姓名',
            'real-name id card is required' => '请输入身份证号',
            'real-name id card format is invalid' => '身份证号格式不正确',
            'real-name verification channel is invalid' => '实名认证通道无效',
            'real-name provider app code is not configured' => '实名认证服务应用编码未配置',
            'real-name alipay app credentials are not configured' => '支付宝实名认证应用配置不完整',
            'merchant real-name verification started successfully' => '实名认证已发起，请继续完成认证',
            'merchant real-name verification fee balance is insufficient' => '余额不足，无法支付实名认证费用',
            'merchant real-name verification completed successfully' => '实名认证完成成功',
            'merchant real-name verification is pending' => '实名认证处理中',
            'merchant real-name verification is already completed' => '当前商户已完成实名认证。',
            'merchant real-name verification is live in webman merchant center' => '实名认证发起已接入当前商户后台。',
            'merchant real-name verification initiation is not migrated for the webman merchant center yet' => '管理员尚未配置可用的实名认证通道。',
            'merchant real-name verification request failed' => '实名认证发起失败，请稍后重试',
            'merchant real-name verification status query failed' => '实名认证状态查询失败，请稍后重试',
            'api qrcode generation is not migrated because it would expose the raw merchant secret' => '商户二维码已支持按所选线路即时生成，接口默认只返回二维码地址与 base64 密文。',
            'ticket title is required' => '请输入工单标题',
            'ticket title is too long' => '工单标题过长',
            'ticket content is required' => '请输入工单内容',
            'ticket category is invalid or disabled' => '工单分类无效或已禁用',
            'ticket id is required' => '请输入工单 ID',
            'ticket not found' => '工单不存在',
            'merchant ticket created successfully' => '工单创建成功',
            'merchant ticket deleted successfully' => '工单删除成功',
            'daily merchant domain submission limit has been reached' => '已达到当日域名提交上限',
            'domain id is required' => '请输入域名 ID',
            'domain not found' => '域名不存在',
            'merchant domain created successfully' => '域名提交成功',
            'merchant domain updated successfully' => '域名更新成功',
            'merchant domain deleted successfully' => '域名删除成功',
            'email or mobile is required' => '邮箱或手机号至少填写一项',
            'at least one notification setting is required' => '至少需要保留一项通知设置',
            'merchant notification settings saved successfully' => '通知设置保存成功',
            'merchant profile updated successfully' => '商户资料更新成功',
            'merchant sign key reset successfully' => '商户签名密钥重置成功',
            'merchant api key reset successfully' => '商户接口密钥重置成功',
            'merchant appkey reset successfully' => '商户通讯密钥重置成功',
        ];

        return ApiResponse::normalizeText($translations[$message] ?? $message);
    }

    private function htmlResponse(string $html, int $status = 200): Response
    {
        return response($this->localizePortalHtml($html), $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function localizePortalHtml(string $html): string
    {
        $replacements = [];
        foreach ([
            '<title>Merchant Login</title>' => '<title>商户登录</title>',
            '<title>Merchant Security</title>' => '<title>安全中心</title>',
            '<title>Merchant Notifications</title>' => '<title>通知设置</title>',
            '<title>Merchant Connections</title>' => '<title>绑定中心</title>',
            '<title>Merchant Orders</title>' => '<title>订单记录</title>',
            '<title>Merchant Recharges</title>' => '<title>充值记录</title>',
            '<title>Merchant Money Logs</title>' => '<title>资金日志</title>',
            '<title>Merchant VIP Packages</title>' => '<title>会员套餐</title>',
            '<title>Merchant API</title>' => '<title>接口信息</title>',
            '<title>Merchant Login Logs</title>' => '<title>登录日志</title>',
            '<title>Merchant Google Auth</title>' => '<title>谷歌验证</title>',
            '<title>Merchant Affiliate</title>' => '<title>推广返佣</title>',
            '<title>Merchant Real Name</title>' => '<title>实名认证</title>',
            '<title>Merchant Domains</title>' => '<title>域名管理</title>',
            '<title>Merchant Tickets</title>' => '<title>工单中心</title>',
            '<title>Merchant Frozen</title>' => '<title>商户账户已冻结</title>',
            '>Merchant Login<' => '>商户登录<',
            '>Merchant Security<' => '>安全中心<',
            '>Merchant Notifications<' => '>通知设置<',
            '>Merchant Connections<' => '>绑定中心<',
            '>Merchant Orders<' => '>订单记录<',
            '>Merchant Recharges<' => '>充值记录<',
            '>Merchant Money Logs<' => '>资金日志<',
            '>Merchant VIP Packages<' => '>会员套餐<',
            '>Merchant API<' => '>接口信息<',
            '>Merchant Login Logs<' => '>登录日志<',
            '>Merchant Google Auth<' => '>谷歌验证<',
            '>Merchant Affiliate<' => '>推广返佣<',
            '>Merchant Real Name<' => '>实名认证<',
            '>Merchant Domains<' => '>域名管理<',
            '>Merchant Tickets<' => '>工单中心<',
            '>Merchant Frozen<' => '>商户账户已冻结<',
            '>Webman Merchant Portal<' => '>商户中心<',
            '>Merchant Center<' => '>商户中心<',
            '>Profile<' => '>资料维护<',
            '>Security Center<' => '>安全中心<',
            '>Security<' => '>安全中心<',
            '>Connections<' => '>绑定中心<',
            '>Real Name<' => '>实名认证<',
            '>Orders<' => '>订单记录<',
            '>Recharges<' => '>充值记录<',
            '>Money Logs<' => '>资金日志<',
            '>VIP Packages<' => '>会员套餐<',
            '>API Info<' => '>接口信息<',
            '>Tickets<' => '>工单中心<',
            '>Domains<' => '>域名管理<',
            '>Login Logs<' => '>登录日志<',
            '>Affiliate<' => '>推广返佣<',
            '>JSON View<' => '>查看 JSON<',
            '>Logout<' => '>退出登录<',
            '>Back to Migration Entry<' => '>返回首页<',
            '>Enter Merchant Center<' => '>进入商户中心<',
            '>Google Auth<' => '>谷歌验证<',
            '>Enabled<' => '>已启用<',
            '>Disabled<' => '>已关闭<',
            '>Required<' => '>必需<',
            '>Optional<' => '>可选<',
            '>Available<' => '>可用<',
            '>Unavailable<' => '>不可用<',
            '>Configured<' => '>已配置<',
            '>Not configured<' => '>未配置<',
            '>Bound<' => '>已绑定<',
            '>Not bound<' => '>未绑定<',
            '>Closed<' => '>关闭<',
            '>Current<' => '>当前<',
            '>Read Only<' => '>信息查看<',
            '>Blocked<' => '>受限<',
            '>Missing<' => '>未配置<',
            '>Verified<' => '>已认证<',
            '>Not verified<' => '>未认证<',
            '>Pending verification<' => '>待认证<',
            '>No active VIP<' => '>暂无有效会员<',
            '>Normal merchant<' => '>普通商户<',
            '>VIP active<' => '>会员有效<',
            '>VIP expired<' => '>会员已过期<',
            '>Unknown<' => '>未知<',
            '>Behavior Log<' => '>行为日志<',
            '>Login Event<' => '>登录事件<',
            '>Security Event<' => '>安全事件<',
            '>Merchant Action<' => '>商户操作<',
            '>Provider<' => '>提供方<',
            '>Status<' => '>状态<',
            '>Masked Secret<' => '>脱敏密钥<',
            '>Migration State<' => '>当前状态<',
            '>Write Actions<' => '>写入能力<',
            '>Low Balance Threshold<' => '>余额预警阈值<',
            '>Console Notice<' => '>控制台提示<',
            '>Voice Tips<' => '>语音提醒<',
            '>Setting<' => '>设置项<',
            '>Delivery Channel<' => '>通知渠道<',
            '>Available Channels<' => '>可选渠道<',
            '>Action<' => '>操作<',
            '>Email<' => '>邮箱<',
            '>Mobile<' => '>手机号<',
            '>QQ Login<' => '>QQ 登录<',
            '>WeChat Login<' => '>微信登录<',
            '>New order notification<' => '>新订单通知<',
            '>Channel offline alert<' => '>通道离线提醒<',
            '>Account login alert<' => '>账户登录提醒<',
            '>Low balance notification<' => '>余额不足提醒<',
            '>Save Notification Settings<' => '>保存通知设置<',
            '>Reset<' => '>重置<',
            '>Credentials ready<' => '>凭据完整<',
            '>Credentials incomplete<' => '>凭据不完整<',
            '>Verification flow pending<' => '>验证流程暂未开放<',
            '>WxPusher UID<' => '>微信推送标识<',
            '>Telegram Chat ID<' => '>电报会话标识<',
            '>Current VIP<' => '>当前会员<',
            '>VIP Expiry<' => '>会员到期时间<',
            '>Enabled Plans<' => '>可用套餐数<',
            '>Price Range<' => '>价格区间<',
            '>Quota Enabled<' => '>额度限制套餐<',
            '>Purchase<' => '>购买状态<',
            '>Mode<' => '>模式<',
            '>Migration Guard<' => '>当前状态<',
            '>Key Actions<' => '>密钥操作<',
            '>Key Rotation<' => '>密钥重置<',
            '>Reset Sign Key<' => '>重置签名密钥<',
            '>Reset Appkey<' => '>重置通讯密钥<',
            '>Gateway Lines<' => '>网关线路<',
            '>Timeout Settings<' => '>超时设置<',
            '>Endpoint Overview<' => '>接口总览<',
            '>Merchant ID<' => '>商户 ID<',
            '>Username<' => '>登录账号<',
            '>Sign Key<' => '>签名密钥<',
            '>Legacy Appkey<' => '>通讯密钥<',
            '>Masked Sign Key<' => '>已脱敏签名密钥<',
            '>Masked Appkey<' => '>已脱敏通讯密钥<',
            '>Timeout Seconds<' => '>超时秒数<',
            '>Pending<' => '>待审核<',
            '>Total<' => '>总数<',
            '>Paid<' => '>已支付<',
            '>Expired Pending<' => '>已过期待支付<',
            '>Approved<' => '>已通过<',
            '>Rejected<' => '>已驳回<',
            '>Submit Domain<' => '>提交域名<',
            '>Create Domain<' => '>创建域名<',
            '>Cancel Edit<' => '>取消编辑<',
            '>Total Logs<' => '>日志总数<',
            '>Today<' => '>今日<',
            '>Payload Logs<' => '>载荷日志<',
            '>IP Count<' => '>IP 数量<',
            '>Log<' => '>日志<',
            '>Path<' => '>路径<',
            '>Type<' => '>类型<',
            '>User Agent<' => '>客户端信息<',
            '>New<' => '>新建<',
            '>Processing<' => '>处理中<',
            '>Resolved<' => '>已解决<',
            '>Replied<' => '>已回复<',
            '>Category<' => '>分类<',
            '>Title<' => '>标题<',
            '>Content<' => '>内容<',
            '>Reply<' => '>回复<',
            '>Create Ticket<' => '>提交工单<',
            '>Record<' => '>记录<',
            '>Order<' => '>订单<',
            '>Trade<' => '>交易信息<',
            '>Method<' => '>方式<',
            '>Amount<' => '>金额<',
            '>Paid At<' => '>支付时间<',
            '>Total Orders<' => '>订单总数<',
            '>Paid Orders<' => '>已支付订单<',
            '>Pending Orders<' => '>待支付订单<',
            '>Paid Amount<' => '>支付金额<',
            '>Success Rate<' => '>成功率<',
            '>Income Entries<' => '>收入笔数<',
            '>Expense Entries<' => '>支出笔数<',
            '>Income Amount<' => '>收入金额<',
            '>Expense Amount<' => '>支出金额<',
            '>Net Change<' => '>净变动<',
            '>Change<' => '>变动<',
            '>Balance<' => '>余额<',
            '>Memo<' => '>备注<',
            '>Recharge amount<' => '>充值金额<',
            '>Recharge method<' => '>充值方式<',
            '>Write Mode<' => '>写入模式<',
            '>Face/Auth Providers<' => '>实名渠道<',
            '>Merchant<' => '>商户<',
            '>Display<' => '>展示名称<',
            '>Verification<' => '>认证状态<',
            '>VIP / Balance<' => '>会员 / 余额<',
            '>Contacts<' => '>联系方式<',
            '>Verification Channels<' => '>认证渠道<',
            '>Passage Locked<' => '>通道限制套餐<',
            '>Duration<' => '>时长<',
            '>Fee Rate<' => '>费率<',
            '>Channels<' => '>通道范围<',
            '>Quota<' => '>额度限制<',
            '>Channel Grant<' => '>通道增配<',
            '>Base URL<' => '>基础地址<',
            '>Line<' => '>线路<',
            '>Entries<' => '>入口地址<',
            '>Endpoint Reference<' => '>接口说明<',
            '>Entry<' => '>入口<',
            '>Note<' => '>说明<',
            '>Open JSON payload<' => '>打开 JSON 数据<',
            '>Recharge rules<' => '>充值规则<',
            '>Save Enabled<' => '>已开放保存<',
            '>Save Domain Changes<' => '>保存域名修改<',
            '>Use order callback host<' => '>使用订单回调域名<',
            '>Use configured timeout_url<' => '>使用已配置的超时跳转地址<',
            '>Timeout URL<' => '>超时地址<',
            '>QR Code<' => '>二维码<',
            '>Key Reset<' => '>密钥重置<',
            '>Live<' => '>已生效<',
            '>Site Name<' => '>站点名称<',
            '>Site Domain<' => '>站点域名<',
            '>Current:<' => '>当前：<',
            '>Saved with form<' => '>随表单保存<',
            '>Create and continue to payment<' => '>创建订单并继续支付<',
            '>Purchase blocked during migration<' => '>当前暂未开放购买<',
            '>Voice Template<' => '>语音模板<',
            '>Delete<' => '>删除<',
            '>Edit / Resubmit<' => '>编辑 / 重新提交<',
            '>Payload<' => '>载荷<',
            'Use the migrated username and password flow to enter the Webman merchant center. Captcha, SMS/email-code, and Google-auth login branches are blocked until those security flows are migrated.' => '请直接使用账号密码登录商户中心。当前登录入口不提供验证码、短信/邮箱验证码或谷歌验证登录。',
            'If your account requires migrated captcha, SMS/email verification, or Google verification, keep using the legacy flow until that branch is rebuilt.' => '如果你的账号启用了额外安全校验，请联系管理员协助处理。',
            ', this Webman page lists your own frontend behavior and login audit records only. Delete, batch-delete, and cleanup actions remain blocked from the merchant center.' => '，当前页面仅展示你自己的前台行为与登录记录，并自动按当前商户范围过滤。',
            'Migration guard is enabled: logs are read-only here, and cross-merchant log access is blocked by the current front_token merchant scope.' => '当前页面会严格限制在当前商户范围内访问，不提供跨商户查询。',
            ', this Webman page keeps current keys masked on load, but now lets you rotate the merchant sign key and legacy appkey directly from Webman. QR payload generation and raw-secret export still stay blocked.' => '，当前页面会在加载时对现有密钥做脱敏展示，并支持重置签名密钥、通讯密钥与按所选线路生成商户二维码。原始密钥导出仍保持关闭。',
            'Current secrets remain masked until you choose to rotate them. Each reset returns the fresh secret one time so you can copy it into your integration before leaving this page.' => '现有密钥会一直以脱敏形式展示，只有在你主动重置时才会返回一次新的明文密钥，请在离开页面前完成保存。',
            'Use these actions to rotate the signing key stored in <code>ypay_user.user_key</code> or the legacy appkey stored in <code>ypay_userbasic.appkey</code>. Current values stay masked above, and each reset returns the fresh secret below one time only.' => '你可以在这里重置保存在 <code>ypay_user.user_key</code> 的签名密钥，或保存在 <code>ypay_userbasic.appkey</code> 的通讯密钥。当前值会保持脱敏，重置后仅返回一次新的明文密钥。',
            'Use the rotation panel above to generate a fresh sign key or appkey for the current merchant.' => '请使用上方密钥重置面板为当前商户生成新的签名密钥或通讯密钥。',
            'Legacy QR generation embeds merchant ID and raw key, so it is disabled during migration.' => '商户二维码会按所选线路、商户 ID 与当前通讯密钥生成加密信息，并在点击时按需展示。',
            ', this Webman page is read-only. VIP purchase and renewal are blocked until the audited merchant payment flow is migrated.' => '，当前页面仅展示会员套餐信息，购买与续费暂未开放。',
            ', this read-only Webman page replaces the first merchant order-log landing flow. Callback replay and status reset remain blocked until their audit controls are migrated.' => '，当前页面用于承接商户订单记录入口。已支持回调重放，状态重置入口已关闭。',
            'Migration guard is enabled: legacy `/Deal/set_function` requests now return a safe 405 response and do not replay callbacks or reset order status.' => '当前已关闭 `/Deal/set_function` 的状态重置能力，请直接使用现有订单操作入口。',
            ', your Webman merchant center can now create balance recharge orders directly. The only remaining legacy write guard on this screen is CDK redemption.' => '，当前已支持直接创建余额充值订单。该页面不处理卡密兑换，请使用卡密兑换入口。',
            'Create a new recharge order' => '创建新的充值订单',
            'Supported methods are loaded from the current system recharge mapping. Amounts outside the configured range are rejected before an upstream handoff is attempted.' => '可用充值方式由当前系统充值映射动态加载。超出配置范围的金额会在跳转上游前被直接拦截。',
            'Enabled methods: <strong>' => '已启用方式：<strong>',
            '. Minimum amount: <strong>' => '。最小金额：<strong>',
            '. Maximum amount: <strong>' => '。最大金额：<strong>',
            ', this read-only Webman page exposes your own balance ledger only. Balance adjustment, recharge, VIP purchase, and cleanup actions remain blocked until audited merchant controls are migrated.' => '，当前页面仅展示你自己的余额流水，暂不提供余额调账、充值补录、会员购买与日志清理操作。',
            ', notification-channel and low-balance settings can now be saved on Webman. Channel binding and test-send flows are still intentionally blocked until their external side effects are rebuilt.' => '，当前已支持保存通知渠道与余额预警设置。此页仅保留通知设置保存与渠道可用性查看。',
            'Save is enabled for the current merchant only and writes into <code>ypay_userbasic</code>. Binding QR flows, verification flows, and test-send actions remain blocked in this migration step.' => '当前仅允许为本商户保存设置，并写入 <code>ypay_userbasic</code>。通知渠道是否可用会直接在页面展示。',
            'Use the form below to persist low-balance, console, and voice-tip preferences. Unsupported delivery channels stay visible for audit clarity and normalize to <code>close</code> if they are disabled system-wide.' => '你可以通过下列表单保存余额预警、控制台提示与语音提醒偏好。系统级被禁用的渠道仍会展示出来用于确认当前可用状态，并在保存时自动归一到 <code>close</code>。',
            'Voice-tip text uses <code>[money]</code> as the amount placeholder.' => '语音提醒文案使用 <code>[money]</code> 作为金额占位符。',
            'Leave blank to clear the extra console notice line.' => '留空即可清空额外的控制台提示语。',
            'Voice template preview:' => '语音模板预览：',
            'Optional merchant console note' => '可选的商户控制台提示',
            'Saving notification settings...' => '正在保存通知设置...',
            'Notification settings saved successfully.' => '通知设置已保存。',
            'Notification save failed.' => '通知设置保存失败。',
            'Notification save failed. Please try again.' => '通知设置保存失败，请稍后重试。',
            ', this Webman page now supports replaying paid-order callbacks directly from the merchant center while keeping status reset under migration protection.' => '，当前页面已支持直接在商户中心重放已支付订单回调，状态重置入口已下线。',
            'Paid-order callback replay is now live on legacy <code>/Deal/set_function</code>. Status reset stays protected and still returns a safe 405 response.' => '订单回调接口 <code>/Deal/set_function</code> 现仅保留已支付订单回调重放；状态重置已关闭。',
            '>Detail<' => '>详情<',
            ', this Webman page keeps the legacy Google Auth landing route available, but verification, binding, unbinding, and QR enrollment remain blocked until the audited security flow is migrated.' => '，当前页面展示谷歌验证信息，暂不提供验证、绑定、解绑与二维码开通操作。',
            '当前页面已支持修改商户密码和重置密钥；Google Auth、实名认证和账号注销等流程仍保留在受控迁移阶段。' => '当前页面已支持修改商户密码和重置密钥；谷歌验证、实名认证和账号注销请在当前安全页中处理。',
            '密码修改已在 Webman 生效，保存后需要重新登录。Google Auth 开通/解绑及注销账号等高风险操作，仍会返回受控响应，待完整重建后再开放。' => '密码修改已生效，保存后需要重新登录。谷歌验证开通/解绑及账号注销等高风险操作，请在当前安全页中处理。',
            ', current page keeps the legacy Google Auth landing route available, but verification, binding, unbinding, and QR enrollment remain blocked until the audited security flow is migrated.' => '，当前页面展示谷歌验证信息，暂不提供验证、绑定、解绑与二维码开通操作。',
            'Security verification setup notice' => '安全验证说明',
            'This merchant flow is still pending migration.' => '当前商户安全功能暂不提供完整操作。',
            'Use the legacy flow if you need to complete Google Auth verification or binding during migration.' => '如需完成谷歌验证校验或绑定，请在当前安全页中处理。',
            'read-only' => '信息查看',
            'Password save failed.' => '密码保存失败。',
            'Password save failed. Please try again.' => '密码保存失败，请稍后重试。',
            ', this Webman page now lets you submit, edit, resubmit, and delete your own domains while keeping the audit scope locked to the current merchant.' => '，当前页面已支持提交、修改、重新提交与删除你自己的域名，同时严格将审核范围限制在当前商户内。',
            'New and edited domains still pass through the existing approval rules: blacklist blocks immediately, whitelist or auto-approve can mark a domain approved, and everything else returns to pending review.' => '新增或修改后的域名仍会沿用现有审核规则：命中黑名单会直接拦截，命中白名单或自动通过规则会直接审核通过，其余情况则进入待审核状态。',
            'Use this form to add a new merchant domain. Choosing an existing row for edit will switch the form into resubmission mode and reset that row back through the approval rules.' => '你可以通过这里提交新的商户域名。选择已有记录进行编辑后，表单会切换到重新提交模式，并重新走一遍审核规则。',
            'Shown in the domain review list for your merchant account.' => '该名称会展示在当前商户的域名审核列表中。',
            'Do not include spaces. `http://` and trailing `/` are normalized automatically.' => '请不要包含空格，`http://` 前缀和末尾 `/` 会自动规范化。',
            ', this Webman page now lets you create and delete your own support tickets while keeping admin replies and cross-merchant access safely scoped.' => '，当前页面已支持创建和删除你自己的工单，同时会安全限制管理员回复展示与跨商户访问范围。',
            'Ticket create and delete are enabled. Reply workflows still remain admin-side, so Webman only opens and removes merchant-owned tickets here.' => '当前已开放工单创建与删除。回复流程仍保留在管理员侧，因此这里只允许处理商户自己创建的工单。',
            'Open a new merchant ticket with one of the enabled categories. If no categories are enabled, the create form stays disabled until the admin restores at least one category.' => '请使用已启用的工单分类提交新的工单。如果当前没有任何启用分类，创建表单会保持禁用，直到管理员恢复至少一个分类。',
            'Categories come from the enabled admin ticket-category list.' => '工单分类来自管理员侧已启用的工单分类列表。',
            'Keep the title short and specific so the admin queue is easier to sort.' => '建议标题简短且明确，便于管理员侧队列快速分拣。',
            'Enabled Categories:' => '已启用分类：',
            'Describe the issue' => '请输入问题标题',
            'Provide the full issue details, steps, and expected outcome.' => '请填写完整的问题详情、复现步骤和期望结果。',
            ', this Webman page replaces the first merchant order-log landing flow. Callback replay and status reset remain blocked until their audit controls are migrated.' => '，当前页面用于承接商户订单记录入口。回调重放已开放，状态重置入口已下线。',
            'No merchant notification settings were found.' => '当前未找到通知设置项。',
            'No quick-login bindings are configured for this merchant.' => '当前商户未配置快捷登录绑定。',
            'No merchant order records matched the current filter.' => '当前筛选条件下暂无订单记录。',
            'No merchant recharge records matched the current filter.' => '当前筛选条件下暂无充值记录。',
            'No merchant money logs matched the current filter.' => '当前筛选条件下暂无资金日志。',
            'No merchant domains matched the current filter.' => '当前筛选条件下暂无域名记录。',
            'No merchant login logs matched the current filter.' => '当前筛选条件下暂无登录日志。',
            'No merchant tickets matched the current filter.' => '当前筛选条件下暂无工单记录。',
            'No enabled VIP packages matched the current filter.' => '当前筛选条件下暂无可用会员套餐。',
            'No enabled categories' => '暂无启用分类',
            'No enabled ticket categories are configured.' => '当前未配置可用工单分类。',
            'All enabled channels' => '全部已启用通道',
            'No collection quota' => '无限制额度',
            'No extra channel grant' => '无额外通道增配',
            'Unavailable because the global recharge mapping or upstream paylist is missing.' => '因全局充值映射或上游支付通道缺失，当前方式不可用。',
            'Accepted format: non-negative amount with up to 2 decimal places.' => '格式要求：非负金额，最多支持 2 位小数。',
            'Use the direct-save panel below' => '请使用下方直存面板',
            'Not available' => '暂不可用',
            'No recent merchant login logs are available.' => '当前没有可展示的最近登录日志。',
            'No payload captured' => '未采集到载荷',
            'No merchant orders matched the current filter.' => '当前筛选条件下暂无订单记录。',
            'No gateway lines are available.' => '当前没有可用网关线路。',
            'No endpoint metadata is available.' => '当前没有可展示的接口说明。',
            'Legacy EPay compatible browser form entry.' => '易支付网关的浏览器表单下单入口。',
            'Saving WxPusher UID...' => '正在保存微信推送标识...',
            'WxPusher UID saved.' => '微信推送标识已保存。',
            'WxPusher UID save failed.' => '微信推送标识保存失败。',
            'WxPusher UID save failed. Please try again.' => '微信推送标识保存失败，请稍后重试。',
            '。你可以直接粘贴新的 UID，也可以清空当前商户已保存的值。' => '。你可以直接粘贴新的微信推送标识，也可以清空当前商户已保存的值。',
            '>保存 UID<' => '>保存标识<',
            '>清空 UID<' => '>清空标识<',
            'Checking current WxPusher status...' => '正在检查当前微信推送状态...',
            'A WxPusher UID is already stored for this merchant.' => '当前商户已保存微信推送标识。',
            'No WxPusher UID is stored yet.' => '当前商户尚未保存微信推送标识。',
            'Status check failed. Please try again.' => '状态检查失败，请稍后重试。',
            'Clear the stored WxPusher UID for the current merchant?' => '确认清空当前商户已保存的微信推送标识吗？',
            'Clearing WxPusher UID...' => '正在清空微信推送标识...',
            'WxPusher UID cleared.' => '微信推送标识已清空。',
            'Saving Telegram chat ID...' => '正在保存电报会话标识...',
            'Telegram chat ID saved.' => '电报会话标识已保存。',
            'Telegram save failed.' => '电报会话标识保存失败。',
            'Telegram save failed. Please try again.' => '电报会话标识保存失败，请稍后重试。',
            '。在这里保存后，会直接替换当前商户的 Telegram Chat ID。' => '。在这里保存后，会直接替换当前商户的电报会话标识。',
            'placeholder="请输入 Telegram Chat ID"' => 'placeholder="请输入电报会话标识"',
            '>保存 Chat ID<' => '>保存标识<',
            '>清空 Chat ID<' => '>清空标识<',
            'Clear the stored Telegram chat ID for the current merchant?' => '确认清空当前商户已保存的电报会话标识吗？',
            'Clearing Telegram chat ID...' => '正在清空电报会话标识...',
            'Telegram chat ID cleared.' => '电报会话标识已清空。',
            'Clear the stored ' . '\' + button.dataset.label + \'' . ' binding for the current merchant?' => '确认清除当前商户的\' + button.dataset.label + \'绑定信息吗？',
            'Unbind failed.' => '解绑失败。',
            'Unbind failed. Please try again.' => '解绑失败，请稍后重试。',
            ', your Webman merchant center can now create balance recharge orders and redeem CDKs directly from the same screen.' => '，当前已可在同一页面直接创建余额充值订单并兑换卡密。',
            'A successful callback credits the merchant balance, writes a `money_log` row, and applies the configured superior rebate when `is_aff=1` and `aff_type=0`.' => '充值成功回调后会为商户余额入账、写入 `money_log` 记录，并在 `is_aff=1` 且 `aff_type=0` 时结算对应上级返佣。',
            'CDK redemption is now live for balance cards and VIP cards owned by the current merchant session.' => '当前商户会话下，余额卡与会员卡的卡密兑换功能已开放。',
            'Last recharge:' => '最近充值：',
            'Pending amount:' => '待支付金额：',
            'Last order:' => '最近订单：',
            'Last money log:' => '最近资金日志：',
            'Last domain submission:' => '最近域名提交：',
            'Last login log:' => '最近登录日志：',
            'Last ticket:' => '最近工单：',
            'Legacy EPay compatible JSON/API entry.' => '易支付网关的 JSON/API 下单入口。',
            'Upstream payment notify callback entry.' => '上游支付异步回调入口。',
            'Upstream payment return callback entry.' => '上游支付同步跳转入口。',
            'CDK redemption is still intentionally guarded until that branch is migrated and audited separately.' => 'CDK 兑换当前暂未开放。',
            'A successful callback credits the merchant balance, writes a `money_log` row, and applies the configured superior rebate when `is_aff=1` and `aff_type=0`.' => '充值成功回调后，会为商户余额入账、写入 `money_log` 流水，并在 `is_aff=1` 且 `aff_type=0` 时结算上级返佣。',
            'Rebate Type:' => '返佣类型：',
            'Rebate Ratio:' => '返佣比例：',
            'Upstream Merchant:' => '上级商户：',
            'Invite URL:' => '邀请链接：',
            'Last Invite:' => '最近邀请时间：',
            'Upstream Channel' => '上游通道',
            'Local Channel' => '本地通道',
            'Normal merchant' => '普通商户',
            'Current: ' => '当前：',
            'Current: Closed' => '当前：关闭',
            'Current: Email' => '当前：邮箱',
            'Current: WxPusher' => '当前：WxPusher',
            'Current: Telegram' => '当前：Telegram',
            'read-only' => '只读',
            'Live' => '已启用',
            'Use order callback host' => '使用订单回调域名',
            'Use configured timeout_url' => '使用已配置的超时跳转地址',
            'Balance recharge' => '余额充值',
            'Balance deduction' => '余额扣减',
            'Balance increase' => '余额增加',
            'Balance decrease' => '余额减少',
            'Merchant password change is live in the Webman merchant center and will require a fresh login after save' => '商户密码修改已生效，保存后需要重新登录。',
            'Quick-login unbind is live in Webman. Fresh OAuth bind flows still follow the legacy route during migration.' => '快捷登录解绑已生效；当前页面仅展示已接入状态，不再提供新的授权绑定入口。',
            ' (disabled)' => '（已关闭）',
            'Signing in...' => '正在登录...',
            'Login failed. Please try again.' => '登录失败，请稍后重试。',
            'Waiting for payment confirmation' => '等待支付确认',
            'The page will refresh the status automatically.' => '页面会自动刷新支付状态。',
            'QR code expired. Redirecting back to the recharge page...' => '二维码已过期，正在返回充值页面...',
            'Recharge timed out' => '充值已超时',
            'Create a new recharge order if you still need to top up the balance.' => '如仍需充值，请重新创建一笔充值订单。',
            'Recharge paid successfully' => '充值支付成功',
            'Redirecting back to the merchant recharge list now.' => '正在返回商户充值记录列表。',
            'QR code ready' => '二维码已生成',
            'Please finish the payment in the matching wallet app.' => '请在对应的钱包应用内完成支付。',
            'Saving notification settings...' => '正在保存通知设置...',
            'Notification settings saved successfully.' => '通知设置保存成功。',
            'Notification save failed.' => '通知设置保存失败。',
            'Notification save failed. Please try again.' => '通知设置保存失败，请稍后重试。',
            'Saving domain changes...' => '正在保存域名修改...',
            'Creating domain...' => '正在创建域名...',
            'Domain saved successfully.' => '域名保存成功。',
            'Domain save failed.' => '域名保存失败。',
            'Domain save failed. Please try again.' => '域名保存失败，请稍后重试。',
            'Editing domain #' => '正在编辑域名 #',
            'Deleting domain...' => '正在删除域名...',
            'Domain deleted successfully.' => '域名删除成功。',
            'Domain delete failed.' => '域名删除失败。',
            'Domain delete failed. Please try again.' => '域名删除失败，请稍后重试。',
            'Edit / Resubmit Domain' => '编辑 / 重新提交域名',
            'this domain' => '该域名',
            'Creating ticket...' => '正在创建工单...',
            'Ticket created successfully.' => '工单创建成功。',
            'Ticket create failed.' => '工单创建失败。',
            'Ticket create failed. Please try again.' => '工单创建失败，请稍后重试。',
            'Deleting ticket...' => '正在删除工单...',
            'Ticket deleted successfully.' => '工单删除成功。',
            'Ticket delete failed.' => '工单删除失败。',
            'Ticket delete failed. Please try again.' => '工单删除失败，请稍后重试。',
            'Saving merchant password...' => '正在保存商户密码...',
            'Saving sign key...' => '正在重置签名密钥...',
            'Saving appkey...' => '正在重置通讯密钥...',
            'Password updates are available here.' => '当前页面支持直接修改登录密码。',
            'Password save failed.' => '密码保存失败。',
            'Password save failed. Please try again.' => '密码保存失败，请稍后重试。',
            'Resetting ' => '正在重置',
            ' reset successfully. Copy the fresh secret now.' => ' 重置成功，请立即保存新的密钥。',
            ' reset successfully. Copy the new secret now.' => ' 重置成功，请立即保存新的密钥。',
            ' reset failed.' => ' 重置失败。',
            ' reset failed. Please try again.' => ' 重置失败，请稍后重试。',
            'Generate a fresh merchant sign key now?' => '确认立即生成新的商户签名密钥吗？',
            'Generate a fresh merchant appkey now?' => '确认立即生成新的商户通讯密钥吗？',
            'sign key' => '签名密钥',
            'appkey' => '通讯密钥',
            'Bind tips: ' => '绑定提示：',
            'No recharge methods are configured yet. Bind an upstream paylist in system config before using merchant balance top-up.' => '当前尚未配置可用的充值方式，请先在系统配置中绑定上游支付通道后再使用商户余额充值。',
            'Use the rotation panel above to generate a fresh sign key or appkey for the current merchant.' => '可通过上方密钥重置面板为当前商户生成新的签名密钥或通讯密钥。',
            'cannot enter the merchant center right now.' => '当前暂时无法进入商户中心。',
            'Delete ' => '删除 ',
            'this ticket' => '该工单',
            'No reason was provided.' => '未提供原因说明。',
            'Nothing to unbind' => '暂无可解绑内容',
            '>Unbind<' => '>解绑<',
            'placeholder="Optional merchant console note"' => 'placeholder="可选的控制台提示内容"',
            'placeholder="Describe the issue"' => 'placeholder="请简要描述问题"',
            'placeholder="Provide the full issue details, steps, and expected outcome."' => 'placeholder="请填写完整的问题详情、复现步骤与预期结果"',
            'placeholder="My Checkout Site"' => 'placeholder="例如：我的收银站"',
            'placeholder="checkout.example.com"' => 'placeholder="例如：pay.aipay.local"',
        ] as $search => $replacement) {
            $replacements[$search] = $this->normalizePortalReplacement($search, $replacement);
        }

        return strtr($html, $replacements);
    }

    private function normalizePortalReplacement(string $search, string $replacement): string
    {
        $replacement = $this->repairPortalReplacementText($replacement);
        $fallback = $this->portalReplacementFallback($search);
        if ($fallback !== null && $this->portalMojibakeScore($replacement) > 0) {
            return $fallback;
        }

        if (str_starts_with($search, '<title>') && str_ends_with($search, '</title>')) {
            return '<title>' . $this->stripPortalTitle($replacement) . '</title>';
        }

        if (str_starts_with($search, '>') && str_ends_with($search, '<')) {
            return '>' . $this->stripPortalTextNode($replacement) . '<';
        }

        return $replacement;
    }

    private function repairPortalReplacementText(string $value): string
    {
        $value = ApiResponse::normalizeText($value);
        $converted = @mb_convert_encoding($value, 'GB18030', 'UTF-8');
        if (is_string($converted) && $converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
            if ($this->portalMojibakeScore($converted) < $this->portalMojibakeScore($value)) {
                $value = $converted;
            }
        }

        $value = str_replace('閿?', '', $value);
        $value = preg_replace('/\?+(?=$|[\s，。；：、）】>])/u', '', $value) ?? $value;

        return trim($value);
    }

    private function stripPortalTitle(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/^<title>/u', '', $value) ?? $value;
        $value = preg_replace('/(?:<\/title>|\/title>|title>)$/u', '', $value) ?? $value;

        return trim($value, " \t\n\r\0\x0B<>/");
    }

    private function stripPortalTextNode(string $value): string
    {
        $value = trim($value);
        if (str_starts_with($value, '>')) {
            $value = substr($value, 1);
        }
        if (str_ends_with($value, '<')) {
            $value = substr($value, 0, -1);
        }

        return trim($value, " \t\n\r\0\x0B?");
    }

    private function portalMojibakeScore(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        preg_match_all('/[鍟鎴璇閫鏀鐧鍏瑙妯褰寰缁鍒瀵庡叏]/u', $text, $matches);

        return count($matches[0]);
    }

    private function portalReplacementFallback(string $search): ?string
    {
        $token = $this->portalSearchToken($search);
        $map = [
            'Merchant Login' => '商户登录',
            'Merchant Security' => '安全中心',
            'Merchant Notifications' => '通知设置',
            'Merchant Connections' => '绑定中心',
            'Merchant Orders' => '订单记录',
            'Merchant Recharges' => '充值记录',
            'Merchant Money Logs' => '资金日志',
            'Merchant VIP Packages' => '会员套餐',
            'Merchant API' => '接口信息',
            'Merchant Login Logs' => '登录日志',
            'Merchant Google Auth' => '谷歌验证',
            'Merchant Affiliate' => '推广返佣',
            'Merchant Real Name' => '实名认证',
            'Merchant Domains' => '域名管理',
            'Merchant Tickets' => '工单中心',
            'Merchant Frozen' => '账户冻结',
            'Webman Merchant Portal' => '商户中心',
            'Merchant Center' => '商户中心',
            'Profile' => '资料维护',
            'Security Center' => '安全中心',
            'Security' => '安全中心',
            'Connections' => '绑定中心',
            'Real Name' => '实名认证',
            'Orders' => '订单记录',
            'Recharges' => '充值记录',
            'Money Logs' => '资金日志',
            'VIP Packages' => '会员套餐',
            'API Info' => '接口信息',
            'Tickets' => '工单中心',
            'Domains' => '域名管理',
            'Login Logs' => '登录日志',
            'Affiliate' => '推广返佣',
            'JSON View' => '查看 JSON',
            'Logout' => '退出登录',
            'Back to Migration Entry' => '返回首页',
            'Enter Merchant Center' => '进入商户中心',
            'Google Auth' => '谷歌验证',
            'Enabled' => '已启用',
            'Disabled' => '已关闭',
            'Required' => '必填',
            'Optional' => '可选',
            'Available' => '可用',
            'Unavailable' => '不可用',
            'Configured' => '已配置',
            'Not configured' => '未配置',
            'Bound' => '已绑定',
            'Not bound' => '未绑定',
            'Closed' => '关闭',
            'Current' => '当前',
            'Read Only' => '只读查看',
            'Blocked' => '受限',
            'Missing' => '缺失',
            'Verified' => '已认证',
            'Not verified' => '未认证',
            'Pending verification' => '待认证',
            'No active VIP' => '暂无有效会员',
            'Normal merchant' => '普通商户',
            'VIP active' => '会员有效',
            'VIP expired' => '会员已过期',
            'Unknown' => '未知',
            'Behavior Log' => '行为日志',
            'Login Event' => '登录事件',
            'Security Event' => '安全事件',
            'Merchant Action' => '商户操作',
            'Provider' => '提供方',
            'Status' => '状态',
            'Masked Secret' => '脱敏密钥',
            'Migration State' => '迁移状态',
            'Write Actions' => '写入能力',
            'Low Balance Threshold' => '余额预警阈值',
            'Console Notice' => '控制台提示',
            'Voice Tips' => '语音提示',
            'Setting' => '设置项',
            'Delivery Channel' => '通知渠道',
            'Available Channels' => '可用渠道',
            'Action' => '操作',
            'Email' => '邮箱',
            'Mobile' => '手机号',
            'QQ Login' => 'QQ 登录',
            'WeChat Login' => '微信登录',
            'Save Notification Settings' => '保存通知设置',
            'Reset' => '重置',
            'Current VIP' => '当前会员',
            'VIP Expiry' => '会员到期时间',
            'Enabled Plans' => '可用套餐数',
            'Price Range' => '价格区间',
            'Purchase' => '购买状态',
            'Mode' => '模式',
            'Migration Guard' => '迁移保护',
            'Key Actions' => '密钥操作',
            'Key Rotation' => '密钥重置',
            'Reset Sign Key' => '重置签名密钥',
            'Reset Appkey' => '重置通讯密钥',
            'Gateway Lines' => '网关线路',
            'Timeout Settings' => '超时设置',
            'Endpoint Overview' => '接口总览',
            'Merchant ID' => '商户 ID',
            'Username' => '登录账号',
            'Sign Key' => '签名密钥',
            'Legacy Appkey' => '通讯密钥',
            'Masked Sign Key' => '已脱敏签名密钥',
            'Masked Appkey' => '已脱敏通讯密钥',
            'Timeout Seconds' => '超时秒数',
            'Pending' => '待处理',
            'Total' => '总数',
            'Paid' => '已支付',
            'Expired Pending' => '已过期待支付',
            'Approved' => '已通过',
            'Rejected' => '已驳回',
            'Submit Domain' => '提交域名',
            'Create Domain' => '创建域名',
            'Cancel Edit' => '取消编辑',
            'Total Logs' => '日志总数',
            'Today' => '今日',
            'Payload Logs' => '载荷日志',
            'IP Count' => 'IP 数量',
            'Log' => '日志',
            'Path' => '路径',
            'Type' => '类型',
            'User Agent' => '客户端信息',
            'New' => '新建',
            'Processing' => '处理中',
            'Resolved' => '已解决',
            'Replied' => '已回复',
            'Category' => '分类',
            'Title' => '标题',
            'Content' => '内容',
            'Reply' => '回复',
            'Create Ticket' => '创建工单',
            'Record' => '记录',
            'Order' => '订单',
            'Trade' => '交易信息',
            'Method' => '方式',
            'Amount' => '金额',
            'Paid At' => '支付时间',
            'Total Orders' => '订单总数',
            'Paid Orders' => '已支付订单',
            'Pending Orders' => '待支付订单',
            'Paid Amount' => '支付金额',
            'Success Rate' => '成功率',
            'Income Entries' => '收入笔数',
            'Expense Entries' => '支出笔数',
            'Income Amount' => '收入金额',
            'Expense Amount' => '支出金额',
            'Net Change' => '净变动',
            'Change' => '变动',
            'Balance' => '余额',
            'Memo' => '备注',
            'Recharge amount' => '充值金额',
            'Recharge method' => '充值方式',
            'Merchant' => '商户',
            'Display' => '显示名称',
            'Verification' => '认证状态',
            'Duration' => '时长',
            'Fee Rate' => '费率',
            'Channels' => '通道范围',
            'Base URL' => '基础地址',
            'Line' => '线路',
            'Entry' => '入口',
            'Entries' => '入口地址',
            'Endpoint Reference' => '接口说明',
            'Note' => '说明',
            'Open JSON payload' => '打开 JSON 数据',
            'Recharge rules' => '充值规则',
            'Save Domain Changes' => '保存域名修改',
            'Use order callback host' => '使用订单回调域名',
            'Use configured timeout_url' => '使用已配置的超时跳转地址',
            'Timeout URL' => '超时地址',
            'QR Code' => '二维码',
            'Key Reset' => '密钥重置',
            'Live' => '已生效',
            'Site Name' => '站点名称',
            'Site Domain' => '站点域名',
            'Current:' => '当前：',
            'Saved with form' => '随表单保存',
            'Create and continue to payment' => '创建订单并继续支付',
            'Purchase blocked during migration' => '当前暂未开放购买',
            'Voice Template' => '语音模板',
            'Delete' => '删除',
            'Edit / Resubmit' => '编辑 / 重新提交',
            'Payload' => '载荷',
            'Detail' => '详情',
            'Unbind' => '解绑',
            'Optional merchant console note' => '可选的控制台提示内容',
            'Describe the issue' => '请简要描述问题',
            'Provide the full issue details, steps, and expected outcome.' => '请填写完整的问题详情、复现步骤与预期结果',
            'My Checkout Site' => '例如：我的收银站',
            'checkout.example.com' => '例如：pay.aipay.local',
        ];

        if (!isset($map[$token])) {
            return null;
        }

        $value = $map[$token];

        if (str_starts_with($search, '<title>') && str_ends_with($search, '</title>')) {
            return '<title>' . $value . '</title>';
        }

        if (str_starts_with($search, '>') && str_ends_with($search, '<')) {
            return '>' . $value . '<';
        }

        if (preg_match('/^placeholder="(.+)"$/', $search) === 1) {
            return 'placeholder="' . $value . '"';
        }

        return $value;
    }

    private function portalSearchToken(string $search): string
    {
        if (str_starts_with($search, '<title>') && str_ends_with($search, '</title>')) {
            return trim(substr($search, 7, -8));
        }

        if (str_starts_with($search, '>') && str_ends_with($search, '<')) {
            return trim(substr($search, 1, -1));
        }

        if (preg_match('/^placeholder="(.+)"$/', $search, $matches) === 1) {
            return $matches[1];
        }

        return $search;
    }

    private function featureDisabledPage(array $merchant, string $title, string $summary, string $detail): string
    {
        $displayName = $this->escape($this->merchantDisplay($merchant));
        $title = $this->escape($title);
        $summary = $this->escape($summary);
        $detail = $this->escape($detail);

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
      <a class="btn secondary" href="/User/Index">商户中心</a>
      <a class="btn secondary" href="/My/userpro">资料维护</a>
      <a class="btn secondary" href="/My/Security">安全中心</a>
      <a class="btn secondary" href="/Deal/OrderLog">订单记录</a>
      <a class="btn secondary" href="/My/loginlog">登录日志</a>
      <a class="btn" href="/User/Logout">退出登录</a>
    </nav>
  </div>
</body>
</html>
HTML;
    }

    private function dashboardPage(array $merchant): string
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $stats = $this->orderStats($merchantId);
        $channelStats = $this->channelStats($merchantId);
        $displayName = $this->escape($this->merchantDisplay($merchant));
        $username = $this->escape((string)($merchant['username'] ?? ''));
        $balance = $this->money($merchant['money'] ?? 0);
        $feeRate = $this->escape($this->feeRate($merchant['feilv'] ?? null));
        $vip = $this->escape($this->vipLabel($merchant));
        $createdAt = $this->escape((string)($merchant['create_time'] ?? '--'));
        $lastOrder = $this->escape((string)($stats['last_order_time'] ?? '--'));
        $orderCount = (int)($stats['order_count'] ?? 0);
        $paidOrderCount = (int)($stats['paid_order_count'] ?? 0);
        $paidAmount = $this->money($stats['paid_amount'] ?? 0);
        $todayPaidAmount = $this->money($stats['today_paid_amount'] ?? 0);
        $accountCount = (int)($channelStats['account_count'] ?? 0);
        $upstreamCount = (int)($channelStats['upstream_count'] ?? 0);

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>商户中心</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f6f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1120px;margin:0 auto 20px;padding:28px;border-radius:24px;background:linear-gradient(135deg,#0f172a,#155e75);color:#fff;box-shadow:0 20px 60px rgba(15,23,42,.18)}
    .hero h1{margin:0 0 8px;font-size:30px}
    .hero p{margin:0;color:#cbd5e1;line-height:1.7}
    .grid{max-width:1120px;margin:0 auto;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:18px;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    .card span{display:block;color:#64748b;font-size:13px;margin-bottom:8px}
    .card strong{display:block;font-size:24px;color:#0f172a}
    .wide{grid-column:span 2}
    .meta{font-size:14px;line-height:1.8;color:#475569}
    .actions{max-width:1120px;margin:18px auto 0;display:flex;gap:10px;flex-wrap:wrap}
    .btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#0f172a;color:#fff;text-decoration:none}
    .btn.secondary{background:#e2e8f0;color:#0f172a}
    @media (max-width:900px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.wide{grid-column:span 2}}
    @media (max-width:560px){.grid{grid-template-columns:1fr}.wide{grid-column:span 1}.shell{padding:18px}.hero{padding:22px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>商户中心</h1>
      <p>欢迎你，{$displayName}。这里是商户中心首页，当前主要展示账户与支付经营概览，方便你快速查看核心经营数据。</p>
    </section>
    <section class="grid">
      <div class="card"><span>账户余额</span><strong>{$balance}</strong></div>
      <div class="card"><span>累计已付金额</span><strong>{$paidAmount}</strong></div>
      <div class="card"><span>今日已付金额</span><strong>{$todayPaidAmount}</strong></div>
      <div class="card"><span>已付订单数</span><strong>{$paidOrderCount}</strong></div>
      <div class="card"><span>订单总数</span><strong>{$orderCount}</strong></div>
      <div class="card"><span>本地账户数</span><strong>{$accountCount}</strong></div>
      <div class="card"><span>上游通道数</span><strong>{$upstreamCount}</strong></div>
      <div class="card"><span>费率</span><strong>{$feeRate}</strong></div>
      <div class="card wide">
        <span>商户资料</span>
        <div class="meta">商户 ID：{$merchantId}<br>登录账号：{$username}<br>会员状态：{$vip}<br>创建时间：{$createdAt}</div>
      </div>
      <div class="card wide">
        <span>运行说明</span>
        <div class="meta">最近订单：{$lastOrder}<br>当前已按页面拆分资料、安全、订单、充值、通知等常用入口，可直接进入对应功能页面操作。</div>
      </div>
    </section>
    <nav class="actions">
      <a class="btn secondary" href="/index.php">返回首页</a>
      <a class="btn secondary" href="/My/userpro">资料维护</a>
      <a class="btn secondary" href="/My/Security">安全中心</a>
      <a class="btn secondary" href="/My/real_name">实名认证</a>
      <a class="btn secondary" href="/My/Notifications">通知设置</a>
      <a class="btn secondary" href="/My/Connections">绑定中心</a>
      <a class="btn secondary" href="/My/aff">推广返佣</a>
      <a class="btn secondary" href="/Deal/OrderLog">订单记录</a>
      <a class="btn secondary" href="/Deal/Recharge">充值记录</a>
      <a class="btn secondary" href="/Deal/MoneyLog">资金日志</a>
      <a class="btn secondary" href="/Deal/Vip">会员套餐</a>
      <a class="btn secondary" href="/My/Api">接口信息</a>
      <a class="btn secondary" href="/My/Ticket">工单中心</a>
      <a class="btn secondary" href="/My/is_domain">域名管理</a>
      <a class="btn secondary" href="/My/loginlog">登录日志</a>
      <a class="btn" href="/User/Logout">退出登录</a>
    </nav>
  </div>
</body>
</html>
HTML;
    }

    private function loginPage(): string
    {
        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>商户登录</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:radial-gradient(circle at top left,#dcfce7,transparent 32%),linear-gradient(135deg,#f8fafc,#e0f2fe);color:#172033}
    .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .card{width:min(520px,100%);background:rgba(255,255,255,.92);border:1px solid #dbeafe;border-radius:24px;padding:30px;box-shadow:0 24px 70px rgba(15,23,42,.14);backdrop-filter:blur(12px)}
    .eyebrow{margin:0 0 10px;color:#0f766e;font-weight:700;letter-spacing:.08em;text-transform:uppercase;font-size:12px}
    h1{margin:0 0 10px;font-size:30px}
    p{margin:0 0 18px;color:#475569;line-height:1.8}
    label{display:block;margin:14px 0 7px;color:#334155;font-size:14px;font-weight:600}
    input{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:13px 14px;font:inherit;outline:none;background:#fff}
    input:focus{border-color:#0f766e;box-shadow:0 0 0 4px rgba(15,118,110,.12)}
    button,a{display:inline-flex;align-items:center;justify-content:center;padding:11px 15px;border-radius:13px;border:0;font:inherit;text-decoration:none;cursor:pointer}
    button{margin-top:18px;width:100%;background:#0f172a;color:#fff;font-weight:700}
    a{margin-top:12px;background:#e2e8f0;color:#0f172a}
    .status{min-height:22px;margin-top:14px;color:#b91c1c;font-size:14px;line-height:1.5}
    .note{margin-top:18px;padding:12px 14px;border-radius:14px;background:#f8fafc;color:#64748b;font-size:13px}
  </style>
</head>
<body>
  <div class="wrap">
    <main class="card">
      <p class="eyebrow">AiPay 商户中心</p>
      <h1>商户登录</h1>
      <p>商户登录统一使用账号密码。若系统启用了额外安全校验，会在登录后进入对应安全流程。</p>
      <form id="loginForm" method="post" action="">
        <label for="username">登录账号</label>
        <input id="username" name="username" autocomplete="username" required>
        <label for="password">登录密码</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit">进入商户中心</button>
      </form>
      <div id="status" class="status" role="alert"></div>
      <div class="note">当前登录页聚焦账号密码主链路；额外安全校验会在独立安全步骤中处理。</div>
      <a href="/index.php">返回统一入口</a>
    </main>
  </div>
  <script>
    const form = document.getElementById('loginForm');
    const statusNode = document.getElementById('status');
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      statusNode.textContent = '正在登录...';
      const response = await fetch(form.action, {
        method: 'POST',
        headers: {'Accept': 'application/json'},
        body: new FormData(form)
      });
      const payload = await response.json().catch(() => null);
      if (payload && Number(payload.code) === 200) {
        window.location.href = payload.data && payload.data.url ? payload.data.url : '/';
        return;
      }
      statusNode.textContent = payload && payload.message ? payload.message : '登录失败，请重试。';
    });
  </script>
</body>
</html>
HTML;
    }

    private function profilePage(array $merchant, array $payload): string
    {
        $displayName = $this->escape($this->merchantDisplay($merchant));
        $profile = (array)($payload['profile'] ?? []);
        $apiSettings = (array)($payload['api_settings'] ?? []);
        $merchantId = (int)($profile['id'] ?? 0);
        $username = $this->escape((string)($profile['username'] ?? ''));
        $emailValue = $this->escape((string)($profile['email'] ?? ''));
        $mobileValue = $this->escape((string)($profile['mobile'] ?? ''));
        $email = $emailValue === '' ? '未设置' : $emailValue;
        $mobile = $mobileValue === '' ? '未设置' : $mobileValue;
        $money = $this->escape((string)($profile['money_display'] ?? '0.00'));
        $feeRate = $this->escape((string)($profile['fee_rate'] ?? '--'));
        $vipLabel = $this->escape((string)($profile['vip_label'] ?? '普通商户'));
        $vipTime = $this->escape((string)($profile['vip_time'] ?? '不限时'));
        $createdAt = $this->escape((string)($profile['create_time'] ?? '--'));
        $timeoutUrl = $this->escape((string)($apiSettings['timeout_url'] ?? '/'));
        $timeoutTime = (int)($apiSettings['timeout_time'] ?? 0);
        $timeoutMethod = $this->escape((string)($apiSettings['timeout_method_label'] ?? '使用已配置的超时跳转地址'));

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>商户资料维护</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f6f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1180px;margin:0 auto 18px;padding:26px;border-radius:24px;background:linear-gradient(135deg,#0f172a,#0e7490);color:#fff;box-shadow:0 20px 60px rgba(15,23,42,.18)}
    .hero h1{margin:0 0 8px;font-size:30px}.hero p{margin:0;color:#cffafe;line-height:1.7}
    .stats{max-width:1180px;margin:0 auto 16px;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;box-shadow:0 14px 34px rgba(15,23,42,.06)}
    .card span{display:block;color:#64748b;font-size:13px;margin-bottom:7px}.card strong{font-size:22px;word-break:break-all}
    .notice{max-width:1180px;margin:0 auto 16px;padding:15px 16px;border-radius:18px;background:#ecfeff;border:1px solid #a5f3fc;color:#155e75;line-height:1.7}
    .panel{max-width:1180px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    table{width:100%;border-collapse:collapse}th,td{padding:14px 16px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}th{background:#f8fafc;color:#475569;font-size:13px;width:220px}td{font-size:14px;word-break:break-all}
    .badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;background:#fef3c7;color:#92400e}.badge.success{background:#dcfce7;color:#166534}
    .form-panel{margin:16px auto 0}
    .form-shell{padding:22px}
    .form-shell h2{margin:0 0 8px;font-size:22px}
    .form-shell p{margin:0 0 18px;color:#64748b;line-height:1.7}
    .form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .field label{display:block;margin:0 0 8px;color:#334155;font-size:14px;font-weight:600}
    .field input{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:12px;padding:12px 13px;font:inherit;outline:none}
    .field input:focus{border-color:#0f766e;box-shadow:0 0 0 4px rgba(15,118,110,.12)}
    .field small{display:block;margin-top:6px;color:#64748b}
    .toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
    button.btn{border:0;cursor:pointer;font:inherit}
    .btn.primary{background:#0f766e}
    .status{min-height:22px;margin-top:14px;color:#0f766e;font-size:14px;line-height:1.6}
    .status.error{color:#b91c1c}
    .actions{max-width:1180px;margin:16px auto 0;display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#0f172a;color:#fff;text-decoration:none}.btn.secondary{background:#e2e8f0;color:#0f172a}
    @media (max-width:1000px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media (max-width:760px){.form-grid{grid-template-columns:1fr}}
    @media (max-width:560px){.shell{padding:18px}.stats{grid-template-columns:1fr}.hero{padding:22px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>资料维护</h1>
      <p>{$displayName}，当前页面已支持直接修改邮箱和手机号；接口超时、密钥等高风险项目仍放在各自独立页面维护。</p>
    </section>
    <section class="notice">这里只允许维护当前商户自己的联系方式。余额、会员状态和超时配置会在对应页面单独维护，避免资料页承载过多高风险操作。</section>
    <section class="stats">
      <div class="card"><span>商户 ID</span><strong>{$merchantId}</strong></div>
      <div class="card"><span>登录账号</span><strong>{$username}</strong></div>
      <div class="card"><span>账户余额</span><strong>{$money}</strong></div>
      <div class="card"><span>费率</span><strong>{$feeRate}</strong></div>
      <div class="card"><span>可写状态</span><strong>已启用</strong></div>
    </section>
    <section class="panel">
      <table>
        <tbody>
          <tr><th>邮箱</th><td>{$email}</td></tr>
          <tr><th>手机号</th><td>{$mobile}</td></tr>
          <tr><th>会员等级</th><td>{$vipLabel}<br><small>{$vipTime}</small></td></tr>
          <tr><th>创建时间</th><td>{$createdAt}</td></tr>
          <tr><th>超时地址</th><td>{$timeoutUrl}</td></tr>
          <tr><th>超时时长</th><td>{$timeoutTime}</td></tr>
          <tr><th>超时方式</th><td>{$timeoutMethod}</td></tr>
          <tr><th>资料修改</th><td><span class="badge success">邮箱/手机号可直接保存</span></td></tr>
        </tbody>
      </table>
    </section>
    <section class="panel form-panel">
      <div class="form-shell">
        <h2>修改联系方式</h2>
        <p>这里只能修改当前商户自己的邮箱和手机号。接口密钥、安全绑定等更高风险操作仍在独立页面处理。</p>
        <form id="profileForm" method="post" action="/My/userpro">
          <div class="form-grid">
            <div class="field">
              <label for="profileEmail">邮箱</label>
              <input id="profileEmail" name="email" type="email" value="{$emailValue}" placeholder="merchant@aipay.local" autocomplete="email">
              <small>如当前商户无需保留邮箱，可留空保存。</small>
            </div>
            <div class="field">
              <label for="profileMobile">手机号</label>
              <input id="profileMobile" name="mobile" type="text" value="{$mobileValue}" placeholder="13800138000" inputmode="numeric" autocomplete="tel">
              <small>如需配置手机号，请使用 11 位中国大陆手机号。</small>
            </div>
          </div>
          <div class="toolbar">
            <button class="btn primary" type="submit">保存联系方式</button>
            <button class="btn secondary" type="reset">重置</button>
          </div>
          <div id="profileStatus" class="status" role="status" aria-live="polite"></div>
        </form>
      </div>
    </section>
    <nav class="actions">
      <a class="btn secondary" href="/User/Index">商户中心</a>
      <a class="btn secondary" href="/My/userpro">资料维护</a>
      <a class="btn secondary" href="/My/Security">安全中心</a>
      <a class="btn secondary" href="/My/Notifications">通知设置</a>
      <a class="btn secondary" href="/My/Connections">绑定中心</a>
      <a class="btn secondary" href="/Deal/OrderLog">订单记录</a>
      <a class="btn secondary" href="/Deal/Recharge">充值记录</a>
      <a class="btn secondary" href="/Deal/MoneyLog">资金日志</a>
      <a class="btn secondary" href="/Deal/Vip">会员套餐</a>
      <a class="btn secondary" href="/My/Api">接口信息</a>
      <a class="btn secondary" href="/My/Ticket">工单中心</a>
      <a class="btn secondary" href="/My/is_domain">域名管理</a>
      <a class="btn secondary" href="/My/loginlog">登录日志</a>
      <a class="btn secondary" href="/My/userpro?format=json">查看 JSON</a>
      <a class="btn" href="/User/Logout">退出登录</a>
    </nav>
  </div>
  <script>
    const profileForm = document.getElementById('profileForm');
    const profileStatus = document.getElementById('profileStatus');
    profileForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      profileStatus.classList.remove('error');
      profileStatus.textContent = '正在保存联系方式...';
      try {
        const response = await fetch(profileForm.action, {
          method: 'POST',
          headers: {'Accept': 'application/json'},
          body: new FormData(profileForm)
        });
        const payload = await response.json().catch(() => null);
        const code = payload ? Number(payload.code) : NaN;
        if (code === 1 || code === 200 || code === 0) {
          profileStatus.textContent = payload && payload.message ? payload.message : '联系方式保存成功。';
          window.setTimeout(() => window.location.reload(), 450);
          return;
        }
        profileStatus.classList.add('error');
        profileStatus.textContent = payload && payload.message ? payload.message : '资料保存失败。';
      } catch (error) {
        profileStatus.classList.add('error');
        profileStatus.textContent = '资料保存失败，请稍后重试。';
      }
    });
  </script>
</body>
</html>
HTML;
    }

    private function notificationsPage(array $merchant, array $payload): string
    {
        $displayName = $this->escape($this->merchantDisplay($merchant));
        $settings = (array)($payload['settings'] ?? []);
        $channels = (array)($payload['channels'] ?? []);
        $lowBalanceThreshold = $this->escape((string)($payload['low_balance_threshold'] ?? '0'));
        $consoleNoticeValue = $this->escape((string)($payload['console_notice'] ?? ''));
        $consoleNotice = $consoleNoticeValue === '' ? 'Not configured' : $consoleNoticeValue;
        $voiceTips = (array)($payload['voice_tips'] ?? []);
        $voiceState = !empty($voiceTips['enabled']) ? 'Enabled' : 'Disabled';
        $voiceEnabled = !empty($voiceTips['enabled']) ? 1 : 0;
        $voiceEnabledSelected = $voiceEnabled === 1 ? ' selected' : '';
        $voiceTemplate = $this->escape((string)($voiceTips['template'] ?? self::DEFAULT_VOICE_TIPS));
        $rowsHtml = '';
        $channelsHtml = '';

        foreach ($settings as $setting) {
            $field = $this->escape((string)($setting['id'] ?? ''));
            $name = $this->escape((string)($setting['name'] ?? 'Notification'));
            $selectedLabel = $this->escape((string)($setting['selected_label'] ?? 'Closed'));
            $selectedAvailable = !empty($setting['selected_available']);
            $statusClass = $selectedAvailable ? 'success' : 'warning';
            $channelChips = '';
            $selectOptions = '';
            $selectedChannel = (string)($setting['selected'] ?? 'close');
            foreach ((array)($setting['channels'] ?? []) as $channel) {
                $channelId = (string)($channel['id'] ?? 'close');
                $label = (string)($channel['label'] ?? '');
                $chipClass = !empty($channel['selected']) ? 'selected' : (!empty($channel['available']) ? 'available' : 'disabled');
                $channelChips .= '<span class="chip ' . $chipClass . '">' . $this->escape($label) . '</span>';
                $disabled = empty($channel['available']) && $channelId !== 'close' ? ' disabled' : '';
                $selected = $channelId === $selectedChannel ? ' selected' : '';
                $optionLabel = $label . ($disabled !== '' ? ' (disabled)' : '');
                $selectOptions .= '<option value="' . $this->escape($channelId) . '"' . $selected . $disabled . '>' . $this->escape($optionLabel) . '</option>';
            }
            $rowsHtml .= <<<HTML
        <tr>
          <td><strong>{$name}</strong></td>
          <td>
            <select name="{$field}">
              {$selectOptions}
            </select>
            <div style="margin-top:8px"><span class="badge {$statusClass}">Current: {$selectedLabel}</span></div>
          </td>
          <td>{$channelChips}</td>
          <td><span class="badge info">Saved with form</span></td>
        </tr>
HTML;
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="4" class="empty">No merchant notification settings were found.</td></tr>';
        }

        foreach ($channels as $channel) {
            $label = $this->escape((string)($channel['label'] ?? ''));
            $state = !empty($channel['available']) ? 'Available' : 'Disabled';
            $class = !empty($channel['available']) ? 'success' : 'warning';
            $channelsHtml .= '<div class="card"><span>' . $label . '</span><strong><span class="badge ' . $class . '">' . $state . '</span></strong></div>';
        }

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Merchant Notifications</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f6f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1180px;margin:0 auto 18px;padding:26px;border-radius:24px;background:linear-gradient(135deg,#0f172a,#854d0e);color:#fff;box-shadow:0 20px 60px rgba(15,23,42,.18)}
    .hero h1{margin:0 0 8px;font-size:30px}.hero p{margin:0;color:#fef3c7;line-height:1.7}
    .stats{max-width:1180px;margin:0 auto 16px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;box-shadow:0 14px 34px rgba(15,23,42,.06)}
    .card span{display:block;color:#64748b;font-size:13px;margin-bottom:7px}.card strong{font-size:18px;word-break:break-all}
    .notice{max-width:1180px;margin:0 auto 16px;padding:15px 16px;border-radius:18px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;line-height:1.7}
    .panel{max-width:1180px;margin:0 auto 16px;background:#fff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    table{width:100%;border-collapse:collapse}th,td{padding:14px 16px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}th{background:#f8fafc;color:#475569;font-size:13px}td{font-size:14px}
    .badge,.chip{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700}.success{background:#dcfce7;color:#166534}.warning{background:#fef3c7;color:#92400e}.info{background:#dbeafe;color:#1e40af}
    .chip{margin:3px;background:#f1f5f9;color:#334155}.chip.selected{background:#dbeafe;color:#1e40af}.chip.disabled{background:#fee2e2;color:#991b1b}.chip.available{background:#dcfce7;color:#166534}
    .empty{text-align:center;color:#64748b;padding:30px}
    .form-shell{padding:22px}
    .form-shell h2{margin:0 0 8px;font-size:22px}
    .form-shell p{margin:0 0 18px;color:#64748b;line-height:1.7}
    .form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
    .field label{display:block;margin:0 0 8px;color:#334155;font-size:14px;font-weight:600}
    .field input,.field select,.field textarea,table select{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:12px;padding:11px 12px;font:inherit;outline:none;background:#fff}
    .field textarea{min-height:110px;resize:vertical}
    .field input:focus,.field select:focus,.field textarea:focus,table select:focus{border-color:#854d0e;box-shadow:0 0 0 4px rgba(133,77,14,.12)}
    .field small{display:block;margin-top:6px;color:#64748b}
    .toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
    button.btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:12px;border:0;cursor:pointer;font:inherit}
    .btn.primary{background:#854d0e;color:#fff}.btn.secondary{background:#e2e8f0;color:#0f172a}
    .status{min-height:22px;margin-top:14px;color:#854d0e;font-size:14px;line-height:1.6}
    .status.error{color:#b91c1c}
    .actions{max-width:1180px;margin:16px auto 0;display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#0f172a;color:#fff;text-decoration:none}.btn.secondary{background:#e2e8f0;color:#0f172a}
    @media (max-width:1000px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.panel{overflow:auto}table{min-width:860px}}
    @media (max-width:760px){.form-grid{grid-template-columns:1fr}}
    @media (max-width:560px){.shell{padding:18px}.stats{grid-template-columns:1fr}.hero{padding:22px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>通知设置</h1>
      <p>{$displayName}，当前已支持保存通知渠道和余额预警设置。此页仅保留通知设置保存与渠道可用性查看，避免展示无效操作。</p>
    </section>
    <section class="notice">当前仅允许为本商户保存设置，并写入 <code>ypay_userbasic</code>。通知渠道是否可用会直接在页面展示，不再保留无实际作用的绑定与测试入口。</section>
    <section class="stats">
{$channelsHtml}
    </section>
    <section class="stats">
      <div class="card"><span>余额预警阈值</span><strong>{$lowBalanceThreshold}</strong></div>
      <div class="card"><span>控制台提示</span><strong>{$consoleNotice}</strong></div>
      <div class="card"><span>语音提醒</span><strong>{$voiceState}</strong></div>
      <div class="card"><span>写入能力</span><strong>已开放保存</strong></div>
    </section>
    <form id="notificationsForm" method="post" action="/My/Notifications">
      <section class="panel">
        <table>
          <thead><tr><th>设置项</th><th>通知渠道</th><th>可选渠道</th><th>操作</th></tr></thead>
          <tbody>
{$rowsHtml}
          </tbody>
        </table>
      </section>
      <section class="panel">
        <div class="form-shell">
          <h2>保存通知设置</h2>
          <p>你可以通过下列表单保存余额预警、控制台提示与语音提醒偏好。系统级被禁用的渠道仍会展示出来用于确认当前可用状态，并在保存时自动归一到 <code>close</code>。</p>
          <div class="form-grid">
            <div class="field">
              <label for="moneyTips">余额预警阈值</label>
              <input id="moneyTips" name="money_tips" type="text" value="{$lowBalanceThreshold}" placeholder="0.00">
              <small>格式要求：非负金额，最多支持 2 位小数。</small>
            </div>
            <div class="field">
              <label for="voiceEnabled">语音提醒</label>
              <select id="voiceEnabled" name="is_voice_tips">
                <option value="0">关闭</option>
                <option value="1"{$voiceEnabledSelected}>开启</option>
              </select>
              <small>语音提醒文案使用 <code>[money]</code> 作为金额占位符。</small>
            </div>
            <div class="field">
              <label for="consoleNotice">控制台提示</label>
              <input id="consoleNotice" name="console_notity" type="text" value="{$consoleNoticeValue}" placeholder="可选的商户控制台提示">
              <small>留空即可清空额外的控制台提示语。</small>
            </div>
          </div>
          <div class="field" style="margin-top:14px">
            <label for="voiceTemplate">语音模板</label>
            <textarea id="voiceTemplate" name="voice_tips" placeholder="尊敬的用户，你本次交易金额为[money]">{$voiceTemplate}</textarea>
          </div>
          <div class="toolbar">
            <button class="btn primary" type="submit">保存通知设置</button>
            <button class="btn secondary" type="reset">重置</button>
          </div>
          <div id="notificationsStatus" class="status" role="status" aria-live="polite"></div>
        </div>
      </section>
    </form>
    <section class="notice">语音模板预览：{$voiceTemplate}</section>
    <nav class="actions">
      <a class="btn secondary" href="/User/Index">商户中心</a>
      <a class="btn secondary" href="/My/userpro">资料维护</a>
      <a class="btn secondary" href="/My/Security">安全中心</a>
      <a class="btn secondary" href="/My/Connections">绑定中心</a>
      <a class="btn secondary" href="/Deal/OrderLog">订单记录</a>
      <a class="btn secondary" href="/Deal/Recharge">充值中心</a>
      <a class="btn secondary" href="/Deal/MoneyLog">资金日志</a>
      <a class="btn secondary" href="/Deal/Vip">会员套餐</a>
      <a class="btn secondary" href="/My/Api">接口信息</a>
      <a class="btn secondary" href="/My/Ticket">工单中心</a>
      <a class="btn secondary" href="/My/is_domain">域名管理</a>
      <a class="btn secondary" href="/My/loginlog">登录日志</a>
      <a class="btn secondary" href="/My/Notifications?format=json">查看 JSON</a>
      <a class="btn" href="/User/Logout">退出登录</a>
    </nav>
  </div>
  <script>
    const notificationsForm = document.getElementById('notificationsForm');
    const notificationsStatus = document.getElementById('notificationsStatus');
    notificationsForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      notificationsStatus.classList.remove('error');
      notificationsStatus.textContent = '正在保存通知设置...';
      try {
        const response = await fetch(notificationsForm.action, {
          method: 'POST',
          headers: {'Accept': 'application/json'},
          body: new FormData(notificationsForm)
        });
        const payload = await response.json().catch(() => null);
        const code = payload ? Number(payload.code) : NaN;
        if (code === 1 || code === 200 || code === 0) {
          notificationsStatus.textContent = payload && payload.message ? payload.message : '通知设置已保存。';
          window.setTimeout(() => window.location.reload(), 450);
          return;
        }
        notificationsStatus.classList.add('error');
        notificationsStatus.textContent = payload && payload.message ? payload.message : '通知设置保存失败。';
      } catch (error) {
        notificationsStatus.classList.add('error');
        notificationsStatus.textContent = '通知设置保存失败，请稍后重试。';
      }
    });
  </script>
</body>
</html>
HTML;
    }

    private function connectionsPage(array $merchant, array $payload): string
    {
        $displayName = $this->escape($this->merchantDisplay($merchant));
        $quickLogins = (array)($payload['quick_logins'] ?? []);
        $contactBindings = (array)($payload['contact_bindings'] ?? []);
        $summary = (array)($payload['summary'] ?? []);
        $quickLoginCount = (int)($summary['quick_login_count'] ?? count($quickLogins));
        $boundQuickLoginCount = (int)($summary['bound_quick_login_count'] ?? 0);
        $contactBindingCount = (int)($summary['contact_binding_count'] ?? count($contactBindings));
        $configuredContactCount = (int)($summary['configured_contact_count'] ?? 0);
        $quickRowsHtml = '';
        $contactRowsHtml = '';
        $wxpusherMasked = '未配置';
        $telegramMasked = '未配置';
        $wxpusherConfigured = false;
        $telegramConfigured = false;

        foreach ($quickLogins as $binding) {
            $id = $this->escape((string)($binding['id'] ?? ''));
            $label = $this->escape((string)($binding['label'] ?? 'Quick Login'));
            $configName = $this->escape((string)($binding['config_name'] ?? 'Unconfigured'));
            $configType = $this->escape((string)($binding['config_type'] ?? ''));
            $statusLabel = $this->escape((string)($binding['status_label'] ?? 'Disabled'));
            $statusType = $this->escape((string)($binding['status_type'] ?? 'warning'));
            $boundLabel = $this->escape((string)($binding['bound_label'] ?? 'Not bound'));
            $boundType = $this->escape((string)($binding['bound_type'] ?? 'info'));
            $identifier = $this->escape((string)($binding['identifier_masked'] ?? 'Not bound'));
            $callbackEntry = $this->escape((string)($binding['callback_entry'] ?? ''));
            $credentialState = !empty($binding['credential_ready']) ? 'Credentials ready' : 'Credentials incomplete';
            $credentialType = !empty($binding['credential_ready']) ? 'success' : 'warning';
            $actionHtml = !empty($binding['unbind_allowed'])
                ? '<button type="button" class="mini-btn connection-unbind" data-type="' . $id . '" data-label="' . $label . '">Unbind</button>'
                : '<button type="button" class="mini-btn disabled" disabled>Nothing to unbind</button>';
            $quickRowsHtml .= <<<HTML
        <tr>
          <td><strong>{$label}</strong><br><small>{$configName}</small></td>
          <td><span class="badge {$statusType}">{$statusLabel}</span><br><small>{$configType}</small></td>
          <td><span class="badge {$boundType}">{$boundLabel}</span><br><small>{$identifier}</small></td>
          <td><span class="badge {$credentialType}">{$credentialState}</span><br><small><code>{$callbackEntry}</code></small></td>
          <td>{$actionHtml}</td>
        </tr>
HTML;
        }

        if ($quickRowsHtml === '') {
            $quickRowsHtml = '<tr><td colspan="5" class="empty">No quick-login bindings are configured for this merchant.</td></tr>';
        }

        foreach ($contactBindings as $binding) {
            $id = (string)($binding['id'] ?? '');
            $label = $this->escape((string)($binding['label'] ?? 'Channel'));
            $statusLabel = $this->escape((string)($binding['status_label'] ?? 'Disabled'));
            $statusType = $this->escape((string)($binding['status_type'] ?? 'warning'));
            $configuredLabel = $this->escape((string)($binding['configured_label'] ?? 'Not configured'));
            $configuredType = $this->escape((string)($binding['configured_type'] ?? 'info'));
            $valueDisplay = $this->escape((string)($binding['value_display'] ?? 'Not configured'));
            if ($id === 'wxpusher_uid') {
                $wxpusherMasked = $valueDisplay;
                $wxpusherConfigured = !empty($binding['configured']);
            }
            if ($id === 'tg_chat_id') {
                $telegramMasked = $valueDisplay;
                $telegramConfigured = !empty($binding['configured']);
            }
            $actionHtml = match ($id) {
                'wxpusher_uid' => '<span class="inline-note">Use the direct-save panel below</span>',
                'tg_chat_id' => '<span class="inline-note">Use the direct-save panel below</span>',
                default => '<button type="button" class="mini-btn disabled" disabled>Verification flow pending</button>',
            };
            $contactRowsHtml .= <<<HTML
        <tr>
          <td><strong>{$label}</strong></td>
          <td><span class="badge {$statusType}">{$statusLabel}</span></td>
          <td><span class="badge {$configuredType}">{$configuredLabel}</span></td>
          <td>{$valueDisplay}</td>
          <td>{$actionHtml}</td>
        </tr>
HTML;
        }

        if ($contactRowsHtml === '') {
            $contactRowsHtml = '<tr><td colspan="5" class="empty">No contact bindings are configured for this merchant.</td></tr>';
        }

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Merchant Connections</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f6f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1180px;margin:0 auto 18px;padding:26px;border-radius:24px;background:linear-gradient(135deg,#0f172a,#0369a1);color:#fff;box-shadow:0 20px 60px rgba(15,23,42,.18)}
    .hero h1{margin:0 0 8px;font-size:30px}.hero p{margin:0;color:#dbeafe;line-height:1.7}
    .stats{max-width:1180px;margin:0 auto 16px;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;box-shadow:0 14px 34px rgba(15,23,42,.06)}
    .card span{display:block;color:#64748b;font-size:13px;margin-bottom:7px}.card strong{font-size:22px}
    .notice{max-width:1180px;margin:0 auto 16px;padding:15px 16px;border-radius:18px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;line-height:1.7}
    .grid{max-width:1180px;margin:0 auto 16px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .panel{max-width:1180px;margin:0 auto 16px;background:#fff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    .panel h2{margin:0;padding:16px 18px;border-bottom:1px solid #e2e8f0;font-size:18px}
    table{width:100%;border-collapse:collapse}th,td{padding:14px 16px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}th{background:#f8fafc;color:#475569;font-size:13px}td{font-size:14px}
    small{color:#64748b;word-break:break-all}code{display:inline-block;padding:3px 6px;border-radius:8px;background:#f1f5f9;color:#0f172a;word-break:break-all}
    .badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700}.success{background:#dcfce7;color:#166534}.warning{background:#fef3c7;color:#92400e}.info{background:#dbeafe;color:#1e40af}
    .form-panel{max-width:none;margin:0;background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:22px;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    .form-panel h2{margin:0 0 8px;font-size:22px}.form-panel p{margin:0 0 18px;color:#64748b;line-height:1.7}
    .field label{display:block;margin:0 0 8px;color:#334155;font-size:14px;font-weight:600}
    .field input{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:12px;padding:11px 12px;font:inherit;outline:none;background:#fff}
    .field input:focus{border-color:#0369a1;box-shadow:0 0 0 4px rgba(3,105,161,.12)}
    .toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
    button{padding:8px 10px;border:0;border-radius:10px;background:#e2e8f0;color:#0f172a;font-weight:700;cursor:pointer}
    button.primary{background:#0369a1;color:#fff}
    button.danger{background:#fee2e2;color:#991b1b}
    button.disabled{cursor:not-allowed;color:#64748b}
    .inline-note{color:#64748b;font-size:13px}
    .status{min-height:22px;margin-top:12px;color:#0369a1;font-size:14px;line-height:1.6}
    .status.error{color:#b91c1c}
    .empty{text-align:center;color:#64748b;padding:30px}
    .actions{max-width:1180px;margin:16px auto 0;display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#0f172a;color:#fff;text-decoration:none}.btn.secondary{background:#e2e8f0;color:#0f172a}
    @media (max-width:1000px){.stats,.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.panel{overflow:auto}table{min-width:940px}}
    @media (max-width:560px){.shell{padding:18px}.stats,.grid{grid-template-columns:1fr}.hero{padding:22px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>绑定中心</h1>
      <p>{$displayName}，当前页面已支持解除已存储的快捷登录关系，并可直接维护邮箱/手机号验证码绑定、WxPusher / Telegram 联系方式；新的 OAuth 绑定入口已从商户中心移除。</p>
    </section>
    <section class="notice">QQ、微信快捷登录在这里仅展示已接入状态与解绑能力；邮箱、手机号、WxPusher、Telegram 的写操作都只作用于当前 <code>front_token</code> 商户。</section>
    <section class="stats">
      <div class="card"><span>快捷登录来源</span><strong>{$quickLoginCount}</strong></div>
      <div class="card"><span>已绑定快捷登录</span><strong>{$boundQuickLoginCount}</strong></div>
      <div class="card"><span>通知渠道数</span><strong>{$contactBindingCount}</strong></div>
      <div class="card"><span>已配置联系方式</span><strong>{$configuredContactCount}</strong></div>
      <div class="card"><span>可写状态</span><strong>部分开放</strong></div>
    </section>
    <section class="grid">
      <section class="form-panel">
        <h2>微信推送标识</h2>
        <p>当前脱敏值：<strong>{$wxpusherMasked}</strong>。你可以直接粘贴新的微信推送标识，也可以清空当前商户已保存的值。</p>
        <form id="wxpusherForm">
          <div class="field">
            <label for="wxpusherUidInput">微信推送标识</label>
            <input id="wxpusherUidInput" name="wxpusher_uid" placeholder="UID_xxxxxxxx">
          </div>
          <div class="toolbar">
            <button type="submit" class="primary">保存标识</button>
            <button type="button" id="wxpusherStatusBtn">查询状态</button>
            <button type="button" id="wxpusherUnbindBtn" class="danger">清空标识</button>
          </div>
          <div id="wxpusherStatus" class="status"></div>
        </form>
      </section>
      <section class="form-panel">
        <h2>电报会话标识</h2>
        <p>当前脱敏值：<strong>{$telegramMasked}</strong>。在这里保存后，会直接替换当前商户的电报会话标识。</p>
        <form id="telegramForm">
            <div class="field">
              <label for="telegramChatIdInput">电报会话标识</label>
              <input id="telegramChatIdInput" name="tg_chat_id" placeholder="请输入电报会话标识">
            </div>
          <div class="toolbar">
            <button type="submit" class="primary">保存标识</button>
            <button type="button" id="telegramUnbindBtn" class="danger">清空标识</button>
          </div>
          <div id="telegramStatus" class="status"></div>
        </form>
      </section>
    </section>
    <section class="panel">
      <h2>快捷登录绑定情况</h2>
      <table>
        <thead><tr><th>来源</th><th>可用状态</th><th>绑定状态</th><th>回调 / 凭证</th><th>操作</th></tr></thead>
        <tbody>
{$quickRowsHtml}
        </tbody>
      </table>
    </section>
    <section class="panel">
      <h2>通知渠道绑定</h2>
      <table>
        <thead><tr><th>渠道</th><th>可用状态</th><th>是否已配置</th><th>当前值</th><th>操作</th></tr></thead>
        <tbody>
{$contactRowsHtml}
        </tbody>
      </table>
    </section>
    <nav class="actions">
      <a class="btn secondary" href="/User/Index">商户中心</a>
      <a class="btn secondary" href="/My/userpro">资料维护</a>
      <a class="btn secondary" href="/My/Security">安全中心</a>
      <a class="btn secondary" href="/My/Notifications">通知设置</a>
      <a class="btn secondary" href="/Deal/OrderLog">订单记录</a>
      <a class="btn secondary" href="/Deal/Recharge">充值记录</a>
      <a class="btn secondary" href="/Deal/MoneyLog">资金日志</a>
      <a class="btn secondary" href="/Deal/Vip">会员套餐</a>
      <a class="btn secondary" href="/My/Api">接口信息</a>
      <a class="btn secondary" href="/My/Ticket">工单中心</a>
      <a class="btn secondary" href="/My/is_domain">域名管理</a>
      <a class="btn secondary" href="/My/loginlog">登录日志</a>
      <a class="btn secondary" href="/My/Connections?format=json">查看 JSON</a>
      <a class="btn" href="/User/Logout">退出登录</a>
    </nav>
  </div>
  <script>
    const connectionStatus = (element, message, isError = false) => {
      element.textContent = message;
      element.classList.toggle('error', !!isError);
    };

    const postConnectionForm = async (url, payload) => {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
          'Accept': 'application/json'
        },
        body: new URLSearchParams(payload).toString()
      });
      return response.json();
    };

    document.querySelectorAll('.connection-unbind').forEach((button) => {
      button.addEventListener('click', async () => {
        if (!confirm('Clear the stored ' + button.dataset.label + ' binding for the current merchant?')) {
          return;
        }
        try {
          const payload = await postConnectionForm('/My/Unbinding', {type: button.dataset.type});
          if (payload && (payload.code === 1 || payload.code === 200)) {
            window.location.reload();
            return;
          }
          alert(payload && (payload.message || payload.msg) ? (payload.message || payload.msg) : 'Unbind failed.');
        } catch (error) {
          alert('Unbind failed. Please try again.');
        }
      });
    });

    const wxpusherStatus = document.getElementById('wxpusherStatus');
    const telegramStatus = document.getElementById('telegramStatus');
    const wxpusherUnbindBtn = document.getElementById('wxpusherUnbindBtn');
    const telegramUnbindBtn = document.getElementById('telegramUnbindBtn');

    document.getElementById('wxpusherForm').addEventListener('submit', async (event) => {
      event.preventDefault();
      connectionStatus(wxpusherStatus, '正在保存微信推送标识...');
      try {
        const payload = await postConnectionForm('/My/savaWxPuserUID', {
          wxpusher_uid: document.getElementById('wxpusherUidInput').value
        });
        if (payload && (payload.code === 1 || payload.code === 200)) {
          connectionStatus(wxpusherStatus, payload.message || payload.msg || '微信推送标识已保存。');
          setTimeout(() => window.location.reload(), 500);
          return;
        }
        connectionStatus(wxpusherStatus, payload && (payload.message || payload.msg) ? (payload.message || payload.msg) : '微信推送标识保存失败。', true);
      } catch (error) {
        connectionStatus(wxpusherStatus, '微信推送标识保存失败，请稍后重试。', true);
      }
    });

    document.getElementById('wxpusherStatusBtn').addEventListener('click', async () => {
      connectionStatus(wxpusherStatus, '正在检查当前微信推送状态...');
      try {
        const response = await fetch('/My/getWxPusherUID?operate=bind', {headers: {'Accept': 'application/json'}});
        const payload = await response.json();
        connectionStatus(
          wxpusherStatus,
          payload && payload.code === 1
            ? '当前商户已保存微信推送标识。'
            : '当前商户尚未保存微信推送标识。',
          false
        );
      } catch (error) {
        connectionStatus(wxpusherStatus, '状态检查失败，请稍后重试。', true);
      }
    });

    wxpusherUnbindBtn.addEventListener('click', async () => {
      if (!confirm('确认清空当前商户已保存的微信推送标识吗？')) {
        return;
      }
      connectionStatus(wxpusherStatus, '正在清空微信推送标识...');
      try {
        const payload = await postConnectionForm('/My/Unbinding', {type: 'wxpusher_uid'});
        if (payload && (payload.code === 1 || payload.code === 200)) {
          connectionStatus(wxpusherStatus, '微信推送标识已清空。');
          setTimeout(() => window.location.reload(), 500);
          return;
        }
        connectionStatus(wxpusherStatus, payload && (payload.message || payload.msg) ? (payload.message || payload.msg) : '清空失败。', true);
      } catch (error) {
        connectionStatus(wxpusherStatus, '清空失败，请稍后重试。', true);
      }
    });

    document.getElementById('telegramForm').addEventListener('submit', async (event) => {
      event.preventDefault();
      connectionStatus(telegramStatus, '正在保存电报会话标识...');
      try {
        const payload = await postConnectionForm('/My/saveTgChatId', {
          tg_chat_id: document.getElementById('telegramChatIdInput').value
        });
        if (payload && (payload.code === 1 || payload.code === 200)) {
          connectionStatus(telegramStatus, payload.message || payload.msg || '电报会话标识已保存。');
          setTimeout(() => window.location.reload(), 500);
          return;
        }
        connectionStatus(telegramStatus, payload && (payload.message || payload.msg) ? (payload.message || payload.msg) : '电报会话标识保存失败。', true);
      } catch (error) {
        connectionStatus(telegramStatus, '电报会话标识保存失败，请稍后重试。', true);
      }
    });

    telegramUnbindBtn.addEventListener('click', async () => {
      if (!confirm('确认清空当前商户已保存的电报会话标识吗？')) {
        return;
      }
      connectionStatus(telegramStatus, '正在清空电报会话标识...');
      try {
        const payload = await postConnectionForm('/My/Unbinding', {type: 'tg_chat_id'});
        if (payload && (payload.code === 1 || payload.code === 200)) {
          connectionStatus(telegramStatus, '电报会话标识已清空。');
          setTimeout(() => window.location.reload(), 500);
          return;
        }
        connectionStatus(telegramStatus, payload && (payload.message || payload.msg) ? (payload.message || payload.msg) : '清空失败。', true);
      } catch (error) {
        connectionStatus(telegramStatus, '清空失败，请稍后重试。', true);
      }
    });
  </script>
</body>
</html>
HTML;
    }

    private function securityPage(array $merchant, array $payload): string
    {
        $displayName = $this->escape($this->merchantDisplay($merchant));
        $securityCenter = (array)($payload['security_center'] ?? []);
        $password = (array)($payload['password'] ?? []);
        $googleAuth = (array)($payload['google_auth'] ?? []);
        $realName = (array)($payload['real_name'] ?? []);
        $accountCancellation = (array)($payload['account_cancellation'] ?? []);
        $recentLogs = (array)($payload['recent_logs'] ?? []);
        $logSummary = (array)($payload['log_summary'] ?? []);
        $providerName = $this->escape((string)($securityCenter['provider_name'] ?? '谷歌验证器'));
        $securityEnabled = !empty($securityCenter['enabled']) ? '已启用' : '未开启';
        $securityForce = !empty($securityCenter['force_bind']) ? '必需' : '可选';
        $loginVerification = !empty($securityCenter['login_verification_required']) ? '已启用' : '未开启';
        $bindTips = $this->escape((string)($securityCenter['bind_tips'] ?? '谷歌验证已接入当前商户中心，可在当前安全页查看状态并按提示完成操作。'));
        $popupTitle = $this->escape((string)($securityCenter['popup_title'] ?? '安全能力说明'));
        $popupContent = $this->escape((string)($securityCenter['popup_content'] ?? '密码修改、谷歌验证、实名认证和账号注销检查均已接入当前商户后台；是否允许提交由系统开关与当前账号状态共同决定。'));
        $googleStatusLabel = $this->escape((string)($googleAuth['status_label'] ?? '未绑定'));
        $googleStatusType = $this->escape((string)($googleAuth['status_type'] ?? 'warning'));
        $googleSecretMasked = $this->escape((string)($googleAuth['secret_masked'] ?? '未绑定'));
        $googleWriteMessage = $this->escape((string)($googleAuth['write_message'] ?? '谷歌验证已接入当前商户中心，可按需绑定或解绑。'));
        $realNameStatusLabel = $this->escape((string)($realName['status_label'] ?? '未开启'));
        $realNameStatusType = $this->escape((string)($realName['status_type'] ?? 'info'));
        $realNameIdCard = $this->escape((string)($realName['id_card_masked'] ?? '暂未提供'));
        $realNameWriteMessage = $this->escape((string)($realName['write_message'] ?? '实名认证状态会根据系统开关与当前认证结果动态更新。'));
        $accountCancellationStatusLabel = !empty($accountCancellation['feature_enabled'])
            ? (!empty($accountCancellation['can_submit']) ? '可提交' : '待处理')
            : '未开启';
        $accountCancellationStatusType = !empty($accountCancellation['feature_enabled'])
            ? (!empty($accountCancellation['can_submit']) ? 'success' : 'warning')
            : 'info';
        $accountCancellationMessage = $this->escape((string)($accountCancellation['write_message'] ?? '账号注销前会先检查余额、未完成订单和下级商户等拦截项。'));
        $passwordAllowed = !empty($password['update_allowed']);
        $passwordHint = $this->escape((string)($password['write_message'] ?? '当前页面支持直接修改登录密码。'));
        $totalLogs = (int)($logSummary['total_count'] ?? 0);
        $todayLogs = (int)($logSummary['today_count'] ?? 0);
        $ipCount = (int)($logSummary['ip_count'] ?? 0);
        $lastLogTime = $this->escape((string)($logSummary['last_log_time'] ?? '--'));
        $logRowsHtml = '';

        foreach ($recentLogs as $record) {
            $createdAt = $this->escape((string)($record['create_time'] ?? '--'));
            $path = $this->escape((string)($record['path'] ?? '/'));
            $ip = $this->escape((string)($record['ip'] ?? ''));
            $typeLabel = $this->escape((string)($record['type_label'] ?? '行为日志'));
            $payloadPreview = $this->escape((string)($record['payload_preview'] ?? '无可展示的请求载荷'));
            $logRowsHtml .= <<<HTML
        <tr>
          <td>{$createdAt}</td>
          <td><strong>{$path}</strong><br><small>{$ip}</small></td>
          <td><span class="badge info">{$typeLabel}</span></td>
          <td>{$payloadPreview}</td>
        </tr>
HTML;
        }

        if ($logRowsHtml === '') {
            $logRowsHtml = '<tr><td colspan="4" class="empty">当前没有可展示的最近登录日志。</td></tr>';
        }

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>安全中心</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f6f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1180px;margin:0 auto 18px;padding:26px;border-radius:24px;background:linear-gradient(135deg,#111827,#1d4ed8);color:#fff;box-shadow:0 20px 60px rgba(15,23,42,.18)}
    .hero h1{margin:0 0 8px;font-size:30px}.hero p{margin:0;color:#dbeafe;line-height:1.7}
    .stats{max-width:1180px;margin:0 auto 16px;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;box-shadow:0 14px 34px rgba(15,23,42,.06)}
    .card span{display:block;color:#64748b;font-size:13px;margin-bottom:7px}.card strong{font-size:22px;word-break:break-all}
    .notice{max-width:1180px;margin:0 auto 16px;padding:15px 16px;border-radius:18px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;line-height:1.7}
    .grid{max-width:1180px;margin:0 auto 16px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .panel{background:#fff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    .panel h2{margin:0;padding:16px 18px;border-bottom:1px solid #e2e8f0;font-size:18px}
    .panel-body{padding:18px}
    table{width:100%;border-collapse:collapse}th,td{padding:14px 16px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}th{background:#f8fafc;color:#475569;font-size:13px}td{font-size:14px}
    small{color:#64748b;word-break:break-all}
    .badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700}.success{background:#dcfce7;color:#166534}.warning{background:#fef3c7;color:#92400e}.info{background:#dbeafe;color:#1e40af}
    .field{margin-bottom:14px}.field label{display:block;margin:0 0 8px;color:#334155;font-size:14px;font-weight:600}
    .field input{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:12px;padding:11px 12px;font:inherit;outline:none;background:#fff}
    .field input:focus{border-color:#1d4ed8;box-shadow:0 0 0 4px rgba(29,78,216,.12)}
    .toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
    button{border:0;border-radius:12px;padding:10px 14px;font:inherit;font-weight:700;cursor:pointer;background:#e2e8f0;color:#0f172a}
    button.primary{background:#1d4ed8;color:#fff}
    .status{min-height:22px;margin-top:12px;color:#1d4ed8;font-size:14px;line-height:1.6}
    .status.error{color:#b91c1c}
    code.secret{display:block;margin-top:10px;padding:10px 12px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;color:#0f172a;word-break:break-all}
    .empty{text-align:center;color:#64748b;padding:30px}.actions{max-width:1180px;margin:16px auto 0;display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#0f172a;color:#fff;text-decoration:none}.btn.secondary{background:#e2e8f0;color:#0f172a}
    @media (max-width:1000px){.stats,.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.panel{overflow:auto}table{min-width:760px}}
    @media (max-width:560px){.shell{padding:18px}.stats,.grid{grid-template-columns:1fr}.hero{padding:22px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>安全中心</h1>
      <p>{$displayName}，当前页面已支持修改商户密码、重置密钥、谷歌验证绑定/解绑、实名认证发起和账号注销条件检查；具体动作是否允许提交，会根据系统开关与当前账户状态动态判断。</p>
    </section>
    <section class="notice">{$passwordHint} {$googleWriteMessage}</section>
    <section class="stats">
      <div class="card"><span>安全中心</span><strong>{$securityEnabled}</strong></div>
      <div class="card"><span>强制绑定</span><strong>{$securityForce}</strong></div>
      <div class="card"><span>登录校验</span><strong>{$loginVerification}</strong></div>
      <div class="card"><span>提供方</span><strong>{$providerName}</strong></div>
      <div class="card"><span>可写状态</span><strong>部分开放</strong></div>
    </section>
    <section class="notice"><strong>{$popupTitle}</strong><br>{$popupContent}</section>
    <section class="grid">
      <section class="panel">
        <h2>谷歌验证</h2>
        <table>
          <tbody>
            <tr><th>状态</th><td><span class="badge {$googleStatusType}">{$googleStatusLabel}</span></td></tr>
            <tr><th>密钥</th><td>{$googleSecretMasked}</td></tr>
            <tr><th>验证页面</th><td><a href="/My/GoogleAuth">/My/GoogleAuth</a></td></tr>
            <tr><th>绑定说明</th><td>{$bindTips}</td></tr>
            <tr><th>操作提示</th><td>{$googleWriteMessage}</td></tr>
          </tbody>
        </table>
      </section>
      <section class="panel">
        <h2>实名认证状态</h2>
        <table>
          <tbody>
            <tr><th>认证结果</th><td><span class="badge {$realNameStatusType}">{$realNameStatusLabel}</span></td></tr>
            <tr><th>身份证号</th><td>{$realNameIdCard}</td></tr>
            <tr><th>认证说明</th><td>{$realNameWriteMessage}</td></tr>
            <tr><th>账号注销</th><td><span class="badge {$accountCancellationStatusType}">{$accountCancellationStatusLabel}</span><br><small>{$accountCancellationMessage}</small></td></tr>
          </tbody>
        </table>
      </section>
      <section class="panel">
        <h2>修改密码</h2>
        <div class="panel-body">
          <p>{$passwordHint}</p>
          <form id="passwordForm">
            <div class="field">
              <label for="newPasswordInput">新密码</label>
              <input id="newPasswordInput" name="newpwd" type="password" minlength="6" placeholder="请输入新密码" required>
            </div>
            <div class="field">
              <label for="confirmPasswordInput">确认密码</label>
              <input id="confirmPasswordInput" name="renewpwd" type="password" minlength="6" placeholder="请再次输入新密码" required>
            </div>
            <div class="toolbar">
              <button type="submit" class="primary">保存密码</button>
            </div>
            <div id="passwordStatus" class="status"></div>
          </form>
        </div>
      </section>
      <section class="panel">
        <h2>商户密钥重置</h2>
        <div class="panel-body">
          <p>签名密钥与通讯密钥已支持直接重置。页面加载时只展示脱敏值，每次重置仅返回一次新密钥，方便你安全轮换。</p>
          <div class="toolbar">
            <button type="button" id="securityResetSignKey" class="primary">重置签名密钥</button>
            <button type="button" id="securityResetAppkey">重置通讯密钥</button>
            <a class="btn secondary" href="/My/Api">打开接口页面</a>
          </div>
          <div id="securityKeyStatus" class="status"></div>
          <code id="securityKeySecret" class="secret" hidden></code>
        </div>
      </section>
    </section>
    <section class="stats">
      <div class="card"><span>日志总数</span><strong>{$totalLogs}</strong></div>
      <div class="card"><span>今日日志</span><strong>{$todayLogs}</strong></div>
      <div class="card"><span>IP 数量</span><strong>{$ipCount}</strong></div>
      <div class="card"><span>最近日志</span><strong>{$lastLogTime}</strong></div>
      <div class="card"><span>账号注销</span><strong>{$accountCancellationStatusLabel}</strong></div>
    </section>
    <section class="panel" style="max-width:1180px;margin:0 auto;">
      <h2>最近登录日志</h2>
      <table>
        <thead><tr><th>时间</th><th>路径 / IP</th><th>类型</th><th>载荷</th></tr></thead>
        <tbody>
{$logRowsHtml}
        </tbody>
      </table>
    </section>
    <nav class="actions">
      <a class="btn secondary" href="/User/Index">商户中心</a>
      <a class="btn secondary" href="/My/userpro">资料维护</a>
      <a class="btn secondary" href="/My/real_name">实名认证</a>
      <a class="btn secondary" href="/My/Notifications">通知设置</a>
      <a class="btn secondary" href="/My/Connections">绑定中心</a>
      <a class="btn secondary" href="/My/GoogleAuth">谷歌验证</a>
      <a class="btn secondary" href="/My/aff">推广返佣</a>
      <a class="btn secondary" href="/My/loginlog">登录日志</a>
      <a class="btn secondary" href="/My/Security?format=json">查看 JSON</a>
      <a class="btn" href="/User/Logout">退出登录</a>
    </nav>
  </div>
  <script>
    const passwordStatus = document.getElementById('passwordStatus');
    const securityKeyStatus = document.getElementById('securityKeyStatus');
    const securityKeySecret = document.getElementById('securityKeySecret');

    const setStatus = (element, message, isError = false) => {
      element.textContent = message;
      element.classList.toggle('error', !!isError);
    };

    const postSecurityForm = async (url, payload) => {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
          'Accept': 'application/json'
        },
        body: new URLSearchParams(payload).toString()
      });
      return response.json();
    };

    document.getElementById('passwordForm').addEventListener('submit', async (event) => {
      event.preventDefault();
      setStatus(passwordStatus, '正在保存新密码...');
      try {
        const payload = await postSecurityForm('/My/UpdatePwd', {
          newpwd: document.getElementById('newPasswordInput').value,
          renewpwd: document.getElementById('confirmPasswordInput').value
        });
        if (payload && payload.code === 200) {
          setStatus(passwordStatus, payload.message || payload.msg || '密码修改成功，正在跳转到登录页...');
          setTimeout(() => {
            window.location.replace('/User/Login');
          }, 700);
          return;
        }
        setStatus(passwordStatus, payload && (payload.message || payload.msg) ? (payload.message || payload.msg) : '密码保存失败。', true);
      } catch (error) {
        setStatus(passwordStatus, '密码保存失败，请稍后重试。', true);
      }
    });

    const resetKey = async (url, label) => {
      setStatus(securityKeyStatus, '正在重置' + label + '...');
      securityKeySecret.hidden = true;
      try {
        const payload = await postSecurityForm(url, {});
        if (payload && payload.code === 1 && payload.key) {
          setStatus(securityKeyStatus, label + '重置成功，请立即保存新的密钥。');
          securityKeySecret.hidden = false;
          securityKeySecret.textContent = payload.key;
          return;
        }
        setStatus(securityKeyStatus, payload && (payload.message || payload.msg) ? (payload.message || payload.msg) : label + '重置失败。', true);
      } catch (error) {
        setStatus(securityKeyStatus, label + '重置失败，请稍后重试。', true);
      }
    };

    document.getElementById('securityResetSignKey').addEventListener('click', () => {
      if (!confirm('确认立即生成新的商户签名密钥吗？')) {
        return;
      }
      resetKey('/My/GeneratingKey', '签名密钥');
    });

    document.getElementById('securityResetAppkey').addEventListener('click', () => {
      if (!confirm('确认立即生成新的商户通讯密钥吗？')) {
        return;
      }
      resetKey('/My/goAPPKey', '通讯密钥');
    });
  </script>
</body>
</html>
HTML;
    }

    private function googleAuthPage(array $merchant, array $payload): string
    {
        $displayName = $this->escape($this->merchantDisplay($merchant));
        $securityCenter = (array)($payload['security_center'] ?? []);
        $googleAuth = (array)($payload['google_auth'] ?? []);
        $providerName = $this->escape((string)($securityCenter['provider_name'] ?? '谷歌验证器'));
        $bindTips = $this->escape((string)($securityCenter['bind_tips'] ?? '如需完成谷歌验证校验或绑定，请在当前安全页中处理。'));
        $popupTitle = $this->escape((string)($securityCenter['popup_title'] ?? '安全验证说明'));
        $popupContent = $this->escape((string)($securityCenter['popup_content'] ?? '当前商户安全功能暂未开放完整操作。'));
        $statusLabel = $this->escape((string)($googleAuth['status_label'] ?? '未绑定'));
        $statusType = $this->escape((string)($googleAuth['status_type'] ?? 'warning'));
        $secretMasked = $this->escape((string)($googleAuth['secret_masked'] ?? '未绑定'));

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>谷歌验证</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:radial-gradient(circle at top left,#dbeafe,transparent 32%),linear-gradient(135deg,#f8fafc,#e2e8f0);color:#172033}
    .shell{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .card{width:min(760px,100%);background:rgba(255,255,255,.95);border:1px solid #dbeafe;border-radius:26px;padding:30px;box-shadow:0 24px 70px rgba(15,23,42,.14)}
    h1{margin:0 0 10px;font-size:30px}p{margin:0 0 18px;color:#475569;line-height:1.8}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:20px 0}
    .panel{background:#f8fafc;border:1px solid #e2e8f0;border-radius:18px;padding:16px}
    .panel span{display:block;color:#64748b;font-size:13px;margin-bottom:7px}.panel strong{font-size:20px;word-break:break-all}
    .badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700}.success{background:#dcfce7;color:#166534}.warning{background:#fef3c7;color:#92400e}
    .notice{padding:14px 16px;border-radius:16px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;line-height:1.7}
    .actions{margin-top:18px;display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#0f172a;color:#fff;text-decoration:none}.btn.secondary{background:#e2e8f0;color:#0f172a}
    @media (max-width:560px){.grid{grid-template-columns:1fr}.card{padding:22px}}
  </style>
</head>
<body>
  <div class="shell">
    <main class="card">
      <h1>谷歌验证</h1>
      <p>{$displayName}，当前页面保留谷歌验证信息展示，暂不开放验证、绑定、解绑与二维码开通。</p>
      <div class="notice"><strong>{$popupTitle}</strong><br>{$popupContent}</div>
      <section class="grid">
        <article class="panel">
          <span>提供方</span>
          <strong>{$providerName}</strong>
        </article>
        <article class="panel">
          <span>状态</span>
          <strong><span class="badge {$statusType}">{$statusLabel}</span></strong>
        </article>
        <article class="panel">
          <span>脱敏密钥</span>
          <strong>{$secretMasked}</strong>
        </article>
        <article class="panel">
          <span>当前模式</span>
          <strong>信息查看</strong>
        </article>
      </section>
      <div class="notice">绑定提示：{$bindTips}</div>
      <nav class="actions">
        <a class="btn secondary" href="/My/Security">安全中心</a>
        <a class="btn secondary" href="/My/real_name">实名认证</a>
        <a class="btn secondary" href="/User/Index">商户中心</a>
        <a class="btn secondary" href="/My/aff">推广返佣</a>
        <a class="btn secondary" href="/My/GoogleAuth?format=json">查看 JSON</a>
        <a class="btn" href="/User/Logout">退出登录</a>
      </nav>
    </main>
  </div>
</body>
</html>
HTML;
    }

    private function affiliatePage(array $merchant, array $payload): string
    {
        $displayName = $this->escape($this->merchantDisplay($merchant));
        $summary = (array)($payload['summary'] ?? []);
        $records = (array)($payload['records'] ?? []);
        $inviteCount = (int)($summary['invite_count'] ?? 0);
        $verifiedCount = (int)($summary['verified_invite_count'] ?? 0);
        $vipCount = (int)($summary['vip_invite_count'] ?? 0);
        $totalRebate = $this->escape((string)($summary['total_rebate_display'] ?? '0.00'));
        $todayRebate = $this->escape((string)($summary['today_rebate_display'] ?? '0.00'));
        $rebateType = $this->escape((string)($summary['rebate_type_label'] ?? '充值返佣'));
        $percentage = $this->escape((string)($summary['percentage_display'] ?? '0%'));
        $inviteUrl = $this->escape((string)($summary['invite_url'] ?? ''));
        $parentAffiliate = $this->escape((string)($summary['parent_affiliate_label'] ?? '暂无上级商户'));
        $lastInviteTime = $this->escape((string)($summary['last_invite_time'] ?? '--'));
        $rowsHtml = '';

        foreach ($records as $record) {
            $id = (int)($record['id'] ?? 0);
            $username = $this->escape((string)($record['username'] ?? ''));
            $display = $this->escape((string)($record['display_name'] ?? ('商户 #' . $id)));
            $vipLabel = $this->escape((string)($record['vip_label'] ?? '普通商户'));
            $balance = $this->escape((string)($record['balance_display'] ?? '0.00'));
            $createdAt = $this->escape((string)($record['create_time'] ?? '--'));
            $verifiedLabel = $this->escape((string)($record['verified_label'] ?? '未实名'));
            $verifiedType = $this->escape((string)($record['verified_type'] ?? 'warning'));
            $emailMasked = $this->escape((string)($record['email_masked'] ?? '未配置'));
            $mobileMasked = $this->escape((string)($record['mobile_masked'] ?? '未配置'));
            $rowsHtml .= <<<HTML
        <tr>
          <td>#{$id}<br><small>{$createdAt}</small></td>
          <td><strong>{$display}</strong><br><small>{$username}</small></td>
          <td><span class="badge {$verifiedType}">{$verifiedLabel}</span></td>
          <td>{$vipLabel}<br><small>余额 {$balance}</small></td>
          <td>{$emailMasked}<br><small>{$mobileMasked}</small></td>
        </tr>
HTML;
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="5" class="empty">当前筛选条件下暂无邀请商户记录。</td></tr>';
        }

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>推广返佣</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f6f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1180px;margin:0 auto 18px;padding:26px;border-radius:24px;background:linear-gradient(135deg,#0f172a,#0f766e);color:#fff;box-shadow:0 20px 60px rgba(15,23,42,.18)}
    .hero h1{margin:0 0 8px;font-size:30px}.hero p{margin:0;color:#ccfbf1;line-height:1.7}
    .stats{max-width:1180px;margin:0 auto 16px;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;box-shadow:0 14px 34px rgba(15,23,42,.06)}
    .card span{display:block;color:#64748b;font-size:13px;margin-bottom:7px}.card strong{font-size:22px;word-break:break-all}
    .notice,.meta{max-width:1180px;margin:0 auto 16px;padding:15px 16px;border-radius:18px;background:#fff;border:1px solid #e2e8f0;box-shadow:0 12px 28px rgba(15,23,42,.05);line-height:1.7}
    .notice{background:#ecfeff;color:#155e75;border-color:#a5f3fc}
    .panel{max-width:1180px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    table{width:100%;border-collapse:collapse}th,td{padding:14px 16px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}th{background:#f8fafc;color:#475569;font-size:13px}td{font-size:14px}
    small{color:#64748b;word-break:break-all}.badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700}.success{background:#dcfce7;color:#166534}.warning{background:#fef3c7;color:#92400e}
    .empty{text-align:center;color:#64748b;padding:30px}.actions{max-width:1180px;margin:16px auto 0;display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#0f172a;color:#fff;text-decoration:none}.btn.secondary{background:#e2e8f0;color:#0f172a}
    @media (max-width:1000px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.panel{overflow:auto}table{min-width:900px}}
    @media (max-width:560px){.shell{padding:18px}.stats{grid-template-columns:1fr}.hero{padding:22px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>推广返佣</h1>
      <p>{$displayName}，当前页面可直接查看邀请链接、返佣汇总和邀请商户记录；邀请链接会按当前商户固定生成。</p>
    </section>
    <section class="notice">推广返佣会按系统配置和订单结算规则自动统计；本页聚焦查看数据与复制邀请链接。</section>
    <section class="stats">
      <div class="card"><span>累计返佣</span><strong>{$totalRebate}</strong></div>
      <div class="card"><span>今日返佣</span><strong>{$todayRebate}</strong></div>
      <div class="card"><span>邀请商户数</span><strong>{$inviteCount}</strong></div>
      <div class="card"><span>实名商户数</span><strong>{$verifiedCount}</strong></div>
      <div class="card"><span>会员商户数</span><strong>{$vipCount}</strong></div>
    </section>
    <section class="meta"><strong>返佣类型：</strong>{$rebateType}<br><strong>返佣比例：</strong>{$percentage}<br><strong>上级商户：</strong>{$parentAffiliate}<br><strong>推广地址：</strong>{$inviteUrl}<br><strong>最近邀请：</strong>{$lastInviteTime}</section>
    <section class="panel">
      <table>
        <thead><tr><th>商户</th><th>显示信息</th><th>实名状态</th><th>会员 / 余额</th><th>联系方式</th></tr></thead>
        <tbody>
{$rowsHtml}
        </tbody>
      </table>
    </section>
    <nav class="actions">
      <a class="btn secondary" href="/User/Index">商户中心</a>
      <a class="btn secondary" href="/My/userpro">资料维护</a>
      <a class="btn secondary" href="/My/real_name">实名认证</a>
      <a class="btn secondary" href="/My/Security">安全中心</a>
      <a class="btn secondary" href="/My/loginlog">登录日志</a>
      <a class="btn secondary" href="/My/aff?format=json">JSON 视图</a>
      <a class="btn secondary" href="/My/affInfo?limit=20">数据接口</a>
      <a class="btn" href="/User/Logout">退出登录</a>
    </nav>
  </div>
</body>
</html>
HTML;
    }

    private function realNamePage(array $merchant, array $payload): string
    {
        $displayName = $this->escape($this->merchantDisplay($merchant));
        $status = (array)($payload['status'] ?? []);
        $verification = (array)($payload['verification'] ?? []);
        $cost = (array)($payload['cost'] ?? []);
        $statusLabel = $this->escape((string)($status['label'] ?? '未知状态'));
        $statusType = $this->escape((string)($status['type'] ?? 'info'));
        $maskedName = $this->escape((string)($status['name_masked'] ?? '未留存'));
        $maskedIdCard = $this->escape((string)($status['id_card_masked'] ?? '未留存'));
        $typeLabel = $this->escape((string)($verification['type_label'] ?? '未知流程'));
        $availableChannels = (int)($verification['available_channel_count'] ?? 0);
        $costLabel = !empty($cost['merchant_bears_cost'])
            ? ('商户承担 ' . $this->escape((string)($cost['amount_display'] ?? '0.00')))
            : '平台承担认证费用';
        $rowsHtml = '';

        foreach ((array)($verification['channels'] ?? []) as $channel) {
            $label = $this->escape((string)($channel['label'] ?? '通道'));
            $flow = $this->escape((string)($channel['flow'] ?? ''));
            $availability = !empty($channel['available']) ? '可用' : '未开放';
            $availabilityType = !empty($channel['available']) ? 'success' : 'warning';
            $rowsHtml .= <<<HTML
        <tr>
          <td>{$label}</td>
          <td>{$flow}</td>
          <td><span class="badge {$availabilityType}">{$availability}</span></td>
          <td><button type="button" disabled>暂未开放</button></td>
        </tr>
HTML;
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="4" class="empty">当前未配置可用的认证通道。</td></tr>';
        }

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>商户实名认证</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f6f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1180px;margin:0 auto 18px;padding:26px;border-radius:24px;background:linear-gradient(135deg,#111827,#b45309);color:#fff;box-shadow:0 20px 60px rgba(15,23,42,.18)}
    .hero h1{margin:0 0 8px;font-size:30px}.hero p{margin:0;color:#fde68a;line-height:1.7}
    .stats{max-width:1180px;margin:0 auto 16px;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;box-shadow:0 14px 34px rgba(15,23,42,.06)}
    .card span{display:block;color:#64748b;font-size:13px;margin-bottom:7px}.card strong{font-size:22px;word-break:break-all}
    .notice{max-width:1180px;margin:0 auto 16px;padding:15px 16px;border-radius:18px;background:#fffbeb;border:1px solid #fcd34d;color:#92400e;line-height:1.7}
    .grid{max-width:1180px;margin:0 auto 16px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .panel{background:#fff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    .panel h2{margin:0;padding:16px 18px;border-bottom:1px solid #e2e8f0;font-size:18px}
    table{width:100%;border-collapse:collapse}th,td{padding:14px 16px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}th{background:#f8fafc;color:#475569;font-size:13px}td{font-size:14px}
    .badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700}.success{background:#dcfce7;color:#166534}.warning{background:#fef3c7;color:#92400e}.info{background:#dbeafe;color:#1e40af}
    button{padding:8px 10px;border:0;border-radius:10px;background:#e2e8f0;color:#64748b;font-weight:700}.empty{text-align:center;color:#64748b;padding:30px}
    .actions{max-width:1180px;margin:16px auto 0;display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#0f172a;color:#fff;text-decoration:none}.btn.secondary{background:#e2e8f0;color:#0f172a}
    @media (max-width:1000px){.stats,.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.panel{overflow:auto}table{min-width:760px}}
    @media (max-width:560px){.shell{padding:18px}.stats,.grid{grid-template-columns:1fr}.hero{padding:22px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>商户实名认证</h1>
      <p>{$displayName}，当前页面用于查看认证结果、费用策略和可用通道；正式提交入口请使用已开放的认证页面。</p>
    </section>
    <section class="notice">当前页主要用于查看实名状态与通道信息，暂不支持在这里直接修改商户身份资料。</section>
    <section class="stats">
      <div class="card"><span>认证状态</span><strong><span class="badge {$statusType}">{$statusLabel}</span></strong></div>
      <div class="card"><span>认证方式</span><strong>{$typeLabel}</strong></div>
      <div class="card"><span>可用通道</span><strong>{$availableChannels}</strong></div>
      <div class="card"><span>费用策略</span><strong>{$costLabel}</strong></div>
      <div class="card"><span>当前模式</span><strong>状态查看</strong></div>
    </section>
    <section class="grid">
      <section class="panel">
        <h2>身份信息快照</h2>
        <table>
          <tbody>
            <tr><th>实名姓名</th><td>{$maskedName}</td></tr>
            <tr><th>证件号码</th><td>{$maskedIdCard}</td></tr>
            <tr><th>状态接口</th><td>/My/getRealNameStatus</td></tr>
            <tr><th>提交接口</th><td>/My/realname</td></tr>
          </tbody>
        </table>
      </section>
      <section class="panel">
        <h2>功能说明</h2>
        <table>
          <tbody>
            <tr><th>当前模式</th><td>仅提供状态查看与通道说明。</td></tr>
            <tr><th>认证提供方</th><td>微信、芝麻信用和支付宝等认证通道会按后台配置展示可用状态。</td></tr>
            <tr><th>状态查询</th><td><code>/My/getRealNameStatus</code> 用于查询当前实名状态。</td></tr>
            <tr><th>建议路径</th><td>如需发起实名认证，请进入已开放的认证入口完成操作。</td></tr>
          </tbody>
        </table>
      </section>
    </section>
    <section class="panel" style="max-width:1180px;margin:0 auto;">
      <h2>认证通道</h2>
      <table>
        <thead><tr><th>通道</th><th>认证流程</th><th>可用状态</th><th>操作</th></tr></thead>
        <tbody>
{$rowsHtml}
        </tbody>
      </table>
    </section>
    <nav class="actions">
      <a class="btn secondary" href="/User/Index">商户中心</a>
      <a class="btn secondary" href="/My/Security">安全中心</a>
      <a class="btn secondary" href="/My/GoogleAuth">谷歌验证</a>
      <a class="btn secondary" href="/My/aff">推广返佣</a>
      <a class="btn secondary" href="/My/real_name?format=json">JSON 视图</a>
      <a class="btn" href="/User/Logout">退出登录</a>
    </nav>
  </div>
</body>
</html>
HTML;
    }

    private function ordersPage(array $merchant, array $payload): string
    {
        $displayName = $this->escape($this->merchantDisplay($merchant));
        $summary = (array)($payload['summary'] ?? []);
        $records = (array)($payload['records'] ?? []);
        $totalCount = (int)($summary['total_count'] ?? 0);
        $paidCount = (int)($summary['paid_count'] ?? 0);
        $pendingCount = (int)($summary['pending_count'] ?? 0);
        $paidAmount = $this->money($summary['paid_amount'] ?? 0);
        $successRate = number_format((float)($summary['success_rate'] ?? 0), 2, '.', '');
        $lastOrderTime = $this->escape((string)($summary['last_order_time'] ?? '--'));
        $rowsHtml = '';

        foreach ($records as $record) {
            $id = (int)($record['id'] ?? 0);
            $name = $this->escape((string)($record['name'] ?? ''));
            $tradeNo = $this->escape((string)($record['trade_no'] ?? ''));
            $outTradeNo = $this->escape((string)($record['out_trade_no'] ?? ''));
            $typeLabel = $this->escape((string)($record['type_label'] ?? ''));
            $channel = $this->escape((string)($record['channel_label'] ?? ''));
            $amount = $this->money($record['settled_amount'] ?? 0);
            $statusLabel = $this->escape((string)($record['status_label'] ?? ''));
            $statusBadge = $this->escape((string)($record['status_badge'] ?? 'info'));
            $createdAt = $this->escape((string)($record['create_time'] ?? '--'));
            $rowsHtml .= <<<HTML
        <tr>
          <td>#{$id}<br><small>{$createdAt}</small></td>
          <td><strong>{$name}</strong><br><small>{$tradeNo}</small><br><small>{$outTradeNo}</small></td>
          <td>{$typeLabel}<br><small>{$channel}</small></td>
          <td>{$amount}</td>
          <td><span class="badge {$statusBadge}">{$statusLabel}</span></td>
          <td><a href="/Deal/getDetails?id={$id}" data-order-detail>详情</a></td>
        </tr>
HTML;
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="6" class="empty">No merchant orders matched the current filter.</td></tr>';
        }

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Merchant Orders</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f6f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1180px;margin:0 auto 18px;padding:26px;border-radius:24px;background:linear-gradient(135deg,#111827,#0f766e);color:#fff;box-shadow:0 20px 60px rgba(15,23,42,.18)}
    .hero h1{margin:0 0 8px;font-size:30px}
    .hero p{margin:0;color:#ccfbf1;line-height:1.7}
    .stats{max-width:1180px;margin:0 auto 16px;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;box-shadow:0 14px 34px rgba(15,23,42,.06)}
    .card span{display:block;color:#64748b;font-size:13px;margin-bottom:7px}.card strong{font-size:22px}
    .panel{max-width:1180px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    table{width:100%;border-collapse:collapse}th,td{padding:14px 16px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}th{background:#f8fafc;color:#475569;font-size:13px}td{font-size:14px}small{color:#64748b}
    .badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700}.success{background:#dcfce7;color:#166534}.warning{background:#fef3c7;color:#92400e}.info{background:#dbeafe;color:#1e40af}
    .empty{text-align:center;color:#64748b;padding:30px}.actions{max-width:1180px;margin:16px auto 0;display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#0f172a;color:#fff;text-decoration:none}.btn.secondary{background:#e2e8f0;color:#0f172a}
    @media (max-width:900px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.panel{overflow:auto}table{min-width:820px}}
    @media (max-width:560px){.shell{padding:18px}.stats{grid-template-columns:1fr}.hero{padding:22px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>订单记录</h1>
      <p>{$displayName}，当前页面已支持直接在商户中心重放已支付订单回调。状态重置入口已从产品中移除，避免出现无效操作。</p>
    </section>
    <section class="notice" style="max-width:1180px;margin:0 auto 16px;padding:15px 16px;border-radius:18px;background:#ecfeff;border:1px solid #99f6e4;color:#115e59;line-height:1.7">旧版 <code>/Deal/set_function</code> 现仅保留已支付订单回调重放；状态重置已关闭。</section>
    <section class="stats">
      <div class="card"><span>订单总数</span><strong>{$totalCount}</strong></div>
      <div class="card"><span>已支付订单</span><strong>{$paidCount}</strong></div>
      <div class="card"><span>待支付订单</span><strong>{$pendingCount}</strong></div>
      <div class="card"><span>支付金额</span><strong>{$paidAmount}</strong></div>
      <div class="card"><span>成功率</span><strong>{$successRate}%</strong></div>
    </section>
    <section class="panel">
      <table>
        <thead><tr><th>订单</th><th>交易信息</th><th>方式</th><th>金额</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
{$rowsHtml}
        </tbody>
      </table>
    </section>
    <nav class="actions">
      <a class="btn secondary" href="/User/Index">商户中心</a>
      <a class="btn secondary" href="/My/userpro">资料维护</a>
      <a class="btn secondary" href="/Deal/Recharge">充值中心</a>
      <a class="btn secondary" href="/Deal/MoneyLog">资金日志</a>
      <a class="btn secondary" href="/Deal/Vip">会员套餐</a>
      <a class="btn secondary" href="/My/Api">接口信息</a>
      <a class="btn secondary" href="/My/Ticket">工单中心</a>
      <a class="btn secondary" href="/My/is_domain">域名管理</a>
      <a class="btn secondary" href="/My/loginlog">登录日志</a>
      <a class="btn secondary" href="/Deal/OrderLog?format=json">查看 JSON</a>
      <a class="btn" href="/User/Logout">退出登录</a>
    </nav>
    <section class="actions"><small>最近订单：{$lastOrderTime}</small></section>
  </div>
</body>
</html>
HTML;
    }

    private function moneyLogsPage(array $merchant, array $payload): string
    {
        $displayName = $this->escape($this->merchantDisplay($merchant));
        $summary = (array)($payload['summary'] ?? []);
        $records = (array)($payload['records'] ?? []);
        $incomeCount = (int)($summary['income_count'] ?? 0);
        $expenseCount = (int)($summary['expense_count'] ?? 0);
        $incomeAmount = $this->logMoney($summary['income_amount'] ?? 0);
        $expenseAmount = $this->logMoney($summary['expense_amount'] ?? 0);
        $netAmount = $this->signedLogMoney((float)($summary['net_amount'] ?? 0));
        $lastLogTime = $this->escape((string)($summary['last_log_time'] ?? '--'));
        $rowsHtml = '';

        foreach ($records as $record) {
            $id = (int)($record['id'] ?? 0);
            $createdAt = $this->escape((string)($record['create_time'] ?? '--'));
            $moneyDisplay = $this->escape((string)($record['money_display'] ?? '0.000'));
            $beforeDisplay = $this->escape((string)($record['before_money_display'] ?? '0.000'));
            $afterDisplay = $this->escape((string)($record['after_money_display'] ?? '0.000'));
            $typeLabel = $this->escape((string)($record['type_label'] ?? ''));
            $directionLabel = $this->escape((string)($record['direction_label'] ?? ''));
            $typeTag = $this->escape((string)($record['type_tag'] ?? 'info'));
            $memo = $this->escape((string)($record['memo_label'] ?? '无备注'));
            $rowsHtml .= <<<HTML
        <tr>
          <td>#{$id}<br><small>{$createdAt}</small></td>
          <td><span class="amount {$typeTag}">{$moneyDisplay}</span><br><small>{$directionLabel}</small></td>
          <td>{$beforeDisplay}<br><small>变更后 {$afterDisplay}</small></td>
          <td>{$typeLabel}</td>
          <td>{$memo}</td>
        </tr>
HTML;
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="5" class="empty">当前筛选条件下暂无资金日志。</td></tr>';
        }

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>资金日志</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f6f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1180px;margin:0 auto 18px;padding:26px;border-radius:24px;background:linear-gradient(135deg,#0f172a,#b45309);color:#fff;box-shadow:0 20px 60px rgba(15,23,42,.18)}
    .hero h1{margin:0 0 8px;font-size:30px}
    .hero p{margin:0;color:#ffedd5;line-height:1.7}
    .stats{max-width:1180px;margin:0 auto 16px;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;box-shadow:0 14px 34px rgba(15,23,42,.06)}
    .card span{display:block;color:#64748b;font-size:13px;margin-bottom:7px}.card strong{font-size:22px}
    .panel{max-width:1180px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    table{width:100%;border-collapse:collapse}th,td{padding:14px 16px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}th{background:#f8fafc;color:#475569;font-size:13px}td{font-size:14px}small{color:#64748b}
    .amount{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:800}.success{background:#dcfce7;color:#166534}.warning{background:#fee2e2;color:#991b1b}.info{background:#dbeafe;color:#1e40af}
    .empty{text-align:center;color:#64748b;padding:30px}.actions{max-width:1180px;margin:16px auto 0;display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#0f172a;color:#fff;text-decoration:none}.btn.secondary{background:#e2e8f0;color:#0f172a}
    @media (max-width:900px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.panel{overflow:auto}table{min-width:760px}}
    @media (max-width:560px){.shell{padding:18px}.stats{grid-template-columns:1fr}.hero{padding:22px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>资金日志</h1>
      <p>{$displayName}，当前页面仅展示你自己的余额流水，暂不提供余额调账、充值补录、会员购买与日志清理操作。</p>
    </section>
    <section class="stats">
      <div class="card"><span>收入笔数</span><strong>{$incomeCount}</strong></div>
      <div class="card"><span>支出笔数</span><strong>{$expenseCount}</strong></div>
      <div class="card"><span>收入金额</span><strong>{$incomeAmount}</strong></div>
      <div class="card"><span>支出金额</span><strong>{$expenseAmount}</strong></div>
      <div class="card"><span>净变动</span><strong>{$netAmount}</strong></div>
    </section>
    <section class="panel">
      <table>
        <thead><tr><th>日志</th><th>变动</th><th>余额</th><th>类型</th><th>备注</th></tr></thead>
        <tbody>
{$rowsHtml}
        </tbody>
      </table>
    </section>
    <nav class="actions">
      <a class="btn secondary" href="/User/Index">商户中心</a>
      <a class="btn secondary" href="/My/userpro">资料维护</a>
      <a class="btn secondary" href="/Deal/OrderLog">订单记录</a>
      <a class="btn secondary" href="/Deal/Recharge">充值中心</a>
      <a class="btn secondary" href="/Deal/Vip">会员套餐</a>
      <a class="btn secondary" href="/My/Api">接口信息</a>
      <a class="btn secondary" href="/My/Ticket">工单中心</a>
      <a class="btn secondary" href="/My/is_domain">域名管理</a>
      <a class="btn secondary" href="/My/loginlog">登录日志</a>
      <a class="btn secondary" href="/Deal/MoneyLog?format=json">查看 JSON</a>
      <a class="btn" href="/User/Logout">退出登录</a>
    </nav>
    <section class="actions"><small>最近资金日志：{$lastLogTime}</small></section>
  </div>
</body>
</html>
HTML;
    }

    private function rechargesPage(array $merchant, array $payload): string
    {
        $displayName = $this->escape($this->merchantDisplay($merchant));
        $summary = (array)($payload['summary'] ?? []);
        $records = (array)($payload['records'] ?? []);
        $catalog = (array)($payload['catalog'] ?? []);
        $writeActions = (array)($payload['write_actions'] ?? []);
        $methods = (array)($catalog['methods'] ?? []);
        $totalCount = (int)($summary['total_count'] ?? 0);
        $paidCount = (int)($summary['paid_count'] ?? 0);
        $pendingCount = (int)($summary['pending_count'] ?? 0);
        $expiredPendingCount = (int)($summary['expired_pending_count'] ?? 0);
        $paidAmount = $this->money($summary['paid_amount'] ?? 0);
        $pendingAmount = $this->money($summary['pending_amount'] ?? 0);
        $successRate = number_format((float)($summary['success_rate'] ?? 0), 2, '.', '');
        $lastRechargeTime = $this->escape((string)($summary['last_recharge_time'] ?? '--'));
        $minRecharge = $this->money($catalog['min_recharge'] ?? 0.01);
        $maxRecharge = $this->money($catalog['max_recharge'] ?? 0);
        $enabledCount = (int)($catalog['enabled_count'] ?? 0);
        $rechargeCreateEnabled = !empty($writeActions['recharge_create']);
        $cdkRedeemEnabled = !empty($writeActions['cdk_redeem']);
        $submitDisabledAttr = $rechargeCreateEnabled ? '' : ' disabled';
        $heroMessage = $rechargeCreateEnabled
            ? ($cdkRedeemEnabled
                ? '当前已可在同一页面直接创建余额充值订单并兑换卡密。'
                : '当前已可直接创建余额充值订单，卡密兑换入口暂未开放。')
            : ($cdkRedeemEnabled
                ? '当前暂无可用充值通道，暂时仅保留卡密兑换能力。'
                : '当前暂无可用充值通道，充值与卡密兑换入口均未开放。');
        $ruleMessage = $rechargeCreateEnabled
            ? '充值成功回调后，会为商户余额入账、写入 `money_log` 流水，并在 `is_aff=1` 且 `aff_type=0` 时结算上级返佣。'
            : '请先在系统配置中绑定可用上游支付通道并开放充值映射，随后商户端才会放开余额充值。';
        $cdkMessage = $cdkRedeemEnabled
            ? '当前商户会话下，余额卡与会员卡的卡密兑换功能已开放。'
            : '当前商户会话下，卡密兑换功能未开启。';
        $rowsHtml = '';
        $methodCards = '';

        foreach ($records as $record) {
            $id = (int)($record['id'] ?? 0);
            $createdAt = $this->escape((string)($record['create_time'] ?? '--'));
            $paidAt = $this->escape((string)($record['end_time'] ?? '-'));
            $tradeNo = $this->escape((string)($record['out_trade_no'] ?? ''));
            $typeLabel = $this->escape((string)($record['type_label'] ?? ''));
            $rtypeLabel = $this->escape((string)($record['rtype_label'] ?? ''));
            $amount = $this->money($record['money'] ?? 0);
            $statusLabel = $this->escape((string)($record['status_label'] ?? ''));
            $statusBadge = $this->escape((string)($record['status_type'] ?? 'info'));
            $timeoutStatus = $this->escape((string)($record['timeout_status'] ?? ''));
            $rowsHtml .= <<<HTML
        <tr>
          <td>#{$id}<br><small>{$createdAt}</small></td>
          <td><strong>{$tradeNo}</strong><br><small>{$rtypeLabel}</small></td>
          <td>{$typeLabel}</td>
          <td>{$amount}</td>
          <td><span class="badge {$statusBadge}">{$statusLabel}</span><br><small>{$timeoutStatus}</small></td>
          <td>{$paidAt}</td>
        </tr>
HTML;
        }

        foreach ($methods as $method) {
            $item = (array)$method;
            $enabled = !empty($item['enabled']);
            $statusType = $enabled ? 'success' : 'warning';
            $statusLabel = $enabled ? 'Ready' : 'Unavailable';
            $methodId = $this->escape((string)($item['id'] ?? ''));
            $methodLabel = $this->escape((string)($item['label'] ?? strtoupper($methodId)));
            $description = $this->escape((string)($item['description'] ?? ''));
            $checked = $enabled && $methodCards === '' ? ' checked' : '';
            $disabled = $enabled ? '' : ' disabled';
            $methodCards .= <<<HTML
      <label class="method-card {$statusType}">
        <input type="radio" name="type" value="{$methodId}"{$checked}{$disabled}>
        <span class="method-head">
          <strong>{$methodLabel}</strong>
          <span class="badge {$statusType}">{$statusLabel}</span>
        </span>
        <small>{$description}</small>
      </label>
HTML;
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="6" class="empty">No merchant recharge records matched the current filter.</td></tr>';
        }

        if ($methodCards === '') {
            $methodCards = '<div class="empty-card">当前尚未配置可用的充值方式，请先在系统配置中绑定上游支付通道后再使用商户余额充值。</div>';
        }

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Merchant Recharges</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f6f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1180px;margin:0 auto 18px;padding:26px;border-radius:24px;background:linear-gradient(135deg,#0f172a,#0369a1);color:#fff;box-shadow:0 20px 60px rgba(15,23,42,.18)}
    .hero h1{margin:0 0 8px;font-size:30px}
    .hero p{margin:0;color:#e0f2fe;line-height:1.7}
    .composer{max-width:1180px;margin:0 auto 16px;display:grid;grid-template-columns:1.1fr .9fr;gap:14px}
    .composer-card{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:20px;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    .composer-card h2{margin:0 0 10px;font-size:22px}
    .composer-card p{margin:0;color:#475569;line-height:1.7}
    .composer-form{display:grid;gap:14px;margin-top:18px}
    .field{display:grid;gap:8px}
    .field label{font-size:13px;font-weight:700;color:#475569}
    .field input{width:100%;padding:12px 14px;border:1px solid #cbd5e1;border-radius:14px;font:inherit}
    .field input:focus{outline:none;border-color:#0369a1;box-shadow:0 0 0 4px rgba(3,105,161,.12)}
    .method-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
    .method-card{display:grid;gap:10px;padding:14px;border-radius:16px;border:1px solid #e2e8f0;background:#f8fafc;cursor:pointer}
    .method-card input{margin:0}
    .method-card.warning{opacity:.72}
    .method-head{display:flex;align-items:center;justify-content:space-between;gap:10px}
    .composer-actions{display:flex;gap:10px;flex-wrap:wrap}
    .empty-card{padding:16px;border-radius:16px;background:#fff7ed;border:1px solid #fdba74;color:#9a3412;line-height:1.7}
    .stats{max-width:1180px;margin:0 auto 16px;display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;box-shadow:0 14px 34px rgba(15,23,42,.06)}
    .card span{display:block;color:#64748b;font-size:13px;margin-bottom:7px}.card strong{font-size:22px}
    .panel{max-width:1180px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    table{width:100%;border-collapse:collapse}th,td{padding:14px 16px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}th{background:#f8fafc;color:#475569;font-size:13px}td{font-size:14px}small{color:#64748b}
    .badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700}.success{background:#dcfce7;color:#166534}.warning{background:#fef3c7;color:#92400e}.info{background:#dbeafe;color:#1e40af}
    .empty{text-align:center;color:#64748b;padding:30px}.actions{max-width:1180px;margin:16px auto 0;display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#0f172a;color:#fff;text-decoration:none;border:0;font:inherit;cursor:pointer}.btn.secondary{background:#e2e8f0;color:#0f172a}
    .btn:disabled{cursor:not-allowed;opacity:.55}
    @media (max-width:1050px){.composer{grid-template-columns:1fr}.method-grid,.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.panel{overflow:auto}table{min-width:860px}}
    @media (max-width:560px){.shell{padding:18px}.stats{grid-template-columns:1fr}.hero{padding:22px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>充值中心</h1>
      <p>{$displayName}，{$heroMessage}</p>
    </section>
    <section class="composer">
      <article class="composer-card">
        <h2>创建新的充值订单</h2>
        <p>可用充值方式由当前系统充值映射动态加载。超出配置范围的金额会在跳转上游前被直接拦截。</p>
        <form class="composer-form" method="post" action="/Deal/dopay">
          <div class="field">
            <label for="money">充值金额</label>
            <input id="money" name="money" type="number" min="{$minRecharge}" max="{$maxRecharge}" step="0.01" placeholder="{$minRecharge} - {$maxRecharge}" required>
          </div>
          <div class="field">
            <label>充值方式</label>
            <div class="method-grid">
{$methodCards}
            </div>
          </div>
          <div class="composer-actions">
            <button class="btn" type="submit"{$submitDisabledAttr}>创建订单并继续支付</button>
            <a class="btn secondary" href="/Deal/Recharge?format=json">打开 JSON 数据</a>
          </div>
        </form>
      </article>
      <article class="composer-card">
        <h2>充值规则</h2>
        <p>已启用方式：<strong>{$enabledCount}</strong>。最小金额：<strong>{$minRecharge}</strong>。最大金额：<strong>{$maxRecharge}</strong>。</p>
        <p style="margin-top:14px">{$ruleMessage}</p>
        <p style="margin-top:14px">{$cdkMessage}</p>
      </article>
    </section>
    <section class="stats">
      <div class="card"><span>总数</span><strong>{$totalCount}</strong></div>
      <div class="card"><span>已支付</span><strong>{$paidCount}</strong></div>
      <div class="card"><span>待支付</span><strong>{$pendingCount}</strong></div>
      <div class="card"><span>已过期待支付</span><strong>{$expiredPendingCount}</strong></div>
      <div class="card"><span>支付金额</span><strong>{$paidAmount}</strong></div>
      <div class="card"><span>成功率</span><strong>{$successRate}%</strong></div>
    </section>
    <section class="panel">
      <table>
        <thead><tr><th>记录</th><th>交易信息</th><th>方式</th><th>金额</th><th>状态</th><th>支付时间</th></tr></thead>
        <tbody>
{$rowsHtml}
        </tbody>
      </table>
    </section>
    <nav class="actions">
      <a class="btn secondary" href="/User/Index">商户中心</a>
      <a class="btn secondary" href="/My/userpro">资料维护</a>
      <a class="btn secondary" href="/Deal/OrderLog">订单记录</a>
      <a class="btn secondary" href="/Deal/MoneyLog">资金日志</a>
      <a class="btn secondary" href="/Deal/Vip">会员套餐</a>
      <a class="btn secondary" href="/My/Api">接口信息</a>
      <a class="btn secondary" href="/My/Ticket">工单中心</a>
      <a class="btn secondary" href="/My/is_domain">域名管理</a>
      <a class="btn secondary" href="/My/loginlog">登录日志</a>
      <a class="btn secondary" href="/Deal/Recharge?format=json">查看 JSON</a>
      <a class="btn" href="/User/Logout">退出登录</a>
    </nav>
    <section class="actions"><small>最近充值：{$lastRechargeTime}。待支付金额：{$pendingAmount}</small></section>
  </div>
</body>
</html>
HTML;
    }

    private function vipPage(array $merchant, array $payload): string
    {
        $displayName = $this->escape($this->merchantDisplay($merchant));
        $summary = (array)($payload['summary'] ?? []);
        $records = (array)($payload['records'] ?? []);
        $currentVip = (array)($payload['current_vip'] ?? []);
        $totalCount = (int)($summary['total_count'] ?? 0);
        $minPrice = $this->money($summary['min_price'] ?? 0);
        $maxPrice = $this->money($summary['max_price'] ?? 0);
        $passageLockedCount = (int)($summary['passage_locked_count'] ?? 0);
        $quotaEnabledCount = (int)($summary['quota_enabled_count'] ?? 0);
        $currentName = $this->escape((string)($currentVip['name'] ?? '普通商户'));
        $currentStatus = $this->escape((string)($currentVip['status_label'] ?? '暂无有效会员'));
        $currentStatusType = $this->escape((string)($currentVip['status_type'] ?? 'info'));
        $vipTime = $this->escape((string)($currentVip['vip_time'] ?? '--'));
        $cardsHtml = '';

        foreach ($records as $record) {
            $name = $this->escape((string)($record['name'] ?? ''));
            $price = $this->money($record['money'] ?? 0);
            $duration = $this->escape((string)($record['duration_label'] ?? ''));
            $feeRate = $this->escape((string)($record['fee_rate_display'] ?? '--'));
            $statusType = !empty($record['is_current']) ? 'success' : 'info';
            $statusLabel = !empty($record['is_current']) ? '当前套餐' : '信息查看';
            $quota = !empty($record['quota_enabled'])
                ? ('日额度 ' . $this->escape((string)($record['today_quota'] ?? '0')) . ' / 月额度 ' . $this->escape((string)($record['month_quota'] ?? '0')))
                : '无限制额度';
            $channels = !empty($record['passage_enabled'])
                ? ((int)($record['passage_count'] ?? 0) . ' 个绑定通道')
                : '全部已启用通道';
            $addChannels = !empty($record['add_channel_enabled'])
                ? ((int)($record['add_channel_num'] ?? 0) . ' 个额外通道')
                : '无额外通道增配';
            $cardsHtml .= <<<HTML
      <article class="plan">
        <div class="plan-head">
          <h2>{$name}</h2>
          <span class="badge {$statusType}">{$statusLabel}</span>
        </div>
        <div class="price">{$price}</div>
        <dl>
          <div><dt>时长</dt><dd>{$duration}</dd></div>
          <div><dt>费率</dt><dd>{$feeRate}</dd></div>
          <div><dt>通道范围</dt><dd>{$channels}</dd></div>
          <div><dt>额度限制</dt><dd>{$quota}</dd></div>
          <div><dt>通道增配</dt><dd>{$addChannels}</dd></div>
        </dl>
        <button type="button" disabled>当前暂未开放购买</button>
      </article>
HTML;
        }

        if ($cardsHtml === '') {
            $cardsHtml = '<div class="empty">当前筛选条件下暂无可用会员套餐。</div>';
        }

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>会员套餐</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f6f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1180px;margin:0 auto 18px;padding:26px;border-radius:24px;background:linear-gradient(135deg,#172033,#7c2d12);color:#fff;box-shadow:0 20px 60px rgba(15,23,42,.18)}
    .hero h1{margin:0 0 8px;font-size:30px}.hero p{margin:0;color:#ffedd5;line-height:1.7}
    .stats{max-width:1180px;margin:0 auto 16px;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}
    .card,.plan{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;box-shadow:0 14px 34px rgba(15,23,42,.06)}
    .card span{display:block;color:#64748b;font-size:13px;margin-bottom:7px}.card strong{font-size:22px}
    .plans{max-width:1180px;margin:0 auto;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
    .plan-head{display:flex;align-items:center;justify-content:space-between;gap:10px}.plan h2{margin:0;font-size:20px}.price{margin:16px 0;font-size:32px;font-weight:800;color:#0f172a}
    dl{display:grid;gap:8px;margin:0 0 16px}dl div{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #eef2f7;padding-bottom:8px}dt{color:#64748b}dd{margin:0;text-align:right;color:#0f172a}
    .badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700}.success{background:#dcfce7;color:#166534}.warning{background:#fef3c7;color:#92400e}.info{background:#dbeafe;color:#1e40af}
    button{width:100%;padding:11px 14px;border:0;border-radius:12px;background:#e2e8f0;color:#64748b;font-weight:700}.empty{max-width:1180px;margin:0 auto;padding:30px;text-align:center;color:#64748b;background:#fff;border-radius:18px}
    .actions{max-width:1180px;margin:16px auto 0;display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#0f172a;color:#fff;text-decoration:none}.btn.secondary{background:#e2e8f0;color:#0f172a}
    @media (max-width:1000px){.stats,.plans{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media (max-width:560px){.shell{padding:18px}.stats,.plans{grid-template-columns:1fr}.hero{padding:22px}dl div{display:block}dd{text-align:left;margin-top:4px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>会员套餐</h1>
      <p>{$displayName}，当前页面仅展示会员套餐信息，购买与续费暂未开放。</p>
    </section>
    <section class="stats">
      <div class="card"><span>当前会员</span><strong>{$currentName}</strong></div>
      <div class="card"><span>状态</span><strong><span class="badge {$currentStatusType}">{$currentStatus}</span></strong></div>
      <div class="card"><span>会员到期时间</span><strong>{$vipTime}</strong></div>
      <div class="card"><span>可用套餐数</span><strong>{$totalCount}</strong></div>
      <div class="card"><span>价格区间</span><strong>{$minPrice}-{$maxPrice}</strong></div>
    </section>
    <section class="stats">
      <div class="card"><span>通道限制套餐</span><strong>{$passageLockedCount}</strong></div>
      <div class="card"><span>额度限制套餐</span><strong>{$quotaEnabledCount}</strong></div>
      <div class="card"><span>购买状态</span><strong>受限</strong></div>
      <div class="card"><span>当前模式</span><strong>信息查看</strong></div>
      <div class="card"><span>购买入口</span><strong>暂未开放</strong></div>
    </section>
    <section class="plans">
{$cardsHtml}
    </section>
    <nav class="actions">
      <a class="btn secondary" href="/User/Index">商户中心</a>
      <a class="btn secondary" href="/My/userpro">资料维护</a>
      <a class="btn secondary" href="/Deal/OrderLog">订单记录</a>
      <a class="btn secondary" href="/Deal/Recharge">充值记录</a>
      <a class="btn secondary" href="/Deal/MoneyLog">资金日志</a>
      <a class="btn secondary" href="/My/Api">接口信息</a>
      <a class="btn secondary" href="/My/Ticket">工单中心</a>
      <a class="btn secondary" href="/My/is_domain">域名管理</a>
      <a class="btn secondary" href="/My/loginlog">登录日志</a>
      <a class="btn secondary" href="/Deal/Vip?format=json">查看 JSON</a>
      <a class="btn" href="/User/Logout">退出登录</a>
    </nav>
  </div>
</body>
</html>
HTML;
    }

    private function apiPage(array $merchant, array $payload): string
    {
        $displayName = $this->escape($this->merchantDisplay($merchant));
        $merchantId = (int)($payload['merchant_id'] ?? ($merchant['id'] ?? 0));
        $username = $this->escape((string)($payload['merchant_username'] ?? ($merchant['username'] ?? '')));
        $signConfigured = !empty($payload['sign_key_configured']);
        $appkeyConfigured = !empty($payload['appkey_configured']);
        $signState = $signConfigured ? '已配置' : '未配置';
        $appkeyState = $appkeyConfigured ? '已配置' : '未配置';
        $signMasked = $this->escape((string)($payload['sign_key_masked'] ?? '未配置'));
        $appkeyMasked = $this->escape((string)($payload['appkey_masked'] ?? '未配置'));
        $timeoutTime = (int)($payload['timeout_time'] ?? 0);
        $timeoutUrl = $this->escape((string)($payload['timeout_url'] ?? '/'));
        $timeoutMethod = $this->escape((string)($payload['timeout_method_label'] ?? '使用已配置的超时跳转地址'));
        $gatewayRowsHtml = '';

        foreach ((array)($payload['gateway_lines'] ?? []) as $line) {
            $lineName = $this->escape((string)($line['name'] ?? '线路'));
            $lineUrl = $this->escape((string)($line['url'] ?? ''));
            $submitUrl = $this->escape((string)($line['submit_url'] ?? ''));
            $mapiUrl = $this->escape((string)($line['mapi_url'] ?? ''));
            $gatewayRowsHtml .= <<<HTML
        <tr>
          <td>{$lineName}</td>
          <td><code>{$lineUrl}</code></td>
          <td><code>{$submitUrl}</code><br><small><code>{$mapiUrl}</code></small></td>
        </tr>
HTML;
        }

        if ($gatewayRowsHtml === '') {
            $gatewayRowsHtml = '<tr><td colspan="3" class="empty">No gateway lines are available.</td></tr>';
        }

        $endpointRowsHtml = '';
        foreach ((array)($payload['endpoints'] ?? []) as $name => $endpoint) {
            $label = $this->escape((string)$name);
            $method = $this->escape((string)($endpoint['method'] ?? 'GET/POST'));
            $url = $this->escape((string)($endpoint['url'] ?? ''));
            $description = $this->escape((string)($endpoint['description'] ?? ''));
            $endpointRowsHtml .= <<<HTML
        <tr>
          <td>{$label}<br><small>{$method}</small></td>
          <td><code>{$url}</code></td>
          <td>{$description}</td>
        </tr>
HTML;
        }

        if ($endpointRowsHtml === '') {
            $endpointRowsHtml = '<tr><td colspan="3" class="empty">No endpoint metadata is available.</td></tr>';
        }

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Merchant API</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f6f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1180px;margin:0 auto 18px;padding:26px;border-radius:24px;background:linear-gradient(135deg,#172033,#0f766e);color:#fff;box-shadow:0 20px 60px rgba(15,23,42,.18)}
    .hero h1{margin:0 0 8px;font-size:30px}.hero p{margin:0;color:#ccfbf1;line-height:1.7}
    .stats{max-width:1180px;margin:0 auto 16px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;box-shadow:0 14px 34px rgba(15,23,42,.06)}
    .card span{display:block;color:#64748b;font-size:13px;margin-bottom:7px}.card strong{font-size:22px;word-break:break-all}
    .panel{max-width:1180px;margin:0 auto 16px;background:#fff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    .panel h2{margin:0;padding:16px 18px;border-bottom:1px solid #e2e8f0;font-size:18px}
    table{width:100%;border-collapse:collapse}th,td{padding:14px 16px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}th{background:#f8fafc;color:#475569;font-size:13px}td{font-size:14px}small{color:#64748b}
    code{display:inline-block;max-width:100%;padding:3px 6px;border-radius:8px;background:#f1f5f9;color:#0f172a;word-break:break-all}
    .badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700}.success{background:#dcfce7;color:#166534}.warning{background:#fef3c7;color:#92400e}.info{background:#dbeafe;color:#1e40af}
    .notice{max-width:1180px;margin:0 auto 16px;padding:15px 16px;border-radius:18px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;line-height:1.7}
    .empty{text-align:center;color:#64748b;padding:30px}.actions{max-width:1180px;margin:16px auto 0;display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#0f172a;color:#fff;text-decoration:none}.btn.secondary{background:#e2e8f0;color:#0f172a}
    @media (max-width:1000px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.panel{overflow:auto}table{min-width:860px}}
    @media (max-width:560px){.shell{padding:18px}.stats{grid-template-columns:1fr}.hero{padding:22px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>接口信息</h1>
      <p>{$displayName}，当前页面会在加载时对现有密钥做脱敏展示，并支持重置签名密钥、通讯密钥与按所选线路生成商户二维码。原始密钥导出仍保持关闭。</p>
    </section>
    <section class="notice">现有密钥会一直以脱敏形式展示，只有在你主动重置时才会返回一次新的明文密钥，请在离开页面前完成保存。</section>
    <section class="stats">
      <div class="card"><span>商户 ID</span><strong>{$merchantId}</strong></div>
      <div class="card"><span>登录账号</span><strong>{$username}</strong></div>
      <div class="card"><span>签名密钥</span><strong><span class="badge success">{$signState}</span></strong></div>
      <div class="card"><span>通讯密钥</span><strong><span class="badge info">{$appkeyState}</span></strong></div>
    </section>
    <section class="stats">
      <div class="card"><span>已脱敏签名密钥</span><strong>{$signMasked}</strong></div>
      <div class="card"><span>已脱敏通讯密钥</span><strong>{$appkeyMasked}</strong></div>
      <div class="card"><span>超时秒数</span><strong>{$timeoutTime}</strong></div>
      <div class="card"><span>密钥操作</span><strong>已启用</strong></div>
    </section>
    <section class="panel">
      <h2>密钥重置</h2>
      <div style="padding:18px">
        <p style="margin:0 0 14px;color:#64748b;line-height:1.7">你可以在这里重置保存在 <code>ypay_user.user_key</code> 的签名密钥，或保存在 <code>ypay_userbasic.appkey</code> 的通讯密钥。当前值会保持脱敏，重置后仅返回一次新的明文密钥。</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <button type="button" id="resetSignKeyBtn" style="border:0;border-radius:12px;padding:10px 14px;font:inherit;font-weight:700;cursor:pointer;background:#0f766e;color:#fff">重置签名密钥</button>
          <button type="button" id="resetAppkeyBtn" style="border:0;border-radius:12px;padding:10px 14px;font:inherit;font-weight:700;cursor:pointer;background:#e2e8f0;color:#0f172a">重置通讯密钥</button>
        </div>
        <div id="apiKeyStatus" style="min-height:22px;margin-top:12px;color:#0f766e;font-size:14px;line-height:1.6"></div>
        <code id="apiKeySecret" style="display:none;max-width:100%;margin-top:10px;padding:10px 12px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;word-break:break-all"></code>
      </div>
    </section>
    <section class="panel">
      <h2>网关线路</h2>
      <table>
        <thead><tr><th>线路</th><th>基础地址</th><th>入口地址</th></tr></thead>
        <tbody>
{$gatewayRowsHtml}
        </tbody>
      </table>
    </section>
    <section class="panel">
      <h2>接口说明</h2>
      <table>
        <thead><tr><th>入口</th><th>URL</th><th>说明</th></tr></thead>
        <tbody>
{$endpointRowsHtml}
        </tbody>
      </table>
    </section>
    <section class="panel">
      <h2>超时设置</h2>
      <table>
        <tbody>
          <tr><th>超时地址</th><td><code>{$timeoutUrl}</code></td><td>{$timeoutMethod}</td></tr>
          <tr><th>二维码</th><td><span class="badge success">可生成</span></td><td>商户二维码会按所选线路、商户 ID 与当前通讯密钥生成加密信息，并在点击时按需展示。</td></tr>
          <tr><th>密钥重置</th><td><span class="badge success">已生效</span></td><td>请使用上方密钥重置面板为当前商户生成新的签名密钥或通讯密钥。</td></tr>
        </tbody>
      </table>
    </section>
    <nav class="actions">
      <a class="btn secondary" href="/User/Index">商户中心</a>
      <a class="btn secondary" href="/My/userpro">资料维护</a>
      <a class="btn secondary" href="/Deal/OrderLog">订单记录</a>
      <a class="btn secondary" href="/Deal/Recharge">充值记录</a>
      <a class="btn secondary" href="/Deal/MoneyLog">资金日志</a>
      <a class="btn secondary" href="/Deal/Vip">会员套餐</a>
      <a class="btn secondary" href="/My/Api">接口信息</a>
      <a class="btn secondary" href="/My/Ticket">工单中心</a>
      <a class="btn secondary" href="/My/is_domain">域名管理</a>
      <a class="btn secondary" href="/My/loginlog">登录日志</a>
      <a class="btn secondary" href="/My/Api?format=json">查看 JSON</a>
      <a class="btn" href="/User/Logout">退出登录</a>
    </nav>
  </div>
  <script>
    const apiKeyStatus = document.getElementById('apiKeyStatus');
    const apiKeySecret = document.getElementById('apiKeySecret');

    const setApiStatus = (message, isError = false) => {
      apiKeyStatus.textContent = message;
      apiKeyStatus.style.color = isError ? '#b91c1c' : '#0f766e';
    };

    const rotateMerchantKey = async (url, label) => {
      setApiStatus('正在重置' + label + '...');
      apiKeySecret.style.display = 'none';
      try {
        const response = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            'Accept': 'application/json'
          },
          body: ''
        });
        const payload = await response.json();
        if (payload && payload.code === 1 && payload.key) {
          setApiStatus(label + '重置成功，请立即保存新的密钥。');
          apiKeySecret.style.display = 'block';
          apiKeySecret.textContent = payload.key;
          return;
        }
        setApiStatus(payload && (payload.message || payload.msg) ? (payload.message || payload.msg) : label + '重置失败。', true);
      } catch (error) {
        setApiStatus(label + '重置失败，请稍后重试。', true);
      }
    };

    document.getElementById('resetSignKeyBtn').addEventListener('click', () => {
      if (!confirm('确认立即生成新的商户签名密钥吗？')) {
        return;
      }
      rotateMerchantKey('/My/GeneratingKey', '签名密钥');
    });

    document.getElementById('resetAppkeyBtn').addEventListener('click', () => {
      if (!confirm('确认立即生成新的商户通讯密钥吗？')) {
        return;
      }
      rotateMerchantKey('/My/goAPPKey', '通讯密钥');
    });
  </script>
</body>
</html>
HTML;
    }

    private function domainsPage(array $merchant, array $payload): string
    {
        $displayName = $this->escape($this->merchantDisplay($merchant));
        $summary = (array)($payload['summary'] ?? []);
        $records = (array)($payload['records'] ?? []);
        $pendingCount = (int)($summary['pending_count'] ?? 0);
        $approvedCount = (int)($summary['approved_count'] ?? 0);
        $rejectedCount = (int)($summary['rejected_count'] ?? 0);
        $lastDomainTime = $this->escape((string)($summary['last_domain_time'] ?? '--'));
        $rowsHtml = '';

        foreach ($records as $record) {
            $id = (int)($record['id'] ?? 0);
            $createdAt = $this->escape((string)($record['create_time'] ?? '--'));
            $siteNameRaw = (string)($record['sitename'] ?? '未命名站点');
            $siteUrlRaw = (string)($record['siteurl'] ?? '');
            $siteName = $this->escape($siteNameRaw);
            $siteUrl = $this->escape($siteUrlRaw);
            $statusLabel = $this->escape((string)($record['status_label'] ?? '未知'));
            $statusType = $this->escape((string)($record['status_type'] ?? 'info'));
            $reason = $this->escape((string)($record['reason_preview'] ?? '暂无驳回原因'));
            $link = $this->escape((string)($record['siteurl_link'] ?? ''));
            $siteCell = $link !== ''
                ? '<a href="' . $link . '" target="_blank" rel="noreferrer">' . $siteUrl . '</a>'
                : $siteUrl;
            $rowsHtml .= <<<HTML
        <tr>
          <td>#{$id}<br><small>{$createdAt}</small></td>
          <td><strong>{$siteName}</strong><br><small>{$siteCell}</small></td>
          <td><span class="badge {$statusType}">{$statusLabel}</span></td>
          <td>{$reason}</td>
          <td>
            <div class="row-actions">
              <button type="button" class="mini-btn edit-domain" data-id="{$id}" data-sitename="{$siteName}" data-siteurl="{$siteUrl}">编辑 / 重新提交</button>
              <button type="button" class="mini-btn danger delete-domain" data-id="{$id}" data-sitename="{$siteName}">删除</button>
            </div>
          </td>
        </tr>
HTML;
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="5" class="empty">当前筛选条件下暂无域名记录。</td></tr>';
        }

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>域名管理</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f6f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1180px;margin:0 auto 18px;padding:26px;border-radius:24px;background:linear-gradient(135deg,#0f172a,#047857);color:#fff;box-shadow:0 20px 60px rgba(15,23,42,.18)}
    .hero h1{margin:0 0 8px;font-size:30px}.hero p{margin:0;color:#d1fae5;line-height:1.7}
    .stats{max-width:1180px;margin:0 auto 16px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;box-shadow:0 14px 34px rgba(15,23,42,.06)}
    .card span{display:block;color:#64748b;font-size:13px;margin-bottom:7px}.card strong{font-size:22px}
    .notice{max-width:1180px;margin:0 auto 16px;padding:15px 16px;border-radius:18px;background:#ecfdf5;border:1px solid #bbf7d0;color:#166534;line-height:1.7}
    .panel{max-width:1180px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    table{width:100%;border-collapse:collapse}th,td{padding:14px 16px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}th{background:#f8fafc;color:#475569;font-size:13px}td{font-size:14px}small{color:#64748b}a{color:#047857;text-decoration:none;word-break:break-all}
    .badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700}.success{background:#dcfce7;color:#166534}.warning{background:#fef3c7;color:#92400e}.danger{background:#fee2e2;color:#991b1b}.info{background:#dbeafe;color:#1e40af}
    .form-panel{margin:0 auto 16px;padding:22px}
    .form-panel h2{margin:0 0 8px;font-size:22px}
    .form-panel p{margin:0 0 18px;color:#64748b;line-height:1.7}
    .form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .field label{display:block;margin:0 0 8px;color:#334155;font-size:14px;font-weight:600}
    .field input{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:12px;padding:11px 12px;font:inherit;outline:none;background:#fff}
    .field input:focus{border-color:#047857;box-shadow:0 0 0 4px rgba(4,120,87,.12)}
    .field small{display:block;margin-top:6px;color:#64748b}
    .toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
    button.btn,button.mini-btn{border:0;cursor:pointer;font:inherit}
    .btn.primary{background:#047857}
    .status{min-height:22px;margin-top:14px;color:#047857;font-size:14px;line-height:1.6}
    .status.error{color:#b91c1c}
    .row-actions{display:flex;gap:8px;flex-wrap:wrap}
    .mini-btn{padding:8px 10px;border-radius:10px;background:#e2e8f0;color:#0f172a;font-weight:700}
    .mini-btn.danger{background:#fee2e2;color:#991b1b}
    .empty{text-align:center;color:#64748b;padding:30px}
    .actions{max-width:1180px;margin:16px auto 0;display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#0f172a;color:#fff;text-decoration:none}.btn.secondary{background:#e2e8f0;color:#0f172a}
    @media (max-width:1000px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.panel{overflow:auto}table{min-width:860px}}
    @media (max-width:760px){.form-grid{grid-template-columns:1fr}}
    @media (max-width:560px){.shell{padding:18px}.stats{grid-template-columns:1fr}.hero{padding:22px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>域名管理</h1>
      <p>{$displayName}，当前页面已支持提交、修改、重新提交与删除你自己的域名，同时严格将审核范围限制在当前商户内。</p>
    </section>
    <section class="notice">新增或修改后的域名仍会沿用现有审核规则：命中黑名单会直接拦截，命中白名单或自动通过规则会直接审核通过，其余情况则进入待审核状态。</section>
    <section class="stats">
      <div class="card"><span>待审核</span><strong>{$pendingCount}</strong></div>
      <div class="card"><span>已通过</span><strong>{$approvedCount}</strong></div>
      <div class="card"><span>已驳回</span><strong>{$rejectedCount}</strong></div>
      <div class="card"><span>写入能力</span><strong>已启用</strong></div>
    </section>
    <section class="panel form-panel">
      <h2 id="domainFormTitle">提交域名</h2>
      <p>你可以通过这里提交新的商户域名。选择已有记录进行编辑后，表单会切换到重新提交模式，并重新走一遍审核规则。</p>
      <form id="domainForm" method="post" action="/My/addDomain">
        <input type="hidden" id="domainId" name="id" value="">
        <div class="form-grid">
          <div class="field">
            <label for="domainSiteName">站点名称</label>
            <input id="domainSiteName" name="sitename" placeholder="我的收银站点" required>
            <small>该名称会展示在当前商户的域名审核列表中。</small>
          </div>
          <div class="field">
            <label for="domainSiteUrl">站点域名</label>
            <input id="domainSiteUrl" name="siteurl" placeholder="pay.aipay.local" required>
            <small>请不要包含空格，`http://` 前缀和末尾 `/` 会自动规范化。</small>
          </div>
        </div>
        <div class="toolbar">
          <button class="btn primary" id="domainSubmitButton" type="submit">创建域名</button>
          <button class="btn secondary" type="button" id="domainCancelEdit" hidden>取消编辑</button>
        </div>
        <div id="domainStatus" class="status" role="status" aria-live="polite"></div>
      </form>
    </section>
    <section class="panel">
      <table>
        <thead><tr><th>域名</th><th>站点</th><th>状态</th><th>原因</th><th>操作</th></tr></thead>
        <tbody>
{$rowsHtml}
        </tbody>
      </table>
    </section>
    <nav class="actions">
      <a class="btn secondary" href="/User/Index">商户中心</a>
      <a class="btn secondary" href="/My/userpro">资料维护</a>
      <a class="btn secondary" href="/Deal/OrderLog">订单记录</a>
      <a class="btn secondary" href="/Deal/Recharge">充值中心</a>
      <a class="btn secondary" href="/Deal/MoneyLog">资金日志</a>
      <a class="btn secondary" href="/Deal/Vip">会员套餐</a>
      <a class="btn secondary" href="/My/Api">接口信息</a>
      <a class="btn secondary" href="/My/Ticket">工单中心</a>
      <a class="btn secondary" href="/My/is_domain?format=json">查看 JSON</a>
      <a class="btn secondary" href="/My/loginlog">登录日志</a>
      <a class="btn" href="/User/Logout">退出登录</a>
    </nav>
    <section class="actions"><small>最近域名提交：{$lastDomainTime}</small></section>
  </div>
  <script>
    const domainForm = document.getElementById('domainForm');
    const domainFormTitle = document.getElementById('domainFormTitle');
    const domainId = document.getElementById('domainId');
    const domainSiteName = document.getElementById('domainSiteName');
    const domainSiteUrl = document.getElementById('domainSiteUrl');
    const domainSubmitButton = document.getElementById('domainSubmitButton');
    const domainCancelEdit = document.getElementById('domainCancelEdit');
    const domainStatus = document.getElementById('domainStatus');

    function resetDomainForm() {
      domainForm.reset();
      domainId.value = '';
      domainForm.action = '/My/addDomain';
      domainFormTitle.textContent = '提交域名';
      domainSubmitButton.textContent = '创建域名';
      domainCancelEdit.hidden = true;
      domainStatus.classList.remove('error');
      domainStatus.textContent = '';
    }

    domainForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      domainStatus.classList.remove('error');
      domainStatus.textContent = domainId.value ? '正在保存域名修改...' : '正在创建域名...';
      try {
        const response = await fetch(domainForm.action, {
          method: 'POST',
          headers: {'Accept': 'application/json'},
          body: new FormData(domainForm)
        });
        const payload = await response.json().catch(() => null);
        if (payload && Number(payload.code) === 200) {
          domainStatus.textContent = payload.message || '域名保存成功。';
          window.setTimeout(() => window.location.reload(), 450);
          return;
        }
        domainStatus.classList.add('error');
        domainStatus.textContent = payload && payload.message ? payload.message : '域名保存失败。';
      } catch (error) {
        domainStatus.classList.add('error');
        domainStatus.textContent = '域名保存失败，请稍后重试。';
      }
    });

    domainCancelEdit.addEventListener('click', resetDomainForm);

    for (const button of document.querySelectorAll('.edit-domain')) {
      button.addEventListener('click', () => {
        domainId.value = button.dataset.id || '';
        domainSiteName.value = button.dataset.sitename || '';
        domainSiteUrl.value = button.dataset.siteurl || '';
        domainForm.action = '/My/editDomain';
        domainFormTitle.textContent = '编辑 / 重新提交域名';
        domainSubmitButton.textContent = '保存域名修改';
        domainCancelEdit.hidden = false;
        domainStatus.classList.remove('error');
        domainStatus.textContent = '正在编辑域名 #' + (button.dataset.id || '');
        domainSiteName.focus();
      });
    }

    for (const button of document.querySelectorAll('.delete-domain')) {
      button.addEventListener('click', async () => {
        const domainName = button.dataset.sitename || '该域名';
        if (!window.confirm('确认删除 ' + domainName + ' 吗？')) {
          return;
        }
        domainStatus.classList.remove('error');
        domainStatus.textContent = '正在删除域名...';
        const formData = new FormData();
        formData.append('id', button.dataset.id || '');
        try {
          const response = await fetch('/My/delDomain', {
            method: 'POST',
            headers: {'Accept': 'application/json'},
            body: formData
          });
          const payload = await response.json().catch(() => null);
          if (payload && Number(payload.code) === 200) {
            domainStatus.textContent = payload.message || '域名删除成功。';
            window.setTimeout(() => window.location.reload(), 450);
            return;
          }
          domainStatus.classList.add('error');
          domainStatus.textContent = payload && payload.message ? payload.message : '域名删除失败。';
        } catch (error) {
          domainStatus.classList.add('error');
          domainStatus.textContent = '域名删除失败，请稍后重试。';
        }
      });
    }
  </script>
</body>
</html>
HTML;
    }

    private function loginLogsPage(array $merchant, array $payload): string
    {
        $displayName = $this->escape($this->merchantDisplay($merchant));
        $summary = (array)($payload['summary'] ?? []);
        $records = (array)($payload['records'] ?? []);
        $totalCount = (int)($summary['total_count'] ?? 0);
        $payloadCount = (int)($summary['payload_count'] ?? 0);
        $todayCount = (int)($summary['today_count'] ?? 0);
        $ipCount = (int)($summary['ip_count'] ?? 0);
        $lastLogTime = $this->escape((string)($summary['last_log_time'] ?? '--'));
        $rowsHtml = '';

        foreach ($records as $record) {
            $id = (int)($record['id'] ?? 0);
            $createdAt = $this->escape((string)($record['create_time'] ?? '--'));
            $path = $this->escape((string)($record['path'] ?? '/'));
            $url = $this->escape((string)($record['url'] ?? '/'));
            $ip = $this->escape((string)($record['ip'] ?? ''));
            $typeLabel = $this->escape((string)($record['type_label'] ?? '行为日志'));
            $payloadPreview = $this->escape((string)($record['payload_preview'] ?? '未采集到载荷'));
            $userAgent = $this->escape((string)($record['user_agent_preview'] ?? '未知'));
            $rowsHtml .= <<<HTML
        <tr>
          <td>#{$id}<br><small>{$createdAt}</small></td>
          <td><strong>{$path}</strong><br><small>{$url}</small></td>
          <td>{$ip}</td>
          <td><span class="badge info">{$typeLabel}</span></td>
          <td>{$payloadPreview}</td>
          <td>{$userAgent}</td>
        </tr>
HTML;
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="6" class="empty">当前筛选条件下暂无登录日志。</td></tr>';
        }

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>登录日志</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f6f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1180px;margin:0 auto 18px;padding:26px;border-radius:24px;background:linear-gradient(135deg,#0f172a,#7c2d12);color:#fff;box-shadow:0 20px 60px rgba(15,23,42,.18)}
    .hero h1{margin:0 0 8px;font-size:30px}.hero p{margin:0;color:#fed7aa;line-height:1.7}
    .stats{max-width:1180px;margin:0 auto 16px;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;box-shadow:0 14px 34px rgba(15,23,42,.06)}
    .card span{display:block;color:#64748b;font-size:13px;margin-bottom:7px}.card strong{font-size:22px}
    .notice{max-width:1180px;margin:0 auto 16px;padding:15px 16px;border-radius:18px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;line-height:1.7}
    .panel{max-width:1180px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    table{width:100%;border-collapse:collapse}th,td{padding:14px 16px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}th{background:#f8fafc;color:#475569;font-size:13px}td{font-size:14px}small{color:#64748b;word-break:break-all}
    .badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700}.info{background:#dbeafe;color:#1e40af}
    .empty{text-align:center;color:#64748b;padding:30px}.actions{max-width:1180px;margin:16px auto 0;display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#0f172a;color:#fff;text-decoration:none}.btn.secondary{background:#e2e8f0;color:#0f172a}
    @media (max-width:1000px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.panel{overflow:auto}table{min-width:980px}}
    @media (max-width:560px){.shell{padding:18px}.stats{grid-template-columns:1fr}.hero{padding:22px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>登录日志</h1>
      <p>{$displayName}，当前页面仅展示您自己的前台访问行为与登录记录，并自动按当前商户范围过滤，方便进行安全核查与登录轨迹排查。</p>
    </section>
    <section class="notice">当前页面聚焦查询与详情查看，不再展示删除、批量删除或清理等无效操作；所有数据均受当前 front_token 商户范围限制。</section>
    <section class="stats">
      <div class="card"><span>日志总数</span><strong>{$totalCount}</strong></div>
      <div class="card"><span>今日</span><strong>{$todayCount}</strong></div>
      <div class="card"><span>载荷日志</span><strong>{$payloadCount}</strong></div>
      <div class="card"><span>IP 数量</span><strong>{$ipCount}</strong></div>
      <div class="card"><span>访问范围</span><strong>当前商户</strong></div>
    </section>
    <section class="panel">
      <table>
        <thead><tr><th>日志</th><th>路径</th><th>IP</th><th>类型</th><th>载荷</th><th>客户端信息</th></tr></thead>
        <tbody>
{$rowsHtml}
        </tbody>
      </table>
    </section>
    <nav class="actions">
      <a class="btn secondary" href="/User/Index">商户中心</a>
      <a class="btn secondary" href="/My/userpro">资料维护</a>
      <a class="btn secondary" href="/Deal/OrderLog">订单记录</a>
      <a class="btn secondary" href="/Deal/Recharge">充值中心</a>
      <a class="btn secondary" href="/Deal/MoneyLog">资金日志</a>
      <a class="btn secondary" href="/Deal/Vip">会员套餐</a>
      <a class="btn secondary" href="/My/Api">接口信息</a>
      <a class="btn secondary" href="/My/Ticket">工单中心</a>
      <a class="btn secondary" href="/My/is_domain">域名管理</a>
      <a class="btn secondary" href="/My/loginlog?format=json">查看 JSON</a>
      <a class="btn" href="/User/Logout">退出登录</a>
    </nav>
    <section class="actions"><small>最近登录日志：{$lastLogTime}</small></section>
  </div>
</body>
</html>
HTML;
    }

    private function ticketsPage(array $merchant, array $payload): string
    {
        $displayName = $this->escape($this->merchantDisplay($merchant));
        $summary = (array)($payload['summary'] ?? []);
        $records = (array)($payload['records'] ?? []);
        $categories = (array)($payload['categories'] ?? []);
        $newCount = (int)($summary['new_count'] ?? 0);
        $processingCount = (int)($summary['processing_count'] ?? 0);
        $resolvedCount = (int)($summary['resolved_count'] ?? 0);
        $closedCount = (int)($summary['closed_count'] ?? 0);
        $repliedCount = (int)($summary['replied_count'] ?? 0);
        $lastTicketTime = $this->escape((string)($summary['last_ticket_time'] ?? '--'));
        $rowsHtml = '';
        $categoriesHtml = '';
        $categoryOptionsHtml = '';

        foreach ($records as $record) {
            $id = (int)($record['id'] ?? 0);
            $createdAt = $this->escape((string)($record['create_time'] ?? '--'));
            $titleRaw = (string)($record['title'] ?? '未命名工单');
            $title = $this->escape($titleRaw);
            $category = $this->escape((string)($record['type_name'] ?? '未分配分类'));
            $contentPreview = $this->escape((string)($record['content_preview'] ?? '暂无工单内容'));
            $replyPreview = $this->escape((string)($record['reply_preview'] ?? '暂无管理员回复'));
            $replyState = $this->escape((string)($record['reply_state_label'] ?? '待回复'));
            $statusLabel = $this->escape((string)($record['status_label'] ?? '未知状态'));
            $statusType = $this->escape((string)($record['status_type'] ?? 'info'));
            $rowsHtml .= <<<HTML
        <tr>
          <td>#{$id}<br><small>{$createdAt}</small></td>
          <td><strong>{$title}</strong><br><small>{$category}</small></td>
          <td>{$contentPreview}</td>
          <td>{$replyPreview}<br><small>{$replyState}</small></td>
          <td><span class="badge {$statusType}">{$statusLabel}</span></td>
          <td><button type="button" class="mini-btn danger delete-ticket" data-id="{$id}" data-title="{$title}">删除</button></td>
        </tr>
HTML;
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="6" class="empty">当前筛选条件下暂无工单记录。</td></tr>';
        }

        foreach ($categories as $category) {
            $id = (int)($category['id'] ?? 0);
            $name = $this->escape((string)($category['name'] ?? '未命名分类'));
            $statusLabel = $this->escape((string)($category['status_label'] ?? '已启用'));
            $categoriesHtml .= '<span class="chip">' . $name . ' <small>' . $statusLabel . '</small></span>';
            $categoryOptionsHtml .= '<option value="' . $id . '">' . $name . '</option>';
        }

        if ($categoriesHtml === '') {
            $categoriesHtml = '<span class="muted">当前未配置可用工单分类。</span>';
        }

        $ticketCreateDisabled = $categoryOptionsHtml === '' ? ' disabled' : '';
        if ($categoryOptionsHtml === '') {
            $categoryOptionsHtml = '<option value="">暂无启用分类</option>';
        }

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>工单中心</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f6f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1180px;margin:0 auto 18px;padding:26px;border-radius:24px;background:linear-gradient(135deg,#0f172a,#4338ca);color:#fff;box-shadow:0 20px 60px rgba(15,23,42,.18)}
    .hero h1{margin:0 0 8px;font-size:30px}.hero p{margin:0;color:#e0e7ff;line-height:1.7}
    .stats{max-width:1180px;margin:0 auto 16px;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;box-shadow:0 14px 34px rgba(15,23,42,.06)}
    .card span{display:block;color:#64748b;font-size:13px;margin-bottom:7px}.card strong{font-size:22px}
    .notice,.categories{max-width:1180px;margin:0 auto 16px;padding:15px 16px;border-radius:18px;background:#fff;border:1px solid #e2e8f0;box-shadow:0 12px 28px rgba(15,23,42,.05);line-height:1.7}
    .notice{background:#eef2ff;color:#3730a3;border-color:#c7d2fe}
    .panel{max-width:1180px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    table{width:100%;border-collapse:collapse}th,td{padding:14px 16px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}th{background:#f8fafc;color:#475569;font-size:13px}td{font-size:14px}small{color:#64748b}
    .badge,.chip{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700}.success{background:#dcfce7;color:#166534}.warning{background:#fef3c7;color:#92400e}.info{background:#dbeafe;color:#1e40af}.primary{background:#e0e7ff;color:#3730a3}.danger{background:#fee2e2;color:#991b1b}
    .chip{margin:4px;background:#f1f5f9;color:#334155;gap:6px}.muted{color:#64748b}
    .form-panel{margin:0 auto 16px;padding:22px}
    .form-panel h2{margin:0 0 8px;font-size:22px}
    .form-panel p{margin:0 0 18px;color:#64748b;line-height:1.7}
    .form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .field label{display:block;margin:0 0 8px;color:#334155;font-size:14px;font-weight:600}
    .field input,.field select,.field textarea{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:12px;padding:11px 12px;font:inherit;outline:none;background:#fff}
    .field textarea{min-height:120px;resize:vertical}
    .field input:focus,.field select:focus,.field textarea:focus{border-color:#4338ca;box-shadow:0 0 0 4px rgba(67,56,202,.12)}
    .field small{display:block;margin-top:6px;color:#64748b}
    .toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
    button.btn,button.mini-btn{border:0;cursor:pointer;font:inherit}
    .btn.primary{background:#4338ca}
    .status{min-height:22px;margin-top:14px;color:#4338ca;font-size:14px;line-height:1.6}
    .status.error{color:#b91c1c}
    .mini-btn{padding:8px 10px;border-radius:10px;background:#e2e8f0;color:#0f172a;font-weight:700}
    .mini-btn.danger{background:#fee2e2;color:#991b1b}
    .empty{text-align:center;color:#64748b;padding:30px}
    .actions{max-width:1180px;margin:16px auto 0;display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#0f172a;color:#fff;text-decoration:none}.btn.secondary{background:#e2e8f0;color:#0f172a}
    @media (max-width:1000px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.panel{overflow:auto}table{min-width:980px}}
    @media (max-width:760px){.form-grid{grid-template-columns:1fr}}
    @media (max-width:560px){.shell{padding:18px}.stats{grid-template-columns:1fr}.hero{padding:22px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>工单中心</h1>
      <p>{$displayName}，当前页面已支持创建和删除你自己的工单，同时会安全限制管理员回复展示与跨商户访问范围。</p>
    </section>
    <section class="notice">当前已开放工单创建与删除。回复流程仍保留在管理员侧，因此这里只允许处理商户自己创建的工单。</section>
    <section class="stats">
      <div class="card"><span>新建</span><strong>{$newCount}</strong></div>
      <div class="card"><span>处理中</span><strong>{$processingCount}</strong></div>
      <div class="card"><span>已解决</span><strong>{$resolvedCount}</strong></div>
      <div class="card"><span>关闭</span><strong>{$closedCount}</strong></div>
      <div class="card"><span>已回复</span><strong>{$repliedCount}</strong></div>
    </section>
    <section class="categories"><strong>已启用分类：</strong> {$categoriesHtml}</section>
    <section class="panel form-panel">
      <h2>提交工单</h2>
      <p>请使用已启用的工单分类提交新的工单。如果当前没有任何启用分类，创建表单会保持禁用，直到管理员恢复至少一个分类。</p>
      <form id="ticketForm" method="post" action="/My/addTicket">
        <div class="form-grid">
          <div class="field">
            <label for="ticketType">分类</label>
            <select id="ticketType" name="type"{$ticketCreateDisabled}>
              {$categoryOptionsHtml}
            </select>
            <small>工单分类来自管理员侧已启用的工单分类列表。</small>
          </div>
          <div class="field">
            <label for="ticketTitle">标题</label>
            <input id="ticketTitle" name="title" placeholder="请输入问题标题"{$ticketCreateDisabled}>
            <small>建议标题简短且明确，便于管理员侧队列快速分拣。</small>
          </div>
        </div>
        <div class="field" style="margin-top:14px">
          <label for="ticketContent">内容</label>
          <textarea id="ticketContent" name="content" placeholder="请填写完整的问题详情、复现步骤和期望结果。"{$ticketCreateDisabled}></textarea>
        </div>
        <div class="toolbar">
          <button class="btn primary" type="submit"{$ticketCreateDisabled}>提交工单</button>
        </div>
        <div id="ticketStatus" class="status" role="status" aria-live="polite"></div>
      </form>
    </section>
    <section class="panel">
      <table>
        <thead><tr><th>工单</th><th>标题</th><th>内容</th><th>回复</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
{$rowsHtml}
        </tbody>
      </table>
    </section>
    <nav class="actions">
      <a class="btn secondary" href="/User/Index">商户中心</a>
      <a class="btn secondary" href="/My/userpro">资料维护</a>
      <a class="btn secondary" href="/Deal/OrderLog">订单记录</a>
      <a class="btn secondary" href="/Deal/Recharge">充值中心</a>
      <a class="btn secondary" href="/Deal/MoneyLog">资金日志</a>
      <a class="btn secondary" href="/Deal/Vip">会员套餐</a>
      <a class="btn secondary" href="/My/Api">接口信息</a>
      <a class="btn secondary" href="/My/Ticket?format=json">查看 JSON</a>
      <a class="btn secondary" href="/My/is_domain">域名管理</a>
      <a class="btn secondary" href="/My/loginlog">登录日志</a>
      <a class="btn" href="/User/Logout">退出登录</a>
    </nav>
    <section class="actions"><small>最近工单：{$lastTicketTime}</small></section>
  </div>
  <script>
    const ticketForm = document.getElementById('ticketForm');
    const ticketStatus = document.getElementById('ticketStatus');
    ticketForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      ticketStatus.classList.remove('error');
      ticketStatus.textContent = '正在创建工单...';
      try {
        const response = await fetch(ticketForm.action, {
          method: 'POST',
          headers: {'Accept': 'application/json'},
          body: new FormData(ticketForm)
        });
        const payload = await response.json().catch(() => null);
        if (payload && Number(payload.code) === 200) {
          ticketStatus.textContent = payload.message || '工单创建成功。';
          window.setTimeout(() => window.location.reload(), 450);
          return;
        }
        ticketStatus.classList.add('error');
        ticketStatus.textContent = payload && payload.message ? payload.message : '工单创建失败。';
      } catch (error) {
        ticketStatus.classList.add('error');
        ticketStatus.textContent = '工单创建失败，请稍后重试。';
      }
    });

    for (const button of document.querySelectorAll('.delete-ticket')) {
      button.addEventListener('click', async () => {
        const title = button.dataset.title || '该工单';
        if (!window.confirm('确认删除 ' + title + ' 吗？')) {
          return;
        }
        ticketStatus.classList.remove('error');
        ticketStatus.textContent = '正在删除工单...';
        const formData = new FormData();
        formData.append('id', button.dataset.id || '');
        try {
          const response = await fetch('/My/delTicket', {
            method: 'POST',
            headers: {'Accept': 'application/json'},
            body: formData
          });
          const payload = await response.json().catch(() => null);
          if (payload && Number(payload.code) === 200) {
            ticketStatus.textContent = payload.message || '工单删除成功。';
            window.setTimeout(() => window.location.reload(), 450);
            return;
          }
          ticketStatus.classList.add('error');
          ticketStatus.textContent = payload && payload.message ? payload.message : '工单删除失败。';
        } catch (error) {
          ticketStatus.classList.add('error');
          ticketStatus.textContent = '工单删除失败，请稍后重试。';
        }
      });
    }
  </script>
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
        $row = Db::table('ypay_order')
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
            'account_count' => (int)Db::table('ypay_account')->where('user_id', $merchantId)->count(),
            'upstream_count' => (int)Db::table('ypay_paylist')->where('user_id', $merchantId)->where('status', 1)->count(),
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

        $balance = (float)(Db::table('ypay_user')
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
            $merchantRow = Db::table('ypay_user')
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

            Db::table('ypay_user')
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
