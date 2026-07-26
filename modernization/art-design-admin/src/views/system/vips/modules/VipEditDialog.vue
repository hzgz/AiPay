<template>
  <ElDialog
    v-model="dialogVisible"
    width="680px"
    destroy-on-close
    align-center
    :title="mode === 'create' ? '新增会员套餐' : '编辑会员套餐'"
  >
    <ElForm v-loading="loading" label-position="top">
      <div class="dialog-grid">
        <ElFormItem label="套餐名称" required>
          <ElInput v-model="name" maxlength="50" show-word-limit placeholder="请输入会员套餐名称" />
        </ElFormItem>
        <ElFormItem label="套餐价格" required>
          <ElInput v-model="money" maxlength="12" inputmode="decimal" placeholder="例如 199.00" />
        </ElFormItem>
        <ElFormItem label="会员天数" required>
          <ElInput v-model="vipDays" maxlength="10" inputmode="numeric" placeholder="例如 30" />
        </ElFormItem>
        <ElFormItem label="费率 (%)" required>
          <ElInput v-model="feeRate" maxlength="50" inputmode="decimal" placeholder="例如 0.6" />
        </ElFormItem>
        <ElFormItem label="排序值" required>
          <ElInput v-model="sort" maxlength="10" inputmode="numeric" placeholder="数值越小越靠前" />
        </ElFormItem>
        <ElFormItem label="赠送通道数">
          <ElInput
            v-model="addChannelNum"
            :disabled="!addChannelEnabled"
            maxlength="10"
            inputmode="numeric"
            placeholder="关闭后将按 0 保存"
          />
        </ElFormItem>
      </div>

      <div class="switch-grid">
        <ElFormItem label="分润">
          <ElSwitch v-model="profitEnabled" inline-prompt active-text="开" inactive-text="关" />
        </ElFormItem>
        <ElFormItem label="赠送通道">
          <ElSwitch v-model="addChannelEnabled" inline-prompt active-text="开" inactive-text="关" />
        </ElFormItem>
        <ElFormItem label="额度控制">
          <ElSwitch v-model="quotaEnabled" inline-prompt active-text="开" inactive-text="关" />
        </ElFormItem>
        <ElFormItem label="通道限制">
          <ElSwitch v-model="passageEnabled" inline-prompt active-text="开" inactive-text="关" />
        </ElFormItem>
      </div>

      <div class="dialog-grid">
        <ElFormItem label="日额度">
          <ElInput
            v-model="todayQuota"
            :disabled="!quotaEnabled"
            maxlength="20"
            inputmode="decimal"
            placeholder="开启额度控制后必填"
          />
        </ElFormItem>
        <ElFormItem label="月额度">
          <ElInput
            v-model="monthQuota"
            :disabled="!quotaEnabled"
            maxlength="20"
            inputmode="decimal"
            placeholder="可选"
          />
        </ElFormItem>
      </div>

      <div class="passage-editor">
        <div class="passage-editor__header">
          <div>
            <h4>通道限制</h4>
            <p>请选择当前会员套餐允许使用的通道编码。</p>
          </div>
          <ElTag :type="form.passage_enabled ? 'primary' : 'info'" effect="plain">
            {{ form.passage_enabled ? `已选 ${form.passage_codes.length}` : '未启用' }}
          </ElTag>
        </div>

        <ElTreeSelect
          v-model="passageCodes"
          class="passage-editor__tree"
          :data="passageOptionGroups"
          :disabled="!form.passage_enabled"
          multiple
          show-checkbox
          check-strictly
          collapse-tags
          collapse-tags-tooltip
          clearable
          filterable
          default-expand-all
          node-key="value"
          :props="passageTreeProps"
          placeholder="请选择允许使用的通道编码"
        />

        <div v-if="selectedPassageOptions.length" class="passage-editor__selected">
          <ElTag
            v-for="option in selectedPassageOptions"
            :key="option.value"
            size="small"
            effect="plain"
            :type="option.status === 1 ? 'success' : 'warning'"
          >
            {{ option.label }} / {{ option.code }}
          </ElTag>
        </div>

        <ElAlert
          v-else
          type="info"
          :closable="false"
          show-icon
          :title="
            form.passage_enabled ? '启用通道限制后，至少要选择一个通道。' : '当前未启用通道限制。'
          "
        />
      </div>
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="dialogVisible = false">取消</ElButton>
        <ElButton type="primary" :loading="submitting || loading" @click="emit('submit')">
          {{ mode === 'create' ? '创建套餐' : '保存修改' }}
        </ElButton>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import type { VipDialogMode, VipEditFormState } from './vipFormState'

  defineOptions({ name: 'VipEditDialog' })

  type VipPassageOption = Api.Vips.VipPassageOption
  type VipPassageOptionGroup = Api.Vips.VipPassageOptionGroup

  interface Props {
    visible: boolean
    loading: boolean
    submitting: boolean
    mode: VipDialogMode
    form: VipEditFormState
    passageOptionGroups: VipPassageOptionGroup[]
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:visible', value: boolean): void
    (e: 'update:form', value: VipEditFormState): void
    (e: 'submit'): void
  }>()

  const passageTreeProps = {
    label: 'label',
    value: 'value',
    children: 'children',
    disabled: 'disabled'
  } as const

  const dialogVisible = computed({
    get: () => props.visible,
    set: (value: boolean) => emit('update:visible', value)
  })

  const selectedPassageOptions = computed<VipPassageOption[]>(() => {
    const optionMap = new Map<string, VipPassageOption>()

    for (const group of props.passageOptionGroups) {
      for (const option of group.children || []) {
        optionMap.set(option.value, option)
      }
    }

    return props.form.passage_codes
      .map((code) => optionMap.get(code))
      .filter((option): option is VipPassageOption => Boolean(option))
  })

  function updateForm(patch: Partial<VipEditFormState>) {
    emit('update:form', {
      ...props.form,
      ...patch,
      passage_codes: patch.passage_codes ? [...patch.passage_codes] : [...props.form.passage_codes]
    })
  }

  const name = computed({
    get: () => props.form.name,
    set: (value: string) => updateForm({ name: value })
  })

  const money = computed({
    get: () => props.form.money,
    set: (value: string) => updateForm({ money: value })
  })

  const vipDays = computed({
    get: () => props.form.vip_days,
    set: (value: string) => updateForm({ vip_days: value })
  })

  const feeRate = computed({
    get: () => props.form.fee_rate,
    set: (value: string) => updateForm({ fee_rate: value })
  })

  const sort = computed({
    get: () => props.form.sort,
    set: (value: string) => updateForm({ sort: value })
  })

  const profitEnabled = computed({
    get: () => props.form.profit_enabled,
    set: (value: boolean) => updateForm({ profit_enabled: value })
  })

  const addChannelEnabled = computed({
    get: () => props.form.add_channel_enabled,
    set: (value: boolean) => updateForm({ add_channel_enabled: value })
  })

  const quotaEnabled = computed({
    get: () => props.form.quota_enabled,
    set: (value: boolean) => updateForm({ quota_enabled: value })
  })

  const passageEnabled = computed({
    get: () => props.form.passage_enabled,
    set: (value: boolean) => updateForm({ passage_enabled: value })
  })

  const addChannelNum = computed({
    get: () => props.form.add_channel_num,
    set: (value: string) => updateForm({ add_channel_num: value })
  })

  const todayQuota = computed({
    get: () => props.form.today_quota,
    set: (value: string) => updateForm({ today_quota: value })
  })

  const monthQuota = computed({
    get: () => props.form.month_quota,
    set: (value: string) => updateForm({ month_quota: value })
  })

  const passageCodes = computed({
    get: () => props.form.passage_codes,
    set: (value: string[]) => updateForm({ passage_codes: value })
  })
