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

        $row = Db::table(BusinessTable::user('merchant'))
            ->leftJoin(BusinessTable::vip('vip'), 'merchant.vip_id', '=', 'vip.id')
            ->select(
                BusinessTable::column('user', 'id', 'merchant'),
                BusinessTable::column('user', 'username', 'merchant'),
                BusinessTable::column('user', 'email', 'merchant'),
                BusinessTable::column('user', 'mobile', 'merchant'),
                BusinessTable::column('user', 'wxpusher_uid', 'merchant'),
                BusinessTable::column('user', 'tg_chat_id', 'merchant'),
                BusinessTable::column('user', 'is_bindqq', 'merchant'),
                BusinessTable::column('user', 'qq_sid', 'merchant'),
                BusinessTable::column('user', 'is_bindwx', 'merchant'),
                BusinessTable::column('user', 'wx_sid', 'merchant'),
                BusinessTable::column('user', 'googlekey', 'merchant'),
                BusinessTable::column('user', 'is_realName', 'merchant'),
                BusinessTable::column('user', 'name', 'merchant'),
                BusinessTable::column('user', 'idCard', 'merchant'),
                BusinessTable::column('user', 'superior_id', 'merchant'),
                BusinessTable::column('user', 'money', 'merchant'),
                BusinessTable::column('user', 'vip_id', 'merchant'),
                BusinessTable::column('user', 'vip_time', 'merchant'),
                BusinessTable::column('user', 'feilv', 'merchant'),
                BusinessTable::column('user', 'user_key', 'merchant'),
                BusinessTable::column('user', 'create_time', 'merchant'),
                BusinessTable::column('user', 'is_frozen', 'merchant'),
                BusinessTable::column('user', 'frozen_reason', 'merchant'),
                BusinessTable::column('vip', 'name', 'vip') . ' as vip_name'
            )
            ->where('merchant.token', $token)
            ->first();

        return $row ? (array)$row : null;
    }
}
