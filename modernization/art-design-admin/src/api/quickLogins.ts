/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

import request from '@/utils/http'

export function fetchGetQuickLoginList(params: Api.QuickLogins.QuickLoginSearchParams) {
  return request.get<Api.QuickLogins.QuickLoginList>({
    url: '/api/admin/quick-logins',
    params
  })
}

export function fetchGetQuickLoginDetail(id: number) {
  return request.get<Api.QuickLogins.QuickLoginDetailResponse>({
    url: `/api/admin/quick-logins/${id}`
  })
}

export function fetchCreateQuickLogin(data: Api.QuickLogins.QuickLoginWritePayload) {
  return request.post<Api.QuickLogins.QuickLoginCreateResponse>({
    url: '/api/admin/quick-logins/create',
    data
  })
}

export function fetchUpdateQuickLogin(id: number, data: Api.QuickLogins.QuickLoginWritePayload) {
  return request.post<Api.QuickLogins.QuickLoginUpdateResponse>({
    url: `/api/admin/quick-logins/${id}/update`,
    data
  })
}

export function fetchUpdateQuickLoginStatus(
  id: number,
  data: Api.QuickLogins.QuickLoginStatusPayload
) {
  return request.post<Api.QuickLogins.QuickLoginStatusResponse>({
    url: `/api/admin/quick-logins/${id}/status`,
    data
  })
}

export function fetchGetQuickLoginDeleteAudit(id: number) {
  return request.get<Api.QuickLogins.QuickLoginDeleteAuditResponse>({
    url: `/api/admin/quick-logins/${id}/delete-audit`
  })
}

export function fetchDeleteQuickLogin(id: number, data: Api.QuickLogins.QuickLoginDeletePayload) {
  return request.post<Api.QuickLogins.QuickLoginDeleteResponse>({
    url: `/api/admin/quick-logins/${id}/delete`,
    data
  })
}

export function fetchAuditQuickLoginBatchDelete(
  data: Api.QuickLogins.QuickLoginBatchDeleteAuditPayload
) {
  return request.post<Api.QuickLogins.QuickLoginBatchDeleteAuditResponse>({
    url: '/api/admin/quick-logins/batch-delete-audit',
    data
  })
}

export function fetchBatchDeleteQuickLogins(
  data: Api.QuickLogins.QuickLoginBatchDeletePayload
) {
  return request.post<Api.QuickLogins.QuickLoginBatchDeleteResponse>({
    url: '/api/admin/quick-logins/batch-delete',
    data
  })
}
