<template>
  <div class="media-library-page art-full-height">
    <ArtSearchBar
      v-model="searchForm"
      :items="searchItems"
      :showExpand="false"
      @search="handleSearch"
      @reset="handleReset"
    />

    <ElCard class="art-table-card" shadow="never">
      <ArtTableHeader v-model:columns="columnChecks" :loading="loading" @refresh="getMediaLibrary">
        <template #left>
          <ElSpace wrap>
            <ElTag effect="plain">目录 {{ summary.directory_count }}</ElTag>
            <ElTag type="success" effect="plain">正常 {{ summary.healthy_count }}</ElTag>
            <ElTag type="warning" effect="plain">预警 {{ summary.warning_directory_count }}</ElTag>
            <ElTag effect="plain">空目录 {{ summary.empty_directory_count }}</ElTag>
            <ElTag type="info" effect="plain">索引文件 {{ summary.db_file_count }}</ElTag>
            <ElTag effect="plain">磁盘文件 {{ summary.disk_file_count }}</ElTag>
            <ElTag type="warning" effect="plain">孤立文件 {{ summary.orphan_disk_count }}</ElTag>
            <ElTag type="danger" effect="plain">缺失本地 {{ summary.missing_local_count }}</ElTag>
            <ElTag type="primary" effect="plain">云端记录 {{ summary.cloud_file_count }}</ElTag>
            <ElButton type="primary" @click="openCreateDialog">新建目录</ElButton>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        :loading="loading"
        :data="directoryList"
        :columns="columns"
        :pagination="pagination"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <ElDrawer
      v-model="detailVisible"
      size="980px"
      destroy-on-close
      :title="activeItem ? `素材目录 / ${activeItem.path_label}` : '素材目录详情'"
    >
      <div v-loading="detailLoading" class="media-library-detail">
        <template v-if="activeItem">
          <section class="detail-hero">
            <div class="detail-hero-copy">
              <h3>{{ activeItem.path_label }}</h3>
              <p>{{ activeItem.sync_status_label }} / {{ activeItem.storage_label }}</p>
              <span>{{ activeItem.readonly_note }}</span>
            </div>
            <div class="detail-hero-actions">
              <ElTag :type="tagType(activeItem.sync_status_type)" effect="light">
                {{ activeItem.sync_status_label }}
              </ElTag>
              <ElTag :type="tagType(activeItem.storage_tag)" effect="plain">
                {{ activeItem.storage_label }}
              </ElTag>
              <ElUpload
                v-if="hasMediaSingleUploadAuth"
                ref="uploadRef"
                :multiple="hasMediaMultiUploadAuth"
                accept=".jpg,.jpeg,.png,.bmp,.gif"
                :show-file-list="false"
                :disabled="uploadingCount > 0 || !hasMediaSingleUploadAuth"
                :before-upload="beforeMediaUpload"
                :http-request="handleUploadRequest"
              >
                <ElButton type="primary" :loading="uploadingCount > 0">
                  {{ uploadingCount > 0 ? `上传中 ${uploadingCount}` : '上传图片' }}
                </ElButton>
              </ElUpload>
              <ElButton
                v-if="hasMediaDirectoryDeleteAuth"
                type="danger"
                plain
                @click="handleDeleteDirectory()"
              >
                删除目录
              </ElButton>
            </div>
          </section>

          <section class="detail-section">
            <div class="metric-grid">
              <div class="metric-card">
                <span>索引文件</span>
                <strong>{{ activeItem.db_file_count }}</strong>
                <small>{{ displayAdminFixtureText(activeItem.db_size_label, '0 字节') }}</small>
              </div>
              <div class="metric-card">
                <span>磁盘文件</span>
                <strong>{{ activeItem.disk_file_count }}</strong>
                <small>{{ displayAdminFixtureText(activeItem.disk_size_label, '0 字节') }}</small>
              </div>
              <div class="metric-card">
                <span>匹配情况</span>
                <strong>{{ activeItem.matched_file_count }}</strong>
                <small
                  >孤立 {{ activeItem.orphan_disk_count }} / 缺档
                  {{ activeItem.missing_local_count }}</small
                >
              </div>
              <div class="metric-card">
                <span>存储模式</span>
                <strong>{{ activeItem.storage_label }}</strong>
                <small
                  >本地索引 {{ activeItem.local_db_count }} / 云端
                  {{ activeItem.cloud_file_count }}</small
                >
              </div>
              <div class="metric-card">
                <span>目录状态</span>
                <strong>{{ activeItem.directory_exists ? '目录存在' : '仅索引保留' }}</strong>
                <small>{{ activeItem.empty_directory ? '当前为空目录' : '存在素材内容' }}</small>
              </div>
              <div class="metric-card">
                <span>最近素材</span>
                <strong>{{
                  displayAdminFixtureText(activeItem.latest_file_name, '暂无素材')
                }}</strong>
                <small>{{
                  activeItem.latest_disk_time || activeItem.latest_db_time || '--'
                }}</small>
              </div>
            </div>
          </section>

          <section class="detail-section">
            <h4>目录概览</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="目录名">
                <span class="mono-text">{{ activeItem.path }}</span>
              </ElDescriptionsItem>
              <ElDescriptionsItem label="关联页面">
                <span class="mono-text">{{ displayAdminFixtureText(activeItem.legacy_page) }}</span>
              </ElDescriptionsItem>
              <ElDescriptionsItem label="目录接口">
                <span class="mono-text">{{
                  displayAdminFixtureText(activeItem.legacy_list_endpoint)
                }}</span>
              </ElDescriptionsItem>
              <ElDescriptionsItem label="最近索引时间">
                {{ activeItem.latest_db_time || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="最近磁盘时间">
                {{ activeItem.latest_disk_time || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="目录预览">
                <ElLink
                  v-if="activeItem.preview_url"
                  :href="resolveMediaAssetUrl(activeItem.preview_url)"
                  target="_blank"
                  type="primary"
                >
                  打开最近素材
                </ElLink>
                <span v-else>当前目录暂无可预览文件</span>
              </ElDescriptionsItem>
            </ElDescriptions>
          </section>

          <section class="detail-section">
            <div class="section-header">
              <h4>素材明细</h4>
              <ElSpace wrap>
                <ElTag v-if="uploadingCount > 0" type="primary" effect="plain">
                  上传中 {{ uploadingCount }}
                </ElTag>
                <ElTag v-if="selectedFiles.length > 0" type="danger" effect="plain">
                  已选 {{ selectedFiles.length }}
                </ElTag>
                <ElButton
                  v-if="hasMediaBatchDeleteAuth"
                  plain
                  type="danger"
                  :disabled="selectedFiles.length === 0"
                  @click="handleBatchDeleteFiles"
                >
                  批量删图
                </ElButton>
              </ElSpace>
            </div>

            <ElTable
              v-if="activeItem.files.length > 0"
              :data="activeItem.files"
              border
              row-key="key"
              max-height="460"
              @selection-change="handleFileSelectionChange"
            >
              <ElTableColumn v-if="hasMediaBatchDeleteAuth" type="selection" width="54" />
              <ElTableColumn label="预览" width="92" align="center">
                <template #default="{ row }">
                  <ElImage
                    v-if="row.preview_url"
                    :src="resolveMediaAssetUrl(row.preview_url)"
                    :preview-src-list="[resolveMediaAssetUrl(row.preview_url)]"
                    preview-teleported
                    fit="cover"
                    class="file-preview"
                  />
                  <div v-else class="file-preview-empty">无预览</div>
                </template>
              </ElTableColumn>
              <ElTableColumn label="文件" min-width="270">
                <template #default="{ row }">
                  <div class="file-cell">
                    <strong>{{ displayAdminFixtureText(row.name, '未命名素材') }}</strong>
                    <p class="mono-text">
                      {{ displayAdminFixtureText(row.relative_path || row.href || '--') }}
                    </p>
                    <p>{{ displayAdminFixtureText(row.mime || row.ext, '未识别类型') }}</p>
                  </div>
                </template>
              </ElTableColumn>
              <ElTableColumn label="来源状态" width="130" align="center">
                <template #default="{ row }">
                  <ElTag :type="tagType(row.source_status_type)" effect="light">
                    {{ row.source_status_label }}
                  </ElTag>
                </template>
              </ElTableColumn>
              <ElTableColumn label="存储" width="120" align="center">
                <template #default="{ row }">
                  <ElTag :type="tagType(row.storage_tag)" effect="plain">
                    {{ row.storage_label }}
                  </ElTag>
                </template>
              </ElTableColumn>
              <ElTableColumn label="大小" width="150">
                <template #default="{ row }">
                  <div class="file-size-cell">
                    <strong>{{ displayAdminFixtureText(row.size_label, '0 字节') }}</strong>
                    <p>
                      索引 {{ displayAdminFixtureText(row.db_size_label, '0 字节') }} / 磁盘
                      {{ displayAdminFixtureText(row.disk_size_label, '0 字节') }}
                    </p>
                  </div>
                </template>
              </ElTableColumn>
              <ElTableColumn label="时间" width="180">
                <template #default="{ row }">
                  {{ row.disk_mtime || row.create_time || '--' }}
                </template>
              </ElTableColumn>
              <ElTableColumn label="操作" width="96" align="center" fixed="right">
                <template #default="{ row }">
                  <ElButton
                    v-if="hasMediaFileDeleteAuth"
                    text
                    type="danger"
                    @click="handleDeleteFile(row)"
                  >
                    删除
                  </ElButton>
                </template>
              </ElTableColumn>
            </ElTable>
            <ElEmpty v-else description="当前目录暂无素材记录" />
          </section>

          <ElAlert type="info" :closable="false" show-icon :title="activeItem.readonly_note" />
        </template>
      </div>
    </ElDrawer>

    <ElDialog
      v-model="createVisible"
      width="560px"
      destroy-on-close
      align-center
      title="新建素材目录"
    >
      <ElForm label-position="top">
        <ElFormItem label="目录名" required>
          <ElInput
            v-model="createForm.path"
            maxlength="64"
            show-word-limit
            placeholder="例如：系统图片目录、支付二维码目录、商户素材目录"
          />
        </ElFormItem>
        <ElAlert
          type="info"
          :closable="false"
          show-icon
          title="目录名只允许字母、数字、下划线和中划线。当前已支持本地图片上传；若系统配置为云端存储，上传和云端清理仍需在对应存储侧处理。"
        />
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="createVisible = false">取消</ElButton>
          <ElButton
            v-if="hasMediaCreateDirectoryAuth"
            type="primary"
            :loading="creatingDirectory"
            @click="submitCreateDirectory"
          >
            创建目录
          </ElButton>
        </div>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import {
    ElMessage,
    ElMessageBox,
    ElTag,
    type UploadInstance,
    type UploadRequestOptions
  } from 'element-plus'
  import { useAuth } from '@/hooks'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'
  import { resolveBackendOrigin } from '@/utils/http/base'
  import {
    fetchBatchDeleteMediaLibraryFiles,
    fetchCreateMediaLibraryDirectory,
    fetchDeleteMediaLibraryDirectory,
    fetchDeleteMediaLibraryFile,
    fetchGetMediaLibraryBatchDeleteAudit,
    fetchGetMediaLibraryDetail,
    fetchGetMediaLibraryDirectoryDeleteAudit,
    fetchGetMediaLibraryFileDeleteAudit,
    fetchGetMediaLibraryList,
    fetchUploadMediaLibraryFiles
  } from '@/api/media-library'

  defineOptions({ name: 'SystemMediaLibrary' })

  type MediaLibraryDirectoryItem = Api.MediaLibrary.MediaLibraryDirectoryItem
  type MediaLibraryDetailItem = Api.MediaLibrary.MediaLibraryDetailItem
  type MediaLibraryFileItem = Api.MediaLibrary.MediaLibraryFileItem
  type MediaLibrarySummary = Api.MediaLibrary.MediaLibrarySummary

  const { hasAuth } = useAuth()
  const loading = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const createVisible = ref(false)
  const creatingDirectory = ref(false)
  const directoryList = ref<MediaLibraryDirectoryItem[]>([])
  const activeItem = ref<MediaLibraryDetailItem | null>(null)
  const selectedFiles = ref<MediaLibraryFileItem[]>([])
  const uploadRef = ref<UploadInstance>()
  const uploadingCount = ref(0)
  const uploadSucceededCount = ref(0)
  const uploadFailedCount = ref(0)
  const uploadedFileNames = ref<string[]>([])

  const pagination = reactive({
    current: 1,
    size: 20,
    total: 0
  })

  const summary = reactive<MediaLibrarySummary>(emptySummary())
  const searchForm = ref<{
    keyword?: string
    sync_status?: string
    storage_mode?: string
  }>({})

  const createForm = reactive({
    path: ''
  })
  const hasMediaCreateDirectoryAuth = computed(() => hasAuth('add'))
  const hasMediaSingleUploadAuth = computed(() => hasAuth('addPhoto') || hasAuth('addPhotos'))
  const hasMediaMultiUploadAuth = computed(() => hasAuth('addPhotos'))
  const hasMediaFileDeleteAuth = computed(() => hasAuth('remove'))
  const hasMediaBatchDeleteAuth = computed(() => hasAuth('batchRemove'))
  const hasMediaDirectoryDeleteAuth = computed(() => hasAuth('del'))
  const mediaAssetBaseUrl = resolveBackendOrigin()

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '搜索目录名、最近文件名、存储标签或目录接口'
      }
    },
    {
      label: '目录状态',
      key: 'sync_status',
      type: 'select',
      props: {
        placeholder: '全部状态',
        options: [
          { label: '状态正常', value: 'healthy' },
          { label: '有孤立文件', value: 'orphan_disk' },
          { label: '索引缺档', value: 'missing_local' },
          { label: '双向偏差', value: 'drift' },
          { label: '空目录', value: 'empty' }
        ]
      }
    },
    {
      label: '存储模式',
      key: 'storage_mode',
      type: 'select',
      props: {
        placeholder: '全部模式',
        options: [
          { label: '本地目录', value: 'local' },
          { label: '云端记录', value: 'cloud' },
          { label: '混合存储', value: 'mixed' }
        ]
      }
    }
  ])

  const { columnChecks, columns } = useTableColumns<MediaLibraryDirectoryItem>(() => [
    { type: 'globalIndex', width: 70, label: '序号' },
    {
      prop: 'path',
      label: '目录',
      minWidth: 260,
      formatter: (row) =>
        h('div', { class: 'directory-cell' }, [
          h('strong', { class: 'cell-title mono-text' }, row.path_label || row.path),
          h(
            'p',
            { class: 'cell-sub' },
            displayAdminFixtureText(row.latest_file_name, '当前目录暂无素材')
          ),
          h('p', { class: 'cell-sub mono-text' }, displayAdminFixtureText(row.legacy_list_endpoint))
        ])
    },
    {
      prop: 'preview_url',
      label: '预览',
      width: 110,
      align: 'center',
      formatter: (row) => renderPreviewThumb(resolveMediaAssetUrl(row.preview_url), row.path)
    },
    {
      prop: 'sync_status_label',
      label: '目录状态',
      minWidth: 230,
      formatter: (row) =>
        h('div', { class: 'status-cell' }, [
          h(
            ElTag,
            { type: tagType(row.sync_status_type), effect: 'light' },
            () => row.sync_status_label
          ),
          h(
            'p',
            { class: 'cell-sub' },
            `匹配 ${row.matched_file_count} / 孤立 ${row.orphan_disk_count}`
          ),
          h(
            'p',
            { class: 'cell-sub' },
            `缺失本地 ${row.missing_local_count} / 空目录 ${row.empty_directory ? '是' : '否'}`
          )
        ])
    },
    {
      prop: 'storage_label',
      label: '存储统计',
      minWidth: 220,
      formatter: (row) =>
        h('div', { class: 'storage-cell' }, [
          h(ElTag, { type: tagType(row.storage_tag), effect: 'plain' }, () => row.storage_label),
          h('p', { class: 'cell-sub' }, `索引 ${row.db_file_count} / 磁盘 ${row.disk_file_count}`),
          h(
            'p',
            { class: 'cell-sub' },
            `本地索引 ${row.local_db_count} / 云端 ${row.cloud_file_count}`
          )
        ])
    },
    {
      prop: 'disk_size_label',
      label: '体积',
      minWidth: 160,
      formatter: (row) =>
        h('div', { class: 'size-cell' }, [
          h(
            'strong',
            { class: 'cell-title' },
            displayAdminFixtureText(row.disk_size_label, '0 字节')
          ),
          h(
            'p',
            { class: 'cell-sub' },
            `索引 ${displayAdminFixtureText(row.db_size_label, '0 字节')}`
          )
        ])
    },
    {
      prop: 'latest_disk_time',
      label: '最近时间',
      minWidth: 180,
      formatter: (row) => row.latest_disk_time || row.latest_db_time || '--'
    },
    {
      prop: 'operation',
      label: '操作',
      width: 120,
      align: 'center',
      fixed: 'right',
      formatter: (row) => renderDirectoryOperationButtons(row)
    }
  ])

  onMounted(() => {
    getMediaLibrary()
  })

  async function getMediaLibrary() {
    loading.value = true
    try {
      const response = await fetchGetMediaLibraryList({
        current: pagination.current,
        size: pagination.size,
        keyword: searchForm.value.keyword,
        sync_status: searchForm.value.sync_status,
        storage_mode: searchForm.value.storage_mode
      })
      directoryList.value = response.records
      pagination.current = response.current
      pagination.size = response.size
      pagination.total = response.total
      Object.assign(summary, response.summary || emptySummary())
    } catch {
      ElMessage.error('素材目录加载失败')
    } finally {
      loading.value = false
    }
  }

  function handleSearch(params: Api.MediaLibrary.MediaLibrarySearchParams) {
    pagination.current = 1
    searchForm.value = {
      keyword: params.keyword,
      sync_status: params.sync_status,
      storage_mode: params.storage_mode
    }
    getMediaLibrary()
  }

  function handleReset() {
    pagination.current = 1
    searchForm.value = {}
    getMediaLibrary()
  }

  function handleSizeChange(size: number) {
    pagination.size = size
    pagination.current = 1
    getMediaLibrary()
  }

  function handleCurrentChange(current: number) {
    pagination.current = current
    getMediaLibrary()
  }

  async function openDetail(row: MediaLibraryDirectoryItem) {
    detailVisible.value = true
    detailLoading.value = true
    selectedFiles.value = []
    resetUploadTracking()
    uploadRef.value?.clearFiles()
    activeItem.value = {
      ...row,
      files: []
    } as MediaLibraryDetailItem

    try {
      const response = await fetchGetMediaLibraryDetail(row.path)
      if (!response.item) {
        throw new Error('detail missing')
      }
      activeItem.value = response.item
    } catch {
      detailVisible.value = false
      activeItem.value = null
      ElMessage.error('素材目录详情加载失败')
    } finally {
      detailLoading.value = false
    }
  }

  function openCreateDialog() {
    if (!hasMediaCreateDirectoryAuth.value) {
      ElMessage.warning('当前账号没有创建目录权限')
      return
    }

    createForm.path = ''
    createVisible.value = true
  }

  async function submitCreateDirectory() {
    if (!hasMediaCreateDirectoryAuth.value) {
      ElMessage.warning('当前账号没有创建目录权限')
      return
    }

    const path = normalizeInput(createForm.path)
    if (!/^[A-Za-z0-9_-]+$/.test(path)) {
      ElMessage.warning('目录名只允许字母、数字、下划线和中划线')
      return
    }

    creatingDirectory.value = true
    try {
      const response = await fetchCreateMediaLibraryDirectory({ path })
      createVisible.value = false
      await getMediaLibrary()
      if (response.item) {
        await openDetail(response.item)
      }
      ElMessage.success(`目录 ${response.created_path} 已创建`)
    } catch {
      ElMessage.error('创建目录失败')
    } finally {
      creatingDirectory.value = false
    }
  }

  function handleFileSelectionChange(rows: MediaLibraryFileItem[]) {
    selectedFiles.value = rows
  }

  function beforeMediaUpload(file: File) {
    if (!hasMediaSingleUploadAuth.value) {
      ElMessage.warning('当前账号没有上传素材权限')
      return false
    }

    if (!activeItem.value?.path) {
      ElMessage.warning('请先打开素材目录详情后再上传图片')
      return false
    }

    const extension = file.name.split('.').pop()?.toLowerCase() || ''
    const allowedExtensions = ['jpg', 'jpeg', 'png', 'bmp', 'gif']
    if (!allowedExtensions.includes(extension)) {
      ElMessage.warning('仅支持上传 jpg、jpeg、png、bmp、gif 图片')
      return false
    }

    return true
  }

  async function handleUploadRequest(options: UploadRequestOptions) {
    if (!hasMediaSingleUploadAuth.value) {
      ElMessage.warning('当前账号没有上传素材权限')
      options.onError?.(new Error('media library upload forbidden') as any)
      return
    }

    const path = activeItem.value?.path
    if (!path) {
      options.onError?.(new Error('media directory missing') as any)
      return
    }

    uploadingCount.value += 1

    try {
      const response = await fetchUploadMediaLibraryFiles(path, options.file)
      uploadSucceededCount.value += response.uploaded_count
      uploadedFileNames.value.push(...response.uploaded_files.map((item) => item.name))

      if (response.item && activeItem.value?.path === path) {
        activeItem.value = response.item
      }

      options.onSuccess?.(response as any)
    } catch (error) {
      uploadFailedCount.value += 1
      options.onError?.(error as any)
    } finally {
      await finalizeUploadBatch(path)
    }
  }

  async function handleDeleteFile(file: MediaLibraryFileItem) {
    if (!hasMediaFileDeleteAuth.value) {
      ElMessage.warning('当前账号没有删除素材权限')
      return
    }

    const selector = buildFileSelector(file)

    try {
      const response = await fetchGetMediaLibraryFileDeleteAudit(selector)
      const audit = response.audit

      if (!audit.can_delete) {
        await ElMessageBox.alert(buildFileDeleteBlockedMessage(audit), '当前不可删除', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(buildFileDeletePromptMessage(audit), '删除素材', {
        confirmButtonText: '删除',
        cancelButtonText: '取消',
        type: 'error',
        inputPlaceholder: audit.confirmation_phrase,
        inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
        inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以确认删除`
      })

      const deleteResponse = await fetchDeleteMediaLibraryFile({
        file: selector,
        confirmation_phrase: String(value || '')
      })

      await refreshActiveDirectory(audit.path)
      ElMessage.success(`素材 ${deleteResponse.deleted_file_label} 已删除`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('删除素材失败')
    }
  }

  async function handleBatchDeleteFiles() {
    if (!hasMediaBatchDeleteAuth.value) {
      ElMessage.warning('当前账号没有批量删除素材权限')
      return
    }

    if (!activeItem.value || selectedFiles.value.length === 0) {
      ElMessage.warning('请先选择要删除的素材')
      return
    }

    const files = selectedFiles.value.map(buildFileSelector)

    try {
      const response = await fetchGetMediaLibraryBatchDeleteAudit(files)
      const audit = response.audit

      if (!audit.can_delete_all) {
        await ElMessageBox.alert(buildBatchDeleteBlockedMessage(audit), '批量删除受限', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildBatchDeletePromptMessage(audit),
        '批量删除素材',
        {
          confirmButtonText: '批量删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以确认批量删除`
        }
      )

      const deleteResponse = await fetchBatchDeleteMediaLibraryFiles({
        files,
        confirmation_phrase: String(value || '')
      })

      await refreshActiveDirectory(activeItem.value.path)
      ElMessage.success(`已删除 ${deleteResponse.deleted_count} 个素材`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('批量删除素材失败')
    }
  }

  async function handleDeleteDirectory(row?: MediaLibraryDirectoryItem | Event) {
    if (!hasMediaDirectoryDeleteAuth.value) {
      ElMessage.warning('当前账号没有删除目录权限')
      return
    }

    const target = resolveTargetDirectory(row)
    if (!target) {
      return
    }

    try {
      const response = await fetchGetMediaLibraryDirectoryDeleteAudit(target.path)
      const audit = response.audit

      if (!audit.can_delete) {
        await ElMessageBox.alert(buildDirectoryDeleteBlockedMessage(audit), '当前不可删除', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildDirectoryDeletePromptMessage(audit),
        '删除目录',
        {
          confirmButtonText: '删除目录',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以确认删除`
        }
      )

      const deleteResponse = await fetchDeleteMediaLibraryDirectory(target.path, {
        confirmation_phrase: String(value || '')
      })

      if (activeItem.value?.path === target.path) {
        detailVisible.value = false
        activeItem.value = null
        selectedFiles.value = []
      }

      await getMediaLibrary()
      ElMessage.success(`目录 ${deleteResponse.deleted_path} 已删除`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('删除目录失败')
    }
  }

  async function finalizeUploadBatch(path: string) {
    uploadingCount.value = Math.max(0, uploadingCount.value - 1)
    if (uploadingCount.value > 0) {
      return
    }

    uploadRef.value?.clearFiles()

    const uploadedCount = uploadSucceededCount.value
    const failedCount = uploadFailedCount.value
    const labels = [...uploadedFileNames.value]
    resetUploadTracking()

    await refreshActiveDirectory(path)

    if (uploadedCount <= 0) {
      if (failedCount > 0) {
        ElMessage.error('图片上传失败')
      }
      return
    }

    if (failedCount > 0) {
      ElMessage.warning(`已上传 ${uploadedCount} 张图片，另有 ${failedCount} 张失败`)
      return
    }

    if (labels.length === 1) {
      ElMessage.success(`图片 ${labels[0]} 已上传`)
      return
    }

    ElMessage.success(`已上传 ${uploadedCount} 张图片`)
  }

  async function refreshActiveDirectory(path: string) {
    selectedFiles.value = []
    await getMediaLibrary()

    if (!detailVisible.value || !path) {
      return
    }

    detailLoading.value = true
    try {
      const response = await fetchGetMediaLibraryDetail(path)
      if (!response.item) {
        throw new Error('detail missing')
      }
      activeItem.value = response.item
    } catch {
      detailVisible.value = false
      activeItem.value = null
    } finally {
      detailLoading.value = false
    }
  }

  function resolveTargetDirectory(input?: MediaLibraryDirectoryItem | Event | null) {
    if (input && typeof input === 'object' && 'path' in input) {
      return input as MediaLibraryDirectoryItem
    }

    return activeItem.value
  }

  function resetUploadTracking() {
    uploadingCount.value = 0
    uploadSucceededCount.value = 0
    uploadFailedCount.value = 0
    uploadedFileNames.value = []
  }

  function buildFileSelector(
    file: MediaLibraryFileItem
  ): Api.MediaLibrary.MediaLibraryFileSelector {
    return {
      path: file.path,
      db_id: file.db_id,
      href: file.href
    }
  }

  function buildFileDeletePromptMessage(audit: Api.MediaLibrary.MediaLibraryFileDeleteAudit) {
    return [
      `${audit.file_label} 即将被永久删除。`,
      '',
      `删除索引行：${audit.summary.delete_db_row_count}`,
      `删除磁盘文件：${audit.summary.delete_disk_file_count}`,
      '',
      `请输入 ${audit.confirmation_phrase} 以确认删除。`,
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function buildFileDeleteBlockedMessage(audit: Api.MediaLibrary.MediaLibraryFileDeleteAudit) {
    return [
      `${audit.file_label} 当前不能删除。`,
      '',
      ...audit.blocking_reasons.map((item) => `- ${item}`),
      '',
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function buildBatchDeletePromptMessage(audit: Api.MediaLibrary.MediaLibraryBatchDeleteAudit) {
    return [
      `即将永久删除 ${audit.summary.deletable_count} 个素材。`,
      '',
      `删除索引行：${audit.summary.delete_db_row_count}`,
      `删除磁盘文件：${audit.summary.delete_disk_file_count}`,
      `缺失本地记录：${audit.summary.missing_local_count}`,
      `孤立磁盘文件：${audit.summary.orphan_disk_count}`,
      '',
      `请输入 ${audit.confirmation_phrase} 以确认批量删除。`,
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function buildBatchDeleteBlockedMessage(audit: Api.MediaLibrary.MediaLibraryBatchDeleteAudit) {
    const blockedItems = audit.items.filter((item) => !item.can_delete)
    return [
      '当前选择中包含不可删除的素材。',
      '',
      ...blockedItems
        .slice(0, 6)
        .map((item) => `- ${item.file_label}: ${item.blocking_reasons.join(' ')}`),
      '',
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function buildDirectoryDeletePromptMessage(
    audit: Api.MediaLibrary.MediaLibraryDirectoryDeleteAudit
  ) {
    return [
      `目录 ${audit.path_label} 即将被永久删除。`,
      '',
      `删除索引行：${audit.summary.delete_db_row_count}`,
      `删除磁盘文件：${audit.summary.delete_disk_file_count}`,
      `删除目录：${audit.summary.delete_directory_count}`,
      '',
      `请输入 ${audit.confirmation_phrase} 以确认删除。`,
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function buildDirectoryDeleteBlockedMessage(
    audit: Api.MediaLibrary.MediaLibraryDirectoryDeleteAudit
  ) {
    return [
      `目录 ${audit.path_label} 当前不能删除。`,
      '',
      ...audit.blocking_reasons.map((item) => `- ${item}`),
      '',
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function emptySummary(): MediaLibrarySummary {
    return {
      directory_count: 0,
      healthy_count: 0,
      warning_directory_count: 0,
      empty_directory_count: 0,
      db_file_count: 0,
      disk_file_count: 0,
      orphan_disk_count: 0,
      missing_local_count: 0,
      cloud_file_count: 0,
      generated_at: ''
    }
  }

  function resolveMediaAssetUrl(rawUrl?: string | null) {
    const normalizedUrl = String(rawUrl || '').trim()

    if (!normalizedUrl) return ''
    if (/^(data:|blob:)/i.test(normalizedUrl)) return normalizedUrl

    if (normalizedUrl.startsWith('//')) {
      if (typeof window !== 'undefined') {
        return `${window.location.protocol}${normalizedUrl}`
      }

      return `https:${normalizedUrl}`
    }

    if (!mediaAssetBaseUrl) return normalizedUrl

    if (/^https?:\/\//i.test(normalizedUrl)) {
      try {
        const assetUrl = new URL(normalizedUrl)

        if (
          typeof window !== 'undefined' &&
          assetUrl.origin === window.location.origin &&
          mediaAssetBaseUrl !== window.location.origin &&
          /^\/upload\//i.test(assetUrl.pathname)
        ) {
          return `${mediaAssetBaseUrl}${assetUrl.pathname}${assetUrl.search}${assetUrl.hash}`
        }
      } catch {
        return normalizedUrl
      }

      return normalizedUrl
    }

    return `${mediaAssetBaseUrl}${normalizedUrl.startsWith('/') ? normalizedUrl : `/${normalizedUrl}`}`
  }

  function renderPreviewThumb(previewUrl?: string | null, alt = 'preview') {
    if (!previewUrl) {
      return h('div', { class: 'preview-empty' }, '无预览')
    }

    return h('div', { class: 'preview-thumb-wrap' }, [
      h('img', {
        src: previewUrl,
        alt,
        class: 'preview-thumb'
      })
    ])
  }

  function renderDirectoryOperationButtons(row: MediaLibraryDirectoryItem) {
    const actions = [
      h(ArtButtonTable, {
        type: 'view',
        onClick: () => openDetail(row)
      })
    ]

    if (hasMediaDirectoryDeleteAuth.value) {
      actions.push(
        h(ArtButtonTable, {
          type: 'delete',
          onClick: () => handleDeleteDirectory(row)
        })
      )
    }

    return h('div', { class: 'table-actions' }, actions)
  }

  function normalizeInput(value: string | undefined) {
    return String(value || '').trim()
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
  .media-library-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .directory-cell,
  .status-cell,
  .storage-cell,
  .size-cell,
  .file-cell,
  .file-size-cell {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .cell-title,
  .detail-hero-copy h3,
  .metric-card strong {
    color: var(--el-text-color-primary);
    font-size: 14px;
    word-break: break-all;
  }

  .cell-sub,
  .file-cell p,
  .file-size-cell p,
  .detail-hero-copy p,
  .detail-hero-copy span,
  .metric-card span,
  .metric-card small {
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
    letter-spacing: 0.02em;
  }

  .preview-thumb-wrap,
  .preview-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    margin: 0 auto;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 14px;
    background: rgb(241 245 249 / 0.92);
    overflow: hidden;
  }

  .preview-thumb {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .preview-empty,
  .file-preview-empty {
    color: #94a3b8;
    font-size: 12px;
  }

  .media-library-detail {
    min-height: 260px;
  }

  .detail-hero {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
    padding: 20px;
    border: 1px solid rgb(14 165 233 / 0.18);
    border-radius: 18px;
    background:
      linear-gradient(135deg, rgb(240 249 255 / 0.98), rgb(248 250 252 / 0.96)),
      radial-gradient(circle at top right, rgb(14 165 233 / 0.12), transparent 54%);
  }

  .detail-hero-copy {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .detail-hero-copy h3 {
    margin: 0;
    font-size: 22px;
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
    margin: 0;
    color: var(--el-text-color-primary);
    font-size: 15px;
  }

  .section-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
  }

  .metric-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
  }

  .metric-card {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 14px 16px;
    border: 1px solid #e4ecfc;
    border-radius: 16px;
    background: linear-gradient(180deg, rgb(248 250 252 / 0.96), rgb(241 245 249 / 0.9));
  }

  .file-preview {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    overflow: hidden;
    background: rgb(241 245 249 / 0.92);
  }

  .file-preview-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
    margin: 0 auto;
    border-radius: 12px;
    background: rgb(241 245 249 / 0.92);
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

    .metric-grid {
      grid-template-columns: 1fr;
    }

    .section-header {
      align-items: flex-start;
    }
  }
</style>
