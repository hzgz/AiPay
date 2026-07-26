<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <section class="drawer-section">
    <div class="section-heading section-heading--compact">
      <ElTag :type="statusTagType(detail.state.status)">
        {{ statusLabel(detail.state.status) }}
      </ElTag>
    </div>

    <div class="overview-grid">
      <div class="overview-item">
        <span>插件编码</span>
        <strong>{{ detail.manifest.code }}</strong>
      </div>
      <div class="overview-item">
        <span>版本</span>
        <strong>{{ detail.manifest.version }}</strong>
      </div>
      <div class="overview-item">
        <span>来源</span>
        <strong>{{ normalizePluginCopy(detail.manifest.provider) }}</strong>
      </div>
      <div class="overview-item">
        <span>支付方式</span>
        <div class="overview-item-tags">
          <ElTag
            v-for="label in pluginPaymentLabels(detail.manifest)"
            :key="label"
            :type="pluginPaymentTagType(label)"
          >
            {{ label }}
          </ElTag>
        </div>
      </div>
    </div>

    <p v-if="detail.manifest.description" class="overview-desc">
      {{ normalizePluginCopy(detail.manifest.description) }}
    </p>

    <div class="overview-action-bar">
      <div class="plan-actions">
        <ElButton
          v-if="canInstallDetail"
          type="primary"
          :loading="lifecycleActionLoading === 'install'"
          @click="emit('install', detail.manifest.code)"
        >
          安装插件
        </ElButton>
        <ElButton
          v-if="canEnableDetail"
          type="success"
          :loading="lifecycleActionLoading === 'enable'"
          @click="emit('enable', detail.manifest.code)"
        >
          启用插件
        </ElButton>
        <ElButton
          v-if="canDisableDetail"
          type="warning"
          :loading="lifecycleActionLoading === 'disable'"
          @click="emit('disable', detail.manifest.code)"
        >
          停用插件
        </ElButton>
        <ElButton
          v-if="canUninstallDetail"
          type="danger"
          plain
          :loading="lifecycleActionLoading === 'uninstall'"
          @click="emit('uninstall', detail.manifest.code)"
        >
          卸载插件
        </ElButton>
        <ElButton
          plain
          :loading="bundleExporting"
          @click="emit('downloadBundle', detail.manifest.code)"
        >
          <Icon icon="ri:download-2-line" />
          <span>导出插件包</span>
        </ElButton>
      </div>
    </div>

    <div class="overview-grid lifecycle-grid">
      <div class="overview-item">
        <span>安装时间</span>
        <strong>{{ detail.state.installed_at || '--' }}</strong>
      </div>
      <div class="overview-item">
        <span>更新时间</span>
        <strong>{{ detail.state.updated_at || '--' }}</strong>
      </div>
      <div class="overview-item">
        <span>最近操作</span>
        <strong>{{ historyActionLabel(detail.state.last_action) }}</strong>
      </div>
      <div class="overview-item">
        <span>执行结果</span>
        <strong>{{ executionStateLabel(detail.state.hook_execution) }}</strong>
      </div>
    </div>

    <div class="overview-status-grid">
      <article
        v-for="card in pluginOverviewCards"
        :key="card.key"
        class="overview-status-card"
        :class="`overview-status-card--${card.tone}`"
      >
        <span class="overview-status-card__label">{{ card.label }}</span>
        <strong>{{ card.value }}</strong>
      </article>
    </div>

    <div v-if="detail.state_audit.issues.length > 0" class="capability-list overview-event-tags">
      <ElTag
        v-for="issue in detail.state_audit.issues"
        :key="issue"
        :type="overviewAuditTone(detail)"
        effect="plain"
      >
        {{ normalizePluginCopy(issue) }}
      </ElTag>
    </div>

    <div v-if="canUpgradeDetail" class="plan-actions maintenance-actions">
      <ElButton type="primary" plain @click="emit('upgrade', detail.manifest.code)">
        执行升级
      </ElButton>
      <span class="maintenance-reason">
        {{ normalizePluginCopy(detail.state_audit.upgrade_reason) }}
      </span>
    </div>

    <div v-if="canRepairDetail" class="plan-actions maintenance-actions">
      <ElButton type="warning" @click="emit('repair', detail.manifest.code)">执行修复</ElButton>
      <span class="maintenance-reason">
        {{ normalizePluginCopy(detail.state_audit.repair_reason) }}
      </span>
    </div>
  </section>
</template>

<script setup lang="ts">
  import { Icon } from '@iconify/vue'
  import {
    executionStateLabel,
    historyActionLabel,
    normalizePluginCopy,
    overviewAuditTone,
    pluginPaymentLabels,
    pluginPaymentTagType,
    statusLabel,
    statusTagType,
    type PluginOverviewCardTone
  } from '@/views/payments/shared/paymentPluginDisplay'

  type PaymentPluginDetail = Api.SystemManage.PaymentPluginDetail

  interface OverviewCardItem {
    key: string
    label: string
    value: string
    tone: PluginOverviewCardTone
  }

  interface Props {
    detail: PaymentPluginDetail
    pluginOverviewCards: OverviewCardItem[]
    lifecycleActionLoading: 'install' | 'enable' | 'disable' | 'uninstall' | ''
    bundleExporting: boolean
    canInstallDetail: boolean
    canEnableDetail: boolean
    canDisableDetail: boolean
    canUninstallDetail: boolean
    canUpgradeDetail: boolean
    canRepairDetail: boolean
  }

  const { detail } = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'install', code: string): void
    (e: 'enable', code: string): void
    (e: 'disable', code: string): void
    (e: 'uninstall', code: string): void
    (e: 'upgrade', code: string): void
    (e: 'repair', code: string): void
    (e: 'downloadBundle', code: string): void
  }>()
