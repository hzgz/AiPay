import request from '@/utils/http'

export function fetchGetVipList(params: Api.Vips.VipSearchParams) {
  return request.get<Api.Vips.VipList>({
    url: '/api/admin/vips',
    params
  })
}

export function fetchGetVipTemplate() {
  return request.get<Api.Vips.VipCreateTemplateResponse>({
    url: '/api/admin/vips/template'
  })
}

export function fetchGetVipDetail(id: number) {
  return request.get<Api.Vips.VipDetailResponse>({
    url: `/api/admin/vips/${id}`
  })
}

export function fetchCreateVip(data: Api.Vips.VipCreatePayload) {
  return request.post<Api.Vips.VipCreateResponse>({
    url: '/api/admin/vips/create',
    data
  })
}

export function fetchAuditVipBatchDelete(data: Api.Vips.VipBatchDeleteAuditPayload) {
  return request.post<Api.Vips.VipBatchDeleteAuditResponse>({
    url: '/api/admin/vips/batch-delete-audit',
    data
  })
}

export function fetchBatchDeleteVips(data: Api.Vips.VipBatchDeletePayload) {
  return request.post<Api.Vips.VipBatchDeleteResponse>({
    url: '/api/admin/vips/batch-delete',
    data,
    showSuccessMessage: false
  })
}

export function fetchBatchRestoreVips(data: Api.Vips.VipBatchRestorePayload) {
  return request.post<Api.Vips.VipBatchRestoreResponse>({
    url: '/api/admin/vips/batch-restore',
    data,
    showSuccessMessage: false
  })
}

export function fetchUpdateVip(id: number, data: Api.Vips.VipUpdatePayload) {
  return request.post<Api.Vips.VipUpdateResponse>({
    url: `/api/admin/vips/${id}/update`,
    data
  })
}

export function fetchUpdateVipStatus(id: number, data: Api.Vips.VipStatusUpdatePayload) {
  return request.post<Api.Vips.VipUpdateResponse>({
    url: `/api/admin/vips/${id}/status`,
    data
  })
}

export function fetchUpdateVipSort(id: number, data: Api.Vips.VipSortUpdatePayload) {
  return request.post<Api.Vips.VipUpdateResponse>({
    url: `/api/admin/vips/${id}/sort`,
    data,
    showSuccessMessage: true
  })
}

export function fetchReorderVips(data: Api.Vips.VipReorderPayload) {
  return request.post<Api.Vips.VipReorderResponse>({
    url: '/api/admin/vips/reorder',
    data,
    showSuccessMessage: false
  })
}

export function fetchGetVipDeleteAudit(id: number) {
  return request.get<Api.Vips.VipDeleteAuditResponse>({
    url: `/api/admin/vips/${id}/delete-audit`
  })
}

export function fetchDeleteVip(id: number, data: Api.Vips.VipDeletePayload) {
  return request.post<Api.Vips.VipDeleteResponse>({
    url: `/api/admin/vips/${id}/delete`,
    data,
    showSuccessMessage: false
  })
}

export function fetchRestoreVip(id: number) {
  return request.post<Api.Vips.VipRestoreResponse>({
    url: `/api/admin/vips/${id}/restore`,
    showSuccessMessage: false
  })
}
