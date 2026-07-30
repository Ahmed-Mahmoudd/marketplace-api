<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
  protected $model = Review::class;

  public function definition(): array
  {
    return [
      'user_id'    => User::factory(),
      'product_id' => Product::factory(),
      'rating'     => fake()->numberBetween(1, 5),
      'comment'    => fake()->optional(0.8)->paragraph(),
    ];
  }
}
