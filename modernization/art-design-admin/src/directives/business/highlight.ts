/**
 * v-highlight 浠ｇ爜楂樹寒鎸囦护
 *
 * 涓轰唬鐮佸潡鎻愪緵璇硶楂樹寒銆佽鍙锋樉绀哄拰涓€閿鍒跺姛鑳姐€?
 * 鍩轰簬 highlight.js 瀹炵幇锛屾敮鎸佸绉嶇紪绋嬭瑷€鐨勮娉曢珮浜€?
 *
 * ## 涓昏鍔熻兘
 *
 * - 璇硶楂樹寒 - 浣跨敤 highlight.js 鑷姩璇嗗埆骞堕珮浜唬鐮?
 * - 琛屽彿鏄剧ず - 鑷姩涓烘瘡琛屼唬鐮佹坊鍔犺鍙?
 * - 涓€閿鍒?- 鎻愪緵澶嶅埗鎸夐挳锛岀偣鍑诲嵆鍙鍒朵唬鐮侊紙鑷姩杩囨护琛屽彿锛?
 * - 鎬ц兘浼樺寲 - 鎵归噺澶勭悊浠ｇ爜鍧楋紝閬垮厤闃诲娓叉煋
 * - 鍔ㄦ€佺洃鍚?- 浣跨敤 MutationObserver 鐩戝惉鏂板浠ｇ爜鍧?
 * - 闃查噸澶嶅鐞?- 鑷姩鏍囪宸插鐞嗙殑浠ｇ爜鍧楋紝閬垮厤閲嶅澶勭悊
 *
 * ## 浣跨敤绀轰緥
 *
 * ```vue
 * <template>
 *   <!-- 鍩虹鐢ㄦ硶 -->
 *   <div v-highlight v-html="codeContent"></div>
 *
 *   <!-- 閰嶅悎 Markdown 娓叉煋 -->
 *   <div v-highlight>
 *     <pre><code class="language-javascript">
 *       const hello = 'world';
 *       console.log(hello);
 *     </code></pre>
 *   </div>
 * </template>
 * ```
 *
 * ## 鎬ц兘浼樺寲
 *
 * - 鎵归噺澶勭悊锛氭瘡娆″鐞?10 涓唬鐮佸潡锛岄伩鍏嶉暱鏃堕棿闃诲
 * - 寤惰繜澶勭悊锛氫娇鐢?requestAnimationFrame 鍒嗘壒澶勭悊
 * - 閲嶈瘯鏈哄埗锛氳嚜鍔ㄩ噸璇曞鐞嗗け璐ョ殑浠ｇ爜鍧?
 * - 鏅鸿兘鐩戝惉锛氬彧鍦ㄦ湁鏂颁唬鐮佸潡鏃舵墠瑙﹀彂澶勭悊
 *
 * @module directives/highlight
 * @author AiPay
 */

import { App, Directive } from 'vue'
import hljs from 'highlight.js'

export type HighlightDirective = Directive<HTMLElement>

// 楂樹寒浠ｇ爜
function highlightCode(block: HTMLElement) {
  hljs.highlightElement(block)
}

// 鎻掑叆琛屽彿
function insertLineNumbers(block: HTMLElement) {
  const lines = block.innerHTML.split('\n')
  const numberedLines = lines
    .map((line, index) => {
      return `<span class="line-number">${index + 1}</span> ${line}`
    })
    .join('\n')
  block.innerHTML = numberedLines
}

// 娣诲姞澶嶅埗鎸夐挳锛氳皟鏁?DOM 缁撴瀯锛屽皢浠ｇ爜閮ㄥ垎鍖呰９鍦?.code-wrapper 鍐?
function addCopyButton(block: HTMLElement) {
  const copyButton = document.createElement('i')
  copyButton.className = 'copy-button'
  copyButton.innerHTML =
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M7 6V3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-3v3c0 .552-.45 1-1.007 1H4.007A1 1 0 0 1 3 21l.003-14c0-.552.45-1 1.006-1zM5.002 8L5 20h10V8zM9 6h8v10h2V4H9z"/></svg>'
  copyButton.onclick = () => {
    // 杩囨护鎺夎鍙凤紝鍙鍒朵唬鐮佸唴瀹?
    const codeContent = block.innerText.replace(/^\d+\s+/gm, '')
    navigator.clipboard.writeText(codeContent).then(() => {
      ElMessage.success('澶嶅埗鎴愬姛')
    })
  }

  const preElement = block.parentElement
  if (preElement) {
    let codeWrapper: HTMLElement
    // 濡傛灉浠ｇ爜鍧楄繕娌℃湁琚寘瑁癸紝鍒欏垱寤哄寘瑁瑰鍣?
    if (!block.parentElement.classList.contains('code-wrapper')) {
      codeWrapper = document.createElement('div')
      codeWrapper.className = 'code-wrapper'
      preElement.replaceChild(codeWrapper, block)
      codeWrapper.appendChild(block)
    } else {
      codeWrapper = block.parentElement
    }
    // 灏嗗鍒舵寜閽坊鍔犲埌 pre 鍏冪礌锛堣€岄潪 codeWrapper 鍐咃級锛岃繖鏍峰畠涓嶄細闅忔粴鍔ㄦ潯婊氬姩
    preElement.appendChild(copyButton)
  }
}

