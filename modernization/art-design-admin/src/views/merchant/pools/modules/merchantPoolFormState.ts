/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

type PoolEditable = Api.Payments.PoolEditable
type PoolListItem = Api.Payments.PoolListItem
type PoolSummary = Api.Payments.PoolSummary
type PoolChannelEditor = Api.Payments.PoolChannelEditor
type PoolChannelEditorAccount = Api.Payments.PoolChannelEditorAccount
type MerchantPoolCreatePayload = Omit<Api.Payments.PoolCreatePayload, 'user_id'>

export interface MerchantPoolSummaryState extends PoolSummary {
  merchant_id?: number | string
  merchant_username?: string
  vip_label?: string
}

export interface MerchantPoolCreateFormState {
  name: string
  type: string
  round_type: number
  status: boolean
}

export interface MerchantPoolEditFormState {
  name: string
  round_type: number
}

export function createMerchantPoolSummaryState(): MerchantPoolSummaryState {
  return {
    total_count: 0,
    merchant_count: 0,
    enabled_count: 0,
    disabled_count: 0,
    configured_pool_count: 0,
    empty_pool_count: 0,
    configured_channel_count: 0,
    healthy_pool_count: 0,
    generated_at: '',
    merchant_id: undefined,
    merchant_username: undefined,
    vip_label: undefined
  }
}

export function createMerchantPoolCreateFormState(): MerchantPoolCreateFormState {
  return {
    name: '',
    type: 'alipay',
    round_type: 1,
    status: true
  }
}

export function createMerchantPoolEditFormState(): MerchantPoolEditFormState {
  return {
    name: '',
    round_type: 1
  }
}

export function assignMerchantPoolCreateFormState(
  target: MerchantPoolCreateFormState,
  source: Partial<MerchantPoolCreateFormState>
) {
  target.name = source.name || ''
  target.type = source.type || 'alipay'
  target.round_type = normalizeRoundType(source.round_type)
  target.status = normalizeStatusBoolean(source.status, true)
}

export function assignMerchantPoolEditFormState(
  target: MerchantPoolEditFormState,
  source: Partial<MerchantPoolEditFormState>
) {
  target.name = source.name || ''
  target.round_type = normalizeRoundType(source.round_type)
}

export function syncMerchantPoolEditFormFromEditable(
  target: MerchantPoolEditFormState,
  editable: Partial<PoolEditable>
) {
  assignMerchantPoolEditFormState(target, {
    name: editable.name || '',
    round_type: Number(editable.round_type || 1)
  })
}

export function buildMerchantPoolCreatePayload(form: MerchantPoolCreateFormState) {
  const name = normalizeInput(form.name)
  if (!name) {
    return {
      message: '请输入轮询池名称'
    } as const
  }

  const type = normalizeInput(form.type)
  if (!type) {
    return {
      message: '请选择支付类型'
    } as const
  }

  return {
    payload: {
      name,
      type,
      round_type: normalizeRoundType(form.round_type),
      status: form.status
    } satisfies MerchantPoolCreatePayload
  } as const
}

export function buildMerchantPoolUpdatePayload(form: MerchantPoolEditFormState) {
  const name = normalizeInput(form.name)
  if (!name) {
    return {
      message: '请输入轮询池名称'
    } as const
  }

  return {
    payload: {
      name,
      round_type: normalizeRoundType(form.round_type)
    } satisfies Api.Payments.PoolUpdatePayload
  } as const
}

export function buildMerchantPoolStatusPayload(status: number) {
  return {
    payload: {
      status
    } satisfies Api.Payments.PoolStatusUpdatePayload
  } as const
}

export function buildMerchantPoolEditableFromItem(item: Partial<PoolListItem>): PoolEditable {
  return {
    name: item.name || '',
    type: item.type || '',
    round_type: normalizeRoundType(item.round_type),
    status: normalizeStatusBoolean(item.status, false) ? 1 : 0
  }
}

