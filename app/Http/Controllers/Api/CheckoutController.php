<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
  public function __construct(
    private OrderService $orderService
  ) {}

  public function store(CheckoutRequest $request)
  {
    try {
      $order = $this->orderService->checkout($request->user());
    } catch (\DomainException $e) {
      return $this->error($e->getMessage(), 422);
    }

    return $this->success(
      new OrderResource($order),
      'Order placed successfully.',
      201
    );
  }
}
