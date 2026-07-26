<template>
  <div class="merchant-page">
    <section class="merchant-page-header">
      <div class="merchant-page-header__title">
        <h1>登录日志</h1>
        <p>查看前台访问记录和请求摘要。</p>
      </div>

      <div v-if="!loading" class="merchant-chip-row">
        <span class="merchant-chip">日志 {{ summary.total_count ?? 0 }} 条</span>
        <span class="merchant-chip">今日访问 {{ summary.today_count ?? 0 }} 条</span>
        <span class="merchant-chip">来源地址 {{ summary.ip_count ?? 0 }} 个</span>
      </div>
    </section>

    <div v-if="loading" class="merchant-panel merchant-state-card">
      <ElSkeleton :rows="8" animated />
    </div>

    <template v-else>
      <section class="merchant-stat-grid">
        <article
          v-for="card in summaryCards"
          :key="card.label"
          class="merchant-card merchant-stat-card"
        >
          <div class="merchant-stat-card__row">
            <div class="merchant-stat-card__copy">
              <div class="merchant-stat-card__label">{{ card.label }}</div>
              <div class="merchant-stat-card__value">{{ card.value }}</div>
              <div class="merchant-stat-card__hint">{{ card.hint }}</div>
            </div>

            <div class="merchant-stat-card__symbol">
              <Icon :icon="card.icon" />
            </div>
          </div>
        </article>
      </section>

      <article class="merchant-card">
        <div class="merchant-card__head">
          <div>
            <h2>日志记录</h2>
            <p>按路径、来源地址、行为类型筛选。</p>
          </div>

          <div class="merchant-toolbar-pills">
            <div class="merchant-toolbar-pill">
              <span>行为类型</span>
              <strong>{{ currentTypeLabel }}</strong>
            </div>
            <div v-if="filters.ip" class="merchant-toolbar-pill">
              <span>来源地址</span>
              <strong>{{ filters.ip }}</strong>
            </div>
          </div>
        </div>

        <div class="merchant-table-toolbar">
          <div class="merchant-table-toolbar__filters merchant-table-toolbar__filters--login">
            <ElInput
              v-model.trim="filters.keyword"
              placeholder="搜索路径或载荷内容"
              clearable
              @keyup.enter="loadLogs(true)"
            >
              <template #prefix>
                <Icon icon="ri:search-line" />
              </template>
            </ElInput>

            <ElInput
              v-model.trim="filters.ip"
              placeholder="搜索来源地址"
              clearable
              @keyup.enter="loadLogs(true)"
            />

            <ElSelect v-model="filters.type" clearable placeholder="行为类型" style="width: 160px">
              <ElOption label="登录事件" value="1" />
              <ElOption label="安全事件" value="2" />
              <ElOption label="商户行为" value="3" />
            </ElSelect>

            <ElButton type="primary" @click="loadLogs(true)">查询</ElButton>
            <ElButton plain :disabled="!hasActiveFilters" @click="resetFilters">重置</ElButton>
          </div>
        </div>

        <ElTable :data="records" empty-text="暂无登录日志">
          <ElTableColumn prop="path" label="访问路径" min-width="200" show-overflow-tooltip />
          <ElTableColumn prop="ip" label="来源地址" width="150" />
          <ElTableColumn prop="type_label" label="行为类型" width="120">
            <template #default="{ row }">
              {{ translateMerchantText(row.type_label) }}
            </template>
          </ElTableColumn>
          <ElTableColumn
            prop="payload_preview"
            label="请求载荷"
            min-width="240"
            show-overflow-tooltip
          >
            <template #default="{ row }">
              {{ translateMerchantText(row.payload_preview) }}
            </template>
          </ElTableColumn>
          <ElTableColumn prop="create_time" label="访问时间" min-width="180" />
          <ElTableColumn label="操作" width="100" fixed="right">
            <template #default="{ row }">
              <ElButton text type="primary" @click="openDetail(row)">详情</ElButton>
            </template>
          </ElTableColumn>
        </ElTable>

        <div class="merchant-pagination">
          <ElPagination
            background
            layout="prev, pager, next"
            :current-page="pagination.current"
            :page-size="pagination.size"
            :total="pagination.total"
            @current-change="handlePageChange"
          />
        </div>
      </article>

      <ElDrawer v-model="detailVisible" size="560px" class="merchant-login-log-drawer">
        <template #header>
          <div class="merchant-log-drawer-head">
            <div class="merchant-log-drawer-head__copy">
              <strong>日志详情</strong>
              <span>{{ detail?.path || '查看来源、行为和请求内容' }}</span>
            </div>
          </div>
        </template>

        <div v-if="detail" class="merchant-detail-stack">
          <section class="merchant-soft-panel">
            <div class="merchant-detail-section__head">
              <strong>基础信息</strong>
              <span>{{ detail.create_time || '--' }}</span>
            </div>

            <div class="merchant-kv-grid merchant-kv-grid--detail">
              <div class="merchant-kv-item">
                <span>访问路径</span>
                <div>{{ detail.path || '--' }}</div>
              </div>
              <div class="merchant-kv-item">
                <span>来源地址</span>
                <div>{{ detail.ip || '--' }}</div>
              </div>
              <div class="merchant-kv-item">
                <span>行为类型</span>
                <div>{{ translateMerchantText(detail.type_label) }}</div>
              </div>
              <div class="merchant-kv-item">
                <span>访问时间</span>
                <div>{{ detail.create_time || '--' }}</div>
              </div>
              <div class="merchant-kv-item">
                <span>浏览器标识</span>
                <div>{{ detail.user_agent || '--' }}</div>
              </div>
            </div>
          </section>

          <section class="merchant-soft-panel">
            <div class="merchant-detail-section__head">
              <strong>请求载荷</strong>
              <span>用于排查异常访问</span>
            </div>

            <div class="merchant-code-block">{{ detail.payload_text || '暂无请求载荷' }}</div>
          </section>
        </div>
      </ElDrawer>
    </template>
  </div>
