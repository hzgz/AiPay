/**
 * useFastEnter - 蹇€熷叆鍙ｇ鐞?
 *
 * 绠＄悊椤堕儴鏍忕殑蹇€熷叆鍙ｅ姛鑳斤紝鎻愪緵搴旂敤鍒楄〃鍜屽揩閫熼摼鎺ョ殑閰嶇疆鍜岃繃婊ゃ€?
 * 鏀寔鍔ㄦ€佸惎鐢?绂佺敤銆佽嚜瀹氫箟鎺掑簭銆佸搷搴斿紡瀹藉害鎺у埗绛夊姛鑳姐€?
 *
 * ## 涓昏鍔熻兘
 *
 * 1. 搴旂敤鍒楄〃绠＄悊 - 鑾峰彇鍚敤鐨勫簲鐢ㄥ垪琛紝鑷姩鎸夋帓搴忔潈閲嶆帓搴?
 * 2. 蹇€熼摼鎺ョ鐞?- 鑾峰彇鍚敤鐨勫揩閫熼摼鎺ワ紝鏀寔鑷畾涔夋帓搴?
 * 3. 鍝嶅簲寮忛厤缃?- 鎵€鏈夐厤缃嚜鍔ㄥ搷搴斿彉鍖栵紝鏃犻渶鎵嬪姩鏇存柊
 * 4. 瀹藉害鎺у埗 - 鎻愪緵鏈€灏忔樉绀哄搴﹂厤缃紝鏀寔鍝嶅簲寮忓竷灞€
 *
 * @module useFastEnter
 * @author AiPay
 */

import { computed } from 'vue'
import appConfig from '@/config'
import type { FastEnterApplication, FastEnterQuickLink } from '@/types/config'

export function useFastEnter() {
  // 鑾峰彇蹇€熷叆鍙ｉ厤缃?
  const fastEnterConfig = computed(() => appConfig.fastEnter)

  // 鑾峰彇鍚敤鐨勫簲鐢ㄥ垪琛紙鎸夋帓搴忔潈閲嶆帓搴忥級
  const enabledApplications = computed<FastEnterApplication[]>(() => {
    if (!fastEnterConfig.value?.applications) return []

    return fastEnterConfig.value.applications
      .filter((app) => app.enabled !== false)
      .sort((a, b) => (a.order || 0) - (b.order || 0))
  })

  // 鑾峰彇鍚敤鐨勫揩閫熼摼鎺ワ紙鎸夋帓搴忔潈閲嶆帓搴忥級
  const enabledQuickLinks = computed<FastEnterQuickLink[]>(() => {
    if (!fastEnterConfig.value?.quickLinks) return []

    return fastEnterConfig.value.quickLinks
      .filter((link) => link.enabled !== false)
      .sort((a, b) => (a.order || 0) - (b.order || 0))
  })

  // 鑾峰彇鏈€灏忔樉绀哄搴?
  const minWidth = computed(() => {
    return fastEnterConfig.value?.minWidth || 1200
  })

  return {
    fastEnterConfig,
    enabledApplications,
    enabledQuickLinks,
    minWidth
  }
}

