import axios, { type AxiosRequestConfig } from 'axios'
import { resolveBackendOrigin } from '@/utils/http/base'
import { getMerchantFrontToken } from '@/utils/merchant-session'

export interface MerchantApiResponse<T = any> {
  code: number
  msg: string
  message: string
  data: T
  records?: any[]
  pagination?: {
    current: number
    size: number
    total: number
  }
  summary?: Record<string, any>
  write_actions?: Record<string, boolean>
  migration_guard?: Record<string, any>
  categories?: any[]
  catalog?: Record<string, any>
  extend?: Record<string, any>
  [key: string]: any
}

export interface MerchantCollectionResult<T = Record<string, any>> {
  records: T[]
  pagination: {
    current: number
    size: number
    total: number
  }
  summary: Record<string, any>
  writeActions: Record<string, boolean>
  migrationGuard: Record<string, any>
  categories: any[]
  catalog: Record<string, any>
  raw: MerchantApiResponse<any>
}

export interface MerchantChannelCredentialImageUploadPayload {
  code: string
  field: 'qr_url'
  qr_type?: string
  file: File
}

export interface MerchantChannelCredentialImageUploadResponse {
  code: string
  field: string
  mode: 'image'
  value: string
  href: string
  preview_url: string
  photo_id: number
  path: string
}

export interface MerchantChannelCredentialDecodePayload {
  code: string
  field: 'qr_url' | 'extra_value'
  qr_type?: string
  file: File
}

export interface MerchantChannelCredentialDecodeResponse {
  code: string
  field: string
  mode: 'decoded_text'
  value: string
}

export interface MerchantChannelTestPayResponse {
  state: 'ready' | 'loading' | 'missing' | 'reconciling' | 'paid' | 'timeout'
  state_label: string
  state_message: string
  can_poll: boolean
  trade_no: string
  out_trade_no: string
  pay_amount: string
  type: string
  pay_url: string
  direct_open_url?: null | string
  display_mode: 'image' | 'none' | 'qrcode'
  qrcode_url?: null | string
  raw_qrcode?: string
  is_paid: boolean
  is_timeout: boolean
}

export interface MerchantWxPusherQrPayload {
  enabled: boolean
  token_configured: boolean
  bound: boolean
  uid_masked?: string | null
  manual_save_allowed: boolean
  write_allowed: boolean
  write_message: string
  expires_seconds: number
  callback_entry?: string | null
  callback_url?: string | null
  merchant_id?: number
  merchant_username?: string
  shortUrl?: string | null
  short_url?: string | null
  url?: string | null
  extra?: string | null
  expires?: string | null
  expires_at?: string | null
  qrcode_url?: string | null
}

export interface MerchantWxPusherUidStatusPayload {
  operate: 'bind' | 'edit' | 'update' | string
  bound: boolean
  uid_masked?: string | null
}

export class MerchantApiError extends Error {
  status: number
  code: number
  payload: MerchantApiResponse<any> | null

  constructor(message: string, status: number, code: number, payload: MerchantApiResponse<any> | null) {
    super(message)
    this.name = 'MerchantApiError'
    this.status = status
    this.code = code
    this.payload = payload
  }
}

const merchantClient = axios.create({
  baseURL: resolveBackendOrigin(),
  timeout: 15000,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  },
  validateStatus: () => true
})

merchantClient.interceptors.request.use((config) => {
  const frontToken = getMerchantFrontToken()
  if (frontToken) {
    config.headers = config.headers || {}
    config.headers['X-Front-Token'] = frontToken
  }

  return config
})

async function merchantRequest<T = any>(
  config: AxiosRequestConfig,
  successCodes: number[] = [0, 1, 200]
) {
  const response = await merchantClient.request<MerchantApiResponse<T> | string>({
    ...config,
    params: {
      format: 'json',
      ...(config.params || {})
    }
  })

  if (typeof response.data !== 'object' || response.data === null || Array.isArray(response.data)) {
    throw new MerchantApiError('商户接口未返回 JSON 数据', response.status, response.status, null)
  }

  const payload = response.data
  const acceptedCodes = new Set(successCodes)
  const isSuccess = response.status >= 200 && response.status < 300 && acceptedCodes.has(Number(payload.code))

  if (isSuccess) {
    return payload
  }

  throw new MerchantApiError(
    payload.message || payload.msg || '商户接口请求失败',
    response.status,
    Number(payload.code || response.status || 400),
    payload
  )
}

