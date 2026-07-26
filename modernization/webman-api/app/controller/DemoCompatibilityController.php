<?php

declare(strict_types=1);

namespace app\controller;

use app\support\ApiResponse;
use app\support\FrontendUrlBuilder;
use app\support\SystemConfig;
use Webman\Http\Request;
use Webman\Http\Response;

class DemoCompatibilityController
{
    public function index(Request $request): Response
    {
        if (($redirect = $this->compatibilityPageRedirect($request)) !== null) {
            return $redirect;
        }

        $payload = $this->demoPayload($request);
        if ($this->wantsJson($request)) {
            return $this->ok($payload);
        }

        return redirect(FrontendUrlBuilder::publicDemoUrl($request));
    }

    public function success(Request $request): Response
    {
        if (($redirect = $this->compatibilityPageRedirect($request)) !== null) {
            return $redirect;
        }

        $payload = $this->demoPayload($request);
        if ($this->wantsJson($request)) {
            return $this->ok($payload);
        }

        return redirect(FrontendUrlBuilder::publicDemoUrl($request));
    }

    public function theme(Request $request): Response
    {
        return redirect(FrontendUrlBuilder::publicDemoUrl($request));
    }

    public function doPay(Request $request): Response
    {
        return $this->blockedWriteResponse(
            '支付测试页不支持直接创建订单，请在商户端或正式支付链路中发起支付。',
            ['demo_payment_create', 'demo_payment_handoff']
        );
    }

    public function notifyEpay(Request $request): Response
    {
        if ($this->wantsJson($request)) {
            return $this->blockedWriteResponse(
                '支付测试页不处理支付回调，请使用正式业务回调地址。',
                ['demo_notify_callback']
            );
        }

        return response('fail', 405, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function returnEpay(Request $request): Response
    {
        if (($redirect = $this->compatibilityPageRedirect($request)) !== null) {
            return $redirect;
        }

        $payload = $this->demoPayload($request);
        if ($this->wantsJson($request)) {
            return $this->ok($payload);
        }

        return redirect(FrontendUrlBuilder::publicDemoUrl($request));
    }

    private function demoPayload(Request $request): array
    {
        $config = SystemConfig::all();
        $availableMethods = $this->demoMethods((string)($config['diy_demoPay'] ?? ''));
        $gatewayConfigured = $this->gatewayConfigured($config);
        $navs = $this->defaultPublicNavItems($request);
        $demoName = trim((string)($config['demopay_name'] ?? '支付测试'));
        $demoMoney = trim((string)($config['demopay_money'] ?? '0.10'));

        return [
            'site_name' => $this->displaySiteName((string)($config['sitename'] ?? 'AiPay')),
            'demo_name' => $this->displayDemoName($demoName),
            'demo_money' => $demoMoney !== '' ? $demoMoney : '0.10',
            'gateway_configured' => $gatewayConfigured,
            'available_methods' => $availableMethods,
            'navs' => $navs,
            'merchant_login_url' => FrontendUrlBuilder::merchantLoginUrl($request),
        ];
    }

    private function demoMethods(string $enabledList): array
    {
        $enabled = array_filter(array_map('trim', explode(',', strtolower($enabledList))));
        if ($enabled === []) {
            $enabled = ['wxpay', 'alipay', 'qqpay'];
        }

        $catalog = [
            'alipay' => [
                'id' => 'alipay',
                'label' => '支付宝',
                'description' => '适合支付宝扫码、拉起支付和收款场景。',
            ],
            'wxpay' => [
                'id' => 'wxpay',
                'label' => '微信支付',
                'description' => '适合微信扫码、H5 与公众号支付场景。',
            ],
            'qqpay' => [
                'id' => 'qqpay',
                'label' => 'QQ 支付',
                'description' => '适合 QQ 钱包扫码和收款场景。',
            ],
        ];

        $methods = [];
        foreach ($catalog as $key => $method) {
            if (!in_array($key, $enabled, true)) {
                continue;
            }

            $methods[] = $method;
        }

        return $methods;
    }

    private function gatewayConfigured(array $config): bool
    {
        foreach (['epayid_demo', 'epaykey_demo', 'epayurl_demo'] as $key) {
            if (trim((string)($config[$key] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    private function defaultPublicNavItems(Request $request): array
    {
        return [
            ['id' => 0, 'name' => '首页', 'url' => FrontendUrlBuilder::publicHomeUrl($request), 'is_target' => 0, 'sort' => 1],
            ['id' => 0, 'name' => '开发文档', 'url' => FrontendUrlBuilder::publicDocUrl($request), 'is_target' => 0, 'sort' => 2],
            ['id' => 0, 'name' => '公告中心', 'url' => FrontendUrlBuilder::publicNewsIndexUrl($request), 'is_target' => 0, 'sort' => 3],
            ['id' => 0, 'name' => '支付测试', 'url' => FrontendUrlBuilder::publicDemoUrl($request), 'is_target' => 0, 'sort' => 4],
        ];
    }

    private function displaySiteName(string $siteName): string
    {
        $trimmed = trim($siteName);
        if ($trimmed === '' || $trimmed === 'AiPay') {
            return 'AiPay';
        }

        return $trimmed;
    }

    private function displayDemoName(string $demoName): string
    {
        $trimmed = trim($demoName);
        if ($trimmed === '' || in_array($trimmed, ['演示支付', '支付体验', '支付测试'], true)) {
            return '支付测试';
        }

        return $trimmed;
    }

    private function compatibilityPageRedirect(Request $request): ?Response
    {
        return null;
    }

    private function ok(array $payload): Response
    {
        $responsePayload = [
            'code' => 0,
            'msg' => '成功',
            'message' => '成功',
            'data' => $this->stripCompatibilityMeta($payload),
        ];
        $responsePayload['msg'] = ApiResponse::normalizeText((string)$responsePayload['msg']);
        $responsePayload['message'] = ApiResponse::normalizeText((string)$responsePayload['message']);

        return json($responsePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function blockedWriteResponse(string $message, array $blockedActions): Response
    {
        $responsePayload = [
            'code' => 202,
            'msg' => $message,
            'message' => $message,
            'data' => [
                'blocked_actions' => array_values($blockedActions),
            ],
        ];
        $responsePayload['msg'] = ApiResponse::normalizeText((string)$responsePayload['msg']);
        $responsePayload['message'] = ApiResponse::normalizeText((string)$responsePayload['message']);

        return json($responsePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)->withStatus(405);
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

    private function stripCompatibilityMeta(array $payload): array
    {
        foreach (['route_policy', 'migration_guard', 'legacy_url', 'legacy_routes', 'legacy_page', 'legacy_endpoint', 'legacy_action_label', 'write_actions', 'supports_write', 'admin_url', 'gateway_host'] as $key) {
            unset($payload[$key]);
        }

        return $payload;
    }

}
