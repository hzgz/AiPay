/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { fetchMerchantProfile } from '@/api/merchant'
import { clearMerchantFrontToken } from '@/utils/merchantSession'
import {
  formatMerchantAccountHint,
  formatMerchantDisplayName,
  translateMerchantText
} from '@/views/merchant/shared/text'

export const useMerchantStore = defineStore('merchantCenterStore', () => {
  const ready = ref(false)
  const loading = ref(false)
  const authenticated = ref(false)
  const profile = ref<Record<string, any> | null>(null)
  const rawProfile = ref<Record<string, any> | null>(null)
  let pendingPromise: Promise<void> | null = null

  const merchantId = computed(() => Number(profile.value?.id || 0))
  const username = computed(() => String(profile.value?.username || ''))

  const displayName = computed(() => {
    return formatMerchantDisplayName(
      profile.value?.display_name,
      profile.value?.username,
      merchantId.value
    )
  })

  const accountHint = computed(() => formatMerchantAccountHint(profile.value?.username, merchantId.value))

  const balanceDisplay = computed(() => String(profile.value?.money_display || '0.00'))
  const vipLabel = computed(() => translateMerchantText(profile.value?.vip_label || 'normal merchant'))

  function applyProfilePayload(payload: Record<string, any>) {
    rawProfile.value = payload
    profile.value = payload?.profile || null
    authenticated.value = Boolean(profile.value?.id)
    ready.value = true
  }

  function clearSession() {
    clearMerchantFrontToken()
    ready.value = true
    authenticated.value = false
    profile.value = null
    rawProfile.value = null
  }

  async function hydrate(force = false) {
    if (pendingPromise && !force) {
      return pendingPromise
    }

    if (ready.value && authenticated.value && !force) {
      return
    }

    loading.value = true
    pendingPromise = (async () => {
      try {
        const payload = await fetchMerchantProfile()
        applyProfilePayload(payload)
      } catch (error) {
        clearSession()
        throw error
      } finally {
        loading.value = false
        pendingPromise = null
      }
    })()

    return pendingPromise
  }

  return {
    ready,
    loading,
    authenticated,
    profile,
    rawProfile,
    merchantId,
    username,
    displayName,
    accountHint,
    balanceDisplay,
    vipLabel,
    hydrate,
    applyProfilePayload,
    clearSession
  }
})
