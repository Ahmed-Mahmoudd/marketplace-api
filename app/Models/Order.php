<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
  use HasFactory;

  public const STATUS_PENDING = 'pending';

  public const STATUS_CANCELLED = 'cancelled';

  public const PAYMENT_METHOD_COD = 'cod';

  public const PAYMENT_STATUS_PENDING = 'pending';

  protected $fillable = [
    'order_number',
    'user_id',
    'status',
    'payment_method',
    'payment_status',
    'subtotal',
    'total',
  ];

  protected $casts = [
    'subtotal' => 'decimal:2',
    'total' => 'decimal:2',
  ];

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  public function items(): HasMany
  {
    return $this->hasMany(OrderItem::class);
  }

  public function isPending(): bool
  {
    return $this->status === self::STATUS_PENDING;
  }
}
