<?php

namespace Tests\Feature\Product;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
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

  public function test_a_vendor_can_upload_a_single_image_and_it_becomes_primary_automatically(): void
  {
    $vendor = $this->approvedVendor();
    $product = Product::factory()->create(['vendor_id' => $vendor->id]);

    $response = $this->actingAs($vendor->user, 'sanctum')->postJson(
      "/api/vendor/products/{$product->id}/images",
      ['images' => [UploadedFile::fake()->image('photo.jpg')]]
    );

    $response->assertCreated()->assertJsonPath('data.0.is_primary', true);

    $this->assertDatabaseHas('product_images', [
      'product_id' => $product->id,
      'is_primary' => true,
    ]);
  }

  public function test_a_vendor_can_upload_multiple_images_at_once(): void
  {
    $vendor = $this->approvedVendor();
    $product = Product::factory()->create(['vendor_id' => $vendor->id]);

    $response = $this->actingAs($vendor->user, 'sanctum')->postJson(
      "/api/vendor/products/{$product->id}/images",
      [
        'images' => [
          UploadedFile::fake()->image('front.jpg'),
          UploadedFile::fake()->image('back.jpg'),
        ],
      ]
    );

    $response->assertCreated();
    $this->assertDatabaseCount('product_images', 2);
  }

  public function test_uploading_a_new_image_as_primary_unsets_the_previous_primary(): void
  {
    $vendor = $this->approvedVendor();
    $product = Product::factory()->create(['vendor_id' => $vendor->id]);
    $oldPrimary = ProductImage::factory()->for($product)->primary()->create();

    $response = $this->actingAs($vendor->user, 'sanctum')->postJson(
      "/api/vendor/products/{$product->id}/images",
      [
        'images' => [UploadedFile::fake()->image('new.jpg')],
        'is_primary' => true,
      ]
    );

    $response->assertCreated()->assertJsonPath('data.0.is_primary', true);

    $this->assertDatabaseHas('product_images', ['id' => $oldPrimary->id, 'is_primary' => false]);
  }

  public function test_a_vendor_can_set_an_existing_image_as_primary(): void
  {
    $vendor = $this->approvedVendor();
    $product = Product::factory()->create(['vendor_id' => $vendor->id]);
    $first = ProductImage::factory()->for($product)->primary()->create();
    $second = ProductImage::factory()->for($product)->create();

    $response = $this->actingAs($vendor->user, 'sanctum')
      ->patchJson("/api/vendor/products/{$product->id}/images/{$second->id}/primary");

    $response->assertOk()->assertJsonPath('data.is_primary', true);

    $this->assertDatabaseHas('product_images', ['id' => $second->id, 'is_primary' => true]);
    $this->assertDatabaseHas('product_images', ['id' => $first->id, 'is_primary' => false]);
  }

  public function test_a_vendor_can_delete_an_image(): void
  {
    $vendor = $this->approvedVendor();
    $product = Product::factory()->create(['vendor_id' => $vendor->id]);
    $image = ProductImage::factory()->for($product)->primary()->create();

    $response = $this->actingAs($vendor->user, 'sanctum')
      ->deleteJson("/api/vendor/products/{$product->id}/images/{$image->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
  }

  public function test_deleting_the_primary_image_promotes_another_image_to_primary(): void
  {
    $vendor = $this->approvedVendor();
    $product = Product::factory()->create(['vendor_id' => $vendor->id]);
    $primary = ProductImage::factory()->for($product)->primary()->create();
    $other = ProductImage::factory()->for($product)->create();

    $this->actingAs($vendor->user, 'sanctum')
      ->deleteJson("/api/vendor/products/{$product->id}/images/{$primary->id}")
      ->assertOk();

    $this->assertDatabaseHas('product_images', ['id' => $other->id, 'is_primary' => true]);
  }

  public function test_a_vendor_cannot_upload_images_to_another_vendors_product(): void
  {
    $vendorA = $this->approvedVendor();
    $vendorB = $this->approvedVendor();
    $product = Product::factory()->create(['vendor_id' => $vendorB->id]);

    $response = $this->actingAs($vendorA->user, 'sanctum')->postJson(
      "/api/vendor/products/{$product->id}/images",
      ['images' => [UploadedFile::fake()->image('photo.jpg')]]
    );

    $response->assertForbidden();
  }

  public function test_a_vendor_cannot_delete_another_vendors_product_image(): void
  {
    $vendorA = $this->approvedVendor();
    $vendorB = $this->approvedVendor();
    $product = Product::factory()->create(['vendor_id' => $vendorB->id]);
    $image = ProductImage::factory()->for($product)->primary()->create();

    $response = $this->actingAs($vendorA->user, 'sanctum')
      ->deleteJson("/api/vendor/products/{$product->id}/images/{$image->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('product_images', ['id' => $image->id]);
  }

  public function test_a_customer_cannot_upload_product_images(): void
  {
    $vendor = $this->approvedVendor();
    $product = Product::factory()->create(['vendor_id' => $vendor->id]);

    $response = $this->actingAs($this->customer(), 'sanctum')->postJson(
      "/api/vendor/products/{$product->id}/images",
      ['images' => [UploadedFile::fake()->image('photo.jpg')]]
    );

    $response->assertForbidden();
  }

  public function test_image_upload_rejects_invalid_file_types(): void
  {
    $vendor = $this->approvedVendor();
    $product = Product::factory()->create(['vendor_id' => $vendor->id]);

    $response = $this->actingAs($vendor->user, 'sanctum')->postJson(
      "/api/vendor/products/{$product->id}/images",
      ['images' => [UploadedFile::fake()->create('document.pdf', 100)]]
    );

    $response->assertUnprocessable()->assertJsonValidationErrors('images.0');
  }

  public function test_image_upload_rejects_files_larger_than_2mb(): void
  {
    $vendor = $this->approvedVendor();
    $product = Product::factory()->create(['vendor_id' => $vendor->id]);

    $response = $this->actingAs($vendor->user, 'sanctum')->postJson(
      "/api/vendor/products/{$product->id}/images",
      ['images' => [UploadedFile::fake()->image('big.jpg')->size(3000)]]
    );

    $response->assertUnprocessable()->assertJsonValidationErrors('images.0');
  }
}
