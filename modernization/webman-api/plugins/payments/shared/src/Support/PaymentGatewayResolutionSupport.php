<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace Plugins\Payments\Shared\Support;

use app\service\payment\PaymentPluginManager;
use app\support\BusinessTable;
use InvalidArgumentException;
use support\Db;

final class PaymentGatewayResolutionSupport
{
    private ?array $gatewayCapablePluginsCache = null;
    private ?array $gatewayCapableCodeMapCache = null;
    private array $pluginDetailCache = [];

    public function resolve(array $payload): array
    {
        $this->assertMerchantReference($payload);

        $requestedPluginCode = $this->resolvePluginCode($payload);
        if ($requestedPluginCode !== '') {
            return [
                'code' => $requestedPluginCode,
                'resolution' => 'explicit_request',
                'detail' => $this->assertGatewayCapablePlugin($requestedPluginCode),
                'account_id' => (int)($payload['account_id'] ?? ($payload['channel_id'] ?? 0)),
            ];
        }

        $selectedAccount = $this->selectAccountForPayload($payload);
        if ($selectedAccount !== null) {
            $pluginCode = strtolower(trim((string)($selectedAccount['code'] ?? '')));

            return [
                'code' => $pluginCode,
                'resolution' => (string)($selectedAccount['_resolution'] ?? 'merchant_account_selection'),
                'detail' => $this->assertGatewayCapablePlugin($pluginCode),
                'account_id' => (int)($selectedAccount['id'] ?? 0),
                'account' => [
                    'id' => (int)($selectedAccount['id'] ?? 0),
                    'user_id' => (int)($selectedAccount['user_id'] ?? 0),
                    'type' => (string)($selectedAccount['type'] ?? ''),
                    'code' => $pluginCode,
                ],
            ];
        }

        $paymentType = $this->normalizePaymentType((string)($payload['type'] ?? ''));
        if ($paymentType === '') {
            throw new InvalidArgumentException(PaymentErrorMessageCatalog::paymentTypeRequired());
        }

        if ($this->gatewayCapablePlugins() === []) {
            throw new InvalidArgumentException(PaymentErrorMessageCatalog::noGatewayPluginAvailable());
        }

        $merchantId = (int)($payload['pid'] ?? 0);
        if ($merchantId > 0) {
            throw new InvalidArgumentException(PaymentErrorMessageCatalog::merchantNoChannel($paymentType));
        }

        throw new InvalidArgumentException(PaymentErrorMessageCatalog::requestChannelUnavailable());
    }

    private function resolvePluginCode(array $payload): string
    {
        return strtolower(trim((string)($payload['plugin'] ?? $payload['plugin_code'] ?? '')));
    }

    private function selectAccountForPayload(array $payload): ?array
    {
        $merchantId = (int)($payload['pid'] ?? 0);
        $paymentType = $this->normalizePaymentType((string)($payload['type'] ?? ''));

        $accountId = (int)($payload['account_id'] ?? ($payload['channel_id'] ?? 0));
        if ($accountId > 0) {
            return $this->loadSelectableAccount($accountId, $merchantId, $paymentType, 'explicit_account');
        }

        $poolId = (int)($payload['pool_id'] ?? ($payload['poll_id'] ?? 0));
        if ($poolId > 0) {
            return $this->selectAccountFromPool($poolId, $merchantId, $paymentType);
        }

        if ($merchantId <= 0 || $paymentType === '') {
            return null;
        }

        return $this->selectRandomMerchantAccount($merchantId, $paymentType);
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

        throw new InvalidArgumentException(PaymentErrorMessageCatalog::merchantIdRequired());
    }

