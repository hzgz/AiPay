/**
 * useTheme - 绯荤粺涓婚绠＄悊
 *
 * 鎻愪緵瀹屾暣鐨勪富棰樺垏鎹㈠拰绠＄悊鍔熻兘锛屾敮鎸佷寒鑹层€佹殫鑹插拰鑷姩妯″紡銆?
 * 鑷姩澶勭悊涓婚鍒囨崲鏃剁殑杩囨浮鏁堟灉锛岀‘淇濆垏鎹㈡祦鐣呮棤闂儊銆?
 *
 * ## 涓昏鍔熻兘
 *
 * 1. 涓婚鍒囨崲 - 鏀寔浜壊銆佹殫鑹层€佽嚜鍔ㄤ笁绉嶄富棰樻ā寮?
 * 2. 鑷姩妯″紡 - 鏍规嵁绯荤粺鍋忓ソ鑷姩鍒囨崲涓婚
 * 3. 棰滆壊閫傞厤 - 鑷姩璋冩暣涓婚鑹茬殑鏄庢殫鍙樹綋锛? 涓眰绾э級
 * 4. 杩囨浮浼樺寲 - 鍒囨崲鏃朵复鏃剁鐢ㄨ繃娓℃晥鏋滐紝閬垮厤闂儊
 * 5. 鐘舵€佹寔涔呭寲 - 涓婚璁剧疆鑷姩淇濆瓨鍒?store
 *
 * ## 浣跨敤绀轰緥
 *
 * ```typescript
 * const { switchThemeStyles } = useTheme()
 *
 * // 鍒囨崲鍒版殫鑹蹭富棰?
 * switchThemeStyles(SystemThemeEnum.DARK)
 *
 * // 鍒囨崲鍒颁寒鑹蹭富棰?
 * switchThemeStyles(SystemThemeEnum.LIGHT)
 *
 * // 鍒囨崲鍒拌嚜鍔ㄦā寮忥紙璺熼殢绯荤粺锛?
 * switchThemeStyles(SystemThemeEnum.AUTO)
 * ```
 *
 * @module useTheme
 * @author AiPay
 */

import { useSettingStore } from '@/store/modules/setting'
import { SystemThemeEnum } from '@/enums/appEnum'
import AppConfig from '@/config'
import { SystemThemeTypes } from '@/types/store'
import { getDarkColor, getLightColor, setElementThemeColor } from '@/utils/ui'
import { usePreferredDark } from '@vueuse/core'
import { watch } from 'vue'

export function useTheme() {
  const settingStore = useSettingStore()

  // 绂佺敤杩囨浮鏁堟灉
  const disableTransitions = () => {
    const style = document.createElement('style')
    style.setAttribute('id', 'disable-transitions')
    style.textContent = '* { transition: none !important; }'
    document.head.appendChild(style)
  }

  // 鍚敤杩囨浮鏁堟灉
  const enableTransitions = () => {
    const style = document.getElementById('disable-transitions')
    if (style) {
      style.remove()
    }
  }

  // 璁剧疆绯荤粺涓婚
  const setSystemTheme = (theme: SystemThemeEnum, themeMode?: SystemThemeEnum) => {
    // 涓存椂绂佺敤杩囨浮鏁堟灉
    disableTransitions()

    const el = document.getElementsByTagName('html')[0]
    const isDark = theme === SystemThemeEnum.DARK

    if (!themeMode) {
      themeMode = theme
    }

    const currentTheme = AppConfig.systemThemeStyles[theme as keyof SystemThemeTypes]

    if (currentTheme) {
      el.setAttribute('class', currentTheme.className)
    }

    // 璁剧疆鎸夐挳棰滆壊鍔犳繁鎴栧彉娴?
    const primary = settingStore.systemThemeColor

    for (let i = 1; i <= 9; i++) {
      document.documentElement.style.setProperty(
        `--el-color-primary-light-${i}`,
        isDark ? `${getDarkColor(primary, i / 10)}` : `${getLightColor(primary, i / 10)}`
      )
    }

    // 鏇存柊store涓殑涓婚璁剧疆
    settingStore.setGlopTheme(theme, themeMode)

    // 浣跨敤 requestAnimationFrame 纭繚鍦ㄤ笅涓€甯ф仮澶嶈繃娓℃晥鏋?
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        enableTransitions()
      })
    })
  }

  // 浣跨敤 VueUse 鐨?usePreferredDark 妫€娴嬬郴缁熶富棰樺亸濂?
  const prefersDark = usePreferredDark()

  // 鑷姩璁剧疆绯荤粺涓婚
  const setSystemAutoTheme = () => {
    const theme = prefersDark.value ? SystemThemeEnum.DARK : SystemThemeEnum.LIGHT
    setSystemTheme(theme, SystemThemeEnum.AUTO)
  }

  // 鍒囨崲涓婚
  const switchThemeStyles = (theme: SystemThemeEnum) => {
    if (theme === SystemThemeEnum.AUTO) {
      setSystemAutoTheme()
    } else {
      setSystemTheme(theme)
    }
  }

  return {
    setSystemTheme,
    setSystemAutoTheme,
    switchThemeStyles,
    prefersDark
  }
}

/**
 * 鍒濆鍖栦富棰樼郴缁?
 */
export function initializeTheme() {
  const settingStore = useSettingStore()
  const prefersDark = usePreferredDark()

  // 鏍规嵁绯荤粺鍋忓ソ搴旂敤涓婚
  const applyThemeByMode = () => {
    const el = document.getElementsByTagName('html')[0]
    let actualTheme = settingStore.systemThemeType

    // 濡傛灉鏄?AUTO 妯″紡锛屾娴嬬郴缁熷亸濂?
    if (settingStore.systemThemeMode === SystemThemeEnum.AUTO) {
      actualTheme = prefersDark.value ? SystemThemeEnum.DARK : SystemThemeEnum.LIGHT
      // 鏇存柊瀹為檯搴旂敤鐨勪富棰樼被鍨?
      settingStore.systemThemeType = actualTheme
    }

    // 璁剧疆涓婚 class
    const currentTheme = AppConfig.systemThemeStyles[actualTheme as keyof SystemThemeTypes]
    if (currentTheme) {
      el.setAttribute('class', currentTheme.className)
    }

    // 璁剧疆涓婚棰滆壊
    setElementThemeColor(settingStore.systemThemeColor)

    // 璁剧疆鍦嗚
    document.documentElement.style.setProperty('--custom-radius', `${settingStore.customRadius}rem`)
  }

  // 搴旂敤涓婚
  applyThemeByMode()

  // 濡傛灉鏄?AUTO 妯″紡锛岀洃鍚郴缁熶富棰樺彉鍖栵紙浣跨敤 VueUse 鐨勫搷搴斿紡鐗规€э級
  if (settingStore.systemThemeMode === SystemThemeEnum.AUTO) {
    watch(
      prefersDark,
      () => {
        // 鍙湁鍦?AUTO 妯″紡涓嬫墠鍝嶅簲绯荤粺涓婚鍙樺寲
        if (settingStore.systemThemeMode === SystemThemeEnum.AUTO) {
          applyThemeByMode()
        }
      },
      { immediate: false }
    )
  }
}

