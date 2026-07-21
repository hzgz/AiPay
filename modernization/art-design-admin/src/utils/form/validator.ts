/**
 * 琛ㄥ崟楠岃瘉宸ュ叿妯″潡
 *
 * 鎻愪緵鍏ㄩ潰鐨勮〃鍗曞瓧娈甸獙璇佸姛鑳?
 *
 * ## 涓昏鍔熻兘
 *
 * - 鎵嬫満鍙风爜楠岃瘉锛堜腑鍥藉ぇ闄嗘牸寮忥級
 * - 鍥哄畾鐢佃瘽楠岃瘉锛堟敮鎸佸尯鍙锋牸寮忥級
 * - 鐢ㄦ埛璐﹀彿楠岃瘉锛堝瓧姣嶅紑澶达紝鏀寔鏁板瓧鍜屼笅鍒掔嚎锛?
 * - 瀵嗙爜寮哄害楠岃瘉锛堟櫘閫氬瘑鐮併€佸己瀵嗙爜锛?
 * - 瀵嗙爜寮哄害璇勪及锛堝急銆佷腑銆佸己锛?
 * - IPv4 鍦板潃楠岃瘉
 * - 閭鍦板潃楠岃瘉锛圧FC 5322 鏍囧噯锛?
 * - URL 鍦板潃楠岃瘉
 * - 韬唤璇佸彿鐮侀獙璇侊紙18浣嶏紝鍚牎楠岀爜楠岃瘉锛?
 * - 閾惰鍗″彿楠岃瘉锛圠uhn 绠楁硶锛?
 * - 瀛楃涓茬┖鏍煎鐞?
 *
 * ## 楠岃瘉瑙勫垯
 *
 * - 鎵嬫満鍙凤細1寮€澶达紝绗簩浣?-9锛屽叡11浣?
 * - 璐﹀彿锛氬瓧姣嶅紑澶达紝5-20浣嶏紝鏀寔瀛楁瘝鏁板瓧涓嬪垝绾?
 * - 鏅€氬瘑鐮侊細6-20浣嶏紝蹇呴』鍖呭惈瀛楁瘝鍜屾暟瀛?
 * - 寮哄瘑鐮侊細8-20浣嶏紝蹇呴』鍖呭惈澶у皬鍐欏瓧姣嶃€佹暟瀛楀拰鐗规畩瀛楃
 * - 韬唤璇侊細18浣嶏紝鍚嚭鐢熸棩鏈熷拰鏍￠獙鐮侀獙璇?
 * - 閾惰鍗★細13-19浣嶏紝閫氳繃 Luhn 绠楁硶楠岃瘉
 *
 * @module utils/validation/formValidator
 * @author AiPay
 */

/**
 * 瀵嗙爜寮哄害绾у埆鏋氫妇
 */
export enum PasswordStrength {
  WEAK = '弱',
  MEDIUM = '中',
  STRONG = '强'
}

/**
 * 鍘婚櫎瀛楃涓查灏剧┖鏍?
 * @param value 寰呭鐞嗙殑瀛楃涓?
 * @returns 杩斿洖鍘婚櫎棣栧熬绌烘牸鍚庣殑瀛楃涓?
 */
export function trimSpaces(value: string): string {
  if (typeof value !== 'string') {
    return ''
  }
  return value.trim()
}

/**
 * 楠岃瘉鎵嬫満鍙风爜锛堜腑鍥藉ぇ闄嗭級
 * @param value 鎵嬫満鍙风爜瀛楃涓?
 * @returns 杩斿洖楠岃瘉缁撴灉锛宼rue琛ㄧず鏍煎紡姝ｇ‘
 */
export function validatePhone(value: string): boolean {
  if (!value || typeof value !== 'string') {
    return false
  }

  // 涓浗澶ч檰鎵嬫満鍙风爜锛?寮€澶达紝绗簩浣嶄负3-9锛屽叡11浣嶆暟瀛?
  const phoneRegex = /^1[3-9]\d{9}$/
  return phoneRegex.test(value.trim())
}

/**
 * 楠岃瘉鍥哄畾鐢佃瘽鍙风爜锛堜腑鍥藉ぇ闄嗭級
 * @param value 鐢佃瘽鍙风爜瀛楃涓?
 * @returns 杩斿洖楠岃瘉缁撴灉锛宼rue琛ㄧず鏍煎紡姝ｇ‘
 */
