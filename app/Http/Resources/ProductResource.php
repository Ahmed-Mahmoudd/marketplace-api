<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id' => $this->id,
      'vendor_id' => $this->vendor_id,
      'category' => new CategoryResource($this->whenLoaded('category')),
      'name' => $this->name,
      'slug' => $this->slug,
      'description' => $this->description,
      'price' => $this->price,
      'stock' => $this->stock,
      'status' => $this->status,
      'created_at' => $this->created_at,
    ];
  }
}
