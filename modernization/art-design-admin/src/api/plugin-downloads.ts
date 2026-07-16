import request from '@/utils/http'

export function fetchGetPluginDownloadList(params: Api.PluginDownloads.PluginDownloadSearchParams) {
  return request.get<Api.PluginDownloads.PluginDownloadList>({
    url: '/api/admin/plugin-downloads',
    params
  })
}

export function fetchGetPluginDownloadDetail(id: number) {
  return request.get<Api.PluginDownloads.PluginDownloadDetailResponse>({
    url: `/api/admin/plugin-downloads/${id}`
  })
}

export function fetchCreatePluginDownload(data: Api.PluginDownloads.PluginDownloadWritePayload) {
  return request.post<Api.PluginDownloads.PluginDownloadCreateResponse>({
    url: '/api/admin/plugin-downloads/create',
    data
  })
}

export function fetchUpdatePluginDownload(id: number, data: Api.PluginDownloads.PluginDownloadWritePayload) {
  return request.post<Api.PluginDownloads.PluginDownloadUpdateResponse>({
    url: `/api/admin/plugin-downloads/${id}/update`,
    data
  })
}

export function fetchUpdatePluginDownloadStatus(
  id: number,
  data: Api.PluginDownloads.PluginDownloadStatusPayload
) {
  return request.post<Api.PluginDownloads.PluginDownloadStatusResponse>({
    url: `/api/admin/plugin-downloads/${id}/status`,
    data
  })
}

export function fetchGetPluginDownloadDeleteAudit(id: number) {
  return request.get<Api.PluginDownloads.PluginDownloadDeleteAuditResponse>({
    url: `/api/admin/plugin-downloads/${id}/delete-audit`
  })
}

export function fetchDeletePluginDownload(id: number, data: Api.PluginDownloads.PluginDownloadDeletePayload) {
  return request.post<Api.PluginDownloads.PluginDownloadDeleteResponse>({
    url: `/api/admin/plugin-downloads/${id}/delete`,
    data
  })
}

export function fetchAuditPluginDownloadBatchDelete(
  data: Api.PluginDownloads.PluginDownloadBatchDeleteAuditPayload
) {
  return request.post<Api.PluginDownloads.PluginDownloadBatchDeleteAuditResponse>({
    url: '/api/admin/plugin-downloads/batch-delete-audit',
    data
  })
}

export function fetchBatchDeletePluginDownloads(
  data: Api.PluginDownloads.PluginDownloadBatchDeletePayload
) {
  return request.post<Api.PluginDownloads.PluginDownloadBatchDeleteResponse>({
    url: '/api/admin/plugin-downloads/batch-delete',
    data
  })
}

export function fetchRestorePluginDownload(id: number) {
  return request.post<Api.PluginDownloads.PluginDownloadRestoreResponse>({
    url: `/api/admin/plugin-downloads/${id}/restore`
  })
}

export function fetchBatchRestorePluginDownloads(
  data: Api.PluginDownloads.PluginDownloadBatchRestorePayload
) {
  return request.post<Api.PluginDownloads.PluginDownloadBatchRestoreResponse>({
    url: '/api/admin/plugin-downloads/batch-restore',
    data
  })
}