export function validateTelPhone(value: string): boolean {
  if (!value || typeof value !== 'string') {
    return false
  }

  // 鏀寔鏍煎紡锛氬尯鍙?鍙风爜锛屽锛?10-12345678銆?755-1234567
  const telRegex = /^0\d{2,3}-?\d{7,8}$/
  return telRegex.test(value.trim().replace(/\s+/g, ''))
}

/**
 * 楠岃瘉鐢ㄦ埛璐﹀彿
 * @param value 璐﹀彿瀛楃涓?
 * @returns 杩斿洖楠岃瘉缁撴灉锛宼rue琛ㄧず鏍煎紡姝ｇ‘
 * @description 瑙勫垯锛氬瓧姣嶅紑澶达紝5-20浣嶏紝鏀寔瀛楁瘝銆佹暟瀛椼€佷笅鍒掔嚎
 */
export function validateAccount(value: string): boolean {
  if (!value || typeof value !== 'string') {
    return false
  }

  // 瀛楁瘝寮€澶达紝5-20浣嶏紝鏀寔瀛楁瘝銆佹暟瀛椼€佷笅鍒掔嚎
  const accountRegex = /^[a-zA-Z][a-zA-Z0-9_]{4,19}$/
  return accountRegex.test(value.trim())
}

/**
 * 楠岃瘉瀵嗙爜
 * @param value 瀵嗙爜瀛楃涓?
 * @returns 杩斿洖楠岃瘉缁撴灉锛宼rue琛ㄧず鏍煎紡姝ｇ‘
 * @description 瑙勫垯锛?-20浣嶏紝蹇呴』鍖呭惈瀛楁瘝鍜屾暟瀛?
 */
export function validatePassword(value: string): boolean {
  if (!value || typeof value !== 'string') {
    return false
  }

  const trimmedValue = value.trim()

  // 闀垮害妫€鏌?
  if (trimmedValue.length < 6 || trimmedValue.length > 20) {
    return false
  }

  // 蹇呴』鍖呭惈瀛楁瘝鍜屾暟瀛?
  const hasLetter = /[a-zA-Z]/.test(trimmedValue)
  const hasNumber = /\d/.test(trimmedValue)

  return hasLetter && hasNumber
}

/**
 * 楠岃瘉寮哄瘑鐮?
 * @param value 瀵嗙爜瀛楃涓?
 * @returns 杩斿洖楠岃瘉缁撴灉锛宼rue琛ㄧず鏍煎紡姝ｇ‘
 * @description 瑙勫垯锛?-20浣嶏紝蹇呴』鍖呭惈澶у啓瀛楁瘝銆佸皬鍐欏瓧姣嶃€佹暟瀛楀拰鐗规畩瀛楃
 */
export function validateStrongPassword(value: string): boolean {
  if (!value || typeof value !== 'string') {
    return false
  }

  const trimmedValue = value.trim()

  // 闀垮害妫€鏌?
  if (trimmedValue.length < 8 || trimmedValue.length > 20) {
    return false
  }

  // 蹇呴』鍖呭惈锛氬ぇ鍐欏瓧姣嶃€佸皬鍐欏瓧姣嶃€佹暟瀛椼€佺壒娈婂瓧绗?
  const hasUpperCase = /[A-Z]/.test(trimmedValue)
  const hasLowerCase = /[a-z]/.test(trimmedValue)
  const hasNumber = /\d/.test(trimmedValue)
  const hasSpecialChar = /[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?]/.test(trimmedValue)

  return hasUpperCase && hasLowerCase && hasNumber && hasSpecialChar
}

/**
 * 鑾峰彇瀵嗙爜寮哄害
 * @param value 瀵嗙爜瀛楃涓?
 * @returns 杩斿洖瀵嗙爜寮哄害锛氬急銆佷腑銆佸己
 * @description 寮憋細绾暟瀛?绾瓧姣?绾壒娈婂瓧绗︼紱涓細涓ょ缁勫悎锛涘己锛氫笁绉嶆垨浠ヤ笂缁勫悎
 */
export function getPasswordStrength(value: string): PasswordStrength {
  if (!value || typeof value !== 'string') {
    return PasswordStrength.WEAK
  }

  const trimmedValue = value.trim()

  if (trimmedValue.length < 6) {
    return PasswordStrength.WEAK
  }

  const hasUpperCase = /[A-Z]/.test(trimmedValue)
  const hasLowerCase = /[a-z]/.test(trimmedValue)
  const hasNumber = /\d/.test(trimmedValue)
  const hasSpecialChar = /[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?]/.test(trimmedValue)

  const typeCount = [hasUpperCase, hasLowerCase, hasNumber, hasSpecialChar].filter(Boolean).length

  if (typeCount >= 3) {
    return PasswordStrength.STRONG
  } else if (typeCount >= 2) {
    return PasswordStrength.MEDIUM
  } else {
    return PasswordStrength.WEAK
  }
}

