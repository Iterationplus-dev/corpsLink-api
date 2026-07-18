<?php

namespace App\Actions\Account;

use App\Enums\VerificationPurpose;
use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\EmailChangeOtpNotification;
use App\Services\AuditLogService;
use App\Services\VerificationCodeService;
use Illuminate\Support\Facades\Notification;

class RequestEmailChangeAction
{
    public function __construct(
        protected VerificationCodeService $codes,
        protected AuditLogService $auditLog,
    ) {}

    public function handle(User $user, string $newEmail): void
    {
        $issued = $this->codes->generate($user->email, VerificationPurpose::EmailChange, $user, $newEmail);

        Notification::route('mail', $newEmail)
            ->route(WhatsAppChannel::class, $user->phone)
            ->notify(new EmailChangeOtpNotification($issued['code'], config('corpslink.otp.expiry_minutes')));

        $this->auditLog->record('email_change_requested', $user, $user, ['new_email' => $newEmail]);
    }
}
