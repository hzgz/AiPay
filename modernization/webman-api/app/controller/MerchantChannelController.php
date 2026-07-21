<?php

namespace app\controller;

use app\service\payment\PaymentPluginManager;
use app\service\order\OrderReconcileTaskService;
use app\support\AdminFixtureTextNormalizer;
use app\support\AdminOrderFormatter;
use app\support\AdminPaymentAccountFormatter;
use app\support\ApiResponse;
use app\support\BusinessTable;
use app\support\FrontendUrlBuilder;
use app\support\MerchantFrontSession;
use app\support\QrCodeService;
use app\support\RequestPayload;
use app\support\SystemConfig;
use app\support\UploadWorkspace;
use Illuminate\Database\Query\Builder;
use Plugins\Payments\Shared\Support\JiaofeiyiSupport;
use Plugins\Payments\Shared\Managed\AbstractManagedGatewayOrderService;
use Plugins\Payments\UniversalEpay\Support\UniversalEpayGatewayService;
use support\Db;
use Throwable;
use Plugins\Payments\AlipayOfficial\Support\AlipayOfficialGatewayService;
use Plugins\Payments\WxpayV3\Support\WxpayV3GatewayService;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\Http\UploadFile;

class MerchantChannelController
{
    private const CREDENTIAL_IMAGE_UPLOAD_URL = '/api/merchant/channels/credential-image';
    private const CREDENTIAL_DECODE_URL = '/api/merchant/channels/credential-decode';
    private const TEST_PAY_ORDER_MEMOS = ['merchant_channel_test_pay', 'merchant_channel_test_paid'];
    private const ALIPAY_BILL_RECONCILE_CODES = ['alipay_bill', 'alipay_mck'];
    private const ALIPAY_BILL_RECONCILE_GRACE_SECONDS = 300;
    private const USDT_RECONCILE_GRACE_SECONDS = 300;
    private const WXPAY_V3_RECONCILE_GRACE_SECONDS = 300;

    public function index(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->wantsJson($request)
                ? $this->merchantAuthError()
                : $this->merchantLoginRedirect($request, '/merchant/channels');
        }

