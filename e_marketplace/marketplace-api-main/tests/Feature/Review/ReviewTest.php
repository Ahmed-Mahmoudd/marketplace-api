<?php

namespace Tests\Feature\Review;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewTest extends TestCase
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
   * Create a delivered order for the customer containing the product.
   */
  private function deliveredOrder(User $customer, Product $product): Order
  {
    $subtotal = round($product->price, 2);

    $order = Order::create([
      'order_number'   => 'ORD-REV-' . strtoupper(Str::random(6)),
      'user_id'        => $customer->id,
      'status'         => Order::STATUS_DELIVERED,
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
  // Index (public listing)
  // ---------------------------------------------------------------------------

  public function test_anyone_can_list_reviews_for_a_product(): void
  {
    $product  = $this->availableProduct();
    $customer = $this->customer();
    $this->deliveredOrder($customer, $product);

    Review::create([
      'user_id'    => $customer->id,
      'product_id' => $product->id,
      'rating'     => 4,
      'comment'    => 'Great product!',
    ]);

    $this->getJson("/api/products/{$product->slug}/reviews")
      ->assertOk()
      ->assertJsonCount(1, 'data.items')
      ->assertJsonPath('data.items.0.rating', 4);
  }

  public function test_listing_reviews_for_nonexistent_product_returns_404(): void
  {
    $this->getJson('/api/products/nonexistent-slug/reviews')
      ->assertNotFound();
  }

  // ---------------------------------------------------------------------------
  // Store
  // ---------------------------------------------------------------------------

  public function test_customer_with_delivered_order_can_submit_review(): void
  {
    $customer = $this->customer();
    $product  = $this->availableProduct();
    $this->deliveredOrder($customer, $product);

    $this->actingAs($customer, 'sanctum')
      ->postJson("/api/products/{$product->slug}/reviews", [
        'rating'  => 5,
        'comment' => 'Excellent!',
      ])
      ->assertStatus(201)
      ->assertJsonPath('data.rating', 5)
      ->assertJsonPath('data.comment', 'Excellent!');

    $this->assertDatabaseHas('reviews', [
      'user_id'    => $customer->id,
      'product_id' => $product->id,
      'rating'     => 5,
    ]);
  }

  public function test_review_without_comment_is_accepted(): void
  {
    $customer = $this->customer();
    $product  = $this->availableProduct();
    $this->deliveredOrder($customer, $product);

    $this->actingAs($customer, 'sanctum')
      ->postJson("/api/products/{$product->slug}/reviews", ['rating' => 3])
      ->assertStatus(201)
      ->assertJsonPath('data.comment', null);
  }

  public function test_customer_without_delivered_order_cannot_review(): void
  {
    $customer = $this->customer();
    $product  = $this->availableProduct();

    $this->actingAs($customer, 'sanctum')
      ->postJson("/api/products/{$product->slug}/reviews", ['rating' => 5])
      ->assertForbidden();
  }

  public function test_customer_with_only_pending_order_cannot_review(): void
  {
    $customer = $this->customer();
    $product  = $this->availableProduct();

    // Create a pending order (not delivered)
    $subtotal = round($product->price, 2);
    $order = Order::create([
      'order_number'   => 'ORD-PEND-' . strtoupper(Str::random(6)),
      'user_id'        => $customer->id,
      'status'         => Order::STATUS_PENDING,
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

    $this->actingAs($customer, 'sanctum')
      ->postJson("/api/products/{$product->slug}/reviews", ['rating' => 5])
      ->assertForbidden();
  }

  public function test_customer_cannot_review_same_product_twice(): void
  {
    $customer = $this->customer();
    $product  = $this->availableProduct();
    $this->deliveredOrder($customer, $product);

    Review::create([
      'user_id'    => $customer->id,
      'product_id' => $product->id,
      'rating'     => 4,
      'comment'    => 'First review',
    ]);

    $this->actingAs($customer, 'sanctum')
      ->postJson("/api/products/{$product->slug}/reviews", ['rating' => 5])
      ->assertUnprocessable();
  }

  public function test_review_rating_must_be_between_1_and_5(): void
  {
    $customer = $this->customer();
    $product  = $this->availableProduct();
    $this->deliveredOrder($customer, $product);

    $this->actingAs($customer, 'sanctum')
      ->postJson("/api/products/{$product->slug}/reviews", ['rating' => 6])
      ->assertUnprocessable();

    $this->actingAs($customer, 'sanctum')
      ->postJson("/api/products/{$product->slug}/reviews", ['rating' => 0])
      ->assertUnprocessable();
  }

  public function test_unauthenticated_user_cannot_submit_review(): void
  {
    $product = $this->availableProduct();

    $this->postJson("/api/products/{$product->slug}/reviews", ['rating' => 5])
      ->assertUnauthorized();
  }

  // ---------------------------------------------------------------------------
  // Update
  // ---------------------------------------------------------------------------

  public function test_user_can_update_own_review(): void
  {
    $customer = $this->customer();
    $product  = $this->availableProduct();
    $this->deliveredOrder($customer, $product);

    $review = Review::create([
      'user_id'    => $customer->id,
      'product_id' => $product->id,
      'rating'     => 3,
      'comment'    => 'Okay',
    ]);

    $this->actingAs($customer, 'sanctum')
      ->putJson("/api/reviews/{$review->id}", [
        'rating'  => 5,
        'comment' => 'Changed my mind — great!',
      ])
      ->assertOk()
      ->assertJsonPath('data.rating', 5)
      ->assertJsonPath('data.comment', 'Changed my mind — great!');
  }

  public function test_user_cannot_update_another_users_review(): void
  {
    $customer  = $this->customer();
    $customer2 = $this->customer();
    $product   = $this->availableProduct();
    $this->deliveredOrder($customer, $product);

    $review = Review::create([
      'user_id'    => $customer->id,
      'product_id' => $product->id,
      'rating'     => 3,
      'comment'    => 'Okay',
    ]);

    $this->actingAs($customer2, 'sanctum')
      ->putJson("/api/reviews/{$review->id}", ['rating' => 1])
      ->assertForbidden();
  }

  // ---------------------------------------------------------------------------
  // Delete
  // ---------------------------------------------------------------------------

  public function test_user_can_delete_own_review(): void
  {
    $customer = $this->customer();
    $product  = $this->availableProduct();
    $this->deliveredOrder($customer, $product);

    $review = Review::create([
      'user_id'    => $customer->id,
      'product_id' => $product->id,
      'rating'     => 4,
      'comment'    => 'Good',
    ]);

    $this->actingAs($customer, 'sanctum')
      ->deleteJson("/api/reviews/{$review->id}")
      ->assertOk();

    $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
  }

  public function test_user_cannot_delete_another_users_review(): void
  {
    $customer  = $this->customer();
    $customer2 = $this->customer();
    $product   = $this->availableProduct();
    $this->deliveredOrder($customer, $product);

    $review = Review::create([
      'user_id'    => $customer->id,
      'product_id' => $product->id,
      'rating'     => 4,
      'comment'    => 'Good',
    ]);

    $this->actingAs($customer2, 'sanctum')
      ->deleteJson("/api/reviews/{$review->id}")
      ->assertForbidden();
  }

  // ---------------------------------------------------------------------------
  // Product aggregate fields
  // ---------------------------------------------------------------------------

  public function test_product_detail_includes_average_rating_and_count(): void
  {
    $customer = $this->customer();
    $product  = $this->availableProduct();
    $this->deliveredOrder($customer, $product);

    Review::create([
      'user_id'    => $customer->id,
      'product_id' => $product->id,
      'rating'     => 4,
    ]);

    $response = $this->getJson("/api/products/{$product->slug}")
      ->assertOk()
      ->assertJsonPath('data.reviews_count', 1);

    // average_rating may be serialised as 4 or 4.0 depending on PHP version
    $this->assertEquals(4.0, (float) $response->json('data.average_rating'));
  }
}
