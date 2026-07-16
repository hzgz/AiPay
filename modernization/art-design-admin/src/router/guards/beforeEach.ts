import type { NavigationGuardNext, RouteLocationNormalized, Router } from 'vue-router'
import { nextTick } from 'vue'
import NProgress from 'nprogress'
import { useCommon } from '@/hooks/core/useCommon'
import { fetchGetUserInfo } from '@/api/auth'
import { useMenuStore } from '@/store/modules/menu'
import { useSettingStore } from '@/store/modules/setting'
import { useUserStore } from '@/store/modules/user'
import { useWorktabStore } from '@/store/modules/worktab'
import { ApiStatus } from '@/utils/http/status'
import { isHttpError } from '@/utils/http/error'
import { setWorktab } from '@/utils/navigation'
import { setPageTitle } from '@/utils/router'
import { loadingService } from '@/utils/ui'
import { IframeRouteManager, MenuProcessor, RoutePermissionValidator, RouteRegistry } from '../core'
import { staticRoutes } from '../routes/staticRoutes'
import { RoutesAlias } from '../routesAlias'

const PAYMENT_PLUGIN_WORKSPACE_PATH = '/payments/plugins'
const LEGACY_PAYMENT_WORKSPACE_PATHS = ['/payments/catalog', '/payments/channels']

let routeRegistry: RouteRegistry | null = null
const menuProcessor = new MenuProcessor()
let pendingLoading = false
let routeInitFailed = false
let routeInitInProgress = false

export function getPendingLoading(): boolean {
  return pendingLoading
}

export function resetPendingLoading(): void {
  pendingLoading = false
}

export function getRouteInitFailed(): boolean {
  return routeInitFailed
}

export function resetRouteInitState(): void {
  routeInitFailed = false
  routeInitInProgress = false
}

export function setupBeforeEachGuard(router: Router): void {
  routeRegistry = new RouteRegistry(router)

  router.beforeEach(async (to, from, next) => {
    try {
      await handleRouteGuard(to, from, next, router)
    } catch (error) {
      console.error('[RouteGuard] 路由前置守卫处理失败', error)
      closeLoading()
      next({ name: 'Exception500' })
    }
  })
}

function closeLoading(): void {
  if (!pendingLoading) {
    return
  }

  nextTick(() => {
    loadingService.hideLoading()
    pendingLoading = false
  })
}

async function handleRouteGuard(
  to: RouteLocationNormalized,
  _from: RouteLocationNormalized,
  next: NavigationGuardNext,
  router: Router
): Promise<void> {
  const settingStore = useSettingStore()
  const userStore = useUserStore()
  const legacyPaymentWorkspaceRedirect = resolveLegacyPaymentWorkspaceRedirect(to)

  if (to.path === RoutesAlias.Login) {
    setPageTitle(to)
    next()
    return
  }

  if (settingStore.showNprogress) {
    NProgress.start()
  }

  if (legacyPaymentWorkspaceRedirect) {
    next(legacyPaymentWorkspaceRedirect)
    return
  }

  if (!handleLoginStatus(to, userStore, next)) {
    return
  }

  if (to.meta?.publicLanding) {
    setPageTitle(to)
    next()
    return
  }

  if (isMerchantRoute(to.path)) {
    setPageTitle(to)
    next()
    return
  }

  if (routeInitFailed) {
    if (to.matched.length > 0) {
      next()
    } else {
      next({ name: 'Exception500', replace: true })
    }
    return
  }

  if (!routeRegistry?.isRegistered() && userStore.isLogin) {
    if (routeInitInProgress) {
      next(false)
      return
    }

    await handleDynamicRoutes(to, next, router)
    return
  }

  if (handleRootPathRedirect(to, next)) {
    return
  }

  if (to.matched.length > 0) {
    setWorktab(to)
    setPageTitle(to)
    next()
    return
  }

  next({ name: 'Exception404' })
}

function handleLoginStatus(
  to: RouteLocationNormalized,
  userStore: ReturnType<typeof useUserStore>,
  next: NavigationGuardNext
): boolean {
  if (isMerchantRoute(to.path)) {
    return true
  }

  if (userStore.isLogin || to.path === RoutesAlias.Login || isStaticRoute(to.path)) {
    return true
  }

  userStore.logOut()
  next({
    name: 'Login',
    query: { redirect: to.fullPath }
  })
  return false
}

