import { Link } from 'react-router-dom'
import { ImageOff } from 'lucide-react'
import { Card } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { formatPrice } from '@/lib/utils'
import type { PublicProduct } from '@/types'

function primaryImage(product: PublicProduct) {
  if (!product.images || product.images.length === 0) return null
  return product.images.find((image) => image.is_primary) ?? product.images[0]
}

export function ProductCard({ product }: { product: PublicProduct }) {
  const image = primaryImage(product)
  const outOfStock = product.stock <= 0

  return (
    <Link to={`/products/${product.slug}`} className="group block">
      <Card className="h-full overflow-hidden transition-shadow group-hover:shadow-md">
        <div className="relative aspect-square w-full overflow-hidden border-b border-border bg-surface-muted">
          {image ? (
            <img
              src={image.url}
              alt={product.name}
              className="h-full w-full object-cover transition-transform group-hover:scale-[1.03]"
              loading="lazy"
            />
          ) : (
            <div className="flex h-full w-full items-center justify-center text-muted-foreground">
              <ImageOff className="size-8" />
            </div>
          )}
          {outOfStock ? (
            <Badge variant="danger" className="absolute right-2 top-2">
              Out of stock
            </Badge>
          ) : null}
        </div>
        <div className="space-y-1 p-4">
          {product.category ? (
            <p className="text-xs font-medium uppercase tracking-wide text-muted">
              {product.category.name}
            </p>
          ) : null}
          <h3 className="line-clamp-1 text-sm font-medium text-foreground">{product.name}</h3>
          {product.vendor ? (
            <p className="text-xs text-muted">{product.vendor.store_name}</p>
          ) : null}
          <p className="pt-1 text-base font-semibold text-ink">{formatPrice(product.price)}</p>
        </div>
      </Card>
    </Link>
  )
}
