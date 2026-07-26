/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

import { displayAdminFixtureText } from '@/utils/adminFixtureText'
import { displayAccountCode } from '@/views/shared/paymentAccountDisplay'

type DeleteAuditLike = {
  blocking_reasons: string[]
  channel_label?: null | string
  confirmation_phrase: string
  merchant_display?: null | string
  summary: {
    last_selected_pool_count: number | string
    order_count: number | string
    pool_item_count: number | string
  }
  warnings: string[]
}

type BatchDeleteAuditLike = {
  confirmation_phrase: string
  items: Array<{
    account_id: number | string
    account_label?: null | string
    blocking_reasons: string[]
    can_delete: boolean
  }>
  summary: {
    blocked_count: number | string
    deletable_count: number | string
    last_selected_pool_count: number | string
    missing_count: number | string
    order_count: number | string
    pool_item_count: number | string
    requested_count: number | string
  }
  warnings: string[]
}

type PaymentAccountBatchMessageOptions = {
  blockedHeader?: string
  blockedReasonFallback?: string
  entityLabel?: string
  entityPrefix?: string
}

type PaymentAccountTagType = 'success' | 'warning' | 'info' | 'danger' | 'primary' | undefined

export function createEmptyPaymentAccountSummary() {
  return {
    online_count: 0,
    offline_count: 0,
    enabled_count: 0,
    disabled_count: 0,
    identifier_ready_count: 0,
    credential_ready_count: 0,
    paid_order_count: 0,
    paid_amount: 0
  }
}

export function formatPaymentAccountAmount(
  value: null | number | string | undefined,
  digits = 2
) {
  return Number(value || 0).toLocaleString('zh-CN', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits
  })
}

export function formatPaymentAccountLimit(
  count: null | number | string | undefined,
  amount: null | string | undefined
) {
  const amountLabel = amount ? amount : '不限'
  return `${count ?? 0} 笔 / ${amountLabel}`
}

export function isPaymentAccountDecimalValue(value: string) {
  if (!value) {
    return true
  }

  return /^\d+(?:\.\d{1,2})?$/.test(value)
}

export function resolvePaymentAccountTagType(value: string): PaymentAccountTagType {
  if (
    value === 'success' ||
    value === 'warning' ||
    value === 'info' ||
    value === 'danger' ||
    value === 'primary'
  ) {
    return value
  }

  return 'info'
}

export function buildPaymentAccountDeleteBlockedMessage(
  audit: DeleteAuditLike,
  title: string
) {
  return [
    `${title} 当前不可删除。`,
    ...audit.blocking_reasons.map((item) => displayAdminFixtureText(item, item)),
    ...(audit.warnings.length > 0
      ? ['', ...audit.warnings.map((item) => displayAdminFixtureText(item, item))]
      : [])
  ]
    .filter(Boolean)
    .join('\n')
}

export function buildPaymentAccountDeletePromptMessage(audit: DeleteAuditLike, title: string) {
  return [
    `确认删除 ${title} 吗？`,
    audit.merchant_display
      ? `所属商户：${displayAdminFixtureText(audit.merchant_display, audit.merchant_display)}`
      : null,
    audit.channel_label ? `关联通道：${displayAccountCode(audit.channel_label)}` : null,
    `关联订单：${audit.summary.order_count}`,
    `轮询池分配：${audit.summary.pool_item_count}`,
    `轮询游标重置：${audit.summary.last_selected_pool_count}`,
    ...(audit.warnings.length > 0
      ? ['', ...audit.warnings.map((item) => displayAdminFixtureText(item, item))]
      : []),
    '',
    `请输入 ${audit.confirmation_phrase} 以继续。`
  ]
    .filter(Boolean)
    .join('\n')
}

export function buildPaymentAccountBatchDeleteBlockedMessage(
  audit: BatchDeleteAuditLike,
  options: PaymentAccountBatchMessageOptions = {}
) {
  const entityLabel = options.entityLabel || '账号'
  const entityPrefix = options.entityPrefix || entityLabel
  const blockedHeader = options.blockedHeader || '受阻账号：'
  const blockedReasonFallback = options.blockedReasonFallback || '该账号当前不可删除。'
  const blockedLines = audit.items
    .filter((item) => !item.can_delete)
    .map((item) => {
      const label = displayAccountCode(item.account_label, `${entityPrefix} #${item.account_id}`)
      const reason =
        displayAdminFixtureText(item.blocking_reasons[0], item.blocking_reasons[0]) ||
        blockedReasonFallback

      return `${label}：${reason}`
    })

  return [
    `已选账号：${audit.summary.requested_count}`,
    `可删除：${audit.summary.deletable_count}`,
    `受阻：${audit.summary.blocked_count}`,
    `缺失：${audit.summary.missing_count}`,
    '',
    ...audit.warnings.map((item) => displayAdminFixtureText(item, item)),
    ...(blockedLines.length > 0 ? ['', blockedHeader, ...blockedLines] : [])
  ].join('\n')
}

export function buildPaymentAccountBatchDeletePromptMessage(
  audit: BatchDeleteAuditLike,
  entityLabel = '账号'
) {
  return [
    `确认删除 ${audit.summary.deletable_count} 个已选${entityLabel}吗？`,
    `关联订单：${audit.summary.order_count}`,
    `轮询池分配：${audit.summary.pool_item_count}`,
    `轮询游标重置：${audit.summary.last_selected_pool_count}`,
    ...(audit.warnings.length > 0
      ? ['', ...audit.warnings.map((item) => displayAdminFixtureText(item, item))]
      : []),
    '',
    `请输入 ${audit.confirmation_phrase} 以继续。`
  ].join('\n')
}

export function escapePaymentAccountConfirmation(value: string) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

export function isPaymentAccountDialogCancel(error: unknown) {
  return error === 'cancel' || error === 'close'
}
