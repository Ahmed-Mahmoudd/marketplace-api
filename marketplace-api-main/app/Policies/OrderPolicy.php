<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
  public function view(User $user, Order $order): bool
  {
    return $user->id === $order->user_id;
  }

  public function cancel(User $user, Order $order): bool
  {
    return $user->id === $order->user_id && $order->isCancellable();
  }

  /**
   * Only vendors (with items in the order) and admins can update status.
   * Fine-grained vendor authorization is enforced in the controller / service.
   */
  public function updateStatus(User $user, Order $order): bool
  {
    return $user->hasRole('vendor') || $user->hasRole('admin');
  }
}
