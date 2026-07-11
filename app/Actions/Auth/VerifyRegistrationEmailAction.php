<?php

namespace App\Actions\Auth;

use App\Enums\VerificationPurpose;
use App\Exceptions\RegistrationExpiredException;
use App\Models\PendingRegistration;
use App\Services\VerificationCodeService;

class VerifyRegistrationEmailAction
{
    public function __construct(protected VerificationCodeService $codes) {}

    public function handle(string $registrationToken, string $code): PendingRegistration
    {
        $pending = PendingRegistration::query()
            ->where('registration_token', $registrationToken)
            ->first();

        if (! $pending || $pending->isExpired()) {
            throw RegistrationExpiredException::make();
        }

        $this->codes->verify($pending->email, VerificationPurpose::RegistrationEmail, $code);

        $pending->update(['email_verified_at' => now()]);

        return $pending;
    }
}
