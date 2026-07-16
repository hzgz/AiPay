const EXACT_MAP: Record<string, string> = {
  'safe dependent update': '安全依赖更新示例',
  'plugin managed fixture': '插件托管示例',
  'batch delete fixture a': '批量删除示例A',
  'batch delete fixture b': '批量删除示例B',
  'batch restore fixture a': '批量恢复示例A',
  'batch restore fixture b': '批量恢复示例B',
  'single restore fixture': '单条恢复示例',
  'homepage theme': '站点首页模板',
  'member center theme': '会员中心模板',
  'payment page theme': '支付页面模板',
  'demo page theme': '演示页面模板',
  'document page theme': '文档页面模板',
  'announcement page theme': '公告页面模板',
  active: '已启用',
  available: '可使用',
  enabled: '已启用',
  disabled: '已停用',
  recycled: '回收站',
  'missing style': '缺少样式文件',
  'missing screenshot': '缺少预览图',
  'metadata ready': '元数据完整',
  'metadata incomplete': '元数据不完整',
  'config ready': '配置已就绪',
  'config missing': '缺少系统配置',
  'using default value': '当前使用默认配置',
  'no config mapping': '未接入系统配置',
  'platform announcement': '平台公告',
  'industry news': '行业资讯',
  faq: '常见问题',
  'new window': '新窗口打开',
  'same window': '当前窗口打开',
  '/doc': '文档中心',
  '/demo': '演示中心',
  '/news/index': '公告页面',
  '/admin.photo/list/name/images': '系统图片目录接口',
  '/admin.photo/list/name/news': '公告图片目录接口',
  '/admin.photo/list/name/plugins': '插件素材目录接口',
  '/admin.photo/list/name/qrcode': '二维码目录接口',
  '/admin.photo/list/name/pay_qr': '支付二维码目录接口',
  '/admin.photo/list/name/merchant_assets': '商户素材目录接口',
  '/ypay.shop/clear': '数据清理页',
  '/ypay.shop/clearOrder': '订单清理接口',
  '/ypay.shop/clearRecharge': '充值清理接口',
  '/ypay.shop/clearAdminLog': '管理员日志清理接口',
  '/ypay.shop/clearUserLog': '商户日志清理接口',
  ypay_order: '订单表',
  ypay_recharge: '充值记录表',
  admin_admin_log: '管理员日志表',
  admin_front_log: '商户日志表',
  'created from smoke test': '由示例数据创建',
  'updated from smoke test': '由示例数据更新',
  'smoke ticket': '工单示例',
  'merchant batch delete smoke': '批量删除示例商户',
  'blocked merchant batch delete smoke': '批量删除示例商户（阻塞样例）',
  'deletable merchant batch delete smoke': '批量删除示例商户（可删样例）',
  'merchant batch delete smoke subordinate': '批量删除示例商户子项',
  legacy_epay: '易支付网关插件',
  legacy_epay_fee_deduct: '易支付手续费扣减',
  'legacy epay compatibility': '易支付网关插件',
  'compatibility wrapper for the legacy payment flow during the thinkphp to webman migration.':
    '用于接入易支付网关模式的插件。',
  'legacy smoke upstream': '易支付支付通道',
  'aipay modernization': 'AiPay官方',
  smokeapimapi: '接口单笔支付示例',
  smokepayapisubmit: '支付接口提交示例',
  smokeapipayment: '接口支付示例',
  smokemapi: '移动端接口示例',
  smokepaysubmit: '支付提交流程示例',
  smokesubmit: '提交支付示例',
  alipay_software: '支付宝软件通道',
  qqpay_software: 'QQ 软件通道',
  alipay_mck: '支付宝免CK插件',
  alipay_bill: '支付宝二维码账单插件',
  alipay_official: '支付宝官方版V3插件',
  qqpay_mg: 'QQ 免挂机通道',
  wxpay_software: '微信软件通道',
  wxpay_v3: '微信官方 V3 接口',
  jiaofeiyi_wxpay: '缴费易微信',
  jiaofeiyi_alipay: '缴费易支付宝',
  usdt: 'USDT 收款通道',
  'report tips': '举报提示',
  'domain black': '域名黑名单',
  'domain white': '域名白名单',
  shop: '商城',
  cdk: '卡券',
  网站bug: '网站问题',
  'shield key': '风控密钥',
  admin: '本地管理员账号',
  api: '接口',
  tag: '标签',
  alipay: '支付宝',
  wxpay: '微信支付',
  wechat: '微信支付',
  qqpay: 'QQ 钱包',
  epay: '易支付',
  epay_ali: '易支付支付宝',
  epay_wechat: '易支付微信',
  accesskey: '访问密钥',
  'accesskey id': '访问密钥 ID',
  'accesskey secret': '访问密钥密文',
  secretkey: '访问密钥密文',
  'smoke pay theme': '支付模板示例',
  'smoke home theme': '首页模板示例',
  'theme delete': '模板删除',
  'theme activate': '模板启用',
  'permission create': '权限创建',
  'permission update': '权限更新',
  'permission delete': '权限删除',
  'permission status': '权限状态变更',
  'domain recycle smoke fixture': '域名回收示例',
  'news-editor-upload': '公告编辑器上传图片',
  'plugin-editor-upload': '插件编辑器上传图片',
  'news-editor-upload.png': '公告编辑器上传图片',
  'plugin-editor-upload.png': '插件编辑器上传图片',
  'rsa 私钥': '站点私钥',
  'think 验证码密钥': '验证码密钥',
  '短信宝 api': '短信宝接口密钥',
  '支付宝 rsa 公钥': '支付宝公钥',
  '域名联调示例-blocked.example.com': '黑名单域名示例',
  '域名联调示例-create.example.com': '白名单域名示例',
  index99: '经典支付风格首页',
  home_temp: '首页模板配置键'
}

