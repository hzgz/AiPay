<?php

namespace app\controller;

use app\support\FrontendUrlBuilder;
use app\support\QrCodeService;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;

class PublicEntryController
{
    public function indexShell(Request $request): Response
    {
        return response($this->indexShellPage($request), 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function adminShell(Request $request): Response
    {
        return redirect($this->adminLoginUrl($request));
    }

    public function qrcode(Request $request): Response
    {
        $text = trim((string)$request->get('text', ''));
        if ($text === '') {
            return response($this->qrcodeHelpPage($request), 200, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        if (strlen($text) > 4096) {
            return response('二维码内容过长', 413, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        try {
            $size = $this->normalizeQrSize($request->get('size', '180'));
            $png = $this->qrCodeService()->renderPng($text, $size);
            return response($png, 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=300',
            ]);
        } catch (Throwable $exception) {
            error_log('[qrcode] ' . $exception::class . ': ' . $exception->getMessage());
            return response('二维码生成失败', 500, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
    }

    public function alipayUrl(Request $request): Response
    {
        $userId = trim((string)$request->get('user_id', ''));
        if ($userId === '') {
            return response('missing user_id', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $price = trim((string)$request->get('price', ''));
        $tradeNo = trim((string)$request->get('trade_no', ''));
        $bizData = [
            's' => 'money',
            'u' => $userId,
        ];

        if ($price !== '') {
            $bizData['a'] = $price;
        }
        if ($tradeNo !== '') {
            $bizData['m'] = $tradeNo;
        }

        $encodedBizData = rawurlencode(json_encode($bizData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $innerUrl = 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data=' . $encodedBizData;
        $alipayUrl = 'alipayqr://platformapi/startapp?saId=20000032&url=' . urlencode($innerUrl);

        return response($this->alipayUrlPage($alipayUrl), 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function normalizeQrSize(mixed $size): int
    {
        $normalized = (int)$size;
        if ($normalized <= 0) {
            return 180;
        }

        return min($normalized, 1024);
    }

    private function qrcodeHelpPage(Request $request): string
    {
        $origin = htmlspecialchars($this->requestOrigin($request), ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>二维码接口</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f7f8fb;color:#1f2937}
    main{max-width:720px;margin:8vh auto;padding:28px;background:#fff;border-radius:18px;box-shadow:0 18px 50px rgba(15,23,42,.08)}
    h1{margin:0 0 12px;font-size:26px}
    p{line-height:1.8;color:#4b5563}
    code{padding:2px 6px;border-radius:6px;background:#eef2ff}
  </style>
</head>
<body>
  <main>
    <h1>二维码接口</h1>
    <p>使用 <code>text</code> 传入二维码内容，使用 <code>size</code> 控制 PNG 图片像素尺寸。</p>
    <p>示例：<code>{$origin}/qrcode.php?text=https%3A%2F%2Fpay.aipay.local&amp;size=180</code></p>
  </main>
</body>
</html>
HTML;
    }

    private function alipayUrlPage(string $alipayUrl): string
    {
        $safeUrl = htmlspecialchars($alipayUrl, ENT_QUOTES, 'UTF-8');
        $jsonUrl = json_encode($alipayUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>正在打开支付宝</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f7f8fb;color:#1f2937}
    .wrap{max-width:560px;margin:12vh auto 0;padding:28px;text-align:center;background:#fff;border-radius:18px;box-shadow:0 18px 50px rgba(15,23,42,.08)}
    .title{font-size:22px;font-weight:700;margin-bottom:10px}
    .desc{font-size:14px;color:#64748b;line-height:1.8;margin-bottom:20px}
    .btn{display:inline-block;padding:11px 18px;border-radius:10px;color:#fff;text-decoration:none;background:#1677ff}
  </style>
</head>
<body>
<div class="wrap">
  <div class="title">正在打开支付宝</div>
  <div class="desc">如果没有自动拉起支付宝，请点击下方按钮继续。</div>
  <a class="btn" href="{$safeUrl}">打开支付宝</a>
</div>
<script>
(function () {
  var ua = navigator.userAgent || '';
  var isMobile = /(phone|pad|pod|iPhone|iPod|ios|iPad|Android|Mobile|BlackBerry|IEMobile|Windows Phone)/i.test(ua);
  if (isMobile) {
    window.location.href = {$jsonUrl};
  }
})();
</script>
</body>
</html>
HTML;
    }

    private function indexShellPage(Request $request): string
    {
        $adminUrl = htmlspecialchars($this->adminLoginUrl($request), ENT_QUOTES, 'UTF-8');
        $merchantUrl = htmlspecialchars($this->merchantLoginUrl($request), ENT_QUOTES, 'UTF-8');
        $storefrontUrl = htmlspecialchars($this->storefrontHomeUrl($request), ENT_QUOTES, 'UTF-8');
        $healthUrl = htmlspecialchars($this->requestOrigin($request) . '/api/health', ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AiPay 统一入口</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:linear-gradient(135deg,#f8fafc,#e0f2fe);color:#102033}
    .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:28px}
    .card{width:min(760px,100%);background:rgba(255,255,255,.92);border:1px solid rgba(148,163,184,.28);border-radius:24px;box-shadow:0 24px 80px rgba(15,23,42,.12);padding:34px}
    .eyebrow{display:inline-flex;padding:6px 10px;border-radius:999px;background:#e0f2fe;color:#0369a1;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em}
    h1{margin:18px 0 10px;font-size:30px;line-height:1.2}
    p{margin:0;color:#475569;line-height:1.8}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:26px}
    a{display:block;padding:16px;border-radius:16px;background:#fff;color:#0f172a;text-decoration:none;border:1px solid #e2e8f0}
    a strong{display:block;margin-bottom:6px}
    a span{display:block;color:#64748b;font-size:13px;line-height:1.5}
    @media (max-width:720px){.grid{grid-template-columns:1fr}.card{padding:24px}h1{font-size:24px}}
  </style>
</head>
<body>
  <div class="wrap">
    <main class="card">
      <span class="eyebrow">统一访问入口</span>
      <h1>AiPay 业务入口</h1>
      <p>管理后台、商户中心与公开站点已统一接入当前系统服务，以下入口可直接用于日常运营、商户登录、对外展示与接口巡检。</p>
      <div class="grid">
        <a href="{$adminUrl}">
          <strong>管理后台</strong>
          <span>打开管理员登录页与平台运营工作台。</span>
        </a>
        <a href="{$merchantUrl}">
          <strong>商户中心</strong>
          <span>打开商户登录页与商户经营中心。</span>
        </a>
        <a href="{$storefrontUrl}">
          <strong>公开站点</strong>
          <span>查看对外展示首页、导航与平台公告。</span>
        </a>
        <a href="{$healthUrl}">
          <strong>接口状态</strong>
          <span>用于部署验收时检查接口服务是否正常。</span>
        </a>
      </div>
    </main>
  </div>
</body>
</html>
HTML;
    }

    private function adminLoginUrl(Request $request): string
    {
        return FrontendUrlBuilder::adminLoginUrl($request);
    }

    private function merchantLoginUrl(Request $request): string
    {
        return FrontendUrlBuilder::merchantLoginUrl($request);
    }

    private function storefrontHomeUrl(Request $request): string
    {
        return FrontendUrlBuilder::publicHomeUrl($request);
    }

    private function requestOrigin(Request $request): string
    {
        return FrontendUrlBuilder::requestOrigin($request);
    }

    private function qrCodeService(): QrCodeService
    {
        return new QrCodeService();
    }
}
