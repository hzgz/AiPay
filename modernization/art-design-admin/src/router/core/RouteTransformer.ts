/**
 * 璺敱杞崲鍣?
 *
 * 璐熻矗灏嗚彍鍗曟暟鎹浆鎹负 Vue Router 璺敱閰嶇疆
 *
 * @module router/core/RouteTransformer
 * @author AiPay
 */

import type { RouteRecordRaw } from 'vue-router'
import type { AppRouteRecord } from '@/types/router'
import { ComponentLoader } from './ComponentLoader'
import { IframeRouteManager } from './IframeRouteManager'

interface ConvertedRoute extends Omit<RouteRecordRaw, 'children'> {
  id?: number
  children?: ConvertedRoute[]
  component?: RouteRecordRaw['component'] | (() => Promise<any>)
}

export class RouteTransformer {
  private componentLoader: ComponentLoader
  private iframeManager: IframeRouteManager

  constructor(componentLoader: ComponentLoader) {
    this.componentLoader = componentLoader
    this.iframeManager = IframeRouteManager.getInstance()
  }

  /**
   * 杞崲璺敱閰嶇疆
   */
  transform(route: AppRouteRecord, depth = 0): ConvertedRoute {
    const { component, children, ...routeConfig } = route

    // 鍩虹璺敱閰嶇疆
    const converted: ConvertedRoute = {
      ...routeConfig,
      component: undefined
    }

    // 澶勭悊涓嶅悓绫诲瀷鐨勮矾鐢?
    if (route.meta.isIframe) {
      this.handleIframeRoute(converted, route, depth)
    } else if (this.isFirstLevelRoute(route, depth)) {
      this.handleFirstLevelRoute(converted, route, component as string)
    } else {
      this.handleNormalRoute(converted, component as string)
    }

    // 閫掑綊澶勭悊瀛愯矾鐢?
    if (children?.length) {
      converted.children = children.map((child) => this.transform(child, depth + 1))
    }

    return converted
  }

  /**
   * 鍒ゆ柇鏄惁涓轰竴绾ц矾鐢憋紙闇€瑕?Layout 鍖呰９锛?
   */
  private isFirstLevelRoute(route: AppRouteRecord, depth: number): boolean {
    return depth === 0 && (!route.children || route.children.length === 0)
  }

  /**
   * 澶勭悊 iframe 绫诲瀷璺敱
   */
  private handleIframeRoute(
    targetRoute: ConvertedRoute,
    sourceRoute: AppRouteRecord,
    depth: number
  ): void {
    if (depth === 0) {
      // 椤剁骇 iframe锛氱敤 Layout 鍖呰９
      targetRoute.component = this.componentLoader.loadLayout()
      targetRoute.path = this.extractFirstSegment(sourceRoute.path || '')
      targetRoute.name = ''

      targetRoute.children = [
        {
          ...sourceRoute,
          component: this.componentLoader.loadIframe()
        } as ConvertedRoute
      ]
    } else {
      // 闈為《绾э紙宓屽锛塱frame锛氱洿鎺ヤ娇鐢?Iframe.vue
      targetRoute.component = this.componentLoader.loadIframe()
    }

    // 璁板綍 iframe 璺敱
    this.iframeManager.add(sourceRoute)
  }

  /**
   * 澶勭悊涓€绾ц彍鍗曡矾鐢?
   */
  private handleFirstLevelRoute(
    converted: ConvertedRoute,
    route: AppRouteRecord,
    component: string | undefined
  ): void {
    converted.component = this.componentLoader.loadLayout()
    converted.path = this.extractFirstSegment(route.path || '')
    converted.name = ''
    route.meta.isFirstLevel = true

    converted.children = [
      {
        ...route,
        component: component ? this.componentLoader.load(component) : undefined
      } as ConvertedRoute
    ]
  }

  /**
   * 澶勭悊鏅€氳矾鐢?
   */
  private handleNormalRoute(converted: ConvertedRoute, component: string | undefined): void {
    if (component) {
      converted.component = this.componentLoader.load(component)
    }
  }

  /**
   * 鎻愬彇璺緞鐨勭涓€娈?
   */
  private extractFirstSegment(path: string): string {
    const segments = path.split('/').filter(Boolean)
    return segments.length > 0 ? `/${segments[0]}` : '/'
  }
}

