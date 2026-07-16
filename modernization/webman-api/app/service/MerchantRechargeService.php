<?php

declare(strict_types=1);

namespace app\service;

use app\support\LegacyPaymentSdkAutoloader;
use app\support\RequestPayload;
use app\support\SystemConfig;
use RuntimeException;
use support\Db;
use Webman\Http\Request;

class MerchantRechargeService
{
    private const SUPPORTED_METHODS = [
        'alipay' => ['id' => 'alipay', 'label' => '支付宝', 'config_key' => 'alipay'],
        'wxpay' => ['id' => 'wxpay', 'label' => '微信支付', 'config_key' => 'wechat'],
        'qqpay' => ['id' => 'qqpay', 'label' => 'QQ支付', 'config_key' => 'qqpay'],
    ];

    public function catalog(): array
    {
        $config = SystemConfig::all();
        $enabledList = array_filter(array_map('trim', explode(',', strtolower((string)($config['diy_recharge'] ?? 'qqpay,wxpay,alipay')))));

        $methods = [];
        foreach (self::SUPPORTED_METHODS as $method) {
            $paylist = $this->configuredPaylist((string)$method['config_key'], $config);
            $enabled = in_array((string)$method['id'], $enabledList, true) && $paylist !== null;
            $methods[] = [
                'id' => (string)$method['id'],
                'label' => (string)$method['label'],
                'enabled' => $enabled,
                'paylist_id' => (int)($paylist['id'] ?? 0),
                'paylist_type' => trim((string)($paylist['type'] ?? '')),
                'paylist_name' => trim((string)($paylist['name'] ?? '')),
                'description' => $enabled
                    ? ('已接入支付通道 #' . (int)($paylist['id'] ?? 0) . '（' . trim((string)($paylist['type'] ?? '')) . '）')
                    : '因全局充值映射或上游支付通道缺失，当前方式不可用。',
            ];
        }

        return [
            'min_recharge' => $this->minRecharge($config),
            'max_recharge' => $this->maxRecharge($config),
            'methods' => $methods,
            'enabled_count' => count(array_filter($methods, static fn (array $item): bool => !empty($item['enabled']))),
        ];
    }

    public function createRecharge(Request $request, array $merchant): array
    {
        $payload = RequestPayload::all($request);
        if ($payload === []) {
            $payload = $request->post();
        }
        if (!is_array($payload)) {
            $payload = [];
        }

        $config = SystemConfig::all();
        $amountInput = trim((string)($payload['money'] ?? ''));
        $type = strtolower(trim((string)($payload['type'] ?? '')));

        if ($amountInput === '' || !is_numeric($amountInput)) {
            return $this->error('充值金额不能为空', 201, 422);
        }

        if (!array_key_exists($type, self::SUPPORTED_METHODS)) {
            return $this->error('充值方式无效', 201, 422);
        }

        $amount = round((float)$amountInput, 2);
        $minRecharge = $this->minRecharge($config);
        $maxRecharge = $this->maxRecharge($config);
        if ($amount < $minRecharge) {
            return $this->error('充值金额低于最低限额：' . number_format($minRecharge, 2, '.', ''), 201, 422);
        }
        if ($amount > $maxRecharge) {
            return $this->error('充值金额超过最高限额：' . number_format($maxRecharge, 2, '.', ''), 201, 422);
        }

        $catalog = $this->catalog();
        $selectedMethod = $this->catalogMethod($catalog, $type);
        if ($selectedMethod === null || empty($selectedMethod['enabled'])) {
            return $this->error('所选充值方式当前不可用', 201, 409);
        }

        $paylist = $this->configuredPaylist((string)($selectedMethod['config_key'] ?? self::SUPPORTED_METHODS[$type]['config_key']), $config);
        if ($paylist === null) {
            $paylist = $this->configuredPaylist((string)self::SUPPORTED_METHODS[$type]['config_key'], $config);
        }
        if ($paylist === null) {
            return $this->error('充值上游支付通道未配置', 201, 409);
        }

        $basic = $this->merchantBasic((int)($merchant['id'] ?? 0));
        $orderNo = $this->generateRechargeOrderNo($config);
        $now = date('Y-m-d H:i:s');
        $rechargeId = (int)Db::table('ypay_recharge')->insertGetId([
            'type' => $type,
            'rtype' => 0,
            'out_trade_no' => $orderNo,
            'user_id' => (int)($merchant['id'] ?? 0),
            'money' => number_format($amount, 2, '.', ''),
            'qrcode' => '',
            'status' => 0,
            'regdata' => null,
            'create_time' => $now,
            'end_time' => null,
            'update_time' => $now,
            'out_time' => 0,
        ]);

        try {
            $notifyUrl = $this->requestOrigin($request) . '/Notify/notify';
            $returnUrl = $this->requestOrigin($request) . '/Notify/return';
            $providerType = strtolower(trim((string)($paylist['type'] ?? '')));

            return match ($providerType) {
                'epay' => $this->createEpayRecharge($rechargeId, $orderNo, $amount, $type, $notifyUrl, $returnUrl, $paylist),
                'dmf' => $this->createQrRecharge($request, $rechargeId, $orderNo, $amount, $type, $notifyUrl, $returnUrl, $paylist, $basic, 'dmf'),
                'wxpay' => $this->createQrRecharge($request, $rechargeId, $orderNo, $amount, $type, $notifyUrl, $returnUrl, $paylist, $basic, 'wxpay'),
                'qqpay' => $this->createQrRecharge($request, $rechargeId, $orderNo, $amount, $type, $notifyUrl, $returnUrl, $paylist, $basic, 'qqpay'),
                'alipay' => $this->createAlipayWebRecharge($request, $rechargeId, $orderNo, $amount, $notifyUrl, $returnUrl, $paylist),
                default => $this->error('当前充值通道类型暂不支持：' . $providerType, 201, 409),
            };
        } catch (\Throwable $exception) {
            Db::table('ypay_recharge')->where('id', $rechargeId)->delete();
            return $this->error('创建充值订单失败：' . $exception->getMessage(), 201, 500);
        }
    }

