import { Link, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ChevronRight, PackageSearch } from 'lucide-react'
import { listVendorOrders } from '@/api/vendor'
import { getErrorMessage } from '@/api/client'
import { Card } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Alert, EmptyState } from '@/components/ui/Alert'
import { Pagination } from '@/components/ui/Pagination'
import { PageLoader } from '@/components/ui/Spinner'
import { formatPrice } from '@/lib/utils'
import type { OrderStatus } from '@/types'

function statusVariant(status: OrderStatus) {
  switch (status) {
    case 'pending':
      return 'warning'
    case 'confirmed':
      return 'default'
    case 'processing':
      return 'default'
    case 'shipped':
      return 'default'
    case 'delivered':
      return 'success'
    case 'cancelled':
      return 'muted'
  }
}

export function VendorOrdersPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const page = Number(searchParams.get('page') ?? '1')

  const ordersQuery = useQuery({
    queryKey: ['vendor-orders', { page }],
    queryFn: () => listVendorOrders({ page }),
  })

  function handlePageChange(nextPage: number) {
    const params = new URLSearchParams(searchParams)
    params.set('page', String(nextPage))
    setSearchParams(params)
  }

  return (
    <div className="space-y-4">
      {ordersQuery.isLoading ? <PageLoader /> : null}

      {ordersQuery.isError ? (
        <Alert
          message={getErrorMessage(ordersQuery.error, 'Unable to load orders.')}
          onRetry={() => void ordersQuery.refetch()}
        />
      ) : null}

      {ordersQuery.data && ordersQuery.data.items.length === 0 ? (
        <EmptyState
          title="No orders yet"
          description="Orders containing your products will show up here."
        />
      ) : null}

      {ordersQuery.data && ordersQuery.data.items.length > 0 ? (
        <>
          <div className="space-y-3">
            {ordersQuery.data.items.map((order) => (
              <Link key={order.id} to={`/vendor/orders/${order.id}`}>
                <Card className="flex items-center justify-between gap-4 p-4 transition-shadow hover:shadow-md">
                  <div className="flex items-center gap-4">
                    <div className="flex size-10 items-center justify-center rounded-md bg-surface-muted text-muted">
                      <PackageSearch className="size-5" />
                    </div>
                    <div>
                      <p className="text-sm font-medium text-foreground">{order.order_number}</p>
                      <p className="text-xs text-muted">
                        {order.customer?.name ?? 'Customer'} ·{' '}
                        {new Date(order.created_at).toLocaleDateString(undefined, {
                          year: 'numeric',
                          month: 'short',
                          day: 'numeric',
                        })}
                        {order.items_count ? ` · ${order.items_count} item(s)` : ''}
                      </p>
                    </div>
                  </div>

                  <div className="flex items-center gap-4">
                    <Badge variant={statusVariant(order.status)} className="capitalize">
                      {order.status}
                    </Badge>
                    <p className="w-24 text-right text-sm font-semibold text-ink">
                      {formatPrice(order.vendor_subtotal)}
                    </p>
                    <ChevronRight className="size-4 text-muted" />
                  </div>
                </Card>
              </Link>
            ))}
          </div>

          <Pagination meta={ordersQuery.data.meta} onPageChange={handlePageChange} />
        </>
      ) : null}
    </div>
  )
}
