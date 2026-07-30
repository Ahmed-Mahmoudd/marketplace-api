import { ChevronLeft, ChevronRight } from 'lucide-react'
import { Button } from './Button'
import type { PaginatedMeta } from '@/types'

interface PaginationProps {
  meta: PaginatedMeta
  onPageChange: (page: number) => void
}

export function Pagination({ meta, onPageChange }: PaginationProps) {
  if (meta.last_page <= 1) {
    return null
  }

  return (
    <div className="flex items-center justify-between border-t border-border pt-4">
      <p className="text-sm text-muted">
        Page {meta.current_page} of {meta.last_page} · {meta.total} results
      </p>
      <div className="flex items-center gap-2">
        <Button
          variant="secondary"
          size="sm"
          disabled={meta.current_page <= 1}
          onClick={() => onPageChange(meta.current_page - 1)}
        >
          <ChevronLeft className="size-4" />
          Prev
        </Button>
        <Button
          variant="secondary"
          size="sm"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPageChange(meta.current_page + 1)}
        >
          Next
          <ChevronRight className="size-4" />
        </Button>
      </div>
    </div>
  )
}
