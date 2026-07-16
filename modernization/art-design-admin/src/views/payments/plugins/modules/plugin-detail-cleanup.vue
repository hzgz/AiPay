<template>
  <section class="drawer-section">
    <div class="cleanup-toolbar">
      <div class="detail-sub-switcher detail-sub-switcher--compact">
        <button
          v-for="panel in cleanupPanels"
          :key="panel.key"
          type="button"
          class="detail-sub-switcher__item"
          :class="{ 'detail-sub-switcher__item--active': activeCleanupPanel === panel.key }"
          @click="emit('changePanel', panel.key)"
        >
          <span class="detail-sub-switcher__label">{{ panel.label }}</span>
        </button>
      </div>
      <ElTag :type="activeCleanupPanel === 'safe' ? 'success' : 'danger'" effect="plain">
        {{ activeCleanupPanel === 'safe' ? '保留业务数据' : '破坏性操作' }}
      </ElTag>
    </div>

    <div v-if="activeCleanupPanel === 'safe'" class="plan-card safe">
      <p class="plan-summary-copy">仅清理插件私有资源。</p>
      <div class="plan-meta">
        <ElTag effect="plain">现存文件 {{ detail.uninstall_plan.summary.existing_file_count }}</ElTag>
        <ElTag effect="plain">
          现存数据表 {{ detail.uninstall_plan.summary.existing_table_count }}
        </ElTag>
        <ElTag
          :type="detail.uninstall_plan.summary.existing_managed_channel_count > 0 ? 'primary' : 'info'"
          effect="plain"
        >
          通道 {{ detail.uninstall_plan.summary.existing_managed_channel_count }}/{{
            detail.uninstall_plan.summary.managed_channel_count
          }}
        </ElTag>
        <ElTag type="warning" effect="plain">
          数据行 {{ detail.uninstall_plan.summary.table_row_count }}
        </ElTag>
      </div>

      <div class="plan-actions">
        <ElButton
          v-if="hasPluginCleanupSafeAuth && canRunSafeCleanup"
          type="primary"
          :loading="cleanupLoading"
          @click="emit('runSafeCleanup', detail.manifest.code)"
        >
          执行安全清理
        </ElButton>
        <ElTag v-else effect="plain" type="info">
          {{
            !hasPluginCleanupSafeAuth
              ? '当前账号没有安全清理权限。'
              : detail.state.installed || detail.state.enabled
                ? '请先卸载插件后再清理。'
                : '当前没有可执行安全清理的残留项。'
          }}
        </ElTag>
      </div>

      <div v-if="detail.state.last_cleanup_report" class="plan-result">
        <span>最近清理 {{ detail.state.last_cleanup_report.finished_at }}</span>
        <div class="audit-tags">
          <ElTag type="success" effect="plain">
            文件 {{ detail.state.last_cleanup_report.removed_file_count }}
          </ElTag>
          <ElTag type="warning" effect="plain">
            数据表 {{ detail.state.last_cleanup_report.removed_table_count }}
          </ElTag>
          <ElTag effect="plain">数据行 {{ detail.state.last_cleanup_report.removed_row_count }}</ElTag>
          <ElTag
            v-if="lastCleanupManagedChannelSummary.total > 0"
            :type="lastCleanupManagedChannelSummary.blocked > 0 ? 'warning' : 'primary'"
            effect="plain"
          >
            通道 {{ lastCleanupManagedChannelSummary.removed }}/{{
              lastCleanupManagedChannelSummary.total
            }}
          </ElTag>
          <ElTag
            v-if="detail.state.last_cleanup_report.plugin_hook.executed"
            type="info"
            effect="plain"
          >
            流程 {{ detail.state.last_cleanup_report.plugin_hook.steps.length }}
          </ElTag>
        </div>
      </div>

      <p v-if="detail.state.last_cleanup_report?.plugin_hook.summary" class="plan-hook-summary">
        {{ normalizePluginCopy(detail.state.last_cleanup_report.plugin_hook.summary) }}
      </p>

      <div class="plan-actions plan-detail-toolbar">
        <ElButton
          v-if="detail.uninstall_plan.file_audit.length > 0"
          plain
          @click="emit('toggleVisibility', 'safeFiles')"
        >
          {{
            cleanupVisibility.safeFiles
              ? '收起资源文件'
              : `资源文件（${detail.uninstall_plan.file_audit.length}）`
          }}
        </ElButton>
        <ElButton
          v-if="detail.uninstall_plan.table_audit.length > 0"
          plain
          @click="emit('toggleVisibility', 'safeTables')"
        >
          {{
            cleanupVisibility.safeTables
              ? '收起数据表'
              : `数据表（${detail.uninstall_plan.table_audit.length}）`
          }}
        </ElButton>
        <ElButton
          v-if="detail.uninstall_plan.managed_channel_audit.length > 0"
          plain
          @click="emit('toggleVisibility', 'safeChannels')"
        >
          {{
            cleanupVisibility.safeChannels
              ? '收起通道'
              : `通道（${detail.uninstall_plan.managed_channel_audit.length}）`
          }}
        </ElButton>
        <ElButton
          v-if="detail.uninstall_plan.notes.length > 0"
          plain
          @click="emit('toggleVisibility', 'safeNotes')"
        >
          {{
            cleanupVisibility.safeNotes ? '收起说明' : `说明（${detail.uninstall_plan.notes.length}）`
          }}
        </ElButton>
      </div>

      <ul v-if="cleanupVisibility.safeFiles" class="plan-list">
        <li v-for="file in detail.uninstall_plan.file_audit" :key="`safe-${file.target}`">
          <span>{{ resourceTargetLabel(file.target) }}</span>
          <div class="audit-tags">
            <ElTag :type="file.exists ? 'success' : 'info'" effect="plain">
              {{ file.exists ? '存在' : '缺失' }}
            </ElTag>
            <ElTag v-if="file.kind !== 'missing'" type="warning" effect="plain">
              {{ resourceKindLabel(file.kind) }}
            </ElTag>
            <ElTag v-if="file.entry_count !== null" effect="plain">条目 {{ file.entry_count }}</ElTag>
            <ElTag v-else-if="file.size_bytes !== null" effect="plain">
              大小 {{ formatBytes(file.size_bytes) }}
            </ElTag>
          </div>
        </li>
      </ul>

      <ul v-if="cleanupVisibility.safeTables" class="plan-list muted">
        <li v-for="table in detail.uninstall_plan.table_audit" :key="`safe-table-${table.table}`">
          <span>{{ tableTargetLabel(table.table) }}</span>
          <div class="audit-tags">
            <ElTag :type="table.exists ? 'success' : 'info'" effect="plain">
              {{ table.exists ? '存在' : '缺失' }}
            </ElTag>
            <ElTag v-if="table.row_count !== null" type="warning" effect="plain">
              数据行 {{ table.row_count }}
            </ElTag>
          </div>
        </li>
      </ul>

      <ul v-if="cleanupVisibility.safeChannels" class="plan-list muted">
        <li
          v-for="channel in detail.uninstall_plan.managed_channel_audit"
          :key="`safe-channel-${channel.code}`"
        >
          <span>{{ normalizePluginCopy(channel.code) }}</span>
          <div class="audit-tags">
            <ElTag :type="channel.exists ? 'success' : 'info'" effect="plain">
              {{ channel.exists ? '存在' : '缺失' }}
            </ElTag>
            <ElTag :type="channel.can_cleanup ? 'success' : 'warning'" effect="plain">
              {{ channel.can_cleanup ? '安全清理可执行' : '已阻塞' }}
            </ElTag>
            <ElTag v-if="managedChannelDriftCount(channel) > 0" type="warning" effect="plain">
              漂移 {{ managedChannelDriftCount(channel) }}
            </ElTag>
            <ElTag
              v-if="
                channel.dependency_summary.account_count > 0 ||
                channel.dependency_summary.order_count > 0
              "
              type="danger"
              effect="plain"
            >
              依赖 {{ channel.dependency_summary.account_count }}/{{
                channel.dependency_summary.order_count
              }}
            </ElTag>
          </div>
        </li>
      </ul>

      <ul v-if="cleanupVisibility.safeNotes" class="plan-list note-list">
        <li v-for="note in detail.uninstall_plan.notes" :key="`safe-note-${note}`">
          {{ normalizePluginCopy(note) }}
        </li>
      </ul>
    </div>

    <div v-else class="plan-card purge">
      <p class="plan-summary-copy">永久移除插件目录与相关残留。</p>
      <div class="plan-meta">
        <ElTag effect="plain">现存文件 {{ detail.purge_plan.summary.existing_file_count }}</ElTag>
        <ElTag effect="plain">现存数据表 {{ detail.purge_plan.summary.existing_table_count }}</ElTag>
        <ElTag
          :type="detail.purge_plan.summary.existing_managed_channel_count > 0 ? 'primary' : 'info'"
          effect="plain"
        >
          通道 {{ detail.purge_plan.summary.existing_managed_channel_count }}/{{
            detail.purge_plan.summary.managed_channel_count
          }}
        </ElTag>
        <ElTag type="warning" effect="plain">数据行 {{ detail.purge_plan.summary.table_row_count }}</ElTag>
        <ElTag
          :type="detail.purge_plan.snapshot_guard.has_snapshot ? 'success' : 'danger'"
          effect="plain"
        >
          快照 {{ detail.purge_plan.snapshot_guard.snapshot_total }}
        </ElTag>
      </div>

      <ElAlert type="error" :closable="false" show-icon title="彻底清理属于破坏性操作。" />
      <ElAlert
        v-if="detail.purge_plan.snapshot_guard.warning"
        type="warning"
        :closable="false"
        show-icon
        :title="normalizePluginCopy(detail.purge_plan.snapshot_guard.warning)"
      />

      <div class="purge-guard-card">
        <div class="purge-guard-copy">
          <strong>{{
            detail.purge_plan.snapshot_guard.has_snapshot ? '恢复快照已就绪' : '暂无恢复快照'
          }}</strong>
          <p v-if="detail.purge_plan.snapshot_guard.has_snapshot">
            最近恢复点：
            {{
              snapshotDisplayTitle(
                detail.purge_plan.snapshot_guard.latest_snapshot_label,
                detail.purge_plan.snapshot_guard.latest_snapshot_created_at,
                detail.purge_plan.snapshot_guard.latest_snapshot_id
              )
            }}
            <span>
              创建于 {{ detail.purge_plan.snapshot_guard.latest_snapshot_created_at || '--' }}
            </span>
          </p>
          <p v-else>
            建议在彻底清理前先创建恢复快照；如果仍需继续，则需要使用更严格的确认口令：
            {{ detail.purge_plan.snapshot_guard.missing_snapshot_confirmation_phrase }}.
          </p>
        </div>
        <ElButton
          v-if="!recoveryOnlyMode && hasPluginCreateSnapshotAuth"
          type="warning"
          plain
          :loading="snapshotCreating"
          @click="emit('captureSnapshot', detail.manifest.code)"
        >
          先创建快照
        </ElButton>
      </div>

      <div class="plan-actions">
        <ElButton
          v-if="hasPluginCleanupPurgeAuth && canRunPurgeCleanup"
          type="danger"
          :loading="purgeCleanupLoading"
          @click="emit('runPurgeCleanup', detail.manifest.code)"
        >
          执行彻底清理
        </ElButton>
        <ElTag v-else effect="plain" type="warning">
          {{ hasPluginCleanupPurgeAuth ? purgeActionHint : '当前账号没有彻底清理权限。' }}
        </ElTag>
      </div>

      <div class="plan-actions plan-detail-toolbar">
        <ElButton
          v-if="detail.purge_plan.file_audit.length > 0"
          plain
          @click="emit('toggleVisibility', 'purgeFiles')"
        >
          {{
            cleanupVisibility.purgeFiles
              ? '收起资源文件'
              : `资源文件（${detail.purge_plan.file_audit.length}）`
          }}
        </ElButton>
        <ElButton
          v-if="detail.purge_plan.table_audit.length > 0"
          plain
          @click="emit('toggleVisibility', 'purgeTables')"
        >
          {{
            cleanupVisibility.purgeTables
              ? '收起数据表'
              : `数据表（${detail.purge_plan.table_audit.length}）`
          }}
        </ElButton>
        <ElButton
          v-if="detail.purge_plan.managed_channel_audit.length > 0"
          plain
          @click="emit('toggleVisibility', 'purgeChannels')"
        >
          {{
            cleanupVisibility.purgeChannels
              ? '收起通道'
              : `通道（${detail.purge_plan.managed_channel_audit.length}）`
          }}
        </ElButton>
        <ElButton
          v-if="detail.purge_plan.notes.length > 0"
          plain
          @click="emit('toggleVisibility', 'purgeNotes')"
        >
          {{
            cleanupVisibility.purgeNotes
              ? '收起说明'
              : `说明（${detail.purge_plan.notes.length}）`
          }}
        </ElButton>
      </div>

      <ul v-if="cleanupVisibility.purgeFiles" class="plan-list">
        <li v-for="file in detail.purge_plan.file_audit" :key="`purge-${file.target}`">
          <span>{{ resourceTargetLabel(file.target) }}</span>
          <div class="audit-tags">
            <ElTag :type="file.exists ? 'success' : 'info'" effect="plain">
              {{ file.exists ? '存在' : '缺失' }}
            </ElTag>
            <ElTag v-if="file.kind !== 'missing'" type="warning" effect="plain">
              {{ resourceKindLabel(file.kind) }}
            </ElTag>
            <ElTag v-if="file.entry_count !== null" effect="plain">条目 {{ file.entry_count }}</ElTag>
            <ElTag v-else-if="file.size_bytes !== null" effect="plain">
              大小 {{ formatBytes(file.size_bytes) }}
            </ElTag>
          </div>
        </li>
      </ul>

      <ul v-if="cleanupVisibility.purgeTables" class="plan-list muted">
        <li v-for="table in detail.purge_plan.table_audit" :key="`purge-table-${table.table}`">
          <span>{{ tableTargetLabel(table.table) }}</span>
          <div class="audit-tags">
            <ElTag :type="table.exists ? 'success' : 'info'" effect="plain">
              {{ table.exists ? '存在' : '缺失' }}
            </ElTag>
            <ElTag v-if="table.row_count !== null" type="warning" effect="plain">
              数据行 {{ table.row_count }}
            </ElTag>
          </div>
        </li>
      </ul>

      <ul v-if="cleanupVisibility.purgeChannels" class="plan-list muted">
        <li
          v-for="channel in detail.purge_plan.managed_channel_audit"
          :key="`purge-channel-${channel.code}`"
        >
          <span>{{ normalizePluginCopy(channel.code) }}</span>
          <div class="audit-tags">
            <ElTag :type="channel.exists ? 'success' : 'info'" effect="plain">
              {{ channel.exists ? '存在' : '缺失' }}
            </ElTag>
            <ElTag :type="channel.can_cleanup ? 'success' : 'warning'" effect="plain">
              {{ channel.can_cleanup ? '彻底清理可执行' : '已阻塞' }}
            </ElTag>
            <ElTag v-if="managedChannelDriftCount(channel) > 0" type="warning" effect="plain">
              漂移 {{ managedChannelDriftCount(channel) }}
            </ElTag>
            <ElTag
              v-if="
                channel.dependency_summary.account_count > 0 ||
                channel.dependency_summary.order_count > 0
              "
              type="danger"
              effect="plain"
            >
              依赖 {{ channel.dependency_summary.account_count }}/{{
                channel.dependency_summary.order_count
              }}
            </ElTag>
          </div>
        </li>
      </ul>

      <ul v-if="cleanupVisibility.purgeNotes" class="plan-list note-list">
        <li v-for="note in detail.purge_plan.notes" :key="`purge-note-${note}`">
          {{ normalizePluginCopy(note) }}
        </li>
      </ul>
    </div>

    <div class="capability-list">
      <ElTag v-for="scope in detail.uninstall_plan.retain_scopes" :key="`retain-${scope}`" effect="plain" type="info">
        保留 {{ retainScopeLabel(scope) }}
      </ElTag>
    </div>

    <ElAlert
      type="warning"
      :closable="false"
      show-icon
      title="订单、充值记录、资金日志、结算数据、回调轨迹和后台日志默认保留。"
    />
  </section>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import {
    managedChannelCleanupSummary,
    managedChannelDriftCount,
    normalizePluginCopy,
    resourceKindLabel,
    resourceTargetLabel,
    retainScopeLabel,
    snapshotDisplayTitle,
    tableTargetLabel
  } from '@/views/payments/shared/paymentPluginDisplay'

  type PaymentPluginDetail = Api.SystemManage.PaymentPluginDetail

  type PluginCleanupPanelKey = 'safe' | 'purge'
  type CleanupVisibilityKey =
    | 'safeFiles'
    | 'safeTables'
    | 'safeChannels'
    | 'safeNotes'
    | 'purgeFiles'
    | 'purgeTables'
    | 'purgeChannels'
    | 'purgeNotes'

  interface CleanupVisibilityState {
    safeFiles: boolean
    safeTables: boolean
    safeChannels: boolean
    safeNotes: boolean
    purgeFiles: boolean
    purgeTables: boolean
    purgeChannels: boolean
    purgeNotes: boolean
  }

  interface Props {
    detail: PaymentPluginDetail
    cleanupPanels: Array<{ key: PluginCleanupPanelKey; label: string }>
    activeCleanupPanel: PluginCleanupPanelKey
    cleanupVisibility: CleanupVisibilityState
    cleanupLoading: boolean
    purgeCleanupLoading: boolean
    snapshotCreating: boolean
    recoveryOnlyMode: boolean
    hasPluginCreateSnapshotAuth: boolean
    hasPluginCleanupSafeAuth: boolean
    hasPluginCleanupPurgeAuth: boolean
    canRunSafeCleanup: boolean
    canRunPurgeCleanup: boolean
    purgeActionHint: string
    formatBytes: (value: number | null) => string
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'changePanel', panel: PluginCleanupPanelKey): void
    (e: 'toggleVisibility', key: CleanupVisibilityKey): void
    (e: 'runSafeCleanup', code: string): void
    (e: 'runPurgeCleanup', code: string): void
    (e: 'captureSnapshot', code: string): void
  }>()

  const lastCleanupManagedChannelSummary = computed(() =>
    managedChannelCleanupSummary(props.detail.state.last_cleanup_report?.items)
  )