function normalizeCollection<T = Record<string, any>>(payload: MerchantApiResponse<any>): MerchantCollectionResult<T> {
  const records = Array.isArray(payload.records)
    ? payload.records
    : Array.isArray(payload.data)
      ? payload.data
      : []

  return {
    records,
    pagination: payload.pagination || {
      current: 1,
      size: records.length,
      total: records.length
    },
    summary: payload.summary || {},
    writeActions: payload.write_actions || {},
    migrationGuard: payload.migration_guard || {},
    categories: Array.isArray(payload.categories) ? payload.categories : [],
    catalog: payload.catalog || {},
    raw: payload
  }
}

export function isMerchantUnauthorized(error: unknown) {
  return error instanceof MerchantApiError && error.status === 401
}

export function isMerchantFeatureDisabled(error: unknown) {
  return error instanceof MerchantApiError && error.code === 202
}

export async function merchantLogin(username: string, password: string) {
  const payload = await merchantRequest<{
    url?: string
    token?: string
    merchant_id?: number
    merchant_username?: string
  }>({
    url: '/User/Login',
    method: 'POST',
    data: {
      username,
      password
    }
  })

  return payload.data
}

export async function merchantLogout() {
  await merchantClient.get('/User/Logout')
}

export async function fetchMerchantDashboard() {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/User/Index',
    method: 'GET'
  })

  return payload.data
}

export async function fetchMerchantProfile() {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/My/userpro',
    method: 'GET'
  })

  return payload.data
}

export async function updateMerchantProfile(data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/My/userpro',
      method: 'POST',
      data
    },
    [1, 200]
  )

  return payload.data
}

export async function fetchMerchantNotifications() {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/My/Notifications',
    method: 'GET'
  })

  return payload.data
}

export async function updateMerchantNotifications(data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/My/Notifications',
    method: 'POST',
    data
  })

  return payload.data
}

export async function fetchMerchantConnections() {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/My/Connections',
    method: 'GET'
  })

  return payload.data
}

export async function fetchMerchantWxPusherQrCode() {
  const payload = await merchantRequest<MerchantWxPusherQrPayload>(
    {
      url: '/My/getWxPusherQrCode',
      method: 'POST',
      data: {}
    },
    [1, 200]
  )

  return payload.data
}

export async function fetchMerchantWxPusherUidStatus(operate: 'bind' | 'edit' = 'bind', uid = '') {
  const payload = await merchantRequest<MerchantWxPusherUidStatusPayload>(
    {
      url: '/My/getWxPusherUID',
      method: 'GET',
      params: {
        operate,
        uid
      }
    },
    [1, 2, 200]
  )

  return {
    code: Number(payload.code || 0),
    data: payload.data
  }
}

export async function requestMerchantConnectionCode(
  channel: 'email' | 'mobile',
  mode: 'bind' | 'unbind',
  target: string
) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: mode === 'bind' ? '/My/getBindCode' : '/My/getUBindCode',
      method: 'POST',
      data: {
        bind: channel,
        email: channel === 'email' ? target : '',
        mobile: channel === 'mobile' ? target : ''
      }
    },
    [200]
  )

  return payload.data
}

export async function submitMerchantEmailBinding(type: 1 | 2, email: string, captcha: string) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/My/bindOrUBindEmail',
      method: 'POST',
      data: {
        type,
        email,
        captcha
      }
    },
    [1, 200]
  )

  return payload.data
}

export async function submitMerchantMobileBinding(type: 1 | 2, mobile: string, captcha: string) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/My/bindOrUBindMobile',
      method: 'POST',
      data: {
        type,
        mobile,
        captcha
      }
    },
    [1, 200]
  )

  return payload.data
}

export async function unbindMerchantConnection(type: string) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/My/Unbinding',
      method: 'POST',
      data: { type }
    },
    [1, 200]
  )

  return payload.data
}

export async function saveMerchantWxPusherUid(wxpusherUid: string) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/My/savaWxPuserUID',
      method: 'POST',
      data: {
        wxpusher_uid: wxpusherUid
      }
    },
    [1, 200]
  )

  return payload.data
}

export async function saveMerchantTelegramChatId(chatId: string) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/My/saveTgChatId',
      method: 'POST',
      data: {
        tg_chat_id: chatId
      }
    },
    [1, 200]
  )

  return payload.data
}

export async function fetchMerchantSecurity() {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/My/Security',
    method: 'GET'
  })

  return payload.data
}

export async function fetchMerchantGoogleAuthQrCode() {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/My/getGoogleAuthQrCode',
      method: 'POST',
      data: {}
    },
    [200]
  )

  return payload.data
}

export async function bindMerchantGoogleAuth(code: string) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/My/bindGoogleAuth',
      method: 'POST',
      data: { code }
    },
    [200]
  )

  return payload.data
}

