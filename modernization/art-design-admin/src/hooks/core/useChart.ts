/**
 * useChart - ECharts 鍥捐〃绠＄悊
 *
 * 鎻愪緵瀹屾暣鐨?ECharts 鍥捐〃鐢熷懡鍛ㄦ湡绠＄悊鍜岄厤缃兘鍔涳紝绠€鍖栧浘琛ㄥ紑鍙戞祦绋嬨€?
 * 鑷姩澶勭悊鍥捐〃鍒濆鍖栥€佹洿鏂般€侀攢姣併€佷富棰樺垏鎹€佸搷搴斿紡璋冩暣绛夊鏉傞€昏緫銆?
 *
 * ## 鏍稿績鍔熻兘
 *
 * 1. 鍥捐〃鐢熷懡鍛ㄦ湡绠＄悊 - 鑷姩澶勭悊鍒濆鍖栥€佹洿鏂般€侀攢姣侊紝鏀寔寤惰繜鍔犺浇鍜屽彲瑙佹€ф娴?
 * 2. 涓婚鑷姩閫傞厤 - 鍝嶅簲绯荤粺涓婚鍙樺寲锛岃嚜鍔ㄦ洿鏂板浘琛ㄦ牱寮忓拰閰嶈壊
 * 3. 鍝嶅簲寮忚皟鏁?- 鐩戝惉绐楀彛澶у皬銆佽彍鍗曞睍寮€绛夊彉鍖栵紝鑷姩璋冩暣鍥捐〃灏哄
 * 4. 绌虹姸鎬佸鐞?- 浼橀泤鐨勭┖鏁版嵁灞曠ず锛岃嚜鍔ㄦ樉绀?鏆傛棤鏁版嵁"鎻愮ず
 * 5. 鏍峰紡閰嶇疆缁熶竴 - 鎻愪緵鍧愭爣杞淬€佸浘渚嬨€佹彁绀烘绛夌粺涓€鐨勬牱寮忛厤缃柟娉?
 * 6. 鎬ц兘浼樺寲 - 闃叉姈澶勭悊銆佹牱寮忕紦瀛樸€乺equestAnimationFrame 浼樺寲
 * 7. 楂樼骇缁勪欢鎶借薄 - useChartComponent 鎻愪緵鏇撮珮灞傛鐨勫浘琛ㄧ粍浠跺皝瑁?
 *
 * ## 浣跨敤绀轰緥
 *
 * ```typescript
 * // 鍩虹鐢ㄦ硶
 * const {
 *   chartRef,
 *   initChart,
 *   updateChart,
 *   getAxisLineStyle,
 *   getTooltipStyle
 * } = useChart()
 *
 * onMounted(() => {
 *   initChart({
 *     xAxis: { type: 'category', data: ['Mon', 'Tue', 'Wed'] },
 *     yAxis: { type: 'value' },
 *     series: [{ data: [120, 200, 150], type: 'bar' }]
 *   })
 * })
 *
 * // 楂樼骇鐢ㄦ硶 - 缁勪欢鎶借薄
 * const chart = useChartComponent({
 *   props,
 *   generateOptions: () => ({
 *     // ECharts 閰嶇疆
 *   }),
 *   checkEmpty: () => data.value.length === 0,
 *   watchSources: [() => props.data]
 * })
 * ```
 *
 * @module useChart
 * @author AiPay
 */

import { echarts, type EChartsOption } from '@/plugins/echarts'
import { storeToRefs } from 'pinia'
import { useSettingStore } from '@/store/modules/setting'
import { getCssVar } from '@/utils/ui'
import type { BaseChartProps, ChartThemeConfig, UseChartOptions } from '@/types/component/chart'

// 鍥捐〃涓婚閰嶇疆
export const useChartOps = (): ChartThemeConfig => ({
  /** */
  chartHeight: '16rem',
  /** 瀛椾綋澶у皬 */
  fontSize: 13,
  /** 瀛椾綋棰滆壊 */
  fontColor: '#999',
  /** 涓婚棰滆壊 */
  themeColor: getCssVar('--el-color-primary-light-1'),
  /** 棰滆壊缁?*/
  colors: [
    getCssVar('--el-color-primary-light-1'),
    '#4ABEFF',
    '#EDF2FF',
    '#14DEBA',
    '#FFAF20',
    '#FA8A6C',
    '#FFAF20'
  ]
})

