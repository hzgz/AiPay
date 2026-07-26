<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <ElDialog
    v-model="visible"
    width="620px"
    class="payment-account-shell-dialog"
    destroy-on-close
    align-center
    title="编辑收款账户限额"
  >
    <ElForm label-position="top" class="payment-account-dialog-shell">
      <ElFormItem label="收款账户">
        <ElInput :model-value="accountSummary" disabled />
      </ElFormItem>
      <ElFormItem label="内部备注">
        <ElInput
          v-model="editForm.memo"
          type="textarea"
          :rows="4"
          maxlength="50"
          placeholder="选填"
        />
      </ElFormItem>
      <div class="dialog-grid">
        <ElFormItem label="单日次数限制">
          <ElInput
            v-model="editForm.daymaxcount"
            maxlength="10"
            inputmode="numeric"
            placeholder="例如：200"
          />
        </ElFormItem>
        <ElFormItem label="单日金额限制">
          <ElInput
            v-model="editForm.daymaxmoney"
            maxlength="50"
            inputmode="decimal"
            placeholder="例如：5000.00，留空表示不限"
          />
        </ElFormItem>
        <ElFormItem label="累计次数限制">
          <ElInput
            v-model="editForm.allmaxcount"
            maxlength="10"
            inputmode="numeric"
            placeholder="例如：5000"
          />
        </ElFormItem>
        <ElFormItem label="累计金额限制">
          <ElInput
            v-model="editForm.allmaxmoney"
            maxlength="50"
            inputmode="decimal"
            placeholder="例如：100000.00，留空表示不限"
          />
        </ElFormItem>
      </div>
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="visible = false">取消</ElButton>
        <ElButton v-if="canSubmit" type="primary" :loading="savingEdit" @click="emit('submit')">
          保存修改
        </ElButton>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import { displayAccountCode } from '@/views/shared/paymentAccountDisplay'

  type AccountItem = Api.Payments.AccountListItem

  interface EditFormModel {
    memo: string
    daymaxcount: string
    daymaxmoney: string
    allmaxcount: string
    allmaxmoney: string
  }

  interface Props {
    modelValue: boolean
    activeAccount: AccountItem | null
    editForm: EditFormModel
    savingEdit: boolean
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

  const accountSummary = computed(() =>
    props.activeAccount
      ? `${displayAccountCode(props.activeAccount.code_label)} / #${props.activeAccount.id}`
      : ''
  )
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

  .payment-account-dialog-shell :deep(.el-input__wrapper),
  .payment-account-dialog-shell :deep(.el-textarea__inner) {
    border-radius: 12px;
  }

  .dialog-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
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

  @media (width <= 991px) {
    .dialog-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
