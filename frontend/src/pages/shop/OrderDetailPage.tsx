import { useState } from 'react'
import { Link, useLocation, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CheckCircle, ChevronLeft, Circle, ImageOff, PackageCheck } from 'lucide-react'
import { cancelOrder, getOrder } from '@/api/orders'
import { getErrorMessage } from '@/api/client'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Alert } from '@/components/ui/Alert'
import { PageLoader } from '@/components/ui/Spinner'
import { formatPrice } from '@/lib/utils'
import type { OrderStatus } from '@/types'

// ---------------------------------------------------------------------------
// Status timeline
// ---------------------------------------------------------------------------

const STATUS_STEPS: { status: OrderStatus; label: string }[] = [
  { status: 'pending', label: 'Order placed' },
  { status: 'confirmed', label: 'Confirmed' },
  { status: 'processing', label: 'Processing' },
  { status: 'shipped', label: 'Shipped' },
  { status: 'delivered', label: 'Delivered' },
]

const STATUS_ORDER: Record<OrderStatus, number> = {
  pending: 0,
  confirmed: 1,
  processing: 2,
  shipped: 3,
  delivered: 4,
  cancelled: -1,
}

function statusVariant(status: OrderStatus) {
  switch (status) {
    case 'pending':    return 'warning'
    case 'confirmed':  return 'default'
    case 'processing': return 'default'
    case 'shipped':    return 'default'
    case 'delivered':  return 'success'
    case 'cancelled':  return 'muted'
  }
}

function OrderStatusTimeline({ status }: { status: OrderStatus }) {
  const isCancelled = status === 'cancelled'
  const currentIndex = STATUS_ORDER[status]

  if (isCancelled) {
    return (
      <div className="flex items-center gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-danger">
        <PackageCheck className="size-4 shrink-0" />
        This order has been cancelled.
      </div>
    )
  }

  return (
    <div className="py-2">
      <div className="flex items-center justify-between gap-1">
        {STATUS_STEPS.map((step, index) => {
          const isCompleted = currentIndex >= index
          const isCurrent   = currentIndex === index

          return (
            <div key={step.status} className="flex flex-1 flex-col items-center gap-1">
              {/* connector line before (except first) */}
              <div className="flex w-full items-center">
                {index > 0 && (
                  <div
                    className={`h-0.5 flex-1 ${isCompleted ? 'bg-accent' : 'bg-border'}`}
                  />
                )}
                <div
                  className={`flex size-6 shrink-0 items-center justify-center rounded-full border-2 transition-colors ${
                    isCompleted
                      ? 'border-accent bg-accent text-white'
                      : 'border-border bg-surface text-muted'
                  }`}
                >
                  {isCompleted ? (
                    <CheckCircle className="size-3" />
                  ) : (
                    <Circle className="size-3" />
                  )}
                </div>
                {index < STATUS_STEPS.length - 1 && (
                  <div
                    className={`h-0.5 flex-1 ${
                      currentIndex > index ? 'bg-accent' : 'bg-border'
                    }`}
                  />
                )}
              </div>
              <p
                className={`text-center text-[10px] leading-tight ${
                  isCurrent ? 'font-semibold text-accent' : isCompleted ? 'text-foreground' : 'text-muted'
                }`}
              >
                {step.label}
              </p>
            </div>
          )
        })}
      </div>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Main page
// ---------------------------------------------------------------------------

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

  const canCancel = order.status === 'pending' || order.status === 'confirmed'

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
        <Badge variant={statusVariant(order.status)} className="capitalize">
          {order.status}
        </Badge>
      </div>

      {/* Order status timeline */}
      <Card className="mb-6 p-5">
        <h2 className="mb-4 text-sm font-semibold text-foreground">Order progress</h2>
        <OrderStatusTimeline status={order.status} />
      </Card>

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
      </Card>
    </div>
  )
}
