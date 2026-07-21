/**
 * 缁勪欢绫诲瀷瀹氫箟妯″潡
 *
 * 鎻愪緵椤圭洰缁勪欢鐨勭被鍨嬪畾涔?
 *
 * ## 涓昏鍔熻兘
 *
 * - 鎼滅储缁勪欢绫诲瀷瀹氫箟
 * - 琛ㄦ牸鍒楅厤缃被鍨?
 * - 鍒嗛〉閰嶇疆绫诲瀷
 * - 琛ㄥ崟瑙勫垯绫诲瀷
 * - 瀵硅瘽妗嗛厤缃被鍨?
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 缁勪欢 Props 绫诲瀷绾︽潫
 * - 缁勪欢閰嶇疆绫诲瀷瀹氫箟
 * - 缁勪欢浜嬩欢鍙傛暟绫诲瀷
 *
 * @module types/component/index
 * @author AiPay
 */

// 鎼滅储缁勪欢绫诲瀷
export type SearchComponentType =
  | 'input'
  | 'select'
  | 'radio'
  | 'checkbox'
  | 'date'
  | 'datetime'
  | 'daterange'
  | 'datetimerange'
  | 'month'
  | 'monthrange'
  | 'year'
  | 'yearrange'
  | 'week'
  | 'time'
  | 'timerange'

// 鎼滅储妗嗗€煎彉鍖栧弬鏁?
export interface SearchChangeParams {
  prop: string
  val: unknown
}

// 琛ㄦ牸鍒楅厤缃帴鍙?
export interface ColumnOption<T = any> {
  // 鍒楃被鍨?
  type?: 'selection' | 'expand' | 'index' | 'globalIndex'
  // 鍒楀睘鎬у悕
  prop?: string
  // 鍒楁爣棰?
  label?: string
  // 鍒楀搴?
  width?: string | number
  // 鏈€灏忓垪瀹藉害
  minWidth?: string | number
  // 鍥哄畾鍒?
  fixed?: boolean | 'left' | 'right'
  // 鏄惁鍙帓搴?
  sortable?: boolean | 'custom'
  // 杩囨护鍣ㄩ€夐」
  filters?: any[]
  // 杩囨护鏂规硶
  filterMethod?: (value: any, row: any) => boolean
  // 杩囨护鍣ㄤ綅缃?
  filterPlacement?: string
  // 鏄惁绂佺敤
  disabled?: boolean
  // 鏄惁鏄剧ず鍒?
  visible?: boolean
  // 鏄惁閫変腑鏄剧ず
  checked?: boolean
  // 鑷畾涔夋覆鏌撳嚱鏁?
  formatter?: (row: T) => any
  // 鎻掓Ы鐩稿叧閰嶇疆
  // 鏄惁浣跨敤鎻掓Ы娓叉煋鍐呭
  useSlot?: boolean
  // 鎻掓Ы鍚嶇О锛堥粯璁や负 prop 鍊硷級
  slotName?: string
  // 鏄惁浣跨敤琛ㄥご鎻掓Ы
  useHeaderSlot?: boolean
  // 琛ㄥご鎻掓Ы鍚嶇О锛堥粯璁や负 `${prop}-header`锛?
  headerSlotName?: string
  // 鍏朵粬灞炴€?
  [key: string]: any
}

// 鍒嗛〉閰嶇疆
export interface PaginationConfig {
  // 褰撳墠椤?
  currentPage: number
  // 姣忛〉鏉℃暟
  pageSize: number
  // 鎬绘潯鏁?
  total: number
  // 姣忛〉鏄剧ず涓暟閫夋嫨鍣ㄧ殑閫夐」
  pageSizes?: number[]
  // 缁勪欢甯冨眬
  layout?: string
  // 鏄惁涓哄皬鍨嬪垎椤?
  small?: boolean
}

// 琛ㄥ崟瑙勫垯
export interface FormRule {
  // 鏄惁蹇呭～
  required?: boolean
  // 閿欒鎻愮ず淇℃伅
  message?: string
  // 瑙﹀彂鏂瑰紡
  trigger?: string | string[]
  // 鏈€灏忛暱搴?
  min?: number
  // 鏈€澶ч暱搴?
  max?: number
  // 姝ｅ垯琛ㄨ揪寮?
  pattern?: RegExp
  // 鑷畾涔夐獙璇佸嚱鏁?
  validator?: (rule: any, value: any, callback: any) => void
}

// 瀵硅瘽妗嗛厤缃?
export interface DialogConfig {
  // 鏍囬
  title: string
  // 鏄惁鏄剧ず
  visible: boolean
  // 瀹藉害
  width?: string | number
  // 鏄惁鍙互閫氳繃鐐瑰嚮 modal 鍏抽棴
  closeOnClickModal?: boolean
  // 鏄惁鍙互閫氳繃鎸変笅 ESC 鍏抽棴
  closeOnPressEscape?: boolean
  // 鏄惁鏄剧ず鍏抽棴鎸夐挳
  showClose?: boolean
  // 鏄惁鍦?Dialog 鍑虹幇鏃跺皢 body 婊氬姩閿佸畾
  lockScroll?: boolean
  // 鏄惁鏄剧ず閬僵灞?
  modal?: boolean
  // 鑷畾涔夌被鍚?
  customClass?: string
}

