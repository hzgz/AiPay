/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

import request from '@/utils/http'

export function fetchGetDashboardOverview() {
  return request.get<Api.Dashboard.OverviewResponse>({
    url: '/api/admin/dashboard/overview'
  })
}
