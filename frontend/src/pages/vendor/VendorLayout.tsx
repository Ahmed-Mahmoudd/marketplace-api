import { NavLink, Outlet } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { getMyVendor } from '@/api/vendor'
import { getErrorMessage } from '@/api/client'
import { Badge } from '@/components/ui/Badge'
import { Alert } from '@/components/ui/Alert'
import { PageLoader } from '@/components/ui/Spinner'
import { cn } from '@/lib/utils'
import type { Vendor } from '@/types'

const tabClass = ({ isActive }: { isActive: boolean }) =>
  cn(
    'rounded-md px-3 py-2 text-sm font-medium transition-colors',
    isActive ? 'bg-accent-muted text-accent' : 'text-muted hover:bg-surface-muted hover:text-foreground',
  )

function statusVariant(status: Vendor['status']) {
  if (status === 'approved') return 'success'
  if (status === 'pending') return 'warning'
  return 'danger'
}

export function VendorLayout() {
  const vendorQuery = useQuery({
    queryKey: ['my-vendor'],
    queryFn: getMyVendor,
  })

  return (
    <div className="container-app py-8">
      <div className="mb-6 flex items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-semibold text-ink">
            {vendorQuery.data?.store_name ?? 'Vendor dashboard'}
          </h1>
          <p className="mt-1 text-sm text-muted">Manage your products and incoming orders.</p>
        </div>
        {vendorQuery.data ? (
          <Badge variant={statusVariant(vendorQuery.data.status)} className="capitalize">
            {vendorQuery.data.status}
          </Badge>
        ) : null}
      </div>

      {vendorQuery.isError ? (
        <Alert
          className="mb-6"
          message={getErrorMessage(vendorQuery.error, 'Unable to load your vendor profile.')}
          onRetry={() => void vendorQuery.refetch()}
        />
      ) : null}

      <nav className="mb-6 flex gap-2 border-b border-border pb-2">
        <NavLink to="/vendor/products" className={tabClass}>
          Products
        </NavLink>
        <NavLink to="/vendor/orders" className={tabClass}>
          Orders
        </NavLink>
      </nav>

      {vendorQuery.isLoading ? <PageLoader /> : <Outlet />}
    </div>
  )
}
