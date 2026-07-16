<?php

declare(strict_types=1);

namespace app\support;

use support\Db;
use Webman\Http\Request;

final class MerchantFrontSession
{
    public static function resolveToken(Request $request): string
    {
        $candidates = [
            (string)$request->cookie('front_token', ''),
            (string)$request->header('x-front-token', ''),
            (string)$request->header('authorization', ''),
        ];

        foreach ($candidates as $candidate) {
            $token = trim(rawurldecode($candidate));
            if ($token === '') {
                continue;
            }

            if (stripos($token, 'Bearer ') === 0) {
                $token = trim(substr($token, 7));
            }

            if ($token !== '') {
                return $token;
            }
        }

        return '';
    }

    public static function current(Request $request): ?array
    {
        $token = self::resolveToken($request);
        if ($token === '') {
            return null;
        }

        $row = Db::table('ypay_user')
            ->leftJoin('ypay_vip', 'ypay_user.vip_id', '=', 'ypay_vip.id')
            ->select(
                'ypay_user.id',
                'ypay_user.username',
                'ypay_user.email',
                'ypay_user.mobile',
                'ypay_user.wxpusher_uid',
                'ypay_user.tg_chat_id',
                'ypay_user.is_bindqq',
                'ypay_user.qq_sid',
                'ypay_user.is_bindwx',
                'ypay_user.wx_sid',
                'ypay_user.googlekey',
                'ypay_user.is_realName',
                'ypay_user.name',
                'ypay_user.idCard',
                'ypay_user.superior_id',
                'ypay_user.money',
                'ypay_user.vip_id',
                'ypay_user.vip_time',
                'ypay_user.feilv',
                'ypay_user.user_key',
                'ypay_user.create_time',
                'ypay_user.is_frozen',
                'ypay_user.frozen_reason',
                'ypay_vip.name as vip_name'
            )
            ->where('ypay_user.token', $token)
            ->first();

        return $row ? (array)$row : null;
    }
}
