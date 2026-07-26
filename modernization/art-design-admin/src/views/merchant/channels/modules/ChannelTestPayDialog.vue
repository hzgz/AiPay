<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <ElDialog
    :model-value="visible"
    width="620px"
    class="channel-shell-dialog"
    destroy-on-close
    align-center
    title="通道测试"
    @update:model-value="handleVisibilityChange"
  >
    <div :class="['test-pay-panel', { 'test-pay-panel--result': Boolean(testPayResult) }]">
      <div v-if="!testPayResult" class="test-pay-amount-bar">
        <span class="test-pay-amount-bar__label">{{ amountInputLabel }}</span>
        <ElInput
          v-model="testPayAmount"
          class="test-pay-amount-bar__input"
          maxlength="12"
          inputmode="decimal"
          placeholder="1.00"
          :disabled="testingTestPay"
        >
          <template #append>{{ amountInputUnit }}</template>
        </ElInput>
        <div class="test-pay-amount-bar__copy">
          <p class="test-pay-amount-bar__title">{{ currentAccountTitle }}</p>
          <p class="test-pay-amount-bar__hint">{{ amountInputHint }}</p>
        </div>
      </div>

      <template v-if="testPayResult">
        <section :class="['test-pay-hero', `test-pay-hero--${testPayResult.state}`]">
          <div class="test-pay-hero__copy">
            <p class="test-pay-hero__eyebrow">
              {{ resolveTestPayHeroEyebrow(testPayResult.state) }}
            </p>
            <h4>{{ resolveTestPayHeroTitle(testPayResult.state) }}</h4>
            <p>{{ resolveTestPayHeroDescription(testPayResult) }}</p>
          </div>

          <div class="test-pay-hero__aside">
            <span>{{ resultAmountLabel }}</span>
            <strong>{{ formatResultAmount(testPayResult) }}</strong>
            <small v-if="isUsdtResult" class="test-pay-hero__aside-meta">
              {{ formatBaseAmount(testPayResult) }} / {{ formatExchangeRate(testPayResult) }}
            </small>
            <ElTag :type="resolveTestPayTagType(testPayResult.state)" effect="light">
              {{ testPayResult.state_label }}
            </ElTag>
          </div>
        </section>

        <div class="detail-grid test-pay-detail-grid">
          <div class="detail-item">
            <span>支付方式</span>
            <strong>{{ displayAccountTypeLabel(testPayResult.type) }}</strong>
          </div>
          <div class="detail-item">
            <span>当前通道</span>
            <strong>{{ currentAccountLabel }}</strong>
          </div>
          <div class="detail-item">
            <span>系统订单号</span>
            <strong class="mono-text">{{ displayAccountFieldText(testPayResult.trade_no) }}</strong>
          </div>
          <div class="detail-item">
            <span>测试订单号</span>
            <strong class="mono-text">
              {{ displayAccountFieldText(testPayResult.out_trade_no) }}
            </strong>
          </div>
          <div v-if="isUsdtResult" class="detail-item">
            <span>下单金额</span>
            <strong>{{ formatBaseAmount(testPayResult) }}</strong>
          </div>
          <div v-if="isUsdtResult" class="detail-item">
            <span>换算汇率</span>
            <strong>{{ formatExchangeRate(testPayResult) }}</strong>
          </div>
          <div v-if="isUsdtResult" class="detail-item detail-item--wide">
            <span>USDT 地址</span>
            <strong class="mono-text">
              {{ displayAccountFieldText(testPayResult.wallet_address) }}
            </strong>
          </div>
        </div>

        <div
          v-if="testPayResult.state === 'paid'"
          class="test-pay-state-card test-pay-state-card--paid"
        >
          <h5>测试成功</h5>
          <p>{{ paidStateDescription }}</p>
        </div>

        <div
          v-else-if="testPayResult.state === 'reconciling'"
          class="test-pay-state-card test-pay-state-card--reconciling"
        >
          <h5>{{ reconcilingTitle }}</h5>
          <p>{{ reconcilingDescription }}</p>
        </div>

        <div
          v-else-if="testPayResult.state === 'timeout'"
          class="test-pay-state-card test-pay-state-card--timeout"
        >
          <h5>订单已超时</h5>
          <p>当前二维码已失效，如需继续测试，请点击底部“重新发起”生成新的测试订单。</p>
        </div>

        <div v-else class="test-pay-side-panel">
          <div v-if="testPayResult.qrcode_url" class="test-pay-visual">
            <img
              :src="testPayResult.qrcode_url"
              :alt="testPayResult.display_mode === 'image' ? '收款码图片' : '测试二维码'"
            />
          </div>

          <div v-else class="test-pay-empty">
            {{
              testPayResult.can_poll
                ? '二维码返回中，请稍候刷新。'
                : '当前没有可展示的二维码内容。'
            }}
          </div>

          <div v-if="showPayCountdown" class="test-pay-countdown">
            <span class="test-pay-countdown__label">支付倒计时</span>
            <strong>{{ payCountdownText }}</strong>
          </div>
        </div>

        <div
          v-if="
            testPayResult.state !== 'paid' &&
            testPayResult.state !== 'reconciling' &&
            testPayResult.state !== 'timeout'
          "
          class="test-pay-links"
        >
          <a :href="testPayResult.pay_url" target="_blank" rel="noreferrer">打开收银台</a>
          <a
            v-if="testPayResult.direct_open_url && !isUsdtResult"
            :href="testPayResult.direct_open_url"
            target="_blank"
            rel="noreferrer"
          >
            直接打开链接
          </a>
        </div>
      </template>
    </div>

    <template #footer>
      <div class="dialog-footer">
        <ElButton v-if="testPayResult?.can_poll" :loading="pollingTestPay" @click="emit('refresh')">
          刷新状态
        </ElButton>
        <ElButton @click="emit('update:visible', false)">关闭</ElButton>
        <ElButton
          v-if="activeTestPayAccount && hasTestPayAuth"
          type="primary"
          :loading="testingTestPay"
          @click="emit('submit')"
        >
          {{ testPayResult ? '重新发起' : '发起测试' }}
        </ElButton>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import { ElTag } from 'element-plus'
  import {
    displayAccountCode,
    displayAccountFieldText,
    displayAccountTypeLabel
  } from '@/views/shared/paymentAccountDisplay'

  type AccountItem = Api.Payments.AccountListItem
  type AccountTestPayResult = import('@/api/merchant').MerchantChannelTestPayResponse

  interface TestPayForm {
    pay_amount: string
  }

  interface Props {
    visible: boolean
    testPayForm: TestPayForm
    testingTestPay: boolean
    pollingTestPay: boolean
    testPayResult: AccountTestPayResult | null
    activeTestPayAccount: AccountItem | null
    hasTestPayAuth: boolean
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:visible', value: boolean): void
    (e: 'update:test-pay-form', value: TestPayForm): void
    (e: 'refresh'): void
    (e: 'submit'): void
  }>()

  const handleVisibilityChange = (value: boolean) => emit('update:visible', value)

  const testPayAmount = computed<string>({
    get: () => props.testPayForm.pay_amount,
    set: (value) =>
      emit('update:test-pay-form', {
        ...props.testPayForm,
        pay_amount: value
      })
  })

  const currentAccountLabel = computed(() =>
    props.activeTestPayAccount
      ? `${displayAccountCode(props.activeTestPayAccount.code_label)} / #${props.activeTestPayAccount.id}`
      : '--'
  )

  const isUsdtAccount = computed(
    () =>
      String(props.activeTestPayAccount?.code || '')
        .trim()
        .toLowerCase() === 'usdt'
  )

  const isUsdtResult = computed(
    () =>
      String(
        props.testPayResult?.type ||
          props.activeTestPayAccount?.type ||
          props.activeTestPayAccount?.code ||
          ''
      )
        .trim()
        .toLowerCase() === 'usdt'
  )

  const amountInputLabel = computed(() => (isUsdtAccount.value ? '下单金额' : '支付金额'))
  const amountInputUnit = computed(() => '元')
  const amountInputHint = computed(() =>
    isUsdtAccount.value
      ? '请输入人民币金额，系统会按当前通道汇率自动换算成 USDT 后生成测试订单。'
      : '可自定义测试金额，发起后会生成对应的测试订单和支付二维码。'
  )
  const resultAmountLabel = computed(() => (isUsdtResult.value ? '应付 USDT' : '支付金额'))
  const reconcilingTitle = computed(() =>
    isUsdtResult.value ? '正在核对链上到账' : '正在核对支付结果'
  )
  const reconcilingDescription = computed(() =>
    isUsdtResult.value
      ? '订单支付时限已到，系统仍会继续检查链上到账情况；确认到账后，状态会自动更新。'
      : '账单可能存在同步延迟，系统会继续自动核对本笔测试订单的支付结果。'
  )
  const paidStateDescription = computed(() =>
    isUsdtResult.value
      ? '测试订单已确认到账。你可以直接关闭弹窗，也可以保留当前结果继续核对通道配置。'
      : '测试订单已完成支付。你可以直接关闭弹窗，也可以继续留在当前页面查看本次测试结果。'
  )
  const currentAccountTitle = computed(() =>
    props.activeTestPayAccount ? `当前通道：${currentAccountLabel.value}` : '当前通道：未选择'
  )

  const countdownRemaining = ref(0)
  const showPayCountdown = computed(() => {
    if (!props.testPayResult) {
      return false
    }

    return !['paid', 'reconciling', 'timeout'].includes(props.testPayResult.state)
  })
  const payCountdownText = computed(() => formatCountdown(countdownRemaining.value))

  let countdownTimer: ReturnType<typeof setInterval> | null = null

  function resolveTestPayTagType(state: AccountTestPayResult['state']) {
    if (state === 'paid') {
      return 'success'
    }
    if (state === 'timeout') {
      return 'danger'
    }
    if (state === 'loading' || state === 'missing' || state === 'reconciling') {
      return 'warning'
    }

    return 'primary'
  }

  function resolveTestPayHeroEyebrow(state: AccountTestPayResult['state']) {
    if (state === 'paid') {
      return '测试成功'
    }
    if (state === 'timeout') {
      return '测试超时'
    }
    if (state === 'reconciling') {
      return isUsdtResult.value ? '链上核对中' : '账单核对中'
    }
    if (state === 'loading' || state === 'missing') {
      return isUsdtResult.value ? '等待钱包地址' : '等待二维码'
    }

    return '等待支付'
  }

  function resolveTestPayHeroTitle(state: AccountTestPayResult['state']) {
    if (state === 'paid') {
      return '支付确认成功'
    }
    if (state === 'timeout') {
      return '测试订单已超时'
    }
    if (state === 'reconciling') {
      return '支付结果核对中'
    }
    if (state === 'loading') {
      return isUsdtResult.value ? '正在准备钱包二维码' : '正在生成支付二维码'
    }
    if (state === 'missing') {
      return isUsdtResult.value ? '等待通道返回钱包地址' : '等待通道返回二维码'
    }

    return isUsdtResult.value ? '请按金额完成 USDT 测试' : '请扫码完成通道测试'
  }

  function resolveTestPayHeroDescription(result: AccountTestPayResult) {
    if (result.state === 'paid') {
      return isUsdtResult.value
        ? `本次 ${displayAccountTypeLabel(result.type)} 测试订单已经确认到账，当前弹窗会保留成功结果，方便你继续核对通道状态。`
        : `本次 ${displayAccountTypeLabel(result.type)} 测试订单已经完成支付，当前弹窗会保留成功结果，方便你继续核对通道状态。`
    }

    if (result.state === 'timeout') {
      return isUsdtResult.value
        ? '这笔测试订单已超过有效时间，如链上仍在处理，系统会在核对完成后自动更新。'
        : '这笔测试订单已超过有效时间，当前二维码不可继续使用，请重新发起新的测试订单。'
    }

    if (result.state === 'reconciling') {
      return isUsdtResult.value
        ? '转账时限已结束，系统仍在继续核对链上到账结果。'
        : '二维码已过期，系统正在等待账单同步并继续核对本笔支付结果。'
    }

    if (result.state === 'loading') {
      return isUsdtResult.value
        ? '系统正在准备钱包地址二维码，弹窗会继续自动轮询，你也可以手动刷新状态。'
        : '系统正在生成支付二维码，弹窗会继续自动轮询，你也可以手动刷新状态。'
    }

    if (result.state === 'missing') {
      return isUsdtResult.value
        ? '系统已创建测试订单，但钱包地址或二维码暂未返回，请稍候刷新。'
        : '系统已创建测试订单，但二维码暂未返回，请稍候刷新。'
    }

    return isUsdtResult.value
      ? '请按下方金额向钱包地址转账，到账后弹窗会自动刷新为成功状态。'
      : `请使用 ${displayAccountTypeLabel(result.type)} 扫描下方二维码完成支付，成功后弹窗会自动切换为成功状态。`
  }

  function formatLocaleAmount(value?: null | number | string, digits = 2) {
    return Number(value || 0).toLocaleString('zh-CN', {
      minimumFractionDigits: digits,
      maximumFractionDigits: digits
    })
  }

  function formatResultAmount(result: AccountTestPayResult) {
    const amount = formatLocaleAmount(result.pay_amount || 0)
    const unit = String(result.pay_amount_unit || '')
      .trim()
      .toUpperCase()
    return unit === 'USDT' ? `${amount} USDT` : `${amount} 元`
  }

  function formatBaseAmount(result: AccountTestPayResult) {
    const amount = formatLocaleAmount(result.base_amount || 0)
    return `￥${amount}`
  }

  function formatExchangeRate(result: AccountTestPayResult) {
    const rate = String(result.exchange_rate || '').trim()
    return rate ? `1 USDT = ￥${rate}` : '汇率未配置'
  }

  function formatCountdown(seconds: number) {
    const normalized = Math.max(0, Number.isFinite(seconds) ? Math.floor(seconds) : 0)
    const minutes = Math.floor(normalized / 60)
    const remain = normalized % 60
    return `${String(minutes).padStart(2, '0')}:${String(remain).padStart(2, '0')}`
  }

  function stopCountdownTimer() {
    if (countdownTimer !== null) {
      clearInterval(countdownTimer)
      countdownTimer = null
    }
  }

  function syncCountdownFromResult(result: AccountTestPayResult | null) {
    stopCountdownTimer()

    if (!result || ['paid', 'reconciling', 'timeout'].includes(result.state)) {
      countdownRemaining.value = 0
      return
    }

    const expiresAtTimestamp = Number(result.expires_at_timestamp || 0)
    if (expiresAtTimestamp > 0) {
      const tickByTimestamp = () => {
        countdownRemaining.value = Math.max(0, expiresAtTimestamp - Math.floor(Date.now() / 1000))
        if (countdownRemaining.value <= 0) {
          stopCountdownTimer()
        }
      }

      tickByTimestamp()
      countdownTimer = setInterval(tickByTimestamp, 1000)
      return
    }

    countdownRemaining.value = Math.max(0, Number(result.expires_seconds || 0))
    if (countdownRemaining.value <= 0) {
      return
    }

    countdownTimer = setInterval(() => {
      countdownRemaining.value = Math.max(0, countdownRemaining.value - 1)
      if (countdownRemaining.value <= 0) {
        stopCountdownTimer()
      }
    }, 1000)
  }

  watch(
    () => [
      props.visible,
      props.testPayResult?.trade_no,
      props.testPayResult?.state,
      props.testPayResult?.expires_at_timestamp,
      props.testPayResult?.expires_seconds
    ],
    () => {
      if (!props.visible) {
        stopCountdownTimer()
        countdownRemaining.value = 0
        return
      }

      syncCountdownFromResult(props.testPayResult)
    },
    { immediate: true }
  )

  onBeforeUnmount(() => {
    stopCountdownTimer()
  })
