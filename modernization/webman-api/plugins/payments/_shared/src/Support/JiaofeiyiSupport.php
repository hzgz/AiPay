<?php

declare(strict_types=1);

namespace Plugins\Payments\Shared\Support;

use app\support\SystemConfig;
use RuntimeException;
use Throwable;

final class JiaofeiyiSupport
{
    public const PAY_API_URL = 'https://jfyconsole.lakala.com/order/api/cashier/pay';
    public const QUERY_API_URL = 'https://payment.lakala.com/m/ccss/counter/order/query';
    public const DEFAULT_CHANNEL_ID = '95';

    private const CONNECT_TIMEOUT = 15;
    private const REQUEST_TIMEOUT = 35;
    private const PROXY_RETRY_TIMES = 3;
    private const PROXY_RETRY_DELAY_US = 300000;
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';

    /**
     * @return array{store_name: string, remote_api_url: string, proxy_api_url: string}
     */
    public function decodeConfig(array $record): array
    {
        $raw = trim((string)($record['cookie'] ?? ''));
        if ($raw === '') {
            return [
                'store_name' => '',
                'remote_api_url' => '',
                'proxy_api_url' => '',
            ];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [
                'store_name' => $raw,
                'remote_api_url' => '',
                'proxy_api_url' => '',
            ];
        }

        return [
            'store_name' => trim((string)($decoded['store_name'] ?? '')),
            'remote_api_url' => trim((string)($decoded['remote_api_url'] ?? $decoded['remote_api'] ?? '')),
            'proxy_api_url' => trim((string)($decoded['proxy_api_url'] ?? $decoded['proxy_api'] ?? '')),
        ];
    }

