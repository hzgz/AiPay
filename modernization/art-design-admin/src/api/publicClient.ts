/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

import axios, { type AxiosRequestConfig } from 'axios'
import { resolveApiBaseUrl, resolveBackendOrigin } from '@/utils/http/base'

export interface PublicCompatEnvelope<T> {
  code?: number
  msg?: string
  message?: string
  data: T
}

export interface PublicCompatResponse<T> extends PublicCompatEnvelope<T> {
  code: number
  msg: string
  message: string
  status: number
}

export class PublicCompatError<T = any> extends Error {
  status: number
  code: number
  payload: null | PublicCompatResponse<T>

  constructor(message: string, status: number, code: number, payload: null | PublicCompatResponse<T>) {
    super(message)
    this.name = 'PublicCompatError'
    this.status = status
    this.code = code
    this.payload = payload
  }
}

const compatClient = axios.create({
  timeout: 15000,
  baseURL: resolveApiBaseUrl(),
  withCredentials: import.meta.env.VITE_WITH_CREDENTIALS === 'true',
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  },
  validateStatus: () => true
})

export async function publicCompatEnvelopeRequest<T>(
  config: AxiosRequestConfig,
  successCodes: number[] = [0, 200]
): Promise<PublicCompatResponse<T>> {
  const response = await compatClient.request<PublicCompatEnvelope<T> | string>({
    ...config,
    params: {
      format: 'json',
      ...(config.params || {})
    }
  })

  if (typeof response.data !== 'object' || response.data === null || Array.isArray(response.data)) {
    throw new PublicCompatError<T>(
      '公开接口没有返回有效的 JSON 数据',
      response.status,
      response.status || 400,
      null
    )
  }

  const payload = response.data
  const normalized: PublicCompatResponse<T> = {
    code: Number(payload.code ?? response.status ?? 400),
    msg: String(payload.msg || payload.message || ''),
    message: String(payload.message || payload.msg || ''),
    data: payload.data,
    status: response.status
  }

  const acceptedCodes = new Set(successCodes)
  const isSuccess =
    response.status >= 200 && response.status < 300 && acceptedCodes.has(normalized.code)

  if (isSuccess) {
    return normalized
  }

  throw new PublicCompatError<T>(
    normalized.message || normalized.msg || '公开接口请求失败',
    response.status,
    normalized.code,
    normalized
  )
}

export async function publicCompatRequest<T>(
  config: AxiosRequestConfig,
  successCodes: number[] = [0, 200]
): Promise<T> {
  const response = await publicCompatEnvelopeRequest<T>(config, successCodes)
  return response.data
}

export function resolvePublicBackendOrigin() {
  return resolveBackendOrigin()
}
