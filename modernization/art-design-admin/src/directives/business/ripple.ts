/**
 * v-ripple 姘存尝绾规晥鏋滄寚浠?
 *
 * 涓哄厓绱犳坊鍔?Material Design 椋庢牸鐨勬按娉㈢汗鐐瑰嚮鏁堟灉銆?
 * 鐐瑰嚮鏃朵粠鐐瑰嚮浣嶇疆鎵╂暎鍑哄渾褰㈡按娉㈢汗鍔ㄧ敾锛屾彁鍗囦氦浜掍綋楠屻€?
 *
 * ## 涓昏鍔熻兘
 *
 * - 姘存尝绾瑰姩鐢?- 鐐瑰嚮鏃朵粠鐐瑰嚮浣嶇疆鎵╂暎鍦嗗舰娉㈢汗
 * - 鑷€傚簲澶у皬 - 鏍规嵁鍏冪礌灏哄鑷姩璋冩暣娉㈢汗澶у皬鍜屽姩鐢绘椂闀?
 * - 鏅鸿兘閰嶈壊 - 鑷姩璇嗗埆鎸夐挳绫诲瀷锛屼娇鐢ㄥ悎閫傜殑娉㈢汗棰滆壊
 * - 鑷畾涔夐鑹?- 鏀寔閫氳繃鍙傛暟鑷畾涔夋尝绾归鑹?
 * - 鎬ц兘浼樺寲 - 浣跨敤 requestAnimationFrame 鍜岃嚜鍔ㄦ竻鐞嗘満鍒?
 *
 * ## 浣跨敤绀轰緥
 *
 * ```vue
 * <template>
 *   <!-- 鍩虹鐢ㄦ硶 - 浣跨敤榛樿棰滆壊 -->
 *   <el-button v-ripple>鐐瑰嚮鎴?/el-button>
 *
 *   <!-- 鑷畾涔夐鑹?-->
 *   <el-button v-ripple="{ color: 'rgba(255, 0, 0, 0.3)' }">鑷畾涔夐鑹?/el-button>
 *
 *   <!-- 搴旂敤鍒颁换鎰忓厓绱?-->
 *   <div v-ripple class="custom-card">鍗＄墖鍐呭</div>
 * </template>
 * ```
 *
 * ## 棰滆壊瑙勫垯
 *
 * - 鏈夎壊鎸夐挳锛坧rimary銆乻uccess銆亀arning 绛夛級锛氫娇鐢ㄧ櫧鑹插崐閫忔槑娉㈢汗
 * - 榛樿鎸夐挳锛氫娇鐢ㄤ富棰樿壊鍗婇€忔槑娉㈢汗
 * - 鑷畾涔夛細閫氳繃 color 鍙傛暟鎸囧畾浠绘剰棰滆壊
 *
 * @module directives/ripple
 * @author AiPay
 */

import type { App, Directive, DirectiveBinding } from 'vue'

export interface RippleOptions {
  /** 姘存尝绾归鑹?*/
  color?: string
}

export type RippleDirective = Directive<HTMLElement, RippleOptions>

export const vRipple: RippleDirective = {
  mounted(el: HTMLElement, binding: DirectiveBinding) {
    // 鑾峰彇鎸囦护鐨勯厤缃弬鏁?
    const options: RippleOptions = binding.value || {}

    // 璁剧疆鍏冪礌涓虹浉瀵瑰畾浣嶏紝骞堕殣钘忔孩鍑洪儴鍒?
    el.style.position = 'relative'
    el.style.overflow = 'hidden'

    // 鐐瑰嚮浜嬩欢澶勭悊
    el.addEventListener('mousedown', (e: MouseEvent) => {
      const rect = el.getBoundingClientRect()
      const left = e.clientX - rect.left
      const top = e.clientY - rect.top

      // 鍒涘缓姘存尝绾瑰厓绱?
      const ripple = document.createElement('div')
      const diameter = Math.max(el.clientWidth, el.clientHeight)
      const radius = diameter / 2

      // 鏍规嵁鐩村緞璁＄畻鍔ㄧ敾鏃堕棿锛堢洿寰勮秺澶э紝鍔ㄧ敾鏃堕棿瓒婇暱锛?
      const baseTime = 600 // 鍩虹鍔ㄧ敾鏃堕棿锛堟绉掞級
      const scaleFactor = 0.5 // 缂╂斁鍥犲瓙
      const animationDuration = baseTime + diameter * scaleFactor

      // 璁剧疆姘存尝绾圭殑灏哄鍜屼綅缃?
      ripple.style.width = ripple.style.height = `${diameter}px`
      ripple.style.left = `${left - radius}px`
      ripple.style.top = `${top - radius}px`
      ripple.style.position = 'absolute'
      ripple.style.borderRadius = '50%'
      ripple.style.pointerEvents = 'none'

      // 鍒ゆ柇鏄惁涓烘湁鑹叉寜閽紙Element Plus 鎸夐挳绫诲瀷锛?
      const buttonTypes = ['primary', 'info', 'warning', 'danger', 'success'].map(
        (type) => `el-button--${type}`
      )
      const isColoredButton = buttonTypes.some((type) => el.classList.contains(type))
      const defaultColor = isColoredButton
        ? 'rgba(255, 255, 255, 0.25)' // 鏈夎壊鎸夐挳浣跨敤鐧借壊姘存尝绾?
        : 'var(--el-color-primary-light-7)' // 榛樿鎸夐挳浣跨敤涓婚鑹叉按娉㈢汗

      // 璁剧疆姘存尝绾归鑹层€佸垵濮嬬姸鎬佸拰杩囨浮鏁堟灉
      ripple.style.backgroundColor = options.color || defaultColor
      ripple.style.transform = 'scale(0)'
      ripple.style.transition = `transform ${animationDuration}ms cubic-bezier(0.3, 0, 0.2, 1), opacity ${animationDuration}ms cubic-bezier(0.3, 0, 0.5, 1)`
      ripple.style.zIndex = '1'

      // 娣诲姞姘存尝绾瑰厓绱犲埌DOM涓?
      el.appendChild(ripple)

      // 瑙﹀彂鍔ㄧ敾
      requestAnimationFrame(() => {
        ripple.style.transform = 'scale(2)'
        ripple.style.opacity = '0'
      })

      // 鍔ㄧ敾缁撴潫鍚庣Щ闄ゆ按娉㈢汗鍏冪礌
      setTimeout(() => {
        ripple.remove()
      }, animationDuration + 500) // 澧炲姞500ms缂撳啿鏃堕棿
    })
  }
}

export function setupRippleDirective(app: App) {
  app.directive('ripple', vRipple)
}

