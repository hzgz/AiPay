/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

function trimTrailingSlash(value: string): string {
  return value.replace(/\/+$/, '')
}

function isAbsoluteHttpUrl(value: string): boolean {
  return /^https?:\/\//i.test(value)
}

export function resolveApiBaseUrl(): string {
  const configuredUrl = String(import.meta.env.VITE_API_URL || '').trim()

  if (isAbsoluteHttpUrl(configuredUrl)) {
    return trimTrailingSlash(configuredUrl)
  }

  // Local split-port mode: frontend 8132 talks directly to backend 8787.
  if (typeof window !== 'undefined' && window.location.port === '8132') {
    return `${window.location.protocol}//${window.location.hostname}:8787`
  }

  // Same-origin deployments already route `/api/*` and legacy compatibility paths
  // through the current host, so returning `/api` here would produce `/api/api/*`.
  if (configuredUrl === '/api' || configuredUrl.toLowerCase() === '/api/') {
    return ''
  }

  return configuredUrl
}

export function resolveBackendOrigin(): string {
  const configuredOrigin = String(import.meta.env.VITE_PUBLIC_BACKEND_ORIGIN || '').trim()
  if (configuredOrigin) {
    return trimTrailingSlash(configuredOrigin)
  }

  const apiBaseUrl = resolveApiBaseUrl()
  if (isAbsoluteHttpUrl(apiBaseUrl)) {
    return trimTrailingSlash(apiBaseUrl.replace(/\/api\/?$/i, ''))
  }

  if (typeof window !== 'undefined') {
    return window.location.origin
  }

  return ''
}

export function buildApiUrl(path: string): string {
  const normalizedPath = path.startsWith('/') ? path : `/${path}`
  const apiBaseUrl = resolveApiBaseUrl()

  if (!apiBaseUrl) {
    return normalizedPath
  }

  if (isAbsoluteHttpUrl(apiBaseUrl)) {
    return `${trimTrailingSlash(apiBaseUrl)}${normalizedPath}`
  }

  const trimmedBase = trimTrailingSlash(apiBaseUrl)
  if (trimmedBase.toLowerCase().endsWith('/api') && normalizedPath.startsWith('/api/')) {
    return `${trimmedBase}${normalizedPath.slice(4)}`
  }

  if (trimmedBase === '/api' && normalizedPath === '/api') {
    return trimmedBase
  }

  return `${trimmedBase}${normalizedPath}`
}
