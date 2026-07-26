/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

export type PaymentAccountCredentialField = 'extra_value' | 'qr_url'
export type PaymentAccountFieldEditor = 'hidden' | 'image' | 'qr-decode' | 'text' | 'textarea'

const MULTI_MODE_CREDENTIAL_CODES = ['alipay_official', 'wxpay_v3'] as const

export type PaymentAccountCodeMetaLike =
  | {
      qrTypeOptions?: ReadonlyArray<{
        value: string
      }>
    }
  | null
  | undefined

export function isRequiredCredentialCode(code?: string | null) {
  return Boolean(
    code && ['alipay_bill', 'alipay_mck', 'alipay_official', 'universal_epay'].includes(code)
  )
}

export function isMultiModeCredentialCode(code?: string | null) {
  return Boolean(
    code &&
      MULTI_MODE_CREDENTIAL_CODES.includes(code as (typeof MULTI_MODE_CREDENTIAL_CODES)[number])
  )
}

export function isJiaofeiyiCredentialCode(code?: string | null) {
  return code === 'jiaofeiyi_alipay' || code === 'jiaofeiyi_wxpay'
}

export function normalizeAlipayOfficialSignMode(value?: null | string) {
  const normalized = String(value || '')
    .trim()
    .toLowerCase()

  if (['1', 'cert', 'certificate', 'cert_mode', 'certmode'].includes(normalized)) {
    return 'cert'
  }

  return 'key'
}

export function isAlipayOfficialCertMode(value?: null | string) {
  return normalizeAlipayOfficialSignMode(value) === 'cert'
}

