<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id' => $this->id,
      'product' => new PublicProductResource($this->whenLoaded('product')),
      'unit_price' => $this->product->price,
      'quantity' => $this->quantity,
      'subtotal' => round($this->product->price * $this->quantity, 2),
    ];
  }
}
