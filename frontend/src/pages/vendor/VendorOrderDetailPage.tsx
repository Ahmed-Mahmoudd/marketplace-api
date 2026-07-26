import { Link, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ChevronLeft, ImageOff } from 'lucide-react'
import { getVendorOrder } from '@/api/vendor'
import { getErrorMessage } from '@/api/client'
import { Card } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Alert } from '@/components/ui/Alert'
import { PageLoader } from '@/components/ui/Spinner'
import { formatPrice } from '@/lib/utils'

export function VendorOrderDetailPage() {
  const { id } = useParams<{ id: string }>()

  const orderQuery = useQuery({
    queryKey: ['vendor-order', id],
    queryFn: () => getVendorOrder(id as string),
    enabled: Boolean(id),
  })

  if (orderQuery.isLoading) {
    return <PageLoader />
  }

  if (orderQuery.isError) {
    return (
      <div className="space-y-4">
        <Alert
          title="Unable to load order"
          message={getErrorMessage(orderQuery.error)}
          onRetry={() => void orderQuery.refetch()}
        />
        <Link
          to="/vendor/orders"
          className="inline-flex items-center gap-1 text-sm text-muted hover:text-foreground"
        >
          <ChevronLeft className="size-4" />
          Back to orders
        </Link>
      </div>
    )
  }

  const order = orderQuery.data
  if (!order) return null

  return (
    <div className="max-w-2xl space-y-6">
      <Link
        to="/vendor/orders"
        className="inline-flex items-center gap-1 text-sm text-muted hover:text-foreground"
      >
        <ChevronLeft className="size-4" />
        Back to orders
      </Link>

      <div className="flex items-start justify-between gap-4">
        <div>
          <h2 className="text-xl font-semibold text-ink">{order.order_number}</h2>
          <p className="mt-1 text-sm text-muted">
            Placed{' '}
            {new Date(order.created_at).toLocaleDateString(undefined, {
              year: 'numeric',
              month: 'long',
              day: 'numeric',
            })}
          </p>
        </div>
        <Badge variant={order.status === 'pending' ? 'success' : 'muted'} className="capitalize">
          {order.status}
        </Badge>
      </div>

      {order.customer ? (
        <Card className="p-5">
          <h3 className="text-sm font-semibold text-foreground">Customer</h3>
          <p className="mt-2 text-sm text-foreground">{order.customer.name}</p>
          <p className="text-sm text-muted">{order.customer.email}</p>
        </Card>
      ) : null}

      <Card className="p-5">
        <h3 className="text-sm font-semibold text-foreground">Your items in this order</h3>
        <div className="mt-4 space-y-4">
          {order.items?.map((item) => {
            const image =
              item.product?.images?.find((img) => img.is_primary) ?? item.product?.images?.[0]

            return (
              <div key={item.id} className="flex items-center gap-3">
                <div className="size-14 shrink-0 overflow-hidden rounded-md border border-border bg-surface-muted">
                  {image ? (
                    <img
                      src={image.url}
                      alt={item.product_name}
                      className="h-full w-full object-cover"
                    />
                  ) : (
                    <div className="flex h-full w-full items-center justify-center text-muted-foreground">
                      <ImageOff className="size-5" />
                    </div>
                  )}
                </div>
                <div className="min-w-0 flex-1">
                  <p className="line-clamp-1 text-sm font-medium text-foreground">
                    {item.product_name}
                  </p>
                  <p className="text-xs text-muted">
                    {formatPrice(item.unit_price)} × {item.quantity}
                  </p>
                </div>
                <p className="text-sm font-semibold text-ink">{formatPrice(item.subtotal)}</p>
              </div>
            )
          })}
        </div>

        <div className="mt-5 flex justify-between border-t border-border pt-4 text-base font-semibold text-ink">
          <span>Your subtotal</span>
          <span>{formatPrice(order.vendor_subtotal)}</span>
        </div>

        <div className="mt-5 flex items-center justify-between rounded-md border border-border bg-surface-muted px-4 py-3 text-sm text-foreground">
          <span>
            Payment: <span className="font-medium">Cash on delivery</span>
          </span>
          <span className="capitalize text-muted">{order.payment_status}</span>
        </div>
      </Card>
    </div>
  )
}
