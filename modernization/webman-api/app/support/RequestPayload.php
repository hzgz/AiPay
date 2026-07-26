<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

use Webman\Http\Request;

class RequestPayload
{
    public static function all(Request $request): array
    {
        $data = $request->post();
        if (!empty($data)) {
            return $data;
        }

        $rawBody = trim($request->rawBody());
        if ($rawBody === '') {
            return [];
        }

        $json = json_decode($rawBody, true);
        return is_array($json) ? $json : [];
    }
}
