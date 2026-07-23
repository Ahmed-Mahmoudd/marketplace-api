<?php

namespace App\Providers;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;
use App\Policies\CartItemPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\VendorPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Vendor::class, VendorPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(CartItem::class, CartItemPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
    }
}
