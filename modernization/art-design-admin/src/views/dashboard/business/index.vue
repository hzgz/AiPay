<template>
  <div class="dashboard-business-page">
    <ElRow :gutter="20" class="hero-row">
      <ElCol :xs="24" :xl="17">
        <ElCard class="hero-card" shadow="never" v-loading="loading">
          <div class="hero-content">
            <div class="hero-copy">
              <p class="hero-eyebrow">经营概览</p>
              <h2 class="hero-title">商城总览</h2>
              <p class="hero-desc">
                这里集中展示日、月、年交易表现，过去 30 个完整自然日订单趋势，以及收款与充值对比。
              </p>
              <div class="hero-tags">
                <ElTag type="success" effect="dark">
                  成交总笔数 {{ formatCount(summary.total_paid_trade_count) }}
                </ElTag>
                <ElTag type="warning" effect="dark">
                  今日新增 {{ formatCount(summary.today_new_user_count) }} 用户
                </ElTag>
                <ElTag effect="dark">数据更新 {{ overview?.generated_at || '--' }}</ElTag>
              </div>
            </div>

            <div class="hero-actions">
              <ElButton type="primary" @click="loadOverview" v-ripple>刷新总览</ElButton>
              <ElButton plain @click="goOrders" v-ripple>查看订单</ElButton>
              <ElButton plain @click="goRecharges" v-ripple>查看充值</ElButton>
            </div>
          </div>
        </ElCard>
      </ElCol>

      <ElCol :xs="24" :xl="7">
        <ElCard class="focus-card" shadow="never" v-loading="loading">
          <div class="panel-head">
            <div>
              <h3>关键快照</h3>
              <p>值班时优先盯住最近一个业务周期的变化。</p>
            </div>
          </div>

          <div class="focus-grid">
            <div v-for="item in focusItems" :key="item.label" class="focus-item">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
              <small>{{ item.note }}</small>
            </div>
          </div>
        </ElCard>
      </ElCol>
    </ElRow>

    <ElRow :gutter="20" class="summary-row">
      <ElCol v-for="card in summaryCards" :key="card.key" :xs="24" :sm="12" :xl="6">
        <ElCard class="summary-card" shadow="never" v-loading="loading">
          <div class="summary-head">
            <span>{{ card.label }}</span>
            <Icon :icon="card.icon" class="summary-icon" />
          </div>
          <strong class="summary-value">{{ card.value }}</strong>
          <p class="summary-note">{{ card.note }}</p>
        </ElCard>
      </ElCol>
    </ElRow>

    <ElRow :gutter="20" class="period-row">
      <ElCol v-for="period in periodCards" :key="period.key" :xs="24" :xl="8">
        <ElCard class="period-card" shadow="never" v-loading="loading">
          <div class="period-head">
            <div>
              <p>{{ period.label }}</p>
              <strong>{{ formatAmount(period.paid_amount) }}</strong>
            </div>
            <ElTag :type="periodTag(period.key)" effect="plain">
              {{ formatRate(period.success_rate) }}
            </ElTag>
          </div>
          <div class="period-meta">
            <span>已支付 {{ formatCount(period.paid_order_count) }}</span>
            <span>总订单 {{ formatCount(period.total_order_count) }}</span>
            <span>未支付 {{ formatCount(period.unpaid_order_count) }}</span>
          </div>
          <p class="period-note">订单总金额 {{ formatAmount(period.total_amount) }}</p>
        </ElCard>
      </ElCol>
    </ElRow>

    <ElRow :gutter="20">
      <ElCol :xs="24" :xl="16">
        <ElCard class="panel-card" shadow="never">
          <div class="panel-head">
            <div>
              <h3>近 30 天订单趋势</h3>
              <p>按完整自然日对比总订单、已支付订单与未支付订单，默认排除今天未结束的数据。</p>
            </div>
          </div>

          <ArtLineChart
            :loading="loading"
            :data="orderTrendSeries"
            :x-axis-data="orderTrendLabels"
            :colors="['#2563eb', '#16a34a', '#f97316']"
            :show-legend="true"
            legend-position="top"
            height="21rem"
          />
        </ElCard>
      </ElCol>

      <ElCol :xs="24" :xl="8">
        <ElCard class="panel-card" shadow="never" v-loading="loading">
          <div class="panel-head">
            <div>
              <h3>业务明细</h3>
              <p>保持高信息密度，方便快速扫描关键状态。</p>
            </div>
          </div>

          <div class="snapshot-grid">
            <div v-for="item in snapshotItems" :key="item.label" class="snapshot-item">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </div>
          </div>
        </ElCard>
      </ElCol>
    </ElRow>

    <ElRow :gutter="20">
      <ElCol :xs="24" :xl="12">
        <ElCard class="panel-card" shadow="never">
          <div class="panel-head">
            <div>
              <h3>收款金额对比</h3>
              <p>当前按微信、支付宝、QQ 钱包三种业务口径统计。</p>
            </div>
          </div>

          <ArtBarChart
            :loading="loading"
            :data="collectionSeries"
            :x-axis-data="comparisonLabels"
            :colors="['#14b8a6', '#3b82f6', '#f59e0b']"
            :show-legend="true"
            legend-position="top"
            height="20rem"
          />
        </ElCard>
      </ElCol>

      <ElCol :xs="24" :xl="12">
        <ElCard class="panel-card" shadow="never">
          <div class="panel-head">
            <div>
              <h3>充值金额对比</h3>
              <p>观察充值结构与收款结构是否同步，便于识别资金流向变化。</p>
            </div>
          </div>

          <ArtBarChart
            :loading="loading"
            :data="rechargeSeries"
            :x-axis-data="comparisonLabels"
            :colors="['#0ea5e9', '#6366f1', '#f97316']"
            :show-legend="true"
            legend-position="top"
            height="20rem"
          />
        </ElCard>
      </ElCol>
    </ElRow>

    <ElAlert
      type="info"
      show-icon
      :closable="false"
      class="readonly-alert"
      :title="readonlyAlertTitle"
    />
  </div>
