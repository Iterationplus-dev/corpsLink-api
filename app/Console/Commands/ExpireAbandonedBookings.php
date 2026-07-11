<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\SeatHold;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * A pending_payment Booking (created by CreateBookingAction alongside its
 * seat hold, before payment) is only abandoned once that same seat hold is
 * gone — the hold is deliberately kept alive through the whole payment
 * flow, so this never races a genuinely in-progress checkout.
 */
#[Signature('corpslink:expire-abandoned-bookings')]
#[Description('Mark pending-payment bookings whose seat hold has lapsed as expired, freeing the seat.')]
class ExpireAbandonedBookings extends Command
{
    public function handle(): int
    {
        $holdMinutes = (int) config('corpslink.seat_hold.duration_minutes');

        $candidates = Booking::query()
            ->where('status', BookingStatus::PendingPayment)
            ->where('created_at', '<=', now()->subMinutes($holdMinutes))
            ->get();

        $expired = 0;

        foreach ($candidates as $booking) {
            $stillHeld = SeatHold::query()->active()
                ->where('seat_id', $booking->seat_id)
                ->where('user_id', $booking->user_id)
                ->exists();

            if (! $stillHeld) {
                $booking->update(['status' => BookingStatus::Expired]);
                $expired++;
            }
        }

        $this->info("Expired {$expired} abandoned booking(s).");

        return self::SUCCESS;
    }
}
