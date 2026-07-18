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
        $config = SystemConfig::all();
        $mode = $this->storefrontMode($config);
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
            'mode' => $mode,
            'site_name' => $this->displaySiteName((string)($config['sitename'] ?? 'AiPay')),
            'home_url' => $this->validatedUrl((string)($config['home_url'] ?? '')),
            'is_logged_in' => $this->merchantFromRequest($request) !== null,
            'affiliate_id' => trim((string)$request->get('aff', '')),
            'merchant_login_url' => $this->merchantLoginUrl($request),
            'merchant_register_url' => FrontendUrlBuilder::merchantRegisterUrl($request),
            'public_home_url' => $this->publicHomeUrl($request),
            'news_index_url' => $this->publicNewsIndexUrl($request),
            'doc_url' => $this->publicDocUrl($request),
            'demo_url' => FrontendUrlBuilder::publicDemoUrl($request),
            'legacy_url' => $this->publicHomeUrl($request),
            'news_sections' => $sections,
            'navs' => $navs,
            'summary' => [
                'nav_count' => count($navs),
                'news_count' => array_sum(array_map(static fn (array $item): int => (int)($item['count'] ?? 0), $sections)),
                'doc_routes' => 4,
            ],
            'route_policy' => $this->routePolicy(),
            'write_actions' => [
                'home_theme_switch' => false,
                'nav_write' => false,
                'news_write' => false,
            ],
            'migration_guard' => [
                'read_only' => true,
                'blocked_actions' => ['home_theme_switch', 'nav_write', 'news_write'],
            ],
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
            'mode' => $isCategoryMode ? 'categories' : 'index',
            'type' => $resolvedType,
            'type_label' => $this->newsTypeLabel($resolvedType),
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'records' => array_map(fn ($row): array => $this->formatNewsSummary((array)$row), $rows),
            'navs' => $this->navItems($request),
            'merchant_register_url' => FrontendUrlBuilder::merchantRegisterUrl($request),
            'public_home_url' => $this->publicHomeUrl($request),
            'demo_url' => FrontendUrlBuilder::publicDemoUrl($request),
            'summary' => [
                'total_count' => $total,
                'type' => $resolvedType,
                'type_label' => $this->newsTypeLabel($resolvedType),
            ],
            'route_policy' => $this->routePolicy(),
            'write_actions' => [
                'news_write' => false,
                'news_delete' => false,
            ],
            'migration_guard' => [
                'read_only' => true,
                'blocked_actions' => ['news_write', 'news_delete'],
            ],
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
                'public_home_url' => $this->publicHomeUrl($request),
                'legacy_url' => $this->publicHomeUrl($request),
                'route_policy' => $this->routePolicy(),
                'migration_guard' => [
                    'read_only' => true,
                    'blocked_actions' => $this->publicWriteActions(),
                ],
            ];
        }

        return [
            'status_code' => 200,
            'site_name' => $this->displaySiteName((string)(SystemConfig::get('sitename', 'AiPay'))),
            'is_logged_in' => $this->merchantFromRequest($request) !== null,
            'article' => $this->formatNewsDetail($item),
            'navs' => $this->navItems($request),
            'merchant_register_url' => FrontendUrlBuilder::merchantRegisterUrl($request),
            'public_home_url' => $this->publicHomeUrl($request),
            'demo_url' => FrontendUrlBuilder::publicDemoUrl($request),
            'legacy_url' => $this->publicHomeUrl($request),
            'route_policy' => $this->routePolicy(),
            'write_actions' => [
                'news_write' => false,
                'news_delete' => false,
            ],
            'migration_guard' => [
                'read_only' => true,
                'blocked_actions' => ['news_write', 'news_delete'],
            ],
        ];
    }

    private function docPayload(Request $request, string $section): array
    {
        $origin = $this->requestOrigin($request);
        $sectionMeta = $this->docSectionMeta($section);

        return [
            'site_name' => $this->displaySiteName((string)(SystemConfig::get('sitename', 'AiPay'))),
            'section' => $sectionMeta['id'],
            'section_label' => $sectionMeta['label'],
            'section_description' => $sectionMeta['description'],
            'is_logged_in' => $this->merchantFromRequest($request) !== null,
            'navs' => $this->navItems($request),
            'merchant_login_url' => $this->merchantLoginUrl($request),
            'merchant_register_url' => FrontendUrlBuilder::merchantRegisterUrl($request),
            'public_home_url' => $this->publicHomeUrl($request),
            'demo_url' => FrontendUrlBuilder::publicDemoUrl($request),
            'legacy_url' => $this->publicHomeUrl($request),
            'docs' => $this->docSections($request, $origin),
            'route_policy' => $this->routePolicy(),
            'write_actions' => [
                'doc_write' => false,
                'example_export' => false,
            ],
            'migration_guard' => [
                'read_only' => true,
                'blocked_actions' => ['doc_write', 'example_export'],
            ],
        ];
    }

    private function storefrontMode(array $config): string
    {
        $switch = trim((string)($config['is_weboff'] ?? '1'));
        if ($switch === '' || $switch === '1') {
            return 'local_home';
        }

        if ($switch === '2' && $this->validatedUrl((string)($config['home_url'] ?? '')) !== null) {
            return 'external_home';
        }

        return 'redirect_login';
    }

    private function homePage(array $payload): string
    {
        $siteName = $this->escape($this->displaySiteName((string)($payload['site_name'] ?? 'AiPay')));
        $navs = (array)($payload['navs'] ?? []);
        $sections = (array)($payload['news_sections'] ?? []);
        $summary = (array)($payload['summary'] ?? []);
        $isLoggedIn = !empty($payload['is_logged_in']);
        $affiliateId = trim((string)($payload['affiliate_id'] ?? ''));
        $merchantLoginUrl = $this->escape((string)($payload['merchant_login_url'] ?? '#'));
        $docUrl = $this->escape((string)($payload['doc_url'] ?? '/doc'));
        $newsIndexUrl = $this->escape((string)($payload['news_index_url'] ?? '/news/index'));
        $publicHomeUrl = $this->escape((string)($payload['public_home_url'] ?? '/'));

        $navHtml = '';
        foreach ($navs as $nav) {
            $item = (array)$nav;
            $target = !empty($item['new_window']) ? ' target="_blank" rel="noreferrer"' : '';
            $navHtml .= '<a href="' . $this->escape((string)($item['url'] ?? '#')) . '"' . $target . '>'
                . '<strong>' . $this->escape((string)($item['name'] ?? '导航')) . '</strong>'
                . '<span>' . $this->escape((string)($item['url'] ?? '')) . '</span>'
                . '</a>';
        }
        if ($navHtml === '') {
            $navHtml = '<div class="empty">当前暂未发布公开导航。</div>';
        }

        $sectionHtml = '';
        foreach ($sections as $section) {
            $newsCards = '';
            foreach ((array)($section['items'] ?? []) as $article) {
                $newsCards .= '<article class="news-card">'
                    . '<span>' . $this->escape((string)($article['date_label'] ?? '--')) . '</span>'
                    . '<h3><a href="/news/detail/' . (int)($article['id'] ?? 0) . '">' . $this->escape((string)($article['title'] ?? '未命名公告')) . '</a></h3>'
                    . '<p>' . $this->escape((string)($article['excerpt'] ?? '')) . '</p>'
                    . '</article>';
            }
            if ($newsCards === '') {
                $newsCards = '<div class="empty">当前分类下暂未发布内容。</div>';
            }

            $sectionHtml .= '<section class="panel">'
                . '<div class="panel-head"><h2>' . $this->escape((string)($section['type_label'] ?? '公告')) . '</h2>'
                . '<a href="' . $this->escape((string)($section['path'] ?? '/news/categories/1')) . '">查看全部</a></div>'
                . '<div class="news-grid">' . $newsCards . '</div></section>';
        }

        $affiliateHtml = $affiliateId !== ''
            ? '<div class="notice">检测到推广标识：' . $this->escape($affiliateId) . '。页面会保留该标识用于来源识别，后续下单与登录仍以实际业务流程为准。</div>'
            : '';

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$siteName} 前台首页</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f7f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1160px;margin:0 auto 20px;padding:30px;border-radius:28px;background:linear-gradient(135deg,#0f172a,#0f766e);color:#fff;box-shadow:0 24px 70px rgba(15,23,42,.16)}
    .eyebrow{display:inline-flex;padding:6px 10px;border-radius:999px;background:rgba(255,255,255,.12);font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
    h1{margin:18px 0 10px;font-size:34px}
    .hero p{margin:0;color:#dbeafe;line-height:1.8;max-width:760px}
    .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
    .btn{display:inline-flex;padding:11px 15px;border-radius:12px;background:#fff;color:#0f172a;text-decoration:none;font-weight:600}
    .btn.secondary{background:rgba(255,255,255,.12);color:#fff}
    .stats{max-width:1160px;margin:0 auto;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
    .card,.panel{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:18px;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    .card span{display:block;color:#64748b;font-size:13px;margin-bottom:8px}
    .card strong{display:block;font-size:24px}
    .nav-grid,.news-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
    .nav-grid a,.news-card{display:block;padding:16px;border-radius:16px;background:#fff;border:1px solid #e2e8f0;text-decoration:none;color:#0f172a}
    .nav-grid a strong{display:block;margin-bottom:8px}
    .nav-grid a span,.news-card span{display:block;color:#64748b;font-size:13px}
    .news-card h3{margin:10px 0 8px;font-size:18px}
    .news-card h3 a{text-decoration:none;color:#0f172a}
    .news-card p{margin:0;color:#475569;line-height:1.7}
    .panel{max-width:1160px;margin:14px auto 0}
    .panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
    .panel-head h2{margin:0;font-size:22px}
    .panel-head a{color:#0f766e;text-decoration:none;font-weight:600}
    .notice{max-width:1160px;margin:14px auto 0;padding:16px 18px;border-radius:16px;background:#ecfeff;border:1px solid #a5f3fc;color:#155e75}
    .empty{padding:16px;border-radius:14px;background:#f8fafc;color:#64748b}
    @media (max-width:980px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.nav-grid,.news-grid{grid-template-columns:1fr 1fr}}
    @media (max-width:640px){.shell{padding:18px}.stats,.nav-grid,.news-grid{grid-template-columns:1fr}h1{font-size:28px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <span class="eyebrow">公开站点</span>
      <h1>{$siteName}</h1>
      <p>当前公开首页、导航与公告中心已稳定上线，您可以在这里查看平台动态、接入说明与公开入口信息。</p>
      <div class="actions">
        <a class="btn" href="{$merchantLoginUrl}">{$this->escape($isLoggedIn ? '进入商户中心' : '商户登录')}</a>
        <a class="btn secondary" href="{$docUrl}">接入文档</a>
        <a class="btn secondary" href="{$newsIndexUrl}">公告中心</a>
      </div>
    </section>
    <section class="stats">
      <div class="card"><span>导航数量</span><strong>{$this->escape((string)($summary['nav_count'] ?? 0))}</strong></div>
      <div class="card"><span>公告数量</span><strong>{$this->escape((string)($summary['news_count'] ?? 0))}</strong></div>
      <div class="card"><span>文档分栏</span><strong>{$this->escape((string)($summary['doc_routes'] ?? 0))}</strong></div>
      <div class="card"><span>商户状态</span><strong>{$this->escape($isLoggedIn ? '已登录' : '访客')}</strong></div>
    </section>
    {$affiliateHtml}
    <section class="panel">
      <div class="panel-head"><h2>公开导航</h2><a href="{$publicHomeUrl}">统一入口</a></div>
      <div class="nav-grid">{$navHtml}</div>
    </section>
    {$sectionHtml}
  </div>
</body>
</html>
HTML;
    }

    private function externalHomePage(array $payload): string
    {
        $siteName = $this->escape($this->displaySiteName((string)($payload['site_name'] ?? 'AiPay')));
        $homeUrl = $this->escape((string)($payload['home_url'] ?? ''));
        $merchantLoginUrl = $this->escape((string)($payload['merchant_login_url'] ?? '#'));
        $publicHomeUrl = $this->escape((string)($payload['public_home_url'] ?? '/'));

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$siteName} 外部首页</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f7f8fb;color:#172033}
    .shell{min-height:100vh;padding:24px}
    .hero{max-width:1160px;margin:0 auto 14px;padding:24px;border-radius:22px;background:#fff;border:1px solid #dbeafe;box-shadow:0 18px 48px rgba(15,23,42,.08)}
    .hero h1{margin:0 0 10px;font-size:28px}
    .hero p{margin:0;color:#475569;line-height:1.8}
    .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
    .btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#0f172a;color:#fff;text-decoration:none}
    iframe{display:block;width:100%;height:calc(100vh - 220px);border:0;border-radius:20px;background:#fff;box-shadow:0 18px 48px rgba(15,23,42,.08)}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>{$siteName} 外部首页</h1>
      <p>当前公开首页已配置为跳转到外部站点，系统会继续保留商户登录与统一入口，便于运营和验收。</p>
      <div class="actions">
        <a class="btn" href="{$homeUrl}" target="_blank" rel="noreferrer">打开外部站点</a>
        <a class="btn" href="{$merchantLoginUrl}">商户登录</a>
        <a class="btn" href="{$publicHomeUrl}">统一入口</a>
      </div>
    </section>
    <iframe src="{$homeUrl}" title="外部前台首页"></iframe>
  </div>
</body>
</html>
HTML;
    }

    private function newsListPage(array $payload, bool $isCategoryMode): string
    {
        $siteName = $this->escape($this->displaySiteName((string)($payload['site_name'] ?? 'AiPay')));
        $recordsHtml = '';
        foreach ((array)($payload['records'] ?? []) as $record) {
            $item = (array)$record;
            $recordsHtml .= '<article class="news-card">'
                . '<div class="badge">' . $this->escape((string)($item['type_label'] ?? '公告')) . '</div>'
                . '<span class="date">' . $this->escape((string)($item['date_label'] ?? '--')) . '</span>'
                . '<h2><a href="/news/detail/' . (int)($item['id'] ?? 0) . '">' . $this->escape((string)($item['title'] ?? '未命名公告')) . '</a></h2>'
                . '<p>' . $this->escape((string)($item['excerpt'] ?? '')) . '</p>'
                . '</article>';
        }

        if ($recordsHtml === '') {
            $recordsHtml = '<div class="empty">当前分类下暂无已发布公告。</div>';
        }

        $label = $this->escape((string)($payload['type_label'] ?? '公告中心'));
        $modeText = $isCategoryMode ? '分类公告页' : '公告中心';

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$siteName} {$label}</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f7f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1080px;margin:0 auto 18px;padding:28px;border-radius:24px;background:linear-gradient(135deg,#0f172a,#7c3aed);color:#fff;box-shadow:0 22px 64px rgba(15,23,42,.16)}
    .hero h1{margin:0 0 10px;font-size:32px}
    .hero p{margin:0;color:#ddd6fe;line-height:1.8}
    .panel{max-width:1080px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:18px;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    .news-card{padding:18px 0;border-bottom:1px solid #e2e8f0}
    .news-card:last-child{border-bottom:0}
    .badge{display:inline-flex;padding:5px 9px;border-radius:999px;background:#ede9fe;color:#6d28d9;font-size:12px;font-weight:700}
    .date{display:block;margin-top:10px;color:#64748b;font-size:13px}
    .news-card h2{margin:10px 0;font-size:24px}
    .news-card h2 a{text-decoration:none;color:#0f172a}
    .news-card p{margin:0;color:#475569;line-height:1.8}
    .topbar{max-width:1080px;margin:0 auto 14px;display:flex;gap:10px;flex-wrap:wrap}
    .btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#fff;border:1px solid #dbeafe;color:#0f172a;text-decoration:none}
    .empty{padding:16px;border-radius:14px;background:#f8fafc;color:#64748b}
    @media (max-width:640px){.shell{padding:18px}.hero h1{font-size:28px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>{$label}</h1>
      <p>当前{$modeText}已稳定上线，公开阅读由当前前台页面直接提供；公告发布与编辑仍统一在已审计的管理后台中完成。</p>
    </section>
    <div class="topbar">
      <a class="btn" href="/">前台首页</a>
      <a class="btn" href="/news/categories/1">平台公告</a>
      <a class="btn" href="/news/categories/2">行业资讯</a>
      <a class="btn" href="/news/categories/3">常见问题</a>
    </div>
    <section class="panel">{$recordsHtml}</section>
  </div>
</body>
</html>
HTML;
    }

    private function newsDetailPage(array $payload): string
    {
        $article = (array)($payload['article'] ?? []);
        $siteName = $this->escape($this->displaySiteName((string)($payload['site_name'] ?? 'AiPay')));
        $title = $this->escape((string)($article['title'] ?? '公告详情'));
        $typeLabel = $this->escape((string)($article['type_label'] ?? '公告'));
        $dateLabel = $this->escape((string)($article['date_label'] ?? '--'));
        $content = (string)($article['content_html'] ?? '');

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$siteName} {$title}</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f7f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .article{max-width:920px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:22px;padding:28px;box-shadow:0 18px 48px rgba(15,23,42,.08)}
    .badge{display:inline-flex;padding:5px 10px;border-radius:999px;background:#dcfce7;color:#166534;font-size:12px;font-weight:700}
    h1{margin:14px 0 10px;font-size:34px}
    .meta{color:#64748b;font-size:14px}
    .content{margin-top:22px;line-height:1.9;color:#334155}
    .content img{max-width:100%;height:auto;border-radius:14px}
    .content pre{white-space:pre-wrap;background:#0f172a;color:#e2e8f0;padding:14px;border-radius:14px;overflow:auto}
    .actions{max-width:920px;margin:14px auto 0;display:flex;gap:10px;flex-wrap:wrap}
    .btn{display:inline-flex;padding:10px 14px;border-radius:12px;background:#fff;border:1px solid #dbeafe;color:#0f172a;text-decoration:none}
    @media (max-width:640px){.shell{padding:18px}h1{font-size:28px}}
  </style>
</head>
<body>
  <div class="shell">
    <article class="article">
      <span class="badge">{$typeLabel}</span>
      <h1>{$title}</h1>
      <div class="meta">发布时间 {$dateLabel}</div>
      <section class="content">{$content}</section>
    </article>
    <div class="actions">
      <a class="btn" href="/news/categories/{$this->escape((string)($article['type'] ?? 1))}">返回{$typeLabel}</a>
      <a class="btn" href="/">前台首页</a>
    </div>
  </div>
</body>
</html>
HTML;
    }

    private function newsMissingPage(array $payload): string
    {
        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>公告不存在</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#fff7ed;color:#7c2d12}
    main{max-width:640px;margin:10vh auto;padding:28px;background:#fff;border:1px solid #fed7aa;border-radius:20px;box-shadow:0 18px 48px rgba(124,45,18,.08)}
    h1{margin:0 0 10px;font-size:28px}
    p{line-height:1.8}
    a{display:inline-flex;margin-top:12px;padding:10px 14px;border-radius:12px;background:#9a3412;color:#fff;text-decoration:none}
  </style>
</head>
<body>
  <main>
    <h1>公告不存在</h1>
    <p>你访问的公告不存在，或已不在当前公开公告列表中展示。</p>
    <a href="/news/index">返回公告中心</a>
  </main>
</body>
</html>
HTML;
    }

    private function docPage(array $payload): string
    {
        $siteName = $this->escape($this->displaySiteName((string)($payload['site_name'] ?? 'AiPay')));
        $sectionLabel = $this->escape((string)($payload['section_label'] ?? '文档中心'));
        $sectionDescription = $this->escape((string)($payload['section_description'] ?? ''));
        $docs = (array)($payload['docs'] ?? []);
        $currentSection = (string)($payload['section'] ?? 'overview');

        $tabsHtml = '';
        $routes = [
            'overview' => '/doc',
            'api' => '/doc/api',
            'result' => '/doc/result',
            'findorder' => '/doc/findorder',
        ];
        foreach ($routes as $key => $href) {
            $active = $currentSection === $key ? ' active' : '';
            $tabsHtml .= '<a class="tab' . $active . '" href="' . $href . '">' . $this->escape($this->docSectionMeta($key)['label']) . '</a>';
        }

        $panelHtml = '';
        foreach ($docs as $group) {
            $itemsHtml = '';
            foreach ((array)($group['items'] ?? []) as $item) {
                $itemsHtml .= '<tr>'
                    . '<th>' . $this->escape((string)($item['label'] ?? 'Field')) . '</th>'
                    . '<td>' . $this->escape((string)($item['value'] ?? '')) . '</td>'
                    . '</tr>';
            }
            $panelHtml .= '<section class="panel"><h2>' . $this->escape((string)($group['title'] ?? 'Section')) . '</h2>'
                . '<p>' . $this->escape((string)($group['description'] ?? '')) . '</p>'
                . '<table>' . $itemsHtml . '</table></section>';
        }

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$siteName} {$sectionLabel}</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f7f8fb;color:#172033}
    .shell{min-height:100vh;padding:28px}
    .hero{max-width:1080px;margin:0 auto 18px;padding:28px;border-radius:24px;background:linear-gradient(135deg,#0f172a,#155e75);color:#fff;box-shadow:0 22px 64px rgba(15,23,42,.16)}
    .hero h1{margin:0 0 10px;font-size:32px}
    .hero p{margin:0;color:#cbd5e1;line-height:1.8}
    .tabs{max-width:1080px;margin:0 auto 14px;display:flex;gap:10px;flex-wrap:wrap}
    .tab{display:inline-flex;padding:10px 14px;border-radius:12px;background:#fff;border:1px solid #dbeafe;color:#0f172a;text-decoration:none}
    .tab.active{background:#0f172a;color:#fff;border-color:#0f172a}
    .panel{max-width:1080px;margin:14px auto 0;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:18px;box-shadow:0 14px 36px rgba(15,23,42,.06)}
    .panel h2{margin:0 0 8px;font-size:22px}
    .panel p{margin:0 0 16px;color:#475569;line-height:1.8}
    table{width:100%;border-collapse:collapse}
    th,td{padding:12px 0;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}
    th{width:220px;color:#475569}
    @media (max-width:640px){.shell{padding:18px}.hero h1{font-size:28px}th{width:120px}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <h1>{$sectionLabel}</h1>
      <p>{$sectionDescription}</p>
    </section>
    <div class="tabs">{$tabsHtml}</div>
    {$panelHtml}
  </div>
</body>
</html>
HTML;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function docSections(Request $request, string $origin): array
    {
        return [
            [
                'id' => 'overview',
                'title' => '核心支付入口',
                'description' => '以下地址用于提交支付、处理异步通知与同步返回，便于商户接入时直接引用。',
                'items' => [
                    ['label' => '浏览器提交', 'value' => $origin . '/submit.php'],
                    ['label' => 'MAPI 提交', 'value' => $origin . '/mapi.php'],
                    ['label' => '异步通知', 'value' => $origin . '/Notify/epay_notifyzj'],
                    ['label' => '同步返回', 'value' => $origin . '/Notify/epay_returnzj'],
                ],
            ],
            [
                'id' => 'api',
                'title' => '辅助地址与商户入口',
                'description' => '以下辅助地址可用于二维码生成、拉起支付和商户后台登录。',
                'items' => [
                    ['label' => '二维码生成', 'value' => $origin . '/qrcode.php?text=https%3A%2F%2Fpay.%E4%BD%A0%E7%9A%84%E5%9F%9F%E5%90%8D.com&size=180'],
                    ['label' => '支付宝拉起地址', 'value' => $origin . '/url.php?user_id=10001&price=1.00&trade_no=TEST202607170001'],
                    ['label' => '商户登录', 'value' => $this->merchantLoginUrl($request)],
                    ['label' => '商户接口信息', 'value' => FrontendUrlBuilder::merchantUrl($request, '/merchant/api')],
                ],
            ],
            [
                'id' => 'result',
                'title' => '回调处理说明',
                'description' => '支付结果与返回处理仍然基于回调。当前公开文档仅保留稳定的回调地址说明，商户侧写入类操作默认关闭，待新版流程完成后再开放。',
                'items' => [
                    ['label' => '异步通知方式', 'value' => 'GET 或 POST'],
                    ['label' => '异步通知地址', 'value' => $origin . '/Notify/epay_notifyzj'],
                    ['label' => '同步返回方式', 'value' => 'GET'],
                    ['label' => '同步返回地址', 'value' => $origin . '/Notify/epay_returnzj'],
                ],
            ],
            [
                'id' => 'findorder',
                'title' => '订单查询指引',
                'description' => '订单查询请在商户中心中按权限查看，以下地址保留给当前登录商户使用。',
                'items' => [
                    ['label' => '商户订单列表', 'value' => FrontendUrlBuilder::merchantUrl($request, '/merchant/orders')],
                    ['label' => '订单检索方式', 'value' => '进入商户订单中心后按订单号或商户单号搜索'],
                    ['label' => '适用范围', 'value' => '仅限当前登录商户，需要 front_token 会话'],
                    ['label' => '写入支持', 'value' => '当前仅开放查询与查看，不开放公开写入动作'],
                ],
            ],
        ];
    }

    private function docSectionMeta(string $section): array
    {
        return match ($section) {
            'api' => [
                'id' => 'api',
                'label' => '接入文档',
                'description' => '整理二维码、支付拉起、登录和接口地址，便于商户接入与排查。',
            ],
            'result' => [
                'id' => 'result',
                'label' => '回调说明',
                'description' => '说明支付结果通知与浏览器返回地址的使用方式，便于接入系统完成验签与状态确认。',
            ],
            'findorder' => [
                'id' => 'findorder',
                'label' => '订单查询指引',
                'description' => '说明订单查询入口与适用范围，帮助商户快速定位订单状态。',
            ],
            default => [
                'id' => 'overview',
                'label' => '文档首页',
                'description' => '汇总当前可直接使用的支付入口、辅助地址和订单查询指引。',
            ],
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

        return str_replace(
            [
                'AiPay 已完成 Webman 架构升级，商户可通过首页完成注册、登录与支付接入。',
                '如需对接支付、回调或订单查询，请前往开发文档查看完整说明。',
                '当前为本地纯净预览环境。'
            ],
            [
                '欢迎使用 AiPay，商户可在首页完成注册、登录与支付接入。',
                '如需接入支付、回调或订单查询，请前往开发文档查看完整说明。',
                '欢迎使用 AiPay。'
            ],
            $value
        );
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
        if ($trimmed === '' || in_array($trimmed, ['AiPay', 'AiPay Smoke', 'AiPay 演示站', 'Puple', 'Purple'], true)) {
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
            'data' => $payload,
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

        $message = '公开页面当前仅提供浏览访问，写入、提交和变更操作均已关闭。';
        $message = ApiResponse::normalizeText($message);
        if ($this->wantsJson($request)) {
            return json([
                'code' => 405,
                'msg' => $message,
                'message' => $message,
                'data' => [
                    'route_policy' => $this->routePolicy(),
                    'migration_guard' => [
                        'read_only' => true,
                        'blocked_actions' => $this->publicWriteActions(),
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)->withStatus(405);
        }

        return response($this->methodNotAllowedPage($message), 405, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function routePolicy(): array
    {
        return [
            'strategy' => 'public_content_online_read_only',
            'public_status' => 'online',
            'allowed_methods' => ['GET', 'HEAD'],
            'write_policy' => 'always_405',
            'write_surface' => 'admin_console_only',
            'legacy_routes' => [
                '/Index/index',
                '/News/Index',
                '/News/Categories',
                '/News/Detail',
                '/Doc/index',
                '/Doc/api',
                '/Doc/result',
                '/Doc/findorder',
            ],
            'blocked_actions' => $this->publicWriteActions(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function publicWriteActions(): array
    {
        return [
            'home_theme_switch',
            'nav_write',
            'news_write',
            'news_delete',
            'doc_write',
            'example_export',
        ];
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
  <title>公开页面只读模式</title>
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
    <h1>公开页面只读模式</h1>
    <p>{$safeMessage}</p>
    <div class="actions">
      <a href="/">返回公开首页</a>
      <a href="/doc">查看接入文档</a>
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

    private function adminLoginUrl(Request $request): string
    {
        return FrontendUrlBuilder::adminLoginUrl($request);
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

    private function validatedUrl(string $value): ?string
    {
        $url = trim($value);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $url;
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
