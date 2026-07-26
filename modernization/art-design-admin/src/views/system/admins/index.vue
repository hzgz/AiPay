<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <div class="admin-account-page art-full-height">
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
        @refresh="refreshData"
      >
        <template #left>
          <ElSpace wrap>
            <ElTag effect="plain">管理员 {{ pagination.total }}</ElTag>
            <ElTag type="success" effect="plain">启用 {{ pageEnabledCount }}</ElTag>
            <ElTag type="warning" effect="plain">在线令牌 {{ pageTokenCount }}</ElTag>
            <ElTag v-if="pageRootCount > 0" type="danger" effect="plain">
              超级账号 {{ pageRootCount }}
            </ElTag>
            <ElButton v-if="hasAdminCreateAuth" type="primary" @click="openCreateDialog">
              新增管理员
            </ElButton>
            <ElButton
              v-if="hasAdminRecycleAuth"
              plain
              :type="isRecycleView ? 'primary' : 'info'"
              @click="toggleRecycleView"
            >
              {{ isRecycleView ? '返回正常列表' : '回收站' }}
            </ElButton>
            <ElButton
              v-if="hasAdminRecycleAuth && isRecycleView"
              plain
              type="success"
              :disabled="selectedAdmins.length === 0"
              @click="handleBatchRestoreAdmins"
            >
              批量恢复
            </ElButton>
            <ElButton
              v-if="hasAdminBatchDeleteAuth && !isRecycleView"
              plain
              type="danger"
              :disabled="selectedAdmins.length === 0"
              @click="handleBatchDeleteAdmins"
            >
              批量回收
            </ElButton>
            <ElTag v-if="selectedAdmins.length > 0" type="danger" effect="plain">
              已选 {{ selectedAdmins.length }}
            </ElTag>
            <ElTag v-if="isRecycleView" type="info" effect="plain">回收站视图</ElTag>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        ref="tableRef"
        :loading="loading"
        :data="data"
        :columns="columns"
        :pagination="pagination"
        row-key="id"
        reserve-selection
        @selection-change="handleAdminSelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <AdminDetailDrawer
      :visible="detailVisible"
      :detail-loading="detailLoading"
      :active-admin="activeAdmin"
      :detail-editable="detailEditable"
      :permission-tree="permissionTree"
      :direct-permission-tree="directPermissionTree"
      :can-edit-active-admin="canEditActiveAdmin"
      :can-assign-roles-active-admin="canAssignRolesActiveAdmin"
      :can-assign-direct-permissions-active-admin="canAssignDirectPermissionsActiveAdmin"
      :can-toggle-status-active-admin="canToggleStatusActiveAdmin"
      :can-delete-active-admin="canDeleteActiveAdmin"
      :can-restore-active-admin="canRestoreActiveAdmin"
      @update:visible="detailVisible = $event"
      @edit="openEditDialog()"
      @role="openRoleDialog()"
      @permission="openPermissionDialog()"
      @status="handleToggleStatus()"
      @delete="handleDeleteAdmin()"
      @restore="handleRestoreAdmin()"
    />

    <AdminWriteDialogs
      ref="writeDialogsRef"
      :create-visible="createVisible"
      :edit-visible="editVisible"
      :creating-admin="creatingAdmin"
      :saving-admin="savingAdmin"
      :has-admin-role-auth="hasAdminRoleAuth"
      :active-admin="activeAdmin"
      :available-roles="availableRoles"
      :create-form="createForm"
      :edit-form="editForm"
      @update:create-visible="createVisible = $event"
      @update:edit-visible="editVisible = $event"
      @update:create-form="syncCreateFormState"
      @update:edit-form="syncEditFormState"
      @submit:create="submitCreateAdmin"
      @submit:edit="submitEditAdmin"
      @closed:create="resetCreateState"
      @closed:edit="resetEditState"
    />

    <AdminAccessDialogs
      ref="accessDialogsRef"
      :role-visible="roleVisible"
      :permission-visible="permissionVisible"
      :saving-roles="savingRoles"
      :saving-permissions="savingPermissions"
      :active-admin="activeAdmin"
      :available-roles="availableRoles"
      :direct-permission-tree="directPermissionTree"
      :role-form="roleForm"
      :permission-form="permissionForm"
      @update:role-visible="roleVisible = $event"
      @update:permission-visible="permissionVisible = $event"
      @update:role-form="syncRoleFormState"
      @update:permission-form="syncPermissionFormState"
      @submit:role="submitAdminRoles"
      @submit:permission="submitAdminPermissions"
      @closed:role="resetRoleState"
      @closed:permission="resetPermissionState"
    />
  </div>
