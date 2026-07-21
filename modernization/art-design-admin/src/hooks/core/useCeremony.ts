/**
 * useCeremony - 鑺傛棩搴嗙绠＄悊
 *
 * 鎻愪緵鑺傛棩鐑熻姳鏁堟灉鍜岀绂忔枃鏈睍绀哄姛鑳斤紝涓虹郴缁熷娣昏妭鏃ユ皼鍥淬€?
 * 鑷姩妫€娴嬪綋鍓嶆棩鏈熸槸鍚︿负鑺傛棩锛屽苟鍦ㄩ娆¤繘鍏ユ椂鎾斁鐑熻姳鍔ㄧ敾鍜屾樉绀虹绂忚銆?
 *
 * ## 涓昏鍔熻兘
 *
 * 1. 鑺傛棩妫€娴?- 鑷姩鍖归厤褰撳墠鏃ユ湡涓庤妭鏃ラ厤缃垪琛紝鏀寔鍗曟棩鍜岃法鏃ユ湡鑺傛棩
 * 2. 鐑熻姳鍔ㄧ敾 - 鎾斁鑺傛棩鐑熻姳鐗规晥锛屾敮鎸佽嚜瀹氫箟鍥剧墖鍜岃Е鍙戞鏁?
 * 3. 绁濈鏂囨湰 - 鐑熻姳缁撴潫鍚庢樉绀鸿妭鏃ョ绂忔枃鏈?
 * 4. 鐘舵€佺鐞?- 璁板綍鐑熻姳鎾斁鐘舵€侊紝閬垮厤閲嶅鎾斁
 * 5. 娓呯悊鏈哄埗 - 鎻愪緵娓呯悊鏂规硶锛屾敮鎸佹墜鍔ㄥ仠姝㈠拰閲嶇疆
 *
 * ## 浣跨敤绀轰緥
 *
 * ```typescript
 * // 鍦ㄩ厤缃枃浠朵腑瀹氫箟鑺傛棩
 * // 鍗曟棩鑺傛棩
 * {
 *   date: '2024-12-25',
 *   name: '鍦ｈ癁鑺?,
 *   image: christmasImage,
 *   count: 3 // 鍙€夛紝涓嶈缃垯浣跨敤榛樿鍊?3 娆?
 *   scrollText: 'Merry Christmas!',
 * }
 *
 * // 璺ㄦ棩鏈熻妭鏃?
 * {
 *   date: '2025-11-07',
 *   endDate: '2025-11-10',
 *   name: 'v3.0 娴嬭瘯闃舵',
 *   image: '',
 *   count: 5 // 鑷畾涔夌儫鑺辨挱鏀炬鏁?
 *   scrollText: '绯荤粺 v3.0 娴嬭瘯闃舵姝ｅ紡寮€鍚紒',
 * }
 * ```
 *
 * @module useCeremony
 * @author AiPay
 */

import { useTimeoutFn, useIntervalFn, useDateFormat } from '@vueuse/core'
import { storeToRefs } from 'pinia'
import { computed } from 'vue'
import { useSettingStore } from '@/store/modules/setting'
import { mittBus } from '@/utils/sys'
import { festivalConfigList } from '@/config/modules/festival'

/**
 * 鑺傛棩搴嗙閰嶇疆甯搁噺
 */
const FESTIVAL_CONFIG = {
  /** 鍒濆寤惰繜锛堟绉掞級 */
  INITIAL_DELAY: 300,
  /** 鐑熻姳鎾斁闂撮殧锛堟绉掞級 */
  FIREWORK_INTERVAL: 1000,
  /** 鏂囨湰鏄剧ず寤惰繜锛堟绉掞級 */
  TEXT_DELAY: 2000,
  /** 榛樿鐑熻姳鎾斁娆℃暟 */
  DEFAULT_FIREWORKS_COUNT: 3
} as const

