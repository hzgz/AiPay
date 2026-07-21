/**
 * 棰滆壊澶勭悊宸ュ叿妯″潡
 *
 * 鎻愪緵瀹屾暣鐨勯鑹叉牸寮忚浆鎹㈠拰澶勭悊鍔熻兘
 *
 * ## 涓昏鍔熻兘
 *
 * - Hex 涓?RGB/RGBA 鏍煎紡浜掕浆
 * - 棰滆壊娣峰悎璁＄畻
 * - 棰滆壊鍙樻祬/鍙樻繁澶勭悊
 * - Element Plus 涓婚鑹茶嚜鍔ㄧ敓鎴?
 * - 棰滆壊鏍煎紡楠岃瘉
 * - CSS 鍙橀噺璇诲彇
 * - 鏆楅粦妯″紡棰滆壊閫傞厤
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 涓婚鑹插姩鎬佸垏鎹?
 * - Element Plus 缁勪欢涓婚瀹氬埗
 * - 棰滆壊娓愬彉鐢熸垚
 * - 鏄庢殫涓婚棰滆壊璁＄畻
 * - 棰滆壊鏍煎紡鏍囧噯鍖?
 *
 * ## 鏍稿績鍔熻兘
 *
 * - hexToRgba: Hex 杞?RGBA锛堟敮鎸侀€忔槑搴︼級
 * - hexToRgb: Hex 杞?RGB 鏁扮粍
 * - rgbToHex: RGB 杞?Hex
 * - colourBlend: 涓ょ棰滆壊娣峰悎
 * - getLightColor: 鐢熸垚鍙樻祬鐨勯鑹?
 * - getDarkColor: 鐢熸垚鍙樻繁鐨勯鑹?
 * - handleElementThemeColor: 澶勭悊 Element Plus 涓婚鑹?
 * - setElementThemeColor: 璁剧疆瀹屾暣鐨勪富棰樿壊绯荤粺
 *
 * ## 鏀寔鏍煎紡
 *
 * - Hex: #FFF, #FFFFFF
 * - RGB: rgb(255, 255, 255)
 * - RGBA: rgba(255, 255, 255, 0.5)
 *
 * @module utils/ui/colors
 * @author AiPay
 */
import { useSettingStore } from '@/store/modules/setting'

/**
 * 棰滆壊杞崲缁撴灉鎺ュ彛
 */
interface RgbaResult {
  red: number
  green: number
  blue: number
  rgba: string
}

/**
 * 鑾峰彇CSS鍙橀噺鍊硷紙鍒悕鍑芥暟锛?
 * @param name CSS鍙橀噺鍚?
 * @returns CSS鍙橀噺鍊?
 */
export function getCssVar(name: string): string {
  return getComputedStyle(document.documentElement).getPropertyValue(name)
}

/**
 * 楠岃瘉hex棰滆壊鏍煎紡
 * @param hex hex棰滆壊鍊?
 * @returns 鏄惁涓烘湁鏁堢殑hex棰滆壊
 */
