<template>
  <ElCard class="vault-card recovery-card" shadow="never">
    <div class="vault-header">
      <div class="vault-copy">
        <p class="vault-eyebrow">回滚中心</p>
        <h3 class="vault-title">回滚快照</h3>
        <p class="vault-desc">即使本地插件包已不在当前目录中，回滚快照仍会保留在这里，方便后续回滚或重建。</p>
      </div>

      <div class="vault-summary-grid">
        <div class="vault-stat">
          <span>快照数</span>
          <strong>{{ recoveryVault?.summary.total_snapshots || 0 }}</strong>
        </div>
        <div class="vault-stat">
          <span>插件数量</span>
          <strong>{{ recoveryVault?.summary.plugin_count || 0 }}</strong>
        </div>
        <div class="vault-stat warning">
          <span>插件目录缺失</span>
          <strong>{{ recoveryVault?.summary.catalog_missing_count || 0 }}</strong>
        </div>
        <div class="vault-stat success">
          <span>可恢复插件</span>
          <strong>{{ recoveryVault?.summary.restore_ready_count || 0 }}</strong>
        </div>
      </div>
    </div>

    <ElAlert
      v-if="(recoveryVault?.summary.catalog_missing_count || 0) > 0"
      type="warning"
      :closable="false"
      show-icon
      title="部分快照对应的插件已不在本地目录中，可在这里回滚，重建插件目录和专属资源。"
    />

    <ElAlert
      v-else-if="!loading && (recoveryVault?.items.length || 0) === 0"
      type="info"
      :closable="false"
      show-icon
      title="当前还没有可用回滚快照，建议先创建。"
    />

    <ElTable
      v-else
      :data="recoveryVault?.items || []"
      class="vault-table"
      border
      table-layout="fixed"
      v-loading="loading"
    >
      <ElTableColumn label="插件" min-width="220">
        <template #default="{ row }">
          <div class="vault-plugin-cell">
            <strong>{{ normalizePluginCopy(row.plugin_name) }}</strong>
            <div class="vault-plugin-meta">
              <span>{{ pluginCodeSummary(row.plugin_code) }}</span>
              <span>{{ normalizePluginCopy(row.provider) }}</span>
            </div>
            <div class="capability-list">
              <ElTag :type="row.catalog_available ? 'success' : 'warning'" effect="plain">
                {{ row.catalog_available ? '插件目录可用' : '插件目录缺失' }}
              </ElTag>
              <ElTag :type="row.current_state.enabled ? 'danger' : 'info'" effect="plain">
                {{ row.current_state.enabled ? '回滚前请先停用' : '允许回滚' }}
              </ElTag>
            </div>
          </div>
        </template>
      </ElTableColumn>

      <ElTableColumn label="快照" min-width="240">
        <template #default="{ row }">
          <div class="vault-plugin-cell">
            <strong>{{ snapshotDisplayTitle(row.label, row.created_at, row.snapshot_id) }}</strong>
            <div class="vault-plugin-meta">
              <span>{{ row.created_at || '--' }}</span>
              <span>版本 {{ row.manifest_version || '--' }}</span>
            </div>
            <div class="capability-list">
              <ElTag effect="plain">大小 {{ formatBytes(row.size_bytes) }}</ElTag>
              <ElTag effect="plain" type="info">{{ snapshotPathLabel(row.snapshot_path) }}</ElTag>
            </div>
          </div>
        </template>
      </ElTableColumn>

      <ElTableColumn label="归档范围" min-width="220">
        <template #default="{ row }">
          <div class="capability-list vault-scope-tags">
            <ElTag effect="plain">根目录 {{ row.summary.file_root_count }}</ElTag>
            <ElTag type="success" effect="plain">文件 {{ row.summary.archived_file_count }}</ElTag>
            <ElTag type="warning" effect="plain">数据表 {{ row.summary.table_count }}</ElTag>
            <ElTag type="danger" effect="plain">记录 {{ row.summary.row_count }}</ElTag>
            <ElTag type="info" effect="plain">操作人 {{ operatorLabel(row.operator) }}</ElTag>
          </div>
        </template>
      </ElTableColumn>

      <ElTableColumn label="当前状态" min-width="220">
        <template #default="{ row }">
          <div class="vault-plugin-cell">
            <div class="capability-list">
              <ElTag :type="statusTagType(row.current_state.status)" effect="plain">
                {{ statusLabel(row.current_state.status) }}
              </ElTag>
              <ElTag :type="row.runtime_available ? 'success' : 'warning'" effect="plain">
                运行目录 {{ row.runtime_available ? '正常' : '缺失' }}
              </ElTag>
              <ElTag :type="row.history_available ? 'success' : 'info'" effect="plain">
                运行记录 {{ row.history_available ? '正常' : '缺失' }}
              </ElTag>
            </div>
            <div class="vault-plugin-meta">
              <span>更新时间 {{ row.current_state.updated_at || '--' }}</span>
              <span>最近操作 {{ historyActionLabel(row.current_state.last_action) }}</span>
            </div>
          </div>
        </template>
      </ElTableColumn>

      <ElTableColumn label="操作" width="220" align="right">
        <template #default="{ row }">
          <div class="vault-action-stack">
            <ElButton
              v-if="row.catalog_available"
              link
              type="primary"
              @click="emit('open-detail', row.plugin_code)"
            >
              打开插件
            </ElButton>
            <ElButton
              v-if="hasPluginRestoreSnapshotAuth"
              type="warning"
              plain
              :disabled="!row.restorable || snapshotDeletingId === row.snapshot_id"
              :loading="snapshotRestoringId === row.snapshot_id"
              @click="emit('restore-vault-snapshot', row)"
            >
              恢复
            </ElButton>
            <ElButton
              v-if="hasPluginDeleteSnapshotAuth"
              type="danger"
              plain
              :disabled="snapshotRestoringId === row.snapshot_id"
              :loading="snapshotDeletingId === row.snapshot_id"
              @click="emit('delete-vault-snapshot', row)"
            >
              删除快照
            </ElButton>
          </div>
        </template>
      </ElTableColumn>
    </ElTable>
  </ElCard>

  <ElCard class="vault-card residue-card" shadow="never">
    <div class="vault-header">
      <div class="vault-copy">
        <p class="vault-eyebrow">孤立项检查</p>
        <h3 class="vault-title">孤立插件项</h3>
        <p class="vault-desc">
          识别那些已经不在当前插件目录中，但运行目录、运行记录、数据表或托管通道记录仍然保留的插件项。
        </p>
      </div>

      <div class="vault-summary-grid">
        <div class="vault-stat">
          <span>孤立编码</span>
          <strong>{{ registryResidue?.summary.total_items || 0 }}</strong>
        </div>
        <div class="vault-stat">
          <span>运行目录</span>
          <strong>{{ registryResidue?.summary.runtime_residue_count || 0 }}</strong>
        </div>
        <div class="vault-stat warning">
          <span>运行记录</span>
          <strong>{{ registryResidue?.summary.history_residue_count || 0 }}</strong>
        </div>
        <div class="vault-stat">
          <span>托管通道</span>
          <strong>{{ registryResidue?.summary.managed_channel_residue_count || 0 }}</strong>
        </div>
        <div class="vault-stat warning">
          <span>清理受阻</span>
          <strong>{{ registryResidue?.summary.blocked_managed_channel_count || 0 }}</strong>
        </div>
        <div class="vault-stat success">
          <span>已有快照</span>
          <strong>{{ registryResidue?.summary.snapshot_backed_count || 0 }}</strong>
        </div>
      </div>
    </div>

    <ElAlert
      v-if="(registryResidue?.summary.total_items || 0) > 0"
      type="warning"
      :closable="false"
      show-icon
      title="这些插件编码已不在当前目录中。请先确认是否保留回滚快照、是否已解除托管通道阻塞，再执行清理。"
    />

    <ElAlert
      v-else
      type="success"
      :closable="false"
      show-icon
      title="当前没有发现需要处理的孤立插件项。"
    />

    <ElTable
      v-if="(registryResidue?.items.length || 0) > 0"
      :data="registryResidue?.items || []"
      class="vault-table residue-table"
      border
      table-layout="fixed"
      v-loading="loading"
    >
      <ElTableColumn label="插件编码" min-width="220">
        <template #default="{ row }">
          <div class="vault-plugin-cell">
            <strong>{{ normalizePluginCopy(row.plugin_code) }}</strong>
            <div class="vault-plugin-meta">
              <span>最近操作 {{ historyActionLabel(row.current_state.last_action) }}</span>
              <span>更新时间 {{ row.current_state.updated_at || '--' }}</span>
            </div>
            <div class="capability-list">
              <ElTag type="danger" effect="plain">目录缺失</ElTag>
              <ElTag :type="statusTagType(row.current_state.status)" effect="plain">
                {{ statusLabel(row.current_state.status) }}
              </ElTag>
            </div>
          </div>
        </template>
      </ElTableColumn>

      <ElTableColumn label="影响范围" min-width="280">
        <template #default="{ row }">
          <div class="vault-plugin-cell">
            <div class="capability-list vault-scope-tags">
              <ElTag :type="row.runtime_audit.exists ? 'warning' : 'info'" effect="plain">
                运行目录 {{ row.runtime_audit.exists ? '存在' : '已清空' }}
              </ElTag>
              <ElTag :type="row.history_audit.exists ? 'warning' : 'info'" effect="plain">
                运行记录 {{ row.history_audit.exists ? '存在' : '已清空' }}
              </ElTag>
              <ElTag :type="row.plugin_directory_audit.exists ? 'danger' : 'info'" effect="plain">
                插件目录 {{ row.plugin_directory_audit.exists ? '存在' : '已清空' }}
              </ElTag>
              <ElTag
                :type="row.summary.existing_table_count > 0 ? 'danger' : 'info'"
                effect="plain"
              >
                数据表 {{ row.summary.existing_table_count }}
              </ElTag>
              <ElTag :type="row.summary.table_row_count > 0 ? 'warning' : 'info'" effect="plain">
                记录 {{ row.summary.table_row_count }}
              </ElTag>
              <ElTag
                :type="row.summary.existing_managed_channel_count > 0 ? 'primary' : 'info'"
                effect="plain"
              >
                通道 {{ row.summary.existing_managed_channel_count }}
              </ElTag>
              <ElTag
                :type="row.summary.deletable_managed_channel_count > 0 ? 'success' : 'info'"
                effect="plain"
              >
                可清理 {{ row.summary.deletable_managed_channel_count }}
              </ElTag>
              <ElTag
                :type="row.summary.blocked_managed_channel_count > 0 ? 'danger' : 'info'"
                effect="plain"
              >
                阻塞 {{ row.summary.blocked_managed_channel_count }}
              </ElTag>
            </div>
          </div>
        </template>
      </ElTableColumn>

      <ElTableColumn label="快照保护" min-width="260">
        <template #default="{ row }">
          <div class="vault-plugin-cell">
            <div class="capability-list">
              <ElTag :type="row.snapshot_guard.has_snapshot ? 'success' : 'danger'" effect="plain">
                快照 {{ row.snapshot_guard.snapshot_total }}
              </ElTag>
            </div>
            <div class="vault-plugin-meta">
              <span>
                最新
                {{
                  snapshotDisplayTitle(
                    row.snapshot_guard.latest_snapshot_label,
                    row.snapshot_guard.latest_snapshot_created_at,
                    row.snapshot_guard.latest_snapshot_id
                  )
                }}
              </span>
              <span>{{ row.snapshot_guard.latest_snapshot_created_at || '--' }}</span>
            </div>
            <p v-if="row.summary.blocked_managed_channel_count > 0" class="residue-warning-copy">
              托管通道阻塞：{{ residueManagedChannelBlockSummary(row) }}
            </p>
            <p class="residue-warning-copy">{{
              normalizePluginCopy(row.snapshot_guard.warning)
            }}</p>
          </div>
        </template>
      </ElTableColumn>

      <ElTableColumn label="操作" width="220" align="right">
        <template #default="{ row }">
          <div class="vault-action-stack">
            <ElButton
              v-if="hasPluginCleanupResidueAuth"
              type="danger"
              plain
              :disabled="row.summary.blocked_managed_channel_count > 0"
              :loading="residueCleaningCode === row.plugin_code"
              @click="emit('cleanup-registry-residue', row)"
            >
              {{ residueCleanupActionLabel(row) }}
            </ElButton>
          </div>
        </template>
      </ElTableColumn>
    </ElTable>
  </ElCard>

  <ElCard class="vault-card residue-ledger-card" shadow="never">
    <div class="vault-header">
      <div class="vault-copy">
        <p class="vault-eyebrow">处理台账</p>
        <h3 class="vault-title">清理记录</h3>
        <p class="vault-desc">这里保留清理记录，方便回看目录、数据表和通道的处理结果。</p>
      </div>

      <div class="vault-summary-grid">
        <div class="vault-stat">
          <span>记录数</span>
          <strong>{{ registryResidueLedger?.summary.total_events || 0 }}</strong>
        </div>
        <div class="vault-stat warning">
          <span>无快照</span>
          <strong>{{ registryResidueLedger?.summary.without_snapshot_count || 0 }}</strong>
        </div>
        <div class="vault-stat success">
          <span>保留快照</span>
          <strong>{{ registryResidueLedger?.summary.snapshot_retained_count || 0 }}</strong>
        </div>
        <div class="vault-stat">
          <span>已删通道</span>
          <strong>{{ registryResidueLedger?.summary.removed_managed_channel_count || 0 }}</strong>
        </div>
        <div class="vault-stat">
          <span>已删数据表</span>
          <strong>{{ registryResidueLedger?.summary.removed_table_count || 0 }}</strong>
        </div>
      </div>
    </div>

    <ElAlert
      v-if="(registryResidueLedger?.summary.total_events || 0) > 0"
      type="info"
      :closable="false"
      show-icon
      :title="`当前展示最近 ${registryResidueLedger?.summary.visible_items || 0} 条清理记录。`"
    />

    <ElAlert v-else type="success" :closable="false" show-icon title="当前还没有清理记录。" />

    <ElTable
      v-if="(registryResidueLedger?.items.length || 0) > 0"
      :data="registryResidueLedger?.items || []"
      class="vault-table ledger-table"
      border
      table-layout="fixed"
      v-loading="loading"
    >
      <ElTableColumn label="事件" min-width="220">
        <template #default="{ row }">
          <div class="vault-plugin-cell">
            <strong>{{ normalizePluginCopy(row.plugin_code) }}</strong>
            <div class="vault-plugin-meta">
              <span>{{ row.created_at || '--' }}</span>
              <span>操作人 {{ operatorLabel(row.operator) }}</span>
            </div>
            <div class="capability-list">
              <ElTag :type="historyActionTagType(row.action)" effect="plain">
                {{ historyActionLabel(row.action, row.label) }}
              </ElTag>
              <ElTag :type="historyStatusTagType(row.status)" effect="light">
                {{ historyStatusLabel(row.status) }}
              </ElTag>
            </div>
          </div>
        </template>
      </ElTableColumn>

      <ElTableColumn label="保护模式" min-width="240">
        <template #default="{ row }">
          <div class="vault-plugin-cell">
            <div class="capability-list">
              <ElTag :type="residueLedgerGuardTagType(row)" effect="plain">
                {{ residueLedgerGuardLabel(row) }}
              </ElTag>
              <ElTag v-if="row.details?.snapshot_retained" type="success" effect="plain">
                保留 {{ row.details?.retained_snapshot_count || 0 }} 个快照
              </ElTag>
            </div>
            <div class="vault-plugin-meta">
              <span>
                最新快照
                {{
                  snapshotDisplayTitle(
                    row.details?.latest_snapshot_label,
                    row.details?.latest_snapshot_created_at,
                    row.details?.latest_snapshot_id
                  )
                }}
              </span>
              <span>{{ row.details?.latest_snapshot_created_at || '--' }}</span>
            </div>
          </div>
        </template>
      </ElTableColumn>

      <ElTableColumn label="清理范围" min-width="260">
        <template #default="{ row }">
          <div class="vault-plugin-cell">
            <div class="capability-list vault-scope-tags">
              <ElTag type="warning" effect="plain"
                >文件 {{ row.details?.removed_file_count || 0 }}</ElTag
              >
              <ElTag type="danger" effect="plain"
                >数据表 {{ row.details?.removed_table_count || 0 }}</ElTag
              >
              <ElTag
                :type="(row.details?.removed_managed_channel_count || 0) > 0 ? 'primary' : 'info'"
                effect="plain"
              >
                通道 {{ row.details?.removed_managed_channel_count || 0 }}
              </ElTag>
              <ElTag
                :type="(row.details?.removed_row_count || 0) > 0 ? 'warning' : 'info'"
                effect="plain"
              >
                记录 {{ row.details?.removed_row_count || 0 }}
              </ElTag>
              <ElTag :type="row.details?.runtime_exists ? 'warning' : 'info'" effect="plain">
                运行目录 {{ row.details?.runtime_exists ? '清理前存在' : '已为空' }}
              </ElTag>
              <ElTag :type="row.details?.history_exists ? 'warning' : 'info'" effect="plain">
                运行记录 {{ row.details?.history_exists ? '清理前存在' : '已为空' }}
              </ElTag>
              <ElTag
                :type="row.details?.plugin_directory_exists ? 'danger' : 'info'"
                effect="plain"
              >
                插件目录 {{ row.details?.plugin_directory_exists ? '清理前存在' : '已为空' }}
              </ElTag>
            </div>
          </div>
        </template>
      </ElTableColumn>

      <ElTableColumn label="结果" min-width="320">
        <template #default="{ row }">
          <div class="ledger-summary-stack">
            <p class="ledger-summary-copy">
              {{ normalizePluginCopy(row.summary) || '清理已完成。' }}
            </p>
            <div class="capability-list">
              <ElTag :type="row.details?.registry_removed ? 'success' : 'warning'" effect="plain">
                注册表 {{ row.details?.registry_removed ? '已删除' : '保留' }}
              </ElTag>
              <ElTag effect="plain" type="info">
                范围 {{ row.details?.existing_file_target_count || 0 }} 个文件目标 /
                {{ row.details?.existing_table_count || 0 }} 张数据表
              </ElTag>
              <ElTag effect="plain" type="info">
                清理前记录 {{ row.details?.table_row_count || 0 }}
              </ElTag>
            </div>
          </div>
        </template>
      </ElTableColumn>
    </ElTable>
  </ElCard>
