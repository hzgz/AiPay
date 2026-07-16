import request from '@/utils/http'

export function fetchGetAdminLogList(params: Api.AdminLogs.LogSearchParams) {
  return request.get<Api.AdminLogs.LogList>({
    url: '/api/admin/admin-logs',
    params
  })
}

export function fetchGetAdminLogDetail(id: number) {
  return request.get<Api.AdminLogs.LogDetailResponse>({
    url: `/api/admin/admin-logs/${id}`
  })
}

export function fetchGetAdminLogCleanupAudit() {
  return request.get<Api.AdminLogs.LogCleanupAuditResponse>({
    url: '/api/admin/admin-logs/cleanup-audit'
  })
}

export function fetchCleanupAdminLogs(data: Api.AdminLogs.LogCleanupPayload) {
  return request.post<Api.AdminLogs.LogCleanupResponse>({
    url: '/api/admin/admin-logs/cleanup',
    data
  })
}
