<template>
  <section class="drawer-section">
    <div class="section-heading section-heading--compact">
      <ElTag effect="plain" type="info">{{ activeSnapshots?.total || 0 }} 条</ElTag>
    </div>

    <ElAlert
      v-if="recoveryOnlyMode"
      type="warning"
      :closable="false"
      show-icon
      title="当前处于恢复模式，可直接从快照恢复插件。"
    />

    <div class="snapshot-toolbar">
      <div class="snapshot-toolbar-actions">
        <ElTag v-if="detail.state.enabled" type="warning" effect="plain">需先停用</ElTag>
        <ElButton
          v-if="!recoveryOnlyMode && hasPluginCreateSnapshotAuth"
          type="primary"
          plain
          :loading="snapshotCreating"
          @click="emit('captureSnapshot', detail.manifest.code)"
        >
          创建快照
        </ElButton>
      </div>
    </div>

    <ElSkeleton v-if="snapshotsLoading" :rows="4" animated />

    <ElAlert
      v-else-if="!activeSnapshots || activeSnapshots.items.length === 0"
      type="info"
      :closable="false"
      show-icon
      title="该插件暂时还没有恢复快照。"
    />

    <div v-else class="snapshot-list">
      <article
        v-for="snapshot in activeSnapshots.items"
        :key="snapshot.snapshot_id"
        class="snapshot-card"
      >
        <div class="snapshot-card-head">
          <div class="snapshot-card-title">
            <strong>{{
              snapshotDisplayTitle(snapshot.label, snapshot.created_at, snapshot.snapshot_id)
            }}</strong>
            <span>{{ snapshot.created_at || '--' }}</span>
          </div>
          <div class="audit-tags">
            <ElTag :type="statusTagType(snapshot.state_snapshot.status)" effect="plain">
              {{ statusLabel(snapshot.state_snapshot.status) }}
            </ElTag>
            <ElTag type="info" effect="plain">版本 {{ snapshot.manifest_version || '--' }}</ElTag>
            <ElTag effect="plain">大小 {{ formatBytes(snapshot.size_bytes) }}</ElTag>
          </div>
        </div>

        <div class="capability-list snapshot-summary-tags">
          <ElTag effect="plain">根目录 {{ snapshot.summary.file_root_count }}</ElTag>
          <ElTag type="success" effect="plain">
            文件 {{ snapshot.summary.archived_file_count }}
          </ElTag>
          <ElTag type="warning" effect="plain">数据表 {{ snapshot.summary.table_count }}</ElTag>
          <ElTag type="primary" effect="plain">
            通道 {{ snapshot.summary.existing_managed_channel_count }}/{{
              snapshot.summary.managed_channel_count
            }}
          </ElTag>
          <ElTag type="danger" effect="plain">记录 {{ snapshot.summary.row_count }}</ElTag>
          <ElTag type="info" effect="plain">操作人 {{ operatorLabel(snapshot.operator) }}</ElTag>
        </div>

        <div class="snapshot-card-actions">
          <span class="history-path">{{ snapshotPathLabel(snapshot.snapshot_path) }}</span>
          <div class="snapshot-card-action-buttons">
            <ElButton
              v-if="hasPluginRestoreSnapshotAuth"
              type="warning"
              plain
              :disabled="
                Boolean(detail.state.enabled) || snapshotDeletingId === snapshot.snapshot_id
              "
              :loading="snapshotRestoringId === snapshot.snapshot_id"
              @click="emit('restoreSnapshot', snapshot)"
            >
              恢复快照
            </ElButton>
            <ElButton
              v-if="hasPluginDeleteSnapshotAuth"
              type="danger"
              plain
              :disabled="snapshotRestoringId === snapshot.snapshot_id"
              :loading="snapshotDeletingId === snapshot.snapshot_id"
              @click="emit('deleteSnapshot', snapshot)"
            >
              删除快照
            </ElButton>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>

