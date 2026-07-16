<template>
  <div class="merchant-shell" :class="{ 'merchant-shell--collapsed': sidebarCollapsed }">
    <aside class="merchant-sidebar layout-sidebar">
      <div
        class="menu-left menu-left-light"
        :class="sidebarCollapsed ? 'menu-left-close' : 'menu-left-open'"
      >
        <div class="merchant-sidebar__brand header" @click="goHome">
          <ArtLogo class="logo" />
          <div class="merchant-sidebar__brand-copy">
            <strong>AiPay 商户中心</strong>
          </div>

          <ArtIconButton
            icon="ri:menu-2-fill"
            class="merchant-sidebar__toggle"
            @click.stop="drawerVisible = true"
          />
        </div>

        <ElScrollbar
          v-if="sidebarCollapsed && responsiveMode !== 'drawer'"
          class="merchant-sidebar__scroll merchant-sidebar__scroll--rail"
        >
          <div class="merchant-rail">
            <button
              v-for="section in menuSections"
              :key="section.key"
              type="button"
              class="merchant-rail__item"
              :class="{ 'merchant-rail__item--active': activeSectionKey === section.key }"
              :title="section.title"
              @click="expandSidebarSection(section.key)"
            >
              <div class="menu-icon flex-cc">
                <ArtSvgIcon :icon="section.icon" />
              </div>
            </button>
          </div>
        </ElScrollbar>

        <ElScrollbar v-else class="merchant-sidebar__scroll">
          <ElMenu
            ref="desktopMenuRef"
            class="merchant-menu el-menu-light"
            :default-active="route.path"
            :unique-opened="true"
            :show-timeout="50"
            :hide-timeout="50"
            @select="handleMenuSelect"
          >
            <ElSubMenu
              v-for="section in menuSections"
              :key="section.key"
              :index="section.key"
              popper-class="menu-left-popper"
            >
              <template #title>
                <div class="menu-title-wrap">
                  <div class="menu-icon flex-cc">
                    <ArtSvgIcon :icon="section.icon" />
                  </div>
                  <span class="menu-name">{{ section.title }}</span>
                </div>
              </template>

              <ElMenuItem v-for="item in section.items" :key="item.path" :index="item.path">
                <div class="menu-icon flex-cc">
                  <ArtSvgIcon :icon="item.icon" />
                </div>

                <template #title>
                  <span class="menu-name">{{ item.title }}</span>
                </template>
              </ElMenuItem>
            </ElSubMenu>
          </ElMenu>
        </ElScrollbar>
      </div>
    </aside>

    <main class="merchant-main">
      <header class="merchant-topbar">
        <div class="merchant-topbar__left">
          <ArtIconButton
            icon="ri:menu-2-fill"
            class="merchant-topbar__tool"
            @click="handleMenuTrigger"
          />
          <ArtIconButton
            icon="ri:refresh-line"
            class="merchant-topbar__tool merchant-topbar__tool--refresh"
            @click="reloadPage"
          />
          <ArtIconButton
            icon="ri:function-line"
            class="merchant-topbar__tool merchant-topbar__tool--quick"
            @click="openQuickSearch"
          />

          <div class="merchant-topbar__breadcrumb">
            <ArtBreadcrumb />
          </div>

          <div class="merchant-topbar__fallback-title">
            <span>{{ currentSectionTitle }}</span>
            <strong>{{ currentTitle }}</strong>
          </div>
        </div>

        <div class="merchant-topbar__right">
          <button type="button" class="merchant-topbar__search" @click="openQuickSearch">
            <div class="merchant-topbar__search-label">
              <ArtSvgIcon icon="ri:search-line" />
              <span>搜索菜单</span>
            </div>
            <div class="merchant-topbar__search-shortcut">
              <span>快捷</span>
            </div>
          </button>

          <ArtIconButton
            :icon="isFullscreen ? 'ri:fullscreen-exit-line' : 'ri:fullscreen-line'"
            class="merchant-topbar__tool merchant-topbar__tool--desktop"
            @click="toggleFullScreen"
          />
          <ArtIconButton
            icon="ri:notification-2-line"
            class="merchant-topbar__tool merchant-topbar__tool--desktop"
            @click="navigateTo('/merchant/notifications')"
          />
          <ArtIconButton
            icon="ri:user-settings-line"
            class="merchant-topbar__tool merchant-topbar__tool--desktop"
            @click="navigateTo('/merchant/profile')"
          />
          <ArtIconButton
            :icon="isDark ? 'ri:sun-fill' : 'ri:moon-line'"
            class="merchant-topbar__tool merchant-topbar__tool--desktop"
            @click="themeAnimation($event)"
          />

          <ElDropdown @command="handleHeaderCommand">
            <button type="button" class="merchant-topbar__merchant-trigger">
              <span class="merchant-topbar__avatar">{{ merchantInitial }}</span>
              <div class="merchant-topbar__merchant-copy">
                <strong>{{ merchantStore.displayName }}</strong>
                <span>{{ merchantMetaLine }}</span>
              </div>
              <Icon icon="ri:arrow-down-s-line" class="merchant-topbar__merchant-arrow" />
            </button>

            <template #dropdown>
              <ElDropdownMenu>
                <ElDropdownItem command="home">返回看板</ElDropdownItem>
                <ElDropdownItem command="profile">账号设置</ElDropdownItem>
                <ElDropdownItem command="refresh">刷新页面</ElDropdownItem>
                <ElDropdownItem command="logout">退出登录</ElDropdownItem>
              </ElDropdownMenu>
            </template>
          </ElDropdown>
        </div>
      </header>

      <section class="merchant-content">
        <div v-if="authLoading" class="merchant-panel merchant-state-card">
          <ElSkeleton :rows="6" animated />
        </div>
        <RouterView v-else v-slot="{ Component, route: childRoute }">
          <Suspense :key="childRoute.fullPath" timeout="0">
            <component :is="Component" :key="childRoute.fullPath" />

            <template #fallback>
              <div class="merchant-panel merchant-state-card merchant-route-skeleton">
                <ElSkeleton :rows="6" animated />
              </div>
            </template>
          </Suspense>
        </RouterView>
      </section>
    </main>

    <ElDrawer v-model="drawerVisible" direction="ltr" size="86%" class="merchant-drawer">
      <template #header>
        <div class="merchant-drawer__header">
          <ArtLogo size="30" />
          <div class="merchant-drawer__header-copy">
            <strong>AiPay 商户中心</strong>
          </div>
        </div>
      </template>

      <ElMenu
        ref="drawerMenuRef"
        class="merchant-menu merchant-menu--drawer el-menu-light"
        :default-active="route.path"
        :unique-opened="true"
        :show-timeout="50"
        :hide-timeout="50"
        @select="handleMenuSelect"
      >
        <ElSubMenu
          v-for="section in menuSections"
          :key="section.key"
          :index="section.key"
          popper-class="menu-left-popper"
        >
          <template #title>
            <div class="menu-title-wrap">
              <div class="menu-icon flex-cc">
                <ArtSvgIcon :icon="section.icon" />
              </div>
              <span class="menu-name">{{ section.title }}</span>
            </div>
          </template>

          <ElMenuItem v-for="item in section.items" :key="item.path" :index="item.path">
            <div class="menu-icon flex-cc">
              <ArtSvgIcon :icon="item.icon" />
            </div>

            <template #title>
              <span class="menu-name">{{ item.title }}</span>
            </template>
          </ElMenuItem>
        </ElSubMenu>
      </ElMenu>
    </ElDrawer>

    <ElDialog
      v-model="searchDialogVisible"
      width="560px"
      align-center
      destroy-on-close
      class="merchant-search-dialog"
    >
      <template #header>
        <div class="merchant-search-dialog__header">
          <strong>快捷访问</strong>
        </div>
      </template>

      <ElInput
        ref="quickSearchInputRef"
        v-model="searchKeyword"
        size="large"
        clearable
        placeholder="搜索菜单"
      >
        <template #prefix>
          <ArtSvgIcon icon="ri:search-line" />
        </template>
      </ElInput>

      <ElScrollbar max-height="360px" class="merchant-search-dialog__scroll">
        <button
          v-for="item in filteredSearchItems"
          :key="item.path"
          type="button"
          class="merchant-search-dialog__item"
          @click="handleQuickSearchSelect(item.path)"
        >
          <div class="merchant-search-dialog__item-copy">
            <strong>{{ item.title }}</strong>
          </div>
          <span class="merchant-search-dialog__item-meta">{{ item.sectionTitle }}</span>
        </button>

        <div v-if="filteredSearchItems.length === 0" class="merchant-search-dialog__empty">
          未找到匹配菜单。
        </div>
      </ElScrollbar>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { Icon } from '@iconify/vue'
  import { useFullscreen, useWindowSize } from '@vueuse/core'
  import { ElMessage } from 'element-plus'
  import { merchantLogout, isMerchantUnauthorized, MerchantApiError } from '@/api/merchant'
  import { useMerchantStore } from '@/store/modules/merchant'
  import { useSettingStore } from '@/store/modules/setting'
  import { themeAnimation } from '@/utils/ui/animation'
  import { merchantNavSections, findMerchantNavItem } from '../shared/navigation'
  import { translateMerchantText } from '../shared/text'

  defineOptions({ name: 'MerchantLayout' })

  const route = useRoute()
  const router = useRouter()
  const merchantStore = useMerchantStore()
  const settingStore = useSettingStore()
  const { isDark } = storeToRefs(settingStore)
  const { width } = useWindowSize()
  const { isFullscreen, toggle: toggleFullscreen } = useFullscreen()

  const authLoading = ref(true)
  const drawerVisible = ref(false)
  const sidebarCollapsed = ref(false)
  const searchDialogVisible = ref(false)
  const searchKeyword = ref('')
  type MerchantMenuController = {
    close?: (index: string) => void
    open?: (index: string) => void
  }

  const desktopMenuRef = ref<MerchantMenuController | null>(null)
  const drawerMenuRef = ref<MerchantMenuController | null>(null)
  const quickSearchInputRef = ref<{ focus?: () => void } | null>(null)
  const DRAWER_BREAKPOINT = 760
  const COMPACT_BREAKPOINT = 1120
  const responsiveMode = ref<'compact' | 'desktop' | 'drawer'>('desktop')

  const sectionIcons = [
    'ri:dashboard-line',
    'ri:user-settings-line',
    'ri:line-chart-line',
    'ri:customer-service-2-line'
  ]

  const menuSections = computed(() =>
    merchantNavSections.map((section, index) => ({
      ...section,
      key: `section-${index}`,
      icon: sectionIcons[index] || 'ri:apps-2-line'
    }))
  )

  const searchItems = computed(() =>
    menuSections.value.flatMap((section) =>
      section.items.map((item) => ({
        ...item,
        sectionTitle: section.title
      }))
    )
  )

  const filteredSearchItems = computed(() => {
    const keyword = searchKeyword.value.trim().toLowerCase()

    if (keyword === '') {
      return searchItems.value
    }

    return searchItems.value.filter((item) =>
      [item.title, item.sectionTitle].some((field) =>
        field.toLowerCase().includes(keyword)
      )
    )
  })

  const currentItem = computed(() => findMerchantNavItem(route.path))
  const activeSectionKey = computed(() => findActiveSectionKey(route.path))
  const currentSectionTitle = computed(() => {
    const matchedSection = merchantNavSections.find((section) =>
      section.items.some((item) => item.path === route.path)
    )

    return matchedSection?.title || '商户中心'
  })

  const currentTitle = computed(() =>
    String(route.meta.title || currentItem.value?.title || '商户中心')
  )

  const merchantInitial = computed(() => {
    const source = String(merchantStore.displayName || merchantStore.username || 'M').trim()
    return source === '' ? 'M' : source.slice(0, 1).toUpperCase()
  })

  const merchantMetaLine = computed(() => {
    const accountHint = String(merchantStore.accountHint || '').replace(/^登录账号：/, '账号：')
    const vipLabel = String(merchantStore.vipLabel || '').trim()

    return [accountHint, vipLabel].filter((item) => item !== '').join(' / ')
  })

  function collapseAllMenuSections() {
    const sectionKeys = menuSections.value.map((section) => section.key)

    sectionKeys.forEach((key) => {
      desktopMenuRef.value?.close?.(key)
      drawerMenuRef.value?.close?.(key)
    })
  }

  function findActiveSectionKey(path = route.path) {
    const matchedSection = menuSections.value.find((section) =>
      section.items.some((item) => item.path === path)
    )

    return matchedSection?.key || ''
  }

  function syncExpandedSection(path = route.path) {
    const activeSectionKey = findActiveSectionKey(path)

    collapseAllMenuSections()

    if (activeSectionKey === '') {
      return
    }

    if (!sidebarCollapsed.value && responsiveMode.value !== 'drawer') {
      desktopMenuRef.value?.open?.(activeSectionKey)
    }

    if (drawerVisible.value) {
      drawerMenuRef.value?.open?.(activeSectionKey)
    }
  }

  function applyResponsiveLayout(nextWidth: number) {
    const nextMode =
      nextWidth <= DRAWER_BREAKPOINT
        ? 'drawer'
        : nextWidth <= COMPACT_BREAKPOINT
          ? 'compact'
          : 'desktop'

    if (nextMode === responsiveMode.value) {
      return
    }

    responsiveMode.value = nextMode

    if (nextMode === 'drawer') {
      drawerVisible.value = false
      sidebarCollapsed.value = false
      return
    }

    drawerVisible.value = false

    if (nextMode === 'compact') {
      sidebarCollapsed.value = true
      return
    }

    sidebarCollapsed.value = false
  }

  async function ensureMerchantSession() {
    authLoading.value = true
    try {
      await merchantStore.hydrate()
      if (!merchantStore.authenticated) {
        await router.replace({
          name: 'MerchantLogin',
          query: {
            redirect: route.fullPath
          }
        })
      }
    } catch (error) {
      if (isMerchantUnauthorized(error)) {
        await router.replace({
          name: 'MerchantLogin',
          query: {
            redirect: route.fullPath
          }
        })
      } else {
        const message =
          error instanceof MerchantApiError
            ? translateMerchantText(error.message, error.message)
            : '商户会话校验失败，请重新登录'
        ElMessage.error(message)
      }
    } finally {
      authLoading.value = false
    }
  }

  async function handleLogout() {
    try {
      await merchantLogout()
    } catch {
      // Ignore logout redirect failures and clear the local state anyway.
    } finally {
      merchantStore.clearSession()
      await router.replace({ name: 'MerchantLogin' })
    }
  }

  async function goHome() {
    await router.push('/merchant/dashboard')
  }

  async function navigateTo(path: string) {
    if (path !== route.path) {
      await router.push(path)
    }
  }

  function handleMenuTrigger() {
    if (width.value <= DRAWER_BREAKPOINT) {
      drawerVisible.value = true
      return
    }

    sidebarCollapsed.value = !sidebarCollapsed.value

    if (!sidebarCollapsed.value) {
      nextTick(() => {
        syncExpandedSection(route.path)
      })
    }
  }

  function expandSidebarSection(sectionKey: string) {
    sidebarCollapsed.value = false

    nextTick(() => {
      collapseAllMenuSections()
      desktopMenuRef.value?.open?.(sectionKey)
    })
  }

  function reloadPage() {
    window.location.reload()
  }

  function openQuickSearch() {
    searchDialogVisible.value = true
    nextTick(() => {
      quickSearchInputRef.value?.focus?.()
    })
  }

  function toggleFullScreen() {
    toggleFullscreen()
  }

  async function handleMenuSelect(index: string) {
    if (!index.startsWith('/')) {
      return
    }

    drawerVisible.value = false
    searchDialogVisible.value = false
    if (index !== route.path) {
      await router.push(index)
    }
  }

  async function handleQuickSearchSelect(path: string) {
    searchKeyword.value = ''
    await handleMenuSelect(path)
  }

  async function handleHeaderCommand(command: string) {
    if (command === 'home') {
      await goHome()
      return
    }

    if (command === 'profile') {
      await navigateTo('/merchant/profile')
      return
    }

    if (command === 'refresh') {
      reloadPage()
      return
    }

    if (command === 'logout') {
      await handleLogout()
    }
  }

  function handleWindowKeydown(event: KeyboardEvent) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault()
      openQuickSearch()
    }
  }

  watch(
    () => route.fullPath,
    () => {
      drawerVisible.value = false
      searchDialogVisible.value = false
      nextTick(() => {
        syncExpandedSection(route.path)
      })
    }
  )

  watch(
    width,
    (nextWidth) => {
      applyResponsiveLayout(nextWidth)
    },
    { immediate: true }
  )

  watch(drawerVisible, (visible) => {
    if (!visible) {
      return
    }

    nextTick(() => {
      syncExpandedSection(route.path)
    })
  })

  onMounted(() => {
    ensureMerchantSession()
    nextTick(() => {
      syncExpandedSection(route.path)
    })
    window.addEventListener('keydown', handleWindowKeydown)
  })

  onUnmounted(() => {
    window.removeEventListener('keydown', handleWindowKeydown)
  })
