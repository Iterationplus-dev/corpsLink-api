<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Database\Factories\SeatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Seats are laid out 2 | aisle | 2 (4 per row), matching the design's
 * fixed coach layout. Row/window position is derived from seat_number
 * rather than stored, so it can never drift out of sync with capacity.
 */
#[Fillable(['vehicle_id', 'seat_number'])]
class Seat extends Model
{
    /** @use HasFactory<SeatFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return HasOne<SeatHold, $this>
     */
    public function activeHold(): HasOne
    {
        return $this->hasOne(SeatHold::class)->whereNull('released_at')->where('expires_at', '>', now());
    }

    /**
     * @return HasOne<Booking, $this>
     */
    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }

    /**
     * Only a Confirmed booking actually holds the seat — a PendingPayment
     * or Expired one (abandoned before payment completed) must not
     * permanently block the seat for everyone else.
     *
     * @return HasOne<Booking, $this>
     */
    public function confirmedBooking(): HasOne
    {
        return $this->hasOne(Booking::class)->where('status', BookingStatus::Confirmed);
    }

    /**
     * Whether the seat can be newly held right now.
     */
    public function isAvailable(): bool
    {
        return ! $this->confirmedBooking()->exists() && ! $this->activeHold()->exists();
    }

    /**
     * Nearest free seat in the same vehicle, by seat-number distance —
     * powers the "Closest match" recovery suggestion used both when a
     * hold claim loses a race (Phase 2) and when a paid-for seat is taken
     * out from under a confirming payment (Phase 3).
     */
    public function nearestAvailableSeatNumber(): ?int
    {
        return static::query()
            ->where('vehicle_id', $this->vehicle_id)
            ->where('seat_number', '!=', $this->seat_number)
            ->whereDoesntHave('confirmedBooking')
            ->whereDoesntHave('activeHold')
            ->orderByRaw('ABS(seat_number - ?)', [$this->seat_number])
            ->value('seat_number');
    }

    public function row(): int
    {
        return intdiv($this->seat_number - 1, 4) + 1;
    }

    public function positionInRow(): int
    {
        return ($this->seat_number - 1) % 4;
    }

    /**
     * 1-indexed visual column within the row (1-4), for grid layouts that
     * want a column number rather than just window/aisle.
     */
    public function col(): int
    {
        return $this->positionInRow() + 1;
    }

    public function isWindow(): bool
    {
        return in_array($this->positionInRow(), [0, 3], true);
    }
}
