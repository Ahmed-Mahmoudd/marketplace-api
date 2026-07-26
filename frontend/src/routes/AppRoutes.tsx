import { Navigate, Route, Routes } from 'react-router-dom'
import { MainLayout } from '@/components/layout/MainLayout'
import { AuthLayout } from '@/components/layout/AuthLayout'
import { GuestRoute, ProtectedRoute } from '@/routes/ProtectedRoute'
import { HomePage } from '@/pages/HomePage'
import { ProductsPage } from '@/pages/shop/ProductsPage'
import { ProductDetailPage } from '@/pages/shop/ProductDetailPage'
import { CartPage } from '@/pages/shop/CartPage'
import { CheckoutPage } from '@/pages/shop/CheckoutPage'
import { OrdersPage } from '@/pages/shop/OrdersPage'
import { OrderDetailPage } from '@/pages/shop/OrderDetailPage'
import { VendorApplyPage } from '@/pages/vendor/VendorApplyPage'
import { VendorLayout } from '@/pages/vendor/VendorLayout'
import { VendorProductsPage } from '@/pages/vendor/VendorProductsPage'
import { VendorProductFormPage } from '@/pages/vendor/VendorProductFormPage'
import { VendorOrdersPage } from '@/pages/vendor/VendorOrdersPage'
import { VendorOrderDetailPage } from '@/pages/vendor/VendorOrderDetailPage'
import { AdminLayout } from '@/pages/admin/AdminLayout'
import { AdminVendorsPage } from '@/pages/admin/AdminVendorsPage'
import { AdminCategoriesPage } from '@/pages/admin/AdminCategoriesPage'
import { LoginPage } from '@/pages/auth/LoginPage'
import { RegisterPage } from '@/pages/auth/RegisterPage'
import { NotFoundPage } from '@/pages/NotFoundPage'

export function AppRoutes() {
  return (
    <Routes>
      <Route element={<MainLayout />}>
        <Route index element={<HomePage />} />
        <Route path="products" element={<ProductsPage />} />
        <Route path="products/:slug" element={<ProductDetailPage />} />

        <Route element={<ProtectedRoute />}>
          <Route path="cart" element={<CartPage />} />
          <Route path="checkout" element={<CheckoutPage />} />
          <Route path="orders" element={<OrdersPage />} />
          <Route path="orders/:id" element={<OrderDetailPage />} />
          <Route path="vendor/apply" element={<VendorApplyPage />} />
        </Route>

        <Route element={<ProtectedRoute roles={['vendor']} />}>
          <Route path="vendor" element={<VendorLayout />}>
            <Route index element={<Navigate to="products" replace />} />
            <Route path="products" element={<VendorProductsPage />} />
            <Route path="products/new" element={<VendorProductFormPage />} />
            <Route path="products/:id/edit" element={<VendorProductFormPage />} />
            <Route path="orders" element={<VendorOrdersPage />} />
            <Route path="orders/:id" element={<VendorOrderDetailPage />} />
          </Route>
        </Route>

        <Route element={<ProtectedRoute roles={['admin']} />}>
          <Route path="admin" element={<AdminLayout />}>
            <Route index element={<Navigate to="vendors" replace />} />
            <Route path="vendors" element={<AdminVendorsPage />} />
            <Route path="categories" element={<AdminCategoriesPage />} />
          </Route>
        </Route>

        <Route path="*" element={<NotFoundPage />} />
      </Route>

      <Route element={<GuestRoute />}>
        <Route element={<AuthLayout />}>
          <Route path="login" element={<LoginPage />} />
          <Route path="register" element={<RegisterPage />} />
        </Route>
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
