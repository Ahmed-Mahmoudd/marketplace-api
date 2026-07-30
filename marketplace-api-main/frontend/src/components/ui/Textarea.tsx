import { forwardRef, type TextareaHTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  label?: string
  error?: string
  hint?: string
}

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(
  ({ className, label, error, hint, id, ...props }, ref) => {
    const textareaId = id ?? props.name

    return (
      <div className="space-y-1.5">
        {label ? (
          <label htmlFor={textareaId} className="block text-sm font-medium text-foreground">
            {label}
          </label>
        ) : null}
        <textarea
          ref={ref}
          id={textareaId}
          className={cn(
            'flex min-h-[100px] w-full rounded-md border bg-surface px-3 py-2 text-sm',
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

Textarea.displayName = 'Textarea'
