<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace Plugins\Payments\Leshua\Support;

use InvalidArgumentException;
use RuntimeException;

final class LeshuaCore
{
    private const API_URL = 'https://paygate.leshuazf.com/cgi-bin/lepos_pay_gateway.cgi';

    public function __construct(
        private readonly string $merchantId,
        private readonly string $transactionKey,
        private readonly string $notifyKey = ''
    ) {
        if ($this->merchantId === '') {
            throw new InvalidArgumentException('乐刷支付插件商户号未配置');
        }

        if ($this->transactionKey === '') {
            throw new InvalidArgumentException('乐刷支付插件交易密钥未配置');
        }
    }

    /**
     * @param array<string, mixed> $account
     */
    public static function fromAccount(array $account): self
    {
        return new self(
            trim((string)($account['wxname'] ?? '')),
            trim((string)($account['cookie'] ?? '')),
            trim((string)($account['qr_url'] ?? ''))
        );
    }

    /**
     * @return array<string, string>
     */
    public static function parseXml(string $xml): array
    {
        $xml = trim($xml);
        if ($xml === '' || !str_starts_with($xml, '<')) {
            return [];
        }

        $previous = null;
        if (\LIBXML_VERSION < 20900) {
            $previous = libxml_disable_entity_loader(true);
        }

        $previousInternalErrors = libxml_use_internal_errors(true);
        $element = simplexml_load_string($xml, 'SimpleXMLElement', \LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previousInternalErrors);

        if ($previous !== null) {
            libxml_disable_entity_loader($previous);
        }

        if ($element === false) {
            return [];
        }

        $decoded = json_decode(json_encode($element, JSON_UNESCAPED_UNICODE), true);

        return self::normalizePayload(is_array($decoded) ? $decoded : []);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    public static function normalizePayload(array $payload): array
    {
        $normalized = [];
        foreach ($payload as $key => $value) {
            if (!is_string($key) || (!is_scalar($value) && $value !== null)) {
                continue;
            }

            $normalized[$key] = $value === null ? '' : trim((string)$value);
        }

        return $normalized;
    }

    public static function amountToFen(string $money): int
    {
        $money = trim($money);
        if (preg_match('/^\d{1,13}(?:\.(\d{1,2}))?$/D', $money, $matches) !== 1) {
            return 0;
        }

        [$whole] = explode('.', $money, 2);
        $fraction = str_pad((string)($matches[1] ?? ''), 2, '0');

        return ((int)$whole * 100) + (int)$fraction;
    }

    /**
     * @return array<string, string>
     */
    public function createGatewayOrder(
        string $paymentType,
        string $tradeNo,
        int $amountFen,
        string $body,
        string $notifyUrl,
        string $clientIp
    ): array {
        $definition = $this->paymentDefinition($paymentType);
        $result = $this->requestValidated([
            'service' => 'get_tdcode',
            'jspay_flag' => $definition['jspay_flag'],
            'pay_way' => $definition['pay_way'],
            'merchant_id' => $this->merchantId,
            'third_order_id' => $tradeNo,
            'amount' => (string)$amountFen,
            'body' => $body,
            'notify_url' => $notifyUrl,
            'client_ip' => $clientIp,
            'nonce_str' => $this->nonce(),
        ], '下单');

        $payUrl = '';
        foreach ([$definition['pay_url_field'], 'td_code', 'jspay_url', 'code_url', 'pay_url'] as $field) {
            $candidate = trim((string)($result[$field] ?? ''));
            if ($candidate !== '') {
                $payUrl = $candidate;
                break;
            }
        }

        if ($payUrl === '') {
            throw new RuntimeException('乐刷支付插件未返回有效的支付地址');
        }

        return array_merge($result, ['pay_url' => $payUrl]);
    }

    /**
     * @return array<string, string>
     */
    public function queryOrderByTradeNo(string $tradeNo): array
    {
        return $this->requestValidated([
            'service' => 'query_status',
            'merchant_id' => $this->merchantId,
            'third_order_id' => trim($tradeNo),
            'nonce_str' => $this->nonce(),
        ], '查单');
    }

    /**
     * @return array<string, string>
     */
    public function queryOrderByGatewayTradeNo(string $gatewayTradeNo): array
    {
        return $this->requestValidated([
            'service' => 'query_status',
            'merchant_id' => $this->merchantId,
            'leshua_order_id' => trim($gatewayTradeNo),
            'nonce_str' => $this->nonce(),
        ], '查单');
    }

    /**
     * @return array<string, string>
     */
    public function refund(string $gatewayTradeNo, string $refundNo, int $refundAmountFen): array
    {
        return $this->requestValidated([
            'service' => 'unified_refund',
            'merchant_id' => $this->merchantId,
            'leshua_order_id' => trim($gatewayTradeNo),
            'merchant_refund_id' => trim($refundNo),
            'refund_amount' => (string)$refundAmountFen,
            'nonce_str' => $this->nonce(),
        ], '退款');
    }

    /**
     * @param array<string, string> $payload
     */
    public function verifyNotifySignature(array $payload): bool
    {
        if ($this->notifyKey === '') {
            return false;
        }

        $received = trim((string)($payload['sign'] ?? ''));
        if ($received === '') {
            return false;
        }

        return strcasecmp($received, $this->makeSign($payload, $this->notifyKey)) === 0;
    }

    public function hasNotifyKey(): bool
    {
        return $this->notifyKey !== '';
    }

    /**
     * @param array<string, string> $payload
     */
    public function isPaid(array $payload): bool
    {
        return trim((string)($payload['status'] ?? '')) === '2';
    }

    /**
     * @return array{jspay_flag: string, pay_way: string, pay_url_field: string}
     */
    private function paymentDefinition(string $paymentType): array
    {
        return match (strtolower(trim($paymentType))) {
            'alipay' => [
                'jspay_flag' => '0',
                'pay_way' => 'ZFBZF',
                'pay_url_field' => 'td_code',
            ],
            'wxpay' => [
                'jspay_flag' => '2',
                'pay_way' => 'WXZF',
                'pay_url_field' => 'jspay_url',
            ],
            default => throw new InvalidArgumentException('乐刷支付插件仅支持支付宝和微信'),
        };
    }

    /**
     * @param array<string, scalar|null> $params
     * @return array<string, string>
     */
    private function requestValidated(array $params, string $action): array
    {
        $payload = $params;
        $payload['sign'] = $this->makeSign($payload, $this->transactionKey);
        $response = $this->request($payload);
        $result = self::parseXml($response);

        if ($result === []) {
            throw new RuntimeException('乐刷支付插件' . $action . '响应解析失败');
        }

        if (trim((string)($result['resp_code'] ?? '')) !== '0') {
            $message = trim((string)($result['resp_msg'] ?? ''));
            throw new RuntimeException($message !== '' ? $message : '乐刷支付插件' . $action . '失败');
        }

        if (array_key_exists('result_code', $result) && trim((string)$result['result_code']) !== '0') {
            $message = trim((string)($result['error_msg'] ?? ''));
            throw new RuntimeException($message !== '' ? $message : '乐刷支付插件' . $action . '失败');
        }

        return $result;
    }

    /**
     * @param array<string, scalar|null> $params
     */
    private function request(array $params, int $timeout = 15): string
    {
        $curl = curl_init(self::API_URL);
        if ($curl === false) {
            throw new RuntimeException('初始化乐刷支付插件请求失败');
        }

        curl_setopt($curl, \CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, \CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, \CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, \CURLOPT_HTTPHEADER, [
            'Accept: */*',
            'Connection: close',
        ]);
        curl_setopt($curl, \CURLOPT_HEADER, false);
        curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, \CURLOPT_POST, true);
        curl_setopt($curl, \CURLOPT_POSTFIELDS, http_build_query($params, '', '&', \PHP_QUERY_RFC3986));

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if (!is_string($response) || trim($response) === '') {
            throw new RuntimeException($error !== '' ? $error : '乐刷支付插件请求上游失败');
        }

        return $response;
    }

    /**
     * @param array<string, scalar|null> $params
     */
    private function makeSign(array $params, string $key): string
    {
        ksort($params, \SORT_STRING);
        $pairs = [];
        foreach ($params as $name => $value) {
            if ($name === 'sign' || $name === 'error_code') {
                continue;
            }

            if (is_array($value)) {
                $value = '';
            }

            $pairs[] = $name . '=' . (string)$value;
        }

        $pairs[] = 'key=' . $key;

        return strtoupper(md5(implode('&', $pairs)));
    }

    private function nonce(): string
    {
        return bin2hex(random_bytes(8));
    }
}
