<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <div class="merchant-page">
    <section class="merchant-page-header">
      <div class="merchant-page-header__title">
        <h1>会员套餐</h1>
        <p>查看套餐并购买或续费。</p>
      </div>

      <div v-if="!loading" class="merchant-chip-row">
        <span class="merchant-chip">套餐 {{ summary.total_count ?? 0 }} 个</span>
        <span class="merchant-chip">当前会员 {{ currentVipName }}</span>
        <span class="merchant-chip">到期 {{ currentVipExpiry }}</span>
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
        <article class="merchant-card">
          <div class="merchant-card__head">
            <div>
              <h2>当前会员状态</h2>
              <p>当前套餐可续费，切换后按新时长计算。</p>
            </div>
          </div>

          <div class="merchant-vip-current">
            <section class="merchant-soft-panel merchant-vip-current__hero">
              <div class="merchant-vip-current__head">
                <div class="merchant-vip-current__icon">
                  <Icon icon="ri:vip-crown-2-line" />
                </div>

                <div class="merchant-vip-current__copy">
                  <strong>{{ currentVipName }}</strong>
                  <span>{{ currentVipStatus }}</span>
                </div>
              </div>

              <div class="merchant-kv-grid merchant-kv-grid--single">
                <div class="merchant-kv-item">
                  <span>到期时间</span>
                  <div>{{ currentVipExpiry }}</div>
                </div>
                <div class="merchant-kv-item">
                  <span>适用状态</span>
                  <div>{{ currentVipStatus }}</div>
                </div>
              </div>
            </section>

            <div class="merchant-grid-2 merchant-vip-current__metrics">
              <section class="merchant-soft-panel merchant-vip-mini">
                <span>价格区间</span>
                <strong>{{ money(summary.min_price) }} - {{ money(summary.max_price) }}</strong>
                <p>按可购套餐统计</p>
              </section>

              <section class="merchant-soft-panel merchant-vip-mini">
                <span>套餐数量</span>
                <strong>{{ summary.total_count ?? 0 }} 个</strong>
                <p>含当前可续费套餐</p>
              </section>
            </div>
          </div>
        </article>

        <article class="merchant-card">
          <div class="merchant-card__head">
            <div>
              <h2>套餐卡片概览</h2>
              <p>展示费率、价格和时长。</p>
            </div>
          </div>

          <div class="merchant-vip-package-grid">
            <section
              v-for="item in packageCards"
              :key="item.id || item.name"
              class="merchant-soft-panel merchant-vip-package"
            >
              <div class="merchant-vip-package__head">
                <div>
                  <strong>{{ translateMerchantText(item.name) }}</strong>
                  <p>{{ translateMerchantText(item.duration_label) }}</p>
                </div>

                <ElTag :type="item.is_current ? 'success' : item.status_type" effect="plain">
                  {{ item.is_current ? '当前套餐' : translateMerchantText(item.status_label) }}
                </ElTag>
              </div>

              <div class="merchant-vip-package__price">{{
                item.money_display || money(item.money)
              }}</div>

              <div class="merchant-chip-row merchant-chip-row--compact">
                <span class="merchant-chip">费率 {{ item.fee_rate_display || '--' }}</span>
                <span class="merchant-chip">{{ translateMerchantText(item.purchase_message) }}</span>
              </div>

              <div class="merchant-vip-package__actions">
                <span class="merchant-fine-print">
                  {{ item.is_current ? '可直接续费' : '切换后重算时长' }}
                </span>
                <ElButton
                  :type="item.is_current ? 'warning' : 'primary'"
                  plain
                  :disabled="!canPurchase(item)"
                  :loading="purchaseLoadingId === Number(item.id || 0)"
                  @click="purchasePackage(item)"
                >
                  {{ item.is_current ? '续费' : '购买' }}
                </ElButton>
              </div>
            </section>
          </div>
        </article>
      </section>

      <article class="merchant-card">
        <div class="merchant-card__head">
          <div>
            <h2>会员套餐明细</h2>
            <p>表格中可继续购买或续费。</p>
          </div>
        </div>

        <ElTable :data="records" empty-text="暂无套餐">
          <ElTableColumn prop="name" label="套餐名称" min-width="180">
            <template #default="{ row }">
              {{ translateMerchantText(row.name) }}
            </template>
          </ElTableColumn>
          <ElTableColumn prop="money_display" label="价格" width="120" />
          <ElTableColumn prop="fee_rate_display" label="费率" width="120" />
          <ElTableColumn prop="duration_label" label="时长" width="120">
            <template #default="{ row }">
              {{ translateMerchantText(row.duration_label) }}
            </template>
          </ElTableColumn>
          <ElTableColumn prop="status_label" label="状态" width="120">
            <template #default="{ row }">
              <ElTag :type="row.status_type" effect="plain">
                {{ translateMerchantText(row.status_label) }}
              </ElTag>
            </template>
          </ElTableColumn>
          <ElTableColumn label="当前适用" width="120">
            <template #default="{ row }">
              <ElTag :type="row.is_current ? 'success' : 'info'" effect="plain">
                {{ row.is_current ? '当前套餐' : '可选套餐' }}
              </ElTag>
            </template>
          </ElTableColumn>
          <ElTableColumn label="购买提示" min-width="220">
            <template #default="{ row }">
              {{ translateMerchantText(row.purchase_message) }}
            </template>
          </ElTableColumn>
          <ElTableColumn label="操作" width="140" fixed="right">
            <template #default="{ row }">
              <ElButton
                :type="row.is_current ? 'warning' : 'primary'"
                link
                :disabled="!canPurchase(row)"
                :loading="purchaseLoadingId === Number(row.id || 0)"
                @click="purchasePackage(row)"
              >
                {{ row.is_current ? '续费' : '购买' }}
              </ElButton>
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
    </template>
  </div>
