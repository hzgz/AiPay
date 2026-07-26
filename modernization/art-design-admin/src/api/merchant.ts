import axios, { type AxiosRequestConfig, type AxiosResponse } from 'axios'
import { resolveBackendOrigin } from '@/utils/http/base'
import { clearMerchantFrontToken, getMerchantFrontToken } from '@/utils/merchantSession'

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
  pay_amount_unit?: 'CNY' | 'USDT' | string
  base_amount?: string
  base_amount_unit?: 'CNY' | string
  expires_seconds?: number
  expires_at?: string | null
  expires_at_timestamp?: number | null
  exchange_rate?: null | string
  wallet_address?: null | string
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

  constructor(
    message: string,
    status: number,
    code: number,
    payload: MerchantApiResponse<any> | null
  ) {
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

merchantClient.interceptors.response.use((response) => {
  const payload = parseMerchantResponsePayload(response.data)
  if (payload !== null) {
    if (response.status === 401 || Number(payload.code) === 401) {
      clearMerchantFrontToken()
      redirectToMerchantLogin()
    }

    response.data = payload
    return response
  }

  const text = normalizeMerchantResponseText(response.data)
  const contentType = String(response.headers?.['content-type'] || '').toLowerCase()
  const looksLikeHtml = isHtmlDocumentPayload(text)
  const looksLikeUnauthorized =
    response.status === 401 ||
    response.status === 403 ||
    text.includes('请先登录商户账号') ||
    text.includes('merchant login is required') ||
    text.includes('/merchant/login') ||
    text.includes('/#/merchant/login')

  if (looksLikeUnauthorized) {
    clearMerchantFrontToken()
    redirectToMerchantLogin()
    response.status = 401
    response.data = {
      code: 401,
      msg: '请先登录商户账号',
      message: '请先登录商户账号',
      data: null
    }
    return response
  }

  const fallbackMessage =
    looksLikeHtml || contentType.includes('text/html')
      ? '商户接口返回了页面内容，请刷新后重试'
      : '商户接口未返回 JSON 数据'

  response.data = {
    code: response.status || 500,
    msg: fallbackMessage,
    message: fallbackMessage,
    data: null
  }
  return response
})

function normalizeMerchantResponseText(value: unknown): string {
  if (typeof value !== 'string') {
    return ''
  }

  return value.replace(/^\uFEFF/, '').trim()
}

function parseMerchantResponsePayload<T = any>(
  data: MerchantApiResponse<T> | string
): MerchantApiResponse<T> | null {
  if (typeof data === 'object' && data !== null && !Array.isArray(data)) {
    return data
  }

  const normalized = normalizeMerchantResponseText(data)
  if (normalized === '') {
    return null
  }

  try {
    const parsed = JSON.parse(normalized)
    if (typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed)) {
      return parsed as MerchantApiResponse<T>
    }
  } catch {
    // Ignore parse failures and let the caller surface a friendlier error.
  }

  return null
}

function isHtmlDocumentPayload(text: string): boolean {
  return /^<!doctype html/i.test(text) || /^<html[\s>]/i.test(text)
}

function merchantCurrentHashPath(): string {
  if (typeof window === 'undefined') {
    return '/merchant/dashboard'
  }

  const currentHash = String(window.location.hash || '')
    .replace(/^#/, '')
    .trim()
  return currentHash !== '' ? currentHash : '/merchant/dashboard'
}

function redirectToMerchantLogin() {
  if (typeof window === 'undefined') {
    return
  }

  const currentPath = merchantCurrentHashPath()
  if (currentPath.startsWith('/merchant/login')) {
    return
  }

  const loginPath = `/merchant/login?redirect=${encodeURIComponent(currentPath)}`
  window.location.hash = `#${loginPath}`
}

function createMerchantUnauthorizedError(message = '请先登录商户账号') {
  clearMerchantFrontToken()
  redirectToMerchantLogin()
  return new MerchantApiError(message, 401, 401, null)
}

function createMerchantNonJsonError(response: AxiosResponse<MerchantApiResponse<any> | string>) {
  const text = normalizeMerchantResponseText(response.data)
  const contentType = String(response.headers?.['content-type'] || '').toLowerCase()
  const looksLikeHtml = isHtmlDocumentPayload(text)
  const looksLikeUnauthorized =
    response.status === 401 ||
    response.status === 403 ||
    text.includes('请先登录商户账号') ||
    text.includes('merchant login is required') ||
    text.includes('/merchant/login') ||
    text.includes('/#/merchant/login')

  if (looksLikeUnauthorized) {
    return createMerchantUnauthorizedError()
  }

  if (looksLikeHtml || contentType.includes('text/html')) {
    return new MerchantApiError(
      '商户接口返回了页面内容，请刷新后重试',
      response.status,
      response.status || 500,
      null
    )
  }

  return new MerchantApiError(
    '商户接口未返回 JSON 数据',
    response.status,
    response.status || 500,
    null
  )
}

void createMerchantNonJsonError

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
  const isSuccess =
    response.status >= 200 && response.status < 300 && acceptedCodes.has(Number(payload.code))

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

function normalizeCollection<T = Record<string, any>>(
  payload: MerchantApiResponse<any>
): MerchantCollectionResult<T> {
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
    url: '/api/merchant/login',
    method: 'POST',
    data: {
      username,
      password
    }
  })

  return payload.data
}

