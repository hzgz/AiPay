import { displayAdminFixtureText } from '@/utils/adminFixtureText'

type PaymentAccountDisplayValue = null | number | string | undefined

export function beautifyAccountFieldText(value: string) {
  return value
    .replace(/账户ID/g, '账户编号')
    .replace(/通道编码/g, '通道标识')
    .replace(/商户\s*PID/gi, '商户标识')
    .replace(/\bPID\b/gi, '上游标识')
    .replace(/应用\s*ID/gi, '应用编号')
    .replace(/App\s*ID/gi, '应用编号')
    .replace(/API\s*V3/gi, 'V3 接口')
    .replace(/远程\s*API/gi, '远程接口')
    .replace(/指定\s*IP/gi, '固定来源地址')
    .replace(/来源\s*IP/gi, '来源地址')
    .replace(/Cookie/gi, '登录凭证')
    .replace(/QQ号/g, 'QQ 账号')
    .replace(/\bGUID\b/gi, '凭证标识')
    .replace(/([\u4e00-\u9fa5])\s+([\u4e00-\u9fa5])/g, '$1$2')
    .replace(/\s{2,}/g, ' ')
    .trim()
}

export function displayAccountFieldText(value: PaymentAccountDisplayValue, fallback = '--') {
  const normalized = displayAdminFixtureText(value, fallback)
  return normalized === fallback ? fallback : beautifyAccountFieldText(normalized)
}

export function displayAccountFieldLabel(value: PaymentAccountDisplayValue, fallback = '--') {
  return displayAccountFieldText(value, fallback)
}

export function displayAccountFieldPlaceholder(value: PaymentAccountDisplayValue, fallback = '') {
  return displayAccountFieldText(value, fallback)
}

export function displayAccountFieldPlaceholderCompact(
  value: PaymentAccountDisplayValue,
  fallback = ''
) {
  return displayAccountFieldText(value, fallback)
    .replace(/^示例[:：]\s*/g, '')
    .replace(/^例如[:：]\s*/g, '')
    .replace(/未填写可留空/g, '选填')
    .replace(/可留空/g, '选填')
    .replace(/，?留空表示不限/g, ' / 不限')
    .trim()
}

export function displayAccountCode(value: PaymentAccountDisplayValue, fallback = '--') {
  return displayAccountFieldText(value, fallback)
}

export function displayAccountTypeLabel(value: PaymentAccountDisplayValue, fallback = '--') {
  return displayAccountFieldText(value, fallback)
}

export function displayAccountTypeSummary(
  typeLabel: PaymentAccountDisplayValue,
  typeCode: PaymentAccountDisplayValue
) {
  const primary = displayAccountTypeLabel(typeLabel)
  const secondary = displayAccountFieldText(typeCode, '')
  return secondary && secondary !== primary ? `${primary} / ${secondary}` : primary
}

export function displayAccountIdentifierValue(
  value: PaymentAccountDisplayValue,
  hasIdentifier: boolean
) {
  return hasIdentifier ? displayAccountFieldText(value) : '--'
}

export function displayAccountIdentifierSource(value: PaymentAccountDisplayValue, fallback = '--') {
  return displayAccountFieldText(value, fallback)
}
