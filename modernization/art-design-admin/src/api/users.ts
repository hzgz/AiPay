/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

import request from '@/utils/http'

export function fetchGetMerchantList(params: Api.Users.UserSearchParams) {
  return request.get<Api.Users.UserList>({
    url: '/api/admin/users',
    params
  })
}

export function fetchGetMerchantDetail(id: number) {
  return request.get<Api.Users.UserDetailResponse>({
    url: `/api/admin/users/${id}`
  })
}

export function fetchGetMerchantTemplate() {
  return request.get<Api.Users.UserCreateTemplateResponse>({
    url: '/api/admin/users/template'
  })
}

export function fetchCreateMerchant(params: Api.Users.UserCreatePayload) {
  return request.post<Api.Users.UserCreateResponse>({
    url: '/api/admin/users/create',
    params
  })
}

export function fetchAuditMerchantEmail(params: Api.Users.UserEmailAuditPayload) {
  return request.post<Api.Users.UserEmailAuditResponse>({
    url: '/api/admin/users/email-audit',
    params
  })
}

export function fetchSendMerchantEmail(params: Api.Users.UserEmailSendPayload) {
  return request.post<Api.Users.UserEmailSendResponse>({
    url: '/api/admin/users/email',
    params,
    showSuccessMessage: false
  })
}

export function fetchGetMerchantImpersonationAudit(id: number) {
  return request.get<Api.Users.UserImpersonationAuditResponse>({
    url: `/api/admin/users/${id}/impersonation-audit`
  })
}

export function fetchImpersonateMerchant(
  id: number,
  params: Api.Users.UserImpersonationExecutePayload = {}
) {
  return request.post<Api.Users.UserImpersonationExecuteResponse>({
    url: `/api/admin/users/${id}/impersonate`,
    params,
    showSuccessMessage: false
  })
}

export function fetchAuditMerchantBatchDelete(params: Api.Users.UserBatchDeleteAuditPayload) {
  return request.post<Api.Users.UserBatchDeleteAuditResponse>({
    url: '/api/admin/users/batch-delete-audit',
    params
  })
}

export function fetchBatchDeleteMerchants(params: Api.Users.UserBatchDeletePayload) {
  return request.post<Api.Users.UserBatchDeleteResponse>({
    url: '/api/admin/users/batch-delete',
    params,
    showSuccessMessage: false
  })
}

export function fetchUpdateMerchant(id: number, params: Api.Users.UserUpdatePayload) {
  return request.post<Api.Users.UserUpdateResponse>({
    url: `/api/admin/users/${id}/update`,
    params,
    showSuccessMessage: true
  })
}

export function fetchUpdateMerchantStatus(id: number, params: Api.Users.UserStatusUpdatePayload) {
  return request.post<Api.Users.UserStatusUpdateResponse>({
    url: `/api/admin/users/${id}/status`,
    params,
    showSuccessMessage: true
  })
}

export function fetchUpdateMerchantBusiness(
  id: number,
  params: Api.Users.UserBusinessUpdatePayload
) {
  return request.post<Api.Users.UserBusinessUpdateResponse>({
    url: `/api/admin/users/${id}/business`,
    params,
    showSuccessMessage: true
  })
}

export function fetchUpdateMerchantNotifications(
  id: number,
  params: Api.Users.UserNotificationUpdatePayload
) {
  return request.post<Api.Users.UserNotificationUpdateResponse>({
    url: `/api/admin/users/${id}/notifications`,
    params,
    showSuccessMessage: true
  })
}

export function fetchGetMerchantDeleteAudit(id: number) {
  return request.get<Api.Users.UserDeleteAuditResponse>({
    url: `/api/admin/users/${id}/delete-audit`
  })
}

export function fetchDeleteMerchant(id: number, params: Api.Users.UserDeletePayload) {
  return request.post<Api.Users.UserDeleteResponse>({
    url: `/api/admin/users/${id}/delete`,
    params
  })
}
