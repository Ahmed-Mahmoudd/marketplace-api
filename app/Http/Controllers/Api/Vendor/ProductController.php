<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
  public function __construct(
    private ProductService $productService
  ) {}

  public function index(Request $request)
  {
    $this->authorize('viewAny', Product::class);

    $products = $this->productService->listForVendor(
      $request->user()->vendor,
      $request->only(['q', 'status', 'sort'])
    );

    return $this->success([
      'items' => ProductResource::collection($products->items()),
      'meta' => [
        'current_page' => $products->currentPage(),
        'last_page' => $products->lastPage(),
        'per_page' => $products->perPage(),
        'total' => $products->total(),
      ],
    ]);
  }

  public function store(StoreProductRequest $request)
  {
    $product = $this->productService->create(
      $request->user()->vendor,
      $request->validated()
    );

    return $this->success(
      new ProductResource($product),
      'Product created.',
      201
    );
  }

  public function show(Product $product)
  {
    $this->authorize('view', $product);

    return $this->success(
      new ProductResource($product->load(['category', 'vendor']))
    );
  }

  public function update(UpdateProductRequest $request, Product $product)
  {
    $this->authorize('update', $product);

    $product = $this->productService->update($product, $request->validated());

    return $this->success(
      new ProductResource($product),
      'Product updated.'
    );
  }

  public function destroy(Product $product)
  {
    $this->authorize('delete', $product);

    $this->productService->delete($product);

    return $this->success(null, 'Product deleted.');
  }
}
