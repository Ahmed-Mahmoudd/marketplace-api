import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/Button'
import { EmptyState } from '@/components/ui/Alert'

export function ProductsPage() {
  return (
    <div className="container-app py-8">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-foreground">Products</h1>
        <p className="mt-1 text-sm text-muted">
          Product listing will be connected to the API in Phase 2.
        </p>
      </div>
      <EmptyState
        title="Catalog coming in Phase 2"
        description="The API layer and layout are ready. Next step: fetch real products from GET /products."
        action={
          <Link to="/">
            <Button variant="secondary">Back to home</Button>
          </Link>
        }
      />
    </div>
  )
}
