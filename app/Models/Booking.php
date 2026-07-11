<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'seat_id', 'vehicle_id', 'payment_id', 'reference', 'fare', 'status', 'booked_at',
    'departure_reminder_24h_sent_at', 'departure_reminder_1h_sent_at',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // The human-facing reference is derived from the now-known
        // auto-increment id, so it can never collide under concurrent
        // inserts the way a pre-computed count-based reference could.
        static::created(function (Booking $booking) {
            if ($booking->reference === null) {
                $booking->updateQuietly([
                    'reference' => sprintf('CL-%d-%05d', $booking->created_at->year, $booking->id),
                ]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'fare' => 'decimal:2',
            'booked_at' => 'datetime',
            'departure_reminder_24h_sent_at' => 'datetime',
            'departure_reminder_1h_sent_at' => 'datetime',
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
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
