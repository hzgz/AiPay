<template>
  <PublicShell
    :site-name="siteName"
    :navs="navs"
    :is-logged-in="isLoggedIn"
    page-label="公告中心"
  >
    <div class="public-news-page">
      <section class="news-hero">
        <div>
          <span class="news-eyebrow">公告中心</span>
          <h1>{{ pageTitle }}</h1>
          <p>{{ pageDescription }}</p>
        </div>

        <div class="news-hero__meta">
          <div>
            <small>内容总数</small>
            <strong>{{ payload?.total ?? 0 }}</strong>
          </div>
          <div>
            <small>当前页</small>
            <strong>{{ payload?.current ?? currentPage }}</strong>
          </div>
          <div>
            <small>当前栏目</small>
            <strong>{{ currentTypeTitle }}</strong>
          </div>
        </div>
      </section>

      <section class="news-types">
        <RouterLink
          v-for="item in typeCards"
          :key="item.type"
          :to="item.to"
          :class="['news-type', { 'is-active': item.type === currentType && isCategoryMode }]"
        >
          <strong>{{ item.title }}</strong>
          <small>{{ item.description }}</small>
        </RouterLink>
      </section>

      <section v-if="loading && !payload" class="news-state">
        <ElSkeleton animated :rows="10" />
      </section>

      <section v-else-if="error" class="news-state">
        <h2>公告列表加载失败</h2>
        <p>{{ error }}</p>
        <button type="button" class="news-state__button" @click="loadPage">重新加载</button>
      </section>

      <section v-else class="news-list-section">
        <div class="news-list-section__head">
          <div>
            <span class="news-eyebrow">内容列表</span>
            <h2>{{ listTitle }}</h2>
          </div>

          <div class="news-list-section__meta">共 {{ payload?.total ?? 0 }} 条</div>
        </div>

        <div class="news-list">
          <article v-for="item in records" :key="item.id" class="news-row">
            <div class="news-row__meta">
              <span>{{ resolveTypeTitle(item.type) }}</span>
              <strong>{{ item.date_label }}</strong>
            </div>

            <div class="news-row__body">
              <h3>
                <RouterLink :to="`/news/detail/${item.id}`">{{ item.title }}</RouterLink>
              </h3>
              <p>{{ item.excerpt || '点击查看完整公告内容。' }}</p>
            </div>

            <div class="news-row__action">
              <RouterLink :to="`/news/detail/${item.id}`">查看详情</RouterLink>
            </div>
          </article>

          <div v-if="!records.length" class="news-empty">当前栏目暂无公告内容</div>
        </div>

        <div v-if="(payload?.total || 0) > (payload?.size || 10)" class="news-pagination">
          <ElPagination
            background
            layout="prev, pager, next"
            :current-page="payload?.current || 1"
            :page-size="payload?.size || 10"
            :total="payload?.total || 0"
            @current-change="handlePageChange"
          />
        </div>
      </section>
    </div>
  </PublicShell>
</template>

