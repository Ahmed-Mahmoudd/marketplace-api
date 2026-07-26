import { apiClient } from './client'
import type { ApiResponse, PaginatedData, Product, ProductImage, Vendor, VendorOrder } from '@/types'
import type { ProductSort } from './products'

export interface VendorApplyInput {
  store_name: string
  payout_details?: {
    bank_name: string
    iban: string
  }
}

export async function applyForVendor(input: VendorApplyInput): Promise<Vendor> {
  const { data } = await apiClient.post<ApiResponse<Vendor>>('/vendor/apply', input)
  return data.data
}

export async function getMyVendor(): Promise<Vendor> {
  const { data } = await apiClient.get<ApiResponse<Vendor>>('/vendor/me')
  return data.data
}

// ---------------------------------------------------------------------------
// Vendor products (/api/vendor/products)
// ---------------------------------------------------------------------------

export type VendorProductStatus = 'active' | 'inactive'

export interface ListVendorProductsParams {
  q?: string
  status?: VendorProductStatus
  sort?: ProductSort
  page?: number
}

export async function listVendorProducts(
  params: ListVendorProductsParams = {},
): Promise<PaginatedData<Product>> {
  const { data } = await apiClient.get<ApiResponse<PaginatedData<Product>>>('/vendor/products', {
    params,
  })
  return data.data
}

export async function getVendorProduct(id: number | string): Promise<Product> {
  const { data } = await apiClient.get<ApiResponse<Product>>(`/vendor/products/${id}`)
  return data.data
}

export interface VendorProductInput {
  category_id: number
  name: string
  description?: string | null
  price: number | string
  stock: number
  status?: VendorProductStatus
}

export async function createVendorProduct(input: VendorProductInput): Promise<Product> {
  const { data } = await apiClient.post<ApiResponse<Product>>('/vendor/products', input)
  return data.data
}

export async function updateVendorProduct(
  id: number | string,
  input: Partial<VendorProductInput>,
): Promise<Product> {
  const { data } = await apiClient.put<ApiResponse<Product>>(`/vendor/products/${id}`, input)
  return data.data
}

export async function deleteVendorProduct(id: number | string): Promise<void> {
  await apiClient.delete(`/vendor/products/${id}`)
}

// ---------------------------------------------------------------------------
// Vendor product images (/api/vendor/products/{product}/images)
// ---------------------------------------------------------------------------

export async function uploadVendorProductImages(
  productId: number | string,
  files: File[],
  isPrimary = false,
): Promise<ProductImage[]> {
  const formData = new FormData()
  files.forEach((file) => formData.append('images[]', file))
  if (isPrimary) {
    formData.append('is_primary', '1')
  }

  const { data } = await apiClient.post<ApiResponse<ProductImage[]>>(
    `/vendor/products/${productId}/images`,
    formData,
    { headers: { 'Content-Type': 'multipart/form-data' } },
  )
  return data.data
}

export async function setVendorProductImagePrimary(
  productId: number | string,
  imageId: number | string,
): Promise<ProductImage> {
  const { data } = await apiClient.patch<ApiResponse<ProductImage>>(
    `/vendor/products/${productId}/images/${imageId}/primary`,
  )
  return data.data
}

export async function deleteVendorProductImage(
  productId: number | string,
  imageId: number | string,
): Promise<void> {
  await apiClient.delete(`/vendor/products/${productId}/images/${imageId}`)
}

// ---------------------------------------------------------------------------
// Vendor orders (/api/vendor/orders)
// ---------------------------------------------------------------------------

export interface ListVendorOrdersParams {
  page?: number
}

export async function listVendorOrders(
  params: ListVendorOrdersParams = {},
): Promise<PaginatedData<VendorOrder>> {
  const { data } = await apiClient.get<ApiResponse<PaginatedData<VendorOrder>>>('/vendor/orders', {
    params,
  })
  return data.data
}

export async function getVendorOrder(id: number | string): Promise<VendorOrder> {
  const { data } = await apiClient.get<ApiResponse<VendorOrder>>(`/vendor/orders/${id}`)
  return data.data
}