function isStaticRoute(path: string): boolean {
  const checkRoute = (routes: any[], targetPath: string): boolean => {
    return routes.some((route) => {
      if (route.name === 'Exception404') {
        return false
      }

      const routePath = route.path
      const pattern = routePath.replace(/:[^/]+/g, '[^/]+').replace(/\*/g, '.*')
      const regex = new RegExp(`^${pattern}$`)

      if (regex.test(targetPath)) {
        return true
      }

      if (route.children && route.children.length > 0) {
        return checkRoute(route.children, targetPath)
      }

      return false
    })
  }

  return checkRoute(staticRoutes, path)
}

function isMerchantRoute(path: string): boolean {
  return path === '/merchant' || path.startsWith('/merchant/')
}

function resolveLegacyPaymentWorkspaceRedirect(to: RouteLocationNormalized) {
  const isLegacyWorkspacePath = LEGACY_PAYMENT_WORKSPACE_PATHS.includes(to.path)
  const hasLegacyWorkspaceQuery = String(to.query.workspace || '').trim() !== ''
  const needsRedirect =
    isLegacyWorkspacePath ||
    (to.path === PAYMENT_PLUGIN_WORKSPACE_PATH && hasLegacyWorkspaceQuery)

  if (!needsRedirect) {
    return null
  }

  const normalizedQuery = { ...to.query }
  delete normalizedQuery.workspace

  return {
    path: PAYMENT_PLUGIN_WORKSPACE_PATH,
    query: Object.keys(normalizedQuery).length > 0 ? normalizedQuery : undefined,
    replace: true
  }
}

async function handleDynamicRoutes(
  to: RouteLocationNormalized,
  next: NavigationGuardNext,
  router: Router
): Promise<void> {
  routeInitInProgress = true
  pendingLoading = true
  loadingService.showLoading()

  try {
    await fetchUserInfo()

    const menuList = await menuProcessor.getMenuList()
    if (!menuProcessor.validateMenuList(menuList)) {
      throw new Error('获取菜单列表失败，请重新登录')
    }

    routeRegistry?.register(menuList)

    const menuStore = useMenuStore()
    menuStore.setMenuList(menuList)
    menuStore.addRemoveRouteFns(routeRegistry?.getRemoveRouteFns() || [])

    IframeRouteManager.getInstance().save()
    useWorktabStore().validateWorktabs(router)

    if (isStaticRoute(to.path)) {
      routeInitInProgress = false
      next({
        path: to.path,
        query: to.query,
        hash: to.hash,
        replace: true
      })
      return
    }

    const { homePath } = useCommon()
    const { path: validatedPath, hasPermission } = RoutePermissionValidator.validatePath(
      to.path,
      menuList,
      homePath.value || '/'
    )

    routeInitInProgress = false

    if (!hasPermission) {
      closeLoading()
      console.warn(`[RouteGuard] 用户无权访问路径 ${to.path}，已跳转到首页`)
      next({
        path: validatedPath,
        replace: true
      })
      return
    }

    next({
      path: to.path,
      query: to.query,
      hash: to.hash,
      replace: true
    })
  } catch (error) {
    console.error('[RouteGuard] 动态路由初始化失败', error)
    closeLoading()

    if (isUnauthorizedError(error)) {
      routeInitInProgress = false
      next(false)
      return
    }

    routeInitFailed = true
    routeInitInProgress = false

    if (isHttpError(error)) {
      console.error(`[RouteGuard] 错误码 ${error.code}，消息: ${error.message}`)
    }

    next({ name: 'Exception500', replace: true })
  }
}

async function fetchUserInfo(): Promise<void> {
  const userStore = useUserStore()
  const data = await fetchGetUserInfo()
  userStore.setUserInfo(data)
  userStore.checkAndClearWorktabs()
}

export function resetRouterState(delay: number): void {
  setTimeout(() => {
    routeRegistry?.unregister()
    IframeRouteManager.getInstance().clear()

    const menuStore = useMenuStore()
    menuStore.removeAllDynamicRoutes()
    menuStore.setMenuList([])

    resetRouteInitState()
  }, delay)
}

function handleRootPathRedirect(to: RouteLocationNormalized, next: NavigationGuardNext): boolean {
  if (to.path !== '/' || to.meta?.publicLanding) {
    return false
  }

  const { homePath } = useCommon()
  if (homePath.value && homePath.value !== '/') {
    next({ path: homePath.value, replace: true })
    return true
  }

  return false
}

function isUnauthorizedError(error: unknown): boolean {
  return isHttpError(error) && error.code === ApiStatus.unauthorized
}
