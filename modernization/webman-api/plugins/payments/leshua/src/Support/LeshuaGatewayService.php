<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace Plugins\Payments\Leshua\Support;

use InvalidArgumentException;
use Plugins\Payments\Shared\Managed\AbstractManagedGatewayOrderService;

final class LeshuaGatewayService extends AbstractManagedGatewayOrderService
{
    public function __construct(private readonly string $resolvedPaymentType)
    {
        parent::__construct();
    }

    protected function pluginCode(): string
    {
        return 'leshua';
    }

    protected function pluginName(): string
    {
        return '乐刷支付插件';
    }

    protected function paymentType(): string
    {
        return $this->resolvedPaymentType;
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
        $amountFen = LeshuaCore::amountToFen((string)($order['truemoney'] ?? $order['money'] ?? '0'));
        if ($amountFen <= 0) {
            throw new InvalidArgumentException('乐刷支付插件订单金额无效');
        }

        $result = LeshuaCore::fromAccount($account)->createGatewayOrder(
            $this->paymentType(),
            trim((string)($order['trade_no'] ?? '')),
            $amountFen,
            trim((string)($order['name'] ?? 'AiPay Order')),
            $this->resolveOrigin($payload) . '/Notify/leshua',
            $this->safeClientIp($payload)
        );

        $payUrl = trim((string)($result['pay_url'] ?? ''));

        return [
            'qrcode' => $payUrl,
            'h5_qrurl' => $this->paymentType() === 'alipay' ? $this->buildAlipayLaunchUrl($payUrl) : $payUrl,
            'gateway_trade_no' => trim((string)($result['leshua_order_id'] ?? '')),
            'raw_response' => json_encode($result, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function safeClientIp(array $payload): string
    {
        $candidate = $this->resolveClientIp($payload);
        if (filter_var($candidate, \FILTER_VALIDATE_IP) !== false) {
            return $candidate;
        }

        return '127.0.0.1';
    }

    private function buildAlipayLaunchUrl(string $payUrl): string
    {
        $normalized = trim($payUrl);
        if ($normalized === '') {
            return '';
        }

        if (preg_match('/^(alipayqr:\/\/|alipays:\/\/|alipay:\/\/)/i', $normalized) === 1) {
            return $normalized;
        }

        return 'alipayqr://platformapi/startapp?saId=10000007&qrcode=' . rawurlencode($normalized);
    }
}
