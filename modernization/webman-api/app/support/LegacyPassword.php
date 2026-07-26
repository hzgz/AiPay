<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

class LegacyPassword
{
    public static function hash(string $password): string
    {
        return substr(md5($password), 3, -3);
    }
}
