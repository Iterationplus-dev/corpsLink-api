<?php

namespace App\Exceptions;

class NoSeatsAvailableException extends ApiException
{
    public static function make(): self
    {
        return new self(
            'Your payment was received but this vehicle is now completely full. Please contact support — you will not be charged twice.',
            status: 409,
            errorCode: 'no_seats_available',
        );
    }
}
