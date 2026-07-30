<?php

namespace Tests\Feature\Cart;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
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

  public function test_a_customer_can_add_a_product_to_their_cart(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct();

    $response = $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => 2,
    ]);

    $response->assertCreated()
      ->assertJsonPath('data.quantity', 2)
      ->assertJsonPath('data.unit_price', '100.00')
      ->assertJsonPath('data.subtotal', 200);

    $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);
  }

  public function test_adding_the_same_product_again_increases_quantity_instead_of_duplicating(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct(['stock' => 10]);

    $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => 2,
    ]);
    $response = $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => 3,
    ]);

    $response->assertCreated()->assertJsonPath('data.quantity', 5);
    $this->assertDatabaseCount('cart_items', 1);
  }

  public function test_adding_more_than_available_stock_is_rejected(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct(['stock' => 3]);

    $response = $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => 5,
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseCount('cart_items', 0);
  }

  public function test_cannot_add_an_inactive_product(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct(['status' => Product::STATUS_INACTIVE]);

    $response = $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => 1,
    ]);

    $response->assertStatus(422);
  }

  public function test_a_vendor_cannot_add_their_own_product_to_the_cart(): void
  {
    $vendor = $this->approvedVendor();
    $category = Category::factory()->create();
    $product = Product::factory()->create([
      'vendor_id' => $vendor->id,
      'category_id' => $category->id,
      'status' => Product::STATUS_ACTIVE,
      'stock' => 10,
    ]);

    $response = $this->actingAs($vendor->user, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => 1,
    ]);

    $response->assertStatus(422);
  }

  public function test_a_customer_can_update_item_quantity(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct(['stock' => 10]);

    $add = $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => 2,
    ]);
    $itemId = $add->json('data.id');

    $response = $this->actingAs($customer, 'sanctum')
      ->putJson("/api/cart/items/{$itemId}", ['quantity' => 5]);

    $response->assertOk()->assertJsonPath('data.quantity', 5);
  }

  public function test_updating_quantity_beyond_stock_is_rejected(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct(['stock' => 4]);

    $add = $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => 2,
    ]);
    $itemId = $add->json('data.id');

    $response = $this->actingAs($customer, 'sanctum')
      ->putJson("/api/cart/items/{$itemId}", ['quantity' => 10]);

    $response->assertStatus(422);
  }

  public function test_a_customer_can_remove_an_item(): void
  {
    $customer = $this->customer();
    $product = $this->availableProduct();

    $add = $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => 1,
    ]);
    $itemId = $add->json('data.id');

    $response = $this->actingAs($customer, 'sanctum')->deleteJson("/api/cart/items/{$itemId}");

    $response->assertOk();
    $this->assertDatabaseMissing('cart_items', ['id' => $itemId]);
  }

  public function test_a_customer_can_clear_their_cart(): void
  {
    $customer = $this->customer();
    $productA = $this->availableProduct();
    $productB = $this->availableProduct();

    $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $productA->id, 'quantity' => 1]);
    $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $productB->id, 'quantity' => 1]);

    $response = $this->actingAs($customer, 'sanctum')->deleteJson('/api/cart');

    $response->assertOk();
    $this->assertDatabaseCount('cart_items', 0);
  }

  public function test_a_customer_cannot_modify_another_customers_cart_item(): void
  {
    $customerA = $this->customer();
    $customerB = $this->customer();
    $product = $this->availableProduct();

    $add = $this->actingAs($customerA, 'sanctum')->postJson('/api/cart/items', [
      'product_id' => $product->id,
      'quantity' => 1,
    ]);
    $itemId = $add->json('data.id');

    $response = $this->actingAs($customerB, 'sanctum')
      ->putJson("/api/cart/items/{$itemId}", ['quantity' => 2]);

    $response->assertForbidden();
  }

  public function test_viewing_the_cart_returns_the_correct_total(): void
  {
    $customer = $this->customer();
    $productA = $this->availableProduct(['price' => 100]);
    $productB = $this->availableProduct(['price' => 250]);

    $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $productA->id, 'quantity' => 2]);
    $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $productB->id, 'quantity' => 1]);

    $response = $this->actingAs($customer, 'sanctum')->getJson('/api/cart');

    $response->assertOk()
      ->assertJsonCount(2, 'data.items')
      ->assertJsonPath('data.items_count', 2)
      ->assertJsonPath('data.total', 450);
  }
}
