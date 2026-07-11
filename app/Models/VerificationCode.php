<?php

namespace App\Models;

use App\Enums\VerificationPurpose;
use Database\Factories\VerificationCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'email', 'user_id', 'purpose', 'code_hash', 'new_email',
    'attempts', 'max_attempts', 'expires_at', 'consumed_at',
])]
class VerificationCode extends Model
{
    /** @use HasFactory<VerificationCodeFactory> */
    use HasFactory, Prunable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => VerificationPurpose::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function attemptsExhausted(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    public function prunable(): Builder
    {
        return static::where('expires_at', '<=', now()->subDay());
    }
}
