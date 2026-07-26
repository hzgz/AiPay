<template>
  <ElDialog
    :model-value="editVisible"
    width="760px"
    class="channel-shell-dialog"
    destroy-on-close
    align-center
    title="编辑通道限额"
    @update:model-value="emit('update:editVisible', $event)"
  >
    <ElForm v-if="activeAccount" label-position="top" class="channel-dialog-shell">
      <section class="channel-form-summary">
        <article class="channel-form-summary__card">
          <span>支付通道</span>
          <strong>{{ accountCodeText }}</strong>
        </article>
        <article class="channel-form-summary__card">
          <span>支付类型</span>
          <strong>{{ accountTypeText }}</strong>
        </article>
        <article class="channel-form-summary__card">
          <span>当前状态</span>
          <strong>{{ accountStatusText }} / {{ accountEnabledStatusText }}</strong>
        </article>
      </section>

      <section class="channel-form-section">
        <div class="channel-form-section__head">
          <h4>运行备注</h4>
        </div>

        <ElFormItem label="备注">
          <ElInput
            v-model="editMemoModel"
            type="textarea"
            :rows="4"
            maxlength="50"
            placeholder="选填"
          />
        </ElFormItem>
      </section>

      <section class="channel-form-section">
        <div class="channel-form-section__head">
          <h4>限额策略</h4>
        </div>

        <div class="dialog-grid">
          <ElFormItem label="单日笔数限制">
            <ElInput
              v-model="editDayMaxCountModel"
              maxlength="10"
              inputmode="numeric"
              placeholder="200"
            />
          </ElFormItem>
          <ElFormItem label="单日金额限制">
            <ElInput
              v-model="editDayMaxMoneyModel"
              maxlength="50"
              inputmode="decimal"
              placeholder="5000.00 / 不限"
            />
          </ElFormItem>
          <ElFormItem label="累计笔数限制">
            <ElInput
              v-model="editAllMaxCountModel"
              maxlength="10"
              inputmode="numeric"
              placeholder="5000"
            />
          </ElFormItem>
          <ElFormItem label="累计金额限制">
            <ElInput
              v-model="editAllMaxMoneyModel"
              maxlength="50"
              inputmode="decimal"
              placeholder="100000.00 / 不限"
            />
          </ElFormItem>
        </div>
      </section>
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="emit('update:editVisible', false)">取消</ElButton>
        <ElButton
          v-if="hasEditAuth"
          type="primary"
          :loading="savingEdit"
          @click="emit('submitEdit')"
        >
          保存修改
        </ElButton>
      </div>
    </template>
  </ElDialog>

  <ElDialog
    :model-value="statusVisible"
    width="560px"
    class="channel-shell-dialog"
    destroy-on-close
    align-center
    title="更新通道状态"
    @update:model-value="emit('update:statusVisible', $event)"
  >
    <ElForm v-if="activeAccount" label-position="top" class="channel-dialog-shell">
      <section class="channel-form-summary">
        <article class="channel-form-summary__card">
          <span>支付通道</span>
          <strong>{{ accountCodeText }}</strong>
        </article>
        <article class="channel-form-summary__card">
          <span>当前在线状态</span>
          <strong>{{ accountStatusText }}</strong>
        </article>
        <article class="channel-form-summary__card">
          <span>当前启用状态</span>
          <strong>{{ accountEnabledStatusText }}</strong>
        </article>
      </section>

      <section class="channel-form-section">
        <div class="channel-form-section__head">
          <h4>状态切换</h4>
        </div>

        <ElFormItem label="在线状态">
          <ElSwitch
            v-model="statusOnlineModel"
            inline-prompt
            active-text="在线"
            inactive-text="离线"
          />
        </ElFormItem>
        <ElFormItem label="启用状态">
          <ElSwitch
            v-model="statusEnabledModel"
            inline-prompt
            active-text="启用"
            inactive-text="停用"
          />
        </ElFormItem>
      </section>
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="emit('update:statusVisible', false)">取消</ElButton>
        <ElButton
          v-if="hasStatusAuth"
          type="primary"
          :loading="savingStatus"
          @click="emit('submitStatus')"
        >
          保存状态
        </ElButton>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { computed } from 'vue'

  defineOptions({ name: 'MerchantChannelEditStatusDialogs' })

  type AccountItem = Api.Payments.AccountListItem

  interface EditFormState {
    memo: string
    daymaxcount: string
    daymaxmoney: string
    allmaxcount: string
    allmaxmoney: string
  }

  interface StatusFormState {
    status: boolean
    is_status: boolean
  }

  interface Props {
    editVisible: boolean
    statusVisible: boolean
    activeAccount: AccountItem | null
    editForm: EditFormState
    statusForm: StatusFormState
    hasEditAuth: boolean
    hasStatusAuth: boolean
    savingEdit: boolean
    savingStatus: boolean
    accountCodeText: string
    accountTypeText: string
    accountStatusText: string
    accountEnabledStatusText: string
  }

  const emit = defineEmits<{
    (e: 'update:editVisible', value: boolean): void
    (e: 'update:statusVisible', value: boolean): void
    (e: 'update:editForm', value: EditFormState): void
    (e: 'update:statusForm', value: StatusFormState): void
    (e: 'submitEdit'): void
    (e: 'submitStatus'): void
  }>()

  const props = defineProps<Props>()

  const editMemoModel = computed({
    get: () => props.editForm.memo,
    set: (value: string) => emit('update:editForm', { ...props.editForm, memo: value })
  })

  const editDayMaxCountModel = computed({
    get: () => props.editForm.daymaxcount,
    set: (value: string) => emit('update:editForm', { ...props.editForm, daymaxcount: value })
  })

  const editDayMaxMoneyModel = computed({
    get: () => props.editForm.daymaxmoney,
    set: (value: string) => emit('update:editForm', { ...props.editForm, daymaxmoney: value })
  })

  const editAllMaxCountModel = computed({
    get: () => props.editForm.allmaxcount,
    set: (value: string) => emit('update:editForm', { ...props.editForm, allmaxcount: value })
  })

  const editAllMaxMoneyModel = computed({
    get: () => props.editForm.allmaxmoney,
    set: (value: string) => emit('update:editForm', { ...props.editForm, allmaxmoney: value })
  })

  const statusOnlineModel = computed({
    get: () => props.statusForm.status,
    set: (value: boolean) => emit('update:statusForm', { ...props.statusForm, status: value })
  })

  const statusEnabledModel = computed({
    get: () => props.statusForm.is_status,
    set: (value: boolean) => emit('update:statusForm', { ...props.statusForm, is_status: value })
  })
