<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id' => $this->id,
      'order_number' => $this->order_number,
      'status' => $this->status,
      'payment_method' => $this->payment_method,
      'payment_status' => $this->payment_status,
      'subtotal' => $this->subtotal,
      'total' => $this->total,
      'items' => OrderItemResource::collection($this->whenLoaded('items')),
      'items_count' => $this->whenLoaded('items', fn() => $this->items->count()),
      'created_at' => $this->created_at,
      'updated_at' => $this->updated_at,
    ];
  }
}
