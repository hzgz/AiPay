/**
 * Menu processing service.
 *
 * Fetches, filters, and normalizes route menus for the current app mode.
 *
 * @module router/core/MenuProcessor
 * @author AiPay
 */

import type { AppRouteRecord } from '@/types/router'
import { useUserStore } from '@/store/modules/user'
import { useAppMode } from '@/hooks/core/useAppMode'
import { fetchGetMenuList } from '@/api/systemManage'
import { asyncRoutes } from '../routes/asyncRoutes'
import { RoutesAlias } from '../routesAlias'
import { formatMenuTitle } from '@/utils'

const enableDemoRoutes = import.meta.env.VITE_ENABLE_DEMO_ROUTES === 'true'
const hiddenReleaseRoutePrefixes = [
  '/exception'
]
const hiddenReleaseComponentPrefixes = [
  '/exception/'
]
const hiddenReleaseRouteNames = new Set([
  'exception',
  'exception403',
  'exception404',
  'exception500'
])

export class MenuProcessor {
  /**
   * Fetch and prepare the effective menu list.
   */
  async getMenuList(): Promise<AppRouteRecord[]> {
    const { isFrontendMode } = useAppMode()

    let menuList: AppRouteRecord[]
    if (isFrontendMode.value) {
      menuList = await this.processFrontendMenu()
    } else {
      menuList = await this.processBackendMenu()
    }

    if (!enableDemoRoutes) {
      menuList = this.filterReleaseMenus(menuList)
    }

    // Validate raw menu paths before normalization.
    this.validateMenuPaths(menuList)

    // Normalize relative paths into full route paths.
    return this.normalizeMenuPaths(menuList)
  }

  /**
   * Build menus for the frontend-controlled mode.
   */
  private async processFrontendMenu(): Promise<AppRouteRecord[]> {
    const userStore = useUserStore()
    const roles = userStore.info?.roles

    let menuList = [...asyncRoutes]

    // Filter menus by the current user's roles when role data is available.
    if (roles && roles.length > 0) {
      menuList = this.filterMenuByRoles(menuList, roles)
    }

    return this.filterEmptyMenus(menuList)
  }

  /**
   * Build menus for the backend-controlled mode.
   */
  private async processBackendMenu(): Promise<AppRouteRecord[]> {
    const list = await fetchGetMenuList()
    return this.filterEmptyMenus(list)
  }

  /**
   * Filter menu entries by role permissions.
   */
  private filterMenuByRoles(menu: AppRouteRecord[], roles: string[]): AppRouteRecord[] {
    return menu.reduce((acc: AppRouteRecord[], item) => {
      const itemRoles = item.meta?.roles
      const hasPermission = !itemRoles || itemRoles.some((role) => roles?.includes(role))

      if (hasPermission) {
        const filteredItem = { ...item }
        if (filteredItem.children?.length) {
          filteredItem.children = this.filterMenuByRoles(filteredItem.children, roles)
        }
        acc.push(filteredItem)
      }

      return acc
    }, [])
  }

  /**
   * Recursively remove empty menu entries while keeping valid containers.
   */
  private filterEmptyMenus(menuList: AppRouteRecord[]): AppRouteRecord[] {
    return menuList
      .map((item) => {
        // Filter child entries before deciding whether the container should remain.
        if (item.children && item.children.length > 0) {
          const filteredChildren = this.filterEmptyMenus(item.children)
          return {
            ...item,
            children: filteredChildren
          }
        }
        return item
      })
      .filter((item) => {
        // Keep directory-style menus when they explicitly carry a children field.
        if ('children' in item) {
          return true
        }

        // External links and iframe routes are still navigable entries.
        if (item.meta?.isIframe === true || item.meta?.link) {
          return true
        }

        // Keep entries that point to a concrete component.
        if (item.component && item.component !== '' && item.component !== RoutesAlias.Layout) {
          return true
        }

        // Drop placeholder items that cannot be opened on their own.
        return false
      })
  }

  /**
   * Check whether the menu list contains usable data.
   */
  validateMenuList(menuList: AppRouteRecord[]): boolean {
    return Array.isArray(menuList) && menuList.length > 0
  }

  /**
   * Remove demo-style routes from release builds.
   */
  private filterReleaseMenus(menuList: AppRouteRecord[]): AppRouteRecord[] {
    return menuList.reduce((acc: AppRouteRecord[], item) => {
      if (this.isReleaseHiddenRoute(item)) {
        return acc
      }

      const nextItem: AppRouteRecord = { ...item }

      if (nextItem.children?.length) {
        nextItem.children = this.filterReleaseMenus(nextItem.children)
      }

      const hasChildren = Array.isArray(nextItem.children) && nextItem.children.length > 0
      const canNavigateSelf =
        Boolean(nextItem.meta?.link) ||
        nextItem.meta?.isIframe === true ||
        (Boolean(nextItem.component) && nextItem.component !== RoutesAlias.Layout)

      if (!hasChildren && !canNavigateSelf) {
        return acc
      }

      acc.push(nextItem)
      return acc
    }, [])
  }

