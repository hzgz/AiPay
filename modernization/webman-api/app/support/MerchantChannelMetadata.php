<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

final class MerchantChannelMetadata
{
    private const SCHEMA = 'merchant_channel_v2';

    public static function pack(string $extra, array $meta = []): string
    {
        $extra = trim($extra);
        $normalizedMeta = self::normalizeMeta($meta);

        if ($normalizedMeta === []) {
            return $extra;
        }

        $encoded = json_encode([
            '_meta' => array_merge([
                'schema' => self::SCHEMA,
            ], $normalizedMeta),
            'extra' => $extra,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) && $encoded !== '' ? $encoded : $extra;
    }

    /**
     * @return array{extra: string, meta: array<string, string>}
     */
    public static function unpack(mixed $value): array
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return [
                'extra' => '',
                'meta' => [],
            ];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [
                'extra' => $raw,
                'meta' => [],
            ];
        }

        $meta = is_array($decoded['_meta'] ?? null) ? $decoded['_meta'] : [];
        if (trim((string)($meta['schema'] ?? '')) !== self::SCHEMA) {
            return [
                'extra' => $raw,
                'meta' => [],
            ];
        }

        return [
            'extra' => trim((string)($decoded['extra'] ?? '')),
            'meta' => self::normalizeMeta($meta),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function normalizeMeta(array $meta): array
    {
        $normalized = [];

        foreach ([
            'payment_method_type',
            'payment_method_label',
            'plugin_code',
            'plugin_name',
            'channel_type',
        ] as $field) {
            $value = trim((string)($meta[$field] ?? ''));
            if ($value !== '') {
                $normalized[$field] = $value;
            }
        }

        return $normalized;
    }
}
