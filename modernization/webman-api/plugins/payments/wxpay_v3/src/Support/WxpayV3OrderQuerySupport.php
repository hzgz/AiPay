<?php

declare(strict_types=1);

namespace Plugins\Payments\WxpayV3\Support;

use app\support\WxpayV3SdkAutoloader;
use RuntimeException;

final class WxpayV3OrderQuerySupport
{
    private const PAID_STATES = ['SUCCESS', 'REFUND'];
    private const PENDING_STATES = ['NOTPAY', 'USERPAYING', 'PROCESSING', 'ACCEPT'];
    private const CLOSED_STATES = ['CLOSED', 'PAYERROR', 'REVOKED'];

    /**
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    public function queryOrder(array $account, string $outTradeNo = '', string $transactionId = ''): array
    {
        $outTradeNo = trim($outTradeNo);
        $transactionId = trim($transactionId);
        if ($outTradeNo === '' && $transactionId === '') {
            throw new RuntimeException('missing_query_identifier');
        }

        WxpayV3SdkAutoloader::register();
        $client = new \WeChatPay\V3\PaymentService($this->sdkConfig($account));
        $result = $transactionId !== ''
            ? $client->orderQuery($transactionId, null)
            : $client->orderQuery(null, $outTradeNo);

        return is_array($result) ? $result : [];
    }

    public function isPaidStatus(mixed $tradeState): bool
    {
        return in_array($this->normalizeState($tradeState), self::PAID_STATES, true);
    }

    public function isPendingStatus(mixed $tradeState): bool
    {
        return in_array($this->normalizeState($tradeState), self::PENDING_STATES, true);
    }

    public function isClosedStatus(mixed $tradeState): bool
    {
        return in_array($this->normalizeState($tradeState), self::CLOSED_STATES, true);
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $queryResult
     */
    public function assertOrderMatches(array $order, array $queryResult): void
    {
        $transactionId = trim((string)($queryResult['transaction_id'] ?? ''));
        if ($transactionId === '') {
            throw new RuntimeException('transaction_id_missing');
        }

        $amount = $queryResult['amount'] ?? null;
        if (!is_array($amount) || !array_key_exists('total', $amount)) {
            throw new RuntimeException('amount_missing');
        }

        $notifiedTotal = $this->integerCents($amount['total']);
        $expectedTotal = $this->amountToCents((string)($order['truemoney'] ?? $order['money'] ?? '0'));
        if ($notifiedTotal <= 0 || $expectedTotal <= 0 || $notifiedTotal !== $expectedTotal) {
            throw new RuntimeException('amount_mismatch');
        }

        $currency = strtoupper(trim((string)($amount['currency'] ?? '')));
        if ($currency !== 'CNY') {
            throw new RuntimeException('currency_mismatch');
        }

        $storedTransactionId = trim((string)($order['alipay_order_no'] ?? ''));
        if (
            (int)($order['status'] ?? 0) === 1
            && $storedTransactionId !== ''
            && !hash_equals($storedTransactionId, $transactionId)
        ) {
            throw new RuntimeException('transaction_mismatch');
        }
    }

    /**
     * @param array<string, mixed> $queryResult
     * @return array<string, string>
     */
    public function settlementPayloadFromQuery(array $queryResult): array
    {
        $transactionId = trim((string)($queryResult['transaction_id'] ?? ''));
        $payer = is_array($queryResult['payer'] ?? null) ? (array)$queryResult['payer'] : [];

        return [
            'trade_no' => $transactionId,
            'transaction_id' => $transactionId,
            'buyer_trade_no' => trim((string)($payer['openid'] ?? '')),
            'transaction_provider' => 'wxpay_v3',
        ];
    }

    /**
     * @param array<string, mixed> $account
     * @return array<string, string>
     */
    private function sdkConfig(array $account): array
    {
        $appId = trim((string)($account['wxname'] ?? ''));
        if ($appId === '') {
            throw new RuntimeException('wxpay_v3_appid_missing');
        }

        $mchId = trim((string)($account['zfb_pid'] ?? ''));
        if ($mchId === '') {
            throw new RuntimeException('wxpay_v3_mchid_missing');
        }

        $apiV3Key = trim((string)($account['remark'] ?? ''));
        if ($apiV3Key === '' || strlen($apiV3Key) !== 32) {
            throw new RuntimeException('wxpay_v3_api_v3_key_invalid');
        }

        $serial = trim((string)($account['wx_guid'] ?? ''));
        if ($serial === '') {
            throw new RuntimeException('wxpay_v3_merchant_serial_missing');
        }

        $merchantPrivateKey = trim((string)($account['qr_url'] ?? ''));
        if ($merchantPrivateKey === '') {
            throw new RuntimeException('wxpay_v3_private_key_missing');
        }

        $accountId = (int)($account['id'] ?? 0);
        $runtimeDir = WxpayV3SdkAutoloader::ensureRuntimeDirectory($accountId);
        $merchantPrivateKeyPath = WxpayV3SdkAutoloader::writeRuntimeFile(
            $accountId,
            'merchant_private_key.pem',
            $this->normalizePem($merchantPrivateKey, 'PRIVATE KEY')
        );

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
            $platformPublicKeyPath = WxpayV3SdkAutoloader::writeRuntimeFile(
                $accountId,
                'platform_public_key.pem',
                $this->normalizePem($platformPublicKey, 'PUBLIC KEY')
            );
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

    private function normalizeState(mixed $tradeState): string
    {
        return strtoupper(trim((string)$tradeState));
    }

    private function integerCents(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (!is_string($value) || !preg_match('/^[1-9]\d*$/', $value) || strlen($value) > 14) {
            return 0;
        }

        return (int)$value;
    }

    private function amountToCents(string $amount): int
    {
        $amount = trim($amount);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            return 0;
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        if (strlen($whole) > 12) {
            return 0;
        }

        return ((int)$whole * 100) + (int)str_pad($fraction, 2, '0');
    }
}
