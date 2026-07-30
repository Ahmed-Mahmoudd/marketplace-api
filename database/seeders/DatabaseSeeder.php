<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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

        $vendorA = Vendor::firstOrCreate(
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

        $vendorB = Vendor::firstOrCreate(
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

        // Demo catalog: realistic categories/products with local presentation-ready images.
        $this->call(DemoCatalogSeeder::class);

        // Seed orders with various statuses for the customer
        if (Order::count() == 0) {
            $allProducts = Product::all();

            $statuses = [
                Order::STATUS_PENDING,
                Order::STATUS_CONFIRMED,
                Order::STATUS_PROCESSING,
                Order::STATUS_SHIPPED,
                Order::STATUS_CANCELLED,
            ];

            foreach ($statuses as $status) {
                $product = $allProducts->random();
                $unitPrice = $product->price;
                $quantity = 1;
                $subtotal = round($unitPrice * $quantity, 2);

                $order = Order::create([
                    'order_number'   => 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
                    'user_id'        => $customer->id,
                    'status'         => $status,
                    'payment_method' => Order::PAYMENT_METHOD_COD,
                    'payment_status' => Order::PAYMENT_STATUS_PENDING,
                    'subtotal'       => $subtotal,
                    'total'          => $subtotal,
                ]);

                $order->items()->create([
                    'product_id'   => $product->id,
                    'vendor_id'    => $product->vendor_id,
                    'product_name' => $product->name,
                    'unit_price'   => $unitPrice,
                    'quantity'     => $quantity,
                    'subtotal'     => $subtotal,
                ]);
            }

            // Delivered orders suitable for review testing
            $deliveredProducts = $allProducts->random(3);
            foreach ($deliveredProducts as $product) {
                $unitPrice = $product->price;
                $quantity = 1;
                $subtotal = round($unitPrice * $quantity, 2);

                $order = Order::create([
                    'order_number'   => 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
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
                    'unit_price'   => $unitPrice,
                    'quantity'     => $quantity,
                    'subtotal'     => $subtotal,
                ]);

                // Create a sample review for this delivered product
                if (! Review::where('user_id', $customer->id)->where('product_id', $product->id)->exists()) {
                    Review::create([
                        'user_id'    => $customer->id,
                        'product_id' => $product->id,
                        'rating'     => fake()->numberBetween(3, 5),
                        'comment'    => fake()->paragraph(),
                    ]);
                }
            }
        }
    }
}