        if (!$this->wantsJson($request)) {
            return $this->merchantSpaRedirect($request, '/merchant/channels');
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $current = max(1, (int)$request->get('current', $request->get('page', 1)));
        $size = max(1, min((int)$request->get('size', $request->get('limit', 20)), 100));

        $query = $this->accountQuery($merchantId);
        $this->applyFilters($query, $request);

        $summary = $this->buildSummary($merchant, clone $query);
        $total = (int)(clone $query)->count('account.id');
        $rows = $query
            ->orderByDesc('account.id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        $accountIds = array_values(array_unique(array_map(
            static fn($row): int => (int)((array)$row)['id'],
            $rows
        )));
        $stats = $this->loadOrderStats($accountIds);

        $records = array_map(static function ($row) use ($stats): array {
            $record = (array)$row;
            $accountId = (int)($record['id'] ?? 0);

            return AdminPaymentAccountFormatter::format($record, $stats[$accountId] ?? []);
        }, $rows);

        return $this->merchantCollectionSuccess(
            $records,
            [
                'current' => $current,
                'size' => $size,
                'total' => $total,
            ],
            $summary,
            $this->buildWriteActions($merchant),
            $this->buildCatalog($merchantId)
        );
    }

    public function show(Request $request): Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantAuthError();
        }

        $id = $this->accountIdFromRequest($request);
        if ($id <= 0) {
            return $this->merchantError('收款账号编号无效', 422, 201);
        }

        $detail = $this->findAccountDetail((int)($merchant['id'] ?? 0), $id);
        if ($detail === null) {
            return $this->merchantError('商户收款账号不存在', 404, 404);
        }

        return $this->merchantDataSuccess($detail, '商户收款账号详情获取成功');
    }

    public function create(Request $request): Response
    {
        $merchant = $this->writeGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $payload = $this->requestPayload($request);

        try {
            $context = $this->resolveMerchantCreateContext($payload);
            $writePayload = $this->normalizeCreatePayload($payload, $merchantId, $context);
        } catch (\InvalidArgumentException $exception) {
            return $this->merchantError($exception->getMessage(), 422, 201);
        }

        try {
            $accountId = (int)Db::transaction(function () use ($writePayload, $merchantId, $payload): int {
                $now = date('Y-m-d H:i:s');
                $accountId = (int)Db::table(BusinessTable::account())->insertGetId([
                    'code' => $writePayload['code'],
                    'type' => $writePayload['type'],
                    'user_id' => $merchantId,
                    'qr_url' => $writePayload['qr_url'] === '' ? null : $writePayload['qr_url'],
                    // Legacy schema keeps qr_type as NOT NULL, so unsupported plugins must persist an empty string.
                    'qr_type' => (string)$writePayload['qr_type'],
                    'wxname' => $writePayload['wxname'] === '' ? null : $writePayload['wxname'],
                    'zfb_pid' => $writePayload['zfb_pid'] === '' ? null : $writePayload['zfb_pid'],
                    'wx_guid' => $writePayload['wx_guid'] === '' ? null : $writePayload['wx_guid'],
                    'cloud_id' => $writePayload['cloud_id'] === '' ? null : $writePayload['cloud_id'],
                    'qq' => $writePayload['qq'] === '' ? null : $writePayload['qq'],
                    'status' => $writePayload['status'],
                    'is_status' => $writePayload['is_status'],
                    'create_time' => $now,
                    'update_time' => $now,
                    'succcount' => 0,
                    'succprice' => '0.00',
                    'memo' => $writePayload['memo'] === '' ? null : $writePayload['memo'],
                    'cookie' => $writePayload['cookie'] === '' ? null : $writePayload['cookie'],
                    'allmaxcount' => $writePayload['allmaxcount'],
                    'allmaxmoney' => $writePayload['allmaxmoney'] === '' ? null : $writePayload['allmaxmoney'],
                    'daymaxcount' => $writePayload['daymaxcount'],
                    'daymaxmoney' => $writePayload['daymaxmoney'] === '' ? null : $writePayload['daymaxmoney'],
                    'remark' => $writePayload['remark'] === '' ? null : $writePayload['remark'],
                    'money' => '0.00',
                ]);

                $this->saveManagedCredentialConfig(
                    (string)($writePayload['code'] ?? ''),
                    $accountId,
                    $payload,
                    $writePayload
                );

                return $accountId;
            });
        } catch (\InvalidArgumentException $exception) {
            return $this->merchantError($exception->getMessage(), 422, 201);
        }

        $detail = $this->findAccountDetail($merchantId, $accountId);
        if ($detail === null) {
            return $this->merchantError('收款账号创建成功，但详情加载失败', 500, 201);
        }

        return $this->merchantDataSuccess(
            array_merge($detail, [
                'created_account_id' => $accountId,
                'created_account_label' => $this->accountLabel(
                    $this->accountRecord($merchantId, $accountId) ?? ['id' => $accountId],
                    (array)($detail['item'] ?? [])
                ),
            ]),
            '商户收款账号已创建'
        );
    }

    public function uploadCredentialImage(Request $request): Response
    {
        $merchant = $this->writeGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $configuredFileType = SystemConfig::int('file-type', 1);
        if ($configuredFileType !== 1) {
            return $this->merchantError(
                '当前仅支持本地存储模式上传收款码图片',
                422,
                201,
                ['configured_file_type' => $configuredFileType]
            );
        }

        try {
            $target = $this->normalizeCredentialImageTarget($this->requestPayload($request));
            $file = $this->normalizeCredentialImageFile($request);
            $prepared = $this->prepareCredentialImageUpload(
                $file,
                max(1, SystemConfig::int('imageSize', 2000)) * 1024
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->merchantError($exception->getMessage(), 422, 201);
        }

        $absolutePath = '';
        $legacyPath = '';
        try {
            [$photoId, $href] = Db::transaction(function () use ($file, $prepared, &$absolutePath, &$legacyPath): array {
                $dateSegment = date('Ymd');
                $relativeChild = $dateSegment . '/' . date('His') . '_' . bin2hex(random_bytes(8)) . '.' . $prepared['ext'];
                $absolutePath = $this->credentialImageUploadRoot()
                    . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $relativeChild);

                $directory = dirname($absolutePath);
                if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
                    throw new \RuntimeException('failed to prepare merchant payment account upload directory');
                }

                $href = UploadWorkspace::publicHref('payment-accounts', $relativeChild);
                $file->move($absolutePath);
                $legacyPath = UploadWorkspace::mirrorFileToLegacyPublic($absolutePath, 'payment-accounts', $relativeChild);

                $photoId = (int)Db::table('admin_photo')->insertGetId([
                    'name' => $prepared['name'],
                    'href' => $href,
                    'path' => 'payment-accounts',
                    'type' => 1,
                    'ext' => $prepared['ext'],
                    'mime' => $prepared['mime'],
                    'size' => $prepared['size_bytes'],
                    'create_time' => date('Y-m-d H:i:s'),
                ]);

                return [$photoId, $href];
            });
        } catch (\Throwable $exception) {
            if ($absolutePath !== '' && is_file($absolutePath)) {
                @unlink($absolutePath);
            }
            if ($legacyPath !== '' && is_file($legacyPath)) {
                @unlink($legacyPath);
            }

            return $this->merchantError(
                '收款码图片上传失败：' . $exception->getMessage(),
                500,
                201
            );
        }

        return $this->merchantDataSuccess([
            'code' => $target['code'],
            'field' => $target['field'],
            'mode' => 'image',
            'value' => $href,
            'href' => $href,
            'preview_url' => $href,
            'photo_id' => $photoId,
            'path' => 'payment-accounts',
        ], '收款码图片上传成功');
    }

    public function decodeCredentialImage(Request $request): Response
    {
        $merchant = $this->writeGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        try {
            $target = $this->normalizeCredentialDecodeTarget($this->requestPayload($request));
            (new PaymentPluginManager())->credentialDecodeProfile($target['code'], $target['field']);
            $file = $this->normalizeCredentialImageFile($request);
            $this->prepareCredentialImageUpload(
                $file,
                max(1, SystemConfig::int('imageSize', 2000)) * 1024
            );
            $decodedContent = (new QrCodeService())->decodeFile($file->getPathname());
        } catch (\InvalidArgumentException $exception) {
            return $this->merchantError($exception->getMessage(), 422, 201);
        } catch (\DomainException $exception) {
            return $this->merchantError($exception->getMessage(), 422, 201);
        } catch (\Throwable $exception) {
            return $this->merchantError($exception->getMessage(), 500, 201);
        }

        return $this->merchantDataSuccess([
            'code' => $target['code'],
            'field' => $target['field'],
            'mode' => 'decoded_text',
            'value' => $decodedContent,
        ], '二维码内容已解析');
    }

    public function update(Request $request): Response
    {
        $merchant = $this->writeGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $id = $this->accountIdFromRequest($request);
        if ($id <= 0) {
            return $this->merchantError('收款账号编号无效', 422, 201);
        }

        $record = $this->accountRecord($merchantId, $id);
        if ($record === null) {
            return $this->merchantError('商户收款账号不存在', 404, 404);
        }

        $payload = $this->requestPayload($request);

        try {
            $memo = $this->normalizeOptionalText(
                $payload['memo'] ?? ($record['memo'] ?? ''),
                50,
                '账号备注'
            );
            $daymaxcount = $this->normalizeNonNegativeInteger(
                $payload['daymaxcount'] ?? ($record['daymaxcount'] ?? 0),
                '单日笔数限制'
            );
            $daymaxmoney = $this->normalizeOptionalDecimal(
                $payload['daymaxmoney'] ?? ($record['daymaxmoney'] ?? ''),
                50,
                '单日金额限制'
            );
            $allmaxcount = $this->normalizeNonNegativeInteger(
                $payload['allmaxcount'] ?? ($record['allmaxcount'] ?? 0),
                '总笔数限制'
            );
            $allmaxmoney = $this->normalizeOptionalDecimal(
                $payload['allmaxmoney'] ?? ($record['allmaxmoney'] ?? ''),
                50,
                '总金额限制'
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->merchantError($exception->getMessage(), 422, 201);
        }

        Db::table(BusinessTable::account())
            ->where('id', $id)
            ->where('user_id', $merchantId)
            ->update([
                'memo' => $memo === '' ? null : $memo,
                'daymaxcount' => $daymaxcount,
                'daymaxmoney' => $daymaxmoney === '' ? null : $daymaxmoney,
                'allmaxcount' => $allmaxcount,
                'allmaxmoney' => $allmaxmoney === '' ? null : $allmaxmoney,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        $detail = $this->findAccountDetail($merchantId, $id);
        if ($detail === null) {
            return $this->merchantError('收款账号更新成功，但详情加载失败', 500, 201);
        }

        return $this->merchantDataSuccess($detail, '商户收款账号已更新');
    }

    public function updateCredentials(Request $request): Response
    {
        $merchant = $this->writeGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $id = $this->accountIdFromRequest($request);
        if ($id <= 0) {
            return $this->merchantError('收款账号编号无效', 422, 201);
        }

        $record = $this->accountRecord($merchantId, $id);
        if ($record === null) {
            return $this->merchantError('商户收款账号不存在', 404, 404);
        }

        $payload = $this->requestPayload($request);

        try {
            $updates = $this->normalizeCredentialPayload($payload, $record);
        } catch (\InvalidArgumentException $exception) {
            return $this->merchantError($exception->getMessage(), 422, 201);
        }

        $writePayload = ['update_time' => date('Y-m-d H:i:s')];
        foreach ($updates as $key => $value) {
            $writePayload[$key] = is_string($value) && $value === '' ? null : $value;
        }

        try {
            Db::transaction(function () use ($merchantId, $id, $writePayload, $record, $payload, $updates): void {
                Db::table(BusinessTable::account())
                    ->where('id', $id)
                    ->where('user_id', $merchantId)
                    ->update($writePayload);

                $this->saveManagedCredentialConfig(
                    (string)($record['code'] ?? ''),
                    $id,
                    $payload,
                    array_merge($record, $updates)
                );
            });
        } catch (\InvalidArgumentException $exception) {
            return $this->merchantError($exception->getMessage(), 422, 201);
        }

        $detail = $this->findAccountDetail($merchantId, $id);
        if ($detail === null) {
            return $this->merchantError('凭证更新成功，但详情加载失败', 500, 201);
        }

        return $this->merchantDataSuccess($detail, '商户收款账号凭证已更新');
    }

    public function status(Request $request): Response
    {
        $merchant = $this->writeGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $id = $this->accountIdFromRequest($request);
        if ($id <= 0) {
            return $this->merchantError('收款账号编号无效', 422, 201);
        }

        $record = $this->accountRecord($merchantId, $id);
        if ($record === null) {
            return $this->merchantError('商户收款账号不存在', 404, 404);
        }

        $payload = $this->requestPayload($request);
        $updates = [
            'update_time' => date('Y-m-d H:i:s'),
        ];
        $hasChange = false;

        try {
            if (array_key_exists('status', $payload)) {
                $updates['status'] = $this->normalizeOnlineStatus($payload['status']);
                $hasChange = true;
            }

            if (array_key_exists('is_status', $payload)) {
                $updates['is_status'] = $this->normalizeEnabledStatus($payload['is_status']);
                $hasChange = true;
            }

            if (!$hasChange) {
                throw new \InvalidArgumentException('请至少提交一个状态字段');
            }
        } catch (\InvalidArgumentException $exception) {
            return $this->merchantError($exception->getMessage(), 422, 201);
        }

        Db::table(BusinessTable::account())
            ->where('id', $id)
            ->where('user_id', $merchantId)
            ->update($updates);

        return $this->merchantDataSuccess([
            'item' => $this->findAccount($merchantId, $id),
        ], '商户收款账号状态已更新');
    }

    public function deleteAudit(Request $request): Response
    {
        $merchant = $this->writeGuard($request, false);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $id = $this->accountIdFromRequest($request);
        if ($id <= 0) {
            return $this->merchantError('收款账号编号无效', 422, 201);
        }

        $record = $this->accountRecord($merchantId, $id);
        if ($record === null) {
            return $this->merchantError('商户收款账号不存在', 404, 404);
        }

        $item = $this->findAccount($merchantId, $id);

        return $this->merchantDataSuccess([
            'item' => $item,
            'audit' => $this->buildDeleteAudit($record, $item),
        ], '收款账号删除审计获取成功');
    }

    public function delete(Request $request): Response
    {
        $merchant = $this->writeGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $id = $this->accountIdFromRequest($request);
        if ($id <= 0) {
            return $this->merchantError('收款账号编号无效', 422, 201);
        }

        $record = $this->accountRecord($merchantId, $id);
        if ($record === null) {
            return $this->merchantError('商户收款账号不存在', 404, 404);
        }

        $item = $this->findAccount($merchantId, $id);
        $audit = $this->buildDeleteAudit($record, $item);
        if (empty($audit['can_delete'])) {
            return $this->merchantError(
                '当前收款账号仍有关联订单或轮询池，暂时不能删除',
                422,
                201,
                ['audit' => $audit]
            );
        }

        $payload = $this->requestPayload($request);
        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return $this->merchantError(
                '删除确认短语不匹配',
                422,
                201,
                ['audit' => $audit]
            );
        }

        $now = date('Y-m-d H:i:s');
        Db::transaction(function () use ($merchantId, $id, $now, $record): void {
            Db::table(BusinessTable::pollPool())
                ->where('user_id', $merchantId)
                ->where('last_account_id', $id)
                ->update([
                    'last_account_id' => 0,
                    'update_time' => $now,
                ]);

            $this->detachInactiveOrderReferences($merchantId, [$id]);

            Db::table(BusinessTable::account())
                ->where('id', $id)
                ->where('user_id', $merchantId)
                ->delete();

            $this->deleteManagedCredentialConfigRows([
                [
                    'id' => $id,
                    'code' => (string)($record['code'] ?? ''),
                ],
            ]);
        });

        return $this->merchantDataSuccess([
            'deleted_account_id' => $id,
            'deleted_account_label' => (string)($audit['account_label'] ?? ('商户收款账号 #' . $id)),
            'audit' => $audit,
        ], '商户收款账号已删除');
    }

    public function batchDeleteAudit(Request $request): Response
    {
        $merchant = $this->writeGuard($request, false);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $payload = $this->requestPayload($request);

        try {
            $accountIds = $this->normalizeAccountIds($payload['account_ids'] ?? ($payload['ids'] ?? []));
        } catch (\InvalidArgumentException $exception) {
            return $this->merchantError($exception->getMessage(), 422, 201);
        }

        return $this->merchantDataSuccess([
            'audit' => $this->batchDeleteAuditPayload((int)($merchant['id'] ?? 0), $accountIds),
        ], '批量删除审计获取成功');
    }

    public function batchDelete(Request $request): Response
    {
        $merchant = $this->writeGuard($request);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $payload = $this->requestPayload($request);

        try {
            $accountIds = $this->normalizeAccountIds($payload['account_ids'] ?? ($payload['ids'] ?? []));
        } catch (\InvalidArgumentException $exception) {
            return $this->merchantError($exception->getMessage(), 422, 201);
        }

        $audit = $this->batchDeleteAuditPayload($merchantId, $accountIds);
        if (empty($audit['can_delete_all'])) {
            return $this->merchantError(
                '所选收款账号中仍存在不可删除项',
                422,
                201,
                ['audit' => $audit]
            );
        }

        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return $this->merchantError(
                '批量删除确认短语不匹配',
                422,
                201,
                ['audit' => $audit]
            );
        }

        $deletableAccountIds = array_values(array_map(
            'intval',
            (array)($audit['deletable_account_ids'] ?? [])
        ));

        if ($deletableAccountIds !== []) {
            $now = date('Y-m-d H:i:s');
            $rows = $this->loadAccountRowsByIds($merchantId, $deletableAccountIds);
            Db::transaction(function () use ($merchantId, $deletableAccountIds, $now, $rows): void {
                Db::table(BusinessTable::pollPool())
                    ->where('user_id', $merchantId)
                    ->whereIn('last_account_id', $deletableAccountIds)
                    ->update([
                        'last_account_id' => 0,
                        'update_time' => $now,
                    ]);

                $this->detachInactiveOrderReferences($merchantId, $deletableAccountIds);

                Db::table(BusinessTable::account())
                    ->where('user_id', $merchantId)
                    ->whereIn('id', $deletableAccountIds)
                    ->delete();

                $this->deleteManagedCredentialConfigRows($rows);
            });
        }

        return $this->merchantDataSuccess([
            'deleted_account_ids' => $deletableAccountIds,
            'deleted_count' => count($deletableAccountIds),
            'audit' => $audit,
        ], '商户收款账号已批量删除');
    }

    public function testPay(Request $request): Response
    {
        $merchant = $this->writeGuard($request, false);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        if (SystemConfig::int('is_channelPay', 0) !== 1) {
            return $this->merchantError('管理员尚未开启通道测试支付', 403, 202);
        }

        $merchantId = (int)($merchant['id'] ?? 0);
        $id = $this->accountIdFromRequest($request);
        if ($id <= 0) {
            return $this->merchantError('收款账号编号无效', 422, 201);
        }

        $record = $this->accountRecord($merchantId, $id);
        if ($record === null) {
            return $this->merchantError('商户收款账号不存在', 404, 404);
        }

        $requestPayload = $this->requestPayload($request);

        try {
            $payload = $this->createTestPayPayload($merchant, $record, $request, $requestPayload);
        } catch (\InvalidArgumentException $exception) {
            return $this->merchantError($exception->getMessage(), 422, 201);
        } catch (\RuntimeException $exception) {
            return $this->merchantError('测试支付创建失败：' . $exception->getMessage(), 500, 201);
        }

        return $this->merchantDataSuccess($payload, '测试支付已创建');
    }

    public function testPayPoll(Request $request): Response
    {
        $merchant = $this->writeGuard($request, false);
        if ($merchant instanceof Response) {
            return $merchant;
        }

        if (SystemConfig::int('is_channelPay', 0) !== 1) {
            return $this->merchantError('管理员尚未开启通道测试支付', 403, 202);
        }

        $outTradeNo = trim((string)($request->input('out_trade_no', '') ?: $request->get('out_trade_no', '')));
        if ($outTradeNo === '') {
            return $this->merchantError('缺少测试订单号', 422, 201);
        }

        $order = $this->findMerchantTestPayOrder((int)($merchant['id'] ?? 0), $outTradeNo);
        if ($order === null) {
            return $this->merchantError('未找到对应的测试订单', 404, 404);
        }

        return $this->merchantDataSuccess(
            $this->buildTestPayStatusPayload($order, $request),
            '测试支付状态获取成功'
        );
    }

    /**
     * @param array<string, mixed> $merchant
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    private function createTestPayPayload(
        array $merchant,
        array $account,
        Request $request,
        array $payload = []
    ): array
    {
        if ((int)($account['status'] ?? 0) !== 1) {
            throw new \InvalidArgumentException('当前通道处于离线状态，无法发起测试支付');
        }

        if ((int)($account['is_status'] ?? 0) !== 1) {
            throw new \InvalidArgumentException('当前通道收款开关已关闭，无法发起测试支付');
        }

        $accountId = (int)($account['id'] ?? 0);
        $code = strtolower(trim((string)($account['code'] ?? '')));
        $baseAmount = $this->resolveRequestedTestPayAmount($payload);
        $resolvedAmount = $this->resolveUniquePendingTestPayAmount($baseAmount, $accountId, $code);
        $tradeNo = $this->nextTestTradeNo();
        $outTradeNo = $this->nextTestOutTradeNo();
        $orderType = strtolower(trim((string)($account['type'] ?? '')));

        if ($orderType === '') {
            throw new \InvalidArgumentException('当前通道未声明支付方式');
        }

        $orderPayload = match ($code) {
            'alipay_software', 'alipay_mck' => $this->buildAlipayStyleTestPayOrder(
                $merchant,
                $account,
                $request,
                $tradeNo,
                $outTradeNo,
                $baseAmount,
                $resolvedAmount,
                $code
            ),
            'alipay_bill' => $this->buildAlipayBillTestPayOrder(
                $merchant,
                $account,
                $request,
                $tradeNo,
                $outTradeNo,
                $baseAmount,
                $resolvedAmount
            ),
            'alipay_official' => $this->buildAlipayOfficialTestPayOrder(
                $merchant,
                $account,
                $request,
                $tradeNo,
                $outTradeNo,
                $baseAmount,
                $resolvedAmount
            ),
            'wxpay_v3' => $this->buildManagedGatewayTestPayOrder(
                $merchant,
                $account,
                $request,
                $tradeNo,
                $outTradeNo,
                $baseAmount,
                $resolvedAmount,
                'wxpay',
                $payload,
                new WxpayV3GatewayService()
            ),
            'universal_epay' => $this->buildManagedGatewayTestPayOrder(
                $merchant,
                $account,
                $request,
                $tradeNo,
                $outTradeNo,
                $baseAmount,
                $resolvedAmount,
                $orderType,
                $payload,
                new UniversalEpayGatewayService()
            ),
            'wxpay_software' => $this->buildStaticQrTestPayOrder(
                $merchant,
                $account,
                $request,
                $tradeNo,
                $outTradeNo,
                $baseAmount,
                $resolvedAmount,
                'wxpay',
                trim((string)($account['qr_url'] ?? '')),
                'weixin://',
                '微信软件版二维码内容未配置'
            ),
            'qqpay_software' => $this->buildStaticQrTestPayOrder(
                $merchant,
                $account,
                $request,
                $tradeNo,
                $outTradeNo,
                $baseAmount,
                $resolvedAmount,
                'qqpay',
                trim((string)($account['qr_url'] ?? '')),
                '',
                'QQ 软件版二维码内容未配置'
            ),
            'usdt' => $this->buildStaticQrTestPayOrder(
                $merchant,
                $account,
                $request,
                $tradeNo,
                $outTradeNo,
                $baseAmount,
                $resolvedAmount,
                'usdt',
                trim((string)($account['wxname'] ?? '')),
                trim((string)($account['wxname'] ?? '')),
                'USDT 钱包地址未配置'
            ),
            'jiaofeiyi_alipay', 'jiaofeiyi_wxpay' => $this->buildJiaofeiyiTestPayOrder(
                $merchant,
                $account,
                $request,
                $tradeNo,
                $outTradeNo,
                $baseAmount,
                $resolvedAmount,
                $code === 'jiaofeiyi_alipay' ? 'alipay' : 'wxpay'
            ),
            default => throw new \InvalidArgumentException('当前插件暂不支持测试支付'),
        };

        Db::table(BusinessTable::order())->insert($orderPayload['insert']);

        if (isset($orderPayload['after_create']) && is_callable($orderPayload['after_create'])) {
            try {
                $orderPayload['after_create']();
            } catch (\Throwable $exception) {
                Db::table(BusinessTable::order())->where('trade_no', $tradeNo)->delete();
                throw new \RuntimeException($exception->getMessage(), previous: $exception);
            }
        }

        $order = $this->findMerchantTestPayOrderByTradeNo((int)($merchant['id'] ?? 0), $tradeNo);
        if ($order === null) {
            throw new \RuntimeException('测试订单已创建，但读取结果失败');
        }

        if (in_array($code, ['alipay_bill', 'alipay_mck', 'usdt', 'jiaofeiyi_alipay', 'jiaofeiyi_wxpay', 'wxpay_v3'], true)) {
            $reconcileOrder = Db::table(BusinessTable::order())
                ->select(
                    'id',
                    'user_id',
                    'account_id',
                    'trade_no',
                    'out_trade_no',
                    'type',
                    'out_time',
                    'alipay_order_no',
                    'status'
                )
                ->where('trade_no', $tradeNo)
                ->first();

            if ($reconcileOrder) {
                $reconcilePayload = (array)$reconcileOrder;
                $reconcilePayload['code'] = $code;
                (new OrderReconcileTaskService())->enqueueForOrder($reconcilePayload);
            }
        }

        return $this->buildTestPayStatusPayload($order, $request);
    }

    /**
     * @param array<string, mixed> $merchant
     * @param array<string, mixed> $account
     * @return array{insert: array<string, mixed>}
     */
    private function buildStaticQrTestPayOrder(
        array $merchant,
        array $account,
        Request $request,
        string $tradeNo,
        string $outTradeNo,
        float $baseAmount,
        float $resolvedAmount,
        string $orderType,
        string $qrcode,
        string $h5QrUrl,
        string $emptyMessage
    ): array {
        if ($qrcode === '') {
            throw new \InvalidArgumentException($emptyMessage);
        }

        return [
            'insert' => $this->baseTestPayOrderInsert(
                $merchant,
                $account,
                $request,
                $tradeNo,
                $outTradeNo,
                $baseAmount,
                $resolvedAmount,
                $orderType,
                $qrcode,
                $h5QrUrl
            ),
        ];
    }

    /**
     * @param array<string, mixed> $merchant
     * @param array<string, mixed> $account
     * @return array{insert: array<string, mixed>}
     */
    private function buildAlipayStyleTestPayOrder(
        array $merchant,
        array $account,
        Request $request,
        string $tradeNo,
        string $outTradeNo,
        float $baseAmount,
        float $resolvedAmount,
        string $code
    ): array {
        $cashierMode = $this->merchantCashierMode((int)($merchant['id'] ?? 0));
        $useStaticQr = $cashierMode === '3' && $code !== 'alipay_mck';

        if ($useStaticQr) {
            $qrcode = trim((string)($account['qr_url'] ?? ''));
            if ($qrcode === '') {
                throw new \InvalidArgumentException('支付宝收款码未配置');
            }

            $imageOrUrl = $this->looksLikeCredentialImageReference($qrcode) || preg_match('/^(https?:\/\/|data:image)/i', $qrcode) === 1
                ? $this->absoluteAssetUrl($qrcode, $request)
                : $qrcode;

            return [
                'insert' => $this->baseTestPayOrderInsert(
                    $merchant,
                    $account,
                    $request,
                    $tradeNo,
                    $outTradeNo,
                    $baseAmount,
                    $resolvedAmount,
                    'alipay',
                    $imageOrUrl,
                    'alipayqr://platformapi/startapp?saId=10000007&qrcode=' . rawurlencode($imageOrUrl)
                ),
            ];
        }

        $userId = trim((string)($account['zfb_pid'] ?? ''));
        if ($userId === '') {
            throw new \InvalidArgumentException('支付宝 PID 未配置');
        }

        $amountText = number_format($resolvedAmount, 2, '.', '');
        $bridgeUrl = $this->buildAlipayTransferBridgeUrl($request, $userId, $amountText, $outTradeNo);
        $jumpUrl = $this->buildAlipayTransferJumpUrl($userId, $amountText, $outTradeNo);

        return [
            'insert' => $this->baseTestPayOrderInsert(
                $merchant,
                $account,
                $request,
                $tradeNo,
                $outTradeNo,
                $baseAmount,
                $resolvedAmount,
                'alipay',
                $bridgeUrl,
                $jumpUrl
            ),
        ];
    }

    /**
     * @param array<string, mixed> $merchant
     * @param array<string, mixed> $account
     * @return array{insert: array<string, mixed>}
     */
    private function buildAlipayBillTestPayOrder(
        array $merchant,
        array $account,
        Request $request,
        string $tradeNo,
        string $outTradeNo,
        float $baseAmount,
        float $resolvedAmount
    ): array {
        $billPayload = $this->decodeBillCredentialPayload($account);
        $qrcode = trim((string)($billPayload['qrcode'] ?? ''));
        if ($qrcode === '') {
            throw new \InvalidArgumentException('支付宝账单二维码内容未配置');
        }

        $qrcode = $this->looksLikeCredentialImageReference($qrcode) || preg_match('/^(https?:\/\/|data:image)/i', $qrcode) === 1
            ? $this->absoluteAssetUrl($qrcode, $request)
            : $qrcode;

        return [
            'insert' => $this->baseTestPayOrderInsert(
                $merchant,
                $account,
                $request,
                $tradeNo,
                $outTradeNo,
                $baseAmount,
                $resolvedAmount,
                'alipay',
                $qrcode,
                'alipayqr://platformapi/startapp?saId=10000007&qrcode=' . rawurlencode($qrcode)
            ),
        ];
    }

    /**
     * @param array<string, mixed> $merchant
     * @param array<string, mixed> $account
     * @return array{insert: array<string, mixed>, after_create: callable(): void}
     */
    private function buildAlipayOfficialTestPayOrder(
        array $merchant,
        array $account,
        Request $request,
        string $tradeNo,
        string $outTradeNo,
        float $baseAmount,
        float $resolvedAmount
    ): array {
        return $this->buildManagedGatewayTestPayOrder(
            $merchant,
            $account,
            $request,
            $tradeNo,
            $outTradeNo,
            $baseAmount,
            $resolvedAmount,
            'alipay',
            [],
            new AlipayOfficialGatewayService()
        );
    }

    /**
     * @param array<string, mixed> $merchant
     * @param array<string, mixed> $account
     * @param array<string, mixed> $payload
     * @return array{insert: array<string, mixed>, after_create: callable(): void}
     */
    private function buildManagedGatewayTestPayOrder(
        array $merchant,
        array $account,
        Request $request,
        string $tradeNo,
        string $outTradeNo,
        float $baseAmount,
        float $resolvedAmount,
        string $orderType,
        array $payload,
        AbstractManagedGatewayOrderService $service
    ): array {
        $insert = $this->baseTestPayOrderInsert(
            $merchant,
            $account,
            $request,
            $tradeNo,
            $outTradeNo,
            $baseAmount,
            $resolvedAmount,
            $orderType,
            'ewmLoading',
            ''
        );

        return [
            'insert' => $insert,
            'after_create' => function () use ($merchant, $account, $request, $tradeNo, $payload, $service): void {
                $order = $this->findMerchantTestPayOrderByTradeNo((int)($merchant['id'] ?? 0), $tradeNo);
                if ($order === null) {
                    throw new \RuntimeException('测试订单已创建，但读取结果失败');
                }

                $service->attachGatewayToOrder($merchant, $account, array_merge($payload, [
                    '_origin' => $this->requestOrigin($request),
                    '_request_host' => (string)$request->host(),
                    '_request_scheme' => $this->requestScheme($request),
                    '_client_ip' => (string)$request->getRealIp(),
                ]), $order);
            },
        ];
    }

    /**
     * @param array<string, mixed> $merchant
     * @param array<string, mixed> $account
     * @return array{insert: array<string, mixed>, after_create: callable(): void}
     */
    private function buildJiaofeiyiTestPayOrder(
        array $merchant,
        array $account,
        Request $request,
        string $tradeNo,
        string $outTradeNo,
        float $baseAmount,
        float $resolvedAmount,
        string $orderType
    ): array {
        $insert = $this->baseTestPayOrderInsert(
            $merchant,
            $account,
            $request,
            $tradeNo,
            $outTradeNo,
            $baseAmount,
            $resolvedAmount,
            $orderType,
            'ewmLoading',
            ''
        );

        return [
            'insert' => $insert,
            'after_create' => function () use ($account, $tradeNo, $resolvedAmount, $orderType): void {
                $result = $this->jiaofeiyi()->createCashierPay($account, $resolvedAmount);
                $storeNo = trim((string)($result['sys_trade_no'] ?? ''));
                if ($storeNo === '') {
                    $storeNo = trim((string)($result['pay_order_no'] ?? ''));
                }
                if ($storeNo === '') {
                    $storeNo = trim((string)($result['channel_trade_no'] ?? ''));
                }

                $updates = [
                    'type' => $orderType,
                    'qrcode' => (string)($result['pay_url'] ?? ''),
                    'h5_qrurl' => (string)($result['pay_url'] ?? ''),
                ];
                if ($storeNo !== '') {
                    $updates['alipay_order_no'] = $storeNo;
                }

                Db::table(BusinessTable::order())->where('trade_no', $tradeNo)->update($updates);

                return;

                $jfyConfig = $this->decodeJiaofeiyiConfig($account);
                $cashierTemplateName = trim((string)($jfyConfig['store_name'] ?? ''));
                if ($cashierTemplateName === '') {
                    $cashierTemplateName = trim((string)SystemConfig::get('sitename', 'cashier')) ?: 'cashier';
                }

                $timeKey = (int)(microtime(true) * 1000);
                $requestBody = [
                    'merchId' => $merchId,
                    'tradeAmount' => number_format($resolvedAmount, 2, '.', ''),
                    'remark' => trim((string)($account['qr_url'] ?? '')),
                    'orderTemplateData' => [[
                        'key' => $timeKey,
                        'type' => 'number',
                        'index' => 0,
                        'label' => '支付金额',
                        'value' => number_format($resolvedAmount, 2, '.', ''),
                        'origin' => 'number' . $timeKey . '0',
                        'options' => [
                            'label' => '支付金额',
                            'content' => number_format($resolvedAmount, 2, '.', ''),
                            'required' => true,
                            'labelAlign' => '',
                        ],
                        'displayName' => '金额类型',
                        'formItemFlag' => false,
                        'settingsTitle' => '金额类型设置',
                        'marginLeftRight' => 10,
                        'marginTopBottom' => 5,
                        'cashierTemplateName' => $cashierTemplateName,
                        'state' => true,
                    ]],
                ];

                $headers = [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    'Content-Type: application/json;charset=utf-8',
                ];

                $clientIp = trim((string)($account['remark'] ?? ''));
                if ($clientIp !== '' && !$this->jiaofeiyiIsHttpUrl($clientIp)) {
                    $headers[] = 'X-FORWARDED-FOR: ' . $clientIp;
                    $headers[] = 'CLIENT-IP: ' . $clientIp;
                }

                $response = $this->jiaofeiyiRequestWithFallback(
                    $account,
                    'https://jfyconsole.lakala.com/order/api/cashier/pay',
                    $requestBody,
                    $headers
                );

                $payUrl = $this->extractRecursiveValue($response, ['payUrl', 'pay_url', 'url', 'codeUrl', 'counterUrl']);
                if ($payUrl === null || trim((string)$payUrl) === '') {
                    throw new \RuntimeException('缴费易未返回支付地址');
                }

                $sysTradeNo = $this->extractRecursiveValue($response, ['sysTradeNo', 'sys_trade_no', 'tradeNo', 'trade_no', 'orderNo', 'outOrderNo']);
                $payOrderNo = $this->extractJiaofeiyiPayOrderNo((string)$payUrl, $response);
                $storeNo = trim((string)$sysTradeNo);
                if ($storeNo === '') {
                    $storeNo = trim((string)$payOrderNo);
                }

                $updates = [
                    'type' => $orderType,
                    'qrcode' => (string)$payUrl,
                    'h5_qrurl' => (string)$payUrl,
                ];
                if ($storeNo !== '') {
                    $updates['alipay_order_no'] = $storeNo;
                }

                Db::table(BusinessTable::order())->where('trade_no', $tradeNo)->update($updates);
            },
        ];
    }

    /**
     * @param array<string, mixed> $merchant
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    private function baseTestPayOrderInsert(
        array $merchant,
        array $account,
        Request $request,
        string $tradeNo,
        string $outTradeNo,
        float $baseAmount,
        float $resolvedAmount,
        string $orderType,
        string $qrcode,
        string $h5QrUrl
    ): array {
        return [
            'name' => $this->resolveTestPayName(),
            'sitename' => trim((string)SystemConfig::get('sitename', '')),
            'type' => $orderType,
            'account_id' => (int)($account['id'] ?? 0),
            'trade_no' => $tradeNo,
            'out_trade_no' => $outTradeNo,
            'notify_url' => '',
            'return_url' => '',
            'user_id' => (int)($merchant['id'] ?? 0),
            'pay_type' => 1,
            'money' => number_format($baseAmount, 2, '.', ''),
            'truemoney' => number_format($resolvedAmount, 2, '.', ''),
            'feilvmoney' => '0.000',
            'status' => 0,
            'return_num' => 0,
            'ip' => trim((string)$request->getRealIp()),
            'qrcode' => $qrcode,
            'h5_qrurl' => $h5QrUrl,
            'api_memo' => 'merchant_channel_test_pay',
            'out_time' => time() + $this->resolveTestPayTimeoutSeconds((int)($merchant['id'] ?? 0)),
            'create_time' => date('Y-m-d H:i:s'),
        ];
    }

    private function resolveTestPayBaseAmount(): float
    {
        $amount = SystemConfig::float('demopay_money', 0.01);
        if ($amount <= 0) {
            $amount = 0.01;
        }

        $min = SystemConfig::float('min_orderprice', 0);
        $max = SystemConfig::float('max_orderprice', 0);
        if ($min > 0 && $amount < $min) {
            $amount = $min;
        }
        if ($max > 0 && $amount > $max) {
            $amount = $max;
        }

        return (float)number_format($amount, 2, '.', '');
    }

    private function resolveRequestedTestPayAmount(array $payload): float
    {
        $candidate = $payload['pay_amount'] ?? ($payload['money'] ?? null);
        if ($candidate === null || trim((string)$candidate) === '') {
            return $this->resolveTestPayBaseAmount();
        }

        $normalized = $this->normalizeOptionalDecimal($candidate, 20, '测试金额');
        $amount = (float)$normalized;
        if ($amount <= 0) {
            throw new \InvalidArgumentException('测试金额必须大于 0');
        }

        $min = SystemConfig::float('min_orderprice', 0);
        if ($min > 0 && $amount < $min) {
            throw new \InvalidArgumentException(
                '测试金额不能低于 ' . number_format($min, 2, '.', '') . ' 元'
            );
        }

        $max = SystemConfig::float('max_orderprice', 0);
        if ($max > 0 && $amount > $max) {
            throw new \InvalidArgumentException(
                '测试金额不能高于 ' . number_format($max, 2, '.', '') . ' 元'
            );
        }

        return (float)number_format($amount, 2, '.', '');
    }

    private function resolveTestPayName(): string
    {
        $name = trim((string)SystemConfig::get('demopay_name', ''));
        return $name !== '' ? $name : '通道测试支付';
    }

    private function resolveTestPayTimeoutSeconds(int $merchantId): int
    {
        $timeout = SystemConfig::int('timeout', 180);
        if ($timeout <= 0) {
            $timeout = 180;
        }

        $basic = Db::table(BusinessTable::userBasic())
            ->select('timeout_time')
            ->where('user_id', $merchantId)
            ->first();

        $basicTimeout = (int)(($basic ? (array)$basic : [])['timeout_time'] ?? 0);
        if ($basicTimeout > 0) {
            $timeout = min($timeout, $basicTimeout);
        }

        return max(60, $timeout);
    }

    private function resolveUniquePendingTestPayAmount(
        float $amount,
        int $accountId,
        string $accountCode,
        int $maxRetry = 15
    ): float
    {
        $current = (float)number_format($amount, 2, '.', '');
        $offsets = [0.01, 0.02, 0.03, 0.04, 0.05, 0.06, 0.07, 0.08, 0.09, 0.10];
        $graceSeconds = in_array(strtolower(trim($accountCode)), self::ALIPAY_BILL_RECONCILE_CODES, true)
            ? self::ALIPAY_BILL_RECONCILE_GRACE_SECONDS
            : 0;
        $reservationCutoff = time() - $graceSeconds;

        for ($attempt = 1; $attempt <= $maxRetry; $attempt++) {
            $exists = Db::table(BusinessTable::order())
                ->where('account_id', $accountId)
                ->where('status', 0)
                ->where('out_time', '>', $reservationCutoff)
                ->where('truemoney', number_format($current, 2, '.', ''))
                ->exists();

            if (!$exists) {
                return (float)number_format($current, 2, '.', '');
            }

            $offset = $offsets[array_rand($offsets)];
            $current = (float)number_format($current + $offset, 2, '.', '');
        }

        throw new \InvalidArgumentException('测试金额冲突过多，请稍后重试');
    }

    private function merchantCashierMode(int $merchantId): string
    {
        $row = Db::table(BusinessTable::userBasic())
            ->select('cashierMode')
            ->where('user_id', $merchantId)
            ->first();

        $mode = trim((string)(($row ? (array)$row : [])['cashierMode'] ?? '2'));
        return $mode !== '' ? $mode : '2';
    }

    private function nextTestTradeNo(): string
    {
        $prefix = SystemConfig::int('isDiy_orderNo', 0) === 1
            ? trim((string)SystemConfig::get('diy_orderNo', ''))
            : 'Y';
        if ($prefix === '') {
            $prefix = 'Y';
        }

        return $prefix . date('YmdHis') . random_int(11111, 99999);
    }

    private function nextTestOutTradeNo(): string
    {
        return 'TEST' . date('YmdHis') . random_int(11111, 99999);
    }

    private function buildAlipayTransferBridgeUrl(Request $request, string $userId, string $amount, string $memo): string
    {
        return \app\support\FrontendUrlBuilder::publicApiUrl($request, 'url', [
            'user_id' => $userId,
            'price' => $amount,
            'trade_no' => $memo,
        ]);
    }

    private function buildAlipayTransferJumpUrl(string $userId, string $amount, string $memo): string
    {
        $payload = rawurlencode(json_encode([
            's' => 'money',
            'u' => $userId,
            'a' => $amount,
            'm' => $memo,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return 'alipayqr://platformapi/startapp?saId=20000032&url=' . urlencode(
            'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data=' . $payload
        );
    }

    private function findMerchantTestPayOrder(int $merchantId, string $outTradeNo): ?array
    {
        $row = Db::table(BusinessTable::order('orders'))
            ->leftJoin(BusinessTable::account('account'), 'orders.account_id', '=', 'account.id')
            ->select(
                'orders.id',
                'orders.trade_no',
                'orders.out_trade_no',
                'orders.money',
                'orders.truemoney',
                'orders.status',
                'orders.out_time',
                'orders.qrcode',
                'orders.h5_qrurl',
                'orders.type',
                'orders.account_id',
                'account.code as account_code'
            )
            ->where('orders.user_id', $merchantId)
            ->whereIn('orders.api_memo', self::TEST_PAY_ORDER_MEMOS)
            ->where('orders.out_trade_no', $outTradeNo)
            ->orderByDesc('orders.id')
            ->first();

        return $row ? (array)$row : null;
    }

    private function findMerchantTestPayOrderByTradeNo(int $merchantId, string $tradeNo): ?array
    {
        $row = Db::table(BusinessTable::order('orders'))
            ->leftJoin(BusinessTable::account('account'), 'orders.account_id', '=', 'account.id')
            ->select(
                'orders.id',
                'orders.trade_no',
                'orders.out_trade_no',
                'orders.money',
                'orders.truemoney',
                'orders.status',
                'orders.out_time',
                'orders.qrcode',
                'orders.h5_qrurl',
                'orders.type',
                'orders.account_id',
                'account.code as account_code'
            )
            ->where('orders.user_id', $merchantId)
            ->whereIn('orders.api_memo', self::TEST_PAY_ORDER_MEMOS)
            ->where('orders.trade_no', $tradeNo)
            ->orderByDesc('orders.id')
            ->first();

        return $row ? (array)$row : null;
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private function buildTestPayStatusPayload(array $order, Request $request): array
    {
        $state = $this->resolveTestPayState($order);
        $rawQrCode = trim((string)($order['qrcode'] ?? ''));
        $qrPayload = $this->buildTestPayQrPayload($rawQrCode, $request);
        $directOpenUrl = trim((string)($order['h5_qrurl'] ?? ''));

        return [
            'state' => $state,
            'state_label' => $this->testPayStateLabel($state),
            'state_message' => $this->testPayStateMessage($state),
            'can_poll' => !in_array($state, ['paid', 'timeout'], true),
            'trade_no' => trim((string)($order['trade_no'] ?? '')),
            'out_trade_no' => trim((string)($order['out_trade_no'] ?? '')),
            'pay_amount' => number_format(
                (float)($order['truemoney'] ?? $order['money'] ?? 0),
                2,
                '.',
                ''
            ),
            'type' => trim((string)($order['type'] ?? '')),
            'pay_url' => $this->testPayConsoleUrl($request, trim((string)($order['trade_no'] ?? ''))),
            'direct_open_url' => $directOpenUrl !== '' ? $directOpenUrl : null,
            'display_mode' => $qrPayload['display_mode'],
            'qrcode_url' => $qrPayload['qrcode_url'],
            'raw_qrcode' => $rawQrCode,
            'is_paid' => $state === 'paid',
            'is_timeout' => $state === 'timeout',
        ];
    }

    /**
     * @param array<string, mixed> $order
     */
    private function resolveTestPayState(array $order): string
    {
        if ((int)($order['status'] ?? 0) === 1) {
            return 'paid';
        }

        $outTime = (int)($order['out_time'] ?? 0);
        $now = time();
        $accountCode = strtolower(trim((string)($order['account_code'] ?? '')));
        $reconcileGraceSeconds = in_array($accountCode, self::ALIPAY_BILL_RECONCILE_CODES, true)
            ? self::ALIPAY_BILL_RECONCILE_GRACE_SECONDS
            : ($accountCode === 'usdt'
                ? self::USDT_RECONCILE_GRACE_SECONDS
                : ($accountCode === 'wxpay_v3' ? self::WXPAY_V3_RECONCILE_GRACE_SECONDS : 0));
        if (
            $reconcileGraceSeconds > 0
            && $outTime > 0
            && $outTime <= $now
            && $now < $outTime + $reconcileGraceSeconds
        ) {
            return 'reconciling';
        }

        if ($outTime > 0 && $outTime <= $now) {
            return 'timeout';
        }

        $qrcode = trim((string)($order['qrcode'] ?? ''));
        if ($qrcode === 'ewmLoading') {
            return 'loading';
        }

        if ($qrcode === '') {
            return 'missing';
        }

        return 'ready';
    }

    /**
     * @return array{display_mode: string, qrcode_url: ?string}
     */
    private function buildTestPayQrPayload(string $value, Request $request): array
    {
        $normalized = trim($value);
        if ($normalized === '' || $normalized === 'ewmLoading') {
            return [
                'display_mode' => 'none',
                'qrcode_url' => null,
            ];
        }

        if ($this->looksLikeCredentialImageReference($normalized) || preg_match('/^(data:image\/|https?:\/\/.+\.(png|jpe?g|gif|bmp|webp|svg)(\?.*)?$)/i', $normalized) === 1) {
            return [
                'display_mode' => 'image',
                'qrcode_url' => $this->absoluteAssetUrl($normalized, $request),
            ];
        }

        return [
            'display_mode' => 'qrcode',
            'qrcode_url' => \app\support\FrontendUrlBuilder::publicQrCodeUrl($request, $normalized, 350),
        ];
    }

    private function absoluteAssetUrl(string $value, Request $request): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        if (preg_match('/^(https?:\/\/|data:image\/|alipayqr:\/\/|alipays:\/\/|weixin:\/\/|mqqapi:\/\/)/i', $normalized) === 1) {
            return $normalized;
        }

        if (str_starts_with($normalized, '//')) {
            return $this->requestScheme($request) . ':' . $normalized;
        }

        return $this->requestOrigin($request) . ($normalized[0] === '/' ? '' : '/') . $normalized;
    }

    private function testPayConsoleUrl(Request $request, string $tradeNo): string
    {
        return $this->requestOrigin($request) . '/api/public/cashier/console?trade_no=' . rawurlencode($tradeNo);
    }

    private function testPayStateLabel(string $state): string
    {
        return match ($state) {
            'paid' => '已支付',
            'reconciling' => '到账核对中',
            'timeout' => '已超时',
            'loading' => '生成中',
            'missing' => '待生成',
            default => '待支付',
        };
    }

    private function testPayStateMessage(string $state): string
    {
        return match ($state) {
            'paid' => '测试订单已支付完成。',
            'reconciling' => '支付时限已到，系统仍在核对到账记录，请稍候。',
            'timeout' => '测试订单已超时，请重新发起。',
            'loading' => '上游通道正在生成二维码，请稍候刷新。',
            'missing' => '二维码尚未返回，请稍候刷新。',
            default => '二维码已就绪，可直接扫码测试。',
        };
    }

    /**
     * @param array<string, mixed> $account
     * @param array<int, string> $headers
     * @param array<string, mixed> $requestBody
     * @return array<string, mixed>
     */
    private function jiaofeiyiRequestWithFallback(
        array $account,
        string $targetUrl,
        array $requestBody,
        array $headers
    ): array {
        return $this->jiaofeiyi()->requestWithFallback($account, $targetUrl, $requestBody, $headers, 'pay');

        $remoteApiUrl = trim((string)($this->decodeJiaofeiyiConfig($account)['remote_api_url'] ?? ''));

        try {
            return $this->jiaofeiyiCurlRequest($targetUrl, $requestBody, $headers);
        } catch (\Throwable $directException) {
            if (!$this->jiaofeiyiIsHttpUrl($remoteApiUrl)) {
                throw new \RuntimeException($directException->getMessage(), previous: $directException);
            }

            $proxyResponse = $this->jiaofeiyiCurlRequest($remoteApiUrl, [
                'scene' => 'pay',
                'target_url' => $targetUrl,
                'method' => 'POST',
                'data' => $requestBody,
                'headers' => array_values($headers),
                'timestamp' => time(),
            ], [
                'Content-Type: application/json;charset=utf-8',
                'Accept: application/json,text/plain,*/*',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            ]);

            if (isset($proxyResponse['code']) && (int)$proxyResponse['code'] !== 0) {
                throw new \RuntimeException((string)($proxyResponse['msg'] ?? '远程接口返回失败'));
            }

            if (is_array($proxyResponse['data'] ?? null)) {
                return (array)$proxyResponse['data'];
            }

            if (is_array($proxyResponse['result'] ?? null)) {
                return (array)$proxyResponse['result'];
            }

            return $proxyResponse;
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    private function jiaofeiyiCurlRequest(string $url, array $payload, array $headers): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('请求失败: ' . $error);
        }

        curl_close($ch);
        $decoded = $this->decodeJsonText((string)$response);
        if (!is_array($decoded)) {
            throw new \RuntimeException('响应数据解析失败');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonText(string $response): ?array
    {
        return $this->jiaofeiyi()->decodeJsonText($response);

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

    private function jiaofeiyiIsHttpUrl(string $url): bool
    {
        return $this->jiaofeiyi()->isHttpUrl($url);

        return trim($url) !== '' && preg_match('/^https?:\\/\\/.+/i', trim($url)) === 1;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $keys
     */
    private function extractRecursiveValue(array $data, array $keys): mixed
    {
        return $this->jiaofeiyi()->extractValueRecursive($data, $keys);

        foreach ($keys as $key) {
            $value = $this->findRecursiveValue($data, (string)$key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function findRecursiveValue(array $data, string $targetKey): mixed
    {
        foreach ($data as $key => $value) {
            if ((string)$key === $targetKey && !is_array($value)) {
                return $value;
            }

            if (is_array($value)) {
                $found = $this->findRecursiveValue($value, $targetKey);
                if ($found !== null && $found !== '') {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $response
     */
    private function extractJiaofeiyiPayOrderNo(string $payUrl, ?array $response = null): string
    {
        return $this->jiaofeiyi()->extractPayOrderNo($payUrl, $response);

        if (is_array($response)) {
            $fromResponse = $this->extractRecursiveValue($response, ['payOrderNo', 'pay_order_no']);
            if ($fromResponse !== null && trim((string)$fromResponse) !== '') {
                return trim((string)$fromResponse);
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
                foreach (['payOrderNo', 'pay_order_no'] as $field) {
                    if (!empty($queryData[$field])) {
                        return trim((string)$queryData[$field]);
                    }
                }
            }

            if (preg_match('/(?:^|[?&])payOrderNo=([^&#]+)/i', $decodedUrl, $matches) === 1) {
                return trim(rawurldecode((string)$matches[1]));
            }

            if (preg_match('/(?:^|[?&])pay_order_no=([^&#]+)/i', $decodedUrl, $matches) === 1) {
                return trim(rawurldecode((string)$matches[1]));
            }

            $next = rawurldecode($decodedUrl);
            if ($next === $decodedUrl) {
                break;
            }
            $decodedUrl = $next;
        }

        return '';
    }

    private function accountQuery(int $merchantId): Builder
    {
        return Db::table(BusinessTable::account('account'))
            ->leftJoin('admin_channel', 'account.code', '=', 'admin_channel.code')
            ->leftJoin(BusinessTable::user('merchant'), 'account.user_id', '=', 'merchant.id')
            ->select(
                'account.id',
                'account.code',
                'account.type',
                'account.user_id',
                'account.qr_url',
                'account.qr_type',
                'account.wxname',
                'account.zfb_pid',
                'account.wx_guid',
                'account.cloud_id',
                'account.qq',
                'account.status',
                'account.is_status',
                'account.create_time',
                'account.update_time',
                'account.memo',
                'account.cookie',
                'account.allmaxcount',
                'account.allmaxmoney',
                'account.daymaxcount',
                'account.daymaxmoney',
                'account.remark',
                'account.money',
                'admin_channel.name as channel_name',
                'merchant.username as merchant_username',
                'merchant.name as merchant_name'
            )
            ->where('account.user_id', $merchantId);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword) {
                $builder
                    ->where('account.code', 'like', '%' . $keyword . '%')
                    ->orWhere('account.type', 'like', '%' . $keyword . '%')
                    ->orWhere('account.zfb_pid', 'like', '%' . $keyword . '%')
                    ->orWhere('account.wxname', 'like', '%' . $keyword . '%')
                    ->orWhere('account.qq', 'like', '%' . $keyword . '%')
                    ->orWhere('account.memo', 'like', '%' . $keyword . '%')
                    ->orWhere('admin_channel.name', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder->orWhere('account.id', (int)$keyword);
                }
            });
        }

        $type = trim((string)$request->get('type', ''));
        if ($type !== '') {
            $query->where('account.type', $type);
        }

        $status = trim((string)$request->get('status', ''));
        if ($status !== '' && in_array($status, ['0', '1'], true)) {
            $query->where('account.status', (int)$status);
        }

        $enabled = trim((string)$request->get('is_status', ''));
        if ($enabled !== '' && in_array($enabled, ['0', '1', '2'], true)) {
            $query->where('account.is_status', (int)$enabled);
        }

        $startDate = $this->normalizeDate((string)$request->get('start_date', ''));
        $endDate = $this->normalizeDate((string)$request->get('end_date', ''));
        if ($startDate !== null && $endDate !== null) {
            $query
                ->where('account.create_time', '>=', $startDate . ' 00:00:00')
                ->where('account.create_time', '<', date('Y-m-d 00:00:00', strtotime($endDate . ' +1 day')));
        }
    }

    private function buildSummary(array $merchant, Builder $query): array
    {
        $accountIds = (clone $query)->pluck('account.id')->toArray();
        $amountStats = $this->summaryOrderStats(array_map('intval', $accountIds));
        $vipLabel = AdminFixtureTextNormalizer::normalize(trim((string)($merchant['vip_name'] ?? '')));

        return [
            'merchant_id' => (int)($merchant['id'] ?? 0),
            'merchant_username' => trim((string)($merchant['username'] ?? '')),
            'vip_label' => $vipLabel !== '' ? $vipLabel : '普通商户',
            'total_count' => (int)(clone $query)->count('account.id'),
            'online_count' => (int)(clone $query)->where('account.status', 1)->count('account.id'),
            'offline_count' => (int)(clone $query)->where('account.status', '<>', 1)->count('account.id'),
            'enabled_count' => (int)(clone $query)->where('account.is_status', 1)->count('account.id'),
            'disabled_count' => (int)(clone $query)->where('account.is_status', '<>', 1)->count('account.id'),
            'identifier_ready_count' => (int)(clone $query)
                ->where(function (Builder $builder) {
                    $builder
                        ->whereNotNull('account.zfb_pid')
                        ->where('account.zfb_pid', '<>', '')
                        ->orWhere(function (Builder $nested) {
                            $nested
                                ->whereNotNull('account.wxname')
                                ->where('account.wxname', '<>', '');
                        })
                        ->orWhere(function (Builder $nested) {
                            $nested
                                ->whereNotNull('account.qq')
                                ->where('account.qq', '<>', '');
                        });
                })
                ->count('account.id'),
            'credential_ready_count' => (int)(clone $query)
                ->where(function (Builder $builder) {
                    $builder
                        ->whereNotNull('account.cookie')
                        ->where('account.cookie', '<>', '')
                        ->orWhere(function (Builder $nested) {
                            $nested
                                ->whereNotNull('account.qr_url')
                                ->where('account.qr_url', '<>', '');
                        })
                        ->orWhere(function (Builder $nested) {
                            $nested
                                ->whereNotNull('account.remark')
                                ->where('account.remark', '<>', '');
                        })
                        ->orWhere(function (Builder $nested) {
                            $nested
                                ->whereNotNull('account.wx_guid')
                                ->where('account.wx_guid', '<>', '');
                        });
                })
                ->count('account.id'),
            'paid_order_count' => $amountStats['paid_order_count'],
            'paid_amount' => $amountStats['paid_amount'],
        ];
    }

    private function buildWriteActions(array $merchant): array
    {
        $canWrite = (int)($merchant['is_frozen'] ?? 0) !== 1;

        return [
            'create' => $canWrite,
            'edit' => $canWrite,
            'status' => $canWrite,
            'testPay' => $canWrite && SystemConfig::int('is_channelPay', 0) === 1,
            'remove' => $canWrite,
            'batchRemove' => $canWrite,
        ];
    }

    private function buildCatalog(int $merchantId): array
    {
        $paymentMethodMap = $this->paymentMethodMap();
        $pluginCatalog = $this->discoverMerchantPluginCatalog($paymentMethodMap);

        $availableMethodTypes = [];
        foreach ($pluginCatalog as $plugin) {
            foreach ((array)($plugin['method_types'] ?? []) as $type) {
                $normalizedType = trim((string)$type);
                if ($normalizedType !== '') {
                    $availableMethodTypes[$normalizedType] = true;
                }
            }
        }

        $knownTypes = array_merge(
            array_keys($availableMethodTypes),
            Db::table(BusinessTable::account())
                ->where('user_id', $merchantId)
                ->select('type')
                ->distinct()
                ->pluck('type')
                ->map(static fn($value): string => trim((string)$value))
                ->filter(static fn(string $value): bool => $value !== '')
                ->toArray()
        );
        $knownTypes = array_values(array_unique($knownTypes));

        return [
            'gateway_options' => array_map(static function (string $value) use ($paymentMethodMap): array {
                $method = $paymentMethodMap[$value] ?? null;

                return [
                    'label' => (string)($method['label'] ?? AdminOrderFormatter::paymentTypeLabel($value)),
                    'value' => $value,
                ];
            }, $knownTypes),
            'payment_methods' => array_values(array_filter($paymentMethodMap, static function (array $item) use ($availableMethodTypes): bool {
                return isset($availableMethodTypes[(string)($item['value'] ?? '')]);
            })),
            'plugin_options' => $pluginCatalog,
            'test_pay' => [
                'enabled' => SystemConfig::int('is_channelPay', 0) === 1,
                'amount' => number_format($this->resolveTestPayBaseAmount(), 2, '.', ''),
                'name' => $this->resolveTestPayName(),
            ],
        ];
    }

    private function paymentMethodMap(): array
    {
        $rows = Db::table(BusinessTable::payment())
            ->select('id', 'name', 'type', 'status', 'sort')
            ->where('status', 1)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->toArray();

        $items = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $type = trim((string)($record['type'] ?? ''));
            if ($type === '') {
                continue;
            }

            $name = AdminFixtureTextNormalizer::normalize(trim((string)($record['name'] ?? '')));
            $items[$type] = [
                'id' => (int)($record['id'] ?? 0),
                'value' => $type,
                'label' => $name !== '' ? $name : AdminOrderFormatter::paymentTypeLabel($type),
                'type_label' => AdminOrderFormatter::paymentTypeLabel($type),
                'sort' => (int)($record['sort'] ?? 0),
            ];
        }

        return $items;
    }

    private function loadOrderStats(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        $rows = Db::table(BusinessTable::order())
            ->select('account_id')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as paid_order_count')
            ->selectRaw('SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as pending_order_count')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN truemoney ELSE 0 END) as paid_amount')
            ->selectRaw('MAX(create_time) as latest_order_time')
            ->where('pay_type', 1)
            ->whereIn('account_id', $accountIds)
            ->groupBy('account_id')
            ->get()
            ->toArray();

        $stats = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $stats[(int)($record['account_id'] ?? 0)] = $record;
        }

        return $stats;
    }

    private function summaryOrderStats(array $accountIds): array
    {
        if ($accountIds === []) {
            return [
                'paid_order_count' => 0,
                'paid_amount' => 0.0,
            ];
        }

        $row = Db::table(BusinessTable::order())
            ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as paid_order_count')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN truemoney ELSE 0 END) as paid_amount')
            ->where('pay_type', 1)
            ->whereIn('account_id', $accountIds)
            ->first();

        $record = $row ? (array)$row : [];

        return [
            'paid_order_count' => (int)($record['paid_order_count'] ?? 0),
            'paid_amount' => AdminPaymentAccountFormatter::toFloat($record['paid_amount'] ?? 0),
        ];
    }

    private function accountIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function accountRecord(int $merchantId, int $id): ?array
    {
        $row = $this->accountQuery($merchantId)
            ->where('account.id', $id)
            ->first();

        return $row ? (array)$row : null;
    }

    private function findAccount(int $merchantId, int $id): ?array
    {
        $record = $this->accountRecord($merchantId, $id);
        if ($record === null) {
            return null;
        }

        $stats = $this->loadOrderStats([$id]);

        return AdminPaymentAccountFormatter::format($record, $stats[$id] ?? []);
    }

    private function findAccountDetail(int $merchantId, int $id): ?array
    {
        $record = $this->accountRecord($merchantId, $id);
        if ($record === null) {
            return null;
        }

        return [
            'item' => $this->findAccount($merchantId, $id),
            'editable' => array_merge(
                [
                    'memo' => trim((string)($record['memo'] ?? '')),
                    'daymaxcount' => (string)(int)($record['daymaxcount'] ?? 0),
                    'daymaxmoney' => trim((string)($record['daymaxmoney'] ?? '')),
                    'allmaxcount' => (string)(int)($record['allmaxcount'] ?? 0),
                    'allmaxmoney' => trim((string)($record['allmaxmoney'] ?? '')),
                    'status' => (int)($record['status'] ?? 0),
                    'is_status' => (int)($record['is_status'] ?? 0),
                ],
                $this->credentialEditablePayload($record)
            ),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{code: string, payment_method_type: string, payment_method_label: string, plugin_name: string}
     */
    private function resolveMerchantCreateContext(array $payload): array
    {
        $pluginCode = strtolower(trim((string)($payload['plugin_code'] ?? ($payload['code'] ?? ''))));
        if ($pluginCode === '') {
            throw new \InvalidArgumentException('请选择支付插件');
        }

        if (!isset($this->createCodeCatalog()[$pluginCode])) {
            throw new \InvalidArgumentException('所选插件暂不支持商户端维护');
        }

        $paymentMethodType = strtolower(trim((string)($payload['payment_method_type'] ?? '')));
        $paymentMethodMap = $this->paymentMethodMap();
        $catalog = $this->discoverMerchantPluginCatalog($paymentMethodMap);

        foreach ($catalog as $plugin) {
            if ((string)($plugin['code'] ?? '') !== $pluginCode) {
                continue;
            }

            $methodTypes = array_values(array_filter(array_map(
                static fn($value): string => strtolower(trim((string)$value)),
                (array)($plugin['method_types'] ?? [])
            )));

            if ($paymentMethodType === '') {
                $paymentMethodType = $methodTypes[0] ?? '';
            }

            if ($paymentMethodType === '') {
                throw new \InvalidArgumentException('所选插件未声明可用支付方式');
            }

            if (!in_array($paymentMethodType, $methodTypes, true)) {
                throw new \InvalidArgumentException('所选插件不支持当前支付方式');
            }

            $paymentMethod = $paymentMethodMap[$paymentMethodType] ?? $this->resolvePaymentMethod($paymentMethodType);

            return [
                'code' => $pluginCode,
                'payment_method_type' => $paymentMethodType,
                'payment_method_label' => trim((string)($paymentMethod['label'] ?? '')),
                'plugin_name' => trim((string)($plugin['name'] ?? $pluginCode)),
            ];
        }

        throw new \InvalidArgumentException('所选支付插件不可用或尚未启用');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array{code: string, payment_method_type: string, payment_method_label: string, plugin_name: string} $context
     * @return array<string, mixed>
     */
    private function normalizeCreatePayload(array $payload, int $merchantId, array $context): array
    {
        $code = $context['code'];
        $channel = $this->findCreatableChannel($code);
        if ($channel === null) {
            throw new \InvalidArgumentException('插件已启用，但对应通道目录未就绪');
        }

        $identifier = $this->normalizeRequiredText(
            $payload['identifier'] ?? '',
            50,
            (string)($this->createCodeCatalog()[$code]['identifier_label'] ?? '账号标识')
        );
        $pid = $this->normalizeOptionalText($payload['pid'] ?? '', 50, '商户标识');
        $qrUrl = $this->normalizeOptionalText($payload['qr_url'] ?? '', 12000, '二维码内容');
        $cookie = $this->normalizeOptionalText($payload['cookie'] ?? '', 12000, '凭证内容');
        $remark = strip_tags($this->normalizeOptionalText($payload['remark'] ?? '', 225, '备注'));
        $wxGuid = $code === 'alipay_official'
            ? $this->normalizeOptionalText($payload['wx_guid'] ?? '', 12000, '应用公钥证书')
            : $this->normalizeOptionalText($payload['wx_guid'] ?? '', 120, '证书序列号');
        $cloudId = in_array($code, ['jiaofeiyi_alipay', 'jiaofeiyi_wxpay'], true)
            ? $this->normalizeOptionalHttpUrl($payload['cloud_id'] ?? '', 2500, '代理IP API 地址')
            : ($code === 'alipay_official'
                ? $this->normalizeOptionalText($payload['cloud_id'] ?? '', 12000, '支付宝公钥证书')
                : $this->normalizeOptionalText($payload['cloud_id'] ?? '', 50, '云端标识'));
        $extraValue = $this->normalizeOptionalText(
            $payload['extra_value'] ?? '',
            $code === 'alipay_official' ? 20000 : 12000,
            '扩展内容'
        );

        $identifierFields = $this->mapIdentifierFields($code, $identifier);
        $seedRecord = array_merge([
            'code' => $code,
            'qr_type' => trim((string)($payload['qr_type'] ?? '')),
            'qr_url' => $qrUrl,
            'cookie' => $cookie,
            'remark' => $remark,
            'wx_guid' => $wxGuid,
            'cloud_id' => $cloudId,
        ], $identifierFields);

        $credentialUpdates = $this->normalizeCredentialPayload([
            'identifier' => $identifier,
            'pid' => $pid,
            'qr_type' => $payload['qr_type'] ?? '',
            'qr_url' => $qrUrl,
            'cookie' => $cookie,
            'remark' => $remark,
            'wx_guid' => $wxGuid,
            'cloud_id' => $cloudId,
            'extra_value' => $extraValue,
        ], $seedRecord);

        return array_merge(
            [
                'code' => $code,
                'type' => $context['payment_method_type'],
                'user_id' => $merchantId,
                'qr_url' => $qrUrl,
                'qr_type' => '',
                'status' => $this->normalizeOnlineStatus($payload['status'] ?? 0),
                'is_status' => $this->normalizeEnabledStatus($payload['is_status'] ?? 1),
                'memo' => $this->normalizeOptionalText($payload['memo'] ?? '', 50, '账号备注'),
                'cookie' => $cookie,
                'allmaxcount' => $this->normalizeNonNegativeInteger(
                    $payload['allmaxcount'] ?? 0,
                    '总笔数限制'
                ),
                'allmaxmoney' => $this->normalizeOptionalDecimal(
                    $payload['allmaxmoney'] ?? '',
                    50,
                    '总金额限制'
                ),
                'daymaxcount' => $this->normalizeNonNegativeInteger(
                    $payload['daymaxcount'] ?? 0,
                    '单日笔数限制'
                ),
                'daymaxmoney' => $this->normalizeOptionalDecimal(
                    $payload['daymaxmoney'] ?? '',
                    50,
                    '单日金额限制'
                ),
                'remark' => $remark,
                'wx_guid' => $wxGuid,
                'cloud_id' => $cloudId,
                'payment_method_type' => $context['payment_method_type'],
                'payment_method_label' => $context['payment_method_label'],
                'plugin_name' => $context['plugin_name'],
                'payment_method_type' => $context['payment_method_type'],
                'payment_method_label' => $context['payment_method_label'],
                'plugin_name' => $context['plugin_name'],
            ],
            $identifierFields,
            $credentialUpdates
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $record
     * @return array<string, string>
     */
    private function normalizeCredentialPayload(array $payload, array $record): array
    {
        $code = $this->normalizeCredentialCode((string)($record['code'] ?? ''));
        $identifierField = $this->identifierFieldForCode($code);
        $identifierLabel = (string)($this->credentialCodeCatalog()[$code]['identifier_label'] ?? '账号标识');

        $updates = [
            $identifierField => $this->normalizeRequiredText(
                $payload['identifier'] ?? $this->identifierValueForCode($record, $code),
                50,
                $identifierLabel
            ),
        ];

        if ($code === 'alipay_software') {
            $qrUrl = $this->normalizeOptionalText(
                $payload['qr_url'] ?? ($record['qr_url'] ?? ''),
                2500,
                '二维码图片地址'
            );
            $updates['qr_type'] = $this->normalizeCreateQrType(
                $payload['qr_type'] ?? ($record['qr_type'] ?? ''),
                $code,
                $qrUrl
            );
            $updates['qr_url'] = $qrUrl;

            return $updates;
        }

        if (in_array($code, ['jiaofeiyi_alipay', 'jiaofeiyi_wxpay'], true)) {
            $jiaofeiyiConfig = $this->decodeJiaofeiyiConfig($record);
            $proxyApiUrl = $this->normalizeOptionalHttpUrl(
                $payload['cloud_id'] ?? ($jiaofeiyiConfig['proxy_api_url'] ?? ''),
                2500,
                '代理IP API 地址'
            );
            $updates = array_merge($updates, [
                'zfb_pid' => $this->normalizeRequiredText(
                    $payload['pid'] ?? ($record['zfb_pid'] ?? ''),
                    50,
                    '商户 ID'
                ),
                'cookie' => $this->encodeJiaofeiyiConfig(
                    $this->normalizeOptionalText(
                        $payload['cookie'] ?? ($jiaofeiyiConfig['store_name'] ?? ''),
                        12000,
                        '店铺名称'
                    ),
                    $this->normalizeOptionalHttpUrl(
                        $payload['extra_value'] ?? ($jiaofeiyiConfig['remote_api_url'] ?? ''),
                        2500,
                        '远程 API 地址'
                    ),
                    $proxyApiUrl
                ),
                'qr_url' => $this->normalizeOptionalText(
                    $payload['qr_url'] ?? ($record['qr_url'] ?? ''),
                    12000,
                    '收款备注'
                ),
                'remark' => $this->normalizeOptionalText(
                    $payload['remark'] ?? ($record['remark'] ?? ''),
                    225,
                    '指定 IP'
                ),
                'cloud_id' => '',
            ]);

            if ($code === 'jiaofeiyi_wxpay') {
                $updates['qr_type'] = $this->normalizeJiaofeiyiPayMode(
                    $payload['qr_type'] ?? ($record['qr_type'] ?? '2')
                );
            }

            return $updates;
        }

        if ($code === 'universal_epay') {
            $gatewayUrl = $this->normalizeOptionalHttpUrl(
                $payload['qr_url'] ?? ($record['qr_url'] ?? ''),
                2500,
                '接口地址'
            );
            if ($gatewayUrl === '') {
                throw new \InvalidArgumentException('接口地址不能为空');
            }

            return array_merge($updates, [
                'zfb_pid' => '',
                'cookie' => $this->normalizeRequiredText(
                    $payload['cookie'] ?? ($record['cookie'] ?? ''),
                    12000,
                    '商户密钥'
                ),
                'qr_url' => $gatewayUrl,
                'qr_type' => $this->normalizeUniversalEpayMode(
                    $payload['qr_type'] ?? ($record['qr_type'] ?? '0')
                ),
                'wx_guid' => '',
                'cloud_id' => '',
                'qq' => '',
            ]);
        }

        if ($code === 'alipay_bill') {
            $billPayload = $this->decodeBillCredentialPayload($record);
            $privateKey = $this->normalizeRequiredText(
                $payload['qr_url'] ?? ($billPayload['private_key'] ?? ''),
                12000,
                '私钥'
            );
            $qrcode = $this->normalizeRequiredText(
                $payload['extra_value'] ?? ($billPayload['qrcode'] ?? ''),
                12000,
                '账单二维码内容'
            );

            return array_merge($updates, [
                'zfb_pid' => '',
                'cookie' => $this->normalizeOptionalText(
                    $payload['cookie'] ?? ($record['cookie'] ?? ''),
                    12000,
                    '公钥'
                ),
                'qr_type' => 'qrcode',
                'remark' => 'qrcode',
                'qr_url' => json_encode([
                    'private_key' => $privateKey,
                    'qrcode' => $qrcode,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        if ($code === 'alipay_mck') {
            return array_merge($updates, [
                'zfb_pid' => $this->normalizeRequiredText(
                    $payload['pid'] ?? ($record['zfb_pid'] ?? ''),
                    50,
                    'PID'
                ),
                'cookie' => $this->normalizeRequiredText(
                    $payload['cookie'] ?? ($record['cookie'] ?? ''),
                    12000,
                    '公钥'
                ),
                'qr_url' => $this->normalizeRequiredText(
                    $payload['qr_url'] ?? ($record['qr_url'] ?? ''),
                    12000,
                    '私钥'
                ),
            ]);
        }

        if ($code === 'alipay_official') {
            $managedConfig = $this->loadManagedCredentialConfig($code, (int)($record['id'] ?? 0));
            $signMode = $this->normalizeAlipayOfficialSignMode(
                $payload['remark'] ?? ($record['remark'] ?? '')
            );
            $alipayCertContent = $signMode === 'cert'
                ? $this->normalizeRequiredText(
                    $payload['cloud_id'] ?? ($managedConfig['alipay_cert'] ?? ''),
                    12000,
                    '支付宝公钥证书'
                )
                : $this->normalizeOptionalText(
                    $payload['cloud_id'] ?? ($managedConfig['alipay_cert'] ?? ''),
                    12000,
                    '支付宝公钥证书'
                );

            return array_merge($updates, [
                'zfb_pid' => $this->normalizeOptionalText(
                    $payload['pid'] ?? ($record['zfb_pid'] ?? ''),
                    50,
                    '卖家支付宝用户 ID'
                ),
                'cookie' => $signMode === 'cert'
                    ? $this->resolveAlipayOfficialPublicKey(
                        $payload['cookie'] ?? ($record['cookie'] ?? ''),
                        $alipayCertContent
                    )
                    : $this->normalizeRequiredText(
                        $payload['cookie'] ?? ($record['cookie'] ?? ''),
                        12000,
                        '支付宝公钥'
                    ),
                'qr_url' => $this->normalizeRequiredText(
                    $payload['qr_url'] ?? ($record['qr_url'] ?? ''),
                    12000,
                    '应用私钥'
                ),
                'wx_guid' => '',
                'cloud_id' => '',
                'qq' => '',
                'remark' => $signMode,
                'qr_type' => $this->normalizeModeCsv(
                    $payload['qr_type'] ?? ($record['qr_type'] ?? ''),
                    ['1', '2', '3', '4', '5', '6', '7', '8'],
                    ['1', '2', '3', '4', '5', '6', '7', '8'],
                    '支付模式'
                ),
            ]);
        }

        if ($code === 'wxpay_v3') {
            return array_merge($updates, [
                'zfb_pid' => $this->normalizeRequiredText(
                    $payload['pid'] ?? ($record['zfb_pid'] ?? ''),
                    50,
                    '商户号'
                ),
                'cookie' => $this->normalizeRequiredText(
                    $payload['cookie'] ?? ($record['cookie'] ?? ''),
                    12000,
                    '平台公钥'
                ),
                'qr_url' => $this->normalizeRequiredText(
                    $payload['qr_url'] ?? ($record['qr_url'] ?? ''),
                    12000,
                    '商户私钥'
                ),
                'remark' => $this->normalizeRequiredText(
                    $payload['remark'] ?? ($record['remark'] ?? ''),
                    225,
                    'API V3 密钥'
                ),
                'wx_guid' => $this->normalizeRequiredText(
                    $payload['wx_guid'] ?? ($record['wx_guid'] ?? ''),
                    120,
                    '证书序列号'
                ),
                'cloud_id' => $this->normalizeOptionalText(
                    $payload['cloud_id'] ?? ($record['cloud_id'] ?? ''),
                    50,
                    '微信支付平台公钥 ID'
                ),
                'qq' => $this->normalizeOptionalText(
                    $payload['extra_value'] ?? ($record['qq'] ?? ''),
                    50,
                    '商户 APIv2 密钥'
                ),
                'qr_type' => $this->normalizeModeCsv(
                    $payload['qr_type'] ?? ($record['qr_type'] ?? ''),
                    ['1', '2', '3', '5'],
                    ['1'],
                    '微信 V3 支付模式'
                ),
            ]);
        }

        if (in_array($code, ['wxpay_software', 'qqpay_software'], true)) {
            $updates['qr_url'] = $this->normalizeOptionalText(
                $payload['qr_url'] ?? ($record['qr_url'] ?? ''),
                2500,
                '二维码内容'
            );
        }

        return $updates;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function credentialEditablePayload(array $record): array
    {
        $code = strtolower(trim((string)($record['code'] ?? '')));
        if (!$this->supportsCredentialEditing($code)) {
            return [
                'code' => $code,
                'credential_supported' => false,
                'pid' => '',
                'identifier' => '',
                'qr_type' => '',
                'qr_url' => '',
                'cookie' => '',
                'remark' => '',
                'wx_guid' => '',
                'cloud_id' => '',
                'extra_value' => '',
            ];
        }

        $billPayload = $code === 'alipay_bill' ? $this->decodeBillCredentialPayload($record) : ['private_key' => '', 'qrcode' => ''];
        $jiaofeiyiConfig = in_array($code, ['jiaofeiyi_alipay', 'jiaofeiyi_wxpay'], true)
            ? $this->decodeJiaofeiyiConfig($record)
            : ['store_name' => '', 'remote_api_url' => '', 'proxy_api_url' => ''];
        $managedConfig = $this->loadManagedCredentialConfig($code, (int)($record['id'] ?? 0));

        return [
            'code' => $code,
            'credential_supported' => true,
            'pid' => trim((string)($record['zfb_pid'] ?? '')),
            'identifier' => $this->identifierValueForCode($record, $code),
            'qr_type' => trim((string)($record['qr_type'] ?? '')),
            'qr_url' => $code === 'alipay_bill'
                ? (string)($billPayload['private_key'] ?? '')
                : trim((string)($record['qr_url'] ?? '')),
            'cookie' => in_array($code, ['jiaofeiyi_alipay', 'jiaofeiyi_wxpay'], true)
                ? (string)($jiaofeiyiConfig['store_name'] ?? '')
                : trim((string)($record['cookie'] ?? '')),
            'remark' => $code === 'alipay_official'
                ? $this->normalizeAlipayOfficialSignMode($record['remark'] ?? '')
                : trim((string)($record['remark'] ?? '')),
            'wx_guid' => $code === 'alipay_official'
                ? (string)($managedConfig['app_cert'] ?? '')
                : trim((string)($record['wx_guid'] ?? '')),
            'cloud_id' => $code === 'alipay_official'
                ? (string)($managedConfig['alipay_cert'] ?? '')
                : (in_array($code, ['jiaofeiyi_alipay', 'jiaofeiyi_wxpay'], true)
                    ? (string)($jiaofeiyiConfig['proxy_api_url'] ?? '')
                    : trim((string)($record['cloud_id'] ?? ''))),
            'extra_value' => $code === 'alipay_bill'
                ? (string)($billPayload['qrcode'] ?? '')
                : ($code === 'alipay_official'
                    ? (string)($managedConfig['root_cert'] ?? '')
                    : ($code === 'wxpay_v3'
                        ? trim((string)($record['qq'] ?? ''))
                        : (string)($jiaofeiyiConfig['remote_api_url'] ?? ''))),
        ];
    }

    private function normalizeAlipayOfficialSignMode(mixed $value): string
    {
        $normalized = strtolower(trim((string)$value));
        if (in_array($normalized, ['1', 'cert', 'certificate', 'cert_mode', 'certmode'], true)) {
            return 'cert';
        }

        return 'key';
    }

    private function resolveAlipayOfficialPublicKey(mixed $value, string $certificateContent): string
    {
        $publicKey = $this->normalizeOptionalText($value, 12000, '支付宝公钥');
        if ($publicKey !== '') {
            return $publicKey;
        }

        $extracted = $this->extractPublicKeyFromCertificateContent($certificateContent);
        if ($extracted === '') {
            throw new \InvalidArgumentException('支付宝公钥为空，且无法从支付宝公钥证书中提取公钥');
        }

        return $this->normalizeRequiredText($extracted, 12000, '支付宝公钥');
    }

    private function extractPublicKeyFromCertificateContent(string $certificateContent): string
    {
        $normalized = trim($certificateContent);
        if ($normalized === '') {
            return '';
        }

        $resource = openssl_pkey_get_public($normalized);
        if ($resource === false) {
            return '';
        }

        $details = openssl_pkey_get_details($resource);
        if (!is_array($details)) {
            return '';
        }

        $publicKey = str_replace(
            ['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----'],
            '',
            (string)($details['key'] ?? '')
        );

        return trim($publicKey);
    }

    private function managedCredentialConfigTable(string $code): ?string
    {
        return match ($code) {
            'alipay_official' => 'pay_plugin_alipay_official_config',
            default => null,
        };
    }

    private function managedCredentialConfigPrefix(int $accountId): string
    {
        return 'account:' . $accountId . ':';
    }

    /**
     * @return array<string, string>
     */
    private function loadManagedCredentialConfig(string $code, int $accountId): array
    {
        $table = $this->managedCredentialConfigTable($code);
        if ($table === null || $accountId <= 0) {
            return [];
        }

        $prefix = $this->managedCredentialConfigPrefix($accountId);
        $rows = Db::table($table)
            ->select('config_key', 'config_value')
            ->where('config_key', 'like', $prefix . '%')
            ->get()
            ->toArray();

        $result = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $configKey = trim((string)($record['config_key'] ?? ''));
            if ($configKey === '' || !str_starts_with($configKey, $prefix)) {
                continue;
            }

            $field = substr($configKey, strlen($prefix));
            if ($field === false || $field === '') {
                continue;
            }

            $result[$field] = trim((string)($record['config_value'] ?? ''));
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $record
     * @return array<string, string>
     */
    private function normalizeManagedCredentialConfig(string $code, array $payload, array $record = []): array
    {
        if ($code !== 'alipay_official') {
            return [];
        }

        $current = $this->loadManagedCredentialConfig($code, (int)($record['id'] ?? 0));
        $signMode = $this->normalizeAlipayOfficialSignMode($payload['remark'] ?? ($record['remark'] ?? ''));
        $appCert = $signMode === 'cert'
            ? $this->normalizeRequiredText(
                $payload['wx_guid'] ?? ($current['app_cert'] ?? ''),
                12000,
                '应用公钥证书'
            )
            : $this->normalizeOptionalText(
                $payload['wx_guid'] ?? ($current['app_cert'] ?? ''),
                12000,
                '应用公钥证书'
            );
        $alipayCert = $signMode === 'cert'
            ? $this->normalizeRequiredText(
                $payload['cloud_id'] ?? ($current['alipay_cert'] ?? ''),
                12000,
                '支付宝公钥证书'
            )
            : $this->normalizeOptionalText(
                $payload['cloud_id'] ?? ($current['alipay_cert'] ?? ''),
                12000,
                '支付宝公钥证书'
            );
        $rootCert = $signMode === 'cert'
            ? $this->normalizeRequiredText(
                $payload['extra_value'] ?? ($current['root_cert'] ?? ''),
                20000,
                '支付宝根证书'
            )
            : $this->normalizeOptionalText(
                $payload['extra_value'] ?? ($current['root_cert'] ?? ''),
                20000,
                '支付宝根证书'
            );

        return [
            'app_cert' => $appCert,
            'alipay_cert' => $alipayCert,
            'root_cert' => $rootCert,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $record
     */
    private function saveManagedCredentialConfig(string $code, int $accountId, array $payload, array $record = []): void
    {
        $table = $this->managedCredentialConfigTable($code);
        if ($table === null || $accountId <= 0) {
            return;
        }

        $normalized = $this->normalizeManagedCredentialConfig($code, $payload, $record);
        $prefix = $this->managedCredentialConfigPrefix($accountId);

        foreach ($normalized as $field => $value) {
            $configKey = $prefix . $field;
            $exists = Db::table($table)
                ->where('config_key', $configKey)
                ->exists();

            if ($exists) {
                Db::table($table)
                    ->where('config_key', $configKey)
                    ->update([
                        'config_value' => $value === '' ? null : $value,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                continue;
            }

            Db::table($table)->insert([
                'plugin_code' => $code,
                'config_key' => $configKey,
                'config_value' => $value === '' ? null : $value,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function deleteManagedCredentialConfigRows(array $rows): void
    {
        foreach ($rows as $row) {
            $record = (array)$row;
            $code = strtolower(trim((string)($record['code'] ?? '')));
            $accountId = (int)($record['id'] ?? 0);
            $table = $this->managedCredentialConfigTable($code);
            if ($table === null || $accountId <= 0) {
                continue;
            }

            Db::table($table)
                ->where('config_key', 'like', $this->managedCredentialConfigPrefix($accountId) . '%')
                ->delete();
        }
    }

    private function buildDeleteAudit(array $record, ?array $item = null): array
    {
        $accountId = (int)($record['id'] ?? 0);
        $item = $item ?? $this->findAccount((int)($record['user_id'] ?? 0), $accountId) ?? [];
        $dependency = $this->loadDeleteDependencySummary((int)($record['user_id'] ?? 0), $accountId);

        $orderCount = (int)($dependency['order_count'] ?? 0);
        $paidOrderCount = (int)($dependency['paid_order_count'] ?? 0);
        $paidAmount = AdminPaymentAccountFormatter::toFloat($dependency['paid_amount'] ?? 0);
        $activePendingOrderCount = (int)($dependency['active_pending_order_count'] ?? 0);
        $detachableOrderCount = (int)($dependency['detachable_order_count'] ?? 0);
        $poolItemCount = (int)($dependency['pool_item_count'] ?? 0);
        $poolCount = (int)($dependency['pool_count'] ?? 0);
        $lastSelectedPoolCount = (int)($dependency['last_selected_pool_count'] ?? 0);
        $activeLastSelectedPoolCount = (int)($dependency['active_last_selected_pool_count'] ?? 0);

        $blockingReasons = [];
        if ($paidOrderCount > 0) {
            $blockingReasons[] = sprintf('当前仍有 %d 笔已支付订单引用该通道，删除后会影响历史对账，暂不允许删除。', $paidOrderCount);
        }
        if ($activePendingOrderCount > 0) {
            $blockingReasons[] = sprintf('当前仍有 %d 笔待支付订单仍在有效期内，需先等待失效或完成支付后再删除。', $activePendingOrderCount);
        }
        if ($poolItemCount > 0) {
            $blockingReasons[] = sprintf('当前仍有 %d 个轮询池条目引用该通道，涉及 %d 个轮询池，请先移出轮询池后再删除。', $poolItemCount, $poolCount);
        }
        /*
        if (false && $orderCount > 0) {
            $blockingReasons[] = sprintf('仍有 %d 笔历史订单引用该账号，其中 %d 笔已支付。', $orderCount, $paidOrderCount);
        }
        if (false && $poolItemCount > 0) {
            $blockingReasons[] = sprintf('仍有 %d 个轮询池条目引用该账号，涉及 %d 个轮询池。', $poolItemCount, $poolCount);
        }
        */

        $warnings = [
            '删除后会永久移除当前账号保存的凭证、二维码与限额配置。',
            '商户主体、支付方式、通道目录和历史订单不会随账号一起删除。',
        ];
        if ($detachableOrderCount > 0) {
            $warnings[] = sprintf('删除时会自动解除 %d 笔失效或不可继续支付订单与当前通道的关联，订单记录仍会保留。', $detachableOrderCount);
        }
        if ($lastSelectedPoolCount > 0) {
            $warnings[] = sprintf('删除后会重置 %d 个轮询池保存的最近命中账号指针。', $lastSelectedPoolCount);
        }

        return [
            'account_id' => $accountId,
            'account_label' => $this->accountLabel($record, (array)$item),
            'merchant_display' => (string)($item['merchant_display'] ?? ''),
            'channel_label' => $this->channelLabel($record, (array)$item),
            'type' => trim((string)($record['type'] ?? '')),
            'type_label' => (string)($item['type_label'] ?? ''),
            'can_delete' => $blockingReasons === [],
            'confirmation_phrase' => $this->deleteConfirmationPhrase($accountId),
            'blocking_reasons' => $blockingReasons,
            'warnings' => $warnings,
            'summary' => [
                'delete_row_count' => $blockingReasons === [] ? (1 + $detachableOrderCount + $lastSelectedPoolCount) : 0,
                'order_count' => $orderCount,
                'paid_order_count' => $paidOrderCount,
                'active_pending_order_count' => $activePendingOrderCount,
                'detachable_order_count' => $detachableOrderCount,
                'paid_amount' => $paidAmount,
                'pool_count' => $poolCount,
                'pool_item_count' => $poolItemCount,
                'last_selected_pool_count' => $lastSelectedPoolCount,
                'active_last_selected_pool_count' => $activeLastSelectedPoolCount,
            ],
        ];
    }

    private function batchDeleteAuditPayload(int $merchantId, array $accountIds): array
    {
        $rows = $this->loadAccountRowsByIds($merchantId, $accountIds);
        $rowMap = [];
        foreach ($rows as $row) {
            $rowMap[(int)($row['id'] ?? 0)] = $row;
        }

        $items = [];
        $deletableAccountIds = [];
        $blockedAccountIds = [];
        $missingAccountIds = [];
        $deleteRowCount = 0;

        foreach ($accountIds as $accountId) {
            $row = $rowMap[$accountId] ?? null;
            if ($row === null) {
                $missingAccountIds[] = $accountId;
                $items[] = [
                    'account_id' => $accountId,
                    'account_label' => '商户收款账号 #' . $accountId,
                    'merchant_display' => '',
                    'channel_label' => '',
                    'exists' => false,
                    'can_delete' => false,
                    'blocking_reasons' => ['该收款账号不存在，或不属于当前商户。'],
                    'warnings' => ['请先从批量选择中移除不存在的账号后再试。'],
                    'summary' => [
                        'delete_row_count' => 0,
                        'order_count' => 0,
                        'paid_order_count' => 0,
                        'paid_amount' => 0.0,
                        'pool_count' => 0,
                        'pool_item_count' => 0,
                        'last_selected_pool_count' => 0,
                    ],
                ];
                continue;
            }

            $item = $this->findAccount($merchantId, $accountId);
            $audit = $this->buildDeleteAudit($row, $item);
            $summary = (array)($audit['summary'] ?? []);

            $items[] = [
                'account_id' => $accountId,
                'account_label' => (string)($audit['account_label'] ?? ('商户收款账号 #' . $accountId)),
                'merchant_display' => (string)($audit['merchant_display'] ?? ''),
                'channel_label' => (string)($audit['channel_label'] ?? ''),
                'exists' => true,
                'can_delete' => !empty($audit['can_delete']),
                'blocking_reasons' => array_values(array_map('strval', (array)($audit['blocking_reasons'] ?? []))),
                'warnings' => array_values(array_map('strval', (array)($audit['warnings'] ?? []))),
                'summary' => [
                    'delete_row_count' => (int)($summary['delete_row_count'] ?? 0),
                    'order_count' => (int)($summary['order_count'] ?? 0),
                    'paid_order_count' => (int)($summary['paid_order_count'] ?? 0),
                    'active_pending_order_count' => (int)($summary['active_pending_order_count'] ?? 0),
                    'detachable_order_count' => (int)($summary['detachable_order_count'] ?? 0),
                    'paid_amount' => AdminPaymentAccountFormatter::toFloat($summary['paid_amount'] ?? 0),
                    'pool_count' => (int)($summary['pool_count'] ?? 0),
                    'pool_item_count' => (int)($summary['pool_item_count'] ?? 0),
                    'last_selected_pool_count' => (int)($summary['last_selected_pool_count'] ?? 0),
                ],
            ];

            $deleteRowCount += (int)($summary['delete_row_count'] ?? 0);
            if (!empty($audit['can_delete'])) {
                $deletableAccountIds[] = $accountId;
                continue;
            }

            $blockedAccountIds[] = $accountId;
        }

        $existingAccountIds = array_values(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $rows
        ));
        $aggregate = $this->loadBatchDeleteDependencySummary($merchantId, $existingAccountIds);

        $warnings = [];
        if ($missingAccountIds !== []) {
            $warnings[] = '所选账号中包含不存在或不属于当前商户的记录。';
        }
        if ($blockedAccountIds !== []) {
            $warnings[] = '所选账号中仍存在订单或轮询池依赖，暂时不能批量删除。';
        }
        if ((int)($aggregate['detachable_order_count'] ?? 0) > 0) {
            $warnings[] = sprintf('批量删除时会自动解除 %d 笔失效或不可继续支付订单与原通道的关联，订单记录仍会保留。', (int)($aggregate['detachable_order_count'] ?? 0));
        }
        if ((int)($aggregate['last_selected_pool_count'] ?? 0) > 0) {
            $warnings[] = sprintf('删除后会重置 %d 个轮询池保存的最近命中账号指针。', (int)($aggregate['last_selected_pool_count'] ?? 0));
        }
        if ($deletableAccountIds !== []) {
            $warnings[] = '批量删除会永久移除所选收款账号记录及其已保存的凭证配置。';
        }

        return [
            'requested_account_ids' => $accountIds,
            'deletable_account_ids' => $deletableAccountIds,
            'blocked_account_ids' => $blockedAccountIds,
            'missing_account_ids' => $missingAccountIds,
            'confirmation_phrase' => $this->batchDeleteConfirmationPhrase($accountIds),
            'can_delete_all' => $accountIds !== [] && $blockedAccountIds === [] && $missingAccountIds === [],
            'items' => $items,
            'summary' => [
                'requested_count' => count($accountIds),
                'existing_count' => count($existingAccountIds),
                'deletable_count' => count($deletableAccountIds),
                'blocked_count' => count($blockedAccountIds),
                'missing_count' => count($missingAccountIds),
                'delete_row_count' => $deleteRowCount,
                'order_count' => (int)($aggregate['order_count'] ?? 0),
                'paid_order_count' => (int)($aggregate['paid_order_count'] ?? 0),
                'active_pending_order_count' => (int)($aggregate['active_pending_order_count'] ?? 0),
                'detachable_order_count' => (int)($aggregate['detachable_order_count'] ?? 0),
                'paid_amount' => AdminPaymentAccountFormatter::toFloat($aggregate['paid_amount'] ?? 0),
                'pool_count' => (int)($aggregate['pool_count'] ?? 0),
                'pool_item_count' => (int)($aggregate['pool_item_count'] ?? 0),
                'last_selected_pool_count' => (int)($aggregate['last_selected_pool_count'] ?? 0),
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<int, int> $accountIds
     * @return array<int, array<string, mixed>>
     */
    private function loadAccountRowsByIds(int $merchantId, array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        return array_map(
            static fn($row): array => (array)$row,
            $this->accountQuery($merchantId)
                ->whereIn('account.id', $accountIds)
                ->get()
                ->toArray()
        );
    }

    private function loadDeleteDependencySummary(int $merchantId, int $accountId): array
    {
        return $this->loadBatchDeleteDependencySummary($merchantId, $accountId > 0 ? [$accountId] : []);
    }

    /**
     * @param array<int, int> $accountIds
     * @return array<string, mixed>
     */
    private function loadBatchDeleteDependencySummary(int $merchantId, array $accountIds): array
    {
        if ($accountIds === []) {
            return [
                'order_count' => 0,
                'paid_order_count' => 0,
                'active_pending_order_count' => 0,
                'detachable_order_count' => 0,
                'paid_amount' => 0.0,
                'pool_count' => 0,
                'pool_item_count' => 0,
                'last_selected_pool_count' => 0,
                'active_last_selected_pool_count' => 0,
            ];
        }

        $now = time();
        $activePendingCondition = $this->activePendingOrderConditionSql($now);
        $detachableCondition = $this->detachableOrderConditionSql($now);
        $orderRow = Db::table(BusinessTable::order())
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as paid_order_count')
            ->selectRaw('SUM(CASE WHEN ' . $activePendingCondition . ' THEN 1 ELSE 0 END) as active_pending_order_count')
            ->selectRaw('SUM(CASE WHEN ' . $detachableCondition . ' THEN 1 ELSE 0 END) as detachable_order_count')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN truemoney ELSE 0 END) as paid_amount')
            ->where('pay_type', 1)
            ->whereIn('account_id', $accountIds)
            ->first();

        $poolItemRow = Db::table(BusinessTable::pollPoolItem())
            ->selectRaw('COUNT(*) as pool_item_count')
            ->selectRaw('COUNT(DISTINCT pool_id) as pool_count')
            ->where('user_id', $merchantId)
            ->whereIn('account_id', $accountIds)
            ->first();

        $lastPoolRow = Db::table(BusinessTable::pollPool())
            ->selectRaw('COUNT(*) as last_selected_pool_count')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_last_selected_pool_count')
            ->where('user_id', $merchantId)
            ->whereIn('last_account_id', $accountIds)
            ->first();

        return array_merge(
            [
                'order_count' => 0,
                'paid_order_count' => 0,
                'active_pending_order_count' => 0,
                'detachable_order_count' => 0,
                'paid_amount' => 0.0,
                'pool_count' => 0,
                'pool_item_count' => 0,
                'last_selected_pool_count' => 0,
                'active_last_selected_pool_count' => 0,
            ],
            $orderRow ? (array)$orderRow : [],
            $poolItemRow ? (array)$poolItemRow : [],
            $lastPoolRow ? (array)$lastPoolRow : []
        );
    }

    private function activePendingOrderConditionSql(int $now): string
    {
        return '(status = 0 AND ('
            . 'out_time IS NULL OR out_time = 0 '
            . 'OR (out_time >= 1000000000 AND out_time > ' . $now . ') '
            . 'OR (out_time > 0 AND out_time < 1000000000 AND create_time IS NOT NULL AND TIMESTAMPADD(SECOND, out_time, create_time) > UTC_TIMESTAMP())'
            . '))';
    }

    private function detachableOrderConditionSql(int $now): string
    {
        return '(status <> 1 AND ('
            . 'status <> 0 '
            . 'OR (out_time >= 1000000000 AND out_time > 0 AND out_time <= ' . $now . ') '
            . 'OR (out_time > 0 AND out_time < 1000000000 AND create_time IS NOT NULL AND TIMESTAMPADD(SECOND, out_time, create_time) <= UTC_TIMESTAMP())'
            . '))';
    }

    /**
     * @param array<int, int> $accountIds
     */
    private function detachInactiveOrderReferences(int $merchantId, array $accountIds): int
    {
        if ($accountIds === []) {
            return 0;
        }

        $now = time();
        $detachableCondition = $this->detachableOrderConditionSql($now);

        return Db::table(BusinessTable::order())
            ->where('user_id', $merchantId)
            ->where('pay_type', 1)
            ->whereIn('account_id', $accountIds)
            ->whereRaw($detachableCondition)
            ->update(['account_id' => 0]);
    }

    private function accountLabel(array $record, array $item = []): string
    {
        $codeLabel = trim((string)($item['code_label'] ?? $record['channel_name'] ?? ''));
        $accountId = (int)($record['id'] ?? 0);

        $parts = ['#' . $accountId];
        if ($codeLabel !== '') {
            $parts[] = $codeLabel;
        }

        return implode(' / ', $parts);
    }

    private function channelLabel(array $record, array $item = []): string
    {
        $channelLabel = trim((string)($item['code_label'] ?? $record['channel_name'] ?? ''));
        if ($channelLabel !== '') {
            return $channelLabel;
        }

        $code = trim((string)($record['code'] ?? ''));
        return $code !== '' ? $code : '未知通道';
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function createCodeCatalog(): array
    {
        return [
            'alipay_software' => [
                'type' => 'alipay',
                'identifier_field' => 'zfb_pid',
                'identifier_label' => 'PID',
            ],
            'wxpay_software' => [
                'type' => 'wxpay',
                'identifier_field' => 'wxname',
                'identifier_label' => '账号标识',
            ],
            'qqpay_software' => [
                'type' => 'qqpay',
                'identifier_field' => 'qq',
                'identifier_label' => 'QQ 号',
            ],
            'usdt' => [
                'type' => 'usdt',
                'identifier_field' => 'wxname',
                'identifier_label' => '钱包地址',
            ],
            'alipay_bill' => [
                'type' => 'alipay',
                'identifier_field' => 'wxname',
                'identifier_label' => '应用 ID',
            ],
            'alipay_mck' => [
                'type' => 'alipay',
                'identifier_field' => 'wxname',
                'identifier_label' => '应用 ID',
            ],
            'alipay_official' => [
                'type' => 'alipay',
                'identifier_field' => 'wxname',
                'identifier_label' => '应用 ID',
            ],
            'wxpay_v3' => [
                'type' => 'wxpay',
                'identifier_field' => 'wxname',
                'identifier_label' => '应用 ID',
            ],
            'universal_epay' => [
                'type' => '',
                'identifier_field' => 'wxname',
                'identifier_label' => '商户ID',
            ],
            'jiaofeiyi_alipay' => [
                'type' => 'alipay',
                'identifier_field' => 'wxname',
                'identifier_label' => '商户号',
            ],
            'jiaofeiyi_wxpay' => [
                'type' => 'wxpay',
                'identifier_field' => 'wxname',
                'identifier_label' => '商户号',
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function credentialCodeCatalog(): array
    {
        return $this->createCodeCatalog();
    }

    private function findCreatableChannel(string $code): ?array
    {
        $row = Db::table('admin_channel')
            ->select('id', 'name', 'type', 'code', 'status', 'delete_time')
            ->where('code', $code)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->first();

        if (!$row) {
            return null;
        }

        $record = (array)$row;
        $expectedTypes = $this->supportedMethodTypesForCreateCode($code);
        if (
            $expectedTypes !== []
            && !in_array(strtolower(trim((string)($record['type'] ?? ''))), $expectedTypes, true)
        ) {
            return null;
        }

        return $record;
    }

    /**
     * @return string[]
     */
    private function supportedMethodTypesForCreateCode(string $code): array
    {
        if ($code === 'universal_epay') {
            return ['alipay', 'wxpay', 'qqpay'];
        }

        $type = strtolower(trim((string)($this->createCodeCatalog()[$code]['type'] ?? '')));

        return $type === '' ? [] : [$type];
    }

    private function discoverMerchantPluginCatalog(array $paymentMethodMap): array
    {
        try {
            $manager = new PaymentPluginManager();
            $plugins = $manager->all();
        } catch (Throwable) {
            return [];
        }

        $items = [];
        $supportedCodes = $this->createCodeCatalog();

        foreach ($plugins as $plugin) {
            $code = trim((string)($plugin['code'] ?? ''));
            if ($code === '' || !isset($supportedCodes[$code])) {
                continue;
            }

            try {
                $detail = $manager->detail($code);
            } catch (Throwable) {
                continue;
            }

            $state = is_array($detail['state'] ?? null) ? $detail['state'] : [];
            if (empty($state['installed']) || empty($state['enabled'])) {
                continue;
            }

            $manifest = is_array($detail['manifest'] ?? null) ? $detail['manifest'] : [];
            if (array_key_exists('merchant_enabled', $manifest) && empty($manifest['merchant_enabled'])) {
                continue;
            }

            $configFields = is_array($detail['config_schema'] ?? null) ? $detail['config_schema'] : [];
            $managedChannels = is_array($detail['managed_channels'] ?? null) ? $detail['managed_channels'] : [];
            $pluginMethods = [];

            foreach ($this->pluginSupportedMethodTypes($managedChannels, $configFields, $manifest, $code) as $methodType) {
                if (!isset($paymentMethodMap[$methodType])) {
                    continue;
                }

                $pluginMethods[$methodType] = $paymentMethodMap[$methodType];
            }

            if ($pluginMethods === []) {
                continue;
            }

            $items[] = [
                'code' => $code,
                'name' => AdminFixtureTextNormalizer::normalize(trim((string)($plugin['name'] ?? $code))),
                'description' => AdminFixtureTextNormalizer::normalize(trim((string)($plugin['description'] ?? ''))),
                'enabled' => true,
                'status' => trim((string)($plugin['status'] ?? 'enabled')),
                'channel_type' => count($pluginMethods) === 1 ? (array_key_first($pluginMethods) ?: '') : '',
                'method_types' => array_values(array_keys($pluginMethods)),
                'method_options' => array_values($pluginMethods),
                'config_fields' => array_values(array_map(static function (array $field): array {
                    return [
                        'field' => trim((string)($field['field'] ?? '')),
                        'label' => trim((string)($field['label'] ?? '')),
                        'required' => !empty($field['required']),
                        'secret' => !empty($field['secret']),
                        'placeholder' => trim((string)($field['placeholder'] ?? '')),
                    ];
                }, $configFields)),
            ];
        }

        usort($items, static function (array $left, array $right): int {
            return strcmp((string)($left['name'] ?? ''), (string)($right['name'] ?? ''));
        });

        return $items;
    }

    private function pluginSupportedMethodTypes(array $managedChannels, array $configFields, array $manifest, string $pluginCode): array
    {
        $types = [];

        foreach ((array)($manifest['supported_payment_types'] ?? []) as $item) {
            $type = strtolower(trim((string)$item));
            if ($type === '') {
                continue;
            }

            if ($type === 'wechat') {
                $type = 'wxpay';
            } elseif ($type === 'qq') {
                $type = 'qqpay';
            }

            $types[$type] = true;
        }

        if ($types === []) {
            foreach ($this->supportedMethodTypesForCreateCode($pluginCode) as $expectedType) {
                $types[$expectedType] = true;
            }
        }

        foreach ($managedChannels as $channel) {
            if (!is_array($channel)) {
                continue;
            }

            $type = strtolower(trim((string)($channel['type'] ?? '')));
            if ($type !== '') {
                $types[$type] = true;
            }
        }

        foreach ($configFields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = strtolower(trim((string)($field['field'] ?? '')));
            if (in_array($name, ['gateway_url', 'merchant_id', 'merchant_key'], true)) {
                foreach (array_keys($this->paymentMethodMap()) as $fallbackType) {
                    $types[$fallbackType] = true;
                }
            }
        }

        return array_values(array_filter(array_keys($types), static fn(string $value): bool => $value !== ''));
    }

    private function resolvePaymentMethod(string $type): array
    {
        $row = Db::table(BusinessTable::payment())
            ->select('id', 'name', 'type', 'status')
            ->where('type', $type)
            ->where('status', 1)
            ->first();

        if (!$row) {
            throw new \InvalidArgumentException('所选支付方式不可用');
        }

        $record = (array)$row;

        return [
            'id' => (int)($record['id'] ?? 0),
            'type' => trim((string)($record['type'] ?? '')),
            'label' => AdminFixtureTextNormalizer::normalize(trim((string)($record['name'] ?? ''))),
        ];
    }

    private function supportsCredentialEditing(string $code): bool
    {
        $normalized = strtolower(trim($code));

        return $normalized !== '' && isset($this->credentialCodeCatalog()[$normalized]);
    }

    private function normalizeCredentialCode(string $value): string
    {
        $normalized = strtolower(trim($value));
        if (!$this->supportsCredentialEditing($normalized)) {
            throw new \InvalidArgumentException('当前插件暂不支持凭证维护');
        }

        return $normalized;
    }

    private function identifierFieldForCode(string $code): string
    {
        $field = trim((string)($this->credentialCodeCatalog()[$code]['identifier_field'] ?? ''));
        if ($field === '') {
            throw new \InvalidArgumentException('当前插件暂不支持凭证维护');
        }

        return $field;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function identifierValueForCode(array $record, string $code): string
    {
        $field = $this->identifierFieldForCode($code);

        return trim((string)($record[$field] ?? ''));
    }

    /**
     * @param array<string, mixed> $record
     * @return array{private_key: string, qrcode: string}
     */
    private function decodeBillCredentialPayload(array $record): array
    {
        $raw = trim((string)($record['qr_url'] ?? ''));
        if ($raw === '') {
            return [
                'private_key' => '',
                'qrcode' => '',
            ];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [
                'private_key' => $raw,
                'qrcode' => '',
            ];
        }

        return [
            'private_key' => trim((string)($decoded['private_key'] ?? '')),
            'qrcode' => trim((string)($decoded['qrcode'] ?? '')),
        ];
    }

    private function encodeJiaofeiyiConfig(string $storeName, string $remoteApiUrl, string $proxyApiUrl = ''): string
    {
        return $this->jiaofeiyi()->encodeConfig($storeName, $remoteApiUrl, $proxyApiUrl);

        $storeName = trim($storeName);
        $remoteApiUrl = trim($remoteApiUrl);
        if ($storeName === '' && $remoteApiUrl === '') {
            return '';
        }

        return json_encode([
            'store_name' => $storeName,
            'remote_api_url' => $remoteApiUrl,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * @param array<string, mixed> $record
     * @return array{store_name: string, remote_api_url: string, proxy_api_url: string}
     */
    private function decodeJiaofeiyiConfig(array $record): array
    {
        return $this->jiaofeiyi()->decodeConfig($record);

        $raw = trim((string)($record['cookie'] ?? ''));
        if ($raw === '') {
            return [
                'store_name' => '',
                'remote_api_url' => '',
            ];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [
                'store_name' => $raw,
                'remote_api_url' => '',
            ];
        }

        return [
            'store_name' => trim((string)($decoded['store_name'] ?? '')),
            'remote_api_url' => trim((string)($decoded['remote_api_url'] ?? '')),
        ];
    }

    /**
     * @return array{wxname: string, zfb_pid: string, qq: string}
     */
    private function mapIdentifierFields(string $code, string $identifier): array
    {
        $fields = [
            'wxname' => '',
            'zfb_pid' => '',
            'qq' => '',
        ];
        $identifierField = (string)($this->createCodeCatalog()[$code]['identifier_field'] ?? '');
        if ($identifierField !== '' && array_key_exists($identifierField, $fields)) {
            $fields[$identifierField] = $identifier;
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{code: string, field: string, qr_type: string}
     */
    private function normalizeCredentialImageTarget(array $payload): array
    {
        $code = strtolower(trim((string)($payload['code'] ?? '')));
        $field = strtolower(trim((string)($payload['field'] ?? '')));
        $qrType = trim((string)($payload['qr_type'] ?? ''));

        if ($code === 'alipay_software') {
            $qrType = 'pic';
        } elseif ($code === 'wxpay_software') {
            $qrType = $this->normalizeWxpaySoftwareQrType($qrType);
        } else {
            throw new \InvalidArgumentException('当前仅支持支付宝软件版图片模式或微信软件版赞赏码模式上传图片');
        }

        if ($field !== 'qr_url') {
            throw new \InvalidArgumentException('当前字段不支持图片上传');
        }

        if ($code === 'alipay_software' && $qrType !== 'pic') {
            throw new \InvalidArgumentException('支付宝软件版仅图片模式下可以上传收款码图片');
        }

        if ($code === 'wxpay_software' && $qrType !== 'appreciate') {
            throw new \InvalidArgumentException('微信软件版仅赞赏码模式下可以上传收款码图片');
        }

        return [
            'code' => $code,
            'field' => $field,
            'qr_type' => $qrType,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{code: string, field: string}
     */
    private function normalizeCredentialDecodeTarget(array $payload): array
    {
        $code = strtolower(trim((string)($payload['code'] ?? '')));
        $field = strtolower(trim((string)($payload['field'] ?? '')));

        if ($code === '') {
            throw new \InvalidArgumentException('请选择需要解析二维码的支付插件');
        }

        if (!in_array($field, ['qr_url', 'extra_value'], true)) {
            throw new \InvalidArgumentException('当前字段不支持二维码解析');
        }

        return [
            'code' => $code,
            'field' => $field,
        ];
    }

    private function normalizeCredentialImageFile(Request $request): UploadFile
    {
        $file = $request->file('file');
        if (!$file instanceof UploadFile) {
            throw new \InvalidArgumentException('请上传一张图片文件');
        }

        return $file;
    }

    /**
     * @return array{name: string, ext: string, mime: string, size_bytes: int}
     */
    private function prepareCredentialImageUpload(UploadFile $file, int $maxSizeBytes): array
    {
        $uploadName = trim((string)($file->getUploadName() ?? ''));
        $displayName = $uploadName !== '' ? basename($uploadName) : 'unnamed-file';

        if (!$file->isValid()) {
            throw new \InvalidArgumentException(sprintf('上传文件 "%s" 无效', $displayName));
        }

        $sizeBytes = max(0, (int)$file->getSize());
        if ($sizeBytes <= 0) {
            throw new \InvalidArgumentException(sprintf('上传文件 "%s" 为空', $displayName));
        }

        if ($sizeBytes > $maxSizeBytes) {
            throw new \InvalidArgumentException(sprintf(
                '上传文件 "%s" 超过系统限制 %d KB',
                $displayName,
                (int)ceil($maxSizeBytes / 1024)
            ));
        }

        $uploadExtension = strtolower(trim((string)$file->getUploadExtension()));
        if ($uploadExtension !== '' && !in_array($uploadExtension, ['jpg', 'jpeg', 'png', 'bmp', 'gif'], true)) {
            throw new \InvalidArgumentException(sprintf('上传文件 "%s" 格式不支持', $displayName));
        }

        $imageInfo = @getimagesize($file->getPathname());
        $mime = is_array($imageInfo) ? trim((string)($imageInfo['mime'] ?? '')) : '';
        $allowedMimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/x-ms-bmp' => 'bmp',
        ];
        if ($mime === '' || !isset($allowedMimeMap[$mime])) {
            throw new \InvalidArgumentException(sprintf('上传文件 "%s" 不是支持的图片', $displayName));
        }

        return [
            'name' => mb_substr($displayName, 0, 50),
            'ext' => $allowedMimeMap[$mime],
            'mime' => $mime,
            'size_bytes' => $sizeBytes,
        ];
    }

    private function credentialImageUploadRoot(): string
    {
        return UploadWorkspace::directoryPath('payment-accounts');
    }

    private function deleteConfirmationPhrase(int $accountId): string
    {
        return 'DELETE MERCHANT ACCOUNT ' . $accountId;
    }

    /**
     * @param array<int, int> $accountIds
     */
    private function batchDeleteConfirmationPhrase(array $accountIds): string
    {
        return sprintf(
            'DELETE MERCHANT ACCOUNT BATCH %d-%s',
            count($accountIds),
            strtoupper(substr(md5(implode(',', $accountIds)), 0, 6))
        );
    }

    private function normalizeOnlineStatus(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_numeric($value)) {
            $status = (int)$value;
            if (in_array($status, [0, 1], true)) {
                return $status;
            }
        }

        $normalized = strtolower(trim((string)$value));

        return match ($normalized) {
            '1', 'true', 'yes', 'on', 'online', 'enable', 'enabled' => 1,
            '0', 'false', 'no', 'off', 'offline', 'disable', 'disabled' => 0,
            default => throw new \InvalidArgumentException('在线状态只能是 0 或 1'),
        };
    }

    private function normalizeEnabledStatus(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_numeric($value)) {
            $status = (int)$value;
            if (in_array($status, [0, 1, 2], true)) {
                return $status;
            }
        }

        $normalized = strtolower(trim((string)$value));

        return match ($normalized) {
            '1', 'true', 'yes', 'on', 'enable', 'enabled' => 1,
            '0', '2', 'false', 'no', 'off', 'disable', 'disabled' => $normalized === '2' ? 2 : 0,
            default => throw new \InvalidArgumentException('启用状态只能是 0、1 或 2'),
        };
    }

    private function normalizeCreateQrType(mixed $value, string $code, string $qrUrl): string
    {
        if ($code === 'wxpay_software') {
            return $this->normalizeWxpaySoftwareQrType($value, $qrUrl);
        }

        if ($code !== 'alipay_software') {
            return '';
        }

        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('二维码模式格式无效');
        }

        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            $normalized = 'agt';
        }

        if (!in_array($normalized, ['agt', 'pic'], true)) {
            throw new \InvalidArgumentException('支付宝软件版二维码模式只能是 agt 或 pic');
        }

        if ($normalized === 'pic' && $qrUrl === '') {
            throw new \InvalidArgumentException('图片模式下必须上传二维码图片');
        }

        return $normalized;
    }

    private function normalizeWxpaySoftwareQrType(mixed $value, string $qrUrl = ''): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('微信软件版收款模式格式无效');
        }

        $normalized = strtolower(trim((string)$value));

        if (in_array($normalized, ['appreciate', 'reward', 'rewardcode', 'reward_code'], true)) {
            return 'appreciate';
        }

        if (in_array($normalized, ['personormerchant', 'person_or_merchant', 'person-or-merchant', 'qr', 'qrcode', 'qr_code', ''], true)) {
            if ($normalized === '' && $this->looksLikeCredentialImageReference($qrUrl)) {
                return 'appreciate';
            }

            return 'personOrMerchant';
        }

        throw new \InvalidArgumentException('微信软件版收款模式只能是个人/经营码或赞赏码');
    }

    private function looksLikeCredentialImageReference(string $value): bool
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return false;
        }

        return preg_match('/(^\/upload\/)|\.(png|jpe?g|gif|bmp)(\?.*)?$/i', $normalized) === 1;
    }

    /**
     * @param array<int, string> $allowed
     * @param array<int, string> $default
     */
    private function normalizeModeCsv(mixed $value, array $allowed, array $default, string $field): string
    {
        if (is_object($value)) {
            throw new \InvalidArgumentException($field . ' 格式无效');
        }

        $items = [];
        if (is_array($value)) {
            $items = $value;
        } else {
            $raw = trim((string)$value);
            if ($raw !== '') {
                $items = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            }
        }

        $normalized = array_values(array_unique(array_filter(array_map(
            static fn($item): string => trim((string)$item),
            $items
        ), static fn(string $item): bool => $item !== '' && in_array($item, $allowed, true))));

        if ($normalized === []) {
            $normalized = $default;
        }

        return implode(',', $normalized);
    }

    private function normalizeJiaofeiyiPayMode(mixed $value): string
    {
        return $this->jiaofeiyi()->normalizePayMode($value);

        if (is_object($value)) {
            throw new \InvalidArgumentException('交费易支付模式格式无效');
        }

        if (is_array($value)) {
            $value = reset($value);
        }

        $normalized = trim((string)$value);

        return in_array($normalized, ['1', '2', '3'], true) ? $normalized : '2';
    }

    private function normalizeUniversalEpayMode(mixed $value): string
    {
        if (is_object($value)) {
            throw new \InvalidArgumentException('接口模式格式不正确');
        }

        if (is_array($value)) {
            $value = reset($value);
        }

        $normalized = strtolower(trim((string)$value));

        return match ($normalized) {
            '', '0', 'submit', 'page', 'web' => '0',
            '1', 'mapi', 'api' => '1',
            default => throw new \InvalidArgumentException('接口模式仅支持普通接口或接口直连'),
        };
    }

    private function normalizeOptionalHttpUrl(mixed $value, int $maxLength, string $field): string
    {
        $normalized = $this->normalizeOptionalText($value, $maxLength, $field);
        if ($normalized === '') {
            return '';
        }

        if (!preg_match('/^https?:\/\/.+/i', $normalized)) {
            throw new \InvalidArgumentException($field . ' 必须是 http 或 https 地址');
        }

        return $normalized;
    }

    private function normalizeOptionalText(mixed $value, int $maxLength, string $field): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($field . ' 格式无效');
        }

        $normalized = trim((string)$value);
        if (mb_strlen($normalized) > $maxLength) {
            throw new \InvalidArgumentException($field . ' 长度超出限制');
        }

        return $normalized;
    }

    private function normalizeRequiredText(mixed $value, int $maxLength, string $field): string
    {
        $normalized = $this->normalizeOptionalText($value, $maxLength, $field);
        if ($normalized === '') {
            throw new \InvalidArgumentException($field . ' 不能为空');
        }

        return $normalized;
    }

    private function normalizeNonNegativeInteger(mixed $value, string $field): int
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($field . ' 格式无效');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            throw new \InvalidArgumentException($field . ' 不能为空');
        }

        if (!preg_match('/^\d+$/', $normalized)) {
            throw new \InvalidArgumentException($field . ' 必须是非负整数');
        }

        if (strlen($normalized) > 10) {
            throw new \InvalidArgumentException($field . ' 数值过大');
        }

        return (int)$normalized;
    }

    private function normalizeOptionalDecimal(mixed $value, int $maxLength, string $field): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($field . ' 格式无效');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            return '';
        }

        if (mb_strlen($normalized) > $maxLength) {
            throw new \InvalidArgumentException($field . ' 长度超出限制');
        }

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw new \InvalidArgumentException($field . ' 必须是最多 2 位小数的非负金额');
        }

        return $normalized;
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return null;
        }

        return substr($value, 0, 10);
    }

    /**
     * @return array<int, int>
     */
    private function normalizeAccountIds(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = preg_split('/\s*,\s*/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            }
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException('账号编号必须是数组');
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_numeric($item)) {
                throw new \InvalidArgumentException('账号编号必须是正整数');
            }

            $accountId = (int)$item;
            if ($accountId <= 0) {
                throw new \InvalidArgumentException('账号编号必须是正整数');
            }

            $normalized[$accountId] = $accountId;
        }

        return array_values($normalized);
    }

    private function wantsJson(Request $request): bool
    {
        $format = strtolower(trim((string)$request->get('format', '')));
        if ($format === 'json') {
            return true;
        }

        $accept = strtolower(trim((string)$request->header('accept', '')));

        return str_contains($accept, 'application/json')
            || str_contains($accept, 'text/json')
            || str_contains($accept, '+json');
    }

    private function merchantFrontendBaseUrl(Request $request): string
    {
        return FrontendUrlBuilder::merchantBaseUrl($request);
    }

    private function merchantLoginRedirect(Request $request, ?string $redirectPath = null): Response
    {
        return redirect($this->merchantLoginUrl($request, $redirectPath));
    }

    private function merchantLoginUrl(Request $request, ?string $redirectPath = null): string
    {
        $query = [];
        if ($redirectPath !== null && $redirectPath !== '' && $redirectPath !== '/merchant/login') {
            $query['redirect'] = $redirectPath;
        }

        return $this->withHashPath($this->merchantFrontendBaseUrl($request), '/merchant/login', $query);
    }

    private function merchantSpaRedirect(Request $request, string $targetPath, array $query = []): Response
    {
        return redirect($this->withHashPath($this->merchantFrontendBaseUrl($request), $targetPath, $query));
    }

    private function withHashPath(string $baseUrl, string $path, array $query = []): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $path = '/' . ltrim($path, '/');
        $queryString = $query === [] ? '' : ('?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));

        if (str_contains($baseUrl, '#')) {
            return preg_replace('#/+$#', '', $baseUrl) . $path . $queryString;
        }

        return $baseUrl . '/#' . $path . $queryString;
    }

    private function requestOrigin(Request $request): string
    {
        return \app\support\FrontendUrlBuilder::requestOrigin($request);
    }

    private function requestScheme(Request $request): string
    {
        $forwardedProto = strtolower(trim((string)$request->header('x-forwarded-proto', '')));
        if ($forwardedProto !== '') {
            $proto = trim((string)(explode(',', $forwardedProto)[0] ?? ''));
            if (in_array($proto, ['http', 'https'], true)) {
                return $proto;
            }
        }

        if ((string)$request->header('front-end-https', '') === 'on') {
            return 'https';
        }

        if ((string)$request->header('x-forwarded-port', '') === '443') {
            return 'https';
        }

        return 'http';
    }

    private function merchantFromRequest(Request $request): ?array
    {
        $token = MerchantFrontSession::resolveToken($request);
        if ($token === '') {
            return null;
        }

        $row = Db::table(BusinessTable::user('merchant'))
            ->leftJoin(BusinessTable::vip('vip'), 'merchant.vip_id', '=', 'vip.id')
            ->select(
                'merchant.id',
                'merchant.username',
                'merchant.vip_id',
                'merchant.vip_time',
                'merchant.is_frozen',
                'merchant.frozen_reason',
                'vip.name as vip_name'
            )
            ->where('merchant.token', $token)
            ->first();

        return $row ? (array)$row : null;
    }

    private function merchantAuthError(): Response
    {
        $payload = [
            'code' => 401,
            'msg' => '请先登录商户账号',
            'message' => '请先登录商户账号',
        ];
        $payload['msg'] = ApiResponse::normalizeText((string)$payload['msg']);
        $payload['message'] = ApiResponse::normalizeText((string)$payload['message']);

        return json($payload, JSON_UNESCAPED_UNICODE)->withStatus(401);
    }

    private function merchantCollectionSuccess(
        array $records,
        array $pagination,
        array $summary,
        array $writeActions,
        array $catalog = []
    ): Response {
        $payload = [
            'code' => 0,
            'msg' => '成功',
            'message' => '成功',
            'data' => $records,
            'records' => $records,
            'pagination' => [
                'current' => (int)($pagination['current'] ?? 1),
                'size' => (int)($pagination['size'] ?? count($records)),
                'total' => (int)($pagination['total'] ?? count($records)),
            ],
            'summary' => $summary,
            'write_actions' => $writeActions,
            'catalog' => $catalog,
        ];
        $payload['msg'] = ApiResponse::normalizeText((string)$payload['msg']);
        $payload['message'] = ApiResponse::normalizeText((string)$payload['message']);

        return json($payload, JSON_UNESCAPED_UNICODE);
    }

    private function merchantDataSuccess(array $data, string $message): Response
    {
        $message = ApiResponse::normalizeText($message);

        return json([
            'code' => 0,
            'msg' => $message,
            'message' => $message,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function merchantError(
        string $message,
        int $status = 422,
        int $code = 201,
        array $extra = []
    ): Response {
        $message = ApiResponse::normalizeText($message);

        return json(array_merge([
            'code' => $code,
            'msg' => $message,
            'message' => $message,
        ], $extra), JSON_UNESCAPED_UNICODE)->withStatus($status);
    }

    private function writeGuard(Request $request, bool $requireWritable = true): array|Response
    {
        $merchant = $this->merchantFromRequest($request);
        if ($merchant === null) {
            return $this->merchantAuthError();
        }

        if ($requireWritable && (int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->merchantError('商户账户已被冻结，暂时无法维护收款账号', 403, 201);
        }

        return $merchant;
    }

    private function requestPayload(Request $request): array
    {
        $payload = RequestPayload::all($request);
        if ($payload !== []) {
            return $payload;
        }

        $post = $request->post();
        return is_array($post) ? $post : [];
    }

    private function jiaofeiyi(): JiaofeiyiSupport
    {
        return new JiaofeiyiSupport();
    }
}
