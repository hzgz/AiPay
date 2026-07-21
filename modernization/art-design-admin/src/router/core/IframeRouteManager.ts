/**
 * Iframe 璺敱绠＄悊鍣?
 *
 * 璐熻矗绠＄悊 iframe 绫诲瀷鐨勮矾鐢?
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
   * 娣诲姞 iframe 璺敱
   */
  add(route: AppRouteRecord): void {
    if (!this.iframeRoutes.find((r) => r.path === route.path)) {
      this.iframeRoutes.push(route)
    }
  }

  /**
   * 鑾峰彇鎵€鏈?iframe 璺敱
   */
  getAll(): AppRouteRecord[] {
    return this.iframeRoutes
  }

  /**
   * 鏍规嵁璺緞鏌ユ壘 iframe 璺敱
   */
  findByPath(path: string): AppRouteRecord | undefined {
    return this.iframeRoutes.find((route) => route.path === path)
  }

  /**
   * 娓呯┖鎵€鏈?iframe 璺敱
   */
  clear(): void {
    this.iframeRoutes = []
  }

  /**
   * 淇濆瓨鍒?sessionStorage
   */
  save(): void {
    if (this.iframeRoutes.length > 0) {
      sessionStorage.setItem('iframeRoutes', JSON.stringify(this.iframeRoutes))
    }
  }

  /**
   * 浠?sessionStorage 鍔犺浇
   */
  load(): void {
    try {
      const data = sessionStorage.getItem('iframeRoutes')
      if (data) {
        this.iframeRoutes = JSON.parse(data)
      }
    } catch (error) {
      console.error('[IframeRouteManager] 鍔犺浇 iframe 璺敱澶辫触:', error)
      this.iframeRoutes = []
    }
  }
}

