<?php

declare(strict_types=1);

namespace app\support;

use support\Db;

class AdminConfigCatalog
{
    private const FORCED_DISPLAY_LABELS = [
        'adminMail' => '管理员邮箱',
        'aff_percentage' => '返佣比例',
        'aff_type' => '分销模式',
        'apiTemp' => '接口模板',
        'api_bg' => '接口页背景',
        'bg' => '全站背景',
        'bgtype' => '背景类型',
        'create_qrCode' => '二维码生成方式',
        'daily_limit' => '验证码每日请求上限',
        'disconnect_minute' => '掉线检测时间',
        'domain_black' => '域名黑名单',
        'domain_white' => '域名白名单',
        'file-type' => '文件存储方式',
        'home_temp' => '首页模板',
        'home_url' => '首页入口开关',
        'is_channelPay' => '通道测试支付',
        'max_orderprice' => '最大订单金额',
        'min_orderprice' => '最小订单金额',
        'orderDisplay' => '订单显示条数',
        'pay_api' => 'API 地址',
        'qq_login' => 'QQ 快捷登录',
        'qr_codeType' => '二维码解码方式',
        'reg_give_vip' => '赠送套餐',
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
        'appid' => '应用 ID',
        'adminSecurityKey' => '后台安全验证密钥',
        'alipay' => '支付宝收款开关',
        'bearMoney' => '实名认证费用',
        'dataClearDays' => '数据清理保留天数',
        'demo_theme' => '演示主题',
        'demopay_money' => '演示金额',
        'demopay_name' => '演示收款人',
        'diyApiTemp' => '自定义接口模板',
        'diyMtceHtml' => '维护页内容',
        'diy_dataClear' => '数据清理范围',
        'diy_demoPay' => '演示支付方式',
        'diy_js' => '自定义脚本',
        'diy_orderNo' => '自定义订单号',
        'diy_recharge' => '充值支付方式',
        'diy_task_key' => '计划任务密钥',
        'diy_userAvatar' => '默认用户头像',
        'diy_userId' => '自定义商户编号',
        'doc_theme' => '文档主题',
        'domain_notice' => '域名提示',
        'email_switch' => '邮件通知开关',
        'epayid_demo' => '易支付演示商户号',
        'epaykey_demo' => '易支付演示密钥',
        'epayurl_demo' => '易支付演示地址',
        'home_popup' => '首页弹窗',
        'icp' => 'ICP备案',
        'index_popup' => '入口页弹窗',
        'isAdminSecurity' => '后台安全验证开关',
        'isCdkPay' => '卡密充值开关',
        'isMtce' => '维护模式开关',
        'isSecurity' => '安全绑定开关',
        'isSecurityForce' => '强制安全绑定开关',
        'isSecurityLogin' => '登录安全验证开关',
        'is_aff' => '分销功能开关',
        'is_dataClear' => '数据清理开关',
        'is_diyUserId' => '自定义商户编号开关',
        'is_logOff' => '账户注销开关',
        'is_notice' => '公告开关',
        'is_pay_api' => '自定义 API 线路开关',
        'is_pay_money' => '金额校验开关',
        'is_paypage_realname' => '支付页实名开关',
        'is_quotations' => '行情展示开关',
        'is_reg_give_price' => '注册赠金额开关',
        'is_reg_give_vip' => '注册赠套餐开关',
        'is_smOrder' => '补单按钮开关',
        'is_sponsor' => '赞助位开关',
        'is_vip_expire' => '会员到期提醒',
        'isDiy_orderNo' => '自定义订单号开关',
        'is_weboff' => '前台停站开关',
        'mtceType' => '维护页模板',
        'news_theme' => '公告主题',
        'privacy' => '隐私政策',
        'qqpay' => 'QQ 支付开关',
        'quotations' => '行情展示内容',
        'randomKey' => '随机密钥',
        'realNameBear' => '实名费用承担',
        'realNameType' => '实名通道类型',
        'reg_give_price' => '赠送金额',
        'reg_popup' => '注册页弹窗',
        'reportNo' => '举报否定文案',
        'reportPos' => '举报弹窗位置',
        'reportTips' => '举报说明',
        'reportTitle' => '举报标题',
        'reportUrl' => '举报跳转地址',
        'reportYes' => '举报确认文案',
        'alipayrsaPublicKey' => '支付宝公钥',
        'rsaPrivateKey' => '站点私钥',
        'securityBindTips' => '安全绑定提示',
        'securityIcon' => '安全验证图标',
        'securityName' => '安全验证名称',
        'securityPopContent' => '安全弹窗内容',
        'securityPopTitle' => '安全弹窗标题',
        'sh_notice' => '首页公告说明',
        'SmtpSecure' => 'SMTP 加密方式',
        'smtp-host' => 'SMTP 服务器',
        'smtp-port' => 'SMTP 端口',
        'smtp-user' => 'SMTP 账号',
        'smtp-pass' => 'SMTP 密码',
        'smsbao-api' => '短信宝 API 地址',
        'td_notice' => '支付说明',
        'tg_admin_id' => 'Telegram 管理员 ID',
        'tg_bot_token' => 'Telegram 机器人令牌',
        'thinkCode' => '验证码密钥',
        'user_agreement' => '用户协议',
        'user_theme' => '用户中心主题',
        'vip_expire' => '会员到期提醒天数',
        'wechat' => '微信收款开关',
        'wxpusher_appToken' => 'WxPusher 应用令牌',
        'imageSize' => '图片压缩大小',
        'api_url' => '接口地址',
        'code_switch' => '短信验证开关',
        'key' => '站点关键词',
        'shield_tips' => '风控提示',
        'web_url' => '前台地址',
    ];
    private const EDITABLE_FORM_GROUPS = [
        'basic_display' => [
            'title' => '基础展示',
            'description' => '站点名称、页面标题、Logo、图标以及首页基础展示配置。',
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
            'title' => '模板内容',
            'description' => '前台公告、协议公示文案、首页弹窗与主题模板设置。',
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
            'description' => '订单金额、测试支付、演示支付与二维码相关配置。',
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
            'description' => '商户注册、实名、域名、工单、分销与充值限制配置。',
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
            'description' => '验证码、安全校验与风控提示等常用配置。',
            'fields' => [
                'isAdminSecurity',
                'isSecurity',
                'isSecurityForce',
                'isSecurityLogin',
                'code_switch',
                'captcha-type',
                'logincode-type',
                'regcode-type',
                'retrieve-type',
                'smstype',
                'shield_tips',
                'shield_key',
            ],
        ],
        'notifications' => [
            'title' => '通知提醒',
            'description' => '邮件、Telegram、WxPusher 与常用通知模板配置。',
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
            'title' => '存储集成',
            'description' => '文件策略、上传大小以及存储接入基础配置。',
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
            'title' => '维护设置',
            'description' => '停站、维护页和数据清理相关配置。',
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
            'help_text' => '接收系统告警、业务通知和关键操作提醒的管理员邮箱地址。',
        ],
        'desc' => [
            'label' => '站点简介',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 255,
            'placeholder' => '请输入站点简介',
            'help_text' => '用于首页展示、搜索描述和系统简介。',
        ],
        'demopay_money' => [
            'label' => '演示金额',
            'editor' => 'input',
            'value_type' => 'non_negative_decimal',
            'max_length' => 12,
            'placeholder' => '0.01',
            'help_text' => '用于支付测试和演示页面的默认下单金额。',
        ],
        'demopay_name' => [
            'label' => '演示收款人',
            'editor' => 'input',
            'value_type' => 'text',
            'max_length' => 80,
            'placeholder' => '演示收款商户',
            'help_text' => '用于支付测试、演示订单和收银台展示的收款人名称。',
        ],
        'diy_codeTemp' => [
            'label' => '验证码模板',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 255,
            'placeholder' => '您的验证码是 [code]',
            'help_text' => '使用 [code] 作为验证码变量。',
        ],
        'diyApiTemp' => [
            'label' => '自定义接口模板',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 20000,
            'placeholder' => '请输入完整的自定义接口模板内容',
            'help_text' => '启用自定义接口模板后，接口展示页将按这里维护的 HTML 内容渲染。',
        ],
        'diy_loginTips' => [
            'label' => '登录通知模板',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 500,
            'placeholder' => '账号 [login_uid] 于 [login_time] 在 [login_ip] 登录',
            'help_text' => '支持 [login_uid]、[login_ip]、[login_time] 变量。',
        ],
        'diy_demoPay' => [
            'label' => '演示支付方式',
            'editor' => 'textarea',
            'value_type' => 'list',
            'max_length' => 255,
            'placeholder' => "wxpay
alipay
qqpay",
            'help_text' => '每行一个支付方式编码，用于演示支付和支付测试页面。',
        ],
        'diy_loseTips' => [
            'label' => '掉线通知模板',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 500,
            'placeholder' => '收款账号 [account_code] 已于 [lose_time] 掉线',
            'help_text' => '支持 [account_id]、[account_type]、[account_code]、[lose_time] 变量。',
        ],
        'diy_moneyTips' => [
            'label' => '余额提醒模板',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 255,
            'placeholder' => '当前余额低于 [money] 元',
            'help_text' => '支持 [money] 变量，用于余额不足提醒。',
        ],
        'diy_orderTips' => [
            'label' => '订单通知模板',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 500,
            'placeholder' => '您有新的订单 [out_trade_no]',
            'help_text' => '用于新订单通知和订单到账提醒内容。',
        ],
        'diy_regTips' => [
            'label' => '注册通知模板',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 255,
            'placeholder' => '欢迎新商户 [userName]',
            'help_text' => '支持 [userName] 变量，用于商户注册成功提示。',
        ],
        'diy_vipTemp' => [
            'label' => 'VIP 到期模板',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 255,
            'placeholder' => '[sitename] VIP 将于 [day] 天后到期',
            'help_text' => '支持 [sitename]、[day] 变量，用于 VIP 到期提醒。',
        ],
        'domain_notice' => [
            'label' => '域名提示',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 500,
            'placeholder' => '请输入域名相关提示',
            'help_text' => '显示在域名绑定、审核和联调场景附近的说明文字。',
        ],
        'domainNum' => [
            'label' => '每日可添加域名数',
            'editor' => 'input',
            'value_type' => 'non_negative_integer',
            'max_length' => 6,
            'placeholder' => '0 表示不限',
            'help_text' => '限制单个商户每天可新增的域名数量，0 表示不限制。',
        ],
        'domain_black' => [
            'label' => '域名黑名单',
            'editor' => 'textarea',
            'value_type' => 'list',
            'max_length' => 5000,
            'placeholder' => "spam-domain.com
blocked-domain.com",
            'help_text' => '每行一个域名，命中后将禁止绑定或访问相关业务功能。',
        ],
        'domain_white' => [
            'label' => '域名白名单',
            'editor' => 'textarea',
            'value_type' => 'list',
            'max_length' => 5000,
            'placeholder' => "pay.example.com
safe.example.com",
            'help_text' => '每行一个域名，命中后优先视为可信域名。',
        ],
        'email_switch' => [
            'label' => '邮件通知开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后可使用邮箱通知、验证码和邮件提醒能力。',
        ],
        'epayid_demo' => [
            'label' => '易支付演示商户号',
            'editor' => 'input',
            'value_type' => 'text',
            'max_length' => 120,
            'placeholder' => '请输入演示商户号',
            'help_text' => '用于易支付兼容演示、支付测试和联调示例。',
        ],
        'epaykey_demo' => [
            'label' => '易支付演示密钥',
            'editor' => 'password',
            'value_type' => 'text',
            'max_length' => 255,
            'placeholder' => '请输入演示密钥',
            'help_text' => '用于易支付兼容演示、支付测试和联调示例。',
        ],
        'epayurl_demo' => [
            'label' => '易支付演示地址',
            'editor' => 'input',
            'value_type' => 'url',
            'max_length' => 255,
            'placeholder' => 'https://demo.example.com/submit.php',
            'help_text' => '填写易支付兼容网关地址，用于演示和支付测试。',
        ],
        'favicon' => [
            'label' => '网站图标',
            'editor' => 'input',
            'value_type' => 'path',
            'max_length' => 255,
            'placeholder' => '/upload/images/favicon.ico',
            'help_text' => '填写站点图标文件路径或完整 URL。',
        ],
        'forceRealName' => [
            'label' => '强制实名认证',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后，商户需先完成实名认证才能使用需要实名的相关功能。',
        ],
        'home_popup' => [
            'label' => '首页弹窗',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 2000,
            'placeholder' => '请输入首页弹窗内容',
            'help_text' => '支持 HTML 富文本内容。',
        ],
        'icp' => [
            'label' => 'ICP 备案',
            'editor' => 'input',
            'value_type' => 'text',
            'max_length' => 80,
            'placeholder' => 'ICP 备案号',
            'help_text' => '显示在首页底部和公共页面页脚。',
        ],
        'index_popup' => [
            'label' => '入口页弹窗',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 2000,
            'placeholder' => '请输入入口页弹窗内容',
            'help_text' => '支持 HTML 富文本内容。',
        ],
        'is_aff' => [
            'label' => '分销功能开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后支持推广返佣、分销统计和相关通知能力。',
        ],
        'is_channelPay' => [
            'label' => '通道测试支付',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后，商户可在通道管理中发起测试订单。',
        ],
        'isCdkPay' => [
            'label' => '卡密充值开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后商户中心可使用卡密充值与兑换能力。',
        ],
        'is_domain' => [
            'label' => '域名功能开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后商户可绑定、审核和管理自有域名。',
        ],
        'is_examine' => [
            'label' => '审核开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后新增商户、域名等业务需要后台审核。',
        ],
        'is_notice' => [
            'label' => '公告开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后显示公告中心与前台公告内容。',
        ],
        'is_logOff' => [
            'label' => '注销开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后商户可在前台申请账号注销。',
        ],
        'is_pay_api' => [
            'label' => 'DIY 对接 API',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后，对外网关地址将改为这里维护的自定义 API 线路。',
        ],
        'is_reg_give_price' => [
            'label' => '注册赠金额开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后，新注册商户将自动获得赠送余额。',
        ],
        'is_reg_give_vip' => [
            'label' => '注册赠套餐开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后，新注册商户将自动获得指定会员套餐。',
        ],
        'is_reg' => [
            'label' => '注册开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '控制商户自助注册入口是否开放。',
        ],
        'isRealName' => [
            'label' => '实名开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后显示实名认证相关配置和业务能力。',
        ],
        'is_smOrder' => [
            'label' => '补单按钮开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后商户端显示手动补单按钮。',
        ],
        'is_sponsor' => [
            'label' => '赞助位开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '控制首页或公共页的赞助位内容展示。',
        ],
        'isTicket' => [
            'label' => '工单开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后商户可提交和查看工单。',
        ],
        'is_vip_expire' => [
            'label' => 'VIP 到期提醒',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后按提醒天数向商户发送 VIP 到期通知。',
        ],
        'logo' => [
            'label' => 'Logo 地址',
            'editor' => 'input',
            'value_type' => 'path',
            'max_length' => 255,
            'placeholder' => '/upload/images/logo.png',
            'help_text' => '填写网站 Logo 文件路径或完整 URL。',
        ],
        'max_orderprice' => [
            'label' => '最大订单金额',
            'editor' => 'input',
            'value_type' => 'non_negative_decimal',
            'max_length' => 12,
            'placeholder' => '1000',
            'help_text' => '单笔支付订单允许提交的最大金额。',
        ],
        'max_recharge' => [
            'label' => '最大充值金额',
            'editor' => 'input',
            'value_type' => 'non_negative_decimal',
            'max_length' => 12,
            'placeholder' => '1000',
            'help_text' => '单笔余额充值允许提交的最大金额。',
        ],
        'min_recharge' => [
            'label' => '最小充值金额',
            'editor' => 'input',
            'value_type' => 'non_negative_decimal',
            'max_length' => 12,
            'placeholder' => '0',
            'help_text' => '单笔余额充值允许提交的最小金额。',
        ],
        'min_orderprice' => [
            'label' => '最小订单金额',
            'editor' => 'input',
            'value_type' => 'non_negative_decimal',
            'max_length' => 12,
            'placeholder' => '0.01',
            'help_text' => '单笔支付订单允许提交的最小金额。',
        ],
        'orderDisplay' => [
            'label' => '订单显示条数',
            'editor' => 'input',
            'value_type' => 'non_negative_integer',
            'max_length' => 6,
            'placeholder' => '10',
            'help_text' => '订单列表默认显示的条数，用于后台和商户端表格分页。',
        ],
        'paid_reg' => [
            'label' => '付费注册',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后，新商户注册需先完成付费开通。',
        ],
        'pay_api' => [
            'label' => 'API 地址',
            'editor' => 'textarea',
            'value_type' => 'list',
            'max_length' => 2000,
            'placeholder' => "https://api1.example.com/
https://api2.example.com/",
            'help_text' => '每行一个地址，用于对外展示或分发给商户的接口入口地址。',
        ],
        'paid_reg_price' => [
            'label' => '注册费用',
            'editor' => 'input',
            'value_type' => 'non_negative_decimal',
            'max_length' => 12,
            'placeholder' => '0.01',
            'help_text' => '开启付费注册后，商户注册时需要支付的金额。',
        ],
        'qq_login' => [
            'label' => 'QQ 快捷登录',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 12,
            'placeholder' => '请选择 QQ 快捷登录',
            'help_text' => '关闭或选择一个已配置的 QQ 登录渠道。',
        ],
        'privacy' => [
            'label' => '隐私政策',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 5000,
            'placeholder' => '请输入隐私政策',
            'help_text' => '支持 HTML 富文本内容。',
        ],
        'reg_give_price' => [
            'label' => '赠送金额',
            'editor' => 'input',
            'value_type' => 'non_negative_decimal',
            'max_length' => 12,
            'placeholder' => '0.00',
            'help_text' => '注册成功后自动发放到商户账户余额中的金额。',
        ],
        'reg_give_vip' => [
            'label' => '赠送套餐',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 12,
            'placeholder' => '请选择赠送套餐',
            'help_text' => '注册成功后自动发放给商户的会员套餐。',
        ],
        'reg_popup' => [
            'label' => '注册页弹窗',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 2000,
            'placeholder' => '请输入注册页弹窗内容',
            'help_text' => '支持 HTML 富文本内容。',
        ],
        'sh_notice' => [
            'label' => '审核提示',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 1000,
            'placeholder' => '商户审核提示',
            'help_text' => '显示在商户或域名审核场景附近。',
        ],
        'sitename' => [
            'label' => '站点名称',
            'editor' => 'input',
            'value_type' => 'text',
            'max_length' => 80,
            'placeholder' => 'AiPay 支付平台',
            'help_text' => '显示在首页、商户端和公共页面中的站点名称。',
        ],
        'software_name' => [
            'label' => '软件名称',
            'editor' => 'input',
            'value_type' => 'text',
            'max_length' => 80,
            'placeholder' => 'AiPay',
            'help_text' => '用于软件监控上报、客户端展示和系统对外标识。',
        ],
        'SmtpSecure' => [
            'label' => 'SMTP 加密方式',
            'editor' => 'select',
            'value_type' => 'text',
            'max_length' => 16,
            'placeholder' => '请选择 SMTP 加密方式',
            'help_text' => '选择发送邮件时所使用的加密模式，例如 SSL/TLS 或 STARTTLS。',
            'options' => [
                ['label' => '无加密', 'value' => ''],
                ['label' => 'SSL/TLS', 'value' => 'ssl'],
                ['label' => 'STARTTLS', 'value' => 'tls'],
            ],
        ],
        'aff_type' => [
            'label' => '分销模式',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 1,
            'placeholder' => '请选择分销模式',
            'help_text' => '决定返佣按充值金额结算，还是按会员购买金额结算。',
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
            'help_text' => '当实名费用由商户承担时，将按这里的金额从商户余额扣除。',
        ],
        'apiTemp' => [
            'label' => '接口模板',
            'editor' => 'select',
            'value_type' => 'text',
            'max_length' => 24,
            'placeholder' => '请选择接口模板',
            'help_text' => '用于接口对接页或接口展示页的模板方案。',
            'options' => [
                ['label' => '默认模板', 'value' => 'default'],
                ['label' => '自定义模板', 'value' => 'diyApiTemp'],
            ],
        ],
        'bgtype' => [
            'label' => '背景类型',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 1,
            'placeholder' => '请选择背景类型',
            'help_text' => '选择登录页、首页等背景资源的获取方式。',
            'options' => [
                ['label' => '本地资源', 'value' => '0'],
                ['label' => '自定义 API', 'value' => '1'],
            ],
        ],
        'captcha-type' => [
            'label' => '验证码类型',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 1,
            'placeholder' => '请选择验证码类型',
            'help_text' => '用于登录、注册或测试支付等场景的验证码能力。',
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
            'placeholder' => '请选择二维码生成方式',
            'help_text' => '选择系统生成二维码图片时所使用的服务。',
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
            'placeholder' => '请选择文件存储方式',
            'help_text' => '上传素材、二维码和凭证图片时所使用的文件存储方案，OSS 需先完成配置。',
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
            'placeholder' => '请选择登录验证方式',
            'help_text' => '用于前台登录方式选择，TG 验证需先配置 Telegram 能力。',
            'options' => [
                ['label' => '账号密码', 'value' => '0'],
                ['label' => '短信验证', 'value' => '1'],
                ['label' => '邮箱验证', 'value' => '2'],
                ['label' => '社交登录', 'value' => '3'],
                ['label' => 'TG 验证', 'value' => '4'],
            ],
        ],
        'mtceType' => [
            'label' => '维护页模板',
            'editor' => 'select',
            'value_type' => 'text',
            'max_length' => 24,
            'placeholder' => '请选择维护页模板',
            'help_text' => '系统进入维护模式后，前台展示的维护页面模板方案。',
            'options' => [
                ['label' => '默认模板', 'value' => 'default'],
                ['label' => '自定义模板', 'value' => 'diyMtceHtml'],
            ],
        ],
        'qr_codeType' => [
            'label' => '二维码解码方式',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 1,
            'placeholder' => '请选择二维码解码方式',
            'help_text' => '支付插件需要解析二维码内容时，使用 API 或本地方式完成解码。',
            'options' => [
                ['label' => 'API 解码', 'value' => '1'],
                ['label' => '本地解码', 'value' => '2'],
            ],
        ],
        'realNameBear' => [
            'label' => '实名费用承担',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 1,
            'placeholder' => '请选择承担方式',
            'help_text' => '决定实名认证费用由平台承担还是由商户承担。',
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
            'placeholder' => '请选择实名通道类型',
            'help_text' => '选择商户实名认证时使用的实名通道类型。',
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
            'placeholder' => '请选择注册验证方式',
            'help_text' => '用于商户注册验证码发送方式选择，TG 验证需先配置 Telegram 能力。',
            'options' => [
                ['label' => '关闭验证', 'value' => '0'],
                ['label' => '短信验证', 'value' => '1'],
                ['label' => '邮箱验证', 'value' => '2'],
                ['label' => 'TG 验证', 'value' => '3'],
            ],
        ],
        'reportPos' => [
            'label' => '举报弹窗位置',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后举报说明以弹窗位置展示，关闭则按页面内联方式展示。',
        ],
        'reportTips' => [
            'label' => '举报说明',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 5000,
            'placeholder' => '请输入举报说明',
            'help_text' => '支持 HTML，用于举报页面或举报弹窗中的说明内容。',
        ],
        'retrieve-type' => [
            'label' => '找回方式',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 1,
            'placeholder' => '请选择找回方式',
            'help_text' => '用于密码找回验证码发送方式选择，TG 验证需先配置 Telegram 能力。',
            'options' => [
                ['label' => '关闭', 'value' => '0'],
                ['label' => '短信验证', 'value' => '1'],
                ['label' => '邮箱验证', 'value' => '2'],
                ['label' => 'TG 验证', 'value' => '3'],
            ],
        ],
        'smstype' => [
            'label' => '短信服务商',
            'editor' => 'select',
            'value_type' => 'text',
            'max_length' => 16,
            'placeholder' => '请选择短信服务商',
            'help_text' => '发送短信验证码时所使用的服务商，需同时完成对应密钥配置。',
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
            'placeholder' => '请选择签名模式',
            'help_text' => '基础校验兼容旧版 token 逻辑，安全签名会同时校验签名和时间窗口。',
            'options' => [
                ['label' => '基础校验', 'value' => 'compat'],
                ['label' => '安全签名', 'value' => 'strict'],
            ],
        ],
        'software_callback_sign_window' => [
            'label' => '软件回调签名时效',
            'editor' => 'input',
            'value_type' => 'non_negative_integer',
            'max_length' => 6,
            'placeholder' => '300',
            'help_text' => '安全签名模式下允许的时间窗口，默认 300 秒。',
        ],
        'shield_key' => [
            'label' => '风控关键词',
            'editor' => 'textarea',
            'value_type' => 'list',
            'max_length' => 5000,
            'placeholder' => "博彩
色情
套现",
            'help_text' => '每行一个关键词，命中后将触发风控拦截或风险提示。',
        ],
        'td_notice' => [
            'label' => '支付说明',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 1000,
            'placeholder' => '请输入支付说明',
            'help_text' => '显示在支付页、测试支付和下单场景附近的说明内容。',
        ],
        'vip_expire' => [
            'label' => '提醒天数',
            'editor' => 'input',
            'value_type' => 'non_negative_integer',
            'max_length' => 6,
            'placeholder' => '3',
            'help_text' => '会员到期前多少天开始提醒，建议填写 1 到 7 天。',
        ],
        'wechat_login' => [
            'label' => '微信快捷登录',
            'editor' => 'select',
            'value_type' => 'non_negative_integer',
            'max_length' => 12,
            'placeholder' => '请选择微信快捷登录',
            'help_text' => '关闭或选择一个已配置的微信登录渠道。',
        ],
        'tg_bind_tips' => [
            'label' => 'Telegram 绑定提示',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 255,
            'placeholder' => '请输入 Telegram 绑定提示',
            'help_text' => '显示在商户绑定 Telegram 账号时的说明内容。',
        ],
        'aff_percentage' => [
            'label' => '返佣比例',
            'editor' => 'input',
            'value_type' => 'non_negative_decimal',
            'max_length' => 8,
            'placeholder' => '0.10',
            'help_text' => '填写 0 到 1 之间的小数，例如 0.10 表示 10%。',
        ],
        'tg_notice_recharge' => [
            'label' => 'Telegram 充值通知',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后通过 Telegram 发送充值相关通知。',
        ],
        'tg_notice_register' => [
            'label' => 'Telegram 注册通知',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后通过 Telegram 发送商户注册通知。',
        ],
        'tg_notice_ticket' => [
            'label' => 'Telegram 工单通知',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后通过 Telegram 发送工单消息通知。',
        ],
        'tg_notice_vip' => [
            'label' => 'Telegram VIP 通知',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后通过 Telegram 发送 VIP 相关提醒。',
        ],
        'tg_switch' => [
            'label' => 'Telegram 开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后显示并启用 Telegram 相关能力。',
        ],
        'timeout' => [
            'label' => '订单超时时间',
            'editor' => 'input',
            'value_type' => 'non_negative_integer',
            'max_length' => 6,
            'placeholder' => '180',
            'help_text' => '支付订单超过该时长未完成将按超时处理。',
        ],
        'cdkPayUrl' => [
            'label' => '卡密充值地址',
            'editor' => 'input',
            'value_type' => 'url',
            'max_length' => 255,
            'placeholder' => 'https://cdk.example.com/',
            'help_text' => '用于商户中心跳转至卡密充值页面或外部卡密系统。',
        ],
        'daily_limit' => [
            'label' => '验证码每日请求上限',
            'editor' => 'input',
            'value_type' => 'non_negative_integer',
            'max_length' => 6,
            'placeholder' => '10',
            'help_text' => '限制单个目标每日可发送的验证码次数，超出后拒绝继续发送。',
        ],
        'disconnect_minute' => [
            'label' => '掉线检测时间',
            'editor' => 'input',
            'value_type' => 'non_negative_integer',
            'max_length' => 6,
            'placeholder' => '1',
            'help_text' => '超过该分钟数未收到软件心跳或上报时判定掉线，最小 1 分钟。',
        ],
        'diy_userId' => [
            'label' => '商户起始 ID',
            'editor' => 'input',
            'value_type' => 'non_negative_integer',
            'max_length' => 12,
            'placeholder' => '10000',
            'help_text' => '开启自定义商户编号后，新商户编号将从这里的数值开始递增分配。',
        ],
        'title' => [
            'label' => '页面标题',
            'editor' => 'input',
            'value_type' => 'text',
            'max_length' => 120,
            'placeholder' => '请输入页面标题',
            'help_text' => '显示在浏览器标题栏和搜索引擎标题中。',
        ],
        'user_agreement' => [
            'label' => '用户协议',
            'editor' => 'textarea',
            'value_type' => 'text',
            'max_length' => 5000,
            'placeholder' => '请输入用户协议',
            'help_text' => '支持 HTML 富文本内容。',
        ],
        'wxpusher_switch' => [
            'label' => 'WxPusher 开关',
            'editor' => 'switch',
            'value_type' => 'boolean',
            'help_text' => '开启后可使用 WxPusher 消息推送能力。',
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

        if ($sanitized === []) {
            throw new \InvalidArgumentException('配置分组提交内容不能为空');
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
        $label = self::normalizeUiText((string)($definition['label'] ?? ''), self::displayLabel($key));

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
                '' => '无',
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
                'default' => '默认模板',
                'diyApiTemp', 'diyMtceHtml' => '自定义模板',
                default => $value,
            },
            'bgtype' => match ($value) {
                '0' => '本地资源',
                '1' => '自定义 API',
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
                '2' => '阿里云 OSS',
                '3' => '七牛云',
                default => $value,
            },
            'logincode-type' => match ($value) {
                '0' => '账号密码',
                '1' => '短信验证',
                '2' => '邮箱验证',
                '3' => '社交登录',
                '4' => 'TG 验证',
                default => $value,
            },
            'regcode-type' => match ($value) {
                '0' => '关闭验证',
                '1' => '短信验证',
                '2' => '邮箱验证',
                '3' => 'TG 验证',
                default => $value,
            },
            'retrieve-type' => match ($value) {
                '0' => '关闭',
                '1' => '短信验证',
                '2' => '邮箱验证',
                '3' => 'TG 验证',
                default => $value,
            },
            'qr_codeType' => match ($value) {
                '1' => 'API 解码',
                '2' => '本地解码',
                default => $value,
            },
            'realNameBear' => match ($value) {
                '0' => '平台承担',
                '1' => '商户承担',
                default => $value,
            },
            'realNameType' => match ($value) {
                '1' => '微信/支付宝人脸核验',
                '2' => '支付宝身份授权',
                default => $value,
            },
            'smstype' => match ($value) {
                'aliyun' => '阿里云',
                'qcloud' => '腾讯云',
                'smsbao' => '短信宝',
                default => $value,
            },
            'software_callback_sign_mode' => match ($value) {
                'compat' => '基础校验',
                'strict' => '安全签名',
                default => $value,
            },
            default => '',
        };
    }

