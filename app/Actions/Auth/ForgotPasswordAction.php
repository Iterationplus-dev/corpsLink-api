<?php

namespace App\Actions\Auth;

use App\Enums\VerificationPurpose;
use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use App\Services\VerificationCodeService;

class ForgotPasswordAction
{
    public function __construct(protected VerificationCodeService $codes) {}

    /**
     * Silently no-ops for unknown emails to avoid leaking which addresses
     * are registered; the controller always returns the same generic response.
     */
    public function handle(string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return;
        }

        $issued = $this->codes->generate($email, VerificationPurpose::PasswordReset, $user);

        $user->notify(new PasswordResetOtpNotification($issued['code'], config('corpslink.otp.expiry_minutes')));
    }
}
