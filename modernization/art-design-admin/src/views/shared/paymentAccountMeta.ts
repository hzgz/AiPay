export type PaymentAccountQrTypeOption = {
  label: string
  value: string
}

export type PaymentAccountCodeMeta = {
  createEnabled: boolean
  label: string
  typeLabel: string
  identifierLabel: string
  identifierPlaceholder: string
  qrTypeOptions: ReadonlyArray<PaymentAccountQrTypeOption>
  supportsPid: boolean
  pidLabel: string
  pidPlaceholder: string
  supportsQrUrl: boolean
  supportsCookie: boolean
  supportsRemark: boolean
  supportsWxGuid: boolean
  supportsCloudId?: boolean
  supportsExtraValue: boolean
  qrUrlLabel: string
  qrUrlPlaceholder: string
  cookieLabel: string
  cookiePlaceholder: string
  remarkLabel: string
  remarkPlaceholder: string
  wxGuidLabel: string
  wxGuidPlaceholder: string
  cloudIdLabel?: string
  cloudIdPlaceholder?: string
  extraValueLabel: string
  extraValuePlaceholder: string
  credentialHelpText: string
}

export const ACCOUNT_CODE_META = {
  alipay_software: {
    createEnabled: true,
    label: '支付宝软件版',
    typeLabel: '支付宝',
    identifierLabel: 'PID',
    identifierPlaceholder: '请输入支付宝 PID',
    qrTypeOptions: [
      { label: '代理模式', value: 'agt' },
      { label: '图片模式', value: 'pic' }
    ],
    supportsPid: false,
    pidLabel: 'PID',
    pidPlaceholder: '',
    supportsQrUrl: true,
    supportsCookie: false,
    supportsRemark: false,
    supportsWxGuid: false,
    supportsExtraValue: false,
    qrUrlLabel: '二维码图片地址',
    qrUrlPlaceholder: '图片模式下必须上传或填写二维码图片地址',
    cookieLabel: 'Cookie / 公钥',
    cookiePlaceholder: '',
    remarkLabel: '备注',
    remarkPlaceholder: '',
    wxGuidLabel: '证书序列号',
    wxGuidPlaceholder: '',
    extraValueLabel: '扩展值',
    extraValuePlaceholder: '',
    credentialHelpText:
      '用于维护路由 PID，可在此切换代理模式或二维码图片模式。'
  },
  wxpay_software: {
    createEnabled: true,
    label: '微信软件版',
    typeLabel: '微信',
    identifierLabel: '账户标识',
    identifierPlaceholder: '请输入微信账户标识或应用标记',
    qrTypeOptions: [
      { label: '个人/经营码', value: 'personOrMerchant' },
      { label: '赞赏码', value: 'appreciate' }
    ],
    supportsPid: false,
    pidLabel: '商户 PID',
    pidPlaceholder: '',
    supportsQrUrl: true,
    supportsCookie: false,
    supportsRemark: false,
    supportsWxGuid: false,
    supportsExtraValue: false,
    qrUrlLabel: '二维码内容 / 赞赏码图片',
    qrUrlPlaceholder: '按所选模式填写二维码内容，或上传赞赏码图片',
    cookieLabel: 'Cookie / 公钥',
    cookiePlaceholder: '',
    remarkLabel: '备注',
    remarkPlaceholder: '',
    wxGuidLabel: '证书序列号',
    wxGuidPlaceholder: '',
    extraValueLabel: '扩展值',
    extraValuePlaceholder: '',
    credentialHelpText: '个人/经营码支持二维码解析，赞赏码支持直接上传图片。'
  },
  qqpay_software: {
    createEnabled: true,
    label: 'QQ 软件版',
    typeLabel: 'QQ',
    identifierLabel: 'QQ号',
    identifierPlaceholder: '请输入 QQ 账号',
    qrTypeOptions: [],
    supportsPid: false,
    pidLabel: '商户 PID',
    pidPlaceholder: '',
    supportsQrUrl: true,
    supportsCookie: false,
    supportsRemark: false,
    supportsWxGuid: false,
    supportsExtraValue: false,
    qrUrlLabel: '二维码 / 凭证种子',
    qrUrlPlaceholder: '可填写二维码内容或凭证种子',
    cookieLabel: 'Cookie / 公钥',
    cookiePlaceholder: '',
    remarkLabel: '备注',
    remarkPlaceholder: '',
    wxGuidLabel: '证书序列号',
    wxGuidPlaceholder: '',
    extraValueLabel: '扩展值',
    extraValuePlaceholder: '',
    credentialHelpText: '维护 QQ 软件版主账号标识，以及可选的二维码内容或凭证种子。'
  },
  usdt: {
    createEnabled: true,
    label: 'USDT',
    typeLabel: 'USDT',
    identifierLabel: '钱包地址',
    identifierPlaceholder: '请输入 USDT 钱包地址',
    qrTypeOptions: [],
    supportsPid: false,
    pidLabel: '商户 PID',
    pidPlaceholder: '',
    supportsQrUrl: false,
    supportsCookie: false,
    supportsRemark: false,
    supportsWxGuid: false,
    supportsExtraValue: false,
    qrUrlLabel: '二维码 / Memo',
    qrUrlPlaceholder: '可填写二维码内容或钱包 Memo',
    cookieLabel: 'Cookie / 公钥',
    cookiePlaceholder: '',
    remarkLabel: '备注',
    remarkPlaceholder: '',
    wxGuidLabel: '证书序列号',
    wxGuidPlaceholder: '',
    extraValueLabel: '扩展值',
    extraValuePlaceholder: '',
    credentialHelpText: '维护 USDT 收款钱包地址，备注与限额请在独立弹窗中管理。'
  },
  alipay_bill: {
    createEnabled: true,
    label: '支付宝二维码账单插件',
    typeLabel: '支付宝',
    identifierLabel: '应用 ID',
    identifierPlaceholder: '请输入支付宝应用 ID',
    qrTypeOptions: [],
    supportsPid: false,
    pidLabel: 'PID',
    pidPlaceholder: '',
    supportsQrUrl: true,
    supportsCookie: true,
    supportsRemark: false,
    supportsWxGuid: false,
    supportsExtraValue: true,
    qrUrlLabel: '私钥',
    qrUrlPlaceholder: '请输入支付宝应用私钥',
    cookieLabel: '公钥',
    cookiePlaceholder: '请输入支付宝公钥',
    remarkLabel: '备注',
    remarkPlaceholder: '',
    wxGuidLabel: '证书序列号',
    wxGuidPlaceholder: '',
    extraValueLabel: '账单二维码内容',
    extraValuePlaceholder: '请输入账单二维码内容或链接',
    credentialHelpText: '编辑器会把私钥、公钥与账单二维码内容组合后写回原始记录。'
  },
  alipay_mck: {
    createEnabled: true,
    label: '支付宝免CK插件',
    typeLabel: '支付宝',
    identifierLabel: '应用 ID',
    identifierPlaceholder: '请输入应用 ID',
    qrTypeOptions: [],
    supportsPid: true,
    pidLabel: 'PID',
    pidPlaceholder: '请输入商户 PID',
    supportsQrUrl: true,
    supportsCookie: true,
    supportsRemark: false,
    supportsWxGuid: false,
    supportsExtraValue: false,
    qrUrlLabel: '私钥',
    qrUrlPlaceholder: '请输入私钥',
    cookieLabel: '公钥',
    cookiePlaceholder: '请输入公钥',
    remarkLabel: '备注',
    remarkPlaceholder: '',
    wxGuidLabel: '证书序列号',
    wxGuidPlaceholder: '',
    extraValueLabel: '扩展值',
    extraValuePlaceholder: '',
    credentialHelpText: '维护常驻在线支付宝账户的 PID、应用 ID 以及公私钥材料。'
  },
  alipay_official: {
    createEnabled: true,
    label: '支付宝官方版V3插件',
    typeLabel: '支付宝',
    identifierLabel: '应用 ID',
    identifierPlaceholder: '请输入支付宝应用 ID',
    qrTypeOptions: [
      { label: '电脑网站支付', value: '1' },
      { label: '手机网站支付', value: '2' },
      { label: '当面付扫码', value: '3' },
      { label: '当面付 JS', value: '4' },
      { label: '预授权支付', value: '5' },
      { label: 'APP 支付', value: '6' },
      { label: 'JSAPI 支付', value: '7' },
      { label: '订单码支付', value: '8' }
    ],
    supportsPid: true,
    pidLabel: '卖家支付宝用户 ID',
    pidPlaceholder: '选填，填写收款支付宝用户 ID',
    supportsQrUrl: true,
    supportsCookie: true,
    supportsRemark: true,
    supportsWxGuid: true,
    supportsCloudId: true,
    supportsExtraValue: true,
    qrUrlLabel: '应用私钥',
    qrUrlPlaceholder: '请输入支付宝应用私钥',
    cookieLabel: '支付宝公钥',
    cookiePlaceholder: '请输入支付宝公钥',
    remarkLabel: '签名模式',
    remarkPlaceholder: '留空默认 key，可填写 key 或 cert',
    wxGuidLabel: '应用公钥证书',
    wxGuidPlaceholder: '证书模式下填写应用公钥证书路径或内容',
    cloudIdLabel: '支付宝公钥证书',
    cloudIdPlaceholder: '证书模式下填写支付宝公钥证书内容',
    extraValueLabel: '支付宝根证书',
    extraValuePlaceholder: '证书模式下填写支付宝根证书内容',
    credentialHelpText:
      '支持密钥模式与证书模式。密钥模式填写应用 ID、公钥、私钥；证书模式改为填写应用证书、支付宝公钥证书和根证书。'
  },
  wxpay_v3: {
    createEnabled: true,
    label: '微信支付 V3 插件',
    typeLabel: '微信',
    identifierLabel: '应用 ID',
    identifierPlaceholder: '请输入微信应用 ID',
    qrTypeOptions: [
      { label: 'Native 支付', value: '1' },
      { label: 'JSAPI 支付', value: '2' },
      { label: 'H5 支付', value: '3' },
      { label: 'APP 支付', value: '5' }
    ],
    supportsPid: true,
    pidLabel: '商户号',
    pidPlaceholder: '请输入微信商户号',
    supportsQrUrl: true,
    supportsCookie: true,
    supportsRemark: true,
    supportsWxGuid: true,
    supportsCloudId: true,
    supportsExtraValue: true,
    qrUrlLabel: '商户 API 私钥',
    qrUrlPlaceholder: '请输入商户 API 私钥内容',
    cookieLabel: '平台公钥',
    cookiePlaceholder: '请输入微信支付平台公钥',
    remarkLabel: 'API V3 密钥',
    remarkPlaceholder: '请输入 API V3 密钥',
    wxGuidLabel: '商户证书序列号',
    wxGuidPlaceholder: '请输入商户证书序列号',
    cloudIdLabel: '微信支付平台公钥 ID',
    cloudIdPlaceholder: '选填，填写微信支付平台公钥 ID',
    extraValueLabel: '商户 APIv2 密钥',
    extraValuePlaceholder: '选填，部分场景需要 APIv2 密钥',
    credentialHelpText:
      '填写应用 ID、商户号、平台公钥、商户私钥、API V3 密钥、商户证书序列号，以及可选的平台公钥 ID、APIv2 密钥。'
  },
  universal_epay: {
    createEnabled: true,
    label: '通用易支付插件',
    typeLabel: '易支付',
    identifierLabel: '商户ID',
    identifierPlaceholder: '请输入上游易支付商户ID',
    qrTypeOptions: [
      { label: '普通接口', value: '0' },
      { label: 'MAPI接口', value: '1' }
    ],
    supportsPid: false,
    pidLabel: '商户PID',
    pidPlaceholder: '',
    supportsQrUrl: true,
    supportsCookie: true,
    supportsRemark: false,
    supportsWxGuid: false,
    supportsCloudId: false,
    supportsExtraValue: false,
    qrUrlLabel: '接口地址',
    qrUrlPlaceholder: '请输入完整接口地址，例如 https://demo.com/',
    cookieLabel: '商户密钥',
    cookiePlaceholder: '请输入上游易支付商户密钥',
    remarkLabel: '备注',
    remarkPlaceholder: '',
    wxGuidLabel: '证书序列号',
    wxGuidPlaceholder: '',
    cloudIdLabel: '云端标识',
    cloudIdPlaceholder: '',
    extraValueLabel: '扩展值',
    extraValuePlaceholder: '',
    credentialHelpText:
      '一个插件目录同时支持支付宝、微信、QQ 三种支付方式。先选择支付方式，再填写商户ID、接口地址、商户密钥和接口模式。'
  },
  jiaofeiyi_alipay: {
    createEnabled: true,
    label: '缴费易支付宝',
    typeLabel: '支付宝',
    identifierLabel: '商户号',
    identifierPlaceholder: '请输入缴费易商户号',
    qrTypeOptions: [],
    supportsPid: true,
    pidLabel: '商户ID',
    pidPlaceholder: '请输入商户ID',
    supportsQrUrl: true,
    supportsCookie: true,
    supportsRemark: true,
    supportsWxGuid: false,
    supportsCloudId: true,
    supportsExtraValue: true,
    qrUrlLabel: '收款备注',
    qrUrlPlaceholder: '可填写传给通道的收款备注',
    cookieLabel: '店铺名',
    cookiePlaceholder: '可填写店铺名称',
    remarkLabel: '指定IP',
    remarkPlaceholder: '可填写固定客户端 IP',
    wxGuidLabel: '代理IP API',
    wxGuidPlaceholder: '',
    cloudIdLabel: '代理IP API',
    cloudIdPlaceholder: '填写代理IP提取接口',
    extraValueLabel: '远程API',
    extraValuePlaceholder: '填写完整远程API地址',
    credentialHelpText:
      '维护缴费易商户ID、商户号、店铺名、收款备注、指定IP、远程API与代理IP API。'
  },
  jiaofeiyi_wxpay: {
    createEnabled: true,
    label: '缴费易微信',
    typeLabel: '微信',
    identifierLabel: '商户号',
    identifierPlaceholder: '请输入缴费易商户号',
    qrTypeOptions: [
      { label: 'H5支付', value: '1' },
      { label: '二维码支付', value: '2' },
      { label: '二维码链接', value: '3' }
    ],
    supportsPid: true,
    pidLabel: '商户ID',
    pidPlaceholder: '请输入商户ID',
    supportsQrUrl: true,
    supportsCookie: true,
    supportsRemark: true,
    supportsWxGuid: false,
    supportsCloudId: true,
    supportsExtraValue: true,
    qrUrlLabel: '收款备注',
    qrUrlPlaceholder: '可填写传给通道的收款备注',
    cookieLabel: '店铺名',
    cookiePlaceholder: '可填写店铺名称',
    remarkLabel: '指定IP',
    remarkPlaceholder: '可填写固定客户端 IP',
    wxGuidLabel: '代理IP API',
    wxGuidPlaceholder: '',
    cloudIdLabel: '代理IP API',
    cloudIdPlaceholder: '填写代理IP提取接口',
    extraValueLabel: '远程API',
    extraValuePlaceholder: '填写完整远程API地址',
    credentialHelpText:
      '维护缴费易微信通道的商户ID、商户号、微信支付模式、店铺名、收款备注、指定IP、远程API与代理IP API。'
  }
} as const

