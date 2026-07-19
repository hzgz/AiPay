<template>
  <ElDialog
    v-model="visible"
    width="720px"
    class="payment-account-shell-dialog"
    destroy-on-close
    align-center
    title="编辑收款账户凭证"
  >
    <ElForm v-if="activeCredentialMeta" label-position="top" class="payment-account-dialog-shell">
      <ElFormItem label="支付账户">
        <ElInput :model-value="accountSummary" disabled />
      </ElFormItem>
      <AlipayOfficialCredentialFields
        v-if="isAlipayOfficialCredentialCode"
        :model="credentialForm"
        :meta="activeCredentialMeta"
      />
      <WxpayV3CredentialFields
        v-else-if="isWxpayV3CredentialCode"
        :model="credentialForm"
        :meta="activeCredentialMeta"
      />
      <template v-else>
        <ElFormItem :label="displayAccountFieldLabel(activeCredentialMeta.identifierLabel)">
          <ElInput
            v-model="credentialForm.identifier"
            maxlength="50"
            :placeholder="displayAccountFieldPlaceholder(activeCredentialMeta.identifierPlaceholder)"
          />
        </ElFormItem>
        <ElFormItem
          v-if="activeCredentialMeta.supportsPid"
          :label="displayAccountFieldLabel(activeCredentialMeta.pidLabel)"
        >
          <ElInput
            v-model="credentialForm.pid"
            maxlength="50"
            :placeholder="displayAccountFieldPlaceholder(activeCredentialMeta.pidPlaceholder)"
          />
        </ElFormItem>
        <ElFormItem v-if="activeCredentialMeta.qrTypeOptions.length > 0" :label="qrTypeFieldLabel">
          <ElCheckboxGroup
            v-if="isCredentialMultiMode"
            v-model="selectedCredentialQrTypes"
            class="interface-checkbox-group"
          >
            <ElCheckbox
              v-for="option in activeCredentialMeta.qrTypeOptions"
              :key="option.value"
              :label="option.value"
              class="interface-checkbox-group__item"
            >
              {{ option.label }}
            </ElCheckbox>
          </ElCheckboxGroup>
          <ElSelect
            v-else
            v-model="selectedCredentialQrType"
            :placeholder="qrTypeFieldPlaceholder"
          >
            <ElOption
              v-for="option in activeCredentialMeta.qrTypeOptions"
              :key="option.value"
              :label="option.label"
              :value="option.value"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem
          v-if="activeCredentialMeta.supportsQrUrl && credentialQrUrlEditor !== 'hidden'"
          :label="displayAccountFieldLabel(activeCredentialQrUrlLabel)"
        >
          <div class="credential-field-shell">
            <template v-if="credentialQrUrlEditor === 'image'">
              <div class="credential-field-actions">
                <ElUpload
                  accept="image/png,image/jpeg,image/gif,image/bmp"
                  :show-file-list="false"
                  :http-request="onCredentialImageUpload"
                >
                  <ElButton :loading="isAssetUploading('qr_url')" type="primary" plain>
                    {{ activeCredentialQrImageButtonText }}
                  </ElButton>
                </ElUpload>
                <ElButton
                  v-if="credentialForm.qr_url"
                  link
                  type="danger"
                  @click="onClearField('qr_url')"
                >
                  清空
                </ElButton>
              </div>
              <div v-if="credentialForm.qr_url" class="credential-image-preview">
                <img
                  :src="resolveCredentialAssetUrl(credentialForm.qr_url)"
                  :alt="activeCredentialQrPreviewAlt"
                />
                <ElInput
                  :model-value="credentialForm.qr_url"
                  readonly
                  class="credential-upload-value"
                />
              </div>
            </template>
            <template v-else-if="credentialQrUrlEditor === 'qr-decode'">
              <ElInput
                v-model="credentialForm.qr_url"
                type="textarea"
                :rows="3"
                maxlength="2500"
                :placeholder="displayAccountFieldPlaceholder(activeCredentialQrUrlPlaceholder)"
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
                  v-if="credentialForm.qr_url"
                  link
                  type="danger"
                  @click="onClearField('qr_url')"
                >
                  清空
                </ElButton>
              </div>
            </template>
            <template v-else-if="credentialQrUrlEditor === 'text'">
              <ElInput
                v-model="credentialForm.qr_url"
                maxlength="2500"
                :placeholder="displayAccountFieldPlaceholder(activeCredentialQrUrlPlaceholder)"
              />
            </template>
            <template v-else>
              <ElInput
                v-model="credentialForm.qr_url"
                type="textarea"
                :rows="3"
                maxlength="2500"
                :placeholder="displayAccountFieldPlaceholder(activeCredentialQrUrlPlaceholder)"
              />
            </template>
          </div>
        </ElFormItem>
        <ElFormItem
          v-if="activeCredentialMeta.supportsCookie"
          :label="displayAccountFieldLabel(activeCredentialMeta.cookieLabel)"
        >
          <ElInput
            v-model="credentialForm.cookie"
            type="textarea"
            :rows="4"
            maxlength="12000"
            :placeholder="displayAccountFieldPlaceholder(activeCredentialMeta.cookiePlaceholder)"
          />
        </ElFormItem>
        <ElFormItem
          v-if="activeCredentialMeta.supportsRemark"
          :label="displayAccountFieldLabel(activeCredentialMeta.remarkLabel)"
        >
          <ElInput
            v-model="credentialForm.remark"
            maxlength="225"
            :placeholder="displayAccountFieldPlaceholder(activeCredentialMeta.remarkPlaceholder)"
          />
        </ElFormItem>
        <ElFormItem
          v-if="activeCredentialMeta.supportsWxGuid"
          :label="displayAccountFieldLabel(activeCredentialMeta.wxGuidLabel)"
        >
          <ElInput
            v-model="credentialForm.wx_guid"
            maxlength="2500"
            :placeholder="displayAccountFieldPlaceholder(activeCredentialMeta.wxGuidPlaceholder)"
          />
        </ElFormItem>
        <ElFormItem
          v-if="activeCredentialMeta.supportsCloudId"
          :label="displayAccountFieldLabel(activeCredentialMeta.cloudIdLabel)"
        >
          <ElInput
            v-model="credentialForm.cloud_id"
            maxlength="2500"
            :placeholder="displayAccountFieldPlaceholder(activeCredentialMeta.cloudIdPlaceholder)"
          />
        </ElFormItem>
        <ElFormItem
          v-if="activeCredentialMeta.supportsExtraValue"
          :label="displayAccountFieldLabel(activeCredentialMeta.extraValueLabel)"
        >
          <div class="credential-field-shell">
            <template v-if="credentialExtraValueEditor === 'qr-decode'">
              <ElInput
                v-model="credentialForm.extra_value"
                type="textarea"
                :rows="4"
                maxlength="12000"
                :placeholder="
                  displayAccountFieldPlaceholder(activeCredentialMeta.extraValuePlaceholder)
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
                  v-if="credentialForm.extra_value"
                  link
                  type="danger"
                  @click="onClearField('extra_value')"
                >
                  清空
                </ElButton>
              </div>
            </template>
            <template v-else-if="credentialExtraValueEditor === 'text'">
              <ElInput
                v-model="credentialForm.extra_value"
                maxlength="2500"
                :placeholder="
                  displayAccountFieldPlaceholder(activeCredentialMeta.extraValuePlaceholder)
                "
              />
            </template>
            <template v-else>
              <ElInput
                v-model="credentialForm.extra_value"
                type="textarea"
                :rows="4"
                maxlength="12000"
                :placeholder="
                  displayAccountFieldPlaceholder(activeCredentialMeta.extraValuePlaceholder)
                "
              />
            </template>
          </div>
        </ElFormItem>
      </template>
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="visible = false">取消</ElButton>
        <ElButton v-if="canSubmit" type="primary" :loading="savingCredential" @click="emit('submit')">
          保存凭证
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
    isMultiModeCredentialCode,
    resolveQrTypeFieldPlaceholder,
    resolveQrTypeSelections,
    type PaymentAccountFieldEditor
  } from '@/views/shared/paymentAccountCredential'
  import { displayAccountCode, displayAccountFieldLabel, displayAccountFieldPlaceholder } from '@/views/shared/paymentAccountDisplay'
  import type { PaymentAccountCodeMeta } from '@/views/shared/paymentAccountMeta'

  type AccountFieldEditor = PaymentAccountFieldEditor
  type AccountCodeMeta = PaymentAccountCodeMeta
  type AccountItem = Api.Payments.AccountListItem
  type UploadField = 'extra_value' | 'qr_url'

  interface CredentialFormModel {
    pid: string
    identifier: string
    qr_type: string
    qr_url: string
    cookie: string
    remark: string
    wx_guid: string
    cloud_id: string
    extra_value: string
  }

  interface Props {
    modelValue: boolean
    activeAccount: AccountItem | null
    activeCredentialMeta: AccountCodeMeta | null
    credentialForm: CredentialFormModel
    credentialQrUrlEditor: AccountFieldEditor
    credentialExtraValueEditor: AccountFieldEditor
    activeCredentialQrUrlLabel: string
    activeCredentialQrUrlPlaceholder: string
    activeCredentialQrImageButtonText: string
    activeCredentialQrPreviewAlt: string
    savingCredential: boolean
    canSubmit: boolean
    isAssetUploading: (field: UploadField) => boolean
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

  const isAlipayOfficialCredentialCode = computed(
    () => props.activeAccount?.code === 'alipay_official'
  )
  const isWxpayV3CredentialCode = computed(() => props.activeAccount?.code === 'wxpay_v3')
  const accountSummary = computed(() =>
    props.activeAccount
      ? `${displayAccountCode(props.activeAccount.code_label)} / #${props.activeAccount.id}`
      : ''
  )
  const isCredentialMultiMode = computed(() =>
    isMultiModeCredentialCode(props.activeAccount?.code)
  )
  const qrTypeFieldLabel = computed(() =>
    isCredentialMultiMode.value ? '请选择可用的接口' : '路由模式'
  )
  const qrTypeFieldPlaceholder = computed(() =>
    resolveQrTypeFieldPlaceholder(props.activeAccount?.code)
  )
  const selectedCredentialQrTypes = computed<string[]>({
    get: () =>
      resolveQrTypeSelections(
        props.activeAccount?.code,
        props.credentialForm.qr_type,
        props.activeCredentialMeta
      ),
    set: (value) => {
      props.credentialForm.qr_type = isCredentialMultiMode.value ? formatModeCsv(value) : value[0] || ''
    }
  })
  const selectedCredentialQrType = computed<string>({
    get: () =>
      resolveQrTypeSelections(
        props.activeAccount?.code,
        props.credentialForm.qr_type,
        props.activeCredentialMeta
      )[0] || '',
    set: (value) => {
      props.credentialForm.qr_type = String(value || '').trim()
    }
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

  .payment-account-dialog-shell :deep(.el-input__wrapper),
  .payment-account-dialog-shell :deep(.el-textarea__inner),
  .payment-account-dialog-shell :deep(.el-select__wrapper) {
    border-radius: 12px;
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
</style>



