/**
 * 璺敱娉ㄥ唽鏍稿績绫?
 *
 * 璐熻矗鍔ㄦ€佽矾鐢辩殑娉ㄥ唽銆侀獙璇佸拰绠＄悊
 *
 * @module router/core/RouteRegistry
 * @author AiPay
 */

import type { Router, RouteRecordRaw } from 'vue-router'
import type { AppRouteRecord } from '@/types/router'
import { ComponentLoader } from './ComponentLoader'
import { RouteValidator } from './RouteValidator'
import { RouteTransformer } from './RouteTransformer'

export class RouteRegistry {
  private router: Router
  private componentLoader: ComponentLoader
  private validator: RouteValidator
  private transformer: RouteTransformer
  private removeRouteFns: (() => void)[] = []
  private registered = false

  constructor(router: Router) {
    this.router = router
    this.componentLoader = new ComponentLoader()
    this.validator = new RouteValidator()
    this.transformer = new RouteTransformer(this.componentLoader)
  }

  /**
   * 娉ㄥ唽鍔ㄦ€佽矾鐢?
   */
  register(menuList: AppRouteRecord[]): void {
    if (this.registered) {
      console.warn('[RouteRegistry] 璺敱宸叉敞鍐岋紝璺宠繃閲嶅娉ㄥ唽')
      return
    }

    // 楠岃瘉璺敱閰嶇疆
    const validationResult = this.validator.validate(menuList)
    if (!validationResult.valid) {
      throw new Error(`璺敱閰嶇疆楠岃瘉澶辫触: ${validationResult.errors.join(', ')}`)
    }

    // 杞崲骞舵敞鍐岃矾鐢?
    const removeRouteFns: (() => void)[] = []

    menuList.forEach((route) => {
      if (route.name && !this.router.hasRoute(route.name)) {
        const routeConfig = this.transformer.transform(route)
        const removeRouteFn = this.router.addRoute(routeConfig as RouteRecordRaw)
        removeRouteFns.push(removeRouteFn)
      }
    })

    this.removeRouteFns = removeRouteFns
    this.registered = true
  }

  /**
   * 绉婚櫎鎵€鏈夊姩鎬佽矾鐢?
   */
  unregister(): void {
    this.removeRouteFns.forEach((fn) => fn())
    this.removeRouteFns = []
    this.registered = false
  }

  /**
   * 妫€鏌ユ槸鍚﹀凡娉ㄥ唽
   */
  isRegistered(): boolean {
    return this.registered
  }

  /**
   * 鑾峰彇绉婚櫎鍑芥暟鍒楄〃锛堢敤浜?store 绠＄悊锛?
   */
  getRemoveRouteFns(): (() => void)[] {
    return this.removeRouteFns
  }

  /**
   * 鏍囪涓哄凡娉ㄥ唽锛堢敤浜庨敊璇鐞嗗満鏅紝閬垮厤閲嶅璇锋眰锛?
   */
  markAsRegistered(): void {
    this.registered = true
  }
}

