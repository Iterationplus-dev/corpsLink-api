<?php

namespace App\Models;

use Database\Factories\SeatHoldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['seat_id', 'user_id', 'expires_at', 'released_at', 'expiry_warning_sent_at'])]
class SeatHold extends Model
{
    /** @use HasFactory<SeatHoldFactory> */
    use HasFactory, Prunable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
            'expiry_warning_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Seat, $this>
     */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->released_at === null && $this->expires_at->isFuture();
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('released_at')->where('expires_at', '>', now());
    }

    public function prunable(): Builder
    {
        return static::where('expires_at', '<=', now()->subDay());
    }
}
