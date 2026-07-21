<?php

namespace Tests\Feature\Product;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProductTest extends TestCase
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

  public function test_public_index_only_returns_active_products_from_approved_vendors_and_active_categories(): void
  {
    $vendor = $this->approvedVendor();
    $suspendedVendor = Vendor::factory()->create(['status' => Vendor::STATUS_SUSPENDED]);
    $category = Category::factory()->create(['is_active' => true]);
    $inactiveCategory = Category::factory()->create(['is_active' => false]);

    Product::factory()->create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'status' => 'active']);
    Product::factory()->create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'status' => 'inactive']);
    Product::factory()->create(['vendor_id' => $suspendedVendor->id, 'category_id' => $category->id, 'status' => 'active']);
    Product::factory()->create(['vendor_id' => $vendor->id, 'category_id' => $inactiveCategory->id, 'status' => 'active']);

    $response = $this->getJson('/api/products');

    $response->assertOk()->assertJsonPath('data.meta.total', 1);
  }

  public function test_public_index_supports_search(): void
  {
    $vendor = $this->approvedVendor();
    $category = Category::factory()->create();
    Product::factory()->create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'iPhone 16 Pro']);
    Product::factory()->create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'Samsung Galaxy']);

    $response = $this->getJson('/api/products?q=iphone');

    $response->assertOk()
      ->assertJsonPath('data.meta.total', 1)
      ->assertJsonPath('data.items.0.name', 'iPhone 16 Pro');
  }

  public function test_public_index_supports_category_filter_by_slug(): void
  {
    $vendor = $this->approvedVendor();
    $electronics = Category::factory()->create(['slug' => 'electronics']);
    $fashion = Category::factory()->create(['slug' => 'fashion']);
    Product::factory()->create(['vendor_id' => $vendor->id, 'category_id' => $electronics->id]);
    Product::factory()->create(['vendor_id' => $vendor->id, 'category_id' => $fashion->id]);

    $response = $this->getJson('/api/products?category=electronics');

    $response->assertOk()
      ->assertJsonPath('data.meta.total', 1)
      ->assertJsonPath('data.items.0.category.slug', 'electronics');
  }

  public function test_public_index_supports_price_range_filter(): void
  {
    $vendor = $this->approvedVendor();
    $category = Category::factory()->create();
    Product::factory()->create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'Cheap Item', 'price' => 50]);
    Product::factory()->create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'Mid Item', 'price' => 500]);
    Product::factory()->create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'Expensive Item', 'price' => 5000]);

    $response = $this->getJson('/api/products?min_price=100&max_price=1000');

    $response->assertOk()
      ->assertJsonPath('data.meta.total', 1)
      ->assertJsonPath('data.items.0.name', 'Mid Item');
  }

  public function test_public_index_supports_sorting_by_price(): void
  {
    $vendor = $this->approvedVendor();
    $category = Category::factory()->create();
    Product::factory()->create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'Costly', 'price' => 900]);
    Product::factory()->create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'Budget', 'price' => 100]);

    $response = $this->getJson('/api/products?sort=price');

    $response->assertOk()
      ->assertJsonPath('data.items.0.name', 'Budget')
      ->assertJsonPath('data.items.1.name', 'Costly');
  }

  public function test_filters_combine_with_and(): void
  {
    $vendor = $this->approvedVendor();
    $electronics = Category::factory()->create(['slug' => 'electronics']);
    $fashion = Category::factory()->create(['slug' => 'fashion']);
    Product::factory()->create(['vendor_id' => $vendor->id, 'category_id' => $electronics->id, 'name' => 'Asus Laptop', 'price' => 1500]);
    Product::factory()->create(['vendor_id' => $vendor->id, 'category_id' => $fashion->id, 'name' => 'Asus Shoes', 'price' => 1500]);
    Product::factory()->create(['vendor_id' => $vendor->id, 'category_id' => $electronics->id, 'name' => 'Asus Cheap Mouse', 'price' => 50]);

    $response = $this->getJson('/api/products?category=electronics&q=asus&min_price=1000&max_price=2000');

    $response->assertOk()
      ->assertJsonPath('data.meta.total', 1)
      ->assertJsonPath('data.items.0.name', 'Asus Laptop');
  }

  public function test_show_by_slug_returns_nested_category_and_vendor(): void
  {
    $vendor = $this->approvedVendor();
    $category = Category::factory()->create();
    Product::factory()->create([
      'vendor_id' => $vendor->id,
      'category_id' => $category->id,
      'slug' => 'iphone-16-pro',
    ]);

    $response = $this->getJson('/api/products/iphone-16-pro');

    $response->assertOk()
      ->assertJsonPath('data.category.id', $category->id)
      ->assertJsonPath('data.vendor.store_name', $vendor->store_name);
  }

  public function test_show_returns_404_when_product_is_inactive(): void
  {
    $vendor = $this->approvedVendor();
    $category = Category::factory()->create();
    Product::factory()->create([
      'vendor_id' => $vendor->id,
      'category_id' => $category->id,
      'slug' => 'hidden-product',
      'status' => 'inactive',
    ]);

    $response = $this->getJson('/api/products/hidden-product');

    $response->assertStatus(404);
  }

  public function test_show_returns_404_when_vendor_is_suspended(): void
  {
    $vendor = Vendor::factory()->create(['status' => Vendor::STATUS_SUSPENDED]);
    $category = Category::factory()->create();
    Product::factory()->create([
      'vendor_id' => $vendor->id,
      'category_id' => $category->id,
      'slug' => 'suspended-vendor-product',
    ]);

    $response = $this->getJson('/api/products/suspended-vendor-product');

    $response->assertStatus(404);
  }

  public function test_show_returns_404_when_category_is_disabled(): void
  {
    $vendor = $this->approvedVendor();
    $category = Category::factory()->create(['is_active' => false]);
    Product::factory()->create([
      'vendor_id' => $vendor->id,
      'category_id' => $category->id,
      'slug' => 'disabled-category-product',
    ]);

    $response = $this->getJson('/api/products/disabled-category-product');

    $response->assertStatus(404);
  }
}
