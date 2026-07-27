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
  public const STATUS_CONFIRMED = 'confirmed';
  public const STATUS_PROCESSING = 'processing';
  public const STATUS_SHIPPED = 'shipped';
  public const STATUS_DELIVERED = 'delivered';
  public const STATUS_CANCELLED = 'cancelled';

  public const STATUSES = [
    self::STATUS_PENDING,
    self::STATUS_CONFIRMED,
    self::STATUS_PROCESSING,
    self::STATUS_SHIPPED,
    self::STATUS_DELIVERED,
    self::STATUS_CANCELLED,
  ];

  /** Valid transitions: currentStatus => array of allowed next statuses */
  public const TRANSITIONS = [
    self::STATUS_PENDING    => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
    self::STATUS_CONFIRMED  => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
    self::STATUS_PROCESSING => [self::STATUS_SHIPPED],
    self::STATUS_SHIPPED    => [self::STATUS_DELIVERED],
    self::STATUS_DELIVERED  => [],
    self::STATUS_CANCELLED  => [],
  ];

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
    'total'    => 'decimal:2',
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

  public function isDelivered(): bool
  {
    return $this->status === self::STATUS_DELIVERED;
  }

  public function isInTerminalState(): bool
  {
    return in_array($this->status, [self::STATUS_DELIVERED, self::STATUS_CANCELLED], true);
  }

  /** Returns the allowed next statuses from the current status. */
  public function getAllowedNextStatuses(): array
  {
    return self::TRANSITIONS[$this->status] ?? [];
  }

  /** Whether a transition to the given status is valid from the current status. */
  public function canTransitionTo(string $newStatus): bool
  {
    return in_array($newStatus, $this->getAllowedNextStatuses(), true);
  }

  /** Customers can cancel when order is pending or confirmed (before processing). */
  public function isCancellable(): bool
  {
    return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED], true);
  }
}
