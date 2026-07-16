<template>
  <div class="system-processes-page art-full-height">
    <ElRow :gutter="20">
      <ElCol :xs="24" :lg="12">
        <ElCard class="hero-card" shadow="never">
          <div class="hero-content">
            <div>
              <h2 class="hero-title">进程管理</h2>
              <div class="hero-chips">
                <ElTag effect="plain">{{ displayOsFamily(environment?.os_family) }}</ElTag>
                <ElTag effect="plain">主服务实例 {{ summary.supervisor_total }}</ElTag>
                <ElTag
                  :type="monitor.paused ? 'warning' : monitor.running ? 'success' : 'info'"
                  effect="plain"
                >
                  {{ monitorStatusLabel }}
                </ElTag>
              </div>
            </div>

            <div class="hero-actions">
              <ElButton :loading="loading" @click="loadData">刷新</ElButton>
              <ElButton
                v-if="monitor.paused"
                type="primary"
                plain
                :disabled="!hasResumeMonitorAuth"
                :loading="actionLoading === 'resume'"
                @click="handleResumeMonitor"
              >
                恢复巡检
              </ElButton>
              <ElButton
                v-else
                type="warning"
                plain
                :disabled="!hasPauseMonitorAuth"
                :loading="actionLoading === 'pause'"
                @click="handlePauseMonitor"
              >
                暂停巡检
              </ElButton>
              <ElButton
                v-if="showDuplicateCleanupAction"
                type="danger"
                plain
                :disabled="!cleanupPreview.can_cleanup || !hasCleanupSupervisorsAuth"
                :loading="actionLoading === 'cleanup'"
                @click="handleCleanupDuplicateSupervisors"
              >
                清理重复进程
              </ElButton>
            </div>
          </div>
        </ElCard>
      </ElCol>

      <ElCol :xs="12" :sm="6" :lg="3">
        <ElCard class="metric-card core" shadow="never">
          <span class="metric-label">核心进程</span>
          <strong class="metric-value">{{ summary.core_total }}</strong>
        </ElCard>
      </ElCol>

      <ElCol :xs="12" :sm="6" :lg="3">
        <ElCard class="metric-card worker" shadow="never">
          <span class="metric-label">在线进程</span>
          <strong class="metric-value">{{ summary.core_worker_total }}</strong>
        </ElCard>
      </ElCol>

      <ElCol :xs="12" :sm="6" :lg="3">
        <ElCard class="metric-card plugin" shadow="never">
          <span class="metric-label">插件任务</span>
          <strong class="metric-value">{{ pluginProcessCount }}</strong>
        </ElCard>
      </ElCol>

      <ElCol :xs="12" :sm="6" :lg="3">
        <ElCard class="metric-card monitor" shadow="never">
          <span class="metric-label">巡检状态</span>
          <strong class="metric-value metric-status">{{ monitorStatusShort }}</strong>
        </ElCard>
      </ElCol>
    </ElRow>

    <div v-if="duplicateSupervisorNotice" class="process-alert-bar">
      <ElAlert
        type="warning"
        :closable="false"
        show-icon
        class="process-alert"
        :title="duplicateSupervisorNotice"
      />

      <ElButton
        v-if="showDuplicateCleanupAction"
        type="danger"
        plain
        class="process-alert-action"
        :disabled="!cleanupPreview.can_cleanup || !hasCleanupSupervisorsAuth"
        :loading="actionLoading === 'cleanup'"
        @click="handleCleanupDuplicateSupervisors"
      >
        立即清理
      </ElButton>
    </div>

    <ElCard
      v-if="cleanupPreview.can_cleanup || cleanupPreview.warnings.length"
      class="cleanup-card"
      shadow="never"
    >
      <div class="cleanup-head">
        <div>
          <h3>重复进程提醒</h3>
        </div>

        <ElButton
          v-if="cleanupPreview.can_cleanup"
          type="danger"
          plain
          :disabled="!hasCleanupSupervisorsAuth"
          :loading="actionLoading === 'cleanup'"
          @click="handleCleanupDuplicateSupervisors"
        >
          清理重复进程
        </ElButton>
      </div>

      <div class="cleanup-tags">
        <ElTag v-if="cleanupPreview.keep_supervisor_pid" effect="plain">
          保留主服务 #{{ cleanupPreview.keep_supervisor_pid }}
        </ElTag>
        <ElTag type="warning" effect="plain">
          待清理主服务 {{ cleanupPreview.remove_supervisor_pids.length }}
        </ElTag>
        <ElTag type="warning" effect="plain">
          待清理子进程 {{ cleanupPreview.remove_worker_pids.length }}
        </ElTag>
        <ElTag effect="plain">
          主服务进程 {{ cleanupPreview.current_webman_worker_total }} /
          {{ cleanupPreview.expected_webman_worker_total }}
        </ElTag>
        <ElTag effect="plain">
          巡检进程 {{ cleanupPreview.current_monitor_worker_total }} /
          {{ cleanupPreview.expected_monitor_worker_total }}
        </ElTag>
        <ElTag
          v-for="warning in cleanupPreview.warnings"
          :key="warning"
          type="danger"
          effect="plain"
        >
          {{ warning }}
        </ElTag>
      </div>
    </ElCard>

    <ElCard class="panel-card" shadow="never">
      <template #header>
        <div class="panel-head">
          <div>
            <h3>核心进程</h3>
          </div>
        </div>
      </template>

      <ElTable :data="coreProcesses" class="compact-table" v-loading="loading">
        <ElTableColumn prop="title" label="进程" min-width="180">
          <template #default="{ row }">
            <div class="stack-cell">
              <strong>{{ row.title }}</strong>
              <span class="sub-copy">{{ row.key }}</span>
            </div>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="handler" label="运行入口" min-width="220">
          <template #default="{ row }">
            <span class="mono-text">{{ displayText(row.handler) }}</span>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="listen" label="监听地址" min-width="180">
          <template #default="{ row }">
            <span class="mono-text">{{ displayText(row.listen) }}</span>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="configured_workers" label="预设数量" width="120" align="center">
          <template #default="{ row }">
            {{ row.configured_workers ?? '未配置' }}
          </template>
        </ElTableColumn>
        <ElTableColumn prop="process_count" label="在线进程" width="120" align="center" />
        <ElTableColumn label="状态" width="120" align="center">
          <template #default="{ row }">
            <ElTag :type="row.running ? 'success' : 'info'" effect="plain">
              {{ row.running ? '运行中' : '未运行' }}
            </ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="监听端口" width="120" align="center">
          <template #default="{ row }">
            <ElTag :type="row.listening ? 'success' : 'info'" effect="plain">
              {{ row.listening ? '已监听' : '未监听' }}
            </ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="进程序号" min-width="220">
          <template #default="{ row }">
            <div v-if="row.workers.length" class="pid-tags">
              <ElTag
                v-for="worker in row.workers.slice(0, 5)"
                :key="`${row.key}-${worker.pid}`"
                effect="plain"
              >
                #{{ worker.pid }}
              </ElTag>
              <ElTag v-if="row.workers.length > 5" type="info" effect="plain">
                +{{ row.workers.length - 5 }}
              </ElTag>
            </div>
            <span v-else class="empty-copy">未检测到</span>
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <ElRow :gutter="20">
      <ElCol :xs="24" :xl="12">
        <ElCard class="panel-card" shadow="never">
          <template #header>
            <div class="panel-head">
              <div>
                <h3>插件任务</h3>
              </div>
            </div>
          </template>

          <ElTable v-if="pluginProcesses.length" :data="pluginProcesses" class="compact-table">
            <ElTableColumn label="插件 / 任务" min-width="220">
              <template #default="{ row }">
                <div class="stack-cell">
                  <strong>{{ row.title }}</strong>
                  <span class="sub-copy">
                    {{ displayText(row.plugin_name || row.plugin_code, '未命名插件') }} /
                    {{ row.key }}
                  </span>
                </div>
              </template>
            </ElTableColumn>
            <ElTableColumn prop="source_label" label="来源" width="150" />
            <ElTableColumn prop="handler" label="运行入口" min-width="220">
              <template #default="{ row }">
                <span class="mono-text">{{ displayText(row.handler) }}</span>
              </template>
            </ElTableColumn>
            <ElTableColumn prop="listen" label="监听地址" min-width="160">
              <template #default="{ row }">
                <span class="mono-text">{{ displayText(row.listen) }}</span>
              </template>
            </ElTableColumn>
            <ElTableColumn label="状态" width="110" align="center">
              <template #default="{ row }">
                <ElTag :type="row.running ? 'success' : 'info'" effect="plain">
                  {{ row.running ? '运行中' : '未运行' }}
                </ElTag>
              </template>
            </ElTableColumn>
          </ElTable>

          <ElEmpty v-else description="暂无插件任务" />
        </ElCard>
      </ElCol>

      <ElCol :xs="24" :xl="12">
        <ElCard class="panel-card" shadow="never">
          <template #header>
            <div class="panel-head">
              <div>
                <h3>运行时文件</h3>
              </div>
            </div>
          </template>

          <ElTable :data="runtimeFiles" class="compact-table">
            <ElTableColumn prop="label" label="文件" width="140" />
            <ElTableColumn label="状态" width="110" align="center">
              <template #default="{ row }">
                <ElTag :type="row.exists ? 'success' : 'info'" effect="plain">
                  {{ row.exists ? '存在' : '缺失' }}
                </ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn prop="size" label="大小" width="100" align="right">
              <template #default="{ row }">
                {{ formatFileSize(row.size) }}
              </template>
            </ElTableColumn>
            <ElTableColumn prop="updated_at" label="更新时间" width="170" />
            <ElTableColumn prop="path" label="路径" min-width="280">
              <template #default="{ row }">
                <span class="mono-text break-all">{{ row.path }}</span>
              </template>
            </ElTableColumn>
          </ElTable>
        </ElCard>
      </ElCol>
    </ElRow>

    <ElRow :gutter="20">
      <ElCol :xs="24" :xl="14">
        <ElCard class="panel-card" shadow="never">
          <template #header>
            <div class="panel-head">
              <div>
                <h3>主服务进程</h3>
              </div>
            </div>
          </template>

          <ElTable v-if="supervisors.length" :data="supervisors" class="compact-table">
            <ElTableColumn prop="pid" label="进程序号" width="100" />
            <ElTableColumn prop="started_at" label="启动时间" width="180" />
            <ElTableColumn label="建议" width="110" align="center">
              <template #default="{ row }">
                <ElTag :type="supervisorActionType(row.pid)" effect="plain">
                  {{ supervisorActionLabel(row.pid) }}
                </ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn prop="command_line" label="命令行" min-width="420">
              <template #default="{ row }">
                <span class="mono-text break-all">{{ displayText(row.command_line) }}</span>
              </template>
            </ElTableColumn>
          </ElTable>

          <ElEmpty v-else description="未检测到主服务" />
        </ElCard>
      </ElCol>

      <ElCol :xs="24" :xl="10">
        <ElCard class="panel-card" shadow="never">
          <template #header>
            <div class="panel-head">
              <div>
                <h3>运行环境</h3>
              </div>
            </div>
          </template>

          <ElDescriptions class="process-descriptions" :column="1" border>
            <ElDescriptionsItem label="系统">
              {{ displayOsFamily(environment?.os_family) }}
            </ElDescriptionsItem>
            <ElDescriptionsItem label="PHP">
              <span class="mono-text break-all">{{ displayText(environment?.php_binary) }}</span>
            </ElDescriptionsItem>
            <ElDescriptionsItem label="服务监听">
              <span class="mono-text break-all">{{ displayText(environment?.server_listen) }}</span>
            </ElDescriptionsItem>
            <ElDescriptionsItem label="项目目录">
              <span class="mono-text break-all">{{ displayText(environment?.project_root) }}</span>
            </ElDescriptionsItem>
            <ElDescriptionsItem label="运行目录">
              <span class="mono-text break-all">{{ displayText(environment?.runtime_root) }}</span>
            </ElDescriptionsItem>
            <ElDescriptionsItem label="监控锁文件">
              <span class="mono-text break-all">{{ displayText(monitor.lock_file) }}</span>
            </ElDescriptionsItem>
          </ElDescriptions>
        </ElCard>
      </ElCol>
    </ElRow>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox } from 'element-plus'
  import { useAuth } from '@/hooks'
  import {
    fetchCleanupDuplicateSupervisors,
    fetchGetProcessOverview,
    fetchPauseProcessMonitor,
    fetchResumeProcessMonitor
  } from '@/api/processes'

  defineOptions({ name: 'SystemProcesses' })

  type ProcessItem = Api.SystemManage.ProcessDefinition
  type SupervisorItem = Api.SystemManage.ProcessWorkerRecord

  const loading = ref(false)
  const actionLoading = ref<'pause' | 'resume' | 'cleanup' | ''>('')
  const overview = ref<Api.SystemManage.ProcessOverviewResponse | null>(null)
  const { hasAuth } = useAuth()

  const summary = computed(
    () =>
      overview.value?.summary || {
        core_total: 0,
        core_running_total: 0,
        core_worker_total: 0,
        plugin_total: 0,
        plugin_running_total: 0,
        payment_plugin_total: 0,
        payment_plugin_manifest_process_total: 0,
        supervisor_total: 0,
        monitor_running: false,
        monitor_paused: false
      }
  )

  const environment = computed(() => overview.value?.environment || null)
  const monitor = computed(
    () =>
      overview.value?.monitor || {
        running: false,
        paused: false,
        lock_file: '',
        paused_at: null,
        process_count: 0,
        workers: []
      }
  )
  const coreProcesses = computed<ProcessItem[]>(() => overview.value?.core_processes || [])
  const runtimeFiles = computed(() => overview.value?.runtime_files || [])
  const supervisors = computed<SupervisorItem[]>(() => overview.value?.supervisors.items || [])
  const cleanupPreview = computed<Api.SystemManage.ProcessDuplicateCleanupPreview>(() => {
    return (
      overview.value?.duplicate_cleanup || {
        can_cleanup: false,
        strategy: '',
        summary: '当前没有检测到可清理的重复主服务进程。',
        keep_supervisor_pid: null,
        keep_supervisor: null,
        keep_workers: [],
        remove_supervisors: [],
        remove_workers: [],
        remove_supervisor_pids: [],
        remove_worker_pids: [],
        current_webman_worker_total: 0,
        current_monitor_worker_total: 0,
        expected_webman_worker_total: 0,
        expected_monitor_worker_total: 0,
        warnings: []
      }
    )
  })
  const pluginProcesses = computed(() => {
    const registered = (overview.value?.plugin_processes || []).map((item) => ({
      ...item,
      source_label: '系统注册'
    }))
    const manifests = (overview.value?.payment_plugin_manifest_processes || []).map((item) => ({
      ...item,
      source_label: '支付插件清单'
    }))

    return [...registered, ...manifests]
  })

  const pluginProcessCount = computed(() => pluginProcesses.value.length)
  const hasPauseMonitorAuth = computed(() => hasAuth('pauseMonitor') || hasAuth('index'))
  const hasResumeMonitorAuth = computed(() => hasAuth('resumeMonitor') || hasAuth('index'))
  const hasCleanupSupervisorsAuth = computed(
    () => hasAuth('cleanupSupervisors') || hasAuth('index')
  )
  const showDuplicateCleanupAction = computed(() => {
    return (
      cleanupPreview.value.can_cleanup ||
      cleanupPreview.value.remove_supervisor_pids.length > 0 ||
      cleanupPreview.value.remove_worker_pids.length > 0 ||
      summary.value.supervisor_total > 1
    )
  })
  const duplicateSupervisorNotice = computed(() => {
    if (summary.value.supervisor_total <= 1) {
      return ''
    }

    return `检测到 ${summary.value.supervisor_total} 个主服务实例，存在重复启动，请确认后清理。`
  })
  const monitorStatusLabel = computed(() => {
    if (monitor.value.paused) {
      return '进程巡检已暂停'
    }

    if (monitor.value.running) {
      return '进程巡检运行中'
    }

    return '进程巡检未运行'
  })
  const monitorStatusShort = computed(() => {
    if (monitor.value.paused) {
      return '已暂停'
    }

    if (monitor.value.running) {
      return '运行中'
    }

    return '未运行'
  })

  onMounted(() => {
    loadData()
  })

  async function loadData() {
    loading.value = true
    try {
      overview.value = await fetchGetProcessOverview()
    } finally {
      loading.value = false
    }
  }

  async function handlePauseMonitor() {
    actionLoading.value = 'pause'
    try {
      overview.value = await fetchPauseProcessMonitor()
      ElMessage.success('进程巡检已暂停')
    } finally {
      actionLoading.value = ''
    }
  }

  async function handleResumeMonitor() {
    actionLoading.value = 'resume'
    try {
      overview.value = await fetchResumeProcessMonitor()
      ElMessage.success('进程巡检已恢复')
    } finally {
      actionLoading.value = ''
    }
  }

  async function handleCleanupDuplicateSupervisors() {
    const preview = cleanupPreview.value
    if (!preview.can_cleanup) {
      ElMessage.warning('当前没有可清理的重复进程')
      return
    }

    const summaryLines = [
      preview.summary,
      `保留主服务：#${preview.keep_supervisor_pid || '--'}`,
      `待清理主服务：${preview.remove_supervisor_pids.length} 个`,
      `待清理子进程：${preview.remove_worker_pids.length} 个`
    ]

    if (preview.warnings.length) {
      summaryLines.push(`注意：${preview.warnings.join('；')}`)
    }

    await ElMessageBox.confirm(summaryLines.join('\n'), '清理重复进程', {
      type: 'warning',
      confirmButtonText: '确认清理',
      cancelButtonText: '取消'
    })

    actionLoading.value = 'cleanup'
    try {
      overview.value = await fetchCleanupDuplicateSupervisors()
      ElMessage.success('重复进程已清理')
    } finally {
      actionLoading.value = ''
    }
  }

  function supervisorActionLabel(pid: number) {
    if (cleanupPreview.value.keep_supervisor_pid === pid) {
      return '保留'
    }

    if (cleanupPreview.value.remove_supervisor_pids.includes(pid)) {
      return '清理'
    }

    return '观察'
  }

  function supervisorActionType(pid: number) {
    if (cleanupPreview.value.keep_supervisor_pid === pid) {
      return 'success'
    }

    if (cleanupPreview.value.remove_supervisor_pids.includes(pid)) {
      return 'warning'
    }

    return 'info'
  }

  function displayText(value: null | string | undefined, fallback = '未检测到') {
    const normalized = String(value || '').trim()
    return normalized === '' ? fallback : normalized
  }

  function displayOsFamily(value: null | string | undefined) {
    const normalized = String(value || '')
      .trim()
      .toLowerCase()

    if (normalized === 'windows') {
      return 'Windows 系统'
    }

    if (normalized === 'linux') {
      return 'Linux 系统'
    }

    if (normalized === 'darwin' || normalized === 'mac' || normalized === 'macos') {
      return 'macOS 系统'
    }

    return displayText(value)
  }

  function formatFileSize(size: null | number | undefined) {
    if (size === null || size === undefined || Number.isNaN(size)) {
      return '--'
    }

    if (size < 1024) {
      return `${size} B`
    }

    if (size < 1024 * 1024) {
      return `${(size / 1024).toFixed(1)} KB`
    }

    return `${(size / (1024 * 1024)).toFixed(2)} MB`
  }
