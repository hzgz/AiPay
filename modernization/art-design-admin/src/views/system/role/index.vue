<template>
  <div class="role-page art-full-height">
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
            <ElTag effect="plain">角色 {{ pagination.total }}</ElTag>
            <ElButton v-if="hasRoleCreateAuth" type="primary" @click="openCreateDialog">
              新增角色
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
      size="820px"
      destroy-on-close
      :title="activeRole ? `${activeRole.name} / ${activeRole.code}` : '角色详情'"
    >
      <div v-loading="detailLoading" class="role-detail">
        <template v-if="activeRole">
          <div class="detail-hero">
            <div class="detail-hero-copy">
              <h3>{{ activeRole.name }}</h3>
              <p>{{ activeRole.scope_label }} / {{ activeRole.code }}</p>
              <span>
                权限 {{ activeRole.permission_count }} / {{ activeRole.total_permission_count }}
              </span>
            </div>
            <div class="detail-hero-actions">
              <ElButton v-if="canEditRole(activeRole)" plain @click="openEditDialog()">
                编辑
              </ElButton>
              <ElButton
                v-if="canAssignPermissions(activeRole)"
                plain
                @click="openPermissionDialog()"
              >
                权限
              </ElButton>
              <ElButton
                v-if="canDeleteRole(activeRole)"
                type="danger"
                plain
                @click="handleDeleteRole()"
              >
                删除
              </ElButton>
            </div>
          </div>

          <div class="drawer-section">
            <div class="drawer-grid">
              <div class="drawer-item">
                <span>角色名称</span>
                <strong>{{ activeRole.name }}</strong>
              </div>
              <div class="drawer-item">
                <span>角色标识</span>
                <strong>{{ activeRole.code }}</strong>
              </div>
              <div class="drawer-item">
                <span>角色范围</span>
                <strong>{{ activeRole.scope_label }}</strong>
              </div>
              <div class="drawer-item">
                <span>已绑定管理员</span>
                <strong>{{ activeRole.assigned_admin_count }}</strong>
              </div>
              <div class="drawer-item">
                <span>状态</span>
                <strong>{{ activeRole.status_label }}</strong>
              </div>
              <div class="drawer-item">
                <span>创建时间</span>
                <strong>{{ activeRole.create_time || '--' }}</strong>
              </div>
              <div class="drawer-item">
                <span>更新时间</span>
                <strong>{{ activeRole.update_time || '--' }}</strong>
              </div>
              <div class="drawer-item">
                <span>权限覆盖</span>
                <strong
                  >{{ activeRole.permission_count }} /
                  {{ activeRole.total_permission_count }}</strong
                >
              </div>
            </div>
          </div>

          <div v-if="activeRole.description" class="drawer-section">
            <h4>角色说明</h4>
            <ElInput :model-value="activeRole.description" type="textarea" :rows="3" readonly />
          </div>

          <div class="drawer-section">
            <h4>已绑定管理员</h4>
            <div v-if="activeRole.admins.length" class="admin-tags">
              <ElTag v-for="admin in activeRole.admins" :key="admin.id" effect="plain">
                {{ admin.display }}
              </ElTag>
            </div>
            <p v-else class="empty-copy">暂未绑定管理员账号</p>
          </div>

          <div class="drawer-section">
            <h4>权限树</h4>
            <ElScrollbar height="360px" class="permission-scroll">
              <ElTree
                :data="permissionTree"
                node-key="id"
                default-expand-all
                :expand-on-click-node="false"
                :props="{ children: 'children', label: 'title' }"
              >
                <template #default="{ data: treeItem }">
                  <div class="permission-node">
                    <div class="permission-meta">
                      <strong>{{ treeItem.title }}</strong>
                      <p>{{ treeItem.path || '--' }}</p>
                    </div>
                    <ElTag
                      size="small"
                      :type="treeItem.checked ? 'success' : 'info'"
                      effect="light"
                    >
                      {{ treeItem.checked ? '已授权' : '未授权' }}
                    </ElTag>
                  </div>
                </template>
              </ElTree>
            </ElScrollbar>
          </div>

          <p v-if="activeRole.grants_all_permissions" class="detail-note">
            超级角色默认继承全部权限，权限树仅供查看。
          </p>
        </template>
      </div>
    </ElDrawer>

    <ElDialog
      v-model="writeDialogVisible"
      width="720px"
      destroy-on-close
      align-center
      :title="writeDialogTitle"
      @closed="resetWriteState"
    >
      <ElForm ref="writeFormRef" :model="writeForm" :rules="writeRules" label-position="top">
        <ElFormItem label="角色名称" prop="name">
          <ElInput v-model="writeForm.name" maxlength="30" placeholder="输入角色名称" />
        </ElFormItem>
        <ElFormItem label="说明" prop="description">
          <ElInput
            v-model="writeForm.description"
            type="textarea"
            :rows="4"
            maxlength="100"
            placeholder="输入备注"
          />
        </ElFormItem>
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="writeDialogVisible = false">取消</ElButton>
          <ElButton type="primary" :loading="savingRole" @click="submitRoleWrite">
            {{ writeDialogMode === 'create' ? '新增角色' : '保存修改' }}
          </ElButton>
        </div>
      </template>
    </ElDialog>

    <ElDialog
      v-model="permissionVisible"
      width="920px"
      destroy-on-close
      align-center
      :title="activeRole ? `分配权限：${activeRole.name}` : '分配权限'"
      @closed="resetPermissionState"
    >
      <div class="permission-editor">
        <p v-if="activeRole?.grants_all_permissions" class="dialog-hint warning">
          超级角色默认继承全部权限。
        </p>
        <ElScrollbar height="420px" class="permission-scroll permission-edit-scroll">
          <ElTree
            ref="permissionEditorTreeRef"
            :data="permissionTree"
            node-key="id"
            show-checkbox
            default-expand-all
            :expand-on-click-node="false"
            :props="{ children: 'children', label: 'title' }"
            @check="handlePermissionTreeCheck"
          >
            <template #default="{ data: treeItem }">
              <div class="permission-node">
                <div class="permission-meta">
                  <strong>{{ treeItem.title }}</strong>
                  <p>{{ treeItem.path || '--' }}</p>
                </div>
                <ElTag
                  size="small"
                  :type="isRolePermissionSelected(treeItem.id) ? 'success' : 'info'"
                  effect="plain"
                >
                  {{ isRolePermissionSelected(treeItem.id) ? '已授权' : '可选' }}
                </ElTag>
              </div>
            </template>
          </ElTree>
        </ElScrollbar>
      </div>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="permissionVisible = false">取消</ElButton>
          <ElButton type="primary" :loading="savingPermissions" @click="submitRolePermissions">
            保存权限
          </ElButton>
        </div>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox, ElTag, type FormInstance, type FormRules } from 'element-plus'
  import { useAuth } from '@/hooks'
  import { useTable } from '@/hooks/core/useTable'
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import {
    fetchCreateAdminRole,
    fetchDeleteAdminRole,
    fetchGetAdminRoleDeleteAudit,
    fetchGetAdminRoleDetail,
    fetchGetAdminRoleList,
    fetchUpdateAdminRole,
    fetchUpdateAdminRolePermissions
  } from '@/api/roles'

  defineOptions({ name: 'SystemRole' })

  type RoleListItem = Api.Roles.RoleListItem
  type RoleDeleteAudit = Api.Roles.RoleDeleteAudit
  type PermissionTreeItem = Api.Roles.PermissionTreeItem

  const { hasAuth } = useAuth()
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const writeDialogVisible = ref(false)
  const permissionVisible = ref(false)
  const writeDialogMode = ref<'create' | 'edit'>('create')
  const savingRole = ref(false)
  const savingPermissions = ref(false)
  const activeRole = ref<RoleListItem | null>(null)
  const permissionTree = ref<PermissionTreeItem[]>([])
  const writeRoleId = ref<number | null>(null)
  const writeFormRef = ref<FormInstance>()
  const permissionEditorTreeRef = ref<any>()
  const searchForm = ref<{
    keyword?: string
    date_range?: string[]
  }>({})
  const writeForm = reactive(emptyWriteForm())
  const permissionForm = reactive(emptyPermissionForm())
  const hasRoleCreateAuth = computed(() => hasAuth('add'))
  const hasRoleEditAuth = computed(() => hasAuth('edit'))
  const hasRolePermissionAuth = computed(() => hasAuth('permission'))
  const hasRoleDeleteAuth = computed(() => hasAuth('remove'))
  const writeDialogTitle = computed(() =>
    writeDialogMode.value === 'create' ? '新增角色' : '编辑角色'
  )

  const writeRules = reactive<FormRules>({
    name: [
      { required: true, message: '请输入角色名称。', trigger: 'blur' },
      {
        validator: (_rule, value, callback) => {
          const length = String(value || '').trim().length
          if (length === 0) {
            callback(new Error('请输入角色名称。'))
            return
          }
          if (length > 30) {
            callback(new Error('角色名称不能超过 30 个字符。'))
            return
          }
          callback()
        },
        trigger: 'blur'
      }
    ],
    description: [
      {
        validator: (_rule, value, callback) => {
          if (String(value || '').trim().length > 100) {
            callback(new Error('角色说明不能超过 100 个字符。'))
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
        placeholder: '搜索角色 ID、名称或标识'
      }
    },
    {
      label: '时间范围',
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
    refreshData,
    refreshCreate,
    refreshUpdate,
    refreshRemove
  } = useTable({
    core: {
      apiFn: fetchGetAdminRoleList,
      apiParams: {
        current: 1,
        size: 20
      },
      columnsFactory: () => [
        { type: 'globalIndex', width: 70, label: '序号' },
        {
          prop: 'name',
          label: '角色',
          minWidth: 260,
          formatter: (row) =>
            h('div', { class: 'role-cell' }, [
              h('strong', { class: 'cell-title' }, row.name),
              h('p', { class: 'cell-sub' }, `ID：${row.id} / 标识：${row.code}`)
            ])
        },
        {
          prop: 'scope_label',
          label: '范围',
          minWidth: 150
        },
        {
          prop: 'permission_count',
          label: '权限覆盖',
          minWidth: 160,
          formatter: (row) => `${row.permission_count} / ${row.total_permission_count}`
        },
        {
          prop: 'assigned_admin_count',
          label: '管理员',
          width: 110,
          align: 'center',
          formatter: (row) => row.assigned_admin_count
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
          prop: 'update_time',
          label: '更新时间',
          minWidth: 170,
          formatter: (row) => row.update_time || '--'
        },
        {
          prop: 'operation',
          label: '操作',
          width: 340,
          align: 'center',
          fixed: 'right',
          formatter: (row) => renderRoleOperationButtons(row)
        }
      ]
    }
  })

  function renderRoleOperationButtons(row: RoleListItem) {
    const actions = [
      h(ArtButtonTable, {
        type: 'view',
        title: '详情',
        onClick: () => openDetail(row)
      })
    ]

    if (canEditRole(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:pencil-line',
          iconClass: 'bg-primary/12 text-primary',
          title: '编辑',
          onClick: () => openEditDialog(row)
        })
      )
    }

    if (canAssignPermissions(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:key-2-line',
          iconClass: 'bg-amber-500/12 text-amber-500',
          title: '权限',
          onClick: () => openPermissionDialog(row)
        })
      )
    }

    if (canDeleteRole(row)) {
      actions.push(
        h(ArtButtonTable, {
          type: 'delete',
          title: '删除',
          onClick: () => handleDeleteRole(row)
        })
      )
    }

    return h('div', { class: 'table-actions' }, actions)
  }

  function handleSearch(params: Record<string, unknown>) {
    const dateRange = Array.isArray(params.date_range) ? (params.date_range as string[]) : []
    replaceSearchParams({
      keyword: params.keyword as string | undefined,
      start_date: dateRange[0] || undefined,
      end_date: dateRange[1] || undefined
    })
    getData()
  }

  function handleReset() {
    searchForm.value = {}
    resetSearchParams()
  }

  async function loadRoleDetail(id: number, openDrawer = true) {
    if (openDrawer) {
      detailVisible.value = true
    }

    detailLoading.value = true

    try {
      const response = await fetchGetAdminRoleDetail(id)
      activeRole.value = response.item
      permissionTree.value = response.permission_tree
      return response
    } catch (_error) {
      ElMessage.error('加载角色详情失败。')
      throw _error
    } finally {
      detailLoading.value = false
    }
  }

  async function openDetail(row: RoleListItem) {
    activeRole.value = row
    permissionTree.value = []
    await loadRoleDetail(row.id, true)
  }

  function openCreateDialog() {
    if (!hasRoleCreateAuth.value) {
      ElMessage.warning('您没有新增角色的权限。')
      return
    }

    writeDialogMode.value = 'create'
    writeRoleId.value = null
    Object.assign(writeForm, emptyWriteForm())
    writeDialogVisible.value = true
    nextTick(() => writeFormRef.value?.clearValidate())
  }

  function openEditDialog(row?: RoleListItem | null) {
    const target = row || activeRole.value
    if (!target) {
      return
    }

    if (!hasRoleEditAuth.value) {
      ElMessage.warning('您没有编辑角色的权限。')
      return
    }

    writeDialogMode.value = 'edit'
    writeRoleId.value = target.id
    Object.assign(writeForm, {
      name: target.name,
      description: target.description || ''
    })
    writeDialogVisible.value = true
    nextTick(() => writeFormRef.value?.clearValidate())
  }

  async function openPermissionDialog(row?: RoleListItem | null) {
    const target = row || activeRole.value
    if (!target) {
      return
    }

    if (!hasRolePermissionAuth.value) {
      ElMessage.warning('您没有分配角色权限的权限。')
      return
    }

    if (target.grants_all_permissions) {
      ElMessage.warning('内置超级角色会自动继承全部权限，不能在这里编辑。')
      return
    }

    try {
      const response =
        activeRole.value?.id === target.id && permissionTree.value.length
          ? {
              item: target,
              permission_tree: permissionTree.value,
              assigned_permission_ids: collectCheckedPermissionIdsFromTree(permissionTree.value)
            }
          : await loadRoleDetail(target.id, false)

      activeRole.value = response.item
      permissionTree.value = response.permission_tree
      permissionForm.permission_ids = [...response.assigned_permission_ids]
      permissionVisible.value = true

      await nextTick()
      syncPermissionTreeCheckedKeys()
    } catch (_error) {
      ElMessage.error('加载角色权限树失败。')
    }
  }

  function resetWriteState() {
    writeDialogMode.value = 'create'
    writeRoleId.value = null
    Object.assign(writeForm, emptyWriteForm())
  }

  function resetPermissionState() {
    Object.assign(permissionForm, emptyPermissionForm())
    permissionEditorTreeRef.value = undefined
  }

  async function submitRoleWrite() {
    if (!writeFormRef.value) {
      return
    }

    try {
      await writeFormRef.value.validate()
    } catch {
      return
    }

    const payload = buildWritePayload()
    if (!payload.name) {
      ElMessage.warning('请输入角色名称。')
      return
    }

    savingRole.value = true
    try {
      if (writeDialogMode.value === 'create') {
        const response = await fetchCreateAdminRole(payload)
        writeDialogVisible.value = false
        await refreshCreate()
        ElMessage.success(
          `角色 ${response.created_role_label || `#${response.created_role_id}`} 已创建。`
        )
        return
      }

      if (!writeRoleId.value) {
        ElMessage.warning('当前没有可编辑的角色。')
        return
      }

      const response = await fetchUpdateAdminRole(writeRoleId.value, payload)
      syncActiveRole(response.item)
      writeDialogVisible.value = false
      await refreshUpdate()
      if (detailVisible.value && activeRole.value?.id === response.updated_role_id) {
        await loadRoleDetail(response.updated_role_id, true)
      }
      ElMessage.success(
        `角色 ${response.updated_role_label || `#${response.updated_role_id}`} 已更新。`
      )
    } catch (_error) {
      ElMessage.error(writeDialogMode.value === 'create' ? '创建角色失败。' : '更新角色失败。')
    } finally {
      savingRole.value = false
    }
  }

  async function submitRolePermissions() {
    if (!activeRole.value) {
      return
    }

    permissionForm.permission_ids = collectPermissionTreeCheckedKeys()
    savingPermissions.value = true

    try {
      const response = await fetchUpdateAdminRolePermissions(activeRole.value.id, {
        permission_ids: permissionForm.permission_ids
      })

      activeRole.value = response.item
      permissionTree.value = response.permission_tree
      permissionForm.permission_ids = [...response.assigned_permission_ids]
      permissionVisible.value = false
      resetPermissionState()
      await refreshUpdate()
      if (detailVisible.value && activeRole.value?.id === response.updated_role_id) {
        await loadRoleDetail(response.updated_role_id, true)
      }
      ElMessage.success('角色权限已更新。')
    } catch (_error) {
      ElMessage.error('更新角色权限失败。')
    } finally {
      savingPermissions.value = false
    }
  }

  async function handleDeleteRole(row?: RoleListItem | null) {
    const target = row || activeRole.value
    if (!target) {
      return
    }

    if (!hasRoleDeleteAuth.value) {
      ElMessage.warning('您没有删除角色的权限。')
      return
    }

    try {
      const response = await fetchGetAdminRoleDeleteAudit(target.id)
      const audit = response.audit
      const title = target.name || `角色 #${target.id}`

      if (!audit.can_delete) {
        await ElMessageBox.alert(buildRoleDeleteBlockedMessage(audit, title), '删除受限', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildRoleDeletePromptMessage(audit, title),
        '删除角色',
        {
          confirmButtonText: '删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 后继续。`
        }
      )

      const deleteResponse = await fetchDeleteAdminRole(target.id, {
        confirmation_phrase: String(value || '')
      })

      if (activeRole.value?.id === target.id) {
        detailVisible.value = false
        activeRole.value = null
        permissionTree.value = []
      }
      if (writeRoleId.value === target.id) {
        writeDialogVisible.value = false
      }
      if (permissionVisible.value) {
        permissionVisible.value = false
        resetPermissionState()
      }

      await refreshRemove()
      ElMessage.success(`角色 ${deleteResponse.deleted_role_label || title} 已删除。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('删除角色失败。')
    }
  }

  function canEditRole(item?: RoleListItem | null) {
    return Boolean(item && hasRoleEditAuth.value)
  }

  function canAssignPermissions(item?: RoleListItem | null) {
    return Boolean(item && hasRolePermissionAuth.value && !item.grants_all_permissions)
  }

  function canDeleteRole(item?: RoleListItem | null) {
    return Boolean(item && hasRoleDeleteAuth.value)
  }

  function syncActiveRole(item: RoleListItem | null) {
    if (!item) {
      return
    }

    if (activeRole.value?.id === item.id) {
      activeRole.value = item
    }
  }

  function buildWritePayload(): Api.Roles.RoleWritePayload {
    const name = normalizeInput(writeForm.name)
    const description = normalizeInput(writeForm.description)

    return {
      name,
      description: description === '' ? null : description
    }
  }

  function buildRoleDeleteBlockedMessage(audit: RoleDeleteAudit, title: string) {
    const assignedAdmins = audit.assigned_admins.slice(0, 6).map((item) => `- ${item.display}`)

    return [
      `${title} 当前暂不可删除。`,
      '',
      ...audit.blocking_reasons.map((item) => `- ${item}`),
      ...(assignedAdmins.length ? ['', '已绑定管理员：', ...assignedAdmins] : []),
      '',
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function buildRoleDeletePromptMessage(audit: RoleDeleteAudit, title: string) {
    return [
      `${title} 将被永久删除。`,
      '',
      `本次会移除 ${audit.summary.delete_role_permission_row_count} 条角色权限关联记录，以及 ${audit.summary.delete_admin_role_row_count} 条管理员角色关联记录。`,
      `请输入 ${audit.confirmation_phrase} 以确认删除。`,
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function emptyWriteForm() {
    return {
      name: '',
      description: ''
    }
  }

  function emptyPermissionForm() {
    return {
      permission_ids: [] as number[]
    }
  }

  function collectCheckedPermissionIdsFromTree(items: PermissionTreeItem[]) {
    const permissionIds: number[] = []

    const walk = (nodes: PermissionTreeItem[]) => {
      for (const node of nodes) {
        if (node.checked) {
          permissionIds.push(Number(node.id))
        }
        if (node.children?.length) {
          walk(node.children)
        }
      }
    }

    walk(items)

    return Array.from(
      new Set(permissionIds.filter((value) => Number.isInteger(value) && value > 0))
    )
  }

  function handlePermissionTreeCheck() {
    permissionForm.permission_ids = collectPermissionTreeCheckedKeys()
  }

  function collectPermissionTreeCheckedKeys() {
    const checkedKeys = permissionEditorTreeRef.value?.getCheckedKeys?.() ?? []
    return checkedKeys
      .map((value: unknown) => Number(value))
      .filter((value: number) => Number.isInteger(value) && value > 0)
  }

  function syncPermissionTreeCheckedKeys() {
    permissionEditorTreeRef.value?.setCheckedKeys?.(permissionForm.permission_ids)
  }

  function isRolePermissionSelected(permissionId: number) {
    return permissionForm.permission_ids.includes(Number(permissionId))
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
  .role-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .role-cell {
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

  .role-detail {
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
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 14px;
    background: rgb(248 250 252 / 0.82);
  }

  .drawer-item span {
    color: #64748b;
    font-size: 12px;
  }

  .drawer-item strong {
    color: #0f172a;
    word-break: break-all;
  }

  .empty-copy,
  .detail-note,
  .dialog-hint {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.7;
  }

  .dialog-hint.warning {
    color: #b45309;
  }

  .admin-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .permission-editor {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .permission-scroll {
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 14px;
    padding: 12px;
    background: rgb(248 250 252 / 0.55);
  }

  .permission-scroll :deep(.el-tree-node__content) {
    height: auto;
    min-height: 44px;
    align-items: flex-start;
    padding: 6px 0;
  }

  .permission-scroll :deep(.el-tree-node__content > .permission-node) {
    flex: 1;
    min-width: 0;
  }

  .permission-scroll :deep(.el-tree-node__expand-icon),
  .permission-scroll :deep(.el-checkbox) {
    margin-top: 6px;
  }

  .permission-node {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
    padding: 4px 0;
  }

  .permission-meta {
    display: flex;
    flex-direction: column;
    flex: 1;
    gap: 2px;
    min-width: 0;
  }

  .permission-meta strong {
    color: #0f172a;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.5;
    word-break: break-word;
  }

  .permission-meta p {
    margin: 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.5;
    word-break: break-all;
  }

  .permission-node :deep(.el-tag) {
    flex-shrink: 0;
    margin-top: 2px;
  }

  .dialog-footer {
    display: flex;
    justify-content: flex-end;
  }

  @media (width <= 991px) {
    .detail-hero,
    .drawer-grid {
      grid-template-columns: 1fr;
      flex-direction: column;
    }

    .detail-hero-actions,
    .permission-node {
      justify-content: flex-start;
      align-items: flex-start;
      flex-direction: column;
    }
  }
</style>
