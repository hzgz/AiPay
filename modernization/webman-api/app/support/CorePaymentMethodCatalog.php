<?php

namespace app\support;

use support\Db;
use Throwable;

class CorePaymentMethodCatalog
{
    /**
     * @return array<int, array{name:string,type:string,sort:string,status:string}>
     */
    public static function defaults(): array
    {
        return [
            ['name' => '支付宝', 'type' => 'alipay', 'sort' => '100', 'status' => '1'],
            ['name' => '微信支付', 'type' => 'wxpay', 'sort' => '90', 'status' => '1'],
            ['name' => 'QQ钱包', 'type' => 'qqpay', 'sort' => '80', 'status' => '1'],
            ['name' => 'USDT', 'type' => 'usdt', 'sort' => '70', 'status' => '1'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function supportedTypes(): array
    {
        return array_values(array_map(
            static fn (array $item): string => (string)$item['type'],
            self::defaults()
        ));
    }

    public static function isCoreType(string $type): bool
    {
        return in_array(strtolower(trim($type)), self::supportedTypes(), true);
    }

    public static function seedWhenTableEmpty(): bool
    {
        try {
            $count = (int)Db::table('ypay_payment')->count('id');
        } catch (Throwable) {
            return false;
        }

        if ($count > 0) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $rows = array_map(static function (array $item) use ($now): array {
            return [
                'name' => $item['name'],
                'type' => $item['type'],
                'sort' => $item['sort'],
                'status' => $item['status'],
                'create_time' => $now,
                'update_time' => $now,
                'delete_time' => null,
            ];
        }, self::defaults());

        Db::table('ypay_payment')->insert($rows);

        return true;
    }
}
