<?php

namespace app\support;

class LegacyApiCompatState
{
    private const IMAGE_CAPTCHA_TTL = 300;
    private const VERIFY_CODE_TTL = 300;

    public static function storeImageCaptcha(string $code, ?int $ttl = null): void
    {
        $state = self::read();
        $state['image_captcha'] = [
            'code' => strtoupper(trim($code)),
            'expires_at' => time() + max(60, $ttl ?? self::IMAGE_CAPTCHA_TTL),
        ];

        self::write($state);
    }

    public static function verifyImageCaptcha(string $code): bool
    {
        $state = self::read();
        $captcha = (array)($state['image_captcha'] ?? []);
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

        $state = self::read();
        $key = self::verificationKey($purpose, $channel, $target);
        $payload = [
            'purpose' => $purpose,
            'channel' => $channel,
            'target' => $target,
            'code' => trim($code),
            'expires_at' => time() + max(60, $ttl ?? self::VERIFY_CODE_TTL),
        ];

        $codes = (array)($state['verification_codes'] ?? []);
        $codes[$key] = $payload;
        $state['verification_codes'] = $codes;
        $state['latest_verification_code'] = $payload;

        self::write($state);
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

        $state = self::read();
        $codes = (array)($state['verification_codes'] ?? []);
        $key = self::verificationKey($purpose, $channel, $target);
        $entry = (array)($codes[$key] ?? []);

        if (self::matchesCodeEntry($entry, $submittedCode)) {
            return true;
        }

        $latest = (array)($state['latest_verification_code'] ?? []);
        return self::matchesCodeEntry($latest, $submittedCode);
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

    private static function read(): array
    {
        $path = self::path();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string)file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        return self::purgeExpired($decoded);
    }

    private static function write(array $state): void
    {
        $state = self::purgeExpired($state);
        $path = self::path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents(
            $path,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    private static function purgeExpired(array $state): array
    {
        $now = time();
        $codes = [];
        foreach ((array)($state['verification_codes'] ?? []) as $key => $payload) {
            $item = (array)$payload;
            if ((int)($item['expires_at'] ?? 0) >= $now) {
                $codes[$key] = $item;
            }
        }
        $state['verification_codes'] = $codes;

        $image = (array)($state['image_captcha'] ?? []);
        if ((int)($image['expires_at'] ?? 0) < $now) {
            unset($state['image_captcha']);
        }

        $latest = (array)($state['latest_verification_code'] ?? []);
        if ((int)($latest['expires_at'] ?? 0) < $now) {
            unset($state['latest_verification_code']);
        }

        return $state;
    }

    private static function path(): string
    {
        return runtime_path('legacy-api-compat-state.json');
    }

    private static function verificationKey(string $purpose, string $channel, string $target): string
    {
        return self::normalize($purpose) . '|' . self::normalize($channel) . '|' . self::normalizeTarget($target);
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
