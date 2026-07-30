import { apiClient } from './client'
import type { ApiResponse, PaginatedData, PublicProduct } from '@/types'

export type ProductSort = 'latest' | 'name' | 'price' | '-price'

export interface ListProductsParams {
  q?: string
  category?: string
  min_price?: string
  max_price?: string
  sort?: ProductSort
  page?: number
}

export async function listProducts(
  params: ListProductsParams = {},
): Promise<PaginatedData<PublicProduct>> {
  const { data } = await apiClient.get<ApiResponse<PaginatedData<PublicProduct>>>('/products', {
    params,
  })
  return data.data
}

export async function getProduct(slug: string): Promise<PublicProduct> {
  const { data } = await apiClient.get<ApiResponse<PublicProduct>>(`/products/${slug}`)
  return data.data
}
