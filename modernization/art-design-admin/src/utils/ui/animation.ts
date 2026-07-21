/**
 * 涓婚鍔ㄧ敾宸ュ叿妯″潡
 *
 * 鎻愪緵涓婚鍒囨崲鐨勮瑙夊姩鐢绘晥鏋?
 *
 * ## 涓昏鍔熻兘
 *
 * - 鍩轰簬榧犳爣鐐瑰嚮浣嶇疆鐨勫渾褰㈡墿鏁ｅ姩鐢?
 * - View Transition API 鏀寔锛堢幇浠ｆ祻瑙堝櫒锛?
 * - 闄嶇骇澶勭悊锛堜笉鏀寔鍔ㄧ敾鐨勬祻瑙堝櫒锛?
 * - 鏆楅粦涓婚鍒囨崲杩囨浮鏁堟灉
 * - 椤甸潰鍒锋柊鏃剁殑涓婚杩囨浮浼樺寲
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 鏄庢殫涓婚鍒囨崲
 * - 鎻愬崌鐢ㄦ埛浣撻獙鐨勮瑙夊弽棣?
 * - 椤甸潰鍒锋柊鏃剁殑骞虫粦杩囨浮
 *
 * ## 鎶€鏈疄鐜?
 *
 * - 浣跨敤 CSS 鍙橀噺瀛樺偍鐐瑰嚮浣嶇疆鍜屽崐寰?
 * - 鍒╃敤 View Transition API 瀹炵幇娴佺晠鍔ㄧ敾
 * - 閫氳繃 CSS class 鎺у埗杩囨浮鏁堟灉
 * - 鑷姩璁＄畻鏈€澶ф墿鏁ｅ崐寰?
 *
 * @module utils/theme/animation
 * @author AiPay
 */
import { useCommon } from '@/hooks/core/useCommon'
import { useTheme } from '@/hooks/core/useTheme'
import { SystemThemeEnum } from '@/enums/appEnum'
import { useSettingStore } from '@/store/modules/setting'
const { LIGHT, DARK } = SystemThemeEnum

/**
 * 涓婚鍒囨崲鍔ㄧ敾
 * @param e 榧犳爣鐐瑰嚮浜嬩欢
 */
export const themeAnimation = (e: any) => {
  const x = e.clientX
  const y = e.clientY
  // 璁＄畻榧犳爣鐐瑰嚮浣嶇疆璺濈瑙嗙獥鐨勬渶澶у渾鍗婂緞
  const endRadius = Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y))

  // 璁剧疆CSS鍙橀噺
  document.documentElement.style.setProperty('--x', x + 'px')
  document.documentElement.style.setProperty('--y', y + 'px')
  document.documentElement.style.setProperty('--r', endRadius + 'px')

  if (document.startViewTransition) {
    document.startViewTransition(() => toggleTheme())
  } else {
    toggleTheme()
  }
}

/**
 * 鍒囨崲涓婚
 */
const toggleTheme = () => {
  useTheme().switchThemeStyles(useSettingStore().systemThemeType === LIGHT ? DARK : LIGHT)
  useCommon().refresh()
}

/**
 * 鍒囨崲涓婚杩囨浮鏁堟灉
 * @param enable 鏄惁鍚敤杩囨浮鏁堟灉
 */
export const toggleTransition = (enable: boolean) => {
  const body = document.body

  if (enable) {
    body.classList.add('theme-change')
  } else {
    setTimeout(() => {
      body.classList.remove('theme-change')
    }, 300)
  }
}

