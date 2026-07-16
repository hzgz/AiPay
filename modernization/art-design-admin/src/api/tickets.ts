import request from '@/utils/http'

export function fetchGetTicketList(params: Api.Tickets.TicketSearchParams) {
  return request.get<Api.Tickets.TicketList>({
    url: '/api/admin/tickets',
    params
  })
}

export function fetchGetTicketDetail(id: number) {
  return request.get<Api.Tickets.TicketDetailResponse>({
    url: `/api/admin/tickets/${id}`
  })
}

export function fetchReplyTicket(id: number, data: Api.Tickets.TicketReplyPayload) {
  return request.post<Api.Tickets.TicketReplyResponse>({
    url: `/api/admin/tickets/${id}/reply`,
    data
  })
}

export function fetchUpdateTicketStatus(id: number, data: Api.Tickets.TicketStatusPayload) {
  return request.post<Api.Tickets.TicketStatusResponse>({
    url: `/api/admin/tickets/${id}/status`,
    data
  })
}

export function fetchGetTicketDeleteAudit(id: number) {
  return request.get<Api.Tickets.TicketDeleteAuditResponse>({
    url: `/api/admin/tickets/${id}/delete-audit`
  })
}

export function fetchDeleteTicket(id: number, data: Api.Tickets.TicketDeletePayload) {
  return request.post<Api.Tickets.TicketDeleteResponse>({
    url: `/api/admin/tickets/${id}/delete`,
    data
  })
}

export function fetchAuditTicketBatchDelete(data: Api.Tickets.TicketBatchDeleteAuditPayload) {
  return request.post<Api.Tickets.TicketBatchDeleteAuditResponse>({
    url: '/api/admin/tickets/batch-delete-audit',
    data
  })
}

export function fetchBatchDeleteTickets(data: Api.Tickets.TicketBatchDeletePayload) {
  return request.post<Api.Tickets.TicketBatchDeleteResponse>({
    url: '/api/admin/tickets/batch-delete',
    data
  })
}

export function fetchGetTicketCategoryList(params: Api.Tickets.TicketCategorySearchParams) {
  return request.get<Api.Tickets.TicketCategoryList>({
    url: '/api/admin/ticket-categories',
    params
  })
}

export function fetchCreateTicketCategory(data: Api.Tickets.TicketCategoryWritePayload) {
  return request.post<Api.Tickets.TicketCategoryCreateResponse>({
    url: '/api/admin/ticket-categories/create',
    data
  })
}

export function fetchAuditTicketCategoryBatchDelete(
  data: Api.Tickets.TicketCategoryBatchDeleteAuditPayload
) {
  return request.post<Api.Tickets.TicketCategoryBatchDeleteAuditResponse>({
    url: '/api/admin/ticket-categories/batch-delete-audit',
    data
  })
}

export function fetchBatchDeleteTicketCategories(
  data: Api.Tickets.TicketCategoryBatchDeletePayload
) {
  return request.post<Api.Tickets.TicketCategoryBatchDeleteResponse>({
    url: '/api/admin/ticket-categories/batch-delete',
    data
  })
}

export function fetchGetTicketCategoryDeleteAudit(id: number) {
  return request.get<Api.Tickets.TicketCategoryDeleteAuditResponse>({
    url: `/api/admin/ticket-categories/${id}/delete-audit`
  })
}

export function fetchDeleteTicketCategory(id: number, data: Api.Tickets.TicketCategoryDeletePayload) {
  return request.post<Api.Tickets.TicketCategoryDeleteResponse>({
    url: `/api/admin/ticket-categories/${id}/delete`,
    data
  })
}

export function fetchUpdateTicketCategoryStatus(
  id: number,
  data: Api.Tickets.TicketCategoryStatusPayload
) {
  return request.post<Api.Tickets.TicketCategoryStatusResponse>({
    url: `/api/admin/ticket-categories/${id}/status`,
    data
  })
}

export function fetchGetTicketCategoryDetail(id: number) {
  return request.get<Api.Tickets.TicketCategoryDetailResponse>({
    url: `/api/admin/ticket-categories/${id}`
  })
}

export function fetchUpdateTicketCategory(id: number, data: Api.Tickets.TicketCategoryWritePayload) {
  return request.post<Api.Tickets.TicketCategoryUpdateResponse>({
    url: `/api/admin/ticket-categories/${id}/update`,
    data
  })
}
