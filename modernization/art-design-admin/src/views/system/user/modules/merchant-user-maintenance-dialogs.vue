<template>
  <ElDialog
    v-model="editDialogVisible"
    width="560px"
    destroy-on-close
    align-center
    title="编辑商户资料"
  >
    <ElForm label-position="top">
      <ElFormItem label="联系邮箱">
        <ElInput v-model="editEmail" maxlength="50" placeholder="选填，用于联系商户的邮箱" />
      </ElFormItem>
      <ElFormItem label="手机号">
        <ElInput v-model="editMobile" maxlength="50" placeholder="选填，用于联系商户的手机号" />
      </ElFormItem>
      <ElFormItem label="运营备注">
        <ElInput
          v-model="editRemarks"
          type="textarea"
          :rows="4"
          maxlength="255"
          show-word-limit
          placeholder="记录接入状态、风险提醒等内部备注"
        />
      </ElFormItem>
      <ElAlert
        type="info"
        :closable="false"
        show-icon
        title="这里只维护联系信息和内部备注，不修改密钥、余额、实名字段和套餐配置。"
      />
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

  <ElDialog
    v-model="businessDialogVisible"
    width="620px"
    destroy-on-close
    align-center
    title="VIP / 费率维护"
  >
    <ElForm label-position="top">
      <ElFormItem label="VIP 套餐">
        <ElSelect v-model="businessVipId" class="w-full">
          <ElOption
            v-for="option in vipOptions"
            :key="option.value"
            :label="displayVipOptionLabel(option)"
            :value="option.value"
            :disabled="option.disabled"
          />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="会员到期时间">
        <ElDatePicker
          v-model="businessVipTime"
          class="w-full"
          type="datetime"
          value-format="YYYY-MM-DD HH:mm:ss"
          format="YYYY-MM-DD HH:mm:ss"
          placeholder="留空时按套餐天数自动续到期时间"
        />
      </ElFormItem>
      <ElFormItem label="费率">
        <ElInput v-model="businessFeeRate" maxlength="50" placeholder="留空时按套餐默认费率回填">
          <template #append>%</template>
        </ElInput>
      </ElFormItem>
      <ElFormItem label="费率承担">
        <ElRadioGroup v-model="businessIsRate">
          <ElRadioButton :value="0">平台承担</ElRadioButton>
          <ElRadioButton :value="1">商户承担</ElRadioButton>
        </ElRadioGroup>
      </ElFormItem>
      <ElAlert
        type="warning"
        :closable="false"
        show-icon
        :title="
          selectedVipOption && selectedVipOption.value > 0
            ? `当前套餐默认费率 ${selectedVipOption.fee_rate || '--'}%，时长 ${selectedVipOption.vip_days} 天。`
            : '清空套餐会同时清空会员到期时间和商户费率。'
        "
      />
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="businessDialogVisible = false">取消</ElButton>
        <ElButton type="primary" :loading="savingBusiness" @click="emit('submit:business')">
          保存设置
        </ElButton>
      </div>
    </template>
  </ElDialog>

  <ElDialog
    v-model="notificationDialogVisible"
    width="620px"
    destroy-on-close
    align-center
    title="通知设置"
  >
    <ElForm label-position="top">
      <ElFormItem label="新订单通知">
        <ElSelect v-model="notificationOrderTips" class="w-full">
          <ElOption
            v-for="option in notificationChannelOptions"
            :key="`order-${option.value}`"
            :label="notificationOptionLabel(option)"
            :value="option.value"
            :disabled="option.value !== 'close' && (!option.enabled || !option.target_ready)"
          />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="余额不足通知">
        <ElSelect v-model="notificationMoneyChannel" class="w-full">
          <ElOption
            v-for="option in notificationChannelOptions"
            :key="`money-${option.value}`"
            :label="notificationOptionLabel(option)"
            :value="option.value"
            :disabled="option.value !== 'close' && (!option.enabled || !option.target_ready)"
          />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="余额提醒阈值">
        <ElInput
          v-model="notificationMoneyTips"
          maxlength="50"
          placeholder="商户余额低于该值时触发提醒"
        />
      </ElFormItem>
      <div class="notification-options">
        <div
          v-for="option in notificationChannelOptions.filter((item) => item.value !== 'close')"
          :key="option.value"
          class="notification-option-card"
        >
          <div class="notification-option-head">
            <strong>{{ option.label }}</strong>
            <ElTag :type="notificationOptionType(option)" effect="plain">
              {{ option.enabled ? (option.target_ready ? '可用' : '待配置') : '系统未开启' }}
            </ElTag>
          </div>
          <p>{{ option.help_text || '—' }}</p>
          <span>{{ option.requires || '无额外要求' }}</span>
        </div>
      </div>
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="notificationDialogVisible = false">取消</ElButton>
        <ElButton type="primary" :loading="savingNotification" @click="emit('submit:notification')">
          保存设置
        </ElButton>
      </div>
    </template>
  </ElDialog>

  <ElDialog
    v-model="statusDialogVisible"
    width="560px"
    destroy-on-close
    align-center
    title="商户状态维护"
  >
    <ElForm label-position="top">
      <ElFormItem label="账户状态">
        <ElSwitch v-model="statusSwitch" inline-prompt active-text="冻结" inactive-text="正常" />
      </ElFormItem>
      <ElFormItem label="冻结原因">
        <ElInput
          v-model="statusReason"
          type="textarea"
          :rows="4"
          maxlength="255"
          show-word-limit
          :disabled="!statusSwitch"
          :placeholder="
            statusSwitch
              ? '建议填写冻结原因，商户前台被拦截时会返回该提示'
              : '解除冻结后会自动清空冻结原因'
          "
        />
      </ElFormItem>
      <ElAlert :type="statusAlertType" :closable="false" show-icon :title="statusAlertTitle" />
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
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import type {
    MerchantUserBusinessFormState,
    MerchantUserEditFormState,
    MerchantUserNotificationFormState,
    MerchantUserStatusFormState
  } from './merchant-user-form-state'

  defineOptions({ name: 'MerchantUserMaintenanceDialogs' })

  type UserNotificationChannelOption = Api.Users.UserNotificationChannelOption
  type UserVipOption = Api.Users.UserVipOption

  interface Props {
    editVisible: boolean
    businessVisible: boolean
    notificationVisible: boolean
    statusVisible: boolean
    savingEdit: boolean
    savingBusiness: boolean
    savingNotification: boolean
    savingStatus: boolean
    editForm: MerchantUserEditFormState
    businessForm: MerchantUserBusinessFormState
    notificationForm: MerchantUserNotificationFormState
    statusForm: MerchantUserStatusFormState
    vipOptions: UserVipOption[]
    notificationChannelOptions: UserNotificationChannelOption[]
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:editVisible', value: boolean): void
    (e: 'update:businessVisible', value: boolean): void
    (e: 'update:notificationVisible', value: boolean): void
    (e: 'update:statusVisible', value: boolean): void
    (e: 'update:editForm', value: MerchantUserEditFormState): void
    (e: 'update:businessForm', value: MerchantUserBusinessFormState): void
    (e: 'update:notificationForm', value: MerchantUserNotificationFormState): void
    (e: 'update:statusForm', value: MerchantUserStatusFormState): void
    (e: 'submit:edit'): void
    (e: 'submit:business'): void
    (e: 'submit:notification'): void
    (e: 'submit:status'): void
  }>()

  const editDialogVisible = computed({
    get: () => props.editVisible,
    set: (value: boolean) => emit('update:editVisible', value)
  })

  const businessDialogVisible = computed({
    get: () => props.businessVisible,
    set: (value: boolean) => emit('update:businessVisible', value)
  })

  const notificationDialogVisible = computed({
    get: () => props.notificationVisible,
    set: (value: boolean) => emit('update:notificationVisible', value)
  })

  const statusDialogVisible = computed({
    get: () => props.statusVisible,
    set: (value: boolean) => emit('update:statusVisible', value)
  })

  const selectedVipOption = computed(
    () => props.vipOptions.find((item) => item.value === Number(props.businessForm.vip_id)) || null
  )

  function updateEditForm(patch: Partial<MerchantUserEditFormState>) {
    emit('update:editForm', {
      ...props.editForm,
      ...patch
    })
  }

  function updateBusinessForm(patch: Partial<MerchantUserBusinessFormState>) {
    emit('update:businessForm', {
      ...props.businessForm,
      ...patch
    })
  }

  function updateNotificationForm(patch: Partial<MerchantUserNotificationFormState>) {
    emit('update:notificationForm', {
      ...props.notificationForm,
      ...patch
    })
  }

  function updateStatusForm(patch: Partial<MerchantUserStatusFormState>) {
    emit('update:statusForm', {
      ...props.statusForm,
      ...patch
    })
  }

  function updateBusinessVipSelection(value: number) {
    const nextValue = Number(value || 0)
    const option = props.vipOptions.find((item) => item.value === nextValue)

    if (!option) {
      updateBusinessForm({ vip_id: nextValue })
      return
    }

    if (option.value === 0) {
      updateBusinessForm({
        vip_id: nextValue,
        vip_time: '',
        fee_rate: ''
      })
      return
    }

    updateBusinessForm({
      vip_id: nextValue,
      fee_rate: props.businessForm.fee_rate || option.fee_rate || ''
    })
  }

  function notificationOptionLabel(option: UserNotificationChannelOption) {
    return option.label || option.value
  }

  function displayVipOptionLabel(option: UserVipOption) {
    if (!option.label) {
      return `套餐 #${option.value}`
    }

    if (option.value === 0 && /no\s*vip\s*package/i.test(option.label)) {
      return '无会员套餐'
    }

    return option.label
  }

  function notificationOptionType(option: UserNotificationChannelOption) {
    if (!option.enabled) {
      return 'info'
    }

    if (!option.target_ready) {
      return 'warning'
    }

    return 'success'
  }

  const editEmail = computed({
    get: () => props.editForm.email,
    set: (value: string) => updateEditForm({ email: value })
  })

  const editMobile = computed({
    get: () => props.editForm.mobile,
    set: (value: string) => updateEditForm({ mobile: value })
  })

  const editRemarks = computed({
    get: () => props.editForm.remarks,
    set: (value: string) => updateEditForm({ remarks: value })
  })

  const businessVipId = computed({
    get: () => Number(props.businessForm.vip_id || 0),
    set: (value: number) => updateBusinessVipSelection(value)
  })

  const businessVipTime = computed({
    get: () => props.businessForm.vip_time,
    set: (value: string) => updateBusinessForm({ vip_time: value })
  })

  const businessFeeRate = computed({
    get: () => props.businessForm.fee_rate,
    set: (value: string) => updateBusinessForm({ fee_rate: value })
  })

  const businessIsRate = computed({
    get: () => Number(props.businessForm.is_rate || 0),
    set: (value: number) => updateBusinessForm({ is_rate: Number(value || 0) })
  })

  const notificationOrderTips = computed({
    get: () => props.notificationForm.order_tips,
    set: (value: string) => updateNotificationForm({ order_tips: value })
  })

  const notificationMoneyChannel = computed({
    get: () => props.notificationForm.is_money_tips,
    set: (value: string) => updateNotificationForm({ is_money_tips: value })
  })

  const notificationMoneyTips = computed({
    get: () => props.notificationForm.money_tips,
    set: (value: string) => updateNotificationForm({ money_tips: value })
  })

  const statusSwitch = computed({
    get: () => props.statusForm.status,
    set: (value: boolean) => updateStatusForm({ status: value })
  })

  const statusReason = computed({
    get: () => props.statusForm.frozen_reason,
    set: (value: string) => updateStatusForm({ frozen_reason: value })
  })

  const statusAlertType = computed(() => (props.statusForm.status ? 'warning' : 'success'))

  const statusAlertTitle = computed(() =>
    props.statusForm.status
      ? '冻结后，商户前台和支付接入会按现有冻结校验逻辑被拦截。'
      : '解除冻结后，将恢复商户正常接入并自动清除冻结原因。'
  )
</script>

<style scoped>
  .w-full {
    width: 100%;
  }

  .notification-option-head strong {
    color: var(--el-text-color-primary);
  }

  .notification-option-card p,
  .notification-option-card span {
    margin: 0;
    color: var(--el-text-color-secondary);
    font-size: 12px;
  }

  .notification-options {
    margin-top: 4px;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .notification-option-card {
    padding: 14px 16px;
    border-radius: 12px;
    background: var(--el-fill-color-light);
    border: 1px solid var(--el-border-color-lighter);
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .notification-option-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }

  @media (max-width: 768px) {
    .notification-options {
      grid-template-columns: 1fr;
    }

    .notification-option-head {
      align-items: flex-start;
      flex-direction: column;
    }
  }
</style>