</template>

<script setup lang="ts">
  import { Icon } from '@iconify/vue'
  import { ElMessage } from 'element-plus'
  import { MerchantApiError, fetchMerchantLoginLogs } from '@/api/merchant'
  import { translateMerchantText } from '../shared/text'

  defineOptions({ name: 'MerchantLoginLogs' })

  const loading = ref(true)
  const records = ref<Record<string, any>[]>([])
  const summary = ref<Record<string, any>>({})
  const pagination = reactive({
    current: 1,
    size: 10,
    total: 0
  })
  const filters = reactive({
    keyword: '',
    ip: '',
    type: ''
  })

  const detailVisible = ref(false)
  const detail = ref<Record<string, any> | null>(null)

  const summaryCards = computed(() => [
    {
      label: '日志总数',
      value: String(summary.value.total_count ?? 0),
      hint: '当前日志总数',
      icon: 'ri:file-list-3-line'
    },
    {
      label: '含载荷日志',
      value: String(summary.value.payload_count ?? 0),
      hint: '带请求内容的记录',
      icon: 'ri:database-2-line'
    },
    {
      label: '今日访问',
      value: String(summary.value.today_count ?? 0),
      hint: '今日新增记录',
      icon: 'ri:calendar-check-line'
    },
    {
      label: '来源地址数',
      value: String(summary.value.ip_count ?? 0),
      hint: '去重后的来源地址',
      icon: 'ri:global-line'
    }
  ])

  const hasActiveFilters = computed(
    () => filters.keyword !== '' || filters.ip !== '' || filters.type !== ''
  )

  const currentTypeLabel = computed(() => {
    const mapping: Record<string, string> = {
      '1': '登录事件',
      '2': '安全事件',
      '3': '商户行为'
    }

    return mapping[filters.type] || '全部类型'
  })

  function resetFilters() {
    filters.keyword = ''
    filters.ip = ''
    filters.type = ''
    loadLogs(true)
  }

  async function loadLogs(resetPage = false) {
    if (resetPage) {
      pagination.current = 1
    }

    loading.value = true
    try {
      const result = await fetchMerchantLoginLogs({
        current: pagination.current,
        size: pagination.size,
        keyword: filters.keyword,
        ip: filters.ip,
        type: filters.type
      })

      records.value = result.records
      summary.value = result.summary
      pagination.current = result.pagination.current
      pagination.size = result.pagination.size
      pagination.total = result.pagination.total
    } catch (error) {
      const message =
        error instanceof MerchantApiError
          ? translateMerchantText(error.message, error.message)
          : '登录日志加载失败'
      ElMessage.error(message)
    } finally {
      loading.value = false
    }
  }

  function openDetail(row: Record<string, any>) {
    detail.value = row
    detailVisible.value = true
  }

  function handlePageChange(page: number) {
    pagination.current = page
    loadLogs()
  }

  onMounted(() => {
    loadLogs()
  })
</script>

<style lang="scss">
  @use '../styles';
</style>

<style lang="scss" scoped>
  .merchant-table-toolbar__filters--login {
    flex: 1;
  }

  .merchant-table-toolbar__filters--login :deep(.el-input) {
    width: 240px;
    max-width: 100%;
  }

  .merchant-detail-stack {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .merchant-log-drawer-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
  }

  .merchant-log-drawer-head__copy {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
  }

  .merchant-log-drawer-head__copy strong {
    color: var(--merchant-heading-color);
    font-size: 18px;
    font-weight: 700;
    line-height: 1.2;
  }

  .merchant-log-drawer-head__copy span,
  .merchant-detail-section__head span {
    color: var(--merchant-muted);
    font-size: 13px;
    line-height: 1.7;
    word-break: break-all;
  }

  .merchant-detail-section__head {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
  }

  .merchant-detail-section__head strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
    font-weight: 700;
    line-height: 1.2;
  }

  .merchant-kv-grid--detail {
    gap: 16px 18px;
  }

  :deep(.merchant-login-log-drawer .el-drawer__header) {
    margin-bottom: 0;
    padding: 20px 22px 0;
  }

  :deep(.merchant-login-log-drawer .el-drawer__body) {
    padding: 18px 22px 22px;
  }

  @media (width <= 768px) {
    .merchant-table-toolbar__filters--login > * {
      width: 100% !important;
    }

    .merchant-detail-section__head {
      flex-direction: column;
      align-items: flex-start;
    }
  }
</style>
