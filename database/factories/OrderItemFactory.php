<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
  protected $model = OrderItem::class;

  public function definition(): array
  {
    $product = Product::factory()->create();
    $quantity = fake()->numberBetween(1, 5);
    $unitPrice = $product->price;

    return [
      'order_id' => Order::factory(),
      'product_id' => $product->id,
      'vendor_id' => $product->vendor_id,
      'product_name' => $product->name,
      'unit_price' => $unitPrice,
      'quantity' => $quantity,
      'subtotal' => round($unitPrice * $quantity, 2),
    ];
  }
}