function isValidHexColor(hex: string): boolean {
  const cleanHex = hex.trim().replace(/^#/, '')
  return /^[0-9A-Fa-f]{3}$|^[0-9A-Fa-f]{6}$/.test(cleanHex)
}

/**
 * 楠岃瘉RGB棰滆壊鍊?
 * @param r 绾㈣壊鍊?
 * @param g 缁胯壊鍊?
 * @param b 钃濊壊鍊?
 * @returns 鏄惁涓烘湁鏁堢殑RGB鍊?
 */
function isValidRgbValue(r: number, g: number, b: number): boolean {
  const isValid = (value: number) => Number.isInteger(value) && value >= 0 && value <= 255
  return isValid(r) && isValid(g) && isValid(b)
}

/**
 * 灏唄ex棰滆壊杞崲涓篟GBA
 * @param hex hex棰滆壊鍊?(鏀寔 #FFF 鎴?#FFFFFF 鏍煎紡)
 * @param opacity 閫忔槑搴?(0-1)
 * @returns 鍖呭惈RGB鍊煎拰RGBA瀛楃涓茬殑瀵硅薄
 */
export function hexToRgba(hex: string, opacity: number): RgbaResult {
  if (!isValidHexColor(hex)) {
    throw new Error('Invalid hex color format')
  }

  // 绉婚櫎鍙兘瀛樺湪鐨?# 鍓嶇紑骞惰浆鎹负澶у啓
  let cleanHex = hex.trim().replace(/^#/, '').toUpperCase()

  // 濡傛灉鏄缉鍐欏舰寮忥紙濡?FFF锛夛紝杞崲涓哄畬鏁村舰寮?
  if (cleanHex.length === 3) {
    cleanHex = cleanHex
      .split('')
      .map((char) => char.repeat(2))
      .join('')
  }

  // 瑙ｆ瀽 RGB 鍊?
  const [red, green, blue] = cleanHex.match(/\w\w/g)!.map((x) => parseInt(x, 16))

  // 纭繚 opacity 鍦ㄦ湁鏁堣寖鍥村唴
  const validOpacity = Math.max(0, Math.min(1, opacity))

  // 鏋勫缓 RGBA 瀛楃涓?
  const rgba = `rgba(${red}, ${green}, ${blue}, ${validOpacity.toFixed(2)})`

  return { red, green, blue, rgba }
}

/**
 * 灏唄ex棰滆壊杞崲涓篟GB鏁扮粍
 * @param hexColor hex棰滆壊鍊?
 * @returns RGB鏁扮粍 [r, g, b]
 */
export function hexToRgb(hexColor: string): number[] {
  if (!isValidHexColor(hexColor)) {
    ElMessage.warning('输入的 Hex 颜色值无效')
    throw new Error('Invalid hex color format')
  }

  const cleanHex = hexColor.replace(/^#/, '')
  let hex = cleanHex

  // 澶勭悊缂╁啓褰㈠紡
  if (hex.length === 3) {
    hex = hex
      .split('')
      .map((char) => char.repeat(2))
      .join('')
  }

  const hexPairs = hex.match(/../g)
  if (!hexPairs) {
    throw new Error('Invalid hex color format')
  }

  return hexPairs.map((hexPair) => parseInt(hexPair, 16))
}

/**
 * 灏哛GB棰滆壊杞崲涓篽ex
 * @param r 绾㈣壊鍊?(0-255)
 * @param g 缁胯壊鍊?(0-255)
 * @param b 钃濊壊鍊?(0-255)
 * @returns hex棰滆壊鍊?
 */
export function rgbToHex(r: number, g: number, b: number): string {
  if (!isValidRgbValue(r, g, b)) {
    ElMessage.warning('输入的 RGB 颜色值无效')
    throw new Error('Invalid RGB color values')
  }

  const toHex = (value: number) => {
    const hex = value.toString(16)
    return hex.length === 1 ? `0${hex}` : hex
  }

  return `#${toHex(r)}${toHex(g)}${toHex(b)}`
}

/**
 * 棰滆壊娣峰悎
 * @param color1 绗竴涓鑹?
 * @param color2 绗簩涓鑹?
 * @param ratio 娣峰悎姣斾緥 (0-1)
 * @returns 娣峰悎鍚庣殑棰滆壊
 */
export function colourBlend(color1: string, color2: string, ratio: number): string {
  const validRatio = Math.max(0, Math.min(1, Number(ratio)))

  const rgb1 = hexToRgb(color1)
  const rgb2 = hexToRgb(color2)

  const blendedRgb = rgb1.map((value1, index) => {
    const value2 = rgb2[index]
    return Math.round(value1 * (1 - validRatio) + value2 * validRatio)
  })

  return rgbToHex(blendedRgb[0], blendedRgb[1], blendedRgb[2])
}

/**
 * 鑾峰彇鍙樻祬鐨勯鑹?
 * @param color 鍘熷棰滆壊
 * @param level 鍙樻祬绋嬪害 (0-1)
 * @param isDark 鏄惁涓烘殫鑹蹭富棰?
 * @returns 鍙樻祬鍚庣殑棰滆壊
 */
export function getLightColor(color: string, level: number, isDark: boolean = false): string {
  if (!isValidHexColor(color)) {
    ElMessage.warning('输入的 Hex 颜色值无效')
    throw new Error('Invalid hex color format')
  }

  if (isDark) {
    return getDarkColor(color, level)
  }

  const rgb = hexToRgb(color)
  const lightRgb = rgb.map((value) => Math.floor((255 - value) * level + value))

  return rgbToHex(lightRgb[0], lightRgb[1], lightRgb[2])
}

/**
 * 鑾峰彇鍙樻繁鐨勯鑹?
 * @param color 鍘熷棰滆壊
 * @param level 鍙樻繁绋嬪害 (0-1)
 * @returns 鍙樻繁鍚庣殑棰滆壊
 */
export function getDarkColor(color: string, level: number): string {
  if (!isValidHexColor(color)) {
    ElMessage.warning('输入的 Hex 颜色值无效')
    throw new Error('Invalid hex color format')
  }

  const rgb = hexToRgb(color)
  const darkRgb = rgb.map((value) => Math.floor(value * (1 - level)))

  return rgbToHex(darkRgb[0], darkRgb[1], darkRgb[2])
}

/**
 * 澶勭悊 Element Plus 涓婚棰滆壊
 * @param theme 涓婚棰滆壊
 * @param isDark 鏄惁涓烘殫鑹蹭富棰?
 */
export function handleElementThemeColor(theme: string, isDark: boolean = false): void {
  document.documentElement.style.setProperty('--el-color-primary', theme)

  for (let i = 1; i <= 9; i++) {
    document.documentElement.style.setProperty(
      `--el-color-primary-light-${i}`,
      getLightColor(theme, i / 10, isDark)
    )
  }

  for (let i = 1; i <= 9; i++) {
    document.documentElement.style.setProperty(
      `--el-color-primary-dark-${i}`,
      getDarkColor(theme, i / 10)
    )
  }
}

/**
 * 璁剧疆 Element Plus 涓婚棰滆壊
 * @param color 涓婚棰滆壊
 */
export function setElementThemeColor(color: string): void {
  const mixColor = '#ffffff'
  const elStyle = document.documentElement.style

  elStyle.setProperty('--el-color-primary', color)
  handleElementThemeColor(color, useSettingStore().isDark)

  // 鐢熸垚鏇存贰涓€鐐圭殑棰滆壊
  for (let i = 1; i < 16; i++) {
    const itemColor = colourBlend(color, mixColor, i / 16)
    elStyle.setProperty(`--el-color-primary-custom-${i}`, itemColor)
  }
}

