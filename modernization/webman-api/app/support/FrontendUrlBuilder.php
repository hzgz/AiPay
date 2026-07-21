<?php

declare(strict_types=1);

namespace app\support;

use Webman\Http\Request;

final class FrontendUrlBuilder
{
    private static ?array $envCache = null;

    private function __construct()
    {
    }

    public static function requestOrigin(Request $request): string
    {
        return self::detectedScheme($request) . '://' . self::forwardedHost($request);
    }

    public static function adminBaseUrl(Request $request): string
    {
        return self::frontendBaseUrl($request, ['AIPAY_ADMIN_FRONTEND_URL', 'AIPAY_MERCHANT_FRONTEND_URL']);
    }

    public static function merchantBaseUrl(Request $request): string
    {
        return self::frontendBaseUrl($request, ['AIPAY_MERCHANT_FRONTEND_URL', 'AIPAY_ADMIN_FRONTEND_URL']);
    }

    public static function publicBaseUrl(Request $request): string
    {
        $configured = self::firstConfiguredUrl(['AIPAY_PUBLIC_FRONTEND_URL']);
        if ($configured !== null) {
            return self::preferVisibleUrl($configured, $request);
        }

        return self::requestOrigin($request);
    }

    public static function configuredBaseUrl(array $envKeys): ?string
    {
        return self::firstConfiguredUrl($envKeys);
    }

    public static function adminLoginUrl(Request $request): string
    {
        return self::withHashPath(self::adminBaseUrl($request), '/auth/login');
    }

    public static function merchantLoginUrl(Request $request, ?string $redirectPath = null): string
    {
        $query = [];
        if ($redirectPath !== null && $redirectPath !== '' && $redirectPath !== '/merchant/login') {
            $query['redirect'] = $redirectPath;
        }

        return self::withHashPath(self::merchantBaseUrl($request), '/merchant/login', $query);
    }

    public static function merchantRegisterUrl(Request $request): string
    {
        return self::withHashPath(self::merchantBaseUrl($request), '/merchant/register');
    }

    public static function merchantDashboardUrl(Request $request): string
    {
        return self::withHashPath(self::merchantBaseUrl($request), '/merchant/dashboard');
    }

    public static function merchantUrl(Request $request, string $path, array $query = []): string
    {
        return self::withHashPath(self::merchantBaseUrl($request), $path, $query);
    }

    public static function publicHomeUrl(Request $request): string
    {
        return rtrim(self::publicBaseUrl($request), '/') . '/';
    }

    public static function publicNewsIndexUrl(Request $request): string
    {
        return self::publicAppUrl($request, '/news/index');
    }

    public static function publicNewsCategoryUrl(Request $request, int $type): string
    {
        return self::publicAppUrl($request, '/news/categories/' . max(1, $type));
    }

    public static function publicNewsDetailUrl(Request $request, int $id): string
    {
        return self::publicAppUrl($request, '/news/detail/' . max(1, $id));
    }

    public static function publicDocUrl(Request $request, ?string $section = null): string
    {
        if ($section === null || $section === '' || $section === 'overview') {
            return self::publicAppUrl($request, '/doc');
        }

        return self::publicAppUrl($request, '/doc/' . ltrim($section, '/'));
    }

    public static function publicDemoUrl(Request $request): string
    {
        return self::publicAppUrl($request, '/demo');
    }

