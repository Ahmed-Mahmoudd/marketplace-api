import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ExternalLink, RefreshCw } from 'lucide-react'
import { createHandoffCode } from '@/api/auth'
import { getErrorMessage } from '@/api/client'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { PageLoader } from '@/components/ui/Spinner'

const analyticsUrl = import.meta.env.VITE_ANALYTICS_URL

/**
 * Embeds the Streamlit analytics dashboard inside the vendor area.
 *
 * The vendor is signed in via a one-time handoff code rather than a shared
 * token: the code is spent the moment the dashboard loads it, so the URL is
 * useless to anyone who later reads it out of history or a log.
 */
export function VendorAnalyticsPage() {
  const [reloadKey, setReloadKey] = useState(0)

  const handoffQuery = useQuery({
    // reloadKey is part of the key so "Reload" mints a fresh code rather than
    // replaying the spent one, which the API would reject.
    queryKey: ['analytics-handoff', reloadKey],
    queryFn: createHandoffCode,
    // Codes are single-use and expire in ~60s — never serve one from cache.
    staleTime: 0,
    gcTime: 0,
    refetchOnWindowFocus: false,
    retry: false,
  })

  const embedSrc = useMemo(() => {
    if (!handoffQuery.data || !analyticsUrl) return null

    const url = new URL(analyticsUrl)
    url.searchParams.set('code', handoffQuery.data.code)
    url.searchParams.set('embedded', '1')
    // Streamlit's own reserved params: hide its header, footer and padding so
    // it reads as part of this page rather than a separate app.
    url.searchParams.set('embed', 'true')
    url.searchParams.set('embed_options', 'dark_theme')

    return url.toString()
  }, [handoffQuery.data])

  if (!analyticsUrl) {
    return (
      <Alert
        title="Analytics dashboard not configured"
        message="Set VITE_ANALYTICS_URL in the frontend environment to the dashboard's URL (e.g. http://localhost:8501)."
      />
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-lg font-semibold text-ink">Store analytics</h2>
          <p className="mt-1 text-sm text-muted">
            Revenue, customers, forecasting and recommendations for your store only.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Button
            variant="secondary"
            size="sm"
            onClick={() => setReloadKey((key) => key + 1)}
            isLoading={handoffQuery.isFetching}
          >
            <RefreshCw className="mr-2 size-4" />
            Reload
          </Button>

          {embedSrc ? (
            <a href={embedSrc} target="_blank" rel="noreferrer">
              <Button variant="ghost" size="sm">
                <ExternalLink className="mr-2 size-4" />
                Open full screen
              </Button>
            </a>
          ) : null}
        </div>
      </div>

      {handoffQuery.isError ? (
        <Alert
          message={getErrorMessage(handoffQuery.error, 'Could not start an analytics session.')}
          onRetry={() => void handoffQuery.refetch()}
        />
      ) : null}

      {handoffQuery.isPending ? <PageLoader /> : null}

      {embedSrc ? (
        <div className="overflow-hidden rounded-lg border border-border bg-surface">
          <iframe
            key={embedSrc}
            title="Store analytics"
            src={embedSrc}
            className="h-[calc(100vh-16rem)] min-h-[600px] w-full border-0"
          />
        </div>
      ) : null}
    </div>
  )
}
