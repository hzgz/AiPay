<template>
  <ElDrawer
    v-model="drawerVisible"
    size="760px"
    destroy-on-close
    :title="activeMerchant ? displayMerchantName(activeMerchant) : '商户详情'"
  >
    <div v-loading="detailLoading" class="merchant-detail">
      <template v-if="activeMerchant">
        <section class="detail-hero">
          <div class="detail-head">
            <ElAvatar :size="56" :src="activeMerchant.avatar" />
            <div class="detail-head-copy">
              <h3>{{ displayMerchantName(activeMerchant) }}</h3>
              <p>{{ displayMerchantProfileName(activeMerchant) }}</p>
              <span>ID {{ activeMerchant.id }}</span>
            </div>
          </div>
          <div class="detail-hero-actions">
            <ElTag :type="tagType(activeMerchant.status_type)" effect="light">
              {{ activeMerchant.status_label }}
            </ElTag>
            <ElTag :type="activeMerchant.real_name_verified ? 'success' : 'info'" effect="plain">
              {{ activeMerchant.real_name_status_label }}
            </ElTag>
            <ElTag :type="activeMerchant.is_vip ? 'warning' : 'info'" effect="plain">
              {{ activeMerchant.vip_status_label }}
            </ElTag>
            <ElButton v-if="hasEditAuth" plain :disabled="detailLoading" @click="emit('edit')">
              编辑资料
            </ElButton>
            <ElButton v-if="hasEditAuth" plain :disabled="detailLoading" @click="emit('business')">
              VIP / 费率
            </ElButton>
            <ElButton
              v-if="hasEditAuth"
              plain
              :disabled="detailLoading"
              @click="emit('notification')"
            >
              通知设置
            </ElButton>
            <ElButton
              v-if="hasEmailAuth"
              plain
              type="primary"
              :disabled="detailLoading"
              @click="emit('email')"
            >
              发送邮件
            </ElButton>
            <ElButton
              v-if="hasAdminLoginAuth"
              plain
              type="success"
              :disabled="detailLoading || impersonatingMerchant"
              @click="emit('impersonate')"
            >
              代登录商户
            </ElButton>
            <ElButton
              v-if="hasEditAuth"
              plain
              :type="activeMerchant.is_frozen ? 'success' : 'warning'"
              :disabled="detailLoading"
              @click="emit('status')"
            >
              {{ activeMerchant.is_frozen ? '解除冻结' : '冻结商户' }}
            </ElButton>
            <ElButton
              v-if="hasRemoveAuth"
              plain
              type="danger"
              :disabled="detailLoading"
              @click="emit('delete')"
            >
              删除商户
            </ElButton>
          </div>
        </section>

        <section class="detail-section">
          <h4>账户信息</h4>
          <div class="detail-grid">
            <div class="detail-item">
              <span>联系邮箱</span>
              <strong>{{ displayMerchantEmail(activeMerchant.email) }}</strong>
            </div>
            <div class="detail-item">
              <span>手机号</span>
              <strong>{{ displayAdminFixtureText(activeMerchant.mobile) || '--' }}</strong>
            </div>
            <div class="detail-item">
              <span>账户余额</span>
              <strong>{{ formatAmount(activeMerchant.balance) }}</strong>
            </div>
            <div class="detail-item">
              <span>当前费率</span>
              <strong>{{ activeMerchant.fee_rate_display }}</strong>
            </div>
            <div class="detail-item">
              <span>通讯密钥</span>
              <strong class="mono">{{ activeMerchant.appkey || '--' }}</strong>
            </div>
            <div class="detail-item">
              <span>超时秒数</span>
              <strong>{{ activeMerchant.timeout_time || 0 }} 秒</strong>
            </div>
          </div>
        </section>

        <section class="detail-section">
          <h4>套餐与通知</h4>
          <div class="detail-grid">
            <div class="detail-item">
              <span>VIP 状态</span>
              <strong>{{ activeMerchant.vip_status_label }}</strong>
            </div>
            <div class="detail-item">
              <span>会员到期</span>
              <strong>{{ activeMerchant.vip_expire_time || '--' }}</strong>
            </div>
            <div class="detail-item">
              <span>费率承担</span>
              <strong>{{ activeMerchant.is_rate ? '商户承担' : '平台承担' }}</strong>
            </div>
            <div class="detail-item">
              <span>新订单通知</span>
              <strong>{{ activeMerchant.order_tips_label }}</strong>
            </div>
            <div class="detail-item">
              <span>余额不足通知</span>
              <strong>{{ activeMerchant.low_balance_tips_label }}</strong>
            </div>
            <div class="detail-item">
              <span>余额提醒阈值</span>
              <strong>{{ activeMerchant.low_balance_threshold }}</strong>
            </div>
          </div>
          <div class="detail-meta-list">
            <ElTag :type="activeMerchant.email ? 'success' : 'info'" effect="plain">
              邮件 {{ activeMerchant.email ? '已就绪' : '未配置' }}
            </ElTag>
            <ElTag
              :type="activeMerchant.wxpusher_uid_configured ? 'success' : 'info'"
              effect="plain"
            >
              WxPusher
              {{
                activeMerchant.wxpusher_uid_configured
                  ? activeMerchant.wxpusher_uid_masked
                  : '未绑定'
              }}
            </ElTag>
            <ElTag :type="activeMerchant.tg_chat_id_configured ? 'success' : 'info'" effect="plain">
              Telegram
              {{
                activeMerchant.tg_chat_id_configured ? activeMerchant.tg_chat_id_masked : '未绑定'
              }}
            </ElTag>
          </div>
        </section>

        <section class="detail-section">
          <h4>交易统计</h4>
          <div class="detail-grid">
            <div class="detail-item">
              <span>订单总数</span>
              <strong>{{ activeMerchant.order_count }}</strong>
            </div>
            <div class="detail-item">
              <span>支付成功数</span>
              <strong>{{ activeMerchant.paid_order_count }}</strong>
            </div>
            <div class="detail-item">
              <span>累计实收</span>
              <strong>{{ formatAmount(activeMerchant.paid_amount) }}</strong>
            </div>
            <div class="detail-item">
              <span>今日实收</span>
              <strong>{{ formatAmount(activeMerchant.today_paid_amount) }}</strong>
            </div>
            <div class="detail-item">
              <span>最近订单时间</span>
              <strong>{{ activeMerchant.last_order_time || '--' }}</strong>
            </div>
            <div class="detail-item">
              <span>登录失败次数</span>
              <strong>{{ activeMerchant.loginfailure }}</strong>
            </div>
          </div>
        </section>

        <section class="detail-section">
          <h4>风控备注</h4>
          <ElDescriptions :column="1" border>
            <ElDescriptionsItem label="冻结原因">
              {{ activeMerchant.frozen_reason || '--' }}
            </ElDescriptionsItem>
            <ElDescriptionsItem label="内部备注">
              {{ activeMerchant.remarks || '--' }}
            </ElDescriptionsItem>
          </ElDescriptions>
        </section>
      </template>
    </div>
  </ElDrawer>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'

  defineOptions({ name: 'MerchantUserDetailDrawer' })

  type UserListItem = Api.Users.UserListItem

  interface Props {
    visible: boolean
    detailLoading: boolean
    activeMerchant: UserListItem | null
    hasEditAuth: boolean
    hasEmailAuth: boolean
    hasAdminLoginAuth: boolean
    hasRemoveAuth: boolean
    impersonatingMerchant: boolean
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:visible', value: boolean): void
    (e: 'edit'): void
    (e: 'business'): void
    (e: 'notification'): void
    (e: 'email'): void
    (e: 'impersonate'): void
    (e: 'status'): void
    (e: 'delete'): void
  }>()

  const drawerVisible = computed({
    get: () => props.visible,
    set: (value: boolean) => emit('update:visible', value)
  })

  function displayMerchantName(
    merchant: Pick<UserListItem, 'id' | 'userName'> | null | undefined
  ): string {
    if (!merchant) {
      return '--'
    }

    return displayAdminFixtureText(merchant.userName) || `商户 #${merchant.id}`
  }

  function displayMerchantProfileName(
    merchant: Pick<UserListItem, 'merchant_name'> | null | undefined,
    fallback = '未填写实名信息'
  ): string {
    return displayAdminFixtureText(merchant?.merchant_name) || fallback
  }

  function displayMerchantEmail(value: string | null | undefined, fallback = '--'): string {
    return displayAdminFixtureText(value) || fallback
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
    if (
      value === 'success' ||
      value === 'warning' ||
      value === 'info' ||
      value === 'danger' ||
      value === 'primary'
    ) {
      return value
    }

    return undefined
  }
