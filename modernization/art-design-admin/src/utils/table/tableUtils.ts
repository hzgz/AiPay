/**
 * 琛ㄦ牸宸ュ叿鍑芥暟妯″潡
 *
 * 鎻愪緵琛ㄦ牸鏁版嵁澶勭悊鍜岃姹傜鐞嗙殑鏍稿績宸ュ叿鍑芥暟
 *
 * ## 涓昏鍔熻兘
 *
 * - 澶氭牸寮?API 鍝嶅簲鑷姩閫傞厤鍜屾爣鍑嗗寲
 * - 琛ㄦ牸鏁版嵁鎻愬彇鍜岃浆鎹?
 * - 鍒嗛〉淇℃伅鑷姩鏇存柊鍜屾牎楠?
 * - 鏅鸿兘闃叉姈鍑芥暟锛堟敮鎸佸彇娑堝拰绔嬪嵆鎵ц锛?
 * - 缁熶竴鐨勯敊璇鐞嗘満鍒?
 * - 宓屽鏁版嵁缁撴瀯瑙ｆ瀽
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - useTable 缁勫悎寮忓嚱鏁扮殑搴曞眰宸ュ叿
 * - 閫傞厤鍚勭鍚庣鎺ュ彛鍝嶅簲鏍煎紡
 * - 琛ㄦ牸鏁版嵁鐨勬爣鍑嗗寲澶勭悊
 * - 璇锋眰闃叉姈鍜屾€ц兘浼樺寲
 * - 閿欒缁熶竴澶勭悊鍜屾棩蹇楄褰?
 *
 * ## 鏀寔鐨勫搷搴旀牸寮?
 *
 * 1. 鐩存帴鏁扮粍: [item1, item2, ...]
 * 2. 鏍囧噯瀵硅薄: { records: [], total: 100 }
 * 3. 宓屽data: { data: { list: [], total: 100 } }
 * 4. 澶氱瀛楁鍚? list/data/records/items/result/rows
 *
 * ## 鏍稿績鍔熻兘
 *
 * - defaultResponseAdapter: 鏅鸿兘璇嗗埆鍜岃浆鎹㈠搷搴旀牸寮?
 * - extractTableData: 鎻愬彇琛ㄦ牸鏁版嵁鏁扮粍
 * - updatePaginationFromResponse: 鏇存柊鍒嗛〉淇℃伅
 * - createSmartDebounce: 鍒涘缓鍙帶鐨勯槻鎶栧嚱鏁?
 * - createErrorHandler: 鐢熸垚閿欒澶勭悊鍣?
 *
 * @module utils/table/tableUtils
 * @author AiPay
 */

import type { ApiResponse } from './tableCache'
import { tableConfig } from './tableConfig'

// 璇锋眰鍙傛暟鍩虹鎺ュ彛锛屾墿灞曞垎椤靛弬鏁?
export interface BaseRequestParams extends Api.Common.PaginationParams {
  [key: string]: unknown
}

// 閿欒澶勭悊鎺ュ彛
export interface TableError {
  code: string
  message: string
  details?: unknown
}

// 杈呭姪鍑芥暟锛氫粠瀵硅薄涓彁鍙栬褰曟暟缁?
function extractRecords<T>(obj: Record<string, unknown>, fields: string[]): T[] {
  for (const field of fields) {
    if (field in obj && Array.isArray(obj[field])) {
      return obj[field] as T[]
    }
  }
  return []
}

// 杈呭姪鍑芥暟锛氫粠瀵硅薄涓彁鍙栨€绘暟
function extractTotal(obj: Record<string, unknown>, records: unknown[], fields: string[]): number {
  for (const field of fields) {
    if (field in obj && typeof obj[field] === 'number') {
      return obj[field] as number
    }
  }
  return records.length
}

// 杈呭姪鍑芥暟锛氭彁鍙栧垎椤靛弬鏁?
function extractPagination(
  obj: Record<string, unknown>,
  data?: Record<string, unknown>
): Pick<ApiResponse<unknown>, 'current' | 'size'> | undefined {
  const result: Partial<Pick<ApiResponse<unknown>, 'current' | 'size'>> = {}
  const sources = [obj, data ?? {}]

  const currentFields = tableConfig.currentFields
  for (const src of sources) {
    for (const field of currentFields) {
      if (field in src && typeof src[field] === 'number') {
        result.current = src[field] as number
        break
      }
    }
    if (result.current !== undefined) break
  }

  const sizeFields = tableConfig.sizeFields
  for (const src of sources) {
    for (const field of sizeFields) {
      if (field in src && typeof src[field] === 'number') {
        result.size = src[field] as number
        break
      }
    }
    if (result.size !== undefined) break
  }

  if (result.current === undefined && result.size === undefined) return undefined
  return result
}

/**
 * 榛樿鍝嶅簲閫傞厤鍣?- 鏀寔澶氱甯歌鐨凙PI鍝嶅簲鏍煎紡
 */
