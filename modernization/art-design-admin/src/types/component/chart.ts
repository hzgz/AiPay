/**
 * 鍥捐〃缁勪欢绫诲瀷瀹氫箟妯″潡
 *
 * 鎻愪緵 ECharts 鍥捐〃缁勪欢鐨勫畬鏁寸被鍨嬪畾涔?
 *
 * ## 涓昏鍔熻兘
 *
 * - 鍩虹鍥捐〃閰嶇疆绫诲瀷
 * - 鏌辩姸鍥剧被鍨嬪畾涔?
 * - 鎶樼嚎鍥剧被鍨嬪畾涔?
 * - 楗煎浘/鐜舰鍥剧被鍨嬪畾涔?
 * - 闆疯揪鍥剧被鍨嬪畾涔?
 * - K绾垮浘绫诲瀷瀹氫箟
 * - 鏁ｇ偣鍥剧被鍨嬪畾涔?
 * - 鍦板浘鍥捐〃绫诲瀷瀹氫箟
 * - 鍙屽悜鍫嗗彔鏌辩姸鍥剧被鍨嬪畾涔?
 * - 鍥捐〃涓婚閰嶇疆绫诲瀷
 * - 鍥捐〃浜嬩欢鍥炶皟绫诲瀷
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 鍥捐〃缁勪欢 Props 绫诲瀷绾︽潫
 * - 鍥捐〃閰嶇疆绫诲瀷瀹氫箟
 * - 鍥捐〃鏁版嵁缁撴瀯瀹氫箟
 * - 鍥捐〃浜嬩欢澶勭悊
 *
 * @module types/component/chart
 * @author AiPay
 */
import type { EChartsOption } from '@/plugins/echarts'

// 鍥句緥浣嶇疆绫诲瀷
export type LegendPosition = 'bottom' | 'top' | 'left' | 'right'

export type SymbolType =
  | 'circle'
  | 'rect'
  | 'roundRect'
  | 'triangle'
  | 'diamond'
  | 'pin'
  | 'arrow'
  | 'none'

// 鍥捐〃涓婚閰嶇疆
export interface ChartThemeConfig {
  /** 鍥捐〃楂樺害 */
  chartHeight: string
  /** 瀛椾綋澶у皬 */
  fontSize: number
  /** 瀛椾綋棰滆壊 */
  fontColor: string
  /** 涓婚棰滆壊 */
  themeColor: string
  /** 棰滆壊缁?*/
  colors: string[]
}

// 鍥捐〃鍒濆鍖栭€夐」
export interface UseChartOptions {
  /** 鍒濆鍖栭€夐」 */
  initOptions?: EChartsOption
  /** 寤惰繜鍒濆鍖栨椂闂?ms) */
  initDelay?: number
  /** IntersectionObserver闃堝€?*/
  threshold?: number
  /** 鏄惁鑷姩鍝嶅簲涓婚鍙樺寲 */
  autoTheme?: boolean
}

// 鍩虹鍥捐〃 Props 鎺ュ彛 - 缁熶竴鎵€鏈夊浘琛ㄧ殑鍩虹灞炴€?
export interface BaseChartProps {
  /** 鍥捐〃楂樺害 */
  height?: string
  /** 鏄惁鍔犺浇涓?*/
  loading?: boolean
  isEmpty?: boolean
  /** 棰滆壊閰嶇疆 */
  colors?: string[]
}

// 杞寸嚎鏄剧ず鎺у埗鎺ュ彛 - 缁熶竴杞寸嚎鐩稿叧閰嶇疆
export interface AxisDisplayProps {
  /** 鏄惁鏄剧ず鍧愭爣杞存爣绛?*/
  showAxisLabel?: boolean
  /** 鏄惁鏄剧ず鍧愭爣杞寸嚎 */
  showAxisLine?: boolean
  /** 鏄惁鏄剧ず鍒嗗壊绾?*/
  showSplitLine?: boolean
}

// 浜や簰鏄剧ず鎺у埗鎺ュ彛 - 缁熶竴浜や簰鐩稿叧閰嶇疆
export interface InteractionProps {
  /** 鏄惁鏄剧ず鎻愮ず妗?*/
  showTooltip?: boolean
  /** 鏄惁鏄剧ず鍥句緥 */
  showLegend?: boolean
  /** 鍥句緥浣嶇疆 */
  legendPosition?: LegendPosition
}

// 鏌辩姸鍥炬暟鎹」鎺ュ彛
export interface BarDataItem {
  /** 绯诲垪鍚嶇О */
  name: string
  /** 鏁版嵁鍊?*/
  data: number[]
  /** 鏌辩姸鍥惧搴?*/
  barWidth?: string | number
  /** 鍫嗗彔鍒嗙粍鍚嶇О */
  stack?: string
}

