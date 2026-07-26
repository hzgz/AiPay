<?php

declare(strict_types=1);

namespace app\support;

class AdminFixtureTextNormalizer
{
    private const EXACT_MAP = [
        'aipay modernization' => 'AiPay 官方',
        'aipay official' => 'AiPay 官方',
        'aipay smoke' => 'AiPay 官方',
        'universal_epay' => '通用易支付V1插件',
        'leshua' => '乐刷支付插件',
        'wxpay_v3' => '微信支付 V3 插件',
        'alipay_official' => '支付宝官方版V3插件',
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
        'recycled' => '已回收',
        'metadata ready' => '元数据完整',
        'metadata incomplete' => '元数据待补充',
        'config ready' => '配置已就绪',
        'config missing' => '缺少配置',
        'using default value' => '当前使用默认值',
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
        'managed universal epay account plugin for webman.' => '用于统一管理通用易支付上游账户的托管插件。',
        'managed universal epay account plugin for webman' => '用于统一管理通用易支付上游账户的托管插件。',
        'wxpay' => '微信支付',
        'alipay' => '支付宝',
        'qqpay' => 'QQ 支付',
        'wechat' => '微信支付',
        'qq' => 'QQ 支付',
    ];

    private const PAYLOAD_KEY_MAP = [
        'account_id' => '收款账号ID',
        'account_code' => '收款账号插件',
        'access_key' => '访问密钥',
        'access_key_id' => '访问密钥ID',
        'access_key_secret' => '访问密钥内容',
        'accesskey' => '访问密钥',
        'accesskeyid' => '访问密钥ID',
        'accesskeysecret' => '访问密钥内容',
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
        'merchant_display' => '商户显示名称',
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
        'shield_key' => '风控关键字',
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

    private const TEXT_REPLACEMENTS = [
        'AiPay Smoke' => 'AiPay 官方',
        'Purple' => '标准主题',
        'Puple' => '标准主题',
        'smtp-host' => 'SMTP 服务地址',
        'smsbao-api' => '短信宝 API 地址',
        'tg_admin_id' => 'Telegram 管理员编号',
        'tg_bot_token' => 'Telegram 机器人令牌',
        'wxpusher_appToken' => 'WxPusher 应用令牌',
        'thinkCode' => '验证码密钥',
        'siteurl' => '站点地址',
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

        $normalized = self::repairKnownMojibake($normalized);
        $normalized = self::applyTextReplacements($normalized);

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

        if (preg_match('/^[0-9a-f]{10,}@example\.(test|com)$/i', $normalized) === 1) {
            return '脱敏邮箱';
        }

        if (preg_match('/^risk-[a-z0-9]+\.example\.com$/i', $normalized) === 1) {
            return '风控域名已脱敏';
        }

        if (preg_match('/^news-editor-upload(?:-\d+)?\.(png|jpg|jpeg|webp|gif)$/i', $normalized) === 1) {
            return '公告编辑器上传图片';
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

        $normalized = self::repairKnownMojibake($normalized);
        $lower = mb_strtolower($normalized, 'UTF-8');

        if (preg_match('/risk-[a-z0-9]+\.example\.com/i', $normalized) === 1) {
            return '风控地址已脱敏';
        }

        if (str_contains($lower, 'example.test') || str_contains($lower, 'example.com')) {
            return '脱敏地址';
        }

        if (str_contains($lower, '127.0.0.1') || str_contains($lower, 'localhost')) {
            return '本地测试地址';
        }

        return self::translateStructuredText(self::applyTextReplacements($normalized));
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
            static fn (array $matches): string => self::normalizePayloadKey((string)$matches[1]) . '=',
            $value
        );

        if (!is_string($result)) {
            $result = $value;
        }

        $result = preg_replace_callback(
            '/"([a-z][a-z0-9_-]*)":/iu',
            static fn (array $matches): string => '"' . self::normalizePayloadKey((string)$matches[1]) . '":',
            $result
        );

        return is_string($result) ? $result : $value;
    }

    private static function applyTextReplacements(string $value): string
    {
        return strtr($value, self::TEXT_REPLACEMENTS);
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
            str_contains($lower, 'merchant') => '商户数据记录',
            str_contains($lower, 'channel') => '通道数据记录',
            str_contains($lower, 'payment method') => '支付方式记录',
            str_contains($lower, 'account') => '收款账号记录',
            str_contains($lower, 'pool') => '轮询池记录',
            str_contains($lower, 'ticket') => '工单记录',
            str_contains($lower, 'domain') => '域名记录',
            str_contains($lower, 'recharge') => '充值记录',
            str_contains($lower, 'risk') => '风控记录',
            str_contains($lower, 'news') => '公告记录',
            str_contains($lower, 'nav') => '导航记录',
            default => '系统记录',
        };
    }

    private static function repairKnownMojibake(string $value): string
    {
        $converted = @iconv('UTF-8', 'GB18030//IGNORE', $value);
        if (!is_string($converted) || $converted === '') {
            return $value;
        }

        if (function_exists('mb_check_encoding') && !mb_check_encoding($converted, 'UTF-8')) {
            return $value;
        }

        if ($converted === $value || !self::containsChinese($converted)) {
            return $value;
        }

        return self::meaningfulChineseScore($converted) > self::meaningfulChineseScore($value)
            ? $converted
            : $value;
    }

    private static function containsChinese(string $value): bool
    {
        return preg_match('/[\x{4E00}-\x{9FFF}]/u', $value) === 1;
    }

    private static function meaningfulChineseScore(string $value): int
    {
        $score = 0;
        foreach ([
            '支付',
            '商户',
            '插件',
            '模板',
            '配置',
            '系统',
            '公告',
            '订单',
            '官方',
            '账号',
            '通道',
            '管理',
            '中心',
            '地址',
            '日志',
            '安全',
        ] as $fragment) {
            $score += substr_count($value, $fragment);
        }

        return $score;
    }
}
