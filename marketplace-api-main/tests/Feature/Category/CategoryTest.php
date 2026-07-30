<?php

namespace Tests\Feature\Category;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed(RoleSeeder::class);
  }

  private function admin(): User
  {
    $user = User::factory()->create();
    $user->assignRole('admin');
    return $user;
  }

  private function customer(): User
  {
    $user = User::factory()->create();
    $user->assignRole('customer');
    return $user;
  }

  public function test_listing_only_returns_active_categories_and_is_paginated(): void
  {
    Category::factory()->count(3)->create(['is_active' => true]);
    Category::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/categories');

    $response->assertOk()
      ->assertJsonCount(3, 'data.items')
      ->assertJsonPath('data.meta.total', 3);
  }

  public function test_listing_supports_search(): void
  {
    Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
    Category::factory()->create(['name' => 'Fashion', 'slug' => 'fashion']);

    $response = $this->getJson('/api/categories?q=elec');

    $response->assertOk()
      ->assertJsonCount(1, 'data.items')
      ->assertJsonPath('data.items.0.slug', 'electronics');
  }

  public function test_listing_supports_sorting_by_name(): void
  {
    Category::factory()->create(['name' => 'Zebra Gear']);
    Category::factory()->create(['name' => 'Alpha Gear']);

    $response = $this->getJson('/api/categories?sort=name');

    $response->assertOk()
      ->assertJsonPath('data.items.0.name', 'Alpha Gear')
      ->assertJsonPath('data.items.1.name', 'Zebra Gear');
  }

  public function test_anyone_can_view_a_single_category_by_slug(): void
  {
    Category::factory()->create(['name' => 'Gaming Laptops', 'slug' => 'gaming-laptops']);

    $response = $this->getJson('/api/categories/gaming-laptops');

    $response->assertOk()->assertJsonPath('data.slug', 'gaming-laptops');
  }

  public function test_an_inactive_category_is_not_publicly_visible(): void
  {
    Category::factory()->create(['slug' => 'discontinued', 'is_active' => false]);

    $response = $this->getJson('/api/categories/discontinued');

    $response->assertStatus(404);
  }

  public function test_viewing_a_missing_category_returns_404(): void
  {
    $response = $this->getJson('/api/categories/does-not-exist');

    $response->assertStatus(404);
  }

  public function test_an_admin_can_create_a_category_with_an_auto_generated_slug(): void
  {
    $response = $this->actingAs($this->admin(), 'sanctum')
      ->postJson('/api/admin/categories', ['name' => 'Gaming Laptops']);

    $response->assertCreated()
      ->assertJsonPath('data.slug', 'gaming-laptops')
      ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('categories', [
      'name' => 'Gaming Laptops',
      'slug' => 'gaming-laptops',
    ]);
  }

  public function test_a_non_admin_cannot_create_a_category(): void
  {
    $response = $this->actingAs($this->customer(), 'sanctum')
      ->postJson('/api/admin/categories', ['name' => 'Gaming Laptops']);

    $response->assertForbidden();
  }

  public function test_category_name_must_be_unique(): void
  {
    Category::factory()->create(['name' => 'Gaming Laptops']);

    $response = $this->actingAs($this->admin(), 'sanctum')
      ->postJson('/api/admin/categories', ['name' => 'Gaming Laptops']);

    $response->assertUnprocessable()->assertJsonValidationErrors('name');
  }

  public function test_an_admin_can_update_a_category_and_the_slug_regenerates(): void
  {
    $category = Category::factory()->create(['name' => 'Gaming', 'slug' => 'gaming']);

    $response = $this->actingAs($this->admin(), 'sanctum')
      ->putJson("/api/admin/categories/{$category->slug}", ['name' => 'Gaming Consoles']);

    $response->assertOk()->assertJsonPath('data.slug', 'gaming-consoles');
  }

  public function test_an_admin_can_disable_a_category_instead_of_deleting_it(): void
  {
    $category = Category::factory()->create(['is_active' => true]);

    $response = $this->actingAs($this->admin(), 'sanctum')
      ->putJson("/api/admin/categories/{$category->slug}", ['is_active' => false]);

    $response->assertOk()->assertJsonPath('data.is_active', false);
    $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_active' => false]);
  }

  public function test_an_admin_can_delete_a_category_with_no_products(): void
  {
    $category = Category::factory()->create();

    $response = $this->actingAs($this->admin(), 'sanctum')
      ->deleteJson("/api/admin/categories/{$category->slug}");

    $response->assertOk();
    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
  }

  public function test_deleting_a_category_with_products_is_blocked(): void
  {
    $category = Category::factory()->create();
    Product::factory()->create(['category_id' => $category->id]);

    $response = $this->actingAs($this->admin(), 'sanctum')
      ->deleteJson("/api/admin/categories/{$category->slug}");

    $response->assertStatus(422);
    $this->assertDatabaseHas('categories', ['id' => $category->id]);
  }
}
