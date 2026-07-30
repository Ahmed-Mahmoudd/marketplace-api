<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
  use HasFactory;

  public const TYPE_VIEW = 'view';
  public const TYPE_CART = 'cart';
  public const TYPE_PURCHASE = 'purchase';

  public const TYPES = [
    self::TYPE_VIEW,
    self::TYPE_CART,
    self::TYPE_PURCHASE,
  ];

  protected $fillable = [
    'user_id',
    'product_id',
    'vendor_id',
    'type',
    'session_id',
  ];

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  public function product(): BelongsTo
  {
    return $this->belongsTo(Product::class);
  }

  public function vendor(): BelongsTo
  {
    return $this->belongsTo(Vendor::class);
  }
}
