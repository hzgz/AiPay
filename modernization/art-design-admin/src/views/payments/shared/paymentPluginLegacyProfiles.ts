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
    summary: '填写上游标识、二维码模式，以及图片模式下的二维码内容。',
    workspace: 'account',
    fields: [
      {
        key: 'identifier',
        label: '上游标识',
        source: accountSource('zfb_pid'),
        hint: '转账模式必填，图片模式下不需要。'
      },
      {
        key: 'qr_type',
        label: '二维码模式',
        required: true,
        source: accountSource('qr_type'),
        hint: '支持转账模式 / 图片模式；选择图片模式后可上传二维码图片自动解析。'
      },
      {
        key: 'qr_url',
        label: '二维码内容',
        source: accountSource('qr_url'),
        hint: '仅图片模式需要，可直接粘贴内容或上传图片自动解析。'
      }
    ]
  },
  wxpay_software: {
    code: 'wxpay_software',
    title: '微信软件版',
    summary: '支持账户标识二维码和赞赏码图片。',
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
  leshua: {
    code: 'leshua',
    title: '乐刷支付插件',
    summary: '填写商户号、交易密钥和可选的异步通知密钥，支付宝与微信共用同一套乐刷上游账户字段。',
    workspace: 'account',
    fields: [
      {
        key: 'merchant_id',
        label: '商户号',
        required: true,
        source: accountSource('wxname')
      },
      {
        key: 'transaction_key',
        label: '交易密钥',
        required: true,
        secret: true,
        source: accountSource('cookie')
      },
      {
        key: 'notify_key',
        label: '异步通知密钥',
        source: accountSource('qr_url'),
        hint: '选填；填写后按通知密钥验签，留空则回调时改为主动查单确认。'
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
    summary: '填写钱包地址、USDT 汇率，以及可选的订单时长（秒）；留空时继续使用系统默认超时。',
    workspace: 'account',
    fields: [
      {
        key: 'wallet_address',
        label: '钱包地址',
        required: true,
        source: accountSource('wxname')
      },
      { key: 'exchange_rate', label: 'USDT 汇率', source: accountSource('cookie.exchange_rate') },
      {
        key: 'order_timeout',
        label: '订单时长（秒）',
        source: accountSource('remark'),
        hint: '留空时继续使用系统默认超时时间。'
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
