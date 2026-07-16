<template>
  <ElDialog
    v-model="statusDialogVisible"
    width="520px"
    destroy-on-close
    align-center
    title="修改套餐状态"
  >
    <ElForm label-position="top">
      <ElFormItem label="状态">
        <ElSwitch v-model="statusValue" inline-prompt active-text="启用" inactive-text="停用" />
      </ElFormItem>
      <ElAlert
        :type="statusForm.status ? 'success' : 'warning'"
        :closable="false"
        show-icon
        :title="statusForm.status ? '当前套餐重新恢复可用。' : '当前套餐将停止用于新的分配。'"
      />
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="statusDialogVisible = false">取消</ElButton>
        <ElButton type="primary" :loading="savingStatus" @click="emit('submit:status')">
          保存状态
        </ElButton>
      </div>
    </template>
  </ElDialog>

  <ElDialog
    v-model="sortDialogVisible"
    width="520px"
    destroy-on-close
    align-center
    title="修改排序值"
  >
    <ElForm label-position="top">
      <ElFormItem label="排序值" required>
        <ElInput
          v-model="sortValue"
          maxlength="10"
          inputmode="numeric"
          placeholder="请输入非负整数"
        />
      </ElFormItem>
      <ElAlert
        type="info"
        :closable="false"
        show-icon
        title="数值越小越靠前。拖拽表格排序时，也会同步重写当前可见数据的排序值。"
      />
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="sortDialogVisible = false">取消</ElButton>
        <ElButton type="primary" :loading="savingSort" @click="emit('submit:sort')">
          保存排序
        </ElButton>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import type { VipSortFormState, VipStatusFormState } from './vip-form-state'

  defineOptions({ name: 'VipStatusSortDialogs' })

  interface Props {
    statusVisible: boolean
    sortVisible: boolean
    savingStatus: boolean
    savingSort: boolean
    statusForm: VipStatusFormState
    sortForm: VipSortFormState
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:statusVisible', value: boolean): void
    (e: 'update:sortVisible', value: boolean): void
    (e: 'update:statusForm', value: VipStatusFormState): void
    (e: 'update:sortForm', value: VipSortFormState): void
    (e: 'submit:status'): void
    (e: 'submit:sort'): void
  }>()

  const statusDialogVisible = computed({
    get: () => props.statusVisible,
    set: (value: boolean) => emit('update:statusVisible', value)
  })

  const sortDialogVisible = computed({
    get: () => props.sortVisible,
    set: (value: boolean) => emit('update:sortVisible', value)
  })

  const statusValue = computed({
    get: () => props.statusForm.status,
    set: (value: boolean) =>
      emit('update:statusForm', {
        ...props.statusForm,
        status: value
      })
  })

  const sortValue = computed({
    get: () => props.sortForm.sort,
    set: (value: string) =>
      emit('update:sortForm', {
        ...props.sortForm,
        sort: value
      })
  })
</script>

<style scoped>
  .dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
  }
</style>