export function parseModeCsv(value?: null | string) {
  return Array.from(
    new Set(
      String(value || '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean)
    )
  )
}

export function formatModeCsv(values?: ReadonlyArray<string> | null) {
  return Array.from(
    new Set(
      Array.isArray(values) ? values.map((item) => String(item || '').trim()).filter(Boolean) : []
    )
  ).join(',')
}

export function normalizeQrTypeSelection(
  code?: string | null,
  value?: string | null,
  meta?: PaymentAccountCodeMetaLike
) {
  if (!isMultiModeCredentialCode(code)) {
    const normalized = String(value || '').trim()
    return normalized || defaultCredentialQrType(code, meta)
  }

  const allowedValues = (meta?.qrTypeOptions || []).map((option) =>
    String(option.value || '').trim()
  )
  const selected = parseModeCsv(value).filter((item) => allowedValues.includes(item))

  if (selected.length > 0) {
    return formatModeCsv(selected)
  }

  return defaultCredentialQrType(code, meta)
}

export function resolveQrTypeSelections(
  code?: string | null,
  value?: string | null,
  meta?: PaymentAccountCodeMetaLike
) {
  if (!isMultiModeCredentialCode(code)) {
    const normalized = normalizeQrTypeSelection(code, value, meta)
    return normalized ? [normalized] : []
  }

  return parseModeCsv(normalizeQrTypeSelection(code, value, meta))
}

export function resolveQrTypeFieldLabel(code?: string | null) {
  if (code === 'jiaofeiyi_wxpay') {
    return '微信支付模式'
  }

  if (isMultiModeCredentialCode(code)) {
    return '可用接口（可多选）'
  }

  return '路由模式'
}

export function resolveQrTypeFieldPlaceholder(code?: string | null) {
  return isMultiModeCredentialCode(code) ? '选择可用接口' : '选择模式'
}

function isUploadedImageReference(value?: string | null) {
  const normalized = String(value || '').trim()
  if (!normalized) {
    return false
  }

  return /(^\/upload\/)|\.(?:png|jpe?g|gif|bmp)(?:\?.*)?$/i.test(normalized)
}

function normalizeWxpaySoftwareQrType(qrType?: string | null, qrUrl?: string | null) {
  const normalized = String(qrType || '')
    .trim()
    .toLowerCase()

  if (['appreciate', 'reward', 'rewardcode', 'reward_code'].includes(normalized)) {
    return 'appreciate'
  }

  if (
    [
      'personormerchant',
      'person_or_merchant',
      'person-or-merchant',
      'qr',
      'qrcode',
      'qr_code'
    ].includes(normalized)
  ) {
    return 'personOrMerchant'
  }

  return isUploadedImageReference(qrUrl) ? 'appreciate' : 'personOrMerchant'
}

export function isAlipaySoftwarePictureMode(code?: string | null, qrType?: string | null) {
  return code === 'alipay_software' && String(qrType || '').trim().toLowerCase() === 'pic'
}

export function shouldShowAccountIdentifierField(code?: string | null, qrType?: string | null) {
  return !isAlipaySoftwarePictureMode(code, qrType)
}

export function resolveNormalizedQrType(
  code?: string | null,
  qrType?: string | null,
  meta?: PaymentAccountCodeMetaLike,
  qrUrl?: string | null
) {
  if (code === 'wxpay_software') {
    return normalizeWxpaySoftwareQrType(qrType, qrUrl)
  }

  const normalized = String(qrType || '').trim()
  if (normalized) {
    return normalizeQrTypeSelection(code, normalized, meta)
  }

  return defaultCredentialQrType(code, meta)
}

export function isWxpaySoftwareRewardMode(
  code?: string | null,
  qrType?: string | null,
  qrUrl?: string | null
) {
  return code === 'wxpay_software' && normalizeWxpaySoftwareQrType(qrType, qrUrl) === 'appreciate'
}

export function resolveAccountQrUrlLabel(
  code?: string | null,
  qrType?: string | null,
  fallback = '',
  qrUrl?: string | null
) {
  if (code !== 'wxpay_software') {
    return fallback
  }

  return isWxpaySoftwareRewardMode(code, qrType, qrUrl) ? '赞赏码图片' : '二维码内容'
}

export function resolveAccountQrUrlPlaceholder(
  code?: string | null,
  qrType?: string | null,
  fallback = '',
  qrUrl?: string | null
) {
  if (code !== 'wxpay_software') {
    return fallback
  }

  return isWxpaySoftwareRewardMode(code, qrType, qrUrl)
    ? '请上传微信赞赏码图片'
    : '输入二维码内容或上传图片解析'
}

export function resolveCredentialImageButtonText(
  code?: string | null,
  qrType?: string | null,
  hasValue = false,
  qrUrl?: string | null
) {
  if (isWxpaySoftwareRewardMode(code, qrType, qrUrl)) {
    return hasValue ? '重新上传赞赏码' : '上传赞赏码'
  }

  return hasValue ? '重新上传图片' : '上传图片'
}

export function resolveCredentialImagePreviewAlt(
  code?: string | null,
  qrType?: string | null,
  qrUrl?: string | null
) {
  return isWxpaySoftwareRewardMode(code, qrType, qrUrl) ? '赞赏码图片预览' : '二维码图片预览'
}

export function defaultCredentialQrType(code?: string | null, meta?: PaymentAccountCodeMetaLike) {
  if (code === 'alipay_official') {
    return formatModeCsv((meta?.qrTypeOptions || []).map((option) => option.value))
  }

  if (code === 'jiaofeiyi_wxpay') {
    return '1'
  }

  return String(meta?.qrTypeOptions?.[0]?.value || '')
}

export function resolveAccountFieldEditor(
  code: null | string | undefined,
  field: PaymentAccountCredentialField,
  qrType?: null | string
): PaymentAccountFieldEditor {
  if (!code) {
    return 'textarea'
  }

  if (field === 'qr_url') {
    if (code === 'alipay_software') {
      return isAlipaySoftwarePictureMode(code, qrType) ? 'qr-decode' : 'hidden'
    }

    if (code === 'wxpay_software') {
      return isWxpaySoftwareRewardMode(code, qrType) ? 'image' : 'qr-decode'
    }

    if (code === 'qqpay_software') {
      return 'qr-decode'
    }

    if (code === 'jiaofeiyi_alipay' || code === 'jiaofeiyi_wxpay') {
      return 'text'
    }

    if (code === 'universal_epay') {
      return 'text'
    }

    return 'textarea'
  }

  if (code === 'alipay_bill') {
    return 'qr-decode'
  }

  if (code === 'jiaofeiyi_alipay' || code === 'jiaofeiyi_wxpay') {
    return 'text'
  }

  if (code === 'wxpay_v3') {
    return 'text'
  }

  if (code === 'usdt') {
    return 'text'
  }

  return 'textarea'
}
