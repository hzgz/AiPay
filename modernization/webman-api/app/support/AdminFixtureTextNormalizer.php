<?php

declare(strict_types=1);

namespace app\support;

class AdminFixtureTextNormalizer
{
    private const EXACT_MAP = [
        'aipay modernization' => 'AiPay 官方',
        'legacy_epay' => '易支付协议插件',
        'legacy epay compatibility' => '易支付协议插件',
        'legacy_epay_fee_deduct' => '易支付手续费扣减',
        'universal_epay' => '通用易支付插件',
        'wxpay_v3' => '微信官方 V3 插件',
        'alipay_official' => '支付宝官方版 V3 插件',
        'alipay_bill' => '支付宝二维码账单插件',
        'alipay_mck' => '支付宝免CK插件',
        'qqpay_software' => 'QQ 软件插件',
        'wxpay_software' => '微信软件插件',
        'jiaofeiyi_wxpay' => '缴费易微信插件',
        'jiaofeiyi_alipay' => '缴费易支付宝插件',
        'homepage theme' => '首页模板',
        'member center theme' => '商户中心模板',
        'payment page theme' => '收银台模板',
        'document page theme' => '开发文档模板',
        'announcement page theme' => '公告中心模板',
        'demo page theme' => '支付测试模板',
        'platform announcement' => '平台公告',
        'industry news' => '行业资讯',
        'faq' => '常见问题',
        'new window' => '新窗口打开',
        'same window' => '当前窗口打开',
        'active' => '已启用',
        'available' => '可用',
        'enabled' => '已启用',
        'disabled' => '已停用',
        'recycled' => '回收站',
        'metadata ready' => '元数据完整',
        'metadata incomplete' => '元数据不完整',
        'config ready' => '配置已完成',
        'config missing' => '缺少配置',
        'using default value' => '使用默认配置',
        'no config mapping' => '未接入系统配置',
        'index99' => 'Index99 首页模板',
        '/doc' => '开发文档',
        '/demo' => '支付测试',
        '/news/index' => '公告中心',
        '/admin.photo/list/name/images' => '系统图片目录接口',
        '/admin.photo/list/name/news' => '公告图片目录接口',
        '/admin.photo/list/name/plugins' => '插件素材目录接口',
        '/admin.photo/list/name/qrcode' => '二维码目录接口',
        '/admin.photo/list/name/pay_qr' => '支付二维码目录接口',
        '/admin.photo/list/name/merchant_assets' => '商户素材目录接口',
        '/aipay.demo_theme/index' => '支付测试主题',
        '/aipay.doc_theme/index' => '开发文档主题',
        '/aipay.news_theme/index' => '公告中心主题',
    ];

    private const PAYLOAD_KEY_MAP = [
        'account_id' => '收款账号ID',
        'account_code' => '收款账号插件',
        'access_key' => '访问密钥',
        'access_key_id' => '访问密钥ID',
        'access_key_secret' => '访问密钥密文',
        'accesskey' => '访问密钥',
        'accesskeyid' => '访问密钥ID',
        'accesskeysecret' => '访问密钥密文',
        'admin_count' => '管理员数量',
        'admin_id' => '管理员ID',
        'amount' => '金额',
        'api' => '接口',
        'api_url' => '接口地址',
        'assignee_id' => '处理人ID',
        'category_id' => '分类ID',
        'category_ids' => '分类ID',
        'channel' => '通道',
        'channel_code' => '通道编码',
        'channel_id' => '通道ID',
        'channel_name' => '通道名称',
        'clientip' => '客户端IP',
        'code' => '编码',
        'content' => '内容',
        'created_at' => '创建时间',
        'deleted' => '删除数量',
        'device' => '设备',
        'domain' => '域名',
        'domain_id' => '域名ID',
        'email' => '邮箱',
        'id' => 'ID',
        'label' => '标签',
        'log_id' => '日志ID',
        'memo' => '备注',
        'merchant_count' => '商户数量',
        'merchant_display' => '商户显示名',
        'merchant_id' => '商户ID',
        'merchant_name' => '商户名称',
        'merchant_username' => '商户账号',
        'mobile' => '手机号',
        'name' => '名称',
        'nav_id' => '导航ID',
        'nickname' => '昵称',
        'news_id' => '公告ID',
        'notify_url' => '通知地址',
        'open_tickets' => '未结工单数',
        'order_id' => '订单ID',
        'order_no' => '订单号',
        'out_trade_no' => '商户订单号',
        'param' => '透传参数',
        'payment_method' => '支付方式',
        'payment_type' => '支付类型',
        'permission_id' => '权限ID',
        'permission_ids' => '权限ID',
        'plugin_code' => '插件编码',
        'plugin_id' => '插件ID',
        'plugin_name' => '插件名称',
        'pool_id' => '轮询池ID',
        'pool_name' => '轮询池名称',
        'qrcode' => '二维码',
        'qrcode_url' => '二维码地址',
        'quick_login_id' => '快捷登录ID',
        'reason' => '原因',
        'remark' => '备注',
        'remarks' => '备注',
        'requested' => '请求数量',
        'restored' => '恢复数量',
        'return_url' => '返回地址',
        'risk_id' => '风控ID',
        'role_id' => '角色ID',
        'role_ids' => '角色ID',
        'selected_via' => '选择来源',
        'shield_key' => '风控关键词',
        'sign_type' => '签名方式',
        'siteurl' => '站点地址',
        'source' => '来源',
        'status' => '状态',
        'ticket_id' => '工单ID',
        'ticket_ids' => '工单ID',
        'timeout_seconds' => '超时时间',
        'title' => '标题',
        'trade_no' => '系统订单号',
        'type' => '类型',
        'updated' => '更新数量',
        'url' => '地址',
        'user_id' => '用户ID',
        'username' => '账号',
        'vip_id' => '套餐ID',
    ];

