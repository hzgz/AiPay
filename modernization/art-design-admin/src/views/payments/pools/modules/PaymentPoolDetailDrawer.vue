<template>
  <ElDrawer
    v-model="drawerVisible"
    size="860px"
    destroy-on-close
    :title="activePool ? `${activePool.name_label} / #${activePool.id}` : '轮询池详情'"
  >
    <div v-loading="detailLoading" class="pool-detail">
      <template v-if="activePool">
        <section class="detail-hero">
          <div class="detail-hero-copy">
            <h3>{{ activePool.name_label }}</h3>
            <p>{{ activePool.merchant_display }}</p>
            <span>{{ displayPoolType(activePool) }} / {{ displayPoolRoundType(activePool) }}</span>
          </div>
          <div class="detail-hero-actions">
            <ElTag :type="tagType(activePool.status_type)" effect="light">
              {{ displayPoolStatus(activePool) }}
            </ElTag>
            <ElTag :type="tagType(activePool.pool_state_type)" effect="plain">
              {{ displayPoolState(activePool) }}
            </ElTag>
            <ElButton v-if="hasPoolEditAuth" plain :disabled="detailLoading" @click="emit('edit')">
              编辑基础配置
            </ElButton>
            <ElButton
              v-if="hasPoolEditAuth"
              plain
              :disabled="detailLoading"
              @click="emit('channels')"
            >
              维护通道分配
            </ElButton>
            <ElButton
              v-if="hasPoolStatusAuth"
              plain
              :type="activePool.status === 1 ? 'warning' : 'success'"
              :disabled="detailLoading"
              @click="emit('status')"
            >
              {{ activePool.status === 1 ? '停用轮询池' : '启用轮询池' }}
            </ElButton>
            <ElButton
              v-if="hasPoolDeleteAuth"
              type="danger"
              plain
              :disabled="detailLoading"
              @click="emit('delete')"
            >
              删除轮询池
            </ElButton>
          </div>
        </section>

        <section class="detail-section">
          <h4>基础信息</h4>
          <div class="detail-grid">
            <div class="detail-item">
              <span>轮询池编号</span>
              <strong>#{{ activePool.id }}</strong>
            </div>
            <div class="detail-item">
              <span>商户</span>
              <strong>{{ activePool.merchant_display }}</strong>
            </div>
            <div class="detail-item">
              <span>商户编号</span>
              <strong>{{ activePool.user_id || '--' }}</strong>
            </div>
            <div class="detail-item">
              <span>支付类型</span>
              <strong>{{ displayPoolType(activePool) }}</strong>
            </div>
            <div class="detail-item">
              <span>轮询方式</span>
              <strong>{{ displayPoolRoundType(activePool) }}</strong>
            </div>
            <div class="detail-item">
              <span>池状态</span>
              <strong>{{ displayPoolState(activePool) }}</strong>
            </div>
          </div>
        </section>

        <section class="detail-section">
          <h4>轮询游标</h4>
          <ElDescriptions :column="1" border>
            <ElDescriptionsItem label="启用状态">
              {{ displayPoolStatus(activePool) }}
            </ElDescriptionsItem>
            <ElDescriptionsItem label="轮询进度">
              {{ activePool.progress_label }}
            </ElDescriptionsItem>
            <ElDescriptionsItem label="当前索引 / 权重">
              {{ activePool.current_index }} / {{ activePool.current_weight }}
            </ElDescriptionsItem>
            <ElDescriptionsItem label="可用 / 不可用 / 缺失">
              {{ activePool.active_item_count }} / {{ activePool.disabled_item_count }} /
              {{ activePool.missing_item_count }}
            </ElDescriptionsItem>
            <ElDescriptionsItem label="最近选中">
              {{ activePool.last_account_label || '--' }}
            </ElDescriptionsItem>
            <ElDescriptionsItem label="最近配置时间">
              {{ activePool.latest_item_time || '--' }}
            </ElDescriptionsItem>
            <ElDescriptionsItem label="创建时间">
              {{ activePool.create_time || '--' }}
            </ElDescriptionsItem>
            <ElDescriptionsItem label="更新时间">
              {{ activePool.update_time || '--' }}
            </ElDescriptionsItem>
          </ElDescriptions>
        </section>

        <section class="detail-section">
          <h4>已选通道</h4>
          <template v-if="activePool.selected_items.length">
            <ElTable :data="activePool.selected_items" border class="channel-table">
              <ElTableColumn prop="sort_order" label="顺序" width="80" align="center" />
              <ElTableColumn label="通道" min-width="280">
                <template #default="{ row }">
                  <div class="channel-name">
                    <strong class="cell-title">{{ row.account_label }}</strong>
                    <p class="cell-sub">{{ row.channel_label }}</p>
                  </div>
                </template>
              </ElTableColumn>
              <ElTableColumn prop="weight" label="权重" width="90" align="center" />
              <ElTableColumn label="状态" min-width="170" align="center">
                <template #default="{ row }">
                  <div class="state-cell">
                    <ElTag :type="tagType(row.status_type)" effect="light">
                      {{ displayPoolChannelStatus(row) }}
                    </ElTag>
                    <ElTag v-if="row.is_last_selected" type="primary" effect="plain">
                      最近选中
                    </ElTag>
                  </div>
                </template>
              </ElTableColumn>
              <ElTableColumn prop="update_time" label="更新时间" min-width="160">
                <template #default="{ row }">
                  {{ row.update_time || '--' }}
                </template>
              </ElTableColumn>
            </ElTable>
          </template>
          <ElEmpty v-else description="该轮询池暂未配置通道" />
        </section>

        <p v-if="activePool.readonly_note" class="detail-note">
          {{ displayPoolReadonlyNote(activePool) }}
        </p>
      </template>
    </div>
  </ElDrawer>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'

  defineOptions({ name: 'PaymentPoolDetailDrawer' })

  type PoolChannelItem = Api.Payments.PoolChannelItem
  type PoolDetailItem = Api.Payments.PoolDetailItem

  interface Props {
    visible: boolean
    detailLoading: boolean
    activePool: PoolDetailItem | null
    hasPoolEditAuth: boolean
    hasPoolStatusAuth: boolean
    hasPoolDeleteAuth: boolean
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:visible', value: boolean): void
    (e: 'edit'): void
    (e: 'channels'): void
    (e: 'status'): void
    (e: 'delete'): void
  }>()

  const drawerVisible = computed({
    get: () => props.visible,
    set: (value: boolean) => emit('update:visible', value)
  })

  function displayPoolType(pool?: Partial<PoolDetailItem> | null, fallback = '--') {
    return displayAdminFixtureText(pool?.type_text || pool?.type_label || pool?.type, fallback)
  }

  function displayPoolRoundType(pool?: Partial<PoolDetailItem> | null, fallback = '--') {
    return displayAdminFixtureText(pool?.round_type_text || pool?.round_type_label, fallback)
  }

  function displayPoolStatus(pool?: Partial<PoolDetailItem> | null, fallback = '--') {
    return displayAdminFixtureText(pool?.status_text || pool?.status_label, fallback)
  }

  function displayPoolState(pool?: Partial<PoolDetailItem> | null, fallback = '--') {
    return displayAdminFixtureText(pool?.pool_state_text || pool?.pool_state_label, fallback)
  }

  function displayPoolReadonlyNote(pool?: Partial<PoolDetailItem> | null, fallback = '--') {
    return displayAdminFixtureText(pool?.readonly_note_text || pool?.readonly_note, fallback)
  }

  function displayPoolChannelStatus(row?: Partial<PoolChannelItem> | null, fallback = '--') {
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
  .pool-detail {
    min-height: 240px;
    --pool-detail-card-border: var(--el-border-color-lighter);
    --pool-detail-card-bg: rgb(248 250 252 / 0.82);
  }

  :global(html.dark .pool-detail ){
    --pool-detail-card-border: rgb(71 85 105 / 0.42);
    --pool-detail-card-bg: rgb(15 23 42 / 0.84);
  }

  .detail-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 4px 0 20px;
  }

  .detail-hero-copy {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .detail-hero-copy h3 {
    margin: 0;
    color: var(--el-text-color-primary);
    font-size: 22px;
    word-break: break-all;
  }

  .detail-hero-copy p,
  .detail-hero-copy span,
  .cell-sub,
  .detail-note {
    margin: 0;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 1.6;
    word-break: break-all;
  }

  .detail-hero-actions,
  .state-cell {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }

  .detail-hero-actions {
    justify-content: flex-end;
  }

  .detail-section {
    margin-bottom: 24px;
  }

  .detail-section h4 {
    margin: 0 0 12px;
    color: var(--el-text-color-primary);
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
    gap: 6px;
    padding: 14px 16px;
    border: 1px solid var(--pool-detail-card-border);
    border-radius: 14px;
    background: var(--pool-detail-card-bg);
  }

  .detail-item span {
    color: var(--el-text-color-secondary);
    font-size: 12px;
  }

  .detail-item strong,
  .cell-title {
    color: var(--el-text-color-primary);
    font-size: 14px;
    word-break: break-all;
  }

  .channel-name {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .channel-table {
    width: 100%;
  }

  @media (width <= 900px) {
    .detail-hero {
      flex-direction: column;
    }

    .detail-hero-actions {
      justify-content: flex-start;
    }

    .detail-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
