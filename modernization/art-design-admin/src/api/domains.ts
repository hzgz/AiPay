import request from '@/utils/http'

export function fetchGetDomainList(params: Api.Domains.DomainSearchParams) {
  return request.get<Api.Domains.DomainList>({
    url: '/api/admin/domains',
    params
  })
}

export function fetchCreateDomain(data: Api.Domains.DomainWritePayload) {
  return request.post<Api.Domains.DomainCreateResponse>({
    url: '/api/admin/domains/create',
    data
  })
}

export function fetchGetDomainDetail(id: number) {
  return request.get<Api.Domains.DomainDetailResponse>({
    url: `/api/admin/domains/${id}`
  })
}

export function fetchUpdateDomain(id: number, data: Api.Domains.DomainWritePayload) {
  return request.post<Api.Domains.DomainUpdateResponse>({
    url: `/api/admin/domains/${id}/update`,
    data
  })
}

export function fetchGetDomainDeleteAudit(id: number) {
  return request.get<Api.Domains.DomainDeleteAuditResponse>({
    url: `/api/admin/domains/${id}/delete-audit`
  })
}

export function fetchApproveDomain(id: number) {
  return request.post<Api.Domains.DomainApproveResponse>({
    url: `/api/admin/domains/${id}/approve`
  })
}

export function fetchRejectDomain(id: number, data: Api.Domains.DomainRejectPayload) {
  return request.post<Api.Domains.DomainRejectResponse>({
    url: `/api/admin/domains/${id}/reject`,
    data
  })
}

export function fetchDeleteDomain(id: number, data: Api.Domains.DomainDeletePayload) {
  return request.post<Api.Domains.DomainDeleteResponse>({
    url: `/api/admin/domains/${id}/delete`,
    data,
    showSuccessMessage: false
  })
}

export function fetchRestoreDomain(id: number) {
  return request.post<Api.Domains.DomainRestoreResponse>({
    url: `/api/admin/domains/${id}/restore`
  })
}

export function fetchAuditDomainBatchDelete(data: Api.Domains.DomainBatchDeleteAuditPayload) {
  return request.post<Api.Domains.DomainBatchDeleteAuditResponse>({
    url: '/api/admin/domains/batch-delete-audit',
    data
  })
}

export function fetchBatchDeleteDomains(data: Api.Domains.DomainBatchDeletePayload) {
  return request.post<Api.Domains.DomainBatchDeleteResponse>({
    url: '/api/admin/domains/batch-delete',
    data,
    showSuccessMessage: false
  })
}

export function fetchBatchRestoreDomains(data: Api.Domains.DomainBatchRestorePayload) {
  return request.post<Api.Domains.DomainBatchRestoreResponse>({
    url: '/api/admin/domains/batch-restore',
    data
  })
}