  private isReleaseHiddenRoute(route: AppRouteRecord): boolean {
    const path = String(route.path || '').trim().toLowerCase()
    const component = String(route.component || '').trim().toLowerCase()
    const name = String(route.name || '').trim().toLowerCase()

    return (
      hiddenReleaseRoutePrefixes.some((prefix) => path === prefix || path.startsWith(`${prefix}/`)) ||
      hiddenReleaseComponentPrefixes.some(
        (prefix) => component === prefix || component.startsWith(prefix)
      ) ||
      hiddenReleaseRouteNames.has(name)
    )
  }

  /**
   * Normalize menu paths into full, navigable route paths.
   */
  private normalizeMenuPaths(menuList: AppRouteRecord[], parentPath = ''): AppRouteRecord[] {
    return menuList.map((item) => {
      // Build the full path for the current menu entry.
      const fullPath = this.buildFullPath(item.path || '', parentPath)

      // Normalize children using the resolved parent path.
      const children = item.children?.length
        ? this.normalizeMenuPaths(item.children, fullPath)
        : item.children

      const redirect = item.redirect || this.resolveDefaultRedirect(children)

      return {
        ...item,
        path: fullPath,
        redirect,
        children
      }
    })
  }

  /**
   * Infer a default redirect target for directory-style menus.
   */
  private resolveDefaultRedirect(children?: AppRouteRecord[]): string | undefined {
    if (!children?.length) {
      return undefined
    }

    for (const child of children) {
      if (this.isNavigableRoute(child)) {
        return child.path
      }

      const nestedRedirect = this.resolveDefaultRedirect(child.children)
      if (nestedRedirect) {
        return nestedRedirect
      }
    }

    return undefined
  }

  /**
   * Check whether a child route can be used as a redirect landing target.
   */
  private isNavigableRoute(route: AppRouteRecord): boolean {
    return Boolean(
      route.path &&
        route.path !== '/' &&
        !route.meta?.link &&
        route.meta?.isIframe !== true &&
        route.component &&
        route.component !== ''
    )
  }

  /**
   * Validate menu path declarations before normalization.
   *
   * Non-root child routes should not start with a leading slash unless they are
   * external links or iframe routes.
   */
  private validateMenuPaths(menuList: AppRouteRecord[], level = 1): void {
    menuList.forEach((route) => {
      if (!route.children?.length) return

      const parentName = String(route.name || route.path || '未知路由')

      route.children.forEach((child) => {
        const childPath = child.path || ''

        // Skip valid absolute-style targets such as external links or iframes.
        if (this.isValidAbsolutePath(childPath)) return

        // Flag invalid child paths that still start with a slash.
        if (childPath.startsWith('/')) {
          this.logPathError(child, childPath, parentName, level)
        }
      })

      // Continue validating deeper child levels.
      this.validateMenuPaths(route.children, level + 1)
    })
  }

  /**
   * Check whether an absolute-style path is valid in menu data.
   */
  private isValidAbsolutePath(path: string): boolean {
    return (
      path.startsWith('http://') ||
      path.startsWith('https://') ||
      path.startsWith('/outside/iframe/')
    )
  }

  /**
   * Print a structured error for invalid child menu paths.
   */
  private logPathError(
    route: AppRouteRecord,
    path: string,
    parentName: string,
    level: number
  ): void {
    const routeName = String(route.name || path || '未知路由')
    const menuTitle = route.meta?.title || routeName
    const suggestedPath = path.split('/').pop() || path.slice(1)

    console.error(
      `[路由配置错误] 菜单 "${formatMenuTitle(menuTitle)}" (name: ${routeName}, path: ${path}) 配置错误\n` +
        `  位置: ${parentName} > ${routeName}\n` +
        `  问题: ${level + 1} 级菜单的 path 不能以 / 开头\n` +
        `  当前配置: path: '${path}'\n` +
        `  应改为: path: '${suggestedPath}'`
    )
  }

  /**
   * Build a full route path from a parent path and child segment.
   */
  private buildFullPath(path: string, parentPath: string): string {
    if (!path) return ''

    // External links stay untouched.
    if (path.startsWith('http://') || path.startsWith('https://')) {
      return path
    }

    // Already absolute paths can be returned as-is.
    if (path.startsWith('/')) {
      return path
    }

    // Join the parent path with the current segment.
    if (parentPath) {
      // Trim duplicate slashes before combining the segments.
      const cleanParent = parentPath.replace(/\/$/, '')
      const cleanChild = path.replace(/^\//, '')
      return `${cleanParent}/${cleanChild}`
    }

    // Root-level segments should still be normalized to absolute paths.
    return `/${path}`
  }
}
