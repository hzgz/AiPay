/**
 * 璺敱鏉冮檺楠岃瘉妯″潡
 *
 * 鎻愪緵璺敱鏉冮檺楠岃瘉鍜岃矾寰勬鏌ュ姛鑳?
 *
 * ## 涓昏鍔熻兘
 *
 * - 楠岃瘉璺緞鏄惁鍦ㄧ敤鎴疯彍鍗曟潈闄愪腑
 * - 鏋勫缓鑿滃崟璺緞闆嗗悎锛堟墎骞冲寲澶勭悊锛?
 * - 鏀寔鍔ㄦ€佽矾鐢卞弬鏁板尮閰?
 * - 璺緞鍓嶇紑鍖归厤
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 璺敱瀹堝崼涓獙璇佺敤鎴锋潈闄?
 * - 鍔ㄦ€佽矾鐢辨敞鍐屽悗鐨勬潈闄愭鏌?
 * - 闃叉鐢ㄦ埛璁块棶鏃犳潈闄愮殑椤甸潰
 *
 * @module router/core/RoutePermissionValidator
 * @author AiPay
 */

import type { AppRouteRecord } from '@/types/router'

/**
 * 璺敱鏉冮檺楠岃瘉鍣?
 */
export class RoutePermissionValidator {
  /**
   * 楠岃瘉璺緞鏄惁鍦ㄧ敤鎴疯彍鍗曟潈闄愪腑
   * @param targetPath 鐩爣璺緞
   * @param menuList 鑿滃崟鍒楄〃
   * @returns 鏄惁鏈夋潈闄愯闂?
   */
  static hasPermission(targetPath: string, menuList: AppRouteRecord[]): boolean {
    // 鏍硅矾寰勫缁堝厑璁歌闂?
    if (targetPath === '/') {
      return true
    }

    return this.matchRoute(targetPath, menuList)
  }

  /**
   * 鏋勫缓鑿滃崟璺緞闆嗗悎锛堟墎骞冲寲澶勭悊锛?
   * @param menuList 鑿滃崟鍒楄〃
   * @param pathSet 璺緞闆嗗悎
   * @returns 璺緞闆嗗悎
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

      // 鏍囧噯鍖栬矾寰勫苟娣诲姞鍒伴泦鍚?
      const menuPath = menuItem.path.startsWith('/') ? menuItem.path : `/${menuItem.path}`
      pathSet.add(menuPath)

      // 閫掑綊澶勭悊瀛愯彍鍗?
      if (menuItem.children?.length) {
        this.buildMenuPathSet(menuItem.children, pathSet)
      }
    }

    return pathSet
  }

  /**
   * 妫€鏌ョ洰鏍囪矾寰勬槸鍚﹀尮閰嶉泦鍚堜腑鐨勬煇涓矾寰勫墠缂€
   * 鐢ㄤ簬鏀寔鍔ㄦ€佽矾鐢卞弬鏁板尮閰嶏紝濡?/user/123 鍖归厤 /user
   * @param targetPath 鐩爣璺緞
   * @param pathSet 璺緞闆嗗悎
   * @returns 鏄惁鍖归厤
   */
  static checkPathPrefix(targetPath: string, pathSet: Set<string>): boolean {
    // 閬嶅巻璺緞闆嗗悎锛屾鏌ユ槸鍚︽湁鍓嶇紑鍖归厤
    for (const menuPath of pathSet) {
      if (targetPath.startsWith(`${menuPath}/`)) {
        return true
      }
    }
    return false
  }

  /**
   * 閫掑綊鍖归厤璺敱閰嶇疆锛屾敮鎸侀殣钘忚矾鐢卞拰鍔ㄦ€佸弬鏁拌矾鐢?
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
   * 妫€鏌ョ洰鏍囪矾寰勬槸鍚﹀尮閰嶅姩鎬佸弬鏁拌矾鐢憋紝濡?/demo/123 鍖归厤 /demo/:id
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
   * 楠岃瘉骞惰繑鍥炴湁鏁堢殑璺緞
   * 濡傛灉鐩爣璺緞鏃犳潈闄愶紝杩斿洖棣栭〉璺緞
   * @param targetPath 鐩爣璺緞
   * @param menuList 鑿滃崟鍒楄〃
   * @param homePath 棣栭〉璺緞
   * @returns 楠岃瘉鍚庣殑璺緞
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

