<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\support;

final class MerchantPortalReadOnlyGuard
{
    /**
     * @return array{code:int,message:string,status:int}
     */
    public static function response(string $scope): array
    {
        return match ($scope) {
            'ticket' => [
                'code' => 202,
                'message' => '当前工单列表页用于查询记录，新增或删除请在对应工单页面处理。',
                'status' => 405,
            ],
            'domain' => [
                'code' => 202,
                'message' => '当前域名列表页用于查询记录，新增、编辑或删除请在对应域名页面处理。',
                'status' => 405,
            ],
            'connections' => [
                'code' => 202,
                'message' => '当前绑定中心页用于查看接入状态，绑定、解绑、验证码或扫码请在对应页面处理。',
                'status' => 405,
            ],
            'security' => [
                'code' => 202,
                'message' => '当前安全中心页用于查看状态；密码修改、谷歌验证、实名认证和账号注销请在对应入口处理。',
                'status' => 405,
            ],
            'recharge' => [
                'code' => 202,
                'message' => '当前充值页用于查询记录，充值创建与支付跳转请在对应页面处理。',
                'status' => 405,
            ],
            'cdk' => [
                'code' => 202,
                'message' => '当前充值页暂未开放卡密兑换。',
                'status' => 405,
            ],
            'order' => [
                'code' => 202,
                'message' => '当前订单页已支持回调重放，状态重置暂未开放。',
                'status' => 405,
            ],
            'api_key' => [
                'code' => 202,
                'message' => '当前接口信息页用于查看接入信息，请使用签名密钥或通讯密钥重置入口处理变更。',
                'status' => 405,
            ],
            default => [
                'code' => 202,
                'message' => '当前页面用于查询或查看信息，写操作请使用对应的专用入口。',
                'status' => 405,
            ],
        };
    }
}
