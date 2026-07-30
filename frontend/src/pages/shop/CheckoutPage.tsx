import { Link, useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ChevronLeft, ImageOff, PackageCheck } from 'lucide-react'
import { getCart } from '@/api/cart'
import { checkout } from '@/api/orders'
import { getErrorMessage } from '@/api/client'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Alert, EmptyState } from '@/components/ui/Alert'
import { PageLoader } from '@/components/ui/Spinner'
import { formatPrice } from '@/lib/utils'

export function CheckoutPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const cartQuery = useQuery({
    queryKey: ['cart'],
    queryFn: getCart,
  })

  const checkoutMutation = useMutation({
    mutationFn: checkout,
    onSuccess: (order) => {
      void queryClient.invalidateQueries({ queryKey: ['cart'] })
      void queryClient.invalidateQueries({ queryKey: ['orders'] })
      navigate(`/orders/${order.id}`, { state: { justPlaced: true } })
    },
  })

  if (cartQuery.isLoading) {
    return <PageLoader />
  }

  if (cartQuery.isError) {
    return (
      <div className="container-app py-8">
        <Alert
          title="Unable to load cart"
          message={getErrorMessage(cartQuery.error)}
          onRetry={() => void cartQuery.refetch()}
        />
      </div>
    )
  }

  const cart = cartQuery.data
  if (!cart || cart.items.length === 0) {
    return (
      <div className="container-app py-8">
        <EmptyState
          title="Your cart is empty"
          description="Add products to your cart before checking out."
          action={
            <Link to="/products">
              <Button>Browse products</Button>
            </Link>
          }
        />
      </div>
    )
  }

  const hasUnavailableItems = cart.items.some(
    (item) => item.product.stock <= 0 || item.quantity > item.product.stock,
  )

  return (
    <div className="container-app max-w-2xl py-8">
      <Link
        to="/cart"
        className="mb-6 inline-flex items-center gap-1 text-sm text-muted hover:text-foreground"
      >
        <ChevronLeft className="size-4" />
        Back to cart
      </Link>

      <h1 className="mb-6 text-xl font-semibold text-ink">Checkout</h1>

      <Card className="p-5">
        <h2 className="text-sm font-semibold text-foreground">Items</h2>
        <div className="mt-4 space-y-4">
          {cart.items.map((item) => {
            const image =
              item.product.images?.find((img) => img.is_primary) ?? item.product.images?.[0]

            return (
              <div key={item.id} className="flex items-center gap-3">
                <div className="size-14 shrink-0 overflow-hidden rounded-md border border-border bg-surface-muted">
                  {image ? (
                    <img
                      src={image.url}
                      alt={item.product.name}
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
                    {item.product.name}
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

        <div className="mt-5 space-y-2 border-t border-border pt-4 text-sm">
          <div className="flex justify-between text-muted">
            <span>Subtotal</span>
            <span>{formatPrice(cart.total)}</span>
          </div>
          <div className="flex justify-between text-base font-semibold text-ink">
            <span>Total</span>
            <span>{formatPrice(cart.total)}</span>
          </div>
        </div>

        <div className="mt-5 rounded-md border border-border bg-surface-muted px-4 py-3 text-sm text-foreground">
          Payment method: <span className="font-medium">Cash on delivery</span>
        </div>

        {hasUnavailableItems ? (
          <Alert
            className="mt-4"
            message="One or more items in your cart are unavailable or exceed available stock. Update your cart before placing the order."
          />
        ) : null}

        {checkoutMutation.isError ? (
          <Alert
            className="mt-4"
            message={getErrorMessage(checkoutMutation.error, 'Unable to place order.')}
          />
        ) : null}

        <Button
          className="mt-5 w-full"
          disabled={hasUnavailableItems}
          isLoading={checkoutMutation.isPending}
          onClick={() => checkoutMutation.mutate()}
        >
          <PackageCheck className="size-4" />
          Place order
        </Button>
      </Card>
    </div>
  )
}
