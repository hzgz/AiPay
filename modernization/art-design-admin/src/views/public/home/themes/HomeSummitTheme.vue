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
    <div class="summit-page">
      <section class="summit-hero">
        <div class="summit-hero__copy">
          <span class="summit-kicker">Summit Home</span>
          <h1>让商户接入、支付测试与公告浏览在一个首页完成闭环。</h1>
          <p>当前首页模板更强调入口聚合与信息密度，适合想把商户转化和文档触达放在前台第一屏的场景。</p>

          <div class="summit-actions">
            <a class="summit-button summit-button--primary" :href="merchantEntryUrl">
              {{ isLoggedIn ? '进入商户中心' : '立即登录' }}
            </a>
            <a class="summit-button summit-button--secondary" :href="merchantRegisterUrl">注册商户</a>
            <a class="summit-button summit-button--secondary" :href="demoUrl">支付测试</a>
          </div>

          <p v-if="error" class="summit-error">{{ error }}</p>
        </div>

        <div class="summit-panel">
          <div class="summit-panel__grid">
            <a class="summit-panel__item" :href="docUrl">
              <span>开发文档</span>
              <strong>接口接入</strong>
              <p>查看接口参数、签名规则与回调说明。</p>
            </a>
            <a class="summit-panel__item" :href="newsIndexUrl">
              <span>公告中心</span>
              <strong>平台动态</strong>
              <p>同步平台公告、行业资讯与常见问题。</p>
            </a>
            <a class="summit-panel__item" :href="merchantEntryUrl">
              <span>商户入口</span>
              <strong>{{ isLoggedIn ? '继续处理业务' : '进入商户中心' }}</strong>
              <p>订单、通道、结算与账户配置都从这里进入。</p>
            </a>
          </div>
        </div>
      </section>

      <section class="summit-signals">
        <article v-for="section in newsSections" :key="section.type" class="signal-card">
          <div class="signal-card__head">
            <div>
              <span>{{ section.title }}</span>
              <strong>{{ section.items.length }}</strong>
            </div>
            <a :href="section.path">查看全部</a>
          </div>

          <div class="signal-card__list">
            <a
              v-for="item in section.items"
              :key="item.id"
              class="signal-card__row"
              :href="`/#/news/detail/${item.id}`"
            >
              <small>{{ item.date_label }}</small>
              <h3>{{ item.title }}</h3>
            </a>

            <div v-if="!section.items.length" class="signal-card__empty">暂无内容</div>
          </div>
        </article>
      </section>
    </div>
  </PublicShell>
</template>

<script setup lang="ts">
  import type { PublicNavItem, PublicNewsSummary } from '@/api/publicSite'
  import PublicShell from '../../shared/PublicShell.vue'

  defineOptions({ name: 'HomeSummitTheme' })

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
  .summit-page {
    display: grid;
    gap: 28px;
  }

  .summit-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.9fr);
    gap: 22px;
    align-items: stretch;
  }

  .summit-hero__copy,
  .summit-panel,
  .signal-card {
    border: 1px solid var(--public-border);
    border-radius: 28px;
    background:
      radial-gradient(circle at top right, rgb(37 99 235 / 0.08), transparent 28%),
      linear-gradient(180deg, var(--public-surface), var(--public-surface-soft));
  }

  .summit-hero__copy {
    padding: 28px;
  }

  .summit-kicker {
    display: inline-flex;
    color: var(--public-accent);
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
  }

  .summit-hero__copy h1 {
    margin: 16px 0 18px;
    color: var(--public-title);
    font-size: clamp(2.4rem, 4vw, 4rem);
    line-height: 1.06;
    letter-spacing: -0.06em;
  }

  .summit-hero__copy p {
    margin: 0;
    color: var(--public-text);
    line-height: 1.9;
  }

  .summit-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 28px;
  }

  .summit-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 18px;
    border-radius: 999px;
    border: 1px solid transparent;
    text-decoration: none;
    font-weight: 700;
  }

  .summit-button--primary {
    border-color: var(--public-cta-border);
    background: var(--public-cta-bg);
    color: var(--public-cta-text);
  }

  .summit-button--secondary {
    border-color: var(--public-border);
    background: var(--public-surface-muted);
    color: var(--public-title);
  }

  .summit-error {
    margin-top: 16px;
    color: var(--public-warning);
  }

  .summit-panel {
    padding: 18px;
  }

  .summit-panel__grid {
    display: grid;
    gap: 14px;
    height: 100%;
  }

  .summit-panel__item {
    display: grid;
    gap: 8px;
    padding: 18px;
    border: 1px solid var(--public-border);
    border-radius: 20px;
    background: var(--public-surface-muted);
    color: inherit;
    text-decoration: none;
  }

  .summit-panel__item span,
  .signal-card__head span {
    color: var(--public-muted);
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .summit-panel__item strong,
  .signal-card__head strong {
    color: var(--public-title);
    font-size: 1.28rem;
  }

  .summit-panel__item p {
    color: var(--public-text);
    line-height: 1.75;
  }

  .summit-signals {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
  }

  .signal-card {
    padding: 20px;
  }

  .signal-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--public-border);
  }

  .signal-card__head a {
    color: var(--public-accent);
    text-decoration: none;
  }

  .signal-card__list {
    display: grid;
    gap: 12px;
    margin-top: 14px;
  }

  .signal-card__row {
    display: grid;
    gap: 6px;
    padding: 12px 0;
    border-bottom: 1px solid var(--public-border);
    color: inherit;
    text-decoration: none;
  }

  .signal-card__row:last-child {
    border-bottom: 0;
    padding-bottom: 0;
  }

  .signal-card__row small {
    color: var(--public-muted);
    font-weight: 700;
  }

  .signal-card__row h3 {
    margin: 0;
    color: var(--public-title);
    line-height: 1.6;
  }

  .signal-card__empty {
    color: var(--public-text);
    padding-top: 10px;
  }

  @media (max-width: 1024px) {
    .summit-hero,
    .summit-signals {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 720px) {
    .summit-hero__copy,
    .signal-card,
    .summit-panel {
      padding: 16px;
    }

    .summit-actions {
      flex-direction: column;
      align-items: stretch;
    }

    .summit-button {
      width: 100%;
    }
  }
</style>
