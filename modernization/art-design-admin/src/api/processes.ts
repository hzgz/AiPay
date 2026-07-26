import request from '@/utils/http'

export function fetchGetProcessOverview() {
  return request.get<Api.SystemManage.ProcessOverviewResponse>({
    url: '/api/admin/processes'
  })
}

export function fetchCleanupDuplicateSupervisors() {
  return request.post<Api.SystemManage.ProcessOverviewResponse>({
    url: '/api/admin/processes/supervisors/cleanup',
    showSuccessMessage: false
  })
}
