<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
  use HasFactory;

  public const STATUS_ACTIVE = 'active';
  public const STATUS_INACTIVE = 'inactive';

  public const STATUSES = [
    self::STATUS_ACTIVE,
    self::STATUS_INACTIVE,
  ];

  protected $fillable = [
    'vendor_id',
    'category_id',
    'name',
    'slug',
    'description',
    'price',
    'stock',
    'status',
  ];

  protected $casts = [
    'price' => 'decimal:2',
    'stock' => 'integer',
  ];

  public function category(): BelongsTo
  {
    return $this->belongsTo(Category::class);
  }

  public function vendor(): BelongsTo
  {
    return $this->belongsTo(Vendor::class);
  }

  public function images(): HasMany
  {
    return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('id');
  }
}