/**
 * 楠岃瘉IPv4鍦板潃
 * @param value IP鍦板潃瀛楃涓?
 * @returns 杩斿洖楠岃瘉缁撴灉锛宼rue琛ㄧず鏍煎紡姝ｇ‘
 */
export function validateIPv4Address(value: string): boolean {
  if (!value || typeof value !== 'string') {
    return false
  }

  const trimmedValue = value.trim()
  const ipRegex = /^((25[0-5]|2[0-4]\d|[01]?\d{1,2})\.){3}(25[0-5]|2[0-4]\d|[01]?\d{1,2})$/

  if (!ipRegex.test(trimmedValue)) {
    return false
  }

  // 棰濆妫€鏌ユ瘡涓鏄惁鍦ㄦ湁鏁堣寖鍥村唴
  const segments = trimmedValue.split('.')
  return segments.every((segment) => {
    const num = parseInt(segment, 10)
    return num >= 0 && num <= 255
  })
}

/**
 * 楠岃瘉閭鍦板潃
 * @param value 閭鍦板潃瀛楃涓?
 * @returns 杩斿洖楠岃瘉缁撴灉锛宼rue琛ㄧず鏍煎紡姝ｇ‘
 */
export function validateEmail(value: string): boolean {
  if (!value || typeof value !== 'string') {
    return false
  }

  const trimmedValue = value.trim()

  // RFC 5322 鏍囧噯鐨勭畝鍖栫増閭姝ｅ垯
  const emailRegex =
    /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/

  return emailRegex.test(trimmedValue) && trimmedValue.length <= 254
}

/**
 * 楠岃瘉URL鍦板潃
 * @param value URL瀛楃涓?
 * @returns 杩斿洖楠岃瘉缁撴灉锛宼rue琛ㄧず鏍煎紡姝ｇ‘
 */
export function validateURL(value: string): boolean {
  if (!value || typeof value !== 'string') {
    return false
  }

  try {
    new URL(value.trim())
    return true
  } catch {
    return false
  }
}

/**
 * 楠岃瘉韬唤璇佸彿鐮侊紙涓浗澶ч檰锛?
 * @param value 韬唤璇佸彿鐮佸瓧绗︿覆
 * @returns 杩斿洖楠岃瘉缁撴灉锛宼rue琛ㄧず鏍煎紡姝ｇ‘
 */
export function validateChineseIDCard(value: string): boolean {
  if (!value || typeof value !== 'string') {
    return false
  }

  const trimmedValue = value.trim()

  // 18浣嶈韩浠借瘉鍙风爜姝ｅ垯
  const idCardRegex =
    /^[1-9]\d{5}(18|19|20)\d{2}((0[1-9])|(1[0-2]))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$/

  if (!idCardRegex.test(trimmedValue)) {
    return false
  }

  // 楠岃瘉鏍￠獙鐮?
  const weights = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2]
  const checkCodes = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2']

  let sum = 0
  for (let i = 0; i < 17; i++) {
    sum += parseInt(trimmedValue[i]) * weights[i]
  }

  const checkCode = checkCodes[sum % 11]
  return trimmedValue[17].toUpperCase() === checkCode
}

/**
 * 楠岃瘉閾惰鍗″彿
 * @param value 閾惰鍗″彿瀛楃涓?
 * @returns 杩斿洖楠岃瘉缁撴灉锛宼rue琛ㄧず鏍煎紡姝ｇ‘
 */
export function validateBankCard(value: string): boolean {
  if (!value || typeof value !== 'string') {
    return false
  }

  const trimmedValue = value.trim().replace(/\s+/g, '')

  // 閾惰鍗″彿閫氬父涓?3-19浣嶆暟瀛?
  if (!/^\d{13,19}$/.test(trimmedValue)) {
    return false
  }

  // Luhn绠楁硶楠岃瘉
  let sum = 0
  let shouldDouble = false

  for (let i = trimmedValue.length - 1; i >= 0; i--) {
    let digit = parseInt(trimmedValue[i])

    if (shouldDouble) {
      digit *= 2
      if (digit > 9) {
        digit = (digit % 10) + 1
      }
    }

    sum += digit
    shouldDouble = !shouldDouble
  }

  return sum % 10 === 0
}

