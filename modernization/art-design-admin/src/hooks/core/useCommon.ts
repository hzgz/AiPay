/**
 * useCommon - 閫氱敤鍔熻兘闆嗗悎
 *
 * 鎻愪緵甯哥敤鐨勯〉闈㈡搷浣滃姛鑳斤紝鍖呮嫭椤甸潰鍒锋柊銆佹粴鍔ㄦ帶鍒躲€佽矾寰勮幏鍙栫瓑銆?
 * 杩欎簺鍔熻兘鍦ㄥ涓〉闈㈠拰缁勪欢涓兘浼氱敤鍒帮紝缁熶竴灏佽渚夸簬澶嶇敤銆?
 *
 * ## 涓昏鍔熻兘
 *
 * 1. 棣栭〉璺緞 - 鑾峰彇绯荤粺閰嶇疆鐨勯椤佃矾寰?
 * 2. 椤甸潰鍒锋柊 - 鍒锋柊褰撳墠椤甸潰鍐呭
 * 3. 婊氬姩鎺у埗 - 鎻愪緵澶氱婊氬姩鍒伴《閮ㄥ拰鎸囧畾浣嶇疆鐨勬柟娉?
 * 4. 骞虫粦婊氬姩 - 鏀寔骞虫粦婊氬姩鍔ㄧ敾鏁堟灉
 *
 * @module useCommon
 * @author AiPay
 */

import { computed } from 'vue'
import { useMenuStore } from '@/store/modules/menu'
import { useSettingStore } from '@/store/modules/setting'

export function useCommon() {
  const menuStore = useMenuStore()
  const settingStore = useSettingStore()

  /**
   * 棣栭〉璺緞
   * 浠庤彍鍗?store 涓幏鍙栭厤缃殑棣栭〉璺緞
   */
  const homePath = computed(() => menuStore.getHomePath())

  /**
   * 鍒锋柊褰撳墠椤甸潰
   * 閫氳繃鍒囨崲 setting store 涓殑 refresh 鐘舵€佽Е鍙戦〉闈㈤噸鏂版覆鏌?
   */
  const refresh = () => {
    settingStore.reload()
  }

  /**
   * 婊氬姩鍒伴〉闈㈤《閮?
   * 鏌ユ壘涓诲唴瀹瑰尯鍩熷苟灏嗗叾婊氬姩浣嶇疆閲嶇疆涓洪《閮?
   */
  const scrollToTop = () => {
    const scrollContainer = document.getElementById('app-main')
    if (scrollContainer) {
      scrollContainer.scrollTop = 0
    }
  }

  /**
   * 骞虫粦婊氬姩鍒伴〉闈㈤《閮?
   * 浣跨敤 smooth 琛屼负瀹炵幇骞虫粦婊氬姩鏁堟灉
   */
  const smoothScrollToTop = () => {
    const scrollContainer = document.getElementById('app-main')
    if (scrollContainer) {
      scrollContainer.scrollTo({
        top: 0,
        behavior: 'smooth'
      })
    }
  }

  /**
   * 婊氬姩鍒版寚瀹氫綅缃?
   * @param top 鐩爣婊氬姩浣嶇疆锛堝儚绱狅級
   * @param smooth 鏄惁浣跨敤骞虫粦婊氬姩
   */
  const scrollTo = (top: number, smooth: boolean = false) => {
    const scrollContainer = document.getElementById('app-main')
    if (scrollContainer) {
      scrollContainer.scrollTo({
        top,
        behavior: smooth ? 'smooth' : 'auto'
      })
    }
  }

  return {
    homePath,
    refresh,
    scrollTo,
    scrollToTop,
    smoothScrollToTop
  }
}

