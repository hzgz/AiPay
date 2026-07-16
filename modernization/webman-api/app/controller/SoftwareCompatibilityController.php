<?php

declare(strict_types=1);

namespace app\controller;

use app\support\ApiResponse;
use app\service\order\OrderCallbackTaskService;
use app\support\SystemConfig;
use Plugins\Payments\Shared\Legacy\LegacyEpayOrderRepository;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

use function runtime_path;

class SoftwareCompatibilityController
{
    public function verify(Request $request): Response
    {
        [$merchant, $errorResponse] = $this->softwareMerchant($request);
        if ($errorResponse instanceof Response) {
            return $errorResponse;
        }

        if ($signatureError = $this->assertSoftwareSignature($request, $merchant, 'key', false)) {
            return $signatureError;
        }

        return $this->monitorResponse(200, '验证成功', [
            'merchant_id' => (int)($merchant['id'] ?? 0),
            'merchant_username' => trim((string)($merchant['username'] ?? '')),
        ], [
            'security' => $this->softwareSecurityContext($request, 'key', false),
        ]);
    }

    public function checkOrder(Request $request): Response
    {
        [$merchant, $errorResponse] = $this->softwareMerchant($request);
        if ($errorResponse instanceof Response) {
            return $errorResponse;
        }

        if ($signatureError = $this->assertSoftwareSignature($request, $merchant, 'key', false)) {
            return $signatureError;
        }

        $security = $this->softwareSecurityContext($request, 'key', false);

        [$account, $errorResponse] = $this->softwareAccount($request, (int)($merchant['id'] ?? 0));
        if ($errorResponse instanceof Response) {
            return $errorResponse;
        }

        $orders = Db::table('ypay_order')
            ->select(
                'id',
                'name',
                'type',
                'money',
                'truemoney',
                'account_id',
                'trade_no',
                'out_trade_no',
                'status',
                'out_time'
            )
            ->where('account_id', (int)($account['id'] ?? 0))
            ->where('status', 0)
            ->where('out_time', '>', time())
            ->orderByDesc('id')
            ->get()
            ->toArray();

        if ($orders === []) {
            return $this->monitorResponse(201, '当前通道暂无待支付订单');
        }

        return $this->monitorResponse(200, '查询成功', [
            'orders' => array_map(static function ($row): array {
                $item = (array)$row;

                return [
                    'id' => (int)($item['id'] ?? 0),
                    'name' => trim((string)($item['name'] ?? '')),
                    'type' => trim((string)($item['type'] ?? '')),
                    'money' => (string)($item['money'] ?? '0.00'),
                    'truemoney' => (string)($item['truemoney'] ?? '0.00'),
                    'account_id' => (int)($item['account_id'] ?? 0),
                    'trade_no' => trim((string)($item['trade_no'] ?? '')),
                    'out_trade_no' => trim((string)($item['out_trade_no'] ?? '')),
                    'status' => (int)($item['status'] ?? 0),
                    'out_time' => (int)($item['out_time'] ?? 0),
                ];
            }, $orders),
            'summary' => [
                'pending_count' => count($orders),
                'account_id' => (int)($account['id'] ?? 0),
            ],
        ], [
            'security' => $security,
        ]);
    }

    public function heartbeat(Request $request): Response
    {
        [$merchant, $errorResponse] = $this->softwareMerchant($request);
        if ($errorResponse instanceof Response) {
            return $errorResponse;
        }

        if ($signatureError = $this->assertSoftwareSignature($request, $merchant, 'key', true)) {
            return $signatureError;
        }

        $security = $this->softwareSecurityContext($request, 'key', true);

        [$account, $errorResponse] = $this->softwareAccount($request, (int)($merchant['id'] ?? 0));
        if ($errorResponse instanceof Response) {
            return $errorResponse;
        }

        $status = $this->normalizeStatus($request->input('status', null), (int)($account['status'] ?? 0));
        $update = ['status' => $status];

        $identifier = trim((string)$request->input('tempParam', $request->input('pid', '')));
        if (
            $identifier !== ''
            && strtolower(trim((string)$request->input('mode', ''))) === 'agt'
        ) {
            $field = $this->heartbeatIdentifierField($account, trim((string)$request->input('type', '')));
            if ($field !== null) {
                $update[$field] = $identifier;
            }
        }

        Db::table('ypay_account')
            ->where('id', (int)($account['id'] ?? 0))
            ->update($update);

        return $this->monitorResponse(200, '心跳更新成功', [
            'account_id' => (int)($account['id'] ?? 0),
            'status' => $status,
            'updated_fields' => array_keys($update),
        ], [
            'security' => $security,
        ]);
    }

