/**
 * 琛ㄦ牸缂撳瓨绠＄悊妯″潡
 *
 * 鎻愪緵楂樻€ц兘鐨勮〃鏍兼暟鎹紦瀛樻満鍒?
 *
 * ## 涓昏鍔熻兘
 *
 * - 鍩轰簬鍙傛暟鐨勬櫤鑳界紦瀛橀敭鐢熸垚锛堜娇鐢?ohash锛?
 * - LRU锛堟渶杩戞渶灏戜娇鐢級缂撳瓨娣樻卑绛栫暐
 * - 缂撳瓨杩囨湡鏃堕棿绠＄悊
 * - 缂撳瓨澶у皬闄愬埗鍜岃嚜鍔ㄦ竻鐞?
 * - 鍩轰簬鏍囩鐨勭紦瀛樺垎缁勭鐞?
 * - 澶氱缂撳瓨澶辨晥绛栫暐锛堟竻绌烘墍鏈夈€佹竻绌哄綋鍓嶃€佹竻绌哄垎椤电瓑锛?
 * - 缂撳瓨璁块棶缁熻鍜屽懡涓巼鍒嗘瀽
 * - 缂撳瓨澶у皬浼扮畻
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 琛ㄦ牸鏁版嵁鐨勫垎椤电紦瀛?
 * - 鍑忓皯閲嶅鐨?API 璇锋眰
 * - 鎻愬崌琛ㄦ牸鍒囨崲鍜岃繑鍥炵殑鍝嶅簲閫熷害
 * - 鎼滅储鏉′欢鍙樺寲鏃剁殑鏅鸿兘缂撳瓨绠＄悊
 * - 鏁版嵁鏇存柊鍚庣殑缂撳瓨澶辨晥澶勭悊
 *
 * ## 缂撳瓨绛栫暐
 *
 * - CLEAR_ALL: 娓呯┖鎵€鏈夌紦瀛橈紙閫傜敤浜庡叏灞€鏁版嵁鏇存柊锛?
 * - CLEAR_CURRENT: 浠呮竻绌哄綋鍓嶆煡璇㈡潯浠剁殑缂撳瓨锛堥€傜敤浜庡崟鏉℃暟鎹洿鏂帮級
 * - CLEAR_PAGINATION: 娓呯┖鎵€鏈夊垎椤电紦瀛樹絾淇濈暀涓嶅悓鎼滅储鏉′欢锛堥€傜敤浜庢壒閲忔搷浣滐級
 * - KEEP_ALL: 涓嶆竻闄ょ紦瀛橈紙閫傜敤浜庡彧璇绘搷浣滐級
 *
 * @module utils/table/tableCache
 * @author AiPay
 */
import { hash } from 'ohash'

// 缂撳瓨澶辨晥绛栫暐鏋氫妇
export enum CacheInvalidationStrategy {
  /** 娓呯┖鎵€鏈夌紦瀛?*/
  CLEAR_ALL = 'clear_all',
  /** 浠呮竻绌哄綋鍓嶆煡璇㈡潯浠剁殑缂撳瓨 */
  CLEAR_CURRENT = 'clear_current',
  /** 娓呯┖鎵€鏈夊垎椤电紦瀛橈紙淇濈暀涓嶅悓鎼滅储鏉′欢鐨勭紦瀛橈級 */
  CLEAR_PAGINATION = 'clear_pagination',
  /** 涓嶆竻闄ょ紦瀛?*/
  KEEP_ALL = 'keep_all'
}

// 閫氱敤 API 鍝嶅簲鎺ュ彛锛堝吋瀹逛笉鍚岀殑鍚庣鍝嶅簲鏍煎紡锛?
export interface ApiResponse<T = unknown> {
  records?: T[]
  data?: T[]
  total?: number
  current?: number
  size?: number
  [key: string]: unknown
}

// 缂撳瓨瀛樺偍鎺ュ彛
export interface CacheItem<T> {
  data: T[]
  response: ApiResponse<T>
  timestamp: number
  params: string
  // 缂撳瓨鏍囩锛岀敤浜庡垎缁勭鐞?
  tags: Set<string>
  // 璁块棶娆℃暟锛堢敤浜?LRU 绠楁硶锛?
  accessCount: number
  // 鏈€鍚庤闂椂闂?
  lastAccessTime: number
}

// 澧炲己鐨勭紦瀛樼鐞嗙被
export class TableCache<T> {
  private cache = new Map<string, CacheItem<T>>()
  private cacheTime: number
  private maxSize: number
  private enableLog: boolean

  constructor(cacheTime = 5 * 60 * 1000, maxSize = 50, enableLog = false) {
    // 榛樿5鍒嗛挓锛屾渶澶?0鏉＄紦瀛?
    this.cacheTime = cacheTime
    this.maxSize = maxSize
    this.enableLog = enableLog
  }

  // 鍐呴儴鏃ュ織宸ュ叿
  private log(message: string, ...args: any[]) {
    if (this.enableLog) {
      console.log(`[TableCache] ${message}`, ...args)
    }
  }

  // 鐢熸垚绋冲畾鐨勭紦瀛橀敭
  private generateKey(params: unknown): string {
    return hash(params)
  }

