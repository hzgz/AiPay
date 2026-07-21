/**
 * 琛ㄦ儏
 * 鐢ㄤ簬鍦ㄦ秷鎭彁绀虹殑鏃跺€欐樉绀哄搴旂殑琛ㄦ儏
 *
 * 鐢ㄦ硶
 * ElMessage.success(`${EmojiText[200]} 鍥剧墖涓婁紶鎴愬姛`)
 * ElMessage.error(`${EmojiText[400]} 鍥剧墖涓婁紶澶辫触`)
 * ElMessage.error(`${EmojiText[500]} 鍥剧墖涓婁紶澶辫触`)
 *
 * @module utils/ui/emojo
 * @author AiPay
 */

// macos 鐢ㄦ埛 鎸?shift + 6 鍙互鍞ゅ嚭鏇村琛ㄦ儏鈥︹€?
const EmojiText: { [key: string]: string } = {
  '0': 'O_O', // 绌?
  '200': '^_^', // 鎴愬姛
  '400': 'T_T', // 閿欒璇锋眰
  '500': 'X_X' // 鏈嶅姟鍣ㄥ唴閮ㄩ敊璇紝鏃犳硶瀹屾垚璇锋眰
}

// const EmojiIcon = ['馃煝', '馃敶', '馃煛 ', '馃殌', '鉁?, '馃挕', '馃洜锔?, '馃敟', '馃帀', '馃専', '馃寛']

export default EmojiText