    private function loadSelectableAccount(
        int $accountId,
        int $merchantId,
        string $paymentType,
        string $resolution
    ): array {
        $row = Db::table(BusinessTable::account())
            ->select('id', 'user_id', 'code', 'type', 'status', 'is_status')
            ->where('id', $accountId)
            ->first();

        if ($row === null) {
            throw new InvalidArgumentException(PaymentErrorMessageCatalog::accountNotFound());
        }

        $account = (array)$row;
        $accountMerchantId = (int)($account['user_id'] ?? 0);
        if ($merchantId > 0 && $accountMerchantId !== $merchantId) {
            throw new InvalidArgumentException(PaymentErrorMessageCatalog::accountMerchantMismatch());
        }

        $accountType = $this->normalizePaymentType((string)($account['type'] ?? ''));
        if ($paymentType !== '' && $accountType !== $paymentType) {
            throw new InvalidArgumentException(PaymentErrorMessageCatalog::accountTypeMismatch());
        }

        if ((int)($account['status'] ?? 0) !== 1 || (int)($account['is_status'] ?? 0) !== 1) {
            throw new InvalidArgumentException(PaymentErrorMessageCatalog::accountDisabled());
        }

        $account['_resolution'] = $resolution;

        return $account;
    }

    private function selectAccountFromPool(int $poolId, int $merchantId, string $paymentType): ?array
    {
        if ($poolId <= 0) {
            return null;
        }

        return Db::transaction(function () use ($poolId, $merchantId, $paymentType): array {
            $query = Db::table(BusinessTable::pollPool())
                ->select('id', 'user_id', 'type', 'status', 'round_type', 'current_index', 'current_weight', 'last_account_id')
                ->where('id', $poolId)
                ->lockForUpdate();

            if ($merchantId > 0) {
                $query->where('user_id', $merchantId);
            }

            $pool = $query->first();
            if ($pool === null) {
                throw new InvalidArgumentException(PaymentErrorMessageCatalog::poolNotFound());
            }

            $poolRecord = (array)$pool;
            if ((int)($poolRecord['status'] ?? 0) !== 1) {
                throw new InvalidArgumentException(PaymentErrorMessageCatalog::poolDisabled());
            }

            $resolvedMerchantId = (int)($poolRecord['user_id'] ?? 0);
            $resolvedType = $this->normalizePaymentType((string)($poolRecord['type'] ?? ''));
            if ($paymentType !== '' && $resolvedType !== $paymentType) {
                throw new InvalidArgumentException(PaymentErrorMessageCatalog::poolTypeMismatch());
            }

            $allowedPluginCodes = array_keys($this->gatewayCapableCodeMap());
            if ($allowedPluginCodes === []) {
                throw new InvalidArgumentException(PaymentErrorMessageCatalog::poolNoChannel($resolvedType));
            }

            $rows = Db::table(BusinessTable::pollPoolItem('item'))
                ->join(BusinessTable::account('account'), 'account.id', '=', 'item.account_id')
                ->select(
                    'item.account_id',
                    'item.weight',
                    'item.sort',
                    'account.id',
                    'account.user_id',
                    'account.code',
                    'account.type',
                    'account.status',
                    'account.is_status'
                )
                ->where('item.pool_id', $poolId)
                ->where('account.user_id', $resolvedMerchantId)
                ->where('account.type', $resolvedType)
                ->where('account.status', 1)
                ->where('account.is_status', 1)
                ->whereIn('account.code', $allowedPluginCodes)
                ->orderBy('item.sort')
                ->orderByDesc('account.id')
                ->get()
                ->toArray();

            $accounts = $this->filterGatewayCapableAccounts(array_map(
                static fn ($row): array => (array)$row,
                $rows
            ));

            if ($accounts === []) {
                throw new InvalidArgumentException(PaymentErrorMessageCatalog::poolNoChannel($resolvedType));
            }

            $selectedIndex = 0;
            if ((int)($poolRecord['round_type'] ?? 1) === 2) {
                $totalWeight = 0;
                foreach ($accounts as $account) {
                    $totalWeight += max(1, (int)($account['weight'] ?? 1));
                }

                $ticket = random_int(1, max(1, $totalWeight));
                foreach ($accounts as $index => $account) {
                    $ticket -= max(1, (int)($account['weight'] ?? 1));
                    if ($ticket <= 0) {
                        $selectedIndex = $index;
                        break;
                    }
                }
            } else {
                $currentIndex = max(0, (int)($poolRecord['current_index'] ?? 0));
                $selectedIndex = $currentIndex % count($accounts);
            }

            $selected = $accounts[$selectedIndex];

            Db::table(BusinessTable::pollPool())
                ->where('id', $poolId)
                ->update([
                    'current_index' => (($selectedIndex + 1) % max(1, count($accounts))),
                    'current_weight' => max(1, (int)($selected['weight'] ?? 1)),
                    'last_account_id' => (int)($selected['id'] ?? 0),
                    'update_time' => date('Y-m-d H:i:s'),
                ]);

            $selected['_resolution'] = 'merchant_pool_selection';

            return $selected;
        });
    }

