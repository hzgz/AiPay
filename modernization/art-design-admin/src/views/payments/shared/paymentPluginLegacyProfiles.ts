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

const accountSource = (field: string): string => `配置字段 ${field}`

const PAYMENT_PLUGIN_LEGACY_PROFILE_MAP: Record<string, PaymentPluginLegacyProfile> = {
  alipay_software: {
    code: 'alipay_software',
    title: '支付宝软件版',
    summary: '填写 PID、二维码模式和二维码图片地址。',
    workspace: 'account',
    fields: [
      { key: 'pid', label: 'PID', required: true, source: accountSource('zfb_pid') },
      {
        key: 'qr_type',
        label: '二维码模式',
        required: true,
        source: accountSource('qr_type'),
        hint: '支持 agt / pic；选择 pic 时再填写二维码图片地址。'
      },
      {
        key: 'qr_url',
        label: '二维码图片地址',
        source: accountSource('qr_url'),
        hint: '仅 pic 模式需要。'
      }
    ]
  },
  wxpay_software: {
    code: 'wxpay_software',
    title: '微信软件版',
    summary: '填写账户标识和二维码内容。',
    workspace: 'account',
    fields: [
      {
        key: 'identifier',
        label: '账户标识',
        required: true,
        source: accountSource('wxname')
      },
      {
        key: 'qr_url',
        label: '二维码内容 / 凭证种子',
        source: accountSource('qr_url'),
        hint: '可填写二维码内容或凭证种子。'
      }
    ]
  },
  qqpay_software: {
    code: 'qqpay_software',
    title: 'QQ 软件版',
    summary: '填写 QQ 账号和二维码内容。',
    workspace: 'account',
    fields: [
      { key: 'qq', label: 'QQ 账号', required: true, source: accountSource('qq') },
      {
        key: 'qr_url',
        label: '二维码内容 / 凭证种子',
        source: accountSource('qr_url')
      }
    ]
  },
  alipay_bill: {
    code: 'alipay_bill',
    title: '支付宝二维码账单插件',
    summary: '填写应用 ID、支付宝公钥、应用私钥和账单二维码内容。',
    workspace: 'account',
    fields: [
      { key: 'app_id', label: '应用 ID', required: true, source: accountSource('wxname') },
      {
        key: 'public_key',
        label: '支付宝公钥',
        required: true,
        secret: true,
        source: accountSource('cookie')
      },
      {
        key: 'private_key',
        label: '应用私钥',
        required: true,
        secret: true,
        source: accountSource('qr_url.private_key')
      },
      {
        key: 'bill_qrcode',
        label: '账单二维码内容',
        required: true,
        source: accountSource('qr_url.qrcode')
      }
    ]
  },
  alipay_mck: {
    code: 'alipay_mck',
    title: '支付宝免CK插件',
    summary: '填写 PID、应用 ID 和公私钥。',
    workspace: 'account',
    fields: [
      { key: 'pid', label: 'PID', required: true, source: accountSource('zfb_pid') },
      { key: 'app_id', label: '应用 ID', required: true, source: accountSource('wxname') },
      {
        key: 'public_key',
        label: '支付宝公钥',
        secret: true,
        source: accountSource('cookie')
      },
      {
        key: 'private_key',
        label: '应用私钥',
        secret: true,
        source: accountSource('qr_url')
      }
    ]
  },
  alipay_official: {
    code: 'alipay_official',
    title: '支付宝官方版V3插件',
    summary: '填写应用 ID、支付宝公钥、应用私钥和支付模式。',
    workspace: 'account',
    fields: [
      { key: 'app_id', label: '应用 ID', required: true, source: accountSource('wxname') },
      {
        key: 'public_key',
        label: '支付宝公钥',
        required: true,
        secret: true,
        source: accountSource('cookie')
      },
      {
        key: 'private_key',
        label: '应用私钥',
        required: true,
        secret: true,
        source: accountSource('qr_url')
      },
      {
        key: 'qr_type',
        label: '支付模式',
        source: accountSource('qr_type'),
        hint: '按上游接口要求填写模式值。'
      }
    ]
  },
  wxpay_v3: {
    code: 'wxpay_v3',
    title: '微信支付 V3 插件',
    summary: '填写商户号、应用 ID、平台公钥、商户私钥、API V3 密钥和证书序列号。',
    workspace: 'account',
    fields: [
      {
        key: 'merchant_id',
        label: '商户号',
        required: true,
        source: accountSource('zfb_pid')
      },
      { key: 'app_id', label: '应用 ID', required: true, source: accountSource('wxname') },
      {
        key: 'platform_public_key',
        label: '平台公钥',
        required: true,
        secret: true,
        source: accountSource('cookie')
      },
      {
        key: 'merchant_private_key',
        label: '商户私钥',
        required: true,
        secret: true,
        source: accountSource('qr_url')
      },
      {
        key: 'api_v3_key',
        label: 'API V3 密钥',
        required: true,
        secret: true,
        source: accountSource('remark')
      },
      {
        key: 'cert_serial',
        label: '证书序列号',
        required: true,
        source: accountSource('wx_guid')
      },
      {
        key: 'qr_type',
        label: '微信支付模式',
        source: accountSource('qr_type'),
        hint: '按接入文档填写对应模式值。'
      }
    ]
  },
  universal_epay: {
    code: 'universal_epay',
    title: '通用易支付V1插件',
    summary: '填写上游商户 ID、接口地址、商户密钥和接口模式，可用于支付宝、微信、QQ 支付。',
    workspace: 'account',
    fields: [
      {
        key: 'merchant_id',
        label: '商户 ID',
        required: true,
        source: accountSource('wxname')
      },
      {
        key: 'gateway_url',
        label: '接口地址',
        required: true,
        source: accountSource('qr_url'),
        hint: '必须填写完整的 http:// 或 https:// 接口根地址。'
      },
      {
        key: 'merchant_key',
        label: '商户密钥',
        required: true,
        secret: true,
        source: accountSource('cookie')
      },
      {
        key: 'mode',
        label: '接口模式',
        source: accountSource('qr_type'),
        hint: '0 为普通接口，1 为 MAPI 接口。'
      }
    ]
  },
  jiaofeiyi_alipay: {
    code: 'jiaofeiyi_alipay',
    title: '缴费易支付宝插件',
    summary: '填写商户 ID、商户号、店铺名、远程 API、收款备注和指定 IP。',
    workspace: 'account',
    fields: [
      {
        key: 'merchant_id',
        label: '商户 ID',
        required: true,
        source: accountSource('zfb_pid')
      },
      {
        key: 'merchant_no',
        label: '商户号',
        required: true,
        source: accountSource('wxname')
      },
      { key: 'store_name', label: '店铺名', source: accountSource('cookie.store_name') },
      {
        key: 'remote_api_url',
        label: '远程 API',
        source: accountSource('cookie.remote_api_url / extra_value'),
        hint: '填写时必须是 http:// 或 https:// 地址。'
      },
      { key: 'payment_note', label: '收款备注', source: accountSource('qr_url') },
      { key: 'fixed_ip', label: '指定 IP', source: accountSource('remark') }
    ]
  },
  jiaofeiyi_wxpay: {
    code: 'jiaofeiyi_wxpay',
    title: '缴费易微信插件',
    summary: '填写商户 ID、商户号、微信支付模式、店铺名、远程 API、收款备注和指定 IP。',
    workspace: 'account',
    fields: [
      {
        key: 'merchant_id',
        label: '商户 ID',
        required: true,
        source: accountSource('zfb_pid')
      },
      {
        key: 'merchant_no',
        label: '商户号',
        required: true,
        source: accountSource('wxname')
      },
      { key: 'store_name', label: '店铺名', source: accountSource('cookie.store_name') },
      {
        key: 'remote_api_url',
        label: '远程 API',
        source: accountSource('cookie.remote_api_url / extra_value'),
        hint: '填写时必须是 http:// 或 https:// 地址。'
      },
      { key: 'payment_note', label: '收款备注', source: accountSource('qr_url') },
      { key: 'fixed_ip', label: '指定 IP', source: accountSource('remark') },
      {
        key: 'qr_type',
        label: '微信支付模式',
        source: accountSource('qr_type'),
        hint: '支持 H5、二维码支付、二维码链接等模式。'
      }
    ]
  },
  usdt: {
    code: 'usdt',
    title: 'USDT',
    summary: '填写钱包地址，需要时补充 Memo。',
    workspace: 'account',
    fields: [
      {
        key: 'wallet_address',
        label: '钱包地址',
        required: true,
        source: accountSource('wxname')
      },
      { key: 'memo', label: 'Memo / 附加参数', source: accountSource('qr_url') }
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
