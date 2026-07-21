/**
 * 鍥介檯鍖栭厤缃?
 *
 * 鍩轰簬 vue-i18n 瀹炵幇鐨勫璇█鍥介檯鍖栬В鍐虫柟妗堛€?
 * 鏀寔涓枃鍜岃嫳鏂囧垏鎹紝鑷姩浠庢湰鍦板瓨鍌ㄦ仮澶嶇敤鎴风殑璇█鍋忓ソ銆?
 *
 * ## 涓昏鍔熻兘
 *
 * - 澶氳瑷€鏀寔 - 鏀寔涓枃锛堢畝浣擄級鍜岃嫳鏂囦袱绉嶈瑷€
 * - 璇█鍒囨崲 - 杩愯鏃跺姩鎬佸垏鎹㈣瑷€锛屾棤闇€鍒锋柊椤甸潰
 * - 鎸佷箙鍖栧瓨鍌?- 鑷姩淇濆瓨鍜屾仮澶嶇敤鎴风殑璇█鍋忓ソ
 * - 鍏ㄥ眬娉ㄥ叆 - 鍦ㄤ换浣曠粍浠朵腑閮藉彲浠ヤ娇鐢?$t 鍑芥暟杩涜缈昏瘧
 * - 绫诲瀷瀹夊叏 - 鎻愪緵 TypeScript 绫诲瀷鏀寔
 *
 * ## 鏀寔鐨勮瑷€
 *
 * - zh: 绠€浣撲腑鏂?
 * - en: English
 *
 * @module locales
 * @author AiPay
 */

import { createI18n } from 'vue-i18n'
import type { I18n, I18nOptions } from 'vue-i18n'
import { LanguageEnum } from '@/enums/appEnum'
import { getSystemStorage } from '@/utils/storage'
import { StorageKeyManager } from '@/utils/storage/storage-key-manager'

// 鍚屾瀵煎叆璇█鏂囦欢
import enMessages from './langs/en.json'
import zhMessages from './langs/zh.json'

/**
 * 瀛樺偍閿鐞嗗櫒瀹炰緥
 */
const storageKeyManager = new StorageKeyManager()

/**
 * 璇█娑堟伅瀵硅薄
 */
const messages = {
  [LanguageEnum.EN]: enMessages,
  [LanguageEnum.ZH]: zhMessages
}

/**
 * 璇█閫夐」鍒楄〃
 * 鐢ㄤ簬璇█鍒囨崲涓嬫媺妗?
 */
export const languageOptions = [
  { value: LanguageEnum.ZH, label: '简体中文' },
  { value: LanguageEnum.EN, label: 'English' }
]

/**
 * 浠庡瓨鍌ㄤ腑鑾峰彇璇█璁剧疆
 * @returns 璇█璁剧疆锛屽鏋滆幏鍙栧け璐ュ垯杩斿洖榛樿璇█
 */
const getDefaultLanguage = (): LanguageEnum => {
  // 灏濊瘯浠庣増鏈寲鐨勫瓨鍌ㄤ腑鑾峰彇璇█璁剧疆
  try {
    const storageKey = storageKeyManager.getStorageKey('user')
    const userStore = localStorage.getItem(storageKey)

    if (userStore) {
      const { language } = JSON.parse(userStore)
      if (language && Object.values(LanguageEnum).includes(language)) {
        return language
      }
    }
  } catch (error) {
    console.warn('[i18n] 浠庣増鏈寲瀛樺偍鑾峰彇璇█璁剧疆澶辫触:', error)
  }

  // 灏濊瘯浠庣郴缁熷瓨鍌ㄤ腑鑾峰彇璇█璁剧疆
  try {
    const sys = getSystemStorage()
    if (sys) {
      const { user } = JSON.parse(sys)
      if (user?.language && Object.values(LanguageEnum).includes(user.language)) {
        return user.language
      }
    }
  } catch (error) {
    console.warn('[i18n] 浠庣郴缁熷瓨鍌ㄨ幏鍙栬瑷€璁剧疆澶辫触:', error)
  }

  // 杩斿洖榛樿璇█
  console.debug('[i18n] 浣跨敤榛樿璇█:', LanguageEnum.ZH)
  return LanguageEnum.ZH
}

/**
 * i18n 閰嶇疆閫夐」
 */
const i18nOptions: I18nOptions = {
  locale: getDefaultLanguage(),
  legacy: false,
  globalInjection: true,
  fallbackLocale: LanguageEnum.ZH,
  messages
}

/**
 * i18n 瀹炰緥
 */
const i18n: I18n = createI18n(i18nOptions)

/**
 * 缈昏瘧鍑芥暟绫诲瀷
 */
interface Translation {
  (key: string): string
}

/**
 * 鍏ㄥ眬缈昏瘧鍑芥暟
 * 鍙湪浠讳綍鍦版柟浣跨敤锛屾棤闇€瀵煎叆 useI18n
 */
export const $t = i18n.global.t as Translation

export default i18n