    private function selectRandomMerchantAccount(int $merchantId, string $paymentType): ?array
    {
        $allowedPluginCodes = array_keys($this->gatewayCapableCodeMap());
        if ($allowedPluginCodes === []) {
            return null;
        }

        $rows = Db::table(BusinessTable::account())
            ->select('id', 'user_id', 'code', 'type', 'status', 'is_status')
            ->where('user_id', $merchantId)
            ->where('type', $paymentType)
            ->where('status', 1)
            ->where('is_status', 1)
            ->whereIn('code', $allowedPluginCodes)
            ->get()
            ->toArray();

        $accounts = $this->filterGatewayCapableAccounts(array_map(
            static fn ($row): array => (array)$row,
            $rows
        ));

        if ($accounts === []) {
            return null;
        }

        $selected = $accounts[array_rand($accounts)];
        $selected['_resolution'] = count($accounts) === 1
            ? 'merchant_single_account'
            : 'merchant_random_account';

        return $selected;
    }

    /**
     * @param array<int, array<string, mixed>> $accounts
     * @return array<int, array<string, mixed>>
     */
    private function filterGatewayCapableAccounts(array $accounts): array
    {
        $allowedCodes = $this->gatewayCapableCodeMap();

        return array_values(array_filter(
            $accounts,
            static fn (array $account): bool => isset($allowedCodes[strtolower(trim((string)($account['code'] ?? '')))])
        ));
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
        $normalizedCode = strtolower(trim($pluginCode));
        if (isset($this->pluginDetailCache[$normalizedCode])) {
            return $this->pluginDetailCache[$normalizedCode];
        }

        $detail = $this->manager()->detail($normalizedCode);
        $manifest = is_array($detail['manifest'] ?? null) ? $detail['manifest'] : [];
        $state = is_array($detail['state'] ?? null) ? $detail['state'] : [];
        $capabilities = array_map(
            static fn (mixed $value): string => strtolower(trim((string)$value)),
            is_array($manifest['capabilities'] ?? null) ? $manifest['capabilities'] : []
        );

        if (!in_array('create_order', $capabilities, true)) {
            throw new InvalidArgumentException(PaymentErrorMessageCatalog::pluginDoesNotSupportGateway($pluginCode));
        }

        if (!(bool)($state['installed'] ?? false)) {
            throw new InvalidArgumentException(PaymentErrorMessageCatalog::pluginNotInstalled($pluginCode));
        }

        if (!(bool)($state['enabled'] ?? false)) {
            throw new InvalidArgumentException(PaymentErrorMessageCatalog::pluginDisabled($pluginCode));
        }

        return $this->pluginDetailCache[$normalizedCode] = $detail;
    }

    private function gatewayCapablePlugins(): array
    {
        if (is_array($this->gatewayCapablePluginsCache)) {
            return $this->gatewayCapablePluginsCache;
        }

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

        $this->gatewayCapablePluginsCache = $plugins;
        $this->gatewayCapableCodeMapCache = [];
        foreach ($plugins as $plugin) {
            $code = strtolower(trim((string)($plugin['code'] ?? '')));
            if ($code !== '') {
                $this->gatewayCapableCodeMapCache[$code] = true;
            }
        }

        return $this->gatewayCapablePluginsCache;
    }

    private function gatewayCapableCodeMap(): array
    {
        if (!is_array($this->gatewayCapableCodeMapCache)) {
            $this->gatewayCapablePlugins();
        }

        return is_array($this->gatewayCapableCodeMapCache) ? $this->gatewayCapableCodeMapCache : [];
    }

    private function manager(): PaymentPluginManager
    {
        return new PaymentPluginManager();
    }
}
