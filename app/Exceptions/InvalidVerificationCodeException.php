<?php

namespace App\Exceptions;

class InvalidVerificationCodeException extends ApiException
{
    public static function notFound(): self
    {
        return new self(
            'This code is invalid or has expired. Please request a new one.',
            status: 422,
            errorCode: 'verification_code_invalid',
        );
    }

    public static function incorrect(int $attemptsRemaining): self
    {
        return new self(
            "That code isn't right. You have {$attemptsRemaining} attempt(s) left.",
            status: 422,
            errorCode: 'verification_code_incorrect',
        );
    }

    public static function attemptsExhausted(): self
    {
        return new self(
            'Too many incorrect attempts. Please request a new code.',
            status: 429,
            errorCode: 'verification_code_locked',
        );
    }
}
