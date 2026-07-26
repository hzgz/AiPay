type PoolEditable = Api.Payments.PoolEditable
type PoolListItem = Api.Payments.PoolListItem
type PoolSummary = Api.Payments.PoolSummary
type PoolChannelEditor = Api.Payments.PoolChannelEditor
type PoolChannelEditorAccount = Api.Payments.PoolChannelEditorAccount

export interface PaymentPoolCreateFormState {
  user_id: string
  name: string
  type: string
  round_type: number
  status: boolean
}

export interface PaymentPoolEditFormState {
  name: string
  round_type: number
}

export interface PaymentPoolStatusFormState {
  status: boolean
}

export function createPaymentPoolCreateFormState(): PaymentPoolCreateFormState {
  return {
    user_id: '',
    name: '',
    type: 'alipay',
    round_type: 1,
    status: true
  }
}

export function createPaymentPoolEditFormState(): PaymentPoolEditFormState {
  return {
    name: '',
    round_type: 1
  }
}

export function createPaymentPoolStatusFormState(): PaymentPoolStatusFormState {
  return {
    status: true
  }
}

export function createPaymentPoolSummaryState(): PoolSummary {
  return {
    total_count: 0,
    merchant_count: 0,
    enabled_count: 0,
    disabled_count: 0,
    configured_pool_count: 0,
    empty_pool_count: 0,
    configured_channel_count: 0,
    healthy_pool_count: 0,
    generated_at: ''
  }
}

export function assignPaymentPoolCreateFormState(
  target: PaymentPoolCreateFormState,
  source: Partial<PaymentPoolCreateFormState>
) {
  target.user_id = String(source.user_id || '')
  target.name = source.name || ''
  target.type = source.type || 'alipay'
  target.round_type = normalizeRoundType(source.round_type)
  target.status = normalizeStatusBoolean(source.status, true)
}

export function assignPaymentPoolEditFormState(
  target: PaymentPoolEditFormState,
  source: Partial<PaymentPoolEditFormState>
) {
  target.name = source.name || ''
  target.round_type = normalizeRoundType(source.round_type)
}

export function assignPaymentPoolStatusFormState(
  target: PaymentPoolStatusFormState,
  source: Partial<PaymentPoolStatusFormState>
) {
  target.status = normalizeStatusBoolean(source.status, true)
}

export function syncPaymentPoolEditFormFromEditable(
  target: PaymentPoolEditFormState,
  editable: Partial<PoolEditable>
) {
  assignPaymentPoolEditFormState(target, {
    name: editable.name || '',
    round_type: Number(editable.round_type || 1)
  })
}

export function syncPaymentPoolStatusFormFromEditable(
  target: PaymentPoolStatusFormState,
  editable: Partial<PoolEditable>
) {
  assignPaymentPoolStatusFormState(target, {
    status: Number(editable.status || 0) === 1
  })
}

export function buildPaymentPoolCreatePayload(form: PaymentPoolCreateFormState) {
  const userId = Number(form.user_id)
  if (!Number.isInteger(userId) || userId <= 0) {
    return {
      message: '请输入有效的商户编号'
    } as const
  }

  const name = normalizeInput(form.name)
  if (!name) {
    return {
      message: '请输入轮询池名称'
    } as const
  }

  return {
    payload: {
      user_id: userId,
      name,
      type: normalizeInput(form.type) || 'alipay',
      round_type: normalizeRoundType(form.round_type),
      status: form.status
    } satisfies Api.Payments.PoolCreatePayload
  } as const
}

export function buildPaymentPoolUpdatePayload(form: PaymentPoolEditFormState) {
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

export function buildPaymentPoolStatusPayload(form: PaymentPoolStatusFormState) {
  return {
    payload: {
      status: form.status
    } satisfies Api.Payments.PoolStatusUpdatePayload
  } as const
}

export function buildPaymentPoolEditableFromItem(item: Partial<PoolListItem>): PoolEditable {
  return {
    name: item.name || '',
    type: item.type || '',
    round_type: normalizeRoundType(item.round_type),
    status: normalizeStatusBoolean(item.status, false) ? 1 : 0
  }
}

export function normalizePaymentPoolChannelEditorRows(editorData: PoolChannelEditor) {
  const rows = (editorData.available_accounts || []).map((item) => ({
    ...item,
    selected: item.selected === true,
    weight: clampPoolChannelWeight(item.weight),
    sort_order: item.sort_order === null ? null : Number(item.sort_order)
  }))

  normalizePaymentPoolChannelSortOrders(rows)
  return rows
}

export function getSelectedPaymentPoolChannelRows(rows: PoolChannelEditorAccount[]) {
  return [...rows]
    .filter((item) => item.selected)
    .sort((left, right) => Number(left.sort_order || 0) - Number(right.sort_order || 0))
}

export function getSelectedPaymentPoolChannelTotalWeight(rows: PoolChannelEditorAccount[]) {
  return getSelectedPaymentPoolChannelRows(rows).reduce(
    (total, item) => total + Number(item.weight || 0),
    0
  )
}

export function togglePaymentPoolChannelRowSelection(
  rows: PoolChannelEditorAccount[],
  row: PoolChannelEditorAccount,
  value: string | number | boolean
) {
  row.selected = value === true || value === 1 || value === '1'
  if (row.selected) {
    row.weight = clampPoolChannelWeight(row.weight)
    if (row.sort_order === null) {
      row.sort_order = getSelectedPaymentPoolChannelRows(rows).length + 1
    }
  } else {
    row.sort_order = null
  }

  normalizePaymentPoolChannelSortOrders(rows)
}

export function updatePaymentPoolChannelRowWeight(
  row: PoolChannelEditorAccount,
  value: string | number | null | undefined
) {
  row.weight = clampPoolChannelWeight(value)
}

export function movePaymentPoolChannelEditorRow(
  rows: PoolChannelEditorAccount[],
  accountId: number,
  direction: -1 | 1
) {
  const selectedRows = getSelectedPaymentPoolChannelRows(rows)
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

  normalizePaymentPoolChannelSortOrders(rows)
}

export function buildPaymentPoolChannelSavePayload(rows: PoolChannelEditorAccount[]) {
  return {
    channels: getSelectedPaymentPoolChannelRows(rows).map((item, index) => ({
      account_id: item.account_id,
      weight: clampPoolChannelWeight(item.weight),
      sort: index
    }))
  } satisfies Api.Payments.PoolChannelSavePayload
}

function normalizePaymentPoolChannelSortOrders(rows: PoolChannelEditorAccount[]) {
  getSelectedPaymentPoolChannelRows(rows).forEach((item, index) => {
    item.sort_order = index + 1
    item.weight = clampPoolChannelWeight(item.weight)
  })
}

function clampPoolChannelWeight(value: string | number | null | undefined) {
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
