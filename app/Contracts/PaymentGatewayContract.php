<?php

namespace App\Contracts;

use App\Models\Payment;
use App\Services\Payments\PaymentVerificationResult;
use Illuminate\Http\Request;

interface PaymentGatewayContract
{
    /**
     * @return array{authorization_url: ?string, gateway_reference: ?string}
     */
    public function initialize(Payment $payment): array;

    public function verify(string $reference): PaymentVerificationResult;

    public function validateWebhookSignature(Request $request): bool;

    /**
     * The payment reference a webhook payload is about, so the caller can
     * look up which Payment to reconcile before calling verify().
     */
    public function referenceFromWebhook(Request $request): ?string;
}
