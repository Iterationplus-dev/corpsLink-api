<?php

namespace App\Models;

use Database\Factories\PendingRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'registration_token', 'name', 'email', 'phone', 'email_verified_at',
    'institution_id', 'call_up_number', 'state_code', 'batch', 'stream',
    'nok_full_name', 'nok_relationship', 'nok_phone', 'nok_alternate_phone',
    'nok_address', 'nok_apply_all', 'expires_at',
])]
class PendingRegistration extends Model
{
    /** @use HasFactory<PendingRegistrationFactory> */
    use HasFactory, Prunable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'nok_apply_all' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function hasSchoolInfo(): bool
    {
        return $this->institution_id !== null && $this->call_up_number !== null;
    }

    public function hasNextOfKin(): bool
    {
        return $this->nok_full_name !== null;
    }

    public function prunable(): Builder
    {
        return static::where('expires_at', '<=', now());
    }
}
