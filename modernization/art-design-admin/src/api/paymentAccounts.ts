/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

import request from '@/utils/http'

export interface PaymentAccountCredentialImageUploadPayload {
  code: string
  field: 'qr_url'
  qr_type?: string
  file: File
}

export interface PaymentAccountCredentialImageUploadResponse {
  code: string
  field: string
  mode: 'image'
  value: string
  href: string
  preview_url: string
  photo_id: number
  path: string
}

export interface PaymentAccountCredentialDecodePayload {
  code: string
  field: 'qr_url' | 'extra_value'
  qr_type?: string
  file: File
}

export interface PaymentAccountCredentialDecodeResponse {
  code: string
  field: string
  mode: 'decoded_text'
  value: string
}

export function fetchGetPaymentAccountList(params: Api.Payments.AccountSearchParams) {
  return request.get<Api.Payments.AccountList>({
    url: '/api/admin/payment-accounts',
    params
  })
}

export function fetchGetPaymentAccountDetail(id: number) {
  return request.get<Api.Payments.AccountDetailResponse>({
    url: `/api/admin/payment-accounts/${id}`
  })
}

export function fetchCreatePaymentAccount(data: Api.Payments.AccountCreatePayload) {
  return request.post<Api.Payments.AccountCreateResponse>({
    url: '/api/admin/payment-accounts/create',
    data
  })
}

export function fetchUploadPaymentAccountCredentialImage(
  payload: PaymentAccountCredentialImageUploadPayload
) {
  const formData = new FormData()
  formData.append('code', payload.code)
  formData.append('field', payload.field)
  if (payload.qr_type) {
    formData.append('qr_type', payload.qr_type)
  }
  formData.append('file', payload.file)

  return request.post<PaymentAccountCredentialImageUploadResponse>({
    url: '/api/admin/payment-accounts/credential-image',
    data: formData
  })
}

export function fetchDecodePaymentAccountCredentialImage(
  payload: PaymentAccountCredentialDecodePayload
) {
  const formData = new FormData()
  formData.append('code', payload.code)
  formData.append('field', payload.field)
  if (payload.qr_type) {
    formData.append('qr_type', payload.qr_type)
  }
  formData.append('file', payload.file)

  return request.post<PaymentAccountCredentialDecodeResponse>({
    url: '/api/admin/payment-accounts/credential-decode',
    data: formData
  })
}

export function fetchGetPaymentAccountDeleteAudit(id: number) {
  return request.get<Api.Payments.AccountDeleteAuditResponse>({
    url: `/api/admin/payment-accounts/${id}/delete-audit`
  })
}

export function fetchUpdatePaymentAccount(id: number, data: Api.Payments.AccountUpdatePayload) {
  return request.post<Api.Payments.AccountUpdateResponse>({
    url: `/api/admin/payment-accounts/${id}/update`,
    data
  })
}

export function fetchUpdatePaymentAccountCredentials(
  id: number,
  data: Api.Payments.AccountCredentialUpdatePayload
) {
  return request.post<Api.Payments.AccountCredentialUpdateResponse>({
    url: `/api/admin/payment-accounts/${id}/credentials`,
    data
  })
}

export function fetchUpdatePaymentAccountStatus(
  id: number,
  data: Api.Payments.AccountStatusUpdatePayload
) {
  return request.post<Api.Payments.AccountStatusUpdateResponse>({
    url: `/api/admin/payment-accounts/${id}/status`,
    data
  })
}

export function fetchDeletePaymentAccount(id: number, data: Api.Payments.AccountDeletePayload) {
  return request.post<Api.Payments.AccountDeleteResponse>({
    url: `/api/admin/payment-accounts/${id}/delete`,
    data
  })
}

export function fetchAuditPaymentAccountBatchDelete(
  data: Api.Payments.AccountBatchDeleteAuditPayload
) {
  return request.post<Api.Payments.AccountBatchDeleteAuditResponse>({
    url: '/api/admin/payment-accounts/batch-delete-audit',
    data
  })
}

export function fetchBatchDeletePaymentAccounts(data: Api.Payments.AccountBatchDeletePayload) {
  return request.post<Api.Payments.AccountBatchDeleteResponse>({
    url: '/api/admin/payment-accounts/batch-delete',
    data
  })
}
