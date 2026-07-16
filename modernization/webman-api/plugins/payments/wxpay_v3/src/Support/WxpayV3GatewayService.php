<?php

declare(strict_types=1);

namespace Plugins\Payments\WxpayV3\Support;

use app\payment\managed\AbstractManagedGatewayOrderService;
use app\support\SystemConfig;
use app\support\WxpayV3SdkAutoloader;

final class WxpayV3GatewayService extends AbstractManagedGatewayOrderService
{
    protected function pluginCode(): string
    {
        return 'wxpay_v3';
    }

    protected function pluginName(): string
    {
        return '微信支付 V3 插件';
    }

    protected function paymentType(): string
    {
        return 'wxpay';
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
        WxpayV3SdkAutoloader::register();
        $client = new \WeChatPay\V3\PaymentService($this->sdkConfig($account));
        $mode = $this->resolveMode($account, $payload);
        $orderName = (string)($order['name'] ?? 'AiPay Order');
        $totalCents = (int)round((float)($order['truemoney'] ?? $order['money'] ?? 0) * 100);

        if ($totalCents <= 0) {
            throw new \RuntimeException('微信支付 V3 订单金额无效');
        }

        $baseParams = [
            'description' => $orderName,
            'out_trade_no' => (string)($order['out_trade_no'] ?? ''),
            'time_expire' => date('c', min(time() + 7200, (int)($order['out_time'] ?? (time() + 7200)))),
            'notify_url' => $this->resolveOrigin($payload) . '/Notify/wxpay_v3',
            'amount' => [
                'total' => $totalCents,
                'currency' => 'CNY',
            ],
            'scene_info' => [
                'payer_client_ip' => $this->safeClientIp($payload),
            ],
        ];

        return match ($mode) {
            'h5' => $this->createH5Order($client, $baseParams, $payload),
            default => $this->createNativeOrder($client, $baseParams),
        };
    }

    /**
     * @param array<string, mixed> $account
     * @return array<string, string>
     */
    private function sdkConfig(array $account): array
    {
        $appId = trim((string)($account['wxname'] ?? ''));
        if ($appId === '') {
            throw new \InvalidArgumentException('微信支付 V3 插件应用 ID 未配置');
        }

        $mchId = trim((string)($account['zfb_pid'] ?? ''));
        if ($mchId === '') {
            throw new \InvalidArgumentException('微信支付 V3 插件商户号未配置');
        }

        $apiV3Key = trim((string)($account['remark'] ?? ''));
        if ($apiV3Key === '' || strlen($apiV3Key) !== 32) {
            throw new \InvalidArgumentException('微信支付 V3 插件 API V3 密钥未配置或长度不正确');
        }

        $serial = trim((string)($account['wx_guid'] ?? ''));
        if ($serial === '') {
            throw new \InvalidArgumentException('微信支付 V3 插件商户证书序列号未配置');
        }

        $merchantPrivateKey = trim((string)($account['qr_url'] ?? ''));
        if ($merchantPrivateKey === '') {
            throw new \InvalidArgumentException('微信支付 V3 插件商户私钥未配置');
        }

        $runtimeDir = WxpayV3SdkAutoloader::ensureRuntimeDirectory((int)($account['id'] ?? 0));
        $merchantPrivateKeyPath = $runtimeDir . DIRECTORY_SEPARATOR . 'merchant_private_key.pem';
        file_put_contents($merchantPrivateKeyPath, $this->normalizePem($merchantPrivateKey, 'PRIVATE KEY'));

        $platformCertificatePath = $runtimeDir . DIRECTORY_SEPARATOR . 'platform_certificate.pem';
        $config = [
            'appid' => $appId,
            'mchid' => $mchId,
            'apikey' => $apiV3Key,
            'merchantPrivateKeyFilePath' => $merchantPrivateKeyPath,
            'merchantCertificateSerial' => $serial,
            'platformCertificateFilePath' => $platformCertificatePath,
            'platformCertificateSerial' => trim((string)($account['cloud_id'] ?? '')),
        ];

        $platformPublicKey = trim((string)($account['cookie'] ?? ''));
        $platformPublicKeyId = trim((string)($account['cloud_id'] ?? ''));
        if ($platformPublicKey !== '' && $platformPublicKeyId !== '') {
            $platformPublicKeyPath = $runtimeDir . DIRECTORY_SEPARATOR . 'platform_public_key.pem';
            file_put_contents($platformPublicKeyPath, $this->normalizePem($platformPublicKey, 'PUBLIC KEY'));
            $config['platformPublicKeyFilePath'] = $platformPublicKeyPath;
        }

        return $config;
    }

    private function normalizePem(string $content, string $defaultLabel): string
    {
        $normalized = trim(str_replace(['\\r\\n', '\\n', "\r\n", "\r"], ["\n", "\n", "\n", "\n"], $content));
        if ($normalized !== '' && !str_contains($normalized, '-----BEGIN ')) {
            $body = preg_replace('/\s+/', '', $normalized);
            if (is_string($body) && $body !== '') {
                $normalized = "-----BEGIN {$defaultLabel}-----\n"
                    . chunk_split($body, 64, "\n")
                    . "-----END {$defaultLabel}-----";
            }
        }

        return $normalized . "\n";
    }

    /**
     * @param array<string, mixed> $account
     * @param array<string, mixed> $payload
     */
    private function resolveMode(array $account, array $payload): string
    {
        $requested = strtolower(trim((string)($payload['method'] ?? $payload['trade_type'] ?? '')));
        if (in_array($requested, ['h5', 'mweb'], true)) {
            return 'h5';
        }

        if (in_array($requested, ['native', 'scan', 'qrcode'], true)) {
            return 'native';
        }

        $available = array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            explode(',', (string)($account['qr_type'] ?? '1'))
        )));

        if (in_array('1', $available, true)) {
            return 'native';
        }

        if (in_array('3', $available, true)) {
            return 'h5';
        }

        return 'native';
    }

    /**
     * @param array<string, mixed> $baseParams
     * @return array<string, mixed>
     */
    private function createNativeOrder(\WeChatPay\V3\PaymentService $client, array $baseParams): array
    {
        $result = $client->nativePay($baseParams);
        $codeUrl = trim((string)($result['code_url'] ?? ''));
        if ($codeUrl === '') {
            throw new \RuntimeException('微信支付 V3 未返回 Native 支付地址');
        }

        return [
            'qrcode' => $codeUrl,
            'h5_qrurl' => $codeUrl,
            'raw_response' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'pay_scene' => 'native',
        ];
    }

    /**
     * @param array<string, mixed> $baseParams
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function createH5Order(\WeChatPay\V3\PaymentService $client, array $baseParams, array $payload): array
    {
        $baseParams['scene_info']['h5_info'] = [
            'type' => 'Wap',
            'app_name' => trim((string)SystemConfig::get('sitename', 'AiPay')),
            'app_url' => $this->resolveOrigin($payload),
        ];

        $result = $client->h5Pay($baseParams);
        $h5Url = trim((string)($result['h5_url'] ?? ''));
        if ($h5Url === '') {
            throw new \RuntimeException('微信支付 V3 未返回 H5 支付地址');
        }

        return [
            'qrcode' => $h5Url,
            'h5_qrurl' => $h5Url,
            'raw_response' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'pay_scene' => 'h5',
        ];
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
}
