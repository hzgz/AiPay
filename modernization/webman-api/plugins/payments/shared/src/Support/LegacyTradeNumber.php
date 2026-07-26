<?php

declare(strict_types=1);

namespace Plugins\Payments\Shared\Support;

class LegacyTradeNumber
{
    public static function make(string $prefix = 'Y'): string
    {
        return $prefix . date('YmdHis') . random_int(11111, 99999);
    }
}
