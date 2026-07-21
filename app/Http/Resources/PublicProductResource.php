<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicProductResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id' => $this->id,
      'name' => $this->name,
      'slug' => $this->slug,
      'description' => $this->description,
      'price' => $this->price,
      'stock' => $this->stock,
      'category' => new CategoryResource($this->whenLoaded('category')),
      'vendor' => $this->whenLoaded('vendor', fn() => [
        'id' => $this->vendor->id,
        'store_name' => $this->vendor->store_name,
        'store_slug' => $this->vendor->store_slug,
      ]),
      'images' => ProductImageResource::collection($this->whenLoaded('images')),
      'created_at' => $this->created_at,
    ];
  }
}
