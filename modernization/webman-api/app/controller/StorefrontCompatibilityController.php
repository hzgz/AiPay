<?php

declare(strict_types=1);

namespace app\controller;

use app\support\ApiResponse;
use app\support\BusinessTable;
use app\support\FrontendUrlBuilder;
use app\support\MerchantFrontSession;
use app\support\SystemConfig;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class StorefrontCompatibilityController
{
    public function home(Request $request): Response
    {
        if (($blocked = $this->ensureReadMethod($request)) !== null) {
            return $blocked;
        }

        if ($this->wantsJson($request)) {
            return $this->ok($this->homePayload($request));
        }

        return redirect($this->publicHomeUrl($request));
    }

    public function newsIndex(Request $request, ?int $type = null): Response
    {
        if (($blocked = $this->ensureReadMethod($request)) !== null) {
            return $blocked;
        }

        $resolvedType = $this->normalizeNewsType($type ?? (int)$request->get('type', 1));
        $payload = $this->newsListPayload($request, $resolvedType, false);

        if ($this->wantsJson($request)) {
            return $this->ok($payload);
        }

        if ($resolvedType > 1) {
            return redirect(FrontendUrlBuilder::publicNewsCategoryUrl($request, $resolvedType));
        }

        return redirect(FrontendUrlBuilder::publicNewsIndexUrl($request));
    }

    public function newsCategories(Request $request, ?int $type = null): Response
    {
        if (($blocked = $this->ensureReadMethod($request)) !== null) {
            return $blocked;
        }

        $resolvedType = $this->normalizeNewsType($type ?? (int)$request->get('type', 1));
        $payload = $this->newsListPayload($request, $resolvedType, true);

        if ($this->wantsJson($request)) {
            return $this->ok($payload);
        }

        return redirect(FrontendUrlBuilder::publicNewsCategoryUrl($request, $resolvedType));
    }

    public function newsDetail(Request $request, ?int $id = null): Response
    {
        if (($blocked = $this->ensureReadMethod($request)) !== null) {
            return $blocked;
        }

        $newsId = $id ?? (int)$request->get('id', 0);
        $payload = $this->newsDetailPayload($request, $newsId);

        if ((int)($payload['status_code'] ?? 200) !== 200) {
            if ($this->wantsJson($request)) {
                $responsePayload = [
                    'code' => 404,
                    'msg' => '公告不存在',
                    'message' => '公告不存在',
                    'data' => $payload,
                ];
                $responsePayload['msg'] = ApiResponse::normalizeText((string)$responsePayload['msg']);
                $responsePayload['message'] = ApiResponse::normalizeText((string)$responsePayload['message']);

                return json($responsePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)->withStatus(404);
            }

            return redirect(FrontendUrlBuilder::publicNewsIndexUrl($request));
        }

        if ($this->wantsJson($request)) {
            return $this->ok($payload);
        }

        return redirect(FrontendUrlBuilder::publicNewsDetailUrl($request, $newsId));
    }

    public function docIndex(Request $request): Response
    {
        return $this->docPageRedirectResponse($request, 'overview');
    }

    public function docApi(Request $request): Response
    {
        return $this->docPageRedirectResponse($request, 'api');
    }

    public function docResult(Request $request): Response
    {
        return $this->docPageRedirectResponse($request, 'result');
    }

    public function docFindOrder(Request $request): Response
    {
        return $this->docPageRedirectResponse($request, 'findorder');
    }

    public function homeData(Request $request): Response
    {
        if (($blocked = $this->ensureReadMethod($request)) !== null) {
            return $blocked;
        }

        return $this->ok($this->homePayload($request));
    }

    public function newsIndexData(Request $request, ?int $type = null): Response
    {
        if (($blocked = $this->ensureReadMethod($request)) !== null) {
            return $blocked;
        }

        $resolvedType = $type ?? (int)$request->get('type', 1);
        return $this->ok($this->newsListPayload($request, $resolvedType, false));
    }

    public function newsCategoriesData(Request $request, ?int $type = null): Response
    {
        if (($blocked = $this->ensureReadMethod($request)) !== null) {
            return $blocked;
        }

        $resolvedType = $type ?? (int)$request->get('type', 1);
        return $this->ok($this->newsListPayload($request, $resolvedType, true));
    }

    public function newsDetailData(Request $request, ?int $id = null): Response
    {
        if (($blocked = $this->ensureReadMethod($request)) !== null) {
            return $blocked;
        }

        $newsId = $id ?? (int)$request->get('id', 0);
        $payload = $this->newsDetailPayload($request, $newsId);
        if ((int)($payload['status_code'] ?? 200) !== 200) {
            $responsePayload = [
                'code' => 404,
                'msg' => '公告不存在',
                'message' => '公告不存在',
                'data' => $payload,
            ];
            $responsePayload['msg'] = ApiResponse::normalizeText((string)$responsePayload['msg']);
            $responsePayload['message'] = ApiResponse::normalizeText((string)$responsePayload['message']);

            return json($responsePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)->withStatus(404);
        }

        return $this->ok($payload);
    }

    public function docData(Request $request, ?string $section = null): Response
    {
        if (($blocked = $this->ensureReadMethod($request)) !== null) {
            return $blocked;
        }

        $resolvedSection = trim(strtolower((string)($section ?? $request->get('section', 'overview'))));
        if ($resolvedSection === '') {
            $resolvedSection = 'overview';
        }

        return $this->ok($this->docPayload($request, $resolvedSection));
    }

    private function docPageRedirectResponse(Request $request, string $section): Response
    {
        if (($blocked = $this->ensureReadMethod($request)) !== null) {
            return $blocked;
        }

        if ($this->wantsJson($request)) {
            return $this->ok($this->docPayload($request, $section));
        }

        return redirect(FrontendUrlBuilder::publicDocUrl($request, $section === 'overview' ? null : $section));
    }

    private function homePayload(Request $request): array
    {
        $navs = $this->navItems($request);
        $sections = [];
        foreach ([1, 2, 3] as $type) {
            $items = $this->newsItems($type, 5);
            $sections[] = [
                'type' => $type,
                'type_label' => $this->newsTypeLabel($type),
                'items' => $items,
                'count' => count($items),
                'path' => '/news/categories/' . $type,
            ];
        }

        return [
            'site_name' => $this->displaySiteName((string)(SystemConfig::get('sitename', 'AiPay'))),
            'is_logged_in' => $this->merchantFromRequest($request) !== null,
            'merchant_login_url' => $this->merchantLoginUrl($request),
            'merchant_register_url' => FrontendUrlBuilder::merchantRegisterUrl($request),
            'news_index_url' => $this->publicNewsIndexUrl($request),
            'doc_url' => $this->publicDocUrl($request),
            'demo_url' => FrontendUrlBuilder::publicDemoUrl($request),
            'news_sections' => $sections,
            'navs' => $navs,
        ];
    }

    private function newsListPayload(Request $request, int $type, bool $isCategoryMode): array
    {
        $current = max(1, (int)$request->get('current', $request->get('page', 1)));
        $size = max(1, min((int)$request->get('size', $request->get('limit', 10)), 50));
        $resolvedType = $this->normalizeNewsType($type);
        $baseQuery = $this->newsBaseQuery()->where('type', $resolvedType);
        $total = (int)(clone $baseQuery)->count('id');
        $rows = (clone $baseQuery)
            ->orderByDesc('id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        return [
            'site_name' => $this->displaySiteName((string)(SystemConfig::get('sitename', 'AiPay'))),
            'is_logged_in' => $this->merchantFromRequest($request) !== null,
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'records' => array_map(fn ($row): array => $this->formatNewsSummary((array)$row), $rows),
            'navs' => $this->navItems($request),
        ];
    }

    private function newsDetailPayload(Request $request, int $id): array
    {
        $item = null;
        if ($id > 0) {
            $row = $this->newsBaseQuery()
                ->where('id', $id)
                ->first();
            $item = $row ? (array)$row : null;
        }

        if ($item === null) {
            return [
                'status_code' => 404,
                'site_name' => $this->displaySiteName((string)(SystemConfig::get('sitename', 'AiPay'))),
                'navs' => $this->navItems($request),
            ];
        }

        return [
            'status_code' => 200,
            'site_name' => $this->displaySiteName((string)(SystemConfig::get('sitename', 'AiPay'))),
            'is_logged_in' => $this->merchantFromRequest($request) !== null,
            'article' => $this->formatNewsDetail($item),
            'navs' => $this->navItems($request),
        ];
    }

    private function docPayload(Request $request, string $section): array
    {
        $origin = $this->requestOrigin($request);
        $resolvedSection = $this->normalizeDocSection($section);

        return [
            'site_name' => $this->displaySiteName((string)(SystemConfig::get('sitename', 'AiPay'))),
            'section' => $resolvedSection,
            'is_logged_in' => $this->merchantFromRequest($request) !== null,
            'navs' => $this->navItems($request),
            'merchant_login_url' => $this->merchantLoginUrl($request),
            'merchant_register_url' => FrontendUrlBuilder::merchantRegisterUrl($request),
            'docs' => $this->docSections($request, $origin),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function docSections(Request $request, string $origin): array
    {
        return [
            [
                'id' => 'overview',
                'title' => '支付入口',
                'description' => '提交、通知与返回地址。',
                'items' => [
                    ['label' => '浏览器提交', 'value' => $origin . '/submit.php'],
                    ['label' => '接口提交', 'value' => $origin . '/mapi.php'],
                    ['label' => '异步通知', 'value' => $origin . '/Notify/epay_notifyzj'],
                    ['label' => '同步返回', 'value' => $origin . '/Notify/epay_returnzj'],
                ],
            ],
            [
                'id' => 'api',
                'title' => '辅助地址',
                'description' => '二维码、拉起与商户入口。',
                'items' => [
                    ['label' => '二维码生成', 'value' => $origin . '/qrcode.php?text=https%3A%2F%2Fpay.%E4%BD%A0%E7%9A%84%E5%9F%9F%E5%90%8D.com&size=180'],
                    ['label' => '支付宝拉起地址', 'value' => $origin . '/url.php?user_id=10001&price=1.00&trade_no=TEST202607170001'],
                    ['label' => '商户登录', 'value' => $this->merchantLoginUrl($request)],
                    ['label' => '商户接口信息', 'value' => FrontendUrlBuilder::merchantUrl($request, '/merchant/api')],
                ],
            ],
            [
                'id' => 'result',
                'title' => '回调地址',
                'description' => '通知与返回地址。',
                'items' => [
                    ['label' => '异步通知方式', 'value' => 'GET 或 POST'],
                    ['label' => '异步通知地址', 'value' => $origin . '/Notify/epay_notifyzj'],
                    ['label' => '同步返回方式', 'value' => 'GET'],
                    ['label' => '同步返回地址', 'value' => $origin . '/Notify/epay_returnzj'],
                ],
            ],
            [
                'id' => 'findorder',
                'title' => '订单查询',
                'description' => '商户订单查询入口。',
                'items' => [
                    ['label' => '商户订单列表', 'value' => FrontendUrlBuilder::merchantUrl($request, '/merchant/orders')],
                    ['label' => '订单检索方式', 'value' => '在商户订单中心按订单号或商户单号搜索'],
                    ['label' => '适用范围', 'value' => '仅限当前登录商户'],
                    ['label' => '公开页状态', 'value' => '仅提供查看与查询'],
                ],
            ],
        ];
    }

    private function normalizeDocSection(string $section): string
    {
        return match ($section) {
            'api', 'result', 'findorder' => $section,
            default => 'overview',
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function navItems(Request $request): array
    {
        $defaults = $this->defaultPublicNavItems($request);
        $rows = Db::table(BusinessTable::nav())
            ->select('id', 'name', 'url', 'is_target', 'sort')
            ->where('status', 1)
            ->whereNull('delete_time')
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->toArray();

        $items = array_map(function ($row) use ($request): array {
            $item = (array)$row;
            return [
                'id' => (int)($item['id'] ?? 0),
                'name' => trim((string)($item['name'] ?? '')),
                'url' => $this->normalizePublicNavUrl($request, trim((string)($item['url'] ?? ''))),
                'new_window' => (int)($item['is_target'] ?? 0) === 1,
                'sort' => (int)($item['sort'] ?? 0),
            ];
        }, $rows);

        $items = array_values(array_filter(
            $items,
            fn (array $item): bool => !$this->isRestrictedPublicNavUrl((string)($item['url'] ?? ''))
                && !$this->isAuthOnlyPublicNavUrl((string)($item['url'] ?? ''))
                && trim((string)($item['name'] ?? '')) !== ''
        ));

        return $this->mergePublicNavItems($defaults, $items);
    }

    private function normalizePublicNavUrl(Request $request, string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return '/';
        }

        $path = parse_url($trimmed, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return $trimmed;
        }

        $query = parse_url($trimmed, PHP_URL_QUERY);
        $suffix = is_string($query) && $query !== '' ? ('?' . $query) : '';
        $queryParams = [];
        if (is_string($query) && $query !== '') {
            parse_str($query, $queryParams);
        }

        return match (strtolower(rtrim($path, '/'))) {
            '/index/index', '/index' => FrontendUrlBuilder::publicHomeUrl($request),
            '/news', '/news/index' => FrontendUrlBuilder::publicNewsIndexUrl($request),
            '/news/categories' => FrontendUrlBuilder::publicNewsCategoryUrl($request, max(1, (int)($queryParams['type'] ?? 1))),
            '/news/detail' => FrontendUrlBuilder::publicNewsDetailUrl($request, max(1, (int)($queryParams['id'] ?? 1))),
            '/doc', '/doc/index' => FrontendUrlBuilder::publicDocUrl($request),
            '/doc/api' => FrontendUrlBuilder::publicDocUrl($request, 'api'),
            '/doc/result' => FrontendUrlBuilder::publicDocUrl($request, 'result'),
            '/doc/findorder' => FrontendUrlBuilder::publicDocUrl($request, 'findorder'),
            '/demo', '/demo/index' => FrontendUrlBuilder::publicDemoUrl($request),
            '/user/login' => FrontendUrlBuilder::merchantLoginUrl($request),
            '/user/reg' => FrontendUrlBuilder::merchantRegisterUrl($request),
            default => preg_replace(
                ['#^/News/#', '#^/news/#', '#^/Doc/#', '#^/doc/#', '#^/Index/#', '#^/index/#'],
                ['/news/', '/news/', '/doc/', '/doc/', '/', '/'],
                $trimmed
            ) ?? $trimmed,
        };
    }

    private function isRestrictedPublicNavUrl(string $url): bool
    {
        foreach ($this->publicNavRouteCandidates($url) as $normalized) {
            if ($normalized === '') {
                continue;
            }

            if (
                $normalized === '/auth'
                || str_starts_with($normalized, '/auth/')
                || $normalized === '/admin'
                || str_starts_with($normalized, '/admin/')
            ) {
                return true;
            }
        }

        return false;
    }

    private function isAuthOnlyPublicNavUrl(string $url): bool
    {
        foreach ($this->publicNavRouteCandidates($url) as $normalized) {
            if (
                $normalized === '/user/login'
                || $normalized === '/user/reg'
                || $normalized === '/user/register'
                || $normalized === '/user/index'
                || $normalized === '/merchant/login'
                || $normalized === '/merchant/register'
                || $normalized === '/merchant/dashboard'
            ) {
                return true;
            }
        }

        return false;
    }

    private function defaultPublicNavItems(Request $request): array
    {
        return [
            ['id' => 0, 'name' => '首页', 'url' => FrontendUrlBuilder::publicHomeUrl($request), 'new_window' => false, 'sort' => 1],
            ['id' => 0, 'name' => '开发文档', 'url' => FrontendUrlBuilder::publicDocUrl($request), 'new_window' => false, 'sort' => 2],
            ['id' => 0, 'name' => '公告中心', 'url' => FrontendUrlBuilder::publicNewsIndexUrl($request), 'new_window' => false, 'sort' => 3],
            ['id' => 0, 'name' => '支付测试', 'url' => FrontendUrlBuilder::publicDemoUrl($request), 'new_window' => false, 'sort' => 4],
        ];
    }

    private function mergePublicNavItems(array ...$groups): array
    {
        $merged = [];
        $seen = [];

        foreach ($groups as $items) {
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $name = trim((string)($item['name'] ?? ''));
                $url = trim((string)($item['url'] ?? ''));
                if ($name === '' || $url === '') {
                    continue;
                }

                $signature = strtolower($name) . '|' . $this->publicNavRouteKey($url);
                if (isset($seen[$signature])) {
                    continue;
                }

                $seen[$signature] = true;
                $merged[] = $item;
            }
        }

        return $merged;
    }

    private function publicNavRouteKey(string $url): string
    {
        $candidates = $this->publicNavRouteCandidates($url);
        foreach ($candidates as $candidate) {
            if ($candidate !== '/' && !preg_match('~^/[a-z0-9_-]+$~i', $candidate)) {
                return $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '/') {
                return $candidate;
            }
        }

        if ($candidates !== []) {
            return (string)($candidates[0] ?? '');
        }

        return strtolower(rtrim(trim($url), '/'));
    }

    private function publicNavRouteCandidates(string $url): array
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return [];
        }

        $path = parse_url($trimmed, PHP_URL_PATH);
        $fragment = parse_url($trimmed, PHP_URL_FRAGMENT);
        $candidates = [];

        if (is_string($path) && $path !== '') {
            $candidates[] = strtolower(rtrim($path, '/'));
        }

        if (is_string($fragment) && $fragment !== '') {
            $fragmentPath = '/' . ltrim($fragment, '/');
            $candidates[] = strtolower(rtrim($fragmentPath, '/'));
        }

        return array_values(array_unique(array_filter($candidates, static fn (string $item): bool => $item !== '')));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function newsItems(int $type, int $limit): array
    {
        $rows = $this->newsBaseQuery()
            ->where('type', $type)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->toArray();

        return array_map(fn ($row): array => $this->formatNewsSummary((array)$row), $rows);
    }

    private function newsBaseQuery()
    {
        return Db::table(BusinessTable::news())
            ->select('id', 'type', 'title', 'color', 'content', 'status', 'create_time', 'update_time')
            ->where('status', 1)
            ->whereNull('delete_time');
    }

    private function normalizeNewsType(int $type): int
    {
        return in_array($type, [1, 2, 3], true) ? $type : 1;
    }

    private function formatNewsSummary(array $row): array
    {
        $type = $this->normalizeNewsType((int)($row['type'] ?? 1));
        $content = $this->normalizePublicNewsText((string)($row['content'] ?? ''));
        $excerpt = mb_substr($content, 0, 120);
        if ($content !== '' && mb_strlen($content) > 120) {
            $excerpt .= '...';
        }

        return [
            'id' => (int)($row['id'] ?? 0),
            'type' => $type,
            'type_label' => $this->newsTypeLabel($type),
            'title' => $this->normalizePublicNewsText((string)($row['title'] ?? '')),
            'color' => $this->nullableString($row['color'] ?? null),
            'create_time' => $this->nullableString($row['create_time'] ?? null),
            'date_label' => $this->dateLabel((string)($row['create_time'] ?? '')),
            'excerpt' => $excerpt,
        ];
    }

    private function formatNewsDetail(array $row): array
    {
        $summary = $this->formatNewsSummary($row);
        $contentHtml = $this->sanitizeHtmlFragment(
            $this->normalizePublicNewsHtml((string)($row['content'] ?? ''))
        );

        return array_merge($summary, [
            'content_html' => $contentHtml !== '' ? $contentHtml : '<p>暂无公告内容。</p>',
        ]);
    }

    private function normalizePublicNewsText(string $value): string
    {
        return trim(strip_tags($this->normalizePublicNewsHtml($value)));
    }

    private function normalizePublicNewsHtml(string $value): string
    {
        if (trim($value) === '') {
            return '';
        }

        $normalized = str_replace(
            [
                '欢迎使用 AiPay，商户可在首页完成注册、登录与支付接入。',
                '如需接入支付、回调或订单查询，请前往开发文档查看完整说明。',
                '如需对接支付、回调或订单查询，请前往开发文档查看完整说明。'
            ],
            [
                '欢迎使用 AiPay，可在首页完成商户注册、登录与接入配置。',
                '支付接入、回调规则与订单查询请查看开发文档。',
                '支付接入、回调规则与订单查询请查看开发文档。'
            ],
            $value
        );

        $normalized = preg_replace(
            [
                '/AiPay\s+已完成[^<。]{0,40}(?:系统|架构)升级[^<。]{0,80}。?/u',
                '/当前为[^<。]{0,40}预览环境。?/u',
                '/预览环境/u',
            ],
            [
                '欢迎使用 AiPay，可在首页完成商户注册、登录与接入配置。',
                '欢迎使用 AiPay。',
                'AiPay',
            ],
            $normalized
        );

        return is_string($normalized) ? $normalized : $value;
    }

    private function sanitizeHtmlFragment(string $html): string
    {
        $clean = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? '';
        $clean = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $clean) ?? '';
        $clean = preg_replace('/\son\w+\s*=\s*(["\']).*?\1/is', '', $clean) ?? '';
        $clean = preg_replace('/\son\w+\s*=\s*[^\s>]+/is', '', $clean) ?? '';
        $clean = preg_replace('/javascript\s*:/i', '', $clean) ?? '';
        $clean = trim($clean);

        return $clean;
    }

    private function newsTypeLabel(int $type): string
    {
        return match ($type) {
            2 => '行业资讯',
            3 => '常见问题',
            default => '平台公告',
        };
    }

    private function displaySiteName(string $siteName): string
    {
        $trimmed = trim($siteName);
        if ($trimmed === '' || $trimmed === 'AiPay') {
            return 'AiPay';
        }

        return $trimmed;
    }

    private function dateLabel(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '--';
        }

        return date('Y-m-d', $timestamp);
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

    private function ensureReadMethod(Request $request): ?Response
    {
        if (in_array(strtoupper($request->method()), ['GET', 'HEAD'], true)) {
            return null;
        }

        $message = '当前页面仅支持浏览访问。';
        $message = ApiResponse::normalizeText($message);
        if ($this->wantsJson($request)) {
            return json([
                'code' => 405,
                'msg' => $message,
                'message' => $message,
                'data' => [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)->withStatus(405);
        }

        return response($this->methodNotAllowedPage($message), 405, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function methodNotAllowedPage(string $message): string
    {
        $safeMessage = $this->escape($message);

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>访问受限</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#fff7ed;color:#7c2d12}
    main{max-width:680px;margin:10vh auto;padding:28px;background:#fff;border:1px solid #fed7aa;border-radius:22px;box-shadow:0 18px 48px rgba(124,45,18,.08)}
    h1{margin:0 0 12px;font-size:30px}
    p{margin:0;line-height:1.8}
    .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
    a{display:inline-flex;padding:10px 14px;border-radius:12px;background:#9a3412;color:#fff;text-decoration:none}
  </style>
</head>
<body>
  <main>
    <h1>访问受限</h1>
    <p>{$safeMessage}</p>
    <div class="actions">
      <a href="/">返回公开首页</a>
      <a href="/doc">查看开发文档</a>
    </div>
  </main>
</body>
</html>
HTML;
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

    private function merchantFromRequest(Request $request): ?array
    {
        $token = MerchantFrontSession::resolveToken($request);
        if ($token === '') {
            return null;
        }

        $row = Db::table(BusinessTable::user())
            ->select('id', 'username', 'is_frozen')
            ->where('token', $token)
            ->first();

        return $row ? (array)$row : null;
    }

    private function merchantLoginUrl(Request $request): string
    {
        return FrontendUrlBuilder::merchantLoginUrl($request);
    }

    private function publicHomeUrl(Request $request): string
    {
        return FrontendUrlBuilder::publicHomeUrl($request);
    }

    private function publicNewsIndexUrl(Request $request): string
    {
        return FrontendUrlBuilder::publicNewsIndexUrl($request);
    }

    private function publicDocUrl(Request $request): string
    {
        return FrontendUrlBuilder::publicDocUrl($request);
    }

    private function requestOrigin(Request $request): string
    {
        return FrontendUrlBuilder::requestOrigin($request);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string)$value);
        return $text === '' ? null : $text;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
