/**
 * 宸ヤ綔鏍囩椤电鐞嗘ā鍧?
 *
 * 鎻愪緵宸ヤ綔鏍囩椤碉紙Worktab锛夌殑鑷姩绠＄悊鍔熻兘
 *
 * ## 涓昏鍔熻兘
 *
 * - 鏍规嵁璺敱瀵艰埅鑷姩鍒涘缓鍜屾洿鏂板伐浣滄爣绛鹃〉
 * - iframe 椤甸潰鏍囩椤电壒娈婂鐞?
 * - 鏍囩椤典俊鎭彁鍙栵紙鏍囬銆佽矾寰勩€佺紦瀛樼姸鎬佺瓑锛?
 * - 鍥哄畾鏍囩椤垫敮鎸?
 * - 鏍规嵁绯荤粺璁剧疆鎺у埗鏍囩椤垫樉绀?
 * - 棣栭〉鏍囩椤电壒娈婂鐞?
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 璺敱瀹堝崼涓嚜鍔ㄥ垱寤烘爣绛鹃〉
 * - 椤甸潰鍒囨崲鏃舵洿鏂版爣绛鹃〉鐘舵€?
 * - 澶氭爣绛鹃〉瀵艰埅绯荤粺
 *
 * @module utils/navigation/worktab
 * @author AiPay
 */
import { useWorktabStore } from '@/store/modules/worktab'
import { RouteLocationNormalized } from 'vue-router'
import { isIframe } from './route'
import { useSettingStore } from '@/store/modules/setting'
import { IframeRouteManager } from '@/router/core'
import { useCommon } from '@/hooks/core/useCommon'

/**
 * 鏍规嵁褰撳墠璺敱淇℃伅璁剧疆宸ヤ綔鏍囩椤碉紙worktab锛?
 * @param to 褰撳墠璺敱瀵硅薄
 */
export const setWorktab = (to: RouteLocationNormalized): void => {
  const worktabStore = useWorktabStore()
  const { meta, path, name, params, query } = to
  if (!meta.isHideTab) {
    // 濡傛灉鏄?iframe 椤甸潰锛屽垯鐗规畩澶勭悊宸ヤ綔鏍囩椤?
    if (isIframe(path)) {
      const iframeRoute = IframeRouteManager.getInstance().findByPath(to.path)

      if (iframeRoute?.meta) {
        worktabStore.openTab({
          title: iframeRoute.meta.title,
          icon: meta.icon as string,
          path,
          name: name as string,
          keepAlive: meta.keepAlive as boolean,
          params,
          query
        })
      }
    } else if (useSettingStore().showWorkTab || path === useCommon().homePath.value) {
      worktabStore.openTab({
        title: meta.title as string,
        icon: meta.icon as string,
        path,
        name: name as string,
        keepAlive: meta.keepAlive as boolean,
        params,
        query,
        fixedTab: meta.fixedTab as boolean
      })
    }
  }
}

