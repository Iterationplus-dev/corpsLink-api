<?php

namespace App\Actions\Payments;

use App\Enums\PaymentGateway;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayResolver;

class InitializePaymentAction
{
    public function __construct(protected PaymentGatewayResolver $resolver) {}

    /**
     * The Payment already exists (created alongside its pending_payment
     * Booking by CreateBookingAction) — this just picks a gateway and
     * asks it for a checkout URL.
     *
     * @return array{payment: Payment, authorization_url: ?string}
     */
    public function handle(Payment $payment, PaymentGateway $gateway): array
    {
        $payment->update(['gateway' => $gateway]);

        $result = $this->resolver->resolve($gateway)->initialize($payment);

        $payment->update(['gateway_reference' => $result['gateway_reference']]);

        return ['payment' => $payment, 'authorization_url' => $result['authorization_url']];
    }
}
