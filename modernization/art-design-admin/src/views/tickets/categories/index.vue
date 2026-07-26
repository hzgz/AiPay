<template>
  <div class="ticket-category-page art-full-height">
    <ArtSearchBar
      v-model="searchForm"
      :items="searchItems"
      :showExpand="false"
      @search="handleSearch"
      @reset="handleReset"
    />

    <ElCard class="art-table-card">
      <ArtTableHeader
        v-model:columns="columnChecks"
        :loading="loading"
        @refresh="getCategoryList"
      >
        <template #left>
          <ElSpace wrap>
            <ElTag effect="plain">分类 {{ pagination.total }}</ElTag>
            <ElTag type="success" effect="plain">启用 {{ summary.enabled_count }}</ElTag>
            <ElTag type="info" effect="plain">停用 {{ summary.disabled_count }}</ElTag>
            <ElTag type="primary" effect="plain">已关联 {{ summary.linked_count }}</ElTag>
            <ElTag effect="plain">未使用 {{ summary.unused_count }}</ElTag>
            <ElTag type="warning" effect="plain">未结工单 {{ summary.open_ticket_count }}</ElTag>
            <ElButton v-if="hasTicketCategoryCreateAuth" type="primary" @click="openCreateDialog">
              新增分类
            </ElButton>
            <ElButton
              v-if="hasTicketCategoryBatchDeleteAuth"
              plain
              type="danger"
              :disabled="selectedCategories.length === 0"
              @click="handleBatchDeleteCategories"
            >
              批量删除
            </ElButton>
            <ElTag v-if="selectedCategories.length > 0" type="danger" effect="plain">
              已选 {{ selectedCategories.length }}
            </ElTag>
            <ElTag type="info" effect="plain">工单分类维护</ElTag>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        ref="tableRef"
        :loading="loading"
        :data="categoryList"
        :columns="columns"
        :pagination="pagination"
        row-key="id"
        reserve-selection
        @selection-change="handleCategorySelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <ElDrawer
      v-model="detailVisible"
      size="760px"
      destroy-on-close
      :title="activeCategory ? `${displayCategoryName(activeCategory)} / #${activeCategory.id}` : '工单分类详情'"
    >
      <div v-loading="detailLoading" class="ticket-category-detail">
        <template v-if="activeCategory">
          <div class="detail-hero">
            <div class="detail-hero-copy">
              <h3>{{ displayCategoryName(activeCategory) }}</h3>
              <p>{{ displayCategoryStatus(activeCategory) }} / {{ displayCategoryLinkStatus(activeCategory) }}</p>
              <span>{{ displayAdminFixtureText(activeCategory.delete_guard_reason) }}</span>
            </div>
            <div class="detail-hero-actions">
              <ElButton v-if="canEditCategory(activeCategory)" plain @click="openEditDialog()">
                编辑
              </ElButton>
              <ElButton
                v-if="canToggleStatusCategory(activeCategory)"
                :type="activeCategory.status === 1 ? 'warning' : 'success'"
                plain
                @click="handleToggleStatusCategory()"
              >
                {{ activeCategory.status === 1 ? '停用' : '启用' }}
              </ElButton>
              <ElButton v-if="canDeleteCategory(activeCategory)" type="danger" plain @click="handleDeleteCategory()">
                删除
              </ElButton>
            </div>
          </div>

          <div class="drawer-section">
            <ElDescriptions :column="2" border>
              <ElDescriptionsItem label="分类名称">
                {{ displayCategoryName(activeCategory) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="状态">
                {{ displayCategoryStatus(activeCategory) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="排序值">
                {{ activeCategory.sort || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="关联状态">
                {{ displayCategoryLinkStatus(activeCategory) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="创建时间">
                {{ activeCategory.create_time || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="更新时间">
                {{ activeCategory.update_time || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="分类 ID">
                {{ activeCategory.id }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="原始状态值">
                {{ activeCategory.status ?? '--' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>工单关联情况</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="工单总数">
                {{ activeCategory.ticket_count }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="未结工单">
                {{ activeCategory.open_ticket_count }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="已回复工单">
                {{ activeCategory.replied_ticket_count }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="最近工单时间">
                {{ activeCategory.latest_ticket_time || '--' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <ElAlert
            type="info"
            :closable="false"
            show-icon
            title="删除为硬删除。凡是仍有关联工单的分类，都必须先清空或重新分配工单后才能删除。"
          />
        </template>
      </div>
    </ElDrawer>

    <ElDialog
      v-model="createVisible"
      width="720px"
      destroy-on-close
      align-center
      title="新增工单分类"
    >
      <ElForm label-position="top">
        <ElFormItem label="分类名称">
          <ElInput
            v-model="createForm.name"
            maxlength="255"
            placeholder="请输入分类名称"
          />
        </ElFormItem>
        <ElFormItem label="排序值">
          <ElInput
            v-model="createForm.sort"
            maxlength="9"
            placeholder="可选，填写整数排序值"
          />
        </ElFormItem>
        <ElFormItem label="状态">
          <ElSelect v-model="createForm.status" placeholder="请选择状态">
            <ElOption label="启用" value="1" />
            <ElOption label="停用" value="0" />
          </ElSelect>
        </ElFormItem>
        <ElAlert
          type="info"
          :closable="false"
          show-icon
          title="当前阶段工单分类仍为硬删除。有关联工单的分类会继续被保护，直到相关工单被处理完毕。"
        />
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="createVisible = false">取消</ElButton>
          <ElButton
            v-if="hasTicketCategoryCreateAuth"
            type="primary"
            :loading="creatingCategory"
            @click="submitCreateCategory"
          >
            创建分类
          </ElButton>
        </div>
      </template>
    </ElDialog>

    <ElDialog
      v-model="editVisible"
      width="720px"
      destroy-on-close
      align-center
      title="编辑工单分类"
    >
      <ElForm label-position="top">
        <ElFormItem label="分类名称">
          <ElInput
            v-model="editForm.name"
            maxlength="255"
            placeholder="请输入分类名称"
          />
        </ElFormItem>
        <ElFormItem label="排序值">
          <ElInput
            v-model="editForm.sort"
            maxlength="9"
            placeholder="可选，填写整数排序值"
          />
        </ElFormItem>
        <ElFormItem label="状态">
          <ElSelect v-model="editForm.status" placeholder="请选择状态">
            <ElOption label="启用" value="1" />
            <ElOption label="停用" value="0" />
          </ElSelect>
        </ElFormItem>
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="editVisible = false">取消</ElButton>
          <ElButton
            v-if="hasTicketCategoryEditAuth"
            type="primary"
            :loading="savingEdit"
            @click="submitEditCategory"
          >
            保存修改
          </ElButton>
        </div>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox, ElTag } from 'element-plus'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import ArtButtonTable from '@/components/core/forms/artButtonTable/index.vue'
  import { useAuth } from '@/hooks'
  import {
    fetchAuditTicketCategoryBatchDelete,
    fetchBatchDeleteTicketCategories,
    fetchCreateTicketCategory,
    fetchDeleteTicketCategory,
    fetchGetTicketCategoryDeleteAudit,
    fetchGetTicketCategoryDetail,
    fetchGetTicketCategoryList,
    fetchUpdateTicketCategory,
    fetchUpdateTicketCategoryStatus
  } from '@/api/tickets'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'

  defineOptions({ name: 'TicketCategories' })

  type TicketCategoryItem = Api.Tickets.TicketCategoryListItem
  type TicketCategorySummary = Api.Tickets.TicketCategorySummary

  const tableRef = ref<{ elTableRef?: { clearSelection?: () => void } } | null>(null)
  const loading = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const createVisible = ref(false)
  const editVisible = ref(false)
  const creatingCategory = ref(false)
  const savingEdit = ref(false)
  const categoryList = ref<TicketCategoryItem[]>([])
  const selectedCategories = ref<TicketCategoryItem[]>([])
  const activeCategory = ref<TicketCategoryItem | null>(null)
  const editCategoryId = ref<number | null>(null)
  const { hasAuth } = useAuth()
  const pagination = reactive({
    current: 1,
    size: 20,
    total: 0
  })
  const summary = reactive<TicketCategorySummary>(emptySummary())
  const searchForm = ref<{
    keyword?: string
    status?: string
  }>({})
  const createForm = reactive(emptyWriteForm())
  const editForm = reactive(emptyWriteForm())
  const hasTicketCategoryCreateAuth = computed(() => hasAuth('add'))
  const hasTicketCategoryEditAuth = computed(() => hasAuth('edit'))
  const hasTicketCategoryStatusAuth = computed(() => hasAuth('status'))
  const hasTicketCategoryDeleteAuth = computed(() => hasAuth('remove'))
  const hasTicketCategoryBatchDeleteAuth = computed(() => hasAuth('batchRemove'))

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '搜索分类编号、名称或排序值'
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

  const { columnChecks, columns } = useTableColumns<TicketCategoryItem>(() => [
    { type: 'selection', width: 54, fixed: 'left' as const },
    { type: 'globalIndex', width: 70, label: '序号' },
    {
      prop: 'name_label',
      label: '分类',
      minWidth: 240,
      formatter: (row) =>
        h('div', { class: 'category-cell' }, [
          h('strong', { class: 'cell-title' }, displayCategoryName(row)),
          h('p', { class: 'cell-sub' }, `编号：${row.id}`),
          h('p', { class: 'cell-sub' }, displayCategoryLinkStatus(row))
        ])
    },
    {
      prop: 'sort',
      label: '排序',
      width: 110,
      align: 'center' as const,
      formatter: (row) => row.sort || '--'
    },
    {
      prop: 'status_label',
      label: '状态',
      width: 120,
      align: 'center' as const,
      formatter: (row) =>
        h(ElTag, { type: tagType(row.status_type), effect: 'light' }, () => displayCategoryStatus(row))
    },
    {
      prop: 'ticket_count',
      label: '关联工单',
      minWidth: 180,
      align: 'center' as const,
      formatter: (row) =>
        h('div', { class: 'count-cell' }, [
          h('strong', { class: 'cell-title' }, row.ticket_count),
          h('p', { class: 'cell-sub' }, `未结 ${row.open_ticket_count}`),
          h('p', { class: 'cell-sub' }, `已回复 ${row.replied_ticket_count}`)
        ])
    },
    {
      prop: 'latest_ticket_time',
      label: '最近工单',
      minWidth: 170,
      formatter: (row) => row.latest_ticket_time || '--'
    },
    {
      prop: 'update_time',
      label: '更新时间',
      minWidth: 170,
      formatter: (row) => row.update_time || '--'
    },
    {
      prop: 'operation',
      label: '操作',
      width: 320,
      align: 'center' as const,
      fixed: 'right' as const,
      formatter: (row) => renderCategoryOperationButtons(row)
    }
  ])

  onMounted(() => {
    getCategoryList()
  })

  function displayCategoryName(category?: Partial<TicketCategoryItem> | null) {
    return displayAdminFixtureText(category?.name_label || category?.id, '未命名分类')
  }

  function displayCategoryStatus(category?: Partial<TicketCategoryItem> | null, fallback = '--') {
    return displayAdminFixtureText(category?.status_text || category?.status_label, fallback)
  }

  function displayCategoryLinkStatus(
    category?: Partial<TicketCategoryItem> | null,
    fallback = '--'
  ) {
    return displayAdminFixtureText(category?.link_status_text || category?.link_status_label, fallback)
  }

  function renderCategoryOperationButtons(row: TicketCategoryItem) {
    const actions = [
      h(ArtButtonTable, {
        type: 'view',
        title: '详情',
        onClick: () => openDetail(row)
      })
    ]

    if (canEditCategory(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:pencil-line',
          iconClass: 'bg-primary/12 text-primary',
          title: '编辑',
          onClick: () => openEditDialog(row)
        })
      )
    }

    if (canToggleStatusCategory(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: row.status === 1 ? 'ri:forbid-line' : 'ri:check-line',
          iconClass:
            row.status === 1 ? 'bg-warning/12 text-warning' : 'bg-success/12 text-success',
          title: row.status === 1 ? '停用' : '启用',
          onClick: () => handleToggleStatusCategory(row)
        })
      )
    }

    if (canDeleteCategory(row)) {
      actions.push(
        h(ArtButtonTable, {
          type: 'delete',
          title: '删除',
          onClick: () => handleDeleteCategory(row)
        })
      )
    }

    return h('div', { class: 'table-actions' }, actions)
  }

  function canEditCategory(item?: TicketCategoryItem | null) {
    return Boolean(item && hasTicketCategoryEditAuth.value)
  }

  function canToggleStatusCategory(item?: TicketCategoryItem | null) {
    return Boolean(item && hasTicketCategoryStatusAuth.value)
  }

  function canDeleteCategory(item?: TicketCategoryItem | null) {
    return Boolean(item && hasTicketCategoryDeleteAuth.value)
  }

  async function getCategoryList() {
    loading.value = true
    try {
      const response = await fetchGetTicketCategoryList({
        current: pagination.current,
        size: pagination.size,
        keyword: searchForm.value.keyword,
        status: searchForm.value.status
      })
      categoryList.value = response.records
      pagination.current = response.current
      pagination.size = response.size
      pagination.total = response.total
      Object.assign(summary, response.summary || emptySummary())
    } catch (_error) {
      ElMessage.error('加载工单分类列表失败。')
    } finally {
      loading.value = false
    }
  }

  function handleSearch(params: Api.Tickets.TicketCategorySearchParams) {
    pagination.current = 1
    clearCategorySelection()
    searchForm.value = {
      keyword: params.keyword,
      status: params.status as string | undefined
    }
    getCategoryList()
  }

  function handleReset() {
    pagination.current = 1
    clearCategorySelection()
    searchForm.value = {}
    getCategoryList()
  }

  function handleSizeChange(size: number) {
    pagination.size = size
    pagination.current = 1
    clearCategorySelection()
    getCategoryList()
  }

  function handleCurrentChange(current: number) {
    pagination.current = current
    clearCategorySelection()
    getCategoryList()
  }

  function handleCategorySelectionChange(rows: TicketCategoryItem[]) {
    selectedCategories.value = rows
  }

  async function openDetail(row: TicketCategoryItem) {
    detailVisible.value = true
    detailLoading.value = true
    activeCategory.value = row

    try {
      const response = await fetchGetTicketCategoryDetail(row.id)
      activeCategory.value = response.item
    } catch (_error) {
      ElMessage.error('加载工单分类详情失败。')
    } finally {
      detailLoading.value = false
    }
  }

  function openCreateDialog() {
    if (!hasTicketCategoryCreateAuth.value) {
      ElMessage.warning('您没有创建工单分类的权限。')
      return
    }

    Object.assign(createForm, emptyWriteForm())
    createVisible.value = true
  }

  function openEditDialog(row?: TicketCategoryItem) {
    const target = row || activeCategory.value
    if (!target) {
      return
    }

    if (!hasTicketCategoryEditAuth.value) {
      ElMessage.warning('您没有编辑工单分类的权限。')
      return
    }

    editCategoryId.value = target.id
    Object.assign(editForm, {
      name: target.name || '',
      sort: target.sort || '',
      status: String(target.status ?? 1)
    })
    editVisible.value = true
  }

  async function submitCreateCategory() {
    if (!hasTicketCategoryCreateAuth.value) {
      ElMessage.warning('您没有创建工单分类的权限。')
      return
    }

    const payload = buildWritePayload(createForm)
    if (!payload.name) {
      ElMessage.warning('请输入分类名称。')
      return
    }

    creatingCategory.value = true
    try {
      const response = await fetchCreateTicketCategory(payload)
      createVisible.value = false
      clearCategorySelection()
      await getCategoryList()
      ElMessage.success(
        `工单分类 ${response.created_category_label || `#${response.created_category_id}`} 已创建。`
      )
    } catch (_error) {
      ElMessage.error('创建工单分类失败。')
    } finally {
      creatingCategory.value = false
    }
  }

  async function submitEditCategory() {
    if (!hasTicketCategoryEditAuth.value) {
      ElMessage.warning('您没有编辑工单分类的权限。')
      return
    }

    if (!editCategoryId.value) {
      ElMessage.warning('当前没有可编辑的工单分类。')
      return
    }

    const payload = buildWritePayload(editForm)
    if (!payload.name) {
      ElMessage.warning('请输入分类名称。')
      return
    }

    savingEdit.value = true
    try {
      const response = await fetchUpdateTicketCategory(editCategoryId.value, payload)
      editVisible.value = false
      syncActiveCategory(response.item)
      clearCategorySelection()
      await getCategoryList()
      ElMessage.success(
        `工单分类 ${response.updated_category_label || `#${response.updated_category_id}`} 已更新。`
      )
    } catch (_error) {
      ElMessage.error('更新工单分类失败。')
    } finally {
      savingEdit.value = false
    }
  }

  async function handleToggleStatusCategory(row?: TicketCategoryItem) {
    const target = row || activeCategory.value
    if (!target) {
      return
    }

    if (!hasTicketCategoryStatusAuth.value) {
      ElMessage.warning('您没有修改工单分类状态的权限。')
      return
    }

    const nextStatus = target.status === 1 ? 0 : 1
    const actionLabel = nextStatus === 1 ? '启用' : '停用'

    try {
      await ElMessageBox.confirm(
        `${actionLabel} ${displayCategoryName(target)}？`,
        `${actionLabel}工单分类`,
        {
          confirmButtonText: actionLabel,
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchUpdateTicketCategoryStatus(target.id, {
        status: nextStatus
      })
      syncActiveCategory(response.item)
      clearCategorySelection()
      await getCategoryList()
      ElMessage.success(
        `工单分类 ${displayAdminFixtureText(response.updated_category_label, displayCategoryName(target))} 状态已更新为 ${displayAdminFixtureText(response.status_text || response.status_label)}。`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('更新工单分类状态失败。')
    }
  }

  async function handleDeleteCategory(row?: TicketCategoryItem) {
    const target = row || activeCategory.value
    if (!target) {
      return
    }

    if (!hasTicketCategoryDeleteAuth.value) {
      ElMessage.warning('您没有删除工单分类的权限。')
      return
    }

    try {
      const response = await fetchGetTicketCategoryDeleteAudit(target.id)
      const audit = response.audit
      const title = displayCategoryName(target)

      if (!audit.can_delete) {
        await ElMessageBox.alert(buildCategoryDeleteBlockedMessage(audit, title), '删除受限', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildCategoryDeletePromptMessage(audit, title),
        '删除工单分类',
        {
          confirmButtonText: '删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以继续。`
        }
      )

      const deleteResponse = await fetchDeleteTicketCategory(target.id, {
        confirmation_phrase: String(value || '')
      })

      if (activeCategory.value?.id === target.id) {
        detailVisible.value = false
        activeCategory.value = null
      }
      if (editCategoryId.value === target.id) {
        editVisible.value = false
        editCategoryId.value = null
      }

      clearCategorySelection()
      await getCategoryList()
      ElMessage.success(
        `工单分类 ${displayAdminFixtureText(deleteResponse.deleted_category_label, title)} 已永久删除。`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('删除工单分类失败。')
    }
  }

  async function handleBatchDeleteCategories() {
    if (!hasTicketCategoryBatchDeleteAuth.value) {
      ElMessage.warning('您没有批量删除工单分类的权限。')
      return
    }

    if (selectedCategories.value.length === 0) {
      ElMessage.warning('请至少选择一条工单分类。')
      return
    }

    const categoryIds = selectedCategories.value.map((item) => item.id)

    try {
      const response = await fetchAuditTicketCategoryBatchDelete({
        category_ids: categoryIds
      })
      const audit = response.audit

      if (!audit.can_delete_all) {
        await ElMessageBox.alert(
          buildCategoryBatchDeleteBlockedMessage(audit),
          '批量删除受限',
          {
            type: 'warning',
            confirmButtonText: '知道了'
          }
        )
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildCategoryBatchDeletePromptMessage(audit),
        '批量删除工单分类',
        {
          confirmButtonText: '批量删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以继续。`
        }
      )

      const deleteResponse = await fetchBatchDeleteTicketCategories({
        category_ids: categoryIds,
        confirmation_phrase: String(value || '')
      })

      if (
        activeCategory.value &&
        deleteResponse.deleted_category_ids.includes(activeCategory.value.id)
      ) {
        detailVisible.value = false
        activeCategory.value = null
      }
      if (
        editCategoryId.value &&
        deleteResponse.deleted_category_ids.includes(editCategoryId.value)
      ) {
        editVisible.value = false
        editCategoryId.value = null
      }

      clearCategorySelection()
      await getCategoryList()
      ElMessage.success(`已永久删除 ${deleteResponse.deleted_count} 条工单分类记录。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('批量删除工单分类失败。')
    }
  }

  function syncActiveCategory(item: TicketCategoryItem | null) {
    if (!item) {
      return
    }

    if (activeCategory.value?.id === item.id) {
      activeCategory.value = item
    }
  }

  function clearCategorySelection() {
    selectedCategories.value = []
    tableRef.value?.elTableRef?.clearSelection?.()
  }

  function emptySummary(): TicketCategorySummary {
    return {
      total_count: 0,
      enabled_count: 0,
      disabled_count: 0,
      linked_count: 0,
      unused_count: 0,
      open_ticket_count: 0
    }
  }

  function emptyWriteForm() {
    return {
      name: '',
      sort: '',
      status: '1'
    }
  }

  function buildWritePayload(form: { name: string; sort: string; status: string }) {
    const payload: Api.Tickets.TicketCategoryWritePayload = {
      name: normalizeInput(form.name),
      status: form.status
    }

    const sort = normalizeInput(form.sort)
    if (sort !== '') {
      payload.sort = sort
    }

    return payload
  }

  function buildCategoryDeleteBlockedMessage(
    audit: Api.Tickets.TicketCategoryDeleteAudit,
    title: string
  ) {
    return [
      `${title} 当前暂不可删除。`,
      '',
      ...audit.blocking_reasons.map((item) => `- ${item}`),
      '',
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function buildCategoryDeletePromptMessage(
    audit: Api.Tickets.TicketCategoryDeleteAudit,
    title: string
  ) {
    return [
      `${title} 将被永久删除。`,
      '',
      `请输入 ${audit.confirmation_phrase} 以确认永久删除。`,
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function buildCategoryBatchDeleteBlockedMessage(
    audit: Api.Tickets.TicketCategoryBatchDeleteAudit
  ) {
    const blocked = audit.items.filter((item) => !item.can_delete)
    return [
      '所选工单分类当前还不能批量删除。',
      '',
      ...blocked.slice(0, 6).map((item) => {
        const label = displayAdminFixtureText(item.category_label, `分类 #${item.category_id}`)
        const reasons = item.blocking_reasons.map((reason) => displayAdminFixtureText(reason)).join(' ')
        return `- ${label}: ${reasons}`
      }),
      '',
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function buildCategoryBatchDeletePromptMessage(
    audit: Api.Tickets.TicketCategoryBatchDeleteAudit
  ) {
    return [
      `即将永久删除 ${audit.summary.deletable_count} 条工单分类记录。`,
      '',
      `请输入 ${audit.confirmation_phrase} 以确认永久删除。`,
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function normalizeInput(value: string | undefined) {
    return String(value || '').trim()
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

  function escapeRegExp(value: string) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  }

  function isDialogCancel(error: unknown) {
    return error === 'cancel' || error === 'close'
  }
</script>

<style scoped lang="scss">
  .ticket-category-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
    --detail-hero-bg: linear-gradient(135deg, rgb(248 250 252 / 0.96), rgb(241 245 249 / 0.92));
    --detail-card-border: var(--el-border-color-lighter);
    --detail-title-color: #0f172a;
    --detail-text-color: #475569;
    --detail-muted-color: #64748b;
  }

  :global(html.dark .ticket-category-page ){
    --detail-hero-bg: linear-gradient(135deg, rgb(30 41 59 / 0.96), rgb(15 23 42 / 0.94));
    --detail-card-border: rgb(71 85 105 / 0.42);
    --detail-title-color: #e2e8f0;
    --detail-text-color: #cbd5e1;
    --detail-muted-color: #94a3b8;
  }

  .category-cell,
  .count-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .cell-title {
    color: var(--detail-title-color);
    font-size: 14px;
    word-break: break-all;
  }

  .cell-sub {
    margin: 0;
    color: var(--detail-muted-color);
    font-size: 12px;
    line-height: 1.6;
    word-break: break-all;
  }

  .ticket-category-detail {
    min-height: 240px;
  }

  .detail-hero {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
    padding: 20px;
    border: 1px solid var(--detail-card-border);
    border-radius: 18px;
    background: var(--detail-hero-bg);
  }

  .detail-hero-copy {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .detail-hero-copy h3 {
    margin: 0;
    color: var(--detail-title-color);
    font-size: 20px;
  }

  .detail-hero-copy p,
  .detail-hero-copy span {
    margin: 0;
    color: var(--detail-text-color);
    line-height: 1.7;
  }

  .detail-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-content: flex-start;
    justify-content: flex-end;
  }

  .drawer-section {
    margin-bottom: 24px;
  }

  .drawer-section h4 {
    margin: 0 0 12px;
    color: var(--detail-title-color);
    font-size: 15px;
  }

  @media (width <= 991px) {
    .detail-hero {
      flex-direction: column;
    }

    .detail-hero-actions {
      justify-content: flex-start;
    }
  }
</style>
