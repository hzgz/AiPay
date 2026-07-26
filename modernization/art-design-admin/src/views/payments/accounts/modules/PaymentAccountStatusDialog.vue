<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <ElDialog
    v-model="visible"
    width="560px"
    class="payment-account-shell-dialog"
    destroy-on-close
    align-center
    title="更新收款账户状态"
  >
    <ElForm label-position="top" class="payment-account-dialog-shell payment-account-status-form">
      <ElFormItem label="在线状态">
        <ElSwitch
          v-model="statusForm.status"
          inline-prompt
          active-text="在线"
          inactive-text="离线"
        />
      </ElFormItem>
      <ElFormItem label="启用状态">
        <ElSwitch
          v-model="statusForm.is_status"
          inline-prompt
          active-text="启用"
          inactive-text="停用"
        />
      </ElFormItem>
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="visible = false">取消</ElButton>
        <ElButton v-if="canSubmit" type="primary" :loading="savingStatus" @click="emit('submit')">
          保存状态
        </ElButton>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { computed } from 'vue'

  interface StatusFormModel {
    status: boolean
    is_status: boolean
  }

  interface Props {
    modelValue: boolean
    statusForm: StatusFormModel
    savingStatus: boolean
    canSubmit: boolean
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void
    (e: 'submit'): void
  }>()

  const visible = computed({
    get: () => props.modelValue,
    set: (value: boolean) => emit('update:modelValue', value)
  })
</script>

<style scoped lang="scss">
  .payment-account-shell-dialog :deep(.el-dialog__header) {
    padding-bottom: 12px;
    margin-right: 0;
  }

  .payment-account-shell-dialog :deep(.el-dialog__body) {
    padding-top: 8px;
  }

  .payment-account-shell-dialog :deep(.el-dialog__footer) {
    padding-top: 14px;
    border-top: 1px solid var(--el-border-color-lighter);
  }

  .payment-account-dialog-shell {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .payment-account-dialog-shell :deep(.el-form-item) {
    margin-bottom: 18px;
  }

  .payment-account-dialog-shell :deep(.el-form-item:last-child) {
    margin-bottom: 0;
  }

  .payment-account-dialog-shell :deep(.el-form-item__label) {
    color: var(--el-text-color-primary);
    font-weight: 500;
  }

  .payment-account-status-form :deep(.el-switch) {
    min-width: 64px;
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
</style>
