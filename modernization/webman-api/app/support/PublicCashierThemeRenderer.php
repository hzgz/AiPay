<?php

declare(strict_types=1);

namespace app\support;

final class PublicCashierThemeRenderer
{
    /**
     * @param array<string, mixed> $view
     */
    public static function render(string $themeId, array $view): string
    {
        $variant = self::variant($themeId);
        $themeClass = self::escape($variant['class']);
        $brandBadge = self::escape($variant['badge']);
        $helperTitle = self::escape($variant['helper_title']);

        $siteName = self::escape((string)($view['site_name'] ?? 'AiPay'));
        $title = self::escape((string)($view['title'] ?? '订单支付'));
        $amount = self::escape((string)($view['amount'] ?? '0.00'));
        $amountPrefix = self::escape((string)($view['amount_prefix'] ?? '￥'));
        $amountCaption = self::escape((string)($view['amount_caption'] ?? '订单金额'));
        $secondaryAmount = trim((string)($view['secondary_amount'] ?? ''));
        $secondaryHint = trim((string)($view['secondary_hint'] ?? ''));
        $walletAddress = trim((string)($view['wallet_address'] ?? ''));
        $payType = self::escape((string)($view['pay_type'] ?? '在线支付'));
        $tradeNo = self::escape((string)($view['trade_no'] ?? ''));
        $outTradeNo = self::escape((string)($view['out_trade_no'] ?? ''));
        $timeoutUrl = self::escape((string)($view['timeout_url'] ?? '/'));
        $notice = trim((string)($view['notice'] ?? ''));
        $state = trim((string)($view['state'] ?? 'pending'));
        $stateLabel = self::escape((string)($view['state_label'] ?? '等待支付'));
        $stateDescription = self::escape((string)($view['state_description'] ?? '请在有效期内完成支付。'));
        $countdownLabel = self::escape((string)($view['countdown_label'] ?? '00:00'));
        $placeholderText = self::escape((string)($view['placeholder_text'] ?? '二维码加载中，请稍候。'));
        $defaultScanTip = self::escape((string)($view['default_scan_tip'] ?? '请扫描二维码完成支付。'));
        $qrUrl = trim((string)($view['qr_url'] ?? ''));
        $launchUrl = trim((string)($view['launch_url'] ?? ''));
        $launchAction = trim((string)($view['launch_action'] ?? ''));
        $launchText = trim((string)($view['launch_text'] ?? ''));
        $okUrl = trim((string)($view['ok_url'] ?? ''));
        $stayOnPaidPage = !empty($view['stay_on_paid_page']);
        $countdown = max(0, (int)($view['countdown'] ?? 0));
        $isUsdt = !empty($view['is_usdt']);

        $qrMarkup = $qrUrl !== ''
            ? '<img id="qrImage" src="' . self::escape($qrUrl) . '" alt="支付二维码">'
            : '<div class="placeholder" id="qrPlaceholder">' . $placeholderText . '</div>';

        $walletRow = $walletAddress !== ''
            ? '<div class="row"><span>钱包地址</span><code>' . self::escape($walletAddress) . '</code></div>'
            : '';

        $amountMeta = '';
        if ($secondaryAmount !== '' || $secondaryHint !== '') {
            $amountMeta = '<div class="amount-meta">'
                . ($secondaryAmount !== '' ? '<span>' . self::escape($secondaryAmount) . '</span>' : '')
                . ($secondaryHint !== '' ? '<em>' . self::escape($secondaryHint) . '</em>' : '')
                . '</div>';
        }

        $noticeMarkup = $notice !== ''
            ? '<div class="notice" id="noticeBand">' . self::escape($notice) . '</div>'
            : '<div class="notice hidden" id="noticeBand"></div>';

        $pageState = self::json($state);
        $pageOutTradeNo = self::json((string)($view['out_trade_no_raw'] ?? ''));
        $pagePollUrl = self::json((string)($view['poll_url'] ?? '/api/public/cashier/poll'));
        $pageTimeoutUrl = self::json((string)($view['timeout_url_raw'] ?? '/'));
        $pageLaunchUrl = self::json($launchUrl);
        $pageLaunchAction = self::json($launchAction);
        $pageLaunchText = self::json($launchText);
        $pageAutoJump = self::json(!empty($view['auto_jump']));
        $pageCountdown = self::json($countdown);
        $pageTradeNo = self::json((string)($view['trade_no_raw'] ?? ''));
        $pagePayType = self::json((string)($view['pay_type_raw'] ?? '在线支付'));
        $pageNotice = self::json((string)($view['notice_raw'] ?? ''));
        $pageOkUrl = self::json($okUrl);
        $pageStayOnPaidPage = self::json($stayOnPaidPage);
        $pageIsUsdt = self::json($isUsdt);
        $pageDefaultScanTip = self::json((string)($view['default_scan_tip_raw'] ?? '请扫描二维码完成支付。'));

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>收银台支付</title>
  <style>
    :root{
      --surface:#ffffff;
      --surface-muted:#f8fafc;
      --text:#0f172a;
      --text-muted:#64748b;
      --line:#dbe5f3;
      --brand:#0f172a;
      --brand-soft:#dbeafe;
      --brand-text:#1d4ed8;
      --warn:#b45309;
      --warn-soft:#fffbeb;
      --shadow:0 24px 70px rgba(15,23,42,.12);
      --hero:radial-gradient(circle at top left,rgba(14,165,233,.16),transparent 28%),radial-gradient(circle at top right,rgba(129,140,248,.18),transparent 22%),linear-gradient(180deg,#f8fbff 0%,#eef4fb 48%,#f8fafc 100%);
    }
    body.theme-aurora{
      --surface:rgba(12,24,38,.95);
      --surface-muted:rgba(16,33,52,.92);
      --text:#e2e8f0;
      --text-muted:#93a4b8;
      --line:rgba(125,211,252,.16);
      --brand:#22d3ee;
      --brand-soft:rgba(8,47,73,.94);
      --brand-text:#67e8f9;
      --warn:#f59e0b;
      --warn-soft:rgba(120,53,15,.3);
      --shadow:0 28px 72px rgba(2,8,23,.38);
      --hero:radial-gradient(circle at top left,rgba(34,211,238,.18),transparent 28%),radial-gradient(circle at top right,rgba(251,191,36,.12),transparent 22%),linear-gradient(180deg,#07111d 0%,#0b1728 48%,#0f172a 100%);
    }
    *{box-sizing:border-box}
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;color:var(--text);background:var(--hero)}
    .page{max-width:1180px;margin:0 auto;padding:24px 18px 36px}
    .shell{display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:18px}
    .panel{background:var(--surface);border:1px solid rgba(148,163,184,.18);border-radius:28px;box-shadow:var(--shadow)}
    .summary{padding:28px}
    .checkout{padding:24px;position:relative;overflow:hidden;background:linear-gradient(180deg,var(--surface),var(--surface-muted))}
    .checkout:before{content:"";position:absolute;inset:18px;border-radius:22px;border:1px solid rgba(191,219,254,.24);pointer-events:none}
    .brand{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center}
    .pill{display:inline-flex;padding:7px 12px;border-radius:999px;background:var(--brand-soft);color:var(--brand-text);font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
    .site{font-size:13px;color:var(--text-muted);font-weight:600}
    h1{margin:16px 0 8px;font-size:34px;line-height:1.12}
    .sub{margin:0;color:var(--text-muted);line-height:1.75}
    .amount{display:flex;align-items:flex-end;gap:10px;margin-top:22px}
    .amount span{font-size:24px;font-weight:700;color:var(--text-muted)}
    .amount strong{font-size:52px;line-height:.95;font-weight:800;letter-spacing:-.04em}
    .amount-meta{display:flex;gap:10px;flex-wrap:wrap;margin-top:8px}
    .amount-meta span,.amount-meta em{font-style:normal;color:var(--text-muted);font-size:13px}
    .metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:22px}
    .metric{padding:16px;border:1px solid var(--line);border-radius:20px;background:linear-gradient(180deg,var(--surface),var(--surface-muted))}
    .metric em{display:block;font-style:normal;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);font-weight:700}
    .metric strong{display:block;margin-top:8px;font-size:18px;line-height:1.4;word-break:break-word}
    .rows{display:grid;gap:12px;margin-top:22px}
    .row{display:flex;justify-content:space-between;gap:16px;padding:14px 16px;border:1px solid var(--line);border-radius:18px;background:var(--surface-muted)}
    .row span{color:var(--text-muted);font-size:14px}
    .row code{margin:0;font-size:13px;word-break:break-all;white-space:pre-wrap;text-align:right;color:var(--text)}
    .notice{margin-top:18px;padding:16px;border-radius:18px;background:var(--warn-soft);border:1px solid rgba(245,158,11,.28);color:var(--warn);line-height:1.75}
    .actions,.checkout-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:20px}
    .btn{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 18px;border-radius:14px;border:1px solid transparent;text-decoration:none;cursor:pointer;font-size:14px;font-weight:700}
    .btn.primary{background:var(--brand);color:#fff}
    .btn.secondary{background:var(--surface);color:var(--text);border-color:var(--line)}
    .btn.ghost{background:var(--surface-muted);color:var(--text);border-color:var(--line)}
    .hidden{display:none !important}
    .head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start}
    .label{font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:var(--brand);font-weight:700}
    .head h2{margin:10px 0 0;font-size:28px;line-height:1.15}
    .state-tag{padding:8px 12px;border-radius:999px;background:var(--brand-soft);border:1px solid rgba(59,130,246,.18);font-size:12px;font-weight:700;color:var(--brand-text)}
    .state-text{margin:12px 0 0;color:var(--text-muted);line-height:1.75}
    .qrbox{margin-top:22px;min-height:340px;display:grid;place-items:center;padding:18px;border-radius:28px;background:linear-gradient(180deg,var(--surface),var(--surface-muted));border:1px solid var(--line);box-shadow:inset 0 0 0 1px rgba(226,232,240,.18)}
    .qrbox img{display:block;width:min(100%,310px);height:auto;padding:12px;border-radius:20px;background:#fff;box-shadow:0 20px 44px rgba(15,23,42,.12)}
    .placeholder{width:min(100%,310px);aspect-ratio:1;display:grid;place-items:center;padding:24px;border-radius:20px;border:1px dashed var(--line);background:repeating-linear-gradient(45deg,rgba(191,219,254,.18),rgba(191,219,254,.18) 14px,transparent 14px,transparent 28px);color:var(--text-muted);font-weight:700;line-height:1.7;text-align:center}
    .scan-meta{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:16px;padding:14px 16px;border:1px solid var(--line);border-radius:18px;background:var(--surface-muted)}
    .scan-tip{margin:12px 0 0;color:var(--text-muted);line-height:1.75}
    .timer-inline{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .timer-inline em{font-style:normal;font-size:13px;color:var(--text-muted)}
    .timer-inline strong{font-size:22px;color:var(--text);font-variant-numeric:tabular-nums}
    #cashierStage[data-state="timeout"] .state-tag{background:#fef2f2;border-color:#fecaca;color:#dc2626}
    #cashierStage[data-state="qrcode_loading"] .state-tag,
    #cashierStage[data-state="qrcode_missing"] .state-tag,
    #cashierStage[data-state="reconciling"] .state-tag{background:#fffbeb;border-color:#fde68a;color:#b45309}
    @media (max-width:980px){.shell{grid-template-columns:1fr}}
    @media (max-width:720px){
      .page{padding:14px 12px 24px}
      .summary,.checkout{padding:18px}
      h1{font-size:28px}
      .amount strong{font-size:42px}
      .metrics{grid-template-columns:1fr}
      .row{display:grid;gap:8px}
      .row code{text-align:left}
      .head h2{font-size:24px}
      .qrbox{min-height:300px}
    }
  </style>
</head>
<body class="{$themeClass}">
  <div class="page">
    <div class="shell" id="cashierStage" data-state="{$state}">
      <section class="panel summary">
        <div class="brand"><span class="pill">{$brandBadge}</span><span class="site">{$siteName}</span></div>
        <h1>{$title}</h1>
        <p class="sub">请核对订单信息后完成付款，页面会自动轮询支付状态，无需手动刷新。</p>
        <div class="amount"><span>{$amountPrefix}</span><strong>{$amount}</strong></div>
        {$amountMeta}
        <div class="metrics">
          <div class="metric"><em>支付方式</em><strong>{$payType}</strong></div>
          <div class="metric"><em>当前状态</em><strong id="statusBadge">{$stateLabel}</strong></div>
          <div class="metric"><em>剩余时间</em><strong id="countdown">{$countdownLabel}</strong></div>
        </div>
        <div class="rows">
          {$walletRow}
          <div class="row"><span>系统订单号</span><code>{$tradeNo}</code></div>
          <div class="row"><span>商户订单号</span><code>{$outTradeNo}</code></div>
        </div>
        {$noticeMarkup}
        <div class="actions">
          <button type="button" class="btn secondary" id="copyTradeNoButton">复制订单号</button>
          <a class="btn ghost" href="{$timeoutUrl}">取消支付</a>
        </div>
      </section>
      <aside class="panel checkout">
        <div class="head">
          <div>
            <div class="label">{$helperTitle}</div>
            <h2 id="statusTitle">{$stateLabel}</h2>
          </div>
          <span class="state-tag" id="statusTag">{$stateLabel}</span>
        </div>
        <p class="state-text" id="statusText">{$stateDescription}</p>
        <div class="qrbox" id="qrWrap">{$qrMarkup}</div>
        <div class="scan-meta">
          <div class="timer-inline"><em>剩余时间</em><strong id="countdownPanel">{$countdownLabel}</strong></div>
          <div class="checkout-actions">
            <a id="launchLink" class="btn primary hidden" href="#" rel="nofollow">立即支付</a>
            <button type="button" class="btn secondary hidden" id="copyLaunchButton">复制支付链接</button>
          </div>
        </div>
        <p class="scan-tip" id="scanTip">{$defaultScanTip}</p>
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
  var launchAction = {$pageLaunchAction};
  var launchText = {$pageLaunchText};
  var autoJump = {$pageAutoJump};
  var remaining = {$pageCountdown};
  var tradeNo = {$pageTradeNo};
  var payType = {$pagePayType};
  var noticeText = {$pageNotice};
  var okUrl = {$pageOkUrl};
  var stayOnPaidPage = {$pageStayOnPaidPage};
  var isUsdt = {$pageIsUsdt};
  var defaultScanTip = {$pageDefaultScanTip};
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
  var redirected = false;

  function labelFor(nextState) {
    if (nextState === 'paid') return '支付成功';
    if (nextState === 'timeout') return '订单超时';
    if (nextState === 'reconciling') return '到账核对中';
    if (nextState === 'qrcode_loading') return '二维码生成中';
    if (nextState === 'qrcode_missing') return '等待二维码';
    return '等待支付';
  }

  function textFor(nextState, message) {
    if (nextState === 'paid') return stayOnPaidPage ? '支付结果已确认，可关闭当前页面并返回商户后台继续操作。' : '支付结果已确认，正在跳转到支付成功页。';
    if (nextState === 'timeout') return isUsdt ? '订单已超时，如已转账请等待系统继续核对到账结果。' : '订单已超时，请返回后重新发起支付。';
    if (nextState === 'reconciling') return isUsdt ? '订单超时后仍在核对链上到账结果，请勿重复转账。' : '支付时限已到，系统仍在核对到账结果。';
    if (nextState === 'qrcode_loading') return isUsdt ? '钱包地址二维码正在准备，请稍候。' : '上游通道正在生成支付二维码，请稍候。';
    if (nextState === 'qrcode_missing') return isUsdt ? '钱包地址暂未返回，请等待系统继续刷新。' : '二维码暂未返回，请等待系统继续刷新。';
    return message || (isUsdt ? '请按页面显示金额向钱包地址转账，系统会自动刷新到账状态。' : '请使用二维码完成支付，系统会自动同步支付状态。');
  }

  function placeholderFor(nextState) {
    if (nextState === 'paid') return stayOnPaidPage ? '支付成功，可关闭当前页面。' : '支付成功，正在跳转结果页。';
    if (nextState === 'timeout') return '当前订单已超时，请重新发起支付。';
    if (nextState === 'qrcode_loading') return '正在生成支付二维码，请稍候。';
    if (nextState === 'qrcode_missing') return '支付二维码暂未就绪，请等待系统刷新。';
    return '二维码加载中，请稍候。';
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function isMobile() {
    return /(phone|pad|pod|iphone|ipod|ios|ipad|android|mobile|blackberry|iemobile|windows phone)/i.test(navigator.userAgent || '');
  }

  function isScheme(url) {
    return /^[a-z][a-z0-9+.-]*:\/\//i.test(url || '') && !/^https?:\/\//i.test(url || '');
  }

  function redirectToResult(url) {
    if (redirected || !url) {
      return;
    }

    redirected = true;
    setState('paid');
    setQr('');
    window.setTimeout(function () {
      window.location.href = url;
    }, 280);
  }

  function showPaidInline(message) {
    remaining = 0;
    setState('paid', message || '');
    setQr('');
    showLaunch('', '', '');
    if (countdownEl) countdownEl.textContent = '00:00';
    if (countdownPanelEl) countdownPanelEl.textContent = '00:00';
  }

  function bindQrError() {
    var image = document.getElementById('qrImage');
    if (!image) return;
    image.onerror = function () {
      qrWrap.innerHTML = '<div class="placeholder">二维码加载失败，请刷新页面后重试。</div>';
      setState('qrcode_missing', '二维码图片暂时无法显示，请等待系统重试或刷新页面。');
    };
  }

  function scanTipText(url) {
    if (state === 'paid') return stayOnPaidPage ? '支付成功，可关闭当前页面并返回后台继续操作。' : '支付成功，正在跳转结果页。';
    if (state === 'timeout') return isUsdt ? '订单已超时，如转账已经发出，请等待系统继续核对到账结果。' : '订单已超时，请重新创建新的支付订单。';
    if (state === 'reconciling') return isUsdt ? '系统仍在核对链上到账结果，请勿重复转账。' : '系统仍在核对到账结果，请稍候。';
    if (isUsdt) return defaultScanTip;
    if (!url) return '请使用 ' + payType + ' 扫描二维码完成支付。';
    if (isScheme(url)) return isMobile() ? '如未自动拉起支付应用，可点击下方按钮继续支付。' : '当前设备无法直接拉起支付应用，请使用手机扫码完成支付。';
    return isMobile() ? '如需直接前往支付页，可点击下方按钮继续。' : '可以直接扫码完成支付，也可打开支付页面继续。';
  }

  function setState(nextState, message) {
    state = nextState;
    var label = labelFor(nextState);
    if (stage) stage.setAttribute('data-state', nextState);
    statusBadge.textContent = label;
    statusTag.textContent = label;
    statusTitle.textContent = label;
    statusText.textContent = textFor(nextState, message);
    if (noticeBand) {
      noticeBand.classList.remove('hidden');
      noticeBand.textContent = nextState === 'paid'
        ? (stayOnPaidPage
            ? '订单已支付完成，可关闭当前页面并返回商户后台继续操作。'
            : '订单已支付完成，当前页面将自动跳转到支付成功页。')
        : nextState === 'timeout'
          ? (isUsdt ? '订单已超时，如已转账请等待系统继续核对到账结果。' : '订单超时后请重新发起支付，避免继续使用失效二维码。')
          : (noticeText || textFor(nextState, message));
    }
    if (scanTip) scanTip.textContent = scanTipText(launchUrl);
  }

  function setQr(url) {
    if (!url) {
      qrWrap.innerHTML = '<div class="placeholder">' + escapeHtml(placeholderFor(state)) + '</div>';
      return;
    }
    qrWrap.innerHTML = '<img id="qrImage" src="' + escapeHtml(url) + '" alt="支付二维码">';
    bindQrError();
  }

  function showLaunch(url, action, text) {
    launchUrl = (url || '').trim();
    launchAction = (action || launchAction || '').trim();
    launchText = (text || launchText || '').trim();
    if (!launchLink) return;
    if (launchAction === 'copy_wallet') {
      launchLink.href = '#';
      launchLink.textContent = launchText || '复制地址';
      launchLink.classList.toggle('hidden', launchUrl === '');
      if (copyLaunchButton) copyLaunchButton.classList.add('hidden');
      if (scanTip) scanTip.textContent = scanTipText(launchUrl);
      return;
    }
    if (launchUrl === '') {
      launchLink.classList.add('hidden');
      if (copyLaunchButton) copyLaunchButton.classList.add('hidden');
      if (scanTip) scanTip.textContent = scanTipText('');
      return;
    }
    var scheme = isScheme(launchUrl);
    launchLink.href = launchUrl;
    launchLink.textContent = launchText || (scheme ? '打开支付应用' : '立即支付');
    launchLink.classList.toggle('hidden', scheme && !isMobile());
    if (copyLaunchButton) copyLaunchButton.classList.toggle('hidden', !(scheme || /^https?:\/\//i.test(launchUrl)));
    if (scanTip) scanTip.textContent = scanTipText(launchUrl);
  }

  function formatSeconds(seconds) {
    if (seconds <= 0) return '00:00';
    var mins = Math.floor(seconds / 60);
    var secs = seconds % 60;
    return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
  }

  function beginCountdown() {
    if (!countdownEl || !countdownPanelEl) return;
    countdownEl.textContent = formatSeconds(remaining);
    countdownPanelEl.textContent = formatSeconds(remaining);
    window.setInterval(function () {
      if (remaining <= 0 || state === 'paid') return;
      remaining -= 1;
      countdownEl.textContent = formatSeconds(remaining);
      countdownPanelEl.textContent = formatSeconds(remaining);
      if (remaining <= 0 && timeoutUrl) {
        if (isUsdt) {
          setState('reconciling');
          return;
        }
        setState('timeout');
        setQr('');
        window.setTimeout(function () {
          window.location.href = timeoutUrl;
        }, 1200);
      }
    }, 1000);
  }

  function maybeAutoJump() {
    if (launchAction === 'copy_wallet') return;
    if (isMobile() && autoJump && launchUrl) {
      window.setTimeout(function () {
        window.location.href = launchUrl;
      }, 900);
    }
  }

  function flash(button, text) {
    if (!button) return;
    var original = button.textContent;
    button.textContent = text;
    window.setTimeout(function () {
      button.textContent = original;
    }, 1400);
  }

  function copyText(value, button) {
    if (!value || !navigator.clipboard || !navigator.clipboard.writeText) {
      flash(button, '复制失败');
      return;
    }
    navigator.clipboard.writeText(value).then(function () {
      flash(button, '已复制');
    }).catch(function () {
      flash(button, '复制失败');
    });
  }

  function poll() {
    if (!outTradeNo || state === 'paid' || redirected) return;
    fetch(pollUrl + '?TradeNo=' + encodeURIComponent(outTradeNo) + '&_t=' + Date.now(), {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (!data || typeof data !== 'object') return;
        if (data.code === 100) {
          setState('pending', isUsdt ? '钱包地址已就绪，请按页面金额完成转账。' : '支付二维码已就绪，请尽快完成付款。');
          setQr(data.qr_url || '');
          showLaunch(isUsdt ? launchUrl : (data.h5_qrurl || launchUrl), launchAction, launchText);
          return;
        }
        if (data.code === 404) {
          setState('qrcode_loading');
          setQr('');
          return;
        }
        if (data.code === 200) {
          if (stayOnPaidPage || data.stay_on_paid_page) {
            showPaidInline(typeof data.message === 'string' && data.message ? data.message : '');
            return;
          }
          redirectToResult(data.ok_url || data.url || okUrl);
          return;
        }
        if (data.msg === 'order_timeout') {
          setState(isUsdt ? 'reconciling' : 'timeout');
          if (!isUsdt) setQr('');
          return;
        }
        if (data.msg === 'qrcode_missing') {
          setState('qrcode_missing');
          setQr('');
          return;
        }
        if (typeof data.message === 'string' && data.message) {
          statusText.textContent = data.message;
          return;
        }
        if (typeof data.msg === 'string' && data.msg) {
          statusText.textContent = data.msg;
        }
      })
      .catch(function () {
        statusText.textContent = '状态轮询暂时失败，系统正在自动重试。';
      });
  }

  if (copyTradeNoButton) copyTradeNoButton.addEventListener('click', function () { copyText(tradeNo, copyTradeNoButton); });
  if (copyLaunchButton) copyLaunchButton.addEventListener('click', function () { copyText(launchUrl, copyLaunchButton); });
  if (launchLink) {
    launchLink.addEventListener('click', function (event) {
      if (launchAction === 'copy_wallet') {
        event.preventDefault();
        copyText(launchUrl, launchLink);
      }
    });
  }

  if (isUsdt && state === 'timeout') {
    setState('reconciling');
  }

  bindQrError();
  beginCountdown();
  showLaunch(launchUrl, launchAction, launchText);
  maybeAutoJump();

  if (state === 'paid') {
    if (stayOnPaidPage) {
      showPaidInline('');
      return;
    }
    if (okUrl) {
      redirectToResult(okUrl);
      return;
    }
  }

  if (state !== 'paid' && !(state === 'timeout' && !isUsdt)) {
    window.setTimeout(poll, 600);
    window.setInterval(poll, 3000);
    return;
  }
})();
</script>
</body>
</html>
HTML;
    }

    /**
     * @return array{class: string, badge: string, helper_title: string}
     */
    private static function variant(string $themeId): array
    {
        return match (strtolower(trim($themeId))) {
            'aurora' => [
                'class' => 'theme-aurora',
                'badge' => 'AiPay Aurora Checkout',
                'helper_title' => '沉浸式支付',
            ],
            default => [
                'class' => 'theme-default',
                'badge' => 'AiPay Checkout',
                'helper_title' => '标准收银台',
            ],
        };
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function json(mixed $value): string
    {
        return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
    }
}
