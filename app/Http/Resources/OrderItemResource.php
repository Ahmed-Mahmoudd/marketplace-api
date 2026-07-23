<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id' => $this->id,
      'product_id' => $this->product_id,
      'vendor_id' => $this->vendor_id,
      'product_name' => $this->product_name,
      'unit_price' => $this->unit_price,
      'quantity' => $this->quantity,
      'subtotal' => $this->subtotal,
      'product' => new PublicProductResource($this->whenLoaded('product')),
      'vendor' => $this->whenLoaded('vendor', fn() => [
        'id' => $this->vendor->id,
        'store_name' => $this->vendor->store_name,
        'store_slug' => $this->vendor->store_slug,
      ]),
    ];
  }
}
