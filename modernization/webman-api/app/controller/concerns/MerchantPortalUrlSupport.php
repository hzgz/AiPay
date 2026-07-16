<?php

namespace app\controller\concerns;

use app\support\FrontendUrlBuilder;
use Webman\Http\Request;
use Webman\Http\Response;

trait MerchantPortalUrlSupport
{
    protected function resolveMerchantApiGatewayLine(Request $request, array $gatewayLines): ?string
    {
        $payload = $this->requestPayload($request);
        $requested = trim((string)($payload['line_url'] ?? $payload['url'] ?? $request->get('line_url', $request->get('url', ''))));
        if ($requested === '') {
            return isset($gatewayLines[0]['url']) ? rtrim((string)$gatewayLines[0]['url'], '/') : null;
        }

        $normalizedRequested = rtrim($requested, '/');
        foreach ($gatewayLines as $line) {
            $candidate = rtrim((string)($line['url'] ?? ''), '/');
            if ($candidate !== '' && strcasecmp($candidate, $normalizedRequested) === 0) {
                return $candidate;
            }
        }

        return null;
    }

    protected function merchantApiQrPayload(string $site, int $merchantId, string $appkey): string
    {
        $content = json_encode([
            'site' => rtrim($site, '/'),
            'pid' => (string)$merchantId,
            'key' => $appkey,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($content) || $content === '') {
            $content = '{}';
        }

        return base64_encode($content);
    }

    protected function gatewayUrl(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    protected function requestOrigin(Request $request): string
    {
        $host = trim((string)$request->host());
        if ($host === '') {
            $host = trim((string)$request->header('host', '127.0.0.1:8787'));
        }
        if ($host === '') {
            $host = '127.0.0.1:8787';
        }

        return $this->requestScheme($request) . '://' . $host;
    }

    protected function merchantQrCodeUrl(Request $request, string $content, string $size = '250x250'): string
    {
        return $this->requestOrigin($request)
            . '/qrcode.php?text='
            . rawurlencode($content)
            . '&size='
            . rawurlencode($size);
    }

    protected function requireLegacyProjectAutoload(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        $this->assertMerchantLegacySdkFiles();
        $this->registerLocalThinkApiAutoloader();
        $this->registerLocalAlipayAutoloader();

        $loaded = true;
    }

    protected function assertMerchantLegacySdkFiles(): void
    {
        $requiredFiles = [
            $this->legacyThinkApiRoot() . DIRECTORY_SEPARATOR . 'Client.php' => '实名认证服务 SDK',
            $this->legacyAlipayRoot() . DIRECTORY_SEPARATOR . 'AopClient.php' => '支付宝实名认证 SDK',
            $this->legacyAlipayRoot() . DIRECTORY_SEPARATOR . 'AlipaySystemOauthTokenRequest.php' => '支付宝授权请求类',
            $this->legacyAlipayRoot() . DIRECTORY_SEPARATOR . 'AlipayUserCertdocCertverifyPreconsultRequest.php' => '支付宝实名认证预咨询请求类',
            $this->legacyAlipayRoot() . DIRECTORY_SEPARATOR . 'AlipayUserCertdocCertverifyConsultRequest.php' => '支付宝实名认证结果查询请求类',
        ];

        foreach ($requiredFiles as $file => $label) {
            if (!is_file($file)) {
                throw new \RuntimeException($label . '缺失，请先完成本地 SDK 部署');
            }
        }
    }

    protected function registerLocalThinkApiAutoloader(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }

        $basePath = $this->legacyThinkApiRoot();
        spl_autoload_register(static function (string $class) use ($basePath): void {
            if (!str_starts_with($class, 'think\\api\\')) {
                return;
            }

            $relativePath = substr($class, strlen('think\\api\\'));
            $file = $basePath . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativePath) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });

        $registered = true;
    }

    protected function registerLocalAlipayAutoloader(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }

        $basePath = $this->legacyAlipayRoot();
        spl_autoload_register(static function (string $class) use ($basePath): void {
            if (str_contains($class, '\\')) {
                return;
            }

            $file = $basePath . DIRECTORY_SEPARATOR . $class . '.php';
            if (!is_file($file)) {
                return;
            }

            $currentWorkingDirectory = getcwd();
            chdir($basePath);
            try {
                require_once $file;
            } finally {
                if (is_string($currentWorkingDirectory) && $currentWorkingDirectory !== '') {
                    chdir($currentWorkingDirectory);
                }
            }
        });