</script>

<style lang="scss">
  @use '../styles';
  @use '@/components/core/layouts/art-menus/art-sidebar-menu/style.scss';
  @use '@/components/core/layouts/art-menus/art-sidebar-menu/theme.scss';
</style>

<style lang="scss" scoped>
  .merchant-shell {
    display: grid;
    grid-template-columns: var(--merchant-sidebar-width) minmax(0, 1fr);
    min-height: 100vh;
    transition: grid-template-columns 0.24s ease;
  }

  .merchant-shell--collapsed {
    grid-template-columns: 76px minmax(0, 1fr);
  }

  .merchant-sidebar {
    min-height: 100vh;
    background: var(--merchant-panel-bg);
    border-right: 1px solid var(--merchant-soft-border);
  }

  .merchant-sidebar .menu-left {
    width: 100%;
    height: 100vh;
    background: var(--merchant-panel-bg);
  }

  .merchant-sidebar__brand {
    position: relative;
    display: flex;
    gap: 10px;
    align-items: center;
    min-height: 60px;
    background: var(--merchant-panel-bg);
    border-bottom: 1px solid var(--merchant-soft-border);
  }

  .merchant-sidebar__brand :deep(.logo) {
    cursor: pointer;
  }

  .merchant-sidebar__brand-copy {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
    transition:
      opacity 0.2s ease,
      transform 0.2s ease,
      width 0.2s ease;
  }

  .merchant-sidebar__brand-copy strong {
    color: var(--merchant-heading-color);
    font-size: 14px;
    font-weight: 700;
    line-height: 1.2;
    white-space: nowrap;
  }

  .merchant-sidebar__brand-copy span {
    color: var(--merchant-muted);
    font-size: 10px;
    line-height: 1.2;
    white-space: nowrap;
  }

  .merchant-shell--collapsed .merchant-sidebar__brand-copy {
    width: 0;
    overflow: hidden;
    opacity: 0;
    transform: translateX(-8px);
  }

  .merchant-sidebar__toggle {
    position: absolute;
    top: 10px;
    right: 12px;
    display: none;
  }

  .merchant-sidebar__scroll {
    height: calc(100vh - 60px);
  }

  .merchant-sidebar__scroll--rail {
    padding-top: 10px;
  }

  .merchant-rail {
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: center;
    padding: 0 10px 18px;
  }

  .merchant-rail__item {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    color: var(--merchant-muted);
    background: transparent;
    border: 1px solid transparent;
    border-radius: 14px;
    cursor: pointer;
    transition:
      border-color 0.2s ease,
      background-color 0.2s ease,
      box-shadow 0.2s ease,
      transform 0.2s ease;
  }

  .merchant-rail__item:hover {
    border-color: rgb(86 119 255 / 16%);
    background: color-mix(in srgb, var(--merchant-panel-bg) 88%, #fff);
    box-shadow: 0 10px 24px rgb(86 119 255 / 10%);
    transform: translateY(-1px);
  }

  .merchant-rail__item--active {
    color: var(--main-color);
    background: color-mix(in srgb, var(--main-color) 10%, var(--merchant-panel-bg));
    border-color: rgb(86 119 255 / 20%);
    box-shadow: 0 12px 28px rgb(86 119 255 / 10%);
  }

  .merchant-menu {
    height: 100%;
    background: transparent;
    border-right: 0;
  }

  .merchant-menu :deep(.menu-name) {
    font-size: 12px !important;
    font-weight: 500;
  }

  .merchant-menu :deep(.el-sub-menu__title),
  .merchant-menu :deep(.el-menu-item) {
    height: 38px !important;
    margin-bottom: 3px;
    line-height: 38px !important;
  }

  .merchant-menu :deep(.el-menu-item),
  .merchant-menu :deep(.el-sub-menu__title) {
    position: relative;
  }

  .merchant-menu--drawer {
    padding-top: 4px;
  }

  .menu-title-wrap {
    display: flex;
    align-items: center;
    width: 100%;
    min-width: 0;
    cursor: pointer;
  }

  .merchant-main {
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 100vh;
    background: var(--merchant-shell-bg);
  }

  .merchant-topbar {
    position: sticky;
    top: 0;
    z-index: 30;
    display: flex;
    gap: 16px;
    align-items: center;
    justify-content: space-between;
    min-height: 58px;
    padding: 0 16px;
    background: color-mix(in srgb, var(--merchant-panel-bg) 94%, transparent);
    border-bottom: 1px solid var(--merchant-soft-border);
    backdrop-filter: blur(12px);
  }

  .merchant-topbar__left,
  .merchant-topbar__right {
    display: flex;
    gap: 10px;
    align-items: center;
    min-width: 0;
  }

  .merchant-topbar__right {
    justify-content: flex-end;
  }

  .merchant-topbar__tool {
    flex-shrink: 0;
  }

  .merchant-topbar__tool--quick,
  .merchant-topbar__tool--refresh,
  .merchant-topbar__tool--desktop {
    display: inline-flex;
  }

  .merchant-topbar__breadcrumb {
    min-width: 0;
  }

  .merchant-topbar__fallback-title {
    display: none;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
  }

  .merchant-topbar__fallback-title span {
    color: var(--merchant-muted);
    font-size: 12px;
    line-height: 1.2;
  }

  .merchant-topbar__fallback-title strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
    font-weight: 700;
    line-height: 1.2;
  }

  .merchant-topbar__search {
    display: inline-flex;
    gap: 10px;
    align-items: center;
    justify-content: space-between;
    width: 156px;
    height: 34px;
    padding: 0 10px;
    color: var(--merchant-muted);
    background: var(--merchant-panel-bg);
    border: 1px solid var(--merchant-soft-border);
    border-radius: 12px;
    cursor: pointer;
    transition:
      border-color 0.2s ease,
      box-shadow 0.2s ease,
      transform 0.2s ease;
  }

  .merchant-topbar__search:hover {
    border-color: rgb(86 119 255 / 20%);
    box-shadow: 0 12px 28px rgb(86 119 255 / 9%);
    transform: translateY(-1px);
  }

  .merchant-topbar__search-label,
  .merchant-topbar__search-shortcut {
    display: inline-flex;
    align-items: center;
  }

  .merchant-topbar__search-label {
    gap: 6px;
    font-size: 13px;
    line-height: 1;
  }

  .merchant-topbar__search-shortcut {
    gap: 4px;
    min-width: 52px;
    height: 22px;
    padding: 0 6px;
    color: var(--merchant-muted);
    background: var(--merchant-shell-bg);
    border: 1px solid var(--merchant-soft-border);
    border-radius: 8px;
    font-size: 12px;
    line-height: 1;
  }

  .merchant-topbar__merchant-trigger {
    display: flex;
    gap: 7px;
    align-items: center;
    min-height: 34px;
    min-width: 0;
    margin: 0;
    padding: 3px 10px 3px 3px;
    font: inherit;
    text-align: left;
    appearance: none;
    background: var(--merchant-panel-bg);
    border: 1px solid var(--merchant-soft-border);
    border-radius: 999px;
    cursor: pointer;
    transition:
      border-color 0.2s ease,
      box-shadow 0.2s ease,
      transform 0.2s ease;
  }

  .merchant-topbar__merchant-trigger:hover {
    border-color: rgb(86 119 255 / 18%);
    box-shadow: 0 12px 28px rgb(86 119 255 / 8%);
    transform: translateY(-1px);
  }

  .merchant-topbar__merchant-trigger:focus-visible {
    outline: none;
  }

  .merchant-topbar__avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    color: #fff;
    background: linear-gradient(135deg, var(--main-color), #7c9cff);
    border-radius: 50%;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
  }

  .merchant-topbar__merchant-copy {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
  }

  .merchant-topbar__merchant-copy strong {
    color: var(--merchant-heading-color);
    font-size: 11px;
    font-weight: 600;
    line-height: 1.15;
  }

  .merchant-topbar__merchant-copy span {
    color: var(--merchant-muted);
    font-size: 10px;
    line-height: 1.15;
    white-space: nowrap;
  }

  .merchant-topbar__merchant-arrow {
    color: var(--merchant-muted);
    font-size: 16px;
    flex-shrink: 0;
  }

  .merchant-content {
    flex: 1;
    min-width: 0;
    padding: 20px;
  }

  .merchant-drawer__header {
    display: flex;
    gap: 12px;
    align-items: center;
  }

  .merchant-drawer__header-copy {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .merchant-drawer__header-copy strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
    font-weight: 700;
    line-height: 1.2;
  }

  .merchant-drawer__header-copy span {
    color: var(--merchant-muted);
    font-size: 12px;
    line-height: 1.2;
  }

  .merchant-route-skeleton {
    min-height: 320px;
  }

  .merchant-search-dialog__header {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .merchant-search-dialog__header strong {
    color: var(--merchant-heading-color);
    font-size: 18px;
    font-weight: 700;
    line-height: 1.2;
  }

  .merchant-search-dialog__header span {
    color: var(--merchant-muted);
    font-size: 13px;
    line-height: 1.6;
  }

  .merchant-search-dialog__scroll {
    margin-top: 14px;
  }

  .merchant-search-dialog__item {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    margin-bottom: 10px;
    padding: 14px 16px;
    color: inherit;
    text-align: left;
    background: var(--merchant-shell-bg);
    border: 1px solid transparent;
    border-radius: 14px;
    cursor: pointer;
    transition:
      border-color 0.2s ease,
      box-shadow 0.2s ease,
      transform 0.2s ease;
  }

  .merchant-search-dialog__item:last-child {
    margin-bottom: 0;
  }

  .merchant-search-dialog__item:hover {
    border-color: rgb(86 119 255 / 18%);
    box-shadow: 0 12px 28px rgb(86 119 255 / 10%);
    transform: translateY(-1px);
  }

  .merchant-search-dialog__item-copy {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
  }

  .merchant-search-dialog__item-copy strong {
    color: var(--merchant-heading-color);
    font-size: 14px;
    font-weight: 700;
    line-height: 1.3;
  }

  .merchant-search-dialog__item-copy span,
  .merchant-search-dialog__item-meta,
  .merchant-search-dialog__empty {
    color: var(--merchant-muted);
    font-size: 13px;
    line-height: 1.7;
  }

  .merchant-search-dialog__item-meta {
    flex-shrink: 0;
    padding: 3px 10px;
    background: var(--merchant-panel-bg);
    border: 1px solid var(--merchant-soft-border);
    border-radius: 999px;
    line-height: 1.4;
  }

  .merchant-search-dialog__empty {
    padding: 18px 4px 6px;
    text-align: center;
  }

  :deep(.merchant-search-dialog .el-dialog) {
    border-radius: 22px;
  }

  :deep(.merchant-search-dialog .el-dialog__header) {
    padding: 22px 22px 12px;
    margin-right: 0;
  }

  :deep(.merchant-search-dialog .el-dialog__body) {
    padding: 0 22px 22px;
  }

  :deep(.merchant-search-dialog .el-input__wrapper) {
    height: 46px;
    border-radius: 14px;
  }

  @media (width <= 1120px) {
    .merchant-topbar__breadcrumb {
      display: none;
    }

    .merchant-topbar__fallback-title {
      display: flex;
    }
  }

  @media (width <= 920px) {
    .merchant-topbar__search,
    .merchant-topbar__tool--quick {
      display: none;
    }
  }

  @media (width <= 820px) {
    .merchant-topbar__tool--desktop {
      display: none;
    }
  }

  @media (width <= 760px) {
    .merchant-shell,
    .merchant-shell--collapsed {
      grid-template-columns: minmax(0, 1fr);
    }

    .merchant-sidebar {
      display: none;
    }

    .merchant-sidebar__toggle {
      display: inline-flex;
    }
  }

  @media (width <= 800px) {
    .merchant-topbar {
      padding: 0 16px;
    }

    .merchant-topbar__tool--refresh {
      display: none;
    }

    .merchant-topbar__merchant-copy span {
      display: none;
    }

    .merchant-content {
      padding: 18px 16px 24px;
    }
  }
</style>
