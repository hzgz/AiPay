/**
 * 瀛樺偍閰嶇疆绠＄悊妯″潡
 *
 * 鎻愪緵缁熶竴鐨勬湰鍦板瓨鍌ㄩ厤缃拰宸ュ叿鏂规硶
 *
 * ## 涓昏鍔熻兘
 *
 * - 鐗堟湰鍖栧瓨鍌ㄩ敭绠＄悊锛屾敮鎸佸鐗堟湰鏁版嵁闅旂
 * - 瀛樺偍閿悕鐢熸垚鍜岃В鏋愶紙甯︾増鏈墠缂€锛?
 * - 鐗堟湰鍙锋彁鍙栧拰楠岃瘉
 * - 瀛樺偍閿尮閰嶇殑姝ｅ垯琛ㄨ揪寮忕敓鎴?
 * - 鏃х増鏈瓨鍌ㄩ敭鍏煎澶勭悊
 * - 鍗囩骇鍜岀櫥鍑哄欢杩熼厤缃?
 * - 涓婚瀛樺偍閿厤缃?
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - Pinia Store 鎸佷箙鍖栧瓨鍌?
 * - 搴旂敤鐗堟湰鍗囩骇鏃剁殑鏁版嵁杩佺Щ
 * - 澶氱増鏈暟鎹竻鐞?
 * - 瀛樺偍閿殑缁熶竴绠＄悊鍜岃鑼?
 *
 * 瀛樺偍閿牸寮忥細sys-v{version}-{storeId}
 * 渚嬪锛歴ys-v1.0.0-user, sys-v1.0.0-setting
 *
 * @module utils/storage/storage-config
 * @author AiPay
 */
export class StorageConfig {
  /** 褰撳墠搴旂敤鐗堟湰 */
  static readonly CURRENT_VERSION = __APP_VERSION__

  /** 瀛樺偍閿墠缂€ */
  static readonly STORAGE_PREFIX = 'sys-v'

  /** 鐗堟湰閿悕 */
  static readonly VERSION_KEY = 'sys-version'

  /** 涓婚閿悕锛坕ndex.html涓娇鐢ㄤ簡锛屽鏋滀慨鏀癸紝闇€瑕佸悓姝ヤ慨鏀癸級 */
  static readonly THEME_KEY = 'sys-theme'

  /** 涓婃鐧诲綍鐢ㄦ埛ID閿悕锛堢敤浜庡垽鏂槸鍚︿负鍚屼竴鐢ㄦ埛鐧诲綍锛?*/
  static readonly LAST_USER_ID_KEY = 'sys-last-user-id'

  /** 鍝嶅簲寮忓竷灞€鍒囨崲鏃舵殏瀛樻闈㈢鑿滃崟绫诲瀷 */
  static readonly RESPONSIVE_MENU_TYPE_KEY = 'sys-responsive-menu-type'

  /** 璺宠繃鍗囩骇妫€鏌ョ殑鐗堟湰 */
  static readonly SKIP_UPGRADE_VERSION = '1.0.0'

  /** 鍗囩骇澶勭悊寤惰繜鏃堕棿锛堟绉掞級 */
  static readonly UPGRADE_DELAY = 1000

  /** 鐧诲嚭寤惰繜鏃堕棿锛堟绉掞級 */
  static readonly LOGOUT_DELAY = 1000

  /**
   * 鐢熸垚鐗堟湰鍖栫殑瀛樺偍閿悕
   * @param storeId 瀛樺偍ID
   * @param version 鐗堟湰鍙凤紝榛樿浣跨敤褰撳墠鐗堟湰
   */
  static generateStorageKey(storeId: string, version: string = this.CURRENT_VERSION): string {
    return `${this.STORAGE_PREFIX}${version}-${storeId}`
  }

  /**
   * 鐢熸垚鏃х増鏈殑瀛樺偍閿悕锛堜笉甯﹀垎闅旂锛?
   * @param version 鐗堟湰鍙凤紝榛樿浣跨敤褰撳墠鐗堟湰
   */
  static generateLegacyKey(version: string = this.CURRENT_VERSION): string {
    return `${this.STORAGE_PREFIX}${version}`
  }

  /**
   * 鍒涘缓瀛樺偍閿尮閰嶇殑姝ｅ垯琛ㄨ揪寮?
   * @param storeId 瀛樺偍ID
   */
  static createKeyPattern(storeId: string): RegExp {
    return new RegExp(`^${this.STORAGE_PREFIX}[^-]+-${storeId}$`)
  }

  /**
   * 鍒涘缓褰撳墠鐗堟湰瀛樺偍閿尮閰嶇殑姝ｅ垯琛ㄨ揪寮?
   */
  static createCurrentVersionPattern(): RegExp {
    return new RegExp(`^${this.STORAGE_PREFIX}${this.CURRENT_VERSION}-`)
  }

  /**
   * 鍒涘缓浠绘剰鐗堟湰瀛樺偍閿尮閰嶇殑姝ｅ垯琛ㄨ揪寮?
   */
  static createVersionPattern(): RegExp {
    return new RegExp(`^${this.STORAGE_PREFIX}`)
  }

  /**
   * 妫€鏌ユ槸鍚︿负褰撳墠鐗堟湰鐨勯敭
   */
  static isCurrentVersionKey(key: string): boolean {
    return key.startsWith(`${this.STORAGE_PREFIX}${this.CURRENT_VERSION}`)
  }

  /**
   * 妫€鏌ユ槸鍚︿负鐗堟湰鍖栫殑閿?
   */
  static isVersionedKey(key: string): boolean {
    return key.startsWith(this.STORAGE_PREFIX)
  }

  /**
   * 浠庡瓨鍌ㄩ敭涓彁鍙栫増鏈彿
   */
  static extractVersionFromKey(key: string): string | null {
    const match = key.match(new RegExp(`^${this.STORAGE_PREFIX}([^-]+)`))
    return match ? match[1] : null
  }

  /**
   * 浠庡瓨鍌ㄩ敭涓彁鍙栧瓨鍌↖D
   */
  static extractStoreIdFromKey(key: string): string | null {
    const match = key.match(new RegExp(`^${this.STORAGE_PREFIX}[^-]+-(.+)$`))
    return match ? match[1] : null
  }
}

