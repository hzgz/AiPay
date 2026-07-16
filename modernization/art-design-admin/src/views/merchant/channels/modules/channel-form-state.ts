import { ElMessage } from 'element-plus'
import {
  defaultCredentialQrType,
  isAlipayOfficialCertMode,
  isJiaofeiyiCredentialCode,
  isMultiModeCredentialCode,
  isRequiredCredentialCode,
  isWxpaySoftwareRewardMode,
  normalizeQrTypeSelection,
  parseModeCsv,
  resolveAccountFieldEditor,
  resolveNormalizedQrType
} from '@/views/shared/paymentAccountCredential'
import { displayAccountFieldLabel } from '@/views/shared/paymentAccountDisplay'
import {
  getAccountCodeMeta,
  type PaymentAccountCreateCode as AccountCreateCode
} from '@/views/shared/paymentAccountMeta'
import { isPaymentAccountDecimalValue as isDecimalValue } from '@/views/shared/paymentAccountPageShared'

type AccountItem = Api.Payments.AccountListItem
type AccountEditable = Api.Payments.AccountEditable

export type MerchantChannelFormScope = 'create' | 'credential'

export interface MerchantChannelPluginOption {
  code: string
  name: string
  method_types?: string[]
}

export interface MerchantChannelCreateFormState {
  payment_method_type: string
  plugin_code: string
  code: '' | AccountCreateCode
  pid: string
  identifier: string
  qr_type: string
  qr_url: string
  cookie: string
  memo: string
  remark: string
  wx_guid: string
  cloud_id: string
  extra_value: string
  daymaxcount: string
  daymaxmoney: string
  allmaxcount: string
  allmaxmoney: string
  status: boolean
  is_status: boolean
}

export interface MerchantChannelEditFormState {
  memo: string
  daymaxcount: string
  daymaxmoney: string
  allmaxcount: string
  allmaxmoney: string
}

export interface MerchantChannelCredentialFormState {
  pid: string
  identifier: string
  qr_type: string
  qr_url: string
  cookie: string
  remark: string
  wx_guid: string
  cloud_id: string
  extra_value: string
}

export interface MerchantChannelStatusFormState {
  status: boolean
  is_status: boolean
}

export interface MerchantChannelTestPayFormState {
  pay_amount: string
}

export function createEmptyMerchantChannelCreateForm(): MerchantChannelCreateFormState {
  return {
    payment_method_type: '',
    plugin_code: '',
    code: '' as '' | AccountCreateCode,
    pid: '',
    identifier: '',
    qr_type: '',
    qr_url: '',
    cookie: '',
    memo: '',
    remark: '',
    wx_guid: '',
    cloud_id: '',
    extra_value: '',
    daymaxcount: '0',
    daymaxmoney: '',
    allmaxcount: '0',
    allmaxmoney: '',
    status: false,
    is_status: true
  }
}

export function createEmptyMerchantChannelEditForm(): MerchantChannelEditFormState {
  return {
    memo: '',
    daymaxcount: '0',
    daymaxmoney: '',
    allmaxcount: '0',
    allmaxmoney: ''
  }
}

export function createEmptyMerchantChannelCredentialForm(): MerchantChannelCredentialFormState {
  return {
    pid: '',
    identifier: '',
    qr_type: '',
    qr_url: '',
    cookie: '',
    remark: '',
    wx_guid: '',
    cloud_id: '',
    extra_value: ''
  }
}

export function createEmptyMerchantChannelStatusForm(): MerchantChannelStatusFormState {
  return {
    status: false,
    is_status: true
  }
}

export function createEmptyMerchantChannelTestPayForm(): MerchantChannelTestPayFormState {
  return {
    pay_amount: ''
  }
}

export function handleMerchantChannelCreatePaymentMethodChange(
  form: MerchantChannelCreateFormState,
  value: string
) {
  const paymentMethodType = String(value || '').trim()
  const preserved = snapshotCreateGeneralState(form)

  Object.assign(form, createEmptyMerchantChannelCreateForm(), preserved, {
    payment_method_type: paymentMethodType
  })
}

export function handleMerchantChannelCreatePluginChange(
  form: MerchantChannelCreateFormState,
  value: string
) {
  const pluginCode = String(value || '').trim() as '' | AccountCreateCode
  const meta = getAccountCodeMeta(pluginCode)
  const preserved = snapshotCreateGeneralState(form)

  Object.assign(form, createEmptyMerchantChannelCreateForm(), preserved, {
    payment_method_type: preserved.payment_method_type,
    plugin_code: pluginCode,
    code: pluginCode,
    qr_type: defaultCredentialQrType(pluginCode, meta)
  })
}

