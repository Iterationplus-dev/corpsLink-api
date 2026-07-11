<?php

namespace App\Exceptions;

class SeatUnavailableException extends ApiException
{
    public static function make(?int $suggestedSeatNumber = null): self
    {
        return new self(
            'That seat was just taken. Please pick another.',
            status: 409,
            errorCode: 'seat_unavailable',
            errors: $suggestedSeatNumber ? ['suggested_seat_number' => $suggestedSeatNumber] : [],
        );
    }
}
