<?php

declare(strict_types=1);

namespace app\controller;

use app\support\ApiResponse;
use app\support\BusinessTable;
use app\support\FrontendUrlBuilder;
use app\support\LegacyMojibakeGuard;
use app\support\MerchantPortalMessageCatalog;
use app\support\PaymentResultPageTicket;
use app\support\PublicCashierThemeRenderer;
use app\support\PublicPaymentResultRenderer;
use app\support\ThemeCatalog;
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

        if (
            !$this->wantsJson($request)
            && (int)($order['status'] ?? 0) === 1
            && !$this->shouldStayOnPaidPage($order)
        ) {
            return redirect($this->paymentResultPageUrl($request, $order));
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

    public function ok(Request $request): Response
    {
        $ticketValue = trim((string)$request->input('ticket', ''));
        $ticket = PaymentResultPageTicket::read($ticketValue);
        if ($ticket === null) {
            return response($this->errorPage('支付结果页凭证无效或已过期', '/'), 400, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]);
        }

        $order = $this->findOrderByTradeNo((string)($ticket['trade_no'] ?? ''));
        if ($order === null || !$this->validatePaymentResultTicketOrder($ticket, $order)) {
            return response($this->errorPage('未找到对应订单或凭证校验失败', '/'), 404, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]);
        }

        if ((int)($order['status'] ?? 0) !== 1) {
            return redirect($this->cashierConsoleUrl($request, trim((string)($order['trade_no'] ?? ''))));
        }

        return response(
            PublicPaymentResultRenderer::render(
                $this->paymentResultPageView(
                    $request,
                    $order,
                    PaymentResultPageTicket::normalizeScene((string)($ticket['scene'] ?? ''), $order)
                )
            ),
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
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
            $stayOnPaidPage = $this->shouldStayOnPaidPage($order);
            $okUrl = $stayOnPaidPage ? '' : $this->paymentResultPageUrl($request, $order);

            return $this->legacyPollResponse(200, 'order_paid', [
                'url' => $okUrl,
                'ok_url' => $okUrl,
                'return_url' => $this->merchantReturnUrl($order),
                'stay_on_paid_page' => $stayOnPaidPage,
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
                'orders.api_memo',
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
                'account.qr_type as account_qr_type',
                'account.wxname as account_wallet',
                'account.qr_url as account_qr_url',
                'account.cookie as account_cookie'
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
        $stayOnPaidPage = $this->shouldStayOnPaidPage($order);
        $okUrl = $stayOnPaidPage ? '' : $this->paymentResultPageUrl($request, $order);
        $state = $this->consoleState($order);
        $remainingSeconds = max(0, (int)($order['out_time'] ?? 0) - time());
        $displayH5Url = $this->displayH5QrUrl((string)($order['account_code'] ?? ''), (string)($order['h5_qrurl'] ?? ''));
        $display = $this->consoleDisplayMeta($order, $displayH5Url);

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
            'display' => $display,
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
                'ok_url' => $okUrl,
                'stay_on_paid_page' => $stayOnPaidPage,
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

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private function consoleDisplayMeta(array $order, string $defaultLaunchUrl): array
    {
        $type = strtolower(trim((string)($order['type'] ?? '')));
        $accountCode = strtolower(trim((string)($order['account_code'] ?? '')));
        $isUsdt = $type === 'usdt' || $accountCode === 'usdt';
        $baseAmount = number_format((float)($order['money'] ?? 0), 2, '.', '');
        $payAmount = number_format(
            (float)($isUsdt ? ($order['truemoney'] ?? $order['money'] ?? 0) : ($order['truemoney'] ?? $order['money'] ?? 0)),
            2,
            '.',
            ''
        );

        $display = [
            'is_usdt' => $isUsdt,
            'primary_amount' => $payAmount,
            'primary_prefix' => $isUsdt ? 'USDT' : '￥',
            'primary_caption' => $isUsdt ? '应付金额' : '订单金额',
            'secondary_amount' => $isUsdt ? ('下单金额 ￥' . $baseAmount) : '',
            'secondary_hint' => '',
            'wallet_address' => '',
            'exchange_rate' => '',
            'launch_action' => '',
            'launch_text' => '',
            'launch_value' => $defaultLaunchUrl,
        ];

        if (!$isUsdt) {
            return $display;
        }

        $usdtConfig = $this->decodeUsdtConsoleConfig($order);
        $display['wallet_address'] = $usdtConfig['wallet_address'];
        $display['exchange_rate'] = $usdtConfig['exchange_rate'];
        $display['secondary_hint'] = $usdtConfig['exchange_rate'] !== ''
            ? '汇率 1 USDT = ￥' . $usdtConfig['exchange_rate']
            : '';
        $display['launch_action'] = $usdtConfig['wallet_address'] !== '' ? 'copy_wallet' : '';
        $display['launch_text'] = $usdtConfig['wallet_address'] !== '' ? '复制地址' : '';
        $display['launch_value'] = $usdtConfig['wallet_address'] !== '' ? $usdtConfig['wallet_address'] : '';

        return $display;
    }

    /**
     * @param array<string, mixed> $order
     * @return array{wallet_address: string, memo: string, exchange_rate: string}
     */
    private function decodeUsdtConsoleConfig(array $order): array
    {
        $raw = trim((string)($order['account_cookie'] ?? ''));
        $exchangeRate = '';

        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $exchangeRate = $this->sanitizeUsdtExchangeRateText(
                    $decoded['exchange_rate'] ?? ($decoded['rate'] ?? '')
                );
            } else {
                $exchangeRate = $this->sanitizeUsdtExchangeRateText($raw);
            }
        }

        return [
            'wallet_address' => trim((string)($order['account_wallet'] ?? '')),
            'memo' => trim((string)($order['account_qr_url'] ?? '')),
            'exchange_rate' => $exchangeRate,
        ];
    }

    private function sanitizeUsdtExchangeRateText(mixed $value): string
    {
        $raw = trim((string)$value);
        if ($raw === '' || !preg_match('/^\d+(?:\.\d{1,2})?$/', $raw)) {
            return '';
        }

        $rate = (float)$raw;
        if ($rate <= 0) {
            return '';
        }

        return number_format($rate, 2, '.', '');
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
        $display = (array)($payload['display'] ?? []);

        $title = '请完成支付';
        $amountRaw = trim((string)($display['primary_amount'] ?? ''));
        if ($amountRaw === '') {
            $amountRaw = number_format((float)($order['truemoney'] ?? $order['money'] ?? 0), 2, '.', '');
        }

        $defaultScanTip = !empty($display['is_usdt'])
            ? '请按页面显示的 USDT 金额向钱包地址转账，到账后页面会自动刷新状态。'
            : ('请使用 ' . $this->paymentMethodLabelText((string)($order['type'] ?? '')) . ' 扫描二维码完成支付。');

        return PublicCashierThemeRenderer::render(ThemeCatalog::effectiveThemeId('pay'), [
            'site_name' => trim((string)($order['sitename'] ?? 'AiPay')) ?: 'AiPay',
            'title' => $title,
            'amount' => $amountRaw,
            'amount_prefix' => trim((string)($display['primary_prefix'] ?? '￥')),
            'amount_caption' => trim((string)($display['primary_caption'] ?? '订单金额')),
            'secondary_amount' => trim((string)($display['secondary_amount'] ?? '')),
            'secondary_hint' => trim((string)($display['secondary_hint'] ?? '')),
            'wallet_address' => trim((string)($display['wallet_address'] ?? '')),
            'pay_type' => $this->paymentMethodLabelText((string)($order['type'] ?? '')),
            'pay_type_raw' => $this->paymentMethodLabelText((string)($order['type'] ?? '')),
            'trade_no' => trim((string)($order['trade_no'] ?? '')),
            'trade_no_raw' => trim((string)($order['trade_no'] ?? '')),
            'out_trade_no' => trim((string)($order['out_trade_no'] ?? '')),
            'out_trade_no_raw' => trim((string)($order['out_trade_no'] ?? '')),
            'qr_url' => trim((string)($order['qr_url'] ?? '')),
            'launch_url' => trim((string)($display['launch_value'] ?? ($order['display_h5_qrurl'] ?? ''))),
            'launch_action' => trim((string)($display['launch_action'] ?? '')),
            'launch_text' => trim((string)($display['launch_text'] ?? '')),
            'timeout_url' => (string)($console['timeout_url'] ?? '/'),
            'timeout_url_raw' => (string)($console['timeout_url'] ?? '/'),
            'ok_url' => (string)($status['ok_url'] ?? ''),
            'stay_on_paid_page' => !empty($status['stay_on_paid_page']),
            'notice' => trim((string)($console['console_notice'] ?? '')),
            'notice_raw' => trim((string)($console['console_notice'] ?? '')),
            'state' => (string)($status['state'] ?? 'pending'),
            'state_label' => $this->stateLabelText((string)($status['state'] ?? 'pending')),
            'state_description' => $this->stateDescriptionText((string)($status['state'] ?? 'pending')),
            'countdown_label' => $this->formatCountdown((int)($console['timeout_seconds'] ?? 0)),
            'placeholder_text' => $this->placeholderTextValue((string)($status['state'] ?? 'pending')),
            'default_scan_tip' => $defaultScanTip,
            'default_scan_tip_raw' => $defaultScanTip,
            'countdown' => (int)($console['timeout_seconds'] ?? 0),
            'is_usdt' => !empty($display['is_usdt']),
            'poll_url' => '/api/public/cashier/poll',
            'auto_jump' => !empty($console['is_jump']),
        ]);
    }

    /**
     * @param array<string, mixed> $order
     */
    private function shouldStayOnPaidPage(array $order): bool
    {
        return PaymentResultPageTicket::isMerchantChannelTestOrder($order);
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private function paymentResultPageView(Request $request, array $order, string $scene): array
    {
        $display = $this->consoleDisplayMeta(
            $order,
            $this->displayH5QrUrl((string)($order['account_code'] ?? ''), (string)($order['h5_qrurl'] ?? ''))
        );
        $isUsdt = !empty($display['is_usdt']);
        $amount = trim((string)($display['primary_amount'] ?? ''));
        if ($amount === '') {
            $amount = number_format((float)($order['truemoney'] ?? $order['money'] ?? 0), 2, '.', '');
        }

        $amountPrefix = $isUsdt ? 'USDT' : '￥';
        $amountCaption = $isUsdt ? '到账金额' : '支付金额';
        $completedAt = trim((string)($order['end_time'] ?? ''));
        if ($completedAt === '') {
            $completedAt = trim((string)($order['create_time'] ?? ''));
        }
        if ($completedAt === '') {
            $completedAt = date('Y-m-d H:i:s');
        }

        $merchantHasReturnUrl = trim((string)($order['return_url'] ?? '')) !== '';
        $primaryButtonLabel = $merchantHasReturnUrl ? '返回商户页面' : '返回首页';
        $primaryButtonUrl = $merchantHasReturnUrl
            ? $this->merchantReturnUrl($order)
            : $this->resolveTimeoutUrl($order);
        $secondaryButtonLabel = '';
        $secondaryButtonUrl = '';
        $autoRedirectUrl = $merchantHasReturnUrl ? $primaryButtonUrl : '';
        $autoRedirectSeconds = $merchantHasReturnUrl ? 5 : 0;
        $autoRedirectLabel = '正在返回商户页面';
        $badge = '支付成功';
        $title = '支付成功';
        $subtitle = '订单已完成支付，系统已经开始处理回调与结果返回。';
        $notice = $merchantHasReturnUrl
            ? '如果商户配置了返回地址，页面将在几秒后自动跳转。'
            : '商户未配置返回地址时，你可以保留此页作为支付完成凭证。';

        if ($scene === PaymentResultPageTicket::SCENE_MERCHANT_CHANNEL_TEST) {
            $primaryButtonLabel = '返回商户通道';
            $primaryButtonUrl = $this->merchantChannelsUrl($request);
            $autoRedirectUrl = '';
            $autoRedirectSeconds = 0;
            $autoRedirectLabel = '正在返回商户通道';
            $badge = '通道测试';
            $title = '通道测试成功';
            $subtitle = '测试订单已完成支付，可以返回通道列表继续配置或再次发起测试。';
            $notice = '该结果页仅在测试订单已支付并通过签名凭证校验后才会展示。';
        }

        return [
            'site_name' => trim((string)($order['sitename'] ?? 'AiPay')) ?: 'AiPay',
            'badge' => $badge,
            'title' => $title,
            'subtitle' => $subtitle,
            'amount' => $amount,
            'amount_prefix' => $amountPrefix,
            'amount_caption' => $amountCaption,
            'secondary_amount' => trim((string)($display['secondary_amount'] ?? '')),
            'secondary_hint' => trim((string)($display['secondary_hint'] ?? '')),
            'pay_type' => $this->paymentMethodLabelText((string)($order['type'] ?? '')),
            'trade_no' => trim((string)($order['trade_no'] ?? '')),
            'out_trade_no' => trim((string)($order['out_trade_no'] ?? '')),
            'completed_at' => $completedAt,
            'primary_button_label' => $primaryButtonLabel,
            'primary_button_url' => $primaryButtonUrl,
            'secondary_button_label' => $secondaryButtonLabel,
            'secondary_button_url' => $secondaryButtonUrl,
            'auto_redirect_url' => $autoRedirectUrl,
            'auto_redirect_seconds' => $autoRedirectSeconds,
            'auto_redirect_label' => $autoRedirectLabel,
            'notice' => $notice,
        ];
    }

    /**
     * @param array<string, mixed> $order
     */
    private function paymentResultPageUrl(Request $request, array $order, string $scene = ''): string
    {
        $ticket = PaymentResultPageTicket::issue($order, $scene);
        if ($ticket === '') {
            return $this->cashierConsoleUrl($request, trim((string)($order['trade_no'] ?? '')));
        }

        return rtrim($this->requestOrigin($request), '/') . '/api/public/cashier/ok?ticket=' . rawurlencode($ticket);
    }

    /**
     * @param array<string, mixed> $ticket
     * @param array<string, mixed> $order
     */
    private function validatePaymentResultTicketOrder(array $ticket, array $order): bool
    {
        return trim((string)($ticket['trade_no'] ?? '')) === trim((string)($order['trade_no'] ?? ''))
            && trim((string)($ticket['out_trade_no'] ?? '')) === trim((string)($order['out_trade_no'] ?? ''))
            && (int)($ticket['merchant_id'] ?? 0) === (int)($order['user_id'] ?? 0)
            && PaymentResultPageTicket::normalizeScene((string)($ticket['scene'] ?? ''), $order)
                === PaymentResultPageTicket::normalizeScene('', $order);
    }

    private function merchantChannelsUrl(Request $request): string
    {
        return $this->withHashPath(FrontendUrlBuilder::merchantBaseUrl($request), '/merchant/channels');
    }

    private function withHashPath(string $baseUrl, string $path, array $query = []): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $path = '/' . ltrim($path, '/');
        $queryString = $query === [] ? '' : ('?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));

        if (str_contains($baseUrl, '#')) {
            return preg_replace('#/+$#', '', $baseUrl) . $path . $queryString;
        }

        return $baseUrl . '/#' . $path . $queryString;
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

    private function formatCountdown(int $seconds): string
    {
        if ($seconds <= 0) {
            return '00:00';
        }

        $minutes = intdiv($seconds, 60);
        $remainder = $seconds % 60;

        return sprintf('%02d:%02d', $minutes, $remainder);
    }

    private function stateLabelText(string $state): string
    {
        return match ($state) {
            'paid' => '支付成功',
            'timeout' => '订单超时',
            'reconciling' => '到账核对中',
            'qrcode_loading' => '二维码生成中',
            'qrcode_missing' => '等待二维码',
            default => '等待支付',
        };
    }

    private function stateDescriptionText(string $state): string
    {
        return match ($state) {
            'paid' => '订单已完成支付，系统会自动处理商户回调与页面跳转。',
            'timeout' => '当前订单已超时，请返回后重新发起支付。',
            'reconciling' => '支付时限已到，系统仍在继续核对到账结果。',
            'qrcode_loading' => '上游通道正在生成二维码，系统会自动轮询刷新。',
            'qrcode_missing' => '支付二维码暂未返回，请稍候等待系统继续刷新。',
            default => '请扫码完成支付，系统会自动更新当前订单状态。',
        };
    }

    private function placeholderTextValue(string $state): string
    {
        return match ($state) {
            'paid' => '支付已完成，订单状态正在同步。',
            'timeout' => '当前订单已超时。',
            'reconciling' => '系统正在核对到账结果，请勿重复支付。',
            'qrcode_loading' => '正在生成支付二维码，请稍候。',
            'qrcode_missing' => '支付二维码暂未就绪，请等待系统刷新。',
            default => '二维码加载中，请稍候。',
        };
    }

    private function paymentMethodLabelText(string $type): string
    {
        return match (strtolower(trim($type))) {
            'alipay' => '支付宝',
            'wxpay' => '微信支付',
            'qqpay' => 'QQ 支付',
            'usdt' => 'USDT',
            default => strtoupper(trim($type)) !== '' ? strtoupper(trim($type)) : '在线支付',
        };
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
