import type { App } from 'vue'
import { createRouter, createWebHashHistory } from 'vue-router'
import { staticRoutes } from './routes/staticRoutes'
import { configureNProgress } from '@/utils/router'
import { setupBeforeEachGuard } from './guards/beforeEach'
import { setupAfterEachGuard } from './guards/afterEach'

const CHUNK_RELOAD_MARK_KEY = '__art_chunk_reload_once__'

// 创建路由实例
export const router = createRouter({
  history: createWebHashHistory(),
  routes: staticRoutes // 静态路由
})

function isDynamicModuleLoadError(error: unknown) {
  const message = error instanceof Error ? error.message : String(error || '')
  return /Failed to fetch dynamically imported module|Importing a module script failed|Failed to import/i.test(
    message
  )
}

function setupChunkErrorRecovery() {
  router.onError((error, to) => {
    if (!isDynamicModuleLoadError(error)) {
      return
    }

    const targetPath = typeof to === 'string' ? to : to?.fullPath || '/'
    const reloadMark = sessionStorage.getItem(CHUNK_RELOAD_MARK_KEY)

    if (reloadMark === targetPath) {
      sessionStorage.removeItem(CHUNK_RELOAD_MARK_KEY)
      return
    }

    sessionStorage.setItem(CHUNK_RELOAD_MARK_KEY, targetPath)
    window.location.replace(`${window.location.pathname}${window.location.search}#${targetPath}`)
  })

  router.afterEach((to) => {
    const reloadMark = sessionStorage.getItem(CHUNK_RELOAD_MARK_KEY)
    if (reloadMark === to.fullPath) {
      sessionStorage.removeItem(CHUNK_RELOAD_MARK_KEY)
    }
  })
}

// 初始化路由
export function initRouter(app: App<Element>): void {
  configureNProgress() // 顶部进度条
  setupBeforeEachGuard(router) // 路由前置守卫
  setupAfterEachGuard(router) // 路由后置守卫
  setupChunkErrorRecovery()
  app.use(router)
}

// 主页路径，默认使用菜单第一个有效路径，配置后使用此路径
export const HOME_PAGE_PATH = ''