export const defaultResponseAdapter = <T>(response: unknown): ApiResponse<T> => {
  // 瀹氫箟鏀寔鐨勫瓧娈?
  const recordFields = tableConfig.recordFields

  if (!response) {
    return { records: [], total: 0 }
  }

  if (Array.isArray(response)) {
    return { records: response, total: response.length }
  }

  if (typeof response !== 'object') {
    console.warn(
      '[tableUtils] 无法识别的响应格式，支持数组、包含 ' +
        recordFields.join('/') +
        ' 字段的对象，或嵌套 data 对象。',
      response
    )
    return { records: [], total: 0 }
  }

  const res = response as Record<string, unknown>
  let records: T[] = []
  let total = 0
  let pagination: Pick<ApiResponse<unknown>, 'current' | 'size'> | undefined

  // 澶勭悊鏍囧噯鏍煎紡鎴栫洿鎺ュ垪琛?
  records = extractRecords(res, recordFields)
  total = extractTotal(res, records, tableConfig.totalFields)
  pagination = extractPagination(res)

  // 濡傛灉娌℃湁鎵惧埌锛屾鏌ュ祵濂梔ata
  if (records.length === 0 && 'data' in res && typeof res.data === 'object') {
    const data = res.data as Record<string, unknown>
    records = extractRecords(data, ['list', 'records', 'items'])
    total = extractTotal(data, records, tableConfig.totalFields)
    pagination = extractPagination(res, data)

    if (Array.isArray(res.data)) {
      records = res.data as T[]
      total = records.length
    }
  }

  if (!recordFields.some((field) => field in res) && records.length === 0) {
    console.warn('[tableUtils] 无法识别的响应格式')
    console.warn('支持的字段包括: ' + recordFields.join('、'), response)
    console.warn('如需扩展字段，请到 utils/table/tableConfig 文件中配置')
  }

  const result: ApiResponse<T> = { records, total }
  if (pagination) {
    Object.assign(result, pagination)
  }
  return result
}

/**
 * 浠庢爣鍑嗗寲鐨凙PI鍝嶅簲涓彁鍙栬〃鏍兼暟鎹?
 */
export const extractTableData = <T>(response: ApiResponse<T>): T[] => {
  const data = response.records || response.data || []
  return Array.isArray(data) ? data : []
}

/**
 * 鏍规嵁API鍝嶅簲鏇存柊鍒嗛〉淇℃伅
 */
export const updatePaginationFromResponse = <T>(
  pagination: Api.Common.PaginationParams,
  response: ApiResponse<T>
): void => {
  pagination.total = response.total ?? pagination.total ?? 0

  if (response.current !== undefined) {
    pagination.current = response.current
  }

  const maxPage = Math.max(1, Math.ceil(pagination.total / (pagination.size || 1)))
  if (pagination.current > maxPage) {
    pagination.current = maxPage
  }
}

/**
 * 鍒涘缓鏅鸿兘闃叉姈鍑芥暟 - 鏀寔鍙栨秷鍜岀珛鍗虫墽琛?
 */
export const createSmartDebounce = <T extends (...args: any[]) => Promise<any>>(
  fn: T,
  delay: number
): T & { cancel: () => void; flush: () => Promise<any> } => {
  let timeoutId: NodeJS.Timeout | null = null
  let lastArgs: Parameters<T> | null = null
  let lastResolve: ((value: any) => void) | null = null
  let lastReject: ((reason: any) => void) | null = null

  const debouncedFn = (...args: Parameters<T>): Promise<any> => {
    return new Promise((resolve, reject) => {
      if (timeoutId) clearTimeout(timeoutId)
      lastArgs = args
      lastResolve = resolve
      lastReject = reject
      timeoutId = setTimeout(async () => {
        try {
          const result = await fn(...args)
          resolve(result)
        } catch (error) {
          reject(error)
        } finally {
          timeoutId = null
          lastArgs = null
          lastResolve = null
          lastReject = null
        }
      }, delay)
    })
  }

  debouncedFn.cancel = () => {
    if (timeoutId) clearTimeout(timeoutId)
    timeoutId = null
    lastArgs = null
    lastResolve = null
    lastReject = null
  }

  debouncedFn.flush = async () => {
    if (timeoutId && lastArgs && lastResolve && lastReject) {
      clearTimeout(timeoutId)
      timeoutId = null
      const args = lastArgs
      const resolve = lastResolve
      const reject = lastReject
      lastArgs = null
      lastResolve = null
      lastReject = null
      try {
        const result = await fn(...args)
        resolve(result)
        return result
      } catch (error) {
        reject(error)
        throw error
      }
    }
    return Promise.resolve()
  }

  return debouncedFn as any
}

/**
 * 鐢熸垚閿欒澶勭悊鍑芥暟
 */
export const createErrorHandler = (
  onError?: (error: TableError) => void,
  enableLog: boolean = false
) => {
  const logger = {
    error: (message: string, ...args: any[]) => {
      if (enableLog) console.error(`[useTable] ${message}`, ...args)
    }
  }

  return (err: unknown, context: string): TableError => {
    const tableError: TableError = {
      code: 'UNKNOWN_ERROR',
      message: '鏈煡閿欒',
      details: err
    }

    if (err instanceof Error) {
      tableError.message = err.message
      tableError.code = err.name
    } else if (typeof err === 'string') {
      tableError.message = err
    }

    logger.error(`${context}:`, err)
    onError?.(tableError)
    return tableError
  }
}

