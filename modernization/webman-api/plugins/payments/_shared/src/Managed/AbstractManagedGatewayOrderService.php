<?php

declare(strict_types=1);

namespace Plugins\Payments\Shared\Managed;

use app\support\BusinessTable;
use app\support\SystemConfig;
use Plugins\Payments\Shared\EpayProtocol\EpayMerchantRepository;
use Plugins\Payments\Shared\EpayProtocol\EpayOrderRepository;
use Plugins\Payments\Shared\EpayProtocol\EpayProtocolService;
use Plugins\Payments\Shared\Support\LegacyTradeNumber;
use Plugins\Payments\Shared\Support\PaymentPluginException;
use support\Db;

abstract class AbstractManagedGatewayOrderService
{
    public function __construct(
        protected readonly EpayMerchantRepository $merchants = new EpayMerchantRepository(),
        protected readonly EpayOrderRepository $orders = new EpayOrderRepository(),
        protected readonly EpayProtocolService $epayProtocol = new EpayProtocolService()
    ) {
    }

    abstract protected function pluginCode(): string;

    abstract protected function pluginName(): string;

    abstract protected function paymentType(): string;

    /**
     * @param array<string, mixed> $merchant
     * @param array<string, mixed> $account
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    abstract protected function createGatewayOrder(array $merchant, array $account, array $payload, array $order): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload): array
    {
        $cleanPayload = $this->sanitizePayload($payload);
        $entry = strtolower(trim((string)($cleanPayload['_entry'] ?? 'submit')));

        $this->validateRequiredFields($cleanPayload, [
            'pid',
            'out_trade_no',
            'type',
            'name',
            'money',
            'notify_url',
            'return_url',
        ]);

        $merchant = $this->merchants->findMerchant((int)$cleanPayload['pid']);
        if (!$this->epayProtocol->verifySignature($cleanPayload, (string)($merchant['user_key'] ?? ''))) {
            throw PaymentPluginException::unauthorized();
        }

        $systemConfig = SystemConfig::all();
        $paymentType = $this->normalizePaymentType((string)$cleanPayload['type']);

        if ($paymentType !== $this->paymentType()) {
            throw PaymentPluginException::validation(
                sprintf('%s 仅支持 %s 订单', $this->pluginName(), $this->paymentType())
            );
        }

        $this->assertVipActive($merchant);
        $this->assertMoney((string)$cleanPayload['money']);
        $this->assertOrderName((string)($cleanPayload['name'] ?? ''), $entry, $systemConfig);
        $this->assertMoneyRange((float)$cleanPayload['money'], $systemConfig);
        $this->assertMerchantBalance($merchant, (float)$cleanPayload['money'], $systemConfig);
        $this->orders->assertRequestCanCreate($cleanPayload);

        $basicSettings = $this->merchants->findBasicSettings((int)$merchant['id']);
        $basicSettings['system_timeout'] = SystemConfig::int('timeout', 180);
        $cleanPayload['_trade_no'] = $this->resolveTradeNo($systemConfig);

        $account = $this->resolveAccount($merchant, $cleanPayload, $paymentType);
        $order = $this->createLocalOrder($merchant, $account, $cleanPayload, $paymentType, $basicSettings);
        $gatewayResult = $this->createGatewayOrder($merchant, $account, $cleanPayload, $order);
        $order = $this->applyGatewayResult($order, $gatewayResult);
        $cashierUrl = $this->cashierUrl($cleanPayload, (string)($order['trade_no'] ?? ''));

        return [
            'plugin' => $this->pluginCode(),
            'entry' => $entry,
            'status' => 'created',
            'merchant' => $this->publicMerchant($merchant),
            'account' => $this->publicAccount($account),
            'order' => $this->publicOrder($order),
            'cashier_url' => $cashierUrl,
            'gateway_result' => $this->publicGatewayResult($gatewayResult),
            'form_html' => $this->buildRedirectHtml($cashierUrl),
            'legacy_api_response' => $this->buildLegacyApiResponse(
                $entry,
                $order,
                $cashierUrl,
                $gatewayResult
            ),
        ];
    }

    /**
     * 将官方网关返回结果挂载到已经创建好的本地订单。
     *
     * @param array<string, mixed> $merchant
     * @param array<string, mixed> $account
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    public function attachGatewayToOrder(array $merchant, array $account, array $payload, array $order): array
    {
        $cleanPayload = $this->sanitizePayload($payload);
        $gatewayResult = $this->createGatewayOrder($merchant, $account, $cleanPayload, $order);
        $updatedOrder = $this->applyGatewayResult($order, $gatewayResult);

        return [
            'plugin' => $this->pluginCode(),
            'merchant' => $this->publicMerchant($merchant),
            'account' => $this->publicAccount($account),
            'order' => $this->publicOrder($updatedOrder),
            'cashier_url' => $this->cashierUrl(
                $cleanPayload,
                (string)($updatedOrder['trade_no'] ?? $order['trade_no'] ?? '')
            ),
            'gateway_result' => $this->publicGatewayResult($gatewayResult),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function sanitizePayload(array $payload): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            if (!is_string($key) && !is_int($key)) {
                continue;
            }

            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $clean[(string)$key] = is_string($value) ? trim($value) : $value;
        }

        return $clean;
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function validateRequiredFields(array $payload, array $fields): void
    {
        foreach ($fields as $field) {
            $value = trim((string)($payload[$field] ?? ''));
            if ($value === '') {
                throw PaymentPluginException::validation('缺少必填字段: ' . $field);
            }
        }
    }

    protected function assertMoney(string $value): void
    {
        if (!is_numeric($value)) {
            throw PaymentPluginException::validation('金额必须为数字');
        }

        if ((float)$value <= 0) {
            throw PaymentPluginException::validation('金额必须大于 0');
        }
    }

    /**
     * @param array<string, mixed> $systemConfig
     */
    protected function assertMoneyRange(float $money, array $systemConfig): void
    {
        $min = isset($systemConfig['min_orderprice']) && is_numeric($systemConfig['min_orderprice'])
            ? (float)$systemConfig['min_orderprice']
            : 0.0;
        $max = isset($systemConfig['max_orderprice']) && is_numeric($systemConfig['max_orderprice'])
            ? (float)$systemConfig['max_orderprice']
            : 0.0;

        if ($money < $min) {
            throw PaymentPluginException::validation('金额低于系统最小限额');
        }

        if ($max > 0 && $money > $max) {
            throw PaymentPluginException::validation('金额高于系统最大限额');
        }
    }

