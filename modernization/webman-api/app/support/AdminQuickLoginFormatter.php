<?php

namespace app\support;

class AdminQuickLoginFormatter
{
    public static function format(array $config): array
    {
        $type = trim((string)($config['type'] ?? ''));
        $status = (int)($config['status'] ?? 0);
        $url = trim((string)($config['url'] ?? ''));
        $appid = trim((string)($config['appid'] ?? ''));
        $appkey = trim((string)($config['appkey'] ?? ''));
        $bindingConfigNames = array_values(array_map(
            'strval',
            (array)($config['binding_config_names'] ?? [])
        ));
        $bindingLabels = array_values(array_map(
            'strval',
            (array)($config['binding_labels'] ?? [])
        ));
        $bindingTexts = self::bindingTexts($bindingConfigNames, $bindingLabels);
        $hasAppid = $appid !== '';
        $hasAppkey = $appkey !== '';
        $enabled = $status === 1;

        return [
            'id' => (int)($config['id'] ?? 0),
            'type' => $type,
            'type_label' => self::typeLabel($type),
            'type_text' => self::typeText($type),
            'type_tag' => self::typeTag($type),
            'type_help' => self::typeHelp($type),
            'type_help_text' => self::typeHelpText($type),
            'status' => $status,
            'status_label' => $enabled ? 'Enabled' : 'Disabled',
            'status_text' => $enabled ? '启用' : '停用',
            'status_type' => $enabled ? 'success' : 'info',
            'name' => trim((string)($config['name'] ?? '')),
            'name_label' => self::nameLabel($config),
            'url' => $url,
            'url_link' => self::safeUrl($url),
            'appid_masked' => self::mask($appid, 4, 4),
            'appkey_masked' => self::mask($appkey, 4, 4),
            'has_appid' => $hasAppid,
            'has_appkey' => $hasAppkey,
            'credential_ready' => $hasAppid && $hasAppkey,
            'credential_summary' => self::credentialSummary($hasAppid, $hasAppkey),
            'credential_summary_text' => self::credentialSummaryText($hasAppid, $hasAppkey),
            'callback_path' => self::callbackPath($type),
            'create_time' => self::nullableString($config['create_time'] ?? null),
            'is_bound' => $bindingLabels !== [],
            'binding_count' => count($bindingLabels),
            'binding_config_names' => $bindingConfigNames,
            'binding_labels' => $bindingLabels,
            'binding_texts' => $bindingTexts,
            'binding_summary' => $bindingLabels === [] ? 'Not bound' : implode(', ', $bindingLabels),
            'binding_summary_text' => $bindingTexts === [] ? '未绑定' : implode('、', $bindingTexts),
        ];
    }

    private static function nameLabel(array $config): string
    {
        $name = trim((string)($config['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return self::defaultName(
            trim((string)($config['type'] ?? '')),
            (int)($config['id'] ?? 0)
        );
    }

    private static function typeLabel(string $type): string
    {
        return match ($type) {
            'qq' => 'QQ OAuth',
            'polymerization' => 'Aggregated OAuth',
            default => $type !== '' ? $type : 'Unknown Type',
        };
    }

    private static function typeText(string $type): string
    {
        return match ($type) {
            'qq' => 'QQ 登录',
            'polymerization' => '聚合登录',
            default => $type !== '' ? $type : '未知类型',
        };
    }

    private static function typeTag(string $type): string
    {
        return match ($type) {
            'qq' => 'primary',
            'polymerization' => 'success',
            default => 'info',
        };
    }

    private static function typeHelp(string $type): string
    {
        return match ($type) {
            'qq' => 'Official QQ OAuth callback integration.',
            'polymerization' => 'Aggregated third-party OAuth callback integration.',
            default => 'Custom quick-login adapter.',
        };
    }

    private static function typeHelpText(string $type): string
    {
        return match ($type) {
            'qq' => '官方 QQ OAuth 回调接入。',
            'polymerization' => '聚合第三方 OAuth 回调接入。',
            default => '自定义快捷登录适配器。',
        };
    }

    private static function credentialSummary(bool $hasAppid, bool $hasAppkey): string
    {
        if ($hasAppid && $hasAppkey) {
            return 'Credentials ready';
        }

        if ($hasAppid || $hasAppkey) {
            return 'Credentials incomplete';
        }

        return 'Credentials not configured';
    }

    private static function credentialSummaryText(bool $hasAppid, bool $hasAppkey): string
    {
        if ($hasAppid && $hasAppkey) {
            return '应用凭证已完整配置';
        }

        if ($hasAppid || $hasAppkey) {
            return '应用凭证尚未完整配置';
        }

        return '应用凭证未配置';
    }

    private static function defaultName(string $type, int $id): string
    {
        $base = match ($type) {
            'qq' => 'QQ 登录配置',
            'polymerization' => '聚合登录配置',
            default => '快捷登录配置',
        };

        return $id > 0 ? $base . ' #' . $id : $base;
    }

    /**
     * @param array<int, string> $bindingConfigNames
     * @param array<int, string> $bindingLabels
     * @return array<int, string>
     */
    private static function bindingTexts(array $bindingConfigNames, array $bindingLabels): array
    {
        if ($bindingConfigNames !== []) {
            return array_values(array_map(
                static fn(string $name): string => match ($name) {
                    'qq_login' => 'QQ 登录绑定',
                    'wechat_login' => '微信登录绑定',
                    default => $name,
                },
                $bindingConfigNames
            ));
        }

        return array_values(array_map(
            static fn(string $label): string => match (strtolower(trim($label))) {
                'qq login binding' => 'QQ 登录绑定',
                'wechat login binding' => '微信登录绑定',
                default => $label,
            },
            $bindingLabels
        ));
    }

    private static function callbackPath(string $type): ?string
    {
        return match ($type) {
            'qq' => '/Notify/qqcallback',
            'polymerization' => '/Notify/CallBack',
            default => null,
        };
    }

    private static function mask(string $value, int $head, int $tail): ?string
    {
        if ($value === '') {
            return null;
        }

        $length = mb_strlen($value);
        if ($length <= $head + $tail) {
            return str_repeat('*', max(4, $length));
        }

        return mb_substr($value, 0, $head)
            . str_repeat('*', 8)
            . mb_substr($value, $length - $tail);
    }

    private static function safeUrl(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return null;
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }
}
