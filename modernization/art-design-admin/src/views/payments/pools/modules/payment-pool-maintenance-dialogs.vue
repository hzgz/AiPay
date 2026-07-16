<template>
  <ElDialog
    v-model="createDialogVisible"
    width="560px"
    class="payment-pool-shell-dialog"
    destroy-on-close
    align-center
    title="新建轮询池"
  >
    <ElForm label-position="top" class="payment-pool-dialog-shell">
      <ElFormItem label="商户编号">
        <ElInput v-model="createUserId" placeholder="请输入已有商户编号" />
      </ElFormItem>
      <ElFormItem label="轮询池名称">
        <ElInput
          v-model="createName"
          maxlength="64"
          show-word-limit
          placeholder="请输入轮询池名称"
        />
      </ElFormItem>
      <ElFormItem label="支付类型">
        <ElSelect v-model="createType" placeholder="请选择支付类型">
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
          <ElRadioButton :label="2">随机权重</ElRadioButton>
        </ElRadioGroup>
      </ElFormItem>
      <ElFormItem label="默认状态">
        <ElSwitch v-model="createStatus" inline-prompt active-text="启用" inactive-text="停用" />
      </ElFormItem>
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="createDialogVisible = false">取消</ElButton>
        <ElButton
          v-if="hasPoolCreateAuth"
          type="primary"
          :loading="savingCreate"
          @click="emit('submit:create')"
        >
          创建轮询池
        </ElButton>
      </div>
    </template>
  </ElDialog>

  <ElDialog
    v-model="editDialogVisible"
    width="560px"
    class="payment-pool-shell-dialog"
    destroy-on-close
    align-center
    title="编辑轮询池基础配置"
  >
    <ElForm label-position="top" class="payment-pool-dialog-shell">
      <ElFormItem label="支付类型">
        <ElInput :model-value="activePoolTypeLabel" disabled />
      </ElFormItem>
      <ElFormItem label="轮询池名称">
        <ElInput v-model="editName" maxlength="64" show-word-limit placeholder="请输入轮询池名称" />
      </ElFormItem>
      <ElFormItem label="轮询方式">
        <ElRadioGroup v-model="editRoundType">
          <ElRadioButton :label="1">顺序轮询</ElRadioButton>
          <ElRadioButton :label="2">随机权重</ElRadioButton>
        </ElRadioGroup>
      </ElFormItem>
      <p class="dialog-note">支付类型创建后固定，避免影响已配置的通道关系。</p>
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="editDialogVisible = false">取消</ElButton>
        <ElButton
          v-if="hasPoolEditAuth"
          type="primary"
          :loading="savingEdit"
          @click="emit('submit:edit')"
        >
          保存修改
        </ElButton>
      </div>
    </template>
  </ElDialog>

  <ElDialog
    v-model="statusDialogVisible"
    width="520px"
    class="payment-pool-shell-dialog"
    destroy-on-close
    align-center
    title="维护轮询池状态"
  >
    <ElForm label-position="top" class="payment-pool-dialog-shell">
      <ElFormItem label="启用状态">
        <ElSwitch v-model="statusEnabled" inline-prompt active-text="启用" inactive-text="停用" />
      </ElFormItem>
      <p class="dialog-note">
        {{
          statusForm.status
            ? '启用后会继续参与收款路由。'
            : '停用后会退出收款路由，但不会删除已选通道和游标。'
        }}
      </p>
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="statusDialogVisible = false">取消</ElButton>
        <ElButton
          v-if="hasPoolStatusAuth"
          type="primary"
          :loading="savingStatus"
          @click="emit('submit:status')"
        >
          保存状态
        </ElButton>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'
  import type {
    PaymentPoolCreateFormState,
    PaymentPoolEditFormState,
    PaymentPoolStatusFormState
  } from './payment-pool-form-state'

  defineOptions({ name: 'PaymentPoolMaintenanceDialogs' })

  type PoolListItem = Api.Payments.PoolListItem

  interface Props {
    createVisible: boolean
    editVisible: boolean
    statusVisible: boolean
    savingCreate: boolean
    savingEdit: boolean
    savingStatus: boolean
    hasPoolCreateAuth: boolean
    hasPoolEditAuth: boolean
    hasPoolStatusAuth: boolean
    activePool: PoolListItem | null
    createForm: PaymentPoolCreateFormState
    editForm: PaymentPoolEditFormState
    statusForm: PaymentPoolStatusFormState
    paymentTypeOptions: Array<{ label: string; value: string }>
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:createVisible', value: boolean): void
    (e: 'update:editVisible', value: boolean): void
    (e: 'update:statusVisible', value: boolean): void
    (e: 'update:createForm', value: PaymentPoolCreateFormState): void
    (e: 'update:editForm', value: PaymentPoolEditFormState): void
    (e: 'update:statusForm', value: PaymentPoolStatusFormState): void
    (e: 'submit:create'): void
    (e: 'submit:edit'): void
    (e: 'submit:status'): void
  }>()

  const createDialogVisible = computed({
    get: () => props.createVisible,
    set: (value: boolean) => emit('update:createVisible', value)
  })

  const editDialogVisible = computed({
    get: () => props.editVisible,
    set: (value: boolean) => emit('update:editVisible', value)
  })

  const statusDialogVisible = computed({
    get: () => props.statusVisible,
    set: (value: boolean) => emit('update:statusVisible', value)
  })

  const activePoolTypeLabel = computed(() => {
    if (!props.activePool) {
      return ''
    }

    return `${displayPoolType(props.activePool)} / ${props.activePool.type}`
  })

  function updateCreateForm(patch: Partial<PaymentPoolCreateFormState>) {
    emit('update:createForm', {
      ...props.createForm,
      ...patch
    })
  }

  function updateEditForm(patch: Partial<PaymentPoolEditFormState>) {
    emit('update:editForm', {
      ...props.editForm,
      ...patch
    })
  }

  function updateStatusForm(patch: Partial<PaymentPoolStatusFormState>) {
    emit('update:statusForm', {
      ...props.statusForm,
      ...patch
    })
  }

  const createUserId = computed({
    get: () => props.createForm.user_id,
    set: (value: string) => updateCreateForm({ user_id: value })
  })

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

  const statusEnabled = computed({
    get: () => props.statusForm.status,
    set: (value: boolean) => updateStatusForm({ status: value })
  })

  function displayPoolType(pool?: Partial<PoolListItem> | null, fallback = '--') {
    return displayAdminFixtureText(pool?.type_text || pool?.type_label || pool?.type, fallback)
  }
</script>

<style scoped lang="scss">
  .payment-pool-shell-dialog :deep(.el-dialog__header) {
    padding-bottom: 12px;
    margin-right: 0;
  }

  .payment-pool-shell-dialog :deep(.el-dialog__body) {
    padding-top: 8px;
  }

  .payment-pool-shell-dialog :deep(.el-dialog__footer) {
    padding-top: 14px;
    border-top: 1px solid var(--el-border-color-lighter);
  }

  .payment-pool-dialog-shell {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .payment-pool-dialog-shell :deep(.el-form-item) {
    margin-bottom: 18px;
  }

  .payment-pool-dialog-shell :deep(.el-form-item:last-child) {
    margin-bottom: 0;
  }

  .payment-pool-dialog-shell :deep(.el-form-item__label) {
    color: var(--el-text-color-primary);
    font-weight: 500;
  }

  .payment-pool-dialog-shell :deep(.el-input__wrapper),
  .payment-pool-dialog-shell :deep(.el-select__wrapper) {
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

  .dialog-note {
    margin: 4px 0 0;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 1.6;
  }
</style>
