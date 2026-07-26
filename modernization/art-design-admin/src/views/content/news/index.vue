<template>
  <div class="news-page art-full-height">
    <ArtSearchBar
      v-model="searchForm"
      :items="searchItems"
      :showExpand="false"
      @search="handleSearch"
      @reset="handleReset"
    />

    <ElCard class="art-table-card">
      <ArtTableHeader v-model:columns="columnChecks" :loading="loading" @refresh="getNewsList">
        <template #left>
          <ElSpace wrap>
            <ElTag effect="plain">公告总数 {{ pagination.total }}</ElTag>
            <ElTag type="success" effect="plain">启用中 {{ summary.enabled_count }}</ElTag>
            <ElTag type="info" effect="plain">已停用 {{ summary.disabled_count }}</ElTag>
            <ElTag type="primary" effect="plain">平台公告 {{ summary.platform_count }}</ElTag>
            <ElTag type="warning" effect="plain">行业资讯 {{ summary.industry_count }}</ElTag>
            <ElTag effect="plain">常见问题 {{ summary.faq_count }}</ElTag>
            <ElTag type="info" effect="plain">回收站 {{ summary.deleted_count }}</ElTag>
            <ElButton plain :type="isRecycleView ? 'primary' : 'info'" @click="toggleRecycleView">
              {{ isRecycleView ? '返回正常列表' : '回收站' }}
            </ElButton>
            <ElButton
              v-if="!isRecycleView && hasAuth('add')"
              type="primary"
              @click="openCreateDialog"
            >
              新建公告
            </ElButton>
            <ElButton
              v-if="!isRecycleView && hasAuth('batchRemove')"
              plain
              type="danger"
              :disabled="selectedNews.length === 0"
              @click="handleBatchDeleteNews"
            >
              批量删除
            </ElButton>
            <ElButton
              v-if="isRecycleView && hasAuth('recycle')"
              plain
              type="success"
              :disabled="selectedNews.length === 0"
              @click="handleBatchRestoreNews"
            >
              批量恢复
            </ElButton>
            <ElTag v-if="selectedNews.length > 0" type="danger" effect="plain">
              已选 {{ selectedNews.length }} 条
            </ElTag>
            <ElTag type="info" effect="plain">
              {{ isRecycleView ? '公告回收恢复视图' : '公告维护面板' }}
            </ElTag>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        ref="tableRef"
        :loading="loading"
        :data="newsList"
        :columns="columns"
        :pagination="pagination"
        row-key="id"
        reserve-selection
        @selection-change="handleNewsSelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <ElDrawer
      v-model="detailVisible"
      size="760px"
      destroy-on-close
      :title="activeNews ? `${displayNewsTitle(activeNews)} / 第 ${activeNews.id} 号` : '公告详情'"
    >
      <div v-loading="detailLoading" class="news-detail">
        <template v-if="activeNews">
          <div class="detail-hero">
            <div class="detail-hero-copy">
              <h3>{{ displayNewsTitle(activeNews) }}</h3>
              <p>{{ displayNewsType(activeNews) }} / {{ displayNewsStatus(activeNews) }}</p>
              <span>这里用于查看公告基础信息、正文摘要和回收状态，避免直接暴露过多技术字段。</span>
            </div>
            <div class="detail-hero-actions">
              <ElButton v-if="canEditNews(activeNews)" plain @click="openEditDialog()">
                编辑
              </ElButton>
              <ElButton
                v-if="canToggleStatusNews(activeNews)"
                :type="activeNews.status === 1 ? 'warning' : 'success'"
                plain
                @click="handleToggleStatusNews()"
              >
                {{ activeNews.status === 1 ? '停用' : '启用' }}
              </ElButton>
              <ElButton
                v-if="canDeleteNews(activeNews)"
                type="danger"
                plain
                @click="handleDeleteNews()"
              >
                删除
              </ElButton>
              <ElButton
                v-if="activeNews.is_deleted && hasAuth('recycle')"
                type="success"
                plain
                @click="handleRestoreNews()"
              >
                恢复公告
              </ElButton>
            </div>
          </div>

          <div class="drawer-section">
            <ElDescriptions :column="2" border>
              <ElDescriptionsItem label="公告类型">
                {{ displayNewsType(activeNews) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="当前状态">
                {{ displayNewsStatus(activeNews) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="标题颜色">
                <span class="color-summary">
                  <i
                    v-if="activeNews.color"
                    class="color-dot"
                    :style="{ backgroundColor: activeNews.color }"
                  ></i>
                  {{ displayNewsColor(activeNews.color) }}
                </span>
              </ElDescriptionsItem>
              <ElDescriptionsItem label="正文情况">
                {{ activeNews.has_content ? '已填写正文' : '暂无正文' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="创建时间">
                {{ activeNews.create_time || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="更新时间">
                {{ activeNews.update_time || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="回收时间">
                {{ activeNews.delete_time || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="记录编号">
                {{ activeNews.id }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>正文摘要</h4>
            <pre class="content-box">{{ displayNewsContentText(activeNews.content_text) }}</pre>
          </div>

          <div class="drawer-section">
            <h4>原始内容</h4>
            <pre class="content-box source-box">{{ displayNewsContentText(activeNews.content, '暂无存储内容') }}</pre>
          </div>

        </template>
      </div>
    </ElDrawer>

    <ElDialog v-model="createVisible" width="760px" destroy-on-close align-center title="新建公告">
      <ElForm label-position="top">
        <div class="news-form-grid">
          <ElFormItem label="公告类型">
            <ElSelect v-model="createForm.type" placeholder="请选择公告类型">
              <ElOption label="平台公告" value="1" />
              <ElOption label="行业资讯" value="2" />
              <ElOption label="常见问题" value="3" />
            </ElSelect>
          </ElFormItem>
          <ElFormItem label="状态">
            <ElSelect v-model="createForm.status" placeholder="请选择状态">
              <ElOption label="启用中" value="1" />
              <ElOption label="已停用" value="2" />
            </ElSelect>
          </ElFormItem>
        </div>
        <ElFormItem label="公告标题">
          <ElInput v-model="createForm.title" maxlength="255" placeholder="输入公告标题" />
        </ElFormItem>
        <ElFormItem label="标题颜色">
          <ElInput v-model="createForm.color" maxlength="50" placeholder="选填，例如 #1d4ed8" />
        </ElFormItem>
        <ElFormItem label="公告内容">
          <ArtWangEditor
            v-model="createForm.content"
            height="360px"
            placeholder="输入公告内容"
            :upload-config="newsEditorUploadConfig"
          />
        </ElFormItem>
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="createVisible = false">取消</ElButton>
          <ElButton type="primary" :loading="creatingNews" @click="submitCreateNews">
            确认新建
          </ElButton>
        </div>
      </template>
    </ElDialog>

    <ElDialog v-model="editVisible" width="760px" destroy-on-close align-center title="编辑公告">
      <ElForm label-position="top">
        <div class="news-form-grid">
          <ElFormItem label="公告类型">
            <ElSelect v-model="editForm.type" placeholder="请选择公告类型">
              <ElOption label="平台公告" value="1" />
              <ElOption label="行业资讯" value="2" />
              <ElOption label="常见问题" value="3" />
            </ElSelect>
          </ElFormItem>
          <ElFormItem label="状态">
            <ElSelect v-model="editForm.status" placeholder="请选择状态">
              <ElOption label="启用中" value="1" />
              <ElOption label="已停用" value="2" />
            </ElSelect>
          </ElFormItem>
        </div>
        <ElFormItem label="公告标题">
          <ElInput v-model="editForm.title" maxlength="255" placeholder="输入公告标题" />
        </ElFormItem>
        <ElFormItem label="标题颜色">
          <ElInput v-model="editForm.color" maxlength="50" placeholder="选填，例如 #1d4ed8" />
        </ElFormItem>
        <ElFormItem label="公告内容">
          <ArtWangEditor
            v-model="editForm.content"
            height="360px"
            placeholder="输入公告内容"
            :upload-config="newsEditorUploadConfig"
          />
        </ElFormItem>
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="editVisible = false">取消</ElButton>
          <ElButton type="primary" :loading="savingEdit" @click="submitEditNews">
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
  import ArtButtonTable from '@/components/core/forms/artButtonTable/index.vue'
  import ArtWangEditor from '@/components/core/forms/artWangEditor/index.vue'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'
  import {
    fetchAuditNewsBatchDelete,
    fetchBatchDeleteNews,
    fetchBatchRestoreNews,
    fetchCreateNews,
    fetchDeleteNews,
    fetchGetNewsDeleteAudit,
    fetchGetNewsDetail,
    fetchGetNewsList,
    fetchRestoreNews,
    fetchUpdateNews,
    fetchUpdateNewsStatus
  } from '@/api/news'

  defineOptions({ name: 'ContentNews' })

  type NewsItem = Api.News.NewsListItem
  type NewsSummary = Api.News.NewsSummary

  const { hasAuth } = useAuth()
  const tableRef = ref<{ elTableRef?: { clearSelection?: () => void } } | null>(null)
  const loading = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const createVisible = ref(false)
  const editVisible = ref(false)
  const creatingNews = ref(false)
  const savingEdit = ref(false)
  const newsList = ref<NewsItem[]>([])
  const selectedNews = ref<NewsItem[]>([])
  const activeNews = ref<NewsItem | null>(null)
  const editNewsId = ref<number | null>(null)
  const pagination = reactive({
    current: 1,
    size: 20,
    total: 0
  })
  const summary = reactive<NewsSummary>(emptySummary())
  const searchForm = ref<{
    keyword?: string
    type?: string
    status?: string
  }>({})
  const createForm = reactive(emptyWriteForm())
  const editForm = reactive(emptyWriteForm())
  const newsEditorUploadConfig = {
    isCustomUpload: true,
    server: '/api/common/upload/wangeditor?path=news',
    maxFileSize: 2 * 1024 * 1024,
    maxNumberOfFiles: 10
  }

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
        placeholder: '搜索公告标题、摘要或颜色'
      }
    },
    {
      label: '公告类型',
      key: 'type',
      type: 'select',
      props: {
        placeholder: '请选择类型',
        options: [
          { label: '平台公告', value: '1' },
          { label: '行业资讯', value: '2' },
          { label: '常见问题', value: '3' }
        ]
      }
    },
    {
      label: '状态',
      key: 'status',
      type: 'select',
      props: {
        placeholder: '请选择状态',
        options: [
          { label: '启用中', value: '1' },
          { label: '已停用', value: '2' },
          { label: '回收站', value: '-1' }
        ]
      }
    }
  ])

  const { columnChecks, columns } = useTableColumns<NewsItem>(() => [
    { type: 'selection', width: 54, fixed: 'left' as const },
    { type: 'globalIndex', width: 70, label: '序号' },
    {
      prop: 'title',
      label: '公告信息',
      minWidth: 320,
      formatter: (row) =>
        h('div', { class: 'news-cell' }, [
          h('strong', { class: 'cell-title' }, displayNewsTitle(row)),
          h('p', { class: 'cell-sub' }, displayNewsPreview(row))
        ])
    },
    {
      prop: 'type_label',
      label: '类型',
      width: 170,
      align: 'center' as const,
      formatter: (row) =>
        h(ElTag, { type: tagType(row.type_tag), effect: 'light' }, () => displayNewsType(row))
    },
    {
      prop: 'status_label',
      label: '状态',
      width: 120,
      align: 'center' as const,
      formatter: (row) =>
        h(ElTag, { type: tagType(row.status_type), effect: 'light' }, () => displayNewsStatus(row))
    },
    {
      prop: 'color',
      label: '标题颜色',
      minWidth: 120,
      formatter: (row) => renderNewsColor(row.color)
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
      formatter: (row) => renderNewsOperationButtons(row)
    }
  ])

  onMounted(() => {
    getNewsList()
  })

  function displayNewsTitle(news?: Partial<NewsItem> | null) {
    if (!news) {
      return '公告'
    }

    if (news.title) {
      return displayAdminFixtureText(news.title, news.title)
    }

    return `公告 #${news.id || '--'}`
  }

  function displayNewsPreview(news?: Partial<NewsItem> | null) {
    const preview = String(news?.content_preview || '').trim()
    return preview ? displayAdminFixtureText(preview, preview) : '暂无公告摘要'
  }

  function displayNewsType(news?: Partial<NewsItem> | null) {
    return displayAdminFixtureText(news?.type_label, '未知类型')
  }

  function displayNewsStatus(news?: Partial<NewsItem> | null) {
    return displayAdminFixtureText(news?.status_label, '未知状态')
  }

  function displayNewsColor(color?: null | string) {
    return color ? '已设置自定义颜色' : '默认颜色'
  }

  function displayNewsContentText(value: null | string | undefined, fallback = '暂无公告内容') {
    const raw = String(value || '').trim()
    if (raw === '') {
      return fallback
    }

    const cleaned = raw
      .replace(/<style[\s\S]*?<\/style>/gi, ' ')
      .replace(/<script[\s\S]*?<\/script>/gi, ' ')
      .replace(/<[^>]+>/g, ' ')
      .replace(/&nbsp;/gi, ' ')
      .replace(/&amp;/gi, '&')
      .replace(/&lt;/gi, '<')
      .replace(/&gt;/gi, '>')
      .replace(/\s+/g, ' ')
      .trim()

    return displayAdminFixtureText(cleaned || raw, fallback)
  }

  function renderNewsColor(color?: null | string) {
    return h('span', { class: 'color-summary' }, [
      color
        ? h('i', {
            class: 'color-dot',
            style: { backgroundColor: color }
          })
        : null,
      h('span', displayNewsColor(color))
    ])
  }

  function renderNewsOperationButtons(row: NewsItem) {
    const actions = [
      h(ArtButtonTable, {
        type: 'view',
        title: '详情',
        onClick: () => openDetail(row)
      })
    ]

    if (canEditNews(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:pencil-line',
          iconClass: 'bg-primary/12 text-primary',
          title: '编辑',
          onClick: () => openEditDialog(row)
        })
      )
    }

    if (canToggleStatusNews(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: row.status === 1 ? 'ri:forbid-line' : 'ri:check-line',
          iconClass: row.status === 1 ? 'bg-warning/12 text-warning' : 'bg-success/12 text-success',
          title: row.status === 1 ? '停用' : '启用',
          onClick: () => handleToggleStatusNews(row)
        })
      )
    }

    if (canDeleteNews(row)) {
      actions.push(
        h(ArtButtonTable, {
          type: 'delete',
          title: '删除',
          onClick: () => handleDeleteNews(row)
        })
      )
    }

    if (row.is_deleted && hasAuth('recycle')) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:restart-line',
          iconClass: 'bg-success/12 text-success',
          title: '恢复',
          onClick: () => handleRestoreNews(row)
        })
      )
    }

    return h('div', { class: 'table-actions' }, actions)
  }

  function canEditNews(news?: NewsItem | null) {
    return Boolean(news && !news.is_deleted && hasAuth('edit'))
  }

  function canToggleStatusNews(news?: NewsItem | null) {
    return Boolean(news && !news.is_deleted && hasAuth('status'))
  }

  function canDeleteNews(news?: NewsItem | null) {
    return Boolean(news && !news.is_deleted && hasAuth('remove'))
  }

  async function getNewsList() {
    loading.value = true
    try {
      const response = await fetchGetNewsList({
        current: pagination.current,
        size: pagination.size,
        keyword: searchForm.value.keyword,
        type: searchForm.value.type,
        status: searchForm.value.status
      })
      newsList.value = response.records
      pagination.current = response.current
      pagination.size = response.size
      pagination.total = response.total
      Object.assign(summary, response.summary || emptySummary())
    } catch (_error) {
      ElMessage.error('加载公告列表失败')
    } finally {
      loading.value = false
    }
  }

  function handleSearch(params: Api.News.NewsSearchParams) {
    pagination.current = 1
    clearNewsSelection()
    searchForm.value = {
      keyword: params.keyword,
      type: params.type as string | undefined,
      status: params.status as string | undefined
    }
    getNewsList()
  }

  function handleReset() {
    pagination.current = 1
    clearNewsSelection()
    searchForm.value = {}
    getNewsList()
  }

  function toggleRecycleView() {
    pagination.current = 1
    clearNewsSelection()
    searchForm.value = {
      ...searchForm.value,
      status: isRecycleView.value ? undefined : '-1'
    }
    getNewsList()
  }

  function handleSizeChange(size: number) {
    pagination.size = size
    pagination.current = 1
    getNewsList()
  }

  function handleCurrentChange(current: number) {
    pagination.current = current
    getNewsList()
  }

  function handleNewsSelectionChange(rows: NewsItem[]) {
    selectedNews.value = rows
  }

  function openCreateDialog() {
    resetWriteForm(createForm)
    createVisible.value = true
  }

  function openEditDialog(row?: NewsItem) {
    const target = row || activeNews.value
    if (!target) {
      return
    }

    if (!canEditNews(target)) {
      ElMessage.warning('请先恢复该公告，再进行编辑')
      return
    }

    editNewsId.value = target.id
    syncWriteForm(editForm, target)
    editVisible.value = true
  }

  async function openDetail(row: NewsItem) {
    detailVisible.value = true
    detailLoading.value = true
    activeNews.value = row

    try {
      const response = await fetchGetNewsDetail(row.id)
      activeNews.value = response.item
    } catch (_error) {
      ElMessage.error('加载公告详情失败')
    } finally {
      detailLoading.value = false
    }
  }

  async function submitCreateNews() {
    const payload = buildWritePayload(createForm)
    if (!payload) {
      return
    }

    creatingNews.value = true
    try {
      const response = await fetchCreateNews(payload)
      createVisible.value = false
      resetWriteForm(createForm)
      clearNewsSelection()
      await getNewsList()
      ElMessage.success(
        `公告 ${displayAdminFixtureText(response.created_news_label || payload.title || `#${response.created_news_id}`, payload.title || `#${response.created_news_id}`)} 已创建`
      )
    } finally {
      creatingNews.value = false
    }
  }

  async function submitEditNews() {
    if (!editNewsId.value) {
      return
    }

    const payload = buildWritePayload(editForm)
    if (!payload) {
      return
    }

    savingEdit.value = true
    try {
      const response = await fetchUpdateNews(editNewsId.value, payload)
      editVisible.value = false
      syncActiveNews(response.item)
      clearNewsSelection()
      await getNewsList()
      ElMessage.success(
        `公告 ${displayAdminFixtureText(response.updated_news_label || payload.title || `#${response.updated_news_id}`, payload.title || `#${response.updated_news_id}`)} 已更新`
      )
    } finally {
      savingEdit.value = false
    }
  }

  async function handleToggleStatusNews(row?: NewsItem) {
    const target = row || activeNews.value
    if (!target) {
      return
    }

    if (!canToggleStatusNews(target)) {
      ElMessage.warning('请先恢复该公告，再调整状态')
      return
    }

    const nextStatus = target.status === 1 ? 2 : 1
    const nextLabel = nextStatus === 1 ? '启用' : '停用'

    try {
      await ElMessageBox.confirm(
        `确认${nextStatus === 1 ? '启用' : '停用'} ${displayNewsTitle(target)} 吗？`,
        `${nextStatus === 1 ? '启用' : '停用'}公告`,
        {
          confirmButtonText: nextStatus === 1 ? '确认启用' : '确认停用',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchUpdateNewsStatus(target.id, {
        status: nextStatus
      })
      syncActiveNews(response.item)
      clearNewsSelection()
      await getNewsList()
      ElMessage.success(
        `公告 ${displayAdminFixtureText(response.updated_news_label || target.title || `#${target.id}`, target.title || `#${target.id}`)} 已${nextLabel}`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('更新公告状态失败')
    }
  }

  async function handleDeleteNews(row?: NewsItem) {
    const target = row || activeNews.value
    if (!target) {
      return
    }

    if (!canDeleteNews(target)) {
      ElMessage.warning('该公告已在回收站中')
      return
    }

    try {
      const response = await fetchGetNewsDeleteAudit(target.id)
      const audit = response.audit
      const title = target.title || `公告 #${target.id}`

      if (!audit.can_delete) {
        await ElMessageBox.alert(buildNewsDeleteBlockedMessage(audit, title), '当前不可删除', {
          type: 'warning',
          confirmButtonText: '我知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildNewsDeletePromptMessage(audit, title),
        '删除公告',
        {
          confirmButtonText: '确认删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: '请输入删除确认短语',
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase}`
        }
      )

      const deleteResponse = await fetchDeleteNews(target.id, {
        confirmation_phrase: String(value || '')
      })

      if (activeNews.value?.id === target.id) {
        detailVisible.value = false
        activeNews.value = null
      }

      clearNewsSelection()
      await getNewsList()
      ElMessage.success(
        `公告 ${displayAdminFixtureText(deleteResponse.deleted_news_label || title, title)} 已移入回收站`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('删除公告失败')
    }
  }

  async function handleRestoreNews(row?: NewsItem) {
    const target = row || activeNews.value
    if (!target) {
      return
    }

    if (!target.is_deleted) {
      ElMessage.warning('该公告当前已在正常列表中')
      return
    }

    try {
      await ElMessageBox.confirm(
        `确认将 ${displayNewsTitle(target)} 恢复到正常列表吗？`,
        '恢复公告',
        {
          confirmButtonText: '确认恢复',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchRestoreNews(target.id)
      syncActiveNews(response.item)
      clearNewsSelection()
      await getNewsList()

      if (isRecycleView.value && activeNews.value?.id === target.id) {
        detailVisible.value = false
        activeNews.value = null
      }

      ElMessage.success(
        `公告 ${displayAdminFixtureText(response.restored_news_label || target.title || `#${target.id}`, target.title || `#${target.id}`)} 已恢复`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('恢复公告失败')
    }
  }

  async function handleBatchDeleteNews() {
    const activeSelection = selectedNews.value.filter((item) => !item.is_deleted)
    if (activeSelection.length === 0) {
      ElMessage.warning('请至少选择一条正常状态的公告')
      return
    }

    const newsIds = activeSelection.map((item) => item.id)

    try {
      const response = await fetchAuditNewsBatchDelete({
        news_ids: newsIds
      })
      const audit = response.audit

      if (!audit.can_delete_all) {
        await ElMessageBox.alert(buildNewsBatchDeleteBlockedMessage(audit), '当前不可批量删除', {
          type: 'warning',
          confirmButtonText: '我知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildNewsBatchDeletePromptMessage(audit),
        '批量删除公告',
        {
          confirmButtonText: '确认批量删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: '请输入删除确认短语',
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase}`
        }
      )

      const deleteResponse = await fetchBatchDeleteNews({
        news_ids: newsIds,
        confirmation_phrase: String(value || '')
      })

      if (activeNews.value && deleteResponse.deleted_news_ids.includes(activeNews.value.id)) {
        detailVisible.value = false
        activeNews.value = null
      }

      clearNewsSelection()
      await getNewsList()
      ElMessage.success(`已将 ${deleteResponse.deleted_count} 条公告移入回收站`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('批量删除公告失败')
    }
  }

  async function handleBatchRestoreNews() {
    const recycleSelection = selectedNews.value.filter((item) => item.is_deleted)
    if (recycleSelection.length === 0) {
      ElMessage.warning('请至少选择一条回收站中的公告')
      return
    }

    const newsIds = recycleSelection.map((item) => item.id)

    try {
      await ElMessageBox.confirm(`确认恢复所选 ${newsIds.length} 条公告吗？`, '批量恢复公告', {
        confirmButtonText: '确认恢复',
        cancelButtonText: '取消',
        type: 'warning'
      })

      const response = await fetchBatchRestoreNews({
        news_ids: newsIds
      })

      clearNewsSelection()
      await getNewsList()

      if (activeNews.value && response.restored_news_ids.includes(activeNews.value.id)) {
        detailVisible.value = false
        activeNews.value = null
      }

      ElMessage.success(`已恢复 ${response.restored_count} 条公告`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('批量恢复公告失败')
    }
  }

  function buildWritePayload(form: ReturnType<typeof emptyWriteForm>) {
    form.title = form.title.trim()
    form.color = form.color.trim()

    if (!form.type) {
      ElMessage.warning('请选择公告类型')
      return null
    }

    if (!form.title) {
      ElMessage.warning('请输入公告标题')
      return null
    }

    if (form.color.length > 50) {
      ElMessage.warning('标题颜色长度过长')
      return null
    }

    return {
      type: form.type,
      title: form.title,
      color: form.color,
      content: form.content,
      status: form.status
    }
  }

  function emptyWriteForm() {
    return {
      type: '1',
      title: '',
      color: '',
      content: '',
      status: '1'
    }
  }

  function resetWriteForm(form: ReturnType<typeof emptyWriteForm>) {
    Object.assign(form, emptyWriteForm())
  }

  function syncWriteForm(form: ReturnType<typeof emptyWriteForm>, item: NewsItem) {
    form.type = String(item.type ?? 1)
    form.title = item.title || ''
    form.color = item.color || ''
    form.content = item.content || ''
    form.status = String(item.status ?? 1)
  }

  function syncActiveNews(item: NewsItem) {
    if (activeNews.value?.id === item.id) {
      activeNews.value = item
    }
  }

  function buildNewsDeleteBlockedMessage(audit: Api.News.NewsDeleteAudit, title: string) {
    return [
      `${displayAdminFixtureText(title, title)} 当前暂不可移入回收站。`,
      ...audit.blocking_reasons.map((item) => displayAdminFixtureText(item, item)),
      ...audit.warnings.map((item) => displayAdminFixtureText(item, item))
    ]
      .filter(Boolean)
      .join('\n')
  }

  function buildNewsDeletePromptMessage(audit: Api.News.NewsDeleteAudit, title: string) {
    return [
      `确认将 ${displayAdminFixtureText(title, title)} 移入回收站吗？`,
      `公告类型：${typeLabel(audit.type)}`,
      ...audit.warnings.map((item) => displayAdminFixtureText(item, item)),
      `请输入 ${audit.confirmation_phrase} 后继续。`
    ]
      .filter(Boolean)
      .join('\n')
  }

  function buildNewsBatchDeleteBlockedMessage(audit: Api.News.NewsBatchDeleteAudit) {
    return [
      '当前所选公告暂时无法批量移入回收站。',
      ...audit.warnings.map((item) => displayAdminFixtureText(item, item)),
      ...audit.items.flatMap((item) =>
        item.can_delete || item.blocking_reasons.length === 0
          ? []
          : [
              `#${item.news_id || '--'} ${displayAdminFixtureText(item.news_label || '未知公告', item.news_label || '未知公告')}：${item.blocking_reasons.map((reason) => displayAdminFixtureText(reason, reason)).join('；')}`
            ]
      )
    ]
      .filter(Boolean)
      .join('\n')
  }

  function buildNewsBatchDeletePromptMessage(audit: Api.News.NewsBatchDeleteAudit) {
    return [
      `确认将 ${audit.summary.deletable_count} 条已选公告移入回收站吗？`,
      `命中记录：${audit.summary.existing_count}`,
      `影响行数：${audit.summary.delete_row_count}`,
      ...audit.warnings.map((item) => displayAdminFixtureText(item, item)),
      `请输入 ${audit.confirmation_phrase} 后继续。`
    ]
      .filter(Boolean)
      .join('\n')
  }

  function typeLabel(type: number) {
    return (
      {
        1: '平台公告',
        2: '行业资讯',
        3: '常见问题'
      }[type] || '未知类型'
    )
  }

  function escapeRegExp(value: string) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  }

  function clearNewsSelection() {
    selectedNews.value = []
    tableRef.value?.elTableRef?.clearSelection?.()
  }

  function emptySummary(): NewsSummary {
    return {
      enabled_count: 0,
      disabled_count: 0,
      platform_count: 0,
      industry_count: 0,
      faq_count: 0,
      content_count: 0,
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
  .news-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
    --news-color-dot-border: rgb(148 163 184 / 0.35);
    --news-content-box-border: var(--el-border-color-lighter);
    --news-content-box-bg: rgb(248 250 252 / 0.88);
  }

  :global(html.dark .news-page ){
    --news-color-dot-border: rgb(71 85 105 / 0.6);
    --news-content-box-border: rgb(71 85 105 / 0.42);
    --news-content-box-bg: rgb(15 23 42 / 0.78);
  }

  .news-cell {
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

  .color-summary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  .color-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    border: 1px solid var(--news-color-dot-border);
    background: #cbd5e1;
  }

  .news-detail {
    min-height: 240px;
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

  .news-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }

  .dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
  }

  .drawer-section {
    margin-bottom: 24px;
  }

  .drawer-section h4 {
    margin: 0 0 12px;
    color: var(--el-text-color-primary);
    font-size: 15px;
  }

  .content-box {
    min-height: 160px;
    max-height: 420px;
    padding: 16px;
    overflow: auto;
    color: var(--el-text-color-regular);
    font-family: inherit;
    font-size: 14px;
    line-height: 1.8;
    white-space: pre-wrap;
    word-break: break-word;
    border: 1px solid var(--news-content-box-border);
    border-radius: 14px;
    background: var(--news-content-box-bg);
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

    .news-form-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
