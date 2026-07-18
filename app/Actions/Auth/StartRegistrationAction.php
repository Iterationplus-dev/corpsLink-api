<?php

namespace App\Actions\Auth;

use App\Enums\VerificationPurpose;
use App\Models\PendingRegistration;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\RegistrationOtpNotification;
use App\Services\VerificationCodeService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class StartRegistrationAction
{
    public function __construct(protected VerificationCodeService $codes) {}

    /**
     * @param  array{name: string, email: string, phone: string}  $data
     */
    public function handle(array $data): PendingRegistration
    {
        $pending = PendingRegistration::query()->create([
            'registration_token' => (string) Str::uuid(),
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'expires_at' => now()->addHours(config('corpslink.registration.ttl_hours')),
        ]);

        $this->issueOtp($pending);

        return $pending;
    }

    protected function issueOtp(PendingRegistration $pending): void
    {
        $issued = $this->codes->generate($pending->email, VerificationPurpose::RegistrationEmail);

        Notification::route('mail', $pending->email)
            ->route(WhatsAppChannel::class, $pending->phone)
            ->notify(new RegistrationOtpNotification($issued['code'], config('corpslink.otp.expiry_minutes')));
    }
}
