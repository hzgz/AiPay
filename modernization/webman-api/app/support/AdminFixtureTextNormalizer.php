<?php

declare(strict_types=1);

namespace app\support;

class AdminFixtureTextNormalizer
{
    private const EXACT_MAP = [
        'safe dependent update' => '安全依赖更新示例',
        'plugin managed fixture' => '插件托管示例',
        'batch delete fixture a' => '批量删除示例A',
        'batch delete fixture b' => '批量删除示例B',
        'batch restore fixture a' => '批量恢复示例A',
        'batch restore fixture b' => '批量恢复示例B',
        'single restore fixture' => '单条恢复示例',
        'created from smoke test' => '由示例数据创建',
        'updated from smoke test' => '由示例数据更新',
        'smoke ticket' => '工单示例',
        'merchant batch delete smoke' => '批量删除示例商户',
        'blocked merchant batch delete smoke' => '批量删除示例商户（阻塞样例）',
        'deletable merchant batch delete smoke' => '批量删除示例商户（可删样例）',
        'merchant batch delete smoke subordinate' => '批量删除示例商户子项',
        'legacy_epay' => '易支付网关插件',
        'legacy_epay_fee_deduct' => '易支付手续费扣减',
        'legacy epay compatibility' => '易支付网关插件',
        'compatibility wrapper for the legacy payment flow during the thinkphp to webman migration.' => '用于接入易支付网关模式的插件。',
        'legacy smoke upstream' => '易支付支付通道',
        'aipay modernization' => 'AiPay官方',
        'smokeapimapi' => '接口单笔支付示例',
        'smokepayapisubmit' => '支付接口提交示例',
        'smokeapipayment' => '接口支付示例',
        'smokemapi' => '移动端接口示例',
        'smokepaysubmit' => '支付提交流程示例',
        'smokesubmit' => '提交支付示例',
        'qqpay_software' => 'QQ 软件通道',
        'alipay_mck' => '支付宝免挂机通道',
        'qqpay_mg' => 'QQ 免挂机通道',
        'wxpay_software' => '微信软件通道',
        'wxpay_v3' => '微信官方 V3 接口',
        'jiaofeiyi_wxpay' => '缴费易微信',
        'jiaofeiyi_alipay' => '缴费易支付宝',
        'homepage theme' => '站点首页模板',
        'member center theme' => '会员中心模板',
        'payment page theme' => '支付页面模板',
        'demo page theme' => '演示页面模板',
        'document page theme' => '文档页面模板',
        'announcement page theme' => '公告页面模板',
        'platform announcement' => '平台公告',
        'industry news' => '行业资讯',
        'faq' => '常见问题',
        'new window' => '新窗口打开',
        'same window' => '当前窗口打开',
        '/doc' => '文档中心',
        '/demo' => '演示中心',
        '/news/index' => '公告页面',
        '/admin.photo/list/name/images' => '旧版图片目录接口',
        '/admin.photo/list/name/news' => '公告图片目录接口',
        '/admin.photo/list/name/plugins' => '插件素材目录接口',
        '/admin.photo/list/name/qrcode' => '二维码目录接口',
        '/admin.photo/list/name/pay_qr' => '支付二维码目录接口',
        '/admin.photo/list/name/merchant_assets' => '商户素材目录接口',
        '/ypay.shop/clear' => '旧版数据清理页',
        '/ypay.shop/clearOrder' => '旧版订单清理接口',
        '/ypay.shop/clearRecharge' => '旧版充值清理接口',
        '/ypay.shop/clearAdminLog' => '旧版管理员日志清理接口',
        '/ypay.shop/clearUserLog' => '旧版商户日志清理接口',
        'ypay_order' => '订单表',
        'ypay_recharge' => '充值记录表',
        'admin_admin_log' => '管理员日志表',
        'admin_front_log' => '商户日志表',
        'active' => '已启用',
        'available' => '可使用',
        'enabled' => '已启用',
        'disabled' => '已停用',
        'recycled' => '回收站',
        'missing style' => '缺少样式文件',
        'missing screenshot' => '缺少预览图',
        'metadata ready' => '元数据完整',
        'metadata incomplete' => '元数据不完整',
        'config ready' => '配置已就绪',
        'config missing' => '缺少系统配置',
        'using default value' => '当前使用默认配置',
        'no config mapping' => '未接入系统配置',
        'shop' => '商城',
        'cdk' => '卡券',
        '网站bug' => '网站问题',
        'shield key' => '风控密钥',
        'admin' => '本地管理员账号',
        'api' => '接口',
        'tag' => '标签',
        'alipay' => '支付宝',
        'wxpay' => '微信支付',
        'wechat' => '微信支付',
        'qqpay' => 'QQ 钱包',
        'epay_ali' => '易支付支付宝',
        'epay_wechat' => '易支付微信',
        'accesskey' => '访问密钥',
        'accesskey id' => '访问密钥 ID',
        'accesskey secret' => '访问密钥密文',
        'secretkey' => '访问密钥密文',
        'smoke pay theme' => '支付模板示例',
        'smoke home theme' => '首页模板示例',
        'theme delete' => '模板删除',
        'theme activate' => '模板启用',
        'permission create' => '权限创建',
        'permission update' => '权限更新',
        'permission delete' => '权限删除',
        'permission status' => '权限状态变更',
        'domain recycle smoke fixture' => '域名回收示例',
        'news-editor-upload' => '公告编辑器上传图片',
        'plugin-editor-upload' => '插件编辑器上传图片',
        'news-editor-upload.png' => '公告编辑器上传图片',
        'plugin-editor-upload.png' => '插件编辑器上传图片',
        'rsa 私钥' => '站点私钥',
        'think 验证码密钥' => '验证码密钥',
        '短信宝 api' => '短信宝接口密钥',
        '支付宝 rsa 公钥' => '支付宝公钥',
        '域名联调示例-blocked.example.com' => '黑名单域名示例',
        '域名联调示例-create.example.com' => '白名单域名示例',
        'index99' => '经典支付风格首页',
        'home_temp' => '首页模板配置键',
    ];

