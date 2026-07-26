import { NavLink, Link } from 'react-router-dom'
import { LogOut, Menu, ShoppingBag, Store, User, X } from 'lucide-react'
import { useState } from 'react'
import { useAuth } from '@/context'
import { Button } from '@/components/ui/Button'
import { cn } from '@/lib/utils'

const navLinkClass = ({ isActive }: { isActive: boolean }) =>
  cn(
    'text-sm font-medium transition-colors',
    isActive ? 'text-accent' : 'text-muted hover:text-foreground',
  )

export function Header() {
  const { user, isAuthenticated, logout, hasRole } = useAuth()
  const [mobileOpen, setMobileOpen] = useState(false)

  const handleLogout = async () => {
    await logout()
    setMobileOpen(false)
  }

  return (
    <header className="sticky top-0 z-40 border-b border-border bg-surface/95 backdrop-blur-sm">
      <div className="container-app flex h-16 items-center justify-between gap-4">
        <div className="flex items-center gap-8">
          <Link to="/" className="flex items-center gap-2 text-foreground">
            <Store className="size-5 text-accent" strokeWidth={2.25} />
            <span className="text-base font-semibold tracking-tight">Marketplace</span>
          </Link>

          <nav className="hidden items-center gap-6 md:flex">
            <NavLink to="/" className={navLinkClass} end>
              Shop
            </NavLink>
            <NavLink to="/products" className={navLinkClass}>
              Products
            </NavLink>
            {isAuthenticated ? (
              <>
                <NavLink to="/orders" className={navLinkClass}>
                  Orders
                </NavLink>
                {hasRole('vendor') ? (
                  <NavLink to="/vendor" className={navLinkClass}>
                    Vendor
                  </NavLink>
                ) : null}
                {hasRole('admin') ? (
                  <NavLink to="/admin" className={navLinkClass}>
                    Admin
                  </NavLink>
                ) : null}
              </>
            ) : null}
          </nav>
        </div>

        <div className="hidden items-center gap-3 md:flex">
          {isAuthenticated ? (
            <>
              <NavLink
                to="/cart"
                className="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-muted hover:bg-surface-muted hover:text-foreground"
              >
                <ShoppingBag className="size-4" />
                Cart
              </NavLink>
              <div className="hidden items-center gap-2 border-l border-border pl-3 lg:flex">
                <User className="size-4 text-muted" />
                <span className="max-w-[160px] truncate text-sm text-foreground">
                  {user?.name}
                </span>
              </div>
              <Button variant="ghost" size="sm" onClick={() => void handleLogout()}>
                <LogOut className="size-4" />
                Sign out
              </Button>
            </>
          ) : (
            <>
              <Link to="/login">
                <Button variant="ghost" size="sm">
                  Sign in
                </Button>
              </Link>
              <Link to="/register">
                <Button size="sm">Create account</Button>
              </Link>
            </>
          )}
        </div>

        <button
          type="button"
          className="inline-flex items-center justify-center rounded-md p-2 text-muted hover:bg-surface-muted md:hidden"
          onClick={() => setMobileOpen((open) => !open)}
          aria-label={mobileOpen ? 'Close menu' : 'Open menu'}
        >
          {mobileOpen ? <X className="size-5" /> : <Menu className="size-5" />}
        </button>
      </div>

      {mobileOpen ? (
        <div className="border-t border-border bg-surface md:hidden">
          <nav className="container-app flex flex-col gap-1 py-3">
            <NavLink to="/" className={navLinkClass} end onClick={() => setMobileOpen(false)}>
              Shop
            </NavLink>
            <NavLink to="/products" className={navLinkClass} onClick={() => setMobileOpen(false)}>
              Products
            </NavLink>
            {isAuthenticated ? (
              <>
                <NavLink to="/cart" className={navLinkClass} onClick={() => setMobileOpen(false)}>
                  Cart
                </NavLink>
                <NavLink to="/orders" className={navLinkClass} onClick={() => setMobileOpen(false)}>
                  Orders
                </NavLink>
                {hasRole('vendor') ? (
                  <NavLink to="/vendor" className={navLinkClass} onClick={() => setMobileOpen(false)}>
                    Vendor
                  </NavLink>
                ) : null}
                {hasRole('admin') ? (
                  <NavLink to="/admin" className={navLinkClass} onClick={() => setMobileOpen(false)}>
                    Admin
                  </NavLink>
                ) : null}
                <button
                  type="button"
                  className="mt-2 text-left text-sm font-medium text-muted"
                  onClick={() => void handleLogout()}
                >
                  Sign out
                </button>
              </>
            ) : (
              <>
                <NavLink to="/login" className={navLinkClass} onClick={() => setMobileOpen(false)}>
                  Sign in
                </NavLink>
                <NavLink to="/register" className={navLinkClass} onClick={() => setMobileOpen(false)}>
                  Create account
                </NavLink>
              </>
            )}
          </nav>
        </div>
      ) : null}
    </header>
  )
}
