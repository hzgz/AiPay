<template>
  <div class="merchant-page">
    <section class="merchant-page-header">
      <div class="merchant-page-header__title">
        <h1>充值中心</h1>
        <p>创建充值单、兑换卡券并查看记录。</p>
      </div>

      <div class="merchant-chip-row" v-if="catalog">
        <span class="merchant-chip">最低充值 {{ catalog.min_recharge ?? 0 }}</span>
        <span class="merchant-chip">最高充值 {{ catalog.max_recharge ?? 0 }}</span>
        <span class="merchant-chip">可用方式 {{ enabledMethods.length }} 项</span>
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

      <section class="merchant-grid-2">
        <article class="merchant-form-card">
          <div class="merchant-form-card__head">
            <div>
              <h2>在线充值</h2>
              <p>创建充值单并跳转支付。</p>
            </div>
          </div>

          <div class="merchant-chip-row">
            <span class="merchant-chip">支付方式 {{ enabledMethods.length }} 项</span>
          </div>

          <div class="merchant-form-grid">
            <div class="merchant-form-field merchant-form-field--full">
              <label for="rechargeMoney">充值金额</label>
              <ElInput
                id="rechargeMoney"
                v-model.trim="rechargeForm.money"
                type="number"
                placeholder="请输入充值金额"
                inputmode="decimal"
              />
              <small>按限额填写</small>
            </div>

            <div class="merchant-form-field merchant-form-field--full">
              <label>充值方式</label>
              <div v-if="enabledMethods.length" class="merchant-method-grid">
                <button
                  v-for="method in enabledMethods"
                  :key="method.id"
                  type="button"
                  class="merchant-method-card"
                  :class="{ 'is-active': rechargeForm.type === method.id }"
                  @click="rechargeForm.type = method.id"
                >
                  <strong>{{ translateMerchantText(method.label) }}</strong>
                  <span>{{ translateMerchantText(method.description) }}</span>
                </button>
              </div>
              <div v-else class="merchant-empty-box"> 暂无可用充值方式 </div>
            </div>
          </div>

          <div class="merchant-form-actions merchant-form-actions--split">
            <ElButton
              type="primary"
              :loading="rechargeSubmitting"
              :disabled="!canCreateRecharge"
              @click="submitRecharge"
            >
              创建充值单并前往支付
            </ElButton>
          </div>
        </article>

        <article class="merchant-form-card">
          <div class="merchant-form-card__head">
            <div>
              <h2>卡券兑换</h2>
              <p>支持余额卡券与会员卡券。</p>
            </div>
          </div>

          <div class="merchant-chip-row">
            <span class="merchant-chip">当前入口 卡券兑换码</span>
          </div>

          <div class="merchant-form-grid">
            <div class="merchant-form-field merchant-form-field--full">
              <label for="cdkCode">卡券兑换码</label>
              <ElInput
                id="cdkCode"
                v-model.trim="cdkForm.code"
                placeholder="请输入卡券兑换码"
                clearable
              />
              <small>支持余额卡券、会员卡券</small>
            </div>
          </div>

          <div class="merchant-form-actions merchant-form-actions--split">
            <ElButton
              type="success"
              plain
              :loading="cdkSubmitting"
              :disabled="!canRedeemCdk"
              @click="submitCdkRedeem"
            >
              立即兑换
            </ElButton>
          </div>
        </article>
      </section>

      <article class="merchant-card">
        <div class="merchant-card__head">
          <div>
            <h2>充值记录</h2>
            <p>按订单号和状态筛选。</p>
          </div>

          <div class="merchant-toolbar-pills">
            <div class="merchant-toolbar-pill">
              <span>状态</span>
              <strong>{{ currentStatusLabel }}</strong>
            </div>
            <div v-if="filters.keyword" class="merchant-toolbar-pill">
              <span>订单号</span>
              <strong>{{ filters.keyword }}</strong>
            </div>
          </div>
        </div>

        <div class="merchant-table-toolbar">
          <div class="merchant-table-toolbar__filters merchant-table-toolbar__filters--recharge">
            <ElInput
              v-model.trim="filters.keyword"
              placeholder="搜索订单号"
              clearable
              @keyup.enter="loadRecharges(true)"
            >
              <template #prefix>
                <Icon icon="ri:search-line" />
              </template>
            </ElInput>

            <ElSelect
              v-model="filters.status"
              clearable
              placeholder="全部状态"
              style="width: 160px"
            >
              <ElOption label="待支付" value="0" />
              <ElOption label="已支付" value="1" />
            </ElSelect>

            <ElButton type="primary" @click="loadRecharges(true)">查询</ElButton>
            <ElButton plain :disabled="!hasActiveFilters" @click="resetFilters">重置</ElButton>
          </div>
        </div>

        <ElTable :data="records" empty-text="暂无充值记录">
          <ElTableColumn label="订单号" min-width="180" show-overflow-tooltip>
            <template #default="{ row }">
              {{ formatMerchantRecordCode(row.out_trade_no) }}
            </template>
          </ElTableColumn>
          <ElTableColumn prop="type_label" label="充值方式" width="120">
            <template #default="{ row }">
              {{ translateMerchantText(row.type_label || row.type) }}
            </template>
          </ElTableColumn>
          <ElTableColumn prop="money_display" label="金额" width="120">
            <template #default="{ row }">
              {{ row.money_display || row.money || '--' }}
            </template>
          </ElTableColumn>
          <ElTableColumn prop="status_label" label="状态" width="110">
            <template #default="{ row }">
              <ElTag :type="row.status_type || 'info'" effect="plain">
                {{ translateMerchantText(row.status_label || '--') }}
              </ElTag>
            </template>
          </ElTableColumn>
          <ElTableColumn prop="create_time" label="创建时间" min-width="180" />
          <ElTableColumn prop="end_time" label="完成时间" min-width="180" />
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

      <ElDialog
        v-model="cashierDialogVisible"
        title="等待支付"
        width="680px"
        destroy-on-close
        @closed="closeCashierDialog"
      >
        <div v-if="cashierPayload" class="cashier-dialog">
          <div class="cashier-dialog__qr">
            <img v-if="cashierQrUrl" :src="cashierQrUrl" alt="充值二维码" />
            <div v-else class="merchant-empty-box">二维码生成中，请稍候...</div>
          </div>

          <div class="cashier-dialog__body">
            <div class="merchant-kv-grid">
              <div class="merchant-kv-item">
                <span>订单号</span>
                <div>{{ formatMerchantRecordCode(cashierPayload.order?.out_trade_no) }}</div>
              </div>
              <div class="merchant-kv-item">
                <span>支付方式</span>
                <div>{{ translateMerchantText(cashierPayload.order?.type || '--') }}</div>
              </div>
              <div class="merchant-kv-item">
                <span>充值金额</span>
                <div>{{ cashierPayload.order?.truemoney || '--' }}</div>
              </div>
              <div class="merchant-kv-item">
                <span>剩余时间</span>
                <div>{{ cashierCountdownText }}</div>
              </div>
            </div>

            <div class="cashier-status-card">
              <strong>{{ cashierStatus.title }}</strong>
              <p>{{ cashierStatus.hint }}</p>
            </div>

            <div class="merchant-form-actions">
              <ElButton
                v-if="cashierPayload.order?.launch_url"
                type="primary"
                plain
                @click="openCashierLaunchUrl"
              >
                打开支付应用
              </ElButton>
              <ElButton plain @click="closeCashierDialog">稍后支付</ElButton>
            </div>
          </div>
        </div>
      </ElDialog>
    </template>
  </div>
