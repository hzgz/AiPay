<?php

declare(strict_types=1);

namespace Plugins\Payments\Shared\EpayProtocol;

use app\service\order\OrderCallbackBuilder;
use app\service\order\OrderCallbackTaskService;
use app\support\SystemConfig;
use Plugins\Payments\Shared\Support\PaymentErrorMessageCatalog;
use Plugins\Payments\Shared\Support\PaymentPluginException;

class EpayProtocolService
{
    public function __construct(
        private readonly EpayMerchantRepository $merchants = new EpayMerchantRepository(),
        private readonly EpayOrderRepository $orders = new EpayOrderRepository(),
        private readonly OrderCallbackBuilder $callbackBuilder = new OrderCallbackBuilder(),
        private readonly OrderCallbackTaskService $callbackTasks = new OrderCallbackTaskService()
    ) {
    }

    public function createOrder(array $payload): array
    {
        $cleanPayload = $this->sanitizePayload($payload);
        $entry = strtolower(trim((string)($cleanPayload['_entry'] ?? 'submit')));
        $this->validateRequiredFields($cleanPayload, ['pid', 'out_trade_no', 'type', 'name', 'money', 'notify_url', 'return_url']);
        $merchantBundle = $this->merchants->findMerchantBundle((int)$cleanPayload['pid']);
        $merchant = $merchantBundle['merchant'];
        $systemConfig = SystemConfig::all();

        if (!$this->verifySignature($cleanPayload, (string)($merchant['user_key'] ?? ''))) {
            throw PaymentPluginException::unauthorized();
        }

        $this->assertVipActive($merchant);
        $this->assertMoney((string)$cleanPayload['money']);
        $this->assertOrderName((string)($cleanPayload['name'] ?? ''), $entry, $systemConfig);
        $this->assertMoneyRange((float)$cleanPayload['money'], $systemConfig);
        $this->assertMerchantBalance($merchant, (float)$cleanPayload['money'], $systemConfig);
        $this->orders->assertRequestCanCreate($cleanPayload);
        $paylist = $this->merchants->latestPaylist(
            (int)$merchant['id'],
            'epay',
            (string)($cleanPayload['type'] ?? ''),
            'universal_epay'
        );
        if (!$paylist) {
            throw PaymentPluginException::conflict(PaymentErrorMessageCatalog::universalEpayChannelUnavailable());
        }

        $basicSettings = $merchantBundle['basic'];
        $basicSettings['system_timeout'] = SystemConfig::int('timeout', 180);
        $cleanPayload['_trade_no'] = $this->resolveTradeNo($systemConfig);
        $localNotifyUrls = $this->buildLocalNotifyUrls($cleanPayload);
        $upstreamForm = $this->buildUpstreamForm($paylist, $cleanPayload, $localNotifyUrls);
        $order = $this->orders->create(
            $merchant,
            $cleanPayload,
            $paylist,
            $basicSettings,
            (string)($cleanPayload['_client_ip'] ?? '')
        );

        return [
            'plugin' => 'universal_epay',
            'entry' => $entry,
            'status' => 'created',
            'merchant' => [
                'id' => (int)$merchant['id'],
                'username' => (string)$merchant['username'],
                'money' => (string)$merchant['money'],
                'vip_id' => $merchant['vip_id'],
                'vip_time' => $merchant['vip_time'],
            ],
            'paylist' => [
                'id' => (int)$paylist['id'],
                'type' => (string)$paylist['type'],
                'status' => (int)$paylist['status'],
                'url' => (string)$paylist['url'],
            ],
            'order' => $this->publicOrder($order),
            'notify_urls' => $localNotifyUrls,
            'gateway' => [
                'action' => $upstreamForm['action'],
                'method' => 'post',
                'fields' => $upstreamForm['fields'],
            ],
            'form_html' => $this->buildAutoSubmitForm($upstreamForm['action'], $upstreamForm['fields']),
            'legacy_api_response' => $this->buildLegacyApiResponse($entry, $order, $upstreamForm),
            'response_mode' => $entry === 'submit' ? 'html_form' : 'legacy_api_json',
            'migration' => [
                'persisted' => true,
                'reason' => PaymentErrorMessageCatalog::orderCreatePersistedReason(),
            ],
        ];
    }

