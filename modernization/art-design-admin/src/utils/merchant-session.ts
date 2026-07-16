const MERCHANT_FRONT_TOKEN_KEY = 'aipay_merchant_front_token'

function canUseStorage() {
  return typeof window !== 'undefined' && typeof window.localStorage !== 'undefined'
}

export function getMerchantFrontToken(): string {
  if (!canUseStorage()) {
    return ''
  }

  try {
    return String(window.localStorage.getItem(MERCHANT_FRONT_TOKEN_KEY) || '').trim()
  } catch {
    return ''
  }
}

export function setMerchantFrontToken(token: string) {
  if (!canUseStorage()) {
    return
  }

  const normalized = String(token || '').trim()
  try {
    if (normalized) {
      window.localStorage.setItem(MERCHANT_FRONT_TOKEN_KEY, normalized)
      return
    }

    window.localStorage.removeItem(MERCHANT_FRONT_TOKEN_KEY)
  } catch {
    // Ignore storage write failures in restricted environments.
  }
}

export function clearMerchantFrontToken() {
  setMerchantFrontToken('')
}
