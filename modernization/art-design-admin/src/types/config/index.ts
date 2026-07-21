/**
 * 閰嶇疆绫诲瀷瀹氫箟妯″潡
 *
 * 鎻愪緵绯荤粺閰嶇疆鐩稿叧鐨勭被鍨嬪畾涔?
 *
 * ## 涓昏鍔熻兘
 *
 * - 涓婚璁剧疆绫诲瀷
 * - 鑿滃崟甯冨眬绫诲瀷
 * - 鑺傛棩閰嶇疆绫诲瀷
 * - 绯荤粺鍩虹閰嶇疆绫诲瀷
 * - 蹇€熷叆鍙ｉ厤缃被鍨?
 * - 椤堕儴鏍忓姛鑳介厤缃被鍨?
 * - 鐜閰嶇疆绫诲瀷
 * - 搴旂敤閰嶇疆绫诲瀷
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 绯荤粺閰嶇疆鏂囦欢绫诲瀷绾︽潫
 * - 閰嶇疆椤圭被鍨嬪畾涔?
 * - 閰嶇疆鏁版嵁楠岃瘉
 *
 * @module types/config/index
 * @author AiPay
 */

import { MenuTypeEnum, SystemThemeEnum } from '@/enums/appEnum'
import { MenuThemeType, SystemThemeTypes } from '@/types/store'

// 涓婚璁剧疆
export interface ThemeSetting {
  /** 涓婚鍚嶇О */
  name: string
  /** 绯荤粺涓婚绫诲瀷 */
  theme: SystemThemeEnum
  /** 涓婚棰滆壊鏁扮粍 */
  color: string[]
  /** 宸︿晶绾挎潯棰滆壊 */
  leftLineColor: string
  /** 鍙充晶绾挎潯棰滆壊 */
  rightLineColor: string
  /** 涓婚鍥剧墖 */
  img: string
}

// 鑿滃崟甯冨眬
export interface MenuLayout {
  /** 甯冨眬鍚嶇О */
  name: string
  /** 鑿滃崟绫诲瀷鍊?*/
  value: MenuTypeEnum
  /** 甯冨眬棰勮鍥?*/
  img: string
  /** 甯冨眬鎻忚堪 */
  description?: string
}

// 鑺傛棩閰嶇疆
export interface FestivalConfig {
  /** 鑺傛棩鏃ユ湡锛堝崟鏃ワ級鎴栧紑濮嬫棩鏈燂紙鏃ユ湡鑼冨洿锛?*/
  date: string
  /** 鑺傛棩缁撴潫鏃ユ湡锛堝彲閫夛紝鐢ㄤ簬璺ㄦ棩鏈熻妭鏃ワ級 */
  endDate?: string
  /** 鑺傛棩鍚嶇О */
  name: string
  /** 鐑熻姳鍥剧墖 */
  image: string
  /** 婊氬姩鏂囨湰 */
  scrollText: string
  /** 鏄惁婵€娲?*/
  isActive?: boolean
  /** 鐑熻姳鎾斁娆℃暟锛堝彲閫夛紝榛樿涓?3 娆★級 */
  count?: number
}

// 绯荤粺鍩虹閰嶇疆
export interface SystemBasicConfig {
  // 绯荤粺鍚嶇О
  name: string
  // 绯荤粺鎻忚堪
  description?: string
  // 绯荤粺logo
  logo?: string
  // 绯荤粺favicon
  favicon?: string
  // 鐗堟潈淇℃伅
  copyright?: string
}

// 蹇€熷叆鍙ｅ熀纭€椤?
export interface FastEnterBaseItem {
  /** 鍚嶇О */
  name: string
  /** 鏄惁鍚敤 */
  enabled?: boolean
  /** 鎺掑簭鏉冮噸 */
  order?: number
  /** 璺敱鍚嶇О */
  routeName?: string
  /** 澶栭儴閾炬帴 */
  link?: string
}