    public function handleNotify(array $context): array
    {
        $mode = trim((string)($context['mode'] ?? 'notify'));
        $payload = $this->sanitizePayload((array)($context['payload'] ?? []));

        $outTradeNo = trim((string)($payload['out_trade_no'] ?? ''));
        if ($outTradeNo === '') {
            throw PaymentPluginException::validation(PaymentErrorMessageCatalog::callbackOrderNoRequired());
        }

        $order = $this->orders->findByOutTradeNo($outTradeNo);
        if (!$order) {
            throw PaymentPluginException::notFound(PaymentErrorMessageCatalog::orderNotFound());
        }

        $merchant = $this->merchants->findMerchant((int)$order['user_id']);
        $paylist = $this->resolvePaylistForOrder($order);
        if (!$this->verifySignature($payload, (string)($paylist['key'] ?? ''))) {
            throw PaymentPluginException::unauthorized();
        }

        $isPaid = strtoupper(trim((string)($payload['trade_status'] ?? ''))) === 'TRADE_SUCCESS';
        $settlement = [
            'order' => $order,
            'already_paid' => (int)($order['status'] ?? 0) === 1,
            'settlement_executed' => false,
        ];

        if ($isPaid) {
            $settlement = $this->orders->settlePaidOrder($order, $merchant, $payload);
        }

        $callbackUrls = $isPaid
            ? $this->callbackBuilder->buildUrls($settlement['order'], $merchant)
            : ['notify' => '', 'return' => '', 'payload' => []];
        $merchantNotify = null;

        if ($mode === 'notify' && $isPaid) {
            $merchantNotify = $this->callbackTasks->enqueueForSettledOrder($settlement['order'], $merchant, [
                'scene' => 'notify',
            ]);
        }

        return [
            'plugin' => 'universal_epay',
            'mode' => $mode,
            'verified' => true,
            'paid' => $isPaid,
            'notify_response' => $isPaid ? 'success' : 'fail',
            'return_response' => $isPaid ? 'success' : 'fail',
            'return_redirect' => $isPaid ? $callbackUrls['return'] : null,
            'merchant_notify' => $merchantNotify,
            'callback_urls' => $callbackUrls,
            'order' => $this->publicOrder($settlement['order']),
            'settlement' => [
                'already_paid' => (bool)$settlement['already_paid'],
                'settlement_executed' => (bool)$settlement['settlement_executed'],
            ],
            'migration' => [
                'persisted' => true,
                'reason' => PaymentErrorMessageCatalog::notifyPersistedReason(),
            ],
        ];
    }

    public function verifySignature(array $payload, string $key): bool
    {
        $sign = (string)($payload['sign'] ?? '');
        if ($sign === '' || $key === '') {
            return false;
        }

        return strcasecmp($this->makeSign($payload, $key), $sign) === 0;
    }

    public function makeSign(array $payload, string $key): string
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

    private function validateRequiredFields(array $payload, array $fields): void
    {
        foreach ($fields as $field) {
            $value = trim((string)($payload[$field] ?? ''));
            if ($value === '') {
                throw PaymentPluginException::validation(PaymentErrorMessageCatalog::requiredField($field));
            }
        }
    }

    private function assertMoney(string $value): void
    {
        if (!is_numeric($value)) {
            throw PaymentPluginException::validation(PaymentErrorMessageCatalog::amountNotNumeric());
        }

        if ((float)$value <= 0) {
            throw PaymentPluginException::validation(PaymentErrorMessageCatalog::amountMustBePositive());
        }
    }

