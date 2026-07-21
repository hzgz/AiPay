const EXACT_MAP: Record<string, string> = {
  'normal merchant': '普通商户',
  'vip merchant': '会员商户',
  'no active vip': '暂无有效会员',
  'current merchant package': '当前正在使用的套餐',
  'vip active': '会员有效',
  'vip expired': '会员已过期',
  'new order notification': '新订单通知',
  'channel offline alert': '通道掉线提醒',
  'account login alert': '账号登录提醒',
  'low balance notification': '余额不足提醒',
  closed: '关闭',
  disabled: '已停用',
  enabled: '已启用',
  available: '可用',
  configured: '已配置',
  'not configured': '未配置',
  bound: '已绑定',
  'not bound': '未绑定',
  verified: '已认证',
  'not verified': '未认证',
  'pending verification': '待认证',
  pending: '待处理',
  processing: '处理中',
  resolved: '已解决',
  paid: '已支付',
  expense: '支出',
  income: '收入',
  email: '邮箱',
  mobile: '手机',
  'qq login': 'QQ 快捷登录',
  'wechat login': '微信快捷登录',
  telegram: '电报通知',
  wxpusher: '微信推送',
  'telegram chat id': '电报会话标识',
  'wxpusher uid': '微信推送标识',
  appkey: '通讯密钥',
  google: '谷歌验证器',
  alipay: '支付宝',
  'wechat pay': '微信支付',
  wechat: '微信',
  'qq wallet': 'QQ 钱包',
  'qq pay': 'QQ 支付',
  '网站bug': '网站问题',
  '缃戠珯bug': '网站问题',
  usdt: 'USDT',
  'line 1': '线路 1',
  'line 2': '线路 2',
  'line 3': '线路 3',
  'fee deduction': '手续费扣除',
  'balance recharge': '余额充值',
  'balance deduction': '余额扣减',
  'settlement change': '结算变动',
  'balance decrease': '余额减少',
  'balance increase': '余额增加',
  'no memo': '暂无备注',
  'login event': '登录事件',
  'security event': '安全事件',
  'merchant action': '商户行为',
  'behavior log': '行为日志',
  'direct channel save is live in webman. email and mobile verification-code flows still stay on the migration guard.':
    '当前可直接保存通知通道设置，邮箱和手机号变更仍需通过验证码校验。',
  'email and mobile bind flows still stay on the migration guard until the verification-code service is migrated.':
    '邮箱和手机号绑定请通过验证码完成验证。',
  'merchant google auth verify, bind, and unbind flows are not migrated for the webman merchant center yet':
    '当前页面展示谷歌验证状态，请在安全中心完成校验、绑定或解绑操作。',
  'merchant google auth bind is live in webman. login-time verification still follows the migration guard.':
    '谷歌验证器已支持在商户后台绑定；如登录启用安全校验，仍需按要求完成验证。',
  'merchant google auth unbind is live in webman. login-time verification still follows the migration guard.':
    '谷歌验证器已支持在商户后台解绑；如登录启用安全校验，仍需按要求完成验证。',
  'google auth qr code generated successfully': '谷歌验证二维码已生成',
  'merchant google auth bound successfully': '谷歌验证器绑定成功',
  'merchant google auth unbound successfully': '谷歌验证器解绑成功',
  'google verification code is required': '请输入 6 位谷歌验证码',
  'google verification code is invalid': '谷歌验证码不正确',
  'please request a fresh google auth qr code first': '请先重新获取谷歌绑定二维码',
  'current merchant account already has google auth bound': '当前商户账号已绑定谷歌验证器',
  'current merchant account has not bound google auth yet': '当前商户账号尚未绑定谷歌验证器',
  'merchant password change is live in the webman merchant center and will require a fresh login after save':
    '密码修改已接入商户中心，保存后需要重新登录。',
  'merchant ticket create and delete are enabled on webman': '工单创建与删除已接入商户后台。',
  'merchant domain create, edit, and delete are enabled on webman':
    '域名新增、编辑和删除已接入商户后台。',
  'merchant login-log delete and cleanup flows are not exposed in the webman merchant center':
    '登录日志当前支持查看与检索，删除和批量清理不在此页处理。',
  'merchant affiliate page is read-only in the webman merchant center':
    '当前页面支持查看返佣统计与复制邀请链接，返佣提现与邀请链接重置暂未在此页开放。',
  'merchant real-name verification finalization is not migrated for the webman merchant center yet':
    '实名认证结果会根据上游返回自动更新，请继续轮询或稍后刷新页面。',
  'merchant real-name verification initiation is not migrated for the webman merchant center yet':
    '管理员尚未配置可用的实名认证通道。',
  'merchant real-name verification is live in webman merchant center':
    '实名认证提交流程已接入商户中心，可直接在当前页面发起并轮询。',
  'merchant real-name verification is already completed': '当前商户已完成实名认证，无需重复提交。',
  'merchant real-name verification started successfully': '实名认证已发起，请扫码继续完成认证。',
  'merchant real-name verification completed successfully': '实名认证已完成。',
  'merchant real-name verification is pending': '实名认证处理中，请稍后刷新或继续轮询。',
  'merchant real-name verification request failed': '实名认证发起失败，请稍后重试。',
  'merchant real-name verification status query failed': '实名认证状态查询失败，请稍后重试。',
  'real-name full name is required': '请输入真实姓名',
  'real-name id card is required': '请输入身份证号',
  'real-name id card format is invalid': '身份证号格式不正确',
  'real-name verification channel is invalid': '实名认证通道无效',
  'real-name provider app code is not configured': '实名认证服务应用编码未配置',
  'real-name alipay app credentials are not configured': '支付宝实名认证应用配置不完整',
  'merchant real-name verification fee balance is insufficient': '余额不足，无法承担实名认证费用。',
  'alipay real-name authorization failed': '支付宝实名认证授权失败',
  'merchant identity information is incomplete': '实名认证资料不完整，请重新填写后再试。',
  'vip purchase is not migrated for webman merchant center yet':
    '当前会员页仅提供套餐与权益查看，购买和续费暂未开放。',
  'vip package is required': '请选择会员套餐',
  'vip package not found': '会员套餐不存在或已下架',
  'merchant balance is insufficient for vip purchase': '余额不足，请先充值。',
  'merchant vip purchase completed successfully': '会员套餐购买成功',
  'current package can be renewed directly in webman merchant center': '当前套餐可直接在商户中心续费。',
  'vip package purchase is enabled in webman merchant center': '会员套餐购买已开放。',
  'legacy epay compatible browser form entry.': '网页下单入口。',
  'legacy epay compatible json/api entry.': '接口下单入口。',
  'upstream payment notify callback entry.': '支付异步回调地址。',
  'upstream payment return callback entry.': '支付同步跳转地址。',
  'use the configured merchant sign key for payment request signing. raw values remain hidden during migration.':
    '请使用当前商户密钥完成请求签名，密钥默认脱敏显示。',
  'merchant sign key reset successfully': '商户签名密钥已重置',
  'merchant api key reset successfully': '商户接口密钥已重置',
  'merchant appkey reset successfully': '商户通讯密钥已重置',
  'merchant notification settings saved successfully': '通知设置已保存',
  'merchant password updated successfully, please sign in again': '密码已修改，请重新登录。',
  'merchant profile updated successfully': '商户资料已更新',
  'wxpusher uid saved successfully': '微信推送标识已保存',
  'telegram chat id saved successfully': '电报会话标识已保存',
  'merchant ticket created successfully': '工单创建成功',
  'merchant ticket deleted successfully': '工单删除成功',
  'merchant domain created successfully': '域名已提交',
  'merchant domain updated successfully': '域名已更新',
  'merchant domain deleted successfully': '域名已删除',
  'merchant affiliate feature is disabled': '推广返佣功能未开启',
  'merchant real-name feature is disabled': '实名认证功能未开启',
  'merchant ticket feature is disabled': '工单功能未开启',
  'merchant domain feature is disabled': '域名功能未开启',
  'merchant is frozen': '商户账户已冻结',
  'merchant login is required': '请先登录商户账号',
  'username and password are required': '请输入账号和密码',
  'username or password is incorrect': '账号或密码错误',
  'captcha verification is not migrated for webman merchant login yet':
    '当前登录无需图形验证码。',
  'only username/password merchant login is migrated in webman': '当前仅支持账号密码登录。',
  'google verification is required before webman direct merchant login can continue':
    '当前账户需要先完成谷歌验证后才能继续登录。',
  'use order callback host': '使用订单回调域名',
  'use configured timeout_url': '使用已配置的超时跳转地址',
  'face verification via wechat or alipay': '通过微信或支付宝进行人脸核验',
  'alipay identity authorization': '支付宝身份授权',
  'quick-login unbind is live in webman. fresh oauth bind flows still follow the legacy route during migration.':
    '当前仅支持解绑已绑定的快捷登录，新授权入口暂未开放。',
  'unavailable because the global recharge mapping or upstream paylist is missing.':
    '当前未配置全局充值映射或可用支付通道。',
  'merchant recharge creation and payment handoff are not migrated for webman merchant center yet':
    '当前仅可查看充值记录，创建充值与支付跳转暂未开放。',
  'merchant connection bind-code, qr enrollment, and email/mobile verification flows are not migrated for webman merchant center yet':
    '绑定中心仅展示当前接入状态，绑定、解绑与验证码操作暂未开放。',
  'merchant order callback replay and status reset flows are not migrated for webman merchant center yet':
    '当前订单页已支持回调重放，状态重置暂未开放。',
  'merchant cdk recharge feature is disabled': '卡密充值功能未开启',
  'merchant cdk redemption is not migrated for webman merchant center yet':
    '当前充值页暂未开放卡密兑换。',
  'recharge amount is required': '请输入充值金额',
  'recharge method is invalid': '充值方式无效',
  'selected recharge method is not available': '所选充值方式当前未接入可用通道',
  '会员排序联调': '会员套餐',
  '卡券联调会员': '卡券会员套餐'
}