</template>

<script setup lang="ts">
  import { Icon } from '@iconify/vue'
  import { ElMessage } from 'element-plus'
  import { useRouter } from 'vue-router'
  import { fetchGetCommerceOverview } from '@/api/commerce-overview'

  defineOptions({ name: 'BusinessOverview' })

  type CommerceSummary = Api.CommerceOverview.Summary
  type CommercePeriod = Api.CommerceOverview.PeriodItem

  const router = useRouter()
  const loading = ref(false)
  const overview = ref<Api.CommerceOverview.OverviewResponse | null>(null)
  const readonlyAlertTitle = computed(
    () => overview.value?.readonly_note || '当前页面仅展示经营统计数据。'
  )

  const summary = computed<CommerceSummary>(() => overview.value?.summary ?? emptySummary())
  const comparisonLabels = computed(
    () => overview.value?.collection_comparison.labels ?? ['本月', '本周', '今日']
  )

  const orderTrendLabels = computed(() => overview.value?.order_trend.labels ?? [])
  const orderTrendSeries = computed(() => [
    {
      name: '总订单',
      data: overview.value?.order_trend.total_order_counts ?? [],
      showAreaColor: true
    },
    {
      name: '已支付',
      data: overview.value?.order_trend.paid_order_counts ?? [],
      showAreaColor: true
    },
    {
      name: '未支付',
      data: overview.value?.order_trend.unpaid_order_counts ?? []
    }
  ])

  const collectionSeries = computed(() =>
    (overview.value?.collection_comparison.series ?? []).map((item) => ({
      name: item.label,
      data: item.data
    }))
  )

  const rechargeSeries = computed(() =>
    (overview.value?.recharge_comparison.series ?? []).map((item) => ({
      name: item.label,
      data: item.data
    }))
  )

  const periodCards = computed<CommercePeriod[]>(() => {
    const labels: Record<CommercePeriod['key'], string> = {
      day: '今日',
      month: '本月',
      year: '本年'
    }

    return (['day', 'month', 'year'] as CommercePeriod['key'][]).map((key) => {
      return (
        overview.value?.periods.find((item) => item.key === key) ?? {
          ...emptyPeriod(key, labels[key])
        }
      )
    })
  })

  const summaryCards = computed(() => [
    {
      key: 'users',
      label: '总用户',
      value: formatCount(summary.value.total_user_count),
      note: `今日新增 ${formatCount(summary.value.today_new_user_count)} 用户`,
      icon: 'ri:user-3-line'
    },
    {
      key: 'trade-count',
      label: '成交总笔数',
      value: formatCount(summary.value.total_paid_trade_count),
      note: `订单 ${formatCount(summary.value.total_paid_order_count)} / 充值 ${formatCount(summary.value.total_paid_recharge_count)}`,
      icon: 'ri:shopping-cart-2-line'
    },
    {
      key: 'balance-pool',
      label: '余额池',
      value: formatAmount(summary.value.total_balance_pool),
      note: '按商户余额汇总，不包含清理类操作',
      icon: 'ri:wallet-3-line'
    },
    {
      key: 'online-accounts',
      label: '在线通道',
      value: formatCount(summary.value.total_online_account_count),
      note: `微信 ${formatCount(summary.value.wx_online_account_count)} / 支付宝 ${formatCount(summary.value.alipay_online_account_count)} / QQ ${formatCount(summary.value.qq_online_account_count)}`,
      icon: 'ri:radar-line'
    }
  ])

  const focusItems = computed(() => [
    {
      label: '昨日交易订单',
      value: formatCount(summary.value.yesterday_paid_order_count),
      note: '昨日已支付订单'
    },
    {
      label: '昨日收款',
      value: formatAmount(summary.value.yesterday_paid_amount),
      note: '昨日已支付金额'
    },
    {
      label: '上周收款',
      value: formatAmount(summary.value.last_week_paid_amount),
      note: '上一完整自然周'
    },
    {
      label: '上月收款',
      value: formatAmount(summary.value.last_month_paid_amount),
      note: '上一完整自然月'
    }
  ])

  const snapshotItems = computed(() => [
    { label: '总充值笔数', value: formatCount(summary.value.total_paid_recharge_count) },
    { label: '今日充值笔数', value: formatCount(summary.value.today_paid_recharge_count) },
    { label: '昨日交易订单', value: formatCount(summary.value.yesterday_paid_order_count) },
    { label: '昨日收款金额', value: formatAmount(summary.value.yesterday_paid_amount) },
    { label: '上周交易金额', value: formatAmount(summary.value.last_week_paid_amount) },
    { label: '上月交易金额', value: formatAmount(summary.value.last_month_paid_amount) },
    { label: 'QQ 在线通道', value: formatCount(summary.value.qq_online_account_count) },
    { label: '微信在线通道', value: formatCount(summary.value.wx_online_account_count) },
    { label: '支付宝在线通道', value: formatCount(summary.value.alipay_online_account_count) },
    { label: '余额池', value: formatAmount(summary.value.total_balance_pool) }
  ])

  onMounted(() => {
    loadOverview()
  })

  async function loadOverview() {
    loading.value = true
    try {
      overview.value = await fetchGetCommerceOverview()
    } catch (_error) {
      ElMessage.error('商城总览加载失败')
    } finally {
      loading.value = false
    }
  }

  function goOrders() {
    router.push('/orders')
  }

  function goRecharges() {
    router.push('/recharge')
  }

  function emptySummary(): CommerceSummary {
    return {
      total_user_count: 0,
      total_paid_order_count: 0,
      total_paid_recharge_count: 0,
      total_paid_trade_count: 0,
      total_balance_pool: 0,
      total_online_account_count: 0,
      today_new_user_count: 0,
      today_paid_recharge_count: 0,
      yesterday_paid_order_count: 0,
      yesterday_paid_amount: 0,
      last_week_paid_amount: 0,
      last_month_paid_amount: 0,
      qq_online_account_count: 0,
      wx_online_account_count: 0,
      alipay_online_account_count: 0
    }
  }

  function emptyPeriod(key: CommercePeriod['key'], label: string): CommercePeriod {
    return {
      key,
      label,
      paid_order_count: 0,
      total_order_count: 0,
      unpaid_order_count: 0,
      success_rate: 0,
      paid_amount: 0,
      total_amount: 0
    }
  }

  function formatAmount(value: number, digits = 2) {
    return Number(value || 0).toLocaleString('zh-CN', {
      minimumFractionDigits: digits,
      maximumFractionDigits: digits
    })
  }

  function formatCount(value: number) {
    return Number(value || 0).toLocaleString('zh-CN', {
      maximumFractionDigits: 0
    })
  }

  function formatRate(value: number) {
    return `${Number(value || 0).toFixed(2)}%`
  }

  function periodTag(
    key: CommercePeriod['key']
  ): 'success' | 'warning' | 'info' | 'primary' | undefined {
    if (key === 'day') {
      return 'success'
    }

    if (key === 'month') {
      return 'primary'
    }

    return 'warning'
  }
