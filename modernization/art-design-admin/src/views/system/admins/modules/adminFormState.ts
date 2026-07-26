import type { FormRules } from 'element-plus'

type AdminAccountItem = Api.AdminAccounts.AdminAccountListItem
type AdminEditable = Api.AdminAccounts.AdminAccountEditable
type AdminEditableRoleItem = Api.AdminAccounts.AdminAccountEditableRoleItem

export interface AdminCreateFormState {
  username: string
  nickname: string
  password: string
  status: number
  role_ids: number[]
}

export interface AdminEditFormState {
  username: string
  nickname: string
  password: string
}

export interface AdminRoleFormState {
  role_ids: number[]
}

export interface AdminPermissionFormState {
  permission_ids: number[]
}

export function createAdminCreateFormState(): AdminCreateFormState {
  return {
    username: '',
    nickname: '',
    password: '',
    status: 1,
    role_ids: []
  }
}

export function createAdminEditFormState(): AdminEditFormState {
  return {
    username: '',
    nickname: '',
    password: ''
  }
}

export function createAdminRoleFormState(): AdminRoleFormState {
  return {
    role_ids: []
  }
}

export function createAdminPermissionFormState(): AdminPermissionFormState {
  return {
    permission_ids: []
  }
}

export function assignAdminCreateFormState(
  target: AdminCreateFormState,
  source: Partial<AdminCreateFormState>
) {
  target.username = source.username || ''
  target.nickname = source.nickname || ''
  target.password = source.password || ''
  target.status = Number(source.status ?? 1)
  target.role_ids = normalizeNumberList(source.role_ids)
}

export function assignAdminEditFormState(
  target: AdminEditFormState,
  source: Partial<AdminEditFormState>
) {
  target.username = source.username || ''
  target.nickname = source.nickname || ''
  target.password = source.password || ''
}

export function assignAdminRoleFormState(
  target: AdminRoleFormState,
  source: Partial<AdminRoleFormState>
) {
  target.role_ids = normalizeNumberList(source.role_ids)
}

export function assignAdminPermissionFormState(
  target: AdminPermissionFormState,
  source: Partial<AdminPermissionFormState>
) {
  target.permission_ids = normalizeNumberList(source.permission_ids)
}

export function syncAdminCreateFormFromEditable(
  target: AdminCreateFormState,
  editable: Partial<AdminEditable>
) {
  assignAdminCreateFormState(target, {
    username: editable.username || '',
    nickname: editable.nickname || '',
    password: '',
    status: Number(editable.status ?? 1),
    role_ids: editable.current_role_ids || []
  })
}

export function syncAdminEditFormFromItem(
  target: AdminEditFormState,
  item: Partial<AdminAccountItem>
) {
  assignAdminEditFormState(target, {
    username: item.username || '',
    nickname: item.nickname || '',
    password: ''
  })
}

export function syncAdminRoleFormFromEditable(
  target: AdminRoleFormState,
  editable: Partial<AdminEditable>
) {
  assignAdminRoleFormState(target, {
    role_ids: editable.current_role_ids || []
  })
}

export function syncAdminPermissionFormFromEditable(
  target: AdminPermissionFormState,
  editable: Partial<AdminEditable>
) {
  assignAdminPermissionFormState(target, {
    permission_ids: editable.current_direct_permission_ids || []
  })
}

export function buildAdminCreatePayload(form: AdminCreateFormState, allowRoleBinding: boolean) {
  return {
    username: normalizeInput(form.username),
    nickname: normalizeInput(form.nickname),
    password: form.password,
    status: Number(form.status ?? 1),
    role_ids: allowRoleBinding ? normalizeNumberList(form.role_ids) : []
  } satisfies Api.AdminAccounts.AdminAccountWritePayload
}

export function buildAdminEditPayload(form: AdminEditFormState) {
  return {
    username: normalizeInput(form.username),
    nickname: normalizeInput(form.nickname),
    password: normalizeInput(form.password) || undefined
  } satisfies Api.AdminAccounts.AdminAccountWritePayload
}

export function buildAdminRolePayload(form: AdminRoleFormState) {
  return {
    role_ids: normalizeNumberList(form.role_ids)
  } satisfies Api.AdminAccounts.AdminAccountRolePayload
}

export function buildAdminPermissionPayload(permissionIds: number[]) {
  return {
    permission_ids: normalizeNumberList(permissionIds)
  } satisfies Api.AdminAccounts.AdminAccountPermissionPayload
}

export function createAdminCreateRules(): FormRules<AdminCreateFormState> {
  return {
    username: [usernameRule(true)],
    nickname: [nicknameRule()],
    password: [passwordRule(true)],
    status: [{ required: true, message: '请选择状态。', trigger: 'change' }]
  }
}

export function createAdminEditRules(): FormRules<AdminEditFormState> {
  return {
    username: [usernameRule(true)],
    nickname: [nicknameRule()],
    password: [passwordRule(false)]
  }
}

export function displayAdminRoleOptionLabel(role: AdminEditableRoleItem) {
  return role.name || role.code || `角色 #${role.id}`
}

function usernameRule(required: boolean) {
  return {
    validator: (_rule: unknown, value: string, callback: (error?: Error) => void) => {
      const normalized = normalizeInput(value)
      if (!normalized) {
        if (required) {
          callback(new Error('请输入用户名。'))
          return
        }
        callback()
        return
      }

      if (normalized.length < 2 || normalized.length > 40) {
        callback(new Error('用户名长度需在 2 到 40 个字符之间。'))
        return
      }

      if (/\s/.test(normalized)) {
        callback(new Error('用户名不能包含空白字符。'))
        return
      }

      callback()
    },
    trigger: 'blur'
  }
}

function nicknameRule() {
  return {
    validator: (_rule: unknown, value: string, callback: (error?: Error) => void) => {
      const normalized = normalizeInput(value)
      if (!normalized) {
        callback(new Error('请输入昵称。'))
        return
      }

      if (normalized.length > 40) {
        callback(new Error('昵称长度不能超过 40 个字符。'))
        return
      }

      callback()
    },
    trigger: 'blur'
  }
}

function passwordRule(required: boolean) {
  return {
    validator: (_rule: unknown, value: string, callback: (error?: Error) => void) => {
      const normalized = normalizeInput(value)
      if (!normalized) {
        if (required) {
          callback(new Error('请输入密码。'))
          return
        }
        callback()
        return
      }

      if (normalized.length < 6 || normalized.length > 64) {
        callback(new Error('密码长度需在 6 到 64 个字符之间。'))
        return
      }

      callback()
    },
    trigger: 'blur'
  }
}

function normalizeInput(value: string | undefined) {
  return String(value || '').trim()
}

function normalizeNumberList(values: Array<number | string> | undefined) {
  return [...new Set((values || []).map((value) => Number(value)).filter((value) => value > 0))]
}
