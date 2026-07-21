/**
 * 閰嶇疆鍥剧墖璧勬簮
 *
 * 缁熶竴绠＄悊璁剧疆涓績浣跨敤鐨勯瑙堝浘鐗囪祫婧愩€?
 * 鍖呭惈涓婚鏍峰紡銆佽彍鍗曞竷灞€銆佽彍鍗曢鏍肩殑棰勮鍥俱€?
 *
 * ## 鍥剧墖鍒嗙被
 *
 * - themeStyles: 绯荤粺涓婚棰勮鍥撅紙浜壊/鏆楄壊/鑷姩锛?
 * - menuLayouts: 鑿滃崟甯冨眬棰勮鍥撅紙宸︿晶/椤堕儴/娣峰悎/鍙屾爮锛?
 * - menuStyles: 鑿滃崟椋庢牸棰勮鍥撅紙璁捐/鏆楄壊/浜壊锛?
 *
 * @module config/assets/images
 * @author AiPay
 */

import lightTheme from '@imgs/settings/theme_styles/light.png'
import darkTheme from '@imgs/settings/theme_styles/dark.png'
import systemTheme from '@imgs/settings/theme_styles/system.png'
import verticalLayout from '@imgs/settings/menu_layouts/vertical.png'
import horizontalLayout from '@imgs/settings/menu_layouts/horizontal.png'
import mixedLayout from '@imgs/settings/menu_layouts/mixed.png'
import dualColumnLayout from '@imgs/settings/menu_layouts/dual_column.png'
import designStyle from '@imgs/settings/menu_styles/design.png'
import darkStyle from '@imgs/settings/menu_styles/dark.png'
import lightStyle from '@imgs/settings/menu_styles/light.png'

/**
 * 閰嶇疆涓績鍥剧墖璧勬簮瀵硅薄
 */
export const configImages = {
  /** 绯荤粺涓婚棰勮鍥?*/
  themeStyles: {
    /** 浜壊涓婚 */
    light: lightTheme,
    /** 鏆楄壊涓婚 */
    dark: darkTheme,
    /** 鑷姩涓婚锛堣窡闅忕郴缁燂級 */
    system: systemTheme
  },
  /** 鑿滃崟甯冨眬棰勮鍥?*/
  menuLayouts: {
    /** 宸︿晶鑿滃崟 */
    vertical: verticalLayout,
    /** 椤堕儴鑿滃崟 */
    horizontal: horizontalLayout,
    /** 娣峰悎鑿滃崟 */
    mixed: mixedLayout,
    /** 鍙屾爮鑿滃崟 */
    dualColumn: dualColumnLayout
  },
  /** 鑿滃崟椋庢牸棰勮鍥?*/
  menuStyles: {
    /** 璁捐椋庢牸 */
    design: designStyle,
    /** 鏆楄壊椋庢牸 */
    dark: darkStyle,
    /** 浜壊椋庢牸 */
    light: lightStyle
  }
}

