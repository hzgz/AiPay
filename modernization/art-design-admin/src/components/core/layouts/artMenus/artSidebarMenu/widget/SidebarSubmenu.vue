<template>
  <template v-for="(item, index) in filteredMenuItems" :key="getUniqueKey(item, index)">
    <ElSubMenu
      v-if="hasChildren(item)"
      :index="item.path || item.meta.title"
      :level="level"
      :title="formatMenuTitle(item.meta.title)"
    >
      <template #title>
        <div
          class="menu-title-wrap"
          :title="formatMenuTitle(item.meta.title)"
          @click="goPage(item, true)"
        >
          <div class="menu-icon flex-cc">
            <ArtSvgIcon
              :icon="resolveMenuIcon(item.meta.icon, item.meta.title, item.path)"
              :color="theme?.iconColor"
              :style="{ color: theme.iconColor }"
            />
          </div>
          <span class="menu-name">
            {{ formatMenuTitle(item.meta.title) }}
          </span>
          <div v-if="item.meta.showBadge" class="art-badge" style="right: 10px" />
        </div>
      </template>

      <SidebarSubmenu
        :list="item.children"
        :is-mobile="isMobile"
        :level="level + 1"
        :theme="theme"
        @close="closeMenu"
      />
    </ElSubMenu>

    <ElMenuItem
      v-else
      :index="isExternalLink(item) ? undefined : item.path || item.meta.title"
      :level-item="level + 1"
      :aria-label="formatMenuTitle(item.meta.title)"
      :title="formatMenuTitle(item.meta.title)"
      @click="goPage(item)"
    >
      <div class="menu-icon flex-cc">
        <ArtSvgIcon
          :icon="resolveMenuIcon(item.meta.icon, item.meta.title, item.path)"
          :color="theme?.iconColor"
          :style="{ color: theme.iconColor }"
        />
      </div>
      <div
        v-show="item.meta.showBadge && level === 0 && !menuOpen"
        class="art-badge"
        style="right: 5px"
      />

      <template #title>
        <span class="menu-name">
          {{ formatMenuTitle(item.meta.title) }}
        </span>
        <div v-if="item.meta.showBadge" class="art-badge" />
        <div v-if="item.meta.showTextBadge && (level > 0 || menuOpen)" class="art-text-badge">
          {{ item.meta.showTextBadge }}
        </div>
      </template>
    </ElMenuItem>
  </template>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import type { AppRouteRecord } from '@/types/router'
  import { formatMenuTitle } from '@/utils/router'
  import { handleMenuJump } from '@/utils/navigation'
  import { resolveMenuIcon } from '@/utils/ui/menuIcon'
  import { useSettingStore } from '@/store/modules/setting'

  interface MenuTheme {
    iconColor?: string
  }

  interface Props {
    
    title?: string
    
    list?: AppRouteRecord[]
    
    theme?: MenuTheme
    
    isMobile?: boolean
    
    level?: number
  }

  interface Emits {
    
    (e: 'close'): void
  }

  const props = withDefaults(defineProps<Props>(), {
    title: '',
    list: () => [],
    theme: () => ({}),
    isMobile: false,
    level: 0
  })

  const emit = defineEmits<Emits>()

  const settingStore = useSettingStore()

  const { menuOpen } = storeToRefs(settingStore)

  
  const filteredMenuItems = computed(() => filterRoutes(props.list))

  
  const goPage = (item: AppRouteRecord, jumpToFirst = false): void => {
    closeMenu()
    handleMenuJump(item, jumpToFirst)
  }

  
  const closeMenu = (): void => {
    emit('close')
  }

  
  const isNavigableRoute = (item: AppRouteRecord): boolean => {
    return !!(
      !item.meta.isHide &&
      ((item.path && item.path.trim()) || item.meta.link || item.meta.isIframe === true) &&
      (item.component || item.meta.link || item.meta.isIframe === true)
    )
  }

  
  const filterRoutes = (items: AppRouteRecord[]): AppRouteRecord[] => {
    return items
      .filter((item) => {

        if (item.meta.isHide) {
          return false
        }

        if (item.children && item.children.length > 0) {
          const filteredChildren = filterRoutes(item.children)

          return filteredChildren.length > 0 || isNavigableRoute(item)
        }

        return isNavigableRoute(item)
      })
      .map((item) => ({
        ...item,
        children: item.children ? filterRoutes(item.children) : undefined
      }))
  }

  
  const hasChildren = (item: AppRouteRecord): boolean => {
    if (!item.children || item.children.length === 0) {
      return false
    }

    const filteredChildren = filterRoutes(item.children)
    return filteredChildren.length > 0
  }

  
  const isExternalLink = (item: AppRouteRecord): boolean => {
    return !!(item.meta.link && !item.meta.isIframe)
  }

  
  const getUniqueKey = (item: AppRouteRecord, index: number): string => {
    return `${item.path || item.meta.title || 'menu'}-${props.level}-${index}`
  }
</script>

<style lang="scss" scoped>
  .menu-title-wrap {
    display: flex;
    align-items: center;
    width: 100%;
    min-width: 0;
    cursor: pointer;
  }
</style>
