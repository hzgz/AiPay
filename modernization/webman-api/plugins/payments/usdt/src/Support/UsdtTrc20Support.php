<?php

declare(strict_types=1);

namespace Plugins\Payments\Usdt\Support;

use Closure;
use RuntimeException;

final class UsdtTrc20Support
{
    public const USDT_CONTRACT_ADDRESS = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
    public const TRANSACTION_PROVIDER = 'tron_usdt_trc20';

    private const DEFAULT_BASE_URL = 'https://api.trongrid.io';
    private const TOKEN_DECIMALS = 6;
    private const PAGE_SIZE = 100;
    private const MAX_PAGES = 3;
    private const CONNECT_TIMEOUT_SECONDS = 3;
    private const REQUEST_TIMEOUT_SECONDS = 8;
    private const BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    private string $baseUrl;
    private string $apiKey;
    private ?Closure $transport;

    public function __construct(
        string $baseUrl = self::DEFAULT_BASE_URL,
        ?callable $transport = null,
        ?string $apiKey = null
    ) {
        $baseUrl = rtrim(trim($baseUrl), '/');
        if (!str_starts_with($baseUrl, 'https://')) {
            throw new RuntimeException('TronGrid base URL must use HTTPS');
        }

        $this->baseUrl = $baseUrl;
        $this->transport = $transport === null ? null : Closure::fromCallable($transport);
        $this->apiKey = trim($apiKey ?? $this->environmentApiKey());
    }

    /**
     * @param array<string, mixed> $account
     * @param array<string, mixed> $order
     * @return array<int, array<string, mixed>>
     */
    public function queryIncomingTransfers(array $account, array $order): array
    {
        $recipient = trim((string)($account['wxname'] ?? ''));
        if (!$this->isValidMainnetAddress($recipient)) {
            throw new RuntimeException('USDT account wallet address is invalid');
        }

        [$minimumTimestamp, $maximumTimestamp] = $this->queryWindow($order);
        $path = '/v1/accounts/' . rawurlencode($recipient) . '/transactions/trc20';
        $query = [
            'only_to' => 'true',
            'only_confirmed' => 'true',
            'limit' => (string)self::PAGE_SIZE,
            'contract_address' => self::USDT_CONTRACT_ADDRESS,
            'min_timestamp' => (string)($minimumTimestamp * 1000),
            'max_timestamp' => (string)($maximumTimestamp * 1000),
            'order_by' => 'block_timestamp,desc',
        ];

        $transfers = [];
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $response = $this->requestJson($path, $query);
            if (($response['success'] ?? null) !== true || !is_array($response['data'] ?? null)) {
                throw new RuntimeException('TronGrid returned an invalid transfer response');
            }

            $pageRows = array_values($response['data']);
            foreach ($pageRows as $row) {
                $normalized = $this->normalizeTransfer($row, $recipient);
                if ($normalized === null) {
                    continue;
                }

                $transactionId = (string)$normalized['transaction_id'];
                if (isset($transfers[$transactionId]) && $transfers[$transactionId] !== $normalized) {
                    throw new RuntimeException('TronGrid returned conflicting events for one transaction');
                }
                $transfers[$transactionId] = $normalized;
            }

            $fingerprint = trim((string)($response['meta']['fingerprint'] ?? ''));
            if (count($pageRows) < self::PAGE_SIZE) {
                break;
            }
            if ($fingerprint === '') {
                throw new RuntimeException('TronGrid full transfer page is missing a pagination fingerprint');
            }
            if ($page === self::MAX_PAGES) {
                throw new RuntimeException('TronGrid transfer page limit was exceeded');
            }
            $query['fingerprint'] = $fingerprint;
        }

        $transfers = array_values($transfers);
        usort(
            $transfers,
            static fn (array $left, array $right): int =>
                (int)($left['paid_timestamp'] ?? 0) <=> (int)($right['paid_timestamp'] ?? 0)
        );

