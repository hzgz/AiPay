import request from '@/utils/http'

export function fetchGetSystemConfigSummary(params: Api.Configs.SearchParams) {
  return request.get<Api.Configs.SummaryResponse>({
    url: '/api/admin/config',
    params
  })
}

export function fetchUpdateSystemConfig(params: Api.Configs.UpdatePayload) {
  return request.post<Api.Configs.UpdateResponse>({
    url: '/api/admin/config/update',
    params,
    showSuccessMessage: true
  })
}

export function fetchUpdateSystemConfigGroup(params: Api.Configs.GroupUpdatePayload) {
  return request.post<Api.Configs.GroupUpdateResponse>({
    url: '/api/admin/config/group-update',
    params,
    showSuccessMessage: true
  })
}
