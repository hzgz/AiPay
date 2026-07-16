<template>
  <div class="merchant-page">
    <section class="merchant-page-header">
      <div class="merchant-page-header__title">
        <h1>资金日志</h1>
        <p>查看收入支出、余额变化和流水备注，统一追踪每一笔账户资金的来龙去脉。</p>
      </div>

      <div v-if="!loading" class="merchant-chip-row">
        <span class="merchant-chip">收入 {{ summary.income_count ?? 0 }} 笔</span>
        <span class="merchant-chip">支出 {{ summary.expense_count ?? 0 }} 笔</span>
        <span class="merchant-chip">最近流水 {{ summary.last_log_time || '--' }}</span>
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
            <h2>资金流水</h2>
            <p>支持按备注关键字和流水编号筛选，用于核对收入支出、手续费和余额变化明细。</p>
          </div>

          <div class="merchant-toolbar-pills">
            <div class="merchant-toolbar-pill">
              <span>净变动</span>
              <strong>{{ amount(summary.net_amount) }}</strong>
            </div>
            <div v-if="filters.keyword" class="merchant-toolbar-pill">
              <span>关键词</span>
              <strong>{{ filters.keyword }}</strong>
            </div>
          </div>
        </div>

        <div class="merchant-note merchant-money-note">
          资金日志以查询和核对为主，适合快速追踪余额变动、手续费扣减和结算类账务事件。
        </div>

        <div class="merchant-table-toolbar">
          <div class="merchant-table-toolbar__filters merchant-table-toolbar__filters--money">
            <ElInput
              v-model.trim="filters.keyword"
              placeholder="搜索备注、流水类型或记录编号"
              clearable
              @keyup.enter="loadLogs(true)"
            >
              <template #prefix>
                <Icon icon="ri:search-line" />
              </template>
            </ElInput>

            <ElButton type="primary" @click="loadLogs(true)">查询</ElButton>
            <ElButton plain :disabled="!hasActiveFilters" @click="resetFilters">重置</ElButton>
          </div>

        </div>

        <ElTable :data="records" empty-text="暂无资金日志">
          <ElTableColumn prop="type_label" label="类型" min-width="160">
            <template #default="{ row }">
              {{ translateMerchantText(row.type_label) }}
            </template>
          </ElTableColumn>
          <ElTableColumn prop="direction_label" label="方向" width="110">
            <template #default="{ row }">
              <ElTag :type="row.direction === 'income' ? 'success' : 'warning'" effect="plain">
                {{ translateMerchantText(row.direction_label) }}
              </ElTag>
            </template>
          </ElTableColumn>
          <ElTableColumn prop="money_display" label="变动金额" width="120" />
          <ElTableColumn prop="before_money_display" label="变动前" width="120" />
          <ElTableColumn prop="after_money_display" label="变动后" width="120" />
          <ElTableColumn prop="memo_label" label="备注" min-width="220" show-overflow-tooltip>
            <template #default="{ row }">
              {{ translateMerchantText(row.memo_label) }}
            </template>
          </ElTableColumn>
          <ElTableColumn prop="create_time" label="创建时间" min-width="180" />
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
    </template>
  </div>
</template>

<script setup lang="ts">
  import { Icon } from '@iconify/vue'
  import { ElMessage } from 'element-plus'
  import { MerchantApiError, fetchMerchantMoneyLogs } from '@/api/merchant'
  import { translateMerchantText } from '../shared/text'

  defineOptions({ name: 'MerchantMoneyLogs' })

  const loading = ref(true)
  const records = ref<Record<string, any>[]>([])
  const summary = ref<Record<string, any>>({})
  const pagination = reactive({
    current: 1,
    size: 10,
    total: 0
  })
  const filters = reactive({
    keyword: ''
  })

  const summaryCards = computed(() => [
    {
      label: '收入笔数',
      value: String(summary.value.income_count ?? 0),
      hint: '当前筛选结果中的金额流入次数',
      icon: 'ri:arrow-right-up-line'
    },
    {
      label: '支出笔数',
      value: String(summary.value.expense_count ?? 0),
      hint: '当前筛选结果中的金额流出次数',
      icon: 'ri:arrow-left-down-line'
    },
    {
      label: '净变动',
      value: amount(summary.value.net_amount),
      hint: '收入与支出相抵后的净变化结果',
      icon: 'ri:exchange-dollar-line'
    },
    {
      label: '最近流水',
      value: summary.value.last_log_time || '--',
      hint: '最近一笔资金流水的创建时间',
      icon: 'ri:time-line'
    }
  ])

  const hasActiveFilters = computed(() => filters.keyword !== '')

  function amount(value: unknown) {
    return Number(value || 0).toFixed(3)
  }

  function resetFilters() {
    filters.keyword = ''
    loadLogs(true)
  }

  async function loadLogs(resetPage = false) {
    if (resetPage) {
      pagination.current = 1
    }

    loading.value = true
    try {
      const result = await fetchMerchantMoneyLogs({
        current: pagination.current,
        size: pagination.size,
        keyword: filters.keyword
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
          : '资金日志加载失败'
      ElMessage.error(message)
    } finally {
      loading.value = false
    }
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
  .merchant-money-note {
    margin-bottom: 16px;
  }

  .merchant-table-toolbar__filters--money {
    flex: 1;
  }

  .merchant-table-toolbar__filters--money :deep(.el-input) {
    width: 340px;
    max-width: 100%;
  }

  @media (width <= 768px) {
    .merchant-table-toolbar__filters--money > * {
      width: 100% !important;
    }
  }
</style>
