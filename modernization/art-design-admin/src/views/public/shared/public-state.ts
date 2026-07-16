import { PublicCompatError } from '@/api/public-client'

export function resolvePublicErrorMessage(error: unknown, fallback = '页面加载失败，请稍后重试。') {
  if (error instanceof PublicCompatError) {
    return normalizePublicErrorText(error.message, error.status, fallback)
  }

  if (error instanceof Error) {
    return normalizePublicErrorText(error.message, 0, fallback)
  }

  return fallback
}

export function normalizePublicPage(value: unknown, fallback = 1) {
  const page = Number(value)
  if (!Number.isFinite(page) || page < 1) {
    return fallback
  }

  return Math.floor(page)
}

export function scrollPublicPageToTop() {
  if (typeof window === 'undefined') {
    return
  }

  window.scrollTo({
    top: 0,
    left: 0,
    behavior: 'auto'
  })
}

function normalizePublicErrorText(message: string, status: number, fallback: string) {
  const raw = String(message || '').trim()
  if (!raw) {
    return fallback
  }

  const lower = raw.toLowerCase()

  if (
    lower === 'server internal error' ||
    lower === 'internal server error' ||
    lower.includes('sqlstate') ||
    lower.includes('database') ||
    status >= 500
  ) {
    return '服务暂时不可用，请稍后再试。'
  }

  if (lower === 'network error' || lower.includes('err_connection_refused')) {
    return '网络连接失败，请确认服务已经启动。'
  }

  if (lower.includes('timeout')) {
    return '请求超时，请稍后重试。'
  }

  return raw
}
