/**
 * v-auth 鏉冮檺鎸囦护
 *
 * 閫傜敤浜庡悗绔潈闄愭帶鍒舵ā寮忥紝鍩轰簬鏉冮檺鏍囪瘑鎺у埗 DOM 鍏冪礌鐨勬樉绀哄拰闅愯棌銆?
 * 濡傛灉鐢ㄦ埛娌℃湁瀵瑰簲鏉冮檺锛屽厓绱犲皢浠?DOM 涓Щ闄ゃ€?
 *
 * ## 涓昏鍔熻兘
 *
 * - 鏉冮檺楠岃瘉 - 鏍规嵁璺敱 meta 涓殑鏉冮檺鍒楄〃楠岃瘉鐢ㄦ埛鏉冮檺
 * - DOM 鎺у埗 - 鏃犳潈闄愭椂鑷姩绉婚櫎鍏冪礌锛岃€岄潪闅愯棌
 * - 鍝嶅簲寮忔洿鏂?- 鏉冮檺鍙樺寲鏃惰嚜鍔ㄦ洿鏂板厓绱犵姸鎬?
 *
 * ## 浣跨敤绀轰緥
 *
 * ```vue
 * <!-- 鍙湁鎷ユ湁 'add' 鏉冮檺鐨勭敤鎴锋墠鑳界湅鍒版柊澧炴寜閽?-->
 * <el-button v-auth="'add'">鏂板</el-button>
 *
 * <!-- 鍙湁鎷ユ湁 'edit' 鏉冮檺鐨勭敤鎴锋墠鑳界湅鍒扮紪杈戞寜閽?-->
 * <el-button v-auth="'edit'">缂栬緫</el-button>
 *
 * <!-- 鍙湁鎷ユ湁 'delete' 鏉冮檺鐨勭敤鎴锋墠鑳界湅鍒板垹闄ゆ寜閽?-->
 * <el-button v-auth="'delete'">鍒犻櫎</el-button>
 * ```
 *
 * ## 娉ㄦ剰浜嬮」
 *
 * - 璇ユ寚浠や細鐩存帴绉婚櫎 DOM 鍏冪礌锛岃€屼笉鏄娇鐢?v-if 闅愯棌
 * - 鏉冮檺鍒楄〃浠庡綋鍓嶈矾鐢辩殑 meta.authList 涓幏鍙?
 *
 * @module directives/auth
 * @author AiPay
 */

import { router } from '@/router'
import { App, Directive, DirectiveBinding } from 'vue'

export type AuthDirective = Directive<HTMLElement, string>

function checkAuthPermission(el: HTMLElement, binding: DirectiveBinding<string>): void {
  // 鑾峰彇褰撳墠璺敱鐨勬潈闄愬垪琛?
  const authList = (router.currentRoute.value.meta.authList as Array<{ authMark: string }>) || []

  // 妫€鏌ユ槸鍚︽湁瀵瑰簲鐨勬潈闄愭爣璇?
  const hasPermission = authList.some((item) => item.authMark === binding.value)

  // 濡傛灉娌℃湁鏉冮檺锛岀Щ闄ゅ厓绱?
  if (!hasPermission) {
    removeElement(el)
  }
}

function removeElement(el: HTMLElement): void {
  if (el.parentNode) {
    el.parentNode.removeChild(el)
  }
}

const authDirective: AuthDirective = {
  mounted: checkAuthPermission,
  updated: checkAuthPermission
}

export function setupAuthDirective(app: App): void {
  app.directive('auth', authDirective)
}

