<template>
  <ElDialog
    v-model="dialogVisible"
    width="760px"
    destroy-on-close
    class="plugin-scaffold-dialog"
    :show-close="!creating"
    :close-on-click-modal="!creating"
    :close-on-press-escape="!creating"
    @closed="handleClosed"
  >
    <template #header>
      <div class="scaffold-dialog-head">
        <p class="scaffold-dialog-eyebrow">插件创建</p>
        <h3 class="scaffold-dialog-title">创建新的支付插件</h3>
        <p class="scaffold-dialog-desc"> 创建独立插件目录，并自动生成基础清单、脚本和清理规则。 </p>
      </div>
    </template>

    <ElSteps :active="scaffoldStep" simple class="scaffold-steps">
      <ElStep title="基础信息" description="编码、名称、来源" />
      <ElStep title="能力定义" description="能力与标签" />
      <ElStep title="清理范围" description="目录、数据表、边界" />
    </ElSteps>

    <ElForm
      ref="scaffoldFormRef"
      :model="scaffoldForm"
      :rules="scaffoldRules"
      label-position="top"
      class="scaffold-form"
    >
      <template v-if="scaffoldStep === 0">
        <div class="scaffold-form-grid">
          <ElFormItem label="插件编码" prop="code">
            <ElInput
              v-model="scaffoldForm.code"
              placeholder="epay_protocol"
              autocomplete="off"
              @blur="normalizeScaffoldCode"
            />
          </ElFormItem>
          <ElFormItem label="插件名称" prop="name">
            <ElInput v-model="scaffoldForm.name" placeholder="易支付协议插件" autocomplete="off" />
          </ElFormItem>
          <ElFormItem label="来源" prop="provider">
            <ElInput v-model="scaffoldForm.provider" placeholder="AiPay官方" autocomplete="off" />
          </ElFormItem>
          <ElFormItem label="初始版本" prop="version">
            <ElInput v-model="scaffoldForm.version" placeholder="0.1.0" autocomplete="off" />
          </ElFormItem>
        </div>

        <ElFormItem label="说明" prop="description">
          <ElInput
            v-model="scaffoldForm.description"
            type="textarea"
            :rows="4"
            resize="vertical"
            placeholder="描述该支付插件的用途，以及为什么需要这个插件。"
          />
        </ElFormItem>

        <div class="scaffold-preview-grid">
          <div class="scaffold-preview-card emphasis">
            <span>生成类名</span>
            <strong>{{ scaffoldPreviewClass }}</strong>
            <p>命名空间 {{ scaffoldPreviewNamespace }}</p>
          </div>
          <div class="scaffold-preview-card">
            <span>插件目录</span>
            <strong>{{ scaffoldPreviewDirectory }}</strong>
            <p>运行目录 {{ scaffoldPreviewRuntimeDirectory }}</p>
          </div>
          <div class="scaffold-preview-card">
            <span>命名空间数据表</span>
            <strong>{{ scaffoldPreviewConfigTable }}</strong>
            <p>{{ scaffoldPreviewLogTable }}</p>
          </div>
        </div>

        <ElAlert
          type="info"
          :closable="false"
          show-icon
          title="插件编码仅支持小写字母、数字和下划线。创建插件只会生成本地目录，不会自动安装。"
        />
      </template>

      <template v-else-if="scaffoldStep === 1">
        <ElFormItem label="能力列表" prop="capabilities">
          <ElCheckboxGroup v-model="scaffoldForm.capabilities" class="scaffold-capability-grid">
            <ElCheckbox
              v-for="option in scaffoldCapabilityOptions"
              :key="option.value"
              :label="option.value"
              class="scaffold-capability-option"
            >
              <div>
                <strong>{{ option.label }}</strong>
                <p>{{ option.description }}</p>
              </div>
            </ElCheckbox>
          </ElCheckboxGroup>
        </ElFormItem>

        <div class="scaffold-capability-toolbar">
          <ElInput
            v-model="scaffoldCustomCapability"
            placeholder="添加自定义能力，例如 reconcile"
            autocomplete="off"
            @keyup.enter="addCustomScaffoldCapability"
          >
            <template #prefix>
              <Icon icon="ri:price-tag-3-line" />
            </template>
          </ElInput>
          <ElButton plain @click="addCustomScaffoldCapability">添加能力</ElButton>
        </div>

        <div class="capability-list scaffold-selected-capabilities">
          <ElTag
            v-for="capability in scaffoldForm.capabilities"
            :key="capability"
            closable
            effect="plain"
            @close="removeScaffoldCapability(capability)"
          >
            {{ capability }}
          </ElTag>
        </div>

        <div class="scaffold-note-list">
          <div
            v-for="note in scaffoldSelectedCapabilityNotes"
            :key="note.capability"
            class="scaffold-note-card"
          >
            <div class="scaffold-note-head">
              <strong>{{ note.label }}</strong>
              <ElTag :type="note.custom ? 'warning' : 'success'" effect="plain">
                {{ note.custom ? '自定义' : '内置' }}
              </ElTag>
            </div>
            <p>{{ note.summary }}</p>
          </div>
        </div>

        <ElAlert
          type="success"
          :closable="false"
          show-icon
          title="能力定义会写入当前插件清单，便于后续维护和审核。"
        />
      </template>

      <template v-else>
        <div class="scaffold-review-grid">
          <div class="plan-card scaffold-plan-card">
            <div class="plan-head">
              <strong>插件目录</strong>
              <span>{{ scaffoldPreviewDirectory }}</span>
            </div>
            <div class="capability-list">
              <ElTag effect="plain">类名 {{ scaffoldPreviewClass }}</ElTag>
              <ElTag effect="plain" type="info"
                >运行目录 {{ scaffoldPreviewRuntimeDirectory }}</ElTag
              >
              <ElTag effect="plain" type="warning"
                >能力 {{ scaffoldForm.capabilities.length }}</ElTag
              >
            </div>
            <ul class="plan-list note-list">
              <li v-for="file in scaffoldPreviewFiles" :key="file">{{ file }}</li>
            </ul>
          </div>

          <div class="plan-card safe scaffold-plan-card">
            <div class="plan-head">
              <strong>安全清理</strong>
              <span>默认保留业务记录</span>
            </div>
            <div class="capability-list">
              <ElTag effect="plain">文件 {{ scaffoldPreviewRuntimeDirectory }}</ElTag>
              <ElTag type="success" effect="plain">数据表 {{ scaffoldPreviewConfigTable }}</ElTag>
            </div>
            <p class="plan-hook-summary">
              安全清理只会在卸载确认后，移除运行目录产物和插件专属配置记录。
            </p>
          </div>

          <div class="plan-card purge scaffold-plan-card">
            <div class="plan-head">
              <strong>彻底清理</strong>
              <span>仍需强确认口令</span>
            </div>
            <div class="capability-list">
              <ElTag type="warning" effect="plain">文件 {{ scaffoldPreviewDirectory }}</ElTag>
              <ElTag type="danger" effect="plain">数据表 {{ scaffoldPreviewLogTable }}</ElTag>
            </div>
            <p class="plan-hook-summary">
              彻底清理会在明确确认后，移除插件目录和插件专属日志表。
            </p>
          </div>

          <div class="plan-card scaffold-plan-card">
            <div class="plan-head">
              <strong>生成配置</strong>
              <span>{{ scaffoldPreviewConfigFields.length }} 个字段</span>
            </div>
            <div class="capability-list">
              <ElTag
                v-for="field in scaffoldPreviewConfigFields"
                :key="field.field"
                :type="field.required ? 'warning' : 'info'"
                effect="plain"
              >
                {{ normalizePluginCopy(field.field) }} {{ field.required ? '必填' : '可选' }}
              </ElTag>
            </div>
            <p class="plan-hook-summary"> 配置字段会按已选能力自动生成。 </p>
          </div>

          <div class="plan-card scaffold-plan-card">
            <div class="plan-head">
              <strong>运行时默认行为</strong>
              <span>已选能力保持可执行，其余能力会明确提示未接入</span>
            </div>
            <ul class="plan-list note-list">
              <li v-for="item in scaffoldPreviewRuntimeDefaults" :key="item.method">
                <span>{{ normalizePluginCopy(item.method) }}</span>
                <div class="capability-list">
                  <ElTag
                    :type="item.status === 'not_implemented' ? 'warning' : 'info'"
                    effect="plain"
                  >
                    {{ normalizePluginCopy(item.status) }}
                  </ElTag>
                  <ElTag effect="plain">{{ capabilityDisplayLabel(item.capability) }}</ElTag>
                </div>
              </li>
            </ul>
          </div>
        </div>

        <ElAlert
          type="warning"
          :closable="false"
          show-icon
          title="订单、充值、资金日志、结算数据和回调记录默认不在自动清理范围内。"
        />
      </template>
    </ElForm>

    <template #footer>
      <div class="scaffold-footer">
        <span class="scaffold-footer-copy">{{ scaffoldStepHint }}</span>
        <div class="scaffold-footer-actions">
          <ElButton :disabled="creating" @click="dialogVisible = false">取消</ElButton>
          <ElButton
            v-if="scaffoldStep > 0"
            plain
            :disabled="creating"
            @click="previousScaffoldStep"
          >
            上一步
          </ElButton>
          <ElButton
            v-if="scaffoldStep < 2"
            type="primary"
            :disabled="creating"
            @click="nextScaffoldStep"
          >
            下一步
          </ElButton>
          <ElButton v-else type="primary" :loading="creating" @click="submitScaffold">
            创建插件
          </ElButton>
        </div>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { computed, nextTick, reactive, ref } from 'vue'
  import { ElMessage } from 'element-plus'
  import type { FormInstance, FormRules } from 'element-plus'
  import { Icon } from '@iconify/vue'
  import {
    buildPaymentPluginScaffoldSubmitPayload,
    createScaffoldForm,
    scaffoldCapabilityOptions,
    usePaymentPluginScaffoldPreview,
    type PaymentPluginScaffoldForm,
    type PaymentPluginScaffoldSubmitPayload
  } from '@/views/shared/paymentPluginScaffold'
  import {
    capabilityDisplayLabel,
    normalizePluginCopy
  } from '@/views/payments/shared/paymentPluginDisplay'

  defineOptions({ name: 'PluginScaffoldDialog' })

  interface Props {
    visible: boolean
    creating: boolean
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:visible', value: boolean): void
    (e: 'submit', payload: PaymentPluginScaffoldSubmitPayload): void
  }>()

  const dialogVisible = computed({
    get: () => props.visible,
    set: (value: boolean) => emit('update:visible', value)
  })

  const scaffoldStep = ref(0)
  const scaffoldCustomCapability = ref('')
  const scaffoldFormRef = ref<FormInstance>()
  const scaffoldForm = reactive<PaymentPluginScaffoldForm>(createScaffoldForm())

  const {
    scaffoldCodeCandidate,
    scaffoldPreviewClass,
    scaffoldPreviewConfigFields,
    scaffoldPreviewConfigTable,
    scaffoldPreviewDirectory,
    scaffoldPreviewFiles,
    scaffoldPreviewLogTable,
    scaffoldPreviewNamespace,
    scaffoldPreviewRuntimeDefaults,
    scaffoldPreviewRuntimeDirectory,
    scaffoldSelectedCapabilityNotes,
    scaffoldStepHint
  } = usePaymentPluginScaffoldPreview(scaffoldForm, scaffoldStep)

  const scaffoldRules: FormRules<PaymentPluginScaffoldForm> = {
    code: [
      { required: true, message: '请输入插件编码', trigger: 'blur' },
      {
        pattern: /^[a-z0-9_]+$/,
        message: '仅支持小写字母、数字和下划线',
        trigger: 'blur'
      }
    ],
    name: [{ required: true, message: '请输入插件名称', trigger: 'blur' }],
    provider: [{ required: true, message: '请输入来源', trigger: 'blur' }],
    description: [{ required: true, message: '请输入插件说明', trigger: 'blur' }],
    version: [
      { required: true, message: '请输入初始版本', trigger: 'blur' },
      {
        pattern: /^\d+\.\d+\.\d+$/,
        message: '请使用 0.1.0 这类语义化版本号',
        trigger: 'blur'
      }
    ],
    capabilities: [
      {
        type: 'array',
        validator: (_rule, value: string[], callback) => {
          if (!Array.isArray(value) || value.length === 0) {
            callback(new Error('请至少选择一个插件能力'))
            return
          }

          callback()
        },
        trigger: 'change'
      }
    ]
  }

  const resetScaffoldDialog = () => {
    Object.assign(scaffoldForm, createScaffoldForm())
    scaffoldStep.value = 0
    scaffoldCustomCapability.value = ''
    nextTick(() => scaffoldFormRef.value?.clearValidate())
  }

  const validateScaffoldFields = async (fields: Array<keyof PaymentPluginScaffoldForm>) => {
    if (!scaffoldFormRef.value) {
      return true
    }

    try {
      await scaffoldFormRef.value.validateField(fields)
      return true
    } catch {
      return false
    }
  }

  const normalizeScaffoldCode = () => {
    scaffoldForm.code = scaffoldCodeCandidate.value
  }

  const addCustomScaffoldCapability = () => {
    const value = scaffoldCustomCapability.value.trim().toLowerCase()
    if (!value) {
      ElMessage.warning('请先输入能力标识')
      return
    }

    if (!/^[a-z0-9_]+$/.test(value)) {
      ElMessage.warning('自定义能力仅支持小写字母、数字和下划线')
      return
    }

    if (!scaffoldForm.capabilities.includes(value)) {
      scaffoldForm.capabilities = [...scaffoldForm.capabilities, value]
    }

    scaffoldCustomCapability.value = ''
    nextTick(() => scaffoldFormRef.value?.validateField('capabilities'))
  }

  const removeScaffoldCapability = (capability: string) => {
    scaffoldForm.capabilities = scaffoldForm.capabilities.filter((item) => item !== capability)
    nextTick(() => scaffoldFormRef.value?.validateField('capabilities'))
  }

  const nextScaffoldStep = async () => {
    if (scaffoldStep.value === 0) {
      const valid = await validateScaffoldFields([
        'code',
        'name',
        'provider',
        'description',
        'version'
      ])
      if (!valid) {
        return
      }
    }

    if (scaffoldStep.value === 1) {
      const valid = await validateScaffoldFields(['capabilities'])
      if (!valid) {
        return
      }
    }

    scaffoldStep.value = Math.min(scaffoldStep.value + 1, 2)
  }

  const previousScaffoldStep = () => {
    scaffoldStep.value = Math.max(scaffoldStep.value - 1, 0)
  }

  const submitScaffold = async () => {
    const valid = await validateScaffoldFields([
      'code',
      'name',
      'provider',
      'description',
      'version',
      'capabilities'
    ])

    if (!valid) {
      return
    }

    emit(
      'submit',
      buildPaymentPluginScaffoldSubmitPayload(scaffoldForm, scaffoldCodeCandidate.value)
    )
  }

  const handleClosed = () => {
    resetScaffoldDialog()
  }
