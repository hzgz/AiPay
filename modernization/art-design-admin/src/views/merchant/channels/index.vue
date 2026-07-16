<template>
  <div class="payment-account-page art-full-height">
    <ArtSearchBar
      v-model="searchForm"
      :items="searchItems"
      :showExpand="false"
      @search="handleSearch"
      @reset="handleReset"
    />

    <ElCard class="art-table-card" shadow="never">
      <ArtTableHeader v-model:columns="columnChecks" :loading="loading" @refresh="getAccountList">
        <template #left>
          <div class="account-toolbar">
            <ElSpace wrap>
              <ElButton v-if="hasCreateAuth" size="small" type="primary" @click="openCreateDialog">
                新增通道
              </ElButton>
              <ElButton
                v-if="hasBatchDeleteAuth"
                size="small"
                plain
                type="danger"
                :disabled="selectedAccounts.length === 0"
                @click="handleBatchDeleteAccounts"
              >
                批量删除
              </ElButton>
              <ElTag v-if="selectedAccounts.length > 0" size="small" type="danger" effect="plain">
                已选 {{ selectedAccounts.length }}
              </ElTag>
            </ElSpace>

            <ElSpace wrap class="account-toolbar__summary">
              <ElTag size="small" effect="plain">通道 {{ pagination.total }}</ElTag>
              <ElTag size="small" type="success" effect="plain">
                在线 {{ summary.online_count }} / 启用 {{ summary.enabled_count }}
              </ElTag>
              <ElTag size="small" type="warning" effect="plain">
                离线 {{ summary.offline_count }} / 停用 {{ summary.disabled_count }}
              </ElTag>
              <ElTag size="small" type="info" effect="plain">
                成交 {{ summary.paid_order_count }} / {{ formatAmount(summary.paid_amount) }}
              </ElTag>
              <ElTag
                size="small"
                :type="testPayEnabled ? 'primary' : 'info'"
                effect="plain"
              >
                {{ testPaySummaryText }}
              </ElTag>
            </ElSpace>
          </div>
        </template>
      </ArtTableHeader>

      <ArtTable
        ref="tableRef"
        :loading="loading"
        :data="accountList"
        :columns="columns"
        :pagination="pagination"
        row-key="id"
        reserve-selection
        @selection-change="handleAccountSelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>
    <MerchantChannelDetailDrawer
      :visible="detailVisible"
      :detail-loading="detailLoading"
      :active-account="activeAccount"
      :has-test-pay-auth="hasTestPayAuth"
      :has-edit-auth="hasEditAuth"
      :has-status-auth="hasStatusAuth"
      :has-delete-auth="hasDeleteAuth"
      :can-edit-credentials="
        Boolean(activeAccount && supportsCredentialEditCode(activeAccount.code))
      "
      @update:visible="detailVisible = $event"
      @test="activeAccount && openTestPayDialog(activeAccount)"
      @edit="openEditDialog()"
      @edit-credentials="openCredentialDialog()"
      @edit-status="openStatusDialog()"
      @delete="handleDeleteAccount()"
    />

    <MerchantChannelCreateDialog
      :visible="createVisible"
      :has-create-auth="hasCreateAuth"
      :creating-account="creatingAccount"
      :create-form="createForm"
      :payment-method-options="paymentMethodOptions"
      :filtered-plugin-options="filteredPluginOptions"
      :is-create-form-ready="isCreateFormReady"
      :active-create-payment-option="activeCreatePaymentOption"
      :active-create-plugin-option="activeCreatePluginOption"
      :active-create-meta="activeCreateMeta"
      :create-qr-url-editor="createQrUrlEditor"
      :create-extra-value-editor="createExtraValueEditor"
      :active-create-qr-url-label="activeCreateQrUrlLabel"
      :active-create-qr-url-placeholder="activeCreateQrUrlPlaceholder"
      :active-create-qr-image-button-text="activeCreateQrImageButtonText"
      :active-create-qr-preview-alt="activeCreateQrPreviewAlt"
      :is-asset-uploading="isAssetUploading"
      :resolve-credential-asset-url="resolveCredentialAssetUrl"
      :handle-credential-image-upload-request="handleCredentialImageUploadRequest"
      :handle-qr-decode-upload-request="handleQrDecodeUploadRequest"
      :clear-scoped-credential-field="clearScopedCredentialField"
      @update:visible="createVisible = $event"
      @payment-method-change="handleCreatePaymentMethodChange"
      @plugin-change="handleCreatePluginChange"
      @submit="submitCreate"
    />

    <MerchantChannelCredentialDialog
      :visible="credentialVisible"
      :active-account="activeAccount"
      :active-credential-meta="activeCredentialMeta"
      :credential-form="credentialForm"
      :credential-qr-url-editor="credentialQrUrlEditor"
      :credential-extra-value-editor="credentialExtraValueEditor"
      :active-credential-qr-url-label="activeCredentialQrUrlLabel"
      :active-credential-qr-url-placeholder="activeCredentialQrUrlPlaceholder"
      :active-credential-qr-image-button-text="activeCredentialQrImageButtonText"
      :active-credential-qr-preview-alt="activeCredentialQrPreviewAlt"
      :saving-credential="savingCredential"
      :has-edit-auth="hasEditAuth"
      :is-asset-uploading="isAssetUploading"
      :resolve-credential-asset-url="resolveCredentialAssetUrl"
      :handle-credential-image-upload-request="handleCredentialImageUploadRequest"
      :handle-qr-decode-upload-request="handleQrDecodeUploadRequest"
      :clear-scoped-credential-field="clearScopedCredentialField"
      @update:visible="credentialVisible = $event"
      @submit="submitCredentialEdit"
    />
    <MerchantChannelEditStatusDialogs
      :edit-visible="editVisible"
      :status-visible="statusVisible"
      :active-account="activeAccount"
      :edit-form="editForm"
      :status-form="statusForm"
      :has-edit-auth="hasEditAuth"
      :has-status-auth="hasStatusAuth"
      :saving-edit="savingEdit"
      :saving-status="savingStatus"
      :account-code-text="activeAccountCodeText"
      :account-type-text="activeAccountTypeText"
      :account-status-text="activeAccountStatusText"
      :account-enabled-status-text="activeAccountEnabledStatusText"
      @update:edit-visible="editVisible = $event"
      @update:status-visible="statusVisible = $event"
      @update:edit-form="Object.assign(editForm, $event)"
      @update:status-form="Object.assign(statusForm, $event)"
      @submit-edit="submitEdit"
      @submit-status="submitStatus"
    />

    <MerchantChannelTestPayDialog
      :visible="testPayVisible"
      :test-pay-form="testPayForm"
      :testing-test-pay="testingTestPay"
      :polling-test-pay="pollingTestPay"
      :test-pay-result="testPayResult"
      :active-test-pay-account="activeTestPayAccount"
      :has-test-pay-auth="hasTestPayAuth"
      @update:visible="testPayVisible = $event"
      @refresh="refreshTestPayStatus"
      @submit="handleTestPay(activeTestPayAccount || undefined, true)"
    />
  </div>