export function normalizeMerchantPoolChannelEditorRows(editorData: PoolChannelEditor) {
  const rows = (editorData.available_accounts || []).map((item) => ({
    ...item,
    selected: item.selected === true,
    weight: clampMerchantPoolChannelWeight(item.weight),
    sort_order: item.sort_order === null ? null : Number(item.sort_order)
  }))

  normalizeMerchantPoolChannelSortOrders(rows)
  return rows
}

export function getSelectedMerchantPoolChannelRows(rows: PoolChannelEditorAccount[]) {
  return [...rows]
    .filter((item) => item.selected)
    .sort((left, right) => Number(left.sort_order || 0) - Number(right.sort_order || 0))
}

export function getSelectedMerchantPoolChannelTotalWeight(rows: PoolChannelEditorAccount[]) {
  return getSelectedMerchantPoolChannelRows(rows).reduce(
    (total, item) => total + Number(item.weight || 0),
    0
  )
}

export function toggleMerchantPoolChannelRowSelection(
  rows: PoolChannelEditorAccount[],
  row: PoolChannelEditorAccount,
  value: string | number | boolean
) {
  row.selected = value === true || value === 1 || value === '1'
  if (row.selected) {
    row.weight = clampMerchantPoolChannelWeight(row.weight)
    if (row.sort_order === null) {
      row.sort_order = getSelectedMerchantPoolChannelRows(rows).length + 1
    }
  } else {
    row.sort_order = null
  }

  normalizeMerchantPoolChannelSortOrders(rows)
}

export function updateMerchantPoolChannelRowWeight(
  row: PoolChannelEditorAccount,
  value: string | number | null | undefined
) {
  row.weight = clampMerchantPoolChannelWeight(value)
}

export function moveMerchantPoolChannelEditorRow(
  rows: PoolChannelEditorAccount[],
  accountId: number,
  direction: -1 | 1
) {
  const selectedRows = getSelectedMerchantPoolChannelRows(rows)
  const currentIndex = selectedRows.findIndex((item) => item.account_id === accountId)
  const targetIndex = currentIndex + direction

  if (currentIndex < 0 || targetIndex < 0 || targetIndex >= selectedRows.length) {
    return
  }

  const [moved] = selectedRows.splice(currentIndex, 1)
  selectedRows.splice(targetIndex, 0, moved)
  selectedRows.forEach((item, index) => {
    const target = rows.find((rowItem) => rowItem.account_id === item.account_id)
    if (target) {
      target.sort_order = index + 1
    }
  })

  normalizeMerchantPoolChannelSortOrders(rows)
}

export function buildMerchantPoolChannelSavePayload(rows: PoolChannelEditorAccount[]) {
  return {
    channels: getSelectedMerchantPoolChannelRows(rows).map((item, index) => ({
      account_id: item.account_id,
      weight: clampMerchantPoolChannelWeight(item.weight),
      sort: index
    }))
  } satisfies Api.Payments.PoolChannelSavePayload
}

function normalizeMerchantPoolChannelSortOrders(rows: PoolChannelEditorAccount[]) {
  getSelectedMerchantPoolChannelRows(rows).forEach((item, index) => {
    item.sort_order = index + 1
    item.weight = clampMerchantPoolChannelWeight(item.weight)
  })
}

function clampMerchantPoolChannelWeight(value: string | number | null | undefined) {
  const numeric = Number(value || 1)
  if (!Number.isFinite(numeric)) {
    return 1
  }

  return Math.min(9999, Math.max(1, Math.round(numeric)))
}

function normalizeRoundType(value: number | string | undefined) {
  return Number(value || 1) === 2 ? 2 : 1
}

function normalizeStatusBoolean(value: number | string | boolean | undefined, fallback: boolean) {
  if (value === undefined) {
    return fallback
  }

  return value === true || value === 1 || value === '1'
}

function normalizeInput(value: string | undefined) {
  return String(value || '').trim()
}
