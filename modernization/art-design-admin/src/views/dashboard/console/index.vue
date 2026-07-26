<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <div class="dashboard-console-page">
    <ElRow :gutter="20" class="hero-row">
      <ElCol :xs="24" :lg="16">
        <ElCard class="hero-card" shadow="never" v-loading="loading">
          <div class="hero-content">
            <div>
              <p class="hero-eyebrow">AiPay 控制台</p>
              <h2 class="hero-title">支付经营总览</h2>
              <p class="hero-desc">
                这套控制台直接读取正式支付接口，优先展示成交走势、待处理事务、账号在线情况和支付方式分布。
              </p>
              <div class="hero-tags">
                <ElTag type="warning" effect="plain">
                  待支付 {{ summary.pending_order_count }} 笔
                </ElTag>
                <ElTag type="danger" effect="plain">
                  待充值 {{ dutyBoard.pending_recharge_count }} 笔
                </ElTag>
                <ElTag type="info" effect="plain">
                  待处理工单 {{ dutyBoard.pending_ticket_count }} 个
                </ElTag>
                <ElTag type="success" effect="plain">
                  在线账号 {{ dutyBoard.online_account_count }} 个
                </ElTag>
                <ElTag effect="plain">数据更新 {{ overview?.generated_at || '--' }}</ElTag>
              </div>
            </div>
            <div class="hero-actions">
              <ElButton type="primary" @click="loadOverview" v-ripple>刷新总览</ElButton>
              <ElButton plain @click="goOrders" v-ripple>查看订单</ElButton>
            </div>
          </div>
        </ElCard>
      </ElCol>

      <ElCol :xs="24" :sm="12" :lg="4">
        <ElCard class="highlight-card warning" shadow="never" v-loading="loading">
          <p class="highlight-label">待支付订单</p>
          <strong class="highlight-value">{{ summary.pending_order_count }}</strong>
          <span class="highlight-sub">需要继续跟进的未完成交易</span>
        </ElCard>
      </ElCol>

      <ElCol :xs="24" :sm="12" :lg="4">
        <ElCard class="highlight-card success" shadow="never" v-loading="loading">
          <p class="highlight-label">待处理工单</p>
          <strong class="highlight-value">{{ dutyBoard.pending_ticket_count }}</strong>
          <span class="highlight-sub">
            新工单 {{ dutyBoard.new_ticket_count }} / 处理中 {{ dutyBoard.processing_ticket_count }}
          </span>
        </ElCard>
      </ElCol>
    </ElRow>

    <ElRow :gutter="20" class="summary-row">
      <ElCol v-for="card in summaryCards" :key="card.key" :xs="24" :sm="12" :xl="8">
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

    <ElRow :gutter="20">
      <ElCol :xs="24" :xl="15">
        <ElCard class="panel-card" shadow="never">
          <div class="panel-head">
            <div>
              <h3>最近 7 天订单趋势</h3>
              <p>对比新增订单与成功订单，帮助确认流量和转化是否同步。</p>
            </div>
          </div>
          <ArtLineChart
            :loading="loading"
            :data="trendSeries"
            :x-axis-data="trendLabels"
            :show-area-color="true"
            :show-legend="true"
            legend-position="top"
            height="20rem"
          />
        </ElCard>
      </ElCol>

      <ElCol :xs="24" :xl="9">
        <ElCard class="panel-card" shadow="never">
          <div class="panel-head">
            <div>
              <h3>支付方式分布</h3>
              <p>按订单笔数统计主要支付方式，并展示已成交金额。</p>
            </div>
          </div>
          <ArtRingChart
            :loading="loading"
            :data="paymentChartData"
            :center-text="`共 ${summary.total_order_count} 笔`"
            :show-legend="true"
            legend-position="bottom"
            height="18rem"
          />
          <div class="channel-list">
            <div v-for="item in displayPaymentDistribution" :key="item.label" class="channel-item">
              <div>
                <strong>{{ item.displayLabel }}</strong>
                <p>{{ item.order_count }} 笔 / 占比 {{ item.share }}%</p>
              </div>
              <span>{{ formatAmount(item.paid_amount) }}</span>
            </div>
          </div>
        </ElCard>
      </ElCol>
    </ElRow>

    <ElRow :gutter="20">
      <ElCol :xs="24" :xl="16">
        <ElCard class="panel-card" shadow="never">
          <div class="panel-head">
            <div>
              <h3>最新订单</h3>
              <p>优先展示最近进入系统的订单，便于值班时快速定位异常。</p>
            </div>
            <ElButton text type="primary" @click="goOrders">进入订单中心</ElButton>
          </div>

          <ElTable :data="displayRecentOrders" stripe class="recent-table" v-loading="loading">
            <ElTableColumn label="订单" min-width="260">
              <template #default="{ row }">
                <div class="order-main">
                  <strong>{{ row.displayName }}</strong>
                  <p>{{ row.trade_no }}</p>
                </div>
              </template>
            </ElTableColumn>
            <ElTableColumn label="商户" min-width="150">
              <template #default="{ row }">{{ row.displayMerchant }}</template>
            </ElTableColumn>
            <ElTableColumn label="金额" width="140" align="right">
              <template #default="{ row }">{{ formatAmount(row.settled_amount) }}</template>
            </ElTableColumn>
            <ElTableColumn label="状态" width="110" align="center">
              <template #default="{ row }">
                <ElTag :type="tagType(row.status_type)" effect="light">{{ row.status_label }}</ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn prop="create_time" label="创建时间" min-width="170" />
          </ElTable>
        </ElCard>
      </ElCol>

      <ElCol :xs="24" :xl="8">
        <ElCard class="panel-card" shadow="never">
          <div class="panel-head">
            <div>
              <h3>值班摘要</h3>
              <p>把值班时优先要处理的事务独立出来，方便快速确认处理顺序。</p>
            </div>
          </div>

          <div class="brief-list">
            <div v-for="item in briefItems" :key="item.key" class="brief-item">
              <div class="brief-copy">
                <span>{{ item.label }}</span>
                <p class="brief-note">{{ item.note }}</p>
              </div>
              <strong>{{ item.value }}</strong>
            </div>
          </div>
        </ElCard>
      </ElCol>
    </ElRow>
  </div>
