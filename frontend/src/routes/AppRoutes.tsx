import { Navigate, Route, Routes } from 'react-router-dom'
import { MainLayout } from '@/components/layout/MainLayout'
import { AuthLayout } from '@/components/layout/AuthLayout'
import { GuestRoute, ProtectedRoute } from '@/routes/ProtectedRoute'
import { HomePage } from '@/pages/HomePage'
import { ProductsPage } from '@/pages/shop/ProductsPage'
import { LoginPage } from '@/pages/auth/LoginPage'
import { RegisterPage } from '@/pages/auth/RegisterPage'
import { NotFoundPage } from '@/pages/NotFoundPage'
import { PlaceholderPage } from '@/pages/PlaceholderPage'

export function AppRoutes() {
  return (
    <Routes>
      <Route element={<MainLayout />}>
        <Route index element={<HomePage />} />
        <Route path="products" element={<ProductsPage />} />

        <Route element={<ProtectedRoute />}>
          <Route
            path="cart"
            element={
              <PlaceholderPage
                title="Cart"
                description="Cart UI will be implemented in Phase 3."
              />
            }
          />
          <Route
            path="orders"
            element={
              <PlaceholderPage
                title="My orders"
                description="Order history will be implemented in Phase 3."
              />
            }
          />
        </Route>

        <Route element={<ProtectedRoute roles={['vendor']} />}>
          <Route
            path="vendor/*"
            element={
              <PlaceholderPage
                title="Vendor dashboard"
                description="Vendor tools will be implemented in Phase 4."
              />
            }
          />
        </Route>

        <Route element={<ProtectedRoute roles={['admin']} />}>
          <Route
            path="admin/*"
            element={
              <PlaceholderPage
                title="Admin dashboard"
                description="Admin tools will be implemented in Phase 5."
              />
            }
          />
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
