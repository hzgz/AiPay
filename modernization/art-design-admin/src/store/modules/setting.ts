/**
 * 绯荤粺璁剧疆鐘舵€佺鐞嗘ā鍧?
 *
 * 鎻愪緵瀹屾暣鐨勭郴缁熻缃姸鎬佺鐞?
 *
 * ## 涓昏鍔熻兘
 *
 * - 鑿滃崟甯冨眬閰嶇疆锛堝乏渚с€侀《閮ㄣ€佹贩鍚堛€佸弻鏍忥級
 * - 涓婚绠＄悊锛堜寒鑹层€佹殫鑹层€佽嚜鍔級
 * - 鑿滃崟涓婚鏍峰紡閰嶇疆
 * - 鐣岄潰鏄剧ず寮€鍏筹紙闈㈠寘灞戙€佹爣绛鹃〉銆佽瑷€鍒囨崲绛夛級
 * - 鍔熻兘寮€鍏筹紙鎵嬮鐞存ā寮忋€佽壊寮辨ā寮忋€佹按鍗扮瓑锛?
 * - 鏍峰紡閰嶇疆锛堣竟妗嗐€佸渾瑙掋€佸鍣ㄥ搴︺€侀〉闈㈣繃娓★級
 * - 鑺傛棩鍔熻兘閰嶇疆
 * - Element Plus 涓婚鑹插姩鎬佽缃?
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 璁剧疆闈㈡澘閰嶇疆绠＄悊
 * - 涓婚鍒囨崲鍜屾牱寮忓畾鍒?
 * - 鐣岄潰鍔熻兘寮€鍏虫帶鍒?
 * - 鐢ㄦ埛鍋忓ソ璁剧疆鎸佷箙鍖?
 *
 * ## 鎸佷箙鍖?
 *
 * - 浣跨敤 localStorage 瀛樺偍
 * - 瀛樺偍閿細sys-v{version}-setting
 * - 鏀寔璺ㄧ増鏈暟鎹縼绉?
 *
 * @module store/modules/setting
 * @author AiPay
 */
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { MenuThemeType } from '@/types/store'
import AppConfig from '@/config'
import { SystemThemeEnum, MenuThemeEnum, MenuTypeEnum, ContainerWidthEnum } from '@/enums/appEnum'
import { setElementThemeColor } from '@/utils/ui'
import { useCeremony } from '@/hooks/core/useCeremony'
import {
  resolveConsoleSettingStorageScope,
  readScopedSettingItem,
  scopedSettingStorage,
  writeScopedThemeValue
} from '@/utils/storage/setting-scope'
import { SETTING_DEFAULT_CONFIG } from '@/config/setting'

/**
 * 绯荤粺璁剧疆鐘舵€佺鐞?
 * 绠＄悊搴旂敤鐨勮彍鍗曘€佷富棰樸€佺晫闈㈡樉绀虹瓑鍚勯」璁剧疆
 */