</template>

<script setup lang="ts">
  import { storeToRefs } from 'pinia'
  import { ElMessage, ElMessageBox, ElTag } from 'element-plus'
  import ArtButtonTable from '@/components/core/forms/artButtonTable/index.vue'
  import { useAuth } from '@/hooks'
  import { useTable } from '@/hooks/core/useTable'
  import { useUserStore } from '@/store/modules/user'
  import AdminAccessDialogs from './modules/AdminAccessDialogs.vue'
  import AdminDetailDrawer from './modules/AdminDetailDrawer.vue'
  import {
    assignAdminCreateFormState,
    assignAdminEditFormState,
    assignAdminPermissionFormState,
    assignAdminRoleFormState,
    buildAdminCreatePayload,
    buildAdminEditPayload,
    buildAdminPermissionPayload,
    buildAdminRolePayload,
    createAdminCreateFormState,
    createAdminEditFormState,
    createAdminPermissionFormState,
    createAdminRoleFormState,
    syncAdminCreateFormFromEditable,
    syncAdminEditFormFromItem,
    syncAdminPermissionFormFromEditable,
    syncAdminRoleFormFromEditable
  } from './modules/adminFormState'
  import type {
    AdminCreateFormState,
    AdminEditFormState,
    AdminPermissionFormState,
    AdminRoleFormState
  } from './modules/adminFormState'
  import AdminWriteDialogs from './modules/AdminWriteDialogs.vue'
  import {
    fetchAuditAdminAccountBatchDelete,
    fetchBatchRestoreAdminAccounts,
    fetchBatchDeleteAdminAccounts,
    fetchCreateAdminAccount,
    fetchDeleteAdminAccount,
    fetchGetAdminAccountDeleteAudit,
    fetchGetAdminAccountDetail,
    fetchGetAdminAccountList,
    fetchGetAdminAccountTemplate,
    fetchRestoreAdminAccount,
    fetchUpdateAdminAccount,
    fetchUpdateAdminAccountPermissions,
    fetchUpdateAdminAccountRoles,
    fetchUpdateAdminAccountStatus
  } from '@/api/admins'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'

  defineOptions({ name: 'SystemAdmins' })

  type AdminAccountItem = Api.AdminAccounts.AdminAccountListItem
  type PermissionTreeItem = Api.Roles.PermissionTreeItem
  type AdminEditable = Api.AdminAccounts.AdminAccountEditable
  type AdminEditableRoleItem = Api.AdminAccounts.AdminAccountEditableRoleItem

  const { hasAuth } = useAuth()
  const userStore = useUserStore()
  const { info } = storeToRefs(userStore)

  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const createVisible = ref(false)
  const editVisible = ref(false)
  const roleVisible = ref(false)
  const permissionVisible = ref(false)
  const creatingAdmin = ref(false)
  const savingAdmin = ref(false)
  const savingRoles = ref(false)
  const savingPermissions = ref(false)
  const activeAdmin = ref<AdminAccountItem | null>(null)
  const detailEditable = ref<AdminEditable | null>(null)
  const permissionTree = ref<PermissionTreeItem[]>([])
  const directPermissionTree = ref<PermissionTreeItem[]>([])
  const availableRoles = ref<AdminEditableRoleItem[]>([])
  const tableRef = ref<any>()
  const writeDialogsRef = ref<InstanceType<typeof AdminWriteDialogs> | null>(null)
  const accessDialogsRef = ref<InstanceType<typeof AdminAccessDialogs> | null>(null)
  const selectedAdmins = ref<AdminAccountItem[]>([])
  const searchForm = ref<{
    keyword?: string
    status?: string
  }>({})
  const createForm = reactive(createAdminCreateFormState())
  const editForm = reactive(createAdminEditFormState())
  const roleForm = reactive(createAdminRoleFormState())
  const permissionForm = reactive(createAdminPermissionFormState())
  const currentAdminId = computed(() => Number(info.value?.userId || 0))
  const hasAdminCreateAuth = computed(() => hasAuth('add'))
  const hasAdminEditAuth = computed(() => hasAuth('edit'))
  const hasAdminStatusAuth = computed(() => hasAuth('status'))
  const hasAdminRoleAuth = computed(() => hasAuth('role'))
  const hasAdminPermissionAuth = computed(() => hasAuth('permission'))
  const hasAdminDeleteAuth = computed(() => hasAuth('remove'))
  const hasAdminBatchDeleteAuth = computed(() => hasAuth('batchRemove'))
  const hasAdminRecycleAuth = computed(() => hasAuth('recycle'))
  const isRecycleView = computed(() => searchForm.value.status === '-1')
  const canEditActiveAdmin = computed(() =>
    Boolean(activeAdmin.value && canEditAdmin(activeAdmin.value))
  )
  const canAssignRolesActiveAdmin = computed(() =>
    Boolean(activeAdmin.value && canAssignRoles(activeAdmin.value))
  )
  const canAssignDirectPermissionsActiveAdmin = computed(() =>
    Boolean(activeAdmin.value && canAssignDirectPermissions(activeAdmin.value))
  )
  const canToggleStatusActiveAdmin = computed(() =>
    Boolean(activeAdmin.value && canToggleStatus(activeAdmin.value))
  )
  const canDeleteActiveAdmin = computed(() =>
    Boolean(activeAdmin.value && canDeleteAdmin(activeAdmin.value))
  )
  const canRestoreActiveAdmin = computed(() =>
    Boolean(activeAdmin.value && canRestoreAdmin(activeAdmin.value))
  )

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '搜索管理员编号、用户名或昵称'
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
          { label: '停用', value: '0' },
          { label: '已回收', value: '-1' }
        ]
      }
    }
  ])

  const pageEnabledCount = computed(() => data.value.filter((item) => item.status === 1).length)
  const pageRootCount = computed(() => data.value.filter((item) => item.is_root).length)
  const pageTokenCount = computed(() => data.value.filter((item) => item.token_active).length)

  function displayAdminStatus(admin?: AdminAccountItem | null) {
    return displayAdminFixtureText(admin?.status_text || admin?.status_label, '未知状态')
  }

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
      apiFn: fetchGetAdminAccountList,
      apiParams: {
        current: 1,
        size: 20
      },
      columnsFactory: () => [
        {
          type: 'selection',
          width: 54,
          fixed: 'left' as const,
          visible: hasAdminBatchDeleteAuth.value || hasAdminRecycleAuth.value
        },
        { type: 'globalIndex', width: 70, label: '序号' },
        {
          prop: 'username',
          label: '管理员账号',
          minWidth: 270,
          formatter: (row) =>
            h('div', { class: 'admin-cell' }, [
              h('strong', { class: 'cell-title' }, row.display || row.username),
              h('p', { class: 'cell-sub' }, `编号：${row.id} / ${row.scope_label}`)
            ])
        },
        {
          prop: 'role_count',
          label: '角色',
          minWidth: 220,
          formatter: (row) =>
            h(
              'div',
              { class: 'role-cell' },
              row.roles.length
                ? row.roles.map((role) =>
                    h(
                      ElTag,
                      {
                        type: role.code === 'super_admin' ? 'danger' : 'primary',
                        effect: 'plain',
                        size: 'small'
                      },
                      () => role.name
                    )
                  )
                : [h(ElTag, { type: 'info', effect: 'plain', size: 'small' }, () => '无角色')]
            )
        },
        {
          prop: 'effective_permission_count',
          label: '权限覆盖',
          minWidth: 150,
          formatter: (row) => row.permission_coverage_label
        },
        {
          prop: 'direct_permission_count',
          label: '直属',
          width: 100,
          align: 'center'
        },
        {
          prop: 'status_label',
          label: '状态',
          width: 100,
          align: 'center',
          formatter: (row) =>
            h(ElTag, { type: tagType(row.status_type), effect: 'light' }, () =>
              displayAdminStatus(row)
            )
        },
        {
          prop: 'update_time',
          label: '更新时间',
          minWidth: 170,
          formatter: (row) => row.update_time || row.create_time || '--'
        },
        {
          prop: 'operation',
          label: '操作',
          width: 390,
          align: 'center',
          fixed: 'right',
          formatter: (row) => renderOperationButtons(row)
        }
      ]
    }
  })

  function renderOperationButtons(row: AdminAccountItem) {
    const actions = [
      h(ArtButtonTable, {
        type: 'view',
        title: '详情',
        onClick: () => openDetail(row)
      })
    ]

    if (canEditAdmin(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:pencil-line',
          iconClass: 'bg-primary/12 text-primary',
          title: '编辑',
          onClick: () => openEditDialog(row)
        })
      )
    }

    if (canAssignRoles(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:shield-user-line',
          iconClass: 'bg-warning/12 text-warning',
          title: '角色',
          onClick: () => openRoleDialog(row)
        })
      )
    }

    if (canAssignDirectPermissions(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:key-2-line',
          iconClass: 'bg-amber-500/12 text-amber-500',
          title: '权限',
          onClick: () => openPermissionDialog(row)
        })
      )
    }

    if (canToggleStatus(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: row.status === 1 ? 'ri:pause-circle-line' : 'ri:play-circle-line',
          iconClass: row.status === 1 ? 'bg-warning/12 text-warning' : 'bg-success/12 text-success',
          title: row.status === 1 ? '停用' : '启用',
          onClick: () => handleToggleStatus(row)
        })
      )
    }

    if (canRestoreAdmin(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:arrow-go-back-line',
          iconClass: 'bg-success/12 text-success',
          title: '恢复',
          onClick: () => handleRestoreAdmin(row)
        })
      )
    } else if (canDeleteAdmin(row)) {
      actions.push(
        h(ArtButtonTable, {
          type: 'delete',
          title: '回收',
          onClick: () => handleDeleteAdmin(row)
        })
      )
    }

    return h('div', { class: 'operation-cell' }, actions)
  }

  function handleSearch(params: Api.AdminAccounts.AdminAccountSearchParams) {
    replaceSearchParams({
      keyword: params.keyword,
      status: params.status
    })
    getData()
  }

  function handleReset() {
    resetSearchParams()
    searchForm.value = {}
    clearAdminSelection()
    getData()
  }

  function toggleRecycleView() {
    replaceSearchParams({
      keyword: searchForm.value.keyword,
      status: isRecycleView.value ? undefined : '-1'
    })
    searchForm.value = {
      ...searchForm.value,
      status: isRecycleView.value ? undefined : '-1'
    }
    clearAdminSelection()
    getData()
  }

  function handleAdminSelectionChange(rows: AdminAccountItem[]) {
    selectedAdmins.value = Array.isArray(rows) ? rows : []
  }

  async function loadAdminDetail(id: number, openDrawer = true) {
    if (openDrawer) {
      detailVisible.value = true
    }

    detailLoading.value = true

    try {
      const response = await fetchGetAdminAccountDetail(id)
      activeAdmin.value = response.item
      permissionTree.value = response.permission_tree
      directPermissionTree.value = response.direct_permission_tree
      detailEditable.value = response.editable
      availableRoles.value = response.editable.available_roles
      return response
    } catch (error) {
      ElMessage.error('加载管理员详情失败。')
      throw error
    } finally {
      detailLoading.value = false
    }
  }

  async function openDetail(row: AdminAccountItem) {
    await loadAdminDetail(row.id, true)
  }

  async function openCreateDialog() {
    try {
      const response = await fetchGetAdminAccountTemplate()
      availableRoles.value = response.editable.available_roles
      syncAdminCreateFormFromEditable(createForm, response.editable)
      createVisible.value = true
    } catch {
      ElMessage.error('加载管理员创建模板失败。')
    }
  }

  function openEditDialog(row?: AdminAccountItem) {
    const target = row ?? activeAdmin.value
    if (!target) {
      return
    }

    activeAdmin.value = target
    syncAdminEditFormFromItem(editForm, target)
    editVisible.value = true
  }

  async function openRoleDialog(row?: AdminAccountItem) {
    const target = row ?? activeAdmin.value
    if (!target) {
      return
    }

    try {
      const response =
        activeAdmin.value?.id === target.id && detailEditable.value
          ? {
              item: activeAdmin.value,
              editable: detailEditable.value
            }
          : await loadAdminDetail(target.id, false)

      activeAdmin.value = response.item
      detailEditable.value = response.editable
      availableRoles.value = response.editable.available_roles
      syncAdminRoleFormFromEditable(roleForm, response.editable)
      roleVisible.value = true
    } catch {
      ElMessage.error('加载角色绑定数据失败。')
    }
  }

  async function openPermissionDialog(row?: AdminAccountItem) {
    const target = row ?? activeAdmin.value
    if (!target) {
      return
    }

    try {
      const response =
        activeAdmin.value?.id === target.id && detailEditable.value
          ? {
              item: activeAdmin.value,
              editable: detailEditable.value,
              direct_permission_tree: directPermissionTree.value
            }
          : await loadAdminDetail(target.id, false)

      activeAdmin.value = response.item
      detailEditable.value = response.editable
      syncAdminPermissionFormFromEditable(permissionForm, response.editable)
      directPermissionTree.value = response.direct_permission_tree
      permissionVisible.value = true

      await nextTick()
      accessDialogsRef.value?.syncPermissionTreeCheckedKeys()
    } catch {
      ElMessage.error('加载直属权限数据失败。')
    }
  }

  async function submitCreateAdmin() {
    const valid = await writeDialogsRef.value?.validateCreate()
    if (!valid) {
      return
    }

    creatingAdmin.value = true

    try {
      const response = await fetchCreateAdminAccount(
        buildAdminCreatePayload(createForm, hasAdminRoleAuth.value)
      )

      ElMessage.success(`管理员已创建：${response.created_admin_label}`)
      createVisible.value = false
      resetCreateState()
      await getData()
    } catch {
      ElMessage.error('创建管理员账号失败。')
    } finally {
      creatingAdmin.value = false
    }
  }

  async function submitEditAdmin() {
    if (!activeAdmin.value) {
      return
    }

    const valid = await writeDialogsRef.value?.validateEdit()
    if (!valid) {
      return
    }

    savingAdmin.value = true

    try {
      const response = await fetchUpdateAdminAccount(
        activeAdmin.value.id,
        buildAdminEditPayload(editForm)
      )

      ElMessage.success(
        response.password_reset ? '管理员信息已更新，密码已重置。' : '管理员信息已更新。'
      )
      editVisible.value = false
      resetEditState()
      await getData()
      if (detailVisible.value && activeAdmin.value?.id === response.updated_admin_id) {
        await loadAdminDetail(response.updated_admin_id, true)
      }
    } catch {
      ElMessage.error('更新管理员信息失败。')
    } finally {
      savingAdmin.value = false
    }
  }

  async function submitAdminRoles() {
    if (!activeAdmin.value) {
      return
    }

    savingRoles.value = true

    try {
      const response = await fetchUpdateAdminAccountRoles(
        activeAdmin.value.id,
        buildAdminRolePayload(roleForm)
      )

      ElMessage.success('管理员角色已更新。')
      roleVisible.value = false
      resetRoleState()
      await getData()
      if (detailVisible.value && activeAdmin.value?.id === response.updated_admin_id) {
        await loadAdminDetail(response.updated_admin_id, true)
      }
    } catch {
      ElMessage.error('更新管理员角色失败。')
    } finally {
      savingRoles.value = false
    }
  }

  async function submitAdminPermissions() {
    if (!activeAdmin.value) {
      return
    }

    const permissionIds =
      accessDialogsRef.value?.collectPermissionTreeCheckedKeys() ?? permissionForm.permission_ids
    assignAdminPermissionFormState(permissionForm, { permission_ids: permissionIds })
    savingPermissions.value = true

    try {
      const response = await fetchUpdateAdminAccountPermissions(
        activeAdmin.value.id,
        buildAdminPermissionPayload(permissionIds)
      )

      ElMessage.success('管理员直属权限已更新。')
      permissionVisible.value = false
      resetPermissionState()
      await getData()
      if (detailVisible.value && activeAdmin.value?.id === response.updated_admin_id) {
        await loadAdminDetail(response.updated_admin_id, true)
      }
    } catch {
      ElMessage.error('更新管理员直属权限失败。')
    } finally {
      savingPermissions.value = false
    }
  }

  async function handleToggleStatus(row?: AdminAccountItem) {
    const target = row ?? activeAdmin.value
    if (!target) {
      return
    }

    const nextStatus = target.status === 1 ? 0 : 1
    const actionLabel = nextStatus === 1 ? '启用' : '停用'

    try {
      await ElMessageBox.confirm(
        `本次将${actionLabel} ${target.display}，并清空其登录令牌，是否继续？`,
        `${actionLabel}管理员`,
        {
          type: nextStatus === 1 ? 'success' : 'warning',
          confirmButtonText: actionLabel,
          cancelButtonText: '取消'
        }
      )
    } catch {
      return
    }

    try {
      const response = await fetchUpdateAdminAccountStatus(target.id, { status: nextStatus })
      ElMessage.success(`管理员已${nextStatus === 1 ? '启用' : '停用'}。`)
      await getData()
      if (detailVisible.value && activeAdmin.value?.id === response.updated_admin_id) {
        await loadAdminDetail(response.updated_admin_id, true)
      }
    } catch {
      ElMessage.error('更新管理员状态失败。')
    }
  }

  async function handleDeleteAdmin(row?: AdminAccountItem) {
    const target = row ?? activeAdmin.value
    if (!target) {
      return
    }

    try {
      const response = await fetchGetAdminAccountDeleteAudit(target.id)
      const audit = response.audit

      if (!audit.can_delete) {
        await ElMessageBox.alert(audit.blocking_reasons.join('\n'), '暂不可回收', {
          type: 'warning'
        })
        return
      }

      const prompt = await ElMessageBox.prompt(
        `请输入 ${audit.confirmation_phrase}，将 ${audit.admin_label} 移入回收站。角色、直属权限和日志会保留。`,
        '回收管理员',
        {
          type: 'warning',
          inputValue: '',
          inputPlaceholder: audit.confirmation_phrase,
          confirmButtonText: '移入回收站',
          cancelButtonText: '取消',
          inputValidator: (value) =>
            value.trim() === audit.confirmation_phrase || `请输入：${audit.confirmation_phrase}`
        }
      )

      await fetchDeleteAdminAccount(target.id, {
        confirmation_phrase: prompt.value.trim()
      })

      ElMessage.success('管理员账号已移入回收站。')
      closeAdminPanelsForDeletedIds([target.id])
      clearAdminSelection()
      await getData()
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }
      ElMessage.error('移入回收站失败。')
    }
  }

  async function handleRestoreAdmin(row?: AdminAccountItem) {
    const target = row ?? activeAdmin.value
    if (!target) {
      return
    }

    if (!target.deleted) {
      ElMessage.warning('该管理员账号当前已处于正常状态。')
      return
    }

    try {
      await ElMessageBox.confirm(
        `确认将 ${target.display || `管理员 #${target.id}`} 恢复到正常列表吗？`,
        '恢复管理员',
        {
          confirmButtonText: '恢复管理员',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchRestoreAdminAccount(target.id)
      clearAdminSelection()
      await getData()

      if (detailVisible.value && activeAdmin.value?.id === response.restored_admin_id) {
        if (isRecycleView.value) {
          detailVisible.value = false
          activeAdmin.value = null
          detailEditable.value = null
          permissionTree.value = []
          directPermissionTree.value = []
        } else {
          await loadAdminDetail(response.restored_admin_id, true)
        }
      }

      ElMessage.success(`管理员已恢复：${response.restored_admin_label || target.display}。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('恢复管理员失败。')
    }
  }

  async function handleBatchDeleteAdmins() {
    if (!hasAdminBatchDeleteAuth.value) {
      ElMessage.warning('您没有批量回收管理员账号的权限。')
      return
    }

    if (selectedAdmins.value.length === 0) {
      ElMessage.warning('请至少选择一个管理员账号。')
      return
    }

    const adminIds = selectedAdmins.value.map((item) => item.id)

    try {
      const response = await fetchAuditAdminAccountBatchDelete({
        admin_ids: adminIds
      })
      const audit = response.audit

      if (!audit.can_delete_all) {
        await ElMessageBox.alert(buildAdminBatchDeleteBlockedMessage(audit), '批量回收受限', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const prompt = await ElMessageBox.prompt(
        buildAdminBatchDeletePromptMessage(audit),
        '批量回收管理员',
        {
          type: 'warning',
          inputValue: '',
          inputPlaceholder: audit.confirmation_phrase,
          confirmButtonText: '批量回收',
          cancelButtonText: '取消',
          inputValidator: (value) =>
            value.trim() === audit.confirmation_phrase || `请输入：${audit.confirmation_phrase}`
        }
      )

      const deleteResponse = await fetchBatchDeleteAdminAccounts({
        admin_ids: adminIds,
        confirmation_phrase: prompt.value.trim()
      })

      closeAdminPanelsForDeletedIds(deleteResponse.deleted_admin_ids)
      clearAdminSelection()
      await getData()
      ElMessage.success(`已将 ${deleteResponse.deleted_count} 个管理员账号移入回收站。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }
      ElMessage.error('批量移入回收站失败。')
    }
  }

  async function handleBatchRestoreAdmins() {
    const recycleSelection = selectedAdmins.value.filter((item) => item.deleted)
    if (recycleSelection.length === 0) {
      ElMessage.warning('请至少选择一个已回收的管理员账号。')
      return
    }

    const adminIds = recycleSelection.map((item) => item.id)

    try {
      await ElMessageBox.confirm(
        `确认将选中的 ${adminIds.length} 个管理员账号恢复到正常列表吗？`,
        '批量恢复管理员',
        {
          confirmButtonText: '批量恢复',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchBatchRestoreAdminAccounts({
        admin_ids: adminIds
      })

      clearAdminSelection()
      await getData()

      if (activeAdmin.value && response.restored_admin_ids.includes(activeAdmin.value.id)) {
        if (isRecycleView.value) {
          detailVisible.value = false
          activeAdmin.value = null
          detailEditable.value = null
          permissionTree.value = []
          directPermissionTree.value = []
        } else {
          await loadAdminDetail(activeAdmin.value.id, true)
        }
      }

      ElMessage.success(`已恢复 ${response.restored_count} 个管理员账号。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }
      ElMessage.error('批量恢复管理员失败。')
    }
  }

  function syncCreateFormState(form: AdminCreateFormState) {
    assignAdminCreateFormState(createForm, form)
  }

  function syncEditFormState(form: AdminEditFormState) {
    assignAdminEditFormState(editForm, form)
  }

  function syncRoleFormState(form: AdminRoleFormState) {
    assignAdminRoleFormState(roleForm, form)
  }

  function syncPermissionFormState(form: AdminPermissionFormState) {
    assignAdminPermissionFormState(permissionForm, form)
  }

  function resetCreateState() {
    assignAdminCreateFormState(createForm, createAdminCreateFormState())
    writeDialogsRef.value?.clearCreateValidate()
  }

  function resetEditState() {
    assignAdminEditFormState(editForm, createAdminEditFormState())
    writeDialogsRef.value?.clearEditValidate()
  }

  function resetRoleState() {
    assignAdminRoleFormState(roleForm, createAdminRoleFormState())
  }

  function resetPermissionState() {
    assignAdminPermissionFormState(permissionForm, createAdminPermissionFormState())
  }

  function clearAdminSelection() {
    selectedAdmins.value = []
    tableRef.value?.elTableRef?.clearSelection?.()
  }

  function closeAdminPanelsForDeletedIds(adminIds: number[]) {
    if (!activeAdmin.value || !adminIds.includes(activeAdmin.value.id)) {
      return
    }

    detailVisible.value = false
    editVisible.value = false
    roleVisible.value = false
    permissionVisible.value = false
    activeAdmin.value = null
    detailEditable.value = null
    permissionTree.value = []
    directPermissionTree.value = []
  }

  function canEditAdmin(row: AdminAccountItem) {
    return hasAdminEditAuth.value && !row.deleted && !isProtectedAdmin(row)
  }

  function canAssignRoles(row: AdminAccountItem) {
    return hasAdminRoleAuth.value && !row.deleted && !isProtectedAdmin(row)
  }

  function canAssignDirectPermissions(row: AdminAccountItem) {
    return hasAdminPermissionAuth.value && !row.deleted && !isProtectedAdmin(row)
  }

  function canToggleStatus(row: AdminAccountItem) {
    return hasAdminStatusAuth.value && !row.deleted && !isProtectedAdmin(row)
  }

  function canDeleteAdmin(row: AdminAccountItem) {
    return hasAdminDeleteAuth.value && !row.deleted && !isProtectedAdmin(row)
  }

  function canRestoreAdmin(row: AdminAccountItem) {
    return hasAdminRecycleAuth.value && row.deleted
  }

  function isProtectedAdmin(row: AdminAccountItem) {
    return row.is_root || row.id === currentAdminId.value
  }

  function buildAdminBatchDeleteBlockedMessage(
    audit: Api.AdminAccounts.AdminAccountBatchDeleteAudit
  ) {
    const blockedItems = audit.items.filter((item) => !item.can_delete)
    return [
      '当前所选管理员暂不可批量回收。',
      '',
      ...blockedItems.slice(0, 6).map((item) => {
        const label = item.admin_label || `管理员 #${item.admin_id}`
        const reason = item.blocking_reasons.join(' ') || '请刷新选择列表后重试。'
        return `- ${label}: ${reason}`
      }),
      '',
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function buildAdminBatchDeletePromptMessage(
    audit: Api.AdminAccounts.AdminAccountBatchDeleteAudit
  ) {
    return [
      `即将把 ${audit.summary.deletable_count} 个管理员账号移入回收站。`,
      '',
      `保留角色：${audit.summary.retained_admin_role_row_count}`,
      `保留直属权限：${audit.summary.retained_admin_permission_row_count}`,
      `保留日志：${audit.summary.retained_admin_log_row_count}`,
      '',
      `请输入 ${audit.confirmation_phrase} 确认回收。`,
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
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
    if (value === 'success' || value === 'warning' || value === 'info' || value === 'danger') {
      return value
    }
    return 'info'
  }
</script>

<style scoped lang="scss">
  .admin-account-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .admin-cell {
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

  .operation-cell,
  .role-cell {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
</style>