// 甯搁噺瀹氫箟
const RESIZE_DELAYS = [50, 100, 200, 350] as const
const MENU_RESIZE_DELAYS = [50, 100, 200] as const
const RESIZE_DEBOUNCE_DELAY = 100

export function useChart(options: UseChartOptions = {}) {
  const { initOptions, initDelay = 0, threshold = 0.1, autoTheme = true } = options

  const settingStore = useSettingStore()
  const { isDark, menuOpen, menuType } = storeToRefs(settingStore)

  const chartRef = ref<HTMLElement>()
  let chart: echarts.ECharts | null = null
  let intersectionObserver: IntersectionObserver | null = null
  let pendingOptions: EChartsOption | null = null
  let resizeTimeoutId: number | null = null
  let resizeFrameId: number | null = null
  let isDestroyed = false
  let emptyStateDiv: HTMLElement | null = null

  // 娓呯悊瀹氭椂鍣ㄧ殑缁熶竴鏂规硶
  const clearTimers = () => {
    if (resizeTimeoutId) {
      clearTimeout(resizeTimeoutId)
      resizeTimeoutId = null
    }
    if (resizeFrameId) {
      cancelAnimationFrame(resizeFrameId)
      resizeFrameId = null
    }
  }

  // 浣跨敤 requestAnimationFrame 浼樺寲 resize 澶勭悊
  const requestAnimationResize = () => {
    if (resizeFrameId) {
      cancelAnimationFrame(resizeFrameId)
    }
    resizeFrameId = requestAnimationFrame(() => {
      handleResize()
      resizeFrameId = null
    })
  }

  // 闃叉姈鐨剅esize澶勭悊锛堢敤浜庣獥鍙esize浜嬩欢锛?
  const debouncedResize = () => {
    if (resizeTimeoutId) {
      clearTimeout(resizeTimeoutId)
    }
    resizeTimeoutId = window.setTimeout(() => {
      requestAnimationResize()
      resizeTimeoutId = null
    }, RESIZE_DEBOUNCE_DELAY)
  }

  // 澶氬欢杩焤esize澶勭悊 - 缁熶竴鏂规硶
  const multiDelayResize = (delays: readonly number[]) => {
    // 绔嬪嵆璋冪敤涓€娆★紝蹇€熷搷搴?
    nextTick(requestAnimationResize)

    // 浣跨敤寤惰繜鏃堕棿锛岀‘淇濆浘琛ㄦ纭€傚簲鍙樺寲
    delays.forEach((delay) => {
      setTimeout(requestAnimationResize, delay)
    })
  }

  // 鏀剁缉鑿滃崟鏃讹紝閲嶆柊璁＄畻鍥捐〃澶у皬锛堜粎鍦ㄥ浘琛ㄥ瓨鍦ㄦ椂鐩戝惉锛?
  let menuOpenStopHandle: (() => void) | null = null
  let menuTypeStopHandle: (() => void) | null = null

  const setupMenuWatchers = () => {
    menuOpenStopHandle = watch(menuOpen, () => multiDelayResize(RESIZE_DELAYS))
    menuTypeStopHandle = watch(menuType, () => {
      nextTick(requestAnimationResize)
      setTimeout(() => multiDelayResize(MENU_RESIZE_DELAYS), 0)
    })
  }

  const cleanupMenuWatchers = () => {
    menuOpenStopHandle?.()
    menuTypeStopHandle?.()
    menuOpenStopHandle = null
    menuTypeStopHandle = null
  }

  // 涓婚鍙樺寲鏃堕噸鏂拌缃浘琛ㄩ€夐」
  let themeStopHandle: (() => void) | null = null

  const setupThemeWatcher = () => {
    if (autoTheme) {
      themeStopHandle = watch(isDark, () => {
        // 鏇存柊绌虹姸鎬佹牱寮?
        emptyStateManager.updateStyle()

        if (chart && !isDestroyed) {
          // 浣跨敤 requestAnimationFrame 浼樺寲涓婚鏇存柊
          requestAnimationFrame(() => {
            if (chart && !isDestroyed) {
              const currentOptions = chart.getOption()
              if (currentOptions) {
                updateChart(currentOptions as EChartsOption)
              }
            }
          })
        }
      })
    }
  }

  const cleanupThemeWatcher = () => {
    themeStopHandle?.()
    themeStopHandle = null
  }

  // 鏍峰紡鐢熸垚鍣?- 缁熶竴鐨勬牱寮忛厤缃?
  const createLineStyle = (color: string, width = 1, type?: 'solid' | 'dashed') => ({
    color,
    width,
    ...(type && { type })
  })

  // 缂撳瓨鏍峰紡閰嶇疆浠ュ噺灏戦噸澶嶈绠?
  const styleCache = {
    axisLine: null as any,
    splitLine: null as any,
    axisLabel: null as any,
    lastDarkValue: isDark.value
  }

  const clearStyleCache = () => {
    styleCache.axisLine = null
    styleCache.splitLine = null
    styleCache.axisLabel = null
    styleCache.lastDarkValue = isDark.value
  }

  // 鍧愭爣杞寸嚎鏍峰紡
  const getAxisLineStyle = (show: boolean = true) => {
    if (styleCache.lastDarkValue !== isDark.value) {
      clearStyleCache()
    }
    if (!styleCache.axisLine) {
      styleCache.axisLine = {
        show,
        lineStyle: createLineStyle(isDark.value ? '#444' : '#EDEDED')
      }
    }
    return styleCache.axisLine
  }

  // 鍒嗗壊绾挎牱寮?
  const getSplitLineStyle = (show: boolean = true) => {
    if (styleCache.lastDarkValue !== isDark.value) {
      clearStyleCache()
    }
    if (!styleCache.splitLine) {
      styleCache.splitLine = {
        show,
        lineStyle: createLineStyle(isDark.value ? '#444' : '#EDEDED', 1, 'dashed')
      }
    }
    return styleCache.splitLine
  }

  // 鍧愭爣杞存爣绛炬牱寮?
  const getAxisLabelStyle = (show: boolean = true) => {
    if (styleCache.lastDarkValue !== isDark.value) {
      clearStyleCache()
    }
    if (!styleCache.axisLabel) {
      const { fontColor, fontSize } = useChartOps()
      styleCache.axisLabel = {
        show,
        color: fontColor,
        fontSize
      }
    }
    return styleCache.axisLabel
  }

  // 鍧愭爣杞村埢搴︽牱寮忥紙闈欐€侀厤缃紝鏃犻渶缂撳瓨锛?
  const getAxisTickStyle = () => ({
    show: false
  })

  // 鑾峰彇鍔ㄧ敾閰嶇疆
  const getAnimationConfig = (animationDelay: number = 50, animationDuration: number = 1500) => ({
    animationDelay: (idx: number) => idx * animationDelay + 200,
    animationDuration: (idx: number) => animationDuration - idx * 50,
    animationEasing: 'quarticOut' as const
  })

  // 鑾峰彇缁熶竴鐨?tooltip 閰嶇疆
  const getTooltipStyle = (trigger: 'item' | 'axis' = 'axis', customOptions: any = {}) => ({
    trigger,
    backgroundColor: isDark.value ? 'rgba(0, 0, 0, 0.8)' : 'rgba(255, 255, 255, 0.9)',
    borderColor: isDark.value ? '#333' : '#ddd',
    borderWidth: 1,
    textStyle: {
      color: isDark.value ? '#fff' : '#333'
    },
    ...customOptions
  })

  // 鑾峰彇缁熶竴鐨勫浘渚嬮厤缃?
  const getLegendStyle = (
    position: 'bottom' | 'top' | 'left' | 'right' = 'bottom',
    customOptions: any = {}
  ) => {
    const baseConfig = {
      textStyle: {
        color: isDark.value ? '#fff' : '#333'
      },
      itemWidth: 12,
      itemHeight: 12,
      itemGap: 20,
      ...customOptions
    }

    // 鏍规嵁浣嶇疆璁剧疆涓嶅悓鐨勯厤缃?
    switch (position) {
      case 'bottom':
        return {
          ...baseConfig,
          bottom: 0,
          left: 'center',
          orient: 'horizontal',
          icon: 'roundRect'
        }
      case 'top':
        return {
          ...baseConfig,
          top: 0,
          left: 'center',
          orient: 'horizontal',
          icon: 'roundRect'
        }
      case 'left':
        return {
          ...baseConfig,
          left: 0,
          top: 'center',
          orient: 'vertical',
          icon: 'roundRect'
        }
      case 'right':
        return {
          ...baseConfig,
          right: 0,
          top: 'center',
          orient: 'vertical',
          icon: 'roundRect'
        }
      default:
        return baseConfig
    }
  }

  // 鏍规嵁鍥句緥浣嶇疆璁＄畻 grid 閰嶇疆
  const getGridWithLegend = (
    showLegend: boolean,
    legendPosition: 'bottom' | 'top' | 'left' | 'right' = 'bottom',
    baseGrid: any = {}
  ) => {
    const defaultGrid = {
      top: 15,
      right: 15,
      bottom: 8,
      left: 0,
      containLabel: true,
      ...baseGrid
    }

    if (!showLegend) {
      return defaultGrid
    }

    // 鏍规嵁鍥句緥浣嶇疆璋冩暣 grid
    switch (legendPosition) {
      case 'bottom':
        return {
          ...defaultGrid,
          bottom: 40
        }
      case 'top':
        return {
          ...defaultGrid,
          top: 40
        }
      case 'left':
        return {
          ...defaultGrid,
          left: 120
        }
      case 'right':
        return {
          ...defaultGrid,
          right: 120
        }
      default:
        return defaultGrid
    }
  }

  // 鍒涘缓IntersectionObserver
  const createIntersectionObserver = () => {
    if (intersectionObserver || !chartRef.value) return

    intersectionObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting && pendingOptions && !isDestroyed) {
            // 浣跨敤 requestAnimationFrame 纭繚鍦ㄤ笅涓€甯у垵濮嬪寲鍥捐〃
            requestAnimationFrame(() => {
              if (!isDestroyed && pendingOptions) {
                try {
                  // 鍏冪礌鍙樹负鍙锛屽垵濮嬪寲鍥捐〃
                  if (!chart) {
                    chart = echarts.init(entry.target as HTMLElement)
                  }

                  // 瑙﹀彂鑷畾涔変簨浠讹紝璁╃粍浠跺鐞嗗姩鐢婚€昏緫
                  const event = new CustomEvent('chartVisible', {
                    detail: { options: pendingOptions }
                  })
                  entry.target.dispatchEvent(event)

                  pendingOptions = null
                  cleanupIntersectionObserver()
                } catch (error) {
                  console.error('鍥捐〃鍒濆鍖栧け璐?', error)
                }
              }
            })
          }
        })
      },
      { threshold }
    )

    intersectionObserver.observe(chartRef.value)
  }

  // 娓呯悊IntersectionObserver
  const cleanupIntersectionObserver = () => {
    if (intersectionObserver) {
      intersectionObserver.disconnect()
      intersectionObserver = null
    }
  }

  // 妫€鏌ュ鍣ㄦ槸鍚﹀彲瑙?
  const isContainerVisible = (element: HTMLElement): boolean => {
    const rect = element.getBoundingClientRect()
    return rect.width > 0 && rect.height > 0 && rect.top < window.innerHeight && rect.bottom > 0
  }

  // 鍥捐〃鍒濆鍖栨牳蹇冮€昏緫
  const performChartInit = (options: EChartsOption) => {
    if (!chart && chartRef.value && !isDestroyed) {
      chart = echarts.init(chartRef.value)
      // 鍥捐〃鍒涘缓鍚庣珛鍗宠缃洃鍚櫒
      setupMenuWatchers()
      setupThemeWatcher()
    }
    if (chart && !isDestroyed) {
      chart.setOption(options)
      pendingOptions = null
    }
  }

  // 绌虹姸鎬佺鐞嗗櫒
  const emptyStateManager = {
    create: () => {
      if (!chartRef.value || emptyStateDiv) return

      emptyStateDiv = document.createElement('div')
      emptyStateDiv.style.cssText = `
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        color: ${isDark.value ? '#555555' : '#B3B2B2'};
        background: transparent;
        z-index: 10;
      `
      emptyStateDiv.innerHTML = `<span>鏆傛棤鏁版嵁</span>`

      // 纭繚鐖跺鍣ㄦ湁鐩稿瀹氫綅
      if (
        chartRef.value.style.position !== 'relative' &&
        chartRef.value.style.position !== 'absolute'
      ) {
        chartRef.value.style.position = 'relative'
      }

      chartRef.value.appendChild(emptyStateDiv)
    },

    remove: () => {
      if (emptyStateDiv && chartRef.value) {
        chartRef.value.removeChild(emptyStateDiv)
        emptyStateDiv = null
      }
    },

    updateStyle: () => {
      if (emptyStateDiv) {
        emptyStateDiv.style.color = isDark.value ? '#666' : '#999'
      }
    }
  }

  // 鍒濆鍖栧浘琛?
  const initChart = (options: EChartsOption = {}, isEmpty: boolean = false) => {
    if (!chartRef.value || isDestroyed) return

    const mergedOptions = { ...initOptions, ...options }

    try {
      if (isEmpty) {
        // 澶勭悊绌烘暟鎹儏鍐?- 鏄剧ず鑷畾涔夌┖鐘舵€乨iv
        if (chart) {
          chart.clear()
        }
        emptyStateManager.create()
        return
      } else {
        // 鏈夋暟鎹椂绉婚櫎绌虹姸鎬乨iv
        emptyStateManager.remove()
      }

      if (isContainerVisible(chartRef.value)) {
        // 瀹瑰櫒鍙锛屾甯稿垵濮嬪寲
        if (initDelay > 0) {
          setTimeout(() => performChartInit(mergedOptions), initDelay)
        } else {
          performChartInit(mergedOptions)
        }
      } else {
        // 瀹瑰櫒涓嶅彲瑙侊紝淇濆瓨閫夐」骞惰缃洃鍚櫒
        pendingOptions = mergedOptions
        createIntersectionObserver()
      }
    } catch (error) {
      console.error('鍥捐〃鍒濆鍖栧け璐?', error)
    }
  }

  // 鏇存柊鍥捐〃
  const updateChart = (options: EChartsOption) => {
    if (isDestroyed) return

    try {
      if (!chart) {
        // 濡傛灉鍥捐〃涓嶅瓨鍦紝鍏堝垵濮嬪寲
        initChart(options)
        return
      }
      chart.setOption(options)
    } catch (error) {
      console.error('鍥捐〃鏇存柊澶辫触:', error)
    }
  }

  // 澶勭悊绐楀彛澶у皬鍙樺寲
  const handleResize = () => {
    if (chart && !isDestroyed) {
      try {
        chart.resize()
      } catch (error) {
        console.error('鍥捐〃resize澶辫触:', error)
      }
    }
  }

  // 閿€姣佸浘琛?
  const destroyChart = () => {
    isDestroyed = true

    if (chart) {
      try {
        chart.dispose()
      } catch (error) {
        console.error('鍥捐〃閿€姣佸け璐?', error)
      } finally {
        chart = null
      }
    }

    // 娓呯悊鎵€鏈夌洃鍚櫒鍜岃祫婧?
    cleanupMenuWatchers()
    cleanupThemeWatcher()
    emptyStateManager.remove()
    cleanupIntersectionObserver()
    clearTimers()
    clearStyleCache()
    pendingOptions = null
  }

  // 鑾峰彇鍥捐〃瀹炰緥
  const getChartInstance = () => chart

  // 鑾峰彇鍥捐〃鏄惁宸插垵濮嬪寲
  const isChartInitialized = () => chart !== null

  onMounted(() => {
    window.addEventListener('resize', debouncedResize)
  })

  onBeforeUnmount(() => {
    window.removeEventListener('resize', debouncedResize)
  })

  onUnmounted(() => {
    destroyChart()
  })

  return {
    isDark,
    chartRef,
    initChart,
    updateChart,
    handleResize,
    destroyChart,
    getChartInstance,
    isChartInitialized,
    emptyStateManager,
    getAxisLineStyle,
    getSplitLineStyle,
    getAxisLabelStyle,
    getAxisTickStyle,
    getAnimationConfig,
    getTooltipStyle,
    getLegendStyle,
    useChartOps,
    getGridWithLegend
  }
}

