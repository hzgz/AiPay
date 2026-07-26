<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\controller;

use app\support\QrCodeService;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;

class PublicEntryController
{
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
  <title>二维码接口说明</title>
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
    <p>通过 <code>text</code> 传入二维码内容，通过 <code>size</code> 控制 PNG 图片像素尺寸。</p>
    <p>调用格式：<code>{$origin}/qrcode.php?text=https%3A%2F%2Fpay.example.com&amp;size=180</code></p>
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

    private function requestOrigin(Request $request): string
    {
        $uri = $request->uri();
        $scheme = $uri->getScheme() ?: ((string)$request->header('x-forwarded-proto', '') !== '' ? (string)$request->header('x-forwarded-proto') : 'http');
        $host = (string)($request->header('x-forwarded-host', '') ?: $request->header('host', '127.0.0.1'));

        return rtrim($scheme . '://' . $host, '/');
    }

    private function qrCodeService(): QrCodeService
    {
        return new QrCodeService();
    }
}
