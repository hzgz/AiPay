/**
 * 璺敱楠岃瘉鍣?
 *
 * 璐熻矗楠岃瘉璺敱閰嶇疆鐨勫悎娉曟€?
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
  // 鐢ㄤ簬璁板綍宸茬粡鎻愮ず杩囩殑璺敱锛岄伩鍏嶉噸澶嶆彁绀?
  private warnedRoutes = new Set<string>()

  /**
   * 楠岃瘉璺敱閰嶇疆
   */
  validate(routes: AppRouteRecord[]): ValidationResult {
    const errors: string[] = []
    const warnings: string[] = []

    // 妫€娴嬮噸澶嶈矾鐢?
    this.checkDuplicates(routes, errors, warnings)

    // 妫€娴嬬粍浠堕厤缃?
    this.checkComponents(routes, errors, warnings)

    // 妫€娴嬪祵濂楄彍鍗曠殑 /index/index 閰嶇疆
    this.checkNestedIndexComponent(routes)

    return {
      valid: errors.length === 0,
      errors,
      warnings
    }
  }

  /**
   * 妫€娴嬮噸澶嶈矾鐢?
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

        // 鍚嶇О閲嶅妫€娴?
        if (route.name) {
          const routeName = String(route.name)
          if (routeNameMap.has(routeName)) {
            warnings.push(`璺敱鍚嶇О閲嶅: "${routeName}" (${fullPath})`)
          } else {
            routeNameMap.set(routeName, fullPath)
          }
        }

        // 缁勪欢璺緞閲嶅妫€娴?
        if (route.component && typeof route.component === 'string') {
          const componentPath = route.component
          if (componentPath !== RoutesAlias.Layout) {
            const componentKey = `${parentPath}:${componentPath}`
            if (componentPathMap.has(componentKey)) {
              warnings.push(`缁勪欢璺緞閲嶅: "${componentPath}" (${fullPath})`)
            } else {
              componentPathMap.set(componentKey, fullPath)
            }
          }
        }

        // 閫掑綊澶勭悊瀛愯矾鐢?
        if (route.children?.length) {
          checkRoutes(route.children, fullPath)
        }
      })
    }

    checkRoutes(routes, parentPath)
  }

  /**
   * 妫€娴嬬粍浠堕厤缃?
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
      const routePath = route.path || '[鏈畾涔夎矾寰刔'
      const isIframe = route.meta?.isIframe

      // 濡傛灉閰嶇疆浜?component锛屽垯鏃犻渶鏍￠獙
      if (route.component) {
        // 閫掑綊妫€鏌ュ瓙璺敱
        if (route.children?.length) {
          const fullPath = this.resolvePath(parentPath, route.path || '')
          this.checkComponents(route.children, errors, warnings, fullPath)
        }
        return
      }

      // 涓€绾ц彍鍗曪細蹇呴』鎸囧畾 Layout锛岄櫎闈炴槸澶栭摼鎴?iframe
      if (parentPath === '' && !hasExternalLink && !isIframe) {
        errors.push(`涓€绾ц彍鍗?${routePath}) 缂哄皯 component锛屽繀椤绘寚鍚?${RoutesAlias.Layout}`)
        return
      }

      // 闈炰竴绾ц彍鍗曪細濡傛灉鏃笉鏄閾俱€乮frame锛屼篃娌℃湁瀛愯矾鐢憋紝鍒欏繀椤婚厤缃?component
      if (!hasExternalLink && !isIframe && !hasChildren) {
        errors.push(`璺敱(${routePath}) 缂哄皯 component 閰嶇疆`)
      }

      // 閫掑綊妫€鏌ュ瓙璺敱
      if (route.children?.length) {
        const fullPath = this.resolvePath(parentPath, route.path || '')
        this.checkComponents(route.children, errors, warnings, fullPath)
      }
    })
  }

  /**
   * 妫€娴嬪祵濂楄彍鍗曠殑 Layout 缁勪欢閰嶇疆
   * 鍙湁涓€绾ц彍鍗曟墠鑳戒娇鐢?Layout锛屼簩绾у強浠ヤ笅鑿滃崟涓嶈兘浣跨敤
   */
  private checkNestedIndexComponent(routes: AppRouteRecord[], level = 1): void {
    routes.forEach((route) => {
      // 妫€鏌ヤ簩绾у強浠ヤ笅鑿滃崟鏄惁閿欒浣跨敤浜?Layout
      if (level > 1 && route.component === RoutesAlias.Layout) {
        this.logLayoutError(route, level)
      }

      // 閫掑綊妫€鏌ュ瓙璺敱
      if (route.children?.length) {
        this.checkNestedIndexComponent(route.children, level + 1)
      }
    })
  }

  /**
   * 杈撳嚭 Layout 缁勪欢閰嶇疆閿欒鏃ュ織
   */
  private logLayoutError(route: AppRouteRecord, level: number): void {
    const routeName = String(route.name || route.path || '鏈煡璺敱')
    const routeKey = `${routeName}_${route.path}`

    // 閬垮厤閲嶅鎻愮ず
    if (this.warnedRoutes.has(routeKey)) return
    this.warnedRoutes.add(routeKey)

    const menuTitle = route.meta?.title || routeName
    const routePath = route.path || '/'

    console.error(
      `[璺敱閰嶇疆閿欒] 鑿滃崟 "${menuTitle}" (name: ${routeName}, path: ${routePath}) 閰嶇疆閿欒\n` +
        `  问题: ${level} 级菜单不能使用 ${RoutesAlias.Layout} 作为 component\n` +
        `  说明: 只有一级菜单才能使用 ${RoutesAlias.Layout}，二级及以下菜单应指向具体组件路径\n` +
        `  褰撳墠閰嶇疆: component: '${RoutesAlias.Layout}'\n` +
        `  应改为: component: '/your/component/path' 或留空 ''（如果是目录菜单）`
    )
  }

  /**
   * 璺緞瑙ｆ瀽
   */
  private resolvePath(parent: string, child: string): string {
    return [parent.replace(/\/$/, ''), child.replace(/^\//, '')].filter(Boolean).join('/')
  }
}

