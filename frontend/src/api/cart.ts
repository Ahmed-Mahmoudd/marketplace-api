import { apiClient } from './client'
import type { ApiResponse, Cart, CartItem } from '@/types'

export async function getCart(): Promise<Cart> {
  const { data } = await apiClient.get<ApiResponse<Cart>>('/cart')
  return data.data
}

export async function addCartItem(productId: number, quantity: number): Promise<CartItem> {
  const { data } = await apiClient.post<ApiResponse<CartItem>>('/cart/items', {
    product_id: productId,
    quantity,
  })
  return data.data
}

export async function updateCartItem(itemId: number, quantity: number): Promise<CartItem> {
  const { data } = await apiClient.put<ApiResponse<CartItem>>(`/cart/items/${itemId}`, {
    quantity,
  })
  return data.data
}

export async function removeCartItem(itemId: number): Promise<void> {
  await apiClient.delete(`/cart/items/${itemId}`)
}

export async function clearCart(): Promise<void> {
  await apiClient.delete('/cart')
}
