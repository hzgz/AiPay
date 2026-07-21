/**
 * useAppMode - 搴旂敤妯″紡绠＄悊
 *
 * 鎻愪緵搴旂敤璁块棶妯″紡鐨勫垽鏂拰绠＄悊鍔熻兘锛屾敮鎸佸墠绔拰鍚庣涓ょ鏉冮檺鎺у埗妯″紡銆?
 * 鏍规嵁鐜鍙橀噺 VITE_ACCESS_MODE 鑷姩璇嗗埆褰撳墠杩愯妯″紡銆?
 *
 * ## 涓昏鍔熻兘
 *
 * 1. 妯″紡璇嗗埆 - 鑷姩璇嗗埆鍓嶇妯″紡鎴栧悗绔ā寮?
 * 2. 鍓嶇妯″紡 - 鏉冮檺鐢卞墠绔矾鐢遍厤缃帶鍒讹紝閫傚悎灏忓瀷椤圭洰鎴栨紨绀虹幆澧?
 * 3. 鍚庣妯″紡 - 鏉冮檺鐢卞悗绔帴鍙ｈ繑鍥炵殑鑿滃崟鏁版嵁鎺у埗锛岄€傚悎浼佷笟绾у簲鐢?
 * 4. 鍝嶅簲寮忕姸鎬?- 鎻愪緵鍝嶅簲寮忕殑妯″紡鍒ゆ柇锛屾柟渚垮湪缁勪欢涓娇鐢?
 *
 * @module useAppMode
 * @author AiPay
 */

import { computed } from 'vue'

export function useAppMode() {
  // 鑾峰彇璁块棶妯″紡閰嶇疆
  const accessMode = import.meta.env.VITE_ACCESS_MODE

  /**
   * 鏄惁涓哄墠绔帶鍒舵ā寮?
   * 鍓嶇妯″紡锛氭潈闄愮敱鍓嶇璺敱閰嶇疆鎺у埗
   */
  const isFrontendMode = computed(() => accessMode === 'frontend')
  /**
   * 鏄惁涓哄悗绔帶鍒舵ā寮?
   * 鍚庣妯″紡锛氭潈闄愮敱鍚庣鎺ュ彛杩斿洖鐨勮彍鍗曟暟鎹帶鍒?
   */
  const isBackendMode = computed(() => accessMode === 'backend')

  /**
   * 褰撳墠搴旂敤妯″紡
   */
  const currentMode = computed(() => accessMode)

  return {
    isFrontendMode,
    isBackendMode,
    currentMode
  }
}

