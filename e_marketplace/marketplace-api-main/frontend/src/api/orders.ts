import { apiClient } from './client'
import { generateIdempotencyKey } from '@/lib/utils'
import type { ApiResponse, Order, PaginatedData } from '@/types'

export interface ListOrdersParams {
  page?: number
}

export async function listOrders(params: ListOrdersParams = {}): Promise<PaginatedData<Order>> {
  const { data } = await apiClient.get<ApiResponse<PaginatedData<Order>>>('/orders', {
    params,
  })
  return data.data
}

export async function getOrder(id: number | string): Promise<Order> {
  const { data } = await apiClient.get<ApiResponse<Order>>(`/orders/${id}`)
  return data.data
}

export async function cancelOrder(id: number | string): Promise<Order> {
  const { data } = await apiClient.post<ApiResponse<Order>>(`/orders/${id}/cancel`)
  return data.data
}

export async function checkout(): Promise<Order> {
  const { data } = await apiClient.post<ApiResponse<Order>>(
    '/checkout',
    {},
    {
      headers: {
        'Idempotency-Key': generateIdempotencyKey(),
      },
    },
  )
  return data.data
}
