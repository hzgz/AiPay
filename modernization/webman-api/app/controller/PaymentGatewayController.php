<?php

namespace app\controller;

use app\support\ApiResponse;
use app\support\RequestPayload;
use InvalidArgumentException;
use Plugins\Payments\Shared\Support\PaymentGatewayResolutionSupport;
use Plugins\Payments\Shared\Support\PaymentPluginException;
use RuntimeException;
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

    private function dispatchOrderCreation(array $payload): array
    {
        $pluginResolution = $this->gatewayResolution()->resolve($payload);
        $pluginCode = $pluginResolution['code'];
        $plugin = $pluginResolution['detail'];
        $pluginResult = $this->pluginInstance($plugin['manifest'])->createOrder(array_merge($payload, [
            '_resolved_plugin_code' => $pluginCode,
            '_plugin_resolution' => $pluginResolution['resolution'],
        ]));

        return [$pluginCode, $plugin, $pluginResult, $pluginResolution];
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
            throw new RuntimeException('插件入口文件不存在：' . $manifest['entry']);
        }

        require_once $entryPath;

        $className = (string)$manifest['class'];
        if ($className === '' || !class_exists($className)) {
            throw new RuntimeException('插件类不存在：' . $className);
        }

        return new $className();
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
  <title>支付请求失败</title>
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
      <h1>支付请求失败</h1>
      <p>{$safeMessage}</p>
      <a href="{$safeUrl}">返回</a>
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

        return '支付网关处理失败';
    }

    private function gatewayResolution(): PaymentGatewayResolutionSupport
    {
        return new PaymentGatewayResolutionSupport();
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

        return ApiResponse::error('支付网关处理失败', 500, [
            'exception' => $exception->getMessage(),
        ], 500);
    }
}
