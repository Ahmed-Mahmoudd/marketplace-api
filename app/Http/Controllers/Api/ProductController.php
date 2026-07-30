<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicProductResource;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class ProductController extends Controller
{
  public function __construct(
    private ProductService $productService
  ) {}

  public function index(Request $request)
  {
    $products = $this->productService->listPublic(
      $request->only(['q', 'category', 'min_price', 'max_price', 'sort'])
    );

    return $this->success([
      'items' => PublicProductResource::collection($products->items()),
      'meta' => [
        'current_page' => $products->currentPage(),
        'last_page' => $products->lastPage(),
        'per_page' => $products->perPage(),
        'total' => $products->total(),
      ],
    ]);
  }

  public function show(string $slug)
  {
    try {
      $product = $this->productService->showPublicBySlug($slug);
    } catch (ModelNotFoundException) {
      return $this->error('Product not found.', 404);
    }

    return $this->success(new PublicProductResource($product));
  }
}
