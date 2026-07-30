import type { ReactNode } from 'react'
import { AlertCircle } from 'lucide-react'
import { Button } from './Button'
import { cn } from '@/lib/utils'

interface AlertProps {
  title?: string
  message: string
  variant?: 'error' | 'info'
  onRetry?: () => void
  className?: string
}

export function Alert({ title, message, variant = 'error', onRetry, className }: AlertProps) {
  return (
    <div
      role="alert"
      className={cn(
        'rounded-md border px-4 py-3',
        variant === 'error'
          ? 'border-red-200 bg-danger-muted text-danger'
          : 'border-border bg-surface-muted text-foreground',
        className,
      )}
    >
      <div className="flex items-start gap-3">
        <AlertCircle className="mt-0.5 size-4 shrink-0" />
        <div className="flex-1 space-y-1">
          {title ? <p className="font-medium">{title}</p> : null}
          <p className="text-sm">{message}</p>
          {onRetry ? (
            <Button variant="secondary" size="sm" className="mt-2" onClick={onRetry}>
              Try again
            </Button>
          ) : null}
        </div>
      </div>
    </div>
  )
}

interface EmptyStateProps {
  title: string
  description?: string
  action?: ReactNode
}

export function EmptyState({ title, description, action }: EmptyStateProps) {
  return (
    <div className="rounded-lg border border-dashed border-border bg-surface px-6 py-12 text-center">
      <h3 className="text-base font-medium text-foreground">{title}</h3>
      {description ? <p className="mt-2 text-sm text-muted">{description}</p> : null}
      {action ? <div className="mt-4">{action}</div> : null}
    </div>
  )
}
