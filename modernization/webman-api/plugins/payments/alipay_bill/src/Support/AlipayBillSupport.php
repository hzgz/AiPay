<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace Plugins\Payments\AlipayBill\Support;

use RuntimeException;
use Traversable;

final class AlipayBillSupport
{
    private const MAX_QUERY_LOOKBACK_SECONDS = 3600;
    private const QUERY_PAGE_SIZE = 100;
    private const MAX_QUERY_PAGES = 5;
    private const ORDER_CLOCK_SKEW_SECONDS = 60;
    private const ORDER_GRACE_SECONDS = 300;
    private const INCOME_LABEL = "\u{6536}\u{5165}";
    private const BALANCE_TRANSFER_MEMO = "\u{8F6C}\u{51FA}\u{5230}\u{4F59}\u{989D}";
    private const ORDER_MEMO_PREFIX = "\u{8BF7}\u{52FF}\u{6DFB}\u{52A0}\u{5907}\u{6CE8}-";

    private static bool $sdkRegistered = false;

    /**
     * @param array<string, mixed> $account
     * @param array<string, mixed> $order
     * @return array<int, array<string, mixed>>
     */
    public function queryTransactions(array $account, array $order): array
    {
        $credentials = $this->credentials($account);
        $this->registerSdk();

        $client = new \AopClient();
        $client->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $client->appId = $credentials['app_id'];
        $client->rsaPrivateKey = $credentials['private_key'];
        $client->alipayrsaPublicKey = $credentials['public_key'];
        $client->apiVersion = '1.0';
        $client->signType = 'RSA2';
        $client->postCharset = 'UTF-8';
        $client->format = 'json';

        $transactions = [];
        $hitFullPageCap = false;
        for ($page = 1; $page <= self::MAX_QUERY_PAGES; $page++) {
            $request = new \AlipayDataBillAccountlogQueryRequest();
            $request->setBizContent(json_encode(
                $this->queryPayload($order, $credentials['bill_user_id'], $page),
                JSON_UNESCAPED_SLASHES
            ));

            $result = $client->execute($request);
            $responseNode = str_replace('.', '_', $request->getApiMethodName()) . '_response';
            $response = is_object($result) ? ($result->$responseNode ?? $result->error_response ?? null) : null;
            if (!is_object($response)) {
                throw new RuntimeException('支付宝账单接口返回无效响应');
            }

            $code = trim((string)($response->code ?? ''));
            if ($code !== '10000') {
                $message = trim((string)($response->sub_msg ?? $response->msg ?? '支付宝账单查询失败'));
                throw new RuntimeException($message !== '' ? $message : '支付宝账单查询失败');
            }

            $detailList = $this->detailList($response->detail_list ?? []);
            foreach ($detailList as $detail) {
                $normalized = $this->normalizeTransaction($detail);
                if ($normalized !== null) {
                    $transactions[(string)$normalized['transaction_id']] = $normalized;
                }
            }

            if (count($detailList) < self::QUERY_PAGE_SIZE) {
                break;
            }

            if ($page === self::MAX_QUERY_PAGES) {
                $hitFullPageCap = true;
            }
        }

        if ($hitFullPageCap) {
            throw new RuntimeException('支付宝账单查询超过分页上限');
        }

        $transactions = array_values($transactions);

        usort(
            $transactions,
            static fn (array $left, array $right): int => (int)($left['paid_timestamp'] ?? 0) <=> (int)($right['paid_timestamp'] ?? 0)
        );

        return $transactions;
    }

