/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */


import { useSettingStore } from '@/store/modules/setting'
import { SystemThemeEnum } from '@/enums/appEnum'
import AppConfig from '@/config'
import { SystemThemeTypes } from '@/types/store'
import { getDarkColor, getLightColor, setElementThemeColor } from '@/utils/ui'
import { usePreferredDark } from '@vueuse/core'
import { watch, type WatchStopHandle } from 'vue'

let stopAutoThemeWatcher: WatchStopHandle | null = null

function applyThemeByMode(settingStore: ReturnType<typeof useSettingStore>, prefersDark: boolean) {
  const el = document.getElementsByTagName('html')[0]
  let actualTheme = settingStore.systemThemeType

  if (settingStore.systemThemeMode === SystemThemeEnum.AUTO) {
    actualTheme = prefersDark ? SystemThemeEnum.DARK : SystemThemeEnum.LIGHT
    settingStore.systemThemeType = actualTheme
  }

  const currentTheme = AppConfig.systemThemeStyles[actualTheme as keyof SystemThemeTypes]
  if (currentTheme) {
    el.setAttribute('class', currentTheme.className)
  }

  setElementThemeColor(settingStore.systemThemeColor)
  document.documentElement.style.setProperty('--custom-radius', `${settingStore.customRadius}rem`)
}

function syncAutoThemeWatcher(settingStore: ReturnType<typeof useSettingStore>) {
  const prefersDark = usePreferredDark()

  if (stopAutoThemeWatcher) {
    stopAutoThemeWatcher()
    stopAutoThemeWatcher = null
  }

  if (settingStore.systemThemeMode === SystemThemeEnum.AUTO) {
    stopAutoThemeWatcher = watch(
      prefersDark,
      () => {
        if (settingStore.systemThemeMode === SystemThemeEnum.AUTO) {
          applyThemeByMode(settingStore, prefersDark.value)
        }
      },
      { immediate: false }
    )
  }
}

export function useTheme() {
  const settingStore = useSettingStore()

  const disableTransitions = () => {
    const style = document.createElement('style')
    style.setAttribute('id', 'disable-transitions')
    style.textContent = '* { transition: none !important; }'
    document.head.appendChild(style)
  }

  const enableTransitions = () => {
    const style = document.getElementById('disable-transitions')
    if (style) {
      style.remove()
    }
  }

  const setSystemTheme = (theme: SystemThemeEnum, themeMode?: SystemThemeEnum) => {

    disableTransitions()

    const el = document.getElementsByTagName('html')[0]
    const isDark = theme === SystemThemeEnum.DARK

    if (!themeMode) {
      themeMode = theme
    }

    const currentTheme = AppConfig.systemThemeStyles[theme as keyof SystemThemeTypes]

    if (currentTheme) {
      el.setAttribute('class', currentTheme.className)
    }

    const primary = settingStore.systemThemeColor

    for (let i = 1; i <= 9; i++) {
      document.documentElement.style.setProperty(
        `--el-color-primary-light-${i}`,
        isDark ? `${getDarkColor(primary, i / 10)}` : `${getLightColor(primary, i / 10)}`
      )
    }

    settingStore.setGlopTheme(theme, themeMode)

    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        enableTransitions()
      })
    })
  }

  const prefersDark = usePreferredDark()

  const setSystemAutoTheme = () => {
    const theme = prefersDark.value ? SystemThemeEnum.DARK : SystemThemeEnum.LIGHT
    setSystemTheme(theme, SystemThemeEnum.AUTO)
  }

  const switchThemeStyles = (theme: SystemThemeEnum) => {
    if (theme === SystemThemeEnum.AUTO) {
      setSystemAutoTheme()
    } else {
      setSystemTheme(theme)
    }
  }

  return {
    setSystemTheme,
    setSystemAutoTheme,
    switchThemeStyles,
    prefersDark
  }
}

export function initializeTheme() {
  const settingStore = useSettingStore()
  settingStore.syncPersistedScope(undefined, true)
  const prefersDark = usePreferredDark()
  applyThemeByMode(settingStore, prefersDark.value)
  syncAutoThemeWatcher(settingStore)
}

export function syncThemeScope(path?: string) {
  const settingStore = useSettingStore()
  const scopeChanged = settingStore.syncPersistedScope(path)

  if (!scopeChanged) {
    return false
  }

  const prefersDark = usePreferredDark()
  applyThemeByMode(settingStore, prefersDark.value)
  syncAutoThemeWatcher(settingStore)
  return true
}

