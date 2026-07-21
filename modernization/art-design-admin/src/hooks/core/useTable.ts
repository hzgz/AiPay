/**
 * useTable - 浼佷笟绾ц〃鏍兼暟鎹鐞嗘柟妗?
 *
 * 鍔熻兘瀹屾暣鐨勮〃鏍兼暟鎹鐞嗚В鍐虫柟妗堬紝涓撲负鍚庡彴绠＄悊绯荤粺璁捐銆?
 * 灏佽浜嗚〃鏍煎紑鍙戜腑鐨勬墍鏈夊父瑙侀渶姹傦紝璁╀綘涓撴敞浜庝笟鍔￠€昏緫銆?
 *
 * ## 涓昏鍔熻兘
 *
 * 1. 鏁版嵁绠＄悊 - 鑷姩澶勭悊 API 璇锋眰銆佸搷搴旇浆鎹€佸姞杞界姸鎬佸拰閿欒澶勭悊
 * 2. 鍒嗛〉鎺у埗 - 鑷姩鍚屾鍒嗛〉鐘舵€併€佺Щ鍔ㄧ閫傞厤銆佹櫤鑳介〉鐮佽竟鐣屽鐞?
 * 3. 鎼滅储鍔熻兘 - 闃叉姈鎼滅储浼樺寲銆佸弬鏁扮鐞嗐€佷竴閿噸缃€佸弬鏁拌繃婊?
 * 4. 缂撳瓨绯荤粺 - 鏅鸿兘璇锋眰缂撳瓨銆佸绉嶆竻鐞嗙瓥鐣ャ€佽嚜鍔ㄨ繃鏈熺鐞嗐€佺粺璁′俊鎭?
 * 5. 鍒锋柊绛栫暐 - 鎻愪緵 5 绉嶅埛鏂版柟娉曢€傞厤涓嶅悓涓氬姟鍦烘櫙锛堟柊澧?鏇存柊/鍒犻櫎/鎵嬪姩/瀹氭椂锛?
 * 6. 鍒楅厤缃鐞?- 鍔ㄦ€佹樉绀?闅愯棌鍒椼€佸垪鎺掑簭銆侀厤缃寔涔呭寲銆佹壒閲忔搷浣滐紙鍙€夛級
 *
 * @module useTable
 * @author AiPay
 */

import { ref, reactive, computed, onMounted, onUnmounted, nextTick, readonly } from 'vue'
import { useWindowSize } from '@vueuse/core'
import { useTableColumns } from './useTableColumns'
import type { ColumnOption } from '@/types/component'
import {
  TableCache,
  CacheInvalidationStrategy,
  type ApiResponse
} from '../../utils/table/tableCache'
import {
  type TableError,
  defaultResponseAdapter,
  extractTableData,
  updatePaginationFromResponse,
  createSmartDebounce,
  createErrorHandler
} from '../../utils/table/tableUtils'
import { tableConfig } from '../../utils/table/tableConfig'

// 绫诲瀷鎺ㄥ宸ュ叿绫诲瀷
type InferApiParams<T> = T extends (params: infer P) => any ? P : never
type InferApiResponse<T> = T extends (params: any) => Promise<infer R> ? R : never
type InferRecordType<T> = T extends Api.Common.PaginatedResponse<infer U> ? U : never

// 浼樺寲鐨勯厤缃帴鍙?- 鏀寔鑷姩绫诲瀷鎺ㄥ
export interface UseTableConfig<
  TApiFn extends (params: any) => Promise<any> = (params: any) => Promise<any>,
  TRecord = InferRecordType<InferApiResponse<TApiFn>>,
  TParams = InferApiParams<TApiFn>,
  TResponse = InferApiResponse<TApiFn>