</script>

<style scoped lang="scss">
  .dialog-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 16px;
  }

  .switch-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    column-gap: 16px;
  }

  .passage-editor {
    --vip-editor-border: var(--el-border-color-lighter);
    --vip-editor-bg: linear-gradient(180deg, rgb(248 250 252 / 0.96), rgb(241 245 249 / 0.72));
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 4px;
    padding: 16px;
    border: 1px solid var(--vip-editor-border);
    border-radius: 16px;
    background: var(--vip-editor-bg);
  }

  :global(html.dark .passage-editor ){
    --vip-editor-border: rgb(71 85 105 / 0.42);
    --vip-editor-bg: linear-gradient(180deg, rgb(15 23 42 / 0.86), rgb(30 41 59 / 0.74));
  }

  .passage-editor__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
  }

  .passage-editor__header h4 {
    margin: 0 0 4px;
    color: var(--el-text-color-primary);
    font-size: 14px;
  }

  .passage-editor__header p {
    margin: 0;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 1.6;
  }

  .passage-editor__tree {
    width: 100%;
  }

  .passage-editor__selected {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
  }

  @media (width <= 991px) {
    .dialog-grid,
    .switch-grid {
      grid-template-columns: 1fr;
    }

    .passage-editor__header {
      flex-direction: column;
      align-items: stretch;
    }
  }
</style>
