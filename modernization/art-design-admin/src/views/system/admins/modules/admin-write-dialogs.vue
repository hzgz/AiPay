<template>
  <ElDialog
    v-model="createDialogVisible"
    width="760px"
    destroy-on-close
    align-center
    title="新增管理员"
    @closed="emit('closed:create')"
  >
    <ElForm ref="createFormRef" :model="createForm" :rules="createRules" label-position="top">
      <ElFormItem label="用户名" prop="username">
        <ElInput v-model="createUsername" maxlength="40" placeholder="请输入登录用户名" />
      </ElFormItem>
      <ElFormItem label="昵称" prop="nickname">
        <ElInput v-model="createNickname" maxlength="40" placeholder="请输入显示昵称" />
      </ElFormItem>
      <ElFormItem label="密码" prop="password">
        <ElInput
          v-model="createPassword"
          maxlength="64"
          show-password
          placeholder="至少 6 位字符"
        />
      </ElFormItem>
      <ElFormItem label="状态" prop="status">
        <ElSelect v-model="createStatus" placeholder="请选择状态">
          <ElOption label="启用" :value="1" />
          <ElOption label="停用" :value="0" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem v-if="hasAdminRoleAuth" label="初始角色">
        <ElSelect
          v-model="createRoleIds"
          multiple
          collapse-tags
          collapse-tags-tooltip
          clearable
          placeholder="可选的初始角色绑定"
        >
          <ElOption
            v-for="role in availableRoles"
            :key="role.id"
            :label="displayAdminRoleOptionLabel(role)"
            :value="role.id"
          />
        </ElSelect>
      </ElFormItem>
      <p class="dialog-hint">留空时可登录后台，但不会继承角色菜单权限。</p>
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="createDialogVisible = false">取消</ElButton>
        <ElButton type="primary" :loading="creatingAdmin" @click="emit('submit:create')">
          新增管理员
        </ElButton>
      </div>
    </template>
  </ElDialog>

  <ElDialog
    v-model="editDialogVisible"
    width="760px"
    destroy-on-close
    align-center
    :title="activeAdmin ? `编辑 ${activeAdmin.display}` : '编辑管理员'"
    @closed="emit('closed:edit')"
  >
    <ElForm ref="editFormRef" :model="editForm" :rules="editRules" label-position="top">
      <ElFormItem label="用户名" prop="username">
        <ElInput v-model="editUsername" maxlength="40" placeholder="请输入登录用户名" />
      </ElFormItem>
      <ElFormItem label="昵称" prop="nickname">
        <ElInput v-model="editNickname" maxlength="40" placeholder="请输入显示昵称" />
      </ElFormItem>
      <ElFormItem label="重置密码" prop="password">
        <ElInput
          v-model="editPassword"
          maxlength="64"
          show-password
          placeholder="留空则保留当前密码"
        />
      </ElFormItem>
      <p class="dialog-hint">重置密码后，当前登录令牌会失效。</p>
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="editDialogVisible = false">取消</ElButton>
        <ElButton type="primary" :loading="savingAdmin" @click="emit('submit:edit')">
          保存修改
        </ElButton>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import type { FormInstance } from 'element-plus'
  import {
    createAdminCreateRules,
    createAdminEditRules,
    displayAdminRoleOptionLabel
  } from './admin-form-state'
  import type { AdminCreateFormState, AdminEditFormState } from './admin-form-state'

  defineOptions({ name: 'AdminWriteDialogs' })

  type AdminAccountItem = Api.AdminAccounts.AdminAccountListItem
  type AdminEditableRoleItem = Api.AdminAccounts.AdminAccountEditableRoleItem

  interface Props {
    createVisible: boolean
    editVisible: boolean
    creatingAdmin: boolean
    savingAdmin: boolean
    hasAdminRoleAuth: boolean
    activeAdmin: AdminAccountItem | null
    availableRoles: AdminEditableRoleItem[]
    createForm: AdminCreateFormState
    editForm: AdminEditFormState
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:createVisible', value: boolean): void
    (e: 'update:editVisible', value: boolean): void
    (e: 'update:createForm', value: AdminCreateFormState): void
    (e: 'update:editForm', value: AdminEditFormState): void
    (e: 'submit:create'): void
    (e: 'submit:edit'): void
    (e: 'closed:create'): void
    (e: 'closed:edit'): void
  }>()

  const createFormRef = ref<FormInstance>()
  const editFormRef = ref<FormInstance>()
  const createRules = createAdminCreateRules()
  const editRules = createAdminEditRules()

  const createDialogVisible = computed({
    get: () => props.createVisible,
    set: (value: boolean) => emit('update:createVisible', value)
  })

  const editDialogVisible = computed({
    get: () => props.editVisible,
    set: (value: boolean) => emit('update:editVisible', value)
  })

  function updateCreateForm(patch: Partial<AdminCreateFormState>) {
    emit('update:createForm', {
      ...props.createForm,
      ...patch,
      role_ids: patch.role_ids ? [...patch.role_ids] : [...props.createForm.role_ids]
    })
  }

  function updateEditForm(patch: Partial<AdminEditFormState>) {
    emit('update:editForm', {
      ...props.editForm,
      ...patch
    })
  }

  const createUsername = computed({
    get: () => props.createForm.username,
    set: (value: string) => updateCreateForm({ username: value })
  })

  const createNickname = computed({
    get: () => props.createForm.nickname,
    set: (value: string) => updateCreateForm({ nickname: value })
  })

  const createPassword = computed({
    get: () => props.createForm.password,
    set: (value: string) => updateCreateForm({ password: value })
  })

  const createStatus = computed({
    get: () => props.createForm.status,
    set: (value: number) => updateCreateForm({ status: value })
  })

  const createRoleIds = computed({
    get: () => props.createForm.role_ids,
    set: (value: number[]) => updateCreateForm({ role_ids: value })
  })

  const editUsername = computed({
    get: () => props.editForm.username,
    set: (value: string) => updateEditForm({ username: value })
  })

  const editNickname = computed({
    get: () => props.editForm.nickname,
    set: (value: string) => updateEditForm({ nickname: value })
  })

  const editPassword = computed({
    get: () => props.editForm.password,
    set: (value: string) => updateEditForm({ password: value })
  })

  async function validateCreate() {
    if (!createFormRef.value) {
      return false
    }

    return createFormRef.value.validate().catch(() => false)
  }

  async function validateEdit() {
    if (!editFormRef.value) {
      return false
    }

    return editFormRef.value.validate().catch(() => false)
  }

  function clearCreateValidate() {
    createFormRef.value?.clearValidate()
  }

  function clearEditValidate() {
    editFormRef.value?.clearValidate()
  }

  defineExpose({
    validateCreate,
    validateEdit,
    clearCreateValidate,
    clearEditValidate
  })
</script>

<style scoped lang="scss">
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
</style>
