<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicProductResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id'          => $this->id,
      'name'        => $this->name,
      'slug'        => $this->slug,
      'description' => $this->description,
      'price'       => $this->price,
      'stock'       => $this->stock,
      'category'    => new CategoryResource($this->whenLoaded('category')),
      'vendor'      => $this->whenLoaded('vendor', fn() => [
        'id'         => $this->vendor->id,
        'store_name' => $this->vendor->store_name,
        'store_slug' => $this->vendor->store_slug,
      ]),
      'images'         => ProductImageResource::collection($this->whenLoaded('images')),
      'average_rating' => $this->whenNotNull(
        isset($this->reviews_avg_rating) ? round((float) $this->reviews_avg_rating, 1) : null
      ),
      'reviews_count'  => $this->when(
        isset($this->reviews_count),
        fn() => (int) $this->reviews_count
      ),
      'created_at' => $this->created_at,
    ];
  }
}
