<template>
  <PublicShell
    :site-name="siteName"
    :navs="navs"
    :is-logged-in="isLoggedIn"
    page-label="游客首页"
    :merchant-login-url="merchantLoginUrl"
    :merchant-register-url="merchantRegisterUrl"
    :merchant-center-url="merchantDashboardUrl"
  >
    <div class="public-home-page">
      <section class="home-hero">
        <div class="home-hero__copy">
          <span class="home-eyebrow">AiPay</span>
          <h1>{{ siteName }} 为商户提供稳定清晰的支付接入入口</h1>
          <p>
            在这里了解平台能力、查看公告与文档，并完成商户注册或登录。
          </p>

          <div class="home-hero__actions">
            <a class="home-button home-button--primary" :href="merchantEntryUrl">
              {{ isLoggedIn ? '进入商户中心' : '商户登录' }}
            </a>
            <a class="home-button home-button--secondary" :href="merchantRegisterUrl">注册商户</a>
            <a class="home-button home-button--secondary" :href="docUrl">开发文档</a>
          </div>

          <p v-if="error" class="home-hero__notice">
            当前内容暂时无法刷新，请稍后再试。
          </p>
        </div>

        <aside class="home-hero__side">
          <div class="home-side__section">
            <span>当前概览</span>

            <div class="home-side__rows">
              <div v-for="metric in heroMetrics" :key="metric.label" class="home-side__row">
                <small>{{ metric.label }}</small>
                <strong>{{ metric.value }}</strong>
              </div>
            </div>
          </div>

          <div class="home-side__section">
            <span>常用入口</span>

            <div class="home-side__links">
              <a v-for="item in heroLinks" :key="item.label" :href="item.href">
                <strong>{{ item.label }}</strong>
                <small>{{ item.note }}</small>
              </a>
            </div>
          </div>
        </aside>
      </section>

      <section class="home-band">
        <div class="home-band__head">
          <div>
            <span class="home-eyebrow">平台能力</span>
            <h2>游客前台只讲清楚入口、说明和下一步</h2>
          </div>
        </div>

        <div class="home-feature-list">
          <article v-for="item in featureCards" :key="item.title" class="home-feature-item">
            <span>{{ item.eyebrow }}</span>
            <h3>{{ item.title }}</h3>
            <p>{{ item.description }}</p>
          </article>
        </div>
      </section>

      <section class="home-band">
        <div class="home-band__head">
          <div>
            <span class="home-eyebrow">常用入口</span>
            <h2>游客浏览与商户接入从这里开始</h2>
          </div>
        </div>

        <div class="home-entry-list">
          <a v-for="entry in quickEntries" :key="entry.title" class="home-entry-item" :href="entry.href">
            <div>
              <span>{{ entry.eyebrow }}</span>
              <h3>{{ entry.title }}</h3>
            </div>
            <p>{{ entry.description }}</p>
          </a>
        </div>
      </section>

      <section class="home-band">
        <div class="home-band__head">
          <div>
            <span class="home-eyebrow">公告预览</span>
            <h2>平台公告、行业资讯与常见问题</h2>
          </div>

          <a class="home-band__link" :href="newsIndexUrl">查看全部</a>
        </div>

        <div class="home-news-grid">
          <article v-for="section in newsSections" :key="section.type" class="home-news-column">
            <div class="home-news-column__head">
              <strong>{{ section.title }}</strong>
              <a :href="section.path">更多</a>
            </div>

            <div class="home-news-column__list">
              <a
                v-for="item in section.items"
                :key="item.id"
                class="home-news-row"
                :href="`/#/news/detail/${item.id}`"
              >
                <small>{{ item.date_label }}</small>
                <h3>{{ item.title }}</h3>
                <p>{{ item.excerpt || '点击查看公告详情。' }}</p>
              </a>

              <div v-if="!section.items.length" class="home-news-empty">当前分类暂无内容</div>
            </div>
          </article>
        </div>
      </section>
    </div>
  </PublicShell>
</template>

