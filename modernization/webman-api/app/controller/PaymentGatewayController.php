<?php

namespace app\controller;

use app\payment\PaymentPluginException;
use app\payment\PaymentPluginManager;
use app\support\ApiResponse;
use app\support\RequestPayload;
use InvalidArgumentException;
use RuntimeException;
use support\Db;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;

class PaymentGatewayController
{
    public function submit(Request $request): Response
    {
        $payload = $this->payload($request);
        $payload = $this->enrichPayload($request, $payload, 'submit');

        try {
            [$pluginCode, $plugin, $pluginResult, $pluginResolution] = $this->dispatchOrderCreation($payload);

            if (!empty($pluginResult['form_html'])) {
                return response((string)$pluginResult['form_html'], 200, ['Content-Type' => 'text/html; charset=utf-8']);
            }

            if (!empty($pluginResult['legacy_api_response']) && is_array($pluginResult['legacy_api_response'])) {
                return json($pluginResult['legacy_api_response'], JSON_UNESCAPED_UNICODE);
            }

            return ApiResponse::success([
                'entry' => 'submit',
                'plugin' => $pluginCode,
                'plugin_state' => $plugin['state'],
                'plugin_resolution' => $pluginResolution,
                'plugin_result' => $pluginResult,
            ]);
        } catch (Throwable $exception) {
            return response(
                $this->legacySubmitErrorPage($this->legacyExceptionMessage($exception), $this->inferReturnHost($payload)),
                200,
                ['Content-Type' => 'text/html; charset=utf-8']
            );
        }
    }

