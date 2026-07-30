<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'items' => CartItemResource::collection($this->whenLoaded('items')),
      'items_count' => $this->items->count(),
      'total' => round(
        $this->items->sum(fn($item) => $item->product->price * $item->quantity),
        2
      ),
    ];
  }
}