</script>

<style scoped lang="scss">
  .drawer-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
    --plugin-card-border: var(--el-border-color-lighter);
    --plugin-surface-bg: linear-gradient(180deg, rgb(255 255 255 / 1), rgb(249 250 251 / 1));
    --plugin-surface-bg-soft: linear-gradient(180deg, rgb(248 250 252 / 0.92), rgb(255 255 255 / 1));
    --plugin-surface-bg-success:
      radial-gradient(circle at top right, rgb(34 197 94 / 0.12), transparent 32%),
      linear-gradient(180deg, rgb(240 253 244 / 0.94), rgb(255 255 255 / 1));
    --plugin-surface-bg-warning:
      radial-gradient(circle at top right, rgb(245 158 11 / 0.14), transparent 32%),
      linear-gradient(180deg, rgb(255 251 235 / 0.94), rgb(255 255 255 / 1));
    --plugin-surface-bg-danger:
      radial-gradient(circle at top right, rgb(248 113 113 / 0.14), transparent 32%),
      linear-gradient(180deg, rgb(254 242 242 / 0.94), rgb(255 255 255 / 1));
    --plugin-title-color: #111827;
    --plugin-text-color: #4b5563;
    --plugin-muted-color: #64748b;
  }

  :global(html.dark .drawer-section ){
    --plugin-card-border: rgb(71 85 105 / 0.42);
    --plugin-surface-bg: linear-gradient(180deg, rgb(15 23 42 / 0.96), rgb(2 6 23 / 0.94));
    --plugin-surface-bg-soft: linear-gradient(180deg, rgb(15 23 42 / 0.94), rgb(2 6 23 / 0.92));
    --plugin-surface-bg-success:
      radial-gradient(circle at top right, rgb(34 197 94 / 0.1), transparent 32%),
      linear-gradient(180deg, rgb(15 23 42 / 0.96), rgb(2 6 23 / 0.94));
    --plugin-surface-bg-warning:
      radial-gradient(circle at top right, rgb(245 158 11 / 0.12), transparent 32%),
      linear-gradient(180deg, rgb(30 41 59 / 0.96), rgb(15 23 42 / 0.94));
    --plugin-surface-bg-danger:
      radial-gradient(circle at top right, rgb(248 113 113 / 0.12), transparent 32%),
      linear-gradient(180deg, rgb(30 41 59 / 0.96), rgb(15 23 42 / 0.94));
    --plugin-title-color: #e2e8f0;
    --plugin-text-color: #cbd5e1;
    --plugin-muted-color: #94a3b8;
  }

  .section-heading {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
    color: var(--plugin-title-color);
    font-size: 15px;
    font-weight: 600;
  }

  .section-heading--compact {
    justify-content: flex-end;
    margin-top: -2px;
  }

  .overview-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .overview-status-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .overview-item {
    padding: 12px 14px;
    border: 1px solid var(--plugin-card-border);
    border-radius: 12px;
    background: var(--plugin-surface-bg);
  }

  .overview-item span {
    display: block;
    margin-bottom: 4px;
    color: var(--plugin-muted-color);
    font-size: 12px;
  }

  .overview-item strong {
    color: var(--plugin-title-color);
    font-size: 14px;
    word-break: break-all;
  }

  .overview-item-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
  }

  .overview-desc {
    margin: 0;
    color: var(--plugin-text-color);
    line-height: 1.7;
  }

  .overview-action-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    border: 1px solid var(--plugin-card-border);
    border-radius: 12px;
    background: var(--plugin-surface-bg-soft);
  }

  .overview-action-bar .plan-actions {
    gap: 8px;
  }

  .overview-action-bar :deep(.el-button) {
    min-height: var(--el-component-custom-height);
    padding: 0 14px;
    border-radius: 10px;
  }

  .overview-status-card {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 78px;
    padding: 12px 14px;
    border: 1px solid var(--plugin-card-border);
    border-radius: 16px;
    background: var(--plugin-surface-bg-soft);
  }

  .overview-status-card__label {
    color: var(--plugin-muted-color);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  .overview-status-card strong {
    color: var(--plugin-title-color);
    font-size: 16px;
    line-height: 1.25;
  }

  .overview-status-card--success {
    background: var(--plugin-surface-bg-success);
  }

  .overview-status-card--warning {
    background: var(--plugin-surface-bg-warning);
  }

  .overview-status-card--danger {
    background: var(--plugin-surface-bg-danger);
  }

  .overview-event-tags {
    margin-top: -2px;
  }

  .lifecycle-grid {
    margin-top: 4px;
  }

  .capability-list,
  .plan-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }

  .maintenance-actions {
    margin-top: -4px;
  }

  .maintenance-reason {
    color: #fdba74;
    font-size: 13px;
    line-height: 1.6;
  }

  @media (width <= 991px) {
    .overview-action-bar {
      flex-direction: column;
      align-items: flex-start;
    }
  }

  @media (width <= 420px) {
    .overview-grid,
    .overview-status-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
