<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HandoffCode extends Model
{
  /** Deliberately short: the code is redeemed within a page load. */
  public const TTL_SECONDS = 60;

  protected $fillable = [
    'code_hash',
    'user_id',
    'expires_at',
    'redeemed_at',
  ];

  protected function casts(): array
  {
    return [
      'expires_at'  => 'datetime',
      'redeemed_at' => 'datetime',
    ];
  }

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  /**
   * Issue a code for a user. Returns the plaintext, which is shown once and
   * never stored — only its hash is persisted.
   */
  public static function issueFor(User $user): string
  {
    $plain = Str::random(64);

    static::create([
      'code_hash'  => hash('sha256', $plain),
      'user_id'    => $user->id,
      'expires_at' => now()->addSeconds(self::TTL_SECONDS),
    ]);

    return $plain;
  }

  public function isRedeemable(): bool
  {
    return $this->redeemed_at === null && $this->expires_at->isFuture();
  }
}
