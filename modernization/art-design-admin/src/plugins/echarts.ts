/**
 * ECharts 鎻掍欢閰嶇疆
 *
 * 鎸夐渶瀵煎叆 ECharts 鍥捐〃鍜岀粍浠讹紝鍑忓皬鎵撳寘浣撶Н銆?
 * 鍙敞鍐岄」鐩腑瀹為檯浣跨敤鐨勫浘琛ㄧ被鍨嬪拰缁勪欢銆?
 *
 * @module plugins/echarts
 * @author AiPay
 */

// ECharts 鎸夐渶瀵煎叆閰嶇疆
import * as echarts from 'echarts/core'

// 瀵煎叆鍥捐〃绫诲瀷
import {
  BarChart,
  LineChart,
  PieChart,
  ScatterChart,
  RadarChart,
  MapChart,
  CandlestickChart
} from 'echarts/charts'

// 瀵煎叆缁勪欢
import {
  TitleComponent,
  TooltipComponent,
  GridComponent,
  LegendComponent,
  DataZoomComponent,
  MarkPointComponent,
  MarkLineComponent,
  ToolboxComponent,
  BrushComponent,
  GeoComponent,
  VisualMapComponent
} from 'echarts/components'

// 瀵煎叆娓叉煋鍣?
import { CanvasRenderer } from 'echarts/renderers'

// 娉ㄥ唽蹇呰鐨勭粍浠?
echarts.use([
  // 鍥捐〃绫诲瀷
  BarChart,
  LineChart,
  PieChart,
  ScatterChart,
  RadarChart,
  MapChart,
  CandlestickChart,

  // 缁勪欢
  TitleComponent,
  TooltipComponent,
  GridComponent,
  LegendComponent,
  DataZoomComponent,
  MarkPointComponent,
  MarkLineComponent,
  ToolboxComponent,
  BrushComponent,
  GeoComponent,
  VisualMapComponent,

  // 娓叉煋鍣?
  CanvasRenderer
])

// 瀵煎嚭 echarts 瀹炰緥鍜岀被鍨?
export { echarts }
export type { EChartsOption, BarSeriesOption } from 'echarts'

// 瀵煎嚭甯哥敤鐨勫浘褰㈠伐鍏?
export const graphic = echarts.graphic