<script setup lang="ts">
  import {
    fetchPublicNewsCategory,
    fetchPublicNewsIndex,
    type PublicNewsListPayload
  } from '@/api/public-site'
  import PublicShell from '../shared/public-shell.vue'
  import {
    normalizePublicPage,
    resolvePublicErrorMessage,
    scrollPublicPageToTop
  } from '../shared/public-state'

  defineOptions({ name: 'PublicNewsIndexPage' })

  const route = useRoute()
  const router = useRouter()
  const loading = ref(false)
  const error = ref('')
  const payload = ref<PublicNewsListPayload | null>(null)

  const currentType = computed(() => {
    const type = Number(route.params.type || 1)
    return [1, 2, 3].includes(type) ? type : 1
  })

  const currentPage = computed(() => normalizePublicPage(route.query.page, 1))
  const isCategoryMode = computed(() => route.path.startsWith('/news/categories/'))
  const records = computed(() => payload.value?.records || [])
  const siteName = computed(() => payload.value?.site_name || 'AiPay')
  const navs = computed(() => payload.value?.navs || [])
  const isLoggedIn = computed(() => Boolean(payload.value?.is_logged_in))

  const typeCards = [
    {
      type: 1,
      title: '平台公告',
      description: '查看平台更新、通知和站点公告',
      to: '/news/categories/1'
    },
    {
      type: 2,
      title: '行业资讯',
      description: '查看行业动态和支付环境变化',
      to: '/news/categories/2'
    },
    {
      type: 3,
      title: '常见问题',
      description: '查看接入和使用过程中的高频问题',
      to: '/news/categories/3'
    }
  ]

  const currentTypeTitle = computed(() => {
    if (!isCategoryMode.value) {
      return '全部公告'
    }

    return resolveTypeTitle(currentType.value)
  })

  const pageTitle = computed(() => (isCategoryMode.value ? currentTypeTitle.value : '公告中心'))
  const pageDescription = computed(() =>
    isCategoryMode.value
      ? `${currentTypeTitle.value}统一在这里对外展示。`
      : '平台公告、行业资讯与常见问题统一从这里对外展示。'
  )
  const listTitle = computed(() => (isCategoryMode.value ? currentTypeTitle.value : '最新公告'))

  async function loadPage() {
    loading.value = true
    error.value = ''

    try {
      payload.value = isCategoryMode.value
        ? await fetchPublicNewsCategory(currentType.value, {
            current: currentPage.value,
            size: 10
          })
        : await fetchPublicNewsIndex(undefined, {
            current: currentPage.value,
            size: 10
          })

      scrollPublicPageToTop()
    } catch (err) {
      error.value = resolvePublicErrorMessage(err, '公告内容暂时不可用，请稍后再试。')
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

  function handlePageChange(page: number) {
    const targetPath = isCategoryMode.value ? `/news/categories/${currentType.value}` : '/news/index'

    void router.push({
      path: targetPath,
      query: page > 1 ? { page: String(page) } : {}
    })
  }

  watch(
    () => `${route.path}|${route.params.type || ''}|${route.query.page || ''}`,
    () => {
      void loadPage()
    },
    { immediate: true }
  )
</script>

<style scoped lang="scss">
  .public-news-page {
    display: grid;
    gap: 28px;
  }

  .news-eyebrow {
    display: inline-flex;
    color: var(--public-muted);
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .news-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.3fr) minmax(260px, 0.7fr);
    gap: 30px;
    align-items: end;
  }

  .news-hero h1 {
    margin: 14px 0 14px;
    color: var(--public-title);
    font-size: clamp(2.2rem, 4vw, 3.9rem);
    line-height: 1.08;
    letter-spacing: -0.05em;
  }

  .news-hero p {
    margin: 0;
    max-width: 760px;
    color: var(--public-text);
    line-height: 1.9;
  }

  .news-hero__meta,
  .news-types,
  .news-state,
  .news-list-section {
    border-top: 1px solid var(--public-border-strong);
    padding-top: 14px;
  }

  .news-hero__meta {
    display: grid;
    gap: 14px;
  }

  .news-hero__meta small {
    display: block;
    color: var(--public-muted);
  }

  .news-hero__meta strong {
    display: block;
    margin-top: 8px;
    color: var(--public-title);
  }

  .news-types {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 24px;
  }

  .news-type {
    display: block;
    padding-bottom: 14px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    color: inherit;
    text-decoration: none;
  }

  .news-type strong {
    display: block;
    color: var(--public-title);
  }

  .news-type small {
    display: block;
    margin-top: 8px;
    color: var(--public-text);
    line-height: 1.75;
  }

  .news-type.is-active strong {
    color: var(--public-accent);
  }

  .news-state h2 {
    margin: 0 0 10px;
    color: var(--public-title);
  }

  .news-state p {
    margin: 0;
    color: var(--public-text);
    line-height: 1.8;
  }

  .news-state__button {
    margin-top: 16px;
    min-height: 40px;
    padding: 0 16px;
    border: 1px solid var(--public-border);
    border-radius: 999px;
    background: #fff;
    color: var(--public-title);
    font-weight: 700;
    cursor: pointer;
  }

  .news-list-section__head {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 16px;
  }

  .news-list-section__head h2 {
    margin: 10px 0 0;
    color: var(--public-title);
    font-size: 1.72rem;
    line-height: 1.25;
    letter-spacing: -0.04em;
  }

  .news-list-section__meta {
    color: var(--public-muted);
    font-weight: 700;
  }

  .news-list {
    display: grid;
  }

  .news-row {
    display: grid;
    grid-template-columns: 180px minmax(0, 1fr) 120px;
    gap: 20px;
    align-items: start;
    padding: 18px 0;
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
  }

  .news-row:last-child {
    border-bottom: 0;
    padding-bottom: 0;
  }

  .news-row__meta {
    display: grid;
    gap: 8px;
  }

  .news-row__meta span {
    display: inline-flex;
    width: fit-content;
    color: var(--public-muted);
    font-size: 0.84rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .news-row__meta strong {
    color: var(--public-title);
    font-size: 0.96rem;
  }

  .news-row__body h3 {
    margin: 0;
    font-size: 1.1rem;
    line-height: 1.6;
  }

  .news-row__body h3 a,
  .news-row__action a {
    color: var(--public-title);
    text-decoration: none;
  }

  .news-row__body p {
    margin: 10px 0 0;
    color: var(--public-text);
    line-height: 1.82;
  }

  .news-row__action {
    display: flex;
    justify-content: flex-end;
  }

  .news-row__action a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 0 16px;
    border: 1px solid var(--public-border);
    border-radius: 999px;
    background: #fff;
    font-weight: 700;
  }

  .news-empty {
    padding: 24px 0 4px;
    color: var(--public-text);
  }

  .news-pagination {
    display: flex;
    justify-content: center;
    margin-top: 22px;
  }

  @media (max-width: 980px) {
    .news-hero,
    .news-types {
      grid-template-columns: 1fr;
    }

    .news-row {
      grid-template-columns: 1fr;
    }

    .news-row__action {
      justify-content: flex-start;
    }
  }

  @media (max-width: 720px) {
    .news-list-section__head {
      flex-direction: column;
      align-items: stretch;
    }
  }
</style>
