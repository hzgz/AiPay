<?php

namespace app\support;

use Webman\Http\Request;
use Webman\Http\Response;
use support\Db;

class MerchantImpersonationService
{
    private const COOKIE_TTL = 7 * 86400;
    private const TICKET_TTL = 180;

    public function audit(int $merchantId, string $merchantCenterTargetUrl): array
    {
        if ($merchantId <= 0) {
            throw new \InvalidArgumentException('merchant id is invalid');
        }

        $merchant = $this->merchant($merchantId);
        if ($merchant === null) {
            throw new \InvalidArgumentException('merchant not found');
        }

        $config = SystemConfig::all();
        $systemSecurityEnabled = $this->configEnabled($config, 'isSecurity');
        $securityForceEnabled = $this->configEnabled($config, 'isSecurityForce');
        $securityLoginEnabled = $this->configEnabled($config, 'isSecurityLogin');
        $googlekeyConfigured = trim((string)($merchant['googlekey'] ?? '')) !== '';
        $isFrozen = (int)($merchant['is_frozen'] ?? 0) === 1;

        $warnings = [];
        $possibleRedirects = [];

        if ($isFrozen) {
            $warnings[] = '当前商户已冻结，暂不允许代登录。';
        }

        if ($systemSecurityEnabled && $googlekeyConfigured) {
            $warnings[] = '当前商户已绑定谷歌验证，登录后可能跳转到 /merchant/security 完成安全校验。';
            $possibleRedirects[] = '/merchant/security';
        }

        if ($systemSecurityEnabled && $securityForceEnabled && !$googlekeyConfigured) {
            $warnings[] = '系统已开启强制安全设置，未绑定谷歌验证的商户登录后可能跳转到 /merchant/security 完成安全设置。';
            $possibleRedirects[] = '/merchant/security';
        }

        if ($securityLoginEnabled && $googlekeyConfigured) {
            $warnings[] = '当前商户登录时需要额外填写谷歌验证码；代登录仅会写入 front_token 会话。';
        }

        return [
            'merchant_id' => $merchantId,
            'merchant_username' => (string)($merchant['username'] ?? ''),
            'can_impersonate' => !$isFrozen,
            'target_url' => trim($merchantCenterTargetUrl),
            'warnings' => $warnings,
            'possible_redirects' => array_values(array_unique($possibleRedirects)),
            'system_security_enabled' => $systemSecurityEnabled,
            'security_force_enabled' => $securityForceEnabled,
            'security_login_enabled' => $securityLoginEnabled,
            'googlekey_configured' => $googlekeyConfigured,
            'merchant' => $merchant,
        ];
    }

    public function publicAudit(array $audit): array
    {
        unset($audit['merchant']);

        return $audit;
    }

    public function issue(array $audit, array $admin, string $webmanBaseUrl, Request $request): array
    {
        if (empty($audit['can_impersonate'])) {
            throw new \InvalidArgumentException('merchant cannot be impersonated');
        }

        $merchant = (array)($audit['merchant'] ?? []);
        $merchantId = (int)($merchant['id'] ?? ($audit['merchant_id'] ?? 0));
        if ($merchantId <= 0) {
            throw new \InvalidArgumentException('merchant not found');
        }

        $merchantUsername = trim((string)($merchant['username'] ?? ($audit['merchant_username'] ?? '')));
        $newToken = $this->rotateMerchantToken($merchantId);
        $expiresAtTs = time() + self::TICKET_TTL;
        $issuedIp = trim((string)$request->getRealIp());
        $issuedUserAgent = trim((string)$request->header('user-agent', ''));

        $ticket = $this->createTicket([
            'merchant_id' => $merchantId,
            'merchant_username' => $merchantUsername,
            'merchant_token' => $newToken,
            'target_url' => (string)($audit['target_url'] ?? ''),
            'admin_id' => (int)($admin['id'] ?? 0),
            'admin_username' => (string)($admin['username'] ?? ''),
            'issued_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', $expiresAtTs),
            'expires_at_ts' => $expiresAtTs,
            'issued_ip' => $issuedIp,
            'issued_user_agent_hash' => $this->userAgentHash($issuedUserAgent),
        ]);