    private const COMMON_REPLACEMENTS = [
        'AiPay Smoke' => 'AiPay',
        'Purple' => '标准主题',
        'Puple' => '标准主题',
        '账�' => '账号',
        '角�' => '角色',
        '权�' => '权限',
        '回收�' => '回收站',
        '状��切�' => '状态切换',
        '日�' => '日志',
        '发��' => '发送',
        '充��' => '充值',
        '记�' => '记录',
        '朢�大' => '最大',
        '朢�小' => '最小',
        '地坢�' => '地址',
        '密�' => '密钥',
        '编�' => '编号',
        '开�' => '开关',
        '模�' => '模板',
        '内�' => '内容',
        '背�' => '背景',
        '方�' => '方式',
        '套�' => '套餐',
        '推��' => '推送',
        '服务�' => '服务商',
        '名�' => '名单',
        '测时�' => '检测时间',
        '收款�' => '收款人',
        '自定�?API' => '自定义 API',
        '管理员邮�' => '管理员邮箱',
        '测试收款�' => '测试收款人',
        '支付宝公�' => '支付宝公钥',
        'smtp-host' => 'SMTP 服务器',
        'smsbao-api' => '短信宝 API 地址',
        'tg_admin_id' => '电报管理员编号',
        'tg_bot_token' => '电报机器人令牌',
        'wxpusher_appToken' => '微信推送应用令牌',
        'thinkCode' => '验证码密钥',
        'key' => '站点关键词',
    ];

    private const SUSPICIOUS_FRAGMENTS = [
        '鍟', '绯', '鏀', '閫', '鍒', '绔', '鐢', '娴', '鏉', '璁', '妯', '鍏',
        '闃', '缁', '閰', '鍥', '鑿', '宸', '浠', '銆', '锛', '鈥', '�',
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
        $normalized = self::normalizeWhitespace($value);
        if ($normalized === '') {
            return '';
        }

        $normalized = self::repairMojibake($normalized);
        $normalized = self::applyCommonReplacements($normalized);

        if (self::looksLikeJson($normalized)) {
            $decoded = json_decode($normalized, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $encoded = json_encode(
                    self::normalizePayload($decoded),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );

                return is_string($encoded) ? $encoded : $normalized;
            }
        }

        $lookup = mb_strtolower($normalized, 'UTF-8');
        if (isset(self::EXACT_MAP[$lookup])) {
            return self::EXACT_MAP[$lookup];
        }

        if (preg_match('/^[0-9a-f]{10,}@example\.test$/i', $normalized) === 1) {
            return '脱敏邮箱';
        }

        if (preg_match('/^risk-[a-z0-9]+\.example\.com$/i', $normalized) === 1) {
            return '风控测试域名';
        }

        if (preg_match('/^news-editor-upload(?:-\d+)?\.(png|jpg|jpeg|webp|gif)$/i', $normalized) === 1) {
            return '公告编辑器上传图片';
        }

        if (preg_match('/^plugin-editor-upload(?:-\d+)?\.(png|jpg|jpeg|webp|gif)$/i', $normalized) === 1) {
            return '插件编辑器上传图片';
        }

        if (preg_match('/^[0-9a-f]{20,}\.(png|jpg|jpeg|webp|gif)$/i', $normalized) === 1) {
            return '系统素材图片';
        }

        if (self::isFixtureLikeText($normalized)) {
            return self::generalizeFixtureText($normalized);
        }

        return self::translateStructuredText($normalized);
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
        $trimmed = self::normalizeWhitespace($key);
        if ($trimmed === '') {
            return '';
        }

        $lookup = self::payloadLookupKey($trimmed);

        return self::PAYLOAD_KEY_MAP[$lookup] ?? $trimmed;
    }

