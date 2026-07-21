/**
 * useAuth - 鏉冮檺楠岃瘉绠＄悊
 *
 * 鎻愪緵缁熶竴鐨勬潈闄愰獙璇佸姛鑳斤紝鏀寔鍓嶇鍜屽悗绔袱绉嶆潈闄愭ā寮忋€?
 * 鐢ㄤ簬鎺у埗椤甸潰鎸夐挳銆佹搷浣滅瓑鍔熻兘鐨勬樉绀哄拰璁块棶鏉冮檺銆?
 *
 * ## 涓昏鍔熻兘
 *
 * 1. 鏉冮檺妫€鏌?- 妫€鏌ョ敤鎴锋槸鍚︽嫢鏈夋寚瀹氱殑鏉冮檺鏍囪瘑
 * 2. 鍙屾ā寮忔敮鎸?- 鑷姩閫傞厤鍓嶇妯″紡鍜屽悗绔ā寮忕殑鏉冮檺楠岃瘉
 * 3. 鍓嶇妯″紡 - 浠庣敤鎴蜂俊鎭腑鑾峰彇鎸夐挳鏉冮檺鍒楄〃锛堝 ['add', 'edit', 'delete']锛?
 * 4. 鍚庣妯″紡 - 浠庤矾鐢?meta 閰嶇疆涓幏鍙栨潈闄愬垪琛紙濡?[{ authMark: 'add' }]锛?
 *
 * ## 浣跨敤绀轰緥
 *
 * ```typescript
 * const { hasAuth } = useAuth()
 *
 * // 妫€鏌ユ槸鍚︽湁鏂板鏉冮檺
 * if (hasAuth('add')) {
 *   // 鏄剧ず鏂板鎸夐挳
 * }
 *
 * // 鍦ㄦā鏉夸腑浣跨敤
 * <el-button v-if="hasAuth('edit')">缂栬緫</el-button>
 * <el-button v-if="hasAuth('delete')">鍒犻櫎</el-button>
 * ```
 *
 * @module useAuth
 * @author AiPay
 */

import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useUserStore } from '@/store/modules/user'
import { useAppMode } from '@/hooks/core/useAppMode'
import type { AppRouteRecord } from '@/types/router'

type AuthItem = NonNullable<AppRouteRecord['meta']['authList']>[number]

const userStore = useUserStore()

export const useAuth = () => {
  const route = useRoute()
  const { isFrontendMode } = useAppMode()
  const { info } = storeToRefs(userStore)

  // 鍓嶇鎸夐挳鏉冮檺锛堜緥濡傦細['add', 'edit']锛?
  const frontendAuthList = info.value?.buttons ?? []

  // 鍚庣璺敱 meta 閰嶇疆鐨勬潈闄愬垪琛紙渚嬪锛歔{ authMark: 'add' }]锛?
  const backendAuthList: AuthItem[] = Array.isArray(route.meta.authList)
    ? (route.meta.authList as AuthItem[])
    : []

  /**
   * 妫€鏌ユ槸鍚︽嫢鏈夋煇鏉冮檺鏍囪瘑锛堝墠鍚庣妯″紡閫氱敤锛?
   * @param auth 鏉冮檺鏍囪瘑
   * @returns 鏄惁鏈夋潈闄?
   */
  const hasAuth = (auth: string): boolean => {
    // 鍓嶇妯″紡
    if (isFrontendMode.value) {
      return frontendAuthList.includes(auth)
    }

    // 鍚庣妯″紡
    if (backendAuthList.length === 0) {
      // 璺敱鏈寕杞芥寜閽潈闄愭椂锛岄粯璁や繚鐣欓〉闈㈠熀纭€璁块棶鏉冮檺锛?      // 璁╀緷璧?hasAuth('index') 鐨勭鐞嗛〉涓嶅啀鏁翠綋鍙樼伆銆?      return auth === 'index'
    }

    return backendAuthList.some((item) => item?.authMark === auth)
  }

  return {
    hasAuth
  }
}

