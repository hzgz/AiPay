import request from '@/utils/http'

export function fetchGetFrontLogList(params: Api.FrontLogs.LogSearchParams) {
  return request.get<Api.FrontLogs.LogList>({
    url: '/api/admin/front-logs',
    params
  })
}

export function fetchGetFrontLogDetail(id: number) {
  return request.get<Api.FrontLogs.LogDetailResponse>({
    url: `/api/admin/front-logs/${id}`
  })
}

export function fetchGetFrontLogDeleteAudit(id: number) {
  return request.get<Api.FrontLogs.LogDeleteAuditResponse>({
    url: `/api/admin/front-logs/${id}/delete-audit`
  })
}

export function fetchDeleteFrontLog(id: number, data: Api.FrontLogs.LogDeletePayload) {
  return request.post<Api.FrontLogs.LogDeleteResponse>({
    url: `/api/admin/front-logs/${id}/delete`,
    data
  })
}

export function fetchAuditFrontLogBatchDelete(data: Api.FrontLogs.LogBatchDeleteAuditPayload) {
  return request.post<Api.FrontLogs.LogBatchDeleteAuditResponse>({
    url: '/api/admin/front-logs/batch-delete-audit',
    data
  })
}

export function fetchBatchDeleteFrontLogs(data: Api.FrontLogs.LogBatchDeletePayload) {
  return request.post<Api.FrontLogs.LogBatchDeleteResponse>({
    url: '/api/admin/front-logs/batch-delete',
    data
  })
}

export function fetchGetFrontLogCleanupAudit() {
  return request.get<Api.FrontLogs.LogCleanupAuditResponse>({
    url: '/api/admin/front-logs/cleanup-audit'
  })
}

export function fetchCleanupFrontLogs(data: Api.FrontLogs.LogCleanupPayload) {
  return request.post<Api.FrontLogs.LogCleanupResponse>({
    url: '/api/admin/front-logs/cleanup',
    data
  })
}
