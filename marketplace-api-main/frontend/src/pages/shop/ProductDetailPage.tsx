import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ChevronLeft, ImageOff, Minus, Plus, ShoppingBag, Star, Trash2, Pencil } from 'lucide-react'
import { getProduct } from '@/api/products'
import { addCartItem } from '@/api/cart'
import { listReviews, createReview, updateReview, deleteReview } from '@/api/reviews'
import { getErrorMessage } from '@/api/client'
import { useAuth } from '@/context'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Alert } from '@/components/ui/Alert'
import { Card } from '@/components/ui/Card'
import { PageLoader } from '@/components/ui/Spinner'
import { formatPrice } from '@/lib/utils'
import type { ProductImage, Review } from '@/types'

// ---------------------------------------------------------------------------
// Star display helper
// ---------------------------------------------------------------------------

function StarRating({
  rating,
  interactive = false,
  onChange,
}: {
  rating: number
  interactive?: boolean
  onChange?: (r: number) => void
}) {
  const [hovered, setHovered] = useState(0)
  const display = interactive ? hovered || rating : rating

  return (
    <div className="flex gap-0.5">
      {[1, 2, 3, 4, 5].map((star) => (
        <button
          key={star}
          type="button"
          disabled={!interactive}
          onClick={() => onChange?.(star)}
          onMouseEnter={() => interactive && setHovered(star)}
          onMouseLeave={() => interactive && setHovered(0)}
          className={interactive ? 'cursor-pointer' : 'cursor-default'}
          aria-label={interactive ? `Rate ${star} star${star > 1 ? 's' : ''}` : undefined}
        >
          <Star
            className={`size-4 ${
              star <= display ? 'fill-amber-400 text-amber-400' : 'fill-none text-muted'
            }`}
          />
        </button>
      ))}
    </div>
  )
}

// ---------------------------------------------------------------------------
// Reviews section
// ---------------------------------------------------------------------------

