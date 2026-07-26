<template>
  <div class="merchant-page merchant-pool-page">
    <section class="merchant-page-header">
      <div class="merchant-page-header__title">
        <h1>轮询池</h1>
      </div>

      <div v-if="!loading" class="merchant-chip-row">
        <span class="merchant-chip"
          >商户 #{{ summary.merchant_id || merchantStore.merchantId || '--' }}</span
        >
        <span class="merchant-chip">轮询池 {{ pagination.total }}</span>
        <span class="merchant-chip">{{ summary.vip_label || merchantStore.vipLabel }}</span>
      </div>
    </section>

    <div v-if="loading" class="merchant-panel merchant-state-card">
      <ElSkeleton :rows="8" animated />
    </div>

    <template v-else>
      <ArtSearchBar
        v-model="searchForm"
        :items="searchItems"
        :showExpand="false"
        @search="handleSearch"
        @reset="handleReset"
      />

      <article class="merchant-card merchant-pool-table-card">
        <div class="merchant-card__head merchant-card__head--stack">
          <h2>轮询池列表</h2>

          <div class="merchant-pool-toolbar">
            <ElTag
              v-for="item in poolToolbarStats"
              :key="item.key"
              :type="item.type"
              effect="plain"
            >
              {{ item.label }} {{ item.value }}
            </ElTag>
            <ElButton v-if="canCreate" type="primary" @click="openCreateDialog"
              >新建轮询池</ElButton
            >
            <ElButton plain @click="loadPools">刷新</ElButton>
          </div>
        </div>

        <ArtTable
          :loading="loading"
          :data="poolList"
          :columns="columns"
          :pagination="pagination"
          @pagination:size-change="handleSizeChange"
          @pagination:current-change="handleCurrentChange"
        />
      </article>
    </template>

    <MerchantPoolDetailDrawer
      :visible="detailVisible"
      :detail-loading="detailLoading"
      :active-pool="activePool"
      :merchant-id="summary.merchant_id || merchantStore.merchantId"
      :can-edit="canEdit"
      :can-toggle-status="canToggleStatus"
      :can-delete="canDelete"
      @update:visible="detailVisible = $event"
      @edit="openEditDialog"
      @channels="openChannelDialog"
      @status="handleToggleStatusPool"
      @delete="handleDeletePool"
    />

    <MerchantPoolMaintenanceDialogs
      :create-visible="createVisible"
      :edit-visible="editVisible"
      :saving-create="savingCreate"
      :saving-edit="savingEdit"
      :active-pool="activePool"
      :create-form="createForm"
      :edit-form="editForm"
      :payment-type-options="paymentTypeOptions"
      @update:create-visible="createVisible = $event"
      @update:edit-visible="editVisible = $event"
      @update:create-form="syncCreateFormState"
      @update:edit-form="syncEditFormState"
      @submit:create="submitCreatePool"
      @submit:edit="submitEdit"
    />

    <MerchantPoolChannelEditorDialog
      :visible="channelEditorVisible"
      :loading="channelEditorLoading"
      :saving-channels="savingChannels"
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
  import { ElMessage, ElMessageBox, ElTag } from 'element-plus'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import ArtButtonTable from '@/components/core/forms/artButtonTable/index.vue'
  import { useMerchantStore } from '@/store/modules/merchant'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'
  import MerchantPoolChannelEditorDialog from './modules/MerchantPoolChannelEditorDialog.vue'
  import MerchantPoolDetailDrawer from './modules/MerchantPoolDetailDrawer.vue'
  import {
    assignMerchantPoolCreateFormState,
    assignMerchantPoolEditFormState,
    buildMerchantPoolChannelSavePayload,
    buildMerchantPoolCreatePayload,
    buildMerchantPoolEditableFromItem,
    buildMerchantPoolStatusPayload,
    buildMerchantPoolUpdatePayload,
    createMerchantPoolCreateFormState,
    createMerchantPoolEditFormState,
    createMerchantPoolSummaryState,
    getSelectedMerchantPoolChannelRows,
    getSelectedMerchantPoolChannelTotalWeight,
    moveMerchantPoolChannelEditorRow,
    normalizeMerchantPoolChannelEditorRows,
    syncMerchantPoolEditFormFromEditable,
    toggleMerchantPoolChannelRowSelection,
    updateMerchantPoolChannelRowWeight
  } from './modules/merchantPoolFormState'
  import type {
    MerchantPoolCreateFormState,
    MerchantPoolEditFormState
  } from './modules/merchantPoolFormState'
  import MerchantPoolMaintenanceDialogs from './modules/MerchantPoolMaintenanceDialogs.vue'
  import {
    createMerchantPool,
    deleteMerchantPool,
    fetchMerchantPoolChannelEditor,
    fetchMerchantPoolDeleteAudit,
    fetchMerchantPoolDetail,
    fetchMerchantPools,
    saveMerchantPoolChannels,
    updateMerchantPool,
    updateMerchantPoolStatus
  } from '@/api/merchant'

  defineOptions({ name: 'MerchantPools' })

  type PoolListItem = Api.Payments.PoolListItem
  type PoolDetailItem = Api.Payments.PoolDetailItem
  type PoolEditable = Api.Payments.PoolEditable
  type PoolChannelEditor = Api.Payments.PoolChannelEditor
  type PoolChannelEditorAccount = Api.Payments.PoolChannelEditorAccount
  type PoolMissingChannelItem = Api.Payments.PoolMissingChannelItem
  type PoolDeleteAudit = Api.Payments.PoolDeleteAudit

  const merchantStore = useMerchantStore()
  const loading = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const createVisible = ref(false)
  const editVisible = ref(false)
  const channelEditorVisible = ref(false)
  const channelEditorLoading = ref(false)
  const savingCreate = ref(false)
  const savingEdit = ref(false)
  const savingChannels = ref(false)
  const poolList = ref<PoolListItem[]>([])
  const activePool = ref<PoolDetailItem | null>(null)
  const editablePool = ref<PoolEditable | null>(null)
  const channelEditor = ref<PoolChannelEditor | null>(null)
  const channelEditorRows = ref<PoolChannelEditorAccount[]>([])
  const missingSelectedAccounts = ref<PoolMissingChannelItem[]>([])
  const catalog = ref<Record<string, any>>({})

  const pagination = reactive({
    current: 1,
    size: 20,
    total: 0
  })

  const summary = reactive(createMerchantPoolSummaryState())
  const writeActions = reactive({
    create: false,
    edit: false,
    status: false,
    remove: false
  })

  const searchForm = ref<{
    keyword?: string
    type?: string
    round_type?: string
    status?: string
  }>({})

  const createForm = reactive(createMerchantPoolCreateFormState())
  const editForm = reactive(createMerchantPoolEditFormState())

  const canCreate = computed(() => writeActions.create !== false)
  const canEdit = computed(() => writeActions.edit !== false)
  const canToggleStatus = computed(() => writeActions.status !== false)
  const canDelete = computed(() => writeActions.remove !== false)
  const poolToolbarStats = computed<
    Array<{
      key: string
      label: string
      value: number
      type?: 'success' | 'warning' | 'info' | 'primary'
    }>
  >(() => {
    const total = Number(pagination.total || 0)
    const enabled = Number(summary.enabled_count || 0)
    const disabled = Number(summary.disabled_count || 0)
    const configured = Number(summary.configured_pool_count || 0)
    const healthy = Number(summary.healthy_pool_count || 0)
    const channels = Number(summary.configured_channel_count || 0)

    return [
      { key: 'total', label: '总数', value: total },
      ...(enabled > 0
        ? [{ key: 'enabled', label: '启用', value: enabled, type: 'success' as const }]
        : []),
      ...(disabled > 0
        ? [{ key: 'disabled', label: '停用', value: disabled, type: 'warning' as const }]
        : []),
      ...(configured > 0 ? [{ key: 'configured', label: '已配置', value: configured }] : []),
      ...(healthy > 0
        ? [{ key: 'healthy', label: '可用', value: healthy, type: 'success' as const }]
        : []),
      ...(channels > 0
        ? [{ key: 'channels', label: '账号', value: channels, type: 'info' as const }]
        : [])
    ]
  })

  const paymentTypeOptions = computed(() =>
    Array.isArray(catalog.value?.payment_types) && catalog.value.payment_types.length > 0
      ? catalog.value.payment_types
      : [
          { label: '支付宝', value: 'alipay' },
          { label: '微信', value: 'wxpay' },
          { label: 'QQ', value: 'qqpay' },
          { label: 'USDT', value: 'usdt' }
        ]
  )

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '搜索编号、名称或类型'
      }
    },
    {
      label: '支付类型',
      key: 'type',
      type: 'select',
      props: {
        placeholder: '全部类型',
        options: paymentTypeOptions.value
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
          { label: '随机轮询', value: '2' }
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
    getSelectedMerchantPoolChannelRows(channelEditorRows.value)
  )

  const selectedTotalWeight = computed(() =>
    getSelectedMerchantPoolChannelTotalWeight(channelEditorRows.value)
  )

  const { columns } = useTableColumns<PoolListItem>(() => [
    { type: 'globalIndex', width: 70, label: '序号' },
    {
      prop: 'name_label',
      label: '轮询池',
      minWidth: 260,
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
      label: '已配账号',
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
        h(ArtButtonTable, {
          type: 'view',
          title: '详情',
          onClick: () => openDetail(row)
        })
    }
  ])

  onMounted(() => {
    loadPools()
  })

  async function loadPools() {
    loading.value = true
    try {
      const response = await fetchMerchantPools({
        current: pagination.current,
        size: pagination.size,
        keyword: searchForm.value.keyword,
        type: searchForm.value.type,
        round_type: searchForm.value.round_type,
        status: searchForm.value.status
      })

      poolList.value = response.records as PoolListItem[]
      pagination.current = Number(response.pagination?.current || 1)
      pagination.size = Number(response.pagination?.size || 20)
      pagination.total = Number(response.pagination?.total || 0)
      Object.assign(summary, createMerchantPoolSummaryState(), response.summary || {})
      Object.assign(
        writeActions,
        {
          create: false,
          edit: false,
          status: false,
          remove: false
        },
        response.writeActions || {}
      )
      catalog.value = response.catalog || {}

      if (
        !paymentTypeOptions.value.some(
          (item: Record<string, any>) => item.value === createForm.type
        ) &&
        paymentTypeOptions.value.length > 0
      ) {
        createForm.type = String(paymentTypeOptions.value[0]?.value || 'alipay')
      }
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
      type: params.type,
      round_type: params.round_type !== undefined ? String(params.round_type) : undefined,
      status: params.status !== undefined ? String(params.status) : undefined
    }
    loadPools()
  }

  function handleReset() {
    pagination.current = 1
    searchForm.value = {}
    loadPools()
  }

  function handleSizeChange(size: number) {
    pagination.size = size
    pagination.current = 1
    loadPools()
  }

  function handleCurrentChange(current: number) {
    pagination.current = current
    loadPools()
  }

  function syncCreateFormState(form: MerchantPoolCreateFormState) {
    assignMerchantPoolCreateFormState(createForm, form)
  }

  function syncEditFormState(form: MerchantPoolEditFormState) {
    assignMerchantPoolEditFormState(editForm, form)
  }

  async function openDetail(row: PoolListItem) {
    activePool.value = {
      ...row,
      selected_items: []
    } as PoolDetailItem
    editablePool.value = buildMerchantPoolEditableFromItem(row)
    detailVisible.value = true
    detailLoading.value = true

    try {
      const response = await fetchMerchantPoolDetail(row.id)
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
    if (!canCreate.value) {
      ElMessage.warning('当前商户暂无创建轮询池权限')
      return
    }

    assignMerchantPoolCreateFormState(createForm, createMerchantPoolCreateFormState())
    if (paymentTypeOptions.value.length > 0) {
      createForm.type = String(paymentTypeOptions.value[0]?.value || 'alipay')
    }
    createVisible.value = true
  }

  async function submitCreatePool() {
    if (!canCreate.value) {
      ElMessage.warning('当前商户暂无创建轮询池权限')
      return
    }

    const result = buildMerchantPoolCreatePayload(createForm)
    if ('message' in result) {
      ElMessage.warning(result.message)
      return
    }

    savingCreate.value = true
    try {
      const response = await createMerchantPool(result.payload)

      createVisible.value = false
      if (response.item) {
        applyPoolDetail(response.item, response.editable)
        detailVisible.value = true
      }
      await loadPools()
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
    if (!canEdit.value) {
      ElMessage.warning('当前商户暂无编辑轮询池权限')
      return
    }

    if (!activePool.value) {
      return
    }

    syncMerchantPoolEditFormFromEditable(
      editForm,
      editablePool.value || buildMerchantPoolEditableFromItem(activePool.value)
    )
    editVisible.value = true
  }

  async function submitEdit() {
    if (!canEdit.value || !activePool.value) {
      ElMessage.warning('当前商户暂无编辑轮询池权限')
      return
    }

    const result = buildMerchantPoolUpdatePayload(editForm)
    if ('message' in result) {
      ElMessage.warning(result.message)
      return
    }

    savingEdit.value = true
    try {
      const response = await updateMerchantPool(activePool.value.id, result.payload)

      if (response.item) {
        applyPoolDetail(response.item, response.editable)
      }

      await loadPools()
      editVisible.value = false
      ElMessage.success('轮询池已更新')
    } catch {
      ElMessage.error('保存轮询池失败')
    } finally {
      savingEdit.value = false
    }
  }

  async function handleToggleStatusPool() {
    if (!canToggleStatus.value || !activePool.value) {
      ElMessage.warning('当前商户暂无维护轮询池状态权限')
      return
    }

    const nextStatus = activePool.value.status === 1 ? 0 : 1
    const actionLabel = nextStatus === 1 ? '启用' : '停用'

    try {
      await ElMessageBox.confirm(
        `确定要${actionLabel} ${activePool.value.name_label} 吗？`,
        `${actionLabel}轮询池`,
        {
          type: nextStatus === 1 ? 'success' : 'warning',
          confirmButtonText: actionLabel,
          cancelButtonText: '取消'
        }
      )

      const response = await updateMerchantPoolStatus(activePool.value.id, {
        ...buildMerchantPoolStatusPayload(nextStatus).payload
      })

      if (response.item) {
        applyPoolDetail(
          {
            ...response.item,
            selected_items: activePool.value.selected_items || []
          },
          {
            ...(editablePool.value || buildMerchantPoolEditableFromItem(response.item)),
            status: response.item.status
          }
        )
      }

      await loadPools()
      ElMessage.success(`轮询池已${actionLabel}`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error(`${actionLabel}轮询池失败`)
    }
  }

  async function openChannelDialog() {
    if (!canEdit.value || !activePool.value) {
      ElMessage.warning('当前商户暂无维护通道分配权限')
      return
    }

    channelEditorVisible.value = true
    channelEditorLoading.value = true

    try {
      const response = await fetchMerchantPoolChannelEditor(activePool.value.id)
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
    if (!canEdit.value || !activePool.value) {
      ElMessage.warning('当前商户暂无维护通道分配权限')
      return
    }

    savingChannels.value = true
    try {
      const response = await saveMerchantPoolChannels(
        activePool.value.id,
        buildMerchantPoolChannelSavePayload(channelEditorRows.value)
      )

      if (response.item) {
        applyPoolDetail(response.item, response.editable)
      }
      applyChannelEditor(response.channel_editor)
      await loadPools()
      channelEditorVisible.value = false
      ElMessage.success('轮询池通道分配已保存')
    } catch {
      ElMessage.error('保存轮询池通道分配失败')
    } finally {
      savingChannels.value = false
    }
  }

  async function handleDeletePool() {
    if (!canDelete.value || !activePool.value) {
      ElMessage.warning('当前商户暂无删除轮询池权限')
      return
    }

    const target = activePool.value

    try {
      const response = await fetchMerchantPoolDeleteAudit(target.id)
      const audit = response.audit as PoolDeleteAudit

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
        inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以确认删除`
      })

      const deleteResponse = await deleteMerchantPool(target.id, {
        confirmation_phrase: String(value || '')
      })

      detailVisible.value = false
      activePool.value = null
      editablePool.value = null
      channelEditor.value = null
      channelEditorRows.value = []
      missingSelectedAccounts.value = []
      await loadPools()
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
    editablePool.value = editable || buildMerchantPoolEditableFromItem(item)
    syncMerchantPoolEditFormFromEditable(editForm, editablePool.value)
  }

  function applyChannelEditor(editorData: PoolChannelEditor) {
    channelEditor.value = editorData
    channelEditorRows.value = normalizeMerchantPoolChannelEditorRows(editorData)
    missingSelectedAccounts.value = editorData?.missing_selected_accounts || []
  }

  function handleChannelSelectedChange(
    row: PoolChannelEditorAccount,
    value: string | number | boolean
  ) {
    toggleMerchantPoolChannelRowSelection(channelEditorRows.value, row, value)
  }

  function updateChannelWeight(
    row: PoolChannelEditorAccount,
    value: string | number | null | undefined
  ) {
    updateMerchantPoolChannelRowWeight(row, value)
  }

  function moveChannelRow(row: PoolChannelEditorAccount, direction: -1 | 1) {
    moveMerchantPoolChannelEditorRow(channelEditorRows.value, row.account_id, direction)
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
  .merchant-pool-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .merchant-pool-toolbar,
  .pool-cell,
  .round-cell,
  .channel-cell {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
  }

  .pool-cell,
  .round-cell,
  .channel-cell {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }

  .state-cell {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: center;
  }

  .cell-title,
  .detail-item strong,
  .detail-hero-copy h3 {
    color: var(--el-text-color-primary);
    font-size: 14px;
    word-break: break-all;
  }

  .cell-sub,
  .channel-preview,
  .detail-hero-copy p,
  .detail-hero-copy span {
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
