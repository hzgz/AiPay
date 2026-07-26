/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

export type VipDialogMode = 'create' | 'edit'

export interface VipEditFormState {
  name: string
  money: string
  vip_days: string
  fee_rate: string
  sort: string
  profit_enabled: boolean
  add_channel_enabled: boolean
  add_channel_num: string
  quota_enabled: boolean
  today_quota: string
  month_quota: string
  passage_enabled: boolean
  passage_codes: string[]
}

export interface VipStatusFormState {
  status: boolean
}

export interface VipSortFormState {
  sort: string
}

type VipEditableLike = Partial<Api.Vips.VipEditable>

export function createVipEditFormState(): VipEditFormState {
  return {
    name: '',
    money: '',
    vip_days: '',
    fee_rate: '',
    sort: '',
    profit_enabled: false,
    add_channel_enabled: false,
    add_channel_num: '0',
    quota_enabled: false,
    today_quota: '',
    month_quota: '',
    passage_enabled: false,
    passage_codes: []
  }
}

export function createVipStatusFormState(): VipStatusFormState {
  return {
    status: true
  }
}

export function createVipSortFormState(): VipSortFormState {
  return {
    sort: ''
  }
}

export function assignVipEditFormState(
  target: VipEditFormState,
  source: Partial<VipEditFormState>
) {
  target.name = source.name || ''
  target.money = source.money || ''
  target.vip_days = source.vip_days || ''
  target.fee_rate = source.fee_rate || ''
  target.sort = source.sort || ''
  target.profit_enabled = Boolean(source.profit_enabled)
  target.add_channel_enabled = Boolean(source.add_channel_enabled)
  target.add_channel_num = source.add_channel_num || '0'
  target.quota_enabled = Boolean(source.quota_enabled)
  target.today_quota = source.today_quota || ''
  target.month_quota = source.month_quota || ''
  target.passage_enabled = Boolean(source.passage_enabled)
  target.passage_codes = Array.isArray(source.passage_codes) ? [...source.passage_codes] : []
}

export function syncVipEditFormFromEditable(
  target: VipEditFormState,
  editable: VipEditableLike
): Api.Vips.VipPassageOptionGroup[] {
  assignVipEditFormState(target, {
    name: editable.name || '',
    money: editable.money || '0.00',
    vip_days: String(editable.vip_days ?? 0),
    fee_rate: editable.fee_rate || '',
    sort: String(editable.sort ?? 0),
    profit_enabled: Number(editable.profit_enabled || 0) === 1,
    add_channel_enabled: Number(editable.add_channel_enabled || 0) === 1,
    add_channel_num: String(editable.add_channel_num ?? 0),
    quota_enabled: Number(editable.quota_enabled || 0) === 1,
    today_quota: editable.today_quota || '',
    month_quota: editable.month_quota || '',
    passage_enabled: Number(editable.passage_enabled || 0) === 1,
    passage_codes: editable.passage_codes || []
  })

  return [...(editable.passage_option_groups || [])]
}

export function assignVipStatusFormState(
  target: VipStatusFormState,
  source: Partial<VipStatusFormState>
) {
  target.status = Boolean(source.status)
}

export function syncVipStatusFormFromEditable(
  target: VipStatusFormState,
  editable: VipEditableLike
) {
  assignVipStatusFormState(target, {
    status: Number(editable.status || 0) === 1
  })
}

export function assignVipSortFormState(
  target: VipSortFormState,
  source: Partial<VipSortFormState>
) {
  target.sort = source.sort || ''
}

export function syncVipSortForm(
  target: VipSortFormState,
  sort: number | string | null | undefined
) {
  target.sort = String(sort ?? 0)
}

export function trimVipEditForm(form: VipEditFormState) {
  form.name = form.name.trim()
  form.money = form.money.trim()
  form.vip_days = form.vip_days.trim()
  form.fee_rate = form.fee_rate.trim()
  form.sort = form.sort.trim()
  form.add_channel_num = form.add_channel_num.trim()
  form.today_quota = form.today_quota.trim()
  form.month_quota = form.month_quota.trim()
  form.passage_codes = Array.from(
    new Set(form.passage_codes.map((code) => code.trim()).filter(Boolean))
  )
}

export function validateVipEditForm(form: VipEditFormState): string | null {
  trimVipEditForm(form)

  if (!form.name) {
    return '请输入 VIP 套餐名称。'
  }

  if (!isMoneyValue(form.money)) {
    return '请输入合法的套餐价格，最多保留 2 位小数。'
  }

  if (!isIntegerValue(form.vip_days)) {
    return '请输入合法的 VIP 天数整数值。'
  }

  if (!isRateValue(form.fee_rate)) {
    return '请输入合法的费率值。'
  }

  if (!isIntegerValue(form.sort)) {
    return '请输入合法的排序值整数。'
  }

  if (form.add_channel_enabled && !isIntegerValue(form.add_channel_num)) {
    return '请输入合法的赠送通道数量整数值。'
  }

  if (form.quota_enabled && !form.today_quota) {
    return '启用额度限制后，请填写日额度。'
  }

  if (!isOptionalMoneyValue(form.today_quota) || !isOptionalMoneyValue(form.month_quota)) {
    return '请输入合法的额度值，最多保留 2 位小数。'
  }

  if (form.passage_enabled && form.passage_codes.length === 0) {
    return '开启通道限制后，至少需要选择一个通道。'
  }

  return null
}

export function validateVipSortForm(form: VipSortFormState): string | null {
  form.sort = form.sort.trim()

  if (!isIntegerValue(form.sort)) {
    return '排序值必须为非负整数'
  }

  return null
}

export function buildVipPayloadFromForm(form: VipEditFormState): Api.Vips.VipCreatePayload {
  return {
    name: form.name,
    money: form.money,
    vip_days: form.vip_days,
    fee_rate: form.fee_rate,
    sort: form.sort,
    profit_enabled: form.profit_enabled,
    add_channel_enabled: form.add_channel_enabled,
    add_channel_num: form.add_channel_enabled ? form.add_channel_num : '0',
    quota_enabled: form.quota_enabled,
    today_quota: form.today_quota,
    month_quota: form.month_quota,
    passage_enabled: form.passage_enabled,
    passage_codes: form.passage_enabled ? form.passage_codes : []
  }
}

function isIntegerValue(value: string) {
  return /^\d+$/.test(value)
}

function isMoneyValue(value: string) {
  return /^\d+(?:\.\d{1,2})?$/.test(value)
}

function isOptionalMoneyValue(value: string) {
  return !value || isMoneyValue(value)
}

function isRateValue(value: string) {
  return /^\d+(?:\.\d+)?$/.test(value)
}
