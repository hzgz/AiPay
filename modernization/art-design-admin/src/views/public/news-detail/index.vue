<template>
  <PublicShell
    :site-name="siteName"
    :navs="navs"
    :is-logged-in="isLoggedIn"
    page-label="公告详情"
  >
    <div class="public-news-detail-page">
      <section v-if="loading && !article" class="state-panel">
        <ElSkeleton animated :rows="12" />
      </section>

      <section v-else-if="notFound" class="state-panel state-panel--warning">
        <h1>公告不存在</h1>
        <p>当前访问的公告可能已经删除、下线，或者链接地址已经失效。</p>
        <div class="state-panel__actions">
          <RouterLink class="state-link state-link--primary" to="/news/index">返回公告中心</RouterLink>
          <RouterLink class="state-link" to="/">返回首页</RouterLink>
        </div>
      </section>

      <section v-else-if="error" class="state-panel state-panel--error">
        <h1>公告详情加载失败</h1>
        <p>{{ error }}</p>
        <button type="button" class="state-panel__button" @click="loadPage">重新加载</button>
      </section>

      <template v-else-if="article">
        <section class="article-hero">
          <div>
            <span>{{ resolveTypeTitle(article.type) }}</span>
            <h1>{{ article.title }}</h1>
            <p>{{ article.excerpt || `发布时间 ${article.date_label}` }}</p>
          </div>

          <aside class="article-hero__meta">
            <div>
              <small>栏目</small>
              <strong>{{ resolveTypeTitle(article.type) }}</strong>
            </div>
            <div>
              <small>发布时间</small>
              <strong>{{ article.date_label }}</strong>
            </div>
            <div>
              <small>站点</small>
              <strong>{{ siteName }}</strong>
            </div>
          </aside>
        </section>

        <section class="article-layout">
          <article class="article-card">
            <div class="article-card__content" v-html="article.content_html"></div>
          </article>

          <aside class="article-side">
            <div class="article-side__card">
              <span>快捷跳转</span>
              <RouterLink :to="`/news/categories/${article.type}`">返回 {{ resolveTypeTitle(article.type) }}</RouterLink>
              <RouterLink to="/news/index">公告中心</RouterLink>
              <RouterLink to="/doc">开发文档</RouterLink>
            </div>

            <div class="article-side__card">
              <span>当前信息</span>
              <p>公告详情统一走前台原生页面展示，不再依赖旧模板详情页。</p>
            </div>
          </aside>
        </section>
      </template>
    </div>
  </PublicShell>
</template>

