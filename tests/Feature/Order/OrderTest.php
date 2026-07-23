<?php

namespace Tests\Feature\Order;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderTest extends TestCase
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
      'vendor_id' => $vendorId,
      'category_id' => $category->id,
      'status' => Product::STATUS_ACTIVE,
      'stock' => 10,
      'price' => 100,
    ], $overrides));
  }

  private function addToCart(User $customer, Product $product, int $quantity = 1): void
  {
    $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => $quantity,
    ])->assertCreated();
  }

  private function seedCartItem(User $customer, Product $product, int $quantity = 1): void
  {
    $cart = Cart::query()->firstOrCreate(['user_id' => $customer->id]);
    $cart->items()->create([
      'product_id' => $product->id,
      'quantity' => $quantity,
    ]);
  }

  private function checkout(User $customer): \Illuminate\Testing\TestResponse
  {
    return $this->actingAs($customer, 'sanctum')
      ->postJson('/api/checkout', [], [
        'Idempotency-Key' => (string) Str::uuid(),
      ]);
  }

  public function test_successful_checkout(): void
  {
    $customer = $this->customer();
    $productA = $this->availableProduct(['price' => 100]);
    $productB = $this->availableProduct(['price' => 50, 'vendor_id' => $this->approvedVendor()->id]);

    $this->addToCart($customer, $productA, 2);
    $this->addToCart($customer, $productB, 1);

    $response = $this->checkout($customer);

    $response->assertCreated()
      ->assertJsonPath('data.status', 'pending')
      ->assertJsonPath('data.payment_method', 'cod')
      ->assertJsonPath('data.payment_status', 'pending')
      ->assertJsonPath('data.subtotal', '250.00')
      ->assertJsonPath('data.total', '250.00')
      ->assertJsonCount(2, 'data.items');

    $this->assertDatabaseHas('orders', [
      'user_id' => $customer->id,
      'status' => Order::STATUS_PENDING,
      'subtotal' => 250,
      'total' => 250,
    ]);
  }

  public function test_checkout_with_empty_cart_is_rejected(): void
  {
    $customer = $this->customer();

    $response = $this->checkout($customer);

    $response->assertUnprocessable()
      ->assertJsonPath('message', 'Your cart is empty.');
  }

  public function test_checkout_rejects_inactive_product(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct(['status' => Product::STATUS_ACTIVE]);

    $this->addToCart($customer, $product);
    $product->update(['status' => Product::STATUS_INACTIVE]);

    $response = $this->checkout($customer);

    $response->assertUnprocessable()
      ->assertJsonPath('message', 'One or more products in your cart are no longer available.');
  }

  public function test_checkout_rejects_suspended_vendor(): void
  {
    $customer = $this->customer();
    $vendor = $this->approvedVendor();
    $product = $this->availableProduct(['vendor_id' => $vendor->id]);

    $this->addToCart($customer, $product);
    $vendor->update(['status' => Vendor::STATUS_SUSPENDED]);

    $response = $this->checkout($customer);

    $response->assertUnprocessable()
      ->assertJsonPath('message', 'One or more products in your cart are no longer available.');
  }

  public function test_checkout_rejects_disabled_category(): void
  {
    $customer = $this->customer();
    $category = Category::factory()->create(['is_active' => true]);
    $product = $this->availableProduct(['category_id' => $category->id]);

    $this->addToCart($customer, $product);
    $category->update(['is_active' => false]);

    $response = $this->checkout($customer);

    $response->assertUnprocessable()
      ->assertJsonPath('message', 'One or more products in your cart are no longer available.');
  }

  public function test_checkout_rejects_insufficient_stock(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct(['stock' => 2]);

    $this->seedCartItem($customer, $product, 5);

    $response = $this->checkout($customer);

    $response->assertUnprocessable()
      ->assertJsonPath('message', 'Insufficient stock for "' . $product->name . '". Available: 2.');
  }

  public function test_checkout_freezes_product_price(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct(['price' => 99.99]);

    $this->addToCart($customer, $product);
    $response = $this->checkout($customer);

    $response->assertCreated()
      ->assertJsonPath('data.items.0.unit_price', '99.99');

    $product->update(['price' => 199.99]);

    $orderId = $response->json('data.id');
    $this->actingAs($customer, 'sanctum')
      ->getJson("/api/orders/{$orderId}")
      ->assertOk()
      ->assertJsonPath('data.items.0.unit_price', '99.99');
  }

  public function test_checkout_freezes_product_name(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct(['name' => 'Original Name']);

    $this->addToCart($customer, $product);
    $response = $this->checkout($customer);

    $response->assertCreated()
      ->assertJsonPath('data.items.0.product_name', 'Original Name');

    $product->update(['name' => 'Renamed Product']);

    $orderId = $response->json('data.id');
    $this->actingAs($customer, 'sanctum')
      ->getJson("/api/orders/{$orderId}")
      ->assertOk()
      ->assertJsonPath('data.items.0.product_name', 'Original Name');
  }

  public function test_checkout_deducts_stock(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct(['stock' => 10]);

    $this->addToCart($customer, $product, 3);
    $this->checkout($customer)->assertCreated();

    $this->assertSame(7, $product->fresh()->stock);
  }

  public function test_cart_is_cleared_after_checkout(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct();

    $this->addToCart($customer, $product);
    $this->checkout($customer)->assertCreated();

    $cart = Cart::query()->where('user_id', $customer->id)->first();
    $this->assertSame(0, $cart->items()->count());

    $this->actingAs($customer, 'sanctum')
      ->getJson('/api/cart')
      ->assertOk()
      ->assertJsonPath('data.items_count', 0);
  }

  public function test_checkout_requires_idempotency_key(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct();
    $this->addToCart($customer, $product);

    $this->actingAs($customer, 'sanctum')
      ->postJson('/api/checkout')
      ->assertBadRequest();
  }

  public function test_customer_can_view_own_orders(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct();
    $this->addToCart($customer, $product);
    $checkout = $this->checkout($customer)->assertCreated();
    $orderId = $checkout->json('data.id');

    $this->actingAs($customer, 'sanctum')
      ->getJson('/api/orders')
      ->assertOk()
      ->assertJsonCount(1, 'data.items');

    $this->actingAs($customer, 'sanctum')
      ->getJson("/api/orders/{$orderId}")
      ->assertOk()
      ->assertJsonPath('data.id', $orderId);
  }

  public function test_customer_cannot_view_another_customers_orders(): void
  {
    $customerA = $this->customer();
    $customerB = $this->customer();
    $product = $this->availableProduct();

    $this->addToCart($customerA, $product);
    $orderId = $this->checkout($customerA)->json('data.id');

    $this->actingAs($customerB, 'sanctum')
      ->getJson("/api/orders/{$orderId}")
      ->assertForbidden();
  }

  public function test_customer_can_cancel_pending_order(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct();
    $this->addToCart($customer, $product);
    $orderId = $this->checkout($customer)->json('data.id');

    $this->actingAs($customer, 'sanctum')
      ->postJson("/api/orders/{$orderId}/cancel")
      ->assertOk()
      ->assertJsonPath('data.status', 'cancelled');
  }

  public function test_stock_is_restored_after_cancellation(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct(['stock' => 10]);

    $this->addToCart($customer, $product, 4);
    $orderId = $this->checkout($customer)->json('data.id');

    $this->assertSame(6, $product->fresh()->stock);

    $this->actingAs($customer, 'sanctum')
      ->postJson("/api/orders/{$orderId}/cancel")
      ->assertOk();

    $this->assertSame(10, $product->fresh()->stock);
  }

  public function test_vendor_can_view_orders_containing_their_products(): void
  {
    $customer = $this->customer();
    $vendorA = $this->approvedVendor();
    $vendorB = $this->approvedVendor();
    $productA = $this->availableProduct(['vendor_id' => $vendorA->id, 'price' => 100]);
    $productB = $this->availableProduct(['vendor_id' => $vendorB->id, 'price' => 50]);

    $this->addToCart($customer, $productA, 1);
    $this->addToCart($customer, $productB, 1);
    $orderId = $this->checkout($customer)->json('data.id');

    $this->actingAs($vendorA->user, 'sanctum')
      ->getJson('/api/vendor/orders')
      ->assertOk()
      ->assertJsonCount(1, 'data.items')
      ->assertJsonPath('data.items.0.id', $orderId);

    $this->actingAs($vendorA->user, 'sanctum')
      ->getJson("/api/vendor/orders/{$orderId}")
      ->assertOk()
      ->assertJsonPath('data.vendor_subtotal', 100)
      ->assertJsonCount(1, 'data.items')
      ->assertJsonPath('data.items.0.product_name', $productA->name);
  }

  public function test_vendor_cannot_access_unrelated_orders(): void
  {
    $customer = $this->customer();
    $vendorA = $this->approvedVendor();
    $vendorB = $this->approvedVendor();
    $product = $this->availableProduct(['vendor_id' => $vendorA->id]);

    $this->addToCart($customer, $product);
    $orderId = $this->checkout($customer)->json('data.id');

    $this->actingAs($vendorB->user, 'sanctum')
      ->getJson("/api/vendor/orders/{$orderId}")
      ->assertNotFound();
  }

  public function test_vendor_cannot_see_other_vendors_items_in_shared_order(): void
  {
    $customer = $this->customer();
    $vendorA = $this->approvedVendor();
    $vendorB = $this->approvedVendor();
    $productA = $this->availableProduct(['vendor_id' => $vendorA->id, 'name' => 'Vendor A Laptop']);
    $productB = $this->availableProduct(['vendor_id' => $vendorB->id, 'name' => 'Vendor B Keyboard']);

    $this->addToCart($customer, $productA);
    $this->addToCart($customer, $productB);
    $orderId = $this->checkout($customer)->json('data.id');

    $response = $this->actingAs($vendorA->user, 'sanctum')
      ->getJson("/api/vendor/orders/{$orderId}")
      ->assertOk();

    $itemNames = collect($response->json('data.items'))->pluck('product_name');

    $this->assertTrue($itemNames->contains('Vendor A Laptop'));
    $this->assertFalse($itemNames->contains('Vendor B Keyboard'));
    $this->assertArrayNotHasKey('total', $response->json('data'));
    $this->assertArrayNotHasKey('subtotal', $response->json('data'));
  }
}
