/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

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
