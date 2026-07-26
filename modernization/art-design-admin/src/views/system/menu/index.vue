<template>
  <div class="permission-page art-full-height">
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
        :showZebra="false"
        :loading="loading"
        layout="refresh"
        @refresh="getPermissionTree"
      >
        <template #left>
          <div class="summary-section">
            <ElSpace wrap>
              <ElTag effect="plain">节点 {{ summary.total }}</ElTag>
              <ElTag type="success" effect="plain">启用 {{ summary.enabled_count }}</ElTag>
              <ElTag type="warning" effect="plain">停用 {{ summary.disabled_count }}</ElTag>
              <ElTag type="info" effect="plain">根节点 {{ summary.tree_root_count }}</ElTag>
              <ElTag v-if="summary.orphan_count" type="danger" effect="plain">
                孤立节点 {{ summary.orphan_count }}
              </ElTag>
              <ElButton @click="toggleExpand" v-ripple>
                {{ isExpanded ? '收起树形' : '展开树形' }}
              </ElButton>
              <ElButton v-if="hasMenuCreateAuth" type="primary" @click="openCreateDialog()">
                新增根节点
              </ElButton>
            </ElSpace>
          </div>
        </template>
      </ArtTableHeader>

      <ArtTable
        ref="tableRef"
        rowKey="id"
        :loading="loading"
        :columns="columns"
        :data="permissionTree"
        :stripe="false"
        :tree-props="{ children: 'children' }"
        :default-expand-all="false"
      />
    </ElCard>

    <ElDrawer
      v-model="detailVisible"
      size="780px"
      destroy-on-close
      :title="
        activePermission
          ? `${activePermission.title || '菜单节点'} / #${activePermission.id}`
          : '菜单详情'
      "
    >
      <div v-loading="detailLoading" class="permission-detail">
        <template v-if="activePermission">
          <div class="detail-hero">
            <div class="detail-hero-copy">
              <h3>{{ activePermission.title || `菜单节点 #${activePermission.id}` }}</h3>
              <p>{{ modernRouteSummary(activePermission) }}</p>
              <span>
                {{ activePermission.type_label }} / {{ activePermission.status_label }} / 排序
                {{ activePermission.sort }}
              </span>
            </div>
            <div class="detail-hero-actions">
              <ElButton v-if="hasMenuCreateAuth" plain @click="openCreateDialog(activePermission)">
                新增子节点
              </ElButton>
              <ElButton v-if="canEditPermission(activePermission)" plain @click="openEditDialog()">
                编辑
              </ElButton>
              <ElButton
                v-if="canToggleStatusPermission(activePermission)"
                :type="activePermission.status === 1 ? 'warning' : 'success'"
                plain
                @click="handleToggleStatusPermission()"
              >
                {{ activePermission.status === 1 ? '停用' : '启用' }}
              </ElButton>
              <ElButton
                v-if="canDeletePermission(activePermission)"
                type="danger"
                plain
                @click="handleDeletePermission()"
              >
                删除
              </ElButton>
            </div>
          </div>

          <div class="drawer-section">
            <div class="drawer-grid">
              <div class="drawer-item">
                <span>节点名称</span>
                <strong>{{ activePermission.title }}</strong>
              </div>
              <div class="drawer-item">
                <span>节点编号</span>
                <strong>{{ activePermission.id }}</strong>
              </div>
              <div class="drawer-item">
                <span>父级编号</span>
                <strong>{{ activePermission.parent_id || '顶级节点' }}</strong>
              </div>
              <div class="drawer-item">
                <span>节点类型</span>
                <strong>{{ activePermission.type_label }}</strong>
              </div>
              <div class="drawer-item">
                <span>状态</span>
                <strong>{{ activePermission.status_label }}</strong>
              </div>
              <div class="drawer-item">
                <span>权限标识</span>
                <strong>{{ activePermission.path || '--' }}</strong>
              </div>
              <div class="drawer-item">
                <span>所属分组</span>
                <strong>{{ activePermission.modern_group_title || '--' }}</strong>
              </div>
              <div class="drawer-item">
                <span>当前菜单</span>
                <strong>{{ activePermission.modern_menu_title || '--' }}</strong>
              </div>
              <div class="drawer-item">
                <span>访问入口</span>
                <strong>{{ activePermission.modern_route_path || '--' }}</strong>
              </div>
              <div class="drawer-item">
                <span>图标状态</span>
                <strong>{{ iconConfiguredSummary(activePermission.icon) }}</strong>
              </div>
            </div>
          </div>

          <div class="drawer-section">
            <h4>路由信息</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="当前分组">
                {{ activePermission.modern_group_title || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="当前菜单">
                {{ activePermission.modern_menu_title || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="权限标识">
                {{ activePermission.path || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="访问入口">
                {{ activePermission.modern_route_path || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="图标状态">
                {{ iconConfiguredSummary(activePermission.icon) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="排序">
                {{ activePermission.sort }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>直属子节点</h4>
            <div v-if="permissionChildren.length" class="child-list">
              <ElTag
                v-for="child in permissionChildren"
                :key="child.id"
                :type="tagType(child.status_type)"
                effect="plain"
              >
                {{ child.title }}
              </ElTag>
            </div>
            <span v-else class="empty-copy">暂无直属子节点</span>
          </div>
        </template>
      </div>
    </ElDrawer>

    <ElDialog
      v-model="writeDialogVisible"
      width="760px"
      destroy-on-close
      align-center
      :title="writeDialogTitle"
      @closed="resetWriteState"
    >
      <ElForm ref="writeFormRef" :model="writeForm" :rules="writeRules" label-position="top">
        <div class="write-form-grid">
          <ElFormItem label="父级节点" prop="parent_id">
            <ElTreeSelect
              v-model="writeForm.parent_id"
              class="write-form__tree"
              :data="parentTreeOptions"
              check-strictly
              default-expand-all
              filterable
              node-key="id"
              :render-after-expand="false"
              :props="parentTreeProps"
              placeholder="请选择父级节点"
            />
          </ElFormItem>
          <ElFormItem label="节点类型" prop="type">
            <ElSelect v-model="writeForm.type" placeholder="请选择节点类型">
              <ElOption label="目录" value="0" />
              <ElOption label="菜单 / 权限" value="1" />
            </ElSelect>
          </ElFormItem>
          <ElFormItem label="节点名称" prop="title">
            <ElInput v-model="writeForm.title" maxlength="50" placeholder="输入节点名称" />
          </ElFormItem>
          <ElFormItem label="状态" prop="status">
            <ElSelect v-model="writeForm.status" placeholder="请选择状态">
              <ElOption label="启用" value="1" />
              <ElOption label="停用" value="2" />
            </ElSelect>
          </ElFormItem>
          <ElFormItem label="页面路径" prop="path" class="write-form__wide">
            <ElInput
              v-model="writeForm.path"
              :disabled="writeForm.type === '0' || currentWriteHrefLocked"
              maxlength="50"
              placeholder="/system/menu"
            />
          </ElFormItem>
          <ElFormItem label="图标" prop="icon">
            <ElInput v-model="writeForm.icon" maxlength="50" placeholder="输入图标标识" />
          </ElFormItem>
          <ElFormItem label="排序值" prop="sort">
            <ElInput v-model="writeForm.sort" maxlength="3" placeholder="输入排序值" />
          </ElFormItem>
        </div>

        <p v-if="currentWriteHrefLocked" class="write-note">
          受保护节点建议只改名称、图标和排序。
        </p>
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="writeDialogVisible = false">取消</ElButton>
          <ElButton type="primary" :loading="savingWrite" @click="submitPermissionWrite">
            {{ writeDialogMode === 'create' ? '新增菜单节点' : '保存修改' }}
          </ElButton>
        </div>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox, ElTag, type FormInstance, type FormRules } from 'element-plus'
  import { useAuth } from '@/hooks'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import ArtButtonTable from '@/components/core/forms/artButtonTable/index.vue'
  import {
    fetchCreateAdminPermission,
    fetchDeleteAdminPermission,
    fetchGetAdminPermissionDeleteAudit,
    fetchGetAdminPermissionDetail,
    fetchGetAdminPermissionTree,
    fetchReorderAdminPermissions,
    fetchUpdateAdminPermission,
    fetchUpdateAdminPermissionStatus
  } from '@/api/permissions'

  defineOptions({ name: 'SystemMenu' })

  type PermissionItem = Api.Permissions.PermissionItem
  type PermissionSummary = Api.Permissions.PermissionSummary
  type PermissionDeleteAudit = Api.Permissions.PermissionDeleteAudit

  interface ParentTreeOption {
    id: number
    title: string
    disabled?: boolean
    children?: ParentTreeOption[]
  }

  const protectedSystemMenuPaths = [
    '/admin.permission/index',
    '/admin.permission/add',
    '/admin.permission/edit',
    '/admin.permission/status',
    '/admin.permission/remove'
  ]

  const { hasAuth } = useAuth()
  const loading = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const writeDialogVisible = ref(false)
  const savingWrite = ref(false)
  const isExpanded = ref(false)
  const tableRef = ref()
  const writeFormRef = ref<FormInstance>()
  const permissionTree = ref<PermissionItem[]>([])
  const activePermission = ref<PermissionItem | null>(null)
  const permissionChildren = ref<PermissionItem[]>([])
  const writeDialogMode = ref<'create' | 'edit'>('create')
  const writePermissionId = ref<number | null>(null)
  const searchForm = ref<{
    keyword?: string
    status?: string
    type?: string
  }>({})
  const summary = reactive<PermissionSummary>({
    total: 0,
    enabled_count: 0,
    disabled_count: 0,
    root_count: 0,
    tree_root_count: 0,
    orphan_count: 0,
    directory_count: 0,
    permission_count: 0,
    write_enabled_count: 0,
    read_only_count: 0,
    pending_write_count: 0,
    group_split_count: 0,
    legacy_only_count: 0,
    unmapped_count: 0
  })
  const writeForm = reactive(emptyWriteForm())
  const hasMenuCreateAuth = computed(() => hasAuth('add'))
  const hasMenuEditAuth = computed(() => hasAuth('edit'))
  const hasMenuSortAuth = computed(() => hasAuth('sort'))
  const hasMenuStatusAuth = computed(() => hasAuth('status'))
  const hasMenuDeleteAuth = computed(() => hasAuth('remove'))
  const hasAnyMenuWriteAuth = computed(
    () =>
      hasMenuCreateAuth.value ||
      hasMenuEditAuth.value ||
      hasMenuSortAuth.value ||
      hasMenuStatusAuth.value ||
      hasMenuDeleteAuth.value
  )
  const writeDialogTitle = computed(() =>
    writeDialogMode.value === 'create' ? '新增菜单节点' : '编辑菜单节点'
  )
  const currentWriteItem = computed(() =>
    writePermissionId.value
      ? findPermissionInTree(permissionTree.value, writePermissionId.value)
      : null
  )
  const currentWriteHrefLocked = computed(
    () =>
      writeDialogMode.value === 'edit' &&
      !!currentWriteItem.value &&
      isSystemMenuProtectedNode(currentWriteItem.value)
  )
  const parentTreeProps = {
    value: 'id',
    label: 'title',
    children: 'children',
    disabled: 'disabled'
  }
  const parentTreeOptions = computed<ParentTreeOption[]>(() => {
    const blockedIds = new Set<number>()
    if (writeDialogMode.value === 'edit' && writePermissionId.value) {
      const current = findPermissionInTree(permissionTree.value, writePermissionId.value)
      if (current) {
        collectNodeIds(current, blockedIds)
      }
    }

    return [
      {
        id: 0,
        title: '顶级节点',
        children: buildParentTreeOptions(permissionTree.value, blockedIds)
      }
    ]
  })

  const writeRules = reactive<FormRules>({
    title: [
      { required: true, message: '请输入菜单节点名称。', trigger: 'blur' },
      {
        validator: (_rule, value, callback) => {
          const length = String(value || '').trim().length
          if (length === 0) {
            callback(new Error('请输入菜单节点名称。'))
            return
          }
          if (length > 50) {
            callback(new Error('节点名称不能超过 50 个字符。'))
            return
          }
          callback()
        },
        trigger: 'blur'
      }
    ],
    path: [
      {
        validator: (_rule, value, callback) => {
          if (writeForm.type === '0') {
            callback()
            return
          }

          const normalized = String(value || '').trim()
          if (!normalized) {
            callback(new Error('菜单 / 权限节点必须填写页面路径。'))
            return
          }
          if (/\s/.test(normalized)) {
            callback(new Error('页面路径不能包含空白字符。'))
            return
          }
          if (normalized.length > 50) {
            callback(new Error('页面路径不能超过 50 个字符。'))
            return
          }
          callback()
        },
        trigger: 'blur'
      }
    ],
    icon: [
      {
        validator: (_rule, value, callback) => {
          if (String(value || '').trim().length > 50) {
            callback(new Error('图标字段不能超过 50 个字符。'))
            return
          }
          callback()
        },
        trigger: 'blur'
      }
    ],
    sort: [
      {
        validator: (_rule, value, callback) => {
          const normalized = String(value || '').trim()
          if (!/^\d+$/.test(normalized)) {
            callback(new Error('排序值必须为非负整数。'))
            return
          }
          const numeric = Number(normalized)
          if (numeric < 0 || numeric > 127) {
            callback(new Error('排序值必须介于 0 到 127 之间。'))
            return
          }
          callback()
        },
        trigger: 'blur'
      }
    ]
  })

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '搜索节点编号、名称、页面路径或图标'
      }
    },
    {
      label: '状态',
      key: 'status',
      type: 'select',
      props: {
        placeholder: '全部状态',
        options: [
          { label: '启用', value: '1' },
          { label: '停用', value: '2' }
        ]
      }
    },
    {
      label: '类型',
      key: 'type',
      type: 'select',
      props: {
        placeholder: '全部类型',
        options: [
          { label: '目录', value: '0' },
          { label: '菜单 / 权限', value: '1' }
        ]
      }
    }
  ])

  const iconConfiguredSummary = (icon: string | null | undefined) => {
    return String(icon || '').trim() ? '已设置' : '未设置'
  }

  const modernRouteSummary = (item: PermissionItem | null | undefined) => {
    if (!item) {
      return '--'
    }

    return (
      [item.modern_group_title, item.modern_menu_title]
        .map((part) => String(part || '').trim())
        .filter(Boolean)
        .join(' / ') || '--'
    )
  }

  const { columnChecks, columns } = useTableColumns<PermissionItem>(() => [
    {
      prop: 'title',
      label: '菜单节点',
      minWidth: 280,
      formatter: (row) => renderTitleCell(row)
    },
    {
      prop: 'modern_route_path',
      label: '菜单归属',
      minWidth: 320,
      formatter: (row) => renderModernCell(row)
    },
    {
      prop: 'path',
      label: '节点摘要',
      minWidth: 280,
      formatter: (row) => renderMetaCell(row)
    },
    {
      prop: 'type_label',
      label: '类型',
      width: 120,
      align: 'center',
      formatter: (row) =>
        h(ElTag, { type: tagType(row.type_tag), effect: 'light' }, () => row.type_label)
    },
    {
      prop: 'status_label',
      label: '状态',
      width: 110,
      align: 'center',
      formatter: (row) =>
        h(ElTag, { type: tagType(row.status_type), effect: 'light' }, () => row.status_label)
    },
    {
      prop: 'sort',
      label: '排序',
      width: 90,
      align: 'center'
    },
    {
      prop: 'operation',
      label: '操作',
      width: 430,
      align: 'center',
      fixed: 'right',
      formatter: (row) => renderOperationButtons(row)
    }
  ])

  onMounted(() => {
    getPermissionTree()
  })

  async function getPermissionTree() {
    loading.value = true
    try {
      const response = await fetchGetAdminPermissionTree({
        keyword: searchForm.value.keyword,
        status: searchForm.value.status,
        type: searchForm.value.type
      })
      permissionTree.value = response.tree
      Object.assign(summary, response.summary)
      syncActivePermissionFromTree()
    } catch (_error) {
      ElMessage.error('菜单树加载失败')
    } finally {
      loading.value = false
    }
  }

  function handleSearch(params: Record<string, unknown>) {
    searchForm.value = {
      keyword: params.keyword as string | undefined,
      status: params.status as string | undefined,
      type: params.type as string | undefined
    }
    getPermissionTree()
  }

  function handleReset() {
    searchForm.value = {}
    getPermissionTree()
  }

  async function openDetail(row: PermissionItem) {
    detailVisible.value = true
    await loadPermissionDetail(row.id)
  }

  async function loadPermissionDetail(id: number, showLoading = true) {
    if (showLoading) {
      detailLoading.value = true
    }

    try {
      const response = await fetchGetAdminPermissionDetail(id)
      activePermission.value = response.item
      permissionChildren.value = response.children
    } catch (_error) {
      ElMessage.error('菜单详情加载失败')
    } finally {
      if (showLoading) {
        detailLoading.value = false
      }
    }
  }

  async function refreshActivePermissionDetail() {
    if (!activePermission.value?.id) {
      return
    }

    await loadPermissionDetail(activePermission.value.id, false)
  }

  function toggleExpand() {
    isExpanded.value = !isExpanded.value
    nextTick(() => {
      const table = tableRef.value?.elTableRef
      if (!table) {
        return
      }

      const processRows = (items: PermissionItem[]) => {
        items.forEach((item) => {
          if (item.children?.length) {
            table.toggleRowExpansion(item, isExpanded.value)
            processRows(item.children)
          }
        })
      }

      processRows(permissionTree.value)
    })
  }

  function renderOperationButtons(row: PermissionItem) {
    const actions = [
      h(ArtButtonTable, {
        type: 'view',
        title: '详情',
        onClick: () => openDetail(row)
      })
    ]

    if (hasMenuCreateAuth.value) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:add-circle-line',
          iconClass: 'bg-primary/12 text-primary',
          title: '新增子节点',
          onClick: () => openCreateDialog(row)
        })
      )
    }

    if (canEditPermission(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:pencil-line',
          iconClass: 'bg-primary/12 text-primary',
          title: '编辑',
          onClick: () => openEditDialog(row)
        })
      )
    }

    if (canMovePermission(row, 'up')) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:arrow-up-line',
          iconClass: 'bg-info/12 text-info',
          title: '上移',
          onClick: () => handleMovePermission(row, 'up')
        })
      )
    }

    if (canMovePermission(row, 'down')) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:arrow-down-line',
          iconClass: 'bg-info/12 text-info',
          title: '下移',
          onClick: () => handleMovePermission(row, 'down')
        })
      )
    }

    if (canToggleStatusPermission(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: row.status === 1 ? 'ri:forbid-line' : 'ri:check-line',
          iconClass: row.status === 1 ? 'bg-warning/12 text-warning' : 'bg-success/12 text-success',
          title: row.status === 1 ? '停用' : '启用',
          onClick: () => handleToggleStatusPermission(row)
        })
      )
    }

    if (canDeletePermission(row)) {
      actions.push(
        h(ArtButtonTable, {
          type: 'delete',
          title: '删除',
          onClick: () => handleDeletePermission(row)
        })
      )
    }

    return h('div', { class: 'table-actions' }, actions)
  }

  function renderTitleCell(row: PermissionItem) {
    return h('div', { class: 'permission-cell' }, [
      h('div', { class: 'permission-cell__header' }, [
        h('strong', { class: 'cell-title' }, row.title || `菜单节点 #${row.id}`),
        isSystemMenuProtectedNode(row)
          ? h(ElTag, { type: 'warning', effect: 'plain', size: 'small' }, () => '受保护授权节点')
          : null
      ]),
      h(
        'p',
        { class: 'cell-sub' },
        `编号：${row.id} / 上级：${row.parent_id || '顶级节点'} / ${row.type_label}`
      )
    ])
  }

  function renderModernCell(row: PermissionItem) {
    const children = [
      h(
        'strong',
        { class: 'cell-title' },
        modernRouteSummary(row)
      ),
      h('p', { class: 'cell-sub' }, `当前菜单：${row.modern_menu_title || '--'}`),
      h('p', { class: 'cell-note' }, `访问入口：${row.modern_route_path || '--'}`)
    ]

    return h('div', { class: 'mapping-cell' }, children)
  }

  function renderMetaCell(row: PermissionItem) {
    return h('div', { class: 'mapping-cell' }, [
      h('strong', { class: 'cell-title' }, row.status_label || '--'),
      h('p', { class: 'cell-sub' }, `类型：${row.type_label || '--'}`),
      h('p', { class: 'cell-sub' }, `图标状态：${iconConfiguredSummary(row.icon)}`)
    ])
  }

  function openCreateDialog(parent?: PermissionItem) {
    writeDialogMode.value = 'create'
    writePermissionId.value = null
    Object.assign(writeForm, emptyWriteForm())
    writeForm.parent_id = parent?.id ?? 0
    writeForm.type = parent ? '1' : '0'
    writeDialogVisible.value = true
    nextTick(() => writeFormRef.value?.clearValidate())
  }

  function openEditDialog(row?: PermissionItem) {
    const target = row || activePermission.value
    if (!target) {
      return
    }

    writeDialogMode.value = 'edit'
    writePermissionId.value = target.id
    syncWriteForm(writeForm, target)
    writeDialogVisible.value = true
    nextTick(() => writeFormRef.value?.clearValidate())
  }

  async function submitPermissionWrite() {
    const form = writeFormRef.value
    if (!form) {
      return
    }

    await form.validate()

    const payload = buildWritePayload()
    savingWrite.value = true
    try {
      if (writeDialogMode.value === 'create') {
        const response = await fetchCreateAdminPermission(payload)
        writeDialogVisible.value = false
        await getPermissionTree()
        await refreshActivePermissionDetail()
        ElMessage.success(`菜单节点 ${response.created_permission_label || payload.title} 已创建`)
      } else if (writePermissionId.value) {
        const response = await fetchUpdateAdminPermission(writePermissionId.value, payload)
        writeDialogVisible.value = false
        await getPermissionTree()
        await refreshActivePermissionDetail()
        ElMessage.success(`菜单节点 ${response.updated_permission_label || payload.title} 已更新`)
      }
    } catch (_error) {
      ElMessage.error(writeDialogMode.value === 'create' ? '新增菜单节点失败' : '更新菜单节点失败')
    } finally {
      savingWrite.value = false
    }
  }

  async function handleToggleStatusPermission(row?: PermissionItem) {
    const target = row || activePermission.value
    if (!target) {
      return
    }

    if (!canToggleStatusPermission(target)) {
      ElMessage.warning('该系统菜单授权节点状态已锁定')
      return
    }

    const nextStatus = target.status === 1 ? 2 : 1
    const nextLabel = nextStatus === 1 ? '启用' : '停用'

    try {
      await ElMessageBox.confirm(
        `${nextStatus === 1 ? '启用' : '停用'} ${target.title || `菜单节点 #${target.id}`}？`,
        `${nextStatus === 1 ? '启用' : '停用'}菜单节点`,
        {
          confirmButtonText: nextStatus === 1 ? '启用' : '停用',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchUpdateAdminPermissionStatus(target.id, {
        status: nextStatus
      })
      await getPermissionTree()
      await refreshActivePermissionDetail()
      ElMessage.success(
        `菜单节点 ${response.updated_permission_label || target.title || `#${target.id}`} 已${nextLabel}`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('更新菜单节点状态失败')
    }
  }

  async function handleDeletePermission(row?: PermissionItem) {
    const target = row || activePermission.value
    if (!target) {
      return
    }

    try {
      const response = await fetchGetAdminPermissionDeleteAudit(target.id)
      const audit = response.audit
      const title = target.title || target.path || `菜单节点 #${target.id}`

      if (!audit.can_delete) {
        await ElMessageBox.alert(buildPermissionDeleteBlockedMessage(audit, title), '删除受阻', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildPermissionDeletePromptMessage(audit, title),
        audit.requires_cascade ? '级联删除菜单树' : '删除菜单节点',
        {
          confirmButtonText: audit.requires_cascade ? '删除整棵树' : '删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 后继续`
        }
      )

      const deleteResponse = await fetchDeleteAdminPermission(target.id, {
        confirmation_phrase: String(value || ''),
        cascade_children: audit.requires_cascade
      })

      if (
        activePermission.value &&
        deleteResponse.deleted_permission_ids.includes(activePermission.value.id)
      ) {
        detailVisible.value = false
        activePermission.value = null
        permissionChildren.value = []
      }

      await getPermissionTree()
      ElMessage.success(
        audit.requires_cascade
          ? `菜单树 ${deleteResponse.deleted_permission_label || title} 已级联删除`
          : `菜单节点 ${deleteResponse.deleted_permission_label || title} 已删除`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('删除菜单节点失败')
    }
  }

  async function handleMovePermission(row: PermissionItem, direction: 'up' | 'down') {
    if (!canMovePermission(row, direction)) {
      ElMessage.warning('仅在未筛选的完整同级菜单树中支持排序调整。')
      return
    }

    const siblings = siblingPermissions(row)
    const currentIndex = siblings.findIndex((item) => item.id === row.id)
    const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1
    if (currentIndex < 0 || targetIndex < 0 || targetIndex >= siblings.length) {
      return
    }

    const nextSiblings = siblings.slice()
    const current = nextSiblings[currentIndex]
    nextSiblings[currentIndex] = nextSiblings[targetIndex]
    nextSiblings[targetIndex] = current

    try {
      await fetchReorderAdminPermissions({
        parent_id: row.parent_id || 0,
        permission_ids: nextSiblings.map((item) => item.id)
      })
      await getPermissionTree()
      await refreshActivePermissionDetail()
      ElMessage.success(
        `菜单节点 ${row.title || `#${row.id}`} 已${direction === 'up' ? '上移' : '下移'}。`
      )
    } catch (_error) {
      ElMessage.error('同级菜单排序调整失败')
    }
  }

  function buildWritePayload(): Api.Permissions.PermissionWritePayload {
    const type = writeForm.type
    const path = type === '0' ? '' : normalizePath(writeForm.path)

    return {
      parent_id: writeForm.parent_id,
      title: writeForm.title.trim(),
      path,
      icon: writeForm.icon.trim(),
      sort: writeForm.sort.trim(),
      type,
      status: writeForm.status
    }
  }

  function emptyWriteForm() {
    return {
      parent_id: 0,
      title: '',
      path: '',
      icon: '',
      sort: '99',
      type: '0',
      status: '1'
    }
  }

  function syncWriteForm(form: ReturnType<typeof emptyWriteForm>, item: PermissionItem) {
    form.parent_id = item.parent_id || 0
    form.title = item.title || ''
    form.path = item.path || ''
    form.icon = item.icon || ''
    form.sort = String(item.sort ?? 99)
    form.type = String(item.type ?? 1)
    form.status = String(item.status ?? 1)
  }

  function resetWriteState() {
    writePermissionId.value = null
    Object.assign(writeForm, emptyWriteForm())
  }

  function syncActivePermissionFromTree() {
    if (!activePermission.value) {
      return
    }

    const current = findPermissionInTree(permissionTree.value, activePermission.value.id)
    if (!current) {
      detailVisible.value = false
      activePermission.value = null
      permissionChildren.value = []
      return
    }

    activePermission.value = {
      ...activePermission.value,
      ...current
    }
  }

  function findPermissionInTree(items: PermissionItem[], id: number): PermissionItem | null {
    for (const item of items) {
      if (item.id === id) {
        return item
      }
      if (item.children?.length) {
        const child = findPermissionInTree(item.children, id)
        if (child) {
          return child
        }
      }
    }

    return null
  }

  function collectNodeIds(item: PermissionItem, bucket: Set<number>) {
    bucket.add(item.id)
    item.children?.forEach((child) => collectNodeIds(child, bucket))
  }

  function buildParentTreeOptions(
    items: PermissionItem[],
    blockedIds: Set<number>
  ): ParentTreeOption[] {
    return items.map((item) => ({
      id: item.id,
      title: `${item.title || `菜单节点 #${item.id}`}${item.path ? ` / ${item.path}` : ''}`,
      disabled: blockedIds.has(item.id),
      children: item.children?.length
        ? buildParentTreeOptions(item.children, blockedIds)
        : undefined
    }))
  }

  function canEditPermission(permission?: PermissionItem | null) {
    return Boolean(permission && hasMenuEditAuth.value)
  }

  function canToggleStatusPermission(permission?: PermissionItem | null) {
    return Boolean(permission && hasMenuStatusAuth.value && !isSystemMenuProtectedNode(permission))
  }

  function canMovePermission(
    permission: PermissionItem | null | undefined,
    direction: 'up' | 'down'
  ) {
    if (!permission || !hasMenuSortAuth.value || hasActiveSearchFilters()) {
      return false
    }

    const siblings = siblingPermissions(permission)
    const index = siblings.findIndex((item) => item.id === permission.id)
    if (siblings.length < 2 || index < 0) {
      return false
    }

    return direction === 'up' ? index > 0 : index < siblings.length - 1
  }

  function canDeletePermission(permission?: PermissionItem | null) {
    return Boolean(permission && hasMenuDeleteAuth.value)
  }

  function isSystemMenuProtectedNode(permission?: PermissionItem | null) {
    return Boolean(permission && protectedSystemMenuPaths.includes(permission.path || ''))
  }

  function hasActiveSearchFilters() {
    return Boolean(searchForm.value.keyword || searchForm.value.status || searchForm.value.type)
  }

  function siblingPermissions(permission: PermissionItem) {
    if (!permission.parent_id) {
      return permissionTree.value
    }

    const parent = findPermissionInTree(permissionTree.value, permission.parent_id)

    return parent?.children || []
  }

  function normalizePath(value: string) {
    const trimmed = value.trim()
    if (!trimmed) {
      return ''
    }

    return trimmed.startsWith('/') ? trimmed : `/${trimmed}`
  }

  function buildPermissionDeleteBlockedMessage(audit: PermissionDeleteAudit, title: string) {
    return [`${title} 当前无法删除。`, ...audit.blocking_reasons, ...audit.warnings]
      .filter(Boolean)
      .join('\n')
  }

  function buildPermissionDeletePromptMessage(audit: PermissionDeleteAudit, title: string) {
    return [
      audit.requires_cascade ? `确认删除 ${title} 及其整棵子树吗？` : `确认删除 ${title} 吗？`,
      audit.path ? `页面路径：${audit.path}` : null,
      `将删除权限记录：${audit.summary.delete_permission_row_count}`,
      `将清理角色关联：${audit.summary.delete_role_permission_row_count}`,
      `将清理管理员直绑权限：${audit.summary.delete_admin_permission_row_count}`,
      ...audit.warnings,
      `请输入 ${audit.confirmation_phrase} 后继续。`
    ]
      .filter(Boolean)
      .join('\n')
  }

  function tagType(
    value?: string | null
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
  .permission-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
    --permission-note-color: #94a3b8;
    --permission-hero-border: #e2e8f0;
    --permission-hero-bg:
      linear-gradient(135deg, rgb(255 255 255 / 0.96), rgb(248 250 252 / 0.88)),
      radial-gradient(circle at top right, rgb(14 165 233 / 0.1), transparent 48%);
    --permission-drawer-border: #e2e8f0;
    --permission-drawer-bg: #f8fafc;
    --permission-migration-border: #dbeafe;
    --permission-migration-bg: linear-gradient(135deg, rgb(239 246 255 / 0.92), rgb(248 250 252 / 0.92));
  }

  :global(html.dark .permission-page ){
    --permission-note-color: #94a3b8;
    --permission-hero-border: rgb(71 85 105 / 0.52);
    --permission-hero-bg:
      linear-gradient(135deg, rgb(15 23 42 / 0.96), rgb(30 41 59 / 0.9)),
      radial-gradient(circle at top right, rgb(56 189 248 / 0.18), transparent 48%);
    --permission-drawer-border: rgb(71 85 105 / 0.42);
    --permission-drawer-bg: rgb(15 23 42 / 0.84);
    --permission-migration-border: rgb(59 130 246 / 0.28);
    --permission-migration-bg: linear-gradient(135deg, rgb(17 24 39 / 0.94), rgb(15 23 42 / 0.88));
  }

  .summary-section {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .permission-cell,
  .mapping-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .permission-cell__header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
  }

  .cell-title {
    color: var(--el-text-color-primary);
    font-size: 14px;
    font-weight: 600;
    word-break: break-all;
  }

  .cell-sub,
  .cell-note {
    margin: 0;
    font-size: 12px;
    line-height: 1.6;
    word-break: break-all;
  }

  .cell-sub {
    color: var(--el-text-color-secondary);
  }

  .cell-note {
    color: var(--permission-note-color);
  }

  .permission-detail {
    min-height: 240px;
  }

  .detail-hero {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 20px;
    margin-bottom: 24px;
    border: 1px solid var(--permission-hero-border);
    border-radius: 18px;
    background: var(--permission-hero-bg);
  }

  .detail-hero-copy {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 6px;
    min-width: 240px;
  }

  .detail-hero-copy h3 {
    margin: 0;
    color: var(--el-text-color-primary);
    font-size: 22px;
    font-weight: 700;
  }

  .detail-hero-copy p,
  .detail-hero-copy span {
    margin: 0;
    color: var(--el-text-color-secondary);
    font-size: 13px;
    line-height: 1.7;
    word-break: break-all;
  }

  .detail-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: flex-start;
    justify-content: flex-end;
  }

  .drawer-section {
    margin-bottom: 24px;
  }

  .drawer-section h4 {
    margin: 0 0 12px;
    color: var(--el-text-color-primary);
    font-size: 15px;
    font-weight: 600;
  }

  .drawer-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
  }

  .drawer-item {
    padding: 14px 16px;
    border: 1px solid var(--permission-drawer-border);
    border-radius: 14px;
    background: var(--permission-drawer-bg);
  }

  .drawer-item span {
    display: block;
    margin-bottom: 6px;
    color: var(--el-text-color-secondary);
    font-size: 12px;
  }

  .drawer-item strong {
    color: var(--el-text-color-primary);
    font-size: 14px;
    font-weight: 600;
    word-break: break-all;
  }

  .migration-panel {
    padding: 18px 20px;
    margin-bottom: 14px;
    border: 1px solid var(--permission-migration-border);
    border-radius: 16px;
    background: var(--permission-migration-bg);
  }

  .migration-panel__header {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    margin-bottom: 10px;
  }

  .migration-panel__route {
    color: var(--el-text-color-primary);
    font-size: 14px;
    font-weight: 600;
    word-break: break-all;
  }

  .migration-panel__note {
    margin: 0;
    color: var(--el-text-color-secondary);
    font-size: 13px;
    line-height: 1.7;
  }

  .child-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .empty-copy {
    color: var(--el-text-color-secondary);
    font-size: 13px;
  }

  .write-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0 16px;
  }

  .write-form__wide {
    grid-column: 1 / -1;
  }

  .write-form__tree {
    width: 100%;
  }

  .write-note {
    margin-top: 12px;
    color: var(--el-color-warning);
    font-size: 12px;
    line-height: 1.6;
  }

  .dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
  }

  @media (max-width: 768px) {
    .detail-hero {
      padding: 16px;
      border-radius: 16px;
    }

    .detail-hero-actions {
      justify-content: flex-start;
    }

    .drawer-grid,
    .write-form-grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
</style>