<script setup lang="ts">
  import { PublicCompatError } from '@/api/public-client'
  import { fetchPublicNewsDetail, type PublicNewsDetailPayload } from '@/api/public-site'
  import PublicShell from '../shared/public-shell.vue'
  import { resolvePublicErrorMessage, scrollPublicPageToTop } from '../shared/public-state'

  defineOptions({ name: 'PublicNewsDetailPage' })

  const route = useRoute()
  const loading = ref(false)
  const error = ref('')
  const notFound = ref(false)
  const payload = ref<PublicNewsDetailPayload | null>(null)

  const article = computed(() => payload.value?.article || null)
  const siteName = computed(() => payload.value?.site_name || 'AiPay')
  const navs = computed(() => payload.value?.navs || [])
  const isLoggedIn = computed(() => Boolean(payload.value?.is_logged_in))

  async function loadPage() {
    const id = Number(route.params.id || 0)

    loading.value = true
    error.value = ''
    notFound.value = false
    payload.value = null

    try {
      payload.value = await fetchPublicNewsDetail(id)
      scrollPublicPageToTop()
    } catch (err) {
      if (err instanceof PublicCompatError && err.status === 404) {
        notFound.value = true
        return
      }

      error.value = resolvePublicErrorMessage(err, '公告详情暂时不可用，请稍后再试。')
    } finally {
      loading.value = false
    }
  }

  function resolveTypeTitle(type: number) {
    return (
      {
        1: '平台公告',
        2: '行业资讯',
        3: '常见问题'
      } as Record<number, string>
    )[type] || '公告中心'
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
  .public-news-detail-page {
    display: grid;
    gap: 22px;
  }

  .state-panel,
  .article-hero,
  .article-card,
  .article-side__card {
    border: 1px solid rgba(148, 163, 184, 0.16);
    border-radius: 28px;
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
  }

  .state-panel {
    padding: 28px;
  }

  .state-panel--warning {
    background: #fffbeb;
    border-color: rgba(245, 158, 11, 0.2);
  }

  .state-panel--error {
    background: #fff7f7;
    border-color: rgba(239, 68, 68, 0.2);
  }

  .state-panel h1 {
    margin: 0 0 10px;
    color: #1f2937;
    font-size: 1.6rem;
  }

  .state-panel p {
    margin: 0;
    color: #5f6b7a;
    line-height: 1.8;
  }

  .state-panel__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 18px;
  }

  .state-link,
  .state-panel__button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 16px;
    border-radius: 14px;
    border: 0;
    color: #1f2937;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    background: #e2e8f0;
  }

  .state-link--primary,
  .state-panel__button {
    background: #5467f5;
    color: #fff;
  }

  .article-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.6fr) minmax(260px, 0.7fr);
    gap: 18px;
    padding: 30px;
  }

  .article-hero span,
  .article-side__card span {
    display: inline-flex;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(97, 115, 255, 0.12);
    color: #5467f5;
    font-size: 0.8rem;
    font-weight: 700;
  }

  .article-hero h1 {
    margin: 18px 0 12px;
    color: #1f2937;
    font-size: clamp(2rem, 4vw, 3.2rem);
    line-height: 1.08;
    letter-spacing: -0.04em;
  }

  .article-hero p {
    margin: 0;
    color: #5f6b7a;
    line-height: 1.8;
  }

  .article-hero__meta {
    display: grid;
    gap: 12px;
  }

  .article-hero__meta div {
    padding: 18px;
    border-radius: 20px;
    background: #f8fbff;
  }

  .article-hero__meta small {
    display: block;
    color: #64748b;
  }

  .article-hero__meta strong {
    display: block;
    margin-top: 8px;
    color: #1f2937;
  }

  .article-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.6fr) minmax(280px, 0.8fr);
    gap: 20px;
  }

  .article-card {
    padding: 28px 30px;
  }

  .article-card__content {
    color: #334155;
    line-height: 1.9;
    font-size: 1rem;
  }

  .article-card__content :deep(img) {
    max-width: 100%;
    height: auto;
    border-radius: 18px;
  }

  .article-card__content :deep(p) {
    margin: 0 0 1.1em;
  }

  .article-card__content :deep(h1),
  .article-card__content :deep(h2),
  .article-card__content :deep(h3),
  .article-card__content :deep(h4) {
    margin: 1.4em 0 0.8em;
    color: #1f2937;
    line-height: 1.35;
  }

  .article-card__content :deep(pre) {
    overflow: auto;
    padding: 18px;
    border-radius: 18px;
    background: #0f172a;
    color: #e2e8f0;
  }

  .article-card__content :deep(table) {
    width: 100%;
    border-collapse: collapse;
  }

  .article-card__content :deep(th),
  .article-card__content :deep(td) {
    padding: 12px;
    border: 1px solid #e2e8f0;
  }

  .article-side {
    display: grid;
    gap: 18px;
    align-self: start;
  }

  .article-side__card {
    padding: 22px;
  }

  .article-side__card a {
    display: block;
    margin-top: 14px;
    color: #1f2937;
    font-weight: 700;
    text-decoration: none;
  }

  .article-side__card p {
    margin: 16px 0 0;
    color: #5f6b7a;
    line-height: 1.8;
  }

  @media (max-width: 960px) {
    .article-hero,
    .article-layout {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 640px) {
    .state-panel,
    .article-hero,
    .article-card,
    .article-side__card {
      border-radius: 24px;
    }

    .state-panel,
    .article-hero,
    .article-card,
    .article-side__card {
      padding: 22px;
    }
  }
</style>