</script>

<style scoped lang="scss">
  .channel-shell-dialog :deep(.el-dialog__header) {
    padding-bottom: 12px;
    margin-right: 0;
  }

  .channel-shell-dialog :deep(.el-dialog__body) {
    padding-top: 8px;
  }

  .channel-shell-dialog :deep(.el-dialog__footer) {
    padding-top: 14px;
    border-top: 1px solid var(--el-border-color-lighter);
  }

  .test-pay-panel {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .test-pay-panel--result {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 232px;
    align-items: start;
    gap: 14px;
  }

  .test-pay-amount-bar {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 18px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 18px;
    background: rgb(248 250 252 / 82%);
  }

  .test-pay-amount-bar__label {
    flex: none;
    color: var(--el-text-color-primary);
    font-weight: 600;
    white-space: nowrap;
  }

  .test-pay-amount-bar__input {
    max-width: 260px;
  }

  .test-pay-amount-bar__copy {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 4px;
    color: var(--el-text-color-secondary);
  }

  .test-pay-amount-bar__title,
  .test-pay-amount-bar__hint {
    margin: 0;
    line-height: 1.6;
  }

  .test-pay-amount-bar__title {
    color: var(--el-text-color-primary);
    font-size: 13px;
    font-weight: 600;
  }

  .test-pay-amount-bar__hint {
    font-size: 12px;
  }

  .test-pay-hero {
    display: flex;
    align-items: stretch;
    justify-content: space-between;
    gap: 14px;
    padding: 18px;
    border: 1px solid transparent;
    border-radius: 20px;
  }

  .test-pay-panel--result .test-pay-hero {
    grid-column: 1 / -1;
  }

  .test-pay-hero--ready {
    border-color: rgb(96 165 250 / 28%);
    background:
      linear-gradient(135deg, rgb(239 246 255 / 95%), rgb(219 234 254 / 78%)),
      linear-gradient(135deg, rgb(37 99 235 / 5%), rgb(14 165 233 / 4%));
  }

  .test-pay-hero--loading,
  .test-pay-hero--reconciling,
  .test-pay-hero--missing {
    border-color: rgb(251 191 36 / 32%);
    background:
      linear-gradient(135deg, rgb(255 251 235 / 96%), rgb(254 243 199 / 80%)),
      linear-gradient(135deg, rgb(245 158 11 / 5%), rgb(249 115 22 / 4%));
  }

  .test-pay-hero--paid {
    border-color: rgb(16 185 129 / 30%);
    background:
      linear-gradient(135deg, rgb(236 253 245 / 96%), rgb(209 250 229 / 82%)),
      linear-gradient(135deg, rgb(16 185 129 / 5%), rgb(5 150 105 / 4%));
  }

  .test-pay-hero--timeout {
    border-color: rgb(248 113 113 / 30%);
    background:
      linear-gradient(135deg, rgb(254 242 242 / 97%), rgb(254 226 226 / 82%)),
      linear-gradient(135deg, rgb(239 68 68 / 5%), rgb(249 115 22 / 4%));
  }

  .test-pay-hero__copy {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
    gap: 6px;
  }

  .test-pay-hero__eyebrow {
    margin: 0;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgb(71 85 105 / 86%);
  }

  .test-pay-hero__copy h4 {
    margin: 0;
    font-size: 24px;
    line-height: 1.15;
    color: var(--el-text-color-primary);
  }

  .test-pay-hero__copy p {
    margin: 0;
    max-width: 44ch;
    color: var(--el-text-color-secondary);
    line-height: 1.6;
  }

  .test-pay-hero__aside {
    display: flex;
    min-width: 178px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 16px;
    border-radius: 18px;
    border: 1px solid rgb(255 255 255 / 72%);
    background: rgb(255 255 255 / 75%);
    box-shadow: inset 0 1px 0 rgb(255 255 255 / 50%);
    text-align: center;
  }

  .test-pay-hero__aside span {
    font-size: 12px;
    color: var(--el-text-color-secondary);
  }

  .test-pay-hero__aside strong {
    font-size: 30px;
    line-height: 1;
    color: var(--el-text-color-primary);
    font-variant-numeric: tabular-nums;
  }

  .test-pay-hero__aside-meta {
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 1.5;
    text-align: center;
  }

  .detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .test-pay-panel--result .test-pay-detail-grid {
    grid-column: 1;
    grid-row: 2;
    gap: 10px;
    align-content: start;
  }

  .detail-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 88px;
    padding: 14px 16px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 14px;
    background: linear-gradient(180deg, rgb(255 255 255 / 100%), rgb(248 250 252 / 90%));
  }

  .detail-item span {
    color: var(--el-text-color-secondary);
    font-size: 12px;
  }

  .detail-item strong {
    line-height: 1.65;
    word-break: break-word;
  }

  .detail-item--wide {
    grid-column: 1 / -1;
  }

  .test-pay-detail-grid .detail-item {
    min-height: 82px;
    gap: 4px;
    border-radius: 16px;
  }

  .test-pay-panel--result .test-pay-state-card,
  .test-pay-panel--result .test-pay-side-panel {
    grid-column: 2;
    grid-row: 2 / span 2;
  }

  .test-pay-side-panel {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .test-pay-state-card {
    padding: 18px;
    border-radius: 18px;
    border: 1px solid var(--el-border-color-lighter);
    background: linear-gradient(180deg, rgb(255 255 255 / 100%), rgb(248 250 252 / 90%));
  }

  .test-pay-state-card h5 {
    margin: 0 0 8px;
    font-size: 20px;
    color: var(--el-text-color-primary);
  }

  .test-pay-state-card p {
    margin: 0;
    color: var(--el-text-color-secondary);
    line-height: 1.7;
  }

  .test-pay-state-card--paid {
    border-color: rgb(16 185 129 / 28%);
    background:
      linear-gradient(135deg, rgb(240 253 244 / 98%), rgb(220 252 231 / 84%)),
      linear-gradient(135deg, rgb(16 185 129 / 5%), rgb(34 197 94 / 4%));
  }

  .test-pay-state-card--reconciling {
    border-color: rgb(245 158 11 / 30%);
    background:
      linear-gradient(135deg, rgb(255 251 235 / 98%), rgb(254 243 199 / 84%)),
      linear-gradient(135deg, rgb(245 158 11 / 5%), rgb(234 179 8 / 4%));
  }

  .test-pay-state-card--timeout {
    border-color: rgb(248 113 113 / 28%);
    background:
      linear-gradient(135deg, rgb(254 242 242 / 98%), rgb(254 226 226 / 84%)),
      linear-gradient(135deg, rgb(248 113 113 / 5%), rgb(249 115 22 / 4%));
  }

  .test-pay-visual,
  .test-pay-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 228px;
    padding: 14px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 18px;
    background: rgb(248 250 252 / 82%);
  }

  .test-pay-visual img {
    width: 100%;
    max-width: 214px;
    max-height: 214px;
    object-fit: contain;
    border-radius: 14px;
    background: #fff;
  }

  .test-pay-empty {
    color: var(--el-text-color-secondary);
    text-align: center;
    line-height: 1.7;
  }

  .test-pay-countdown {
    display: flex;
    min-height: 68px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 12px 14px;
    border: 1px solid rgb(96 165 250 / 24%);
    border-radius: 16px;
    background: linear-gradient(180deg, rgb(255 255 255 / 100%), rgb(239 246 255 / 88%));
    text-align: center;
  }

  .test-pay-countdown__label {
    color: var(--el-text-color-secondary);
    font-size: 12px;
  }

  .test-pay-countdown strong {
    color: var(--el-text-color-primary);
    font-size: 24px;
    line-height: 1;
    font-variant-numeric: tabular-nums;
  }

  .test-pay-links {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .test-pay-panel--result .test-pay-links {
    grid-column: 1;
    grid-row: 3;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  }

  .test-pay-links a {
    display: inline-flex;
    min-height: 36px;
    align-items: center;
    justify-content: center;
    padding: 0 14px;
    border: 1px solid rgb(96 165 250 / 22%);
    border-radius: 10px;
    background: rgb(239 246 255 / 80%);
    color: var(--el-color-primary);
    text-decoration: none;
    font-weight: 500;
  }

  :global(html.dark .channel-shell-dialog .test-pay-amount-bar) {
    border-color: rgb(71 85 105 / 40%);
    background: linear-gradient(180deg, rgb(15 23 42 / 90%), rgb(30 41 59 / 82%));
    box-shadow: 0 12px 28px rgb(2 6 23 / 28%);
  }

  :global(html.dark .channel-shell-dialog .test-pay-hero--ready) {
    border-color: rgb(59 130 246 / 34%);
    background:
      linear-gradient(135deg, rgb(15 23 42 / 92%), rgb(30 41 59 / 84%)),
      linear-gradient(135deg, rgb(59 130 246 / 10%), rgb(14 165 233 / 6%));
  }

  :global(html.dark .channel-shell-dialog .test-pay-hero--loading),
  :global(html.dark .channel-shell-dialog .test-pay-hero--reconciling),
  :global(html.dark .channel-shell-dialog .test-pay-hero--missing) {
    border-color: rgb(245 158 11 / 30%);
    background:
      linear-gradient(135deg, rgb(15 23 42 / 92%), rgb(30 41 59 / 84%)),
      linear-gradient(135deg, rgb(245 158 11 / 10%), rgb(249 115 22 / 6%));
  }

  :global(html.dark .channel-shell-dialog .test-pay-hero--paid) {
    border-color: rgb(16 185 129 / 30%);
    background:
      linear-gradient(135deg, rgb(15 23 42 / 92%), rgb(30 41 59 / 84%)),
      linear-gradient(135deg, rgb(16 185 129 / 10%), rgb(34 197 94 / 6%));
  }

  :global(html.dark .channel-shell-dialog .test-pay-hero--timeout) {
    border-color: rgb(248 113 113 / 30%);
    background:
      linear-gradient(135deg, rgb(15 23 42 / 92%), rgb(30 41 59 / 84%)),
      linear-gradient(135deg, rgb(248 113 113 / 10%), rgb(249 115 22 / 6%));
  }

  :global(html.dark .channel-shell-dialog .test-pay-hero__eyebrow) {
    color: rgb(148 163 184 / 88%);
  }

  :global(html.dark .channel-shell-dialog .test-pay-hero__aside),
  :global(html.dark .channel-shell-dialog .detail-item),
  :global(html.dark .channel-shell-dialog .test-pay-state-card),
  :global(html.dark .channel-shell-dialog .test-pay-visual),
  :global(html.dark .channel-shell-dialog .test-pay-empty) {
    border-color: rgb(71 85 105 / 36%);
    background: linear-gradient(180deg, rgb(15 23 42 / 88%), rgb(30 41 59 / 82%));
    box-shadow: 0 12px 28px rgb(2 6 23 / 24%);
  }

  :global(html.dark .channel-shell-dialog .test-pay-visual img) {
    background: rgb(15 23 42 / 92%);
  }

  :global(html.dark .channel-shell-dialog .test-pay-countdown) {
    border-color: rgb(96 165 250 / 24%);
    background: linear-gradient(180deg, rgb(15 23 42 / 88%), rgb(30 41 59 / 80%));
  }

  .dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
  }

  .dialog-footer :deep(.el-button) {
    min-width: 96px;
    height: 36px;
    border-radius: 10px;
  }

  @media (width <= 991px) {
    .test-pay-panel--result,
    .detail-grid {
      grid-template-columns: minmax(0, 1fr);
    }

    .test-pay-amount-bar {
      flex-direction: column;
      align-items: stretch;
    }

    .test-pay-amount-bar__input {
      max-width: none;
    }

    .test-pay-hero {
      flex-direction: column;
    }

    .test-pay-panel--result .test-pay-detail-grid,
    .test-pay-panel--result .test-pay-state-card,
    .test-pay-panel--result .test-pay-side-panel,
    .test-pay-panel--result .test-pay-links {
      grid-column: auto;
      grid-row: auto;
    }

    .test-pay-hero__copy h4 {
      font-size: 24px;
    }

    .test-pay-hero__aside {
      min-width: 0;
    }
  }
</style>
