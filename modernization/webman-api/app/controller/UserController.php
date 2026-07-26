<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\controller;

use app\controller\concerns\MerchantPortalUrlSupport;
use app\support\AdminRouteAuthorization;
use app\support\AdminSmtpMailer;
use app\support\AdminUserFormatter;
use app\support\ApiResponse;
use app\support\BusinessTable;
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

        $total = (int)(clone $query)->count('merchant.id');
        $rows = $query
            ->orderByDesc('merchant.id')
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
            return ApiResponse::error('缺少商户编号', 422, null, 422);
        }

        $detail = $this->findUserDetail($id);
        if ($detail === null) {
            return ApiResponse::error('商户不存在', 404, null, 404);
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

            $userId = (int)Db::table(BusinessTable::user())->insertGetId([
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

            Db::table(BusinessTable::userBasic())->insert([
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
            return ApiResponse::error('缺少商户编号', 422, null, 422);
        }

        if ($this->baseUserRecord($id) === null) {
            return ApiResponse::error('商户不存在', 404, null, 404);
        }

        $payload = RequestPayload::all($request);

        try {
            $email = $this->normalizeEmail($payload['email'] ?? null);
            $mobile = $this->normalizeNullableString($payload['mobile'] ?? null, 50, 'merchant mobile');
            $remarks = $this->normalizeNullableString($payload['remarks'] ?? null, 255, 'merchant remarks');
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table(BusinessTable::user())
            ->where('id', $id)
            ->update([
                'email' => $email,
                'mobile' => $mobile,
                'remarks' => $remarks,
            ]);

        $detail = $this->findUserDetail($id);
        if ($detail === null) {
            return ApiResponse::error('商户不存在', 404, null, 404);
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
            return ApiResponse::error('缺少商户编号', 422, null, 422);
        }

        if ($this->baseUserRecord($id) === null) {
            return ApiResponse::error('商户不存在', 404, null, 404);
        }

        $payload = RequestPayload::all($request);

        try {
            $status = $this->normalizeFrozenStatus($payload['status'] ?? null);
            $reason = $this->normalizeNullableString($payload['frozen_reason'] ?? null, 255, 'frozen reason');
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table(BusinessTable::user())
            ->where('id', $id)
            ->update([
                'is_frozen' => $status,
                'frozen_reason' => $status === 1 ? $reason : null,
            ]);

        return ApiResponse::success([
            'item' => $this->findUserItem($id),
        ], '商户状态已更新');
    }

    public function business(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->userIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少商户编号', 422, null, 422);
        }

        $record = $this->baseUserRecord($id);
        if ($record === null) {
            return ApiResponse::error('商户不存在', 404, null, 404);
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
                    throw new \InvalidArgumentException('会员套餐不存在');
                }

                if ((int)($vip['status'] ?? 0) !== 1 && $vipId !== $currentVipId) {
                    throw new \InvalidArgumentException('该会员套餐已停用，无法新分配');
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

        Db::table(BusinessTable::user())
            ->where('id', $id)
            ->update([
                'vip_id' => $vipId > 0 ? $vipId : null,
                'vip_time' => $vipId > 0 ? $vipTime : null,
                'feilv' => $vipId > 0 ? $feeRate : null,
            ]);

        Db::table(BusinessTable::userBasic())
            ->where('user_id', $id)
            ->update([
                'is_rate' => $isRate,
            ]);

        $detail = $this->findUserDetail($id);
        if ($detail === null) {
            return ApiResponse::error('商户不存在', 404, null, 404);
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
            return ApiResponse::error('缺少商户编号', 422, null, 422);
        }

        if ($this->baseUserRecord($id) === null) {
            return ApiResponse::error('商户不存在', 404, null, 404);
        }

        $this->ensureUserBasicRecord($id);

        $payload = RequestPayload::all($request);
        $item = $this->findUserItem($id);
        if ($item === null) {
            return ApiResponse::error('商户不存在', 404, null, 404);
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

        Db::table(BusinessTable::userBasic())
            ->where('user_id', $id)
            ->update([
                'order_tips' => $orderTips,
                'is_money_tips' => $moneyTipsChannel,
                'money_tips' => $moneyTips,
            ]);

        $detail = $this->findUserDetail($id);
        if ($detail === null) {
            return ApiResponse::error('商户不存在', 404, null, 404);
        }

        return ApiResponse::success($detail, '商户通知设置已更新');
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
                '当前商户邮件发送条件不满足，暂不能发送',
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

        return ApiResponse::success($result, '商户邮件已发送');
    }

    public function impersonationAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'adminLogin');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->userIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少商户编号', 422, null, 422);
        }

        $detail = $this->findUserDetail($id);
        if ($detail === null) {
            return ApiResponse::error('商户不存在', 404, null, 404);
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
            return ApiResponse::error('缺少商户编号', 422, null, 422);
        }

        $detail = $this->findUserDetail($id);
        if ($detail === null) {
            return ApiResponse::error('商户不存在', 404, null, 404);
        }

        $service = $this->merchantImpersonation();

        try {
            $audit = $service->audit($id, $this->merchantImpersonationTargetUrl($request));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        if (empty($audit['can_impersonate'])) {
            return ApiResponse::error(
                '当前商户暂不允许代登录',
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

        return ApiResponse::success($result, '商户代登录已就绪');
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
        ], '商户批量删除已完成');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->userIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('缺少商户编号', 422, null, 422);
        }

        $detail = $this->findUserDetail($id);
        if ($detail === null) {
            return ApiResponse::error('商户不存在', 404, null, 404);
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
            return ApiResponse::error('缺少商户编号', 422, null, 422);
        }

        $item = $this->findUserItem($id);
        if ($item === null) {
            return ApiResponse::error('商户不存在', 404, null, 404);
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
        ], '商户已删除');
    }

    private function merchantQuery(): Builder
    {
        return Db::table(BusinessTable::user('merchant'))
            ->leftJoin(BusinessTable::userBasic('basic'), 'merchant.id', '=', 'basic.user_id')
            ->leftJoin(BusinessTable::vip('vip'), 'merchant.vip_id', '=', 'vip.id')
            ->select(
                'merchant.id',
                'merchant.username',
                'merchant.superior_id',
                'merchant.email',
                'merchant.mobile',
                'merchant.wxpusher_uid',
                'merchant.tg_chat_id',
                'merchant.is_realName',
                'merchant.name',
                'merchant.money',
                'merchant.vip_id',
                'merchant.vip_time',
                'merchant.feilv',
                'merchant.create_time',
                'merchant.is_frozen',
                'merchant.frozen_reason',
                'merchant.remarks',
                'basic.appkey',
                'basic.loginfailure',
                'basic.timeout_time',
                'basic.is_rate',
                'basic.order_tips',
                'basic.is_money_tips',
                'basic.money_tips',
                'vip.name as vip_name'
            );
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', $request->get('userName', '')));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword) {
                $builder
                    ->where('merchant.username', 'like', '%' . $keyword . '%')
                    ->orWhere('merchant.name', 'like', '%' . $keyword . '%')
                    ->orWhere('merchant.email', 'like', '%' . $keyword . '%')
                    ->orWhere('merchant.mobile', 'like', '%' . $keyword . '%')
                    ->orWhere('merchant.remarks', 'like', '%' . $keyword . '%')
                    ->orWhere('basic.appkey', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder
                        ->orWhere('merchant.id', (int)$keyword)
                        ->orWhere('merchant.superior_id', (int)$keyword);
                }
            });
        }

        $mobile = trim((string)$request->get('userPhone', $request->get('mobile', '')));
        if ($mobile !== '') {
            $query->where('merchant.mobile', 'like', '%' . $mobile . '%');
        }

        $email = trim((string)$request->get('userEmail', $request->get('email', '')));
        if ($email !== '') {
            $query->where('merchant.email', 'like', '%' . $email . '%');
        }

        $status = strtolower(trim((string)$request->get('status', '')));
        if ($status !== '') {
            if (in_array($status, ['1', 'active', 'normal'], true)) {
                $query->where('merchant.is_frozen', 0);
            }

            if (in_array($status, ['2', '4', 'frozen', 'disabled'], true)) {
                $query->where('merchant.is_frozen', 1);
            }
        }

        $realNameStatus = strtolower(trim((string)$request->get('realname_status', $request->get('real_name_status', ''))));
        if ($realNameStatus === '') {
            $realNameStatus = strtolower(trim((string)$request->get('userGender', '')));
        }

        if (in_array($realNameStatus, ['1', 'verified', 'yes', 'realname'], true)) {
            $query->where('merchant.is_realName', 1);
        }

        if (in_array($realNameStatus, ['0', '2', 'unverified', 'no'], true)) {
            $query->where(function (Builder $builder) {
                $builder
                    ->whereNull('merchant.is_realName')
                    ->orWhere('merchant.is_realName', 0);
            });
        }

        $vipStatus = strtolower(trim((string)$request->get('vip_status', '')));
        if ($vipStatus !== '') {
            $now = date('Y-m-d H:i:s');
            if (in_array($vipStatus, ['1', 'vip', 'member'], true)) {
                $query
                    ->where('merchant.vip_id', '>', 0)
                    ->where(function (Builder $builder) use ($now) {
                        $builder
                            ->whereNull('merchant.vip_time')
                            ->orWhere('merchant.vip_time', '>=', $now);
                    });
            }

            if (in_array($vipStatus, ['0', '2', 'normal'], true)) {
                $query->where(function (Builder $builder) use ($now) {
                    $builder
                        ->whereNull('merchant.vip_id')
                        ->orWhere('merchant.vip_id', 0)
                        ->orWhere('merchant.vip_time', '<', $now);
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
        $rows = Db::table(BusinessTable::order())
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
            ->where('merchant.id', $id)
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
        $row = Db::table(BusinessTable::user())
            ->select('id', 'vip_id', 'vip_time', 'feilv')
            ->where('id', $id)
            ->first();

        return $row ? (array)$row : null;
    }

    private function userBasicRecord(int $id): ?array
    {
        $row = Db::table(BusinessTable::userBasic())
            ->select('user_id', 'is_rate', 'order_tips', 'is_money_tips', 'money_tips')
            ->where('user_id', $id)
            ->first();

        return $row ? (array)$row : null;
    }

    private function ensureUserBasicRecord(int $id): void
    {
        $exists = Db::table(BusinessTable::userBasic())->where('user_id', $id)->exists();
        if ($exists) {
            return;
        }

        Db::table(BusinessTable::userBasic())->insert([
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
        $rows = Db::table(BusinessTable::vip())
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
        $subordinateCount = (int)Db::table(BusinessTable::user())->where('superior_id', $id)->count();
        $relatedCounts = [[
            'key' => 'subordinate_merchants',
            'label' => 'Subordinate merchants',
            'table_name' => BusinessTable::user(),
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
                '当前商户仍通过 superior_id 关联了 %d 个下级商户，请先解除关联后再删除。',
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
                '删除商户会一并清理下方审计表中的商户自有数据。',
                '充值记录、订单历史、资金日志、工单、风控记录以及上游通道都会纳入本次清理范围。',
            ],
        ];
    }

    private function merchantDeletionTargets(): array
    {
        return [
            [
                'key' => 'userbasic',
                'label' => '商户基础设置',
                'table' => BusinessTable::userBasic(),
                'column' => 'user_id',
                'help_text' => '清理 appkey、超时、通知方式与手续费承担方等基础配置。',
            ],
            [
                'key' => 'payment_accounts',
                'label' => '商户本地支付账户',
                'table' => BusinessTable::account(),
                'column' => 'user_id',
                'help_text' => '清理商户名下的本地支付账户记录。',
            ],
            [
                'key' => 'merchant_paylists',
                'label' => '商户上游通道',
                'table' => BusinessTable::paylist(),
                'column' => 'user_id',
                'help_text' => '清理商户范围内的上游通道密钥与元数据。',
            ],
            [
                'key' => 'payment_pools',
                'label' => '商户轮询池',
                'table' => BusinessTable::pollPool(),
                'column' => 'user_id',
                'help_text' => '清理商户轮询池定义。',
            ],
            [
                'key' => 'payment_pool_items',
                'label' => '商户轮询池项',
                'table' => BusinessTable::pollPoolItem(),
                'column' => 'user_id',
                'help_text' => '清理商户轮询池内的通道选择记录。',
            ],
            [
                'key' => 'orders',
                'label' => '商户订单',
                'table' => BusinessTable::order(),
                'column' => 'user_id',
                'help_text' => '清理该商户的本地订单记录。',
            ],
            [
                'key' => 'recharges',
                'label' => '商户充值记录',
                'table' => BusinessTable::recharge(),
                'column' => 'user_id',
                'help_text' => '清理商户充值记录与付费注册记录。',
            ],
            [
                'key' => 'money_logs',
                'label' => '商户资金日志',
                'table' => 'money_log',
                'column' => 'user_id',
                'help_text' => '清理商户余额变动日志。',
            ],
            [
                'key' => 'front_logs',
                'label' => '商户前台日志',
                'table' => 'admin_front_log',
                'column' => 'uid',
                'help_text' => '清理商户前台操作日志。',
            ],
            [
                'key' => 'domains',
                'label' => '商户域名',
                'table' => BusinessTable::domain(),
                'column' => 'user_id',
                'help_text' => '清理商户域名提交记录与审核结果。',
            ],
            [
                'key' => 'risks',
                'label' => '商户风控记录',
                'table' => BusinessTable::risk(),
                'column' => 'user_id',
                'help_text' => '清理商户风控记录。',
            ],
            [
                'key' => 'tickets',
                'label' => '商户工单',
                'table' => BusinessTable::ticket(),
                'column' => 'creator_id',
                'help_text' => '清理该商户创建的客服工单。',
            ],
            [
                'key' => 'merchant_record',
                'label' => '商户账号',
                'table' => BusinessTable::user(),
                'column' => 'id',
                'help_text' => '清理商户账号本身的主记录。',
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
                    'blocking_reasons' => ['当前商户记录不存在。'],
                    'summary' => [
                        'delete_row_count' => 0,
                        'non_empty_target_count' => 0,
                        'blocking_reference_count' => 0,
                    ],
                    'warnings' => ['请先从选择列表中移除不存在的商户，再重新执行批量删除。'],
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
            $warnings[] = '所选商户中存在已失效记录，请先移出本次选择后再继续。';
        }
        if ($blockedMerchantIds !== []) {
            $warnings[] = '所选商户中仍存在阻塞引用，需先清理这些商户后才能继续批量删除。';
        }
        if ($deletableMerchantIds !== []) {
            $warnings[] = '批量删除沿用单个商户删除的同一清理范围，请确认后再执行。';
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
        $row = Db::table(BusinessTable::user())
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
                'label' => '关闭',
                'enabled' => true,
                'target_ready' => true,
                'requires' => null,
                'help_text' => '关闭该项通知。',
            ],
            [
                'value' => 'email',
                'label' => '邮箱',
                'enabled' => (string)($config['email_switch'] ?? '0') === '1',
                'target_ready' => trim((string)($item['email'] ?? '')) !== '',
                'requires' => '商户邮箱',
                'help_text' => '使用商户资料中已保存的邮箱地址。',
            ],
            [
                'value' => 'wxpusher',
                'label' => '微信推送',
                'enabled' => (string)($config['wxpusher_switch'] ?? '0') === '1',
                'target_ready' => !empty($item['wxpusher_uid_configured']),
                'requires' => '商户微信推送标识',
                'help_text' => '需要商户先绑定微信推送标识。',
            ],
            [
                'value' => 'tg',
                'label' => '电报',
                'enabled' => (string)($config['tg_switch'] ?? '0') === '1',
                'target_ready' => !empty($item['tg_chat_id_configured']),
                'requires' => '商户电报会话标识',
                'help_text' => '需要商户先绑定电报会话标识。',
            ],
        ];
    }

    private function vipRecord(int $vipId): ?array
    {
        $row = Db::table(BusinessTable::vip())
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
            default => throw new \InvalidArgumentException('商户状态只能是正常或冻结'),
        };
    }

    private function normalizeVipId(mixed $value): int
    {
        if (is_bool($value) || is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('会员套餐无效');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            return 0;
        }

        if (!ctype_digit($normalized)) {
            throw new \InvalidArgumentException('会员套餐无效');
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
            default => throw new \InvalidArgumentException(
                $field === 'merchant fee bearer'
                    ? '手续费承担方只能是平台或商户'
                    : $this->fieldLabel($field) . '只能是开启或关闭'
            ),
        };
    }

    private function normalizeOptionalDateTime(mixed $value, string $field): ?string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '格式不正确');
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

        throw new \InvalidArgumentException($this->fieldLabel($field) . '必须是有效的日期时间');
    }

    private function normalizeFeeRate(mixed $value, string $field, bool $required = true): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '格式不正确');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            if ($required) {
                throw new \InvalidArgumentException($this->fieldLabel($field) . '不能为空');
            }

            return '';
        }

        if (strlen($normalized) > 50 || !preg_match('/^\d+(?:\.\d+)?$/', $normalized)) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '必须是非负数字');
        }

        return $normalized;
    }

    private function normalizeNotificationChannel(mixed $value, string $field): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '格式不正确');
        }

        $normalized = strtolower(trim((string)$value));
        if ($normalized === '' || $normalized === '0' || $normalized === 'off' || $normalized === 'disabled') {
            return 'close';
        }

        if (!in_array($normalized, ['close', 'email', 'wxpusher', 'tg'], true)) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '无效');
        }

        return $normalized;
    }

    private function normalizeThreshold(mixed $value, string $field): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '格式不正确');
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            return '0';
        }

        if (strlen($normalized) > 50 || !preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw new \InvalidArgumentException($this->fieldLabel($field) . '必须是最多两位小数的非负金额');
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
            throw new \InvalidArgumentException($this->fieldLabel($field) . '未在系统配置中开启');
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
                'email' => '启用邮箱通知前，商户邮箱不能为空',
                'wxpusher' => '启用微信推送通知前，商户需先绑定微信推送标识',
                'tg' => '启用电报通知前，商户需先绑定电报会话标识',
                default => $this->fieldLabel($field) . '接收目标未就绪',
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
            throw new \InvalidArgumentException('商户邮箱格式不正确');
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
            throw new \InvalidArgumentException('商户账号已存在');
        }

        if ($email !== null && $this->merchantFieldExists('email', $email)) {
            throw new \InvalidArgumentException('商户邮箱已存在');
        }

        if ($mobile !== null && $this->merchantFieldExists('mobile', $mobile)) {
            throw new \InvalidArgumentException('商户手机号已存在');
        }

        $vipTime = null;
        $feeRate = null;
        if ($vipId > 0) {
            $vip = $this->vipRecord($vipId);
            if ($vip === null) {
                throw new \InvalidArgumentException('会员套餐不存在');
            }

            if ((int)($vip['status'] ?? 0) !== 1) {
                throw new \InvalidArgumentException('该会员套餐已停用，无法分配');
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
            throw new \InvalidArgumentException($this->fieldLabel($fieldLabel) . '格式不正确');
        }

        $string = trim((string)$value);
        if ($string === '') {
            throw new \InvalidArgumentException($this->fieldLabel($fieldLabel) . '不能为空');
        }

        if (mb_strlen($string) > $maxLength) {
            throw new \InvalidArgumentException($this->fieldLabel($fieldLabel) . '长度不能超过 ' . $maxLength . ' 个字符');
        }

        return $string;
    }

    private function merchantFieldExists(string $field, string $value): bool
    {
        return Db::table(BusinessTable::user())
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

        $nextId = ((int)(Db::table(BusinessTable::user())->max('id') ?? 0)) + 1;
        if ($nextId >= $target) {
            return;
        }

        Db::statement('ALTER TABLE ' . BusinessTable::user() . ' AUTO_INCREMENT = ' . $target);
    }

    private function generateSecret(int $length = 32): string
    {
        return substr(bin2hex(random_bytes(max(16, (int)ceil($length / 2)))), 0, $length);
    }

    private function normalizeNullableString(mixed $value, int $maxLength, string $fieldLabel): ?string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException($this->fieldLabel($fieldLabel) . '格式不正确');
        }

        $string = trim((string)$value);
        if ($string === '') {
            return null;
        }

        if (mb_strlen($string) > $maxLength) {
            throw new \InvalidArgumentException($this->fieldLabel($fieldLabel) . '长度不能超过 ' . $maxLength . ' 个字符');
        }

        return $string;
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'merchant username' => '商户账号',
            'merchant password' => '商户密码',
            'merchant email' => '商户邮箱',
            'merchant mobile' => '商户手机号',
            'merchant remarks' => '商户备注',
            'frozen reason' => '冻结原因',
            'merchant fee bearer' => '手续费承担方',
            'merchant vip expire time' => '商户会员到期时间',
            'merchant fee rate' => '商户费率',
            'merchant order notification channel' => '订单通知方式',
            'merchant low-balance notification channel' => '低余额通知方式',
            'merchant low-balance threshold' => '低余额提醒阈值',
            'merchant wxpusher uid' => '商户微信推送标识',
            'merchant telegram chat id' => '商户电报会话标识',
            default => $field,
        };
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
                '商户邮件发送 [%s] 标题="%s" 尝试=%d 已发=%d 失败=%d 跳过=%d',
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
                '商户代登录 merchant_id=%d username="%s" warning_count=%d target="%s"',
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
                '商户批量删除 requested=%d deleted=%d blocked=%d missing=%d delete_rows=%d merchants="%s" labels="%s"',
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
