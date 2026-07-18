<?php

declare(strict_types=1);

namespace Plugins\Payments\Shared\Support;

use app\service\payment\PaymentPluginManager;
use app\support\BusinessTable;
use InvalidArgumentException;
use RuntimeException;
use support\Db;
use Throwable;

final class PaymentGatewayResolutionSupport
{
    public function resolve(array $payload): array
    {
        $this->assertMerchantReference($payload);

        $requestedPluginCode = $this->resolvePluginCode($payload);
        if ($requestedPluginCode !== '') {
            return [
                'code' => $requestedPluginCode,
                'resolution' => 'explicit_request',
                'detail' => $this->assertGatewayCapablePlugin($requestedPluginCode),
            ];
        }

        $inferredPluginCode = $this->inferPluginCodeFromPayload($payload);
        if ($inferredPluginCode !== '') {
            return [
                'code' => $inferredPluginCode,
                'resolution' => 'merchant_channel_inference',
                'detail' => $this->assertGatewayCapablePlugin($inferredPluginCode),
            ];
        }

        $gatewayPlugins = $this->gatewayCapablePlugins();
        if ($gatewayPlugins === []) {
            throw new InvalidArgumentException('当前没有可用的网关支付插件');
        }

        if (count($gatewayPlugins) > 1) {
            throw new InvalidArgumentException('当前启用了多个网关支付插件，请明确指定插件');
        }

        $pluginCode = trim((string)($gatewayPlugins[0]['code'] ?? ''));
        if ($pluginCode === '') {
            throw new RuntimeException('网关插件解析结果为空');
        }

        return [
            'code' => $pluginCode,
            'resolution' => 'implicit_single_gateway_plugin',
            'detail' => $this->assertGatewayCapablePlugin($pluginCode),
        ];
    }

    private function resolvePluginCode(array $payload): string
    {
        return strtolower(trim((string)($payload['plugin'] ?? $payload['plugin_code'] ?? '')));
    }

    private function inferPluginCodeFromPayload(array $payload): string
    {
        $accountId = (int)($payload['account_id'] ?? ($payload['channel_id'] ?? 0));
        if ($accountId > 0) {
            $account = $this->loadPaymentAccount($accountId);
            if ($account !== null && $this->isGatewayCapablePluginCode((string)($account['code'] ?? ''))) {
                return strtolower(trim((string)($account['code'] ?? '')));
            }
        }

        $poolId = (int)($payload['pool_id'] ?? ($payload['poll_id'] ?? 0));
        if ($poolId > 0) {
            $poolPluginCode = $this->inferPoolPluginCode($poolId);
            if ($poolPluginCode !== '') {
                return $poolPluginCode;
            }
        }

        $merchantId = (int)($payload['pid'] ?? 0);
        $paymentType = $this->normalizePaymentType((string)($payload['type'] ?? ''));
        if ($merchantId <= 0 || $paymentType === '') {
            return '';
        }

        $account = Db::table(BusinessTable::account())
            ->select('code')
            ->where('user_id', $merchantId)
            ->where('type', $paymentType)
            ->where('status', 1)
            ->where('is_status', 1)
            ->orderByDesc('id')
            ->first();

        if ($account !== null) {
            $pluginCode = strtolower(trim((string)((array)$account)['code']));
            if ($this->isGatewayCapablePluginCode($pluginCode)) {
                return $pluginCode;
            }
        }

        if ($this->epayProtocolPaylistExists($merchantId, $paymentType) && $this->isGatewayCapablePluginCode('legacy_epay')) {
            return 'legacy_epay';
        }

        return '';
    }

    private function assertMerchantReference(array $payload): void
    {
        if ($this->resolvePluginCode($payload) !== '') {
            return;
        }

        foreach (['account_id', 'channel_id', 'pool_id', 'poll_id'] as $field) {
            if ((int)($payload[$field] ?? 0) > 0) {
                return;
            }
        }

        if ((int)($payload['pid'] ?? 0) > 0) {
            return;
        }

        throw new InvalidArgumentException('商户 ID 不能为空');
    }

