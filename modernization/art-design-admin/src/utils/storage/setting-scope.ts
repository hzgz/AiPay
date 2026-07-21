import { StorageConfig } from './storage-config'
import { getMerchantFrontToken } from '@/utils/merchant-session'

export type ConsoleSettingScope = 'admin' | 'merchant'

const MERCHANT_SCOPE_PREFIX = '/merchant'
const ADMIN_STORAGE_SCOPE = 'admin'
const MERCHANT_GUEST_STORAGE_SCOPE = 'merchant:guest'

function getBrowserStorage() {
  if (typeof window === 'undefined') {
    return null
  }

  return window.localStorage
}

function normalizeRoutePath(path: string) {
  const [routePath] = String(path || '/').split('?')
  const normalized = routePath.trim()
  return normalized !== '' ? normalized : '/'
}

function isMerchantRoutePath(path: string) {
  const normalized = normalizeRoutePath(path)
  return normalized === MERCHANT_SCOPE_PREFIX || normalized.startsWith(`${MERCHANT_SCOPE_PREFIX}/`)
}

export function resolveConsoleSettingScope(path?: string): ConsoleSettingScope {
  if (typeof path === 'string' && path.trim() !== '') {
    return isMerchantRoutePath(path) ? 'merchant' : 'admin'
  }

  if (typeof window === 'undefined') {
    return 'admin'
  }

  const hash = String(window.location.hash || '')
  if (hash.startsWith('#/')) {
    return isMerchantRoutePath(hash.slice(1)) ? 'merchant' : 'admin'
  }

  return isMerchantRoutePath(window.location.pathname) ? 'merchant' : 'admin'
}

export function getScopedSettingStorageKey(
  baseKey: string,
  scope: string = resolveConsoleSettingStorageScope()
) {
  return `${baseKey}:${scope}`
}

export function getScopedThemeStorageKey(scope: string = resolveConsoleSettingStorageScope()) {
  return `${StorageConfig.THEME_KEY}:${scope}`
}

export function resolveConsoleSettingStorageScope(path?: string) {
  const scope = resolveConsoleSettingScope(path)
  if (scope === 'merchant') {
    const merchantToken = String(getMerchantFrontToken() || '').trim()
    return merchantToken !== '' ? `merchant:${merchantToken}` : MERCHANT_GUEST_STORAGE_SCOPE
  }

  return ADMIN_STORAGE_SCOPE
}

export function readScopedSettingItem(
  key: string,
  scope: string = resolveConsoleSettingStorageScope()
) {
  const storage = getBrowserStorage()
  if (!storage) {
    return null
  }

  const scopedKey = getScopedSettingStorageKey(key, scope)
  const scopedValue = storage.getItem(scopedKey)
  if (scopedValue !== null) {
    return scopedValue
  }

  if (scope === ADMIN_STORAGE_SCOPE) {
    const legacyValue = storage.getItem(key)
    if (legacyValue !== null) {
      storage.setItem(scopedKey, legacyValue)
      storage.removeItem(key)
      return legacyValue
    }
  }

  return null
}

export function writeScopedThemeValue(
  theme: string,
  scope: string = resolveConsoleSettingStorageScope()
) {
  const storage = getBrowserStorage()
  if (!storage) {
    return
  }

  storage.setItem(getScopedThemeStorageKey(scope), theme)
  storage.removeItem(StorageConfig.THEME_KEY)
}

export function readScopedThemeValue(scope: string = resolveConsoleSettingStorageScope()) {
  const storage = getBrowserStorage()
  if (!storage) {
    return null
  }

  const scopedKey = getScopedThemeStorageKey(scope)
  const scopedValue = storage.getItem(scopedKey)
  if (scopedValue !== null) {
    return scopedValue
  }

  if (scope === ADMIN_STORAGE_SCOPE) {
    const legacyValue = storage.getItem(StorageConfig.THEME_KEY)
    if (legacyValue !== null) {
      storage.setItem(scopedKey, legacyValue)
      storage.removeItem(StorageConfig.THEME_KEY)
      return legacyValue
    }
  }

  return null
}

export const scopedSettingStorage: Storage = {
  get length() {
    const storage = getBrowserStorage()
    return storage ? storage.length : 0
  },
  clear() {
    const storage = getBrowserStorage()
    if (!storage) {
      return
    }

    storage.removeItem(getScopedSettingStorageKey('setting'))
  },
  getItem(key: string) {
    return readScopedSettingItem(key)
  },
  key(index: number) {
    const storage = getBrowserStorage()
    return storage ? storage.key(index) : null
  },
  removeItem(key: string) {
    const storage = getBrowserStorage()
    if (!storage) {
      return
    }

    storage.removeItem(getScopedSettingStorageKey(key))
  },
  setItem(key: string, value: string) {
    const storage = getBrowserStorage()
    if (!storage) {
      return
    }

    storage.setItem(getScopedSettingStorageKey(key), value)
  }
}