</template>

<script setup lang="ts">
  import { Icon } from '@iconify/vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import { useMerchantStore } from '@/store/modules/merchant'
  import {
    MerchantApiError,
    fetchMerchantVipPackages,
    purchaseMerchantVipPackage
  } from '@/api/merchant'
  import { translateMerchantText } from '../shared/text'

  defineOptions({ name: 'MerchantVip' })

  const merchantStore = useMerchantStore()
  const loading = ref(true)
  const purchaseLoadingId = ref<number | null>(null)
  const records = ref<Record<string, any>[]>([])
  const currentVip = ref<Record<string, any> | null>(null)
  const summary = ref<Record<string, any>>({})
  const writeActions = ref<Record<string, boolean>>({})
  const pagination = reactive({
    current: 1,
    size: 10,
    total: 0
  })

  const currentVipName = computed(() => translateMerchantText(currentVip.value?.name || '--'))
  const currentVipStatus = computed(() =>
    translateMerchantText(currentVip.value?.status_label || '--')
  )
  const currentVipExpiry = computed(() => currentVip.value?.vip_time || '未开通')

  const summaryCards = computed(() => [
    {
      label: '套餐总数',
      value: String(summary.value.total_count ?? 0),
      hint: '当前可直接购买的会员套餐数量',
      icon: 'ri:stack-line'
    },
    {
      label: '最低价格',
      value: money(summary.value.min_price),
      hint: '所有有效套餐中的最低价格',
      icon: 'ri:price-tag-3-line'
    },
    {
      label: '最高价格',
      value: money(summary.value.max_price),
      hint: '所有有效套餐中的最高价格',
      icon: 'ri:funds-box-line'
    },
    {
      label: '当前会员',
      value: currentVipName.value,
      hint: currentVipStatus.value,
      icon: 'ri:vip-crown-line'
    }
  ])

  const packageCards = computed(() => records.value.slice(0, 6))

  function money(value: unknown) {
    return Number(value || 0).toFixed(2)
  }

  function canPurchase(item: Record<string, any>) {
    return Boolean(writeActions.value.purchase) && Boolean(item.purchase_enabled)
  }

  async function loadVip() {
    loading.value = true
    try {
      const result = await fetchMerchantVipPackages({
        current: pagination.current,
        size: pagination.size
      })
      records.value = result.records
      currentVip.value = result.raw.current_vip || null
      summary.value = result.summary
      writeActions.value = result.writeActions || {}
      pagination.current = result.pagination.current
      pagination.size = result.pagination.size
      pagination.total = result.pagination.total
    } catch (error) {
      const message =
        error instanceof MerchantApiError
          ? translateMerchantText(error.message, error.message)
          : '会员套餐加载失败'
      ElMessage.error(message)
    } finally {
      loading.value = false
    }
  }

  async function purchasePackage(item: Record<string, any>) {
    const vipId = Number(item.id || 0)
    if (vipId <= 0 || !canPurchase(item)) {
      return
    }

    const confirmed = await ElMessageBox.confirm(
      `确认${item.is_current ? '续费' : '购买'}「${translateMerchantText(item.name)}」吗？本次将扣减余额 ${item.money_display || money(item.money)}。`,
      item.is_current ? '确认续费' : '确认购买',
      {
        type: 'warning',
        confirmButtonText: item.is_current ? '立即续费' : '立即购买',
        cancelButtonText: '取消'
      }
    )
      .then(() => true)
      .catch(() => false)

    if (!confirmed) {
      return
    }

    purchaseLoadingId.value = vipId
    try {
      const result = await purchaseMerchantVipPackage(vipId)
      ElMessage.success(translateMerchantText(result?.message || 'merchant vip purchase completed successfully'))
      await merchantStore.hydrate(true)
      await loadVip()
    } catch (error) {
      if (error instanceof MerchantApiError && error.code === 202) {
        const gotoRecharge = await ElMessageBox.confirm(
          `${translateMerchantText(error.message, error.message)}，是否前往充值中心？`,
          '余额不足',
          {
            type: 'warning',
            confirmButtonText: '前往充值',
            cancelButtonText: '稍后处理'
          }
        )
          .then(() => true)
          .catch(() => false)

        if (gotoRecharge) {
          window.location.hash = '#/merchant/recharges'
        }
        return
      }

      const message =
        error instanceof MerchantApiError
          ? translateMerchantText(error.message, error.message)
          : '会员套餐购买失败'
      ElMessage.error(message)
    } finally {
      purchaseLoadingId.value = null
    }
  }

  function handlePageChange(page: number) {
    pagination.current = page
    loadVip()
  }

  onMounted(() => {
    loadVip()
  })
