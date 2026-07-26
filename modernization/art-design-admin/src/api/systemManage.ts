import request from '@/utils/http'
import { AppRouteRecord } from '@/types/router'

// 获取用户列表
export function fetchGetUserList(params: Api.SystemManage.UserSearchParams) {
  return request.get<Api.SystemManage.UserList>({
    url: '/api/user/list',
    params
  })
}

// 获取角色列表
export function fetchGetRoleList(params: Api.SystemManage.RoleSearchParams) {
  return request.get<Api.SystemManage.RoleList>({
    url: '/api/role/list',
    params
  })
}

// 获取菜单列表
export function fetchGetMenuList() {
  return request.get<AppRouteRecord[]>({
    url: '/api/admin/menus'
  })
}

export function fetchGetPaymentPlugins() {
  return request.get<Api.SystemManage.PaymentPluginListResponse>({
    url: '/api/admin/payment-plugins'
  })
}

export function fetchCreatePaymentPluginScaffold(data: {
  code: string
  name: string
  provider: string
  description: string
  version: string
  capabilities: string[]
}) {
  return request.post<Api.SystemManage.PaymentPluginScaffoldResponse>({
    url: '/api/admin/payment-plugins/scaffold',
    data,
    showSuccessMessage: false
  })
}

export function fetchCleanupPaymentPluginRegistryResidue(
  code: string,
  data: { confirm_code: string; confirm_phrase: string }
) {
  return request.post<Api.SystemManage.PaymentPluginRegistryResidueCleanupResponse>({
    url: `/api/admin/payment-plugin-registry-residues/${code}/cleanup`,
    data,
    showSuccessMessage: false
  })
}

export function fetchGetPaymentPluginDetail(code: string) {
  return request.get<Api.SystemManage.PaymentPluginDetail>({
    url: `/api/admin/payment-plugins/${code}`
  })
}

export function fetchGetPaymentPluginHistory(code: string) {
  return request.get<Api.SystemManage.PaymentPluginHistory>({
    url: `/api/admin/payment-plugins/${code}/history`
  })
}

export function fetchGetPaymentPluginBundle(code: string) {
  return request.get<Api.SystemManage.PaymentPluginBundle>({
    url: `/api/admin/payment-plugins/${code}/bundle`
  })
}

export function fetchGetPaymentPluginRecoveryVault() {
  return request.get<Api.SystemManage.PaymentPluginRecoveryVaultResponse>({
    url: '/api/admin/payment-plugin-snapshots'
  })
}

export function fetchGetPaymentPluginSnapshots(code: string) {
  return request.get<Api.SystemManage.PaymentPluginSnapshotList>({
    url: `/api/admin/payment-plugins/${code}/snapshots`
  })
}

export function fetchCreatePaymentPluginSnapshot(
  code: string,
  data: { label?: string | null } = {}
) {
  return request.post<Api.SystemManage.PaymentPluginSnapshotActionResponse>({
    url: `/api/admin/payment-plugins/${code}/snapshot`,
    data,
    showSuccessMessage: false
  })
}

export function fetchRestorePaymentPluginSnapshot(
  code: string,
  data: { snapshot_id: string; confirm_code: string; confirm_phrase: string }
) {
  return request.post<Api.SystemManage.PaymentPluginSnapshotActionResponse>({
    url: `/api/admin/payment-plugins/${code}/restore-snapshot`,
    data,
    showSuccessMessage: false
  })
}

export function fetchDeletePaymentPluginSnapshot(
  code: string,
  data: { snapshot_id: string; confirm_code: string; confirm_phrase: string }
) {
  return request.post<Api.SystemManage.PaymentPluginSnapshotDeleteResponse>({
    url: `/api/admin/payment-plugins/${code}/delete-snapshot`,
    data,
    showSuccessMessage: false
  })
}

export function fetchInstallPaymentPlugin(code: string) {
  return request.post<Api.SystemManage.PaymentPluginDetail>({
    url: `/api/admin/payment-plugins/${code}/install`,
    showSuccessMessage: false
  })
}

export function fetchRepairPaymentPlugin(code: string) {
  return request.post<Api.SystemManage.PaymentPluginDetail>({
    url: `/api/admin/payment-plugins/${code}/repair`,
    showSuccessMessage: false
  })
}

export function fetchUpgradePaymentPlugin(code: string) {
  return request.post<Api.SystemManage.PaymentPluginDetail>({
    url: `/api/admin/payment-plugins/${code}/upgrade`,
    showSuccessMessage: false
  })
}

export function fetchEnablePaymentPlugin(code: string) {
  return request.post<Api.SystemManage.PaymentPluginDetail>({
    url: `/api/admin/payment-plugins/${code}/enable`,
    showSuccessMessage: false
  })
}

export function fetchDisablePaymentPlugin(code: string) {
  return request.post<Api.SystemManage.PaymentPluginDetail>({
    url: `/api/admin/payment-plugins/${code}/disable`,
    showSuccessMessage: false
  })
}

export function fetchSavePaymentPluginConfig(
  code: string,
  data: { config: Record<string, string | null> }
) {
  return request.post<Api.SystemManage.PaymentPluginDetail>({
    url: `/api/admin/payment-plugins/${code}/config`,
    data,
    showSuccessMessage: false
  })
}

export function fetchGetPaymentPluginUninstallPlan(
  code: string,
  params: { purge: boolean }
) {
  return request.post<Api.SystemManage.PaymentPluginUninstallPlan>({
    url: `/api/admin/payment-plugins/${code}/uninstall-plan`,
    params,
    showSuccessMessage: false
  })
}

export function fetchUninstallPaymentPlugin(code: string, params: { purge: boolean }) {
  return request.post<Api.SystemManage.PaymentPluginDetail>({
    url: `/api/admin/payment-plugins/${code}/uninstall`,
    params,
    showSuccessMessage: false
  })
}

export function fetchCleanupPaymentPlugin(code: string, data: { confirm_code: string }) {
  return request.post<Api.SystemManage.PaymentPluginCleanupResponse>({
    url: `/api/admin/payment-plugins/${code}/cleanup-safe`,
    data,
    showSuccessMessage: false
  })
}

export function fetchPurgeCleanupPaymentPlugin(
  code: string,
  data: { confirm_code: string; confirm_phrase: string }
) {
  return request.post<Api.SystemManage.PaymentPluginCleanupResponse>({
    url: `/api/admin/payment-plugins/${code}/cleanup-purge`,
    data,
    showSuccessMessage: false
  })
}
