import { forwardRef, type InputHTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string
  error?: string
  hint?: string
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ className, label, error, hint, id, ...props }, ref) => {
    const inputId = id ?? props.name

    return (
      <div className="space-y-1.5">
        {label ? (
          <label htmlFor={inputId} className="block text-sm font-medium text-foreground">
            {label}
          </label>
        ) : null}
        <input
          ref={ref}
          id={inputId}
          className={cn(
            'flex h-10 w-full rounded-md border bg-surface px-3 py-2 text-sm',
            'placeholder:text-muted-foreground',
            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/30',
            error ? 'border-danger' : 'border-border',
            className,
          )}
          {...props}
        />
        {error ? <p className="text-sm text-danger">{error}</p> : null}
        {!error && hint ? <p className="text-sm text-muted">{hint}</p> : null}
      </div>
    )
  },
)

Input.displayName = 'Input'
