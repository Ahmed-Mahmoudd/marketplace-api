<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductImageRequest;
use App\Http\Resources\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;

class ProductImageController extends Controller
{
  public function __construct(
    private ProductImageService $productImageService
  ) {}

  public function store(StoreProductImageRequest $request, Product $product)
  {
    $this->authorize('update', $product);

    $images = $this->productImageService->upload(
      $product,
      $request->file('images'),
      $request->boolean('is_primary')
    );

    return $this->success(
      ProductImageResource::collection($images),
      'Images uploaded.',
      201
    );
  }

  public function destroy(Product $product, ProductImage $image)
  {
    $this->authorize('update', $product);
    $this->ensureImageBelongsToProduct($product, $image);

    $this->productImageService->delete($product, $image);

    return $this->success(null, 'Image deleted.');
  }

  public function primary(Product $product, ProductImage $image)
  {
    $this->authorize('update', $product);
    $this->ensureImageBelongsToProduct($product, $image);

    $image = $this->productImageService->makePrimary($product, $image);

    return $this->success(
      new ProductImageResource($image),
      'Primary image updated.'
    );
  }

  private function ensureImageBelongsToProduct(Product $product, ProductImage $image): void
  {
    abort_if($image->product_id !== $product->id, 404);
  }
}
