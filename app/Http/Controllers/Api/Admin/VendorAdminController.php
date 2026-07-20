<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\VendorResource;
use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorAdminController extends Controller
{
    public function index(Request $request)
    {
        $vendors = Vendor::query()
            ->with('user')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20);

        return VendorResource::collection($vendors);
    }

    public function approve(Vendor $vendor)
    {
        if ($vendor->status === Vendor::STATUS_APPROVED) {
            return $this->error('Vendor is already approved.', 422);
        }

        $vendor->update([
            'status' => Vendor::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        $vendor->user->assignRole('vendor');

        return $this->success(new VendorResource($vendor->fresh('user')), 'Vendor approved.');
    }

    public function suspend(Vendor $vendor)
    {
        $vendor->update(['status' => Vendor::STATUS_SUSPENDED]);
        $vendor->user->removeRole('vendor');

        return $this->success(new VendorResource($vendor->fresh('user')), 'Vendor suspended.');
    }
}
