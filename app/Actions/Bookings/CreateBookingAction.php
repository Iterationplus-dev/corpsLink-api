<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\SeatHold;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateBookingAction
{
    /**
     * Turns an active seat hold into a pending-payment booking + payment
     * pair. Idempotent for the same hold: calling this twice (double-tap,
     * retry after a network blip) returns the same pending booking rather
     * than erroring, since a hold can only ever produce one booking.
     *
     * The hold itself is deliberately NOT released here — it's still
     * needed to prove ownership of the seat until payment resolves one
     * way or the other (ConfirmPaymentAction releases it).
     *
     * @return array{booking: Booking, payment: Payment}
     */
    public function handle(User $user, int $holdId): array
    {
        $hold = SeatHold::query()->active()->whereKey($holdId)->where('user_id', $user->id)->first();

        if (! $hold) {
            throw new ApiException(
                'This seat hold has expired or does not belong to you.',
                status: 410,
                errorCode: 'hold_expired',
            );
        }

        return DB::transaction(function () use ($user, $hold) {
            $existing = Booking::query()
                ->where('seat_id', $hold->seat_id)
                ->where('user_id', $user->id)
                ->where('status', BookingStatus::PendingPayment)
                ->with('payment')
                ->first();

            if ($existing) {
                return ['booking' => $existing, 'payment' => $existing->payment];
            }

            $hold->loadMissing('seat.vehicle');
            $vehicle = $hold->seat->vehicle;

            $payment = Payment::query()->create([
                'user_id' => $user->id,
                'seat_id' => $hold->seat_id,
                'vehicle_id' => $vehicle->id,
                'gateway' => null,
                'reference' => 'CL-PAY-'.Str::upper(Str::random(12)),
                'amount' => $vehicle->fare,
                'currency' => config('corpslink.payments.currency'),
                'status' => PaymentStatus::Pending,
            ]);

            $booking = Booking::query()->create([
                'user_id' => $user->id,
                'seat_id' => $hold->seat_id,
                'vehicle_id' => $vehicle->id,
                'payment_id' => $payment->id,
                'fare' => $vehicle->fare,
                'status' => BookingStatus::PendingPayment,
                'booked_at' => now(),
            ]);

            return ['booking' => $booking, 'payment' => $payment];
        });
    }
}
