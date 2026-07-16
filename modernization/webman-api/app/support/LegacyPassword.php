<?php

namespace app\support;

class LegacyPassword
{
    public static function hash(string $password): string
    {
        return substr(md5($password), 3, -3);
    }
}