</template>

<script setup lang="ts">
  import {
    formatBytes,
    historyActionLabel,
    historyActionTagType,
    historyStatusLabel,
    historyStatusTagType,
    normalizePluginCopy,
    operatorLabel,
    pluginCodeSummary,
    residueCleanupActionLabel,
    residueLedgerGuardLabel,
    residueLedgerGuardTagType,
    residueManagedChannelBlockSummary,
    snapshotDisplayTitle,
    snapshotPathLabel,
    statusLabel,
    statusTagType
  } from '@/views/payments/shared/paymentPluginDisplay'

  defineOptions({ name: 'PaymentPluginGovernancePanels' })

  type PaymentPluginRecoveryVaultItem = Api.SystemManage.PaymentPluginRecoveryVaultItem
  type PaymentPluginRecoveryVaultResponse = Api.SystemManage.PaymentPluginRecoveryVaultResponse
  type PaymentPluginRegistryResidueItem = Api.SystemManage.PaymentPluginRegistryResidueItem
  type PaymentPluginRegistryResidueResponse = Api.SystemManage.PaymentPluginRegistryResidueResponse
  type PaymentPluginRegistryResidueLedgerResponse =
    Api.SystemManage.PaymentPluginRegistryResidueLedgerResponse

  interface Props {
    loading: boolean
    recoveryVault: PaymentPluginRecoveryVaultResponse | null
    registryResidue: PaymentPluginRegistryResidueResponse | null
    registryResidueLedger: PaymentPluginRegistryResidueLedgerResponse | null
    hasPluginRestoreSnapshotAuth: boolean
    hasPluginDeleteSnapshotAuth: boolean
    hasPluginCleanupResidueAuth: boolean
    snapshotRestoringId: string
    snapshotDeletingId: string
    residueCleaningCode: string
  }

  defineProps<Props>()

  const emit = defineEmits<{
    (e: 'open-detail', code: string): void
    (e: 'restore-vault-snapshot', row: PaymentPluginRecoveryVaultItem): void
    (e: 'delete-vault-snapshot', row: PaymentPluginRecoveryVaultItem): void
    (e: 'cleanup-registry-residue', row: PaymentPluginRegistryResidueItem): void
  }>()