</script>

<style scoped lang="scss">
  .dialog-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .channel-shell-dialog :deep(.el-dialog__header) {
    padding-bottom: 12px;
    margin-right: 0;
  }

  .channel-shell-dialog :deep(.el-dialog__body) {
    padding-top: 8px;
  }

  .channel-shell-dialog :deep(.el-dialog__footer) {
    padding-top: 14px;
    border-top: 1px solid var(--el-border-color-lighter);
  }

  .channel-dialog-shell {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .channel-dialog-shell :deep(.el-form-item) {
    margin-bottom: 18px;
  }

  .channel-dialog-shell :deep(.el-form-item:last-child) {
    margin-bottom: 0;
  }

  .channel-dialog-shell :deep(.el-form-item__label) {
    color: var(--el-text-color-primary);
    font-weight: 500;
  }

  .channel-dialog-shell :deep(.el-input__wrapper),
  .channel-dialog-shell :deep(.el-textarea__inner),
  .channel-dialog-shell :deep(.el-select__wrapper) {
    border-radius: 12px;
  }

  .channel-form-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
  }

  .channel-form-summary__card,
  .channel-form-section {
    padding: 14px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 16px;
    background: linear-gradient(180deg, rgb(255 255 255 / 1), rgb(248 250 252 / 0.92));
  }

  .channel-form-summary__card {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-height: 76px;
    box-shadow: 0 10px 24px rgb(15 23 42 / 0.03);
  }

  .channel-form-summary__card span {
    color: var(--el-text-color-secondary);
    font-size: 12px;
  }

  .channel-form-summary__card strong {
    color: var(--el-text-color-primary);
    font-size: 15px;
    word-break: break-word;
  }

  .channel-form-section {
    display: flex;
    flex-direction: column;
    gap: 10px;
    box-shadow: 0 10px 24px rgb(15 23 42 / 0.03);
  }

  .channel-form-section__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .channel-form-section__head h4 {
    margin: 0;
    color: var(--el-text-color-primary);
    font-size: 15px;
    font-weight: 700;
  }

  .dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
  }

  .dialog-footer :deep(.el-button) {
    min-width: 96px;
    height: 36px;
    border-radius: 10px;
  }

  :global(html.dark .channel-shell-dialog .channel-form-summary__card),
  :global(html.dark .channel-shell-dialog .channel-form-section ){
    border-color: rgb(71 85 105 / 36%);
    background: linear-gradient(180deg, rgb(15 23 42 / 90%), rgb(30 41 59 / 82%));
    box-shadow: 0 12px 28px rgb(2 6 23 / 24%);
  }

  @media (width <= 768px) {
    .channel-form-summary__card,
    .channel-form-section {
      border-radius: 16px;
    }
  }

  @media (width <= 991px) {
    .dialog-grid,
    .channel-form-summary {
      grid-template-columns: 1fr;
    }
  }
</style>