// 鏌辩姸鍥?Props 鎺ュ彛 - 缁熶竴鏌辩姸鍥鹃厤缃?
export interface BarChartProps extends BaseChartProps, AxisDisplayProps, InteractionProps {
  /** 鍥捐〃鏁版嵁 - 鏀寔鍗曠粍鏁版嵁鎴栧缁勬暟鎹?*/
  data: number[] | BarDataItem[]
  /** X杞存爣绛炬暟鎹?*/
  xAxisData?: string[]
  /** 鏌辩姸鍥惧搴?*/
  barWidth?: string | number
  /** 鏄惁鍫嗗彔鏄剧ず */
  stack?: boolean
  /** 鍦嗚 */
  borderRadius?: number | number[]
}

// 鎶樼嚎鍥炬暟鎹」鎺ュ彛
export interface LineDataItem {
  /** 绯诲垪鍚嶇О */
  name: string
  /** 鏁版嵁鍊?*/
  data: number[]
  /** 绾挎潯瀹藉害 */
  lineWidth?: number
  /** 鏄惁鏄剧ず鍖哄煙濉厖 */
  showAreaColor?: boolean
  /** 鍖哄煙鏍峰紡閰嶇疆 */
  areaStyle?: {
    /** 娓愬彉寮€濮嬮€忔槑搴?*/
    startOpacity?: number
    /** 娓愬彉缁撴潫閫忔槑搴?*/
    endOpacity?: number
    /** 鑷畾涔?ECharts areaStyle 閰嶇疆 */
    custom?: any
  }
  /** 鏄惁骞虫粦鏇茬嚎 */
  smooth?: boolean
  /** 鏁版嵁鐐圭鍙?*/
  symbol?: SymbolType
  /** 鏁版嵁鐐瑰ぇ灏?*/
  symbolSize?: number
}

// 鎶樼嚎鍥?Props 鎺ュ彛 - 缁熶竴鎶樼嚎鍥鹃厤缃?
export interface LineChartProps extends BaseChartProps, AxisDisplayProps, InteractionProps {
  /** 鍥捐〃鏁版嵁 - 鏀寔鍗曠粍鏁版嵁鎴栧缁勬暟鎹?*/
  data: number[] | LineDataItem[]
  /** X杞存爣绛炬暟鎹?*/
  xAxisData?: string[]
  /** 绾挎潯瀹藉害 */
  lineWidth?: number
  /** 鏄惁鏄剧ず鍖哄煙濉厖 */
  showAreaColor?: boolean
  /** 鏄惁骞虫粦鏇茬嚎 */
  smooth?: boolean
  /** 鏁版嵁鐐圭鍙?*/
  symbol?: SymbolType
  /** 鏁版嵁鐐瑰ぇ灏?*/
  symbolSize?: number
  /** 澶氭暟鎹姩鐢诲欢杩熼棿闅旓紙姣锛?*/
  animationDelay?: number
}

// 闆疯揪鍥炬暟鎹」鎺ュ彛
export interface RadarDataItem {
  /** 绯诲垪鍚嶇О */
  name: string
  /** 鏁版嵁鍊?*/
  value: number[]
}

// 闆疯揪鍥?Props 鎺ュ彛 - 缁熶竴闆疯揪鍥鹃厤缃?
export interface RadarChartProps extends BaseChartProps, InteractionProps {
  /** 闆疯揪鍥炬寚鏍囬厤缃?*/
  indicator?: Array<{ name: string; max: number }>
  /** 鍥捐〃鏁版嵁 */
  data?: RadarDataItem[]
}

// 楗煎浘/鐜舰鍥炬暟鎹」鎺ュ彛
export interface PieDataItem {
  /** 鏁版嵁鍊?*/
  value: number
  /** 鏁版嵁鍚嶇О */
  name: string
}

// 鐜舰鍥?Props 鎺ュ彛 - 缁熶竴鐜舰鍥鹃厤缃?
export interface RingChartProps extends BaseChartProps, InteractionProps {
  /** 鍥捐〃鏁版嵁 */
  data: PieDataItem[]
  /** 鍐呭鍗婂緞 */
  radius?: string[]
  /** 杈规鍦嗚 */
  borderRadius?: number
  /** 涓績鏂囨湰 */
  centerText?: string
  /** 鏄惁鏄剧ず鏍囩 */
  showLabel?: boolean
}

// K绾垮浘鏁版嵁椤规帴鍙?
export interface KLineDataItem {
  /** 鏃堕棿鏍囩 */
  time: string
  /** 寮€鐩樹环 */
  open: number
  /** 鏀剁洏浠?*/
  close: number
  /** 鏈€楂樹环 */
  high: number
  /** 鏈€浣庝环 */
  low: number
}

