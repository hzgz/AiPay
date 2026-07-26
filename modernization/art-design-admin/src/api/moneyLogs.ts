/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

import request from '@/utils/http'

export function fetchGetMoneyLogList(params: Api.MoneyLogs.MoneyLogSearchParams) {
  return request.get<Api.MoneyLogs.MoneyLogList>({
    url: '/api/admin/money-logs',
    params
  })
}

export function fetchGetMoneyLogDetail(id: number) {
  return request.get<Api.MoneyLogs.MoneyLogDetailResponse>({
    url: `/api/admin/money-logs/${id}`
  })
}

export function fetchCreateMoneyLogAdjustment(params: Api.MoneyLogs.MoneyLogCreatePayload) {
  return request.post<Api.MoneyLogs.MoneyLogCreateResponse>({
    url: '/api/admin/money-logs/create',
    params,
    showSuccessMessage: false
  })
}
