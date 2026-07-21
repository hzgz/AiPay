<?php

declare(strict_types=1);

namespace app\controller;

use app\support\ApiResponse;
use app\support\BusinessTable;
use app\support\FrontendUrlBuilder;
use app\support\LegacyMojibakeGuard;
use app\support\MerchantPortalMessageCatalog;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class PayCompatibilityController
{
    private const ALIPAY_DEEP_LINK_CODES = ['alipay_mck'];
    private const DEFAULT_VOICE_TIPS = MerchantPortalMessageCatalog::DEFAULT_VOICE_TIPS;

    public function console(Request $request): Response
    {
        if ($this->isPollRequest($request)) {
            return $this->consolePoll($request);
        }

        $tradeNo = trim((string)$request->input('trade_no', ''));
        if ($tradeNo === '') {
            if ($this->wantsJson($request)) {
                return $this->monitorResponse(201, '缺少交易订单号 trade_no', [], [
                    'route_policy' => $this->routePolicy(),
                    'migration_guard' => [
                        'read_only' => true,
                        'blocked_actions' => ['callback_replay', 'return_num_increment', 'status_reset'],
                    ],
                ]);
            }

            return response($this->errorPage('缺少交易订单号 trade_no', '/'), 400, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]);
        }

        $order = $this->findOrderByTradeNo($tradeNo);
        if ($order === null) {
            if ($this->wantsJson($request)) {
                return $this->monitorResponse(201, '未找到对应订单', [], [
                    'route_policy' => $this->routePolicy(),
                    'migration_guard' => [
                        'read_only' => true,
                        'blocked_actions' => ['callback_replay', 'return_num_increment', 'status_reset'],
                    ],
                ]);
            }

            return response($this->errorPage('未找到对应订单', '/'), 404, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]);
        }

        if ((int)($order['merchant_is_frozen'] ?? 0) === 1) {
            $message = trim(ApiResponse::normalizeText((string)($order['merchant_frozen_reason'] ?? '')));
            $message = $message !== '' ? $message : '商户账户已冻结';
            if ($this->wantsJson($request)) {
                return $this->forbiddenJson($message);
            }

            return response($this->errorPage($message, $this->resolveTimeoutUrl($order)), 403, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]);
        }

        $payload = $this->consolePayload($request, $order);
        if ($this->wantsJson($request)) {
            return $this->monitorResponse(200, '收银台订单加载成功', $payload);
        }

        return response($this->consolePage($payload), 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
    public function poll(Request $request): Response
    {
        return $this->consolePoll($request);
    }

    private function consolePoll(Request $request): Response
    {
        $outTradeNo = trim((string)($request->input('TradeNo', '') ?: $request->input('trade_no', '')));
        if ($outTradeNo === '') {
            return $this->legacyPollResponse(201, 'order_no_required');
        }

        $order = $this->findOrderByOutTradeNo($outTradeNo);
        if ($order === null) {
            return $this->legacyPollResponse(201, 'order_not_found');
        }

        if ((int)($order['status'] ?? 0) === 1) {
            return $this->legacyPollResponse(200, 'order_paid', [
                'url' => $this->merchantReturnUrl($order),
            ]);
        }

        if ((int)($order['out_time'] ?? 0) > 0 && (int)($order['out_time'] ?? 0) < time()) {
            return $this->legacyPollResponse(201, 'order_timeout');
        }

        $qrcode = trim((string)($order['qrcode'] ?? ''));
        if ($qrcode === 'ewmLoading') {
            return $this->legacyPollResponse(404, 'qrcode_loading');
        }

        if ($qrcode === '') {
            return $this->legacyPollResponse(201, 'qrcode_missing');
        }

        return $this->legacyPollResponse(100, 'qrcode_ready', [
            'qr_url' => $this->buildQrCodeUrl(
                $qrcode,
                $request,
                350,
                (string)($order['account_code'] ?? '')
            ),
            'h5_qrurl' => trim((string)($order['h5_qrurl'] ?? '')),
        ]);
    }

    private function findOrderByTradeNo(string $tradeNo): ?array
    {
        return $this->findOrder('trade_no', $tradeNo);
    }

    private function findOrderByOutTradeNo(string $outTradeNo): ?array
    {
        return $this->findOrder('out_trade_no', $outTradeNo);
    }

    private function findOrder(string $field, string $value): ?array
    {
        $row = Db::table(BusinessTable::order() . ' as orders')
            ->leftJoin(BusinessTable::user() . ' as merchant', 'orders.user_id', '=', 'merchant.id')
            ->leftJoin(BusinessTable::userbasic() . ' as basic', 'orders.user_id', '=', 'basic.user_id')
            ->leftJoin(BusinessTable::account() . ' as account', 'orders.account_id', '=', 'account.id')
            ->select(
                'orders.id',
                'orders.name',
                'orders.sitename',
                'orders.type',
                'orders.trade_no',
                'orders.out_trade_no',
                'orders.notify_url',
                'orders.return_url',
                'orders.user_id',
                'orders.account_id',
                'orders.money',
                'orders.truemoney',
                'orders.feilvmoney',
                'orders.status',
                'orders.return_num',
                'orders.out_time',
                'orders.create_time',
                'orders.end_time',
                'orders.qrcode',
                'orders.h5_qrurl',
                'merchant.username as merchant_username',
                'merchant.user_key as merchant_user_key',
                'merchant.is_frozen as merchant_is_frozen',
                'merchant.frozen_reason as merchant_frozen_reason',
                'basic.timeout_method',
                'basic.timeout_url',
                'basic.timeout_time',
                'basic.callback_hiddenName as callback_hidden_name',
                'basic.hidden_sacnName',
                'basic.is_jump',
                'basic.console_notity',
                'basic.is_voice_tips',
                'basic.voice_tips',
                'basic.is_payPopUp',
                'basic.console_temp',
                'account.code as account_code',
                'account.qr_type as account_qr_type'
            )
            ->where('orders.' . $field, $value)
            ->orderByDesc('orders.id')
            ->first();

        return $row ? (array)$row : null;
    }

    private function consolePayload(Request $request, array $order): array
    {
        $timeoutUrl = $this->resolveTimeoutUrl($order);
        $returnUrl = $this->merchantReturnUrl($order);
        $state = $this->consoleState($order);
        $remainingSeconds = max(0, (int)($order['out_time'] ?? 0) - time());
        $displayH5Url = $this->displayH5QrUrl((string)($order['account_code'] ?? ''), (string)($order['h5_qrurl'] ?? ''));

        return [
            'order' => [
                'id' => (int)($order['id'] ?? 0),
                'name' => trim((string)($order['name'] ?? '')),
                'sitename' => trim((string)($order['sitename'] ?? '')),
                'type' => trim((string)($order['type'] ?? '')),
                'trade_no' => trim((string)($order['trade_no'] ?? '')),
                'out_trade_no' => trim((string)($order['out_trade_no'] ?? '')),
                'money' => (string)($order['money'] ?? '0.00'),
                'truemoney' => (string)($order['truemoney'] ?? '0.00'),
                'status' => (int)($order['status'] ?? 0),
                'return_num' => (int)($order['return_num'] ?? 0),
                'create_time' => trim((string)($order['create_time'] ?? '')),
                'end_time' => trim((string)($order['end_time'] ?? '')),
                'out_time' => (int)($order['out_time'] ?? 0),
                'qr_url' => $this->buildQrCodeUrl(
                    (string)($order['qrcode'] ?? ''),
                    $request,
                    350,
                    (string)($order['account_code'] ?? '')
                ),
                'raw_qrcode' => trim((string)($order['qrcode'] ?? '')),
                'h5_qrurl' => trim((string)($order['h5_qrurl'] ?? '')),
                'display_h5_qrurl' => $displayH5Url,
                'notify_url' => trim((string)($order['notify_url'] ?? '')),
                'return_url' => trim((string)($order['return_url'] ?? '')),
            ],
            'merchant' => [
                'id' => (int)($order['user_id'] ?? 0),
                'username' => trim((string)($order['merchant_username'] ?? '')),
                'is_frozen' => (int)($order['merchant_is_frozen'] ?? 0) === 1,
            ],
            'console' => [
                'theme' => trim((string)($order['console_temp'] ?? '')),
                'timeout_seconds' => $remainingSeconds,
                'timeout_url' => $timeoutUrl,
                'timeout_method' => (int)($order['timeout_method'] ?? 0),
                'timeout_method_label' => $this->timeoutMethodLabel((int)($order['timeout_method'] ?? 0)),
                'console_notice' => trim((string)($order['console_notity'] ?? '')),
                'hidden_scan_name' => (int)($order['hidden_sacnName'] ?? 0) === 1,
                'is_jump' => (int)($order['is_jump'] ?? 0) === 1,
                'is_voice_tips' => (int)($order['is_voice_tips'] ?? 0) === 1,
                'voice_tips' => $this->normalizeVoiceTipsTemplate($order['voice_tips'] ?? null),
                'is_pay_popup' => (int)($order['is_payPopUp'] ?? 0) === 1,
                'account_code' => trim((string)($order['account_code'] ?? '')),
                'account_qr_type' => trim((string)($order['account_qr_type'] ?? '')),
            ],
            'status' => [
                'state' => $state,
                'is_paid' => $state === 'paid',
                'is_timeout' => $state === 'timeout',
                'is_qrcode_loading' => $state === 'qrcode_loading',
                'merchant_return_url' => $returnUrl,
            ],
            'legacy_urls' => [
                'poll' => '/api/public/cashier/poll',
                'submit' => '/submit.php',
                'api_submit' => '/api/payment',
                'legacy_console' => $this->cashierConsoleUrl($request, trim((string)($order['trade_no'] ?? ''))),
            ],
            'route_policy' => $this->routePolicy(),
            'migration_guard' => [
                'read_only' => true,
                'blocked_actions' => ['callback_replay', 'return_num_increment', 'status_reset'],
            ],
        ];
    }

    private function consoleState(array $order): string
    {
        if ((int)($order['status'] ?? 0) === 1) {
            return 'paid';
        }

        if ((int)($order['out_time'] ?? 0) > 0 && (int)($order['out_time'] ?? 0) < time()) {
            return 'timeout';
        }

        $qrcode = trim((string)($order['qrcode'] ?? ''));
        if ($qrcode === 'ewmLoading') {
            return 'qrcode_loading';
        }

        if ($qrcode === '') {
            return 'qrcode_missing';
        }

        return 'pending';
    }

    private function consolePage(array $payload): string
    {
        $order = (array)($payload['order'] ?? []);
        $console = (array)($payload['console'] ?? []);
        $status = (array)($payload['status'] ?? []);
        $siteNameRaw = trim((string)($order['sitename'] ?? 'AiPay')) ?: 'AiPay';
        $titleRaw = trim((string)($order['name'] ?? '')) ?: '订单支付';
        $amountRaw = number_format((float)($order['truemoney'] ?? $order['money'] ?? 0), 2, '.', '');
        $payTypeRaw = $this->paymentMethodLabel((string)($order['type'] ?? ''));
        $tradeNoRaw = trim((string)($order['trade_no'] ?? ''));
        $outTradeNoRaw = trim((string)($order['out_trade_no'] ?? ''));
        $qrUrlRaw = (string)($order['qr_url'] ?? '');
        $launchUrlRaw = (string)($order['display_h5_qrurl'] ?? '');
        $timeoutUrlRaw = (string)($console['timeout_url'] ?? '/');
        $returnUrlRaw = (string)($status['merchant_return_url'] ?? '');
        $noticeRaw = trim((string)($console['console_notice'] ?? ''));
        $noticeRaw = $noticeRaw !== '' ? $noticeRaw : '请在有效期内完成支付，页面会自动同步支付状态，无需手动刷新。';
        $state = (string)($status['state'] ?? 'pending');
        $countdown = (int)($console['timeout_seconds'] ?? 0);
        $siteName = $this->escape($siteNameRaw);
        $title = $this->escape($titleRaw);
        $amount = $this->escape($amountRaw);
        $payType = $this->escape($payTypeRaw);
        $tradeNo = $this->escape($tradeNoRaw);
        $outTradeNo = $this->escape($outTradeNoRaw);
        $timeoutUrl = $this->escape($timeoutUrlRaw);
        $returnUrl = $this->escape($returnUrlRaw);
        $notice = $this->escape($noticeRaw);
        $stateLabel = $this->escape($this->stateLabel($state));
        $stateDescription = $this->escape($this->stateDescription($state));
        $countdownLabel = $this->escape($this->formatCountdown($countdown));
        $placeholderText = $this->escape($this->placeholderText($state));
        $qrMarkup = $qrUrlRaw !== ''
            ? '<img id="qrImage" src="' . $this->escape($qrUrlRaw) . '" alt="支付二维码">'
            : '<div class="placeholder" id="qrPlaceholder">' . $placeholderText . '</div>';
        $paidButton = $returnUrlRaw !== ''
            ? '<a class="btn success" href="' . $returnUrl . '">返回商户页面</a>'
            : '<a class="btn secondary" href="' . $timeoutUrl . '">返回上一页</a>';
        $pageState = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pageOutTradeNo = json_encode($outTradeNoRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pagePollUrl = json_encode('/api/public/cashier/poll', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pageTimeoutUrl = json_encode($timeoutUrlRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pageLaunchUrl = json_encode($launchUrlRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pageAutoJump = json_encode(!empty($console['is_jump']));
        $pageCountdown = json_encode($countdown);
        $pageTradeNo = json_encode($tradeNoRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pagePayType = json_encode($payTypeRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pageNotice = json_encode($noticeRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pageReturnUrl = json_encode($returnUrlRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AiPay 收银台</title>
  <style>
    :root{--bg:#07111f;--bg2:#10203b;--surface:#fff;--text:#0f172a;--muted:#64748b;--line:#e2e8f0;--brand:#111827;--brandSoft:#dbeafe;--brandText:#1d4ed8;--success:#15803d;--warn:#b45309;--warnSoft:#fffbeb;--shadow:0 24px 70px rgba(15,23,42,.12)}
    *{box-sizing:border-box}body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;color:var(--text);background:radial-gradient(circle at top left,rgba(14,165,233,.16),transparent 28%),radial-gradient(circle at top right,rgba(129,140,248,.18),transparent 22%),linear-gradient(180deg,#f8fbff 0%,#eef4fb 48%,#f8fafc 100%)}
    .page{max-width:1180px;margin:0 auto;padding:24px 18px 36px}.shell{display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:18px}.panel{background:rgba(255,255,255,.96);border:1px solid rgba(148,163,184,.2);border-radius:28px;box-shadow:var(--shadow)}
    .summary{padding:28px}.brand{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center}.pill{display:inline-flex;padding:7px 12px;border-radius:999px;background:var(--brandSoft);color:var(--brandText);font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.site{font-size:13px;color:var(--muted);font-weight:600}
    h1{margin:16px 0 8px;font-size:34px;line-height:1.12}.sub{margin:0;color:var(--muted);line-height:1.75}.amount{display:flex;align-items:flex-end;gap:10px;margin-top:22px}.amount span{font-size:26px;font-weight:700;color:#334155}.amount strong{font-size:52px;line-height:.95;font-weight:800;letter-spacing:-.04em}
    .metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:22px}.metric{padding:16px;border:1px solid var(--line);border-radius:20px;background:linear-gradient(180deg,#fff,#f8fafc)}.metric em{display:block;font-style:normal;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#94a3b8;font-weight:700}.metric strong{display:block;margin-top:8px;font-size:18px;line-height:1.4;word-break:break-word}
    .rows{display:grid;gap:12px;margin-top:22px}.row{display:flex;justify-content:space-between;gap:16px;padding:14px 16px;border:1px solid var(--line);border-radius:18px;background:#fff}.row span{color:var(--muted);font-size:14px}.row code{margin:0;font-size:13px;word-break:break-all;white-space:pre-wrap;text-align:right}
    .notice{margin-top:18px;padding:16px;border-radius:18px;background:var(--warnSoft);border:1px solid #fde68a;color:var(--warn);line-height:1.75}.actions,.checkoutActions{display:flex;gap:12px;flex-wrap:wrap;margin-top:20px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 18px;border-radius:14px;border:1px solid transparent;text-decoration:none;cursor:pointer;font-size:14px;font-weight:700}.btn.primary{background:var(--brand);color:#fff}.btn.secondary{background:#fff;color:#0f172a;border-color:#cbd5e1}.btn.ghost{background:#f8fafc;color:#0f172a;border-color:#e2e8f0}.btn.success{background:var(--success);color:#fff}.hidden{display:none !important}
    .checkout{padding:24px;color:#e2e8f0;background:radial-gradient(circle at top left,rgba(56,189,248,.22),transparent 34%),radial-gradient(circle at bottom right,rgba(129,140,248,.18),transparent 28%),linear-gradient(180deg,var(--bg) 0%,var(--bg2) 100%);position:relative;overflow:hidden}.checkout:before{content:"";position:absolute;inset:18px;border-radius:22px;border:1px solid rgba(255,255,255,.08);pointer-events:none}
    .head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start}.label{font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:#93c5fd;font-weight:700}.head h2{margin:10px 0 0;font-size:28px;line-height:1.15;color:#fff}.stateTag{padding:8px 12px;border-radius:999px;background:rgba(148,163,184,.16);border:1px solid rgba(255,255,255,.12);font-size:12px;font-weight:700}.stateText{margin:12px 0 0;color:#cbd5e1;line-height:1.75}
    .qrbox{margin-top:22px;min-height:340px;display:grid;place-items:center;padding:18px;border-radius:28px;background:linear-gradient(180deg,#fff,#f8fafc);border:1px solid rgba(255,255,255,.16);box-shadow:inset 0 0 0 1px rgba(226,232,240,.6)}.qrbox img{display:block;width:min(100%,310px);height:auto;padding:12px;border-radius:20px;background:#fff;box-shadow:0 20px 44px rgba(15,23,42,.12)}.placeholder{width:min(100%,310px);aspect-ratio:1;display:grid;place-items:center;padding:24px;border-radius:20px;border:1px dashed #cbd5e1;background:repeating-linear-gradient(45deg,#eff6ff,#eff6ff 14px,#fff 14px,#fff 28px);color:#334155;font-weight:700;line-height:1.7;text-align:center}
    .scanMeta{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:16px}.scanMeta p{margin:0;color:#cbd5e1;line-height:1.7}.timer{padding:10px 14px;border-radius:16px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);text-align:right}.timer em{display:block;font-style:normal;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#93c5fd}.timer strong{display:block;margin-top:4px;font-size:22px;color:#fff}
    .successBox{display:none;margin-top:22px;padding:22px;border-radius:24px;background:rgba(220,252,231,.96);border:1px solid rgba(34,197,94,.18);color:#14532d}.successBox h3{margin:0 0 8px;font-size:24px}.successBox p{margin:0;color:#166534;line-height:1.75}.successGrid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:16px}.successGrid div{padding:14px 12px;border-radius:16px;background:rgba(255,255,255,.84);border:1px solid rgba(21,128,61,.12)}.successGrid em{display:block;font-style:normal;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#166534}.successGrid strong{display:block;margin-top:8px;font-size:15px;line-height:1.5;word-break:break-word}
    #cashierStage[data-state="paid"] .qrbox,#cashierStage[data-state="paid"] .scanMeta,#cashierStage[data-state="paid"] .checkoutActions{display:none}#cashierStage[data-state="paid"] .successBox{display:block}#cashierStage[data-state="timeout"] .stateTag{background:rgba(251,113,133,.14);border-color:rgba(251,113,133,.22);color:#fecdd3}#cashierStage[data-state="qrcode_loading"] .stateTag,#cashierStage[data-state="qrcode_missing"] .stateTag{background:rgba(245,158,11,.14);border-color:rgba(245,158,11,.22);color:#fde68a}
    @media (max-width:980px){.shell{grid-template-columns:1fr}}@media (max-width:720px){.page{padding:14px 12px 24px}.summary,.checkout{padding:18px}h1{font-size:28px}.amount strong{font-size:42px}.metrics,.successGrid{grid-template-columns:1fr}.row{display:grid;gap:8px}.row code{text-align:left}.head h2{font-size:24px}.qrbox{min-height:300px}}
  </style>
</head>
<body>
  <div class="page">
    <div class="shell" id="cashierStage" data-state="{$state}">
      <section class="panel summary">
        <div class="brand"><span class="pill">AiPay Checkout</span><span class="site">{$siteName}</span></div>
        <h1>{$title}</h1>
        <p class="sub">请核对订单信息后完成付款，页面会持续轮询支付结果并自动更新。</p>
        <div class="amount"><span>￥</span><strong>{$amount}</strong></div>
        <div class="metrics">
          <div class="metric"><em>支付方式</em><strong>{$payType}</strong></div>
          <div class="metric"><em>当前状态</em><strong id="statusBadge">{$stateLabel}</strong></div>
          <div class="metric"><em>订单有效期</em><strong id="countdown">{$countdownLabel}</strong></div>
        </div>
        <div class="rows">
          <div class="row"><span>系统订单号</span><code>{$tradeNo}</code></div>
          <div class="row"><span>商户订单号</span><code>{$outTradeNo}</code></div>
          <div class="row"><span>支付完成后跳转</span><code>{$returnUrl}</code></div>
          <div class="row"><span>订单超时后跳转</span><code>{$timeoutUrl}</code></div>
        </div>
        <div class="notice" id="noticeBand">{$notice}</div>
        <div class="actions"><button type="button" class="btn secondary" id="copyTradeNoButton">复制订单号</button><a class="btn ghost" href="{$timeoutUrl}">取消支付</a></div>
      </section>
      <aside class="panel checkout">
        <div class="head"><div><div class="label">{$payType}</div><h2 id="statusTitle">{$stateLabel}</h2></div><span class="stateTag" id="statusTag">{$stateLabel}</span></div>
        <p class="stateText" id="statusText">{$stateDescription}</p>
        <div class="qrbox" id="qrWrap">{$qrMarkup}</div>
        <div class="scanMeta"><p id="scanTip">请使用{$payType}扫描二维码完成支付。</p><div class="timer"><em>剩余时间</em><strong id="countdownPanel">{$countdownLabel}</strong></div></div>
        <div class="checkoutActions" id="checkoutActions"><a id="launchLink" class="btn primary hidden" href="#" rel="nofollow">立即支付</a><button type="button" class="btn secondary hidden" id="copyLaunchButton">复制支付链接</button></div>
        <div class="successBox" id="successBox"><h3>支付成功</h3><p>订单状态已同步，如商户设置了回跳地址，页面会自动返回。</p><div class="successGrid"><div><em>支付金额</em><strong>{$amount}</strong></div><div><em>支付方式</em><strong>{$payType}</strong></div><div><em>系统订单号</em><strong>{$tradeNo}</strong></div></div><div class="checkoutActions">{$paidButton}</div></div>
      </aside>
    </div>
  </div>
<script>
(function () {
  var state = {$pageState};
  var outTradeNo = {$pageOutTradeNo};
  var pollUrl = {$pagePollUrl};
  var timeoutUrl = {$pageTimeoutUrl};
  var launchUrl = {$pageLaunchUrl};
  var autoJump = {$pageAutoJump};
  var remaining = {$pageCountdown};
  var tradeNo = {$pageTradeNo};
  var payType = {$pagePayType};
  var noticeText = {$pageNotice};
  var returnUrl = {$pageReturnUrl};
  var stage = document.getElementById('cashierStage');
  var statusBadge = document.getElementById('statusBadge');
  var statusTag = document.getElementById('statusTag');
  var statusTitle = document.getElementById('statusTitle');
  var statusText = document.getElementById('statusText');
  var countdownEl = document.getElementById('countdown');
  var countdownPanelEl = document.getElementById('countdownPanel');
  var qrWrap = document.getElementById('qrWrap');
  var launchLink = document.getElementById('launchLink');
  var copyLaunchButton = document.getElementById('copyLaunchButton');
  var copyTradeNoButton = document.getElementById('copyTradeNoButton');
  var scanTip = document.getElementById('scanTip');
  var noticeBand = document.getElementById('noticeBand');
  function labelFor(nextState){if(nextState==='paid')return'支付成功';if(nextState==='timeout')return'订单超时';if(nextState==='qrcode_loading')return'二维码生成中';if(nextState==='qrcode_missing')return'等待二维码';return'等待支付'}
  function textFor(nextState,message){if(nextState==='paid')return'支付结果已确认，页面将为你同步后续跳转。';if(nextState==='timeout')return'订单已超时，请返回上一步重新发起支付。';if(nextState==='qrcode_loading')return'上游通道正在生成二维码，请稍候，系统会自动刷新。';if(nextState==='qrcode_missing')return'二维码暂未返回，请等待系统继续轮询。';return message||'请使用二维码完成支付，系统会自动轮询并更新结果。'}
  function placeholderFor(nextState){if(nextState==='paid')return'支付成功，订单状态已完成同步。';if(nextState==='timeout')return'当前订单已超时，请重新发起支付。';if(nextState==='qrcode_loading')return'正在生成支付二维码，请稍候。';if(nextState==='qrcode_missing')return'支付二维码暂未就绪，请等待系统刷新。';return'二维码加载中，请稍候。'}
  function escapeHtml(value){return String(value||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;')}
  function isMobile(){return /(phone|pad|pod|iphone|ipod|ios|ipad|android|mobile|blackberry|iemobile|windows phone)/i.test(navigator.userAgent||'')}
  function isScheme(url){return /^[a-z][a-z0-9+.-]*:\/\//i.test(url||'')&&!/^https?:\/\//i.test(url||'')}
  function bindQrError(){var image=document.getElementById('qrImage');if(!image)return;image.onerror=function(){qrWrap.innerHTML='<div class="placeholder">二维码加载失败，请刷新页面后重试。</div>';setState('qrcode_missing','二维码图片暂时无法显示，请等待系统重试或刷新页面。')}}
  function scanTipText(url){if(state==='paid')return returnUrl?'支付成功后将自动跳转，也可手动返回商户页面。':'支付成功后可在下方直接返回。';if(state==='timeout')return'订单已超时，请返回后重新创建支付订单。';if(!url)return'请使用'+payType+'扫描二维码完成支付。';if(isScheme(url))return isMobile()?'如未自动拉起支付应用，可点击下方按钮继续支付。':'当前设备无法直接拉起支付应用，请使用手机扫码完成支付。';return isMobile()?'如需直接前往支付页，可点击下方按钮继续。':'可直接扫码完成支付，也可以打开支付页面继续。'}
  function setState(nextState,message){state=nextState;var label=labelFor(nextState);if(stage)stage.setAttribute('data-state',nextState);statusBadge.textContent=label;statusTag.textContent=label;statusTitle.textContent=label;statusText.textContent=textFor(nextState,message);if(noticeBand)noticeBand.textContent=nextState==='paid'?'订单已支付完成，当前页面会自动同步商户回跳。':nextState==='timeout'?'订单超时后请重新发起支付，避免继续使用失效二维码。':noticeText;if(scanTip)scanTip.textContent=scanTipText(launchUrl)}
  function setQr(url){if(!url){qrWrap.innerHTML='<div class="placeholder">'+escapeHtml(placeholderFor(state))+'</div>';return}qrWrap.innerHTML='<img id="qrImage" src="'+escapeHtml(url)+'" alt="支付二维码">';bindQrError()}
  function showLaunch(url){launchUrl=(url||'').trim();if(!launchLink)return;if(launchUrl===''){launchLink.classList.add('hidden');if(copyLaunchButton)copyLaunchButton.classList.add('hidden');if(scanTip)scanTip.textContent=scanTipText('');return}var scheme=isScheme(launchUrl);launchLink.href=launchUrl;launchLink.textContent=scheme?'打开支付应用':'立即支付';launchLink.classList.toggle('hidden',scheme&&!isMobile());if(copyLaunchButton)copyLaunchButton.classList.toggle('hidden',!(scheme||/^https?:\/\//i.test(launchUrl)));if(scanTip)scanTip.textContent=scanTipText(launchUrl)}
  function formatSeconds(seconds){if(seconds<=0)return'00:00';var mins=Math.floor(seconds/60);var secs=seconds%60;return String(mins).padStart(2,'0')+':'+String(secs).padStart(2,'0')}
  function beginCountdown(){if(!countdownEl||!countdownPanelEl)return;countdownEl.textContent=formatSeconds(remaining);countdownPanelEl.textContent=formatSeconds(remaining);window.setInterval(function(){if(remaining<=0||state==='paid')return;remaining-=1;countdownEl.textContent=formatSeconds(remaining);countdownPanelEl.textContent=formatSeconds(remaining);if(remaining<=0&&timeoutUrl){setState('timeout');setQr('');window.setTimeout(function(){window.location.href=timeoutUrl},1200)}},1000)}
  function maybeAutoJump(){if(isMobile()&&autoJump&&launchUrl){window.setTimeout(function(){window.location.href=launchUrl},900)}}
  function flash(button,text){if(!button)return;var original=button.textContent;button.textContent=text;window.setTimeout(function(){button.textContent=original},1400)}
  function copyText(value,button){if(!value||!navigator.clipboard||!navigator.clipboard.writeText){flash(button,'复制失败');return}navigator.clipboard.writeText(value).then(function(){flash(button,'已复制')}).catch(function(){flash(button,'复制失败')})}
  function poll(){if(!outTradeNo||state==='paid')return;fetch(pollUrl+'?TradeNo='+encodeURIComponent(outTradeNo)+'&_t='+Date.now(),{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}).then(function(response){return response.json()}).then(function(data){if(!data||typeof data!=='object')return;if(data.code===100){setState('pending','支付二维码已就绪，请尽快完成付款。');setQr(data.qr_url||'');showLaunch(data.h5_qrurl||launchUrl);return}if(data.code===404){setState('qrcode_loading');setQr('');return}if(data.code===200){setState('paid','支付成功，正在同步订单结果。');if(data.url){window.setTimeout(function(){window.location.href=data.url},1500)}return}if(data.msg==='order_timeout'){setState('timeout');setQr('');return}if(data.msg==='qrcode_missing'){setState('qrcode_missing');setQr('');return}if(typeof data.message==='string'&&data.message){statusText.textContent=data.message;return}if(typeof data.msg==='string'&&data.msg)statusText.textContent=data.msg}).catch(function(){statusText.textContent='状态轮询暂时失败，系统正在自动重试。'})}
  if(copyTradeNoButton)copyTradeNoButton.addEventListener('click',function(){copyText(tradeNo,copyTradeNoButton)});
  if(copyLaunchButton)copyLaunchButton.addEventListener('click',function(){copyText(launchUrl,copyLaunchButton)});
  bindQrError();beginCountdown();showLaunch(launchUrl);maybeAutoJump();if(state!=='paid'&&state!=='timeout'){window.setTimeout(poll,600);window.setInterval(poll,3000)}
})();
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
  <title>AiPay 收银台异常</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:linear-gradient(180deg,#f8fbff,#eef4fb);color:#0f172a}
    .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .card{width:min(520px,100%);background:#fff;border:1px solid #e2e8f0;border-radius:28px;box-shadow:0 24px 60px rgba(15,23,42,.08);padding:32px}
    .pill{display:inline-flex;padding:6px 10px;border-radius:999px;background:#fee2e2;color:#be123c;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
    h1{margin:16px 0 10px;font-size:28px}
    p{margin:0 0 22px;line-height:1.8;color:#475569}
    a{display:inline-flex;min-height:44px;padding:0 16px;align-items:center;justify-content:center;border-radius:14px;background:#111827;color:#fff;text-decoration:none;font-weight:700}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <span class="pill">Checkout Error</span>
      <h1>收银台暂时不可用</h1>
      <p>{$safeMessage}</p>
      <a href="{$safeUrl}">返回上一页</a>
    </div>
  </div>
</body>
</html>
HTML;
    }
    private function legacyPollResponse(int $code, string $message, array $data = []): Response
    {
        return json(array_merge([
            'code' => $code,
            'msg' => $message,
            'message' => $this->legacyPollMessage($message),
        ], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function legacyPollMessage(string $message): string
    {
        return match ($message) {
            'order_no_required' => '缺少订单号',
            'order_not_found' => '未找到对应订单',
            'order_paid' => '订单已支付',
            'order_timeout' => '订单已超时',
            'qrcode_loading' => '二维码生成中',
            'qrcode_missing' => '二维码暂未返回',
            'qrcode_ready' => '支付二维码已就绪',
            default => ApiResponse::normalizeText($message),
        };
    }

    private function monitorResponse(int $code, string $message, array $data = [], array $extra = []): Response
    {
        $message = ApiResponse::normalizeText($message);

        return json(array_merge([
            'code' => $code,
            'message' => $message,
            'msg' => $message,
            'data' => $data,
            'redirect' => '',
        ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function forbiddenJson(string $message): Response
    {
        $message = ApiResponse::normalizeText($message);

        return json([
            'code' => 403,
            'message' => $message,
            'msg' => $message,
            'data' => [
                'route_policy' => $this->routePolicy(),
                'migration_guard' => [
                    'read_only' => true,
                    'blocked_actions' => ['payment_console_access'],
                ],
            ],
            'redirect' => '',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)->withStatus(403);
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

    private function isPollRequest(Request $request): bool
    {
        return trim((string)$request->input('TradeNo', '')) !== '';
    }

    private function resolveTimeoutUrl(array $order): string
    {
        if ((int)($order['timeout_method'] ?? 0) === 1) {
            $notifyUrl = trim((string)($order['notify_url'] ?? ''));
            $returnUrl = trim((string)($order['return_url'] ?? ''));
            $sourceUrl = $notifyUrl !== '' ? $notifyUrl : $returnUrl;
            $root = $this->urlRoot($sourceUrl);

            return $root !== '' ? $root : '/';
        }

        $timeoutUrl = trim((string)($order['timeout_url'] ?? ''));
        return $timeoutUrl !== '' ? $timeoutUrl : '/';
    }

    private function merchantReturnUrl(array $order): string
    {
        $returnUrl = trim((string)($order['return_url'] ?? ''));
        if ($returnUrl === '') {
            return $this->resolveTimeoutUrl($order);
        }

        $payload = [
            'pid' => (string)($order['user_id'] ?? ''),
            'trade_no' => (string)($order['trade_no'] ?? ''),
            'out_trade_no' => (string)($order['out_trade_no'] ?? ''),
            'type' => (string)($order['type'] ?? ''),
            'money' => number_format((float)($order['money'] ?? 0), 2, '.', ''),
            'trade_status' => 'TRADE_SUCCESS',
        ];

        if ((int)($order['callback_hidden_name'] ?? 0) !== 1) {
            $payload['name'] = (string)($order['name'] ?? '');
        }

        $payload['sign'] = $this->makeLegacySign($payload, (string)($order['merchant_user_key'] ?? ''));
        $payload['sign_type'] = 'MD5';

        return $this->appendQuery($returnUrl, $payload);
    }

    private function makeLegacySign(array $payload, string $key): string
    {
        ksort($payload);
        $pairs = [];
        foreach ($payload as $name => $value) {
            if (str_starts_with((string)$name, '_')) {
                continue;
            }
            if ($name === 'sign' || $name === 'sign_type' || $value === '' || $value === null) {
                continue;
            }
            $pairs[] = $name . '=' . (string)$value;
        }

        return md5(implode('&', $pairs) . $key);
    }

    private function appendQuery(string $url, array $query): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    private function buildQrCodeUrl(
        string $content,
        Request $request,
        int $size,
        string $accountCode = ''
    ): string
    {
        $content = trim($content);
        if ($content === '' || $content === 'ewmLoading') {
            return '';
        }

        if (str_starts_with($content, 'data:image/')) {
            return $content;
        }

        if (str_starts_with($content, '//')) {
            $content = $this->requestScheme($request) . ':' . $content;
        }

        if (str_starts_with($content, '/')) {
            $content = $this->requestOrigin($request) . $content;
        }

        if (preg_match('#^https?://#i', $content) === 1 && $this->looksLikeImageUrl($content, $accountCode)) {
            return $content;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $content) === 1) {
            return FrontendUrlBuilder::publicQrCodeUrl($request, $content, $size);
        }

        return FrontendUrlBuilder::publicQrCodeUrl($request, $content, $size);
    }

    private function looksLikeImageUrl(string $content, string $accountCode = ''): bool
    {
        $path = strtolower((string)(parse_url($content, PHP_URL_PATH) ?: ''));
        if ($path !== '' && preg_match('/\.(png|jpe?g|gif|bmp|webp|svg)$/i', $path) === 1) {
            return true;
        }

        if (str_contains($path, '/qrcode') || str_contains($path, 'qrimage') || str_contains($path, 'barcode')) {
            return true;
        }

        $query = strtolower((string)(parse_url($content, PHP_URL_QUERY) ?: ''));
        if ($query !== '' && preg_match('/(?:image|img|qrimage|qrcode)=/i', $query) === 1) {
            return true;
        }

        return strtolower(trim($accountCode)) === 'alipay_bill';
    }
    private function displayH5QrUrl(string $accountCode, string $h5Url): string
    {
        $h5Url = trim($h5Url);
        if ($h5Url === '') {
            return '';
        }

        if (in_array(strtolower($accountCode), self::ALIPAY_DEEP_LINK_CODES, true) && !str_starts_with($h5Url, 'alipayqr://')) {
            return 'alipayqr://platformapi/startapp?saId=10000007&qrcode=' . $h5Url;
        }

        return $h5Url;
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

    private function urlRoot(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $scheme = !empty($parts['scheme']) ? $parts['scheme'] : 'http';
        $port = !empty($parts['port']) ? ':' . $parts['port'] : '';

        return $scheme . '://' . $parts['host'] . $port;
    }

    private function timeoutMethodLabel(int $method): string
    {
        return $method === 1 ? '使用订单回调域名' : '使用已配置的超时跳转地址';
    }

    private function stateLabel(string $state): string
    {
        return match ($state) {
            'paid' => '支付成功',
            'timeout' => '订单超时',
            'qrcode_loading' => '二维码生成中',
            'qrcode_missing' => '等待二维码',
            default => '等待支付',
        };
    }

    private function stateDescription(string $state): string
    {
        return match ($state) {
            'paid' => '订单已支付完成，系统将自动处理商户回调与页面跳转。',
            'timeout' => '当前订单已超时，请返回后重新发起支付。',
            'qrcode_loading' => '上游通道正在生成二维码，系统会自动轮询刷新。',
            'qrcode_missing' => '支付二维码暂未返回，请稍候等待系统继续刷新。',
            default => '请扫码完成支付，系统会自动更新当前订单状态。',
        };
    }

    private function placeholderText(string $state): string
    {
        return match ($state) {
            'paid' => '支付已完成，订单状态正在同步。',
            'timeout' => '当前订单已超时。',
            'qrcode_loading' => '正在生成支付二维码，请稍候。',
            'qrcode_missing' => '支付二维码暂未就绪，请等待系统刷新。',
            default => '二维码加载中，请稍候。',
        };
    }

    private function paymentMethodLabel(string $type): string
    {
        return match (strtolower(trim($type))) {
            'alipay' => '支付宝',
            'wxpay' => '微信支付',
            'qqpay' => 'QQ支付',
            'usdt' => 'USDT',
            default => strtoupper(trim($type)) !== '' ? strtoupper(trim($type)) : '在线支付',
        };
    }
    private function formatCountdown(int $seconds): string
    {
        if ($seconds <= 0) {
            return '00:00';
        }

        $minutes = intdiv($seconds, 60);
        $remainder = $seconds % 60;

        return sprintf('%02d:%02d', $minutes, $remainder);
    }

    private function routePolicy(): array
    {
        return [
            'strategy' => 'legacy_cashier_kept_online',
            'status' => 'active',
            'primary_entry' => '/api/public/cashier/console',
            'poll_entry' => '/api/public/cashier/poll',
            'alias_entries' => ['/Pay/console', '/Pay/ConSole', '/pay/console', '/pay/ConSole'],
            'allowed_methods' => ['GET', 'POST'],
            'write_policy' => 'no_callback_replay_or_status_mutation',
            'blocked_actions' => ['callback_replay', 'return_num_increment', 'status_reset'],
        ];
    }

    private function cashierConsoleUrl(Request $request, string $tradeNo): string
    {
        return rtrim($this->requestOrigin($request), '/') . '/api/public/cashier/console?trade_no=' . rawurlencode($tradeNo);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function normalizeVoiceTipsTemplate(mixed $value): string
    {
        return LegacyMojibakeGuard::normalizeVoiceTipsTemplate($value, self::DEFAULT_VOICE_TIPS);
    }
}
