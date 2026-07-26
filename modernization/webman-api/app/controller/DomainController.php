<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\controller;

use app\support\AdminRouteAuthorization;
use app\support\AdminSmtpMailer;
use app\support\AdminDomainFormatter;
use app\support\ApiResponse;
use app\support\BusinessTable;
use app\support\FrontendUrlBuilder;
use app\support\RequestPayload;
use app\support\SystemConfig;
use Illuminate\Database\Query\Builder;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class DomainController
{
    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $summaryQuery = $this->domainQuery();
        $this->applyBaseFilters($summaryQuery, $request);

        $summary = $this->summary(clone $summaryQuery);

        $query = clone $summaryQuery;
        $this->applyStatusFilter($query, $request);

        $total = (int)(clone $query)->count('domain.id');
        $rows = $query
            ->orderByDesc('domain.id')
            ->offset(($current - 1) * $size)
            ->limit($size)
            ->get()
            ->toArray();

        $records = array_map(
            static fn($row): array => AdminDomainFormatter::format((array)$row),
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
        $id = $this->domainIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('domain id is required', 422, null, 422);
        }

        $row = $this->loadDomainRow($id);
        if ($row === null) {
            return ApiResponse::error('domain not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminDomainFormatter::format($row),
        ]);
    }

    public function create(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'add');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $payload = $this->normalizeWritePayload(RequestPayload::all($request));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $domainId = Db::transaction(function () use ($payload): int {
            return (int)Db::table(BusinessTable::domain())->insertGetId([
                'user_id' => $payload['user_id'],
                'sitename' => $payload['sitename'],
                'siteurl' => $payload['siteurl'],
                'status' => $payload['status'],
                'reason' => $payload['reason'],
                'create_time' => date('Y-m-d H:i:s'),
                'delete_time' => null,
            ]);
        });

        $created = $this->loadDomainRow($domainId);
        if ($created === null) {
            return ApiResponse::error('created domain could not be loaded', 500, null, 500);
        }

        $this->recordAdminDomainCreate($request, $created, $payload);

        return ApiResponse::success([
            'item' => AdminDomainFormatter::format($created),
            'created_domain_id' => $domainId,
            'created_domain_label' => $this->domainLabel($created),
        ], 'domain created');
    }

    public function update(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'edit');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->domainIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('domain id is required', 422, null, 422);
        }

        $record = $this->loadDomainRow($id);
        if ($record === null) {
            return ApiResponse::error('domain not found', 404, null, 404);
        }

        if (!empty($record['delete_time'])) {
            return ApiResponse::error('recycled domain must be restored before editing', 422, null, 422);
        }

        try {
            $payload = $this->normalizeWritePayload(RequestPayload::all($request), $record);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        Db::table(BusinessTable::domain())
            ->where('id', $id)
            ->update([
                'user_id' => $payload['user_id'],
                'sitename' => $payload['sitename'],
                'siteurl' => $payload['siteurl'],
                'status' => $payload['status'],
                'reason' => $payload['reason'],
            ]);

        $updated = $this->loadDomainRow($id);
        if ($updated === null) {
            return ApiResponse::error('updated domain could not be loaded', 500, null, 500);
        }

        $this->recordAdminDomainUpdate($request, $record, $updated, $payload);

        return ApiResponse::success([
            'item' => AdminDomainFormatter::format($updated),
            'updated_domain_id' => $id,
            'updated_domain_label' => $this->domainLabel($updated),
        ], 'domain updated');
    }

    public function restore(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'recycle');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->domainIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('domain id is required', 422, null, 422);
        }

        $record = $this->loadDomainRow($id);
        if ($record === null) {
            return ApiResponse::error('domain not found', 404, null, 404);
        }

        if (empty($record['delete_time'])) {
            return ApiResponse::error('domain is already active', 422, null, 422);
        }

        try {
            $this->restoreDomainRow($id);
        } catch (\Throwable $exception) {
            return ApiResponse::error('domain restore failed', 500, null, 500);
        }

        $restored = $this->loadDomainRow($id);
        if ($restored === null) {
            return ApiResponse::error('restored domain could not be loaded', 500, null, 500);
        }

        $this->recordAdminDomainRestore($request, $record);

        return ApiResponse::success([
            'item' => AdminDomainFormatter::format($restored),
            'restored_domain_id' => $id,
            'restored_domain_label' => $this->domainLabel($record),
        ], 'domain restored');
    }

    public function batchRestore(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'recycle');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $domainIds = $this->normalizeDomainIds($request->post('domain_ids', []));
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        if ($domainIds === []) {
            return ApiResponse::error('at least one domain id is required', 422, null, 422);
        }

        $rows = $this->loadDomainRowsByIds($domainIds);
        if ($rows === []) {
            return ApiResponse::error('no domains matched the restore request', 422, [
                'restored_domain_ids' => [],
                'already_active_domain_ids' => [],
                'missing_domain_ids' => $domainIds,
            ], 422);
        }

        $rowMap = [];
        foreach ($rows as $row) {
            $rowMap[(int)($row['id'] ?? 0)] = $row;
        }

        $restorableRows = [];
        $alreadyActiveDomainIds = [];
        $matchedDomainIds = [];

        foreach ($domainIds as $domainId) {
            $row = $rowMap[$domainId] ?? null;
            if ($row === null) {
                continue;
            }

            $matchedDomainIds[] = $domainId;

            if (empty($row['delete_time'])) {
                $alreadyActiveDomainIds[] = $domainId;
                continue;
            }

            $restorableRows[] = $row;
        }

        $missingDomainIds = array_values(array_diff($domainIds, $matchedDomainIds));

        if ($restorableRows === []) {
            return ApiResponse::error('no recycled domains matched the restore request', 422, [
                'restored_domain_ids' => [],
                'already_active_domain_ids' => $alreadyActiveDomainIds,
                'missing_domain_ids' => $missingDomainIds,
            ], 422);
        }

        Db::transaction(function () use ($restorableRows): void {
            foreach ($restorableRows as $row) {
                $this->restoreDomainRow((int)($row['id'] ?? 0));
            }
        });

        $restoredDomainIds = array_values(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $restorableRows
        ));

        $this->recordAdminDomainBatchRestore(
            $request,
            $restorableRows,
            $domainIds,
            $alreadyActiveDomainIds,
            $missingDomainIds
        );

        return ApiResponse::success([
            'restored_domain_ids' => $restoredDomainIds,
            'restored_count' => count($restorableRows),
            'already_active_domain_ids' => $alreadyActiveDomainIds,
            'missing_domain_ids' => $missingDomainIds,
        ], 'domains restored');
    }

    public function deleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->domainIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('domain id is required', 422, null, 422);
        }

        $record = $this->loadDomainRow($id);
        if ($record === null) {
            return ApiResponse::error('domain not found', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => AdminDomainFormatter::format($record),
            'audit' => $this->buildDomainDeleteAudit($record),
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->domainIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('domain id is required', 422, null, 422);
        }

        $record = $this->loadDomainRow($id);
        if ($record === null) {
            return ApiResponse::error('domain not found', 404, null, 404);
        }

        $audit = $this->buildDomainDeleteAudit($record);
        if (empty($audit['can_delete'])) {
            return ApiResponse::error(
                'domain cannot be deleted until the recycle-bin conflict is cleared',
                422,
                ['audit' => $audit],
                422
            );
        }

        $payload = RequestPayload::all($request);
        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error(
                'confirmation phrase mismatch',
                422,
                ['audit' => $audit],
                422
            );
        }

        Db::transaction(function () use ($id): void {
            $this->deleteDomainRow($id);
        });

        $this->recordAdminDomainDelete($request, $audit);

        return ApiResponse::success([
            'deleted_domain_id' => $id,
            'deleted_domain_label' => (string)($audit['domain_label'] ?? ''),
            'audit' => $audit,
        ], 'domain deleted');
    }

    public function batchDeleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $domainIds = $this->normalizeDomainIds($payload['domain_ids'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success([
            'audit' => $this->batchDomainDeleteAudit($domainIds),
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
            $domainIds = $this->normalizeDomainIds($payload['domain_ids'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $audit = $this->batchDomainDeleteAudit($domainIds);
        if (empty($audit['can_delete_all'])) {
            return ApiResponse::error(
                'selected domains cannot be batch deleted until the recycle-bin conflicts are cleared',
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
            foreach ((array)($audit['deletable_domain_ids'] ?? []) as $domainId) {
                $this->deleteDomainRow((int)$domainId);
            }
        });

        $this->recordAdminDomainBatchDelete($request, $audit);

        return ApiResponse::success([
            'deleted_domain_ids' => array_values(array_map('intval', (array)($audit['deletable_domain_ids'] ?? []))),
            'deleted_count' => (int)(($audit['summary'] ?? [])['deletable_count'] ?? 0),
            'audit' => $audit,
        ], 'domain batch delete completed');
    }

    public function approve(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'status');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->domainIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('domain id is required', 422, null, 422);
        }

        $record = $this->loadDomainRow($id);
        if ($record === null) {
            return ApiResponse::error('domain not found', 404, null, 404);
        }

        if (!empty($record['delete_time'])) {
            return ApiResponse::error('recycled domain must be restored before approval', 422, null, 422);
        }

        try {
            $this->writeDomainAuditState($id, 1, null);
        } catch (\Throwable $exception) {
            return ApiResponse::error('domain approval failed', 500, null, 500);
        }

        $approved = $this->loadDomainRow($id);
        if ($approved === null) {
            return ApiResponse::error('approved domain could not be loaded', 500, null, 500);
        }

        $notification = $this->sendDomainApprovalNotification($request, $approved);
        $this->recordAdminDomainAudit($request, $record, 1, null);

        return ApiResponse::success([
            'item' => AdminDomainFormatter::format($approved),
            'approved_domain_id' => $id,
            'approved_domain_label' => $this->domainLabel($record),
            'notification' => $notification,
        ], 'domain approved');
    }

    public function reject(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'status');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $id = $this->domainIdFromRequest($request);
        if ($id <= 0) {
            return ApiResponse::error('domain id is required', 422, null, 422);
        }

        $record = $this->loadDomainRow($id);
        if ($record === null) {
            return ApiResponse::error('domain not found', 404, null, 404);
        }

        if (!empty($record['delete_time'])) {
            return ApiResponse::error('recycled domain must be restored before rejection', 422, null, 422);
        }

        $payload = RequestPayload::all($request);

        try {
            $reason = $this->normalizeRejectionReason($payload['reason'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        try {
            $this->writeDomainAuditState($id, 2, $reason);
        } catch (\Throwable $exception) {
            return ApiResponse::error('domain rejection failed', 500, null, 500);
        }

        $rejected = $this->loadDomainRow($id);
        if ($rejected === null) {
            return ApiResponse::error('rejected domain could not be loaded', 500, null, 500);
        }

        $this->recordAdminDomainAudit($request, $record, 2, $reason);

        return ApiResponse::success([
            'item' => AdminDomainFormatter::format($rejected),
            'rejected_domain_id' => $id,
            'rejected_domain_label' => $this->domainLabel($record),
            'reason' => $reason,
        ], 'domain rejected');
    }

    private function domainQuery(): Builder
    {
        return Db::table(BusinessTable::domain('domain'))
            ->leftJoin(BusinessTable::user('merchant'), 'domain.user_id', '=', 'merchant.id')
            ->select(
                'domain.id',
                'domain.user_id',
                'domain.sitename',
                'domain.siteurl',
                'domain.status',
                'domain.reason',
                'domain.create_time',
                'domain.delete_time',
                'merchant.username as merchant_username',
                'merchant.name as merchant_name',
                'merchant.email as merchant_email',
                'merchant.mobile as merchant_mobile'
            );
    }

    private function applyBaseFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string)$request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('domain.sitename', 'like', '%' . $keyword . '%')
                    ->orWhere('domain.siteurl', 'like', '%' . $keyword . '%')
                    ->orWhere('domain.reason', 'like', '%' . $keyword . '%')
                    ->orWhere('merchant.username', 'like', '%' . $keyword . '%')
                    ->orWhere('merchant.name', 'like', '%' . $keyword . '%')
                    ->orWhere('merchant.email', 'like', '%' . $keyword . '%')
                    ->orWhere('merchant.mobile', 'like', '%' . $keyword . '%');

                if (ctype_digit($keyword)) {
                    $builder
                        ->orWhere('domain.id', (int)$keyword)
                        ->orWhere('domain.user_id', (int)$keyword);
                }
            });
        }

        $userId = trim((string)$request->get('user_id', ''));
        if ($userId !== '') {
            $query->where('domain.user_id', 'like', '%' . $userId . '%');
        }

        $siteName = trim((string)$request->get('sitename', ''));
        if ($siteName !== '') {
            $query->where('domain.sitename', 'like', '%' . $siteName . '%');
        }

        $siteUrl = trim((string)$request->get('siteurl', ''));
        if ($siteUrl !== '') {
            $query->where('domain.siteurl', 'like', '%' . $siteUrl . '%');
        }
    }

    private function applyStatusFilter(Builder $query, Request $request): void
    {
        $status = trim((string)$request->get('status', ''));
        if ($status === '-1' || strtolower($status) === 'deleted') {
            $query->whereNotNull('domain.delete_time');
            return;
        }

        $query->whereNull('domain.delete_time');

        if (in_array($status, ['0', '1', '2'], true)) {
            $query->where('domain.status', (int)$status);
        }
    }

    private function summary(Builder $query): array
    {
        return [
            'pending_count' => (int)(clone $query)
                ->where('domain.status', 0)
                ->whereNull('domain.delete_time')
                ->count('domain.id'),
            'approved_count' => (int)(clone $query)
                ->where('domain.status', 1)
                ->whereNull('domain.delete_time')
                ->count('domain.id'),
            'rejected_count' => (int)(clone $query)
                ->where('domain.status', 2)
                ->whereNull('domain.delete_time')
                ->count('domain.id'),
            'deleted_count' => (int)(clone $query)
                ->whereNotNull('domain.delete_time')
                ->count('domain.id'),
        ];
    }

    private function loadDomainRow(int $id): ?array
    {
        $row = $this->domainQuery()
            ->where('domain.id', $id)
            ->first();

        return $row ? (array)$row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadDomainRowsByIds(array $domainIds): array
    {
        if ($domainIds === []) {
            return [];
        }

        return array_map(
            static fn($row): array => (array)$row,
            $this->domainQuery()
                ->whereIn('domain.id', $domainIds)
                ->get()
                ->toArray()
        );
    }

    private function deleteDomainRow(int $id): void
    {
        Db::table(BusinessTable::domain())
            ->where('id', $id)
            ->update(['delete_time' => date('Y-m-d H:i:s')]);
    }

    private function restoreDomainRow(int $id): void
    {
        Db::table(BusinessTable::domain())
            ->where('id', $id)
            ->update(['delete_time' => null]);
    }

    private function writeDomainAuditState(int $id, int $status, ?string $reason): void
    {
        Db::table(BusinessTable::domain())
            ->where('id', $id)
            ->update([
                'status' => $status,
                'reason' => $reason,
            ]);
    }

    private function loadMerchantRecord(int $userId): ?array
    {
        $row = Db::table(BusinessTable::user())
            ->select('id', 'username', 'name', 'email', 'mobile')
            ->where('id', $userId)
            ->first();

        return $row ? (array)$row : null;
    }

    private function normalizeWritePayload(array $payload, ?array $currentRecord = null): array
    {
        $userId = $this->normalizeMerchantId($payload['user_id'] ?? ($currentRecord['user_id'] ?? null));
        $merchant = $this->loadMerchantRecord($userId);
        if ($merchant === null) {
            throw new \InvalidArgumentException('商户不存在');
        }

        $siteName = $this->normalizeSiteName($payload['sitename'] ?? ($currentRecord['sitename'] ?? null));
        $siteUrl = $this->normalizeSiteUrl($payload['siteurl'] ?? ($currentRecord['siteurl'] ?? null));

        $currentSiteUrl = $this->normalizeStoredSiteUrl($currentRecord['siteurl'] ?? null);
        $siteUrlChanged = $currentRecord !== null && $siteUrl !== $currentSiteUrl;
        $review = $currentRecord === null || $siteUrlChanged
            ? $this->evaluateDomainReviewRule($siteUrl)
            : [
                'status' => (int)($currentRecord['status'] ?? 0),
                'reason' => $this->nullableString($currentRecord['reason'] ?? null),
                'source' => 'unchanged',
                'siteurl_changed' => false,
            ];

        if ($currentRecord === null) {
            $this->assertDomainDailyCreateLimit($userId);
        }

        return [
            'user_id' => $userId,
            'merchant' => $merchant,
            'sitename' => $siteName,
            'siteurl' => $siteUrl,
            'status' => (int)($review['status'] ?? 0),
            'reason' => $this->nullableString($review['reason'] ?? null),
            'review_source' => (string)($review['source'] ?? 'manual_review'),
            'siteurl_changed' => $siteUrlChanged,
        ];
    }

    private function evaluateDomainReviewRule(string $siteUrl): array
    {
        $config = SystemConfig::all();
        $matchedWhiteList = $this->domainListContains((string)($config['domain_white'] ?? ''), $siteUrl);
        $matchedBlackList = $this->domainListContains((string)($config['domain_black'] ?? ''), $siteUrl);

        if ($matchedBlackList) {
            throw new \InvalidArgumentException('domain is blocked by the system blacklist');
        }

        $autoExamine = (string)($config['is_examine'] ?? '0') === '1';
        if ($matchedWhiteList) {
            return [
                'status' => 1,
                'reason' => null,
                'source' => 'white_list',
                'siteurl_changed' => true,
            ];
        }

        if ($autoExamine) {
            return [
                'status' => 1,
                'reason' => null,
                'source' => 'auto_examine',
                'siteurl_changed' => true,
            ];
        }

        return [
            'status' => 0,
            'reason' => null,
            'source' => 'manual_review',
            'siteurl_changed' => true,
        ];
    }

    private function assertDomainDailyCreateLimit(int $userId): void
    {
        $dailyLimit = SystemConfig::int('domainNum', 0);
        if ($dailyLimit <= 0) {
            return;
        }

        $createdToday = (int)Db::table(BusinessTable::domain())
            ->where('user_id', $userId)
            ->whereDate('create_time', date('Y-m-d'))
            ->count('id');

        if ($createdToday >= $dailyLimit) {
            throw new \InvalidArgumentException('daily domain submission limit has been reached for this merchant');
        }
    }

    private function domainListContains(string $list, string $siteUrl): bool
    {
        $entries = preg_split('/[\r\n,;]+/', trim($list)) ?: [];
        if ($entries === []) {
            return false;
        }

        $normalizedTarget = strtolower($this->normalizeStoredSiteUrl($siteUrl));
        if ($normalizedTarget === '') {
            return false;
        }

        foreach ($entries as $entry) {
            $candidate = strtolower($this->normalizeStoredSiteUrl($entry));
            if ($candidate === '') {
                continue;
            }

            if ($candidate === $normalizedTarget) {
                return true;
            }

            if (str_starts_with($normalizedTarget, $candidate . '/')) {
                return true;
            }
        }

        return false;
    }

    private function normalizeMerchantId(mixed $value): int
    {
        if (is_bool($value) || is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('merchant id is invalid');
        }

        $normalized = trim((string)$value);
        if ($normalized === '' || !ctype_digit($normalized)) {
            throw new \InvalidArgumentException('merchant id is required');
        }

        $userId = (int)$normalized;
        if ($userId <= 0) {
            throw new \InvalidArgumentException('merchant id is invalid');
        }

        return $userId;
    }

    private function normalizeSiteName(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('domain site name must be a scalar');
        }

        $siteName = trim(strip_tags((string)$value));
        if ($siteName === '') {
            throw new \InvalidArgumentException('domain site name is required');
        }

        if (mb_strlen($siteName) > 255) {
            throw new \InvalidArgumentException('domain site name is too long');
        }

        return $siteName;
    }

    private function normalizeSiteUrl(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('domain url must be a scalar');
        }

        $siteUrl = trim(strip_tags((string)$value));
        if ($siteUrl === '') {
            throw new \InvalidArgumentException('domain url is required');
        }

        $siteUrl = preg_replace('#^https?://#i', '', $siteUrl) ?? $siteUrl;
        $siteUrl = rtrim($siteUrl, '/');
        $siteUrl = trim($siteUrl);

        if ($siteUrl === '') {
            throw new \InvalidArgumentException('domain url is required');
        }

        if (mb_strlen($siteUrl) > 255) {
            throw new \InvalidArgumentException('domain url is too long');
        }

        if (preg_match('/\s/u', $siteUrl)) {
            throw new \InvalidArgumentException('domain url must not contain whitespace');
        }

        return $siteUrl;
    }

    private function normalizeStoredSiteUrl(mixed $value): string
    {
        $siteUrl = trim((string)$value);
        $siteUrl = preg_replace('#^https?://#i', '', $siteUrl) ?? $siteUrl;
        $siteUrl = rtrim($siteUrl, '/');

        return trim($siteUrl);
    }

    /**
     * @return array<int, int>
     */
    private function normalizeDomainIds(mixed $value, int $maxCount = 100): array
    {
        $items = [];

        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value) && trim((string)$value) !== '') {
            $items = preg_split('/\s*,\s*/', trim((string)$value)) ?: [];
        } elseif (is_numeric($value)) {
            $items = [$value];
        } else {
            throw new \InvalidArgumentException('domain_ids must be an array');
        }

        $domainIds = [];
        foreach ($items as $item) {
            if (is_bool($item) || is_array($item) || is_object($item)) {
                continue;
            }

            $normalized = trim((string)$item);
            if ($normalized === '' || !ctype_digit($normalized)) {
                continue;
            }

            $domainId = (int)$normalized;
            if ($domainId <= 0) {
                continue;
            }

            $domainIds[$domainId] = $domainId;
        }

        $domainIds = array_values($domainIds);
        sort($domainIds);

        if ($domainIds === []) {
            throw new \InvalidArgumentException('domain ids are required');
        }

        if (count($domainIds) > $maxCount) {
            throw new \InvalidArgumentException('too many domains were selected for one batch operation');
        }

        return $domainIds;
    }

    private function domainIdFromRequest(Request $request): int
    {
        return (int)($request->route ? $request->route->param('id', 0) : 0);
    }

    private function normalizeRejectionReason(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('domain rejection reason must be a scalar');
        }

        $reason = trim((string)$value);
        if ($reason === '') {
            throw new \InvalidArgumentException('domain rejection reason is required');
        }

        if (mb_strlen($reason) > 1000) {
            throw new \InvalidArgumentException('domain rejection reason is too long');
        }

        return $reason;
    }

    private function buildDomainDeleteAudit(array $record): array
    {
        $domainId = (int)($record['id'] ?? 0);
        $blockingReasons = [];
        if (!empty($record['delete_time'])) {
            $blockingReasons[] = 'This domain is already in the recycle bin.';
        }

        return [
            'domain_id' => $domainId,
            'domain_label' => $this->domainLabel($record),
            'merchant_id' => (int)($record['user_id'] ?? 0),
            'merchant_display' => AdminDomainFormatter::format($record)['merchant_display'] ?? '',
            'siteurl' => trim((string)($record['siteurl'] ?? '')),
            'confirmation_phrase' => $this->domainDeleteConfirmationPhrase($domainId),
            'can_delete' => $blockingReasons === [],
            'blocking_reasons' => $blockingReasons,
            'summary' => [
                'delete_row_count' => $blockingReasons === [] ? 1 : 0,
                'non_empty_target_count' => 1,
                'blocked_count' => $blockingReasons === [] ? 0 : 1,
            ],
            'warnings' => [
                'Deleting a domain moves the row into the recycle bin first.',
                'You can restore the domain later from the recycle view if the deletion was accidental.',
            ],
        ];
    }

    private function batchDomainDeleteAudit(array $domainIds): array
    {
        $rows = $this->loadDomainRowsByIds($domainIds);
        $rowMap = [];
        foreach ($rows as $row) {
            $rowMap[(int)($row['id'] ?? 0)] = $row;
        }

        $items = [];
        $deletableDomainIds = [];
        $blockedDomainIds = [];
        $missingDomainIds = [];
        $deleteRowCount = 0;
        $nonEmptyTargetCount = 0;

        foreach ($domainIds as $domainId) {
            $row = $rowMap[$domainId] ?? null;
            if ($row === null) {
                $missingDomainIds[] = $domainId;
                $items[] = [
                    'domain_id' => $domainId,
                    'domain_label' => '',
                    'merchant_id' => 0,
                    'merchant_display' => '',
                    'siteurl' => '',
                    'exists' => false,
                    'can_delete' => false,
                    'blocking_reasons' => ['This domain record was not found.'],
                    'summary' => [
                        'delete_row_count' => 0,
                        'non_empty_target_count' => 0,
                        'blocked_count' => 1,
                    ],
                    'warnings' => ['Remove missing domains from the selection before retrying the batch delete.'],
                ];
                continue;
            }

            $audit = $this->buildDomainDeleteAudit($row);
            $items[] = [
                'domain_id' => $domainId,
                'domain_label' => (string)($audit['domain_label'] ?? ''),
                'merchant_id' => (int)($audit['merchant_id'] ?? 0),
                'merchant_display' => (string)($audit['merchant_display'] ?? ''),
                'siteurl' => (string)($audit['siteurl'] ?? ''),
                'exists' => true,
                'can_delete' => !empty($audit['can_delete']),
                'blocking_reasons' => array_values(array_map('strval', (array)($audit['blocking_reasons'] ?? []))),
                'summary' => (array)($audit['summary'] ?? []),
                'warnings' => array_values(array_map('strval', (array)($audit['warnings'] ?? []))),
            ];

            $summary = (array)($audit['summary'] ?? []);
            $deleteRowCount += (int)($summary['delete_row_count'] ?? 0);
            $nonEmptyTargetCount += (int)($summary['non_empty_target_count'] ?? 0);

            if (!empty($audit['can_delete'])) {
                $deletableDomainIds[] = $domainId;
                continue;
            }

            $blockedDomainIds[] = $domainId;
        }

        $warnings = [];
        if ($missingDomainIds !== []) {
            $warnings[] = 'Some selected domains no longer exist and must be removed from the batch selection.';
        }
        if ($blockedDomainIds !== []) {
            $warnings[] = 'At least one selected domain is already in the recycle bin, so the batch delete is paused until the selection is cleaned up.';
        }
        if ($deletableDomainIds !== []) {
            $warnings[] = 'Batch delete moves the selected domains into the recycle bin after one shared confirmation phrase is accepted.';
        }

        return [
            'requested_domain_ids' => $domainIds,
            'deletable_domain_ids' => $deletableDomainIds,
            'blocked_domain_ids' => $blockedDomainIds,
            'missing_domain_ids' => $missingDomainIds,
            'confirmation_phrase' => $this->batchDomainDeleteConfirmationPhrase($domainIds),
            'can_delete_all' => $domainIds !== [] && $blockedDomainIds === [] && $missingDomainIds === [],
            'items' => $items,
            'summary' => [
                'requested_count' => count($domainIds),
                'existing_count' => count($domainIds) - count($missingDomainIds),
                'deletable_count' => count($deletableDomainIds),
                'blocked_count' => count($blockedDomainIds),
                'missing_count' => count($missingDomainIds),
                'delete_row_count' => $deleteRowCount,
                'non_empty_target_count' => $nonEmptyTargetCount,
            ],
            'warnings' => $warnings,
        ];
    }

    private function domainLabel(array $record): string
    {
        $siteName = trim((string)($record['sitename'] ?? ''));
        if ($siteName !== '') {
            return $siteName;
        }

        $siteUrl = trim((string)($record['siteurl'] ?? ''));
        if ($siteUrl !== '') {
            return $siteUrl;
        }

        return 'domain #' . (int)($record['id'] ?? 0);
    }

    private function domainDeleteConfirmationPhrase(int $id): string
    {
        return 'DELETE DOMAIN ' . $id;
    }

    private function batchDomainDeleteConfirmationPhrase(array $domainIds): string
    {
        return sprintf(
            'DELETE DOMAIN BATCH %d-%s',
            count($domainIds),
            strtoupper(substr(md5(implode(',', $domainIds)), 0, 6))
        );
    }

    private function sendDomainApprovalNotification(Request $request, array $record): array
    {
        $recipient = trim((string)($record['merchant_email'] ?? ''));
        if ($recipient === '') {
            return [
                'attempted' => false,
                'sent' => false,
                'status' => 'skipped',
                'message' => 'merchant email is empty',
            ];
        }

        $config = SystemConfig::all();
        $mailer = new AdminSmtpMailer();
        $summary = $mailer->configurationSummary($config);

        if (!$summary['enabled']) {
            return [
                'attempted' => false,
                'sent' => false,
                'status' => 'skipped',
                'message' => 'system email switch is disabled',
            ];
        }

        if (!$summary['configured']) {
            return [
                'attempted' => false,
                'sent' => false,
                'status' => 'skipped',
                'message' => 'smtp configuration is incomplete',
            ];
        }

        try {
            $mailer->sendHtml(
                $recipient,
                '域名审核通过',
                $this->domainApprovalEmailHtml($request, $record, $config),
                $config
            );

            return [
                'attempted' => true,
                'sent' => true,
                'status' => 'sent',
                'message' => 'domain approval email sent',
            ];
        } catch (\Throwable $exception) {
            return [
                'attempted' => true,
                'sent' => false,
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function domainApprovalEmailHtml(Request $request, array $record, array $config): string
    {
        $siteName = htmlspecialchars($this->domainLabel($record), ENT_QUOTES, 'UTF-8');
        $siteUrl = htmlspecialchars(trim((string)($record['siteurl'] ?? '')), ENT_QUOTES, 'UTF-8');
        $platformName = htmlspecialchars(trim((string)($config['sitename'] ?? 'AiPay')), ENT_QUOTES, 'UTF-8');
        $merchantCenterUrl = htmlspecialchars(FrontendUrlBuilder::merchantDashboardUrl($request), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div style="font-family:Arial,sans-serif;background:#f8fafc;padding:24px;color:#0f172a;">
  <div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:18px;padding:32px;box-shadow:0 12px 32px rgba(15,23,42,0.08);">
    <h2 style="margin:0 0 16px;font-size:24px;">域名审核通过</h2>
    <p style="margin:0 0 12px;line-height:1.7;">您提交的域名已通过审核，可以正常发起支付。</p>
    <p style="margin:0 0 6px;line-height:1.7;"><strong>站点名称：</strong>{$siteName}</p>
    <p style="margin:0 0 20px;line-height:1.7;"><strong>域名地址：</strong>{$siteUrl}</p>
    <p style="margin:0 0 24px;line-height:1.7;">感谢您对 {$platformName} 的支持。</p>
    <a href="{$merchantCenterUrl}" style="display:inline-block;padding:12px 22px;background:#f59e0b;color:#ffffff;text-decoration:none;border-radius:10px;">前往商户中心</a>
    <p style="margin:24px 0 0;font-size:12px;line-height:1.7;color:#64748b;">这是一封系统自动发送的通知邮件，请勿直接回复。</p>
  </div>
</div>
HTML;
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);

        return $string === '' ? null : $string;
    }

    private function recordAdminDomainCreate(Request $request, array $record, array $payload): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $domainId = (int)($record['id'] ?? 0);
        $domainLabel = $this->truncateLogText($this->domainLabel($record), 120);
        $siteUrl = $this->truncateLogText((string)($record['siteurl'] ?? ''), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/domains/create',
            'desc' => sprintf(
                'domain create domain_id=%d label="%s" merchant_id=%d status=%d source=%s siteurl="%s"',
                $domainId,
                $domainLabel,
                (int)($record['user_id'] ?? 0),
                (int)($record['status'] ?? 0),
                $this->truncateLogText((string)($payload['review_source'] ?? 'manual_review'), 40),
                $siteUrl
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminDomainUpdate(Request $request, array $before, array $after, array $payload): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $domainId = (int)($after['id'] ?? 0);
        $domainLabel = $this->truncateLogText($this->domainLabel($after), 120);
        $siteUrl = $this->truncateLogText((string)($after['siteurl'] ?? ''), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/domains/' . $domainId . '/update',
            'desc' => sprintf(
                'domain update domain_id=%d label="%s" merchant_id=%d from_status=%d to_status=%d siteurl_changed=%d source=%s siteurl="%s"',
                $domainId,
                $domainLabel,
                (int)($after['user_id'] ?? 0),
                (int)($before['status'] ?? 0),
                (int)($after['status'] ?? 0),
                !empty($payload['siteurl_changed']) ? 1 : 0,
                $this->truncateLogText((string)($payload['review_source'] ?? 'unchanged'), 40),
                $siteUrl
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminDomainRestore(Request $request, array $record): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $domainId = (int)($record['id'] ?? 0);
        $domainLabel = $this->truncateLogText($this->domainLabel($record), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/domains/' . $domainId . '/restore',
            'desc' => sprintf(
                'domain restore domain_id=%d label="%s"',
                $domainId,
                $domainLabel
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminDomainBatchRestore(
        Request $request,
        array $restorableRows,
        array $requestedDomainIds,
        array $alreadyActiveDomainIds,
        array $missingDomainIds
    ): void {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $restoredDomainIds = implode(',', array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            $restorableRows
        ));
        $restoredLabels = implode(',', array_map(
            fn(array $row): string => $this->domainLabel($row),
            $restorableRows
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/domains/batch-restore',
            'desc' => sprintf(
                'domain batch restore requested=%d restored=%d active=%d missing=%d domains="%s" labels="%s"',
                count($requestedDomainIds),
                count($restorableRows),
                count($alreadyActiveDomainIds),
                count($missingDomainIds),
                $this->truncateLogText($restoredDomainIds, 255),
                $this->truncateLogText($restoredLabels, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminDomainDelete(Request $request, array $audit): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $domainId = (int)($audit['domain_id'] ?? 0);
        $domainLabel = $this->truncateLogText((string)($audit['domain_label'] ?? ''), 120);

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/domains/' . $domainId . '/delete',
            'desc' => sprintf(
                'domain delete domain_id=%d label="%s" merchant_id=%d delete_rows=%d',
                $domainId,
                $domainLabel,
                (int)($audit['merchant_id'] ?? 0),
                (int)($summary['delete_row_count'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminDomainBatchDelete(Request $request, array $audit): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $domainIds = implode(',', array_map('intval', (array)($audit['deletable_domain_ids'] ?? [])));
        $domainLabels = implode(',', array_map(
            static function (array $item): string {
                $label = trim((string)($item['domain_label'] ?? ''));
                $domainId = (int)($item['domain_id'] ?? 0);

                return $label !== '' ? $label : ('domain #' . $domainId);
            },
            array_values(array_filter(
                (array)($audit['items'] ?? []),
                static fn(array $item): bool => !empty($item['can_delete'])
            ))
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/domains/batch-delete',
            'desc' => sprintf(
                'domain batch delete requested=%d deleted=%d blocked=%d missing=%d domains="%s" labels="%s"',
                (int)($summary['requested_count'] ?? 0),
                (int)($summary['deletable_count'] ?? 0),
                (int)($summary['blocked_count'] ?? 0),
                (int)($summary['missing_count'] ?? 0),
                $this->truncateLogText($domainIds, 255),
                $this->truncateLogText($domainLabels, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminDomainAudit(Request $request, array $record, int $nextStatus, ?string $reason): void
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $domainId = (int)($record['id'] ?? 0);
        $previousStatus = (int)($record['status'] ?? 0);
        $domainLabel = $this->truncateLogText($this->domainLabel($record), 120);
        $action = $nextStatus === 1 ? 'approve' : 'reject';
        $desc = sprintf(
            'domain %s domain_id=%d label="%s" merchant_id=%d from_status=%d to_status=%d',
            $action,
            $domainId,
            $domainLabel,
            (int)($record['user_id'] ?? 0),
            $previousStatus,
            $nextStatus
        );

        if ($nextStatus === 2) {
            $desc .= sprintf(' reason="%s"', $this->truncateLogText((string)$reason, 255));
        }

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/domains/' . $domainId . '/' . $action,
            'desc' => $desc,
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorizeAny($request, 'SystemDomains', [$authMark, 'index']);
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
