import request from '@/utils/http'

export function fetchGetRechargeList(params: Api.Recharges.RechargeSearchParams) {
  return request.get<Api.Recharges.RechargeList>({
    url: '/api/admin/recharges',
    params
  })
}

export function fetchGetRechargeDetail(id: number) {
  return request.get<Api.Recharges.RechargeDetailResponse>({
    url: `/api/admin/recharges/${id}`
  })
}
