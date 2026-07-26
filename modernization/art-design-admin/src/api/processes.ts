/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

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
