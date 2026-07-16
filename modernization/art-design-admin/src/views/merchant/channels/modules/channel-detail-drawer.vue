<template>
  <ElDrawer
    :model-value="visible"
    size="780px"
    class="channel-shell-drawer"
    destroy-on-close
    :title="drawerTitle"
    @update:model-value="handleVisibilityChange"
  >
    <div v-loading="detailLoading" class="payment-account-detail">
      <template v-if="activeAccount">
        <section class="detail-hero detail-hero--drawer">
          <div class="detail-hero-copy">
            <h3>{{ displayAccountCode(activeAccount.code_label) }}</h3>
            <p>{{ displayAccountFieldText(activeAccount.merchant_display) }}</p>
            <span>{{
              displayAccountTypeSummary(activeAccount.type_text || activeAccount.type_label, activeAccount.type)
            }}</span>
          </div>
          <div class="detail-tags">
            <ElTag :type="tagType(activeAccount.status_type)" effect="light">
              {{ displayAccountStatus(activeAccount) }}
            </ElTag>
            <ElTag :type="tagType(activeAccount.is_status_type)" effect="plain">
              {{ displayAccountEnabledStatus(activeAccount) }}
            </ElTag>
          </div>
        </section>

        <section class="channel-form-summary channel-form-summary--drawer">
          <article class="channel-form-summary__card">
            <span>通道编号</span>
            <strong>#{{ activeAccount.id }} / {{ displayAccountCode(activeAccount.code) }}</strong>
          </article>
          <article class="channel-form-summary__card">
            <span>支付类型</span>
            <strong>{{ displayAccountType(activeAccount) }}</strong>
          </article>
          <article class="channel-form-summary__card">
            <span>标识来源</span>
            <strong>{{ displayAccountIdentifierSource(activeAccount.identifier_source) }}</strong>
          </article>
        </section>

        <section class="channel-form-section channel-form-section--actions">
          <div class="channel-form-section__head">
            <h4>快捷操作</h4>
          </div>
          <div class="channel-detail-actions">
            <ElButton
              v-if="hasTestPayAuth"
              plain
              :disabled="detailLoading"
              @click="emit('test')"
            >
              测试
            </ElButton>
            <ElButton v-if="hasEditAuth" plain :disabled="detailLoading" @click="emit('edit')">
              编辑限额
            </ElButton>
            <ElButton
              v-if="hasEditAuth && canEditCredentials"
              plain
              :disabled="detailLoading"
              @click="emit('editCredentials')"
            >
              编辑凭证
            </ElButton>
            <ElButton
              v-if="hasStatusAuth"
              plain
              :disabled="detailLoading"
              @click="emit('editStatus')"
            >
              修改状态
            </ElButton>
            <ElButton
              v-if="hasDeleteAuth"
              plain
              type="danger"
              :disabled="detailLoading"
              @click="emit('delete')"
            >
              删除通道
            </ElButton>
          </div>
        </section>

        <section class="channel-form-section">
          <div class="channel-form-section__head">
            <h4>通道标识与凭证</h4>
          </div>
          <div class="detail-grid">
            <div class="detail-item">
              <span>账号标识</span>
              <strong class="mono-text">
                {{ displayAccountIdentifierValue(activeAccount.identifier, activeAccount.has_identifier) }}
              </strong>
            </div>
            <div class="detail-item">
              <span>二维码 / 私钥</span>
              <strong>{{ activeAccount.has_qr_url ? '已配置' : '未配置' }}</strong>
            </div>
            <div class="detail-item">
              <span>路由模式</span>
              <strong>{{ displayAccountQrType(activeAccount) }}</strong>
            </div>
            <div class="detail-item">
              <span>登录凭证 / 公钥</span>
              <strong>{{ activeAccount.has_cookie ? '已配置' : '未配置' }}</strong>
            </div>
            <div class="detail-item">
              <span>备用备注</span>
              <strong>{{ activeAccount.has_remark ? '已配置' : '未配置' }}</strong>
            </div>
            <div class="detail-item">
              <span>微信凭证标识 / 证书序列号</span>
              <strong>{{ activeAccount.has_wx_guid ? '已配置' : '未配置' }}</strong>
            </div>
            <div class="detail-item">
              <span>云端标识</span>
              <strong>{{ activeAccount.has_cloud_id ? '已配置' : '未配置' }}</strong>
            </div>
          </div>
        </section>

        <section class="channel-form-section">
          <div class="channel-form-section__head">
            <h4>运行限额</h4>
          </div>
          <div class="detail-grid">
            <div class="detail-item">
              <span>备注</span>
              <strong>{{ displayAccountMemo(activeAccount) }}</strong>
            </div>
            <div class="detail-item">
              <span>余额</span>
              <strong>{{ formatAmount(activeAccount.account_balance) }}</strong>
            </div>
            <div class="detail-item">
              <span>单日限额</span>
              <strong>{{ formatLimit(activeAccount.daymaxcount, activeAccount.daymaxmoney) }}</strong>
            </div>
            <div class="detail-item">
              <span>累计限额</span>
              <strong>{{ formatLimit(activeAccount.allmaxcount, activeAccount.allmaxmoney) }}</strong>
            </div>
          </div>
        </section>

        <section class="channel-form-section">
          <div class="channel-form-section__head">
            <h4>订单统计</h4>
          </div>
          <div class="detail-grid">
            <div class="detail-item">
              <span>总订单数</span>
              <strong>{{ activeAccount.order_count }}</strong>
            </div>
            <div class="detail-item">
              <span>已付订单</span>
              <strong>{{ activeAccount.paid_order_count }}</strong>
            </div>
            <div class="detail-item">
              <span>待处理订单</span>
              <strong>{{ activeAccount.pending_order_count }}</strong>
            </div>
            <div class="detail-item">
              <span>已付金额</span>
              <strong>{{ formatAmount(activeAccount.paid_amount) }}</strong>
            </div>
            <div class="detail-item">
              <span>创建时间</span>
              <strong>{{ activeAccount.create_time || '--' }}</strong>
            </div>
            <div class="detail-item">
              <span>更新时间</span>
              <strong>{{ activeAccount.update_time || '--' }}</strong>
            </div>
          </div>
        </section>
      </template>
    </div>
  </ElDrawer>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import { ElTag } from 'element-plus'
  import {
    displayAccountCode,
    displayAccountFieldText,
    displayAccountIdentifierSource,
    displayAccountIdentifierValue,
    displayAccountTypeLabel,
    displayAccountTypeSummary
  } from '@/views/shared/paymentAccountDisplay'
  import {
    formatPaymentAccountAmount as formatAmount,
    formatPaymentAccountLimit as formatLimit,
    resolvePaymentAccountTagType as tagType
  } from '@/views/shared/paymentAccountPageShared'

  type AccountItem = Api.Payments.AccountListItem

  interface Props {
    visible: boolean
    detailLoading: boolean
    activeAccount: AccountItem | null
    hasTestPayAuth: boolean
    hasEditAuth: boolean
    hasStatusAuth: boolean
    hasDeleteAuth: boolean
    canEditCredentials: boolean
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:visible', value: boolean): void
    (e: 'test'): void
    (e: 'edit'): void
    (e: 'editCredentials'): void
    (e: 'editStatus'): void
    (e: 'delete'): void
  }>()

  const drawerTitle = computed(() =>
    props.activeAccount
      ? `${displayAccountCode(props.activeAccount.code_label)} / #${props.activeAccount.id}`
      : '支付通道详情'
  )

  function displayAccountType(account?: Partial<AccountItem> | null, fallback = '--') {
    return displayAccountTypeLabel(account?.type_text || account?.type_label || account?.type, fallback)
  }

  function displayAccountStatus(account?: Partial<AccountItem> | null, fallback = '--') {
    return displayAccountFieldText(account?.status_text || account?.status_label, fallback)
  }

  function displayAccountEnabledStatus(account?: Partial<AccountItem> | null, fallback = '--') {
    return displayAccountFieldText(account?.is_status_text || account?.is_status_label, fallback)
  }

  function displayAccountQrType(account?: Partial<AccountItem> | null, fallback = '未配置') {
    return displayAccountFieldText(account?.qr_type_text || account?.qr_type_label || account?.qr_type, fallback)
  }

  function displayAccountMemo(account?: Partial<AccountItem> | null, fallback = '--') {
    return displayAccountFieldText(account?.memo_text || account?.memo_label || account?.memo, fallback)
  }

  const handleVisibilityChange = (value: boolean) => emit('update:visible', value)
