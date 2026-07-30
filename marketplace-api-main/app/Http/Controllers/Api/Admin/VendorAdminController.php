<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\VendorResource;
use App\Models\Vendor;
use App\Services\VendorAdminService;
use Illuminate\Http\Request;

class VendorAdminController extends Controller
{
    public function __construct(
        private VendorAdminService $vendorAdminService
    ) {}

    public function index(Request $request)
    {
        $vendors = $this->vendorAdminService->index(
            $request->query('status')
        );

        return VendorResource::collection($vendors);
    }

    public function approve(Vendor $vendor)
    {
        try {

            $vendor = $this->vendorAdminService->approve($vendor);

            return $this->success(
                new VendorResource($vendor),
                'Vendor approved.'
            );
        } catch (\DomainException $e) {

            return $this->error($e->getMessage(), 422);
        }
    }

    public function suspend(Vendor $vendor)
    {
        $vendor = $this->vendorAdminService->suspend($vendor);

        return $this->success(
            new VendorResource($vendor),
            'Vendor suspended.'
        );
    }

    public function reject(Vendor $vendor)
    {
        $vendor = $this->vendorAdminService->reject($vendor);

        return $this->success(
            new VendorResource($vendor),
            'Vendor rejected.'
        );
    }
}