    public function pollRecharge(string $outTradeNo, Request $request): array
    {
        $outTradeNo = trim($outTradeNo);
        if ($outTradeNo === '') {
            return [
                'code' => 0,
                'msg' => 'order_no_required',
                'message' => 'order_no_required',
            ];
        }

        $row = Db::table('ypay_recharge')
            ->select('id', 'out_trade_no', 'user_id', 'money', 'qrcode', 'status', 'out_time')
            ->where('out_trade_no', $outTradeNo)
            ->orderByDesc('id')
            ->first();

        if (!$row) {
            return [
                'code' => 0,
                'msg' => 'order_not_found',
                'message' => 'order_not_found',
            ];
        }

        $recharge = (array)$row;
        if ((int)($recharge['status'] ?? 0) === 1) {
            return [
                'code' => 200,
                'msg' => 'order_paid',
                'message' => 'order_paid',
                'url' => '/Deal/Recharge',
            ];
        }

        if ((int)($recharge['out_time'] ?? 0) > 0 && (int)($recharge['out_time'] ?? 0) < time()) {
            return [
                'code' => 0,
                'msg' => 'order_timeout',
                'message' => 'order_timeout',
            ];
        }

        $qrcode = trim((string)($recharge['qrcode'] ?? ''));
        if ($qrcode === '') {
            return [
                'code' => 0,
                'msg' => 'qrcode_missing',
                'message' => 'qrcode_missing',
            ];
        }

        return [
            'code' => 100,
            'msg' => 'qrcode_ready',
            'message' => 'qrcode_ready',
            'qr_url' => $this->buildQrCodeUrl($qrcode, $request, 350),
        ];
    }

    public function handleRechargeCallback(Request $request, string $mode): array
    {
        $payload = $this->callbackPayload($request);
        $config = SystemConfig::all();
        $candidates = $this->callbackCandidates($payload, $config);

        foreach ($candidates as $candidate) {
            $verified = $this->verifyCallback($candidate, $payload, $request);
            if (empty($verified['verified'])) {
                continue;
            }

            $outTradeNo = trim((string)($verified['out_trade_no'] ?? $payload['out_trade_no'] ?? ''));
            if ($outTradeNo === '') {
                return $mode === 'notify'
                    ? ['kind' => 'text', 'body' => 'fail']
                    : ['kind' => 'redirect', 'location' => '/Deal/Recharge'];
            }

            $this->settleRecharge($outTradeNo, $config);

            if ($mode === 'notify') {
                if (($verified['response_kind'] ?? '') === 'xml') {
                    return [
                        'kind' => 'xml',
                        'body' => (string)($verified['response_body'] ?? $this->wxSuccessXml()),
                    ];
                }

                return [
                    'kind' => 'text',
                    'body' => (string)($verified['response_body'] ?? 'success'),
                ];
            }

            return [
                'kind' => 'redirect',
                'location' => '/Deal/Recharge',
            ];
        }

        return $mode === 'notify'
            ? ['kind' => 'text', 'body' => $this->callbackFailureBody($payload), 'content_type' => $this->callbackFailureContentType($payload)]
            : ['kind' => 'redirect', 'location' => '/Deal/Recharge'];
    }

