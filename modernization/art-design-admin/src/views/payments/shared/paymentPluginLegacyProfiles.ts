export interface PaymentPluginLegacyField {
  key: string
  label: string
  required?: boolean
  secret?: boolean
  source?: string
  hint?: string
}

export interface PaymentPluginLegacyProfile {
  code: string
  title: string
  summary: string
  workspace: 'account' | 'merchant-channel' | 'none'
  fields: PaymentPluginLegacyField[]
}

const PAYMENT_PLUGIN_LEGACY_PROFILE_MAP: Record<string, PaymentPluginLegacyProfile> = {
  alipay_software: {
    code: 'alipay_software',
    title: '支付宝软件版',
    summary: '软件版账户核心维护 PID、二维码模式与图片模式二维码地址。',
    workspace: 'account',
    fields: [
      { key: 'pid', label: 'PID', required: true, source: 'ypay_account.zfb_pid' },
      {
        key: 'qr_type',
        label: '二维码模式',
        required: true,
        source: 'ypay_account.qr_type',
        hint: '支持 agt / pic；选择 pic 时需要再填写二维码图片地址。'
      },
      {
        key: 'qr_url',
        label: '二维码图片地址',
        source: 'ypay_account.qr_url',
        hint: '仅在 pic 模式下必填。'
      }
    ]
  },
  wxpay_software: {
    code: 'wxpay_software',
    title: '微信软件版',
    summary: '软件版主账号核心维护账户标识与二维码内容字段。',
    workspace: 'account',
    fields: [
      { key: 'identifier', label: '账户标识', required: true, source: 'ypay_account.wxname' },
      {
        key: 'qr_url',
        label: '二维码内容 / 凭证种子',
        source: 'ypay_account.qr_url',
        hint: '用于维护软件版二维码内容，必要时也可填写凭证种子。'
      }
    ]
  },
  qqpay_software: {
    code: 'qqpay_software',
    title: 'QQ 软件版',
    summary: 'QQ 软件版核心维护主账号字段与二维码内容字段。',
    workspace: 'account',
    fields: [
      { key: 'qq', label: 'QQ 账号', required: true, source: 'ypay_account.qq' },
      {
        key: 'qr_url',
        label: '二维码内容 / 凭证种子',
        source: 'ypay_account.qr_url'
      }
    ]
  },
  alipay_bill: {
    code: 'alipay_bill',
    title: '支付宝二维码账单插件',
    summary: '账单二维码账户需要应用 ID、公钥、私钥和账单二维码内容，当前统一在收款账号维护。',
    workspace: 'account',
    fields: [
      { key: 'app_id', label: '应用 ID', required: true, source: 'ypay_account.wxname' },
      { key: 'public_key', label: '支付宝公钥', required: true, secret: true, source: 'ypay_account.cookie' },
      {
        key: 'private_key',
        label: '应用私钥',
        required: true,
        secret: true,
        source: 'ypay_account.qr_url.private_key'
      },
      {
        key: 'bill_qrcode',
        label: '账单二维码内容',
        required: true,
        source: 'ypay_account.qr_url.qrcode'
      }
    ]
  },
  alipay_mck: {
    code: 'alipay_mck',
    title: '支付宝免CK插件',
    summary: 'MCK 账户核心维护 PID、应用 ID 与公私钥字段。',
    workspace: 'account',
    fields: [
      { key: 'pid', label: 'PID', required: true, source: 'ypay_account.zfb_pid' },
      { key: 'app_id', label: '应用 ID', required: true, source: 'ypay_account.wxname' },
      { key: 'public_key', label: '支付宝公钥', secret: true, source: 'ypay_account.cookie' },
      { key: 'private_key', label: '应用私钥', secret: true, source: 'ypay_account.qr_url' }
    ]
  },
  alipay_official: {
    code: 'alipay_official',
    title: '支付宝官方版V3插件',
    summary: '官方版账户要求应用 ID、公钥、私钥，并支持多种支付模式。',
    workspace: 'account',
    fields: [
      { key: 'app_id', label: '应用 ID', required: true, source: 'ypay_account.wxname' },
      { key: 'public_key', label: '支付宝公钥', required: true, secret: true, source: 'ypay_account.cookie' },
      { key: 'private_key', label: '应用私钥', required: true, secret: true, source: 'ypay_account.qr_url' },
      {
        key: 'qr_type',
        label: '支付模式',
        source: 'ypay_account.qr_type',
        hint: '支持 1、2、3、4、6、7、8 等多种支付模式。'
      }
    ]
  },
  wxpay_v3: {
    code: 'wxpay_v3',
    title: '微信支付 V3',
    summary: '微信 V3 账户包含商户号、应用 ID、平台公钥、商户私钥、API V3 密钥与证书序列号。',
    workspace: 'account',
    fields: [
      { key: 'merchant_id', label: '商户号', required: true, source: 'ypay_account.zfb_pid' },
      { key: 'app_id', label: '应用 ID', required: true, source: 'ypay_account.wxname' },
      {
        key: 'platform_public_key',
        label: '平台公钥',
        required: true,
        secret: true,
        source: 'ypay_account.cookie'
      },
      {
        key: 'merchant_private_key',
        label: '商户私钥',
        required: true,
        secret: true,
        source: 'ypay_account.qr_url'
      },
      { key: 'api_v3_key', label: 'API V3 密钥', required: true, secret: true, source: 'ypay_account.remark' },
      {
        key: 'cert_serial',
        label: '证书序列号',
        required: true,
        source: 'ypay_account.wx_guid'
      },
      {
        key: 'qr_type',
        label: '支付模式',
        source: 'ypay_account.qr_type',
        hint: '支持 1、2、3、5 等支付模式。'
      }
    ]
  },
  jiaofeiyi_alipay: {
    code: 'jiaofeiyi_alipay',
    title: '缴费易支付宝',
    summary: '缴费易账户维护商户 ID、商户号、店铺名与远程 API 地址。',
    workspace: 'account',
    fields: [
      { key: 'merchant_id', label: '商户 ID', required: true, source: 'ypay_account.zfb_pid' },
      { key: 'merchant_no', label: '商户号', required: true, source: 'ypay_account.wxname' },
      { key: 'store_name', label: '店铺名称', source: 'ypay_account.cookie.store_name' },
      {
        key: 'remote_api_url',
        label: '远程 API 地址',
        source: 'ypay_account.cookie.remote_api_url / extra_value',
        hint: '填写时必须是 http:// 或 https:// 地址。'
      },
      { key: 'payment_note', label: '收款备注', source: 'ypay_account.qr_url' },
      { key: 'fixed_ip', label: '指定 IP', source: 'ypay_account.remark' }
    ]
  },
  jiaofeiyi_wxpay: {
    code: 'jiaofeiyi_wxpay',
    title: '缴费易微信',
    summary: '缴费易微信账户在支付宝版本基础上，还包含支付模式字段。',
    workspace: 'account',
    fields: [
      { key: 'merchant_id', label: '商户 ID', required: true, source: 'ypay_account.zfb_pid' },
      { key: 'merchant_no', label: '商户号', required: true, source: 'ypay_account.wxname' },
      { key: 'store_name', label: '店铺名称', source: 'ypay_account.cookie.store_name' },
      {
        key: 'remote_api_url',
        label: '远程 API 地址',
        source: 'ypay_account.cookie.remote_api_url / extra_value',
        hint: '填写时必须是 http:// 或 https:// 地址。'
      },
      { key: 'payment_note', label: '收款备注', source: 'ypay_account.qr_url' },
      { key: 'fixed_ip', label: '指定 IP', source: 'ypay_account.remark' },
      {
        key: 'qr_type',
        label: '支付模式',
        source: 'ypay_account.qr_type',
        hint: '支持 H5、二维码支付、二维码链接。'
      }
    ]
  },
  usdt: {
    code: 'usdt',
    title: 'USDT',
    summary: 'USDT 通道主要维护钱包地址，必要时补充 Memo 或链路参数。',
    workspace: 'account',
    fields: [
      { key: 'wallet_address', label: '钱包地址', required: true, source: 'ypay_account.wxname' },
      { key: 'memo', label: 'Memo / 扩展参数', source: 'ypay_account.qr_url' }
    ]
  },
  legacy_epay: {
    code: 'legacy_epay',
    title: '易支付网关',
    summary: '该插件用于第三方网关式通道，主要在商户通道新增时填写网关、商户号与密钥。',
    workspace: 'merchant-channel',
    fields: [
      { key: 'gateway_url', label: '网关地址', required: true, source: 'merchant_channel.url' },
      { key: 'pid', label: '商户号 / PID', required: true, source: 'merchant_channel.pid' },
      { key: 'key', label: '签名密钥', required: true, secret: true, source: 'merchant_channel.key' }
    ]
  }
}

export function getPaymentPluginLegacyProfile(
  code: null | string | undefined
): PaymentPluginLegacyProfile | null {
  const normalized = String(code || '')
    .trim()
    .toLowerCase()

  if (!normalized) {
    return null
  }

  return PAYMENT_PLUGIN_LEGACY_PROFILE_MAP[normalized] || null
}
