<template>
  <div class="admin-logs-page art-full-height">
    <ArtSearchBar
      v-model="searchForm"
      :items="searchItems"
      @search="handleSearch"
      @reset="handleReset"
    />

    <ElCard class="art-table-card">
      <ArtTableHeader
        v-model:columns="columnChecks"
        :loading="loading"
        layout="refresh"
        @refresh="refreshData"
      >
        <template #left>
          <ElSpace wrap>
            <ElTag effect="plain">后台日志 {{ pagination.total }}</ElTag>
            <ElButton
              v-if="hasAdminLogCleanupAuth"
              plain
              type="warning"
              :loading="cleanupLoading"
              @click="handleCleanupAdminLogs"
            >
              清理日志
            </ElButton>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        :loading="loading"
        :data="data"
        :columns="columns"
        :pagination="pagination"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <ElDrawer
      v-model="detailVisible"
      size="760px"
      destroy-on-close
      :title="
        activeLog
          ? `${displayLogText(activeLog.admin_display, '管理员')} / #${activeLog.id}`
          : '后台日志详情'
      "
    >
      <div v-loading="detailLoading" class="log-detail">
        <template v-if="activeLog">
          <div class="drawer-section">
            <div class="drawer-grid">
              <div class="drawer-item">
                <span>管理员</span>
                <strong>{{ displayLogText(activeLog.admin_display, '管理员') }}</strong>
              </div>
              <div class="drawer-item">
                <span>管理员编号</span>
                <strong>{{ activeLog.admin_id || '--' }}</strong>
              </div>
              <div class="drawer-item">
                <span>请求路径</span>
                <strong>{{ displayLogText(activeLog.path || activeLog.url, '--') }}</strong>
              </div>
              <div class="drawer-item">
                <span>来源地址</span>
                <strong>{{ activeLog.ip || '--' }}</strong>
              </div>
              <div class="drawer-item">
                <span>记录时间</span>
                <strong>{{ activeLog.create_time || '--' }}</strong>
              </div>
              <div class="drawer-item">
                <span>负载预览</span>
                <strong>{{ displayLogText(activeLog.payload_preview, '--') }}</strong>
              </div>
            </div>
          </div>

          <div class="drawer-section">
            <h4>请求元数据</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="完整地址">
                {{ displayLogText(activeLog.url, '--') }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="浏览器标识">
                {{ activeLog.user_agent || '--' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>请求负载</h4>
            <ElInput
              :model-value="displayLogText(activeLog.payload_text, '未记录请求负载。')"
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
  import { ElButton, ElMessage, ElMessageBox, ElTag } from 'element-plus'
  import { useAuth } from '@/hooks'
  import { useTable } from '@/hooks/core/useTable'
  import ArtButtonTable from '@/components/core/forms/artButtonTable/index.vue'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'
  import {
    fetchCleanupAdminLogs,
    fetchGetAdminLogCleanupAudit,
    fetchGetAdminLogDetail,
    fetchGetAdminLogList
  } from '@/api/logs'

  defineOptions({ name: 'SystemAdminLogs' })

  type AdminLogItem = Api.AdminLogs.LogListItem

  const { hasAuth } = useAuth()
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const cleanupLoading = ref(false)
  const activeLog = ref<AdminLogItem | null>(null)
  const searchForm = ref<{
    keyword?: string
    admin_id?: string
    date_range?: string[]
  }>({})
  const hasAdminLogCleanupAuth = computed(() => hasAuth('removeLog'))

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '可按管理员、路径、来源地址、负载内容或日志编号搜索'
      }
    },
    {
      label: '管理员编号',
      key: 'admin_id',
      type: 'input',
      props: {
        placeholder: '输入管理员编号筛选'
      }
    },
    {
      label: '日期',
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

  const {
    columns,
    columnChecks,
    data,
    loading,
    pagination,
    getData,
    replaceSearchParams,
    resetSearchParams,
    handleSizeChange,
    handleCurrentChange,
    refreshData
  } = useTable({
    core: {
      apiFn: fetchGetAdminLogList,
      apiParams: {
        current: 1,
        size: 20
      },
      columnsFactory: () => [
        { type: 'globalIndex', width: 70, label: '序号' },
        {
          prop: 'admin_display',
          label: '管理员',
          minWidth: 210,
          formatter: (row) =>
            h('div', { class: 'admin-cell' }, [
              h('strong', { class: 'cell-title' }, displayLogText(row.admin_display, '管理员')),
              h('p', { class: 'cell-sub' }, displayAdminMeta(row))
            ])
        },
        {
          prop: 'path',
          label: '请求路径',
          minWidth: 320,
          formatter: (row) =>
            h('div', { class: 'path-cell' }, [
              h('strong', { class: 'cell-title' }, displayLogText(row.path || row.url, '/')),
              h('p', { class: 'cell-sub' }, displayLogText(row.payload_preview, '--'))
            ])
        },
        {
          prop: 'ip',
          label: '来源地址',
          minWidth: 140
        },
        {
          prop: 'create_time',
          label: '记录时间',
          minWidth: 180,
          formatter: (row) => row.create_time || '--'
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
      ]
    }
  })

  function handleSearch(params: Record<string, unknown>) {
    const dateRange = Array.isArray(params.date_range) ? params.date_range : []
    replaceSearchParams({
      keyword: params.keyword as string | undefined,
      admin_id: params.admin_id as string | undefined,
      start_date: (dateRange[0] as string) || undefined,
      end_date: (dateRange[1] as string) || undefined
    })
    getData()
  }

  function handleReset() {
    resetSearchParams()
    getData()
  }

  function displayLogText(value: null | number | string | undefined, fallback = '--') {
    return displayAdminFixtureText(value, fallback)
  }

  function displayAdminMeta(row: AdminLogItem) {
    const normalizedUsername = displayLogText(row.admin_username, '')
    const adminId = row.admin_id || '--'
    return normalizedUsername
      ? `编号：${adminId} / 账号：${normalizedUsername}`
      : `编号：${adminId}`
  }

  async function openDetail(row: AdminLogItem) {
    detailVisible.value = true
    detailLoading.value = true
    activeLog.value = row

    try {
      const response = await fetchGetAdminLogDetail(row.id)
      activeLog.value = response.item
    } catch {
      ElMessage.error('加载后台日志详情失败。')
    } finally {
      detailLoading.value = false
    }
  }

  async function handleCleanupAdminLogs() {
    if (!hasAdminLogCleanupAuth.value) {
      ElMessage.warning('当前没有日志清理权限。')
      return
    }

    cleanupLoading.value = true
    try {
      const response = await fetchGetAdminLogCleanupAudit()
      const audit = response.audit

      if (!audit.can_cleanup) {
        await ElMessageBox.alert(audit.warnings.join('\n'), '当前没有可清理日志', {
          type: 'info',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildAdminLogCleanupPromptMessage(audit),
        '清理后台日志',
        {
          confirmButtonText: '立即清理',
          cancelButtonText: '取消',
          type: 'warning',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 后再继续。`
        }
      )

      const cleanupResponse = await fetchCleanupAdminLogs({
        confirmation_phrase: String(value || '')
      })

      detailVisible.value = false
      activeLog.value = null
      await getData()
      ElMessage.success(`已清理 ${cleanupResponse.deleted_count} 条后台日志。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('日志清理失败。')
    } finally {
      cleanupLoading.value = false
    }
  }

  function buildAdminLogCleanupPromptMessage(audit: Api.AdminLogs.LogCleanupAudit) {
    const summary = audit.summary
    return [
      '清理后会永久删除当前后台日志。',
      '',
      `日志条数：${summary.total_count}`,
      `涉及管理员：${summary.admin_count}`,
      `包含负载：${summary.payload_log_count}`,
      `ID 范围：${summary.first_log_id || 0} -> ${summary.last_log_id || 0}`,
      '',
      '系统会保留一条清理记录，便于追溯。',
      '',
      `请输入 ${audit.confirmation_phrase} 继续。`,
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
  .admin-logs-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
    --detail-card-border: var(--el-border-color-lighter);
    --detail-card-bg: rgb(248 250 252 / 0.82);
    --detail-title-color: #0f172a;
    --detail-muted-color: #64748b;
  }

  :global(html.dark .admin-logs-page ){
    --detail-card-border: rgb(71 85 105 / 0.42);
    --detail-card-bg: rgb(15 23 42 / 0.84);
    --detail-title-color: #e2e8f0;
    --detail-muted-color: #94a3b8;
  }

  .admin-cell,
  .path-cell {
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

  .log-detail {
    min-height: 240px;
  }

  .drawer-section {
    margin-bottom: 24px;
  }

  .drawer-section h4 {
    margin: 0 0 12px;
    color: var(--detail-title-color);
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

  @media (width <= 991px) {
    .drawer-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
