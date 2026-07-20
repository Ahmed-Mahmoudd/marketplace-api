<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\VendorApplyRequest;
use App\Http\Resources\VendorResource;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorController extends Controller
{
    public function apply(VendorApplyRequest $request)
    {
        $user = $request->user();

        if ($user->vendor()->exists()) {
            return $this->error('You already have a vendor application on file.', 422);
        }

        $vendor = Vendor::create([
            'user_id' => $user->id,
            'store_name' => $request->validated('store_name'),
            'store_slug' => Str::slug($request->validated('store_name')) . '-' . Str::lower(Str::random(6)),
            'status' => Vendor::STATUS_PENDING,
            'payout_details' => $request->validated('payout_details'),
        ]);

        return $this->success(new VendorResource($vendor), 'Vendor application submitted.', 201);
    }

    public function show(Request $request)
    {
        $vendor = $request->user()->vendor;

        if (! $vendor) {
            return $this->error('No vendor profile found.', 404);
        }

        $this->authorize('view', $vendor);

        return $this->success(new VendorResource($vendor));
    }
}
