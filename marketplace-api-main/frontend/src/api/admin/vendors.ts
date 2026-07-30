import { apiClient } from '../client'
import type { PaginatedData, Vendor } from '@/types'

export type VendorStatusFilter = 'pending' | 'approved' | 'suspended' | 'rejected'

interface LaravelResourceCollection<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export async function listAdminVendors(
  status?: VendorStatusFilter,
  page?: number,
): Promise<PaginatedData<Vendor>> {
  const { data } = await apiClient.get<LaravelResourceCollection<Vendor>>('/admin/vendors', {
    params: { status, page },
  })
  return { items: data.data, meta: data.meta }
}

export async function approveVendor(id: number): Promise<Vendor> {
  const { data } = await apiClient.post<{ data: Vendor }>(`/admin/vendors/${id}/approve`)
  return data.data
}

export async function suspendVendor(id: number): Promise<Vendor> {
  const { data } = await apiClient.post<{ data: Vendor }>(`/admin/vendors/${id}/suspend`)
  return data.data
}

export async function rejectVendor(id: number): Promise<Vendor> {
  const { data } = await apiClient.post<{ data: Vendor }>(`/admin/vendors/${id}/reject`)
  return data.data
}
