<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\controller;

use app\support\ApiResponse;
use app\service\order\OrderCallbackTaskService;
use app\support\BusinessTable;
use app\support\SharedRedis;
use app\support\SystemConfig;
use Plugins\Payments\Shared\EpayProtocol\EpayOrderRepository;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

use function runtime_path;

class SoftwareCompatibilityController
{
    public function verify(Request $request): Response
    {
        $this->recordSoftwareReportAudit($request, ['endpoint' => 'verify', 'stage' => 'received']);

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
        $this->recordSoftwareReportAudit($request, ['endpoint' => 'checkOrder', 'stage' => 'received']);

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

        $orders = Db::table(BusinessTable::order())
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
        $this->recordSoftwareReportAudit($request, ['endpoint' => 'heartbeat', 'stage' => 'received']);

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

        Db::table(BusinessTable::account())
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
        $this->recordSoftwareReportAudit($request, ['endpoint' => 'pcNotify', 'stage' => 'received']);

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
        $orderReferences = $this->resolveSoftwareReportOrderReferences($request, []);

        try {
            $order = $this->findPendingSoftwareOrder(
                (int)($merchant['id'] ?? 0),
                (int)($account['id'] ?? 0),
                $money,
                $type,
                $orderReferences
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
                $orderReferences
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

    private function softwareMerchant(Request $request): array
    {
        $merchantId = (int)$request->input('id', 0);
        $appKey = trim((string)$request->input('key', ''));

        if ($appKey === '') {
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
        if ($merchantId <= 0) {
            return [null, $this->monitorResponse(201, '商户编号不能为空')];
        }
        if ($appKey === '' && $this->isPcReportRequest($request)) {
            return $this->findSoftwareMerchantById($merchantId);
        }
        if ($appKey === '') {
            return [null, $this->monitorResponse(201, '商户编号和 token 不能为空')];
        }

        return $this->findSoftwareMerchant($merchantId, $appKey);
    }

    private function findSoftwareMerchant(int $merchantId, string $appKey): array
    {
        $row = Db::table(BusinessTable::userBasic('basic'))
            ->leftJoin(BusinessTable::user('merchant'), 'basic.user_id', '=', 'merchant.id')
            ->select(
                'basic.user_id',
                'basic.appkey',
                'merchant.id',
                'merchant.username',
                'merchant.user_key',
                'merchant.money',
                'merchant.is_frozen',
                'merchant.frozen_reason'
            )
            ->where('basic.user_id', $merchantId)
            ->where('basic.appkey', $appKey)
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

    private function findSoftwareMerchantById(int $merchantId): array
    {
        $row = Db::table(BusinessTable::user('merchant'))
            ->leftJoin(BusinessTable::userBasic('basic'), 'basic.user_id', '=', 'merchant.id')
            ->select(
                'basic.user_id',
                'basic.appkey',
                'merchant.id',
                'merchant.username',
                'merchant.user_key',
                'merchant.money',
                'merchant.is_frozen',
                'merchant.frozen_reason'
            )
            ->where('merchant.id', $merchantId)
            ->first();

        if (!$row) {
            return [null, $this->monitorResponse(201, '商户不存在')];
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

        $account = Db::table(BusinessTable::account())
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

    private function resolveSoftwareReportAccount(
        Request $request,
        array $content,
        int $merchantId,
        string $type
    ): array {
        $explicitChannelId = $this->resolveSoftwareReportChannelId($request, $content);
        if ($explicitChannelId > 0) {
            $account = $this->findSoftwareAccountById($merchantId, $explicitChannelId);
            if ($account === null) {
                return [null, $this->monitorResponse(201, 'channel_id 无效'), [
                    'explicit_channel_id' => $explicitChannelId,
                    'matched_by' => 'channel_id',
                    'resolved' => false,
                ]];
            }

            return [$account, null, [
                'explicit_channel_id' => $explicitChannelId,
                'matched_by' => 'channel_id',
                'resolved' => true,
                'identifier' => (string)$explicitChannelId,
            ]];
        }

        $identifiers = $this->softwareReportAccountIdentifierCandidates($request, $content);
        if ($identifiers === []) {
            return [null, null, [
                'explicit_channel_id' => 0,
                'matched_by' => '',
                'resolved' => false,
                'identifier_candidates' => [],
            ]];
        }

        $account = $this->findSoftwareAccountByIdentifier($merchantId, $type, $identifiers);
        if ($account === null) {
            return [null, null, [
                'explicit_channel_id' => 0,
                'matched_by' => '',
                'resolved' => false,
                'identifier_candidates' => $identifiers,
            ]];
        }

        return [$account, null, [
            'explicit_channel_id' => 0,
            'matched_by' => 'identifier',
            'resolved' => true,
            'identifier' => (string)($account['_matched_identifier'] ?? ''),
            'identifier_source' => (string)($account['_matched_identifier_source'] ?? ''),
            'identifier_candidates' => $identifiers,
        ]];
    }

    private function resolveSoftwareReportChannelId(Request $request, array $content): int
    {
        $candidates = [
            $request->input('channel_id', null),
            $request->input('account_id', null),
            $request->input('channelId', null),
            $request->input('accountId', null),
            $content['channel_id'] ?? null,
            $content['account_id'] ?? null,
            $content['channelId'] ?? null,
            $content['accountId'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $channelId = (int)$candidate;
            if ($channelId > 0) {
                return $channelId;
            }
        }

        return 0;
    }

    private function softwareReportAccountIdentifierCandidates(Request $request, array $content): array
    {
        $candidates = [
            $content['tempParam'] ?? null,
            $request->input('tempParam', null),
            $content['pid'] ?? null,
            $request->input('pid', null),
            $content['wxid'] ?? null,
            $request->input('wxid', null),
            $content['wxname'] ?? null,
            $request->input('wxname', null),
            $content['account'] ?? null,
            $request->input('account', null),
            $content['account_name'] ?? null,
            $request->input('account_name', null),
            $content['channel_code'] ?? null,
            $request->input('channel_code', null),
            $content['channel'] ?? null,
            $request->input('channel', null),
            $content['code'] ?? null,
            $request->input('code', null),
        ];

        $identifiers = [];
        foreach ($candidates as $candidate) {
            $identifier = trim((string)$candidate);
            if ($identifier === '') {
                continue;
            }

            $identifiers[] = $identifier;
        }

        return array_values(array_unique($identifiers));
    }

    private function findSoftwareAccountById(int $merchantId, int $channelId): ?array
    {
        if ($merchantId <= 0 || $channelId <= 0) {
            return null;
        }

        $account = Db::table(BusinessTable::account())
            ->select('id', 'user_id', 'code', 'type', 'status', 'is_status', 'wxname', 'zfb_pid', 'qq', 'qr_type', 'qr_url', 'remark')
            ->where('id', $channelId)
            ->where('user_id', $merchantId)
            ->first();

        return $account ? (array)$account : null;
    }

    private function findSoftwareAccountByIdentifier(int $merchantId, string $type, array $identifiers): ?array
    {
        if ($merchantId <= 0 || $identifiers === []) {
            return null;
        }

        $normalizedType = $this->normalizePaymentType($type);
        foreach ($identifiers as $identifier) {
            $query = Db::table(BusinessTable::account())
                ->select('id', 'user_id', 'code', 'type', 'status', 'is_status', 'wxname', 'zfb_pid', 'qq', 'qr_type', 'qr_url', 'remark')
                ->where('user_id', $merchantId);

            if (in_array($normalizedType, ['alipay', 'wxpay', 'qqpay'], true)) {
                $query->where('type', $normalizedType);
            }

            $query->where(function ($builder) use ($identifier) {
                $builder
                    ->where('code', $identifier)
                    ->orWhere('wxname', $identifier)
                    ->orWhere('zfb_pid', $identifier)
                    ->orWhere('qq', $identifier);
            });

            $row = $query
                ->orderByDesc('status')
                ->orderByDesc('is_status')
                ->orderByDesc('id')
                ->first();

            if (!$row) {
                continue;
            }

            $account = (array)$row;
            $account['_matched_identifier'] = $identifier;
            $account['_matched_identifier_source'] = $this->softwareAccountIdentifierSource($account, $identifier);

            return $account;
        }

        return null;
    }

    private function softwareAccountIdentifierSource(array $account, string $identifier): string
    {
        foreach (['code', 'wxname', 'zfb_pid', 'qq'] as $field) {
            if (trim((string)($account[$field] ?? '')) === $identifier) {
                return $field;
            }
        }

        return '';
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

        if (str_contains($type, '支付宝')) {
            return 'alipay';
        }
        if (str_contains($type, '微信')) {
            return 'wxpay';
        }
        if (str_contains($type, 'qq')) {
            return 'qqpay';
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
                'hint' => '如果软件端当前仍只提交 id + key/token，请先切换到基础校验模式，或升级软件端后按 HMAC-SHA256 强签名协议接入。',
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

        return $this->validateSoftwareStrongSignature(
            $request,
            $merchant,
            $credentialField,
            $providedSignature,
            $timestampRaw,
            $nonce,
            $timestamp,
            $window,
            $auditContext
        );
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

    /**
     * @param array<string, mixed> $merchant
     * @param array<string, mixed> $auditContext
     */
    private function validateSoftwareStrongSignature(
        Request $request,
        array $merchant,
        string $credentialField,
        string $providedSignature,
        string $timestampRaw,
        string $nonce,
        int $timestamp,
        int $window,
        array $auditContext
    ): ?Response {
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

        try {
            $nonceAccepted = $this->claimSoftwareNonce((int)($merchant['id'] ?? 0), $credentialField, $nonce, $timestamp, $window);
        } catch (\RuntimeException $exception) {
            $this->recordSoftwareSignatureAudit(array_merge($auditContext, [
                'decision' => 'blocked',
                'reason' => 'nonce_backend_unavailable',
                'window_seconds' => $window,
            ]));

            return $this->monitorResponse(503, '软件回调防重服务暂不可用');
        }

        if (!$nonceAccepted) {
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

    private function claimSoftwareNonce(int $merchantId, string $scope, string $nonce, int $timestamp, int $window): bool
    {
        $normalizedNonce = trim($nonce);
        if ($merchantId <= 0 || $normalizedNonce === '') {
            return false;
        }

        if (!preg_match('/^[A-Za-z0-9:_\\-]{6,128}$/', $normalizedNonce)) {
            return false;
        }

        $normalizedScope = preg_replace('/[^A-Za-z0-9:_\\-]/', '_', trim($scope)) ?: 'default';
        $now = time();
        $expireAt = max($timestamp + $window, $now + $window);
        $ttl = max(1, $expireAt - $now);
        $claimed = SharedRedis::setIfAbsent(
            sprintf('merchant:%d:%s:%s', $merchantId, $normalizedScope, $normalizedNonce),
            (string)$expireAt,
            $ttl,
            SharedRedis::softwareNonceConfig()
        );

        if ($claimed === null) {
            throw new \RuntimeException('software nonce redis is unavailable');
        }

        return $claimed;
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

    private function anonymousPcReportSecurityContext(Request $request): array
    {
        return [
            'scope' => 'software_pc_report_write',
            'credential_field' => 'route_merchant_id',
            'credential_guard' => 'legacy_pc_report_route',
            'sign_mode' => 'legacy_pc_route_compat',
            'strong_signature' => [
                'algorithm' => 'none',
                'required' => false,
                'present' => false,
                'fields' => [],
            ],
            'replay_protection' => [
                'strategy' => 'settlement_idempotency_only',
                'window_seconds' => null,
            ],
            'path' => method_exists($request, 'path') ? (string)$request->path() : '',
        ];
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
        array $orderReferences
    ): ?array {
        $resolvedOrder = $this->findSoftwareOrderMatch(
            $merchantId,
            $channelId,
            $money,
            $type,
            $orderReferences,
            false
        );
        if ($resolvedOrder !== null) {
            return $resolvedOrder;
        }

        $rows = $this->buildSoftwareOrderBaseQuery($merchantId, $channelId, $money, $type, false)
            ->orderByDesc('id')
            ->limit(2)
            ->get()
            ->toArray();

        if ($rows === []) {
            return null;
        }

        if (count($rows) > 1) {
            throw new \RuntimeException('multiple pending orders matched the software callback');
        }

        return (array)$rows[0];
    }

    private function findProcessedSoftwareOrder(
        int $merchantId,
        int $channelId,
        float $money,
        string $type,
        array $orderReferences
    ): ?array {
        $row = $this->findSoftwareOrderMatch(
            $merchantId,
            $channelId,
            $money,
            $type,
            $orderReferences,
            true
        );
        if ($row !== null) {
            return $row;
        }

        $thresholdTimestamp = time() - 900;
        $thresholdDateTime = date('Y-m-d H:i:s', $thresholdTimestamp);

        $row = $this->buildSoftwareOrderBaseQuery($merchantId, $channelId, $money, $type, true)
            ->where(function ($builder) use ($thresholdTimestamp, $thresholdDateTime) {
                $builder
                    ->where('update_time', '>=', $thresholdDateTime)
                    ->orWhere('create_time', '>=', $thresholdDateTime)
                    ->orWhere('out_time', '>=', $thresholdTimestamp);
            })
            ->orderByDesc('id')
            ->first();

        return $row ? (array)$row : null;
    }

    private function buildSoftwareOrderBaseQuery(int $merchantId, int $channelId, float $money, string $type, bool $processed)
    {
        $query = Db::table(BusinessTable::order())
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
            ->where('status', $processed ? 1 : 0)
            ->where('truemoney', number_format($money, 2, '.', ''));

        if (!$processed) {
            $query->where('out_time', '>', time());
        }

        if ($channelId > 0) {
            $query->where('account_id', $channelId);
        } else {
            $query->where('user_id', $merchantId);
        }

        if ($type !== '') {
            $query->where('type', $type);
        }

        return $query;
    }

    private function findSoftwareOrderMatch(
        int $merchantId,
        int $channelId,
        float $money,
        string $type,
        array $orderReferences,
        bool $processed
    ): ?array {
        $normalizedReferences = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): string => trim((string)$value), $orderReferences),
            static fn (string $value): bool => $value !== ''
        )));
        if ($normalizedReferences === []) {
            return null;
        }

        foreach ($normalizedReferences as $reference) {
            $row = $this->buildSoftwareOrderBaseQuery($merchantId, $channelId, $money, $type, $processed)
                ->where(function ($builder) use ($reference) {
                    $builder
                        ->where('out_trade_no', $reference)
                        ->orWhere('trade_no', $reference);
                })
                ->orderByDesc('id')
                ->first();

            if ($row) {
                return (array)$row;
            }
        }

        return null;
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
        $repository = new EpayOrderRepository();
        $settlement = $repository->settlePaidOrder($order, $merchant, $payload);
        $settledOrder = (array)($settlement['order'] ?? $order);
        $callbackTask = (new OrderCallbackTaskService())->enqueueForSettledOrder($settledOrder, $merchant, [
            'scene' => 'software_callback',
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

    private function isPcReportRequest(Request $request): bool
    {
        $path = strtolower(trim((string)(method_exists($request, 'path') ? $request->path() : '')));

        return $path !== '' && str_ends_with($path, '/pc');
    }

    private function isAnonymousPcReportRequest(Request $request): bool
    {
        if (!$this->isPcReportRequest($request)) {
            return false;
        }

        $token = trim((string)$request->input('token', $request->input('key', '')));
        $signature = trim((string)$request->input('signature', $request->input('sign', '')));

        return $token === '' && $signature === '';
    }

    private function isPcReportHandshake(Request $request, array $content): bool
    {
        if (!$this->isPcReportRequest($request)) {
            return false;
        }

        $payload = array_merge((array)$request->get(), (array)$request->post());
        foreach (['id', 'user_id', 'token', 'key', 'signature', 'sign', 'timestamp', 'nonce'] as $ignoredKey) {
            unset($payload[$ignoredKey]);
        }

        if ($content !== []) {
            return false;
        }

        foreach ($payload as $value) {
            if (trim((string)$value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function resolveReportType(Request $request, array $content): ?string
    {
        $packageName = trim((string)($content['package_name'] ?? $request->input('package_name', $request->input('package', ''))));
        if ($packageName !== '') {
            $type = $this->reportPackageType($packageName);
            if ($type !== null) {
                return $type;
            }

            $normalizedPackageType = $this->normalizePaymentType($packageName);
            if (in_array($normalizedPackageType, ['alipay', 'wxpay', 'qqpay'], true)) {
                return $normalizedPackageType;
            }
        }

        $candidates = [
            $content['type'] ?? null,
            $content['pay_type'] ?? null,
            $content['channel'] ?? null,
            $content['channel_code'] ?? null,
            $content['method'] ?? null,
            $content['title'] ?? null,
            $content['payway'] ?? null,
            $request->input('type', null),
            $request->input('pay_type', null),
            $request->input('channel', null),
            $request->input('channel_code', null),
            $request->input('method', null),
            $request->input('title', null),
            $request->input('payway', null),
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizePaymentType(trim((string)$candidate));
            if (in_array($normalized, ['alipay', 'wxpay', 'qqpay'], true)) {
                return $normalized;
            }
        }

        return null;
    }

    private function resolveReportMoney(Request $request, array $content, string $message, string $type): ?float
    {
        $directValues = [
            $content['money'] ?? null,
            $content['amount'] ?? null,
            $content['truemoney'] ?? null,
            $content['real_money'] ?? null,
            $content['pay_amount'] ?? null,
            $content['price'] ?? null,
            $request->input('money', null),
            $request->input('amount', null),
            $request->input('truemoney', null),
            $request->input('real_money', null),
            $request->input('pay_amount', null),
            $request->input('price', null),
        ];

        foreach ($directValues as $value) {
            $money = $this->normalizeMoney($value);
            if ($money !== null) {
                return $money;
            }
        }

        return $message !== '' ? $this->extractReportAmount($message, $type) : null;
    }

    private function resolveSoftwareReportOrderReferences(Request $request, array $content): array
    {
        $candidates = [
            $content['out_trade_no'] ?? null,
            $request->input('out_trade_no', null),
            $content['out_order_id'] ?? null,
            $request->input('out_order_id', null),
            $content['orderNo'] ?? null,
            $request->input('orderNo', null),
            $content['order_no'] ?? null,
            $request->input('order_no', null),
            $content['order_id'] ?? null,
            $request->input('order_id', null),
            $content['trade_no'] ?? null,
            $request->input('trade_no', null),
        ];

        $references = [];
        foreach ($candidates as $candidate) {
            $reference = trim((string)$candidate);
            if ($reference === '') {
                continue;
            }

            $references[] = $reference;
        }

        return array_values(array_unique($references));
    }

    private function buildReportMessageFallback(Request $request, array $content, ?string $type): string
    {
        $parts = array_filter([
            trim((string)($content['title'] ?? $request->input('title', ''))),
            trim((string)($content['type'] ?? $request->input('type', $type ?? ''))),
            trim((string)($content['money'] ?? $request->input('money', $request->input('amount', '')))),
            trim((string)($content['mark'] ?? $request->input('mark', $request->input('memo', '')))),
        ], static fn (string $value): bool => $value !== '');

        return implode(' ', $parts);
    }

    private function recordSoftwareReportAudit(Request $request, array $context = []): void
    {
        $directory = runtime_path('software-reports');
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        $query = (array)$request->get();
        $post = (array)$request->post();
        $payload = array_merge($query, $post);
        $entry = array_merge([
            'recorded_at' => date('Y-m-d H:i:s'),
            'path' => method_exists($request, 'path') ? (string)$request->path() : '',
            'method' => method_exists($request, 'method') ? (string)$request->method() : '',
            'merchant_id_from_route' => (int)($request->route ? $request->route->param('id', 0) : 0),
            'query_keys' => array_keys($query),
            'post_keys' => array_keys($post),
            'payload' => $this->sanitizeSoftwareReportAuditPayload($payload),
            'ip' => method_exists($request, 'getRealIp') ? (string)$request->getRealIp() : '',
            'user_agent' => trim((string)$request->header('user-agent', '')),
        ], $context);

        $encoded = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return;
        }

        @file_put_contents($directory . DIRECTORY_SEPARATOR . 'incoming.jsonl', $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function sanitizeSoftwareReportAuditPayload(array $payload): array
    {
        $maskedKeys = ['token', 'key', 'message_key', 'signature', 'sign', 'nonce'];
        $sanitized = [];

        foreach ($payload as $key => $value) {
            $name = trim((string)$key);
            if ($name === '') {
                continue;
            }

            if (is_array($value) || is_object($value)) {
                $sanitized[$name] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                continue;
            }

            $stringValue = trim((string)$value);
            if (in_array(strtolower($name), $maskedKeys, true)) {
                $sanitized[$name] = $this->maskSoftwareReportAuditValue($stringValue);
                continue;
            }

            if ($name === 'content' && strlen($stringValue) > 400) {
                $sanitized[$name] = substr($stringValue, 0, 400) . '...';
                continue;
            }

            $sanitized[$name] = $stringValue;
        }

        return $sanitized;
    }

    private function maskSoftwareReportAuditValue(string $value): string
    {
        $length = strlen($value);
        if ($length <= 0) {
            return '';
        }

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 4) . str_repeat('*', max($length - 8, 4)) . substr($value, -4);
    }

    private function recordSoftwareResponseAudit(array $payload, array $context = []): void
    {
        $request = function_exists('request') ? request() : null;
        $entry = array_merge([
            'recorded_at' => date('Y-m-d H:i:s'),
            'path' => $request instanceof Request && method_exists($request, 'path') ? (string)$request->path() : '',
            'method' => $request instanceof Request && method_exists($request, 'method') ? (string)$request->method() : '',
            'merchant_id_from_route' => $request instanceof Request && $request->route ? (int)$request->route->param('id', 0) : 0,
            'payload' => $this->sanitizeSoftwareReportAuditPayload($payload),
        ], $context);

        $encoded = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return;
        }

        $directory = runtime_path('software-reports');
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        @file_put_contents($directory . DIRECTORY_SEPARATOR . 'outgoing.jsonl', $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
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

        $this->recordSoftwareResponseAudit($payload, ['response_type' => 'monitor']);

        return json($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
