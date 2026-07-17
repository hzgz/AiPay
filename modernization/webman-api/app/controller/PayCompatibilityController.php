<?php

declare(strict_types=1);

namespace app\controller;

use app\support\ApiResponse;
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
            $message = trim((string)($order['merchant_frozen_reason'] ?? '商户账户已冻结')) ?: '商户账户已冻结';
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
        $row = Db::table('ypay_order as orders')
            ->leftJoin('ypay_user as merchant', 'orders.user_id', '=', 'merchant.id')
            ->leftJoin('ypay_userbasic as basic', 'orders.user_id', '=', 'basic.user_id')
            ->leftJoin('ypay_account as account', 'orders.account_id', '=', 'account.id')
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
                'poll' => '/Pay/ConSole',
                'submit' => '/Pay/submit',
                'api_submit' => '/Pay/apisubmit',
                'legacy_console' => $this->legacyConsoleUrl(trim((string)($order['trade_no'] ?? ''))),
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
        $legacyUrls = (array)($payload['legacy_urls'] ?? []);

        $title = $this->escape((string)($order['name'] ?? '支付订单'));
        $amount = $this->escape((string)($order['truemoney'] ?? '0.00'));
        $tradeNo = $this->escape((string)($order['trade_no'] ?? ''));
        $outTradeNo = $this->escape((string)($order['out_trade_no'] ?? ''));
        $payType = $this->escape(strtoupper((string)($order['type'] ?? '')));
        $qrUrl = $this->escape((string)($order['qr_url'] ?? ''));
        $displayH5Url = $this->escape((string)($order['display_h5_qrurl'] ?? ''));
        $timeoutUrl = $this->escape((string)($console['timeout_url'] ?? '/'));
        $consoleNotice = $this->escape((string)($console['console_notice'] ?? ''));
        $legacyConsoleUrl = $this->escape((string)($legacyUrls['legacy_console'] ?? ''));
        $returnUrl = $this->escape((string)($status['merchant_return_url'] ?? ''));
        $state = (string)($status['state'] ?? 'pending');
        $stateLabel = $this->escape($this->stateLabel($state));
        $stateDescription = $this->escape($this->stateDescription($state));
        $countdown = (int)($console['timeout_seconds'] ?? 0);
        $countdownLabel = $this->escape($this->formatCountdown($countdown));
        $canLaunch = $displayH5Url !== '';
        $showQr = $qrUrl !== '';

        $pageState = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pageOutTradeNo = json_encode((string)($order['out_trade_no'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pagePollUrl = json_encode('/Pay/ConSole', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pageTimeoutUrl = json_encode((string)($console['timeout_url'] ?? '/'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pageLaunchUrl = json_encode((string)($order['display_h5_qrurl'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pageCountdown = json_encode($countdown);
        $pageAutoJump = json_encode(!empty($console['is_jump']));

        $qrMarkup = $showQr
            ? '<img id="qrImage" src="' . $qrUrl . '" alt="支付二维码" />'
            : '<div id="qrPlaceholder" class="placeholder">正在等待二维码生成</div>';
        $launchButton = $canLaunch
            ? '<a id="launchLink" class="btn primary" href="' . $displayH5Url . '">打开支付应用</a>'
            : '<a id="launchLink" class="btn primary hidden" href="#">打开支付应用</a>';
        $noticeMarkup = $consoleNotice !== ''
            ? '<p class="notice">' . $consoleNotice . '</p>'
            : '<p class="notice">系统会持续轮询支付状态；如支付已完成，请等待当前页面自动刷新结果。</p>';
        $paidButton = $returnUrl !== ''
            ? '<a class="btn success" href="' . $returnUrl . '">返回商户页面</a>'
            : '<a class="btn secondary" href="' . $timeoutUrl . '">返回上一页</a>';
        $paidActionsDisplay = $state === 'paid' ? 'flex' : 'none';

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>支付收银台</title>
  <style>
    :root{color-scheme:light;background:#f5f7fb;color:#0f172a}
    *{box-sizing:border-box}
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:
      radial-gradient(circle at top left,rgba(251,191,36,.18),transparent 36%),
      radial-gradient(circle at top right,rgba(59,130,246,.16),transparent 34%),
      linear-gradient(180deg,#f8fafc,#eef2ff 52%,#f8fafc)}
    .wrap{max-width:1120px;margin:0 auto;padding:28px 18px 42px}
    .shell{display:grid;grid-template-columns:1.15fr .85fr;gap:18px}
    .card{background:rgba(255,255,255,.94);border:1px solid rgba(148,163,184,.22);border-radius:28px;box-shadow:0 28px 90px rgba(15,23,42,.08)}
    .hero{padding:28px}
    .eyebrow{display:inline-flex;padding:6px 10px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
    h1{margin:16px 0 10px;font-size:34px;line-height:1.15}
    p{margin:0;color:#475569;line-height:1.8}
    .stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:22px}
    .stat{padding:16px;border-radius:20px;background:#fff;border:1px solid #e2e8f0}
    .label{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;font-weight:700}
    .value{margin-top:8px;font-size:18px;font-weight:700;color:#0f172a;word-break:break-word}
    .muted{font-size:13px;color:#64748b}
    .actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px}
    .btn{display:inline-flex;align-items:center;justify-content:center;padding:11px 16px;border-radius:14px;text-decoration:none;font-weight:700;border:1px solid transparent}
    .btn.primary{background:#111827;color:#fff}
    .btn.secondary{background:#fff;color:#0f172a;border-color:#cbd5e1}
    .btn.success{background:#14532d;color:#fff}
    .hidden{display:none}
    .panel{padding:24px}
    .qr-wrap{display:grid;place-items:center;min-height:320px;border-radius:24px;background:linear-gradient(180deg,#ffffff,#f8fafc);border:1px dashed #cbd5e1}
    .qr-wrap img{width:min(320px,100%);height:auto;border-radius:18px;background:#fff;padding:10px;box-shadow:0 18px 36px rgba(15,23,42,.08)}
    .placeholder{width:min(320px,100%);aspect-ratio:1;border-radius:18px;display:grid;place-items:center;background:repeating-linear-gradient(45deg,#e2e8f0,#e2e8f0 12px,#f8fafc 12px,#f8fafc 24px);color:#475569;font-weight:700;text-align:center;padding:24px}
    .status{margin-top:18px;padding:18px;border-radius:20px;background:#eff6ff;border:1px solid #bfdbfe}
    .status h2{margin:0 0 8px;font-size:22px}
    .status p{color:#1e3a8a}
    .list{display:grid;gap:12px;margin-top:18px}
    .row{display:flex;justify-content:space-between;gap:16px;padding:12px 14px;border-radius:16px;background:#fff;border:1px solid #e2e8f0}
    .row span:first-child{color:#64748b}
    .row code{font-size:12px;word-break:break-all}
    .notice{margin-top:18px;padding:14px 16px;border-radius:18px;background:#fffbeb;border:1px solid #fde68a;color:#92400e}
    .footer{margin-top:16px;font-size:13px;color:#64748b}
    @media (max-width:980px){.shell{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media (max-width:640px){.wrap{padding:16px 12px 28px}.hero,.panel{padding:18px}.stats{grid-template-columns:1fr}h1{font-size:28px}.row{display:grid;gap:6px}}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="shell">
      <section class="card hero">
        <span class="eyebrow">支付收银台</span>
        <h1>{$title}</h1>
        <p>当前页面继续承接原 <code>/Pay/console</code> 收银入口，统一展示支付状态与回跳结果。</p>
        <div class="stats">
          <div class="stat"><div class="label">支付金额</div><div class="value">{$amount}</div></div>
          <div class="stat"><div class="label">当前状态</div><div class="value" id="statusBadge">{$stateLabel}</div></div>
          <div class="stat"><div class="label">支付方式</div><div class="value">{$payType}</div></div>
          <div class="stat"><div class="label">剩余时间</div><div class="value" id="countdown">{$countdownLabel}</div></div>
        </div>
        <div class="list">
          <div class="row"><span>系统单号</span><span><code>{$tradeNo}</code></span></div>
          <div class="row"><span>商户单号</span><span><code>{$outTradeNo}</code></span></div>
          <div class="row"><span>超时返回地址</span><span><code>{$timeoutUrl}</code></span></div>
          <div class="row"><span>收银台入口</span><span><a href="{$legacyConsoleUrl}">打开收银台</a></span></div>
        </div>
        {$noticeMarkup}
        <div class="actions" id="primaryActions">
          {$launchButton}
          <a class="btn secondary" href="{$timeoutUrl}">取消支付</a>
        </div>
        <p class="footer">当前页面会继续承接已有下单与轮询链接，避免书签或已接入地址直接失效。</p>
      </section>
      <aside class="card panel">
        <div class="qr-wrap" id="qrWrap">{$qrMarkup}</div>
        <div class="status">
          <h2 id="statusTitle">{$stateLabel}</h2>
          <p id="statusText">{$stateDescription}</p>
        </div>
        <div class="actions" id="paidActions" style="margin-top:18px;display:{$paidActionsDisplay};">
          {$paidButton}
        </div>
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
  var statusBadge = document.getElementById('statusBadge');
  var statusTitle = document.getElementById('statusTitle');
  var statusText = document.getElementById('statusText');
  var countdownEl = document.getElementById('countdown');
  var qrWrap = document.getElementById('qrWrap');
  var launchLink = document.getElementById('launchLink');
  var paidActions = document.getElementById('paidActions');

  function labelFor(nextState) {
    if (nextState === 'paid') return '已支付';
    if (nextState === 'timeout') return '已超时';
    if (nextState === 'qrcode_loading') return '生成二维码中';
    if (nextState === 'qrcode_missing') return '二维码待生成';
    return '等待支付';
  }

  function textFor(nextState, message) {
    if (nextState === 'paid') return '支付成功，正在返回商户页面...';
    if (nextState === 'timeout') return '该订单已超时，未能在规定时间内完成支付。';
    if (nextState === 'qrcode_loading') return '上游通道正在生成二维码，请稍候。';
    if (nextState === 'qrcode_missing') return '二维码暂未就绪，请等待系统继续轮询。';
    return message || '正在等待下一次支付状态结果。';
  }

  function setState(nextState, message) {
    state = nextState;
    var label = labelFor(nextState);
    statusBadge.textContent = label;
    statusTitle.textContent = label;
    statusText.textContent = textFor(nextState, message);
  }

  function setQr(url) {
    if (!url) {
      return;
    }
    qrWrap.innerHTML = '<img id="qrImage" src="' + url.replace(/"/g, '&quot;') + '" alt="支付二维码" />';
  }

  function showLaunch(url) {
    if (!url || !launchLink) {
      return;
    }
    launchLink.href = url;
    launchLink.classList.remove('hidden');
  }

  function formatSeconds(seconds) {
    if (seconds <= 0) {
      return '00:00';
    }
    var mins = Math.floor(seconds / 60);
    var secs = seconds % 60;
    return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
  }

  function beginCountdown() {
    if (!countdownEl) {
      return;
    }
    countdownEl.textContent = formatSeconds(remaining);
    window.setInterval(function () {
      if (remaining <= 0 || state === 'paid') {
        return;
      }
      remaining -= 1;
      countdownEl.textContent = formatSeconds(remaining);
      if (remaining <= 0 && timeoutUrl) {
        setState('timeout');
        window.setTimeout(function () {
          window.location.href = timeoutUrl;
        }, 900);
      }
    }, 1000);
  }

  function maybeAutoJump() {
    var ua = navigator.userAgent || '';
    var isMobile = /(phone|pad|pod|iphone|ipod|ios|ipad|android|mobile|blackberry|iemobile|windows phone)/i.test(ua);
    if (isMobile && autoJump && launchUrl) {
      window.setTimeout(function () {
        window.location.href = launchUrl;
      }, 900);
    }
  }

  function poll() {
    if (!outTradeNo || state === 'paid') {
      return;
    }
    fetch(pollUrl + '?TradeNo=' + encodeURIComponent(outTradeNo) + '&_t=' + Date.now(), {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    }).then(function (response) {
      return response.json();
    }).then(function (data) {
      if (!data || typeof data !== 'object') {
        return;
      }
      if (data.code === 100) {
        setState('pending', '二维码已生成，系统会持续轮询直到支付完成。');
        setQr(data.qr_url || '');
        showLaunch(data.h5_qrurl || launchUrl);
        return;
      }
      if (data.code === 404) {
        setState('qrcode_loading');
        return;
      }
      if (data.code === 200) {
        setState('paid');
        if (paidActions) {
          paidActions.style.display = 'flex';
        }
        if (data.url) {
          window.setTimeout(function () {
            window.location.href = data.url;
          }, 800);
        }
        return;
      }
      if (data.msg === 'order_timeout') {
        setState('timeout');
        return;
      }
      if (data.msg === 'qrcode_missing') {
        setState('qrcode_missing');
        return;
      }
      if (typeof data.msg === 'string' && data.msg) {
        statusText.textContent = data.msg;
      }
    }).catch(function () {
      statusText.textContent = '状态轮询暂时失败，系统正在自动重试...';
    });
  }

  beginCountdown();
  maybeAutoJump();
  if (state !== 'paid' && state !== 'timeout') {
    window.setTimeout(poll, 600);
    window.setInterval(poll, 3000);
  }
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
  <title>支付收银台异常</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f8fafc;color:#0f172a}
    .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .card{width:min(520px,100%);background:#fff;border:1px solid #e2e8f0;border-radius:24px;box-shadow:0 20px 60px rgba(15,23,42,.08);padding:30px}
    h1{margin:0 0 12px;font-size:24px}
    p{margin:0 0 20px;line-height:1.8;color:#475569}
    a{display:inline-flex;padding:11px 16px;border-radius:12px;background:#111827;color:#fff;text-decoration:none}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>支付收银台异常</h1>
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
            'message' => $message,
        ], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

        if (preg_match('#^https?://#i', $content) === 1) {
            $path = (string)(parse_url($content, PHP_URL_PATH) ?: '');
            $isImageUrl = preg_match('/\.(png|jpe?g|gif|bmp|webp|svg)$/i', $path) === 1;
            if ($isImageUrl || strtolower(trim($accountCode)) !== 'alipay_bill') {
                return $content;
            }
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
            'paid' => '已支付',
            'timeout' => '已超时',
            'qrcode_loading' => '生成二维码中',
            'qrcode_missing' => '二维码待生成',
            default => '等待支付',
        };
    }

    private function stateDescription(string $state): string
    {
        return match ($state) {
            'paid' => '订单已支付，系统将使用签名后的商户回跳地址完成返回，不会额外变更 return_num。',
            'timeout' => '订单已超时，请通过超时返回地址离开当前页面。',
            'qrcode_loading' => '上游通道仍在生成二维码，系统会自动继续轮询。',
            'qrcode_missing' => '二维码尚未写入完成，系统会继续等待上游通道返回结果。',
            default => '请扫码支付或拉起支付应用。当前页面会持续轮询 `/Pay/ConSole`，直到订单支付成功或超时。',
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
            'primary_entry' => '/Pay/console',
            'poll_entry' => '/Pay/ConSole',
            'alias_entries' => ['/pay/console', '/pay/ConSole'],
            'allowed_methods' => ['GET', 'POST'],
            'write_policy' => 'no_callback_replay_or_status_mutation',
            'blocked_actions' => ['callback_replay', 'return_num_increment', 'status_reset'],
        ];
    }

    private function legacyConsoleUrl(string $tradeNo): string
    {
        $configured = FrontendUrlBuilder::configuredBaseUrl([
            'AIPAY_LEGACY_FRONTEND_URL',
            'AIPAY_PUBLIC_FRONTEND_URL',
            'AIPAY_MERCHANT_FRONTEND_URL',
            'AIPAY_ADMIN_FRONTEND_URL',
        ]);
        $base = $configured !== null ? rtrim($configured, '/') : 'http://127.0.0.1:8132';

        return $base . '/Pay/console?trade_no=' . rawurlencode($tradeNo);
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
