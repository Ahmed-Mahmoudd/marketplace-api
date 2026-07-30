import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ImageOff, Pencil, Plus, Search, Trash2 } from 'lucide-react'
import {
  deleteVendorProduct,
  listVendorProducts,
  type ListVendorProductsParams,
  type VendorProductStatus,
} from '@/api/vendor'
import { getErrorMessage } from '@/api/client'
import { Card } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Select } from '@/components/ui/Select'
import { Alert, EmptyState } from '@/components/ui/Alert'
import { Pagination } from '@/components/ui/Pagination'
import { PageLoader } from '@/components/ui/Spinner'
import { formatPrice } from '@/lib/utils'
import type { Product } from '@/types'

const SORT_OPTIONS: { value: NonNullable<ListVendorProductsParams['sort']>; label: string }[] = [
  { value: 'latest', label: 'Newest' },
  { value: 'name', label: 'Name (A–Z)' },
  { value: 'price', label: 'Price (low to high)' },
  { value: '-price', label: 'Price (high to low)' },
]

function statusVariant(status: Product['status']) {
  return status === 'active' ? 'success' : 'muted'
}

export function VendorProductsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const queryClient = useQueryClient()
  const [confirmingDeleteId, setConfirmingDeleteId] = useState<number | null>(null)

  const q = searchParams.get('q') ?? ''
  const status = (searchParams.get('status') as VendorProductStatus | null) ?? undefined
  const sort =
    (searchParams.get('sort') as ListVendorProductsParams['sort']) ?? 'latest'
  const page = Number(searchParams.get('page') ?? '1')

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

  const productsQuery = useQuery({
    queryKey: ['vendor-products', { q, status, sort, page }],
    queryFn: () => listVendorProducts({ q: q || undefined, status, sort, page }),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteVendorProduct(id),
    onSuccess: () => {
      setConfirmingDeleteId(null)
      void queryClient.invalidateQueries({ queryKey: ['vendor-products'] })
    },
  })

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <form
          onSubmit={(event) => {
            event.preventDefault()
            updateParams({ q: searchInput || undefined })
          }}
          className="relative w-full max-w-xs"
        >
          <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
          <Input
            aria-label="Search your products"
            placeholder="Search products…"
            className="pl-9"
            value={searchInput}
            onChange={(event) => setSearchInput(event.target.value)}
          />
        </form>

        <div className="flex items-center gap-2">
          <Select
            aria-label="Filter by status"
            value={status ?? ''}
            onChange={(event) => updateParams({ status: event.target.value || undefined })}
            className="w-auto"
          >
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </Select>

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

          <Link to="/vendor/products/new">
            <Button size="sm">
              <Plus className="size-4" />
              New product
            </Button>
          </Link>
        </div>
      </div>

      {productsQuery.isLoading ? <PageLoader /> : null}

      {productsQuery.isError ? (
        <Alert
          message={getErrorMessage(productsQuery.error, 'Unable to load your products.')}
          onRetry={() => void productsQuery.refetch()}
        />
      ) : null}

      {productsQuery.data && productsQuery.data.items.length === 0 ? (
        <EmptyState
          title="No products yet"
          description="Create your first product listing to start selling."
          action={
            <Link to="/vendor/products/new">
              <Button>Create product</Button>
            </Link>
          }
        />
      ) : null}

      {productsQuery.data && productsQuery.data.items.length > 0 ? (
        <>
          <div className="space-y-2">
            {productsQuery.data.items.map((product) => {
              const image =
                product.images?.find((img) => img.is_primary) ?? product.images?.[0]
              const isConfirming = confirmingDeleteId === product.id

              return (
                <Card key={product.id} className="flex items-center gap-4 p-4">
                  <div className="size-12 shrink-0 overflow-hidden rounded-md border border-border bg-surface-muted">
                    {image ? (
                      <img
                        src={image.url}
                        alt={product.name}
                        className="h-full w-full object-cover"
                      />
                    ) : (
                      <div className="flex h-full w-full items-center justify-center text-muted-foreground">
                        <ImageOff className="size-4" />
                      </div>
                    )}
                  </div>

                  <div className="min-w-0 flex-1">
                    <p className="line-clamp-1 text-sm font-medium text-foreground">
                      {product.name}
                    </p>
                    <p className="text-xs text-muted">
                      {product.category?.name ?? 'Uncategorized'} · Stock: {product.stock}
                    </p>
                  </div>

                  <p className="w-24 shrink-0 text-right text-sm font-semibold text-ink">
                    {formatPrice(product.price)}
                  </p>

                  <Badge variant={statusVariant(product.status)} className="shrink-0 capitalize">
                    {product.status}
                  </Badge>

                  <div className="flex shrink-0 items-center gap-1">
                    <Link
                      to={`/vendor/products/${product.id}/edit`}
                      className="rounded-md p-2 text-muted hover:bg-surface-muted hover:text-foreground"
                      aria-label="Edit product"
                    >
                      <Pencil className="size-4" />
                    </Link>

                    {isConfirming ? (
                      <Button
                        variant="danger"
                        size="sm"
                        isLoading={deleteMutation.isPending}
                        onClick={() => deleteMutation.mutate(product.id)}
                      >
                        Confirm
                      </Button>
                    ) : (
                      <button
                        type="button"
                        className="rounded-md p-2 text-muted hover:bg-danger-muted hover:text-danger"
                        onClick={() => setConfirmingDeleteId(product.id)}
                        aria-label="Delete product"
                      >
                        <Trash2 className="size-4" />
                      </button>
                    )}
                  </div>
                </Card>
              )
            })}
          </div>

          {deleteMutation.isError ? (
            <Alert message={getErrorMessage(deleteMutation.error, 'Unable to delete product.')} />
          ) : null}

          <Pagination
            meta={productsQuery.data.meta}
            onPageChange={(nextPage) => updateParams({ page: String(nextPage) })}
          />
        </>
      ) : null}
    </div>
  )
}
