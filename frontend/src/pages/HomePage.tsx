import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/Button'
import { Card, CardBody } from '@/components/ui/Card'

export function HomePage() {
  return (
    <section className="border-b border-border bg-surface">
      <div className="container-app grid gap-10 py-12 lg:grid-cols-[1.2fr_0.8fr] lg:items-center lg:py-16">
        <div className="space-y-5">
          <p className="text-sm font-medium uppercase tracking-wide text-accent">
            Multi-vendor marketplace
          </p>
          <h1 className="max-w-xl text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            Shop products from trusted vendors in one place.
          </h1>
          <p className="max-w-xl text-base leading-relaxed text-muted">
            Browse the catalog, manage your cart, and place cash-on-delivery orders through a
            connected Laravel API.
          </p>
          <div className="flex flex-wrap gap-3">
            <Link to="/products">
              <Button>Browse products</Button>
            </Link>
            <Link to="/register">
              <Button variant="secondary">Create account</Button>
            </Link>
          </div>
        </div>

        <Card>
          <CardBody className="space-y-4">
            <h2 className="text-sm font-semibold uppercase tracking-wide text-muted">
              Phase 1 foundation
            </h2>
            <ul className="space-y-2 text-sm text-foreground">
              <li>React + TypeScript + Vite</li>
              <li>Sanctum token authentication</li>
              <li>Role-aware routing shell</li>
              <li>API client wired to Laravel</li>
            </ul>
            <p className="text-sm text-muted">
              Storefront, cart, checkout, vendor, and admin flows will be built in the next phases.
            </p>
          </CardBody>
        </Card>
      </div>
    </section>
  )
}
