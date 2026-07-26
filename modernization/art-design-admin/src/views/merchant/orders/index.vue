<template>
  <div class="merchant-page">
    <section class="merchant-page-header">
      <div class="merchant-page-header__title">
        <h1>订单中心</h1>
        <p>查看交易订单、支付状态、通道归属和详细回调信息。</p>
      </div>

      <div v-if="!loading" class="merchant-chip-row">
        <span class="merchant-chip">成功率 {{ summary.success_rate ?? 0 }}%</span>
        <span class="merchant-chip">最近订单 {{ summary.last_order_time || '--' }}</span>
        <span class="merchant-chip">{{ callbackReplayLabel }}</span>
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
            <h2>订单列表</h2>
            <p>支持按商户单号、平台单号和支付状态筛选。</p>
          </div>

          <div class="merchant-toolbar-pills">
            <div class="merchant-toolbar-pill">
              <span>记录</span>
              <strong>{{ pagination.total }}</strong>
            </div>
            <div class="merchant-toolbar-pill">
              <span>状态</span>
              <strong>{{ currentStatusLabel }}</strong>
            </div>
            <div v-if="filters.keyword" class="merchant-toolbar-pill">
              <span>关键词</span>
              <strong>{{ filters.keyword }}</strong>
            </div>
          </div>
        </div>

        <div class="merchant-table-toolbar">
          <div class="merchant-table-toolbar__filters merchant-table-toolbar__filters--orders">
            <ElInput
              v-model.trim="filters.keyword"
              placeholder="搜索平台单号或商户单号"
              clearable
              @keyup.enter="loadOrders(true)"
            >
              <template #prefix>
                <Icon icon="ri:search-line" />
              </template>
            </ElInput>

            <ElSelect
              v-model="filters.status"
              clearable
              placeholder="支付状态"
              style="width: 160px"
            >
              <ElOption label="待支付" value="0" />
              <ElOption label="已支付" value="1" />
            </ElSelect>

            <ElButton type="primary" @click="loadOrders(true)">查询</ElButton>
            <ElButton plain :disabled="!hasActiveFilters" @click="resetFilters">重置</ElButton>
          </div>
        </div>

        <div class="merchant-note merchant-order-note">{{ actionTip }}</div>

        <ElTable :data="records" empty-text="暂无订单记录">
          <ElTableColumn label="平台单号" min-width="180" show-overflow-tooltip>
            <template #default="{ row }">
              {{ row.trade_no || '--' }}
            </template>
          </ElTableColumn>

          <ElTableColumn label="商户单号" min-width="180" show-overflow-tooltip>
            <template #default="{ row }">
              {{ formatMerchantRecordCode(row.out_trade_no) }}
            </template>
          </ElTableColumn>

          <ElTableColumn prop="type_label" label="支付方式" width="120">
            <template #default="{ row }">
              {{ translateMerchantText(row.type_label) }}
            </template>
          </ElTableColumn>

          <ElTableColumn prop="channel_label" label="通道" min-width="180">
            <template #default="{ row }">
              {{ translateMerchantText(row.channel_label) }}
            </template>
          </ElTableColumn>

          <ElTableColumn label="订单金额" width="120">
            <template #default="{ row }">
              {{ money(row.money) }}
            </template>
          </ElTableColumn>

          <ElTableColumn label="到账金额" width="120">
            <template #default="{ row }">
              {{ money(row.settled_amount) }}
            </template>
          </ElTableColumn>

          <ElTableColumn prop="status_label" label="状态" width="110">
            <template #default="{ row }">
              <ElTag :type="row.status_badge" effect="plain">
                {{ translateMerchantText(row.status_label) }}
              </ElTag>
            </template>
          </ElTableColumn>

          <ElTableColumn prop="create_time" label="创建时间" min-width="180" />

          <ElTableColumn
            label="操作"
            width="180"
            fixed="right"
            align="center"
            class-name="merchant-order-action-column"
          >
            <template #default="{ row }">
              <div class="table-actions">
                <ElButton
                  type="primary"
                  size="small"
                  plain
                  class="table-action-link"
                  @click="openDetail(row)"
                >
                  详情
                </ElButton>
                <ElButton
                  type="success"
                  size="small"
                  plain
                  class="table-action-link"
                  :disabled="!canReplayCallback(row)"
                  :loading="replayingId === Number(row.id)"
                  @click="handleReplay(row)"
                >
                  重放回调
                </ElButton>
              </div>
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

      <ElDrawer v-model="detailVisible" size="560px" class="merchant-order-drawer">
        <template #header>
          <div class="merchant-drawer-head">
            <div class="merchant-drawer-head__copy">
              <strong>订单详情</strong>
              <span>{{ detail?.trade_no || '查看单笔订单的交易回调与金额明细' }}</span>
            </div>

            <ElTag
              v-if="detail"
              class="merchant-drawer-head__status"
              :type="detail.status_badge"
              effect="plain"
            >
              {{ translateMerchantText(detail.status_label) }}
            </ElTag>
          </div>
        </template>

        <div v-if="detailLoading" class="merchant-state-card">
          <ElSkeleton :rows="6" animated />
        </div>

        <div v-else-if="detail" class="merchant-detail-stack">
          <section class="merchant-soft-panel">
            <div class="merchant-detail-section__head">
              <strong>基础信息</strong>
              <span>创建于 {{ detail.create_time || '--' }}</span>
            </div>

            <div class="merchant-kv-grid merchant-kv-grid--detail">
              <div class="merchant-kv-item">
                <span>平台单号</span>
                <div>{{ detail.trade_no || '--' }}</div>
              </div>
              <div class="merchant-kv-item">
                <span>商户单号</span>
                <div>{{ formatMerchantRecordCode(detail.out_trade_no) }}</div>
              </div>
              <div class="merchant-kv-item">
                <span>支付方式</span>
                <div>{{ translateMerchantText(detail.type_label) }}</div>
              </div>
              <div class="merchant-kv-item">
                <span>通道</span>
                <div>{{ translateMerchantText(detail.channel_label) }}</div>
              </div>
              <div class="merchant-kv-item">
                <span>订单金额</span>
                <div>{{ money(detail.money) }}</div>
              </div>
              <div class="merchant-kv-item">
                <span>到账金额</span>
                <div>{{ money(detail.settled_amount) }}</div>
              </div>
            </div>
          </section>

          <section class="merchant-soft-panel">
            <div class="merchant-detail-section__head">
              <strong>回调与跳转</strong>
              <span>仅已支付订单支持重放</span>
            </div>

            <div class="merchant-kv-grid merchant-kv-grid--detail">
              <div class="merchant-kv-item">
                <span>异步通知</span>
                <div>{{ detail.notify_url || '--' }}</div>
              </div>
              <div class="merchant-kv-item">
                <span>同步跳转</span>
                <div>{{ detail.return_url || '--' }}</div>
              </div>
            </div>

            <div class="merchant-detail-actions">
              <ElButton
                type="success"
                plain
                :disabled="!canReplayCallback(detail)"
                :loading="replayingId === Number(detail.id)"
                @click="handleReplay(detail)"
              >
                重放当前订单回调
              </ElButton>
              <span class="merchant-detail-tip"
                >仅已支付订单支持回调重放，状态重置入口已关闭。</span
              >
            </div>
          </section>

          <section class="merchant-soft-panel">
            <div class="merchant-detail-section__head">
              <strong>接口备注</strong>
              <span>保留原始扩展信息，便于核对问题单</span>
            </div>

            <div class="merchant-code-block">{{ formatApiMemo(detail.api_memo) }}</div>
          </section>
        </div>
      </ElDrawer>
    </template>
  </div>
