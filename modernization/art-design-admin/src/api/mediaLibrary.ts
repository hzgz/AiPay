import request from '@/utils/http'

export function fetchGetMediaLibraryList(params: Api.MediaLibrary.MediaLibrarySearchParams) {
  return request.get<Api.MediaLibrary.MediaLibraryList>({
    url: '/api/admin/media-library',
    params
  })
}

export function fetchGetMediaLibraryDetail(path: string) {
  return request.get<Api.MediaLibrary.MediaLibraryDetailResponse>({
    url: `/api/admin/media-library/${encodeURIComponent(path)}`
  })
}

export function fetchCreateMediaLibraryDirectory(payload: Api.MediaLibrary.MediaLibraryCreateDirectoryPayload) {
  return request.post<Api.MediaLibrary.MediaLibraryCreateDirectoryResponse>({
    url: '/api/admin/media-library/create-directory',
    data: payload
  })
}

export function fetchUploadMediaLibraryFiles(path: string, files: File | File[]) {
  const formData = new FormData()
  const normalizedFiles = Array.isArray(files) ? files : [files]

  normalizedFiles.forEach((file) => {
    formData.append('files[]', file)
  })

  return request.post<Api.MediaLibrary.MediaLibraryUploadResponse>({
    url: `/api/admin/media-library/${encodeURIComponent(path)}/upload`,
    data: formData
  })
}

export function fetchGetMediaLibraryFileDeleteAudit(file: Api.MediaLibrary.MediaLibraryFileSelector) {
  return request.post<Api.MediaLibrary.MediaLibraryFileDeleteAuditResponse>({
    url: '/api/admin/media-library/file-delete-audit',
    data: {
      file
    }
  })
}

export function fetchDeleteMediaLibraryFile(payload: Api.MediaLibrary.MediaLibraryFileDeletePayload) {
  return request.post<Api.MediaLibrary.MediaLibraryFileDeleteResponse>({
    url: '/api/admin/media-library/file-delete',
    data: payload
  })
}

export function fetchGetMediaLibraryBatchDeleteAudit(files: Api.MediaLibrary.MediaLibraryFileSelector[]) {
  return request.post<Api.MediaLibrary.MediaLibraryBatchDeleteAuditResponse>({
    url: '/api/admin/media-library/batch-delete-audit',
    data: {
      files
    }
  })
}

export function fetchBatchDeleteMediaLibraryFiles(payload: Api.MediaLibrary.MediaLibraryBatchDeletePayload) {
  return request.post<Api.MediaLibrary.MediaLibraryBatchDeleteResponse>({
    url: '/api/admin/media-library/batch-delete',
    data: payload
  })
}

export function fetchGetMediaLibraryDirectoryDeleteAudit(path: string) {
  return request.get<Api.MediaLibrary.MediaLibraryDirectoryDeleteAuditResponse>({
    url: `/api/admin/media-library/${encodeURIComponent(path)}/delete-audit`
  })
}

export function fetchDeleteMediaLibraryDirectory(
  path: string,
  payload: Api.MediaLibrary.MediaLibraryDirectoryDeletePayload
) {
  return request.post<Api.MediaLibrary.MediaLibraryDirectoryDeleteResponse>({
    url: `/api/admin/media-library/${encodeURIComponent(path)}/delete`,
    data: payload
  })
}
