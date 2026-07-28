<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'Clothing' => [
                ['Classic Cotton T-Shirt', 'Astra', 29.99, 85, 'Soft everyday cotton T-shirt with a clean, versatile fit.', 'classic-cotton-t-shirt'],
                ['Everyday Hoodie', 'Everest', 54.99, 60, 'Comfortable fleece hoodie designed for everyday layering.', 'everyday-hoodie'],
                ['Urban Denim Jacket', 'Orion', 89.99, 42, 'Classic denim jacket with a modern urban fit.', 'urban-denim-jacket'],
            ],
            'Toys' => [
                ['Magnetic Building Blocks', 'NeoTech', 34.99, 75, 'Creative magnetic construction set for hands-on building and play.', 'magnetic-building-blocks'],
                ['Wooden Puzzle Set', 'Harbor', 24.99, 55, 'Colorful wooden puzzles that make screen-free play fun and engaging.', 'wooden-puzzle-set'],
                ['Remote Control Racer', 'Acme', 49.99, 38, 'Fast remote-control racer with responsive steering and durable wheels.', 'remote-control-racer'],
            ],
            'Pet Supplies' => [
                ['Cozy Pet Bed', 'Harbor', 39.99, 45, 'Soft, supportive pet bed designed for comfortable naps and rest.', 'cozy-pet-bed'],
                ['Interactive Feather Toy', 'Astra', 14.99, 90, 'Interactive feather toy that keeps cats engaged and active.', 'interactive-feather-toy'],
                ['Stainless Pet Bowl', 'Nimbus', 18.99, 70, 'Easy-clean stainless steel bowl for everyday feeding and hydration.', 'stainless-pet-bowl'],
            ],
            'Beauty' => [
                ['Vitamin C Face Serum', 'Nimbus', 28.99, 65, 'Lightweight daily serum with a brightening skincare routine focus.', 'vitamin-c-face-serum'],
                ['Daily Moisturizer', 'Astra', 22.99, 80, 'Gentle everyday moisturizer for a simple, comfortable skincare routine.', 'daily-moisturizer'],
                ['Hair Care Set', 'Zenith', 36.99, 50, 'A practical hair-care bundle for cleansing, conditioning, and daily care.', 'hair-care-set'],
            ],
            'Electronics' => [
                ['Pro Laptop 14', 'Nimbus', 899.99, 24, 'Slim 14-inch productivity laptop for work, study, and everyday computing.', 'pro-laptop-14'],
                ['Nova Smartphone', 'NeoTech', 649.99, 32, 'Modern smartphone with a bright display, reliable camera, and all-day battery.', 'nova-smartphone'],
                ['Wireless Headphones', 'Orion', 119.99, 48, 'Comfortable wireless headphones with rich sound and long listening time.', 'wireless-headphones'],
            ],
            'Home & Kitchen' => [
                ['BrewMaster Coffee Maker', 'Harbor', 79.99, 35, 'Countertop coffee maker for smooth, convenient morning brewing.', 'brewmaster-coffee-maker'],
                ['Air Fryer 5L', 'Nimbus', 99.99, 28, 'Large-capacity air fryer for quick everyday meals with less oil.', 'air-fryer-5l'],
                ['Ceramic Table Lamp', 'Astra', 44.99, 55, 'Minimal ceramic table lamp that adds warm ambient light to any room.', 'ceramic-table-lamp'],
            ],
            'Books' => [
                ['The Modern Home Handbook', 'Everest', 24.99, 30, 'Practical ideas for organizing, styling, and improving everyday home life.', 'modern-home-handbook'],
                ["Beginner's Coding Guide", 'NeoTech', 32.99, 40, 'Friendly introduction to programming concepts for new developers.', 'beginners-coding-guide'],
                ['The Art of Everyday Cooking', 'Harbor', 29.99, 35, 'Approachable recipes and techniques for confident home cooking.', 'art-of-everyday-cooking'],
            ],
            'Automotive' => [
                ['Car Phone Mount', 'Acme', 19.99, 70, 'Secure dashboard phone mount for safer navigation and hands-free driving.', 'car-phone-mount'],
                ['All-Season Floor Mats', 'Orion', 64.99, 25, 'Durable all-weather floor mats designed for everyday vehicle protection.', 'all-season-floor-mats'],
                ['Portable Tire Inflator', 'Nimbus', 59.99, 30, 'Compact electric tire inflator for convenient roadside and home use.', 'portable-tire-inflator'],
            ],
            'Groceries' => [
                ['Premium Arabica Coffee', 'Astra', 16.99, 100, 'Smooth roasted Arabica coffee for a rich and balanced daily cup.', 'premium-arabica-coffee'],
                ['Organic Honey Jar', 'Everest', 12.99, 85, 'Naturally sweet honey for breakfast, baking, and everyday recipes.', 'organic-honey-jar'],
                ['Dark Chocolate Bar', 'Orion', 7.99, 120, 'Rich dark chocolate bar made for an indulgent everyday treat.', 'dark-chocolate-bar'],
            ],
            'Sports' => [
                ['Performance Running Shoes', 'Acme', 94.99, 45, 'Lightweight running shoes with cushioned support for daily training.', 'performance-running-shoes'],
                ['Yoga Mat Pro', 'Zenith', 39.99, 60, 'Cushioned non-slip yoga mat for stretching, yoga, and home workouts.', 'yoga-mat-pro'],
                ['Insulated Sports Bottle', 'Nimbus', 24.99, 75, 'Double-wall insulated bottle for keeping drinks cool during workouts.', 'insulated-sports-bottle'],
            ],
        ];

        $categoryDescriptions = [
            'Clothing' => 'Everyday fashion, layers, and wardrobe essentials.',
            'Toys' => 'Fun, creative, and engaging products for playtime.',
            'Pet Supplies' => 'Comfort, feeding, and enrichment products for pets.',
            'Beauty' => 'Simple skincare and personal-care essentials.',
            'Electronics' => 'Modern devices and accessories for work and everyday life.',
            'Home & Kitchen' => 'Useful appliances, lighting, and kitchen essentials.',
            'Books' => 'Practical and entertaining reads for everyday interests.',
            'Automotive' => 'Useful accessories and equipment for drivers and vehicles.',
            'Groceries' => 'Everyday food and pantry favorites.',
            'Sports' => 'Gear and accessories for active lifestyles and workouts.',
        ];

        $categories = [];
        foreach ($categoryDescriptions as $name => $description) {
            $categories[$name] = Category::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $description,
                    'is_active' => true,
                ]
            );
        }

        // Keep the demo catalog deterministic and clean after migrate:fresh --seed.
        if (Product::count() > 0) {
            return;
        }

        $vendorA = \App\Models\Vendor::where('store_slug', 'vendor-a-store')->firstOrFail();
        $vendorB = \App\Models\Vendor::where('store_slug', 'vendor-b-store')->firstOrFail();
        $vendors = [$vendorA, $vendorB];
        $vendorIndex = 0;

        foreach ($catalog as $categoryName => $products) {
            foreach ($products as [$name, $brand, $price, $stock, $description, $imageSlug]) {
                $vendor = $vendors[$vendorIndex % count($vendors)];
                $vendorIndex++;

                $product = Product::create([
                    'vendor_id' => $vendor->id,
                    'category_id' => $categories[$categoryName]->id,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'description' => $description,
                    'price' => $price,
                    'stock' => $stock,
                    'status' => Product::STATUS_ACTIVE,
                ]);

                $source = __DIR__ . '/assets/products/' . $imageSlug . '.png';
                $path = 'products/demo/' . $imageSlug . '.png';
                Storage::disk('public')->put($path, file_get_contents($source));

                $product->images()->create([
                    'path' => $path,
                    'is_primary' => true,
                ]);
            }
        }
    }
}
