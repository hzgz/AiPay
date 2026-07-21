<?php

declare(strict_types=1);

namespace app\support;

use support\Db;

class AdminConfigCatalog
{
    private const MOJIBAKE_FRAGMENTS = [
        "\u{FFFD}", "\u{20AC}", "\u{935F}", "\u{93B4}", "\u{7487}", "\u{95AB}",
        "\u{93C0}", "\u{9427}", "\u{934F}", "\u{5BF0}", "\u{7039}", "\u{7490}",
        "\u{7F01}", "\u{95C8}", "\u{7EEF}", "\u{7EFE}", "\u{7F03}", "\u{9422}",
        "\u{9352}", "\u{9359}", "\u{95C2}", "\u{8930}", "\u{748B}", "\u{9365}",
        "\u{93C3}", "\u{951B}", "\u{9286}", "\u{9369}", "\u{59AF}", "\u{95B0}",
        "\u{93BB}", "\u{935A}", "\u{7481}", "\u{9475}", "\u{95C3}", "\u{942D}",
        "\u{95AD}", "\u{74A7}", "\u{7F02}", "\u{93BA}", "\u{7ED4}", "\u{7EE0}",
        "\u{935B}", "\u{6A40}", "\u{5056}", "\u{5D85}", "\u{608A}",
    ];
    private const FORCED_DISPLAY_LABELS = [
        'adminMail' => '管理员邮箱',
        'aff_percentage' => '推广返佣比例',
        'aff_type' => '返佣模式',
        'apiTemp' => '接口模板',
        'api_bg' => '接口页背景',
        'bg' => '全站背景',
        'bgtype' => '背景类型',
        'create_qrCode' => '二维码生成方式',
        'daily_limit' => '验证码每日限制',
        'disconnect_minute' => '掉线判定分钟',
        'domain_black' => '域名黑名单',
        'domain_white' => '域名白名单',
        'file-type' => '文件存储方式',
        'home_temp' => '首页模板',
        'home_url' => '首页入口开关',
        'is_channelPay' => '通道测试支付',
        'merchant_login_drag_verify' => '商户登录滑动验证',
        'merchant_register_drag_verify' => '商户注册滑动验证',
        'merchant_retrieve_drag_verify' => '找回密码滑动验证',
        'max_orderprice' => '最大订单金额',
        'min_orderprice' => '最小订单金额',
        'orderDisplay' => '订单显示条数',
        'pay_api' => 'API地址',
        'qq_login' => 'QQ快捷登录',
        'qr_codeType' => '二维码解码方式',
        'reg_give_vip' => '注册赠送套餐',
        'sitename' => '站点名称',
        'smstype' => '短信服务商',
        'software_callback_sign_mode' => '软件回调签名模式',
        'software_callback_sign_window' => '软件回调签名时效',
        'software_name' => '软件名称',
        'timeout' => '订单超时时间',
        'title' => '页面标题',
        'wechat_login' => '微信快捷登录',
    ];

    private const DISPLAY_LABELS = [
        'adminSecurityKey' => '后台安全验证密钥',
        'alipay' => '支付宝收款开关',
        'alipayrsaPublicKey' => '支付宝公钥',
        'api_url' => '接口地址',
        'appid' => '应用编号',
        'bearMoney' => '实名认证费用',
        'captcha-type' => '验证码类型',
        'cdkPayUrl' => '卡密充值地址',
        'code_switch' => '验证码开关',
        'dataClearDays' => '数据清理保留天数',
        'demo_theme' => '支付测试主题',
        'demopay_money' => '支付测试金额',
        'demopay_name' => '支付测试收款人',
        'desc' => '站点简介',
        'diyApiTemp' => '自定义接口模板',
        'diyMtceHtml' => '维护页模板',
        'diy_codeTemp' => '验证码模板',
        'diy_dataClear' => '数据清理范围',
        'diy_demoPay' => '支付测试方式',
        'diy_js' => '自定义脚本',
        'diy_loginTips' => '登录通知模板',
        'diy_loseTips' => '掉线通知模板',
        'diy_moneyTips' => '余额提醒模板',
        'diy_orderNo' => '自定义订单号',
        'diy_orderTips' => '订单通知模板',
        'diy_recharge' => '充值支付方式',
        'diy_regTips' => '注册通知模板',
        'diy_task_key' => '计划任务密钥',
        'diy_userAvatar' => '默认用户头像',
        'diy_userId' => '自定义商户编号',
        'diy_vipTemp' => 'VIP到期模板',
        'doc_theme' => '开发文档主题',
        'domainNum' => '域名每日新增上限',
        'domain_black' => '域名黑名单',
        'domain_notice' => '域名提示',
        'domain_white' => '域名白名单',
        'email_switch' => '邮件通知开关',
        'epayid_demo' => '支付测试商户号',
        'epaykey_demo' => '支付测试密钥',
        'epayurl_demo' => '支付测试网关地址',
        'favicon' => '网站图标',
        'forceRealName' => '强制实名认证',
        'home_popup' => '首页弹窗',
        'icp' => 'ICP备案号',
        'imageSize' => '图片压缩大小',
        'index_popup' => '入口页弹窗',
        'isAdminSecurity' => '后台安全验证开关',
        'isCdkPay' => '卡密充值开关',
        'isMtce' => '维护模式开关',
        'isRealName' => '实名认证开关',
        'isSecurity' => '安全绑定开关',
        'isSecurityForce' => '强制安全绑定开关',
        'isSecurityLogin' => '登录安全验证开关',
        'isTicket' => '工单中心开关',
        'is_aff' => '推广返佣开关',
        'is_channelPay' => '通道测试支付',
        'is_dataClear' => '数据清理开关',
        'is_diyUserId' => '自定义商户编号开关',
        'is_domain' => '域名管理开关',
        'is_examine' => '审核开关',
        'is_logOff' => '账户注销开关',
        'is_notice' => '公告中心开关',
        'is_pay_api' => '自定义API线路开关',
        'is_pay_money' => '支付金额校验开关',
        'is_paypage_realname' => '支付页实名展示',
        'is_quotations' => '行情展示开关',
        'is_reg' => '注册开关',
        'is_reg_give_price' => '注册赠送余额开关',
        'is_reg_give_vip' => '注册赠送套餐开关',
        'is_smOrder' => '手动补单按钮开关',
        'is_sponsor' => '赞助位开关',
        'is_vip_expire' => 'VIP到期提醒开关',
        'is_weboff' => '前台停站开关',
        'isDiy_orderNo' => '自定义订单号开关',
        'key' => '站点关键字',
        'logo' => '网站标志',
        'logincode-type' => '登录验证方式',
        'merchant_login_drag_verify' => '商户登录滑动验证',
        'merchant_register_drag_verify' => '商户注册滑动验证',
        'merchant_retrieve_drag_verify' => '找回密码滑动验证',
        'mtceType' => '维护页模板',
        'news_theme' => '公告中心主题',
        'paid_reg' => '付费注册',
        'paid_reg_price' => '付费注册金额',
        'privacy' => '隐私政策',
        'qqpay' => 'QQ支付开关',
        'quotations' => '行情展示内容',
        'randomKey' => '随机密钥',
        'realNameBear' => '实名费用承担方',
        'realNameType' => '实名通道类型',
        'regcode-type' => '注册验证方式',
        'reg_give_price' => '注册赠送余额',
        'reg_popup' => '注册页弹窗',
        'reportNo' => '举报按钮文案',
        'reportPos' => '举报说明位置',
        'reportTips' => '举报说明',
        'reportTitle' => '举报弹窗标题',
        'reportUrl' => '举报跳转地址',
        'reportYes' => '举报确认文案',
        'retrieve-type' => '找回方式',
        'rsaPrivateKey' => '站点私钥',
        'securityBindTips' => '安全绑定提示',
        'securityIcon' => '安全验证图标',
        'securityName' => '安全验证名称',
        'securityPopContent' => '安全验证弹窗内容',
        'securityPopTitle' => '安全验证弹窗标题',
        'sh_notice' => '商户审核提示',
        'shield_key' => '风控关键词',
        'shield_tips' => '风控提示',
        'SmtpSecure' => '邮件加密方式',
        'smtp-host' => '邮件服务器',
        'smtp-pass' => '发信密码',
        'smtp-port' => '邮件端口',
        'smtp-user' => '发信账号',
        'smsbao-api' => '短信宝接口地址',
        'td_notice' => '支付说明',
        'tg_admin_id' => '电报管理员编号',
        'tg_bind_tips' => '电报绑定提示',
        'tg_bot_token' => '电报机器人令牌',
        'tg_notice_recharge' => '电报充值通知',
        'tg_notice_register' => '电报注册通知',
        'tg_notice_ticket' => '电报工单通知',
        'tg_notice_vip' => '电报会员通知',
        'tg_switch' => '电报通知开关',
        'thinkCode' => '验证码密钥',
        'user_agreement' => '用户协议',
        'user_theme' => '用户中心主题',
        'vip_expire' => 'VIP到期提醒天数',
        'wechat' => '微信收款开关',
        'web_url' => '前台地址',
        'wxpusher_appToken' => '微信推送应用令牌',
        'wxpusher_switch' => '微信推送开关',
    ];