        $registered = true;
    }

    protected function legacyThinkApiRoot(): string
    {
        return base_path() . DIRECTORY_SEPARATOR . 'support' . DIRECTORY_SEPARATOR . 'legacy-sdk' . DIRECTORY_SEPARATOR . 'think-api' . DIRECTORY_SEPARATOR . 'src';
    }

    protected function legacyAlipayRoot(): string
    {
        return base_path() . DIRECTORY_SEPARATOR . 'support' . DIRECTORY_SEPARATOR . 'legacy-sdk' . DIRECTORY_SEPARATOR . 'alipay';
    }

    protected function merchantFrontendBaseUrl(Request $request): string
    {
        return FrontendUrlBuilder::merchantBaseUrl($request);
    }

    protected function merchantLoginUrl(Request $request, ?string $redirectPath = null): string
    {
        $query = [];
        if ($redirectPath !== null && $redirectPath !== '' && $redirectPath !== '/merchant/login') {
            $query['redirect'] = $redirectPath;
        }

        return $this->withHashPath($this->merchantFrontendBaseUrl($request), '/merchant/login', $query);
    }

    protected function merchantSpaRedirect(Request $request, string $targetPath, array $query = []): Response
    {
        return redirect($this->withHashPath($this->merchantFrontendBaseUrl($request), $targetPath, $query));
    }

    protected function merchantSpaRedirectForCurrentRequest(Request $request): Response
    {
        return $this->merchantSpaRedirect(
            $request,
            $this->merchantSpaPathForLegacyPath($this->requestLegacyPath($request))
        );
    }

    protected function merchantSpaPathForLegacyPath(string $path): string
    {
        $normalizedPath = parse_url($path, PHP_URL_PATH);
        $normalizedPath = is_string($normalizedPath) ? $normalizedPath : $path;
        $normalizedPath = '/' . ltrim($normalizedPath, '/');
        $normalizedPath = rtrim($normalizedPath, '/');
        if ($normalizedPath === '') {
            $normalizedPath = '/User/Login';
        }

        $map = [
            '/user/login' => '/merchant/login',
            '/user/index' => '/merchant/dashboard',
            '/user/logout' => '/merchant/login',
            '/my/userpro' => '/merchant/profile',
            '/my/notifications' => '/merchant/notifications',
            '/my/connections' => '/merchant/connections',
            '/my/security' => '/merchant/security',
            '/my/cancellation' => '/merchant/security',
            '/my/real_name' => '/merchant/real-name',
            '/my/googleauth' => '/merchant/security',
            '/my/api' => '/merchant/api',
            '/channel/pool' => '/merchant/pools',
            '/my/aff' => '/merchant/affiliate',
            '/deal/orderlog' => '/merchant/orders',
            '/deal/moneylog' => '/merchant/money-logs',
            '/deal/recharge' => '/merchant/recharges',
            '/deal/vip' => '/merchant/vip',
            '/my/ticket' => '/merchant/tickets',
            '/my/is_domain' => '/merchant/domains',
            '/my/loginlog' => '/merchant/login-logs',
            '/deal/getdetails' => '/merchant/orders',
        ];

        return $map[strtolower($normalizedPath)] ?? '/merchant/dashboard';
    }

    protected function requestLegacyPath(Request $request): string
    {
        return '/' . ltrim((string)$request->path(), '/');
    }

    protected function withHashPath(string $baseUrl, string $path, array $query = []): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $path = '/' . ltrim($path, '/');
        $queryString = $query === [] ? '' : ('?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));

        if (str_contains($baseUrl, '#')) {
            return preg_replace('#/+$#', '', $baseUrl) . $path . $queryString;
        }

        return $baseUrl . '/#' . $path . $queryString;
    }

    protected function requestScheme(Request $request): string
    {
        $forwardedProto = strtolower(trim((string)$request->header('x-forwarded-proto', '')));
        if ($forwardedProto !== '') {
            $proto = trim((string)(explode(',', $forwardedProto)[0] ?? ''));
            if (in_array($proto, ['http', 'https'], true)) {
                return $proto;
            }
        }

        if ((string)$request->header('front-end-https', '') === 'on') {
            return 'https';
        }

        if ((string)$request->header('x-forwarded-port', '') === '443') {
            return 'https';
        }

        return 'http';
    }
}
