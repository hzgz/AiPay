/**
 * API 鍝嶅簲绫诲瀷瀹氫箟妯″潡
 *
 * 鎻愪緵缁熶竴鐨?API 鍝嶅簲缁撴瀯绫诲瀷瀹氫箟
 *
 * ## 涓昏鍔熻兘
 *
 * - 鍩虹鍝嶅簲缁撴瀯瀹氫箟
 * - 娉涘瀷鏀寔锛堥€傞厤涓嶅悓鏁版嵁绫诲瀷锛?
 * - 缁熶竴鐨勫搷搴旀牸寮忕害鏉?
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - API 璇锋眰鍝嶅簲绫诲瀷绾︽潫
 * - 鎺ュ彛鏁版嵁绫诲瀷瀹氫箟
 * - 鍝嶅簲鏁版嵁瑙ｆ瀽
 *
 * @module types/common/response
 * @author AiPay
 */

/** 鍩虹 API 鍝嶅簲缁撴瀯 */
export interface BaseResponse<T = unknown> {
  /** 鐘舵€佺爜 */
  code: number
  /** 娑堟伅 */
  msg: string
  /** 鏁版嵁 */
  data: T
}

