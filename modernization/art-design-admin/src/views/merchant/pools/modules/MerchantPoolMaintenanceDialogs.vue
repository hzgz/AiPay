<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <ElDialog
    v-model="createDialogVisible"
    width="560px"
    class="merchant-pool-shell-dialog"
    destroy-on-close
    align-center
    title="新建轮询池"
  >
    <ElForm label-position="top" class="merchant-pool-dialog-shell">
      <ElFormItem label="轮询池名称">
        <ElInput v-model="createName" maxlength="64" placeholder="输入名称" />
      </ElFormItem>
      <ElFormItem label="支付类型">
        <ElSelect v-model="createType" placeholder="请选择支付类型" class="full-width">
          <ElOption
            v-for="item in paymentTypeOptions"
            :key="item.value"
            :label="item.label"
            :value="item.value"
          />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="轮询方式">
        <ElRadioGroup v-model="createRoundType">
          <ElRadioButton :label="1">顺序轮询</ElRadioButton>
          <ElRadioButton :label="2">随机轮询</ElRadioButton>
        </ElRadioGroup>
      </ElFormItem>
      <ElFormItem label="默认状态">
        <ElSwitch v-model="createStatus" inline-prompt active-text="启用" inactive-text="停用" />
      </ElFormItem>
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="createDialogVisible = false">取消</ElButton>
        <ElButton type="primary" :loading="savingCreate" @click="emit('submit:create')">
          创建轮询池
        </ElButton>
      </div>
    </template>
  </ElDialog>

  <ElDialog
    v-model="editDialogVisible"
    width="560px"
    class="merchant-pool-shell-dialog"
    destroy-on-close
    align-center
    title="编辑轮询池"
  >
    <ElForm label-position="top" class="merchant-pool-dialog-shell">
      <ElFormItem label="支付类型">
        <ElInput :model-value="activePoolTypeLabel" disabled />
      </ElFormItem>
      <ElFormItem label="轮询池名称">
        <ElInput v-model="editName" maxlength="64" placeholder="输入名称" />
      </ElFormItem>
      <ElFormItem label="轮询方式">
        <ElRadioGroup v-model="editRoundType">
          <ElRadioButton :label="1">顺序轮询</ElRadioButton>
          <ElRadioButton :label="2">随机轮询</ElRadioButton>
        </ElRadioGroup>
      </ElFormItem>
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="editDialogVisible = false">取消</ElButton>
        <ElButton type="primary" :loading="savingEdit" @click="emit('submit:edit')">
          保存修改
        </ElButton>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'
  import type {
    MerchantPoolCreateFormState,
    MerchantPoolEditFormState
  } from './merchantPoolFormState'

  defineOptions({ name: 'MerchantPoolMaintenanceDialogs' })

  type PoolListItem = Api.Payments.PoolListItem

  interface Props {
    createVisible: boolean
    editVisible: boolean
    savingCreate: boolean
    savingEdit: boolean
    activePool: PoolListItem | null
    createForm: MerchantPoolCreateFormState
    editForm: MerchantPoolEditFormState
    paymentTypeOptions: Array<{ label: string; value: string }>
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:createVisible', value: boolean): void
    (e: 'update:editVisible', value: boolean): void
    (e: 'update:createForm', value: MerchantPoolCreateFormState): void
    (e: 'update:editForm', value: MerchantPoolEditFormState): void
    (e: 'submit:create'): void
    (e: 'submit:edit'): void
  }>()

  const createDialogVisible = computed({
    get: () => props.createVisible,
    set: (value: boolean) => emit('update:createVisible', value)
  })

  const editDialogVisible = computed({
    get: () => props.editVisible,
    set: (value: boolean) => emit('update:editVisible', value)
  })

  const activePoolTypeLabel = computed(() => {
    if (!props.activePool) {
      return ''
    }

    return `${displayPoolType(props.activePool)} / ${props.activePool.type}`
  })

  function updateCreateForm(patch: Partial<MerchantPoolCreateFormState>) {
    emit('update:createForm', {
      ...props.createForm,
      ...patch
    })
  }

  function updateEditForm(patch: Partial<MerchantPoolEditFormState>) {
    emit('update:editForm', {
      ...props.editForm,
      ...patch
    })
  }

  const createName = computed({
    get: () => props.createForm.name,
    set: (value: string) => updateCreateForm({ name: value })
  })

  const createType = computed({
    get: () => props.createForm.type,
    set: (value: string) => updateCreateForm({ type: value })
  })

  const createRoundType = computed({
    get: () => props.createForm.round_type,
    set: (value: number) => updateCreateForm({ round_type: value })
  })

  const createStatus = computed({
    get: () => props.createForm.status,
    set: (value: boolean) => updateCreateForm({ status: value })
  })

  const editName = computed({
    get: () => props.editForm.name,
    set: (value: string) => updateEditForm({ name: value })
  })

  const editRoundType = computed({
    get: () => props.editForm.round_type,
    set: (value: number) => updateEditForm({ round_type: value })
  })

  function displayPoolType(pool?: Partial<PoolListItem> | null, fallback = '--') {
    return displayAdminFixtureText(pool?.type_text || pool?.type_label || pool?.type, fallback)
  }
</script>

<style scoped lang="scss">
  .merchant-pool-shell-dialog :deep(.el-dialog__header) {
    padding-bottom: 12px;
    margin-right: 0;
  }

  .merchant-pool-shell-dialog :deep(.el-dialog__body) {
    padding-top: 8px;
  }

  .merchant-pool-shell-dialog :deep(.el-dialog__footer) {
    padding-top: 14px;
    border-top: 1px solid var(--el-border-color-lighter);
  }

  .merchant-pool-dialog-shell {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .merchant-pool-dialog-shell :deep(.el-form-item) {
    margin-bottom: 18px;
  }

  .merchant-pool-dialog-shell :deep(.el-form-item:last-child) {
    margin-bottom: 0;
  }

  .merchant-pool-dialog-shell :deep(.el-form-item__label) {
    color: var(--el-text-color-primary);
    font-weight: 500;
  }

  .merchant-pool-dialog-shell :deep(.el-input__wrapper),
  .merchant-pool-dialog-shell :deep(.el-select__wrapper) {
    border-radius: 12px;
  }

  .dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
  }

  .dialog-footer :deep(.el-button) {
    min-width: 96px;
    height: 36px;
    border-radius: 10px;
  }

  .full-width {
    width: 100%;
  }
</style>
