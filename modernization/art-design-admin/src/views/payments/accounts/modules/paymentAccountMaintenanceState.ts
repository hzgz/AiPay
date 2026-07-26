import { ElMessage } from 'element-plus'
import { isPaymentAccountDecimalValue as isDecimalValue } from '@/views/shared/paymentAccountPageShared'
import { getAccountCodeMeta } from '@/views/shared/paymentAccountMeta'

type AccountEditable = Api.Payments.AccountEditable
type AccountItem = Api.Payments.AccountListItem

export interface PaymentAccountEditFormState {
  memo: string
  daymaxcount: string
  daymaxmoney: string
  allmaxcount: string
  allmaxmoney: string
}

export interface PaymentAccountStatusFormState {
  status: boolean
  is_status: boolean
}

export function createEmptyPaymentAccountEditForm(): PaymentAccountEditFormState {
  return {
    memo: '',
    daymaxcount: '0',
    daymaxmoney: '',
    allmaxcount: '0',
    allmaxmoney: ''
  }
}

export function createEmptyPaymentAccountStatusForm(): PaymentAccountStatusFormState {
  return {
    status: false,
    is_status: true
  }
}

export function buildPaymentAccountEditableFromItem(item: AccountItem): AccountEditable {
  return {
    memo: item.memo || '',
    daymaxcount: String(item.daymaxcount ?? 0),
    daymaxmoney: item.daymaxmoney || '',
    allmaxcount: String(item.allmaxcount ?? 0),
    allmaxmoney: item.allmaxmoney || '',
    status: Number(item.status || 0),
    is_status: Number(item.is_status || 0),
    code: item.code || '',
    credential_supported: Boolean(getAccountCodeMeta(item.code)),
    pid: '',
    identifier: '',
    qr_type: item.qr_type || '',
    qr_url: '',
    cookie: '',
    remark: '',
    wx_guid: '',
    cloud_id: '',
    extra_value: ''
  }
}

export function syncPaymentAccountEditForm(
  target: PaymentAccountEditFormState,
  editable: AccountEditable
) {
  target.memo = editable.memo || ''
  target.daymaxcount = editable.daymaxcount || '0'
  target.daymaxmoney = editable.daymaxmoney || ''
  target.allmaxcount = editable.allmaxcount || '0'
  target.allmaxmoney = editable.allmaxmoney || ''
}

export function syncPaymentAccountStatusForm(
  target: PaymentAccountStatusFormState,
  editable: AccountEditable
) {
  target.status = Number(editable.status || 0) === 1
  target.is_status = Number(editable.is_status || 0) === 1
}

export function buildPaymentAccountUpdatePayload(
  form: PaymentAccountEditFormState
): Api.Payments.AccountUpdatePayload | null {
  trimPaymentAccountEditForm(form)

  if (!/^\d+$/.test(form.daymaxcount)) {
    ElMessage.warning('单日次数上限必须是非负整数。')
    return null
  }

  if (!/^\d+$/.test(form.allmaxcount)) {
    ElMessage.warning('累计次数上限必须是非负整数。')
    return null
  }

  if (!isDecimalValue(form.daymaxmoney)) {
    ElMessage.warning('单日金额上限必须是最多保留两位小数的非负金额。')
    return null
  }

  if (!isDecimalValue(form.allmaxmoney)) {
    ElMessage.warning('累计金额上限必须是最多保留两位小数的非负金额。')
    return null
  }

  return {
    memo: form.memo,
    daymaxcount: form.daymaxcount,
    daymaxmoney: form.daymaxmoney,
    allmaxcount: form.allmaxcount,
    allmaxmoney: form.allmaxmoney
  }
}

export function buildPaymentAccountStatusPayload(
  form: PaymentAccountStatusFormState
): Api.Payments.AccountStatusUpdatePayload {
  return {
    status: form.status,
    is_status: form.is_status
  }
}

function trimPaymentAccountEditForm(form: PaymentAccountEditFormState) {
  form.memo = form.memo.trim()
  form.daymaxcount = form.daymaxcount.trim()
  form.daymaxmoney = form.daymaxmoney.trim()
  form.allmaxcount = form.allmaxcount.trim()
  form.allmaxmoney = form.allmaxmoney.trim()
}
