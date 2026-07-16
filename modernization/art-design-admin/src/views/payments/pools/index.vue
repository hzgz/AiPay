<template>
  <div class="payment-pool-page art-full-height">
    <ArtSearchBar
      v-model="searchForm"
      :items="searchItems"
      :showExpand="false"
      @search="handleSearch"
      @reset="handleReset"
    />

    <ElCard class="art-table-card" shadow="never">
      <ArtTableHeader v-model:columns="columnChecks" :loading="loading" @refresh="getPoolList">
        <template #left>
          <ElSpace wrap>
            <ElTag effect="plain">轮询池 {{ pagination.total }}</ElTag>
            <ElTag type="primary" effect="plain">商户 {{ summary.merchant_count }}</ElTag>
            <ElTag type="success" effect="plain">启用 {{ summary.enabled_count }}</ElTag>
            <ElTag type="warning" effect="plain">停用 {{ summary.disabled_count }}</ElTag>
            <ElTag effect="plain">已配置 {{ summary.configured_pool_count }}</ElTag>
            <ElTag type="info" effect="plain">空池 {{ summary.empty_pool_count }}</ElTag>
            <ElTag type="success" effect="plain">全部可用 {{ summary.healthy_pool_count }}</ElTag>
            <ElTag effect="plain">通道 {{ summary.configured_channel_count }}</ElTag>
            <ElButton v-if="hasPoolCreateAuth" type="primary" @click="openCreateDialog">
              新建轮询池
            </ElButton>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        :loading="loading"
        :data="poolList"
        :columns="columns"
        :pagination="pagination"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <PaymentPoolDetailDrawer
      :visible="detailVisible"
      :detail-loading="detailLoading"
      :active-pool="activePool"
      :has-pool-edit-auth="hasPoolEditAuth"
      :has-pool-status-auth="hasPoolStatusAuth"
      :has-pool-delete-auth="hasPoolDeleteAuth"
      @update:visible="detailVisible = $event"
      @edit="openEditDialog"
      @channels="openChannelDialog"
      @status="openStatusDialog"
      @delete="handleDeletePool"
    />

    <PaymentPoolMaintenanceDialogs
      :create-visible="createVisible"
      :edit-visible="editVisible"
      :status-visible="statusVisible"
      :saving-create="savingCreate"
      :saving-edit="savingEdit"
      :saving-status="savingStatus"
      :has-pool-create-auth="hasPoolCreateAuth"
      :has-pool-edit-auth="hasPoolEditAuth"
      :has-pool-status-auth="hasPoolStatusAuth"
      :active-pool="activePool"
      :create-form="createForm"
      :edit-form="editForm"
      :status-form="statusForm"
      :payment-type-options="paymentTypeOptions"
      @update:create-visible="createVisible = $event"
      @update:edit-visible="editVisible = $event"
      @update:status-visible="statusVisible = $event"
      @update:create-form="syncCreateFormState"
      @update:edit-form="syncEditFormState"
      @update:status-form="syncStatusFormState"
      @submit:create="submitCreatePool"
      @submit:edit="submitEdit"
      @submit:status="submitStatus"
    />

    <PaymentPoolChannelEditorDialog
      :visible="channelEditorVisible"
      :loading="channelEditorLoading"
      :saving-channels="savingChannels"
      :has-pool-edit-auth="hasPoolEditAuth"
      :active-pool="activePool"
      :channel-editor="channelEditor"
      :channel-editor-rows="channelEditorRows"
      :missing-selected-accounts="missingSelectedAccounts"
      :selected-total-weight="selectedTotalWeight"
      :selected-count="selectedChannelRows.length"
      @update:visible="channelEditorVisible = $event"
      @toggle:row="handleChannelSelectedChange"
      @move:row="moveChannelRow"
      @update:weight="updateChannelWeight"
      @submit="submitChannelEditor"
    />
  </div>
</template>

