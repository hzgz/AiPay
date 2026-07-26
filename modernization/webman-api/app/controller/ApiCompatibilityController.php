<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\controller;

use app\support\ApiResponse;
use app\support\AdminSmtpMailer;
use app\support\BusinessTable;
use app\support\Environment;
use app\support\GoogleAuthenticator;
use app\support\LegacyApiCompatState;
use app\support\LegacyPassword;
use app\support\MerchantSmsCodeSender;
use app\support\RequestRateLimiter;
use app\support\SystemConfig;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class ApiCompatibilityController
{
    private const CODE_TTL = 300;

    public function getSoftwareConfig(Request $request): Response
    {
        $config = SystemConfig::all();
        $data = [
            'name' => $this->softwareName($config),
            'login_type' => $this->configInt($config, 'logincode-type', 0),
            'register_type' => $this->configInt($config, 'regcode-type', 0),
            'retrieve_type' => $this->configInt($config, 'retrieve-type', 0),
            'captcha_type' => $this->configInt($config, 'captcha-type', 0),
            'merchant_login_drag_verify' => $this->configInt($config, 'merchant_login_drag_verify', 1),
            'merchant_register_drag_verify' => $this->configInt($config, 'merchant_register_drag_verify', 1),
            'merchant_retrieve_drag_verify' => $this->configInt($config, 'merchant_retrieve_drag_verify', 1),
        ];

        return $this->monitorResponse(200, '软件配置获取成功', $data, [
            'summary' => [
                'site_name' => trim((string)($config['sitename'] ?? 'AiPay')),
                'software_name' => $data['name'],
                'read_only' => false,
            ],
            'migration_guard' => [
                'read_only' => false,
                'blocked_actions' => [],
            ],
            'security' => $this->legacyReadOnlySecurityContext('getSoftwareConfig', false),
        ]);
    }

    public function findOrder(Request $request): Response
    {
        $routePolicy = $this->findOrderRoutePolicy();
        $migrationGuard = [
            'read_only' => true,
            'blocked_actions' => ['order_update', 'callback_replay', 'status_reset'],
        ];
        $security = $this->legacyReadOnlySecurityContext('findorder', true);

        $orderNo = trim((string)$request->input('order_no', ''));
        if ($orderNo === '') {
            return $this->monitorResponse(201, 'order_no 不能为空', [], [
                'route_policy' => $routePolicy,
                'migration_guard' => $migrationGuard,
                'security' => $security,
            ]);
        }

        $typeInput = trim((string)$request->input('type', ''));
        if ($typeInput === '') {
            return $this->monitorResponse(201, 'type 不能为空', [], [
                'route_policy' => $routePolicy,
                'migration_guard' => $migrationGuard,
                'security' => $security,
            ]);
        }

        $type = (int)$typeInput;
        [$order, $matchedField] = $this->findLegacyOrder($orderNo, $type);
        if ($order === null || $matchedField === null) {
            return $this->monitorResponse(201, '订单不存在', [], [
                'route_policy' => $routePolicy,
                'migration_guard' => $migrationGuard,
                'security' => $security,
            ]);
        }

        $data = [
            'id' => (int)($order['user_id'] ?? 0),
            'merchant_id' => (int)($order['user_id'] ?? 0),
            'type' => trim((string)($order['type'] ?? '')),
            'trade_no' => trim((string)($order['trade_no'] ?? '')),
            'out_trade_no' => trim((string)($order['out_trade_no'] ?? '')),
            'name' => trim((string)($order['name'] ?? '')),
            'money' => (string)($order['money'] ?? '0.00'),
            'truemoney' => (string)($order['truemoney'] ?? ($order['money'] ?? '0.00')),
            'status' => (int)($order['status'] ?? 0),
            'notify_url' => trim((string)($order['notify_url'] ?? '')),
            'return_url' => trim((string)($order['return_url'] ?? '')),
        ];

        return $this->monitorResponse(200, '订单查询成功', $data, [
            'lookup' => [
                'requested_type' => $type,
                'order_no' => $orderNo,
                'matched_field' => $matchedField,
                'read_only' => true,
            ],
            'route_policy' => $routePolicy,
            'migration_guard' => $migrationGuard,
            'security' => $security,
        ]);
    }

    public function login(Request $request): Response
    {
        $payload = $this->payload($request);
        $authThrottleError = $this->checkPublicAuthThrottle('login', $request);
        if ($authThrottleError instanceof Response) {
            return $authThrottleError;
        }

        $config = SystemConfig::all();
        $captchaError = $this->validateImageCaptcha($payload, $config, $request);
        if ($captchaError instanceof Response) {
            return $captchaError;
        }

        $loginType = $this->configInt($config, 'logincode-type', 0);
        $user = null;

        switch ($loginType) {
            case 0:
                $username = trim((string)($payload['username'] ?? ''));
                $password = trim((string)($payload['password'] ?? ''));
                if ($username === '' || $password === '') {
                    return $this->monitorResponse(201, '用户名和密码不能为空');
                }

                $user = Db::table(BusinessTable::user())
                    ->where('username', $username)
                    ->where('password', LegacyPassword::hash($password))
                    ->first();
                if (!$user) {
                    return $this->monitorResponse(201, '用户名/密码错误');
                }
                break;

            case 1:
                $mobile = trim((string)($payload['mobile'] ?? ''));
                $code = trim((string)($payload['captcha'] ?? ''));
                if ($mobile === '') {
                    return $this->monitorResponse(201, '手机号不能为空');
                }
                if ($code === '') {
                    return $this->monitorResponse(201, '验证码不能为空');
                }
                if (!$this->validateVerificationCode('login', 'mobile', $mobile, $code)) {
                    return $this->monitorResponse(201, '验证码错误');
                }

                $user = Db::table(BusinessTable::user())->where('mobile', $mobile)->first();
                if (!$user) {
                    return $this->monitorResponse(201, '该手机号不存在');
                }
                break;

            case 2:
                $email = trim((string)($payload['email'] ?? ''));
                $code = trim((string)($payload['captcha'] ?? ''));
                if ($email === '') {
                    return $this->monitorResponse(201, '邮箱不能为空');
                }
                if ($code === '') {
                    return $this->monitorResponse(201, '验证码不能为空');
                }
                if (!$this->validateVerificationCode('login', 'email', $email, $code)) {
                    return $this->monitorResponse(201, '验证码错误');
                }

                $user = Db::table(BusinessTable::user())->where('email', $email)->first();
                if (!$user) {
                    return $this->monitorResponse(201, '该邮箱不存在');
                }
                break;

            case 4:
                $telegramId = trim((string)($payload['tg_chat_id'] ?? ''));
                $code = trim((string)($payload['captcha'] ?? ''));
                if ($telegramId === '') {
                    return $this->monitorResponse(201, 'Telegram Chat ID不能为空');
                }
                if ($code === '') {
                    return $this->monitorResponse(201, '验证码不能为空');
                }
                if (!$this->validateVerificationCode('login', 'tg', $telegramId, $code)) {
                    return $this->monitorResponse(201, '验证码错误');
                }

                $user = Db::table(BusinessTable::user())->where('tg_chat_id', $telegramId)->first();
                if (!$user) {
                    return $this->monitorResponse(201, '该TG账号不存在');
                }
                break;

            default:
                return $this->monitorResponse(201, '当前登录方式暂不支持软件公开 API');
        }

        $merchant = (array)$user;
        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            $reason = trim((string)($merchant['frozen_reason'] ?? '商户账户已冻结'));
            return $this->monitorResponse(201, $reason !== '' ? $reason : '商户账户已冻结');
        }

        $googleError = $this->validateGoogleLogin($merchant, $payload, $config);
        if ($googleError instanceof Response) {
            return $googleError;
        }

        $token = $this->rotateMerchantToken((int)($merchant['id'] ?? 0));
        $this->recordFrontLog(
            (int)($merchant['id'] ?? 0),
            '/Api/login',
            0,
            '商户登录成功',
            $request
        );

        return $this->monitorResponse(200, '登录成功', [
            'id' => (int)($merchant['id'] ?? 0),
            'token' => $token,
        ]);
    }

    public function register(Request $request): Response
    {
        $payload = $this->payload($request);
        $authThrottleError = $this->checkPublicAuthThrottle('register', $request);
        if ($authThrottleError instanceof Response) {
            return $authThrottleError;
        }

        $config = SystemConfig::all();

        if ($this->configInt($config, 'is_reg', 0) !== 1) {
            return $this->monitorResponse(201, '注册功能已关闭!');
        }

        $captchaError = $this->validateImageCaptcha($payload, $config, $request);
        if ($captchaError instanceof Response) {
            return $captchaError;
        }

        $username = trim((string)($payload['username'] ?? ''));
        $password = trim((string)($payload['password'] ?? ''));
        $password2 = trim((string)($payload['password2'] ?? ''));
        $email = $this->nullableInput($payload['email'] ?? null);
        $mobile = $this->nullableInput($payload['mobile'] ?? null);
        $telegramId = $this->nullableInput($payload['tg_chat_id'] ?? null);

        if ($username === '') {
            return $this->monitorResponse(201, '用户名不能为空');
        }
        if (mb_strlen($username) > 50) {
            return $this->monitorResponse(201, '用户名长度不能超过50位');
        }
        if ($password === '') {
            return $this->monitorResponse(201, '密码不能为空');
        }
        if (mb_strlen($password) > 50) {
            return $this->monitorResponse(201, '密码长度不能超过50位');
        }
        if ($password2 !== '' && $password !== $password2) {
            return $this->monitorResponse(201, '两次输入的密码不一致');
        }

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->monitorResponse(201, '邮箱格式错误');
        }
        if ($mobile !== null && !preg_match('/^1\d{10}$/', $mobile)) {
            return $this->monitorResponse(201, '手机号格式错误');
        }
        if ($telegramId !== null && !preg_match('/^-?[0-9]{5,32}$/', $telegramId)) {
            return $this->monitorResponse(201, 'Telegram Chat ID格式错误');
        }

        if ($this->merchantFieldExists('username', $username)) {
            return $this->monitorResponse(201, '用户名已存在');
        }
        if ($email !== null && $this->merchantFieldExists('email', $email)) {
            return $this->monitorResponse(201, '邮箱已存在');
        }
        if ($mobile !== null && $this->merchantFieldExists('mobile', $mobile)) {
            return $this->monitorResponse(201, '手机号已存在');
        }
        if ($telegramId !== null && $this->merchantFieldExists('tg_chat_id', $telegramId)) {
            return $this->monitorResponse(201, '该TG账号已存在!');
        }

        $registerType = $this->configInt($config, 'regcode-type', 0);
        if ($registerType !== 0) {
            $code = trim((string)($payload['captcha'] ?? ''));
            if ($code === '') {
                return $this->monitorResponse(201, '验证码不能为空');
            }

            [$channel, $target, $targetError] = $this->resolveVerificationTargetForRegister($registerType, $email, $mobile, $telegramId);
            if ($targetError !== null) {
                return $targetError;
            }

            if (!$this->validateVerificationCode('register', $channel, $target, $code)) {
                return $this->monitorResponse(201, '验证码错误');
            }
        }

        $registerData = [
            'username' => htmlspecialchars($username, ENT_QUOTES, 'UTF-8'),
            'password' => LegacyPassword::hash($password),
            'email' => $email,
            'mobile' => $mobile,
            'tg_chat_id' => $telegramId,
            'money' => '0.00',
            'user_key' => $this->generateSecret(32),
            'create_time' => date('Y-m-d H:i:s'),
            'is_frozen' => 0,
            'remarks' => null,
            'superior_id' => $this->normalizeSuperiorId($payload),
        ];

        $registerData = $this->applyRegistrationGifts($registerData, $config);

        if ($this->configInt($config, 'paid_reg', 0) === 1 && (float)($config['paid_reg_price'] ?? 0) > 0) {
            $paymentMethods = $this->paidRegistrationMethods($config);
            if ($paymentMethods === []) {
                return $this->monitorResponse(201, '无收款通道');
            }

            $tradeNo = $this->generateTradeNo('Y');
            $now = date('Y-m-d H:i:s');
            Db::table(BusinessTable::recharge())->insert([
                'type' => 'default',
                'rtype' => 1,
                'out_trade_no' => $tradeNo,
                'user_id' => 0,
                'money' => number_format((float)$config['paid_reg_price'], 2, '.', ''),
                'qrcode' => '',
                'status' => 0,
                'regdata' => json_encode($registerData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'create_time' => $now,
                'end_time' => $now,
                'update_time' => $now,
                'out_time' => 0,
            ]);

            return $this->monitorResponse(888, '请先完成付费注册', [
                'paytype' => $paymentMethods,
                'need' => number_format((float)$config['paid_reg_price'], 2, '.', ''),
                'trade_no' => $tradeNo,
            ]);
        }

        try {
            Db::transaction(function () use ($config, $registerData): void {
                $this->applyCustomMerchantAutoIncrement($config);

                $userId = (int)Db::table(BusinessTable::user())->insertGetId($registerData);

                Db::table(BusinessTable::userBasic())->insert([
                    'user_id' => $userId,
                    'timeout_method' => 2,
                    'timeout_url' => '/',
                    'timeout_time' => '180',
                    'loginfailure' => 0,
                    'appkey' => $this->generateSecret(32),
                    'order_tips' => 'close',
                    'is_money_tips' => 'close',
                    'money_tips' => '0',
                    'is_rate' => 0,
                    'callback_hiddenName' => 0,
                ]);
            });
        } catch (\Throwable $exception) {
            return $this->monitorResponse(201, '操作失败' . $exception->getMessage());
        }

        $this->recordFrontLog(0, '/Api/register', 2, '软件公开 API 注册成功', $request);

        return $this->monitorResponse(200, '注册成功');
    }

    public function getCode(Request $request): Response
    {
        $payload = $this->payload($request);
        $purpose = trim((string)($payload['type'] ?? ''));
        if (!in_array($purpose, ['login', 'register', 'retrieve'], true)) {
            return $this->monitorResponse(400, '无效的验证码类型');
        }

        $config = SystemConfig::all();
        [$channel, $target, $title, $targetError] = $this->resolveVerificationDelivery($purpose, $payload, $config);
        if ($targetError instanceof Response) {
            return $targetError;
        }

        $ip = trim((string)$request->getRealIp());
        $throttleError = $this->checkVerificationThrottle($purpose, $ip);
        if ($throttleError instanceof Response) {
            return $throttleError;
        }

        $code = (string)random_int(100000, 999999);

        try {
            switch ($channel) {
                case 'mobile':
                    (new MerchantSmsCodeSender())->sendCode($target, $code, $config);
                    break;

                case 'tg':
                    $this->sendTelegramCode($target, $code, $title, $config);
                    break;

                case 'email':
                default:
                    $content = sprintf(
                        '<p>您的验证码为 <strong style="font-size:20px;">%s</strong></p><p>5分钟内有效，请勿泄露。</p>',
                        htmlspecialchars($code, ENT_QUOTES, 'UTF-8')
                    );
                    (new AdminSmtpMailer())->sendHtml($target, $title, $content, $config);
                    break;
            }
        } catch (\Throwable $exception) {
            return $this->monitorResponse(201, $this->deliveryErrorMessage($channel, $exception));
        }

        LegacyApiCompatState::storeVerificationCode($purpose, $channel, $target, $code, self::CODE_TTL);
        $this->recordVerificationLog($purpose, $request);

        return $this->monitorResponse(200, '发送成功!');
    }

    public function getCaptcha(Request $request): Response
    {
        $code = $this->randomCaptchaCode();
        LegacyApiCompatState::storeImageCaptchaForScope(
            LegacyApiCompatState::captchaScopeFromRequest($request),
            $code,
            self::CODE_TTL
        );
        $svg = LegacyApiCompatState::renderCaptchaSvg($code);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function retrievePassword(Request $request): Response
    {
        $payload = $this->payload($request);
        $authThrottleError = $this->checkPublicAuthThrottle('retrieve', $request);
        if ($authThrottleError instanceof Response) {
            return $authThrottleError;
        }

        $config = SystemConfig::all();
        $retrieveType = $this->configInt($config, 'retrieve-type', 0);

        if ($retrieveType === 0) {
            return $this->monitorResponse(201, '找回密码功能未开启');
        }

        $captchaError = $this->validateImageCaptcha($payload, $config, $request);
        if ($captchaError instanceof Response) {
            return $captchaError;
        }

        $username = trim((string)($payload['username'] ?? ''));
        $password = trim((string)($payload['password'] ?? ''));
        $password2 = trim((string)($payload['password2'] ?? ''));
        $email = $this->nullableInput($payload['email'] ?? null);
        $mobile = $this->nullableInput($payload['mobile'] ?? null);
        $telegramId = $this->nullableInput($payload['tg_chat_id'] ?? null);
        $code = trim((string)($payload['captcha'] ?? ''));

        if ($username === '') {
            return $this->monitorResponse(201, '商户账号不能为空');
        }
        if ($password === '') {
            return $this->monitorResponse(201, '新密码不能为空');
        }
        if (mb_strlen($password) < 6) {
            return $this->monitorResponse(201, '新密码至少 6 位');
        }
        if (mb_strlen($password) > 50) {
            return $this->monitorResponse(201, '新密码长度不能超过50位');
        }
        if ($password !== $password2) {
            return $this->monitorResponse(201, '两次输入的密码不一致');
        }
        if ($code === '') {
            return $this->monitorResponse(201, '验证码不能为空');
        }

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->monitorResponse(201, '邮箱格式错误');
        }
        if ($mobile !== null && !preg_match('/^1\d{10}$/', $mobile)) {
            return $this->monitorResponse(201, '手机号格式错误');
        }
        if ($telegramId !== null && !preg_match('/^-?[0-9]{5,32}$/', $telegramId)) {
            return $this->monitorResponse(201, 'Telegram Chat ID格式错误');
        }

        [$channel, $target, $targetError] = $this->resolveVerificationTargetForRetrieve(
            $retrieveType,
            $email,
            $mobile,
            $telegramId
        );
        if ($targetError instanceof Response) {
            return $targetError;
        }

        if (!$this->validateVerificationCode('retrieve', $channel, $target, $code)) {
            return $this->monitorResponse(201, '验证码错误');
        }

        $user = Db::table(BusinessTable::user())
            ->where('username', $username)
            ->where(match ($channel) {
                'mobile' => 'mobile',
                'tg' => 'tg_chat_id',
                default => 'email',
            }, $target)
            ->first();

        if (!$user) {
            return $this->monitorResponse(201, '商户账号与找回信息不匹配');
        }

        $merchant = (array)$user;
        $newToken = $this->generateSecret(48);

        Db::table(BusinessTable::user())
            ->where('id', (int)($merchant['id'] ?? 0))
            ->update([
                'password' => LegacyPassword::hash($password),
                'token' => $newToken,
            ]);

        $this->recordFrontLog(
            (int)($merchant['id'] ?? 0),
            '/Api/retrievePassword',
            2,
            '商户找回密码成功',
            $request
        );

        return $this->monitorResponse(200, '密码重置成功，请使用新密码登录');
    }

    private function softwareName(array $config): string
    {
        $name = trim((string)($config['software_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $siteName = trim((string)($config['sitename'] ?? ''));
        return $siteName !== '' ? $siteName : 'AiPay';
    }

    private function configInt(array $config, string $key, int $default): int
    {
        $value = $config[$key] ?? $default;
        return is_numeric($value) ? (int)$value : $default;
    }

    private function payload(Request $request): array
    {
        $payload = [];
        foreach ((array)$request->all() as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $payload[$key] = $value;
                continue;
            }

            $payload[$key] = trim((string)$value);
        }

        return $payload;
    }

    private function validateImageCaptcha(array $payload, array $config, Request $request): ?Response
    {
        $captchaType = $this->configInt($config, 'captcha-type', 0);
        if ($captchaType > 1) {
            $captchaType = 1;
        }

        if ($captchaType === 0) {
            return null;
        }

        $captcha = trim((string)($payload['ordinary_captcha'] ?? ''));
        if ($captcha === '') {
            return $this->monitorResponse(201, '请输入正确的验证码');
        }

        $scope = LegacyApiCompatState::captchaScopeFromRequest($request);
        if (!LegacyApiCompatState::verifyImageCaptchaForScope($scope, $captcha)) {
            return $this->monitorResponse(201, '验证码错误');
        }

        return null;
    }

    private function validateGoogleLogin(array $merchant, array $payload, array $config): ?Response
    {
        if ($this->configInt($config, 'isSecurity', 0) !== 1 || $this->configInt($config, 'isSecurityLogin', 0) !== 1) {
            return null;
        }

        $googleSecret = trim((string)($merchant['googlekey'] ?? ''));
        if ($googleSecret === '') {
            return null;
        }

        $securityCode = trim((string)($payload['securityCode'] ?? $payload['security_code'] ?? ''));
        if ($securityCode === '') {
            return $this->monitorResponse(201, '安全验证码错误');
        }

        $google = new GoogleAuthenticator();
        if (!$google->verifyCode($googleSecret, $securityCode, 4)) {
            return $this->monitorResponse(201, '安全验证码错误');
        }

        return null;
    }

    private function rotateMerchantToken(int $merchantId): string
    {
        if ($merchantId <= 0) {
            throw new \InvalidArgumentException('商户编号不能为空');
        }

        $token = $this->generateSecret(32) . $merchantId . substr(str_replace('.', '', (string)microtime(true)), -8);
        Db::table(BusinessTable::user())
            ->where('id', $merchantId)
            ->update(['token' => $token]);

        return $token;
    }

    private function generateSecret(int $length = 32): string
    {
        return substr(bin2hex(random_bytes(max(16, (int)ceil($length / 2)))), 0, $length);
    }

    private function merchantFieldExists(string $field, string $value): bool
    {
        return Db::table(BusinessTable::user())->where($field, $value)->exists();
    }

    private function nullableInput(mixed $value): ?string
    {
        if (is_array($value) || is_object($value)) {
            return null;
        }

        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }

    private function normalizeSuperiorId(array $payload): ?int
    {
        $value = $payload['superior_id'] ?? $payload['aff_id'] ?? null;
        if (!is_scalar($value) || !is_numeric((string)$value)) {
            return null;
        }

        $superiorId = (int)$value;
        return $superiorId > 0 ? $superiorId : null;
    }

    private function applyRegistrationGifts(array $registerData, array $config): array
    {
        if ($this->configInt($config, 'is_reg_give_vip', 0) === 1) {
            $vipId = $this->configInt($config, 'reg_give_vip', 0);
            if ($vipId > 0) {
                $vip = Db::table(BusinessTable::vip())
                    ->select('id', 'viptime', 'feilv', 'status')
                    ->where('id', $vipId)
                    ->first();
                if ($vip) {
                    $vipRow = (array)$vip;
                    $registerData['vip_id'] = (int)($vipRow['id'] ?? 0);
                    $registerData['vip_time'] = date(
                        'Y-m-d H:i:s',
                        strtotime('+ ' . max(0, (int)($vipRow['viptime'] ?? 0)) . ' day')
                    );
                    $registerData['feilv'] = (string)($vipRow['feilv'] ?? '0');
                }
            }
        }

        if ($this->configInt($config, 'is_reg_give_price', 0) === 1) {
            $registerData['money'] = number_format((float)($config['reg_give_price'] ?? 0), 2, '.', '');
        }

        return $registerData;
    }

    private function applyCustomMerchantAutoIncrement(array $config): void
    {
        if (($config['is_diyUserId'] ?? '0') !== '1') {
            return;
        }

        $target = max(0, (int)($config['diy_userId'] ?? 0));
        if ($target <= 0) {
            return;
        }

        $nextId = ((int)(Db::table(BusinessTable::user())->max('id') ?? 0)) + 1;
        if ($nextId >= $target) {
            return;
        }

        Db::statement('ALTER TABLE ' . BusinessTable::user() . ' AUTO_INCREMENT = ' . $target);
    }

    private function paidRegistrationMethods(array $config): array
    {
        $methods = [];
        if ($this->configInt($config, 'alipay', 0) === 1) {
            $methods[] = ['name' => 'alipay', 'showname' => '支付宝'];
        }
        if ($this->configInt($config, 'wechat', 0) === 1) {
            $methods[] = ['name' => 'wxpay', 'showname' => '微信'];
        }

        return $methods;
    }

    private function resolveVerificationTargetForRegister(
        int $registerType,
        ?string $email,
        ?string $mobile,
        ?string $telegramId
    ): array {
        return match ($registerType) {
            1 => $mobile !== null
                ? ['mobile', $mobile, null]
                : [null, null, $this->monitorResponse(201, '手机号不能为空')],
            3 => $telegramId !== null
                ? ['tg', $telegramId, null]
                : [null, null, $this->monitorResponse(201, 'Telegram Chat ID不能为空')],
            default => $email !== null
                ? ['email', $email, null]
                : [null, null, $this->monitorResponse(201, '邮箱不能为空')],
        };
    }

    private function resolveVerificationTargetForRetrieve(
        int $retrieveType,
        ?string $email,
        ?string $mobile,
        ?string $telegramId
    ): array {
        return match ($retrieveType) {
            1 => $mobile !== null
                ? ['mobile', $mobile, null]
                : [null, null, $this->monitorResponse(201, '手机号不能为空')],
            3 => $telegramId !== null
                ? ['tg', $telegramId, null]
                : [null, null, $this->monitorResponse(201, 'Telegram Chat ID不能为空')],
            default => $email !== null
                ? ['email', $email, null]
                : [null, null, $this->monitorResponse(201, '邮箱不能为空')],
        };
    }

    private function resolveVerificationDelivery(string $purpose, array $payload, array $config): array
    {
        $mobile = $this->nullableInput($payload['mobile'] ?? null);
        $email = $this->nullableInput($payload['email'] ?? null);
        $telegramId = $this->nullableInput($payload['tg_chat_id'] ?? null);

        if ($purpose === 'login') {
            $loginType = $this->configInt($config, 'logincode-type', 0);

            return match ($loginType) {
                1 => $mobile !== null
                    ? ['mobile', $mobile, '平台登录验证码', null]
                    : [null, null, null, $this->monitorResponse(201, '手机号不能为空')],
                4 => $telegramId !== null
                    ? ['tg', $telegramId, '平台登录验证码', null]
                    : [null, null, null, $this->monitorResponse(201, 'Telegram Chat ID不能为空')],
                0 => [null, null, null, $this->monitorResponse(201, '当前登录方式无需发送验证码')],
                default => $email !== null
                    ? ['email', $email, '平台登录验证码', null]
                    : [null, null, null, $this->monitorResponse(201, '邮箱不能为空')],
            };
        }

        if ($purpose === 'register') {
            $registerType = $this->configInt($config, 'regcode-type', 0);

            return match ($registerType) {
                1 => $mobile !== null
                    ? ['mobile', $mobile, '平台注册验证码', null]
                    : [null, null, null, $this->monitorResponse(201, '手机号不能为空')],
                3 => $telegramId !== null
                    ? ['tg', $telegramId, '平台注册验证码', null]
                    : [null, null, null, $this->monitorResponse(201, 'Telegram Chat ID不能为空')],
                0 => [null, null, null, $this->monitorResponse(201, '当前注册方式无需发送验证码')],
                default => $email !== null
                    ? ['email', $email, '平台注册验证码', null]
                    : [null, null, null, $this->monitorResponse(201, '邮箱不能为空')],
            };
        }

        $retrieveType = $this->configInt($config, 'retrieve-type', 0);
        return match ($retrieveType) {
            1 => $mobile !== null
                ? ['mobile', $mobile, '平台找回验证码', null]
                : [null, null, null, $this->monitorResponse(201, '手机号不能为空')],
            3 => $telegramId !== null
                ? ['tg', $telegramId, '平台找回验证码', null]
                : [null, null, null, $this->monitorResponse(201, 'Telegram Chat ID不能为空')],
            default => $email !== null
                ? ['email', $email, '平台找回验证码', null]
                : [null, null, null, $this->monitorResponse(201, '邮箱不能为空')],
        };
    }

    private function validateVerificationCode(string $purpose, string $channel, string $target, string $code): bool
    {
        return LegacyApiCompatState::verifyVerificationCode($code, $purpose, $channel, $target);
    }

    private function checkVerificationThrottle(string $purpose, string $ip): ?Response
    {
        $freqMessage = match ($purpose) {
            'register' => '注册验证码之间需要相隔60秒！',
            'retrieve' => '两次发送验证码之间需要相隔60秒！',
            default => '两次发送验证码之间需要相隔60秒！',
        };

        $limitMessage = match ($purpose) {
            'register' => '今日注册验证码发送次数已达上限',
            'retrieve' => '今日找回密码验证码发送次数已达上限',
            default => '今日登录验证码发送次数已达上限',
        };

        $cooldownSeconds = max(1, Environment::int('COMPAT_CODE_COOLDOWN_SECONDS', 60));
        $cooldown = RequestRateLimiter::attempt(
            'compat:code:' . $purpose . ':cooldown:' . $ip,
            1,
            $cooldownSeconds
        );
        if (!$cooldown['allowed']) {
            return $this->monitorResponse(201, $freqMessage);
        }

        $dailyLimit = max(1, $this->configInt(SystemConfig::all(), 'daily_limit', 10));
        $dailyWindow = max(60, strtotime('tomorrow') - time());
        $daily = RequestRateLimiter::attempt(
            'compat:code:' . $purpose . ':daily:' . date('Ymd') . ':' . $ip,
            $dailyLimit,
            $dailyWindow
        );

        if (!$daily['allowed']) {
            return $this->monitorResponse(201, $limitMessage);
        }

        return null;
    }

    private function checkPublicAuthThrottle(string $action, Request $request): ?Response
    {
        $maxAttempts = Environment::int('PUBLIC_AUTH_RATE_LIMIT_MAX', 30);
        $windowSeconds = Environment::int('PUBLIC_AUTH_RATE_LIMIT_WINDOW', 60);
        if ($maxAttempts <= 0 || $windowSeconds <= 0) {
            return null;
        }

        $ip = trim((string)$request->getRealIp());
        if ($ip === '') {
            return null;
        }

        $result = RequestRateLimiter::attempt(
            'compat:auth:' . strtolower(trim($action)) . ':' . $ip,
            $maxAttempts,
            $windowSeconds
        );
        if ($result['allowed']) {
            return null;
        }

        return $this->monitorResponse(429, '请求过于频繁，请稍后再试');
    }

    private function recordVerificationLog(string $purpose, Request $request): void
    {
        $url = match ($purpose) {
            'register' => '/api/compat/code/register',
            'retrieve' => '/api/compat/code/retrieve',
            default => '/api/compat/code/login',
        };

        $this->recordFrontLog(0, $url, 2, '发送验证码', $request);
    }

    private function recordFrontLog(int $uid, string $url, int $type, string $desc, Request $request): void
    {
        Db::table('admin_front_log')->insert([
            'uid' => $uid,
            'url' => $url,
            'type' => $type,
            'desc' => $desc,
            'ip' => trim((string)$request->getRealIp()),
            'user_agent' => trim((string)$request->header('user-agent', '')),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function deliveryErrorMessage(string $channel, \Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        if ($message !== '') {
            return $message;
        }

        return match ($channel) {
            'mobile' => '短信验证码发送失败',
            'tg' => 'Telegram验证码发送失败',
            default => '邮箱验证码发送失败',
        };
    }

    private function sendTelegramCode(string $chatId, string $code, string $title, array $config): void
    {
        if (trim((string)($config['tg_switch'] ?? '0')) !== '1') {
            throw new \InvalidArgumentException('TG服务未开启');
        }

        $botToken = trim((string)($config['tg_bot_token'] ?? ''));
        if ($botToken === '') {
            throw new \InvalidArgumentException('TG机器人Token未配置');
        }

        if (!preg_match('/^-?[0-9]{5,32}$/', $chatId)) {
            throw new \InvalidArgumentException('Telegram Chat ID格式错误');
        }

        $payload = json_encode([
            'chat_id' => $chatId,
            'text' => '<b>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</b>' . "\n"
                . '验证码: <code>' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</code>' . "\n"
                . '5分钟内有效，请勿泄露。',
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init('https://api.telegram.org/bot' . $botToken . '/sendMessage');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException('Telegram 请求失败: ' . $curlError);
        }

        $decoded = json_decode((string)$body, true);
        if (!is_array($decoded) || empty($decoded['ok'])) {
            $errorMessage = is_array($decoded) ? trim((string)($decoded['description'] ?? 'Telegram 发送失败')) : 'Telegram 返回格式异常';
            throw new \RuntimeException($errorMessage);
        }
    }

    private function randomCaptchaCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $result = '';
        for ($i = 0; $i < 4; $i++) {
            $result .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $result;
    }

    private function generateTradeNo(string $prefix = 'Y'): string
    {
        return $prefix . date('YmdHis') . random_int(11111, 99999);
    }

    private function findLegacyOrder(string $orderNo, int $type): array
    {
        $fields = $type === 1
            ? ['trade_no', 'out_trade_no']
            : ['out_trade_no', 'trade_no'];

        foreach ($fields as $field) {
            $row = Db::table(BusinessTable::order())
                ->select(
                    'id',
                    'user_id',
                    'name',
                    'type',
                    'trade_no',
                    'out_trade_no',
                    'money',
                    'truemoney',
                    'status',
                    'notify_url',
                    'return_url'
                )
                ->where($field, $orderNo)
                ->orderByDesc('id')
                ->first();

            if ($row) {
                return [(array)$row, $field];
            }
        }

        return [null, null];
    }

    private function legacyReadOnlySecurityContext(string $endpoint, bool $returnsOrderData): array
    {
        return [
            'scope' => 'legacy_public_read',
            'endpoint' => $endpoint,
            'signature_required' => false,
            'replay_protection' => [
                'strategy' => 'not_applicable',
                'window_seconds' => null,
            ],
            'write_guard' => [
                'enabled' => true,
                'returns_order_data' => $returnsOrderData,
            ],
        ];
    }

    private function findOrderRoutePolicy(): array
    {
        return [
            'strategy' => 'legacy_findorder_kept_online_read_only',
            'status' => 'active',
            'alias_entries' => ['/api/findorder'],
            'allowed_methods' => ['GET', 'POST'],
            'write_policy' => 'read_only_lookup_only',
            'blocked_actions' => ['order_update', 'callback_replay', 'status_reset'],
        ];
    }

    private function monitorResponse(int $code, string $message, array $data = [], array $extra = []): Response
    {
        $message = ApiResponse::normalizeText($message);

        $payload = array_merge([
            'code' => $code,
            'message' => $message,
            'msg' => $message,
            'data' => $data,
            'redirect' => '',
        ], $extra);

        $payload = $this->stripCompatibilityMeta($payload);

        return json($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function stripCompatibilityMeta(array $payload): array
    {
        foreach (['route_policy', 'migration_guard', 'legacy_url', 'legacy_routes', 'legacy_page', 'legacy_endpoint', 'legacy_action_label'] as $key) {
            unset($payload[$key]);
        }

        return $payload;
    }
}
