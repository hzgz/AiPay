<template>
  <ElDialog v-model="dialogVisible" width="680px" destroy-on-close align-center title="运营邮件">
    <ElForm label-position="top">
      <ElFormItem label="发送范围">
        <ElSelect v-model="scope" class="w-full">
          <ElOption
            v-for="option in scopeOptions"
            :key="option.value"
            :label="option.label"
            :value="option.value"
            :disabled="option.disabled"
          />
        </ElSelect>
      </ElFormItem>

      <ElFormItem v-if="scope === 'merchant'" label="目标商户">
        <ElAlert
          type="info"
          :closable="false"
          show-icon
          :title="scopeAlertTitle"
          :description="scopeAlertDescription"
        />
      </ElFormItem>

      <ElFormItem v-if="scope === 'direct'" label="接收邮箱">
        <ElInput v-model="email" maxlength="120" placeholder="请输入临时接收邮箱" />
      </ElFormItem>

      <ElFormItem label="邮件标题">
        <ElInput v-model="title" maxlength="120" placeholder="请输入邮件标题" />
      </ElFormItem>

      <ElFormItem label="邮件内容">
        <ElInput
          v-model="content"
          type="textarea"
          :rows="9"
          maxlength="20000"
          show-word-limit
          placeholder="支持 HTML 内容；发送前会先校验目标范围并要求确认。"
        />
      </ElFormItem>

      <ElAlert
        type="warning"
        :closable="false"
        show-icon
        :title="scope === 'all' ? '群发前会先校验发送范围' : '发送前会先校验可投递邮箱'"
        :description="
          scope === 'all'
            ? '确认弹窗会展示命中数量、跳过数量和确认短语，避免误发。'
            : '系统会先检查可投递邮箱，再要求输入确认短语后实际发送。'
        "
      />
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="dialogVisible = false">取消</ElButton>
        <ElButton type="primary" :loading="submitting" @click="emit('submit')">校验后发送</ElButton>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { computed, watch } from 'vue'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'
  import type { MerchantUserEmailFormState } from './merchantUserFormState'

  defineOptions({ name: 'MerchantUserEmailDialog' })

  type UserListItem = Api.Users.UserListItem
  type UserEmailScope = Api.Users.UserEmailScope

  interface Props {
    visible: boolean
    submitting: boolean
    form: MerchantUserEmailFormState
    activeMerchant: UserListItem | null
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:visible', value: boolean): void
    (e: 'update:form', value: MerchantUserEmailFormState): void
    (e: 'submit'): void
  }>()

  const dialogVisible = computed({
    get: () => props.visible,
    set: (value: boolean) => emit('update:visible', value)
  })

  const scopeOptions = computed<Array<{ value: UserEmailScope; label: string; disabled: boolean }>>(
    () => [
      {
        value: 'merchant',
        label: props.activeMerchant
          ? `当前商户：${displayMerchantName(props.activeMerchant)}`
          : '当前商户',
        disabled: !props.activeMerchant
      },
      {
        value: 'vip',
        label: '全部会员商户',
        disabled: false
      },
      {
        value: 'all',
        label: '全部商户',
        disabled: false
      },
      {
        value: 'direct',
        label: '直接邮箱',
        disabled: false
      }
    ]
  )

  const scopeAlertTitle = computed(() => {
    if (props.form.scope === 'merchant') {
      return props.activeMerchant
        ? `当前目标：${displayMerchantName(props.activeMerchant)}`
        : '当前还没有选中的商户目标'
    }

    if (props.form.scope === 'vip') {
      return '会员商户群发'
    }

    if (props.form.scope === 'all') {
      return '全部商户群发需要二次确认'
    }

    return '直接邮箱不会读取商户档案'
  })

  const scopeAlertDescription = computed(() => {
    if (props.form.scope === 'merchant') {
      return props.activeMerchant
        ? `将使用商户档案中的邮箱 ${displayMerchantEmail(props.activeMerchant.email, '（未配置）')} 作为收件地址；若邮箱为空或无效，会自动跳过。`
        : '请先从商户详情中进入，或切换到会员商户、全部商户、直接邮箱等范围。'
    }

    if (props.form.scope === 'vip') {
      return '会按已开通会员的商户筛选可投递邮箱，再要求输入确认短语。'
    }

    if (props.form.scope === 'all') {
      return '会面向全部商户检查邮箱命中情况。发送前会展示可投递数量、跳过数量和样例目标。'
    }

    return '适合运营测试或临时补发，不依赖商户表邮箱字段。'
  })

  function updateForm(patch: Partial<MerchantUserEmailFormState>) {
    emit('update:form', {
      ...props.form,
      ...patch,
      merchant_ids: patch.merchant_ids ? [...patch.merchant_ids] : [...props.form.merchant_ids]
    })
  }

  function handleScopeChange(value: UserEmailScope) {
    if (value === 'merchant') {
      updateForm({
        scope: value,
        merchant_ids: props.activeMerchant ? [props.activeMerchant.id] : [],
        email: ''
      })
      return
    }

    updateForm({
      scope: value,
      merchant_ids: [],
      email: value === 'direct' ? props.form.email : ''
    })
  }

  function displayMerchantName(
    merchant: Pick<UserListItem, 'id' | 'userName'> | null | undefined
  ): string {
    if (!merchant) {
      return '--'
    }

    return displayAdminFixtureText(merchant.userName) || `商户 #${merchant.id}`
  }

  function displayMerchantEmail(value: string | null | undefined, fallback = '--'): string {
    return displayAdminFixtureText(value) || fallback
  }

  watch(
    () => props.activeMerchant?.id,
    (merchantId) => {
      if (props.form.scope === 'merchant') {
        updateForm({
          merchant_ids: merchantId ? [merchantId] : []
        })
      }
    }
  )

  const scope = computed({
    get: () => props.form.scope,
    set: (value: UserEmailScope) => handleScopeChange(value)
  })

  const email = computed({
    get: () => props.form.email,
    set: (value: string) => updateForm({ email: value })
  })

  const title = computed({
    get: () => props.form.title,
    set: (value: string) => updateForm({ title: value })
  })

  const content = computed({
    get: () => props.form.content,
    set: (value: string) => updateForm({ content: value })
  })
</script>

<style scoped>
  .w-full {
    width: 100%;
  }
</style>