    public function cashierPayload(int $merchantId, string $outTradeNo, string $type, string $rawQrCode, string $launchUrl = ''): array
    {
        $basic = $this->merchantBasic($merchantId);
        $recharge = Db::table('ypay_recharge')
            ->select('id', 'out_trade_no', 'money', 'status', 'create_time', 'out_time')
            ->where('out_trade_no', $outTradeNo)
            ->first();
        $row = $recharge ? (array)$recharge : [];
        $timeoutSeconds = max(0, (int)($row['out_time'] ?? 0) - time());

        return [
            'order' => [
                'trade_no' => $outTradeNo,
                'out_trade_no' => $outTradeNo,
                'type' => $type,
                'name' => 'Online Recharge',
                'truemoney' => (string)($row['money'] ?? '0.00'),
                'raw_qrcode' => $rawQrCode,
                'launch_url' => $launchUrl,
                'status' => (int)($row['status'] ?? 0),
            ],
            'console' => [
                'timeout_seconds' => $timeoutSeconds,
                'timeout_url' => trim((string)($basic['timeout_url'] ?? '/Deal/Recharge')) ?: '/Deal/Recharge',
                'console_notice' => trim((string)($basic['console_notity'] ?? '')),
                'is_pay_popup' => (int)($basic['is_payPopUp'] ?? 0) === 1,
            ],
        ];
    }

    private function createEpayRecharge(int $rechargeId, string $orderNo, float $amount, string $type, string $notifyUrl, string $returnUrl, array $paylist): array
    {
        $fields = [
            'pid' => (string)($paylist['pid'] ?? ''),
            'type' => $type,
            'out_trade_no' => $orderNo,
            'notify_url' => $notifyUrl,
            'return_url' => $returnUrl,
            'name' => 'Online Recharge',
            'money' => number_format($amount, 2, '.', ''),
        ];

        return [
            'ok' => true,
            'mode' => 'html',
            'content_type' => 'text/html; charset=utf-8',
            'body' => $this->epayAutoSubmitForm((string)($paylist['url'] ?? ''), $fields, (string)($paylist['key'] ?? '')),
            'recharge_id' => $rechargeId,
            'out_trade_no' => $orderNo,
        ];
    }

    private function createAlipayWebRecharge(Request $request, int $rechargeId, string $orderNo, float $amount, string $notifyUrl, string $returnUrl, array $paylist): array
    {
        LegacyPaymentSdkAutoloader::register();
        $this->prepareServerForLegacySdk($request);

        $client = new \iboxs\payment\Client($this->officialGatewayConfig($paylist, $notifyUrl, $returnUrl));
        $body = $this->captureOutput(static function () use ($client, $orderNo, $amount): void {
            $client->AlipayWeb([
                'out_trade_no' => $orderNo,
                'amount' => number_format($amount, 2, '.', ''),
                'order_name' => 'Online Recharge',
            ]);
        });

        if (trim($body) === '') {
            throw new RuntimeException('official alipay gateway did not return an HTML submit body');
        }

        return [
            'ok' => true,
            'mode' => 'html',
            'content_type' => 'text/html; charset=utf-8',
            'body' => $body,
            'recharge_id' => $rechargeId,
            'out_trade_no' => $orderNo,
        ];
    }

