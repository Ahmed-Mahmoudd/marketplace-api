import { useState } from 'react'
import { Link, Navigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Store } from 'lucide-react'
import { applyForVendor, getMyVendor } from '@/api/vendor'
import { getErrorMessage, getFirstFieldError, getValidationErrors, isApiError } from '@/api/client'
import { useAuth } from '@/context'
import { Card, CardBody, CardHeader, CardTitle } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Alert } from '@/components/ui/Alert'
import { PageLoader } from '@/components/ui/Spinner'
import type { Vendor } from '@/types'

function statusVariant(status: Vendor['status']) {
  if (status === 'approved') return 'success'
  if (status === 'pending') return 'warning'
  return 'danger'
}

const statusCopy: Record<Vendor['status'], string> = {
  pending: 'Your application is under review. We will let you know as soon as a decision is made.',
  approved: 'Your store is approved. Head to your vendor dashboard to manage products and orders.',
  suspended: 'Your vendor account is currently suspended. Contact support for more information.',
  rejected: 'Your application was not approved this time. Reach out to support if you have questions.',
}

export function VendorApplyPage() {
  const { hasRole } = useAuth()
  const queryClient = useQueryClient()

  const [storeName, setStoreName] = useState('')
  const [showPayout, setShowPayout] = useState(false)
  const [bankName, setBankName] = useState('')
  const [iban, setIban] = useState('')

  const vendorQuery = useQuery({
    queryKey: ['my-vendor'],
    queryFn: getMyVendor,
    retry: false,
  })

  const applyMutation = useMutation({
    mutationFn: () =>
      applyForVendor({
        store_name: storeName,
        payout_details: showPayout && bankName && iban ? { bank_name: bankName, iban } : undefined,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['my-vendor'] })
    },
  })

  const validationErrors = getValidationErrors(applyMutation.error)
  const vendorNotFound = isApiError(vendorQuery.error) && vendorQuery.error.response?.status === 404

  if (hasRole('vendor')) {
    return <Navigate to="/vendor" replace />
  }

  if (vendorQuery.isLoading) {
    return <PageLoader />
  }

  const vendor = vendorQuery.data

  return (
    <div className="container-app max-w-lg py-8">
      <div className="mb-6 text-center">
        <div className="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-accent-muted text-accent">
          <Store className="size-6" />
        </div>
        <h1 className="text-xl font-semibold text-ink">Sell on Marketplace</h1>
        <p className="mt-1 text-sm text-muted">
          Apply to open your own storefront and start listing products.
        </p>
      </div>

      {vendor ? (
        <Card>
          <CardHeader className="flex items-center justify-between">
            <CardTitle>{vendor.store_name}</CardTitle>
            <Badge variant={statusVariant(vendor.status)} className="capitalize">
              {vendor.status}
            </Badge>
          </CardHeader>
          <CardBody>
            <p className="text-sm text-muted">{statusCopy[vendor.status]}</p>
          </CardBody>
        </Card>
      ) : (
        <Card>
          <CardBody className="space-y-4">
            {!vendorNotFound && vendorQuery.isError ? (
              <Alert message={getErrorMessage(vendorQuery.error, 'Unable to load vendor profile.')} />
            ) : null}

            <Input
              label="Store name"
              placeholder="e.g. Sunset Goods"
              value={storeName}
              onChange={(event) => setStoreName(event.target.value)}
              error={getFirstFieldError(validationErrors, 'store_name')}
            />

            <button
              type="button"
              className="text-sm font-medium text-accent hover:underline"
              onClick={() => setShowPayout((value) => !value)}
            >
              {showPayout ? 'Hide payout details' : 'Add payout details (optional)'}
            </button>

            {showPayout ? (
              <div className="space-y-4 rounded-md border border-border bg-surface-muted p-4">
                <Input
                  label="Bank name"
                  value={bankName}
                  onChange={(event) => setBankName(event.target.value)}
                  error={getFirstFieldError(validationErrors, 'payout_details.bank_name')}
                />
                <Input
                  label="IBAN"
                  value={iban}
                  onChange={(event) => setIban(event.target.value)}
                  error={getFirstFieldError(validationErrors, 'payout_details.iban')}
                />
              </div>
            ) : null}

            {applyMutation.isError ? (
              <Alert message={getErrorMessage(applyMutation.error, 'Unable to submit application.')} />
            ) : null}

            <Button
              className="w-full"
              disabled={!storeName.trim()}
              isLoading={applyMutation.isPending}
              onClick={() => applyMutation.mutate()}
            >
              Submit application
            </Button>
          </CardBody>
        </Card>
      )}

      <p className="mt-6 text-center text-sm text-muted">
        <Link to="/" className="text-accent hover:underline">
          Back to shop
        </Link>
      </p>
    </div>
  )
}