const REGEX_RULES: Array<{ pattern: RegExp; label: string }> = [
  { pattern: /^cdepend_[a-f0-9]+$/i, label: '依赖通道示例' },
  { pattern: /^cplugin_[a-f0-9]+$/i, label: '插件通道示例' },
  { pattern: /^cbatcha_[a-f0-9]+$/i, label: '批量通道示例A' },
  { pattern: /^cbatchb_[a-f0-9]+$/i, label: '批量通道示例B' },
  { pattern: /^ccreate_[a-f0-9]+$/i, label: '创建示例通道' },
  { pattern: /^cupdate_[a-f0-9]+$/i, label: '更新示例通道' },
  { pattern: /^dependent channel updated [a-f0-9]+$/i, label: '依赖通道已更新' },
  { pattern: /^plugin channel [a-f0-9]+$/i, label: '插件通道示例' },
  { pattern: /^batch channel a [a-f0-9]+$/i, label: '批量通道示例A' },
  { pattern: /^batch channel b [a-f0-9]+$/i, label: '批量通道示例B' },
  { pattern: /^smoke updated channel [a-f0-9]+$/i, label: '示例更新通道' },
  { pattern: /^smoke local channel [a-f0-9]+$/i, label: '本地示例通道' },
  { pattern: /^legacy_epay_smoke_[a-z0-9_]+$/i, label: '易支付支付通道' },
  { pattern: /^art_merchant_demo$/i, label: '演示商户账号' },
  { pattern: /^risk_write_smoke_[a-z0-9_]+$/i, label: '风控示例商户' },
  { pattern: /^smoke_account_[a-z0-9]+$/i, label: '收款账号示例' },
  { pattern: /^merchant_batch_delete_smoke_[a-z0-9_]+$/i, label: '批量删除示例商户' },
  { pattern: /^news_recycle_smoke_[a-z0-9_]+_single$/i, label: '公告回收单条恢复示例' },
  { pattern: /^news_recycle_smoke_[a-z0-9_]+_batch_a$/i, label: '公告回收批量恢复示例A' },
  { pattern: /^news_recycle_smoke_[a-z0-9_]+_batch_b$/i, label: '公告回收批量恢复示例B' },
  { pattern: /^nav_recycle_smoke_[a-z0-9_]+_single$/i, label: '导航回收单条恢复示例' },
  { pattern: /^nav_recycle_smoke_[a-z0-9_]+_batch_a$/i, label: '导航回收批量恢复示例A' },
  { pattern: /^nav_recycle_smoke_[a-z0-9_]+_batch_b$/i, label: '导航回收批量恢复示例B' },
  {
    pattern: /^linked ticket for ticket_category_write_smoke_[a-z0-9_]+$/i,
    label: '已关联分类工单示例'
  },
  { pattern: /^ticket_category_[a-z0-9_]+$/i, label: '工单分类示例' },
  { pattern: /^ticket_category_[a-z0-9_]+_linked$/i, label: '已关联分类示例' },
  {
    pattern: /^linked content for ticket_category_write_smoke_[a-z0-9_]+$/i,
    label: '已关联分类内容示例'
  },
  { pattern: /^les_[a-z0-9_]+$/i, label: '示例商户单号' },
  { pattern: /^risk-[a-z0-9]+\.example\.com$/i, label: '风控示例域名' },
  { pattern: /^plugin_download_recycle_smoke_/i, label: '插件回收示例' },
  { pattern: /^quick_login_write_smoke_[a-z0-9_]+(?:-bound)?$/i, label: '快捷登录示例' },
  { pattern: /^channel_catalog_write_smoke_[a-z0-9_]+$/i, label: '本地通道示例' },
  { pattern: /^(wx|wechat|qq|alipay|ali|merchant|channel)-test-[a-z0-9-]+$/i, label: '示例账号标识' },
  { pattern: /^batch payment method a [a-f0-9]+$/i, label: '批量支付方式A' },
  { pattern: /^batch payment method b [a-f0-9]+$/i, label: '批量支付方式B' },
  { pattern: /^smoke payment method [a-f0-9]+$/i, label: '支付方式示例' },
  { pattern: /^channel smoke account [a-z0-9]+$/i, label: '收款账号示例' },
  { pattern: /^smkp[a-z0-9*_-]+$/i, label: '系统生成方式标识' },
  { pattern: /^keep[a-z0-9*_-]+$/i, label: '已脱敏卡密' },
  { pattern: /^dependent pool [a-z0-9_]+$/i, label: '轮询池示例' },
  { pattern: /^vip_sort_smoke_[a-z0-9_]+$/i, label: '会员排序示例' },
  { pattern: /^cdk_smoke_vip_[a-z0-9_]+$/i, label: '卡券示例会员' },
  { pattern: /^recharge_[a-z0-9_]+$/i, label: '充值示例记录' },
  { pattern: /^recharge_read_[a-z0-9_]+$/i, label: '充值示例账号' },
  { pattern: /^domain_(write|audit|delete|recycle)_smoke_[a-z0-9_]+$/i, label: '域名示例' },
  { pattern: /^merchant_impersonation_smoke_[a-z0-9_]+$/i, label: '商户代登示例' },
  { pattern: /^merchant batch delete pool [a-z0-9_]+$/i, label: '批量删除示例轮询池' },
  { pattern: /^[a-z0-9._-]+@ex\.com$/i, label: '示例联系邮箱' },
  { pattern: /^[0-9a-f]{10,}@example\.test$/i, label: '示例邮箱' },
  {
    pattern: /^news-editor-upload(?:-\d+)?\.(png|jpg|jpeg|webp|gif)$/i,
    label: '公告编辑器上传图片'
  },
  {
    pattern: /^plugin-editor-upload(?:-\d+)?\.(png|jpg|jpeg|webp|gif)$/i,
    label: '插件编辑器上传图片'
  },
  {
    pattern: /^[a-f0-9]{20,}\.(png|jpg|jpeg|webp|gif)$/i,
    label: '系统素材图片'
  }
]