</script>

<style scoped lang="scss">
  .plugin-scaffold-dialog :deep(.el-dialog__body) {
    display: flex;
    flex-direction: column;
    gap: 18px;
  }

  .scaffold-dialog-head {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .scaffold-dialog-eyebrow {
    margin: 0;
    color: #b45309;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
  }

  .scaffold-dialog-title {
    margin: 0;
    color: #0f172a;
    font-size: 28px;
    line-height: 1.1;
  }

  .scaffold-dialog-desc {
    margin: 0;
    color: #64748b;
    line-height: 1.7;
  }

  .scaffold-steps {
    border: 1px solid rgb(245 158 11 / 0.16);
    border-radius: 18px;
    background:
      radial-gradient(circle at top right, rgb(251 191 36 / 0.14), transparent 34%),
      linear-gradient(180deg, rgb(255 251 235 / 0.92), rgb(255 255 255 / 1));
  }

  .scaffold-form {
    display: flex;
    flex-direction: column;
    gap: 18px;
  }

  .scaffold-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }

  .scaffold-preview-grid,
  .scaffold-review-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
  }

  .scaffold-review-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .scaffold-preview-card {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 16px;
    border: 1px solid rgb(148 163 184 / 0.16);
    border-radius: 16px;
    background: linear-gradient(180deg, rgb(255 255 255 / 1), rgb(248 250 252 / 0.96));
    box-shadow: 0 12px 32px rgb(15 23 42 / 0.04);
  }

  .scaffold-preview-card.emphasis {
    border-color: rgb(245 158 11 / 0.24);
    background:
      radial-gradient(circle at top right, rgb(251 191 36 / 0.18), transparent 38%),
      linear-gradient(180deg, rgb(255 251 235 / 0.96), rgb(255 255 255 / 1));
  }

  .scaffold-preview-card span {
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  .scaffold-preview-card strong {
    color: #0f172a;
    font-size: 14px;
    word-break: break-all;
  }

  .scaffold-preview-card p {
    margin: 0;
    color: #475569;
    font-size: 13px;
    line-height: 1.6;
    word-break: break-all;
  }

  .scaffold-capability-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    width: 100%;
  }

  .scaffold-capability-option {
    margin-right: 0;
    align-items: flex-start;
    min-height: 100%;
    padding: 14px;
    border: 1px solid rgb(148 163 184 / 0.18);
    border-radius: 16px;
    background: linear-gradient(180deg, rgb(255 255 255 / 1), rgb(248 250 252 / 0.94));
  }

  .scaffold-capability-option :deep(.el-checkbox__label) {
    width: 100%;
    padding-left: 10px;
    white-space: normal;
  }

  .scaffold-capability-option strong {
    display: block;
    margin-bottom: 4px;
    color: #111827;
    font-size: 14px;
  }

  .scaffold-capability-option p {
    margin: 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.6;
  }

  .scaffold-capability-toolbar {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    align-items: center;
  }

  .scaffold-selected-capabilities {
    margin-top: -2px;
  }

  .scaffold-note-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .scaffold-note-card {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 14px;
    border: 1px solid rgb(148 163 184 / 0.16);
    border-radius: 16px;
    background: linear-gradient(180deg, rgb(255 255 255 / 1), rgb(248 250 252 / 0.94));
  }

  .scaffold-note-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .scaffold-note-head strong {
    color: #111827;
    text-transform: capitalize;
  }

  .scaffold-note-card p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.7;
  }

  .scaffold-plan-card {
    min-height: 100%;
  }

  .scaffold-review-grid .scaffold-plan-card:first-child {
    grid-column: 1 / -1;
    background:
      radial-gradient(circle at top right, rgb(59 130 246 / 0.12), transparent 30%),
      linear-gradient(180deg, rgb(248 250 252 / 0.98), rgb(255 255 255 / 1));
  }

  .scaffold-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
  }

  .scaffold-footer-copy {
    color: #64748b;
    font-size: 13px;
    line-height: 1.6;
  }

  .scaffold-footer-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
  }

  .capability-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .plan-card {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 14px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 16px;
  }

  .plan-card.safe {
    background: linear-gradient(180deg, rgb(240 253 244 / 0.9), rgb(255 255 255 / 1));
  }

  .plan-card.purge {
    background: linear-gradient(180deg, rgb(255 247 237 / 0.9), rgb(255 255 255 / 1));
  }

  .plan-head {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .plan-head strong {
    color: #111827;
  }

  .plan-head span {
    color: #6b7280;
    font-size: 13px;
  }

  .plan-hook-summary {
    margin: -4px 0 0;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.6;
  }

  .plan-list {
    margin: 0;
    padding-left: 18px;
    color: #374151;
    line-height: 1.7;
  }

  .note-list li {
    display: list-item;
  }

  @media (width <= 991px) {
    .scaffold-form-grid,
    .scaffold-preview-grid,
    .scaffold-capability-grid,
    .scaffold-review-grid,
    .scaffold-note-list {
      grid-template-columns: 1fr;
    }

    .scaffold-capability-toolbar,
    .scaffold-footer {
      grid-template-columns: 1fr;
      flex-direction: column;
      align-items: flex-start;
    }

    .scaffold-footer-actions {
      justify-content: flex-start;
    }
  }
</style>
