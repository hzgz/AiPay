/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

import request from '@/utils/http'

export function fetchGetSystemCacheAudit() {
  return request.get<Api.SystemCache.CacheAuditResponse>({
    url: '/api/admin/system-cache/audit'
  })
}

export function fetchCleanupServerCache(data?: Api.SystemCache.ServerCacheCleanupPayload) {
  return request.post<Api.SystemCache.ServerCacheCleanupResponse>({
    url: '/api/admin/system-cache/server/cleanup',
    data
  })
}
