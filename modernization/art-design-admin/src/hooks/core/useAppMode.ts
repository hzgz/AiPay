/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */


import { computed } from 'vue'

export function useAppMode() {

  const accessMode = import.meta.env.VITE_ACCESS_MODE

  
  const isFrontendMode = computed(() => accessMode === 'frontend')
  
  const isBackendMode = computed(() => accessMode === 'backend')

  
  const currentMode = computed(() => accessMode)

  return {
    isFrontendMode,
    isBackendMode,
    currentMode
  }
}

