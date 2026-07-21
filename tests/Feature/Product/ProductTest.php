<?php

namespace Tests\Feature\Product;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
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

    return Vendor::factory()->for($user)->create(['status' => Vendor::STATUS_APPROVED]);
  }

  private function customer(): User
  {
    $user = User::factory()->create();
    $user->assignRole('customer');

    return $user;
  }

  public function test_an_approved_vendor_can_create_a_product_with_an_auto_generated_slug(): void
  {
    $vendor = $this->approvedVendor();
    $category = Category::factory()->create();

    $response = $this->actingAs($vendor->user, 'sanctum')->postJson('/api/vendor/products', [
      'category_id' => $category->id,
      'name' => 'Gaming Laptop 15"',
      'price' => 25999.99,
      'stock' => 10,
    ]);

    $response->assertCreated()
      ->assertJsonPath('data.slug', 'gaming-laptop-15')
      ->assertJsonPath('data.status', 'active')
      ->assertJsonPath('data.vendor_id', $vendor->id)
      ->assertJsonPath('data.category.id', $category->id);

    $this->assertDatabaseHas('products', [
      'vendor_id' => $vendor->id,
      'name' => 'Gaming Laptop 15"',
    ]);
  }

  public function test_a_customer_cannot_create_a_product(): void
  {
    $category = Category::factory()->create();

    $response = $this->actingAs($this->customer(), 'sanctum')->postJson('/api/vendor/products', [
      'category_id' => $category->id,
      'name' => 'Should Fail',
      'price' => 10,
      'stock' => 5,
    ]);

    $response->assertForbidden();
  }

  public function test_price_must_be_greater_than_zero(): void
  {
    $vendor = $this->approvedVendor();
    $category = Category::factory()->create();

    $response = $this->actingAs($vendor->user, 'sanctum')->postJson('/api/vendor/products', [
      'category_id' => $category->id,
      'name' => 'Free Item',
      'price' => 0,
      'stock' => 5,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('price');
  }

  public function test_stock_cannot_be_negative(): void
  {
    $vendor = $this->approvedVendor();
    $category = Category::factory()->create();

    $response = $this->actingAs($vendor->user, 'sanctum')->postJson('/api/vendor/products', [
      'category_id' => $category->id,
      'name' => 'Negative Stock',
      'price' => 10,
      'stock' => -1,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('stock');
  }

  public function test_category_id_must_exist(): void
  {
    $vendor = $this->approvedVendor();

    $response = $this->actingAs($vendor->user, 'sanctum')->postJson('/api/vendor/products', [
      'category_id' => 999,
      'name' => 'Orphan Product',
      'price' => 10,
      'stock' => 5,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('category_id');
  }

  public function test_a_vendor_only_sees_their_own_products_in_the_index(): void
  {
    $vendorA = $this->approvedVendor();
    $vendorB = $this->approvedVendor();
    Product::factory()->count(2)->create(['vendor_id' => $vendorA->id]);
    Product::factory()->create(['vendor_id' => $vendorB->id]);

    $response = $this->actingAs($vendorA->user, 'sanctum')->getJson('/api/vendor/products');

    $response->assertOk()->assertJsonPath('data.meta.total', 2);
  }

  public function test_index_supports_search_and_status_filter(): void
  {
    $vendor = $this->approvedVendor();
    Product::factory()->create(['vendor_id' => $vendor->id, 'name' => 'Gaming Mouse', 'status' => 'active']);
    Product::factory()->create(['vendor_id' => $vendor->id, 'name' => 'Office Chair', 'status' => 'inactive']);

    $response = $this->actingAs($vendor->user, 'sanctum')
      ->getJson('/api/vendor/products?q=gaming&status=active');

    $response->assertOk()
      ->assertJsonPath('data.meta.total', 1)
      ->assertJsonPath('data.items.0.name', 'Gaming Mouse');
  }

  public function test_show_returns_a_single_product_with_nested_category(): void
  {
    $vendor = $this->approvedVendor();
    $category = Category::factory()->create();
    $product = Product::factory()->create(['vendor_id' => $vendor->id, 'category_id' => $category->id]);

    $response = $this->actingAs($vendor->user, 'sanctum')->getJson("/api/vendor/products/{$product->id}");

    $response->assertOk()->assertJsonPath('data.category.id', $category->id);
  }

  public function test_a_vendor_cannot_view_another_vendors_product(): void
  {
    $vendorA = $this->approvedVendor();
    $vendorB = $this->approvedVendor();
    $product = Product::factory()->create(['vendor_id' => $vendorB->id]);

    $response = $this->actingAs($vendorA->user, 'sanctum')->getJson("/api/vendor/products/{$product->id}");

    $response->assertForbidden();
  }

  public function test_a_vendor_can_update_their_own_product_and_the_slug_regenerates(): void
  {
    $vendor = $this->approvedVendor();
    $product = Product::factory()->create(['vendor_id' => $vendor->id, 'name' => 'Old Name', 'slug' => 'old-name']);

    $response = $this->actingAs($vendor->user, 'sanctum')
      ->putJson("/api/vendor/products/{$product->id}", ['name' => 'New Name']);

    $response->assertOk()->assertJsonPath('data.slug', 'new-name');
  }

  public function test_a_vendor_cannot_update_another_vendors_product(): void
  {
    $vendorA = $this->approvedVendor();
    $vendorB = $this->approvedVendor();
    $product = Product::factory()->create(['vendor_id' => $vendorB->id]);

    $response = $this->actingAs($vendorA->user, 'sanctum')
      ->putJson("/api/vendor/products/{$product->id}", ['name' => 'Hijacked']);

    $response->assertForbidden();
  }

  public function test_a_vendor_cannot_delete_another_vendors_product(): void
  {
    $vendorA = $this->approvedVendor();
    $vendorB = $this->approvedVendor();
    $product = Product::factory()->create(['vendor_id' => $vendorB->id]);

    $response = $this->actingAs($vendorA->user, 'sanctum')
      ->deleteJson("/api/vendor/products/{$product->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('products', ['id' => $product->id]);
  }

  public function test_a_vendor_can_delete_their_own_product(): void
  {
    $vendor = $this->approvedVendor();
    $product = Product::factory()->create(['vendor_id' => $vendor->id]);

    $response = $this->actingAs($vendor->user, 'sanctum')
      ->deleteJson("/api/vendor/products/{$product->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('products', ['id' => $product->id]);
  }
}
