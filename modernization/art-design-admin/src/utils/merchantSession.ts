/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

const MERCHANT_FRONT_TOKEN_KEY = 'aipay_merchant_front_token'
const MERCHANT_FRONT_COOKIE_KEY = 'front_token'

function canUseStorage() {
  return typeof window !== 'undefined' && typeof window.localStorage !== 'undefined'
}

function canUseDocumentCookie() {
  return typeof document !== 'undefined'
}

function normalizeMerchantFrontToken(token: string) {
  return String(token || '').trim()
}

function readMerchantFrontCookie(): string {
  if (!canUseDocumentCookie()) {
    return ''
  }

  try {
    const cookieSource = String(document.cookie || '')
    const prefix = `${MERCHANT_FRONT_COOKIE_KEY}=`
    const matched = cookieSource
      .split(';')
      .map((item) => item.trim())
      .find((item) => item.startsWith(prefix))

    if (!matched) {
      return ''
    }

    return normalizeMerchantFrontToken(decodeURIComponent(matched.slice(prefix.length)))
  } catch {
    return ''
  }
}

function writeMerchantFrontCookie(token: string) {
  if (!canUseDocumentCookie()) {
    return
  }

  try {
    const normalized = normalizeMerchantFrontToken(token)
    if (normalized === '') {
      document.cookie = `${MERCHANT_FRONT_COOKIE_KEY}=; Path=/; Max-Age=0; SameSite=Lax`
      return
    }

    document.cookie = `${MERCHANT_FRONT_COOKIE_KEY}=${encodeURIComponent(normalized)}; Path=/; SameSite=Lax`
  } catch {
    // Ignore cookie write failures in restricted environments.
  }
}

export function getMerchantFrontToken(): string {
  const storageToken = canUseStorage()
    ? (() => {
        try {
          return normalizeMerchantFrontToken(
            String(window.localStorage.getItem(MERCHANT_FRONT_TOKEN_KEY) || '')
          )
        } catch {
          return ''
        }
      })()
    : ''

  if (storageToken !== '') {
    return storageToken
  }

  const cookieToken = readMerchantFrontCookie()
  if (cookieToken !== '' && canUseStorage()) {
    try {
      window.localStorage.setItem(MERCHANT_FRONT_TOKEN_KEY, cookieToken)
    } catch {
      // Ignore storage write failures in restricted environments.
    }
  }

  return cookieToken
}

export function syncMerchantFrontTokenFromCookie(): string {
  const cookieToken = readMerchantFrontCookie()
  if (cookieToken === '') {
    return ''
  }

  setMerchantFrontToken(cookieToken)
  return cookieToken
}

export function setMerchantFrontToken(token: string) {
  if (!canUseStorage()) {
    writeMerchantFrontCookie(token)
    return
  }

  try {
    const normalized = normalizeMerchantFrontToken(token)
    if (normalized) {
      window.localStorage.setItem(MERCHANT_FRONT_TOKEN_KEY, normalized)
      writeMerchantFrontCookie(normalized)
      return
    }

    window.localStorage.removeItem(MERCHANT_FRONT_TOKEN_KEY)
    writeMerchantFrontCookie('')
  } catch {
    // Ignore storage write failures in restricted environments.
  }
}

export function clearMerchantFrontToken() {
  setMerchantFrontToken('')
}
