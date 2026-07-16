import { PublicCompatError } from '@/api/public-client'

export function resolvePublicErrorMessage(error: unknown, fallback = '页面加载失败，请稍后重试。') {
  if (error instanceof PublicCompatError) {
    return error.message || fallback
  }

  if (error instanceof Error) {
    return error.message || fallback
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