// 妫€鏌ヤ唬鐮佸潡鏄惁宸茬粡琚鐞嗚繃
function isBlockProcessed(block: HTMLElement): boolean {
  return (
    block.hasAttribute('data-highlighted') ||
    !!block.querySelector('.line-number') ||
    !!block.parentElement?.querySelector('.copy-button')
  )
}

// 鏍囪浠ｇ爜鍧椾负宸插鐞?
function markBlockAsProcessed(block: HTMLElement) {
  block.setAttribute('data-highlighted', 'true')
}

// 澶勭悊鍗曚釜浠ｇ爜鍧?
function processBlock(block: HTMLElement) {
  if (isBlockProcessed(block)) {
    return
  }

  try {
    highlightCode(block)
    insertLineNumbers(block)
    addCopyButton(block)
    markBlockAsProcessed(block)
  } catch (error) {
    console.warn('澶勭悊浠ｇ爜鍧楁椂鍑洪敊:', error)
  }
}

// 鏌ユ壘骞跺鐞嗘墍鏈変唬鐮佸潡
function processAllCodeBlocks(el: HTMLElement) {
  const blocks = Array.from(el.querySelectorAll<HTMLElement>('pre code'))
  const unprocessedBlocks = blocks.filter((block) => !isBlockProcessed(block))

  if (unprocessedBlocks.length === 0) {
    return
  }

  if (unprocessedBlocks.length <= 10) {
    // 濡傛灉浠ｇ爜鍧楁暟閲忓皯浜庣瓑浜?0锛岀洿鎺ュ鐞嗘墍鏈変唬鐮佸潡
    unprocessedBlocks.forEach((block) => processBlock(block))
  } else {
    // 瀹氫箟姣忔澶勭悊鐨勪唬鐮佸潡鏁?
    const batchSize = 10
    let currentIndex = 0

    const processBatch = () => {
      const batch = unprocessedBlocks.slice(currentIndex, currentIndex + batchSize)

      batch.forEach((block) => {
        processBlock(block)
      })

      // 鏇存柊绱㈠紩骞剁户缁鐞嗕笅涓€鎵?
      currentIndex += batchSize
      if (currentIndex < unprocessedBlocks.length) {
        // 浣跨敤 requestAnimationFrame 纭繚涓嬩竴甯у啀澶勭悊
        requestAnimationFrame(processBatch)
      }
    }

    // 寮€濮嬪鐞嗙涓€鎵逛唬鐮佸潡
    processBatch()
  }
}

// 閲嶈瘯澶勭悊鍑芥暟
function retryProcessing(el: HTMLElement, maxRetries: number = 3, delay: number = 200) {
  let retryCount = 0

  const tryProcess = () => {
    processAllCodeBlocks(el)

    // 妫€鏌ユ槸鍚﹁繕鏈夋湭澶勭悊鐨勪唬鐮佸潡
    const remainingBlocks = Array.from(el.querySelectorAll<HTMLElement>('pre code')).filter(
      (block) => !isBlockProcessed(block)
    )

    if (remainingBlocks.length > 0 && retryCount < maxRetries) {
      retryCount++
      setTimeout(tryProcess, delay * retryCount) // 閫掑寤惰繜
    }
  }

  tryProcess()
}

// 浠ｇ爜楂樹寒銆佹彃鍏ヨ鍙枫€佸鍒舵寜閽?
const highlightDirective: HighlightDirective = {
  mounted(el: HTMLElement) {
    // 绔嬪嵆灏濊瘯澶勭悊涓€娆?
    processAllCodeBlocks(el)

    // 寤惰繜澶勭悊锛岀‘淇?v-html 鍐呭宸茬粡娓叉煋
    setTimeout(() => {
      retryProcessing(el)
    }, 100)

    // 浣跨敤 MutationObserver 鐩戝惉 DOM 鍙樺寲
    const observer = new MutationObserver((mutations) => {
      let hasNewCodeBlocks = false

      mutations.forEach((mutation) => {
        if (mutation.type === 'childList') {
          mutation.addedNodes.forEach((node) => {
            if (node.nodeType === Node.ELEMENT_NODE) {
              const element = node as HTMLElement
              // 妫€鏌ユ柊娣诲姞鐨勮妭鐐规槸鍚﹀寘鍚唬鐮佸潡
              if (element.tagName === 'PRE' || element.querySelector('pre code')) {
                hasNewCodeBlocks = true
              }
            }
          })
        }
      })

      if (hasNewCodeBlocks) {
        // 寤惰繜澶勭悊鏂版坊鍔犵殑浠ｇ爜鍧?
        setTimeout(() => {
          processAllCodeBlocks(el)
        }, 50)
      }
    })

    // 寮€濮嬭瀵?
    observer.observe(el, {
      childList: true,
      subtree: true
    })

    // 灏?observer 瀛樺偍鍒板厓绱犱笂锛屼互渚垮湪 unmounted 鏃舵竻鐞?
    ;(el as any)._highlightObserver = observer
  },

  updated(el: HTMLElement) {
    // 褰撶粍浠舵洿鏂版椂锛岄噸鏂板鐞嗕唬鐮佸潡
    setTimeout(() => {
      processAllCodeBlocks(el)
    }, 50)
  },

  unmounted(el: HTMLElement) {
    // 娓呯悊 MutationObserver
    const observer = (el as any)._highlightObserver
    if (observer) {
      observer.disconnect()
      delete (el as any)._highlightObserver
    }
  }
}

export function setupHighlightDirective(app: App) {
  app.directive('highlight', highlightDirective)
}

