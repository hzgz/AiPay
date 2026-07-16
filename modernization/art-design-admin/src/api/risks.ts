import request from '@/utils/http'

export function fetchGetRiskList(params: Api.Risks.RiskSearchParams) {
  return request.get<Api.Risks.RiskList>({
    url: '/api/admin/risks',
    params
  })
}

export function fetchGetRiskDetail(id: number) {
  return request.get<Api.Risks.RiskDetailResponse>({
    url: `/api/admin/risks/${id}`
  })
}

export function fetchCreateRisk(data: Api.Risks.RiskWritePayload) {
  return request.post<Api.Risks.RiskCreateResponse>({
    url: '/api/admin/risks/create',
    data
  })
}

export function fetchUpdateRisk(id: number, data: Api.Risks.RiskWritePayload) {
  return request.post<Api.Risks.RiskUpdateResponse>({
    url: `/api/admin/risks/${id}/update`,
    data
  })
}

export function fetchGetRiskDeleteAudit(id: number) {
  return request.get<Api.Risks.RiskDeleteAuditResponse>({
    url: `/api/admin/risks/${id}/delete-audit`
  })
}

export function fetchDeleteRisk(id: number, data: Api.Risks.RiskDeletePayload) {
  return request.post<Api.Risks.RiskDeleteResponse>({
    url: `/api/admin/risks/${id}/delete`,
    data
  })
}

export function fetchAuditRiskBatchDelete(data: Api.Risks.RiskBatchDeleteAuditPayload) {
  return request.post<Api.Risks.RiskBatchDeleteAuditResponse>({
    url: '/api/admin/risks/batch-delete-audit',
    data
  })
}

export function fetchBatchDeleteRisks(data: Api.Risks.RiskBatchDeletePayload) {
  return request.post<Api.Risks.RiskBatchDeleteResponse>({
    url: '/api/admin/risks/batch-delete',
    data
  })
}