    private function inferPoolPluginCode(int $poolId): string
    {
        $row = Db::table(BusinessTable::poll_pool_item() . ' as item')
            ->join(BusinessTable::account() . ' as account', 'account.id', '=', 'item.account_id')
            ->select('account.code')
            ->where('item.pool_id', $poolId)
            ->where('account.status', 1)
            ->where('account.is_status', 1)
            ->orderBy('item.sort')
            ->orderByDesc('account.id')
            ->first();

        if ($row === null) {
            return '';
        }

        $pluginCode = strtolower(trim((string)((array)$row)['code']));

        return $this->isGatewayCapablePluginCode($pluginCode) ? $pluginCode : '';
    }

    private function loadPaymentAccount(int $accountId): ?array
    {
        if ($accountId <= 0) {
            return null;
        }

        $row = Db::table(BusinessTable::account())
            ->select('id', 'code', 'type', 'user_id', 'status', 'is_status')
            ->where('id', $accountId)
            ->first();

        return $row ? (array)$row : null;
    }

    private function epayProtocolPaylistExists(int $merchantId, string $paymentType): bool
    {
        if ($merchantId <= 0 || $paymentType === '') {
            return false;
        }

        return Db::table(BusinessTable::paylist())
            ->where('user_id', $merchantId)
            ->where('status', 1)
            ->where('type', 'epay')
            ->exists();
    }

    private function isGatewayCapablePluginCode(string $pluginCode): bool
    {
        $normalized = strtolower(trim($pluginCode));
        if ($normalized === '') {
            return false;
        }

        try {
            $detail = $this->assertGatewayCapablePlugin($normalized);
        } catch (Throwable) {
            return false;
        }

        return !empty($detail);
    }

    private function normalizePaymentType(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'alipay', 'alipay_official', 'alipay_bill', 'alipay_mck' => 'alipay',
            'wxpay', 'wxpay_v3' => 'wxpay',
            'qqpay' => 'qqpay',
            default => $normalized,
        };
    }

    private function assertGatewayCapablePlugin(string $pluginCode): array
    {
        $detail = $this->manager()->detail($pluginCode);
        $manifest = is_array($detail['manifest'] ?? null) ? $detail['manifest'] : [];
        $state = is_array($detail['state'] ?? null) ? $detail['state'] : [];
        $capabilities = array_map(
            static fn (mixed $value): string => strtolower(trim((string)$value)),
            is_array($manifest['capabilities'] ?? null) ? $manifest['capabilities'] : []
        );

        if (!in_array('create_order', $capabilities, true)) {
            throw new InvalidArgumentException("支付插件[$pluginCode]不支持网关下单");
        }

        if (!(bool)($state['installed'] ?? false)) {
            throw new InvalidArgumentException("支付插件[$pluginCode]未安装");
        }

        if (!(bool)($state['enabled'] ?? false)) {
            throw new InvalidArgumentException("支付插件[$pluginCode]已停用");
        }

        return $detail;
    }

    private function gatewayCapablePlugins(): array
    {
        $plugins = [];

        foreach ($this->manager()->all() as $plugin) {
            if (!is_array($plugin)) {
                continue;
            }

            $capabilities = array_map(
                static fn (mixed $value): string => strtolower(trim((string)$value)),
                is_array($plugin['capabilities'] ?? null) ? $plugin['capabilities'] : []
            );

            if (!in_array('create_order', $capabilities, true)) {
                continue;
            }

            if (!(bool)($plugin['installed'] ?? false) || !(bool)($plugin['enabled'] ?? false)) {
                continue;
            }

            $plugins[] = $plugin;
        }

        usort(
            $plugins,
            static fn (array $left, array $right): int => strcmp(
                strtolower(trim((string)($left['code'] ?? ''))),
                strtolower(trim((string)($right['code'] ?? '')))
            )
        );

        return $plugins;
    }

    private function manager(): PaymentPluginManager
    {
        return new PaymentPluginManager();
    }
}
