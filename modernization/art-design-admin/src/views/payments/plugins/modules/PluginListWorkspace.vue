<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <ElCard class="plugin-workspace-card" shadow="never">
    <div class="plugin-list-head">
      <h3 class="plugin-list-head__title">插件列表</h3>

      <div class="plugin-list-head__actions">
        <ElButton v-if="hasPluginScaffoldAuth" type="primary" @click="emit('scaffold')" v-ripple>
          新建插件
        </ElButton>
        <ElButton
          v-if="hasGovernanceData"
          plain
          :type="
            showGovernancePanels ? 'primary' : governanceAttentionCount > 0 ? 'warning' : undefined
          "
          @click="emit('toggle-governance')"
          v-ripple
        >
          {{ showGovernancePanels ? '收起回滚中心' : '回滚中心' }}
        </ElButton>
        <ElButton
          class="plugin-list-refresh"
          :loading="loading"
          plain
          @click="emit('refresh')"
          v-ripple
        >
          刷新
        </ElButton>
      </div>
    </div>

    <div class="plugin-view-strip">
      <button
        v-for="view in pluginViews"
        :key="view.key"
        type="button"
        class="plugin-view-pill"
        :class="{ 'plugin-view-pill--active': pluginViewModel === view.key }"
        @click="pluginViewModel = view.key"
      >
        <span class="plugin-view-pill__label">{{ view.label }}</span>
        <strong class="plugin-view-pill__value">{{ view.count }}</strong>
      </button>
    </div>

    <div class="plugin-catalog-layout">
      <aside class="plugin-payment-sidebar">
        <div class="plugin-payment-sidebar__head">
          <h4>支付方式</h4>
        </div>

        <div class="plugin-payment-sidebar__list">
          <button
            v-for="item in pluginPaymentFilters"
            :key="item.key"
            type="button"
            class="plugin-payment-filter"
            :class="{ 'plugin-payment-filter--active': paymentFilterModel === item.key }"
            @click="paymentFilterModel = item.key"
          >
            <span>{{ item.label }}</span>
            <strong>{{ item.count }}</strong>
          </button>
        </div>
      </aside>

      <div class="plugin-catalog-main">
        <div class="plugin-toolbar">
          <ElInput
            v-model="keywordModel"
            clearable
            class="keyword-input"
            placeholder="搜索插件名称或编码"
          >
            <template #prefix>
              <Icon icon="ri:search-line" />
            </template>
          </ElInput>

          <div class="plugin-toolbar__meta">
            <ElTag type="primary" effect="plain">{{ filteredPlugins.length }} 个插件</ElTag>
          </div>
        </div>

        <ArtTable
          rowKey="code"
          :loading="loading"
          :data="filteredPlugins"
          :columns="columns"
          :show-header="false"
          :stripe="false"
        />
      </div>
    </div>
  </ElCard>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import { Icon } from '@iconify/vue'
  import type { ColumnOption } from '@/types'
  import type { PluginPaymentFilterKey } from '@/views/payments/shared/paymentPluginDisplay'

  defineOptions({ name: 'PaymentPluginListWorkspace' })

  type PaymentPluginItem = Api.SystemManage.PaymentPluginItem
  type PluginListViewKey = 'all' | 'installed' | 'enabled' | 'attention'

  interface PluginViewItem {
    key: PluginListViewKey
    label: string
    count: number
  }

  interface PluginPaymentFilterItem {
    key: PluginPaymentFilterKey
    label: string
    count: number
  }

  interface Props {
    loading: boolean
    keyword: string
    activePluginView: PluginListViewKey
    activePluginPaymentFilter: PluginPaymentFilterKey
    pluginViews: PluginViewItem[]
    pluginPaymentFilters: PluginPaymentFilterItem[]
    filteredPlugins: PaymentPluginItem[]
    columns: ColumnOption[]
    hasPluginScaffoldAuth: boolean
    hasGovernanceData: boolean
    showGovernancePanels: boolean
    governanceAttentionCount: number
    governanceSnapshotCount: number
    governanceResidueCount: number
    governanceLedgerCount: number
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:keyword', value: string): void
    (e: 'update:activePluginView', value: PluginListViewKey): void
    (e: 'update:activePluginPaymentFilter', value: PluginPaymentFilterKey): void
    (e: 'scaffold'): void
    (e: 'toggle-governance'): void
    (e: 'refresh'): void
  }>()

  const keywordModel = computed({
    get: () => props.keyword,
    set: (value: string) => emit('update:keyword', value)
  })

  const pluginViewModel = computed({
    get: () => props.activePluginView,
    set: (value: PluginListViewKey) => emit('update:activePluginView', value)
  })

  const paymentFilterModel = computed({
    get: () => props.activePluginPaymentFilter,
    set: (value: PluginPaymentFilterKey) => emit('update:activePluginPaymentFilter', value)
  })
