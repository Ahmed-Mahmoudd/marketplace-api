import { Outlet } from 'react-router-dom'
import { Link } from 'react-router-dom'
import { Store } from 'lucide-react'

export function AuthLayout() {
  return (
    <div className="min-h-screen bg-background">
      <div className="container-app flex min-h-screen flex-col justify-center py-10">
        <div className="mx-auto w-full max-w-md">
          <Link to="/" className="mb-8 inline-flex items-center gap-2 text-foreground">
            <Store className="size-5 text-accent" />
            <span className="font-semibold">Marketplace</span>
          </Link>
          <Outlet />
        </div>
      </div>
    </div>
  )
}