</script>

<style scoped lang="scss">
  .dashboard-business-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .hero-card,
  .focus-card,
  .summary-card,
  .period-card,
  .panel-card {
    border: 1px solid var(--el-border-color-light);
  }

  .hero-card {
    background:
      radial-gradient(circle at top right, rgb(34 197 94 / 0.2), transparent 30%),
      linear-gradient(135deg, rgb(15 23 42 / 0.98), rgb(30 41 59 / 0.96));
  }

  .hero-content {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    min-height: 188px;
    align-items: center;
  }

  .hero-copy {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .hero-eyebrow {
    margin: 0;
    color: rgb(134 239 172 / 0.92);
    font-size: 12px;
    letter-spacing: 0.18em;
    text-transform: uppercase;
  }

  .hero-title {
    margin: 0;
    color: #f8fafc;
    font-size: 30px;
    font-weight: 700;
    line-height: 1.15;
  }

  .hero-desc {
    max-width: 760px;
    margin: 0;
    color: rgb(203 213 225 / 0.94);
    line-height: 1.8;
  }

  .hero-tags {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }

  .hero-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .focus-card,
  .summary-card,
  .period-card {
    background: linear-gradient(180deg, rgb(255 255 255 / 1), rgb(248 250 252 / 0.94));
  }

  .panel-card {
    background: linear-gradient(180deg, rgb(255 255 255 / 0.98), rgb(248 250 252 / 0.92));
  }

  .panel-head,
  .summary-head,
  .period-head {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    align-items: flex-start;
  }

  .panel-head h3,
  .summary-value,
  .period-head strong,
  .focus-item strong,
  .snapshot-item strong {
    margin: 0;
    color: #0f172a;
  }

  .panel-head p,
  .summary-note,
  .focus-item small,
  .snapshot-item span,
  .period-note {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.7;
  }

  .summary-icon {
    color: #1d4ed8;
    font-size: 20px;
  }

  .summary-value,
  .period-head strong {
    display: block;
    font-size: 28px;
    line-height: 1.15;
    font-variant-numeric: tabular-nums;
  }

  .summary-value {
    margin-top: 14px;
  }

  .summary-note {
    margin-top: 10px;
  }

  .summary-head span,
  .period-head p,
  .focus-item span,
  .snapshot-item span {
    color: #475569;
    font-size: 13px;
    font-weight: 600;
  }

  .focus-grid,
  .snapshot-grid {
    display: grid;
    gap: 12px;
  }

  .focus-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin-top: 8px;
  }

  .snapshot-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .focus-item,
  .snapshot-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 14px 16px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 16px;
    background: rgb(248 250 252 / 0.88);
  }

  .focus-item strong {
    font-size: 20px;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
  }

  .snapshot-item strong {
    font-size: 16px;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
    word-break: break-all;
  }

  .period-meta {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-top: 16px;
  }

  .period-meta span {
    display: flex;
    justify-content: center;
    padding: 10px 12px;
    border-radius: 12px;
    background: rgb(241 245 249 / 0.9);
    color: #334155;
    font-size: 12px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
  }

  .period-note {
    margin-top: 12px;
  }

  .readonly-alert {
    margin-top: 4px;
  }

  @media (width <= 1200px) {
    .hero-content {
      flex-direction: column;
      align-items: flex-start;
    }

    .hero-actions {
      justify-content: flex-start;
    }
  }

  @media (width <= 991px) {
    .focus-grid,
    .snapshot-grid,
    .period-meta {
      grid-template-columns: 1fr;
    }
  }
</style>
