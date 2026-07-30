import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ChevronLeft } from 'lucide-react'
import {
  createVendorProduct,
  getVendorProduct,
  updateVendorProduct,
  type VendorProductStatus,
} from '@/api/vendor'
import { listCategories } from '@/api/categories'
import { getErrorMessage, getFirstFieldError, getValidationErrors } from '@/api/client'
import { ProductImageManager } from '@/components/vendor/ProductImageManager'
import { Card, CardBody, CardHeader, CardTitle } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Select } from '@/components/ui/Select'
import { Textarea } from '@/components/ui/Textarea'
import { Alert } from '@/components/ui/Alert'
import { PageLoader } from '@/components/ui/Spinner'

export function VendorProductFormPage() {
  const { id } = useParams<{ id: string }>()
  const isEditing = Boolean(id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const [categoryId, setCategoryId] = useState('')
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [price, setPrice] = useState('')
  const [stock, setStock] = useState('')
  const [status, setStatus] = useState<VendorProductStatus>('active')
  const [hydrated, setHydrated] = useState(!isEditing)

  const categoriesQuery = useQuery({
    queryKey: ['categories', 'vendor-form'],
    queryFn: () => listCategories({ sort: 'name', page: 1 }),
  })

  const productQuery = useQuery({
    queryKey: ['vendor-product', id],
    queryFn: () => getVendorProduct(id as string),
    enabled: isEditing,
  })

  useEffect(() => {
    if (productQuery.data && !hydrated) {
      const product = productQuery.data
      setCategoryId(product.category ? String(product.category.id) : '')
      setName(product.name)
      setDescription(product.description ?? '')
      setPrice(product.price)
      setStock(String(product.stock))
      setStatus(product.status)
      setHydrated(true)
    }
  }, [productQuery.data, hydrated])

  const createMutation = useMutation({
    mutationFn: () =>
      createVendorProduct({
        category_id: Number(categoryId),
        name,
        description: description || null,
        price,
        stock: Number(stock),
        status,
      }),
    onSuccess: (product) => {
      void queryClient.invalidateQueries({ queryKey: ['vendor-products'] })
      navigate(`/vendor/products/${product.id}/edit`, { replace: true })
    },
  })

  const updateMutation = useMutation({
    mutationFn: () =>
      updateVendorProduct(id as string, {
        category_id: Number(categoryId),
        name,
        description: description || null,
        price,
        stock: Number(stock),
        status,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['vendor-products'] })
      void queryClient.invalidateQueries({ queryKey: ['vendor-product', id] })
    },
  })

  const saveMutation = isEditing ? updateMutation : createMutation
  const validationErrors = getValidationErrors(saveMutation.error)

  function handleSubmit() {
    saveMutation.mutate()
  }

  if (isEditing && productQuery.isLoading) {
    return <PageLoader />
  }

  if (isEditing && productQuery.isError) {
    return (
      <div className="space-y-4">
        <Alert
          title="Unable to load product"
          message={getErrorMessage(productQuery.error)}
          onRetry={() => void productQuery.refetch()}
        />
        <Link to="/vendor/products" className="inline-flex items-center gap-1 text-sm text-muted hover:text-foreground">
          <ChevronLeft className="size-4" />
          Back to products
        </Link>
      </div>
    )
  }

  const isFormValid = categoryId !== '' && name.trim() !== '' && price !== '' && stock !== ''

  return (
    <div className="max-w-2xl space-y-6">
      <Link
        to="/vendor/products"
        className="inline-flex items-center gap-1 text-sm text-muted hover:text-foreground"
      >
        <ChevronLeft className="size-4" />
        Back to products
      </Link>

      <Card>
        <CardHeader>
          <CardTitle>{isEditing ? 'Edit product' : 'New product'}</CardTitle>
        </CardHeader>
        <CardBody className="space-y-4">
          <Input
            label="Name"
            value={name}
            onChange={(event) => setName(event.target.value)}
            error={getFirstFieldError(validationErrors, 'name')}
          />

          <Select
            label="Category"
            value={categoryId}
            onChange={(event) => setCategoryId(event.target.value)}
          >
            <option value="">Select a category…</option>
            {categoriesQuery.data?.items.map((category) => (
              <option key={category.id} value={category.id}>
                {category.name}
              </option>
            ))}
          </Select>
          {getFirstFieldError(validationErrors, 'category_id') ? (
            <p className="-mt-2 text-sm text-danger">
              {getFirstFieldError(validationErrors, 'category_id')}
            </p>
          ) : null}

          <Textarea
            label="Description"
            rows={5}
            value={description}
            onChange={(event) => setDescription(event.target.value)}
            error={getFirstFieldError(validationErrors, 'description')}
          />

          <div className="grid grid-cols-2 gap-4">
            <Input
              label="Price"
              type="number"
              min="0.01"
              step="0.01"
              value={price}
              onChange={(event) => setPrice(event.target.value)}
              error={getFirstFieldError(validationErrors, 'price')}
            />
            <Input
              label="Stock"
              type="number"
              min="0"
              step="1"
              value={stock}
              onChange={(event) => setStock(event.target.value)}
              error={getFirstFieldError(validationErrors, 'stock')}
            />
          </div>

          <Select
            label="Status"
            value={status}
            onChange={(event) => setStatus(event.target.value as VendorProductStatus)}
          >
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </Select>

          {saveMutation.isError ? (
            <Alert message={getErrorMessage(saveMutation.error, 'Unable to save product.')} />
          ) : null}

          <Button
            className="w-full"
            disabled={!isFormValid}
            isLoading={saveMutation.isPending}
            onClick={handleSubmit}
          >
            {isEditing ? 'Save changes' : 'Create product'}
          </Button>
        </CardBody>
      </Card>

      {isEditing && productQuery.data ? (
        <Card>
          <CardHeader>
            <CardTitle>Images</CardTitle>
          </CardHeader>
          <CardBody>
            <ProductImageManager
              productId={productQuery.data.id}
              images={productQuery.data.images ?? []}
            />
          </CardBody>
        </Card>
      ) : null}
    </div>
  )
}
