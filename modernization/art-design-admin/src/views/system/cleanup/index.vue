<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <div class="cleanup-page art-full-height">
    <div class="cleanup-scroll-area">
    <ElCard class="cache-card" shadow="never">
      <template #header>
        <div class="cache-card-header">
          <div class="cache-card-copy">
            <h3>数据清理</h3>
            <p>统一管理订单、充值、后台日志和商户日志的清理审计，Redis 和浏览器缓存工具保留在这里。</p>
          </div>

          <div class="cache-card-aside">
            <ElSpace wrap class="cache-card-tags">
              <ElTag effect="plain">服务端目录 {{ cacheAudit.server_summary.target_count }}</ElTag>
              <ElTag type="primary" effect="plain">
                服务端体积 {{ cacheAudit.server_summary.size_label }}
              </ElTag>
              <ElTag type="success" effect="plain">
                当前浏览器 {{ browserCachePreview.approxSizeLabel }}
              </ElTag>
              <ElTag type="warning" effect="plain">
                可清理目录 {{ cacheAudit.server_summary.clearable_target_count }}
              </ElTag>
            </ElSpace>

            <ElSpace wrap class="cache-card-actions">
              <ElButton :loading="cacheAuditLoading" @click="refreshCacheAudit">刷新状态</ElButton>
              <ElButton
                plain
                type="warning"
                :disabled="!hasCleanupExecuteAuth"
                :loading="serverCacheClearing"
                @click="handleClearServerCache(['hot_path_redis'])"
              >
                清 Redis
              </ElButton>
              <ElButton
                plain
                type="warning"
                :disabled="!hasCleanupExecuteAuth"
                :loading="serverCacheClearing"
                @click="() => handleClearServerCache()"
              >
                清服务端
              </ElButton>
              <ElButton
                plain
                type="danger"
                :loading="browserCacheClearing"
                @click="handleClearBrowserCache"
              >
                清浏览器
              </ElButton>
              <ElButton
                type="danger"
                :disabled="!hasCleanupExecuteAuth"
                :loading="serverCacheClearing || browserCacheClearing"
                @click="handleClearAllCaches"
              >
                一键清理
              </ElButton>
            </ElSpace>
          </div>
        </div>
      </template>

      <div class="cache-target-grid">
        <article
          v-for="target in cacheAudit.server_targets"
          :key="target.key"
          class="cache-target-card"
        >
          <div class="cache-target-head">
            <div>
              <h4>{{ target.title }}</h4>
            </div>
            <ElTag :type="target.clearable ? 'warning' : 'success'" effect="light">
              {{ target.size_label }}
            </ElTag>
          </div>

          <p class="cache-target-path mono-text">{{ target.relative_path }}</p>

          <div class="cache-target-facts">
            <div class="cache-target-fact">
              <span>文件</span>
              <strong>{{ target.file_count }}</strong>
            </div>
            <div class="cache-target-fact">
              <span>目录</span>
              <strong>{{ target.directory_count }}</strong>
            </div>
            <div class="cache-target-fact">
              <span>总项数</span>
              <strong>{{ target.entry_count }}</strong>
            </div>
          </div>
        </article>

        <article class="cache-target-card browser-target-card">
          <div class="cache-target-head">
            <div>
              <h4>浏览器缓存</h4>
            </div>
            <ElTag type="danger" effect="light">{{ browserCachePreview.approxSizeLabel }}</ElTag>
          </div>

          <div class="browser-cache-grid">
            <div class="browser-cache-item">
              <span>本地存储键</span>
              <strong>{{ browserCachePreview.localStorageKeys.length }}</strong>
            </div>
            <div class="browser-cache-item">
              <span>会话键</span>
              <strong>{{ browserCachePreview.sessionStorageKeys.length }}</strong>
            </div>
            <div class="browser-cache-item">
              <span>缓存仓</span>
              <strong>{{ browserCachePreview.cacheStorageKeys.length }}</strong>
            </div>
            <div class="browser-cache-item">
              <span>缓存项</span>
              <strong>{{ browserCachePreview.cacheEntryCount }}</strong>
            </div>
          </div>

          <div class="browser-cache-notes">
            <div class="browser-cache-note">
              <span>缓存前缀</span>
              <strong class="mono-text">{{ browserCachePrefix }}</strong>
            </div>
            <div class="browser-cache-note">
              <span>托管键</span>
              <strong>{{ browserManagedLocalKeys.length }}</strong>
            </div>
            <div class="browser-cache-note">
              <span>影响范围</span>
              <strong>仅当前浏览器</strong>
            </div>
          </div>
        </article>
      </div>
    </ElCard>

      <div class="cleanup-search-bar">
        <ArtSearchBar
          v-model="searchForm"
          :items="searchItems"
          :showExpand="false"
          @search="handleSearch"
          @reset="handleReset"
        />
      </div>

    <ElCard class="art-table-card" shadow="never">
      <ArtTableHeader
        v-model:columns="columnChecks"
        :loading="loading"
        layout="refresh"
        @refresh="refreshAllCleanupData"
      >
        <template #left>
          <ElSpace wrap>
            <ElTag effect="plain">清理项 {{ pagination.total }}</ElTag>
            <ElTag type="warning" effect="plain">建议 {{ summary.recommended_count }}</ElTag>
            <ElTag type="primary" effect="plain">待清理 {{ summary.target_row_count }}</ElTag>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        :loading="loading"
        :data="cleanupItems"
        :columns="columns"
        :pagination="pagination"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>
    </div>

    <ElDrawer
      v-model="detailVisible"
      size="820px"
      destroy-on-close
      :title="
        activeItem
          ? `${displayCleanupText(activeItem.title)} / ${displayCleanupText(activeItem.category_label)}`
          : '清理详情'
      "
    >
      <div v-loading="detailLoading" class="cleanup-detail">
        <template v-if="activeItem">
          <section class="detail-hero">
            <div class="detail-hero-copy">
              <h3>{{ displayCleanupText(activeItem.title) }}</h3>
              <p>{{ displayCleanupText(activeItem.action_scope_label) }}</p>
            </div>

            <div class="detail-hero-actions">
              <ElTag :type="tagType(activeItem.status_type)" effect="light">
                {{ displayCleanupText(activeItem.status_label) }}
              </ElTag>
              <ElTag :type="tagType(activeItem.action_mode_type)" effect="plain">
                {{ displayCleanupText(activeItem.action_mode_label) }}
              </ElTag>
              <ElButton
                v-if="canExecuteCleanup(activeItem)"
                plain
                type="danger"
                :disabled="!activeItem.action_available"
                :loading="executingKey === activeItem.key"
                @click="handleExecuteCleanup()"
              >
                执行
              </ElButton>
            </div>
          </section>

          <section class="detail-section">
            <h4>当前状态</h4>
            <div class="detail-grid">
              <div class="detail-item">
                <span>目标表</span>
                <strong class="mono-text">{{ displayCleanupText(activeItem.table_name) }}</strong>
              </div>
              <div class="detail-item">
                <span>命中记录</span>
                <strong>{{ activeItem.target_count }}</strong>
              </div>
              <div class="detail-item">
                <span>占比</span>
                <strong>{{ activeItem.ratio_label }}</strong>
              </div>
              <div class="detail-item">
                <span>阈值</span>
                <strong>{{ activeItem.threshold_label }}</strong>
              </div>
              <div class="detail-item">
                <span>执行模式</span>
                <strong>{{ displayCleanupText(activeItem.action_mode_label) }}</strong>
              </div>
              <div class="detail-item">
                <span>最近命中</span>
                <strong>{{ activeItem.latest_target_time || '--' }}</strong>
              </div>
              <div class="detail-item">
                <span>最近记录</span>
                <strong>{{ activeItem.latest_record_time || '--' }}</strong>
              </div>
            </div>
          </section>

          <section class="detail-section">
            <h4>备注</h4>
            <pre class="detail-note-box">{{
              displayCleanupText(activeItem.note || activeItem.maintenance_note)
            }}</pre>
          </section>
        </template>
      </div>
    </ElDrawer>
  </div>
