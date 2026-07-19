<?php

namespace app\controller;

use app\service\payment\PaymentPluginManager;
use app\support\AdminFixtureTextNormalizer;
use app\support\AdminOrderFormatter;
use app\support\AdminPaymentAccountFormatter;
use app\support\AdminRouteAuthorization;
use app\support\ApiResponse;
use app\support\BusinessTable;
use app\support\QrCodeService;
use app\support\RequestPayload;
use app\support\SystemConfig;
use app\support\UploadWorkspace;
use Illuminate\Database\Query\Builder;
use Plugins\Payments\Shared\Support\JiaofeiyiSupport;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\Http\UploadFile;

class PaymentAccountController
{
    private const CREDENTIAL_IMAGE_UPLOAD_URL = '/api/admin/payment-accounts/credential-image';
    private const CREDENTIAL_DECODE_URL = '/api/admin/payment-accounts/credential-decode';

    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $query = $this->accountQuery();
        $this->applyFilters($query, $request);

        $summary = $this->summary(clone $query);
        $total = (int)(clone $query)->count('account.id');
        $rows = $query
            ->orderByDesc('account.id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        $accountIds = array_values(array_unique(array_map(
            static fn ($row): int => (int)((array)$row)['id'],
            $rows
        )));
        $stats = $this->loadOrderStats($accountIds);

        $records = array_map(static function ($row) use ($stats): array {
            $record = (array)$row;
            $accountId = (int)($record['id'] ?? 0);

            return AdminPaymentAccountFormatter::format($record, $stats[$accountId] ?? []);
        }, $rows);

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
        $id = $this->accountIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('请输入收款账号 ID', 422, null, 422);
        }

        $detail = $this->findAccountDetail($id);
        if ($detail === null) {
            return ApiResponse::error('收款账号不存在', 404, null, 404);
        }

        return ApiResponse::success($detail);
    }

    public function create(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'add');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $context = $this->resolveAdminCreateContext($payload);
            $writePayload = $this->normalizeCreatePayload($payload, $context);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        try {
            $accountId = (int)Db::transaction(function () use ($writePayload, $payload): int {
                $now = date('Y-m-d H:i:s');
                $accountId = (int)Db::table(BusinessTable::account())->insertGetId([
                    'code' => $writePayload['code'],
                    'type' => $writePayload['type'],
                    'user_id' => $writePayload['user_id'],
                    'qr_url' => $writePayload['qr_url'] === '' ? null : $writePayload['qr_url'],
                    'qr_type' => $writePayload['qr_type'],
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
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $detail = $this->findAccountDetail($accountId);
        if ($detail === null) {
            return ApiResponse::error('收款账号已创建，但回读详情失败', 500, null, 500);
        }

        return ApiResponse::success(
            array_merge(
                $detail,
                [
                    'created_account_id' => $accountId,
                    'created_account_label' => $this->accountLabel(
                        $this->accountRecord($accountId) ?? ['id' => $accountId],
                        (array)($detail['item'] ?? [])
                    ),
                ]
            ),
            '收款账号已创建'
        );
    }

    public function uploadCredentialImage(Request $request): Response
    {
        $authorizationError = $this->authorizeWriteAny($request, ['add', 'edit']);
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $configuredFileType = SystemConfig::int('file-type', 1);
        if ($configuredFileType !== 1) {
            return ApiResponse::error(
                '当前仅在文件存储方式为本地存储时才支持上传收款凭证图片',
                422,
                [
                    'configured_file_type' => $configuredFileType,
                ],
                422
            );
        }

        try {
            $target = $this->normalizeCredentialImageTarget(RequestPayload::all($request));
            $file = $this->normalizeCredentialImageFile($request);
            $prepared = $this->prepareCredentialImageUpload(
                $file,
                max(1, SystemConfig::int('imageSize', 2000)) * 1024
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
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
                    throw new \RuntimeException('创建收款凭证上传目录失败');
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

            return ApiResponse::error(
                '收款凭证图片上传失败：' . $exception->getMessage(),
                500,
                null,
                500
            );
        }

        $this->recordCredentialImageUpload($request, $prepared, $href);

        return ApiResponse::success([
            'code' => $target['code'],
            'field' => $target['field'],
            'mode' => 'image',
            'value' => $href,
            'href' => $href,
            'preview_url' => $href,
            'photo_id' => $photoId,
            'path' => 'payment-accounts',
        ], '收款凭证图片已上传');
    }

    public function decodeCredentialImage(Request $request): Response
    {
        $authorizationError = $this->authorizeWriteAny($request, ['add', 'edit']);
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $target = $this->normalizeCredentialDecodeTarget(RequestPayload::all($request));
            (new PaymentPluginManager())->credentialDecodeProfile($target['code'], $target['field']);
            $file = $this->normalizeCredentialImageFile($request);
            $this->prepareCredentialImageUpload(
                $file,
                max(1, SystemConfig::int('imageSize', 2000)) * 1024
            );
            $decodedContent = (new QrCodeService())->decodeFile($file->getPathname());
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        } catch (\DomainException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        } catch (\Throwable $exception) {
            return ApiResponse::error($exception->getMessage(), 500, null, 500);
        }

        $this->recordCredentialImageDecode($request, $target, $decodedContent);

        return ApiResponse::success([
            'code' => $target['code'],
            'field' => $target['field'],
            'mode' => 'decoded_text',
            'value' => $decodedContent,
        ], '二维码内容已解析');
    }

    public function update(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->accountIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('请输入收款账号 ID', 422, null, 422);
        }

        $record = $this->accountRecord($id);
        if ($record === null) {
            return ApiResponse::error('收款账号不存在', 404, null, 404);
        }

        $payload = RequestPayload::all($request);

        try {
            $memo = $this->normalizeOptionalText(
                $payload['memo'] ?? ($record['memo'] ?? ''),
                50,
                '备注'
            );
            $daymaxcount = $this->normalizeNonNegativeInteger(
                $payload['daymaxcount'] ?? ($record['daymaxcount'] ?? 0),
                '单日次数上限'
            );
            $daymaxmoney = $this->normalizeOptionalDecimal(
                $payload['daymaxmoney'] ?? ($record['daymaxmoney'] ?? ''),
                50,
                '单日金额上限'
            );
            $allmaxcount = $this->normalizeNonNegativeInteger(
                $payload['allmaxcount'] ?? ($record['allmaxcount'] ?? 0),
                '累计次数上限'
            );
            $allmaxmoney = $this->normalizeOptionalDecimal(
                $payload['allmaxmoney'] ?? ($record['allmaxmoney'] ?? ''),
                50,
                '累计金额上限'
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table(BusinessTable::account())
            ->where('id', $id)
            ->update([
                'memo' => $memo === '' ? null : $memo,
                'daymaxcount' => $daymaxcount,
                'daymaxmoney' => $daymaxmoney === '' ? null : $daymaxmoney,
                'allmaxcount' => $allmaxcount,
                'allmaxmoney' => $allmaxmoney === '' ? null : $allmaxmoney,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        return ApiResponse::success(
            $this->findAccountDetail($id),
            '收款账号已更新'
        );
    }

    public function updateCredentials(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->accountIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('请输入收款账号 ID', 422, null, 422);
        }

        $record = $this->accountRecord($id);
        if ($record === null) {
            return ApiResponse::error('收款账号不存在', 404, null, 404);
        }

        $payload = RequestPayload::all($request);

        try {
            $updates = $this->normalizeCredentialPayload($payload, $record);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $writePayload = ['update_time' => date('Y-m-d H:i:s')];
        foreach ($updates as $key => $value) {
            $writePayload[$key] = is_string($value) && $value === '' ? null : $value;
        }

        try {
            Db::transaction(function () use ($id, $writePayload, $record, $payload, $updates): void {
                Db::table(BusinessTable::account())
                    ->where('id', $id)
                    ->update($writePayload);

                $this->saveManagedCredentialConfig(
                    (string)($record['code'] ?? ''),
                    $id,
                    $payload,
                    array_merge($record, $updates)
                );
            });
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success(
            $this->findAccountDetail($id),
            '收款凭证已更新'
        );
    }

    public function status(Request $request): Response
    {
        $authorizationError = $this->authorizeWriteAny($request, ['status', 'is_status']);
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->accountIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('请输入收款账号 ID', 422, null, 422);
        }

        if ($this->accountRecord($id) === null) {
            return ApiResponse::error('收款账号不存在', 404, null, 404);
        }

        $payload = RequestPayload::all($request);
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
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table(BusinessTable::account())
            ->where('id', $id)
            ->update($updates);

        return ApiResponse::success([
            'item' => $this->findAccount($id),
        ], '收款账号状态已更新');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->accountIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('请输入收款账号 ID', 422, null, 422);
        }

        $record = $this->accountRecord($id);
        if ($record === null) {
            return ApiResponse::error('收款账号不存在', 404, null, 404);
        }

        $item = $this->findAccount($id);

        return ApiResponse::success([
            'item' => $item,
            'audit' => $this->buildDeleteAudit($record, $item),
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->accountIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('请输入收款账号 ID', 422, null, 422);
        }

        $record = $this->accountRecord($id);
        if ($record === null) {
            return ApiResponse::error('收款账号不存在', 404, null, 404);
        }

        $item = $this->findAccount($id);
        $audit = $this->buildDeleteAudit($record, $item);
        if (empty($audit['can_delete'])) {
            return ApiResponse::error(
                '当前收款账号仍有关联依赖，暂时不能删除',
                422,
                ['audit' => $audit],
                422
            );
        }

        $payload = RequestPayload::all($request);
        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error(
                '删除确认口令不正确',
                422,
                ['audit' => $audit],
                422
            );
        }

        $now = date('Y-m-d H:i:s');
        Db::transaction(function () use ($id, $now, $record): void {
            Db::table(BusinessTable::pollPool())
                ->where('last_account_id', $id)
                ->update([
                    'last_account_id' => 0,
                    'update_time' => $now,
                ]);

            $this->detachInactiveOrderReferences([$id]);

            Db::table(BusinessTable::account())
                ->where('id', $id)
                ->delete();

            $this->deleteManagedCredentialConfigRows([
                [
                    'id' => $id,
                    'code' => (string)($record['code'] ?? ''),
                ],
            ]);
        });

        return ApiResponse::success([
            'deleted_account_id' => $id,
            'deleted_account_label' => (string)($audit['account_label'] ?? ('收款账号 #' . $id)),
            'audit' => $audit,
        ], '收款账号已删除');
    }

    public function batchDeleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $accountIds = $this->normalizeAccountIds($payload['account_ids'] ?? ($payload['ids'] ?? []));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success([
            'audit' => $this->batchDeleteAuditPayload($accountIds),
        ]);
    }

    public function batchDelete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $accountIds = $this->normalizeAccountIds($payload['account_ids'] ?? ($payload['ids'] ?? []));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $audit = $this->batchDeleteAuditPayload($accountIds);
        if (empty($audit['can_delete_all'])) {
            return ApiResponse::error(
                '当前选中的收款账号暂时不能批量删除，请先清理异常项后再试',
                422,
                ['audit' => $audit],
                422
            );
        }

        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error(
                '批量删除确认口令不正确',
                422,
                ['audit' => $audit],
                422
            );
        }

        $deletableAccountIds = array_values(array_map('intval', (array)($audit['deletable_account_ids'] ?? [])));
        if ($deletableAccountIds !== []) {
            $now = date('Y-m-d H:i:s');
            $rows = $this->loadAccountRowsByIds($deletableAccountIds);
            Db::transaction(function () use ($deletableAccountIds, $now, $rows): void {
                Db::table(BusinessTable::pollPool())
                    ->whereIn('last_account_id', $deletableAccountIds)
                    ->update([
                        'last_account_id' => 0,
                        'update_time' => $now,
                    ]);

                $this->detachInactiveOrderReferences($deletableAccountIds);

                Db::table(BusinessTable::account())
                    ->whereIn('id', $deletableAccountIds)
                    ->delete();

                $this->deleteManagedCredentialConfigRows($rows);
            });
        }

        return ApiResponse::success([
            'deleted_account_ids' => $deletableAccountIds,
            'deleted_count' => count($deletableAccountIds),
            'audit' => $audit,
        ], '批量删除收款账号已完成');
    }

    private function accountQuery(): Builder
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
            );
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
                    ->orWhere('admin_channel.name', 'like', '%' . $keyword . '%')
                    ->orWhere('merchant.username', 'like', '%' . $keyword . '%')
                    ->orWhere('merchant.name', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder
                        ->orWhere('account.id', (int)$keyword)
                        ->orWhere('account.user_id', (int)$keyword);
                }
            });
        }

        $userId = trim((string)$request->get('user_id', ''));
        if ($userId !== '') {
            $query->where('account.user_id', 'like', '%' . $userId . '%');
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
        if ($enabled !== '' && in_array($enabled, ['1', '2', '0'], true)) {
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

    private function summary(Builder $query): array
    {
        $accountIds = (clone $query)->pluck('account.id')->toArray();
        $amountStats = $this->summaryOrderStats(array_map('intval', $accountIds));

        return [
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
            throw new \InvalidArgumentException('请上传一张凭证图片');
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
            throw new \InvalidArgumentException(sprintf('uploaded file "%s" is invalid', $displayName));
        }

        $sizeBytes = max(0, (int)$file->getSize());
        if ($sizeBytes <= 0) {
            throw new \InvalidArgumentException(sprintf('uploaded file "%s" is empty', $displayName));
        }

        if ($sizeBytes > $maxSizeBytes) {
            throw new \InvalidArgumentException(sprintf(
                '上传文件“%s”超过了 %d KB 的大小限制',
                $displayName,
                (int)ceil($maxSizeBytes / 1024)
            ));
        }

        $uploadExtension = strtolower(trim((string)$file->getUploadExtension()));
        if ($uploadExtension !== '' && !in_array($uploadExtension, ['jpg', 'jpeg', 'png', 'bmp', 'gif'], true)) {
            throw new \InvalidArgumentException(sprintf('上传文件“%s”的扩展名不受支持', $displayName));
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
            throw new \InvalidArgumentException(sprintf('uploaded file "%s" is not a supported image', $displayName));
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

    /**
     * @param array{name: string, ext: string, mime: string, size_bytes: int} $prepared
     */
    private function recordCredentialImageUpload(Request $request, array $prepared, string $href): void
    {
        $adminId = (int)(((array)($request->admin ?? []))['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => self::CREDENTIAL_IMAGE_UPLOAD_URL,
            'desc' => sprintf(
                'payment account credential image upload href="%s" size=%d mime=%s name="%s"',
                $this->truncateLogText($href, 255),
                (int)($prepared['size_bytes'] ?? 0),
                $this->truncateLogText((string)($prepared['mime'] ?? ''), 60),
                $this->truncateLogText((string)($prepared['name'] ?? ''), 80)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array{code: string, field: string} $target
     */
    private function recordCredentialImageDecode(Request $request, array $target, string $decodedContent): void
    {
        $adminId = (int)(((array)($request->admin ?? []))['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => self::CREDENTIAL_DECODE_URL,
            'desc' => sprintf(
                'payment account credential decode code="%s" field="%s" value="%s"',
                $this->truncateLogText($target['code'], 80),
                $this->truncateLogText($target['field'], 80),
                $this->truncateLogText($decodedContent, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function truncateLogText(string $value, int $limit): string
    {
        $value = trim(str_replace(["\r", "\n"], ' ', $value));
        if ($value === '') {
            return '';
        }

        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit - 3) . '...' : $value;
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'PaymentAccounts', $authMark);
    }

    /**
     * @param array<int, string> $authMarks
     */
    private function authorizeWriteAny(Request $request, array $authMarks): ?Response
    {
        return (new AdminRouteAuthorization())->authorizeAny($request, 'PaymentAccounts', $authMarks);
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

    private function accountRecord(int $id): ?array
    {
        $row = $this->accountQuery()
            ->where('account.id', $id)
            ->first();

        return $row ? (array)$row : null;
    }

    private function findAccount(int $id): ?array
    {
        $record = $this->accountRecord($id);
        if ($record === null) {
            return null;
        }

        $stats = $this->loadOrderStats([$id]);

        return AdminPaymentAccountFormatter::format($record, $stats[$id] ?? []);
    }

    private function findAccountDetail(int $id): ?array
    {
        $record = $this->accountRecord($id);
        if ($record === null) {
            return null;
        }

        return [
            'item' => $this->findAccount($id),
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
     * @return array<string, mixed>
     */
    /**
     * @param array<string, mixed> $payload
     * @return array{code: string, payment_method_type: string, payment_method_label: string, plugin_name: string}
     */
    private function resolveAdminCreateContext(array $payload): array
    {
        $pluginCode = strtolower(trim((string)($payload['plugin_code'] ?? ($payload['code'] ?? ''))));
        if ($pluginCode === '') {
            throw new \InvalidArgumentException('请选择支付插件');
        }

        if (!isset($this->createCodeCatalog()[$pluginCode])) {
            throw new \InvalidArgumentException('当前支付插件暂不支持后台创建收款账户');
        }

        try {
            $detail = (new PaymentPluginManager())->detail($pluginCode);
        } catch (\Throwable) {
            throw new \InvalidArgumentException('所选支付插件不可用或尚未安装');
        }

        $state = is_array($detail['state'] ?? null) ? $detail['state'] : [];
        if (empty($state['installed']) || empty($state['enabled'])) {
            throw new \InvalidArgumentException('所选支付插件尚未安装或未启用');
        }

        $manifest = is_array($detail['manifest'] ?? null) ? $detail['manifest'] : [];
        $methodTypes = array_values(array_unique(array_filter(array_map(
            static function ($value): string {
                $normalized = strtolower(trim((string)$value));

                return match ($normalized) {
                    'wechat' => 'wxpay',
                    'qq' => 'qqpay',
                    default => $normalized,
                };
            },
            (array)($manifest['supported_payment_types'] ?? [])
        ))));

        if ($methodTypes === []) {
            $methodTypes = $this->supportedMethodTypesForCreateCode($pluginCode);
        }

        $paymentMethodType = strtolower(trim((string)($payload['payment_method_type'] ?? '')));
        if ($paymentMethodType === '') {
            $paymentMethodType = $methodTypes[0] ?? '';
        }

        if ($paymentMethodType === '') {
            throw new \InvalidArgumentException('所选插件未声明可用支付方式');
        }

        if (!in_array($paymentMethodType, $methodTypes, true)) {
            throw new \InvalidArgumentException('所选插件不支持当前支付方式');
        }

        $paymentMethodMap = $this->paymentMethodMap();
        $paymentMethod = $paymentMethodMap[$paymentMethodType] ?? $this->resolvePaymentMethod($paymentMethodType);

        return [
            'code' => $pluginCode,
            'payment_method_type' => $paymentMethodType,
            'payment_method_label' => trim((string)($paymentMethod['label'] ?? '')),
            'plugin_name' => trim((string)($manifest['name'] ?? $pluginCode)),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array{code: string, payment_method_type: string, payment_method_label: string, plugin_name: string} $context
     * @return array<string, mixed>
     */
    private function normalizeCreatePayload(array $payload, array $context): array
    {
        $code = $context['code'];
        $channel = $this->findCreatableChannel($code);
        if ($channel === null) {
            throw new \InvalidArgumentException('当前收款账号类型未启用或对应通道不可用');
        }

        $merchantUserId = $this->normalizeMerchantUserId($payload['user_id'] ?? '');
        if (!$this->merchantExists($merchantUserId)) {
            throw new \InvalidArgumentException('商户不存在');
        }

        $identifier = $this->normalizeRequiredText(
            $payload['identifier'] ?? '',
            50,
            (string)($this->createCodeCatalog()[$code]['identifier_label'] ?? '账号标识')
        );
        $pid = $this->normalizeOptionalText(
            $payload['pid'] ?? '',
            50,
            'PID'
        );
        $qrUrl = $this->normalizeOptionalText(
            $payload['qr_url'] ?? '',
            12000,
            '二维码内容'
        );
        $cookie = $this->normalizeOptionalText(
            $payload['cookie'] ?? '',
            12000,
            '扩展凭证'
        );
        $remark = strip_tags($this->normalizeOptionalText(
            $payload['remark'] ?? '',
            225,
            '备注'
        ));
        $wxGuid = $code === 'alipay_official'
            ? $this->normalizeOptionalText(
                $payload['wx_guid'] ?? '',
                12000,
                '应用公钥证书'
            )
            : $this->normalizeOptionalText(
                $payload['wx_guid'] ?? '',
                50,
                '证书序列号'
            );
        $cloudId = in_array($code, ['jiaofeiyi_alipay', 'jiaofeiyi_wxpay'], true)
            ? $this->normalizeOptionalHttpUrl(
                $payload['cloud_id'] ?? '',
                2500,
                '代理IP API 地址'
            )
            : ($code === 'alipay_official'
                ? $this->normalizeOptionalText(
                    $payload['cloud_id'] ?? '',
                    12000,
                    '支付宝公钥证书'
                )
                : $this->normalizeOptionalText(
                    $payload['cloud_id'] ?? '',
                    50,
                    '云端标识'
                ));
        $extraValue = $this->normalizeOptionalText(
            $payload['extra_value'] ?? '',
            $code === 'alipay_official' ? 20000 : 12000,
            '附加配置'
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
                'user_id' => $merchantUserId,
                'qr_url' => $qrUrl,
                'qr_type' => '',
                'status' => $this->normalizeOnlineStatus($payload['status'] ?? 0),
                'is_status' => $this->normalizeEnabledStatus($payload['is_status'] ?? 1),
                'memo' => $this->normalizeOptionalText(
                    $payload['memo'] ?? '',
                    50,
                    '备注'
                ),
                'cookie' => $cookie,
                'allmaxcount' => $this->normalizeNonNegativeInteger(
                    $payload['allmaxcount'] ?? 0,
                    '累计次数上限'
                ),
                'allmaxmoney' => $this->normalizeOptionalDecimal(
                    $payload['allmaxmoney'] ?? '',
                    50,
                    '累计金额上限'
                ),
                'daymaxcount' => $this->normalizeNonNegativeInteger(
                    $payload['daymaxcount'] ?? 0,
                    '单日次数上限'
                ),
                'daymaxmoney' => $this->normalizeOptionalDecimal(
                    $payload['daymaxmoney'] ?? '',
                    50,
                    '单日金额上限'
                ),
                'remark' => $remark,
                'wx_guid' => $wxGuid,
                'cloud_id' => $cloudId,
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
                '二维码内容'
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
                    '商户号'
                ),
                'cookie' => $this->encodeJiaofeiyiConfig(
                    $this->normalizeOptionalText(
                        $payload['cookie'] ?? ($jiaofeiyiConfig['store_name'] ?? ''),
                        12000,
                        '门店名称'
                    ),
                    $this->normalizeOptionalHttpUrl(
                        $payload['extra_value'] ?? ($jiaofeiyiConfig['remote_api_url'] ?? ''),
                        2500,
                        '远程接口地址'
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
                    '官方支付模式'
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
                    '公钥'
                ),
                'qr_url' => $this->normalizeRequiredText(
                    $payload['qr_url'] ?? ($record['qr_url'] ?? ''),
                    12000,
                    '私钥'
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

    /**
     * @param array<int, int> $accountIds
     * @return array<int, array<string, mixed>>
     */
    private function loadAccountRowsByIds(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        return array_map(
            static fn($row): array => (array)$row,
            $this->accountQuery()
                ->whereIn('account.id', $accountIds)
                ->get()
                ->toArray()
        );
    }

    private function buildDeleteAudit(array $record, ?array $item = null): array
    {
        $accountId = (int)($record['id'] ?? 0);
        $item = $item ?? $this->findAccount($accountId) ?? [];
        $dependency = $this->loadDeleteDependencySummary($accountId);

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
            $blockingReasons[] = sprintf('当前仍有 %d 笔已支付订单引用该收款账户，删除后会影响历史对账，暂不允许删除。', $paidOrderCount);
        }
        if ($activePendingOrderCount > 0) {
            $blockingReasons[] = sprintf('当前仍有 %d 笔待支付订单仍在有效期内，需先等待失效或完成支付后再删除。', $activePendingOrderCount);
        }
        if ($poolItemCount > 0) {
            $blockingReasons[] = sprintf('当前仍有 %d 个轮询池条目引用该收款账户，涉及 %d 个轮询池，请先移出轮询池后再删除。', $poolItemCount, $poolCount);
        }
        /*
        if (false && $orderCount > 0) {
            $blockingReasons[] = sprintf(
                '历史订单仍在引用该收款账号（共 %d 笔，其中已支付 %d 笔）。',
                $orderCount,
                $paidOrderCount
            );
        }
        if (false && $poolItemCount > 0) {
            $blockingReasons[] = sprintf(
                '轮询池仍在使用该收款账号（共 %d 个池条目，分布于 %d 个轮询池）。',
                $poolItemCount,
                $poolCount
            );
        }
        */

        $warnings = [
            '删除后会永久移除该收款账号保存的凭证与运行限额配置。',
            '商户、支付方式、支付通道与历史订单记录不会被自动删除。',
        ];
        if ($detachableOrderCount > 0) {
            $warnings[] = sprintf('删除时会自动解除 %d 笔失效或不可继续支付订单与当前收款账户的关联，订单记录仍会保留。', $detachableOrderCount);
        }
        if ($lastSelectedPoolCount > 0) {
            $warnings[] = sprintf(
                '仍在指向该收款账号的 %d 个轮询池会同步重置最近选中游标。',
                $lastSelectedPoolCount
            );
        }

        return [
            'account_id' => $accountId,
            'account_label' => $this->accountLabel($record, $item),
            'merchant_display' => (string)($item['merchant_display'] ?? ''),
            'channel_label' => $this->channelLabel($record, $item),
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

    /**
     * @param array<int, int> $accountIds
     * @return array<string, mixed>
     */
    private function batchDeleteAuditPayload(array $accountIds): array
    {
        $rows = $this->loadAccountRowsByIds($accountIds);
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
                    'account_label' => '收款账号 #' . $accountId,
                    'merchant_display' => '',
                    'channel_label' => '',
                    'exists' => false,
                    'can_delete' => false,
                    'blocking_reasons' => ['该收款账号记录不存在，请刷新列表后重新选择。'],
                    'warnings' => ['请先移除不存在的收款账号后再重试批量删除。'],
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

            $item = $this->findAccount($accountId);
            $audit = $this->buildDeleteAudit($row, $item);
            $summary = (array)($audit['summary'] ?? []);

            $items[] = [
                'account_id' => $accountId,
                'account_label' => (string)($audit['account_label'] ?? ('收款账号 #' . $accountId)),
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
        $aggregate = $this->loadBatchDeleteDependencySummary($existingAccountIds);

        $warnings = [];
        if ($missingAccountIds !== []) {
            $warnings[] = '所选收款账号中存在已不存在的记录，请先移除后再继续。';
        }
        if ($blockedAccountIds !== []) {
            $warnings[] = '所选收款账号中仍有记录存在关联订单或轮询池分配，暂时不能删除。';
        }
        if ((int)($aggregate['detachable_order_count'] ?? 0) > 0) {
            $warnings[] = sprintf('批量删除时会自动解除 %d 笔失效或不可继续支付订单与原收款账户的关联，订单记录仍会保留。', (int)($aggregate['detachable_order_count'] ?? 0));
        }
        if ((int)($aggregate['last_selected_pool_count'] ?? 0) > 0) {
            $warnings[] = sprintf(
                '删除后会同步重置 %d 个轮询池的最近选中游标。',
                (int)($aggregate['last_selected_pool_count'] ?? 0)
            );
        }
        if ($deletableAccountIds !== []) {
            $warnings[] = '确认后，符合条件的收款账号会被永久删除。';
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
     * @return array<string, mixed>
     */
    private function loadDeleteDependencySummary(int $accountId): array
    {
        return $this->loadBatchDeleteDependencySummary($accountId > 0 ? [$accountId] : []);
    }

    /**
     * @param array<int, int> $accountIds
     * @return array<string, mixed>
     */
    private function loadBatchDeleteDependencySummary(array $accountIds): array
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
            ->whereIn('account_id', $accountIds)
            ->first();

        $lastPoolRow = Db::table(BusinessTable::pollPool())
            ->selectRaw('COUNT(*) as last_selected_pool_count')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_last_selected_pool_count')
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
    private function detachInactiveOrderReferences(array $accountIds): int
    {
        if ($accountIds === []) {
            return 0;
        }

        $now = time();
        $detachableCondition = $this->detachableOrderConditionSql($now);

        return Db::table(BusinessTable::order())
            ->where('pay_type', 1)
            ->whereIn('account_id', $accountIds)
            ->whereRaw($detachableCondition)
            ->update(['account_id' => 0]);
    }

    private function accountLabel(array $record, array $item = []): string
    {
        $codeLabel = trim((string)($item['code_label'] ?? $record['channel_name'] ?? ''));
        $merchantDisplay = trim((string)($item['merchant_display'] ?? ''));
        $accountId = (int)($record['id'] ?? 0);

        $parts = ['#' . $accountId];
        if ($codeLabel !== '') {
            $parts[] = $codeLabel;
        }
        if ($merchantDisplay !== '') {
            $parts[] = $merchantDisplay;
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
                'identifier_label' => 'QQ号',
            ],
            'usdt' => [
                'type' => 'usdt',
                'identifier_field' => 'wxname',
                'identifier_label' => '钱包地址',
            ],
            'alipay_bill' => [
                'type' => 'alipay',
                'identifier_field' => 'wxname',
                'identifier_label' => '应用ID',
            ],
            'alipay_mck' => [
                'type' => 'alipay',
                'identifier_field' => 'wxname',
                'identifier_label' => '应用ID',
            ],
            'alipay_official' => [
                'type' => 'alipay',
                'identifier_field' => 'wxname',
                'identifier_label' => '应用ID',
            ],
            'wxpay_v3' => [
                'type' => 'wxpay',
                'identifier_field' => 'wxname',
                'identifier_label' => '应用ID',
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
        return array_merge($this->createCodeCatalog(), [
            'alipay_bill' => [
                'type' => 'alipay',
                'identifier_field' => 'wxname',
                'identifier_label' => '应用ID',
            ],
            'alipay_mck' => [
                'type' => 'alipay',
                'identifier_field' => 'wxname',
                'identifier_label' => '应用ID',
            ],
            'alipay_official' => [
                'type' => 'alipay',
                'identifier_field' => 'wxname',
                'identifier_label' => '应用ID',
            ],
            'wxpay_v3' => [
                'type' => 'wxpay',
                'identifier_field' => 'wxname',
                'identifier_label' => '应用ID',
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
        ]);
    }

    private function normalizeCreateCode(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('收款账号类型格式无效');
        }

        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            throw new \InvalidArgumentException('收款账号类型不能为空');
        }

        if (!isset($this->createCodeCatalog()[$normalized])) {
            throw new \InvalidArgumentException('当前收款账号类型暂不支持直接新建');
        }

        return $normalized;
    }

    private function normalizeCredentialCode(string $value): string
    {
        $normalized = strtolower(trim($value));
        if (!$this->supportsCredentialEditing($normalized)) {
            throw new \InvalidArgumentException('当前收款账号类型暂不支持直接编辑凭据');
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
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

    private function merchantExists(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return Db::table(BusinessTable::user())
            ->where('id', $userId)
            ->exists();
    }

    private function normalizeMerchantUserId(mixed $value): int
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('商户编号格式不正确');
        }

        $normalized = trim((string)$value);
        if ($normalized === '' || !preg_match('/^[1-9]\d*$/', $normalized)) {
            throw new \InvalidArgumentException('商户编号必须是正整数');
        }

        return (int)$normalized;
    }

    private function supportsCredentialEditing(string $code): bool
    {
        $normalized = strtolower(trim($code));

        return $normalized !== '' && isset($this->credentialCodeCatalog()[$normalized]);
    }

    private function identifierFieldForCode(string $code): string
    {
        $field = trim((string)($this->credentialCodeCatalog()[$code]['identifier_field'] ?? ''));
        if ($field === '') {
            throw new \InvalidArgumentException('当前收款账号类型暂不支持凭证编辑');
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
    }

    /**
     * @param array<string, mixed> $record
     * @return array{store_name: string, remote_api_url: string, proxy_api_url: string}
     */
    private function decodeJiaofeiyiConfig(array $record): array
    {
        return $this->jiaofeiyi()->decodeConfig($record);
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

    private function deleteConfirmationPhrase(int $accountId): string
    {
        return '删除收款账号 ' . $accountId;
    }

    /**
     * @param array<int, int> $accountIds
     */
    private function batchDeleteConfirmationPhrase(array $accountIds): string
    {
        return sprintf(
            '批量删除收款账号 %d-%s',
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
            default => throw new \InvalidArgumentException('在线状态只能是在线或离线'),
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
            default => throw new \InvalidArgumentException('启用状态只能是停用、启用或系统锁定'),
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
            throw new \InvalidArgumentException('支付宝软件版使用图片模式时，必须上传二维码图片');
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
            throw new \InvalidArgumentException($field . '格式不正确');
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
            throw new \InvalidArgumentException('缴费易支付模式格式不正确');
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
            default => throw new \InvalidArgumentException('接口模式仅支持普通接口或 MAPI 接口'),
        };
    }

    private function normalizeOptionalHttpUrl(mixed $value, int $maxLength, string $field): string
    {
        $normalized = $this->normalizeOptionalText($value, $maxLength, $field);
        if ($normalized === '') {
            return '';
        }

        if (!preg_match('/^https?:\/\/.+/i', $normalized)) {
            throw new \InvalidArgumentException($field . '必须是 http 或 https 地址');
        }

        return $normalized;
    }

    private function normalizeOptionalText(mixed $value, int $maxLength, string $field): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($field . '格式不正确');
        }

        $normalized = trim((string)$value);
        if (mb_strlen($normalized) > $maxLength) {
            throw new \InvalidArgumentException($field . '长度不能超过 ' . $maxLength . ' 个字符');
        }

        return $normalized;
    }

    private function normalizeRequiredText(mixed $value, int $maxLength, string $field): string
    {
        $normalized = $this->normalizeOptionalText($value, $maxLength, $field);
        if ($normalized === '') {
            throw new \InvalidArgumentException('请输入' . $field);
        }

        return $normalized;
    }

    private function normalizeNonNegativeInteger(mixed $value, string $field): int
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($field . '格式不正确');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            throw new \InvalidArgumentException('请输入' . $field);
        }

        if (!preg_match('/^\d+$/', $normalized)) {
            throw new \InvalidArgumentException($field . '必须是非负整数');
        }

        if (strlen($normalized) > 10) {
            throw new \InvalidArgumentException($field . '数值过大');
        }

        return (int)$normalized;
    }

    private function normalizeOptionalDecimal(mixed $value, int $maxLength, string $field): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($field . '格式不正确');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            return '';
        }

        if (mb_strlen($normalized) > $maxLength) {
            throw new \InvalidArgumentException($field . '长度不能超过 ' . $maxLength . ' 个字符');
        }

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw new \InvalidArgumentException($field . '必须是最多保留两位小数的非负金额');
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
            throw new \InvalidArgumentException('收款账号编号列表格式不正确');
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_numeric($item)) {
                throw new \InvalidArgumentException('收款账号编号必须是正整数');
            }

            $accountId = (int)$item;
            if ($accountId <= 0) {
                throw new \InvalidArgumentException('收款账号编号必须是正整数');
            }

            $normalized[$accountId] = $accountId;
        }

        return array_values($normalized);
    }

    private function jiaofeiyi(): JiaofeiyiSupport
    {
        return new JiaofeiyiSupport();
    }
}
