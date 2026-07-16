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
          <span class="news-hero__tag">公告中心</span>
          <h1>{{ pageTitle }}</h1>
          <p>{{ pageDescription }}</p>
        </div>

        <div class="news-hero__meta">
          <article>
            <small>内容数量</small>
            <strong>{{ payload?.total ?? 0 }}</strong>
          </article>
          <article>
            <small>当前页</small>
            <strong>{{ payload?.current ?? currentPage }}</strong>
          </article>
          <article>
            <small>当前栏目</small>
            <strong>{{ currentTypeTitle }}</strong>
          </article>
        </div>
      </section>

      <section class="news-type-grid">
        <RouterLink
          v-for="item in typeCards"
          :key="item.type"
          :to="item.to"
          :class="['type-card', { 'is-active': item.type === currentType && isCategoryMode }]"
        >
          <span>{{ item.eyebrow }}</span>
          <h2>{{ item.title }}</h2>
          <p>{{ item.description }}</p>
        </RouterLink>
      </section>

      <section v-if="loading && !payload" class="state-panel">
        <ElSkeleton animated :rows="10" />
      </section>

      <section v-else-if="error" class="state-panel state-panel--error">
        <h2>公告列表加载失败</h2>
        <p>{{ error }}</p>
        <button type="button" class="state-panel__button" @click="loadPage">重新加载</button>
      </section>

      <section v-else class="news-list-panel">
        <div class="news-list-panel__head">
          <div>
            <span>最新内容</span>
            <h2>{{ listTitle }}</h2>
          </div>

          <div class="news-list-panel__meta">共 {{ payload?.total ?? 0 }} 条</div>
        </div>

        <div class="news-list">
          <article v-for="item in records" :key="item.id" class="news-card">
            <div class="news-card__meta">
              <span>{{ resolveTypeTitle(item.type) }}</span>
              <strong>{{ item.date_label }}</strong>
            </div>

            <h3>
              <RouterLink :to="`/news/detail/${item.id}`">{{ item.title }}</RouterLink>
            </h3>

            <p>{{ item.excerpt || '点击查看完整公告内容。' }}</p>

            <div class="news-card__actions">
              <RouterLink :to="`/news/detail/${item.id}`">查看详情</RouterLink>
            </div>
          </article>

          <div v-if="!records.length" class="news-card news-card--empty">当前栏目暂无公告内容</div>
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
      eyebrow: '平台公告',
      title: '系统通知',
      description: '查看平台公告、更新说明和站点通知。',
      to: '/news/categories/1'
    },
    {
      type: 2,
      eyebrow: '行业资讯',
      title: '行业动态',
      description: '收录支付环境变化、渠道更新和行业事件。',
      to: '/news/categories/2'
    },
    {
      type: 3,
      eyebrow: '常见问题',
      title: '问题排查',
      description: '集中整理高频问题和接入排查建议。',
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
      ? `${currentTypeTitle.value}统一使用 index99 前台样式展示。`
      : '平台公告、行业资讯和常见问题统一从这里对外展示。'
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
      error.value = resolvePublicErrorMessage(err, '公告内容暂时不可用，请检查 8787 接口。')
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
    gap: 22px;
  }

  .news-hero,
  .type-card,
  .state-panel,
  .news-list-panel {
    border: 1px solid rgba(148, 163, 184, 0.16);
    border-radius: 28px;
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
  }

  .news-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(280px, 0.8fr);
    gap: 20px;
    padding: 30px;
  }

  .news-hero__tag,
  .news-card__meta span,
  .news-list-panel__head span {
    display: inline-flex;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(97, 115, 255, 0.12);
    color: #5467f5;
    font-size: 0.8rem;
    font-weight: 700;
  }

  .news-hero h1 {
    margin: 18px 0 12px;
    color: #1f2937;
    font-size: clamp(2rem, 4vw, 3.2rem);
    line-height: 1.08;
    letter-spacing: -0.04em;
  }

  .news-hero p {
    margin: 0;
    color: #5f6b7a;
    line-height: 1.8;
  }

  .news-hero__meta {
    display: grid;
    gap: 12px;
  }

  .news-hero__meta article {
    padding: 18px;
    border-radius: 20px;
    background: #f8fbff;
  }

  .news-hero__meta small {
    display: block;
    color: #64748b;
  }

  .news-hero__meta strong {
    display: block;
    margin-top: 8px;
    color: #1f2937;
    font-size: 1.08rem;
  }

  .news-type-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
  }

  .type-card {
    display: block;
    padding: 24px;
    color: inherit;
    text-decoration: none;
    transition:
      transform 0.2s ease,
      box-shadow 0.2s ease,
      background 0.2s ease;
  }

  .type-card:hover,
  .type-card.is-active {
    transform: translateY(-2px);
    background: #f8fbff;
    box-shadow: 0 18px 36px rgba(97, 115, 255, 0.12);
  }

  .type-card span {
    display: inline-flex;
    color: #5467f5;
    font-size: 0.82rem;
    font-weight: 700;
  }

  .type-card h2 {
    margin: 14px 0 10px;
    color: #1f2937;
    font-size: 1.22rem;
  }

  .type-card p {
    margin: 0;
    color: #5f6b7a;
    line-height: 1.8;
  }

  .state-panel {
    padding: 28px;
  }

  .state-panel--error {
    background: #fff7f7;
    border-color: rgba(239, 68, 68, 0.2);
  }

  .state-panel h2 {
    margin: 0 0 8px;
    color: #1f2937;
  }

  .state-panel p {
    margin: 0;
    color: #5f6b7a;
    line-height: 1.8;
  }

  .state-panel__button {
    margin-top: 18px;
    min-height: 42px;
    padding: 0 16px;
    border: 0;
    border-radius: 14px;
    background: #5467f5;
    color: #fff;
    font-weight: 700;
    cursor: pointer;
  }

  .news-list-panel {
    padding: 26px;
  }

  .news-list-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 18px;
  }

  .news-list-panel__head h2 {
    margin: 14px 0 0;
    color: #1f2937;
    font-size: 1.42rem;
  }

  .news-list-panel__meta {
    color: #64748b;
    font-weight: 700;
  }

  .news-list {
    display: grid;
    gap: 14px;
  }

  .news-card {
    padding: 22px;
    border-radius: 22px;
    background: #f8fbff;
  }

  .news-card__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .news-card__meta strong {
    color: #64748b;
    font-size: 0.9rem;
  }

  .news-card h3 {
    margin: 16px 0 10px;
    font-size: 1.16rem;
    line-height: 1.55;
  }

  .news-card h3 a {
    color: #1f2937;
    text-decoration: none;
  }

  .news-card p {
    margin: 0;
    color: #5f6b7a;
    line-height: 1.8;
  }

  .news-card__actions {
    margin-top: 16px;
  }

  .news-card__actions a {
    color: #5467f5;
    font-weight: 700;
    text-decoration: none;
  }

  .news-card--empty {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 180px;
    color: #64748b;
  }

  .news-pagination {
    display: flex;
    justify-content: center;
    margin-top: 22px;
  }

  @media (max-width: 980px) {
    .news-hero,
    .news-type-grid {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 640px) {
    .news-hero,
    .type-card,
    .state-panel,
    .news-list-panel {
      border-radius: 24px;
    }

    .news-hero,
    .state-panel,
    .news-list-panel {
      padding: 22px;
    }

    .news-list-panel__head,
    .news-card__meta {
      flex-direction: column;
      align-items: flex-start;
    }
  }
</style>
