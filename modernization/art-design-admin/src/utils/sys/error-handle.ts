/**
 * 鍏ㄥ眬閿欒澶勭悊妯″潡
 *
 * 鎻愪緵缁熶竴鐨勯敊璇崟鑾峰拰澶勭悊鏈哄埗
 *
 * ## 涓昏鍔熻兘
 *
 * - Vue 杩愯鏃堕敊璇崟鑾凤紙缁勪欢閿欒銆佺敓鍛藉懆鏈熼敊璇瓑锛?
 * - 鍏ㄥ眬鑴氭湰閿欒鎹曡幏锛堣娉曢敊璇€佽繍琛屾椂閿欒绛夛級
 * - Promise 鏈崟鑾烽敊璇鐞嗭紙unhandledrejection锛?
 * - 闈欐€佽祫婧愬姞杞介敊璇洃鎺э紙鍥剧墖銆佽剼鏈€佹牱寮忕瓑锛?
 * - 閿欒鏃ュ織璁板綍鍜屼笂鎶?
 * - 缁熶竴鐨勯敊璇鐞嗗叆鍙?
 *
 * ## 浣跨敤鍦烘櫙
 * - 搴旂敤鍚姩鏃跺畨瑁呭叏灞€閿欒澶勭悊鍣?
 * - 鎹曡幏鍜岃褰曟墍鏈夌被鍨嬬殑閿欒
 * - 閿欒涓婃姤鍒扮洃鎺у钩鍙?
 * - 鎻愬崌搴旂敤绋冲畾鎬у拰鍙淮鎶ゆ€?
 * - 闂鎺掓煡鍜岃皟璇?
 *
 * ## 閿欒绫诲瀷
 *
 * - VueError: Vue 缁勪欢鐩稿叧閿欒
 * - ScriptError: JavaScript 鑴氭湰閿欒
 * - PromiseError: Promise 鏈崟鑾风殑 rejection
 * - ResourceError: 闈欐€佽祫婧愬姞杞藉け璐?
 *
 * @module utils/sys/error-handle
 * @author AiPay
 */
import type { App } from 'vue'

const IGNORABLE_SCRIPT_ERRORS = [
  'ResizeObserver loop completed with undelivered notifications.',
  'ResizeObserver loop limit exceeded'
]

function normalizeErrorMessage(message: Event | string): string {
  if (typeof message === 'string') {
    return message
  }

  if ('message' in message && typeof message.message === 'string') {
    return message.message
  }

  return ''
}

function isIgnorableScriptError(message: Event | string, source?: string): boolean {
  const normalizedMessage = normalizeErrorMessage(message)

  if (!normalizedMessage) {
    return false
  }

  if (IGNORABLE_SCRIPT_ERRORS.some((item) => normalizedMessage.includes(item))) {
    // 娴忚鍣?鎵╁睍鍦ㄥ竷灞€鎶栧姩鏃跺父瑙佺殑 ResizeObserver 鍣０锛屼笉浣滀负鐪熷疄寮傚父澶勭悊
    return true
  }

  // 娴忚鍣ㄦ墿灞曟敞鍏ヨ剼鏈伓鍙戠殑璺ㄥ煙 Script error 涔熸病鏈夋帓鏌ヤ环鍊?
  if (normalizedMessage === 'Script error.' && source === '') {
    return true
  }

  return false
}

/**
 * Vue 杩愯鏃堕敊璇鐞?
 */
export function vueErrorHandler(err: unknown, instance: any, info: string) {
  console.error('[VueError]', err, info, instance)
  // 杩欓噷鍙互涓婃姤鍒版湇鍔＄锛屾瘮濡傦細
  // reportError({ type: 'vue', err, info })
}

/**
 * 鍏ㄥ眬鑴氭湰閿欒澶勭悊
 */
export function scriptErrorHandler(
  message: Event | string,
  source?: string,
  lineno?: number,
  colno?: number,
  error?: Error
): boolean {
  if (isIgnorableScriptError(message, source)) {
    return true
  }

  console.error('[ScriptError]', { message, source, lineno, colno, error })
  // reportError({ type: 'script', message, source, lineno, colno, error })
  return true // 闃绘榛樿鎺у埗鍙版姤閿欙紝鍙牴鎹渶姹傛敼
}

/**
 * Promise 鏈崟鑾烽敊璇鐞?
 */
export function registerPromiseErrorHandler() {
  window.addEventListener('unhandledrejection', (event) => {
    console.error('[PromiseError]', event.reason)
    // reportError({ type: 'promise', reason: event.reason })
  })
}

/**
 * 璧勬簮鍔犺浇閿欒澶勭悊 (img, script, css...)
 */
export function registerResourceErrorHandler() {
  window.addEventListener(
    'error',
    (event: Event) => {
      const target = event.target as HTMLElement
      if (
        target &&
        (target.tagName === 'IMG' || target.tagName === 'SCRIPT' || target.tagName === 'LINK')
      ) {
        console.error('[ResourceError]', {
          tagName: target.tagName,
          src:
            (target as HTMLImageElement).src ||
            (target as HTMLScriptElement).src ||
            (target as HTMLLinkElement).href
        })
        // reportError({ type: 'resource', target })
      }
    },
    true // 鎹曡幏闃舵鎵嶈兘鐩戝惉鍒拌祫婧愰敊璇?
  )
}

/**
 * 瀹夎缁熶竴閿欒澶勭悊
 */
export function setupErrorHandle(app: App) {
  app.config.errorHandler = vueErrorHandler
  window.onerror = scriptErrorHandler
  registerPromiseErrorHandler()
  registerResourceErrorHandler()
}