export async function unbindMerchantGoogleAuth(code: string) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/My/uBindGoogleAuth',
      method: 'POST',
      data: { code }
    },
    [200]
  )

  return payload.data
}

export async function updateMerchantPassword(newPassword: string, confirmPassword: string) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/My/UpdatePwd',
    method: 'POST',
    data: {
      newpwd: newPassword,
      renewpwd: confirmPassword
    }
  })

  return payload.data
}

export async function cancelMerchantAccount(confirmation: string) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/My/Cancellation',
      method: 'POST',
      data: {
        confirmation
      }
    },
    [200]
  )

  return payload.data
}

export async function fetchMerchantRealName() {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/My/real_name',
    method: 'GET'
  })

  return payload.data
}

export async function submitMerchantRealName(data: {
  name: string
  idCard: string
  channel: string
}) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/My/realname',
      method: 'POST',
      data
    },
    [200]
  )

  return payload.data
}

export async function pollMerchantRealNameStatus(orderNumber: string) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/My/getRealNameStatus',
      method: 'GET',
      params: {
        orderNumber
      }
    },
    [200, 201]
  )

  return payload.data
}

export async function fetchMerchantAffiliate(params: Record<string, any> = {}) {
  const payload = await merchantRequest({
    url: '/My/affInfo',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function fetchMerchantOrders(params: Record<string, any> = {}) {
  const payload = await merchantRequest({
    url: '/Deal/OrderLog',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function fetchMerchantOrderDetail(id: number) {
  const payload = await merchantRequest<Record<string, any> & { dataArray?: Record<string, any> }>({
    url: '/Deal/getDetails',
    method: 'GET',
    params: { id }
  })

  return payload.dataArray || payload.data
}

export async function replayMerchantOrderCallback(id: number) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/Deal/set_function',
      method: 'POST',
      data: {
        id,
        type: 'reback'
      }
    },
    [200]
  )

  return payload.data
}

export async function fetchMerchantMoneyLogs(params: Record<string, any> = {}) {
  const payload = await merchantRequest({
    url: '/Deal/MoneyLog',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function fetchMerchantRecharges(params: Record<string, any> = {}) {
  const payload = await merchantRequest({
    url: '/Deal/Recharge',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function createMerchantRecharge(data: Record<string, any>) {
  return merchantRequest<Record<string, any>>({
    url: '/Deal/Recharge',
    method: 'POST',
    data
  })
}

export async function redeemMerchantCdk(code: string) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/Deal/cdkPay',
      method: 'POST',
      data: {
        cdk: code
      }
    },
    [200]
  )

  return payload.data
}

export async function fetchMerchantVipPackages(params: Record<string, any> = {}) {
  const payload = await merchantRequest({
    url: '/Deal/Vip',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function purchaseMerchantVipPackage(vipId: number) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/Deal/Vip',
      method: 'POST',
      data: {
        tcid: vipId
      }
    },
    [200]
  )

  return payload.data
}

export async function fetchMerchantApiInfo() {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/My/Api',
    method: 'GET'
  })

  return payload.data
}

export async function generateMerchantApiQrcode(
  lineUrl: string,
  options: {
    bootstrapAppkey?: boolean
  } = {}
) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/My/getApiQrcode',
      method: 'POST',
      data: {
        line_url: lineUrl,
        bootstrap_appkey: options.bootstrapAppkey ? 1 : 0
      }
    },
    [200]
  )

  return payload.data
}

export async function resetMerchantSignKey() {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/My/GeneratingKey',
      method: 'POST',
      data: {}
    },
    [1, 200]
  )

  return payload.data
}

export async function resetMerchantAppKey() {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/My/goAPPKey',
      method: 'POST',
      data: {}
    },
    [1, 200]
  )

  return payload.data
}

export async function fetchMerchantTickets(params: Record<string, any> = {}) {
  const payload = await merchantRequest({
    url: '/My/Ticket',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function createMerchantTicket(data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/My/addTicket',
    method: 'POST',
    data
  })

  return payload.data
}

export async function deleteMerchantTicket(id: number) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/My/delTicket',
    method: 'POST',
    data: { id }
  })

  return payload.data
}

export async function fetchMerchantDomains(params: Record<string, any> = {}) {
  const payload = await merchantRequest({
    url: '/My/is_domain',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function createMerchantDomain(data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/My/addDomain',
    method: 'POST',
    data
  })

  return payload.data
}

export async function updateMerchantDomain(data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/My/editDomain',
    method: 'POST',
    data
  })

  return payload.data
}

export async function deleteMerchantDomain(id: number) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/My/delDomain',
    method: 'POST',
    data: { id }
  })

  return payload.data
}