;(ACCOUNT_CODE_META as Record<string, PaymentAccountCodeMeta>).wxpay_v3 = {
  createEnabled: true,
  label: '微信官方版V3插件',
  typeLabel: '微信',
  identifierLabel: '服务号/小程序/开放平台AppID',
  identifierPlaceholder: '请输入服务号/小程序/开放平台AppID',
  qrTypeOptions: [
    { label: 'Native支付', value: '1' },
    { label: 'JSAPI支付', value: '2' },
    { label: 'H5支付', value: '3' },
    { label: 'APP支付', value: '5' }
  ],
  supportsPid: true,
  pidLabel: '商户号',
  pidPlaceholder: '请输入商户号',
  supportsQrUrl: true,
  supportsCookie: true,
  supportsRemark: true,
  supportsWxGuid: true,
  supportsCloudId: true,
  supportsExtraValue: true,
  qrUrlLabel: '商户API私钥',
  qrUrlPlaceholder: '请上传商户API私钥文件',
  cookieLabel: '微信支付公钥',
  cookiePlaceholder: '可留空，不上传则继续使用平台证书模式',
  remarkLabel: '商户APIv3密钥',
  remarkPlaceholder: '请输入商户APIv3密钥',
  wxGuidLabel: '商户API证书序列号',
  wxGuidPlaceholder: '请输入商户API证书序列号',
  cloudIdLabel: '微信支付公钥ID',
  cloudIdPlaceholder: '平台证书模式需要留空',
  extraValueLabel: '商户APIv2密钥',
  extraValuePlaceholder: '非必填，仅付款码支付需要填写',
  credentialHelpText:
    '按微信支付 V3 接口要求填写 AppID、商户号、APIv3 密钥、证书序列号，并上传商户API私钥；如需走微信支付公钥模式，再补充公钥ID与公钥文件。'
}

