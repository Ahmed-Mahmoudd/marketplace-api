<?php

namespace Tests\Feature\Regression;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression tests for the "missing eager load" class of bug found and
 * fixed in this session: CartService, OrderService, and
 * Api\Vendor\ProductController were all returning products/orders without
 * loading every relation their JSON Resource actually needs
 * (category / vendor / images), so those fields silently came back empty
 * instead of erroring — easy to ship unnoticed. Each test here asserts the
 * *nested* fields are present, not just that the request succeeds.
 *
 * Not covered here (frontend-only, can't be tested via PHP feature tests):
 * the cross-account React Query cache leak fixed in AuthContext.tsx
 * (queryClient.clear() on login/register/logout). That one needs a manual
 * browser check: log in as one role, log out, log in as another, and
 * confirm no leftover data from the previous session appears.
 */
class EagerLoadedRelationsTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
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

  private function assertProductPayloadIsComplete(array $product): void
  {
    $this->assertArrayHasKey('category', $product);
    $this->assertArrayHasKey('vendor', $product);
    $this->assertArrayHasKey('images', $product);
    $this->assertNotEmpty($product['category'], 'product.category should not be empty');
    $this->assertNotEmpty($product['vendor'], 'product.vendor should not be empty');
  }

  // -------------------------------------------------------------------
  // Customer role — cart (CartService)
  // -------------------------------------------------------------------

  public function test_viewing_the_cart_returns_full_product_details(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct();

    $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => 1,
    ])->assertCreated();

    $response = $this->actingAs($customer, 'sanctum')->getJson('/api/cart');

    $response->assertOk();
    $this->assertProductPayloadIsComplete($response->json('data.items.0.product'));
  }

  public function test_adding_to_cart_returns_full_product_details_immediately(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct();

    $response = $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => 1,
    ]);

    $response->assertCreated();
    $this->assertProductPayloadIsComplete($response->json('data.product'));
  }

  public function test_updating_cart_item_quantity_returns_full_product_details(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct(['stock' => 10]);

    $add = $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => 1,
    ]);
    $itemId = $add->json('data.id');

    $response = $this->actingAs($customer, 'sanctum')
      ->putJson("/api/cart/items/{$itemId}", ['quantity' => 3]);

    $response->assertOk();
    $this->assertProductPayloadIsComplete($response->json('data.product'));
  }

  // -------------------------------------------------------------------
  // Customer role — orders (OrderService)
  // -------------------------------------------------------------------

  private function checkout(User $customer): \Illuminate\Testing\TestResponse
  {
    return $this->actingAs($customer, 'sanctum')
      ->postJson('/api/checkout', [], [
        'Idempotency-Key' => (string) Str::uuid(),
      ]);
  }

  public function test_checkout_response_returns_full_product_details_per_item(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct(['price' => 100]);

    $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => 2,
    ])->assertCreated();

    $response = $this->checkout($customer);

    $response->assertCreated();
    $this->assertProductPayloadIsComplete($response->json('data.items.0.product'));
  }

  public function test_order_list_returns_full_product_details_per_item(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct();

    $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => 1,
    ])->assertCreated();
    $this->checkout($customer)->assertCreated();

    $response = $this->actingAs($customer, 'sanctum')->getJson('/api/orders');

    $response->assertOk();
    $this->assertProductPayloadIsComplete($response->json('data.items.0.items.0.product'));
  }

  public function test_order_detail_returns_full_product_details_per_item(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct();

    $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => 1,
    ])->assertCreated();
    $orderId = $this->checkout($customer)->assertCreated()->json('data.id');

    $response = $this->actingAs($customer, 'sanctum')->getJson("/api/orders/{$orderId}");

    $response->assertOk();
    $this->assertProductPayloadIsComplete($response->json('data.items.0.product'));
  }

  public function test_cancelling_an_order_returns_full_product_details_per_item(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct();

    $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => 1,
    ])->assertCreated();
    $orderId = $this->checkout($customer)->assertCreated()->json('data.id');

    $response = $this->actingAs($customer, 'sanctum')->postJson("/api/orders/{$orderId}/cancel");

    $response->assertOk();
    $this->assertProductPayloadIsComplete($response->json('data.items.0.product'));
  }

  // -------------------------------------------------------------------
  // Vendor role — vendor order views (OrderService)
  // -------------------------------------------------------------------

  public function test_vendor_order_list_returns_full_product_details_per_item(): void
  {
    $vendor = $this->approvedVendor();
    $product = $this->availableProduct(['vendor_id' => $vendor->id]);
    $customer = $this->customer();

    $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => 1,
    ])->assertCreated();
    $this->checkout($customer)->assertCreated();

    $response = $this->actingAs($vendor->user, 'sanctum')->getJson('/api/vendor/orders');

    $response->assertOk();
    $this->assertProductPayloadIsComplete($response->json('data.items.0.items.0.product'));
  }

  public function test_vendor_order_detail_returns_full_product_details_per_item(): void
  {
    $vendor = $this->approvedVendor();
    $product = $this->availableProduct(['vendor_id' => $vendor->id]);
    $customer = $this->customer();

    $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => 1,
    ])->assertCreated();
    $orderId = $this->checkout($customer)->assertCreated()->json('data.id');

    $response = $this->actingAs($vendor->user, 'sanctum')->getJson("/api/vendor/orders/{$orderId}");

    $response->assertOk();
    $this->assertProductPayloadIsComplete($response->json('data.items.0.product'));
  }

  // -------------------------------------------------------------------
  // Vendor role — single product view (Api\Vendor\ProductController)
  // -------------------------------------------------------------------

  public function test_fetching_a_single_vendor_product_includes_its_uploaded_images(): void
  {
    $vendor = $this->approvedVendor();
    $product = $this->availableProduct(['vendor_id' => $vendor->id]);

    $this->actingAs($vendor->user, 'sanctum')->postJson(
      "/api/vendor/products/{$product->id}/images",
      ['images' => [UploadedFile::fake()->image('photo.jpg')]],
    )->assertCreated();

    // This is the exact regression: before the fix, GET show() never
    // loaded the images relation, so this always came back empty even
    // though the upload above succeeded.
    $response = $this->actingAs($vendor->user, 'sanctum')
      ->getJson("/api/vendor/products/{$product->id}");

    $response->assertOk();
    $this->assertNotEmpty(
      $response->json('data.images'),
      'GET /vendor/products/{id} should include previously uploaded images',
    );
  }
}
