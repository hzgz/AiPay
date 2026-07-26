<?php

declare(strict_types=1);

namespace app\service\order;

use app\support\BusinessTable;
use support\Db;

final class OrderCallbackBuilder
{
    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $merchant
     * @param array<string, mixed>|null $basicSettings
     * @return array{notify: string, return: string, payload: array<string, string>}
     */
    public function buildUrls(array $order, array $merchant, ?array $basicSettings = null): array
    {
        $merchantId = (int)($merchant['id'] ?? $order['user_id'] ?? 0);
        $basicSettings = $basicSettings ?? $this->loadBasicSettings($merchantId);
        $hiddenName = (int)($basicSettings['callback_hiddenName'] ?? 0) === 1;

        $payload = [
            'pid' => (string)($order['user_id'] ?? ''),
            'trade_no' => trim((string)($order['trade_no'] ?? '')),
            'out_trade_no' => trim((string)($order['out_trade_no'] ?? '')),
            'type' => trim((string)($order['type'] ?? '')),
            'money' => number_format((float)($order['money'] ?? 0), 2, '.', ''),
            'trade_status' => 'TRADE_SUCCESS',
        ];

        if (!$hiddenName) {
            $payload['name'] = trim((string)($order['name'] ?? ''));
        }

        $payload['sign'] = $this->sign($payload, (string)($merchant['user_key'] ?? ''));
        $payload['sign_type'] = 'MD5';

        return [
            'notify' => $this->appendQuery(trim((string)($order['notify_url'] ?? '')), $payload),
            'return' => $this->appendQuery(trim((string)($order['return_url'] ?? '')), $payload),
            'payload' => $payload,
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    public function memoFromResponse(array $response): string
    {
        if (!empty($response['error'])) {
            return mb_substr(
                'HTTP ' . (int)($response['status'] ?? 0) . ' ' . trim((string)($response['error'] ?? '')),
                0,
                500
            );
        }

        $status = (int)($response['status'] ?? 0);
        $body = trim((string)($response['body'] ?? ''));
        if ($body === '') {
            return 'HTTP ' . $status;
        }

        $memo = 'HTTP ' . $status . ' ' . $body;
        if (mb_strlen($memo) > 500) {
            return mb_substr($memo, 0, 500);
        }

        return $memo;
    }

    /**
     * @param array<string, string> $payload
     */
    public function sign(array $payload, string $key): string
    {
        ksort($payload);
        $pairs = [];
        foreach ($payload as $name => $value) {
            if ($name === 'sign' || $name === 'sign_type' || $value === '') {
                continue;
            }

            $pairs[] = $name . '=' . $value;
        }

        return md5(implode('&', $pairs) . $key);
    }

    /**
     * @param array<string, string> $query
     */
    public function appendQuery(string $url, array $query): string
    {
        if ($url === '') {
            return '';
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadBasicSettings(int $merchantId): array
    {
        if ($merchantId <= 0) {
            return ['callback_hiddenName' => 0];
        }

        $row = Db::table(BusinessTable::userBasic())
            ->select('callback_hiddenName')
            ->where('user_id', $merchantId)
            ->first();

        return $row ? (array)$row : ['callback_hiddenName' => 0];
    }

}