    private const PAYLOAD_KEY_MAP = [
        'merchant_id' => '商户编号',
        'merchant_username' => '商户账号',
        'merchant_name' => '商户名称',
        'merchant_display' => '商户显示名',
        'user_id' => '用户编号',
        'username' => '账号',
        'nickname' => '昵称',
        'name' => '名称',
        'email' => '邮箱',
        'mobile' => '手机号',
        'type' => '类型',
        'rtype' => '收支类型',
        'tag' => '标签',
        'channel' => '通道',
        'channel_name' => '通道名称',
        'channel_code' => '通道编码',
        'payment_type' => '支付类型',
        'payment_method' => '支付方式',
        'out_trade_no' => '商户单号',
        'order_no' => '订单号',
        'code' => '编码',
        'status' => '状态',
        'reason' => '原因',
        'remark' => '说明',
        'remarks' => '备注',
        'memo' => '备注',
        'account_id' => '收款账号编号',
        'pool_id' => '轮询池编号',
        'pool_name' => '轮询池名称',
        'qrcode' => '二维码',
        'qrcode_url' => '二维码地址',
        'siteurl' => '站点地址',
        'domain' => '域名',
        'url' => '地址',
        'return_url' => '返回地址',
        'notify_url' => '通知地址',
        'api' => '接口',
        'shield_key' => '风控密钥',
        'access_key' => '访问密钥',
        'accesskey' => '访问密钥',
        'access_key_id' => '访问密钥ID',
        'accesskeyid' => '访问密钥ID',
        'access_key_secret' => '访问密钥密文',
        'accesskeysecret' => '访问密钥密文',
        'secretkey' => '访问密钥密文',
        'order_tips' => '订单通知方式',
        'is_money_tips' => '余额提醒方式',
        'money_tips' => '余额提醒阈值',
    ];

