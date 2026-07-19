<template>
  <ElDialog
    :model-value="visible"
    width="760px"
    class="channel-shell-dialog"
    destroy-on-close
    align-center
    title="新增通道"
    @update:model-value="handleVisibilityChange"
  >
    <ElForm label-position="top" class="channel-dialog-shell">
      <div class="dialog-grid">
        <ElFormItem label="支付方式">
          <ElSelect
            v-model="createForm.payment_method_type"
            placeholder="请选择支付方式"
            @change="handlePaymentMethodChange"
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
            @change="handlePluginChange"
          >
            <ElOption
              v-for="option in filteredPluginOptions"
              :key="option.code"
              :label="option.name"
              :value="option.code"
            />
          </ElSelect>
        </ElFormItem>
      </div>

      <div v-if="isCreateFormReady" class="merchant-channel-create-form">
        <section class="channel-form-section">
          <div class="channel-form-section__head">
            <h4>
              {{
                isAlipayOfficialCreateCode
                  ? '支付宝官方 V3 配置'
                  : isWxpayV3CreateCode
                    ? '微信官方 V3 配置'
                    : '基础信息'
              }}
            </h4>
          </div>

          <template v-if="isAlipayOfficialCreateCode">
            <AlipayOfficialCredentialFields
              :model="createForm"
              :meta="activeCreateMeta"
              compact-placeholder
            />
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
          </template>
          <template v-else-if="isWxpayV3CreateCode">
            <WxpayV3CredentialFields
              :model="createForm"
              :meta="activeCreateMeta"
              compact-placeholder
            />
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
          </template>
          <template v-else>
            <ElFormItem
              v-if="isJiaofeiyiCreateCode && activeCreateMeta.supportsPid"
              :label="jiaofeiyiMerchantIdLabel"
            >
              <ElInput
                v-model="createForm.pid"
                maxlength="50"
                :placeholder="jiaofeiyiMerchantIdPlaceholder"
              />
            </ElFormItem>
            <ElFormItem :label="displayAccountFieldLabel(activeCreateMeta.identifierLabel)">
              <ElInput
                v-model="createForm.identifier"
                maxlength="50"
                :placeholder="displayAccountFieldPlaceholder(activeCreateMeta.identifierPlaceholder)"
              />
            </ElFormItem>
            <ElFormItem
              v-if="activeCreateMeta.supportsPid && !isJiaofeiyiCreateCode"
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
            </template>
            <div v-else class="dialog-grid">
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
          </template>
        </section>

        <section
          v-if="!isAlipayOfficialCreateCode && !isWxpayV3CreateCode"
          class="channel-form-section"
        >
          <div class="channel-form-section__head">
            <h4>凭证信息</h4>
          </div>

          <ElFormItem
            v-if="isJiaofeiyiCreateCode && activeCreateMeta.supportsCookie"
            :label="jiaofeiyiStoreNameLabel"
          >
            <ElInput
              v-model="createForm.cookie"
              maxlength="12000"
              :placeholder="jiaofeiyiStoreNamePlaceholder"
            />
          </ElFormItem>
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
                    :http-request="handleCreateCredentialImageUpload"
                  >
                    <ElButton :loading="isCreateQrUrlUploading" type="primary" plain>
                      {{ activeCreateQrImageButtonText }}
                    </ElButton>
                  </ElUpload>
                  <ElButton v-if="createForm.qr_url" link type="danger" @click="clearCreateQrUrl">
                    娓呯┖
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
                    :http-request="handleCreateQrUrlDecodeUpload"
                  >
                    <ElButton :loading="isCreateQrUrlUploading" plain>
                      上传二维码并解析
                    </ElButton>
                  </ElUpload>
                  <ElButton v-if="createForm.qr_url" link type="danger" @click="clearCreateQrUrl">
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
            v-if="activeCreateMeta.supportsCookie && !isJiaofeiyiCreateCode"
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
                    :http-request="handleCreateExtraValueDecodeUpload"
                  >
                    <ElButton :loading="isCreateExtraValueUploading" plain>
                      上传二维码并解析
                    </ElButton>
                  </ElUpload>
                  <ElButton
                    v-if="createForm.extra_value"
                    link
                    type="danger"
                    @click="clearCreateExtraValue"
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
        </section>

        <section class="channel-form-section">
          <div class="channel-form-section__head">
            <h4>限额与备注</h4>
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
                placeholder="0"
              />
            </ElFormItem>
            <ElFormItem label="单日金额限制">
              <ElInput
                v-model="createForm.daymaxmoney"
                maxlength="50"
                inputmode="decimal"
                placeholder="不限"
              />
            </ElFormItem>
            <ElFormItem label="累计次数限制">
              <ElInput
                v-model="createForm.allmaxcount"
                maxlength="10"
                inputmode="numeric"
                placeholder="0"
              />
            </ElFormItem>
            <ElFormItem label="累计金额限制">
              <ElInput
                v-model="createForm.allmaxmoney"
                maxlength="50"
                inputmode="decimal"
                placeholder="不限"
              />
            </ElFormItem>
          </div>
        </section>
      </div>
      <ElEmpty
        v-else
        class="channel-create-empty"
        description="请先选择支付方式和支付插件，再填写通道参数。"
      />
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="emit('update:visible', false)">取消</ElButton>
        <ElButton
          v-if="hasCreateAuth"
          type="primary"
          :loading="creatingAccount"
          @click="emit('submit')"
        >
          新增通道
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
    type PaymentAccountCredentialField
  } from '@/views/shared/paymentAccountCredential'
  import type { PaymentAccountCodeMeta } from '@/views/shared/paymentAccountMeta'
  import {
    displayAccountFieldLabel,
    displayAccountFieldPlaceholderCompact as displayAccountFieldPlaceholder
  } from '@/views/shared/paymentAccountDisplay'

  interface PaymentMethodOption {
    label: string
    value: string
  }

  interface PluginOption {
    code: string
    name: string
  }

  interface MerchantChannelCreateForm {
    payment_method_type: string
    plugin_code: string
    code: string
    identifier: string
    pid: string
    qr_type: string
    qr_url: string
    cookie: string
    remark: string
    wx_guid: string
    cloud_id: string
    extra_value: string
    memo: string
    daymaxcount: string
    daymaxmoney: string
    allmaxcount: string
    allmaxmoney: string
    status: boolean
    is_status: boolean
  }

  interface Props {
    visible: boolean
    hasCreateAuth: boolean
    creatingAccount: boolean
    createForm: MerchantChannelCreateForm
    paymentMethodOptions: PaymentMethodOption[]
    filteredPluginOptions: PluginOption[]
    isCreateFormReady: boolean
    activeCreatePaymentOption: PaymentMethodOption | null
    activeCreatePluginOption: PluginOption | null
    activeCreateMeta: PaymentAccountCodeMeta
    createQrUrlEditor: string
    createExtraValueEditor: string
    activeCreateQrUrlLabel: string
    activeCreateQrUrlPlaceholder: string
    activeCreateQrImageButtonText: string
    activeCreateQrPreviewAlt: string
    isAssetUploading: (
      scope: 'create',
      field: PaymentAccountCredentialField
    ) => boolean
    resolveCredentialAssetUrl: (value: string) => string
    handleCredentialImageUploadRequest: (
      options: UploadRequestOptions,
      scope: 'create'
    ) => void | Promise<void>
    handleQrDecodeUploadRequest: (
      options: UploadRequestOptions,
      scope: 'create',
      field: PaymentAccountCredentialField
    ) => void | Promise<void>
    clearScopedCredentialField: (
      scope: 'create',
      field: PaymentAccountCredentialField
    ) => void
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:visible', value: boolean): void
    (e: 'submit'): void
    (e: 'paymentMethodChange', value: string): void
    (e: 'pluginChange', value: string): void
  }>()

  const handleVisibilityChange = (value: boolean) => emit('update:visible', value)
  const handlePaymentMethodChange = (value: string) => emit('paymentMethodChange', value)
  const handlePluginChange = (value: string) => emit('pluginChange', value)

  const isCreateQrUrlUploading = computed(() => props.isAssetUploading('create', 'qr_url'))
  const isCreateExtraValueUploading = computed(() =>
    props.isAssetUploading('create', 'extra_value')
  )
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
  const jiaofeiyiMerchantIdLabel = computed(() => '商户ID')
  const jiaofeiyiMerchantIdPlaceholder = computed(() => '请输入商户ID')
  const jiaofeiyiStoreNameLabel = computed(() => '店铺名')
  const jiaofeiyiStoreNamePlaceholder = computed(() => '可填写店铺名称')
  const clearCreateQrUrl = () => props.clearScopedCredentialField('create', 'qr_url')
  const clearCreateExtraValue = () => props.clearScopedCredentialField('create', 'extra_value')
  const handleCreateCredentialImageUpload = (options: UploadRequestOptions) =>
    Promise.resolve(props.handleCredentialImageUploadRequest(options, 'create'))
  const handleCreateQrUrlDecodeUpload = (options: UploadRequestOptions) =>
    Promise.resolve(props.handleQrDecodeUploadRequest(options, 'create', 'qr_url'))
  const handleCreateExtraValueDecodeUpload = (options: UploadRequestOptions) =>
    Promise.resolve(props.handleQrDecodeUploadRequest(options, 'create', 'extra_value'))
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

  .dialog-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .merchant-channel-create-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .channel-create-empty {
    display: none;
    padding: 0;
    margin: 0;
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

  @media (width <= 991px) {
    .dialog-grid {
      grid-template-columns: 1fr;
    }
  }
</style>


