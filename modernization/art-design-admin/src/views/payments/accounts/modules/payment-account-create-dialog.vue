<template>
  <ElDialog
    v-model="visible"
    width="760px"
    class="payment-account-shell-dialog"
    destroy-on-close
    align-center
    title="新增收款账户"
  >
    <ElForm label-position="top" class="payment-account-dialog-shell">
      <ElFormItem label="商户编号">
        <ElInput
          v-model="createForm.user_id"
          maxlength="12"
          inputmode="numeric"
          placeholder="请输入已存在的商户编号"
        />
      </ElFormItem>

      <div class="dialog-grid">
        <ElFormItem label="支付方式">
          <ElSelect
            v-model="createForm.payment_method_type"
            placeholder="请选择支付方式"
            @change="onPaymentMethodChange"
          >
            <ElOption
              v-for="option in paymentMethodOptions"
              :key="option.value"
              :label="option.label"
              :value="option.value"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="支付插件">
          <ElSelect
            v-model="createForm.plugin_code"
            placeholder="请选择支付插件"
            :disabled="!createForm.payment_method_type"
            @change="onPluginChange"
          >
            <ElOption
              v-for="option in filteredCreatePluginOptions"
              :key="option.code"
              :label="option.name"
              :value="option.code"
            />
          </ElSelect>
        </ElFormItem>
      </div>

      <div v-if="isCreateFormReady" class="payment-account-create-form">
        <template v-if="isAlipayOfficialCreateCode">
          <AlipayOfficialCredentialFields :model="createForm" :meta="activeCreateMeta" />
        </template>
        <template v-else-if="isWxpayV3CreateCode">
          <WxpayV3CredentialFields :model="createForm" :meta="activeCreateMeta" />
        </template>
        <template v-else>
          <ElFormItem :label="displayAccountFieldLabel(activeCreateMeta.identifierLabel)">
            <ElInput
              v-model="createForm.identifier"
              maxlength="50"
              :placeholder="displayAccountFieldPlaceholder(activeCreateMeta.identifierPlaceholder)"
            />
          </ElFormItem>
          <ElFormItem
            v-if="activeCreateMeta.supportsPid"
            :label="displayAccountFieldLabel(activeCreateMeta.pidLabel)"
          >
            <ElInput
              v-model="createForm.pid"
              maxlength="50"
              :placeholder="displayAccountFieldPlaceholder(activeCreateMeta.pidPlaceholder)"
            />
          </ElFormItem>
          <template v-if="activeCreateMeta.qrTypeOptions.length > 0">
            <ElFormItem :label="routeModeLabel">
              <ElCheckboxGroup
                v-if="isCreateMultiMode"
                v-model="selectedCreateQrTypes"
                class="interface-checkbox-group"
              >
                <ElCheckbox
                  v-for="option in activeCreateMeta.qrTypeOptions"
                  :key="option.value"
                  :label="option.value"
                  class="interface-checkbox-group__item"
                >
                  {{ option.label }}
                </ElCheckbox>
              </ElCheckboxGroup>
              <ElSelect
                v-else
                v-model="selectedCreateQrType"
                :placeholder="qrTypePlaceholder"
              >
                <ElOption
                  v-for="option in activeCreateMeta.qrTypeOptions"
                  :key="option.value"
                  :label="option.label"
                  :value="option.value"
                />
              </ElSelect>
            </ElFormItem>
          </template>
          <ElFormItem
            v-if="activeCreateMeta.supportsQrUrl && createQrUrlEditor !== 'hidden'"
            :label="displayAccountFieldLabel(activeCreateQrUrlLabel)"
          >
            <div class="credential-field-shell">
              <template v-if="createQrUrlEditor === 'image'">
                <div class="credential-field-actions">
                  <ElUpload
                    accept="image/png,image/jpeg,image/gif,image/bmp"
                    :show-file-list="false"
                    :http-request="onCredentialImageUpload"
                  >
                    <ElButton :loading="isAssetUploading('qr_url')" type="primary" plain>
                      {{ activeCreateQrImageButtonText }}
                    </ElButton>
                  </ElUpload>
                  <ElButton
                    v-if="createForm.qr_url"
                    link
                    type="danger"
                    @click="onClearField('qr_url')"
                  >
                    清空
                  </ElButton>
                </div>
                <div v-if="createForm.qr_url" class="credential-image-preview">
                  <img
                    :src="resolveCredentialAssetUrl(createForm.qr_url)"
                    :alt="activeCreateQrPreviewAlt"
                  />
                  <ElInput
                    :model-value="createForm.qr_url"
                    readonly
                    class="credential-upload-value"
                  />
                </div>
              </template>
              <template v-else-if="createQrUrlEditor === 'qr-decode'">
                <ElInput
                  v-model="createForm.qr_url"
                  type="textarea"
                  :rows="3"
                  maxlength="12000"
                  :placeholder="displayAccountFieldPlaceholder(activeCreateQrUrlPlaceholder)"
                />
                <div class="credential-field-actions">
                  <ElUpload
                    accept="image/png,image/jpeg,image/gif,image/bmp"
                    :show-file-list="false"
                    :http-request="(options) => onQrDecodeUpload(options, 'qr_url')"
                  >
                    <ElButton :loading="isAssetUploading('qr_url')" plain>
                      上传二维码并解析
                    </ElButton>
                  </ElUpload>
                  <ElButton
                    v-if="createForm.qr_url"
                    link
                    type="danger"
                    @click="onClearField('qr_url')"
                  >
                    清空
                  </ElButton>
                </div>
              </template>
              <template v-else-if="createQrUrlEditor === 'text'">
                <ElInput
                  v-model="createForm.qr_url"
                  maxlength="2500"
                  :placeholder="displayAccountFieldPlaceholder(activeCreateQrUrlPlaceholder)"
                />
              </template>
              <template v-else>
                <ElInput
                  v-model="createForm.qr_url"
                  type="textarea"
                  :rows="3"
                  maxlength="12000"
                  :placeholder="displayAccountFieldPlaceholder(activeCreateQrUrlPlaceholder)"
                />
              </template>
            </div>
          </ElFormItem>
          <ElFormItem
            v-if="activeCreateMeta.supportsCookie"
            :label="displayAccountFieldLabel(activeCreateMeta.cookieLabel)"
          >
            <ElInput
              v-model="createForm.cookie"
              type="textarea"
              :rows="3"
              maxlength="12000"
              :placeholder="displayAccountFieldPlaceholder(activeCreateMeta.cookiePlaceholder)"
            />
          </ElFormItem>
          <ElFormItem
            v-if="activeCreateMeta.supportsRemark"
            :label="displayAccountFieldLabel(activeCreateMeta.remarkLabel)"
          >
            <ElInput
              v-model="createForm.remark"
              maxlength="225"
              :placeholder="displayAccountFieldPlaceholder(activeCreateMeta.remarkPlaceholder)"
            />
          </ElFormItem>
          <ElFormItem
            v-if="activeCreateMeta.supportsWxGuid"
            :label="displayAccountFieldLabel(activeCreateMeta.wxGuidLabel)"
          >
            <ElInput
              v-model="createForm.wx_guid"
              maxlength="2500"
              :placeholder="displayAccountFieldPlaceholder(activeCreateMeta.wxGuidPlaceholder)"
            />
          </ElFormItem>
          <ElFormItem
            v-if="activeCreateMeta.supportsCloudId"
            :label="displayAccountFieldLabel(activeCreateMeta.cloudIdLabel)"
          >
            <ElInput
              v-model="createForm.cloud_id"
              maxlength="2500"
              :placeholder="displayAccountFieldPlaceholder(activeCreateMeta.cloudIdPlaceholder)"
            />
          </ElFormItem>
          <ElFormItem
            v-if="activeCreateMeta.supportsExtraValue"
            :label="displayAccountFieldLabel(activeCreateMeta.extraValueLabel)"
          >
            <div class="credential-field-shell">
              <template v-if="createExtraValueEditor === 'qr-decode'">
                <ElInput
                  v-model="createForm.extra_value"
                  type="textarea"
                  :rows="3"
                  maxlength="12000"
                  :placeholder="
                    displayAccountFieldPlaceholder(activeCreateMeta.extraValuePlaceholder)
                  "
                />
                <div class="credential-field-actions">
                  <ElUpload
                    accept="image/png,image/jpeg,image/gif,image/bmp"
                    :show-file-list="false"
                    :http-request="(options) => onQrDecodeUpload(options, 'extra_value')"
                  >
                    <ElButton :loading="isAssetUploading('extra_value')" plain>
                      上传二维码并解析
                    </ElButton>
                  </ElUpload>
                  <ElButton
                    v-if="createForm.extra_value"
                    link
                    type="danger"
                    @click="onClearField('extra_value')"
                  >
                    清空
                  </ElButton>
                </div>
              </template>
              <template v-else-if="createExtraValueEditor === 'text'">
                <ElInput
                  v-model="createForm.extra_value"
                  maxlength="2500"
                  :placeholder="
                    displayAccountFieldPlaceholder(activeCreateMeta.extraValuePlaceholder)
                  "
                />
              </template>
              <template v-else>
                <ElInput
                  v-model="createForm.extra_value"
                  type="textarea"
                  :rows="3"
                  maxlength="12000"
                  :placeholder="
                    displayAccountFieldPlaceholder(activeCreateMeta.extraValuePlaceholder)
                  "
                />
              </template>
            </div>
          </ElFormItem>
        </template>
        <div class="dialog-grid">
          <ElFormItem label="在线状态">
            <ElSwitch
              v-model="createForm.status"
              inline-prompt
              active-text="在线"
              inactive-text="离线"
            />
          </ElFormItem>
          <ElFormItem label="启用状态">
            <ElSwitch
              v-model="createForm.is_status"
              inline-prompt
              active-text="启用"
              inactive-text="停用"
            />
          </ElFormItem>
        </div>
        <ElFormItem label="内部备注（可选）">
          <ElInput
            v-model="createForm.memo"
            type="textarea"
            :rows="3"
            maxlength="50"
            placeholder="选填"
          />
        </ElFormItem>
        <div class="dialog-grid">
          <ElFormItem label="单日次数限制">
            <ElInput
              v-model="createForm.daymaxcount"
              maxlength="10"
              inputmode="numeric"
              placeholder="默认 0"
            />
          </ElFormItem>
          <ElFormItem label="单日金额限制">
            <ElInput
              v-model="createForm.daymaxmoney"
              maxlength="50"
              inputmode="decimal"
              placeholder="留空表示不限"
            />
          </ElFormItem>
          <ElFormItem label="累计次数限制">
            <ElInput
              v-model="createForm.allmaxcount"
              maxlength="10"
              inputmode="numeric"
              placeholder="默认 0"
            />
          </ElFormItem>
          <ElFormItem label="累计金额限制">
            <ElInput
              v-model="createForm.allmaxmoney"
              maxlength="50"
              inputmode="decimal"
              placeholder="留空表示不限"
            />
          </ElFormItem>
        </div>
      </div>
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="visible = false">取消</ElButton>
        <ElButton v-if="hasCreateAuth" type="primary" :loading="creatingAccount" @click="emit('submit')">
          新增账户
        </ElButton>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import type { UploadRequestOptions } from 'element-plus'
  import AlipayOfficialCredentialFields from '@/views/shared/AlipayOfficialCredentialFields.vue'
  import WxpayV3CredentialFields from '@/views/shared/WxpayV3CredentialFields.vue'
  import {
    formatModeCsv,
    isJiaofeiyiCredentialCode,
    isMultiModeCredentialCode,
    resolveQrTypeFieldPlaceholder,
    resolveQrTypeSelections,
    type PaymentAccountFieldEditor
  } from '@/views/shared/paymentAccountCredential'
  import {
    displayAccountFieldLabel,
    displayAccountFieldPlaceholder
  } from '@/views/shared/paymentAccountDisplay'
  import type {
    PaymentAccountCodeMeta,
    PaymentAccountCreateCode
  } from '@/views/shared/paymentAccountMeta'

  type AccountFieldEditor = PaymentAccountFieldEditor
  type AccountCreateCode = PaymentAccountCreateCode
  type AccountCodeMeta = PaymentAccountCodeMeta
  type UploadField = 'extra_value' | 'qr_url'

  interface PaymentMethodOption {
    value: string
    label: string
  }

  interface CreatePluginOption {
    code: AccountCreateCode
    name: string
  }

  interface CreateFormModel {
    user_id: string
    payment_method_type: string
    plugin_code: string
    code: '' | AccountCreateCode
    pid: string
    identifier: string
    qr_type: string
    qr_url: string
    cookie: string
    memo: string
    remark: string
    wx_guid: string
    cloud_id: string
    extra_value: string
    daymaxcount: string
    daymaxmoney: string
    allmaxcount: string
    allmaxmoney: string
    status: boolean
    is_status: boolean
  }

  interface Props {
    modelValue: boolean
    createForm: CreateFormModel
    hasCreateAuth: boolean
    creatingAccount: boolean
    isCreateFormReady: boolean
    paymentMethodOptions: PaymentMethodOption[]
    filteredCreatePluginOptions: CreatePluginOption[]
    activeCreateMeta: AccountCodeMeta
    createQrUrlEditor: AccountFieldEditor
    createExtraValueEditor: AccountFieldEditor
    activeCreateQrUrlLabel: string
    activeCreateQrUrlPlaceholder: string
    activeCreateQrImageButtonText: string
    activeCreateQrPreviewAlt: string
    isAssetUploading: (field: UploadField) => boolean
    onPaymentMethodChange: (value: string) => void
    onPluginChange: (value: string) => void
    onClearField: (field: UploadField) => void
    onCredentialImageUpload: (
      options: UploadRequestOptions
    ) => Promise<unknown> | XMLHttpRequest
    onQrDecodeUpload: (
      options: UploadRequestOptions,
      field: UploadField
    ) => Promise<unknown> | XMLHttpRequest
    resolveCredentialAssetUrl: (value: string) => string
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

  const isJiaofeiyiCreateCode = computed(() => isJiaofeiyiCredentialCode(props.createForm.code))
  const isAlipayOfficialCreateCode = computed(() => props.createForm.code === 'alipay_official')
  const isWxpayV3CreateCode = computed(() => props.createForm.code === 'wxpay_v3')
  const isCreateMultiMode = computed(() => isMultiModeCredentialCode(props.createForm.code))
  const qrTypePlaceholder = computed(() =>
    resolveQrTypeFieldPlaceholder(props.createForm.code)
  )
  const selectedCreateQrTypes = computed<string[]>({
    get: () =>
      resolveQrTypeSelections(
        props.createForm.code,
        props.createForm.qr_type,
        props.activeCreateMeta
      ),
    set: (value) => {
      props.createForm.qr_type = isCreateMultiMode.value ? formatModeCsv(value) : value[0] || ''
    }
  })
  const selectedCreateQrType = computed<string>({
    get: () => resolveQrTypeSelections(props.createForm.code, props.createForm.qr_type, props.activeCreateMeta)[0] || '',
    set: (value) => {
      props.createForm.qr_type = String(value || '').trim()
    }
  })
  const isJiaofeiyiWxCreateCode = computed(() => props.createForm.code === 'jiaofeiyi_wxpay')
  const routeModeLabel = computed(() =>
    isJiaofeiyiWxCreateCode.value
      ? '微信支付模式'
      : isCreateMultiMode.value
        ? '请选择可用的接口'
        : '路由模式'
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
  .payment-account-dialog-shell :deep(.el-textarea__inner),
  .payment-account-dialog-shell :deep(.el-select__wrapper) {
    border-radius: 12px;
  }

  .dialog-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .credential-field-shell {
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 100%;
  }

  .credential-field-shell :deep(.el-input),
  .credential-field-shell :deep(.el-input-number),
  .credential-field-shell :deep(.el-select),
  .credential-field-shell :deep(.el-textarea) {
    width: 100%;
  }

  .credential-field-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
  }

  .credential-field-actions :deep(.el-button) {
    min-width: 96px;
    height: 34px;
    border-radius: 10px;
  }

  .credential-field-actions :deep(.el-button--link) {
    min-width: auto;
    height: auto;
    padding: 0;
    border-radius: 0;
  }

  .credential-image-preview {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 12px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 14px;
    background: rgb(248 250 252 / 0.82);
  }

  .credential-image-preview img {
    width: 100%;
    max-width: 280px;
    max-height: 280px;
    object-fit: contain;
    border-radius: 12px;
    border: 1px solid var(--el-border-color-lighter);
    background: #fff;
  }

  .credential-upload-value :deep(.el-input__wrapper) {
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
  }

  .interface-checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 14px 18px;
    min-height: 40px;
    padding: 10px 0 4px;
  }

  .interface-checkbox-group__item {
    margin-right: 0;
  }

  .interface-checkbox-group__item :deep(.el-checkbox__label) {
    font-size: 15px;
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



