import request from '@/utils/http'

export function fetchGetDashboardOverview() {
  return request.get<Api.Dashboard.OverviewResponse>({
    url: '/api/admin/dashboard/overview'
  })
}