    /**
     * @param array<string, mixed> $systemConfig
     */
    protected function assertOrderName(string $name, string $entry, array $systemConfig): void
    {
        if ($entry === 'submit' && str_contains($name, '=')) {
            throw PaymentPluginException::validation('商品名称包含非法字符');
        }

        $shieldKey = trim((string)($systemConfig['shield_key'] ?? ''));
        if ($shieldKey === '') {
            return;
        }

        foreach (explode('|', $shieldKey) as $keyword) {
            $keyword = trim($keyword);
            if ($keyword !== '' && str_contains($name, $keyword)) {
                $message = trim((string)($systemConfig['shield_tips'] ?? '商品存在风控风险'));
                throw PaymentPluginException::validation($message !== '' ? $message : '商品名称触发风控关键词');
            }
        }
    }

    /**
     * @param array<string, mixed> $merchant
     * @param array<string, mixed> $systemConfig
     */
    protected function assertMerchantBalance(array $merchant, float $money, array $systemConfig): void
    {
        $fee = round($money * (float)($merchant['feilv'] ?? 0) / 100, 3);
        $balance = round((float)($merchant['money'] ?? 0), 3);
        $allowZeroBalance = (string)($systemConfig['is_pay_money'] ?? '1') === '1';

        if (!$allowZeroBalance && $balance <= 0) {
            throw PaymentPluginException::conflict('商户余额不足');
        }

        if ($balance < $fee) {
            throw PaymentPluginException::conflict('商户余额不足');
        }
    }

    /**
     * @param array<string, mixed> $merchant
     */
    protected function assertVipActive(array $merchant): void
    {
        $vipTime = trim((string)($merchant['vip_time'] ?? ''));
        if ($vipTime === '') {
            throw PaymentPluginException::conflict('商户套餐不存在');
        }

        $timestamp = strtotime($vipTime);
        if ($timestamp === false || $timestamp < time()) {
            throw PaymentPluginException::conflict('商户套餐已过期');
        }
    }

