<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace Plugins\Payments\Shared\EpayProtocol;

use app\support\BusinessTable;
use app\support\MerchantChannelMetadata;
use Plugins\Payments\Shared\Support\PaymentErrorMessageCatalog;
use Plugins\Payments\Shared\Support\PaymentPluginException;
use support\Db;

class EpayMerchantRepository
{
    public function findMerchant(int $merchantId): array
    {
        if ($merchantId <= 0) {
            throw PaymentPluginException::validation(PaymentErrorMessageCatalog::merchantIdRequired());
        }

        $merchant = Db::table(BusinessTable::user())
            ->select('id', 'username', 'user_key', 'is_frozen', 'vip_id', 'vip_time', 'feilv', 'money')
            ->where('id', $merchantId)
            ->first();

        if (!$merchant) {
            throw PaymentPluginException::notFound(PaymentErrorMessageCatalog::merchantNotFound());
        }

        $merchantArray = (array)$merchant;
        if ((int)($merchantArray['is_frozen'] ?? 0) !== 0) {
            throw PaymentPluginException::conflict(PaymentErrorMessageCatalog::merchantFrozen());
        }

        return $merchantArray;
    }

    /**
     * @return array{merchant: array<string, mixed>, basic: array<string, mixed>}
     */
    public function findMerchantBundle(int $merchantId): array
    {
        if ($merchantId <= 0) {
            throw PaymentPluginException::validation(PaymentErrorMessageCatalog::merchantIdRequired());
        }

        $record = Db::table(BusinessTable::user('merchant'))
            ->leftJoin(BusinessTable::userBasic('basic'), 'merchant.id', '=', 'basic.user_id')
            ->select(
                'merchant.id as merchant_id',
                'merchant.username as merchant_username',
                'merchant.user_key as merchant_user_key',
                'merchant.is_frozen as merchant_is_frozen',
                'merchant.vip_id as merchant_vip_id',
                'merchant.vip_time as merchant_vip_time',
                'merchant.feilv as merchant_feilv',
                'merchant.money as merchant_money',
                'basic.user_id as basic_user_id',
                'basic.timeout_time as basic_timeout_time',
                'basic.callback_hiddenName as basic_callback_hiddenName',
                'basic.order_tips as basic_order_tips',
                'basic.is_money_tips as basic_is_money_tips',
                'basic.money_tips as basic_money_tips'
            )
            ->where('merchant.id', $merchantId)
            ->first();

        if (!$record) {
            throw PaymentPluginException::notFound(PaymentErrorMessageCatalog::merchantNotFound());
        }

        $row = (array)$record;
        if ((int)($row['merchant_is_frozen'] ?? 0) !== 0) {
            throw PaymentPluginException::conflict(PaymentErrorMessageCatalog::merchantFrozen());
        }

        return [
            'merchant' => [
                'id' => (int)($row['merchant_id'] ?? 0),
                'username' => (string)($row['merchant_username'] ?? ''),
                'user_key' => (string)($row['merchant_user_key'] ?? ''),
                'is_frozen' => (int)($row['merchant_is_frozen'] ?? 0),
                'vip_id' => $row['merchant_vip_id'] ?? null,
                'vip_time' => $row['merchant_vip_time'] ?? null,
                'feilv' => $row['merchant_feilv'] ?? null,
                'money' => $row['merchant_money'] ?? null,
            ],
            'basic' => array_merge([
                'user_id' => $merchantId,
                'timeout_time' => '180',
                'callback_hiddenName' => 0,
                'order_tips' => '0',
                'is_money_tips' => '0',
                'money_tips' => '0',
            ], array_filter([
                'user_id' => isset($row['basic_user_id']) ? (int)$row['basic_user_id'] : null,
                'timeout_time' => isset($row['basic_timeout_time']) ? (string)$row['basic_timeout_time'] : null,
                'callback_hiddenName' => isset($row['basic_callback_hiddenName']) ? (int)$row['basic_callback_hiddenName'] : null,
                'order_tips' => isset($row['basic_order_tips']) ? (string)$row['basic_order_tips'] : null,
                'is_money_tips' => isset($row['basic_is_money_tips']) ? (string)$row['basic_is_money_tips'] : null,
                'money_tips' => isset($row['basic_money_tips']) ? (string)$row['basic_money_tips'] : null,
            ], static fn ($value): bool => $value !== null)),
        ];
    }

    public function latestPaylist(
        int $merchantId,
        ?string $type = 'epay',
        ?string $paymentMethodType = null,
        ?string $pluginCode = null
    ): ?array {
        $query = Db::table(BusinessTable::paylist())
            ->select('id', 'user_id', 'type', 'status', 'name', 'url', 'pid', 'key', 'other', 'create_time')
            ->where('user_id', $merchantId)
            ->where('status', 1);

        if ($type !== null && $type !== '') {
            $query->where('type', $type);
        }

        $rows = $query->orderByDesc('id')->get()->toArray();
        if ($rows === []) {
            return null;
        }

        $normalizedPaymentMethodType = strtolower(trim((string)$paymentMethodType));
        $normalizedPluginCode = strtolower(trim((string)$pluginCode));

        if ($normalizedPaymentMethodType === '' && $normalizedPluginCode === '') {
            return (array)$rows[0];
        }

        foreach ($rows as $row) {
            $record = (array)$row;
            $meta = MerchantChannelMetadata::unpack($record['other'] ?? '')['meta'];
            $rowMethodType = strtolower(trim((string)($meta['payment_method_type'] ?? '')));
            $rowPluginCode = strtolower(trim((string)($meta['plugin_code'] ?? '')));

            if ($normalizedPluginCode !== '' && $rowPluginCode !== $normalizedPluginCode) {
                continue;
            }

            if ($normalizedPaymentMethodType !== '' && $rowMethodType !== $normalizedPaymentMethodType) {
                continue;
            }

            return $record;
        }

        return (array)$rows[0];
    }

    public function findPaylistById(int $paylistId): ?array
    {
        if ($paylistId <= 0) {
            return null;
        }

        $paylist = Db::table(BusinessTable::paylist())
            ->select('id', 'user_id', 'type', 'status', 'name', 'url', 'pid', 'key', 'other', 'create_time')
            ->where('id', $paylistId)
            ->first();

        return $paylist ? (array)$paylist : null;
    }

    public function findBasicSettings(int $merchantId): array
    {
        $basic = Db::table(BusinessTable::userBasic())
            ->select('user_id', 'timeout_time', 'callback_hiddenName', 'order_tips', 'is_money_tips', 'money_tips')
            ->where('user_id', $merchantId)
            ->first();

        $defaults = [
            'user_id' => $merchantId,
            'timeout_time' => '180',
            'callback_hiddenName' => 0,
            'order_tips' => '0',
            'is_money_tips' => '0',
            'money_tips' => '0',
        ];

        if (!$basic) {
            return $defaults;
        }

        return array_merge($defaults, (array)$basic);
    }
}
