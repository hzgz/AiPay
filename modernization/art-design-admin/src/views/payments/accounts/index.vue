<template>
  <div class="payment-account-page art-full-height">
    <ArtSearchBar
      v-model="searchForm"
      :items="searchItems"
      :showExpand="false"
      @search="handleSearch"
      @reset="handleReset"
    />

    <ElCard class="account-config-card" shadow="never">
      <div class="account-config-bar">
        <div class="account-config-bar__copy">
          <strong>商户通道测试</strong>
          <p>控制商户端测试开关与默认值。</p>
        </div>

        <div class="account-config-bar__controls">
          <ElTag size="small" :type="channelTestPayConfig.enabled ? 'primary' : 'info'" effect="plain">
            {{ channelTestPayConfig.enabled ? '已开启' : '未开启' }}
          </ElTag>
          <ElSwitch
            v-model="channelTestPayConfig.enabled"
            :disabled="testPayConfigLoading || testPaySaving"
            inline-prompt
            active-text="开"
            inactive-text="关"
          />
          <ElInput
            v-model.trim="channelTestPayConfig.amount"
            class="account-config-bar__amount"
            maxlength="12"
            inputmode="decimal"
            placeholder="0.01"
            :disabled="testPayConfigLoading || testPaySaving"
          >
            <template #append>元</template>
          </ElInput>
          <ElInput
            v-model.trim="channelTestPayConfig.name"
            class="account-config-bar__name"
            maxlength="40"
            placeholder="默认收款人"
            :disabled="testPayConfigLoading || testPaySaving"
          />
          <ElButton
            size="small"
            type="primary"
            :loading="testPaySaving"
            :disabled="testPayConfigLoading"
            @click="saveChannelTestPayConfig"
          >
            保存测试设置
          </ElButton>
        </div>
      </div>
    </ElCard>

    <ElCard class="art-table-card" shadow="never">
      <ArtTableHeader v-model:columns="columnChecks" :loading="loading" @refresh="getAccountList">
        <template #left>
          <div class="account-toolbar">
            <ElSpace wrap>
              <ElButton v-if="hasCreateAuth" size="small" type="primary" @click="openCreateDialog">
                新增账户
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
              <ElTag size="small" effect="plain">账户 {{ pagination.total }}</ElTag>
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
                :type="channelTestPayConfig.enabled ? 'primary' : 'info'"
                effect="plain"
              >
                {{ channelTestPaySummaryText }}
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

    <PaymentAccountDetailDrawer
      v-model="detailVisible"
      :detail-loading="detailLoading"
      :active-account="activeAccount"
      :has-edit-auth="hasAuth('edit')"
      :has-status-auth="hasStatusAuth"
      :has-delete-auth="hasDeleteAuth"
      :can-edit-credentials="
        Boolean(activeAccount && supportsCredentialEditCode(activeAccount.code))
      "
      @edit="openEditDialog()"
      @edit-credentials="openCredentialDialog()"
      @edit-status="openStatusDialog()"
      @delete="handleDeleteAccount()"
    />

    <PaymentAccountCreateDialog
      v-model="createVisible"
      :create-form="createForm"
      :has-create-auth="hasCreateAuth"
      :creating-account="creatingAccount"
      :is-create-form-ready="isCreateFormReady"
      :payment-method-options="paymentMethodOptions"
      :filtered-create-plugin-options="filteredCreatePluginOptions"
      :active-create-meta="activeCreateMeta"
      :create-qr-url-editor="createQrUrlEditor"
      :create-extra-value-editor="createExtraValueEditor"
      :active-create-qr-url-label="activeCreateQrUrlLabel"
      :active-create-qr-url-placeholder="activeCreateQrUrlPlaceholder"
      :active-create-qr-image-button-text="activeCreateQrImageButtonText"
      :active-create-qr-preview-alt="activeCreateQrPreviewAlt"
      :is-asset-uploading="(field) => isAssetUploading('create', field)"
      :on-payment-method-change="handleCreatePaymentMethodChange"
      :on-plugin-change="handleCreatePluginChange"
      :on-clear-field="(field) => clearScopedCredentialField('create', field)"
      :on-credential-image-upload="
        (options) => handleCredentialImageUploadRequest(options, 'create')
      "
      :on-qr-decode-upload="
        (options, field) => handleQrDecodeUploadRequest(options, 'create', field)
      "
      :resolve-credential-asset-url="resolveCredentialAssetUrl"
      @submit="submitCreate"
    />

    <PaymentAccountLimitDialog
      v-model="editVisible"
      :active-account="activeAccount"
      :edit-form="editForm"
      :saving-edit="savingEdit"
      :can-submit="hasAuth('edit')"
      @submit="submitEdit"
    />

    <PaymentAccountCredentialDialog
      v-model="credentialVisible"
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
      :can-submit="hasAuth('edit')"
      :is-asset-uploading="(field) => isAssetUploading('credential', field)"
      :on-clear-field="(field) => clearScopedCredentialField('credential', field)"
      :on-credential-image-upload="
        (options) => handleCredentialImageUploadRequest(options, 'credential')
      "
      :on-qr-decode-upload="
        (options, field) => handleQrDecodeUploadRequest(options, 'credential', field)
      "
      :resolve-credential-asset-url="resolveCredentialAssetUrl"
      @submit="submitCredentialEdit"
    />

    <PaymentAccountStatusDialog
      v-model="statusVisible"
      :status-form="statusForm"
      :saving-status="savingStatus"
      :can-submit="hasStatusAuth"
      @submit="submitStatus"
    />
  </div>
