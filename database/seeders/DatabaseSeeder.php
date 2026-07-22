<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

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
            Category::factory()->count(5)->create();
        }
    }
}
