/**
 * 琛ㄥ崟鍝嶅簲寮忓竷灞€宸ュ叿妯″潡
 *
 * 鎻愪緵琛ㄥ崟椤瑰湪涓嶅悓灞忓箷灏哄涓嬬殑鏅鸿兘甯冨眬璁＄畻
 *
 * ## 涓昏鍔熻兘
 *
 * - 鍝嶅簲寮忔柇鐐圭鐞嗭紙xs/sm/md/lg/xl锛?
 * - 琛ㄥ崟鍒楀鑷姩闄嶇骇锛堥伩鍏嶅皬灞忓箷鍘嬬缉锛?
 * - 鍩轰簬闃堝€肩殑鏅鸿兘 span 璁＄畻
 * - 鍝嶅簲寮忚绠楀櫒宸ュ巶鍑芥暟
 * - 鍙厤缃殑鏂偣瑙勫垯
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 琛ㄥ崟缁勪欢鍝嶅簲寮忓竷灞€
 * - 鎼滅储琛ㄥ崟鑷€傚簲
 * - 绉诲姩绔〃鍗曚紭鍖?
 * - 澶氬垪琛ㄥ崟甯冨眬
 *
 * ## 鏂偣璇存槑锛堝熀浜?Element Plus Grid 24 鏍呮牸绯荤粺锛夛細
 * - xs (鎵嬫満): < 768px锛屽皬浜?12 鏃堕檷绾т负 24锛堟弧瀹斤級
 * - sm (骞虫澘): 鈮?768px锛屽皬浜?12 鏃堕檷绾т负 12锛堝崐瀹斤級
 * - md (涓瓑灞忓箷): 鈮?992px锛屽皬浜?8 鏃堕檷绾т负 8锛堜笁鍒嗕箣涓€瀹斤級
 * - lg (澶у睆骞?: 鈮?1200px锛岀洿鎺ヤ娇鐢ㄨ缃殑 span
 * - xl (瓒呭ぇ灞忓箷): 鈮?1920px锛岀洿鎺ヤ娇鐢ㄨ缃殑 span
 *
 * ## 鏍稿績鍔熻兘
 *
 * - calculateResponsiveSpan: 璁＄畻鍝嶅簲寮忓垪瀹?
 * - createResponsiveSpanCalculator: 鍒涘缓 span 璁＄畻鍣紙鏌噷鍖栵級
 *
 * @module utils/form/responsive
 * @author AiPay
 */

/**
 * 鍝嶅簲寮忔柇鐐圭被鍨?
 */
export type ResponsiveBreakpoint = 'xs' | 'sm' | 'md' | 'lg' | 'xl'

/**
 * 鏂偣閰嶇疆鏄犲皠
 */
interface BreakpointConfig {
  /** 鏈€灏?span 闃堝€?*/
  threshold: number
  /** 闄嶇骇鍚庣殑 span 鍊?*/
  fallback: number
}

/**
 * 鍝嶅簲寮忔柇鐐归厤缃?
 */
const BREAKPOINT_CONFIG: Record<ResponsiveBreakpoint, BreakpointConfig | null> = {
  xs: { threshold: 12, fallback: 24 }, // 鎵嬫満锛氬皬浜?12 鏃朵娇鐢ㄦ弧瀹?
  sm: { threshold: 12, fallback: 12 }, // 骞虫澘锛氬皬浜?12 鏃朵娇鐢ㄥ崐瀹?
  md: { threshold: 8, fallback: 8 }, // 涓瓑灞忓箷锛氬皬浜?8 鏃朵娇鐢ㄤ笁鍒嗕箣涓€瀹?
  lg: null, // 澶у睆骞曪細鐩存帴浣跨敤璁剧疆鐨?span
  xl: null // 瓒呭ぇ灞忓箷锛氱洿鎺ヤ娇鐢ㄨ缃殑 span
}

/**
 * 璁＄畻鍝嶅簲寮忓垪瀹?
 *
 * 鏍规嵁灞忓箷灏哄鏅鸿兘闄嶇骇锛岄伩鍏嶅皬灞忓箷涓婅〃鍗曢」琚帇缂╄繃灏?
 *
 * @param itemSpan 琛ㄥ崟椤硅嚜瀹氫箟鐨?span 鍊?
 * @param defaultSpan 榛樿鐨?span 鍊?
 * @param breakpoint 褰撳墠鏂偣
 * @returns 璁＄畻鍚庣殑 span 鍊?
 *
 * @example
 * ```ts
 * // 鍦?xs 鏂偣涓嬶紝span 涓?6 浼氶檷绾т负 24锛堟弧瀹斤級
 * calculateResponsiveSpan(6, 6, 'xs') // 24
 *
 * // 鍦?md 鏂偣涓嬶紝span 涓?6 浼氶檷绾т负 8锛堜笁鍒嗕箣涓€瀹斤級
 * calculateResponsiveSpan(6, 6, 'md') // 8
 *
 * // 鍦?lg 鏂偣涓嬶紝鐩存帴浣跨敤鍘熷 span
 * calculateResponsiveSpan(6, 6, 'lg') // 6
 * ```
 */
export function calculateResponsiveSpan(
  itemSpan: number | undefined,
  defaultSpan: number,
  breakpoint: ResponsiveBreakpoint
): number {
  const finalSpan = itemSpan ?? defaultSpan
  const config = BREAKPOINT_CONFIG[breakpoint]

  // 濡傛灉娌℃湁閰嶇疆锛坙g/xl锛夛紝鐩存帴杩斿洖鍘熷 span
  if (!config) {
    return finalSpan
  }

  // 濡傛灉 span 灏忎簬闃堝€硷紝浣跨敤闄嶇骇鍊?
  return finalSpan >= config.threshold ? finalSpan : config.fallback
}

/**
 * 鍒涘缓鍝嶅簲寮?span 璁＄畻鍣?
 *
 * 杩斿洖涓€涓嚱鏁帮紝鐢ㄤ簬璁＄畻鎸囧畾鏂偣涓嬬殑 span 鍊?
 *
 * @param defaultSpan 榛樿鐨?span 鍊?
 * @returns span 璁＄畻鍑芥暟
 *
 * @example
 * ```ts
 * const getColSpan = createResponsiveSpanCalculator(6)
 * getColSpan(undefined, 'xs') // 24
 * getColSpan(8, 'md') // 8
 * getColSpan(12, 'lg') // 12
 * ```
 */
export function createResponsiveSpanCalculator(defaultSpan: number) {
  return (itemSpan: number | undefined, breakpoint: ResponsiveBreakpoint): number => {
    return calculateResponsiveSpan(itemSpan, defaultSpan, breakpoint)
  }
}