export async function fetchMerchantLoginLogs(params: Record<string, any> = {}) {
  const payload = await merchantRequest({
    url: '/My/loginlog',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function fetchMerchantChannels(params: Record<string, any> = {}) {
  const payload = await merchantRequest({
    url: '/My/channels',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function fetchMerchantChannelDetail(id: number) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/My/channels/${id}`,
    method: 'GET'
  })

  return payload.data
}

export async function createMerchantChannel(data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/My/channels/create',
    method: 'POST',
    data
  })

  return payload.data
}

export async function uploadMerchantChannelCredentialImage(
  payload: MerchantChannelCredentialImageUploadPayload
) {
  const formData = new FormData()
  formData.append('code', payload.code)
  formData.append('field', payload.field)
  if (payload.qr_type) {
    formData.append('qr_type', payload.qr_type)
  }
  formData.append('file', payload.file)

  const response = await merchantRequest<MerchantChannelCredentialImageUploadResponse>({
    url: '/My/channels/credential-image',
    method: 'POST',
    data: formData
  })

  return response.data
}

export async function decodeMerchantChannelCredentialImage(
  payload: MerchantChannelCredentialDecodePayload
) {
  const formData = new FormData()
  formData.append('code', payload.code)
  formData.append('field', payload.field)
  if (payload.qr_type) {
    formData.append('qr_type', payload.qr_type)
  }
  formData.append('file', payload.file)

  const response = await merchantRequest<MerchantChannelCredentialDecodeResponse>({
    url: '/My/channels/credential-decode',
    method: 'POST',
    data: formData
  })

  return response.data
}

export async function updateMerchantChannel(id: number, data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/My/channels/${id}/update`,
    method: 'POST',
    data
  })

  return payload.data
}

export async function updateMerchantChannelCredentials(id: number, data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/My/channels/${id}/credentials`,
    method: 'POST',
    data
  })

  return payload.data
}

export async function updateMerchantChannelStatus(id: number, data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/My/channels/${id}/status`,
    method: 'POST',
    data
  })

  return payload.data
}

export async function fetchMerchantChannelDeleteAudit(id: number) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/My/channels/${id}/delete-audit`,
    method: 'GET'
  })

  return payload.data
}

export async function deleteMerchantChannel(id: number, data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/My/channels/${id}/delete`,
    method: 'POST',
    data
  })

  return payload.data
}

export async function createMerchantChannelTestPay(id: number, data: Record<string, any> = {}) {
  const payload = await merchantRequest<MerchantChannelTestPayResponse>({
    url: `/My/channels/${id}/test-pay`,
    method: 'POST',
    data
  })

  return payload.data
}

export async function pollMerchantChannelTestPay(outTradeNo: string) {
  const payload = await merchantRequest<MerchantChannelTestPayResponse>({
    url: '/My/channels/test-pay/poll',
    method: 'GET',
    params: {
      out_trade_no: outTradeNo
    }
  })

  return payload.data
}

export async function fetchMerchantChannelBatchDeleteAudit(data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/My/channels/batch-delete-audit',
    method: 'POST',
    data
  })

  return payload.data
}

export async function batchDeleteMerchantChannels(data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/My/channels/batch-delete',
    method: 'POST',
    data
  })

  return payload.data
}

export async function fetchMerchantPools(params: Record<string, any> = {}) {
  const payload = await merchantRequest({
    url: '/My/pools',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function fetchMerchantPoolDetail(id: number) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/My/pools/${id}`,
    method: 'GET'
  })

  return payload.data
}

export async function createMerchantPool(data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/My/pools/create',
    method: 'POST',
    data
  })

  return payload.data
}

export async function updateMerchantPool(id: number, data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/My/pools/${id}/update`,
    method: 'POST',
    data
  })

  return payload.data
}

export async function updateMerchantPoolStatus(id: number, data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/My/pools/${id}/status`,
    method: 'POST',
    data
  })

  return payload.data
}

export async function fetchMerchantPoolChannelEditor(id: number) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/My/pools/${id}/channel-editor`,
    method: 'GET'
  })

  return payload.data
}

export async function saveMerchantPoolChannels(id: number, data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/My/pools/${id}/channels`,
    method: 'POST',
    data
  })

  return payload.data
}

export async function fetchMerchantPoolDeleteAudit(id: number) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/My/pools/${id}/delete-audit`,
    method: 'GET'
  })

  return payload.data
}

export async function deleteMerchantPool(id: number, data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/My/pools/${id}/delete`,
    method: 'POST',
    data
  })

  return payload.data
}
