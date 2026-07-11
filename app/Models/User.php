<?php

namespace App\Models;

use App\Contracts\ImageStorageContract;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name', 'email', 'phone', 'password', 'avatar_path', 'institution_id',
    'call_up_number', 'state_code', 'batch', 'stream', 'two_factor_enabled',
    'notification_preferences',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Default state for the notification-preferences toggles shown on
     * screen N3. Stored per-user so it can be individually overridden.
     *
     * @var array<string, bool>
     */
    public const array DEFAULT_NOTIFICATION_PREFERENCES = [
        'booking_updates' => true,
        'seat_hold_alerts' => true,
        'departure_reminders' => true,
        'trip_changes' => true,
        'tips_announcements' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<array<string, bool>, array<string, bool>>
     */
    protected function notificationPreferences(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => array_merge(
                self::DEFAULT_NOTIFICATION_PREFERENCES,
                $value ? json_decode($value, true) : [],
            ),
            set: fn (?array $value) => json_encode($value ?? []),
        );
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @return HasOne<NextOfKin, $this>
     */
    public function nextOfKin(): HasOne
    {
        return $this->hasOne(NextOfKin::class);
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * @return HasOne<SeatHold, $this>
     */
    public function activeSeatHold(): HasOne
    {
        return $this->hasOne(SeatHold::class)->whereNull('released_at')->where('expires_at', '>', now());
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return HasMany<DeviceToken, $this>
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    #[Scope]
    protected function withIdentifier(Builder $query, string $identifier): void
    {
        $query->where('email', $identifier)->orWhere('call_up_number', $identifier);
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path
            ? app(ImageStorageContract::class)->url($this->avatar_path, config('corpslink.avatar.transformation'))
            : null;
    }
}