const REGEX_RULES: Array<[RegExp, string]> = [
  [/\bWebman\b/gi, '商户后台'],
  [/\bOAuth\b/gi, '授权'],
  [/\bTelegram\b/gi, '电报'],
  [/\bpayment gateway upstream\b/gi, '支付通道'],
  [/\bupstream channel\b/gi, '支付通道'],
  [/\blocal channel\b/gi, '本地通道'],
  [/\buniversal_epay_smoke_[a-z0-9_]+\b/gi, '商户账号已脱敏'],
  [/\bmerchant_batch_delete_smoke_[a-z0-9_]+\b/gi, '商户账号已脱敏'],
  [/\bsmoke_account_[a-z0-9_]+\b/gi, '收款账号已脱敏'],
  [/\bmerchant_impersonation_smoke_[a-z0-9_]+\b/gi, '商户代登录账号已脱敏'],
  [/\bmerchant #(\d+)\b/gi, '商户 #$1'],
  [/\bvip #(\d+)\b/gi, '会员 #$1'],
  [/\bcategory #(\d+)\b/gi, '分类 #$1'],
  [/\badmin #(\d+)\b/gi, '管理员 #$1'],
  [/\bticket #(\d+)\b/gi, '工单 #$1'],
  [/\btype (\d+)\b/gi, '类型 $1'],
  [/\b31 day(s)?\b/gi, '31 天'],
  [/\b1 month(s)?\b/gi, '1 个月'],
  [/\b(\d+) month(s)?\b/gi, '$1 个月'],
  [/\b(\d+) day(s)?\b/gi, '$1 天']
]

const IDENTITY_FIXTURE_RULES: Array<[RegExp, string]> = [
  [/^art_merchant_demo$/i, '商户账号已脱敏'],
  [/^universal_epay_smoke_[a-z0-9_]+$/i, '商户账号已脱敏'],
  [/^merchant_batch_delete_smoke_[a-z0-9_]+$/i, '商户账号已脱敏'],
  [/^smoke_account_[a-z0-9_]+$/i, '收款账号已脱敏'],
  [/^merchant_impersonation_smoke_[a-z0-9_]+$/i, '商户代登录账号已脱敏'],
  [/^smoke_[a-z0-9_]+$/i, '账号已脱敏'],
  [/^legacy_[a-z0-9_]+$/i, '系统账号'],
  [/^[a-f0-9]{20,}$/i, '系统账号']
]

const RECORD_FIXTURE_RULES: Array<[RegExp, string]> = [
  [/^les_[a-z0-9_]+$/i, '商户订单号已脱敏'],
  [/^recharge_[a-z0-9_]+$/i, '充值单号已脱敏'],
  [/^smoke_[a-z0-9_]+$/i, '记录编号已脱敏']
]

const MERCHANT_TEXT_HINT =
  /(普通|会员|微信|支付|商户|账号|登录|回调|通知|工单|充值|验证|谷歌|域名|实名|推广|脱敏|系统)/

function tryDecodeLatin1Utf8(raw: string) {
  if (MERCHANT_TEXT_HINT.test(raw)) {
    return raw
  }

  try {
    const bytes = Uint8Array.from(raw, (char) => char.charCodeAt(0) & 0xff)
    const decoded = new TextDecoder('utf-8', { fatal: true }).decode(bytes)
    return MERCHANT_TEXT_HINT.test(decoded) ? decoded : raw
  } catch {
    return raw
  }
}

function normalizeMerchantText(value: string) {
  return tryDecodeLatin1Utf8(value).trim()
}

function cleanupMerchantVisibleWords(value: string) {
  return value
    .replace(/演示联系邮箱/g, '脱敏联系邮箱')
    .replace(/示例联系邮箱/g, '脱敏联系邮箱')
    .replace(/示例邮箱/g, '脱敏邮箱')
    .replace(/风控示例地址/g, '风控地址已脱敏')
    .replace(/风控示例域名/g, '风控域名已脱敏')
    .replace(/示例地址/g, '脱敏地址')
    .replace(/测试商户账号/g, '商户账号已脱敏')
    .replace(/测试收款账号/g, '收款账号已脱敏')
    .replace(/商户代登测试账号/g, '商户代登录账号已脱敏')
    .replace(/测试账号标识/g, '账号标识已脱敏')
    .replace(/会员套餐测试/g, '会员套餐')
    .replace(/支付方式测试/g, '支付方式')
    .replace(/风控测试地址/g, '风控地址已脱敏')
    .replace(/风控测试域名/g, '风控域名已脱敏')
    .replace(/旧版/g, '')
    .replace(/联调/g, '系统')
    .replace(/\s{2,}/g, ' ')
    .trim()
}

function translateKnownMerchantText(raw: string) {
  const mapped = EXACT_MAP[raw.toLowerCase()]
  if (mapped) {
    return cleanupMerchantVisibleWords(mapped)
  }

  if (raw.toLowerCase().includes('timeout_url')) {
    return '使用已配置的超时跳转地址'
  }

  let result = raw
  for (const [pattern, replacement] of REGEX_RULES) {
    result = result.replace(pattern, replacement)
  }

  return cleanupMerchantVisibleWords(result)
}

function matchMerchantIdentityFixture(raw: string) {
  for (const [pattern, label] of IDENTITY_FIXTURE_RULES) {
    if (pattern.test(raw)) {
      return cleanupMerchantVisibleWords(label)
    }
  }

  if (
    /^[a-z0-9_.:-]+$/i.test(raw) &&
    raw.length >= 18 &&
    (/_/.test(raw) || /-/.test(raw) || /\d{6,}/.test(raw))
  ) {
    return '系统账号'
  }

  return ''
}

function matchMerchantRecordFixture(raw: string) {
  for (const [pattern, label] of RECORD_FIXTURE_RULES) {
    if (pattern.test(raw)) {
      return cleanupMerchantVisibleWords(label)
    }
  }

  return ''
}

export function translateMerchantText(value: null | number | string | undefined, fallback = '--') {
  const raw = normalizeMerchantText(String(value ?? ''))
  if (raw === '') {
    return fallback
  }

  const translated = translateKnownMerchantText(raw)
  if (translated !== raw) {
    return translated || fallback
  }

  const fixtureLabel = matchMerchantIdentityFixture(raw)
  if (fixtureLabel) {
    return fixtureLabel
  }

  return translated || fallback
}

export function translateMerchantNullable(value: null | number | string | undefined) {
  const translated = translateMerchantText(value, '')
  return translated === '' ? null : translated
}

export function isMerchantMachineAccount(value: null | number | string | undefined) {
  const raw = normalizeMerchantText(String(value ?? ''))
  if (raw === '') {
    return false
  }

  return matchMerchantIdentityFixture(raw) !== ''
}

function truncateMerchantAccount(value: string) {
  if (value.length <= 20) {
    return value
  }

  return `${value.slice(0, 8)}...${value.slice(-6)}`
}

export function formatMerchantIdentity(
  value: null | number | string | undefined,
  options: {
    merchantId?: number
    fallback?: string
    defaultLabel?: string
  } = {}
) {
  const { merchantId = 0, fallback = '--', defaultLabel = '商户账户' } = options
  const raw = normalizeMerchantText(String(value ?? ''))

  if (raw === '') {
    return fallback
  }

  const fixtureLabel = matchMerchantIdentityFixture(raw)
  if (fixtureLabel) {
    return merchantId > 0 ? `商户 #${merchantId}` : defaultLabel || fixtureLabel
  }

  const translated = translateKnownMerchantText(raw)
  if (translated !== raw) {
    return translated || fallback
  }

  return raw
}

export function formatMerchantDisplayName(
  displayName: null | number | string | undefined,
  username: null | number | string | undefined,
  merchantId = 0,
  fallback = '商户账户'
) {
  const rawDisplayName = normalizeMerchantText(String(displayName ?? ''))
  const rawUsername = normalizeMerchantText(String(username ?? ''))

  if (rawDisplayName !== '' && rawDisplayName !== rawUsername) {
    return formatMerchantIdentity(rawDisplayName, {
      merchantId,
      fallback,
      defaultLabel: fallback
    })
  }

  const usernameLabel = formatMerchantIdentity(rawUsername, {
    merchantId,
    fallback: '',
    defaultLabel: ''
  })
  if (usernameLabel !== '') {
    return usernameLabel
  }

  const displayLabel = formatMerchantIdentity(rawDisplayName, {
    merchantId,
    fallback: '',
    defaultLabel: ''
  })
  if (displayLabel !== '') {
    return displayLabel
  }

  return merchantId > 0 ? `商户 #${merchantId}` : fallback
}

export function formatMerchantAccountHint(
  username: null | number | string | undefined,
  merchantId = 0
) {
  const raw = normalizeMerchantText(String(username ?? ''))
  if (raw === '') {
    return merchantId > 0 ? `商户编号：${merchantId}` : '当前商户'
  }

  if (isMerchantMachineAccount(raw)) {
    return merchantId > 0 ? `商户编号：${merchantId}` : '当前商户'
  }

  const visible = formatMerchantIdentity(raw, {
    merchantId,
    fallback: '',
    defaultLabel: '商户账户'
  })

  return `登录账号：${truncateMerchantAccount(visible)}`
}

export function formatMerchantContactValue(
  value: null | number | string | undefined,
  options: {
    emptyLabel?: string
    fallbackLabel?: string
    maskMode?: 'email' | 'mobile' | 'plain'
  } = {}
) {
  const { emptyLabel = '未填写', fallbackLabel = '已填写', maskMode = 'plain' } = options
  const raw = normalizeMerchantText(String(value ?? ''))
  if (raw === '') {
    return emptyLabel
  }

  const translated = translateKnownMerchantText(raw)
  if (translated !== raw) {
    return translated || emptyLabel
  }

  if (matchMerchantIdentityFixture(raw)) {
    return fallbackLabel
  }

  if (maskMode === 'email') {
    const [localPart, domainPart = ''] = raw.split('@')
    if (localPart && domainPart) {
      if (/example\.com$/i.test(domainPart)) {
        return '脱敏邮箱'
      }
      const safeLocal =
        localPart.length <= 2 ? `${localPart[0] || '*'}*` : `${localPart.slice(0, 2)}***`
      const safeDomain = domainPart.replace(/^([a-z0-9]{1,3})[a-z0-9.-]*\./i, '$1***.')
      return `${safeLocal}@${safeDomain}`
    }
    return fallbackLabel
  }

  if (maskMode === 'mobile') {
    const digits = raw.replace(/\D/g, '')
    if (digits.length >= 7) {
      return `${digits.slice(0, 3)}****${digits.slice(-4)}`
    }
  }

  return raw
}

export function formatMerchantRecordCode(
  value: null | number | string | undefined,
  fallback = '--'
) {
  const raw = normalizeMerchantText(String(value ?? ''))
  if (raw === '') {
    return fallback
  }

  const translated = translateKnownMerchantText(raw)
  if (translated !== raw) {
    return translated || fallback
  }

  return matchMerchantRecordFixture(raw) || raw
}

export function merchantBooleanLabel(value: unknown, labels: [string, string] = ['是', '否']) {
  return value ? labels[0] : labels[1]
}

export function merchantEnabledLabel(value: unknown) {
  return merchantBooleanLabel(value, ['已启用', '已关闭'])
}
