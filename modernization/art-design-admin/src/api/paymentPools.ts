/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

import request from '@/utils/http'

export function fetchGetPaymentPoolList(params: Api.Payments.PoolSearchParams) {
  return request.get<Api.Payments.PoolList>({
    url: '/api/admin/payment-pools',
    params
  })
}

export function fetchCreatePaymentPool(data: Api.Payments.PoolCreatePayload) {
  return request.post<Api.Payments.PoolCreateResponse>({
    url: '/api/admin/payment-pools/create',
    data
  })
}

export function fetchGetPaymentPoolDetail(id: number) {
  return request.get<Api.Payments.PoolDetailResponse>({
    url: `/api/admin/payment-pools/${id}`
  })
}

export function fetchGetPaymentPoolChannelEditor(id: number) {
  return request.get<Api.Payments.PoolChannelEditorResponse>({
    url: `/api/admin/payment-pools/${id}/channel-editor`
  })
}

export function fetchSavePaymentPoolChannels(id: number, data: Api.Payments.PoolChannelSavePayload) {
  return request.post<Api.Payments.PoolChannelSaveResponse>({
    url: `/api/admin/payment-pools/${id}/channels`,
    data
  })
}

export function fetchGetPaymentPoolDeleteAudit(id: number) {
  return request.get<Api.Payments.PoolDeleteAuditResponse>({
    url: `/api/admin/payment-pools/${id}/delete-audit`
  })
}

export function fetchDeletePaymentPool(id: number, data: Api.Payments.PoolDeletePayload) {
  return request.post<Api.Payments.PoolDeleteResponse>({
    url: `/api/admin/payment-pools/${id}/delete`,
    data
  })
}

export function fetchUpdatePaymentPool(id: number, data: Api.Payments.PoolUpdatePayload) {
  return request.post<Api.Payments.PoolUpdateResponse>({
    url: `/api/admin/payment-pools/${id}/update`,
    data
  })
}

export function fetchUpdatePaymentPoolStatus(id: number, data: Api.Payments.PoolStatusUpdatePayload) {
  return request.post<Api.Payments.PoolStatusUpdateResponse>({
    url: `/api/admin/payment-pools/${id}/status`,
    data
  })
}