</script>

<style scoped lang="scss">
  .plugin-workspace-card {
    order: 1;
    display: block;
    flex: 0 0 auto;
    margin-top: 0;
    overflow: visible;
    --plugin-panel-border: rgb(226 232 240 / 0.92);
    --plugin-surface-bg: rgb(248 250 252 / 0.96);
    --plugin-surface-bg-strong:
      radial-gradient(circle at top right, rgb(59 130 246 / 0.08), transparent 34%),
      linear-gradient(180deg, rgb(248 250 252 / 0.96), rgb(255 255 255 / 1));
    --plugin-surface-pill-active-bg:
      radial-gradient(circle at top right, rgb(99 102 241 / 0.12), transparent 38%),
      linear-gradient(180deg, rgb(238 242 255 / 0.96), rgb(255 255 255 / 1));
    --plugin-title-color: #111827;
    --plugin-text-color: #475569;
    --plugin-muted-color: #64748b;
    --plugin-accent-color: #312e81;
  }

  :global(html.dark .plugin-workspace-card ){
    --plugin-panel-border: rgb(71 85 105 / 0.42);
    --plugin-surface-bg: rgb(15 23 42 / 0.9);
    --plugin-surface-bg-strong:
      radial-gradient(circle at top right, rgb(59 130 246 / 0.1), transparent 34%),
      linear-gradient(180deg, rgb(15 23 42 / 0.96), rgb(2 6 23 / 0.94));
    --plugin-surface-pill-active-bg:
      radial-gradient(circle at top right, rgb(96 165 250 / 0.16), transparent 38%),
      linear-gradient(180deg, rgb(30 41 59 / 0.96), rgb(15 23 42 / 0.94));
    --plugin-title-color: #e2e8f0;
    --plugin-text-color: #cbd5e1;
    --plugin-muted-color: #94a3b8;
    --plugin-accent-color: #93c5fd;
  }

  .plugin-workspace-card :deep(.el-card__body) {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 16px 16px 14px;
    overflow: visible;
  }

  .plugin-list-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }

  .plugin-list-head__title {
    margin: 0;
    color: var(--plugin-title-color);
    font-size: 18px;
    font-weight: 700;
    line-height: 1.2;
  }

  .plugin-list-head__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    justify-content: flex-end;
  }

  .plugin-list-refresh {
    min-height: var(--el-component-custom-height);
    padding: 0 14px;
    border-radius: 10px;
  }

  .plugin-view-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .plugin-view-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: var(--el-component-custom-height);
    padding: 0 12px;
    color: var(--plugin-text-color);
    background: var(--plugin-surface-bg);
    border: 1px solid var(--plugin-panel-border);
    border-radius: 999px;
    cursor: pointer;
    transition:
      border-color 0.2s ease,
      box-shadow 0.2s ease,
      transform 0.2s ease;
  }

  .plugin-view-pill:hover {
    border-color: rgb(99 102 241 / 0.32);
    box-shadow: 0 10px 24px rgb(99 102 241 / 0.08);
    transform: translateY(-1px);
  }

  .plugin-view-pill--active {
    color: var(--plugin-accent-color);
    border-color: rgb(99 102 241 / 0.55);
    background: var(--plugin-surface-pill-active-bg);
    box-shadow: 0 12px 28px rgb(99 102 241 / 0.1);
  }

  .plugin-view-pill__label {
    font-size: 11px;
    font-weight: 600;
    line-height: 1.2;
  }

  .plugin-view-pill__value {
    color: var(--plugin-title-color);
    font-size: 15px;
    font-weight: 700;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
  }

  .plugin-catalog-layout {
    display: grid;
    grid-template-columns: 208px minmax(0, 1fr);
    gap: 16px;
    align-items: flex-start;
  }

  .plugin-payment-sidebar {
    position: sticky;
    top: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 14px;
    max-height: calc(100vh - 220px);
    overflow: auto;
    border: 1px solid var(--plugin-panel-border);
    border-radius: 18px;
    background: var(--plugin-surface-bg-strong);
  }

  .plugin-payment-sidebar__head {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .plugin-payment-sidebar__head h4 {
    margin: 0;
    color: var(--plugin-title-color);
    font-size: 14px;
    font-weight: 700;
  }

  .plugin-payment-sidebar__list {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .plugin-payment-filter {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
    min-height: var(--el-component-custom-height);
    padding: 0 12px;
    color: var(--plugin-text-color);
    background: var(--plugin-surface-bg);
    border: 1px solid var(--plugin-panel-border);
    border-radius: 14px;
    cursor: pointer;
    transition:
      border-color 0.2s ease,
      box-shadow 0.2s ease,
      transform 0.2s ease;
  }

  .plugin-payment-filter span {
    font-size: 13px;
    font-weight: 600;
  }

  .plugin-payment-filter strong {
    color: var(--plugin-title-color);
    font-size: 15px;
    font-variant-numeric: tabular-nums;
  }

  .plugin-payment-filter:hover {
    border-color: rgb(99 102 241 / 0.34);
    box-shadow: 0 10px 24px rgb(99 102 241 / 0.08);
    transform: translateY(-1px);
  }

  .plugin-payment-filter--active {
    color: var(--plugin-accent-color);
    border-color: rgb(99 102 241 / 0.52);
    background: var(--plugin-surface-pill-active-bg);
  }

  .plugin-catalog-main {
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-width: 0;
  }

  .plugin-catalog-main :deep(.art-table),
  .plugin-catalog-main :deep(.el-table) {
    min-width: 0;
  }

  .plugin-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    justify-content: space-between;
  }

  .keyword-input {
    flex: 1 1 320px;
    width: auto;
    min-width: 240px;
    max-width: 480px;
  }

  .keyword-input :deep(.el-input__wrapper) {
    min-height: var(--el-component-custom-height);
    border-radius: 10px;
  }

  .plugin-toolbar__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    margin-left: auto;
  }

  .plugin-workspace-card :deep(.el-table__cell) {
    padding-top: 10px;
    padding-bottom: 10px;
  }

  .plugin-workspace-card :deep(.el-tag) {
    margin-right: 0;
  }

  .plugin-governance-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-top: 14px;
    padding: 14px 16px;
    border: 1px dashed var(--plugin-panel-border);
    border-radius: 16px;
    background: var(--plugin-surface-bg);
  }

  .plugin-governance-bar__copy {
    display: flex;
    flex: 1 1 360px;
    flex-direction: column;
    gap: 4px;
    min-width: min(100%, 320px);
  }

  .plugin-governance-bar__copy strong {
    color: var(--plugin-title-color);
    font-size: 14px;
  }

  .plugin-governance-bar__copy span {
    color: var(--plugin-muted-color);
    font-size: 13px;
    line-height: 1.6;
  }

  .plugin-governance-bar__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    justify-content: flex-end;
  }

  @media (width <= 991px) {
    .plugin-catalog-layout {
      grid-template-columns: 1fr;
    }

    .plugin-payment-sidebar__list {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .keyword-input {
      width: 100%;
    }
  }

  @media (min-width: 861px) and (max-width: 991px) {
    .plugin-catalog-layout {
      grid-template-columns: 198px minmax(0, 1fr);
    }

    .plugin-payment-sidebar__list {
      display: flex;
      flex-direction: column;
    }

    .plugin-toolbar {
      align-items: flex-start;
    }
  }

  @media (width <= 480px) {
    .plugin-payment-sidebar__list {
      grid-template-columns: 1fr;
    }
  }
</style>