        return [
            'merchant_id' => $merchantId,
            'merchant_username' => $merchantUsername,
            'redirect_url' => rtrim($webmanBaseUrl, '/') . '/api/admin/merchant-impersonations/' . rawurlencode($ticket),
            'target_url' => (string)($audit['target_url'] ?? ''),
            'expires_at' => date('Y-m-d H:i:s', $expiresAtTs),
            'audit' => $this->publicAudit($audit),
        ];
    }

    public function consume(string $ticket, Request $request): Response
    {
        $payload = $this->consumeTicket($ticket);
        if ($payload === null) {
            return $this->invalidTicketResponse();
        }

        if (!$this->matchesRequestFingerprint($payload, $request)) {
            return $this->invalidTicketResponse();
        }

        $merchant = $this->merchant((int)($payload['merchant_id'] ?? 0));
        if ($merchant === null) {
            return $this->invalidTicketResponse();
        }

        if ((int)($merchant['is_frozen'] ?? 0) === 1) {
            return $this->invalidTicketResponse();
        }

        if ((string)($merchant['token'] ?? '') !== (string)($payload['merchant_token'] ?? '')) {
            return $this->invalidTicketResponse();
        }

        // Use a top-level redirect bridge so the merchant cookie is written before opening the center.
        return redirect((string)($payload['target_url'] ?? '/'))
            ->cookie('PHPSESSID', '', 0, '/')
            ->cookie('sign', '', 0, '/')
            ->cookie('front_token', (string)$payload['merchant_token'], self::COOKIE_TTL, '/');
    }

    private function merchant(int $merchantId): ?array
    {
        $row = Db::table(BusinessTable::user())
            ->select('id', 'username', 'token', 'is_frozen', 'frozen_reason', 'googlekey')
            ->where('id', $merchantId)
            ->first();

        return $row ? (array)$row : null;
    }

    private function rotateMerchantToken(int $merchantId): string
    {
        $newToken = $this->newMerchantToken($merchantId);

        Db::table(BusinessTable::user())
            ->where('id', $merchantId)
            ->update([
                'token' => $newToken,
            ]);

        return $newToken;
    }

    private function newMerchantToken(int $merchantId): string
    {
        return substr(bin2hex(random_bytes(16)), 0, 32)
            . $merchantId
            . str_replace('.', '', sprintf('%.6f', microtime(true)));
    }

    private function configEnabled(array $config, string $key): bool
    {
        return trim((string)($config[$key] ?? '0')) === '1';
    }

    private function matchesRequestFingerprint(array $payload, Request $request): bool
    {
        $expectedIp = trim((string)($payload['issued_ip'] ?? ''));
        $requestIp = trim((string)$request->getRealIp());
        if ($expectedIp !== '' && $requestIp !== '' && !hash_equals($expectedIp, $requestIp)) {
            return false;
        }

        $expectedUserAgentHash = trim((string)($payload['issued_user_agent_hash'] ?? ''));
        if ($expectedUserAgentHash === '') {
            return true;
        }

        return hash_equals(
            $expectedUserAgentHash,
            $this->userAgentHash(trim((string)$request->header('user-agent', '')))
        );
    }

    private function userAgentHash(string $userAgent): string
    {
        return hash('sha256', trim($userAgent));
    }

    private function createTicket(array $payload): string
    {
        $this->cleanupExpiredTickets();
        $this->ensureTicketDirectory();

        $ticket = bin2hex(random_bytes(24));
        $payload['ticket'] = $ticket;
        $path = $this->ticketPath($ticket);
        $tmpPath = $path . '.tmp';

        file_put_contents(
            $tmpPath,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );

        if (!@rename($tmpPath, $path)) {
            @unlink($tmpPath);
            throw new \RuntimeException('failed to create merchant impersonation ticket');
        }

        return $ticket;
    }

    private function consumeTicket(string $ticket): ?array
    {
        if (!$this->isValidTicket($ticket)) {
            return null;
        }

        $path = $this->ticketPath($ticket);
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if (!@unlink($path)) {
            @unlink($path);
        }

        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        $expiresAtTs = (int)($decoded['expires_at_ts'] ?? 0);
        if ($expiresAtTs <= 0 || $expiresAtTs < time()) {
            return null;
        }

        return $decoded;
    }

    private function cleanupExpiredTickets(): void
    {
        $dir = $this->ticketDirectory();
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        foreach ($files as $file) {
            $raw = @file_get_contents($file);
            if (!is_string($raw) || trim($raw) === '') {
                @unlink($file);
                continue;
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                @unlink($file);
                continue;
            }

            $expiresAtTs = (int)($decoded['expires_at_ts'] ?? 0);
            if ($expiresAtTs > 0 && $expiresAtTs >= time()) {
                continue;
            }

            @unlink($file);
        }
    }

    private function ensureTicketDirectory(): void
    {
        $dir = $this->ticketDirectory();
        if (is_dir($dir)) {
            return;
        }

        mkdir($dir, 0777, true);
    }

    private function ticketDirectory(): string
    {
        return runtime_path() . DIRECTORY_SEPARATOR . 'merchant-impersonation';
    }

    private function ticketPath(string $ticket): string
    {
        return $this->ticketDirectory() . DIRECTORY_SEPARATOR . $ticket . '.json';
    }

    private function isValidTicket(string $ticket): bool
    {
        return preg_match('/^[a-f0-9]{48}$/', $ticket) === 1;
    }

    private function invalidTicketResponse(): Response
    {
        return response(
            'merchant impersonation ticket is invalid or expired',
            410,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }
}
