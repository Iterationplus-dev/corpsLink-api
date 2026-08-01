<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * A pending_payment Booking (created by CreateBookingAction alongside its
 * seat hold, before payment) is only abandoned once that same seat hold is
 * gone — the hold is deliberately kept alive through the whole payment
 * flow, so this never races a genuinely in-progress checkout.
 *
 * Deliberately keyed off the hold's own `expires_at` (via NOT EXISTS below)
 * rather than `bookings.created_at` + the configured hold duration: a hold
 * can sit active for a while before CreateBookingAction ever converts it
 * into a booking (the user browsing before confirming), so the hold's real
 * expiry is `hold.created_at + duration`, not `booking.created_at +
 * duration` — those two only coincide when the booking was created in the
 * same instant as the hold. Anchoring to booking.created_at previously let
 * an already-expired seat sit "taken" for up to the full hold duration
 * past when the UI's countdown said it would free up.
 */
#[Signature('corpslink:expire-abandoned-bookings')]
#[Description('Mark pending-payment bookings whose seat hold has lapsed as expired, freeing the seat.')]
class ExpireAbandonedBookings extends Command
{
    public function handle(): int
    {
        $expired = Booking::query()
            ->where('status', BookingStatus::PendingPayment)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('seat_holds')
                    ->whereColumn('seat_holds.seat_id', 'bookings.seat_id')
                    ->whereColumn('seat_holds.user_id', 'bookings.user_id')
                    ->whereNull('seat_holds.released_at')
                    ->where('seat_holds.expires_at', '>', now());
            })
            ->update(['status' => BookingStatus::Expired]);

        $this->info("Expired {$expired} abandoned booking(s).");

        return self::SUCCESS;
    }
}
