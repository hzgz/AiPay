/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

import request from '@/utils/http'

export function fetchGetCommerceOverview() {
  return request.get<Api.CommerceOverview.OverviewResponse>({
    url: '/api/admin/commerce-overview'
  })
}
