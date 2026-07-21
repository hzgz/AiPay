/**
 * 鍏ㄥ眬缁勪欢閰嶇疆
 *
 * 缁熶竴绠＄悊绯荤粺绾у叏灞€缁勪欢鐨勬敞鍐屻€?
 * 杩欎簺缁勪欢浼氬湪搴旂敤鍚姩鏃跺叏灞€娉ㄥ唽锛屽彲鍦ㄤ换浣曞湴鏂逛娇鐢ㄣ€?
 *
 * ## 涓昏鍔熻兘
 *
 * - 缁勪欢閰嶇疆 - 闆嗕腑绠＄悊鍏ㄥ眬缁勪欢鐨勯厤缃俊鎭?
 * - 寮傛鍔犺浇 - 浣跨敤 defineAsyncComponent 瀹炵幇鎸夐渶鍔犺浇
 * - 寮€鍏虫帶鍒?- 鏀寔閫氳繃 enabled 瀛楁鍚敤/绂佺敤缁勪欢
 * - 閰嶇疆鏌ヨ - 鎻愪緵宸ュ叿鍑芥暟蹇€熸煡璇㈢粍浠堕厤缃?
 *
 * @module config/component
 * @author AiPay
 */

import { defineAsyncComponent } from 'vue'

/**
 * 鍏ㄥ眬缁勪欢閰嶇疆鍒楄〃
 */
export const globalComponentsConfig: GlobalComponentConfig[] = [
  {
    name: '璁剧疆闈㈡澘',
    key: 'settings-panel',
    component: defineAsyncComponent(
      () => import('@/components/core/layouts/art-settings-panel/index.vue')
    ),
    enabled: false
  },
  {
    name: '鍏ㄥ眬鎼滅储',
    key: 'global-search',
    component: defineAsyncComponent(
      () => import('@/components/core/layouts/art-global-search/index.vue')
    ),
    enabled: true
  },
  {
    name: '閿佸睆',
    key: 'screen-lock',
    component: defineAsyncComponent(
      () => import('@/components/core/layouts/art-screen-lock/index.vue')
    ),
    enabled: false
  },
  {
    name: '鑱婂ぉ绐楀彛',
    key: 'chat-window',
    component: defineAsyncComponent(
      () => import('@/components/core/layouts/art-chat-window/index.vue')
    ),
    enabled: false
  },
  {
    name: '绀艰姳鏁堟灉',
    key: 'fireworks-effect',
    component: defineAsyncComponent(
      () => import('@/components/core/layouts/art-fireworks-effect/index.vue')
    ),
    enabled: false
  },
  {
    name: '姘村嵃鏁堟灉',
    key: 'watermark',
    component: defineAsyncComponent(
      () => import('@/components/core/others/art-watermark/index.vue')
    ),
    enabled: true
  }
]

/**
 * 鍏ㄥ眬缁勪欢閰嶇疆鎺ュ彛
 */
export interface GlobalComponentConfig {
  /** 缁勪欢鍚嶇О */
  name: string
  /** 缁勪欢鏍囪瘑 */
  key: string
  /** 缁勪欢 */
  component: any
  /** 鏄惁鍚敤 */
  enabled?: boolean
  /** 缁勪欢鎻忚堪 */
  description?: string
}

/**
 * 鑾峰彇鍚敤鐨勫叏灞€缁勪欢
 * @returns 宸插惎鐢ㄧ殑缁勪欢閰嶇疆鍒楄〃
 */
export const getEnabledGlobalComponents = () => {
  return globalComponentsConfig.filter((config) => config.enabled !== false)
}

/**
 * 鏍规嵁 key 鑾峰彇缁勪欢閰嶇疆
 * @param key 缁勪欢鏍囪瘑
 * @returns 缁勪欢閰嶇疆瀵硅薄
 */
export const getGlobalComponentByKey = (key: string) => {
  return globalComponentsConfig.find((config) => config.key === key)
}

