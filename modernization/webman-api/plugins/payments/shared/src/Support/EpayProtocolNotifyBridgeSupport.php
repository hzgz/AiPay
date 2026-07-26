<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace Plugins\Payments\Shared\Support;

final class EpayProtocolNotifyBridgeSupport
{
    public const PLUGIN_CODE = 'universal_epay';

    public static function resolution(bool $enabled): string
    {
        return $enabled ? 'fixed_protocol_binding' : 'fixed_protocol_binding_drain_mode';
    }

    public static function availability(bool $enabled): string
    {
        return $enabled ? 'enabled' : 'drain_only';
    }

    public static function securityContext(string $pluginCode, string $availability): array
    {
        return [
            'scope' => 'epay_protocol_notify_compatibility',
            'plugin' => $pluginCode,
            'availability' => $availability,
            'signature' => [
                'algorithm' => 'md5',
                'field' => 'sign',
                'secret_source' => 'upstream_paylist.key',
            ],
            'replay_protection' => [
                'strategy' => 'settlement_idempotency',
                'window_seconds' => null,
                'duplicate_response' => 'success_or_return_redirect',
            ],
        ];
    }
}