export async function merchantLogout() {
  await merchantClient.post('/api/merchant/logout', null, {
    params: {
      format: 'json'
    }
  })
}

export async function fetchMerchantDashboard() {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/api/merchant/dashboard',
    method: 'GET'
  })

  return payload.data
}

export async function fetchMerchantProfile() {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/api/merchant/profile',
    method: 'GET'
  })

  return payload.data
}

export async function updateMerchantProfile(data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/api/merchant/profile',
      method: 'POST',
      data
    },
    [1, 200]
  )

  return payload.data
}

export async function fetchMerchantNotifications() {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/api/merchant/notifications',
    method: 'GET'
  })

  return payload.data
}

export async function updateMerchantNotifications(data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/api/merchant/notifications',
    method: 'POST',
    data
  })

  return payload.data
}

export async function fetchMerchantConnections() {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/api/merchant/connections',
    method: 'GET'
  })

  return payload.data
}

export async function fetchMerchantWxPusherQrCode() {
  const payload = await merchantRequest<MerchantWxPusherQrPayload>(
    {
      url: '/api/merchant/connections/wxpusher/qrcode',
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
      url: '/api/merchant/connections/wxpusher/status',
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
      url:
        mode === 'bind'
          ? '/api/merchant/connections/bind-code'
          : '/api/merchant/connections/unbind-code',
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
      url: '/api/merchant/connections/email',
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
      url: '/api/merchant/connections/mobile',
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
      url: '/api/merchant/connections/unbind',
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
      url: '/api/merchant/connections/wxpusher',
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
      url: '/api/merchant/connections/telegram',
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
    url: '/api/merchant/security',
    method: 'GET'
  })

  return payload.data
}

export async function fetchMerchantGoogleAuthQrCode() {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/api/merchant/security/google-auth/qrcode',
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
      url: '/api/merchant/security/google-auth/bind',
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
      url: '/api/merchant/security/google-auth/unbind',
      method: 'POST',
      data: { code }
    },
    [200]
  )

  return payload.data
}

export async function updateMerchantPassword(newPassword: string, confirmPassword: string) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/api/merchant/security/password',
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
      url: '/api/merchant/security/cancellation',
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
    url: '/api/merchant/security/real-name',
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
      url: '/api/merchant/security/real-name',
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
      url: '/api/merchant/security/real-name/status',
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
    url: '/api/merchant/affiliate',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function fetchMerchantOrders(params: Record<string, any> = {}) {
  const payload = await merchantRequest({
    url: '/api/merchant/orders',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function fetchMerchantOrderDetail(id: number) {
  const payload = await merchantRequest<Record<string, any> & { dataArray?: Record<string, any> }>({
    url: '/api/merchant/orders/detail',
    method: 'GET',
    params: { id }
  })

  return payload.dataArray || payload.data
}

export async function replayMerchantOrderCallback(id: number) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/api/merchant/orders/callback-replay',
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
    url: '/api/merchant/money-logs',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function fetchMerchantRecharges(params: Record<string, any> = {}) {
  const payload = await merchantRequest({
    url: '/api/merchant/recharges',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function createMerchantRecharge(data: Record<string, any>) {
  return merchantRequest<Record<string, any>>({
    url: '/api/merchant/recharges',
    method: 'POST',
    data
  })
}

export async function redeemMerchantCdk(code: string) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/api/merchant/recharges/cdk',
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
    url: '/api/merchant/vips',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function purchaseMerchantVipPackage(vipId: number) {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/api/merchant/vips',
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
    url: '/api/merchant/api',
    method: 'GET'
  })

  return payload.data
}

export async function fetchMerchantApiSecret(keyType: 'sign_key' | 'appkey') {
  const payload = await merchantRequest<Record<string, any>>(
    {
      url: '/api/merchant/api/secret',
      method: 'POST',
      data: {
        key_type: keyType
      }
    },
    [200]
  )

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
      url: '/api/merchant/api/qrcode',
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
      url: '/api/merchant/api/sign-key/reset',
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
      url: '/api/merchant/api/app-key/reset',
      method: 'POST',
      data: {}
    },
    [1, 200]
  )

  return payload.data
}

export async function fetchMerchantTickets(params: Record<string, any> = {}) {
  const payload = await merchantRequest({
    url: '/api/merchant/tickets',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function createMerchantTicket(data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/api/merchant/tickets',
    method: 'POST',
    data
  })

  return payload.data
}

export async function deleteMerchantTicket(id: number) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/api/merchant/tickets/delete',
    method: 'POST',
    data: { id }
  })

  return payload.data
}

