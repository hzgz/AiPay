/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

/**
 * Route permission validation helpers.
 *
 * Provides permission checks and path matching for menu-driven routing.
 *
 * @module router/core/RoutePermissionValidator
 * @author AiPay
 */

import type { AppRouteRecord } from '@/types/router'

/**
 * Permission utilities for route access control.
 */
export class RoutePermissionValidator {
  /**
   * Check whether the target path exists inside the current menu permission set.
   */
  static hasPermission(targetPath: string, menuList: AppRouteRecord[]): boolean {
    // The root path is always allowed.
    if (targetPath === '/') {
      return true
    }

    return this.matchRoute(targetPath, menuList)
  }

  /**
   * Build a flattened set of menu paths for quick lookups.
   */
  static buildMenuPathSet(
    menuList: AppRouteRecord[],
    pathSet: Set<string> = new Set()
  ): Set<string> {
    if (!Array.isArray(menuList) || menuList.length === 0) {
      return pathSet
    }

    for (const menuItem of menuList) {
      if (!menuItem.path) {
        continue
      }

      // Normalize paths before storing them in the lookup set.
      const menuPath = menuItem.path.startsWith('/') ? menuItem.path : `/${menuItem.path}`
      pathSet.add(menuPath)

      // Include all nested child paths.
      if (menuItem.children?.length) {
        this.buildMenuPathSet(menuItem.children, pathSet)
      }
    }

    return pathSet
  }

  /**
   * Check whether the target path matches any known path prefix.
   */
  static checkPathPrefix(targetPath: string, pathSet: Set<string>): boolean {
    // Support nested URLs such as /user/123 matching the /user menu entry.
    for (const menuPath of pathSet) {
      if (targetPath.startsWith(`${menuPath}/`)) {
        return true
      }
    }
    return false
  }

  /**
   * Recursively match a target path against the menu route tree.
   */
  static matchRoute(targetPath: string, routes: AppRouteRecord[]): boolean {
    if (!Array.isArray(routes) || routes.length === 0) {
      return false
    }

    for (const route of routes) {
      if (!route.path) {
        continue
      }

      const routePath = route.path.startsWith('/') ? route.path : `/${route.path}`

      if (
        routePath === targetPath ||
        this.isDynamicRouteMatch(targetPath, routePath) ||
        targetPath.startsWith(`${routePath}/`)
      ) {
        return true
      }

      if (route.children?.length && this.matchRoute(targetPath, route.children)) {
        return true
      }
    }

    return false
  }

  /**
   * Check whether the target path matches a dynamic route pattern.
   */
  static isDynamicRouteMatch(targetPath: string, routePath: string): boolean {
    if (!routePath.includes(':')) {
      return false
    }

    const pattern = routePath
      .replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
      .replace(/:([^/]+)/g, '[^/]+')
      .replace(/\\\*/g, '.*')

    return new RegExp(`^${pattern}$`).test(targetPath)
  }

  /**
   * Validate a target path and fall back to the home path when access is denied.
   */
  static validatePath(
    targetPath: string,
    menuList: AppRouteRecord[],
    homePath: string = '/'
  ): { path: string; hasPermission: boolean } {
    const hasPermission = this.hasPermission(targetPath, menuList)

    if (hasPermission) {
      return { path: targetPath, hasPermission: true }
    }

    return { path: homePath, hasPermission: false }
  }
}
