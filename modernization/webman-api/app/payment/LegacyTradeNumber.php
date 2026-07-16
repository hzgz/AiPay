<?php

namespace app\payment;

class LegacyTradeNumber
{
    public static function make(string $prefix = 'Y'): string
    {
        return $prefix . date('YmdHis') . random_int(11111, 99999);
    }
}
