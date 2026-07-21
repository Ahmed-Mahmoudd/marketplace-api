<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
  public function viewAny(User $user): bool
  {
    return $user->vendor !== null;
  }

  public function update(User $user, Product $product): bool
  {
    return $user->vendor && $user->vendor->id === $product->vendor_id;
  }

  public function delete(User $user, Product $product): bool
  {
    return $this->update($user, $product);
  }

  public function view(User $user, Product $product): bool
  {
    return $this->update($user, $product);
  }
}
