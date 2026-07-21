/**
 * useHeaderBar - 椤堕儴鏍忓姛鑳界鐞?
 *
 * 缁熶竴绠＄悊椤堕儴鏍忓悇涓姛鑳芥ā鍧楃殑鏄剧ず鐘舵€佸拰閰嶇疆淇℃伅銆?
 * 鎻愪緵鐏垫椿鐨勫姛鑳藉紑鍏虫帶鍒讹紝鏀寔鍔ㄦ€佹樉绀?闅愯棌椤堕儴鏍忕殑鍚勪釜鍔熻兘鎸夐挳銆?
 *
 * ## 涓昏鍔熻兘
 *
 * 1. 鍔熻兘寮€鍏虫帶鍒?- 缁熶竴绠＄悊鑿滃崟鎸夐挳銆佸埛鏂版寜閽€佸揩閫熷叆鍙ｇ瓑鍔熻兘鐨勬樉绀虹姸鎬?
 * 2. 閰嶇疆淇℃伅鑾峰彇 - 鑾峰彇鍚勪釜鍔熻兘妯″潡鐨勮缁嗛厤缃俊鎭?
 * 3. 鍔熻兘鍒楄〃鏌ヨ - 蹇€熻幏鍙栨墍鏈夊惎鐢ㄦ垨绂佺敤鐨勫姛鑳藉垪琛?
 * 4. 鍝嶅簲寮忕姸鎬?- 鎵€鏈夌姸鎬佽嚜鍔ㄥ搷搴旈厤缃拰 store 鍙樺寲
 *
 * @module useHeaderBar
 * @author AiPay
 */

import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useSettingStore } from '@/store/modules/setting'
import { headerBarConfig } from '@/config/modules/headerBar'
import { HeaderBarFeatureConfig } from '@/types'

/**
 * 椤堕儴鏍忓姛鑳界鐞?
 * @returns 椤堕儴鏍忓姛鑳界浉鍏崇殑鐘舵€佸拰鏂规硶
 */
