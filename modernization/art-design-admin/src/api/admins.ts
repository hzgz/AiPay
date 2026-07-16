import request from '@/utils/http'

export function fetchGetAdminAccountList(params: Api.AdminAccounts.AdminAccountSearchParams) {
  return request.get<Api.AdminAccounts.AdminAccountList>({
    url: '/api/admin/admins',
    params
  })
}

export function fetchGetAdminAccountDetail(id: number) {
  return request.get<Api.AdminAccounts.AdminAccountDetailResponse>({
    url: `/api/admin/admins/${id}`
  })
}

export function fetchGetAdminAccountTemplate() {
  return request.get<Api.AdminAccounts.AdminAccountTemplateResponse>({
    url: '/api/admin/admins/template'
  })
}

export function fetchCreateAdminAccount(data: Api.AdminAccounts.AdminAccountWritePayload) {
  return request.post<Api.AdminAccounts.AdminAccountCreateResponse>({
    url: '/api/admin/admins/create',
    data
  })
}

export function fetchBatchRestoreAdminAccounts(
  data: Api.AdminAccounts.AdminAccountBatchRestorePayload
) {
  return request.post<Api.AdminAccounts.AdminAccountBatchRestoreResponse>({
    url: '/api/admin/admins/batch-restore',
    data
  })
}

export function fetchAuditAdminAccountBatchDelete(
  data: Api.AdminAccounts.AdminAccountBatchDeleteAuditPayload
) {
  return request.post<Api.AdminAccounts.AdminAccountBatchDeleteAuditResponse>({
    url: '/api/admin/admins/batch-delete-audit',
    data
  })
}

export function fetchBatchDeleteAdminAccounts(
  data: Api.AdminAccounts.AdminAccountBatchDeletePayload
) {
  return request.post<Api.AdminAccounts.AdminAccountBatchDeleteResponse>({
    url: '/api/admin/admins/batch-delete',
    data
  })
}

export function fetchUpdateAdminAccount(
  id: number,
  data: Api.AdminAccounts.AdminAccountWritePayload
) {
  return request.post<Api.AdminAccounts.AdminAccountUpdateResponse>({
    url: `/api/admin/admins/${id}/update`,
    data
  })
}

export function fetchUpdateAdminAccountStatus(
  id: number,
  data: Api.AdminAccounts.AdminAccountStatusPayload
) {
  return request.post<Api.AdminAccounts.AdminAccountStatusResponse>({
    url: `/api/admin/admins/${id}/status`,
    data
  })
}

export function fetchUpdateAdminAccountRoles(
  id: number,
  data: Api.AdminAccounts.AdminAccountRolePayload
) {
  return request.post<Api.AdminAccounts.AdminAccountRoleUpdateResponse>({
    url: `/api/admin/admins/${id}/roles`,
    data
  })
}

export function fetchUpdateAdminAccountPermissions(
  id: number,
  data: Api.AdminAccounts.AdminAccountPermissionPayload
) {
  return request.post<Api.AdminAccounts.AdminAccountPermissionUpdateResponse>({
    url: `/api/admin/admins/${id}/permissions`,
    data
  })
}

export function fetchGetAdminAccountDeleteAudit(id: number) {
  return request.get<Api.AdminAccounts.AdminAccountDeleteAuditResponse>({
    url: `/api/admin/admins/${id}/delete-audit`
  })
}

export function fetchDeleteAdminAccount(
  id: number,
  data: Api.AdminAccounts.AdminAccountDeletePayload
) {
  return request.post<Api.AdminAccounts.AdminAccountDeleteResponse>({
    url: `/api/admin/admins/${id}/delete`,
    data
  })
}

export function fetchRestoreAdminAccount(id: number) {
  return request.post<Api.AdminAccounts.AdminAccountRestoreResponse>({
    url: `/api/admin/admins/${id}/restore`
  })
}
