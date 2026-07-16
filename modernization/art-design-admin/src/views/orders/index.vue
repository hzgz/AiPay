<template>
  <div class="orders-page art-full-height">
    <ArtSearchBar
      v-model="searchForm"
      :items="searchItems"
      @search="handleSearch"
      @reset="resetSearchParams"
    />

    <ElCard class="art-table-card">
      <ArtTableHeader v-model:columns="columnChecks" :loading="loading" @refresh="refreshData">
        <template #left>
          <ElSpace wrap>
            <ElTag effect="plain">订单 {{ summary.total_count }}</ElTag>
            <ElTag type="success" effect="plain">已支付 {{ summary.paid_count }}</ElTag>
            <ElTag type="warning" effect="plain">待支付 {{ summary.pending_count }}</ElTag>
            <ElTag type="primary" effect="plain">
              已支付金额 {{ formatAmount(summary.paid_amount) }}
            </ElTag>
            <ElTag effect="plain">手续费 {{ formatAmount(summary.fee_amount, 3) }}</ElTag>
            <ElTag :type="summary.success_rate >= 80 ? 'success' : 'warning'" effect="plain">
              成功率 {{ summary.success_rate }}%
            </ElTag>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        :loading="loading"
        :data="data"
        :columns="columns"
        :pagination="pagination"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <ElDrawer v-model="detailVisible" size="720px" destroy-on-close :title="detailTitle">
      <div v-loading="detailLoading" class="order-detail">
        <template v-if="activeOrder">
          <div class="drawer-section">
            <div class="drawer-grid">
              <div class="drawer-item">
                <span>商户</span>
                <strong>{{ displayAdminFixtureText(activeOrder.merchant_display) }}</strong>
              </div>
              <div class="drawer-item">
                <span>支付方式</span>
                <strong>{{ displayOrderType(activeOrder) }}</strong>
              </div>
              <div class="drawer-item">
                <span>通道</span>
                <strong>{{ displayOrderChannel(activeOrder) }}</strong>
              </div>
              <div class="drawer-item">
                <span>状态</span>
                <strong>{{ displayOrderStatus(activeOrder) }}</strong>
              </div>
              <div class="drawer-item">
                <span>订单金额</span>
                <strong>{{ formatAmount(activeOrder.money) }}</strong>
              </div>
              <div class="drawer-item">
                <span>实付金额</span>
                <strong>{{ formatAmount(activeOrder.settled_amount) }}</strong>
              </div>
              <div class="drawer-item">
                <span>手续费</span>
                <strong>{{ formatAmount(activeOrder.fee_amount, 3) }}</strong>
              </div>
              <div class="drawer-item">
                <span>创建时间</span>
                <strong>{{ activeOrder.create_time || '--' }}</strong>
              </div>
              <div class="drawer-item">
                <span>支付时间</span>
                <strong>{{ activeOrder.end_time || '--' }}</strong>
              </div>
              <div class="drawer-item">
                <span>来源地址</span>
                <strong>{{ activeOrder.ip || '--' }}</strong>
              </div>
            </div>
          </div>

          <div class="drawer-section">
            <h4>订单编号</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="系统订单号">
                {{ activeOrder.trade_no || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="商户订单号">
                {{ displayAdminFixtureText(activeOrder.out_trade_no) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="上游订单号">
                {{ activeOrder.upstream_trade_no || '--' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>回调地址</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="异步回调地址">
                {{ activeOrder.notify_url || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="同步跳转地址">
                {{ activeOrder.return_url || '--' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>回调备注</h4>
            <ElInput
              :model-value="displayAdminFixtureText(activeOrder.api_memo)"
              type="textarea"
              :rows="6"
              readonly
            />
          </div>
        </template>
      </div>
    </ElDrawer>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElTag } from 'element-plus'
  import { useTable } from '@/hooks/core/useTable'
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import { fetchGetOrderDetail, fetchGetOrderList } from '@/api/orders'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'
  import type { ApiResponse } from '@/utils/table/tableCache'

  defineOptions({ name: 'Orders' })

  type OrderListItem = Api.Orders.OrderListItem
  type OrderSummary = Api.Orders.OrderSummary

  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const activeOrder = ref<OrderListItem | null>(null)
  const detailTitle = computed(() =>
    displayAdminFixtureText(activeOrder.value?.name || activeOrder.value?.out_trade_no, '订单详情')
  )
  const summary = reactive<OrderSummary>(emptySummary())
  const searchForm = ref<{
    keyword?: string
    status?: string
    type?: string
    date_range?: string[]
  }>({})

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '搜索订单号、商户、商品、站点或账号编号'
      }
    },
    {
      label: '状态',
      key: 'status',
      type: 'select',
      props: {
        placeholder: '全部状态',
        options: [
          { label: '待支付', value: '0' },
          { label: '已支付', value: '1' }
        ]
      }
    },
    {
      label: '方式',
      key: 'type',
      type: 'select',
      props: {
        placeholder: '全部方式',
        options: [
          { label: '支付宝', value: 'alipay' },
          { label: '微信支付', value: 'wxpay' },
          { label: 'QQ 钱包', value: 'qqpay' },
          { label: 'USDT', value: 'usdt' },
          { label: '易支付支付宝', value: 'epay_ali' },
          { label: '易支付微信', value: 'epay_wechat' }
        ]
      }
    },
    {
      label: '日期',
      key: 'date_range',
      type: 'daterange',
      props: {
        type: 'daterange',
        valueFormat: 'YYYY-MM-DD',
        startPlaceholder: '开始日期',
        endPlaceholder: '结束日期',
        rangeSeparator: '至'
      }
    }
  ])

  const {
    columns,
    columnChecks,
    data,
    loading,
    pagination,
    getData,
    replaceSearchParams,
    resetSearchParams,
    handleSizeChange,
    handleCurrentChange,
    refreshData
  } = useTable({
    core: {
      apiFn: fetchGetOrderList,
      apiParams: {
        current: 1,
        size: 20
      },
      columnsFactory: () => [
        { type: 'globalIndex', width: 70, label: '序号' },
        {
          prop: 'order',
          label: '订单',
          minWidth: 320,
          formatter: (row) =>
            h('div', { class: 'order-cell' }, [
              h(
                'strong',
                { class: 'order-name' },
                displayAdminFixtureText(row.name || row.out_trade_no, '未命名订单')
              ),
              h('p', { class: 'order-sub' }, `系统单号：${row.trade_no || '--'}`),
              h(
                'p',
                { class: 'order-sub' },
                `商户单号：${displayAdminFixtureText(row.out_trade_no)}`
              ),
              h('p', { class: 'order-sub' }, `站点：${displayAdminFixtureText(row.sitename)}`)
            ])
        },
        {
          prop: 'merchant_display',
          label: '商户',
          minWidth: 150,
          formatter: (row) => displayAdminFixtureText(row.merchant_display)
        },
        {
          prop: 'channel',
          label: '支付',
          minWidth: 180,
          formatter: (row) =>
            h('div', { class: 'channel-cell' }, [
              h('strong', {}, displayOrderType(row)),
              h('p', { class: 'order-sub' }, displayOrderChannel(row))
            ])
        },
        {
          prop: 'amount',
          label: '金额',
          width: 160,
          align: 'right',
          formatter: (row) =>
            h('div', { class: 'amount-cell' }, [
              h('strong', {}, formatAmount(row.settled_amount)),
              h('p', { class: 'order-sub' }, `手续费 ${formatAmount(row.fee_amount, 3)}`)
            ])
        },
        {
          prop: 'status',
          label: '状态',
          width: 110,
          align: 'center',
          formatter: (row) =>
            h(ElTag, { type: tagType(row.status_type), effect: 'light' }, () => displayOrderStatus(row))
        },
        {
          prop: 'create_time',
          label: '创建时间',
          minWidth: 170
        },
        {
          prop: 'end_time',
          label: '支付时间',
          minWidth: 170,
          formatter: (row) => row.end_time || '--'
        },
        {
          prop: 'operation',
          label: '操作',
          width: 90,
          align: 'center',
          fixed: 'right',
          formatter: (row) =>
            h(ArtButtonTable, {
              type: 'view',
              title: '详情',
              onClick: () => openDetail(row)
            })
        }
      ]
    },
    transform: {
      responseAdapter: (response): ApiResponse<OrderListItem> =>
        response as unknown as ApiResponse<OrderListItem>
    },
    hooks: {
      onSuccess: (_rows, response) =>
        applyOrderSummary(response as unknown as Api.Orders.OrderList),
      onCacheHit: (_rows, response) =>
        applyOrderSummary(response as unknown as Api.Orders.OrderList)
    }
  })

  function handleSearch(params: Record<string, unknown>) {
    const dateRange = Array.isArray(params.date_range) ? params.date_range : []
    replaceSearchParams({
      keyword: params.keyword as string | undefined,
      status: params.status as string | undefined,
      type: params.type as string | undefined,
      start_date: (dateRange[0] as string) || undefined,
      end_date: (dateRange[1] as string) || undefined
    })
    getData()
  }

  async function openDetail(row: OrderListItem) {
    activeOrder.value = row
    detailVisible.value = true
    detailLoading.value = true

    try {
      const response = await fetchGetOrderDetail(row.id)
      activeOrder.value = response.item
    } catch (_error) {
      ElMessage.error('订单详情加载失败')
    } finally {
      detailLoading.value = false
    }
  }

  function formatAmount(value: number, digits = 2) {
    return Number(value || 0).toLocaleString('zh-CN', {
      minimumFractionDigits: digits,
      maximumFractionDigits: digits
    })
  }

  function displayOrderType(item?: Partial<OrderListItem> | null, fallback = '--') {
    return displayAdminFixtureText(item?.type_text || item?.type_label || item?.type, fallback)
  }

  function displayOrderChannel(item?: Partial<OrderListItem> | null, fallback = '--') {
    return displayAdminFixtureText(item?.channel_text || item?.channel_label, fallback)
  }

  function displayOrderStatus(item?: Partial<OrderListItem> | null, fallback = '--') {
    return displayAdminFixtureText(item?.status_text || item?.status_label, fallback)
  }

  function applyOrderSummary(response: Api.Orders.OrderList) {
    Object.assign(summary, response.summary || emptySummary())
  }

  function emptySummary(): OrderSummary {
    return {
      total_count: 0,
      paid_count: 0,
      pending_count: 0,
      unknown_status_count: 0,
      merchant_count: 0,
      gross_amount: 0,
      paid_amount: 0,
      pending_amount: 0,
      fee_amount: 0,
      success_rate: 0,
      generated_at: ''
    }
  }

  function tagType(
    value: string
  ): 'success' | 'warning' | 'info' | 'danger' | 'primary' | undefined {
    if (
      value === 'success' ||
      value === 'warning' ||
      value === 'info' ||
      value === 'danger' ||
      value === 'primary'
    ) {
      return value
    }
    return 'info'
  }
</script>

<style scoped lang="scss">
  .orders-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .order-cell,
  .channel-cell,
  .amount-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .order-name {
    color: #0f172a;
    font-size: 14px;
  }

  .order-detail {
    min-height: 260px;
  }

  .order-sub {
    margin: 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.6;
  }

  .drawer-section {
    margin-bottom: 24px;
  }

  .drawer-section h4 {
    margin: 0 0 12px;
    color: #0f172a;
    font-size: 15px;
  }

  .drawer-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .drawer-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 14px 16px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 14px;
    background: rgb(248 250 252 / 0.82);
  }

  .drawer-item span {
    color: #64748b;
    font-size: 12px;
  }

  .drawer-item strong {
    color: #0f172a;
    word-break: break-all;
  }

  @media (width <= 991px) {
    .drawer-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
