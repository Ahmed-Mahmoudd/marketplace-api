<?php

namespace Tests\Feature\Vendor;

use App\Models\Category;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorAnalyticsTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed(RoleSeeder::class);
  }

  private function approvedVendor(): Vendor
  {
    $user = User::factory()->create();
    $user->assignRole('vendor');

    return Vendor::factory()->for($user)->create([
      'status' => Vendor::STATUS_APPROVED,
      'approved_at' => now(),
    ]);
  }

  /** An order containing one line item for the given vendor. */
  private function orderFor(Vendor $vendor, Product $product, User $buyer, int $quantity, string $status = Order::STATUS_DELIVERED): Order
  {
    $subtotal = round($product->price * $quantity, 2);

    $order = Order::factory()->create([
      'user_id'  => $buyer->id,
      'status'   => $status,
      'subtotal' => $subtotal,
      'total'    => $subtotal,
    ]);

    OrderItem::factory()->create([
      'order_id'     => $order->id,
      'product_id'   => $product->id,
      'vendor_id'    => $vendor->id,
      'product_name' => $product->name,
      'unit_price'   => $product->price,
      'quantity'     => $quantity,
      'subtotal'     => $subtotal,
    ]);

    return $order;
  }

  public function test_unauthenticated_users_cannot_read_analytics(): void
  {
    $this->getJson('/api/vendor/analytics/dataset')->assertUnauthorized();
  }

  public function test_a_customer_cannot_read_vendor_analytics(): void
  {
    $user = User::factory()->create();
    $user->assignRole('customer');

    $this->actingAs($user, 'sanctum')
      ->getJson('/api/vendor/analytics/dataset')
      ->assertForbidden();
  }

  public function test_a_vendor_receives_the_expected_table_contract(): void
  {
    $vendor = $this->approvedVendor();

    $response = $this->actingAs($vendor->user, 'sanctum')
      ->getJson('/api/vendor/analytics/dataset');

    $response->assertOk()
      ->assertJsonPath('data.vendor.id', $vendor->id)
      ->assertJsonStructure([
        'data' => [
          'vendor' => ['id', 'store_name', 'store_slug', 'status'],
          'generated_at',
          'counts',
          'tables' => ['products', 'customers', 'orders', 'order_items', 'reviews', 'events'],
        ],
      ]);
  }

  public function test_a_vendor_only_sees_their_own_products_and_orders(): void
  {
    $mine = $this->approvedVendor();
    $theirs = $this->approvedVendor();
    $category = Category::factory()->create();
    $buyer = User::factory()->create();

    $myProduct = Product::factory()->for($mine)->for($category)->create(['price' => 100]);
    $theirProduct = Product::factory()->for($theirs)->for($category)->create(['price' => 100]);

    $this->orderFor($mine, $myProduct, $buyer, 2);
    $this->orderFor($theirs, $theirProduct, $buyer, 3);

    $data = $this->actingAs($mine->user, 'sanctum')
      ->getJson('/api/vendor/analytics/dataset')
      ->assertOk()
      ->json('data.tables');

    $this->assertCount(1, $data['products']);
    $this->assertSame($myProduct->id, $data['products'][0]['product_id']);

    $this->assertCount(1, $data['order_items']);
    $this->assertSame($myProduct->id, $data['order_items'][0]['product_id']);

    $productIds = array_column($data['order_items'], 'product_id');
    $this->assertNotContains($theirProduct->id, $productIds);
  }

  /**
   * The important one: on a basket spanning two vendors, each vendor's
   * `total_amount` must be their own share, not the whole order total.
   */
  public function test_order_total_is_reduced_to_the_vendors_own_share(): void
  {
    $mine = $this->approvedVendor();
    $theirs = $this->approvedVendor();
    $category = Category::factory()->create();
    $buyer = User::factory()->create();

    $myProduct = Product::factory()->for($mine)->for($category)->create(['price' => 100]);
    $theirProduct = Product::factory()->for($theirs)->for($category)->create(['price' => 250]);

    // One order, two vendors: 200 of mine + 250 of theirs = 450 total.
    $order = Order::factory()->create([
      'user_id'  => $buyer->id,
      'status'   => Order::STATUS_DELIVERED,
      'subtotal' => 450,
      'total'    => 450,
    ]);

    OrderItem::factory()->create([
      'order_id' => $order->id, 'product_id' => $myProduct->id, 'vendor_id' => $mine->id,
      'product_name' => $myProduct->name, 'unit_price' => 100, 'quantity' => 2, 'subtotal' => 200,
    ]);

    OrderItem::factory()->create([
      'order_id' => $order->id, 'product_id' => $theirProduct->id, 'vendor_id' => $theirs->id,
      'product_name' => $theirProduct->name, 'unit_price' => 250, 'quantity' => 1, 'subtotal' => 250,
    ]);

    $orders = $this->actingAs($mine->user, 'sanctum')
      ->getJson('/api/vendor/analytics/dataset')
      ->assertOk()
      ->json('data.tables.orders');

    $this->assertCount(1, $orders);
    $this->assertEquals(200, $orders[0]['total_amount'], 'Vendor saw the whole basket, not their share.');

    $theirOrders = $this->actingAs($theirs->user, 'sanctum')
      ->getJson('/api/vendor/analytics/dataset')
      ->assertOk()
      ->json('data.tables.orders');

    $this->assertEquals(250, $theirOrders[0]['total_amount']);
  }

  public function test_products_carry_a_derived_rating_and_the_store_name_as_brand(): void
  {
    $vendor = $this->approvedVendor();
    $category = Category::factory()->create();
    $product = Product::factory()->for($vendor)->for($category)->create();

    Review::factory()->create(['product_id' => $product->id, 'rating' => 5]);
    Review::factory()->create(['product_id' => $product->id, 'rating' => 3]);

    $products = $this->actingAs($vendor->user, 'sanctum')
      ->getJson('/api/vendor/analytics/dataset')
      ->assertOk()
      ->json('data.tables.products');

    $this->assertEquals(4.0, $products[0]['rating']);
    $this->assertSame(2, $products[0]['review_count']);
    $this->assertSame($vendor->store_name, $products[0]['brand']);
    $this->assertSame($category->name, $products[0]['category']);
  }

  public function test_a_product_without_reviews_has_a_null_rating(): void
  {
    $vendor = $this->approvedVendor();
    Product::factory()->for($vendor)->for(Category::factory())->create();

    $products = $this->actingAs($vendor->user, 'sanctum')
      ->getJson('/api/vendor/analytics/dataset')
      ->assertOk()
      ->json('data.tables.products');

    $this->assertNull($products[0]['rating']);
    $this->assertSame(0, $products[0]['review_count']);
  }

  public function test_events_are_scoped_to_the_vendor(): void
  {
    $mine = $this->approvedVendor();
    $theirs = $this->approvedVendor();
    $category = Category::factory()->create();

    $myProduct = Product::factory()->for($mine)->for($category)->create();
    $theirProduct = Product::factory()->for($theirs)->for($category)->create();

    Event::create(['product_id' => $myProduct->id, 'vendor_id' => $mine->id, 'type' => Event::TYPE_VIEW]);
    Event::create(['product_id' => $theirProduct->id, 'vendor_id' => $theirs->id, 'type' => Event::TYPE_VIEW]);

    $events = $this->actingAs($mine->user, 'sanctum')
      ->getJson('/api/vendor/analytics/dataset')
      ->assertOk()
      ->json('data.tables.events');

    $this->assertCount(1, $events);
    $this->assertSame($myProduct->id, $events[0]['product_id']);
  }

  public function test_the_tables_parameter_restricts_the_payload(): void
  {
    $vendor = $this->approvedVendor();

    $data = $this->actingAs($vendor->user, 'sanctum')
      ->getJson('/api/vendor/analytics/dataset?tables[]=products&tables[]=orders')
      ->assertOk()
      ->json('data.tables');

    $this->assertEqualsCanonicalizing(['products', 'orders'], array_keys($data));
  }

  public function test_an_unknown_table_is_rejected(): void
  {
    $vendor = $this->approvedVendor();

    $this->actingAs($vendor->user, 'sanctum')
      ->getJson('/api/vendor/analytics/dataset?tables[]=passwords')
      ->assertStatus(422);
  }

  public function test_the_since_filter_excludes_older_orders(): void
  {
    $vendor = $this->approvedVendor();
    $category = Category::factory()->create();
    $buyer = User::factory()->create();
    $product = Product::factory()->for($vendor)->for($category)->create(['price' => 50]);

    $old = $this->orderFor($vendor, $product, $buyer, 1);
    // forceFill, not update(): created_at isn't in Order::$fillable, so mass
    // assignment would silently drop it and leave the order dated today.
    $old->forceFill(['created_at' => now()->subMonths(6)])->save();

    $this->orderFor($vendor, $product, $buyer, 2);

    $orders = $this->actingAs($vendor->user, 'sanctum')
      ->getJson('/api/vendor/analytics/dataset?since=' . now()->subMonth()->toDateString())
      ->assertOk()
      ->json('data.tables.orders');

    $this->assertCount(1, $orders);
    $this->assertEquals(100, $orders[0]['total_amount']);
  }

  public function test_a_pending_vendor_cannot_read_analytics(): void
  {
    $user = User::factory()->create();
    $user->assignRole('vendor');
    Vendor::factory()->for($user)->create(['status' => Vendor::STATUS_PENDING]);

    $this->actingAs($user, 'sanctum')
      ->getJson('/api/vendor/analytics/dataset')
      ->assertForbidden();
  }
}