    private function createQrRecharge(
        Request $request,
        int $rechargeId,
        string $orderNo,
        float $amount,
        string $type,
        string $notifyUrl,
        string $returnUrl,
        array $paylist,
        array $basic,
        string $providerType
    ): array {
        LegacyPaymentSdkAutoloader::register();
        $this->prepareServerForLegacySdk($request);

        $client = new \iboxs\payment\Client($this->officialGatewayConfig($paylist, $notifyUrl, $returnUrl));
        $gatewayOrder = [
            'out_trade_no' => $orderNo,
            'amount' => number_format($amount, 2, '.', ''),
            'order_name' => 'Online Recharge',
        ];

        $result = match ($providerType) {
            'dmf' => $client->AlipayCode($gatewayOrder),
            'wxpay' => $client->WxPayCode($gatewayOrder),
            'qqpay' => $client->QQPay($gatewayOrder),
            default => throw new RuntimeException('unsupported qr provider: ' . $providerType),
        };

        if (!is_array($result)) {
            throw new RuntimeException('qr payment upstream did not return a structured response');
        }

        $qrCode = trim((string)($result['qr_code'] ?? $result['code_url'] ?? $result['payurl'] ?? ''));
        if ($qrCode === '') {
            throw new RuntimeException('qr payment upstream did not return a QR code');
        }

        $launchUrl = $providerType === 'dmf' ? $qrCode : trim((string)($result['mweb_url'] ?? ''));
        $outTime = time() + $this->timeoutSeconds($basic);
        Db::table('ypay_recharge')
            ->where('id', $rechargeId)
            ->update([
                'qrcode' => $qrCode,
                'out_time' => $outTime,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        return [
            'ok' => true,
            'mode' => 'cashier',
            'recharge_id' => $rechargeId,
            'out_trade_no' => $orderNo,
            'cashier_payload' => $this->cashierPayload((int)($basic['user_id'] ?? 0), $orderNo, $type, $qrCode, $launchUrl),
        ];
    }

    private function merchantBasic(int $merchantId): array
    {
        $row = Db::table('ypay_userbasic')
            ->select('user_id', 'timeout_time', 'timeout_url', 'console_notity', 'is_payPopUp')
            ->where('user_id', $merchantId)
            ->first();

        return $row ? (array)$row : [
            'user_id' => $merchantId,
            'timeout_time' => 180,
            'timeout_url' => '/Deal/Recharge',
            'console_notity' => '',
            'is_payPopUp' => 0,
        ];
    }

    private function generateRechargeOrderNo(array $config): string
    {
        $prefix = (int)($config['isDiy_orderNo'] ?? 0) === 1
            ? trim((string)($config['diy_orderNo'] ?? ''))
            : 'Y';

        if ($prefix === '') {
            $prefix = 'Y';
        }

        return $prefix . date('YmdHis') . random_int(11111, 99999);
    }

    private function configuredPaylist(string $configKey, ?array $config = null): ?array
    {
        $config ??= SystemConfig::all();
        $paylistId = (int)($config[$configKey] ?? 0);
        if ($paylistId <= 0) {
            return null;
        }

        $row = Db::table('ypay_paylist')
            ->select('id', 'type', 'status', 'name', 'url', 'pid', 'key', 'other')
            ->where('id', $paylistId)
            ->where('status', 1)
            ->first();

        if (!$row) {
            return null;
        }

        $paylist = (array)$row;
        $paylist['key'] = (string)($paylist['key'] ?? '');

        return $paylist;
    }

    private function minRecharge(array $config): float
    {
        $value = isset($config['min_recharge']) && is_numeric($config['min_recharge'])
            ? (float)$config['min_recharge']
            : 0.01;

        return max(0.01, round($value, 2));
    }

    private function maxRecharge(array $config): float
    {
        $value = isset($config['max_recharge']) && is_numeric($config['max_recharge'])
            ? (float)$config['max_recharge']
            : 100000.00;

        return max($this->minRecharge($config), round($value, 2));
    }

    private function catalogMethod(array $catalog, string $type): ?array
    {
        foreach ((array)($catalog['methods'] ?? []) as $method) {
            $item = (array)$method;
            $item['config_key'] = (string)(self::SUPPORTED_METHODS[$type]['config_key'] ?? '');
            if (($item['id'] ?? '') === $type) {
                return $item;
            }
        }

        return null;
    }

    private function error(string $message, int $apiCode, int $httpStatus): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'api_code' => $apiCode,
            'http_status' => $httpStatus,
        ];
    }

    private function requestOrigin(Request $request): string
    {
        return \app\support\FrontendUrlBuilder::requestOrigin($request);
    }

    private function requestScheme(Request $request): string
    {
        $forwardedProto = strtolower(trim((string)$request->header('x-forwarded-proto', '')));
        if ($forwardedProto !== '') {
            return explode(',', $forwardedProto)[0] === 'https' ? 'https' : 'http';
        }

        if ((string)$request->header('front-end-https', '') === 'on') {
            return 'https';
        }

        if ((string)$request->header('x-forwarded-port', '') === '443') {
            return 'https';
        }

        return 'http';
    }

