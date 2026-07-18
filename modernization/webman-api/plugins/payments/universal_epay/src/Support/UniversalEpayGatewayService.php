<?php

declare(strict_types=1);

namespace Plugins\Payments\UniversalEpay\Support;

use app\support\SystemConfig;
use Plugins\Payments\Shared\Managed\AbstractManagedGatewayOrderService;
use Plugins\Payments\Shared\Support\PaymentPluginException;

final class UniversalEpayGatewayService extends AbstractManagedGatewayOrderService
{
    protected function pluginCode(): string
    {
        return 'universal_epay';
    }

    protected function pluginName(): string
    {
        return '通用易支付V1插件';
    }

    protected function paymentType(): string
    {
        return 'alipay';
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload): array
    {
        $cleanPayload = $this->sanitizePayload($payload);
        $entry = strtolower(trim((string)($cleanPayload['_entry'] ?? 'submit')));

        $this->validateRequiredFields($cleanPayload, [
            'pid',
            'out_trade_no',
            'type',
            'name',
            'money',
            'notify_url',
            'return_url',
        ]);

        $merchant = $this->merchants->findMerchant((int)$cleanPayload['pid']);
        if (!$this->legacy->verifySignature($cleanPayload, (string)($merchant['user_key'] ?? ''))) {
            throw PaymentPluginException::unauthorized();
        }

        $systemConfig = SystemConfig::all();
        $paymentType = $this->resolveRequestedPaymentType($cleanPayload);

        $this->assertVipActive($merchant);
        $this->assertMoney((string)$cleanPayload['money']);
        $this->assertOrderName((string)($cleanPayload['name'] ?? ''), $entry, $systemConfig);
        $this->assertMoneyRange((float)$cleanPayload['money'], $systemConfig);
        $this->assertMerchantBalance($merchant, (float)$cleanPayload['money'], $systemConfig);
        $this->orders->assertRequestCanCreate($cleanPayload);

        $basicSettings = $this->merchants->findBasicSettings((int)$merchant['id']);
        $basicSettings['system_timeout'] = SystemConfig::int('timeout', 180);
        $cleanPayload['_trade_no'] = $this->resolveTradeNo($systemConfig);
        $cleanPayload['_resolved_payment_type'] = $paymentType;

        $account = $this->resolveAccount($merchant, $cleanPayload, $paymentType);
        $order = $this->createLocalOrder($merchant, $account, $cleanPayload, $paymentType, $basicSettings);
        $gatewayResult = $this->createGatewayOrder($merchant, $account, $cleanPayload, $order);
        $order = $this->applyGatewayResult($order, $gatewayResult);
        $cashierUrl = $this->cashierUrl($cleanPayload, (string)($order['trade_no'] ?? ''));

        return [
            'plugin' => $this->pluginCode(),
            'entry' => $entry,
            'status' => 'created',
            'merchant' => $this->publicMerchant($merchant),
            'account' => $this->publicAccount($account),
            'order' => $this->publicOrder($order),
            'cashier_url' => $cashierUrl,
            'gateway_result' => $this->publicGatewayResult($gatewayResult),
            'form_html' => $this->buildRedirectHtml($cashierUrl),
            'legacy_api_response' => $this->buildLegacyApiResponse(
                $entry,
                $order,
                $cashierUrl,
                $gatewayResult
            ),
        ];
    }

