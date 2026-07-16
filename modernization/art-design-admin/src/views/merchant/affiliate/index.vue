<template>
  <div class="merchant-page">
    <section class="merchant-page-header">
      <div class="merchant-page-header__title">
        <h1>推广返佣</h1>
        <p>查看邀请商户、返佣统计与可直接复制的推广链接。</p>
      </div>

      <div class="merchant-chip-row" v-if="payload?.summary">
        <span class="merchant-chip"
          >返佣类型 {{ translateMerchantText(payload.summary.rebate_type_label) }}</span
        >
        <span class="merchant-chip">返佣比例 {{ payload.summary.percentage_display || '--' }}</span>
        <span class="merchant-chip">最近邀请 {{ payload.summary.last_invite_time || '--' }}</span>
      </div>
    </section>

    <div v-if="loading" class="merchant-panel merchant-state-card">
      <ElSkeleton :rows="7" animated />
    </div>

    <div v-else-if="featureMessage" class="merchant-panel merchant-state-card">
      <h3>当前不可用</h3>
      <p>{{ featureMessage }}</p>
    </div>

    <template v-else-if="payload">
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
              <h2>推广链接</h2>
              <p>复制后可直接邀请注册。</p>
            </div>
          </div>

          <div class="merchant-soft-panel merchant-affiliate-link">
            <div class="merchant-affiliate-link__head">
              <div class="merchant-affiliate-link__icon">
                <Icon icon="ri:share-forward-line" />
              </div>

              <div class="merchant-affiliate-link__copy">
                <strong>当前推广地址</strong>
                <span>可直接邀请注册</span>
              </div>
            </div>

            <div class="merchant-code-block merchant-affiliate-link__code">
              {{ payload.summary.invite_url || '暂无推广地址' }}
            </div>
          </div>

          <div class="merchant-kv-grid">
            <div class="merchant-kv-item">
              <span>上级商户</span>
              <div>{{ translateMerchantText(payload.summary.parent_affiliate_label) }}</div>
            </div>
            <div class="merchant-kv-item">
              <span>最近邀请时间</span>
              <div>{{ payload.summary.last_invite_time || '--' }}</div>
            </div>
          </div>

          <div class="merchant-form-actions merchant-form-actions--split">
            <ElButton type="primary" @click="copyInviteUrl">复制推广链接</ElButton>
          </div>
        </article>

        <article class="merchant-card">
          <div class="merchant-card__head">
            <div>
              <h2>返佣策略</h2>
              <p>查看当前返佣规则。</p>
            </div>
          </div>

          <div class="merchant-affiliate-policy">
            <section class="merchant-soft-panel merchant-affiliate-policy__panel">
              <strong>返佣类型</strong>
              <p>{{ translateMerchantText(payload.summary.rebate_type_label) }}</p>
            </section>
            <section class="merchant-soft-panel merchant-affiliate-policy__panel">
              <strong>返佣比例</strong>
              <p>{{ payload.summary.percentage_display || '--' }}</p>
            </section>
          </div>
        </article>
      </section>

      <article class="merchant-card">
        <div class="merchant-card__head">
          <div>
            <h2>邀请商户列表</h2>
            <p>按账号和实名状态筛选。</p>
          </div>

          <div class="merchant-toolbar-pills">
            <div class="merchant-toolbar-pill">
              <span>实名筛选</span>
              <strong>{{ currentVerifiedLabel }}</strong>
            </div>
            <div v-if="filters.keyword" class="merchant-toolbar-pill">
              <span>关键词</span>
              <strong>{{ filters.keyword }}</strong>
            </div>
          </div>
        </div>

        <div class="merchant-table-toolbar">
          <div class="merchant-table-toolbar__filters merchant-table-toolbar__filters--affiliate">
            <ElInput
              v-model.trim="filters.keyword"
              placeholder="搜索商户账号或名称"
              clearable
              @keyup.enter="loadAffiliate(true)"
            >
              <template #prefix>
                <Icon icon="ri:search-line" />
              </template>
            </ElInput>

            <ElSelect
              v-model="filters.verified"
              clearable
              placeholder="实名状态"
              style="width: 160px"
            >
              <ElOption label="已实名" value="1" />
              <ElOption label="未实名" value="0" />
            </ElSelect>

            <ElButton type="primary" @click="loadAffiliate(true)">查询</ElButton>
            <ElButton plain :disabled="!hasActiveFilters" @click="resetFilters">重置</ElButton>
          </div>

        </div>

        <ElTable :data="records" empty-text="暂无邀请商户">
          <ElTableColumn label="商户名称" min-width="180" show-overflow-tooltip>
            <template #default="{ row }">
              {{ displayAffiliateName(row) }}
            </template>
          </ElTableColumn>
          <ElTableColumn label="登录账号" min-width="220" show-overflow-tooltip>
            <template #default="{ row }">
              {{ displayAffiliateUsername(row) }}
            </template>
          </ElTableColumn>
          <ElTableColumn prop="vip_label" label="会员状态" min-width="120">
            <template #default="{ row }">
              {{ translateMerchantText(row.vip_label) }}
            </template>
          </ElTableColumn>
          <ElTableColumn prop="verified_label" label="实名状态" width="120">
            <template #default="{ row }">
              <ElTag :type="row.verified_type" effect="plain">
                {{ translateMerchantText(row.verified_label) }}
              </ElTag>
            </template>
          </ElTableColumn>
          <ElTableColumn prop="balance_display" label="余额" width="120" />
          <ElTableColumn prop="create_time" label="注册时间" min-width="180" />
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
  import { ElMessage } from 'element-plus'
  import {
    MerchantApiError,
    fetchMerchantAffiliate,
    isMerchantFeatureDisabled
  } from '@/api/merchant'
  import {
    formatMerchantDisplayName,
    formatMerchantIdentity,
    translateMerchantText
  } from '../shared/text'

  defineOptions({ name: 'MerchantAffiliate' })

  const loading = ref(true)
  const featureMessage = ref('')
  const payload = ref<Record<string, any> | null>(null)
  const records = ref<Record<string, any>[]>([])
  const pagination = reactive({
    current: 1,
    size: 10,
    total: 0
  })

  const filters = reactive({
    keyword: '',
    verified: ''
  })

  const summaryCards = computed(() => [
    {
      label: '邀请商户数',
      value: String(payload.value?.summary?.invite_count ?? 0),
      hint: '当前筛选条件下的邀请商户总量',
      icon: 'ri:team-line'
    },
    {
      label: '实名商户数',
      value: String(payload.value?.summary?.verified_invite_count ?? 0),
      hint: '已完成实名认证的邀请商户数量',
      icon: 'ri:verified-badge-line'
    },
    {
      label: '累计返佣',
      value: payload.value?.summary?.total_rebate_display || '0.00',
      hint: '历史累计返佣总额',
      icon: 'ri:money-dollar-circle-line'
    },
    {
      label: '今日返佣',
      value: payload.value?.summary?.today_rebate_display || '0.00',
      hint: '今日新增返佣金额',
      icon: 'ri:calendar-check-line'
    }
  ])

  const hasActiveFilters = computed(() => filters.keyword !== '' || filters.verified !== '')

  const currentVerifiedLabel = computed(() => {
    if (filters.verified === '1') {
      return '已实名'
    }
    if (filters.verified === '0') {
      return '未实名'
    }
    return '全部'
  })

  function resolveMerchantId(row: Record<string, any>) {
    const merchantId = Number(row.id || row.merchant_id || row.user_id || 0)
    return Number.isFinite(merchantId) && merchantId > 0 ? merchantId : 0
  }

  function displayAffiliateName(row: Record<string, any>) {
    return formatMerchantDisplayName(
      row.display_name,
      row.username,
      resolveMerchantId(row),
      '邀请商户'
    )
  }

  function displayAffiliateUsername(row: Record<string, any>) {
    return formatMerchantIdentity(row.username, {
      merchantId: resolveMerchantId(row),
      fallback: '--',
      defaultLabel: '邀请商户'
    })
  }

  async function loadAffiliate(resetPage = false) {
    if (resetPage) {
      pagination.current = 1
    }

    loading.value = true
    featureMessage.value = ''
    try {
      const result = await fetchMerchantAffiliate({
        current: pagination.current,
        size: pagination.size,
        keyword: filters.keyword,
        verified: filters.verified
      })

      payload.value = {
        summary: result.summary
      }
      records.value = result.records
      pagination.current = result.pagination.current
      pagination.size = result.pagination.size
      pagination.total = result.pagination.total
    } catch (error) {
      if (isMerchantFeatureDisabled(error)) {
        featureMessage.value = translateMerchantText(
          error instanceof MerchantApiError
            ? error.message
            : 'merchant affiliate feature is disabled'
        )
      } else {
        const message =
          error instanceof MerchantApiError
            ? translateMerchantText(error.message, error.message)
            : '推广返佣数据加载失败'
        ElMessage.error(message)
      }
    } finally {
      loading.value = false
    }
  }

  async function copyInviteUrl() {
    const inviteUrl = payload.value?.summary?.invite_url
    if (!inviteUrl) {
      ElMessage.warning('当前暂无可复制的推广链接')
      return
    }

    try {
      await navigator.clipboard.writeText(inviteUrl)
      ElMessage.success('推广链接已复制')
    } catch {
      ElMessage.error('推广链接复制失败，请手动复制')
    }
  }

  function resetFilters() {
    filters.keyword = ''
    filters.verified = ''
    loadAffiliate(true)
  }

  function handlePageChange(page: number) {
    pagination.current = page
    loadAffiliate()
  }

  onMounted(() => {
    loadAffiliate()
  })
