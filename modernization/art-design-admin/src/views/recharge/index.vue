<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <div class="recharge-page art-full-height">
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
            <ElTag effect="plain">充值 {{ summary.total_count }}</ElTag>
            <ElTag type="success" effect="plain">已支付 {{ summary.paid_count }}</ElTag>
            <ElTag type="warning" effect="plain">待支付 {{ summary.pending_count }}</ElTag>
            <ElTag type="primary" effect="plain">
              已支付金额 {{ formatAmount(summary.paid_amount) }}
            </ElTag>
            <ElTag effect="plain">待支付金额 {{ formatAmount(summary.pending_amount) }}</ElTag>
            <ElTag :type="summary.expired_pending_count > 0 ? 'danger' : 'info'" effect="plain">
              已过期待支付 {{ summary.expired_pending_count }}
            </ElTag>
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

    <ElDrawer
      v-model="detailVisible"
      size="760px"
      destroy-on-close
      :title="activeRecharge?.out_trade_no || '充值详情'"
    >
      <div v-loading="detailLoading" class="recharge-detail">
        <template v-if="activeRecharge">
          <div class="drawer-section">
            <div class="drawer-grid">
              <div class="drawer-item">
                <span>商户</span>
                <strong>{{ activeRecharge.merchant_display }}</strong>
              </div>
              <div class="drawer-item">
                <span>方式</span>
                <strong>{{ displayRechargeType(activeRecharge) }}</strong>
              </div>
              <div class="drawer-item">
                <span>收入类型</span>
                <strong>{{ displayRechargeRtype(activeRecharge) }}</strong>
              </div>
              <div class="drawer-item">
                <span>状态</span>
                <strong>{{ displayRechargeStatus(activeRecharge) }}</strong>
              </div>
              <div class="drawer-item">
                <span>充值金额</span>
                <strong>{{ formatAmount(activeRecharge.money) }}</strong>
              </div>
              <div class="drawer-item">
                <span>超时状态</span>
                <strong>{{ displayRechargeTimeoutStatus(activeRecharge) }}</strong>
              </div>
              <div class="drawer-item">
                <span>创建时间</span>
                <strong>{{ activeRecharge.create_time || '--' }}</strong>
              </div>
              <div class="drawer-item">
                <span>支付时间</span>
                <strong>{{ activeRecharge.end_time || '--' }}</strong>
              </div>
              <div class="drawer-item">
                <span>过期时间</span>
                <strong>{{ activeRecharge.expires_at || '--' }}</strong>
              </div>
              <div class="drawer-item">
                <span>更新时间</span>
                <strong>{{ activeRecharge.update_time || '--' }}</strong>
              </div>
            </div>
          </div>

          <div class="drawer-section">
            <h4>充值编号</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="记录 ID">
                {{ activeRecharge.id }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="本地订单号">
                {{ activeRecharge.out_trade_no || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="商户 ID">
                {{ activeRecharge.user_id || '--' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>商户资料</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="商户账号">
                {{ activeRecharge.merchant_username || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="联系人">
                {{ activeRecharge.merchant_name || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="Email">
                {{ activeRecharge.merchant_email || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="手机号">
                {{ activeRecharge.merchant_mobile || '--' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>二维码地址</h4>
            <ElInput
              :model-value="activeRecharge.qrcode_url || '暂无二维码'"
              type="textarea"
              :rows="3"
              readonly
            />
          </div>

          <div class="drawer-section">
            <h4>附加参数</h4>
            <ElInput
              :model-value="activeRecharge.regdata_text || '暂无附加参数'"
              type="textarea"
              :rows="10"
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
  import ArtButtonTable from '@/components/core/forms/artButtonTable/index.vue'
  import { fetchGetRechargeDetail, fetchGetRechargeList } from '@/api/recharge'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'
  import type { ApiResponse } from '@/utils/table/tableCache'

  defineOptions({ name: 'RechargeRecords' })

  type RechargeListItem = Api.Recharges.RechargeListItem
  type RechargeSummary = Api.Recharges.RechargeSummary

  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const activeRecharge = ref<RechargeListItem | null>(null)
  const summary = reactive<RechargeSummary>(emptySummary())
  const searchForm = ref<{
    keyword?: string
    status?: string
    type?: string
    rtype?: string
    date_range?: string[]
  }>({})

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '搜索订单号、商户、邮箱、手机号或记录 ID'
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
          { label: '默认通道', value: 'default' },
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
      label: '收入类型',
      key: 'rtype',
      type: 'select',
      props: {
        placeholder: '全部收入类型',
        options: [
          { label: '商户充值', value: '0' },
          { label: '付费注册', value: '1' }
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
      apiFn: fetchGetRechargeList,
      apiParams: {
        current: 1,
        size: 20
      },
      columnsFactory: () => [
        { type: 'globalIndex', width: 70, label: '序号' },
        {
          prop: 'out_trade_no',
          label: '充值记录',
          minWidth: 300,
          formatter: (row) =>
            h('div', { class: 'recharge-cell' }, [
              h('strong', { class: 'cell-title' }, row.out_trade_no || `充值 #${row.id}`),
              h('p', { class: 'cell-sub' }, `方式：${displayRechargeType(row)} / ${displayRechargeRtype(row)}`),
              h('p', { class: 'cell-sub' }, row.regdata_preview)
            ])
        },
        {
          prop: 'merchant_display',
          label: '商户',
          minWidth: 180,
          formatter: (row) =>
            h('div', { class: 'merchant-cell' }, [
              h('strong', { class: 'cell-title' }, row.merchant_display),
              h(
                'p',
                { class: 'cell-sub' },
                row.merchant_email || row.merchant_mobile || `商户 ID：${row.user_id || '--'}`
              )
            ])
        },
        {
          prop: 'money',
          label: '金额',
          width: 140,
          align: 'right',
          formatter: (row) =>
            h('div', { class: 'amount-cell' }, [
              h('strong', {}, formatAmount(row.money)),
              h('p', { class: 'cell-sub' }, displayRechargeTimeoutStatus(row))
            ])
        },
        {
          prop: 'status',
          label: '状态',
          width: 120,
          align: 'center',
          formatter: (row) =>
            h(ElTag, { type: tagType(row.status_type), effect: 'light' }, () => displayRechargeStatus(row))
        },
        {
          prop: 'create_time',
          label: '创建时间',
          minWidth: 170,
          formatter: (row) => row.create_time || '--'
        },
        {
          prop: 'expires_at',
          label: '过期时间',
          minWidth: 170,
          formatter: (row) => row.expires_at || '--'
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
      responseAdapter: (response): ApiResponse<RechargeListItem> =>
        response as unknown as ApiResponse<RechargeListItem>
    },
    hooks: {
      onSuccess: (_rows, response) =>
        applyRechargeSummary(response as unknown as Api.Recharges.RechargeList),
      onCacheHit: (_rows, response) =>
        applyRechargeSummary(response as unknown as Api.Recharges.RechargeList)
    }
  })

  function handleSearch(params: Record<string, unknown>) {
    const dateRange = Array.isArray(params.date_range) ? params.date_range : []
    replaceSearchParams({
      keyword: params.keyword as string | undefined,
      status: params.status as string | undefined,
      type: params.type as string | undefined,
      rtype: params.rtype as string | undefined,
      start_date: (dateRange[0] as string) || undefined,
      end_date: (dateRange[1] as string) || undefined
    })
    getData()
  }

  async function openDetail(row: RechargeListItem) {
    activeRecharge.value = row
    detailVisible.value = true
    detailLoading.value = true

    try {
      const response = await fetchGetRechargeDetail(row.id)
      activeRecharge.value = response.item
    } catch (_error) {
      ElMessage.error('充值详情加载失败')
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

  function displayRechargeType(item?: Partial<RechargeListItem> | null, fallback = '--') {
    return displayAdminFixtureText(item?.type_text || item?.type_label || item?.type, fallback)
  }

  function displayRechargeRtype(item?: Partial<RechargeListItem> | null, fallback = '--') {
    return displayAdminFixtureText(item?.rtype_text || item?.rtype_label, fallback)
  }

  function displayRechargeStatus(item?: Partial<RechargeListItem> | null, fallback = '--') {
    return displayAdminFixtureText(item?.status_text || item?.status_label, fallback)
  }

  function displayRechargeTimeoutStatus(
    item?: Partial<RechargeListItem> | null,
    fallback = '--'
  ) {
    return displayAdminFixtureText(item?.timeout_status_text || item?.timeout_status, fallback)
  }

  function applyRechargeSummary(response: Api.Recharges.RechargeList) {
    Object.assign(summary, response.summary || emptySummary())
  }

  function emptySummary(): RechargeSummary {
    return {
      total_count: 0,
      paid_count: 0,
      pending_count: 0,
      unknown_status_count: 0,
      merchant_count: 0,
      merchant_recharge_count: 0,
      registration_count: 0,
      expired_pending_count: 0,
      gross_amount: 0,
      paid_amount: 0,
      pending_amount: 0,
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
  .recharge-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
    --detail-card-border: var(--el-border-color-lighter);
    --detail-card-bg: rgb(248 250 252 / 0.82);
    --detail-title-color: #0f172a;
    --detail-text-color: #64748b;
  }

  :global(html.dark .recharge-page ){
    --detail-card-border: rgb(71 85 105 / 0.42);
    --detail-card-bg: rgb(15 23 42 / 0.84);
    --detail-title-color: #e2e8f0;
    --detail-text-color: #94a3b8;
  }

  .recharge-cell,
  .merchant-cell,
  .amount-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .cell-title {
    color: var(--detail-title-color);
    font-size: 14px;
    word-break: break-all;
  }

  .cell-sub {
    margin: 0;
    color: var(--detail-text-color);
    font-size: 12px;
    line-height: 1.6;
    word-break: break-all;
  }

  .recharge-detail {
    min-height: 260px;
  }

  .drawer-section {
    margin-bottom: 24px;
  }

  .drawer-section h4 {
    margin: 0 0 12px;
    color: var(--detail-title-color);
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
    border: 1px solid var(--detail-card-border);
    border-radius: 14px;
    background: var(--detail-card-bg);
  }

  .drawer-item span {
    color: var(--detail-text-color);
    font-size: 12px;
  }

  .drawer-item strong {
    color: var(--detail-title-color);
    word-break: break-all;
  }

  @media (width <= 991px) {
    .drawer-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
