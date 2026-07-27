import { apiClient } from './client'
import type { ApiResponse, PaginatedData, Review } from '@/types'

export interface ListReviewsParams {
  page?: number
}

export interface CreateReviewInput {
  rating: number
  comment?: string | null
}

export interface UpdateReviewInput {
  rating?: number
  comment?: string | null
}

export async function listReviews(
  productSlug: string,
  params: ListReviewsParams = {},
): Promise<PaginatedData<Review>> {
  const { data } = await apiClient.get<ApiResponse<PaginatedData<Review>>>(
    `/products/${productSlug}/reviews`,
    { params },
  )
  return data.data
}

export async function createReview(
  productSlug: string,
  input: CreateReviewInput,
): Promise<Review> {
  const { data } = await apiClient.post<ApiResponse<Review>>(
    `/products/${productSlug}/reviews`,
    input,
  )
  return data.data
}

export async function updateReview(
  reviewId: number | string,
  input: UpdateReviewInput,
): Promise<Review> {
  const { data } = await apiClient.put<ApiResponse<Review>>(`/reviews/${reviewId}`, input)
  return data.data
}

export async function deleteReview(reviewId: number | string): Promise<void> {
  await apiClient.delete(`/reviews/${reviewId}`)
}
