<template>
  <div class="money-log-page art-full-height">
    <ArtSearchBar
      v-model="searchForm"
      :items="searchItems"
      :showExpand="false"
      @search="handleSearch"
      @reset="handleReset"
    />

    <ElCard class="art-table-card">
      <ArtTableHeader
        v-model:columns="columnChecks"
        :loading="loading"
        layout="refresh"
        @refresh="getMoneyLogList"
      >
        <template #left>
          <ElSpace wrap>
            <ElTag effect="plain">资金日志 {{ pagination.total }}</ElTag>
            <ElTag type="success" effect="plain">
              收入 {{ summary.income_count }} / {{ formatAmount(summary.income_amount) }}
            </ElTag>
            <ElTag type="warning" effect="plain">
              支出 {{ summary.expense_count }} /
              {{ formatAmount(Math.abs(summary.expense_amount)) }}
            </ElTag>
            <ElTag :type="summary.net_amount >= 0 ? 'success' : 'danger'" effect="plain">
              净变动 {{ formatSignedAmount(summary.net_amount) }}
            </ElTag>
            <ElButton v-if="hasMoneyLogAdjustmentAuth" type="primary" @click="openAdjustmentDialog">
              手工调账
            </ElButton>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        :loading="loading"
        :data="moneyLogList"
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
      :title="
        activeLog
          ? `${displayAdminFixtureText(activeLog.merchant_display)} / #${activeLog.id}`
          : '资金日志详情'
      "
    >
      <div v-loading="detailLoading" class="money-log-detail">
        <template v-if="activeLog">
          <div class="drawer-section">
            <div class="drawer-grid">
              <div class="drawer-item">
                <span>商户</span>
                <strong>{{ displayAdminFixtureText(activeLog.merchant_display) }}</strong>
              </div>
              <div class="drawer-item">
                <span>商户编号</span>
                <strong>{{ activeLog.user_id || '--' }}</strong>
              </div>
              <div class="drawer-item">
                <span>方向</span>
                <strong>{{ activeLog.direction_label }}</strong>
              </div>
              <div class="drawer-item">
                <span>类型</span>
                <strong>{{ activeLog.type_label }}</strong>
              </div>
              <div class="drawer-item">
                <span>金额</span>
                <strong :class="['amount-text', activeLog.direction]">
                  {{ activeLog.money_display }}
                </strong>
              </div>
              <div class="drawer-item">
                <span>变更前余额</span>
                <strong>{{ formatAmount(activeLog.before_money) }}</strong>
              </div>
              <div class="drawer-item">
                <span>变更后余额</span>
                <strong>{{ formatAmount(activeLog.after_money) }}</strong>
              </div>
              <div class="drawer-item">
                <span>记录时间</span>
                <strong>{{ activeLog.create_time || '--' }}</strong>
              </div>
            </div>
          </div>

          <div class="drawer-section">
            <h4>日志元数据</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="日志编号">
                {{ activeLog.id }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="原始类型">
                {{ activeLog.type ?? '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="商户账号">
                {{ displayAdminFixtureText(activeLog.merchant_username) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="商户实名">
                {{ displayAdminFixtureText(activeLog.merchant_name) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="备注">
                {{ displayAdminFixtureText(activeLog.memo_label) }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>
        </template>
      </div>
    </ElDrawer>

    <ElDialog
      v-model="adjustmentVisible"
      width="920px"
      destroy-on-close
      align-center
      title="手工余额调账"
    >
      <div class="adjustment-layout">
        <ElForm label-position="top" class="adjustment-form">
          <ElFormItem label="商户编号">
            <div class="merchant-id-row">
              <ElInput
                v-model="adjustmentForm.user_id"
                maxlength="12"
                placeholder="输入需要调账的商户编号"
                @keyup.enter="loadAdjustmentMerchant"
              />
              <ElButton plain :loading="merchantPreviewLoading" @click="loadAdjustmentMerchant">
                加载商户
              </ElButton>
            </div>
          </ElFormItem>

          <ElFormItem label="变动方向">
            <ElSelect v-model="adjustmentForm.direction" placeholder="选择余额变动方向">
              <ElOption label="入账 / 增加" value="income" />
              <ElOption label="出账 / 扣减" value="expense" />
            </ElSelect>
          </ElFormItem>

          <ElFormItem label="金额">
            <ElInput
              v-model="adjustmentForm.amount"
              maxlength="16"
              placeholder="输入正数金额，例如 88.66"
            />
          </ElFormItem>

          <ElFormItem label="操作备注">
            <ElInput
              v-model="adjustmentForm.memo"
              type="textarea"
              :rows="4"
              maxlength="32"
              show-word-limit
              placeholder="选填备注，会同步写入资金日志"
            />
          </ElFormItem>

          <ElAlert
            type="warning"
            :closable="false"
            show-icon
            title="扣减后余额不能低于 0。每次调账都会写入资金日志。"
          />
        </ElForm>

        <div class="adjustment-preview">
          <div v-if="adjustmentMerchant" class="preview-card">
            <div class="preview-copy">
              <h3>{{
                adjustmentMerchant.name ||
                adjustmentMerchant.userName ||
                `商户 #${adjustmentMerchant.id}`
              }}</h3>
              <p>
                {{ adjustmentMerchant.userName || '--' }}
                <span>/ 编号 {{ adjustmentMerchant.id }}</span>
              </p>
            </div>
            <ElTag :type="adjustmentMerchant.is_frozen ? 'danger' : 'success'" effect="light">
              {{
                adjustmentMerchant.status_label || (adjustmentMerchant.is_frozen ? '冻结' : '正常')
              }}
            </ElTag>

            <div class="preview-grid">
              <div class="preview-item">
                <span>当前余额</span>
                <strong>{{
                  formatAmount(adjustmentMerchant.money ?? adjustmentMerchant.balance ?? 0, 2)
                }}</strong>
              </div>
              <div class="preview-item">
                <span>会员状态</span>
                <strong>{{ adjustmentMerchant.vip_status_label || '--' }}</strong>
              </div>
              <div class="preview-item">
                <span>邮箱</span>
                <strong>{{ adjustmentMerchant.email || '--' }}</strong>
              </div>
              <div class="preview-item">
                <span>手机号</span>
                <strong>{{ adjustmentMerchant.mobile || '--' }}</strong>
              </div>
            </div>

            <ElAlert
              class="preview-alert"
              type="info"
              :closable="false"
              show-icon
              :title="previewImpactLabel"
            />
          </div>

          <div v-else class="preview-empty">
            <ElEmpty description="提交前请先加载商户预览。" />
          </div>
        </div>
      </div>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="adjustmentVisible = false">取消</ElButton>
          <ElButton
            v-if="hasMoneyLogAdjustmentAuth"
            type="primary"
            :loading="adjusting"
            @click="submitAdjustment"
          >
            确认调账
          </ElButton>
        </div>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElTag } from 'element-plus'
  import { useAuth } from '@/hooks'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import ArtButtonTable from '@/components/core/forms/artButtonTable/index.vue'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'
  import {
    fetchCreateMoneyLogAdjustment,
    fetchGetMoneyLogDetail,
    fetchGetMoneyLogList
  } from '@/api/moneyLogs'
  import { fetchGetMerchantDetail } from '@/api/users'

  defineOptions({ name: 'FinanceMoneyLogs' })

  type MoneyLogItem = Api.MoneyLogs.MoneyLogListItem
  type MoneyLogSummary = Api.MoneyLogs.MoneyLogSummary
  type MoneyLogCreateResponse = Api.MoneyLogs.MoneyLogCreateResponse
  type MerchantItem = Api.Users.UserListItem
  type MoneyLogDirection = 'income' | 'expense'

  const { hasAuth } = useAuth()
  const loading = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const adjustmentVisible = ref(false)
  const adjusting = ref(false)
  const merchantPreviewLoading = ref(false)
  const moneyLogList = ref<MoneyLogItem[]>([])
  const activeLog = ref<MoneyLogItem | null>(null)
  const adjustmentMerchant = ref<MerchantItem | null>(null)
  const pagination = reactive({
    current: 1,
    size: 20,
    total: 0
  })
  const summary = reactive<MoneyLogSummary>(emptySummary())
  const searchForm = ref<{
    keyword?: string
    user_id?: string
    direction?: MoneyLogDirection | ''
    memo?: string
    date_range?: string[]
  }>({})
  const adjustmentForm = reactive(emptyAdjustmentForm())
  const hasMoneyLogAdjustmentAuth = computed(() => hasAuth('add'))

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '可按日志编号、商户编号、商户账号、商户实名或备注搜索'
      }
    },
    {
      label: '商户编号',
      key: 'user_id',
      type: 'input',
      props: {
        placeholder: '输入商户编号筛选'
      }
    },
    {
      label: '方向',
      key: 'direction',
      type: 'select',
      props: {
        placeholder: '全部方向',
        options: [
          { label: '收入', value: 'income' },
          { label: '支出', value: 'expense' }
        ]
      }
    },
    {
      label: '备注',
      key: 'memo',
      type: 'input',
      props: {
        placeholder: '按备注内容筛选'
      }
    },
    {
      label: '日期范围',
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

  const previewImpactLabel = computed(() => {
    const merchant = adjustmentMerchant.value
    const amount = normalizeAdjustmentAmount(adjustmentForm.amount)
    if (!merchant || amount === null) {
      return '先输入金额，即可在提交前预览余额变化。'
    }

    const currentBalance = Number(merchant.money ?? merchant.balance ?? 0)
    const nextBalance =
      adjustmentForm.direction === 'expense' ? currentBalance - amount : currentBalance + amount
    const sign = adjustmentForm.direction === 'expense' ? '-' : '+'

    return `预览：${formatAmount(currentBalance, 2)} ${sign} ${formatAmount(amount, 2)} = ${formatAmount(nextBalance, 2)}`
  })

  const { columnChecks, columns } = useTableColumns<MoneyLogItem>(() => [
    { type: 'globalIndex', width: 70, label: '序号' },
    {
      prop: 'merchant_display',
      label: '商户',
      minWidth: 220,
      formatter: (row) =>
        h('div', { class: 'merchant-cell' }, [
          h('strong', { class: 'cell-title' }, displayAdminFixtureText(row.merchant_display)),
          h('p', { class: 'cell-sub' }, `商户编号：${row.user_id || '--'}`)
        ])
    },
    {
      prop: 'money',
      label: '金额',
      minWidth: 150,
      align: 'right',
      formatter: (row) =>
        h('div', { class: ['amount-cell', row.direction] }, [
          h('strong', row.money_display),
          h('p', { class: 'cell-sub' }, row.direction_label)
        ])
    },
    {
      prop: 'balance',
      label: '余额变动',
      minWidth: 210,
      formatter: (row) =>
        h('div', { class: 'balance-cell' }, [
          h('strong', { class: 'cell-title' }, formatAmount(row.after_money)),
          h('p', { class: 'cell-sub' }, `变更前 ${formatAmount(row.before_money)}`)
        ])
    },
    {
      prop: 'type_label',
      label: '类型',
      minWidth: 140,
      align: 'center',
      formatter: (row) =>
        h(ElTag, { type: tagType(row.type_tag), effect: 'light' }, () => row.type_label)
    },
    {
      prop: 'memo_label',
      label: '备注',
      minWidth: 280,
      formatter: (row) => h('span', { class: 'memo-text' }, displayAdminFixtureText(row.memo_label))
    },
    {
      prop: 'create_time',
      label: '记录时间',
      minWidth: 170,
      formatter: (row) => row.create_time || '--'
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
  ])

  watch(
    () => adjustmentForm.user_id,
    (value) => {
      const merchantId = adjustmentMerchant.value?.id ? String(adjustmentMerchant.value.id) : ''
      if (String(value || '').trim() !== merchantId) {
        adjustmentMerchant.value = null
      }
    }
  )

  onMounted(() => {
    getMoneyLogList()
  })

  async function getMoneyLogList() {
    loading.value = true
    try {
      const response = await fetchGetMoneyLogList({
        current: pagination.current,
        size: pagination.size,
        keyword: searchForm.value.keyword,
        user_id: searchForm.value.user_id,
        direction: searchForm.value.direction,
        memo: searchForm.value.memo,
        start_date: searchForm.value.date_range?.[0],
        end_date: searchForm.value.date_range?.[1]
      })
      moneyLogList.value = response.records
      pagination.current = response.current
      pagination.size = response.size
      pagination.total = response.total
      Object.assign(summary, response.summary || emptySummary())
    } catch (_error) {
      ElMessage.error('加载资金日志失败。')
    } finally {
      loading.value = false
    }
  }

  function handleSearch(params: Record<string, unknown>) {
    pagination.current = 1
    searchForm.value = {
      keyword: params.keyword as string | undefined,
      user_id: params.user_id as string | undefined,
      direction: params.direction as MoneyLogDirection | '',
      memo: params.memo as string | undefined,
      date_range: Array.isArray(params.date_range) ? (params.date_range as string[]) : undefined
    }
    getMoneyLogList()
  }

  function handleReset() {
    pagination.current = 1
    searchForm.value = {}
    getMoneyLogList()
  }

  function handleSizeChange(size: number) {
    pagination.size = size
    pagination.current = 1
    getMoneyLogList()
  }

  function handleCurrentChange(current: number) {
    pagination.current = current
    getMoneyLogList()
  }

  async function openDetail(row: MoneyLogItem) {
    detailVisible.value = true
    detailLoading.value = true
    activeLog.value = row

    try {
      const response = await fetchGetMoneyLogDetail(row.id)
      activeLog.value = response.item
    } catch (_error) {
      ElMessage.error('加载资金日志详情失败。')
    } finally {
      detailLoading.value = false
    }
  }

  function openAdjustmentDialog() {
    if (!hasMoneyLogAdjustmentAuth.value) {
      guardNoAdjustmentAuth()
      return
    }

    adjustmentVisible.value = true
    adjustmentMerchant.value = null
    Object.assign(adjustmentForm, emptyAdjustmentForm())
  }

  async function loadAdjustmentMerchant() {
    const merchantId = parseMerchantId(adjustmentForm.user_id)
    if (merchantId === null) {
      ElMessage.warning('请先输入有效的商户编号。')
      return
    }

    merchantPreviewLoading.value = true
    try {
      const response = await fetchGetMerchantDetail(merchantId)
      adjustmentMerchant.value = response.item
    } catch (_error) {
      adjustmentMerchant.value = null
      ElMessage.error('加载商户预览失败。')
    } finally {
      merchantPreviewLoading.value = false
    }
  }

  async function submitAdjustment() {
    if (!hasMoneyLogAdjustmentAuth.value) {
      guardNoAdjustmentAuth()
      return
    }

    const merchantId = parseMerchantId(adjustmentForm.user_id)
    if (merchantId === null) {
      ElMessage.warning('请输入有效的商户编号。')
      return
    }

    const amount = normalizeAdjustmentAmount(adjustmentForm.amount)
    if (amount === null || amount <= 0) {
      ElMessage.warning('请输入大于 0 且最多保留 2 位小数的金额。')
      return
    }

    if (!adjustmentMerchant.value || adjustmentMerchant.value.id !== merchantId) {
      await loadAdjustmentMerchant()
      if (!adjustmentMerchant.value || adjustmentMerchant.value.id !== merchantId) {
        return
      }
    }

    adjusting.value = true
    try {
      const response = await fetchCreateMoneyLogAdjustment({
        user_id: merchantId,
        direction: adjustmentForm.direction,
        amount: amount.toFixed(2),
        memo: adjustmentForm.memo.trim()
      })
      handleAdjustmentSuccess(response)
    } catch (_error) {
      ElMessage.error('执行余额调账失败。')
    } finally {
      adjusting.value = false
    }
  }

  function handleAdjustmentSuccess(response: MoneyLogCreateResponse) {
    adjustmentVisible.value = false
    adjustmentMerchant.value = null
    Object.assign(adjustmentForm, emptyAdjustmentForm())
    ElMessage.success(
      `${response.merchant_display} 已调整 ${response.applied_amount_display}，最新余额 ${formatAmount(response.balance_after, 2)}`
    )
    pagination.current = 1
    getMoneyLogList()
  }

  function guardNoAdjustmentAuth() {
    ElMessage.warning('当前没有手工余额调账权限。')
  }

  function emptySummary(): MoneyLogSummary {
    return {
      income_count: 0,
      expense_count: 0,
      income_amount: 0,
      expense_amount: 0,
      net_amount: 0
    }
  }

  function emptyAdjustmentForm(): {
    user_id: string
    direction: MoneyLogDirection
    amount: string
    memo: string
  } {
    return {
      user_id: '',
      direction: 'income',
      amount: '',
      memo: ''
    }
  }

  function parseMerchantId(value: string) {
    const normalized = String(value || '').trim()
    if (!/^\d+$/.test(normalized)) {
      return null
    }

    const merchantId = Number.parseInt(normalized, 10)
    return Number.isInteger(merchantId) && merchantId > 0 ? merchantId : null
  }

  function normalizeAdjustmentAmount(value: string) {
    const normalized = String(value || '').trim()
    if (!/^\d+(\.\d{1,2})?$/.test(normalized)) {
      return null
    }

    const amount = Number.parseFloat(normalized)
    return Number.isFinite(amount) && amount > 0 ? amount : null
  }

  function formatAmount(value: number, digits = 3) {
    return Number(value || 0).toLocaleString('zh-CN', {
      minimumFractionDigits: digits,
      maximumFractionDigits: digits
    })
  }

  function formatSignedAmount(value: number) {
    const amount = Number(value || 0)
    const prefix = amount > 0 ? '+' : ''
    return `${prefix}${formatAmount(amount)}`
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
  .money-log-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .merchant-cell,
  .balance-cell,
  .amount-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .amount-cell {
    font-variant-numeric: tabular-nums;
  }

  .amount-cell strong,
  .amount-text {
    font-size: 15px;
    font-variant-numeric: tabular-nums;
  }

  .amount-cell.income strong,
  .amount-text.income {
    color: var(--el-color-success);
  }

  .amount-cell.expense strong,
  .amount-text.expense {
    color: var(--el-color-warning);
  }

  .cell-title {
    color: #0f172a;
    font-size: 14px;
    word-break: break-all;
  }

  .cell-sub {
    margin: 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.6;
    word-break: break-all;
  }

  .memo-text {
    color: #334155;
    line-height: 1.6;
    word-break: break-all;
  }

  .money-log-detail {
    min-height: 240px;
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

  .adjustment-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
    gap: 20px;
  }

  .adjustment-form {
    min-width: 0;
  }

  .merchant-id-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
  }

  .adjustment-preview {
    min-width: 0;
  }

  .preview-card,
  .preview-empty {
    height: 100%;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 18px;
    background:
      radial-gradient(circle at top right, rgb(14 165 233 / 0.12), transparent 35%),
      linear-gradient(160deg, #f8fafc 0%, #eff6ff 100%);
    padding: 20px;
  }

  .preview-copy {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 16px;
  }

  .preview-copy h3 {
    margin: 0;
    color: #0f172a;
    font-size: 20px;
  }

  .preview-copy p {
    margin: 0;
    color: #475569;
    line-height: 1.6;
  }

  .preview-copy span {
    color: #64748b;
  }

  .preview-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-top: 18px;
  }

  .preview-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 14px;
    border-radius: 14px;
    background: rgb(255 255 255 / 0.76);
    border: 1px solid rgb(226 232 240 / 0.86);
  }

  .preview-item span {
    color: #64748b;
    font-size: 12px;
  }

  .preview-item strong {
    color: #0f172a;
    word-break: break-all;
  }

  .preview-alert {
    margin-top: 18px;
  }

  @media (width <= 991px) {
    .drawer-grid,
    .preview-grid,
    .adjustment-layout {
      grid-template-columns: 1fr;
    }

    .merchant-id-row {
      grid-template-columns: 1fr;
    }
  }
</style>
