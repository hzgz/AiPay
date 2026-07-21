/**
 * 瀵艰埅璺宠浆宸ュ叿妯″潡
 *
 * 鎻愪緵缁熶竴鐨勯〉闈㈣烦杞拰瀵艰埅鍔熻兘
 *
 * ## 涓昏鍔熻兘
 *
 * - 澶栭儴閾炬帴鎵撳紑锛堟柊绐楀彛锛?
 * - 鑿滃崟椤硅烦杞鐞嗭紙鏀寔鍐呴儴璺敱鍜屽閮ㄩ摼鎺ワ級
 * - iframe 椤甸潰璺宠浆鏀寔
 * - 閫掑綊鏌ユ壘骞惰烦杞埌绗竴涓彲瑙佺殑瀛愯彍鍗?
 * - 鏅鸿兘鍒ゆ柇璺宠浆鐩爣绫诲瀷锛堝閮ㄩ摼鎺?鍐呴儴璺敱锛?
 *
 * @module utils/navigation/jump
 * @author AiPay
 */
import { AppRouteRecord } from '@/types/router'
import { router } from '@/router'
import { isNavigableMenuItem } from './route'

// 鎵撳紑澶栭儴閾炬帴
export const openExternalLink = (link: string) => {
  window.open(link, '_blank')
}

/**
 * 鑿滃崟璺宠浆
 * @param item 鑿滃崟椤?
 * @param jumpToFirst 鏄惁璺宠浆鍒扮涓€涓瓙鑿滃崟
 * @returns
 */
export const handleMenuJump = (item: AppRouteRecord, jumpToFirst: boolean = false) => {
  // 澶勭悊澶栭儴閾炬帴
  const { link, isIframe } = item.meta
  if (link && !isIframe) {
    return openExternalLink(link)
  }

  // 濡傛灉涓嶉渶瑕佽烦杞埌绗竴涓瓙鑿滃崟锛屾垨鑰呮病鏈夊瓙鑿滃崟锛岀洿鎺ヨ烦杞綋鍓嶈矾寰?
  if (!jumpToFirst || !item.children?.length) {
    return router.push(item.path)
  }

  // 閫掑綊鏌ユ壘绗竴涓彲瀵艰埅鐨勫彾瀛愯妭鐐硅彍鍗?
  const findFirstLeafMenu = (items: AppRouteRecord[]): AppRouteRecord | undefined => {
    for (const child of items) {
      if (isNavigableMenuItem(child)) {
        return child.children?.length ? findFirstLeafMenu(child.children) || child : child
      }
    }
    return undefined
  }

  const firstChild = findFirstLeafMenu(item.children)

  // 濡傛灉瀛愯彍鍗曢兘涓嶅彲瑙侊紝鍒欏洖閫€鍒扮埗绾ч〉闈㈣嚜韬€?
  if (!firstChild) {
    return router.push(item.path)
  }

  // 濡傛灉绗竴涓瓙鑿滃崟鏄閮ㄩ摼鎺ュ垯鎵撳紑鏂扮獥鍙?
  if (firstChild.meta?.link) {
    return openExternalLink(firstChild.meta.link)
  }

  // 璺宠浆鍒板瓙鑿滃崟璺緞
  router.push(firstChild.path)
}

