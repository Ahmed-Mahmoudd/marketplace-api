<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        $storeName = fake()->unique()->company();

        return [
            'user_id' => User::factory(),
            'store_name' => $storeName,
            'store_slug' => Str::slug($storeName).'-'.Str::lower(Str::random(6)),
            'status' => Vendor::STATUS_PENDING,
            'commission_rate' => 10.00,
            'payout_details' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => Vendor::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }
}