</script>

<style lang="scss">
  @use '../styles';
</style>

<style lang="scss" scoped>
  .merchant-affiliate-link {
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-bottom: 16px;
  }

  .merchant-affiliate-link__head {
    display: flex;
    gap: 14px;
    align-items: center;
  }

  .merchant-affiliate-link__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 46px;
    height: 46px;
    color: var(--main-color);
    background: var(--merchant-active-bg);
    border-radius: 14px;
    font-size: 20px;
    flex-shrink: 0;
  }

  .merchant-affiliate-link__copy {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .merchant-affiliate-link__copy strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
    font-weight: 700;
  }

  .merchant-affiliate-link__copy span {
    color: var(--merchant-muted);
    font-size: 13px;
    line-height: 1.7;
  }

  .merchant-affiliate-link__code {
    margin-top: 0;
  }

  .merchant-affiliate-policy {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }

  .merchant-affiliate-policy__panel {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-height: 120px;
  }

  .merchant-affiliate-policy__panel strong {
    color: var(--merchant-muted);
    font-size: 12px;
    font-weight: 500;
  }

  .merchant-affiliate-policy__panel p {
    margin: 0;
    color: var(--merchant-heading-color);
    font-size: 18px;
    font-weight: 700;
    line-height: 1.5;
  }

  .merchant-affiliate-note {
    margin-top: 16px;
  }

  .merchant-form-actions--split {
    justify-content: space-between;
  }

  .merchant-table-toolbar__filters--affiliate {
    flex: 1;
  }

  .merchant-table-toolbar__filters--affiliate :deep(.el-input) {
    width: 320px;
    max-width: 100%;
  }

  @media (width <= 900px) {
    .merchant-form-actions--split {
      justify-content: flex-end;
    }
  }

  @media (width <= 820px) {
    .merchant-affiliate-policy {
      grid-template-columns: 1fr;
    }
  }

  @media (width <= 768px) {
    .merchant-table-toolbar__filters--affiliate > * {
      width: 100% !important;
    }
  }
</style>
