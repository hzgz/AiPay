<?php

declare(strict_types=1);

namespace app\controller;

use app\support\ApiResponse;
use app\support\BusinessTable;
use app\support\FrontendUrlBuilder;
use app\support\ProductionSecurity;
use app\support\SystemConfig;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class DemoCompatibilityController
{
    public function index(Request $request): Response
    {
        if (($redirect = $this->compatibilityPageRedirect($request)) !== null) {
            return $redirect;
        }

        $payload = $this->demoPayload($request, 'index');
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

        $payload = $this->demoPayload($request, 'success');
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
            '支付测试页已正式下线，当前服务不开放测试订单创建或收银台跳转。',
            ['demo_payment_create', 'demo_payment_handoff']
        );
    }

    public function notifyEpay(Request $request): Response
    {
        if ($this->wantsJson($request)) {
            return $this->blockedWriteResponse(
                '支付测试异步回调已正式下线，当前服务不会处理测试回调写入。',
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

        $payload = $this->demoPayload($request, 'return_callback');
        if ($this->wantsJson($request)) {
            return $this->ok($payload);
        }

        return redirect(FrontendUrlBuilder::publicDemoUrl($request));
    }

    private function demoPayload(Request $request, string $mode): array
    {
        $config = SystemConfig::all();
        $availableMethods = $this->demoMethods((string)($config['diy_demoPay'] ?? ''));
        $gatewayConfigured = $this->gatewayConfigured($config);
        $navs = $this->defaultPublicNavItems($request);
        $demoName = trim((string)($config['demopay_name'] ?? '支付测试'));
        $demoMoney = trim((string)($config['demopay_money'] ?? '0.01'));
        $demoRoutesAllowed = ProductionSecurity::demoRoutesAllowed();

        return [
            'mode' => $mode,
            'site_name' => $this->displaySiteName((string)($config['sitename'] ?? 'AiPay')),
            'demo_name' => $this->displayDemoName($demoName),
            'demo_money' => $demoMoney !== '' ? $demoMoney : '0.01',
            'demo_theme' => trim((string)($config['demo_theme'] ?? '')),
            'gateway_configured' => $gatewayConfigured,
            'gateway_host' => trim((string)($config['epayurl_demo'] ?? '')),
            'available_methods' => $availableMethods,
            'navs' => $navs,
            'public_home_url' => FrontendUrlBuilder::publicHomeUrl($request),
            'merchant_login_url' => FrontendUrlBuilder::merchantLoginUrl($request),
            'admin_url' => FrontendUrlBuilder::adminLoginUrl($request),
            'supports_write' => false,
            'route_policy' => [
                'strategy' => 'demo_routes_decommissioned',
                'production_browser_default' => 'redirect_public_home',
                'compat_query_flag' => 'compat=1',
                'audit_page_available' => true,
                'write_policy' => 'always_405',
                'write_actions' => [
                    'demo_payment_create',
                    'demo_payment_handoff',
                    'demo_notify_callback',
                    'demo_return_callback',
                ],
            ],
            'summary' => [
                'available_method_count' => count($availableMethods),
                'gateway_configured' => $gatewayConfigured,
                'nav_count' => count($navs),
                'read_only' => true,
                'production_enabled' => $demoRoutesAllowed,
            ],
            'write_actions' => [
                'demo_payment_create' => false,
                'demo_payment_handoff' => false,
                'demo_notify_callback' => false,
                'demo_return_callback' => false,
            ],
            'migration_guard' => [
                'read_only' => true,
                'blocked_actions' => [
                    'demo_payment_create',
                    'demo_payment_handoff',
                    'demo_notify_callback',
                    'demo_return_callback',
                ],
            ],
        ];
    }

    private function demoMethods(string $enabledList): array
    {
        $enabled = array_filter(array_map('trim', explode(',', strtolower($enabledList))));
        if ($enabled === []) {
            $enabled = ['wxpay', 'alipay', 'qqpay'];
        }
        $catalog = [
            'alipay' => ['id' => 'alipay', 'label' => '支付宝', 'description' => '当前仅展示状态，不创建测试订单。'],
            'wxpay' => ['id' => 'wxpay', 'label' => '微信支付', 'description' => '当前仅展示状态，不创建测试订单。'],
            'qqpay' => ['id' => 'qqpay', 'label' => 'QQ 支付', 'description' => '当前仅展示状态，不创建测试订单。'],
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

    private function navItems(): array
    {
        $rows = Db::table(BusinessTable::nav())
            ->select('id', 'name', 'url', 'is_target', 'sort')
            ->where('status', 1)
            ->whereNull('delete_time')
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->toArray();

        return array_map(static function ($row): array {
            $item = (array)$row;
            return [
                'id' => (int)($item['id'] ?? 0),
                'name' => trim((string)($item['name'] ?? '')),
                'url' => trim((string)($item['url'] ?? '')),
                'is_target' => (int)($item['is_target'] ?? 0),
                'sort' => (int)($item['sort'] ?? 0),
            ];
        }, $rows);
    }

    private function demoPage(array $payload): string
    {
        $siteName = $this->escape((string)($payload['site_name'] ?? 'AiPay'));
        $demoName = $this->escape((string)($payload['demo_name'] ?? '支付测试'));
        $demoMoney = $this->escape((string)($payload['demo_money'] ?? '0.01'));
        $publicHomeUrl = $this->escape((string)($payload['public_home_url'] ?? '/'));
        $merchantLoginUrl = $this->escape((string)($payload['merchant_login_url'] ?? '/User/Login'));
        $adminUrl = $this->escape((string)($payload['admin_url'] ?? '/'));
        $gatewayConfigured = !empty($payload['gateway_configured']);
        $methodCards = $this->methodCardsHtml((array)($payload['available_methods'] ?? []));
        $navHtml = $this->navLinksHtml((array)($payload['navs'] ?? []));
        $gatewayStatus = $gatewayConfigured ? '已配置' : '未配置';
        $themeLabel = $this->escape($this->displayThemeLabel((string)($payload['demo_theme'] ?? '')));

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>支付测试说明</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:linear-gradient(160deg,#fff7ed,#ecfeff 48%,#f8fafc);color:#0f172a}
    .wrap{max-width:1080px;margin:0 auto;padding:36px 20px 48px}
    .hero{background:rgba(255,255,255,.94);border:1px solid rgba(251,146,60,.18);border-radius:28px;box-shadow:0 24px 80px rgba(15,23,42,.08);padding:28px}
    .eyebrow{display:inline-flex;padding:6px 10px;border-radius:999px;background:#ffedd5;color:#c2410c;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
    h1{margin:16px 0 10px;font-size:34px;line-height:1.2}
    p{margin:0;color:#475569;line-height:1.8}
    .grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-top:22px}
    .stat,.card,.navs{background:#fff;border:1px solid #e2e8f0;border-radius:22px;box-shadow:0 16px 40px rgba(15,23,42,.04)}
    .stat{padding:18px}
    .label{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;font-weight:700}
    .value{margin-top:8px;font-size:20px;font-weight:700}
    .layout{display:grid;grid-template-columns:1.6fr 1fr;gap:18px;margin-top:18px}
    .card{padding:22px}
    .methods{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:16px}
    .method{padding:16px;border-radius:18px;background:linear-gradient(180deg,#fff7ed,#ffffff);border:1px solid #fed7aa}
    .method h3{margin:0 0 6px;font-size:18px}
    .method p{font-size:13px;color:#64748b}
    .method span{display:inline-flex;margin-top:10px;padding:5px 9px;border-radius:999px;background:#fff7ed;color:#c2410c;font-size:12px;font-weight:700}
    .actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px}
    .btn{display:inline-flex;align-items:center;justify-content:center;padding:11px 16px;border-radius:12px;text-decoration:none;font-weight:700}
    .btn-primary{background:#0f172a;color:#fff}
    .btn-secondary{background:#fff;color:#0f172a;border:1px solid #cbd5e1}
    .navs{padding:22px}
    .navs ul{margin:12px 0 0;padding:0;list-style:none;display:grid;gap:10px}
    .navs a{color:#0f172a;text-decoration:none}
    .notice{margin-top:18px;padding:16px 18px;border-radius:18px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;line-height:1.8}
    @media (max-width:860px){.grid,.layout{grid-template-columns:1fr}.methods{grid-template-columns:1fr 1fr}}
    @media (max-width:620px){h1{font-size:28px}.methods{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="wrap">
    <section class="hero">
      <span class="eyebrow">支付测试已下线</span>
      <h1>支付测试说明</h1>
      <p style="margin-top:10px">支付测试入口已停用，不再承接测试下单、测试回调或测试收银流程。</p>
      <p>{$siteName} 当前展示的是支付测试状态页，用于查看可展示的支付方式和公开导航信息。正式业务请使用已启用的支付链路完成下单、回调与结算。</p>
      <div class="grid">
        <div class="stat"><div class="label">测试项目</div><div class="value">{$demoName}</div></div>
        <div class="stat"><div class="label">参考金额</div><div class="value">{$demoMoney}</div></div>
        <div class="stat"><div class="label">测试配置</div><div class="value">{$gatewayStatus}</div></div>
      </div>
      <div class="layout">
        <article class="card">
          <div class="label">已配置展示方式</div>
          <div class="methods">{$methodCards}</div>
          <div class="notice">发送到 <code>/Demo/dopay</code>、<code>/Demo/notify_epay</code> 或 <code>/Demo/return_epay</code> 的请求不会在当前服务内创建测试订单，也不会写入余额、回放回调或模拟支付成功。</div>
          <div class="actions">
            <a class="btn btn-primary" href="{$publicHomeUrl}">返回公开首页</a>
            <a class="btn btn-secondary" href="{$merchantLoginUrl}">商户登录</a>
            <a class="btn btn-secondary" href="{$adminUrl}">管理后台</a>
          </div>
        </article>
        <aside class="navs">
          <div class="label">公开导航</div>
          <div class="value">主题：{$themeLabel}</div>
          <ul>{$navHtml}</ul>
        </aside>
      </div>
    </section>
  </div>
</body>
</html>
HTML;
    }

    private function successPage(array $payload): string
    {
        $publicHomeUrl = $this->escape((string)($payload['public_home_url'] ?? '/'));
        $merchantLoginUrl = $this->escape((string)($payload['merchant_login_url'] ?? '/User/Login'));

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>支付测试完成说明</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:linear-gradient(160deg,#ecfeff,#f8fafc);color:#0f172a}
    .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .card{width:min(680px,100%);background:#fff;border-radius:26px;border:1px solid #bae6fd;box-shadow:0 24px 70px rgba(15,23,42,.08);padding:30px}
    h1{margin:0 0 12px;font-size:30px}
    p{margin:0;color:#475569;line-height:1.8}
    .notice{margin-top:18px;padding:16px;border-radius:18px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;line-height:1.8}
    .actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px}
    a{display:inline-flex;padding:11px 16px;border-radius:12px;text-decoration:none;font-weight:700}
    .primary{background:#0f172a;color:#fff}.secondary{background:#fff;color:#0f172a;border:1px solid #cbd5e1}
  </style>
</head>
<body>
  <div class="wrap">
    <main class="card">
      <h1>支付测试入口已停用</h1>
      <p>该地址当前只保留状态提示，用于承接已有书签和测试链接，避免直接出现空白页；当前不会在这里执行任何入账或结算。</p>
      <div class="notice">正式业务请使用已启用的支付插件和商户通道完成下单与回调处理，不要再依赖支付测试入口验证生产链路。</div>
      <div class="actions">
        <a class="primary" href="{$publicHomeUrl}">返回公开首页</a>
        <a class="secondary" href="{$merchantLoginUrl}">商户登录</a>
      </div>
    </main>
  </div>
</body>
</html>
HTML;
    }

    private function returnCallbackPage(array $payload): string
    {
        $publicHomeUrl = $this->escape((string)($payload['public_home_url'] ?? '/'));
        $successUrl = $this->escape((string)($payload['public_home_url'] ?? '/'));

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>支付测试返回说明</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f8fafc;color:#0f172a}
    .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .card{width:min(720px,100%);background:#fff;border-radius:26px;border:1px solid #e2e8f0;box-shadow:0 24px 70px rgba(15,23,42,.08);padding:30px}
    h1{margin:0 0 12px;font-size:30px}
    p{margin:0;color:#475569;line-height:1.8}
    .notice{margin-top:18px;padding:16px;border-radius:18px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;line-height:1.8}
    .actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px}
    a{display:inline-flex;padding:11px 16px;border-radius:12px;text-decoration:none;font-weight:700}
    .primary{background:#0f172a;color:#fff}.secondary{background:#fff;color:#0f172a;border:1px solid #cbd5e1}
  </style>
</head>
<body>
  <div class="wrap">
    <main class="card">
      <h1>该返回页仅保留说明，不再参与任何测试支付流程</h1>
      <p>该返回地址当前只保留状态提示，用于承接已有书签和测试链接，避免直接出现 404 或空白页。</p>
      <div class="notice">当前返回页不会执行测试订单结算、余额回写、回调重放或支付成功确认。如需体验完整支付链路，请使用现有支付插件与商户通道功能。</div>
      <div class="actions">
        <a class="primary" href="{$successUrl}">返回公开首页</a>
        <a class="secondary" href="{$publicHomeUrl}">查看公开首页</a>
      </div>
    </main>
  </div>
</body>
</html>
HTML;
    }

    private function methodCardsHtml(array $methods): string
    {
        if ($methods === []) {
            return '<div class="method"><h3>当前没有启用展示方式</h3><p>如需在公开页展示测试方式，可在系统配置中启用对应展示项，但它们仍不会创建测试订单。</p><span>未启用</span></div>';
        }

        $html = '';
        foreach ($methods as $method) {
            $item = (array)$method;
            $label = $this->escape((string)($item['label'] ?? '未命名方式'));
            $description = $this->escape((string)($item['description'] ?? ''));
            $html .= '<section class="method"><h3>' . $label . '</h3><p>' . $description . '</p><span>仅说明</span></section>';
        }

        return $html;
    }

    private function navLinksHtml(array $navs): string
    {
        if ($navs === []) {
            return '<li>当前没有已发布的公开导航。</li>';
        }

        $html = '';
        foreach ($navs as $nav) {
            $item = (array)$nav;
            $name = $this->escape((string)($item['name'] ?? '未命名导航'));
            $url = $this->escape((string)($item['url'] ?? '#'));
            $target = (int)($item['is_target'] ?? 0) === 1 ? ' target="_blank" rel="noreferrer"' : '';
            $html .= '<li><a href="' . $url . '"' . $target . '>' . $name . '</a></li>';
        }

        return $html;
    }

    private function displayThemeLabel(string $theme): string
    {
        $normalized = strtolower(trim($theme));

        return match ($normalized) {
            '', 'default' => '标准主题',
            'puple', 'purple' => '紫色主题',
            default => $theme,
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

    private function displayDemoName(string $demoName): string
    {
        $trimmed = trim($demoName);
        if ($trimmed === '' || in_array($trimmed, ['演示支付', 'AiPay Smoke', 'AiPay 演示站', '支付体验', '支付测试'], true)) {
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
            'data' => $payload,
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
                'write_actions' => array_fill_keys($blockedActions, false),
                'migration_guard' => [
                    'read_only' => true,
                    'blocked_actions' => array_values($blockedActions),
                ],
                'route_policy' => [
                    'strategy' => 'demo_routes_decommissioned',
                    'write_policy' => 'always_405',
                ],
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

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