/**
 * 鑺傛棩搴嗙鍔熻兘
 * 鎻愪緵鑺傛棩鐑熻姳鏁堟灉鍜岀绂忔枃鏈睍绀?
 */
export function useCeremony() {
  const settingStore = useSettingStore()
  const { holidayFireworksLoaded, isShowFireworks } = storeToRefs(settingStore)

  let fireworksInterval: { pause: () => void } | null = null

  /**
   * 妫€鏌ユ棩鏈熸槸鍚﹀湪鑺傛棩鑼冨洿鍐?
   * @param currentDate 褰撳墠鏃ユ湡
   * @param festivalDate 鑺傛棩寮€濮嬫棩鏈?
   * @param festivalEndDate 鑺傛棩缁撴潫鏃ユ湡锛堝彲閫夛級
   */
  const isDateInRange = (
    currentDate: string,
    festivalDate: string,
    festivalEndDate?: string
  ): boolean => {
    if (!festivalEndDate) {
      // 鍗曟棩鑺傛棩
      return currentDate === festivalDate
    }

    // 璺ㄦ棩鏈熻妭鏃?
    const current = new Date(currentDate)
    const start = new Date(festivalDate)
    const end = new Date(festivalEndDate)

    return current >= start && current <= end
  }

  /**
   * 鑾峰彇褰撳墠鏃ユ湡瀵瑰簲鐨勮妭鏃ユ暟鎹?
   */
  const currentFestivalData = computed(() => {
    const currentDate = useDateFormat(new Date(), 'YYYY-MM-DD').value
    return festivalConfigList.find((item) => isDateInRange(currentDate, item.date, item.endDate))
  })

  /**
   * 鏇存柊鑺傛棩鏃ユ湡鍒?store
   */
  const updateFestivalDate = () => {
    settingStore.setFestivalDate(currentFestivalData.value?.date || '')
  }

  /**
   * 瑙﹀彂鐑熻姳鏁堟灉
   */
  const triggerFirework = () => {
    mittBus.emit('triggerFireworks', currentFestivalData.value?.image)
  }

  /**
   * 瀹屾垚鐑熻姳鏁堟灉鍚庢樉绀烘枃鏈?
   */
  const showFestivalText = () => {
    settingStore.setholidayFireworksLoaded(true)

    useTimeoutFn(() => {
      settingStore.setShowFestivalText(true)
      updateFestivalDate()
    }, FESTIVAL_CONFIG.TEXT_DELAY)
  }

  /**
   * 鍚姩鐑熻姳寰幆
   */
  const startFireworksLoop = () => {
    let playedCount = 0
    // 浣跨敤鑺傛棩閰嶇疆鐨勬挱鏀炬鏁帮紝濡傛灉娌℃湁鍒欎娇鐢ㄩ粯璁ゅ€?
    const count = currentFestivalData.value?.count ?? FESTIVAL_CONFIG.DEFAULT_FIREWORKS_COUNT

    const { pause } = useIntervalFn(() => {
      triggerFirework()
      playedCount++

      if (playedCount >= count) {
        pause()
        showFestivalText()
      }
    }, FESTIVAL_CONFIG.FIREWORK_INTERVAL)

    fireworksInterval = { pause }
  }

  /**
   * 寮€鍚妭鏃ュ簡绁?
   */
  const openFestival = () => {
    if (!currentFestivalData.value || !isShowFireworks.value) {
      return
    }

    const { start } = useTimeoutFn(startFireworksLoop, FESTIVAL_CONFIG.INITIAL_DELAY)
    start()
  }

  /**
   * 娓呯悊鐑熻姳鏁堟灉
   */
  const cleanup = () => {
    if (fireworksInterval) {
      fireworksInterval.pause()
      fireworksInterval = null
    }
    settingStore.setShowFestivalText(false)
    updateFestivalDate()
  }

  return {
    openFestival,
    cleanup,
    holidayFireworksLoaded,
    currentFestivalData,
    isShowFireworks
  }
}

