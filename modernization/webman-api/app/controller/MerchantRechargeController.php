<?php

declare(strict_types=1);

namespace app\controller;

use app\service\MerchantRechargeService;
use app\support\ApiResponse;
use app\support\MerchantFrontSession;
use Webman\Http\Request;
use Webman\Http\Response;

class MerchantRechargeController
{
    public function doPay(Request $request): Response
    {
        if (strtoupper($request->method()) === 'GET') {
            return redirect('/Deal/Recharge');
        }

        $merchant = MerchantFrontSession::current($request);
        if ($merchant === null) {
            return $this->jsonOrRedirect($request, '请先登录商户账号', '/User/Login');
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            $message = trim((string)($merchant['frozen_reason'] ?? '商户账户已冻结')) ?: '商户账户已冻结';
            $message = ApiResponse::normalizeText($message);

            if ($this->wantsJson($request)) {
                return json([
                    'code' => 201,
                    'msg' => $message,
                    'message' => $message,
                ], JSON_UNESCAPED_UNICODE)->withStatus(403);
            }

            return response($this->errorPage($message, '/Deal/Recharge'), 403, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]);
        }

        $result = (new MerchantRechargeService())->createRecharge($request, $merchant);
        if (empty($result['ok'])) {
            $message = trim((string)($result['message'] ?? '充值订单创建失败')) ?: '充值订单创建失败';
            $apiCode = (int)($result['api_code'] ?? 201);
            $httpStatus = (int)($result['http_status'] ?? 422);

            $message = ApiResponse::normalizeText($message);
            if ($this->wantsJson($request)) {
                return json([
                    'code' => $apiCode,
                    'msg' => $message,
                    'message' => $message,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)->withStatus($httpStatus);
            }

            return response($this->errorPage($message, '/Deal/Recharge'), $httpStatus, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]);
        }