</template>

<script setup lang="ts">
  import { Icon } from '@iconify/vue'
  import { useRouter } from 'vue-router'
  import { fetchGetDashboardOverview } from '@/api/dashboard'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'

  defineOptions({ name: 'Console' })

  const router = useRouter()
  const loading = ref(false)
  const overview = ref<Api.Dashboard.OverviewResponse | null>(null)

  const summary = computed<Api.Dashboard.Summary>(() => {
    return (
      overview.value?.summary ?? {
        today_order_count: 0,
        today_paid_order_count: 0,
        today_paid_amount: 0,
        today_fee_amount: 0,
        total_order_count: 0,
        total_paid_order_count: 0,
        total_paid_amount: 0,
        total_fee_amount: 0,
        pending_order_count: 0,
        merchant_count: 0,
        success_rate: 0
      }
    )
  })

  const dutyBoard = computed<Api.Dashboard.DutyBoard>(() => {
    return (
      overview.value?.duty_board ?? {
        pending_recharge_count: 0,
        pending_recharge_amount: 0,
        new_ticket_count: 0,
        processing_ticket_count: 0,
        pending_ticket_count: 0,
        online_account_count: 0,
        enabled_account_count: 0
      }
    )
  })

  const paymentDistribution = computed(() => overview.value?.payment_distribution ?? [])
  const recentOrders = computed(() => overview.value?.recent_orders ?? [])
  const displayPaymentDistribution = computed(() =>
    paymentDistribution.value.map((item) => ({
      ...item,
      displayLabel: displayAdminFixtureText(item.label)
    }))
  )
  const displayRecentOrders = computed(() =>
    recentOrders.value.map((row) => ({
      ...row,
      displayName: displayAdminFixtureText(row.name || row.out_trade_no, row.out_trade_no || '--'),
      displayMerchant: displayAdminFixtureText(row.merchant_display)
    }))
  )
  const trendLabels = computed(() => overview.value?.trend.labels ?? [])

  const trendSeries = computed(() => [
    {
      name: '新增订单',
      data: overview.value?.trend.order_counts ?? [],
      showAreaColor: true
    },
    {
      name: '成功订单',
      data: overview.value?.trend.paid_order_counts ?? [],
      showAreaColor: true
    }
  ])

  const paymentChartData = computed(() =>
    displayPaymentDistribution.value.map((item) => ({
      name: item.displayLabel,
      value: item.order_count
    }))
  )

  const summaryCards = computed(() => [
    {
      key: 'today-orders',
      label: '今日订单',
      value: `${summary.value.today_order_count} 笔`,
      note: `成功 ${summary.value.today_paid_order_count} 笔`,
      icon: 'ri:shopping-bag-3-line'
    },
    {
      key: 'today-amount',
      label: '今日成交',
      value: formatAmount(summary.value.today_paid_amount),
      note: `手续费 ${formatAmount(summary.value.today_fee_amount, 3)}`,
      icon: 'ri:funds-line'
    },
    {
      key: 'total-orders',
      label: '累计订单',
      value: `${summary.value.total_order_count} 笔`,
      note: `已支付 ${summary.value.total_paid_order_count} 笔`,
      icon: 'ri:stack-line'
    },
    {
      key: 'total-amount',
      label: '累计成交',
      value: formatAmount(summary.value.total_paid_amount),
      note: `手续费 ${formatAmount(summary.value.total_fee_amount, 3)}`,
      icon: 'ri:line-chart-line'
    },
    {
      key: 'success-rate',
      label: '整体成功率',
      value: `${summary.value.success_rate}%`,
      note: '按订单总量计算',
      icon: 'ri:medal-line'
    },
    {
      key: 'merchant-count',
      label: '商户总数',
      value: `${summary.value.merchant_count} 个`,
      note: '平台当前可服务商户',
      icon: 'ri:user-star-line'
    }
  ])

  const briefItems = computed(() => [
    {
      key: 'pending-orders',
      label: '待支付订单',
      value: `${summary.value.pending_order_count} 笔`,
      note: '等待用户完成付款'
    },
    {
      key: 'pending-recharges',
      label: '待充值订单',
      value: `${dutyBoard.value.pending_recharge_count} 笔`,
      note: `待充值金额 ${formatAmount(dutyBoard.value.pending_recharge_amount)}`
    },
    {
      key: 'pending-tickets',
      label: '待处理工单',
      value: `${dutyBoard.value.pending_ticket_count} 个`,
      note: `新工单 ${dutyBoard.value.new_ticket_count} / 处理中 ${dutyBoard.value.processing_ticket_count}`
    },
    {
      key: 'online-accounts',
      label: '在线账号',
      value: `${dutyBoard.value.online_account_count} 个`,
      note: `已启用 ${dutyBoard.value.enabled_account_count} 个`
    },
    {
      key: 'today-orders',
      label: '今日订单',
      value: `${summary.value.today_order_count} 笔`,
      note: `成功 ${summary.value.today_paid_order_count} 笔`
    },
    {
      key: 'today-amount',
      label: '今日成交',
      value: formatAmount(summary.value.today_paid_amount),
      note: `手续费 ${formatAmount(summary.value.today_fee_amount, 3)}`
    },
    {
      key: 'total-orders',
      label: '累计订单',
      value: `${summary.value.total_order_count} 笔`,
      note: `已支付 ${summary.value.total_paid_order_count} 笔`
    },
    {
      key: 'total-amount',
      label: '累计成交',
      value: formatAmount(summary.value.total_paid_amount),
      note: `累计手续费 ${formatAmount(summary.value.total_fee_amount, 3)}`
    }
  ])

  onMounted(() => {
    loadOverview()
  })

  async function loadOverview() {
    loading.value = true
    try {
      overview.value = await fetchGetDashboardOverview()
    } finally {
      loading.value = false
    }
  }

  function goOrders() {
    router.push('/orders')
  }

  function formatAmount(value: number, digits = 2) {
    return Number(value || 0).toLocaleString('zh-CN', {
      minimumFractionDigits: digits,
      maximumFractionDigits: digits
    })
  }

  function tagType(
    value: string
  ): 'success' | 'warning' | 'info' | 'danger' | 'primary' | undefined {
    if (value === 'success' || value === 'warning' || value === 'info') {
      return value
    }
    return 'info'
  }