    public function pcNotify(Request $request): Response
    {
        [$merchant, $errorResponse] = $this->softwareMerchant($request);
        if ($errorResponse instanceof Response) {
            return $errorResponse;
        }

        if ($signatureError = $this->assertSoftwareSignature($request, $merchant, 'key', true)) {
            return $signatureError;
        }

        $security = $this->softwareSecurityContext($request, 'key', true);

        [$account, $errorResponse] = $this->softwareAccount($request, (int)($merchant['id'] ?? 0));
        if ($errorResponse instanceof Response) {
            return $errorResponse;
        }

        $money = $this->normalizeMoney($request->input('money', ''));
        if ($money === null) {
            return $this->monitorResponse(201, '订单金额不正确');
        }

        $type = $this->normalizePaymentType(trim((string)$request->input('type', '')));
        $orderNo = trim((string)$request->input('orderNo', $request->input('out_trade_no', '')));

        try {
            $order = $this->findPendingSoftwareOrder(
                (int)($merchant['id'] ?? 0),
                (int)($account['id'] ?? 0),
                $money,
                $type,
                $orderNo
            );
        } catch (\RuntimeException $exception) {
            return $this->monitorResponse(201, $exception->getMessage());
        }
        if ($order === null) {
            $processedOrder = $this->findProcessedSoftwareOrder(
                (int)($merchant['id'] ?? 0),
                (int)($account['id'] ?? 0),
                $money,
                $type,
                $orderNo
            );
            if ($processedOrder !== null) {
                return $this->monitorResponse(200, '订单已处理', $this->processedSoftwareOrderPayload($processedOrder));
            }
            return $this->monitorResponse(201, '订单超时或不存在');
        }

        try {
            $callback = $this->settleAndNotifyMerchant($order, $merchant, $this->callbackPayload($request, $money));
            $callback['security'] = $security;
        } catch (\Throwable $exception) {
            return $this->monitorResponse(201, '回调失败: ' . $exception->getMessage());
        }

        return $this->monitorResponse(200, '回调成功', $callback);
    }

    public function appReport(Request $request): Response
    {
        [$merchant, $errorResponse] = $this->softwareReportMerchant($request);
        if ($errorResponse instanceof Response) {
            return $errorResponse;
        }

        if ($signatureError = $this->assertSoftwareSignature($request, $merchant, 'token', true)) {
            return $signatureError;
        }

        $security = $this->softwareSecurityContext($request, 'token', true);

        $content = $this->decodeReportContent((string)$request->input('content', ''));
        if ($content === null) {
            return $this->monitorResponse(201, 'content 解析失败');
        }

        $message = trim((string)($content['msg'] ?? ''));
        $packageName = trim((string)($content['package_name'] ?? ''));
        if ($message === '' || $packageName === '') {
            return $this->monitorResponse(201, 'content 缺少必要字段');
        }

        $channelId = (int)$request->input('channel_id', 0);
        $account = null;
        if ($channelId > 0) {
            $account = Db::table('ypay_account')
                ->select('id', 'user_id', 'code', 'type', 'status', 'is_status', 'wxname', 'zfb_pid', 'qq')
                ->where('id', $channelId)
                ->where('user_id', (int)($merchant['id'] ?? 0))
                ->first();

            if (!$account) {
                return $this->monitorResponse(201, 'channel_id 无效');
            }
            $account = (array)$account;
        }

        $type = $this->reportPackageType($packageName);
        if ($type === null) {
            return $this->monitorResponse(400, '暂不支持的支付软件');
        }

        $money = $this->extractReportAmount($message, $type);
        if ($money === null) {
            return $this->monitorResponse(201, '未能从软件通知中识别金额');
        }

        try {
            $order = $this->findPendingSoftwareOrder(
                (int)($merchant['id'] ?? 0),
                $channelId,
                $money,
                $type,
                ''
            );
        } catch (\RuntimeException $exception) {
            return $this->monitorResponse(201, $exception->getMessage());
        }
        if ($order === null) {
            $processedOrder = $this->findProcessedSoftwareOrder(
                (int)($merchant['id'] ?? 0),
                $channelId,
                $money,
                $type,
                ''
            );
            if ($processedOrder !== null) {
                return $this->monitorResponse(200, '订单已处理', array_merge(
                    $this->processedSoftwareOrderPayload($processedOrder),
                    [
                        'matched_package' => $packageName,
                        'matched_money' => number_format($money, 2, '.', ''),
                        'channel_id' => $channelId,
                    ]
                ));
            }
            return $this->monitorResponse(201, '订单超时或不存在');
        }

        try {
            $callback = $this->settleAndNotifyMerchant($order, $merchant, [
                'trade_no' => trim((string)($content['trade_no'] ?? '')),
                'transaction_id' => trim((string)($content['transaction_id'] ?? '')),
                'buyer_trade_no' => trim((string)($content['buyer_trade_no'] ?? '')),
                'software_package' => $packageName,
                'software_channel_id' => $channelId,
                'software_message' => $message,
                'money' => number_format($money, 2, '.', ''),
            ]);
            $callback['security'] = $security;
        } catch (\Throwable $exception) {
            return $this->monitorResponse(201, '上报处理失败: ' . $exception->getMessage());
        }

        return $this->monitorResponse(200, '处理成功', array_merge($callback, [
            'matched_package' => $packageName,
            'matched_money' => number_format($money, 2, '.', ''),
            'channel_id' => $channelId,
        ]));
    }

