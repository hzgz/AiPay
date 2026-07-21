/**
 * 鑿滃崟澶勭悊鍣?
 *
 * 璐熻矗鑿滃崟鏁版嵁鐨勮幏鍙栥€佽繃婊ゅ拰澶勭悊
 *
 * @module router/core/MenuProcessor
 * @author AiPay
 */

import type { AppRouteRecord } from '@/types/router'
import { useUserStore } from '@/store/modules/user'
import { useAppMode } from '@/hooks/core/useAppMode'
import { fetchGetMenuList } from '@/api/system-manage'
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
   * 鑾峰彇鑿滃崟鏁版嵁
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

    // 鍦ㄨ鑼冨寲璺緞涔嬪墠锛岄獙璇佸師濮嬭矾寰勯厤缃?    this.validateMenuPaths(menuList)

    // 瑙勮寖鍖栬矾寰勶紙灏嗙浉瀵硅矾寰勮浆鎹负瀹屾暣璺緞锛?
    return this.normalizeMenuPaths(menuList)
  }

  /**
   * 澶勭悊鍓嶇鎺у埗妯″紡鐨勮彍鍗?
   */
  private async processFrontendMenu(): Promise<AppRouteRecord[]> {
    const userStore = useUserStore()
    const roles = userStore.info?.roles

    let menuList = [...asyncRoutes]

    // 鏍规嵁瑙掕壊杩囨护鑿滃崟
    if (roles && roles.length > 0) {
      menuList = this.filterMenuByRoles(menuList, roles)
    }

    return this.filterEmptyMenus(menuList)
  }

  /**
   * 澶勭悊鍚庣鎺у埗妯″紡鐨勮彍鍗?
   */
  private async processBackendMenu(): Promise<AppRouteRecord[]> {
    const list = await fetchGetMenuList()
    return this.filterEmptyMenus(list)
  }

  /**
   * 鏍规嵁瑙掕壊杩囨护鑿滃崟
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
   * 閫掑綊杩囨护绌鸿彍鍗曢」
   */
  private filterEmptyMenus(menuList: AppRouteRecord[]): AppRouteRecord[] {
    return menuList
      .map((item) => {
        // 濡傛灉鏈夊瓙鑿滃崟锛屽厛閫掑綊杩囨护瀛愯彍鍗?
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
        // 濡傛灉瀹氫箟浜?children 灞炴€э紙鍗充娇鏄┖鏁扮粍锛夛紝璇存槑杩欐槸涓€涓洰褰曡彍鍗曪紝搴旇淇濈暀
        if ('children' in item) {
          return true
        }

        // 濡傛灉鏈夊閾炬垨 iframe锛屼繚鐣?
        if (item.meta?.isIframe === true || item.meta?.link) {
          return true
        }

        // 濡傛灉鏈夋湁鏁堢殑 component锛屼繚鐣?
        if (item.component && item.component !== '' && item.component !== RoutesAlias.Layout) {
          return true
        }

        // 鍏朵粬鎯呭喌杩囨护鎺?
        return false
      })
  }

  /**
   * 楠岃瘉鑿滃崟鍒楄〃鏄惁鏈夋晥
   */
  validateMenuList(menuList: AppRouteRecord[]): boolean {
    return Array.isArray(menuList) && menuList.length > 0
  }

  /**
   * 姝ｅ紡鐜鑿滃崟瑁佸壀
   * 绉婚櫎婕旂ず銆佺ず渚嬨€佸彉鏇存棩蹇楃瓑闈炰笟鍔″叆鍙ｏ紝閬垮厤璇毚闇插埌鐢熶骇鍚庡彴銆?   */
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
   * 瑙勮寖鍖栬彍鍗曡矾寰?   * 灏嗙浉瀵硅矾寰勮浆鎹负瀹屾暣璺緞锛岀‘淇濊彍鍗曡烦杞纭?
   */
  private normalizeMenuPaths(menuList: AppRouteRecord[], parentPath = ''): AppRouteRecord[] {
    return menuList.map((item) => {
      // 鏋勫缓瀹屾暣璺緞
      const fullPath = this.buildFullPath(item.path || '', parentPath)

      // 閫掑綊澶勭悊瀛愯彍鍗?
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
   * 涓虹洰褰曞瀷鑿滃崟鎺ㄥ榛樿璺宠浆鍦板潃
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
   * 鍒ゆ柇瀛愯矾鐢辨槸鍚﹀彲浠ヤ綔涓洪粯璁よ惤鐐?
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
   * 楠岃瘉鑿滃崟璺緞閰嶇疆
   * 妫€娴嬮潪涓€绾ц彍鍗曟槸鍚﹂敊璇娇鐢ㄤ簡 / 寮€澶寸殑璺緞
   */
  /**
   * 楠岃瘉鑿滃崟璺緞閰嶇疆
   * 妫€娴嬮潪涓€绾ц彍鍗曟槸鍚﹂敊璇娇鐢ㄤ簡 / 寮€澶寸殑璺緞
   */
  private validateMenuPaths(menuList: AppRouteRecord[], level = 1): void {
    menuList.forEach((route) => {
      if (!route.children?.length) return

      const parentName = String(route.name || route.path || '鏈煡璺敱')

      route.children.forEach((child) => {
        const childPath = child.path || ''

        // 璺宠繃鍚堟硶鐨勭粷瀵硅矾寰勶細澶栭儴閾炬帴鍜?iframe 璺敱
        if (this.isValidAbsolutePath(childPath)) return

        // 妫€娴嬮潪娉曠殑缁濆璺緞
        if (childPath.startsWith('/')) {
          this.logPathError(child, childPath, parentName, level)
        }
      })

      // 閫掑綊妫€鏌ユ洿娣卞眰绾х殑瀛愯矾鐢?
      this.validateMenuPaths(route.children, level + 1)
    })
  }

  /**
   * 鍒ゆ柇鏄惁涓哄悎娉曠殑缁濆璺緞
   */
  private isValidAbsolutePath(path: string): boolean {
    return (
      path.startsWith('http://') ||
      path.startsWith('https://') ||
      path.startsWith('/outside/iframe/')
    )
  }

  /**
   * 杈撳嚭璺緞閰嶇疆閿欒鏃ュ織
   */
  private logPathError(
    route: AppRouteRecord,
    path: string,
    parentName: string,
    level: number
  ): void {
    const routeName = String(route.name || path || '鏈煡璺敱')
    const menuTitle = route.meta?.title || routeName
    const suggestedPath = path.split('/').pop() || path.slice(1)

    console.error(
      `[璺敱閰嶇疆閿欒] 鑿滃崟 "${formatMenuTitle(menuTitle)}" (name: ${routeName}, path: ${path}) 閰嶇疆閿欒\n` +
        `  浣嶇疆: ${parentName} > ${routeName}\n` +
        `  闂: ${level + 1}绾ц彍鍗曠殑 path 涓嶈兘浠?/ 寮€澶碶n` +
        `  褰撳墠閰嶇疆: path: '${path}'\n` +
        `  搴旇鏀逛负: path: '${suggestedPath}'`
    )
  }

  /**
   * 鏋勫缓瀹屾暣璺緞
   */
  private buildFullPath(path: string, parentPath: string): string {
    if (!path) return ''

    // 澶栭儴閾炬帴鐩存帴杩斿洖
    if (path.startsWith('http://') || path.startsWith('https://')) {
      return path
    }

    // 濡傛灉宸茬粡鏄粷瀵硅矾寰勶紝鐩存帴杩斿洖
    if (path.startsWith('/')) {
      return path
    }

    // 鎷兼帴鐖惰矾寰勫拰褰撳墠璺緞
    if (parentPath) {
      // 绉婚櫎鐖惰矾寰勬湯灏剧殑鏂滄潬锛岀Щ闄ゅ瓙璺緞寮€澶寸殑鏂滄潬锛岀劧鍚庢嫾鎺?
      const cleanParent = parentPath.replace(/\/$/, '')
      const cleanChild = path.replace(/^\//, '')
      return `${cleanParent}/${cleanChild}`
    }

    // 娌℃湁鐖惰矾寰勶紝娣诲姞鍓嶅鏂滄潬
    return `/${path}`
  }
}

