<?php

declare(strict_types=1);

namespace app\support;

final class LegacyMojibakeGuard
{
    private const MONEY_PLACEHOLDER = '[money]';

    /**
     * Match the small set of high-frequency mojibake code points we have seen
     * in legacy voice-tip templates, while keeping the source ASCII-only.
     */
    private const VOICE_TIPS_MOJIBAKE_PATTERN = '/['
        . '\x{704F}\x{700F}\x{940F}\x{5A43}\x{669A}\x{9428}\x{52ED}\x{6564}'
        . '\x{93B4}\x{51E4}\x{7D1D}\x{6D63}\x{72B3}\x{6E70}\x{5A06}\x{93C6}'
        . '\x{6C36}\x{60C3}\x{9355}\x{E85F}\x{668F}\x{95B9}\x{6751}\x{5696}'
        . '\x{7EF1}\x{6FC7}\x{62C5}\x{9418}\x{866B}\x{62F1}\x{6FDE}\x{55CF}'
        . '\x{20AC}\x{53C9}\x{5509}\x{95BA}\x{52EC}\x{6347}\x{9363}\x{70AC}'
        . '\x{5A75}\x{5470}\x{790B}'
        . ']/u';

    private function __construct()
    {
    }

    public static function normalizeVoiceTipsTemplate(mixed $value, string $defaultTemplate): string
    {
        $template = trim((string)$value);
        if ($template === '') {
            return $defaultTemplate;
        }

        $normalized = ApiResponse::normalizeText($template);
        if ($normalized === '') {
            return $defaultTemplate;
        }

        if (self::isBrokenVoiceTipsTemplate($template, $defaultTemplate)
            || self::isBrokenVoiceTipsTemplate($normalized, $defaultTemplate)
        ) {
            return $defaultTemplate;
        }

        return $normalized;
    }

    public static function isBrokenVoiceTipsTemplate(string $value, string $defaultTemplate): bool
    {
        $template = trim($value);
        if ($template === '') {
            return false;
        }

        $normalized = ApiResponse::normalizeText($template);
        if ($normalized === $defaultTemplate) {
            return true;
        }

        return self::looksLikeBrokenMoneyTemplate($template)
            || self::looksLikeBrokenMoneyTemplate($normalized);
    }

    private static function looksLikeBrokenMoneyTemplate(string $value): bool
    {
        return $value !== ''
            && str_contains($value, self::MONEY_PLACEHOLDER)
            && preg_match(self::VOICE_TIPS_MOJIBAKE_PATTERN, $value) === 1;
    }
}
