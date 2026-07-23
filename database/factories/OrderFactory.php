<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
  protected $model = Order::class;

  public function definition(): array
  {
    $subtotal = fake()->randomFloat(2, 50, 5000);

    return [
      'order_number' => 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
      'user_id' => User::factory(),
      'status' => Order::STATUS_PENDING,
      'payment_method' => Order::PAYMENT_METHOD_COD,
      'payment_status' => Order::PAYMENT_STATUS_PENDING,
      'subtotal' => $subtotal,
      'total' => $subtotal,
    ];
  }
}