    public function mapi(Request $request): Response
    {
        $payload = $this->payload($request);
        $payload = $this->enrichPayload($request, $payload, 'mapi');

        try {
            [$pluginCode, $plugin, $pluginResult, $pluginResolution] = $this->dispatchOrderCreation($payload);

            if (!empty($pluginResult['legacy_api_response']) && is_array($pluginResult['legacy_api_response'])) {
                return json($pluginResult['legacy_api_response'], JSON_UNESCAPED_UNICODE);
            }

            return ApiResponse::success([
                'entry' => 'mapi',
                'plugin' => $pluginCode,
                'plugin_state' => $plugin['state'],
                'plugin_resolution' => $pluginResolution,
                'plugin_result' => $pluginResult,
            ]);
        } catch (Throwable $exception) {
            return json([
                'code' => 201,
                'msg' => $this->legacyExceptionMessage($exception),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiPayment(Request $request): Response
    {
        return $this->legacyApiEntry($request, 'submit');
    }

    public function apiMapi(Request $request): Response
    {
        return $this->legacyApiEntry($request, 'mapi');
    }

    private function payload(Request $request): array
    {
        $payload = RequestPayload::all($request);
        if (!empty($payload)) {
            return $payload;
        }

        $query = $request->get();
        return is_array($query) ? $query : [];
    }

    private function resolvePluginCode(array $payload): string
    {
        return strtolower(trim((string)($payload['plugin'] ?? $payload['plugin_code'] ?? '')));
    }

    private function dispatchOrderCreation(array $payload): array
    {
        $this->assertGatewayMerchantReference($payload);
        $pluginResolution = $this->resolveGatewayPluginSelection($payload);
        $pluginCode = $pluginResolution['code'];
        $plugin = $pluginResolution['detail'];
        $pluginResult = $this->pluginInstance($plugin['manifest'])->createOrder(array_merge($payload, [
            '_resolved_plugin_code' => $pluginCode,
            '_plugin_resolution' => $pluginResolution['resolution'],
        ]));

        return [$pluginCode, $plugin, $pluginResult, $pluginResolution];
    }

    private function resolveGatewayPluginSelection(array $payload): array
    {
        $requestedPluginCode = $this->resolvePluginCode($payload);
        if ($requestedPluginCode !== '') {
            return [
                'code' => $requestedPluginCode,
                'resolution' => 'explicit_request',
                'detail' => $this->assertGatewayCapablePlugin($requestedPluginCode),
            ];
        }

        $inferredPluginCode = $this->inferPluginCodeFromPayload($payload);
        if ($inferredPluginCode !== '') {
            return [
                'code' => $inferredPluginCode,
                'resolution' => 'merchant_channel_inference',
                'detail' => $this->assertGatewayCapablePlugin($inferredPluginCode),
            ];
        }

        $gatewayPlugins = $this->gatewayCapablePlugins();
        if ($gatewayPlugins === []) {
            throw new InvalidArgumentException('no enabled gateway payment plugin is available');
        }

        if (count($gatewayPlugins) > 1) {
            throw new InvalidArgumentException('multiple gateway payment plugins are enabled; please specify plugin');
        }

        $pluginCode = trim((string)($gatewayPlugins[0]['code'] ?? ''));
        if ($pluginCode === '') {
            throw new RuntimeException('gateway plugin resolution returned an empty plugin code');
        }

        return [
            'code' => $pluginCode,
            'resolution' => 'implicit_single_gateway_plugin',
            'detail' => $this->assertGatewayCapablePlugin($pluginCode),
        ];
    }

    private function inferPluginCodeFromPayload(array $payload): string
    {
        $accountId = (int)($payload['account_id'] ?? ($payload['channel_id'] ?? 0));
        if ($accountId > 0) {
            $account = $this->loadPaymentAccount($accountId);
            if ($account !== null && $this->isGatewayCapablePluginCode((string)($account['code'] ?? ''))) {
                return strtolower(trim((string)($account['code'] ?? '')));
            }
        }

        $poolId = (int)($payload['pool_id'] ?? ($payload['poll_id'] ?? 0));
        if ($poolId > 0) {
            $poolPluginCode = $this->inferPoolPluginCode($poolId);
            if ($poolPluginCode !== '') {
                return $poolPluginCode;
            }
        }

        $merchantId = (int)($payload['pid'] ?? 0);
        $paymentType = $this->normalizePaymentType((string)($payload['type'] ?? ''));
        if ($merchantId <= 0 || $paymentType === '') {
            return '';
        }

        $account = Db::table('ypay_account')
            ->select('code')
            ->where('user_id', $merchantId)
            ->where('type', $paymentType)
            ->where('status', 1)
            ->where('is_status', 1)
            ->orderByDesc('id')
            ->first();

        if ($account !== null) {
            $pluginCode = strtolower(trim((string)((array)$account)['code']));
            if ($this->isGatewayCapablePluginCode($pluginCode)) {
                return $pluginCode;
            }
        }

        if ($this->legacyPaylistExists($merchantId, $paymentType) && $this->isGatewayCapablePluginCode('legacy_epay')) {
            return 'legacy_epay';
        }

        return '';
    }

    private function assertGatewayMerchantReference(array $payload): void
    {
        if ($this->resolvePluginCode($payload) !== '') {
            return;
        }

        foreach (['account_id', 'channel_id', 'pool_id', 'poll_id'] as $field) {
            if ((int)($payload[$field] ?? 0) > 0) {
                return;
            }
        }

        if ((int)($payload['pid'] ?? 0) > 0) {
            return;
        }

        throw new InvalidArgumentException('pid is required');
    }

    private function inferPoolPluginCode(int $poolId): string
    {
        $row = Db::table('ypay_poll_pool_item as item')
            ->join('ypay_account as account', 'account.id', '=', 'item.account_id')
            ->select('account.code')
            ->where('item.pool_id', $poolId)
            ->where('account.status', 1)
            ->where('account.is_status', 1)
            ->orderBy('item.sort')
            ->orderByDesc('account.id')
            ->first();

        if ($row === null) {
            return '';
        }

        $pluginCode = strtolower(trim((string)((array)$row)['code']));
        return $this->isGatewayCapablePluginCode($pluginCode) ? $pluginCode : '';
    }

    private function loadPaymentAccount(int $accountId): ?array
    {
        if ($accountId <= 0) {
            return null;
        }

        $row = Db::table('ypay_account')
            ->select('id', 'code', 'type', 'user_id', 'status', 'is_status')
            ->where('id', $accountId)
            ->first();

        return $row ? (array)$row : null;
    }

    private function legacyPaylistExists(int $merchantId, string $paymentType): bool
    {
        if ($merchantId <= 0 || $paymentType === '') {
            return false;
        }

        return Db::table('ypay_paylist')
            ->where('user_id', $merchantId)
            ->where('status', 1)
            ->where('type', 'epay')
            ->exists();
    }

    private function isGatewayCapablePluginCode(string $pluginCode): bool
    {
        $normalized = strtolower(trim($pluginCode));
        if ($normalized === '') {
            return false;
        }

        try {
            $detail = $this->assertGatewayCapablePlugin($normalized);
        } catch (Throwable) {
            return false;
        }

        return !empty($detail);
    }

    private function normalizePaymentType(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'alipay', 'alipay_official', 'alipay_bill', 'alipay_mck' => 'alipay',
            'wxpay', 'wxpay_v3' => 'wxpay',
            'qqpay' => 'qqpay',
            default => $normalized,
        };
    }

    private function assertGatewayCapablePlugin(string $pluginCode): array
    {
        $detail = $this->manager()->detail($pluginCode);
        $manifest = is_array($detail['manifest'] ?? null) ? $detail['manifest'] : [];
        $state = is_array($detail['state'] ?? null) ? $detail['state'] : [];
        $capabilities = array_map(
            static fn (mixed $value): string => strtolower(trim((string)$value)),
            is_array($manifest['capabilities'] ?? null) ? $manifest['capabilities'] : []
        );

        if (!in_array('create_order', $capabilities, true)) {
            throw new InvalidArgumentException("payment plugin [$pluginCode] does not support gateway order creation");
        }

        if (!(bool)($state['installed'] ?? false)) {
            throw new InvalidArgumentException("payment plugin [$pluginCode] is not installed");
        }

        if (!(bool)($state['enabled'] ?? false)) {
            throw new InvalidArgumentException("payment plugin [$pluginCode] is disabled");
        }

        return $detail;
    }

    private function gatewayCapablePlugins(): array
    {
        $plugins = [];

        foreach ($this->manager()->all() as $plugin) {
            if (!is_array($plugin)) {
                continue;
            }

            $capabilities = array_map(
                static fn (mixed $value): string => strtolower(trim((string)$value)),
                is_array($plugin['capabilities'] ?? null) ? $plugin['capabilities'] : []
            );

            if (!in_array('create_order', $capabilities, true)) {
                continue;
            }

            if (!(bool)($plugin['installed'] ?? false) || !(bool)($plugin['enabled'] ?? false)) {
                continue;
            }

            $plugins[] = $plugin;
        }

        usort(
            $plugins,
            static fn (array $left, array $right): int => strcmp(
                strtolower(trim((string)($left['code'] ?? ''))),
                strtolower(trim((string)($right['code'] ?? '')))
            )
        );

        return $plugins;
    }

    private function requestSummary(Request $request, array $payload): array
    {
        return [
            'method' => $request->method(),
            'path' => $request->path(),
            'query' => $request->get(),
            'payload' => $payload,
            'headers' => [
                'content_type' => (string)$request->header('content-type', ''),
                'user_agent' => (string)$request->header('user-agent', ''),
            ],
        ];
    }

    private function pluginInstance(array $manifest): object
    {
        $entryPath = base_path($manifest['entry']);
        if (!is_file($entryPath)) {
            throw new RuntimeException('plugin entry file was not found: ' . $manifest['entry']);
        }

        require_once $entryPath;

        $className = (string)$manifest['class'];
        if ($className === '' || !class_exists($className)) {
            throw new RuntimeException('plugin class was not found: ' . $className);
        }

        return new $className();
    }

    private function manager(): PaymentPluginManager
    {
        return new PaymentPluginManager();
    }

    private function enrichPayload(Request $request, array $payload, string $entry): array
    {
        $payload['_entry'] = $entry;
        $payload['_request_host'] = (string)$request->host();
        $payload['_request_scheme'] = $this->requestScheme($request);
        $payload['_origin'] = $payload['_request_scheme'] . '://' . $payload['_request_host'];
        $payload['_request_path'] = $request->path();
        $payload['_request_url'] = $request->fullUrl();
        $payload['_client_ip'] = $request->getRealIp();

        return $payload;
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

    private function inferReturnHost(array $payload): string
    {
        foreach (['notify_url', 'return_url'] as $field) {
            $url = trim((string)($payload[$field] ?? ''));
            if ($url === '') {
                continue;
            }

            $parts = parse_url($url);
            if (!is_array($parts) || empty($parts['host'])) {
                continue;
            }

            $scheme = !empty($parts['scheme']) ? $parts['scheme'] . '://' : 'http://';
            return $scheme . $parts['host'];
        }

        return '/';
    }

    private function legacyApiEntry(Request $request, string $entry): Response
    {
        $payload = $this->payload($request);
        $payload = $this->enrichPayload($request, $payload, $entry);

        try {
            [$pluginCode, $plugin, $pluginResult, $pluginResolution] = $this->dispatchOrderCreation($payload);

            if (!empty($pluginResult['legacy_api_response']) && is_array($pluginResult['legacy_api_response'])) {
                return json($pluginResult['legacy_api_response'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return $this->legacyJsonResponse(200, '订单创建成功', [
                'entry' => $entry,
                'plugin' => $pluginCode,
                'plugin_state' => $plugin['state'] ?? 'unknown',
                'plugin_resolution' => $pluginResolution,
                'plugin_result' => $pluginResult,
            ]);
        } catch (Throwable $exception) {
            return $this->legacyJsonResponse(201, $this->legacyExceptionMessage($exception));
        }
    }

    private function legacyJsonResponse(int $code, string $message, array $data = []): Response
    {
        $message = ApiResponse::normalizeText($message);

        return json([
            'code' => $code,
            'msg' => $message,
            'message' => $message,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function legacySubmitErrorPage(string $message, string $returnUrl): string
    {
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Payment Error</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f6f7fb;color:#1f2937}
    .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .card{width:min(480px,100%);background:#fff;border-radius:18px;box-shadow:0 20px 60px rgba(15,23,42,.08);padding:32px}
    h1{margin:0 0 12px;font-size:22px}
    p{margin:0 0 20px;line-height:1.7;color:#4b5563}
    a{display:inline-block;padding:10px 16px;border-radius:10px;background:#111827;color:#fff;text-decoration:none}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Payment Request Failed</h1>
      <p>{$safeMessage}</p>
      <a href="{$safeUrl}">Return</a>
    </div>
  </div>
</body>
</html>
HTML;
    }

    private function legacyExceptionMessage(Throwable $exception): string
    {
        if ($exception instanceof PaymentPluginException) {
            return $exception->getMessage();
        }

        if ($exception instanceof InvalidArgumentException) {
            return $exception->getMessage();
        }

        if ($exception instanceof RuntimeException) {
            return $exception->getMessage();
        }

        return 'payment gateway migration entry failed';
    }

    private function handleException(Throwable $exception): Response
    {
        if ($exception instanceof PaymentPluginException) {
            $code = $exception->getCode();
            $status = $code >= 400 && $code < 600 ? $code : 422;
            return ApiResponse::error($exception->getMessage(), $status, null, $status);
        }

        if ($exception instanceof InvalidArgumentException) {
            return ApiResponse::error($exception->getMessage(), 404, null, 404);
        }

        if ($exception instanceof RuntimeException) {
            return ApiResponse::error($exception->getMessage(), 409, null, 409);
        }

        return ApiResponse::error('payment gateway migration entry failed', 500, [
            'exception' => $exception->getMessage(),
        ], 500);
    }
}