</template>

<script setup lang="ts">
  import { Icon } from '@iconify/vue'
  import { ElMessage } from 'element-plus'
  import {
    MerchantApiError,
    fetchMerchantOrderDetail,
    fetchMerchantOrders,
    replayMerchantOrderCallback
  } from '@/api/merchant'
  import { formatMerchantRecordCode, translateMerchantText } from '../shared/text'

  defineOptions({ name: 'MerchantOrders' })

  const loading = ref(true)
  const records = ref<Record<string, any>[]>([])
  const summary = ref<Record<string, any>>({})
  const writeActions = ref<Record<string, any>>({})
  const replayingId = ref(0)

  const pagination = reactive({
    current: 1,
    size: 10,
    total: 0
  })
  const filters = reactive({
    keyword: '',
    status: ''
  })

  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const detail = ref<Record<string, any> | null>(null)
  const detailOrderId = ref(0)

  const summaryCards = computed(() => [
    {
      label: '订单总数',
      value: String(summary.value.total_count ?? 0),
      hint: '当前筛选条件下的全部订单',
      icon: 'ri:file-list-3-line'
    },
    {
      label: '已支付订单',
      value: String(summary.value.paid_count ?? 0),
      hint: '已完成支付并通知成功的订单',
      icon: 'ri:checkbox-circle-line'
    },
    {
      label: '支付金额',
      value: money(summary.value.paid_amount),
      hint: '当前筛选结果内的累计支付金额',
      icon: 'ri:bank-card-line'
    },
    {
      label: '待支付金额',
      value: money(summary.value.pending_amount),
      hint: '尚未完成支付的订单金额',
      icon: 'ri:time-line'
    }
  ])

  const currentStatusLabel = computed(() => {
    if (filters.status === '1') {
      return '已支付'
    }

    if (filters.status === '0') {
      return '待支付'
    }

    return '全部状态'
  })

  const hasActiveFilters = computed(() => filters.keyword !== '' || filters.status !== '')
  const callbackReplayLabel = computed(() =>
    writeActions.value.callback_replay ? '已开放回调重放' : '当前仅支持查看'
  )

  const actionTip = computed(() => {
    if (writeActions.value.callback_replay) {
      return '当前支持对已支付订单执行回调重放，状态重置入口已关闭。'
    }

    return '当前页面提供订单查询与详情核对。'
  })

  function money(value: unknown) {
    return Number(value || 0).toFixed(2)
  }

  function formatApiMemo(value: unknown) {
    if (!value) {
      return '暂无接口备注'
    }

    const text = String(value)
    try {
      return JSON.stringify(JSON.parse(text), null, 2)
    } catch {
      return text
    }
  }

  function canReplayCallback(row: Record<string, any> | null | undefined) {
    if (!row) {
      return false
    }

    return Boolean(writeActions.value.callback_replay) && Number(row.status) === 1
  }

  function resetFilters() {
    filters.keyword = ''
    filters.status = ''
    loadOrders(true)
  }

  async function loadOrders(resetPage = false) {
    if (resetPage) {
      pagination.current = 1
    }

    loading.value = true
    try {
      const result = await fetchMerchantOrders({
        current: pagination.current,
        size: pagination.size,
        keyword: filters.keyword,
        status: filters.status
      })

      records.value = result.records
      summary.value = result.summary
      writeActions.value = result.writeActions
      pagination.current = result.pagination.current
      pagination.size = result.pagination.size
      pagination.total = result.pagination.total
    } catch (error) {
      const message =
        error instanceof MerchantApiError
          ? translateMerchantText(error.message, error.message)
          : '订单数据加载失败'
      ElMessage.error(message)
    } finally {
      loading.value = false
    }
  }

  async function openDetail(row: Record<string, any>) {
    detailVisible.value = true
    detailLoading.value = true
    detail.value = null
    detailOrderId.value = Number(row.id || 0)

    try {
      detail.value = await fetchMerchantOrderDetail(detailOrderId.value)
    } catch (error) {
      const message =
        error instanceof MerchantApiError
          ? translateMerchantText(error.message, error.message)
          : '订单详情加载失败'
      ElMessage.error(message)
    } finally {
      detailLoading.value = false
    }
  }

  async function reloadDetail() {
    if (!detailOrderId.value) {
      return
    }

    try {
      detail.value = await fetchMerchantOrderDetail(detailOrderId.value)
    } catch (error) {
      const message =
        error instanceof MerchantApiError
          ? translateMerchantText(error.message, error.message)
          : '订单详情刷新失败'
      ElMessage.error(message)
    }
  }

  async function handleReplay(row: Record<string, any>) {
    if (!canReplayCallback(row)) {
      return
    }

    const orderId = Number(row.id || 0)
    replayingId.value = orderId

    try {
      await replayMerchantOrderCallback(orderId)
      ElMessage.success('订单回调已重放')
      await loadOrders()
      if (detailVisible.value && detailOrderId.value === orderId) {
        await reloadDetail()
      }
    } catch (error) {
      const message =
        error instanceof MerchantApiError
          ? translateMerchantText(error.message, error.message)
          : '订单回调重放失败'
      ElMessage.error(message)
    } finally {
      replayingId.value = 0
    }
  }

  function handlePageChange(page: number) {
    pagination.current = page
    loadOrders()
  }

  onMounted(() => {
    loadOrders()
  })