</template>

<script setup lang="ts">
  import { Icon } from '@iconify/vue'
  import { ElMessage } from 'element-plus'
  import { resolveBackendOrigin } from '@/utils/http/base'
  import {
    MerchantApiError,
    createMerchantRecharge,
    fetchMerchantRecharges,
    redeemMerchantCdk
  } from '@/api/merchant'
  import { formatMerchantRecordCode, translateMerchantText } from '../shared/text'

  defineOptions({ name: 'MerchantRecharges' })

  const loading = ref(true)
  const records = ref<Record<string, any>[]>([])
  const summary = ref<Record<string, any>>({})
  const catalog = ref<Record<string, any> | null>(null)
  const writeActions = ref<Record<string, any>>({})

  const pagination = reactive({
    current: 1,
    size: 10,
    total: 0
  })
  const filters = reactive({
    keyword: '',
    status: ''
  })

  const rechargeForm = reactive({
    money: '',
    type: ''
  })
  const cdkForm = reactive({
    code: ''
  })

  const rechargeSubmitting = ref(false)
  const cdkSubmitting = ref(false)

  const cashierDialogVisible = ref(false)
  const cashierPayload = ref<Record<string, any> | null>(null)
  const cashierQrUrl = ref('')
  const cashierTradeNo = ref('')
  const backendOrigin = resolveBackendOrigin()
  const cashierRemainingSeconds = ref(0)
  const cashierStatus = reactive({
    title: '等待支付确认',
    hint: '系统会自动轮询支付状态，请在支付完成前不要关闭此窗口。'
  })

  let cashierPollTimer: number | null = null
  let cashierCountdownTimer: number | null = null

  const enabledMethods = computed(() =>
    ((catalog.value?.methods || []) as Record<string, any>[]).filter((item) =>
      Boolean(item.enabled)
    )
  )

  const canCreateRecharge = computed(
    () =>
      Boolean(writeActions.value.recharge_create) &&
      enabledMethods.value.length > 0 &&
      !rechargeSubmitting.value
  )
  const canRedeemCdk = computed(
    () => Boolean(writeActions.value.cdk_redeem) && !cdkSubmitting.value
  )

  const hasActiveFilters = computed(() => filters.keyword !== '' || filters.status !== '')

  const currentStatusLabel = computed(() => {
    if (filters.status === '1') {
      return '已支付'
    }
    if (filters.status === '0') {
      return '待支付'
    }
    return '全部状态'
  })

  const summaryCards = computed(() => [
    {
      label: '充值订单数',
      value: String(summary.value.total_count ?? 0),
      hint: '累计充值订单总数',
      icon: 'ri:file-list-3-line'
    },
    {
      label: '已支付笔数',
      value: String(summary.value.paid_count ?? 0),
      hint: '已完成支付的充值订单数量',
      icon: 'ri:checkbox-circle-line'
    },
    {
      label: '支付金额',
      value: money(summary.value.paid_amount),
      hint: '已到账的充值金额总和',
      icon: 'ri:wallet-3-line'
    },
    {
      label: '待支付笔数',
      value: String(summary.value.pending_count ?? 0),
      hint: '等待支付中的充值订单数量',
      icon: 'ri:time-line'
    }
  ])

  const cashierCountdownText = computed(() => {
    if (!cashierRemainingSeconds.value) {
      return '等待同步'
    }

    const minutes = String(Math.floor(Math.max(cashierRemainingSeconds.value, 0) / 60)).padStart(
      2,
      '0'
    )
    const seconds = String(Math.max(cashierRemainingSeconds.value, 0) % 60).padStart(2, '0')
    return `${minutes}:${seconds}`
  })

  function money(value: unknown) {
    return Number(value || 0).toFixed(2)
  }

  function syncRechargeMethod() {
    if (enabledMethods.value.some((item) => item.id === rechargeForm.type)) {
      return
    }

    rechargeForm.type = enabledMethods.value[0]?.id || ''
  }

  function resetFilters() {
    filters.keyword = ''
    filters.status = ''
    loadRecharges(true)
  }

  function buildMerchantQrUrl(content: unknown) {
    const raw = String(content || '').trim()
    if (!raw) {
      return ''
    }

    if (/^https?:\/\//i.test(raw) || raw.startsWith('data:image/')) {
      return raw
    }

    if (raw.startsWith('//')) {
      return `${window.location.protocol}${raw}`
    }

    if (raw.startsWith('/')) {
      return `${backendOrigin}${raw}`
    }

    return `${backendOrigin}/qrcode.php?text=${encodeURIComponent(raw)}&size=350`
  }

  function stopCashierTimers() {
    if (cashierPollTimer !== null) {
      window.clearInterval(cashierPollTimer)
      cashierPollTimer = null
    }

    if (cashierCountdownTimer !== null) {
      window.clearInterval(cashierCountdownTimer)
      cashierCountdownTimer = null
    }
  }

  function closeCashierDialog() {
    stopCashierTimers()
    cashierDialogVisible.value = false
    cashierPayload.value = null
    cashierQrUrl.value = ''
    cashierTradeNo.value = ''
  }

  function openCashierDialog(payload: Record<string, any>, outTradeNo: string) {
    stopCashierTimers()

    cashierPayload.value = payload
    cashierTradeNo.value = outTradeNo
    cashierQrUrl.value = buildMerchantQrUrl(payload.order?.raw_qrcode)
    cashierRemainingSeconds.value = Number(payload.console?.timeout_seconds || 0)
    cashierStatus.title = '等待支付确认'
    cashierStatus.hint = '系统会自动轮询支付状态，请在支付完成前不要关闭此窗口。'
    cashierDialogVisible.value = true

    cashierCountdownTimer = window.setInterval(() => {
      if (cashierRemainingSeconds.value > 0) {
        cashierRemainingSeconds.value -= 1
        return
      }

      cashierStatus.title = '订单已超时'
      cashierStatus.hint = '当前二维码已过期，请关闭弹窗后重新创建充值订单。'
      stopCashierTimers()
    }, 1000)

    void pollCashierStatus()
    cashierPollTimer = window.setInterval(() => {
      void pollCashierStatus()
    }, 2000)
  }

  async function pollCashierStatus() {
    if (!cashierTradeNo.value) {
      return
    }

    try {
      const response = await fetch(
        `${backendOrigin}/api/merchant/recharges/poll?TradeNo=${encodeURIComponent(cashierTradeNo.value)}&_=${Date.now()}`,
        {
          headers: {
            Accept: 'application/json'
          }
        }
      )
      const result = await response.json()

      if (String(result.code) === '200') {
        cashierStatus.title = '支付成功'
        cashierStatus.hint = '充值已到账，正在刷新充值记录。'
        stopCashierTimers()
        await loadRecharges(true)
        window.setTimeout(() => {
          closeCashierDialog()
        }, 1200)
        return
      }

      if (String(result.code) === '100') {
        if (result.qr_url) {
          cashierQrUrl.value = String(result.qr_url)
        }
        cashierStatus.title = '二维码已生成'
        cashierStatus.hint = '请在对应支付应用内完成支付，系统会自动检测到账状态。'
        return
      }

      if (String(result.code) === '0') {
        const messageMap: Record<string, string> = {
          order_timeout: '当前充值订单已超时，请重新创建订单。',
          order_not_found: '未找到当前充值订单，请刷新页面后重试。',
          qrcode_missing: '当前订单暂未生成二维码，请稍后刷新。'
        }
        cashierStatus.title = '等待支付'
        cashierStatus.hint =
          messageMap[String(result.msg || '')] || '支付状态暂未返回，请稍后刷新或重试。'
      }
    } catch {
      cashierStatus.title = '轮询暂时失败'
      cashierStatus.hint = '网络波动不会影响订单，请稍后继续等待系统自动重试。'
    }
  }

  function openCashierLaunchUrl() {
    const launchUrl = String(cashierPayload.value?.order?.launch_url || '').trim()
    if (!launchUrl) {
      return
    }

    window.open(launchUrl, '_blank')
  }

  async function loadRecharges(resetPage = false) {
    if (resetPage) {
      pagination.current = 1
    }

    loading.value = true
    try {
      const result = await fetchMerchantRecharges({
        current: pagination.current,
        size: pagination.size,
        keyword: filters.keyword,
        status: filters.status
      })

      records.value = result.records
      summary.value = result.summary
      catalog.value = result.catalog
      writeActions.value = result.writeActions
      pagination.current = result.pagination.current
      pagination.size = result.pagination.size
      pagination.total = result.pagination.total
      syncRechargeMethod()
    } catch (error) {
      const message =
        error instanceof MerchantApiError
          ? translateMerchantText(error.message, error.message)
          : '充值记录加载失败'
      ElMessage.error(message)
    } finally {
      loading.value = false
    }
  }

  async function submitRecharge() {
    if (!canCreateRecharge.value) {
      return
    }

    if (!rechargeForm.money) {
      ElMessage.warning('请输入充值金额')
      return
    }

    if (!rechargeForm.type) {
      ElMessage.warning('请选择充值方式')
      return
    }

    rechargeSubmitting.value = true
    let paymentWindow: Window | null = window.open('', '_blank')

    try {
      const result = await createMerchantRecharge({
        money: rechargeForm.money,
        type: rechargeForm.type
      })

      await loadRecharges(true)

      if (String(result.mode) === 'cashier' && result.data) {
        if (paymentWindow && !paymentWindow.closed) {
          paymentWindow.close()
        }
        openCashierDialog(result.data, String(result.out_trade_no || ''))
        ElMessage.success('充值订单已创建，请完成支付')
        return
      }

      if (String(result.mode) === 'html' && result.form_html) {
        if (paymentWindow && !paymentWindow.closed) {
          paymentWindow.document.open()
          paymentWindow.document.write(String(result.form_html))
          paymentWindow.document.close()
        } else {
          const blobUrl = URL.createObjectURL(
            new Blob([String(result.form_html)], { type: 'text/html;charset=utf-8' })
          )
          window.open(blobUrl, '_blank')
        }
        ElMessage.success('充值订单已创建，支付页已打开')
        return
      }

      if (paymentWindow && !paymentWindow.closed) {
        paymentWindow.close()
      }
      ElMessage.success('充值订单已创建')
    } catch (error) {
      if (paymentWindow && !paymentWindow.closed) {
        paymentWindow.close()
      }

      const message =
        error instanceof MerchantApiError
          ? translateMerchantText(error.message, error.message)
          : '创建充值订单失败'
      ElMessage.error(message)
    } finally {
      rechargeSubmitting.value = false
    }
  }

  async function submitCdkRedeem() {
    if (!canRedeemCdk.value) {
      return
    }

    if (!cdkForm.code) {
      ElMessage.warning('请输入卡券兑换码')
      return
    }

    cdkSubmitting.value = true
    try {
      await redeemMerchantCdk(cdkForm.code)
      cdkForm.code = ''
      ElMessage.success('卡券兑换成功')
      await loadRecharges(true)
    } catch (error) {
      const message =
        error instanceof MerchantApiError
          ? translateMerchantText(error.message, error.message)
          : '卡券兑换失败'
      ElMessage.error(message)
    } finally {
      cdkSubmitting.value = false
    }
  }

  function handlePageChange(page: number) {
    pagination.current = page
    loadRecharges()
  }

  onMounted(() => {
    loadRecharges()
  })

  onBeforeUnmount(() => {
    stopCashierTimers()
  })
