/**
 * 璺敱宸ュ叿妯″潡
 *
 * 鎻愪緵璺敱澶勭悊鍜岃彍鍗曡矾寰勭浉鍏崇殑宸ュ叿鍑芥暟
 *
 * ## 涓昏鍔熻兘
 *
 * - iframe 璺敱妫€娴嬶紝鍒ゆ柇鏄惁涓哄閮ㄥ祵鍏ラ〉闈?
 * - 鑿滃崟椤规湁鏁堟€ч獙璇侊紝杩囨护闅愯棌鍜屾棤鏁堣彍鍗?
 * - 璺緞鏍囧噯鍖栧鐞嗭紝缁熶竴璺緞鏍煎紡
 * - 閫掑綊鏌ユ壘鑿滃崟鏍戜腑绗竴涓湁鏁堣矾寰?
 * - 鏀寔澶氱骇宓屽鑿滃崟鐨勮矾寰勮В鏋?
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 绯荤粺鍒濆鍖栨椂鑾峰彇榛樿璺宠浆璺緞
 * - 鑿滃崟鏉冮檺杩囨护鍚庤幏鍙栭涓彲璁块棶椤甸潰
 * - 璺敱閲嶅畾鍚戦€昏緫澶勭悊
 * - iframe 椤甸潰鐗规畩澶勭悊
 *
 * @module utils/navigation/route
 * @author AiPay
 */

import { AppRouteRecord } from '@/types'

// 妫€鏌ユ槸鍚︿负 iframe 璺敱
export function isIframe(url: string): boolean {
  return url.startsWith('/outside/iframe/')
}

/**
 * 鍒ゆ柇鑿滃崟椤规槸鍚﹀彲浣滀负榛樿瀵艰埅钀界偣
 * 闅愯棌鐨勫叏灞忛〉闈㈣櫧鐒朵笉灞曠ず鍦ㄨ彍鍗曚腑锛屼絾浠嶇劧鍙兘鏄悎娉曢椤点€?
 */
export const isNavigableMenuItem = (menuItem: AppRouteRecord): boolean => {
  if (!menuItem.path || !menuItem.path.trim()) {
    return false
  }

  if (!menuItem.meta?.isHide) {
    return true
  }

  return menuItem.meta?.isFullPage === true
}

/**
 * 鏍囧噯鍖栬矾寰勬牸寮?
 * @param path 璺緞
 * @returns 鏍囧噯鍖栧悗鐨勮矾寰?
 */
const normalizePath = (path: string): string => {
  return path.startsWith('/') ? path : `/${path}`
}

/**
 * 閫掑綊鑾峰彇鑿滃崟鐨勭涓€涓湁鏁堣矾寰?
 * @param menuList 鑿滃崟鍒楄〃
 * @returns 绗竴涓湁鏁堣矾寰勶紝濡傛灉娌℃湁鎵惧埌鍒欒繑鍥炵┖瀛楃涓?
 */
export const getFirstMenuPath = (menuList: AppRouteRecord[]): string => {
  if (!Array.isArray(menuList) || menuList.length === 0) {
    return ''
  }

  for (const menuItem of menuList) {
    if (!isNavigableMenuItem(menuItem)) {
      continue
    }

    // 濡傛灉鏈夊瓙鑿滃崟锛屼紭鍏堟煡鎵惧瓙鑿滃崟
    if (menuItem.children?.length) {
      const childPath = getFirstMenuPath(menuItem.children)
      if (childPath) {
        return childPath
      }
    }

    // 杩斿洖褰撳墠鑿滃崟椤圭殑鏍囧噯鍖栬矾寰?
    return normalizePath(menuItem.path!)
  }

  return ''
}

