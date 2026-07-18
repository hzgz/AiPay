<?php

namespace app\controller;

use app\controller\concerns\AdminControllerFormatSupport;
use DomainException;
use InvalidArgumentException;
use app\support\AdminMoneyLogFormatter;
use app\support\AdminRouteAuthorization;
use app\support\ApiResponse;
use app\support\BusinessTable;
use app\support\RequestPayload;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class MoneyLogController
{
    use AdminControllerFormatSupport;

    private const MANUAL_CREDIT_MEMO = '后台充值余额';
    private const MANUAL_DEBIT_MEMO = '后台扣除余额';

    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $query = $this->moneyLogQuery();
        $this->applyFilters($query, $request);

        $summary = $this->summary(clone $query);
        $total = (int)(clone $query)->count('money_log.id');
        $rows = $query
            ->orderByDesc('money_log.id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        $records = array_map(
            static fn ($row): array => AdminMoneyLogFormatter::format((array)$row),
            $rows
        );

        return ApiResponse::success([
            'records' => $records,
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => $summary,
        ]);
    }

    public function show(Request $request): Response
    {
        $id = $this->logIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('money log id is required', 422, null, 422);
        }

        $row = $this->moneyLogQuery()
            ->where('money_log.id', $id)
            ->first();

        if (!$row) {
            return ApiResponse::error('money log not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminMoneyLogFormatter::format((array)$row),
        ]);
    }

    public function create(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'add');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $payload = $this->normalizeCreatePayload(RequestPayload::all($request));
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $createdLogId = 0;
        $balanceBefore = 0.0;
        $balanceAfter = 0.0;

        try {
            Db::transaction(function () use ($payload, &$createdLogId, &$balanceBefore, &$balanceAfter): void {
                $merchant = $this->loadAdjustmentMerchant($payload['user_id']);

                $balanceBefore = $this->normalizeAccountBalance($merchant['money'] ?? 0);
                $balanceAfter = $this->applySignedAmount($balanceBefore, $payload['signed_amount']);

                if ($balanceAfter < 0) {
                    throw new DomainException('merchant balance cannot go below 0');
                }

                Db::table(BusinessTable::user())
                    ->where('id', $payload['user_id'])
                    ->update([
                        'money' => $this->formatAccountMoney($balanceAfter),
                    ]);

                $createdLogId = (int)Db::table('money_log')->insertGetId([
                    'user_id' => $payload['user_id'],
                    'type' => 1,
                    'money' => $this->formatLogMoney($payload['signed_amount']),
                    'beforemoney' => $this->formatLogMoney($balanceBefore),
                    'after' => $this->formatLogMoney($balanceAfter),
                    'create_time' => date('Y-m-d H:i:s'),
                    'memo' => $payload['memo'],
                ]);
            });
        } catch (InvalidArgumentException | DomainException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $createdRow = $this->moneyLogQuery()
            ->where('money_log.id', $createdLogId)
            ->first();

        if (!$createdRow) {
            return ApiResponse::error('created money log could not be loaded', 500, null, 500);
        }

        $record = AdminMoneyLogFormatter::format((array)$createdRow);
        $this->recordAdminBalanceAdjustment(
            $request,
            $record,
            $payload['direction'],
            $payload['operator_note']
        );

        return ApiResponse::success([
            'item' => $record,
            'created_log_id' => $createdLogId,
            'merchant_id' => (int)($record['user_id'] ?? 0),
            'merchant_display' => (string)($record['merchant_display'] ?? ''),
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'applied_amount' => (float)($record['money'] ?? 0),
            'applied_amount_display' => (string)($record['money_display'] ?? ''),
            'operator_note' => $payload['operator_note'] !== '' ? $payload['operator_note'] : null,
        ], 'money log adjustment created');
    }

    private function moneyLogQuery(): Builder
    {
        return Db::table('money_log')
            ->leftJoin(BusinessTable::user('merchant'), 'money_log.user_id', '=', 'merchant.id')
            ->select(
                'money_log.id',
                'money_log.user_id',
                'money_log.type',
                'money_log.money',
                'money_log.beforemoney',
                'money_log.after',
                'money_log.create_time',
                'money_log.memo',
                'merchant.username as merchant_username',
                'merchant.name as merchant_name'
            );
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword) {
                $builder
                    ->where('money_log.memo', 'like', '%' . $keyword . '%')
                    ->orWhere('merchant.username', 'like', '%' . $keyword . '%')
                    ->orWhere('merchant.name', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder
                        ->orWhere('money_log.id', (int)$keyword)
                        ->orWhere('money_log.user_id', (int)$keyword);
                }
            });
        }

        $userId = trim((string)$request->get('user_id', ''));
        if ($userId !== '') {
            $query->where('money_log.user_id', 'like', '%' . $userId . '%');
        }

        $direction = strtolower(trim((string)$request->get('direction', '')));
        if ($direction === 'income') {
            $query->where('money_log.money', '>=', 0);
        }
        if ($direction === 'expense') {
            $query->where('money_log.money', '<', 0);
        }

        $memo = trim((string)$request->get('memo', ''));
        if ($memo !== '') {
            $query->where('money_log.memo', 'like', '%' . $memo . '%');
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', ''));
        $endDate = $this->normalizeDate((string)$request->get('end_date', ''));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('money_log.create_time', '>=', $startDate . ' 00:00:00')
                ->where('money_log.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
        }
    }

    private function logIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function summary(Builder $query): array
    {
        $incomeQuery = clone $query;
        $expenseQuery = clone $query;

        $incomeAmount = AdminMoneyLogFormatter::toFloat(
            $incomeQuery->where('money_log.money', '>=', 0)->sum('money_log.money')
        );
        $expenseAmount = AdminMoneyLogFormatter::toFloat(
            $expenseQuery->where('money_log.money', '<', 0)->sum('money_log.money')
        );

        return [
            'income_count' => (int)(clone $query)->where('money_log.money', '>=', 0)->count('money_log.id'),
            'expense_count' => (int)(clone $query)->where('money_log.money', '<', 0)->count('money_log.id'),
            'income_amount' => $incomeAmount,
            'expense_amount' => $expenseAmount,
            'net_amount' => AdminMoneyLogFormatter::toFloat($incomeAmount + $expenseAmount),
        ];
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'FinanceMoneyLogs', $authMark);
    }

    /**
     * @return array{
     *     user_id: int,
     *     direction: string,
     *     amount: float,
     *     signed_amount: float,
     *     memo: string,
     *     operator_note: string
     * }
     */
    private function normalizeCreatePayload(array $payload): array
    {
        $userId = (int)($payload['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new InvalidArgumentException('user_id is required');
        }

        $direction = strtolower(trim((string)($payload['direction'] ?? 'income')));
        if (!in_array($direction, ['income', 'expense'], true)) {
            throw new InvalidArgumentException('direction must be income or expense');
        }

        $amount = $this->normalizePositiveAmount($payload['amount'] ?? null);
        $operatorNote = $this->normalizeOperatorNote($payload['memo'] ?? $payload['note'] ?? null);
        $signedAmount = $direction === 'expense' ? -$amount : $amount;

        return [
            'user_id' => $userId,
            'direction' => $direction,
            'amount' => $amount,
            'signed_amount' => $signedAmount,
            'memo' => $this->buildManualMemo($direction, $operatorNote),
            'operator_note' => $operatorNote,
        ];
    }

    private function normalizePositiveAmount(mixed $value): float
    {
        $string = trim((string)$value);
        if ($string === '') {
            throw new InvalidArgumentException('amount is required');
        }

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $string)) {
            throw new InvalidArgumentException('amount must be a positive number with up to 2 decimals');
        }

        $amount = round((float)$string, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('amount must be greater than 0');
        }

        return $amount;
    }

    private function normalizeOperatorNote(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $string = preg_replace('/\s+/u', ' ', trim((string)$value));
        $string = trim((string)$string);
        if ($string === '') {
            return '';
        }

        if ($this->stringLength($string) > 32) {
            throw new InvalidArgumentException('memo must be 32 characters or fewer');
        }

        return $string;
    }

    private function buildManualMemo(string $direction, string $operatorNote): string
    {
        $base = $direction === 'expense'
            ? self::MANUAL_DEBIT_MEMO
            : self::MANUAL_CREDIT_MEMO;

        if ($operatorNote === '') {
            return $base;
        }

        return $this->truncateText($base . ' - ' . $operatorNote, 50);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadAdjustmentMerchant(int $userId): array
    {
        $row = Db::table(BusinessTable::user())
            ->select('id', 'username', 'name', 'money')
            ->where('id', $userId)
            ->lockForUpdate()
            ->first();

        if (!$row) {
            throw new InvalidArgumentException('merchant not found');
        }

        return (array)$row;
    }

    private function normalizeAccountBalance(mixed $value): float
    {
        return round((float)$value, 2);
    }

    private function applySignedAmount(float $balance, float $signedAmount): float
    {
        if (function_exists('bcadd')) {
            $result = (float)bcadd(
                $this->formatAccountMoney($balance),
                $this->formatAccountMoney($signedAmount),
                2
            );
        } else {
            $result = round($balance + $signedAmount, 2);
        }

        if (abs($result) < 0.005) {
            return 0.0;
        }

        return $result;
    }

    private function formatAccountMoney(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function formatLogMoney(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function recordAdminBalanceAdjustment(
        Request $request,
        array $record,
        string $direction,
        string $operatorNote
    ): void {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $merchantId = (int)($record['user_id'] ?? 0);
        $merchantUsername = $this->truncateText((string)($record['merchant_username'] ?? ''), 80);
        $merchantLabel = $this->truncateText((string)($record['merchant_display'] ?? ('#' . $merchantId)), 120);
        $note = $operatorNote !== '' ? $operatorNote : (string)($record['memo_label'] ?? '');

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/money-logs/create',
            'desc' => sprintf(
                'manual balance adjustment merchant_id=%d username="%s" direction=%s amount=%s before=%s after=%s merchant="%s" note="%s"',
                $merchantId,
                $merchantUsername,
                $direction,
                $this->truncateText((string)($record['money_display'] ?? ''), 32),
                $this->truncateText($this->formatAccountMoney((float)($record['before_money'] ?? 0)), 32),
                $this->truncateText($this->formatAccountMoney((float)($record['after_money'] ?? 0)), 32),
                $merchantLabel,
                $this->truncateText($note, 120)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function truncateText(string $value, int $maxLength): string
    {
        if ($maxLength <= 0) {
            return '';
        }

        if ($this->stringLength($value) <= $maxLength) {
            return $value;
        }

        if (function_exists('mb_substr')) {
            return (string)mb_substr($value, 0, $maxLength);
        }

        return substr($value, 0, $maxLength);
    }

    private function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int)mb_strlen($value);
        }

        return strlen($value);
    }
}
