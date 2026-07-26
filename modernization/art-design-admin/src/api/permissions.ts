/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

import request from '@/utils/http'

export function fetchGetAdminPermissionTree(params?: Api.Permissions.PermissionSearchParams) {
  return request.get<Api.Permissions.PermissionTreeResponse>({
    url: '/api/admin/permissions',
    params
  })
}

export function fetchGetAdminPermissionDetail(id: number) {
  return request.get<Api.Permissions.PermissionDetailResponse>({
    url: `/api/admin/permissions/${id}`
  })
}

export function fetchCreateAdminPermission(data: Api.Permissions.PermissionWritePayload) {
  return request.post<Api.Permissions.PermissionCreateResponse>({
    url: '/api/admin/permissions/create',
    data
  })
}

export function fetchUpdateAdminPermission(id: number, data: Api.Permissions.PermissionWritePayload) {
  return request.post<Api.Permissions.PermissionUpdateResponse>({
    url: `/api/admin/permissions/${id}/update`,
    data
  })
}

export function fetchUpdateAdminPermissionStatus(
  id: number,
  data: Api.Permissions.PermissionStatusPayload
) {
  return request.post<Api.Permissions.PermissionStatusResponse>({
    url: `/api/admin/permissions/${id}/status`,
    data
  })
}

export function fetchReorderAdminPermissions(data: Api.Permissions.PermissionReorderPayload) {
  return request.post<Api.Permissions.PermissionReorderResponse>({
    url: '/api/admin/permissions/reorder',
    data
  })
}

export function fetchGetAdminPermissionDeleteAudit(id: number) {
  return request.get<Api.Permissions.PermissionDeleteAuditResponse>({
    url: `/api/admin/permissions/${id}/delete-audit`
  })
}

export function fetchDeleteAdminPermission(id: number, data: Api.Permissions.PermissionDeletePayload) {
  return request.post<Api.Permissions.PermissionDeleteResponse>({
    url: `/api/admin/permissions/${id}/delete`,
    data
  })
}
