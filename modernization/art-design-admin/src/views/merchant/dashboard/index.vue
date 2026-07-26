<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <div class="merchant-page">
    <section class="merchant-page-header">
      <div class="merchant-page-header__title">
        <h1>概览看板</h1>
      </div>

      <div class="merchant-chip-row">
        <span class="merchant-chip">当前商户 #{{ payload?.merchant_id || merchantStore.merchantId || '--' }}</span>
        <span class="merchant-chip">账户余额 {{ payload?.balance_display || merchantStore.balanceDisplay }}</span>
        <span class="merchant-chip">{{ translateMerchantText(payload?.vip_label || merchantStore.vipLabel) }}</span>
      </div>
    </section>

    <div v-if="loading" class="merchant-panel merchant-state-card">
      <ElSkeleton :rows="8" animated />
    </div>

    <template v-else-if="payload">
      <section class="merchant-stat-grid">
        <article
          v-for="card in primarySummaryCards"
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
        <article class="merchant-card">
          <div class="merchant-card__head">
            <div>
              <h2>商户资料</h2>
            </div>
          </div>

          <div class="merchant-kv-grid">
            <div class="merchant-kv-item">
              <span>商户编号</span>
              <strong>#{{ payload.merchant_id || '--' }}</strong>
            </div>
            <div class="merchant-kv-item">
              <span>登录账号</span>
              <div>{{ merchantLoginName }}</div>
            </div>
            <div class="merchant-kv-item">
              <span>显示名称</span>
              <div>{{ merchantDisplayName }}</div>
            </div>
            <div class="merchant-kv-item">
              <span>会员状态</span>
              <div>{{ translateMerchantText(payload.vip_label || merchantStore.vipLabel) }}</div>
            </div>
            <div class="merchant-kv-item">
              <span>当前费率</span>
              <div>{{ payload.fee_rate || '--' }}</div>
            </div>
            <div class="merchant-kv-item">
              <span>创建时间</span>
              <div>{{ payload.create_time || '--' }}</div>
            </div>
          </div>
        </article>

        <article class="merchant-card">
          <div class="merchant-card__head">
            <div>
              <h2>经营速览</h2>
            </div>
          </div>

          <div class="merchant-kv-grid merchant-kv-grid--dense">
            <div v-for="card in secondarySummaryCards" :key="card.label" class="merchant-kv-item">
              <span>{{ card.label }}</span>
              <div>{{ card.value }}</div>
            </div>
          </div>
        </article>
      </section>

      <article class="merchant-card">
        <div class="merchant-card__head">
          <div>
            <h2>快捷入口</h2>
          </div>
        </div>

        <div class="merchant-grid-3">
          <RouterLink
            v-for="item in shortcutItems"
            :key="item.path"
            :to="item.path"
            class="merchant-shortcut"
          >
            <div class="merchant-shortcut__icon-wrap">
              <Icon :icon="item.icon" class="merchant-shortcut__icon" />
            </div>
            <div class="merchant-shortcut__copy">
              <strong>{{ item.title }}</strong>
              <span>{{ item.description }}</span>
            </div>
          </RouterLink>
        </div>
      </article>
    </template>

    <div v-else class="merchant-panel merchant-state-card">
      <h3>看板暂时未加载成功</h3>
      <p>当前未能加载商户概览，请刷新页面后重试。</p>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { Icon } from '@iconify/vue'
  import { ElMessage } from 'element-plus'
  import { fetchMerchantDashboard, MerchantApiError } from '@/api/merchant'
  import { useMerchantStore } from '@/store/modules/merchant'
  import { merchantNavItems } from '../shared/navigation'
  import {
    formatMerchantDisplayName,
    formatMerchantIdentity,
    translateMerchantText
  } from '../shared/text'

  defineOptions({ name: 'MerchantDashboard' })

  const merchantStore = useMerchantStore()
  const loading = ref(true)
  const payload = ref<Record<string, any> | null>(null)
  const DASHBOARD_SHORTCUT_KEYS = [
    'profile',
    'api',
    'channels',
    'pools',
    'orders',
    'money-logs'
  ] as const
  const dashboardShortcutKeySet = new Set<string>(DASHBOARD_SHORTCUT_KEYS)

  const merchantIdentifier = computed(
    () => Number(payload.value?.merchant_id || merchantStore.merchantId || 0)
  )
  const merchantLoginName = computed(() =>
    formatMerchantIdentity(payload.value?.merchant_username, {
      merchantId: merchantIdentifier.value,
      fallback: '--',
      defaultLabel: '商户账户'
    })
  )
  const merchantDisplayName = computed(() =>
    formatMerchantDisplayName(
      payload.value?.display_name,
      payload.value?.merchant_username,
      merchantIdentifier.value,
      merchantStore.displayName || '商户账户'
    )
  )

  const shortcutItems = computed(() =>
    merchantNavItems.filter((item) => dashboardShortcutKeySet.has(item.key))
  )
  const summaryCards = computed(() => {
    const summary = payload.value?.summary || {}

    return [
      {
        label: '账户余额',
        value: payload.value?.balance_display || merchantStore.balanceDisplay,
        hint: '当前可用余额',
        icon: 'ri:wallet-3-line'
      },
      {
        label: '累计已付金额',
        value: summary.paid_amount_display || '--',
        hint: '累计成功到账金额',
        icon: 'ri:exchange-cny-line'
      },
      {
        label: '今日已付金额',
        value: summary.today_paid_amount_display || '--',
        hint: '今日成功交易金额',
        icon: 'ri:sun-line'
      },
      {
        label: '已付订单数',
        value: String(summary.paid_order_count ?? 0),
        hint: '成功订单数量',
        icon: 'ri:shopping-bag-3-line'
      },
      {
        label: '订单总数',
        value: String(summary.order_count ?? 0),
        hint: '全部交易订单数量',
        icon: 'ri:file-list-3-line'
      },
      {
        label: '本地账户数',
        value: String(summary.account_count ?? 0),
        hint: '已接入的收款账户数',
        icon: 'ri:bank-card-line'
      },
      {
        label: '支付通道数',
        value: String(summary.upstream_count ?? 0),
        hint: '已启用的支付通道数量',
        icon: 'ri:exchange-funds-line'
      },
      {
        label: '当前费率',
        value: payload.value?.fee_rate || '--',
        hint: translateMerchantText(payload.value?.vip_label || merchantStore.vipLabel),
        icon: 'ri:percent-line'
      }
    ]
  })

  const primarySummaryCards = computed(() => summaryCards.value.slice(0, 4))
  const secondarySummaryCards = computed(() => summaryCards.value.slice(4))

  async function loadDashboard() {
    loading.value = true
    try {
      payload.value = await fetchMerchantDashboard()
    } catch (error) {
      const message =
        error instanceof MerchantApiError
          ? translateMerchantText(error.message, error.message)
          : '概览数据加载失败'
      ElMessage.error(message)
    } finally {
      loading.value = false
    }
  }

  onMounted(() => {
    loadDashboard()
  })
