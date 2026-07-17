<template>
  <PublicShell
    :site-name="siteName"
    :navs="navs"
    :is-logged-in="isLoggedIn"
    page-label="公告详情"
  >
    <div class="public-news-detail-page">
      <section v-if="loading && !article" class="detail-state">
        <ElSkeleton animated :rows="12" />
      </section>

      <section v-else-if="notFound" class="detail-state">
        <h1>公告不存在</h1>
        <p>当前访问的公告可能已经删除、下线，或者链接地址已经失效。</p>
        <div class="detail-state__actions">
          <RouterLink class="detail-link detail-link--primary" to="/news/index">返回公告中心</RouterLink>
          <RouterLink class="detail-link" to="/">返回首页</RouterLink>
        </div>
      </section>

      <section v-else-if="error" class="detail-state">
        <h1>公告详情加载失败</h1>
        <p>{{ error }}</p>
        <button type="button" class="detail-link detail-link--primary detail-link--button" @click="loadPage">
          重新加载
        </button>
      </section>

      <template v-else-if="article">
        <section class="detail-hero">
          <div>
            <span class="detail-eyebrow">{{ resolveTypeTitle(article.type) }}</span>
            <h1>{{ article.title }}</h1>
            <p>{{ article.excerpt || `发布时间 ${article.date_label}` }}</p>
          </div>

          <aside class="detail-hero__meta">
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

        <section class="detail-layout">
          <article class="detail-content">
            <div class="detail-content__body" v-html="article.content_html"></div>
          </article>

          <aside class="detail-side">
            <div class="detail-side__section">
              <span class="detail-eyebrow">相关入口</span>
              <RouterLink :to="`/news/categories/${article.type}`">返回 {{ resolveTypeTitle(article.type) }}</RouterLink>
              <RouterLink to="/news/index">公告中心</RouterLink>
              <RouterLink to="/doc">开发文档</RouterLink>
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
    gap: 28px;
  }

  .detail-eyebrow {
    display: inline-flex;
    color: var(--public-muted);
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .detail-state,
  .detail-hero,
  .detail-content,
  .detail-side__section {
    border-top: 1px solid var(--public-border-strong);
    padding-top: 14px;
  }

  .detail-state h1 {
    margin: 0 0 10px;
    color: var(--public-title);
    font-size: 1.8rem;
  }

  .detail-state p {
    margin: 0;
    color: var(--public-text);
    line-height: 1.84;
  }

  .detail-state__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 16px;
  }

  .detail-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 0 16px;
    border: 1px solid var(--public-border);
    border-radius: 999px;
    background: #fff;
    color: var(--public-title);
    font-weight: 700;
    text-decoration: none;
  }

  .detail-link--primary {
    background: #18202f;
    border-color: #18202f;
    color: #fff;
  }

  .detail-link--button {
    cursor: pointer;
  }

  .detail-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(250px, 0.65fr);
    gap: 30px;
    align-items: end;
  }

  .detail-hero h1 {
    margin: 14px 0 14px;
    color: var(--public-title);
    font-size: clamp(2.2rem, 4vw, 3.9rem);
    line-height: 1.08;
    letter-spacing: -0.05em;
  }

  .detail-hero p {
    margin: 0;
    max-width: 760px;
    color: var(--public-text);
    line-height: 1.9;
  }

  .detail-hero__meta {
    display: grid;
    gap: 14px;
  }

  .detail-hero__meta small {
    display: block;
    color: var(--public-muted);
  }

  .detail-hero__meta strong {
    display: block;
    margin-top: 8px;
    color: var(--public-title);
  }

  .detail-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.65fr) minmax(250px, 0.7fr);
    gap: 32px;
  }

  .detail-content__body {
    color: #344255;
    line-height: 1.92;
    font-size: 1.02rem;
  }

  .detail-content__body :deep(img) {
    max-width: 100%;
    height: auto;
    border-radius: 18px;
  }

  .detail-content__body :deep(p) {
    margin: 0 0 1.1em;
  }

  .detail-content__body :deep(h1),
  .detail-content__body :deep(h2),
  .detail-content__body :deep(h3),
  .detail-content__body :deep(h4) {
    margin: 1.35em 0 0.8em;
    color: var(--public-title);
    line-height: 1.35;
  }

  .detail-content__body :deep(blockquote) {
    margin: 1.2em 0;
    padding: 16px 18px;
    border-left: 3px solid rgba(15, 23, 42, 0.16);
    background: #f8fafc;
    color: var(--public-text);
  }

  .detail-content__body :deep(pre) {
    overflow: auto;
    padding: 18px;
    border-radius: 16px;
    background: #f8fafc;
    color: #1f2937;
  }

  .detail-content__body :deep(table) {
    width: 100%;
    border-collapse: collapse;
  }

  .detail-content__body :deep(th),
  .detail-content__body :deep(td) {
    padding: 12px;
    border: 1px solid #e5e7eb;
  }

  .detail-side {
    position: sticky;
    top: 96px;
    display: grid;
    gap: 24px;
    align-self: start;
  }

  .detail-side__section a {
    display: block;
    padding: 14px 0;
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    color: var(--public-title);
    font-weight: 700;
    text-decoration: none;
  }

  .detail-side__section a:last-child {
    padding-bottom: 0;
    border-bottom: 0;
  }

  .detail-side__section p {
    margin: 10px 0 0;
    color: var(--public-text);
    line-height: 1.82;
  }

  @media (max-width: 980px) {
    .detail-hero,
    .detail-layout {
      grid-template-columns: 1fr;
    }

    .detail-side {
      position: static;
    }
  }
</style>