// K绾垮浘 Props 鎺ュ彛 - 缁熶竴K绾垮浘閰嶇疆
export interface KLineChartProps extends BaseChartProps {
  /** 鍥捐〃鏁版嵁 */
  data?: KLineDataItem[]
  /** 鏄惁鏄剧ず鏁版嵁缂╂斁鎺т欢 */
  showDataZoom?: boolean
  /** 鏁版嵁缂╂斁鍒濆寮€濮嬩綅缃?*/
  dataZoomStart?: number
  /** 鏁版嵁缂╂斁鍒濆缁撴潫浣嶇疆 */
  dataZoomEnd?: number
}

// 鏁ｇ偣鍥炬暟鎹」鎺ュ彛
export interface ScatterDataItem {
  /** 鍧愭爣鍊?[x, y] */
  value: number[]
}

// 鏁ｇ偣鍥?Props 鎺ュ彛 - 缁熶竴鏁ｇ偣鍥鹃厤缃?
export interface ScatterChartProps extends BaseChartProps, AxisDisplayProps, InteractionProps {
  /** 鍥捐〃鏁版嵁 */
  data?: ScatterDataItem[]
  /** 鏁ｇ偣澶у皬 */
  symbolSize?: number
}

// 鍙屾煴瀵规瘮鍥?Props 鎺ュ彛 - 缁熶竴鍙屾煴瀵规瘮鍥鹃厤缃?
export interface DualBarCompareChartProps extends BaseChartProps {
  /** 涓婃柟鏁版嵁 */
  topData: number[]
  /** 涓嬫柟鏁版嵁 */
  bottomData: number[]
  /** X杞存爣绛炬暟鎹?*/
  xAxisData: string[]
  /** 涓婃柟鏌卞瓙棰滆壊 */
  topColor?: string
  /** 涓嬫柟鏌卞瓙棰滆壊 */
  bottomColor?: string
  /** 鏌辩姸鍥惧搴?*/
  barWidth?: number
}

// 鍦板浘鍥捐〃 Props 鎺ュ彛 - 缁熶竴鍦板浘鍥捐〃閰嶇疆
export interface MapChartProps extends BaseChartProps {
  /** 鍦板浘鏁版嵁 */
  mapData?: any[]
  /** 閫変腑鍖哄煙 */
  selectedRegion?: string
  /** 鏄惁鏄剧ず鏍囩 */
  showLabels?: boolean
  /** 鏄惁鏄剧ず鏁ｇ偣 */
  showScatter?: boolean
}

// 鍙屽悜鍫嗗彔鏌辩姸鍥?Props 鎺ュ彛锛堜汉鍙ｉ噾瀛楀鏍峰紡锛?
export interface BidirectionalBarChartProps
  extends BaseChartProps,
    AxisDisplayProps,
    InteractionProps {
  /** 姝ｅ悜鏁版嵁锛堝悜涓婃樉绀猴級 */
  positiveData: number[]
  /** 璐熷悜鏁版嵁锛堝悜涓嬫樉绀猴級 */
  negativeData: number[]
  /** X杞存爣绛炬暟鎹?*/
  xAxisData?: string[]
  /** 姝ｅ悜鏁版嵁鍚嶇О */
  positiveName?: string
  /** 璐熷悜鏁版嵁鍚嶇О */
  negativeName?: string
  /** 鏌辩姸鍥惧搴?*/
  barWidth?: string | number
  /** Y杞存渶灏忓€?*/
  yAxisMin?: number
  /** Y杞存渶澶у€?*/
  yAxisMax?: number
  /** 鏄惁鏄剧ず鏁版嵁鏍囩 */
  showDataLabel?: boolean
  /** 姝ｅ悜鏁版嵁鍦嗚閰嶇疆 */
  positiveBorderRadius?: number | number[]
  /** 璐熷悜鏁版嵁鍦嗚閰嶇疆 */
  negativeBorderRadius?: number | number[]
}

// 鍥捐〃閰嶇疆鐢熸垚鍣ㄥ嚱鏁扮被鍨?
export type ChartOptionGenerator = () => EChartsOption

// 鍥捐〃浜嬩欢鍥炶皟绫诲瀷
export type ChartEventCallback = (params: any) => void

// 鍥捐〃閿欒淇℃伅鎺ュ彛
export interface ChartError {
  /** 閿欒鐮?*/
  code: string
  /** 閿欒淇℃伅 */
  message: string
  /** 閿欒璇︽儏 */
  details?: any
}

