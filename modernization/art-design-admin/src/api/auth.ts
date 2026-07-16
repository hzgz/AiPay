import request from '@/utils/http'

interface WebmanLoginResponse {
  token: string
  token_type?: string
}

interface WebmanAdminInfo {
  id: number
  username: string
  nickname: string
  roles: string[]
}

export function fetchLogin(params: Api.Auth.LoginParams) {
  return request
    .post<WebmanLoginResponse>({
      url: '/api/admin/login',
      params: {
        username: params.userName,
        password: params.password
      }
    })
    .then((data): Api.Auth.LoginResponse => {
      const tokenType = data.token_type || 'Bearer'
      return {
        token: `${tokenType} ${data.token}`,
        refreshToken: ''
      }
    })
}

export function fetchGetUserInfo() {
  return request
    .get<WebmanAdminInfo>({
      url: '/api/admin/me'
    })
    .then(
      (data): Api.Auth.UserInfo => ({
        buttons: [],
        roles: data.roles || [],
        userId: data.id,
        userName: data.nickname || data.username,
        email: '',
        avatar: ''
      })
    )
}
