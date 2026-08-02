<?php

namespace App\Models;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id', 'seat_id', 'vehicle_id', 'gateway', 'reference', 'gateway_reference',
    'amount', 'currency', 'status', 'gateway_response', 'paid_at',
])]
class Payment extends Model
{
    /**
     * Every Payment::reference this API generates starts with this — the
     * one string other than "does an active Payment/Booking row have this
     * value" that reliably tells our own merchant references apart from a
     * gateway-native identifier (e.g. Opay's orderNo) a client hands back
     * at verify() time. See OpayGateway::verify().
     */
    public const REFERENCE_PREFIX = 'CL-PAY-';

    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Seat, $this>
     */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return HasOne<Booking, $this>
     */
    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::Successful;
    }
}
