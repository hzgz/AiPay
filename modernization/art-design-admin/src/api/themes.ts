import request from '@/utils/http'

export function fetchGetThemeList(params: Api.Themes.ThemeSearchParams) {
  return request.get<Api.Themes.ThemeList>({
    url: '/api/admin/themes',
    params
  })
}

export function fetchGetThemeDetail(scope: string, id: string) {
  return request.get<Api.Themes.ThemeDetailResponse>({
    url: `/api/admin/themes/${scope}/${id}`
  })
}

export function fetchActivateTheme(scope: string, id: string) {
  return request.post<Api.Themes.ThemeActivateResponse>({
    url: `/api/admin/themes/${scope}/${id}/activate`
  })
}

export function fetchGetThemeDeleteAudit(scope: string, id: string) {
  return request.get<Api.Themes.ThemeDeleteAuditResponse>({
    url: `/api/admin/themes/${scope}/${id}/delete-audit`
  })
}

export function fetchDeleteTheme(scope: string, id: string, data: Api.Themes.ThemeDeletePayload) {
  return request.post<Api.Themes.ThemeDeleteResponse>({
    url: `/api/admin/themes/${scope}/${id}/delete`,
    data
  })
}