</script>

<style lang="scss">
  @use '../styles';
</style>

<style lang="scss" scoped>
  .merchant-kv-grid--single {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .merchant-vip-current {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .merchant-vip-current__hero {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .merchant-vip-current__head {
    display: flex;
    gap: 14px;
    align-items: center;
  }

  .merchant-vip-current__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    color: #b45309;
    background: rgb(245 158 11 / 12%);
    border-radius: 16px;
    font-size: 24px;
    flex-shrink: 0;
  }

  .merchant-vip-current__copy {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .merchant-vip-current__copy strong {
    color: var(--merchant-heading-color);
    font-size: 18px;
    font-weight: 700;
    line-height: 1.2;
  }

  .merchant-vip-current__copy span {
    color: var(--merchant-muted);
    font-size: 13px;
    line-height: 1.7;
  }

  .merchant-vip-current__metrics {
    gap: 16px;
  }

  .merchant-vip-mini {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .merchant-vip-mini span,
  .merchant-vip-package__head p {
    color: var(--merchant-muted);
    font-size: 12px;
    line-height: 1.7;
  }

  .merchant-vip-mini strong {
    color: var(--merchant-heading-color);
    font-size: 18px;
    font-weight: 700;
    line-height: 1.3;
  }

  .merchant-vip-mini p {
    margin: 0;
    color: var(--merchant-muted);
    font-size: 13px;
    line-height: 1.75;
  }

  .merchant-vip-package-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }

  .merchant-vip-package {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .merchant-vip-package__head {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    justify-content: space-between;
  }

  .merchant-vip-package__head strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
    font-weight: 700;
    line-height: 1.3;
  }

  .merchant-vip-package__head p {
    margin: 6px 0 0;
  }

  .merchant-vip-package__price {
    color: var(--merchant-heading-color);
    font-size: 28px;
    font-weight: 700;
    line-height: 1.1;
  }

  .merchant-vip-package__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
  }

  .merchant-vip-package__actions .merchant-fine-print {
    max-width: 70%;
  }

  .merchant-vip-note {
    margin-bottom: 16px;
  }

  @media (width <= 980px) {
    .merchant-vip-package-grid {
      grid-template-columns: 1fr;
    }

    .merchant-vip-package__actions .merchant-fine-print {
      max-width: 100%;
    }
  }

  @media (width <= 820px) {
    .merchant-kv-grid--single {
      grid-template-columns: 1fr;
    }
  }
</style>
