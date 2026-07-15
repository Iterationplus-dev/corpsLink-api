<?php

namespace App\Actions\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Events\PaymentConfirmed;
use App\Exceptions\NoSeatsAvailableException;
use App\Exceptions\PaymentVerificationFailedException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Services\Payments\PaymentGatewayResolver;
use Illuminate\Support\Facades\DB;

class ConfirmPaymentAction
{
    public function __construct(protected PaymentGatewayResolver $resolver) {}

    /**
     * Verifies a payment with its gateway and confirms the pending-payment
     * booking CreateBookingAction already created for it. Safe to call
     * twice for the same payment — an already-successful payment just
     * returns its existing (already-confirmed) booking, no reprocessing.
     * Called from both the client-triggered verify endpoint and the
     * gateway webhook, whichever reaches it first.
     *
     * @throws PaymentVerificationFailedException|NoSeatsAvailableException
     */
    public function handle(Payment $payment): Booking
    {
        // The transaction must always commit — even on failure we need the
        // "mark payment failed" write to persist, so domain failures are
        // returned as a tagged outcome and only thrown *after* commit
        // (throwing inside DB::transaction rolls back everything in it,
        // including that write).
        $outcome = DB::transaction(function () use ($payment) {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->isSuccessful()) {
                return ['booking' => $locked->booking()->with(['seat', 'vehicle', 'payment'])->firstOrFail()];
            }

            $booking = $locked->booking()->lockForUpdate()->firstOrFail();

            $result = $this->resolver->resolve($locked->gateway)->verify($this->verificationReference($locked));

            if (! $result->successful) {
                $locked->update(['status' => PaymentStatus::Failed, 'gateway_response' => $result->raw]);

                return ['error' => PaymentVerificationFailedException::make()];
            }

            if (round($result->amount, 2) !== round((float) $locked->amount, 2)) {
                $locked->update(['status' => PaymentStatus::Failed, 'gateway_response' => $result->raw]);

                return ['error' => PaymentVerificationFailedException::make('The amount paid did not match the expected fare.')];
            }

            $locked->update([
                'status' => PaymentStatus::Successful,
                'gateway_reference' => $result->gatewayReference ?? $locked->gateway_reference,
                'gateway_response' => $result->raw,
                'paid_at' => now(),
            ]);

            $bookableSeat = $this->resolveBookableSeat($locked);

            if (! $bookableSeat) {
                // Payment is genuinely successful — the money was taken —
                // there's simply nowhere left to seat them. That status
                // stays Successful and the booking stays PendingPayment;
                // this is a support case, not a failure to auto-resolve.
                return ['error' => NoSeatsAvailableException::make()];
            }

            $booking->update([
                'seat_id' => $bookableSeat->id,
                'status' => BookingStatus::Confirmed,
                'booked_at' => now(),
            ]);

            // Converted to a booking (possibly on a reassigned seat) — the
            // original hold, wherever it landed, is now moot.
            SeatHold::query()->active()->where('user_id', $locked->user_id)->update(['released_at' => now()]);

            return ['booking' => $booking->load(['seat', 'vehicle', 'payment'])];
        });

        if (isset($outcome['error'])) {
            throw $outcome['error'];
        }

        event(new PaymentConfirmed($outcome['booking']));

        return $outcome['booking'];
    }

    /**
     * Paystack/Flutterwave always echo back exactly the reference we sent,
     * so Payment::reference works as the verify() lookup key for them. Monnify
     * rejects re-initializing with a reused paymentReference outright — so
     * MonnifyGateway::initialize() sends a fresh, uniquely-suffixed one on
     * every attempt, and the only stable per-attempt identifier left is
     * gateway_reference (Monnify's own transactionReference, unchanged by
     * MonnifyGateway::verify() between calls — unlike Flutterwave's, which
     * gets overwritten with its internal numeric id after a successful verify).
     */
    protected function verificationReference(Payment $payment): string
    {
        return match ($payment->gateway) {
            PaymentGateway::Monnify => $payment->gateway_reference ?? $payment->reference,
            default => $payment->reference,
        };
    }

    /**
     * The seat this payment was for, unless someone else has since taken
     * it — a paid-for booking is never lost to that race, it's reassigned
     * to the nearest free seat instead. Null only when the whole vehicle
     * is genuinely full. Only a Confirmed booking on another seat, or an
     * active hold held by someone else, counts as "taken" — a stale
     * pending_payment booking of ours doesn't block reassignment.
     */
    protected function resolveBookableSeat(Payment $payment): ?Seat
    {
        $seat = Seat::query()->whereKey($payment->seat_id)->lockForUpdate()->firstOrFail();

        $takenByAnotherParty = $seat->confirmedBooking()->exists()
            || SeatHold::query()->active()
                ->where('seat_id', $seat->id)
                ->where('user_id', '!=', $payment->user_id)
                ->exists();

        if (! $takenByAnotherParty) {
            return $seat;
        }

        $alternateSeatNumber = $seat->nearestAvailableSeatNumber();

        if (! $alternateSeatNumber) {
            return null;
        }

        return Seat::query()
            ->where('vehicle_id', $seat->vehicle_id)
            ->where('seat_number', $alternateSeatNumber)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