</script>

<style lang="scss">
  @use '../styles';
</style>

<style lang="scss" scoped>
  .merchant-stat-card {
    min-height: 118px;
  }

  .merchant-stat-card__label {
    margin-bottom: 8px;
  }

  .merchant-stat-card__value {
    margin-bottom: 6px;
  }

  .merchant-stat-card__hint {
    display: block;
    color: var(--merchant-muted);
    font-size: 12px;
    line-height: 1.6;
  }

  .merchant-kv-grid--dense {
    margin-bottom: 18px;
  }

  .merchant-stat-card__row {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    justify-content: space-between;
  }

  .merchant-stat-card__copy {
    min-width: 0;
  }

  .merchant-stat-card__symbol {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 62px;
    height: 62px;
    color: var(--main-color);
    background: var(--merchant-active-bg);
    border-radius: 18px;
    font-size: 28px;
    flex-shrink: 0;
  }

  .merchant-shortcut {
    display: flex;
    gap: 14px;
    align-items: flex-start;
    padding: 18px;
    color: inherit;
    text-decoration: none;
    background: var(--merchant-panel-bg);
    border: 1px solid var(--merchant-soft-border);
    border-radius: 16px;
    transition:
      border-color 0.2s ease,
      transform 0.2s ease,
      box-shadow 0.2s ease;
  }

  .merchant-shortcut:hover {
    border-color: rgb(86 119 255 / 22%);
    box-shadow: 0 14px 28px rgb(86 119 255 / 10%);
    transform: translateY(-2px);
  }

  .merchant-shortcut__icon-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    background: var(--merchant-active-bg);
    border-radius: 12px;
    flex-shrink: 0;
  }

  .merchant-shortcut__icon {
    color: var(--main-color);
    font-size: 20px;
  }

  .merchant-shortcut__copy {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
  }

  .merchant-shortcut strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
    font-weight: 700;
    line-height: 1.4;
  }

  .merchant-shortcut span {
    color: var(--merchant-muted);
    display: -webkit-box;
    overflow: hidden;
    font-size: 12px;
    line-height: 1.65;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
  }
</style>
