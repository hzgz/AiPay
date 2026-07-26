/**
 * Route validation service.
 *
 * Ensures that dynamic route definitions are structurally valid before registration.
 *
 * @module router/core/RouteValidator
 * @author AiPay
 */

import type { AppRouteRecord } from '@/types/router'
import { RoutesAlias } from '../routesAlias'

export interface ValidationResult {
  valid: boolean
  errors: string[]
  warnings: string[]
}

export class RouteValidator {
  // Track routes that have already been warned about to avoid duplicate logs.
  private warnedRoutes = new Set<string>()

  /**
   * Validate route configuration input.
   */
  validate(routes: AppRouteRecord[]): ValidationResult {
    const errors: string[] = []
    const warnings: string[] = []

    // Check for duplicate route names and component mappings.
    this.checkDuplicates(routes, errors, warnings)

    // Ensure every route has a valid component strategy.
    this.checkComponents(routes, errors, warnings)

    // Guard against nested routes that incorrectly reuse the layout alias.
    this.checkNestedIndexComponent(routes)

    return {
      valid: errors.length === 0,
      errors,
      warnings
    }
  }

  /**
   * Detect duplicate route names and component paths.
   */
  private checkDuplicates(
    routes: AppRouteRecord[],
    errors: string[],
    warnings: string[],
    parentPath = ''
  ): void {
    const routeNameMap = new Map<string, string>()
    const componentPathMap = new Map<string, string>()

    const checkRoutes = (routes: AppRouteRecord[], parentPath = '') => {
      routes.forEach((route) => {
        const currentPath = route.path || ''
        const fullPath = this.resolvePath(parentPath, currentPath)

        // Detect duplicate route names.
        if (route.name) {
          const routeName = String(route.name)
          if (routeNameMap.has(routeName)) {
            warnings.push(`路由名称重复: "${routeName}" (${fullPath})`)
          } else {
            routeNameMap.set(routeName, fullPath)
          }
        }

        // Detect duplicate component mappings under the same parent path.
        if (route.component && typeof route.component === 'string') {
          const componentPath = route.component
          if (componentPath !== RoutesAlias.Layout) {
            const componentKey = `${parentPath}:${componentPath}`
            if (componentPathMap.has(componentKey)) {
              warnings.push(`组件路径重复: "${componentPath}" (${fullPath})`)
            } else {
              componentPathMap.set(componentKey, fullPath)
            }
          }
        }

        // Continue walking nested route declarations.
        if (route.children?.length) {
          checkRoutes(route.children, fullPath)
        }
      })
    }

    checkRoutes(routes, parentPath)
  }

  /**
   * Validate component usage for each route node.
   */
  private checkComponents(
    routes: AppRouteRecord[],
    errors: string[],
    warnings: string[],
    parentPath = ''
  ): void {
    routes.forEach((route) => {
      const hasExternalLink = !!route.meta?.link?.trim()
      const hasChildren = Array.isArray(route.children) && route.children.length > 0
      const routePath = route.path || '[未定义路径]'
      const isIframe = route.meta?.isIframe

      // Routes with a component value can skip the missing-component checks.
      if (route.component) {
        // Validate nested routes with the resolved full path.
        if (route.children?.length) {
          const fullPath = this.resolvePath(parentPath, route.path || '')
          this.checkComponents(route.children, errors, warnings, fullPath)
        }
        return
      }

      // Top-level menus must provide the layout alias unless they are links or iframes.
      if (parentPath === '' && !hasExternalLink && !isIframe) {
        errors.push(`一级菜单(${routePath}) 缺少 component，必须指定 ${RoutesAlias.Layout}`)
        return
      }

      // Leaf routes still need a concrete component when they are not links or iframes.
      if (!hasExternalLink && !isIframe && !hasChildren) {
        errors.push(`路由(${routePath}) 缺少 component 配置`)
      }

      // Continue validating nested children.
      if (route.children?.length) {
        const fullPath = this.resolvePath(parentPath, route.path || '')
        this.checkComponents(route.children, errors, warnings, fullPath)
      }
    })
  }

  /**
   * Validate nested layout usage.
   *
   * Only first-level routes should use the shared layout alias.
   */
  private checkNestedIndexComponent(routes: AppRouteRecord[], level = 1): void {
    routes.forEach((route) => {
      // Nested routes should not directly point back to the layout container.
      if (level > 1 && route.component === RoutesAlias.Layout) {
        this.logLayoutError(route, level)
      }

      // Continue validating deeper child nodes.
      if (route.children?.length) {
        this.checkNestedIndexComponent(route.children, level + 1)
      }
    })
  }

  /**
   * Print a detailed error for invalid nested layout usage.
   */
  private logLayoutError(route: AppRouteRecord, level: number): void {
    const routeName = String(route.name || route.path || '未知路由')
    const routeKey = `${routeName}_${route.path}`

    // Avoid repeating the same warning across repeated validation passes.
    if (this.warnedRoutes.has(routeKey)) return
    this.warnedRoutes.add(routeKey)

    const menuTitle = route.meta?.title || routeName
    const routePath = route.path || '/'

    console.error(
      `[路由配置错误] 菜单 "${menuTitle}" (name: ${routeName}, path: ${routePath}) 配置错误\n` +
        `  问题: ${level} 级菜单不能使用 ${RoutesAlias.Layout} 作为 component\n` +
        `  说明: 只有一级菜单才能使用 ${RoutesAlias.Layout}，二级及以下菜单应指向具体组件路径\n` +
        `  当前配置: component: '${RoutesAlias.Layout}'\n` +
        `  应改为: component: '/your/component/path' 或留空 ''（如果是目录菜单）`
    )
  }

  /**
   * Resolve a child path against its parent route path.
   */
  private resolvePath(parent: string, child: string): string {
    return [parent.replace(/\/$/, ''), child.replace(/^\//, '')].filter(Boolean).join('/')
  }
}
