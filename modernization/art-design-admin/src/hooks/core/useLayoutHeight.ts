/**
 * useLayoutHeight - 椤甸潰甯冨眬楂樺害绠＄悊
 *
 * 鑷姩璁＄畻鍜岀鐞嗛〉闈㈠唴瀹瑰尯鍩熺殑楂樺害锛岀‘淇濆唴瀹瑰尯鍩熻兘澶熸纭～鍏呭墿浣欑┖闂淬€?
 * 鐩戝惉澶撮儴鍏冪礌楂樺害鍙樺寲锛屽姩鎬佽皟鏁村唴瀹瑰尯鍩熼珮搴︼紝閬垮厤鍑虹幇婊氬姩鏉℃垨甯冨眬閿欎贡銆?
 *
 * ## 涓昏鍔熻兘
 *
 * 1. 鍔ㄦ€侀珮搴﹁绠?- 鏍规嵁澶撮儴鍏冪礌楂樺害鑷姩璁＄畻鍐呭鍖哄煙楂樺害
 * 2. 鍝嶅簲寮忕洃鍚?- 鑷姩鐩戝惉鍏冪礌灏哄鍙樺寲骞舵洿鏂伴珮搴?
 * 3. CSS 鍙橀噺鍚屾 - 鑷姩鏇存柊 CSS 鍙橀噺锛屾柟渚垮叏灞€浣跨敤
 * 4. 鐏垫椿閰嶇疆 - 鏀寔鑷畾涔夐棿璺濄€丆SS 鍙橀噺鍚嶇瓑
 * 5. 鑷姩鏌ユ壘妯″紡 - 鎻愪緵閫氳繃 ID 鑷姩鏌ユ壘鍏冪礌鐨勪究鎹锋柟寮?
 *
 * @module useLayoutHeight
 * @author AiPay
 */

import { ref, computed, watch, onMounted } from 'vue'
import { useElementSize } from '@vueuse/core'

/**
 * 椤甸潰瀹瑰櫒楂樺害閰嶇疆
 */
interface LayoutHeightOptions {
  /** 棰濆鐨勯棿璺濓紙榛樿 15px锛?*/
  extraSpacing?: number
  /** 鏄惁鑷姩鏇存柊 CSS 鍙橀噺锛堥粯璁?true锛?*/
  updateCssVar?: boolean
  /** CSS 鍙橀噺鍚嶇О锛堥粯璁?'--art-full-height'锛?*/
  cssVarName?: string
}

export function useLayoutHeight(options: LayoutHeightOptions = {}) {
  const { extraSpacing = 15, updateCssVar = true, cssVarName = '--art-full-height' } = options

  // 鍏冪礌寮曠敤
  const headerRef = ref<HTMLElement>()
  const contentHeaderRef = ref<HTMLElement>()

  // 浣跨敤 VueUse 鑷姩鐩戝惉鍏冪礌灏哄鍙樺寲
  const { height: headerHeight } = useElementSize(headerRef)
  const { height: contentHeaderHeight } = useElementSize(contentHeaderRef)

  // 璁＄畻瀹瑰櫒鏈€灏忛珮搴︼紙鍝嶅簲寮忥級
  const containerMinHeight = computed(() => {
    const totalHeight = headerHeight.value + contentHeaderHeight.value + extraSpacing
    return `calc(100vh - ${totalHeight}px)`
  })

  if (updateCssVar) {
    watch(
      containerMinHeight,
      (newHeight) => {
        requestAnimationFrame(() => {
          document.documentElement.style.setProperty(cssVarName, newHeight)
        })
      },
      { immediate: true }
    )
  }

  return {
    /** 瀹瑰櫒鏈€灏忛珮搴︼紙鍝嶅簲寮忥級 */
    containerMinHeight,
    /** 澶撮儴鍏冪礌寮曠敤 */
    headerRef,
    /** 鍐呭澶撮儴鍏冪礌寮曠敤 */
    contentHeaderRef,
    /** 澶撮儴楂樺害锛堝搷搴斿紡锛?*/
    headerHeight,
    /** 鍐呭澶撮儴楂樺害锛堝搷搴斿紡锛?*/
    contentHeaderHeight
  }
}

/**
 * 閫氳繃 ID 鑷姩鏌ユ壘鍏冪礌鐨勫竷灞€楂樺害绠＄悊
 * 閫傜敤浜庢棤娉曠洿鎺ヨ幏鍙栧厓绱犲紩鐢ㄧ殑鍦烘櫙
 *
 * @param headerIds 澶撮儴鍏冪礌鐨?ID 鏁扮粍
 * @param options 閰嶇疆閫夐」
 *
 * ```
 */
export function useAutoLayoutHeight(
  headerIds: string[] = ['app-header', 'app-content-header'],
  options: LayoutHeightOptions = {}
) {
  const { extraSpacing = 15, updateCssVar = true, cssVarName = '--art-full-height' } = options

  // 鍒涘缓鍏冪礌寮曠敤
  const headerRef = ref<HTMLElement>()
  const contentHeaderRef = ref<HTMLElement>()

  // 浣跨敤 VueUse 鑷姩鐩戝惉鍏冪礌灏哄鍙樺寲
  const { height: headerHeight } = useElementSize(headerRef)
  const { height: contentHeaderHeight } = useElementSize(contentHeaderRef)

  // 璁＄畻瀹瑰櫒鏈€灏忛珮搴︼紙鍝嶅簲寮忥級
  const containerMinHeight = computed(() => {
    const totalHeight = headerHeight.value + contentHeaderHeight.value + extraSpacing
    return `calc(100vh - ${totalHeight}px)`
  })

  if (updateCssVar) {
    watch(
      containerMinHeight,
      (newHeight) => {
        requestAnimationFrame(() => {
          document.documentElement.style.setProperty(cssVarName, newHeight)
        })
      },
      { immediate: true }
    )
  }

  // 鍦?DOM 鎸傝浇鍚庢煡鎵惧厓绱?
  onMounted(() => {
    if (typeof document !== 'undefined') {
      // 浣跨敤 nextTick 纭繚 DOM 瀹屽叏娓叉煋
      requestAnimationFrame(() => {
        const header = document.getElementById(headerIds[0])
        const contentHeader = document.getElementById(headerIds[1])

        if (header) {
          headerRef.value = header
        }
        if (contentHeader) {
          contentHeaderRef.value = contentHeader
        }
      })
    }
  })

  return {
    /** 瀹瑰櫒鏈€灏忛珮搴︼紙鍝嶅簲寮忥級 */
    containerMinHeight,
    /** 澶撮儴鍏冪礌寮曠敤 */
    headerRef,
    /** 鍐呭澶撮儴鍏冪礌寮曠敤 */
    contentHeaderRef,
    /** 澶撮儴楂樺害锛堝搷搴斿紡锛?*/
    headerHeight,
    /** 鍐呭澶撮儴楂樺害锛堝搷搴斿紡锛?*/
    contentHeaderHeight
  }
}