// 楂樼骇鍥捐〃缁勪欢鎶借薄
interface UseChartComponentOptions<T extends BaseChartProps> {
  /** Props鍝嶅簲寮忓璞?*/
  props: T
  /** 鍥捐〃閰嶇疆鐢熸垚鍑芥暟 */
  generateOptions: () => EChartsOption
  /** 绌烘暟鎹鏌ュ嚱鏁?*/
  checkEmpty?: () => boolean
  /** 鑷畾涔夌洃鍚殑鍝嶅簲寮忔暟鎹?*/
  watchSources?: (() => any)[]
  /** 鑷畾涔夊彲瑙嗕簨浠跺鐞?*/
  onVisible?: () => void
  /** useChart閫夐」 */
  chartOptions?: UseChartOptions
}

export function useChartComponent<T extends BaseChartProps>(options: UseChartComponentOptions<T>) {
  const {
    props,
    generateOptions,
    checkEmpty,
    watchSources = [],
    onVisible,
    chartOptions = {}
  } = options

  const chart = useChart(chartOptions)
  const { chartRef, initChart, isDark, emptyStateManager } = chart

  // 妫€鏌ユ槸鍚︿负绌烘暟鎹?
  const isEmpty = computed(() => {
    if (props.isEmpty) return true
    if (checkEmpty) return checkEmpty()
    return false
  })

  // 鏇存柊鍥捐〃
  const updateChart = () => {
    nextTick(() => {
      if (isEmpty.value) {
        // 澶勭悊绌烘暟鎹儏鍐?- 鏄剧ず鑷畾涔夌┖鐘舵€乨iv
        if (chart.getChartInstance()) {
          chart.getChartInstance()?.clear()
        }
        emptyStateManager.create()
      } else {
        // 鏈夋暟鎹椂绉婚櫎绌虹姸鎬乨iv骞跺垵濮嬪寲鍥捐〃
        emptyStateManager.remove()
        initChart(generateOptions())
      }
    })
  }

  // 澶勭悊鍥捐〃杩涘叆鍙鍖哄煙鏃剁殑閫昏緫
  const handleChartVisible = () => {
    if (onVisible) {
      onVisible()
    } else {
      updateChart()
    }
  }

  // 瀛樺偍鐩戝惉鍣ㄥ仠姝㈠嚱鏁?
  const stopHandles: (() => void)[] = []

  // 璁剧疆鏁版嵁鐩戝惉
  const setupWatchers = () => {
    // 鐩戝惉鑷畾涔夋暟鎹簮
    if (watchSources.length > 0) {
      const stopHandle = watch(watchSources, updateChart, { deep: true })
      stopHandles.push(stopHandle)
    }

    // 鐩戝惉涓婚鍙樺寲
    const themeStopHandle = watch(isDark, () => {
      emptyStateManager.updateStyle()
      updateChart()
    })
    stopHandles.push(themeStopHandle)
  }

  // 娓呯悊鎵€鏈夌洃鍚櫒
  const cleanupWatchers = () => {
    stopHandles.forEach((stop) => stop())
    stopHandles.length = 0
  }

  // 璁剧疆鐢熷懡鍛ㄦ湡
  const setupLifecycle = () => {
    onMounted(() => {
      updateChart()

      // 鐩戝惉鍥捐〃鍙浜嬩欢
      if (chartRef.value) {
        chartRef.value.addEventListener('chartVisible', handleChartVisible)
      }
    })

    onBeforeUnmount(() => {
      // 娓呯悊浜嬩欢鐩戝惉鍣?
      if (chartRef.value) {
        chartRef.value.removeEventListener('chartVisible', handleChartVisible)
      }
      // 娓呯悊鎵€鏈夌洃鍚櫒
      cleanupWatchers()
      // 娓呯悊绌虹姸鎬乨iv
      emptyStateManager.remove()
    })
  }

  // 鍒濆鍖?
  setupWatchers()
  setupLifecycle()

  return {
    ...chart,
    isEmpty,
    updateChart,
    handleChartVisible
  }
}

