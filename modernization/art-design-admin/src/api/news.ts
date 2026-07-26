/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

import request from '@/utils/http'

export function fetchGetNewsList(params: Api.News.NewsSearchParams) {
  return request.get<Api.News.NewsList>({
    url: '/api/admin/news',
    params
  })
}

export function fetchGetNewsDetail(id: number) {
  return request.get<Api.News.NewsDetailResponse>({
    url: `/api/admin/news/${id}`
  })
}

export function fetchCreateNews(data: Api.News.NewsWritePayload) {
  return request.post<Api.News.NewsCreateResponse>({
    url: '/api/admin/news/create',
    data
  })
}

export function fetchUpdateNews(id: number, data: Api.News.NewsWritePayload) {
  return request.post<Api.News.NewsUpdateResponse>({
    url: `/api/admin/news/${id}/update`,
    data
  })
}

export function fetchUpdateNewsStatus(id: number, data: Api.News.NewsStatusPayload) {
  return request.post<Api.News.NewsStatusResponse>({
    url: `/api/admin/news/${id}/status`,
    data
  })
}

export function fetchGetNewsDeleteAudit(id: number) {
  return request.get<Api.News.NewsDeleteAuditResponse>({
    url: `/api/admin/news/${id}/delete-audit`
  })
}

export function fetchDeleteNews(id: number, data: Api.News.NewsDeletePayload) {
  return request.post<Api.News.NewsDeleteResponse>({
    url: `/api/admin/news/${id}/delete`,
    data
  })
}

export function fetchAuditNewsBatchDelete(data: Api.News.NewsBatchDeleteAuditPayload) {
  return request.post<Api.News.NewsBatchDeleteAuditResponse>({
    url: '/api/admin/news/batch-delete-audit',
    data
  })
}

export function fetchBatchDeleteNews(data: Api.News.NewsBatchDeletePayload) {
  return request.post<Api.News.NewsBatchDeleteResponse>({
    url: '/api/admin/news/batch-delete',
    data
  })
}

export function fetchRestoreNews(id: number) {
  return request.post<Api.News.NewsRestoreResponse>({
    url: `/api/admin/news/${id}/restore`
  })
}

export function fetchBatchRestoreNews(data: Api.News.NewsBatchRestorePayload) {
  return request.post<Api.News.NewsBatchRestoreResponse>({
    url: '/api/admin/news/batch-restore',
    data
  })
}
