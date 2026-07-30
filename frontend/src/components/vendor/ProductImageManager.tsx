import { useRef, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ImageOff, Star, Trash2, Upload } from 'lucide-react'
import {
  deleteVendorProductImage,
  setVendorProductImagePrimary,
  uploadVendorProductImages,
} from '@/api/vendor'
import { getErrorMessage } from '@/api/client'
import { Button } from '@/components/ui/Button'
import { Alert } from '@/components/ui/Alert'
import type { ProductImage } from '@/types'

interface ProductImageManagerProps {
  productId: number
  images: ProductImage[]
}

export function ProductImageManager({ productId, images }: ProductImageManagerProps) {
  const queryClient = useQueryClient()
  const fileInputRef = useRef<HTMLInputElement>(null)
  const [deletingId, setDeletingId] = useState<number | null>(null)

  function invalidate() {
    void queryClient.invalidateQueries({ queryKey: ['vendor-product', String(productId)] })
    void queryClient.invalidateQueries({ queryKey: ['vendor-products'] })
  }

  const uploadMutation = useMutation({
    mutationFn: (files: File[]) => uploadVendorProductImages(productId, files, images.length === 0),
    onSuccess: () => {
      if (fileInputRef.current) {
        fileInputRef.current.value = ''
      }
      invalidate()
    },
  })

  const setPrimaryMutation = useMutation({
    mutationFn: (imageId: number) => setVendorProductImagePrimary(productId, imageId),
    onSuccess: invalidate,
  })

  const deleteMutation = useMutation({
    mutationFn: (imageId: number) => deleteVendorProductImage(productId, imageId),
    onSuccess: () => {
      setDeletingId(null)
      invalidate()
    },
  })

  function handleFilesSelected(fileList: FileList | null) {
    if (!fileList || fileList.length === 0) return
    uploadMutation.mutate(Array.from(fileList))
  }

  return (
    <div className="space-y-4">
      {images.length === 0 ? (
        <div className="flex items-center gap-2 rounded-md border border-dashed border-border bg-surface-muted px-4 py-6 text-sm text-muted">
          <ImageOff className="size-4" />
          No images uploaded yet.
        </div>
      ) : (
        <div className="grid grid-cols-3 gap-3 sm:grid-cols-4">
          {images.map((image) => (
            <div
              key={image.id}
              className="group relative aspect-square overflow-hidden rounded-md border border-border bg-surface-muted"
            >
              <img src={image.url} alt="" className="h-full w-full object-cover" />

              {image.is_primary ? (
                <span className="absolute left-1.5 top-1.5 inline-flex items-center gap-1 rounded bg-accent px-1.5 py-0.5 text-[10px] font-medium text-accent-foreground">
                  <Star className="size-2.5 fill-current" />
                  Primary
                </span>
              ) : null}

              <div className="absolute inset-x-0 bottom-0 flex items-center justify-end gap-1 bg-gradient-to-t from-black/60 to-transparent p-1.5 opacity-0 transition-opacity group-hover:opacity-100">
                {!image.is_primary ? (
                  <button
                    type="button"
                    className="rounded bg-white/90 p-1 text-foreground hover:bg-white"
                    title="Set as primary"
                    aria-label="Set as primary"
                    disabled={setPrimaryMutation.isPending}
                    onClick={() => setPrimaryMutation.mutate(image.id)}
                  >
                    <Star className="size-3.5" />
                  </button>
                ) : null}
                <button
                  type="button"
                  className="rounded bg-white/90 p-1 text-danger hover:bg-white"
                  title="Delete image"
                  aria-label="Delete image"
                  disabled={deleteMutation.isPending}
                  onClick={() => {
                    setDeletingId(image.id)
                    deleteMutation.mutate(image.id)
                  }}
                >
                  {deletingId === image.id && deleteMutation.isPending ? (
                    <span className="block size-3.5 animate-spin rounded-full border-2 border-border border-t-danger" />
                  ) : (
                    <Trash2 className="size-3.5" />
                  )}
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {uploadMutation.isError ? (
        <Alert message={getErrorMessage(uploadMutation.error, 'Unable to upload images.')} />
      ) : null}

      {deleteMutation.isError ? (
        <Alert message={getErrorMessage(deleteMutation.error, 'Unable to delete image.')} />
      ) : null}

      <div>
        <input
          ref={fileInputRef}
          type="file"
          accept="image/png,image/jpeg,image/webp"
          multiple
          className="hidden"
          onChange={(event) => handleFilesSelected(event.target.files)}
        />
        <Button
          type="button"
          variant="secondary"
          size="sm"
          isLoading={uploadMutation.isPending}
          onClick={() => fileInputRef.current?.click()}
        >
          <Upload className="size-4" />
          Upload images
        </Button>
        <p className="mt-1.5 text-xs text-muted">JPG, PNG or WEBP, up to 2MB each.</p>
      </div>
    </div>
  )
}