    /**
     * @param array<string, mixed> $merchant
     * @param array<string, mixed> $account
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    protected function createGatewayOrder(array $merchant, array $account, array $payload, array $order): array
    {
        $paymentType = $this->resolveRequestedPaymentType($payload, (string)($order['type'] ?? $account['type'] ?? ''));
        $config = $this->gatewayConfig($account);
        $core = new UniversalEpayCore($config);
        $notifyUrl = $this->resolveOrigin($payload) . '/Notify/universal_epay';
        $returnUrl = $this->resolveOrigin($payload) . '/Notify/universal_epay_return';
        $money = number_format((float)($order['truemoney'] ?? $order['money'] ?? 0), 2, '.', '');
        $params = [
            'pid' => $config['pid'],
            'type' => $paymentType,
            'notify_url' => $notifyUrl,
            'return_url' => $returnUrl,
            'out_trade_no' => (string)($order['out_trade_no'] ?? ''),
            'name' => (string)($order['name'] ?? 'AiPay Order'),
            'money' => $money,
        ];

        if ($this->usesMApi($account)) {
            $params['device'] = $this->resolveDevice($payload);
            $params['clientip'] = $this->safeClientIp($payload);
            return $this->normalizeGatewayResponse($core->apiPay($params), $paymentType);
        }

        $payLink = $core->getPayLink($params);

        return [
            'qrcode' => $payLink,
            'h5_qrurl' => $paymentType === 'alipay' ? $this->buildAlipayLaunchUrl($payLink) : $payLink,
            'raw_response' => json_encode([
                'payurl' => $payLink,
                'mode' => 'submit',
                'payment_type' => $paymentType,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'pay_scene' => 'submit',
        ];
    }

    /**
     * @param array<string, mixed> $merchant
     * @param array<string, mixed> $account
     * @param array<string, mixed> $payload
     */
    protected function buildApiMemo(array $merchant, array $account, array $payload, int $timeoutSeconds): string
    {
        $selectedVia = trim((string)($account['_selected_via'] ?? 'latest_active_account'));
        $data = [
            'migration' => 'webman_managed_gateway',
            'plugin_code' => $this->pluginCode(),
            'payment_type' => $this->resolveRequestedPaymentType($payload, (string)($account['type'] ?? '')),
            'merchant_id' => (int)($merchant['id'] ?? 0),
            'merchant_username' => (string)($merchant['username'] ?? ''),
            'account_id' => (int)($account['id'] ?? 0),
            'account_code' => (string)($account['code'] ?? ''),
            'selected_via' => $selectedVia,
            'pool_id' => (int)($account['_pool_id'] ?? 0),
            'source' => (string)($payload['_entry'] ?? 'submit'),
            'timeout_seconds' => $timeoutSeconds,
            'created_at' => date('c'),
        ];

        foreach (['clientip', 'device', 'param', 'sign_type'] as $field) {
            if (isset($payload[$field]) && trim((string)$payload[$field]) !== '') {
                $data[$field] = (string)$payload[$field];
            }
        }

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveRequestedPaymentType(array $payload, string $fallback = ''): string
    {
        $value = (string)($payload['_resolved_payment_type'] ?? $payload['type'] ?? $fallback);
        $normalized = $this->normalizePaymentType($value);
        if (!in_array($normalized, ['alipay', 'wxpay', 'qqpay'], true)) {
            throw PaymentPluginException::validation('通用易支付V1插件仅支持支付宝、微信和QQ订单');
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $account
     * @return array{apiurl:string,pid:string,key:string}
     */
    private function gatewayConfig(array $account): array
    {
        $merchantId = trim((string)($account['wxname'] ?? ''));
        if ($merchantId === '') {
            throw new \InvalidArgumentException('通用易支付V1插件商户ID未配置');
        }

        $gatewayUrl = trim((string)($account['qr_url'] ?? ''));
        if ($gatewayUrl === '' || !preg_match('/^https?:\/\/.+/i', $gatewayUrl)) {
            throw new \InvalidArgumentException('通用易支付V1插件接口地址未配置或格式不正确');
        }

        $merchantKey = trim((string)($account['cookie'] ?? ''));
        if ($merchantKey === '') {
            throw new \InvalidArgumentException('通用易支付V1插件商户密钥未配置');
        }

        return [
            'apiurl' => $gatewayUrl,
            'pid' => $merchantId,
            'key' => $merchantKey,
        ];
    }

    /**
     * @param array<string, mixed> $account
     */
    private function usesMApi(array $account): bool
    {
        $mode = trim((string)($account['qr_type'] ?? '0'));
        return in_array($mode, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveDevice(array $payload): string
    {
        $requested = strtolower(trim((string)($payload['device'] ?? '')));
        if (in_array($requested, ['wechat', 'qq', 'alipay', 'mobile', 'pc', 'douyin'], true)) {
            return $requested;
        }

        return 'pc';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function safeClientIp(array $payload): string
    {
        $candidate = $this->resolveClientIp($payload);
        if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
            return $candidate;
        }

        return '127.0.0.1';
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function normalizeGatewayResponse(array $result, string $paymentType): array
    {
        $success = (int)($result['code'] ?? 0) === 1
            || trim((string)($result['payurl'] ?? '')) !== ''
            || trim((string)($result['qrcode'] ?? '')) !== ''
            || trim((string)($result['urlscheme'] ?? '')) !== '';
        if (!$success) {
            $message = trim((string)($result['msg'] ?? $result['message'] ?? ''));
            throw new \RuntimeException($message !== '' ? $message : '通用易支付V1插件未返回可用支付地址');
        }

        $payUrl = trim((string)($result['payurl'] ?? ''));
        $qrCode = trim((string)($result['qrcode'] ?? ''));
        $urlScheme = trim((string)($result['urlscheme'] ?? ''));

        $qrcode = $qrCode !== '' ? $qrCode : ($payUrl !== '' ? $payUrl : $urlScheme);
        $h5 = $payUrl !== '' ? $payUrl : ($urlScheme !== '' ? $urlScheme : $qrcode);

        if ($paymentType === 'alipay' && $qrcode !== '' && $h5 === $qrcode) {
            $h5 = $this->buildAlipayLaunchUrl($qrcode);
        }

        return [
            'qrcode' => $qrcode,
            'h5_qrurl' => $h5,
            'gateway_trade_no' => trim((string)($result['trade_no'] ?? '')),
            'raw_response' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'pay_scene' => $urlScheme !== '' ? 'scheme' : ($payUrl !== '' ? 'payurl' : 'qrcode'),
        ];
    }

    private function buildAlipayLaunchUrl(string $qrcode): string
    {
        $normalized = trim($qrcode);
        if ($normalized === '') {
            return '';
        }

        if (preg_match('/^(alipayqr:\/\/|alipays:\/\/|alipay:\/\/)/i', $normalized) === 1) {
            return $normalized;
        }

        return 'alipayqr://platformapi/startapp?saId=10000007&qrcode=' . rawurlencode($normalized);
    }
}
