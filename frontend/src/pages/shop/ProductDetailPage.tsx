import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ChevronLeft, ImageOff, Minus, Plus, ShoppingBag } from 'lucide-react'
import { getProduct } from '@/api/products'
import { addCartItem } from '@/api/cart'
import { getErrorMessage } from '@/api/client'
import { useAuth } from '@/context'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Alert } from '@/components/ui/Alert'
import { PageLoader } from '@/components/ui/Spinner'
import { formatPrice } from '@/lib/utils'
import type { ProductImage } from '@/types'

export function ProductDetailPage() {
  const { slug } = useParams<{ slug: string }>()
  const navigate = useNavigate()
  const { isAuthenticated } = useAuth()
  const queryClient = useQueryClient()

  const [quantity, setQuantity] = useState(1)
  const [activeImage, setActiveImage] = useState<ProductImage | null>(null)
  const [addedMessage, setAddedMessage] = useState<string | null>(null)

  const productQuery = useQuery({
    queryKey: ['product', slug],
    queryFn: () => getProduct(slug as string),
    enabled: Boolean(slug),
  })

  const addToCartMutation = useMutation({
    mutationFn: (payload: { productId: number; quantity: number }) =>
      addCartItem(payload.productId, payload.quantity),
    onSuccess: () => {
      setAddedMessage('Added to cart.')
      void queryClient.invalidateQueries({ queryKey: ['cart'] })
    },
  })

  if (productQuery.isLoading) {
    return <PageLoader />
  }

  if (productQuery.isError) {
    return (
      <div className="container-app py-8">
        <Alert
          title="Product not found"
          message={getErrorMessage(productQuery.error, 'This product is unavailable.')}
        />
        <Link to="/products" className="mt-4 inline-block">
          <Button variant="secondary">Back to products</Button>
        </Link>
      </div>
    )
  }

  const product = productQuery.data
  if (!product) return null

  const images = product.images ?? []
  const displayedImage = activeImage ?? images.find((image) => image.is_primary) ?? images[0]
  const outOfStock = product.stock <= 0
  const maxQuantity = Math.min(product.stock, 99)

  function handleAddToCart() {
    if (!isAuthenticated) {
      navigate('/login', { state: { from: `/products/${slug}` } })
      return
    }
    setAddedMessage(null)
    addToCartMutation.mutate({ productId: product!.id, quantity })
  }

  return (
    <div className="container-app py-8">
      <Link to="/products" className="mb-6 inline-flex items-center gap-1 text-sm text-muted hover:text-foreground">
        <ChevronLeft className="size-4" />
        Back to products
      </Link>

      <div className="grid gap-10 lg:grid-cols-2">
        <div className="space-y-3">
          <div className="aspect-square w-full overflow-hidden rounded-lg border border-border bg-surface-muted">
            {displayedImage ? (
              <img
                src={displayedImage.url}
                alt={product.name}
                className="h-full w-full object-cover"
              />
            ) : (
              <div className="flex h-full w-full items-center justify-center text-muted-foreground">
                <ImageOff className="size-10" />
              </div>
            )}
          </div>
          {images.length > 1 ? (
            <div className="flex gap-2">
              {images.map((image) => (
                <button
                  key={image.id}
                  type="button"
                  onClick={() => setActiveImage(image)}
                  className={`size-16 overflow-hidden rounded-md border ${
                    displayedImage?.id === image.id ? 'border-accent' : 'border-border'
                  }`}
                >
                  <img src={image.url} alt="" className="h-full w-full object-cover" />
                </button>
              ))}
            </div>
          ) : null}
        </div>

        <div className="space-y-5">
          <div>
            {product.category ? (
              <p className="text-xs font-medium uppercase tracking-wide text-accent">
                {product.category.name}
              </p>
            ) : null}
            <h1 className="mt-1 text-2xl font-semibold text-ink">{product.name}</h1>
            {product.vendor ? (
              <p className="mt-1 text-sm text-muted">Sold by {product.vendor.store_name}</p>
            ) : null}
          </div>

          <p className="text-3xl font-semibold text-foreground">{formatPrice(product.price)}</p>

          <div>
            {outOfStock ? (
              <Badge variant="danger">Out of stock</Badge>
            ) : product.stock <= 5 ? (
              <Badge variant="warning">Only {product.stock} left</Badge>
            ) : (
              <Badge variant="success">In stock</Badge>
            )}
          </div>

          {product.description ? (
            <p className="whitespace-pre-line text-sm leading-relaxed text-muted">
              {product.description}
            </p>
          ) : null}

          {addedMessage ? (
            <Alert variant="info" message={addedMessage} />
          ) : null}

          {addToCartMutation.isError ? (
            <Alert message={getErrorMessage(addToCartMutation.error, 'Unable to add to cart.')} />
          ) : null}

          {!outOfStock ? (
            <div className="flex items-center gap-4">
              <div className="flex items-center rounded-md border border-border">
                <button
                  type="button"
                  className="flex size-10 items-center justify-center text-muted hover:text-foreground disabled:opacity-40"
                  onClick={() => setQuantity((q) => Math.max(1, q - 1))}
                  disabled={quantity <= 1}
                  aria-label="Decrease quantity"
                >
                  <Minus className="size-4" />
                </button>
                <span className="w-10 text-center text-sm font-medium">{quantity}</span>
                <button
                  type="button"
                  className="flex size-10 items-center justify-center text-muted hover:text-foreground disabled:opacity-40"
                  onClick={() => setQuantity((q) => Math.min(maxQuantity, q + 1))}
                  disabled={quantity >= maxQuantity}
                  aria-label="Increase quantity"
                >
                  <Plus className="size-4" />
                </button>
              </div>

              <Button
                onClick={handleAddToCart}
                isLoading={addToCartMutation.isPending}
                className="flex-1"
              >
                <ShoppingBag className="size-4" />
                Add to cart
              </Button>
            </div>
          ) : (
            <Button disabled className="w-full">
              Out of stock
            </Button>
          )}
        </div>
      </div>
    </div>
  )
}
