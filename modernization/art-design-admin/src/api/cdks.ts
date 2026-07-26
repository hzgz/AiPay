/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

import request from '@/utils/http'

export function fetchGetCdkList(params: Api.Cdks.CdkSearchParams) {
  return request.get<Api.Cdks.CdkList>({
    url: '/api/admin/cdks',
    params
  })
}

export function fetchGetCdkDetail(id: number) {
  return request.get<Api.Cdks.CdkDetailResponse>({
    url: `/api/admin/cdks/${id}`
  })
}

export function fetchCreateCdks(data: Api.Cdks.CdkCreatePayload) {
  return request.post<Api.Cdks.CdkCreateResponse>({
    url: '/api/admin/cdks/create',
    data
  })
}

export function fetchGetCdkDeleteAudit(id: number) {
  return request.get<Api.Cdks.CdkDeleteAuditResponse>({
    url: `/api/admin/cdks/${id}/delete-audit`
  })
}

export function fetchDeleteCdk(id: number, data: Api.Cdks.CdkDeletePayload) {
  return request.post<Api.Cdks.CdkDeleteResponse>({
    url: `/api/admin/cdks/${id}/delete`,
    data
  })
}

export function fetchAuditCdkBatchDelete(data: Api.Cdks.CdkBatchDeleteAuditPayload) {
  return request.post<Api.Cdks.CdkBatchDeleteAuditResponse>({
    url: '/api/admin/cdks/batch-delete-audit',
    data
  })
}

export function fetchBatchDeleteCdks(data: Api.Cdks.CdkBatchDeletePayload) {
  return request.post<Api.Cdks.CdkBatchDeleteResponse>({
    url: '/api/admin/cdks/batch-delete',
    data
  })
}

export function fetchGetCdkCleanupUsedAudit() {
  return request.get<Api.Cdks.CdkCleanupUsedAuditResponse>({
    url: '/api/admin/cdks/cleanup-used-audit'
  })
}

export function fetchCleanupUsedCdks(data: Api.Cdks.CdkCleanupUsedPayload) {
  return request.post<Api.Cdks.CdkCleanupUsedResponse>({
    url: '/api/admin/cdks/cleanup-used',
    data
  })
}
