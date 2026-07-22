<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ProductImageService
{
  /**
   * @param  UploadedFile[]  $files
   */
  public function upload(Product $product, array $files, bool $makeFirstPrimary = false): Collection
  {
    $hasExistingImages = $product->images()->exists();
    $created = collect();

    foreach ($files as $index => $file) {
      $path = $file->store('products', 'public');

      // The very first image a product ever gets is primary automatically.
      // Otherwise, only honor `is_primary` for the first file in this batch.
      $isPrimary = (! $hasExistingImages && $index === 0) || ($makeFirstPrimary && $index === 0);

      if ($isPrimary) {
        $this->clearExistingPrimary($product);
      }

      $created->push($product->images()->create([
        'path' => $path,
        'is_primary' => $isPrimary,
      ]));
    }

    return $created;
  }

  public function setPrimary(Product $product, ProductImage $image): ProductImage
  {
    $this->clearExistingPrimary($product);

    $image->update(['is_primary' => true]);

    return $image->fresh();
  }

  public function delete(Product $product, ProductImage $image): void
  {
    $wasPrimary = $image->is_primary;

    Storage::disk('public')->delete($image->path);
    $image->delete();

    // Keep the "always exactly one primary if any images exist" invariant.
    if ($wasPrimary) {
      $next = $product->images()->oldest()->first();
      $next?->update(['is_primary' => true]);
    }
  }

  private function clearExistingPrimary(Product $product): void
  {
    $product->images()->where('is_primary', true)->update(['is_primary' => false]);
  }
}