        $mode = strtolower(trim((string)($result['mode'] ?? '')));
        if ($mode === 'cashier') {
            $payload = (array)($result['cashier_payload'] ?? []);

            if ($this->wantsJson($request)) {
                return json([
                    'code' => 0,
                    'msg' => '成功',
                    'message' => '成功',
                    'mode' => 'cashier',
                    'out_trade_no' => (string)($result['out_trade_no'] ?? ''),
                    'data' => $payload,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return response($this->cashierPage($payload, $request), 200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]);
        }

        if ($this->wantsJson($request)) {
            return json([
                'code' => 0,
                'msg' => '成功',
                'message' => '成功',
                'mode' => $mode !== '' ? $mode : 'html',
                'out_trade_no' => (string)($result['out_trade_no'] ?? ''),
                'form_html' => (string)($result['body'] ?? ''),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return response((string)($result['body'] ?? ''), 200, [
            'Content-Type' => (string)($result['content_type'] ?? 'text/html; charset=utf-8'),
        ]);
    }

    public function consolePoll(Request $request): Response
    {
        $outTradeNo = trim((string)($request->input('TradeNo', '') ?: $request->input('trade_no', '')));
        $payload = (new MerchantRechargeService())->pollRecharge($outTradeNo, $request);

        return json($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

    private function jsonOrRedirect(Request $request, string $message, string $location): Response
    {
        $message = ApiResponse::normalizeText($message);

        if ($this->wantsJson($request)) {
            return json([
                'code' => 401,
                'msg' => $message,
                'message' => $message,
            ], JSON_UNESCAPED_UNICODE)->withStatus(401);
        }

        return redirect($location);
    }

    private function buildQrCodeUrl(string $content, Request $request, int $size): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $content) === 1 || str_starts_with($content, 'data:image/')) {
            return $content;
        }

        if (str_starts_with($content, '//')) {
            return $this->requestScheme($request) . ':' . $content;
        }

        if (str_starts_with($content, '/')) {
            $path = (string)(parse_url($content, PHP_URL_PATH) ?: '');
            if ($path !== '' && preg_match('/\.(png|jpe?g|gif|bmp|webp|svg)$/i', $path) === 1) {
                return $this->requestOrigin($request) . $content;
            }
        }

        return $this->requestOrigin($request) . '/qrcode.php?text=' . rawurlencode($content) . '&size=' . $size;
    }

    private function requestOrigin(Request $request): string
    {
        return \app\support\FrontendUrlBuilder::requestOrigin($request);
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

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function typeLabel(string $type): string
    {
        return match (strtolower(trim($type))) {
            'alipay' => '支付宝',
            'wxpay' => '微信支付',
            'qqpay' => 'QQ钱包',
            default => strtoupper($type),
        };
    }

    private function instructions(string $type): string
    {
        return match (strtolower(trim($type))) {
            'alipay' => '请使用支付宝扫码，并按当前页面展示的金额完成支付。',
            'wxpay' => '请使用微信扫码，并按当前页面展示的金额完成支付。',
            'qqpay' => '请使用 QQ 钱包扫码，并按当前页面展示的金额完成支付。',
            default => '请使用对应支付应用扫码，并按当前页面展示的金额完成支付。',
        };
    }

    private function money(mixed $value): string
    {
        return number_format((float)$value, 2, '.', '');
    }

    private function cashierPage(array $payload, Request $request): string
    {
        $order = (array)($payload['order'] ?? []);
        $console = (array)($payload['console'] ?? []);

        $tradeNo = $this->escape((string)($order['trade_no'] ?? $order['out_trade_no'] ?? ''));
        $amount = $this->escape($this->money($order['truemoney'] ?? 0));
        $type = strtolower(trim((string)($order['type'] ?? 'alipay')));
        $typeLabel = $this->escape($this->typeLabel($type));
        $instructions = $this->escape($this->instructions($type));
        $notice = trim((string)($console['console_notice'] ?? ''));
        $noticeHtml = $notice !== ''
            ? '<p class="notice-text">' . $this->escape($notice) . '</p>'
            : '<p class="notice-text">页面会每 2 秒自动轮询一次支付状态，支付成功后会自动返回充值记录页面。</p>';
        $launchUrl = trim((string)($order['launch_url'] ?? ''));
        $launchButton = $launchUrl !== ''
            ? '<a class="launch" href="' . $this->escape($launchUrl) . '">打开支付应用</a>'
            : '';
        $qrUrl = $this->buildQrCodeUrl((string)($order['raw_qrcode'] ?? ''), $request, 350);
        $qrMarkup = $qrUrl !== ''
            ? '<img id="qrImage" src="' . $this->escape($qrUrl) . '" alt="充值二维码">'
            : '<div id="qrImage" class="placeholder">正在等待二维码生成</div>';
        $timeoutSeconds = max(0, (int)($console['timeout_seconds'] ?? 0));
        $showPayPopup = !empty($console['is_pay_popup']);
        $pageTradeNo = json_encode((string)($order['out_trade_no'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pagePollUrl = json_encode('/Pay/ConSole_DoPay', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pageTimeoutUrl = json_encode((string)($console['timeout_url'] ?? '/Deal/Recharge'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pageTimeoutSeconds = json_encode($timeoutSeconds);

        $popupMarkup = '';
        if ($showPayPopup) {
            $popupMarkup = <<<HTML
  <div class="overlay" id="payTips">
    <div class="overlay-card">
      <h2>支付提醒</h2>
      <p>请务必支付 <strong>{$amount}</strong> 元整，以便系统准确匹配本次充值订单。</p>
      <button type="button" onclick="document.getElementById('payTips').remove()">我知道了</button>
    </div>
  </div>
HTML;
        }

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>商户充值收银台</title>
  <style>
    :root{color-scheme:light;background:#f6f8fb;color:#172033}
    *{box-sizing:border-box}
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:
      radial-gradient(circle at top left,rgba(14,165,233,.16),transparent 34%),
      radial-gradient(circle at top right,rgba(249,115,22,.15),transparent 36%),
      linear-gradient(180deg,#f8fafc,#eef4ff)}
    .shell{max-width:1120px;margin:0 auto;padding:28px 18px 36px}
    .hero,.card{background:rgba(255,255,255,.94);border:1px solid rgba(148,163,184,.22);border-radius:26px;box-shadow:0 24px 70px rgba(15,23,42,.08)}
    .hero{padding:26px;margin-bottom:18px}
    .hero h1{margin:0 0 10px;font-size:30px}
    .hero p{margin:0;color:#475569;line-height:1.8}
    .grid{display:grid;grid-template-columns:1.1fr .9fr;gap:18px}
    .card{padding:24px}
    .eyebrow{display:inline-flex;padding:6px 10px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
    .amount{margin:16px 0 6px;font-size:40px;font-weight:800;color:#0f172a}
    .meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:18px}
    .stat{padding:14px;border-radius:18px;background:#f8fafc;border:1px solid #e2e8f0}
    .stat span{display:block;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.08em}
    .stat strong{display:block;margin-top:8px;font-size:18px;color:#0f172a;word-break:break-word}
    .qr-wrap{display:grid;place-items:center;min-height:320px;border-radius:22px;background:#fff;border:1px dashed #cbd5e1;padding:14px}
    .qr-wrap img{width:min(320px,100%);height:auto;border-radius:18px;box-shadow:0 18px 38px rgba(15,23,42,.08)}
    .placeholder{width:min(320px,100%);aspect-ratio:1;display:grid;place-items:center;text-align:center;border-radius:18px;background:repeating-linear-gradient(45deg,#e2e8f0,#e2e8f0 12px,#f8fafc 12px,#f8fafc 24px);color:#64748b;font-weight:700;padding:24px}
    .status{margin-top:16px;padding:16px;border-radius:18px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;line-height:1.7}
    .status strong{color:#0f172a}
    .notice-text{margin:18px 0 0;color:#475569;line-height:1.8}
    .launch{display:inline-flex;align-items:center;justify-content:center;margin-top:16px;padding:11px 15px;border-radius:14px;background:#111827;color:#fff;text-decoration:none;font-weight:700}
    .footer{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px}
    .btn{display:inline-flex;align-items:center;justify-content:center;padding:11px 15px;border-radius:14px;text-decoration:none;font-weight:700}
    .btn.primary{background:#111827;color:#fff}
    .btn.secondary{background:#e2e8f0;color:#0f172a}
    .overlay{position:fixed;inset:0;background:rgba(15,23,42,.45);display:grid;place-items:center;padding:18px}
    .overlay-card{width:min(420px,100%);background:#fff;border-radius:26px;padding:28px;box-shadow:0 30px 80px rgba(15,23,42,.2)}
    .overlay-card h2{margin:0 0 12px;font-size:24px}
    .overlay-card p{margin:0;color:#475569;line-height:1.8}
    .overlay-card button{margin-top:20px;width:100%;padding:12px 14px;border:0;border-radius:14px;background:#111827;color:#fff;font-weight:700;cursor:pointer}
    @media (max-width:900px){.grid{grid-template-columns:1fr}.shell{padding:18px}}
    @media (max-width:560px){.hero,.card{padding:20px}.amount{font-size:34px}.meta{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <span class="eyebrow">商户充值</span>
      <h1>{$typeLabel} 收银台已就绪</h1>
      <p>{$instructions}</p>
    </section>
    <section class="grid">
      <article class="card">
        <span class="eyebrow">订单信息</span>
        <div class="amount">{$amount}</div>
        <p id="countdownText">二维码有效时间：加载中...</p>
        <div class="meta">
          <div class="stat"><span>订单编号</span><strong>{$tradeNo}</strong></div>
          <div class="stat"><span>支付方式</span><strong>{$typeLabel}</strong></div>
        </div>
        {$noticeHtml}
        {$launchButton}
        <div class="footer">
          <a class="btn secondary" href="/Deal/Recharge">返回充值记录</a>
          <a class="btn secondary" href="/Deal/MoneyLog">查看资金日志</a>
        </div>
      </article>
      <article class="card">
        <div class="qr-wrap" id="qrWrap">{$qrMarkup}</div>
        <div class="status">
          <strong id="statusText">等待支付确认</strong><br>
          <span id="statusHint">页面会自动刷新支付状态。</span>
        </div>
      </article>
    </section>
  </div>
{$popupMarkup}
  <script>
    const outTradeNo = {$pageTradeNo};
    const pollUrl = {$pagePollUrl};
    const timeoutUrl = {$pageTimeoutUrl};
    let remainingSeconds = {$pageTimeoutSeconds};
    let pollTimer = null;
    let countdownTimer = null;

    function formatCountdown(totalSeconds) {
      const safe = Math.max(0, Number(totalSeconds || 0));
      const minute = String(Math.floor(safe / 60)).padStart(2, '0');
      const second = String(safe % 60).padStart(2, '0');
      return minute + ':' + second;
    }

    function setStatus(title, hint) {
      document.getElementById('statusText').textContent = title;
      document.getElementById('statusHint').textContent = hint;
    }

    function setCountdown() {
      const node = document.getElementById('countdownText');
      if (remainingSeconds <= 0) {
        node.textContent = '二维码已过期，正在返回充值页面...';
        setStatus('充值已超时', '如仍需充值，请重新创建一笔新的充值订单。');
        if (countdownTimer !== null) {
          clearInterval(countdownTimer);
          countdownTimer = null;
        }
        if (pollTimer !== null) {
          clearInterval(pollTimer);
          pollTimer = null;
        }
        window.setTimeout(function () {
          window.location.href = timeoutUrl || '/Deal/Recharge';
        }, 1800);
        return;
      }

      node.textContent = '二维码有效时间：' + formatCountdown(remainingSeconds);
      remainingSeconds -= 1;
    }

    async function poll() {
      try {
        const response = await fetch(pollUrl + '?TradeNo=' + encodeURIComponent(outTradeNo) + '&_=' + Date.now(), {
          headers: { 'Accept': 'application/json' }
        });
        const result = await response.json();
        if (String(result.code) === '200') {
          setStatus('充值支付成功', '正在返回商户充值记录列表。');
          if (pollTimer !== null) {
            clearInterval(pollTimer);
            pollTimer = null;
          }
          if (countdownTimer !== null) {
            clearInterval(countdownTimer);
            countdownTimer = null;
          }
          window.location.href = result.url || '/Deal/Recharge';
          return;
        }

        if (String(result.code) === '100' && result.qr_url) {
          const wrap = document.getElementById('qrWrap');
          wrap.innerHTML = '<img id="qrImage" src="' + result.qr_url + '" alt="充值二维码">';
          setStatus('二维码已生成', '请在对应支付应用内完成支付。');
          return;
        }

        if (String(result.code) === '0') {
          const messageMap = {
            order_timeout: '充值订单已超时',
            order_not_found: '未找到对应充值订单',
            qrcode_missing: '当前充值订单暂未生成二维码'
          };
          const title = messageMap[result.msg] || '充值状态暂不可用';
          setStatus(title, '如有需要，请返回充值记录页面重新创建订单。');
        }
      } catch (error) {
        setStatus('状态轮询暂时失败', '系统会自动继续重试，请保持当前页面开启。');
      }
    }

    setCountdown();
    countdownTimer = window.setInterval(setCountdown, 1000);
    poll();
    pollTimer = window.setInterval(poll, 2000);
  </script>
</body>
</html>
HTML;
    }

    private function errorPage(string $message, string $returnUrl): string
    {
        $safeMessage = $this->escape($message);
        $safeUrl = $this->escape($returnUrl);

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>充值异常</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f6f8fb;color:#172033}
    .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .card{width:min(480px,100%);background:#fff;border:1px solid #e2e8f0;border-radius:24px;box-shadow:0 24px 70px rgba(15,23,42,.08);padding:32px}
    h1{margin:0 0 14px;font-size:26px}
    p{margin:0 0 22px;line-height:1.8;color:#475569}
    a{display:inline-flex;padding:11px 16px;border-radius:14px;background:#111827;color:#fff;text-decoration:none;font-weight:700}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>充值订单创建失败</h1>
      <p>{$safeMessage}</p>
      <a href="{$safeUrl}">返回充值中心</a>
    </div>
  </div>
</body>
</html>
HTML;
    }
}