    private const EDITABLE_FORM_GROUPS = [
        'basic_display' => [
            'title' => '基础展示',
            'description' => '站点名称、页面标题、标志、图标以及首页展示相关配置。',
            'fields' => [
                'sitename',
                'software_name',
                'title',
                'desc',
                'key',
                'adminMail',
                'icp',
                'logo',
                'favicon',
                'bgtype',
                'bg',
                'api_bg',
                'apiTemp',
                'home_temp',
                'home_url',
            ],
        ],
        'template_content' => [
            'title' => '内容模板',
            'description' => '首页文案、协议公告、支付说明和模板页面相关配置。',
            'fields' => [
                'diyApiTemp',
                'is_notice',
                'demo_theme',
                'doc_theme',
                'home_popup',
                'index_popup',
                'news_theme',
                'reg_popup',
                'privacy',
                'user_agreement',
                'domain_notice',
                'sh_notice',
                'td_notice',
                'user_theme',
            ],
        ],
        'transaction_rules' => [
            'title' => '交易规则',
            'description' => '订单金额、支付测试、二维码生成与回调签名相关配置。',
            'fields' => [
                'is_channelPay',
                'isDiy_orderNo',
                'diy_orderNo',
                'demopay_money',
                'demopay_name',
                'diy_demoPay',
                'epayid_demo',
                'epaykey_demo',
                'epayurl_demo',
                'min_orderprice',
                'max_orderprice',
                'timeout',
                'is_pay_money',
                'is_pay_api',
                'pay_api',
                'daily_limit',
                'disconnect_minute',
                'orderDisplay',
                'create_qrCode',
                'qr_codeType',
                'software_callback_sign_mode',
                'software_callback_sign_window',
            ],
        ],
        'merchant_access' => [
            'title' => '商户准入',
            'description' => '商户注册、实名认证、域名管理、返佣和赠送能力配置。',
            'fields' => [
                'is_reg',
                'paid_reg',
                'paid_reg_price',
                'min_recharge',
                'max_recharge',
                'is_domain',
                'domainNum',
                'domain_white',
                'domain_black',
                'is_examine',
                'isTicket',
                'isRealName',
                'realNameType',
                'realNameBear',
                'bearMoney',
                'forceRealName',
                'is_aff',
                'aff_type',
                'aff_percentage',
                'is_diyUserId',
                'diy_userId',
                'is_reg_give_price',
                'reg_give_price',
                'is_reg_give_vip',
                'reg_give_vip',
                'is_vip_expire',
                'vip_expire',
                'is_paypage_realname',
                'is_sponsor',
                'is_logOff',
            ],
        ],
        'security_auth' => [
            'title' => '安全验证',
            'description' => '验证码、安全校验、登录保护与风控提示等配置。',
            'fields' => [
                'isAdminSecurity',
                'isSecurity',
                'isSecurityForce',
                'isSecurityLogin',
                'code_switch',
                'captcha-type',
                'merchant_login_drag_verify',
                'merchant_register_drag_verify',
                'merchant_retrieve_drag_verify',
                'logincode-type',
                'regcode-type',
                'retrieve-type',
                'smstype',
                'shield_tips',
                'shield_key',
            ],
        ],
        'notifications' => [
            'title' => '通知服务',
            'description' => '邮件、短信、电报、微信推送和模板通知相关配置。',
            'fields' => [
                'email_switch',
                'smtp-host',
                'smtp-port',
                'smtp-user',
                'smtp-pass',
                'SmtpSecure',
                'smstype',
                'alisms-accessKeyId',
                'alisms-Secret',
                'alisms-SignName',
                'alisms-LoginCodeId',
                'alisms-RegCodeId',
                'tensms-AppId',
                'tensms-accessKeyId',
                'tensms-Secret',
                'tensms-SignName',
                'tensms-LoginCodeId',
                'tensms-RegCodeId',
                'smsbao-user',
                'smsbao-pass',
                'smsbao-api',
                'smsbao-SignName',
                'tg_switch',
                'tg_admin_id',
                'tg_bot_token',
                'wxpusher_switch',
                'wxpusher_appToken',
                'tg_notice_recharge',
                'tg_notice_register',
                'tg_notice_ticket',
                'tg_notice_vip',
                'diy_codeTemp',
                'diy_loginTips',
                'diy_regTips',
                'diy_orderTips',
                'diy_moneyTips',
                'diy_loseTips',
                'diy_vipTemp',
                'tg_bind_tips',
            ],
        ],
        'storage_integrations' => [
            'title' => '存储上传',
            'description' => '文件上传、压缩大小和对象存储服务相关配置。',
            'fields' => [
                'file-type',
                'imageSize',
                'file-endpoint',
                'file-accessKeyId',
                'file-accessKeySecret',
                'file-OssName',
                'qiniu-Domain',
                'qiniu-Bucket',
                'qiniu-AK',
                'qiniu-SK',
            ],
        ],
        'maintenance' => [
            'title' => '维护清理',
            'description' => '停站、维护页展示和数据清理相关配置。',
            'fields' => [
                'isMtce',
                'is_weboff',
                'mtceType',
                'diyMtceHtml',
                'is_dataClear',
                'dataClearDays',
                'diy_task_key',
                'diy_dataClear',
            ],
        ],
    ];