    protected function normalizePaymentType(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'alipay', 'alipay_official', 'alipay_bill', 'alipay_mck' => 'alipay',
            'wxpay', 'wxpay_v3' => 'wxpay',
            'qqpay' => 'qqpay',
            default => $normalized,
        };
    }

    /**
     * @param array<string, mixed> $merchant
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function resolveAccount(array $merchant, array $payload, string $paymentType): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $explicitAccountId = (int)($payload['account_id'] ?? ($payload['channel_id'] ?? 0));

        if ($explicitAccountId > 0) {
            return $this->loadBoundAccount($explicitAccountId, $merchantId, $paymentType);
        }

        $poolId = (int)($payload['pool_id'] ?? ($payload['poll_id'] ?? 0));
        if ($poolId > 0) {
            $pooledAccount = $this->selectAccountFromPool($poolId, $merchantId, $paymentType);
            if ($pooledAccount !== null) {
                return $pooledAccount;
            }
        }

        $row = Db::table(BusinessTable::account())
            ->select(
                'id',
                'user_id',
                'code',
                'type',
                'qr_url',
                'qr_type',
                'wxname',
                'zfb_pid',
                'wx_guid',
                'cloud_id',
                'qq',
                'status',
                'is_status',
                'memo',
                'cookie',
                'remark'
            )
            ->where('user_id', $merchantId)
            ->where('type', $paymentType)
            ->where('code', $this->pluginCode())
            ->where('status', 1)
            ->where('is_status', 1)
            ->orderByDesc('id')
            ->first();

        if (!$row) {
            throw PaymentPluginException::conflict(
                sprintf('%s 没有可用的收款账号', $this->pluginName())
            );
        }

        $account = (array)$row;
        $account['_selected_via'] = 'latest_active_account';

        return $account;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function selectAccountFromPool(int $poolId, int $merchantId, string $paymentType): ?array
    {
        if ($poolId <= 0 || $merchantId <= 0) {
            return null;
        }

        return Db::transaction(function () use ($poolId, $merchantId, $paymentType): ?array {
            $pool = Db::table(BusinessTable::pollPool())
                ->select('id', 'user_id', 'type', 'status', 'round_type', 'current_index', 'current_weight', 'last_account_id')
                ->where('id', $poolId)
                ->where('user_id', $merchantId)
                ->lockForUpdate()
                ->first();

            if (!$pool) {
                throw PaymentPluginException::notFound('轮询池不存在');
            }

            $poolRecord = (array)$pool;
            if ((int)($poolRecord['status'] ?? 0) !== 1) {
                throw PaymentPluginException::conflict('轮询池已停用');
            }

            if (trim((string)($poolRecord['type'] ?? '')) !== $paymentType) {
                throw PaymentPluginException::validation('轮询池类型与订单类型不匹配');
            }

            $rows = Db::table(BusinessTable::pollPoolItem('item'))
                ->join(BusinessTable::account('account'), 'account.id', '=', 'item.account_id')
                ->select(
                    'item.account_id',
                    'item.weight',
                    'item.sort',
                    'account.id',
                    'account.user_id',
                    'account.code',
                    'account.type',
                    'account.qr_url',
                    'account.qr_type',
                    'account.wxname',
                    'account.zfb_pid',
                    'account.wx_guid',
                    'account.cloud_id',
                    'account.qq',
                    'account.status',
                    'account.is_status',
                    'account.memo',
                    'account.cookie',
                    'account.remark'
                )
                ->where('item.pool_id', $poolId)
                ->where('account.user_id', $merchantId)
                ->where('account.type', $paymentType)
                ->where('account.code', $this->pluginCode())
                ->where('account.status', 1)
                ->where('account.is_status', 1)
                ->orderBy('item.sort')
                ->orderByDesc('account.id')
                ->get()
                ->toArray();

            if ($rows === []) {
                throw PaymentPluginException::conflict(
                    sprintf('%s 轮询池里没有可用的收款账号', $this->pluginName())
                );
            }

            $accounts = array_map(static fn ($row): array => (array)$row, $rows);
            $selectedIndex = 0;

            if ((int)($poolRecord['round_type'] ?? 1) === 2) {
                $totalWeight = 0;
                foreach ($accounts as $item) {
                    $totalWeight += max(1, (int)($item['weight'] ?? 1));
                }

                $ticket = random_int(1, max(1, $totalWeight));
                foreach ($accounts as $index => $item) {
                    $ticket -= max(1, (int)($item['weight'] ?? 1));
                    if ($ticket <= 0) {
                        $selectedIndex = $index;
                        break;
                    }
                }
            } else {
                $currentIndex = max(0, (int)($poolRecord['current_index'] ?? 0));
                $selectedIndex = $currentIndex % count($accounts);
            }

            $selected = $accounts[$selectedIndex];
            Db::table(BusinessTable::pollPool())
                ->where('id', $poolId)
                ->update([
                    'current_index' => (($selectedIndex + 1) % max(1, count($accounts))),
                    'current_weight' => max(1, (int)($selected['weight'] ?? 1)),
                    'last_account_id' => (int)($selected['id'] ?? 0),
                    'update_time' => date('Y-m-d H:i:s'),
                ]);

            $selected['_selected_via'] = 'payment_pool';
            $selected['_pool_id'] = $poolId;

            return $selected;
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadBoundAccount(int $accountId, int $merchantId, string $paymentType): array
    {
        $row = Db::table(BusinessTable::account())
            ->select(
                'id',
                'user_id',
                'code',
                'type',
                'qr_url',
                'qr_type',
                'wxname',
                'zfb_pid',
                'wx_guid',
                'cloud_id',
                'qq',
                'status',
                'is_status',
                'memo',
                'cookie',
                'remark'
            )
            ->where('id', $accountId)
            ->first();

        if (!$row) {
            throw PaymentPluginException::notFound('收款账号不存在');
        }

        $account = (array)$row;
        if ((int)($account['user_id'] ?? 0) !== $merchantId) {
            throw PaymentPluginException::conflict('收款账号不属于当前商户');
        }

        if (trim((string)($account['type'] ?? '')) !== $paymentType) {
            throw PaymentPluginException::validation('收款账号类型与订单类型不匹配');
        }

        if (trim((string)($account['code'] ?? '')) !== $this->pluginCode()) {
            throw PaymentPluginException::validation('收款账号插件与当前请求不匹配');
        }

        if ((int)($account['status'] ?? 0) !== 1 || (int)($account['is_status'] ?? 0) !== 1) {
            throw PaymentPluginException::conflict('收款账号已停用');
        }

        $account['_selected_via'] = 'explicit_account';

        return $account;
    }

    /**
     * @param array<string, mixed> $merchant
     * @param array<string, mixed> $account
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $basicSettings
     * @return array<string, mixed>
     */
    protected function createLocalOrder(
        array $merchant,
        array $account,
        array $payload,
        string $paymentType,
        array $basicSettings
    ): array {
        $tradeNo = trim((string)($payload['_trade_no'] ?? ''));
        if ($tradeNo === '') {
            $tradeNo = LegacyTradeNumber::make('Y');
        }

        $money = number_format((float)($payload['money'] ?? 0), 2, '.', '');
        $feeRate = (float)($merchant['feilv'] ?? 0);
        $feeMoney = number_format(round(((float)$money) * $feeRate / 100, 3), 3, '.', '');
        $timeoutSeconds = $this->resolveTimeoutSeconds($basicSettings);

        Db::table(BusinessTable::order())->insert([
            'name' => trim((string)($payload['name'] ?? '')),
            'sitename' => trim((string)SystemConfig::get('sitename', 'AiPay')),
            'type' => $paymentType,
            'account_id' => (int)($account['id'] ?? 0),
            'trade_no' => $tradeNo,
            'out_trade_no' => trim((string)($payload['out_trade_no'] ?? '')),
            'user_id' => (int)($merchant['id'] ?? 0),
            'pay_type' => 2,
            'money' => $money,
            'truemoney' => $money,
            'feilvmoney' => $feeMoney,
            'status' => 0,
            'return_num' => 0,
            'notify_url' => trim((string)($payload['notify_url'] ?? '')),
            'return_url' => trim((string)($payload['return_url'] ?? '')),
            'ip' => $this->resolveClientIp($payload),
            'qrcode' => 'ewmLoading',
            'h5_qrurl' => '',
            'api_memo' => $this->buildApiMemo($merchant, $account, $payload, $timeoutSeconds),
            'out_time' => time() + $timeoutSeconds,
            'create_time' => date('Y-m-d H:i:s'),
        ]);

        $order = $this->orders->findByTradeNo($tradeNo);
        if (!$order) {
            throw new \RuntimeException('订单已创建，但重新加载失败');
        }

        return $order;
    }

    /**
     * @param array<string, mixed> $merchant
     * @param array<string, mixed> $account
     * @param array<string, mixed> $payload
     */
    protected function buildApiMemo(array $merchant, array $account, array $payload, int $timeoutSeconds): string
    {
        $selectedVia = trim((string)($account['_selected_via'] ?? 'latest_active_account'));
        $data = [
            'migration' => 'webman_managed_gateway',
            'plugin_code' => $this->pluginCode(),
            'payment_type' => $this->paymentType(),
            'merchant_id' => (int)($merchant['id'] ?? 0),
            'merchant_username' => (string)($merchant['username'] ?? ''),
            'account_id' => (int)($account['id'] ?? 0),
            'account_code' => (string)($account['code'] ?? ''),
            'selected_via' => $selectedVia,
            'pool_id' => (int)($account['_pool_id'] ?? 0),
            'source' => (string)($payload['_entry'] ?? 'submit'),
            'timeout_seconds' => $timeoutSeconds,
            'created_at' => date('c'),
        ];

        foreach (['clientip', 'device', 'param', 'sign_type'] as $field) {
            if (isset($payload[$field]) && trim((string)$payload[$field]) !== '') {
                $data[$field] = (string)$payload[$field];
            }
        }

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '';
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $gatewayResult
     * @return array<string, mixed>
     */
    protected function applyGatewayResult(array $order, array $gatewayResult): array
    {
        $qrcode = trim((string)($gatewayResult['qrcode'] ?? ''));
        $h5QrUrl = trim((string)($gatewayResult['h5_qrurl'] ?? ''));

        if ($qrcode === '' && $h5QrUrl !== '') {
            $qrcode = $h5QrUrl;
        }

        if ($qrcode === '') {
            throw new \RuntimeException($this->pluginName() . ' 未返回有效的支付地址');
        }

        $updates = [
            'qrcode' => $qrcode,
            'h5_qrurl' => $h5QrUrl,
        ];

        $gatewayTradeNo = trim((string)($gatewayResult['gateway_trade_no'] ?? ''));
        if ($gatewayTradeNo !== '') {
            $updates['alipay_order_no'] = $gatewayTradeNo;
        }

        Db::table(BusinessTable::order())
            ->where('id', (int)($order['id'] ?? 0))
            ->update($updates);

        $reloaded = $this->orders->findById((int)($order['id'] ?? 0));
        if (!$reloaded) {
            throw new \RuntimeException('订单网关字段已更新，但重新加载失败');
        }

        return $reloaded;
    }

    protected function resolveTimeoutSeconds(array $basicSettings): int
    {
        $timeout = (int)($basicSettings['timeout_time'] ?? 180);
        if ($timeout <= 0) {
            $timeout = 180;
        }

        $systemTimeout = (int)($basicSettings['system_timeout'] ?? 0);
        if ($systemTimeout > 0 && $timeout > $systemTimeout) {
            $timeout = $systemTimeout;
        }

        return max(60, $timeout);
    }

    /**
     * @param array<string, mixed> $systemConfig
     */
    protected function resolveTradeNo(array $systemConfig): string
    {
        $prefix = (string)($systemConfig['isDiy_orderNo'] ?? '0') === '1'
            ? trim((string)($systemConfig['diy_orderNo'] ?? ''))
            : 'Y';

        if ($prefix === '') {
            $prefix = 'Y';
        }

        return LegacyTradeNumber::make($prefix);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function resolveOrigin(array $payload): string
    {
        $origin = trim((string)($payload['_origin'] ?? ''));
        if ($origin !== '') {
            return rtrim($origin, '/');
        }

        $scheme = trim((string)($payload['_request_scheme'] ?? 'http'));
        $host = trim((string)($payload['_request_host'] ?? '127.0.0.1:8787'));
        if ($host === '') {
            $host = '127.0.0.1:8787';
        }

        return rtrim($scheme . '://' . $host, '/');
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function resolveClientIp(array $payload): string
    {
        $clientIp = trim((string)($payload['clientip'] ?? ''));
        if ($clientIp !== '') {
            return $clientIp;
        }

        return trim((string)($payload['_client_ip'] ?? ''));
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function cashierUrl(array $payload, string $tradeNo): string
    {
        return $this->resolveOrigin($payload) . '/Pay/console?trade_no=' . rawurlencode($tradeNo);
    }

    protected function buildRedirectHtml(string $cashierUrl): string
    {
        $escapedUrl = htmlspecialchars($cashierUrl, ENT_QUOTES, 'UTF-8');

        return '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url='
            . $escapedUrl
            . '"><title>正在跳转收银台</title></head><body><script>location.replace('
            . json_encode($cashierUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . ');</script><p>正在跳转收银台，如未自动跳转，请 <a href="'
            . $escapedUrl
            . '">点击这里</a>。</p></body></html>';
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $gatewayResult
     * @return array<string, mixed>
     */
    protected function buildLegacyApiResponse(
        string $entry,
        array $order,
        string $cashierUrl,
        array $gatewayResult
    ): array {
        $payload = [
            'trade_no' => (string)($order['trade_no'] ?? ''),
            'out_trade_no' => (string)($order['out_trade_no'] ?? ''),
            'type' => (string)($order['type'] ?? ''),
            'money' => (string)($order['truemoney'] ?? $order['money'] ?? '0.00'),
            'qrcode' => (string)($order['qrcode'] ?? ''),
            'code_url' => (string)($order['qrcode'] ?? ''),
            'payurl' => $cashierUrl,
            'cashier_url' => $cashierUrl,
            'h5_qrurl' => (string)($order['h5_qrurl'] ?? ''),
        ];

        foreach ($gatewayResult as $key => $value) {
            if (in_array($key, ['qrcode', 'h5_qrurl', 'gateway_trade_no', 'raw_response'], true)) {
                continue;
            }

            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $payload[(string)$key] = $value;
        }

        if ($entry === 'mapi') {
            return array_merge([
                'code' => 1,
                'msg' => '获取成功!',
            ], $payload);
        }

        return array_merge([
            'code' => 200,
            'msg' => '获取成功!',
        ], $payload);
    }

    /**
     * @param array<string, mixed> $merchant
     * @return array<string, mixed>
     */
    protected function publicMerchant(array $merchant): array
    {
        return [
            'id' => (int)($merchant['id'] ?? 0),
            'username' => (string)($merchant['username'] ?? ''),
            'money' => (string)($merchant['money'] ?? '0.000'),
            'vip_id' => $merchant['vip_id'] ?? null,
            'vip_time' => $merchant['vip_time'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    protected function publicAccount(array $account): array
    {
        return [
            'id' => (int)($account['id'] ?? 0),
            'user_id' => (int)($account['user_id'] ?? 0),
            'code' => (string)($account['code'] ?? ''),
            'type' => (string)($account['type'] ?? ''),
            'memo' => (string)($account['memo'] ?? ''),
            'status' => (int)($account['status'] ?? 0),
            'is_status' => (int)($account['is_status'] ?? 0),
            'selected_via' => (string)($account['_selected_via'] ?? 'latest_active_account'),
            'pool_id' => (int)($account['_pool_id'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    protected function publicOrder(array $order): array
    {
        return [
            'id' => (int)($order['id'] ?? 0),
            'trade_no' => (string)($order['trade_no'] ?? ''),
            'out_trade_no' => (string)($order['out_trade_no'] ?? ''),
            'type' => (string)($order['type'] ?? ''),
            'status' => (int)($order['status'] ?? 0),
            'user_id' => (int)($order['user_id'] ?? 0),
            'account_id' => (int)($order['account_id'] ?? 0),
            'money' => (string)($order['money'] ?? '0.00'),
            'truemoney' => (string)($order['truemoney'] ?? '0.00'),
            'notify_url' => (string)($order['notify_url'] ?? ''),
            'return_url' => (string)($order['return_url'] ?? ''),
            'qrcode' => (string)($order['qrcode'] ?? ''),
            'h5_qrurl' => (string)($order['h5_qrurl'] ?? ''),
            'create_time' => (string)($order['create_time'] ?? ''),
            'end_time' => (string)($order['end_time'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $gatewayResult
     * @return array<string, mixed>
     */
    protected function publicGatewayResult(array $gatewayResult): array
    {
        $public = [];

        foreach ($gatewayResult as $key => $value) {
            if ($key === 'raw_response') {
                continue;
            }

            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $public[(string)$key] = $value;
        }

        return $public;
    }
}
