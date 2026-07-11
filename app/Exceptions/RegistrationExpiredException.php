<?php

namespace App\Exceptions;

class RegistrationExpiredException extends ApiException
{
    public static function make(): self
    {
        return new self(
            'This registration session has expired or does not exist. Please start again.',
            status: 410,
            errorCode: 'registration_expired',
        );
    }
}
