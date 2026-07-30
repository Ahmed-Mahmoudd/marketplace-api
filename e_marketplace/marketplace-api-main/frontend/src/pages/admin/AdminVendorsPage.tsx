import { useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  approveVendor,
  listAdminVendors,
  rejectVendor,
  suspendVendor,
  type VendorStatusFilter,
} from '@/api/admin/vendors'
import { getErrorMessage } from '@/api/client'
import { Card } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Select } from '@/components/ui/Select'
import { Alert, EmptyState } from '@/components/ui/Alert'
import { Pagination } from '@/components/ui/Pagination'
import { PageLoader } from '@/components/ui/Spinner'
import type { Vendor } from '@/types'

function statusVariant(status: Vendor['status']) {
  if (status === 'approved') return 'success'
  if (status === 'pending') return 'warning'
  return 'danger'
}

export function AdminVendorsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const status = (searchParams.get('status') as VendorStatusFilter | null) ?? undefined
  const page = Number(searchParams.get('page') ?? '1')
  const queryClient = useQueryClient()

  const vendorsQuery = useQuery({
    queryKey: ['admin-vendors', { status, page }],
    queryFn: () => listAdminVendors(status, page),
  })

  function updateParams(next: Record<string, string | undefined>) {
    const params = new URLSearchParams(searchParams)
    Object.entries(next).forEach(([key, value]) => {
      if (value) {
        params.set(key, value)
      } else {
        params.delete(key)
      }
    })
    if (!('page' in next)) {
      params.delete('page')
    }
    setSearchParams(params)
  }

  const approveMutation = useMutation({
    mutationFn: approveVendor,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['admin-vendors'] }),
  })
  const suspendMutation = useMutation({
    mutationFn: suspendVendor,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['admin-vendors'] }),
  })
  const rejectMutation = useMutation({
    mutationFn: rejectVendor,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['admin-vendors'] }),
  })

  const isBusy = approveMutation.isPending || suspendMutation.isPending || rejectMutation.isPending
  const actionError = approveMutation.error ?? suspendMutation.error ?? rejectMutation.error

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <Select
          aria-label="Filter by status"
          value={status ?? ''}
          onChange={(event) => updateParams({ status: event.target.value || undefined })}
          className="w-auto"
        >
          <option value="">All statuses</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="suspended">Suspended</option>
          <option value="rejected">Rejected</option>
        </Select>
      </div>

      {actionError ? (
        <Alert message={getErrorMessage(actionError, 'Unable to update vendor.')} />
      ) : null}

      {vendorsQuery.isLoading ? <PageLoader /> : null}

      {vendorsQuery.isError ? (
        <Alert
          message={getErrorMessage(vendorsQuery.error, 'Unable to load vendors.')}
          onRetry={() => void vendorsQuery.refetch()}
        />
      ) : null}

      {vendorsQuery.data && vendorsQuery.data.items.length === 0 ? (
        <EmptyState title="No vendors found" description="Try a different status filter." />
      ) : null}

      {vendorsQuery.data && vendorsQuery.data.items.length > 0 ? (
        <>
          <div className="space-y-2">
            {vendorsQuery.data.items.map((vendor) => (
              <Card key={vendor.id} className="flex items-center justify-between gap-4 p-4">
                <div>
                  <p className="text-sm font-medium text-foreground">{vendor.store_name}</p>
                  <p className="text-xs text-muted">
                    {vendor.user?.name} · {vendor.user?.email}
                  </p>
                </div>

                <div className="flex items-center gap-3">
                  <Badge variant={statusVariant(vendor.status)} className="capitalize">
                    {vendor.status}
                  </Badge>

                  {vendor.status === 'pending' ? (
                    <div className="flex gap-2">
                      <Button
                        size="sm"
                        disabled={isBusy}
                        isLoading={approveMutation.isPending}
                        onClick={() => approveMutation.mutate(vendor.id)}
                      >
                        Approve
                      </Button>
                      <Button
                        variant="danger"
                        size="sm"
                        disabled={isBusy}
                        isLoading={rejectMutation.isPending}
                        onClick={() => rejectMutation.mutate(vendor.id)}
                      >
                        Reject
                      </Button>
                    </div>
                  ) : null}

                  {vendor.status === 'approved' ? (
                    <Button
                      variant="danger"
                      size="sm"
                      disabled={isBusy}
                      isLoading={suspendMutation.isPending}
                      onClick={() => suspendMutation.mutate(vendor.id)}
                    >
                      Suspend
                    </Button>
                  ) : null}
                </div>
              </Card>
            ))}
          </div>

          <Pagination
            meta={vendorsQuery.data.meta}
            onPageChange={(nextPage) => updateParams({ page: String(nextPage) })}
          />
        </>
      ) : null}
    </div>
  )
}
