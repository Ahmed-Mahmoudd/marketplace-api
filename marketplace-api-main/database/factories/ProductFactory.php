<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
  protected $model = Product::class;

  public function definition(): array
  {
    $name = fake()->unique()->words(3, true);

    return [
      'vendor_id' => Vendor::factory(),
      'category_id' => Category::factory(),
      'name' => ucfirst($name),
      'slug' => Str::slug($name),
      'description' => fake()->sentence(),
      'price' => fake()->randomFloat(2, 10, 5000),
      'stock' => fake()->numberBetween(0, 200),
      'status' => Product::STATUS_ACTIVE,
    ];
  }
}
