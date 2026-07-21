/**
 * 鍏ㄥ眬 Loading 鍔犺浇绠＄悊妯″潡
 *
 * 鎻愪緵缁熶竴鐨勫叏灞忓姞杞藉姩鐢荤鐞?
 *
 * ## 涓昏鍔熻兘
 *
 * - 鍏ㄥ睆 Loading 鏄剧ず鍜岄殣钘?
 * - 鑷姩閫傞厤鏄庢殫涓婚鑳屾櫙鑹?
 * - 鑷畾涔?SVG 鍔犺浇鍔ㄧ敾
 * - 鍗曚緥妯″紡闃叉閲嶅鍒涘缓
 * - 閿佸畾椤甸潰浜や簰
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 椤甸潰鍒濆鍖栧姞杞?
 * - 澶ч噺鏁版嵁璇锋眰
 * - 璺敱鍒囨崲杩囨浮
 * - 寮傛鎿嶄綔绛夊緟
 *
 * ## 鐗规€?
 *
 * - 鑷姩妫€娴嬪綋鍓嶄富棰樺苟搴旂敤瀵瑰簲鑳屾櫙鑹?
 * - 浣跨敤鑷畾涔?SVG 鍔ㄧ敾锛堝洓鐐规棆杞級
 * - 鍗曚緥妯″紡纭繚鍚屾椂鍙湁涓€涓?Loading
 * - 鎻愪緵渚挎嵎鐨勬樉绀?闅愯棌鏂规硶
 *
 * @module utils/ui/loading
 * @author AiPay
 */
import { fourDotsSpinnerSvg } from '@/assets/svg/loading'

/**
 * 鑾峰彇褰撳墠涓婚瀵瑰簲鐨刲oading鑳屾櫙鑹?
 * @returns 鑳屾櫙鑹插瓧绗︿覆
 */
const getLoadingBackground = (): string => {
  const isDark = document.documentElement.classList.contains('dark')
  return isDark ? 'rgba(7, 7, 7, 0.85)' : '#fff'
}

const DEFAULT_LOADING_CONFIG = {
  lock: true,
  get background() {
    return getLoadingBackground()
  },
  svg: fourDotsSpinnerSvg,
  svgViewBox: '0 0 40 40',
  customClass: 'art-loading-fix'
} as const

interface LoadingInstance {
  close: () => void
}

let loadingInstance: LoadingInstance | null = null

export const loadingService = {
  /**
   * 鏄剧ず loading
   * @returns 鍏抽棴 loading 鐨勫嚱鏁?
   */
  showLoading(): () => void {
    if (!loadingInstance) {
      // 姣忔鏄剧ず鏃惰幏鍙栨渶鏂扮殑閰嶇疆锛岀‘淇濊儗鏅壊涓庡綋鍓嶄富棰樺悓姝?
      const config = {
        ...DEFAULT_LOADING_CONFIG,
        background: getLoadingBackground()
      }
      loadingInstance = ElLoading.service(config)
    }
    return () => this.hideLoading()
  },

  /**
   * 闅愯棌 loading
   */
  hideLoading(): void {
    if (loadingInstance) {
      loadingInstance.close()
      loadingInstance = null
    }
  }
}

