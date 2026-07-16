<?php

namespace app\support;

use support\Db;

class MerchantEmailCampaignService
{
    public function __construct(
        private readonly ?AdminSmtpMailer $mailer = null
    ) {
    }

    public function audit(array $payload): array
    {
        $scope = $this->normalizeScope($payload['scope'] ?? null);
        $merchantIds = $this->normalizeMerchantIds($payload['merchant_ids'] ?? ($payload['merchant_id'] ?? []));
        $directEmail = $this->normalizeDirectEmail($payload['email'] ?? null, $scope === 'direct');
        $config = SystemConfig::all();
        $smtp = $this->mailer()->configurationSummary($config);

        $recipients = $this->resolveRecipients($scope, $merchantIds, $directEmail);
        $deliverableRecipients = [];
        $skippedRecipients = [];

        foreach ($recipients as $recipient) {
            $email = trim((string)($recipient['email'] ?? ''));
            if ($email === '') {
                $recipient['reason'] = '未配置邮箱';
                $skippedRecipients[] = $recipient;
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipient['reason'] = '邮箱格式无效';
                $skippedRecipients[] = $recipient;
                continue;
            }

            $recipient['email'] = $email;
            $recipient['reason'] = null;
            $deliverableRecipients[] = $recipient;
        }

        $warnings = $this->warnings($scope, $smtp, $deliverableRecipients, $skippedRecipients);

        return [
            'scope' => $scope,
            'scope_label' => $this->scopeLabel($scope),
            'selected_merchant_ids' => $merchantIds,
            'confirmation_phrase' => $this->confirmationPhrase($scope, $merchantIds),
            'can_send' => $smtp['ready'] && $deliverableRecipients !== [],
            'recipient_total' => count($recipients),
            'deliverable_total' => count($deliverableRecipients),
            'skipped_total' => count($skippedRecipients),
            'warnings' => $warnings,
            'smtp' => $smtp,
            'sample_recipients' => $this->previewRecipients($deliverableRecipients),
            'skipped_recipients' => $this->previewRecipients($skippedRecipients),
            'deliverable_recipients' => $deliverableRecipients,
        ];
    }

    public function publicAudit(array $audit): array
    {
        unset($audit['deliverable_recipients']);

        return $audit;
    }

    public function send(array $payload, ?array $audit = null): array
    {
        $audit = $audit ?? $this->audit($payload);
        if (!$audit['can_send']) {
            throw new \InvalidArgumentException('merchant email campaign cannot be sent');
        }

        $title = $this->normalizeTitle($payload['title'] ?? null);
        $content = $this->normalizeContent($payload['content'] ?? null);
        $config = SystemConfig::all();
        $summary = [
            'attempted_count' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
            'skipped_count' => (int)($audit['skipped_total'] ?? 0),
        ];
        $failures = [];

        foreach ((array)($audit['deliverable_recipients'] ?? []) as $recipient) {
            $summary['attempted_count']++;

            try {
                $this->mailer()->sendHtml((string)$recipient['email'], $title, $content, $config);
                $summary['sent_count']++;
            } catch (\Throwable $exception) {
                $summary['failed_count']++;
                $failures[] = [
                    'merchant_id' => isset($recipient['merchant_id']) ? (int)$recipient['merchant_id'] : null,
                    'merchant_username' => (string)($recipient['merchant_username'] ?? ''),
                    'email' => (string)($recipient['email'] ?? ''),
                    'label' => (string)($recipient['label'] ?? ''),
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return [
            'title' => $title,
            'audit' => $this->publicAudit($audit),
            'summary' => $summary,
            'failures' => $failures,
        ];
    }

    private function resolveRecipients(string $scope, array $merchantIds, ?string $directEmail): array
    {
        if ($scope === 'direct') {
            return [[
                'merchant_id' => null,
                'merchant_username' => '',
                'email' => (string)$directEmail,
                'label' => (string)$directEmail,
            ]];
        }

        $query = Db::table('ypay_user')->select('id', 'username', 'email');

        if ($scope === 'vip') {
            $query->where('vip_id', '>', 0);
        }

        if ($scope === 'merchant') {
            if ($merchantIds === []) {
                throw new \InvalidArgumentException('merchant scope requires at least one merchant id');
            }

            $query->whereIn('id', $merchantIds);
        }

        $rows = $query
            ->orderBy('id')
            ->get()
            ->toArray();

        return array_map(static function ($row): array {
            $record = (array)$row;
            $merchantId = isset($record['id']) ? (int)$record['id'] : null;
            $username = trim((string)($record['username'] ?? ''));

            return [
                'merchant_id' => $merchantId,
                'merchant_username' => $username,
                'email' => trim((string)($record['email'] ?? '')),
                'label' => $username !== ''
                    ? $username . ($merchantId !== null ? ' (#' . $merchantId . ')' : '')
                    : 'Merchant #' . (string)$merchantId,
            ];
        }, $rows);
    }

    private function normalizeScope(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('email scope is invalid');
        }

        $normalized = strtolower(trim((string)$value));

        if (!in_array($normalized, ['merchant', 'vip', 'all', 'direct'], true)) {
            throw new \InvalidArgumentException('email scope is invalid');
        }

        return $normalized;
    }

    private function normalizeMerchantIds(mixed $value): array
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

        return array_values($ids);
    }

    private function normalizeDirectEmail(mixed $value, bool $required): ?string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('direct email is invalid');
        }

