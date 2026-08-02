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
use Illuminate\Support\Facades\Log;

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
     * @param  ?string  $clientReference  Only meaningful for Monnify/Opay —
     *                                    see verificationReference(). Ignored
     *                                    for every other gateway and by
     *                                    the webhook caller (always null).
     *
     * @throws PaymentVerificationFailedException|NoSeatsAvailableException
     */
    public function handle(Payment $payment, ?string $clientReference = null): Booking
    {
        // The transaction must always commit — even on failure we need the
        // "mark payment failed" write to persist, so domain failures are
        // returned as a tagged outcome and only thrown *after* commit
        // (throwing inside DB::transaction rolls back everything in it,
        // including that write).
        $outcome = DB::transaction(function () use ($payment, $clientReference) {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->isSuccessful()) {
                return ['booking' => $locked->booking()->with(['seat', 'vehicle', 'payment'])->firstOrFail()];
            }

            $booking = $locked->booking()->lockForUpdate()->firstOrFail();

            $result = $this->resolver->resolve($locked->gateway)->verify($this->verificationReference($locked, $clientReference));

            if (! $result->successful) {
                $locked->update(['status' => PaymentStatus::Failed, 'gateway_response' => $result->raw]);

                return ['error' => PaymentVerificationFailedException::make()];
            }

            if (round($result->amount, 2) !== round((float) $locked->amount, 2)) {
                $locked->update(['status' => PaymentStatus::Failed, 'gateway_response' => $result->raw]);

                return ['error' => PaymentVerificationFailedException::make('The amount paid did not match the expected fare.')];
            }

            // A Monnify transactionReference trusted from client input (see
            // verificationReference()) names a real transaction at Monnify,
            // but nothing so far confirms *this* payment is the one that
            // transaction belongs to — a copied reference would otherwise
            // let someone else's paid transaction confirm an unrelated
            // booking as long as the amount happened to match. Every other
            // gateway's gateway_reference is always our own uniquely
            // generated value, so it can't collide this way.
            if ($result->gatewayReference && Payment::query()
                ->where('gateway_reference', $result->gatewayReference)
                ->whereKeyNot($locked->id)
                ->where('status', PaymentStatus::Successful)
                ->exists()) {
                $locked->update(['status' => PaymentStatus::Failed, 'gateway_response' => $result->raw]);
                Log::warning('Payment verification rejected — gateway reference already claimed by another payment.', [
                    'payment_id' => $locked->id,
                    'gateway_reference' => $result->gatewayReference,
                ]);

                return ['error' => PaymentVerificationFailedException::make('This payment reference has already been used.')];
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
     * Flutterwave always echoes back exactly the reference we sent, so
     * Payment::reference works as the verify() lookup key for it. Paystack,
     * Opay, and Monnify all reject re-initializing with a reused reference
     * outright — so their gateways send a fresh, uniquely-suffixed one on
     * every attempt (see PaystackGateway::initialize() /
     * OpayGateway::initialize() / MonnifyGateway::initialize()), and the
     * only stable per-attempt identifier left is gateway_reference. That
     * stays stable across verify() calls for all three — unlike
     * Flutterwave's, which gets overwritten with its internal numeric id
     * after a successful verify.
     *
     * Monnify and Opay are both the exception within the exception: each
     * has a native mobile SDK that lets the client charge the user
     * directly, without ever going through our own initialize() checkout —
     * so the reference that gateway actually settled the charge under is
     * one only the client knows, not the (unrelated, likely-abandoned) one
     * our own initialize() call stored as gateway_reference. When the
     * client supplies a $clientReference that isn't just it echoing back
     * Payment::reference (the hosted-checkout contract, where the field is
     * accepted but not otherwise meaningful), trust it over our own stored
     * value. handle()'s reference-reuse guard is what keeps this safe
     * against a copied/replayed reference.
     *
     * Note this doesn't cover every native-SDK edge case for Opay: if its
     * SDK's own charge result never gives the client anything besides our
     * own Payment::reference to send back (see OpayGateway::verify()'s
     * docblock on how that value is then routed), this can't distinguish
     * "that's the real reference the SDK charged under" from "the client is
     * just echoing the old hosted-checkout contract" — so that specific
     * case still falls through to gateway_reference below, same as today.
     */
    protected function verificationReference(Payment $payment, ?string $clientReference = null): string
    {
        $trustsClientReference = in_array($payment->gateway, [PaymentGateway::Monnify, PaymentGateway::Opay], true);

        if ($trustsClientReference && $clientReference && $clientReference !== $payment->reference) {
            return $clientReference;
        }

        return match ($payment->gateway) {
            PaymentGateway::Paystack, PaymentGateway::Opay, PaymentGateway::Monnify => $payment->gateway_reference ?? $payment->reference,
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