    public static function normalizeUrlPreview(string $value): string
    {
        $normalized = self::normalizeWhitespace($value);
        if ($normalized === '') {
            return '';
        }

        $normalized = self::repairMojibake($normalized);
        $lower = mb_strtolower($normalized, 'UTF-8');

        if (preg_match('/risk-[a-z0-9]+\.example\.com/i', $normalized) === 1) {
            return '风控测试地址';
        }

        if (str_contains($lower, 'example.test') || str_contains($lower, 'example.com')) {
            return '脱敏地址';
        }

        if (str_contains($lower, '127.0.0.1') || str_contains($lower, 'localhost')) {
            return '本地调试地址';
        }

        return self::translateStructuredText(self::applyCommonReplacements($normalized));
    }

    private static function normalizeWhitespace(string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\n", "\t"], ' ', trim($value));
        $collapsed = preg_replace('/\s+/u', ' ', $value);

        return is_string($collapsed) ? trim($collapsed) : trim($value);
    }

    private static function payloadLookupKey(string $key): string
    {
        $key = mb_strtolower($key, 'UTF-8');

        return str_replace(['-', ' '], '_', $key);
    }

    private static function translateStructuredText(string $value): string
    {
        $result = preg_replace_callback(
            '/\b([a-z][a-z0-9_-]*)=/iu',
            static fn (array $matches): string => self::normalizePayloadKey((string) $matches[1]) . '=',
            $value
        );

        if (!is_string($result)) {
            $result = $value;
        }

        $result = preg_replace_callback(
            '/"([a-z][a-z0-9_-]*)":/iu',
            static fn (array $matches): string => '"' . self::normalizePayloadKey((string) $matches[1]) . '":',
            $result
        );

        return is_string($result) ? $result : $value;
    }

    private static function applyCommonReplacements(string $value): string
    {
        return str_replace(
            array_keys(self::COMMON_REPLACEMENTS),
            array_values(self::COMMON_REPLACEMENTS),
            $value
        );
    }

    private static function looksLikeJson(string $value): bool
    {
        $first = $value[0] ?? '';

        return $first === '{' || $first === '[';
    }

    private static function isFixtureLikeText(string $value): bool
    {
        return preg_match('/\b(smoke|fixture|sample|demo|test)\b/i', $value) === 1;
    }

    private static function generalizeFixtureText(string $value): string
    {
        $lower = mb_strtolower($value, 'UTF-8');

        return match (true) {
            str_contains($lower, 'merchant') => '测试商户数据',
            str_contains($lower, 'channel') => '测试通道数据',
            str_contains($lower, 'payment method') => '测试支付方式',
            str_contains($lower, 'account') => '测试收款账号',
            str_contains($lower, 'pool') => '测试轮询池',
            str_contains($lower, 'ticket') => '测试工单数据',
            str_contains($lower, 'domain') => '测试域名数据',
            str_contains($lower, 'recharge') => '测试充值记录',
            str_contains($lower, 'risk') => '测试风控记录',
            str_contains($lower, 'news') => '测试公告数据',
            str_contains($lower, 'nav') => '测试导航数据',
            default => '测试数据',
        };
    }

    private static function repairMojibake(string $value): string
    {
        if (!self::looksLikeMojibake($value)) {
            return $value;
        }

        $converted = @iconv('UTF-8', 'GB18030//IGNORE', $value);
        if (!is_string($converted) || $converted === '') {
            return $value;
        }

        if (function_exists('mb_check_encoding') && !mb_check_encoding($converted, 'UTF-8')) {
            return $value;
        }

        return self::mojibakeScore($converted) < self::mojibakeScore($value)
            ? $converted
            : $value;
    }

    private static function looksLikeMojibake(string $value): bool
    {
        return self::mojibakeScore($value) >= 2;
    }

    private static function mojibakeScore(string $value): int
    {
        $score = 0;
        foreach (self::SUSPICIOUS_FRAGMENTS as $fragment) {
            $score += substr_count($value, $fragment);
        }

        return $score;
    }
}