// 蹇€熷叆鍙ｅ簲鐢ㄩ」
export interface FastEnterApplication extends FastEnterBaseItem {
  /** 搴旂敤鎻忚堪 */
  description: string
  /** 鍥炬爣浠ｇ爜 */
  icon: string
  /** 鍥炬爣棰滆壊 */
  iconColor: string
}

// 蹇€熼摼鎺ラ」
export type FastEnterQuickLink = FastEnterBaseItem

// 蹇€熷叆鍙ｉ厤缃?
export interface FastEnterConfig {
  /** 搴旂敤鍒楄〃 */
  applications: FastEnterApplication[]
  /** 蹇€熼摼鎺?*/
  quickLinks: FastEnterQuickLink[]
  /** 鏄剧ず鏉′欢锛堝睆骞曞搴︼級 */
  minWidth?: number
}

// 绯荤粺閰嶇疆
export interface SystemConfig {
  // 绯荤粺鍩虹淇℃伅
  systemInfo: SystemBasicConfig
  // 绯荤粺涓婚鏍峰紡
  systemThemeStyles: SystemThemeTypes
  // 璁剧疆涓婚鍒楄〃
  settingThemeList: ThemeSetting[]
  // 鑿滃崟甯冨眬鍒楄〃
  menuLayoutList: MenuLayout[]
  // 涓婚鍒楄〃
  themeList: MenuThemeType[]
  // 鏆楄壊鑿滃崟鏍峰紡
  darkMenuStyles: MenuThemeType[]
  // 绯荤粺涓昏壊璋?
  systemMainColor: readonly string[]
  // 蹇€熷叆鍙ｉ厤缃?
  fastEnter?: FastEnterConfig
  // 椤堕儴鏍忓姛鑳介厤缃?
  headerBar?: HeaderBarFeatureConfig
}

// 鐜閰嶇疆
export interface EnvConfig {
  // 鐜鍚嶇О
  NODE_ENV: string
  // 搴旂敤鐗堟湰
  VITE_VERSION: string
  // 搴旂敤绔彛
  VITE_PORT: string
  // 搴旂敤鍩虹璺緞
  VITE_BASE_URL: string
  // API 鍦板潃
  VITE_API_URL: string
  // 鏄惁寮€鍚?Mock
  VITE_USE_MOCK?: string
  // 鏄惁寮€鍚帇缂?
  VITE_USE_GZIP?: string
  // 鏄惁寮€鍚?CDN
  VITE_USE_CDN?: string
}

// 搴旂敤閰嶇疆
export interface AppConfig extends SystemConfig {
  // 鐜閰嶇疆
  env: EnvConfig
  // 寮€鍙戞ā寮?
  isDev: boolean
  // 鐢熶骇妯″紡
  isProd: boolean
  // 娴嬭瘯妯″紡
  isTest: boolean
}

// 鍔熻兘閰嶇疆椤瑰熀纭€鎺ュ彛
export interface FeatureConfigItem {
  enabled: boolean
  description: string
}

// 椤堕儴鏍忓姛鑳介厤缃帴鍙?
export interface HeaderBarFeatureConfig {
  /** 鑿滃崟鎸夐挳 */
  menuButton: FeatureConfigItem
  /** 鍒锋柊鎸夐挳 */
  refreshButton: FeatureConfigItem
  /** 蹇€熷叆鍙?*/
  fastEnter: FeatureConfigItem
  /** 闈㈠寘灞戝鑸?*/
  breadcrumb: FeatureConfigItem
  /** 鍏ㄥ眬鎼滅储 */
  globalSearch: FeatureConfigItem
  /** 鍏ㄥ睆鍔熻兘 */
  fullscreen: FeatureConfigItem
  /** 閫氱煡鍔熻兘 */
  notification: FeatureConfigItem
  /** 鑱婂ぉ鍔熻兘 */
  chat: FeatureConfigItem
  /** 澶氳瑷€鍒囨崲 */
  language: FeatureConfigItem
  /** 璁剧疆闈㈡澘 */
  settings: FeatureConfigItem
  /** 涓婚鍒囨崲 */
  themeToggle: FeatureConfigItem
}

