<?php

namespace App\Exceptions;

class PaymentVerificationFailedException extends ApiException
{
    public static function make(string $reason = 'Your bank declined the transaction or the network timed out.'): self
    {
        return new self(
            "Payment didn't go through. {$reason} No booking was made.",
            status: 422,
            errorCode: 'payment_failed',
        );
    }
}
