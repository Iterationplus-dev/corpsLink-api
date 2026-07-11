<?php

namespace App\Exceptions;

class TwoFactorChallengeExpiredException extends ApiException
{
    public static function make(): self
    {
        return new self(
            'This sign-in challenge has expired or does not exist. Please sign in again.',
            status: 410,
            errorCode: 'two_factor_challenge_expired',
        );
    }
}
