<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\VendorApplyRequest;
use App\Http\Resources\VendorResource;
use App\Services\VendorService;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function __construct(
        private VendorService $vendorService
    ) {}

    public function apply(VendorApplyRequest $request)
    {
        try {
            $vendor = $this->vendorService->apply(
                $request->user(),
                $request->validated()
            );

            return $this->success(
                new VendorResource($vendor),
                'Vendor application submitted.',
                201
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }
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
