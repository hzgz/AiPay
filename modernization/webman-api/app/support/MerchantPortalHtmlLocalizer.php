<?php

declare(strict_types=1);

namespace app\support;

final class MerchantPortalHtmlLocalizer
{
    public static function localize(string $html): string
    {
        $replacements = [];

        foreach (self::replacements() as $search => $replacement) {
            $replacements[$search] = self::normalizeReplacement($search, $replacement);
        }

        return strtr($html, $replacements);
    }

    /**
     * @return array<string, string>
     */
    private static function replacements(): array
    {
        return [
            '<title>Merchant Login</title>' => '<title>商户登录</title>',
            '<title>Merchant Security</title>' => '<title>安全中心</title>',
            '<title>Merchant Notifications</title>' => '<title>通知设置</title>',
            '<title>Merchant Connections</title>' => '<title>绑定中心</title>',
            '<title>Merchant Orders</title>' => '<title>订单记录</title>',
            '<title>Merchant Recharges</title>' => '<title>充值记录</title>',
            '<title>Merchant Money Logs</title>' => '<title>资金日志</title>',
            '<title>Merchant VIP Packages</title>' => '<title>会员套餐</title>',
            '<title>Merchant API</title>' => '<title>接口信息</title>',
            '<title>Merchant Login Logs</title>' => '<title>登录日志</title>',
            '<title>Merchant Google Auth</title>' => '<title>谷歌验证</title>',
            '<title>Merchant Affiliate</title>' => '<title>推广返佣</title>',
            '<title>Merchant Real Name</title>' => '<title>实名认证</title>',
            '<title>Merchant Domains</title>' => '<title>域名管理</title>',
            '<title>Merchant Tickets</title>' => '<title>工单中心</title>',
            '<title>Merchant Frozen</title>' => '<title>商户账户已冻结</title>',
            '>Merchant Login<' => '>商户登录<',
            '>Merchant Security<' => '>安全中心<',
            '>Merchant Notifications<' => '>通知设置<',
            '>Merchant Connections<' => '>绑定中心<',
            '>Merchant Orders<' => '>订单记录<',
            '>Merchant Recharges<' => '>充值记录<',
            '>Merchant Money Logs<' => '>资金日志<',
            '>Merchant VIP Packages<' => '>会员套餐<',
            '>Merchant API<' => '>接口信息<',
            '>Merchant Login Logs<' => '>登录日志<',
            '>Merchant Google Auth<' => '>谷歌验证<',
            '>Merchant Affiliate<' => '>推广返佣<',
            '>Merchant Real Name<' => '>实名认证<',
            '>Merchant Domains<' => '>域名管理<',
            '>Merchant Tickets<' => '>工单中心<',
            '>Merchant Frozen<' => '>商户账户已冻结<',
            '>Webman Merchant Portal<' => '>商户中心<',
            '>Merchant Center<' => '>商户中心<',
            '>Profile<' => '>资料维护<',
            '>Security Center<' => '>安全中心<',
            '>Security<' => '>安全中心<',
            '>Connections<' => '>绑定中心<',
            '>Real Name<' => '>实名认证<',
            '>Orders<' => '>订单记录<',
            '>Recharges<' => '>充值记录<',
            '>Money Logs<' => '>资金日志<',
            '>VIP Packages<' => '>会员套餐<',
            '>API Info<' => '>接口信息<',
            '>Tickets<' => '>工单中心<',
            '>Domains<' => '>域名管理<',
            '>Login Logs<' => '>登录日志<',
            '>Affiliate<' => '>推广返佣<',
            '>JSON View<' => '>查看 JSON<',
            '>Logout<' => '>退出登录<',
            '>Back to Migration Entry<' => '>返回首页<',
            '>Enter Merchant Center<' => '>进入商户中心<',
            '>Google Auth<' => '>谷歌验证<',
            '>Enabled<' => '>已启用<',
            '>Disabled<' => '>已关闭<',
            '>Required<' => '>必需<',
            '>Optional<' => '>可选<',
            '>Available<' => '>可用<',
            '>Unavailable<' => '>不可用<',
            '>Configured<' => '>已配置<',
            '>Not configured<' => '>未配置<',
            '>Bound<' => '>已绑定<',
            '>Not bound<' => '>未绑定<',
            '>Closed<' => '>关闭<',
            '>Current<' => '>当前<',
            '>Read Only<' => '>信息查看<',
            '>Blocked<' => '>受限<',
            '>Missing<' => '>未配置<',
            '>Verified<' => '>已认证<',
            '>Not verified<' => '>未认证<',
            '>Pending verification<' => '>待认证<',
            '>No active VIP<' => '>暂无有效会员<',
            '>Normal merchant<' => '>普通商户<',
            '>VIP active<' => '>会员有效<',
            '>VIP expired<' => '>会员已过期<',
            '>Unknown<' => '>未知<',
            '>Behavior Log<' => '>行为日志<',
            '>Login Event<' => '>登录事件<',
            '>Security Event<' => '>安全事件<',
            '>Merchant Action<' => '>商户操作<',
            '>Provider<' => '>提供方<',
            '>Status<' => '>状态<',
            '>Masked Secret<' => '>脱敏密钥<',
            '>Migration State<' => '>当前状态<',
            '>Write Actions<' => '>写入能力<',
            '>Low Balance Threshold<' => '>余额预警阈值<',
            '>Console Notice<' => '>控制台提示<',
            '>Voice Tips<' => '>语音提醒<',
            '>Setting<' => '>设置项<',
            '>Delivery Channel<' => '>通知渠道<',
            '>Available Channels<' => '>可选渠道<',
            '>Action<' => '>操作<',
            '>Email<' => '>邮箱<',
            '>Mobile<' => '>手机号<',
            '>QQ Login<' => '>QQ 登录<',
            '>WeChat Login<' => '>微信登录<',
            '>New order notification<' => '>新订单通知<',
            '>Channel offline alert<' => '>通道离线提醒<',
            '>Account login alert<' => '>账户登录提醒<',
            '>Low balance notification<' => '>余额不足提醒<',
            '>Save Notification Settings<' => '>保存通知设置<',
            '>Reset<' => '>重置<',
            '>Credentials ready<' => '>凭据完整<',
            '>Credentials incomplete<' => '>凭据不完整<',
            '>Verification flow pending<' => '>验证流程暂未开放<',
            '>WxPusher UID<' => '>微信推送标识<',
            '>Telegram Chat ID<' => '>电报会话标识<',
            '>Current VIP<' => '>当前会员<',
            '>VIP Expiry<' => '>会员到期时间<',
            '>Enabled Plans<' => '>可用套餐数<',
            '>Price Range<' => '>价格区间<',
            '>Quota Enabled<' => '>额度限制套餐<',
            '>Purchase<' => '>购买状态<',
            '>Mode<' => '>模式<',
            '>Migration Guard<' => '>当前状态<',
            '>Key Actions<' => '>密钥操作<',
            '>Key Rotation<' => '>密钥重置<',
            '>Reset Sign Key<' => '>重置签名密钥<',
            '>Reset Appkey<' => '>重置通讯密钥<',
            '>Gateway Lines<' => '>网关线路<',
            '>Timeout Settings<' => '>超时设置<',
            '>Endpoint Overview<' => '>接口总览<',
            '>Merchant ID<' => '>商户 ID<',
            '>Username<' => '>登录账号<',
            '>Sign Key<' => '>签名密钥<',
            '>Legacy Appkey<' => '>通讯密钥<',
            '>Masked Sign Key<' => '>已脱敏签名密钥<',
            '>Masked Appkey<' => '>已脱敏通讯密钥<',
            '>Timeout Seconds<' => '>超时秒数<',
            '>Pending<' => '>待审核<',
            '>Total<' => '>总数<',
            '>Paid<' => '>已支付<',
            '>Expired Pending<' => '>已过期待支付<',
            '>Approved<' => '>已通过<',
            '>Rejected<' => '>已驳回<',
            '>Submit Domain<' => '>提交域名<',
            '>Create Domain<' => '>创建域名<',
            '>Cancel Edit<' => '>取消编辑<',
            '>Total Logs<' => '>日志总数<',
            '>Today<' => '>今日<',
            '>Payload Logs<' => '>载荷日志<',
            '>IP Count<' => '>IP 数量<',
            '>Log<' => '>日志<',
            '>Path<' => '>路径<',
            '>Type<' => '>类型<',
            '>User Agent<' => '>客户端信息<',
            '>New<' => '>新建<',
            '>Processing<' => '>处理中<',
            '>Resolved<' => '>已解决<',
            '>Replied<' => '>已回复<',
            '>Category<' => '>分类<',
            '>Title<' => '>标题<',
            '>Content<' => '>内容<',
            '>Reply<' => '>回复<',
            '>Create Ticket<' => '>提交工单<',
            '>Record<' => '>记录<',
            '>Order<' => '>订单<',
            '>Trade<' => '>交易信息<',
            '>Method<' => '>方式<',
            '>Amount<' => '>金额<',
            '>Paid At<' => '>支付时间<',
            '>Total Orders<' => '>订单总数<',
            '>Paid Orders<' => '>已支付订单<',
            '>Pending Orders<' => '>待支付订单<',
            '>Paid Amount<' => '>支付金额<',
            '>Success Rate<' => '>成功率<',
            '>Income Entries<' => '>收入笔数<',
            '>Expense Entries<' => '>支出笔数<',
            '>Income Amount<' => '>收入金额<',
            '>Expense Amount<' => '>支出金额<',
            '>Net Change<' => '>净变动<',
            '>Change<' => '>变动<',
            '>Balance<' => '>余额<',
            '>Memo<' => '>备注<',
            '>Recharge amount<' => '>充值金额<',
            '>Recharge method<' => '>充值方式<',
            '>Write Mode<' => '>写入模式<',
            '>Face/Auth Providers<' => '>实名渠道<',
            '>Merchant<' => '>商户<',
            '>Display<' => '>展示名称<',
            '>Verification<' => '>认证状态<',
            '>VIP / Balance<' => '>会员 / 余额<',
            '>Contacts<' => '>联系方式<',
            '>Verification Channels<' => '>认证渠道<',
            '>Passage Locked<' => '>通道限制套餐<',
            '>Duration<' => '>时长<',
            '>Fee Rate<' => '>费率<',
            '>Channels<' => '>通道范围<',
            '>Quota<' => '>额度限制<',
            '>Channel Grant<' => '>通道增配<',
            '>Base URL<' => '>基础地址<',
            '>Line<' => '>线路<',
            '>Entries<' => '>入口地址<',
            '>Endpoint Reference<' => '>接口说明<',
            '>Entry<' => '>入口<',
            '>Note<' => '>说明<',
            '>Open JSON payload<' => '>打开 JSON 数据<',
            '>Recharge rules<' => '>充值规则<',
            '>Save Enabled<' => '>已开放保存<',
            '>Save Domain Changes<' => '>保存域名修改<',
            '>Use order callback host<' => '>使用订单回调域名<',
            '>Use configured timeout_url<' => '>使用已配置的超时跳转地址<',
            '>Timeout URL<' => '>超时地址<',
            '>QR Code<' => '>二维码<',
            '>Key Reset<' => '>密钥重置<',
            '>Live<' => '>已生效<',
            '>Site Name<' => '>站点名称<',
            '>Site Domain<' => '>站点域名<',
            '>Current:<' => '>当前：<',
            '>Saved with form<' => '>随表单保存<',
            '>Create and continue to payment<' => '>创建订单并继续支付<',
            '>Purchase blocked during migration<' => '>当前暂未开放购买<',
            '>Voice Template<' => '>语音模板<',
            '>Delete<' => '>删除<',
            '>Edit / Resubmit<' => '>编辑 / 重新提交<',
            '>Payload<' => '>载荷<',
            'Use the migrated username and password flow to enter the Webman merchant center. Captcha, SMS/email-code, and Google-auth login branches are blocked until those security flows are migrated.' => '请直接使用账号密码登录商户中心。当前登录入口不提供验证码、短信/邮箱验证码或谷歌验证登录。',
            'If your account requires migrated captcha, SMS/email verification, or Google verification, keep using the legacy flow until that branch is rebuilt.' => '如果你的账号启用了额外安全校验，请联系管理员协助处理。',
            ', this Webman page lists your own frontend behavior and login audit records only. Delete, batch-delete, and cleanup actions remain blocked from the merchant center.' => '，当前页面仅展示你自己的前台行为与登录记录，并自动按当前商户范围过滤。',
            'Migration guard is enabled: logs are read-only here, and cross-merchant log access is blocked by the current front_token merchant scope.' => '当前页面会严格限制在当前商户范围内访问，不提供跨商户查询。',
            ', this Webman page keeps current keys masked on load, but now lets you rotate the merchant sign key and legacy appkey directly from Webman. QR payload generation and raw-secret export still stay blocked.' => '，当前页面会在加载时对现有密钥做脱敏展示，并支持重置签名密钥、通讯密钥与按所选线路生成商户二维码。原始密钥导出仍保持关闭。',
            'Current secrets remain masked until you choose to rotate them. Each reset returns the fresh secret one time so you can copy it into your integration before leaving this page.' => '现有密钥会一直以脱敏形式展示，只有在你主动重置时才会返回一次新的明文密钥，请在离开页面前完成保存。',
            'Use these actions to rotate the signing key stored in <code>ypay_user.user_key</code> or the legacy appkey stored in <code>ypay_userbasic.appkey</code>. Current values stay masked above, and each reset returns the fresh secret below one time only.' => '你可以在这里重置保存在 <code>ypay_user.user_key</code> 的签名密钥，或保存在 <code>ypay_userbasic.appkey</code> 的通讯密钥。当前值会保持脱敏，重置后仅返回一次新的明文密钥。',
            'Use the rotation panel above to generate a fresh sign key or appkey for the current merchant.' => '请使用上方密钥重置面板为当前商户生成新的签名密钥或通讯密钥。',
            'Legacy QR generation embeds merchant ID and raw key, so it is disabled during migration.' => '商户二维码会按所选线路、商户 ID 与当前通讯密钥生成加密信息，并在点击时按需展示。',
            ', this Webman page is read-only. VIP purchase and renewal are blocked until the audited merchant payment flow is migrated.' => '，当前页面仅展示会员套餐信息，购买与续费暂未开放。',
            ', this read-only Webman page replaces the first merchant order-log landing flow. Callback replay and status reset remain blocked until their audit controls are migrated.' => '，当前页面用于承接商户订单记录入口。已支持回调重放，状态重置入口已关闭。',
            'Migration guard is enabled: legacy `/Deal/set_function` requests now return a safe 405 response and do not replay callbacks or reset order status.' => '当前已关闭 `/Deal/set_function` 的状态重置能力，请直接使用现有订单操作入口。',
            ', your Webman merchant center can now create balance recharge orders directly. The only remaining legacy write guard on this screen is CDK redemption.' => '，当前已支持直接创建余额充值订单。该页面不处理卡密兑换，请使用卡密兑换入口。',
            'Create a new recharge order' => '创建新的充值订单',
            'Supported methods are loaded from the current system recharge mapping. Amounts outside the configured range are rejected before an upstream handoff is attempted.' => '可用充值方式由当前系统充值映射动态加载。超出配置范围的金额会在跳转上游前被直接拦截。',
            'Enabled methods: <strong>' => '已启用方式：<strong>',
            '. Minimum amount: <strong>' => '。最小金额：<strong>',
            '. Maximum amount: <strong>' => '。最大金额：<strong>',
            ', this read-only Webman page exposes your own balance ledger only. Balance adjustment, recharge, VIP purchase, and cleanup actions remain blocked until audited merchant controls are migrated.' => '，当前页面仅展示你自己的余额流水，暂不提供余额调账、充值补录、会员购买与日志清理操作。',
            ', notification-channel and low-balance settings can now be saved on Webman. Channel binding and test-send flows are still intentionally blocked until their external side effects are rebuilt.' => '，当前已支持保存通知渠道与余额预警设置。此页仅保留通知设置保存与渠道可用性查看。',
            'Save is enabled for the current merchant only and writes into <code>ypay_userbasic</code>. Binding QR flows, verification flows, and test-send actions remain blocked in this migration step.' => '当前仅允许为本商户保存设置，并写入 <code>ypay_userbasic</code>。通知渠道是否可用会直接在页面展示。',
            'Use the form below to persist low-balance, console, and voice-tip preferences. Unsupported delivery channels stay visible for audit clarity and normalize to <code>close</code> if they are disabled system-wide.' => '你可以通过下列表单保存余额预警、控制台提示与语音提醒偏好。系统级被禁用的渠道仍会展示出来用于确认当前可用状态，并在保存时自动归一到 <code>close</code>。',
            'Voice-tip text uses <code>[money]</code> as the amount placeholder.' => '语音提醒文案使用 <code>[money]</code> 作为金额占位符。',
            'Leave blank to clear the extra console notice line.' => '留空即可清空额外的控制台提示语。',
            'Voice template preview:' => '语音模板预览：',
            'Optional merchant console note' => '可选的商户控制台提示',
            'Saving notification settings...' => '正在保存通知设置...',
            'Notification settings saved successfully.' => '通知设置已保存。',
            'Notification save failed.' => '通知设置保存失败。',
            'Notification save failed. Please try again.' => '通知设置保存失败，请稍后重试。',
            ', this Webman page now supports replaying paid-order callbacks directly from the merchant center while keeping status reset under migration protection.' => '，当前页面已支持直接在商户中心重放已支付订单回调，状态重置入口已下线。',
            'Paid-order callback replay is now live on legacy <code>/Deal/set_function</code>. Status reset stays protected and still returns a safe 405 response.' => '订单回调接口 <code>/Deal/set_function</code> 现仅保留已支付订单回调重放；状态重置已关闭。',
            '>Detail<' => '>详情<',
            ', this Webman page keeps the legacy Google Auth landing route available, but verification, binding, unbinding, and QR enrollment remain blocked until the audited security flow is migrated.' => '，当前页面展示谷歌验证信息，暂不提供验证、绑定、解绑与二维码开通操作。',
            '当前页面已支持修改商户密码和重置密钥；Google Auth、实名认证和账号注销等流程仍保留在受控迁移阶段。' => '当前页面已支持修改商户密码和重置密钥；谷歌验证、实名认证和账号注销请在当前安全页中处理。',
            '密码修改已在 Webman 生效，保存后需要重新登录。Google Auth 开通/解绑及注销账号等高风险操作，仍会返回受控响应，待完整重建后再开放。' => '密码修改已生效，保存后需要重新登录。谷歌验证开通/解绑及账号注销等高风险操作，请在当前安全页中处理。',
            ', current page keeps the legacy Google Auth landing route available, but verification, binding, unbinding, and QR enrollment remain blocked until the audited security flow is migrated.' => '，当前页面展示谷歌验证信息，暂不提供验证、绑定、解绑与二维码开通操作。',
            'Security verification setup notice' => '安全验证说明',
            'This merchant flow is still pending migration.' => '当前商户安全功能暂不提供完整操作。',
            'Use the legacy flow if you need to complete Google Auth verification or binding during migration.' => '如需完成谷歌验证校验或绑定，请在当前安全页中处理。',
            'read-only' => '信息查看',
            'Password save failed.' => '密码保存失败。',
            'Password save failed. Please try again.' => '密码保存失败，请稍后重试。',
            ', this Webman page now lets you submit, edit, resubmit, and delete your own domains while keeping the audit scope locked to the current merchant.' => '，当前页面已支持提交、修改、重新提交与删除你自己的域名，同时严格将审核范围限制在当前商户内。',
            'New and edited domains still pass through the existing approval rules: blacklist blocks immediately, whitelist or auto-approve can mark a domain approved, and everything else returns to pending review.' => '新增或修改后的域名仍会沿用现有审核规则：命中黑名单会直接拦截，命中白名单或自动通过规则会直接审核通过，其余情况则进入待审核状态。',
            'Use this form to add a new merchant domain. Choosing an existing row for edit will switch the form into resubmission mode and reset that row back through the approval rules.' => '你可以通过这里提交新的商户域名。选择已有记录进行编辑后，表单会切换到重新提交模式，并重新走一遍审核规则。',
            'Shown in the domain review list for your merchant account.' => '该名称会展示在当前商户的域名审核列表中。',
            'Do not include spaces. `http://` and trailing `/` are normalized automatically.' => '请不要包含空格，`http://` 前缀和末尾 `/` 会自动规范化。',
            ', this Webman page now lets you create and delete your own support tickets while keeping admin replies and cross-merchant access safely scoped.' => '，当前页面已支持创建和删除你自己的工单，同时会安全限制管理员回复展示与跨商户访问范围。',
            'Ticket create and delete are enabled. Reply workflows still remain admin-side, so Webman only opens and removes merchant-owned tickets here.' => '当前已开放工单创建与删除。回复流程仍保留在管理员侧，因此这里只允许处理商户自己创建的工单。',
            'Open a new merchant ticket with one of the enabled categories. If no categories are enabled, the create form stays disabled until the admin restores at least one category.' => '请使用已启用的工单分类提交新的工单。如果当前没有任何启用分类，创建表单会保持禁用，直到管理员恢复至少一个分类。',
            'Categories come from the enabled admin ticket-category list.' => '工单分类来自管理员侧已启用的工单分类列表。',
            'Keep the title short and specific so the admin queue is easier to sort.' => '建议标题简短且明确，便于管理员侧队列快速分拣。',
            'Enabled Categories:' => '已启用分类：',
            'Describe the issue' => '请输入问题标题',
            'Provide the full issue details, steps, and expected outcome.' => '请填写完整的问题详情、复现步骤和期望结果。',
            ', this Webman page replaces the first merchant order-log landing flow. Callback replay and status reset remain blocked until their audit controls are migrated.' => '，当前页面用于承接商户订单记录入口。回调重放已开放，状态重置入口已下线。',
            'No merchant notification settings were found.' => '当前未找到通知设置项。',
            'No quick-login bindings are configured for this merchant.' => '当前商户未配置快捷登录绑定。',
            'No merchant order records matched the current filter.' => '当前筛选条件下暂无订单记录。',
            'No merchant recharge records matched the current filter.' => '当前筛选条件下暂无充值记录。',
            'No merchant money logs matched the current filter.' => '当前筛选条件下暂无资金日志。',
            'No merchant domains matched the current filter.' => '当前筛选条件下暂无域名记录。',
            'No merchant login logs matched the current filter.' => '当前筛选条件下暂无登录日志。',
            'No merchant tickets matched the current filter.' => '当前筛选条件下暂无工单记录。',
            'No enabled VIP packages matched the current filter.' => '当前筛选条件下暂无可用会员套餐。',
            'No enabled categories' => '暂无启用分类',
            'No enabled ticket categories are configured.' => '当前未配置可用工单分类。',
            'All enabled channels' => '全部已启用通道',
            'No collection quota' => '无限制额度',
            'No extra channel grant' => '无额外通道增配',
            'Unavailable because the global recharge mapping or upstream paylist is missing.' => '因全局充值映射或上游支付通道缺失，当前方式不可用。',
            'Accepted format: non-negative amount with up to 2 decimal places.' => '格式要求：非负金额，最多支持 2 位小数。',
            'Use the direct-save panel below' => '请使用下方直存面板',
            'Not available' => '暂不可用',
            'No recent merchant login logs are available.' => '当前没有可展示的最近登录日志。',
            'No payload captured' => '未采集到载荷',
            'No merchant orders matched the current filter.' => '当前筛选条件下暂无订单记录。',
            'No gateway lines are available.' => '当前没有可用网关线路。',
            'No endpoint metadata is available.' => '当前没有可展示的接口说明。',
            'Legacy EPay compatible browser form entry.' => '易支付网关的浏览器表单下单入口。',
            'Saving WxPusher UID...' => '正在保存微信推送标识...',
            'WxPusher UID saved.' => '微信推送标识已保存。',
            'WxPusher UID save failed.' => '微信推送标识保存失败。',
            'WxPusher UID save failed. Please try again.' => '微信推送标识保存失败，请稍后重试。',
            '。你可以直接粘贴新的 UID，也可以清空当前商户已保存的值。' => '。你可以直接粘贴新的微信推送标识，也可以清空当前商户已保存的值。',
            '>保存 UID<' => '>保存标识<',
            '>清空 UID<' => '>清空标识<',
            'Checking current WxPusher status...' => '正在检查当前微信推送状态...',
            'A WxPusher UID is already stored for this merchant.' => '当前商户已保存微信推送标识。',
            'No WxPusher UID is stored yet.' => '当前商户尚未保存微信推送标识。',
            'Status check failed. Please try again.' => '状态检查失败，请稍后重试。',
            'Clear the stored WxPusher UID for the current merchant?' => '确认清空当前商户已保存的微信推送标识吗？',
            'Clearing WxPusher UID...' => '正在清空微信推送标识...',
            'WxPusher UID cleared.' => '微信推送标识已清空。',
            'Saving Telegram chat ID...' => '正在保存电报会话标识...',
            'Telegram chat ID saved.' => '电报会话标识已保存。',
            'Telegram save failed.' => '电报会话标识保存失败。',
            'Telegram save failed. Please try again.' => '电报会话标识保存失败，请稍后重试。',
            '。在这里保存后，会直接替换当前商户的 Telegram Chat ID。' => '。在这里保存后，会直接替换当前商户的电报会话标识。',
            'placeholder="请输入 Telegram Chat ID"' => 'placeholder="请输入电报会话标识"',
            '>保存 Chat ID<' => '>保存标识<',
            '>清空 Chat ID<' => '>清空标识<',
            'Clear the stored Telegram chat ID for the current merchant?' => '确认清空当前商户已保存的电报会话标识吗？',
            'Clearing Telegram chat ID...' => '正在清空电报会话标识...',
            'Telegram chat ID cleared.' => '电报会话标识已清空。',
            'Clear the stored ' . '\' + button.dataset.label + \'' . ' binding for the current merchant?' => '确认清除当前商户的\' + button.dataset.label + \'绑定信息吗？',
            'Unbind failed.' => '解绑失败。',
            'Unbind failed. Please try again.' => '解绑失败，请稍后重试。',
            ', your Webman merchant center can now create balance recharge orders and redeem CDKs directly from the same screen.' => '，当前已可在同一页面直接创建余额充值订单并兑换卡密。',
            'A successful callback credits the merchant balance, writes a `money_log` row, and applies the configured superior rebate when `is_aff=1` and `aff_type=0`.' => '充值成功回调后会为商户余额入账、写入 `money_log` 记录，并在 `is_aff=1` 且 `aff_type=0` 时结算对应上级返佣。',
            'CDK redemption is now live for balance cards and VIP cards owned by the current merchant session.' => '当前商户会话下，余额卡与会员卡的卡密兑换功能已开放。',
            'Last recharge:' => '最近充值：',
            'Pending amount:' => '待支付金额：',
            'Last order:' => '最近订单：',
            'Last money log:' => '最近资金日志：',
            'Last domain submission:' => '最近域名提交：',
            'Last login log:' => '最近登录日志：',
            'Last ticket:' => '最近工单：',
            'Legacy EPay compatible JSON/API entry.' => '易支付网关的 JSON/API 下单入口。',
            'Upstream payment notify callback entry.' => '上游支付异步回调入口。',
            'Upstream payment return callback entry.' => '上游支付同步跳转入口。',
            'CDK redemption is still intentionally guarded until that branch is migrated and audited separately.' => 'CDK 兑换当前暂未开放。',
            'A successful callback credits the merchant balance, writes a `money_log` row, and applies the configured superior rebate when `is_aff=1` and `aff_type=0`.' => '充值成功回调后，会为商户余额入账、写入 `money_log` 流水，并在 `is_aff=1` 且 `aff_type=0` 时结算上级返佣。',
            'Rebate Type:' => '返佣类型：',
            'Rebate Ratio:' => '返佣比例：',
            'Upstream Merchant:' => '上级商户：',
            'Invite URL:' => '邀请链接：',
            'Last Invite:' => '最近邀请时间：',
            'Upstream Channel' => '上游通道',
            'Local Channel' => '本地通道',
            'Normal merchant' => '普通商户',
            'Current: ' => '当前：',
            'Current: Closed' => '当前：关闭',
            'Current: Email' => '当前：邮箱',
            'Current: WxPusher' => '当前：WxPusher',
            'Current: Telegram' => '当前：Telegram',
            'read-only' => '只读',
            'Live' => '已启用',
            'Use order callback host' => '使用订单回调域名',
            'Use configured timeout_url' => '使用已配置的超时跳转地址',
            'Balance recharge' => '余额充值',
            'Balance deduction' => '余额扣减',
            'Balance increase' => '余额增加',
            'Balance decrease' => '余额减少',
            'Merchant password change is live in the Webman merchant center and will require a fresh login after save' => '商户密码修改已生效，保存后需要重新登录。',
            'Quick-login unbind is live in Webman. Fresh OAuth bind flows still follow the legacy route during migration.' => '快捷登录解绑已生效；当前页面仅展示已接入状态，不再提供新的授权绑定入口。',
            ' (disabled)' => '（已关闭）',
            'Signing in...' => '正在登录...',
            'Login failed. Please try again.' => '登录失败，请稍后重试。',
            'Waiting for payment confirmation' => '等待支付确认',
            'The page will refresh the status automatically.' => '页面会自动刷新支付状态。',
            'QR code expired. Redirecting back to the recharge page...' => '二维码已过期，正在返回充值页面...',
            'Recharge timed out' => '充值已超时',
            'Create a new recharge order if you still need to top up the balance.' => '如仍需充值，请重新创建一笔充值订单。',
            'Recharge paid successfully' => '充值支付成功',
            'Redirecting back to the merchant recharge list now.' => '正在返回商户充值记录列表。',
            'QR code ready' => '二维码已生成',
            'Please finish the payment in the matching wallet app.' => '请在对应的钱包应用内完成支付。',
            'Saving notification settings...' => '正在保存通知设置...',
            'Notification settings saved successfully.' => '通知设置保存成功。',
            'Notification save failed.' => '通知设置保存失败。',
            'Notification save failed. Please try again.' => '通知设置保存失败，请稍后重试。',
            'Saving domain changes...' => '正在保存域名修改...',
            'Creating domain...' => '正在创建域名...',
            'Domain saved successfully.' => '域名保存成功。',
            'Domain save failed.' => '域名保存失败。',
            'Domain save failed. Please try again.' => '域名保存失败，请稍后重试。',
            'Editing domain #' => '正在编辑域名 #',
            'Deleting domain...' => '正在删除域名...',
            'Domain deleted successfully.' => '域名删除成功。',
            'Domain delete failed.' => '域名删除失败。',
            'Domain delete failed. Please try again.' => '域名删除失败，请稍后重试。',
            'Edit / Resubmit Domain' => '编辑 / 重新提交域名',
            'this domain' => '该域名',
            'Creating ticket...' => '正在创建工单...',
            'Ticket created successfully.' => '工单创建成功。',
            'Ticket create failed.' => '工单创建失败。',
            'Ticket create failed. Please try again.' => '工单创建失败，请稍后重试。',
            'Deleting ticket...' => '正在删除工单...',
            'Ticket deleted successfully.' => '工单删除成功。',
            'Ticket delete failed.' => '工单删除失败。',
            'Ticket delete failed. Please try again.' => '工单删除失败，请稍后重试。',
            'Saving merchant password...' => '正在保存商户密码...',
            'Saving sign key...' => '正在重置签名密钥...',
            'Saving appkey...' => '正在重置通讯密钥...',
            'Password updates are available here.' => '当前页面支持直接修改登录密码。',
            'Password save failed.' => '密码保存失败。',
            'Password save failed. Please try again.' => '密码保存失败，请稍后重试。',
            'Resetting ' => '正在重置',
            ' reset successfully. Copy the fresh secret now.' => ' 重置成功，请立即保存新的密钥。',
            ' reset successfully. Copy the new secret now.' => ' 重置成功，请立即保存新的密钥。',
            ' reset failed.' => ' 重置失败。',
            ' reset failed. Please try again.' => ' 重置失败，请稍后重试。',
            'Generate a fresh merchant sign key now?' => '确认立即生成新的商户签名密钥吗？',
            'Generate a fresh merchant appkey now?' => '确认立即生成新的商户通讯密钥吗？',
            'sign key' => '签名密钥',
            'appkey' => '通讯密钥',
            'Bind tips: ' => '绑定提示：',
            'No recharge methods are configured yet. Bind an upstream paylist in system config before using merchant balance top-up.' => '当前尚未配置可用的充值方式，请先在系统配置中绑定上游支付通道后再使用商户余额充值。',
            'Use the rotation panel above to generate a fresh sign key or appkey for the current merchant.' => '可通过上方密钥重置面板为当前商户生成新的签名密钥或通讯密钥。',
            'cannot enter the merchant center right now.' => '当前暂时无法进入商户中心。',
            'Delete ' => '删除 ',
            'this ticket' => '该工单',
            'No reason was provided.' => '未提供原因说明。',
            'Nothing to unbind' => '暂无可解绑内容',
            '>Unbind<' => '>解绑<',
            'placeholder="Optional merchant console note"' => 'placeholder="可选的控制台提示内容"',
            'placeholder="Describe the issue"' => 'placeholder="请简要描述问题"',
            'placeholder="Provide the full issue details, steps, and expected outcome."' => 'placeholder="请填写完整的问题详情、复现步骤与预期结果"',
            'placeholder="My Checkout Site"' => 'placeholder="例如：我的收银站"',
            'placeholder="checkout.example.com"' => 'placeholder="例如：pay.aipay.local"',
        ];
    }

