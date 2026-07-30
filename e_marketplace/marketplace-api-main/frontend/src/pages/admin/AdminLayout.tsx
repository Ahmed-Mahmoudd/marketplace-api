import { NavLink, Outlet } from 'react-router-dom'
import { cn } from '@/lib/utils'

const tabClass = ({ isActive }: { isActive: boolean }) =>
  cn(
    'rounded-md px-3 py-2 text-sm font-medium transition-colors',
    isActive ? 'bg-accent-muted text-accent' : 'text-muted hover:bg-surface-muted hover:text-foreground',
  )

export function AdminLayout() {
  return (
    <div className="container-app py-8">
      <div className="mb-6">
        <h1 className="text-xl font-semibold text-ink">Admin dashboard</h1>
        <p className="mt-1 text-sm text-muted">Manage vendor approvals and the category catalog.</p>
      </div>

      <nav className="mb-6 flex gap-2 border-b border-border pb-2">
        <NavLink to="/admin/vendors" className={tabClass}>
          Vendors
        </NavLink>
        <NavLink to="/admin/categories" className={tabClass}>
          Categories
        </NavLink>
      </nav>

      <Outlet />
    </div>
  )
}
