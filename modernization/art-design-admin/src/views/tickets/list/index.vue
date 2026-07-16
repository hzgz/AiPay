<template>
  <div class="ticket-page art-full-height">
    <ArtSearchBar
      v-model="searchForm"
      :items="searchItems"
      :showExpand="false"
      @search="handleSearch"
      @reset="handleReset"
    />

    <ElCard class="art-table-card">
      <ArtTableHeader v-model:columns="columnChecks" :loading="loading" @refresh="getTicketList">
        <template #left>
          <ElSpace wrap>
            <ElTag effect="plain">工单 {{ pagination.total }}</ElTag>
            <ElTag type="warning" effect="plain">待处理 {{ summary.new_count }}</ElTag>
            <ElTag type="primary" effect="plain">处理中 {{ summary.processing_count }}</ElTag>
            <ElTag type="success" effect="plain">已解决 {{ summary.resolved_count }}</ElTag>
            <ElTag type="info" effect="plain">已关闭 {{ summary.closed_count }}</ElTag>
            <ElTag effect="plain">已回复 {{ summary.replied_count }}</ElTag>
            <ElButton
              v-if="hasTicketBatchDeleteAuth"
              plain
              type="danger"
              :disabled="selectedTickets.length === 0"
              @click="handleBatchDeleteTickets"
            >
              批量删除
            </ElButton>
            <ElTag
              v-if="hasTicketBatchDeleteAuth && selectedTickets.length > 0"
              type="danger"
              effect="plain"
            >
              已选 {{ selectedTickets.length }}
            </ElTag>
            <ElTag type="info" effect="plain">工单维护</ElTag>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        ref="tableRef"
        :loading="loading"
        :data="ticketList"
        :columns="columns"
        :pagination="pagination"
        row-key="id"
        reserve-selection
        @selection-change="handleTicketSelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <ElDrawer
      v-model="detailVisible"
      size="820px"
      destroy-on-close
      :title="
        activeTicket
          ? `${displayAdminFixtureText(activeTicket.ticket_label)} / #${activeTicket.id}`
          : '工单详情'
      "
    >
      <div v-loading="detailLoading" class="ticket-detail">
        <template v-if="activeTicket">
          <div class="detail-hero">
            <div class="detail-hero-copy">
              <h3>{{ displayAdminFixtureText(activeTicket.ticket_label) }}</h3>
              <p>
                {{ displayTicketStatus(activeTicket) }} / {{ displayTicketReplyState(activeTicket) }} /
                {{ displayTicketType(activeTicket) }}
              </p>
              <span>{{ displayAdminFixtureText(activeTicket.delete_guard_reason) }}</span>
            </div>
            <div class="detail-hero-actions">
              <ElButton v-if="hasTicketReplyAuth" plain @click="openReplyDialog()">回复</ElButton>
              <ElButton v-if="hasTicketStatusAuth" plain type="warning" @click="openStatusDialog()">
                修改状态
              </ElButton>
              <ElButton
                v-if="hasTicketDeleteAuth"
                type="danger"
                plain
                @click="handleDeleteTicket()"
              >
                删除
              </ElButton>
            </div>
          </div>

          <div class="drawer-section">
            <ElDescriptions :column="2" border>
              <ElDescriptionsItem label="工单标题">
                {{ displayAdminFixtureText(activeTicket.ticket_label) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="状态">
                {{ displayTicketStatus(activeTicket) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="回复状态">
                {{ displayTicketReplyState(activeTicket) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="分类">
                {{ displayTicketType(activeTicket) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="商户">
                {{ displayAdminFixtureText(activeTicket.creator_display) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="处理人">
                {{ displayAdminFixtureText(activeTicket.assignee_display) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="创建时间">
                {{ activeTicket.create_time || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="回复时间">
                {{ activeTicket.reply_time || '--' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>商户资料</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="商户编号">
                {{ activeTicket.creator_id || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="用户名">
                {{ displayAdminFixtureText(activeTicket.creator_username) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="姓名">
                {{ displayAdminFixtureText(activeTicket.creator_name) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="邮箱">
                {{ displayAdminFixtureText(activeTicket.creator_email) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="手机号">
                {{ displayAdminFixtureText(activeTicket.creator_mobile) || '--' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>工单内容</h4>
            <div class="content-box">{{
              displayAdminFixtureText(activeTicket.content, '暂无工单内容')
            }}</div>
          </div>

          <div class="drawer-section">
            <h4>后台回复</h4>
            <div class="content-box reply">
              {{ displayAdminFixtureText(activeTicket.reply_content, '暂未回复') }}
            </div>
          </div>

          <div class="drawer-section">
            <h4>原始信息</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="工单编号">
                {{ activeTicket.id }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="分类编号">
                {{ activeTicket.type }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="原始状态值">
                {{ activeTicket.status }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="更新时间">
                {{ activeTicket.update_time || '--' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <ElAlert
            type="info"
            :closable="false"
            show-icon
            title="回复内容和状态会立即保存到当前工单；删除工单后不会自动通知商户。"
          />
        </template>
      </div>
    </ElDrawer>

    <ElDialog v-model="replyVisible" width="760px" destroy-on-close align-center title="回复工单">
      <ElForm label-position="top">
        <ElFormItem label="回复内容">
          <ElInput
            v-model="replyForm.reply_content"
            type="textarea"
            :rows="6"
            maxlength="5000"
            show-word-limit
            placeholder="请输入要保存到当前工单的后台回复内容"
          />
        </ElFormItem>
        <ElFormItem label="后续状态">
          <ElSelect v-model="replyForm.status" placeholder="请选择工单状态">
            <ElOption
              v-for="option in statusOptions"
              :key="option.value"
              :label="option.label"
              :value="option.value"
            />
          </ElSelect>
        </ElFormItem>
        <ElAlert
          type="info"
          :closable="false"
          show-icon
          title="保存回复时，会同步更新工单时间，并把当前管理员记录为本次处理人。"
        />
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="replyVisible = false">取消</ElButton>
          <ElButton
            v-if="hasTicketReplyAuth"
            type="primary"
            :loading="savingReply"
            @click="submitReply"
          >
            保存回复
          </ElButton>
        </div>
      </template>
    </ElDialog>

    <ElDialog
      v-model="statusVisible"
      width="520px"
      destroy-on-close
      align-center
      title="修改工单状态"
    >
      <ElForm label-position="top">
        <ElFormItem label="状态">
          <ElSelect v-model="statusForm.status" placeholder="请选择下一个工单状态">
            <ElOption
              v-for="option in statusOptions"
              :key="option.value"
              :label="option.label"
              :value="option.value"
            />
          </ElSelect>
        </ElFormItem>
        <ElAlert
          type="info"
          :closable="false"
          show-icon
          title="状态变更会保留当前回复内容，并将当前管理员记录为本次操作的最新处理人。"
        />
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="statusVisible = false">取消</ElButton>
          <ElButton
            v-if="hasTicketStatusAuth"
            type="primary"
            :loading="savingStatus"
            @click="submitStatus"
          >
            保存状态
          </ElButton>
        </div>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox, ElTag } from 'element-plus'
  import { useAuth } from '@/hooks'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'
  import {
    fetchAuditTicketBatchDelete,
    fetchBatchDeleteTickets,
    fetchDeleteTicket,
    fetchGetTicketDeleteAudit,
    fetchGetTicketDetail,
    fetchGetTicketList,
    fetchReplyTicket,
    fetchUpdateTicketStatus
  } from '@/api/tickets'

  defineOptions({ name: 'TicketList' })

  type TicketItem = Api.Tickets.TicketListItem
  type TicketSummary = Api.Tickets.TicketSummary
  type TicketCategory = Api.Tickets.TicketCategory

  const { hasAuth } = useAuth()
  const tableRef = ref<{ elTableRef?: { clearSelection?: () => void } } | null>(null)
  const loading = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const replyVisible = ref(false)
  const statusVisible = ref(false)
  const savingReply = ref(false)
  const savingStatus = ref(false)
  const ticketList = ref<TicketItem[]>([])
  const selectedTickets = ref<TicketItem[]>([])
  const activeTicket = ref<TicketItem | null>(null)
  const categories = ref<TicketCategory[]>([])
  const replyTicketId = ref<number | null>(null)
  const statusTicketId = ref<number | null>(null)
  const pagination = reactive({
    current: 1,
    size: 20,
    total: 0
  })
  const summary = reactive<TicketSummary>(emptySummary())
  const searchForm = ref<{
    keyword?: string
    creator_id?: string
    status?: string
    type?: string
    date_range?: string[]
  }>({})
  const replyForm = reactive(emptyReplyForm())
  const statusForm = reactive({
    status: '0'
  })
  const hasTicketReplyAuth = computed(() => hasAuth('reply'))
  const hasTicketStatusAuth = computed(() => hasAuth('status'))
  const hasTicketDeleteAuth = computed(() => hasAuth('remove'))
  const hasTicketBatchDeleteAuth = computed(() => hasAuth('batchRemove'))

  const statusOptions = [
    { label: '待处理', value: '0' },
    { label: '处理中', value: '1' },
    { label: '已解决', value: '2' },
    { label: '已关闭', value: '3' }
  ]

  const categoryOptions = computed(() =>
    categories.value.map((item) => ({
      label: displayAdminFixtureText(item.name, `分类 #${item.id}`),
      value: String(item.id)
    }))
  )

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '搜索工单编号、标题、内容、回复、商户或分类'
      }
    },
    {
      label: '商户编号',
      key: 'creator_id',
      type: 'input',
      props: {
        placeholder: '按商户编号筛选'
      }
    },
    {
      label: '状态',
      key: 'status',
      type: 'select',
      props: {
        placeholder: '全部状态',
        options: statusOptions
      }
    },
    {
      label: '分类',
      key: 'type',
      type: 'select',
      props: {
        placeholder: '全部分类',
        options: categoryOptions.value
      }
    },
    {
      label: '创建时间',
      key: 'date_range',
      type: 'daterange',
      props: {
        type: 'daterange',
        valueFormat: 'YYYY-MM-DD',
        startPlaceholder: '开始日期',
        endPlaceholder: '结束日期',
        rangeSeparator: '至'
      }
    }
  ])

  const { columnChecks, columns } = useTableColumns<TicketItem>(() => [
    {
      type: 'selection',
      width: 54,
      fixed: 'left' as const,
      visible: hasTicketBatchDeleteAuth.value
    },
    { type: 'globalIndex', width: 70, label: '序号' },
    {
      prop: 'ticket_label',
      label: '工单',
      minWidth: 280,
      formatter: (row) =>
        h('div', { class: 'ticket-cell' }, [
          h(
            'strong',
            { class: 'cell-title' },
            displayAdminFixtureText(row.ticket_label, `工单 #${row.id}`)
          ),
          h('p', { class: 'cell-sub' }, displayAdminFixtureText(row.content_preview)),
          h('p', { class: 'cell-sub' }, `编号：${row.id}`)
        ])
    },
    {
      prop: 'creator_display',
      label: '商户',
      minWidth: 220,
      formatter: (row) =>
        h('div', { class: 'merchant-cell' }, [
          h('strong', { class: 'cell-title' }, displayAdminFixtureText(row.creator_display)),
          h('p', { class: 'cell-sub' }, `商户编号：${row.creator_id || '--'}`)
        ])
    },
    {
      prop: 'type_name',
      label: '分类',
      minWidth: 160,
      align: 'center' as const,
      formatter: (row) =>
        h(ElTag, { effect: 'plain' }, () => displayTicketType(row))
    },
    {
      prop: 'status_label',
      label: '状态',
      width: 120,
      align: 'center' as const,
      formatter: (row) =>
        h(ElTag, { type: tagType(row.status_type), effect: 'light' }, () => displayTicketStatus(row))
    },
    {
      prop: 'reply_state_label',
      label: '回复',
      minWidth: 240,
      formatter: (row) =>
        h('div', { class: 'reply-cell' }, [
          h('strong', { class: 'cell-title' }, displayTicketReplyState(row)),
          h('p', { class: 'cell-sub' }, displayAdminFixtureText(row.reply_preview))
        ])
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
      formatter: (row) => renderTicketOperationButtons(row)
    }
  ])

  onMounted(() => {
    getTicketList()
  })

  function renderTicketOperationButtons(row: TicketItem) {
    const actions = [
      h(ArtButtonTable, {
        type: 'view',
        title: '详情',
        onClick: () => openDetail(row)
      })
    ]

    if (hasTicketReplyAuth.value) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:message-2-line',
          iconClass: 'bg-primary/12 text-primary',
          title: '回复',
          onClick: () => openReplyDialog(row)
        })
      )
    }

    if (hasTicketStatusAuth.value) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:refresh-line',
          iconClass: 'bg-warning/12 text-warning',
          title: '状态',
          onClick: () => openStatusDialog(row)
        })
      )
    }

    if (hasTicketDeleteAuth.value) {
      actions.push(
        h(ArtButtonTable, {
          type: 'delete',
          title: '删除',
          onClick: () => handleDeleteTicket(row)
        })
      )
    }

    return h('div', { class: 'table-actions' }, actions)
  }

  async function getTicketList() {
    loading.value = true
    try {
      const response = await fetchGetTicketList({
        current: pagination.current,
        size: pagination.size,
        keyword: searchForm.value.keyword,
        creator_id: searchForm.value.creator_id,
        status: searchForm.value.status,
        type: searchForm.value.type,
        start_date: searchForm.value.date_range?.[0],
        end_date: searchForm.value.date_range?.[1]
      })
      ticketList.value = response.records
      pagination.current = response.current
      pagination.size = response.size
      pagination.total = response.total
      categories.value = response.categories || []
      Object.assign(summary, response.summary || emptySummary())
    } catch (_error) {
      ElMessage.error('加载工单列表失败。')
    } finally {
      loading.value = false
    }
  }

  function handleSearch(params: Api.Tickets.TicketSearchParams & { date_range?: string[] }) {
    pagination.current = 1
    clearTicketSelection()
    searchForm.value = {
      keyword: params.keyword,
      creator_id: params.creator_id as string | undefined,
      status: params.status as string | undefined,
      type: params.type as string | undefined,
      date_range: Array.isArray(params.date_range) ? params.date_range : undefined
    }
    getTicketList()
  }

  function handleReset() {
    pagination.current = 1
    clearTicketSelection()
    searchForm.value = {}
    getTicketList()
  }

  function handleSizeChange(size: number) {
    pagination.size = size
    pagination.current = 1
    clearTicketSelection()
    getTicketList()
  }

  function handleCurrentChange(current: number) {
    pagination.current = current
    clearTicketSelection()
    getTicketList()
  }

  function handleTicketSelectionChange(rows: TicketItem[]) {
    selectedTickets.value = rows
  }

  async function openDetail(row: TicketItem) {
    detailVisible.value = true
    detailLoading.value = true
    activeTicket.value = row

    try {
      const response = await fetchGetTicketDetail(row.id)
      activeTicket.value = response.item
      categories.value = response.categories || categories.value
    } catch (_error) {
      ElMessage.error('加载工单详情失败。')
    } finally {
      detailLoading.value = false
    }
  }

  function openReplyDialog(row?: TicketItem) {
    if (!hasTicketReplyAuth.value) {
      ElMessage.warning('您没有回复工单的权限。')
      return
    }

    const target = row || activeTicket.value
    if (!target) {
      return
    }

    replyTicketId.value = target.id
    Object.assign(replyForm, {
      reply_content: target.reply_content || '',
      status: target.is_replied
        ? String(target.status)
        : target.status === 0
          ? '2'
          : String(target.status)
    })
    replyVisible.value = true
  }

  async function submitReply() {
    if (!hasTicketReplyAuth.value) {
      ElMessage.warning('您没有回复工单的权限。')
      return
    }

    if (!replyTicketId.value) {
      ElMessage.warning('当前没有可回复的工单。')
      return
    }

    const replyContent = normalizeInput(replyForm.reply_content)
    if (!replyContent) {
      ElMessage.warning('请先输入回复内容。')
      return
    }

    savingReply.value = true
    try {
      const response = await fetchReplyTicket(replyTicketId.value, {
        reply_content: replyContent,
        status: replyForm.status
      })
      replyVisible.value = false
      syncActiveTicket(response.item)
      clearTicketSelection()
      await getTicketList()
      ElMessage.success(
        `工单 ${displayAdminFixtureText(response.updated_ticket_label, `#${response.updated_ticket_id}`)} 的回复已保存。`
      )
    } catch (_error) {
      ElMessage.error('保存后台回复失败。')
    } finally {
      savingReply.value = false
    }
  }

  function openStatusDialog(row?: TicketItem) {
    if (!hasTicketStatusAuth.value) {
      ElMessage.warning('您没有修改工单状态的权限。')
      return
    }

    const target = row || activeTicket.value
    if (!target) {
      return
    }

    statusTicketId.value = target.id
    Object.assign(statusForm, {
      status: String(target.status)
    })
    statusVisible.value = true
  }

  async function submitStatus() {
    if (!hasTicketStatusAuth.value) {
      ElMessage.warning('您没有修改工单状态的权限。')
      return
    }

    if (!statusTicketId.value) {
      ElMessage.warning('当前没有可修改状态的工单。')
      return
    }

    savingStatus.value = true
    try {
      const response = await fetchUpdateTicketStatus(statusTicketId.value, {
        status: statusForm.status
      })
      statusVisible.value = false
      syncActiveTicket(response.item)
      clearTicketSelection()
      await getTicketList()
      ElMessage.success(
        `工单 ${displayAdminFixtureText(response.updated_ticket_label, `#${response.updated_ticket_id}`)} 状态已更新为 ${displayAdminFixtureText(response.status_text || response.status_label)}。`
      )
    } catch (_error) {
      ElMessage.error('更新工单状态失败。')
    } finally {
      savingStatus.value = false
    }
  }

  async function handleDeleteTicket(row?: TicketItem) {
    if (!hasTicketDeleteAuth.value) {
      ElMessage.warning('您没有删除工单的权限。')
      return
    }

    const target = row || activeTicket.value
    if (!target) {
      return
    }

    try {
      const response = await fetchGetTicketDeleteAudit(target.id)
      const audit = response.audit
      const title = displayAdminFixtureText(target.ticket_label, `工单 #${target.id}`)

      const { value } = await ElMessageBox.prompt(
        buildTicketDeletePromptMessage(audit, title),
        '删除工单',
        {
          confirmButtonText: '删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以继续。`
        }
      )

      const deleteResponse = await fetchDeleteTicket(target.id, {
        confirmation_phrase: String(value || '')
      })

      if (activeTicket.value?.id === target.id) {
        detailVisible.value = false
        activeTicket.value = null
      }
      if (replyTicketId.value === target.id) {
        replyVisible.value = false
        replyTicketId.value = null
      }
      if (statusTicketId.value === target.id) {
        statusVisible.value = false
        statusTicketId.value = null
      }

      clearTicketSelection()
      await getTicketList()
      ElMessage.success(
        `工单 ${displayAdminFixtureText(deleteResponse.deleted_ticket_label, title)} 已永久删除。`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('删除工单失败。')
    }
  }

  async function handleBatchDeleteTickets() {
    if (!hasTicketBatchDeleteAuth.value) {
      ElMessage.warning('您没有批量删除工单的权限。')
      return
    }

    if (selectedTickets.value.length === 0) {
      ElMessage.warning('请至少选择一条工单。')
      return
    }

    const ticketIds = selectedTickets.value.map((item) => item.id)

    try {
      const response = await fetchAuditTicketBatchDelete({
        ticket_ids: ticketIds
      })
      const audit = response.audit

      if (!audit.can_delete_all) {
        await ElMessageBox.alert(buildTicketBatchDeleteBlockedMessage(audit), '批量删除受限', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildTicketBatchDeletePromptMessage(audit),
        '批量删除工单',
        {
          confirmButtonText: '批量删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以继续。`
        }
      )

      const deleteResponse = await fetchBatchDeleteTickets({
        ticket_ids: ticketIds,
        confirmation_phrase: String(value || '')
      })

      if (activeTicket.value && deleteResponse.deleted_ticket_ids.includes(activeTicket.value.id)) {
        detailVisible.value = false
        activeTicket.value = null
      }
      if (replyTicketId.value && deleteResponse.deleted_ticket_ids.includes(replyTicketId.value)) {
        replyVisible.value = false
        replyTicketId.value = null
      }
      if (
        statusTicketId.value &&
        deleteResponse.deleted_ticket_ids.includes(statusTicketId.value)
      ) {
        statusVisible.value = false
        statusTicketId.value = null
      }

      clearTicketSelection()
      await getTicketList()
      ElMessage.success(`已永久删除 ${deleteResponse.deleted_count} 条工单记录。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('批量删除工单失败。')
    }
  }

  function syncActiveTicket(item: TicketItem | null) {
    if (!item) {
      return
    }

    if (activeTicket.value?.id === item.id) {
      activeTicket.value = item
    }
  }

  function clearTicketSelection() {
    selectedTickets.value = []
    tableRef.value?.elTableRef?.clearSelection?.()
  }

  function emptySummary(): TicketSummary {
    return {
      new_count: 0,
      processing_count: 0,
      resolved_count: 0,
      closed_count: 0,
      replied_count: 0
    }
  }

  function displayTicketType(ticket?: Partial<TicketItem> | null, fallback = '--') {
    return displayAdminFixtureText(ticket?.type_name_text || ticket?.type_name, fallback)
  }

  function displayTicketStatus(ticket?: Partial<TicketItem> | null, fallback = '--') {
    return displayAdminFixtureText(ticket?.status_text || ticket?.status_label, fallback)
  }

  function displayTicketReplyState(ticket?: Partial<TicketItem> | null, fallback = '--') {
    return displayAdminFixtureText(ticket?.reply_state_text || ticket?.reply_state_label, fallback)
  }

  function emptyReplyForm() {
    return {
      reply_content: '',
      status: '2'
    }
  }

  function buildTicketDeletePromptMessage(audit: Api.Tickets.TicketDeleteAudit, title: string) {
    return [
      `${title} 将被永久删除。`,
      '',
      `请输入 ${audit.confirmation_phrase} 以确认永久删除。`,
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function buildTicketBatchDeleteBlockedMessage(audit: Api.Tickets.TicketBatchDeleteAudit) {
    const blocked = audit.items.filter((item) => !item.can_delete)
    return [
      '所选工单当前还不能批量删除。',
      '',
      ...blocked.slice(0, 6).map((item) => {
        const label = displayAdminFixtureText(item.ticket_label, `工单 #${item.ticket_id}`)
        const reason = item.blocking_reasons.join(' ') || '请刷新工单列表后重试。'
        return `- ${label}: ${reason}`
      }),
      '',
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function buildTicketBatchDeletePromptMessage(audit: Api.Tickets.TicketBatchDeleteAudit) {
    return [
      `即将永久删除 ${audit.summary.deletable_count} 条工单记录。`,
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
  .ticket-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .ticket-cell,
  .merchant-cell,
  .reply-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .cell-title {
    color: #0f172a;
    font-size: 14px;
    word-break: break-all;
  }

  .cell-sub {
    margin: 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.6;
    word-break: break-all;
  }

  .ticket-detail {
    min-height: 240px;
  }

  .detail-hero {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
    padding: 20px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 18px;
    background: linear-gradient(135deg, rgb(248 250 252 / 0.96), rgb(241 245 249 / 0.92));
  }

  .detail-hero-copy {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .detail-hero-copy h3 {
    margin: 0;
    color: #0f172a;
    font-size: 20px;
  }

  .detail-hero-copy p,
  .detail-hero-copy span {
    margin: 0;
    color: #475569;
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
    color: #0f172a;
    font-size: 15px;
  }

  .content-box {
    min-height: 96px;
    padding: 14px 16px;
    color: #334155;
    line-height: 1.8;
    white-space: pre-wrap;
    word-break: break-word;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 14px;
    background: rgb(248 250 252 / 0.82);
  }

  .content-box.reply {
    background: rgb(240 253 244 / 0.72);
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
  }
</style>
