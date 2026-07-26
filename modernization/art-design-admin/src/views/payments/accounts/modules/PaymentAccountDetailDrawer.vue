<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <ElDrawer v-model="visible" size="780px" destroy-on-close :title="drawerTitle">
    <div v-loading="detailLoading" class="payment-account-detail">
      <template v-if="activeAccount">
        <section class="detail-hero">
          <div class="detail-hero-copy">
            <h3>{{ displayAccountCode(activeAccount.code_label) }}</h3>
            <p>{{ displayAccountFieldText(activeAccount.merchant_display) }}</p>
            <span>{{
              displayAccountTypeSummary(activeAccount.type_label, activeAccount.type)
            }}</span>
          </div>
          <div class="detail-hero-actions">
            <ElTag :type="tagType(activeAccount.status_type)" effect="light">
              {{ activeAccount.status_label }}
            </ElTag>
            <ElTag :type="tagType(activeAccount.is_status_type)" effect="plain">
              {{ activeAccount.is_status_label }}
            </ElTag>
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
              删除账户
            </ElButton>
          </div>
        </section>

        <section class="detail-section">
          <h4>概览</h4>
          <div class="detail-grid">
            <div class="detail-item">
              <span>账户编号</span>
              <strong>#{{ activeAccount.id }}</strong>
            </div>
            <div class="detail-item">
              <span>所属商户</span>
              <strong>{{ displayAccountFieldText(activeAccount.merchant_display) }}</strong>
            </div>
            <div class="detail-item">
              <span>商户编号</span>
              <strong>{{ activeAccount.user_id || '--' }}</strong>
            </div>
            <div class="detail-item">
              <span>通道标识</span>
              <strong>{{ displayAccountCode(activeAccount.code) }}</strong>
            </div>
            <div class="detail-item">
              <span>支付类型</span>
              <strong>{{ displayAccountTypeLabel(activeAccount.type_label) }}</strong>
            </div>
            <div class="detail-item">
              <span>标识来源</span>
              <strong>{{
                displayAccountIdentifierSource(activeAccount.identifier_source)
              }}</strong>
            </div>
          </div>
        </section>

        <section class="detail-section">
          <h4>账号标识与凭证</h4>
          <ElDescriptions :column="1" border>
            <ElDescriptionsItem label="脱敏标识">
              <span class="mono-text">
                {{
                  displayAccountIdentifier(
                    activeAccount.identifier_masked,
                    activeAccount.has_identifier
                  )
                }}
              </span>
            </ElDescriptionsItem>
            <ElDescriptionsItem label="二维码 / 私钥">
              {{
                activeAccount.has_qr_url
                  ? `已配置，长度 ${activeAccount.qr_url_length}`
                  : '未配置'
              }}
            </ElDescriptionsItem>
            <ElDescriptionsItem label="登录凭证 / 公钥">
              {{
                activeAccount.has_cookie
                  ? `已配置，长度 ${activeAccount.cookie_length}`
                  : '未配置'
              }}
            </ElDescriptionsItem>
            <ElDescriptionsItem label="备用备注">
              {{ activeAccount.has_remark ? '已配置' : '未配置' }}
            </ElDescriptionsItem>
            <ElDescriptionsItem label="微信凭证标识 / 证书序列号">
              {{ activeAccount.has_wx_guid ? '已配置' : '未配置' }}
            </ElDescriptionsItem>
            <ElDescriptionsItem label="云端标识">
              {{ activeAccount.has_cloud_id ? '已配置' : '未配置' }}
            </ElDescriptionsItem>
          </ElDescriptions>
        </section>

        <section class="detail-section">
          <h4>运行限额</h4>
          <div class="detail-grid">
            <div class="detail-item">
              <span>备注</span>
              <strong>{{ displayAccountFieldText(activeAccount.memo_label) }}</strong>
            </div>
            <div class="detail-item">
              <span>余额</span>
              <strong>{{ formatAmount(activeAccount.account_balance) }}</strong>
            </div>
            <div class="detail-item">
              <span>单日限额</span>
              <strong>{{
                formatLimit(activeAccount.daymaxcount, activeAccount.daymaxmoney)
              }}</strong>
            </div>
            <div class="detail-item">
              <span>累计限额</span>
              <strong>{{
                formatLimit(activeAccount.allmaxcount, activeAccount.allmaxmoney)
              }}</strong>
            </div>
          </div>
        </section>

        <section class="detail-section">
          <h4>订单统计</h4>
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
  import { displayAdminMaskedPreview } from '@/utils/adminFixtureText'
  import {
    displayAccountCode,
    displayAccountFieldText,
    displayAccountIdentifierSource,
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
    modelValue: boolean
    detailLoading: boolean
    activeAccount: AccountItem | null
    hasEditAuth: boolean
    hasStatusAuth: boolean
    hasDeleteAuth: boolean
    canEditCredentials: boolean
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void
    (e: 'edit'): void
    (e: 'editCredentials'): void
    (e: 'editStatus'): void
    (e: 'delete'): void
  }>()

  const visible = computed({
    get: () => props.modelValue,
    set: (value: boolean) => emit('update:modelValue', value)
  })

  const drawerTitle = computed(() =>
    props.activeAccount
      ? `${displayAccountCode(props.activeAccount.code_label)} / #${props.activeAccount.id}`
      : '支付账户详情'
  )

  function displayAccountIdentifier(
    value: null | number | string | undefined,
    hasIdentifier: boolean
  ) {
    return displayAdminMaskedPreview(
      value,
      hasIdentifier ? '已脱敏标识' : '--',
      '已脱敏标识'
    )
  }
</script>

<style scoped lang="scss">
  .payment-account-detail {
    min-height: 240px;
    --account-detail-card-border: var(--el-border-color-lighter);
    --account-detail-card-bg: rgb(248 250 252 / 0.82);
  }

  :global(html.dark .payment-account-detail ){
    --account-detail-card-border: rgb(71 85 105 / 0.42);
    --account-detail-card-bg: rgb(15 23 42 / 0.84);
  }

  .detail-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 4px 0 20px;
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

  .detail-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: flex-end;
    align-items: center;
  }

  .detail-hero-actions :deep(.el-button) {
    min-width: 96px;
    height: 36px;
    margin-left: 0;
    border-radius: 10px;
    font-weight: 500;
  }

  .detail-section {
    margin-bottom: 24px;
  }

  .detail-section h4 {
    margin: 0 0 12px;
    color: var(--el-text-color-primary);
    font-size: 15px;
  }

  .detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .detail-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 14px 16px;
    border: 1px solid var(--account-detail-card-border);
    border-radius: 14px;
    background: var(--account-detail-card-bg);
  }

  .detail-item span {
    color: var(--el-text-color-secondary);
    font-size: 12px;
  }

  .detail-item strong {
    color: var(--el-text-color-primary);
    word-break: break-all;
  }

  .mono-text {
    font-family:
      ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New',
      monospace;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.02em;
    word-break: break-all;
  }

  @media (width <= 991px) {
    .detail-hero {
      flex-direction: column;
      align-items: flex-start;
    }

    .detail-hero-actions {
      justify-content: flex-start;
    }

    .detail-hero-actions :deep(.el-button) {
      flex: 1 1 calc(50% - 5px);
    }

    .detail-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
