<template>
  <ElDialog
    v-model="roleDialogVisible"
    width="760px"
    destroy-on-close
    align-center
    :title="activeAdmin ? `分配角色：${activeAdmin.display}` : '分配角色'"
    @closed="emit('closed:role')"
  >
    <ElForm :model="roleForm" label-position="top">
      <ElFormItem label="角色">
        <ElSelect
          v-model="roleIds"
          multiple
          collapse-tags
          collapse-tags-tooltip
          clearable
          placeholder="请选择该管理员的角色"
        >
          <ElOption
            v-for="role in availableRoles"
            :key="role.id"
            :label="displayAdminRoleOptionLabel(role)"
            :value="role.id"
          />
        </ElSelect>
      </ElFormItem>
      <p class="dialog-hint">变更角色后，登录令牌会失效，新权限下次登录生效。</p>
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="roleDialogVisible = false">取消</ElButton>
        <ElButton type="primary" :loading="savingRoles" @click="emit('submit:role')">
          保存角色
        </ElButton>
      </div>
    </template>
  </ElDialog>

  <ElDialog
    v-model="permissionDialogVisible"
    width="920px"
    destroy-on-close
    align-center
    :title="activeAdmin ? `直属权限：${activeAdmin.display}` : '直属权限'"
    @closed="emit('closed:permission')"
  >
    <div class="permission-editor">
      <p class="dialog-hint">直属权限会补充角色权限；保存后登录令牌会失效，新权限下次登录生效。</p>
      <ElScrollbar height="420px" class="permission-scroll permission-edit-scroll">
        <ElTree
          ref="permissionEditorTreeRef"
          :data="directPermissionTree"
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
                :type="isDirectPermissionSelected(treeItem.id) ? 'warning' : 'info'"
                effect="plain"
              >
                {{ isDirectPermissionSelected(treeItem.id) ? '直属授权' : '可选' }}
              </ElTag>
            </div>
          </template>
        </ElTree>
      </ElScrollbar>
    </div>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="permissionDialogVisible = false">取消</ElButton>
        <ElButton type="primary" :loading="savingPermissions" @click="emit('submit:permission')">
          保存权限
        </ElButton>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import { displayAdminRoleOptionLabel } from './admin-form-state'
  import type { AdminPermissionFormState, AdminRoleFormState } from './admin-form-state'

  defineOptions({ name: 'AdminAccessDialogs' })

  type AdminAccountItem = Api.AdminAccounts.AdminAccountListItem
  type AdminEditableRoleItem = Api.AdminAccounts.AdminAccountEditableRoleItem
  type PermissionTreeItem = Api.Roles.PermissionTreeItem

  interface Props {
    roleVisible: boolean
    permissionVisible: boolean
    savingRoles: boolean
    savingPermissions: boolean
    activeAdmin: AdminAccountItem | null
    availableRoles: AdminEditableRoleItem[]
    directPermissionTree: PermissionTreeItem[]
    roleForm: AdminRoleFormState
    permissionForm: AdminPermissionFormState
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:roleVisible', value: boolean): void
    (e: 'update:permissionVisible', value: boolean): void
    (e: 'update:roleForm', value: AdminRoleFormState): void
    (e: 'update:permissionForm', value: AdminPermissionFormState): void
    (e: 'submit:role'): void
    (e: 'submit:permission'): void
    (e: 'closed:role'): void
    (e: 'closed:permission'): void
  }>()

  const permissionEditorTreeRef = ref<any>()

  const roleDialogVisible = computed({
    get: () => props.roleVisible,
    set: (value: boolean) => emit('update:roleVisible', value)
  })

  const permissionDialogVisible = computed({
    get: () => props.permissionVisible,
    set: (value: boolean) => emit('update:permissionVisible', value)
  })

  function updateRoleForm(patch: Partial<AdminRoleFormState>) {
    emit('update:roleForm', {
      ...props.roleForm,
      ...patch,
      role_ids: patch.role_ids ? [...patch.role_ids] : [...props.roleForm.role_ids]
    })
  }

  function updatePermissionForm(patch: Partial<AdminPermissionFormState>) {
    emit('update:permissionForm', {
      ...props.permissionForm,
      ...patch,
      permission_ids: patch.permission_ids
        ? [...patch.permission_ids]
        : [...props.permissionForm.permission_ids]
    })
  }

  const roleIds = computed({
    get: () => props.roleForm.role_ids,
    set: (value: number[]) => updateRoleForm({ role_ids: value })
  })

  const permissionIds = computed({
    get: () => props.permissionForm.permission_ids,
    set: (value: number[]) => updatePermissionForm({ permission_ids: value })
  })

  function handlePermissionTreeCheck() {
    permissionIds.value = collectPermissionTreeCheckedKeys()
  }

  function collectPermissionTreeCheckedKeys() {
    const checkedKeys = permissionEditorTreeRef.value?.getCheckedKeys?.() ?? []
    return checkedKeys
      .map((value: unknown) => Number(value))
      .filter((value: number) => Number.isInteger(value) && value > 0)
  }

  function syncPermissionTreeCheckedKeys() {
    permissionEditorTreeRef.value?.setCheckedKeys?.(props.permissionForm.permission_ids)
  }

  function isDirectPermissionSelected(permissionId: number) {
    return props.permissionForm.permission_ids.includes(Number(permissionId))
  }

  defineExpose({
    collectPermissionTreeCheckedKeys,
    syncPermissionTreeCheckedKeys
  })
</script>

<style scoped lang="scss">
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
    width: 100%;
    gap: 12px;
  }

  .permission-meta {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
    gap: 2px;
  }

  .permission-meta strong {
    color: #0f172a;
    font-size: 13px;
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
    gap: 12px;
  }

  .dialog-hint {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.7;
  }

  @media (width <= 991px) {
    .permission-node {
      flex-direction: column;
      align-items: flex-start;
    }
  }
</style>
