

import { AppRouteRecord } from '@/types'

export function isIframe(url: string): boolean {
  return url.startsWith('/outside/iframe/')
}

export const isNavigableMenuItem = (menuItem: AppRouteRecord): boolean => {
  if (!menuItem.path || !menuItem.path.trim()) {
    return false
  }

  if (!menuItem.meta?.isHide) {
    return true
  }

  return menuItem.meta?.isFullPage === true
}

const normalizePath = (path: string): string => {
  return path.startsWith('/') ? path : `/${path}`
}

export const getFirstMenuPath = (menuList: AppRouteRecord[]): string => {
  if (!Array.isArray(menuList) || menuList.length === 0) {
    return ''
  }

  for (const menuItem of menuList) {
    if (!isNavigableMenuItem(menuItem)) {
      continue
    }

    if (menuItem.children?.length) {
      const childPath = getFirstMenuPath(menuItem.children)
      if (childPath) {
        return childPath
      }
    }

    return normalizePath(menuItem.path!)
  }

  return ''
}