    private function epayAutoSubmitForm(string $gatewayUrl, array $fields, string $key): string
    {
        $gatewayUrl = rtrim(trim($gatewayUrl), '/');
        if ($gatewayUrl === '') {
            throw new RuntimeException('epay gateway url is missing');
        }

        $payload = $fields;
        $payload['sign'] = $this->epaySign($payload, $key);
        $payload['sign_type'] = 'MD5';

        $html = '<form id="legacy-recharge-epay" action="' . htmlspecialchars($gatewayUrl . '/submit.php', ENT_QUOTES, 'UTF-8') . '" method="post">';
        foreach ($payload as $name => $value) {
            $html .= '<input type="hidden" name="' . htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"/>';
        }
        $html .= '<input type="submit" value="正在跳转"></form><script>document.getElementById("legacy-recharge-epay").submit();</script>';

        return $html;
    }

    private function epaySign(array $payload, string $key): string
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

    private function officialGatewayConfig(array $paylist, string $notifyUrl, string $returnUrl): array
    {
        return match (strtolower(trim((string)($paylist['type'] ?? '')))) {
            'wxpay' => [
                'mchid' => (string)($paylist['other'] ?? ''),
                'apiKey' => (string)($paylist['key'] ?? ''),
                'appid' => (string)($paylist['pid'] ?? ''),
                'notify_url' => $notifyUrl,
                'return_url' => $returnUrl,
            ],
            'qqpay' => [
                'mchid' => (string)($paylist['other'] ?? ''),
                'apiKey' => (string)($paylist['key'] ?? ''),
                'appid' => (string)($paylist['pid'] ?? ''),
                'notify_url' => $notifyUrl,
                'return_url' => $returnUrl,
            ],
            default => [
                'publicKey' => (string)($paylist['other'] ?? ''),
                'rsaPrivateKey' => (string)($paylist['key'] ?? ''),
                'appid' => (string)($paylist['pid'] ?? ''),
                'notify_url' => $notifyUrl,
                'return_url' => $returnUrl,
                'charset' => 'UTF-8',
                'sign_type' => 'RSA2',
                'gatewayUrl' => 'https://openapi.alipay.com/gateway.do',
            ],
        };
    }

    private function prepareServerForLegacySdk(Request $request): void
    {
        if (empty($_SERVER['REMOTE_ADDR'])) {
            $_SERVER['REMOTE_ADDR'] = $request->getRealIp() ?: '127.0.0.1';
        }

        if (empty($_SERVER['HTTP_HOST'])) {
            $_SERVER['HTTP_HOST'] = $request->host();
        }

        if (empty($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = $request->uri();
        }

        $_SERVER['HTTPS'] = $this->requestScheme($request) === 'https' ? 'on' : 'off';
    }

    private function captureOutput(callable $callback): string
    {
        ob_start();
        try {
            $callback();
            return (string)ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }

    private function timeoutSeconds(array $basic): int
    {
        $timeout = (int)($basic['timeout_time'] ?? 180);
        if ($timeout <= 0) {
            $timeout = 180;
        }

        $systemMax = SystemConfig::int('timeout', 180);
        if ($systemMax > 0 && $timeout > $systemMax) {
            $timeout = $systemMax;
        }

        return max(60, $timeout);
    }

    private function buildQrCodeUrl(string $content, Request $request, int $size): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $content) === 1 || str_starts_with($content, 'data:image/')) {
            return $content;
        }

        if (str_starts_with($content, '//')) {
            return $this->requestScheme($request) . ':' . $content;
        }

        if (str_starts_with($content, '/')) {
            $path = (string)(parse_url($content, PHP_URL_PATH) ?: '');
            if ($path !== '' && preg_match('/\.(png|jpe?g|gif|bmp|webp|svg)$/i', $path) === 1) {
                return $this->requestOrigin($request) . $content;
            }
        }

