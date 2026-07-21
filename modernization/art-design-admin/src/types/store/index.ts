/**
 * Store 鐘舵€佺被鍨嬪畾涔夋ā鍧?
 *
 * 鎻愪緵 Pinia Store 鐨勭姸鎬佺被鍨嬪畾涔?
 *
 * ## 涓昏鍔熻兘
 *
 * - 绯荤粺涓婚绫诲瀷
 * - 鑿滃崟涓婚绫诲瀷
 * - 璁剧疆鐘舵€佺被鍨?
 * - 宸ヤ綔鏍囩椤电被鍨?
 * - 鐢ㄦ埛鐘舵€佺被鍨?
 * - 鑿滃崟鐘舵€佺被鍨?
 * - 鏍圭姸鎬佺被鍨?
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - Store 鐘舵€佺被鍨嬬害鏉?
 * - 鐘舵€佹暟鎹粨鏋勫畾涔?
 * - 绫诲瀷鎻愮ず鍜岃嚜鍔ㄨˉ鍏?
 *
 * @module types/store/index
 * @author AiPay
 */

import { MenuThemeEnum, SystemThemeEnum } from '@/enums/appEnum'
import { LocationQueryRaw } from 'vue-router'

// 绯荤粺涓婚鏍峰紡锛坙ight | dark锛?
export interface SystemThemeType {
  /** 涓婚绫诲悕 */
  className: string
}

// 瀹氫箟鍖呭惈澶氫釜涓婚鐨勭被鍨?
export type SystemThemeTypes = {
  [key in Exclude<SystemThemeEnum, SystemThemeEnum.AUTO>]: SystemThemeType
}

// 鑿滃崟涓婚鏍峰紡
export interface MenuThemeType {
  /** 涓婚绫诲瀷 */
  theme: MenuThemeEnum
  /** 鑳屾櫙棰滆壊 */
  background: string
  /** 绯荤粺鍚嶇О棰滆壊 */
  systemNameColor: string
  /** 鏂囨湰棰滆壊 */
  textColor: string
  /** 鍥炬爣棰滆壊 */
  iconColor: string
  /** 鑳屾櫙鍥剧墖 */
  img?: string
}

// 璁剧疆涓績
export interface SettingState {
  /** 涓婚 */
  theme: string
  /** 鏄惁鍙繚鎸佷竴涓瓙鑿滃崟鐨勫睍寮€ */
  uniqueOpened: boolean
  /** 鏄惁鏄剧ず鑿滃崟鎸夐挳 */
  menuButton: boolean
  /** 鏄惁鏄剧ず鍒锋柊鎸夐挳 */
  showRefreshButton: boolean
  /** 鏄惁鏄剧ず闈㈠寘灞?*/
  showCrumbs: boolean
  /** 鏄惁鑷姩鍏抽棴 */
  autoClose: boolean
  /** 鏄惁鏄剧ず宸ヤ綔鏍囩椤?*/
  showWorkTab: boolean
  /** 鏄惁鏄剧ず璇█鍒囨崲 */
  showLanguage: boolean
  /** 鏄惁鏄剧ず杩涘害鏉?*/
  showNprogress: boolean
  /** 涓婚妯″紡 */
  themeModel: string
}

// 澶氭爣绛?
export interface WorkTab {
  /** 鏍囩鏍囬 */
  title: string
  /** 鑷畾涔夋爣棰?*/
  customTitle?: string
  /** 璺敱璺緞 */
  path: string
  /** 璺敱鍚嶇О */
  name: string
  /** 鏄惁缂撳瓨 */
  keepAlive: boolean
  /** 鏄惁鍥哄畾鏍囩 */
  fixedTab?: boolean
  /** 璺敱鍙傛暟 */
  params?: object
  /** 璺敱鏌ヨ鍙傛暟 */
  query?: LocationQueryRaw
  /** 鍥炬爣 */
  icon?: string
  /** 鏄惁婵€娲?*/
  isActive?: boolean
}

// 鐢ㄦ埛Store鐘舵€?
export interface UserState {
  /** 鐢ㄦ埛淇℃伅 */
  userInfo: Api.Auth.UserInfo | null
  /** 璁よ瘉浠ょ墝 */
  token: string | null
  /** 鐢ㄦ埛瑙掕壊鍒楄〃 */
  roles: string[]
  /** 鐢ㄦ埛鏉冮檺鍒楄〃 */
  permissions: string[]
}

// 璁剧疆Store鐘舵€?
export interface SettingStoreState extends SettingState {
  // 棰濆鐨勮缃姸鎬?
  /** 鑿滃崟鏄惁鎶樺彔 */
  collapsed: boolean
  /** 璁惧绫诲瀷 */
  device: 'desktop' | 'mobile'
  /** 褰撳墠璇█ */
  language: string
}

// 宸ヤ綔鏍囩椤礢tore鐘舵€?
export interface WorkTabState {
  /** 鏍囩椤靛垪琛?*/
  tabs: WorkTab[]
  /** 褰撳墠婵€娲荤殑鏍囩椤?*/
  activeTab: string
  /** 缂撳瓨鐨勬爣绛鹃〉鍒楄〃 */
  cachedTabs: string[]
}

// 鑿滃崟Store鐘舵€?
export interface MenuState {
  /** 鑿滃崟鍒楄〃 */
  menuList: any[]
  /** 鑿滃崟鏄惁宸插姞杞?*/
  isLoaded: boolean
  /** 鑿滃崟鏄惁鎶樺彔 */
  collapsed: boolean
}

// 鏍筍tore鐘舵€佺被鍨?
export interface RootState {
  /** 鐢ㄦ埛鐘舵€?*/
  user: UserState
  /** 璁剧疆鐘舵€?*/
  setting: SettingStoreState
  /** 宸ヤ綔鏍囩椤电姸鎬?*/
  workTab: WorkTabState
  /** 鑿滃崟鐘舵€?*/
  menu: MenuState
}

