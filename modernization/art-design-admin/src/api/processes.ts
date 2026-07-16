import request from '@/utils/http'

export function fetchGetProcessOverview() {
  return request.get<Api.SystemManage.ProcessOverviewResponse>({
    url: '/api/admin/processes'
  })
}

export function fetchPauseProcessMonitor() {
  return request.post<Api.SystemManage.ProcessOverviewResponse>({
    url: '/api/admin/processes/monitor/pause',
    showSuccessMessage: false
  })
}

export function fetchResumeProcessMonitor() {
  return request.post<Api.SystemManage.ProcessOverviewResponse>({
    url: '/api/admin/processes/monitor/resume',
    showSuccessMessage: false
  })
}

export function fetchCleanupDuplicateSupervisors() {
  return request.post<Api.SystemManage.ProcessOverviewResponse>({
    url: '/api/admin/processes/supervisors/cleanup',
    showSuccessMessage: false
  })
}
