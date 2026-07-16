<template>
  <div class="plugin-download-page art-full-height">
    <ArtSearchBar
      v-model="searchForm"
      :items="searchItems"
      :showExpand="false"
      @search="handleSearch"
      @reset="handleReset"
    />

    <ElCard class="art-table-card">
      <ArtTableHeader v-model:columns="columnChecks" :loading="loading" @refresh="getPluginList">
        <template #left>
          <ElSpace wrap>
            <ElTag effect="plain">插件下载 {{ pagination.total }}</ElTag>
            <ElTag type="success" effect="plain">已展示 {{ summary.visible_count }}</ElTag>
            <ElTag type="info" effect="plain">已隐藏 {{ summary.hidden_count }}</ElTag>
            <ElTag type="primary" effect="plain">可下载 {{ summary.download_ready_count }}</ElTag>
            <ElTag effect="plain">已写简介 {{ summary.introduced_count }}</ElTag>
            <ElTag type="info" effect="plain">回收站 {{ summary.deleted_count }}</ElTag>
            <ElButton plain :type="isRecycleView ? 'primary' : 'info'" @click="toggleRecycleView">
              {{ isRecycleView ? '返回有效列表' : '回收站' }}
            </ElButton>
            <ElButton
              v-if="!isRecycleView && hasAuth('add')"
              type="primary"
              @click="openCreateDialog"
            >
              新建下载插件
            </ElButton>
            <ElButton
              v-if="!isRecycleView && hasAuth('batchRemove')"
              plain
              type="danger"
              :disabled="selectedPlugins.length === 0"
              @click="handleBatchDeletePlugins"
            >
              批量删除
            </ElButton>
            <ElButton
              v-if="isRecycleView && hasAuth('recycle')"
              plain
              type="success"
              :disabled="selectedPlugins.length === 0"
              @click="handleBatchRestorePlugins"
            >
              批量恢复
            </ElButton>
            <ElTag v-if="selectedPlugins.length > 0" type="danger" effect="plain">
              已选 {{ selectedPlugins.length }}
            </ElTag>
            <ElTag type="info" effect="plain">
              {{ isRecycleView ? '回收站恢复视图' : '插件下载维护' }}
            </ElTag>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        ref="tableRef"
        :loading="loading"
        :data="pluginList"
        :columns="columns"
        :pagination="pagination"
        row-key="id"
        reserve-selection
        @selection-change="handlePluginSelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <ElDrawer
      v-model="detailVisible"
      size="760px"
      destroy-on-close
      :title="
        activePlugin
          ? `${displayAdminFixtureText(activePlugin.name_label)} / #${activePlugin.id}`
          : '插件下载详情'
      "
    >
      <div v-loading="detailLoading" class="plugin-download-detail">
        <template v-if="activePlugin">
          <div class="detail-hero">
            <div class="detail-hero-copy">
              <h3>{{ displayAdminFixtureText(activePlugin.name_label) }}</h3>
              <p>{{ displayPluginStatus(activePlugin) }} / {{ displayPluginUrl(activePlugin.downurl) }}</p>
              <span>查看当前插件下载记录的状态、下载地址、简介原文和回收站状态。</span>
            </div>
            <div class="detail-hero-actions">
              <ElButton v-if="canEditPlugin(activePlugin)" plain @click="openEditDialog()">
                编辑
              </ElButton>
              <ElButton
                v-if="canToggleStatusPlugin(activePlugin)"
                :type="activePlugin.status === 1 ? 'warning' : 'success'"
                plain
                @click="handleToggleStatusPlugin()"
              >
                {{ activePlugin.status === 1 ? '隐藏' : '展示' }}
              </ElButton>
              <ElButton
                v-if="canDeletePlugin(activePlugin)"
                type="danger"
                plain
                @click="handleDeletePlugin()"
              >
                移入回收站
              </ElButton>
              <ElButton
                v-if="activePlugin.is_deleted && hasAuth('recycle')"
                type="success"
                plain
                @click="handleRestorePlugin()"
              >
                恢复记录
              </ElButton>
            </div>
          </div>

          <div class="drawer-section">
            <ElDescriptions :column="2" border>
              <ElDescriptionsItem label="插件名称">
                {{ displayAdminFixtureText(activePlugin.name_label) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="状态">
                {{ displayPluginStatus(activePlugin) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="下载地址">
                <a
                  v-if="activePlugin.downurl_link"
                  class="cell-link"
                  :href="activePlugin.downurl_link"
                  :target="activePlugin.is_external ? '_blank' : '_self'"
                  rel="noopener noreferrer"
                >
                  {{ displayPluginUrl(activePlugin.downurl) }}
                </a>
                <span v-else>{{ displayPluginUrl(activePlugin.downurl) }}</span>
              </ElDescriptionsItem>
              <ElDescriptionsItem label="外部链接">
                {{ activePlugin.is_external ? '是' : '否' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="已配置下载地址">
                {{ activePlugin.has_download_url ? '是' : '否' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="已配置简介">
                {{ activePlugin.has_introduce ? '是' : '否' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="创建时间">
                {{ activePlugin.create_time || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="更新时间">
                {{ activePlugin.update_time || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="删除时间">
                {{ activePlugin.delete_time || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="记录编号">
                {{ activePlugin.id }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>简介预览</h4>
            <pre class="content-box">{{
              displayAdminFixtureText(activePlugin.introduce_text, '暂无插件简介')
            }}</pre>
          </div>

          <div class="drawer-section">
            <h4>简介源码</h4>
            <pre class="content-box source-box">{{
              activePlugin.introduce || '暂无已保存简介源码'
            }}</pre>
          </div>

          <ElAlert
            type="info"
            :closable="false"
            show-icon
            title="当前页面已支持新建、编辑、启停切换、移入回收站、批量移入回收站和回收站恢复。简介内容会以纯文本预览展示，原始源码仍可继续编辑。"
          />
        </template>
      </div>
    </ElDrawer>

    <ElDialog
      v-model="createVisible"
      width="760px"
      destroy-on-close
      align-center
      title="新建下载插件"
    >
      <ElForm label-position="top">
        <ElFormItem label="展示状态">
          <ElSelect v-model="createForm.status" placeholder="请选择状态">
            <ElOption label="展示" value="1" />
            <ElOption label="隐藏" value="2" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="插件名称">
          <ElInput v-model="createForm.name" maxlength="50" placeholder="请输入插件名称" />
        </ElFormItem>
        <ElFormItem label="下载地址">
          <ElInput v-model="createForm.downurl" placeholder="请输入下载链接或本地路径" />
        </ElFormItem>
        <ElFormItem label="简介源码">
          <ArtWangEditor
            v-model="createForm.introduce"
            height="360px"
            placeholder="请输入插件简介内容，支持在编辑器中上传本地图片。"
            :upload-config="pluginEditorUploadConfig"
          />
        </ElFormItem>
        <ElAlert
          type="info"
          :closable="false"
          show-icon
          title="当前编辑器已支持本地图片上传到插件简介。如需使用云端素材，请通过统一素材库或对应存储入口维护富文本图片。"
        />
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="createVisible = false">取消</ElButton>
          <ElButton type="primary" :loading="creatingPlugin" @click="submitCreatePlugin">
            创建插件
          </ElButton>
        </div>
      </template>
    </ElDialog>

    <ElDialog
      v-model="editVisible"
      width="760px"
      destroy-on-close
      align-center
      title="编辑下载插件"
    >
      <ElForm label-position="top">
        <ElFormItem label="展示状态">
          <ElSelect v-model="editForm.status" placeholder="请选择状态">
            <ElOption label="展示" value="1" />
            <ElOption label="隐藏" value="2" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="插件名称">
          <ElInput v-model="editForm.name" maxlength="50" placeholder="请输入插件名称" />
        </ElFormItem>
        <ElFormItem label="下载地址">
          <ElInput v-model="editForm.downurl" placeholder="请输入下载链接或本地路径" />
        </ElFormItem>
        <ElFormItem label="简介源码">
          <ArtWangEditor
            v-model="editForm.introduce"
            height="360px"
            placeholder="请更新插件简介内容，支持在编辑器中上传本地图片。"
            :upload-config="pluginEditorUploadConfig"
          />
        </ElFormItem>
        <ElAlert
          type="warning"
          :closable="false"
          show-icon
          title="这里会直接更新已保存的 HTML 源码。详情抽屉仍以安全的纯文本和源码视图展示，不会直接渲染存储内容。"
        />
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="editVisible = false">取消</ElButton>
          <ElButton type="primary" :loading="savingEdit" @click="submitEditPlugin">
            保存修改
          </ElButton>
        </div>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox, ElTag } from 'element-plus'
  import { useAuth } from '@/hooks/core/useAuth'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import ArtWangEditor from '@/components/core/forms/art-wang-editor/index.vue'
  import { displayAdminFixtureText, displayAdminFixtureUrl } from '@/utils/adminFixtureText'
  import {
    fetchAuditPluginDownloadBatchDelete,
    fetchBatchDeletePluginDownloads,
    fetchBatchRestorePluginDownloads,
    fetchCreatePluginDownload,
    fetchDeletePluginDownload,
    fetchGetPluginDownloadDetail,
    fetchGetPluginDownloadDeleteAudit,
    fetchGetPluginDownloadList,
    fetchRestorePluginDownload,
    fetchUpdatePluginDownload,
    fetchUpdatePluginDownloadStatus
  } from '@/api/plugin-downloads'

  defineOptions({ name: 'ContentPluginDownloads' })

  type PluginItem = Api.PluginDownloads.PluginDownloadListItem
  type PluginSummary = Api.PluginDownloads.PluginDownloadSummary

  const { hasAuth } = useAuth()
  const tableRef = ref<{ elTableRef?: { clearSelection?: () => void } } | null>(null)
  const loading = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const createVisible = ref(false)
  const editVisible = ref(false)
  const creatingPlugin = ref(false)
  const savingEdit = ref(false)
  const pluginList = ref<PluginItem[]>([])
  const selectedPlugins = ref<PluginItem[]>([])
  const activePlugin = ref<PluginItem | null>(null)
  const editPluginId = ref<number | null>(null)
  const pagination = reactive({
    current: 1,
    size: 20,
    total: 0
  })
  const summary = reactive<PluginSummary>(emptySummary())
  const searchForm = ref<{
    keyword?: string
    status?: string
  }>({})
  const createForm = reactive(emptyWriteForm())
  const editForm = reactive(emptyWriteForm())
  const pluginEditorUploadConfig = {
    isCustomUpload: true,
    server: '/api/common/upload/wangeditor?path=plugins',
    maxFileSize: 2 * 1024 * 1024,
    maxNumberOfFiles: 10
  }

  const isRecycleView = computed(() => {
    const status = String(searchForm.value.status || '')
    return status === '-1' || status.toLowerCase() === 'deleted'
  })

  const searchItems = computed(() => [
    {
      label: '关键字',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '搜索插件编号、名称、下载地址或简介'
      }
    },
    {
      label: '状态',
      key: 'status',
      type: 'select',
      props: {
        placeholder: '请选择状态',
        options: [
          { label: '展示', value: '1' },
          { label: '隐藏', value: '2' },
          { label: '回收站', value: '-1' }
        ]
      }
    }
  ])

  const { columnChecks, columns } = useTableColumns<PluginItem>(() => [
    { type: 'selection', width: 54, fixed: 'left' as const },
    { type: 'globalIndex', width: 70, label: '序号' },
    {
      prop: 'name_label',
      label: '插件',
      minWidth: 280,
      formatter: (row) =>
        h('div', { class: 'plugin-cell' }, [
          h(
            'strong',
            { class: 'cell-title' },
            displayAdminFixtureText(row.name_label, `插件 #${row.id}`)
          ),
          h('p', { class: 'cell-sub' }, displayAdminFixtureText(row.introduce_preview))
        ])
    },
    {
      prop: 'downurl',
      label: '下载地址',
      minWidth: 300,
      formatter: (row) =>
        row.downurl_link
          ? h(
              'a',
              {
                class: 'cell-link',
                href: row.downurl_link,
                target: row.is_external ? '_blank' : '_self',
                rel: 'noopener noreferrer'
              },
              displayPluginUrl(row.downurl)
            )
          : h('span', { class: 'cell-sub' }, displayPluginUrl(row.downurl))
    },
    {
      prop: 'status_label',
      label: '状态',
      width: 120,
      align: 'center' as const,
      formatter: (row) =>
        h(ElTag, { type: tagType(row.status_type), effect: 'light' }, () => displayPluginStatus(row))
    },
    {
      prop: 'create_time',
      label: '创建时间',
      minWidth: 170,
      formatter: (row) => row.create_time || '--'
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
      formatter: (row) => renderPluginOperationButtons(row)
    }
  ])

  onMounted(() => {
    getPluginList()
  })

  function renderPluginOperationButtons(row: PluginItem) {
    const actions = [
      h(ArtButtonTable, {
        type: 'view',
        title: '详情',
        onClick: () => openDetail(row)
      })
    ]

    if (canEditPlugin(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:pencil-line',
          iconClass: 'bg-primary/12 text-primary',
          title: '编辑',
          onClick: () => openEditDialog(row)
        })
      )
    }

    if (canToggleStatusPlugin(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: row.status === 1 ? 'ri:forbid-line' : 'ri:check-line',
          iconClass: row.status === 1 ? 'bg-warning/12 text-warning' : 'bg-success/12 text-success',
          title: row.status === 1 ? '隐藏' : '展示',
          onClick: () => handleToggleStatusPlugin(row)
        })
      )
    }

    if (canDeletePlugin(row)) {
      actions.push(
        h(ArtButtonTable, {
          type: 'delete',
          title: '删除',
          onClick: () => handleDeletePlugin(row)
        })
      )
    }

    if (row.is_deleted && hasAuth('recycle')) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:restart-line',
          iconClass: 'bg-success/12 text-success',
          title: '恢复',
          onClick: () => handleRestorePlugin(row)
        })
      )
    }

    return h('div', { class: 'table-actions' }, actions)
  }

  function canEditPlugin(plugin?: PluginItem | null) {
    return Boolean(plugin && !plugin.is_deleted && hasAuth('edit'))
  }

  function canToggleStatusPlugin(plugin?: PluginItem | null) {
    return Boolean(plugin && !plugin.is_deleted && hasAuth('status'))
  }

  function canDeletePlugin(plugin?: PluginItem | null) {
    return Boolean(plugin && !plugin.is_deleted && hasAuth('remove'))
  }

  function displayPluginUrl(value: null | number | string | undefined, fallback = '--'): string {
    return displayAdminFixtureUrl(value, fallback)
  }

  function displayPluginStatus(plugin?: Partial<PluginItem> | null, fallback = '--') {
    return displayAdminFixtureText(plugin?.status_text || plugin?.status_label, fallback)
  }

  async function getPluginList() {
    loading.value = true
    try {
      const response = await fetchGetPluginDownloadList({
        current: pagination.current,
        size: pagination.size,
        keyword: searchForm.value.keyword,
        status: searchForm.value.status
      })
      pluginList.value = response.records
      pagination.current = response.current
      pagination.size = response.size
      pagination.total = response.total
      Object.assign(summary, response.summary || emptySummary())
    } catch (_error) {
      ElMessage.error('加载插件下载记录失败。')
    } finally {
      loading.value = false
    }
  }

  function handleSearch(params: Api.PluginDownloads.PluginDownloadSearchParams) {
    pagination.current = 1
    clearPluginSelection()
    searchForm.value = {
      keyword: params.keyword,
      status: params.status as string | undefined
    }
    getPluginList()
  }

  function handleReset() {
    pagination.current = 1
    clearPluginSelection()
    searchForm.value = {}
    getPluginList()
  }

  function toggleRecycleView() {
    pagination.current = 1
    clearPluginSelection()
    searchForm.value = {
      ...searchForm.value,
      status: isRecycleView.value ? undefined : '-1'
    }
    getPluginList()
  }

  function handleSizeChange(size: number) {
    pagination.size = size
    pagination.current = 1
    getPluginList()
  }

  function handleCurrentChange(current: number) {
    pagination.current = current
    getPluginList()
  }

  function handlePluginSelectionChange(rows: PluginItem[]) {
    selectedPlugins.value = rows
  }

  function openCreateDialog() {
    resetWriteForm(createForm)
    createVisible.value = true
  }

  function openEditDialog(row?: PluginItem) {
    const target = row || activePlugin.value
    if (!target) {
      return
    }

    if (!canEditPlugin(target)) {
      ElMessage.warning('请先恢复该记录，再继续编辑。')
      return
    }

    editPluginId.value = target.id
    syncWriteForm(editForm, target)
    editVisible.value = true
  }

  async function openDetail(row: PluginItem) {
    detailVisible.value = true
    detailLoading.value = true
    activePlugin.value = row

    try {
      const response = await fetchGetPluginDownloadDetail(row.id)
      activePlugin.value = response.item
    } catch (_error) {
      ElMessage.error('加载插件下载详情失败。')
    } finally {
      detailLoading.value = false
    }
  }

  async function submitCreatePlugin() {
    const payload = buildWritePayload(createForm)
    if (!payload) {
      return
    }

    creatingPlugin.value = true
    try {
      const response = await fetchCreatePluginDownload(payload)
      createVisible.value = false
      resetWriteForm(createForm)
      clearPluginSelection()
      await getPluginList()
      ElMessage.success(
        `插件下载记录 ${displayAdminFixtureText(response.created_plugin_label, payload.name || `#${response.created_plugin_id}`)} 已创建。`
      )
    } finally {
      creatingPlugin.value = false
    }
  }

  async function submitEditPlugin() {
    if (!editPluginId.value) {
      return
    }

    const payload = buildWritePayload(editForm)
    if (!payload) {
      return
    }

    savingEdit.value = true
    try {
      const response = await fetchUpdatePluginDownload(editPluginId.value, payload)
      editVisible.value = false
      syncActivePlugin(response.item)
      clearPluginSelection()
      await getPluginList()
      ElMessage.success(
        `插件下载记录 ${displayAdminFixtureText(response.updated_plugin_label, payload.name || `#${response.updated_plugin_id}`)} 已更新。`
      )
    } finally {
      savingEdit.value = false
    }
  }

  async function handleToggleStatusPlugin(row?: PluginItem) {
    const target = row || activePlugin.value
    if (!target) {
      return
    }

    if (!canToggleStatusPlugin(target)) {
      ElMessage.warning('请先恢复该记录，再切换状态。')
      return
    }

    const nextStatus = target.status === 1 ? 2 : 1
    const nextLabel = nextStatus === 1 ? '展示' : '隐藏'

    try {
      await ElMessageBox.confirm(
        `${nextStatus === 1 ? '展示' : '隐藏'} ${displayAdminFixtureText(target.name_label)}？`,
        `${nextStatus === 1 ? '展示' : '隐藏'}插件下载`,
        {
          confirmButtonText: nextStatus === 1 ? '展示' : '隐藏',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchUpdatePluginDownloadStatus(target.id, {
        status: nextStatus
      })
      syncActivePlugin(response.item)
      clearPluginSelection()
      await getPluginList()
      ElMessage.success(
        `插件下载记录 ${displayAdminFixtureText(response.updated_plugin_label, target.name_label || `#${target.id}`)} 已${nextLabel}。`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('更新插件下载状态失败。')
    }
  }

  async function handleDeletePlugin(row?: PluginItem) {
    const target = row || activePlugin.value
    if (!target) {
      return
    }

    if (!canDeletePlugin(target)) {
      ElMessage.warning('该插件下载记录已在回收站中。')
      return
    }

    try {
      const response = await fetchGetPluginDownloadDeleteAudit(target.id)
      const audit = response.audit
      const title = displayAdminFixtureText(target.name_label, `插件下载 #${target.id}`)

      if (!audit.can_delete) {
        await ElMessageBox.alert(buildPluginDeleteBlockedMessage(audit, title), '删除受阻', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildPluginDeletePromptMessage(audit, title),
        '删除插件下载',
        {
          confirmButtonText: '删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 后继续。`
        }
      )

      const deleteResponse = await fetchDeletePluginDownload(target.id, {
        confirmation_phrase: String(value || '')
      })

      if (activePlugin.value?.id === target.id) {
        detailVisible.value = false
        activePlugin.value = null
      }

      clearPluginSelection()
      await getPluginList()
      ElMessage.success(
        `插件下载记录 ${displayAdminFixtureText(deleteResponse.deleted_plugin_label, title)} 已移入回收站。`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('删除插件下载记录失败。')
    }
  }

  async function handleRestorePlugin(row?: PluginItem) {
    const target = row || activePlugin.value
    if (!target) {
      return
    }

    if (!target.is_deleted) {
      ElMessage.warning('该插件下载记录当前已处于有效状态。')
      return
    }

    try {
      await ElMessageBox.confirm(
        `确认将 ${displayAdminFixtureText(target.name_label, `插件 #${target.id}`)} 恢复到有效列表吗？`,
        '恢复插件下载',
        {
          confirmButtonText: '恢复',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchRestorePluginDownload(target.id)
      syncActivePlugin(response.item)
      clearPluginSelection()
      await getPluginList()

      if (isRecycleView.value && activePlugin.value?.id === target.id) {
        detailVisible.value = false
        activePlugin.value = null
      }

      ElMessage.success(
        `插件下载记录 ${displayAdminFixtureText(response.restored_plugin_label, target.name_label || `#${target.id}`)} 已恢复。`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('恢复插件下载记录失败。')
    }
  }

  async function handleBatchDeletePlugins() {
    const activeSelection = selectedPlugins.value.filter((item) => !item.is_deleted)
    if (activeSelection.length === 0) {
      ElMessage.warning('请至少选择一条有效的插件下载记录。')
      return
    }

    const pluginIds = activeSelection.map((item) => item.id)

    try {
      const response = await fetchAuditPluginDownloadBatchDelete({
        plugin_ids: pluginIds
      })
      const audit = response.audit

      if (!audit.can_delete_all) {
        await ElMessageBox.alert(buildPluginBatchDeleteBlockedMessage(audit), '批量删除受阻', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildPluginBatchDeletePromptMessage(audit),
        '批量删除插件下载',
        {
          confirmButtonText: '批量删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 后继续。`
        }
      )

      const deleteResponse = await fetchBatchDeletePluginDownloads({
        plugin_ids: pluginIds,
        confirmation_phrase: String(value || '')
      })

      if (activePlugin.value && deleteResponse.deleted_plugin_ids.includes(activePlugin.value.id)) {
        detailVisible.value = false
        activePlugin.value = null
      }

      clearPluginSelection()
      await getPluginList()
      ElMessage.success(`已将 ${deleteResponse.deleted_count} 条插件下载记录移入回收站。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('批量删除插件下载记录失败。')
    }
  }

  async function handleBatchRestorePlugins() {
    const recycleSelection = selectedPlugins.value.filter((item) => item.is_deleted)
    if (recycleSelection.length === 0) {
      ElMessage.warning('请至少选择一条回收站内的插件下载记录。')
      return
    }

    const pluginIds = recycleSelection.map((item) => item.id)

    try {
      await ElMessageBox.confirm(
        `确认恢复 ${pluginIds.length} 条插件下载记录到有效列表吗？`,
        '批量恢复插件下载',
        {
          confirmButtonText: '批量恢复',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchBatchRestorePluginDownloads({
        plugin_ids: pluginIds
      })

      clearPluginSelection()
      await getPluginList()

      if (activePlugin.value && response.restored_plugin_ids.includes(activePlugin.value.id)) {
        detailVisible.value = false
        activePlugin.value = null
      }

      ElMessage.success(`已恢复 ${response.restored_count} 条插件下载记录。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('批量恢复插件下载记录失败。')
    }
  }

  function buildWritePayload(form: ReturnType<typeof emptyWriteForm>) {
    form.name = form.name.trim()
    form.downurl = form.downurl.trim()
    form.introduce = form.introduce.trim()

    if (!form.name && !form.downurl && !form.introduce) {
      ElMessage.warning('插件名称、下载地址和简介内容至少需要填写一项。')
      return null
    }

    if (form.name.length > 50) {
      ElMessage.warning('插件名称长度不能超过 50 个字符。')
      return null
    }

    return {
      name: form.name,
      downurl: form.downurl,
      introduce: form.introduce,
      status: form.status
    }
  }

  function emptyWriteForm() {
    return {
      name: '',
      downurl: '',
      introduce: '',
      status: '1'
    }
  }

  function resetWriteForm(form: ReturnType<typeof emptyWriteForm>) {
    Object.assign(form, emptyWriteForm())
  }

  function syncWriteForm(form: ReturnType<typeof emptyWriteForm>, item: PluginItem) {
    form.name = item.name || ''
    form.downurl = item.downurl || ''
    form.introduce = item.introduce || ''
    form.status = String(item.status ?? 1)
  }

  function syncActivePlugin(item: PluginItem) {
    if (activePlugin.value?.id === item.id) {
      activePlugin.value = item
    }
  }

  function buildPluginDeleteBlockedMessage(
    audit: Api.PluginDownloads.PluginDownloadDeleteAudit,
    title: string
  ) {
    return [`${title} 当前暂时无法移入回收站。`, ...audit.blocking_reasons, ...audit.warnings]
      .filter(Boolean)
      .join('\n')
  }

  function buildPluginDeletePromptMessage(
    audit: Api.PluginDownloads.PluginDownloadDeleteAudit,
    title: string
  ) {
    return [
      `确认将 ${title} 移入回收站吗？`,
      `下载地址：${displayPluginUrl(audit.downurl)}`,
      ...audit.warnings,
      `请输入 ${audit.confirmation_phrase} 后继续。`
    ]
      .filter(Boolean)
      .join('\n')
  }

  function buildPluginBatchDeleteBlockedMessage(
    audit: Api.PluginDownloads.PluginDownloadBatchDeleteAudit
  ) {
    return [
      '当前所选记录暂时无法批量移入回收站。',
      ...audit.warnings,
      ...audit.items.flatMap((item) =>
        item.can_delete || item.blocking_reasons.length === 0
          ? []
          : [
              `#${item.plugin_id || '--'} ${item.plugin_label || '未知插件下载记录'}：${item.blocking_reasons.join('；')}`
            ]
      )
    ]
      .filter(Boolean)
      .join('\n')
  }

  function buildPluginBatchDeletePromptMessage(
    audit: Api.PluginDownloads.PluginDownloadBatchDeleteAudit
  ) {
    return [
      `确认将 ${audit.summary.deletable_count} 条选中记录移入回收站吗？`,
      `有效记录：${audit.summary.existing_count}`,
      `影响行数：${audit.summary.delete_row_count}`,
      ...audit.warnings,
      `请输入 ${audit.confirmation_phrase} 后继续。`
    ]
      .filter(Boolean)
      .join('\n')
  }

  function escapeRegExp(value: string) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  }

  function clearPluginSelection() {
    selectedPlugins.value = []
    tableRef.value?.elTableRef?.clearSelection?.()
  }

  function emptySummary(): PluginSummary {
    return {
      visible_count: 0,
      hidden_count: 0,
      download_ready_count: 0,
      introduced_count: 0,
      deleted_count: 0
    }
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

  function isDialogCancel(error: unknown) {
    return error === 'cancel' || error === 'close'
  }
</script>

<style scoped lang="scss">
  .plugin-download-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .plugin-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .cell-title {
    color: #0f172a;
    font-size: 14px;
    word-break: break-all;
  }

  .cell-sub,
  .cell-link {
    margin: 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.6;
    word-break: break-all;
  }

  .cell-link {
    color: var(--el-color-primary);
    text-decoration: none;
  }

  .cell-link:hover {
    text-decoration: underline;
  }

  .plugin-download-detail {
    min-height: 240px;
  }

  .dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
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
    color: #0f172a;
    font-size: 15px;
  }

  .content-box {
    min-height: 160px;
    max-height: 420px;
    padding: 16px;
    overflow: auto;
    color: #334155;
    font-family: inherit;
    font-size: 14px;
    line-height: 1.8;
    white-space: pre-wrap;
    word-break: break-word;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 14px;
    background: rgb(248 250 252 / 0.88);
  }

  .source-box {
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: 12px;
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
