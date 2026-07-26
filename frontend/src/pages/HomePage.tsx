import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowRight } from 'lucide-react'
import { listProducts } from '@/api/products'
import { listCategories } from '@/api/categories'
import { ProductCard } from '@/components/shop/ProductCard'
import { Button } from '@/components/ui/Button'
import { Spinner } from '@/components/ui/Spinner'

export function HomePage() {
  const featuredQuery = useQuery({
    queryKey: ['products', 'featured'],
    queryFn: () => listProducts({ sort: 'latest', page: 1 }),
  })

  const categoriesQuery = useQuery({
    queryKey: ['categories', 'home'],
    queryFn: () => listCategories({ sort: 'name', page: 1 }),
  })

  const featured = featuredQuery.data?.items.slice(0, 8) ?? []
  const categories = categoriesQuery.data?.items.slice(0, 8) ?? []

  return (
    <div>
      <section className="border-b border-border bg-surface">
        <div className="container-app flex flex-col gap-5 py-12 lg:py-16">
          <p className="text-sm font-medium uppercase tracking-wide text-accent">
            Multi-vendor marketplace
          </p>
          <h1 className="max-w-xl text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            Shop products from trusted vendors in one place.
          </h1>
          <p className="max-w-xl text-base leading-relaxed text-muted">
            Browse the catalog, manage your cart, and place cash-on-delivery orders — all fulfilled
            by independent, approved vendors.
          </p>
          <div className="flex flex-wrap gap-3">
            <Link to="/products">
              <Button>Browse products</Button>
            </Link>
            <Link to="/vendor/apply">
              <Button variant="secondary">Sell on Marketplace</Button>
            </Link>
          </div>
        </div>
      </section>

      {categories.length > 0 ? (
        <section className="border-b border-border">
          <div className="container-app py-8">
            <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">
              Shop by category
            </h2>
            <div className="flex flex-wrap gap-2">
              {categories.map((category) => (
                <Link
                  key={category.id}
                  to={`/products?category=${category.slug}`}
                  className="rounded-full border border-border bg-surface px-4 py-1.5 text-sm text-foreground transition-colors hover:border-accent hover:text-accent"
                >
                  {category.name}
                </Link>
              ))}
            </div>
          </div>
        </section>
      ) : null}

      <section className="container-app py-10">
        <div className="mb-6 flex items-center justify-between">
          <h2 className="text-xl font-semibold text-ink">New arrivals</h2>
          <Link
            to="/products"
            className="inline-flex items-center gap-1 text-sm font-medium text-accent hover:underline"
          >
            View all products
            <ArrowRight className="size-4" />
          </Link>
        </div>

        {featuredQuery.isLoading ? (
          <div className="flex justify-center py-12">
            <Spinner label="Loading products…" />
          </div>
        ) : null}

        {featured.length > 0 ? (
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
            {featured.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        ) : null}

        {!featuredQuery.isLoading && featured.length === 0 ? (
          <p className="text-sm text-muted">No products available yet. Check back soon.</p>
        ) : null}
      </section>
    </div>
  )
}
