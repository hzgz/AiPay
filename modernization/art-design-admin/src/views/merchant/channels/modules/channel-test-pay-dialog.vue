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
        <span class="test-pay-amount-bar__label">支付金额</span>
        <ElInput
          v-model="testPayForm.pay_amount"
          class="test-pay-amount-bar__input"
          maxlength="12"
          inputmode="decimal"
          placeholder="1.00"
          :disabled="testingTestPay"
        >
          <template #append>元</template>
        </ElInput>
        <div class="test-pay-amount-bar__copy">
          <p class="test-pay-amount-bar__title">{{ currentAccountTitle }}</p>
          <p class="test-pay-amount-bar__hint">可自定义测试金额，发起后将生成对应测试订单与收款码。</p>
        </div>
      </div>

      <template v-if="testPayResult">
        <section :class="['test-pay-hero', `test-pay-hero--${testPayResult.state}`]">
          <div class="test-pay-hero__copy">
            <p class="test-pay-hero__eyebrow">{{ resolveTestPayHeroEyebrow(testPayResult.state) }}</p>
            <h4>{{ resolveTestPayHeroTitle(testPayResult.state) }}</h4>
            <p>{{ resolveTestPayHeroDescription(testPayResult) }}</p>
          </div>
          <div class="test-pay-hero__aside">
            <span>支付金额</span>
            <strong>{{ formatAmount(Number(testPayResult.pay_amount || 0)) }}</strong>
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
            <strong class="mono-text">{{
              displayAccountFieldText(testPayResult.out_trade_no)
            }}</strong>
          </div>
        </div>

        <div v-if="testPayResult.state === 'paid'" class="test-pay-state-card test-pay-state-card--paid">
          <h5>支付已完成</h5>
          <p>本次测试订单已成功入账，状态会自动同步到订单列表。你可以直接关闭弹窗，或继续发起下一笔测试。</p>
        </div>
        <div
          v-else-if="testPayResult.state === 'reconciling'"
          class="test-pay-state-card test-pay-state-card--reconciling"
        >
          <h5>正在核对支付宝账单</h5>
          <p>账单可能延迟同步，系统会继续核对 5 分钟；确认入账后，状态将自动更新。</p>
        </div>
        <div
          v-else-if="testPayResult.state === 'timeout'"
          class="test-pay-state-card test-pay-state-card--timeout"
        >
          <h5>订单已超时</h5>
          <p>当前二维码已经失效，如需继续测试，请点击底部“重新发起”生成新的测试订单和收款码。</p>
        </div>
        <div v-else-if="testPayResult.qrcode_url" class="test-pay-visual">
          <img
            :src="testPayResult.qrcode_url"
            :alt="testPayResult.display_mode === 'image' ? '收款码图片' : '测试二维码'"
          />
        </div>
        <div v-else class="test-pay-empty">
          {{
            testPayResult.can_poll ? '二维码返回中，请稍候刷新。' : '当前没有可展示的二维码内容。'
          }}
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
            v-if="testPayResult.direct_open_url"
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
  import { computed } from 'vue'
  import { ElTag } from 'element-plus'
  import {
    displayAccountCode,
    displayAccountFieldText,
    displayAccountTypeLabel
  } from '@/views/shared/paymentAccountDisplay'
  import { formatPaymentAccountAmount as formatAmount } from '@/views/shared/paymentAccountPageShared'

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
    (e: 'refresh'): void
    (e: 'submit'): void
  }>()

  const handleVisibilityChange = (value: boolean) => emit('update:visible', value)

  const currentAccountLabel = computed(() =>
    props.activeTestPayAccount
      ? `${displayAccountCode(props.activeTestPayAccount.code_label)} / #${props.activeTestPayAccount.id}`
      : '--'
  )

  const currentAccountTitle = computed(() =>
    props.activeTestPayAccount ? `当前通道：${currentAccountLabel.value}` : '当前通道：未选择'
  )

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
      return '账单核对中'
    }
    if (state === 'loading' || state === 'missing') {
      return '等待收款码'
    }

    return '等待支付'
  }

  function resolveTestPayHeroTitle(state: AccountTestPayResult['state']) {
    if (state === 'paid') {
      return '测试订单已支付完成'
    }
    if (state === 'timeout') {
      return '测试订单已超时'
    }
    if (state === 'reconciling') {
      return '支付结果核对中'
    }
    if (state === 'loading') {
      return '正在生成收款码'
    }
    if (state === 'missing') {
      return '等待通道返回二维码'
    }

    return '请扫码完成通道测试'
  }

  function resolveTestPayHeroDescription(result: AccountTestPayResult) {
    if (result.state === 'paid') {
      return `本次 ${displayAccountTypeLabel(result.type)} 测试订单已经完成支付，系统订单与商户订单状态均已同步更新。`
    }
    if (result.state === 'timeout') {
      return '这笔测试订单已经超过有效时间，当前二维码不可继续使用，请重新发起新的测试订单。'
    }
    if (result.state === 'reconciling') {
      return '二维码已过期，系统正在等待支付宝账单同步，并继续自动查询本笔支付结果。'
    }
    if (result.state === 'loading') {
      return '上游通道正在生成收款码，弹窗会继续自动轮询；如果等待较久，也可以手动点击刷新状态。'
    }
    if (result.state === 'missing') {
      return '系统已创建测试订单，但收款码暂未返回。请稍候片刻，弹窗会自动继续检查最新状态。'
    }

    return `请使用 ${displayAccountTypeLabel(result.type)} 扫描下方二维码完成支付，支付成功后弹窗会自动切换为成功状态。`
  }
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
    background: rgb(248 250 252 / 0.82);
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
    border-color: rgb(96 165 250 / 0.28);
    background:
      linear-gradient(135deg, rgb(239 246 255 / 0.95), rgb(219 234 254 / 0.78)),
      linear-gradient(135deg, rgb(37 99 235 / 0.05), rgb(14 165 233 / 0.04));
  }

  .test-pay-hero--loading,
  .test-pay-hero--reconciling,
  .test-pay-hero--missing {
    border-color: rgb(251 191 36 / 0.32);
    background:
      linear-gradient(135deg, rgb(255 251 235 / 0.96), rgb(254 243 199 / 0.8)),
      linear-gradient(135deg, rgb(245 158 11 / 0.05), rgb(249 115 22 / 0.04));
  }

  .test-pay-hero--paid {
    border-color: rgb(16 185 129 / 0.3);
    background:
      linear-gradient(135deg, rgb(236 253 245 / 0.96), rgb(209 250 229 / 0.82)),
      linear-gradient(135deg, rgb(16 185 129 / 0.05), rgb(5 150 105 / 0.04));
  }

  .test-pay-hero--timeout {
    border-color: rgb(248 113 113 / 0.3);
    background:
      linear-gradient(135deg, rgb(254 242 242 / 0.97), rgb(254 226 226 / 0.82)),
      linear-gradient(135deg, rgb(239 68 68 / 0.05), rgb(249 115 22 / 0.04));
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
    color: rgb(71 85 105 / 0.86);
  }

  .test-pay-hero__copy h4 {
    margin: 0;
    font-size: 24px;
    line-height: 1.15;
    color: var(--el-text-color-primary);
  }

  .test-pay-hero__copy p {
    margin: 0;
    color: var(--el-text-color-secondary);
    line-height: 1.6;
    max-width: 44ch;
  }

  .test-pay-hero__aside {
    display: flex;
    min-width: 178px;
    flex-direction: column;
    justify-content: space-between;
    align-items: flex-end;
    gap: 8px;
    padding: 14px 16px;
    border-radius: 18px;
    border: 1px solid rgb(255 255 255 / 0.72);
    background: rgb(255 255 255 / 0.75);
    box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.5);
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
    background: linear-gradient(180deg, rgb(255 255 255 / 1), rgb(248 250 252 / 0.9));
  }

  .detail-item span {
    color: var(--el-text-color-secondary);
    font-size: 12px;
  }

  .detail-item strong {
    line-height: 1.65;
    word-break: break-word;
  }

  .test-pay-detail-grid .detail-item {
    min-height: 82px;
    gap: 4px;
    border-radius: 16px;
  }

  .test-pay-panel--result .test-pay-state-card,
  .test-pay-panel--result .test-pay-visual,
  .test-pay-panel--result .test-pay-empty {
    grid-column: 2;
    grid-row: 2 / span 2;
  }

  .test-pay-state-card {
    padding: 18px;
    border-radius: 18px;
    border: 1px solid var(--el-border-color-lighter);
    background: linear-gradient(180deg, rgb(255 255 255 / 1), rgb(248 250 252 / 0.9));
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
    border-color: rgb(16 185 129 / 0.28);
    background:
      linear-gradient(135deg, rgb(240 253 244 / 0.98), rgb(220 252 231 / 0.84)),
      linear-gradient(135deg, rgb(16 185 129 / 0.05), rgb(34 197 94 / 0.04));
  }

  .test-pay-state-card--reconciling {
    border-color: rgb(245 158 11 / 0.3);
    background:
      linear-gradient(135deg, rgb(255 251 235 / 0.98), rgb(254 243 199 / 0.84)),
      linear-gradient(135deg, rgb(245 158 11 / 0.05), rgb(234 179 8 / 0.04));
  }

  .test-pay-state-card--timeout {
    border-color: rgb(248 113 113 / 0.28);
    background:
      linear-gradient(135deg, rgb(254 242 242 / 0.98), rgb(254 226 226 / 0.84)),
      linear-gradient(135deg, rgb(248 113 113 / 0.05), rgb(249 115 22 / 0.04));
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
    background: rgb(248 250 252 / 0.82);
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
    align-items: center;
    justify-content: center;
    min-height: 36px;
    padding: 0 14px;
    border: 1px solid rgb(96 165 250 / 0.22);
    border-radius: 10px;
    background: rgb(239 246 255 / 0.8);
    color: var(--el-color-primary);
    text-decoration: none;
    font-weight: 500;
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
    .test-pay-panel--result .test-pay-visual,
    .test-pay-panel--result .test-pay-empty,
    .test-pay-panel--result .test-pay-links {
      grid-column: auto;
      grid-row: auto;
    }

    .test-pay-hero__copy h4 {
      font-size: 24px;
    }

    .test-pay-hero__aside {
      min-width: 0;
      align-items: flex-start;
    }
  }
</style>
