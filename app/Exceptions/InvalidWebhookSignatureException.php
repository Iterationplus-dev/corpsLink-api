<?php

namespace App\Exceptions;

class InvalidWebhookSignatureException extends ApiException
{
    public static function make(): self
    {
        return new self(
            'Invalid webhook signature.',
            status: 401,
            errorCode: 'invalid_webhook_signature',
        );
    }
}
