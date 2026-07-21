/**
 * 鐢ㄦ埛鐘舵€佺鐞嗘ā鍧?
 *
 * 鎻愪緵鐢ㄦ埛鐩稿叧鐨勭姸鎬佺鐞?
 *
 * ## 涓昏鍔熻兘
 *
 * - 鐢ㄦ埛鐧诲綍鐘舵€佺鐞?
 * - 鐢ㄦ埛淇℃伅瀛樺偍
 * - 璁块棶浠ょ墝鍜屽埛鏂颁护鐗岀鐞?
 * - 璇█璁剧疆
 * - 鎼滅储鍘嗗彶璁板綍
 * - 閿佸睆鐘舵€佸拰瀵嗙爜绠＄悊
 * - 鐧诲嚭娓呯悊閫昏緫
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 鐢ㄦ埛鐧诲綍鍜岃璇?
 * - 鏉冮檺楠岃瘉
 * - 涓汉淇℃伅灞曠ず
 * - 澶氳瑷€鍒囨崲
 * - 閿佸睆鍔熻兘
 * - 鎼滅储鍘嗗彶绠＄悊
 *
 * ## 鎸佷箙鍖?
 *
 * - 浣跨敤 localStorage 瀛樺偍
 * - 瀛樺偍閿細sys-v{version}-user
 * - 鐧诲嚭鏃惰嚜鍔ㄦ竻鐞?
 *
 * @module store/modules/user
 * @author AiPay
 */
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { LanguageEnum } from '@/enums/appEnum'
import { router } from '@/router'
import { useSettingStore } from './setting'
import { useWorktabStore } from './worktab'
import { AppRouteRecord } from '@/types/router'
import { setPageTitle } from '@/utils/router'
import { resetRouterState } from '@/router/guards/beforeEach'
import { useMenuStore } from './menu'
import { StorageConfig } from '@/utils/storage/storage-config'

/**
 * 鐢ㄦ埛鐘舵€佺鐞?
 * 绠＄悊鐢ㄦ埛鐧诲綍鐘舵€併€佷釜浜轰俊鎭€佽瑷€璁剧疆銆佹悳绱㈠巻鍙层€侀攣灞忕姸鎬佺瓑
 */
