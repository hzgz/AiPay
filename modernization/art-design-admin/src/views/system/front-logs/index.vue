<template>
  <div class="front-logs-page art-full-height">
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
        layout="refresh"
        @refresh="getFrontLogList"
      >
        <template #left>
          <ElSpace wrap>
            <ElTag effect="plain">日志 {{ pagination.total }}</ElTag>
            <ElButton
              v-if="hasFrontLogBatchDeleteAuth"
              plain
              type="danger"
              :disabled="selectedLogs.length === 0"
              @click="handleBatchDeleteFrontLogs"
            >
              批量删除
            </ElButton>
            <ElButton
              v-if="hasFrontLogBatchDeleteAuth"
              plain
              type="warning"
              :loading="cleanupLoading"
              @click="handleCleanupFrontLogs"
            >
              全量清理
            </ElButton>
            <ElTag v-if="selectedLogs.length > 0" type="danger" effect="plain">
              已选 {{ selectedLogs.length }}
            </ElTag>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        ref="tableRef"
        :loading="loading"
        :data="logList"
        :columns="columns"
        :pagination="pagination"
        row-key="id"
        reserve-selection
        @selection-change="handleLogSelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <ElDrawer
      v-model="detailVisible"
      size="760px"
      destroy-on-close
      :title="activeLog ? `${activeLog.merchant_display} / #${activeLog.id}` : '商户日志详情'"
    >
      <div v-loading="detailLoading" class="front-log-detail">
        <template v-if="activeLog">
          <div class="detail-hero">
            <div class="detail-hero-copy">
              <h3>{{ displayFrontLogText(activeLog.path || activeLog.url, '/') }}</h3>
              <p>{{ activeLog.merchant_display }} / {{ activeLog.ip || '未知来源地址' }}</p>
              <span>{{ displayFrontLogText(activeLog.payload_preview, '--') }}</span>
            </div>
            <div class="detail-hero-actions">
              <ElButton
                v-if="canDeleteFrontLog(activeLog)"
                type="danger"
                plain
                @click="handleDeleteFrontLog()"
              >
                删除
              </ElButton>
            </div>
          </div>

          <div class="drawer-section">
            <ElDescriptions :column="2" border>
              <ElDescriptionsItem label="商户">
                {{ activeLog.merchant_display }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="商户编号">
                {{ activeLog.user_id || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="请求路径">
                {{ displayFrontLogText(activeLog.path || activeLog.url, '--') }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="创建时间">
                {{ activeLog.create_time || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="来源地址">
                {{ activeLog.ip || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="载荷状态">
                {{ activeLog.payload_is_empty ? '未捕获载荷' : '已捕获载荷' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>商户资料</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="用户名">
                {{ activeLog.merchant_username || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="联系人">
                {{ activeLog.merchant_name || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="邮箱">
                {{ activeLog.merchant_email || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="手机号">
                {{ activeLog.merchant_mobile || '--' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>请求信息</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="完整地址">
                {{ displayFrontLogText(activeLog.url, '--') }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="浏览器标识">
                {{ activeLog.user_agent || '--' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>请求载荷</h4>
            <ElInput
              :model-value="displayFrontLogText(activeLog.payload_text, '未捕获到载荷。')"
              type="textarea"
              :rows="10"
              readonly
            />
          </div>
        </template>
      </div>
    </ElDrawer>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox, ElTag } from 'element-plus'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import { useAuth } from '@/hooks'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'
  import {
    fetchAuditFrontLogBatchDelete,
    fetchBatchDeleteFrontLogs,
    fetchCleanupFrontLogs,
    fetchDeleteFrontLog,
    fetchGetFrontLogCleanupAudit,
    fetchGetFrontLogDeleteAudit,
    fetchGetFrontLogDetail,
    fetchGetFrontLogList
  } from '@/api/front-logs'

  defineOptions({ name: 'SystemFrontLogs' })

  type FrontLogItem = Api.FrontLogs.LogListItem
  type FrontLogSummary = Api.FrontLogs.LogSummary

  const tableRef = ref<{ elTableRef?: { clearSelection?: () => void } } | null>(null)
  const loading = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const cleanupLoading = ref(false)
  const logList = ref<FrontLogItem[]>([])
  const selectedLogs = ref<FrontLogItem[]>([])
  const activeLog = ref<FrontLogItem | null>(null)
  const { hasAuth } = useAuth()
  const pagination = reactive({
    current: 1,
    size: 20,
    total: 0
  })
  const summary = reactive<FrontLogSummary>(emptySummary())
  const searchForm = ref<{
    keyword?: string
    user_id?: string
    ip?: string
    date_range?: string[]
  }>({})
  const hasFrontLogDeleteAuth = computed(() => hasAuth('remove'))
  const hasFrontLogBatchDeleteAuth = computed(() => hasAuth('batchRemove'))

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '搜索商户、路径、来源地址、载荷或日志编号'
      }
    },
    {
      label: '商户编号',
      key: 'user_id',
      type: 'input',
      props: {
        placeholder: '按单个商户编号筛选'
      }
    },
    {
      label: '来源地址',
      key: 'ip',
      type: 'input',
      props: {
        placeholder: '按来源地址片段筛选'
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

  const { columnChecks, columns } = useTableColumns<FrontLogItem>(() => [
    { type: 'selection', width: 54, fixed: 'left' as const },
    { type: 'globalIndex', width: 70, label: '序号' },
    {
      prop: 'merchant_display',
      label: '商户',
      minWidth: 220,
      formatter: (row) =>
        h('div', { class: 'merchant-cell' }, [
          h('strong', { class: 'cell-title' }, row.merchant_display),
          h(
            'p',
            { class: 'cell-sub' },
            `编号：${row.user_id || '--'}${row.merchant_username ? ` / 用户名：${row.merchant_username}` : ''}`
          )
        ])
    },
    {
      prop: 'path',
      label: '请求',
      minWidth: 320,
      formatter: (row) =>
        h('div', { class: 'request-cell' }, [
          h('strong', { class: 'cell-title' }, displayFrontLogText(row.path || row.url, '/')),
          h('p', { class: 'cell-sub' }, displayFrontLogText(row.payload_preview, '--')),
          h(
            ElTag,
            {
              type: row.payload_is_empty ? 'info' : 'warning',
              effect: 'light',
              size: 'small'
            },
            () => (row.payload_is_empty ? '无载荷' : '已捕获载荷')
          )
        ])
    },
    {
      prop: 'ip',
      label: '来源',
      minWidth: 240,
      formatter: (row) =>
        h('div', { class: 'source-cell' }, [
          h('strong', { class: 'cell-title' }, row.ip || '--'),
          h('p', { class: 'cell-sub' }, row.user_agent_preview || '未知浏览器标识')
        ])
    },
    {
      prop: 'create_time',
      label: '创建时间',
      minWidth: 180,
      formatter: (row) => row.create_time || '--'
    },
    {
      prop: 'operation',
      label: '操作',
      width: 170,
      align: 'center' as const,
      fixed: 'right' as const,
      formatter: (row) => renderOperationButtons(row)
    }
  ])

  onMounted(() => {
    getFrontLogList()
  })

  function displayFrontLogText(value: null | number | string | undefined, fallback = '--') {
    return displayAdminFixtureText(value, fallback)
  }

  function renderOperationButtons(row: FrontLogItem) {
    const actions = [
      h(ArtButtonTable, {
        type: 'view',
        title: '详情',
        onClick: () => openDetail(row)
      })
    ]

    if (canDeleteFrontLog(row)) {
      actions.push(
        h(ArtButtonTable, {
          type: 'delete',
          title: '删除',
          onClick: () => handleDeleteFrontLog(row)
        })
      )
    }

    return h('div', { class: 'table-actions' }, actions)
  }

  function canDeleteFrontLog(item?: FrontLogItem | null) {
    return Boolean(item && hasFrontLogDeleteAuth.value)
  }

  async function getFrontLogList() {
    loading.value = true
    try {
      const response = await fetchGetFrontLogList({
        current: pagination.current,
        size: pagination.size,
        keyword: searchForm.value.keyword,
        user_id: searchForm.value.user_id,
        ip: searchForm.value.ip,
        start_date: searchForm.value.date_range?.[0],
        end_date: searchForm.value.date_range?.[1]
      })
      logList.value = response.records
      pagination.current = response.current
      pagination.size = response.size
      pagination.total = response.total
      Object.assign(summary, response.summary || emptySummary())
    } catch (_error) {
      ElMessage.error('加载前台日志列表失败。')
    } finally {
      loading.value = false
    }
  }

  function handleSearch(params: Record<string, unknown>) {
    pagination.current = 1
    clearLogSelection()
    searchForm.value = {
      keyword: params.keyword as string | undefined,
      user_id: params.user_id as string | undefined,
      ip: params.ip as string | undefined,
      date_range: Array.isArray(params.date_range) ? (params.date_range as string[]) : undefined
    }
    getFrontLogList()
  }

  function handleReset() {
    pagination.current = 1
    clearLogSelection()
    searchForm.value = {}
    getFrontLogList()
  }

  function handleSizeChange(size: number) {
    pagination.size = size
    pagination.current = 1
    clearLogSelection()
    getFrontLogList()
  }

  function handleCurrentChange(current: number) {
    pagination.current = current
    clearLogSelection()
    getFrontLogList()
  }

  function handleLogSelectionChange(rows: FrontLogItem[]) {
    selectedLogs.value = rows
  }

  async function openDetail(row: FrontLogItem) {
    detailVisible.value = true
    detailLoading.value = true
    activeLog.value = row

    try {
      const response = await fetchGetFrontLogDetail(row.id)
      activeLog.value = response.item
    } catch (_error) {
      ElMessage.error('加载商户日志详情失败。')
    } finally {
      detailLoading.value = false
    }
  }

  async function handleDeleteFrontLog(row?: FrontLogItem) {
    const target = row || activeLog.value
    if (!target) {
      return
    }

    if (!hasFrontLogDeleteAuth.value) {
      ElMessage.warning('您没有删除商户日志的权限。')
      return
    }

    try {
      const response = await fetchGetFrontLogDeleteAudit(target.id)
      const audit = response.audit
      const title = target.path || target.url || `商户日志 #${target.id}`

      const { value } = await ElMessageBox.prompt(
        buildFrontLogDeletePromptMessage(audit, title),
        '删除商户日志',
        {
          confirmButtonText: '删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以继续。`
        }
      )

      const deleteResponse = await fetchDeleteFrontLog(target.id, {
        confirmation_phrase: String(value || '')
      })

      if (activeLog.value?.id === target.id) {
        detailVisible.value = false
        activeLog.value = null
      }

      clearLogSelection()
      await getFrontLogList()
      ElMessage.success(
        `商户日志 ${deleteResponse.deleted_log_label || `#${target.id}`} 已永久删除。`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('删除商户日志失败。')
    }
  }

  async function handleBatchDeleteFrontLogs() {
    if (!hasFrontLogBatchDeleteAuth.value) {
      ElMessage.warning('您没有批量删除商户日志的权限。')
      return
    }

    if (selectedLogs.value.length === 0) {
      ElMessage.warning('请至少选择一条商户日志。')
      return
    }

    const logIds = selectedLogs.value.map((item) => item.id)

    try {
      const response = await fetchAuditFrontLogBatchDelete({
        log_ids: logIds
      })
      const audit = response.audit

      if (!audit.can_delete_all) {
        await ElMessageBox.alert(
          buildFrontLogBatchDeleteBlockedMessage(audit),
          '批量删除受限',
          {
            type: 'warning',
            confirmButtonText: '知道了'
          }
        )
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildFrontLogBatchDeletePromptMessage(audit),
        '批量删除前台日志',
        {
          confirmButtonText: '批量删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以继续。`
        }
      )

      const deleteResponse = await fetchBatchDeleteFrontLogs({
        log_ids: logIds,
        confirmation_phrase: String(value || '')
      })

      if (activeLog.value && deleteResponse.deleted_log_ids.includes(activeLog.value.id)) {
        detailVisible.value = false
        activeLog.value = null
      }

      clearLogSelection()
      await getFrontLogList()
      ElMessage.success(`已永久删除 ${deleteResponse.deleted_count} 条商户日志。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('批量删除商户日志失败。')
    }
  }

  async function handleCleanupFrontLogs() {
    if (!hasFrontLogBatchDeleteAuth.value) {
      ElMessage.warning('您没有清理商户日志的权限。')
      return
    }

    cleanupLoading.value = true
    try {
      const response = await fetchGetFrontLogCleanupAudit()
      const audit = response.audit

      if (!audit.can_cleanup) {
        await ElMessageBox.alert(audit.warnings.join('\n'), '暂无可清理数据', {
          type: 'info',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildFrontLogCleanupPromptMessage(audit),
        '清理全部商户日志',
        {
          confirmButtonText: '执行清理',
          cancelButtonText: '取消',
          type: 'warning',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以继续。`
        }
      )

      const cleanupResponse = await fetchCleanupFrontLogs({
        confirmation_phrase: String(value || '')
      })

      detailVisible.value = false
      activeLog.value = null
      clearLogSelection()
      await getFrontLogList()
      ElMessage.success(`已清理 ${cleanupResponse.deleted_count} 条商户日志。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('清理商户日志失败。')
    } finally {
      cleanupLoading.value = false
    }
  }

  function clearLogSelection() {
    selectedLogs.value = []
    tableRef.value?.elTableRef?.clearSelection?.()
  }

  function emptySummary(): FrontLogSummary {
    return {
      total_count: 0,
      merchant_count: 0,
      payload_count: 0,
      today_count: 0
    }
  }

  function buildFrontLogDeletePromptMessage(audit: Api.FrontLogs.LogDeleteAudit, title: string) {
    return [
      `${title} 将被永久删除。`,
      '',
      `请输入 ${audit.confirmation_phrase} 以确认永久删除。`,
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function buildFrontLogBatchDeleteBlockedMessage(audit: Api.FrontLogs.LogBatchDeleteAudit) {
    const blocked = audit.items.filter((item) => !item.can_delete)
    return [
      '当前所选商户日志暂不能批量删除。',
      '',
      ...blocked.slice(0, 6).map((item) => {
        const label = item.log_label || `商户日志 #${item.log_id}`
        const reason = item.blocking_reasons.join(' ') || '请刷新列表后重试。'
        return `- ${label}：${reason}`
      }),
      '',
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function buildFrontLogBatchDeletePromptMessage(audit: Api.FrontLogs.LogBatchDeleteAudit) {
    return [
      `将永久删除 ${audit.summary.deletable_count} 条商户日志。`,
      '',
      `包含负载的记录：${audit.summary.payload_log_count}`,
      `涉及商户数：${audit.summary.merchant_count}`,
      '',
      `请输入 ${audit.confirmation_phrase} 以确认批量删除。`,
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function buildFrontLogCleanupPromptMessage(audit: Api.FrontLogs.LogCleanupAudit) {
    const summary = audit.summary
    return [
      '执行全量清理后，会直接清空整张商户日志表。',
      '',
      `记录数：${summary.total_count}`,
      `包含负载的记录：${summary.payload_log_count}`,
      `涉及商户数：${summary.merchant_count}`,
      '',
      `请输入 ${audit.confirmation_phrase} 以确认全部清理。`,
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
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
</script>

<style scoped lang="scss">
  .front-logs-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .merchant-cell,
  .request-cell,
  .source-cell,
  .detail-hero-copy {
    display: flex;
    flex-direction: column;
    gap: 6px;
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

  .front-log-detail {
    min-height: 240px;
  }

  .detail-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
    padding: 20px;
    border: 1px solid rgb(251 191 36 / 0.24);
    border-radius: 18px;
    background:
      linear-gradient(135deg, rgb(255 251 235 / 0.98), rgb(255 247 237 / 0.96)),
      radial-gradient(circle at top right, rgb(251 191 36 / 0.14), transparent 52%);
  }

  .detail-hero-copy h3 {
    margin: 0;
    color: #0f172a;
    font-size: 18px;
    word-break: break-all;
  }

  .detail-hero-copy p,
  .detail-hero-copy span {
    margin: 0;
    color: #6b7280;
    line-height: 1.7;
    word-break: break-all;
  }

  .drawer-section {
    margin-bottom: 24px;
  }

  .drawer-section h4 {
    margin: 0 0 12px;
    color: #0f172a;
    font-size: 15px;
  }

  @media (width <= 991px) {
    .detail-hero {
      flex-direction: column;
    }
  }
</style>
