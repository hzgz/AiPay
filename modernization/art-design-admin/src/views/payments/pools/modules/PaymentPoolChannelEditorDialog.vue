<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <ElDialog
    v-model="dialogVisible"
    width="1080px"
    destroy-on-close
    align-center
    title="维护通道分配"
  >
    <div v-loading="loading" class="channel-editor-body">
      <template v-if="activePool && channelEditor">
        <ElSpace wrap class="channel-editor-summary">
          <ElTag effect="plain">可选账号 {{ channelEditor.summary.available_count }}</ElTag>
          <ElTag type="primary" effect="plain"
            >已选 {{ channelEditor.summary.selected_count }}</ElTag
          >
          <ElTag
            v-if="channelEditor.summary.missing_selected_count > 0"
            type="danger"
            effect="plain"
          >
            失效条目 {{ channelEditor.summary.missing_selected_count }}
          </ElTag>
          <ElTag effect="plain">总权重 {{ selectedTotalWeight }}</ElTag>
        </ElSpace>

        <div v-if="channelEditor.warnings.length" class="warning-chip-list">
          <ElTag
            v-for="warning in channelEditor.warnings"
            :key="warning"
            class="warning-chip"
            type="warning"
            effect="plain"
          >
            {{ warning }}
          </ElTag>
        </div>

        <section v-if="missingSelectedAccounts.length" class="detail-section">
          <h4>失效条目</h4>
          <ElTable :data="missingSelectedAccounts" border class="missing-channel-table">
            <ElTableColumn prop="sort_order" label="顺序" width="80" align="center" />
            <ElTableColumn prop="account_label" label="条目" min-width="260" />
            <ElTableColumn prop="channel_label" label="原通道标识" min-width="160" />
            <ElTableColumn prop="weight" label="权重" width="90" align="center" />
            <ElTableColumn label="状态" width="100" align="center">
              <template #default="{ row }">
                <ElTag :type="tagType(row.status_type)" effect="light">
                  {{ displayPoolChannelStatus(row) }}
                </ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn prop="update_time" label="最后记录时间" min-width="160" />
          </ElTable>
        </section>

        <section class="detail-section">
          <h4>可选收款账号</h4>
          <ElTable :data="channelEditorRows" border class="channel-editor-table">
            <ElTableColumn label="选中" width="100" align="center">
              <template #default="{ row }">
                <ElSwitch
                  :model-value="row.selected"
                  inline-prompt
                  active-text="是"
                  inactive-text="否"
                  @update:model-value="(value) => emit('toggle:row', row, value)"
                />
              </template>
            </ElTableColumn>
            <ElTableColumn label="排序" width="130" align="center">
              <template #default="{ row }">
                <div v-if="row.selected" class="sort-cell">
                  <span class="sort-index">{{ row.sort_order }}</span>
                  <ElSpace :size="4">
                    <ElButton
                      size="small"
                      text
                      :disabled="!canMoveChannelUp(row)"
                      @click="emit('move:row', row, -1)"
                    >
                      上移
                    </ElButton>
                    <ElButton
                      size="small"
                      text
                      :disabled="!canMoveChannelDown(row)"
                      @click="emit('move:row', row, 1)"
                    >
                      下移
                    </ElButton>
                  </ElSpace>
                </div>
                <span v-else>--</span>
              </template>
            </ElTableColumn>
            <ElTableColumn label="账号 / 通道" min-width="300">
              <template #default="{ row }">
                <div class="channel-name">
                  <strong class="cell-title">{{ row.account_label }}</strong>
                  <p class="cell-sub">{{ row.channel_label }}</p>
                </div>
              </template>
            </ElTableColumn>
            <ElTableColumn label="状态" min-width="180" align="center">
              <template #default="{ row }">
                <div class="state-cell">
                  <ElTag :type="tagType(row.status_type)" effect="light">
                    {{ row.status_label }}
                  </ElTag>
                  <ElTag v-if="row.status === 1 && row.is_status !== 1" effect="plain">
                    收款关闭
                  </ElTag>
                </div>
              </template>
            </ElTableColumn>
            <ElTableColumn label="权重" width="150" align="center">
              <template #default="{ row }">
                <ElInputNumber
                  v-if="row.selected"
                  :model-value="row.weight"
                  :min="1"
                  :max="9999"
                  controls-position="right"
                  @update:model-value="(value) => emit('update:weight', row, value)"
                />
                <span v-else>--</span>
              </template>
            </ElTableColumn>
            <ElTableColumn prop="update_time" label="更新时间" min-width="160">
              <template #default="{ row }">
                {{ row.update_time || '--' }}
              </template>
            </ElTableColumn>
          </ElTable>
        </section>
      </template>
    </div>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="dialogVisible = false">取消</ElButton>
        <ElButton
          v-if="hasPoolEditAuth"
          type="primary"
          :loading="savingChannels"
          @click="emit('submit')"
        >
          保存通道分配
        </ElButton>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'

  defineOptions({ name: 'PaymentPoolChannelEditorDialog' })

  type PoolChannelEditor = Api.Payments.PoolChannelEditor
  type PoolChannelEditorAccount = Api.Payments.PoolChannelEditorAccount
  type PoolDetailItem = Api.Payments.PoolDetailItem
  type PoolMissingChannelItem = Api.Payments.PoolMissingChannelItem

  interface Props {
    visible: boolean
    loading: boolean
    savingChannels: boolean
    hasPoolEditAuth: boolean
    activePool: PoolDetailItem | null
    channelEditor: PoolChannelEditor | null
    channelEditorRows: PoolChannelEditorAccount[]
    missingSelectedAccounts: PoolMissingChannelItem[]
    selectedTotalWeight: number
    selectedCount: number
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:visible', value: boolean): void
    (e: 'toggle:row', row: PoolChannelEditorAccount, value: string | number | boolean): void
    (e: 'move:row', row: PoolChannelEditorAccount, direction: -1 | 1): void
    (
      e: 'update:weight',
      row: PoolChannelEditorAccount,
      value: string | number | null | undefined
    ): void
    (e: 'submit'): void
  }>()

  const dialogVisible = computed({
    get: () => props.visible,
    set: (value: boolean) => emit('update:visible', value)
  })

  function canMoveChannelUp(row: PoolChannelEditorAccount) {
    return row.selected && Number(row.sort_order || 0) > 1
  }

  function canMoveChannelDown(row: PoolChannelEditorAccount) {
    return row.selected && Number(row.sort_order || 0) < props.selectedCount
  }

  function displayPoolChannelStatus(
    row?: Partial<PoolMissingChannelItem | PoolChannelEditorAccount> | null,
    fallback = '--'
  ) {
    return displayAdminFixtureText(row?.status_text || row?.status_label, fallback)
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

    return 'info'
  }
</script>

<style scoped lang="scss">
  .channel-editor-body {
    min-height: 260px;
  }

  .channel-editor-summary {
    margin-bottom: 16px;
  }

  .warning-chip-list,
  .state-cell {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .warning-chip {
    max-width: 100%;
  }

  .detail-section {
    margin-bottom: 24px;
  }

  .detail-section h4 {
    margin: 0 0 12px;
    color: var(--el-text-color-primary);
    font-size: 15px;
  }

  .channel-name {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .cell-title {
    color: var(--el-text-color-primary);
    font-size: 14px;
    word-break: break-all;
  }

  .cell-sub,
  .sort-index {
    margin: 0;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 1.6;
    word-break: break-all;
  }

  .sort-cell {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
  }

  .channel-editor-table,
  .missing-channel-table {
    width: 100%;
  }

  .dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
  }
</style>
