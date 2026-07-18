<?php

namespace App\Actions\Auth;

use App\Enums\VerificationPurpose;
use App\Exceptions\RegistrationExpiredException;
use App\Models\PendingRegistration;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\RegistrationOtpNotification;
use App\Services\VerificationCodeService;
use Illuminate\Support\Facades\Notification;

class ChangeRegistrationEmailAction
{
    public function __construct(protected VerificationCodeService $codes) {}

    public function handle(string $registrationToken, string $newEmail): PendingRegistration
    {
        $pending = PendingRegistration::query()
            ->where('registration_token', $registrationToken)
            ->first();

        if (! $pending || $pending->isExpired()) {
            throw RegistrationExpiredException::make();
        }

        $pending->update(['email' => $newEmail, 'email_verified_at' => null]);

        $issued = $this->codes->generate($pending->email, VerificationPurpose::RegistrationEmail);

        Notification::route('mail', $pending->email)
            ->route(WhatsAppChannel::class, $pending->phone)
            ->notify(new RegistrationOtpNotification($issued['code'], config('corpslink.otp.expiry_minutes')));

        return $pending;
    }
}
