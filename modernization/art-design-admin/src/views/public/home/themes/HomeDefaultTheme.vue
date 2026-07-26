<template>
  <PublicShell
    :site-name="siteName"
    :navs="navs"
    :is-logged-in="isLoggedIn"
    page-label="首页"
    :merchant-login-url="merchantLoginUrl"
    :merchant-register-url="merchantRegisterUrl"
    :merchant-center-url="merchantCenterUrl"
    :footer-note="`当前展示模板：${activeThemeTitle}`"
  >
    <div class="public-home-page">
      <section class="home-hero">
        <div class="home-hero__copy">
          <span class="home-eyebrow">AiPay</span>
          <h1>{{ siteName }} 商户支付与聚合收款平台</h1>
          <p>支持商户注册、接口接入、支付测试与公告通知，前台页面已接入可切换首页模板。</p>

          <div class="home-hero__actions">
            <a class="home-button home-button--primary" :href="merchantEntryUrl">
              {{ isLoggedIn ? '进入商户中心' : '商户登录' }}
            </a>
            <a class="home-button home-button--secondary" :href="merchantRegisterUrl">注册商户</a>
            <a class="home-button home-button--secondary" :href="docUrl">开发文档</a>
          </div>

          <p v-if="error" class="home-hero__notice">{{ error }}</p>
        </div>

        <aside class="home-hero__side">
          <div class="home-side__section">
            <span>快速开始</span>

            <div class="home-side__links">
              <a :href="merchantEntryUrl">
                <strong>{{ isLoggedIn ? '商户中心' : '商户登录' }}</strong>
                <small>{{ isLoggedIn ? '继续管理订单、通道与账户。' : '已有账号可直接登录。' }}</small>
              </a>
              <a :href="newsIndexUrl">
                <strong>公告中心</strong>
                <small>查看平台公告、行业资讯与常见问题。</small>
              </a>
              <a :href="demoUrl">
                <strong>支付测试</strong>
                <small>检查当前已开放的支付方式与演示流程。</small>
              </a>
            </div>
          </div>
        </aside>
      </section>

      <section class="home-band home-band--entries">
        <div class="home-band__head">
          <div>
            <span class="home-eyebrow">常用入口</span>
            <h2>常用入口</h2>
          </div>
        </div>

        <div class="home-entry-list">
          <a class="home-entry-item" :href="merchantEntryUrl">
            <div>
              <span>商户接入</span>
              <h3>{{ isLoggedIn ? '进入商户中心' : '商户登录' }}</h3>
            </div>
            <p>{{ isLoggedIn ? '继续处理订单、结算和支付配置。' : '登录后进入商户中心继续业务操作。' }}</p>
          </a>

          <a class="home-entry-item" :href="merchantRegisterUrl">
            <div>
              <span>注册入驻</span>
              <h3>注册商户</h3>
            </div>
            <p>创建商户账号并完成基础资料配置，开始接入支付能力。</p>
          </a>

          <a class="home-entry-item" :href="docUrl">
            <div>
              <span>开发接入</span>
              <h3>查看开发文档</h3>
            </div>
            <p>快速查看接口参数、回调规则和订单查询说明。</p>
          </a>

          <a class="home-entry-item" :href="demoUrl">
            <div>
              <span>支付测试</span>
              <h3>支付测试</h3>
            </div>
            <p>实测支付链路、查看可用支付方式与页面模板效果。</p>
          </a>
        </div>
      </section>

      <section class="home-band">
        <div class="home-band__head">
          <div>
            <span class="home-eyebrow">公告动态</span>
            <h2>公告与常见问题</h2>
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
                <p>{{ item.excerpt || '点击查看详情。' }}</p>
              </a>

              <div v-if="!section.items.length" class="home-news-empty">暂无内容</div>
            </div>
          </article>
        </div>
      </section>
    </div>
  </PublicShell>
</template>

<script setup lang="ts">
  import type { PublicNavItem, PublicNewsSummary } from '@/api/publicSite'
  import PublicShell from '../../shared/PublicShell.vue'

  defineOptions({ name: 'HomeDefaultTheme' })

  defineProps<{
    siteName: string
    navs: PublicNavItem[]
    isLoggedIn: boolean
    merchantLoginUrl: string
    merchantRegisterUrl: string
    merchantCenterUrl: string
    merchantEntryUrl: string
    docUrl: string
    newsIndexUrl: string
    demoUrl: string
    error: string
    activeThemeTitle: string
    newsSections: Array<{
      type: number
      title: string
      path: string
      items: PublicNewsSummary[]
    }>
  }>()
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
    min-height: 40px;
    padding: 0 16px;
    border-radius: 999px;
    border: 1px solid transparent;
    text-decoration: none;
    font-weight: 700;
  }

  .home-button--primary {
    border-color: var(--public-cta-border);
    background: var(--public-cta-bg);
    color: var(--public-cta-text);
  }

  .home-button--secondary,
  .home-band__link {
    border-color: var(--public-border);
    background: var(--public-surface-muted);
    color: var(--public-title);
  }

  .home-hero__notice {
    margin-top: 18px;
    color: var(--public-warning);
    font-size: 0.92rem;
    line-height: 1.8;
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
  }

  .home-side__links {
    display: grid;
    gap: 12px;
    margin-top: 14px;
  }

  .home-side__links a {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--public-border);
    color: inherit;
    text-decoration: none;
  }

  .home-side__links a:last-child {
    padding-bottom: 0;
    border-bottom: 0;
  }

  .home-side__links strong {
    color: var(--public-title);
  }

  .home-side__links small {
    color: var(--public-text);
    line-height: 1.7;
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
    border-top: 1px solid var(--public-border);
    color: inherit;
    text-decoration: none;
  }

  .home-entry-item:nth-child(-n + 2) {
    padding-top: 0;
    border-top: 0;
  }

  .home-entry-item span {
    color: var(--public-muted);
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.08em;
  }

  .home-entry-item h3,
  .home-news-row h3 {
    margin: 12px 0 10px;
    color: var(--public-title);
  }

  .home-entry-item p,
  .home-news-row p,
  .home-news-empty {
    margin: 0;
    color: var(--public-text);
    line-height: 1.85;
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
    border-bottom: 1px solid var(--public-border-strong);
  }

  .home-news-column__head strong {
    color: var(--public-title);
  }

  .home-news-column__head a {
    color: var(--public-muted);
    text-decoration: none;
  }

  .home-news-column__list {
    display: grid;
  }

  .home-news-row {
    display: grid;
    gap: 8px;
    padding: 16px 0;
    border-bottom: 1px solid var(--public-border);
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
    .home-news-grid,
    .home-entry-list {
      grid-template-columns: 1fr;
    }

    .home-entry-item {
      padding-top: 18px;
      border-top: 1px solid var(--public-border);
    }

    .home-entry-item:nth-child(2) {
      padding-top: 18px;
      border-top: 1px solid var(--public-border);
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
