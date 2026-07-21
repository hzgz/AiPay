/**
 * v-roles 瑙掕壊鏉冮檺鎸囦护
 *
 * 鍩轰簬鐢ㄦ埛瑙掕壊鎺у埗 DOM 鍏冪礌鐨勬樉绀哄拰闅愯棌銆?
 * 鍙鐢ㄦ埛鎷ユ湁鎸囧畾瑙掕壊涓殑浠绘剰涓€涓紝鍏冪礌灏变細鏄剧ず锛屽惁鍒欎粠 DOM 涓Щ闄ゃ€?
 *
 * ## 涓昏鍔熻兘
 *
 * - 瑙掕壊楠岃瘉 - 妫€鏌ョ敤鎴锋槸鍚︽嫢鏈夋寚瀹氳鑹?
 * - 澶氳鑹叉敮鎸?- 鏀寔鍗曚釜瑙掕壊鎴栧涓鑹诧紙婊¤冻鍏朵竴鍗冲彲锛?
 * - DOM 鎺у埗 - 鏃犳潈闄愭椂鑷姩绉婚櫎鍏冪礌锛岃€岄潪闅愯棌
 * - 鍝嶅簲寮忔洿鏂?- 瑙掕壊鍙樺寲鏃惰嚜鍔ㄦ洿鏂板厓绱犵姸鎬?
 *
 * ## 浣跨敤绀轰緥
 *
 * ```vue
 * <template>
 *   <!-- 鍗曚釜瑙掕壊 - 鍙湁瓒呯骇绠＄悊鍛樺彲瑙?-->
 *   <el-button v-roles="'R_SUPER'">瓒呯骇绠＄悊鍛樺姛鑳?/el-button>
 *
 *   <!-- 澶氫釜瑙掕壊 - 瓒呯骇绠＄悊鍛樻垨鏅€氱鐞嗗憳鍙 -->
 *   <el-button v-roles="['R_SUPER', 'R_ADMIN']">绠＄悊鍛樺姛鑳?/el-button>
 *
 *   <!-- 搴旂敤鍒颁换鎰忓厓绱?-->
 *   <div v-roles="['R_SUPER', 'R_ADMIN', 'R_USER']">
 *     鎵€鏈夌櫥褰曠敤鎴峰彲瑙佺殑鍐呭
 *   </div>
 * </template>
 * ```
 *
 * ## 鏉冮檺閫昏緫
 *
 * - 鐢ㄦ埛瑙掕壊浠?userStore.getUserInfo.roles 鑾峰彇
 * - 鍙鐢ㄦ埛鎷ユ湁鎸囧畾瑙掕壊涓殑浠绘剰涓€涓紝鍏冪礌灏变細鏄剧ず
 * - 濡傛灉鐢ㄦ埛娌℃湁浠讳綍瑙掕壊鎴栦笉婊¤冻鏉′欢锛屽厓绱犲皢琚Щ闄?
 *
 * ## 娉ㄦ剰浜嬮」
 *
 * - 璇ユ寚浠や細鐩存帴绉婚櫎 DOM 鍏冪礌锛岃€屼笉鏄娇鐢?v-if 闅愯棌
 * - 閫傜敤浜庡熀浜庤鑹茬殑绮楃矑搴︽潈闄愭帶鍒?
 * - 濡傞渶鍩轰簬鍏蜂綋鎿嶄綔鐨勭粏绮掑害鏉冮檺鎺у埗锛岃浣跨敤 v-auth 鎸囦护
 *
 * @module directives/roles
 * @author AiPay
 */

import { useUserStore } from '@/store/modules/user'
import { App, Directive, DirectiveBinding } from 'vue'

export type RolesDirective = Directive<HTMLElement, string | string[]>

function checkRolePermission(el: HTMLElement, binding: DirectiveBinding<string | string[]>): void {
  const userStore = useUserStore()
  const userRoles = userStore.getUserInfo.roles

  // 濡傛灉鐢ㄦ埛瑙掕壊涓虹┖鎴栨湭瀹氫箟锛岀Щ闄ゅ厓绱?
  if (!userRoles?.length) {
    removeElement(el)
    return
  }

  // 纭繚鎸囦护鍊间负鏁扮粍鏍煎紡
  const requiredRoles = Array.isArray(binding.value) ? binding.value : [binding.value]

  // 妫€鏌ョ敤鎴锋槸鍚﹀叿鏈夋墍闇€瑙掕壊涔嬩竴
  const hasPermission = requiredRoles.some((role: string) => userRoles.includes(role))

  // 濡傛灉娌℃湁鏉冮檺锛屽畨鍏ㄥ湴绉婚櫎鍏冪礌
  if (!hasPermission) {
    removeElement(el)
  }
}

function removeElement(el: HTMLElement): void {
  if (el.parentNode) {
    el.parentNode.removeChild(el)
  }
}

const rolesDirective: RolesDirective = {
  mounted: checkRolePermission,
  updated: checkRolePermission
}

export function setupRolesDirective(app: App): void {
  app.directive('roles', rolesDirective)
}

