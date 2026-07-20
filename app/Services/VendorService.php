<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Str;

class VendorService
{
  public function apply(User $user, array $data): Vendor
  {
    if ($user->vendor()->exists()) {
      throw new \DomainException('You already have a vendor application on file.');
    }

    return Vendor::create([
      'user_id' => $user->id,
      'store_name' => $data['store_name'],
      'store_slug' => Str::slug($data['store_name']) . '-' . Str::lower(Str::random(6)),
      'status' => Vendor::STATUS_PENDING,
      'payout_details' => $data['payout_details'] ?? null,
    ]);
  }
}
