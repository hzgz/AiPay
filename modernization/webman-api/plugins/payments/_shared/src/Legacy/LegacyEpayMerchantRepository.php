<?php

declare(strict_types=1);

namespace Plugins\Payments\Shared\Legacy;

use app\payment\PaymentPluginException;
use app\support\MerchantChannelMetadata;
use support\Db;

class LegacyEpayMerchantRepository
{
    public function findMerchant(int $merchantId): array
    {
        if ($merchantId <= 0) {
            throw PaymentPluginException::validation('pid is required');
        }

        $merchant = Db::table('ypay_user')
            ->select('id', 'username', 'user_key', 'is_frozen', 'vip_id', 'vip_time', 'feilv', 'money')
            ->where('id', $merchantId)
            ->first();

        if (!$merchant) {
            throw PaymentPluginException::notFound('merchant was not found');
        }

        $merchantArray = (array)$merchant;
        if ((int)($merchantArray['is_frozen'] ?? 0) !== 0) {
            throw PaymentPluginException::conflict('merchant account is frozen');
        }

        return $merchantArray;
    }

    public function latestPaylist(
        int $merchantId,
        ?string $type = 'epay',
        ?string $paymentMethodType = null,
        ?string $pluginCode = null
    ): ?array
    {
        $query = Db::table('ypay_paylist')
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

        $paylist = Db::table('ypay_paylist')
            ->select('id', 'user_id', 'type', 'status', 'name', 'url', 'pid', 'key', 'other', 'create_time')
            ->where('id', $paylistId)
            ->first();

        return $paylist ? (array)$paylist : null;
    }

    public function findBasicSettings(int $merchantId): array
    {
        $basic = Db::table('ypay_userbasic')
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