</script>

<style scoped lang="scss">
  .recovery-card {
    order: 2;
  }

  .residue-card {
    order: 3;
    border-color: rgb(220 38 38 / 0.16);
    background:
      radial-gradient(circle at top right, rgb(248 113 113 / 0.18), transparent 28%),
      radial-gradient(circle at left bottom, rgb(251 191 36 / 0.12), transparent 34%),
      linear-gradient(180deg, rgb(255 245 245 / 0.98), rgb(255 255 255 / 1));
  }

  .residue-ledger-card {
    order: 4;
    border-color: rgb(30 41 59 / 0.14);
    background:
      radial-gradient(circle at top right, rgb(59 130 246 / 0.12), transparent 28%),
      radial-gradient(circle at left bottom, rgb(15 23 42 / 0.08), transparent 34%),
      linear-gradient(180deg, rgb(248 250 252 / 0.98), rgb(255 255 255 / 1));
  }

  .vault-card {
    border: 1px solid rgb(30 58 95 / 0.12);
    background:
      radial-gradient(circle at top right, rgb(5 150 105 / 0.12), transparent 24%),
      radial-gradient(circle at left bottom, rgb(30 58 95 / 0.09), transparent 28%),
      linear-gradient(180deg, rgb(248 250 252 / 0.98), rgb(255 255 255 / 1));
  }

  .vault-card :deep(.el-card__body) {
    display: flex;
    flex-direction: column;
    gap: 18px;
  }

  .residue-card .vault-eyebrow {
    color: #b91c1c;
  }

  .residue-card .vault-title {
    color: #7f1d1d;
  }

  .residue-card .vault-desc {
    color: #7c2d12;
  }

  .residue-card .vault-stat {
    border-color: rgb(248 113 113 / 0.18);
    background: rgb(255 255 255 / 0.84);
  }

  .residue-card .vault-stat.warning {
    background: linear-gradient(180deg, rgb(255 237 213 / 0.96), rgb(255 255 255 / 1));
  }

  .residue-card .vault-stat.success {
    background: linear-gradient(180deg, rgb(254 242 242 / 0.96), rgb(255 255 255 / 1));
  }

  .residue-ledger-card .vault-eyebrow {
    color: #1d4ed8;
  }

  .vault-header {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(320px, 0.9fr);
    gap: 18px;
    align-items: stretch;
  }

  .vault-copy {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .vault-eyebrow {
    margin: 0;
    color: #1e3a5f;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
  }

  .vault-title {
    margin: 0;
    color: #0f172a;
    font-size: 26px;
    line-height: 1.1;
  }

  .vault-desc {
    margin: 0;
    color: #475569;
    line-height: 1.7;
  }

  .vault-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .vault-stat {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 8px;
    min-height: 96px;
    padding: 16px;
    border: 1px solid rgb(148 163 184 / 0.16);
    border-radius: 18px;
    background: rgb(255 255 255 / 0.82);
    box-shadow: 0 10px 30px rgb(15 23 42 / 0.04);
  }

  .vault-stat span {
    color: #64748b;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  .vault-stat strong {
    color: #0f172a;
    font-size: 30px;
    line-height: 1;
    font-variant-numeric: tabular-nums;
  }

  .vault-stat.warning {
    background: linear-gradient(180deg, rgb(255 247 237 / 0.96), rgb(255 255 255 / 1));
  }

  .vault-stat.success {
    background: linear-gradient(180deg, rgb(240 253 244 / 0.96), rgb(255 255 255 / 1));
  }

  .vault-table {
    width: 100%;
  }

  .residue-table :deep(.el-button) {
    min-height: var(--el-component-custom-height);
  }

  .residue-table :deep(.el-tag),
  .ledger-table :deep(.el-tag) {
    font-variant-numeric: tabular-nums;
  }

  .vault-plugin-cell,
  .vault-action-stack {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .vault-plugin-cell strong {
    color: #0f172a;
    font-size: 14px;
  }

  .vault-plugin-meta {
    display: flex;
    flex-direction: column;
    gap: 4px;
    color: #64748b;
    font-size: 12px;
    word-break: break-all;
  }

  .vault-scope-tags {
    align-items: flex-start;
  }

  .vault-action-stack {
    align-items: flex-end;
  }

  .residue-warning-copy {
    margin: 0;
    color: #b45309;
    font-size: 12px;
    line-height: 1.6;
  }

  .ledger-summary-stack {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .ledger-summary-copy {
    margin: 0;
    color: #334155;
    line-height: 1.7;
  }

  @media (width <= 991px) {
    .vault-header {
      grid-template-columns: 1fr;
    }

    .vault-summary-grid {
      grid-template-columns: 1fr 1fr;
    }

    .vault-action-stack {
      align-items: flex-start;
    }
  }
</style>
