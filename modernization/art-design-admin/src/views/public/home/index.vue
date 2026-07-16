<template>
  <PublicShell
    :site-name="siteName"
    :navs="navs"
    :is-logged-in="isLoggedIn"
    page-label="index99 游客前台"
    :merchant-login-url="merchantLoginUrl"
    :merchant-register-url="merchantRegisterUrl"
    :merchant-center-url="merchantDashboardUrl"
  >
    <div class="public-home-page">
      <section class="home-hero">
        <div class="home-hero__copy">
          <span class="home-hero__tag">聚合支付平台</span>
          <h1>{{ siteName }} 为商户提供统一接入前台</h1>
          <p>
            8132 统一承载游客首页、商户入口、开发文档、公告中心与支付测试，8787 仅负责
            Webman API、支付下单与回调处理。
          </p>

          <div class="home-hero__actions">
            <a class="home-btn home-btn--primary" :href="merchantEntryUrl">
              {{ isLoggedIn ? '进入商户中心' : '商户登录' }}
            </a>
            <a class="home-btn home-btn--secondary" :href="merchantRegisterUrl">注册商户</a>
            <a class="home-btn home-btn--ghost" :href="docUrl">开发文档</a>
          </div>

          <div v-if="error" class="home-hero__notice">
            动态数据暂时不可用，当前展示为 index99 静态前台。{{ error }}
          </div>
        </div>

        <div class="home-hero__visual">
          <div class="hero-chip hero-chip--left">微信 / 支付宝 / QQ</div>
          <div class="hero-chip hero-chip--right">{{ isLoggedIn ? '商户在线' : '游客模式' }}</div>

          <div class="hero-panel">
            <div class="hero-panel__head">
              <span>统一前台入口</span>
              <strong>index99</strong>
            </div>

            <div class="hero-panel__metrics">
              <article v-for="metric in heroMetrics" :key="metric.label">
                <small>{{ metric.label }}</small>
                <strong>{{ metric.value }}</strong>
              </article>
            </div>

            <div class="hero-panel__links">
              <a :href="newsIndexUrl">公告中心</a>
              <a :href="demoUrl">支付测试</a>
              <a :href="merchantRegisterUrl">商户入驻</a>
            </div>
          </div>

          <div class="hero-floating hero-floating--merchant">
            <span>商户服务</span>
            <strong>{{ isLoggedIn ? '正在使用控制台' : '开放注册与登录' }}</strong>
          </div>

          <div class="hero-floating hero-floating--docs">
            <span>对接能力</span>
            <strong>文档、公告、测试统一收口</strong>
          </div>
        </div>
      </section>

      <section class="home-summary-grid">
        <article v-for="card in summaryCards" :key="card.label" class="summary-card">
          <span>{{ card.label }}</span>
          <strong>{{ card.value }}</strong>
          <p>{{ card.note }}</p>
        </article>
      </section>

      <section class="home-feature-grid">
        <article v-for="item in featureCards" :key="item.title" class="feature-card">
          <span>{{ item.eyebrow }}</span>
          <h2>{{ item.title }}</h2>
          <p>{{ item.description }}</p>
        </article>
      </section>

      <section class="home-entry-board">
        <div class="section-head">
          <div>
            <span>常用入口</span>
            <h2>游客访问与商户接入从这里开始</h2>
          </div>
        </div>

        <div class="home-entry-grid">
          <a v-for="entry in quickEntries" :key="entry.title" class="entry-card" :href="entry.href">
            <span>{{ entry.eyebrow }}</span>
            <h3>{{ entry.title }}</h3>
            <p>{{ entry.description }}</p>
          </a>
        </div>
      </section>

      <section class="home-news-board">
        <div class="section-head">
          <div>
            <span>平台动态</span>
            <h2>公告中心预览</h2>
          </div>

          <a class="section-head__link" :href="newsIndexUrl">查看全部</a>
        </div>

        <div class="home-news-grid">
          <article v-for="section in newsSections" :key="section.type" class="news-column">
            <div class="news-column__head">
              <strong>{{ section.title }}</strong>
              <a :href="section.path">更多</a>
            </div>

            <div class="news-column__list">
              <a
                v-for="item in section.items"
                :key="item.id"
                class="news-item"
                :href="`/#/news/detail/${item.id}`"
              >
                <span>{{ item.date_label }}</span>
                <h3>{{ item.title }}</h3>
                <p>{{ item.excerpt || '点击查看公告详情。' }}</p>
              </a>

              <div v-if="!section.items.length" class="news-item news-item--empty">
                当前分类暂无内容
              </div>
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
    { label: '前端端口', value: '8132' },
    { label: '后端端口', value: '8787' },
    { label: '公开导航', value: String(payload.value?.summary.nav_count ?? 4) },
    { label: '公告总数', value: String(payload.value?.summary.news_count ?? 0) }
  ])

  const summaryCards = computed(() => [
    {
      label: '游客前台',
      value: 'index99',
      note: '首页、开发文档、公告中心与支付测试全部归拢到同一套前台模板。'
    },
    {
      label: '商户入口',
      value: isLoggedIn.value ? '在线' : '开放',
      note: '商户注册与登录走前台入口，管理员地址不在首页暴露。'
    },
    {
      label: '支付后端',
      value: 'Webman',
      note: '8787 只负责 API、支付、回调和兼容接口，不再承载游客页面。'
    },
    {
      label: '动态内容',
      value: String(payload.value?.summary.news_count ?? 0),
      note: '公告、资讯与常见问题通过统一公告中心对外展示。'
    }
  ])

  const featureCards = [
    {
      eyebrow: '统一风格',
      title: 'index99 前台固定为最终模板',
      description: '8132 打开根路径就是游客首页，不再混入其他临时页面和旧模板说明。'
    },
    {
      eyebrow: '开放接入',
      title: '文档、公告、支付测试统一收口',
      description: '开发者查看文档、商户了解公告、游客体验支付测试都走同一套前台结构。'
    },
    {
      eyebrow: '商户服务',
      title: '商户注册、登录与控制台无缝衔接',
      description: '从游客首页可直接进入商户注册与登录，登录后继续使用统一的商户端界面。'
    }
  ]

  const quickEntries = computed(() => [
    {
      eyebrow: '商户接入',
      title: isLoggedIn.value ? '进入商户中心' : '商户登录',
      description: isLoggedIn.value
        ? '继续管理通道、订单、回调与账户配置。'
        : '商户登录后即可进入控制台管理通道和订单。',
      href: merchantEntryUrl.value
    },
    {
      eyebrow: '注册入驻',
      title: '注册商户',
      description: '从游客首页直接进入商户注册流程，完成基础入驻与配置。',
      href: merchantRegisterUrl.value
    },
    {
      eyebrow: '开发文档',
      title: '查看对接文档',
      description: '统一查看支付地址、回调说明、验签规则与查单入口。',
      href: docUrl.value
    },
    {
      eyebrow: '支付测试',
      title: '体验支付测试',
      description: '游客页展示可用方式与示例金额，真实测试请进入商户端。',
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
      error.value = resolvePublicErrorMessage(err, '首页数据暂时不可用，请检查 8787 服务。')
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
    gap: 22px;
  }

  .home-hero,
  .summary-card,
  .feature-card,
  .home-entry-board,
  .home-news-board {
    border: 1px solid rgba(148, 163, 184, 0.16);
    border-radius: 28px;
    background: rgba(255, 255, 255, 0.88);
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
  }

  .home-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
    gap: 24px;
    padding: 34px;
  }

  .home-hero__copy {
    padding: 10px 0;
  }

  .home-hero__tag,
  .section-head span,
  .feature-card span,
  .entry-card span {
    display: inline-flex;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(97, 115, 255, 0.12);
    color: #5467f5;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.04em;
  }

  .home-hero__copy h1 {
    margin: 18px 0 16px;
    color: #1f2937;
    font-size: clamp(2.4rem, 4vw, 4rem);
    line-height: 1.08;
    letter-spacing: -0.04em;
  }

  .home-hero__copy p {
    margin: 0;
    max-width: 620px;
    color: #5f6b7a;
    font-size: 1rem;
    line-height: 1.9;
  }

  .home-hero__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 26px;
  }

  .home-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    padding: 0 20px;
    border-radius: 16px;
    text-decoration: none;
    font-weight: 700;
    transition:
      transform 0.2s ease,
      box-shadow 0.2s ease,
      background 0.2s ease;
  }

  .home-btn:hover {
    transform: translateY(-2px);
  }

  .home-btn--primary {
    background: linear-gradient(135deg, #6173ff 0%, #6f67ff 100%);
    color: #fff;
    box-shadow: 0 16px 30px rgba(97, 115, 255, 0.26);
  }

  .home-btn--secondary {
    background: #ffffff;
    color: #334155;
    border: 1px solid #dbe4ff;
  }

  .home-btn--ghost {
    background: #edf2ff;
    color: #5467f5;
  }

  .home-hero__notice {
    margin-top: 18px;
    padding: 14px 16px;
    border-radius: 18px;
    background: #fff7ed;
    color: #9a3412;
    line-height: 1.7;
    font-size: 0.92rem;
  }

  .home-hero__visual {
    position: relative;
    min-height: 420px;
    padding: 16px;
  }

  .hero-panel,
  .hero-floating {
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 24px 50px rgba(15, 23, 42, 0.08);
  }

  .hero-panel {
    position: absolute;
    inset: 56px 18px 18px;
    border-radius: 30px;
    padding: 26px;
    background:
      radial-gradient(circle at top left, rgba(97, 115, 255, 0.18), transparent 42%),
      rgba(255, 255, 255, 0.92);
  }

  .hero-panel__head span {
    display: block;
    color: #64748b;
    font-size: 0.84rem;
    font-weight: 700;
    letter-spacing: 0.08em;
  }

  .hero-panel__head strong {
    display: block;
    margin-top: 10px;
    color: #1f2937;
    font-size: 2rem;
    letter-spacing: -0.04em;
  }

  .hero-panel__metrics {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-top: 22px;
  }

  .hero-panel__metrics article {
    padding: 16px;
    border-radius: 18px;
    background: #f8fbff;
  }

  .hero-panel__metrics small {
    display: block;
    color: #64748b;
  }

  .hero-panel__metrics strong {
    display: block;
    margin-top: 8px;
    color: #1f2937;
    font-size: 1.14rem;
  }

  .hero-panel__links {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 22px;
  }

  .hero-panel__links a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 0 14px;
    border-radius: 14px;
    background: #edf2ff;
    color: #5467f5;
    text-decoration: none;
    font-weight: 700;
  }

  .hero-chip {
    position: absolute;
    display: inline-flex;
    align-items: center;
    min-height: 40px;
    padding: 0 16px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.92);
    color: #4b5563;
    font-size: 0.9rem;
    font-weight: 700;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
  }

  .hero-chip--left {
    top: 4px;
    left: 14px;
  }

  .hero-chip--right {
    top: 12px;
    right: 18px;
  }

  .hero-floating {
    position: absolute;
    border-radius: 22px;
    padding: 16px 18px;
  }

  .hero-floating span {
    display: block;
    color: #64748b;
    font-size: 0.82rem;
  }

  .hero-floating strong {
    display: block;
    margin-top: 8px;
    color: #1f2937;
    line-height: 1.6;
  }

  .hero-floating--merchant {
    left: 0;
    bottom: 58px;
    width: 220px;
  }

  .hero-floating--docs {
    right: 0;
    bottom: 76px;
    width: 238px;
  }

  .home-summary-grid,
  .home-feature-grid,
  .home-entry-grid,
  .home-news-grid {
    display: grid;
    gap: 18px;
  }

  .home-summary-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .summary-card {
    padding: 24px;
  }

  .summary-card span {
    display: block;
    color: #64748b;
    font-size: 0.84rem;
    font-weight: 700;
    letter-spacing: 0.05em;
  }

  .summary-card strong {
    display: block;
    margin-top: 14px;
    color: #1f2937;
    font-size: 1.8rem;
    letter-spacing: -0.04em;
  }

  .summary-card p {
    margin: 10px 0 0;
    color: #5f6b7a;
    line-height: 1.8;
  }

  .home-feature-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .feature-card {
    padding: 28px 24px;
  }

  .feature-card h2 {
    margin: 16px 0 12px;
    color: #1f2937;
    font-size: 1.3rem;
    line-height: 1.5;
  }

  .feature-card p {
    margin: 0;
    color: #5f6b7a;
    line-height: 1.8;
  }

  .home-entry-board,
  .home-news-board {
    padding: 26px;
  }

  .section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
  }

  .section-head h2 {
    margin: 14px 0 0;
    color: #1f2937;
    font-size: 1.5rem;
    letter-spacing: -0.03em;
  }

  .section-head__link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 16px;
    border-radius: 14px;
    background: #1f2937;
    color: #fff;
    text-decoration: none;
    font-weight: 700;
  }

  .home-entry-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .entry-card {
    display: block;
    padding: 24px;
    border-radius: 24px;
    background: #f8fbff;
    color: inherit;
    text-decoration: none;
    transition:
      transform 0.2s ease,
      box-shadow 0.2s ease;
  }

  .entry-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 18px 36px rgba(97, 115, 255, 0.12);
  }

  .entry-card h3 {
    margin: 16px 0 10px;
    color: #1f2937;
    font-size: 1.18rem;
  }

  .entry-card p {
    margin: 0;
    color: #5f6b7a;
    line-height: 1.8;
  }

  .home-news-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .news-column {
    padding: 22px;
    border-radius: 24px;
    background: #f8fbff;
  }

  .news-column__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
  }

  .news-column__head strong {
    color: #1f2937;
    font-size: 1.05rem;
  }

  .news-column__head a {
    color: #5467f5;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.9rem;
  }

  .news-column__list {
    display: grid;
    gap: 12px;
  }

  .news-item {
    display: block;
    padding: 18px;
    border-radius: 18px;
    background: #fff;
    color: inherit;
    text-decoration: none;
  }

  .news-item span {
    display: inline-flex;
    color: #64748b;
    font-size: 0.82rem;
    font-weight: 700;
  }

  .news-item h3 {
    margin: 10px 0 8px;
    color: #1f2937;
    font-size: 1rem;
    line-height: 1.6;
  }

  .news-item p {
    margin: 0;
    color: #5f6b7a;
    line-height: 1.75;
  }

  .news-item--empty {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 168px;
    color: #64748b;
  }

  @media (max-width: 1180px) {
    .home-summary-grid,
    .home-entry-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .home-feature-grid,
    .home-news-grid {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 980px) {
    .home-hero {
      grid-template-columns: 1fr;
    }

    .home-hero__visual {
      min-height: 360px;
    }
  }

  @media (max-width: 640px) {
    .home-hero,
    .summary-card,
    .feature-card,
    .home-entry-board,
    .home-news-board {
      border-radius: 24px;
    }

    .home-hero,
    .home-entry-board,
    .home-news-board {
      padding: 22px;
    }

    .home-summary-grid,
    .home-entry-grid {
      grid-template-columns: 1fr;
    }

    .home-hero__actions,
    .hero-panel__links {
      flex-direction: column;
    }

    .home-btn,
    .hero-panel__links a,
    .section-head__link {
      width: 100%;
    }

    .hero-chip,
    .hero-floating {
      position: static;
    }

    .home-hero__visual {
      display: grid;
      gap: 12px;
      padding: 0;
      min-height: 0;
    }

    .hero-panel {
      position: static;
      inset: auto;
    }

    .section-head {
      flex-direction: column;
      align-items: flex-start;
    }
  }
</style>