        return $this->requestOrigin($request) . '/qrcode.php?text=' . rawurlencode($content) . '&size=' . $size;
    }

    private function callbackPayload(Request $request): array
    {
        $payload = RequestPayload::all($request);
        if (is_array($payload) && $payload !== []) {
            return $payload;
        }

        $query = $request->get();
        if (is_array($query) && $query !== []) {
            return $query;
        }

        $raw = (string)$request->rawBody();
        if ($raw !== '' && str_contains(ltrim($raw), '<xml')) {
            $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml instanceof \SimpleXMLElement) {
                $decoded = json_decode(json_encode($xml, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true);
                return is_array($decoded) ? $decoded : [];
            }
        }

        return [];
    }

    private function callbackCandidates(array $payload, array $config): array
    {
        $candidates = [];
        $primaryConfigKey = $this->primaryCallbackConfigKey($payload);
        if ($primaryConfigKey !== null) {
            $paylist = $this->configuredPaylist($primaryConfigKey, $config);
            if ($paylist !== null) {
                $candidates[] = ['config_key' => $primaryConfigKey, 'paylist' => $paylist];
            }
        }

        foreach (['alipay', 'wechat', 'qqpay'] as $configKey) {
            $paylist = $this->configuredPaylist($configKey, $config);
            if ($paylist === null) {
                continue;
            }

            $alreadyAdded = false;
            foreach ($candidates as $candidate) {
                if ((int)($candidate['paylist']['id'] ?? 0) === (int)($paylist['id'] ?? 0)) {
                    $alreadyAdded = true;
                    break;
                }
            }

            if (!$alreadyAdded) {
                $candidates[] = ['config_key' => $configKey, 'paylist' => $paylist];
            }
        }

        return $candidates;
    }

    private function primaryCallbackConfigKey(array $payload): ?string
    {
        $type = strtolower(trim((string)($payload['type'] ?? '')));
        if ($type !== '') {
            return self::SUPPORTED_METHODS[$type]['config_key'] ?? $this->resolveConfigKeyByType($type);
        }

        if (isset($payload['trade_status']) || isset($payload['notify_type']) || isset($payload['method'])) {
            return 'alipay';
        }

        if (isset($payload['return_code']) || isset($payload['result_code']) || isset($payload['appid']) || isset($payload['mch_id'])) {
            return 'wechat';
        }

        return null;
    }

    private function resolveConfigKeyByType(string $type): string
    {
        $type = strtolower(trim($type));
        return match (true) {
            $type === 'wxpay', str_starts_with($type, 'wxpay_') => 'wechat',
            $type === 'qqpay', str_starts_with($type, 'qqpay_') => 'qqpay',
            $type === 'alipay', $type === 'alipay_bill', str_starts_with($type, 'alipay_') => 'alipay',
            default => $type,
        };
    }

    private function verifyCallback(array $candidate, array $payload, Request $request): array
    {
        $paylist = (array)($candidate['paylist'] ?? []);
        $providerType = strtolower(trim((string)($paylist['type'] ?? '')));

        return match ($providerType) {
            'epay' => $this->verifyEpayCallback($paylist, $payload),
            'alipay', 'dmf' => $this->verifyAlipayCallback($request, $paylist, $payload),
            'wxpay' => $this->verifyWxpayCallback($request, $paylist),
            'qqpay' => $this->verifyQqpayCallback($request, $paylist, $payload),
            default => ['verified' => false],
        };
    }

    private function verifyEpayCallback(array $paylist, array $payload): array
    {
        if (!isset($payload['sign'])) {
            return ['verified' => false];
        }

        if ($this->epaySign($payload, (string)($paylist['key'] ?? '')) !== (string)($payload['sign'] ?? '')) {
            return ['verified' => false];
        }

        return [
            'verified' => true,
            'out_trade_no' => trim((string)($payload['out_trade_no'] ?? '')),
            'response_kind' => 'text',
            'response_body' => 'success',
        ];
    }

    private function verifyAlipayCallback(Request $request, array $paylist, array $payload): array
    {
        LegacyPaymentSdkAutoloader::register();
        $this->prepareServerForLegacySdk($request);

        $previousPost = $_POST;
        $_POST = $payload;
        try {
            $config = $this->officialGatewayConfig($paylist, '', '');
            $responseBody = $this->captureOutput(static function () use ($config): void {
                \iboxs\payment\Notify::alipayNotify($config);
            });

            $verified = trim($responseBody) === 'success'
                || (($payload['trade_status'] ?? '') === 'TRADE_SUCCESS' && trim((string)($payload['out_trade_no'] ?? '')) !== '');

            return [
                'verified' => $verified,
                'out_trade_no' => trim((string)($payload['out_trade_no'] ?? '')),
                'response_kind' => 'text',
                'response_body' => $verified ? 'success' : 'fail',
            ];
        } finally {
            $_POST = $previousPost;
        }
    }

    private function verifyWxpayCallback(Request $request, array $paylist): array
    {
        LegacyPaymentSdkAutoloader::register();
        $this->prepareServerForLegacySdk($request);

        $config = $this->officialGatewayConfig($paylist, '', '');
        $responseBody = $this->captureOutput(static function () use ($config): void {
            \iboxs\payment\Notify::WxPayNotify($config);
        });

        $payload = $this->callbackPayload($request);
        return [
            'verified' => str_contains($responseBody, '<return_code><![CDATA[SUCCESS]]></return_code>'),
            'out_trade_no' => trim((string)($payload['out_trade_no'] ?? '')),
            'response_kind' => 'xml',
            'response_body' => str_contains($responseBody, '<xml>') ? $responseBody : $this->wxSuccessXml(),
        ];
    }

    private function verifyQqpayCallback(Request $request, array $paylist, array $payload): array
    {
        LegacyPaymentSdkAutoloader::register();
        $this->prepareServerForLegacySdk($request);

        $config = [
            'mchid' => (string)($paylist['other'] ?? ''),
            'appid' => (string)($paylist['pid'] ?? ''),
            'key' => (string)($paylist['key'] ?? ''),
        ];
        $verified = (bool)\iboxs\payment\Notify::QqPayNotify($config);

        return [
            'verified' => $verified,
            'out_trade_no' => trim((string)($payload['out_trade_no'] ?? '')),
            'response_kind' => 'xml',
            'response_body' => $verified ? $this->wxSuccessXml() : $this->wxFailureXml(),
        ];
    }

    private function settleRecharge(string $outTradeNo, array $config): void
    {
        Db::transaction(function () use ($outTradeNo, $config): void {
            $rechargeRow = Db::table('ypay_recharge')
                ->where('out_trade_no', $outTradeNo)
                ->lockForUpdate()
                ->first();
            if (!$rechargeRow) {
                return;
            }

            $recharge = (array)$rechargeRow;
            if ((int)($recharge['status'] ?? 0) === 1) {
                return;
            }

            $merchantRow = Db::table('ypay_user')
                ->where('id', (int)($recharge['user_id'] ?? 0))
                ->lockForUpdate()
                ->first();
            if (!$merchantRow) {
                throw new RuntimeException('merchant was not found for recharge settlement');
            }

            $merchant = (array)$merchantRow;
            $amount = round((float)($recharge['money'] ?? 0), 2);
            $before = round((float)($merchant['money'] ?? 0), 2);
            $after = round($before + $amount, 2);
            $now = date('Y-m-d H:i:s');

            Db::table('ypay_recharge')
                ->where('id', (int)$recharge['id'])
                ->update([
                    'status' => 1,
                    'end_time' => $now,
                    'update_time' => $now,
                ]);

            Db::table('ypay_user')
                ->where('id', (int)$merchant['id'])
                ->update([
                    'money' => number_format($after, 2, '.', ''),
                ]);

            Db::table('money_log')->insert([
                'user_id' => (int)$merchant['id'],
                'type' => null,
                'money' => number_format($amount, 2, '.', ''),
                'beforemoney' => number_format($before, 2, '.', ''),
                'after' => number_format($after, 2, '.', ''),
                'memo' => '商户在线充值',
                'create_time' => $now,
            ]);

            $this->settleAffiliateRebate($merchant, $amount, $config, $now);
        });
    }

    private function settleAffiliateRebate(array $merchant, float $amount, array $config, string $now): void
    {
        if ((int)($config['is_aff'] ?? 0) !== 1) {
            return;
        }

        if ((int)($config['aff_type'] ?? 0) !== 0) {
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
            'memo' => '下级充值返利',
            'create_time' => $now,
        ]);
    }

    private function wxSuccessXml(): string
    {
        return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
    }

    private function wxFailureXml(): string
    {
        return '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[FAIL]]></return_msg></xml>';
    }

    private function callbackFailureBody(array $payload): string
    {
        return isset($payload['return_code']) || isset($payload['result_code']) ? $this->wxFailureXml() : 'fail';
    }

    private function callbackFailureContentType(array $payload): string
    {
        return isset($payload['return_code']) || isset($payload['result_code'])
            ? 'application/xml; charset=utf-8'
            : 'text/plain; charset=utf-8';
    }
}
