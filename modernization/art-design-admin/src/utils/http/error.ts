/**
 * HTTP 閿欒澶勭悊妯″潡
 *
 * 鎻愪緵缁熶竴鐨?HTTP 璇锋眰閿欒澶勭悊鏈哄埗
 *
 * ## 涓昏鍔熻兘
 *
 * - 鑷畾涔?HttpError 閿欒绫伙紝灏佽閿欒淇℃伅銆佺姸鎬佺爜銆佹椂闂存埑绛?
 * - 閿欒鎷︽埅鍜岃浆鎹紝灏?Axios 閿欒杞崲涓烘爣鍑嗙殑 HttpError
 * - 閿欒娑堟伅鍥介檯鍖栧鐞嗭紝鏍规嵁鐘舵€佺爜杩斿洖瀵瑰簲鐨勫璇█閿欒鎻愮ず
 * - 閿欒鏃ュ織璁板綍锛屼究浜庨棶棰樿拷韪拰璋冭瘯
 * - 閿欒鍜屾垚鍔熸秷鎭殑缁熶竴灞曠ず
 * - 绫诲瀷瀹堝崼鍑芥暟锛岀敤浜庡垽鏂敊璇被鍨?
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - HTTP 璇锋眰鎷︽埅鍣ㄤ腑缁熶竴澶勭悊閿欒
 * - 涓氬姟浠ｇ爜涓崟鑾峰拰澶勭悊鐗瑰畾閿欒
 * - 閿欒鏃ュ織鏀堕泦鍜屼笂鎶?
 *
 * @module utils/http/error
 * @author AiPay
 */
import { AxiosError } from 'axios'
import { ApiStatus } from './status'
import { $t } from '@/locales'

// 閿欒鍝嶅簲鎺ュ彛
export interface ErrorResponse {
  /** 閿欒鐘舵€佺爜 */
  code: number
  /** 閿欒娑堟伅 */
  msg: string
  /** 閿欒闄勫姞鏁版嵁 */
  data?: unknown
}

// 閿欒鏃ュ織鏁版嵁鎺ュ彛
export interface ErrorLogData {
  /** 閿欒鐘舵€佺爜 */
  code: number
  /** 閿欒娑堟伅 */
  message: string
  /** 閿欒闄勫姞鏁版嵁 */
  data?: unknown
  /** 閿欒鍙戠敓鏃堕棿鎴?*/
  timestamp: string
  /** 璇锋眰 URL */
  url?: string
  /** 璇锋眰鏂规硶 */
  method?: string
  /** 閿欒鍫嗘爤淇℃伅 */
  stack?: string
}

// 鑷畾涔?HttpError 绫?
export class HttpError extends Error {
  public readonly code: number
  public readonly data?: unknown
  public readonly timestamp: string
  public readonly url?: string
  public readonly method?: string

  constructor(
    message: string,
    code: number,
    options?: {
      data?: unknown
      url?: string
      method?: string
    }
  ) {
    super(message)
    this.name = 'HttpError'
    this.code = code
    this.data = options?.data
    this.timestamp = new Date().toISOString()
    this.url = options?.url
    this.method = options?.method
  }

  public toLogData(): ErrorLogData {
    return {
      code: this.code,
      message: this.message,
      data: this.data,
      timestamp: this.timestamp,
      url: this.url,
      method: this.method,
      stack: this.stack
    }
  }
}

/**
 * 鑾峰彇閿欒娑堟伅
 * @param status 閿欒鐘舵€佺爜
 * @returns 閿欒娑堟伅
 */
const getErrorMessage = (status: number): string => {
  const errorMap: Record<number, string> = {
    [ApiStatus.unauthorized]: 'httpMsg.unauthorized',
    [ApiStatus.forbidden]: 'httpMsg.forbidden',
    [ApiStatus.notFound]: 'httpMsg.notFound',
    [ApiStatus.methodNotAllowed]: 'httpMsg.methodNotAllowed',
    [ApiStatus.requestTimeout]: 'httpMsg.requestTimeout',
    [ApiStatus.internalServerError]: 'httpMsg.internalServerError',
    [ApiStatus.badGateway]: 'httpMsg.badGateway',
    [ApiStatus.serviceUnavailable]: 'httpMsg.serviceUnavailable',
    [ApiStatus.gatewayTimeout]: 'httpMsg.gatewayTimeout'
  }

  return $t(errorMap[status] || 'httpMsg.internalServerError')
}

/**
 * 澶勭悊閿欒
 * @param error 閿欒瀵硅薄
 * @returns 閿欒瀵硅薄
 */
export function handleError(error: AxiosError<ErrorResponse>): never {
  // 澶勭悊鍙栨秷鐨勮姹?
  if (error.code === 'ERR_CANCELED') {
    console.warn('Request cancelled:', error.message)
    throw new HttpError($t('httpMsg.requestCancelled'), ApiStatus.error)
  }

  const statusCode = error.response?.status
  const errorMessage = error.response?.data?.msg || error.message
  const requestConfig = error.config

  // 澶勭悊缃戠粶閿欒
  if (!error.response) {
    throw new HttpError($t('httpMsg.networkError'), ApiStatus.error, {
      url: requestConfig?.url,
      method: requestConfig?.method?.toUpperCase()
    })
  }

  // 澶勭悊 HTTP 鐘舵€佺爜閿欒
  const message = statusCode
    ? getErrorMessage(statusCode)
    : errorMessage || $t('httpMsg.requestFailed')
  throw new HttpError(message, statusCode || ApiStatus.error, {
    data: error.response.data,
    url: requestConfig?.url,
    method: requestConfig?.method?.toUpperCase()
  })
}

/**
 * 鏄剧ず閿欒娑堟伅
 * @param error 閿欒瀵硅薄
 * @param showMessage 鏄惁鏄剧ず閿欒娑堟伅
 */
export function showError(error: HttpError, showMessage: boolean = true): void {
  if (showMessage) {
    ElMessage.error(error.message)
  }
  // 璁板綍閿欒鏃ュ織
  console.error('[HTTP Error]', error.toLogData())
}

/**
 * 鏄剧ず鎴愬姛娑堟伅
 * @param message 鎴愬姛娑堟伅
 * @param showMessage 鏄惁鏄剧ず娑堟伅
 */
export function showSuccess(message: string, showMessage: boolean = true): void {
  if (showMessage) {
    ElMessage.success(message)
  }
}

/**
 * 鍒ゆ柇鏄惁涓?HttpError 绫诲瀷
 * @param error 閿欒瀵硅薄
 * @returns 鏄惁涓?HttpError 绫诲瀷
 */
export const isHttpError = (error: unknown): error is HttpError => {
  return error instanceof HttpError
}

