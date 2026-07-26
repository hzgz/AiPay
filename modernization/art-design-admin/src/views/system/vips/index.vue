<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <div class="vip-page art-full-height">
    <ArtSearchBar
      v-model="searchForm"
      :items="searchItems"
      :showExpand="false"
      @search="handleSearch"
      @reset="handleReset"
    />

    <ElCard class="art-table-card">
      <ArtTableHeader v-model:columns="columnChecks" :loading="loading" @refresh="getVipList">
        <template #left>
          <ElSpace wrap>
            <ElButton v-auth="'add'" type="primary" @click="openCreateDialog">新增套餐</ElButton>
            <ElTag effect="plain">套餐数 {{ pagination.total }}</ElTag>
            <ElTag type="success" effect="plain">启用 {{ summary.enabled_count }}</ElTag>
            <ElTag type="warning" effect="plain">停用 {{ summary.disabled_count }}</ElTag>
            <ElTag type="primary" effect="plain">商户数 {{ summary.merchant_count }}</ElTag>
            <ElButton
              v-if="hasAuth('recycle')"
              plain
              :type="isRecycleView ? 'primary' : 'info'"
              @click="toggleRecycleView"
            >
              {{ isRecycleView ? '返回正常列表' : '回收站' }}
            </ElButton>
            <ElButton
              v-if="hasAuth('recycle') && isRecycleView"
              plain
              type="success"
              :disabled="selectedVips.length === 0"
              @click="handleBatchRestoreVips"
            >
              批量恢复
            </ElButton>
            <ElButton
              v-if="hasAuth('batchRemove') && !isRecycleView"
              plain
              type="danger"
              :disabled="selectedVips.length === 0"
              @click="handleBatchDeleteVips"
            >
              批量删除
            </ElButton>
            <ElTag v-if="selectedVips.length > 0" type="danger" effect="plain">
              已选 {{ selectedVips.length }}
            </ElTag>
            <ElTag v-if="isRecycleView" type="info" effect="plain">回收站视图</ElTag>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <VueDraggable
        v-model="vipList"
        target="tbody"
        handle=".vip-drag-handle"
        :animation="150"
        :disabled="!hasAuth('sort') || loading || reorderingVips || isRecycleView"
        @end="handleVipDragEnd"
      >
        <ArtTable
          ref="tableRef"
          :loading="loading || reorderingVips"
          :data="vipList"
          :columns="columns"
          :pagination="pagination"
          row-key="id"
          reserve-selection
          @selection-change="handleVipSelectionChange"
          @pagination:size-change="handleSizeChange"
          @pagination:current-change="handleCurrentChange"
        />
      </VueDraggable>
    </ElCard>

    <ElDrawer
      v-model="detailVisible"
      size="780px"
      destroy-on-close
      :title="activeVip ? `${activeVip.name} #${activeVip.id}` : '会员套餐详情'"
    >
      <div v-loading="detailLoading" class="vip-detail">
        <template v-if="activeVip">
          <div class="detail-hero">
            <div class="detail-hero-copy">
              <h3>{{ activeVip.name }}</h3>
              <p
                >#{{ activeVip.id }} / {{ activeVip.duration_label }} / 费率
                {{ activeVip.fee_rate_display }}</p
              >
              <span>查看套餐配置、额度规则、通道限制以及关联商户情况。</span>
            </div>
            <div class="detail-hero-actions">
              <ElButton
                v-if="activeVip.deleted && hasAuth('recycle')"
                type="success"
                plain
                @click="handleRestoreVip()"
              >
                恢复套餐
              </ElButton>
              <template v-else>
                <ElButton v-if="hasAuth('edit')" type="primary" plain @click="openEditDialog"
                  >编辑</ElButton
                >
                <ElButton v-if="hasAuth('sort')" type="warning" plain @click="openSortDialog">
                  调整排序
                </ElButton>
                <ElButton
                  v-if="hasAuth('status')"
                  :type="activeVip.status === 1 ? 'warning' : 'success'"
                  plain
                  @click="openStatusDialog"
                >
                  {{ activeVip.status === 1 ? '停用' : '启用' }}
                </ElButton>
                <ElButton v-if="hasAuth('remove')" type="danger" plain @click="handleDeleteVip()">
                  移入回收站
                </ElButton>
              </template>
            </div>
          </div>

          <div class="drawer-section">
            <ElDescriptions :column="2" border>
              <ElDescriptionsItem label="套餐名称">{{ activeVip.name }}</ElDescriptionsItem>
              <ElDescriptionsItem label="套餐价格">{{
                formatAmount(activeVip.money)
              }}</ElDescriptionsItem>
              <ElDescriptionsItem label="会员天数">{{
                activeVip.duration_label
              }}</ElDescriptionsItem>
              <ElDescriptionsItem label="费率">{{ activeVip.fee_rate_display }}</ElDescriptionsItem>
              <ElDescriptionsItem label="状态">{{ activeVip.status_label }}</ElDescriptionsItem>
              <ElDescriptionsItem label="删除时间">{{
                activeVip.delete_time || '--'
              }}</ElDescriptionsItem>
              <ElDescriptionsItem label="排序值">{{ activeVip.sort }}</ElDescriptionsItem>
              <ElDescriptionsItem label="关联商户">{{
                activeVip.merchant_count
              }}</ElDescriptionsItem>
              <ElDescriptionsItem label="生效商户">{{
                activeVip.active_merchant_count
              }}</ElDescriptionsItem>
              <ElDescriptionsItem label="日额度">{{
                activeVip.today_quota || '--'
              }}</ElDescriptionsItem>
              <ElDescriptionsItem label="月额度">{{
                activeVip.month_quota || '--'
              }}</ElDescriptionsItem>
              <ElDescriptionsItem label="可用通道">
                <div v-if="activeVip.passage_codes.length" class="feature-tags">
                  <ElTag
                    v-for="code in activeVip.passage_codes"
                    :key="code"
                    size="small"
                    effect="plain"
                  >
                    {{ code }}
                  </ElTag>
                </div>
                <span v-else>--</span>
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>功能开关</h4>
            <div class="feature-tags">
              <ElTag :type="activeVip.profit_enabled ? 'success' : 'info'" effect="plain">
                {{ activeVip.profit_enabled ? '开启分润' : '未开启分润' }}
              </ElTag>
              <ElTag :type="activeVip.add_channel_enabled ? 'success' : 'info'" effect="plain">
                {{
                  activeVip.add_channel_enabled
                    ? `赠送通道 ${activeVip.add_channel_num}`
                    : '未赠送通道'
                }}
              </ElTag>
              <ElTag :type="activeVip.quota_enabled ? 'warning' : 'info'" effect="plain">
                {{ activeVip.quota_enabled ? '启用额度限制' : '不限额度' }}
              </ElTag>
              <ElTag :type="activeVip.passage_enabled ? 'primary' : 'info'" effect="plain">
                {{ activeVip.passage_enabled ? '启用通道限制' : '不限通道' }}
              </ElTag>
            </div>
          </div>

          <div class="drawer-section">
            <h4>最近关联商户</h4>
            <div v-if="vipMerchants.length" class="merchant-list">
              <ElTag
                v-for="merchant in vipMerchants"
                :key="merchant.id"
                :type="tagType(merchant.status_type)"
                effect="plain"
              >
                {{ merchant.display }} / {{ merchant.status_label }}
              </ElTag>
            </div>
            <ElAlert v-else type="info" :closable="false" title="当前套餐暂无关联商户。" />
          </div>
        </template>
      </div>
    </ElDrawer>

    <VipEditDialog
      :visible="editVisible"
      :loading="formPreparing"
      :submitting="savingEdit"
      :mode="dialogMode"
      :form="editForm"
      :passage-option-groups="formPassageOptionGroups"
      @update:visible="editVisible = $event"
      @update:form="syncEditFormState"
      @submit="submitForm"
    />

    <VipStatusSortDialogs
      :status-visible="statusVisible"
      :sort-visible="sortVisible"
      :saving-status="savingStatus"
      :saving-sort="savingSort"
      :status-form="statusForm"
      :sort-form="sortForm"
      @update:status-visible="statusVisible = $event"
      @update:sort-visible="sortVisible = $event"
      @update:status-form="syncStatusFormState"
      @update:sort-form="syncSortFormState"
      @submit:status="submitStatus"
      @submit:sort="submitSort"
    />
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox, ElTag } from 'element-plus'
  import { VueDraggable } from 'vue-draggable-plus'
  import { useAuth } from '@/hooks/core/useAuth'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import ArtButtonTable from '@/components/core/forms/artButtonTable/index.vue'
  import VipEditDialog from './modules/VipEditDialog.vue'
  import {
    assignVipEditFormState,
    assignVipSortFormState,
    assignVipStatusFormState,
    buildVipPayloadFromForm,
    createVipEditFormState,
    createVipSortFormState,
    createVipStatusFormState,
    syncVipEditFormFromEditable,
    syncVipSortForm,
    syncVipStatusFormFromEditable,
    validateVipEditForm,
    validateVipSortForm
  } from './modules/vipFormState'
  import type {
    VipDialogMode,
    VipEditFormState,
    VipSortFormState,
    VipStatusFormState
  } from './modules/vipFormState'
  import VipStatusSortDialogs from './modules/VipStatusSortDialogs.vue'
  import {
    fetchAuditVipBatchDelete,
    fetchBatchRestoreVips,
    fetchBatchDeleteVips,
    fetchCreateVip,
    fetchDeleteVip,
    fetchGetVipDetail,
    fetchGetVipDeleteAudit,
    fetchGetVipList,
    fetchReorderVips,
    fetchRestoreVip,
    fetchGetVipTemplate,
    fetchUpdateVip,
    fetchUpdateVipSort,
    fetchUpdateVipStatus
  } from '@/api/vips'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'

  defineOptions({ name: 'SystemVips' })

  type VipItem = Api.Vips.VipListItem
  type VipMerchantItem = Api.Vips.VipMerchantItem
  type VipSummary = Api.Vips.VipSummary
  type VipEditable = Api.Vips.VipEditable
  type VipPassageOptionGroup = Api.Vips.VipPassageOptionGroup
  type VipDeleteAudit = Api.Vips.VipDeleteAudit
  type VipBatchDeleteAudit = Api.Vips.VipBatchDeleteAudit

  const { hasAuth } = useAuth()
  const tableRef = ref<any>(null)
  const loading = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const editVisible = ref(false)
  const statusVisible = ref(false)
  const sortVisible = ref(false)
  const savingEdit = ref(false)
  const savingStatus = ref(false)
  const savingSort = ref(false)
  const reorderingVips = ref(false)
  const formPreparing = ref(false)
  const dialogMode = ref<VipDialogMode>('edit')
  const vipList = ref<VipItem[]>([])
  const selectedVips = ref<VipItem[]>([])
  const activeVip = ref<VipItem | null>(null)
  const editableVip = ref<VipEditable | null>(null)
  const formPassageOptionGroups = ref<VipPassageOptionGroup[]>([])
  const vipMerchants = ref<VipMerchantItem[]>([])
  const pagination = reactive({
    current: 1,
    size: 20,
    total: 0
  })
  const summary = reactive<VipSummary>({
    total: 0,
    enabled_count: 0,
    disabled_count: 0,
    merchant_count: 0,
    active_merchant_count: 0
  })
  const searchForm = ref<{
    keyword?: string
    status?: string
    passage_enabled?: string
  }>({})

  const isRecycleView = computed(() => searchForm.value.status === '-1')

  const editForm = reactive(createVipEditFormState())

  const statusForm = reactive(createVipStatusFormState())

  const sortForm = reactive(createVipSortFormState())

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '请输入 VIP 编号、套餐名称、费率或通道编码'
      }
    },
    {
      label: '状态',
      key: 'status',
      type: 'select',
      props: {
        placeholder: '请选择状态',
        options: [
          { label: '启用', value: '1' },
          { label: '停用', value: '0' },
          { label: '回收站', value: '-1' }
        ]
      }
    },
    {
      label: '通道限制',
      key: 'passage_enabled',
      type: 'select',
      props: {
        placeholder: '请选择通道策略',
        options: [
          { label: '限制通道', value: '1' },
          { label: '不限通道', value: '0' }
        ]
      }
    }
  ])

  function displayVipStatus(vip?: VipItem | null) {
    return displayAdminFixtureText(vip?.status_text || vip?.status_label, '未知状态')
  }

  const { columnChecks, columns } = useTableColumns<VipItem>(() => [
    { type: 'selection', width: 54, fixed: 'left' as const },
    ...(hasAuth('sort') && !isRecycleView.value
      ? [
          {
            prop: 'drag',
            label: '',
            width: 78,
            align: 'center' as const,
            fixed: 'left' as const,
            formatter: () => h('span', { class: 'vip-drag-handle' }, '拖拽')
          }
        ]
      : []),
    {
      prop: 'name',
      label: '套餐信息',
      minWidth: 260,
      formatter: (row) =>
        h('div', { class: 'vip-cell' }, [
          h('strong', { class: 'cell-title' }, row.name || `VIP #${row.id}`),
          h('p', { class: 'cell-sub' }, `编号：${row.id} / 排序：${row.sort}`),
          h('p', { class: 'cell-sub' }, `时长 ${row.duration_label} / 费率 ${row.fee_rate_display}`)
        ])
    },
    {
      prop: 'money',
      label: '价格',
      minWidth: 130,
      align: 'right' as const,
      formatter: (row) => formatAmount(row.money)
    },
    {
      prop: 'features',
      label: '能力',
      minWidth: 230,
      formatter: (row) =>
        h('div', { class: 'feature-tags' }, [
          h(
            ElTag,
            { type: row.profit_enabled ? 'success' : 'info', effect: 'plain', size: 'small' },
            () => (row.profit_enabled ? '支持分润' : '不参与分润')
          ),
          h(
            ElTag,
            { type: row.quota_enabled ? 'warning' : 'info', effect: 'plain', size: 'small' },
            () => (row.quota_enabled ? '额度限制已启用' : '不限额度')
          ),
          h(
            ElTag,
            { type: row.passage_enabled ? 'primary' : 'info', effect: 'plain', size: 'small' },
            () => (row.passage_enabled ? `限制通道 ${row.passage_count}` : '不限通道')
          )
        ])
    },
    {
      prop: 'merchant_count',
      label: '商户数',
      minWidth: 140,
      formatter: (row) => `${row.active_merchant_count} / ${row.merchant_count}`
    },
    {
      prop: 'status_label',
      label: '状态',
      width: 100,
      align: 'center' as const,
      formatter: (row) =>
        h(ElTag, { type: tagType(row.status_type), effect: 'light' }, () => displayVipStatus(row))
    },
    {
      prop: 'create_time',
      label: '创建时间',
      minWidth: 170,
      formatter: (row) => row.create_time || '--'
    },
    {
      prop: 'operation',
      label: '操作',
      width: 280,
      align: 'center' as const,
      fixed: 'right' as const,
      formatter: (row) => renderVipOperationButtons(row)
    }
  ])

  function renderVipOperationButtons(row: VipItem) {
    const actions = [
      h(ArtButtonTable, {
        type: 'view',
        title: '详情',
        onClick: () => openDetail(row)
      })
    ]

    if (row.deleted) {
      if (hasAuth('recycle')) {
        actions.push(
          h(ArtButtonTable, {
            icon: 'ri:restart-line',
            iconClass: 'bg-success/12 text-success',
            title: '恢复',
            onClick: () => handleRestoreVip(row)
          })
        )
      }

      return h('div', { class: 'table-actions' }, actions)
    }

    actions.push(
      h(ArtButtonTable, {
        type: 'edit',
        title: '编辑',
        onClick: async () => {
          await openDetail(row)
          openEditDialog()
        }
      })
    )

    if (hasAuth('remove')) {
      actions.push(
        h(ArtButtonTable, {
          type: 'delete',
          title: '删除',
          onClick: () => handleDeleteVip(row)
        })
      )
    }

    if (hasAuth('sort')) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:sort-desc',
          iconClass: 'bg-warning/12 text-warning',
          title: '排序',
          onClick: async () => {
            await openDetail(row)
            openSortDialog()
          }
        })
      )
    }

    return h('div', { class: 'table-actions' }, actions)
  }

  onMounted(() => {
    getVipList()
  })

  async function getVipList() {
    loading.value = true
    try {
      const response = await fetchGetVipList({
        current: pagination.current,
        size: pagination.size,
        keyword: searchForm.value.keyword,
        status: searchForm.value.status,
        passage_enabled: searchForm.value.passage_enabled
      })
      vipList.value = response.records
      pagination.current = response.current
      pagination.size = response.size
      pagination.total = response.total
      Object.assign(summary, response.summary)
    } catch {
      ElMessage.error('VIP 套餐加载失败')
    } finally {
      loading.value = false
    }
  }

  function handleSearch(params: Api.Vips.VipSearchParams) {
    pagination.current = 1
    clearVipSelection()
    searchForm.value = {
      keyword: params.keyword,
      status: params.status as string | undefined,
      passage_enabled: params.passage_enabled as string | undefined
    }
    getVipList()
  }

  function handleReset() {
    pagination.current = 1
    clearVipSelection()
    searchForm.value = {}
    getVipList()
  }

  function toggleRecycleView() {
    pagination.current = 1
    clearVipSelection()
    searchForm.value = {
      ...searchForm.value,
      status: isRecycleView.value ? undefined : '-1'
    }
    getVipList()
  }

  function handleSizeChange(size: number) {
    pagination.size = size
    pagination.current = 1
    clearVipSelection()
    getVipList()
  }

  function handleCurrentChange(current: number) {
    pagination.current = current
    clearVipSelection()
    getVipList()
  }

  function handleVipSelectionChange(selection: VipItem[]) {
    selectedVips.value = Array.isArray(selection) ? selection : []
  }

  async function handleVipDragEnd(event: { oldIndex?: number; newIndex?: number }) {
    if (isRecycleView.value) {
      return
    }

    const fromIndex = Number(event.oldIndex ?? -1)
    const toIndex = Number(event.newIndex ?? -1)

    if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex) {
      return
    }

    const visibleVipIds = vipList.value.map((item) => item.id)
    reorderingVips.value = true

    try {
      await fetchReorderVips({
        visible_vip_ids: visibleVipIds,
        from_index: fromIndex,
        to_index: toIndex
      })

      await getVipList()
      syncActiveVipFromList()
      ElMessage.success('VIP 套餐排序已更新。')
    } catch (error) {
      await getVipList()
      syncActiveVipFromList()
      throw error
    } finally {
      reorderingVips.value = false
    }
  }

  async function openDetail(row: VipItem) {
    detailVisible.value = true
    detailLoading.value = true
    activeVip.value = row
    editableVip.value = buildEditableFromVip(row)
    vipMerchants.value = []

    try {
      const response = await fetchGetVipDetail(row.id)
      applyVipDetail(response.item, response.editable, response.merchants)
    } catch {
      ElMessage.error('VIP 套餐详情加载失败')
    } finally {
      detailLoading.value = false
    }
  }

  async function openCreateDialog() {
    dialogMode.value = 'create'
    editVisible.value = true
    formPreparing.value = true
    syncEditForm(buildEmptyEditable())

    try {
      const response = await fetchGetVipTemplate()
      syncEditForm(response.editable)
    } catch {
      editVisible.value = false
      ElMessage.error('VIP 套餐创建模板加载失败')
    } finally {
      formPreparing.value = false
    }
  }

  function openEditDialog() {
    if (!activeVip.value) {
      return
    }

    if (activeVip.value.deleted) {
      ElMessage.warning('回收站中的 VIP 套餐请先恢复后再编辑。')
      return
    }

    dialogMode.value = 'edit'
    syncEditForm(editableVip.value || buildEditableFromVip(activeVip.value))
    editVisible.value = true
  }

  function openStatusDialog() {
    if (!activeVip.value) {
      return
    }

    if (activeVip.value.deleted) {
      ElMessage.warning('回收站中的 VIP 套餐请先恢复后再修改状态。')
      return
    }

    syncStatusForm(editableVip.value || buildEditableFromVip(activeVip.value))
    statusVisible.value = true
  }

  function openSortDialog() {
    if (!activeVip.value) {
      return
    }

    if (activeVip.value.deleted) {
      ElMessage.warning('回收站中的 VIP 套餐请先恢复后再调整排序。')
      return
    }

    syncVipSortForm(sortForm, editableVip.value?.sort ?? activeVip.value.sort ?? 0)
    sortVisible.value = true
  }

  async function submitForm() {
    const validationMessage = validateVipEditForm(editForm)
    if (validationMessage) {
      ElMessage.warning(validationMessage)
      return
    }

    const payload = buildVipPayloadFromForm(editForm)

    savingEdit.value = true
    try {
      const response =
        dialogMode.value === 'create'
          ? await fetchCreateVip(payload)
          : activeVip.value
            ? await fetchUpdateVip(activeVip.value.id, payload)
            : null

      if (!response) {
        ElMessage.warning('当前没有可编辑的 VIP 套餐')
        return
      }

      applyVipDetail(response.item, response.editable, response.merchants)
      await getVipList()
      editVisible.value = false

      if (dialogMode.value === 'create') {
        detailVisible.value = true
        ElMessage.success('VIP 套餐创建成功。')
      } else {
        ElMessage.success('VIP 套餐更新成功。')
      }
    } finally {
      savingEdit.value = false
    }
  }

  async function submitStatus() {
    if (!activeVip.value) {
      return
    }

    savingStatus.value = true
    try {
      const response = await fetchUpdateVipStatus(activeVip.value.id, {
        status: statusForm.status
      })

      applyVipDetail(response.item, response.editable, response.merchants)
      await getVipList()
      statusVisible.value = false
      ElMessage.success('VIP 套餐状态已更新')
    } finally {
      savingStatus.value = false
    }
  }

  async function submitSort() {
    if (!activeVip.value) {
      return
    }

    const validationMessage = validateVipSortForm(sortForm)
    if (validationMessage) {
      ElMessage.warning(validationMessage)
      return
    }

    savingSort.value = true
    try {
      const response = await fetchUpdateVipSort(activeVip.value.id, {
        sort: sortForm.sort
      })

      applyVipDetail(response.item, response.editable, response.merchants)
      await getVipList()
      sortVisible.value = false
    } finally {
      savingSort.value = false
    }
  }

  async function handleDeleteVip(row?: VipItem) {
    const target = row || activeVip.value
    if (!target) {
      return
    }

    if (target.deleted) {
      ElMessage.warning('该 VIP 套餐已在回收站中。')
      return
    }

    try {
      const response = await fetchGetVipDeleteAudit(target.id)
      const audit = response.audit
      const title = target.name || `VIP #${target.id}`

      if (!audit.can_delete) {
        await ElMessageBox.alert(buildVipDeleteBlockedMessage(audit, title), '删除受限', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildVipDeletePromptMessage(audit, title),
        '删除 VIP 套餐',
        {
          confirmButtonText: '删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以继续。`
        }
      )

      const deleteResponse = await fetchDeleteVip(target.id, {
        confirmation_phrase: String(value || '')
      })

      if (activeVip.value?.id === target.id) {
        detailVisible.value = false
        activeVip.value = null
        editableVip.value = null
        vipMerchants.value = []
      }

      selectedVips.value = selectedVips.value.filter((item) => item.id !== target.id)
      await getVipList()
      ElMessage.success(
        `VIP 套餐 ${deleteResponse.deleted_vip_name || title} 已移入回收站，共影响 ${deleteResponse.audit.summary.delete_row_count} 条数据。`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      throw error
    }
  }

  async function handleRestoreVip(row?: VipItem) {
    const target = row || activeVip.value
    if (!target) {
      return
    }

    if (!target.deleted) {
      ElMessage.warning('该 VIP 套餐当前已是启用状态。')
      return
    }

    try {
      await ElMessageBox.confirm(
        `确认将 ${target.name || `VIP #${target.id}`} 恢复到启用列表吗？`,
        '恢复 VIP 套餐',
        {
          confirmButtonText: '恢复',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchRestoreVip(target.id)
      applyVipDetail(response.item, response.editable, response.merchants)
      clearVipSelection()
      await getVipList()

      if (isRecycleView.value) {
        detailVisible.value = false
        activeVip.value = null
        editableVip.value = null
        vipMerchants.value = []
      }

      ElMessage.success(`VIP 套餐 ${response.restored_vip_name || target.name} 已恢复。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      throw error
    }
  }

  async function handleBatchDeleteVips() {
    if (selectedVips.value.length === 0) {
      ElMessage.warning('请至少选择一个 VIP 套餐。')
      return
    }

    const vipIds = selectedVips.value.map((item) => item.id)

    try {
      const response = await fetchAuditVipBatchDelete({
        vip_ids: vipIds
      })
      const audit = response.audit

      if (!audit.can_delete_all) {
        await ElMessageBox.alert(buildVipBatchDeleteBlockedMessage(audit), '批量删除受限', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildVipBatchDeletePromptMessage(audit),
        '批量删除 VIP 套餐',
        {
          confirmButtonText: '批量删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以继续。`
        }
      )

      const deleteResponse = await fetchBatchDeleteVips({
        vip_ids: vipIds,
        confirmation_phrase: String(value || '')
      })

      if (activeVip.value && deleteResponse.deleted_vip_ids.includes(activeVip.value.id)) {
        detailVisible.value = false
        activeVip.value = null
        editableVip.value = null
        vipMerchants.value = []
      }

      clearVipSelection()
      await getVipList()
      ElMessage.success(
        `已将 ${deleteResponse.deleted_count} 个 VIP 套餐移入回收站，共影响 ${deleteResponse.audit.summary.delete_row_count} 条数据。`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      throw error
    }
  }

  async function handleBatchRestoreVips() {
    const recycleSelection = selectedVips.value.filter((item) => item.deleted)
    if (recycleSelection.length === 0) {
      ElMessage.warning('请至少选择一个回收站中的 VIP 套餐。')
      return
    }

    const vipIds = recycleSelection.map((item) => item.id)

    try {
      await ElMessageBox.confirm(
        `确认将 ${vipIds.length} 个 VIP 套餐恢复到启用列表吗？`,
        '批量恢复 VIP 套餐',
        {
          confirmButtonText: '批量恢复',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchBatchRestoreVips({
        vip_ids: vipIds
      })

      clearVipSelection()
      await getVipList()

      if (activeVip.value && response.restored_vip_ids.includes(activeVip.value.id)) {
        detailVisible.value = false
        activeVip.value = null
        editableVip.value = null
        vipMerchants.value = []
      }

      ElMessage.success(`已恢复 ${response.restored_count} 个 VIP 套餐。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      throw error
    }
  }

  function applyVipDetail(item: VipItem, editable: VipEditable, merchants: VipMerchantItem[] = []) {
    activeVip.value = item
    editableVip.value = editable || buildEditableFromVip(item)
    vipMerchants.value = merchants
    syncEditForm(editableVip.value)
    syncStatusForm(editableVip.value)
    syncVipSortForm(sortForm, editableVip.value.sort)
  }

  function syncActiveVipFromList() {
    if (!activeVip.value) {
      return
    }

    const latest = vipList.value.find((item) => item.id === activeVip.value?.id)
    if (!latest) {
      return
    }

    activeVip.value = {
      ...activeVip.value,
      ...latest
    }
  }

  function buildEmptyEditable(): VipEditable {
    return {
      name: '',
      money: '0.00',
      vip_days: 0,
      fee_rate: '',
      sort: 0,
      status: 1,
      profit_enabled: 0,
      add_channel_enabled: 0,
      add_channel_num: 0,
      quota_enabled: 0,
      today_quota: '',
      month_quota: '',
      passage_enabled: 0,
      passage_codes: [],
      passage_option_groups: []
    }
  }

  function buildEditableFromVip(item: VipItem): VipEditable {
    return {
      name: item.name || '',
      money: item.money_display || String(item.money || 0),
      vip_days: Number(item.vip_days || 0),
      fee_rate: item.fee_rate !== null ? String(item.fee_rate) : '',
      sort: Number(item.sort || 0),
      status: Number(item.status || 0),
      profit_enabled: item.profit_enabled ? 1 : 0,
      add_channel_enabled: item.add_channel_enabled ? 1 : 0,
      add_channel_num: Number(item.add_channel_num || 0),
      quota_enabled: item.quota_enabled ? 1 : 0,
      today_quota: item.today_quota || '',
      month_quota: item.month_quota || '',
      passage_enabled: item.passage_enabled ? 1 : 0,
      passage_codes: item.passage_codes || [],
      passage_option_groups: editableVip.value?.passage_option_groups || []
    }
  }

  function syncEditForm(editable: VipEditable) {
    formPassageOptionGroups.value = syncVipEditFormFromEditable(editForm, editable)
  }

  function syncStatusForm(editable: VipEditable) {
    syncVipStatusFormFromEditable(statusForm, editable)
  }

  function syncEditFormState(form: VipEditFormState) {
    assignVipEditFormState(editForm, form)
  }

  function syncStatusFormState(form: VipStatusFormState) {
    assignVipStatusFormState(statusForm, form)
  }

  function syncSortFormState(form: VipSortFormState) {
    assignVipSortFormState(sortForm, form)
  }

  function clearVipSelection() {
    selectedVips.value = []
    tableRef.value?.elTableRef?.clearSelection?.()
  }

  function buildVipDeleteBlockedMessage(audit: VipDeleteAudit, title = 'VIP 套餐') {
    const merchantLines = audit.linked_merchants.map(
      (merchant) => merchant.display || `商户 #${merchant.id}`
    )

    return [
      `${title} 暂时无法删除。`,
      ...audit.blocking_reasons,
      '',
      `关联商户数：${audit.summary.linked_merchant_count}`,
      `有效关联商户：${audit.summary.active_linked_merchant_count}`,
      `已过期关联商户：${audit.summary.expired_linked_merchant_count}`,
      ...(merchantLines.length > 0 ? ['', '关联商户：', ...merchantLines] : []),
      ...(audit.warnings.length > 0 ? ['', ...audit.warnings] : [])
    ].join('\n')
  }

  function buildVipDeletePromptMessage(audit: VipDeleteAudit, title = 'VIP 套餐') {
    return [
      `${title}: ${audit.vip_name || `VIP #${audit.vip_id}`}`,
      `将删除数据行：${audit.summary.delete_row_count}`,
      `关联商户数：${audit.summary.linked_merchant_count}`,
      ...(audit.warnings.length > 0 ? ['', ...audit.warnings] : []),
      '',
      `请输入 ${audit.confirmation_phrase} 以继续。`
    ].join('\n')
  }

  function buildVipBatchDeleteBlockedMessage(audit: VipBatchDeleteAudit) {
    const blockedLines = audit.items
      .filter((item) => !item.can_delete)
      .map((item) => {
        const label = item.vip_name ? `${item.vip_name} (#${item.vip_id})` : `VIP #${item.vip_id}`
        const reason = item.blocking_reasons[0] || '该 VIP 套餐当前无法删除。'
        return `${label}: ${reason}`
      })

    return [
      `已选择 VIP 套餐：${audit.summary.requested_count}`,
      `可删除：${audit.summary.deletable_count}`,
      `受阻：${audit.summary.blocked_count}`,
      `缺失：${audit.summary.missing_count}`,
      '',
      ...audit.warnings,
      ...(blockedLines.length > 0 ? ['', '受阻套餐：', ...blockedLines] : [])
    ].join('\n')
  }

  function buildVipBatchDeletePromptMessage(audit: VipBatchDeleteAudit) {
    const vipLines = audit.items
      .filter((item) => item.can_delete)
      .map((item) => (item.vip_name ? `${item.vip_name} (#${item.vip_id})` : `VIP #${item.vip_id}`))

    return [
      `已选择 VIP 套餐：${audit.summary.requested_count}`,
      `将删除数据行：${audit.summary.delete_row_count}`,
      `当前可删除：${audit.summary.deletable_count}`,
      '',
      '套餐列表：',
      ...vipLines,
      ...(audit.warnings.length > 0 ? ['', ...audit.warnings] : []),
      '',
      `请输入 ${audit.confirmation_phrase} 以继续。`
    ].join('\n')
  }

  function formatAmount(value: number, digits = 2) {
    return Number(value || 0).toLocaleString('zh-CN', {
      minimumFractionDigits: digits,
      maximumFractionDigits: digits
    })
  }

  function tagType(
    value: string
  ): 'success' | 'warning' | 'info' | 'danger' | 'primary' | undefined {
    if (value === 'success' || value === 'warning' || value === 'info' || value === 'primary') {
      return value
    }
    return 'info'
  }

  function escapeRegExp(value: string) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  }

  function isDialogCancel(error: unknown) {
    return error === 'cancel' || error === 'close'
  }
</script>

<style scoped lang="scss">
  .vip-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
    --vip-drag-border: #cbd5e1;
    --vip-drag-bg: rgb(248 250 252 / 0.9);
    --vip-drag-text: #475569;
    --vip-drawer-border: var(--el-border-color-lighter);
    --vip-drawer-bg: rgb(248 250 252 / 0.82);
  }

  :global(html.dark .vip-page ){
    --vip-drag-border: rgb(71 85 105 / 0.68);
    --vip-drag-bg: rgb(15 23 42 / 0.82);
    --vip-drag-text: #cbd5e1;
    --vip-drawer-border: rgb(71 85 105 / 0.42);
    --vip-drawer-bg: rgb(15 23 42 / 0.84);
  }

  .vip-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .cell-title {
    color: var(--el-text-color-primary);
    font-size: 14px;
    word-break: break-all;
  }

  .cell-sub {
    margin: 0;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 1.6;
    word-break: break-all;
  }

  .feature-tags,
  .merchant-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .vip-drag-handle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 44px;
    padding: 4px 10px;
    border: 1px dashed var(--vip-drag-border);
    border-radius: 999px;
    background: var(--vip-drag-bg);
    color: var(--vip-drag-text);
    cursor: move;
    font-size: 12px;
    line-height: 1;
    user-select: none;
  }

  .vip-detail {
    min-height: 260px;
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
  .detail-hero-copy span {
    margin: 0;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 1.6;
  }

  .detail-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: flex-end;
  }

  .drawer-section {
    margin-bottom: 24px;
  }

  .drawer-section h4 {
    margin: 0 0 12px;
    color: var(--el-text-color-primary);
    font-size: 15px;
  }

  .drawer-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .drawer-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 14px 16px;
    border: 1px solid var(--vip-drawer-border);
    border-radius: 14px;
    background: var(--vip-drawer-bg);
  }

  .drawer-item span {
    color: var(--el-text-color-secondary);
    font-size: 12px;
  }

  .drawer-item strong {
    color: var(--el-text-color-primary);
    word-break: break-all;
  }

  @media (width <= 991px) {
    .detail-hero {
      flex-direction: column;
    }

    .detail-hero-actions {
      justify-content: flex-start;
    }

    .drawer-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
