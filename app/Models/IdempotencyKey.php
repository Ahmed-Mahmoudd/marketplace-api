<?php

namespace App\Models;

use App\Enums\IdempotencyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyKey extends Model
{
    protected $fillable = [
        'key',
        'endpoint',
        'user_id',
        'request_hash',
        'status',
        'response_status',
        'response_body',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => IdempotencyStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === IdempotencyStatus::Completed;
    }

    public function isInProgress(): bool
    {
        return $this->status === IdempotencyStatus::InProgress;
    }
}