        $email = trim((string)$value);
        if ($email === '') {
            if ($required) {
                throw new \InvalidArgumentException('direct email is required');
            }

            return null;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('direct email format is invalid');
        }

        return $email;
    }

    private function normalizeTitle(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('email title is invalid');
        }

        $title = trim((string)$value);
        if ($title === '') {
            throw new \InvalidArgumentException('email title is required');
        }

        if (strlen($title) > 120) {
            throw new \InvalidArgumentException('email title must be 120 characters or fewer');
        }

        return $title;
    }

    private function normalizeContent(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('email content is invalid');
        }

        $content = trim((string)$value);
        if ($content === '') {
            throw new \InvalidArgumentException('email content is required');
        }

        if (strlen($content) > 20000) {
            throw new \InvalidArgumentException('email content must be 20000 characters or fewer');
        }

        return $content;
    }

    private function warnings(
        string $scope,
        array $smtp,
        array $deliverableRecipients,
        array $skippedRecipients
    ): array {
        $warnings = [];

        if (!$smtp['enabled']) {
            $warnings[] = '系统邮件开关未开启，当前仅可预览发送范围。';
        } elseif (!$smtp['configured']) {
            $warnings[] = 'SMTP 配置不完整，请先完成发信参数配置。';
        }

        if ($deliverableRecipients === []) {
            $warnings[] = '当前范围内没有可投递的邮箱目标。';
        }

        if ($skippedRecipients !== []) {
            $warnings[] = '部分目标会被跳过，因为邮箱为空或格式无效。';
        }

        if ($scope === 'all') {
            $warnings[] = '该操作会面向全部商户群发，请确认内容和范围。';
        } elseif ($scope === 'vip') {
            $warnings[] = '会员范围会筛选已开通会员的商户（vip_id > 0）。';
        }

        return $warnings;
    }

    private function previewRecipients(array $recipients, int $limit = 8): array
    {
        return array_values(array_slice(array_map(
            static function (array $recipient): array {
                return [
                    'merchant_id' => isset($recipient['merchant_id']) ? (int)$recipient['merchant_id'] : null,
                    'merchant_username' => (string)($recipient['merchant_username'] ?? ''),
                    'email' => (string)($recipient['email'] ?? ''),
                    'label' => (string)($recipient['label'] ?? ''),
                    'reason' => isset($recipient['reason']) ? (string)$recipient['reason'] : null,
                ];
            },
            $recipients
        ), 0, $limit));
    }

    private function scopeLabel(string $scope): string
    {
        return match ($scope) {
            'merchant' => '指定商户',
            'vip' => '会员商户',
            'all' => '全部商户',
            'direct' => '直接邮箱',
            default => $scope,
        };
    }

    private function confirmationPhrase(string $scope, array $merchantIds): string
    {
        return match ($scope) {
            'merchant' => count($merchantIds) === 1
                ? 'SEND EMAIL MERCHANT ' . $merchantIds[0]
                : 'SEND EMAIL MERCHANTS ' . count($merchantIds),
            'vip' => 'SEND EMAIL VIP',
            'all' => 'SEND EMAIL ALL',
            'direct' => 'SEND EMAIL DIRECT',
            default => 'SEND EMAIL',
        };
    }

    private function mailer(): AdminSmtpMailer
    {
        return $this->mailer ?? new AdminSmtpMailer();
    }
}
