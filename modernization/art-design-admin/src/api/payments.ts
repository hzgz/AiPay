/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

import request from '@/utils/http'

export function fetchGetPaymentMethodList(params: Api.Payments.MethodSearchParams) {
  return request.get<Api.Payments.MethodList>({
    url: '/api/admin/payments',
    params
  })
}

export function fetchCreatePaymentMethod(params: Api.Payments.MethodCreatePayload) {
  return request.post<Api.Payments.MethodCreateResponse>({
    url: '/api/admin/payments/create',
    params,
    showSuccessMessage: false
  })
}

export function fetchGetPaymentMethodDetail(id: number) {
  return request.get<Api.Payments.MethodDetailResponse>({
    url: `/api/admin/payments/${id}`
  })
}

export function fetchGetPaymentMethodDeleteAudit(id: number) {
  return request.get<Api.Payments.MethodDeleteAuditResponse>({
    url: `/api/admin/payments/${id}/delete-audit`
  })
}

export function fetchUpdatePaymentMethod(id: number, params: Api.Payments.MethodUpdatePayload) {
  return request.post<Api.Payments.MethodUpdateResponse>({
    url: `/api/admin/payments/${id}/update`,
    params,
    showSuccessMessage: false
  })
}

export function fetchUpdatePaymentMethodStatus(
  id: number,
  params: Api.Payments.StatusUpdatePayload
) {
  return request.post<Api.Payments.MethodStatusUpdateResponse>({
    url: `/api/admin/payments/${id}/status`,
    params,
    showSuccessMessage: true
  })
}

export function fetchDeletePaymentMethod(id: number, params: Api.Payments.MethodDeletePayload) {
  return request.post<Api.Payments.MethodDeleteResponse>({
    url: `/api/admin/payments/${id}/delete`,
    params,
    showSuccessMessage: false
  })
}

export function fetchRestorePaymentMethod(id: number) {
  return request.post<Api.Payments.MethodRestoreResponse>({
    url: `/api/admin/payments/${id}/restore`,
    showSuccessMessage: false
  })
}

export function fetchAuditPaymentMethodBatchDelete(
  params: Api.Payments.MethodBatchDeleteAuditPayload
) {
  return request.post<Api.Payments.MethodBatchDeleteAuditResponse>({
    url: '/api/admin/payments/batch-delete-audit',
    params
  })
}

export function fetchBatchDeletePaymentMethods(params: Api.Payments.MethodBatchDeletePayload) {
  return request.post<Api.Payments.MethodBatchDeleteResponse>({
    url: '/api/admin/payments/batch-delete',
    params,
    showSuccessMessage: false
  })
}

export function fetchBatchRestorePaymentMethods(params: Api.Payments.MethodBatchRestorePayload) {
  return request.post<Api.Payments.MethodBatchRestoreResponse>({
    url: '/api/admin/payments/batch-restore',
    params,
    showSuccessMessage: false
  })
}
