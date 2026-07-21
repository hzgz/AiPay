/**
 * 瀛樺偍鍏煎鎬х鐞嗘ā鍧?
 *
 * 鎻愪緵瀹屾暣鐨勬湰鍦板瓨鍌ㄥ吋瀹规€ф鏌ュ拰鏁版嵁楠岃瘉鍔熻兘
 *
 * 涓昏鍔熻兘
 *
 * - 澶氱増鏈瓨鍌ㄦ暟鎹娴嬪拰楠岃瘉
 * - 鏂版棫瀛樺偍鏍煎紡鍏煎澶勭悊
 * - 瀛樺偍鏁版嵁瀹屾暣鎬ф牎楠?
 * - 瀛樺偍寮傚父鑷姩鎭㈠锛堟竻鐞?鐧诲嚭锛?
 * - 鐧诲綍鐘舵€侀獙璇?
 * - 瀛樺偍涓虹┖妫€娴?
 * - 鐗堟湰鍙风鐞?
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 搴旂敤鍚姩鏃舵鏌ュ瓨鍌ㄦ暟鎹湁鏁堟€?
 * - 璺敱瀹堝崼涓獙璇佺櫥褰曠姸鎬?
 * - 鐗堟湰鍗囩骇鏃剁殑鏁版嵁鍏煎鎬ф鏌?
 * - 瀛樺偍寮傚父鏃剁殑鑷姩鎭㈠
 * - 闃叉鍥犲瓨鍌ㄦ暟鎹崯鍧忓鑷寸殑绯荤粺寮傚父
 *
 * ## 宸ヤ綔娴佺▼
 *
 * 1. 浼樺厛妫€鏌ュ綋鍓嶇増鏈殑瀛樺偍鏁版嵁
 * 2. 妫€鏌ュ叾浠栫増鏈殑瀛樺偍鏁版嵁
 * 3. 鍏煎鏃ф牸寮忕殑瀛樺偍鏁版嵁
 * 4. 楠岃瘉鏁版嵁瀹屾暣鎬?
 * 5. 寮傚父鏃舵彁绀虹敤鎴峰苟鎵ц鐧诲嚭
 *
 * @module utils/storage/storage
 * @author AiPay
 */
import { router } from '@/router'
import { useUserStore } from '@/store/modules/user'
import { StorageConfig } from '@/utils/storage/storage-config'

/**
 * 瀛樺偍鍏煎鎬х鐞嗗櫒
 * 璐熻矗澶勭悊涓嶅悓鐗堟湰闂寸殑瀛樺偍鍏煎鎬ф鏌ュ拰鏁版嵁楠岃瘉
 */
class StorageCompatibilityManager {
  /**
   * 鑾峰彇绯荤粺鐗堟湰鍙?
   */
  getSystemVersion(): string | null {
    return localStorage.getItem(StorageConfig.VERSION_KEY)
  }

  /**
   * 鑾峰彇绯荤粺瀛樺偍鏁版嵁锛堝吋瀹规棫鏍煎紡锛?
   */
  getSystemStorage(): any {
    const version = this.getSystemVersion() || StorageConfig.CURRENT_VERSION
    const legacyKey = StorageConfig.generateLegacyKey(version)
    const data = localStorage.getItem(legacyKey)
    return data ? JSON.parse(data) : null
  }

  /**
   * 妫€鏌ュ綋鍓嶇増鏈槸鍚︽湁瀛樺偍鏁版嵁
   */
  private hasCurrentVersionStorage(): boolean {
    const storageKeys = Object.keys(localStorage)
    const currentVersionPattern = StorageConfig.createCurrentVersionPattern()

    return storageKeys.some(
      (key) => currentVersionPattern.test(key) && localStorage.getItem(key) !== null
    )
  }

  /**
   * 妫€鏌ユ槸鍚﹀瓨鍦ㄤ换浣曠増鏈殑瀛樺偍鏁版嵁
   */
  private hasAnyVersionStorage(): boolean {
    const storageKeys = Object.keys(localStorage)
    const versionPattern = StorageConfig.createVersionPattern()

    return storageKeys.some((key) => versionPattern.test(key) && localStorage.getItem(key) !== null)
  }

  /**
   * 鑾峰彇鏃ф牸寮忕殑鏈湴瀛樺偍鏁版嵁
   */
  private getLegacyStorageData(): Record<string, any> {
    try {
      const systemStorage = this.getSystemStorage()
      return systemStorage || {}
    } catch (error) {
      console.warn('[Storage] 瑙ｆ瀽鏃ф牸寮忓瓨鍌ㄦ暟鎹け璐?', error)
      return {}
    }
  }

  /**
   * 鏄剧ず瀛樺偍閿欒娑堟伅
   */
  private showStorageError(): void {
    ElMessage({
      type: 'error',
      offset: 40,
      duration: 5000,
      message: '系统检测到本地数据异常，请重新登录后继续使用'
    })
  }

  /**
   * 鎵ц绯荤粺鐧诲嚭
   */
  private performSystemLogout(): void {
    setTimeout(() => {
      try {
        localStorage.clear()
        useUserStore().logOut()
        router.push({ name: 'Login' })
        console.info('[Storage] 已执行系统登出')
      } catch (error) {
        console.error('[Storage] 绯荤粺鐧诲嚭澶辫触:', error)
      }
    }, StorageConfig.LOGOUT_DELAY)
  }

