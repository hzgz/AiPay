<?php

namespace app\controller;

use app\controller\concerns\MerchantPortalUrlSupport;
use app\support\AdminRouteAuthorization;
use app\support\AdminSmtpMailer;
use app\support\AdminUserFormatter;
use app\support\ApiResponse;
use app\support\LegacyPassword;
use app\support\MerchantEmailCampaignService;
use app\support\MerchantImpersonationService;
use app\support\RequestPayload;
use app\support\SystemConfig;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class UserController
{
    use MerchantPortalUrlSupport;

    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $query = $this->merchantQuery();
        $this->applyFilters($query, $request);

        $total = (int)(clone $query)->count('ypay_user.id');
        $rows = $query
            ->orderByDesc('ypay_user.id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        $userIds = array_values(array_unique(array_map(
            static fn ($row): int => (int)((array)$row)['id'],
            $rows
        )));
        $statsByUserId = $this->loadOrderStats($userIds);

        $records = array_map(function ($row) use ($statsByUserId): array {
            $record = (array)$row;
            $userId = (int)($record['id'] ?? 0);

            return AdminUserFormatter::formatUser($record, $statsByUserId[$userId] ?? []);
        }, $rows);

        return ApiResponse::success([
            'records' => $records,
            'current' => $current,
            'size' => $size,
            'total' => $total,
        ]);
    }

    public function show(Request $request): Response
    {
        $id = $this->userIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('user id is required', 422, null, 422);
        }

        $detail = $this->findUserDetail($id);
        if ($detail === null) {
            return ApiResponse::error('user not found', 404, null, 404);
        }

        return ApiResponse::success($detail);
    }

    public function template(Request $request): Response
    {
        return ApiResponse::success([
            'editable' => $this->createEditablePayload(),
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
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $id = Db::transaction(function () use ($payload): int {
            $this->applyCustomMerchantAutoIncrement();

            $userId = (int)Db::table('ypay_user')->insertGetId([
                'username' => $payload['username'],
                'password' => LegacyPassword::hash($payload['password']),
                'email' => $payload['email'],
                'mobile' => $payload['mobile'],
                'money' => '0.00',
                'user_key' => $this->generateSecret(32),
                'vip_id' => $payload['vip_id'] > 0 ? $payload['vip_id'] : null,
                'vip_time' => $payload['vip_id'] > 0 ? $payload['vip_time'] : null,
                'feilv' => $payload['vip_id'] > 0 ? $payload['fee_rate'] : null,
                'create_time' => date('Y-m-d H:i:s'),
                'is_frozen' => 0,
                'remarks' => $payload['remarks'],
            ]);

            Db::table('ypay_userbasic')->insert([
                'user_id' => $userId,
                'timeout_method' => 2,
                'timeout_url' => '/',
                'timeout_time' => '180',
                'loginfailure' => 0,
                'appkey' => $this->generateSecret(32),
                'order_tips' => 'close',
                'is_money_tips' => 'close',
                'money_tips' => '0',
                'is_rate' => $payload['is_rate'],
                'callback_hiddenName' => 0,
            ]);

            return $userId;
        });

        $detail = $this->findUserDetail($id);
        if ($detail === null) {
            return ApiResponse::error('merchant not found after creation', 500, null, 500);
        }

        return ApiResponse::success($detail, 'merchant created');
    }

    public function update(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->userIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('user id is required', 422, null, 422);
        }

        if ($this->baseUserRecord($id) === null) {
            return ApiResponse::error('user not found', 404, null, 404);
        }

        $payload = RequestPayload::all($request);

        try {
            $email = $this->normalizeEmail($payload['email'] ?? null);
            $mobile = $this->normalizeNullableString($payload['mobile'] ?? null, 50, 'merchant mobile');
            $remarks = $this->normalizeNullableString($payload['remarks'] ?? null, 255, 'merchant remarks');
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table('ypay_user')
            ->where('id', $id)
            ->update([
                'email' => $email,
                'mobile' => $mobile,
                'remarks' => $remarks,
            ]);

        $detail = $this->findUserDetail($id);
        if ($detail === null) {
            return ApiResponse::error('user not found', 404, null, 404);
        }

        return ApiResponse::success($detail, 'merchant profile updated');
    }

    public function status(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->userIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('user id is required', 422, null, 422);
        }

        if ($this->baseUserRecord($id) === null) {
            return ApiResponse::error('user not found', 404, null, 404);
        }

        $payload = RequestPayload::all($request);

        try {
            $status = $this->normalizeFrozenStatus($payload['status'] ?? null);
            $reason = $this->normalizeNullableString($payload['frozen_reason'] ?? null, 255, 'frozen reason');
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table('ypay_user')
            ->where('id', $id)
            ->update([
                'is_frozen' => $status,
                'frozen_reason' => $status === 1 ? $reason : null,
            ]);

        return ApiResponse::success([
            'item' => $this->findUserItem($id),
        ], 'merchant status updated');
    }

    public function business(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->userIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('user id is required', 422, null, 422);
        }

        $record = $this->baseUserRecord($id);
        if ($record === null) {
            return ApiResponse::error('user not found', 404, null, 404);
        }

        $payload = RequestPayload::all($request);
        $basicRecord = $this->userBasicRecord($id);
        $currentVipId = (int)($record['vip_id'] ?? 0);

        try {
            $vipId = $this->normalizeVipId($payload['vip_id'] ?? $currentVipId);
            $isRate = $this->normalizeBinaryToggle(
                $payload['is_rate'] ?? ($basicRecord['is_rate'] ?? 0),
                'merchant fee bearer'
            );

            $vipTime = null;
            $feeRate = null;

            if ($vipId > 0) {
                $vip = $this->vipRecord($vipId);
                if ($vip === null) {
                    throw new \InvalidArgumentException('vip package was not found');
                }

                if ((int)($vip['status'] ?? 0) !== 1 && $vipId !== $currentVipId) {
                    throw new \InvalidArgumentException('vip package is disabled and cannot be newly assigned');
                }

                $vipTime = $this->normalizeOptionalDateTime(
                    $payload['vip_time'] ?? $record['vip_time'] ?? null,
                    'merchant vip expire time'
                );

                if ($vipTime === null) {
                    $vipDays = max(0, (int)($vip['viptime'] ?? 0));
                    $vipTime = date('Y-m-d H:i:s', strtotime('+ ' . $vipDays . ' day'));
                }

                $feeRate = $this->normalizeFeeRate(
                    $payload['fee_rate'] ?? $payload['feilv'] ?? ($record['feilv'] ?? ''),
                    'merchant fee rate',
                    false
                );

                if ($feeRate === '') {
                    $feeRate = $this->normalizeFeeRate($vip['feilv'] ?? '', 'merchant fee rate');
                }
            }
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $this->ensureUserBasicRecord($id);

        Db::table('ypay_user')
            ->where('id', $id)
            ->update([
                'vip_id' => $vipId > 0 ? $vipId : null,
                'vip_time' => $vipId > 0 ? $vipTime : null,
                'feilv' => $vipId > 0 ? $feeRate : null,
            ]);

        Db::table('ypay_userbasic')
            ->where('user_id', $id)
            ->update([
                'is_rate' => $isRate,
            ]);

        $detail = $this->findUserDetail($id);
        if ($detail === null) {
            return ApiResponse::error('user not found', 404, null, 404);
        }

        return ApiResponse::success($detail, 'merchant vip and fee settings updated');
    }

    public function notifications(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->userIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('user id is required', 422, null, 422);
        }

        if ($this->baseUserRecord($id) === null) {
            return ApiResponse::error('user not found', 404, null, 404);
        }

        $this->ensureUserBasicRecord($id);

        $payload = RequestPayload::all($request);
        $item = $this->findUserItem($id);
        if ($item === null) {
            return ApiResponse::error('user not found', 404, null, 404);
        }

        $config = SystemConfig::all();

        try {
            $orderTips = $this->normalizeNotificationChannel(
                $payload['order_tips'] ?? ($item['order_tips'] ?? 'close'),
                'merchant order notification channel'
            );
            $moneyTipsChannel = $this->normalizeNotificationChannel(
                $payload['is_money_tips'] ?? ($item['low_balance_tips'] ?? 'close'),
                'merchant low-balance notification channel'
            );
            $moneyTips = $this->normalizeThreshold(
                $payload['money_tips'] ?? ($item['low_balance_threshold'] ?? '0'),
                'merchant low-balance threshold'
            );

            $this->assertNotificationChannelAvailable($orderTips, $config, 'merchant order notification channel');
            $this->assertNotificationChannelAvailable(
                $moneyTipsChannel,
                $config,
                'merchant low-balance notification channel'
            );
            $this->assertNotificationTargetReady($orderTips, $item, 'merchant order notification channel');
            $this->assertNotificationTargetReady(
                $moneyTipsChannel,
                $item,
                'merchant low-balance notification channel'
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table('ypay_userbasic')
            ->where('user_id', $id)
            ->update([
                'order_tips' => $orderTips,
                'is_money_tips' => $moneyTipsChannel,
                'money_tips' => $moneyTips,
            ]);

        $detail = $this->findUserDetail($id);
        if ($detail === null) {
            return ApiResponse::error('user not found', 404, null, 404);
        }

        return ApiResponse::success($detail, 'merchant notification settings updated');
    }

    public function emailAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'email');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $audit = $this->merchantEmailCampaign()->audit(RequestPayload::all($request));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success([
            'audit' => $this->merchantEmailCampaign()->publicAudit($audit),
        ]);
    }

    public function email(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'email');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);
        $service = $this->merchantEmailCampaign();

        try {
            $audit = $service->audit($payload);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        if (!$audit['can_send']) {
            return ApiResponse::error(
                'merchant email campaign cannot be sent',
                422,
                ['audit' => $service->publicAudit($audit)],
                422
            );
        }

        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error(
                'confirmation phrase mismatch',
                422,
                ['audit' => $service->publicAudit($audit)],
                422
            );
        }

        try {
            $result = $service->send($payload, $audit);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $this->recordAdminEmailCampaign($request, $result);

        return ApiResponse::success($result, 'merchant email campaign sent');
    }

    public function impersonationAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'adminLogin');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->userIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('user id is required', 422, null, 422);
        }

        $detail = $this->findUserDetail($id);
        if ($detail === null) {
            return ApiResponse::error('user not found', 404, null, 404);
        }

        try {
            $audit = $this->merchantImpersonation()->audit($id, $this->merchantImpersonationTargetUrl($request));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success([
            'item' => $detail['item'],
            'audit' => $this->merchantImpersonation()->publicAudit($audit),
        ]);
    }

    public function impersonate(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'adminLogin');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->userIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('user id is required', 422, null, 422);
        }

        $detail = $this->findUserDetail($id);
        if ($detail === null) {
            return ApiResponse::error('user not found', 404, null, 404);
        }

        $service = $this->merchantImpersonation();

        try {
            $audit = $service->audit($id, $this->merchantImpersonationTargetUrl($request));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        if (empty($audit['can_impersonate'])) {
            return ApiResponse::error(
                'merchant cannot be impersonated',
                422,
                ['audit' => $service->publicAudit($audit)],
                422
            );
        }

        try {
            $result = $service->issue(
                $audit,
                (array)($request->admin ?? []),
                $this->webmanPublicBaseUrl($request),
                $request
            );
        } catch (\Throwable $exception) {
            return ApiResponse::error($exception->getMessage(), 500, null, 500);
        }

        $this->recordAdminImpersonation($request, $result);

        return ApiResponse::success($result, 'merchant impersonation ready');
    }

    public function impersonationRedirect(Request $request): Response
    {
        $ticket = trim((string)($request->route ? $request->route->param('ticket', '') : ''));

        return $this->merchantImpersonation()->consume($ticket, $request);
    }

    public function batchDeleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $merchantIds = $this->normalizeMerchantIds($payload['merchant_ids'] ?? $payload['ids'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success([
            'audit' => $this->batchMerchantDeleteAudit($merchantIds),
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
            $merchantIds = $this->normalizeMerchantIds($payload['merchant_ids'] ?? $payload['ids'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $audit = $this->batchMerchantDeleteAudit($merchantIds);
        if (empty($audit['can_delete_all'])) {
            return ApiResponse::error(
                'selected merchants cannot be batch deleted until all blocking items are cleared',
                422,
                ['audit' => $audit],
                422
            );
        }

        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error(
                'confirmation phrase mismatch',
                422,
                ['audit' => $audit],
                422
            );
        }

        Db::transaction(function () use ($audit): void {
            foreach ((array)($audit['deletable_merchant_ids'] ?? []) as $merchantId) {
                $this->deleteMerchantOwnedRows((int)$merchantId);
            }
        });

        $this->recordAdminMerchantBatchDelete($request, $audit);

        return ApiResponse::success([
            'deleted_user_ids' => array_values(array_map('intval', (array)($audit['deletable_merchant_ids'] ?? []))),
            'deleted_count' => (int)($audit['summary']['deletable_count'] ?? 0),
            'audit' => $audit,
        ], 'merchant batch delete completed');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->userIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('user id is required', 422, null, 422);
        }

        $detail = $this->findUserDetail($id);
        if ($detail === null) {
            return ApiResponse::error('user not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => $detail['item'],
            'audit' => $this->merchantDeleteAudit($id),
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->userIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('user id is required', 422, null, 422);
        }

        $item = $this->findUserItem($id);
        if ($item === null) {
            return ApiResponse::error('user not found', 404, null, 404);
        }

        $audit = $this->merchantDeleteAudit($id);
        if (!empty($audit['blocking_reasons'])) {
            return ApiResponse::error(
                'merchant cannot be deleted until all blocking references are cleared',
                422,
                ['audit' => $audit],
                422
            );
        }

        $payload = RequestPayload::all($request);
        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)$audit['confirmation_phrase']) {
            return ApiResponse::error(
                'confirmation phrase mismatch',
                422,
                ['audit' => $audit],
                422
            );
        }

        Db::transaction(function () use ($id): void {
            $this->deleteMerchantOwnedRows($id);
        });

        return ApiResponse::success([
            'deleted_user_id' => $id,
            'deleted_username' => (string)($item['userName'] ?? ''),
            'audit' => $audit,
        ], 'merchant deleted');
    }

    private function merchantQuery(): Builder
    {
        return Db::table('ypay_user')
            ->leftJoin('ypay_userbasic', 'ypay_user.id', '=', 'ypay_userbasic.user_id')
            ->leftJoin('ypay_vip', 'ypay_user.vip_id', '=', 'ypay_vip.id')
            ->select(
                'ypay_user.id',
                'ypay_user.username',
                'ypay_user.superior_id',
                'ypay_user.email',
                'ypay_user.mobile',
                'ypay_user.wxpusher_uid',
                'ypay_user.tg_chat_id',
                'ypay_user.is_realName',
                'ypay_user.name',
                'ypay_user.money',
                'ypay_user.vip_id',
                'ypay_user.vip_time',
                'ypay_user.feilv',
                'ypay_user.create_time',
                'ypay_user.is_frozen',
                'ypay_user.frozen_reason',
                'ypay_user.remarks',
                'ypay_userbasic.appkey',
                'ypay_userbasic.loginfailure',
                'ypay_userbasic.timeout_time',
                'ypay_userbasic.is_rate',
                'ypay_userbasic.order_tips',
                'ypay_userbasic.is_money_tips',
                'ypay_userbasic.money_tips',
                'ypay_vip.name as vip_name'
            );
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', $request->get('userName', '')));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword) {
                $builder
                    ->where('ypay_user.username', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_user.name', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_user.email', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_user.mobile', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_user.remarks', 'like', '%' . $keyword . '%')
                    ->orWhere('ypay_userbasic.appkey', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder
                        ->orWhere('ypay_user.id', (int)$keyword)
                        ->orWhere('ypay_user.superior_id', (int)$keyword);
                }
            });
        }

        $mobile = trim((string)$request->get('userPhone', $request->get('mobile', '')));
        if ($mobile !== '') {
            $query->where('ypay_user.mobile', 'like', '%' . $mobile . '%');
        }

        $email = trim((string)$request->get('userEmail', $request->get('email', '')));
        if ($email !== '') {
            $query->where('ypay_user.email', 'like', '%' . $email . '%');
        }

        $status = strtolower(trim((string)$request->get('status', '')));
        if ($status !== '') {
            if (in_array($status, ['1', 'active', 'normal'], true)) {
                $query->where('ypay_user.is_frozen', 0);
            }

            if (in_array($status, ['2', '4', 'frozen', 'disabled'], true)) {
                $query->where('ypay_user.is_frozen', 1);
            }
        }

        $realNameStatus = strtolower(trim((string)$request->get('realname_status', $request->get('real_name_status', ''))));
        if ($realNameStatus === '') {
            $realNameStatus = strtolower(trim((string)$request->get('userGender', '')));
        }

        if (in_array($realNameStatus, ['1', 'verified', 'yes', 'realname'], true)) {
            $query->where('ypay_user.is_realName', 1);
        }

        if (in_array($realNameStatus, ['0', '2', 'unverified', 'no'], true)) {
            $query->where(function (Builder $builder) {
                $builder
                    ->whereNull('ypay_user.is_realName')
                    ->orWhere('ypay_user.is_realName', 0);
            });
        }

        $vipStatus = strtolower(trim((string)$request->get('vip_status', '')));
        if ($vipStatus !== '') {
            $now = date('Y-m-d H:i:s');
            if (in_array($vipStatus, ['1', 'vip', 'member'], true)) {
                $query
                    ->where('ypay_user.vip_id', '>', 0)
                    ->where(function (Builder $builder) use ($now) {
                        $builder
                            ->whereNull('ypay_user.vip_time')
                            ->orWhere('ypay_user.vip_time', '>=', $now);
                    });
            }

            if (in_array($vipStatus, ['0', '2', 'normal'], true)) {
                $query->where(function (Builder $builder) use ($now) {
                    $builder
                        ->whereNull('ypay_user.vip_id')
                        ->orWhere('ypay_user.vip_id', 0)
                        ->orWhere('ypay_user.vip_time', '<', $now);
                });
            }
        }
    }

    private function userIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function loadOrderStats(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $todayStart = date('Y-m-d 00:00:00');
        $rows = Db::table('ypay_order')
            ->select('user_id')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as paid_order_count')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN truemoney ELSE 0 END) as paid_amount')
            ->selectRaw('SUM(CASE WHEN status = 1 AND create_time >= ? THEN truemoney ELSE 0 END) as today_paid_amount', [$todayStart])
            ->selectRaw('MAX(create_time) as last_order_time')
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id')
            ->get()
            ->toArray();

        $stats = [];
        foreach ($rows as $row) {
            $record = (array)$row;
            $stats[(int)$record['user_id']] = $record;
        }

        return $stats;
    }

    private function findUserItem(int $id): ?array
    {
        $row = $this->merchantQuery()
            ->where('ypay_user.id', $id)
            ->first();

        if (!$row) {
            return null;
        }

        $statsByUserId = $this->loadOrderStats([$id]);

        return AdminUserFormatter::formatUser((array)$row, $statsByUserId[$id] ?? []);
    }

    private function findUserDetail(int $id): ?array
    {
        $item = $this->findUserItem($id);
        if ($item === null) {
            return null;
        }

        $vipId = (int)($item['vip_id'] ?? 0);

        return [
            'item' => $item,
            'editable' => [
                'email' => (string)($item['email'] ?? ''),
                'mobile' => (string)($item['mobile'] ?? ''),
                'remarks' => (string)($item['remarks'] ?? ''),
                'status' => !empty($item['is_frozen']) ? 1 : 0,
                'frozen_reason' => (string)($item['frozen_reason'] ?? ''),
                'vip_id' => $vipId > 0 ? $vipId : 0,
                'vip_time' => (string)($item['vip_expire_time'] ?? ''),
                'fee_rate' => isset($item['fee_rate']) && $item['fee_rate'] !== null ? (string)$item['fee_rate'] : '',
                'is_rate' => !empty($item['is_rate']) ? 1 : 0,
                'vip_options' => $this->loadVipOptions($vipId),
                'order_tips' => (string)($item['order_tips'] ?? 'close'),
                'is_money_tips' => (string)($item['low_balance_tips'] ?? 'close'),
                'money_tips' => (string)($item['low_balance_threshold'] ?? '0'),
                'notification_channel_options' => $this->notificationChannelOptions($item),
            ],
        ];
    }

    private function createEditablePayload(): array
    {
        return [
            'username' => '',
            'password' => '',
            'email' => '',
            'mobile' => '',
            'remarks' => '',
            'vip_id' => 0,
            'vip_time' => '',
            'fee_rate' => '',
            'is_rate' => 0,
            'vip_options' => $this->loadVipOptions(),
        ];
    }

    private function baseUserRecord(int $id): ?array
    {
        $row = Db::table('ypay_user')
            ->select('id', 'vip_id', 'vip_time', 'feilv')
            ->where('id', $id)
            ->first();

        return $row ? (array)$row : null;
    }

    private function userBasicRecord(int $id): ?array
    {
        $row = Db::table('ypay_userbasic')
            ->select('user_id', 'is_rate', 'order_tips', 'is_money_tips', 'money_tips')
            ->where('user_id', $id)
            ->first();

        return $row ? (array)$row : null;
    }

    private function ensureUserBasicRecord(int $id): void
    {
        $exists = Db::table('ypay_userbasic')->where('user_id', $id)->exists();
        if ($exists) {
            return;
        }

        Db::table('ypay_userbasic')->insert([
            'user_id' => $id,
            'timeout_method' => 2,
            'timeout_url' => '/',
            'timeout_time' => '180',
            'loginfailure' => 0,
            'appkey' => substr(bin2hex(random_bytes(16)), 0, 32),
            'order_tips' => 'close',
            'is_money_tips' => 'close',
            'money_tips' => '0',
            'is_rate' => 0,
            'callback_hiddenName' => 0,
        ]);
    }

    private function loadVipOptions(int $currentVipId = 0): array
    {
        $rows = Db::table('ypay_vip')
            ->select('id', 'name', 'feilv', 'viptime', 'status', 'sort')
            ->orderByRaw('CAST(COALESCE(sort, 0) AS UNSIGNED) asc')
            ->orderBy('id')
            ->get()
            ->toArray();

        $options = [[
            'value' => 0,
            'label' => 'No VIP package',
            'fee_rate' => '',
            'vip_days' => 0,
            'status' => 1,
            'disabled' => false,
        ]];

        foreach ($rows as $row) {
            $record = (array)$row;
            $id = (int)($record['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $name = trim((string)($record['name'] ?? ''));
            $feeRate = trim((string)($record['feilv'] ?? ''));
            $vipDays = (int)($record['viptime'] ?? 0);
            $status = (int)($record['status'] ?? 0);
            $segments = [$name !== '' ? $name : ('VIP #' . $id)];

            if ($vipDays > 0) {
                $segments[] = $vipDays . ' days';
            }

            if ($feeRate !== '') {
                $segments[] = $feeRate . '%';
            }

            if ($status !== 1) {
                $segments[] = 'disabled';
            }

            $options[] = [
                'value' => $id,
                'label' => implode(' / ', $segments),
                'fee_rate' => $feeRate,
                'vip_days' => $vipDays,
                'status' => $status,
                'disabled' => $status !== 1 && $id !== $currentVipId,
            ];
        }

        return $options;
    }

    private function merchantDeleteAudit(int $id): array
    {
        $merchant = $this->merchantIdentity($id);
        $subordinateCount = (int)Db::table('ypay_user')->where('superior_id', $id)->count();
        $relatedCounts = [[
            'key' => 'subordinate_merchants',
            'label' => 'Subordinate merchants',
            'table_name' => 'ypay_user',
            'column_name' => 'superior_id',
            'count' => $subordinateCount,
            'delete_action' => 'block',
            'help_text' => 'Reassign or detach subordinate merchants before deleting this merchant.',
        ]];

        $deleteRowCount = 0;
        $nonEmptyTargetCount = 0;

        foreach ($this->merchantDeletionTargets() as $target) {
            $count = (int)Db::table($target['table'])->where($target['column'], $id)->count();
            if ($count > 0) {
                $deleteRowCount += $count;
                $nonEmptyTargetCount++;
            }

            $relatedCounts[] = [
                'key' => $target['key'],
                'label' => $target['label'],
                'table_name' => $target['table'],
                'column_name' => $target['column'],
                'count' => $count,
                'delete_action' => 'delete',
                'help_text' => $target['help_text'],
            ];
        }

        $blockingReasons = [];
        if ($subordinateCount > 0) {
            $blockingReasons[] = sprintf(
                'This merchant still has %d subordinate merchant(s) linked by superior_id.',
                $subordinateCount
            );
        }

        return [
            'merchant_id' => $id,
            'merchant_username' => (string)($merchant['username'] ?? ''),
            'confirmation_phrase' => $this->merchantDeleteConfirmationPhrase($id),
            'can_delete' => $blockingReasons === [],
            'blocking_reasons' => $blockingReasons,
            'related_counts' => $relatedCounts,
            'summary' => [
                'delete_row_count' => $deleteRowCount,
                'non_empty_target_count' => $nonEmptyTargetCount,
                'blocking_reference_count' => $subordinateCount,
            ],
            'warnings' => [
                'Deleting a merchant permanently removes merchant-owned records from the audited tables below.',
                'Recharge records, order history, money logs, tickets, risk records, and upstream channels are all included in this destructive cleanup.',
            ],
        ];
    }

    private function merchantDeletionTargets(): array
    {
        return [
            [
                'key' => 'userbasic',
                'label' => 'Merchant basic settings',
                'table' => 'ypay_userbasic',
                'column' => 'user_id',
                'help_text' => 'Deletes appkey, timeout, notification, and fee-bearer settings.',
            ],
            [
                'key' => 'payment_accounts',
                'label' => 'Merchant local payment accounts',
                'table' => 'ypay_account',
                'column' => 'user_id',
                'help_text' => 'Deletes locally managed payment account records owned by the merchant.',
            ],
            [
                'key' => 'merchant_paylists',
                'label' => 'Merchant upstream channels',
                'table' => 'ypay_paylist',
                'column' => 'user_id',
                'help_text' => 'Deletes merchant-scoped upstream channel credentials and metadata.',
            ],
            [
                'key' => 'payment_pools',
                'label' => 'Merchant payment pools',
                'table' => 'ypay_poll_pool',
                'column' => 'user_id',
                'help_text' => 'Deletes merchant poll-pool definitions.',
            ],
            [
                'key' => 'payment_pool_items',
                'label' => 'Merchant payment pool items',
                'table' => 'ypay_poll_pool_item',
                'column' => 'user_id',
                'help_text' => 'Deletes merchant poll-pool channel selections.',
            ],
            [
                'key' => 'orders',
                'label' => 'Merchant orders',
                'table' => 'ypay_order',
                'column' => 'user_id',
                'help_text' => 'Deletes local order records for this merchant.',
            ],
            [
                'key' => 'recharges',
                'label' => 'Merchant recharge records',
                'table' => 'ypay_recharge',
                'column' => 'user_id',
                'help_text' => 'Deletes merchant recharge and paid registration records.',
            ],
            [
                'key' => 'money_logs',
                'label' => 'Merchant money logs',
                'table' => 'money_log',
                'column' => 'user_id',
                'help_text' => 'Deletes merchant balance change logs.',
            ],
            [
                'key' => 'front_logs',
                'label' => 'Merchant front logs',
                'table' => 'admin_front_log',
                'column' => 'uid',
                'help_text' => 'Deletes merchant front-operation log rows.',
            ],
            [
                'key' => 'domains',
                'label' => 'Merchant domains',
                'table' => 'ypay_domain',
                'column' => 'user_id',
                'help_text' => 'Deletes merchant domain submissions and audit results.',
            ],
            [
                'key' => 'risks',
                'label' => 'Merchant risk records',
                'table' => 'ypay_risk',
                'column' => 'user_id',
                'help_text' => 'Deletes merchant risk-control records.',
            ],
            [
                'key' => 'tickets',
                'label' => 'Merchant tickets',
                'table' => 'ypay_ticket',
                'column' => 'creator_id',
                'help_text' => 'Deletes merchant support tickets created by this merchant.',
            ],
            [
                'key' => 'merchant_record',
                'label' => 'Merchant account',
                'table' => 'ypay_user',
                'column' => 'id',
                'help_text' => 'Deletes the merchant account record itself.',
            ],
        ];
    }

    private function batchMerchantDeleteAudit(array $merchantIds): array
    {
        $items = [];
        $deletableMerchantIds = [];
        $blockedMerchantIds = [];
        $missingMerchantIds = [];
        $deleteRowCount = 0;
        $nonEmptyTargetCount = 0;
        $blockingReferenceCount = 0;

        foreach ($merchantIds as $merchantId) {
            $identity = $this->merchantIdentity($merchantId);
            if ($identity === null) {
                $missingMerchantIds[] = $merchantId;
                $items[] = [
                    'merchant_id' => $merchantId,
                    'merchant_username' => '',
                    'exists' => false,
                    'can_delete' => false,
                    'blocking_reasons' => ['This merchant was not found in ypay_user.'],
                    'summary' => [
                        'delete_row_count' => 0,
                        'non_empty_target_count' => 0,
                        'blocking_reference_count' => 0,
                    ],
                    'warnings' => ['Remove missing merchants from the selection before retrying the batch delete.'],
                    'related_counts' => [],
                ];
                continue;
            }

            $audit = $this->merchantDeleteAudit($merchantId);
            $items[] = [
                'merchant_id' => $merchantId,
                'merchant_username' => (string)($audit['merchant_username'] ?? ''),
                'exists' => true,
                'can_delete' => !empty($audit['can_delete']),
                'blocking_reasons' => array_values(array_map('strval', (array)($audit['blocking_reasons'] ?? []))),
                'summary' => (array)($audit['summary'] ?? []),
                'warnings' => array_values(array_map('strval', (array)($audit['warnings'] ?? []))),
                'related_counts' => array_values((array)($audit['related_counts'] ?? [])),
            ];

            $deleteRowCount += (int)($audit['summary']['delete_row_count'] ?? 0);
            $nonEmptyTargetCount += (int)($audit['summary']['non_empty_target_count'] ?? 0);
            $blockingReferenceCount += (int)($audit['summary']['blocking_reference_count'] ?? 0);

            if (!empty($audit['can_delete'])) {
                $deletableMerchantIds[] = $merchantId;
                continue;
            }

            $blockedMerchantIds[] = $merchantId;
        }

        $warnings = [];
        if ($missingMerchantIds !== []) {
            $warnings[] = 'Some selected merchants no longer exist and must be removed from the batch selection.';
        }
        if ($blockedMerchantIds !== []) {
            $warnings[] = 'At least one selected merchant still has blocking references, so the batch delete is paused until those merchants are cleared.';
        }
        if ($deletableMerchantIds !== []) {
            $warnings[] = 'Batch delete reuses the same destructive cleanup scope as the single-merchant delete flow.';
        }

        return [
            'requested_merchant_ids' => $merchantIds,
            'deletable_merchant_ids' => $deletableMerchantIds,
            'blocked_merchant_ids' => $blockedMerchantIds,
            'missing_merchant_ids' => $missingMerchantIds,
            'confirmation_phrase' => $this->batchMerchantDeleteConfirmationPhrase($merchantIds),
            'can_delete_all' => $merchantIds !== [] && $blockedMerchantIds === [] && $missingMerchantIds === [],
            'items' => $items,
            'summary' => [
                'requested_count' => count($merchantIds),
                'existing_count' => count($merchantIds) - count($missingMerchantIds),
                'deletable_count' => count($deletableMerchantIds),
                'blocked_count' => count($blockedMerchantIds),
                'missing_count' => count($missingMerchantIds),
                'delete_row_count' => $deleteRowCount,
                'non_empty_target_count' => $nonEmptyTargetCount,
                'blocking_reference_count' => $blockingReferenceCount,
            ],
            'warnings' => $warnings,
        ];
    }

    private function merchantIdentity(int $id): ?array
    {
        $row = Db::table('ypay_user')
            ->select('id', 'username')
            ->where('id', $id)
            ->first();

        return $row ? (array)$row : null;
    }

    private function merchantDeleteConfirmationPhrase(int $id): string
    {
        return 'DELETE MERCHANT ' . $id;
    }

    private function batchMerchantDeleteConfirmationPhrase(array $merchantIds): string
    {
        return sprintf(
            'DELETE MERCHANT BATCH %d-%s',
            count($merchantIds),
            strtoupper(substr(md5(implode(',', $merchantIds)), 0, 6))
        );
    }

    private function deleteMerchantOwnedRows(int $id): void
    {
        foreach ($this->merchantDeletionTargets() as $target) {
            Db::table($target['table'])
                ->where($target['column'], $id)
                ->delete();
        }
    }

    private function merchantEmailCampaign(): MerchantEmailCampaignService
    {
        return new MerchantEmailCampaignService(new AdminSmtpMailer());
    }

    private function merchantImpersonation(): MerchantImpersonationService
    {
        return new MerchantImpersonationService();
    }

    private function normalizeMerchantIds(mixed $value, int $maxCount = 100): array
    {
        $items = [];

        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $items = preg_split('/\s*,\s*/', trim($value)) ?: [];
        } elseif (is_numeric($value)) {
            $items = [$value];
        }

        $ids = [];
        foreach ($items as $item) {
            if (is_bool($item) || is_array($item) || is_object($item)) {
                continue;
            }

            $normalized = trim((string)$item);
            if ($normalized === '' || !ctype_digit($normalized)) {
                continue;
            }

            $id = (int)$normalized;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        $merchantIds = array_values($ids);
        sort($merchantIds);

        if ($merchantIds === []) {
            throw new \InvalidArgumentException('merchant ids are required');
        }

        if (count($merchantIds) > $maxCount) {
            throw new \InvalidArgumentException('too many merchants were selected for one batch delete');
        }

        return $merchantIds;
    }

    private function notificationChannelOptions(array $item): array
    {
        $config = SystemConfig::all();

        return [
            [
                'value' => 'close',
                'label' => 'Disabled',
                'enabled' => true,
                'target_ready' => true,
                'requires' => null,
                'help_text' => 'Turn the merchant notification off for this event.',
            ],
            [
                'value' => 'email',
                'label' => 'Email',
                'enabled' => (string)($config['email_switch'] ?? '0') === '1',
                'target_ready' => trim((string)($item['email'] ?? '')) !== '',
                'requires' => 'merchant email',
                'help_text' => 'Uses the merchant email address already saved on the profile.',
            ],
            [
                'value' => 'wxpusher',
                'label' => 'WxPusher',
                'enabled' => (string)($config['wxpusher_switch'] ?? '0') === '1',
                'target_ready' => !empty($item['wxpusher_uid_configured']),
                'requires' => 'merchant WxPusher UID',
                'help_text' => 'Requires the merchant to bind a WxPusher UID first.',
            ],
            [
                'value' => 'tg',
                'label' => 'Telegram',
                'enabled' => (string)($config['tg_switch'] ?? '0') === '1',
                'target_ready' => !empty($item['tg_chat_id_configured']),
                'requires' => 'merchant Telegram chat id',
                'help_text' => 'Requires the merchant to bind a Telegram chat id first.',
            ],
        ];
    }

    private function vipRecord(int $vipId): ?array
    {
        $row = Db::table('ypay_vip')
            ->select('id', 'name', 'feilv', 'viptime', 'status')
            ->where('id', $vipId)
            ->first();

        return $row ? (array)$row : null;
    }

    private function normalizeFrozenStatus(mixed $value): int
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
            '1', 'true', 'yes', 'on', 'freeze', 'frozen', 'disabled' => 1,
            '0', 'false', 'no', 'off', 'unfreeze', 'normal', 'active', 'enabled' => 0,
            default => throw new \InvalidArgumentException('merchant status must be 0 or 1'),
        };
    }

    private function normalizeVipId(mixed $value): int
    {
        if (is_bool($value) || is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('vip package is invalid');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            return 0;
        }

        if (!ctype_digit($normalized)) {
            throw new \InvalidArgumentException('vip package is invalid');
        }

        return (int)$normalized;
    }

    private function normalizeBinaryToggle(mixed $value, string $field): int
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
            '1', 'true', 'yes', 'on', 'enable', 'enabled', 'merchant' => 1,
            '0', 'false', 'no', 'off', 'disable', 'disabled', 'platform' => 0,
            default => throw new \InvalidArgumentException($field . ' must be 0 or 1'),
        };
    }

    private function normalizeOptionalDateTime(mixed $value, string $field): ?string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($field . ' must be a scalar');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $normalized);
            $errors = \DateTimeImmutable::getLastErrors();
            $warningCount = is_array($errors) ? (int)($errors['warning_count'] ?? 0) : 0;
            $errorCount = is_array($errors) ? (int)($errors['error_count'] ?? 0) : 0;
            if ($date !== false && $warningCount === 0 && $errorCount === 0) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        throw new \InvalidArgumentException($field . ' must be a valid datetime');
    }

    private function normalizeFeeRate(mixed $value, string $field, bool $required = true): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($field . ' must be a scalar');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            if ($required) {
                throw new \InvalidArgumentException($field . ' is required');
            }

            return '';
        }

        if (strlen($normalized) > 50 || !preg_match('/^\d+(?:\.\d+)?$/', $normalized)) {
            throw new \InvalidArgumentException($field . ' must be a non-negative number');
        }

        return $normalized;
    }

    private function normalizeNotificationChannel(mixed $value, string $field): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($field . ' must be a scalar');
        }

        $normalized = strtolower(trim((string)$value));
        if ($normalized === '' || $normalized === '0' || $normalized === 'off' || $normalized === 'disabled') {
            return 'close';
        }

        if (!in_array($normalized, ['close', 'email', 'wxpusher', 'tg'], true)) {
            throw new \InvalidArgumentException($field . ' is invalid');
        }

        return $normalized;
    }

    private function normalizeThreshold(mixed $value, string $field): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($field . ' must be a scalar');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            return '0';
        }

        if (strlen($normalized) > 50 || !preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw new \InvalidArgumentException($field . ' must be a non-negative amount with up to 2 decimals');
        }

        return $normalized;
    }

    private function assertNotificationChannelAvailable(string $channel, array $config, string $field): void
    {
        if ($channel === 'close') {
            return;
        }

        $enabled = match ($channel) {
            'email' => (string)($config['email_switch'] ?? '0') === '1',
            'wxpusher' => (string)($config['wxpusher_switch'] ?? '0') === '1',
            'tg' => (string)($config['tg_switch'] ?? '0') === '1',
            default => false,
        };

        if (!$enabled) {
            throw new \InvalidArgumentException($field . ' is disabled in system config');
        }
    }

    private function assertNotificationTargetReady(string $channel, array $item, string $field): void
    {
        if ($channel === 'close') {
            return;
        }

        $ready = match ($channel) {
            'email' => trim((string)($item['email'] ?? '')) !== '',
            'wxpusher' => !empty($item['wxpusher_uid_configured']),
            'tg' => !empty($item['tg_chat_id_configured']),
            default => false,
        };

        if (!$ready) {
            $message = match ($channel) {
                'email' => 'merchant email is required before enabling email notifications',
                'wxpusher' => 'merchant WxPusher UID is required before enabling WxPusher notifications',
                'tg' => 'merchant Telegram chat id is required before enabling Telegram notifications',
                default => $field . ' target is not ready',
            };

            throw new \InvalidArgumentException($message);
        }
    }

    private function normalizeEmail(mixed $value): ?string
    {
        $email = $this->normalizeNullableString($value, 50, 'merchant email');
        if ($email === null) {
            return null;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('merchant email format is invalid');
        }

        return $email;
    }

    private function normalizeCreatePayload(array $payload): array
    {
        $username = $this->normalizeRequiredString($payload['username'] ?? null, 50, 'merchant username');
        $password = $this->normalizeRequiredString($payload['password'] ?? null, 50, 'merchant password');
        $email = $this->normalizeEmail($payload['email'] ?? null);
        $mobile = $this->normalizeNullableString($payload['mobile'] ?? null, 50, 'merchant mobile');
        $remarks = $this->normalizeNullableString($payload['remarks'] ?? null, 255, 'merchant remarks');
        $vipId = $this->normalizeVipId($payload['vip_id'] ?? 0);
        $isRate = $this->normalizeBinaryToggle($payload['is_rate'] ?? 0, 'merchant fee bearer');

        if ($this->merchantFieldExists('username', $username)) {
            throw new \InvalidArgumentException('merchant username already exists');
        }

        if ($email !== null && $this->merchantFieldExists('email', $email)) {
            throw new \InvalidArgumentException('merchant email already exists');
        }

        if ($mobile !== null && $this->merchantFieldExists('mobile', $mobile)) {
            throw new \InvalidArgumentException('merchant mobile already exists');
        }

        $vipTime = null;
        $feeRate = null;
        if ($vipId > 0) {
            $vip = $this->vipRecord($vipId);
            if ($vip === null) {
                throw new \InvalidArgumentException('vip package was not found');
            }

            if ((int)($vip['status'] ?? 0) !== 1) {
                throw new \InvalidArgumentException('vip package is disabled and cannot be assigned');
            }

            $vipTime = $this->normalizeOptionalDateTime(
                $payload['vip_time'] ?? null,
                'merchant vip expire time'
            );

            if ($vipTime === null) {
                $vipDays = max(0, (int)($vip['viptime'] ?? 0));
                $vipTime = date('Y-m-d H:i:s', strtotime('+ ' . $vipDays . ' day'));
            }

            $feeRate = $this->normalizeFeeRate(
                $payload['fee_rate'] ?? $payload['feilv'] ?? '',
                'merchant fee rate',
                false
            );

            if ($feeRate === '') {
                $feeRate = $this->normalizeFeeRate($vip['feilv'] ?? '', 'merchant fee rate');
            }
        }

        return [
            'username' => htmlspecialchars($username, ENT_QUOTES),
            'password' => $password,
            'email' => $email,
            'mobile' => $mobile,
            'remarks' => $remarks,
            'vip_id' => $vipId,
            'vip_time' => $vipTime,
            'fee_rate' => $feeRate,
            'is_rate' => $isRate,
        ];
    }

    private function normalizeRequiredString(mixed $value, int $maxLength, string $fieldLabel): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($fieldLabel . ' must be a scalar');
        }

        $string = trim((string)$value);
        if ($string === '') {
            throw new \InvalidArgumentException($fieldLabel . ' is required');
        }

        if (mb_strlen($string) > $maxLength) {
            throw new \InvalidArgumentException($fieldLabel . ' is too long');
        }

        return $string;
    }

    private function merchantFieldExists(string $field, string $value): bool
    {
        return Db::table('ypay_user')
            ->where($field, $value)
            ->exists();
    }

    private function applyCustomMerchantAutoIncrement(): void
    {
        $config = SystemConfig::all();
        if ((string)($config['is_diyUserId'] ?? '0') !== '1') {
            return;
        }

        $target = max(0, (int)($config['diy_userId'] ?? 0));
        if ($target <= 0) {
            return;
        }

        $nextId = ((int)(Db::table('ypay_user')->max('id') ?? 0)) + 1;
        if ($nextId >= $target) {
            return;
        }

        Db::statement('ALTER TABLE ypay_user AUTO_INCREMENT = ' . $target);
    }

    private function generateSecret(int $length = 32): string
    {
        return substr(bin2hex(random_bytes(max(16, (int)ceil($length / 2)))), 0, $length);
    }

    private function normalizeNullableString(mixed $value, int $maxLength, string $fieldLabel): ?string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($fieldLabel . ' must be a scalar');
        }

        $string = trim((string)$value);
        if ($string === '') {
            return null;
        }

        if (mb_strlen($string) > $maxLength) {
            throw new \InvalidArgumentException($fieldLabel . ' is too long');
        }

        return $string;
    }

    private function recordAdminEmailCampaign(Request $request, array $result): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $scope = (string)(($result['audit'] ?? [])['scope'] ?? 'unknown');
        $summary = (array)($result['summary'] ?? []);
        $title = $this->truncateLogText((string)($result['title'] ?? ''), 80);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/users/email',
            'desc' => sprintf(
                'merchant email campaign [%s] title="%s" attempted=%d sent=%d failed=%d skipped=%d',
                $scope,
                $title,
                (int)($summary['attempted_count'] ?? 0),
                (int)($summary['sent_count'] ?? 0),
                (int)($summary['failed_count'] ?? 0),
                (int)($summary['skipped_count'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminImpersonation(Request $request, array $result): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $merchantId = (int)($result['merchant_id'] ?? 0);
        $merchantUsername = $this->truncateLogText((string)($result['merchant_username'] ?? ''), 80);
        $warnings = (array)(($result['audit'] ?? [])['warnings'] ?? []);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/users/' . $merchantId . '/impersonate',
            'desc' => sprintf(
                'merchant impersonation merchant_id=%d username="%s" warning_count=%d target="%s"',
                $merchantId,
                $merchantUsername,
                count($warnings),
                $this->truncateLogText((string)($result['target_url'] ?? ''), 120)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminMerchantBatchDelete(Request $request, array $audit): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $merchantIds = implode(',', array_map('intval', (array)($audit['deletable_merchant_ids'] ?? [])));
        $merchantLabels = implode(',', array_map(
            static function (array $item): string {
                $username = trim((string)($item['merchant_username'] ?? ''));
                $merchantId = (int)($item['merchant_id'] ?? 0);

                return $username !== '' ? $username : ('#' . $merchantId);
            },
            array_values(array_filter(
                (array)($audit['items'] ?? []),
                static fn (array $item): bool => !empty($item['can_delete'])
            ))
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/users/batch-delete',
            'desc' => sprintf(
                'merchant batch delete requested=%d deleted=%d blocked=%d missing=%d delete_rows=%d merchants="%s" labels="%s"',
                (int)($summary['requested_count'] ?? 0),
                (int)($summary['deletable_count'] ?? 0),
                (int)($summary['blocked_count'] ?? 0),
                (int)($summary['missing_count'] ?? 0),
                (int)($summary['delete_row_count'] ?? 0),
                $this->truncateLogText($merchantIds, 255),
                $this->truncateLogText($merchantLabels, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function webmanPublicBaseUrl(Request $request): string
    {
        $scheme = $this->requestScheme($request);
        $host = trim((string)$request->header('host', '127.0.0.1:8787'));
        if ($host === '') {
            $host = '127.0.0.1:8787';
        }

        return $scheme . '://' . $host;
    }

    private function merchantImpersonationTargetUrl(Request $request): string
    {
        return $this->withHashPath($this->merchantFrontendBaseUrl($request), '/merchant/dashboard');
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'SystemUser', $authMark);
    }

    private function requestScheme(Request $request): string
    {
        $forwardedProto = trim((string)$request->header('x-forwarded-proto', ''));
        if ($forwardedProto !== '') {
            $parts = preg_split('/\s*,\s*/', strtolower($forwardedProto)) ?: [];
            $proto = trim((string)($parts[0] ?? ''));
            if ($proto !== '') {
                return $proto;
            }
        }

        if (in_array(strtolower(trim((string)$request->header('x-forwarded-ssl', ''))), ['on', '1'], true)) {
            return 'https';
        }

        if (in_array(strtolower(trim((string)$request->header('x-forwarded-scheme', ''))), ['http', 'https'], true)) {
            return strtolower(trim((string)$request->header('x-forwarded-scheme', '')));
        }

        return 'http';
    }

    private function truncateLogText(string $value, int $limit): string
    {
        $value = trim(str_replace(["\r", "\n"], ' ', $value));
        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, max(0, $limit - 3)) . '...';
    }
}