</script>

<style lang="scss">
  @use '../styles';
</style>

<style lang="scss" scoped>
  .merchant-recharge-note {
    margin: 14px 0 18px;
  }

  .merchant-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }

  .merchant-form-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .merchant-form-field label {
    color: var(--merchant-heading-color);
    font-size: 14px;
    font-weight: 600;
    line-height: 1.3;
  }

  .merchant-form-field small {
    color: var(--merchant-muted);
    font-size: 12px;
    line-height: 1.7;
  }

  .merchant-form-field--full {
    grid-column: 1 / -1;
  }

  .merchant-form-actions--split {
    justify-content: space-between;
  }

  .merchant-method-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 12px;
  }

  .merchant-method-card {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 14px 16px;
    border-radius: 18px;
    border: 1px solid rgb(148 163 184 / 16%);
    background: rgb(148 163 184 / 6%);
    text-align: left;
    transition:
      border-color 0.2s ease,
      background 0.2s ease,
      transform 0.2s ease;
    cursor: pointer;
  }

  .merchant-method-card strong,
  .cashier-status-card strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
    font-weight: 700;
  }

  .merchant-method-card span {
    color: var(--merchant-muted);
    font-size: 12px;
    line-height: 1.7;
  }

  .merchant-method-card.is-active {
    border-color: rgb(59 130 246 / 34%);
    background: rgb(59 130 246 / 10%);
    transform: translateY(-1px);
  }

  .merchant-empty-box {
    display: grid;
    place-items: center;
    min-height: 120px;
    padding: 18px;
    border-radius: 18px;
    border: 1px dashed rgb(148 163 184 / 28%);
    color: var(--merchant-muted);
    background: rgb(148 163 184 / 4%);
    text-align: center;
    line-height: 1.7;
  }

  .merchant-table-toolbar__filters--recharge {
    flex: 1;
  }

  .merchant-table-toolbar__filters--recharge :deep(.el-input) {
    width: 300px;
    max-width: 100%;
  }

  .cashier-dialog {
    display: grid;
    grid-template-columns: 260px minmax(0, 1fr);
    gap: 20px;
    align-items: start;
  }

  .cashier-dialog__qr {
    display: grid;
    place-items: center;
    min-height: 260px;
    padding: 18px;
    border-radius: 22px;
    background: rgb(148 163 184 / 6%);
    border: 1px solid rgb(148 163 184 / 14%);
  }

  .cashier-dialog__qr img {
    width: 100%;
    max-width: 220px;
    border-radius: 18px;
    background: #fff;
    padding: 10px;
    box-shadow: 0 14px 36px rgb(15 23 42 / 10%);
  }

  .cashier-dialog__body {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .cashier-status-card {
    padding: 14px 16px;
    border-radius: 18px;
    background: rgb(59 130 246 / 8%);
    border: 1px solid rgb(59 130 246 / 14%);
  }

  .cashier-status-card p {
    margin: 6px 0 0;
    color: var(--merchant-muted);
    line-height: 1.7;
  }

  :global(html.dark .cashier-dialog__qr ){
    background: rgb(15 23 42 / 84%);
    border-color: rgb(71 85 105 / 34%);
  }

  :global(html.dark .cashier-dialog__qr img ){
    background: rgb(15 23 42 / 92%);
    box-shadow: 0 14px 36px rgb(2 6 23 / 28%);
  }

  :global(html.dark .cashier-status-card ){
    background: rgb(37 99 235 / 12%);
    border-color: rgb(96 165 250 / 18%);
  }

  @media (width <= 900px) {
    .merchant-form-grid {
      grid-template-columns: 1fr;
    }

    .merchant-form-actions--split {
      justify-content: flex-end;
    }

    .cashier-dialog {
      grid-template-columns: 1fr;
    }
  }

  @media (width <= 768px) {
    .merchant-table-toolbar__filters--recharge > * {
      width: 100% !important;
    }
  }
</style>
