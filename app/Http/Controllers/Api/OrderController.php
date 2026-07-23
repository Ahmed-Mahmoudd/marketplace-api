<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
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
    $orders = $this->orderService->listForCustomer($request->user());

    return $this->success([
      'items' => OrderResource::collection($orders->items()),
      'meta' => [
        'current_page' => $orders->currentPage(),
        'last_page' => $orders->lastPage(),
        'per_page' => $orders->perPage(),
        'total' => $orders->total(),
      ],
    ]);
  }

  public function show(Order $order)
  {
    $this->authorize('view', $order);

    $order = $this->orderService->showForCustomer($order);

    return $this->success(new OrderResource($order));
  }

  public function cancel(Order $order)
  {
    $this->authorize('cancel', $order);

    try {
      $order = $this->orderService->cancel($order);
    } catch (\DomainException $e) {
      return $this->error($e->getMessage(), 422);
    }

    return $this->success(
      new OrderResource($order),
      'Order cancelled.'
    );
  }
}
