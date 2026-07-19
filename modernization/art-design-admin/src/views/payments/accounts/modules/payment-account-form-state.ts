import { ElMessage } from 'element-plus'
import type { UploadRequestOptions } from 'element-plus'
import { computed, reactive, ref, watch, type Ref } from 'vue'
import { resolveBackendOrigin } from '@/utils/http/base'
import {
  fetchDecodePaymentAccountCredentialImage,
  fetchUploadPaymentAccountCredentialImage
} from '@/api/payment-accounts'
import { fetchGetPaymentPlugins } from '@/api/system-manage'
import { displayAccountFieldLabel } from '@/views/shared/paymentAccountDisplay'
import {
  defaultCredentialQrType,
  isAlipayOfficialCertMode,
  isJiaofeiyiCredentialCode,
  isMultiModeCredentialCode,
  isRequiredCredentialCode,
  isWxpaySoftwareRewardMode,
  normalizeQrTypeSelection,
  parseModeCsv,
  resolveAccountFieldEditor,
  resolveAccountQrUrlLabel,
  resolveAccountQrUrlPlaceholder,
  resolveCredentialImageButtonText,
  resolveCredentialImagePreviewAlt,
  resolveNormalizedQrType,
  type PaymentAccountCredentialField
} from '@/views/shared/paymentAccountCredential'
import {
  ACCOUNT_CODE_META,
  ACCOUNT_METHOD_TYPE_MAP,
  ACCOUNT_METHOD_TYPES_MAP,
  PAYMENT_METHOD_DISPLAY_ORDER,
  PAYMENT_METHOD_LABEL_MAP,
  getAccountCodeMeta,
  type PaymentAccountCreateCode as AccountCreateCode,
  type PaymentAccountMethodType as CreatePaymentMethodType
} from '@/views/shared/paymentAccountMeta'
import {
  isPaymentAccountDecimalValue as isDecimalValue
} from '@/views/shared/paymentAccountPageShared'

type AccountItem = Api.Payments.AccountListItem
type AccountEditable = Api.Payments.AccountEditable
type AccountCredentialField = PaymentAccountCredentialField

export type AccountFormScope = 'create' | 'credential'

export type CreatePluginCatalogItem = {
  code: AccountCreateCode
  name: string
  methodType: CreatePaymentMethodType
  methodLabel: string
}

function normalizeSupportedMethodType(value: unknown): CreatePaymentMethodType | null {
  const normalized = String(value || '')
    .trim()
    .toLowerCase()

  const resolved =
    normalized === 'wechat' ? 'wxpay' : normalized === 'qq' ? 'qqpay' : normalized

  return Object.prototype.hasOwnProperty.call(PAYMENT_METHOD_LABEL_MAP, resolved)
    ? (resolved as CreatePaymentMethodType)
    : null
}

