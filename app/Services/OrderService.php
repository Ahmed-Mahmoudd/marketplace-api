<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
  private const PER_PAGE = 15;

  public function checkout(User $user): Order
  {
    $cart = Cart::query()
      ->where('user_id', $user->id)
      ->with('items.product.vendor', 'items.product.category')
      ->first();

    if (! $cart || $cart->items->isEmpty()) {
      throw new \DomainException('Your cart is empty.');
    }

    foreach ($cart->items as $item) {
      $this->assertProductAvailable($item->product);
      $this->assertStockAvailable($item->product, $item->quantity);
    }

    return DB::transaction(function () use ($user, $cart) {
      $lineItems = [];

      foreach ($cart->items as $item) {
        $product = Product::query()
          ->whereKey($item->product_id)
          ->lockForUpdate()
          ->first();

        $this->assertProductAvailable($product);
        $this->assertStockAvailable($product, $item->quantity);

        $lineItems[] = [
          'product' => $product,
          'quantity' => $item->quantity,
        ];
      }

      $subtotal = collect($lineItems)->sum(
        fn(array $line) => round($line['product']->price * $line['quantity'], 2)
      );

      $order = Order::create([
        'order_number' => $this->uniqueOrderNumber(),
        'user_id' => $user->id,
        'status' => Order::STATUS_PENDING,
        'payment_method' => Order::PAYMENT_METHOD_COD,
        'payment_status' => Order::PAYMENT_STATUS_PENDING,
        'subtotal' => $subtotal,
        'total' => $subtotal,
      ]);

      foreach ($lineItems as $line) {
        $product = $line['product'];
        $quantity = $line['quantity'];
        $unitPrice = $product->price;

        $order->items()->create([
          'product_id' => $product->id,
          'vendor_id' => $product->vendor_id,
          'product_name' => $product->name,
          'unit_price' => $unitPrice,
          'quantity' => $quantity,
          'subtotal' => round($unitPrice * $quantity, 2),
        ]);

        $product->decrement('stock', $quantity);
      }

      $cart->items()->delete();

      return $order->load(['items.product', 'items.vendor', 'user']);
    });
  }

  public function cancel(Order $order): Order
  {
    if (! $order->isPending()) {
      throw new \DomainException('Only pending orders can be cancelled.');
    }

    return DB::transaction(function () use ($order) {
      $order->load('items.product');

      foreach ($order->items as $item) {
        $item->product->increment('stock', $item->quantity);
      }

      $order->update(['status' => Order::STATUS_CANCELLED]);

      return $order->fresh(['items.product', 'items.vendor', 'user']);
    });
  }

  public function listForCustomer(User $user): LengthAwarePaginator
  {
    return Order::query()
      ->where('user_id', $user->id)
      ->with(['items.product', 'items.vendor'])
      ->latest()
      ->paginate(self::PER_PAGE);
  }

  public function showForCustomer(Order $order): Order
  {
    return $order->load(['items.product', 'items.vendor', 'user']);
  }

  public function listForVendor(Vendor $vendor): LengthAwarePaginator
  {
    return Order::query()
      ->whereHas('items', fn(Builder $query) => $query->where('vendor_id', $vendor->id))
      ->with([
        'user',
        'items' => fn($query) => $query->where('vendor_id', $vendor->id)->with('product'),
      ])
      ->latest()
      ->paginate(self::PER_PAGE);
  }

  public function showForVendor(Vendor $vendor, Order $order): Order
  {
    $hasVendorItems = $order->items()->where('vendor_id', $vendor->id)->exists();

    if (! $hasVendorItems) {
      throw new \DomainException('Order not found.');
    }

    return $order->load([
      'user',
      'items' => fn($query) => $query->where('vendor_id', $vendor->id)->with('product'),
    ]);
  }

  private function assertProductAvailable(?Product $product): void
  {
    if (
      ! $product
      || $product->status !== Product::STATUS_ACTIVE
      || ! $product->vendor
      || $product->vendor->status !== Vendor::STATUS_APPROVED
      || ! $product->category
      || ! $product->category->is_active
    ) {
      throw new \DomainException('One or more products in your cart are no longer available.');
    }
  }

  private function assertStockAvailable(Product $product, int $quantity): void
  {
    if ($quantity > $product->stock) {
      throw new \DomainException(
        "Insufficient stock for \"{$product->name}\". Available: {$product->stock}."
      );
    }
  }

  private function uniqueOrderNumber(): string
  {
    do {
      $number = 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    } while (Order::query()->where('order_number', $number)->exists());

    return $number;
  }
}
