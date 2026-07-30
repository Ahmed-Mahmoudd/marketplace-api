import { Link, useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ImageOff, Minus, Plus, ShoppingBag, Trash2 } from 'lucide-react'
import { getCart, removeCartItem, updateCartItem, clearCart } from '@/api/cart'
import { getErrorMessage } from '@/api/client'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Alert, EmptyState } from '@/components/ui/Alert'
import { PageLoader } from '@/components/ui/Spinner'
import { formatPrice } from '@/lib/utils'
import type { CartItem } from '@/types'

function CartItemRow({ item }: { item: CartItem }) {
  const queryClient = useQueryClient()
  const image = item.product.images?.find((img) => img.is_primary) ?? item.product.images?.[0]
  const outOfStock = item.product.stock <= 0
  const maxQuantity = Math.min(item.product.stock, 99)

  const updateMutation = useMutation({
    mutationFn: (quantity: number) => updateCartItem(item.id, quantity),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['cart'] })
    },
  })

  const removeMutation = useMutation({
    mutationFn: () => removeCartItem(item.id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['cart'] })
    },
  })

  const isBusy = updateMutation.isPending || removeMutation.isPending

  return (
    <Card className="p-4">
      <div className="flex gap-4">
        <Link
          to={`/products/${item.product.slug}`}
          className="size-20 shrink-0 overflow-hidden rounded-md border border-border bg-surface-muted"
        >
          {image ? (
            <img src={image.url} alt={item.product.name} className="h-full w-full object-cover" />
          ) : (
            <div className="flex h-full w-full items-center justify-center text-muted-foreground">
              <ImageOff className="size-6" />
            </div>
          )}
        </Link>

        <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div className="min-w-0">
            <Link
              to={`/products/${item.product.slug}`}
              className="line-clamp-1 text-sm font-medium text-foreground hover:text-accent"
            >
              {item.product.name}
            </Link>
            {item.product.vendor ? (
              <p className="text-xs text-muted">{item.product.vendor.store_name}</p>
            ) : null}
            <p className="mt-1 text-sm text-muted">{formatPrice(item.unit_price)} each</p>
            {outOfStock ? (
              <p className="mt-1 text-sm text-danger">No longer in stock</p>
            ) : item.quantity > item.product.stock ? (
              <p className="mt-1 text-sm text-danger">Only {item.product.stock} left in stock</p>
            ) : null}
          </div>

          <div className="flex items-center gap-4 sm:flex-col sm:items-end">
            <div className="flex items-center rounded-md border border-border">
              <button
                type="button"
                className="flex size-8 items-center justify-center text-muted hover:text-foreground disabled:opacity-40"
                onClick={() => updateMutation.mutate(item.quantity - 1)}
                disabled={isBusy || item.quantity <= 1}
                aria-label="Decrease quantity"
              >
                <Minus className="size-3.5" />
              </button>
              <span className="w-8 text-center text-sm font-medium">{item.quantity}</span>
              <button
                type="button"
                className="flex size-8 items-center justify-center text-muted hover:text-foreground disabled:opacity-40"
                onClick={() => updateMutation.mutate(item.quantity + 1)}
                disabled={isBusy || item.quantity >= maxQuantity}
                aria-label="Increase quantity"
              >
                <Plus className="size-3.5" />
              </button>
            </div>

            <div className="flex items-center gap-3">
              <p className="text-sm font-semibold text-ink">{formatPrice(item.subtotal)}</p>
              <button
                type="button"
                className="text-muted hover:text-danger disabled:opacity-40"
                onClick={() => removeMutation.mutate()}
                disabled={isBusy}
                aria-label="Remove item"
              >
                <Trash2 className="size-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      {updateMutation.isError ? (
        <p className="mt-2 text-sm text-danger">
          {getErrorMessage(updateMutation.error, 'Unable to update quantity.')}
        </p>
      ) : null}
      {removeMutation.isError ? (
        <p className="mt-2 text-sm text-danger">
          {getErrorMessage(removeMutation.error, 'Unable to remove item.')}
        </p>
      ) : null}
    </Card>
  )
}

export function CartPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const cartQuery = useQuery({
    queryKey: ['cart'],
    queryFn: getCart,
  })

  const clearMutation = useMutation({
    mutationFn: clearCart,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['cart'] })
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
          description="Browse the catalog and add products you'd like to buy."
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
    <div className="container-app py-8">
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-ink">Your cart</h1>
        <button
          type="button"
          className="text-sm text-muted hover:text-danger disabled:opacity-40"
          onClick={() => clearMutation.mutate()}
          disabled={clearMutation.isPending}
        >
          Clear cart
        </button>
      </div>

      {clearMutation.isError ? (
        <Alert
          className="mb-4"
          message={getErrorMessage(clearMutation.error, 'Unable to clear cart.')}
        />
      ) : null}

      <div className="grid gap-8 lg:grid-cols-[1fr_320px]">
        <div className="space-y-3">
          {cart.items.map((item) => (
            <CartItemRow key={item.id} item={item} />
          ))}
        </div>

        <Card className="h-fit p-5">
          <h2 className="text-sm font-semibold text-foreground">Order summary</h2>
          <div className="mt-4 space-y-2 text-sm">
            <div className="flex justify-between text-muted">
              <span>Items ({cart.items_count})</span>
              <span>{formatPrice(cart.total)}</span>
            </div>
            <div className="flex justify-between border-t border-border pt-2 text-base font-semibold text-ink">
              <span>Total</span>
              <span>{formatPrice(cart.total)}</span>
            </div>
          </div>

          {hasUnavailableItems ? (
            <p className="mt-3 text-sm text-danger">
              Resolve unavailable or over-stock items before checking out.
            </p>
          ) : null}

          <Button
            className="mt-4 w-full"
            disabled={hasUnavailableItems}
            onClick={() => navigate('/checkout')}
          >
            <ShoppingBag className="size-4" />
            Proceed to checkout
          </Button>
          <p className="mt-2 text-center text-xs text-muted">Payment: Cash on delivery</p>
        </Card>
      </div>
    </div>
  )
}
