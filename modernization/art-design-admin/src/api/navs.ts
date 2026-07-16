import request from '@/utils/http'

export function fetchGetNavList(params: Api.Navs.NavSearchParams) {
  return request.get<Api.Navs.NavList>({
    url: '/api/admin/navs',
    params
  })
}

export function fetchGetNavDetail(id: number) {
  return request.get<Api.Navs.NavDetailResponse>({
    url: `/api/admin/navs/${id}`
  })
}

export function fetchCreateNav(data: Api.Navs.NavWritePayload) {
  return request.post<Api.Navs.NavCreateResponse>({
    url: '/api/admin/navs/create',
    data
  })
}

export function fetchUpdateNav(id: number, data: Api.Navs.NavWritePayload) {
  return request.post<Api.Navs.NavUpdateResponse>({
    url: `/api/admin/navs/${id}/update`,
    data
  })
}

export function fetchUpdateNavStatus(id: number, data: Api.Navs.NavStatusPayload) {
  return request.post<Api.Navs.NavStatusResponse>({
    url: `/api/admin/navs/${id}/status`,
    data
  })
}

export function fetchUpdateNavTarget(id: number, data: Api.Navs.NavTargetPayload) {
  return request.post<Api.Navs.NavTargetResponse>({
    url: `/api/admin/navs/${id}/target`,
    data
  })
}

export function fetchReorderNavs(data: Api.Navs.NavReorderPayload) {
  return request.post<Api.Navs.NavReorderResponse>({
    url: '/api/admin/navs/reorder',
    data,
    showSuccessMessage: false
  })
}

export function fetchGetNavDeleteAudit(id: number) {
  return request.get<Api.Navs.NavDeleteAuditResponse>({
    url: `/api/admin/navs/${id}/delete-audit`
  })
}

export function fetchDeleteNav(id: number, data: Api.Navs.NavDeletePayload) {
  return request.post<Api.Navs.NavDeleteResponse>({
    url: `/api/admin/navs/${id}/delete`,
    data
  })
}

export function fetchAuditNavBatchDelete(data: Api.Navs.NavBatchDeleteAuditPayload) {
  return request.post<Api.Navs.NavBatchDeleteAuditResponse>({
    url: '/api/admin/navs/batch-delete-audit',
    data
  })
}

export function fetchBatchDeleteNavs(data: Api.Navs.NavBatchDeletePayload) {
  return request.post<Api.Navs.NavBatchDeleteResponse>({
    url: '/api/admin/navs/batch-delete',
    data
  })
}

export function fetchRestoreNav(id: number) {
  return request.post<Api.Navs.NavRestoreResponse>({
    url: `/api/admin/navs/${id}/restore`
  })
}

export function fetchBatchRestoreNavs(data: Api.Navs.NavBatchRestorePayload) {
  return request.post<Api.Navs.NavBatchRestoreResponse>({
    url: '/api/admin/navs/batch-restore',
    data
  })
}