</template>

<script setup lang="ts">
  import { ElButton, ElInput, ElMessage, ElMessageBox, ElSwitch, ElTag } from 'element-plus'
  import { fetchGetSystemConfigSummary, fetchUpdateSystemConfigGroup } from '@/api/config'
  import { useRoute } from 'vue-router'
  import { useAuth } from '@/hooks'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import {
    fetchAuditPaymentAccountBatchDelete,
    fetchBatchDeletePaymentAccounts,
    fetchCreatePaymentAccount,
    fetchDeletePaymentAccount,
    fetchGetPaymentAccountDeleteAudit,
    fetchGetPaymentAccountDetail,
    fetchGetPaymentAccountList,
    fetchUpdatePaymentAccount,
    fetchUpdatePaymentAccountCredentials,
    fetchUpdatePaymentAccountStatus
  } from '@/api/paymentAccounts'
  import { displayAdminMaskedPreview } from '@/utils/adminFixtureText'
  import {
    displayAccountCode,
    displayAccountFieldText,
    displayAccountIdentifierSource,
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
  import {
    createEmptyPaymentAccountCreateForm,
    createEmptyPaymentAccountCredentialForm,
    usePaymentAccountFormState
  } from './modules/paymentAccountFormState'
  import {
    buildPaymentAccountEditableFromItem,
    buildPaymentAccountStatusPayload,
    buildPaymentAccountUpdatePayload,
    createEmptyPaymentAccountEditForm,
    createEmptyPaymentAccountStatusForm,
    syncPaymentAccountEditForm,
    syncPaymentAccountStatusForm
  } from './modules/paymentAccountMaintenanceState'
  import PaymentAccountCreateDialog from './modules/PaymentAccountCreateDialog.vue'
  import PaymentAccountCredentialDialog from './modules/PaymentAccountCredentialDialog.vue'
  import PaymentAccountDetailDrawer from './modules/PaymentAccountDetailDrawer.vue'
  import PaymentAccountLimitDialog from './modules/PaymentAccountLimitDialog.vue'
  import PaymentAccountStatusDialog from './modules/PaymentAccountStatusDialog.vue'

  defineOptions({ name: 'PaymentAccounts' })

  type AccountItem = Api.Payments.AccountListItem
  type AccountSummary = Api.Payments.AccountSummary
  type AccountEditable = Api.Payments.AccountEditable

  const route = useRoute()

  const tableRef = ref<{ elTableRef?: { clearSelection?: () => void } } | null>(null)
  const loading = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const createVisible = ref(false)
  const editVisible = ref(false)
  const credentialVisible = ref(false)
  const statusVisible = ref(false)
  const creatingAccount = ref(false)
  const savingEdit = ref(false)
  const savingCredential = ref(false)
  const savingStatus = ref(false)
  const testPayConfigLoading = ref(false)
  const testPaySaving = ref(false)
  const { hasAuth } = useAuth()
  const hasCreateAuth = computed(() => hasAuth('add'))
  const hasStatusAuth = computed(() => hasAuth('status') || hasAuth('is_status'))
  const hasDeleteAuth = computed(() => hasAuth('remove'))
  const hasBatchDeleteAuth = computed(() => hasAuth('batchRemove'))
  const canManageTestPayConfig = computed(() => hasAuth('index'))
  const accountList = ref<AccountItem[]>([])
  const selectedAccounts = ref<AccountItem[]>([])
  const activeAccount = ref<AccountItem | null>(null)
  const editableAccount = ref<AccountEditable | null>(null)
  const channelTestPayConfig = reactive({
    enabled: false,
    amount: '0.01',
    name: '通道测试支付'
  })

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
  const channelTestPaySummaryText = computed(() => {
    if (!channelTestPayConfig.enabled) {
      return '测试 未开启'
    }

    const amount = String(channelTestPayConfig.amount || '').trim()
    return amount ? `测试 ${amount} 元` : '测试 已开启'
  })

  const searchForm = ref<{
    keyword?: string
    user_id?: string
    type?: string
    status?: string
    is_status?: string
    date_range?: string[]
  }>({})

  const createForm = reactive(createEmptyPaymentAccountCreateForm())
  const editForm = reactive(createEmptyPaymentAccountEditForm())
  const credentialForm = reactive(createEmptyPaymentAccountCredentialForm())

  const statusForm = reactive(createEmptyPaymentAccountStatusForm())

  const {
    paymentMethodOptions,
    filteredCreatePluginOptions,
    activeCreateMeta,
    activeCredentialMeta,
    isCreateFormReady,
    createQrUrlEditor,
    createExtraValueEditor,
    credentialQrUrlEditor,
    credentialExtraValueEditor,
    activeCreateQrUrlLabel,
    activeCreateQrUrlPlaceholder,
    activeCreateQrImageButtonText,
    activeCreateQrPreviewAlt,
    activeCredentialQrUrlLabel,
    activeCredentialQrUrlPlaceholder,
    activeCredentialQrImageButtonText,
    activeCredentialQrPreviewAlt,
    loadCreatePluginCatalog,
    handleCreatePaymentMethodChange,
    handleCreatePluginChange,
    supportsCredentialEditCode,
    isAssetUploading,
    clearScopedCredentialField,
    handleCredentialImageUploadRequest,
    handleQrDecodeUploadRequest,
    resolveCredentialAssetUrl,
    buildCreatePayload,
    buildCredentialPayload,
    syncCredentialForm
  } = usePaymentAccountFormState({
    createForm,
    credentialForm,
    activeAccount
  })

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '搜索账户编号、商户、通道、备注或已知标识'
      }
    },
    {
      label: '商户编号',
      key: 'user_id',
      type: 'input',
      props: {
        placeholder: '按商户编号筛选'
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
      prop: 'merchant_display',
      label: '商户',
      minWidth: 180,
      formatter: (row) =>
        h('div', { class: 'merchant-cell' }, [
          h('strong', { class: 'cell-title' }, displayAccountFieldText(row.merchant_display)),
          h('p', { class: 'cell-sub' }, `商户编号：${row.user_id || '--'}`)
        ])
    },
    {
      prop: 'code_label',
      label: '通道',
      minWidth: 180,
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
        h(ElTag, { type: tagType(row.type_tag), effect: 'light' }, () =>
          displayAccountTypeLabel(row.type_label)
        )
    },
    {
      prop: 'identifier_masked',
      label: '标识',
      minWidth: 180,
      formatter: (row) =>
        h('div', { class: 'identifier-cell' }, [
          h(
            'strong',
            { class: 'mono-text' },
            displayAccountIdentifier(row.identifier_masked, row.has_identifier)
          ),
          h(
            'p',
            { class: 'cell-sub' },
            row.has_identifier
              ? `${displayAccountIdentifierSource(row.identifier_source)} / 长度 ${row.identifier_length}`
              : displayAccountIdentifierSource(row.identifier_source)
          )
        ])
    },
    {
      prop: 'status_label',
      label: '状态',
      minWidth: 150,
      align: 'center' as const,
      formatter: (row) =>
        h('div', { class: 'status-cell' }, [
          h(ElTag, { type: tagType(row.status_type), effect: 'light' }, () => row.status_label),
          h(
            ElTag,
            { type: tagType(row.is_status_type), effect: 'plain' },
            () => row.is_status_label
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
          h('p', { class: 'cell-sub' }, `已付 ${row.paid_order_count} / 总计 ${row.order_count}`)
        ])
    },
    {
      prop: 'memo_label',
      label: '备注',
      minWidth: 160,
      formatter: (row) =>
        h('span', { class: 'memo-text' }, displayAccountFieldText(row.memo_label, '无备注'))
    },
    {
      prop: 'update_time',
      label: '更新时间',
      minWidth: 150,
      formatter: (row) => row.update_time || row.create_time || '--'
    },
    {
      prop: 'operation',
      label: '操作',
      width: hasDeleteAuth.value ? 132 : 78,
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
    void loadChannelTestPayConfig()
    void loadCreatePluginCatalog(false)
  })

  function normalizeSwitchValue(value: unknown) {
    return ['1', 'true', 'yes', 'on'].includes(
      String(value ?? '')
        .trim()
        .toLowerCase()
    )
  }

  function pickConfigValue(
    fields: Api.Configs.ConfigItem[],
    key: string,
    fallback = ''
  ) {
    const field = fields.find((item) => item.key === key)
    return String(field?.editable_value ?? field?.value ?? fallback).trim()
  }

  async function loadChannelTestPayConfig() {
    testPayConfigLoading.value = true

    try {
      const response = await fetchGetSystemConfigSummary({ group: 'transaction_rules' })
      const fields =
        response.editable_forms.find((form) => form.key === 'transaction_rules')?.fields || []

      channelTestPayConfig.enabled = normalizeSwitchValue(pickConfigValue(fields, 'is_channelPay'))
      channelTestPayConfig.amount = pickConfigValue(fields, 'demopay_money', '0.01') || '0.01'
      channelTestPayConfig.name =
        pickConfigValue(fields, 'demopay_name', '通道测试支付') || '通道测试支付'
    } catch {
      ElMessage.error('加载商户通道测试设置失败。')
    } finally {
      testPayConfigLoading.value = false
    }
  }

  async function saveChannelTestPayConfig() {
    if (!canManageTestPayConfig.value) {
      ElMessage.warning('当前账号没有修改测试支付设置的权限。')
      return
    }

    const amount = String(channelTestPayConfig.amount || '').trim()
    if (!/^\d+(?:\.\d{1,2})?$/.test(amount) || Number(amount) <= 0) {
      ElMessage.warning('测试金额必须是大于 0 的数字，最多保留两位小数。')
      return
    }

    testPaySaving.value = true

    try {
      await fetchUpdateSystemConfigGroup({
        group: 'transaction_rules',
        values: {
          is_channelPay: channelTestPayConfig.enabled,
          demopay_money: amount,
          demopay_name: String(channelTestPayConfig.name || '').trim() || '通道测试支付'
        }
      })

      await loadChannelTestPayConfig()
      ElMessage.success('商户通道测试设置已更新。')
    } finally {
      testPaySaving.value = false
    }
  }

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
      const response = await fetchGetPaymentAccountList({
        current: pagination.current,
        size: pagination.size,
        keyword: searchForm.value.keyword,
        user_id: searchForm.value.user_id,
        type: searchForm.value.type,
        status: searchForm.value.status,
        is_status: searchForm.value.is_status,
        start_date: searchForm.value.date_range?.[0],
        end_date: searchForm.value.date_range?.[1]
      })
      accountList.value = response.records
      pagination.current = response.current
      pagination.size = response.size
      pagination.total = response.total
      Object.assign(summary, response.summary || emptySummary())
    } catch {
      ElMessage.error('加载收款账户列表失败。')
    } finally {
      loading.value = false
    }
  }

  function handleSearch(params: Record<string, unknown>) {
    pagination.current = 1
    clearAccountSelection()
    searchForm.value = {
      keyword: params.keyword as string | undefined,
      user_id: params.user_id as string | undefined,
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
      ElMessage.warning('您没有新增收款账户的权限。')
      return
    }

    await loadCreatePluginCatalog()
    Object.assign(createForm, createEmptyPaymentAccountCreateForm())
    createVisible.value = true
  }

  async function submitCreate() {
    if (!hasCreateAuth.value) {
      ElMessage.warning('您没有新增收款账户的权限。')
      return
    }

    const payload = buildCreatePayload()
    if (!payload) {
      return
    }

    creatingAccount.value = true
    try {
      const response = await fetchCreatePaymentAccount(payload)
      createVisible.value = false
      clearAccountSelection()
      await getAccountList()
      ElMessage.success(
        `收款账户 ${displayAccountCode(response.created_account_label || `#${response.created_account_id}`)} 已创建。`
      )
    } finally {
      creatingAccount.value = false
    }
  }

  async function openDetail(row: AccountItem) {
    activeAccount.value = row
    editableAccount.value = buildPaymentAccountEditableFromItem(row)
    detailVisible.value = true
    detailLoading.value = true

    try {
      const response = await fetchGetPaymentAccountDetail(row.id)
      if (response.item) {
        applyAccountDetail(response.item, response.editable)
      }
    } catch {
      ElMessage.error('加载收款账户详情失败。')
    } finally {
      detailLoading.value = false
    }
  }

  function openEditDialog(row?: AccountItem) {
    const target = row || activeAccount.value
    if (!target) {
      return
    }

    activeAccount.value = target
    syncPaymentAccountEditForm(
      editForm,
      editableAccount.value || buildPaymentAccountEditableFromItem(target)
    )
    editVisible.value = true
  }

  function openCredentialDialog(row?: AccountItem) {
    const target = row || activeAccount.value
    if (!target) {
      return
    }

    if (!supportsCredentialEditCode(target.code)) {
      return
    }

    activeAccount.value = target
    syncCredentialForm(
      editableAccount.value || buildPaymentAccountEditableFromItem(target),
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
    syncPaymentAccountStatusForm(
      statusForm,
      editableAccount.value || buildPaymentAccountEditableFromItem(target)
    )
    statusVisible.value = true
  }

  async function submitEdit() {
    if (!activeAccount.value) {
      return
    }

    const payload = buildPaymentAccountUpdatePayload(editForm)
    if (!payload) {
      return
    }

    savingEdit.value = true
    try {
      const response = await fetchUpdatePaymentAccount(activeAccount.value.id, payload)

      if (response.item) {
        applyAccountDetail(response.item, response.editable)
      }

      await getAccountList()
      editVisible.value = false
      ElMessage.success('收款账户限额已更新。')
    } finally {
      savingEdit.value = false
    }
  }

  async function submitCredentialEdit() {
    if (!activeAccount.value) {
      return
    }

    const payload = buildCredentialPayload(activeAccount.value.code)
    if (!payload) {
      return
    }

    savingCredential.value = true
    try {
      const response = await fetchUpdatePaymentAccountCredentials(activeAccount.value.id, payload)

      if (response.item) {
        applyAccountDetail(response.item, response.editable)
      }

      await getAccountList()
      credentialVisible.value = false
      ElMessage.success('收款账户凭证已更新。')
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
      const response = await fetchUpdatePaymentAccountStatus(
        activeAccount.value.id,
        buildPaymentAccountStatusPayload(statusForm)
      )

      if (response.item) {
        applyAccountDetail(response.item, {
          ...(editableAccount.value || buildPaymentAccountEditableFromItem(response.item)),
          status: response.item.status,
          is_status: response.item.is_status
        })
      }

      await getAccountList()
      statusVisible.value = false
      ElMessage.success('收款账户状态已更新。')
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
      ElMessage.warning('您没有删除收款账户的权限。')
      return
    }

    try {
      const response = await fetchGetPaymentAccountDeleteAudit(target.id)
      const audit = response.audit
      const title = displayAccountCode(target.code_label, `收款账户 #${target.id}`)

      if (!audit.can_delete) {
        await ElMessageBox.alert(buildDeleteBlockedMessage(audit, title), '删除受限', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildDeletePromptMessage(audit, title),
        '删除收款账户',
        {
          confirmButtonText: '删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 后继续。`
        }
      )

      const deleteResponse = await fetchDeletePaymentAccount(target.id, {
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
        `收款账户 ${displayAccountCode(deleteResponse.deleted_account_label || title)} 已删除，已清理 ${deleteResponse.audit.summary.delete_row_count} 条关联数据。`
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
      ElMessage.warning('您没有批量删除收款账户的权限。')
      return
    }

    if (selectedAccounts.value.length === 0) {
      ElMessage.warning('请至少选择一个收款账户。')
      return
    }

    const accountIds = selectedAccounts.value.map((item) => item.id)

    try {
      const response = await fetchAuditPaymentAccountBatchDelete({
        account_ids: accountIds
      })
      const audit = response.audit

      if (!audit.can_delete_all) {
        await ElMessageBox.alert(
          buildBatchDeleteBlockedMessage(audit, {
            entityLabel: '收款账户',
            blockedReasonFallback: '该收款账户当前不可删除。',
            entityPrefix: '收款账户'
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
        buildBatchDeletePromptMessage(audit, '收款账户'),
        '批量删除收款账户',
        {
          confirmButtonText: '批量删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 后继续。`
        }
      )

      const deleteResponse = await fetchBatchDeletePaymentAccounts({
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
        `已删除 ${deleteResponse.deleted_count} 个收款账户，已清理 ${deleteResponse.audit.summary.delete_row_count} 条关联数据。`
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
    editableAccount.value = editable || buildPaymentAccountEditableFromItem(item)
    syncPaymentAccountEditForm(editForm, editableAccount.value)
    syncCredentialForm(editableAccount.value, item.code)
    syncPaymentAccountStatusForm(statusForm, editableAccount.value)
  }

  function displayAccountIdentifier(
    value: null | number | string | undefined,
    hasIdentifier: boolean
  ) {
    return displayAdminMaskedPreview(value, hasIdentifier ? '已脱敏标识' : '--', '已脱敏标识')
  }

  function clearAccountSelection() {
    selectedAccounts.value = []
    tableRef.value?.elTableRef?.clearSelection?.()
  }
</script>

<style scoped lang="scss">
  .payment-account-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .account-config-card :deep(.el-card__body) {
    padding: 18px 20px;
  }

  .account-config-bar {
    display: flex;
    flex-direction: column;
    gap: 14px;
    align-items: stretch;
  }

  .account-config-bar__copy {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 4px;
  }

  .account-config-bar__copy strong {
    color: var(--el-text-color-primary);
    font-size: 15px;
    line-height: 1.3;
  }

  .account-config-bar__copy p {
    margin: 0;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 1.6;
  }

  .account-config-bar__controls {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 12px;
    align-items: center;
    justify-content: flex-start;
  }

  .account-config-bar__amount {
    width: 132px;
  }

  .account-config-bar__name {
    width: 176px;
  }

  .merchant-cell,
  .channel-cell,
  .identifier-cell,
  .amount-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
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
    .account-config-bar__controls {
      justify-content: flex-start;
    }

    .account-config-bar__amount,
    .account-config-bar__name {
      width: 100%;
    }

    .account-toolbar {
      justify-content: flex-start;
    }
  }
</style>
