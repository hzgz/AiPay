<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <ElDialog
    :model-value="visible"
    width="720px"
    class="channel-shell-dialog"
    destroy-on-close
    align-center
    title="编辑通道凭证"
    @update:model-value="handleVisibilityChange"
  >
    <ElForm
      v-if="activeCredentialMeta && activeAccount"
      label-position="top"
      class="channel-dialog-shell"
    >
      <section class="channel-form-section">
        <div class="channel-form-section__head">
          <h4>
            {{
              isAlipayOfficialCredentialCode
                ? '支付宝官方 V3 配置'
                : isWxpayV3CredentialCode
                  ? '微信官方 V3 配置'
                  : '基础凭证'
            }}
          </h4>
        </div>

        <AlipayOfficialCredentialFields
          v-if="isAlipayOfficialCredentialCode"
          :model="credentialForm"
          :meta="activeCredentialMeta"
          compact-placeholder
        />
        <WxpayV3CredentialFields
          v-else-if="isWxpayV3CredentialCode"
          :model="credentialForm"
          :meta="activeCredentialMeta"
          compact-placeholder
        />
        <template v-else>
          <ElFormItem
            v-if="isJiaofeiyiCredentialCodeActive && activeCredentialMeta.supportsPid"
            :label="jiaofeiyiMerchantIdLabel"
          >
            <ElInput
              v-model="credentialForm.pid"
              maxlength="50"
              :placeholder="jiaofeiyiMerchantIdPlaceholder"
            />
          </ElFormItem>
          <ElFormItem
            v-if="showCredentialIdentifierField"
            :label="displayAccountFieldLabel(activeCredentialMeta?.identifierLabel || '')"
          >
            <ElInput
              v-model="credentialForm.identifier"
              maxlength="50"
              :placeholder="displayAccountFieldPlaceholder(activeCredentialMeta?.identifierPlaceholder || '')"
            />
          </ElFormItem>
          <ElFormItem
            v-if="activeCredentialMeta.supportsPid && !isJiaofeiyiCredentialCodeActive"
            :label="displayAccountFieldLabel(activeCredentialMeta.pidLabel)"
          >
            <ElInput
              v-model="credentialForm.pid"
              maxlength="50"
              :placeholder="displayAccountFieldPlaceholder(activeCredentialMeta.pidPlaceholder)"
            />
          </ElFormItem>
          <ElFormItem v-if="activeCredentialMeta.qrTypeOptions.length > 0" :label="routeModeLabel">
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
            <ElSelect v-else v-model="selectedCredentialQrType" :placeholder="qrTypePlaceholder">
              <ElOption
                v-for="option in activeCredentialMeta.qrTypeOptions"
                :key="option.value"
                :label="option.label"
                :value="option.value"
              />
            </ElSelect>
          </ElFormItem>
        </template>
      </section>

      <section
        v-if="!isAlipayOfficialCredentialCode && !isWxpayV3CredentialCode"
        class="channel-form-section"
      >
        <div class="channel-form-section__head">
          <h4>凭证内容</h4>
        </div>

        <ElFormItem
          v-if="isJiaofeiyiCredentialCodeActive && activeCredentialMeta.supportsCookie"
          :label="jiaofeiyiStoreNameLabel"
        >
          <ElInput
            v-model="credentialForm.cookie"
            maxlength="12000"
            :placeholder="jiaofeiyiStoreNamePlaceholder"
          />
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
                  :http-request="handleCredentialImageUpload"
                >
                  <ElButton :loading="isQrUrlUploading" type="primary" plain>
                    {{ activeCredentialQrImageButtonText }}
                  </ElButton>
                </ElUpload>
                <ElButton
                  v-if="credentialForm.qr_url"
                  link
                  type="danger"
                  @click="clearCredentialField('qr_url')"
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
                  :http-request="handleQrUrlDecodeUpload"
                >
                  <ElButton :loading="isQrUrlUploading" plain>上传二维码并解析</ElButton>
                </ElUpload>
                <ElButton
                  v-if="credentialForm.qr_url"
                  link
                  type="danger"
                  @click="clearCredentialField('qr_url')"
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
          v-if="activeCredentialMeta.supportsCookie && !isJiaofeiyiCredentialCodeActive"
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
                  :http-request="handleExtraValueDecodeUpload"
                >
                  <ElButton :loading="isExtraValueUploading" plain>上传二维码并解析</ElButton>
                </ElUpload>
                <ElButton
                  v-if="credentialForm.extra_value"
                  link
                  type="danger"
                  @click="clearCredentialField('extra_value')"
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
      </section>
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="emit('update:visible', false)">取消</ElButton>
        <ElButton
          v-if="hasEditAuth"
          type="primary"
          :loading="savingCredential"
          @click="emit('submit')"
        >
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
    isJiaofeiyiCredentialCode,
    isMultiModeCredentialCode,
    resolveQrTypeFieldPlaceholder,
    resolveQrTypeSelections,
    shouldShowAccountIdentifierField,
    type PaymentAccountCredentialField,
    type PaymentAccountFieldEditor
  } from '@/views/shared/paymentAccountCredential'
  import type { PaymentAccountCodeMeta } from '@/views/shared/paymentAccountMeta'
  import {
    displayAccountFieldLabel,
    displayAccountFieldPlaceholderCompact as displayAccountFieldPlaceholder
  } from '@/views/shared/paymentAccountDisplay'

  type AccountItem = Api.Payments.AccountListItem

  interface CredentialForm {
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
    visible: boolean
    activeAccount: AccountItem | null
    activeCredentialMeta: PaymentAccountCodeMeta | null
    credentialForm: CredentialForm
    credentialQrUrlEditor: PaymentAccountFieldEditor | string
    credentialExtraValueEditor: PaymentAccountFieldEditor | string
    activeCredentialQrUrlLabel: string
    activeCredentialQrUrlPlaceholder: string
    activeCredentialQrImageButtonText: string
    activeCredentialQrPreviewAlt: string
    savingCredential: boolean
    hasEditAuth: boolean
    isAssetUploading: (scope: 'credential', field: PaymentAccountCredentialField) => boolean
    resolveCredentialAssetUrl: (value: string) => string
    handleCredentialImageUploadRequest: (
      options: UploadRequestOptions,
      scope: 'credential'
    ) => void | Promise<void>
    handleQrDecodeUploadRequest: (
      options: UploadRequestOptions,
      scope: 'credential',
      field: PaymentAccountCredentialField
    ) => void | Promise<void>
    clearScopedCredentialField: (scope: 'credential', field: PaymentAccountCredentialField) => void
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:visible', value: boolean): void
    (e: 'submit'): void
  }>()

  const isQrUrlUploading = computed(() => props.isAssetUploading('credential', 'qr_url'))
  const isExtraValueUploading = computed(() => props.isAssetUploading('credential', 'extra_value'))
  const isJiaofeiyiCredentialCodeActive = computed(() =>
    isJiaofeiyiCredentialCode(props.activeAccount?.code)
  )
  const isAlipayOfficialCredentialCode = computed(
    () => props.activeAccount?.code === 'alipay_official'
  )
  const isWxpayV3CredentialCode = computed(() => props.activeAccount?.code === 'wxpay_v3')
  const isCredentialMultiMode = computed(() => isMultiModeCredentialCode(props.activeAccount?.code))
  const showCredentialIdentifierField = computed(() =>
    shouldShowAccountIdentifierField(props.activeAccount?.code, props.credentialForm.qr_type)
  )
  const qrTypePlaceholder = computed(() => resolveQrTypeFieldPlaceholder(props.activeAccount?.code))
  const selectedCredentialQrTypes = computed<string[]>({
    get: () =>
      resolveQrTypeSelections(
        props.activeAccount?.code,
        props.credentialForm.qr_type,
        props.activeCredentialMeta
      ),
    set: (value) => {
      props.credentialForm.qr_type = isCredentialMultiMode.value
        ? formatModeCsv(value)
        : value[0] || ''
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
  const routeModeLabel = computed(() =>
    props.activeAccount?.code === 'jiaofeiyi_wxpay'
      ? '微信支付模式'
      : isCredentialMultiMode.value
        ? '请选择可用的接口'
        : '路由模式'
  )
  const jiaofeiyiMerchantIdLabel = computed(() => '商户ID')
  const jiaofeiyiMerchantIdPlaceholder = computed(() => '请输入商户ID')
  const jiaofeiyiStoreNameLabel = computed(() => '店铺名')
  const jiaofeiyiStoreNamePlaceholder = computed(() => '可填写店铺名称')

  const handleVisibilityChange = (value: boolean) => emit('update:visible', value)
  const clearCredentialField = (field: PaymentAccountCredentialField) =>
    props.clearScopedCredentialField('credential', field)
  const handleCredentialImageUpload = (options: UploadRequestOptions) =>
    Promise.resolve(props.handleCredentialImageUploadRequest(options, 'credential'))
  const handleQrUrlDecodeUpload = (options: UploadRequestOptions) =>
    Promise.resolve(props.handleQrDecodeUploadRequest(options, 'credential', 'qr_url'))
  const handleExtraValueDecodeUpload = (options: UploadRequestOptions) =>
    Promise.resolve(props.handleQrDecodeUploadRequest(options, 'credential', 'extra_value'))
</script>

<style scoped lang="scss">
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

  .channel-form-section {
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
  }

  .channel-form-section {
    display: flex;
    flex-direction: column;
    gap: 10px;
    box-shadow: none;
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
    background: linear-gradient(180deg, rgb(255 255 255 / 1), rgb(248 250 252 / 0.9));
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

  :global(html.dark .channel-shell-dialog .channel-form-section),
  :global(html.dark .channel-shell-dialog .credential-image-preview ){
    border-color: rgb(71 85 105 / 36%);
    background: linear-gradient(180deg, rgb(15 23 42 / 90%), rgb(30 41 59 / 82%));
    box-shadow: 0 12px 28px rgb(2 6 23 / 24%);
  }

  :global(html.dark .channel-shell-dialog .credential-image-preview img ){
    background: rgb(15 23 42 / 92%);
    box-shadow: 0 14px 30px rgb(2 6 23 / 28%);
  }

  @media (width <= 991px) {
    .channel-dialog-shell {
      gap: 12px;
    }
  }
</style>