  /**
   * 澶勭悊瀛樺偍寮傚父
   */
  private handleStorageError(): void {
    this.showStorageError()
    this.performSystemLogout()
  }

  /**
   * 楠岃瘉瀛樺偍鏁版嵁瀹屾暣鎬?
   * @param requireAuth 鏄惁闇€瑕侀獙璇佺櫥褰曠姸鎬侊紙榛樿 false锛?
   */
  validateStorageData(requireAuth: boolean = false): boolean {
    try {
      // 浼樺厛妫€鏌ユ柊鐗堟湰瀛樺偍缁撴瀯
      if (this.hasCurrentVersionStorage()) {
        // console.debug('[Storage] 鍙戠幇褰撳墠鐗堟湰瀛樺偍鏁版嵁')
        return true
      }

      // 妫€鏌ユ槸鍚︽湁浠讳綍鐗堟湰鐨勫瓨鍌ㄦ暟鎹?
      if (this.hasAnyVersionStorage()) {
        // console.debug('[Storage] 鍙戠幇鍏朵粬鐗堟湰瀛樺偍鏁版嵁锛屽彲鑳介渶瑕佽縼绉?)
        return true
      }

      // 妫€鏌ユ棫鐗堟湰瀛樺偍缁撴瀯
      const legacyData = this.getLegacyStorageData()
      if (Object.keys(legacyData).length === 0) {
        // 鍙湁鍦ㄩ渶瑕侀獙璇佺櫥褰曠姸鎬佹椂鎵嶆墽琛岀櫥鍑烘搷浣?
        if (requireAuth) {
          console.warn('[Storage] 未发现任何存储数据，需要重新登录')
          this.performSystemLogout()
          return false
        }
        // 棣栨璁块棶鎴栬闂潤鎬佽矾鐢憋紝涓嶉渶瑕佺櫥鍑?
        // console.debug('[Storage] 鏈彂鐜板瓨鍌ㄦ暟鎹紝棣栨璁块棶鎴栬闂潤鎬佽矾鐢?)
        return true
      }

      console.debug('[Storage] 发现旧版存储数据')
      return true
    } catch (error) {
      console.error('[Storage] 瀛樺偍鏁版嵁楠岃瘉澶辫触:', error)
      // 鍙湁鍦ㄩ渶瑕侀獙璇佺櫥褰曠姸鎬佹椂鎵嶅鐞嗛敊璇?
      if (requireAuth) {
        this.handleStorageError()
        return false
      }
      return true
    }
  }

  /**
   * 妫€鏌ュ瓨鍌ㄦ槸鍚︿负绌?
   */
  isStorageEmpty(): boolean {
    // 妫€鏌ユ柊鐗堟湰瀛樺偍缁撴瀯
    if (this.hasCurrentVersionStorage()) {
      return false
    }

    // 妫€鏌ユ槸鍚︽湁浠讳綍鐗堟湰鐨勫瓨鍌ㄦ暟鎹?
    if (this.hasAnyVersionStorage()) {
      return false
    }

    // 妫€鏌ユ棫鐗堟湰瀛樺偍缁撴瀯
    const legacyData = this.getLegacyStorageData()
    return Object.keys(legacyData).length === 0
  }

  /**
   * 妫€鏌ュ瓨鍌ㄥ吋瀹规€?
   * @param requireAuth 鏄惁闇€瑕侀獙璇佺櫥褰曠姸鎬侊紙榛樿 false锛?
   */
  checkCompatibility(requireAuth: boolean = false): boolean {
    try {
      const isValid = this.validateStorageData(requireAuth)
      const isEmpty = this.isStorageEmpty()

      if (isValid || isEmpty) {
        // console.debug('[Storage] 瀛樺偍鍏煎鎬ф鏌ラ€氳繃')
        return true
      }

      console.warn('[Storage] 存储兼容性检查失败')
      return false
    } catch (error) {
      console.error('[Storage] 鍏煎鎬ф鏌ュ紓甯?', error)
      return false
    }
  }
}

// 鍒涘缓瀛樺偍鍏煎鎬х鐞嗗櫒瀹炰緥
const storageManager = new StorageCompatibilityManager()

/**
 * 鑾峰彇绯荤粺瀛樺偍鏁版嵁
 */
export function getSystemStorage(): any {
  return storageManager.getSystemStorage()
}

/**
 * 鑾峰彇绯荤粺鐗堟湰鍙?
 */
export function getSysVersion(): string | null {
  return storageManager.getSystemVersion()
}

/**
 * 楠岃瘉鏈湴瀛樺偍鏁版嵁
 * @param requireAuth 鏄惁闇€瑕侀獙璇佺櫥褰曠姸鎬侊紙榛樿 false锛?
 */
export function validateStorageData(requireAuth: boolean = false): boolean {
  return storageManager.validateStorageData(requireAuth)
}

/**
 * 妫€鏌ュ瓨鍌ㄥ吋瀹规€?
 * @param requireAuth 鏄惁闇€瑕侀獙璇佺櫥褰曠姸鎬侊紙榛樿 false锛?
 */
export function checkStorageCompatibility(requireAuth: boolean = false): boolean {
  return storageManager.checkCompatibility(requireAuth)
}

