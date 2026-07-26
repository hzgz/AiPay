import { PublicCompatError } from '@/api/publicClient'

export function resolvePublicErrorMessage(
  error: unknown,
  fallback = '页面加载失败，请稍后再试。'
) {
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

export function resolvePublicAffiliateId(value?: unknown) {
  const routeValue = normalizePublicAffiliateValue(value)
  if (routeValue !== '') {
    return routeValue
  }

  if (typeof window === 'undefined') {
    return ''
  }

  return normalizePublicAffiliateValue(new URLSearchParams(window.location.search).get('aff'))
}

export function appendPublicAffiliateQuery(url: string, affiliateId?: unknown) {
  const raw = String(url || '').trim()
  const normalizedAffiliateId = normalizePublicAffiliateValue(affiliateId)

  if (raw === '' || normalizedAffiliateId === '') {
    return raw
  }

  if (raw.includes('#/')) {
    const [base, hash = ''] = raw.split('#', 2)
    const [hashPath, hashQuery = ''] = hash.split('?', 2)
    const params = new URLSearchParams(hashQuery)
    if (!params.has('aff')) {
      params.set('aff', normalizedAffiliateId)
    }

    const queryString = params.toString()
    return `${base}#${hashPath}${queryString ? `?${queryString}` : ''}`
  }

  try {
    const fallbackOrigin =
      typeof window === 'undefined' ? 'https://aipay.local' : window.location.origin
    const parsed = new URL(raw, fallbackOrigin)
    if (!parsed.searchParams.has('aff')) {
      parsed.searchParams.set('aff', normalizedAffiliateId)
    }

    return /^[a-z]+:\/\//i.test(raw)
      ? parsed.toString()
      : `${parsed.pathname}${parsed.search}${parsed.hash}`
  } catch {
    return raw
  }
}

function normalizePublicAffiliateValue(value: unknown) {
  if (Array.isArray(value)) {
    return normalizePublicAffiliateValue(value[0])
  }

  const raw = String(value ?? '').trim()
  return /^[1-9]\d*$/.test(raw) ? raw : ''
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
