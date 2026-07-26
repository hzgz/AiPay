import request from '@/utils/http'

export function fetchGetCommerceOverview() {
  return request.get<Api.CommerceOverview.OverviewResponse>({
    url: '/api/admin/commerce-overview'
  })
}