    /**
     * @param array<string, mixed> $order
     * @param array<int, array<string, mixed>> $transactions
     * @param array<int, string> $excludedTransactionIds
     * @return array<string, mixed>
     */
    public function matchTransaction(
        array $order,
        array $transactions,
        bool $allowAmountFallback,
        array $excludedTransactionIds = []
    ): array {
        $expectedAmount = number_format((float)($order['truemoney'] ?? $order['money'] ?? 0), 2, '.', '');
        $orderCreatedAt = strtotime((string)($order['create_time'] ?? '')) ?: 0;
        $orderOutTime = (int)($order['out_time'] ?? 0);
        $memoTargets = array_values(array_filter([
            trim((string)($order['out_trade_no'] ?? '')),
            trim((string)($order['trade_no'] ?? '')),
        ], static fn (string $value): bool => $value !== ''));
        $excluded = array_fill_keys(array_map('strval', $excludedTransactionIds), true);

        $exactMemoCandidates = [];
        $amountCandidates = [];
        foreach ($transactions as $transaction) {
            $transactionId = trim((string)($transaction['transaction_id'] ?? ''));
            if ($transactionId === '' || isset($excluded[$transactionId])) {
                continue;
            }

            if (!$this->isIncome((string)($transaction['direction'] ?? ''))) {
                continue;
            }

            $memo = $this->normalizeMemo((string)($transaction['memo'] ?? ''));
            if ($memo === self::BALANCE_TRANSFER_MEMO) {
                continue;
            }

            $amount = number_format((float)($transaction['amount'] ?? 0), 2, '.', '');
            if ($amount !== $expectedAmount) {
                continue;
            }

            $paidAt = (int)($transaction['paid_timestamp'] ?? 0);
            if ($paidAt > 0 && $orderCreatedAt > 0 && $paidAt < ($orderCreatedAt - self::ORDER_CLOCK_SKEW_SECONDS)) {
                continue;
            }
            if ($paidAt > 0 && $orderOutTime > 0 && $paidAt > ($orderOutTime + self::ORDER_GRACE_SECONDS)) {
                continue;
            }

            $transaction['memo'] = $memo;
            if ($memo !== '' && in_array($memo, $memoTargets, true)) {
                $exactMemoCandidates[] = $transaction;
                continue;
            }

            if ($paidAt > 0) {
                $amountCandidates[] = $transaction;
            }
        }

        if (count($exactMemoCandidates) > 1) {
            return $this->pendingMatch(
                'ambiguous_memo_transactions',
                count($transactions),
                count($amountCandidates),
                count($exactMemoCandidates)
            );
        }

        if ($exactMemoCandidates !== []) {
            return $this->paidMatch($exactMemoCandidates[0], 'memo');
        }

        if (!$allowAmountFallback) {
            return $this->pendingMatch('amount_fallback_disabled', count($transactions), count($amountCandidates));
        }

        if (count($amountCandidates) !== 1) {
            return $this->pendingMatch(
                $amountCandidates === [] ? 'no_matching_transaction' : 'ambiguous_amount_transactions',
                count($transactions),
                count($amountCandidates)
            );
        }

        return $this->paidMatch($amountCandidates[0], 'unique_amount');
    }

