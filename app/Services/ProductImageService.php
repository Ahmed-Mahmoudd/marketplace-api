<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ProductImageService
{
  private const DISK = 'public';

  private const DIRECTORY = 'products';

  /**
   * @param  array<int, UploadedFile>  $files
   */
  public function upload(Product $product, array $files, bool $markAsPrimary): Collection
  {
    $hadNoImages = ! $product->images()->exists();

    $uploaded = collect($files)->map(
      fn(UploadedFile $file) => $product->images()->create([
        'path' => $file->store(self::DIRECTORY, self::DISK),
      ])
    );

    if ($hadNoImages) {
      $this->makePrimary($product, $uploaded->first());
    } elseif ($markAsPrimary) {
      $this->makePrimary($product, $uploaded->last());
    }

    return $uploaded->map(fn(ProductImage $image) => $image->fresh());
  }

  public function delete(Product $product, ProductImage $image): void
  {
    Storage::disk(self::DISK)->delete($image->path);

    $wasPrimary = $image->is_primary;

    $image->delete();

    if ($wasPrimary) {
      $next = $product->images()->first();

      if ($next) {
        $this->makePrimary($product, $next);
      }
    }
  }

  public function makePrimary(Product $product, ProductImage $image): ProductImage
  {
    $product->images()->where('id', '!=', $image->id)->update(['is_primary' => false]);

    $image->update(['is_primary' => true]);

    return $image->fresh();
  }
}
