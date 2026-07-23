<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\VendorOrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
  public function __construct(
    private OrderService $orderService
  ) {}

  public function index(Request $request)
  {
    $orders = $this->orderService->listForVendor($request->user()->vendor);

    return $this->success([
      'items' => VendorOrderResource::collection($orders->items()),
      'meta' => [
        'current_page' => $orders->currentPage(),
        'last_page' => $orders->lastPage(),
        'per_page' => $orders->perPage(),
        'total' => $orders->total(),
      ],
    ]);
  }

  public function show(Request $request, Order $order)
  {
    try {
      $order = $this->orderService->showForVendor($request->user()->vendor, $order);
    } catch (\DomainException $e) {
      return $this->error($e->getMessage(), 404);
    }

    return $this->success(new VendorOrderResource($order));
  }
}
