<?php

declare(strict_types=1);

namespace app\support;

final class PaymentResultPageTicket
{
    public const SCENE_CASHIER = 'cashier';
    public const SCENE_MERCHANT_CHANNEL_TEST = 'merchant_channel_test';
    private const VERSION = 1;
    private const DEFAULT_TTL = 21600;
    private const TEST_PAY_OUT_TRADE_NO_PREFIX = 'TEST';

    /**
     * @param array<string, mixed> $order
     */
    public static function issue(array $order, string $scene = '', int $ttl = self::DEFAULT_TTL): string
    {
        $tradeNo = trim((string)($order['trade_no'] ?? ''));
        $outTradeNo = trim((string)($order['out_trade_no'] ?? ''));
        $merchantId = (int)($order['user_id'] ?? 0);
        if ($tradeNo === '' || $outTradeNo === '' || $merchantId <= 0) {
            return '';
        }

        $issuedAt = time();
        $expiresAt = $issuedAt + max(300, $ttl);
        $normalizedScene = self::normalizeScene($scene, $order);
        $payload = [
            'version' => self::VERSION,
            'scene' => $normalizedScene,
            'trade_no' => $tradeNo,
            'out_trade_no' => $outTradeNo,
            'merchant_id' => $merchantId,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
        ];
        $payload['signature'] = hash_hmac('sha256', self::signatureMessage($payload), self::key());

        return self::encode($payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function read(string $ticket): ?array
    {
        $payload = self::decode($ticket);
        if ($payload === null) {
            return null;
        }

        $version = (int)($payload['version'] ?? 0);
        $scene = trim((string)($payload['scene'] ?? ''));
        $tradeNo = trim((string)($payload['trade_no'] ?? ''));
        $outTradeNo = trim((string)($payload['out_trade_no'] ?? ''));
        $merchantId = (int)($payload['merchant_id'] ?? 0);
        $issuedAt = (int)($payload['issued_at'] ?? 0);
        $expiresAt = (int)($payload['expires_at'] ?? 0);
        $signature = trim((string)($payload['signature'] ?? ''));

        if (
            $version !== self::VERSION
            || !in_array($scene, [self::SCENE_CASHIER, self::SCENE_MERCHANT_CHANNEL_TEST], true)
            || $tradeNo === ''
            || $outTradeNo === ''
            || $merchantId <= 0
            || $issuedAt <= 0
            || $expiresAt < time()
            || $signature === ''
        ) {
            return null;
        }

        $expected = hash_hmac('sha256', self::signatureMessage($payload), self::key());
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $order
     */
    public static function isMerchantChannelTestOrder(array $order): bool
    {
        $memo = strtolower(trim((string)($order['api_memo'] ?? '')));
        if (str_starts_with($memo, 'merchant_channel_test')) {
            return true;
        }

        $outTradeNo = strtoupper(trim((string)($order['out_trade_no'] ?? '')));
        if (!str_starts_with($outTradeNo, self::TEST_PAY_OUT_TRADE_NO_PREFIX)) {
            return false;
        }

        return trim((string)($order['notify_url'] ?? '')) === ''
            && trim((string)($order['return_url'] ?? '')) === '';
    }

    /**
     * @param array<string, mixed> $order
     */
    public static function normalizeScene(string $scene, array $order = []): string
    {
        $normalized = strtolower(trim($scene));
        if ($normalized === self::SCENE_MERCHANT_CHANNEL_TEST) {
            return self::SCENE_MERCHANT_CHANNEL_TEST;
        }

        if ($normalized === self::SCENE_CASHIER) {
            return self::SCENE_CASHIER;
        }

        return self::isMerchantChannelTestOrder($order)
            ? self::SCENE_MERCHANT_CHANNEL_TEST
            : self::SCENE_CASHIER;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function signatureMessage(array $payload): string
    {
        return implode('|', [
            (string)($payload['version'] ?? ''),
            (string)($payload['scene'] ?? ''),
            (string)($payload['trade_no'] ?? ''),
            (string)($payload['out_trade_no'] ?? ''),
            (string)($payload['merchant_id'] ?? ''),
            (string)($payload['issued_at'] ?? ''),
            (string)($payload['expires_at'] ?? ''),
        ]);
    }

    private static function key(): string
    {
        return hash('sha256', dirname(base_path(), 2) . '|payment-result-ticket|aipay');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function encode(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return '';
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decode(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $decoded = base64_decode(strtr($value . str_repeat('=', (4 - strlen($value) % 4) % 4), '-_', '+/'), true);
        if (!is_string($decoded) || $decoded === '') {
            return null;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload) ? $payload : null;
    }
}