<script setup lang="ts">
  import { fetchPublicHome, type PublicHomePayload } from '@/api/public-site'
  import PublicShell from '../shared/public-shell.vue'
  import { resolvePublicErrorMessage, scrollPublicPageToTop } from '../shared/public-state'

  defineOptions({ name: 'PublicHomePage' })

  const route = useRoute()
  const loading = ref(false)
  const error = ref('')
  const payload = ref<PublicHomePayload | null>(null)

  const siteName = computed(() => payload.value?.site_name || 'AiPay')
  const navs = computed(() => payload.value?.navs || [])
  const isLoggedIn = computed(() => Boolean(payload.value?.is_logged_in))
  const merchantLoginUrl = computed(() => payload.value?.merchant_login_url || '/#/merchant/login')
  const merchantRegisterUrl = computed(
    () => payload.value?.merchant_register_url || '/#/merchant/register'
  )
  const merchantDashboardUrl = computed(() => '/#/merchant/dashboard')
  const merchantEntryUrl = computed(() =>
    isLoggedIn.value ? merchantDashboardUrl.value : merchantLoginUrl.value
  )
  const docUrl = computed(() => payload.value?.doc_url || '/#/doc')
  const newsIndexUrl = computed(() => payload.value?.news_index_url || '/#/news/index')
  const demoUrl = computed(() => payload.value?.demo_url || '/#/demo')

  const heroMetrics = computed(() => [
    { label: '商户服务', value: isLoggedIn.value ? '已登录可用' : '开放接入' },
    { label: '公开栏目', value: '文档 / 公告 / 测试' },
    { label: '公告总数', value: String(payload.value?.summary.news_count ?? 0) },
    { label: '导航数量', value: String(payload.value?.summary.nav_count ?? 4) }
  ])

  const heroLinks = computed(() => [
    { label: '公告中心', note: '查看平台公告和行业资讯', href: newsIndexUrl.value },
    { label: '支付测试', note: '快速了解前台支付展示方式', href: demoUrl.value },
    { label: '商户入驻', note: '完成注册后进入商户控制台', href: merchantRegisterUrl.value }
  ])

  const featureCards = [
    {
      eyebrow: '统一入口',
      title: '首页、文档、公告与测试保持同一套产品页语言',
      description: '访问者看到的是一套连续清晰的产品页面，不会被后台式布局和杂乱提示打断。'
    },
    {
      eyebrow: '信息收敛',
      title: '每个页面只保留对游客与商户真正有用的内容',
      description: '不塞无意义说明，不堆杂乱卡片，公开页面只承担清晰展示和引导职责。'
    },
    {
      eyebrow: '接入顺畅',
      title: '从游客浏览到商户进入控制台的路径保持简洁',
      description: '注册、登录、文档和测试入口都在同一前台语境中完成，不暴露无关路径。'
    }
  ]

  const quickEntries = computed(() => [
    {
      eyebrow: '商户接入',
      title: isLoggedIn.value ? '进入商户中心' : '商户登录',
      description: isLoggedIn.value
        ? '继续管理通道、订单、回调与账户配置。'
        : '登录后即可进入商户控制台管理通道和订单。',
      href: merchantEntryUrl.value
    },
    {
      eyebrow: '注册入驻',
      title: '注册商户',
      description: '直接进入商户注册流程，完成基础入驻与配置。',
      href: merchantRegisterUrl.value
    },
    {
      eyebrow: '开发接入',
      title: '查看开发文档',
      description: '查看接入说明、回调规则和常用查询入口。',
      href: docUrl.value
    },
    {
      eyebrow: '支付展示',
      title: '支付测试',
      description: '查看可用支付方式和测试金额，了解展示效果。',
      href: demoUrl.value
    }
  ])

  const newsSections = computed(() => {
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

<style scoped lang="scss">
  .public-home-page {
    display: grid;
    gap: 40px;
  }

  .home-eyebrow {
    display: inline-flex;
    align-items: center;
    color: var(--public-muted);
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .home-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(280px, 0.7fr);
    gap: 44px;
    align-items: start;
  }

  .home-hero__copy h1 {
    margin: 16px 0 18px;
    color: var(--public-title);
    font-size: clamp(2.6rem, 4.6vw, 4.5rem);
    line-height: 1.04;
    letter-spacing: -0.06em;
  }

  .home-hero__copy p {
    margin: 0;
    max-width: 700px;
    color: var(--public-text);
    font-size: 1.04rem;
    line-height: 1.95;
  }

  .home-hero__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 28px;
  }

  .home-button,
  .home-band__link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 16px;
    border-radius: 999px;
    border: 1px solid transparent;
    text-decoration: none;
    font-weight: 700;
    transition:
      border-color 0.2s ease,
      background 0.2s ease,
      color 0.2s ease;
  }

  .home-button--primary {
    background: #18202f;
    color: #fff;
  }

  .home-button--secondary,
  .home-band__link {
    border-color: var(--public-border);
    background: rgba(255, 255, 255, 0.78);
    color: var(--public-title);
  }

  .home-hero__notice {
    margin-top: 18px;
    color: #b45309;
    font-size: 0.92rem;
    line-height: 1.8;
  }

  .home-hero__side {
    display: grid;
    gap: 22px;
  }

  .home-side__section {
    border-top: 1px solid var(--public-border-strong);
    padding-top: 14px;
  }

  .home-side__section > span {
    display: inline-flex;
    color: var(--public-muted);
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .home-side__rows,
  .home-side__links {
    display: grid;
    gap: 12px;
    margin-top: 14px;
  }

  .home-side__row,
  .home-side__links a {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    color: inherit;
    text-decoration: none;
  }

  .home-side__row:last-child,
  .home-side__links a:last-child {
    padding-bottom: 0;
    border-bottom: 0;
  }

  .home-side__row small,
  .home-side__links small {
    color: var(--public-text);
    line-height: 1.7;
  }

  .home-side__row strong,
  .home-side__links strong {
    color: var(--public-title);
    font-size: 0.96rem;
  }

  .home-band {
    border-top: 1px solid var(--public-border-strong);
    padding-top: 22px;
  }

  .home-band__head {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 18px;
  }

  .home-band__head h2 {
    margin: 10px 0 0;
    color: var(--public-title);
    font-size: 1.82rem;
    line-height: 1.22;
    letter-spacing: -0.04em;
  }

  .home-feature-list {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 22px;
  }

  .home-feature-item {
    padding-top: 14px;
    border-top: 1px solid rgba(15, 23, 42, 0.06);
  }

  .home-feature-item span,
  .home-entry-item span {
    color: var(--public-muted);
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .home-feature-item h3,
  .home-entry-item h3,
  .home-news-row h3 {
    margin: 12px 0 10px;
    color: var(--public-title);
  }

  .home-feature-item p,
  .home-entry-item p,
  .home-news-row p,
  .home-news-empty {
    margin: 0;
    color: var(--public-text);
    line-height: 1.85;
  }

  .home-entry-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0 24px;
  }

  .home-entry-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(220px, 0.9fr);
    gap: 20px;
    padding: 18px 0;
    border-top: 1px solid rgba(15, 23, 42, 0.06);
    color: inherit;
    text-decoration: none;
  }

  .home-entry-item:nth-child(-n + 2) {
    padding-top: 0;
    border-top: 0;
  }

  .home-news-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 24px;
  }

  .home-news-column__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
  }

  .home-news-column__head strong {
    color: var(--public-title);
  }

  .home-news-column__head a {
    color: var(--public-muted);
    font-size: 0.9rem;
    text-decoration: none;
  }

  .home-news-column__list {
    display: grid;
  }

  .home-news-row {
    display: grid;
    gap: 8px;
    padding: 16px 0;
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    color: inherit;
    text-decoration: none;
  }

  .home-news-row:last-child {
    border-bottom: 0;
  }

  .home-news-row small {
    color: var(--public-muted);
    font-size: 0.82rem;
    font-weight: 700;
  }

  .home-news-row h3 {
    font-size: 1.02rem;
    line-height: 1.6;
  }

  .home-news-empty {
    padding: 18px 0 4px;
  }

  @media (max-width: 1024px) {
    .home-hero,
    .home-feature-list,
    .home-news-grid {
      grid-template-columns: 1fr;
    }

    .home-entry-list {
      grid-template-columns: 1fr;
    }

    .home-entry-item {
      padding-top: 18px;
      border-top: 1px solid rgba(15, 23, 42, 0.06);
    }

    .home-entry-item:nth-child(2) {
      padding-top: 18px;
      border-top: 1px solid rgba(15, 23, 42, 0.06);
    }
  }

  @media (max-width: 720px) {
    .public-home-page {
      gap: 30px;
    }

    .home-hero__copy h1 {
      font-size: 2.18rem;
    }

    .home-hero__actions,
    .home-entry-item,
    .home-band__head {
      grid-template-columns: 1fr;
      flex-direction: column;
      align-items: stretch;
    }

    .home-button,
    .home-band__link {
      width: 100%;
    }
  }
</style>