export async function fetchMerchantDomains(params: Record<string, any> = {}) {
  const payload = await merchantRequest({
    url: '/api/merchant/domains',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function createMerchantDomain(data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/api/merchant/domains',
    method: 'POST',
    data
  })

  return payload.data
}

export async function updateMerchantDomain(data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/api/merchant/domains/update',
    method: 'POST',
    data
  })

  return payload.data
}

export async function deleteMerchantDomain(id: number) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/api/merchant/domains/delete',
    method: 'POST',
    data: { id }
  })

  return payload.data
}

export async function fetchMerchantLoginLogs(params: Record<string, any> = {}) {
  const payload = await merchantRequest({
    url: '/api/merchant/login-logs',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function fetchMerchantChannels(params: Record<string, any> = {}) {
  const payload = await merchantRequest({
    url: '/api/merchant/channels',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function fetchMerchantChannelDetail(id: number) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/api/merchant/channels/${id}`,
    method: 'GET'
  })

  return payload.data
}

export async function createMerchantChannel(data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/api/merchant/channels',
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
    url: '/api/merchant/channels/credential-image',
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
    url: '/api/merchant/channels/credential-decode',
    method: 'POST',
    data: formData
  })

  return response.data
}

export async function updateMerchantChannel(id: number, data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/api/merchant/channels/${id}/update`,
    method: 'POST',
    data
  })

  return payload.data
}

export async function updateMerchantChannelCredentials(id: number, data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/api/merchant/channels/${id}/credentials`,
    method: 'POST',
    data
  })

  return payload.data
}

export async function updateMerchantChannelStatus(id: number, data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/api/merchant/channels/${id}/status`,
    method: 'POST',
    data
  })

  return payload.data
}

export async function fetchMerchantChannelDeleteAudit(id: number) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/api/merchant/channels/${id}/delete-audit`,
    method: 'GET'
  })

  return payload.data
}

export async function deleteMerchantChannel(id: number, data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/api/merchant/channels/${id}/delete`,
    method: 'POST',
    data
  })

  return payload.data
}

export async function createMerchantChannelTestPay(id: number, data: Record<string, any> = {}) {
  const payload = await merchantRequest<MerchantChannelTestPayResponse>({
    url: `/api/merchant/channels/${id}/test`,
    method: 'POST',
    data
  })

  return payload.data
}

export async function pollMerchantChannelTestPay(outTradeNo: string) {
  const payload = await merchantRequest<MerchantChannelTestPayResponse>({
    url: '/api/merchant/channels/test/poll',
    method: 'GET',
    params: {
      out_trade_no: outTradeNo
    }
  })

  return payload.data
}

export async function fetchMerchantChannelBatchDeleteAudit(data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/api/merchant/channels/batch-delete-audit',
    method: 'POST',
    data
  })

  return payload.data
}

export async function batchDeleteMerchantChannels(data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/api/merchant/channels/batch-delete',
    method: 'POST',
    data
  })

  return payload.data
}

export async function fetchMerchantPools(params: Record<string, any> = {}) {
  const payload = await merchantRequest({
    url: '/api/merchant/pools',
    method: 'GET',
    params
  })

  return normalizeCollection(payload)
}

export async function fetchMerchantPoolDetail(id: number) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/api/merchant/pools/${id}`,
    method: 'GET'
  })

  return payload.data
}

export async function createMerchantPool(data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: '/api/merchant/pools',
    method: 'POST',
    data
  })

  return payload.data
}

export async function updateMerchantPool(id: number, data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/api/merchant/pools/${id}/update`,
    method: 'POST',
    data
  })

  return payload.data
}

export async function updateMerchantPoolStatus(id: number, data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/api/merchant/pools/${id}/status`,
    method: 'POST',
    data
  })

  return payload.data
}

export async function fetchMerchantPoolChannelEditor(id: number) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/api/merchant/pools/${id}/channel-editor`,
    method: 'GET'
  })

  return payload.data
}

export async function saveMerchantPoolChannels(id: number, data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/api/merchant/pools/${id}/channels`,
    method: 'POST',
    data
  })

  return payload.data
}

export async function fetchMerchantPoolDeleteAudit(id: number) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/api/merchant/pools/${id}/delete-audit`,
    method: 'GET'
  })

  return payload.data
}

export async function deleteMerchantPool(id: number, data: Record<string, any>) {
  const payload = await merchantRequest<Record<string, any>>({
    url: `/api/merchant/pools/${id}/delete`,
    method: 'POST',
    data
  })

  return payload.data
}
