export interface MerchantUserEditFormState {
  email: string
  mobile: string
  remarks: string
}

export interface MerchantUserCreateFormState {
  username: string
  password: string
  email: string
  mobile: string
  remarks: string
  vip_id: number
  vip_time: string
  fee_rate: string
  is_rate: number
}

export interface MerchantUserEmailFormState {
  scope: Api.Users.UserEmailScope
  merchant_ids: number[]
  email: string
  title: string
  content: string
}

export interface MerchantUserBusinessFormState {
  vip_id: number
  vip_time: string
  fee_rate: string
  is_rate: number
}

export interface MerchantUserNotificationFormState {
  order_tips: string
  is_money_tips: string
  money_tips: string
}

export interface MerchantUserStatusFormState {
  status: boolean
  frozen_reason: string
}

export function createMerchantUserEditFormState(): MerchantUserEditFormState {
  return {
    email: '',
    mobile: '',
    remarks: ''
  }
}

export function createMerchantUserCreateFormState(): MerchantUserCreateFormState {
  return {
    username: '',
    password: '',
    email: '',
    mobile: '',
    remarks: '',
    vip_id: 0,
    vip_time: '',
    fee_rate: '',
    is_rate: 0
  }
}

export function createMerchantUserEmailFormState(
  scope: Api.Users.UserEmailScope = 'vip'
): MerchantUserEmailFormState {
  return {
    scope,
    merchant_ids: [],
    email: '',
    title: '',
    content: ''
  }
}

export function createMerchantUserBusinessFormState(): MerchantUserBusinessFormState {
  return {
    vip_id: 0,
    vip_time: '',
    fee_rate: '',
    is_rate: 0
  }
}

export function createMerchantUserNotificationFormState(): MerchantUserNotificationFormState {
  return {
    order_tips: 'close',
    is_money_tips: 'close',
    money_tips: '0'
  }
}

export function createMerchantUserStatusFormState(): MerchantUserStatusFormState {
  return {
    status: false,
    frozen_reason: ''
  }
}
