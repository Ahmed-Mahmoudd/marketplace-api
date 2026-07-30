<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReviewService
{
  private const PER_PAGE = 15;

  /**
   * Determine if the user has a delivered order that includes this product.
   */
  public function hasPurchased(User $user, Product $product): bool
  {
    return Order::query()
      ->where('user_id', $user->id)
      ->where('status', Order::STATUS_DELIVERED)
      ->whereHas('items', fn($q) => $q->where('product_id', $product->id))
      ->exists();
  }

  /**
   * Determine if the user has already submitted a review for this product.
   */
  public function hasReviewed(User $user, Product $product): bool
  {
    return Review::query()
      ->where('user_id', $user->id)
      ->where('product_id', $product->id)
      ->exists();
  }

  /**
   * Return the user's existing review for a product, if any.
   */
  public function findReview(User $user, Product $product): ?Review
  {
    return Review::query()
      ->where('user_id', $user->id)
      ->where('product_id', $product->id)
      ->with('user')
      ->first();
  }

  /**
   * Create a new review. Assumes eligibility and uniqueness are already
   * verified (via policy + duplicate check).
   */
  public function createReview(User $user, Product $product, array $data): Review
  {
    if ($this->hasReviewed($user, $product)) {
      throw new \DomainException('You have already reviewed this product.');
    }

    $review = Review::create([
      'user_id'    => $user->id,
      'product_id' => $product->id,
      'rating'     => $data['rating'],
      'comment'    => $data['comment'] ?? null,
    ]);

    return $review->load('user');
  }

  /**
   * Update an existing review.
   */
  public function updateReview(Review $review, array $data): Review
  {
    $review->update(array_filter([
      'rating'  => $data['rating']  ?? $review->rating,
      'comment' => array_key_exists('comment', $data) ? $data['comment'] : $review->comment,
    ], fn($v) => true)); // allow null comment to pass through

    // Use explicit update to allow null comment
    $attributes = [];

    if (isset($data['rating'])) {
      $attributes['rating'] = $data['rating'];
    }

    if (array_key_exists('comment', $data)) {
      $attributes['comment'] = $data['comment'];
    }

    if (! empty($attributes)) {
      $review->update($attributes);
    }

    return $review->fresh('user');
  }

  /**
   * Delete a review.
   */
  public function deleteReview(Review $review): void
  {
    $review->delete();
  }

  /**
   * Paginated list of reviews for a product, with user eager loaded.
   */
  public function listForProduct(Product $product): LengthAwarePaginator
  {
    return Review::query()
      ->where('product_id', $product->id)
      ->with('user')
      ->latest()
      ->paginate(self::PER_PAGE);
  }
}