export function useHeaderBar() {
  const settingStore = useSettingStore()

  // 鑾峰彇椤堕儴鏍忛厤缃?
  const headerBarConfigRef = computed<HeaderBarFeatureConfig>(() => headerBarConfig)

  // 浠巗tore涓幏鍙栫浉鍏崇姸鎬?
  const { showMenuButton, showFastEnter, showRefreshButton, showCrumbs, showLanguage } =
    storeToRefs(settingStore)

  /**
   * 妫€鏌ョ壒瀹氬姛鑳芥槸鍚﹀惎鐢?
   * @param feature 鍔熻兘鍚嶇О
   * @returns 鏄惁鍚敤
   */
  const isFeatureEnabled = (feature: keyof HeaderBarFeatureConfig): boolean => {
    return headerBarConfigRef.value[feature]?.enabled ?? false
  }

  /**
   * 鑾峰彇鍔熻兘閰嶇疆淇℃伅
   * @param feature 鍔熻兘鍚嶇О
   * @returns 鍔熻兘閰嶇疆淇℃伅
   */
  const getFeatureConfig = (feature: keyof HeaderBarFeatureConfig) => {
    return headerBarConfigRef.value[feature]
  }

  // 妫€鏌ヨ彍鍗曟寜閽槸鍚︽樉绀?
  const shouldShowMenuButton = computed(() => {
    return isFeatureEnabled('menuButton') && showMenuButton.value
  })

  // 妫€鏌ュ埛鏂版寜閽槸鍚︽樉绀?
  const shouldShowRefreshButton = computed(() => {
    return isFeatureEnabled('refreshButton') && showRefreshButton.value
  })

  // 妫€鏌ュ揩閫熷叆鍙ｆ槸鍚︽樉绀?
  const shouldShowFastEnter = computed(() => {
    return isFeatureEnabled('fastEnter') && showFastEnter.value
  })

  // 妫€鏌ラ潰鍖呭睉鏄惁鏄剧ず
  const shouldShowBreadcrumb = computed(() => {
    return isFeatureEnabled('breadcrumb') && showCrumbs.value
  })

  // 妫€鏌ュ叏灞€鎼滅储鏄惁鏄剧ず
  const shouldShowGlobalSearch = computed(() => {
    return isFeatureEnabled('globalSearch')
  })

  // 妫€鏌ュ叏灞忔寜閽槸鍚︽樉绀?
  const shouldShowFullscreen = computed(() => {
    return isFeatureEnabled('fullscreen')
  })

  // 妫€鏌ラ€氱煡涓績鏄惁鏄剧ず
  const shouldShowNotification = computed(() => {
    return isFeatureEnabled('notification')
  })

  // 妫€鏌ヨ亰澶╁姛鑳芥槸鍚︽樉绀?
  const shouldShowChat = computed(() => {
    return isFeatureEnabled('chat')
  })

  // 妫€鏌ヨ瑷€鍒囨崲鏄惁鏄剧ず
  const shouldShowLanguage = computed(() => {
    return isFeatureEnabled('language') && showLanguage.value
  })

  // 妫€鏌ヨ缃潰鏉挎槸鍚︽樉绀?
  const shouldShowSettings = computed(() => {
    return isFeatureEnabled('settings')
  })

  // 妫€鏌ヤ富棰樺垏鎹㈡槸鍚︽樉绀?
  const shouldShowThemeToggle = computed(() => {
    return isFeatureEnabled('themeToggle')
  })

  // 鑾峰彇蹇€熷叆鍙ｇ殑鏈€灏忓搴?
  const fastEnterMinWidth = computed(() => {
    const config = getFeatureConfig('fastEnter')
    return (config as any)?.minWidth || 1200
  })

  /**
   * 妫€鏌ュ姛鑳芥槸鍚﹀惎鐢紙鍒悕锛?
   * @param feature 鍔熻兘鍚嶇О
   * @returns 鏄惁鍚敤
   */
  const isFeatureActive = (feature: keyof HeaderBarFeatureConfig): boolean => {
    return isFeatureEnabled(feature)
  }

  /**
   * 鑾峰彇鍔熻兘閰嶇疆锛堝埆鍚嶏級
   * @param feature 鍔熻兘鍚嶇О
   * @returns 鍔熻兘閰嶇疆
   */
  const getFeatureInfo = (feature: keyof HeaderBarFeatureConfig) => {
    return getFeatureConfig(feature)
  }

  /**
   * 鑾峰彇鎵€鏈夊惎鐢ㄧ殑鍔熻兘鍒楄〃
   * @returns 鍚敤鐨勫姛鑳藉悕绉版暟缁?
   */
  const getEnabledFeatures = (): (keyof HeaderBarFeatureConfig)[] => {
    return Object.keys(headerBarConfigRef.value).filter(
      (key) => headerBarConfigRef.value[key as keyof HeaderBarFeatureConfig]?.enabled
    ) as (keyof HeaderBarFeatureConfig)[]
  }

  /**
   * 鑾峰彇鎵€鏈夌鐢ㄧ殑鍔熻兘鍒楄〃
   * @returns 绂佺敤鐨勫姛鑳藉悕绉版暟缁?
   */
  const getDisabledFeatures = (): (keyof HeaderBarFeatureConfig)[] => {
    return Object.keys(headerBarConfigRef.value).filter(
      (key) => !headerBarConfigRef.value[key as keyof HeaderBarFeatureConfig]?.enabled
    ) as (keyof HeaderBarFeatureConfig)[]
  }

  /**
   * 鑾峰彇鎵€鏈夊惎鐢ㄧ殑鍔熻兘锛堝埆鍚嶏級
   * @returns 鍚敤鐨勫姛鑳藉垪琛?
   */
  const getActiveFeatures = () => {
    return getEnabledFeatures()
  }

  /**
   * 鑾峰彇鎵€鏈夌鐢ㄧ殑鍔熻兘锛堝埆鍚嶏級
   * @returns 绂佺敤鐨勫姛鑳藉垪琛?
   */
  const getInactiveFeatures = () => {
    return getDisabledFeatures()
  }

  return {
    // 閰嶇疆
    headerBarConfig: headerBarConfigRef,

    // 鏄剧ず鐘舵€佽绠楀睘鎬?
    shouldShowMenuButton, // 鏄惁鏄剧ず鑿滃崟鎸夐挳
    shouldShowRefreshButton, // 鏄惁鏄剧ず鍒锋柊鎸夐挳
    shouldShowFastEnter, // 鏄惁鏄剧ず蹇€熷叆鍙?
    shouldShowBreadcrumb, // 鏄惁鏄剧ず闈㈠寘灞?
    shouldShowGlobalSearch, // 鏄惁鏄剧ず鍏ㄥ眬鎼滅储
    shouldShowFullscreen, // 鏄惁鏄剧ず鍏ㄥ睆鎸夐挳
    shouldShowNotification, // 鏄惁鏄剧ず閫氱煡涓績
    shouldShowChat, // 鏄惁鏄剧ず鑱婂ぉ鍔熻兘
    shouldShowLanguage, // 鏄惁鏄剧ず璇█鍒囨崲
    shouldShowSettings, // 鏄惁鏄剧ず璁剧疆闈㈡澘
    shouldShowThemeToggle, // 鏄惁鏄剧ず涓婚鍒囨崲

    // 閰嶇疆鐩稿叧
    fastEnterMinWidth, // 蹇€熷叆鍙ｆ渶灏忓搴?

    // 鏂规硶
    isFeatureEnabled, // 妫€鏌ュ姛鑳芥槸鍚﹀惎鐢?
    isFeatureActive, // 妫€鏌ュ姛鑳芥槸鍚﹀惎鐢紙鍒悕锛?
    getFeatureConfig, // 鑾峰彇鍔熻兘閰嶇疆
    getFeatureInfo, // 鑾峰彇鍔熻兘閰嶇疆锛堝埆鍚嶏級
    getEnabledFeatures, // 鑾峰彇鎵€鏈夊惎鐢ㄧ殑鍔熻兘
    getDisabledFeatures, // 鑾峰彇鎵€鏈夌鐢ㄧ殑鍔熻兘
    getActiveFeatures, // 鑾峰彇鎵€鏈夊惎鐢ㄧ殑鍔熻兘锛堝埆鍚嶏級
    getInactiveFeatures // 鑾峰彇鎵€鏈夌鐢ㄧ殑鍔熻兘锛堝埆鍚嶏級
  }
}