</script>

<style lang="scss">
  @use '../styles';
</style>

<style lang="scss" scoped>
  .merchant-table-toolbar__filters--orders {
    flex: 1;
  }

  .merchant-table-toolbar__filters--orders :deep(.el-input) {
    width: 320px;
    max-width: 100%;
  }

  .merchant-order-note {
    margin-bottom: 16px;
  }

  .merchant-detail-stack {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .merchant-drawer-head {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    justify-content: space-between;
    min-width: 0;
    padding-right: 88px;
  }

  .merchant-drawer-head__copy {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
  }

  .merchant-drawer-head__copy strong {
    color: var(--merchant-heading-color);
    font-size: 18px;
    font-weight: 700;
    line-height: 1.2;
  }

  .merchant-drawer-head__copy span {
    color: var(--merchant-muted);
    font-size: 13px;
    line-height: 1.6;
    word-break: break-all;
  }

  .merchant-drawer-head__status {
    flex: none;
    margin-top: 2px;
    margin-right: 0;
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

  .merchant-detail-section__head span {
    color: var(--merchant-muted);
    font-size: 12px;
    line-height: 1.6;
    text-align: right;
  }

  .merchant-kv-grid--detail {
    gap: 16px 18px;
  }

  .merchant-detail-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 16px;
    padding: 14px 16px;
    border-radius: 18px;
    background: rgb(34 197 94 / 8%);
    border: 1px solid rgb(34 197 94 / 16%);
  }

  .merchant-detail-tip {
    color: var(--merchant-muted);
    font-size: 13px;
    line-height: 1.7;
  }

  :deep(.merchant-order-drawer .el-drawer__header) {
    margin-bottom: 0;
    padding: 20px 22px 0;
  }

  :deep(.merchant-order-drawer .el-drawer__headerbtn) {
    top: 20px;
    right: 22px;
    width: 32px;
    height: 32px;
    border-radius: 999px;
    color: var(--merchant-muted);
    background: rgb(148 163 184 / 10%);
    transition:
      background-color 0.18s ease,
      color 0.18s ease;
  }

  :deep(.merchant-order-drawer .el-drawer__headerbtn:hover) {
    color: var(--merchant-heading-color);
    background: rgb(148 163 184 / 18%);
  }

  :deep(.merchant-order-drawer .el-drawer__body) {
    padding: 18px 22px 22px;
  }

  @media (width <= 980px) {
    .merchant-card__head {
      align-items: flex-start;
    }

    .merchant-toolbar-pills {
      justify-content: flex-start;
    }
  }

  @media (width <= 768px) {
    .merchant-table-toolbar__filters--orders > * {
      width: 100% !important;
    }

    .merchant-drawer-head,
    .merchant-detail-section__head {
      flex-direction: column;
      align-items: flex-start;
    }

    .merchant-drawer-head {
      padding-right: 72px;
    }

    .merchant-detail-section__head span {
      text-align: left;
    }
  }
</style>
