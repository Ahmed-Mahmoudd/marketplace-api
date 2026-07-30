import { cn } from '@/lib/utils'

interface SpinnerProps {
  className?: string
  label?: string
}

export function Spinner({ className, label = 'Loading' }: SpinnerProps) {
  return (
    <div className={cn('inline-flex items-center gap-2 text-sm text-muted', className)}>
      <span
        className="size-4 animate-spin rounded-full border-2 border-border border-t-accent"
        aria-hidden="true"
      />
      <span>{label}</span>
    </div>
  )
}

export function PageLoader() {
  return (
    <div className="flex min-h-[40vh] items-center justify-center">
      <Spinner label="Loading…" />
    </div>
  )
}
