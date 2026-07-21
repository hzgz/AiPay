/**
 * 琛ㄦ牸鍏ㄥ眬閰嶇疆妯″潡
 *
 * 鎻愪緵琛ㄦ牸涓庡悗绔帴鍙ｇ殑瀛楁鏄犲皠閰嶇疆
 *
 * ## 涓昏鍔熻兘
 *
 * - 鍝嶅簲鏁版嵁瀛楁鑷姩璇嗗埆鍜屾槧灏?
 * - 鏀寔澶氱甯歌鐨勫悗绔搷搴旀牸寮?
 * - 璇锋眰鍙傛暟瀛楁鏄犲皠閰嶇疆
 * - 鍙墿灞曠殑瀛楁閰嶇疆鏈哄埗
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 閫傞厤涓嶅悓鍚庣鐨勫垎椤垫帴鍙ｆ牸寮?
 * - 缁熶竴鍓嶇琛ㄦ牸缁勪欢鐨勬暟鎹鐞?
 * - 鍑忓皯閲嶅鐨勬暟鎹浆鎹唬鐮?
 * - 鏀寔澶氫釜鍚庣鏈嶅姟鐨勬帴鍙ｅ鎺?
 *
 * ## 閰嶇疆璇存槑
 *
 * - recordFields: 鍒楄〃鏁版嵁瀛楁鍚嶏紙鎸変紭鍏堢骇椤哄簭鏌ユ壘锛?
 * - totalFields: 鎬绘潯鏁板瓧娈靛悕
 * - currentFields: 褰撳墠椤电爜瀛楁鍚?
 * - sizeFields: 姣忛〉澶у皬瀛楁鍚?
 * - paginationKey: 鍓嶇鍙戦€佽姹傛椂浣跨敤鐨勫垎椤靛弬鏁板悕
 *
 * ## 鎵╁睍鏂瑰紡
 *
 * 濡傛灉鍚庣浣跨敤鍏朵粬瀛楁鍚嶏紝鍙互鍦ㄥ搴旀暟缁勪腑娣诲姞鏂扮殑瀛楁鍚?
 * 渚嬪锛歳ecordFields: ['list', 'data', 'records', 'items', 'yourCustomField']
 *
 * @module utils/table/tableConfig
 * @author AiPay
 */
export const tableConfig = {
  // 鍝嶅簲鏁版嵁瀛楁鏄犲皠閰嶇疆锛岀郴缁熶細浠庢帴鍙ｈ繑鍥炴暟鎹腑鎸夐『搴忔煡鎵捐繖浜涘瓧娈?
  // 鍒楄〃鏁版嵁
  recordFields: ['list', 'data', 'records', 'items', 'result', 'rows'],
  // 鎬绘潯鏁?
  totalFields: ['total', 'count'],
  // 褰撳墠椤电爜
  currentFields: ['current', 'page', 'pageNum'],
  // 姣忛〉澶у皬
  sizeFields: ['size', 'pageSize', 'limit'],

  // 璇锋眰鍙傛暟鏄犲皠閰嶇疆锛屽墠绔彂閫佽姹傛椂浣跨敤鐨勫垎椤靛弬鏁板悕
  // useTable 缁勫悎寮忓嚱鏁颁紶閫掑垎椤靛弬鏁扮殑鏃跺€?鐢?current 璺?size
  paginationKey: {
    // 褰撳墠椤电爜
    current: 'current',
    // 姣忛〉澶у皬
    size: 'size'
  }
}