export type PaymentAccountCreateForm = {
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

export type PaymentAccountCredentialForm = {
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

type UsePaymentAccountFormStateOptions = {
  activeAccount: Ref<AccountItem | null>
  createForm: PaymentAccountCreateForm
  credentialForm: PaymentAccountCredentialForm
}

export function createEmptyPaymentAccountCreateForm(): PaymentAccountCreateForm {
  return {
    user_id: '',
    payment_method_type: '',
    plugin_code: '',
    code: '' as '' | AccountCreateCode,
    pid: '',
    identifier: '',
    qr_type: '',
    qr_url: '',
    cookie: '',
    memo: '',
    remark: '',
    wx_guid: '',
    cloud_id: '',
    extra_value: '',
    daymaxcount: '0',
    daymaxmoney: '',
    allmaxcount: '0',
    allmaxmoney: '',
    status: false,
    is_status: true
  }
}

export function createEmptyPaymentAccountCredentialForm(): PaymentAccountCredentialForm {
  return {
    pid: '',
    identifier: '',
    qr_type: '',
    qr_url: '',
    cookie: '',
    remark: '',
    wx_guid: '',
    cloud_id: '',
    extra_value: ''
  }
}

export function usePaymentAccountFormState({
  createForm,
  credentialForm,
  activeAccount
}: UsePaymentAccountFormStateOptions) {
  const createPluginOptions = ref<CreatePluginCatalogItem[]>([])
  const assetUploadState = reactive<Record<string, boolean>>({})
  const paymentAssetBaseUrl = resolvePaymentAssetBaseUrl()

  const paymentMethodOptions = computed(() =>
    PAYMENT_METHOD_DISPLAY_ORDER.filter((value) =>
      createPluginOptions.value.some((item) => item.methodType === value)
    ).map((value) => ({
      value,
      label: PAYMENT_METHOD_LABEL_MAP[value]
    }))
  )

  const filteredCreatePluginOptions = computed(() => {
    const paymentMethodType = String(createForm.payment_method_type || '').trim()
    if (paymentMethodType === '') {
      return []
    }

    return createPluginOptions.value.filter((item) => item.methodType === paymentMethodType)
  })

  const activeCreateMeta = computed(
    () => getAccountCodeMeta(createForm.code) || getAccountCodeMeta('alipay_software')!
  )
  const activeCredentialMeta = computed(() => getAccountCodeMeta(activeAccount.value?.code))
  const isCreateFormReady = computed(
    () =>
      Boolean(String(createForm.payment_method_type || '').trim()) &&
      Boolean(String(createForm.plugin_code || '').trim()) &&
      Boolean(String(createForm.code || '').trim())
  )

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

  watch(
    () => createForm.code,
    (nextCode) => {
      const meta = ACCOUNT_CODE_META[nextCode as AccountCreateCode]
      createForm.qr_type = defaultCredentialQrType(nextCode, meta)
    }
  )

  watch([() => createForm.code, () => createForm.qr_type], () => {
    normalizeScopedCredentialState('create')
  })

  watch([() => activeAccount.value?.code, () => credentialForm.qr_type], () => {
    normalizeScopedCredentialState('credential')
  })

  function snapshotCreateGeneralState() {
    return {
      user_id: createForm.user_id,
      payment_method_type: createForm.payment_method_type,
      memo: createForm.memo,
      daymaxcount: createForm.daymaxcount,
      daymaxmoney: createForm.daymaxmoney,
      allmaxcount: createForm.allmaxcount,
      allmaxmoney: createForm.allmaxmoney,
      status: createForm.status,
      is_status: createForm.is_status
    }
  }

  async function loadCreatePluginCatalog(showError = true) {
    try {
      const response = await fetchGetPaymentPlugins()
      createPluginOptions.value = (Array.isArray(response.items) ? response.items : [])
        .flatMap((item) => {
          const code = String(item.code || '').trim() as '' | AccountCreateCode
          const meta = code ? getAccountCodeMeta(code) : null

          if (!code || !meta || !item.installed || !item.enabled) {
            return []
          }

          const fallbackMethodTypes =
            ACCOUNT_METHOD_TYPES_MAP[code] ??
            (ACCOUNT_METHOD_TYPE_MAP[code] ? [ACCOUNT_METHOD_TYPE_MAP[code]] : [])
          const methodTypes = Array.from(
            new Set(
              (
                Array.isArray(item.supported_payment_types) && item.supported_payment_types.length > 0
                  ? item.supported_payment_types
                  : fallbackMethodTypes
              )
                .map((value) => normalizeSupportedMethodType(value))
                .filter((value): value is CreatePaymentMethodType => Boolean(value))
            )
          )

          return methodTypes.map((methodType) => ({
            code,
            name: String(item.name || ACCOUNT_CODE_META[code].label || code).trim(),
            methodType,
            methodLabel: PAYMENT_METHOD_LABEL_MAP[methodType]
          }))
        })
        .sort((left, right) => {
          const methodOrder =
            PAYMENT_METHOD_DISPLAY_ORDER.indexOf(left.methodType) -
            PAYMENT_METHOD_DISPLAY_ORDER.indexOf(right.methodType)

          if (methodOrder !== 0) {
            return methodOrder
          }

          return left.name.localeCompare(right.name, 'zh-CN')
        })
    } catch (_error) {
      if (showError) {
        ElMessage.error('加载支付插件列表失败，请稍后重试。')
      }
    }
  }

  function handleCreatePaymentMethodChange(value: string) {
    const paymentMethodType = String(value || '').trim()
    const preserved = snapshotCreateGeneralState()

    Object.assign(createForm, createEmptyPaymentAccountCreateForm(), preserved, {
      payment_method_type: paymentMethodType
    })
  }

  function handleCreatePluginChange(value: string) {
    const pluginCode = String(value || '').trim() as '' | AccountCreateCode
    const meta = getAccountCodeMeta(pluginCode)
    const preserved = snapshotCreateGeneralState()

    Object.assign(createForm, createEmptyPaymentAccountCreateForm(), preserved, {
      payment_method_type: preserved.payment_method_type,
      plugin_code: pluginCode,
      code: pluginCode,
      qr_type: defaultCredentialQrType(pluginCode, meta)
    })
  }

  function supportsCredentialEditCode(code?: null | string) {
    return Boolean(getAccountCodeMeta(code))
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

      if (!activeCreateMeta.value.supportsCloudId) {
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

    if (!activeCredentialMeta.value.supportsCloudId) {
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

      const response = await fetchUploadPaymentAccountCredentialImage({
        code,
        field: 'qr_url',
        qr_type: qrType,
        file
      })

      setScopedCredentialFieldValue(scope, 'qr_url', String(response.value || '').trim())
      ElMessage.success('二维码图片上传成功。')
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
      const response = await fetchDecodePaymentAccountCredentialImage({
        code,
        field,
        qr_type: qrType,
        file
      })
      const decodedContent = String(response.value || '').trim()
      setScopedCredentialFieldValue(scope, field, decodedContent)
      ElMessage.success('二维码内容解析成功。')
      options.onSuccess?.(response as any)
    } catch (error) {
      const message = error instanceof Error ? error.message : '二维码解析失败，请稍后重试。'
      ElMessage.warning(message)
      options.onError?.((error instanceof Error ? error : new Error(message)) as any)
    } finally {
      setAssetUploading(scope, field, false)
    }
  }

  function buildCreatePayload(): Api.Payments.AccountCreatePayload | null {
    const qrUrlEditor = resolveAccountFieldEditor(createForm.code, 'qr_url', createForm.qr_type)

    trimCreateForm()

    if (!/^[1-9]\d*$/.test(createForm.user_id)) {
      ElMessage.warning('商户编号必须为正整数。')
      return null
    }

    if (!createForm.payment_method_type) {
      ElMessage.warning('请选择支付方式')
      return null
    }

    if (!createForm.plugin_code || !createForm.code) {
      ElMessage.warning('请选择支付插件')
      return null
    }

    if (
      !filteredCreatePluginOptions.value.some(
        (item) => String(item.code || '').trim() === createForm.code
      )
    ) {
      ElMessage.warning('当前支付方式下没有这个插件，请重新选择。')
      return null
    }

    if (!createForm.identifier) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.value.identifierLabel)}`)
      return null
    }

    if (!/^\d+$/.test(createForm.daymaxcount)) {
      ElMessage.warning('单日次数上限必须为非负整数。')
      return null
    }

    if (!/^\d+$/.test(createForm.allmaxcount)) {
      ElMessage.warning('累计次数上限必须为非负整数。')
      return null
    }

    if (!isDecimalValue(createForm.daymaxmoney)) {
      ElMessage.warning('单日金额上限格式不正确。')
      return null
    }

    if (!isDecimalValue(createForm.allmaxmoney)) {
      ElMessage.warning('累计金额上限格式不正确。')
      return null
    }

    if (
      createForm.code === 'alipay_software' &&
      createForm.qr_type === 'pic' &&
      !createForm.qr_url
    ) {
      ElMessage.warning('支付宝软件版图片模式必须上传二维码图片。')
      return null
    }

    if (
      createForm.code === 'wxpay_software' &&
      isWxpaySoftwareRewardMode(createForm.code, createForm.qr_type, createForm.qr_url) &&
      !createForm.qr_url
    ) {
      ElMessage.warning('微信软件版赞赏码模式必须上传赞赏码图片。')
      return null
    }

    if (
      activeCreateMeta.value.supportsPid &&
      createForm.code !== 'alipay_official' &&
      !createForm.pid
    ) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.value.pidLabel)}`)
      return null
    }

    if (activeCreateMeta.value.qrTypeOptions.length > 0) {
      const allowedQrTypes = activeCreateMeta.value.qrTypeOptions.map(
        (option) => option.value
      ) as string[]
      const normalizedQrType = normalizeQrTypeSelection(
        createForm.code,
        createForm.qr_type,
        activeCreateMeta.value
      )

      if (isMultiModeCredentialCode(createForm.code)) {
        const selections = parseModeCsv(normalizedQrType)
        if (
          selections.length === 0 ||
          selections.some((value) => !allowedQrTypes.includes(value))
        ) {
          ElMessage.warning('请选择至少一个有效的可用接口。')
          return null
        }
      } else if (!allowedQrTypes.includes(normalizedQrType)) {
        ElMessage.warning('请选择一个有效的路由模式。')
        return null
      }

      createForm.qr_type = normalizedQrType
    }

    if (createForm.code === 'wxpay_v3' && !createForm.qr_url) {
      ElMessage.warning(`请上传${displayAccountFieldLabel(activeCreateMeta.value.qrUrlLabel)}`)
      return null
    }

    if (
      isRequiredCredentialCode(createForm.code) &&
      activeCreateMeta.value.supportsQrUrl &&
      !createForm.qr_url
    ) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.value.qrUrlLabel)}`)
      return null
    }

    if (
      isRequiredCredentialCode(createForm.code) &&
      activeCreateMeta.value.supportsCookie &&
      !(createForm.code === 'alipay_official' && isAlipayOfficialCertMode(createForm.remark)) &&
      !createForm.cookie
    ) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.value.cookieLabel)}`)
      return null
    }

    if (
      activeCreateMeta.value.supportsRemark &&
      !isJiaofeiyiCredentialCode(createForm.code) &&
      createForm.code !== 'alipay_official' &&
      !createForm.remark
    ) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.value.remarkLabel)}`)
      return null
    }

    if (
      activeCreateMeta.value.supportsWxGuid &&
      createForm.code !== 'alipay_official' &&
      !createForm.wx_guid
    ) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.value.wxGuidLabel)}`)
      return null
    }

    if (createForm.code === 'alipay_official' && isAlipayOfficialCertMode(createForm.remark)) {
      if (!createForm.wx_guid) {
        ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.value.wxGuidLabel)}`)
        return null
      }

      if (!createForm.cloud_id) {
        ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.value.cloudIdLabel)}`)
        return null
      }

      if (!createForm.extra_value) {
        ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.value.extraValueLabel)}`)
        return null
      }
    }

    if (
      createForm.code === 'alipay_bill' &&
      activeCreateMeta.value.supportsExtraValue &&
      !createForm.extra_value
    ) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(activeCreateMeta.value.extraValueLabel)}`)
      return null
    }

    if (
      isJiaofeiyiCredentialCode(createForm.code) &&
      createForm.extra_value &&
      !/^https?:\/\/.+/i.test(createForm.extra_value)
    ) {
      ElMessage.warning('远程 API 地址必须以 http:// 或 https:// 开头。')
      return null
    }

    if (
      isJiaofeiyiCredentialCode(createForm.code) &&
      createForm.cloud_id &&
      !/^https?:\/\/.+/i.test(createForm.cloud_id)
    ) {
      ElMessage.warning('代理 IP API 地址必须以 http:// 或 https:// 开头。')
      return null
    }

    return {
      user_id: createForm.user_id,
      payment_method_type: createForm.payment_method_type,
      plugin_code: createForm.plugin_code,
      code: createForm.code,
      identifier: createForm.identifier,
      pid: activeCreateMeta.value.supportsPid ? createForm.pid : '',
      qr_type: createForm.qr_type,
      qr_url:
        activeCreateMeta.value.supportsQrUrl && qrUrlEditor !== 'hidden' ? createForm.qr_url : '',
      cookie: activeCreateMeta.value.supportsCookie ? createForm.cookie : '',
      memo: createForm.memo,
      remark: activeCreateMeta.value.supportsRemark ? createForm.remark : '',
      wx_guid: activeCreateMeta.value.supportsWxGuid ? createForm.wx_guid : '',
      cloud_id: activeCreateMeta.value.supportsCloudId ? createForm.cloud_id : '',
      extra_value: activeCreateMeta.value.supportsExtraValue ? createForm.extra_value : '',
      daymaxcount: createForm.daymaxcount,
      daymaxmoney: createForm.daymaxmoney,
      allmaxcount: createForm.allmaxcount,
      allmaxmoney: createForm.allmaxmoney,
      status: createForm.status,
      is_status: createForm.is_status
    }
  }

  function buildCredentialPayload(
    code: string
  ): Api.Payments.AccountCredentialUpdatePayload | null {
    const meta = getAccountCodeMeta(code)
    if (!meta) {
      ElMessage.warning('当前收款账号类型暂不支持编辑凭证。')
      return null
    }

    const qrUrlEditor = resolveAccountFieldEditor(code, 'qr_url', credentialForm.qr_type)

    trimCredentialForm()

    if (!credentialForm.identifier) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(meta.identifierLabel)}`)
      return null
    }

    if (meta.supportsPid && code !== 'alipay_official' && !credentialForm.pid) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(meta.pidLabel)}`)
      return null
    }

    if (meta.qrTypeOptions.length > 0) {
      const allowedQrTypes = meta.qrTypeOptions.map((option) => option.value) as string[]
      const normalizedQrType = normalizeQrTypeSelection(code, credentialForm.qr_type, meta)

      if (isMultiModeCredentialCode(code)) {
        const selections = parseModeCsv(normalizedQrType)
        if (
          selections.length === 0 ||
          selections.some((value) => !allowedQrTypes.includes(value))
        ) {
          ElMessage.warning('请选择至少一个有效的可用接口。')
          return null
        }
      } else if (!allowedQrTypes.includes(normalizedQrType)) {
        ElMessage.warning('请选择一个有效的路由模式。')
        return null
      }

      credentialForm.qr_type = normalizedQrType
    }

    if (code === 'alipay_software' && credentialForm.qr_type === 'pic' && !credentialForm.qr_url) {
      ElMessage.warning('支付宝软件版图片模式必须上传二维码图片。')
      return null
    }

    if (
      code === 'wxpay_software' &&
      isWxpaySoftwareRewardMode(code, credentialForm.qr_type, credentialForm.qr_url) &&
      !credentialForm.qr_url
    ) {
      ElMessage.warning('微信软件版赞赏码模式必须上传赞赏码图片。')
      return null
    }

    if (code === 'wxpay_v3' && !credentialForm.qr_url) {
      ElMessage.warning(`请上传${displayAccountFieldLabel(meta.qrUrlLabel)}`)
      return null
    }

    if (isRequiredCredentialCode(code) && meta.supportsQrUrl && !credentialForm.qr_url) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(meta.qrUrlLabel)}`)
      return null
    }

    if (
      isRequiredCredentialCode(code) &&
      meta.supportsCookie &&
      !(code === 'alipay_official' && isAlipayOfficialCertMode(credentialForm.remark)) &&
      !credentialForm.cookie
    ) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(meta.cookieLabel)}`)
      return null
    }

    if (
      meta.supportsRemark &&
      !isJiaofeiyiCredentialCode(code) &&
      code !== 'alipay_official' &&
      !credentialForm.remark
    ) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(meta.remarkLabel)}`)
      return null
    }

    if (meta.supportsWxGuid && code !== 'alipay_official' && !credentialForm.wx_guid) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(meta.wxGuidLabel)}`)
      return null
    }

    if (code === 'alipay_official' && isAlipayOfficialCertMode(credentialForm.remark)) {
      if (!credentialForm.wx_guid) {
        ElMessage.warning(`请输入${displayAccountFieldLabel(meta.wxGuidLabel)}`)
        return null
      }

      if (!credentialForm.cloud_id) {
        ElMessage.warning(`请输入${displayAccountFieldLabel(meta.cloudIdLabel)}`)
        return null
      }

      if (!credentialForm.extra_value) {
        ElMessage.warning(`请输入${displayAccountFieldLabel(meta.extraValueLabel)}`)
        return null
      }
    }

    if (code === 'alipay_bill' && meta.supportsExtraValue && !credentialForm.extra_value) {
      ElMessage.warning(`请输入${displayAccountFieldLabel(meta.extraValueLabel)}`)
      return null
    }

    if (
      isJiaofeiyiCredentialCode(code) &&
      credentialForm.extra_value &&
      !/^https?:\/\/.+/i.test(credentialForm.extra_value)
    ) {
      ElMessage.warning('远程 API 地址必须以 http:// 或 https:// 开头。')
      return null
    }

    if (
      isJiaofeiyiCredentialCode(code) &&
      credentialForm.cloud_id &&
      !/^https?:\/\/.+/i.test(credentialForm.cloud_id)
    ) {
      ElMessage.warning('代理 IP API 地址必须以 http:// 或 https:// 开头。')
      return null
    }

    return {
      identifier: credentialForm.identifier,
      pid: meta.supportsPid ? credentialForm.pid : '',
      qr_type: credentialForm.qr_type,
      qr_url: meta.supportsQrUrl && qrUrlEditor !== 'hidden' ? credentialForm.qr_url : '',
      cookie: meta.supportsCookie ? credentialForm.cookie : '',
      remark: meta.supportsRemark ? credentialForm.remark : '',
      wx_guid: meta.supportsWxGuid ? credentialForm.wx_guid : '',
      cloud_id: meta.supportsCloudId ? credentialForm.cloud_id : '',
      extra_value: meta.supportsExtraValue ? credentialForm.extra_value : ''
    }
  }

  function syncCredentialForm(editable: AccountEditable, code?: string) {
    const meta = getAccountCodeMeta(code || editable.code)

    credentialForm.pid = editable.pid || ''
    credentialForm.identifier = editable.identifier || ''
    credentialForm.qr_type = resolveNormalizedQrType(
      code || editable.code,
      editable.qr_type,
      meta,
      editable.qr_url
    )
    credentialForm.qr_url = editable.qr_url || ''
    credentialForm.cookie = editable.cookie || ''
    credentialForm.remark = editable.remark || ''
    credentialForm.wx_guid = editable.wx_guid || ''
    credentialForm.cloud_id = editable.cloud_id || ''
    credentialForm.extra_value = editable.extra_value || ''
  }

  function trimCreateForm() {
    createForm.user_id = createForm.user_id.trim()
    createForm.payment_method_type = createForm.payment_method_type.trim()
    createForm.plugin_code = createForm.plugin_code.trim()
    createForm.pid = createForm.pid.trim()
    createForm.identifier = createForm.identifier.trim()
    createForm.qr_type = createForm.qr_type.trim()
    createForm.qr_url = createForm.qr_url.trim()
    createForm.cookie = createForm.cookie.trim()
    createForm.memo = createForm.memo.trim()
    createForm.remark = createForm.remark.trim()
    createForm.wx_guid = createForm.wx_guid.trim()
    createForm.cloud_id = createForm.cloud_id.trim()
    createForm.extra_value = createForm.extra_value.trim()
    createForm.daymaxcount = createForm.daymaxcount.trim()
    createForm.daymaxmoney = createForm.daymaxmoney.trim()
    createForm.allmaxcount = createForm.allmaxcount.trim()
    createForm.allmaxmoney = createForm.allmaxmoney.trim()
  }

  function trimCredentialForm() {
    credentialForm.pid = credentialForm.pid.trim()
    credentialForm.identifier = credentialForm.identifier.trim()
    credentialForm.qr_type = credentialForm.qr_type.trim()
    credentialForm.qr_url = credentialForm.qr_url.trim()
    credentialForm.cookie = credentialForm.cookie.trim()
    credentialForm.remark = credentialForm.remark.trim()
    credentialForm.wx_guid = credentialForm.wx_guid.trim()
    credentialForm.cloud_id = credentialForm.cloud_id.trim()
    credentialForm.extra_value = credentialForm.extra_value.trim()
  }

  return {
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
  }
}

function resolvePaymentAssetBaseUrl() {
  return resolveBackendOrigin()
}