        return $transfers;
    }

    /**
     * @param array<string, mixed> $order
     * @param array<int, array<string, mixed>> $transfers
     * @param array<int, string> $excludedTransactionIds
     * @return array<string, mixed>
     */
    public function matchTransfer(
        array $order,
        array $transfers,
        bool $hasUniquePendingAmount,
        array $excludedTransactionIds = []
    ): array {
        if (!$hasUniquePendingAmount) {
            return $this->pendingMatch('amount_not_unique', count($transfers), 0);
        }

        $expectedAtomic = $this->decimalToAtomic($order['truemoney'] ?? $order['money'] ?? null);
        if ($expectedAtomic === null || $expectedAtomic === '0') {
            return $this->pendingMatch('invalid_order_amount', count($transfers), 0);
        }

        [$minimumTimestamp, $maximumTimestamp] = $this->queryWindow($order);
        $excluded = array_fill_keys(array_map(
            static fn (mixed $value): string => strtolower(trim((string)$value)),
            $excludedTransactionIds
        ), true);

        $candidates = [];
        foreach ($transfers as $transfer) {
            $transactionId = strtolower(trim((string)($transfer['transaction_id'] ?? '')));
            if (!preg_match('/^[a-f0-9]{64}$/', $transactionId) || isset($excluded[$transactionId])) {
                continue;
            }
            if (trim((string)($transfer['contract_address'] ?? '')) !== self::USDT_CONTRACT_ADDRESS) {
                continue;
            }
            if ((int)($transfer['token_decimals'] ?? -1) !== self::TOKEN_DECIMALS) {
                continue;
            }
            if (!$this->isValidMainnetAddress(trim((string)($transfer['to_address'] ?? '')))) {
                continue;
            }
            if ($this->normalizeUnsignedInteger($transfer['amount_atomic'] ?? null) !== $expectedAtomic) {
                continue;
            }

            $paidTimestamp = (int)($transfer['paid_timestamp'] ?? 0);
            if ($paidTimestamp < $minimumTimestamp || $paidTimestamp > $maximumTimestamp) {
                continue;
            }

            $candidates[] = $transfer;
        }

        if (count($candidates) !== 1) {
            return $this->pendingMatch(
                $candidates === [] ? 'no_matching_transfer' : 'ambiguous_amount_transfers',
                count($transfers),
                count($candidates)
            );
        }

        return [
            'status' => 'paid',
            'reason' => 'matched_unique_amount',
            'match_mode' => 'unique_amount',
            'transaction_count' => count($transfers),
            'transaction' => $candidates[0],
        ];
    }

    public function isValidMainnetAddress(string $address): bool
    {
        if (strlen($address) !== 34 || $address[0] !== 'T') {
            return false;
        }
        if (strspn($address, self::BASE58_ALPHABET) !== strlen($address)) {
            return false;
        }

        $decoded = $this->decodeBase58($address);
        if ($decoded === null || strlen($decoded) !== 25) {
            return false;
        }

        $payload = substr($decoded, 0, 21);
        $checksum = substr($decoded, 21, 4);
        if (ord($payload[0]) !== 0x41) {
            return false;
        }

        $expected = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);

        return hash_equals($expected, $checksum);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeTransfer(mixed $row, string $recipient): ?array
    {
        if (is_object($row)) {
            $row = (array)$row;
        }
        if (!is_array($row) || !is_array($row['token_info'] ?? null)) {
            return null;
        }

        $tokenInfo = $row['token_info'];
        $transactionId = strtolower(trim((string)($row['transaction_id'] ?? '')));
        $contractAddress = trim((string)($tokenInfo['address'] ?? ''));
        $tokenDecimals = filter_var($tokenInfo['decimals'] ?? null, FILTER_VALIDATE_INT);
        $toAddress = trim((string)($row['to'] ?? ''));
        $fromAddress = trim((string)($row['from'] ?? ''));
        $amountAtomic = $this->normalizeUnsignedInteger($row['value'] ?? null);
        $timestampMilliseconds = $this->normalizeUnsignedInteger($row['block_timestamp'] ?? null);

        if (!preg_match('/^[a-f0-9]{64}$/', $transactionId)) {
            return null;
        }
        if ($contractAddress !== self::USDT_CONTRACT_ADDRESS || $tokenDecimals !== self::TOKEN_DECIMALS) {
            return null;
        }
        if (strcasecmp(trim((string)($row['type'] ?? '')), 'Transfer') !== 0) {
            return null;
        }
        if ($toAddress !== $recipient || !$this->isValidMainnetAddress($toAddress)) {
            return null;
        }
        if ($fromAddress !== '' && !$this->isValidMainnetAddress($fromAddress)) {
            return null;
        }
        if ($amountAtomic === null || $amountAtomic === '0' || $timestampMilliseconds === null) {
            return null;
        }

        $paidTimestamp = intdiv((int)$timestampMilliseconds, 1000);
        if ($paidTimestamp <= 0) {
            return null;
        }

        return [
            'transaction_id' => $transactionId,
            'contract_address' => $contractAddress,
            'token_decimals' => self::TOKEN_DECIMALS,
            'amount' => $this->atomicToDecimal($amountAtomic),
            'amount_atomic' => $amountAtomic,
            'from_address' => $fromAddress,
            'to_address' => $toAddress,
            'paid_at' => date('Y-m-d H:i:s', $paidTimestamp),
            'paid_timestamp' => $paidTimestamp,
            'confirmed' => true,
        ];
    }

    /**
     * @param array<string, mixed> $order
     * @return array{0: int, 1: int}
     */
    private function queryWindow(array $order): array
    {
        $createdAt = strtotime(trim((string)($order['create_time'] ?? ''))) ?: 0;
        $outTime = (int)($order['out_time'] ?? 0);
        if ($createdAt <= 0 || $outTime <= 0 || $outTime < $createdAt) {
            throw new RuntimeException('USDT order has an invalid reconciliation window');
        }

        $minimumTimestamp = max(1, $createdAt);
        $maximumTimestamp = min(time(), $outTime);
        if ($maximumTimestamp < $minimumTimestamp) {
            throw new RuntimeException('USDT reconciliation window has not started');
        }

        return [$minimumTimestamp, $maximumTimestamp];
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    private function requestJson(string $path, array $query): array
    {
        $url = $this->baseUrl . $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $headers = [
            'Accept: application/json',
            'User-Agent: AiPay-Webman-USDT-Reconciler/1.0',
        ];
        if ($this->apiKey !== '') {
            $headers[] = 'TRON-PRO-API-KEY: ' . $this->apiKey;
        }

        if ($this->transport !== null) {
            $raw = ($this->transport)(
                $url,
                $headers,
                self::CONNECT_TIMEOUT_SECONDS,
                self::REQUEST_TIMEOUT_SECONDS
            );
            if (is_array($raw)) {
                return $raw;
            }
            if (!is_string($raw)) {
                throw new RuntimeException('TronGrid transport returned an invalid response');
            }

            return $this->decodeJson($raw);
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('The PHP cURL extension is required for TronGrid queries');
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Failed to initialize TronGrid request');
        }

        try {
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
                CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT_SECONDS,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
                curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
            }

            $body = curl_exec($curl);
            if (!is_string($body)) {
                throw new RuntimeException('TronGrid request failed: ' . curl_error($curl));
            }

            $httpStatus = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($httpStatus < 200 || $httpStatus >= 300) {
                throw new RuntimeException('TronGrid request returned HTTP ' . $httpStatus);
            }

            return $this->decodeJson($body);
        } finally {
            curl_close($curl);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $raw): array
    {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('TronGrid returned malformed JSON', previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('TronGrid returned an invalid JSON object');
        }

        return $decoded;
    }

    private function decimalToAtomic(mixed $amount): ?string
    {
        $amount = trim((string)$amount);
        if (!preg_match('/^(\d+)(?:\.(\d{1,6}))?$/', $amount, $matches)) {
            return null;
        }

        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad($matches[2] ?? '', self::TOKEN_DECIMALS, '0');

        return $this->normalizeUnsignedInteger($whole . $fraction);
    }

    private function atomicToDecimal(string $amountAtomic): string
    {
        $padded = str_pad($amountAtomic, self::TOKEN_DECIMALS + 1, '0', STR_PAD_LEFT);
        $whole = substr($padded, 0, -self::TOKEN_DECIMALS);
        $fraction = substr($padded, -self::TOKEN_DECIMALS);

        return $whole . '.' . $fraction;
    }

    private function normalizeUnsignedInteger(mixed $value): ?string
    {
        $value = trim((string)$value);
        if (!preg_match('/^\d+$/', $value)) {
            return null;
        }

        $normalized = ltrim($value, '0');

        return $normalized === '' ? '0' : $normalized;
    }

    private function decodeBase58(string $value): ?string
    {
        $bytes = [0];
        $length = strlen($value);
        for ($position = 0; $position < $length; $position++) {
            $digit = strpos(self::BASE58_ALPHABET, $value[$position]);
            if ($digit === false) {
                return null;
            }

            $carry = $digit;
            $byteCount = count($bytes);
            for ($index = 0; $index < $byteCount; $index++) {
                $carry += $bytes[$index] * 58;
                $bytes[$index] = $carry & 0xff;
                $carry >>= 8;
            }
            while ($carry > 0) {
                $bytes[] = $carry & 0xff;
                $carry >>= 8;
            }
        }

        $bytes = array_reverse($bytes);

        return pack('C*', ...$bytes);
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingMatch(string $reason, int $transactionCount, int $candidateCount): array
    {
        return [
            'status' => 'pending',
            'reason' => $reason,
            'transaction_count' => $transactionCount,
            'amount_candidate_count' => $candidateCount,
        ];
    }

    private function environmentApiKey(): string
    {
        $value = $_ENV['TRONGRID_API_KEY'] ?? $_SERVER['TRONGRID_API_KEY'] ?? getenv('TRONGRID_API_KEY');

        return is_string($value) ? $value : '';
    }
}