    private function softwareMerchant(Request $request): array
    {
        $merchantId = (int)$request->input('id', 0);
        $appKey = trim((string)$request->input('key', ''));

        if ($merchantId <= 0 || $appKey === '') {
            return [null, $this->monitorResponse(201, '商户编号和通讯密钥不能为空')];
        }

        return $this->findSoftwareMerchant($merchantId, $appKey);
    }

    private function softwareReportMerchant(Request $request): array
    {
        $merchantId = (int)($request->route ? $request->route->param('id', 0) : 0);
        if ($merchantId <= 0) {
            $merchantId = (int)$request->input('id', $request->input('user_id', 0));
        }

        $appKey = trim((string)$request->input('token', $request->input('key', '')));
        if ($merchantId <= 0 || $appKey === '') {
            return [null, $this->monitorResponse(201, '商户编号和 token 不能为空')];
        }

        return $this->findSoftwareMerchant($merchantId, $appKey);
    }

    private function findSoftwareMerchant(int $merchantId, string $appKey): array
    {
        $row = Db::table('ypay_userbasic')
            ->leftJoin('ypay_user', 'ypay_userbasic.user_id', '=', 'ypay_user.id')
            ->select(
                'ypay_userbasic.user_id',
                'ypay_userbasic.appkey',
                'ypay_user.id',
                'ypay_user.username',
                'ypay_user.user_key',
                'ypay_user.money',
                'ypay_user.is_frozen',
                'ypay_user.frozen_reason'
            )
            ->where('ypay_userbasic.user_id', $merchantId)
            ->where('ypay_userbasic.appkey', $appKey)
            ->first();

        if (!$row) {
            return [null, $this->monitorResponse(201, '商户不存在或密钥无效')];
        }

        $merchant = (array)$row;
        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            $reason = trim((string)($merchant['frozen_reason'] ?? '商户账户已冻结'));
            return [null, $this->monitorResponse(201, $reason !== '' ? $reason : '商户账户已冻结')];
        }

