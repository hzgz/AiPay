<template>
  <ElConfigProvider
    size="default"
    :locale="locales[language]"
    :z-index="3000"
    :card="{
      shadow: 'never'
    }"
  >
    <RouterView v-slot="{ Component, route }">
      <component :is="Component" :key="resolveRouterViewKey(route)" />
    </RouterView>
  </ElConfigProvider>
</template>

<script setup lang="ts">
  import type { RouteLocationNormalizedLoaded } from 'vue-router'
  import { useUserStore } from './store/modules/user'
  import zh from 'element-plus/es/locale/lang/zh-cn'
  import en from 'element-plus/es/locale/lang/en'
  import { toggleTransition } from './utils/ui/animation'
  import { checkStorageCompatibility } from './utils/storage'
  import { initializeTheme } from './hooks/core/useTheme'

  const userStore = useUserStore()
  const { language } = storeToRefs(userStore)

  const locales = {
    zh: zh,
    en: en
  }

  onBeforeMount(() => {
    toggleTransition(true)
    initializeTheme()
  })

  onMounted(() => {
    checkStorageCompatibility()
    toggleTransition(false)
  })

  function resolveRouterViewKey(route: RouteLocationNormalizedLoaded) {
    const path = route.path || ''
    if (
      route.meta?.publicLanding ||
      path.startsWith('/auth/') ||
      path === '/merchant/login' ||
      path === '/merchant/register'
    ) {
      return route.fullPath
    }

    return undefined
  }
</script>