> {
  // 鏍稿績閰嶇疆
  core: {
    /** API 璇锋眰鍑芥暟 */
    apiFn: TApiFn
    /** 榛樿璇锋眰鍙傛暟 */
    apiParams?: Partial<TParams>
    /** 鎺掗櫎 apiParams 涓殑灞炴€?*/
    excludeParams?: string[]
    /** 鏄惁绔嬪嵆鍔犺浇鏁版嵁 */
    immediate?: boolean
    /** 鍒楅厤缃伐鍘傚嚱鏁?*/
    columnsFactory?: () => ColumnOption<TRecord>[]
    /** 鑷畾涔夊垎椤靛瓧娈垫槧灏?*/
    paginationKey?: {
      /** 褰撳墠椤电爜瀛楁鍚嶏紝榛樿涓?'current' */
      current?: string
      /** 姣忛〉鏉℃暟瀛楁鍚嶏紝榛樿涓?'size' */
      size?: string
    }
  }

  // 鏁版嵁澶勭悊
  transform?: {
    /** 鏁版嵁杞崲鍑芥暟 */
    dataTransformer?: (data: TRecord[]) => TRecord[]
    /** 鍝嶅簲鏁版嵁閫傞厤鍣?*/
    responseAdapter?: (response: TResponse) => ApiResponse<TRecord>
  }

  // 鎬ц兘浼樺寲
  performance?: {
    /** 鏄惁鍚敤缂撳瓨 */
    enableCache?: boolean
    /** 缂撳瓨鏃堕棿锛堟绉掞級 */
    cacheTime?: number
    /** 闃叉姈寤惰繜鏃堕棿锛堟绉掞級 */
    debounceTime?: number
    /** 鏈€澶х紦瀛樻潯鏁伴檺鍒?*/
    maxCacheSize?: number
  }

  // 鐢熷懡鍛ㄦ湡閽╁瓙
  hooks?: {
    /** 鏁版嵁鍔犺浇鎴愬姛鍥炶皟锛堜粎缃戠粶璇锋眰鎴愬姛鏃惰Е鍙戯級 */
    onSuccess?: (data: TRecord[], response: ApiResponse<TRecord>) => void
    /** 閿欒澶勭悊鍥炶皟 */
    onError?: (error: TableError) => void
    /** 缂撳瓨鍛戒腑鍥炶皟锛堜粠缂撳瓨鑾峰彇鏁版嵁鏃惰Е鍙戯級 */
    onCacheHit?: (data: TRecord[], response: ApiResponse<TRecord>) => void
    /** 鍔犺浇鐘舵€佸彉鍖栧洖璋?*/
    onLoading?: (loading: boolean) => void
    /** 閲嶇疆琛ㄥ崟鍥炶皟鍑芥暟 */
    resetFormCallback?: () => void
  }

  // 璋冭瘯閰嶇疆
  debug?: {
    /** 鏄惁鍚敤鏃ュ織杈撳嚭 */
    enableLog?: boolean
    /** 鏃ュ織绾у埆 */
    logLevel?: 'info' | 'warn' | 'error'
  }
}

export function useTable<TApiFn extends (params: any) => Promise<any>>(
  config: UseTableConfig<TApiFn>
) {
  return useTableImpl(config)
}

/**
 * useTable 鐨勬牳蹇冨疄鐜?- 寮哄ぇ鐨勮〃鏍兼暟鎹鐞?Hook
 *
 * 鎻愪緵瀹屾暣鐨勮〃鏍艰В鍐虫柟妗堬紝鍖呮嫭锛?
 * - 鏁版嵁鑾峰彇涓庣紦瀛?
 * - 鍒嗛〉鎺у埗
 * - 鎼滅储鍔熻兘
 * - 鏅鸿兘鍒锋柊绛栫暐
 * - 閿欒澶勭悊
 * - 鍒楅厤缃鐞?
 */