export const useUserStore = defineStore(
  'userStore',
  () => {
    // 璇█璁剧疆
    const language = ref(LanguageEnum.ZH)
    // 鐧诲綍鐘舵€?
    const isLogin = ref(false)
    // 閿佸睆鐘舵€?
    const isLock = ref(false)
    // 閿佸睆瀵嗙爜
    const lockPassword = ref('')
    // 鐢ㄦ埛淇℃伅
    const info = ref<Partial<Api.Auth.UserInfo>>({})
    // 鎼滅储鍘嗗彶璁板綍
    const searchHistory = ref<AppRouteRecord[]>([])
    // 璁块棶浠ょ墝
    const accessToken = ref('')
    // 鍒锋柊浠ょ墝
    const refreshToken = ref('')

    // 璁＄畻灞炴€э細鑾峰彇鐢ㄦ埛淇℃伅
    const getUserInfo = computed(() => info.value)
    // 璁＄畻灞炴€э細鑾峰彇璁剧疆鐘舵€?
    const getSettingState = computed(() => useSettingStore().$state)
    // 璁＄畻灞炴€э細鑾峰彇宸ヤ綔鍙扮姸鎬?
    const getWorktabState = computed(() => useWorktabStore().$state)

    /**
     * 璁剧疆鐢ㄦ埛淇℃伅
     * @param newInfo 鏂扮殑鐢ㄦ埛淇℃伅
     */
    const setUserInfo = (newInfo: Api.Auth.UserInfo) => {
      info.value = newInfo
    }

    /**
     * 璁剧疆鐧诲綍鐘舵€?
     * @param status 鐧诲綍鐘舵€?
     */
    const setLoginStatus = (status: boolean) => {
      isLogin.value = status
    }

    /**
     * 璁剧疆璇█
     * @param lang 璇█鏋氫妇鍊?
     */
    const setLanguage = (lang: LanguageEnum) => {
      setPageTitle(router.currentRoute.value)
      language.value = lang
    }

    /**
     * 璁剧疆鎼滅储鍘嗗彶
     * @param list 鎼滅储鍘嗗彶鍒楄〃
     */
    const setSearchHistory = (list: AppRouteRecord[]) => {
      searchHistory.value = list
    }

    /**
     * 璁剧疆閿佸睆鐘舵€?
     * @param status 閿佸睆鐘舵€?
     */
    const setLockStatus = (status: boolean) => {
      isLock.value = status
    }

    /**
     * 璁剧疆閿佸睆瀵嗙爜
     * @param password 閿佸睆瀵嗙爜
     */
    const setLockPassword = (password: string) => {
      lockPassword.value = password
    }

    /**
     * 璁剧疆浠ょ墝
     * @param newAccessToken 璁块棶浠ょ墝
     * @param newRefreshToken 鍒锋柊浠ょ墝锛堝彲閫夛級
     */
    const setToken = (newAccessToken: string, newRefreshToken?: string) => {
      accessToken.value = newAccessToken
      if (newRefreshToken) {
        refreshToken.value = newRefreshToken
      }
    }

    /**
     * 閫€鍑虹櫥褰?
     * 娓呯┖鎵€鏈夌敤鎴风浉鍏崇姸鎬佸苟璺宠浆鍒扮櫥褰曢〉
     * 濡傛灉鏄悓涓€璐﹀彿閲嶆柊鐧诲綍锛屼繚鐣欏伐浣滃彴鏍囩椤?
     */
    const logOut = () => {
      // 淇濆瓨褰撳墠鐢ㄦ埛 ID锛岀敤浜庝笅娆＄櫥褰曟椂鍒ゆ柇鏄惁涓哄悓涓€鐢ㄦ埛
      const currentUserId = info.value.userId
      if (currentUserId) {
        localStorage.setItem(StorageConfig.LAST_USER_ID_KEY, String(currentUserId))
      }

      // 娓呯┖鐢ㄦ埛淇℃伅
      info.value = {}
      // 閲嶇疆鐧诲綍鐘舵€?
      isLogin.value = false
      // 閲嶇疆閿佸睆鐘舵€?
      isLock.value = false
      // 娓呯┖閿佸睆瀵嗙爜
      lockPassword.value = ''
      // 娓呯┖璁块棶浠ょ墝
      accessToken.value = ''
      // 娓呯┖鍒锋柊浠ょ墝
      refreshToken.value = ''
      // 娉ㄦ剰锛氫笉娓呯┖宸ヤ綔鍙版爣绛鹃〉锛岀瓑涓嬫鐧诲綍鏃舵牴鎹敤鎴峰垽鏂?
      // 绉婚櫎iframe璺敱缂撳瓨
      sessionStorage.removeItem('iframeRoutes')
      // 娓呯┖涓婚〉璺緞
      useMenuStore().setHomePath('')
      // 閲嶇疆璺敱鐘舵€?
      resetRouterState(500)
      // 璺宠浆鍒扮櫥褰曢〉锛屾惡甯﹀綋鍓嶈矾鐢变綔涓?redirect 鍙傛暟
      const currentRoute = router.currentRoute.value
      const redirect = currentRoute.path !== '/login' ? currentRoute.fullPath : undefined
      router.push({
        name: 'Login',
        query: redirect ? { redirect } : undefined
      })
    }

    /**
     * 妫€鏌ュ苟娓呯悊宸ヤ綔鍙版爣绛鹃〉
     * 濡傛灉涓嶆槸鍚屼竴鐢ㄦ埛鐧诲綍锛屾竻绌哄伐浣滃彴鏍囩椤?
     * 搴斿湪鐧诲綍鎴愬姛鍚庤皟鐢?
     */
    const checkAndClearWorktabs = () => {
      const lastUserId = localStorage.getItem(StorageConfig.LAST_USER_ID_KEY)
      const currentUserId = info.value.userId

      // 鏃犳硶鑾峰彇褰撳墠鐢ㄦ埛 ID锛岃烦杩囨鏌?
      if (!currentUserId) return

      // 棣栨鐧诲綍鎴栫紦瀛樺凡娓呴櫎锛屼繚鐣欑幇鏈夋爣绛鹃〉
      if (!lastUserId) {
        return
      }

      // 涓嶅悓鐢ㄦ埛鐧诲綍锛屾竻绌哄伐浣滃彴鏍囩椤?
      if (String(currentUserId) !== lastUserId) {
        const worktabStore = useWorktabStore()
        worktabStore.opened = []
        worktabStore.keepAliveExclude = []
      }

      // 娓呴櫎涓存椂瀛樺偍
      localStorage.removeItem(StorageConfig.LAST_USER_ID_KEY)
    }

    return {
      language,
      isLogin,
      isLock,
      lockPassword,
      info,
      searchHistory,
      accessToken,
      refreshToken,
      getUserInfo,
      getSettingState,
      getWorktabState,
      setUserInfo,
      setLoginStatus,
      setLanguage,
      setSearchHistory,
      setLockStatus,
      setLockPassword,
      setToken,
      logOut,
      checkAndClearWorktabs
    }
  },
  {
    persist: {
      key: 'user',
      storage: localStorage
    }
  }
)