    public static function publicApiUrl(Request $request, string $path, array $query = []): string
    {
        $url = rtrim(self::publicBaseUrl($request), '/') . '/api/public/' . ltrim($path, '/');
        if ($query === []) {
            return $url;
        }

        return $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public static function publicQrCodeUrl(Request $request, string $text, int|string $size = 180): string
    {
        return self::publicApiUrl($request, 'qrcode', [
            'text' => $text,
            'size' => (string)$size,
        ]);
    }

    public static function publicPath(Request $request, string $path): string
    {
        return rtrim(self::publicBaseUrl($request), '/') . '/' . ltrim($path, '/');
    }

    public static function publicAppUrl(Request $request, string $path, array $query = []): string
    {
        return self::withHashPath(self::publicBaseUrl($request), $path, $query);
    }

    public static function withHashPath(string $baseUrl, string $path, array $query = []): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $path = '/' . ltrim($path, '/');
        $queryString = $query === [] ? '' : ('?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));

        if (str_contains($baseUrl, '#')) {
            return preg_replace('#/+$#', '', $baseUrl) . $path . $queryString;
        }

        return $baseUrl . '/#' . $path . $queryString;
    }

    private static function frontendBaseUrl(Request $request, array $envKeys): string
    {
        $configured = self::firstConfiguredUrl($envKeys);
        if ($configured !== null) {
            return self::preferVisibleUrl($configured, $request);
        }

        $host = self::forwardedHost($request);
        if (preg_match('/^(127\.0\.0\.1|localhost)(:\d+)?$/i', $host)) {
            return 'http://127.0.0.1:8132';
        }

        return self::requestOrigin($request);
    }

    private static function firstConfiguredUrl(array $envKeys): ?string
    {
        foreach ($envKeys as $envKey) {
            $configured = trim((string)(getenv($envKey) ?: ''));
            if ($configured === '') {
                $configured = trim((string)(self::envValue($envKey) ?? ''));
            }
            if ($configured !== '') {
                return rtrim($configured, '/');
            }
        }

        return null;
    }

    private static function envValue(string $key): ?string
    {
        $env = self::loadEnvFile();
        if (!array_key_exists($key, $env)) {
            return null;
        }

        $value = trim((string)$env[$key]);
        return $value === '' ? null : $value;
    }

    private static function loadEnvFile(): array
    {
        if (self::$envCache !== null) {
            return self::$envCache;
        }

        $path = base_path('.env');
        if (!is_file($path)) {
            self::$envCache = [];
            return self::$envCache;
        }

        $values = [];
        foreach ((array)file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim((string)$line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $rawValue] = explode('=', $line, 2);
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $value = trim($rawValue);
            $length = strlen($value);
            if ($length >= 2) {
                $first = $value[0];
                $last = $value[$length - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            $values[$name] = $value;
        }

        self::$envCache = $values;
        return self::$envCache;
    }

    private static function preferVisibleUrl(string $baseUrl, Request $request): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($baseUrl === '') {
            return $baseUrl;
        }

        $parts = parse_url($baseUrl);
        if (!is_array($parts) || !isset($parts['host'])) {
            return $baseUrl;
        }

        $configuredScheme = strtolower((string)($parts['scheme'] ?? 'http'));
        $configuredHost = strtolower((string)($parts['host'] ?? ''));
        $requestScheme = self::detectedScheme($request);
        $requestHost = strtolower((string)parse_url(self::requestOrigin($request), PHP_URL_HOST));

        if ($configuredScheme === 'http' && $requestScheme === 'https' && $configuredHost !== '' && $configuredHost === $requestHost) {
            $parts['scheme'] = 'https';
            return self::unparseUrl($parts);
        }

        return $baseUrl;
    }

    private static function detectedScheme(Request $request): string
    {
        foreach (['x-forwarded-proto', 'x-forwarded-scheme', 'x-scheme'] as $header) {
            $value = strtolower(trim((string)$request->header($header, '')));
            if ($value === '') {
                continue;
            }

            $proto = trim((string)(explode(',', $value)[0] ?? ''));
            if (in_array($proto, ['http', 'https'], true)) {
                return $proto;
            }
        }

        $cfVisitor = trim((string)$request->header('cf-visitor', ''));
        if ($cfVisitor !== '') {
            $decoded = json_decode($cfVisitor, true);
            if (is_array($decoded)) {
                $proto = strtolower(trim((string)($decoded['scheme'] ?? '')));
                if (in_array($proto, ['http', 'https'], true)) {
                    return $proto;
                }
            }
        }

        if ((string)$request->header('front-end-https', '') === 'on') {
            return 'https';
        }

        if (strtolower(trim((string)$request->header('x-forwarded-ssl', ''))) === 'on') {
            return 'https';
        }

        if ((string)$request->header('x-forwarded-port', '') === '443') {
            return 'https';
        }

        return 'http';
    }

    private static function forwardedHost(Request $request): string
    {
        $forwardedHost = trim((string)$request->header('x-forwarded-host', ''));
        if ($forwardedHost !== '') {
            $host = trim((string)(explode(',', $forwardedHost)[0] ?? ''));
            if ($host !== '') {
                return $host;
            }
        }

        $host = trim((string)$request->host());
        if ($host === '') {
            $host = trim((string)$request->header('host', '127.0.0.1:8787'));
        }

        return $host !== '' ? $host : '127.0.0.1:8787';
    }

    private static function unparseUrl(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? ($parts['scheme'] . '://') : '';
        $user = (string)($parts['user'] ?? '');
        $pass = (string)($parts['pass'] ?? '');
        $auth = $user !== '' ? ($user . ($pass !== '' ? ':' . $pass : '') . '@') : '';
        $host = (string)($parts['host'] ?? '');
        $port = isset($parts['port']) ? (':' . $parts['port']) : '';
        $path = (string)($parts['path'] ?? '');
        $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
        $fragment = isset($parts['fragment']) ? ('#' . $parts['fragment']) : '';

        return $scheme . $auth . $host . $port . $path . $query . $fragment;
    }
}
