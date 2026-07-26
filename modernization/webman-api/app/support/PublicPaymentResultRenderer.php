<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\support;

final class PublicPaymentResultRenderer
{
    /**
     * @param array<string, mixed> $view
     */
    public static function render(array $view): string
    {
        $siteName = self::escape((string)($view['site_name'] ?? 'AiPay'));
        $badge = self::escape((string)($view['badge'] ?? '支付结果'));
        $title = self::escape((string)($view['title'] ?? '支付成功'));
        $subtitle = self::escape((string)($view['subtitle'] ?? '订单已完成支付，系统正在处理后续流程。'));
        $amount = self::escape((string)($view['amount'] ?? '0.00'));
        $amountPrefix = self::escape((string)($view['amount_prefix'] ?? '￥'));
        $amountCaption = self::escape((string)($view['amount_caption'] ?? '支付金额'));
        $secondaryAmount = trim((string)($view['secondary_amount'] ?? ''));
        $secondaryHint = trim((string)($view['secondary_hint'] ?? ''));
        $payType = self::escape((string)($view['pay_type'] ?? '在线支付'));
        $tradeNo = self::escape((string)($view['trade_no'] ?? ''));
        $outTradeNo = self::escape((string)($view['out_trade_no'] ?? ''));
        $completedAt = self::escape((string)($view['completed_at'] ?? ''));
        $primaryButtonLabel = self::escape((string)($view['primary_button_label'] ?? '返回'));
        $primaryButtonUrl = self::escape((string)($view['primary_button_url'] ?? '/'));
        $secondaryButtonLabel = trim((string)($view['secondary_button_label'] ?? ''));
        $secondaryButtonUrl = trim((string)($view['secondary_button_url'] ?? ''));
        $autoRedirectUrl = trim((string)($view['auto_redirect_url'] ?? ''));
        $autoRedirectSeconds = max(0, (int)($view['auto_redirect_seconds'] ?? 0));
        $autoRedirectLabel = self::escape((string)($view['auto_redirect_label'] ?? '即将自动跳转'));
        $notice = trim((string)($view['notice'] ?? ''));

        $secondaryMeta = '';
        if ($secondaryAmount !== '' || $secondaryHint !== '') {
            $secondaryMeta = '<div class="amount-meta">'
                . ($secondaryAmount !== '' ? '<span>' . self::escape($secondaryAmount) . '</span>' : '')
                . ($secondaryHint !== '' ? '<em>' . self::escape($secondaryHint) . '</em>' : '')
                . '</div>';
        }

        $secondaryButton = '';
        if ($secondaryButtonLabel !== '' && $secondaryButtonUrl !== '') {
            $secondaryButton = '<a class="btn secondary" href="'
                . self::escape($secondaryButtonUrl)
                . '">'
                . self::escape($secondaryButtonLabel)
                . '</a>';
        }

        $noticeMarkup = $notice !== ''
            ? '<div class="notice">' . self::escape($notice) . '</div>'
            : '';

        $autoRedirectEnabled = $autoRedirectUrl !== '' && $autoRedirectSeconds > 0;
        $autoRedirectBanner = $autoRedirectEnabled
            ? '<div class="redirect-banner"><span>' . $autoRedirectLabel . '</span><strong id="redirectCountdown">'
                . $autoRedirectSeconds
                . '</strong></div>'
            : '';

        $pageAutoRedirectUrl = self::json($autoRedirectUrl);
        $pageAutoRedirectSeconds = self::json($autoRedirectSeconds);

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>支付结果</title>
  <style>
    :root{
      --surface:rgba(255,255,255,.94);
      --surface-alt:rgba(248,250,252,.95);
      --line:rgba(148,163,184,.24);
      --text:#0f172a;
      --text-muted:#64748b;
      --brand:#0f766e;
      --brand-strong:#115e59;
      --brand-soft:rgba(20,184,166,.12);
      --success:#15803d;
      --success-soft:rgba(34,197,94,.14);
      --shadow:0 28px 80px rgba(15,23,42,.14);
      --page:radial-gradient(circle at top left,rgba(20,184,166,.18),transparent 26%),radial-gradient(circle at top right,rgba(59,130,246,.14),transparent 24%),linear-gradient(180deg,#f8fbff 0%,#eef5fb 45%,#f8fafc 100%);
    }
    *{box-sizing:border-box}
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:var(--page);color:var(--text)}
    .page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .card{width:min(780px,100%);padding:30px;border-radius:30px;background:var(--surface);border:1px solid var(--line);box-shadow:var(--shadow);backdrop-filter:blur(18px)}
    .top{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;align-items:center}
    .pill{display:inline-flex;align-items:center;gap:8px;padding:8px 13px;border-radius:999px;background:var(--brand-soft);color:var(--brand-strong);font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
    .site{font-size:13px;color:var(--text-muted);font-weight:600}
    .hero{display:grid;grid-template-columns:minmax(0,1fr) 210px;gap:18px;margin-top:22px}
    .hero-main{padding:26px;border-radius:24px;background:linear-gradient(180deg,rgba(255,255,255,.98),var(--surface-alt));border:1px solid rgba(203,213,225,.58)}
    .hero-main h1{margin:0;font-size:36px;line-height:1.08}
    .hero-main p{margin:12px 0 0;color:var(--text-muted);line-height:1.75}
    .amount{margin-top:22px;display:flex;align-items:flex-end;gap:10px}
    .amount span{font-size:24px;color:var(--text-muted);font-weight:700}
    .amount strong{font-size:54px;line-height:.95;font-weight:800;letter-spacing:-.04em}
    .amount-meta{display:flex;flex-wrap:wrap;gap:10px;margin-top:10px}
    .amount-meta span,.amount-meta em{font-style:normal;font-size:13px;color:var(--text-muted)}
    .hero-side{display:flex;flex-direction:column;justify-content:center;gap:12px;padding:24px;border-radius:24px;background:linear-gradient(180deg,rgba(220,252,231,.95),rgba(240,253,244,.92));border:1px solid rgba(34,197,94,.22)}
    .hero-side .icon{width:58px;height:58px;display:grid;place-items:center;border-radius:18px;background:var(--success);color:#fff;font-size:20px;font-weight:800;letter-spacing:.08em}
    .hero-side strong{font-size:16px}
    .hero-side span{color:var(--text-muted);line-height:1.7}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:18px}
    .item{padding:18px;border-radius:20px;background:linear-gradient(180deg,rgba(255,255,255,.98),var(--surface-alt));border:1px solid rgba(203,213,225,.5)}
    .item span{display:block;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);font-weight:700}
    .item strong{display:block;margin-top:10px;font-size:16px;line-height:1.7;word-break:break-word}
    .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace}
    .notice{margin-top:18px;padding:16px 18px;border-radius:18px;background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.16);color:#1d4ed8;line-height:1.75}
    .redirect-banner{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:18px;padding:16px 18px;border-radius:18px;background:rgba(15,118,110,.08);border:1px solid rgba(15,118,110,.15);color:var(--brand-strong)}
    .redirect-banner strong{font-size:28px;line-height:1;font-variant-numeric:tabular-nums}
    .actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}
    .btn{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 18px;border-radius:14px;text-decoration:none;font-size:14px;font-weight:700}
    .btn.primary{background:var(--brand-strong);color:#fff}
    .btn.secondary{background:#fff;color:var(--text);border:1px solid var(--line)}
    @media (max-width:860px){
      .hero{grid-template-columns:1fr}
    }
    @media (max-width:720px){
      .page{padding:16px}
      .card{padding:20px;border-radius:24px}
      .hero-main{padding:20px}
      .hero-main h1{font-size:30px}
      .amount strong{font-size:44px}
      .grid{grid-template-columns:1fr}
      .redirect-banner{align-items:flex-start;flex-direction:column}
    }
  </style>
</head>
<body>
  <div class="page">
    <main class="card">
      <div class="top">
        <span class="pill">{$badge}</span>
        <span class="site">{$siteName}</span>
      </div>
      <section class="hero">
        <div class="hero-main">
          <h1>{$title}</h1>
          <p>{$subtitle}</p>
          <div class="amount">
            <span>{$amountPrefix}</span>
            <strong>{$amount}</strong>
          </div>
          {$secondaryMeta}
        </div>
        <div class="hero-side">
          <div class="icon">OK</div>
          <strong>{$amountCaption}</strong>
          <span>订单状态已同步完成，可以安全返回或继续后续流程。</span>
        </div>
      </section>

      <section class="grid">
        <div class="item">
          <span>支付方式</span>
          <strong>{$payType}</strong>
        </div>
        <div class="item">
          <span>完成时间</span>
          <strong>{$completedAt}</strong>
        </div>
        <div class="item">
          <span>系统订单号</span>
          <strong class="mono">{$tradeNo}</strong>
        </div>
        <div class="item">
          <span>商户订单号</span>
          <strong class="mono">{$outTradeNo}</strong>
        </div>
      </section>

      {$noticeMarkup}
      {$autoRedirectBanner}

      <div class="actions">
        <a class="btn primary" href="{$primaryButtonUrl}">{$primaryButtonLabel}</a>
        {$secondaryButton}
      </div>
    </main>
  </div>
  <script>
    (function () {
      var autoRedirectUrl = {$pageAutoRedirectUrl};
      var remaining = {$pageAutoRedirectSeconds};
      var countdown = document.getElementById('redirectCountdown');

      if (!autoRedirectUrl || remaining <= 0) {
        return;
      }

      window.setInterval(function () {
        if (remaining <= 0) {
          window.location.href = autoRedirectUrl;
          return;
        }

        remaining -= 1;
        if (countdown) {
          countdown.textContent = String(remaining);
        }

        if (remaining <= 0) {
          window.location.href = autoRedirectUrl;
        }
      }, 1000);
    })();
  </script>
</body>
</html>
HTML;
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
