import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { listProducts, type ProductSort } from '@/api/products'
import { listCategories } from '@/api/categories'
import { getErrorMessage } from '@/api/client'
import { ProductCard } from '@/components/shop/ProductCard'
import { Alert, EmptyState } from '@/components/ui/Alert'
import { Input } from '@/components/ui/Input'
import { Select } from '@/components/ui/Select'
import { Pagination } from '@/components/ui/Pagination'
import { PageLoader } from '@/components/ui/Spinner'

const SORT_OPTIONS: { value: ProductSort; label: string }[] = [
  { value: 'latest', label: 'Newest' },
  { value: 'name', label: 'Name (A–Z)' },
  { value: 'price', label: 'Price (low to high)' },
  { value: '-price', label: 'Price (high to low)' },
]

export function ProductsPage() {
  const [searchParams, setSearchParams] = useSearchParams()

  const category = searchParams.get('category') ?? ''
  const sort = (searchParams.get('sort') as ProductSort | null) ?? 'latest'
  const page = Number(searchParams.get('page') ?? '1')
  const q = searchParams.get('q') ?? ''

  const [searchInput, setSearchInput] = useState(q)

  useEffect(() => {
    setSearchInput(q)
  }, [q])

  function updateParams(next: Record<string, string | undefined>) {
    const params = new URLSearchParams(searchParams)
    Object.entries(next).forEach(([key, value]) => {
      if (value) {
        params.set(key, value)
      } else {
        params.delete(key)
      }
    })
    if (!('page' in next)) {
      params.delete('page')
    }
    setSearchParams(params)
  }

  const categoriesQuery = useQuery({
    queryKey: ['categories', 'filter-list'],
    queryFn: () => listCategories({ sort: 'name', page: 1 }),
  })

  const productsQuery = useQuery({
    queryKey: ['products', { q, category, sort, page }],
    queryFn: () =>
      listProducts({
        q: q || undefined,
        category: category || undefined,
        sort,
        page,
      }),
  })

  return (
    <div className="container-app py-8">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-foreground">Products</h1>
        <p className="mt-1 text-sm text-muted">Browse the full catalog from approved vendors.</p>
      </div>

      <div className="grid gap-6 lg:grid-cols-[240px_1fr]">
        <aside className="space-y-6">
          <form
            onSubmit={(event) => {
              event.preventDefault()
              updateParams({ q: searchInput || undefined })
            }}
            className="space-y-2"
          >
            <label htmlFor="search" className="block text-sm font-medium text-foreground">
              Search
            </label>
            <div className="relative">
              <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
              <Input
                id="search"
                name="search"
                placeholder="Search products…"
                className="pl-9"
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
              />
            </div>
          </form>

          <div className="space-y-2">
            <p className="text-sm font-medium text-foreground">Category</p>
            <div className="space-y-1">
              <button
                type="button"
                onClick={() => updateParams({ category: undefined })}
                className={`block w-full rounded-md px-2 py-1.5 text-left text-sm ${
                  category === ''
                    ? 'bg-accent-muted text-accent'
                    : 'text-muted hover:bg-surface-muted hover:text-foreground'
                }`}
              >
                All categories
              </button>
              {categoriesQuery.data?.items.map((cat) => (
                <button
                  key={cat.id}
                  type="button"
                  onClick={() => updateParams({ category: cat.slug })}
                  className={`block w-full rounded-md px-2 py-1.5 text-left text-sm ${
                    category === cat.slug
                      ? 'bg-accent-muted text-accent'
                      : 'text-muted hover:bg-surface-muted hover:text-foreground'
                  }`}
                >
                  {cat.name}
                </button>
              ))}
            </div>
          </div>
        </aside>

        <div className="space-y-4">
          <div className="flex items-center justify-between gap-4">
            <p className="text-sm text-muted">
              {productsQuery.data ? `${productsQuery.data.meta.total} products` : ' '}
            </p>
            <Select
              aria-label="Sort by"
              value={sort}
              onChange={(event) => updateParams({ sort: event.target.value })}
              className="w-auto"
            >
              {SORT_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </Select>
          </div>

          {productsQuery.isLoading ? <PageLoader /> : null}

          {productsQuery.isError ? (
            <Alert
              message={getErrorMessage(productsQuery.error, 'Unable to load products.')}
              onRetry={() => void productsQuery.refetch()}
            />
          ) : null}

          {productsQuery.data && productsQuery.data.items.length === 0 ? (
            <EmptyState
              title="No products found"
              description="Try adjusting your search or filters."
            />
          ) : null}

          {productsQuery.data && productsQuery.data.items.length > 0 ? (
            <>
              <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                {productsQuery.data.items.map((product) => (
                  <ProductCard key={product.id} product={product} />
                ))}
              </div>
              <Pagination
                meta={productsQuery.data.meta}
                onPageChange={(nextPage) => updateParams({ page: String(nextPage) })}
              />
            </>
          ) : null}
        </div>
      </div>
    </div>
  )
}
