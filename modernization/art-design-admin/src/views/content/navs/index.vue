<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <div class="nav-page art-full-height">
    <ArtSearchBar
      v-model="searchForm"
      :items="searchItems"
      :showExpand="false"
      @search="handleSearch"
      @reset="handleReset"
    />

    <ElCard class="art-table-card">
      <ArtTableHeader v-model:columns="columnChecks" :loading="loading" @refresh="getNavList">
        <template #left>
          <ElSpace wrap>
            <ElTag effect="plain">导航总数 {{ pagination.total }}</ElTag>
            <ElTag type="success" effect="plain">启用中 {{ summary.enabled_count }}</ElTag>
            <ElTag type="info" effect="plain">已停用 {{ summary.disabled_count }}</ElTag>
            <ElTag type="primary" effect="plain">新窗口打开 {{ summary.new_window_count }}</ElTag>
            <ElTag effect="plain">当前窗口打开 {{ summary.same_window_count }}</ElTag>
            <ElTag type="info" effect="plain">回收站 {{ summary.deleted_count }}</ElTag>
            <ElButton plain :type="isRecycleView ? 'primary' : 'info'" @click="toggleRecycleView">
              {{ isRecycleView ? '返回正常列表' : '回收站' }}
            </ElButton>
            <ElButton
              v-if="!isRecycleView && hasAuth('add')"
              type="primary"
              @click="openCreateDialog"
            >
              新建导航
            </ElButton>
            <ElButton
              v-if="!isRecycleView && hasAuth('batchRemove')"
              plain
              type="danger"
              :disabled="selectedNavs.length === 0"
              @click="handleBatchDeleteNavs"
            >
              批量删除
            </ElButton>
            <ElButton
              v-if="isRecycleView && hasAuth('recycle')"
              plain
              type="success"
              :disabled="selectedNavs.length === 0"
              @click="handleBatchRestoreNavs"
            >
              批量恢复
            </ElButton>
            <ElTag v-if="selectedNavs.length > 0" type="danger" effect="plain">
              已选 {{ selectedNavs.length }} 条
            </ElTag>
            <ElTag type="info" effect="plain">
              {{ isRecycleView ? '导航回收恢复视图' : '公共导航维护面板' }}
            </ElTag>
            <ElTag v-if="!isRecycleView && hasAuth('sort')" type="primary" effect="plain"
              >支持拖拽排序</ElTag
            >
          </ElSpace>
        </template>
      </ArtTableHeader>

      <VueDraggable
        v-model="navList"
        target="tbody"
        handle=".nav-drag-handle"
        :animation="150"
        :disabled="!hasAuth('sort') || loading || reorderingNavs || isRecycleView"
        @end="handleNavDragEnd"
      >
        <ArtTable
          ref="tableRef"
          :loading="loading || reorderingNavs"
          :data="navList"
          :columns="columns"
          :pagination="pagination"
          row-key="id"
          reserve-selection
          @selection-change="handleNavSelectionChange"
          @pagination:size-change="handleSizeChange"
          @pagination:current-change="handleCurrentChange"
        />
      </VueDraggable>
    </ElCard>

    <ElDrawer
      v-model="detailVisible"
      size="760px"
      destroy-on-close
      :title="activeNav ? `${displayNavName(activeNav)} / 第 ${activeNav.id} 号` : '导航详情'"
    >
      <div v-loading="detailLoading" class="nav-detail">
        <template v-if="activeNav">
          <div class="detail-hero">
            <div class="detail-hero-copy">
              <h3>{{ displayNavName(activeNav) }}</h3>
              <p>{{ displayNavUrlText(activeNav) }}</p>
              <span>这里用于查看导航入口、打开方式、排序权重以及回收状态。</span>
            </div>
            <div class="detail-hero-actions">
              <ElButton v-if="canEditNav(activeNav)" plain @click="openEditDialog()">
                编辑
              </ElButton>
              <ElButton
                v-if="canToggleStatusNav(activeNav)"
                :type="activeNav.status === 1 ? 'warning' : 'success'"
                plain
                @click="handleToggleStatusNav()"
              >
                {{ activeNav.status === 1 ? '停用' : '启用' }}
              </ElButton>
              <ElButton
                v-if="canToggleTargetNav(activeNav)"
                type="primary"
                plain
                @click="handleToggleTargetNav()"
              >
                {{ activeNav.is_target === 1 ? '改为当前窗口打开' : '改为新窗口打开' }}
              </ElButton>
              <ElButton
                v-if="canDeleteNav(activeNav)"
                type="danger"
                plain
                @click="handleDeleteNav()"
              >
                删除
              </ElButton>
              <ElButton
                v-if="activeNav.is_deleted && hasAuth('recycle')"
                type="success"
                plain
                @click="handleRestoreNav()"
              >
                恢复导航
              </ElButton>
            </div>
          </div>

          <div class="drawer-section">
            <ElDescriptions :column="2" border>
              <ElDescriptionsItem label="导航名称">
                {{ displayNavName(activeNav) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="当前状态">
                {{ displayNavStatus(activeNav) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="跳转位置">
                <a
                  v-if="activeNav.url_link"
                  class="cell-link"
                  :href="activeNav.url_link"
                  :target="activeNav.is_external ? '_blank' : '_self'"
                  rel="noopener noreferrer"
                >
                  {{ displayNavUrlText(activeNav) }}
                </a>
                <span v-else>{{ displayNavUrlText(activeNav) }}</span>
              </ElDescriptionsItem>
              <ElDescriptionsItem label="打开方式">
                {{ displayNavTarget(activeNav) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="排序权重">
                {{ activeNav.sort }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="链接类型">
                {{ activeNav.is_external ? '外部链接' : '站内链接' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="创建时间">
                {{ activeNav.create_time || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="回收时间">
                {{ activeNav.delete_time || '--' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>维护信息</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="记录编号">
                {{ activeNav.id }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="状态值">
                {{ activeNav.status }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="打开方式值">
                {{ activeNav.is_target }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

        </template>
      </div>
    </ElDrawer>

    <ElDialog v-model="createVisible" width="620px" destroy-on-close align-center title="新建导航">
      <ElForm label-position="top">
        <div class="nav-form-grid">
          <ElFormItem label="导航名称">
            <ElInput v-model="createForm.name" maxlength="50" placeholder="输入导航名称" />
          </ElFormItem>
          <ElFormItem label="排序权重">
            <ElInput v-model="createForm.sort" maxlength="10" placeholder="输入排序值" />
          </ElFormItem>
          <ElFormItem label="状态">
            <ElSelect v-model="createForm.status" placeholder="请选择状态">
              <ElOption label="启用中" value="1" />
              <ElOption label="已停用" value="0" />
            </ElSelect>
          </ElFormItem>
          <ElFormItem label="打开方式">
            <ElSelect v-model="createForm.is_target" placeholder="请选择打开方式">
              <ElOption label="当前窗口打开" value="0" />
              <ElOption label="新窗口打开" value="1" />
            </ElSelect>
          </ElFormItem>
        </div>
        <ElFormItem label="跳转地址">
          <ElInput v-model="createForm.url" maxlength="2000" placeholder="输入站内路径或完整链接" />
        </ElFormItem>
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="createVisible = false">取消</ElButton>
          <ElButton type="primary" :loading="creatingNav" @click="submitCreateNav">
            确认新建
          </ElButton>
        </div>
      </template>
    </ElDialog>

    <ElDialog v-model="editVisible" width="620px" destroy-on-close align-center title="编辑导航">
      <ElForm label-position="top">
        <div class="nav-form-grid">
          <ElFormItem label="导航名称">
            <ElInput v-model="editForm.name" maxlength="50" placeholder="输入导航名称" />
          </ElFormItem>
          <ElFormItem label="排序权重">
            <ElInput v-model="editForm.sort" maxlength="10" placeholder="输入排序值" />
          </ElFormItem>
          <ElFormItem label="状态">
            <ElSelect v-model="editForm.status" placeholder="请选择状态">
              <ElOption label="启用中" value="1" />
              <ElOption label="已停用" value="0" />
            </ElSelect>
          </ElFormItem>
          <ElFormItem label="打开方式">
            <ElSelect v-model="editForm.is_target" placeholder="请选择打开方式">
              <ElOption label="当前窗口打开" value="0" />
              <ElOption label="新窗口打开" value="1" />
            </ElSelect>
          </ElFormItem>
        </div>
        <ElFormItem label="跳转地址">
          <ElInput v-model="editForm.url" maxlength="2000" placeholder="输入站内路径或完整链接" />
        </ElFormItem>
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="editVisible = false">取消</ElButton>
          <ElButton type="primary" :loading="savingEdit" @click="submitEditNav">
            保存修改
          </ElButton>
        </div>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox, ElTag } from 'element-plus'
  import { VueDraggable } from 'vue-draggable-plus'
  import { useAuth } from '@/hooks/core/useAuth'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import ArtButtonTable from '@/components/core/forms/artButtonTable/index.vue'
  import { displayAdminFixtureText, displayAdminFixtureUrl } from '@/utils/adminFixtureText'
  import {
    fetchAuditNavBatchDelete,
    fetchBatchDeleteNavs,
    fetchBatchRestoreNavs,
    fetchCreateNav,
    fetchDeleteNav,
    fetchGetNavDeleteAudit,
    fetchGetNavDetail,
    fetchGetNavList,
    fetchReorderNavs,
    fetchRestoreNav,
    fetchUpdateNav,
    fetchUpdateNavStatus,
    fetchUpdateNavTarget
  } from '@/api/navs'

  defineOptions({ name: 'ContentNavs' })

  type NavItem = Api.Navs.NavListItem
  type NavSummary = Api.Navs.NavSummary

  const { hasAuth } = useAuth()
  const tableRef = ref<{ elTableRef?: { clearSelection?: () => void } } | null>(null)
  const loading = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const createVisible = ref(false)
  const editVisible = ref(false)
  const creatingNav = ref(false)
  const savingEdit = ref(false)
  const reorderingNavs = ref(false)
  const navList = ref<NavItem[]>([])
  const selectedNavs = ref<NavItem[]>([])
  const activeNav = ref<NavItem | null>(null)
  const editNavId = ref<number | null>(null)
  const pagination = reactive({
    current: 1,
    size: 20,
    total: 0
  })
  const summary = reactive<NavSummary>(emptySummary())
  const searchForm = ref<{
    keyword?: string
    status?: string
    is_target?: string
  }>({})
  const createForm = reactive(emptyWriteForm())
  const editForm = reactive(emptyWriteForm())

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
        placeholder: '搜索导航名称或跳转地址'
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
          { label: '已停用', value: '0' },
          { label: '回收站', value: '-1' }
        ]
      }
    },
    {
      label: '打开方式',
      key: 'is_target',
      type: 'select',
      props: {
        placeholder: '请选择打开方式',
        options: [
          { label: '当前窗口打开', value: '0' },
          { label: '新窗口打开', value: '1' }
        ]
      }
    }
  ])

  const { columnChecks, columns } = useTableColumns<NavItem>(() => [
    { type: 'selection', width: 54, fixed: 'left' as const },
    ...(hasAuth('sort') && !isRecycleView.value
      ? [
          {
            prop: 'drag',
            label: '',
            width: 78,
            align: 'center' as const,
            fixed: 'left' as const,
            formatter: () => h('span', { class: 'nav-drag-handle' }, '拖拽')
          }
        ]
      : []),
    { type: 'globalIndex', width: 70, label: '序号' },
    {
      prop: 'name',
      label: '导航信息',
      minWidth: 260,
      formatter: (row) =>
        h('div', { class: 'nav-cell' }, [
          h('strong', { class: 'cell-title' }, displayNavName(row)),
          h('p', { class: 'cell-sub' }, `排序：${row.sort}`)
        ])
    },
    {
      prop: 'url',
      label: '跳转位置',
      minWidth: 300,
      formatter: (row) =>
        row.url_link
          ? h(
              'a',
              {
                class: 'cell-link',
                href: row.url_link,
                target: row.is_external ? '_blank' : '_self',
                rel: 'noopener noreferrer'
              },
              displayNavUrlText(row)
            )
          : h('span', { class: 'cell-sub' }, displayNavUrlText(row))
    },
    {
      prop: 'target_label',
      label: '打开方式',
      width: 140,
      align: 'center' as const,
      formatter: (row) =>
        h(ElTag, { type: tagType(row.target_type), effect: 'light' }, () => displayNavTarget(row))
    },
    {
      prop: 'status_label',
      label: '状态',
      width: 120,
      align: 'center' as const,
      formatter: (row) =>
        h(ElTag, { type: tagType(row.status_type), effect: 'light' }, () => displayNavStatus(row))
    },
    {
      prop: 'create_time',
      label: '创建时间',
      minWidth: 170,
      formatter: (row) => row.create_time || '--'
    },
    {
      prop: 'operation',
      label: '操作',
      width: 360,
      align: 'center' as const,
      fixed: 'right' as const,
      formatter: (row) => renderNavOperationButtons(row)
    }
  ])

  onMounted(() => {
    getNavList()
  })

  function displayNavName(nav?: Partial<NavItem> | null) {
    if (!nav) {
      return '导航'
    }

    if (nav.name) {
      return displayAdminFixtureText(nav.name, nav.name)
    }

    return `导航 #${nav.id || '--'}`
  }

  function displayNavUrlText(nav?: Partial<NavItem> | null) {
    return displayAdminFixtureUrl(nav?.url, nav?.url || '--')
  }

  function displayNavStatus(nav?: Partial<NavItem> | null) {
    return displayAdminFixtureText(nav?.status_label, '未知状态')
  }

  function displayNavTarget(nav?: Partial<NavItem> | null) {
    return displayAdminFixtureText(nav?.target_label, '未知打开方式')
  }

  function renderNavOperationButtons(row: NavItem) {
    const actions = [
      h(ArtButtonTable, {
        type: 'view',
        title: '详情',
        onClick: () => openDetail(row)
      })
    ]

    if (canEditNav(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:pencil-line',
          iconClass: 'bg-primary/12 text-primary',
          title: '编辑',
          onClick: () => openEditDialog(row)
        })
      )
    }

    if (canToggleStatusNav(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: row.status === 1 ? 'ri:forbid-line' : 'ri:check-line',
          iconClass: row.status === 1 ? 'bg-warning/12 text-warning' : 'bg-success/12 text-success',
          title: row.status === 1 ? '停用' : '启用',
          onClick: () => handleToggleStatusNav(row)
        })
      )
    }

    if (canToggleTargetNav(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: row.is_target === 1 ? 'ri:links-line' : 'ri:share-box-line',
          iconClass: 'bg-primary/12 text-primary',
          title: row.is_target === 1 ? '当前窗口打开' : '新窗口打开',
          onClick: () => handleToggleTargetNav(row)
        })
      )
    }

    if (canDeleteNav(row)) {
      actions.push(
        h(ArtButtonTable, {
          type: 'delete',
          title: '删除',
          onClick: () => handleDeleteNav(row)
        })
      )
    }

    if (row.is_deleted && hasAuth('recycle')) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:restart-line',
          iconClass: 'bg-success/12 text-success',
          title: '恢复',
          onClick: () => handleRestoreNav(row)
        })
      )
    }

    return h('div', { class: 'table-actions' }, actions)
  }

  function canEditNav(nav?: NavItem | null) {
    return Boolean(nav && !nav.is_deleted && hasAuth('edit'))
  }

  function canToggleStatusNav(nav?: NavItem | null) {
    return Boolean(nav && !nav.is_deleted && hasAuth('status'))
  }

  function canToggleTargetNav(nav?: NavItem | null) {
    return Boolean(nav && !nav.is_deleted && hasAuth('target'))
  }

  function canDeleteNav(nav?: NavItem | null) {
    return Boolean(nav && !nav.is_deleted && hasAuth('remove'))
  }

  async function getNavList() {
    loading.value = true
    try {
      const response = await fetchGetNavList({
        current: pagination.current,
        size: pagination.size,
        keyword: searchForm.value.keyword,
        status: searchForm.value.status,
        is_target: searchForm.value.is_target
      })
      navList.value = response.records
      pagination.current = response.current
      pagination.size = response.size
      pagination.total = response.total
      Object.assign(summary, response.summary || emptySummary())
    } catch (_error) {
      ElMessage.error('加载导航列表失败')
    } finally {
      loading.value = false
    }
  }

  function handleSearch(params: Api.Navs.NavSearchParams) {
    pagination.current = 1
    clearNavSelection()
    searchForm.value = {
      keyword: params.keyword,
      status: params.status as string | undefined,
      is_target: params.is_target as string | undefined
    }
    getNavList()
  }

  function handleReset() {
    pagination.current = 1
    clearNavSelection()
    searchForm.value = {}
    getNavList()
  }

  function toggleRecycleView() {
    pagination.current = 1
    clearNavSelection()
    searchForm.value = {
      ...searchForm.value,
      status: isRecycleView.value ? undefined : '-1'
    }
    getNavList()
  }

  function handleSizeChange(size: number) {
    pagination.size = size
    pagination.current = 1
    getNavList()
  }

  function handleCurrentChange(current: number) {
    pagination.current = current
    getNavList()
  }

  function handleNavSelectionChange(rows: NavItem[]) {
    selectedNavs.value = rows
  }

  async function handleNavDragEnd(event: { oldIndex?: number; newIndex?: number }) {
    if (isRecycleView.value) {
      return
    }

    const fromIndex = Number(event.oldIndex ?? -1)
    const toIndex = Number(event.newIndex ?? -1)

    if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex) {
      return
    }

    const visibleNavIds = navList.value.map((item) => item.id)
    reorderingNavs.value = true

    try {
      await fetchReorderNavs({
        visible_nav_ids: visibleNavIds,
        from_index: fromIndex,
        to_index: toIndex
      })

      await getNavList()
      syncActiveNavFromList()
      ElMessage.success('导航排序已更新')
    } catch (_error) {
      await getNavList()
      syncActiveNavFromList()
      ElMessage.error('更新导航排序失败')
    } finally {
      reorderingNavs.value = false
    }
  }

  function openCreateDialog() {
    resetWriteForm(createForm)
    createVisible.value = true
  }

  function openEditDialog(row?: NavItem) {
    const target = row || activeNav.value
    if (!target) {
      return
    }

    if (!canEditNav(target)) {
      ElMessage.warning('请先恢复该导航，再进行编辑')
      return
    }

    editNavId.value = target.id
    syncWriteForm(editForm, target)
    editVisible.value = true
  }

  async function openDetail(row: NavItem) {
    detailVisible.value = true
    detailLoading.value = true
    activeNav.value = row

    try {
      const response = await fetchGetNavDetail(row.id)
      activeNav.value = response.item
    } catch (_error) {
      ElMessage.error('加载导航详情失败')
    } finally {
      detailLoading.value = false
    }
  }

  async function submitCreateNav() {
    const payload = buildWritePayload(createForm)
    if (!payload) {
      return
    }

    creatingNav.value = true
    try {
      const response = await fetchCreateNav(payload)
      createVisible.value = false
      resetWriteForm(createForm)
      clearNavSelection()
      await getNavList()
      ElMessage.success(
        `导航 ${displayAdminFixtureText(response.created_nav_label || payload.name, payload.name)} 已创建，当前状态为${displayNavStatus(response.item)}`
      )
    } finally {
      creatingNav.value = false
    }
  }

  async function submitEditNav() {
    if (!editNavId.value) {
      return
    }

    const payload = buildWritePayload(editForm)
    if (!payload) {
      return
    }

    savingEdit.value = true
    try {
      const response = await fetchUpdateNav(editNavId.value, payload)
      editVisible.value = false
      syncActiveNav(response.item)
      clearNavSelection()
      await getNavList()
      ElMessage.success(
        `导航 ${displayAdminFixtureText(response.updated_nav_label || payload.name, payload.name)} 已更新`
      )
    } finally {
      savingEdit.value = false
    }
  }

  async function handleToggleStatusNav(row?: NavItem) {
    const target = row || activeNav.value
    if (!target) {
      return
    }

    if (!canToggleStatusNav(target)) {
      ElMessage.warning('请先恢复该导航，再调整状态')
      return
    }

    const nextStatus = target.status === 1 ? 0 : 1
    const nextLabel = nextStatus === 1 ? '启用' : '停用'

    try {
      await ElMessageBox.confirm(
        `确认${nextStatus === 1 ? '启用' : '停用'} ${displayNavName(target)} 吗？`,
        `${nextStatus === 1 ? '启用' : '停用'}导航`,
        {
          confirmButtonText: nextStatus === 1 ? '确认启用' : '确认停用',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchUpdateNavStatus(target.id, {
        status: nextStatus
      })
      syncActiveNav(response.item)
      clearNavSelection()
      await getNavList()
      ElMessage.success(
        `导航 ${displayAdminFixtureText(response.updated_nav_label || target.name || target.url || `#${target.id}`, target.name || target.url || `#${target.id}`)} 已${nextLabel}`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('更新导航状态失败')
    }
  }

  async function handleToggleTargetNav(row?: NavItem) {
    const target = row || activeNav.value
    if (!target) {
      return
    }

    if (!canToggleTargetNav(target)) {
      ElMessage.warning('请先恢复该导航，再调整打开方式')
      return
    }

    const nextTarget = target.is_target === 1 ? 0 : 1
    const nextLabel = nextTarget === 1 ? '改为新窗口打开' : '改为当前窗口打开'

    try {
      await ElMessageBox.confirm(
        `确认将 ${displayNavName(target)} ${nextLabel}吗？`,
        '切换打开方式',
        {
          confirmButtonText: '确认切换',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchUpdateNavTarget(target.id, {
        is_target: nextTarget
      })
      syncActiveNav(response.item)
      clearNavSelection()
      await getNavList()
      ElMessage.success(
        `导航 ${displayAdminFixtureText(response.updated_nav_label || target.name || target.url || `#${target.id}`, target.name || target.url || `#${target.id}`)} 已${nextLabel}`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('更新导航打开方式失败')
    }
  }

  async function handleDeleteNav(row?: NavItem) {
    const target = row || activeNav.value
    if (!target) {
      return
    }

    if (!canDeleteNav(target)) {
      ElMessage.warning('该导航已在回收站中')
      return
    }

    try {
      const response = await fetchGetNavDeleteAudit(target.id)
      const audit = response.audit
      const title = target.name || target.url || `导航 #${target.id}`

      if (!audit.can_delete) {
        await ElMessageBox.alert(buildNavDeleteBlockedMessage(audit, title), '当前不可删除', {
          type: 'warning',
          confirmButtonText: '我知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildNavDeletePromptMessage(audit, title),
        '删除导航',
        {
          confirmButtonText: '确认删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: '请输入删除确认短语',
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase}`
        }
      )

      const deleteResponse = await fetchDeleteNav(target.id, {
        confirmation_phrase: String(value || '')
      })

      if (activeNav.value?.id === target.id) {
        detailVisible.value = false
        activeNav.value = null
      }

      clearNavSelection()
      await getNavList()
      ElMessage.success(
        `导航 ${displayAdminFixtureText(deleteResponse.deleted_nav_label || title, title)} 已移入回收站`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('删除导航失败')
    }
  }

  async function handleRestoreNav(row?: NavItem) {
    const target = row || activeNav.value
    if (!target) {
      return
    }

    if (!target.is_deleted) {
      ElMessage.warning('该导航当前已在正常列表中')
      return
    }

    try {
      await ElMessageBox.confirm(
        `确认将 ${displayNavName(target)} 恢复到正常列表吗？`,
        '恢复导航',
        {
          confirmButtonText: '确认恢复',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchRestoreNav(target.id)
      syncActiveNav(response.item)
      clearNavSelection()
      await getNavList()

      if (isRecycleView.value && activeNav.value?.id === target.id) {
        detailVisible.value = false
        activeNav.value = null
      }

      ElMessage.success(
        `导航 ${displayAdminFixtureText(response.restored_nav_label || target.name || target.url || `#${target.id}`, target.name || target.url || `#${target.id}`)} 已恢复`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('恢复导航失败')
    }
  }

  async function handleBatchDeleteNavs() {
    const activeSelection = selectedNavs.value.filter((item) => !item.is_deleted)
    if (activeSelection.length === 0) {
      ElMessage.warning('请至少选择一条正常状态的导航')
      return
    }

    const navIds = activeSelection.map((item) => item.id)

    try {
      const response = await fetchAuditNavBatchDelete({
        nav_ids: navIds
      })
      const audit = response.audit

      if (!audit.can_delete_all) {
        await ElMessageBox.alert(buildNavBatchDeleteBlockedMessage(audit), '当前不可批量删除', {
          type: 'warning',
          confirmButtonText: '我知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildNavBatchDeletePromptMessage(audit),
        '批量删除导航',
        {
          confirmButtonText: '确认批量删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: '请输入删除确认短语',
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase}`
        }
      )

      const deleteResponse = await fetchBatchDeleteNavs({
        nav_ids: navIds,
        confirmation_phrase: String(value || '')
      })

      if (activeNav.value && deleteResponse.deleted_nav_ids.includes(activeNav.value.id)) {
        detailVisible.value = false
        activeNav.value = null
      }

      clearNavSelection()
      await getNavList()
      ElMessage.success(`已将 ${deleteResponse.deleted_count} 条导航移入回收站`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('批量删除导航失败')
    }
  }

  async function handleBatchRestoreNavs() {
    const recycleSelection = selectedNavs.value.filter((item) => item.is_deleted)
    if (recycleSelection.length === 0) {
      ElMessage.warning('请至少选择一条回收站中的导航')
      return
    }

    const navIds = recycleSelection.map((item) => item.id)

    try {
      await ElMessageBox.confirm(`确认恢复所选 ${navIds.length} 条导航吗？`, '批量恢复导航', {
        confirmButtonText: '确认恢复',
        cancelButtonText: '取消',
        type: 'warning'
      })

      const response = await fetchBatchRestoreNavs({
        nav_ids: navIds
      })

      clearNavSelection()
      await getNavList()

      if (activeNav.value && response.restored_nav_ids.includes(activeNav.value.id)) {
        detailVisible.value = false
        activeNav.value = null
      }

      ElMessage.success(`已恢复 ${response.restored_count} 条导航`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('批量恢复导航失败')
    }
  }

  function buildWritePayload(form: ReturnType<typeof emptyWriteForm>) {
    form.name = form.name.trim()
    form.url = form.url.trim()
    form.sort = form.sort.trim()

    if (!form.name) {
      ElMessage.warning('请输入导航名称')
      return null
    }

    if (form.sort && !/^\d+$/.test(form.sort)) {
      ElMessage.warning('排序权重必须为非负整数')
      return null
    }

    return {
      name: form.name,
      url: form.url,
      sort: form.sort || '0',
      status: form.status,
      is_target: form.is_target
    }
  }

  function emptyWriteForm() {
    return {
      name: '',
      url: '',
      sort: '0',
      status: '1',
      is_target: '0'
    }
  }

  function resetWriteForm(form: ReturnType<typeof emptyWriteForm>) {
    Object.assign(form, emptyWriteForm())
  }

  function syncWriteForm(form: ReturnType<typeof emptyWriteForm>, item: NavItem) {
    form.name = item.name || ''
    form.url = item.url || ''
    form.sort = String(item.sort ?? 0)
    form.status = String(item.status ?? 0)
    form.is_target = String(item.is_target ?? 0)
  }

  function syncActiveNav(item: NavItem) {
    if (activeNav.value?.id === item.id) {
      activeNav.value = item
    }
  }

  function syncActiveNavFromList() {
    if (!activeNav.value) {
      return
    }

    const current = navList.value.find((item) => item.id === activeNav.value?.id)
    if (current) {
      activeNav.value = {
        ...activeNav.value,
        ...current
      }
    }
  }

  function buildNavDeleteBlockedMessage(audit: Api.Navs.NavDeleteAudit, title: string) {
    return [
      `${displayAdminFixtureText(title, title)} 当前暂不可移入回收站。`,
      ...audit.blocking_reasons,
      ...audit.warnings
    ]
      .filter(Boolean)
      .join('\n')
  }

  function buildNavDeletePromptMessage(audit: Api.Navs.NavDeleteAudit, title: string) {
    return [
      `确认将 ${displayAdminFixtureText(title, title)} 移入回收站吗？`,
      audit.url ? `跳转位置：${displayAdminFixtureUrl(audit.url, audit.url)}` : null,
      ...audit.warnings,
      `请输入 ${audit.confirmation_phrase} 后继续。`
    ]
      .filter(Boolean)
      .join('\n')
  }

  function buildNavBatchDeleteBlockedMessage(audit: Api.Navs.NavBatchDeleteAudit) {
    return [
      '当前所选导航暂时无法批量移入回收站。',
      ...audit.warnings,
      ...audit.items.flatMap((item) =>
        item.can_delete || item.blocking_reasons.length === 0
          ? []
          : [
              `#${item.nav_id || '--'} ${displayAdminFixtureText(item.nav_label || item.url || '未知导航', item.nav_label || item.url || '未知导航')}：${item.blocking_reasons.join('；')}`
            ]
      )
    ]
      .filter(Boolean)
      .join('\n')
  }

  function buildNavBatchDeletePromptMessage(audit: Api.Navs.NavBatchDeleteAudit) {
    return [
      `确认将 ${audit.summary.deletable_count} 条已选导航移入回收站吗？`,
      `命中记录：${audit.summary.existing_count}`,
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

  function clearNavSelection() {
    selectedNavs.value = []
    tableRef.value?.elTableRef?.clearSelection?.()
  }

  function emptySummary(): NavSummary {
    return {
      enabled_count: 0,
      disabled_count: 0,
      new_window_count: 0,
      same_window_count: 0,
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
  .nav-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .nav-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .nav-drag-handle {
    cursor: grab;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    font-weight: 600;
    user-select: none;
  }

  .nav-drag-handle:active {
    cursor: grabbing;
  }

  .cell-title {
    color: var(--el-text-color-primary);
    font-size: 14px;
    word-break: break-all;
  }

  .cell-sub,
  .cell-link {
    margin: 0;
    color: var(--el-text-color-secondary);
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

  .nav-detail {
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

  .nav-form-grid {
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

  @media (width <= 991px) {
    .detail-hero {
      flex-direction: column;
    }

    .detail-hero-actions {
      justify-content: flex-start;
    }

    .nav-form-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
