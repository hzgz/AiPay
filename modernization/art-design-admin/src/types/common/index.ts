/**
 * 閫氱敤绫诲瀷瀹氫箟妯″潡
 *
 * 鎻愪緵椤圭洰涓父鐢ㄧ殑閫氱敤绫诲瀷瀹氫箟
 *
 * ## 涓昏鍔熻兘
 *
 * - 鐘舵€佺被鍨嬶紙鍚敤/绂佺敤锛?
 * - 鎬у埆绫诲瀷
 * - 鎺掑簭鏂瑰悜绫诲瀷
 * - 鎿嶄綔绫诲瀷锛堝鍒犳敼鏌ワ級
 * - 璁板綍绫诲瀷锛堥敭鍊煎锛?
 * - 鏃堕棿鑼冨洿绫诲瀷
 * - 鏂囦欢淇℃伅绫诲瀷
 * - 鍧愭爣鍜屽昂瀵哥被鍨?
 * - 鍝嶅簲寮忔柇鐐圭被鍨?
 * - 涓婚鍜岃瑷€绫诲瀷
 * - 鐜鍜屽脊绐楃被鍨?
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 閫氱敤鏁版嵁缁撴瀯瀹氫箟
 * - 绫诲瀷绾︽潫鍜屾彁绀?
 * - 鍑忓皯閲嶅绫诲瀷瀹氫箟
 *
 * @module types/common/index
 * @author AiPay
 */

// 瀵煎嚭鍝嶅簲绫诲瀷
export * from './response'

// 鐘舵€佺被鍨?
export type Status = 0 | 1 // 0: 绂佺敤, 1: 鍚敤

// 鎬у埆绫诲瀷
export type Gender = 'male' | 'female' | 'unknown'

// 鎺掑簭鏂瑰悜
export type SortOrder = 'ascending' | 'descending'

// 鎿嶄綔绫诲瀷
export type ActionType = 'create' | 'update' | 'delete' | 'view'

// 鍙€夌殑璁板綍绫诲瀷
export type Recordable<T = any> = Record<string, T>

// 閿€煎绫诲瀷
export type KeyValue<T = any> = {
  key: string
  value: T
  label?: string
}

// 鏃堕棿鑼冨洿绫诲瀷
export interface TimeRange {
  startTime: string
  endTime: string
}

// 鏂囦欢绫诲瀷
export interface FileInfo {
  name: string
  url: string
  size: number
  type: string
  lastModified?: number
}

// 鍧愭爣绫诲瀷
export interface Position {
  x: number
  y: number
}

// 灏哄绫诲瀷
export interface Size {
  width: number
  height: number
}

// 鍝嶅簲寮忔柇鐐圭被鍨?
export type Breakpoint = 'xs' | 'sm' | 'md' | 'lg' | 'xl'

// 涓婚绫诲瀷
export type ThemeMode = 'light' | 'dark' | 'auto'

// 璇█绫诲瀷
export type Language = 'zh-CN' | 'en-US'

// 鐜绫诲瀷
export type Environment = 'development' | 'production' | 'test'

// 寮圭獥绫诲瀷
export type DialogType = 'add' | 'edit'

