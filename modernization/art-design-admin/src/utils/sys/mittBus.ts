/**
 * 鍏ㄥ眬浜嬩欢鎬荤嚎妯″潡
 *
 * 鍩轰簬 mitt 搴撳疄鐜扮殑绫诲瀷瀹夊叏鐨勪簨浠舵€荤嚎
 *
 * ## 涓昏鍔熻兘
 *
 * - 璺ㄧ粍浠堕€氫俊锛堝彂甯?璁㈤槄妯″紡锛?
 * - 绫诲瀷瀹夊叏鐨勪簨浠跺畾涔夊拰璋冪敤
 * - 鍏ㄥ眬浜嬩欢绠＄悊锛堢儫鑺辨晥鏋溿€佽缃潰鏉裤€佹悳绱㈠璇濇绛夛級
 * - 瑙ｈ€︾粍浠堕棿鐨勭洿鎺ヤ緷璧?
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 璺ㄥ眰绾х粍浠堕€氫俊
 * - 鍏ㄥ眬鍔熻兘瑙﹀彂锛堣缃€佹悳绱€佽亰澶┿€侀攣灞忕瓑锛?
 * - 鐗规晥瑙﹀彂锛堢儫鑺辨晥鏋滐級
 * - 閬垮厤 props 灞傚眰浼犻€?
 *
 * ## 鐢ㄦ硶绀轰緥
 *
 * ```typescript
 * // 璁㈤槄浜嬩欢
 * mittBus.on('openSetting', () => { ... })
 *
 * // 鍙戝竷浜嬩欢
 * mittBus.emit('openSetting')
 *
 * // 甯﹀弬鏁扮殑浜嬩欢
 * mittBus.emit('triggerFireworks', 'image-url')
 * ```
 *
 * ## 宸插畾涔夌殑浜嬩欢
 *
 * - triggerFireworks: 瑙﹀彂鐑熻姳鏁堟灉锛堝彲閫夊浘鐗嘦RL锛?
 * - openSetting: 鎵撳紑璁剧疆闈㈡澘
 * - openSearchDialog: 鎵撳紑鎼滅储瀵硅瘽妗?
 * - openChat: 鎵撳紑鑱婂ぉ绐楀彛
 * - openLockScreen: 鎵撳紑閿佸睆
 *
 * @module utils/sys/mittBus
 * @author AiPay
 */
import mitt, { type Emitter } from 'mitt'

// 瀹氫箟浜嬩欢绫诲瀷鏄犲皠
type Events = {
  // 鐑熻姳鏁堟灉浜嬩欢 - 鍙€夌殑鍥剧墖URL鍙傛暟
  triggerFireworks: string | undefined
  // 鎵撳紑璁剧疆闈㈡澘浜嬩欢 - 鏃犲弬鏁?
  openSetting: void
  // 鎵撳紑鎼滅储瀵硅瘽妗嗕簨浠?- 鏃犲弬鏁?
  openSearchDialog: void
  // 鎵撳紑鑱婂ぉ绐楀彛浜嬩欢 - 鏃犲弬鏁?
  openChat: void
  // 鎵撳紑閿佸睆浜嬩欢 - 鏃犲弬鏁?
  openLockScreen: void
}

// 鍒涘缓绫诲瀷瀹夊叏鐨勪簨浠舵€荤嚎瀹炰緥
const mittBus: Emitter<Events> = mitt<Events>()

export default mittBus