    private static function normalizeReplacement(string $search, string $replacement): string
    {
        $replacement = self::repairReplacementText($replacement);

        if (str_starts_with($search, '<title>') && str_ends_with($search, '</title>')) {
            return '<title>' . self::stripTitle($replacement) . '</title>';
        }

        if (str_starts_with($search, '>') && str_ends_with($search, '<')) {
            return '>' . self::stripTextNode($replacement) . '<';
        }

        if (preg_match('/^placeholder="(.+)"$/', $search) === 1) {
            return 'placeholder="' . self::stripPlaceholder($replacement) . '"';
        }

        return $replacement;
    }

    private static function repairReplacementText(string $value): string
    {
        $value = ApiResponse::normalizeText($value);
        $converted = @mb_convert_encoding($value, 'UTF-8', 'GB18030');
        if (is_string($converted) && $converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
            if (self::mojibakeScore($converted) < self::mojibakeScore($value)) {
                $value = $converted;
            }
        }

        $value = str_replace('??', '', $value);
        $value = preg_replace('/\?+(?=$|[\s<>"\'\/\]\)])/u', '', $value) ?? $value;
        $value = preg_replace('/(?<=[>\s])\?+(?=<)/u', '', $value) ?? $value;

        return trim($value);
    }

    private static function stripTitle(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/^<title>/u', '', $value) ?? $value;
        $value = preg_replace('/(?:<\/title>|\/title>|title>)$/u', '', $value) ?? $value;

        return trim($value, ' <>/');
    }

    private static function stripTextNode(string $value): string
    {
        $value = trim($value);
        if (str_starts_with($value, '>')) {
            $value = substr($value, 1);
        }
        if (str_ends_with($value, '<')) {
            $value = substr($value, 0, -1);
        }

        return trim($value, ' ?');
    }

    private static function stripPlaceholder(string $value): string
    {
        if (preg_match('/^placeholder="(.+)"$/', $value, $matches) === 1) {
            return trim((string)$matches[1]);
        }

        return trim($value, ' "');
    }

    private static function mojibakeScore(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        preg_match_all('/[鍟鎴璇閫鏀鐧鍏寰缁闈绯绾缃鍒鍙闂褰璋鍥鏃锛銆鍩妯閰鎻鍚璁鑵闃鐭閭璧缂鎺绔绠鍛橀偖嶅悊]/u', $text, $matches);

        return count($matches[0]);
    }
}