</script>

<style scoped lang="scss">
  .system-processes-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .hero-card,
  .metric-card,
  .panel-card {
    border: 1px solid var(--el-border-color-light);
  }

  .process-alert {
    border-radius: 16px;
    flex: 1;
  }

  .process-alert-bar {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .process-alert-action {
    flex-shrink: 0;
  }

  .cleanup-card {
    border: 1px solid rgb(251 191 36 / 0.28);
    background: linear-gradient(180deg, rgb(255 251 235 / 0.92), rgb(255 255 255 / 1));
  }

  .cleanup-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
  }

  .cleanup-head h3 {
    margin: 0;
    color: #92400e;
    font-size: 18px;
  }

  .cleanup-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 16px;
  }

  .hero-card {
    background:
      radial-gradient(circle at top right, rgb(34 197 94 / 0.18), transparent 38%),
      linear-gradient(135deg, rgb(240 253 244 / 0.96), rgb(255 255 255 / 1));
  }

  .metric-card.core {
    background: linear-gradient(180deg, rgb(239 246 255 / 0.92), rgb(255 255 255 / 1));
  }

  .metric-card.worker {
    background: linear-gradient(180deg, rgb(236 253 245 / 0.92), rgb(255 255 255 / 1));
  }

  .metric-card.plugin {
    background: linear-gradient(180deg, rgb(255 251 235 / 0.92), rgb(255 255 255 / 1));
  }

  .metric-card.monitor {
    background: linear-gradient(180deg, rgb(248 250 252 / 0.96), rgb(255 255 255 / 1));
  }

  .hero-content {
    min-height: 164px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
  }

  .hero-title {
    margin: 0;
    color: #0f172a;
    font-size: 30px;
    line-height: 1.1;
    font-weight: 700;
  }

  .hero-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 16px;
  }

  .hero-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .metric-card {
    height: 100%;
    min-height: 164px;
  }

  .metric-card :deep(.el-card__body) {
    display: flex;
    min-height: 164px;
    flex-direction: column;
    justify-content: center;
    gap: 8px;
  }

  .metric-label {
    color: #166534;
    font-size: 13px;
    font-weight: 600;
  }

  .metric-value {
    color: #0f172a;
    font-size: 36px;
    line-height: 1;
    font-variant-numeric: tabular-nums;
  }

  .metric-status {
    font-size: 26px;
  }

  .panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
  }

  .panel-head h3 {
    margin: 0;
    color: #0f172a;
    font-size: 18px;
  }

  .stack-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .stack-cell strong {
    color: #0f172a;
  }

  .sub-copy {
    color: #64748b;
    font-size: 12px;
  }

  .empty-copy {
    color: #94a3b8;
    font-size: 12px;
  }

  .mono-text {
    color: #334155;
    font-family: 'JetBrains Mono', 'Cascadia Code', monospace;
    font-size: 12px;
  }

  .break-all {
    word-break: break-all;
  }

  .pid-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }

  .compact-table :deep(.el-table__cell) {
    vertical-align: top;
  }

  .process-descriptions :deep(.el-descriptions__label) {
    width: 112px;
  }

  @media (width <= 991px) {
    .hero-content,
    .panel-head,
    .process-alert-bar {
      flex-direction: column;
      align-items: flex-start;
    }

    .hero-actions {
      justify-content: flex-start;
    }

    .process-alert {
      width: 100%;
    }
  }
</style>
