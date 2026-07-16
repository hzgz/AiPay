import request from '@/utils/http'

export function fetchGetAdminRoleList(params: Api.Roles.RoleSearchParams) {
  return request.get<Api.Roles.RoleList>({
    url: '/api/admin/roles',
    params
  })
}

export function fetchGetAdminRoleDetail(id: number) {
  return request.get<Api.Roles.RoleDetailResponse>({
    url: `/api/admin/roles/${id}`
  })
}

export function fetchCreateAdminRole(data: Api.Roles.RoleWritePayload) {
  return request.post<Api.Roles.RoleCreateResponse>({
    url: '/api/admin/roles/create',
    data
  })
}

export function fetchUpdateAdminRole(id: number, data: Api.Roles.RoleWritePayload) {
  return request.post<Api.Roles.RoleUpdateResponse>({
    url: `/api/admin/roles/${id}/update`,
    data
  })
}

export function fetchUpdateAdminRolePermissions(
  id: number,
  data: Api.Roles.RolePermissionPayload
) {
  return request.post<Api.Roles.RolePermissionUpdateResponse>({
    url: `/api/admin/roles/${id}/permissions`,
    data
  })
}

export function fetchGetAdminRoleDeleteAudit(id: number) {
  return request.get<Api.Roles.RoleDeleteAuditResponse>({
    url: `/api/admin/roles/${id}/delete-audit`
  })
}

export function fetchDeleteAdminRole(id: number, data: Api.Roles.RoleDeletePayload) {
  return request.post<Api.Roles.RoleDeleteResponse>({
    url: `/api/admin/roles/${id}/delete`,
    data
  })
}
