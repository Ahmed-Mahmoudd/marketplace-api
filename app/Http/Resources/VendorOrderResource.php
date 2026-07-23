<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorOrderResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    $items = $this->relationLoaded('items') ? $this->items : collect();

    return [
      'id' => $this->id,
      'order_number' => $this->order_number,
      'status' => $this->status,
      'payment_method' => $this->payment_method,
      'payment_status' => $this->payment_status,
      'items' => OrderItemResource::collection($items),
      'items_count' => $items->count(),
      'vendor_subtotal' => round($items->sum('subtotal'), 2),
      'customer' => $this->whenLoaded('user', fn() => [
        'id' => $this->user->id,
        'name' => $this->user->name,
        'email' => $this->user->email,
      ]),
      'created_at' => $this->created_at,
    ];
  }
}
