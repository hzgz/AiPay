<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <ElDialog v-model="dialogVisible" width="620px" destroy-on-close align-center title="新建商户">
    <div v-loading="loading">
      <ElForm label-position="top">
        <ElFormItem label="商户账号">
          <ElInput v-model="username" maxlength="50" placeholder="请输入用于商户中心登录的账号" />
        </ElFormItem>
        <ElFormItem label="登录密码">
          <ElInput
            v-model="password"
            maxlength="50"
            show-password
            placeholder="请输入商户初始登录密码"
          />
        </ElFormItem>
        <ElFormItem label="联系邮箱">
          <ElInput v-model="email" maxlength="50" placeholder="选填，用于接收通知和联系商户" />
        </ElFormItem>
        <ElFormItem label="手机号">
          <ElInput v-model="mobile" maxlength="50" placeholder="选填，用于联系商户" />
        </ElFormItem>
        <ElFormItem label="运营备注">
          <ElInput
            v-model="remarks"
            type="textarea"
            :rows="3"
            maxlength="255"
            show-word-limit
            placeholder="记录来源、风险说明等内部备注"
          />
        </ElFormItem>
        <ElFormItem label="VIP 套餐">
          <ElSelect v-model="vipId" class="w-full">
            <ElOption
              v-for="option in vipOptions"
              :key="`create-${option.value}`"
              :label="displayVipOptionLabel(option)"
              :value="option.value"
              :disabled="option.disabled"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="会员到期时间">
          <ElDatePicker
            v-model="vipTime"
            class="w-full"
            type="datetime"
            value-format="YYYY-MM-DD HH:mm:ss"
            format="YYYY-MM-DD HH:mm:ss"
            placeholder="留空时按套餐天数自动生成到期时间"
          />
        </ElFormItem>
        <ElFormItem label="费率">
          <ElInput v-model="feeRate" maxlength="50" placeholder="留空时按套餐默认费率回填">
            <template #append>%</template>
          </ElInput>
        </ElFormItem>
        <ElFormItem label="费率承担">
          <ElRadioGroup v-model="isRate">
            <ElRadioButton :value="0">平台承担</ElRadioButton>
            <ElRadioButton :value="1">商户承担</ElRadioButton>
          </ElRadioGroup>
        </ElFormItem>
        <ElAlert
          type="info"
          :closable="false"
          show-icon
          :title="
            selectedVipOption && selectedVipOption.value > 0
              ? `创建后将同步写入默认费率 ${selectedVipOption.fee_rate || '--'}%，时长 ${selectedVipOption.vip_days} 天。`
              : '创建时会自动生成通讯密钥和签名密钥；未选择套餐时不会写入会员费率与到期时间。'
          "
        />
      </ElForm>
    </div>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="dialogVisible = false">取消</ElButton>
        <ElButton type="primary" :loading="submitting" @click="emit('submit')">创建商户</ElButton>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import type { MerchantUserCreateFormState } from './merchantUserFormState'

  defineOptions({ name: 'MerchantUserCreateDialog' })

  type UserVipOption = Api.Users.UserVipOption

  interface Props {
    visible: boolean
    loading: boolean
    submitting: boolean
    form: MerchantUserCreateFormState
    vipOptions: UserVipOption[]
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:visible', value: boolean): void
    (e: 'update:form', value: MerchantUserCreateFormState): void
    (e: 'submit'): void
  }>()

  const dialogVisible = computed({
    get: () => props.visible,
    set: (value: boolean) => emit('update:visible', value)
  })

  const selectedVipOption = computed(
    () => props.vipOptions.find((item) => item.value === Number(props.form.vip_id)) || null
  )

  function updateForm(patch: Partial<MerchantUserCreateFormState>) {
    emit('update:form', {
      ...props.form,
      ...patch
    })
  }

  function updateVipSelection(value: number) {
    const nextValue = Number(value || 0)
    const option = props.vipOptions.find((item) => item.value === nextValue)

    if (!option) {
      updateForm({ vip_id: nextValue })
      return
    }

    if (option.value === 0) {
      updateForm({
        vip_id: nextValue,
        vip_time: '',
        fee_rate: ''
      })
      return
    }

    updateForm({
      vip_id: nextValue,
      fee_rate: props.form.fee_rate || option.fee_rate || ''
    })
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

  const username = computed({
    get: () => props.form.username,
    set: (value: string) => updateForm({ username: value })
  })

  const password = computed({
    get: () => props.form.password,
    set: (value: string) => updateForm({ password: value })
  })

  const email = computed({
    get: () => props.form.email,
    set: (value: string) => updateForm({ email: value })
  })

  const mobile = computed({
    get: () => props.form.mobile,
    set: (value: string) => updateForm({ mobile: value })
  })

  const remarks = computed({
    get: () => props.form.remarks,
    set: (value: string) => updateForm({ remarks: value })
  })

  const vipId = computed({
    get: () => Number(props.form.vip_id || 0),
    set: (value: number) => updateVipSelection(value)
  })

  const vipTime = computed({
    get: () => props.form.vip_time,
    set: (value: string) => updateForm({ vip_time: value })
  })

  const feeRate = computed({
    get: () => props.form.fee_rate,
    set: (value: string) => updateForm({ fee_rate: value })
  })

  const isRate = computed({
    get: () => Number(props.form.is_rate || 0),
    set: (value: number) => updateForm({ is_rate: Number(value || 0) })
  })
</script>

<style scoped>
  .w-full {
    width: 100%;
  }
</style>
