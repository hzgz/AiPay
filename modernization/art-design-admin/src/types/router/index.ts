/**
 * 璺敱绫诲瀷瀹氫箟妯″潡
 *
 * 鎻愪緵璺敱鐩稿叧鐨勭被鍨嬪畾涔?
 *
 * ## 涓昏鍔熻兘
 *
 * - 璺敱鍏冩暟鎹被鍨嬶紙鏍囬銆佸浘鏍囥€佹潈闄愮瓑锛?
 * - 搴旂敤璺敱璁板綍绫诲瀷
 * - 璺敱閰嶇疆鎵╁睍
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 璺敱閰嶇疆绫诲瀷绾︽潫
 * - 璺敱鍏冩暟鎹畾涔?
 * - 鑿滃崟鐢熸垚
 * - 鏉冮檺鎺у埗
 *
 * @module types/router/index
 * @author AiPay
 */

import { RouteRecordRaw } from 'vue-router'

/**
 * 璺敱鍏冩暟鎹帴鍙?
 * 瀹氫箟璺敱鐨勫悇绉嶉厤缃睘鎬?
 */
export interface RouteMeta extends Record<string | number | symbol, unknown> {
  /** 璺敱鏍囬 */
  title: string
  /** 璺敱鍥炬爣 */
  icon?: string
  /** 鏄惁鏄剧ず寰界珷 */
  showBadge?: boolean
  /** 鏂囨湰寰界珷 */
  showTextBadge?: string
  /** 鏄惁鍦ㄨ彍鍗曚腑闅愯棌 */
  isHide?: boolean
  /** 鏄惁鍦ㄦ爣绛鹃〉涓殣钘?*/
  isHideTab?: boolean
  /** 澶栭儴閾炬帴 */
  link?: string
  /** 鏄惁涓篿frame */
  isIframe?: boolean
  /** 鏄惁缂撳瓨 */
  keepAlive?: boolean
  /** 鎿嶄綔鏉冮檺 */
  authList?: Array<{
    title: string
    authMark: string
  }>
  /** 鏄惁涓轰竴绾ц彍鍗?*/
  isFirstLevel?: boolean
  /** 瑙掕壊鏉冮檺 */
  roles?: string[]
  /** 鏄惁鍥哄畾鏍囩椤?*/
  fixedTab?: boolean
  /** 婵€娲昏彍鍗曡矾寰?*/
  activePath?: string
  /** 鏄惁涓哄叏灞忛〉闈?*/
  isFullPage?: boolean
  /** 鏄惁涓烘潈闄愭寜閽 */
  isAuthButton?: boolean
  /** 鏉冮檺鏍囪瘑 */
  authMark?: string
  /** 鐖剁骇璺緞 */
  parentPath?: string
}

/**
 * 搴旂敤璺敱璁板綍鎺ュ彛
 * 鎵╁睍 Vue Router 鐨勮矾鐢辫褰曠被鍨?
 */
export interface AppRouteRecord extends Omit<RouteRecordRaw, 'meta' | 'children' | 'component'> {
  id?: number
  meta: RouteMeta
  children?: AppRouteRecord[]
  component?: string | (() => Promise<any>)
}

