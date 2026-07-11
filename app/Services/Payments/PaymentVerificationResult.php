<?php

namespace App\Services\Payments;

/**
 * The shape both gateways normalize into, so ConfirmPaymentAction never
 * needs to know which gateway it's dealing with.
 */
readonly class PaymentVerificationResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $successful,
        public float $amount,
        public string $currency,
        public ?string $gatewayReference,
        public array $raw,
    ) {}
}
