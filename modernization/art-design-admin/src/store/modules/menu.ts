/**
 * 鑿滃崟鐘舵€佺鐞嗘ā鍧?
 *
 * 鎻愪緵鑿滃崟鏁版嵁鍜屽姩鎬佽矾鐢辩殑鐘舵€佺鐞?
 *
 * ## 涓昏鍔熻兘
 *
 * - 鑿滃崟鍒楄〃瀛樺偍鍜岀鐞?
 * - 棣栭〉璺緞閰嶇疆
 * - 鍔ㄦ€佽矾鐢辨敞鍐屽拰绉婚櫎
 * - 璺敱绉婚櫎鍑芥暟绠＄悊
 * - 鑿滃崟瀹藉害閰嶇疆
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 鍔ㄦ€佽彍鍗曞姞杞藉拰娓叉煋
 * - 璺敱鏉冮檺鎺у埗
 * - 棣栭〉璺緞鍔ㄦ€佽缃?
 * - 鐧诲嚭鏃舵竻鐞嗗姩鎬佽矾鐢?
 *
 * ## 宸ヤ綔娴佺▼
 *
 * 1. 鑾峰彇鑿滃崟鏁版嵁锛堝墠绔?鍚庣妯″紡锛?
 * 2. 璁剧疆鑿滃崟鍒楄〃鍜岄椤佃矾寰?
 * 3. 娉ㄥ唽鍔ㄦ€佽矾鐢卞苟淇濆瓨绉婚櫎鍑芥暟
 * 4. 鐧诲嚭鏃惰皟鐢ㄧЩ闄ゅ嚱鏁版竻鐞嗚矾鐢?
 *
 * @module store/modules/menu
 * @author AiPay
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { AppRouteRecord } from '@/types/router'
import { getFirstMenuPath } from '@/utils'
import { HOME_PAGE_PATH } from '@/router'

/**
 * 鑿滃崟鐘舵€佺鐞?
 * 绠＄悊搴旂敤鐨勮彍鍗曞垪琛ㄣ€侀椤佃矾寰勩€佽彍鍗曞搴﹀拰鍔ㄦ€佽矾鐢辩Щ闄ゅ嚱鏁?
 */
export const useMenuStore = defineStore('menuStore', () => {
  /** 棣栭〉璺緞 */
  const homePath = ref(HOME_PAGE_PATH)
  /** 鑿滃崟鍒楄〃 */
  const menuList = ref<AppRouteRecord[]>([])
  /** 鑿滃崟瀹藉害 */
  const menuWidth = ref('')
  /** 瀛樺偍璺敱绉婚櫎鍑芥暟鐨勬暟缁?*/
  const removeRouteFns = ref<(() => void)[]>([])

  /**
   * 璁剧疆鑿滃崟鍒楄〃
   * @param list 鑿滃崟璺敱璁板綍鏁扮粍
   */
  const setMenuList = (list: AppRouteRecord[]) => {
    menuList.value = list
    setHomePath(HOME_PAGE_PATH || getFirstMenuPath(list))
  }

  /**
   * 鑾峰彇棣栭〉璺緞
   * @returns 棣栭〉璺緞瀛楃涓?
   */
  const getHomePath = () => homePath.value

  /**
   * 璁剧疆涓婚〉璺緞
   * @param path 涓婚〉璺緞
   */
  const setHomePath = (path: string) => {
    homePath.value = path
  }

  /**
   * 娣诲姞璺敱绉婚櫎鍑芥暟
   * @param fns 瑕佹坊鍔犵殑璺敱绉婚櫎鍑芥暟鏁扮粍
   */
  const addRemoveRouteFns = (fns: (() => void)[]) => {
    removeRouteFns.value.push(...fns)
  }

  /**
   * 绉婚櫎鎵€鏈夊姩鎬佽矾鐢?
   * 鎵ц鎵€鏈夊瓨鍌ㄧ殑璺敱绉婚櫎鍑芥暟骞舵竻绌烘暟缁?
   */
  const removeAllDynamicRoutes = () => {
    removeRouteFns.value.forEach((fn) => fn())
    removeRouteFns.value = []
  }

  /**
   * 娓呯┖璺敱绉婚櫎鍑芥暟鏁扮粍
   */
  const clearRemoveRouteFns = () => {
    removeRouteFns.value = []
  }

  return {
    menuList,
    menuWidth,
    removeRouteFns,
    setMenuList,
    getHomePath,
    setHomePath,
    addRemoveRouteFns,
    removeAllDynamicRoutes,
    clearRemoveRouteFns
  }
})