    public static function normalizeNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = self::normalize($value);
        return $normalized === '' ? null : $normalized;
    }

    public static function normalize(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        $normalized = str_replace(['rsa ˽Կ', 'RSA ˽Կ'], ['rsa 私钥', 'RSA 私钥'], $normalized);

        $lower = strtolower($normalized);
        if (isset(self::EXACT_MAP[$lower])) {
            return self::applyInlineReplacements(self::EXACT_MAP[$lower]);
        }

        $result = match (true) {
            preg_match('/^cdepend_[a-f0-9]+$/i', $normalized) === 1 => '依赖通道示例',
            preg_match('/^cplugin_[a-f0-9]+$/i', $normalized) === 1 => '插件通道示例',
            preg_match('/^cbatcha_[a-f0-9]+$/i', $normalized) === 1 => '批量通道示例A',
            preg_match('/^cbatchb_[a-f0-9]+$/i', $normalized) === 1 => '批量通道示例B',
            preg_match('/^ccreate_[a-f0-9]+$/i', $normalized) === 1 => '创建示例通道',
            preg_match('/^cupdate_[a-f0-9]+$/i', $normalized) === 1 => '更新示例通道',
            preg_match('/^dependent channel updated [a-f0-9]+$/i', $normalized) === 1 => '依赖通道已更新',
            preg_match('/^plugin channel [a-f0-9]+$/i', $normalized) === 1 => '插件通道示例',
            preg_match('/^batch channel a [a-f0-9]+$/i', $normalized) === 1 => '批量通道示例A',
            preg_match('/^batch channel b [a-f0-9]+$/i', $normalized) === 1 => '批量通道示例B',
            preg_match('/^smoke updated channel [a-f0-9]+$/i', $normalized) === 1 => '示例更新通道',
            preg_match('/^smoke local channel [a-f0-9]+$/i', $normalized) === 1 => '本地示例通道',
            preg_match('/^legacy_epay_smoke_[a-z0-9_]+$/i', $normalized) === 1 => '易支付支付通道',
            preg_match('/^smoke_account_[a-z0-9]+$/i', $normalized) === 1 => '收款账号示例',
            preg_match('/^merchant_batch_delete_smoke_[a-z0-9_]+$/i', $normalized) === 1 => '批量删除示例商户',
            preg_match('/^merchant batch delete pool [a-z0-9_]+$/i', $normalized) === 1 => '批量删除示例轮询池',
            preg_match('/^linked ticket for ticket_category_write_smoke_[a-z0-9_]+$/i', $normalized) === 1 => '已关联分类工单示例',
            preg_match('/^ticket_category_[a-z0-9_]+$/i', $normalized) === 1 => '工单分类示例',
            preg_match('/^ticket_category_[a-z0-9_]+_linked$/i', $normalized) === 1 => '已关联分类示例',
            preg_match('/^linked content for ticket_category_write_smoke_[a-z0-9_]+$/i', $lower) === 1 => '已关联分类内容示例',
            preg_match('/^les_[a-z0-9_]+$/i', $normalized) === 1 => '示例商户单号',
            preg_match('/^risk-[a-z0-9]+\.example\.com$/i', $normalized) === 1 => '风控示例域名',
            preg_match('/^plugin_download_recycle_smoke_/i', $normalized) === 1 => '插件回收示例',
            preg_match('/^channel_catalog_write_smoke_[a-z0-9_]+$/i', $normalized) === 1 => '本地通道示例',
            preg_match('/^batch payment method a [a-f0-9]+$/i', $normalized) === 1 => '批量支付方式A',
            preg_match('/^batch payment method b [a-f0-9]+$/i', $normalized) === 1 => '批量支付方式B',
            preg_match('/^smoke payment method [a-f0-9]+$/i', $normalized) === 1 => '支付方式示例',
            preg_match('/^channel smoke account [a-z0-9]+$/i', $normalized) === 1 => '收款账号示例',
            preg_match('/^dependent pool [a-z0-9_]+$/i', $normalized) === 1 => '轮询池示例',
            preg_match('/^ui\s+\?+$/i', $normalized) === 1 => '界面示例',
            preg_match('/^vip_sort_smoke_[a-z0-9_]+$/i', $normalized) === 1 => '会员排序示例',
            preg_match('/^cdk_smoke_vip_[a-z0-9_]+$/i', $normalized) === 1 => '卡券示例会员',
            preg_match('/^recharge_[a-z0-9_]+$/i', $normalized) === 1 => '充值示例记录',
            preg_match('/^recharge_read_[a-z0-9_]+$/i', $normalized) === 1 => '充值示例账号',
            preg_match('/^domain_(write|audit|delete|recycle)_smoke_[a-z0-9_]+$/i', $normalized) === 1 => '域名示例',
            preg_match('/^merchant_impersonation_smoke_[a-z0-9_]+$/i', $normalized) === 1 => '商户代登示例',
            preg_match('/^[0-9a-f]{10,}@example\.test$/i', $normalized) === 1 => '示例邮箱',
            preg_match('/^news-editor-upload(?:-\d+)?\.(png|jpg|jpeg|webp|gif)$/i', $normalized) === 1 => '公告编辑器上传图片',
            preg_match('/^plugin-editor-upload(?:-\d+)?\.(png|jpg|jpeg|webp|gif)$/i', $normalized) === 1 => '插件编辑器上传图片',
            preg_match('/^[0-9a-f]{20,}\.(png|jpg|jpeg|webp|gif)$/i', $normalized) === 1 => '系统素材图片',
            default => str_replace(
                ['example.test', 'AiPay Smoke', 'Purple', 'Puple'],
                ['示例邮箱', 'AiPay 演示站', '紫色主题', '紫色主题'],
                $normalized
            ),
        };

        return self::applyInlineReplacements($result);
    }

    public static function normalizePayload(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::normalize($value);
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalizedKey = is_string($key) ? self::normalizePayloadKey($key) : $key;
                $normalized[$normalizedKey] = self::normalizePayload($item);
            }

            return $normalized;
        }

        return $value;
    }

    public static function normalizePayloadKey(string $key): string
    {
        $trimmed = trim($key);
        if ($trimmed === '') {
            return $trimmed;
        }

        $lookup = self::payloadLookupKey($trimmed);
        return self::PAYLOAD_KEY_MAP[$lookup] ?? $trimmed;
    }

    public static function normalizeUrlPreview(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        if (preg_match('/risk-[a-z0-9]+\.example\.com/i', $normalized) === 1) {
            return '风控示例地址';
        }

        if (str_contains(strtolower($normalized), 'example.test')) {
            return '示例地址';
        }

        if (preg_match('/example\.com/i', $normalized) === 1 && str_contains(strtolower($normalized), 'smoke')) {
            return '示例地址';
        }

        return self::applyInlineReplacements($normalized);
    }

    private static function applyInlineReplacements(string $value): string
    {
        $result = str_replace(
            [
                'System Auth',
                'Payment Auth',
                'Content Auth',
                'Menu Smoke',
                'Role Smoke',
                'Channel Catalog Auth',
                'Admin Log Cleanup Operator',
                'Admin Batch Target A',
                'Admin Batch Target B',
                'Batch Payment Method A',
                'Batch Payment Method B',
                'Smoke Payment Method',
                'Dependent Pool',
                'Dependent Channel Updated',
                'channel smoke account',
                'merchant impersonation smoke',
                'merchant impersonation',
                'Manual audit rejected',
                'legacy rejection reason',
                'legacy reason',
                'report Tips',
                'domain black',
                'domain white',
                'theme delete',
                'theme activate',
                'permission create',
                'permission update',
                'permission delete',
                'permission status',
                'Smoke Pay Theme',
                'domain recycle smoke fixture',
                'shield key',
                'AccessKey Secret',
                'AccessKey ID',
                'AccessKey',
                'SecretKey',
                'news-editor-upload',
                'plugin-editor-upload',
                '/admin.photo/list/name/images',
                '/admin.photo/list/name/news',
                '/admin.photo/list/name/plugins',
                '/admin.photo/list/name/qrcode',
                '/admin.photo/list/name/pay_qr',
                '/admin.photo/list/name/merchant_assets',
            ],
            [
                '系统权限示例',
                '支付权限示例',
                '内容权限示例',
                '菜单示例',
                '角色示例',
                '本地通道示例',
                '日志清理示例账号',
                '管理员批量示例目标A',
                '管理员批量示例目标B',
                '批量支付方式A',
                '批量支付方式B',
                '支付方式示例',
                '依赖轮询池',
                '依赖通道已更新',
                '收款账号示例',
                '商户代登示例',
                '商户代登',
                '人工审核驳回',
                '旧版驳回原因',
                '旧版原因',
                '举报提示',
                '域名黑名单',
                '域名白名单',
                '模板删除',
                '模板启用',
                '权限创建',
                '权限更新',
                '权限删除',
                '权限状态变更',
                '支付模板示例',
                '域名回收示例',
                '风控密钥',
                '访问密钥密文',
                '访问密钥 ID',
                '访问密钥',
                '访问密钥密文',
                '公告编辑器上传图片',
                '插件编辑器上传图片',
                '旧版图片目录接口',
                '公告图片目录接口',
                '插件素材目录接口',
                '二维码目录接口',
                '支付二维码目录接口',
                '商户素材目录接口',
            ],
            $value
        );

        $result = self::inlinePatternReplace($result, '/\bsysa_[a-z0-9]+\b/i', '系统权限账号');
        $result = self::inlinePatternReplace($result, '/\bpaya_[a-z0-9]+\b/i', '支付权限账号');
        $result = self::inlinePatternReplace($result, '/\bcta_[a-z0-9]+\b/i', '内容权限账号');
        $result = self::inlinePatternReplace($result, '/\bmenu_[a-z0-9]+\b/i', '菜单示例账号');
        $result = self::inlinePatternReplace($result, '/\brole_[a-z0-9]+\b/i', '角色示例账号');
        $result = self::inlinePatternReplace($result, '/\blog_[a-z0-9]+\b/i', '日志示例账号');
        $result = self::inlinePatternReplace($result, '/\badm_batch_[ab]_[a-z0-9]+\b/i', '管理员批量示例账号');
        $result = self::inlinePatternReplace($result, '/\bAdmin Smoke Target\b/i', '管理员示例目标');
        $result = self::inlinePatternReplace($result, '/\bAdmin Smoke Operator\b/i', '管理员示例操作员');
        $result = self::inlinePatternReplace($result, '/\bPlugin Auth\b/i', '插件权限示例');
        $result = self::inlinePatternReplace($result, '/\brole create role_id=/i', '角色创建 角色编号=');
        $result = self::inlinePatternReplace($result, '/\brole update role_id=/i', '角色更新 角色编号=');
        $result = self::inlinePatternReplace($result, '/\brole permissions role_id=/i', '角色授权 角色编号=');
        $result = self::inlinePatternReplace($result, '/\brole delete role_id=/i', '角色删除 角色编号=');
        $result = self::inlinePatternReplace($result, '/\bpermission create permission_id=/i', '菜单创建 菜单编号=');
        $result = self::inlinePatternReplace($result, '/\bpermission update permission_id=/i', '菜单更新 菜单编号=');
        $result = self::inlinePatternReplace($result, '/\bpermission reorder parent_id=/i', '菜单排序 上级编号=');
        $result = self::inlinePatternReplace($result, '/\bpermission status permission_id=/i', '菜单状态 菜单编号=');
        $result = self::inlinePatternReplace($result, '/\bpermission delete permission_id=/i', '菜单删除 菜单编号=');
        $result = self::inlinePatternReplace($result, '/\bDELETE ROLE\s+(\d+)\b/i', '删除角色 $1');
        $result = self::inlinePatternReplace($result, '/\bDELETE MENU TREE\s+(\d+)\b/i', '删除菜单树 $1');
        $result = self::inlinePatternReplace($result, '/\bDELETE MENU\s+(\d+)\b/i', '删除菜单 $1');
        $result = self::inlinePatternReplace($result, '/\brole_id=/i', '角色编号=');
        $result = self::inlinePatternReplace($result, '/\bpermission_id=/i', '菜单编号=');
        $result = self::inlinePatternReplace($result, '/\bparent_id=/i', '上级编号=');
        $result = self::inlinePatternReplace($result, '/\bname=/i', '名称=');
        $result = self::inlinePatternReplace($result, '/\btitle=/i', '标题=');
        $result = self::inlinePatternReplace($result, '/\bdescription=/i', '备注=');
        $result = self::inlinePatternReplace($result, '/\bpath=/i', '路径=');
        $result = self::inlinePatternReplace($result, '/\btype=/i', '类型=');
        $result = self::inlinePatternReplace($result, '/\bstatus=/i', '状态=');
        $result = self::inlinePatternReplace($result, '/\bbefore=/i', '变更前=');
        $result = self::inlinePatternReplace($result, '/\bafter=/i', '变更后=');
        $result = self::inlinePatternReplace($result, '/\bcount=/i', '数量=');
        $result = self::inlinePatternReplace($result, '/\bcascade=/i', '级联=');
        $result = self::inlinePatternReplace($result, '/\bname_changed=/i', '名称变更=');
        $result = self::inlinePatternReplace($result, '/\bdescription_changed=/i', '备注变更=');
        $result = self::inlinePatternReplace($result, '/\btitle_changed=/i', '标题变更=');
        $result = self::inlinePatternReplace($result, '/\bparent_changed=/i', '上级变更=');
        $result = self::inlinePatternReplace($result, '/\bpath_changed=/i', '路径变更=');
        $result = self::inlinePatternReplace($result, '/\btype_changed=/i', '类型变更=');
        $result = self::inlinePatternReplace($result, '/\bstatus_changed=/i', '状态变更=');
        $result = self::inlinePatternReplace($result, '/\bdelete_role_rows=/i', '删除角色行数=');
        $result = self::inlinePatternReplace($result, '/\bdelete_admin_role_rows=/i', '删除管理员角色绑定=');
        $result = self::inlinePatternReplace($result, '/\bdelete_role_permission_rows=/i', '删除角色授权行数=');
        $result = self::inlinePatternReplace($result, '/\bassigned_admin_count=/i', '关联管理员数=');
        $result = self::inlinePatternReplace($result, '/\bpermission_count=/i', '权限数=');
        $result = self::inlinePatternReplace($result, '/\bdelete_permission_rows=/i', '删除菜单行数=');
        $result = self::inlinePatternReplace($result, '/\bdelete_admin_permission_rows=/i', '删除管理员授权行数=');
        $result = self::inlinePatternReplace($result, '/\bdescendant_count=/i', '子级数量=');
        $result = self::inlinePatternReplace($result, '/\b(\d+)\s+year\(s\)\b/i', '$1 年');
        $result = self::inlinePatternReplace($result, '/\b(\d+)\s+month\(s\)\b/i', '$1 个月');
        $result = self::inlinePatternReplace($result, '/\b(\d+)\s+day\(s\)\b/i', '$1 天');
        $result = self::inlinePatternReplace($result, '/\bBalance Recharge Card\b/i', '余额充值卡');
        $result = self::inlinePatternReplace($result, '/\bVIP Exchange Card\b/i', 'VIP 兑换卡');
        $result = self::inlinePatternReplace($result, '/\bBalance\s+([0-9]+(?:\.[0-9]+)?)\b/i', '余额 $1 元');
        $result = self::inlinePatternReplace($result, '/\bEnabled\b/i', '已启用');
        $result = self::inlinePatternReplace($result, '/\bDisabled\b/i', '已停用');
        $result = self::inlinePatternReplace($result, '/\bRecycled\b/i', '回收站');
        $result = self::inlinePatternReplace($result, '/\bVIP active\b/i', '会员有效');
        $result = self::inlinePatternReplace($result, '/\bVIP expired\b/i', '会员已过期');
        $result = self::inlinePatternReplace($result, '/\bShop\b/i', '商城');
        $result = self::inlinePatternReplace($result, '/\bCDK\b/i', '卡券');
        $result = self::inlinePatternReplace($result, '/\bUpdated\b/i', '已更新');
        $result = self::inlinePatternReplace($result, '/\bChild B\b/i', '子节点乙');
        $result = self::inlinePatternReplace($result, '/\bChild\b/i', '子节点');
        $result = self::inlinePatternReplace($result, '/菜单联调\s+[a-f0-9]{6,}\s+已更新/u', '菜单示例已更新');
        $result = self::inlinePatternReplace($result, '/菜单联调\s+子节点\s+B\s+[a-f0-9]{6,}/u', '菜单示例子节点乙');
        $result = self::inlinePatternReplace($result, '/菜单联调\s+子节点乙\s+[a-f0-9]{6,}/u', '菜单示例子节点乙');
        $result = self::inlinePatternReplace($result, '/菜单联调\s+子节点\s+[a-f0-9]{6,}/u', '菜单示例子节点');
        $result = self::inlinePatternReplace($result, '/菜单联调\s+[a-f0-9]{6,}/u', '菜单示例');
        $result = self::inlinePatternReplace($result, '/\bPayPro\b/i', '经典支付');
        $result = self::inlinePatternReplace($result, '/merchant_impersonation_smoke_[a-z0-9_]+/i', '商户代登示例');
        $result = self::inlinePatternReplace($result, '/domain_(write|audit|delete|recycle)_smoke_[a-z0-9_]+/i', '域名示例');
        $result = self::inlinePatternReplace($result, '/ticket_category_[a-z0-9_]+/i', '工单分类示例');
        $result = self::inlinePatternReplace($result, '/batch payment method a [a-f0-9]+/i', '批量支付方式A');
        $result = self::inlinePatternReplace($result, '/batch payment method b [a-f0-9]+/i', '批量支付方式B');
        $result = self::inlinePatternReplace($result, '/smoke payment method [a-f0-9]+/i', '支付方式示例');
        $result = self::inlinePatternReplace($result, '/channel smoke account [a-z0-9]+/i', '收款账号示例');
        $result = self::inlinePatternReplace($result, '/管理员联调目标\s+已更新/u', '管理员示例目标已更新');
        $result = self::inlinePatternReplace($result, '/\bpermission reorder\b/i', '权限排序更新');
        $result = self::inlinePatternReplace($result, '/\bwarning_count\s*=/i', '风险提示数=');
        $result = self::inlinePatternReplace($result, '/\btarget\s*=/i', '跳转地址=');
        $result = self::inlinePatternReplace($result, '/\bpermission_id\s*=/i', '权限编号=');
        $result = self::inlinePatternReplace($result, '/\bparent_id\s*=/i', '上级编号=');
        $result = self::inlinePatternReplace($result, '/\bcount\s*=/i', '数量=');
        $result = self::inlinePatternReplace($result, '/\bstatus\s*=\s*0\b/i', '状态=0');
        $result = self::inlinePatternReplace($result, '/\bstatus\s*=\s*1\b/i', '状态=1');
        $result = self::inlinePatternReplace($result, '/\bactive\s*=\s*0\b/i', '当前未启用');
        $result = self::inlinePatternReplace($result, '/\bactive\s*=\s*1\b/i', '当前已启用');
        $result = self::inlinePatternReplace($result, '/\btype\s*=/i', '类型=');
        $result = self::inlinePatternReplace($result, '/\bbefore\s*=/i', '变更前=');
        $result = self::inlinePatternReplace($result, '/\bafter\s*=/i', '变更后=');
        $result = self::inlinePatternReplace($result, '/\btitle_changed\s*=/i', '名称变更=');
        $result = self::inlinePatternReplace($result, '/\bparent_changed\s*=/i', '父级变更=');
        $result = self::inlinePatternReplace($result, '/\bpath_changed\s*=/i', '路径变更=');
        $result = self::inlinePatternReplace($result, '/\btype_changed\s*=/i', '类型变更=');
        $result = self::inlinePatternReplace($result, '/\bstatus_changed\s*=/i', '状态变更=');
        $result = self::inlinePatternReplace($result, '/\bscope\s*=/i', '作用域=');
        $result = self::inlinePatternReplace($result, '/\btheme\s*=/i', '模板=');
        $result = self::inlinePatternReplace($result, '/作用域="?pay"?/u', '作用域="支付页"');
        $result = self::inlinePatternReplace($result, '/作用域="?home"?/u', '作用域="首页"');
        $result = self::inlinePatternReplace($result, '/\blabel\s*=/i', '标签=');
        $result = self::inlinePatternReplace($result, '/\brelative_path\s*=/i', '相对路径=');
        $result = self::inlinePatternReplace($result, '/\bconfig_key\s*=/i', '配置键=');
        $result = self::inlinePatternReplace($result, '/\bhome_temp\b/i', '首页模板配置键');
        $result = self::inlinePatternReplace($result, '/\bfrom_theme\s*=/i', '原模板=');
        $result = self::inlinePatternReplace($result, '/\bfrom_label\s*=/i', '原模板名称=');
        $result = self::inlinePatternReplace($result, '/\bto_label\s*=/i', '目标模板名称=');
        $result = self::inlinePatternReplace($result, '/\bto_theme\s*=/i', '目标模板=');
        $result = self::inlinePatternReplace($result, '/\bfallback_theme\s*=/i', '回退模板=');
        $result = self::inlinePatternReplace($result, '/\bfallback_label\s*=/i', '回退模板名称=');
        $result = self::inlinePatternReplace($result, '/\bdelete_permission_rows\s*=/i', '删除权限记录数=');
        $result = self::inlinePatternReplace($result, '/\bdelete_role_permission_rows\s*=/i', '删除角色权限记录数=');
        $result = self::inlinePatternReplace($result, '/\bdelete_admin_permission_rows\s*=/i', '删除管理员权限记录数=');
        $result = self::inlinePatternReplace($result, '/\bdescendants\s*=/i', '子节点数量=');
        $result = self::inlinePatternReplace($result, '/\bcascade\s*=/i', '级联删除=');
        $result = self::inlinePatternReplace($result, '/\bfiles\s*=/i', '文件数=');
        $result = self::inlinePatternReplace($result, '/\bdirectories\s*=/i', '目录数=');
        $result = self::inlinePatternReplace($result, '/\breferences\s*=/i', '引用记录数=');
        $result = self::inlinePatternReplace($result, '/\bpath\s*=/i', '路径=');
        $result = self::inlinePatternReplace($result, '/\bmerchant_id\s*=/i', '商户编号=');
        $result = self::inlinePatternReplace($result, '/\buser_id\s*=/i', '用户编号=');
        $result = self::inlinePatternReplace($result, '/\bvip_id\s*=/i', '会员编号=');
        $result = self::inlinePatternReplace($result, '/\busername\s*=/i', '账号=');
        $result = self::inlinePatternReplace($result, '/\bmerchant_username\s*=/i', '商户账号=');
        $result = self::inlinePatternReplace($result, '/\btag\s*=/i', '标签=');
        $result = self::inlinePatternReplace($result, '/\/api\/admin\/users\/\d+\/impersonate/i', '商户代登接口');
        $result = self::inlinePatternReplace($result, '/\/api\/admin\/permissions\/create/i', '权限创建接口');
        $result = self::inlinePatternReplace($result, '/\/api\/admin\/permissions\/reorder/i', '权限排序接口');
        $result = self::inlinePatternReplace($result, '/\/api\/admin\/permissions\/\d+\/status/i', '权限状态接口');
        $result = self::inlinePatternReplace($result, '/\/api\/admin\/permissions\/\d+\/update/i', '权限更新接口');
        $result = self::inlinePatternReplace($result, '/\/api\/admin\/permissions\/\d+\/delete/i', '权限删除接口');
        $result = self::inlinePatternReplace($result, '/\/api\/admin\/themes\/pay\/[a-z0-9_]+\/activate/i', '支付模板启用接口');
        $result = self::inlinePatternReplace($result, '/\/api\/admin\/themes\/pay\/[a-z0-9_]+\/delete/i', '支付模板删除接口');
        $result = self::inlinePatternReplace($result, '/\/api\/admin\/themes\/home\/[a-z0-9_]+\/activate/i', '首页模板启用接口');
        $result = self::inlinePatternReplace($result, '/\/api\/admin\/themes\/home\/[a-z0-9_]+\/delete/i', '首页模板删除接口');
        $result = self::inlinePatternReplace($result, '/https?:\/\/127\.0\.0\.1:8787\/User\/Index/i', '商户中心入口页');
        $result = self::inlinePatternReplace($result, '/\/User\/Index/i', '商户中心入口页');
        $result = self::inlinePatternReplace($result, '/\bsmokepay_[a-z0-9_]+\b/i', '支付模板示例');
        $result = self::inlinePatternReplace($result, '/\bsmokehome_[a-z0-9_]+\b/i', '首页模板示例');
        $result = self::inlinePatternReplace($result, '/商户\s*ID/u', '商户编号');
        $result = self::inlinePatternReplace($result, '/\bID\b/i', '编号');
        $result = self::inlinePatternReplace($result, '/\bLogo\b/i', '站点标识图');
        $result = self::inlinePatternReplace($result, '/\bURL\b/i', '链接地址');
        $result = self::inlinePatternReplace($result, '/\bHTML\b/i', '富文本内容');
        $result = self::inlinePatternReplace($result, '/\bTelegram\b/i', '电报通知');
        $result = self::inlinePatternReplace($result, '/\bWxPusher\b/i', '微信推送');
        $result = self::inlinePatternReplace($result, '/\bSMTP\b/i', '邮件服务器');
        $result = self::inlinePatternReplace($result, '/\bOAuth\b/i', '第三方登录');
        $result = self::inlinePatternReplace($result, '/\bVIP\b/i', '会员');
        $result = self::inlinePatternReplace($result, '/\bICP\b/i', '备案');
        $result = self::inlinePatternReplace($result, '/\bNo payload captured\b/i', '未捕获到请求载荷');
        $result = self::inlinePatternReplace($result, '/\bdemo_user\b/i', '演示商户账号');
        $result = self::inlinePatternReplace($result, '/q币/u', '企鹅币');
        $result = self::inlinePatternReplace($result, '/Q币/u', '企鹅币');
        $result = self::inlinePatternReplace($result, '/qq货币/i', '企鹅币');
        $result = self::inlinePatternReplace($result, '/QQ货币/', '企鹅币');
        $result = self::inlinePatternReplace($result, '/baidu云/i', '百度云');
        $result = self::inlinePatternReplace($result, '/bd云/i', '百度云');
        $result = self::inlinePatternReplace($result, '/blocked\.example\.com/i', '黑名单域名示例');
        $result = self::inlinePatternReplace($result, '/create\.example\.com/i', '白名单域名示例');
        $result = self::inlinePatternReplace($result, '/public\/pay\//i', '支付模板目录/');
        $result = self::inlinePatternReplace($result, '/public\/web\/home\//i', '首页模板目录/');
        $result = self::inlinePatternReplace($result, '/\/menu\.[a-z0-9]+\/child-b/i', '菜单示例子路径乙');
        $result = self::inlinePatternReplace($result, '/\/menu\.[a-z0-9]+\/子节点-b/u', '菜单示例子路径乙');
        $result = self::inlinePatternReplace($result, '/\/menu\.[a-z0-9]+\/子节点/u', '菜单示例子路径');
        $result = self::inlinePatternReplace($result, '/\/menu\.[a-z0-9]+\/child/i', '菜单示例子路径');
        $result = self::inlinePatternReplace($result, '/\/menu\.[a-z0-9]+\/index/i', '菜单示例入口路径');
        $result = self::inlinePatternReplace($result, '/支付宝\s+RSA\s+公钥/u', '支付宝公钥');
        $result = self::inlinePatternReplace($result, '/\bindex99\b/i', '经典支付风格首页');
        $result = self::inlinePatternReplace($result, '/\brsa\s+(?:私钥|˽Կ)\b/iu', '站点私钥');
        $result = self::inlinePatternReplace($result, '/\bThink\s+验证码密钥\b/u', '验证码密钥');
        $result = self::inlinePatternReplace($result, '/\b短信宝\s+api\b/i', '短信宝接口密钥');
        $result = self::inlinePatternReplace($result, '/\[code\]/i', '【验证码】');
        $result = self::inlinePatternReplace($result, '/\[login_uid\]/i', '【登录编号】');
        $result = self::inlinePatternReplace($result, '/\[login_ip\]/i', '【登录来源】');
        $result = self::inlinePatternReplace($result, '/\[login_time\]/i', '【登录时间】');
        $result = self::inlinePatternReplace($result, '/\[account_id\]/i', '【通道编号】');
        $result = self::inlinePatternReplace($result, '/\[account_type\]/i', '【通道类型】');
        $result = self::inlinePatternReplace($result, '/\[account_code\]/i', '【通道标识】');
        $result = self::inlinePatternReplace($result, '/\[lose_time\]/i', '【掉线时间】');
        $result = self::inlinePatternReplace($result, '/\[money\]/i', '【金额】');
        $result = self::inlinePatternReplace($result, '/\[out_trade_no\]/i', '【商户单号】');
        $result = self::inlinePatternReplace($result, '/\[userName\]/', '【用户名】');
        $result = self::inlinePatternReplace($result, '/\[sitename\]/i', '【站点名称】');
        $result = self::inlinePatternReplace($result, '/\[day\]/i', '【天数】');
        $result = self::inlinePatternReplace($result, '/([0-9]+(?:\.[0-9]+)?)\s*KB\b/i', '$1 千字节');
        $result = self::inlinePatternReplace($result, '/([0-9]+(?:\.[0-9]+)?)\s*MB\b/i', '$1 兆字节');
        $result = self::inlinePatternReplace($result, '/([0-9]+(?:\.[0-9]+)?)\s*GB\b/i', '$1 吉字节');
        $result = self::inlinePatternReplace($result, '/([0-9]+(?:\.[0-9]+)?)\s*TB\b/i', '$1 太字节');
        $result = self::inlinePatternReplace($result, '/([0-9]+(?:\.[0-9]+)?)\s*B\b/i', '$1 字节');

        $normalizedWhitespace = preg_replace('/\s{2,}/u', ' ', trim($result));
        return is_string($normalizedWhitespace) ? $normalizedWhitespace : trim($result);
    }

    private static function payloadLookupKey(string $key): string
    {
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $key);
        $snake = is_string($snake) ? $snake : $key;
        $snake = str_replace(['-', ' '], '_', $snake);

        return strtolower($snake);
    }

    private static function inlinePatternReplace(string $value, string $pattern, string $replacement): string
    {
        $replaced = preg_replace($pattern, $replacement, $value);
        return is_string($replaced) ? $replaced : $value;
    }
}