        return [$merchant, null];
    }

    private function softwareAccount(Request $request, int $merchantId): array
    {
        $channelId = (int)$request->input('channel_id', 0);
        if ($channelId <= 0) {
            return [null, $this->monitorResponse(201, 'channel_id 不能为空')];
        }

        $account = Db::table('ypay_account')
            ->select(
                'id',
                'user_id',
                'code',
                'type',
                'status',
                'is_status',
                'wxname',
                'zfb_pid',
                'qq',
                'qr_type',
                'qr_url'
            )
            ->where('id', $channelId)
            ->where('user_id', $merchantId)
            ->first();

        if (!$account) {
            return [null, $this->monitorResponse(201, '通道不存在或无权限操作')];
        }

        return [(array)$account, null];
    }

    private function normalizeStatus(mixed $value, int $fallback): int
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $stringValue = strtolower(trim((string)$value));
        if (in_array($stringValue, ['1', 'true', 'online', 'success'], true)) {
            return 1;
        }
        if (in_array($stringValue, ['0', 'false', 'offline', 'fail'], true)) {
            return 0;
        }

        return (int)$value;
    }

    private function heartbeatIdentifierField(array $account, string $requestedType): ?string
    {
        $code = strtolower(trim((string)($account['code'] ?? '')));
        $type = $this->normalizePaymentType($requestedType);

        if ($code === 'alipay_software' || $type === 'alipay') {
            return 'zfb_pid';
        }
        if ($code === 'qqpay_software' || $type === 'qqpay') {
            return 'qq';
        }
        if ($code === 'wxpay_software' || $type === 'wxpay') {
            return 'wxname';
        }

        return null;
    }

    private function normalizeMoney(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string)$value);
        if ($stringValue === '' || !is_numeric($stringValue)) {
            return null;
        }

        $money = round((float)$stringValue, 2);

        return $money > 0 ? $money : null;
    }

    private function normalizePaymentType(string $value): string
    {
        $type = strtolower(trim($value));
        if ($type === '') {
            return '';
        }

        if (str_contains($type, 'ali')) {
            return 'alipay';
        }
        if (in_array($type, ['wx', 'wechat', 'weixin', 'wxpay'], true) || str_contains($type, 'wx')) {
            return 'wxpay';
        }
        if (in_array($type, ['qq', 'qqpay'], true) || str_contains($type, 'qq')) {
            return 'qqpay';
        }

        return $type;
    }

    private function assertSoftwareSignature(
        Request $request,
        array $merchant,
        string $credentialField,
        bool $writeIntent
    ): ?Response
    {
        $mode = strtolower(trim((string)SystemConfig::get('software_callback_sign_mode', 'strict')));
        if ($mode === '') {
            $mode = 'compat';
        }

        $providedSignature = trim((string)$request->input('signature', $request->input('sign', '')));
        $timestampRaw = trim((string)$request->input('timestamp', ''));
        $nonce = trim((string)$request->input('nonce', ''));

        $hasCompleteStrongSignature = $providedSignature !== '' && $timestampRaw !== '' && $nonce !== '';
        $auditContext = $this->softwareSignatureAuditContext(
            $request,
            $merchant,
            $credentialField,
            $writeIntent,
            $mode,
            $providedSignature,
            $timestampRaw,
            $nonce
        );

        if ($mode !== 'strict' && !$hasCompleteStrongSignature) {
            $this->recordSoftwareSignatureAudit(array_merge($auditContext, [
                'decision' => 'allow_compat_without_strong_signature',
                'reason' => 'compat_mode_without_signature',
            ]));
            return null;
        }

        if ($providedSignature === '' || $timestampRaw === '' || $nonce === '') {
            $this->recordSoftwareSignatureAudit(array_merge($auditContext, [
                'decision' => 'blocked',
                'reason' => 'missing_signature_fields',
                'required_fields' => ['signature', 'timestamp', 'nonce'],
            ]));
            return $this->monitorResponse(201, '当前已开启软件回调强签名，缺少 signature、timestamp、nonce 参数', [
                'current_mode' => $mode,
                'required_fields' => ['signature', 'timestamp', 'nonce'],
                'hint' => '旧版软件若仍只提交 id + key/token，请先切回旧版签名模式，或升级软件端后按 HMAC-SHA256 强签名协议接入。',
            ]);
        }

        if (!preg_match('/^\d{10,13}$/', $timestampRaw)) {
            $this->recordSoftwareSignatureAudit(array_merge($auditContext, [
                'decision' => 'blocked',
                'reason' => 'invalid_timestamp_format',
            ]));
            return $this->monitorResponse(201, 'timestamp 格式不正确');
        }

        $timestamp = (int)$timestampRaw;
        if ($timestamp > 9999999999) {
            $timestamp = (int)floor($timestamp / 1000);
        }

        $window = SystemConfig::int('software_callback_sign_window', 300);
        if ($window <= 0) {
            $window = 300;
        }

        if (abs(time() - $timestamp) > $window) {
            $this->recordSoftwareSignatureAudit(array_merge($auditContext, [
                'decision' => 'blocked',
                'reason' => 'signature_expired',
                'window_seconds' => $window,
            ]));
            return $this->monitorResponse(201, '软件回调签名已过期');
        }

        $secret = trim((string)($merchant['appkey'] ?? ''));
        if ($credentialField === 'token') {
            $secret = trim((string)($merchant['appkey'] ?? ''));
        }
        if ($secret === '') {
            $this->recordSoftwareSignatureAudit(array_merge($auditContext, [
                'decision' => 'blocked',
                'reason' => 'missing_signature_secret',
            ]));
            return $this->monitorResponse(201, '软件回调签名密钥不存在');
        }

        $payload = $this->softwareSignaturePayload($request, ['signature', 'sign']);
        $payload['nonce'] = $nonce;
        $payload['timestamp'] = $timestampRaw;
        ksort($payload);

        $expected = $this->softwareSignature($payload, $secret);
        if (!hash_equals($expected, strtolower($providedSignature))) {
            $this->recordSoftwareSignatureAudit(array_merge($auditContext, [
                'decision' => 'blocked',
                'reason' => 'signature_mismatch',
            ]));
            return $this->monitorResponse(201, '软件回调签名校验失败');
        }

        if (!$this->rememberSoftwareNonce((int)($merchant['id'] ?? 0), $credentialField, $nonce, $timestamp, $window)) {
            $this->recordSoftwareSignatureAudit(array_merge($auditContext, [
                'decision' => 'blocked',
                'reason' => 'nonce_replayed',
                'window_seconds' => $window,
            ]));
            return $this->monitorResponse(201, '软件回调签名重复，请勿重放');
        }

        $this->recordSoftwareSignatureAudit(array_merge($auditContext, [
            'decision' => 'allowed',
            'reason' => 'strong_signature_verified',
            'window_seconds' => $window,
        ]));

        return null;
    }

    private function softwareSignaturePayload(Request $request, array $excludedKeys = []): array
    {
        $payload = array_merge($request->get(), $request->post());
        $excludedLookup = array_fill_keys($excludedKeys, true);
        unset($payload['_token']);

        $normalized = [];
        foreach ($payload as $key => $value) {
            $name = trim((string)$key);
            if ($name === '' || isset($excludedLookup[$name])) {
                continue;
            }

            if (is_array($value) || is_object($value)) {
                $normalized[$name] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                continue;
            }

            $normalized[$name] = trim((string)$value);
        }

        ksort($normalized);

        return $normalized;
    }

    private function softwareSignature(array $payload, string $secret): string
    {
        $pairs = [];
        foreach ($payload as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            $pairs[] = $key . '=' . (string)$value;
        }

        return strtolower(hash_hmac('sha256', implode('&', $pairs), $secret));
    }

    private function softwareSecurityContext(Request $request, string $credentialField, bool $writeIntent): array
    {
        $mode = strtolower(trim((string)SystemConfig::get('software_callback_sign_mode', 'strict')));
        if ($mode === '') {
            $mode = 'compat';
        }

        $window = SystemConfig::int('software_callback_sign_window', 300);
        if ($window <= 0) {
            $window = 300;
        }

        $providedSignature = trim((string)$request->input('signature', $request->input('sign', '')));
        $timestampRaw = trim((string)$request->input('timestamp', ''));
        $nonce = trim((string)$request->input('nonce', ''));
        $strongSignaturePresent = $providedSignature !== '' && $timestampRaw !== '' && $nonce !== '';

        return [
            'scope' => $writeIntent ? 'software_callback_write' : 'software_callback_read',
            'credential_field' => $credentialField,
            'credential_guard' => 'merchant_appkey_lookup',
            'sign_mode' => $mode,
            'strong_signature' => [
                'algorithm' => 'hmac_sha256',
                'required' => $mode === 'strict',
                'present' => $strongSignaturePresent,
                'fields' => ['signature', 'timestamp', 'nonce'],
            ],
            'replay_protection' => [
                'strategy' => $strongSignaturePresent || $mode === 'strict'
                    ? 'nonce_window_cache'
                    : ($writeIntent ? 'settlement_idempotency_only' : 'none'),
                'window_seconds' => $strongSignaturePresent || $mode === 'strict' ? $window : null,
            ],
        ];
    }

    private function rememberSoftwareNonce(int $merchantId, string $scope, string $nonce, int $timestamp, int $window): bool
    {
        $normalizedNonce = trim($nonce);
        if ($merchantId <= 0 || $normalizedNonce === '') {
            return false;
        }

        if (!preg_match('/^[A-Za-z0-9:_\\-]{6,128}$/', $normalizedNonce)) {
            return false;
        }

        $directory = runtime_path('software-signatures');
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        $path = $directory . DIRECTORY_SEPARATOR . 'merchant_' . $merchantId . '_' . $scope . '.json';
        $now = time();
        $records = [];

        if (is_file($path)) {
            $decoded = json_decode((string)@file_get_contents($path), true);
            if (is_array($decoded)) {
                foreach ($decoded as $recordNonce => $expireAt) {
                    if (!is_string($recordNonce) || !is_numeric($expireAt)) {
                        continue;
                    }

                    $expireTimestamp = (int)$expireAt;
                    if ($expireTimestamp >= $now) {
                        $records[$recordNonce] = $expireTimestamp;
                    }
                }
            }
        }

        if (isset($records[$normalizedNonce])) {
            return false;
        }

        $records[$normalizedNonce] = max($timestamp + $window, $now + $window);
        @file_put_contents($path, json_encode($records, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);

        return true;
    }

    private function softwareSignatureAuditContext(
        Request $request,
        array $merchant,
        string $credentialField,
        bool $writeIntent,
        string $mode,
        string $providedSignature,
        string $timestampRaw,
        string $nonce
    ): array {
        return [
            'recorded_at' => date('Y-m-d H:i:s'),
            'path' => method_exists($request, 'path') ? (string)$request->path() : '',
            'method' => method_exists($request, 'method') ? (string)$request->method() : '',
            'merchant_id' => (int)($merchant['id'] ?? 0),
            'merchant_username' => trim((string)($merchant['username'] ?? '')),
            'credential_field' => $credentialField,
            'write_intent' => $writeIntent,
            'mode' => $mode,
            'strong_signature_present' => $providedSignature !== '' && $timestampRaw !== '' && $nonce !== '',
            'signature_present' => $providedSignature !== '',
            'timestamp_present' => $timestampRaw !== '',
            'nonce_present' => $nonce !== '',
            'ip' => method_exists($request, 'getRealIp') ? (string)$request->getRealIp() : '',
            'user_agent' => trim((string)$request->header('user-agent', '')),
        ];
    }

    private function recordSoftwareSignatureAudit(array $payload): void
    {
        $directory = runtime_path('software-signatures');
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        $path = $directory . DIRECTORY_SEPARATOR . 'readiness-audit.jsonl';
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return;
        }

        @file_put_contents($path, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function findPendingSoftwareOrder(
        int $merchantId,
        int $channelId,
        float $money,
        string $type,
        string $orderNo
    ): ?array {
        $query = Db::table('ypay_order')
            ->select(
                'id',
                'name',
                'type',
                'money',
                'truemoney',
                'feilvmoney',
                'user_id',
                'account_id',
                'trade_no',
                'out_trade_no',
                'notify_url',
                'return_url',
                'status',
                'api_memo',
                'out_time'
            )
            ->where('status', 0)
            ->where('out_time', '>', time())
            ->where('truemoney', number_format($money, 2, '.', ''));

        if ($channelId > 0) {
            $query->where('account_id', $channelId);
        } else {
            $query->where('user_id', $merchantId);
        }

        if ($type !== '') {
            $query->where('type', $type);
        }

        if ($orderNo !== '') {
            $query->where('out_trade_no', $orderNo);
        }

        $rows = $query
            ->orderByDesc('id')
            ->limit(2)
            ->get()
            ->toArray();

        if ($rows === []) {
            return null;
        }

        if ($orderNo === '' && count($rows) > 1) {
            throw new \RuntimeException('multiple pending orders matched the software callback');
        }

        return (array)$rows[0];
    }

    private function findProcessedSoftwareOrder(
        int $merchantId,
        int $channelId,
        float $money,
        string $type,
        string $orderNo
    ): ?array {
        $thresholdTimestamp = time() - 900;
        $thresholdDateTime = date('Y-m-d H:i:s', $thresholdTimestamp);

        $query = Db::table('ypay_order')
            ->select(
                'id',
                'name',
                'type',
                'money',
                'truemoney',
                'feilvmoney',
                'user_id',
                'account_id',
                'trade_no',
                'out_trade_no',
                'notify_url',
                'return_url',
                'status',
                'api_memo',
                'out_time',
                'create_time',
                'update_time'
            )
            ->where('status', 1)
            ->where('truemoney', number_format($money, 2, '.', ''));

        if ($channelId > 0) {
            $query->where('account_id', $channelId);
        } else {
            $query->where('user_id', $merchantId);
        }

        if ($type !== '') {
            $query->where('type', $type);
        }

        if ($orderNo !== '') {
            $query->where('out_trade_no', $orderNo);
        } else {
            $query->where(function ($builder) use ($thresholdTimestamp, $thresholdDateTime) {
                $builder
                    ->where('update_time', '>=', $thresholdDateTime)
                    ->orWhere('create_time', '>=', $thresholdDateTime)
                    ->orWhere('out_time', '>=', $thresholdTimestamp);
            });
        }

        $row = $query
            ->orderByDesc('id')
            ->first();

        return $row ? (array)$row : null;
    }

    private function processedSoftwareOrderPayload(array $order): array
    {
        return [
            'order' => [
                'id' => (int)($order['id'] ?? 0),
                'trade_no' => trim((string)($order['trade_no'] ?? '')),
                'out_trade_no' => trim((string)($order['out_trade_no'] ?? '')),
                'type' => trim((string)($order['type'] ?? '')),
                'money' => (string)($order['money'] ?? '0.00'),
                'truemoney' => (string)($order['truemoney'] ?? '0.00'),
                'status' => (int)($order['status'] ?? 1),
            ],
            'merchant_callback' => [
                'memo' => trim((string)($order['api_memo'] ?? '')),
            ],
            'settlement' => [
                'already_paid' => true,
                'settlement_executed' => false,
            ],
        ];
    }

    private function callbackPayload(Request $request, float $money): array
    {
        return [
            'trade_no' => trim((string)$request->input('trade_no', $request->input('platform_trade_no', ''))),
            'transaction_id' => trim((string)$request->input('transaction_id', '')),
            'buyer_trade_no' => trim((string)$request->input('buyer_trade_no', '')),
            'orderNo' => trim((string)$request->input('orderNo', '')),
            'money' => number_format($money, 2, '.', ''),
        ];
    }

    private function settleAndNotifyMerchant(array $order, array $merchant, array $payload): array
    {
        $repository = new LegacyEpayOrderRepository();
        $settlement = $repository->settlePaidOrder($order, $merchant, $payload);
        $settledOrder = (array)($settlement['order'] ?? $order);
        $callbackTask = (new OrderCallbackTaskService())->enqueueForSettledOrder($settledOrder, $merchant, [
            'scene' => 'software_callback',
            'dispatch_now' => true,
        ]);

        return [
            'order' => [
                'id' => (int)($settledOrder['id'] ?? 0),
                'trade_no' => trim((string)($settledOrder['trade_no'] ?? '')),
                'out_trade_no' => trim((string)($settledOrder['out_trade_no'] ?? '')),
                'type' => trim((string)($settledOrder['type'] ?? '')),
                'money' => (string)($settledOrder['money'] ?? '0.00'),
                'truemoney' => (string)($settledOrder['truemoney'] ?? '0.00'),
                'status' => 1,
            ],
            'merchant_callback' => [
                'notify_url' => trim((string)($callbackTask['notify_url'] ?? '')),
                'return_url' => trim((string)($callbackTask['return_url'] ?? '')),
                'http_status' => (int)($callbackTask['http_status'] ?? 202),
                'ok' => (bool)($callbackTask['ok'] ?? true),
                'queued' => (bool)($callbackTask['queued'] ?? false),
                'task_id' => (int)($callbackTask['task_id'] ?? 0),
                'memo' => trim((string)($callbackTask['memo'] ?? 'merchant_callback_queued')),
            ],
            'settlement' => [
                'already_paid' => (bool)($settlement['already_paid'] ?? false),
                'settlement_executed' => (bool)($settlement['settlement_executed'] ?? false),
            ],
        ];
    }

    private function decodeReportContent(string $raw): ?array
    {
        $content = trim($raw);
        if ($content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $decoded = json_decode(urldecode($content), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function reportPackageType(string $packageName): ?string
    {
        $package = strtolower(trim($packageName));
        if ($package === '') {
            return null;
        }

        if (str_contains($package, 'alipay')) {
            return 'alipay';
        }
        if ($package === 'com.tencent.mm' || str_contains($package, 'tencent.mm')) {
            return 'wxpay';
        }

        return null;
    }

    private function extractReportAmount(string $message, string $type): ?float
    {
        $patterns = $type === 'alipay'
            ? [
                '/成功收款\s*(\d+(?:\.\d+)?)\s*元?/u',
                '/付款金额.*?(?:¥|￥)?\s*(\d+(?:\.\d+)?)/u',
                '/收款金额.*?(?:¥|￥)?\s*(\d+(?:\.\d+)?)/u',
                '/(?:到账|收款)(?:通知)?[^¥￥\d]*?(?:¥|￥)?\s*(\d+(?:\.\d+)?)\s*元?/u',
                '/个人收款码到账.*?(?:¥|￥)?\s*(\d+(?:\.\d+)?)\s*元?/u',
                '/款\s*(\d+(?:\.\d+)?)\s*元?/u',
                '/(?:¥|￥)\s*(\d+(?:\.\d+)?)/u',
            ]
            : [
                '/成功收款\s*(\d+(?:\.\d+)?)\s*元?/u',
                '/二维码赞赏到账.*?(?:¥|￥)?\s*(\d+(?:\.\d+)?)\s*元?/u',
                '/赞赏码.*?(?:¥|￥)?\s*(\d+(?:\.\d+)?)\s*元?/u',
                '/到账\s*(\d+(?:\.\d+)?)\s*元?/u',
                '/赞赏到账\s*(\d+(?:\.\d+)?)\s*元?/u',
                '/收款金额.*?(?:¥|￥)?\s*(\d+(?:\.\d+)?)/u',
                '/到账金额.*?(?:¥|￥)?\s*(\d+(?:\.\d+)?)/u',
                '/(?:收款|到账)(?:通知)?[^¥￥\d]*?(?:¥|￥)?\s*(\d+(?:\.\d+)?)\s*元?/u',
                '/个人收款码到账.*?(?:¥|￥)?\s*(\d+(?:\.\d+)?)\s*元?/u',
                '/(\d+(?:\.\d+)?)\s*元/u',
                '/(?:¥|￥)\s*(\d+(?:\.\d+)?)/u',
            ];

        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $message, $match)) {
                continue;
            }

            foreach ($match as $value) {
                if (!is_string($value)) {
                    continue;
                }

                $normalized = str_replace([',', '，'], '', trim($value));
                if ($normalized !== '' && is_numeric($normalized)) {
                    return round((float)$normalized, 2);
                }
            }
        }

        $numericMatchCount = preg_match_all('/\d+(?:\.\d{1,2})?/', $message, $matches);
        if (is_int($numericMatchCount) && $numericMatchCount > 0) {
            $numbers = array_values(array_filter(
                array_map(
                    static fn (mixed $value): string => is_string($value) ? trim($value) : '',
                    (array)($matches[0] ?? [])
                ),
                static fn (string $value): bool => $value !== '' && is_numeric($value)
            ));

            if ($numbers !== []) {
                foreach (array_reverse($numbers) as $value) {
                    if (str_contains($value, '.')) {
                        return round((float)$value, 2);
                    }
                }

                return round((float)end($numbers), 2);
            }
        }

        return null;
    }

    private function monitorResponse(int $code, string $message, array $data = [], array $extra = []): Response
    {
        $message = ApiResponse::normalizeText($message);

        $payload = array_merge([
            'code' => $code,
            'message' => $message,
            'msg' => $message,
            'data' => $data,
            'redirect' => '',
        ], $extra);

        return json($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
