<?php

namespace App\Http\Resources;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductImageResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    /** @var FilesystemAdapter $disk */
    $disk = Storage::disk('public');

    return [
      'id' => $this->id,
      'url' => $disk->url($this->path),
      'is_primary' => $this->is_primary,
    ];
  }
}
