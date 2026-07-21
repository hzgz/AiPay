/**
 * 椤堕儴鏍忓姛鑳介厤缃?
 *
 * 缁熶竴绠＄悊椤堕儴鏍忓悇涓姛鑳芥ā鍧楃殑鍚敤鐘舵€併€?
 * 閫氳繃淇敼姝ら厤缃枃浠跺彲浠ュ揩閫熷惎鐢ㄦ垨绂佺敤椤堕儴鏍忕殑鍔熻兘鎸夐挳銆?
 *
 * @module config/headerBar
 * @author AiPay
 */

import { HeaderBarFeatureConfig } from '@/types'

/**
 * 椤堕儴鏍忓姛鑳介厤缃璞?
 */
export const headerBarConfig: HeaderBarFeatureConfig = {
  menuButton: {
    enabled: true,
    description: '鎺у埗宸︿晶鑿滃崟鐨勫睍寮€/鏀惰捣鎸夐挳'
  },
  refreshButton: {
    enabled: true,
    description: '椤甸潰鍒锋柊鎸夐挳'
  },
  fastEnter: {
    enabled: false,
    description: '快捷入口功能，提供常用应用和链接的快速访问'
  },
  breadcrumb: {
    enabled: true,
    description: '闈㈠寘灞戝鑸紝鏄剧ず褰撳墠椤甸潰璺緞'
  },
  globalSearch: {
    enabled: true,
    description: '鍏ㄥ眬鎼滅储鍔熻兘锛屾敮鎸佸揩鎹烽敭 Ctrl+K 鎴?Cmd+K'
  },
  fullscreen: {
    enabled: true,
    description: '鍏ㄥ睆鍒囨崲鍔熻兘'
  },
  notification: {
    enabled: false,
    description: '通知中心，显示系统通知和消息'
  },
  chat: {
    enabled: false,
    description: '聊天功能，提供实时沟通'
  },
  language: {
    enabled: false,
    description: '澶氳瑷€鍒囨崲鍔熻兘'
  },
  settings: {
    enabled: false,
    description: '绯荤粺璁剧疆闈㈡澘'
  },
  themeToggle: {
    enabled: true,
    description: '涓婚鍒囨崲鍔熻兘锛堟槑鏆椾富棰橈級'
  }
}

export default headerBarConfig

