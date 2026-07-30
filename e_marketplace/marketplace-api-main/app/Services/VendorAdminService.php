<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class VendorAdminService
{
  public function index(?string $status): LengthAwarePaginator
  {
    return Vendor::query()
      ->with('user')
      ->when(
        $status,
        fn($query) => $query->where('status', $status)
      )
      ->latest()
      ->paginate(20);
  }

  public function approve(Vendor $vendor): Vendor
  {
    if ($vendor->status === Vendor::STATUS_APPROVED) {
      throw new \DomainException('Vendor is already approved.');
    }

    DB::transaction(function () use ($vendor) {

      $vendor->update([
        'status' => Vendor::STATUS_APPROVED,
        'approved_at' => now(),
      ]);

      $vendor->user->assignRole('vendor');
    });

    return $vendor->fresh('user');
  }

  public function reject(Vendor $vendor): Vendor
  {
    if ($vendor->status !== Vendor::STATUS_PENDING) {
      throw new \DomainException(
        'Only pending vendors can be rejected.'
      );
    }

    $vendor->update([
      'status' => Vendor::STATUS_REJECTED,
    ]);

    return $vendor->fresh('user');
  }

  public function suspend(Vendor $vendor): Vendor
  {
    if ($vendor->status !== Vendor::STATUS_APPROVED) {
      throw new \DomainException(
        'Only approved vendors can be suspended.'
      );
    }

    DB::transaction(function () use ($vendor) {

      $vendor->update([
        'status' => Vendor::STATUS_SUSPENDED,
      ]);

      $vendor->user->removeRole('vendor');
    });

    return $vendor->fresh('user');
  }
}
