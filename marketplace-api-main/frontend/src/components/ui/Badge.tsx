import { cn } from '@/lib/utils'

type BadgeVariant = 'default' | 'success' | 'warning' | 'danger' | 'muted'

interface BadgeProps {
  children: React.ReactNode
  variant?: BadgeVariant
  className?: string
}

const variants: Record<BadgeVariant, string> = {
  default: 'bg-accent-muted text-accent border-accent/20',
  success: 'bg-green-50 text-success border-green-200',
  warning: 'bg-amber-50 text-warning border-amber-200',
  danger: 'bg-danger-muted text-danger border-red-200',
  muted: 'bg-surface-muted text-muted border-border',
}

export function Badge({ children, variant = 'default', className }: BadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded border px-2 py-0.5 text-xs font-medium',
        variants[variant],
        className,
      )}
    >
      {children}
    </span>
  )
}