export function supportsMerchantChannelCredentialEditCode(code?: string | null) {
  return Boolean(getAccountCodeMeta(code))
}

export function buildMerchantChannelCreatePayload(
  form: MerchantChannelCreateFormState,
  filteredPluginOptions: MerchantChannelPluginOption[]
): Record<string, any> | null {
  const activeCreateMeta = getAccountCodeMeta(form.code) || getAccountCodeMeta('alipay_software')!
  const qrUrlEditor = resolveAccountFieldEditor(form.code, 'qr_url', form.qr_type)

  trimMerchantChannelCreateForm(form)

  if (!form.payment_method_type) {
    ElMessage.warning('请选择支付方式')
    return null
  }

  if (!form.plugin_code || !form.code) {
    ElMessage.warning('请选择支付插件')
    return null
  }

  if (!filteredPluginOptions.some((item) => String(item.code || '').trim() === form.code)) {
    ElMessage.warning('当前支付方式下没有这个插件，请重新选择。')
    return null
  }

  if (!form.identifier) {
    ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.identifierLabel)}`)
    return null
  }

  if (!/^\d+$/.test(form.daymaxcount)) {
    ElMessage.warning('单日次数上限必须为非负整数。')
    return null
  }

  if (!/^\d+$/.test(form.allmaxcount)) {
    ElMessage.warning('累计次数上限必须为非负整数。')
    return null
  }

  if (!isDecimalValue(form.daymaxmoney)) {
    ElMessage.warning('单日金额上限格式不正确。')
    return null
  }

  if (!isDecimalValue(form.allmaxmoney)) {
    ElMessage.warning('累计金额上限格式不正确。')
    return null
  }

  if (form.code === 'alipay_software' && form.qr_type === 'pic' && !form.qr_url) {
    ElMessage.warning('支付宝软件版图片模式必须上传二维码图片。')
    return null
  }

  if (
    form.code === 'wxpay_software' &&
    isWxpaySoftwareRewardMode(form.code, form.qr_type, form.qr_url) &&
    !form.qr_url
  ) {
    ElMessage.warning('微信软件版赞赏码模式必须上传赞赏码图片。')
    return null
  }

  if (activeCreateMeta.supportsPid && form.code !== 'alipay_official' && !form.pid) {
    ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.pidLabel)}`)
    return null
  }

  if (activeCreateMeta.qrTypeOptions.length > 0) {
    const allowedQrTypes = activeCreateMeta.qrTypeOptions.map((option) => option.value) as string[]
    const normalizedQrType = normalizeQrTypeSelection(form.code, form.qr_type, activeCreateMeta)

    if (isMultiModeCredentialCode(form.code)) {
      const selections = parseModeCsv(normalizedQrType)
      if (selections.length === 0 || selections.some((value) => !allowedQrTypes.includes(value))) {
        ElMessage.warning('请选择至少一个有效的可用接口。')
        return null
      }
    } else if (!allowedQrTypes.includes(normalizedQrType)) {
      ElMessage.warning('请选择一个有效的路由模式。')
      return null
    }

    form.qr_type = normalizedQrType
  }

  if (form.code === 'wxpay_v3' && !form.qr_url) {
    ElMessage.warning(`请上传${displayAccountFieldLabel(activeCreateMeta.qrUrlLabel)}`)
    return null
  }

  if (isRequiredCredentialCode(form.code) && activeCreateMeta.supportsQrUrl && !form.qr_url) {
    ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.qrUrlLabel)}`)
    return null
  }

  if (isRequiredCredentialCode(form.code) && activeCreateMeta.supportsCookie && !form.cookie) {
    if (form.code === 'alipay_official' && isAlipayOfficialCertMode(form.remark)) {
      // 证书模式下允许后端从支付宝公钥证书中自动提取公钥。
    } else {
      ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.cookieLabel)}`)
      return null
    }
  }

  if (
    activeCreateMeta.supportsRemark &&
    !isJiaofeiyiCredentialCode(form.code) &&
    form.code !== 'alipay_official' &&
    !form.remark
  ) {
    ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.remarkLabel)}`)
    return null
  }

  if (activeCreateMeta.supportsWxGuid && form.code !== 'alipay_official' && !form.wx_guid) {
    ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.wxGuidLabel)}`)
    return null
  }

  if (form.code === 'alipay_official' && isAlipayOfficialCertMode(form.remark)) {
    if (!form.wx_guid) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.wxGuidLabel)}`)
      return null
    }

    if (!form.cloud_id) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.cloudIdLabel)}`)
      return null
    }

    if (!form.extra_value) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.extraValueLabel)}`)
      return null
    }
  }

  if (form.code === 'alipay_bill' && activeCreateMeta.supportsExtraValue && !form.extra_value) {
    ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.extraValueLabel)}`)
    return null
  }

  if (
    isJiaofeiyiCredentialCode(form.code) &&
    form.extra_value &&
    !/^https?:\/\/.+/i.test(form.extra_value)
  ) {
    ElMessage.warning('远程 API 地址必须以 http:// 或 https:// 开头。')
    return null
  }

  if (
    isJiaofeiyiCredentialCode(form.code) &&
    form.cloud_id &&
    !/^https?:\/\/.+/i.test(form.cloud_id)
  ) {
    ElMessage.warning('代理 IP API 地址必须以 http:// 或 https:// 开头。')
    return null
  }

  return {
    payment_method_type: form.payment_method_type,
    plugin_code: form.plugin_code,
    code: form.code,
    identifier: form.identifier,
    pid: activeCreateMeta.supportsPid ? form.pid : '',
    qr_type: form.qr_type,
    qr_url: activeCreateMeta.supportsQrUrl && qrUrlEditor !== 'hidden' ? form.qr_url : '',
    cookie: activeCreateMeta.supportsCookie ? form.cookie : '',
    memo: form.memo,
    remark: activeCreateMeta.supportsRemark ? form.remark : '',
    wx_guid: activeCreateMeta.supportsWxGuid ? form.wx_guid : '',
    cloud_id: activeCreateMeta.supportsCloudId ? form.cloud_id : '',
    extra_value: activeCreateMeta.supportsExtraValue ? form.extra_value : '',
    daymaxcount: form.daymaxcount,
    daymaxmoney: form.daymaxmoney,
    allmaxcount: form.allmaxcount,
    allmaxmoney: form.allmaxmoney,
    status: form.status,
    is_status: form.is_status
  }
}