</script>

<style scoped lang="scss">
  .merchant-detail {
    min-height: 240px;
  }

  .detail-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 4px 0 20px;
  }

  .detail-head {
    display: flex;
    align-items: center;
    gap: 14px;
  }

  .detail-head-copy {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .detail-head-copy h3 {
    margin: 0;
    color: var(--el-text-color-primary);
    font-size: 20px;
  }

  .detail-head-copy p,
  .detail-head-copy span {
    margin: 0;
    color: var(--el-text-color-secondary);
    font-size: 12px;
  }

  .detail-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
    align-items: center;
  }

  .detail-section {
    margin-top: 20px;
  }

  .detail-section h4 {
    margin: 0 0 12px;
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
    gap: 8px;
    padding: 14px 16px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 12px;
    background: var(--el-fill-color-blank);
  }

  .detail-item span {
    color: var(--el-text-color-secondary);
    font-size: 12px;
  }

  .detail-item strong {
    color: var(--el-text-color-primary);
    word-break: break-word;
  }

  .detail-meta-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
  }

  .mono {
    font-family: 'JetBrains Mono', 'Cascadia Code', monospace;
    word-break: break-all;
  }

  @media (max-width: 768px) {
    .detail-hero {
      align-items: flex-start;
      flex-direction: column;
    }

    .detail-grid {
      grid-template-columns: 1fr;
    }

    .detail-hero-actions {
      justify-content: flex-start;
    }
  }
</style>