  // 馃敡 浼樺寲锛氬寮虹被鍨嬪畨鍏ㄦ€?
  private generateTags(params: Record<string, unknown>): Set<string> {
    const tags = new Set<string>()

    // 娣诲姞鎼滅储鏉′欢鏍囩
    const searchKeys = Object.keys(params).filter(
      (key) =>
        !['current', 'size', 'total'].includes(key) &&
        params[key] !== undefined &&
        params[key] !== '' &&
        params[key] !== null
    )

    if (searchKeys.length > 0) {
      const searchTag = searchKeys.map((key) => `${key}:${String(params[key])}`).join('|')
      tags.add(`search:${searchTag}`)
    } else {
      tags.add('search:default')
    }

    // 娣诲姞鍒嗛〉鏍囩
    tags.add(`pagination:${params.size || 10}`)
    // 娣诲姞閫氱敤鍒嗛〉鏍囩锛岀敤浜庢竻鐞嗘墍鏈夊垎椤电紦瀛?
    tags.add('pagination')

    return tags
  }

  // 馃敡 浼樺寲锛歀RU 缂撳瓨娓呯悊
  private evictLRU(): void {
    if (this.cache.size <= this.maxSize) return

    // 鎵惧埌鏈€灏戜娇鐢ㄧ殑缂撳瓨椤?
    let lruKey = ''
    let minAccessCount = Infinity
    let oldestTime = Infinity

    for (const [key, item] of this.cache.entries()) {
      if (
        item.accessCount < minAccessCount ||
        (item.accessCount === minAccessCount && item.lastAccessTime < oldestTime)
      ) {
        lruKey = key
        minAccessCount = item.accessCount
        oldestTime = item.lastAccessTime
      }
    }

    if (lruKey) {
      this.cache.delete(lruKey)
      this.log(`LRU 娓呯悊缂撳瓨: ${lruKey}`)
    }
  }

  // 璁剧疆缂撳瓨
  set(params: unknown, data: T[], response: ApiResponse<T>): void {
    const key = this.generateKey(params)
    const tags = this.generateTags(params as Record<string, unknown>)
    const now = Date.now()

    // 妫€鏌ユ槸鍚﹂渶瑕佹竻鐞?
    this.evictLRU()

    this.cache.set(key, {
      data,
      response,
      timestamp: now,
      params: key,
      tags,
      accessCount: 1,
      lastAccessTime: now
    })
  }

  // 鑾峰彇缂撳瓨
  get(params: unknown): CacheItem<T> | null {
    const key = this.generateKey(params)
    const item = this.cache.get(key)

    if (!item) return null

    // 妫€鏌ユ槸鍚﹁繃鏈?
    if (Date.now() - item.timestamp > this.cacheTime) {
      this.cache.delete(key)
      return null
    }

    // 鏇存柊璁块棶缁熻
    item.accessCount++
    item.lastAccessTime = Date.now()

    return item
  }

  // 鏍规嵁鏍囩娓呴櫎缂撳瓨
  clearByTags(tags: string[]): number {
    let clearedCount = 0

    for (const [key, item] of this.cache.entries()) {
      // 妫€鏌ユ槸鍚﹀寘鍚换鎰忎竴涓爣绛?
      const hasMatchingTag = tags.some((tag) =>
        Array.from(item.tags).some((itemTag) => itemTag.includes(tag))
      )

      if (hasMatchingTag) {
        this.cache.delete(key)
        clearedCount++
      }
    }

    return clearedCount
  }

  // 娓呴櫎褰撳墠鎼滅储鏉′欢鐨勭紦瀛?
  clearCurrentSearch(params: unknown): number {
    const key = this.generateKey(params)
    const deleted = this.cache.delete(key)
    return deleted ? 1 : 0
  }

  // 娓呴櫎鍒嗛〉缂撳瓨
  clearPagination(): number {
    return this.clearByTags(['pagination'])
  }

  // 娓呯┖鎵€鏈夌紦瀛?
  clear(): void {
    this.cache.clear()
  }

  // 鑾峰彇缂撳瓨缁熻淇℃伅
  getStats(): { total: number; size: string; hitRate: string } {
    const total = this.cache.size
    let totalSize = 0
    let totalAccess = 0

    for (const item of this.cache.values()) {
      // 绮楃暐浼扮畻澶у皬锛圝SON瀛楃涓查暱搴︼級
      totalSize += JSON.stringify(item.data).length
      totalAccess += item.accessCount
    }

    // 杞崲涓轰汉绫诲彲璇荤殑澶у皬
    const sizeInKB = (totalSize / 1024).toFixed(2)
    const avgHits = total > 0 ? (totalAccess / total).toFixed(1) : '0'

    return {
      total,
      size: `${sizeInKB}KB`,
      hitRate: `${avgHits} avg hits`
    }
  }

  // 娓呯悊杩囨湡缂撳瓨
  cleanupExpired(): number {
    let cleanedCount = 0
    const now = Date.now()

    for (const [key, item] of this.cache.entries()) {
      if (now - item.timestamp > this.cacheTime) {
        this.cache.delete(key)
        cleanedCount++
      }
    }

    return cleanedCount
  }
}

