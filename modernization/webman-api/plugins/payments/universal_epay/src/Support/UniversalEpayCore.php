<?php

declare(strict_types=1);

namespace Plugins\Payments\UniversalEpay\Support;

use RuntimeException;

final class UniversalEpayCore
{
    private string $pid;
    private string $key;
    private string $submitUrl;
    private string $mapiUrl;
    private string $apiUrl;
    private string $signType = 'MD5';

    /**
     * @param array{apiurl:string,pid:string,key:string} $config
     */
    public function __construct(array $config)
    {
        $this->pid = trim((string)($config['pid'] ?? ''));
        $this->key = trim((string)($config['key'] ?? ''));
        $baseUrl = rtrim(trim((string)($config['apiurl'] ?? '')), '/') . '/';

        if ($this->pid === '' || $this->key === '' || !preg_match('/^https?:\/\/.+/i', $baseUrl)) {
            throw new RuntimeException('通用易支付V1插件上游配置无效');
        }

        $this->submitUrl = $baseUrl . 'submit.php';
        $this->mapiUrl = $baseUrl . 'mapi.php';
        $this->apiUrl = $baseUrl . 'api.php';
    }

    public function pagePay(array $params, string $button = '正在跳转'): string
    {
        $payload = $this->buildSignedParams($params);

        $html = '<form id="universal-epay-submit" action="' . htmlspecialchars($this->submitUrl, ENT_QUOTES, 'UTF-8') . '" method="post">';
        foreach ($payload as $name => $value) {
            $html .= '<input type="hidden" name="' . htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8')
                . '" value="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"/>';
        }
        $html .= '<input type="submit" value="' . htmlspecialchars($button, ENT_QUOTES, 'UTF-8')
            . '"></form><script>document.getElementById("universal-epay-submit").submit();</script>';

        return $html;
    }

    public function getPayLink(array $params): string
    {
        return $this->submitUrl . '?' . http_build_query($this->buildSignedParams($params), '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array<string, mixed>
     */
    public function apiPay(array $params): array
    {
        $response = $this->request($this->mapiUrl, $this->buildSignedParams($params), true);
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('通用易支付V1插件下单响应解析失败');
        }

        return $decoded;
    }

    public function verify(array $params): bool
    {
        if ($params === []) {
            return false;
        }

        $receivedSign = trim((string)($params['sign'] ?? ''));
        if ($receivedSign === '') {
            return false;
        }

        return strcasecmp($receivedSign, $this->makeSign($params)) === 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function queryOrder(string $tradeNo): array
    {
        $tradeNo = trim($tradeNo);
        if ($tradeNo === '') {
            throw new RuntimeException('缺少上游交易号');
        }

        return $this->apiGet([
            'act' => 'order',
            'pid' => $this->pid,
            'key' => $this->key,
            'trade_no' => $tradeNo,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function queryOrderByOutTradeNo(string $outTradeNo): array
    {
        $outTradeNo = trim($outTradeNo);
        if ($outTradeNo === '') {
            throw new RuntimeException('缺少商户订单号');
        }

        return $this->apiGet([
            'act' => 'order',
            'pid' => $this->pid,
            'key' => $this->key,
            'out_trade_no' => $outTradeNo,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function refund(string $refundNo, string $tradeNo, string $money): array
    {
        $refundNo = trim($refundNo);
        $tradeNo = trim($tradeNo);
        $money = trim($money);
        if ($refundNo === '' || $tradeNo === '' || $money === '') {
            throw new RuntimeException('退款参数不完整');
        }

        $response = $this->request($this->apiUrl, [
            'act' => 'refund',
            'pid' => $this->pid,
            'key' => $this->key,
            'refund_no' => $refundNo,
            'trade_no' => $tradeNo,
            'money' => $money,
        ], true);
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('通用易支付V1插件退款响应解析失败');
        }

        return $decoded;
    }

    /**
     * @param array<string, scalar|null> $params
     * @return array<string, scalar|null>
     */
    private function buildSignedParams(array $params): array
    {
        $payload = $params;
        $payload['sign'] = $this->makeSign($payload);
        $payload['sign_type'] = $this->signType;

        return $payload;
    }

    /**
     * @param array<string, scalar|null> $params
     */
    private function makeSign(array $params): string
    {
        ksort($params);
        $pairs = [];
        foreach ($params as $name => $value) {
            if ($name === 'sign' || $name === 'sign_type' || $value === '' || $value === null) {
                continue;
            }

            $pairs[] = $name . '=' . (string)$value;
        }

        return md5(implode('&', $pairs) . $this->key);
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>
     */
    private function apiGet(array $query): array
    {
        $response = $this->request(
            $this->apiUrl . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986),
            [],
            false
        );
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('通用易支付V1插件查单响应解析失败');
        }

        return $decoded;
    }

    /**
     * @param array<string, scalar|null> $payload
     */
    private function request(string $url, array $payload = [], bool $post = false, int $timeout = 15): string
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('初始化通用易支付V1插件请求失败');
        }

        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Accept: */*',
            'Accept-Language: zh-CN,zh;q=0.9',
            'Connection: close',
        ]);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        if ($post) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($payload, '', '&', PHP_QUERY_RFC3986));
        }

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if (!is_string($response) || $response === '') {
            throw new RuntimeException($error !== '' ? $error : '通用易支付V1插件请求上游失败');
        }

        return $response;
    }
}
