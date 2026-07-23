<?php

namespace App\Actions\Auth;

use App\Actions\Account\RequestEmailChangeAction;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;

/**
 * Single entry point the frontend calls for every "resend my code" case,
 * branching on context to the same generate+notify logic each original
 * flow already uses (VerificationCodeService::generate() invalidates the
 * prior active code first, so calling it again is a safe, valid resend).
 */
class ResendOtpAction
{
    public function __construct(
        protected ResendRegistrationOtpAction $resendRegistration,
        protected ForgotPasswordAction $forgotPassword,
        protected RequestEmailChangeAction $requestEmailChange,
    ) {}

    public function handle(?User $user, string $context, ?string $registrationId, ?string $email): void
    {
        match ($context) {
            'register' => $this->resendRegistration->handle($registrationId),
            'reset_password' => $this->forgotPassword->handle($email),
            'change_email' => $this->resendChangeEmail($user, $email),
            default => throw new \InvalidArgumentException("Unsupported OTP resend context: {$context}"),
        };
    }

    protected function resendChangeEmail(?User $user, string $email): void
    {
        if (! $user) {
            throw new AuthenticationException;
        }

        $this->requestEmailChange->handle($user, $email);
    }
}
