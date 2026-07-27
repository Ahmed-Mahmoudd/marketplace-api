<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Requests\Review\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Models\Review;
use App\Services\ProductService;
use App\Services\ReviewService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
  public function __construct(
    private ReviewService $reviewService,
    private ProductService $productService
  ) {}

  /**
   * GET /products/{slug}/reviews
   * Paginated list of reviews for a product.
   */
  public function index(string $slug)
  {
    try {
      $product = $this->productService->showPublicBySlug($slug);
    } catch (ModelNotFoundException) {
      return $this->error('Product not found.', 404);
    }

    $reviews = $this->reviewService->listForProduct($product);

    return $this->success([
      'items' => ReviewResource::collection($reviews->items()),
      'meta'  => [
        'current_page' => $reviews->currentPage(),
        'last_page'    => $reviews->lastPage(),
        'per_page'     => $reviews->perPage(),
        'total'        => $reviews->total(),
      ],
    ]);
  }

  /**
   * POST /products/{slug}/reviews
   * Create a review. Requires: delivered order containing the product.
   */
  public function store(StoreReviewRequest $request, string $slug)
  {
    try {
      $product = $this->productService->showPublicBySlug($slug);
    } catch (ModelNotFoundException) {
      return $this->error('Product not found.', 404);
    }

    $this->authorize('create', [Review::class, $product]);

    try {
      $review = $this->reviewService->createReview(
        $request->user(),
        $product,
        $request->validated()
      );
    } catch (\DomainException $e) {
      return $this->error($e->getMessage(), 422);
    }

    return $this->success(new ReviewResource($review), 'Review submitted.', 201);

  }

  /**
   * PUT /reviews/{review}
   * Update the authenticated user's own review.
   */
  public function update(UpdateReviewRequest $request, Review $review)
  {
    $this->authorize('update', $review);

    $review = $this->reviewService->updateReview($review, $request->validated());

    return $this->success(new ReviewResource($review), 'Review updated.');
  }

  /**
   * DELETE /reviews/{review}
   * Delete the authenticated user's own review.
   */
  public function destroy(Review $review)
  {
    $this->authorize('delete', $review);

    $this->reviewService->deleteReview($review);

    return $this->success(null, 'Review deleted.');
  }
}