export function buildMerchantChannelUpdatePayload(
  form: MerchantChannelEditFormState
): Api.Payments.AccountUpdatePayload | null {
  trimMerchantChannelEditForm(form)

  if (!/^\d+$/.test(form.daymaxcount)) {
    ElMessage.warning('单日次数上限必须为非负整数。')
    return null
  }

  if (!/^\d+$/.test(form.allmaxcount)) {
    ElMessage.warning('累计次数上限必须为非负整数。')
    return null
  }

  if (!isDecimalValue(form.daymaxmoney)) {
    ElMessage.warning('单日金额上限格式不正确。')
    return null
  }

  if (!isDecimalValue(form.allmaxmoney)) {
    ElMessage.warning('累计金额上限格式不正确。')
    return null
  }

  return {
    memo: form.memo,
    daymaxcount: form.daymaxcount,
    daymaxmoney: form.daymaxmoney,
    allmaxcount: form.allmaxcount,
    allmaxmoney: form.allmaxmoney
  }
}

export function buildMerchantChannelCredentialPayload(
  form: MerchantChannelCredentialFormState,
  code: string
): Api.Payments.AccountCredentialUpdatePayload | null {
  const meta = getAccountCodeMeta(code)
  if (!meta) {
    ElMessage.warning('当前通道类型暂不支持编辑凭证。')
    return null
  }

  const qrUrlEditor = resolveAccountFieldEditor(code, 'qr_url', form.qr_type)
  trimMerchantChannelCredentialForm(form)

  if (!form.identifier) {
    ElMessage.warning(`请输入${displayAccountFieldLabel(meta.identifierLabel)}`)
    return null
  }

  if (meta.supportsPid && code !== 'alipay_official' && !form.pid) {
    ElMessage.warning(`请输入${displayAccountFieldLabel(meta.pidLabel)}`)
    return null
  }

  if (meta.qrTypeOptions.length > 0) {
    const allowedQrTypes = meta.qrTypeOptions.map((option) => option.value) as string[]
    const normalizedQrType = normalizeQrTypeSelection(code, form.qr_type, meta)

    if (isMultiModeCredentialCode(code)) {
      const selections = parseModeCsv(normalizedQrType)
      if (selections.length === 0 || selections.some((value) => !allowedQrTypes.includes(value))) {
        ElMessage.warning('请选择至少一个有效的可用接口。')
        return null
      }
    } else if (!allowedQrTypes.includes(normalizedQrType)) {
      ElMessage.warning('请选择一个有效的路由模式。')
      return null
    }

    form.qr_type = normalizedQrType
  }

  if (code === 'alipay_software' && form.qr_type === 'pic' && !form.qr_url) {
    ElMessage.warning('支付宝软件版图片模式必须上传二维码图片。')
    return null
  }

  if (
    code === 'wxpay_software' &&
    isWxpaySoftwareRewardMode(code, form.qr_type, form.qr_url) &&
    !form.qr_url
  ) {
    ElMessage.warning('微信软件版赞赏码模式必须上传赞赏码图片。')
    return null
  }

  if (code === 'wxpay_v3' && !form.qr_url) {
    ElMessage.warning(`请上传${displayAccountFieldLabel(meta.qrUrlLabel)}`)
    return null
  }

  if (isRequiredCredentialCode(code) && meta.supportsQrUrl && !form.qr_url) {
    ElMessage.warning(`请输入${displayAccountFieldLabel(meta.qrUrlLabel)}`)
    return null
  }

  if (isRequiredCredentialCode(code) && meta.supportsCookie && !form.cookie) {
    if (code === 'alipay_official' && isAlipayOfficialCertMode(form.remark)) {
      // 证书模式下允许后端从支付宝公钥证书中自动提取公钥。
    } else {
      ElMessage.warning(`请输入${displayAccountFieldLabel(meta.cookieLabel)}`)
      return null
    }
  }

  if (
    meta.supportsRemark &&
    !isJiaofeiyiCredentialCode(code) &&
    code !== 'alipay_official' &&
    !form.remark
  ) {
    ElMessage.warning(`请输入${displayAccountFieldLabel(meta.remarkLabel)}`)
    return null
  }

  if (meta.supportsWxGuid && code !== 'alipay_official' && !form.wx_guid) {
    ElMessage.warning(`请输入${displayAccountFieldLabel(meta.wxGuidLabel)}`)
    return null
  }

  if (code === 'alipay_official' && isAlipayOfficialCertMode(form.remark)) {
    if (!form.wx_guid) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(meta.wxGuidLabel)}`)
      return null
    }

    if (!form.cloud_id) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(meta.cloudIdLabel)}`)
      return null
    }

    if (!form.extra_value) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(meta.extraValueLabel)}`)
      return null
    }
  }

  if (code === 'alipay_bill' && meta.supportsExtraValue && !form.extra_value) {
    ElMessage.warning(`请输入${displayAccountFieldLabel(meta.extraValueLabel)}`)
    return null
  }

  if (
    isJiaofeiyiCredentialCode(code) &&
    form.extra_value &&
    !/^https?:\/\/.+/i.test(form.extra_value)
  ) {
    ElMessage.warning('远程 API 地址必须以 http:// 或 https:// 开头。')
    return null
  }

  if (
    isJiaofeiyiCredentialCode(code) &&
    form.cloud_id &&
    !/^https?:\/\/.+/i.test(form.cloud_id)
  ) {
    ElMessage.warning('代理 IP API 地址必须以 http:// 或 https:// 开头。')
    return null
  }

  return {
    identifier: form.identifier,
    pid: meta.supportsPid ? form.pid : '',
    qr_type: form.qr_type,
    qr_url: meta.supportsQrUrl && qrUrlEditor !== 'hidden' ? form.qr_url : '',
    cookie: meta.supportsCookie ? form.cookie : '',
    remark: meta.supportsRemark ? form.remark : '',
    wx_guid: meta.supportsWxGuid ? form.wx_guid : '',
    cloud_id: meta.supportsCloudId ? form.cloud_id : '',
    extra_value: meta.supportsExtraValue ? form.extra_value : ''
  }
}

export function buildMerchantChannelStatusPayload(
  form: MerchantChannelStatusFormState
): Api.Payments.AccountStatusUpdatePayload {
  return {
    status: form.status,
    is_status: form.is_status
  }
}

export function buildMerchantChannelTestPayPayload(
  form: MerchantChannelTestPayFormState
): { pay_amount: string; money: string } | null {
  form.pay_amount = String(form.pay_amount || '').trim()

  if (!isDecimalValue(form.pay_amount) || Number(form.pay_amount) <= 0) {
    ElMessage.warning('测试金额必须大于 0，且最多保留两位小数。')
    return null
  }

  return {
    pay_amount: form.pay_amount,
    money: form.pay_amount
  }
}

export function buildMerchantChannelEditableFromAccount(item: AccountItem): AccountEditable {
  return {
    memo: item.memo || '',
    daymaxcount: String(item.daymaxcount ?? 0),
    daymaxmoney: item.daymaxmoney || '',
    allmaxcount: String(item.allmaxcount ?? 0),
    allmaxmoney: item.allmaxmoney || '',
    status: Number(item.status || 0),
    is_status: Number(item.is_status || 0),
    code: item.code || '',
    credential_supported: supportsMerchantChannelCredentialEditCode(item.code),
    pid: '',
    identifier: '',
    qr_type: item.qr_type || '',
    qr_url: '',
    cookie: '',
    remark: '',
    wx_guid: '',
    cloud_id: '',
    extra_value: ''
  }
}

export function syncMerchantChannelEditForm(
  form: MerchantChannelEditFormState,
  editable: AccountEditable
) {
  form.memo = editable.memo || ''
  form.daymaxcount = editable.daymaxcount || '0'
  form.daymaxmoney = editable.daymaxmoney || ''
  form.allmaxcount = editable.allmaxcount || '0'
  form.allmaxmoney = editable.allmaxmoney || ''
}

export function syncMerchantChannelCredentialForm(
  form: MerchantChannelCredentialFormState,
  editable: AccountEditable,
  code?: string
) {
  const meta = getAccountCodeMeta(code || editable.code)

  form.pid = editable.pid || ''
  form.identifier = editable.identifier || ''
  form.qr_type = resolveNormalizedQrType(
    code || editable.code,
    editable.qr_type,
    meta,
    editable.qr_url
  )
  form.qr_url = editable.qr_url || ''
  form.cookie = editable.cookie || ''
  form.remark = editable.remark || ''
  form.wx_guid = editable.wx_guid || ''
  form.cloud_id = editable.cloud_id || ''
  form.extra_value = editable.extra_value || ''
}

export function syncMerchantChannelStatusForm(
  form: MerchantChannelStatusFormState,
  editable: AccountEditable
) {
  form.status = Number(editable.status || 0) === 1
  form.is_status = Number(editable.is_status || 0) === 1
}

export function resolveMerchantChannelTestPayAmount(
  testPaySettings: Record<string, any>,
  value?: null | number | string
) {
  const current = String(value ?? '').trim()
  if (isDecimalValue(current) && Number(current) > 0) {
    return current
  }

  const fallback = String(testPaySettings.amount || '').trim()
  if (isDecimalValue(fallback) && Number(fallback) > 0) {
    return fallback
  }

  return '1.00'
}

function snapshotCreateGeneralState(form: MerchantChannelCreateFormState) {
  return {
    payment_method_type: form.payment_method_type,
    memo: form.memo,
    daymaxcount: form.daymaxcount,
    daymaxmoney: form.daymaxmoney,
    allmaxcount: form.allmaxcount,
    allmaxmoney: form.allmaxmoney,
    status: form.status,
    is_status: form.is_status
  }
}

function trimMerchantChannelCreateForm(form: MerchantChannelCreateFormState) {
  form.payment_method_type = form.payment_method_type.trim()
  form.plugin_code = form.plugin_code.trim()
  form.pid = form.pid.trim()
  form.identifier = form.identifier.trim()
  form.qr_type = form.qr_type.trim()
  form.qr_url = form.qr_url.trim()
  form.cookie = form.cookie.trim()
  form.memo = form.memo.trim()
  form.remark = form.remark.trim()
  form.wx_guid = form.wx_guid.trim()
  form.cloud_id = form.cloud_id.trim()
  form.extra_value = form.extra_value.trim()
  form.daymaxcount = form.daymaxcount.trim()
  form.daymaxmoney = form.daymaxmoney.trim()
  form.allmaxcount = form.allmaxcount.trim()
  form.allmaxmoney = form.allmaxmoney.trim()
}

function trimMerchantChannelEditForm(form: MerchantChannelEditFormState) {
  form.memo = form.memo.trim()
  form.daymaxcount = form.daymaxcount.trim()
  form.daymaxmoney = form.daymaxmoney.trim()
  form.allmaxcount = form.allmaxcount.trim()
  form.allmaxmoney = form.allmaxmoney.trim()
}

function trimMerchantChannelCredentialForm(form: MerchantChannelCredentialFormState) {
  form.pid = form.pid.trim()
  form.identifier = form.identifier.trim()
  form.qr_type = form.qr_type.trim()
  form.qr_url = form.qr_url.trim()
  form.cookie = form.cookie.trim()
  form.remark = form.remark.trim()
  form.wx_guid = form.wx_guid.trim()
  form.cloud_id = form.cloud_id.trim()
  form.extra_value = form.extra_value.trim()
}



