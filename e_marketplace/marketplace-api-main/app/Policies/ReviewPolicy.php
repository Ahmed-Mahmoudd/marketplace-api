<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
  /**
   * A customer can create a review only if they have a delivered order
   * containing the product. Duplicate prevention is enforced in the service.
   */
  public function create(User $user, Product $product): bool
  {
    return Order::query()
      ->where('user_id', $user->id)
      ->where('status', Order::STATUS_DELIVERED)
      ->whereHas('items', fn($q) => $q->where('product_id', $product->id))
      ->exists();
  }

  /** User can update their own review. */
  public function update(User $user, Review $review): bool
  {
    return $user->id === $review->user_id;
  }

  /** User can delete their own review. */
  public function delete(User $user, Review $review): bool
  {
    return $user->id === $review->user_id;
  }
}
