/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

import request from '@/utils/http'

export function fetchGetCleanupAuditList(params: Api.CleanupAudit.CleanupAuditSearchParams) {
  return request.get<Api.CleanupAudit.CleanupAuditList>({
    url: '/api/admin/cleanup-audit',
    params
  })
}

export function fetchGetCleanupAuditDetail(key: string) {
  return request.get<Api.CleanupAudit.CleanupAuditDetailResponse>({
    url: `/api/admin/cleanup-audit/${encodeURIComponent(key)}`
  })
}

export function fetchGetCleanupExecutionAudit(key: string) {
  return request.get<Api.CleanupAudit.CleanupExecutionAuditResponse>({
    url: `/api/admin/cleanup-audit/${encodeURIComponent(key)}/execute-audit`
  })
}

export function fetchExecuteCleanupAction(
  key: string,
  data: Api.CleanupAudit.CleanupExecutePayload
) {
  return request.post<Api.CleanupAudit.CleanupExecuteResponse>({
    url: `/api/admin/cleanup-audit/${encodeURIComponent(key)}/execute`,
    data
  })
}