export const useSettingStore = defineStore(
  'settingStore',
  () => {
    // 鑿滃崟鐩稿叧璁剧疆
    /** 鑿滃崟绫诲瀷 */
    const menuType = ref(SETTING_DEFAULT_CONFIG.menuType)
    /** 鑿滃崟灞曞紑瀹藉害 */
    const menuOpenWidth = ref(SETTING_DEFAULT_CONFIG.menuOpenWidth)
    /** 鑿滃崟鏄惁灞曞紑 */
    const menuOpen = ref(SETTING_DEFAULT_CONFIG.menuOpen)
    /** 鍙岃彍鍗曟槸鍚︽樉绀烘枃鏈?*/
    const dualMenuShowText = ref(SETTING_DEFAULT_CONFIG.dualMenuShowText)

    // 涓婚鐩稿叧璁剧疆
    /** 绯荤粺涓婚绫诲瀷 */
    const systemThemeType = ref(SETTING_DEFAULT_CONFIG.systemThemeType)
    /** 绯荤粺涓婚妯″紡 */
    const systemThemeMode = ref(SETTING_DEFAULT_CONFIG.systemThemeMode)
    /** 鑿滃崟涓婚绫诲瀷 */
    const menuThemeType = ref(SETTING_DEFAULT_CONFIG.menuThemeType)
    /** 绯荤粺涓婚棰滆壊 */
    const systemThemeColor = ref(SETTING_DEFAULT_CONFIG.systemThemeColor)

    // 鐣岄潰鏄剧ず璁剧疆
    /** 鏄惁鏄剧ず鑿滃崟鎸夐挳 */
    const showMenuButton = ref(SETTING_DEFAULT_CONFIG.showMenuButton)
    /** 鏄惁鏄剧ず蹇€熷叆鍙?*/
    const showFastEnter = ref(SETTING_DEFAULT_CONFIG.showFastEnter)
    /** 鏄惁鏄剧ず鍒锋柊鎸夐挳 */
    const showRefreshButton = ref(SETTING_DEFAULT_CONFIG.showRefreshButton)
    /** 鏄惁鏄剧ず闈㈠寘灞?*/
    const showCrumbs = ref(SETTING_DEFAULT_CONFIG.showCrumbs)
    /** 鏄惁鏄剧ず宸ヤ綔鍙版爣绛?*/
    const showWorkTab = ref(SETTING_DEFAULT_CONFIG.showWorkTab)
    /** 鏄惁鏄剧ず璇█鍒囨崲 */
    const showLanguage = ref(SETTING_DEFAULT_CONFIG.showLanguage)
    /** 鏄惁鏄剧ず杩涘害鏉?*/
    const showNprogress = ref(SETTING_DEFAULT_CONFIG.showNprogress)
    /** 鏄惁鏄剧ず璁剧疆寮曞 */
    const showSettingGuide = ref(SETTING_DEFAULT_CONFIG.showSettingGuide)
    /** 鏄惁鏄剧ず鑺傛棩鏂囨湰 */
    const showFestivalText = ref(SETTING_DEFAULT_CONFIG.showFestivalText)
    /** 鏄惁鏄剧ず姘村嵃 */
    const watermarkVisible = ref(SETTING_DEFAULT_CONFIG.watermarkVisible)

    // 鍔熻兘璁剧疆
    /** 鏄惁鑷姩鍏抽棴 */
    const autoClose = ref(SETTING_DEFAULT_CONFIG.autoClose)
    /** 鏄惁鍞竴灞曞紑 */
    const uniqueOpened = ref(SETTING_DEFAULT_CONFIG.uniqueOpened)
    /** 鏄惁鑹插急妯″紡 */
    const colorWeak = ref(SETTING_DEFAULT_CONFIG.colorWeak)
    /** 鏄惁鍒锋柊 */
    const refresh = ref(SETTING_DEFAULT_CONFIG.refresh)
    /** 鏄惁鍔犺浇鑺傛棩鐑熻姳 */
    const holidayFireworksLoaded = ref(SETTING_DEFAULT_CONFIG.holidayFireworksLoaded)

    // 鏍峰紡璁剧疆
    /** 杈规妯″紡 */
    const boxBorderMode = ref(SETTING_DEFAULT_CONFIG.boxBorderMode)
    /** 椤甸潰杩囨浮鏁堟灉 */
    const pageTransition = ref(SETTING_DEFAULT_CONFIG.pageTransition)
    /** 鏍囩椤垫牱寮?*/
    const tabStyle = ref(SETTING_DEFAULT_CONFIG.tabStyle)
    /** 鑷畾涔夊渾瑙?*/
    const customRadius = ref(SETTING_DEFAULT_CONFIG.customRadius)
    /** 瀹瑰櫒瀹藉害 */
    const containerWidth = ref(SETTING_DEFAULT_CONFIG.containerWidth)

    // 鑺傛棩鐩稿叧
    /** 鑺傛棩鏃ユ湡 */
    const festivalDate = ref('')

    const activeScope = ref(resolveConsoleSettingStorageScope())

    const persistedStateRefs = {
      menuType,
      menuOpenWidth,
      menuOpen,
      dualMenuShowText,
      systemThemeType,
      systemThemeMode,
      menuThemeType,
      systemThemeColor,
      showMenuButton,
      showFastEnter,
      showRefreshButton,
      showCrumbs,
      showWorkTab,
      showLanguage,
      showNprogress,
      showSettingGuide,
      showFestivalText,
      watermarkVisible,
      autoClose,
      uniqueOpened,
      colorWeak,
      refresh,
      holidayFireworksLoaded,
      boxBorderMode,
      pageTransition,
      tabStyle,
      customRadius,
      containerWidth,
      festivalDate
    } as const

    type PersistedStateKey = keyof typeof persistedStateRefs

    const applyPersistedState = (payload?: Partial<Record<PersistedStateKey, unknown>>) => {
      for (const key of Object.keys(persistedStateRefs) as PersistedStateKey[]) {
        const stateRef = persistedStateRefs[key]
        const defaultValue = SETTING_DEFAULT_CONFIG[key]
        stateRef.value = (payload && key in payload ? payload[key] : defaultValue) as never
      }
    }

    const syncPersistedScope = (path?: string) => {
      const nextScope = resolveConsoleSettingStorageScope(path)
      if (activeScope.value === nextScope) {
        return false
      }

      activeScope.value = nextScope

      const raw = readScopedSettingItem('setting', nextScope)
      if (!raw) {
        applyPersistedState()
        return true
      }

      try {
        applyPersistedState(JSON.parse(raw) as Partial<Record<PersistedStateKey, unknown>>)
      } catch (error) {
        console.warn('[SettingStore] Failed to parse scoped setting cache.', error)
        applyPersistedState()
      }

      return true
    }

    /**
     * 鑾峰彇鑿滃崟涓婚
     * 鏍规嵁褰撳墠涓婚绫诲瀷鍜屾殫鑹叉ā寮忚繑鍥炲搴旂殑涓婚閰嶇疆
     */
    const getMenuTheme = computed((): MenuThemeType => {
      const list = AppConfig.themeList.filter((item) => item.theme === menuThemeType.value)
      if (isDark.value) {
        return AppConfig.darkMenuStyles[0]
      } else {
        return list[0]
      }
    })

    /**
     * 鍒ゆ柇鏄惁涓烘殫鑹叉ā寮?
     */
    const isDark = computed((): boolean => {
      return systemThemeType.value === SystemThemeEnum.DARK
    })

    /**
     * 鑾峰彇鑿滃崟灞曞紑瀹藉害
     */
    const getMenuOpenWidth = computed((): string => {
      return menuOpenWidth.value + 'px' || SETTING_DEFAULT_CONFIG.menuOpenWidth + 'px'
    })

    /**
     * 鑾峰彇鑷畾涔夊渾瑙?
     */
    const getCustomRadius = computed((): string => {
      return customRadius.value + 'rem' || SETTING_DEFAULT_CONFIG.customRadius + 'rem'
    })

    /**
     * 鏄惁鏄剧ず鐑熻姳
     * 鏍规嵁褰撳墠鏃ユ湡鍜岃妭鏃ユ棩鏈熷垽鏂槸鍚︽樉绀虹儫鑺辨晥鏋?
     */
    const isShowFireworks = computed((): boolean => {
      return festivalDate.value === useCeremony().currentFestivalData.value?.date ? false : true
    })

    /**
     * 鍒囨崲鑿滃崟甯冨眬
     * @param type 鑿滃崟绫诲瀷
     */
    const switchMenuLayouts = (type: MenuTypeEnum) => {
      menuType.value = type
    }

    /**
     * 璁剧疆鑿滃崟灞曞紑瀹藉害
     * @param width 瀹藉害鍊?
     */
    const setMenuOpenWidth = (width: number) => {
      menuOpenWidth.value = width
    }

    /**
     * 璁剧疆鍏ㄥ眬涓婚
     * @param theme 涓婚绫诲瀷
     * @param themeMode 涓婚妯″紡
     */
    const setGlopTheme = (theme: SystemThemeEnum, themeMode: SystemThemeEnum) => {
      systemThemeType.value = theme
      systemThemeMode.value = themeMode
      writeScopedThemeValue(theme, activeScope.value)
    }

    /**
     * 鍒囨崲鑿滃崟鏍峰紡
     * @param theme 鑿滃崟涓婚
     */
    const switchMenuStyles = (theme: MenuThemeEnum) => {
      menuThemeType.value = theme
    }

    /**
     * 璁剧疆Element Plus涓婚棰滆壊
     * @param theme 涓婚棰滆壊
     */
    const setElementTheme = (theme: string) => {
      systemThemeColor.value = theme
      setElementThemeColor(theme)
    }

    /**
     * 鍒囨崲杈规妯″紡
     */
    const setBorderMode = () => {
      boxBorderMode.value = !boxBorderMode.value
    }

    /**
     * 璁剧疆瀹瑰櫒瀹藉害
     * @param width 瀹瑰櫒瀹藉害鏋氫妇鍊?
     */
    const setContainerWidth = (width: ContainerWidthEnum) => {
      containerWidth.value = width
    }

    /**
     * 鍒囨崲鍞竴灞曞紑妯″紡
     */
    const setUniqueOpened = () => {
      uniqueOpened.value = !uniqueOpened.value
    }

    /**
     * 鍒囨崲鑿滃崟鎸夐挳鏄剧ず
     */
    const setButton = () => {
      showMenuButton.value = !showMenuButton.value
    }

    /**
     * 鍒囨崲蹇€熷叆鍙ｆ樉绀?
     */
    const setFastEnter = () => {
      showFastEnter.value = !showFastEnter.value
    }

    /**
     * 鍒囨崲鑷姩鍏抽棴
     */
    const setAutoClose = () => {
      autoClose.value = !autoClose.value
    }

    /**
     * 鍒囨崲鍒锋柊鎸夐挳鏄剧ず
     */
    const setShowRefreshButton = () => {
      showRefreshButton.value = !showRefreshButton.value
    }

    /**
     * 鍒囨崲闈㈠寘灞戞樉绀?
     */
    const setCrumbs = () => {
      showCrumbs.value = !showCrumbs.value
    }

    /**
     * 璁剧疆宸ヤ綔鍙版爣绛炬樉绀?
     * @param show 鏄惁鏄剧ず
     */
    const setWorkTab = (show: boolean) => {
      showWorkTab.value = show
    }

    /**
     * 鍒囨崲璇█鍒囨崲鏄剧ず
     */
    const setLanguage = () => {
      showLanguage.value = !showLanguage.value
    }

    /**
     * 鍒囨崲杩涘害鏉℃樉绀?
     */
    const setNprogress = () => {
      showNprogress.value = !showNprogress.value
    }

    /**
     * 鍒囨崲鑹插急妯″紡
     */
    const setColorWeak = () => {
      colorWeak.value = !colorWeak.value
    }

    /**
     * 闅愯棌璁剧疆寮曞
     */
    const hideSettingGuide = () => {
      showSettingGuide.value = false
    }

    /**
     * 鏄剧ず璁剧疆寮曞
     */
    const openSettingGuide = () => {
      showSettingGuide.value = true
    }

    /**
     * 璁剧疆椤甸潰杩囨浮鏁堟灉
     * @param transition 杩囨浮鏁堟灉鍚嶇О
     */
    const setPageTransition = (transition: string) => {
      pageTransition.value = transition
    }

    /**
     * 璁剧疆鏍囩椤垫牱寮?
     * @param style 鏍峰紡鍚嶇О
     */
    const setTabStyle = (style: string) => {
      tabStyle.value = style
    }

    /**
     * 璁剧疆鑿滃崟灞曞紑鐘舵€?
     * @param open 鏄惁灞曞紑
     */
    const setMenuOpen = (open: boolean) => {
      menuOpen.value = open
    }

    /**
     * 鍒锋柊椤甸潰
     */
    const reload = () => {
      refresh.value = !refresh.value
    }

    /**
     * 璁剧疆姘村嵃鏄剧ず
     * @param visible 鏄惁鏄剧ず
     */
    const setWatermarkVisible = (visible: boolean) => {
      watermarkVisible.value = visible
    }

    /**
     * 璁剧疆鑷畾涔夊渾瑙?
     * @param radius 鍦嗚鍊?
     */
    const setCustomRadius = (radius: string) => {
      customRadius.value = radius
      document.documentElement.style.setProperty('--custom-radius', `${radius}rem`)
    }

    /**
     * 璁剧疆鑺傛棩鐑熻姳鍔犺浇鐘舵€?
     * @param isLoad 鏄惁宸插姞杞?
     */
    const setholidayFireworksLoaded = (isLoad: boolean) => {
      holidayFireworksLoaded.value = isLoad
    }

    /**
     * 璁剧疆鑺傛棩鏂囨湰鏄剧ず
     * @param show 鏄惁鏄剧ず
     */
    const setShowFestivalText = (show: boolean) => {
      showFestivalText.value = show
    }

    const setFestivalDate = (date: string) => {
      festivalDate.value = date
    }

    const setDualMenuShowText = (show: boolean) => {
      dualMenuShowText.value = show
    }

    return {
      menuType,
      menuOpenWidth,
      systemThemeType,
      systemThemeMode,
      menuThemeType,
      systemThemeColor,
      boxBorderMode,
      uniqueOpened,
      showMenuButton,
      showFastEnter,
      showRefreshButton,
      showCrumbs,
      autoClose,
      showWorkTab,
      showLanguage,
      showNprogress,
      colorWeak,
      showSettingGuide,
      pageTransition,
      tabStyle,
      menuOpen,
      refresh,
      watermarkVisible,
      customRadius,
      holidayFireworksLoaded,
      showFestivalText,
      festivalDate,
      dualMenuShowText,
      containerWidth,
      getMenuTheme,
      isDark,
      getMenuOpenWidth,
      getCustomRadius,
      isShowFireworks,
      activeScope,
      switchMenuLayouts,
      setMenuOpenWidth,
      setGlopTheme,
      switchMenuStyles,
      setElementTheme,
      setBorderMode,
      setContainerWidth,
      setUniqueOpened,
      setButton,
      setFastEnter,
      setAutoClose,
      setShowRefreshButton,
      setCrumbs,
      setWorkTab,
      setLanguage,
      setNprogress,
      setColorWeak,
      hideSettingGuide,
      openSettingGuide,
      setPageTransition,
      setTabStyle,
      setMenuOpen,
      reload,
      setWatermarkVisible,
      setCustomRadius,
      syncPersistedScope,
      setholidayFireworksLoaded,
      setShowFestivalText,
      setFestivalDate,
      setDualMenuShowText
    }
  },
  {
    persist: {
      key: 'setting',
      storage: scopedSettingStorage
    }
  }
)