</script>

<style scoped lang="scss">
  .dashboard-console-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
    --console-card-border: var(--el-border-color-light);
    --console-highlight-bg: linear-gradient(180deg, rgb(255 255 255 / 0.98), rgb(248 250 252 / 0.96));
    --console-summary-bg: linear-gradient(180deg, rgb(255 255 255 / 1), rgb(248 250 252 / 0.94));
    --console-list-bg: rgb(248 250 252 / 0.76);
    --console-title-color: #0f172a;
    --console-text-color: #475569;
    --console-muted-color: #64748b;
  }

  :global(html.dark .dashboard-console-page ){
    --console-card-border: rgb(71 85 105 / 0.42);
    --console-highlight-bg: linear-gradient(180deg, rgb(30 41 59 / 0.94), rgb(15 23 42 / 0.9));
    --console-summary-bg: linear-gradient(180deg, rgb(17 24 39 / 0.94), rgb(15 23 42 / 0.88));
    --console-list-bg: rgb(15 23 42 / 0.76);
    --console-title-color: #e2e8f0;
    --console-text-color: #cbd5e1;
    --console-muted-color: #94a3b8;
  }

  .hero-row,
  .summary-row {
    margin-bottom: 0;
  }

  .hero-card,
  .highlight-card,
  .summary-card,
  .panel-card {
    border: 1px solid var(--console-card-border);
  }

  .hero-card {
    background:
      radial-gradient(circle at top right, rgb(217 119 6 / 0.16), transparent 30%),
      linear-gradient(135deg, rgb(30 64 175 / 0.95), rgb(59 130 246 / 0.88));
  }

  .hero-content {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    min-height: 168px;
    align-items: center;
  }

  .hero-eyebrow {
    margin: 0 0 10px;
    color: rgb(253 230 138 / 0.95);
    font-size: 12px;
    letter-spacing: 0.08em;
  }

  .hero-title {
    margin: 0;
    color: #fff;
    font-size: 30px;
    font-weight: 700;
    line-height: 1.15;
  }

  .hero-desc {
    max-width: 720px;
    margin: 14px 0 0;
    color: rgb(219 234 254 / 0.92);
    line-height: 1.75;
  }

  .hero-tags {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 18px;
  }

  .hero-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .highlight-card {
    height: 100%;
    min-height: 168px;
    background: var(--console-highlight-bg);
  }

  .highlight-card.warning {
    border-color: rgb(253 186 116 / 0.65);
  }

  .highlight-card.success {
    border-color: rgb(134 239 172 / 0.75);
  }

  .highlight-card :deep(.el-card__body) {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 10px;
    min-height: 168px;
  }

  .highlight-label,
  .summary-head span {
    color: var(--console-text-color);
    font-size: 13px;
    font-weight: 600;
  }

  .highlight-value {
    color: var(--console-title-color);
    font-size: 38px;
    line-height: 1;
    font-variant-numeric: tabular-nums;
  }

  .highlight-sub,
  .summary-note,
  .panel-head p,
  .order-main p,
  .channel-item p {
    color: var(--console-muted-color);
    font-size: 13px;
    line-height: 1.7;
  }

  .summary-card {
    background: var(--console-summary-bg);
  }

  .summary-head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
  }

  .summary-icon {
    color: #1d4ed8;
    font-size: 20px;
  }

  .summary-value {
    display: block;
    margin-top: 14px;
    color: var(--console-title-color);
    font-size: 28px;
    font-weight: 700;
    line-height: 1.2;
  }

  .summary-note {
    margin: 10px 0 0;
  }

  .panel-head {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: flex-start;
    margin-bottom: 14px;
  }

  .panel-head h3 {
    margin: 0;
    color: var(--console-title-color);
    font-size: 18px;
  }

  .panel-head p {
    margin: 6px 0 0;
  }

  .channel-list,
  .brief-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 12px;
  }

  .channel-item,
  .brief-item {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
    padding: 12px 14px;
    border: 1px solid var(--console-card-border);
    border-radius: 14px;
    background: var(--console-list-bg);
  }

  .channel-item strong,
  .brief-item strong,
  .order-main strong {
    color: var(--console-title-color);
  }

  .channel-item p,
  .order-main p {
    margin: 3px 0 0;
  }

  .brief-item span {
    color: var(--console-text-color);
    font-size: 13px;
    font-weight: 600;
  }

  .brief-copy {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 6px;
  }

  .brief-note {
    margin: 0;
    color: var(--console-muted-color);
    font-size: 12px;
    line-height: 1.6;
  }

  .brief-item strong {
    font-size: 16px;
    text-align: right;
  }

  .order-main p {
    font-size: 12px;
  }

  .recent-table {
    width: 100%;
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
</style>
