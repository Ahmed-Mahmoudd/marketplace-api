<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@marketplace.test'],
            [
                'name' => 'Platform Admin',
                'password' => 'password',
            ]
        );

        $admin->assignRole('admin');

        // Vendor A
        $vendorAUser = User::firstOrCreate(
            ['email' => 'vendor-a@example.com'],
            [
                'name' => 'Vendor A',
                'password' => 'password',
            ]
        );

        $vendorAUser->assignRole('vendor');

        Vendor::firstOrCreate(
            ['user_id' => $vendorAUser->id],
            [
                'store_name' => 'Vendor A Store',
                'store_slug' => 'vendor-a-store',
                'status' => Vendor::STATUS_APPROVED,
                'approved_at' => now(),
                'commission_rate' => 10,
            ]
        );

        // Vendor B
        $vendorBUser = User::firstOrCreate(
            ['email' => 'vendor-b@example.com'],
            [
                'name' => 'Vendor B',
                'password' => 'password',
            ]
        );

        $vendorBUser->assignRole('vendor');

        Vendor::firstOrCreate(
            ['user_id' => $vendorBUser->id],
            [
                'store_name' => 'Vendor B Store',
                'store_slug' => 'vendor-b-store',
                'status' => Vendor::STATUS_APPROVED,
                'approved_at' => now(),
                'commission_rate' => 10,
            ]
        );

        // Customer
        $customer = User::firstOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Customer',
                'password' => 'password',
            ]
        );

        $customer->assignRole('customer');

        // Categories
        if (Category::count() == 0) {
            $categories = Category::factory()->count(5)->create();
        } else {
            $categories = Category::all();
        }

        // Products
        if (Product::count() == 0) {
            // Real placeholder JPEG bytes (not a renamed text file), so the
            // seeded products actually render an image in the storefront
            // instead of a broken <img> icon.
            $placeholder = file_get_contents(__DIR__ . '/assets/placeholder.jpg');

            $vendors = Vendor::all();
            foreach ($vendors as $vendor) {
                Product::factory()->count(5)->create([
                    'vendor_id' => $vendor->id,
                    'category_id' => fn() => $categories->random()->id,
                ])->each(function ($product) use ($placeholder) {
                    $path = "products/seed-{$product->id}.jpg";
                    Storage::disk('public')->put($path, $placeholder);

                    $product->images()->create([
                        'path' => $path,
                        'is_primary' => true,
                    ]);
                });
            }
        }
    }
}
