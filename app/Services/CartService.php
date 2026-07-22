<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;

class CartService
{
  public function getOrCreate(User $user): Cart
  {
    return Cart::query()->firstOrCreate(['user_id' => $user->id]);
  }

  public function view(User $user): Cart
  {
    return $this->getOrCreate($user)->load('items.product');
  }

  public function addItem(User $user, int $productId, int $quantity): CartItem
  {
    $product = $this->findAvailableProduct($productId);

    if ($user->vendor && $product->vendor_id === $user->vendor->id) {
      throw new \DomainException('You cannot add your own product to the cart.');
    }

    $cart = $this->getOrCreate($user);

    $existing = $cart->items()->where('product_id', $product->id)->first();
    $requestedTotal = $quantity + ($existing?->quantity ?? 0);

    if ($requestedTotal > $product->stock) {
      throw new \DomainException('Requested quantity exceeds available stock.');
    }

    if ($existing) {
      $existing->update(['quantity' => $requestedTotal]);

      return $existing->fresh('product');
    }

    $item = $cart->items()->create([
      'product_id' => $product->id,
      'quantity' => $requestedTotal,
    ]);

    return $item->load('product');
  }

  public function updateItem(CartItem $item, int $quantity): CartItem
  {
    if ($quantity > $item->product->stock) {
      throw new \DomainException('Requested quantity exceeds available stock.');
    }

    $item->update(['quantity' => $quantity]);

    return $item->fresh('product');
  }

  public function removeItem(CartItem $item): void
  {
    $item->delete();
  }

  public function clear(User $user): void
  {
    $this->getOrCreate($user)->items()->delete();
  }

  /**
   * Same availability rule as the storefront: active product, approved
   * vendor, active category. Anything else doesn't exist as far as the cart
   * is concerned.
   */
  private function findAvailableProduct(int $productId): Product
  {
    $product = Product::query()
      ->where('id', $productId)
      ->where('status', Product::STATUS_ACTIVE)
      ->whereHas('vendor', fn(Builder $q) => $q->where('status', Vendor::STATUS_APPROVED))
      ->whereHas('category', fn(Builder $q) => $q->where('is_active', true))
      ->first();

    if (! $product) {
      throw new \DomainException('This product is not available.');
    }

    return $product;
  }
}
