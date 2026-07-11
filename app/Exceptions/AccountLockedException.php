<?php

namespace App\Exceptions;

class AccountLockedException extends ApiException
{
    public static function tooManyFailedLogins(int $lockedForMinutes): self
    {
        return new self(
            "Too many failed sign-in attempts. Please try again in {$lockedForMinutes} minute(s).",
            status: 429,
            errorCode: 'account_locked',
        );
    }
}