    /**
     * @param array<string, mixed> $account
     * @return array{app_id: string, private_key: string, public_key: string, bill_user_id: string}
     */
    private function credentials(array $account): array
    {
        $rawPayload = trim((string)($account['qr_url'] ?? ''));
        $decoded = json_decode($rawPayload, true);
        $privateKey = is_array($decoded)
            ? trim((string)($decoded['private_key'] ?? ''))
            : $rawPayload;

        $credentials = [
            'app_id' => trim((string)($account['wxname'] ?? '')),
            'private_key' => $privateKey,
            'public_key' => trim((string)($account['cookie'] ?? '')),
            'bill_user_id' => trim((string)($account['zfb_pid'] ?? '')),
        ];

        foreach (['app_id', 'private_key', 'public_key'] as $field) {
            if ($credentials[$field] === '') {
                throw new RuntimeException('支付宝账单插件凭证不完整：' . $field);
            }
        }

        return $credentials;
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, string>
     */
    private function queryPayload(array $order, string $billUserId, int $page): array
    {
        $now = time();
        $createdAt = strtotime((string)($order['create_time'] ?? '')) ?: ($now - 300);
        $startAt = max($createdAt - self::ORDER_CLOCK_SKEW_SECONDS, $now - self::MAX_QUERY_LOOKBACK_SECONDS);

        $payload = [
            'start_time' => date('Y-m-d H:i:s', $startAt),
            'end_time' => date('Y-m-d H:i:s', $now),
            'page_no' => (string)max(1, $page),
            'page_size' => (string)self::QUERY_PAGE_SIZE,
            'agreement_product_code' => 'FUND_SIGN_WITHHOLDING',
        ];

        if ($billUserId !== '') {
            $payload['bill_user_id'] = $billUserId;
        }

        return $payload;
    }

    /**
     * @return array<int, mixed>
     */
    private function detailList(mixed $details): array
    {
        if ($details instanceof Traversable) {
            $details = iterator_to_array($details, false);
        }
        if (is_object($details)) {
            $details = (array)$details;
        }

        return is_array($details) ? array_values($details) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeTransaction(mixed $detail): ?array
    {
        if (is_object($detail)) {
            $detail = (array)$detail;
        }
        if (!is_array($detail)) {
            return null;
        }

        $transactionId = trim((string)($detail['alipay_order_no'] ?? $detail['trade_no'] ?? ''));
        $amount = $detail['trans_amount'] ?? $detail['amount'] ?? null;
        if ($transactionId === '' || !is_numeric($amount)) {
            return null;
        }

        $paidAt = trim((string)($detail['trans_dt'] ?? $detail['gmt_payment'] ?? ''));
        $paidTimestamp = $paidAt !== '' ? (strtotime($paidAt) ?: 0) : 0;

        return [
            'transaction_id' => $transactionId,
            'amount' => number_format((float)$amount, 2, '.', ''),
            'memo' => trim((string)($detail['trans_memo'] ?? $detail['memo'] ?? '')),
            'direction' => trim((string)($detail['direction'] ?? '')),
            'paid_at' => $paidAt,
            'paid_timestamp' => $paidTimestamp,
        ];
    }

    private function isIncome(string $direction): bool
    {
        $normalized = strtoupper(trim($direction));

        return $direction === self::INCOME_LABEL || in_array($normalized, ['IN', 'INCOME', 'CREDIT'], true);
    }

    private function normalizeMemo(string $memo): string
    {
        $normalized = trim($memo);
        if (str_starts_with($normalized, self::ORDER_MEMO_PREFIX)) {
            return trim(substr($normalized, strlen(self::ORDER_MEMO_PREFIX)));
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $transaction
     * @return array<string, mixed>
     */
    private function paidMatch(array $transaction, string $matchMode): array
    {
        return [
            'status' => 'paid',
            'reason' => 'matched_' . $matchMode,
            'match_mode' => $matchMode,
            'transaction_count' => 1,
            'transaction' => $transaction,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingMatch(
        string $reason,
        int $transactionCount,
        int $amountCandidateCount,
        int $memoCandidateCount = 0
    ): array {
        return [
            'status' => 'pending',
            'reason' => $reason,
            'transaction_count' => $transactionCount,
            'amount_candidate_count' => $amountCandidateCount,
            'memo_candidate_count' => $memoCandidateCount,
        ];
    }

    private function registerSdk(): void
    {
        if (self::$sdkRegistered) {
            return;
        }

        $sdkRoot = base_path() . DIRECTORY_SEPARATOR . 'support' . DIRECTORY_SEPARATOR . 'legacy-sdk' . DIRECTORY_SEPARATOR . 'alipay';
        foreach (['AopClient.php', 'AlipayDataBillAccountlogQueryRequest.php'] as $requiredFile) {
            if (!is_file($sdkRoot . DIRECTORY_SEPARATOR . $requiredFile)) {
                throw new RuntimeException('支付宝 SDK 文件缺失：' . $requiredFile);
            }
        }

        spl_autoload_register(static function (string $class) use ($sdkRoot): void {
            if ($class === '' || str_contains($class, '\\')) {
                return;
            }

            $file = $sdkRoot . DIRECTORY_SEPARATOR . $class . '.php';
            if (!is_file($file)) {
                return;
            }

            $workingDirectory = getcwd();
            chdir($sdkRoot);
            try {
                require_once $file;
            } finally {
                if (is_string($workingDirectory) && $workingDirectory !== '') {
                    chdir($workingDirectory);
                }
            }
        });

        self::$sdkRegistered = true;
    }
}
