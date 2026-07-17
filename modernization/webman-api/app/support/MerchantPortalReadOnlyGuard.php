<?php

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
                'message' => '当前工单列表页仅提供查询，请在对应工单页面完成新增或删除。',
                'status' => 405,
            ],
            'domain' => [
                'code' => 202,
                'message' => '当前域名列表页仅提供查询，请在对应域名页面完成新增、编辑或删除。',
                'status' => 405,
            ],
            'connections' => [
                'code' => 202,
                'message' => '当前绑定中心页仅提供查询，请在对应页面完成绑定、解绑、验证码或扫码操作。',
                'status' => 405,
            ],
            'security' => [
                'code' => 202,
                'message' => '当前安全中心页仅提供查询，请在对应页面完成密码修改、谷歌验证、实名认证或账号注销。',
                'status' => 405,
            ],
            'recharge' => [
                'code' => 202,
                'message' => '当前充值页仅提供查询，请在对应页面完成充值创建与支付跳转。',
                'status' => 405,
            ],
            'cdk' => [
                'code' => 202,
                'message' => '当前充值页不处理卡密兑换，请在卡密兑换页面完成操作。',
                'status' => 405,
            ],
            'order' => [
                'code' => 202,
                'message' => '当前商户中心仅保留订单回调重推，状态重置已下线。',
                'status' => 405,
            ],
            'api_key' => [
                'code' => 202,
                'message' => '当前接口信息页仅提供查询，请使用签名密钥或通讯密钥重置入口完成操作。',
                'status' => 405,
            ],
            default => [
                'code' => 202,
                'message' => '当前页面仅开放查询能力，请使用对应的专用入口完成写操作。',
                'status' => 405,
            ],
        };
    }
}