function ReviewsSection({ productSlug }: { productSlug: string }) {
  const { user, isAuthenticated } = useAuth()
  const queryClient = useQueryClient()

  // Form state
  const [newRating, setNewRating] = useState(5)
  const [newComment, setNewComment] = useState('')
  const [editingId, setEditingId] = useState<number | null>(null)
  const [editRating, setEditRating] = useState(5)
  const [editComment, setEditComment] = useState('')

  const reviewsQuery = useQuery({
    queryKey: ['reviews', productSlug],
    queryFn: () => listReviews(productSlug),
  })

  const createMutation = useMutation({
    mutationFn: () => createReview(productSlug, { rating: newRating, comment: newComment || null }),
    onSuccess: () => {
      setNewRating(5)
      setNewComment('')
      void queryClient.invalidateQueries({ queryKey: ['reviews', productSlug] })
      void queryClient.invalidateQueries({ queryKey: ['product', productSlug] })
    },
  })

  const updateMutation = useMutation({
    mutationFn: (reviewId: number) =>
      updateReview(reviewId, { rating: editRating, comment: editComment || null }),
    onSuccess: () => {
      setEditingId(null)
      void queryClient.invalidateQueries({ queryKey: ['reviews', productSlug] })
      void queryClient.invalidateQueries({ queryKey: ['product', productSlug] })
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (reviewId: number) => deleteReview(reviewId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['reviews', productSlug] })
      void queryClient.invalidateQueries({ queryKey: ['product', productSlug] })
    },
  })

  const reviews: Review[] = reviewsQuery.data?.items ?? []
  const myReview = isAuthenticated
    ? reviews.find((r) => r.user.id === user?.id) ?? null
    : null
  const canSubmit = isAuthenticated && !myReview

  function startEdit(review: Review) {
    setEditingId(review.id)
    setEditRating(review.rating)
    setEditComment(review.comment ?? '')
  }

  return (
    <div className="mt-12 border-t border-border pt-10">
      <h2 className="mb-6 text-lg font-semibold text-ink">
        Customer reviews
        {reviews.length > 0 && (
          <span className="ml-2 text-sm font-normal text-muted">({reviews.length})</span>
        )}
      </h2>

      {/* Submit new review */}
      {canSubmit ? (
        <Card className="mb-8 p-5">
          <h3 className="mb-4 text-sm font-semibold text-foreground">Write a review</h3>

          {createMutation.isError ? (
            <Alert
              className="mb-3"
              message={getErrorMessage(createMutation.error, 'Unable to submit review.')}
            />
          ) : null}

          <div className="space-y-4">
            <div>
              <p className="mb-1.5 text-sm font-medium text-foreground">Your rating</p>
              <StarRating rating={newRating} interactive onChange={setNewRating} />
            </div>

            <div>
              <label
                htmlFor="review-comment"
                className="mb-1.5 block text-sm font-medium text-foreground"
              >
                Comment <span className="font-normal text-muted">(optional)</span>
              </label>
              <textarea
                id="review-comment"
                rows={3}
                value={newComment}
                onChange={(e) => setNewComment(e.target.value)}
                maxLength={2000}
                placeholder="Share your experience…"
                className="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/30 resize-none"
              />
            </div>

            <Button
              onClick={() => createMutation.mutate()}
              isLoading={createMutation.isPending}
              disabled={newRating === 0}
            >
              Submit review
            </Button>
          </div>
        </Card>
      ) : !isAuthenticated ? (
        <p className="mb-6 text-sm text-muted">
          <Link to="/login" className="text-accent hover:underline">
            Sign in
          </Link>{' '}
          to write a review (requires a delivered purchase).
        </p>
      ) : null}

      {/* Review list */}
      {reviewsQuery.isLoading ? (
        <div className="py-8 text-center text-sm text-muted">Loading reviews…</div>
      ) : reviewsQuery.isError ? (
        <Alert
          message={getErrorMessage(reviewsQuery.error, 'Unable to load reviews.')}
          onRetry={() => void reviewsQuery.refetch()}
        />
      ) : reviews.length === 0 ? (
        <p className="py-6 text-center text-sm text-muted">
          No reviews yet. Be the first to review this product!
        </p>
      ) : (
        <div className="space-y-6">
          {reviews.map((review) => {
            const isOwn = review.user.id === user?.id
            const isEditing = editingId === review.id

            return (
              <div key={review.id} className="border-b border-border pb-6 last:border-0">
                {isEditing ? (
                  /* Edit form */
                  <Card className="p-4">
                    <h4 className="mb-3 text-sm font-semibold text-foreground">Edit review</h4>

                    {updateMutation.isError ? (
                      <Alert
                        className="mb-3"
                        message={getErrorMessage(updateMutation.error, 'Unable to update review.')}
                      />
                    ) : null}

                    <div className="space-y-3">
                      <StarRating rating={editRating} interactive onChange={setEditRating} />
                      <textarea
                        rows={3}
                        value={editComment}
                        onChange={(e) => setEditComment(e.target.value)}
                        maxLength={2000}
                        className="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/30 resize-none"
                      />
                      <div className="flex gap-2">
                        <Button
                          size="sm"
                          onClick={() => updateMutation.mutate(review.id)}
                          isLoading={updateMutation.isPending}
                        >
                          Save
                        </Button>
                        <Button
                          size="sm"
                          variant="secondary"
                          onClick={() => setEditingId(null)}
                          disabled={updateMutation.isPending}
                        >
                          Cancel
                        </Button>
                      </div>
                    </div>
                  </Card>
                ) : (
                  /* Review display */
                  <div>
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <p className="text-sm font-medium text-foreground">{review.user.name}</p>
                        <div className="mt-1 flex items-center gap-2">
                          <StarRating rating={review.rating} />
                          <span className="text-xs text-muted">
                            {new Date(review.created_at).toLocaleDateString(undefined, {
                              year: 'numeric',
                              month: 'short',
                              day: 'numeric',
                            })}
                          </span>
                        </div>
                      </div>
                      {isOwn && (
                        <div className="flex shrink-0 gap-1">
                          <button
                            type="button"
                            onClick={() => startEdit(review)}
                            className="rounded p-1.5 text-muted hover:bg-surface-muted hover:text-foreground"
                            aria-label="Edit review"
                          >
                            <Pencil className="size-3.5" />
                          </button>
                          <button
                            type="button"
                            onClick={() => deleteMutation.mutate(review.id)}
                            disabled={deleteMutation.isPending}
                            className="rounded p-1.5 text-muted hover:bg-red-50 hover:text-danger disabled:opacity-40"
                            aria-label="Delete review"
                          >
                            <Trash2 className="size-3.5" />
                          </button>
                        </div>
                      )}
                    </div>
                    {review.comment ? (
                      <p className="mt-3 text-sm leading-relaxed text-foreground">
                        {review.comment}
                      </p>
                    ) : null}
                  </div>
                )}
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}

// ---------------------------------------------------------------------------
// Main page
// ---------------------------------------------------------------------------

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
        {/* Images */}
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

        {/* Info */}
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

          {/* Rating summary */}
          {product.reviews_count != null && product.reviews_count > 0 ? (
            <div className="flex items-center gap-2">
              <StarRating rating={Math.round(product.average_rating ?? 0)} />
              <span className="text-sm text-muted">
                {Number(product.average_rating).toFixed(1)} ({product.reviews_count}{' '}
                {product.reviews_count === 1 ? 'review' : 'reviews'})
              </span>
            </div>
          ) : null}

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

      {/* Reviews */}
      <ReviewsSection productSlug={slug!} />
    </div>
  )
}