<script setup lang="ts">
  import {
    operatorLabel,
    snapshotDisplayTitle,
    snapshotPathLabel,
    statusLabel,
    statusTagType
  } from '@/views/payments/shared/paymentPluginDisplay'

  type PaymentPluginDetail = Api.SystemManage.PaymentPluginDetail
  type PaymentPluginSnapshotItem = Api.SystemManage.PaymentPluginSnapshotItem
  type PaymentPluginSnapshotList = Api.SystemManage.PaymentPluginSnapshotList

  interface Props {
    detail: PaymentPluginDetail
    activeSnapshots: PaymentPluginSnapshotList | null
    snapshotsLoading: boolean
    recoveryOnlyMode: boolean
    hasPluginCreateSnapshotAuth: boolean
    hasPluginRestoreSnapshotAuth: boolean
    hasPluginDeleteSnapshotAuth: boolean
    snapshotCreating: boolean
    snapshotRestoringId: string
    snapshotDeletingId: string
    formatBytes: (value: number | null) => string
  }

  defineProps<Props>()

  const emit = defineEmits<{
    (e: 'captureSnapshot', code: string): void
    (e: 'restoreSnapshot', snapshot: PaymentPluginSnapshotItem): void
    (e: 'deleteSnapshot', snapshot: PaymentPluginSnapshotItem): void
  }>()
</script>

<style scoped lang="scss">
  .drawer-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
  }

  .section-heading {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
    color: #111827;
    font-size: 15px;
    font-weight: 600;
  }

  .section-heading--compact {
    justify-content: flex-end;
    margin-top: -2px;
  }

  .history-path {
    margin: 0;
    color: #9ca3af;
    font-size: 12px;
    word-break: break-all;
  }

  .snapshot-toolbar,
  .snapshot-card,
  .snapshot-card-title {
    display: flex;
    flex-direction: column;
  }

  .snapshot-toolbar {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px 12px;
    align-items: center;
    padding: 12px 14px;
    border: 1px solid rgb(217 119 6 / 0.16);
    border-radius: 14px;
    background:
      radial-gradient(circle at top right, rgb(251 191 36 / 0.12), transparent 32%),
      linear-gradient(180deg, rgb(255 251 235 / 0.92), rgb(255 255 255 / 1));
  }

  .snapshot-toolbar-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    justify-content: flex-end;
  }

  .snapshot-toolbar-actions :deep(.el-button) {
    min-height: var(--el-component-custom-height);
    padding: 0 14px;
    border-radius: 10px;
  }

  .snapshot-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .snapshot-card {
    gap: 8px;
    padding: 14px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 14px;
    background: linear-gradient(180deg, rgb(255 255 255 / 1), rgb(255 247 237 / 0.86));
  }

  .snapshot-card-head,
  .snapshot-card-actions {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    align-items: flex-start;
  }

  .snapshot-card-action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
  }

  .snapshot-card-action-buttons :deep(.el-button) {
    min-height: var(--el-component-custom-height);
    padding: 0 14px;
    border-radius: 10px;
  }

  .snapshot-card-title {
    gap: 4px;
  }

  .snapshot-card-title strong {
    color: #111827;
  }

  .snapshot-card-title span {
    color: #6b7280;
    font-size: 12px;
    font-variant-numeric: tabular-nums;
  }

  .snapshot-summary-tags {
    align-items: flex-start;
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
    .audit-tags {
      justify-content: flex-start;
    }

    .snapshot-card-head,
    .snapshot-card-actions {
      flex-direction: column;
    }

    .snapshot-card-action-buttons {
      justify-content: flex-start;
    }
  }

  @media (width <= 480px) {
    .snapshot-toolbar {
      grid-template-columns: 1fr;
      align-items: flex-start;
    }

    .snapshot-toolbar-actions {
      justify-content: flex-start;
    }
  }
</style>