    public function encodeConfig(string $storeName, string $remoteApiUrl, string $proxyApiUrl = ''): string
    {
        $storeName = trim($storeName);
        $remoteApiUrl = trim($remoteApiUrl);
        $proxyApiUrl = trim($proxyApiUrl);

        if ($storeName === '' && $remoteApiUrl === '' && $proxyApiUrl === '') {
            return '';
        }

        $payload = [
            'store_name' => $storeName,
            'remote_api_url' => $remoteApiUrl,
        ];

        if ($proxyApiUrl !== '') {
            $payload['proxy_api_url'] = $proxyApiUrl;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    public function normalizePayMode(mixed $value): string
    {
        if (is_object($value)) {
            throw new \InvalidArgumentException('缴费易支付模式格式无效');
        }

        if (is_array($value)) {
            $value = reset($value);
        }

        $normalized = trim((string)$value);

        return in_array($normalized, ['1', '2', '3'], true) ? $normalized : '2';
    }

    public function isHttpUrl(string $url): bool
    {
        return trim($url) !== '' && preg_match('/^https?:\/\/.+/i', trim($url)) === 1;
    }

    /**
     * @return array{
     *     pay_url: string,
     *     sys_trade_no: string,
     *     pay_order_no: string,
     *     channel_trade_no: string,
     *     response: array<string, mixed>
     * }
     */
    public function createCashierPay(array $account, float $amount): array
    {
        $merchId = trim((string)($account['zfb_pid'] ?? ''));
        $merchantNo = trim((string)($account['wxname'] ?? ''));
        if ($merchId === '' || $merchantNo === '') {
            throw new RuntimeException('缴费易配置不完整，请填写商户 ID 和商户号');
        }

        $config = $this->decodeConfig($account);
        $cashierTemplateName = trim((string)($config['store_name'] ?? ''));
        if ($cashierTemplateName === '') {
            $cashierTemplateName = trim((string)SystemConfig::get('sitename', 'cashier')) ?: 'cashier';
        }

        $formattedAmount = number_format($amount, 2, '.', '');
        $timeKey = (int)(microtime(true) * 1000);
        $requestBody = [
            'merchId' => $merchId,
            'tradeAmount' => $formattedAmount,
            'remark' => trim((string)($account['qr_url'] ?? '')),
            'orderTemplateData' => [[
                'key' => $timeKey,
                'type' => 'number',
                'index' => 0,
                'label' => 'Pay Amount',
                'value' => $formattedAmount,
                'origin' => 'number' . $timeKey . '0',
                'options' => [
                    'label' => 'Pay Amount',
                    'content' => $formattedAmount,
                    'required' => true,
                    'labelAlign' => '',
                ],
                'displayName' => 'Amount',
                'formItemFlag' => false,
                'settingsTitle' => 'Amount Setting',
                'marginLeftRight' => 10,
                'marginTopBottom' => 5,
                'cashierTemplateName' => $cashierTemplateName,
                'state' => true,
            ]],
        ];

        $headers = $this->jsonHeaders();
        $clientIp = trim((string)($account['remark'] ?? ''));
        if ($clientIp !== '' && !$this->isHttpUrl($clientIp)) {
            $headers[] = 'X-FORWARDED-FOR: ' . $clientIp;
            $headers[] = 'CLIENT-IP: ' . $clientIp;
        }

        $response = $this->requestWithFallback(
            $account,
            self::PAY_API_URL,
            $requestBody,
            $headers,
            'pay'
        );

        $payUrl = trim((string)($this->extractValueRecursive($response, [
            'payUrl',
            'pay_url',
            'url',
            'codeUrl',
            'counterUrl',
        ]) ?? ''));
        if ($payUrl === '') {
            throw new RuntimeException('缴费易未返回支付地址');
        }

        return [
            'pay_url' => $payUrl,
            'sys_trade_no' => trim((string)($this->extractValueRecursive($response, [
                'sysTradeNo',
                'sys_trade_no',
                'tradeNo',
                'trade_no',
                'orderNo',
                'outOrderNo',
            ]) ?? '')),
            'pay_order_no' => $this->extractPayOrderNo($payUrl, $response),
            'channel_trade_no' => trim((string)($this->extractValueRecursive($response, [
                'channelTradeNo',
                'channel_trade_no',
            ]) ?? '')),
            'response' => $response,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function requestWithFallback(
        array $account,
        string $targetUrl,
        array $requestBody,
        array $headers,
        string $scene = 'pay'
    ): array {
        $config = $this->decodeConfig($account);
        $remoteApiUrl = trim((string)($config['remote_api_url'] ?? ''));
        $proxyApiUrl = trim((string)($config['proxy_api_url'] ?? ''));
        $lastError = null;

        if ($this->isHttpUrl($proxyApiUrl)) {
            try {
                return $this->requestViaProxyApi($proxyApiUrl, $targetUrl, $requestBody, $headers);
            } catch (Throwable $exception) {
                $lastError = $exception;
            }
        }

        try {
            return $this->curlRequest($targetUrl, $requestBody, $headers);
        } catch (Throwable $exception) {
            $lastError = $exception;
        }

        if ($this->isHttpUrl($remoteApiUrl)) {
            try {
                return $this->requestViaRemoteApi($remoteApiUrl, $targetUrl, $requestBody, $headers, $scene);
            } catch (Throwable $exception) {
                $lastError = $exception;
            }
        }

        throw new RuntimeException(
            $lastError instanceof Throwable ? $lastError->getMessage() : '缴费易请求失败'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function queryOrder(array $account, string $identifier): array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            throw new RuntimeException('query identifier cannot be empty');
        }

        $lastError = null;
        $results = [];

        foreach (['payOrderNo', 'outOrderNo', 'channelTradeNo'] as $fieldName) {
            try {
                $result = $this->queryOrderOnce($account, $identifier, $fieldName);
                $results[] = $result;

                if ($this->isPaidStatus($result['order_status'] ?? null)) {
                    return $result;
                }
            } catch (Throwable $exception) {
                $lastError = $exception;
            }
        }

        foreach ($results as $result) {
            if ($this->isUnpaidStatus($result['order_status'] ?? null)) {
                return $result;
            }
        }

        if ($results !== []) {
            return $results[0];
        }

        throw new RuntimeException(
            $lastError instanceof Throwable ? $lastError->getMessage() : '缴费易查单失败'
        );
    }

    public function isPaidStatus(mixed $status): bool
    {
        $status = strtoupper(trim((string)$status));

        return in_array($status, ['2', 'PAID', 'SUCCESS', 'TRADE_SUCCESS', 'PAY_SUCCESS'], true);
    }

    public function isUnpaidStatus(mixed $status): bool
    {
        $status = strtoupper(trim((string)$status));

        return in_array($status, ['0', '1', 'UNPAID', 'WAIT_PAY', 'PAYING', 'CREATE', 'PENDING'], true);
    }

    /**
     * @param array<int, string> $keys
     */
    public function extractValueRecursive(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = $this->findValueRecursive($data, (string)$key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $response
     */
    public function extractPayOrderNo(string $payUrl, ?array $response = null): string
    {
        if (is_array($response)) {
            $payOrderNo = $this->extractValueRecursive($response, [
                'payOrderNo',
                'pay_order_no',
                'channelTradeNo',
                'channel_trade_no',
            ]);
            if ($payOrderNo !== null && trim((string)$payOrderNo) !== '') {
                return trim((string)$payOrderNo);
            }
        }

        $decodedUrl = html_entity_decode(trim($payUrl), ENT_QUOTES);
        if ($decodedUrl === '') {
            return '';
        }

        for ($index = 0; $index < 3; $index++) {
            $query = parse_url($decodedUrl, PHP_URL_QUERY);
            if (is_string($query) && $query !== '') {
                parse_str($query, $queryData);
                foreach (['payOrderNo', 'pay_order_no', 'channelTradeNo', 'channel_trade_no'] as $field) {
                    if (!empty($queryData[$field])) {
                        return trim((string)$queryData[$field]);
                    }
                }
            }

            foreach (['payOrderNo', 'pay_order_no', 'channelTradeNo', 'channel_trade_no'] as $field) {
                if (preg_match('/(?:^|[?&])' . preg_quote($field, '/') . '=([^&#]+)/i', $decodedUrl, $matches) === 1) {
                    return trim(rawurldecode((string)$matches[1]));
                }
            }

            $nextUrl = rawurldecode($decodedUrl);
            if ($nextUrl === $decodedUrl) {
                break;
            }
            $decodedUrl = $nextUrl;
        }

        return '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodeJsonText(string $response): ?array
    {
        $text = trim($response);
        if ($text === '') {
            return null;
        }

        if (strncmp($text, "\xEF\xBB\xBF", 3) === 0) {
            $text = substr($text, 3);
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/^[\\w$]+\\((.*)\\)\\s*;?\\s*$/s', $text, $matches) === 1) {
            $decoded = json_decode(trim((string)$matches[1]), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function queryOrderOnce(array $account, string $identifier, string $fieldName): array
    {
        $merchantNo = trim((string)($account['wxname'] ?? ''));
        if ($merchantNo === '') {
            throw new RuntimeException('缴费易商户号不能为空');
        }

        $requestBody = [
            'reqTime' => date('YmdHis'),
            'version' => '1.0',
            'reqData' => [
                'channelId' => self::DEFAULT_CHANNEL_ID,
                $fieldName => $identifier,
                'merchantNo' => $merchantNo,
            ],
        ];

        $headers = $this->jsonHeaders();
        $clientIp = trim((string)($account['remark'] ?? ''));
        if ($clientIp !== '' && !$this->isHttpUrl($clientIp)) {
            $headers[] = 'X-FORWARDED-FOR: ' . $clientIp;
            $headers[] = 'CLIENT-IP: ' . $clientIp;
        }

        $response = $this->requestWithFallback(
            $account,
            self::QUERY_API_URL,
            $requestBody,
            $headers,
            'query'
        );

        $respData = is_array($response['respData'] ?? null) ? (array)$response['respData'] : [];

        return [
            'raw' => $response,
            'query_field' => $fieldName,
            'order_status' => $this->extractValueRecursive($response, ['orderStatus', 'order_status', 'tradeStatus', 'status'])
                ?? $this->extractValueRecursive($respData, ['orderStatus', 'order_status', 'tradeStatus', 'status']),
            'sys_trade_no' => trim((string)(
                $this->extractValueRecursive($response, ['sysTradeNo', 'sys_trade_no', 'tradeNo', 'trade_no', 'orderNo', 'outOrderNo'])
                ?? $this->extractValueRecursive($respData, ['sysTradeNo', 'sys_trade_no', 'tradeNo', 'trade_no', 'orderNo', 'outOrderNo'])
                ?? ''
            )),
            'pay_order_no' => $this->extractPayOrderNo('', $response) ?: $this->extractPayOrderNo('', $respData),
            'channel_trade_no' => trim((string)(
                $this->extractValueRecursive($response, ['channelTradeNo', 'channel_trade_no'])
                ?? $this->extractValueRecursive($respData, ['channelTradeNo', 'channel_trade_no'])
                ?? ''
            )),
            'buyer' => trim((string)(
                $this->extractValueRecursive($response, ['userId2', 'buyer', 'buyer_id', 'buyerId'])
                ?? $this->extractValueRecursive($respData, ['userId2', 'buyer', 'buyer_id', 'buyerId'])
                ?? ''
            )),
            'bill_trade_no' => trim((string)(
                $this->extractValueRecursive($response, ['tradeNo', 'billTradeNo', 'bill_trade_no'])
                ?? $this->extractValueRecursive($respData, ['tradeNo', 'billTradeNo', 'bill_trade_no'])
                ?? ''
            )),
            'bill_mch_trade_no' => trim((string)(
                $this->extractValueRecursive($response, ['accTradeNo', 'billMchTradeNo', 'bill_mch_trade_no'])
                ?? $this->extractValueRecursive($respData, ['accTradeNo', 'billMchTradeNo', 'bill_mch_trade_no'])
                ?? ''
            )),
        ];
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    private function requestViaRemoteApi(
        string $remoteApiUrl,
        string $targetUrl,
        array $requestBody,
        array $headers,
        string $scene
    ): array {
        $proxyResponse = $this->curlRequest(
            $remoteApiUrl,
            [
                'scene' => $scene,
                'target_url' => $targetUrl,
                'method' => 'POST',
                'data' => $requestBody,
                'headers' => array_values($headers),
                'timestamp' => time(),
            ],
            $this->remoteApiHeaders()
        );

        if (isset($proxyResponse['code']) && (int)$proxyResponse['code'] !== 0) {
            throw new RuntimeException((string)($proxyResponse['msg'] ?? '远程接口返回失败'));
        }

        if (is_array($proxyResponse['data'] ?? null)) {
            return (array)$proxyResponse['data'];
        }

        if (is_array($proxyResponse['result'] ?? null)) {
            return (array)$proxyResponse['result'];
        }

        return $proxyResponse;
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    private function requestViaProxyApi(
        string $proxyApiUrl,
        string $targetUrl,
        array $requestBody,
        array $headers
    ): array {
        $lastError = null;

        for ($attempt = 1; $attempt <= self::PROXY_RETRY_TIMES; $attempt++) {
            try {
                $proxy = $this->fetchProxyConfig($proxyApiUrl);

                return $this->curlRequest($targetUrl, $requestBody, $headers, $proxy);
            } catch (Throwable $exception) {
                $lastError = $exception;
                if ($attempt < self::PROXY_RETRY_TIMES) {
                    usleep(self::PROXY_RETRY_DELAY_US);
                }
            }
        }

        throw new RuntimeException(
            $lastError instanceof Throwable
                ? '代理请求失败: ' . $lastError->getMessage()
                : '代理请求失败'
        );
    }

    /**
     * @param array<int, string> $headers
     * @param array<string, mixed>|null $proxy
     * @return array<string, mixed>
     */
    private function curlRequest(string $url, array $payload, array $headers, ?array $proxy = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL extension is not installed');
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('请求数据编码失败');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        if ($headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if (is_array($proxy)) {
            $host = trim((string)($proxy['host'] ?? ''));
            $port = (int)($proxy['port'] ?? 0);
            if ($host === '' || $port <= 0) {
                curl_close($ch);
                throw new RuntimeException('代理节点无效');
            }

            curl_setopt($ch, CURLOPT_PROXY, $host);
            curl_setopt($ch, CURLOPT_PROXYPORT, $port);
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);

            $username = trim((string)($proxy['username'] ?? ''));
            $password = trim((string)($proxy['password'] ?? ''));
            if ($username !== '') {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $username . ':' . $password);
            }
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('请求失败: ' . $error);
        }

        curl_close($ch);

        $decoded = $this->decodeJsonText((string)$response);
        if (!is_array($decoded)) {
            throw new RuntimeException('响应数据解析失败');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchProxyConfig(string $proxyApiUrl): array
    {
        $raw = $this->httpGetText($proxyApiUrl);
        $decoded = $this->decodeJsonText($raw);

        if (is_array($decoded)) {
            $code = $decoded['code'] ?? null;
            if ($code !== null) {
                $normalizedCode = strtoupper(trim((string)$code));
                if (!in_array($normalizedCode, ['0', '200', 'SUCCESS', 'TRUE', 'OK'], true)) {
                    $message = trim((string)($decoded['msg'] ?? $decoded['message'] ?? '代理接口返回失败'));
                    throw new RuntimeException($message !== '' ? $message : '代理接口返回失败');
                }
            }

            $proxyNode = $this->findProxyNode($decoded);
            if ($proxyNode !== null) {
                return $proxyNode;
            }
        }

        if (preg_match('/(\d{1,3}(?:\.\d{1,3}){3})\s*[:]\s*(\d{2,5})/', $raw, $matches) === 1) {
            return [
                'host' => trim((string)$matches[1]),
                'port' => (int)$matches[2],
                'username' => '',
                'password' => '',
            ];
        }

        throw new RuntimeException('代理接口未返回可用代理节点');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findProxyNode(array $data): ?array
    {
        $candidates = [];
        foreach (['obj', 'rows', 'list', 'result', 'data'] as $field) {
            $value = $data[$field] ?? null;
            if (!is_array($value)) {
                continue;
            }

            $candidates[] = $value;
            if (isset($value[0]) && is_array($value[0])) {
                $candidates[] = $value[0];
            }
        }
        $candidates[] = $data;

        foreach ($candidates as $candidate) {
            $host = trim((string)($this->extractValueRecursive($candidate, [
                'ip',
                'proxyIp',
                'proxy_ip',
                'host',
                'proxyHost',
                'proxy_host',
            ]) ?? ''));
            $port = (int)($this->extractValueRecursive($candidate, [
                'port',
                'proxyPort',
                'proxy_port',
            ]) ?? 0);

            if ($host === '' || $port <= 0) {
                continue;
            }

            return [
                'host' => $host,
                'port' => $port,
                'username' => trim((string)($this->extractValueRecursive($candidate, [
                    'username',
                    'userName',
                    'user',
                    'account',
                    'proxyUser',
                    'proxy_user',
                ]) ?? '')),
                'password' => trim((string)($this->extractValueRecursive($candidate, [
                    'password',
                    'pass',
                    'passwd',
                    'pwd',
                    'proxyPass',
                    'proxy_pass',
                ]) ?? '')),
            ];
        }

        return null;
    }

    private function httpGetText(string $url): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL extension is not installed');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json,text/plain,*/*',
            'User-Agent: ' . self::USER_AGENT,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('代理接口请求失败: ' . $error);
        }

        curl_close($ch);

        return (string)$response;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function findValueRecursive(array $data, string $targetKey): mixed
    {
        foreach ($data as $key => $value) {
            if ((string)$key === $targetKey && !is_array($value)) {
                return $value;
            }

            if (is_array($value)) {
                $found = $this->findValueRecursive($value, $targetKey);
                if ($found !== null && $found !== '') {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function jsonHeaders(): array
    {
        return [
            'User-Agent: ' . self::USER_AGENT,
            'Content-Type: application/json;charset=utf-8',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function remoteApiHeaders(): array
    {
        return [
            'Content-Type: application/json;charset=utf-8',
            'Accept: application/json,text/plain,*/*',
            'User-Agent: ' . self::USER_AGENT,
        ];
    }
}
