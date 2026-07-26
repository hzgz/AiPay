<template>
  <div class="payment-method-page art-full-height">
    <ArtSearchBar
      v-model="searchForm"
      :items="searchItems"
      :showExpand="false"
      @search="handleSearch"
      @reset="handleReset"
    />

    <ElCard class="art-table-card" shadow="never">
      <ArtTableHeader v-model:columns="columnChecks" :loading="loading" @refresh="getMethodList">
        <template #left>
          <ElSpace wrap class="method-toolbar">
            <ElButton
              v-if="hasMethodCreateAuth && !isRecycleView"
              size="small"
              type="primary"
              @click="openCreateDialog"
            >
              新增方式
            </ElButton>
            <ElTag size="small" effect="plain">总数 {{ pagination.total }}</ElTag>
            <ElTag size="small" type="success" effect="plain">启用 {{ enabledMethodCount }}</ElTag>
            <ElTag size="small" type="warning" effect="plain"
              >在线账户 {{ onlineAccountCount }}</ElTag
            >
            <ElButton
              v-if="hasMethodRecycleAuth"
              size="small"
              plain
              :type="isRecycleView ? 'primary' : 'info'"
              @click="toggleRecycleView"
            >
              {{ isRecycleView ? '返回正常列表' : '回收站' }}
            </ElButton>
            <ElButton
              v-if="hasMethodBatchDeleteAuth && !isRecycleView"
              size="small"
              plain
              type="danger"
              :disabled="selectedMethods.length === 0"
              @click="handleBatchDeleteMethods"
            >
              批量删除
            </ElButton>
            <ElButton
              v-if="hasMethodRecycleAuth && isRecycleView"
              size="small"
              plain
              type="success"
              :disabled="selectedMethods.length === 0"
              @click="handleBatchRestoreMethods"
            >
              批量恢复
            </ElButton>
            <ElTag v-if="selectedMethods.length > 0" size="small" type="danger" effect="plain">
              已选 {{ selectedMethods.length }}
            </ElTag>
            <ElTag v-if="isRecycleView" size="small" type="info" effect="plain">回收视图</ElTag>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        ref="tableRef"
        :loading="loading"
        :data="methodList"
        :columns="columns"
        :pagination="pagination"
        row-key="id"
        reserve-selection
        @selection-change="handleMethodSelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <ElDrawer
      v-model="detailVisible"
      size="720px"
      destroy-on-close
      :title="
        activeMethod
          ? `${displayAdminFixtureText(activeMethod.name)} / #${activeMethod.id}`
          : '方式详情'
      "
    >
      <div v-loading="detailLoading" class="method-detail">
        <template v-if="activeMethod">
          <div class="detail-hero">
            <div class="detail-hero-copy">
              <h3>{{ displayAdminFixtureText(activeMethod.name) }}</h3>
              <p>{{
                displayMethodTypeLabel(
                  activeMethod.type_text || activeMethod.type_label,
                  activeMethod.type
                )
              }}</p>
            </div>
            <div class="detail-hero-actions">
              <ElButton
                v-if="activeMethod.deleted && hasMethodRecycleAuth"
                type="success"
                plain
                @click="handleRestoreMethod()"
              >
                恢复方式
              </ElButton>
              <template v-else>
                <ElButton v-if="hasMethodEditAuth" plain @click="openEditDialog()">编辑</ElButton>
                <ElButton
                  v-if="hasMethodStatusAuth"
                  plain
                  :type="activeMethod.status === 1 ? 'warning' : 'success'"
                  :loading="updatingMethodId === activeMethod.id"
                  @click="toggleActiveMethodStatus"
                >
                  {{ activeMethod.status === 1 ? '停用' : '启用' }}
                </ElButton>
                <ElButton
                  v-if="hasMethodDeleteAuth"
                  type="danger"
                  plain
                  @click="handleDeleteMethod()"
                >
                  移入回收站
                </ElButton>
              </template>
            </div>
          </div>

          <div class="drawer-section">
            <ElDescriptions :column="2" border>
              <ElDescriptionsItem label="方式名称">{{
                displayAdminFixtureText(activeMethod.name)
              }}</ElDescriptionsItem>
              <ElDescriptionsItem label="支付标识">{{
                displayMethodTypeCode(activeMethod.type)
              }}</ElDescriptionsItem>
              <ElDescriptionsItem label="展示名称">{{
                displayMethodTypeLabel(
                  activeMethod.type_text || activeMethod.type_label,
                  activeMethod.type
                )
              }}</ElDescriptionsItem>
              <ElDescriptionsItem label="状态">{{
                displayMethodStatus(activeMethod)
              }}</ElDescriptionsItem>
              <ElDescriptionsItem label="排序权重">{{ activeMethod.sort }}</ElDescriptionsItem>
              <ElDescriptionsItem label="删除时间">{{
                activeMethod.delete_time || '--'
              }}</ElDescriptionsItem>
              <ElDescriptionsItem label="创建时间">{{
                activeMethod.create_time || '--'
              }}</ElDescriptionsItem>
              <ElDescriptionsItem label="更新时间">{{
                activeMethod.update_time || '--'
              }}</ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>流量统计</h4>
            <div class="drawer-grid">
              <div class="drawer-item">
                <span>总订单数</span>
                <strong>{{ activeMethod.order_count }}</strong>
              </div>
              <div class="drawer-item">
                <span>已付订单</span>
                <strong>{{ activeMethod.paid_order_count }}</strong>
              </div>
              <div class="drawer-item">
                <span>已付金额</span>
                <strong>{{ formatAmount(activeMethod.paid_amount) }}</strong>
              </div>
              <div class="drawer-item">
                <span>账户总数</span>
                <strong>{{ activeMethod.account_count }}</strong>
              </div>
              <div class="drawer-item">
                <span>启用账户</span>
                <strong>{{ activeMethod.enabled_account_count }}</strong>
              </div>
              <div class="drawer-item">
                <span>在线账户</span>
                <strong>{{ activeMethod.online_account_count }}</strong>
              </div>
            </div>
          </div>
        </template>
      </div>
    </ElDrawer>

    <ElDialog v-model="createVisible" width="560px" destroy-on-close align-center title="新增方式">
      <ElForm label-position="top">
        <div class="dialog-grid">
          <ElFormItem label="方式名称">
            <ElInput
              v-model="createForm.name"
              maxlength="255"
              show-word-limit
              placeholder="请输入显示名称"
            />
          </ElFormItem>
          <ElFormItem label="支付标识">
            <ElInput
              v-model="createForm.type"
              maxlength="32"
              placeholder="请输入支付标识，如 alipay、wxpay、usdt"
            />
          </ElFormItem>
          <ElFormItem label="排序权重">
            <ElInput
              v-model="createForm.sort"
              maxlength="20"
              inputmode="numeric"
              placeholder="请输入非负整数"
            />
          </ElFormItem>
          <ElFormItem label="状态">
            <ElSwitch
              v-model="createForm.status"
              inline-prompt
              active-text="启用"
              inactive-text="停用"
            />
          </ElFormItem>
        </div>
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="createVisible = false">取消</ElButton>
          <ElButton type="primary" :loading="creatingMethod" @click="submitCreateMethod">
            新增方式
          </ElButton>
        </div>
      </template>
    </ElDialog>

    <ElDialog v-model="editVisible" width="520px" destroy-on-close align-center title="编辑方式">
      <ElForm label-position="top">
        <ElFormItem label="支付标识">
          <ElInput :model-value="activeMethod?.type || ''" disabled />
        </ElFormItem>
        <ElFormItem label="方式名称">
          <ElInput
            v-model="editForm.name"
            maxlength="255"
            show-word-limit
            placeholder="请输入显示名称"
          />
        </ElFormItem>
        <ElFormItem label="排序权重">
          <ElInput
            v-model="editForm.sort"
            maxlength="20"
            inputmode="numeric"
            placeholder="请输入非负整数"
          />
        </ElFormItem>
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="editVisible = false">取消</ElButton>
          <ElButton type="primary" :loading="savingEdit" @click="submitEditMethod">
            保存修改
          </ElButton>
        </div>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox, ElSwitch, ElTag } from 'element-plus'
  import { useAuth } from '@/hooks'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import ArtButtonTable from '@/components/core/forms/artButtonTable/index.vue'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'
  import {
    fetchAuditPaymentMethodBatchDelete,
    fetchBatchDeletePaymentMethods,
    fetchBatchRestorePaymentMethods,
    fetchCreatePaymentMethod,
    fetchDeletePaymentMethod,
    fetchGetPaymentMethodDeleteAudit,
    fetchGetPaymentMethodDetail,
    fetchGetPaymentMethodList,
    fetchRestorePaymentMethod,
    fetchUpdatePaymentMethod,
    fetchUpdatePaymentMethodStatus
  } from '@/api/payments'

  defineOptions({ name: 'PaymentMethods' })

  type MethodItem = Api.Payments.MethodListItem
  type MethodDeleteAudit = Api.Payments.MethodDeleteAudit
  type MethodBatchDeleteAudit = Api.Payments.MethodBatchDeleteAudit

  const { hasAuth } = useAuth()
  const tableRef = ref<any>(null)
  const loading = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const createVisible = ref(false)
  const editVisible = ref(false)
  const creatingMethod = ref(false)
  const savingEdit = ref(false)
  const updatingMethodId = ref<number | null>(null)
  const methodList = ref<MethodItem[]>([])
  const selectedMethods = ref<MethodItem[]>([])
  const activeMethod = ref<MethodItem | null>(null)
  const pagination = reactive({
    current: 1,
    size: 20,
    total: 0
  })
  const searchForm = ref<{
    keyword?: string
    status?: string
    type?: string
  }>({})
  const createForm = reactive({
    name: '',
    type: '',
    sort: '50',
    status: true
  })
  const editForm = reactive({
    name: '',
    sort: ''
  })

  const hasMethodCreateAuth = computed(() => hasAuth('add'))
  const hasMethodEditAuth = computed(() => hasAuth('edit'))
  const hasMethodStatusAuth = computed(() => hasAuth('status'))
  const hasMethodDeleteAuth = computed(() => hasAuth('remove'))
  const hasMethodBatchDeleteAuth = computed(() => hasAuth('batchRemove'))
  const hasMethodRecycleAuth = computed(() => hasAuth('recycle'))

  const isRecycleView = computed(() => {
    const status = String(searchForm.value.status || '')
    return status === '-1' || status.toLowerCase() === 'deleted'
  })

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '搜名称、类型或 ID'
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
          { label: '已回收', value: '-1' }
        ]
      }
    },
    {
      label: '支付标识',
      key: 'type',
      type: 'input',
      props: {
        placeholder: '如 wxpay、alipay'
      }
    }
  ])

  const enabledMethodCount = computed(
    () => methodList.value.filter((item) => !item.deleted && item.status === 1).length
  )
  const onlineAccountCount = computed(() =>
    methodList.value.reduce((sum, item) => sum + Number(item.online_account_count || 0), 0)
  )

  const { columnChecks, columns } = useTableColumns<MethodItem>(() => [
    { type: 'selection', width: 54, fixed: 'left' as const },
    { type: 'globalIndex', width: 70, label: '序号' },
    {
      prop: 'name',
      label: '支付方式',
      minWidth: 210,
      formatter: (row) =>
        h('div', { class: 'method-cell' }, [
          h('strong', { class: 'cell-title' }, displayAdminFixtureText(row.name)),
          h(
            'p',
            { class: 'cell-sub' },
            displayMethodTypeLabel(row.type_text || row.type_label, row.type)
          ),
          h('span', { class: 'cell-sub' }, `状态：${displayMethodStatus(row)}`)
        ])
    },
    {
      prop: 'status_label',
      label: '状态',
      minWidth: 150,
      formatter: (row) =>
        row.deleted
          ? h('div', { class: 'status-cell' }, [
              h(ElTag, { type: 'info', effect: 'light' }, () => '已回收'),
              h(ElTag, { type: tagType(row.status_type), effect: 'plain' }, () =>
                displayMethodStatus(row)
              )
            ])
          : h('div', { class: 'status-cell' }, [
              h(ElTag, { type: tagType(row.status_type), effect: 'light' }, () =>
                displayMethodStatus(row)
              ),
              h(ElSwitch, {
                modelValue: row.status === 1,
                disabled: !hasMethodStatusAuth.value,
                loading: updatingMethodId.value === row.id,
                inlinePrompt: true,
                activeText: '开',
                inactiveText: '关',
                beforeChange: () => changeMethodStatus(row, row.status !== 1)
              })
            ])
    },
    {
      prop: 'order_count',
      label: '流量',
      minWidth: 168,
      formatter: (row) =>
        h('div', { class: 'stats-cell' }, [
          h('p', { class: 'cell-sub' }, `订单 ${row.order_count}`),
          h('p', { class: 'cell-sub' }, `已付 ${row.paid_order_count}`),
          h('p', { class: 'cell-sub' }, `金额 ${formatAmount(row.paid_amount)}`)
        ])
    },
    {
      prop: 'account_count',
      label: '账户',
      minWidth: 168,
      formatter: (row) =>
        h('div', { class: 'stats-cell' }, [
          h('p', { class: 'cell-sub' }, `总数 ${row.account_count}`),
          h('p', { class: 'cell-sub' }, `启用 ${row.enabled_account_count}`),
          h('p', { class: 'cell-sub' }, `在线 ${row.online_account_count}`)
        ])
    },
    {
      prop: 'update_time',
      label: isRecycleView.value ? '删除时间' : '更新时间',
      minWidth: 148,
      formatter: (row) =>
        isRecycleView.value ? row.delete_time : row.update_time || row.create_time || '--'
    },
    {
      prop: 'operation',
      label: '操作',
      width: 156,
      align: 'center' as const,
      className: 'operation-column-cell',
      fixed: 'right' as const,
      formatter: (row) => renderMethodOperationButtons(row)
    }
  ])

  onMounted(() => {
    getMethodList()
  })

  function renderMethodOperationButtons(row: MethodItem) {
    const actions = [
      h(ArtButtonTable, {
        type: 'view',
        title: '详情',
        onClick: () => openDetail(row)
      })
    ]

    if (!row.deleted && hasMethodEditAuth.value) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:pencil-line',
          iconClass: 'bg-primary/12 text-primary',
          title: '编辑',
          onClick: () => openEditDialog(row)
        })
      )
    }

    if (!row.deleted && hasMethodDeleteAuth.value) {
      actions.push(
        h(ArtButtonTable, {
          type: 'delete',
          title: '删除',
          onClick: () => handleDeleteMethod(row)
        })
      )
    }

    if (row.deleted && hasMethodRecycleAuth.value) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:restart-line',
          iconClass: 'bg-success/12 text-success',
          title: '恢复',
          onClick: () => handleRestoreMethod(row)
        })
      )
    }

    return h('div', { class: 'table-actions' }, actions)
  }

  async function getMethodList() {
    loading.value = true
    try {
      const response = await fetchGetPaymentMethodList({
        current: pagination.current,
        size: pagination.size,
        keyword: searchForm.value.keyword,
        status: searchForm.value.status,
        type: searchForm.value.type
      })

      methodList.value = response.records || []
      pagination.current = response.current
      pagination.size = response.size
      pagination.total = response.total
      syncActiveMethodFromList()
    } catch {
      methodList.value = []
    } finally {
      loading.value = false
    }
  }

  function handleSearch(params: Api.Payments.MethodSearchParams) {
    pagination.current = 1
    clearMethodSelection()
    searchForm.value = {
      keyword: params.keyword,
      status:
        params.status !== undefined && params.status !== null ? String(params.status) : undefined,
      type: params.type
    }
    getMethodList()
  }

  function handleReset() {
    pagination.current = 1
    clearMethodSelection()
    searchForm.value = {}
    getMethodList()
  }

  function toggleRecycleView() {
    pagination.current = 1
    clearMethodSelection()
    searchForm.value = {
      ...searchForm.value,
      status: isRecycleView.value ? undefined : '-1'
    }
    getMethodList()
  }

  function handleSizeChange(size: number) {
    pagination.size = size
    pagination.current = 1
    getMethodList()
  }

  function handleCurrentChange(current: number) {
    pagination.current = current
    getMethodList()
  }

  function handleMethodSelectionChange(rows: MethodItem[]) {
    selectedMethods.value = rows
  }

  function openCreateDialog() {
    resetCreateForm()
    createVisible.value = true
  }

  function openEditDialog(row?: MethodItem) {
    const target = row || activeMethod.value
    if (!target) {
      return
    }

    if (target.deleted) {
      ElMessage.warning('请先恢复该支付方式再进行编辑。')
      return
    }

    editForm.name = target.name || ''
    editForm.sort = String(target.sort || '')
    editVisible.value = true
  }

  async function openDetail(row: MethodItem) {
    detailVisible.value = true
    detailLoading.value = true
    activeMethod.value = row

    try {
      const response = await fetchGetPaymentMethodDetail(row.id)
      if (response.item) {
        activeMethod.value = response.item
      }

      editForm.name = response.editable?.name || response.item?.name || ''
      editForm.sort = response.editable?.sort || String(response.item?.sort || '')
    } catch {
      ElMessage.error('加载支付方式详情失败。')
    } finally {
      detailLoading.value = false
    }
  }

  async function submitCreateMethod() {
    const payload = buildCreatePayload()
    if (!payload) {
      return
    }

    creatingMethod.value = true
    try {
      const response = await fetchCreatePaymentMethod(payload)
      createVisible.value = false
      resetCreateForm()
      clearMethodSelection()
      await getMethodList()
      ElMessage.success(`支付方式 ${response.created_payment_label || payload.name} 已创建。`)
    } finally {
      creatingMethod.value = false
    }
  }

  async function submitEditMethod() {
    if (!activeMethod.value) {
      return
    }

    const payload = buildEditPayload()
    if (!payload) {
      return
    }

    savingEdit.value = true
    try {
      const response = await fetchUpdatePaymentMethod(activeMethod.value.id, payload)
      if (response.item) {
        activeMethod.value = response.item
      }

      editForm.name = response.editable?.name || payload.name
      editForm.sort = response.editable?.sort || payload.sort
      editVisible.value = false
      await getMethodList()
      ElMessage.success('支付方式已更新。')
    } finally {
      savingEdit.value = false
    }
  }

  async function toggleActiveMethodStatus() {
    if (!activeMethod.value) {
      return
    }

    await changeMethodStatus(activeMethod.value, activeMethod.value.status !== 1)
  }

  async function changeMethodStatus(row: MethodItem, enabled: boolean): Promise<boolean> {
    if (row.deleted || updatingMethodId.value !== null) {
      return false
    }

    updatingMethodId.value = row.id
    try {
      const response = await fetchUpdatePaymentMethodStatus(row.id, {
        status: enabled
      })

      if (response.item && activeMethod.value?.id === row.id) {
        activeMethod.value = response.item
      }

      await getMethodList()
      return true
    } catch {
      return false
    } finally {
      updatingMethodId.value = null
    }
  }

  async function handleDeleteMethod(row?: MethodItem) {
    const target = row || activeMethod.value
    if (!target) {
      return
    }

    if (target.deleted) {
      ElMessage.warning('该支付方式已在回收站中。')
      return
    }

    try {
      const response = await fetchGetPaymentMethodDeleteAudit(target.id)
      const audit = response.audit
      const title = displayAdminFixtureText(target.name, `支付方式 #${target.id}`)

      if (!audit.can_delete) {
        await ElMessageBox.alert(buildMethodDeleteBlockedMessage(audit, title), '删除受限', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildMethodDeletePromptMessage(audit, title),
        '删除支付方式',
        {
          confirmButtonText: '删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以继续。`
        }
      )

      const deleteResponse = await fetchDeletePaymentMethod(target.id, {
        confirmation_phrase: String(value || '')
      })

      if (activeMethod.value?.id === target.id) {
        detailVisible.value = false
        activeMethod.value = null
      }

      clearMethodSelection()
      await getMethodList()
      ElMessage.success(`支付方式 ${deleteResponse.deleted_payment_label || title} 已移入回收站。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      throw error
    }
  }

  async function handleRestoreMethod(row?: MethodItem) {
    const target = row || activeMethod.value
    if (!target) {
      return
    }

    if (!target.deleted) {
      ElMessage.warning('该支付方式当前处于正常状态。')
      return
    }

    try {
      await ElMessageBox.confirm(
        `确认将 ${target.name || `支付方式 #${target.id}`} 恢复到正常列表吗？`,
        '恢复支付方式',
        {
          confirmButtonText: '恢复',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchRestorePaymentMethod(target.id)
      if (response.item) {
        activeMethod.value = response.item
      }

      clearMethodSelection()
      await getMethodList()

      if (isRecycleView.value) {
        detailVisible.value = false
        activeMethod.value = null
      }

      ElMessage.success(`支付方式 ${response.restored_payment_label || target.name} 已恢复。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      throw error
    }
  }

  async function handleBatchDeleteMethods() {
    const activeSelection = selectedMethods.value.filter((item) => !item.deleted)
    if (activeSelection.length === 0) {
      ElMessage.warning('请至少选择一条正常状态的支付方式。')
      return
    }

    const paymentIds = activeSelection.map((item) => item.id)

    try {
      const response = await fetchAuditPaymentMethodBatchDelete({
        payment_ids: paymentIds
      })
      const audit = response.audit

      if (!audit.can_delete_all) {
        await ElMessageBox.alert(buildMethodBatchDeleteBlockedMessage(audit), '批量删除受限', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildMethodBatchDeletePromptMessage(audit),
        '批量删除支付方式',
        {
          confirmButtonText: '批量删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以继续。`
        }
      )

      const deleteResponse = await fetchBatchDeletePaymentMethods({
        payment_ids: paymentIds,
        confirmation_phrase: String(value || '')
      })

      if (
        activeMethod.value &&
        deleteResponse.deleted_payment_ids.includes(activeMethod.value.id)
      ) {
        detailVisible.value = false
        activeMethod.value = null
      }

      clearMethodSelection()
      await getMethodList()
      ElMessage.success(`已将 ${deleteResponse.deleted_count} 条支付方式移入回收站。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      throw error
    }
  }

  async function handleBatchRestoreMethods() {
    const recycleSelection = selectedMethods.value.filter((item) => item.deleted)
    if (recycleSelection.length === 0) {
      ElMessage.warning('请至少选择一条已回收的支付方式。')
      return
    }

    const paymentIds = recycleSelection.map((item) => item.id)

    try {
      await ElMessageBox.confirm(
        `确认将 ${paymentIds.length} 条支付方式恢复到正常列表吗？`,
        '批量恢复支付方式',
        {
          confirmButtonText: '批量恢复',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchBatchRestorePaymentMethods({
        payment_ids: paymentIds
      })

      clearMethodSelection()
      await getMethodList()

      if (activeMethod.value && response.restored_payment_ids.includes(activeMethod.value.id)) {
        detailVisible.value = false
        activeMethod.value = null
      }

      ElMessage.success(`已恢复 ${response.restored_count} 条支付方式。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      throw error
    }
  }

  function buildCreatePayload(): Api.Payments.MethodCreatePayload | null {
    createForm.name = createForm.name.trim()
    createForm.type = createForm.type.trim().toLowerCase()
    createForm.sort = createForm.sort.trim()

    if (!createForm.name) {
      ElMessage.warning('请输入支付方式名称。')
      return null
    }

    if (!createForm.type) {
      ElMessage.warning('请输入支付方式类型。')
      return null
    }

    if (!/^[a-z][a-z0-9_]{0,31}$/.test(createForm.type)) {
      ElMessage.warning('支付方式类型必须以字母开头，且只能使用小写字母、数字或下划线。')
      return null
    }

    if (!/^\d+$/.test(createForm.sort)) {
      ElMessage.warning('排序权重必须是非负整数。')
      return null
    }

    return {
      name: createForm.name,
      type: createForm.type,
      sort: createForm.sort,
      status: createForm.status
    }
  }

  function buildEditPayload(): Api.Payments.MethodUpdatePayload | null {
    editForm.name = editForm.name.trim()
    editForm.sort = editForm.sort.trim()

    if (!editForm.name) {
      ElMessage.warning('请输入支付方式名称。')
      return null
    }

    if (!/^\d+$/.test(editForm.sort)) {
      ElMessage.warning('排序权重必须是非负整数。')
      return null
    }

    return {
      name: editForm.name,
      sort: editForm.sort
    }
  }

  function resetCreateForm() {
    createForm.name = ''
    createForm.type = ''
    createForm.sort = '50'
    createForm.status = true
  }

  function syncActiveMethodFromList() {
    if (!activeMethod.value) {
      return
    }

    const latest = methodList.value.find((item) => item.id === activeMethod.value?.id)
    if (!latest) {
      return
    }

    activeMethod.value = {
      ...activeMethod.value,
      ...latest
    }
  }

  function buildMethodDeleteBlockedMessage(audit: MethodDeleteAudit, title: string) {
    return [
      `${title} 当前暂不能移入回收站。`,
      ...audit.blocking_reasons.map((item) => displayAdminFixtureText(item, item)),
      ...audit.warnings.map((item) => displayAdminFixtureText(item, item))
    ]
      .filter(Boolean)
      .join('\n')
  }

  function buildMethodDeletePromptMessage(audit: MethodDeleteAudit, title: string) {
    return [
      `确认将 ${title} 移入回收站吗？`,
      `支付标识：${displayMethodTypeCode(audit.type)}`,
      `关联订单数：${audit.summary.order_count}`,
      `关联账户数：${audit.summary.account_count}`,
      ...audit.warnings.map((item) => displayAdminFixtureText(item, item)),
      `请输入 ${audit.confirmation_phrase} 以继续。`
    ]
      .filter(Boolean)
      .join('\n')
  }

  function buildMethodBatchDeleteBlockedMessage(audit: MethodBatchDeleteAudit) {
    return [
      '当前所选支付方式暂不能批量移入回收站。',
      ...audit.warnings.map((item) => displayAdminFixtureText(item, item)),
      ...audit.items.flatMap((item) =>
        item.can_delete || item.blocking_reasons.length === 0
          ? []
          : [
              `#${item.payment_id || '--'} ${displayAdminFixtureText(item.payment_label || item.type || '未知方式', item.payment_label || item.type || '未知方式')}：${item.blocking_reasons.map((reason) => displayAdminFixtureText(reason, reason)).join('；')}`
            ]
      )
    ]
      .filter(Boolean)
      .join('\n')
  }

  function buildMethodBatchDeletePromptMessage(audit: MethodBatchDeleteAudit) {
    return [
      `确认将 ${audit.summary.deletable_count} 条支付方式移入回收站吗？`,
      `命中记录数：${audit.summary.existing_count}`,
      `关联订单数：${audit.summary.order_count}`,
      `关联账户数：${audit.summary.account_count}`,
      ...audit.warnings.map((item) => displayAdminFixtureText(item, item)),
      `请输入 ${audit.confirmation_phrase} 以继续。`
    ]
      .filter(Boolean)
      .join('\n')
  }

  function displayMethodTypeCode(value: null | number | string | undefined, fallback = '--') {
    const code = String(value ?? '').trim()
    return code || fallback
  }

  function displayMethodTypeText(value: null | number | string | undefined, fallback = '') {
    const normalized = displayAdminFixtureText(value, fallback)
    return normalized === '系统生成方式标识' ? fallback : normalized
  }

  function displayMethodTypeLabel(
    typeLabel: null | number | string | undefined,
    typeCode: null | number | string | undefined,
    fallback = '--'
  ) {
    const primary = displayMethodTypeText(typeLabel, '')
    const secondaryCode = displayMethodTypeCode(typeCode, '')
    const secondary = displayMethodTypeText(secondaryCode, secondaryCode)

    if (primary) {
      return primary
    }

    if (secondary) {
      return secondary
    }

    return primary || fallback
  }

  function displayMethodStatus(method?: Partial<MethodItem> | null, fallback = '--') {
    return displayAdminFixtureText(method?.status_text || method?.status_label, fallback)
  }

  function clearMethodSelection() {
    selectedMethods.value = []
    tableRef.value?.elTableRef?.clearSelection?.()
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
  .payment-method-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
    --detail-card-border: var(--el-border-color-lighter);
    --detail-card-bg: rgb(248 250 252 / 0.82);
    --detail-title-color: #0f172a;
    --detail-muted-color: #64748b;
  }

  :global(html.dark .payment-method-page ){
    --detail-card-border: rgb(71 85 105 / 0.42);
    --detail-card-bg: rgb(15 23 42 / 0.84);
    --detail-title-color: #e2e8f0;
    --detail-muted-color: #94a3b8;
  }

  .method-cell,
  .stats-cell {
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

  .method-detail {
    min-height: 240px;
  }

  .method-toolbar {
    row-gap: 8px;
  }

  .payment-method-page :deep(.method-toolbar .el-button) {
    height: var(--el-component-custom-height);
    min-height: var(--el-component-custom-height);
    padding: 0 14px;
    border-radius: 10px;
    font-size: 13px;
  }

  .payment-method-page :deep(.method-toolbar .el-tag) {
    min-height: 28px;
    padding-inline: 10px;
    border-radius: 999px;
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
    color: var(--detail-title-color);
    font-size: 15px;
  }

  .drawer-grid,
  .dialog-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }

  .drawer-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 14px 16px;
    border: 1px solid var(--detail-card-border);
    border-radius: 14px;
    background: var(--detail-card-bg);
  }

  .drawer-item span {
    color: var(--detail-muted-color);
    font-size: 12px;
  }

  .drawer-item strong {
    color: var(--detail-title-color);
    word-break: break-all;
  }

  .dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
  }

  @media (width <= 991px) {
    .detail-hero {
      flex-direction: column;
    }

    .detail-hero-actions {
      justify-content: flex-start;
    }

    .drawer-grid,
    .dialog-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
