/**
 * Iframe route registry.
 *
 * Stores iframe-backed route records for lookup and persistence.
 *
 * @module router/core/IframeRouteManager
 * @author AiPay
 */

import type { AppRouteRecord } from '@/types/router'

export class IframeRouteManager {
  private static instance: IframeRouteManager
  private iframeRoutes: AppRouteRecord[] = []

  private constructor() {}

  static getInstance(): IframeRouteManager {
    if (!IframeRouteManager.instance) {
      IframeRouteManager.instance = new IframeRouteManager()
    }
    return IframeRouteManager.instance
  }

  /**
   * Add an iframe route when it has not been recorded yet.
   */
  add(route: AppRouteRecord): void {
    if (!this.iframeRoutes.find((r) => r.path === route.path)) {
      this.iframeRoutes.push(route)
    }
  }

  /**
   * Get all tracked iframe routes.
   */
  getAll(): AppRouteRecord[] {
    return this.iframeRoutes
  }

  /**
   * Find an iframe route by its path.
   */
  findByPath(path: string): AppRouteRecord | undefined {
    return this.iframeRoutes.find((route) => route.path === path)
  }

  /**
   * Clear all tracked iframe routes.
   */
  clear(): void {
    this.iframeRoutes = []
  }

  /**
   * Persist iframe routes to session storage.
   */
  save(): void {
    if (this.iframeRoutes.length > 0) {
      sessionStorage.setItem('iframeRoutes', JSON.stringify(this.iframeRoutes))
    }
  }

  /**
   * Restore iframe routes from session storage.
   */
  load(): void {
    try {
      const data = sessionStorage.getItem('iframeRoutes')
      if (data) {
        this.iframeRoutes = JSON.parse(data)
      }
    } catch (error) {
      console.error('[IframeRouteManager] 加载 iframe 路由失败:', error)
      this.iframeRoutes = []
    }
  }
}