<script setup lang="ts">
  import { ElButton, ElMessage, ElMessageBox, ElTag } from 'element-plus'
  import { useAuth } from '@/hooks'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'
  import PaymentPoolChannelEditorDialog from './modules/payment-pool-channel-editor-dialog.vue'
  import PaymentPoolDetailDrawer from './modules/payment-pool-detail-drawer.vue'
  import {
    assignPaymentPoolCreateFormState,
    assignPaymentPoolEditFormState,
    assignPaymentPoolStatusFormState,
    buildPaymentPoolChannelSavePayload,
    buildPaymentPoolCreatePayload,
    buildPaymentPoolEditableFromItem,
    buildPaymentPoolStatusPayload,
    buildPaymentPoolUpdatePayload,
    createPaymentPoolCreateFormState,
    createPaymentPoolEditFormState,
    createPaymentPoolStatusFormState,
    createPaymentPoolSummaryState,
    getSelectedPaymentPoolChannelRows,
    getSelectedPaymentPoolChannelTotalWeight,
    movePaymentPoolChannelEditorRow,
    normalizePaymentPoolChannelEditorRows,
    syncPaymentPoolEditFormFromEditable,
    syncPaymentPoolStatusFormFromEditable,
    togglePaymentPoolChannelRowSelection,
    updatePaymentPoolChannelRowWeight
  } from './modules/payment-pool-form-state'
  import type {
    PaymentPoolCreateFormState,
    PaymentPoolEditFormState,
    PaymentPoolStatusFormState
  } from './modules/payment-pool-form-state'
  import PaymentPoolMaintenanceDialogs from './modules/payment-pool-maintenance-dialogs.vue'
  import {
    fetchCreatePaymentPool,
    fetchDeletePaymentPool,
    fetchGetPaymentPoolChannelEditor,
    fetchGetPaymentPoolDeleteAudit,
    fetchGetPaymentPoolDetail,
    fetchGetPaymentPoolList,
    fetchSavePaymentPoolChannels,
    fetchUpdatePaymentPool,
    fetchUpdatePaymentPoolStatus
  } from '@/api/payment-pools'

  defineOptions({ name: 'PaymentPools' })

  type PoolListItem = Api.Payments.PoolListItem
  type PoolDetailItem = Api.Payments.PoolDetailItem
  type PoolEditable = Api.Payments.PoolEditable
  type PoolChannelEditor = Api.Payments.PoolChannelEditor
  type PoolChannelEditorAccount = Api.Payments.PoolChannelEditorAccount
  type PoolMissingChannelItem = Api.Payments.PoolMissingChannelItem
  type PoolDeleteAudit = Api.Payments.PoolDeleteAudit

  const loading = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const createVisible = ref(false)
  const editVisible = ref(false)
  const statusVisible = ref(false)
  const channelEditorVisible = ref(false)
  const channelEditorLoading = ref(false)
  const savingCreate = ref(false)
  const savingEdit = ref(false)
  const savingStatus = ref(false)
  const savingChannels = ref(false)
  const poolList = ref<PoolListItem[]>([])
  const activePool = ref<PoolDetailItem | null>(null)
  const editablePool = ref<PoolEditable | null>(null)
  const channelEditor = ref<PoolChannelEditor | null>(null)
  const channelEditorRows = ref<PoolChannelEditorAccount[]>([])
  const missingSelectedAccounts = ref<PoolMissingChannelItem[]>([])
  const { hasAuth } = useAuth()

  const pagination = reactive({
    current: 1,
    size: 20,
    total: 0
  })

  const summary = reactive(createPaymentPoolSummaryState())

  const searchForm = ref<{
    keyword?: string
    user_id?: string
    type?: string
    round_type?: string
    status?: string
  }>({})

  const createForm = reactive(createPaymentPoolCreateFormState())
  const editForm = reactive(createPaymentPoolEditFormState())
  const statusForm = reactive(createPaymentPoolStatusFormState())
  const hasPoolCreateAuth = computed(() => hasAuth('add') || hasAuth('index'))
  const hasPoolEditAuth = computed(() => hasAuth('edit') || hasAuth('index'))
  const hasPoolStatusAuth = computed(() => hasAuth('status') || hasAuth('index'))
  const hasPoolDeleteAuth = computed(() => hasAuth('remove') || hasAuth('index'))

  const paymentTypeOptions = [
    { label: '支付宝', value: 'alipay' },
    { label: '微信', value: 'wxpay' },
    { label: 'QQ', value: 'qqpay' },
    { label: 'USDT', value: 'usdt' }
  ]

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '搜索轮询池编号、名称、商户、支付类型或最近选中通道编号'
      }
    },
    {
      label: '商户编号',
      key: 'user_id',
      type: 'input',
      props: {
        placeholder: '按商户编号过滤'
      }
    },
    {
      label: '支付类型',
      key: 'type',
      type: 'select',
      props: {
        placeholder: '全部类型',
        options: paymentTypeOptions
      }
    },
    {
      label: '轮询方式',
      key: 'round_type',
      type: 'select',
      props: {
        placeholder: '全部方式',
        options: [
          { label: '顺序轮询', value: '1' },
          { label: '随机权重', value: '2' }
        ]
      }
    },
    {
      label: '状态',
      key: 'status',
      type: 'select',
      props: {
        placeholder: '全部状态',
        options: [
          { label: '启用', value: '1' },
          { label: '停用', value: '0' }
        ]
      }
    }
  ])

  const selectedChannelRows = computed(() =>
    getSelectedPaymentPoolChannelRows(channelEditorRows.value)
  )

  const selectedTotalWeight = computed(() =>
    getSelectedPaymentPoolChannelTotalWeight(channelEditorRows.value)
  )

  const { columnChecks, columns } = useTableColumns<PoolListItem>(() => [
    { type: 'globalIndex', width: 70, label: '序号' },
    {
      prop: 'merchant_display',
      label: '商户',
      minWidth: 210,
      formatter: (row) =>
        h('div', { class: 'merchant-cell' }, [
          h('strong', { class: 'cell-title' }, row.merchant_display),
          h('p', { class: 'cell-sub' }, `商户编号：${row.user_id || '--'}`)
        ])
    },
    {
      prop: 'name_label',
      label: '轮询池',
      minWidth: 230,
      formatter: (row) =>
        h('div', { class: 'pool-cell' }, [
          h('strong', { class: 'cell-title' }, row.name_label),
          h('p', { class: 'cell-sub' }, `#${row.id} / ${displayPoolType(row)}`)
        ])
    },
    {
      prop: 'round_type_label',
      label: '轮询方式',
      minWidth: 180,
      formatter: (row) =>
        h('div', { class: 'round-cell' }, [
          h(ElTag, { type: tagType(row.round_type_tag), effect: 'light' }, () =>
            displayPoolRoundType(row)
          ),
          h('p', { class: 'cell-sub' }, row.progress_label)
        ])
    },
    {
      prop: 'item_count',
      label: '已配通道',
      minWidth: 260,
      formatter: (row) =>
        h('div', { class: 'channel-cell' }, [
          h('strong', { class: 'cell-title' }, `${row.item_count} 个 / 权重 ${row.total_weight}`),
          h(
            'p',
            { class: 'cell-sub' },
            `可用 ${row.active_item_count} / 不可用 ${row.disabled_item_count} / 缺失 ${row.missing_item_count}`
          ),
          h('p', { class: 'channel-preview' }, displayPoolSelectedPreview(row))
        ])
    },
    {
      prop: 'status_label',
      label: '状态',
      minWidth: 150,
      align: 'center',
      formatter: (row) =>
        h('div', { class: 'state-cell' }, [
          h(ElTag, { type: tagType(row.status_type), effect: 'light' }, () =>
            displayPoolStatus(row)
          ),
          h(ElTag, { type: tagType(row.pool_state_type), effect: 'plain' }, () =>
            displayPoolState(row)
          )
        ])
    },
    {
      prop: 'update_time',
      label: '最近更新时间',
      minWidth: 180,
      formatter: (row) => row.latest_item_time || row.update_time || row.create_time || '--'
    },
    {
      prop: 'operation',
      label: '操作',
      width: 90,
      align: 'center',
      fixed: 'right',
      formatter: (row) =>
        h(
          ElButton,
          {
            type: 'primary',
            size: 'small',
            plain: true,
            class: 'table-action-link',
            onClick: () => openDetail(row)
          },
          () => '详情'
        )
    }
  ])

  onMounted(() => {
    getPoolList()
  })

  async function getPoolList() {
    loading.value = true
    try {
      const response = await fetchGetPaymentPoolList({
        current: pagination.current,
        size: pagination.size,
        keyword: searchForm.value.keyword,
        user_id: searchForm.value.user_id,
        type: searchForm.value.type,
        round_type: searchForm.value.round_type,
        status: searchForm.value.status
      })
      poolList.value = response.records
      pagination.current = response.current
      pagination.size = response.size
      pagination.total = response.total
      Object.assign(summary, response.summary || createPaymentPoolSummaryState())
    } catch {
      ElMessage.error('轮询池列表加载失败')
    } finally {
      loading.value = false
    }
  }

  function handleSearch(params: Api.Payments.PoolSearchParams) {
    pagination.current = 1
    searchForm.value = {
      keyword: params.keyword,
      user_id: params.user_id !== undefined ? String(params.user_id) : undefined,
      type: params.type,
      round_type: params.round_type !== undefined ? String(params.round_type) : undefined,
      status: params.status !== undefined ? String(params.status) : undefined
    }
    getPoolList()
  }

  function handleReset() {
    pagination.current = 1
    searchForm.value = {}
    getPoolList()
  }

  function handleSizeChange(size: number) {
    pagination.size = size
    pagination.current = 1
    getPoolList()
  }

  function handleCurrentChange(current: number) {
    pagination.current = current
    getPoolList()
  }

  function syncCreateFormState(form: PaymentPoolCreateFormState) {
    assignPaymentPoolCreateFormState(createForm, form)
  }

  function syncEditFormState(form: PaymentPoolEditFormState) {
    assignPaymentPoolEditFormState(editForm, form)
  }

  function syncStatusFormState(form: PaymentPoolStatusFormState) {
    assignPaymentPoolStatusFormState(statusForm, form)
  }

  async function openDetail(row: PoolListItem) {
    activePool.value = {
      ...row,
      selected_items: []
    }
    editablePool.value = buildPaymentPoolEditableFromItem(row)
    detailVisible.value = true
    detailLoading.value = true

    try {
      const response = await fetchGetPaymentPoolDetail(row.id)
      if (response.item) {
        applyPoolDetail(response.item, response.editable)
      }
    } catch {
      ElMessage.error('轮询池详情加载失败')
    } finally {
      detailLoading.value = false
    }
  }

  function openCreateDialog() {
    if (!hasPoolCreateAuth.value) {
      ElMessage.warning('您没有创建轮询池的权限。')
      return
    }

    assignPaymentPoolCreateFormState(createForm, createPaymentPoolCreateFormState())
    createVisible.value = true
  }

  async function submitCreatePool() {
    if (!hasPoolCreateAuth.value) {
      ElMessage.warning('您没有创建轮询池的权限。')
      return
    }

    const result = buildPaymentPoolCreatePayload(createForm)
    if ('message' in result) {
      ElMessage.warning(result.message)
      return
    }

    savingCreate.value = true
    try {
      const response = await fetchCreatePaymentPool(result.payload)

      createVisible.value = false
      if (response.item) {
        applyPoolDetail(response.item, response.editable)
        detailVisible.value = true
      }
      await getPoolList()
      ElMessage.success(
        `轮询池 ${response.created_pool_label || `#${response.created_pool_id}`} 已创建`
      )
    } catch {
      ElMessage.error('创建轮询池失败')
    } finally {
      savingCreate.value = false
    }
  }

  function openEditDialog() {
    if (!hasPoolEditAuth.value) {
      ElMessage.warning('您没有编辑轮询池的权限。')
      return
    }

    if (!activePool.value) {
      return
    }

    syncPaymentPoolEditFormFromEditable(
      editForm,
      editablePool.value || buildPaymentPoolEditableFromItem(activePool.value)
    )
    editVisible.value = true
  }

  function openStatusDialog() {
    if (!hasPoolStatusAuth.value) {
      ElMessage.warning('您没有修改轮询池状态的权限。')
      return
    }

    if (!activePool.value) {
      return
    }

    syncPaymentPoolStatusFormFromEditable(
      statusForm,
      editablePool.value || buildPaymentPoolEditableFromItem(activePool.value)
    )
    statusVisible.value = true
  }

  async function submitEdit() {
    if (!hasPoolEditAuth.value) {
      ElMessage.warning('您没有编辑轮询池的权限。')
      return
    }

    if (!activePool.value) {
      return
    }

    const result = buildPaymentPoolUpdatePayload(editForm)
    if ('message' in result) {
      ElMessage.warning(result.message)
      return
    }

    savingEdit.value = true
    try {
      const response = await fetchUpdatePaymentPool(activePool.value.id, result.payload)

      if (response.item) {
        applyPoolDetail(response.item, response.editable)
      }

      await getPoolList()
      editVisible.value = false
    } catch {
      ElMessage.error('保存轮询池基础配置失败')
    } finally {
      savingEdit.value = false
    }
  }

  async function submitStatus() {
    if (!hasPoolStatusAuth.value) {
      ElMessage.warning('您没有修改轮询池状态的权限。')
      return
    }

    if (!activePool.value) {
      return
    }

    savingStatus.value = true
    try {
      const response = await fetchUpdatePaymentPoolStatus(
        activePool.value.id,
        buildPaymentPoolStatusPayload(statusForm).payload
      )

      if (response.item) {
        applyPoolDetail(
          {
            ...response.item,
            selected_items: activePool.value.selected_items || []
          },
          {
            ...(editablePool.value || buildPaymentPoolEditableFromItem(response.item)),
            status: response.item.status
          }
        )
      }

      await getPoolList()
      statusVisible.value = false
    } catch {
      ElMessage.error('保存轮询池状态失败')
    } finally {
      savingStatus.value = false
    }
  }

  async function openChannelDialog() {
    if (!hasPoolEditAuth.value) {
      ElMessage.warning('您没有维护轮询池通道分配的权限。')
      return
    }

    if (!activePool.value) {
      return
    }

    channelEditorVisible.value = true
    channelEditorLoading.value = true
    try {
      const response = await fetchGetPaymentPoolChannelEditor(activePool.value.id)
      if (response.item) {
        applyPoolDetail(response.item, response.editable)
      }
      applyChannelEditor(response.editor)
    } catch {
      ElMessage.error('轮询池通道编辑器加载失败')
    } finally {
      channelEditorLoading.value = false
    }
  }

  async function submitChannelEditor() {
    if (!hasPoolEditAuth.value) {
      ElMessage.warning('您没有维护轮询池通道分配的权限。')
      return
    }

    if (!activePool.value) {
      return
    }

    savingChannels.value = true
    try {
      const response = await fetchSavePaymentPoolChannels(
        activePool.value.id,
        buildPaymentPoolChannelSavePayload(channelEditorRows.value)
      )

      if (response.item) {
        applyPoolDetail(response.item, response.editable)
      }
      applyChannelEditor(response.channel_editor)
      await getPoolList()
      channelEditorVisible.value = false
      ElMessage.success('轮询池通道分配已保存')
    } catch {
      ElMessage.error('保存轮询池通道分配失败')
    } finally {
      savingChannels.value = false
    }
  }

  function applyChannelEditor(editorData: PoolChannelEditor) {
    channelEditor.value = editorData
    channelEditorRows.value = normalizePaymentPoolChannelEditorRows(editorData)
    missingSelectedAccounts.value = editorData.missing_selected_accounts || []
  }

  function handleChannelSelectedChange(
    row: PoolChannelEditorAccount,
    value: string | number | boolean
  ) {
    togglePaymentPoolChannelRowSelection(channelEditorRows.value, row, value)
  }

  function updateChannelWeight(
    row: PoolChannelEditorAccount,
    value: string | number | null | undefined
  ) {
    updatePaymentPoolChannelRowWeight(row, value)
  }

  function moveChannelRow(row: PoolChannelEditorAccount, direction: -1 | 1) {
    movePaymentPoolChannelEditorRow(channelEditorRows.value, row.account_id, direction)
  }

  async function handleDeletePool() {
    if (!hasPoolDeleteAuth.value) {
      ElMessage.warning('您没有删除轮询池的权限。')
      return
    }

    const target = activePool.value
    if (!target) {
      return
    }

    try {
      const response = await fetchGetPaymentPoolDeleteAudit(target.id)
      const audit = response.audit

      if (!audit.can_delete) {
        await ElMessageBox.alert(buildDeleteBlockedMessage(audit), '删除受阻', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(buildDeletePromptMessage(audit), '删除轮询池', {
        confirmButtonText: '删除',
        cancelButtonText: '取消',
        type: 'error',
        inputPlaceholder: audit.confirmation_phrase,
        inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
        inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以确认删除。`
      })

      const deleteResponse = await fetchDeletePaymentPool(target.id, {
        confirmation_phrase: String(value || '')
      })

      detailVisible.value = false
      activePool.value = null
      editablePool.value = null
      channelEditor.value = null
      channelEditorRows.value = []
      missingSelectedAccounts.value = []
      await getPoolList()
      ElMessage.success(`轮询池 ${deleteResponse.deleted_pool_label || `#${target.id}`} 已删除`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('删除轮询池失败')
    }
  }

  function applyPoolDetail(item: PoolDetailItem, editable?: PoolEditable | null) {
    activePool.value = item
    editablePool.value = editable || buildPaymentPoolEditableFromItem(item)
    syncPaymentPoolEditFormFromEditable(editForm, editablePool.value)
    syncPaymentPoolStatusFormFromEditable(statusForm, editablePool.value)
  }

  function buildDeletePromptMessage(audit: PoolDeleteAudit) {
    return [
      `${audit.pool_label} 将被永久删除。`,
      '',
      `已选通道：${audit.summary.selected_channel_count}`,
      `当前可用：${audit.summary.active_selected_channel_count}`,
      `失效条目：${audit.summary.missing_selected_channel_count}`,
      `总权重：${audit.summary.total_weight}`,
      '',
      `请输入 ${audit.confirmation_phrase} 以确认本次删除。`,
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function buildDeleteBlockedMessage(audit: PoolDeleteAudit) {
    return [
      `${audit.pool_label} 当前不能删除。`,
      '',
      ...audit.blocking_reasons.map((item) => `- ${item}`),
      '',
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function displayPoolType(pool?: Partial<PoolListItem> | null, fallback = '--') {
    return displayAdminFixtureText(pool?.type_text || pool?.type_label || pool?.type, fallback)
  }

  function displayPoolRoundType(pool?: Partial<PoolListItem> | null, fallback = '--') {
    return displayAdminFixtureText(pool?.round_type_text || pool?.round_type_label, fallback)
  }

  function displayPoolStatus(pool?: Partial<PoolListItem> | null, fallback = '--') {
    return displayAdminFixtureText(pool?.status_text || pool?.status_label, fallback)
  }

  function displayPoolState(pool?: Partial<PoolListItem> | null, fallback = '--') {
    return displayAdminFixtureText(pool?.pool_state_text || pool?.pool_state_label, fallback)
  }

  function displayPoolSelectedPreview(pool?: Partial<PoolListItem> | null, fallback = '--') {
    return displayAdminFixtureText(pool?.selected_preview_text || pool?.selected_preview, fallback)
  }

  function escapeRegExp(value: string) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  }

  function isDialogCancel(error: unknown) {
    return (
      error === 'cancel' ||
      error === 'close' ||
      (error instanceof Error && error.message === 'cancel')
    )
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
  .payment-pool-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .merchant-cell,
  .pool-cell,
  .round-cell,
  .channel-cell,
  .channel-name {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .state-cell {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: center;
  }

  .cell-title {
    color: var(--el-text-color-primary);
    font-size: 14px;
    word-break: break-all;
  }

  .cell-sub,
  .channel-preview {
    margin: 0;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 1.6;
    word-break: break-all;
  }

  .channel-preview {
    color: var(--el-text-color-regular);
  }
</style>
