<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <component
    :is="activeComponent"
    :site-name="siteName"
    :navs="navs"
    :is-logged-in="isLoggedIn"
    :merchant-login-url="merchantLoginUrl"
    :merchant-register-url="merchantRegisterUrl"
    :merchant-center-url="merchantDashboardUrl"
    :merchant-entry-url="merchantEntryUrl"
    :doc-url="docUrl"
    :news-index-url="newsIndexUrl"
    :demo-url="demoUrl"
    :news-sections="newsSections"
    :error="error"
    :active-theme-title="activeThemeTitle"
  />
</template>

<script setup lang="ts">
  import { fetchPublicHome, type PublicHomePayload, type PublicNewsSummary, type PublicNavItem } from '@/api/publicSite'
  import HomeDefaultTheme from './themes/HomeDefaultTheme.vue'
  import HomeSummitTheme from './themes/HomeSummitTheme.vue'
  import {
    appendPublicAffiliateQuery,
    resolvePublicAffiliateId,
    resolvePublicErrorMessage,
    scrollPublicPageToTop
  } from '../shared/publicState'

  defineOptions({ name: 'PublicHomePage' })

  interface HomeThemeSection {
    type: number
    title: string
    path: string
    items: PublicNewsSummary[]
  }

  const route = useRoute()
  const loading = ref(false)
  const error = ref('')
  const payload = ref<PublicHomePayload | null>(null)

  const siteName = computed(() => payload.value?.site_name || 'AiPay')
  const navs = computed<PublicNavItem[]>(() => payload.value?.navs || [])
  const isLoggedIn = computed(() => Boolean(payload.value?.is_logged_in))
  const merchantLoginUrl = computed(() => payload.value?.merchant_login_url || '/#/merchant/login')
  const affiliateId = computed(() => resolvePublicAffiliateId(route.query.aff))
  const merchantRegisterUrl = computed(() =>
    appendPublicAffiliateQuery(
      payload.value?.merchant_register_url || '/#/merchant/register',
      affiliateId.value
    )
  )
  const merchantDashboardUrl = computed(() => '/#/merchant/dashboard')
  const merchantEntryUrl = computed(() =>
    isLoggedIn.value ? merchantDashboardUrl.value : merchantLoginUrl.value
  )
  const docUrl = computed(() => payload.value?.doc_url || '/#/doc')
  const newsIndexUrl = computed(() => payload.value?.news_index_url || '/#/news/index')
  const demoUrl = computed(() => payload.value?.demo_url || '/#/demo')
  const activeThemeId = computed(() => payload.value?.active_theme_id || 'default')
  const activeThemeTitle = computed(() => payload.value?.active_theme_title || '标准首页模板')

  const activeComponent = computed(() =>
    activeThemeId.value === 'summit' ? HomeSummitTheme : HomeDefaultTheme
  )

  const newsSections = computed<HomeThemeSection[]>(() => {
    const source = payload.value?.news_sections || []
    const titleMap: Record<number, string> = {
      1: '平台公告',
      2: '行业资讯',
      3: '常见问题'
    }

    return [1, 2, 3].map((type) => {
      const matched = source.find((item) => item.type === type)
      return {
        type,
        title: titleMap[type] || '公告中心',
        path: matched?.path || `/#/news/categories/${type}`,
        items: matched?.items?.slice(0, 3) || []
      }
    })
  })

  async function loadPage() {
    loading.value = true
    error.value = ''

    try {
      payload.value = await fetchPublicHome()
      scrollPublicPageToTop()
    } catch (err) {
      error.value = resolvePublicErrorMessage(err, '首页内容暂时无法刷新。')
    } finally {
      loading.value = false
    }
  }

  watch(
    () => route.fullPath,
    () => {
      void loadPage()
    },
    { immediate: true }
  )
</script>