</template>

<script setup lang="ts">
    import { ElButton, ElMessage, ElMessageBox, ElTag } from 'element-plus'
  import type { UploadRequestOptions } from 'element-plus'
  import { useRoute } from 'vue-router'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import { resolveBackendOrigin } from '@/utils/http/base'
  import MerchantChannelCredentialDialog from './modules/channel-credential-dialog.vue'
  import MerchantChannelCreateDialog from './modules/channel-create-dialog.vue'
  import MerchantChannelDetailDrawer from './modules/channel-detail-drawer.vue'
  import MerchantChannelEditStatusDialogs from './modules/channel-edit-status-dialogs.vue'
  import MerchantChannelTestPayDialog from './modules/channel-test-pay-dialog.vue'
  import {
    batchDeleteMerchantChannels,
    createMerchantChannelTestPay,
    createMerchantChannel,
    decodeMerchantChannelCredentialImage,
    deleteMerchantChannel,
    fetchMerchantChannelBatchDeleteAudit,
    fetchMerchantChannelDeleteAudit,
    fetchMerchantChannelDetail,
    fetchMerchantChannels,
    pollMerchantChannelTestPay,
    updateMerchantChannel,
    updateMerchantChannelCredentials,
    updateMerchantChannelStatus,
    uploadMerchantChannelCredentialImage
  } from '@/api/merchant'
  import {
    resolveAccountFieldEditor,
    resolveAccountQrUrlLabel,
    resolveAccountQrUrlPlaceholder,
    resolveCredentialImageButtonText,
    resolveCredentialImagePreviewAlt
  } from '@/views/shared/paymentAccountCredential'
  import type { PaymentAccountCredentialField } from '@/views/shared/paymentAccountCredential'
  import { ACCOUNT_CODE_META, getAccountCodeMeta } from '@/views/shared/paymentAccountMeta'
  import {
    buildMerchantChannelCreatePayload,
    buildMerchantChannelCredentialPayload,
    buildMerchantChannelEditableFromAccount,
    buildMerchantChannelStatusPayload,
    buildMerchantChannelTestPayPayload,
    buildMerchantChannelUpdatePayload,
    createEmptyMerchantChannelCreateForm,
    createEmptyMerchantChannelCredentialForm,
    createEmptyMerchantChannelEditForm,
    createEmptyMerchantChannelStatusForm,
    createEmptyMerchantChannelTestPayForm,
    handleMerchantChannelCreatePaymentMethodChange,
    handleMerchantChannelCreatePluginChange,
    resolveMerchantChannelTestPayAmount,
    supportsMerchantChannelCredentialEditCode,
    syncMerchantChannelCredentialForm,
    syncMerchantChannelEditForm,
    syncMerchantChannelStatusForm,
    type MerchantChannelFormScope,
    type MerchantChannelPluginOption
  } from './modules/channel-form-state'
  import {
    displayAccountCode,
    displayAccountFieldText,
    displayAccountIdentifierSource,
    displayAccountIdentifierValue,
    displayAccountTypeLabel
  } from '@/views/shared/paymentAccountDisplay'
  import {
    buildPaymentAccountBatchDeleteBlockedMessage as buildBatchDeleteBlockedMessage,
    buildPaymentAccountBatchDeletePromptMessage as buildBatchDeletePromptMessage,
    buildPaymentAccountDeleteBlockedMessage as buildDeleteBlockedMessage,
    buildPaymentAccountDeletePromptMessage as buildDeletePromptMessage,
    createEmptyPaymentAccountSummary as emptySummary,
    escapePaymentAccountConfirmation as escapeRegExp,
    formatPaymentAccountAmount as formatAmount,
    isPaymentAccountDialogCancel as isDialogCancel,
    resolvePaymentAccountTagType as tagType
  } from '@/views/shared/paymentAccountPageShared'

  defineOptions({ name: 'MerchantChannels' })

  type AccountItem = Api.Payments.AccountListItem
  type AccountSummary = Api.Payments.AccountSummary
  type AccountEditable = Api.Payments.AccountEditable
  type AccountTestPayResult = import('@/api/merchant').MerchantChannelTestPayResponse
  type AccountCredentialField = PaymentAccountCredentialField
  type AccountFormScope = MerchantChannelFormScope
  const route = useRoute()

  const tableRef = ref<{ elTableRef?: { clearSelection?: () => void } } | null>(null)
  const loading = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const createVisible = ref(false)
  const editVisible = ref(false)
  const credentialVisible = ref(false)
  const statusVisible = ref(false)
  const testPayVisible = ref(false)
  const creatingAccount = ref(false)
  const savingEdit = ref(false)
  const savingCredential = ref(false)
  const savingStatus = ref(false)
  const testingTestPay = ref(false)
  const pollingTestPay = ref(false)
  const writeActions = ref<Record<string, boolean>>({})
  const catalog = ref<Record<string, any>>({})
  const hasCreateAuth = computed(() => Boolean(writeActions.value.create))
  const hasStatusAuth = computed(() => Boolean(writeActions.value.status))
  const hasDeleteAuth = computed(() => Boolean(writeActions.value.remove))
  const hasBatchDeleteAuth = computed(() => Boolean(writeActions.value.batchRemove))
  const hasEditAuth = computed(() => Boolean(writeActions.value.edit))
  const hasTestPayAuth = computed(() => Boolean(writeActions.value.testPay))
  const accountList = ref<AccountItem[]>([])
  const selectedAccounts = ref<AccountItem[]>([])
  const activeAccount = ref<AccountItem | null>(null)
  const activeTestPayAccount = ref<AccountItem | null>(null)
  const editableAccount = ref<AccountEditable | null>(null)
  const testPayResult = ref<AccountTestPayResult | null>(null)
  let testPayPollTimer: ReturnType<typeof setTimeout> | null = null

  const pagination = reactive({
    current: 1,
    size: 20,
    total: 0
  })

  const summary = reactive<AccountSummary>({
    online_count: 0,
    offline_count: 0,
    enabled_count: 0,
    disabled_count: 0,
    identifier_ready_count: 0,
    credential_ready_count: 0,
    paid_order_count: 0,
    paid_amount: 0
  })

  const searchForm = ref<{
    keyword?: string
    type?: string
    status?: string
    is_status?: string
    date_range?: string[]
  }>({})

  const createForm = reactive(createEmptyMerchantChannelCreateForm())
  const editForm = reactive(createEmptyMerchantChannelEditForm())
  const credentialForm = reactive(createEmptyMerchantChannelCredentialForm())
  const statusForm = reactive(createEmptyMerchantChannelStatusForm())
  const testPayForm = reactive(createEmptyMerchantChannelTestPayForm())

  const assetUploadState = reactive<Record<string, boolean>>({})
  const paymentAssetBaseUrl = resolveBackendOrigin()

  const paymentMethodOptions = computed(() => {
    const records = Array.isArray(catalog.value.payment_methods)
      ? (catalog.value.payment_methods as Array<{ label: string; value: string }>)
      : []

    return records.filter((item) => Boolean(String(item.value || '').trim()))
  })
  const pluginOptions = computed(() => {
    const records = Array.isArray(catalog.value.plugin_options)
      ? (catalog.value.plugin_options as MerchantChannelPluginOption[])
      : []

    return records.filter((item) => Boolean(String(item.code || '').trim()))
  })
  const filteredPluginOptions = computed(() => {
    const paymentMethodType = String(createForm.payment_method_type || '').trim()
    if (!paymentMethodType) {
      return []
    }

    return pluginOptions.value.filter((item) =>
      (item.method_types || []).map((type) => String(type || '').trim()).includes(paymentMethodType)
    )
  })
  const testPaySettings = computed(() => {
    const settings =
      catalog.value && typeof catalog.value === 'object'
        ? (catalog.value.test_pay as Record<string, any>)
        : null

    return settings && typeof settings === 'object' ? settings : {}
  })
  const testPayEnabled = computed(() => Boolean(testPaySettings.value.enabled))
  const testPaySummaryText = computed(() => {
    if (!testPayEnabled.value) {
      return '测试 未开启'
    }

    const amount = String(testPaySettings.value.amount || '').trim()
    return amount ? `测试 ${amount} 元` : '测试 已开启'
  })
  const activeCreateMeta = computed(
    () => getAccountCodeMeta(createForm.code) || ACCOUNT_CODE_META.alipay_software
  )
  const activeCreatePaymentOption = computed(
    () =>
      paymentMethodOptions.value.find(
        (item) =>
          String(item.value || '').trim() === String(createForm.payment_method_type || '').trim()
      ) || null
  )
  const activeCreatePluginOption = computed(
    () =>
      pluginOptions.value.find(
        (item) => String(item.code || '').trim() === String(createForm.plugin_code || '').trim()
      ) || null
  )
  const isCreateFormReady = computed(
    () =>
      Boolean(String(createForm.payment_method_type || '').trim()) &&
      Boolean(String(createForm.code || '').trim())
  )
  const activeCredentialMeta = computed(() => getAccountCodeMeta(activeAccount.value?.code))
  const createQrUrlEditor = computed(() =>
    resolveAccountFieldEditor(createForm.code, 'qr_url', createForm.qr_type)
  )
  const createExtraValueEditor = computed(() =>
    resolveAccountFieldEditor(createForm.code, 'extra_value', createForm.qr_type)
  )
  const credentialQrUrlEditor = computed(() =>
    resolveAccountFieldEditor(activeAccount.value?.code, 'qr_url', credentialForm.qr_type)
  )
  const credentialExtraValueEditor = computed(() =>
    resolveAccountFieldEditor(activeAccount.value?.code, 'extra_value', credentialForm.qr_type)
  )
  const activeCreateQrUrlLabel = computed(() =>
    resolveAccountQrUrlLabel(createForm.code, createForm.qr_type, activeCreateMeta.value.qrUrlLabel)
  )
  const activeCreateQrUrlPlaceholder = computed(() =>
    resolveAccountQrUrlPlaceholder(
      createForm.code,
      createForm.qr_type,
      activeCreateMeta.value.qrUrlPlaceholder
    )
  )
  const activeCreateQrImageButtonText = computed(() =>
    resolveCredentialImageButtonText(
      createForm.code,
      createForm.qr_type,
      Boolean(createForm.qr_url)
    )
  )
  const activeCreateQrPreviewAlt = computed(() =>
    resolveCredentialImagePreviewAlt(createForm.code, createForm.qr_type)
  )
  const activeCredentialQrUrlLabel = computed(() =>
    resolveAccountQrUrlLabel(
      activeAccount.value?.code,
      credentialForm.qr_type,
      activeCredentialMeta.value?.qrUrlLabel || ''
    )
  )
  const activeCredentialQrUrlPlaceholder = computed(() =>
    resolveAccountQrUrlPlaceholder(
      activeAccount.value?.code,
      credentialForm.qr_type,
      activeCredentialMeta.value?.qrUrlPlaceholder || ''
    )
  )
  const activeCredentialQrImageButtonText = computed(() =>
    resolveCredentialImageButtonText(
      activeAccount.value?.code,
      credentialForm.qr_type,
      Boolean(credentialForm.qr_url)
    )
  )
  const activeCredentialQrPreviewAlt = computed(() =>
    resolveCredentialImagePreviewAlt(activeAccount.value?.code, credentialForm.qr_type)
  )
  const activeAccountCodeText = computed(() =>
    activeAccount.value
      ? `${displayAccountCode(activeAccount.value.code_label)} / #${activeAccount.value.id}`
      : '--'
  )
  const activeAccountTypeText = computed(() => displayAccountType(activeAccount.value))
  const activeAccountStatusText = computed(() => displayAccountStatus(activeAccount.value))
  const activeAccountEnabledStatusText = computed(() =>
    displayAccountEnabledStatus(activeAccount.value)
  )
  const supportsCredentialEditCode = supportsMerchantChannelCredentialEditCode

  function handleCreatePaymentMethodChange(value: string) {
    handleMerchantChannelCreatePaymentMethodChange(createForm, value)
  }

  function handleCreatePluginChange(value: string) {
    handleMerchantChannelCreatePluginChange(createForm, value)
  }

  function assetUploadKey(scope: AccountFormScope, field: AccountCredentialField) {
    return `${scope}:${field}`
  }

  function isAssetUploading(scope: AccountFormScope, field: AccountCredentialField) {
    return Boolean(assetUploadState[assetUploadKey(scope, field)])
  }

  function setAssetUploading(
    scope: AccountFormScope,
    field: AccountCredentialField,
    loading: boolean
  ) {
    assetUploadState[assetUploadKey(scope, field)] = loading
  }

  function setScopedCredentialFieldValue(
    scope: AccountFormScope,
    field: AccountCredentialField,
    value: string
  ) {
    if (scope === 'create') {
      createForm[field] = value
      return
    }

    credentialForm[field] = value
  }

  function clearScopedCredentialField(scope: AccountFormScope, field: AccountCredentialField) {
    setScopedCredentialFieldValue(scope, field, '')
  }

  function normalizeScopedCredentialState(scope: AccountFormScope) {
    if (scope === 'create') {
      if (!activeCreateMeta.value.supportsQrUrl || createQrUrlEditor.value === 'hidden') {
        createForm.qr_url = ''
      }

      if (!activeCreateMeta.value.supportsExtraValue) {
        createForm.extra_value = ''
      }

      if (!['jiaofeiyi_alipay', 'jiaofeiyi_wxpay'].includes(String(createForm.code || ''))) {
        createForm.cloud_id = ''
      }

      return
    }

    if (!activeCredentialMeta.value) {
      credentialForm.qr_url = ''
      credentialForm.cloud_id = ''
      credentialForm.extra_value = ''
      return
    }

    if (!activeCredentialMeta.value.supportsQrUrl || credentialQrUrlEditor.value === 'hidden') {
      credentialForm.qr_url = ''
    }

    if (!activeCredentialMeta.value.supportsExtraValue) {
      credentialForm.extra_value = ''
    }

    if (!['jiaofeiyi_alipay', 'jiaofeiyi_wxpay'].includes(String(activeAccount.value?.code || ''))) {
      credentialForm.cloud_id = ''
    }
  }

  function resolveCredentialAssetUrl(value: string) {
    const normalized = String(value || '').trim()
    if (!normalized) {
      return ''
    }

    if (
      /^(?:https?:)?\/\//i.test(normalized) ||
      normalized.startsWith('blob:') ||
      normalized.startsWith('data:')
    ) {
      return normalized
    }

    if (!paymentAssetBaseUrl) {
      return normalized
    }

    return `${paymentAssetBaseUrl}${normalized.startsWith('/') ? '' : '/'}${normalized}`
  }

  function validateCredentialImageFile(file: File) {
    const fileName = String(file.name || '').trim()
    const mimeType = String(file.type || '')
      .trim()
      .toLowerCase()
    const isImageMime = /^image\/(jpeg|jpg|png|gif|bmp|x-ms-bmp)$/i.test(mimeType)
    const isImageName = /\.(?:jpe?g|png|gif|bmp)$/i.test(fileName)

    if (!isImageMime && !isImageName) {
      ElMessage.warning('请上传 jpg、png、gif 或 bmp 图片。')
      return false
    }

    return true
  }

  async function handleCredentialImageUploadRequest(
    options: UploadRequestOptions,
    scope: AccountFormScope
  ) {
    const file = options.file as File
    if (!validateCredentialImageFile(file)) {
      options.onError?.(new Error('invalid credential image file') as any)
      return
    }

    setAssetUploading(scope, 'qr_url', true)
    try {
      const code =
        scope === 'create' ? createForm.code : String(activeAccount.value?.code || '').trim()
      const qrType = scope === 'create' ? createForm.qr_type : credentialForm.qr_type

      const response = await uploadMerchantChannelCredentialImage({
        code,
        field: 'qr_url',
        qr_type: qrType,
        file
      })

      setScopedCredentialFieldValue(scope, 'qr_url', String(response.value || '').trim())
      ElMessage.success('二维码图片已上传。')
      options.onSuccess?.(response as any)
    } catch (error) {
      options.onError?.(error as any)
    } finally {
      setAssetUploading(scope, 'qr_url', false)
    }
  }

  async function handleQrDecodeUploadRequest(
    options: UploadRequestOptions,
    scope: AccountFormScope,
    field: AccountCredentialField
  ) {
    const file = options.file as File
    if (!validateCredentialImageFile(file)) {
      options.onError?.(new Error('invalid qr decode file') as any)
      return
    }

    setAssetUploading(scope, field, true)
    try {
      const code =
        scope === 'create' ? createForm.code : String(activeAccount.value?.code || '').trim()
      const qrType = scope === 'create' ? createForm.qr_type : credentialForm.qr_type
      const response = await decodeMerchantChannelCredentialImage({
        code,
        field,
        qr_type: qrType,
        file
      })
      const decodedContent = String(response.value || '').trim()
      setScopedCredentialFieldValue(scope, field, decodedContent)
      ElMessage.success('二维码内容已解析。')
      options.onSuccess?.(response as any)
    } catch (error) {
      const message = error instanceof Error ? error.message : '二维码解析失败，请稍后重试。'
      ElMessage.warning(message)
      options.onError?.((error instanceof Error ? error : new Error(message)) as any)
    } finally {
      setAssetUploading(scope, field, false)
    }
  }

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '搜索通道或备注'
      }
    },
    {
      label: '支付类型',
      key: 'type',
      type: 'select',
      props: {
        placeholder: '全部支付类型',
        options: [
          { label: '支付宝', value: 'alipay' },
          { label: '微信', value: 'wxpay' },
          { label: 'QQ', value: 'qqpay' },
          { label: 'USDT', value: 'usdt' }
        ]
      }
    },
    {
      label: '在线状态',
      key: 'status',
      type: 'select',
      props: {
        placeholder: '全部在线状态',
        options: [
          { label: '在线', value: '1' },
          { label: '离线', value: '0' }
        ]
      }
    },
    {
      label: '启用状态',
      key: 'is_status',
      type: 'select',
      props: {
        placeholder: '全部启用状态',
        options: [
          { label: '启用', value: '1' },
          { label: '停用', value: '0' }
        ]
      }
    },
    {
      label: '创建时间',
      key: 'date_range',
      type: 'daterange',
      props: {
        type: 'daterange',
        valueFormat: 'YYYY-MM-DD',
        startPlaceholder: '开始日期',
        endPlaceholder: '结束日期',
        rangeSeparator: '至'
      }
    }
  ])

  const { columnChecks, columns } = useTableColumns<AccountItem>(() => [
    ...(hasBatchDeleteAuth.value
      ? [{ type: 'selection' as const, width: 54, fixed: 'left' as const }]
      : []),
    { type: 'globalIndex', width: 70, label: '序号' },
    {
      prop: 'code_label',
      label: '通道',
      minWidth: 190,
      formatter: (row) =>
        h('div', { class: 'channel-cell' }, [
          h('strong', { class: 'cell-title' }, displayAccountCode(row.code_label)),
          h(
            'p',
            { class: 'cell-sub' },
            displayAccountCode((row as any).code_display || row.code || '--')
          )
        ])
    },
    {
      prop: 'type_label',
      label: '类型',
      minWidth: 96,
      align: 'center' as const,
      formatter: (row) =>
        h(ElTag, { type: tagType(row.type_tag), effect: 'light' }, () => displayAccountType(row))
    },
    {
      prop: 'identifier',
      label: '标识',
      minWidth: 170,
      formatter: (row) =>
        h('div', { class: 'identifier-cell' }, [
          h(
            'strong',
            { class: 'mono-text' },
            displayAccountIdentifierValue(row.identifier, row.has_identifier)
          ),
          h(
            'p',
            { class: 'cell-sub' },
            row.has_identifier
              ? `${displayAccountIdentifierSource(row.identifier_source)} / ${row.identifier_length}位`
              : displayAccountIdentifierSource(row.identifier_source)
          )
        ])
    },
    {
      prop: 'status_label',
      label: '状态',
      minWidth: 148,
      align: 'center' as const,
      formatter: (row) =>
        h('div', { class: 'status-cell' }, [
          h(ElTag, { type: tagType(row.status_type), effect: 'light' }, () =>
            displayAccountStatus(row)
          ),
          h(ElTag, { type: tagType(row.is_status_type), effect: 'plain' }, () =>
            displayAccountEnabledStatus(row)
          )
        ])
    },
    {
      prop: 'paid_amount',
      label: '支付统计',
      minWidth: 160,
      align: 'right' as const,
      formatter: (row) =>
        h('div', { class: 'amount-cell' }, [
          h('strong', { class: 'cell-title' }, formatAmount(row.paid_amount)),
          h('p', { class: 'cell-sub' }, `订单 ${row.paid_order_count}/${row.order_count}`)
        ])
    },
    {
      prop: 'memo_label',
      label: '备注',
      minWidth: 160,
      formatter: (row) =>
        h(
          'span',
          { class: 'memo-text' },
          row.has_remark ? displayAccountFieldText(row.memo_text || row.memo_label, '--') : '--'
        )
    },
    {
      prop: 'update_time',
      label: '更新时间',
      minWidth: 148,
      formatter: (row) => row.update_time || row.create_time || '--'
    },
    {
      prop: 'operation',
      label: '操作',
      width:
        hasDeleteAuth.value && hasTestPayAuth.value
          ? 112
          : hasDeleteAuth.value || hasTestPayAuth.value
            ? 112
            : 96,
      align: 'center' as const,
      className: 'operation-column-cell',
      fixed: 'right' as const,
      formatter: (row) => renderAccountOperationButtons(row)
    }
  ])

  onMounted(() => {
    const routeKeyword = String(route.query.keyword || '').trim()
    const routeType = String(route.query.type || '').trim()

    searchForm.value = {
      ...(routeKeyword ? { keyword: routeKeyword } : {}),
      ...(routeType ? { type: routeType } : {})
    }

    getAccountList()
  })

  onBeforeUnmount(() => {
    stopTestPayPolling()
  })

  watch(
    () => createForm.code,
    (nextCode) => {
      const meta = getAccountCodeMeta(nextCode)
      createForm.qr_type = meta?.qrTypeOptions[0]?.value || ''
    }
  )

  watch([() => createForm.code, () => createForm.qr_type], () => {
    normalizeScopedCredentialState('create')
  })

  watch([() => activeAccount.value?.code, () => credentialForm.qr_type], () => {
    normalizeScopedCredentialState('credential')
  })

  watch(testPayVisible, (visible) => {
    if (!visible) {
      stopTestPayPolling()
      if (!testingTestPay.value) {
        testPayResult.value = null
      }
      return
    }

    if (testPayResult.value?.can_poll) {
      scheduleTestPayPolling()
    }
  })

  function renderAccountOperationButtons(row: AccountItem) {
    const actions = [
      h(
        ElButton,
        {
          type: 'primary',
          size: 'small',
          plain: true,
          class: 'table-action-link',
          onClick: () => openDetail(row)
        },
        () => '详情'
      )
    ]

    if (hasTestPayAuth.value) {
      actions.unshift(
        h(
          ElButton,
          {
            type: 'success',
            size: 'small',
            plain: true,
            class: 'table-action-link',
            onClick: () => openTestPayDialog(row)
          },
          () => '测试'
        )
      )
    }

    if (hasDeleteAuth.value) {
      actions.push(
        h(
          ElButton,
          {
            type: 'danger',
            size: 'small',
            plain: true,
            class: 'table-action-link',
            onClick: () => handleDeleteAccount(row)
          },
          () => '删除'
        )
      )
    }

    return h('div', { class: 'table-actions' }, actions)
  }

  async function getAccountList() {
    loading.value = true
    try {
      const response = await fetchMerchantChannels({
        current: pagination.current,
        size: pagination.size,
        keyword: searchForm.value.keyword,
        type: searchForm.value.type,
        status: searchForm.value.status,
        is_status: searchForm.value.is_status,
        start_date: searchForm.value.date_range?.[0],
        end_date: searchForm.value.date_range?.[1]
      })
      accountList.value = response.records as AccountItem[]
      pagination.current = Number(response.pagination?.current || 1)
      pagination.size = Number(response.pagination?.size || pagination.size)
      pagination.total = Number(response.pagination?.total || 0)
      Object.assign(summary, emptySummary(), response.summary || {})
      writeActions.value = { ...(response.writeActions || {}) }
      catalog.value = { ...(response.catalog || {}) }
    } catch {
      ElMessage.error('加载通道列表失败。')
    } finally {
      loading.value = false
    }
  }

  function handleSearch(params: Record<string, unknown>) {
    pagination.current = 1
    clearAccountSelection()
    searchForm.value = {
      keyword: params.keyword as string | undefined,
      type: params.type as string | undefined,
      status: params.status as string | undefined,
      is_status: params.is_status as string | undefined,
      date_range: Array.isArray(params.date_range) ? (params.date_range as string[]) : undefined
    }
    getAccountList()
  }

  function handleReset() {
    pagination.current = 1
    clearAccountSelection()
    searchForm.value = {}
    getAccountList()
  }

  function handleSizeChange(size: number) {
    pagination.size = size
    pagination.current = 1
    getAccountList()
  }

  function handleCurrentChange(current: number) {
    pagination.current = current
    getAccountList()
  }

  function handleAccountSelectionChange(rows: AccountItem[]) {
    selectedAccounts.value = rows
  }

  async function openCreateDialog() {
    if (!hasCreateAuth.value) {
      ElMessage.warning('您没有新增通道的权限。')
      return
    }

    await getAccountList()
    Object.assign(createForm, createEmptyMerchantChannelCreateForm())
    createVisible.value = true
  }

  async function submitCreate() {
    if (!hasCreateAuth.value) {
      ElMessage.warning('您没有新增通道的权限。')
      return
    }

    const payload = buildMerchantChannelCreatePayload(createForm, filteredPluginOptions.value)
    if (!payload) {
      return
    }

    creatingAccount.value = true
    try {
      const response = await createMerchantChannel(payload)
      createVisible.value = false
      clearAccountSelection()
      await getAccountList()
      ElMessage.success(
        `通道 ${displayAccountCode(response.created_account_label || `#${response.created_account_id}`)} 已创建。`
      )
    } finally {
      creatingAccount.value = false
    }
  }

  async function openDetail(row: AccountItem) {
    activeAccount.value = row
    editableAccount.value = buildMerchantChannelEditableFromAccount(row)
    detailVisible.value = true
    detailLoading.value = true

    try {
      const response = await fetchMerchantChannelDetail(row.id)
      if (response.item) {
        applyAccountDetail(response.item, response.editable)
      }
    } catch {
      ElMessage.error('加载通道详情失败。')
    } finally {
      detailLoading.value = false
    }
  }

  function openTestPayDialog(row?: AccountItem) {
    const target = row || activeAccount.value
    if (!target) {
      return
    }

    if (!hasTestPayAuth.value) {
      ElMessage.warning('管理员尚未开启通道测试。')
      return
    }

    stopTestPayPolling()
    activeTestPayAccount.value = target
    testPayResult.value = null
    testPayForm.pay_amount = resolveMerchantChannelTestPayAmount(testPaySettings.value)
    testPayVisible.value = true
  }

  async function handleTestPay(row?: AccountItem, keepDialogOpen = false) {
    const target = row || activeAccount.value
    if (!target) {
      return
    }

    if (!hasTestPayAuth.value) {
      ElMessage.warning('管理员尚未开启通道测试。')
      return
    }

    const payload = buildMerchantChannelTestPayPayload(testPayForm)
    if (!payload) {
      return
    }

    stopTestPayPolling()
    activeTestPayAccount.value = target
    if (!keepDialogOpen) {
      testPayVisible.value = true
      testPayResult.value = null
    }

    testingTestPay.value = true
    try {
      const response = await createMerchantChannelTestPay(target.id, payload)
      testPayResult.value = response
      testPayForm.pay_amount = resolveMerchantChannelTestPayAmount(
        testPaySettings.value,
        response.pay_amount
      )
      testPayVisible.value = true

      if (response.can_poll) {
        scheduleTestPayPolling()
      }
    } catch (error) {
      const message = error instanceof Error ? error.message : '发起测试失败。'
      ElMessage.error(message)
    } finally {
      testingTestPay.value = false
    }
  }

  async function refreshTestPayStatus(silent = false) {
    const outTradeNo = String(testPayResult.value?.out_trade_no || '').trim()
    if (!outTradeNo) {
      return
    }

    pollingTestPay.value = true
    try {
      const response = await pollMerchantChannelTestPay(outTradeNo)
      testPayResult.value = response

      if (response.can_poll) {
        scheduleTestPayPolling()
      } else {
        stopTestPayPolling()
      }

      if (!silent) {
        ElMessage.success('测试状态已更新。')
      }
    } catch (error) {
      stopTestPayPolling()
      if (!silent) {
        const message = error instanceof Error ? error.message : '刷新测试状态失败。'
        ElMessage.error(message)
      }
    } finally {
      pollingTestPay.value = false
    }
  }

  function scheduleTestPayPolling(delay = 3000) {
    stopTestPayPolling()
    if (!testPayVisible.value || !testPayResult.value?.can_poll) {
      return
    }

    testPayPollTimer = setTimeout(() => {
      refreshTestPayStatus(true)
    }, delay)
  }

  function stopTestPayPolling() {
    if (testPayPollTimer !== null) {
      clearTimeout(testPayPollTimer)
      testPayPollTimer = null
    }
  }

  function openEditDialog(row?: AccountItem) {
    const target = row || activeAccount.value
    if (!target) {
      return
    }

    activeAccount.value = target
    syncMerchantChannelEditForm(
      editForm,
      editableAccount.value || buildMerchantChannelEditableFromAccount(target)
    )
    editVisible.value = true
  }

  function openCredentialDialog(row?: AccountItem) {
    const target = row || activeAccount.value
    if (!target) {
      return
    }

    if (!supportsCredentialEditCode(target.code)) {
      ElMessage.warning('当前通道类型暂未开放凭证编辑。')
      return
    }

    activeAccount.value = target
    syncMerchantChannelCredentialForm(
      credentialForm,
      editableAccount.value || buildMerchantChannelEditableFromAccount(target),
      target.code
    )
    credentialVisible.value = true
  }

  function openStatusDialog(row?: AccountItem) {
    const target = row || activeAccount.value
    if (!target) {
      return
    }

    activeAccount.value = target
    syncMerchantChannelStatusForm(
      statusForm,
      editableAccount.value || buildMerchantChannelEditableFromAccount(target)
    )
    statusVisible.value = true
  }

  async function submitEdit() {
    if (!activeAccount.value) {
      return
    }

    const payload = buildMerchantChannelUpdatePayload(editForm)
    if (!payload) {
      return
    }

    savingEdit.value = true
    try {
      const response = await updateMerchantChannel(activeAccount.value.id, payload)

      if (response.item) {
        applyAccountDetail(response.item, response.editable)
      }

      await getAccountList()
      editVisible.value = false
      ElMessage.success('通道限额已更新。')
    } finally {
      savingEdit.value = false
    }
  }

  async function submitCredentialEdit() {
    if (!activeAccount.value) {
      return
    }

    const payload = buildMerchantChannelCredentialPayload(credentialForm, activeAccount.value.code)
    if (!payload) {
      return
    }

    savingCredential.value = true
    try {
      const response = await updateMerchantChannelCredentials(activeAccount.value.id, payload)

      if (response.item) {
        applyAccountDetail(response.item, response.editable)
      }

      await getAccountList()
      credentialVisible.value = false
      ElMessage.success('通道凭证已更新。')
    } finally {
      savingCredential.value = false
    }
  }

  async function submitStatus() {
    if (!activeAccount.value) {
      return
    }

    savingStatus.value = true
    try {
      const response = await updateMerchantChannelStatus(
        activeAccount.value.id,
        buildMerchantChannelStatusPayload(statusForm)
      )

      if (response.item) {
        applyAccountDetail(response.item, {
          ...(editableAccount.value || buildMerchantChannelEditableFromAccount(response.item)),
          status: response.item.status,
          is_status: response.item.is_status
        })
      }

      await getAccountList()
      statusVisible.value = false
      ElMessage.success('通道状态已更新。')
    } finally {
      savingStatus.value = false
    }
  }

  async function handleDeleteAccount(row?: AccountItem) {
    const target = row || activeAccount.value
    if (!target) {
      return
    }

    if (!hasDeleteAuth.value) {
      ElMessage.warning('您没有删除通道的权限。')
      return
    }

    try {
      const response = await fetchMerchantChannelDeleteAudit(target.id)
      const audit = response.audit
      const title = displayAccountCode(target.code_label, `通道 #${target.id}`)

      if (!audit.can_delete) {
        await ElMessageBox.alert(buildDeleteBlockedMessage(audit, title), '删除受限', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildDeletePromptMessage(audit, title),
        '删除通道',
        {
          confirmButtonText: '删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 后继续。`
        }
      )

      const deleteResponse = await deleteMerchantChannel(target.id, {
        confirmation_phrase: String(value || '')
      })

      if (activeAccount.value?.id === target.id) {
        detailVisible.value = false
        activeAccount.value = null
        editableAccount.value = null
      }

      clearAccountSelection()
      await getAccountList()
      ElMessage.success(
        `通道 ${displayAccountCode(deleteResponse.deleted_account_label || title)} 已删除，已清理 ${deleteResponse.audit.summary.delete_row_count} 条关联数据。`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      throw error
    }
  }

  async function handleBatchDeleteAccounts() {
    if (!hasBatchDeleteAuth.value) {
      ElMessage.warning('您没有批量删除通道的权限。')
      return
    }

    if (selectedAccounts.value.length === 0) {
      ElMessage.warning('请至少选择一个通道。')
      return
    }

    const accountIds = selectedAccounts.value.map((item) => item.id)

    try {
      const response = await fetchMerchantChannelBatchDeleteAudit({
        account_ids: accountIds
      })
      const audit = response.audit

      if (!audit.can_delete_all) {
        await ElMessageBox.alert(
          buildBatchDeleteBlockedMessage(audit, {
            entityLabel: '通道',
            blockedReasonFallback: '该通道当前不可删除。',
            entityPrefix: '通道'
          }),
          '批量删除受限',
          {
            type: 'warning',
            confirmButtonText: '知道了'
          }
        )
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildBatchDeletePromptMessage(audit, '通道'),
        '批量删除通道',
        {
          confirmButtonText: '批量删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 后继续。`
        }
      )

      const deleteResponse = await batchDeleteMerchantChannels({
        account_ids: accountIds,
        confirmation_phrase: String(value || '')
      })

      if (
        activeAccount.value &&
        deleteResponse.deleted_account_ids.includes(activeAccount.value.id)
      ) {
        detailVisible.value = false
        activeAccount.value = null
        editableAccount.value = null
      }

      clearAccountSelection()
      await getAccountList()
      ElMessage.success(
        `已删除 ${deleteResponse.deleted_count} 个通道，已清理 ${deleteResponse.audit.summary.delete_row_count} 条关联数据。`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      throw error
    }
  }

  function applyAccountDetail(item: AccountItem, editable?: AccountEditable | null) {
    activeAccount.value = item
    editableAccount.value = editable || buildMerchantChannelEditableFromAccount(item)
    syncMerchantChannelEditForm(editForm, editableAccount.value)
    syncMerchantChannelCredentialForm(credentialForm, editableAccount.value, item.code)
    syncMerchantChannelStatusForm(statusForm, editableAccount.value)
  }

  function clearAccountSelection() {
    selectedAccounts.value = []
    tableRef.value?.elTableRef?.clearSelection?.()
  }

  function displayAccountType(account?: Partial<AccountItem> | null, fallback = '--') {
    return displayAccountTypeLabel(
      account?.type_text || account?.type_label || account?.type,
      fallback
    )
  }

  function displayAccountStatus(account?: Partial<AccountItem> | null, fallback = '--') {
    return displayAccountFieldText(account?.status_text || account?.status_label, fallback)
  }

  function displayAccountEnabledStatus(account?: Partial<AccountItem> | null, fallback = '--') {
    return displayAccountFieldText(account?.is_status_text || account?.is_status_label, fallback)
  }
</script>

<style scoped lang="scss">
  .payment-account-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .payment-account-page :deep(.el-pagination__total) {
    display: none;
  }

  .channel-cell,
  .identifier-cell,
  .amount-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  :deep(.operation-column-cell .cell) {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 0;
  }

  .status-cell {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: center;
  }

  .cell-title {
    color: var(--el-text-color-primary);
  }

  .cell-sub {
    margin: 0;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 1.6;
    word-break: break-all;
  }

  .memo-text {
    color: var(--el-text-color-regular);
    line-height: 1.6;
    word-break: break-word;
  }

  .mono-text {
    font-family:
      ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New',
      monospace;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.02em;
    word-break: break-all;
  }

  .account-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px 16px;
    align-items: center;
    justify-content: space-between;
    width: 100%;
  }

  .account-toolbar__summary {
    row-gap: 8px;
  }

  .payment-account-page :deep(.account-toolbar .el-button) {
    height: var(--el-component-custom-height);
    min-height: var(--el-component-custom-height);
    padding: 0 14px;
    border-radius: 10px;
    font-size: 13px;
  }

  .payment-account-page :deep(.account-toolbar .el-tag) {
    min-height: 28px;
    padding-inline: 10px;
    border-radius: 999px;
  }

  @media (width <= 768px) {
    .account-toolbar {
      justify-content: flex-start;
    }
  }

  @media (width <= 991px) {
  }
</style>
