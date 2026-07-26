import { useState } from 'react'
import { Link, useLocation, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ChevronLeft, ImageOff, PackageCheck } from 'lucide-react'
import { cancelOrder, getOrder } from '@/api/orders'
import { getErrorMessage } from '@/api/client'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Alert } from '@/components/ui/Alert'
import { PageLoader } from '@/components/ui/Spinner'
import { formatPrice } from '@/lib/utils'

export function OrderDetailPage() {
  const { id } = useParams<{ id: string }>()
  const location = useLocation()
  const queryClient = useQueryClient()
  const [confirmingCancel, setConfirmingCancel] = useState(false)

  const justPlaced = Boolean(
    location.state && typeof location.state === 'object' && 'justPlaced' in location.state,
  )

  const orderQuery = useQuery({
    queryKey: ['order', id],
    queryFn: () => getOrder(id as string),
    enabled: Boolean(id),
  })

  const cancelMutation = useMutation({
    mutationFn: () => cancelOrder(id as string),
    onSuccess: () => {
      setConfirmingCancel(false)
      void queryClient.invalidateQueries({ queryKey: ['order', id] })
      void queryClient.invalidateQueries({ queryKey: ['orders'] })
    },
  })

  if (orderQuery.isLoading) {
    return <PageLoader />
  }

  if (orderQuery.isError) {
    return (
      <div className="container-app py-8">
        <Alert
          title="Unable to load order"
          message={getErrorMessage(orderQuery.error)}
          onRetry={() => void orderQuery.refetch()}
        />
      </div>
    )
  }

  const order = orderQuery.data
  if (!order) return null

  const canCancel = order.status === 'pending'

  return (
    <div className="container-app max-w-2xl py-8">
      <Link
        to="/orders"
        className="mb-6 inline-flex items-center gap-1 text-sm text-muted hover:text-foreground"
      >
        <ChevronLeft className="size-4" />
        Back to orders
      </Link>

      {justPlaced ? (
        <Alert
          variant="info"
          className="mb-6"
          message="Your order was placed successfully. Payment is cash on delivery."
        />
      ) : null}

      <div className="mb-6 flex items-start justify-between gap-4">
        <div>
          <h1 className="text-xl font-semibold text-ink">{order.order_number}</h1>
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

      <Card className="p-5">
        <h2 className="text-sm font-semibold text-foreground">Items</h2>
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
                  {item.vendor ? (
                    <p className="text-xs text-muted">{item.vendor.store_name}</p>
                  ) : null}
                  <p className="text-xs text-muted">
                    {formatPrice(item.unit_price)} × {item.quantity}
                  </p>
                </div>
                <p className="text-sm font-semibold text-ink">{formatPrice(item.subtotal)}</p>
              </div>
            )
          })}
        </div>

        <div className="mt-5 space-y-2 border-t border-border pt-4 text-sm">
          <div className="flex justify-between text-muted">
            <span>Subtotal</span>
            <span>{formatPrice(order.subtotal)}</span>
          </div>
          <div className="flex justify-between text-base font-semibold text-ink">
            <span>Total</span>
            <span>{formatPrice(order.total)}</span>
          </div>
        </div>

        <div className="mt-5 flex items-center justify-between rounded-md border border-border bg-surface-muted px-4 py-3 text-sm text-foreground">
          <span>
            Payment: <span className="font-medium">Cash on delivery</span>
          </span>
          <span className="capitalize text-muted">{order.payment_status}</span>
        </div>

        {cancelMutation.isError ? (
          <Alert
            className="mt-4"
            message={getErrorMessage(cancelMutation.error, 'Unable to cancel order.')}
          />
        ) : null}

        {canCancel ? (
          <div className="mt-5 border-t border-border pt-4">
            {confirmingCancel ? (
              <div className="flex items-center justify-between gap-3">
                <p className="text-sm text-foreground">Cancel this order?</p>
                <div className="flex gap-2">
                  <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => setConfirmingCancel(false)}
                    disabled={cancelMutation.isPending}
                  >
                    Keep order
                  </Button>
                  <Button
                    variant="danger"
                    size="sm"
                    isLoading={cancelMutation.isPending}
                    onClick={() => cancelMutation.mutate()}
                  >
                    Confirm cancel
                  </Button>
                </div>
              </div>
            ) : (
              <Button variant="danger" size="sm" onClick={() => setConfirmingCancel(true)}>
                Cancel order
              </Button>
            )}
          </div>
        ) : null}

        {order.status === 'cancelled' ? (
          <div className="mt-5 flex items-center gap-2 border-t border-border pt-4 text-sm text-muted">
            <PackageCheck className="size-4" />
            This order has been cancelled.
          </div>
        ) : null}
      </Card>
    </div>
  )
}
