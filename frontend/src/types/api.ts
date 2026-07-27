export interface ApiResponse<T> {
  message: string
  data: T
}

export interface ApiErrorBody {
  message: string
  errors?: Record<string, string[]>
}

export interface PaginatedMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface PaginatedData<T> {
  items: T[]
  meta: PaginatedMeta
}

export interface Category {
  id: number
  name: string
  slug: string
  description: string | null
  is_active?: boolean
  created_at?: string
}

export interface ProductImage {
  id: number
  url: string
  is_primary: boolean
}

export interface VendorSummary {
  id: number
  store_name: string
  store_slug: string
}

export interface Vendor extends VendorSummary {
  status: 'pending' | 'approved' | 'suspended' | 'rejected'
  commission_rate?: string
  approved_at?: string | null
  created_at?: string
  user?: User
}

export interface PublicProduct {
  id: number
  name: string
  slug: string
  description: string | null
  price: string
  stock: number
  category?: Category
  vendor?: VendorSummary
  images?: ProductImage[]
  average_rating?: number | null
  reviews_count?: number
  created_at?: string
}

export interface Product extends PublicProduct {
  vendor_id: number
  status: 'active' | 'inactive'
}

export interface CartItem {
  id: number
  product: PublicProduct
  unit_price: string
  quantity: number
  subtotal: number
}

export interface Cart {
  items: CartItem[]
  items_count: number
  total: number
}

export interface OrderItem {
  id: number
  product_id: number
  vendor_id: number
  product_name: string
  unit_price: string
  quantity: number
  subtotal: string | number
  product?: PublicProduct
  vendor?: VendorSummary
}

export type OrderStatus =
  | 'pending'
  | 'confirmed'
  | 'processing'
  | 'shipped'
  | 'delivered'
  | 'cancelled'

export interface Order {
  id: number
  order_number: string
  status: OrderStatus
  payment_method: 'cod'
  payment_status: 'pending'
  subtotal: string
  total: string
  items?: OrderItem[]
  items_count?: number
  created_at: string
  updated_at?: string
}

export interface VendorOrder {
  id: number
  order_number: string
  status: OrderStatus
  payment_method: 'cod'
  payment_status: 'pending'
  items?: OrderItem[]
  items_count?: number
  vendor_subtotal: number
  customer?: {
    id: number
    name: string
    email: string
  }
  created_at: string
}

export interface Review {
  id: number
  rating: number
  comment: string | null
  user: {
    id: number
    name: string
  }
  created_at: string
  updated_at: string
}

export interface User {
  id: number
  name: string
  email: string
  gender: string | null
  city: string | null
  roles?: string[]
  vendor?: Vendor | null
  created_at?: string
}

export interface AuthPayload {
  user: User
  token: string
}

export type UserRole = 'admin' | 'vendor' | 'customer'
