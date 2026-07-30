import { apiClient } from './client'
import type { ApiResponse, Category, PaginatedData } from '@/types'

export interface ListCategoriesParams {
  q?: string
  sort?: string
  page?: number
}

export async function listCategories(
  params: ListCategoriesParams = {},
): Promise<PaginatedData<Category>> {
  const { data } = await apiClient.get<ApiResponse<PaginatedData<Category>>>('/categories', {
    params,
  })
  return data.data
}

export async function getCategory(slug: string): Promise<Category> {
  const { data } = await apiClient.get<ApiResponse<Category>>(`/categories/${slug}`)
  return data.data
}