export type PaymentAccountCreateCode = keyof typeof ACCOUNT_CODE_META
export type PaymentAccountMethodType = 'alipay' | 'qqpay' | 'usdt' | 'wxpay'

export const PAYMENT_METHOD_DISPLAY_ORDER: PaymentAccountMethodType[] = [
  'wxpay',
  'alipay',
  'qqpay',
  'usdt'
]

export const PAYMENT_METHOD_LABEL_MAP: Record<PaymentAccountMethodType, string> = {
  wxpay: '微信',
  alipay: '支付宝',
  qqpay: 'QQ',
  usdt: 'USDT'
}

export const ACCOUNT_METHOD_TYPE_MAP: Record<PaymentAccountCreateCode, PaymentAccountMethodType> = {
  alipay_software: 'alipay',
  wxpay_software: 'wxpay',
  qqpay_software: 'qqpay',
  usdt: 'usdt',
  alipay_bill: 'alipay',
  alipay_official: 'alipay',
  wxpay_v3: 'wxpay',
  alipay_mck: 'alipay',
  universal_epay: 'alipay',
  jiaofeiyi_alipay: 'alipay',
  jiaofeiyi_wxpay: 'wxpay'
}

export function getAccountCodeMeta(code?: string | null): PaymentAccountCodeMeta | null {
  if (!code) {
    return null
  }

  return ACCOUNT_CODE_META[code as PaymentAccountCreateCode] || null
}
