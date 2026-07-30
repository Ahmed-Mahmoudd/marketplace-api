import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/Button'

export function NotFoundPage() {
  return (
    <div className="container-app flex min-h-[50vh] flex-col items-center justify-center py-16 text-center">
      <p className="text-sm font-medium uppercase tracking-wide text-muted">404</p>
      <h1 className="mt-2 text-2xl font-semibold text-foreground">Page not found</h1>
      <p className="mt-2 max-w-md text-sm text-muted">
        The page you requested does not exist or has moved.
      </p>
      <Link to="/" className="mt-6">
        <Button variant="secondary">Back to home</Button>
      </Link>
    </div>
  )
}
