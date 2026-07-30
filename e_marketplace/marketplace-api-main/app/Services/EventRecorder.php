<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Writes the behavioural event stream consumed by the vendor analytics funnel.
 *
 * Every method is best-effort: analytics must never break a customer's browse,
 * cart or checkout request, so failures are logged and swallowed.
 */
class EventRecorder
{
  public function view(Product $product, ?User $user, ?string $sessionId = null): void
  {
    $this->record(Event::TYPE_VIEW, $product->id, $product->vendor_id, $user?->id, $sessionId);
  }

  public function cart(Product $product, ?User $user, ?string $sessionId = null): void
  {
    $this->record(Event::TYPE_CART, $product->id, $product->vendor_id, $user?->id, $sessionId);
  }

  /**
   * One purchase event per line item, so the funnel counts products the same
   * way the view and cart stages do.
   */
  public function purchase(Order $order, ?string $sessionId = null): void
  {
    try {
      $rows = $order->items->map(fn($item) => [
        'user_id'    => $order->user_id,
        'product_id' => $item->product_id,
        'vendor_id'  => $item->vendor_id,
        'type'       => Event::TYPE_PURCHASE,
        'session_id' => $sessionId,
        'created_at' => now(),
        'updated_at' => now(),
      ])->all();

      if ($rows !== []) {
        Event::insert($rows);
      }
    } catch (\Throwable $e) {
      Log::warning('Failed to record purchase events', [
        'order_id' => $order->id,
        'error'    => $e->getMessage(),
      ]);
    }
  }

  private function record(
    string $type,
    int $productId,
    int $vendorId,
    ?int $userId,
    ?string $sessionId
  ): void {
    try {
      Event::create([
        'user_id'    => $userId,
        'product_id' => $productId,
        'vendor_id'  => $vendorId,
        'type'       => $type,
        'session_id' => $sessionId,
      ]);
    } catch (\Throwable $e) {
      Log::warning('Failed to record event', [
        'type'       => $type,
        'product_id' => $productId,
        'error'      => $e->getMessage(),
      ]);
    }
  }
}
