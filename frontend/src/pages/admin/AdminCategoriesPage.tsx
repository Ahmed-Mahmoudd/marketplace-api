import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Pencil, Plus, Trash2, X } from 'lucide-react'
import { listAdminCategories, createCategory, deleteCategory, updateCategory } from '@/api/admin/categories'
import { getErrorMessage, getValidationErrors, getFirstFieldError } from '@/api/client'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Badge } from '@/components/ui/Badge'
import { Alert, EmptyState } from '@/components/ui/Alert'
import { PageLoader } from '@/components/ui/Spinner'
import type { Category } from '@/types'

function CategoryRow({ category }: { category: Category }) {
  const queryClient = useQueryClient()
  const [isEditing, setIsEditing] = useState(false)
  const [name, setName] = useState(category.name)
  const [description, setDescription] = useState(category.description ?? '')
  const [confirmingDelete, setConfirmingDelete] = useState(false)

  const updateMutation = useMutation({
    mutationFn: () => updateCategory(category.id, { name, description: description || null }),
    onSuccess: () => {
      setIsEditing(false)
      void queryClient.invalidateQueries({ queryKey: ['admin-categories'] })
    },
  })

  const toggleActiveMutation = useMutation({
    mutationFn: () => updateCategory(category.id, { is_active: !category.is_active }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['admin-categories'] })
    },
  })

  const deleteMutation = useMutation({
    mutationFn: () => deleteCategory(category.id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['admin-categories'] })
    },
  })

  const validationErrors = getValidationErrors(updateMutation.error)

  if (isEditing) {
    return (
      <Card className="space-y-3 p-4">
        <Input
          label="Name"
          value={name}
          onChange={(event) => setName(event.target.value)}
          error={getFirstFieldError(validationErrors, 'name')}
        />
        <Input
          label="Description"
          value={description}
          onChange={(event) => setDescription(event.target.value)}
        />
        {updateMutation.isError ? (
          <Alert message={getErrorMessage(updateMutation.error, 'Unable to save category.')} />
        ) : null}
        <div className="flex gap-2">
          <Button size="sm" isLoading={updateMutation.isPending} onClick={() => updateMutation.mutate()}>
            Save
          </Button>
          <Button variant="secondary" size="sm" onClick={() => setIsEditing(false)}>
            Cancel
          </Button>
        </div>
      </Card>
    )
  }

  return (
    <Card className="flex items-center justify-between gap-4 p-4">
      <div className="min-w-0">
        <p className="text-sm font-medium text-foreground">{category.name}</p>
        {category.description ? (
          <p className="line-clamp-1 text-xs text-muted">{category.description}</p>
        ) : null}
      </div>

      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={() => toggleActiveMutation.mutate()}
          disabled={toggleActiveMutation.isPending}
        >
          <Badge variant={category.is_active ? 'success' : 'muted'}>
            {category.is_active ? 'Active' : 'Inactive'}
          </Badge>
        </button>

        <button
          type="button"
          className="text-muted hover:text-foreground"
          onClick={() => setIsEditing(true)}
          aria-label="Edit category"
        >
          <Pencil className="size-4" />
        </button>

        {confirmingDelete ? (
          <div className="flex items-center gap-2">
            <button
              type="button"
              className="text-xs text-muted hover:text-foreground"
              onClick={() => setConfirmingDelete(false)}
            >
              <X className="size-3.5" />
            </button>
            <Button
              variant="danger"
              size="sm"
              isLoading={deleteMutation.isPending}
              onClick={() => deleteMutation.mutate()}
            >
              Confirm
            </Button>
          </div>
        ) : (
          <button
            type="button"
            className="text-muted hover:text-danger"
            onClick={() => setConfirmingDelete(true)}
            aria-label="Delete category"
          >
            <Trash2 className="size-4" />
          </button>
        )}
      </div>

      {deleteMutation.isError ? (
        <p className="text-sm text-danger">
          {getErrorMessage(deleteMutation.error, 'Unable to delete category.')}
        </p>
      ) : null}
    </Card>
  )
}

export function AdminCategoriesPage() {
  const queryClient = useQueryClient()
  const [showCreate, setShowCreate] = useState(false)
  const [newName, setNewName] = useState('')
  const [newDescription, setNewDescription] = useState('')

  const categoriesQuery = useQuery({
    queryKey: ['admin-categories'],
    queryFn: () => listAdminCategories({ sort: 'name', per_page: 100 }),
  })

  const createMutation = useMutation({
    mutationFn: () => createCategory({ name: newName, description: newDescription || null }),
    onSuccess: () => {
      setShowCreate(false)
      setNewName('')
      setNewDescription('')
      void queryClient.invalidateQueries({ queryKey: ['admin-categories'] })
    },
  })

  const validationErrors = getValidationErrors(createMutation.error)

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted">
          {categoriesQuery.data ? `${categoriesQuery.data.meta.total} categories` : ' '}
        </p>
        <Button size="sm" onClick={() => setShowCreate((value) => !value)}>
          <Plus className="size-4" />
          New category
        </Button>
      </div>

      {showCreate ? (
        <Card className="space-y-3 p-4">
          <Input
            label="Name"
            value={newName}
            onChange={(event) => setNewName(event.target.value)}
            error={getFirstFieldError(validationErrors, 'name')}
          />
          <Input
            label="Description"
            value={newDescription}
            onChange={(event) => setNewDescription(event.target.value)}
          />
          {createMutation.isError ? (
            <Alert message={getErrorMessage(createMutation.error, 'Unable to create category.')} />
          ) : null}
          <div className="flex gap-2">
            <Button size="sm" isLoading={createMutation.isPending} onClick={() => createMutation.mutate()}>
              Create
            </Button>
            <Button variant="secondary" size="sm" onClick={() => setShowCreate(false)}>
              Cancel
            </Button>
          </div>
        </Card>
      ) : null}

      {categoriesQuery.isLoading ? <PageLoader /> : null}

      {categoriesQuery.isError ? (
        <Alert
          message={getErrorMessage(categoriesQuery.error, 'Unable to load categories.')}
          onRetry={() => void categoriesQuery.refetch()}
        />
      ) : null}

      {categoriesQuery.data && categoriesQuery.data.items.length === 0 ? (
        <EmptyState title="No categories yet" description="Create your first category above." />
      ) : null}

      <div className="space-y-2">
        {categoriesQuery.data?.items.map((category) => (
          <CategoryRow key={category.id} category={category} />
        ))}
      </div>
    </div>
  )
}
