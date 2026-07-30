<?php

namespace Tests\Feature\Order;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderStatusTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed(RoleSeeder::class);
  }

  private function customer(): User
  {
    $user = User::factory()->create();
    $user->assignRole('customer');
    return $user;
  }

  private function approvedVendor(): Vendor
  {
    $user = User::factory()->create();
    $user->assignRole('vendor');
    return Vendor::factory()->for($user)->create(['status' => Vendor::STATUS_APPROVED]);
  }

  private function availableProduct(array $overrides = []): Product
  {
    $vendorId = $overrides['vendor_id'] ?? $this->approvedVendor()->id;
    $category = Category::factory()->create(['is_active' => true]);

    return Product::factory()->create(array_merge([
      'vendor_id'   => $vendorId,
      'category_id' => $category->id,
      'status'      => Product::STATUS_ACTIVE,
      'stock'       => 10,
      'price'       => 100,
    ], $overrides));
  }

  /**
   * Helper: create an order with the given status and attach a product to it.
   */
  private function createOrder(User $customer, Product $product, string $status): Order
  {
    $subtotal = round($product->price * 1, 2);

    $order = Order::create([
      'order_number'   => 'ORD-TEST-' . strtoupper(Str::random(6)),
      'user_id'        => $customer->id,
      'status'         => $status,
      'payment_method' => Order::PAYMENT_METHOD_COD,
      'payment_status' => Order::PAYMENT_STATUS_PENDING,
      'subtotal'       => $subtotal,
      'total'          => $subtotal,
    ]);

    $order->items()->create([
      'product_id'   => $product->id,
      'vendor_id'    => $product->vendor_id,
      'product_name' => $product->name,
      'unit_price'   => $product->price,
      'quantity'     => 1,
      'subtotal'     => $subtotal,
    ]);

    return $order;
  }

  // ---------------------------------------------------------------------------
  // Vendor status update
  // ---------------------------------------------------------------------------

  public function test_vendor_can_confirm_pending_order(): void
  {
    $vendor  = $this->approvedVendor();
    $product = $this->availableProduct(['vendor_id' => $vendor->id, 'stock' => 10]);
    $customer = $this->customer();
    $order   = $this->createOrder($customer, $product, Order::STATUS_PENDING);

    $this->actingAs($vendor->user, 'sanctum')
      ->patchJson("/api/vendor/orders/{$order->id}/status", ['status' => Order::STATUS_CONFIRMED])
      ->assertOk()
      ->assertJsonPath('data.status', Order::STATUS_CONFIRMED);
  }

  public function test_vendor_can_advance_status_through_full_lifecycle(): void
  {
    $vendor  = $this->approvedVendor();
    $product = $this->availableProduct(['vendor_id' => $vendor->id]);
    $customer = $this->customer();
    $order   = $this->createOrder($customer, $product, Order::STATUS_PENDING);

    $lifecycle = [
      Order::STATUS_CONFIRMED,
      Order::STATUS_PROCESSING,
      Order::STATUS_SHIPPED,
      Order::STATUS_DELIVERED,
    ];

    foreach ($lifecycle as $nextStatus) {
      $this->actingAs($vendor->user, 'sanctum')
        ->patchJson("/api/vendor/orders/{$order->id}/status", ['status' => $nextStatus])
        ->assertOk()
        ->assertJsonPath('data.status', $nextStatus);
    }
  }

  public function test_vendor_cannot_skip_status(): void
  {
    $vendor  = $this->approvedVendor();
    $product = $this->availableProduct(['vendor_id' => $vendor->id]);
    $customer = $this->customer();
    $order   = $this->createOrder($customer, $product, Order::STATUS_PENDING);

    // Attempt to jump from pending → shipped (skipping confirmed & processing)
    $this->actingAs($vendor->user, 'sanctum')
      ->patchJson("/api/vendor/orders/{$order->id}/status", ['status' => Order::STATUS_SHIPPED])
      ->assertUnprocessable();
  }

  public function test_vendor_cannot_update_delivered_order(): void
  {
    $vendor  = $this->approvedVendor();
    $product = $this->availableProduct(['vendor_id' => $vendor->id]);
    $customer = $this->customer();
    $order   = $this->createOrder($customer, $product, Order::STATUS_DELIVERED);

    $this->actingAs($vendor->user, 'sanctum')
      ->patchJson("/api/vendor/orders/{$order->id}/status", ['status' => Order::STATUS_DELIVERED])
      ->assertUnprocessable();
  }

  public function test_vendor_cannot_update_status_of_unrelated_order(): void
  {
    $vendorA = $this->approvedVendor();
    $vendorB = $this->approvedVendor();
    $product = $this->availableProduct(['vendor_id' => $vendorA->id]);
    $customer = $this->customer();
    $order   = $this->createOrder($customer, $product, Order::STATUS_PENDING);

    $this->actingAs($vendorB->user, 'sanctum')
      ->patchJson("/api/vendor/orders/{$order->id}/status", ['status' => Order::STATUS_CONFIRMED])
      ->assertNotFound();
  }

  public function test_customer_cannot_update_order_status(): void
  {
    $vendor  = $this->approvedVendor();
    $product = $this->availableProduct(['vendor_id' => $vendor->id]);
    $customer = $this->customer();
    $order   = $this->createOrder($customer, $product, Order::STATUS_PENDING);

    $this->actingAs($customer, 'sanctum')
      ->patchJson("/api/vendor/orders/{$order->id}/status", ['status' => Order::STATUS_CONFIRMED])
      ->assertForbidden();
  }

  public function test_update_status_requires_valid_status(): void
  {
    $vendor  = $this->approvedVendor();
    $product = $this->availableProduct(['vendor_id' => $vendor->id]);
    $customer = $this->customer();
    $order   = $this->createOrder($customer, $product, Order::STATUS_PENDING);

    $this->actingAs($vendor->user, 'sanctum')
      ->patchJson("/api/vendor/orders/{$order->id}/status", ['status' => 'invalid-status'])
      ->assertUnprocessable();
  }

  // ---------------------------------------------------------------------------
  // Cancellation
  // ---------------------------------------------------------------------------

  public function test_customer_can_cancel_confirmed_order(): void
  {
    $vendor  = $this->approvedVendor();
    $product = $this->availableProduct(['vendor_id' => $vendor->id]);
    $customer = $this->customer();
    $order   = $this->createOrder($customer, $product, Order::STATUS_CONFIRMED);

    $this->actingAs($customer, 'sanctum')
      ->postJson("/api/orders/{$order->id}/cancel")
      ->assertOk()
      ->assertJsonPath('data.status', Order::STATUS_CANCELLED);
  }

  public function test_customer_cannot_cancel_processing_order(): void
  {
    $vendor  = $this->approvedVendor();
    $product = $this->availableProduct(['vendor_id' => $vendor->id]);
    $customer = $this->customer();
    $order   = $this->createOrder($customer, $product, Order::STATUS_PROCESSING);

    $this->actingAs($customer, 'sanctum')
      ->postJson("/api/orders/{$order->id}/cancel")
      ->assertForbidden();
  }

  public function test_customer_cannot_cancel_delivered_order(): void
  {
    $vendor  = $this->approvedVendor();
    $product = $this->availableProduct(['vendor_id' => $vendor->id]);
    $customer = $this->customer();
    $order   = $this->createOrder($customer, $product, Order::STATUS_DELIVERED);

    $this->actingAs($customer, 'sanctum')
      ->postJson("/api/orders/{$order->id}/cancel")
      ->assertForbidden();
  }

  public function test_stock_is_restored_when_vendor_cancels_via_status_update(): void
  {
    $vendor  = $this->approvedVendor();
    $product = $this->availableProduct(['vendor_id' => $vendor->id, 'stock' => 10]);
    $customer = $this->customer();
    $order   = $this->createOrder($customer, $product, Order::STATUS_PENDING);

    // Simulate stock deduction at checkout
    $product->decrement('stock', 1);
    $this->assertSame(9, $product->fresh()->stock);

    $this->actingAs($vendor->user, 'sanctum')
      ->patchJson("/api/vendor/orders/{$order->id}/status", ['status' => Order::STATUS_CONFIRMED])
      ->assertOk();

    // Cancel via vendor status update
    $this->actingAs($vendor->user, 'sanctum')
      ->patchJson("/api/vendor/orders/{$order->id}/status", ['status' => Order::STATUS_CANCELLED])
      ->assertOk()
      ->assertJsonPath('data.status', Order::STATUS_CANCELLED);

    // Stock should be restored
    $this->assertSame(10, $product->fresh()->stock);
  }

  public function test_unauthenticated_user_cannot_update_order_status(): void
  {
    $vendor  = $this->approvedVendor();
    $product = $this->availableProduct(['vendor_id' => $vendor->id]);
    $customer = $this->customer();
    $order   = $this->createOrder($customer, $product, Order::STATUS_PENDING);

    $this->patchJson("/api/vendor/orders/{$order->id}/status", ['status' => Order::STATUS_CONFIRMED])
      ->assertUnauthorized();
  }

  public function test_order_status_transitions_are_correctly_defined(): void
  {
    $order = new Order();
    $order->status = Order::STATUS_PENDING;
    $this->assertTrue($order->canTransitionTo(Order::STATUS_CONFIRMED));
    $this->assertTrue($order->canTransitionTo(Order::STATUS_CANCELLED));
    $this->assertFalse($order->canTransitionTo(Order::STATUS_SHIPPED));

    $order->status = Order::STATUS_CONFIRMED;
    $this->assertTrue($order->canTransitionTo(Order::STATUS_PROCESSING));
    $this->assertFalse($order->canTransitionTo(Order::STATUS_DELIVERED));

    $order->status = Order::STATUS_DELIVERED;
    $this->assertEmpty($order->getAllowedNextStatuses());
    $this->assertTrue($order->isInTerminalState());

    $order->status = Order::STATUS_CANCELLED;
    $this->assertEmpty($order->getAllowedNextStatuses());
    $this->assertTrue($order->isInTerminalState());
  }
}
