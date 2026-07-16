import request from '@/utils/http'

export function fetchGetOrderList(params: Api.Orders.OrderSearchParams) {
  return request.get<Api.Orders.OrderList>({
    url: '/api/admin/orders',
    params
  })
}

export function fetchGetOrderDetail(id: number) {
  return request.get<Api.Orders.OrderDetailResponse>({
    url: `/api/admin/orders/${id}`
  })
}
