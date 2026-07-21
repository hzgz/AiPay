/**
 * 瀛樺偍閿悕绠＄悊鍣ㄦā鍧?
 *
 * 鎻愪緵鏅鸿兘鐨勭増鏈寲瀛樺偍閿鐞嗗拰鏁版嵁杩佺Щ鍔熻兘
 *
 * ## 涓昏鍔熻兘
 *
 * - 鑷姩鐢熸垚褰撳墠鐗堟湰鐨勫瓨鍌ㄩ敭鍚?
 * - 妫€娴嬪綋鍓嶇増鏈暟鎹槸鍚﹀瓨鍦?
 * - 鏌ユ壘鍏朵粬鐗堟湰鐨勫悓鍚嶅瓨鍌ㄦ暟鎹?
 * - 鑷姩灏嗘棫鐗堟湰鏁版嵁杩佺Щ鍒板綋鍓嶇増鏈?
 * - 鏁版嵁杩佺Щ鏃ュ織璁板綍
 * - 杩佺Щ澶辫触鐨勯敊璇鐞?
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - Pinia Store 鎸佷箙鍖栨彃浠朵腑鑾峰彇瀛樺偍閿?
 * - 搴旂敤鐗堟湰鍗囩骇鏃惰嚜鍔ㄨ縼绉荤敤鎴锋暟鎹?
 * - 閬垮厤鐗堟湰鍗囩骇瀵艰嚧鐨勬暟鎹涪澶?
 * - 瀹炵幇骞虫粦鐨勭増鏈繃娓?
 *
 * ## 宸ヤ綔娴佺▼
 *
 * 1. 浼樺厛浣跨敤褰撳墠鐗堟湰鐨勫瓨鍌ㄩ敭
 * 2. 濡傛灉褰撳墠鐗堟湰鏃犳暟鎹紝鏌ユ壘鍏朵粬鐗堟湰鐨勫悓鍚嶆暟鎹?
 * 3. 鎵惧埌鏃х増鏈暟鎹悗鑷姩杩佺Щ鍒板綋鍓嶇増鏈?
 * 4. 杩斿洖褰撳墠鐗堟湰鐨勫瓨鍌ㄩ敭渚涗娇鐢?
 *
 * @module utils/storage/storage-key-manager
 * @author AiPay
 */
import { StorageConfig } from '@/utils/storage'

/**
 * 瀛樺偍閿悕绠＄悊鍣?
 * 璐熻矗澶勭悊鐗堟湰鍖栫殑瀛樺偍閿悕鐢熸垚鍜屾暟鎹縼绉?
 */
export class StorageKeyManager {
  /**
   * 鑾峰彇褰撳墠鐗堟湰鐨勫瓨鍌ㄩ敭鍚?
   */
  private getCurrentVersionKey(storeId: string): string {
    return StorageConfig.generateStorageKey(storeId)
  }

  /**
   * 妫€鏌ュ綋鍓嶇増鏈殑鏁版嵁鏄惁瀛樺湪
   */
  private hasCurrentVersionData(key: string): boolean {
    return localStorage.getItem(key) !== null
  }

  /**
   * 鏌ユ壘鍏朵粬鐗堟湰鐨勫悓鍚嶅瓨鍌ㄩ敭
   */
  private findExistingKey(storeId: string): string | null {
    const storageKeys = Object.keys(localStorage)
    const pattern = StorageConfig.createKeyPattern(storeId)

    return storageKeys.find((key) => pattern.test(key) && localStorage.getItem(key)) || null
  }

  /**
   * 灏嗘暟鎹粠鏃х増鏈縼绉诲埌褰撳墠鐗堟湰
   */
  private migrateData(fromKey: string, toKey: string): void {
    try {
      const existingData = localStorage.getItem(fromKey)
      if (existingData) {
        localStorage.setItem(toKey, existingData)
        console.info(`[Storage] 宸茶縼绉绘暟鎹? ${fromKey} 鈫?${toKey}`)
      }
    } catch (error) {
      console.warn(`[Storage] 鏁版嵁杩佺Щ澶辫触: ${fromKey}`, error)
    }
  }

  /**
   * 鑾峰彇鎸佷箙鍖栧瓨鍌ㄧ殑閿悕锛堟敮鎸佽嚜鍔ㄦ暟鎹縼绉伙級
   */
  getStorageKey(storeId: string): string {
    const currentKey = this.getCurrentVersionKey(storeId)

    // 浼樺厛浣跨敤褰撳墠鐗堟湰鐨勬暟鎹?
    if (this.hasCurrentVersionData(currentKey)) {
      return currentKey
    }

    // 鏌ユ壘骞惰縼绉诲叾浠栫増鏈殑鏁版嵁
    const existingKey = this.findExistingKey(storeId)
    if (existingKey) {
      this.migrateData(existingKey, currentKey)
    }

    return currentKey
  }
}