</script>

<style scoped lang="scss">
  .payment-account-detail {
    display: flex;
    flex-direction: column;
    gap: 14px;
    min-height: 240px;
  }

  .channel-shell-drawer :deep(.el-drawer__header) {
    margin-bottom: 0;
    padding: 20px 22px 14px;
    border-bottom: 1px solid rgb(226 232 240 / 0.9);
  }

  .channel-shell-drawer :deep(.el-drawer__title) {
    color: var(--el-text-color-primary);
    font-size: 16px;
    font-weight: 700;
    letter-spacing: 0.01em;
  }

  .channel-shell-drawer :deep(.el-drawer__body) {
    padding: 18px 22px 22px;
    background:
      radial-gradient(circle at top right, rgb(59 130 246 / 0.07), transparent 30%),
      linear-gradient(180deg, rgb(248 250 252 / 0.72), rgb(255 255 255 / 1) 160px);
  }

  .detail-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 4px 0 20px;
  }

  .detail-hero--drawer {
    padding: 18px;
    border: 1px solid rgb(226 232 240 / 0.9);
    border-radius: 20px;
    background:
      linear-gradient(135deg, rgb(255 255 255 / 0.98), rgb(248 250 252 / 0.9)),
      linear-gradient(135deg, rgb(59 130 246 / 0.08), rgb(14 165 233 / 0.04));
    box-shadow: 0 12px 32px rgb(15 23 42 / 0.05);
  }

  .detail-hero-copy {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .detail-hero-copy h3 {
    margin: 0;
    color: var(--el-text-color-primary);
    font-size: 22px;
  }

  .detail-hero-copy p,
  .detail-hero-copy span {
    margin: 0;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 1.6;
    word-break: break-all;
  }

  .detail-tags {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
  }

  .detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .mono-text {
    font-family:
      ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New',
      monospace;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.02em;
    word-break: break-all;
  }

  .channel-form-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
  }

  .channel-form-summary--drawer {
    gap: 14px;
  }

  .channel-form-summary__card,
  .channel-form-section {
    padding: 14px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 16px;
    background: linear-gradient(180deg, rgb(255 255 255 / 1), rgb(248 250 252 / 0.92));
  }

  .channel-form-summary__card {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-height: 76px;
    box-shadow: 0 10px 24px rgb(15 23 42 / 0.03);
  }

  .channel-form-summary__card span {
    color: var(--el-text-color-secondary);
    font-size: 12px;
  }

  .channel-form-summary__card strong {
    color: var(--el-text-color-primary);
    font-size: 15px;
    word-break: break-word;
  }

  .channel-form-section {
    display: flex;
    flex-direction: column;
    gap: 10px;
    box-shadow: 0 10px 24px rgb(15 23 42 / 0.03);
  }

  .channel-form-section--actions {
    gap: 14px;
  }

  .channel-form-section__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .channel-form-section__head h4 {
    margin: 0;
    color: var(--el-text-color-primary);
    font-size: 15px;
    font-weight: 700;
  }

  .channel-detail-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
  }

  .channel-detail-actions :deep(.el-button) {
    min-width: 96px;
    height: 36px;
    margin-left: 0;
    border-radius: 10px;
    font-weight: 500;
    transition:
      transform 0.2s ease,
      box-shadow 0.2s ease,
      border-color 0.2s ease;
  }

  .channel-detail-actions :deep(.el-button:hover) {
    transform: translateY(-1px);
    box-shadow: 0 10px 20px rgb(15 23 42 / 0.06);
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
    color: var(--el-text-color-primary);
    line-height: 1.65;
    word-break: break-word;
  }

  @media (width <= 768px) {
    .channel-shell-drawer :deep(.el-drawer__header),
    .channel-shell-drawer :deep(.el-drawer__body) {
      padding-right: 16px;
      padding-left: 16px;
    }

    .detail-hero--drawer,
    .channel-form-summary__card,
    .channel-form-section,
    .detail-item {
      border-radius: 16px;
    }

    .channel-detail-actions :deep(.el-button) {
      width: 100%;
    }
  }

  @media (width <= 991px) {
    .detail-hero {
      flex-direction: column;
      align-items: flex-start;
    }

    .detail-tags {
      justify-content: flex-start;
    }

    .detail-grid,
    .channel-form-summary {
      grid-template-columns: 1fr;
    }

    .channel-detail-actions :deep(.el-button) {
      flex: 1 1 calc(50% - 5px);
    }
  }
</style>