function useTableImpl<TApiFn extends (params: any) => Promise<any>>(
  config: UseTableConfig<TApiFn>
) {
  type TRecord = InferRecordType<InferApiResponse<TApiFn>>
  type TParams = InferApiParams<TApiFn>
  const {
    core: {
      apiFn,
      apiParams = {} as Partial<TParams>,
      excludeParams = [],
      immediate = true,
      columnsFactory,
      paginationKey
    },
    transform: { dataTransformer, responseAdapter = defaultResponseAdapter } = {},
    performance: {
      enableCache = false,
      cacheTime = 5 * 60 * 1000,
      debounceTime = 300,
      maxCacheSize = 50
    } = {},
    hooks: { onSuccess, onError, onCacheHit, resetFormCallback } = {},
    debug: { enableLog = false } = {}
  } = config

  // 鍒嗛〉瀛楁鍚嶉厤缃細浼樺厛浣跨敤浼犲叆鐨勯厤缃紝鍚﹀垯浣跨敤鍏ㄥ眬閰嶇疆
  const pageKey = paginationKey?.current || tableConfig.paginationKey.current
  const sizeKey = paginationKey?.size || tableConfig.paginationKey.size

  // 鍝嶅簲寮忚Е鍙戝櫒锛岀敤浜庢墜鍔ㄦ洿鏂扮紦瀛樼粺璁′俊鎭?
  const cacheUpdateTrigger = ref(0)

  // 鏃ュ織宸ュ叿鍑芥暟
  const logger = {
    log: (message: string, ...args: unknown[]) => {
      if (enableLog) {
        console.log(`[useTable] ${message}`, ...args)
      }
    },
    warn: (message: string, ...args: unknown[]) => {
      if (enableLog) {
        console.warn(`[useTable] ${message}`, ...args)
      }
    },
    error: (message: string, ...args: unknown[]) => {
      if (enableLog) {
        console.error(`[useTable] ${message}`, ...args)
      }
    }
  }

  // 缂撳瓨瀹炰緥
  const cache = enableCache ? new TableCache<TRecord>(cacheTime, maxCacheSize, enableLog) : null

  // 鍔犺浇鐘舵€佹満
  type LoadingState = 'idle' | 'loading' | 'success' | 'error'
  const loadingState = ref<LoadingState>('idle')
  const loading = computed(() => loadingState.value === 'loading')

  // 閿欒鐘舵€?
  const error = ref<TableError | null>(null)

  // 琛ㄦ牸鏁版嵁
  const data = ref<TRecord[]>([])

  // 璇锋眰鍙栨秷鎺у埗鍣?
  let abortController: AbortController | null = null

  // 缂撳瓨娓呯悊瀹氭椂鍣?
  let cacheCleanupTimer: NodeJS.Timeout | null = null

  // 鎼滅储鍙傛暟
  const searchParams = reactive(
    Object.assign(
      {
        [pageKey]: 1,
        [sizeKey]: 10
      },
      apiParams || {}
    ) as TParams
  )

  // 鍒嗛〉閰嶇疆
  const pagination = reactive<Api.Common.PaginationParams>({
    current: ((searchParams as Record<string, unknown>)[pageKey] as number) || 1,
    size: ((searchParams as Record<string, unknown>)[sizeKey] as number) || 10,
    total: 0
  })

  // 绉诲姩绔垎椤?(鍝嶅簲寮?
  const { width } = useWindowSize()
  const mobilePagination = computed(() => ({
    ...pagination,
    small: width.value < 768
  }))

  // 鍒楅厤缃?
  const columnConfig = columnsFactory ? useTableColumns<TRecord>(columnsFactory) : null
  const columns = columnConfig?.columns
  const columnChecks = columnConfig?.columnChecks

  // 鏄惁鏈夋暟鎹?
  const hasData = computed(() => data.value.length > 0)

  // 缂撳瓨缁熻淇℃伅
  const cacheInfo = computed(() => {
    // 渚濊禆瑙﹀彂鍣紝纭繚缂撳瓨鍙樺寲鏃堕噸鏂拌绠?
    void cacheUpdateTrigger.value
    if (!cache) return { total: 0, size: '0KB', hitRate: '0 avg hits' }
    return cache.getStats()
  })

  // 閿欒澶勭悊鍑芥暟
  const handleError = createErrorHandler(onError, enableLog)

  // 娓呯悊缂撳瓨锛屾牴鎹笉鍚岀殑涓氬姟鍦烘櫙閫夋嫨鎬у湴娓呯悊缂撳瓨
  const clearCache = (strategy: CacheInvalidationStrategy, context?: string): void => {
    if (!cache) return

    let clearedCount = 0

    switch (strategy) {
      case CacheInvalidationStrategy.CLEAR_ALL:
        cache.clear()
        logger.log(`娓呯┖鎵€鏈夌紦瀛?- ${context || ''}`)
        break

      case CacheInvalidationStrategy.CLEAR_CURRENT:
        clearedCount = cache.clearCurrentSearch(searchParams)
        logger.log(`娓呯┖褰撳墠鎼滅储缂撳瓨 ${clearedCount} 鏉?- ${context || ''}`)
        break

      case CacheInvalidationStrategy.CLEAR_PAGINATION:
        clearedCount = cache.clearPagination()
        logger.log(`娓呯┖鍒嗛〉缂撳瓨 ${clearedCount} 鏉?- ${context || ''}`)
        break

      case CacheInvalidationStrategy.KEEP_ALL:
      default:
        logger.log(`淇濇寔缂撳瓨涓嶅彉 - ${context || ''}`)
        break
    }
    // 鎵嬪姩瑙﹀彂缂撳瓨鐘舵€佹洿鏂?
    cacheUpdateTrigger.value++
  }

  // 鑾峰彇鏁版嵁鐨勬牳蹇冩柟娉?
  const fetchData = async (
    params?: Partial<TParams>,
    useCache = enableCache
  ): Promise<ApiResponse<TRecord>> => {
    // 鍙栨秷涓婁竴涓姹?
    if (abortController) {
      abortController.abort()
    }

    // 鍒涘缓鏂扮殑鍙栨秷鎺у埗鍣?
    const currentController = new AbortController()
    abortController = currentController

    // 鐘舵€佹満锛氳繘鍏?loading 鐘舵€?
    loadingState.value = 'loading'
    error.value = null

    try {
      let requestParams = Object.assign(
        {},
        searchParams,
        {
          [pageKey]: pagination.current,
          [sizeKey]: pagination.size
        },
        params || {}
      ) as TParams

      // 鍓旈櫎涓嶉渶瑕佺殑鍙傛暟
      if (excludeParams.length > 0) {
        const filteredParams = { ...requestParams }
        excludeParams.forEach((key) => {
          delete (filteredParams as Record<string, unknown>)[key]
        })
        requestParams = filteredParams as TParams
      }

      // 妫€鏌ョ紦瀛?
      if (useCache && cache) {
        const cachedItem = cache.get(requestParams)
        if (cachedItem) {
          data.value = cachedItem.data
          updatePaginationFromResponse(pagination, cachedItem.response)

          // 淇锛氶伩鍏嶉噸澶嶈缃浉鍚岀殑鍊硷紝闃叉鍝嶅簲寮忓惊鐜洿鏂?
          const paramsRecord = searchParams as Record<string, unknown>
          if (paramsRecord[pageKey] !== pagination.current) {
            paramsRecord[pageKey] = pagination.current
          }
          if (paramsRecord[sizeKey] !== pagination.size) {
            paramsRecord[sizeKey] = pagination.size
          }

          // 鐘舵€佹満锛氱紦瀛樺懡涓紝杩涘叆 success 鐘舵€?
          loadingState.value = 'success'

          // 缂撳瓨鍛戒腑鏃惰Е鍙戜笓闂ㄧ殑鍥炶皟锛岃€屼笉鏄?onSuccess
          if (onCacheHit) {
            onCacheHit(cachedItem.data, cachedItem.response)
          }

          logger.log(`缂撳瓨鍛戒腑`)
          return cachedItem.response
        }
      }

      const response = await apiFn(requestParams)

      // 妫€鏌ヨ姹傛槸鍚﹁鍙栨秷
      if (currentController.signal.aborted) {
        throw new Error('请求已取消')
      }

      // 浣跨敤鍝嶅簲閫傞厤鍣ㄨ浆鎹负鏍囧噯鏍煎紡
      const standardResponse = responseAdapter(response)

      // 澶勭悊鍝嶅簲鏁版嵁
      let tableData = extractTableData(standardResponse)

      // 搴旂敤鏁版嵁杞崲鍑芥暟
      if (dataTransformer) {
        tableData = dataTransformer(tableData)
      }

      // 鏇存柊鐘舵€?
      data.value = tableData
      updatePaginationFromResponse(pagination, standardResponse)

      // 淇锛氶伩鍏嶉噸澶嶈缃浉鍚岀殑鍊硷紝闃叉鍝嶅簲寮忓惊鐜洿鏂?
      const paramsRecord = searchParams as Record<string, unknown>
      if (paramsRecord[pageKey] !== pagination.current) {
        paramsRecord[pageKey] = pagination.current
      }
      if (paramsRecord[sizeKey] !== pagination.size) {
        paramsRecord[sizeKey] = pagination.size
      }

      // 缂撳瓨鏁版嵁
      if (useCache && cache) {
        cache.set(requestParams, tableData, standardResponse)
        // 鎵嬪姩瑙﹀彂缂撳瓨鐘舵€佹洿鏂?
        cacheUpdateTrigger.value++
        logger.log('数据已写入缓存')
      }

      // 鐘舵€佹満锛氳姹傛垚鍔燂紝杩涘叆 success 鐘舵€?
      loadingState.value = 'success'

      // 鎴愬姛鍥炶皟
      if (onSuccess) {
        onSuccess(tableData, standardResponse)
      }

      return standardResponse
    } catch (err) {
      if (err instanceof Error && err.message === '请求已取消') {
        // 璇锋眰琚彇娑堬紝鍥炲埌 idle 鐘舵€?
        loadingState.value = 'idle'
        return { records: [], total: 0, current: 1, size: 10 }
      }

      // 鐘舵€佹満锛氳姹傚け璐ワ紝杩涘叆 error 鐘舵€?
      loadingState.value = 'error'
      data.value = []
      const tableError = handleError(err, '鑾峰彇琛ㄦ牸鏁版嵁澶辫触')
      throw tableError
    } finally {
      // 鍙湁褰撳墠鎺у埗鍣ㄦ槸娲昏穬鐨勬墠娓呯┖
      if (abortController === currentController) {
        abortController = null
      }
    }
  }

  // 鑾峰彇鏁版嵁 (淇濇寔褰撳墠椤?
  const getData = async (params?: Partial<TParams>): Promise<ApiResponse<TRecord> | void> => {
    try {
      return await fetchData(params)
    } catch {
      // 閿欒宸插湪 fetchData 涓鐞?
      return Promise.resolve()
    }
  }

  // 鍒嗛〉鑾峰彇鏁版嵁 (閲嶇疆鍒扮涓€椤? - 涓撻棬鐢ㄤ簬鎼滅储鍦烘櫙
  const getDataByPage = async (params?: Partial<TParams>): Promise<ApiResponse<TRecord> | void> => {
    pagination.current = 1
    ;(searchParams as Record<string, unknown>)[pageKey] = 1

    // 鎼滅储鏃舵竻绌哄綋鍓嶆悳绱㈡潯浠剁殑缂撳瓨锛岀‘淇濊幏鍙栨渶鏂版暟鎹?
    clearCache(CacheInvalidationStrategy.CLEAR_CURRENT, '鎼滅储鏁版嵁')

    try {
      return await fetchData(params, false) // 鎼滅储鏃朵笉浣跨敤缂撳瓨
    } catch {
      // 閿欒宸插湪 fetchData 涓鐞?
      return Promise.resolve()
    }
  }

  // 鏅鸿兘闃叉姈鎼滅储鍑芥暟
  const debouncedGetDataByPage = createSmartDebounce(getDataByPage, debounceTime)

  // 閲嶇疆鎼滅储鍙傛暟
  const resetSearchParams = async (): Promise<void> => {
    // 鍙栨秷闃叉姈鐨勬悳绱?
    debouncedGetDataByPage.cancel()

    // 淇濆瓨鍒嗛〉鐩稿叧鐨勯粯璁ゅ€?
    const paramsRecord = searchParams as Record<string, unknown>
    const defaultPagination = {
      [pageKey]: 1,
      [sizeKey]: (paramsRecord[sizeKey] as number) || 10
    }

    // 娓呯┖鎵€鏈夋悳绱㈠弬鏁?
    Object.keys(searchParams).forEach((key) => {
      delete paramsRecord[key]
    })

    // 閲嶆柊璁剧疆榛樿鍙傛暟
    Object.assign(searchParams, apiParams || {}, defaultPagination)

    // 閲嶇疆鍒嗛〉
    pagination.current = 1
    pagination.size = defaultPagination[sizeKey] as number

    // 娓呯┖閿欒鐘舵€?
    error.value = null

    // 娓呯┖缂撳瓨
    clearCache(CacheInvalidationStrategy.CLEAR_ALL, '閲嶇疆鎼滅储')

    // 閲嶆柊鑾峰彇鏁版嵁
    await getData()

    // 鎵ц閲嶇疆鍥炶皟
    if (resetFormCallback) {
      await nextTick()
      resetFormCallback()
    }
  }

  // 鏇挎崲鎼滅储鍙傛暟锛氶€傜敤浜庤〃鍗曟煡璇紝閬垮厤鏃у瓧娈垫畫鐣?
  const replaceSearchParams = (params?: Partial<TParams>): void => {
    const paramsRecord = searchParams as Record<string, unknown>
    const currentSize = pagination.size || ((paramsRecord[sizeKey] as number) ?? 10)

    Object.keys(searchParams).forEach((key) => {
      if (key !== pageKey && key !== sizeKey) {
        delete paramsRecord[key]
      }
    })

    Object.assign(
      searchParams,
      {
        [pageKey]: 1,
        [sizeKey]: currentSize
      },
      params || {}
    )

    pagination.current = 1
    pagination.size = currentSize
  }

  // 闃查噸澶嶈皟鐢ㄧ殑鏍囧織
  let isCurrentChanging = false

  // 澶勭悊鍒嗛〉澶у皬鍙樺寲
  const handleSizeChange = async (newSize: number): Promise<void> => {
    if (newSize <= 0) return

    debouncedGetDataByPage.cancel()

    const paramsRecord = searchParams as Record<string, unknown>
    pagination.size = newSize
    pagination.current = 1
    paramsRecord[sizeKey] = newSize
    paramsRecord[pageKey] = 1

    clearCache(CacheInvalidationStrategy.CLEAR_CURRENT, '鍒嗛〉澶у皬鍙樺寲')

    await getData()
  }

  // 澶勭悊褰撳墠椤靛彉鍖?
  const handleCurrentChange = async (newCurrent: number): Promise<void> => {
    if (newCurrent <= 0) return

    // 淇锛氶槻姝㈤噸澶嶈皟鐢?
    if (isCurrentChanging) {
      return
    }

    // 淇锛氬鏋滃綋鍓嶉〉娌℃湁鍙樺寲锛屼笉闇€瑕侀噸鏂拌姹?
    if (pagination.current === newCurrent) {
      logger.log('鍒嗛〉椤电爜鏈彉鍖栵紝璺宠繃璇锋眰')
      return
    }

    try {
      isCurrentChanging = true

      // 淇锛氬彧鏇存柊蹇呰鐨勭姸鎬?
      const paramsRecord = searchParams as Record<string, unknown>
      pagination.current = newCurrent
      // 鍙湁褰?searchParams 鐨勫垎椤靛瓧娈典笌鏂板€间笉鍚屾椂鎵嶆洿鏂?
      if (paramsRecord[pageKey] !== newCurrent) {
        paramsRecord[pageKey] = newCurrent
      }

      await getData()
    } finally {
      isCurrentChanging = false
    }
  }

  // 閽堝涓嶅悓涓氬姟鍦烘櫙鐨勫埛鏂版柟娉?

  // 鏂板鍚庡埛鏂帮細鍥炲埌绗竴椤靛苟娓呯┖鍒嗛〉缂撳瓨锛堥€傜敤浜庢柊澧炴暟鎹悗锛?
  const refreshCreate = async (): Promise<void> => {
    debouncedGetDataByPage.cancel()
    pagination.current = 1
    ;(searchParams as Record<string, unknown>)[pageKey] = 1
    clearCache(CacheInvalidationStrategy.CLEAR_PAGINATION, '鏂板鏁版嵁')
    await getData()
  }

  // 鏇存柊鍚庡埛鏂帮細淇濇寔褰撳墠椤碉紝浠呮竻绌哄綋鍓嶆悳绱㈢紦瀛橈紙閫傜敤浜庢洿鏂版暟鎹悗锛?
  const refreshUpdate = async (): Promise<void> => {
    clearCache(CacheInvalidationStrategy.CLEAR_CURRENT, '缂栬緫鏁版嵁')
    await getData()
  }

  // 鍒犻櫎鍚庡埛鏂帮細鏅鸿兘澶勭悊椤电爜锛岄伩鍏嶇┖椤甸潰锛堥€傜敤浜庡垹闄ゆ暟鎹悗锛?
  const refreshRemove = async (): Promise<void> => {
    const { current } = pagination

    // 娓呴櫎缂撳瓨骞惰幏鍙栨渶鏂版暟鎹?
    clearCache(CacheInvalidationStrategy.CLEAR_CURRENT, '鍒犻櫎鏁版嵁')
    await getData()

    // 濡傛灉褰撳墠椤典负绌轰笖涓嶆槸绗竴椤碉紝鍥炲埌涓婁竴椤?
    if (data.value.length === 0 && current > 1) {
      pagination.current = current - 1
      ;(searchParams as Record<string, unknown>)[pageKey] = current - 1
      await getData()
    }
  }

  // 鍏ㄩ噺鍒锋柊锛氭竻绌烘墍鏈夌紦瀛橈紝閲嶆柊鑾峰彇鏁版嵁锛堥€傜敤浜庢墜鍔ㄥ埛鏂版寜閽級
  const refreshData = async (): Promise<void> => {
    debouncedGetDataByPage.cancel()
    clearCache(CacheInvalidationStrategy.CLEAR_ALL, '鎵嬪姩鍒锋柊')
    await getData()
  }

  // 杞婚噺鍒锋柊锛氫粎娓呯┖褰撳墠鎼滅储鏉′欢鐨勭紦瀛橈紝淇濇寔鍒嗛〉鐘舵€侊紙閫傜敤浜庡畾鏃跺埛鏂帮級
  const refreshSoft = async (): Promise<void> => {
    clearCache(CacheInvalidationStrategy.CLEAR_CURRENT, '轻量刷新')
    await getData()
  }

  // 鍙栨秷褰撳墠璇锋眰
  const cancelRequest = (): void => {
    if (abortController) {
      abortController.abort()
    }
    debouncedGetDataByPage.cancel()
  }

  // 娓呯┖鏁版嵁
  const clearData = (): void => {
    data.value = []
    error.value = null
    clearCache(CacheInvalidationStrategy.CLEAR_ALL, '娓呯┖鏁版嵁')
  }

  // 娓呯悊宸茶繃鏈熺殑缂撳瓨鏉＄洰锛岄噴鏀惧唴瀛樼┖闂?
  const clearExpiredCache = (): number => {
    if (!cache) return 0
    const cleanedCount = cache.cleanupExpired()
    if (cleanedCount > 0) {
      // 鎵嬪姩瑙﹀彂缂撳瓨鐘舵€佹洿鏂?
      cacheUpdateTrigger.value++
    }
    return cleanedCount
  }

  // 璁剧疆瀹氭湡娓呯悊杩囨湡缂撳瓨
  if (enableCache && cache) {
    cacheCleanupTimer = setInterval(() => {
      const cleanedCount = cache.cleanupExpired()
      if (cleanedCount > 0) {
        logger.log(`自动清理 ${cleanedCount} 条过期缓存`)
        // 鎵嬪姩瑙﹀彂缂撳瓨鐘舵€佹洿鏂?
        cacheUpdateTrigger.value++
      }
    }, cacheTime / 2) // 姣忓崐涓紦瀛樺懆鏈熸竻鐞嗕竴娆?
  }

  // 鎸傝浇鏃惰嚜鍔ㄥ姞杞芥暟鎹?
  if (immediate) {
    onMounted(async () => {
      await getData()
    })
  }

  // 缁勪欢鍗歌浇鏃跺交搴曟竻鐞?
  onUnmounted(() => {
    cancelRequest()
    if (cache) {
      cache.clear()
    }
    if (cacheCleanupTimer) {
      clearInterval(cacheCleanupTimer)
    }
  })

  // 浼樺寲鐨勮繑鍥炲€肩粨鏋?
  return {
    // 鏁版嵁鐩稿叧
    /** 琛ㄦ牸鏁版嵁 */
    data,
    /** 鏁版嵁鍔犺浇鐘舵€?*/
    loading: readonly(loading),
    /** 閿欒鐘舵€?*/
    error: readonly(error),
    /** 鏁版嵁鏄惁涓虹┖ */
    isEmpty: computed(() => data.value.length === 0),
    /** 鏄惁鏈夋暟鎹?*/
    hasData,

    // 鍒嗛〉鐩稿叧
    /** 鍒嗛〉鐘舵€佷俊鎭?*/
    pagination: readonly(pagination),
    /** 绉诲姩绔垎椤甸厤缃?*/
    paginationMobile: mobilePagination,
    /** 椤甸潰澶у皬鍙樺寲澶勭悊 */
    handleSizeChange,
    /** 褰撳墠椤靛彉鍖栧鐞?*/
    handleCurrentChange,

    // 鎼滅储鐩稿叧 - 缁熶竴鍓嶇紑
    /** 鎼滅储鍙傛暟 */
    searchParams,
    /** 鏇挎崲鎼滅储鍙傛暟锛堥€傜敤浜庤〃鍗曟煡璇紝閬垮厤鏃у瓧娈垫畫鐣欙級 */
    replaceSearchParams,
    /** 閲嶇疆鎼滅储鍙傛暟 */
    resetSearchParams,

    // 鏁版嵁鎿嶄綔 - 鏇存槑纭殑鎿嶄綔鎰忓浘
    /** 鍔犺浇鏁版嵁 */
    fetchData: getData,
    /** 鑾峰彇鏁版嵁 */
    getData: getDataByPage,
    /** 鑾峰彇鏁版嵁锛堥槻鎶栵級 */
    getDataDebounced: debouncedGetDataByPage,
    /** 娓呯┖鏁版嵁 */
    clearData,

    // 鍒锋柊绛栫暐
    /** 鍏ㄩ噺鍒锋柊锛氭竻绌烘墍鏈夌紦瀛橈紝閲嶆柊鑾峰彇鏁版嵁锛堥€傜敤浜庢墜鍔ㄥ埛鏂版寜閽級 */
    refreshData,
    /** 杞婚噺鍒锋柊锛氫粎娓呯┖褰撳墠鎼滅储鏉′欢鐨勭紦瀛橈紝淇濇寔鍒嗛〉鐘舵€侊紙閫傜敤浜庡畾鏃跺埛鏂帮級 */
    refreshSoft,
    /** 鏂板鍚庡埛鏂帮細鍥炲埌绗竴椤靛苟娓呯┖鍒嗛〉缂撳瓨锛堥€傜敤浜庢柊澧炴暟鎹悗锛?*/
    refreshCreate,
    /** 鏇存柊鍚庡埛鏂帮細淇濇寔褰撳墠椤碉紝浠呮竻绌哄綋鍓嶆悳绱㈢紦瀛橈紙閫傜敤浜庢洿鏂版暟鎹悗锛?*/
    refreshUpdate,
    /** 鍒犻櫎鍚庡埛鏂帮細鏅鸿兘澶勭悊椤电爜锛岄伩鍏嶇┖椤甸潰锛堥€傜敤浜庡垹闄ゆ暟鎹悗锛?*/
    refreshRemove,

    // 缂撳瓨鎺у埗
    /** 缂撳瓨缁熻淇℃伅 */
    cacheInfo,
    /** 娓呴櫎缂撳瓨锛屾牴鎹笉鍚岀殑涓氬姟鍦烘櫙閫夋嫨鎬у湴娓呯悊缂撳瓨锛?*/
    clearCache,
    // 鏀寔4绉嶆竻鐞嗙瓥鐣?
    // clearCache(CacheInvalidationStrategy.CLEAR_ALL, '鎵嬪姩鍒锋柊')     // 娓呯┖鎵€鏈夌紦瀛?
    // clearCache(CacheInvalidationStrategy.CLEAR_CURRENT, '鎼滅储鏁版嵁') // 鍙竻绌哄綋鍓嶆悳绱㈡潯浠剁殑缂撳瓨
    // clearCache(CacheInvalidationStrategy.CLEAR_PAGINATION, '鏂板鏁版嵁') // 娓呯┖鍒嗛〉鐩稿叧缂撳瓨
    // clearCache(CacheInvalidationStrategy.KEEP_ALL, '淇濇寔缂撳瓨')      // 涓嶆竻鐞嗕换浣曠紦瀛?
    /** 娓呯悊宸茶繃鏈熺殑缂撳瓨鏉＄洰锛岄噴鏀惧唴瀛樼┖闂?*/
    clearExpiredCache,

    // 璇锋眰鎺у埗
    /** 鍙栨秷褰撳墠璇锋眰 */
    cancelRequest,

    // 鍒楅厤缃?(濡傛灉鎻愪緵浜?columnsFactory)
    ...(columnConfig && {
      /** 琛ㄦ牸鍒楅厤缃?*/
      columns,
      /** 鍒楁樉绀烘帶鍒?*/
      columnChecks,
      /** 鏂板鍒?*/
      addColumn: columnConfig.addColumn,
      /** 鍒犻櫎鍒?*/
      removeColumn: columnConfig.removeColumn,
      /** 鍒囨崲鍒楁樉绀虹姸鎬?*/
      toggleColumn: columnConfig.toggleColumn,
      /** 鏇存柊鍒楅厤缃?*/
      updateColumn: columnConfig.updateColumn,
      /** 鎵归噺鏇存柊鍒楅厤缃?*/
      batchUpdateColumns: columnConfig.batchUpdateColumns,
      /** 閲嶆柊鎺掑簭鍒?*/
      reorderColumns: columnConfig.reorderColumns,
      /** 鑾峰彇鎸囧畾鍒楅厤缃?*/
      getColumnConfig: columnConfig.getColumnConfig,
      /** 鑾峰彇鎵€鏈夊垪閰嶇疆 */
      getAllColumns: columnConfig.getAllColumns,
      /** 閲嶇疆鎵€鏈夊垪閰嶇疆鍒伴粯璁ょ姸鎬?*/
      resetColumns: columnConfig.resetColumns
    })
  }
}

// 閲嶆柊瀵煎嚭绫诲瀷鍜屾灇涓撅紝鏂逛究浣跨敤
export { CacheInvalidationStrategy } from '../../utils/table/tableCache'
export type { ApiResponse, CacheItem } from '../../utils/table/tableCache'
export type { BaseRequestParams, TableError } from '../../utils/table/tableUtils'