function normalizeFixtureFallback(value: string) {
  let normalized = value
    .replaceAll('example.test', '示例邮箱')
    .replaceAll('AiPay Smoke', 'AiPay 演示站')
    .replace(/AiPay\s*演示站/g, 'AiPay 演示站')
    .replaceAll('Puple', '默认主题')

  const replacements: Array<[RegExp, string]> = [
    [/\bSystem Auth\b/gi, '系统权限示例'],
    [/\bPayment Auth\b/gi, '支付权限示例'],
    [/\bContent Auth\b/gi, '内容权限示例'],
    [/\bMenu Smoke\b/gi, '菜单示例'],
    [/\bRole Smoke\b/gi, '角色示例'],
    [/\bChannel Catalog Auth\b/gi, '本地通道示例'],
    [/\bAdmin Log Cleanup Operator\b/gi, '日志清理示例账号'],
    [/\bAdmin Batch Target A\b/gi, '管理员批量示例目标A'],
    [/\bAdmin Batch Target B\b/gi, '管理员批量示例目标B'],
    [/\bBatch Payment Method A\b/gi, '批量支付方式A'],
    [/\bBatch Payment Method B\b/gi, '批量支付方式B'],
    [/\bSmoke Payment Method\b/gi, '支付方式示例'],
    [/\bDependent Pool\b/gi, '轮询池示例'],
    [/\bDependent Channel Updated\b/gi, '依赖通道已更新'],
    [/\bchannel smoke account\b/gi, '收款账号示例'],
    [/\brisk_write_smoke_[a-z0-9_]+\b/gi, '风控示例商户'],
    [/\bart_merchant_demo\b/gi, '演示商户账号'],
    [/\bmerchant impersonation smoke\b/gi, '商户代登示例'],
    [/\bmerchant impersonation\b/gi, '商户代登'],
    [/\bmanual balance adjustment\b/gi, '手工余额调账'],
    [/\bManual audit rejected\b/gi, '人工审核驳回'],
    [/\blegacy rejection reason\b/gi, '驳回原因'],
    [/\blegacy reason\b/gi, '原因'],
    [/\btheme delete\b/gi, '模板删除'],
    [/\btheme activate\b/gi, '模板启用'],
    [/\bPayPro\b/gi, '经典支付'],
    [/\bpermission create\b/gi, '权限创建'],
    [/\bpermission update\b/gi, '权限更新'],
    [/\bpermission delete\b/gi, '权限删除'],
    [/\bpermission status\b/gi, '权限状态变更'],
    [/\bSmoke Pay Theme\b/gi, '支付模板示例'],
    [/\bSmoke Home Theme\b/gi, '首页模板示例'],
    [/\bdomain recycle smoke fixture\b/gi, '域名回收示例'],
    [/\bshield key\b/gi, '风控密钥'],
    [/\bAccessKey Secret\b/gi, '访问密钥密文'],
    [/\bAccessKey ID\b/gi, '访问密钥 ID'],
    [/\bAccessKey\b/gi, '访问密钥'],
    [/\bSecretKey\b/gi, '访问密钥密文'],
    [/\bnews-editor-upload\b/gi, '公告编辑器上传图片'],
    [/\bplugin-editor-upload\b/gi, '插件编辑器上传图片'],
    [/\/admin\.photo\/list\/name\/images/gi, '系统图片目录接口'],
    [/\/admin\.photo\/list\/name\/news/gi, '公告图片目录接口'],
    [/\/admin\.photo\/list\/name\/plugins/gi, '插件素材目录接口'],
    [/\/admin\.photo\/list\/name\/qrcode/gi, '二维码目录接口'],
    [/\/admin\.photo\/list\/name\/pay_qr/gi, '支付二维码目录接口'],
    [/\/admin\.photo\/list\/name\/merchant_assets/gi, '商户素材目录接口'],
    [/\/api\/admin\/users\/\d+\/impersonate/gi, '商户代登接口'],
    [/\/api\/admin\/permissions\/create/gi, '权限创建接口'],
    [/\/api\/admin\/permissions\/reorder/gi, '权限排序接口'],
    [/\/api\/admin\/permissions\/\d+\/status/gi, '权限状态接口'],
    [/\/api\/admin\/permissions\/\d+\/update/gi, '权限更新接口'],
    [/\/api\/admin\/permissions\/\d+\/delete/gi, '权限删除接口'],
    [/\/api\/admin\/themes\/pay\/[a-z0-9_]+\/activate/gi, '支付模板启用接口'],
    [/\/api\/admin\/themes\/pay\/[a-z0-9_]+\/delete/gi, '支付模板删除接口'],
    [/\/api\/admin\/themes\/home\/[a-z0-9_]+\/activate/gi, '首页模板启用接口'],
    [/\/api\/admin\/themes\/home\/[a-z0-9_]+\/delete/gi, '首页模板删除接口'],
    [/https?:\/\/127\.0\.0\.1:8787\/User\/Index/gi, '商户中心入口页'],
    [/\/User\/Index/gi, '商户中心入口页'],
    [/\bsmokepay_[a-z0-9_]+\b/gi, '支付模板示例'],
    [/\bsmokehome_[a-z0-9_]+\b/gi, '首页模板示例'],
    [/\bsysa_[a-z0-9]+\b/gi, '系统权限账号'],
    [/\bpaya_[a-z0-9]+\b/gi, '支付权限账号'],
    [/\bcta_[a-z0-9]+\b/gi, '内容权限账号'],
    [/\bmenu_[a-z0-9]+\b/gi, '菜单示例账号'],
    [/\brole_[a-z0-9]+\b/gi, '角色示例账号'],
    [/\blog_[a-z0-9]+\b/gi, '日志示例账号'],
    [/\badm_batch_[ab]_[a-z0-9]+\b/gi, '管理员批量示例账号'],
    [/\b(\d+)\s+year\(s\)\b/gi, '$1 年'],
    [/\b(\d+)\s+month\(s\)\b/gi, '$1 个月'],
    [/\b(\d+)\s+day\(s\)\b/gi, '$1 天'],
    [/\bBalance Recharge Card\b/gi, '余额充值卡'],
    [/\bVIP Exchange Card\b/gi, 'VIP 兑换卡'],
    [/\bBalance\s+([0-9]+(?:\.[0-9]+)?)\b/gi, '余额 $1 元'],
    [/\bEnabled\b/gi, '已启用'],
    [/\bDisabled\b/gi, '已停用'],
    [/\bRecycled\b/gi, '回收站'],
    [/\bVIP active\b/gi, '会员有效'],
    [/\bVIP expired\b/gi, '会员已过期'],
    [/\bShop\b/gi, '商城'],
    [/\bCDK\b/gi, '卡券'],
    [/易支付联调上游/gi, '易支付支付通道'],
    [/易支付联调通道/gi, '易支付支付通道'],
    [/\bEPAY\b/gi, '易支付'],
    [/联调接口单笔支付/gi, '接口单笔支付示例'],
    [/联调支付接口提交/gi, '支付接口提交示例'],
    [/联调接口支付/gi, '接口支付示例'],
    [/联调手机接口/gi, '移动端接口示例'],
    [/联调支付提交/gi, '支付提交流程示例'],
    [/联调提交支付/gi, '提交支付示例'],
    [/联调示例邮箱/gi, '示例邮箱'],
    [/联调工单示例/gi, '工单示例'],
    [/联调工单分类/gi, '工单分类示例'],
    [/联调风控示例地址/gi, '风控示例地址'],
    [/联调风控域名/gi, '风控示例域名'],
    [/联调收款账号/gi, '收款账号示例'],
    [/联调支付方式/gi, '支付方式示例'],
    [/通道目录联调/gi, '本地通道示例'],
    [/商户代登联调/gi, '商户代登示例'],
    [/批量删除联调商户/gi, '批量删除示例商户'],
    [/\bUpdated\b/gi, '已更新'],
    [/\bChild B\b/gi, '子节点乙'],
    [/\bChild\b/gi, '子节点'],
    [/菜单联调\s+[a-f0-9]{6,}\s+已更新/gi, '菜单示例已更新'],
    [/菜单联调\s+子节点\s+B\s+[a-f0-9]{6,}/gi, '菜单示例子节点乙'],
    [/菜单联调\s+子节点乙\s+[a-f0-9]{6,}/gi, '菜单示例子节点乙'],
    [/菜单联调\s+子节点\s+[a-f0-9]{6,}/gi, '菜单示例子节点'],
    [/菜单联调\s+[a-f0-9]{6,}/gi, '菜单示例'],
    [/merchant_impersonation_smoke_[a-z0-9_]+/gi, '商户代登示例'],
    [/\blegacy_epay_smoke_[a-z0-9_]+\b/gi, '易支付支付通道'],
    [/\bmerchant_batch_delete_smoke_[a-z0-9_]+\b/gi, '批量删除示例商户'],
    [/\brisk_write_smoke_[a-z0-9_]+\b/gi, '风控示例商户'],
    [/\bsmoke_account_[a-z0-9_]+\b/gi, '收款账号示例'],
    [/domain_(write|audit|delete|recycle)_smoke_[a-z0-9_]+/gi, '域名示例'],
    [/ticket_category_[a-z0-9_]+/gi, '工单分类示例'],
    [/batch payment method a [a-f0-9]+/gi, '批量支付方式A'],
    [/batch payment method b [a-f0-9]+/gi, '批量支付方式B'],
    [/smoke payment method [a-f0-9]+/gi, '支付方式示例'],
    [/channel smoke account [a-z0-9]+/gi, '收款账号示例'],
    [/\bpermission reorder\b/gi, '权限排序更新'],
    [/\bwarning_count\s*=/gi, '风险提示数='],
    [/\btarget\s*=/gi, '跳转地址='],
    [/\bpermission_id\s*=/gi, '权限编号='],
    [/\bparent_id\s*=/gi, '上级编号='],
    [/\bcount\s*=/gi, '数量='],
    [/\bstatus\s*=\s*0\b/gi, '状态值为 0'],
    [/\bstatus\s*=\s*1\b/gi, '状态值为 1'],
    [/\bactive\s*=\s*0\b/gi, '当前未启用'],
    [/\bactive\s*=\s*1\b/gi, '当前已启用'],
    [/\btype\s*=\s*/gi, '类型='],
    [/\bbefore\s*=/gi, '变更前='],
    [/\bafter\s*=/gi, '变更后='],
    [/\btitle_changed\s*=/gi, '名称变更='],
    [/\bparent_changed\s*=/gi, '父级变更='],
    [/\bpath_changed\s*=/gi, '路径变更='],
    [/\btype_changed\s*=/gi, '类型变更='],
    [/\bstatus_changed\s*=/gi, '状态变更='],
    [/\bscope\s*=/gi, '作用域='],
    [/\btheme\s*=/gi, '模板='],
    [/作用域="?pay"?/gi, '作用域="支付页"'],
    [/作用域="?home"?/gi, '作用域="首页"'],
    [/\blabel\s*=/gi, '标签='],
    [/\brelative_path\s*=/gi, '相对路径='],
    [/\bconfig_key\s*=/gi, '配置键='],
    [/\bhome_temp\b/gi, '首页模板配置键'],
    [/\bfrom_theme\s*=/gi, '原模板='],
    [/\bfrom_label\s*=/gi, '原模板名称='],
    [/\bto_label\s*=/gi, '目标模板名称='],
    [/\bto_theme\s*=/gi, '目标模板='],
    [/\bfallback_theme\s*=/gi, '回退模板='],
    [/\bfallback_label\s*=/gi, '回退模板名称='],
    [/\bdelete_permission_rows\s*=/gi, '删除权限记录数='],
    [/\bdelete_role_permission_rows\s*=/gi, '删除角色权限记录数='],
    [/\bdelete_admin_permission_rows\s*=/gi, '删除管理员权限记录数='],
    [/\bdescendants\s*=/gi, '子节点数量='],
    [/\bcascade\s*=/gi, '级联删除='],
    [/\bfiles\s*=/gi, '文件数='],
    [/\bdirectories\s*=/gi, '目录数='],
    [/\breferences\s*=/gi, '引用记录数='],
    [/\bpath\s*=/gi, '路径='],
    [/\bmerchant_id\s*=/gi, '商户编号='],
    [/\buser_id\s*=/gi, '用户编号='],
    [/\bvip_id\s*=/gi, '会员编号='],
    [/\busername\s*=/gi, '账号='],
    [/\bmerchant_username\s*=/gi, '商户账号='],
    [/\bdirection\s*=\s*income\b/gi, '变动方向=增加'],
    [/\bdirection\s*=\s*expense\b/gi, '变动方向=减少'],
    [/\bamount\s*=/gi, '变动金额='],
    [/\bmerchant\s*=/gi, '商户='],
    [/\bnote\s*=/gi, '备注='],
    [/\btag\s*=/gi, '标签='],
    [/商户\s*ID/gi, '商户编号'],
    [/\bID\b/gi, '编号'],
    [/\bLogo\b/gi, '站点标识图'],
    [/\bURL\b/gi, '地址'],
    [/\bHTML\b/gi, '富文本'],
    [/\bTelegram\b/gi, '电报通知'],
    [/\bWxPusher\b/gi, '微信推送'],
    [/\bSMTP\b/gi, '邮件服务器'],
    [/\bOAuth\b/gi, '第三方登录'],
    [/\bVIP\b/gi, '会员'],
    [/\bICP\b/gi, 'ICP'],
    [/\bNo payload captured\b/gi, '未捕获到请求载荷'],
    [/\bdemo_user\b/gi, '演示商户账号'],
    [/\briskwritesmo@ex\.com\b/gi, '示例联系邮箱'],
    [/^[a-z0-9._-]+@ex\.com$/gi, '示例联系邮箱'],
    [/q币/gi, '企鹅币'],
    [/Q币/g, '企鹅币'],
    [/qq货币/gi, '企鹅币'],
    [/QQ货币/g, '企鹅币'],
    [/baidu云/gi, '百度云'],
    [/bd云/gi, '百度云'],
    [/域名回收联调示例/gi, '域名回收示例'],
    [/blocked\.example\.com/gi, '黑名单域名示例'],
    [/create\.example\.com/gi, '白名单域名示例'],
    [/public\/pay\//gi, '支付模板目录/'],
    [/public\/web\/home\//gi, '首页模板目录/'],
    [/\/menu\.[a-z0-9]+\/child-b/gi, '菜单示例子路径乙'],
    [/\/menu\.[a-z0-9]+\/子节点-b/gi, '菜单示例子路径乙'],
    [/\/menu\.[a-z0-9]+\/子节点/gi, '菜单示例子路径'],
    [/\/menu\.[a-z0-9]+\/child/gi, '菜单示例子路径'],
    [/\/menu\.[a-z0-9]+\/index/gi, '菜单示例入口路径'],
    [/支付宝\s+RSA\s+公钥/gi, '支付宝公钥'],
    [/\bindex99\b/gi, '经典支付风格首页'],
    [/\bRSA\s+私钥\b/gi, '站点私钥'],
    [/\bThink\s+验证码密钥\b/gi, '验证码密钥'],
    [/旧版图片目录接口/gi, '系统图片目录接口'],
    [/旧版数据清理页/gi, '数据清理页'],
    [/旧版订单清理接口/gi, '订单清理接口'],
    [/旧版充值清理接口/gi, '充值清理接口'],
    [/旧版管理员日志清理接口/gi, '管理员日志清理接口'],
    [/旧版商户日志清理接口/gi, '商户日志清理接口'],
    [/旧版验证码密钥/gi, '验证码密钥'],
    [/旧版驳回原因/gi, '驳回原因'],
    [/旧版原因/gi, '原因'],
    [/易支付兼容插件/gi, '易支付网关插件'],
    [/\b短信宝\s+api\b/gi, '短信宝接口密钥'],
    [/\[code\]/gi, '【验证码】'],
    [/\[login_uid\]/gi, '【登录编号】'],
    [/\[login_ip\]/gi, '【登录来源】'],
    [/\[login_time\]/gi, '【登录时间】'],
    [/\[account_id\]/gi, '【通道编号】'],
    [/\[account_type\]/gi, '【通道类型】'],
    [/\[account_code\]/gi, '【通道标识】'],
    [/\[lose_time\]/gi, '【掉线时间】'],
    [/\[money\]/gi, '【金额】'],
    [/\[out_trade_no\]/gi, '【商户单号】'],
    [/\[userName\]/g, '【用户名称】'],
    [/\[sitename\]/gi, '【站点名称】'],
    [/\[day\]/gi, '【天数】'],
    [/([0-9]+(?:\.[0-9]+)?)\s*KB\b/gi, '$1 千字节'],
    [/([0-9]+(?:\.[0-9]+)?)\s*MB\b/gi, '$1 兆字节'],
    [/([0-9]+(?:\.[0-9]+)?)\s*GB\b/gi, '$1 吉字节'],
    [/([0-9]+(?:\.[0-9]+)?)\s*TB\b/gi, '$1 太字节'],
    [/([0-9]+(?:\.[0-9]+)?)\s*B\b/gi, '$1 字节']
  ]

  for (const [pattern, replacement] of replacements) {
    normalized = normalized.replace(pattern, replacement)
  }

  normalized = normalized
    .replace(/备案备案/g, 'ICP备案')
    .replace(/遗留\s+富文本/g, '遗留富文本')
    .replace(/现有\s+富文本/g, '现有富文本')
    .replace(/完整图片\s+地址/g, '完整图片地址')
    .replace(/图片\s+地址/g, '图片地址')
    .replace(/站点标识图\s+路径/g, '站点标识图路径')
    .replace(/会员\s+到期/g, '会员到期')
    .replace(/电报通知\s+通知/g, '电报通知')
    .replace(/微信推送\s+通知/g, '微信推送通知')
    .replace(/电报通知\s+绑定提示/g, '电报绑定提示')
    .replace(/电报通知\s+充值通知/g, '电报充值通知')
    .replace(/电报通知\s+注册通知/g, '电报注册通知')
    .replace(/电报通知\s+工单通知/g, '电报工单通知')
    .replace(/电报通知\s+会员通知/g, '电报会员通知')
    .replace(/电报通知\s+会员\s+通知/g, '电报会员通知')
    .replace(/走\s+电报通知\s+通道/g, '走电报通知通道')
    .replace(/启用\s+电报通知\s+投递后/g, '启用电报通知投递后')
    .replace(/使用\s+电报通知前/g, '使用电报通知前')
    .replace(/发送\s+会员\s+通知/g, '发送会员通知')
    .replace(/用于\s+会员到期提醒/g, '用于会员到期提醒')
    .replace(/与\s+会员\s+提醒/g, '与会员提醒')
    .replace(/邮件服务器\s+主机/g, '邮件服务器主机')
    .replace(/邮件服务器\s+密码/g, '邮件服务器密码')
    .replace(/邮件服务器\s+端口/g, '邮件服务器端口')
    .replace(/邮件服务器\s+账号/g, '邮件服务器账号')
    .replace(/邮件服务器\s+加密方式/g, '邮件服务器加密方式')
    .replace(/文件存储\s+文件类型/g, '文件存储类型')
    .replace(/文件存储\s+存储空间名称/g, '文件存储空间名称')
    .replace(/文件存储\s+访问密钥\s+编号/g, '文件存储访问密钥编号')
    .replace(/文件存储\s+访问密钥密文/g, '文件存储访问密钥密文')
    .replace(/文件存储\s+访问节点/g, '文件存储访问节点')
    .replace(/极验\s+验证码/g, '极验验证码')
    .replace(/腾讯云\s+验证码/g, '腾讯云验证码')
    .replace(/极验验证码应用\s+编号/g, '极验验证码应用编号')
    .replace(/腾讯云验证码应用\s+编号/g, '腾讯云验证码应用编号')
    .replace(/阿里云短信\s+登录模板\s+编号/g, '阿里云短信登录模板编号')
    .replace(/阿里云短信\s+注册模板\s+编号/g, '阿里云短信注册模板编号')
    .replace(/阿里云短信\s+访问密钥\s+编号/g, '阿里云短信访问密钥编号')
    .replace(/阿里云短信\s+访问密钥密文/g, '阿里云短信访问密钥密文')
    .replace(/阿里云短信\s+短信签名/g, '阿里云短信签名')
    .replace(/腾讯云短信\s+应用\s+编号/g, '腾讯云短信应用编号')
    .replace(/腾讯云短信\s+登录模板\s+编号/g, '腾讯云短信登录模板编号')
    .replace(/腾讯云短信\s+注册模板\s+编号/g, '腾讯云短信注册模板编号')
    .replace(/腾讯云短信\s+访问密钥\s+编号/g, '腾讯云短信访问密钥编号')
    .replace(/腾讯云短信\s+访问密钥密文/g, '腾讯云短信访问密钥密文')
    .replace(/腾讯云短信\s+短信签名/g, '腾讯云短信签名')
    .replace(/短信宝\s+短信签名/g, '短信宝短信签名')
    .replace(/短信宝\s+密码/g, '短信宝密码')
    .replace(/短信宝\s+账号/g, '短信宝账号')
    .replace(/电报通知\s+管理员\s+编号/g, '电报管理员编号')
    .replace(/电报通知\s+机器人令牌/g, '电报机器人令牌')

  return normalized.replace(/\s{2,}/g, ' ').trim()
}

export function normalizeAdminFixtureText(value: null | number | string | undefined): string {
  const normalized = String(value ?? '').trim()
  if (normalized === '') {
    return ''
  }

  const lower = normalized.toLowerCase()
  if (EXACT_MAP[lower]) {
    return normalizeFixtureFallback(EXACT_MAP[lower])
  }

  const matchedRule = REGEX_RULES.find((rule) => rule.pattern.test(normalized))
  if (matchedRule) {
    return matchedRule.label
  }

  return normalizeFixtureFallback(normalized)
}

export function normalizeAdminFixtureNullable(
  value: null | number | string | undefined
): null | string {
  const normalized = normalizeAdminFixtureText(value)
  return normalized === '' ? null : normalized
}

export function normalizeAdminFixtureUrlPreview(value: null | number | string | undefined): string {
  const normalized = String(value ?? '').trim()
  if (normalized === '') {
    return ''
  }

  if (/mock-upstream/i.test(normalized)) {
    return '本地网关入口'
  }

  if (/^(https?:\/\/)?(127\.0\.0\.1|localhost)(:\d+)?(\/.*)?$/i.test(normalized)) {
    return '本地网关主机'
  }

  if (/risk-[a-z0-9]+\.example\.com/i.test(normalized)) {
    return '风控示例地址'
  }

  if (normalized.toLowerCase().includes('example.test')) {
    return '示例地址'
  }

  if (/example\.com/i.test(normalized) && normalized.toLowerCase().includes('smoke')) {
    return '示例地址'
  }

  return normalizeAdminFixtureText(normalized)
}

export function displayAdminFixtureText(
  value: null | number | string | undefined,
  fallback = '--'
): string {
  return normalizeAdminFixtureText(value) || fallback
}

export function displayAdminFixtureUrl(
  value: null | number | string | undefined,
  fallback = '--'
): string {
  return normalizeAdminFixtureUrlPreview(value) || fallback
}

function looksLikeMaskedFixtureToken(value: string): boolean {
  return (
    /^keep[a-z0-9*_-]+$/i.test(value) ||
    /^smkp[a-z0-9*_-]+$/i.test(value) ||
    /^[a-z0-9._-]*pid[a-z0-9._*-]*$/i.test(value) ||
    (/^[a-z0-9][a-z0-9*_-]{7,}$/i.test(value) && /\*/.test(value))
  )
}

export function displayAdminMaskedPreview(
  value: null | number | string | undefined,
  fallback = '--',
  maskedLabel = '已脱敏'
): string {
  const normalized = String(value ?? '').trim()
  if (normalized === '') {
    return fallback
  }

  const display = normalizeAdminFixtureText(normalized)
  if (display !== normalized) {
    return display || fallback
  }

  if (looksLikeMaskedFixtureToken(normalized)) {
    return maskedLabel
  }

  return display || fallback
}