    private static function dynamicOptionsForConfigKey(string $key): array
    {
        return match ($key) {
            'qq_login' => self::quickLoginOptions('qq', '关闭 - 请先在快捷登录管理中配置 QQ'),
            'wechat_login' => self::quickLoginOptions('wx', '关闭 - 请先在快捷登录管理中配置微信'),
            'reg_give_vip' => self::vipPackageOptions(),
            default => [],
        };
    }

    private static function autoEditor(string $key): string
    {
        if (in_array($key, self::BOOLEAN_KEYS, true)) {
            return 'switch';
        }

        if (in_array($key, self::AUTO_TEXTAREA_KEYS, true) || in_array($key, self::HTML_KEYS, true) || in_array($key, self::LIST_KEYS, true)) {
            return 'textarea';
        }

        if (in_array($key, self::AUTO_PASSWORD_KEYS, true) || (self::isSensitive($key) && !str_contains(strtolower($key), 'privatekey'))) {
            return 'password';
        }

        return 'input';
    }

    private static function autoValueType(string $key): string
    {
        if (in_array($key, self::BOOLEAN_KEYS, true)) {
            return 'boolean';
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

    private static function autoMaxLength(string $editor, string $valueType): ?int
    {
        return match ($valueType) {
            'non_negative_integer' => 12,
            'non_negative_decimal' => 18,
            'url', 'path' => 255,
            'list' => 1000,
            default => match ($editor) {
                'textarea' => 5000,
                'password' => 2000,
                'switch' => null,
                default => 255,
            },
        };
    }

    private static function autoPlaceholder(string $label, string $editor, string $valueType): string
    {
        if ($editor === 'switch') {
            return '';
        }

        if ($valueType === 'list') {
            return '每行一项，或使用英文逗号分隔';
        }

        if ($valueType === 'non_negative_integer') {
            return '请输入非负整数';
        }

        if ($valueType === 'non_negative_decimal') {
            return '请输入非负金额或比例';
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
            return '默认隐藏显示，展开后可编辑当前敏感配置。';
        }

        if ($valueType === 'list') {
            return '支持英文逗号、中文逗号或换行分隔，多项内容会统一整理后保存。';
        }

        if (in_array($key, self::AUTO_PATH_KEYS, true)) {
            return '支持相对路径或完整资源链接。';
        }

        if ($valueType === 'url') {
            return '请填写完整可访问地址。';
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

        $segments = preg_split('/[\r\n,，]+/u', $value) ?: [];
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
            $rows = Db::table('ypay_quicklogin')
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
            $rows = Db::table('ypay_vip')
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
                $label .= sprintf(' / %d天', $vipDays);
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
        $normalized = preg_replace('/\s+/u', ' ', trim($value));
        $normalized = $normalized === null ? trim($value) : $normalized;

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
            return '默认模板';
        }

        if ($normalized === 'index99') {
            return '首页模板 99';
        }

        if (in_array($key, self::BOOLEAN_KEYS, true)) {
            return self::normalizeBoolean($normalized) ? '已开启' : '已关闭';
        }

        $replaced = str_replace(
            ['AiPay Smoke', 'Puple'],
            ['AiPay 演示站', '紫色主题'],
            $normalized
        );

        if ($key === 'smstype') {
            $replaced = str_replace(
                ['qcloud', 'tensms', 'smsbao', 'alisms'],
                ['腾讯云', '腾讯云', '短信宝', '阿里云'],
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
            return self::normalizeUiText('腾讯云' . self::providerSuffixLabel(substr($key, 8)), self::humanize($key));
        }

        if (str_starts_with($key, 'alisms-')) {
            return self::normalizeUiText('阿里云短信' . self::providerSuffixLabel(substr($key, 7)), self::humanize($key));
        }

        if (str_starts_with($key, 'smsbao-')) {
            return self::normalizeUiText('短信宝' . self::providerSuffixLabel(substr($key, 7)), self::humanize($key));
        }

        if (str_starts_with($key, 'tensms-')) {
            return self::normalizeUiText('腾讯云短信' . self::providerSuffixLabel(substr($key, 7)), self::humanize($key));
        }

        if (str_starts_with($key, 'file-')) {
            return self::normalizeUiText('文件存储 ' . self::storageSuffixLabel(substr($key, 5)), self::humanize($key));
        }

        if (str_starts_with($key, 'qiniu-')) {
            return self::normalizeUiText('七牛云' . self::storageSuffixLabel(substr($key, 6)), self::humanize($key));
        }

        if (str_starts_with($key, 'smtp-')) {
            return self::normalizeUiText('SMTP ' . self::providerSuffixLabel(substr($key, 5)), self::humanize($key));
        }

        return self::normalizeUiText(self::DISPLAY_LABELS[$key] ?? '', self::humanize($key));
    }

    private static function providerSuffixLabel(string $suffix): string
    {
        return self::normalizeUiText(match ($suffix) {
            'CaptchaAppId' => '验证码应用 ID',
            'CaptchaKey' => '验证码密钥',
            'LoginCodeId' => '登录模板 ID',
            'RegCodeId' => '注册模板 ID',
            'SignName' => '短信签名',
            'Secret' => '访问密钥密文',
            'accessKeyId' => '访问密钥 ID',
            'host' => '主机',
            'port' => '端口',
            'user' => '账号',
            'pass' => '密码',
            'AppId' => '应用 ID',
            'ApiTemp' => '接口模板',
            default => self::humanize($suffix),
        }, self::humanize($suffix));
    }

    private static function storageSuffixLabel(string $suffix): string
    {
        return self::normalizeUiText(match ($suffix) {
            'type' => '文件类型',
            'OssName' => '存储空间名称',
            'accessKeyId' => '访问密钥 ID',
            'accessKeySecret' => '访问密钥密文',
            'endpoint' => '访问节点',
            'AK' => '访问密钥',
            'SK' => '访问密钥密文',
            'Bucket' => '存储空间',
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

        preg_match_all(
            '/(?:�|€|鏀|鍟|璇|鍏|寰|鐧|瀹|鎴|璐|缁|闈|绯|绾|缃|閫|鐢|鍒|鍙|闂|褰|璋|鍥|鏃|锛|銆|鍩|妯|閰|鎻|鍚|璁|鑵|闃|鐭|閭|璧|缂|鎺|绔|绠|鍛|橀|偖|鏈|嶅|悊|堝)/u',
            $text,
            $matches
        );

        return count($matches[0]);
    }

    private static function normalizeUiText(string $text, string $fallback = ''): string
    {
        $text = trim($text);
        if ($text === '') {
            return $fallback;
        }

        $normalized = $text;
        $repaired = @mb_convert_encoding($normalized, 'GB18030', 'UTF-8');
        if (is_string($repaired) && $repaired !== '' && mb_check_encoding($repaired, 'UTF-8')) {
            if (self::mojibakeScore($repaired) < self::mojibakeScore($normalized)) {
                $normalized = $repaired;
            }
        }

        $normalized = str_replace('�', '', $normalized);
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



