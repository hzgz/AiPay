<?php

namespace app\support;

use Webman\Http\Request;

class LegacyApiCompatState
{
    private const IMAGE_CAPTCHA_TTL = 300;
    private const VERIFY_CODE_TTL = 300;
    private const GLOBAL_IMAGE_CAPTCHA_SCOPE = 'global';
    private const IMAGE_CAPTCHA_PREFIX = 'legacy-api:image-captcha:';
    private const VERIFICATION_PREFIX = 'legacy-api:verification:';
    private const LATEST_VERIFICATION_KEY = 'legacy-api:verification:latest';

    public static function storeImageCaptcha(string $code, ?int $ttl = null): void
    {
        self::storeImageCaptchaForScope(self::GLOBAL_IMAGE_CAPTCHA_SCOPE, $code, $ttl);
    }

    public static function storeImageCaptchaForScope(string $scope, string $code, ?int $ttl = null): void
    {
        $expiresIn = max(60, $ttl ?? self::IMAGE_CAPTCHA_TTL);
        HotPathStore::put(self::imageCaptchaKey($scope), [
            'code' => strtoupper(trim($code)),
            'expires_at' => time() + $expiresIn,
        ], $expiresIn);
    }

    public static function verifyImageCaptcha(string $code): bool
    {
        return self::verifyImageCaptchaForScope(self::GLOBAL_IMAGE_CAPTCHA_SCOPE, $code);
    }

    public static function verifyImageCaptchaForScope(string $scope, string $code): bool
    {
        $captcha = (array)(HotPathStore::get(self::imageCaptchaKey($scope)) ?? []);
        $expiresAt = (int)($captcha['expires_at'] ?? 0);
        $storedCode = strtoupper(trim((string)($captcha['code'] ?? '')));

        if ($storedCode === '' || $expiresAt < time()) {
            return false;
        }

        return hash_equals($storedCode, strtoupper(trim($code)));
    }

    public static function storeVerificationCode(
        string $purpose,
        string $channel,
        string $target,
        string $code,
        ?int $ttl = null
    ): void {
        $purpose = self::normalize($purpose);
        $channel = self::normalize($channel);
        $target = self::normalizeTarget($target);
        if ($purpose === '' || $channel === '' || $target === '') {
            return;
        }

        $expiresIn = max(60, $ttl ?? self::VERIFY_CODE_TTL);
        $payload = [
            'purpose' => $purpose,
            'channel' => $channel,
            'target' => $target,
            'code' => trim($code),
            'expires_at' => time() + $expiresIn,
        ];

        HotPathStore::put(self::verificationKey($purpose, $channel, $target), $payload, $expiresIn);
        HotPathStore::put(self::LATEST_VERIFICATION_KEY, $payload, $expiresIn);
    }

    public static function verifyVerificationCode(
        string $submittedCode,
        string $purpose,
        string $channel,
        string $target
    ): bool {
        $submittedCode = trim($submittedCode);
        if ($submittedCode === '') {
            return false;
        }

        $entry = (array)(HotPathStore::get(self::verificationKey($purpose, $channel, $target)) ?? []);
        if (self::matchesCodeEntry($entry, $submittedCode)) {
            return true;
        }

        $latest = (array)(HotPathStore::get(self::LATEST_VERIFICATION_KEY) ?? []);
        return self::matchesCodeEntry($latest, $submittedCode);
    }

    public static function captchaScopeFromRequest(Request $request): string
    {
        $ip = trim((string)$request->getRealIp());
        $userAgent = strtolower(trim((string)$request->header('user-agent', '')));

        return hash('sha256', $ip . '|' . $userAgent);
    }

    public static function renderCaptchaSvg(string $code): string
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
        $chars = str_split($code);
        $width = 132;
        $height = 44;
        $x = 18;
        $spans = [];

        foreach ($chars as $index => $char) {
            $rotate = ($index % 2 === 0 ? -1 : 1) * (($index * 7) % 11);
            $y = 28 + (($index % 3) - 1) * 3;
            $spans[] = sprintf(
                '<text x="%d" y="%d" font-size="24" font-family="Arial, Helvetica, sans-serif" fill="%s" transform="rotate(%d %d %d)">%s</text>',
                $x,
                $y,
                self::palette($index),
                $rotate,
                $x,
                $y,
                htmlspecialchars($char, ENT_QUOTES, 'UTF-8')
            );
            $x += 24;
        }

        $lines = [];
        for ($i = 0; $i < 4; $i++) {
            $x1 = 6 + $i * 30;
            $y1 = 8 + ($i % 2) * 12;
            $x2 = 34 + $i * 28;
            $y2 = 34 - ($i % 3) * 6;
            $lines[] = sprintf(
                '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="%s" stroke-width="1.2" opacity="0.35" />',
                $x1,
                $y1,
                $x2,
                $y2,
                self::palette($i + 5)
            );
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">'
            . '<rect width="%d" height="%d" rx="10" fill="#f8fafc" stroke="#dbe3ee" />'
            . '%s%s'
            . '</svg>',
            $width,
            $height,
            $width,
            $height,
            $width,
            $height,
            implode('', $lines),
            implode('', $spans)
        );
    }

    private static function matchesCodeEntry(array $entry, string $submittedCode): bool
    {
        $storedCode = trim((string)($entry['code'] ?? ''));
        $expiresAt = (int)($entry['expires_at'] ?? 0);
        if ($storedCode === '' || $expiresAt < time()) {
            return false;
        }

        return hash_equals($storedCode, $submittedCode);
    }

    private static function verificationKey(string $purpose, string $channel, string $target): string
    {
        return self::VERIFICATION_PREFIX
            . self::normalize($purpose)
            . '|'
            . self::normalize($channel)
            . '|'
            . self::normalizeTarget($target);
    }

    private static function imageCaptchaKey(string $scope): string
    {
        $normalizedScope = trim($scope) !== '' ? trim($scope) : self::GLOBAL_IMAGE_CAPTCHA_SCOPE;

        return self::IMAGE_CAPTCHA_PREFIX . hash('sha256', $normalizedScope);
    }

    private static function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    private static function normalizeTarget(string $target): string
    {
        return strtolower(trim($target));
    }

    private static function palette(int $seed): string
    {
        $colors = ['#334155', '#0f766e', '#0369a1', '#7c3aed', '#b45309', '#be123c', '#1d4ed8', '#0f172a'];
        return $colors[$seed % count($colors)];
    }
}