</template>

<script setup lang="ts">
  import { ElButton, ElMessage, ElMessageBox, ElTag } from 'element-plus'
  import { useAuth } from '@/hooks'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'
  import { StorageConfig } from '@/utils/storage/storageConfig'
  import {
    fetchExecuteCleanupAction,
    fetchGetCleanupAuditDetail,
    fetchGetCleanupAuditList,
    fetchGetCleanupExecutionAudit
  } from '@/api/cleanupAudit'
  import { fetchCleanupServerCache, fetchGetSystemCacheAudit } from '@/api/systemCache'

  defineOptions({ name: 'SystemCleanupAudit' })

  type CleanupAuditItem = Api.CleanupAudit.CleanupAuditItem
  type CleanupAuditSummary = Api.CleanupAudit.CleanupAuditSummary
  type CleanupExecutionAudit = Api.CleanupAudit.CleanupExecutionAudit
  type CacheAuditResponse = Api.SystemCache.CacheAuditResponse
  type BrowserCachePreview = {
    localStorageKeys: string[]
    sessionStorageKeys: string[]
    cacheStorageKeys: string[]
    cacheEntryCount: number
    approxSizeBytes: number
    approxSizeLabel: string
  }

  const CLEANUP_TEXT_EXACT_MAP: Record<string, string> = {
    建议清理: '建议',
    无需处理: '正常',
    可手动清理: '手动清理',
    条件删除: '条件清理',
    整表清空: '整表清理'
  }

  const defaultBrowserHintNote =
    '前端缓存只会清理当前浏览器，不会远程清理其他管理员或商户的浏览器缓存。'

  const { hasAuth } = useAuth()
  const loading = ref(false)
  const cacheAuditLoading = ref(false)
  const serverCacheClearing = ref(false)
  const browserCacheClearing = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const executingKey = ref('')
  const cleanupItems = ref<CleanupAuditItem[]>([])
  const activeItem = ref<CleanupAuditItem | null>(null)
  const cacheAudit = ref<CacheAuditResponse>(emptyCacheAudit())
  const browserCachePreview = ref<BrowserCachePreview>(emptyBrowserCachePreview())
  const pagination = reactive({
    current: 1,
    size: 20,
    total: 0
  })
  const summary = reactive<CleanupAuditSummary>(emptySummary())
  const searchForm = ref<{
    keyword?: string
    category?: string
    status?: string
  }>({})
  const hasCleanupExecuteAuth = computed(() => hasAuth('execute'))
  const browserCachePrefix = computed(
    () => cacheAudit.value.browser_hints.local_storage_prefix || StorageConfig.STORAGE_PREFIX
  )
  const browserManagedLocalKeys = computed(() => {
    const configuredKeys = cacheAudit.value.browser_hints.local_storage_keys || []
    return Array.from(new Set([...configuredKeys, ...defaultManagedLocalKeys()]))
  })

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '搜索清理项或表名'
      }
    },
    {
      label: '分类',
      key: 'category',
      type: 'select',
      props: {
        placeholder: '全部分类',
        options: [
          { label: '订单', value: 'orders' },
          { label: '充值', value: 'recharges' },
          { label: '后台日志', value: 'admin_logs' },
          { label: '商户日志', value: 'front_logs' }
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
          { label: '建议处理', value: 'recommended' },
          { label: '稳定 / 手动', value: 'stable' }
        ]
      }
    }
  ])

  const { columnChecks, columns } = useTableColumns<CleanupAuditItem>(() => [
    { type: 'globalIndex', width: 70, label: '序号' },
    {
      prop: 'title',
      label: '清理项',
      minWidth: 280,
      formatter: (row) =>
        h('div', { class: 'audit-cell' }, [
          h('strong', { class: 'cell-title' }, displayCleanupText(row.title)),
          h('p', { class: 'cell-sub' }, displayCleanupText(row.target_description))
        ])
    },
    {
      prop: 'category_label',
      label: '分类',
      width: 120,
      align: 'center' as const,
      formatter: (row) =>
        h(ElTag, { effect: 'light' }, () => displayCleanupText(row.category_label))
    },
    {
      prop: 'target_count',
      label: '命中记录',
      minWidth: 170,
      formatter: (row) =>
        h('div', { class: 'count-cell' }, [
          h(
            'strong',
            { class: 'cell-title mono-text' },
            `${row.target_count} / ${row.total_count}`
          ),
          h('p', { class: 'cell-sub' }, `占比 ${row.ratio_label}`)
        ])
    },
    {
      prop: 'status_label',
      label: '状态',
      width: 140,
      align: 'center' as const,
      formatter: (row) =>
        h(ElTag, { type: tagType(row.status_type), effect: 'light' }, () =>
          displayCleanupText(row.status_label)
        )
    },
    {
      prop: 'action_mode_label',
      label: '执行模式',
      width: 140,
      align: 'center' as const,
      formatter: (row) =>
        h(ElTag, { type: tagType(row.action_mode_type), effect: 'plain' }, () =>
          displayCleanupText(row.action_mode_label)
        )
    },
    {
      prop: 'latest_target_time',
      label: '最近命中时间',
      minWidth: 180,
      formatter: (row) => row.latest_target_time || '--'
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
    void refreshAllCleanupData()
  })

  async function refreshAllCleanupData() {
    await Promise.all([getCleanupItems(), refreshCacheAudit()])
  }

  function displayCleanupText(value: null | number | string | undefined, fallback = '--') {
    const normalized = displayAdminFixtureText(value, fallback)
    return CLEANUP_TEXT_EXACT_MAP[normalized] || normalized
  }

  function renderOperationButtons(row: CleanupAuditItem) {
    if (!canExecuteCleanup(row)) {
      return h('div', { class: 'table-actions' }, [
        h(
          ElButton,
          {
            link: true,
            type: 'primary',
            onClick: () => openDetail(row)
          },
          () => '详情'
        )
      ])
    }

    return h('div', { class: 'table-actions' }, [
      h(
        ElButton,
        {
          link: true,
          type: 'primary',
          onClick: () => openDetail(row)
        },
        () => '详情'
      ),
      h(
        ElButton,
        {
          link: true,
          type: 'danger',
          disabled: !row.action_available,
          loading: executingKey.value === row.key,
          onClick: () => handleExecuteCleanup(row)
        },
        () => '执行'
      )
    ])
  }

  async function getCleanupItems() {
    loading.value = true
    try {
      const response = await fetchGetCleanupAuditList({
        current: pagination.current,
        size: pagination.size,
        keyword: searchForm.value.keyword,
        category: searchForm.value.category,
        status: searchForm.value.status
      })
      cleanupItems.value = response.records
      pagination.current = response.current
      pagination.size = response.size
      pagination.total = response.total
      Object.assign(summary, response.summary || emptySummary())
    } catch {
      ElMessage.error('加载清理列表失败。')
    } finally {
      loading.value = false
    }
  }

  async function refreshCacheAudit() {
    cacheAuditLoading.value = true
    try {
      const [serverAudit, browserPreview] = await Promise.all([
        fetchGetSystemCacheAudit(),
        inspectBrowserCache()
      ])
      cacheAudit.value = serverAudit
      browserCachePreview.value = browserPreview
    } catch {
      ElMessage.error('加载缓存状态失败。')
    } finally {
      cacheAuditLoading.value = false
    }
  }

  function handleSearch(params: Api.CleanupAudit.CleanupAuditSearchParams) {
    pagination.current = 1
    searchForm.value = {
      keyword: params.keyword,
      category: params.category,
      status: params.status
    }
    void getCleanupItems()
  }

  function handleReset() {
    pagination.current = 1
    searchForm.value = {}
    void getCleanupItems()
  }

  function handleSizeChange(size: number) {
    pagination.size = size
    pagination.current = 1
    void getCleanupItems()
  }

  function handleCurrentChange(current: number) {
    pagination.current = current
    void getCleanupItems()
  }

  async function openDetail(row: CleanupAuditItem) {
    detailVisible.value = true
    detailLoading.value = true
    activeItem.value = row

    try {
      const response = await fetchGetCleanupAuditDetail(row.key)
      activeItem.value = response.item
    } catch {
      ElMessage.error('加载数据清理详情失败。')
    } finally {
      detailLoading.value = false
    }
  }

  async function handleExecuteCleanup(row?: CleanupAuditItem) {
    if (!hasCleanupExecuteAuth.value) {
      ElMessage.warning('当前没有清理权限。')
      return
    }

    const target = row || activeItem.value
    if (!target) {
      return
    }

    executingKey.value = target.key
    try {
      const response = await fetchGetCleanupExecutionAudit(target.key)
      const audit = response.audit

      if (!audit.can_execute) {
        await ElMessageBox.alert(buildBlockedMessage(audit), '暂不可执行', {
          type: 'info',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildExecutePromptMessage(response.item, audit),
        `执行 ${displayCleanupText(response.item.title)}`,
        {
          confirmButtonText: '确认执行',
          cancelButtonText: '取消',
          type: 'warning',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 后继续。`
        }
      )

      const executeResponse = await fetchExecuteCleanupAction(target.key, {
        confirmation_phrase: String(value || '')
      })

      if (activeItem.value?.key === target.key) {
        activeItem.value = executeResponse.item
      }

      await getCleanupItems()
      ElMessage.success(
        `${displayCleanupText(executeResponse.action_label || target.title)} 已执行，清理 ${executeResponse.deleted_count} 条。`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('清理执行失败。')
    } finally {
      executingKey.value = ''
    }
  }

  async function handleClearServerCache(targetKeys: string[] = []) {
    if (!hasCleanupExecuteAuth.value) {
      ElMessage.warning('当前没有清理权限。')
      return
    }

    try {
      await ElMessageBox.confirm(
        buildServerCachePrompt(targetKeys),
        buildServerCacheTitle(targetKeys),
        {
        type: 'warning',
        confirmButtonText: '确认清理',
        cancelButtonText: '取消'
        }
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }
      throw error
    }

    serverCacheClearing.value = true
    try {
      const response = await fetchCleanupServerCache(
        targetKeys.length > 0 ? { targets: targetKeys } : undefined
      )
      cacheAudit.value = response.audit
      ElMessage.success(buildServerCacheSuccessMessage(response, targetKeys))

      if (response.warnings.length > 0) {
        ElMessage.warning(
          `部分缓存未清理完成，请检查占用文件。剩余告警 ${response.warnings.length} 条。`
        )
      }
    } catch {
      ElMessage.error('服务端缓存清理失败。')
    } finally {
      serverCacheClearing.value = false
      browserCachePreview.value = await inspectBrowserCache()
    }
  }

  async function handleClearBrowserCache() {
    try {
      await ElMessageBox.confirm(buildBrowserCachePrompt(), '清浏览器缓存', {
        type: 'warning',
        confirmButtonText: '继续清理',
        cancelButtonText: '取消'
      })
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }
      throw error
    }

    await clearBrowserCacheAndRedirect()
  }

  async function handleClearAllCaches() {
    if (!hasCleanupExecuteAuth.value) {
      ElMessage.warning('当前没有清理权限。')
      return
    }

    try {
      await ElMessageBox.confirm(buildAllCachesPrompt(), '一键清缓存', {
        type: 'warning',
        confirmButtonText: '确认执行',
        cancelButtonText: '取消'
      })
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }
      throw error
    }

    serverCacheClearing.value = true
    try {
      const response = await fetchCleanupServerCache()
      cacheAudit.value = response.audit
      ElMessage.success(
        `服务端缓存已清理，释放 ${response.released_size_label}，继续清理浏览器缓存。`
      )
      await clearBrowserCacheAndRedirect(true)
    } catch {
      ElMessage.error('一键清理失败。')
    } finally {
      serverCacheClearing.value = false
    }
  }

  async function clearBrowserCacheAndRedirect(skipSuccessMessage = false) {
    browserCacheClearing.value = true
    try {
      const localKeys = Object.keys(localStorage).filter((key) => isManagedLocalStorageKey(key))
      for (const key of localKeys) {
        localStorage.removeItem(key)
      }

      for (const key of browserManagedLocalKeys.value) {
        localStorage.removeItem(key)
      }

      sessionStorage.clear()

      if ('caches' in window) {
        const cacheKeys = await window.caches.keys()
        await Promise.all(cacheKeys.map((key) => window.caches.delete(key)))
      }

      if (!skipSuccessMessage) {
        ElMessage.success('浏览器缓存已清理，正在返回登录页。')
      }

      setTimeout(() => {
        window.location.replace(`${window.location.pathname}${window.location.search}#/auth/login`)
      }, 360)
    } catch {
      ElMessage.error('浏览器缓存清理失败。')
    } finally {
      browserCacheClearing.value = false
    }
  }

  function canExecuteCleanup(item?: CleanupAuditItem | null) {
    return Boolean(item && hasCleanupExecuteAuth.value)
  }

  function emptySummary(): CleanupAuditSummary {
    return {
      item_count: 0,
      recommended_count: 0,
      stable_count: 0,
      target_row_count: 0,
      threshold_guarded_count: 0,
      generated_at: ''
    }
  }

  function emptyCacheAudit(): CacheAuditResponse {
    return {
      server_targets: [],
      server_summary: {
        target_count: 0,
        clearable_target_count: 0,
        file_count: 0,
        directory_count: 0,
        entry_count: 0,
        size_bytes: 0,
        size_label: '0 B'
      },
      browser_hints: {
        local_storage_prefix: StorageConfig.STORAGE_PREFIX,
        local_storage_keys: defaultManagedLocalKeys(),
        session_storage_keys: ['iframeRoutes', '__art_chunk_reload_once__'],
        note: defaultBrowserHintNote
      },
      generated_at: ''
    }
  }

  function emptyBrowserCachePreview(): BrowserCachePreview {
    return {
      localStorageKeys: [],
      sessionStorageKeys: [],
      cacheStorageKeys: [],
      cacheEntryCount: 0,
      approxSizeBytes: 0,
      approxSizeLabel: '0 B'
    }
  }

  async function inspectBrowserCache(): Promise<BrowserCachePreview> {
    const localStorageKeys = Object.keys(localStorage).filter((key) =>
      isManagedLocalStorageKey(key)
    )
    const sessionStorageKeys = Object.keys(sessionStorage)
    let cacheStorageKeys: string[] = []
    let cacheEntryCount = 0

    if ('caches' in window) {
      try {
        cacheStorageKeys = await window.caches.keys()
        for (const cacheKey of cacheStorageKeys) {
          const cache = await window.caches.open(cacheKey)
          cacheEntryCount += (await cache.keys()).length
        }
      } catch {
        cacheStorageKeys = []
        cacheEntryCount = 0
      }
    }

    let approxSizeBytes = 0
    for (const key of localStorageKeys) {
      approxSizeBytes += estimateBrowserBytes(key, localStorage.getItem(key))
    }
    for (const key of sessionStorageKeys) {
      approxSizeBytes += estimateBrowserBytes(key, sessionStorage.getItem(key))
    }

    return {
      localStorageKeys,
      sessionStorageKeys,
      cacheStorageKeys,
      cacheEntryCount,
      approxSizeBytes,
      approxSizeLabel: formatBytes(approxSizeBytes)
    }
  }

  function isManagedLocalStorageKey(key: string) {
    return (
      key.startsWith(
        cacheAudit.value.browser_hints.local_storage_prefix || StorageConfig.STORAGE_PREFIX
      ) || browserManagedLocalKeys.value.includes(key)
    )
  }

  function defaultManagedLocalKeys() {
    return [
      StorageConfig.VERSION_KEY,
      StorageConfig.THEME_KEY,
      StorageConfig.LAST_USER_ID_KEY,
      StorageConfig.RESPONSIVE_MENU_TYPE_KEY
    ]
  }

  function estimateBrowserBytes(key: string, value: string | null) {
    return new Blob([key, value || '']).size
  }

  function buildBlockedMessage(audit: CleanupExecutionAudit) {
    return [
      `${displayCleanupText(audit.title)} 当前没有可清理内容。`,
      '',
      ...audit.blocking_reasons.map((item) => `- ${displayCleanupText(item, item)}`),
      ...audit.warnings.map((item) => `- ${displayCleanupText(item, item)}`)
    ].join('\n')
  }

  function buildExecutePromptMessage(item: CleanupAuditItem, audit: CleanupExecutionAudit) {
    return [
      `${displayCleanupText(item.title)} 将执行以下清理：`,
      '',
      `范围：${displayCleanupText(audit.action_scope_label)}`,
      `目标表：${displayCleanupText(audit.table_name)}`,
      `命中记录：${audit.summary.delete_row_count}`,
      `保留记录：${audit.summary.keep_row_count}`,
      `命中占比：${audit.summary.ratio_label}`,
      '',
      `请输入 ${audit.confirmation_phrase} 后继续。`,
      ...audit.warnings.map((warning) => `- ${displayCleanupText(warning, warning)}`)
    ].join('\n')
  }

  function resolveServerCacheTargets(targetKeys: string[] = []) {
    if (targetKeys.length === 0) {
      return cacheAudit.value.server_targets
    }

    return cacheAudit.value.server_targets.filter((target) => targetKeys.includes(target.key))
  }

  function buildServerCacheTitle(targetKeys: string[] = []) {
    if (targetKeys.length === 1 && targetKeys[0] === 'hot_path_redis') {
      return '清 Redis'
    }

    return '清服务端'
  }

  function buildServerCachePrompt(targetKeys: string[] = []) {
    const targets = resolveServerCacheTargets(targetKeys)
    const entryCount = targets.reduce((carry, target) => carry + target.entry_count, 0)
    const sizeBytes = targets.reduce((carry, target) => carry + target.size_bytes, 0)
    const lines = [
      '将清理以下服务端缓存：',
      ...targets.map(
        (target) => `- ${target.title}（${target.relative_path}，${target.size_label}）`
      ),
      '',
      `当前共 ${entryCount} 项，预计释放 ${formatBytes(sizeBytes)}。`,
      '该操作不会删除订单、商户、支付插件快照或业务数据。'
    ]

    return lines.join('\n')
  }

  function buildServerCacheSuccessMessage(
    response: Api.SystemCache.ServerCacheCleanupResponse,
    targetKeys: string[] = []
  ) {
    if (targetKeys.length === 1 && targetKeys[0] === 'hot_path_redis') {
      return `Redis 热缓存已清理，释放 ${response.released_size_label}，移除 ${response.removed_key_count} 个键。`
    }

    return `服务端缓存已清理，释放 ${response.released_size_label}，删除文件 ${response.removed_file_count} 个。`
  }

  function buildBrowserCachePrompt() {
    return [
      '将清理当前浏览器缓存：',
      `- 本地存储键 ${browserCachePreview.value.localStorageKeys.length} 个`,
      `- 会话键 ${browserCachePreview.value.sessionStorageKeys.length} 个`,
      `- Cache Storage ${browserCachePreview.value.cacheStorageKeys.length} 个仓 / ${browserCachePreview.value.cacheEntryCount} 条`,
      '',
      '清理完成后会自动退出当前登录并返回登录页。'
    ].join('\n')
  }

  function buildAllCachesPrompt() {
    return [
      '将依次执行：',
      '- 清理服务端缓存',
      '- 清理当前浏览器缓存',
      '',
      `服务端预计释放 ${cacheAudit.value.server_summary.size_label}，当前浏览器约 ${browserCachePreview.value.approxSizeLabel}。`,
      '执行完成后会自动退出当前登录并返回登录页。'
    ].join('\n')
  }

  function formatBytes(bytes: number) {
    if (!Number.isFinite(bytes) || bytes <= 0) {
      return '0 B'
    }

    const units = ['B', 'KB', 'MB', 'GB']
    let value = bytes
    let index = 0

    while (value >= 1024 && index < units.length - 1) {
      value /= 1024
      index++
    }

    return `${value.toFixed(value >= 10 || index === 0 ? 0 : 1)} ${units[index]}`
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
  .cleanup-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
    min-height: 0;
    --cleanup-card-border: rgb(15 23 42 / 0.08);
    --cleanup-card-bg:
      linear-gradient(180deg, rgb(255 255 255 / 0.98), rgb(248 250 252 / 0.96)),
      radial-gradient(circle at top right, rgb(56 189 248 / 0.1), transparent 48%);
    --cleanup-card-bg-soft:
      linear-gradient(180deg, rgb(255 255 255 / 0.98), rgb(255 247 237 / 0.94)),
      radial-gradient(circle at top right, rgb(248 113 113 / 0.12), transparent 50%);
    --cleanup-item-bg: rgb(255 255 255 / 0.78);
    --cleanup-note-bg: rgb(248 250 252 / 0.88);
    --cleanup-hero-bg:
      linear-gradient(135deg, rgb(255 251 235 / 0.98), rgb(255 247 237 / 0.96)),
      radial-gradient(circle at top right, rgb(248 113 113 / 0.12), transparent 54%);
    --cleanup-title-color: var(--el-text-color-primary);
    --cleanup-text-color: #334155;
    --cleanup-muted-color: var(--el-text-color-secondary);
  }

  .cleanup-scroll-area {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    gap: 16px;
    min-height: 0;
    overflow-y: auto;
    padding-bottom: 16px;
  }

  .cleanup-scroll-area > * {
    flex-shrink: 0;
  }

  .cleanup-search-bar {
    flex-shrink: 0;
  }

  :global(html.dark .cleanup-page ){
    --cleanup-card-border: rgb(71 85 105 / 0.42);
    --cleanup-card-bg:
      linear-gradient(180deg, rgb(15 23 42 / 0.96), rgb(2 6 23 / 0.94)),
      radial-gradient(circle at top right, rgb(56 189 248 / 0.08), transparent 48%);
    --cleanup-card-bg-soft:
      linear-gradient(180deg, rgb(15 23 42 / 0.96), rgb(2 6 23 / 0.94)),
      radial-gradient(circle at top right, rgb(248 113 113 / 0.1), transparent 50%);
    --cleanup-item-bg: rgb(15 23 42 / 0.84);
    --cleanup-note-bg: rgb(15 23 42 / 0.86);
    --cleanup-hero-bg:
      linear-gradient(135deg, rgb(30 41 59 / 0.96), rgb(15 23 42 / 0.94)),
      radial-gradient(circle at top right, rgb(248 113 113 / 0.1), transparent 54%);
    --cleanup-title-color: #e2e8f0;
    --cleanup-text-color: #cbd5e1;
    --cleanup-muted-color: #94a3b8;
  }

  .cache-card-header {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: flex-start;
    justify-content: space-between;
  }

  .cache-card-aside {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
    margin-left: auto;
  }

  .cache-card-tags,
  .cache-card-actions {
    justify-content: flex-end;
  }

  .cache-card,
  .art-table-card {
    flex: 0 0 auto;
  }

  .cache-card-copy {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .cache-card-copy h3 {
    margin: 0;
    color: var(--el-text-color-primary);
    font-size: 20px;
  }

  .cache-card-copy p {
    margin: 0;
    max-width: 760px;
    color: var(--el-text-color-secondary);
    font-size: 13px;
    line-height: 1.75;
  }

  .cache-target-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(280px, 1fr));
    gap: 16px;
    align-items: start;
  }

  .cache-target-card {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 18px;
    border: 1px solid var(--cleanup-card-border);
    border-radius: 18px;
    background: var(--cleanup-card-bg);
  }

  .browser-target-card {
    background: var(--cleanup-card-bg-soft);
    min-height: 100%;
  }

  .cache-target-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
  }

  .cache-target-head h4 {
    margin: 0 0 6px;
    color: var(--el-text-color-primary);
    font-size: 16px;
  }

  .browser-cache-item span,
  .cache-target-fact span,
  .browser-cache-note span {
    color: var(--el-text-color-secondary);
    font-size: 12px;
  }

  .cache-target-path {
    margin: 0;
    color: var(--cleanup-text-color);
    font-size: 12px;
    line-height: 1.6;
    word-break: break-all;
  }

  .cache-target-facts {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
  }

  .cache-target-fact,
  .browser-cache-note {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 12px 14px;
    border: 1px solid var(--cleanup-card-border);
    border-radius: 14px;
    background: var(--cleanup-item-bg);
  }

  .cache-target-fact strong,
  .browser-cache-note strong {
    color: var(--el-text-color-primary);
    font-size: 14px;
  }

  .browser-cache-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }

  .browser-cache-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-height: 88px;
    padding: 12px 14px;
    border: 1px solid var(--cleanup-card-border);
    border-radius: 14px;
    background: var(--cleanup-item-bg);
  }

  .browser-cache-item strong {
    color: var(--el-text-color-primary);
    font-size: 16px;
    line-height: 1.2;
  }

  .browser-cache-notes {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
  }

  .audit-cell,
  .count-cell {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .cell-title,
  .detail-item strong,
  .detail-hero-copy h3 {
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

  .mono-text {
    font-family:
      ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New',
      monospace;
    font-variant-numeric: tabular-nums;
  }

  .cleanup-detail {
    min-height: 240px;
  }

  .detail-hero {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
    padding: 20px;
    border: 1px solid rgb(248 113 113 / 0.16);
    border-radius: 18px;
    background: var(--cleanup-hero-bg);
  }

  .detail-hero-copy {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .detail-hero-copy h3 {
    margin: 0;
    color: var(--cleanup-title-color);
    font-size: 22px;
  }

  .detail-hero-copy p,
  .detail-hero-copy span {
    margin: 0;
    color: var(--cleanup-muted-color);
    line-height: 1.7;
    word-break: break-all;
  }

  .detail-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-content: flex-start;
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

  .detail-note-box {
    min-height: 96px;
    margin: 0;
    padding: 14px 16px;
    color: var(--cleanup-text-color);
    font-family: inherit;
    font-size: 13px;
    line-height: 1.8;
    white-space: pre-wrap;
    word-break: break-word;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 14px;
    background: var(--cleanup-note-bg);
  }

  .detail-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 14px 16px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 14px;
    background: var(--cleanup-item-bg);
  }

  .detail-item span {
    color: var(--el-text-color-secondary);
    font-size: 12px;
  }

  @media (width <= 1200px) {
    .cache-target-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (width <= 991px) {
    .cache-card-header,
    .detail-hero {
      flex-direction: column;
    }

    .cache-card-aside {
      width: 100%;
      align-items: flex-start;
      margin-left: 0;
    }

    .cache-card-tags,
    .cache-card-actions {
      justify-content: flex-start;
    }

    .detail-hero-actions {
      justify-content: flex-start;
    }

    .detail-grid,
    .cache-target-grid,
    .browser-cache-grid,
    .cache-target-facts,
    .browser-cache-notes {
      grid-template-columns: 1fr;
    }
  }
</style>
