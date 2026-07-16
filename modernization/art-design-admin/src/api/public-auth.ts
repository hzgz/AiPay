import {
  publicCompatEnvelopeRequest,
  resolvePublicBackendOrigin,
  type PublicCompatResponse
} from './public-client'

export interface PublicSoftwareConfigPayload {
  name: string
  login_type: number
  register_type: number
  captcha_type: number
}

const DEFAULT_PUBLIC_SOFTWARE_CONFIG: PublicSoftwareConfigPayload = {
  name: 'AiPay',
  login_type: 0,
  register_type: 0,
  captcha_type: 0
}

export interface PublicRegisterSubmitPayload {
  username: string
  password: string
  password2: string
  email?: string
  mobile?: string
  tg_chat_id?: string
  captcha?: string
  ordinary_captcha?: string
  superior_id?: string | number
}

export interface PublicRegisterPendingPaymentPayload {
  paytype?: Array<{ name?: string; showname?: string }>
  need?: string
  trade_no?: string
  [key: string]: any
}

export function createDefaultPublicSoftwareConfig(): PublicSoftwareConfigPayload {
  return { ...DEFAULT_PUBLIC_SOFTWARE_CONFIG }
}

export async function fetchPublicSoftwareConfig() {
  try {
    return await publicCompatEnvelopeRequest<PublicSoftwareConfigPayload>({
      url: '/api/getSoftwareConfig',
      method: 'GET'
    })
  } catch (primaryError) {
    const backendOrigin = resolvePublicBackendOrigin()
    const fallbackBase = backendOrigin || ''
    const fallbackUrl = `${fallbackBase}/api/getSoftwareConfig?format=json`
    const response = await fetch(fallbackUrl, {
      method: 'GET',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: import.meta.env.VITE_WITH_CREDENTIALS === 'true' ? 'include' : 'same-origin'
    }).catch(() => null)

    if (!response) {
      throw primaryError
    }

    const payload = await response.json().catch(() => null)
    if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
      throw primaryError
    }

    const code = Number((payload as { code?: number }).code ?? response.status ?? 400)
    if (!(response.status >= 200 && response.status < 300 && [0, 200].includes(code))) {
      throw primaryError
    }

    const data = (payload as { data?: PublicSoftwareConfigPayload }).data
    return {
      code,
      msg: String((payload as { msg?: string; message?: string }).msg || (payload as { message?: string }).message || ''),
      message: String((payload as { message?: string; msg?: string }).message || (payload as { msg?: string }).msg || ''),
      data: {
        ...createDefaultPublicSoftwareConfig(),
        ...(data || {})
      },
      status: response.status
    }
  }
}

export function sendPublicRegisterCode(payload: {
  email?: string
  mobile?: string
  tg_chat_id?: string
}) {
  return publicCompatEnvelopeRequest<Record<string, any>>({
    url: '/api/getCode',
    method: 'POST',
    data: {
      type: 'register',
      ...payload
    }
  })
}

export function submitPublicRegister(payload: PublicRegisterSubmitPayload) {
  return publicCompatEnvelopeRequest<PublicRegisterPendingPaymentPayload | Record<string, any>>(
    {
      url: '/api/register',
      method: 'POST',
      data: payload
    },
    [200, 888]
  )
}

export function buildPublicCaptchaUrl() {
  return `${resolvePublicBackendOrigin()}/api/getCaptcha?_=${Date.now()}`
}

export type PublicAuthResponse<T> = PublicCompatResponse<T>