    private const EDITABLE_FIELDS = [
        'adminMail' => [
            'label' => '管理员邮箱',
            'editor' => 'input',
            'value_type' => 'email',
            'max_length' => 120,
            'placeholder' => 'support@aipay.cn',
            'help_text' => '',
        ],
        'desc' => [
            'label' => '站点简介',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 255,
            'placeholder' => '',
            'help_text' => '',
        ],
        'demopay_money' => [
            'label' => '支付测试金额',
            'editor' => 'input',
            'value_type' => 'non_negative_decimal',
            'max_length' => 12,
            'placeholder' => '0.01',
            'help_text' => '',
        ],
        'demopay_name' => [
            'label' => '支付测试收款人',
            'editor' => 'input',
            'value_type' => 'text',
            'max_length' => 80,
            'placeholder' => '支付测试收款商户',
            'help_text' => '',
        ],
        'diy_codeTemp' => [
            'label' => '验证码模板',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 255,
            'placeholder' => '您的验证码是 [code]',
            'help_text' => '',
        ],
        'diyApiTemp' => [
            'label' => '自定义接口模板',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 20000,
            'placeholder' => '',
            'help_text' => '',
        ],
        'diy_loginTips' => [
            'label' => '登录通知模板',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 500,
            'placeholder' => '',
            'help_text' => '支持 [login_uid]、[login_ip]、[login_time] 变量。',
        ],
        'diy_demoPay' => [
            'label' => '支付测试方式',
            'editor' => 'textarea',
            'value_type' => 'list',
            'max_length' => 255,
            'placeholder' => "wxpay alipay qqpay",
            'help_text' => '',
        ],
        'diy_loseTips' => [
            'label' => '掉线通知模板',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 500,
            'placeholder' => '收款账号 [account_code] 已于 [lose_time] 掉线',
            'help_text' => '',
        ],
        'diy_moneyTips' => [
            'label' => '余额提醒模板',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 255,
            'placeholder' => '',
            'help_text' => '',
        ],
        'diy_orderTips' => [
            'label' => '订单通知模板',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 500,
            'placeholder' => '您有新的订单 [out_trade_no]',
            'help_text' => '',
        ],
        'diy_regTips' => [
            'label' => '注册通知模板',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 255,
            'placeholder' => '',
            'help_text' => '支持 [userName] 变量，用于商户注册成功提示。',
        ],
        'diy_vipTemp' => [
            'label' => 'VIP 到期模板',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 255,
            'placeholder' => '[sitename] VIP 将于 [day] 天后到期',
            'help_text' => '',
        ],
        'domain_notice' => [
            'label' => '域名提示',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 500,
            'placeholder' => '',
            'help_text' => '',
        ],
        'domainNum' => [
            'label' => '域名每日新增上限',
            'editor' => 'input',
            'value_type' => 'non_negative_integer',
            'max_length' => 6,
            'placeholder' => '0 表示不限制',
            'help_text' => '',
        ],
        'domain_black' => [
            'label' => '域名黑名单',
            'editor' => 'textarea',
            'value_type' => 'list',
            'max_length' => 5000,
            'placeholder' => "blocked.example.com
spam.example.com",
            'help_text' => '',
        ],
        'domain_white' => [
            'label' => '域名白名单',
            'editor' => 'textarea',
            'value_type' => 'list',
            'max_length' => 5000,
            'placeholder' => "pay.example.com
api.example.com",
            'help_text' => '',
        ],
        'email_switch' => [
            'label' => '邮件通知开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'epayid_demo' => [
            'label' => '支付测试商户号',
            'editor' => 'input',
            'value_type' => 'text',
            'max_length' => 120,
            'placeholder' => '请输入支付测试商户号',
            'help_text' => '',
        ],
        'epaykey_demo' => [
            'label' => '支付测试密钥',
            'editor' => 'password',
            'value_type' => 'text',
            'max_length' => 255,
            'placeholder' => '',
            'help_text' => '',
        ],
        'epayurl_demo' => [
            'label' => '支付测试网关地址',
            'editor' => 'input',
            'value_type' => 'url',
            'max_length' => 255,
            'placeholder' => '',
            'help_text' => '',
        ],
        'favicon' => [
            'label' => '网站图标',
            'editor' => 'input',
            'value_type' => 'path',
            'max_length' => 255,
            'placeholder' => '/upload/images/favicon.ico',
            'help_text' => '',
        ],
        'forceRealName' => [
            'label' => '强制实名认证',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'home_popup' => [
            'label' => '首页弹窗内容',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 2000,
            'placeholder' => '',
            'help_text' => '',
        ],
        'icp' => [
            'label' => 'ICP 备案号',
            'editor' => 'input',
            'value_type' => 'text',
            'max_length' => 80,
            'placeholder' => '',
            'help_text' => '',
        ],
        'index_popup' => [
            'label' => '入口页弹窗',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 2000,
            'placeholder' => '请输入入口页弹窗内容',
            'help_text' => '',
        ],
        'is_aff' => [
            'label' => '推广返佣开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'is_channelPay' => [
            'label' => '通道测试支付',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'isCdkPay' => [
            'label' => '卡密充值开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'is_domain' => [
            'label' => '域名管理开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'is_examine' => [
            'label' => '审核开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'is_notice' => [
            'label' => '公告中心开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'is_logOff' => [
            'label' => '账户注销开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'is_pay_api' => [
            'label' => '自定义接口模板',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'is_reg_give_price' => [
            'label' => '注册赠送余额开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'is_reg_give_vip' => [
            'label' => '注册赠送套餐开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'is_reg' => [
            'label' => '注册开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'isRealName' => [
            'label' => '实名认证开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'is_smOrder' => [
            'label' => '手动补单按钮开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'is_sponsor' => [
            'label' => '赞助位开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'isTicket' => [
            'label' => '工单中心开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'is_vip_expire' => [
            'label' => 'VIP 到期提醒',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'logo' => [
            'label' => '网站标志',
            'editor' => 'input',
            'value_type' => 'path',
            'max_length' => 255,
            'placeholder' => '/upload/images/logo.png',
            'help_text' => '',
        ],
        'max_orderprice' => [
            'label' => '最大订单金额',
            'editor' => 'input',
            'value_type' => 'non_negative_decimal',
            'max_length' => 12,
            'placeholder' => '1000',
            'help_text' => '',
        ],
        'max_recharge' => [
            'label' => '商户充值最大金额',
            'editor' => 'input',
            'value_type' => 'non_negative_decimal',
            'max_length' => 12,
            'placeholder' => '1000',
            'help_text' => '',
        ],
        'min_recharge' => [
            'label' => '商户充值最小金额',
            'editor' => 'input',
            'value_type' => 'non_negative_decimal',
            'max_length' => 12,
            'placeholder' => '0',
            'help_text' => '',
        ],
        'min_orderprice' => [
            'label' => '最小订单金额',
            'editor' => 'input',
            'value_type' => 'non_negative_decimal',
            'max_length' => 12,
            'placeholder' => '0.01',
            'help_text' => '',
        ],
        'orderDisplay' => [
            'label' => '订单显示条数',
            'editor' => 'input',
            'value_type' => 'non_negative_integer',
            'max_length' => 6,
            'placeholder' => '10',
            'help_text' => '',
        ],
        'paid_reg' => [
            'label' => '付费注册',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'pay_api' => [
            'label' => '对接接口地址',
            'editor' => 'textarea',
            'value_type' => 'list',
            'max_length' => 2000,
            'placeholder' => "https://api.example.com/
https://api2.example.com/",
            'help_text' => '',
        ],
        'paid_reg_price' => [
            'label' => '付费注册金额',
            'editor' => 'input',
            'value_type' => 'non_negative_decimal',
            'max_length' => 12,
            'placeholder' => '0.01',
            'help_text' => '',
        ],
        'qq_login' => [
            'label' => 'QQ 快捷登录',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 12,
            'placeholder' => '',
            'help_text' => '',
        ],
        'privacy' => [
            'label' => '隐私政策',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 5000,
            'placeholder' => '',
            'help_text' => '',
        ],
        'reg_give_price' => [
            'label' => '注册赠送余额',
            'editor' => 'input',
            'value_type' => 'non_negative_decimal',
            'max_length' => 12,
            'placeholder' => '0.00',
            'help_text' => '',
        ],
        'reg_give_vip' => [
            'label' => '注册赠送套餐',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 12,
            'placeholder' => '',
            'help_text' => '',
        ],
        'reg_popup' => [
            'label' => '注册页弹窗',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 2000,
            'placeholder' => '请输入注册页弹窗内容',
            'help_text' => '',
        ],
        'sh_notice' => [
            'label' => '商户审核提示',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 1000,
            'placeholder' => '商户审核提示',
            'help_text' => '',
        ],
        'sitename' => [
            'label' => '站点名称',
            'editor' => 'input',
            'value_type' => 'text',
            'max_length' => 80,
            'placeholder' => 'AiPay 支付平台',
            'help_text' => '',
        ],
        'software_name' => [
            'label' => '软件名称',
            'editor' => 'input',
            'value_type' => 'text',
            'max_length' => 80,
            'placeholder' => 'AiPay',
            'help_text' => '',
        ],
        'SmtpSecure' => [
            'label' => '邮件加密方式',
            'editor' => 'select',
            'value_type' => 'text',
            'max_length' => 16,
            'placeholder' => '',
            'help_text' => '',            'options' => [
                ['label' => '默认', 'value' => ''],
                ['label' => 'SSL/TLS', 'value' => 'ssl'],
                ['label' => 'STARTTLS', 'value' => 'tls'],
            ],
        ],
        'aff_type' => [
            'label' => '分销模式',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 1,
            'placeholder' => '',
            'help_text' => '',
            'options' => [
                ['label' => '充值返佣', 'value' => '0'],
                ['label' => '会员购买返佣', 'value' => '1'],
            ],
        ],
        'bearMoney' => [
            'label' => '实名认证费用',
            'editor' => 'input',
            'value_type' => 'non_negative_decimal',
            'max_length' => 12,
            'placeholder' => '0.00',
            'help_text' => '',
        ],
        'apiTemp' => [
            'label' => '接口模板',
            'editor' => 'select',
            'value_type' => 'text',
            'max_length' => 24,
            'placeholder' => '',
            'help_text' => '',
            'options' => [
                ['label' => '标准模板', 'value' => 'default'],
                ['label' => '自定义模板', 'value' => 'diyApiTemp'],
            ],
        ],
        'bgtype' => [
            'label' => '背景类型',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 1,
            'placeholder' => '',
            'help_text' => '',
            'options' => [
                ['label' => '本地资源', 'value' => '0'],
                ['label' => '自定义接口', 'value' => '1'],
            ],
        ],
        'captcha-type' => [
            'label' => '验证码类型',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 1,
            'placeholder' => '',
            'help_text' => '',
            'options' => [
                ['label' => '关闭', 'value' => '0'],
                ['label' => '普通验证码', 'value' => '1'],
                ['label' => '腾讯防水墙', 'value' => '2'],
                ['label' => '极验行为验证', 'value' => '3'],
            ],
        ],
        'create_qrCode' => [
            'label' => '二维码生成方式',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 1,
            'placeholder' => '',
            'help_text' => '',
            'options' => [
                ['label' => '本地生成', 'value' => '1'],
                ['label' => '国际接口', 'value' => '2'],
                ['label' => '国内接口', 'value' => '3'],
            ],
        ],
        'file-type' => [
            'label' => '文件存储方式',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 1,
            'placeholder' => '',
            'help_text' => '',
            'options' => [
                ['label' => '本地', 'value' => '1'],
                ['label' => '阿里云 OSS', 'value' => '2'],
                ['label' => '七牛云', 'value' => '3'],
            ],
        ],
        'logincode-type' => [
            'label' => '登录验证方式',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 1,
            'placeholder' => '',
            'help_text' => '',
            'options' => [
                ['label' => '账号密码', 'value' => '0'],
                ['label' => '短信验证', 'value' => '1'],
                ['label' => '邮箱验证', 'value' => '2'],
                ['label' => '社交登录', 'value' => '3'],
                ['label' => '电报验证', 'value' => '4'],
            ],
        ],
        'mtceType' => [
            'label' => '维护页模板',
            'editor' => 'select',
            'value_type' => 'text',
            'max_length' => 24,
            'placeholder' => '',
            'help_text' => '',
            'options' => [
                ['label' => '标准模板', 'value' => 'default'],
                ['label' => '自定义模板', 'value' => 'diyMtceHtml'],
            ],
        ],
        'qr_codeType' => [
            'label' => '二维码解码方式',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 1,
            'placeholder' => '',
            'help_text' => '',
            'options' => [
                ['label' => 'API 解码', 'value' => '1'],
                ['label' => '本地解码', 'value' => '2'],
            ],
        ],
        'realNameBear' => [
            'label' => '实名费用承担方',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 1,
            'placeholder' => '',
            'help_text' => '',
            'options' => [
                ['label' => '平台承担', 'value' => '0'],
                ['label' => '商户承担', 'value' => '1'],
            ],
        ],
        'realNameType' => [
            'label' => '实名通道类型',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 1,
            'placeholder' => '',
            'help_text' => '',
            'options' => [
                ['label' => '微信/支付宝人脸核验', 'value' => '1'],
                ['label' => '支付宝身份授权', 'value' => '2'],
            ],
        ],
        'regcode-type' => [
            'label' => '注册验证方式',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 1,
            'placeholder' => '',
            'help_text' => '',
            'options' => [
                ['label' => '关闭验证', 'value' => '0'],
                ['label' => '短信验证', 'value' => '1'],
                ['label' => '邮箱验证', 'value' => '2'],
                ['label' => '电报验证', 'value' => '3'],
            ],
        ],
        'reportPos' => [
            'label' => '举报说明位置',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'reportTips' => [
            'label' => '举报说明',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 5000,
            'placeholder' => '',
            'help_text' => '',
        ],
        'retrieve-type' => [
            'label' => '找回方式',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 1,
            'placeholder' => '',
            'help_text' => '',
            'options' => [
                ['label' => '关闭', 'value' => '0'],
                ['label' => '短信验证', 'value' => '1'],
                ['label' => '邮箱验证', 'value' => '2'],
                ['label' => '电报验证', 'value' => '3'],
            ],
        ],
        'merchant_login_drag_verify' => [
            'label' => '商户登录滑动验证',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'merchant_register_drag_verify' => [
            'label' => '商户注册滑动验证',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'merchant_retrieve_drag_verify' => [
            'label' => '找回密码滑动验证',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'smstype' => [
            'label' => '短信服务商',
            'editor' => 'select',
            'value_type' => 'text',
            'max_length' => 16,
            'placeholder' => '',
            'help_text' => '',
            'options' => [
                ['label' => '阿里云', 'value' => 'aliyun'],
                ['label' => '腾讯云', 'value' => 'qcloud'],
                ['label' => '短信宝', 'value' => 'smsbao'],
            ],
        ],
        'software_callback_sign_mode' => [
            'label' => '软件回调签名模式',
            'editor' => 'select',
            'value_type' => 'text',
            'max_length' => 16,
            'placeholder' => '',
            'help_text' => '',
            'options' => [
                ['label' => '基础校验', 'value' => 'compat'],
                ['label' => '强签模式', 'value' => 'strict'],
            ],
        ],
        'software_callback_sign_window' => [
            'label' => '软件回调签名时效',
            'editor' => 'input',
            'value_type' => 'non_negative_integer',
            'max_length' => 6,
            'placeholder' => '300',
            'help_text' => '',        ],
        'shield_key' => [
            'label' => '风控关键词',
            'editor' => 'textarea',
            'value_type' => 'list',
            'max_length' => 5000,
            'placeholder' => "博彩 色情 套现",
            'help_text' => '',
        ],
        'td_notice' => [
            'label' => '支付说明',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 1000,
            'placeholder' => '',
            'help_text' => '',
        ],
        'vip_expire' => [
            'label' => '提前提醒天数',
            'editor' => 'input',
            'value_type' => 'non_negative_integer',
            'max_length' => 6,
            'placeholder' => '3',
            'help_text' => '',        ],
        'wechat_login' => [
            'label' => '微信快捷登录',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 12,
            'placeholder' => '',
            'help_text' => '',
        ],
        'tg_bind_tips' => [
            'label' => '电报绑定提示',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 255,
            'placeholder' => '',
            'help_text' => '',
        ],
        'aff_percentage' => [
            'label' => '推广返佣比例',
            'editor' => 'input',
            'value_type' => 'non_negative_decimal',
            'max_length' => 8,
            'placeholder' => '0.10',
            'help_text' => '',        ],
        'tg_notice_recharge' => [
            'label' => '电报充值通知',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'tg_notice_register' => [
            'label' => '电报注册通知',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'tg_notice_ticket' => [
            'label' => '电报工单通知',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'tg_notice_vip' => [
            'label' => '电报会员通知',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'tg_switch' => [
            'label' => '电报通知开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
        'timeout' => [
            'label' => '订单超时时间',
            'editor' => 'input',
            'value_type' => 'non_negative_integer',
            'max_length' => 6,
            'placeholder' => '180',
            'help_text' => '',
        ],
        'cdkPayUrl' => [
            'label' => '卡密充值地址',
            'editor' => 'input',
            'value_type' => 'url',
            'max_length' => 255,
            'placeholder' => '',
            'help_text' => '',
        ],
        'daily_limit' => [
            'label' => '验证码每日限制',
            'editor' => 'input',
            'value_type' => 'non_negative_integer',
            'max_length' => 6,
            'placeholder' => '10',
            'help_text' => '',
        ],
        'disconnect_minute' => [
            'label' => '掉线判定分钟',
            'editor' => 'input',
            'value_type' => 'non_negative_integer',
            'max_length' => 6,
            'placeholder' => '1',
            'help_text' => '',        ],
        'diy_userId' => [
            'label' => '商户起始 ID',
            'editor' => 'input',
            'value_type' => 'non_negative_integer',
            'max_length' => 12,
            'placeholder' => '10000',
            'help_text' => '',
        ],
        'title' => [
            'label' => '页面标题',
            'editor' => 'input',
            'value_type' => 'text',
            'max_length' => 120,
            'placeholder' => '',
            'help_text' => '',
        ],
        'user_agreement' => [
            'label' => '用户协议',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 5000,
            'placeholder' => '',
            'help_text' => '',
        ],
        'wxpusher_switch' => [
            'label' => '微信推送开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '',
        ],
    ];

    private const AUTO_TEXTAREA_KEYS = [
        'alipayrsaPublicKey',
        'diyMtceHtml',
        'diy_dataClear',
        'diy_demoPay',
        'diy_js',
        'diy_recharge',
        'domain_black',
        'domain_white',
        'home_popup',
        'index_popup',
        'privacy',
        'quotations',
        'reg_popup',
        'reportTips',
        'rsaPrivateKey',
        'securityBindTips',
        'securityPopContent',
        'shield_key',
        'shield_tips',
        'sh_notice',
        'td_notice',
        'user_agreement',
    ];

    private const AUTO_PASSWORD_KEYS = [
        'adminSecurityKey',
        'epaykey_demo',
        'randomKey',
        'smtp-pass',
        'thinkCode',
        'tg_bot_token',
        'wxpusher_appToken',
    ];

    private const AUTO_INTEGER_KEYS = [
        'dataClearDays',
        'daily_limit',
        'disconnect_minute',
        'domainNum',
        'imageSize',
        'orderDisplay',
        'reg_give_vip',
        'timeout',
        'vip_expire',
    ];

    private const AUTO_DECIMAL_KEYS = [
        'aff_percentage',
        'demopay_money',
        'max_orderprice',
        'max_recharge',
        'min_orderprice',
        'min_recharge',
        'paid_reg_price',
        'reg_give_price',
    ];

    private const AUTO_URL_KEYS = [
        'cdkPayUrl',
        'epayurl_demo',
        'pay_api',
        'reportUrl',
    ];

    private const AUTO_PATH_KEYS = [
        'api_bg',
        'bg',
        'diy_userAvatar',
        'favicon',
        'logo',
        'securityIcon',
    ];

    private const GROUPS = [
        [
            'key' => 'basic_display',
            'keys' => [
                'adminMail',
                'api_bg',
                'apiTemp',
                'bg',
                'bgtype',
                'desc',
                'favicon',
                'home_temp',
                'home_url',
                'icp',
                'key',
                'logo',
                'sitename',
                'software_name',
                'title',
            ],
        ],
        [
            'key' => 'template_content',
            'keys' => [
                'demo_theme',
                'diyApiTemp',
                'diy_js',
                'diy_userAvatar',
                'doc_theme',
                'domain_notice',
                'home_popup',
                'index_popup',
                'is_notice',
                'is_quotations',
                'news_theme',
                'privacy',
                'quotations',
                'reg_popup',
                'reportNo',
                'reportPos',
                'reportTips',
                'reportTitle',
                'reportUrl',
                'reportYes',
                'sh_notice',
                'td_notice',
                'user_agreement',
                'user_theme',
            ],
        ],
        [
            'key' => 'transaction_rules',
            'keys' => [
                'alipay',
                'alipayrsaPublicKey',
                'appid',
                'cdkPayUrl',
                'create_qrCode',
                'daily_limit',
                'demopay_money',
                'demopay_name',
                'disconnect_minute',
                'diy_demoPay',
                'diy_orderNo',
                'diy_recharge',
                'epayid_demo',
                'epaykey_demo',
                'epayurl_demo',
                'isDiy_orderNo',
                'is_channelPay',
                'is_pay_api',
                'is_pay_money',
                'is_smOrder',
                'isCdkPay',
                'max_orderprice',
                'min_orderprice',
                'orderDisplay',
                'pay_api',
                'qqpay',
                'qr_codeType',
                'software_callback_sign_mode',
                'software_callback_sign_window',
                'timeout',
                'wechat',
            ],
        ],
        [
            'key' => 'merchant_access',
            'keys' => [
                'aff_percentage',
                'aff_type',
                'bearMoney',
                'diy_userId',
                'domainNum',
                'domain_black',
                'domain_white',
                'forceRealName',
                'is_aff',
                'is_diyUserId',
                'is_domain',
                'is_examine',
                'is_logOff',
                'is_paypage_realname',
                'is_reg',
                'is_reg_give_price',
                'is_reg_give_vip',
                'is_sponsor',
                'is_vip_expire',
                'isRealName',
                'isTicket',
                'max_recharge',
                'min_recharge',
                'paid_reg',
                'paid_reg_price',
                'realNameBear',
                'realNameType',
                'reg_give_price',
                'reg_give_vip',
                'vip_expire',
            ],
        ],
        [
            'key' => 'security_auth',
            'keys' => [
                'adminSecurityKey',
                'captcha-type',
                'code_switch',
                'isAdminSecurity',
                'merchant_login_drag_verify',
                'merchant_register_drag_verify',
                'merchant_retrieve_drag_verify',
                'isSecurity',
                'isSecurityForce',
                'isSecurityLogin',
                'logincode-type',
                'qq_login',
                'randomKey',
                'regcode-type',
                'retrieve-type',
                'rsaPrivateKey',
                'securityBindTips',
                'securityIcon',
                'securityName',
                'securityPopContent',
                'securityPopTitle',
                'shield_key',
                'shield_tips',
                'thinkCode',
                'wechat_login',
            ],
            'prefixes' => [
                'geetest_',
                'tencent_',
            ],
        ],
        [
            'key' => 'notifications',
            'keys' => [
                'diy_codeTemp',
                'diy_loginTips',
                'diy_loseTips',
                'diy_moneyTips',
                'diy_orderTips',
                'diy_regTips',
                'diy_vipTemp',
                'email_switch',
                'SmtpSecure',
                'smstype',
                'tg_admin_id',
                'tg_bind_tips',
                'tg_bot_token',
                'tg_switch',
                'wxpusher_appToken',
                'wxpusher_switch',
            ],
            'prefixes' => [
                'alisms-',
                'smtp-',
                'smsbao-',
                'tensms-',
                'tg_notice_',
            ],
        ],
        [
            'key' => 'storage_integrations',
            'keys' => [
                'file-type',
                'imageSize',
            ],
            'prefixes' => [
                'file-',
                'qiniu-',
            ],
        ],
        [
            'key' => 'maintenance',
            'keys' => [
                'dataClearDays',
                'diyMtceHtml',
                'diy_dataClear',
                'diy_task_key',
                'is_dataClear',
                'isMtce',
                'is_weboff',
                'mtceType',
            ],
        ],
    ];

    private const SENSITIVE_KEYS = [
        'adminSecurityKey',
        'alisms-Secret',
        'diy_task_key',
        'epaykey_demo',
        'file-accessKeyId',
        'file-accessKeySecret',
        'geetest_CaptchaKey',
        'qiniu-AK',
        'qiniu-SK',
        'randomKey',
        'rsaPrivateKey',
        'smtp-pass',
        'smsbao-pass',
        'tencent_CaptchaKey',
        'tensms-Secret',
        'tg_bot_token',
        'thinkCode',
        'wxpusher_appToken',
    ];

    private const NON_SENSITIVE_KEYS = [
        'key',
        'shield_key',
    ];

    private const HTML_KEYS = [
        'diyApiTemp',
        'diyMtceHtml',
        'privacy',
        'quotations',
        'reportTips',
        'user_agreement',
    ];

    private const LIST_KEYS = [
        'diy_dataClear',
        'diy_demoPay',
        'diy_recharge',
        'domain_black',
        'domain_white',
        'pay_api',
        'shield_key',
    ];

    private const BOOLEAN_KEYS = [
        'code_switch',
        'email_switch',
        'forceRealName',
        'home_url',
        'is_aff',
        'is_channelPay',
        'is_dataClear',
        'is_diyUserId',
        'is_domain',
        'is_examine',
        'is_logOff',
        'is_notice',
        'is_pay_api',
        'is_pay_money',
        'is_paypage_realname',
        'is_quotations',
        'is_reg',
        'is_reg_give_price',
        'is_reg_give_vip',
        'is_smOrder',
        'is_sponsor',
        'is_vip_expire',
        'isDiy_orderNo',
        'is_weboff',
        'isAdminSecurity',
        'isCdkPay',
        'isMtce',
        'isRealName',
        'isSecurity',
        'isSecurityForce',
        'isSecurityLogin',
        'isTicket',
        'paid_reg',
        'tg_notice_recharge',
        'tg_notice_register',
        'tg_notice_ticket',
        'tg_notice_vip',
        'tg_switch',
        'wxpusher_switch',
    ];

    private const FORM_EXCLUDED_KEYS = [
        'alipay',
        'qqpay',
        'wechat',
    ];

    private const HIDDEN_KEYS = [
        'home_temp',
        'user_theme',
        'demo_theme',
        'doc_theme',
        'news_theme',
    ];

    public static function buildItems(array $config, array $databaseConfig): array
    {
        $keys = [];
        foreach (array_keys($config) as $key) {
            $name = trim((string)$key);
            if ($name !== '' && !self::isHiddenKey($name)) {
                $keys[$name] = true;
            }
        }

        foreach (array_keys($databaseConfig) as $key) {
            $name = trim((string)$key);
            if ($name !== '' && !self::isHiddenKey($name)) {
                $keys[$name] = true;
            }
        }

        foreach (self::catalogKeys() as $key) {
            $keys[$key] = true;
        }

        $names = array_keys($keys);
        sort($names, SORT_STRING);

        $items = [];
        foreach ($names as $name) {
            $value = $config[$name] ?? $databaseConfig[$name] ?? '';
            $rawValue = is_scalar($value) ? trim((string)$value) : '';
            $items[] = self::formatItem($name, $rawValue, array_key_exists($name, $databaseConfig));
        }

        return $items;
    }

    public static function buildGroups(array $items, string $groupFilter = '', string $keyword = ''): array
    {
        $filtered = array_values(array_filter($items, static function (array $item) use ($groupFilter, $keyword): bool {
            if ($groupFilter !== '' && $groupFilter !== 'all' && $item['group'] !== $groupFilter) {
                return false;
            }

            if ($keyword === '') {
                return true;
            }

            return self::contains($item['key'], $keyword)
                || self::contains($item['label'], $keyword)
                || self::contains($item['raw_value'], $keyword);
        }));

        $grouped = [];
        foreach (self::groupKeys() as $groupKey) {
            $groupItems = array_values(array_filter($filtered, static fn (array $item): bool => $item['group'] === $groupKey));
            if ($groupItems === []) {
                continue;
            }

            usort($groupItems, static function (array $left, array $right): int {
                if ($left['filled'] !== $right['filled']) {
                    return $left['filled'] ? -1 : 1;
                }

                return strcmp($left['key'], $right['key']);
            });

            $grouped[] = [
                'key' => $groupKey,
                'item_count' => count($groupItems),
                'filled_count' => count(array_filter($groupItems, static fn (array $item): bool => $item['filled'])),
                'items' => array_map(static function (array $item): array {
                    unset($item['raw_value']);
                    return $item;
                }, $groupItems),
            ];
        }

        return $grouped;
    }

    public static function buildGroupOptions(array $items): array
    {
        $options = [];
        foreach (self::groupKeys() as $groupKey) {
            $groupItems = array_values(array_filter($items, static fn (array $item): bool => $item['group'] === $groupKey));
            if ($groupItems === []) {
                continue;
            }

            $options[] = [
                'key' => $groupKey,
                'count' => count($groupItems),
                'filled_count' => count(array_filter($groupItems, static fn (array $item): bool => $item['filled'])),
            ];
        }

        return $options;
    }

    public static function editableDefinition(string $key): ?array
    {
        if (self::isHiddenKey($key)) {
            return null;
        }

        $definition = self::EDITABLE_FIELDS[$key] ?? self::autoEditableDefinition($key);
        if ($definition === null) {
            return null;
        }

        return self::enrichEditableDefinition($key, $definition);
    }

    public static function editableFormDefinition(string $key): ?array
    {
        return self::EDITABLE_FORM_GROUPS[$key] ?? null;
    }

    public static function isEditable(string $key): bool
    {
        return self::editableDefinition($key) !== null;
    }

    public static function editableFormKeys(string $groupKey): array
    {
        $definition = self::editableFormDefinition($groupKey);

        return array_values(array_filter(
            $definition['fields'] ?? [],
            static fn (mixed $key): bool => is_string($key) && !self::isFormExcludedKey($key)
        ));
    }

    public static function buildEditableForms(array $items): array
    {
        $index = [];
        $editableByGroup = [];
        foreach ($items as $item) {
            $index[$item['key']] = $item;
            if (!($item['editable'] ?? false) || self::isFormExcludedKey((string)($item['key'] ?? ''))) {
                continue;
            }

            $editableByGroup[(string)($item['group'] ?? 'other')][] = $item;
        }

        $forms = [];
        foreach (self::EDITABLE_FORM_GROUPS as $groupKey => $definition) {
            $fields = [];
            $seen = [];
            foreach ($definition['fields'] as $fieldKey) {
                if (
                    !isset($index[$fieldKey])
                    || !$index[$fieldKey]['editable']
                    || self::isFormExcludedKey($fieldKey)
                    || (string)($index[$fieldKey]['group'] ?? '') !== $groupKey
                ) {
                    continue;
                }

                $field = $index[$fieldKey];
                unset($field['raw_value']);
                $fields[] = $field;
                $seen[$fieldKey] = true;
            }

            $extraFields = array_values(array_filter(
                $editableByGroup[$groupKey] ?? [],
                static fn (array $field): bool => !isset($seen[(string)($field['key'] ?? '')])
            ));
            usort($extraFields, static function (array $left, array $right): int {
                return strcmp((string)($left['key'] ?? ''), (string)($right['key'] ?? ''));
            });

            foreach ($extraFields as $field) {
                unset($field['raw_value']);
                $fields[] = $field;
            }

            if ($fields === []) {
                continue;
            }

            $forms[] = [
                'key' => $groupKey,
                'title' => self::normalizeUiText((string)($definition['title'] ?? ''), self::humanize($groupKey)),
                'description' => self::normalizeUiText((string)($definition['description'] ?? '')),
                'fields' => $fields,
            ];
        }

        return $forms;
    }

    public static function sanitizeEditableValue(string $key, mixed $value): string
    {
        $definition = self::editableDefinition($key);
        if ($definition === null) {
            throw new \InvalidArgumentException('当前阶段该配置项不可编辑');
        }

        $valueType = (string)($definition['value_type'] ?? 'text');
        $maxLength = (int)($definition['max_length'] ?? 0);

        if ($valueType === 'boolean') {
            return self::normalizeBoolean($value) ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('配置值必须为标量');
        }

        $normalized = trim((string)$value);
        if ($maxLength > 0 && self::length($normalized) > $maxLength) {
            throw new \InvalidArgumentException('配置值长度超出限制');
        }

        if ($valueType === 'email' && $normalized !== '' && !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('管理员邮箱格式不正确');
        }

        if ($valueType === 'non_negative_integer') {
            $sanitized = self::sanitizeNonNegativeInteger($normalized);
        } elseif ($valueType === 'non_negative_decimal') {
            $sanitized = self::sanitizeNonNegativeDecimal($normalized);
        } elseif ($valueType === 'list') {
            $sanitized = self::sanitizeListValue($normalized);
        } else {
            $sanitized = $normalized;
        }

        if ($valueType === 'url' && $sanitized !== '' && filter_var($sanitized, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('请填写完整且可访问的链接地址');
        }

        if ($key === 'pay_api' && $sanitized !== '') {
            foreach (explode(',', $sanitized) as $url) {
                $line = trim($url);
                if ($line === '') {
                    continue;
                }

                if ($line === '/' || str_starts_with($line, '/')) {
                    continue;
                }

                if (filter_var($line, FILTER_VALIDATE_URL) === false) {
                    throw new \InvalidArgumentException('API 地址中存在无效链接，请逐项检查');
                }
            }
        }

        if ($key === 'aff_percentage' && $sanitized !== '') {
            $ratio = (float)$sanitized;
            if ($ratio < 0 || $ratio > 1) {
                throw new \InvalidArgumentException('返佣比例需填写 0 到 1 之间的小数');
            }
        }

        if ($sanitized === '') {
            return '';
        }

        $options = $definition['options'] ?? [];
        if (is_array($options) && $options !== []) {
            $sanitized = self::normalizeSelectableValue($key, $sanitized);
            $allowedValues = array_map(
                static fn (array $option): string => (string)($option['value'] ?? ''),
                array_filter($options, static fn (mixed $option): bool => is_array($option))
            );

            if (!in_array($sanitized, $allowedValues, true)) {
                throw new \InvalidArgumentException('配置值不在允许的范围内');
            }
        }

        return $sanitized;
    }

    public static function sanitizeEditableGroupValues(string $groupKey, array $values): array
    {
        if (self::editableFormDefinition($groupKey) === null) {
            throw new \InvalidArgumentException('当前阶段该配置分组不可编辑');
        }
        $sanitized = [];

        foreach ($values as $key => $value) {
            $name = trim((string)$key);
            if ($name === '') {
                continue;
            }

            if (!self::isEditableGroupField($groupKey, $name)) {
                throw new \InvalidArgumentException('配置项不属于当前可编辑分组');
            }

            $sanitized[$name] = self::sanitizeEditableValue($name, $value);
        }

        return $sanitized;
    }

    private static function isEditableGroupField(string $groupKey, string $key): bool
    {
        return !self::isFormExcludedKey($key)
            && self::resolveGroup($key) === $groupKey
            && self::isEditable($key);
    }

    private static function isFormExcludedKey(string $key): bool
    {
        return in_array($key, self::FORM_EXCLUDED_KEYS, true);
    }

    private static function autoEditor(string $key): string
    {
        if (in_array($key, self::BOOLEAN_KEYS, true)) {
            return 'switch';
        }

        if (self::dynamicOptionsForConfigKey($key) !== []) {
            return 'select';
        }

        if (in_array($key, self::AUTO_PASSWORD_KEYS, true)) {
            return 'password';
        }

        if (
            in_array($key, self::AUTO_TEXTAREA_KEYS, true)
            || in_array($key, self::LIST_KEYS, true)
            || in_array($key, self::HTML_KEYS, true)
        ) {
            return 'textarea';
        }

        return 'input';
    }

    private static function autoValueType(string $key): string
    {
        if (in_array($key, self::BOOLEAN_KEYS, true)) {
            return 'boolean';
        }

        if ($key === 'adminMail') {
            return 'email';
        }

        if (in_array($key, self::LIST_KEYS, true)) {
            return 'list';
        }

        if (in_array($key, self::AUTO_INTEGER_KEYS, true)) {
            return 'non_negative_integer';
        }

        if (in_array($key, self::AUTO_DECIMAL_KEYS, true)) {
            return 'non_negative_decimal';
        }

        if (in_array($key, self::AUTO_URL_KEYS, true)) {
            return 'url';
        }

        if (in_array($key, self::AUTO_PATH_KEYS, true)) {
            return 'path';
        }

        return 'text';
    }

    private static function autoMaxLength(string $editor, string $valueType): int
    {
        if ($editor === 'switch') {
            return 1;
        }

        if ($editor === 'select') {
            return 64;
        }

        if ($editor === 'password') {
            return 1024;
        }

        if ($editor === 'textarea' || $valueType === 'list' || $valueType === 'text') {
            return 5000;
        }

        if ($valueType === 'url' || $valueType === 'path') {
            return 2048;
        }

        if ($valueType === 'non_negative_integer' || $valueType === 'non_negative_decimal') {
            return 32;
        }

        if ($valueType === 'email') {
            return 255;
        }

        return 255;
    }

    private static function autoEditableDefinition(string $key): ?array
    {
        if (self::resolveGroup($key) === 'other') {
            return null;
        }

        $editor = self::autoEditor($key);
        $valueType = self::autoValueType($key);

        return [
            'label' => self::displayLabel($key),
            'editor' => $editor,
            'value_type' => $valueType,
            'max_length' => self::autoMaxLength($editor, $valueType),
            'placeholder' => self::autoPlaceholder(self::displayLabel($key), $editor, $valueType),
            'help_text' => self::autoHelpText($key, $editor, $valueType),
        ];
    }

    private static function dynamicOptionsForConfigKey(string $key): array
    {
        return match ($key) {
            'qq_login' => self::quickLoginOptions('qq', '未配置 QQ 登录渠道'),
            'wechat_login' => self::quickLoginOptions('wechat', '未配置微信登录渠道'),
            'reg_give_vip' => self::vipPackageOptions(),
            'home_temp' => [
                [
                    'label' => 'Index99 首页模板',
                    'value' => 'index99',
                ],
            ],
            'demo_theme', 'doc_theme', 'news_theme', 'user_theme' => [
                [
                    'label' => '标准主题',
                    'value' => 'default',
                ],
            ],
            default => [],
        };
    }

    private static function enrichEditableDefinition(string $key, array $definition): array
    {
        $dynamicOptions = self::dynamicOptionsForConfigKey($key);
        if ($dynamicOptions !== []) {
            $definition['editor'] = 'select';
            $definition['options'] = $dynamicOptions;
        }

        return self::sanitizeEditableDefinition($key, $definition);
    }

    private static function sanitizeEditableDefinition(string $key, array $definition): array
    {
        $editor = (string)($definition['editor'] ?? 'input');
        $valueType = (string)($definition['value_type'] ?? 'text');
        $rawLabel = (string)($definition['label'] ?? '');
        $label = trim($rawLabel) === '' || self::looksLikeMojibake($rawLabel)
            ? self::displayLabel($key)
            : self::normalizeUiText($rawLabel, self::displayLabel($key));

        $definition['label'] = $label;
        $definition['placeholder'] = self::sanitizeDefinitionPlaceholder(
            $key,
            (string)($definition['placeholder'] ?? ''),
            $label,
            $editor,
            $valueType
        );
        $definition['help_text'] = self::sanitizeDefinitionHelpText(
            $key,
            (string)($definition['help_text'] ?? ''),
            $editor,
            $valueType
        );

        if (isset($definition['options']) && is_array($definition['options'])) {
            $normalizedOptions = [];
            foreach ($definition['options'] as $option) {
                if (!is_array($option)) {
                    continue;
                }

                $value = self::normalizeSelectableValue($key, (string)($option['value'] ?? ''));
                $normalizedOptions[] = [
                    'label' => self::normalizeOptionLabel($key, (string)($option['label'] ?? ''), $value),
                    'value' => $value,
                ];
            }
            $definition['options'] = $normalizedOptions;
        }

        return $definition;
    }

    private static function sanitizeDefinitionPlaceholder(
        string $key,
        string $placeholder,
        string $label,
        string $editor,
        string $valueType
    ): string {
        if (trim($placeholder) === '' || self::looksLikeMojibake($placeholder)) {
            $override = self::placeholderOverride($key);
            if ($override !== '') {
                return $override;
            }

            return self::autoPlaceholder($label, $editor, $valueType);
        }

        return self::normalizeUiText($placeholder);
    }

    private static function sanitizeDefinitionHelpText(
        string $key,
        string $helpText,
        string $editor,
        string $valueType
    ): string {
        if (trim($helpText) === '' || self::looksLikeMojibake($helpText)) {
            $override = self::helpTextOverride($key);
            if ($override !== '') {
                return $override;
            }

            return self::autoHelpText($key, $editor, $valueType);
        }

        return self::normalizeUiText($helpText);
    }

    private static function normalizeOptionLabel(string $key, string $label, string $value): string
    {
        if (trim($label) === '' || self::looksLikeMojibake($label)) {
            $fallback = self::optionLabelFallback($key, $value);
            if ($fallback !== '') {
                return $fallback;
            }
        }

        return self::normalizeUiText($label, self::optionLabelFallback($key, $value));
    }

    private static function optionLabelFallback(string $key, string $value): string
    {
        return match ($key) {
            'SmtpSecure' => match ($value) {
                '' => '无加密',
                'ssl' => 'SSL/TLS',
                'tls' => 'STARTTLS',
                default => strtoupper($value),
            },
            'aff_type' => match ($value) {
                '0' => '充值返佣',
                '1' => '会员购买返佣',
                default => $value,
            },
            'apiTemp', 'mtceType' => match ($value) {
                'default' => '标准模板',
                'diyApiTemp', 'diyMtceHtml' => '自定义模板',
                default => $value,
            },
            'bgtype' => match ($value) {
                '0' => '本地资源',
                '1' => '自定义API',
                default => $value,
            },
            'captcha-type' => match ($value) {
                '0' => '关闭',
                '1' => '普通验证码',
                '2' => '腾讯防水墙',
                '3' => '极验行为验证',
                default => $value,
            },
            'create_qrCode' => match ($value) {
                '1' => '本地生成',
                '2' => '国际接口',
                '3' => '国内接口',
                default => $value,
            },
            'file-type' => match ($value) {
                '1' => '本地',
                '2' => '阿里云OSS',
                '3' => '七牛云',
                default => $value,
            },
            'logincode-type' => match ($value) {
                '0' => '账号密码',
                '1' => '短信验证',
                '2' => '邮箱验证',
                '3' => '社交登录',
                '4' => '电报验证',
                default => $value,
            },
            'regcode-type', 'retrieve-type' => match ($value) {
                '0' => '关闭验证',
                '1' => '短信验证',
                '2' => '邮箱验证',
                '3' => '电报验证',
                default => $value,
            },
            'merchant_login_drag_verify',
            'merchant_register_drag_verify',
            'merchant_retrieve_drag_verify' => match ($value) {
                '1' => '开启',
                '0' => '关闭',
                default => $value,
            },
            'qr_codeType' => match ($value) {
                '1' => 'API解码',
                '2' => '本地解码',
                default => $value,
            },
            'realNameBear' => match ($value) {
                '0' => '平台承担',
                '1' => '商户承担',
                default => $value,
            },
            'smstype' => match ($value) {
                'aliyun' => '阿里云短信',
                'qcloud' => '腾讯云短信',
                'smsbao' => '短信宝',
                default => $value,
            },
            'software_callback_sign_mode' => match ($value) {
                'compat' => '基础校验',
                'strict' => '强签模式',
                default => $value,
            },
            default => '',
        };
    }

    private static function placeholderOverride(string $key): string
    {
        return match ($key) {
            'adminMail' => 'support@aipay.cn',
            'demopay_money' => '0.01',
            'demopay_name' => '支付测试收款商户',
            'diy_codeTemp' => '您的验证码是 [code]',
            'diy_loginTips' => '账号 [login_uid] 于 [login_time] 在 [login_ip] 登录',
            'diy_demoPay' => 'wxpay alipay qqpay',
            'diy_loseTips' => '收款账号 [account_code] 已于 [lose_time] 掉线',
            'diy_moneyTips' => '当前余额低于 [money] 元',
            'diy_orderTips' => '您有新的订单 [out_trade_no]',
            'diy_regTips' => '欢迎新商户 [userName]',
            'diy_vipTemp' => '[sitename] VIP 将于 [day] 天后到期',
            'domainNum' => '0',
            'domain_black' => "blocked.example.com
spam.example.com",
            'domain_white' => "pay.example.com
api.example.com",
            'epayid_demo' => '请输入支付测试商户号',
            'epaykey_demo' => '请输入支付测试密钥',
            'epayurl_demo' => 'https://pay.example.com/submit.php',
            'favicon' => '/upload/images/favicon.ico',
            'logo' => '/upload/images/logo.png',
            'max_orderprice' => '1000',
            'max_recharge' => '1000',
            'min_orderprice' => '0.01',
            'min_recharge' => '0',
            'orderDisplay' => '10',
            'paid_reg_price' => '0.01',
            'pay_api' => "https://api.example.com/
https://api2.example.com/",
            'reg_give_price' => '0.00',
            'shield_key' => "博彩
色情
套现",
            'timeout' => '180',
            'cdkPayUrl' => 'https://card.example.com/',
            'daily_limit' => '10',
            'disconnect_minute' => '1',
            'diy_userId' => '10000',
            default => '',
        };
    }

    private static function helpTextOverride(string $key): string
    {
        return match ($key) {
            'adminMail' => '接收系统告警、业务通知和关键操作提醒的管理员邮箱地址。',
            'desc' => '用于首页展示、搜索描述和系统简介。',
            'demopay_money' => '用于支付测试页面默认金额展示。',
            'demopay_name' => '用于支付测试页和收银台展示的收款人名称。',
            'diy_codeTemp' => '支持 [code] 变量。',
            'diyApiTemp' => '启用自定义接口模板后，接口展示页将按这里维护的内容渲染。',
            'diy_loginTips' => '支持 [login_uid]、[login_ip]、[login_time] 变量。',
            'diy_demoPay' => '每行一个支付方式编码，用于支付测试页展示。',
            'diy_loseTips' => '支持 [account_id]、[account_type]、[account_code]、[lose_time] 变量。',
            'diy_moneyTips' => '支持 [money] 变量，用于余额不足提醒。',
            'diy_orderTips' => '用于新订单通知和订单到账提示内容。',
            'diy_regTips' => '支持 [userName] 变量，用于商户注册成功提示。',
            'diy_vipTemp' => '支持 [sitename]、[day] 变量，用于 VIP 到期提醒。',
            'domain_notice' => '显示在域名绑定、审核和调用场景附近的说明文字。',
            'domainNum' => '限制单个商户每日可新增的域名数量，0 表示不限制。',
            'domain_black' => '每行一个域名，命中后将禁止绑定或访问相关业务功能。',
            'domain_white' => '每行一个域名，命中后优先视为可信域名。',
            'email_switch' => '开启后可使用邮箱通知、验证码和邮件提醒能力。',
            'epayid_demo' => '用于协议调用、支付测试和测试下单。',
            'epaykey_demo' => '用于支付测试和接口验签。',
            'epayurl_demo' => '填写易支付协议网关地址，用于支付测试。',
            'favicon' => '填写站点图标文件路径或完整 URL。',
            'forceRealName' => '开启后，商户需先完成实名认证才可使用相关功能。',
            'home_popup', 'index_popup', 'privacy', 'reg_popup', 'user_agreement' => '支持富文本内容。',
            'icp' => '显示在首页底部和公共页面页脚。',
            'is_aff' => '开启后支持推广返佣、分销统计和相关通知。',
            'is_channelPay' => '开启后商户可在通道管理中发起测试订单。',
            'isCdkPay' => '开启后商户中心可使用卡密充值与兑换能力。',
            'is_domain' => '开启后商户可绑定、审核和管理自有域名。',
            'is_examine' => '开启后新增商户、域名等业务需要后台审核。',
            'is_notice' => '开启后显示公告中心与前台公告内容。',
            'is_logOff' => '开启后商户可在前台申请账号注销。',
            'is_pay_api' => '开启后，对外网关地址可改为这里维护的自定义接口线路。',
            'is_reg' => '控制商户自主注册入口是否开放。',
            'is_reg_give_price' => '开启后新注册商户将自动获得赠送余额。',
            'is_reg_give_vip' => '开启后新注册商户将自动获得指定会员套餐。',
            'isRealName' => '开启后显示实名认证相关配置与业务能力。',
            'is_smOrder' => '开启后商户端显示手动补单按钮。',
            'is_sponsor' => '控制首页或公共页赞助位内容展示。',
            'isTicket' => '开启后商户可提交和查看工单。',
            'is_vip_expire' => '开启后按提醒天数向商户发送 VIP 到期通知。',
            'logo' => '填写网站标志文件路径或完整地址。',
            'max_orderprice' => '单笔支付订单允许提交的最大金额。',
            'max_recharge' => '单笔余额充值允许提交的最大金额。',
            'min_recharge' => '单笔余额充值允许提交的最小金额。',
            'min_orderprice' => '单笔支付订单允许提交的最小金额。',
            'orderDisplay' => '后台和商户端表格默认每页显示条数。',
            'paid_reg' => '开启后新商户注册需先完成付费开通。',
            'pay_api' => '每行一个地址，用于对外展示或分发给商户的接口入口地址。',
            'paid_reg_price' => '开启付费注册后，商户注册时需要支付的金额。',
            'qq_login' => '关闭或选择一个已配置的 QQ 登录渠道。',
            'reg_give_price' => '注册成功后自动发放到商户余额中的金额。',
            'reg_give_vip' => '注册成功后自动赠送给商户的会员套餐。',
            'sh_notice' => '显示在商户或域名审核场景附近。',
            'sitename' => '显示在首页、商户端和公共页面中的站点名称。',
            'software_name' => '用于软件监控上报、客户端显示和系统对外标识。',
            'SmtpSecure' => '选择发送邮件时使用的加密方式。',
            'aff_type' => '决定返佣按充值金额结算，还是按会员购买金额结算。',
            'bearMoney' => '当实名认证费用由商户承担时，将按这里的金额从商户余额扣除。',
            'apiTemp' => '用于接口展示页的模板方案。',
            'bgtype' => '选择登录页、首页等背景资源的获取方式。',
            'captcha-type' => '用于登录、注册或支付测试等场景的验证码能力。',
            'merchant_login_drag_verify' => '开启后，商户登录页提交前需要先完成滑动验证。',
            'merchant_register_drag_verify' => '开启后，商户注册页发送验证码和提交注册前都需要先完成滑动验证。',
            'merchant_retrieve_drag_verify' => '开启后，找回密码页发送验证码和重置密码前都需要先完成滑动验证。',
            'create_qrCode' => '选择系统生成二维码图片时所使用的服务。',
            'file-type' => '上传素材、二维码和凭证图片时所使用的文件存储方案。',
            'logincode-type' => '用于前台登录方式选择。',
            'mtceType' => '系统进入维护模式后前台展示的模板方案。',
            'qr_codeType' => '支付插件需要解析二维码内容时所使用的解析方式。',
            'realNameBear' => '决定实名认证费用由平台承担还是由商户承担。',
            'realNameType' => '选择商户实名认证时使用的通道类型。',
            'regcode-type' => '用于商户注册验证码发送方式选择。',
            'reportPos' => '开启后，举报说明可在弹窗或页面内嵌位置展示。',
            'reportTips' => '支持富文本，用于举报页或举报弹窗中的说明内容。',
            'retrieve-type' => '用于密码找回验证码发送方式选择。',
            'smstype' => '选择短信服务商，并同步完成对应密钥配置。',
            'software_callback_sign_mode' => '基础校验仅检查必要参数；强签模式会同时校验签名与时间窗口。',
            'software_callback_sign_window' => '强签模式下允许的时间窗口，默认 300 秒。',
            'shield_key' => '每行一个关键词，命中后会触发风控拦截或风险提示。',
            'td_notice' => '显示在支付页、测试支付和下单场景附近的说明内容。',
            'vip_expire' => '会员到期前多少天开始提醒，建议填写 1 到 7 天。',
            'wechat_login' => '关闭或选择一个已配置的微信登录渠道。',
            'tg_bind_tips' => '显示在商户绑定电报账号时的说明内容。',
            'aff_percentage' => '填写 0 到 1 之间的小数，例如 0.10 表示 10%。',
            'tg_notice_recharge' => '开启后通过电报发送充值相关通知。',
            'tg_notice_register' => '开启后通过电报发送商户注册通知。',
            'tg_notice_ticket' => '开启后通过电报发送工单消息通知。',
            'tg_notice_vip' => '开启后通过电报发送会员相关提醒。',
            'tg_switch' => '开启后显示并启用电报相关能力。',
            'timeout' => '支付订单超过该时长未完成将按超时处理。',
            'cdkPayUrl' => '用于商户中心跳转至卡密充值页面或外部卡密系统。',
            'daily_limit' => '限制单个目标每日可发送的验证码次数，超出后拒绝继续发送。',
            'disconnect_minute' => '超过该分钟数未收到软件心跳或上报时判定掉线，最小 1 分钟。',
            'diy_userId' => '开启自定义商户编号后，新商户编号将从这里的数值开始递增分配。',
            'title' => '显示在浏览器标题栏和搜索引擎标题中。',
            'wxpusher_switch' => '开启后可使用微信推送消息能力。',
            default => '',
        };
    }

    private static function autoPlaceholder(string $label, string $editor, string $valueType): string
    {
        if ($editor === 'switch') {
            return '';
        }

        if ($editor === 'select') {
            return '请选择';
        }

        if ($valueType === 'list') {
            return '每行一项，也可使用英文逗号分隔';
        }

        if ($valueType === 'non_negative_integer') {
            return '请输入非负整数';
        }

        if ($valueType === 'non_negative_decimal') {
            return '请输入非负数值';
        }

        if ($valueType === 'url') {
            return '请输入完整链接或接口地址';
        }

        if ($valueType === 'path') {
            return '请输入图片或文件路径';
        }

        return sprintf('请输入%s', $label);
    }

    private static function autoHelpText(string $key, string $editor, string $valueType): string
    {
        if ($editor === 'password') {
            return '默认以脱敏方式展示，保存后会覆盖当前已配置的值。';
        }

        if ($valueType === 'list') {
            return '支持英文逗号、中文逗号或换行分隔；保存时会自动清理空项并去重。';
        }

        if (in_array($key, self::AUTO_PATH_KEYS, true)) {
            return '支持相对路径或完整资源链接。';
        }

        if ($valueType === 'url') {
            return '请填写可直接访问的完整地址。';
        }

        if ($valueType === 'non_negative_integer' || $valueType === 'non_negative_decimal') {
            return '仅支持非负数值。';
        }

        return '';
    }

    private static function sanitizeListValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $segments = preg_split('/[\\r\\n,，]+/u', $value) ?: [];
        $segments = array_values(array_filter(array_map(static fn (string $item): string => trim($item), $segments), static fn (string $item): bool => $item !== ''));

        return implode(',', array_unique($segments));
    }

    private static function normalizeSelectableValue(string $key, string $value): string
    {
        return match ($key) {
            'SmtpSecure' => match (strtolower(trim($value))) {
                'none', '' => '',
                'ssl', 'smtps' => 'ssl',
                'tls', 'starttls' => 'tls',
                default => trim($value),
            },
            'smstype' => match (strtolower(trim($value))) {
                'alisms' => 'aliyun',
                'tensms' => 'qcloud',
                default => strtolower(trim($value)),
            },
            'qq_login', 'wechat_login', 'reg_give_vip' => trim($value) === '' ? '0' : trim($value),
            default => trim($value),
        };
    }

    private static function readThemeMeta(string $stylePath): array
    {
        if (!is_file($stylePath) || filesize($stylePath) > 262144) {
            return [];
        }

        $content = @file_get_contents($stylePath);
        if (!is_string($content) || $content === '') {
            return [];
        }

        $meta = [];
        foreach (['ThemeName', 'Description', 'Version'] as $key) {
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*[:=]\s*(.*?)\s*$/mi', $content, $matches) === 1) {
                $meta[strtolower($key)] = trim((string)($matches[1] ?? ''));
            }
        }

        return $meta;
    }

    private static function formatItem(string $key, string $rawValue, bool $fromDatabase): array
    {
        $group = self::resolveGroup($key);
        $type = self::detectType($key, $rawValue);
        $filled = $rawValue !== '';
        $masked = self::isSensitive($key) && $filled;
        $displayValue = $masked ? self::maskValue($rawValue) : $rawValue;
        $previewValue = self::previewValue($key, $displayValue, $masked, $type);
        $editableDefinition = self::editableDefinition($key);
        $editableValue = $editableDefinition !== null
            ? self::normalizeSelectableValue($key, $rawValue)
            : '';
        $options = [];
        foreach (($editableDefinition['options'] ?? []) as $option) {
            if (!is_array($option)) {
                continue;
            }

            $options[] = [
                'label' => self::normalizeOptionLabel(
                    $key,
                    (string)($option['label'] ?? ''),
                    self::normalizeSelectableValue($key, (string)($option['value'] ?? ''))
                ),
                'value' => self::normalizeSelectableValue($key, (string)($option['value'] ?? '')),
            ];
        }

        if (($editableDefinition['editor'] ?? null) === 'select') {
            $selectedLabel = self::selectedOptionLabel($options, $editableValue);
            if ($selectedLabel !== null && $selectedLabel !== '') {
                $previewValue = $selectedLabel;
            }
        }

        return [
            'key' => $key,
            'label' => $editableDefinition['label'] ?? self::displayLabel($key),
            'group' => $group,
            'type' => $type,
            'value' => $displayValue,
            'editable_value' => $editableValue,
            'preview_value' => self::preview($previewValue),
            'filled' => $filled,
            'masked' => $masked,
            'source' => $fromDatabase ? 'database' : 'default',
            'length' => self::length($rawValue),
            'has_line_breaks' => str_contains($rawValue, "\n"),
            'editable' => $editableDefinition !== null,
            'editor' => $editableDefinition['editor'] ?? null,
            'placeholder' => $editableDefinition['placeholder'] ?? '',
            'help_text' => $editableDefinition['help_text'] ?? '',
            'max_length' => $editableDefinition['max_length'] ?? null,
            'options' => $options,
            'raw_value' => $rawValue,
        ];
    }

    private static function resolveGroup(string $key): string
    {
        foreach (self::GROUPS as $group) {
            if (in_array($key, $group['keys'] ?? [], true)) {
                return $group['key'];
            }

            foreach ($group['prefixes'] ?? [] as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    return $group['key'];
                }
            }
        }

        return 'other';
    }

    private static function detectType(string $key, string $rawValue): string
    {
        if (self::isSensitive($key)) {
            return 'secret';
        }

        if (in_array($key, self::HTML_KEYS, true)) {
            return 'html';
        }

        if (in_array($key, self::LIST_KEYS, true)) {
            return 'list';
        }

        if (in_array($key, self::BOOLEAN_KEYS, true)) {
            return 'boolean';
        }

        if (self::isImageValue($rawValue)) {
            return 'image';
        }

        if (self::isUrlValue($rawValue)) {
            return 'url';
        }

        if ($rawValue !== '' && is_numeric($rawValue)) {
            return 'number';
        }

        return 'text';
    }

    private static function quickLoginOptions(string $type, string $emptyLabel): array
    {
        static $cache = [];
        $cacheKey = $type . '|' . $emptyLabel;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $options = [
            [
                'label' => $emptyLabel,
                'value' => '0',
            ],
        ];

        try {
            $rows = Db::table(BusinessTable::quickLogin())
                ->select('id', 'name', 'status')
                ->where('type', $type)
                ->orderBy('status')
                ->orderByDesc('id')
                ->get()
                ->toArray();
        } catch (\Throwable) {
            return $cache[$cacheKey] = $options;
        }

        foreach ($rows as $row) {
            $item = (array)$row;
            $quickLoginId = (int)($item['id'] ?? 0);
            if ($quickLoginId <= 0) {
                continue;
            }

            $label = trim((string)($item['name'] ?? ''));
            if ($label === '') {
                $label = $type === 'qq' ? 'QQ 登录配置' : '微信登录配置';
            }

            if ((int)($item['status'] ?? 0) !== 1) {
                $label .= '（已停用）';
            }

            $options[] = [
                'label' => $label,
                'value' => (string)$quickLoginId,
            ];
        }

        return $cache[$cacheKey] = $options;
    }

    private static function vipPackageOptions(): array
    {
        static $cache;
        if (is_array($cache)) {
            return $cache;
        }

        $options = [
            [
                'label' => '请选择赠送的会员套餐',
                'value' => '0',
            ],
        ];

        try {
            $rows = Db::table(BusinessTable::vip())
                ->select('id', 'name', 'status', 'viptime', 'sort')
                ->orderByRaw('CAST(COALESCE(sort, 0) AS UNSIGNED) asc')
                ->orderBy('id')
                ->get()
                ->toArray();
        } catch (\Throwable) {
            return $cache = $options;
        }

        foreach ($rows as $row) {
            $item = (array)$row;
            $vipId = (int)($item['id'] ?? 0);
            if ($vipId <= 0) {
                continue;
            }

            $label = trim((string)($item['name'] ?? ''));
            if ($label === '') {
                $label = '会员套餐 #' . $vipId;
            }

            $vipDays = max(0, (int)($item['viptime'] ?? 0));
            if ($vipDays > 0) {
                $label .= sprintf('/ %d天', $vipDays);
            }

            if ((int)($item['status'] ?? 0) !== 1) {
                $label .= '（已停用）';
            }

            $options[] = [
                'label' => $label,
                'value' => (string)$vipId,
            ];
        }

        return $cache = $options;
    }

    private static function selectedOptionLabel(array $options, string $value): ?string
    {
        foreach ($options as $option) {
            if (!is_array($option)) {
                continue;
            }

            if ((string)($option['value'] ?? '') !== $value) {
                continue;
            }

            return trim((string)($option['label'] ?? ''));
        }

        return null;
    }

    private static function isSensitive(string $key): bool
    {
        if (in_array($key, self::NON_SENSITIVE_KEYS, true)) {
            return false;
        }

        if (in_array($key, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        $normalized = strtolower($key);

        return str_contains($normalized, 'secret')
            || str_contains($normalized, 'token')
            || str_contains($normalized, 'privatekey')
            || str_contains($normalized, 'password');
    }

    private static function maskValue(string $value): string
    {
        $length = self::length($value);
        if ($length <= 0) {
            return '';
        }

        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return self::substr($value, 0, 2)
            . str_repeat('*', min(16, max(4, $length - 4)))
            . self::substr($value, -2);
    }

    private static function preview(string $value): string
    {
        $normalized = trim($value);
        $normalized = preg_replace('/\\?+(?=$|[\\s，。；：、）】》\\]])/u', '', $normalized) ?? $normalized;

        if (self::length($normalized) <= 88) {
            return $normalized;
        }

        return rtrim(self::substr($normalized, 0, 88)) . '...';
    }

    private static function previewValue(string $key, string $value, bool $masked, string $type): string
    {
        if ($masked) {
            return self::normalizeUiText(self::previewSummary($key, $value, true, $type) ?? $value, $value);
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return $value;
        }

        if ($normalized === 'default') {
            return '标准模板';
        }

        if ($normalized === 'index99') {
            return '首页模板 99';
        }

        if (in_array($key, self::BOOLEAN_KEYS, true)) {
            return self::normalizeBoolean($normalized) ? '已开启' : '已关闭';
        }

        $replaced = $normalized;

        if ($key === 'smstype') {
            $replaced = str_replace(
                ['qcloud', 'tensms', 'smsbao', 'alisms'],
                ['腾讯云', '腾讯云短信', '短信宝', '阿里云短信'],
                $replaced
            );
        }

        if (in_array($key, ['diy_demoPay', 'diy_recharge'], true)) {
            $replaced = str_replace(
                ['qqpay', 'wxpay', 'alipay'],
                ['QQ支付', '微信支付', '支付宝'],
                $replaced
            );
            $replaced = str_replace(',', '、', $replaced);
        }

        if ($key === 'diy_dataClear') {
            $replaced = str_replace(
                ['order', 'recharge', 'adminLog'],
                ['订单', '充值记录', '管理员日志'],
                $replaced
            );
            $replaced = str_replace(',', '、', $replaced);
        }

        if ($key === 'key') {
            $replaced = str_replace(',', '，', $replaced);
        }

        $summary = self::previewSummary($key, $replaced, false, $type);
        if ($summary !== null) {
            return self::normalizeUiText($summary, $summary);
        }

        return self::normalizeUiText($replaced, $replaced);
    }

    private static function previewSummary(string $key, string $value, bool $masked, string $type): ?string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        if ($masked || $type === 'secret') {
            return '已配置敏感字段';
        }

        if (in_array($key, ['logo', 'favicon', 'diy_userAvatar', 'securityIcon', 'api_bg', 'bg'], true)) {
            return '已配置图片资源';
        }

        if ($type === 'image') {
            return '已配置图片资源';
        }

        if ($type === 'html') {
            return '已配置富文本内容';
        }

        if ($key === 'adminMail') {
            return '已配置管理员邮箱';
        }

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false) {
            return '已配置邮箱地址';
        }

        if (in_array($key, ['sitename', 'software_name'], true)) {
            return '已配置站点名称';
        }

        if ($key === 'title') {
            return '已配置页面标题';
        }

        if ($key === 'key') {
            return '已配置站点关键词';
        }

        if ($key === 'desc') {
            return '已配置站点简介';
        }

        if (in_array(
            $key,
            [
                'apiTemp',
                'diyApiTemp',
                'diy_codeTemp',
                'diy_loginTips',
                'diy_regTips',
                'diy_orderTips',
                'diy_moneyTips',
                'diy_loseTips',
                'diy_vipTemp',
                'tg_bind_tips',
            ],
            true
        )) {
            return '已配置通知模板';
        }

        if ($key === 'diy_js') {
            return '已配置自定义脚本';
        }

        if (in_array(
            $key,
            [
                'domain_notice',
                'reportNo',
                'reportTitle',
                'reportYes',
                'securityBindTips',
                'securityPopContent',
                'securityPopTitle',
                'shield_tips',
            ],
            true
        )) {
            return '已配置提示文案';
        }

        if (in_array($key, ['captcha-type', 'logincode-type', 'regcode-type', 'retrieve-type', 'smstype'], true)) {
            return '已配置服务类型';
        }

        if (in_array($key, ['demo_theme', 'doc_theme', 'news_theme', 'user_theme'], true)) {
            return '已配置主题方案';
        }

        if (in_array($key, ['home_temp', 'mtceType'], true)) {
            return '已配置模板方案';
        }

        if (in_array($key, ['pay_api', 'cdkPayUrl', 'reportUrl'], true)) {
            return '已配置链接地址';
        }

        if (in_array($key, ['tg_admin_id', 'tg_bot_token', 'wxpusher_appToken'], true)) {
            return '已配置通知参数';
        }

        if (in_array(
            $key,
            ['adminSecurityKey', 'alipayrsaPublicKey', 'diy_task_key', 'randomKey', 'rsaPrivateKey', 'thinkCode'],
            true
        )) {
            return '已配置安全参数';
        }

        if (in_array($key, ['SmtpSecure', 'file-type', 'imageSize'], true)) {
            return '已配置系统参数';
        }

        if (self::hasPrefix($key, ['smtp-', 'alisms-', 'smsbao-', 'tensms-', 'geetest_', 'tencent_', 'file-', 'qiniu-'])) {
            return '已配置服务参数';
        }

        if ($type === 'url') {
            return '已配置链接地址';
        }

        if (self::containsTemplatePlaceholder($normalized)) {
            return '已配置模板内容';
        }

        return null;
    }

    private static function hasPrefix(string $value, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function containsTemplatePlaceholder(string $value): bool
    {
        return (bool)preg_match('/\[[a-zA-Z0-9_]+\]/', $value);
    }

    private static function humanize(string $key): string
    {
        $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
        $label = str_replace(['_', '-'], ' ', (string)$label);
        $label = preg_replace('/\s+/', ' ', $label);

        return trim((string)$label);
    }

    private static function displayLabel(string $key): string
    {
        if (isset(self::FORCED_DISPLAY_LABELS[$key])) {
            return self::FORCED_DISPLAY_LABELS[$key];
        }

        if (str_starts_with($key, 'geetest_')) {
            return self::normalizeUiText('极验 ' . self::providerSuffixLabel(substr($key, 8)), self::humanize($key));
        }

        if (str_starts_with($key, 'tencent_')) {
            return self::normalizeUiText('腾讯云 ' . self::providerSuffixLabel(substr($key, 8)), self::humanize($key));
        }

        if (str_starts_with($key, 'alisms-')) {
            return self::normalizeUiText('阿里云短信 ' . self::providerSuffixLabel(substr($key, 7)), self::humanize($key));
        }

        if (str_starts_with($key, 'smsbao-')) {
            return self::normalizeUiText('短信宝 ' . self::providerSuffixLabel(substr($key, 7)), self::humanize($key));
        }

        if (str_starts_with($key, 'tensms-')) {
            return self::normalizeUiText('腾讯云短信 ' . self::providerSuffixLabel(substr($key, 7)), self::humanize($key));
        }

        if (str_starts_with($key, 'file-')) {
            return self::normalizeUiText('文件存储 ' . self::storageSuffixLabel(substr($key, 5)), self::humanize($key));
        }

        if (str_starts_with($key, 'qiniu-')) {
            return self::normalizeUiText('七牛云 ' . self::storageSuffixLabel(substr($key, 6)), self::humanize($key));
        }

        if (str_starts_with($key, 'smtp-')) {
            return self::normalizeUiText('邮件 ' . self::providerSuffixLabel(substr($key, 5)), self::humanize($key));
        }

        return self::normalizeUiText(self::DISPLAY_LABELS[$key] ?? '', self::humanize($key));
    }

    private static function providerSuffixLabel(string $suffix): string
    {
        return self::normalizeUiText(match ($suffix) {
            'CaptchaAppId' => '验证码应用编号',
            'CaptchaKey' => '验证码密钥',
            'LoginCodeId' => '登录模板编号',
            'RegCodeId' => '注册模板编号',
            'SignName' => '短信签名',
            'Secret' => '访问密钥',
            'accessKeyId' => '访问密钥编号',
            'host' => '服务器地址',
            'port' => '服务端口',
            'user' => '发信账号',
            'pass' => '发信密码',
            'AppId' => '应用编号',
            'ApiTemp' => '接口模板',
            default => self::humanize($suffix),
        }, self::humanize($suffix));
    }

    private static function storageSuffixLabel(string $suffix): string
    {
        return self::normalizeUiText(match ($suffix) {
            'type' => '文件类型',
            'OssName' => '存储桶名称',
            'accessKeyId' => '访问密钥编号',
            'accessKeySecret' => '访问密钥',
            'endpoint' => '服务地址',
            'AK' => '访问密钥编号',
            'SK' => '访问密钥',
            'Bucket' => '存储桶',
            'Domain' => '绑定域名',
            default => self::humanize($suffix),
        }, self::humanize($suffix));
    }

    private static function contains(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        if (function_exists('mb_stripos')) {
            return mb_stripos($haystack, $needle) !== false;
        }

        return stripos($haystack, $needle) !== false;
    }

    private static function looksLikeMojibake(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        return self::mojibakeScore($text) > 0;
    }

    private static function mojibakeScore(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        $score = 0;
        foreach (self::MOJIBAKE_FRAGMENTS as $fragment) {
            $score += substr_count($text, $fragment);
        }

        return $score;
    }

    private static function normalizeUiText(string $text, string $fallback = ''): string
    {
        $text = trim($text);
        if ($text === '') {
            return $fallback;
        }

        $normalized = $text;
        $repaired = @mb_convert_encoding($normalized, 'UTF-8', 'GB18030');
        if (is_string($repaired) && $repaired !== '' && mb_check_encoding($repaired, 'UTF-8')) {
            if (self::mojibakeScore($repaired) < self::mojibakeScore($normalized)) {
                $normalized = $repaired;
            }
        }

        $normalized = str_replace("\u{FFFD}", '', $normalized);
        $normalized = preg_replace('/\?+(?=$|[\s，。；：、）】》\]])/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', trim($normalized)) ?? trim($normalized);

        if ($normalized === '') {
            return $fallback;
        }

        if ($fallback !== '' && self::mojibakeScore($normalized) > 0) {
            return $fallback;
        }

        return $normalized;
    }

    private static function groupKeys(): array
    {
        $keys = array_map(static fn (array $group): string => $group['key'], self::GROUPS);
        $keys[] = 'other';

        return $keys;
    }

    private static function catalogKeys(): array
    {
        $keys = [];

        foreach (self::GROUPS as $group) {
            foreach ((array)($group['keys'] ?? []) as $key) {
                $name = trim((string)$key);
                if ($name === '' || self::isFormExcludedKey($name) || self::isHiddenKey($name)) {
                    continue;
                }

                if (self::editableDefinition($name) === null) {
                    continue;
                }

                $keys[$name] = true;
            }
        }

        foreach (array_keys(self::EDITABLE_FIELDS) as $key) {
            $name = trim((string)$key);
            if ($name === '' || self::isFormExcludedKey($name) || self::isHiddenKey($name)) {
                continue;
            }

            $keys[$name] = true;
        }

        foreach (self::EDITABLE_FORM_GROUPS as $definition) {
            foreach ((array)($definition['fields'] ?? []) as $key) {
                $name = trim((string)$key);
                if ($name === '' || self::isFormExcludedKey($name) || self::isHiddenKey($name)) {
                    continue;
                }

                if (self::editableDefinition($name) === null) {
                    continue;
                }

                $keys[$name] = true;
            }
        }

        $names = array_keys($keys);
        sort($names, SORT_STRING);

        return $names;
    }

    private static function isHiddenKey(string $key): bool
    {
        return in_array($key, self::HIDDEN_KEYS, true);
    }

    private static function isImageValue(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        return (bool)preg_match('/\.(png|jpe?g|gif|bmp|webp|svg)$/i', $value);
    }

    private static function isUrlValue(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        return str_starts_with($value, 'http://')
            || str_starts_with($value, 'https://')
            || str_starts_with($value, '/');
    }

    private static function sanitizeNonNegativeInteger(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (!preg_match('/^\d+$/', $value)) {
            throw new \InvalidArgumentException('配置值必须为非负整数');
        }

        return (string)((int)$value);
    }

    private static function sanitizeNonNegativeDecimal(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            throw new \InvalidArgumentException('配置值必须为非负金额，且最多保留两位小数');
        }

        return rtrim(rtrim($value, '0'), '.') ?: '0';
    }

    private static function length(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }

        return strlen($value);
    }

    private static function substr(string $value, int $start, ?int $length = null): string
    {
        if (function_exists('mb_substr')) {
            return $length === null ? mb_substr($value, $start) : mb_substr($value, $start, $length);
        }

        return $length === null ? substr($value, $start) : substr($value, $start, $length);
    }

    private static function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        $normalized = strtolower(trim((string)$value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}