</script>

<style scoped lang="scss">
  .drawer-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
  }

  .detail-sub-switcher {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 6px;
  }

  .detail-sub-switcher--compact {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .detail-sub-switcher__item {
    display: flex;
    flex-direction: column;
    gap: 2px;
    align-items: center;
    justify-content: center;
    text-align: center;
    min-height: var(--el-component-custom-height);
    padding: 6px 12px;
    border: 1px solid rgb(226 232 240 / 0.92);
    border-radius: 10px;
    background: linear-gradient(180deg, rgb(248 250 252 / 0.96), rgb(255 255 255 / 1));
    cursor: pointer;
    transition:
      border-color 0.2s ease,
      box-shadow 0.2s ease,
      transform 0.2s ease;
  }

  .detail-sub-switcher__item:hover {
    border-color: rgb(59 130 246 / 0.32);
    box-shadow: 0 6px 16px rgb(59 130 246 / 0.08);
    transform: translateY(-1px);
  }

  .detail-sub-switcher__item--active {
    border-color: rgb(59 130 246 / 0.48);
    background:
      radial-gradient(circle at top right, rgb(59 130 246 / 0.12), transparent 36%),
      linear-gradient(180deg, rgb(239 246 255 / 0.98), rgb(255 255 255 / 1));
    box-shadow: 0 8px 18px rgb(59 130 246 / 0.1);
  }

  .detail-sub-switcher__label {
    color: #111827;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.2;
  }

  .cleanup-toolbar {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .cleanup-toolbar :deep(.el-tag) {
    align-self: flex-start;
  }

  .plan-card {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 14px;
    border-radius: 16px;
    border: 1px solid var(--el-border-color-lighter);
  }

  .plan-card.safe {
    background: linear-gradient(180deg, rgb(240 253 244 / 0.9), rgb(255 255 255 / 1));
  }

  .plan-card.purge {
    background: linear-gradient(180deg, rgb(255 247 237 / 0.9), rgb(255 255 255 / 1));
  }

  .plan-summary-copy {
    margin: 0;
    color: #475569;
    line-height: 1.6;
  }

  .plan-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .plan-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }

  .plan-detail-toolbar {
    margin-top: -4px;
  }

  .plan-detail-toolbar :deep(.el-button) {
    min-height: var(--el-component-custom-height);
    padding: 0 14px;
    border-radius: 10px;
    font-size: 13px;
  }

  .purge-guard-card {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 14px;
    align-items: center;
    padding: 14px 16px;
    border: 1px solid rgb(245 158 11 / 0.24);
    border-radius: 14px;
    background:
      radial-gradient(circle at top right, rgb(251 191 36 / 0.18), transparent 34%),
      linear-gradient(180deg, rgb(255 251 235 / 0.92), rgb(255 255 255 / 1));
  }

  .purge-guard-copy {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .purge-guard-copy strong {
    color: #111827;
  }

  .purge-guard-copy p {
    margin: 0;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.7;
  }

  .purge-guard-copy span {
    color: #9ca3af;
    margin-left: 8px;
  }

  .plan-result {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
    padding: 12px 14px;
    border-radius: 12px;
    background: rgb(255 255 255 / 0.72);
    color: #4b5563;
    font-size: 13px;
  }

  .plan-hook-summary {
    margin: -4px 0 0;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.6;
  }

  .plan-list {
    margin: 0;
    padding-left: 18px;
    color: #374151;
    line-height: 1.7;
  }

  .plan-list li {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-start;
  }

  .plan-list.muted {
    color: #6b7280;
  }

  .note-list li {
    display: list-item;
  }

  .capability-list,
  .audit-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .audit-tags {
    gap: 6px;
    justify-content: flex-end;
  }

  @media (width <= 991px) {
    .detail-sub-switcher {
      grid-template-columns: 1fr;
    }

    .detail-sub-switcher--compact {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .plan-list li {
      flex-direction: column;
    }

    .audit-tags {
      justify-content: flex-start;
    }

    .plan-result,
    .purge-guard-card {
      flex-direction: column;
      align-items: flex-start;
    }
  }

  @media (width <= 360px) {
    .detail-sub-switcher--compact {
      grid-template-columns: 1fr;
    }
  }
</style>
