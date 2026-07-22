<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartItemResource;
use App\Http\Resources\CartResource;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
  public function __construct(
    private CartService $cartService
  ) {}

  public function show(Request $request)
  {
    $cart = $this->cartService->view($request->user());

    return $this->success(new CartResource($cart));
  }

  public function storeItem(AddCartItemRequest $request)
  {
    try {
      $item = $this->cartService->addItem(
        $request->user(),
        $request->integer('product_id'),
        $request->integer('quantity')
      );
    } catch (\DomainException $e) {
      return $this->error($e->getMessage(), 422);
    }

    return $this->success(
      new CartItemResource($item),
      'Item added to cart.',
      201
    );
  }

  public function updateItem(UpdateCartItemRequest $request, CartItem $item)
  {
    $this->authorize('update', $item);

    try {
      $item = $this->cartService->updateItem($item, $request->integer('quantity'));
    } catch (\DomainException $e) {
      return $this->error($e->getMessage(), 422);
    }

    return $this->success(
      new CartItemResource($item),
      'Cart item updated.'
    );
  }

  public function destroyItem(CartItem $item)
  {
    $this->authorize('delete', $item);

    $this->cartService->removeItem($item);

    return $this->success(null, 'Item removed from cart.');
  }

  public function clear(Request $request)
  {
    $this->cartService->clear($request->user());

    return $this->success(null, 'Cart cleared.');
  }
}
