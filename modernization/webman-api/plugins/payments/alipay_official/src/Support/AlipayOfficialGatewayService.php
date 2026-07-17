<?php

declare(strict_types=1);

namespace Plugins\Payments\AlipayOfficial\Support;

use app\support\LegacyPaymentSdkAutoloader;
use Plugins\Payments\Shared\Managed\AbstractManagedGatewayOrderService;

final class AlipayOfficialGatewayService extends AbstractManagedGatewayOrderService
{
    protected function pluginCode(): string
    {
        return 'alipay_official';
    }

    protected function pluginName(): string
    {
        return '支付宝官方版V3插件';
    }

    protected function paymentType(): string
    {
        return 'alipay';
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
        $appId = trim((string)($account['wxname'] ?? ''));
        if ($appId === '') {
            throw new \InvalidArgumentException('支付宝官方版V3插件应用 ID 未配置');
        }

        $publicKey = trim((string)($account['cookie'] ?? ''));
        if ($publicKey === '') {
            throw new \InvalidArgumentException('支付宝官方版V3插件公钥未配置');
        }

        $privateKey = trim((string)($account['qr_url'] ?? ''));
        if ($privateKey === '') {
            throw new \InvalidArgumentException('支付宝官方版V3插件私钥未配置');
        }

        LegacyPaymentSdkAutoloader::register();
        $service = new \iboxs\payment\alipay\AlipayService();
        $service->setAppid($appId);
        $service->setNotifyUrl($this->resolveOrigin($payload) . '/Notify/alipay_official');
        $service->setRsaPrivateKey($privateKey);
        $service->setTotalFee(number_format((float)($order['truemoney'] ?? $order['money'] ?? 0), 2, '.', ''));
        $service->setOutTradeNo((string)($order['out_trade_no'] ?? ''));
        $service->setOrderName((string)($order['name'] ?? 'AiPay Order'));
        $service->setGatewayUrl('https://openapi.alipay.com/gateway.do');

        $rawResponse = (string)$this->captureBufferedResult(static fn () => $service->codePay());
        $result = $this->decodeGatewayResponse($rawResponse);
        $response = is_array($result['alipay_trade_precreate_response'] ?? null)
            ? $result['alipay_trade_precreate_response']
            : null;

        if ($response === null) {
            throw new \RuntimeException($this->buildRawGatewayMessage($rawResponse));
        }

        $qrCode = trim((string)($response['qr_code'] ?? $response['code_url'] ?? $response['payurl'] ?? ''));
        if ($qrCode === '') {
            $message = trim((string)($response['sub_msg'] ?? $response['msg'] ?? $response['code'] ?? ''));
            throw new \RuntimeException($message !== '' ? $message : '支付宝官方版V3插件未返回二维码');
        }

        $gatewayTradeNo = trim((string)($response['trade_no'] ?? $response['alipay_trade_no'] ?? ''));

        return [
            'qrcode' => $qrCode,
            'h5_qrurl' => $this->buildLaunchUrl($qrCode),
            'gateway_trade_no' => $gatewayTradeNo,
            'raw_response' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    private function captureBufferedResult(callable $callback): mixed
    {
        ob_start();
        try {
            $result = $callback();
            ob_end_clean();

            return $result;
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }

    private function buildLaunchUrl(string $qrCode): string
    {
        $normalized = trim($qrCode);
        if ($normalized === '') {
            return '';
        }

        if (preg_match('/^(alipayqr:\/\/|alipays:\/\/|alipay:\/\/)/i', $normalized) === 1) {
            return $normalized;
        }

        return 'alipayqr://platformapi/startapp?saId=10000007&qrcode=' . rawurlencode($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeGatewayResponse(string $rawResponse): array
    {
        $decoded = json_decode($rawResponse, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $utf8 = @iconv('GBK', 'UTF-8//IGNORE', $rawResponse);
        if (is_string($utf8) && $utf8 !== '') {
            $decoded = json_decode($utf8, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function buildRawGatewayMessage(string $rawResponse): string
    {
        $message = trim($rawResponse);
        if ($message === '') {
            return '支付宝官方版V3插件未返回有效响应';
        }

        $message = preg_replace('/\s+/', ' ', $message);
        if (!is_string($message) || $message === '') {
            return '支付宝官方版V3插件未返回有效响应';
        }

        return mb_substr($message, 0, 500);
    }
}