    private function assertMoneyRange(float $money, array $systemConfig): void
    {
        $min = isset($systemConfig['min_orderprice']) && is_numeric($systemConfig['min_orderprice'])
            ? (float)$systemConfig['min_orderprice']
            : 0;
        $max = isset($systemConfig['max_orderprice']) && is_numeric($systemConfig['max_orderprice'])
            ? (float)$systemConfig['max_orderprice']
            : 0;

        if ($money < $min) {
            throw PaymentPluginException::validation(PaymentErrorMessageCatalog::amountBelowMin());
        }

        if ($max > 0 && $money > $max) {
            throw PaymentPluginException::validation(PaymentErrorMessageCatalog::amountAboveMax());
        }
    }

    private function assertOrderName(string $name, string $entry, array $systemConfig): void
    {
        if ($entry === 'submit' && str_contains($name, '=')) {
            throw PaymentPluginException::validation(PaymentErrorMessageCatalog::orderNameInvalid());
        }

        $shieldKey = trim((string)($systemConfig['shield_key'] ?? ''));
        if ($shieldKey === '') {
            return;
        }

        $keywords = explode('|', $shieldKey);
        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if ($keyword === '') {
                continue;
            }

            if (str_contains($name, $keyword)) {
                $message = trim((string)($systemConfig['shield_tips'] ?? ''));
                throw PaymentPluginException::validation(
                    $message !== '' ? $message : PaymentErrorMessageCatalog::orderNameRiskBlocked()
                );
            }
        }
    }

    private function assertMerchantBalance(array $merchant, float $money, array $systemConfig): void
    {
        $fee = round($money * (float)($merchant['feilv'] ?? 0) / 100, 3);
        $balance = round((float)($merchant['money'] ?? 0), 3);
        $allowZeroBalance = (string)($systemConfig['is_pay_money'] ?? '1') === '1';

        if (!$allowZeroBalance && $balance <= 0) {
            throw PaymentPluginException::conflict(PaymentErrorMessageCatalog::merchantBalanceInsufficient());
        }

        if ($balance < $fee) {
            throw PaymentPluginException::conflict(PaymentErrorMessageCatalog::merchantBalanceInsufficient());
        }
    }

    private function assertVipActive(array $merchant): void
    {
        $vipTime = trim((string)($merchant['vip_time'] ?? ''));
        if ($vipTime === '') {
            throw PaymentPluginException::conflict(PaymentErrorMessageCatalog::merchantPackageMissing());
        }

        $timestamp = strtotime($vipTime);
        if ($timestamp === false || $timestamp < time()) {
            throw PaymentPluginException::conflict(PaymentErrorMessageCatalog::merchantPackageExpired());
        }
    }

    private function sanitizePayload(array $payload): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $clean[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        return $clean;
    }

    private function resolveTradeNo(array $systemConfig): string
    {
        $prefix = (string)($systemConfig['isDiy_orderNo'] ?? '0') === '1'
            ? trim((string)($systemConfig['diy_orderNo'] ?? ''))
            : 'Y';
        if ($prefix === '') {
            $prefix = 'Y';
        }

        return $prefix . date('YmdHis') . random_int(11111, 99999);
    }

    private function buildLocalNotifyUrls(array $payload): array
    {
        $origin = $this->resolveOrigin($payload);

        return [
            'notify' => $origin . '/Notify/epay_notifyzj',
            'return' => $origin . '/Notify/epay_returnzj',
        ];
    }

    private function buildUpstreamForm(array $paylist, array $payload, array $localNotifyUrls): array
    {
        $fields = [
            'pid' => (string)($paylist['pid'] ?? ''),
            'type' => (string)($payload['type'] ?? ''),
            'out_trade_no' => (string)($payload['out_trade_no'] ?? ''),
            'notify_url' => $localNotifyUrls['notify'],
            'return_url' => $localNotifyUrls['return'],
            'name' => (string)($payload['name'] ?? ''),
            'money' => number_format((float)($payload['money'] ?? 0), 2, '.', ''),
        ];
        $fields['sign'] = $this->makeSign($fields, (string)($paylist['key'] ?? ''));
        $fields['sign_type'] = 'MD5';

        return [
            'action' => $this->normalizeGatewayUrl((string)($paylist['url'] ?? '')),
            'fields' => $fields,
        ];
    }

    private function normalizeGatewayUrl(string $gatewayUrl): string
    {
        $gatewayUrl = trim($gatewayUrl);
        if ($gatewayUrl === '') {
            throw PaymentPluginException::conflict(PaymentErrorMessageCatalog::upstreamGatewayUrlMissing());
        }

        if (str_ends_with($gatewayUrl, 'submit.php')) {
            return $gatewayUrl;
        }

        return rtrim($gatewayUrl, '/') . '/submit.php';
    }

    private function resolveOrigin(array $payload): string
    {
        $origin = trim((string)($payload['_origin'] ?? ''));
        if ($origin !== '') {
            return rtrim($origin, '/');
        }

        $scheme = trim((string)($payload['_request_scheme'] ?? 'http'));
        $host = trim((string)($payload['_request_host'] ?? '127.0.0.1'));
        if ($host === '') {
            $host = '127.0.0.1';
        }

        return rtrim($scheme . '://' . $host, '/');
    }

    private function buildAutoSubmitForm(string $action, array $fields): string
    {
        $html = '<form id="legacy-epay-submit" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '" method="post">';
        foreach ($fields as $name => $value) {
            $html .= '<input type="hidden" name="' . htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"/>';
        }
        $html .= '<input type="submit" value="正在跳转"></form><script>document.getElementById("legacy-epay-submit").submit();</script>';

        return $html;
    }

    private function buildLegacyApiResponse(string $entry, array $order, array $upstreamForm): array
    {
        if ($entry === 'mapi') {
            return [
                'code' => 1,
                'msg' => '获取成功!',
                'trade_no' => (string)$order['trade_no'],
                'qrcode' => '',
                'payurl' => $upstreamForm['action'],
                'submit_method' => 'post',
                'submit_fields' => $upstreamForm['fields'],
            ];
        }

        return [
            'code' => 200,
            'msg' => '获取成功!',
            'trade_no' => (string)$order['trade_no'],
            'payurl' => $upstreamForm['action'],
            'type' => (string)$order['type'],
            'out_trade_no' => (string)$order['out_trade_no'],
            'money' => (string)$order['truemoney'],
            'code_url' => '',
            'submit_method' => 'post',
            'submit_fields' => $upstreamForm['fields'],
        ];
    }

    private function resolvePaylistForOrder(array $order): array
    {
        $paylist = $this->merchants->findPaylistById((int)($order['account_id'] ?? 0));
        if (!$paylist) {
            $paylist = $this->merchants->latestPaylist((int)($order['user_id'] ?? 0), 'epay');
        }

        if (!$paylist || trim((string)($paylist['key'] ?? '')) === '') {
            throw PaymentPluginException::conflict(PaymentErrorMessageCatalog::upstreamChannelUnavailable());
        }

        return $paylist;
    }

    private function publicOrder(array $order): array
    {
        return [
            'id' => (int)($order['id'] ?? 0),
            'trade_no' => (string)($order['trade_no'] ?? ''),
            'out_trade_no' => (string)($order['out_trade_no'] ?? ''),
            'type' => (string)($order['type'] ?? ''),
            'status' => (int)($order['status'] ?? 0),
            'user_id' => (int)($order['user_id'] ?? 0),
            'account_id' => (int)($order['account_id'] ?? 0),
            'money' => (string)($order['money'] ?? '0.00'),
            'truemoney' => (string)($order['truemoney'] ?? '0.00'),
            'notify_url' => (string)($order['notify_url'] ?? ''),
            'return_url' => (string)($order['return_url'] ?? ''),
            'create_time' => (string)($order['create_time'] ?? ''),
            'end_time' => (string)($order['end_time'] ?? ''),
        ];
    }
}
